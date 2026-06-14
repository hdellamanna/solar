<script setup>
/**
 * DemoPixTransferencias — simulated PIX flow: pick sender account, recipient,
 * amount, and watch the balance update in real time.
 */
import { ref, computed } from 'vue';

const accounts = ref([
    { id: 1, name: 'Nubank', balance: 4250.50 },
    { id: 2, name: 'Itaú', balance: 1820.00 },
    { id: 3, name: 'XP', balance: 12750.00 },
]);
const fromId = ref(1);
const recipient = ref('');
const amount = ref(0);
const log = ref([]);

const fromAccount = computed(() => accounts.value.find(a => a.id === fromId.value));

function send() {
    const value = parseFloat(amount.value);
    if (!recipient.value.trim() || value <= 0 || !fromAccount.value) return;
    if (value > fromAccount.value.balance) {
        log.value.unshift({
            id: Date.now(),
            type: 'error',
            text: 'Saldo insuficiente para a transferencia.',
        });
        return;
    }
    fromAccount.value.balance = fromAccount.value.balance - value;
    log.value.unshift({
        id: Date.now(),
        type: 'success',
        text: `PIX de R$ ${value.toFixed(2)} enviado para ${recipient.value}.`,
    });
    recipient.value = '';
    amount.value = 0;
}
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">De qual conta?</h4>
        <select v-model.number="fromId" class="demo__select">
            <option v-for="a in accounts" :key="a.id" :value="a.id">
                {{ a.name }} &mdash; R$ {{ a.balance.toFixed(2) }}
            </option>
        </select>
        <h4 class="demo__title">Enviar PIX</h4>
        <input v-model="recipient" class="demo__input" placeholder="Chave PIX do destinatario" />
        <div class="demo__row">
            <input v-model.number="amount" class="demo__input" type="number" step="0.01" placeholder="Valor" />
            <button class="demo__btn" @click="send">Enviar</button>
        </div>
        <h4 class="demo__title">Atividade</h4>
        <ul class="demo__list">
            <li v-for="entry in log" :key="entry.id" :class="['demo__log', entry.type === 'error' ? 'demo__log--err' : 'demo__log--ok']">
                {{ entry.text }}
            </li>
            <li v-if="!log.length" class="demo__hint">Nenhuma transferencia ainda.</li>
        </ul>
    </div>
</template>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__select, .demo__input {
    width: 100%;
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}
.demo__row { display: flex; gap: 0.5rem; }
.demo__btn {
    padding: 0.5rem 0.875rem; border-radius: 0.5rem;
    background: #f59e0b; color: #0b0f1a;
    font-weight: 600; border: none; cursor: pointer;
    font-size: 0.875rem;
}
.demo__list { list-style: none; padding: 0; margin: 0.5rem 0; }
.demo__log {
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
}
.demo__log--ok { color: #10b981; border-left: 2px solid #10b981; }
.demo__log--err { color: #ef4444; border-left: 2px solid #ef4444; }
.demo__hint { color: rgba(255, 255, 255, 0.55); font-size: 0.8rem; }
</style>
