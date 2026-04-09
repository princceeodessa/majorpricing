<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\FavoriteItem;
use App\Models\OneCPriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_cleanup_dry_run_only_reports_counts(): void
    {
        [$legacyProduct, $oneCProduct] = $this->seedCatalogCleanupData();

        $this->artisan('catalog:cleanup', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Не-1С товаров')
            ->expectsOutputToContain('Dry-run завершён');

        $this->assertDatabaseHas('products', ['id' => $legacyProduct->id]);
        $this->assertDatabaseHas('products', ['id' => $oneCProduct->id]);
        $this->assertDatabaseHas('categories', ['name' => 'Legacy root']);
    }

    public function test_legacy_cleanup_removes_only_non_one_c_catalog(): void
    {
        [$legacyProduct, $oneCProduct, $legacyLeafCategory] = $this->seedCatalogCleanupData();

        $this->artisan('catalog:cleanup')
            ->assertSuccessful()
            ->expectsOutputToContain('Не-1С товары и пустые legacy-категории удалены.');

        $this->assertDatabaseMissing('products', ['id' => $legacyProduct->id]);
        $this->assertDatabaseHas('products', ['id' => $oneCProduct->id]);
        $this->assertDatabaseMissing('categories', ['id' => $legacyLeafCategory->id]);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('favorite_items', 0);

        $this->assertDatabaseHas('order_items', [
            'product_title' => 'Legacy product',
            'product_id' => null,
        ]);
    }

    public function test_full_cleanup_clears_catalog_and_price_types(): void
    {
        [, $oneCProduct] = $this->seedCatalogCleanupData();

        OneCPriceType::query()->create([
            'one_c_id' => 'price-type-1',
            'name' => 'Оптовая',
            'column_index' => 1,
        ]);

        $this->artisan('catalog:cleanup', ['--mode' => 'all'])
            ->assertSuccessful()
            ->expectsOutputToContain('Каталог полностью очищен. Можно запускать новый полный импорт из 1С.');

        $this->assertDatabaseMissing('products', ['id' => $oneCProduct->id]);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_prices', 0);
        $this->assertDatabaseCount('one_c_price_types', 0);
    }

    /**
     * @return array{0: \App\Models\Product, 1: \App\Models\Product, 2: \App\Models\Category}
     */
    private function seedCatalogCleanupData(): array
    {
        $user = User::factory()->create();

        $legacyRoot = Category::query()->create([
            'name' => 'Legacy root',
            'slug' => 'legacy-root',
        ]);

        $legacyLeaf = Category::query()->create([
            'parent_id' => $legacyRoot->id,
            'name' => 'Legacy leaf',
            'slug' => 'legacy-leaf',
        ]);

        $oneCRoot = Category::query()->create([
            'name' => '1C root',
            'slug' => '1c-root',
            'one_c_id' => 'cat-1',
        ]);

        $legacyProduct = Product::query()->create([
            'category_id' => $legacyLeaf->id,
            'title' => 'Legacy product',
            'name' => 'Legacy product',
            'slug' => 'legacy-product',
            'source_sheet' => 'Excel',
        ]);

        $oneCProduct = Product::query()->create([
            'category_id' => $oneCRoot->id,
            'title' => '1C product',
            'name' => '1C product',
            'slug' => '1c-product',
            'one_c_id' => 'prod-1',
            'one_c_code' => 'CODE-1',
            'source_sheet' => '1C',
        ]);

        ProductPrice::query()->create([
            'product_id' => $legacyProduct->id,
            'column_index' => 1,
            'label' => 'Цена',
            'display_value' => '100',
            'min_amount' => 100,
        ]);

        ProductPrice::query()->create([
            'product_id' => $oneCProduct->id,
            'column_index' => 1,
            'label' => 'Оптовая',
            'display_value' => '200',
            'min_amount' => 200,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $legacyProduct->id,
            'quantity' => 2,
        ]);

        FavoriteItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $legacyProduct->id,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-1',
            'status' => 'new',
            'items_count' => 1,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $legacyProduct->id,
            'product_title' => 'Legacy product',
            'quantity' => 1,
        ]);

        return [$legacyProduct, $oneCProduct, $legacyLeaf];
    }
}
