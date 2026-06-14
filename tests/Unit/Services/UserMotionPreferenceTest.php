<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\UserMotionPreference;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Unit tests for {@see UserMotionPreference}.
 *
 * Covers the 6 canonical cases for shouldAnimate(category) and the
 * 3 cases for resolvedMotion().
 *
 * OS reduced-motion is injected via the `__test_reduced_motion` request
 * param (the service supports this for testability; real requests use
 * the Sec-CH-Prefers-Reduced-Motion header).
 */
class UserMotionPreferenceTest extends TestCase
{
    // --------------------------------------------------------------------------
    // shouldAnimate — canonical cases
    // --------------------------------------------------------------------------

    /** Guest / new user (no account), OS = full → animate. */
    public function test_should_animate_true_for_guest_with_os_full(): void
    {
        $req = Request::create('/settings/appearance');
        $prefs = new UserMotionPreference(null);

        $this->assertTrue($prefs->shouldAnimate('backdrop', $req));
        $this->assertTrue($prefs->shouldAnimate('spring', $req));
        $this->assertTrue($prefs->shouldAnimate('parallax', $req));
    }

    /** Guest / new user, OS = reduced → never animate. */
    public function test_should_animate_false_for_guest_with_os_reduced(): void
    {
        $req = Request::create('/settings/appearance?' . http_build_query(['__test_reduced_motion' => '1']));
        $prefs = new UserMotionPreference(null);

        $this->assertFalse($prefs->shouldAnimate('backdrop', $req));
        $this->assertFalse($prefs->shouldAnimate('spring', $req));
        $this->assertFalse($prefs->shouldAnimate('parallax', $req));
    }

    /** User explicitly set pref=reduced → false regardless of category. */
    public function test_should_animate_false_when_user_pref_is_reduced(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'reduced',
            'motion_backdrop'   => true,
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $req = Request::create('/settings/appearance');
        $prefs = new UserMotionPreference($user);

        $this->assertFalse($prefs->shouldAnimate('backdrop', $req));
        $this->assertFalse($prefs->shouldAnimate('spring', $req));
        $this->assertFalse($prefs->shouldAnimate('parallax', $req));
    }

    /** User explicitly set pref=full → per-category flags still apply. */
    public function test_should_animate_honours_per_category_flags_even_when_pref_is_full(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'full',
            'motion_backdrop'   => false,  // user disabled backdrop specifically
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $req = Request::create('/settings/appearance');
        $prefs = new UserMotionPreference($user);

        // backdrop is off; spring and parallax are on
        $this->assertFalse($prefs->shouldAnimate('backdrop', $req));
        $this->assertTrue($prefs->shouldAnimate('spring', $req));
        $this->assertTrue($prefs->shouldAnimate('parallax', $req));
    }

    /** User pref=auto, OS=full, backdrop disabled → false for backdrop, true for others. */
    public function test_should_animate_granular_override_with_auto_and_os_full(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'auto',
            'motion_backdrop'   => false,  // specifically disabled
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $req = Request::create('/settings/appearance');  // OS = full (no __test param)
        $prefs = new UserMotionPreference($user);

        $this->assertFalse($prefs->shouldAnimate('backdrop', $req));
        $this->assertTrue($prefs->shouldAnimate('spring', $req));
        $this->assertTrue($prefs->shouldAnimate('parallax', $req));
    }

    /** User pref=auto, OS=reduced → OS always wins, per-category flags are ignored. */
    public function test_should_animate_false_when_os_prefers_reduced_ignores_user_flags(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'auto',
            'motion_backdrop'   => true,   // user says yes
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $req = Request::create('/settings/appearance?' . http_build_query(['__test_reduced_motion' => '1']));
        $prefs = new UserMotionPreference($user);

        // OS reduced wins — per-category flags are irrelevant
        $this->assertFalse($prefs->shouldAnimate('backdrop', $req));
        $this->assertFalse($prefs->shouldAnimate('spring', $req));
        $this->assertFalse($prefs->shouldAnimate('parallax', $req));
    }

