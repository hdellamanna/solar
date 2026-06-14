<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoMetasOrcamentos from '@/Components/Tutorial/DemoMetasOrcamentos.vue';
</script>

<template>
    <ChapterShell
        title="Metas e orcamentos"
        intro="A disciplina do Solar, sem virar um app de produtividade. Defina o que importa e o app te mostra quando voce se afasta do plano."
        :paragraphs="[
            'Metas sao objetivos financeiros com prazo: a reserva de emergencia, a entrada do apartamento, a viagem. Defina o valor alvo, o prazo, e a parcela mensal. O Solar calcula quanto sobra ou quanto falta e atualiza a cada transacao.',
            'Orcamentos sao tetos mensais por categoria. Quando o gasto passa de 80% o app te avisa em silencio (sem pop-ups). Acima de 100% a barra fica vermelha. Voce decide se ignora ou se ajusta.',
            'Ambos sao passiveis: nao bloqueiam transacoes, nao te enchem de notificação. O Solar acredita que financas pessoais funcionam com visibilidade, nao com restricao.'
        ]"
        deep-link="/goals"
        deep-link-label="Ir para Metas"
    >
        <template #demo>
            <DemoMetasOrcamentos />
        </template>
    </ChapterShell>
</template>
