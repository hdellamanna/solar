<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — the /settings/appearance page (the Motion preferences UI).
 */
class MotionSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_appearance_settings_page(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/settings/appearance');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Appearance')
            ->has('user')
        );
    }

    public function test_authenticated_user_can_save_reduced_motion_preference(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->patch('/settings/appearance', [
                'motion_preference' => 'reduced',
                'motion_backdrop' => true,
                'motion_spring' => true,
                'motion_parallax' => true,
            ]);
        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('reduced', $user->motion_preference);
    }

    public function test_authenticated_user_can_toggle_backdrop_independently(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->patch('/settings/appearance', [
                'motion_preference' => 'auto',
                'motion_backdrop' => false,
                'motion_spring' => true,
                'motion_parallax' => true,
            ]);
        $response->assertRedirect();
        $user->refresh();
        $this->assertFalse((bool) $user->motion_backdrop);
        $this->assertTrue((bool) $user->motion_spring);
        $this->assertTrue((bool) $user->motion_parallax);
    }

    public function test_unauthenticated_user_is_redirected_from_appearance_page(): void
    {
        $response = $this->get('/settings/appearance');
        $response->assertRedirect('/login');
    }
}
