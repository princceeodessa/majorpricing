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
        'measurement_label',
        'measurement_value',
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
                ->orWhere('name', 'like', $needle)
                ->orWhere('description', 'like', $needle)
                ->orWhere('vendor_code', 'like', $needle)
                ->orWhere('one_c_code', 'like', $needle)
                ->orWhere('brand_name', 'like', $needle);
        });
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

    public function publicTitle(): string
    {
        return trim((string) ($this->title ?: $this->name ?: ''));
    }

    public function fullTitle(): string
    {
        return trim((string) ($this->name ?: $this->title ?: ''));
    }

    public function publicUnitLabel(): ?string
    {
        return filled($this->measurement_label)
            ? trim((string) $this->measurement_label)
            : (filled($this->measurement_value) ? trim((string) $this->measurement_value) : null);
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
}
