<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    private const HIDDEN_CATALOG_NAMES = [
        'для продажи',
    ];

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'one_c_id',
        'description',
        'source_sheet',
        'sort_order',
        'accent_color',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('sort_order');
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        $hiddenIds = self::hiddenFromCatalogIds();

        if ($hiddenIds === []) {
            return $query;
        }

        return $query->whereNotIn($query->qualifyColumn('id'), $hiddenIds);
    }

    public function isHiddenFromCatalog(): bool
    {
        return in_array((int) $this->id, self::hiddenFromCatalogIds(), true);
    }

    /**
     * @return array<int, int>
     */
    public static function hiddenFromCatalogIds(): array
    {
        $categories = self::query()->get(['id', 'parent_id', 'name']);
        $hiddenNames = collect(self::HIDDEN_CATALOG_NAMES)
            ->map(fn (string $name): string => self::normalizeCatalogName($name))
            ->filter()
            ->values()
            ->all();

        if ($categories->isEmpty() || $hiddenNames === []) {
            return [];
        }

        $hiddenIds = $categories
            ->filter(fn (Category $category): bool => in_array(self::normalizeCatalogName($category->name), $hiddenNames, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $queue = $hiddenIds;

        while ($queue !== []) {
            $childIds = $categories
                ->filter(fn (Category $category): bool => in_array((int) $category->parent_id, $queue, true))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $newIds = array_values(array_diff($childIds, $hiddenIds));

            if ($newIds === []) {
                break;
            }

            $hiddenIds = array_merge($hiddenIds, $newIds);
            $queue = $newIds;
        }

        return array_values(array_unique($hiddenIds));
    }

    private static function normalizeCatalogName(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
