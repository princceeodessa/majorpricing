<?php

namespace App\Services\OneC;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\OneCPriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OneCCatalogExchangeService
{
    public function __construct(
        private readonly OneCExchangeStorage $storage,
    ) {
    }

    /**
     * @return array{
     *     categories:int,
     *     products:int,
     *     prices:int,
     *     images:int,
     *     files:array<int, string>,
     *     has_import:bool,
     *     has_offers:bool,
     *     offers_without_products:int,
     *     warnings:array<int, string>
     * }
     */
    public function import(string $sessionKey): array
    {
        $result = [
            'categories' => 0,
            'products' => 0,
            'prices' => 0,
            'images' => 0,
            'files' => [],
            'has_import' => false,
            'has_offers' => false,
            'offers_without_products' => 0,
            'warnings' => [],
        ];

        foreach ($this->storage->xmlFiles($sessionKey, 'catalog') as $xmlFile) {
            $filename = basename($xmlFile);
            $result['files'][] = $filename;
            $result['has_import'] = $result['has_import'] || $filename === 'import.xml';
            $result['has_offers'] = $result['has_offers'] || $filename === 'offers.xml';

            $xml = $this->storage->fileContents($xmlFile);

            if ($xml === null) {
                continue;
            }

            $xpath = $this->createXPath($xml);

            if (! $xpath) {
                continue;
            }

            if ($this->hasAnyNode($xpath, ['Классификатор', 'Р С™Р В»Р В°РЎРѓРЎРѓР С‘РЎвЂћР С‘Р С”Р В°РЎвЂљР С•РЎР‚'])) {
                $result['categories'] += $this->importCategories($xpath);
            }

            if ($this->hasAnyNode($xpath, ['Товар', 'Р СћР С•Р Р†Р В°РЎР‚'])) {
                $result['products'] += $this->importProducts($xpath, $sessionKey);
            }

            if ($this->hasAnyNode($xpath, ['ПакетПредложений', 'Р СџР В°Р С”Р ВµРЎвЂљР СџРЎР‚Р ВµР Т‘Р В»Р С•Р В¶Р ВµР Р…Р С‘Р в„–'])) {
                $offersResult = $this->importOffers($xpath);
                $result['prices'] += $offersResult['prices'];
                $result['offers_without_products'] += $offersResult['offers_without_products'];
            }
        }

        if (! $result['has_import']) {
            $result['warnings'][] = 'В пакете нет import.xml. 1С прислала цены или предложения без каталога товаров.';
        }

        if (! $result['has_offers']) {
            $result['warnings'][] = 'В пакете нет offers.xml. Товары и категории могут загрузиться, но цены не обновятся.';
        }

        if ($result['offers_without_products'] > 0) {
            $result['warnings'][] = 'Часть предложений из offers.xml не привязалась к товарам: '.$result['offers_without_products'].'. Обычно это происходит, когда import.xml не был загружен или не распарсился.';
        }

        if ($result['has_offers'] && ! $result['has_import'] && $result['products'] === 0 && $result['prices'] === 0) {
            $result['warnings'][] = 'Пакет содержит только offers.xml, поэтому импорт завершился без изменений. Сначала нужен import.xml с товарами и категориями.';
        }

        return $result;
    }

    private function importCategories(DOMXPath $xpath): int
    {
        $count = 0;
        $rootGroups = $this->queryChildren(
            $xpath,
            '//*',
            null,
            [
                ['Классификатор', 'Р С™Р В»Р В°РЎРѓРЎРѓР С‘РЎвЂћР С‘Р С”Р В°РЎвЂљР С•РЎР‚'],
                ['Группы', 'Р вЂњРЎР‚РЎС“Р С—Р С—РЎвЂ№'],
                ['Группа', 'Р вЂњРЎР‚РЎС“Р С—Р С—Р В°'],
            ],
        );

        foreach ($rootGroups as $index => $groupNode) {
            if ($groupNode instanceof DOMElement) {
                $count += $this->upsertCategoryNode($xpath, $groupNode, null, $index);
            }
        }

        return $count;
    }

