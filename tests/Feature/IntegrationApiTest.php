<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_export_requires_integration_token(): void
    {
        $response = $this->getJson(route('api.integrations.catalog.products'));

        $response->assertUnauthorized();
    }

    public function test_erp_catalog_export_returns_products_with_prices_and_images(): void
    {
        config()->set('integrations.tokens.erp', 'erp-secret');

        $category = Category::query()->create([
            'name' => 'Карнизы',
            'slug' => 'karnizy',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Карниз ПВХ',
            'name' => 'Карниз ПВХ',
            'slug' => 'karniz-pvh',
            'source_sheet' => 'KRAAB',
            'source_row' => 12,
            'image_path' => 'catalog-media/sample-product.png',
            'price_from' => 730,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
            'display_value' => '730,00',
            'min_amount' => 730,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'erp-secret',
        ])->getJson(route('api.integrations.catalog.products'));

        $response->assertOk();
        $response->assertJsonPath('data.0.slug', 'karniz-pvh');
        $response->assertJsonPath('data.0.prices.0.min_amount', 730);
        $this->assertStringContainsString('catalog-media/sample-product.png', (string) $response->json('data.0.image_url'));
    }

    public function test_erp_order_export_returns_customer_and_items(): void
    {
        config()->set('integrations.tokens.erp', 'erp-secret');

        $user = User::factory()->create([
            'login' => 'integration-user',
            'email' => 'integration-user@example.com',
            'company' => 'MAJOR LLC',
        ]);

        $category = Category::query()->create([
            'name' => 'Профили',
            'slug' => 'profili',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль A5',
            'name' => 'Профиль A5',
            'slug' => 'profil-a5',
            'image_path' => 'catalog-media/profil-a5.png',
            'sort_order' => 0,
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-20260402-00001',
            'status' => 'new',
            'payment_status' => 'pending',
            'items_count' => 1,
            'subtotal_amount' => 1200,
            'total_amount' => 1200,
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_slug' => $product->slug,
            'quantity' => 2,
            'price_label' => 'Цена 1',
            'unit_price' => 600,
            'line_total' => 1200,
            'source_sheet' => 'A5+',
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'erp-secret',
        ])->getJson(route('api.integrations.orders.show', ['order' => $order->number]));

        $response->assertOk();
        $response->assertJsonPath('data.number', 'ORD-20260402-00001');
        $response->assertJsonPath('data.customer.company', 'MAJOR LLC');
        $response->assertJsonPath('data.items.0.product_slug', 'profil-a5');
    }

    public function test_erp_price_sync_updates_product_prices(): void
    {
        config()->set('integrations.tokens.erp', 'erp-secret');

        $category = Category::query()->create([
            'name' => 'Расходка',
            'slug' => 'rashodka',
            'sort_order' => 0,
            'accent_color' => '#0f766e',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Hanov',
            'name' => 'Шпатель Hanov',
            'slug' => 'shpatel-hanov',
            'source_sheet' => 'Hanov',
            'source_row' => 18,
            'price_from' => 1000,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
            'display_value' => '1000,00',
            'min_amount' => 1000,
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'erp-secret',
        ])->postJson(route('api.integrations.catalog.prices.sync'), [
            'reset_missing' => true,
            'items' => [
                [
                    'external_id' => 'product-'.$product->id,
                    'prices' => [
                        [
                            'column_index' => 1,
                            'label' => 'Цена 1',
                            'min_amount' => 1490,
                        ],
                        [
                            'column_index' => 2,
                            'label' => 'Цена дилера',
                            'min_amount' => 1320,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated', 1);
        $this->assertDatabaseCount('product_prices', 2);
        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'column_index' => 2,
            'label' => 'Цена дилера',
        ]);
        $this->assertSame(1320.0, (float) $product->fresh()->price_from);
    }

    public function test_checkout_pushes_order_to_erp_when_endpoint_is_configured(): void
    {
        config()->set('integrations.erp.orders_push_url', 'https://erp.example.com/orders/import');
        config()->set('integrations.erp.outgoing_token', 'erp-outgoing');
        config()->set('integrations.erp.outgoing_token_header', 'X-Erp-Key');

        Http::fake([
            'https://erp.example.com/orders/import' => Http::response([
                'integration_reference' => '1c-order-77',
            ], 200),
        ]);

        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профили',
            'slug' => 'profiles',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль Major',
            'name' => 'Профиль Major',
            'slug' => 'profil-major',
            'source_sheet' => 'MAJOR',
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 1,
            'label' => 'Цена 1',
            'display_value' => '530,00',
            'min_amount' => 530,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->post(route('cart.checkout'), [
            'comment' => 'Срочно в работу',
        ]);

        $response->assertRedirect(route('orders.index'));

        $order = Order::query()->firstOrFail();

        Http::assertSent(function ($request) use ($order): bool {
            return $request->url() === 'https://erp.example.com/orders/import'
                && $request->hasHeader('X-Erp-Key', 'erp-outgoing')
                && data_get($request->data(), 'order.number') === $order->number
                && data_get($request->data(), 'items.0.external_id') !== null;
        });

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'integration_status' => 'synced',
            'integration_reference' => '1c-order-77',
        ]);
    }

    public function test_payment_webhook_updates_order_payment_state(): void
    {
        config()->set('integrations.tokens.payments', 'pay-secret');

        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'number' => 'ORD-20260402-00002',
            'status' => 'new',
            'payment_status' => 'pending',
            'items_count' => 1,
            'total_amount' => 1800,
            'placed_at' => now(),
        ]);

        $response = $this->withHeaders([
            'X-Integration-Key' => 'pay-secret',
        ])->postJson(route('api.integrations.payments.webhook'), [
            'order_number' => $order->number,
            'payment_status' => 'paid',
            'payment_method' => 'bank-card',
            'payment_reference' => 'trn-123456',
            'paid_amount' => 1800,
            'payment_payload' => [
                'gateway' => 'demo-pay',
                'status' => 'paid',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.payment_status', 'paid');
        $response->assertJsonPath('data.status', 'processing');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'payment_method' => 'bank-card',
            'payment_reference' => 'trn-123456',
        ]);
    }
}
