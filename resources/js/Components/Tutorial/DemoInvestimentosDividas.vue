<script setup>
/**
 * DemoInvestimentosDividas — combined view: investment positions and
 * debt amortization. The bars animate as the user adjusts slider.
 */
import { ref, computed } from 'vue';

const positions = ref([
    { id: 1, ticker: 'TESOURO SELIC 2029', qty: 1, avg: 130.45, current: 142.18 },
    { id: 2, ticker: 'IVVB11', qty: 12, avg: 285.00, current: 312.40 },
    { id: 3, ticker: 'HGLG11', qty: 50, avg: 168.20, current: 174.10 },
]);

const debts = ref([
    { id: 1, name: 'Financiamento imovel', total: 320000, paid: 84500, monthly: 2450 },
    { id: 2, name: 'Cartao de credito', total: 1820, paid: 0, monthly: 200 },
]);

function pct(paid, total) { return Math.min(100, Math.round((paid / total) * 100)); }

const totalGain = computed(() => positions.value.reduce((sum, p) => sum + (p.current - p.avg) * p.qty, 0));
</script>

<template>
    <div class="demo">
        <h4 class="demo__title">Posicoes</h4>
        <ul class="demo__list">
            <li v-for="p in positions" :key="p.id" class="demo__position">
                <div class="demo__row1">
                    <span class="demo__ticker">{{ p.ticker }}</span>
                    <span :class="['demo__gain', current_above(p) ? 'demo__gain--pos' : 'demo__gain--neg']">
                        {{ current_above(p) ? '+' : '' }}R$ {{ ((p.current - p.avg) * p.qty).toFixed(2) }}
                    </span>
                </div>
                <div class="demo__sub">
                    {{ p.qty }}x &middot; PM R$ {{ p.avg.toFixed(2) }} &middot; Atual R$ {{ p.current.toFixed(2) }}
                </div>
            </li>
        </ul>
        <div class="demo__summary">
            Resultado nao realizado:
            <strong :class="totalGain >= 0 ? 'demo__gain--pos' : 'demo__gain--neg'">
                R$ {{ totalGain.toFixed(2) }}
            </strong>
        </div>

        <h4 class="demo__title">Dividas</h4>
        <ul class="demo__list">
            <li v-for="d in debts" :key="d.id" class="demo__debt">
                <div class="demo__row1">
                    <span class="demo__name">{{ d.name }}</span>
                    <span class="demo__sub">Parcela R$ {{ d.monthly.toFixed(2) }}</span>
                </div>
                <div class="demo__barwrap">
                    <div class="demo__bar" :style="{ width: pct(d.paid, d.total) + '%' }"></div>
                </div>
                <div class="demo__sub">
                    {{ pct(d.paid, d.total) }}% quitado &middot; R$ {{ d.paid.toLocaleString('pt-BR') }} de R$ {{ d.total.toLocaleString('pt-BR') }}
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
function current_above(p) { return p.current >= p.avg; }
</script>

<style scoped>
.demo { font-size: 0.875rem; color: rgba(255, 255, 255, 0.85); }
.demo__title { font-size: 0.95rem; font-weight: 600; margin: 0.75rem 0 0.5rem; }
.demo__list { list-style: none; padding: 0; margin: 0.5rem 0; }
.demo__position, .demo__debt {
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.04);
    margin-bottom: 0.4rem;
}
.demo__row1 { display: flex; justify-content: space-between; align-items: center; }
.demo__ticker { font-weight: 600; }
.demo__name { font-weight: 600; }
.demo__sub { font-size: 0.75rem; color: rgba(255, 255, 255, 0.55); margin-top: 0.15rem; }
.demo__gain { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
.demo__gain--pos { color: #10b981; }
.demo__gain--neg { color: #ef4444; }
.demo__summary {
    margin-top: 0.75rem; padding: 0.5rem 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 0.5rem; font-size: 0.85rem;
}
.demo__barwrap {
    margin: 0.4rem 0 0.25rem; height: 0.4rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px; overflow: hidden;
}
.demo__bar { height: 100%; background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); border-radius: 999px; transition: width 400ms cubic-bezier(0.4, 0, 0.2, 1); }
html[data-motion="reduced"] .demo__bar { transition: none !important; }
</style>
