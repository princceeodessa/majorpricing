<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_sees_profile_screen_with_contact_form(): void
    {
        $user = User::factory()->create([
            'name' => 'Партнер',
            'company' => 'ООО Партнер',
            'login' => 'partner-demo',
            'email' => 'partner-demo@example.com',
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Профиль клиента')
            ->assertSee('Адреса для заявок')
            ->assertSee('ООО Партнер')
            ->assertDontSee('Добавление пользователя');
    }

    public function test_regular_user_can_update_profile_contacts(): void
    {
        $user = User::factory()->create([
            'name' => 'Клиент',
            'login' => 'client-demo',
            'email' => 'client-demo@example.com',
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'name' => 'Иван Клиент',
                'company' => 'ООО Объект',
                'contact_people' => ['Алексей', 'Мария'],
                'phone' => '+7 999 123-45-67',
                'messengers' => ['@majorclient', 'WhatsApp +7 999 123-45-67'],
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('Иван Клиент', $user->name);
        $this->assertSame('ООО Объект', $user->company);
        $this->assertSame('Алексей', $user->contact_person);
        $this->assertSame(['Алексей', 'Мария'], $user->contact_people);
        $this->assertSame('+7 999 123-45-67', $user->phone);
        $this->assertSame('@majorclient', $user->telegram);
        $this->assertSame(['@majorclient', 'WhatsApp +7 999 123-45-67'], $user->messengers);
    }

    public function test_regular_user_can_add_address_and_mark_it_default(): void
    {
        $user = User::factory()->create([
            'name' => 'Клиент',
            'login' => 'client-address-demo',
            'email' => 'client-address-demo@example.com',
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->post(route('account.addresses.store'), [
                'title' => 'Склад Энгельс',
                'address' => 'Энгельс, ул. Заводская, 1',
                'is_default' => '1',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'title' => 'Склад Энгельс',
            'address' => 'Энгельс, ул. Заводская, 1',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'delivery_address' => 'Энгельс, ул. Заводская, 1',
        ]);
    }

    public function test_manager_sees_user_management_screen(): void
    {
        $manager = User::factory()->create([
            'name' => 'Менеджер',
            'company' => 'MAJOR',
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'is_manager' => true,
        ]);

        $this->actingAs($manager)
            ->get('/account')
            ->assertOk()
            ->assertSee('Клиенты, контакты и работа с заказами в одном месте.')
            ->assertSee('Добавление пользователя')
            ->assertSee('Открыть заказы клиентов');
    }
}
