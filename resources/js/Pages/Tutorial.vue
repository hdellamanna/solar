<script setup>
/**
 * Tutorial — index page listing the 6 chapters as glass cards.
 * Each card has a small inline SVG illustration and a slug.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    chapters: { type: Array, default: () => [] },
});

const defaultChapters = [
    { slug: 'contas-categorias', title: 'Contas e categorias', icon: 'wallet', summary: 'Crie contas, monte a arvore de categorias que faz sentido pra voce.' },
    { slug: 'transacoes', title: 'Transacoes', icon: 'arrows', summary: 'Receitas, despesas, transferencias entre contas e splits.' },
    { slug: 'metas-orcamentos', title: 'Metas e orcamentos', icon: 'target', summary: 'Defina uma meta, acompanhe, receba um aviso se sair do trilho.' },
    { slug: 'pix-transferencias', title: 'PIX e transferencias', icon: 'flash', summary: 'Registre PIX in/out e veja o saldo correndo em tempo real.' },
    { slug: 'investimentos-dividas', title: 'Investimentos e dividas', icon: 'chart', summary: 'Acompanhe posicoes de ativos e amortizacao de emprestimos.' },
    { slug: 'seguranca', title: 'Seguranca', icon: 'shield', summary: '2FA, dispositivos confiaveis, codigos de recuperacao, auditoria.' },
];
const chapters = props.chapters?.length ? props.chapters : defaultChapters;
</script>

<template>
    <Head title="Tutorial - Solar Money" />
    <PublicLayout>
        <article class="tutorial">
            <header class="tutorial__hero">
                <h1 class="tutorial__title">Tutorial interativo</h1>
                <p class="tutorial__lede">
                    Seis capitulos. Cada um com uma demo viva pra voce
                    experimentar antes de mexer no seu dinheiro de verdade.
                </p>
            </header>

            <div class="tutorial__grid">
                <Link
                    v-for="ch in chapters"
                    :key="ch.slug"
                    :href="route('tutorial.chapter', { chapter: ch.slug })"
                    class="tutorial__card"
                >
                    <div class="tutorial__icon" aria-hidden="true">
                        <svg v-if="ch.icon === 'wallet'" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="6" width="18" height="14" rx="3"/><path d="M16 13h2"/></svg>
                        <svg v-else-if="ch.icon === 'arrows'" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 7h10M14 4l3 3-3 3M17 17H7m3 3-3-3 3-3"/></svg>
                        <svg v-else-if="ch.icon === 'target'" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg>
                        <svg v-else-if="ch.icon === 'flash'" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg>
                        <svg v-else-if="ch.icon === 'chart'" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-7"/></svg>
                        <svg v-else viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l8 4v6c0 5-4 9-8 10-4-1-8-5-8-10V6l8-4z"/></svg>
                    </div>
                    <h2 class="tutorial__card-title">{{ ch.title }}</h2>
                    <p class="tutorial__card-summary">{{ ch.summary }}</p>
                    <span class="tutorial__card-cta">Experimentar &rarr;</span>
                </Link>
            </div>
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
.tutorial__lede {
    font-size: 1.125rem;
    color: rgba(255, 255, 255, 0.78);
    margin: 0;
    line-height: 1.6;
}
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
html[data-motion="reduced"] .tutorial__card,
html[data-motion="reduced"] .tutorial__card:hover {
    transition: none !important;
    transform: none !important;
}
</style>
