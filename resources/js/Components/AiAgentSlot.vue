<script setup>
/**
 * AiAgentSlot — FASE 8 chrome placeholder.
 *
 * Floating bubble bottom-right that opens a glass panel. Cmd+K / Ctrl+K
 * opens from anywhere. Submit is a no-op for now (FASE 8 will wire to
 * real Groq + tool-use). The slot respects motion.spring (slide up on
 * mount) and is hidden when config('features.ai_agent') is false.
 */
import { ref, computed, nextTick, watch } from 'vue';
import { useAiAgent } from '@/composables/useAiAgent';
import { useMotionPreference } from '@/composables/useMotionPreference';
import { usePage } from '@inertiajs/vue3';

const { isOpen, open, close, toggle, registerInput, focusInput } = useAiAgent();
const { spring } = useMotionPreference();
const page = usePage();

const enabled = computed(() => page.props.appMeta?.features?.ai_agent === true);
const prompt = ref('');
const thinking = ref(false);
const examplePrompts = [
    'Adicionei R$ 50 de almoco hoje.',
    'Quanto gastei em delivery este mes?',
    'Estou no caminho de bater minha meta de emergencia?',
];

const messages = ref([
    { role: 'system', text: 'Ola! Estou em fase de testes. FASE 8 (em breve) me conectara ao Groq para responder perguntas e executar acoes nas suas financas.' },
]);

const inputEl = ref(null);
const panelEl = ref(null);
watch(inputEl, (el) => { if (el) registerInput(el); });
watch(isOpen, async (v) => {
    if (v) {
        await nextTick();
        focusInput();
    }
});

function selectExample(text) {
    prompt.value = text;
    focusInput();
}

function send() {
    if (!prompt.value.trim()) return;
    messages.value.push({ role: 'user', text: prompt.value });
    const userText = prompt.value;
    prompt.value = '';
    thinking.value = true;
    // FASE 8 stub: simulate response, then surface a "em breve" message.
    setTimeout(() => {
        messages.value.push({
            role: 'system',
            text: `Disponivel em breve (FASE 8). Voce disse: "${userText.slice(0, 80)}"`,
        });
        thinking.value = false;
    }, 900);
}

function onBubbleKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle();
    }
}

function onPanelKeydown(e) {
    if (e.key === 'Escape') close();
}
</script>

<template>
    <template v-if="enabled">
        <button
            v-if="!isOpen"
            type="button"
            class="ai-bubble"
            :class="{ 'ai-bubble--animated': spring }"
            aria-label="Abrir assistente IA (Cmd+K ou Ctrl+K)"
            @click="open"
            @keydown="onBubbleKeydown"
        >
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 2a3 3 0 0 0-3 3v1H7a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h2v2l3-2h5a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-2V5a3 3 0 0 0-3-3z"/>
                <circle cx="9" cy="12" r="1" fill="currentColor"/>
                <circle cx="15" cy="12" r="1" fill="currentColor"/>
            </svg>
        </button>

        <div
            v-else
            ref="panelEl"
            class="ai-panel"
            :class="{ 'ai-panel--animated': spring }"
            role="dialog"
            aria-modal="true"
            aria-label="Assistente IA"
            @keydown="onPanelKeydown"
        >
            <header class="ai-panel__header">
                <span class="ai-panel__title">Assistente IA</span>
                <button type="button" class="ai-panel__close" @click="close" aria-label="Fechar">&times;</button>
            </header>
            <div class="ai-panel__messages" role="log" aria-live="polite">
                <div
                    v-for="(m, i) in messages"
                    :key="i"
                    :class="['ai-msg', m.role === 'user' ? 'ai-msg--user' : 'ai-msg--system']"
                >
                    {{ m.text }}
                </div>
            </div>
            <div v-if="!messages.length || messages.length === 1" class="ai-panel__examples">
                <button
                    v-for="(p, i) in examplePrompts"
                    :key="i"
                    type="button"
                    class="ai-chip"
                    @click="selectExample(p)"
                >
                    {{ p }}
                </button>
            </div>
            <form class="ai-panel__form" @submit.prevent="send">
                <input
                    ref="inputEl"
                    :value="prompt"
                    @input="(e) => prompt = e.target.value"
                    class="ai-panel__input"
                    placeholder="Pergunte qualquer coisa sobre suas financas..."
                    :disabled="thinking"
                />
                <button type="submit" class="ai-panel__send" :disabled="!prompt.trim() || thinking">
                    <span v-if="!thinking">Enviar</span>
                    <span v-else class="ai-dots"><span>.</span><span>.</span><span>.</span></span>
                </button>
            </form>
            <footer class="ai-panel__footer">
                <span class="ai-panel__hint">
                    <kbd>Cmd</kbd>+<kbd>K</kbd> ou <kbd>Ctrl</kbd>+<kbd>K</kbd> para abrir
                </span>
            </footer>
        </div>
    </template>
