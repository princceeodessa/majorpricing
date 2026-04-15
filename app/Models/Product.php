<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'name',
        'slug',
        'one_c_id',
        'one_c_code',
        'vendor_code',
        'brand_name',
        'color_name',
        'measurement_label',
        'measurement_value',
        'stock_quantity',
        'minimum_sale_quantity',
        'units_in_package',
        'description',
        'source_sheet',
        'source_row',
        'has_video',
        'video_label',
        'image_path',
        'price_preview',
        'price_from',
        'sort_order',
    ];

    protected $casts = [
        'has_video' => 'boolean',
        'price_from' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'minimum_sale_quantity' => 'decimal:3',
        'units_in_package' => 'decimal:3',
        'source_row' => 'integer',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class)->orderBy('column_index');
    }

    public function productImages(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function coverImagePath(): ?string
    {
        if ($this->relationLoaded('productImages')) {
            $cover = $this->productImages->firstWhere('is_cover', true)
                ?? $this->productImages->first();

            if ($cover?->path) {
                return $cover->path;
            }
        } else {
            $cover = $this->productImages()->first();

            if ($cover?->path) {
                return $cover->path;
            }
        }

        return filled($this->image_path) ? $this->image_path : null;
    }

    /**
     * @return array<int, string>
     */
    public function galleryImagePaths(): array
    {
        $paths = $this->relationLoaded('productImages')
            ? $this->productImages->pluck('path')->all()
            : $this->productImages()->pluck('path')->all();

        if (filled($this->image_path)) {
            array_unshift($paths, $this->image_path);
        }

        return collect($paths)
            ->filter(fn ($path): bool => filled($path))
            ->unique()
            ->values()
            ->all();
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function favoriteItems(): HasMany
    {
        return $this->hasMany(FavoriteItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $needle = '%'.preg_replace('/\s+/u', '%', trim($term)).'%';

        return $query->where(function (Builder $builder) use ($needle): void {
            $builder
                ->where('title', 'like', $needle)
                ->orWhere('description', 'like', $needle)
                ->orWhere('vendor_code', 'like', $needle)
                ->orWhere('one_c_code', 'like', $needle)
                ->orWhere('brand_name', 'like', $needle)
                ->orWhere('color_name', 'like', $needle);
        });
    }

    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        $query
            ->whereNotNull($query->qualifyColumn('title'))
            ->where($query->qualifyColumn('title'), '<>', '');

        $hiddenCategoryIds = Category::hiddenFromCatalogIds();

        if ($hiddenCategoryIds === []) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($hiddenCategoryIds, $query): void {
            $builder
                ->whereNull($query->qualifyColumn('category_id'))
                ->orWhereNotIn($query->qualifyColumn('category_id'), $hiddenCategoryIds);
        });
    }

    public function scopeCatalogPriorityOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                "CASE
                    WHEN COALESCE(stock_quantity, 0) > 0
                        AND COALESCE(NULLIF(image_path, ''), '') <> '' THEN 0
                    WHEN COALESCE(stock_quantity, 0) > 0 THEN 1
                    ELSE 2
                END",
            )
            ->orderBy('title')
            ->orderBy('id');
    }

    public function isVisibleInCatalog(): bool
    {
        if (blank($this->title)) {
            return false;
        }

        return $this->category_id === null
            || ! in_array((int) $this->category_id, Category::hiddenFromCatalogIds(), true);
    }

    public function priceForProfile(): ?ProductPrice
    {
        return $this->publicPrice();
    }

    public function publicPrice(): ?ProductPrice
    {
        $prices = $this->relationLoaded('prices')
            ? $this->prices
            : $this->prices()->orderBy('column_index')->get();

        $prices = $prices->sortBy('column_index')->values();

        $primaryPrice = $prices->first(fn (ProductPrice $price): bool => $this->isPrimaryPublicPriceLabel($price->label));

        if ($primaryPrice) {
            return $primaryPrice;
        }

        $fallbackPublicPrice = $prices->first(
            fn (ProductPrice $price): bool => $this->normalizedPriceLabel($price->label) === $this->normalizedPriceLabel(self::primaryPublicPriceLabel())
        );

        if ($fallbackPublicPrice) {
            return $fallbackPublicPrice;
        }

        $compareOnlyPrice = $prices->first(fn (ProductPrice $price): bool => $this->isComparePublicPriceLabel($price->label));

        if ($compareOnlyPrice) {
            return $compareOnlyPrice;
        }

        return $prices->first();
    }

    public function comparePrice(): ?ProductPrice
    {
        $publicPrice = $this->publicPrice();

        if (! $publicPrice || ! $this->isPrimaryPublicPriceLabel($publicPrice->label)) {
            return null;
        }

        $prices = $this->relationLoaded('prices')
            ? $this->prices
            : $this->prices()->orderBy('column_index')->get();

        $comparePrice = $prices->first(fn (ProductPrice $price): bool => $this->isComparePublicPriceLabel($price->label));

        if (! $comparePrice) {
            $comparePrice = $prices->first(
                fn (ProductPrice $price): bool => $this->normalizedPriceLabel($price->label) === $this->normalizedPriceLabel(self::comparePublicPriceLabel())
            );
        }

        if (! $comparePrice || $comparePrice->min_amount === null || $publicPrice->min_amount === null) {
            return null;
        }

        return (float) $comparePrice->min_amount > (float) $publicPrice->min_amount
            ? $comparePrice
            : null;
    }

    public function priceForPaymentMethod(?string $paymentMethod): ?ProductPrice
    {
        if ($paymentMethod === 'cash') {
            return $this->publicPrice();
        }

        return $this->comparePrice() ?? $this->publicPrice();
    }

    public function publicTitle(): string
    {
        $title = trim((string) $this->title);

        return $title !== '' ? $title : 'Товар';
    }

    public function fullTitle(): string
    {
        return trim((string) ($this->name ?: $this->title ?: ''));
    }

    public function publicUnitLabel(): ?string
    {
        return self::canonicalUnitLabel($this->measurement_label)
            ?? self::canonicalUnitLabel($this->measurement_value);
    }

    public static function canonicalUnitLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $probe = mb_strtolower($normalized);

        if (preg_match('/^(шт\.?|штука|штук|ед\.?|единица|pc\.?|pcs?\.?|pce\.?|piece)$/u', $probe) === 1) {
            return 'шт';
        }

        if (preg_match('/^(м\.п\.?|п\/м|пог\.?\s*м(етр)?|погон(ный)?\s*метр(а|ов)?)$/u', $probe) === 1) {
            return 'м.п.';
        }

        if (preg_match('/^(м\.?|метр(а|ов)?|meter|m)$/u', $probe) === 1) {
            return 'м';
        }

        if (preg_match('/^(м2|м²|кв\.?\s*м|кв\.?\s*метр(а|ов)?|квадратн(ый|ого)?\s*метр(а|ов)?)$/u', $probe) === 1) {
            return 'м²';
        }

        if (preg_match('/^(м3|м³|куб\.?\s*м|куб\.?\s*метр(а|ов)?|кубическ(ий|ого)?\s*метр(а|ов)?)$/u', $probe) === 1) {
            return 'м³';
        }

        if (preg_match('/^(компл\.?|комплект)$/u', $probe) === 1) {
            return 'компл';
        }

        if (preg_match('/^(уп\.?|упак\.?|упаковка)$/u', $probe) === 1) {
            return 'упак';
        }

        if (preg_match('/^(пара|пар)$/u', $probe) === 1) {
            return 'пара';
        }

        if (preg_match('/^(л\.?|литр(а|ов)?)$/u', $probe) === 1) {
            return 'л';
        }

        if (preg_match('/^(кг\.?|килограмм(а|ов)?)$/u', $probe) === 1) {
            return 'кг';
        }

        $wordCount = preg_match_all('/\p{L}+/u', $probe);

        if (
            mb_strlen($normalized) > 10
            || preg_match('/\d/u', $normalized) === 1
            || $wordCount > 2
        ) {
            return null;
        }

        return $normalized;
    }

    private static function normalizeUnitForDisplay(?string $value): ?string
    {
        $canonical = self::canonicalUnitLabel($value);

        if ($canonical !== null) {
            $value = $canonical;
        }

        if (! filled($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $probe = mb_strtolower($normalized);
        $compact = preg_replace('/[\s\.\,\-–—_]+/u', '', $probe) ?? $probe;

        if (
            preg_match('/(^|[^\p{L}])(шт|штук|штука|ед|единиц|pc|pcs?|pce|piece)($|[^\p{L}])/u', $probe) === 1
            || str_contains($compact, 'шт')
        ) {
            return 'шт';
        }

        if (
            str_contains($compact, 'погм')
            || str_contains($compact, 'погон')
            || str_contains($compact, 'мп')
            || str_contains($compact, 'пм')
        ) {
            return 'м.п.';
        }

        if (
            str_contains($probe, 'м²')
            || str_contains($compact, 'м2')
            || str_contains($compact, 'квм')
            || str_contains($compact, 'квадрат')
        ) {
            return 'м²';
        }

        if (
            str_contains($probe, 'м³')
            || str_contains($compact, 'м3')
            || str_contains($compact, 'кубм')
            || str_contains($compact, 'кубическ')
        ) {
            return 'м³';
        }

        if (
            preg_match('/м\s*\/\s*уп/u', $probe) === 1
            || str_contains($compact, 'муп')
            || preg_match('/(^|[^\p{L}])(м|метр|метров|meter|m)($|[^\p{L}])/u', $probe) === 1
        ) {
            return 'м';
        }

        if (str_contains($compact, 'упак') || $compact === 'уп' || $compact === 'уп.') {
            return 'упак';
        }

        if (str_contains($compact, 'комплект') || str_contains($compact, 'компл')) {
            return 'компл';
        }

        if (str_contains($compact, 'пара') || $compact === 'пар') {
            return 'пара';
        }

        if (preg_match('/(^|[^\p{L}])(кг|килограмм)($|[^\p{L}])/u', $probe) === 1) {
            return 'кг';
        }

        if (preg_match('/(^|[^\p{L}])(л|литр|литра|литров)($|[^\p{L}])/u', $probe) === 1) {
            return 'л';
        }

        if (mb_strlen($normalized) <= 8 && preg_match('/^[\p{L}\/\.]+$/u', $normalized) === 1) {
            return $normalized;
        }

        return null;
    }

    private static function extractUnitCandidateFromText(?string $text): ?string
    {
        if (! filled($text)) {
            return null;
        }

        $probe = mb_strtolower((string) $text);

        if (preg_match_all('/\(([^)]{1,24})\)/u', $probe, $matches) === 1) {
            foreach ($matches[1] as $fragment) {
                if (! is_string($fragment)) {
                    continue;
                }

                if (preg_match('/\b(м\.п\.|м²|м³|м2|м3|м|шт|упак|кг|л)\b/u', $fragment, $unitMatch) === 1) {
                    return $unitMatch[1];
                }

                if (preg_match('/\d+[.,]?\d*\s*(м|шт|кг|л)\b/u', $fragment, $valueMatch) === 1) {
                    return $valueMatch[1];
                }
            }
        }

        if (preg_match('/\b(м\.п\.|м²|м³|м2|м3|шт|упак|кг|л)\b/u', $probe, $unitMatch) === 1) {
            return $unitMatch[1];
        }

        if (preg_match('/\d+[.,]?\d*\s*(м|шт|кг|л)\b/u', $probe, $valueMatch) === 1) {
            return $valueMatch[1];
        }

        if (preg_match('/м\s*\/\s*уп/u', $probe) === 1) {
            return 'м';
        }

        return null;
    }

    public function minimumSaleQuantityNumber(): ?float
    {
        return $this->normalizePositiveDecimalValue($this->minimum_sale_quantity);
    }

    public function unitsInPackageNumber(): ?float
    {
        return $this->normalizePositiveDecimalValue($this->units_in_package);
    }

    public function formattedMinimumSaleQuantity(): ?string
    {
        return $this->formattedDecimalQuantity($this->minimumSaleQuantityNumber());
    }

    public function formattedUnitsInPackage(): ?string
    {
        return $this->formattedDecimalQuantity($this->unitsInPackageNumber());
    }

    public function minimumSaleQuantitySummary(): ?string
    {
        $quantity = $this->formattedMinimumSaleQuantity();

        if ($quantity === null) {
            return null;
        }

        $unitLabel = $this->publicUnitLabel();

        return $unitLabel
            ? trim($quantity.' '.mb_strtolower($unitLabel))
            : $quantity;
    }

    public function unitsInPackageSummary(): ?string
    {
        $quantity = $this->formattedUnitsInPackage();

        if ($quantity === null) {
            return null;
        }

        $unitLabel = $this->publicUnitLabel();

        return $unitLabel
            ? trim($quantity.' '.mb_strtolower($unitLabel))
            : $quantity;
    }

    public function cartQuantityMinimum(): int
    {
        $minimum = $this->effectiveSaleStepNumber();

        if ($minimum === null) {
            return 1;
        }

        return max(1, min(999, (int) ceil($minimum)));
    }

    public function cartQuantityStep(): int
    {
        $step = $this->effectiveSaleStepNumber();

        if ($step === null) {
            return 1;
        }

        return max(1, min(999, (int) ceil($step)));
    }

    public function cartQuantityMax(): int
    {
        $minimum = $this->cartQuantityMinimum();
        $step = $this->cartQuantityStep();
        $max = 999;

        if ($minimum > $max) {
            return $max;
        }

        $steps = (int) floor(($max - $minimum) / $step);

        return $minimum + ($steps * $step);
    }

    public function normalizeCartQuantity(int|float|string|null $value): int
    {
        $minimum = $this->cartQuantityMinimum();
        $step = $this->cartQuantityStep();
        $maximum = $this->cartQuantityMax();

        $candidate = is_numeric($value) ? (int) floor((float) $value) : $minimum;
        $candidate = max($minimum, min($candidate, $maximum));

        $stepsFromMinimum = (int) ceil(($candidate - $minimum) / $step);
        $normalized = $minimum + max(0, $stepsFromMinimum) * $step;

        if ($normalized > $maximum) {
            return $maximum;
        }

        return max($minimum, $normalized);
    }

    public function isCartQuantityAligned(int|float|string|null $value): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        return $this->normalizeCartQuantity($value) === (int) floor((float) $value);
    }

    public function hasStockQuantity(): bool
    {
        return $this->stock_quantity !== null;
    }

    public function stockQuantityNumber(): ?float
    {
        return $this->stock_quantity !== null
            ? (float) $this->stock_quantity
            : null;
    }

    public function formattedStockQuantity(): ?string
    {
        $quantity = $this->stockQuantityNumber();

        if ($quantity === null) {
            return null;
        }

        $formatted = number_format($quantity, 3, ',', ' ');
        $formatted = preg_replace('/0+$/', '', $formatted) ?? $formatted;
        $formatted = preg_replace('/,$/', '', $formatted) ?? $formatted;

        return $formatted === '' ? '0' : $formatted;
    }

    public function stockSummary(): ?string
    {
        $quantity = $this->formattedStockQuantity();

        if ($quantity === null) {
            return null;
        }

        $unitLabel = $this->publicUnitLabel();

        return $unitLabel
            ? trim($quantity.' '.mb_strtolower($unitLabel))
            : $quantity;
    }

    public function isInStock(): bool
    {
        $quantity = $this->stockQuantityNumber();

        return $quantity !== null && $quantity > 0;
    }

    public function availabilityLabel(): string
    {
        return $this->isInStock()
            ? 'В наличии'
            : 'Обратитесь к менеджеру для уточнения наличия';
    }

    public function availabilityTone(): string
    {
        return $this->isInStock() ? 'in-stock' : 'attention';
    }

    /**
     * @return array<int, string>
     */
    public static function publicPricePriority(): array
    {
        return [
            self::primaryPublicPriceLabel(),
            self::comparePublicPriceLabel(),
        ];
    }

    public static function primaryPublicPriceLabel(): string
    {
        return json_decode('"\u041e\u043f\u0442\u043e\u0432\u0430\u044f"', true);
    }

    public static function comparePublicPriceLabel(): string
    {
        return json_decode('"\u041e\u043f\u0442\u043e\u0432\u0430\u044f \u0411\u0415\u0417\u041d\u0410\u041b"', true);
    }

    private function normalizedPriceLabel(?string $label): string
    {
        $normalized = mb_strtolower(trim((string) $label));

        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function compactPriceLabel(?string $label): string
    {
        $normalized = $this->normalizedPriceLabel($label);

        if ($normalized === '') {
            return '';
        }

        return preg_replace('/[\s\-_()]+/u', '', $normalized) ?? $normalized;
    }

    private function isPrimaryPublicPriceLabel(?string $label): bool
    {
        $normalized = $this->normalizedPriceLabel($label);

        if ($normalized === '') {
            return false;
        }

        if ($normalized === $this->normalizedPriceLabel(self::primaryPublicPriceLabel())) {
            return true;
        }

        return str_contains($normalized, self::publicPriceMarker())
            && ! str_contains($this->compactPriceLabel($normalized), self::comparePriceMarker());
    }

    private function isComparePublicPriceLabel(?string $label): bool
    {
        $normalized = $this->normalizedPriceLabel($label);

        if ($normalized === '') {
            return false;
        }

        if ($normalized === $this->normalizedPriceLabel(self::comparePublicPriceLabel())) {
            return true;
        }

        return str_contains($normalized, self::publicPriceMarker())
            && str_contains($this->compactPriceLabel($normalized), self::comparePriceMarker());
    }

    private static function publicPriceMarker(): string
    {
        return json_decode('"\u043e\u043f\u0442\u043e\u0432"', true);
    }

    private static function comparePriceMarker(): string
    {
        return json_decode('"\u0431\u0435\u0437\u043d\u0430\u043b"', true);
    }

    private function formattedDecimalQuantity(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $formatted = number_format($value, 3, ',', ' ');
        $formatted = preg_replace('/0+$/', '', $formatted) ?? $formatted;
        $formatted = preg_replace('/,$/', '', $formatted) ?? $formatted;

        return $formatted === '' ? '0' : $formatted;
    }

    private function normalizePositiveDecimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = is_numeric($value) ? (float) $value : null;

        if ($numeric === null || $numeric <= 0) {
            return null;
        }

        return $numeric;
    }

    private function effectiveSaleStepNumber(): ?float
    {
        $minimumQuantity = $this->minimumSaleQuantityNumber();

        if ($minimumQuantity !== null) {
            return $minimumQuantity;
        }

        return $this->unitsInPackageNumber();
    }
}
