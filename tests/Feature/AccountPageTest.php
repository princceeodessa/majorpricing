<?php

namespace Tests\Feature;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_sees_profile_screen_with_contact_form(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Партнерский прайс',
            'slug' => 'partner-price',
            'column_index' => 2,
            'price_label' => 'Цена партнера',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Партнер',
            'company' => 'ООО Партнер',
            'login' => 'partner-demo',
            'email' => 'partner-demo@example.com',
            'price_profile_id' => $profile->id,
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Доступ подтвержден.')
            ->assertSee('Профиль клиента')
            ->assertSee('ООО Партнер')
            ->assertSee('Партнерский прайс')
            ->assertDontSee('Добавление пользователя');
    }

    public function test_regular_user_can_update_profile_contacts(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Клиент',
            'login' => 'client-demo',
            'email' => 'client-demo@example.com',
            'price_profile_id' => $profile->id,
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('account.update'), [
                'name' => 'Иван Клиент',
                'company' => 'ООО Объект',
                'contact_person' => 'Алексей',
                'phone' => '+7 999 123-45-67',
                'telegram' => '@majorclient',
                'delivery_address' => 'Саратов, ул. Тестовая, 5',
            ])
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Иван Клиент',
            'company' => 'ООО Объект',
            'contact_person' => 'Алексей',
            'phone' => '+7 999 123-45-67',
            'telegram' => '@majorclient',
            'delivery_address' => 'Саратов, ул. Тестовая, 5',
        ]);
    }

    public function test_manager_sees_user_management_screen(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $manager = User::factory()->create([
            'name' => 'Менеджер',
            'company' => 'MAJOR',
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'price_profile_id' => $profile->id,
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
