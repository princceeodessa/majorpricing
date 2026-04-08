<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_sees_profile_screen_with_manager_contacts_and_support(): void
    {
        $manager = User::factory()->create([
            'name' => 'Мария Менеджер',
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'phone' => '+7 900 000-00-01',
            'telegram' => '@manager_major',
            'messengers' => ['@manager_major'],
            'is_manager' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Партнер',
            'company' => 'ООО Партнер',
            'login' => 'partner-demo',
            'email' => 'partner-demo@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
        ]);

        SupportMessage::query()->create([
            'client_id' => $user->id,
            'manager_id' => $manager->id,
            'sender_id' => $manager->id,
            'message' => 'Добрый день, подскажите адрес разгрузки.',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Профиль клиента')
            ->assertSee('Ваш менеджер')
            ->assertSee('Мария Менеджер')
            ->assertSee('@manager_major')
            ->assertSee('Вопрос менеджеру')
            ->assertSee('Добрый день, подскажите адрес разгрузки.')
            ->assertSee('Адреса для заявок')
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

    public function test_manager_sees_only_managed_clients_on_account_screen(): void
    {
        $manager = User::factory()->create([
            'name' => 'Менеджер',
            'company' => 'MAJOR',
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'is_manager' => true,
        ]);

        $otherManager = User::factory()->create([
            'login' => 'manager-other',
            'email' => 'manager-other@example.com',
            'is_manager' => true,
        ]);

        $managedClient = User::factory()->create([
            'name' => 'Клиент А',
            'login' => 'client-a',
            'email' => 'client-a@example.com',
            'manager_id' => $manager->id,
            'is_manager' => false,
        ]);

        User::factory()->create([
            'name' => 'Клиент Б',
            'login' => 'client-b',
            'email' => 'client-b@example.com',
            'manager_id' => $otherManager->id,
            'is_manager' => false,
        ]);

        SupportMessage::query()->create([
            'client_id' => $managedClient->id,
            'manager_id' => $manager->id,
            'sender_id' => $managedClient->id,
            'message' => 'Когда сможете подтвердить заявку?',
        ]);

        $this->actingAs($manager)
            ->get('/account')
            ->assertOk()
            ->assertSee('Мои клиенты')
            ->assertSee('Клиент А')
            ->assertSee('Когда сможете подтвердить заявку?')
            ->assertDontSee('Клиент Б');
    }
}
