<script setup>
/**
 * DemoContasCategorias — interactive mini-app for the "Contas e categorias" chapter.
 * Lets the user add accounts and categories, attach icons, and see them
 * rendered as glass cards. Pure mock data, no backend.
 */
import { ref } from 'vue';

const accounts = ref([
    { id: 1, name: 'Conta corrente', icon: 'wallet', balance: 4250.50 },
    { id: 2, name: 'Cartao Nubank', icon: 'card', balance: 1820.00 },
    { id: 3, name: 'Poupanca', icon: 'piggy', balance: 12750.00 },
]);
const categories = ref([
    { id: 1, name: 'Moradia', icon: 'home', color: '#f59e0b' },
    { id: 2, name: 'Alimentacao', icon: 'food', color: '#10b981' },
    { id: 3, name: 'Transporte', icon: 'car', color: '#3b82f6' },
    { id: 4, name: 'Lazer', icon: 'game', color: '#a855f7' },
]);

const newAccount = ref('');
function addAccount() {
    if (!newAccount.value.trim()) return;
    accounts.value.push({
        id: Date.now(),
        name: newAccount.value,
        icon: 'wallet',
        balance: 0,
    });
    newAccount.value = '';
}
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">Crie uma conta</h4>
        <div class="demo__row">
            <input v-model="newAccount" class="demo__input" placeholder="Ex: Conta XP" @keyup.enter="addAccount" />
            <button class="demo__btn" @click="addAccount">Adicionar</button>
        </div>
        <h4 class="demo__title">Suas contas</h4>
        <ul class="demo__list">
            <li v-for="a in accounts" :key="a.id" class="demo__item">
                <span class="demo__name">{{ a.name }}</span>
                <span class="demo__amount">R$ {{ a.balance.toFixed(2) }}</span>
            </li>
        </ul>
        <h4 class="demo__title">Categorias</h4>
        <div class="demo__chips">
            <span v-for="c in categories" :key="c.id" class="demo__chip" :style="{ borderColor: c.color }">
                <span class="demo__dot" :style="{ background: c.color }"></span>{{ c.name }}
            </span>
        </div>
        <p class="demo__hint">
            Toque nos chips para atribuir uma transacao. No app real, esses
            dados persistem no banco e ficam disponiveis em todas as paginas.
        </p>
    </div>
</template>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__row { display: flex; gap: 0.5rem; }
.demo__input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.875rem;
}
.demo__btn {
    padding: 0.5rem 0.875rem;
    border-radius: 0.5rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-size: 0.875rem;
}
.demo__list { list-style: none; padding: 0; margin: 0.5rem 0; }
.demo__item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 0.25rem;
}
.demo__name { font-weight: 500; }
.demo__amount { font-family: 'JetBrains Mono', monospace; color: #10b981; }
.demo__chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.5rem 0; }
.demo__chip {
    display: inline-flex; align-items: center; gap: 0.35rem;
    padding: 0.3rem 0.65rem; border-radius: 999px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    font-size: 0.8rem;
}
.demo__dot { width: 0.5rem; height: 0.5rem; border-radius: 999px; }
.demo__hint { color: rgba(255, 255, 255, 0.55); font-size: 0.8rem; margin-top: 0.75rem; }
</style>
