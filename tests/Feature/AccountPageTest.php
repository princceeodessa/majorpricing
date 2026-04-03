<?php

namespace Tests\Feature;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_sees_access_only_screen(): void
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
            ->assertSee('ООО Партнер')
            ->assertSee('Партнерский прайс')
            ->assertDontSee('Добавление доступа');
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
            ->assertSee('Управление пользователями')
            ->assertSee('Добавление доступа')
            ->assertSee('Новый пользователь');
    }
}
