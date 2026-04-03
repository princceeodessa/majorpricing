<?php

namespace Tests\Feature;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_user(): void
    {
        $defaultProfile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $partnerProfile = PriceProfile::query()->create([
            'name' => 'Партнерский прайс',
            'slug' => 'partner-price',
            'column_index' => 2,
            'price_label' => 'Цена 2',
            'is_default' => false,
        ]);

        $manager = User::factory()->create([
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'price_profile_id' => $defaultProfile->id,
            'is_manager' => true,
        ]);

        $response = $this->actingAs($manager)->post(route('manager.users.store'), [
            'name' => 'Новый клиент',
            'company' => 'ООО Новый клиент',
            'login' => 'new_client',
            'email' => 'new-client@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'price_profile_id' => $partnerProfile->id,
            'is_active' => '1',
        ]);

        $response
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'name' => 'Новый клиент',
            'company' => 'ООО Новый клиент',
            'login' => 'new_client',
            'email' => 'new-client@example.com',
            'price_profile_id' => $partnerProfile->id,
            'is_active' => true,
            'is_manager' => false,
        ]);
    }

    public function test_regular_user_cannot_create_user(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'regular-demo',
            'email' => 'regular-demo@example.com',
            'price_profile_id' => $profile->id,
            'is_manager' => false,
        ]);

        $this->actingAs($user)
            ->post(route('manager.users.store'), [
                'name' => 'Новый клиент',
                'login' => 'new_client',
                'email' => 'new-client@example.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
            ])
            ->assertForbidden();
    }
}
