<script setup>
import ChapterShell from './ChapterShell.vue';
import DemoSeguranca from '@/Components/Tutorial/DemoSeguranca.vue';
</script>

<template>
    <ChapterShell
        title="Seguranca"
        intro="Sua ledger pessoal precisa ser tao segura quanto seu banco. O Solar vem com 2FA, dispositivos confiaveis e auditoria built-in."
        :paragraphs="[
            'Ative a autenticacao em 2 fatores (TOTP) na primeira vez que entrar. O app gera um QR code para Google Authenticator, 1Password, Authy ou Bitwarden. Toda vez que voce logar precisa do codigo de 6 digitos alem da senha.',
            'Apos confirmar o 2FA, escolha confiar no dispositivo atual por 90 dias. Apos isso, novos pedidos de 2FA. Voce pode revogar qualquer dispositivo confiavel a qualquer momento.',
            'Os 10 codigos de recuperacao sao a unica saida caso voce perca o autenticador. Imprima ou guarde em um gerenciador de senhas. Cada codigo so serve uma vez. Em FASE 8, regeneracao automatica de codigos usados.'
        ]"
        deep-link="/settings/security"
        deep-link-label="Ir para Seguranca"
    >
        <template #demo>
            <DemoSeguranca />
        </template>
    </ChapterShell>
</template>
