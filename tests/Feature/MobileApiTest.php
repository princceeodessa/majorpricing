<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\MobileAccessToken;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RegistrationRequest;
use App\Models\SupportMessage;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_returns_token_and_logout_revokes_it(): void
    {
        $user = User::factory()->create([
            'login' => 'mobile-client',
            'email' => 'mobile-client@example.com',
            'password' => 'secret12345',
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'login' => 'mobile-client',
            'password' => 'secret12345',
            'device_name' => 'Pixel 8',
            'platform' => 'android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'client')
            ->assertJsonStructure(['token', 'expires_at']);

        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->withToken($token)
            ->postJson('/api/mobile/auth/logout')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->withToken($token)
            ->getJson('/api/mobile/me')
            ->assertUnauthorized();
    }

    public function test_mobile_protected_routes_require_bearer_token(): void
    {
        $this->getJson('/api/mobile/catalog')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Необходима авторизация.');
    }

    public function test_mobile_catalog_returns_user_state_for_favorites_and_cart(): void
    {
        [$user, $product] = $this->createUserAndProduct();
        [$token] = $this->issueToken($user);

        $this->withToken($token)
            ->postJson('/api/mobile/favorites/'.$product->id)
            ->assertOk()
            ->assertJsonPath('is_favorite', true);

        $this->withToken($token)
            ->postJson('/api/mobile/cart/items', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('summary.total_quantity', 2);

        $this->withToken($token)
            ->getJson('/api/mobile/catalog')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('data.0.cart_quantity', 2)
            ->assertJsonPath('data.0.price', '530.00');
    }

    public function test_mobile_cart_update_and_delete(): void
    {
        [$user, $product] = $this->createUserAndProduct();
        [$token] = $this->issueToken($user);

        $cartResponse = $this->withToken($token)
            ->postJson('/api/mobile/cart/items', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertOk();

        $itemId = $cartResponse->json('items.0.id');

        $this->withToken($token)
            ->patchJson('/api/mobile/cart/items/'.$itemId, ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 4)
            ->assertJsonPath('summary.total_quantity', 4);

        $this->withToken($token)
            ->deleteJson('/api/mobile/cart/items/'.$itemId)
            ->assertOk()
            ->assertJsonPath('summary.total_quantity', 0);

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_mobile_checkout_creates_order_and_clears_cart(): void
    {
        [$user, $product] = $this->createUserAndProduct();
        [$token] = $this->issueToken($user);

        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'title' => 'Основной адрес',
            'address' => 'Саратов, тестовая улица, 1',
            'is_default' => true,
            'sort_order' => 0,
        ]);

        CartItem::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->withToken($token)
            ->postJson('/api/mobile/orders', [
                'address_id' => $address->id,
                'payment_method' => 'bank_transfer',
                'comment' => 'Доставить после 12:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_amount', '1590.00');

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'items_count' => 1,
            'total_amount' => 1590,
            'comment' => 'Доставить после 12:00',
        ]);

        $order = Order::query()->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/mobile/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('items.0.product_id', $product->id);
    }

    public function test_mobile_client_can_fetch_and_send_support_messages(): void
    {
        $manager = User::factory()->create([
            'login' => 'mobile-support-manager',
            'email' => 'mobile-support-manager@example.com',
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'login' => 'mobile-support-client',
            'email' => 'mobile-support-client@example.com',
            'manager_id' => $manager->id,
        ]);

        [$token] = $this->issueToken($client);

        $this->withToken($token)
            ->postJson('/api/mobile/support/messages', [
                'message' => 'Нужна помощь по заказу.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.manager_id', $manager->id)
            ->assertJsonPath('data.sender_id', $client->id)
            ->assertJsonPath('data.is_own', true);

        $this->assertDatabaseHas('support_messages', [
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $client->id,
            'message' => 'Нужна помощь по заказу.',
        ]);

        $this->withToken($token)
            ->getJson('/api/mobile/support/messages')
            ->assertOk()
            ->assertJsonPath('client.id', $client->id)
            ->assertJsonPath('data.0.message', 'Нужна помощь по заказу.')
            ->assertJsonPath('data.0.is_own', true);
    }

    public function test_mobile_manager_sees_own_clients_and_can_reply_only_to_them(): void
    {
        $manager = User::factory()->create([
            'login' => 'mobile-manager-owner',
            'email' => 'mobile-manager-owner@example.com',
            'is_manager' => true,
        ]);

        $otherManager = User::factory()->create([
            'login' => 'mobile-manager-other',
            'email' => 'mobile-manager-other@example.com',
            'is_manager' => true,
        ]);

        $client = User::factory()->create([
            'name' => 'Клиент менеджера',
            'login' => 'mobile-manager-client',
            'email' => 'mobile-manager-client@example.com',
            'manager_id' => $manager->id,
        ]);

        $otherClient = User::factory()->create([
            'name' => 'Чужой клиент',
            'login' => 'mobile-manager-other-client',
            'email' => 'mobile-manager-other-client@example.com',
            'manager_id' => $otherManager->id,
        ]);

        SupportMessage::query()->create([
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $client->id,
            'message' => 'Есть вопрос по счету.',
        ]);

        [$token] = $this->issueToken($manager);

        $this->withToken($token)
            ->getJson('/api/mobile/manager/clients')
            ->assertOk()
            ->assertJsonPath('data.0.id', $client->id)
            ->assertJsonPath('data.0.name', 'Клиент менеджера')
            ->assertJsonPath('data.0.unread_messages_count', 1)
            ->assertJsonMissing(['id' => $otherClient->id]);

        $this->withToken($token)
            ->postJson('/api/mobile/support/messages', [
                'client_id' => $client->id,
                'message' => 'Ответ менеджера.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.manager_id', $manager->id)
            ->assertJsonPath('data.sender_id', $manager->id)
            ->assertJsonPath('data.is_own', true);

        $this->assertDatabaseHas('support_messages', [
            'client_id' => $client->id,
            'manager_id' => $manager->id,
            'sender_id' => $manager->id,
            'message' => 'Ответ менеджера.',
        ]);

        $this->withToken($token)
            ->postJson('/api/mobile/support/messages', [
                'client_id' => $otherClient->id,
                'message' => 'Попытка ответа чужому клиенту.',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('support_messages', [
            'manager_id' => $manager->id,
            'client_id' => $otherClient->id,
        ]);
    }

    public function test_mobile_manager_can_approve_and_reject_registration_requests(): void
    {
        $manager = User::factory()->create([
            'login' => 'mobile-request-manager',
            'email' => 'mobile-request-manager@example.com',
            'is_manager' => true,
        ]);

        [$token] = $this->issueToken($manager);

        $registrationRequest = RegistrationRequest::query()->create([
            'name' => 'Новый клиент',
            'company' => 'ООО Новый клиент',
            'contact_person' => 'Анна',
            'phone' => '+7 999 222-33-44',
            'delivery_address' => 'Саратов, тестовый адрес, 2',
            'login' => 'mobile-approved-client',
            'email' => 'mobile-approved-client@example.com',
            'password' => 'secret12345',
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        $rejectedRequest = RegistrationRequest::query()->create([
            'name' => 'Отклоняемый клиент',
            'login' => 'mobile-rejected-client',
            'email' => 'mobile-rejected-client@example.com',
            'password' => 'secret12345',
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        $this->withToken($token)
            ->getJson('/api/mobile/manager/registration-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $rejectedRequest->id)
            ->assertJsonPath('data.1.id', $registrationRequest->id);

        $this->withToken($token)
            ->postJson('/api/mobile/manager/registration-requests/'.$registrationRequest->id.'/approve')
            ->assertOk()
            ->assertJsonPath('request.status', RegistrationRequest::STATUS_APPROVED)
            ->assertJsonPath('user.login', 'mobile-approved-client')
            ->assertJsonPath('user.role', 'client');

        $this->assertDatabaseHas('users', [
            'login' => 'mobile-approved-client',
            'email' => 'mobile-approved-client@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
            'is_admin' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'title' => 'Основной адрес',
            'address' => 'Саратов, тестовый адрес, 2',
            'is_default' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/mobile/manager/registration-requests/'.$rejectedRequest->id.'/reject')
            ->assertOk()
            ->assertJsonPath('request.status', RegistrationRequest::STATUS_REJECTED);

        $this->assertDatabaseMissing('users', [
            'login' => 'mobile-rejected-client',
        ]);
    }

    private function issueToken(User $user): array
    {
        [, $plainToken] = MobileAccessToken::issueForUser($user, [
            'device_name' => 'Feature test',
            'platform' => 'android',
        ]);

        return [$plainToken];
    }

    private function createUserAndProduct(): array
    {
        $user = User::factory()->create([
            'login' => 'mobile-user-'.uniqid(),
            'email' => 'mobile-user-'.uniqid().'@example.com',
            'password' => 'secret12345',
            'name' => 'Иван Клиент',
            'company' => 'ООО Клиент',
            'phone' => '+7 999 111-22-33',
        ]);

        $category = Category::query()->create([
            'name' => 'Профили',
            'slug' => 'profiles-'.uniqid(),
            'sort_order' => 0,
            'accent_color' => '#d11117',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'title' => 'Профиль M',
            'name' => 'Профиль M',
            'slug' => 'profil-m-'.uniqid(),
            'source_sheet' => 'Major',
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

        return [$user, $product];
    }
}
