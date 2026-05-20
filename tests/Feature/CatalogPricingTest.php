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

    public function test_guest_catalog_hides_prices_and_links_to_partner_request(): void
    {
        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profilya',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль открытого каталога',
            'name' => 'Профиль открытого каталога',
            'slug' => 'profil-public-catalog',
            'price_from' => 530,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => Product::primaryPublicPriceLabel(),
            'display_value' => '530,00',
            'min_amount' => 530,
        ]);

        $partnerRequestUrl = route('registration-requests.create');

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSeeText('Профиль открытого каталога')
            ->assertSeeText('Стать партнером')
            ->assertSee($partnerRequestUrl, false)
            ->assertSeeText('Цены по запросу')
            ->assertDontSeeText('Партнерский доступ')
            ->assertDontSeeText('Цены доступны партнерам')
            ->assertDontSeeText('530,00')
            ->assertDontSeeText('Со скидкой')
            ->assertDontSeeText('В корзину');

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Профиль открытого каталога')
            ->assertSeeText('Стать партнером')
            ->assertSee($partnerRequestUrl, false)
            ->assertSeeText('Цены по запросу')
            ->assertDontSeeText('Цены доступны партнерам')
            ->assertDontSeeText('Оставьте заявку, чтобы получить партнерский доступ.')
            ->assertDontSeeText('530,00')
            ->assertDontSeeText('Со скидкой')
            ->assertDontSeeText('В корзину');
    }

    public function test_guest_cannot_see_aluminum_profiles_catalog_section(): void
    {
        $category = Category::query()->create([
            'name' => 'Профиля алюм.',
            'slug' => 'profilya-alyum',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Скрытый алюминиевый профиль',
            'name' => 'Скрытый алюминиевый профиль',
            'slug' => 'skrytyy-alyuminievyy-profil',
            'price_from' => 610,
            'sort_order' => 0,
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertDontSeeText('Профиля алюм.')
            ->assertDontSeeText('Скрытый алюминиевый профиль');

        $this->get(route('catalog.index', ['q' => 'алюминиевый']))
            ->assertOk()
            ->assertDontSeeText('Скрытый алюминиевый профиль');

        $this->get(route('categories.show', $category))
            ->assertNotFound();

        $this->get(route('products.show', $product))
            ->assertNotFound();

        $user = User::factory()->create([
            'login' => 'aluminum-viewer',
            'email' => 'aluminum-viewer@example.com',
        ]);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSeeText('Профиля алюм.')
            ->assertSeeText('Скрытый алюминиевый профиль');

        $this->actingAs($user)
            ->get(route('categories.show', $category))
            ->assertOk()
            ->assertSeeText('Скрытый алюминиевый профиль');

        $this->actingAs($user)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSeeText('Скрытый алюминиевый профиль');
    }

    public function test_guest_category_does_not_show_or_apply_price_filter(): void
    {
        $category = Category::query()->create([
            'name' => 'Светотехника',
            'slug' => 'svetotekhnika',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $cheapProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Бюджетный светильник',
            'name' => 'Бюджетный светильник',
            'slug' => 'byudzhetnyy-svetilnik',
            'price_from' => 100,
            'sort_order' => 0,
        ]);

        $expensiveProduct = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Премиальный светильник',
            'name' => 'Премиальный светильник',
            'slug' => 'premialnyy-svetilnik',
            'price_from' => 900,
            'sort_order' => 1,
        ]);

        ProductPrice::query()->insert([
            [
                'product_id' => $cheapProduct->id,
                'column_index' => 1,
                'label' => Product::primaryPublicPriceLabel(),
                'display_value' => '100,00',
                'min_amount' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_id' => $expensiveProduct->id,
                'column_index' => 1,
                'label' => Product::primaryPublicPriceLabel(),
                'display_value' => '900,00',
                'min_amount' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('categories.show', [
            'category' => $category,
            'price_min' => 800,
            'price_max' => 1000,
        ]))
            ->assertOk()
            ->assertSeeText('Бюджетный светильник')
            ->assertSeeText('Премиальный светильник')
            ->assertSeeText('Цены по запросу')
            ->assertDontSeeText('Цены доступны партнерам')
            ->assertDontSee('name="price_min"', false)
            ->assertDontSee('name="price_max"', false)
            ->assertDontSeeText('100,00')
            ->assertDontSeeText('900,00');
    }

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
            'description' => 'Актуальное описание профиля из 1С',
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
        $response->assertSeeText('Актуальное описание профиля из 1С');
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
            'description' => 'Описание для карточки товара',
            'price_from' => 508,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertSeeText('Карниз накладной ПВХ 2-х рядный с поворотом Белый 160');
        $response->assertSeeText('Описание для карточки товара');
        $response->assertDontSeeText('160 карниз НК КЛАССИК 2 БЕЛЫЙ в инд. уп. Ультракомплект');
    }
}
