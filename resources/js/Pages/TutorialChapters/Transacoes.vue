<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoTransacoes from '@/Components/Tutorial/DemoTransacoes.vue';
</script>

<template>
    <ChapterShell
        title="Transacoes"
        intro="O coracao do Solar. Cada transacao e um lancamento com data, valor, conta, categoria e descricao."
        :paragraphs="[
            'Registre uma despesa quando o dinheiro sai, uma receita quando entra. O saldo da conta atualiza imediatamente. Use descricoes uteis: o futuro voce vai agradecer quando estiver procurando aquela compra especifica.',
            'Transferencias entre contas sao transacoes especiais: uma saida na conta de origem, uma entrada na conta de destino, ambas com a mesma data e a categoria Transferencia. O Solar vincula as duas para que o saldo geral nao duplique.',
            'Splits dividem uma transacao entre varias categorias: um almoco de R$ 80 com 3 amigos e 60% pessoal, 40% trabalho. O Solar distribui os valores automaticamente e mostra a composicao na hora.'
        ]"
        deep-link="/transactions"
        deep-link-label="Ir para Transacoes"
    >
        <template #demo>
            <DemoTransacoes />
        </template>
    </ChapterShell>
</template>
