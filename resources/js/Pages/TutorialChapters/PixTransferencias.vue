<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoPixTransferencias from '@/Components/Tutorial/DemoPixTransferencias.vue';
</script>

<template>
    <ChapterShell
        title="PIX e transferencias"
        intro="Registre cada movimento entre contas, e entre voce e o mundo, com 3 toques."
        :paragraphs="[
            'Um PIX no Solar e um lancamento com tipo Transferencia, conta de origem, e uma chave ou nome do destinatario. Em vez de colar a chave real (que pode mudar), o Solar guarda um apelido: @fulano, @loja-x, @aluguel.',
            'Transferencias entre as suas proprias contas seguem o mesmo modelo: Nubank para XP, XP para Reserva, etc. O saldo geral nao muda, mas o saldo de cada conta individual acompanha.',
            'O Solar nao se integra com bancos (de proposito). Integracao bancaria sacrifica privacidade e abre vetores de seguranca. Em vez disso, registrar manualmente cada transferencia toma 5 segundos e te da consciencia exata do fluxo.'
        ]"
        deep-link="/pix"
        deep-link-label="Ir para PIX"
    >
        <template #demo>
            <DemoPixTransferencias />
        </template>
    </ChapterShell>
</template>
