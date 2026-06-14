<script setup>
/**
 * DemoTransacoes — interactive mini-app for the "Transacoes" chapter.
 * Lets the user add income/expense entries with category and amount.
 * Computes a running balance and groups by type.
 */
import { ref, computed } from 'vue';

const transactions = ref([
    { id: 1, type: 'expense', desc: 'Almoco', amount: 45.00, category: 'Alimentacao' },
    { id: 2, type: 'income', desc: 'Salario', amount: 8500.00, category: 'Trabalho' },
    { id: 3, type: 'expense', desc: 'Uber', amount: 28.50, category: 'Transporte' },
]);
const newType = ref('expense');
const newDesc = ref('');
const newAmount = ref(0);
const newCategory = ref('Alimentacao');

function add() {
    if (!newDesc.value.trim() || newAmount.value <= 0) return;
    transactions.value.unshift({
        id: Date.now(),
        type: newType.value,
        desc: newDesc.value,
        amount: parseFloat(newAmount.value),
        category: newCategory.value,
    });
    newDesc.value = '';
    newAmount.value = 0;
}

const balance = computed(() =>
    transactions.value.reduce((sum, t) =>
        sum + (t.type === 'income' ? t.amount : -t.amount), 0)
);
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">Saldo atual</h4>
        <div class="demo__balance" :class="balance >= 0 ? 'demo__balance--pos' : 'demo__balance--neg'">
            R$ {{ balance.toFixed(2) }}
        </div>
        <h4 class="demo__title">Nova transacao</h4>
        <div class="demo__row">
            <select v-model="newType" class="demo__select">
                <option value="expense">Despesa</option>
                <option value="income">Receita</option>
            </select>
            <input v-model="newDesc" class="demo__input" placeholder="Descricao" />
        </div>
        <div class="demo__row">
            <input v-model.number="newAmount" class="demo__input" type="number" step="0.01" placeholder="Valor" />
            <select v-model="newCategory" class="demo__select">
                <option>Alimentacao</option>
                <option>Transporte</option>
                <option>Moradia</option>
                <option>Lazer</option>
                <option>Trabalho</option>
            </select>
            <button class="demo__btn" @click="add">+</button>
        </div>
        <h4 class="demo__title">Historico</h4>
        <ul class="demo__list">
            <li v-for="t in transactions" :key="t.id" class="demo__item">
                <span :class="['demo__type', t.type === 'income' ? 'demo__type--inc' : 'demo__type--exp']">
                    {{ t.type === 'income' ? '+' : '-' }}
                </span>
                <span class="demo__name">{{ t.desc }}</span>
                <span class="demo__cat">{{ t.category }}</span>
                <span :class="['demo__amount', t.type === 'income' ? 'demo__amount--inc' : 'demo__amount--exp']">
                    R$ {{ t.amount.toFixed(2) }}
                </span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__balance {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    padding: 0.75rem;
    border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.demo__balance--pos { color: #10b981; }
.demo__balance--neg { color: #ef4444; }
.demo__row { display: flex; gap: 0.5rem; margin-bottom: 0.5rem; }
.demo__input, .demo__select {
    flex: 1; padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.875rem;
}
.demo__select { flex: 0 0 9rem; }
.demo__btn {
    padding: 0.5rem 0.875rem; border-radius: 0.5rem;
    background: #f59e0b; color: #0b0f1a;
    font-weight: 600; border: none; cursor: pointer;
    font-size: 0.95rem;
}
.demo__list { list-style: none; padding: 0; margin: 0.5rem 0; }
.demo__item {
    display: grid; grid-template-columns: auto 1fr auto auto;
    gap: 0.5rem; align-items: center;
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 0.25rem;
}
.demo__type {
    width: 1.25rem; height: 1.25rem;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 0.25rem; font-weight: 700; font-size: 0.85rem;
}
.demo__type--inc { background: rgba(16, 185, 129, 0.18); color: #10b981; }
.demo__type--exp { background: rgba(239, 68, 68, 0.18); color: #ef4444; }
.demo__name { font-weight: 500; }
.demo__cat { font-size: 0.75rem; color: rgba(255, 255, 255, 0.55); }
.demo__amount { font-family: 'JetBrains Mono', monospace; }
.demo__amount--inc { color: #10b981; }
.demo__amount--exp { color: #ef4444; }
</style>
