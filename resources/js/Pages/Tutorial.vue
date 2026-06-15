<script setup>
/**
 * Tutorial — FASE 4D + FASE 7 (i18n tri-língue).
 *
 * The controller passes a `chapters` array (6 entries) with
 * `slug`, `title`, `subtitle`, `body`, `icon` — all already
 * locale-aware because the controller reads from
 * `lang/{current_locale}/tutorial.php`. When `activeChapter`
 * is a non-null slug, the page renders that chapter's
 * `body` as paragraphs plus the matching `Demo*` component.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useT } from '@/Composables/useT';

// Demo* components — one per chapter. They keep their own
// mock data, but their copy is pulled from `useT()`.
import DemoContasCategorias from '@/Components/Tutorial/DemoContasCategorias.vue';
import DemoTransacoes from '@/Components/Tutorial/DemoTransacoes.vue';
import DemoMetasOrcamentos from '@/Components/Tutorial/DemoMetasOrcamentos.vue';
import DemoPixTransferencias from '@/Components/Tutorial/DemoPixTransferencias.vue';
import DemoInvestimentosDividas from '@/Components/Tutorial/DemoInvestimentosDividas.vue';
import DemoSeguranca from '@/Components/Tutorial/DemoSeguranca.vue';

const props = defineProps({
    chapters: { type: Array, default: () => [] },
    activeChapter: { type: String, default: null },
});

const page = usePage();
const { t } = useT();

// Resolve the active chapter object from the slug. Falls back
// to null when the controller sends an empty chapters array.
const activeChapterObj = computed(() => {
    if (!props.activeChapter) return null;
    return props.chapters.find((c) => c.slug === props.activeChapter) || null;
});

const isAuthed = computed(() => Boolean(page.props.auth?.user));

// Split `body` into paragraphs on double newlines (the lang
// files store the body as a single string with `\n\n` between
// paragraphs).
const bodyParagraphs = computed(() => {
    const body = activeChapterObj.value?.body;
    if (!body) return [];
    return body.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);
});

// Map the active chapter slug to its demo component.
const demoComponent = computed(() => {
    const slug = props.activeChapter;
    if (!slug) return null;
    return {
        'contas-e-categorias': DemoContasCategorias,
        'transacoes': DemoTransacoes,
        'metas-e-orcamentos': DemoMetasOrcamentos,
        'pix-e-transferencias': DemoPixTransferencias,
        'investimentos-e-dividas': DemoInvestimentosDividas,
        'seguranca': DemoSeguranca,
    }[slug] || null;
});

// Hero copy from useT() (so the public page can be re-rendered
// in any locale without re-bundling). Falls back to the
// controller-supplied hardcoded pt-BR string for safety.
const heroTitle = computed(() => t('app.tutorial') || 'Tutorial interativo');
const heroLede = computed(() => {
    // No dedicated key in app.php for the lede — keep the
    // hardcoded copy for now (the tutorial is pt-BR-first).
    return 'Seis capitulos. Cada um com uma demo viva pra voce experimentar antes de mexer no seu dinheiro de verdade.';
});
</script>

<template>
    <Head :title="activeChapterObj ? `${activeChapterObj.title} - Solar Money` : `Tutorial - Solar Money`" />
    <PublicLayout>
        <article class="tutorial">
            <!-- INDEX VIEW (no active chapter) -->
            <template v-if="!activeChapterObj">
                <header class="tutorial__hero">
                    <h1 class="tutorial__title">{{ heroTitle }}</h1>
                    <p class="tutorial__lede">{{ heroLede }}</p>
                </header>

                <div class="tutorial__grid">
                    <Link
                        v-for="ch in chapters"
                        :key="ch.slug"
                        :href="route('tutorial.chapter', { chapter: ch.slug })"
                        class="tutorial__card"
                    >
                        <div class="tutorial__icon" aria-hidden="true">
                            <span class="tutorial__emoji">{{ ch.icon || '📘' }}</span>
                        </div>
                        <h2 class="tutorial__card-title">{{ ch.title }}</h2>
                        <p class="tutorial__card-summary">{{ ch.subtitle }}</p>
                        <span class="tutorial__card-cta">Experimentar &rarr;</span>
                    </Link>
                </div>
            </template>

            <!-- CHAPTER DETAIL VIEW -->
            <template v-else>
                <Link :href="route('tutorial')" class="tutorial__back">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ t('app.back') }}
                </Link>

                <header class="tutorial__hero">
                    <div class="tutorial__chapter-emoji" aria-hidden="true">{{ activeChapterObj.icon }}</div>
                    <h1 class="tutorial__title tutorial__title--chapter">{{ activeChapterObj.title }}</h1>
                    <p v-if="activeChapterObj.subtitle" class="tutorial__lede">{{ activeChapterObj.subtitle }}</p>
                </header>

                <section class="tutorial__body">
                    <p v-for="(p, i) in bodyParagraphs" :key="i" class="tutorial__paragraph">{{ p }}</p>
                </section>

                <section v-if="demoComponent" class="tutorial__demo">
                    <component :is="demoComponent" />
                </section>

                <footer class="tutorial__footer">
                    <component
                        :is="isAuthed ? Link : 'a'"
                        :href="isAuthed ? route('dashboard') : route('login')"
                        class="tutorial__cta"
                    >
                        <span v-if="isAuthed">Abrir o Solar &rarr;</span>
                        <span v-else>{{ t('app.login') }} e abrir &rarr;</span>
                    </component>
                </footer>
            </template>
        </article>
    </PublicLayout>
</template>

<style scoped>
.tutorial { max-width: 64rem; margin: 0 auto; }
.tutorial__hero { margin-bottom: 2.5rem; }
.tutorial__title {
    font-family: 'Manrope', system-ui, sans-serif;
    font-size: 2.5rem;
    font-weight: 800;
    margin: 0 0 0.5rem;
    background: linear-gradient(120deg, #f59e0b 0%, #fbbf24 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.tutorial__title--chapter {
    background: linear-gradient(120deg, #fbbf24 0%, #f59e0b 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.tutorial__lede {
    font-size: 1.125rem;
    color: rgba(255, 255, 255, 0.78);
    margin: 0;
    line-height: 1.6;
}
.tutorial__back {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
    transition: color 120ms ease-out;
}
.tutorial__back:hover { color: #f59e0b; }

.tutorial__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
    gap: 1rem;
}
.tutorial__card {
    display: block;
    padding: 1.25rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    color: rgba(255, 255, 255, 0.92);
    text-decoration: none;
    transition: transform 200ms ease-out, border-color 200ms ease-out, background 200ms ease-out;
}
.tutorial__card:hover {
    transform: translateY(-2px);
    border-color: rgba(245, 158, 11, 0.45);
    background: rgba(245, 158, 11, 0.08);
}
.tutorial__icon {
    color: #f59e0b;
    margin-bottom: 0.75rem;
    font-size: 2rem;
    line-height: 1;
}
.tutorial__card-title {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
}
.tutorial__card-summary {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.65);
    margin: 0 0 0.75rem;
    line-height: 1.5;
}
.tutorial__card-cta {
    font-size: 0.875rem;
    color: #f59e0b;
    font-weight: 500;
}

/* Chapter detail */
.tutorial__chapter-emoji {
    font-size: 2.5rem;
    line-height: 1;
    margin-bottom: 0.5rem;
}
.tutorial__body {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-radius: 1rem;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.tutorial__paragraph {
    color: rgba(255, 255, 255, 0.85);
    font-size: 1rem;
    line-height: 1.7;
    margin: 0 0 1rem;
}
.tutorial__paragraph:last-child { margin-bottom: 0; }
.tutorial__demo {
    margin-bottom: 1.5rem;
}
.tutorial__footer {
    margin-top: 2rem;
    text-align: right;
}
.tutorial__cta {
    display: inline-flex;
    align-items: center;
    padding: 0.625rem 1.25rem;
    border-radius: 0.75rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    text-decoration: none;
    transition: transform 160ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.tutorial__cta:hover { transform: translateY(-1px); }

html[data-motion="reduced"] .tutorial__card,
html[data-motion="reduced"] .tutorial__card:hover,
html[data-motion="reduced"] .tutorial__cta {
    transition: none !important;
    transform: none !important;
}
</style>
