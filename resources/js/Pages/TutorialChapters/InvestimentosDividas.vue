<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoInvestimentosDividas from '@/Components/Tutorial/DemoInvestimentosDividas.vue';
</script>

<template>
    <ChapterShell
        title="Investimentos e dividas"
        intro="Os dois lados do patrimonio liquido. O Solar trata ambos com a mesma seriedade."
        :paragraphs="[
            'Posicoes de investimento: ticker, quantidade, preco medio, preco atual. O Solar calcula o lucro nao realizado e a rentabilidade percentual automaticamente. Atualize o preco atual manualmente ou via FASE 9 (integracao com CoinGecko e Yahoo Finance).',
            'Dividas sao tratadas como contas com saldo devedor. Um financiamento de imovel e uma conta com saldo negativo de R$ 320.000, amortizada mensalmente. O app mostra o percentual quitado e a parcela mensal.',
            'A vista consolidada de patrimonio: soma de contas positivas, menos dividas, mais valor de mercado dos investimentos. E o numero que importa, e o Solar te da em um relance.'
        ]"
        deep-link="/investments"
        deep-link-label="Ir para Investimentos"
    >
        <template #demo>
            <DemoInvestimentosDividas />
        </template>
    </ChapterShell>
</template>
