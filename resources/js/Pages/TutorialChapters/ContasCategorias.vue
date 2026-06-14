<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoContasCategorias from '@/Components/Tutorial/DemoContasCategorias.vue';
</script>

<template>
    <ChapterShell
        title="Contas e categorias"
        intro="A base de tudo. Crie contas para onde o dinheiro entra e sai, e uma arvore de categorias que faca sentido pra voce."
        :paragraphs="[
            'Uma conta no Solar representa um lugar onde seu dinheiro vive: corrente, poupanca, carteira, cartao. Cada transacao e registrada em uma conta especifica, e o saldo de cada uma e calculado em tempo real.',
            'Categorias sao como tags inteligentes. Em vez de forcar uma taxonomia pronta, voce monta a sua propria: comece com 5-10 categorias amplas (Moradia, Alimentacao, Transporte, Lazer, Trabalho) e adicione subcategorias conforme precisar.',
            'Atribuir icones e cores as categorias ajuda a identificar transacoes no fluxo visual. Categorias builtin (como Transferencia) tem icones e regras de validacao pre-definidas para reduzir erros.'
        ]"
        deep-link="/accounts"
        deep-link-label="Ir para Contas"
    >
        <template #demo>
            <DemoContasCategorias />
        </template>
    </ChapterShell>
</template>
