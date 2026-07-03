<script setup>
/**
 * Settings/Appearance — the Motion preferences page.
 * 3 radio cards (Sistema / Reduzido / Completo) + 3 granular toggles +
 * live preview card showing animated/stripped demos of all 3 effect
 * categories. Submit via useForm and toast.
 */
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useMotionPreference } from '@/Composables/useMotionPreference';

const props = defineProps({
    user: { type: Object, required: true },
});

const { applyToDocument } = useMotionPreference();

const form = useForm({
    motion_preference: props.user.motion_preference ?? 'auto',
    motion_backdrop: props.user.motion_backdrop ?? true,
    motion_spring: props.user.motion_spring ?? true,
    motion_parallax: props.user.motion_parallax ?? true,
});

const showCustomize = computed(() => true);

function save() {
    form.patch(route('settings.appearance.update'), {
        onSuccess: () => {
            // Re-apply attributes locally so the page reflects immediately
            // before the next navigation.
            applyToDocument();
        },
    });
}
</script>

<template>
    <Head title="Aparencia - Solar Money" />
    <AppLayout>
        <template #header>
            <h1 class="text-xl font-semibold">Aparencia</h1>
        </template>

        <div class="appearance">
            <section class="appearance__card">
                <h2 class="appearance__h2">Preferencia de animacao</h2>
                <p class="appearance__lede">
                    O Solar respeita a preferencia do seu sistema operacional.
                    Se quiser, voce pode forcar um modo ou personalizar
                    categoria por categoria.
                </p>

                <div class="appearance__cards">
                    <label :class="['appearance__radio', { 'appearance__radio--active': form.motion_preference === 'auto' }]">
                        <input type="radio" v-model="form.motion_preference" value="auto" />
                        <span class="appearance__radio-label">Sistema</span>
                        <span class="appearance__radio-sub">Seguindo a preferencia do seu sistema operacional.</span>
                    </label>
                    <label :class="['appearance__radio', { 'appearance__radio--active': form.motion_preference === 'reduced' }]">
                        <input type="radio" v-model="form.motion_preference" value="reduced" />
                        <span class="appearance__radio-label">Reduzido</span>
                        <span class="appearance__radio-sub">Desliga todas as animacoes. Ideal para baixo brilho ou cansaco visual.</span>
                    </label>
                    <label :class="['appearance__radio', { 'appearance__radio--active': form.motion_preference === 'full' }]">
                        <input type="radio" v-model="form.motion_preference" value="full" />
                        <span class="appearance__radio-label">Completo</span>
                        <span class="appearance__radio-sub">Mantem todas as animacoes, ignorando a preferencia do SO.</span>
                    </label>
                </div>
            </section>

            <section v-if="showCustomize" class="appearance__card">
                <h2 class="appearance__h2">Personalizar</h2>
                <div class="appearance__toggles">
                    <label class="appearance__toggle">
                        <input type="checkbox" v-model="form.motion_backdrop" />
                        <span class="appearance__toggle-label">Plano de fundo animado</span>
                        <span class="appearance__toggle-sub">Mesh canvas e glass sweep.</span>
                    </label>
                    <label class="appearance__toggle">
                        <input type="checkbox" v-model="form.motion_spring" />
                        <span class="appearance__toggle-label">Micro-interacoes</span>
                        <span class="appearance__toggle-sub">Transicoes de pagina, sheets, hold-to-confirm.</span>
                    </label>
                    <label class="appearance__toggle">
                        <input type="checkbox" v-model="form.motion_parallax" />
                        <span class="appearance__toggle-label">Parallax</span>
                        <span class="appearance__toggle-sub">Sun parallax do dashboard.</span>
                    </label>
                </div>
            </section>

            <section class="appearance__card">
                <h2 class="appearance__h2">Preview ao vivo</h2>
                <div class="appearance__preview">
                    <div :class="['appearance__demo appearance__demo--backdrop', { 'is-off': !form.motion_backdrop }]">
                        <div class="appearance__demo-strip"></div>
                        <div class="appearance__demo-strip appearance__demo-strip--alt"></div>
                    </div>
                    <div :class="['appearance__demo appearance__demo--spring', { 'is-off': !form.motion_spring }]">
                        <button class="appearance__demo-btn">Botao com spring</button>
                    </div>
                    <div :class="['appearance__demo appearance__demo--parallax', { 'is-off': !form.motion_parallax }]">
                        <div class="appearance__demo-number">R$ 1.234,56</div>
                    </div>
                </div>
            </section>

            <div class="appearance__actions">
                <button class="appearance__save" :disabled="form.processing" @click="save">
                    Salvar preferencias
                </button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.appearance { max-width: 48rem; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }
