<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tutorial page (FASE 4D).
 *
 * Public page with 6 interactive chapters. No auth required.
 */
class TutorialController extends Controller
{
    private const CHAPTER_SLUGS = [
        'contas-e-categorias',
        'transacoes',
        'metas-e-orcamentos',
        'pix-e-transferencias',
        'investimentos-e-dividas',
        'seguranca',
    ];

    /**
     * Render the tutorial index (all chapters listed).
     */
    public function __invoke(): Response
    {
        return Inertia::render('Tutorial', [
            'chapters' => $this->chapters(),
            'activeChapter' => null,
        ]);
    }

    /**
     * Render a single chapter by slug.
     */
    public function chapter(Request $request): Response
    {
        $slug = $request->route('chapter');

        abort_unless(in_array($slug, self::CHAPTER_SLUGS, true), 404);

        return Inertia::render('Tutorial', [
            'chapters' => $this->chapters(),
            'activeChapter' => $slug,
        ]);
    }

    /**
     * Static definition of the 6 tutorial chapters.
     *
     * @return array<int, array{slug: string, title: string, icon: string}>
     */
    private function chapters(): array
    {
        return [
            [
                'slug'  => 'contas-e-categorias',
                'title' => 'Contas e categorias',
                'icon'  => '🏦',
            ],
            [
                'slug'  => 'transacoes',
                'title' => 'Transações',
                'icon'  => '💸',
            ],
            [
                'slug'  => 'metas-e-orcamentos',
                'title' => 'Metas e orçamentos',
                'icon'  => '🎯',
            ],
            [
                'slug'  => 'pix-e-transferencias',
                'title' => 'PIX e transferências',
                'icon'  => '⚡',
            ],
            [
                'slug'  => 'investimentos-e-dividas',
                'title' => 'Investimentos e dívidas',
                'icon'  => '📈',
            ],
            [
                'slug'  => 'seguranca',
                'title' => 'Segurança',
                'icon'  => '🔐',
            ],
        ];
    }
}