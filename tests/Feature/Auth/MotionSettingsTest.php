<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — motion preference settings (backend half).
 *
 * 6 test cases:
 *  1.  test_user_can_view_their_appearance_settings
 *  2.  test_user_can_update_motion_preference_to_reduced
 *  3.  test_user_can_update_motion_preference_to_full
 *  4.  test_user_can_granularly_disable_backdrop
 *  5.  test_validation_rejects_unknown_motion_value
 *  6.  test_motion_settings_round_trip_persists_all_4_fields
 */
class MotionSettingsTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. test_user_can_view_their_appearance_settings
    // ------------------------------------------------------------------
    public function test_user_can_view_their_appearance_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('settings.appearance.show'));

        $response->assertOk();
        // The Inertia page payload must reference the Settings/Appearance
        // component so the frontend can hydrate it.
        // Note: the JSON payload escapes the slash as \/ in the HTML attribute.
        $this->assertStringContainsString('"component":"Settings\/Appearance"', $response->getContent());
    }

    // ------------------------------------------------------------------
    // 2. test_user_can_update_motion_preference_to_reduced
    // ------------------------------------------------------------------
    public function test_user_can_update_motion_preference_to_reduced(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'auto',
            'motion_backdrop'   => true,
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $response = $this->actingAs($user)->patch(
            route('settings.appearance.update'),
            [
                'motion_preference' => 'reduced',
                'motion_backdrop'   => false,
                'motion_spring'     => false,
                'motion_parallax'   => false,
            ],
        );

        $response->assertRedirect(route('settings.appearance.show'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('reduced', $user->motion_preference);
        $this->assertFalse($user->motion_backdrop);
        $this->assertFalse($user->motion_spring);
        $this->assertFalse($user->motion_parallax);
    }

    // ------------------------------------------------------------------
    // 3. test_user_can_update_motion_preference_to_full
    // ------------------------------------------------------------------
    public function test_user_can_update_motion_preference_to_full(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'auto',
            'motion_backdrop'   => true,
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $response = $this->actingAs($user)->patch(
            route('settings.appearance.update'),
            [
                'motion_preference' => 'full',
                'motion_backdrop'   => true,
                'motion_spring'     => true,
                'motion_parallax'   => true,
            ],
        );

        $response->assertRedirect(route('settings.appearance.show'));

        $user->refresh();
        $this->assertSame('full', $user->motion_preference);
        $this->assertTrue($user->motion_backdrop);
        $this->assertTrue($user->motion_spring);
        $this->assertTrue($user->motion_parallax);
    }

    // ------------------------------------------------------------------
    // 4. test_user_can_granularly_disable_backdrop
    // ------------------------------------------------------------------
    public function test_user_can_granularly_disable_backdrop(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'auto',
            'motion_backdrop'   => true,
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $response = $this->actingAs($user)->patch(
            route('settings.appearance.update'),
            [
                'motion_preference' => 'auto',
                'motion_backdrop'   => false,
                'motion_spring'     => true,
                'motion_parallax'   => true,
            ],
        );

        $response->assertRedirect(route('settings.appearance.show'));

        $user->refresh();
        $this->assertSame('auto', $user->motion_preference);
        $this->assertFalse($user->motion_backdrop);
        $this->assertTrue($user->motion_spring);
        $this->assertTrue($user->motion_parallax);
    }

    // ------------------------------------------------------------------
    // 5. test_validation_rejects_unknown_motion_value
    // ------------------------------------------------------------------
    public function test_validation_rejects_unknown_motion_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch(
            route('settings.appearance.update'),
            [
                'motion_preference' => 'super-mega-animated',
                'motion_backdrop'   => true,
                'motion_spring'     => true,
                'motion_parallax'   => true,
            ],
        );

        $response->assertSessionHasErrors(['motion_preference']);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'motion_preference' => 'auto', // unchanged
        ]);
    }

    // ------------------------------------------------------------------
    // 6. test_motion_settings_round_trip_persists_all_4_fields
    // ------------------------------------------------------------------
    public function test_motion_settings_round_trip_persists_all_4_fields(): void
    {
        $user = User::factory()->create();

        $payload = [
            'motion_preference' => 'reduced',
            'motion_backdrop'   => false,
            'motion_spring'     => true,
            'motion_parallax'   => false,
        ];

        $this->actingAs($user)
            ->patch(route('settings.appearance.update'), $payload)
            ->assertRedirect(route('settings.appearance.show'));

        $this->assertDatabaseHas('users', [
            'id'                => $user->id,
            'motion_preference' => 'reduced',
            'motion_backdrop'   => 0,
            'motion_spring'     => 1,
            'motion_parallax'   => 0,
        ]);
    }
}