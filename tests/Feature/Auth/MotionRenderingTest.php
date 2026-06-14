<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — the data-motion + 3 granular flags rendered on
 * every page. The middleware runs in the web group, so the <html> tag
 * carries the attributes even on routes that never hit the auth flow.
 */
class MotionRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_html_has_data_motion_attribute_set(): void
    {
        $response = $this->get('/login');
        $html = $response->getContent();
        $this->assertStringContainsString('data-motion=', $html);
    }

    public function test_root_html_reflects_reduced_preference(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'reduced',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/<html[^>]*data-motion="reduced"/',
            $html,
            'Reduced preference must render as data-motion="reduced"'
        );
    }

    public function test_root_html_has_granular_backdrop_flag_attribute(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'full',
            'motion_backdrop' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/data-motion-backdrop="0"/',
            $html,
            'motion_backdrop=0 must propagate to the data attribute'
        );
    }

    public function test_root_html_has_granular_parallax_flag_attribute(): void
    {
        $user = User::factory()->create([
            'motion_preference' => 'full',
            'motion_parallax' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression(
            '/data-motion-parallax="0"/',
            $html,
            'motion_parallax=0 must propagate to the data attribute'
        );
    }
}
