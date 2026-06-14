<script setup>
/**
 * About — public page that tells the founding story.
 *
 * Five sections in Henrique's voice but with the brand voice — confident,
 * plain language, light wit. pt-BR copy. Static content, no data fetch.
 */
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Sobre - Solar Money" />
    <PublicLayout>
        <article class="about">
            <header class="about__hero">
                <h1 class="about__title">Solar Money</h1>
                <p class="about__lede">
                    Um aplicativo de financas pessoais que trata o seu dinheiro
                    como o que ele e: uma ledger quieta, nao um feed.
                </p>
            </header>

            <section class="about__section">
                <h2 class="about__h2">A origem</h2>
                <p>
                    Eu, Henrique Della Manna, fui um usuario pesado do
                    <em>Microsoft Money</em> por mais de uma decada. Lancava
                    cada transacao no mesmo dia, fechava o orcamento mensal
                    com um grafico de pizza, e dormia tranquilo sabendo
                    exatamente quanto tinha. Quando a Microsoft aposentou o
                    produto e as alternativas modernas ou inchavam ate virar
                    clones do Quicken ou pivotavam para apps de trade com
                    cripto em primeiro plano, a paisagem de software de
                    financas pessoais perdeu algo. Nao apenas o software
                    em si &mdash; a <em>filosofia</em>.
                </p>
                <p>
                    Dinheiro deveria ser um livro-caixa quieto e tedioso,
                    nao um feed que disputa sua atencao com seu patrimonio.
                    Decidi entao construir o meu proprio. Sem banco
                    integrado, sem upsell, sem dark pattern de vinculacao
                    bancaria, sem tier premium que tranca os seus dados.
                </p>
            </section>

            <section class="about__section">
                <h2 class="about__h2">O que e</h2>
                <p>
                    Solar e um app de financas pessoais construido sobre a
                    filosofia do Money: contas claras, transacoes de dupla
                    entrada, categorias previsiveis, zero ruido. A interface
                    Liquid Crystal e a interpretacao moderna desse ideal
                    &mdash; a mesma sensacao de livro-caixa calmo, traduzida
                    para a linguagem visual de 2026: vidro, luz, movimento
                    sutil. E como olhar para um relogio bem desenhado: a
                    informacao esta la, mas sem brigar por atencao.
                </p>
            </section>

            <section class="about__section">
                <h2 class="about__h2">O como</h2>
                <p>
                    Codigo aberto (MIT), self-hostable, single-tenant por
                    padrao. Stack: Laravel 13 + Vue 3 + Inertia 3 + SQLite
                    no desenvolvimento, Postgres em producao. A
                    arquitetura e intencionalmente tediosa para que qualquer
                    pessoa que saiba ler um arquivo PHP possa auditar o
                    proprio dinheiro. Seguranca: 2FA, logs estruturados,
                    rate limit nas rotas de autenticacao, armazenamento de
                    tokens isolado, Sentry-ready.
                </p>
            </section>

            <section class="about__section">
                <h2 class="about__h2">O roadmap</h2>
                <ul class="about__list">
                    <li><strong>v0.11.0</strong> &mdash; Configuracoes de
                        animacao + pagina Sobre + Tutorial interativo + AI
                        agent hook (voce esta aqui).</li>
                    <li><strong>FASE 7</strong> &mdash; i18n tri-lingue
                        (pt-BR, es, en) com nome_pt / nome_es / nome_en
                        numa unica linha de Category e Tag.</li>
                    <li><strong>FASE 8</strong> &mdash; Assistente IA com
                        Groq + tool-use: o agente em todas as paginas que
                        interpreta "Adicionei R$ 50 de almoco hoje" e
                        executa.</li>
                    <li><strong>FASE 9</strong> &mdash; Crypto tracker via
                        CoinGecko com P&amp;L em tempo real.</li>
                    <li><strong>FASE 10</strong> &mdash; Installer Tauri
                        com binarios para Mac, Windows e Linux.</li>
                </ul>
            </section>

            <section class="about__section about__section--founder">
                <h2 class="about__h2">O fundador</h2>
                <p class="about__founder">
                    <strong>Henrique Della Manna</strong> &mdash;
                    <a href="https://github.com/hdellamanna" target="_blank" rel="noopener">@hdellamanna</a>
                    no GitHub. Construindo no Rio, lancando de Porto Real.
                </p>
            </section>
        </article>
    </PublicLayout>
</template>

<style scoped>
.about { max-width: 48rem; margin: 0 auto; }
.about__hero { margin-bottom: 3rem; }
.about__title {
    font-family: 'Manrope', system-ui, sans-serif;
    font-size: 2.75rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    background: linear-gradient(120deg, #f59e0b 0%, #fbbf24 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.about__lede {
    font-size: 1.125rem;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.78);
    margin: 0;
}
.about__section { margin: 2.5rem 0; }
.about__h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 1rem;
    color: rgba(255, 255, 255, 0.95);
}
.about__section p {
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.78);
    margin: 0 0 1rem;
}
.about__list { padding-left: 1.25rem; line-height: 1.7; color: rgba(255, 255, 255, 0.78); }
.about__list li { margin: 0.5rem 0; }
.about__founder { font-size: 1.05rem; }
.about__founder a { color: #f59e0b; text-decoration: none; }
.about__founder a:hover { text-decoration: underline; }
</style>
