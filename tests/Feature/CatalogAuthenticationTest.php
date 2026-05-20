<?php

namespace Tests\Feature;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_login(): void
    {
        $profile = PriceProfile::query()->create([
            'name' => 'Базовый прайс',
            'slug' => 'base-price',
            'column_index' => 1,
            'price_label' => 'Цена 1',
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'login' => 'manager-demo',
            'email' => 'manager-demo@example.com',
            'password' => 'secret12345',
            'price_profile_id' => $profile->id,
        ]);

        $response = $this->post('/login', [
            'login' => 'manager-demo',
            'password' => 'secret12345',
        ]);

        $response->assertRedirect('/catalog');
        $this->assertAuthenticatedAs($user);
    }
}
