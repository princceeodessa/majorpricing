<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\PriceProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAndOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_product_to_cart(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'cart-user',
            'email' => 'cart-user@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profiliya',
            'sort_order' => 0,
            'accent_color' => '#f97316',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль M',
            'name' => 'Профиль M',
            'slug' => 'profil-m',
            'source_sheet' => 'Major',
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

        $response = $this->actingAs($user)->post(route('cart.store', $product), [
            'quantity' => 3,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $cartPage = $this->actingAs($user)->get(route('cart.index'));

        $cartPage->assertOk();
        $cartPage->assertSeeText('Профиль M');
        $cartPage->assertSeeText('Оформить заказ');
    }

    public function test_checkout_moves_cart_to_order_history(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Партнерский прайс',
            'slug' => 'partner-price',
            'column_index' => 2,
            'price_label' => 'Цена 2',
            'is_default' => false,
        ]);

        $user = User::factory()->create([
            'login' => 'order-user',
            'email' => 'order-user@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Инструмент',
            'slug' => 'instrument',
            'sort_order' => 0,
            'accent_color' => '#2563eb',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Шпатель Hanov',
            'name' => 'Шпатель Hanov',
            'slug' => 'shpatel-hanov',
            'source_sheet' => 'Hanov',
            'price_from' => 2000,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => 2,
            'label' => 'Цена 2',
            'display_value' => '2000,00',
            'min_amount' => 2000,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('cart.checkout'), [
            'comment' => 'Проверить наличие на складе',
        ]);

        $response->assertRedirect(route('orders.index'));
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'new',
            'items_count' => 1,
            'price_profile_name' => 'Партнерский прайс',
            'comment' => 'Проверить наличие на складе',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_title' => 'Шпатель Hanov',
            'quantity' => 2,
        ]);

        $order = Order::query()->firstOrFail();
        $historyPage = $this->actingAs($user)->get(route('orders.index'));

        $historyPage->assertOk();
        $historyPage->assertSeeText($order->number);
        $historyPage->assertSeeText('Шпатель Hanov');
        $historyPage->assertSeeText('Проверить наличие на складе');
    }
}
