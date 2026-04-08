<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_shows_only_product_results_without_home_sections(): void
    {
        $user = User::factory()->create([
            'login' => 'search-viewer',
            'email' => 'search-viewer@example.com',
        ]);

        $category = Category::query()->create([
            'name' => 'Шпатели',
            'slug' => 'shpateli',
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $matchedProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Hanov M',
            'name' => 'Шпатель Hanov M',
            'slug' => 'shpatel-hanov-m',
            'price_from' => 2000,
            'sort_order' => 0,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Светильник Linea',
            'name' => 'Светильник Linea',
            'slug' => 'svetilnik-linea',
            'price_from' => 3000,
            'sort_order' => 1,
        ]);

        ProductPrice::query()->create([
            'product_id' => $matchedProduct->id,
            'column_index' => 1,
            'label' => 'Цена',
            'display_value' => '2000,00',
            'min_amount' => 2000,
        ]);

        $response = $this->actingAs($user)->get(route('catalog.index', ['q' => 'Hanov']));

        $response->assertOk();
        $response->assertSeeText('Результаты поиска');
        $response->assertSeeText('Шпатель Hanov M');
        $response->assertDontSeeText('Основные категории');
        $response->assertDontSeeText('Новинки');
        $response->assertDontSeeText('Светильник Linea');
    }
}
