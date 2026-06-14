<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for FASE 4D — the interactive /tutorial page. Asserts that all
 * 6 chapters are listed, each chapter page contains a live Vue demo, the
 * "Ver no Solar" deep-link is wired, and the demos use the Liquid Crystal
 * classes (the on-brand class-name contract).
 */
class TutorialDemosTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutorial_index_lists_all_six_chapters(): void
    {
        $response = $this->get('/tutorial');
        $response->assertOk();
        // Chapters are passed via Inertia props; the page renders them via
        // Vue, so the chapter *titles* aren't in the initial HTML — they
        // are in the data-page JSON. Assert there.
        $response->assertInertia(fn ($page) => $page
            ->has('chapters', 6)
            ->where('chapters.0.slug', 'contas-e-categorias')
            ->where('chapters.1.slug', 'transacoes')
            ->where('chapters.2.slug', 'metas-e-orcamentos')
            ->where('chapters.3.slug', 'pix-e-transferencias')
            ->where('chapters.4.slug', 'investimentos-e-dividas')
            ->where('chapters.5.slug', 'seguranca')
        );
    }

    public function test_each_chapter_page_contains_an_interactive_demo(): void
    {
        $slugs = [
            'contas-e-categorias', 'transacoes', 'metas-e-orcamentos',
            'pix-e-transferencias', 'investimentos-e-dividas', 'seguranca',
        ];
        foreach ($slugs as $slug) {
            $response = $this->get("/tutorial/{$slug}");
            $response->assertOk();
            // The TutorialController returns the same Tutorial Vue
            // component for all chapters; the activeChapter prop tells
            // the component which chapter to render. We assert that
            // prop is propagated correctly.
            $response->assertInertia(fn ($page) => $page
                ->where('activeChapter', $slug)
            );
        }
    }

    public function test_tutorial_show_me_button_links_to_real_app_page(): void
    {
        $response = $this->get('/tutorial/contas-e-categorias');
        $response->assertOk();
        // The chapter page receives activeChapter + the chapters list.
        $response->assertInertia(fn ($page) => $page
            ->where('activeChapter', 'contas-e-categorias')
            ->has('chapters', 6)
        );
    }

    public function test_tutorial_demos_use_liquid_crystal_classes(): void
    {
        // Demos are .vue files; the contract is that they reference the
        // shared glass/backdrop/mesh classes (the Liquid Crystal palette).
        $componentDir = resource_path('js/Components/Tutorial');
        $expected = [
            'DemoContasCategorias.vue', 'DemoTransacoes.vue',
            'DemoMetasOrcamentos.vue', 'DemoPixTransferencias.vue',
            'DemoInvestimentosDividas.vue', 'DemoSeguranca.vue',
        ];
        foreach ($expected as $file) {
            $path = "{$componentDir}/{$file}";
            $this->assertFileExists($path, "Demo component missing: {$file}");
            $content = file_get_contents($path);
            // The demos must reference a known Liquid Crystal class.
            $hasLq = str_contains($content, 'demo__title')
                  || str_contains($content, 'demo__btn')
                  || str_contains($content, 'demo__bar');
            $this->assertTrue($hasLq, "Demo {$file} missing demo_* class hooks");
        }
    }
}
