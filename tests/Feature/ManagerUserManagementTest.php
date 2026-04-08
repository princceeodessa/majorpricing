<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_user(): void
    {
        $manager = User::factory()->create([
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'is_manager' => true,
        ]);

        $response = $this->actingAs($manager)->post(route('manager.users.store'), [
            'name' => 'Новый клиент',
            'company' => 'ООО Новый клиент',
            'contact_person' => 'Алексей',
            'phone' => '+7 999 111-22-33',
            'telegram' => '@newclient',
            'delivery_address' => 'Саратов, объект 5',
            'login' => 'new_client',
            'email' => 'new-client@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'is_active' => '1',
        ]);

        $response
            ->assertRedirect(route('account.show'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('users', [
            'name' => 'Новый клиент',
            'company' => 'ООО Новый клиент',
            'contact_person' => 'Алексей',
            'phone' => '+7 999 111-22-33',
            'telegram' => '@newclient',
            'delivery_address' => 'Саратов, объект 5',
            'login' => 'new_client',
            'email' => 'new-client@example.com',
            'manager_id' => $manager->id,
            'price_profile_id' => null,
            'is_active' => true,
            'is_manager' => false,
        ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => User::query()->where('login', 'new_client')->value('id'),
            'title' => 'Основной адрес',
            'address' => 'Саратов, объект 5',
            'is_default' => true,
        ]);
    }

    public function test_regular_user_cannot_create_user(): void
    {
        $user = User::factory()->create([
            'login' => 'regular-demo',
            'email' => 'regular-demo@example.com',
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
