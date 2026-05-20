<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFiltersAndRecommendationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_filters_use_profile_price_and_series(): void
    {
        $partnerProfile = PriceProfile::query()->create([
            'name' => 'Партнерский прайс',
            'slug' => 'partner-price',
            'column_index' => 2,
            'price_label' => 'Цена 2',
            'is_default' => false,
        ]);

        $user = User::factory()->create([
            'login' => 'partner-filter',
            'email' => 'partner-filter@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $partnerProfile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Шпатели',
            'slug' => 'shpateli',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $matchingProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Hanov M',
            'name' => 'Шпатель Hanov M',
            'slug' => 'shpatel-hanov-m',
            'source_sheet' => 'Hanov',
            'price_from' => 2000,
            'sort_order' => 0,
        ]);

        $wrongPriceProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Hanov S',
            'name' => 'Шпатель Hanov S',
            'slug' => 'shpatel-hanov-s',
            'source_sheet' => 'Hanov',
            'price_from' => 700,
            'sort_order' => 1,
        ]);

        $wrongSheetProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Lumfer',
            'name' => 'Шпатель Lumfer',
            'slug' => 'shpatel-lumfer',
            'source_sheet' => 'Lumfer',
            'price_from' => 1900,
            'sort_order' => 2,
        ]);

        ProductPrice::query()->insert([
            [
                'product_id' => $matchingProduct->id,
                'column_index' => 2,
                'label' => 'Цена 2',
                'display_value' => '2000,00',
                'min_amount' => 2000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $wrongPriceProduct->id,
                'column_index' => 2,
                'label' => 'Цена 2',
                'display_value' => '700,00',
                'min_amount' => 700,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $wrongSheetProduct->id,
                'column_index' => 2,
                'label' => 'Цена 2',
                'display_value' => '1900,00',
                'min_amount' => 1900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('categories.show', [
            'category' => $category,
            'sheet' => 'Hanov',
            'price_min' => 1500,
            'price_max' => 2500,
        ]));

        $response->assertOk();
        $response->assertSeeText('Шпатель Hanov M');
        $response->assertDontSeeText('Шпатель Hanov S');
        $response->assertDontSeeText('Шпатель Lumfer');
    }

    public function test_product_page_hides_recommendations_block(): void
    {
        $baseProfile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'catalog-viewer',
            'email' => 'catalog-viewer@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $baseProfile->id,
        ]);

        $root = Category::query()->create([
            'name' => 'Инструмент',
            'slug' => 'instrument',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        $spatulas = Category::query()->create([
            'parent_id' => $root->id,
            'name' => 'Шпатели',
            'slug' => 'shpateli',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        $knives = Category::query()->create([
            'parent_id' => $root->id,
            'name' => 'Ножи',
            'slug' => 'nozhi',
            'sort_order' => 1,
            'accent_color' => '#2563eb',
        ]);

        $otherRoot = Category::query()->create([
            'name' => 'Свет',
            'slug' => 'svet',
            'sort_order' => 2,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $spatulas->id,
            'title' => 'Шпатель Hanov M мастерок',
            'name' => 'Шпатель Hanov M мастерок',
            'slug' => 'shpatel-hanov-m-masterok',
            'source_sheet' => 'Hanov',
            'measurement_label' => 'Ширина',
            'measurement_value' => '80 мм',
            'price_from' => 2000,
            'sort_order' => 0,
        ]);

        $sameCategory = Product::query()->create([
            'category_id' => $spatulas->id,
            'title' => 'Шпатель Hanov L',
            'name' => 'Шпатель Hanov L',
            'slug' => 'shpatel-hanov-l',
            'source_sheet' => 'Hanov',
            'measurement_label' => 'Ширина',
            'measurement_value' => '100 мм',
            'price_from' => 2400,
            'sort_order' => 1,
        ]);

        $sameRootAndSheet = Product::query()->create([
            'category_id' => $knives->id,
            'title' => 'Нож Hanov монтажный',
            'name' => 'Нож Hanov монтажный',
            'slug' => 'nozh-hanov-montazhnyi',
            'source_sheet' => 'Hanov',
            'price_from' => 1100,
            'sort_order' => 2,
        ]);

        $unrelated = Product::query()->create([
            'category_id' => $otherRoot->id,
            'title' => 'Светильник Linea',
            'name' => 'Светильник Linea',
            'slug' => 'svetilnik-linea',
            'source_sheet' => 'Linea',
            'price_from' => 3500,
            'sort_order' => 3,
        ]);

        ProductPrice::query()->insert([
            [
                'product_id' => $product->id,
                'column_index' => 1,
                'label' => 'Цена 1',
                'display_value' => '2000,00',
                'min_amount' => 2000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $sameCategory->id,
                'column_index' => 1,
                'label' => 'Цена 1',
                'display_value' => '2400,00',
                'min_amount' => 2400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $sameRootAndSheet->id,
                'column_index' => 1,
                'label' => 'Цена 1',
                'display_value' => '1100,00',
                'min_amount' => 1100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $unrelated->id,
                'column_index' => 1,
                'label' => 'Цена 1',
                'display_value' => '3500,00',
                'min_amount' => 3500,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertDontSeeText('Похожие и нужные рядом позиции');
        $response->assertDontSeeText('Шпатель Hanov L');
        $response->assertDontSeeText('Нож Hanov монтажный');
        $response->assertDontSeeText('Светильник Linea');
    }
}
