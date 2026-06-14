<script setup>
/**
 * DemoSeguranca — 2FA + trusted device demo. Toggle 2FA on/off, add a
 * trusted device, generate a recovery code.
 */
import { ref, computed } from 'vue';

const twoFactorEnabled = ref(false);
const trustedDevices = ref([
    { id: 1, name: 'MacBook Pro', location: 'Rio de Janeiro, BR', added: '2026-05-12' },
]);
const recoveryCodes = ref([]);

function generateCodes() {
    recoveryCodes.value = Array.from({ length: 5 }, () =>
        Math.random().toString(36).slice(2, 8).toUpperCase()
    );
}

function toggle() {
    twoFactorEnabled.value = !twoFactorEnabled.value;
}

function trustDevice() {
    trustedDevices.value.push({
        id: Date.now(),
        name: 'Novo dispositivo',
        location: 'Sao Paulo, BR',
        added: new Date().toISOString().slice(0, 10),
    });
}

const status = computed(() => twoFactorEnabled.value ? 'Ativado' : 'Desativado');
const statusClass = computed(() => twoFactorEnabled.value ? 'demo__status--ok' : 'demo__status--warn');
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">Autenticacao em 2 fatores</h4>
        <div class="demo__status-row">
            <span :class="['demo__status', statusClass]">
                <span class="demo__status-dot"></span>{{ status }}
            </span>
            <button class="demo__btn" @click="toggle">
                {{ twoFactorEnabled ? 'Desativar' : 'Ativar' }}
            </button>
        </div>

        <h4 class="demo__title">Dispositivos confiaveis ({{ trustedDevices.length }})</h4>
        <ul class="demo__list">
            <li v-for="d in trustedDevices" :key="d.id" class="demo__device">
                <div class="demo__row1">
                    <span class="demo__name">{{ d.name }}</span>
                    <span class="demo__sub">{{ d.location }} &middot; desde {{ d.added }}</span>
                </div>
            </li>
        </ul>
        <button class="demo__btn demo__btn--ghost" @click="trustDevice">
            + Confiar neste dispositivo
        </button>

        <h4 class="demo__title">Codigos de recuperacao</h4>
        <p class="demo__hint">
            Guarde esses 10 codigos em um lugar seguro. Cada um pode ser usado
            uma vez caso voce perca o acesso ao autenticador.
        </p>
        <div v-if="recoveryCodes.length" class="demo__codes">
            <code v-for="c in recoveryCodes" :key="c">{{ c }}</code>
        </div>
        <button class="demo__btn" @click="generateCodes">
            {{ recoveryCodes.length ? 'Regenerar' : 'Gerar' }} codigos
        </button>
    </div>
</template>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__status-row { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.demo__status {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.3rem 0.65rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 500;
}
.demo__status-dot { width: 0.45rem; height: 0.45rem; border-radius: 999px; }
.demo__status--ok { background: rgba(16, 185, 129, 0.18); color: #10b981; }
.demo__status--ok .demo__status-dot { background: #10b981; }
.demo__status--warn { background: rgba(245, 158, 11, 0.18); color: #f59e0b; }
.demo__status--warn .demo__status-dot { background: #f59e0b; }
.demo__list { list-style: none; padding: 0; margin: 0.5rem 0; }
.demo__device, .demo__position, .demo__debt {
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 0.25rem;
}
.demo__row1 { display: flex; justify-content: space-between; align-items: center; }
.demo__name { font-weight: 600; }
.demo__sub { font-size: 0.75rem; color: rgba(255, 255, 255, 0.55); margin-top: 0.15rem; }
.demo__btn {
    padding: 0.4rem 0.75rem; border-radius: 0.5rem;
    background: #f59e0b; color: #0b0f1a;
    font-weight: 600; border: none; cursor: pointer;
    font-size: 0.85rem;
}
.demo__btn--ghost { background: transparent; color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); margin-top: 0.4rem; }
.demo__hint { color: rgba(255, 255, 255, 0.55); font-size: 0.8rem; margin: 0.25rem 0 0.5rem; }
.demo__codes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.5rem 0; }
.demo__codes code {
    font-family: 'JetBrains Mono', monospace;
    background: rgba(255, 255, 255, 0.06);
    padding: 0.25rem 0.5rem; border-radius: 0.35rem;
    font-size: 0.8rem; color: rgba(255, 255, 255, 0.85);
}
</style>
