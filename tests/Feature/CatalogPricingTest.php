<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_uses_single_catalog_price(): void
    {
        $user = User::factory()->create([
            'login' => 'partner-demo',
            'email' => 'partner-demo@example.com',
            'password' => 'secret12345',
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
            'column_index' => 1,
            'label' => 'Цена 1',
            'display_value' => '530,00',
            'min_amount' => 530,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 2,
            'label' => 'Цена 2',
            'display_value' => '624,75',
            'min_amount' => 624.75,
        ]);

        $response = $this->actingAs($user)->get(route('categories.show', $category));

        $response->assertOk();
        $response->assertSeeText('Профиль тестовый');
        $response->assertSeeText('530,00');
        $response->assertSeeText('Со скидкой');
        $response->assertDontSeeText('Цена 1');
        $response->assertDontSeeText('Цена 2');
        $response->assertDontSeeText('624,75');
    }

    public function test_public_catalog_prefers_optovaya_price_and_shows_compare_beznal_price(): void
    {
        $user = User::factory()->create([
            'login' => 'one-c-price-viewer',
            'email' => 'one-c-price-viewer@example.com',
        ]);

        $category = Category::query()->create([
            'name' => 'Комплектующие',
            'slug' => 'komplektuyushchie',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Заглушка TOREC',
            'name' => 'Заглушка TOREC',
            'slug' => 'zaglushka-torec',
            'price_from' => 38.89,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Дилер Ижевск',
            'display_value' => '44,45',
            'min_amount' => 44.45,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 2,
            'label' => 'Оптовая БЕЗНАЛ (от 50 тыс.)',
            'display_value' => '38,89',
            'min_amount' => 38.89,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 3,
            'label' => 'Оптовая (от 50 тыс.)',
            'display_value' => '35,00',
            'min_amount' => 35.00,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSeeText('35,00');
        $response->assertSeeText('38,89');
        $response->assertSeeText('Со скидкой');
        $response->assertDontSeeText('Оптовая');
        $response->assertDontSeeText('Оптовая БЕЗНАЛ');
        $response->assertDontSeeText('44,45');
    }

    public function test_product_page_uses_print_title_as_public_name(): void
    {
        $user = User::factory()->create([
            'login' => 'print-title-viewer',
            'email' => 'print-title-viewer@example.com',
        ]);

        $category = Category::query()->create([
            'name' => 'Карнизы',
            'slug' => 'karnizy',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Карниз накладной ПВХ 2-х рядный с поворотом Белый 160',
            'name' => '160 карниз НК КЛАССИК 2 БЕЛЫЙ в инд. уп. Ультракомплект',
            'slug' => 'karniz-nakladnoy-belyy-160',
            'price_from' => 508,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSeeText('Карниз накладной ПВХ 2-х рядный с поворотом Белый 160');
        $response->assertDontSeeText('160 карниз НК КЛАССИК 2 БЕЛЫЙ в инд. уп. Ультракомплект');
    }
}
