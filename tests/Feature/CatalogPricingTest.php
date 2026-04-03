<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_uses_price_for_authenticated_user_profile(): void
    {
        $baseProfile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $partnerProfile = PriceProfile::query()->create([
            'name' => 'Партнерский прайс',
            'slug' => 'partner-price',
            'column_index' => 2,
            'price_label' => 'Цена 2',
            'is_default' => false,
        ]);

        $user = User::factory()->create([
            'login' => 'partner-demo',
            'email' => 'partner-demo@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $partnerProfile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profilya',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль тестовый',
            'name' => 'Профиль тестовый',
            'slug' => 'profil-test',
            'measurement_label' => 'Длина м.',
            'measurement_value' => '2,0',
            'price_from' => 530,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => $baseProfile->column_index,
            'label' => 'Цена 1',
            'display_value' => '530,00',
            'min_amount' => 530,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => $partnerProfile->column_index,
            'label' => 'Цена 2',
            'display_value' => '624,75',
            'min_amount' => 624.75,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSeeText('Партнерский прайс');
        $response->assertSeeText('Профиль тестовый');
        $response->assertSeeText('624,75');
        $response->assertDontSeeText('530,00');
    }
}
