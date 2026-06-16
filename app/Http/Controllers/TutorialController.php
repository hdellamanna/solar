<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tutorial page (FASE 4D + FASE 7 — i18n tri-língue).
 *
 * Public page with 6 interactive chapters. No auth required.
 *
 * FASE 7 — the chapter copy (`title`, `subtitle`, `body`) is now
 * looked up from `lang/{current_locale}/tutorial.php` so the
 * front-end can stay dumb (no `__()` calls in Vue — the backend
 * already shipped the right strings). The list of slugs and icons
 * is the same in every locale so the in-page navigation does not
 * need to be re-rendered.
 */
class TutorialController extends Controller
{
    /**
     * Slugs for the 6 chapters, in display order. The slug
     * is the URL identifier (`/tutorial/contas-e-categorias`) and
     * is locale-independent. The localized copy lives in
     * `lang/{locale}/tutorial.php` keyed by chapter number.
     */
    private const CHAPTERS = [
        1 => ['slug' => 'contas-e-categorias',       'icon' => '🏦'],
        2 => ['slug' => 'transacoes',                 'icon' => '💸'],
        3 => ['slug' => 'metas-e-orcamentos',         'icon' => '🎯'],
        4 => ['slug' => 'pix-e-transferencias',       'icon' => '⚡'],
        5 => ['slug' => 'investimentos-e-dividas',    'icon' => '📈'],
        6 => ['slug' => 'seguranca',                  'icon' => '🔐'],
    ];

    /**
     * Render the tutorial index (all chapters listed).
     */
    public function __invoke(): Response
    {
        return Inertia::render('Tutorial', [
            'chapters' => $this->buildChapterList(),
            'activeChapter' => null,
        ]);
    }

    /**
     * Render a single chapter by slug.
     */
    public function chapter(Request $request): Response
    {
        $slug = $request->route('chapter');

        abort_unless($this->slugExists($slug), 404);

        return Inertia::render('Tutorial', [
            'chapters' => $this->buildChapterList(),
            'activeChapter' => $slug,
        ]);
    }

    /**
     * Read the chapter copy from the active locale's tutorial lang
     * file and merge it with the locale-independent metadata
     * (slug + icon). Returns an array of 6 entries with `slug`,
     * `title`, `subtitle`, `body`, `icon`.
     *
     * @return array<int, array{slug: string, title: string, subtitle: string, body: string, icon: string}>
     */
    private function buildChapterList(): array
    {
        $locale = (string) app()->getLocale();
        $copy = $this->loadChapterCopy($locale);

        $out = [];
        foreach (self::CHAPTERS as $n => $meta) {
            $entry = $copy[$n] ?? [];
            $out[] = [
                'slug'    => $meta['slug'],
                'icon'    => $meta['icon'],
                'title'    => $entry['title'] ?? ucfirst(str_replace('-', ' ', $meta['slug'])),
                'subtitle' => $entry['subtitle'] ?? '',
                'body'     => $entry['body'] ?? '',
            ];
        }

        return $out;
    }

    /**
     * Pull the chapter copy from the active locale's lang file.
     * Falls back to the app's default locale when the active
     * locale has no tutorial file (e.g. during the first request
     * after a fresh install with `APP_LOCALE=pt-BR` but a user
     * session that picked an unsupported locale).
     *
     * @return array<int, array{title: string, subtitle: string, body: string, slug: string}>
     */
    private function loadChapterCopy(string $locale): array
    {
        $path = $this->tutorialPath($locale);
        if (! is_file($path)) {
            $path = $this->tutorialPath((string) config('app.fallback_locale', 'en'));
        }
        if (! is_file($path)) {
            return [];
        }
        $arr = require $path;
        return is_array($arr) ? ($arr['chapter'] ?? []) : [];
    }

    private function tutorialPath(string $locale): string
    {
        return lang_path(str_replace('-', '_', $locale) . '/tutorial.php');
    }

    private function slugExists(string $slug): bool
    {
        foreach (self::CHAPTERS as $meta) {
            if ($meta['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }
}