    private function upsertCategoryNode(DOMXPath $xpath, DOMElement $groupNode, ?Category $parent, int $sortOrder): int
    {
        $oneCId = $this->firstChildValue($xpath, $groupNode, ['Ид', 'Р ВР Т‘']);
        $name = $this->firstChildValue($xpath, $groupNode, ['Наименование', 'Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р Вµ']);

        if (blank($oneCId) || blank($name)) {
            return 0;
        }

        $category = $this->resolveCategoryForSync($name, $oneCId, $parent);

        if (! $category->exists) {
            $category->slug = $this->uniqueSlug($name, Category::class);
            $category->accent_color = '#d11117';
        }

        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $name,
            'description' => $this->firstChildValue($xpath, $groupNode, ['Описание', 'Р С›Р С—Р С‘РЎРѓР В°Р Р…Р С‘Р Вµ']),
            'sort_order' => $sortOrder,
        ]);
        $category->save();

        $count = 1;
        $children = $this->queryChildren(
            $xpath,
            '.',
            $groupNode,
            [
                ['Группы', 'Р вЂњРЎР‚РЎС“Р С—Р С—РЎвЂ№'],
                ['Группа', 'Р вЂњРЎР‚РЎС“Р С—Р С—Р В°'],
            ],
        );

        foreach ($children as $index => $childNode) {
            if ($childNode instanceof DOMElement) {
                $count += $this->upsertCategoryNode($xpath, $childNode, $category, $index);
            }
        }

        return $count;
    }

    private function importProducts(DOMXPath $xpath, string $sessionKey): int
    {
        $count = 0;
        $productNodes = $this->queryChildren(
            $xpath,
            '//*',
            null,
            [
                ['Каталог', 'Р С™Р В°РЎвЂљР В°Р В»Р С•Р С–'],
                ['Товары', 'Р СћР С•Р Р†Р В°РЎР‚РЎвЂ№'],
                ['Товар', 'Р СћР С•Р Р†Р В°РЎР‚'],
            ],
        );

        foreach ($productNodes as $index => $productNode) {
            if (! $productNode instanceof DOMElement) {
                continue;
            }

            $oneCId = $this->firstChildValue($xpath, $productNode, ['Ид', 'Р ВР Т‘']);
            $baseTitle = $this->firstChildValue($xpath, $productNode, ['Наименование', 'Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р Вµ']);
            $title = $this->resolvePublicProductTitle($xpath, $productNode, $baseTitle);

            if (blank($oneCId) || blank($title)) {
                continue;
            }

            $groupIds = $this->childValues($xpath, $productNode, [
                ['Группы', 'Р вЂњРЎР‚РЎС“Р С—Р С—РЎвЂ№'],
                ['Ид', 'Р ВР Т‘'],
            ]);

            if ($groupIds === []) {
                $groupIds = $this->childValues($xpath, $productNode, [
                    ['Группа', 'Р вЂњРЎР‚РЎС“Р С—Р С—Р В°'],
                ]);
            }

            $category = filled($groupIds[0] ?? null)
                ? Category::query()->where('one_c_id', $groupIds[0])->first()
                : null;

            $oneCCode = $this->resolveProductCode($xpath, $productNode);
            $vendorCode = $this->firstChildValue($xpath, $productNode, ['РђСЂС‚РёРєСѓР»', 'РВРЎвЂ™РРЋРвЂљРРЋРІР‚С™РВРЎвЂРВРЎвЂќРРЋРЎвЂњРВР’В»']);
            $brandName = $this->resolveProductBrand($xpath, $productNode);

            $product = $this->resolveProductForSync(
                oneCId: $oneCId,
                title: $title,
                baseTitle: $baseTitle,
                category: $category,
                vendorCode: $vendorCode,
                oneCCode: $oneCCode,
            );

            if (! $product->exists) {
                $product->slug = $this->uniqueSlug($title, Product::class);
            }

            $unitLabel = $this->resolveUnitLabel($xpath, $productNode);
            $imagePath = null;

            foreach ($this->childValues($xpath, $productNode, [['Картинка', 'Р С™Р В°РЎР‚РЎвЂљР С‘Р Р…Р С”Р В°']]) as $pictureRef) {
                $imagePath = $this->copyUploadedImage($sessionKey, $pictureRef, $title, $oneCId);

                if ($imagePath !== null) {
                    break;
                }
            }

            $isNewProduct = ! $product->exists;

            $product->fill([
                'category_id' => $category?->id,
                'title' => $title,
                'name' => $baseTitle ?: $title,
                'one_c_code' => $oneCCode,
                'vendor_code' => $this->firstChildValue($xpath, $productNode, ['Артикул', 'Р С’РЎР‚РЎвЂљР С‘Р С”РЎС“Р В»']),
                'brand_name' => $brandName,
                'measurement_label' => $unitLabel,
                'measurement_value' => $this->firstChildValue($xpath, $productNode, ['НаименованиеПолное', 'Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р ВµР СџР С•Р В»Р Р…Р С•Р Вµ']),
                'description' => $this->firstChildValue($xpath, $productNode, ['Описание', 'Р С›Р С—Р С‘РЎРѓР В°Р Р…Р С‘Р Вµ']),
                'source_sheet' => $isNewProduct ? '1C' : $product->source_sheet,
                'source_row' => $isNewProduct ? null : $product->source_row,
                'sort_order' => $index,
                'image_path' => $imagePath ?? $product->image_path,
            ]);
            $product->save();

            $count++;
        }

        return $count;
    }

    /**
     * @return array{prices:int,offers_without_products:int}
     */
    private function importOffers(DOMXPath $xpath): array
    {
        $priceTypeMap = $this->syncPriceTypes($xpath);
        $count = 0;
        $offersWithoutProducts = 0;
        $offerNodes = $this->queryChildren(
            $xpath,
            '//*',
            null,
            [
                ['Предложения', 'Р СџРЎР‚Р ВµР Т‘Р В»Р С•Р В¶Р ВµР Р…Р С‘РЎРЏ'],
                ['Предложение', 'Р СџРЎР‚Р ВµР Т‘Р В»Р С•Р В¶Р ВµР Р…Р С‘Р Вµ'],
            ],
        );

        foreach ($offerNodes as $offerNode) {
            if (! $offerNode instanceof DOMElement) {
                continue;
            }

            $offerId = $this->firstChildValue($xpath, $offerNode, ['Ид', 'Р ВР Т‘']);

            if (blank($offerId)) {
                continue;
            }

            $productOneCId = Str::before($offerId, '#');
            $product = Product::query()->where('one_c_id', $productOneCId)->first();

            if (! $product) {
                $offersWithoutProducts++;
                continue;
            }

            $resolvedMinimums = [];
            $publicMinimums = [];
            $priceNodes = $this->queryChildren(
                $xpath,
                '.',
                $offerNode,
                [
                    ['Цены', 'Р В¦Р ВµР Р…РЎвЂ№'],
                    ['Цена', 'Р В¦Р ВµР Р…Р В°'],
                ],
            );

            foreach ($priceNodes as $priceNode) {
                if (! $priceNode instanceof DOMElement) {
                    continue;
                }

                $priceTypeId = $this->firstChildValue($xpath, $priceNode, ['ИдТипаЦены', 'Р ВР Т‘Р СћР С‘Р С—Р В°Р В¦Р ВµР Р…РЎвЂ№']);
                $amount = $this->normalizeDecimal($this->firstChildValue($xpath, $priceNode, ['ЦенаЗаЕдиницу', 'Р В¦Р ВµР Р…Р В°Р вЂ”Р В°Р вЂўР Т‘Р С‘Р Р…Р С‘РЎвЂ РЎС“']));
                $columnIndex = $priceTypeMap[$priceTypeId]['column_index'] ?? null;
                $label = $priceTypeMap[$priceTypeId]['name'] ?? null;

                if ($columnIndex === null) {
                    continue;
                }

                ProductPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'column_index' => $columnIndex,
                    ],
                    [
                        'label' => $label ?: ('Цена '.$columnIndex),
                        'display_value' => $amount !== null ? number_format($amount, 2, ',', ' ') : null,
                        'min_amount' => $amount,
                    ],
                );

                if ($amount !== null) {
                    $resolvedMinimums[$columnIndex] = $amount;

                    if ($label !== null && in_array(trim($label), Product::publicPricePriority(), true)) {
                        $publicMinimums[$columnIndex] = $amount;
                    }
                }

                $count++;
            }

            if ($resolvedMinimums !== []) {
                $chosenMinimums = $publicMinimums !== [] ? $publicMinimums : $resolvedMinimums;
                ksort($chosenMinimums);

                $product->forceFill([
                    'price_from' => reset($chosenMinimums),
                ])->save();
            }
        }

        return [
            'prices' => $count,
            'offers_without_products' => $offersWithoutProducts,
        ];
    }

    /**
     * @return array<string, array{name:string,column_index:int}>
     */
    private function syncPriceTypes(DOMXPath $xpath): array
    {
        $map = [];
        $priceTypeNodes = $this->queryChildren(
            $xpath,
            '//*',
            null,
            [
                ['ТипыЦен', 'Р СћР С‘Р С—РЎвЂ№Р В¦Р ВµР Р…'],
                ['ТипЦены', 'Р СћР С‘Р С—Р В¦Р ВµР Р…РЎвЂ№'],
            ],
        );

        foreach ($priceTypeNodes as $priceTypeNode) {
            if (! $priceTypeNode instanceof DOMElement) {
                continue;
            }

            $oneCId = $this->firstChildValue($xpath, $priceTypeNode, ['Ид', 'Р ВР Т‘']);
            $name = $this->firstChildValue($xpath, $priceTypeNode, ['Наименование', 'Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р Вµ']);

            if (blank($oneCId) || blank($name)) {
                continue;
            }

            $priceType = OneCPriceType::query()->firstOrNew([
                'one_c_id' => $oneCId,
            ]);

            if (! $priceType->exists) {
                $nextColumn = (int) OneCPriceType::query()->max('column_index') + 1;
                $priceType->column_index = max(1, $nextColumn);
            }

            $priceType->name = $name;
            $priceType->save();

            $map[$oneCId] = [
                'name' => $priceType->name,
                'column_index' => (int) $priceType->column_index,
            ];
        }

        return $map;
    }

    private function resolveProductTitle(DOMXPath $xpath, DOMElement $productNode, ?string $fallback): ?string
    {
        return $this->firstFilled([
            $this->requisiteValue($xpath, $productNode, ['НаименованиеДляПечати', 'Наименование для печати']),
            $this->firstChildValue($xpath, $productNode, ['НаименованиеДляПечати']),
            $this->firstChildValue($xpath, $productNode, ['НаименованиеПолное', 'Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р ВµР СџР С•Р В»Р Р…Р С•Р Вµ']),
            $fallback,
        ]);
    }

    private function resolvePublicProductTitle(DOMXPath $xpath, DOMElement $productNode, ?string $fallback): ?string
    {
        $printTitleKeys = [
            json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435\u0414\u043b\u044f\u041f\u0435\u0447\u0430\u0442\u0438"'),
            json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435 \u0434\u043b\u044f \u043f\u0435\u0447\u0430\u0442\u0438"'),
            json_decode('"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435\u0414\u043b\u044f\u041f\u0435\u0447\u0430\u0442\u0438"'),
            json_decode('"\u041d\u0430\u0437\u0432\u0430\u043d\u0438\u0435 \u0434\u043b\u044f \u043f\u0435\u0447\u0430\u0442\u0438"'),
        ];

        $fullNameKeys = [
            json_decode('"\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435\u041f\u043e\u043b\u043d\u043e\u0435"'),
            json_decode('"\u041f\u043e\u043b\u043d\u043e\u0435\u041d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"'),
            json_decode('"\u041f\u043e\u043b\u043d\u043e\u0435 \u043d\u0430\u0438\u043c\u0435\u043d\u043e\u0432\u0430\u043d\u0438\u0435"'),
        ];

        return $this->firstFilled([
            $this->requisiteValue($xpath, $productNode, $printTitleKeys),
            $this->firstChildValue($xpath, $productNode, $printTitleKeys),
            $this->requisiteValueByNameFragments($xpath, $productNode, [
                [json_decode('"\u043f\u0435\u0447\u0430\u0442"'), json_decode('"\u043d\u0430\u0438\u043c"')],
                [json_decode('"\u043f\u0435\u0447\u0430\u0442"'), json_decode('"\u043d\u0430\u0437\u0432"')],
                [json_decode('"\u043f\u0435\u0447\u0430\u0442"')],
            ]),
            $this->requisiteValue($xpath, $productNode, $fullNameKeys),
            $this->firstChildValue($xpath, $productNode, $fullNameKeys),
            $this->resolveProductTitle($xpath, $productNode, $fallback),
            $fallback,
        ]);
    }

    private function resolveProductCode(DOMXPath $xpath, DOMElement $productNode): ?string
    {
        return $this->firstFilled([
            $this->firstChildValue($xpath, $productNode, ['Код', 'РљРѕРґ']),
            $this->requisiteValue($xpath, $productNode, ['Код']),
        ]);
    }

    private function resolveProductBrand(DOMXPath $xpath, DOMElement $productNode): ?string
    {
        return $this->firstFilled([
            $this->firstChildValue($xpath, $productNode, ['Бренд']),
            $this->requisiteValue($xpath, $productNode, ['Бренд']),
        ]);
    }

    private function resolveCategoryForSync(string $name, string $oneCId, ?Category $parent): Category
    {
        $existingOneC = Category::query()->where('one_c_id', $oneCId)->first();
        $legacyMatch = $this->findCategoryByNormalizedName($name, $parent, $existingOneC?->id);

        if ($existingOneC && $legacyMatch && ! $existingOneC->is($legacyMatch)) {
            return $this->mergeCategories($legacyMatch, $existingOneC);
        }

        return $existingOneC ?? $legacyMatch ?? new Category([
            'one_c_id' => $oneCId,
        ]);
    }

    private function findCategoryByNormalizedName(string $name, ?Category $parent, ?int $exceptId = null): ?Category
    {
        $nameKey = $this->syncKey($name);

        if ($nameKey === '') {
            return null;
        }

        $query = Category::query();

        if ($parent) {
            $query->where('parent_id', $parent->id);
        } else {
            $query->whereNull('parent_id');
        }

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $candidates = $query->get()->filter(function (Category $category) use ($nameKey): bool {
            return $this->syncKey($category->name) === $nameKey;
        });

        return $this->pickPreferredCategoryCandidate($candidates->all());
    }

    private function pickPreferredCategoryCandidate(array $categories): ?Category
    {
        if ($categories === []) {
            return null;
        }

        usort($categories, function (Category $left, Category $right): int {
            $score = $this->categorySyncScore($right) <=> $this->categorySyncScore($left);

            return $score !== 0 ? $score : ($left->id <=> $right->id);
        });

        return $categories[0] ?? null;
    }

    private function categorySyncScore(Category $category): int
    {
        $score = 0;

        if (blank($category->one_c_id)) {
            $score += 100;
        }

        if ($category->source_sheet !== '1C') {
            $score += 50;
        }

        return $score;
    }

    private function mergeCategories(Category $target, Category $duplicate): Category
    {
        if ($target->is($duplicate)) {
            return $target;
        }

        $duplicateOneCId = $duplicate->one_c_id;
        $targetHadOneCId = filled($target->one_c_id);

        DB::transaction(function () use ($target, $duplicate, $duplicateOneCId, $targetHadOneCId): void {
            Category::query()
                ->where('parent_id', $duplicate->id)
                ->update(['parent_id' => $target->id]);

            Product::query()
                ->where('category_id', $duplicate->id)
                ->update(['category_id' => $target->id]);

            if (! $targetHadOneCId && filled($duplicateOneCId)) {
                $duplicate->forceFill(['one_c_id' => null])->save();
            }

            $target->forceFill([
                'one_c_id' => $target->one_c_id ?: $duplicateOneCId,
                'description' => $target->description ?: $duplicate->description,
                'accent_color' => $target->accent_color ?: $duplicate->accent_color,
                'source_sheet' => $target->source_sheet ?: $duplicate->source_sheet,
            ])->save();

            $duplicate->delete();
        });

        return $target->fresh() ?? $target;
    }

    private function resolveProductForSync(
        string $oneCId,
        string $title,
        ?string $baseTitle,
        ?Category $category,
        ?string $vendorCode,
        ?string $oneCCode,
    ): Product {
        $existingOneC = Product::query()->where('one_c_id', $oneCId)->first();
        $legacyMatch = $this->findLegacyProductCandidate(
            title: $title,
            baseTitle: $baseTitle,
            category: $category,
            vendorCode: $vendorCode,
            oneCCode: $oneCCode,
            exceptId: $existingOneC?->id,
        );

        if ($existingOneC && $legacyMatch && ! $existingOneC->is($legacyMatch)) {
            return $this->mergeProducts($legacyMatch, $existingOneC, $category);
        }

        return $existingOneC ?? $legacyMatch ?? new Product([
            'one_c_id' => $oneCId,
        ]);
    }

    private function findLegacyProductCandidate(
        string $title,
        ?string $baseTitle,
        ?Category $category,
        ?string $vendorCode,
        ?string $oneCCode,
        ?int $exceptId = null,
    ): ?Product {
        $candidates = [];

        $byVendorCode = $this->findProductByExactField('vendor_code', $vendorCode, $category, $exceptId);

        if ($byVendorCode) {
            $candidates[] = $byVendorCode;
        }

        $byOneCCode = $this->findProductByExactField('one_c_code', $oneCCode, $category, $exceptId);

        if ($byOneCCode) {
            $candidates[] = $byOneCCode;
        }

        $byTitle = $this->findProductByNormalizedTitle($title, $baseTitle, $category, $exceptId);

        if ($byTitle) {
            $candidates[] = $byTitle;
        }

        $legacyCandidates = collect($candidates)
            ->filter(fn (Product $product): bool => $this->looksLikeLegacyCatalogProduct($product))
            ->unique('id')
            ->all();

        return $this->pickPreferredProductCandidate($legacyCandidates, $category);
    }

    private function findProductByExactField(string $field, ?string $value, ?Category $category, ?int $exceptId = null): ?Product
    {
        if (blank($value)) {
            return null;
        }

        $query = Product::query()->where($field, trim((string) $value));

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $this->pickPreferredProductCandidate($query->get()->all(), $category);
    }

    private function findProductByNormalizedTitle(string $title, ?string $baseTitle, ?Category $category, ?int $exceptId = null): ?Product
    {
        $keys = collect([$title, $baseTitle])
            ->map(fn (?string $value): string => $this->syncKey($value))
            ->filter()
            ->unique()
            ->values();

        if ($keys->isEmpty()) {
            return null;
        }

        $query = Product::query();

        if ($category) {
            $query->where('category_id', $category->id);
        }

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $candidates = $query->get()->filter(function (Product $product) use ($keys): bool {
            return collect([$product->title, $product->name])
                ->map(fn (?string $value): string => $this->syncKey($value))
                ->filter()
                ->contains(fn (string $value): bool => $keys->contains($value));
        })->all();

        return $this->pickPreferredProductCandidate($candidates, $category);
    }

    private function pickPreferredProductCandidate(array $products, ?Category $category): ?Product
    {
        if ($products === []) {
            return null;
        }

        usort($products, function (Product $left, Product $right) use ($category): int {
            $score = $this->productSyncScore($right, $category) <=> $this->productSyncScore($left, $category);

            return $score !== 0 ? $score : ($left->id <=> $right->id);
        });

        return $products[0] ?? null;
    }

    private function productSyncScore(Product $product, ?Category $category): int
    {
        $score = 0;

        if (blank($product->one_c_id)) {
            $score += 100;
        }

        if ($this->looksLikeLegacyCatalogProduct($product)) {
            $score += 70;
        }

        if (filled($product->image_path)) {
            $score += 30;
        }

        if ($category && $product->category_id === $category->id) {
            $score += 40;
        }

        return $score;
    }

    private function looksLikeLegacyCatalogProduct(Product $product): bool
    {
        return $product->source_sheet !== '1C';
    }

    private function mergeProducts(Product $target, Product $duplicate, ?Category $category): Product
    {
        if ($target->is($duplicate)) {
            return $target;
        }

        $duplicateOneCId = $duplicate->one_c_id;
        $targetHadOneCId = filled($target->one_c_id);

        DB::transaction(function () use ($target, $duplicate, $category, $duplicateOneCId, $targetHadOneCId): void {
            CartItem::query()
                ->where('product_id', $duplicate->id)
                ->get()
                ->each(function (CartItem $cartItem) use ($target): void {
                    $existing = CartItem::query()
                        ->where('user_id', $cartItem->user_id)
                        ->where('product_id', $target->id)
                        ->first();

                    if ($existing) {
                        $existing->increment('quantity', $cartItem->quantity);
                        $cartItem->delete();

                        return;
                    }

                    $cartItem->update(['product_id' => $target->id]);
                });

            FavoriteItem::query()
                ->where('product_id', $duplicate->id)
                ->get()
                ->each(function (FavoriteItem $favoriteItem) use ($target): void {
                    $exists = FavoriteItem::query()
                        ->where('user_id', $favoriteItem->user_id)
                        ->where('product_id', $target->id)
                        ->exists();

                    if ($exists) {
                        $favoriteItem->delete();

                        return;
                    }

                    $favoriteItem->update(['product_id' => $target->id]);
                });

            $duplicate->orderItems()->update(['product_id' => $target->id]);
            $duplicate->prices()->delete();

            if (! $targetHadOneCId && filled($duplicateOneCId)) {
                $duplicate->forceFill(['one_c_id' => null])->save();
            }

            $target->forceFill([
                'one_c_id' => $target->one_c_id ?: $duplicateOneCId,
                'vendor_code' => $target->vendor_code ?: $duplicate->vendor_code,
                'one_c_code' => $target->one_c_code ?: $duplicate->one_c_code,
                'brand_name' => $target->brand_name ?: $duplicate->brand_name,
                'measurement_label' => $target->measurement_label ?: $duplicate->measurement_label,
                'measurement_value' => $target->measurement_value ?: $duplicate->measurement_value,
                'description' => $target->description ?: $duplicate->description,
                'image_path' => $target->image_path ?: $duplicate->image_path,
                'category_id' => $target->category_id ?: $category?->id ?: $duplicate->category_id,
            ])->save();

            $duplicate->delete();
        });

        return $target->fresh() ?? $target;
    }

    private function resolveUnitLabel(DOMXPath $xpath, DOMElement $productNode): ?string
    {
        $measureNode = $this->queryChildren(
            $xpath,
            '.',
            $productNode,
            [
                ['БазоваяЕдиница', 'Р вЂР В°Р В·Р С•Р Р†Р В°РЎРЏР вЂўР Т‘Р С‘Р Р…Р С‘РЎвЂ Р В°'],
            ],
        )->item(0);

        if (! $measureNode instanceof DOMElement) {
            return null;
        }

        return $this->firstFilled([
            $measureNode->getAttribute('НаименованиеКраткое'),
            $measureNode->getAttribute('Р СњР В°Р С‘Р СР ВµР Р…Р С•Р Р†Р В°Р Р…Р С‘Р ВµР С™РЎР‚Р В°РЎвЂљР С”Р С•Р Вµ'),
            trim($measureNode->textContent),
        ]);
    }

    private function copyUploadedImage(string $sessionKey, string $pictureRef, string $title, string $oneCId): ?string
    {
        $source = $this->storage->resolveUploadedPath($sessionKey, 'catalog', $pictureRef);

        if ($source === null || ! is_file($source)) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg';
        $filename = Str::slug($title ?: $oneCId).'-'.substr(sha1($oneCId.$pictureRef), 0, 10).'.'.$extension;
        $relativePath = 'catalog-media/1c/'.$filename;
        $absolutePath = public_path($relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::copy($source, $absolutePath);

        return $relativePath;
    }

    private function createXPath(string $xml): ?DOMXPath
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $document = $this->loadCommerceXml($xml);

        if ($document) {
            return new DOMXPath($document);
        }

        if (! str_contains($xml, 'Р ')) {
            return null;
        }

        $legacyCandidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $xml);
        $document = is_string($legacyCandidate) ? $this->loadCommerceXml($legacyCandidate) : null;

        return $document ? new DOMXPath($document) : null;
    }

    private function loadCommerceXml(string $xml): ?DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;

        if (! @$document->loadXML($xml, LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            return null;
        }

        $xpath = new DOMXPath($document);

        return $this->hasAnyNode($xpath, [
            'КоммерческаяИнформация',
            'Классификатор',
            'Каталог',
            'ПакетПредложений',
            'Р С™Р С•Р СР СР ВµРЎР‚РЎвЂЎР ВµРЎРѓР С”Р В°РЎРЏР ВР Р…РЎвЂћР С•РЎР‚Р СР В°РЎвЂ Р С‘РЎРЏ',
            'Р С™Р В»Р В°РЎРѓРЎРѓР С‘РЎвЂћР С‘Р С”Р В°РЎвЂљР С•РЎР‚',
            'Р С™Р В°РЎвЂљР В°Р В»Р С•Р С–',
            'Р СџР В°Р С”Р ВµРЎвЂљР СџРЎР‚Р ВµР Т‘Р В»Р С•Р В¶Р ВµР Р…Р С‘Р в„–',
        ]) ? $document : null;
    }

    /**
     * @param  array<int, string>  $localNames
     */
    private function hasAnyNode(DOMXPath $xpath, array $localNames): bool
    {
        return $xpath->query('//*['.$this->localNamePredicate($localNames).']')->length > 0;
    }

    /**
     * @param  array<int, array<int, string>>  $levels
     */
    private function queryChildren(DOMXPath $xpath, string $base, ?DOMNode $contextNode, array $levels)
    {
        $query = $base;

        foreach ($levels as $index => $level) {
            $segment = '*['.$this->localNamePredicate($level).']';

            if ($index === 0 && $base === '//*') {
                $query .= '['.$this->localNamePredicate($level).']';

                continue;
            }

            $query .= '/'.$segment;
        }

        return $xpath->query($query, $contextNode);
    }

    /**
     * @param  array<int, string>|string  $localNames
     */
    private function firstChildValue(DOMXPath $xpath, DOMNode $contextNode, array|string $localNames): ?string
    {
        $names = is_array($localNames) ? $localNames : [$localNames];
        $node = $xpath->query('./*['.$this->localNamePredicate($names).']', $contextNode)->item(0);

        return $node ? trim($node->textContent) : null;
    }

    /**
     * @param  array<int, array<int, string>>  $levels
     * @return array<int, string>
     */
    private function childValues(DOMXPath $xpath, DOMNode $contextNode, array $levels): array
    {
        $nodes = $this->queryChildren($xpath, '.', $contextNode, $levels);

        if (! $nodes) {
            return [];
        }

        return collect(iterator_to_array($nodes))
            ->map(fn (DOMNode $node): string => trim($node->textContent))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $localNames
     */
    private function localNamePredicate(array $localNames): string
    {
        return collect($localNames)
            ->map(fn (string $name): string => 'local-name()="'.$name.'"')
            ->implode(' or ');
    }

    private function normalizeDecimal(?string $value): ?float
    {
        if (blank($value)) {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    private function uniqueSlug(string $value, string $modelClass): string
    {
        $base = Str::slug($value, '-', 'ru') ?: Str::slug(Str::transliterate($value)) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $requisiteNames
     */
    private function requisiteValue(DOMXPath $xpath, DOMNode $contextNode, array $requisiteNames): ?string
    {
        $requisites = $this->queryChildren(
            $xpath,
            '.',
            $contextNode,
            [
                ['ЗначенияРеквизитов'],
                ['ЗначениеРеквизита'],
            ],
        );

        foreach ($requisites as $requisite) {
            if (! $requisite instanceof DOMElement) {
                continue;
            }

            $name = $this->firstChildValue($xpath, $requisite, ['Наименование']);

            if (! filled($name) || ! in_array(trim($name), $requisiteNames, true)) {
                continue;
            }

            return $this->firstChildValue($xpath, $requisite, ['Значение']);
        }

        return null;
    }

    /**
     * @param  array<int, array<int, string>>  $fragmentSets
     */
    private function requisiteValueByNameFragments(DOMXPath $xpath, DOMNode $contextNode, array $fragmentSets): ?string
    {
        $requisites = $this->queryChildren(
            $xpath,
            '.',
            $contextNode,
            [
                ['Р—РЅР°С‡РµРЅРёСЏР РµРєРІРёР·РёС‚РѕРІ'],
                ['Р—РЅР°С‡РµРЅРёРµР РµРєРІРёР·РёС‚Р°'],
            ],
        );

        foreach ($requisites as $requisite) {
            if (! $requisite instanceof DOMElement) {
                continue;
            }

            $name = $this->firstChildValue($xpath, $requisite, ['Наименование']);

            if (! filled($name)) {
                continue;
            }

            $normalizedName = mb_strtolower(trim((string) $name));

            foreach ($fragmentSets as $fragments) {
                $matched = collect($fragments)
                    ->filter(fn (?string $fragment): bool => filled($fragment))
                    ->every(fn (string $fragment): bool => mb_stripos($normalizedName, mb_strtolower($fragment)) !== false);

                if ($matched) {
                    return $this->firstChildValue($xpath, $requisite, ['Значение']);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function syncKey(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        $slug = Str::slug(trim((string) $value), '-', 'ru');

        if ($slug !== '') {
            return $slug;
        }

        return Str::slug(Str::transliterate(trim((string) $value)));
    }
}