    // --------------------------------------------------------------------------
    // resolvedMotion()
    // --------------------------------------------------------------------------

    public function test_resolved_motion_returns_user_choice_when_not_auto(): void
    {
        $user = User::factory()->make(['motion_preference' => 'reduced']);
        $req = Request::create('/settings/appearance');

        $prefs = new UserMotionPreference($user);
        $this->assertSame('reduced', $prefs->resolvedMotion($req));

        $user2 = User::factory()->make(['motion_preference' => 'full']);
        $prefs2 = new UserMotionPreference($user2);
        $this->assertSame('full', $prefs2->resolvedMotion($req));
    }

    public function test_resolved_motion_returns_full_when_auto_and_os_not_reduced(): void
    {
        $user = User::factory()->make(['motion_preference' => 'auto']);
        $req = Request::create('/settings/appearance');  // OS = full

        $prefs = new UserMotionPreference($user);
        $this->assertSame('full', $prefs->resolvedMotion($req));
    }

    public function test_resolved_motion_returns_reduced_when_auto_and_os_prefers_reduced(): void
    {
        $user = User::factory()->make(['motion_preference' => 'auto']);
        $req = Request::create('/settings/appearance?' . http_build_query(['__test_reduced_motion' => '1']));

        $prefs = new UserMotionPreference($user);
        $this->assertSame('reduced', $prefs->resolvedMotion($req));
    }

    // --------------------------------------------------------------------------
    // toInertiaProps()
    // --------------------------------------------------------------------------

    public function test_to_inertia_props_honours_per_category_flags_with_full_user_pref(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'full',
            'motion_backdrop'   => false,
            'motion_spring'     => true,
            'motion_parallax'   => false,
        ]);

        $req = Request::create('/settings/appearance');
        $prefs = new UserMotionPreference($user);
        $props = $prefs->toInertiaProps($req);

        $this->assertSame('full', $props['preference']);
        $this->assertFalse($props['backdrop']);   // user disabled
        $this->assertTrue($props['spring']);
        $this->assertFalse($props['parallax']);  // user disabled
    }

    public function test_to_inertia_props_returns_all_false_when_os_prefers_reduced_and_user_on_auto(): void
    {
        $user = User::factory()->make([
            'motion_preference' => 'auto',   // user on auto — OS wins
            'motion_backdrop'   => true,
            'motion_spring'     => true,
            'motion_parallax'   => true,
        ]);

        $req = Request::create('/settings/appearance?' . http_build_query(['__test_reduced_motion' => '1']));
        $prefs = new UserMotionPreference($user);
        $props = $prefs->toInertiaProps($req);

        // User on 'auto' + OS prefers reduced → effective state is 'reduced'
        // and all 3 granular flags resolve to false.
        $this->assertSame('reduced', $props['preference']);
        $this->assertFalse($props['backdrop']);
        $this->assertFalse($props['spring']);
        $this->assertFalse($props['parallax']);
    }

    public function test_to_inertia_props_keeps_full_preference_but_zeroes_granular_flags_when_user_chose_full(): void
    {
        // The user explicitly chose 'full' — this overrides the OS
        // preference (per the FASE 4D contract: "full" ignores OS).
        // However, the user's granular flags (which they all set to
        // false) still apply individually.
        $user = User::factory()->make([
            'motion_preference' => 'full',
            'motion_backdrop'   => false,
            'motion_spring'     => false,
            'motion_parallax'   => false,
        ]);

        $req = Request::create('/settings/appearance?' . http_build_query(['__test_reduced_motion' => '1']));
        $prefs = new UserMotionPreference($user);
        $props = $prefs->toInertiaProps($req);

        $this->assertSame('full', $props['preference']);
        $this->assertFalse($props['backdrop']);
        $this->assertFalse($props['spring']);
        $this->assertFalse($props['parallax']);
    }
}