<?php

namespace Tests\Feature;

use App\Models\PriceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_account_page(): void
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
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Личный кабинет')
            ->assertSee('ООО Партнер')
            ->assertSee('Партнерский прайс')
            ->assertSee('Цена партнера');
    }
}
