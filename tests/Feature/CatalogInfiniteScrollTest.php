<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogInfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_index_returns_html_chunks_for_infinite_scroll(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'infinite-index',
            'email' => 'infinite-index@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profilya',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        foreach (range(1, 19) as $number) {
            Product::query()->create([
                'category_id' => $category->id,
                'title' => sprintf('Товар %02d', $number),
                'name' => sprintf('Товар %02d', $number),
                'slug' => sprintf('tovar-%02d', $number),
                'sort_order' => $number,
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->getJson(route('catalog.index', ['page' => 2]));

        $response->assertOk();
        $response->assertJson([
            'hasMorePages' => false,
            'nextPageUrl' => null,
        ]);
        $this->assertStringContainsString('Товар 19', $response->json('html'));
        $this->assertStringNotContainsString('Товар 01', $response->json('html'));
    }

    public function test_category_returns_filtered_html_chunks_for_infinite_scroll(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'infinite-category',
            'email' => 'infinite-category@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Инструмент',
            'slug' => 'instrument',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        foreach (range(1, 25) as $number) {
            Product::query()->create([
                'category_id' => $category->id,
                'title' => sprintf('Hanov %02d', $number),
                'name' => sprintf('Hanov %02d', $number),
                'slug' => sprintf('hanov-%02d', $number),
                'source_sheet' => 'Hanov',
                'sort_order' => $number,
            ]);
        }

        Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Lumfer test',
            'name' => 'Lumfer test',
            'slug' => 'lumfer-test',
            'source_sheet' => 'Lumfer',
            'sort_order' => 50,
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('categories.show', [
                'category' => $category,
                'sheet' => 'Hanov',
                'page' => 2,
            ]));

        $response->assertOk();
        $response->assertJson([
            'hasMorePages' => false,
            'nextPageUrl' => null,
        ]);
        $this->assertStringContainsString('Hanov 25', $response->json('html'));
        $this->assertStringNotContainsString('Lumfer test', $response->json('html'));
    }
}
