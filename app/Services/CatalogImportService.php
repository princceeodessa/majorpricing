<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use DateInterval;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;

class CatalogImportService
{
    private const SKIPPED_SHEETS = [
        'ГЛАВНАЯ',
        '🏆ТОП',
        '💥НОВИНКИ',
        'Лист17',
    ];

    private const ACCENT_PALETTE = [
        '#f97316',
        '#0f766e',
        '#2563eb',
        '#b45309',
        '#dc2626',
        '#047857',
        '#7c3aed',
        '#1d4ed8',
        '#be123c',
        '#0f766e',
        '#374151',
        '#a16207',
    ];

    /**
     * @var array<string, bool>
     */
    private array $usedCategorySlugs = [];

    /**
     * @var array<string, bool>
     */
    private array $usedProductSlugs = [];

    /**
     * @var array<string, \App\Models\Category>
     */
    private array $sectionCache = [];

    /**
     * @var array<string, array<int, string>>
     */
    private array $sheetImageMap = [];

    public function __construct(
        private readonly CatalogWorkbookImageService $catalogWorkbookImageService,
    ) {
    }

    /**
     * @return array{sheets:int,sections:int,products:int,prices:int}
     */
    public function import(string $path, ?Command $console = null): array
    {
        DB::disableQueryLog();

        $this->usedCategorySlugs = [];
        $this->usedProductSlugs = [];
        $this->sectionCache = [];
        $this->sheetImageMap = $this->catalogWorkbookImageService->extractSheetImageMap($path);

        $stats = [
            'sheets' => 0,
            'sections' => 0,
            'products' => 0,
            'prices' => 0,
        ];

        Schema::disableForeignKeyConstraints();
        ProductPrice::query()->truncate();
        Product::query()->truncate();
        Category::query()->truncate();
        Schema::enableForeignKeyConstraints();

        $reader = new Reader();
        $reader->open($path);

        $sheetPosition = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = trim($sheet->getName());

            if ($this->shouldSkipSheet($sheetName)) {
                continue;
            }

            $rootCategory = Category::query()->create([
                'name' => $sheetName,
                'slug' => $this->makeUniqueSlug($sheetName, $this->usedCategorySlugs),
                'description' => "Импортировано из листа {$sheetName}.",
                'source_sheet' => $sheetName,
                'sort_order' => $sheetPosition,
                'accent_color' => self::ACCENT_PALETTE[$sheetPosition % count(self::ACCENT_PALETTE)],
            ]);

            $sheetPosition++;
            $stats['sheets']++;

            $console?->line("Импорт листа: {$sheetName}");

            $header = null;
            $currentSection = null;
            $sheetProducts = 0;
            $sheetSections = 0;
            $rowNumber = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $cells = $this->normalizeRow($row);

                if ($this->isEmptyRow($cells)) {
                    continue;
                }

                if ($header === null) {
                    $header = $this->detectHeader($cells);

                    continue;
                }

                if ($this->looksLikeHeaderRow($cells, $header)) {
                    continue;
                }

                $sectionName = $this->detectSection($cells, $header);

                if ($sectionName !== null) {
                    $currentSection = $this->getOrCreateSection($rootCategory, $sectionName, $sheetSections);
                    $sheetSections++;
                    $stats['sections']++;

                    continue;
                }

                $payload = $this->extractProductPayload($cells, $header);

                if ($payload === null) {
                    continue;
                }

                $category = $currentSection ?? $rootCategory;

                $product = $category->products()->create([
                    'title' => $payload['title'],
                    'name' => $payload['name'],
                    'slug' => $this->makeUniqueSlug($category->name.' '.$payload['title'], $this->usedProductSlugs),
                    'measurement_label' => $payload['measurement_label'],
                    'measurement_value' => $payload['measurement_value'],
                    'description' => $payload['description'],
                    'source_sheet' => $sheetName,
                    'source_row' => $rowNumber,
                    'has_video' => $payload['has_video'],
                    'video_label' => $payload['video_label'],
                    'image_path' => $this->sheetImageMap[$sheetName][$rowNumber] ?? null,
                    'price_preview' => $payload['price_preview'],
                    'price_from' => $payload['price_from'],
                    'sort_order' => $sheetProducts,
                ]);

                $product->prices()->createMany($payload['prices']);

                $sheetProducts++;
                $stats['products']++;
                $stats['prices'] += count($payload['prices']);
            }

