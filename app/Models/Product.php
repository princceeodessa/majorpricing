<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
                ->orWhere('description', 'like', $needle);
        });
    }

    public function priceForProfile(?PriceProfile $profile): ?ProductPrice
    {
        $prices = $this->relationLoaded('prices')
            ? $this->prices
            : $this->prices()->orderBy('column_index')->get();

        $requestedColumn = max(1, (int) ($profile?->column_index ?? 1));

        return $prices->firstWhere('column_index', $requestedColumn)
            ?? $prices->sortBy('column_index')->first();
    }
}
