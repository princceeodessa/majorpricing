<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\OneCPriceType;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('catalog:cleanup {--mode=legacy : Cleanup mode: legacy or all} {--dry-run : Only show what would be removed}')]
#[Description('Removes legacy Excel catalog data or fully resets the catalog before a 1C import')]
class CleanupCatalogCommand extends Command
{
    public function handle(): int
    {
        $mode = mb_strtolower(trim((string) $this->option('mode')));

        if (! in_array($mode, ['legacy', 'all'], true)) {
            $this->components->error('Допустимые режимы: legacy, all.');

            return self::FAILURE;
        }

        $stats = $mode === 'all'
            ? $this->collectFullResetStats()
            : $this->collectLegacyCleanupStats();

        $this->table(
            ['Показатель', 'Значение'],
            collect($stats)
                ->map(fn (int $value, string $label): array => [$label, $value])
                ->values()
                ->all(),
        );

        if ($this->option('dry-run')) {
            $this->components->info(
                $mode === 'all'
                    ? 'Dry-run завершён. Каталог не изменялся.'
                    : 'Dry-run завершён. Не-1С товары и категории не удалялись.'
            );

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($mode): array {
            return $mode === 'all'
                ? $this->performFullReset()
                : $this->performLegacyCleanup();
        });

        $this->newLine();
        $this->table(
            ['Удалено', 'Количество'],
            collect($result)
                ->map(fn (int $value, string $label): array => [$label, $value])
                ->values()
                ->all(),
        );

        $this->components->info(
            $mode === 'all'
                ? 'Каталог полностью очищен. Можно запускать новый полный импорт из 1С.'
                : 'Не-1С товары и пустые legacy-категории удалены.'
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function collectLegacyCleanupStats(): array
    {
        $legacyProductIds = $this->legacyProductIds();

        return [
            'Не-1С товаров' => count($legacyProductIds),
            'Цен у не-1С товаров' => $legacyProductIds === []
                ? 0
                : ProductPrice::query()->whereIn('product_id', $legacyProductIds)->count(),
            'Корзин с не-1С товарами' => $legacyProductIds === []
                ? 0
                : CartItem::query()->whereIn('product_id', $legacyProductIds)->count(),
            'Избранного с не-1С товарами' => $legacyProductIds === []
                ? 0
                : FavoriteItem::query()->whereIn('product_id', $legacyProductIds)->count(),
            'Заказов с привязкой к не-1С товарам' => $legacyProductIds === []
                ? 0
                : OrderItem::query()->whereIn('product_id', $legacyProductIds)->count(),
            'Пустых legacy-категорий к удалению' => $this->countRemovableLegacyCategories($legacyProductIds),
            '1С товаров останется' => Product::query()
                ->whereNotNull('one_c_id')
                ->orWhereNotNull('one_c_code')
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function collectFullResetStats(): array
    {
        return [
            'Всего товаров' => Product::query()->count(),
            'Всего цен' => ProductPrice::query()->count(),
            'Всего категорий' => Category::query()->count(),
            'Типов цен 1С' => OneCPriceType::query()->count(),
            'Корзин будет очищено' => CartItem::query()->count(),
            'Избранное будет очищено' => FavoriteItem::query()->count(),
            'Заказов отвяжется от товаров' => OrderItem::query()->whereNotNull('product_id')->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function performLegacyCleanup(): array
    {
        $legacyProductIds = $this->legacyProductIds();
        $deletedProducts = count($legacyProductIds);

        if ($legacyProductIds !== []) {
            Product::query()->whereKey($legacyProductIds)->delete();
        }

        $deletedCategories = $this->deleteEmptyLegacyCategories();

        return [
            'Товаров' => $deletedProducts,
            'Пустых legacy-категорий' => $deletedCategories,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function performFullReset(): array
    {
        $deletedProducts = Product::query()->count();
        $deletedCategories = Category::query()->count();
        $deletedPriceTypes = OneCPriceType::query()->count();

        Product::query()->delete();
        Category::query()->delete();
        OneCPriceType::query()->delete();

        return [
            'Товаров' => $deletedProducts,
            'Категорий' => $deletedCategories,
            'Типов цен 1С' => $deletedPriceTypes,
        ];
    }

    /**
     * @return array<int, int>
     */
    private function legacyProductIds(): array
    {
        return Product::query()
            ->whereNull('one_c_id')
            ->where(function ($query): void {
                $query->whereNull('one_c_code')->orWhere('one_c_code', '');
            })
            ->pluck('id')
            ->all();
    }

    private function deleteEmptyLegacyCategories(): int
    {
        $deleted = 0;

        do {
            $ids = Category::query()
                ->whereNull('one_c_id')
                ->doesntHave('products')
                ->doesntHave('children')
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted += count($ids);
            Category::query()->whereKey($ids)->delete();
        } while ($ids !== []);

        return $deleted;
    }

    /**
     * @param  array<int, int>  $legacyProductIds
     */
    private function countRemovableLegacyCategories(array $legacyProductIds): int
    {
        $productCounts = Category::query()
            ->withCount([
                'products as cleanup_products_count' => function ($query) use ($legacyProductIds): void {
                    if ($legacyProductIds === []) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereIn('products.id', $legacyProductIds);
                },
            ])
            ->get(['id', 'parent_id', 'one_c_id']);

        $legacyCategories = $productCounts
            ->filter(fn (Category $category): bool => blank($category->one_c_id))
            ->keyBy('id');

        $childrenMap = [];

        foreach ($productCounts as $category) {
            if ($category->parent_id !== null) {
                $childrenMap[$category->parent_id][] = $category->id;
            }
        }

        $removable = [];
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach ($legacyCategories as $categoryId => $category) {
                if (isset($removable[$categoryId])) {
                    continue;
                }

                if ((int) ($category->cleanup_products_count ?? 0) > 0) {
                    continue;
                }

                $childIds = $childrenMap[$categoryId] ?? [];
                $hasBlockedChildren = collect($childIds)->contains(function (int $childId) use ($removable): bool {
                    return ! isset($removable[$childId]);
                });

                if ($hasBlockedChildren) {
                    continue;
                }

                $removable[$categoryId] = true;
                $changed = true;
            }
        }

        return count($removable);
    }
}
