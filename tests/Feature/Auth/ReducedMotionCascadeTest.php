<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — when the user has reduced motion, the data-attributes
 * cascade to the HTML. The actual visual effect is pure CSS — these tests
 * pin the data-attribute contract that the CSS depends on.
 */
class ReducedMotionCascadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reduced_motion_disables_backdrop_via_data_attribute(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'reduced',
            'motion_backdrop' => true, // even if user wants it on, reduced wins
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $html = $response->getContent();
        // The resolved motion is "reduced" (regardless of granular flags),
        // and the granular flag is rendered as 0.
        $this->assertMatchesRegularExpression('/data-motion="reduced"/', $html);
        $this->assertMatchesRegularExpression('/data-motion-backdrop="0"/', $html);
    }

    public function test_reduced_motion_disables_parallax_via_data_attribute(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'reduced',
            'motion_parallax' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-motion="reduced"/', $html);
        $this->assertMatchesRegularExpression('/data-motion-parallax="0"/', $html);
    }
}
