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
use Tests\TestCase;

class CartAndOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_product_to_cart(): void
    {
        [$user, $product] = $this->createUserAndProduct();

        $response = $this->actingAs($user)->post(route('cart.store', $product), [
            'quantity' => 3,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSeeText('Оформление заказа')
            ->assertSeeText('Приобрести и отправить заявку');
    }

    public function test_checkout_moves_cart_to_order_history_and_stores_contact_snapshot(): void
    {
        [$user, $product] = $this->createUserAndProduct(
            priceLabel: 'Цена 2',
            profileName: 'Партнерский прайс',
            profileSlug: 'partner-price',
            profileColumn: 2,
            unitPrice: 2000,
        );

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($user)->post(route('cart.checkout'), [
            'name' => 'Иван Клиент',
            'company' => 'ООО Объект',
            'contact_person' => 'Алексей',
            'phone' => '+7 999 111-22-33',
            'telegram' => '@majorbuyer',
            'delivery_address' => 'Саратов, ул. Тестовая, 5',
            'comment' => 'Проверить наличие на складе',
            'save_profile' => '1',
        ]);

        $response->assertRedirect(route('orders.index'));

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'new',
            'items_count' => 1,
            'price_profile_name' => 'Партнерский прайс',
            'customer_name' => 'Иван Клиент',
            'customer_company' => 'ООО Объект',
            'customer_contact_person' => 'Алексей',
            'customer_phone' => '+7 999 111-22-33',
            'customer_telegram' => '@majorbuyer',
            'customer_delivery_address' => 'Саратов, ул. Тестовая, 5',
            'comment' => 'Проверить наличие на складе',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_title' => $product->title,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Иван Клиент',
            'company' => 'ООО Объект',
            'contact_person' => 'Алексей',
            'phone' => '+7 999 111-22-33',
            'telegram' => '@majorbuyer',
            'delivery_address' => 'Саратов, ул. Тестовая, 5',
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSeeText($order->number)
            ->assertSeeText($product->title)
            ->assertSeeText('Проверить наличие на складе');
    }

    public function test_manager_can_update_order_status_and_comment(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $manager = User::factory()->create([
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'price_profile_id' => $profile->id,
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'login' => 'client-demo',
            'email' => 'client-demo@example.com',
            'price_profile_id' => $profile->id,
            'is_manager' => false,
        ]);

        $order = Order::query()->create([
            'user_id' => $client->id,
            'number' => 'ORD-20260406-00001',
            'status' => 'new',
            'items_count' => 1,
            'customer_name' => 'Иван Клиент',
            'customer_company' => 'ООО Объект',
            'customer_email' => 'client-demo@example.com',
            'customer_phone' => '+7 900 000-00-00',
            'customer_contact_person' => 'Иван',
            'comment' => 'Позвонить перед выездом',
            'placed_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_title' => 'Профиль M',
            'quantity' => 1,
        ]);

        $this->actingAs($manager)
            ->patch(route('orders.update', $order), [
                'status' => 'processing',
                'manager_comment' => 'Созвонились, заказ подтвержден.',
            ])
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
            'manager_comment' => 'Созвонились, заказ подтвержден.',
        ]);
    }

    private function createUserAndProduct(
        string $priceLabel = 'Цена 1',
        string $profileName = 'Базовый прайс',
        string $profileSlug = 'base-price',
        int $profileColumn = 1,
        float $unitPrice = 530,
    ): array {
        $profile = PriceProfile::query()->create([
            'name' => $profileName,
            'slug' => $profileSlug,
            'column_index' => $profileColumn,
            'price_label' => $priceLabel,
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'cart-user-'.$profileColumn,
            'email' => 'cart-user-'.$profileColumn.'@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $category = Category::query()->create([
            'name' => 'Профиля',
            'slug' => 'profiles-'.$profileColumn,
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль M',
            'name' => 'Профиль M',
            'slug' => 'profil-m-'.$profileColumn,
            'source_sheet' => 'Major',
            'price_from' => $unitPrice,
            'sort_order' => 0,
        ]);

        ProductPrice::query()->create([
            'product_id' => $product->id,
            'column_index' => $profileColumn,
            'label' => $priceLabel,
            'display_value' => number_format($unitPrice, 2, ',', ''),
            'min_amount' => $unitPrice,
        ]);

        return [$user, $product];
    }
}