            if ($sheetProducts === 0 && $rootCategory->children()->count() === 0) {
                $rootCategory->delete();
                $stats['sheets']--;
            }
        }

        $reader->close();

        return $stats;
    }

    public function syncProductImages(string $path): int
    {
        $sheetImageMap = $this->catalogWorkbookImageService->extractSheetImageMap($path);
        $updated = 0;

        Product::query()
            ->whereNotNull('source_sheet')
            ->whereNotNull('source_row')
            ->select(['id', 'source_sheet', 'source_row', 'image_path'])
            ->chunkById(200, function ($products) use ($sheetImageMap, &$updated): void {
                foreach ($products as $product) {
                    $imagePath = $sheetImageMap[$product->source_sheet][$product->source_row] ?? null;

                    if ($product->image_path === $imagePath) {
                        continue;
                    }

                    $product->forceFill([
                        'image_path' => $imagePath,
                    ])->save();

                    $updated++;
                }
            });

        return $updated;
    }

    private function shouldSkipSheet(string $sheetName): bool
    {
        return in_array($sheetName, self::SKIPPED_SHEETS, true);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRow(Row $row): array
    {
        $cells = [];

        for ($index = 0; $index < $row->getNumCells(); $index++) {
            $cells[$index] = $this->normalizeValue($row->cells[$index]->getValue() ?? null);
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if (! blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $cells
     * @return array{name_index:int,measure_index:int|null,measure_label:string|null,price_indexes:array<int,int>,price_labels:array<int,string>,video_index:int|null}|null
     */
    private function detectHeader(array $cells): ?array
    {
        $nameIndex = null;
        $measureIndex = null;
        $measureLabel = null;
        $priceIndexes = [];
        $priceLabels = [];
        $videoIndex = null;

        foreach ($cells as $index => $value) {
            if (str_contains(mb_strtolower($value), 'наименование')) {
                $nameIndex = $index;
                break;
            }
        }

        if ($nameIndex === null) {
            return null;
        }

        foreach ($cells as $index => $value) {
            if ($index === $nameIndex || blank($value)) {
                continue;
            }

            $lowerValue = mb_strtolower($value);

            if (str_contains($lowerValue, 'видео')) {
                $videoIndex = $index;
                continue;
            }

            if (str_contains($lowerValue, 'цена')) {
                $priceIndexes[] = $index;
                $priceLabels[] = $this->normalizeInlineText($value) ?: 'Цена '.count($priceIndexes);
                continue;
            }

            if (
                $measureIndex === null
                && $index > $nameIndex
                && (
                    str_contains($lowerValue, 'длина')
                    || str_contains($lowerValue, 'ед.')
                    || str_contains($lowerValue, 'ед изм')
                    || str_contains($lowerValue, 'ед. изм')
                )
            ) {
                $measureIndex = $index;
                $measureLabel = $this->normalizeInlineText($value);
            }
        }

        $lastIndex = array_key_exists(0, $cells) || $cells !== []
            ? max(array_keys($cells))
            : $nameIndex;

        $rangeStart = ($measureIndex ?? $nameIndex) + 1;
        $rangeEnd = ($videoIndex ?? ($lastIndex + 1)) - 1;

        if ($rangeStart <= $rangeEnd) {
            $resolvedIndexes = [];
            $resolvedLabels = [];

            for ($index = $rangeStart; $index <= $rangeEnd; $index++) {
                if ($index === $measureIndex) {
                    continue;
                }

                $resolvedIndexes[] = $index;
                $resolvedLabels[] = $this->normalizeInlineText($cells[$index] ?? '') ?: 'Цена '.count($resolvedIndexes);
            }

            $priceIndexes = $resolvedIndexes;
            $priceLabels = $resolvedLabels;
        }

        return [
            'name_index' => $nameIndex,
            'measure_index' => $measureIndex,
            'measure_label' => $measureLabel,
            'price_indexes' => $priceIndexes,
            'price_labels' => $priceLabels,
            'video_index' => $videoIndex,
        ];
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{name_index:int,measure_index:int|null,measure_label:string|null,price_indexes:array<int,int>,price_labels:array<int,string>,video_index:int|null}  $header
     */
    private function looksLikeHeaderRow(array $cells, array $header): bool
    {
        $nameCell = mb_strtolower($cells[$header['name_index']] ?? '');

        if (str_contains($nameCell, 'наименование')) {
            return true;
        }

        foreach ($header['price_indexes'] as $priceIndex) {
            if (str_contains(mb_strtolower($cells[$priceIndex] ?? ''), 'цена')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{name_index:int,measure_index:int|null,measure_label:string|null,price_indexes:array<int,int>,price_labels:array<int,string>,video_index:int|null}  $header
     */
    private function detectSection(array $cells, array $header): ?string
    {
        if (! blank($cells[$header['name_index']] ?? '')) {
            return null;
        }

        $leading = [];

        foreach ($cells as $index => $value) {
            if (blank($value)) {
                continue;
            }

            if ($index < $header['name_index']) {
                $leading[] = $value;
                continue;
            }

            return null;
        }

        if ($leading === []) {
            return null;
        }

        $section = $this->normalizeInlineText(implode(' ', $leading));
        $sectionLower = mb_strtolower($section);

        if (
            blank($section)
            || str_contains($sectionLower, 'каталог продукции')
            || str_contains($sectionLower, 'бесплатная доставка')
        ) {
            return null;
        }

        return $section;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array{name_index:int,measure_index:int|null,measure_label:string|null,price_indexes:array<int,int>,price_labels:array<int,string>,video_index:int|null}  $header
     * @return array{
     *     title:string,
     *     name:string,
     *     measurement_label:string|null,
     *     measurement_value:string|null,
     *     description:string|null,
     *     has_video:bool,
     *     video_label:string|null,
     *     price_preview:string|null,
     *     price_from:float|null,
     *     prices:array<int, array{column_index:int,label:string,display_value:string,min_amount:float|null}>
     * }|null
     */
    private function extractProductPayload(array $cells, array $header): ?array
    {
        $name = $this->normalizeMultilineText($cells[$header['name_index']] ?? '');

        if (blank($name) || mb_strtolower($name) === 'наименование') {
            return null;
        }

        $measureValue = null;

        if ($header['measure_index'] !== null) {
            $measureValue = $this->normalizeMultilineText($cells[$header['measure_index']] ?? '');
        }

        $prices = [];

        foreach ($header['price_indexes'] as $offset => $priceIndex) {
            $displayValue = $this->normalizePriceDisplay($cells[$priceIndex] ?? '');

            if (blank($displayValue)) {
                continue;
            }

            $prices[] = [
                'column_index' => $offset + 1,
                'label' => $header['price_labels'][$offset] ?? 'Цена '.($offset + 1),
                'display_value' => $displayValue,
                'min_amount' => $this->extractMinPrice($displayValue),
            ];
        }

        $videoLabel = null;

        if ($header['video_index'] !== null) {
            $videoLabel = $this->normalizeInlineText($cells[$header['video_index']] ?? '');
        }

        $title = $this->extractTitle($name);
        $description = $this->extractDescription($name);
        $priceFrom = collect($prices)
            ->sortBy('column_index')
            ->pluck('min_amount')
            ->first(fn ($value) => $value !== null);

        if (blank($title)) {
            return null;
        }

        return [
            'title' => $title,
            'name' => $name,
            'measurement_label' => $header['measure_label'],
            'measurement_value' => blank($measureValue) ? null : $measureValue,
            'description' => $description,
            'has_video' => ! blank($videoLabel),
            'video_label' => blank($videoLabel) ? null : $videoLabel,
            'price_preview' => $prices[0]['display_value'] ?? null,
            'price_from' => $priceFrom !== null ? (float) $priceFrom : null,
            'prices' => $prices,
        ];
    }

    private function getOrCreateSection(Category $rootCategory, string $sectionName, int $sortOrder): Category
    {
        $cacheKey = $rootCategory->id.'::'.mb_strtolower($sectionName);

        if (isset($this->sectionCache[$cacheKey])) {
            return $this->sectionCache[$cacheKey];
        }

        $section = Category::query()->create([
            'parent_id' => $rootCategory->id,
            'name' => $sectionName,
            'slug' => $this->makeUniqueSlug($rootCategory->name.' '.$sectionName, $this->usedCategorySlugs),
            'description' => "Подраздел листа {$rootCategory->name}.",
            'source_sheet' => $rootCategory->source_sheet,
            'sort_order' => $sortOrder,
            'accent_color' => $rootCategory->accent_color,
        ]);

        return $this->sectionCache[$cacheKey] = $section;
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = implode('', array_map(
                fn ($item): string => $item instanceof TextRun ? $item->text : (string) $item,
                $value,
            ));
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof DateInterval) {
            return $value->format('%H:%I:%S');
        }

        $text = str_replace("\r", '', (string) $value);
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function normalizeInlineText(string $value): string
    {
        $value = str_replace("\n", ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeMultilineText(string $value): string
    {
        return collect(preg_split("/\n+/u", $value) ?: [])
            ->map(fn (string $line): string => $this->normalizeInlineText($line))
            ->filter()
            ->implode("\n");
    }

    private function normalizePriceDisplay(string $value): string
    {
        $cleaned = str_replace(['#REF!', '#VALUE!'], '', $value);

        return $this->normalizeMultilineText($cleaned);
    }

    private function extractTitle(string $name): string
    {
        $lines = collect(preg_split("/\n+/u", $name) ?: [])
            ->map(fn (string $line): string => $this->normalizeInlineText($line))
            ->filter()
            ->values();

        $title = $lines->first() ?? $name;

        if (mb_strlen($title) <= 180) {
            return $title;
        }

        return Str::limit($title, 177, '...');
    }

    private function extractDescription(string $name): ?string
    {
        $lines = collect(preg_split("/\n+/u", $name) ?: [])
            ->map(fn (string $line): string => $this->normalizeInlineText($line))
            ->filter()
            ->values();

        if ($lines->count() <= 1) {
            return null;
        }

        return $lines->slice(1)->implode("\n");
    }

    private function extractMinPrice(string $displayValue): ?float
    {
        preg_match_all('/\d[\d\s]*[.,]?\d*/u', $displayValue, $matches);

        $prices = collect($matches[0] ?? [])
            ->map(function (string $candidate): ?float {
                $normalized = str_replace(' ', '', $candidate);
                $normalized = str_replace(',', '.', $normalized);

                if (! is_numeric($normalized)) {
                    return null;
                }

                return round((float) $normalized, 2);
            })
            ->filter(fn (?float $value): bool => $value !== null && $value > 0)
            ->values();

        return $prices->isEmpty() ? null : $prices->min();
    }

    /**
     * @param  array<string, bool>  $usedSlugs
     */
    private function makeUniqueSlug(string $source, array &$usedSlugs): string
    {
        $base = Str::slug($source, '-', 'ru');
        $base = $base !== '' ? $base : 'catalog-item';
        $slug = $base;
        $counter = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        $usedSlugs[$slug] = true;

        return $slug;
    }
}
