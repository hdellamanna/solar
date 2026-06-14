<script setup>
/**
 * DemoMetasOrcamentos — interactive progress visualization for goals and
 * monthly budgets. Lets the user bump the slider and see the bar fill.
 */
import { ref, computed } from 'vue';

const goal = ref({
    name: 'Reserva de emergencia',
    target: 20000,
    current: 12500,
});
const monthly = ref({
    category: 'Alimentacao',
    limit: 1500,
    spent: 870,
});

const goalPct = computed(() => Math.min(100, Math.round((goal.value.current / goal.value.target) * 100)));
const monthlyPct = computed(() => Math.min(100, Math.round((monthly.value.spent / monthly.value.limit) * 100)));
const monthlyColor = computed(() => {
    if (monthlyPct.value >= 100) return '#ef4444';
    if (monthlyPct.value >= 80) return '#f59e0b';
    return '#10b981';
});

function bumpCurrent() { goal.value.current = Math.min(goal.value.target, goal.value.current + 500); }
function bumpSpent() { monthly.value.spent = Math.min(monthly.value.limit + 200, monthly.value.spent + 100); }
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">Meta: {{ goal.name }}</h4>
        <div class="demo__progress">
            <div class="demo__bar" :style="{ width: goalPct + '%' }"></div>
        </div>
        <div class="demo__meta">
            R$ {{ goal.current.toLocaleString('pt-BR') }} de R$ {{ goal.target.toLocaleString('pt-BR') }}
            <span class="demo__pct">{{ goalPct }}%</span>
        </div>
        <button class="demo__btn demo__btn--small" @click="bumpCurrent">+ R$ 500</button>

        <h4 class="demo__title">Orcamento mensal: {{ monthly.category }}</h4>
        <div class="demo__progress">
            <div class="demo__bar" :style="{ width: monthlyPct + '%', background: monthlyColor }"></div>
        </div>
        <div class="demo__meta">
            R$ {{ monthly.spent.toLocaleString('pt-BR') }} de R$ {{ monthly.limit.toLocaleString('pt-BR') }}
            <span class="demo__pct" :style="{ color: monthlyColor }">{{ monthlyPct }}%</span>
        </div>
        <button class="demo__btn demo__btn--small" @click="bumpSpent">+ R$ 100 gasto</button>

        <p class="demo__hint">
            Quando o orcamento passa de 80% o app manda um aviso discreto.
            Acima de 100% a barra fica vermelha.
        </p>
    </div>
</template>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__progress {
    width: 100%; height: 0.625rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    overflow: hidden;
}
.demo__bar {
    height: 100%;
    background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    border-radius: 999px;
    transition: width 400ms cubic-bezier(0.4, 0, 0.2, 1), background 200ms ease-out;
}
.demo__meta {
    display: flex; justify-content: space-between; align-items: center;
    margin: 0.5rem 0;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
}
.demo__pct { font-weight: 600; }
.demo__btn {
    padding: 0.4rem 0.75rem; border-radius: 0.5rem;
    background: rgba(245, 158, 11, 0.18); color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
    font-size: 0.8rem; cursor: pointer;
    font-weight: 500;
}
.demo__btn:hover { background: rgba(245, 158, 11, 0.28); }
.demo__hint { color: rgba(255, 255, 255, 0.55); font-size: 0.8rem; margin-top: 0.75rem; }
html[data-motion="reduced"] .demo__bar { transition: none !important; }
</style>
