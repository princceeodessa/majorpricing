<?php

namespace App\Services\OneC;

use App\Models\Category;
use App\Models\OneCPriceType;
use App\Models\Product;
use App\Models\ProductPrice;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OneCCatalogExchangeService
{
    public function __construct(
        private readonly OneCExchangeStorage $storage,
    ) {
    }

    /**
     * @return array{categories:int,products:int,prices:int,images:int}
     */
    public function import(string $sessionKey): array
    {
        $result = [
            'categories' => 0,
            'products' => 0,
            'prices' => 0,
            'images' => 0,
        ];

        foreach ($this->storage->xmlFiles($sessionKey, 'catalog') as $xmlFile) {
            $xml = $this->storage->fileContents($xmlFile);

            if ($xml === null) {
                continue;
            }

            $xpath = $this->createXPath($xml);

            if (! $xpath) {
                continue;
            }

            if ($this->hasAnyNode($xpath, ['Классификатор', 'РљР»Р°СЃСЃРёС„РёРєР°С‚РѕСЂ'])) {
                $result['categories'] += $this->importCategories($xpath);
            }

            if ($this->hasAnyNode($xpath, ['Товар', 'РўРѕРІР°СЂ'])) {
                $result['products'] += $this->importProducts($xpath, $sessionKey);
            }

            if ($this->hasAnyNode($xpath, ['ПакетПредложений', 'РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№'])) {
                $result['prices'] += $this->importOffers($xpath);
            }
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
                ['Классификатор', 'РљР»Р°СЃСЃРёС„РёРєР°С‚РѕСЂ'],
                ['Группы', 'Р“СЂСѓРїРїС‹'],
                ['Группа', 'Р“СЂСѓРїРїР°'],
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
        $oneCId = $this->firstChildValue($xpath, $groupNode, ['Ид', 'РРґ']);
        $name = $this->firstChildValue($xpath, $groupNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']);

        if (blank($oneCId) || blank($name)) {
            return 0;
        }

        $category = Category::query()->firstOrNew([
            'one_c_id' => $oneCId,
        ]);

        if (! $category->exists) {
            $category->slug = $this->uniqueSlug($name, Category::class);
            $category->accent_color = '#d11117';
        }

        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $name,
            'description' => $this->firstChildValue($xpath, $groupNode, ['Описание', 'РћРїРёСЃР°РЅРёРµ']),
            'sort_order' => $sortOrder,
        ]);
        $category->save();

        $count = 1;
        $children = $this->queryChildren(
            $xpath,
            '.',
            $groupNode,
            [
                ['Группы', 'Р“СЂСѓРїРїС‹'],
                ['Группа', 'Р“СЂСѓРїРїР°'],
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
                ['Каталог', 'РљР°С‚Р°Р»РѕРі'],
                ['Товары', 'РўРѕРІР°СЂС‹'],
                ['Товар', 'РўРѕРІР°СЂ'],
            ],
        );

        foreach ($productNodes as $index => $productNode) {
            if (! $productNode instanceof DOMElement) {
                continue;
            }

            $oneCId = $this->firstChildValue($xpath, $productNode, ['Ид', 'РРґ']);
            $title = $this->firstChildValue($xpath, $productNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']);

            if (blank($oneCId) || blank($title)) {
                continue;
            }

            $groupIds = $this->childValues($xpath, $productNode, [
                ['Группы', 'Р“СЂСѓРїРїС‹'],
                ['Ид', 'РРґ'],
            ]);

            if ($groupIds === []) {
                $groupIds = $this->childValues($xpath, $productNode, [
                    ['Группа', 'Р“СЂСѓРїРїР°'],
                ]);
            }

            $category = filled($groupIds[0] ?? null)
                ? Category::query()->where('one_c_id', $groupIds[0])->first()
                : null;

            $product = Product::query()->firstOrNew([
                'one_c_id' => $oneCId,
            ]);

            if (! $product->exists) {
                $product->slug = $this->uniqueSlug($title, Product::class);
            }

            $measureNode = $this->queryChildren(
                $xpath,
                '.',
                $productNode,
                [
                    ['БазоваяЕдиница', 'Р‘Р°Р·РѕРІР°СЏР•РґРёРЅРёС†Р°'],
                ],
            )->item(0);

            $measurementLabel = $measureNode instanceof DOMElement
                ? ($measureNode->getAttribute('НаименованиеКраткое') ?: $measureNode->getAttribute('РќР°РёРјРµРЅРѕРІР°РЅРёРµРљСЂР°С‚РєРѕРµ') ?: trim($measureNode->textContent))
                : null;

            $imagePath = null;
            $pictureRefs = $this->childValues($xpath, $productNode, [
                ['Картинка', 'РљР°СЂС‚РёРЅРєР°'],
            ]);

            foreach ($pictureRefs as $pictureRef) {
                $imagePath = $this->copyUploadedImage($sessionKey, $pictureRef, $title, $oneCId);

                if ($imagePath !== null) {
                    break;
                }
            }

            $product->fill([
                'category_id' => $category?->id,
                'title' => $title,
                'name' => $title,
                'vendor_code' => $this->firstChildValue($xpath, $productNode, ['Артикул', 'РђСЂС‚РёРєСѓР»']),
                'measurement_label' => $measurementLabel,
                'measurement_value' => $this->firstChildValue($xpath, $productNode, ['НаименованиеПолное', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµРџРѕР»РЅРѕРµ']),
                'description' => $this->firstChildValue($xpath, $productNode, ['Описание', 'РћРїРёСЃР°РЅРёРµ']),
                'source_sheet' => '1C',
                'sort_order' => $index,
                'image_path' => $imagePath ?? $product->image_path,
            ]);
            $product->save();

            $count++;
        }

        return $count;
    }

    private function importOffers(DOMXPath $xpath): int
    {
        $priceTypeMap = $this->syncPriceTypes($xpath);
        $count = 0;
        $offerNodes = $this->queryChildren(
            $xpath,
            '//*',
            null,
            [
                ['Предложения', 'РџСЂРµРґР»РѕР¶РµРЅРёСЏ'],
                ['Предложение', 'РџСЂРµРґР»РѕР¶РµРЅРёРµ'],
            ],
        );

        foreach ($offerNodes as $offerNode) {
            if (! $offerNode instanceof DOMElement) {
                continue;
            }

            $offerId = $this->firstChildValue($xpath, $offerNode, ['Ид', 'РРґ']);

            if (blank($offerId)) {
                continue;
            }

            $productOneCId = Str::before($offerId, '#');
            $product = Product::query()->where('one_c_id', $productOneCId)->first();

            if (! $product) {
                continue;
            }

            $resolvedMinimums = [];
            $priceNodes = $this->queryChildren(
                $xpath,
                '.',
                $offerNode,
                [
                    ['Цены', 'Р¦РµРЅС‹'],
                    ['Цена', 'Р¦РµРЅР°'],
                ],
            );

            foreach ($priceNodes as $priceNode) {
                if (! $priceNode instanceof DOMElement) {
                    continue;
                }

                $priceTypeId = $this->firstChildValue($xpath, $priceNode, ['ИдТипаЦены', 'РРґРўРёРїР°Р¦РµРЅС‹']);
                $amount = $this->normalizeDecimal($this->firstChildValue($xpath, $priceNode, ['ЦенаЗаЕдиницу', 'Р¦РµРЅР°Р—Р°Р•РґРёРЅРёС†Сѓ']));
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
                    $resolvedMinimums[] = $amount;
                }

                $count++;
            }

            if ($resolvedMinimums !== []) {
                $product->forceFill([
                    'price_from' => min($resolvedMinimums),
                ])->save();
            }
        }

        return $count;
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
                ['ТипыЦен', 'РўРёРїС‹Р¦РµРЅ'],
                ['ТипЦены', 'РўРёРїР¦РµРЅС‹'],
            ],
        );

        foreach ($priceTypeNodes as $priceTypeNode) {
            if (! $priceTypeNode instanceof DOMElement) {
                continue;
            }

            $oneCId = $this->firstChildValue($xpath, $priceTypeNode, ['Ид', 'РРґ']);
            $name = $this->firstChildValue($xpath, $priceTypeNode, ['Наименование', 'РќР°РёРјРµРЅРѕРІР°РЅРёРµ']);

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

        if (! str_contains($xml, 'Р')) {
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
            'РљРѕРјРјРµСЂС‡РµСЃРєР°СЏРРЅС„РѕСЂРјР°С†РёСЏ',
            'РљР»Р°СЃСЃРёС„РёРєР°С‚РѕСЂ',
            'РљР°С‚Р°Р»РѕРі',
            'РџР°РєРµС‚РџСЂРµРґР»РѕР¶РµРЅРёР№',
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
}
