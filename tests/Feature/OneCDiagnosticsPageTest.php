<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCDiagnosticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_one_c_diagnostics_page(): void
    {
        config()->set('integrations.one_c.username', 'site-exchange');
        config()->set('integrations.one_c.password', 'secret-1c');

        $manager = User::factory()->create([
            'is_manager' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('manager.onec.show'));

        $response->assertOk();
        $response->assertSee('Диагностика 1С');
        $response->assertSee(route('onec.exchange'));
        $response->assertSee('site-exchange');
    }

    public function test_regular_user_cannot_open_one_c_diagnostics_page(): void
    {
        $user = User::factory()->create([
            'is_manager' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('manager.onec.show'))
            ->assertForbidden();
    }
}