.appearance__card {
    padding: 1.5rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.appearance__h2 { font-size: 1.125rem; font-weight: 600; margin: 0 0 0.5rem; }
.appearance__lede { color: rgba(255, 255, 255, 0.7); font-size: 0.875rem; line-height: 1.5; margin: 0 0 1rem; }
.appearance__cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 0.75rem; }
.appearance__radio {
    display: flex; flex-direction: column; gap: 0.25rem;
    padding: 1rem; border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: border-color 160ms ease-out, background 160ms ease-out;
}
.appearance__radio--active { border-color: #f59e0b; background: rgba(245, 158, 11, 0.08); }
.appearance__radio input { display: none; }
.appearance__radio-label { font-weight: 600; font-size: 0.95rem; }
.appearance__radio-sub { font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); }
.appearance__toggles { display: flex; flex-direction: column; gap: 0.5rem; }
.appearance__toggle {
    display: grid; grid-template-columns: auto 1fr; column-gap: 0.75rem;
    padding: 0.75rem; border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
}
.appearance__toggle input { width: 1.1rem; height: 1.1rem; accent-color: #f59e0b; }
.appearance__toggle-label { font-weight: 600; font-size: 0.95rem; }
.appearance__toggle-sub { display: block; font-size: 0.75rem; color: rgba(255, 255, 255, 0.6); }
.appearance__preview { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 0.75rem; margin-top: 0.5rem; }
.appearance__demo {
    padding: 1rem; border-radius: 0.75rem;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    text-align: center;
    position: relative;
    overflow: hidden;
}
.appearance__demo.is-off { opacity: 0.4; }
.appearance__demo-strip {
    height: 0.5rem; border-radius: 999px;
    background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    margin: 0.5rem 0;
    animation: strip-drift 4s ease-in-out infinite;
}
.appearance__demo-strip--alt { animation-duration: 6s; animation-direction: reverse; }
@keyframes strip-drift {
    0%, 100% { transform: translateX(-15%); }
    50% { transform: translateX(15%); }
}
.appearance__demo-btn {
    padding: 0.5rem 1rem; border-radius: 0.5rem;
    background: #f59e0b; color: #0b0f1a; font-weight: 600; border: none;
    cursor: pointer; transition: transform 200ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.appearance__demo-btn:hover { transform: translateY(-2px) scale(1.04); }
.appearance__demo-number {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.25rem; font-weight: 600; color: #f59e0b;
    animation: number-count 2s ease-in-out infinite;
}
@keyframes number-count {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.04); }
}
.appearance__actions { display: flex; justify-content: flex-end; }
.appearance__save {
    padding: 0.625rem 1.25rem; border-radius: 0.75rem;
    background: #f59e0b; color: #0b0f1a; font-weight: 600;
    border: none; cursor: pointer; font-size: 0.95rem;
}
.appearance__save:disabled { opacity: 0.5; cursor: not-allowed; }
html[data-motion="reduced"] .appearance__demo-strip,
html[data-motion="reduced"] .appearance__demo-number,
html[data-motion="reduced"] .appearance__demo-btn,
html[data-motion="reduced"] .appearance__radio,
html[data-motion="reduced"] .appearance__save {
    animation: none !important;
    transition: none !important;
}
</style>