</template>

<style scoped>
.ai-bubble {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    width: 3.25rem;
    height: 3.25rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    color: #0b0f1a;
    border: none;
    box-shadow: 0 12px 30px rgba(245, 158, 11, 0.32);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    z-index: 90;
    transition: transform 200ms ease-out, box-shadow 200ms ease-out;
}
.ai-bubble:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 16px 36px rgba(245, 158, 11, 0.45);
}
.ai-bubble--animated { animation: ai-bubble-in 480ms cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes ai-bubble-in {
    0% { opacity: 0; transform: translateY(20px) scale(0.6); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.ai-panel {
    position: fixed;
    right: 1.5rem;
    bottom: 1.5rem;
    width: min(22rem, calc(100vw - 3rem));
    height: min(32rem, calc(100vh - 6rem));
    border-radius: 1.25rem;
    background: rgba(17, 24, 42, 0.92);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
    color: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    z-index: 90;
    overflow: hidden;
}
.ai-panel--animated { animation: ai-panel-in 320ms cubic-bezier(0.34, 1.56, 0.64, 1) both; }
@keyframes ai-panel-in {
    0% { opacity: 0; transform: translateY(20px) scale(0.95); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.ai-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(245, 158, 11, 0.08);
}
.ai-panel__title { font-weight: 600; font-size: 0.95rem; }
.ai-panel__close {
    background: transparent; border: none; color: rgba(255, 255, 255, 0.7);
    font-size: 1.5rem; line-height: 1; cursor: pointer; padding: 0; width: 1.5rem; height: 1.5rem;
    border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
}
.ai-panel__close:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }

.ai-panel__messages {
    flex: 1; overflow-y: auto; padding: 1rem;
    display: flex; flex-direction: column; gap: 0.5rem;
}
.ai-msg {
    max-width: 85%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.875rem;
    font-size: 0.875rem;
    line-height: 1.45;
}
.ai-msg--system {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.85);
    align-self: flex-start;
    border-bottom-left-radius: 0.25rem;
}
.ai-msg--user {
    background: #f59e0b;
    color: #0b0f1a;
    align-self: flex-end;
    border-bottom-right-radius: 0.25rem;
    font-weight: 500;
}

.ai-panel__examples {
    padding: 0 1rem 0.75rem;
    display: flex; flex-wrap: wrap; gap: 0.4rem;
}
.ai-chip {
    padding: 0.3rem 0.6rem;
    border-radius: 999px;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.25);
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.75rem;
    cursor: pointer;
    transition: background 160ms ease-out;
}
.ai-chip:hover { background: rgba(245, 158, 11, 0.22); }

.ai-panel__form {
    display: flex; gap: 0.5rem; padding: 0.75rem 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.ai-panel__input {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.875rem;
}
.ai-panel__input:focus { outline: none; border-color: #f59e0b; }
.ai-panel__input::placeholder { color: rgba(255, 255, 255, 0.45); }
.ai-panel__send {
    padding: 0.5rem 0.875rem;
    border-radius: 0.5rem;
    background: #f59e0b;
    color: #0b0f1a;
    font-weight: 600;
    border: none;
    cursor: pointer;
    font-size: 0.85rem;
    min-width: 4.5rem;
}
.ai-panel__send:disabled { opacity: 0.5; cursor: not-allowed; }
.ai-dots span { animation: ai-dots 1.2s infinite; opacity: 0.3; }
.ai-dots span:nth-child(2) { animation-delay: 0.2s; }
.ai-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes ai-dots { 0%, 80%, 100% { opacity: 0.3; } 40% { opacity: 1; } }

.ai-panel__footer {
    padding: 0.4rem 1rem 0.75rem;
    text-align: center;
}
.ai-panel__hint { font-size: 0.7rem; color: rgba(255, 255, 255, 0.45); }
.ai-panel__hint kbd {
    display: inline-block;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0.1rem;
}

html[data-motion="reduced"] .ai-bubble,
html[data-motion="reduced"] .ai-panel,
html[data-motion="reduced"] .ai-chip,
html[data-motion="reduced"] .ai-panel__send,
html[data-motion="reduced"] .ai-bubble:hover {
    transition: none !important;
    animation: none !important;
    transform: none !important;
}
</style>
