<script setup>
/*
 * Solar Money — First-boot setup wizard.
 *
 * Reachable when app_meta.setup_completed_at is null. Renders the
 * current state of every known env var with a per-row editor for the
 * non-secret ones. Secrets are masked and not editable here — the
 * operator sets them in the Render dashboard directly.
 *
 * Two outcomes:
 *   - Click "Salvar e validar" → POST /setup → migrate + seed + health
 *     check → redirect to /login on success.
 *   - Click "Pular por agora" → POST /setup/skip → marks complete +
 *     redirect with a flash explaining that env vars must be set
 *     manually in the Render dashboard.
 */

import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    env_vars: { type: Array, required: true },
    required_set: { type: Boolean, default: false },
    setup_completed: { type: Boolean, default: false },
});

const form = useForm({});

// Group vars by section for the accordion
const sections = computed(() => {
    const app    = [];
    const db     = [];
    const mail   = [];
    const cache  = [];
    const oauth  = [];
    const other  = [];

    for (const v of props.env_vars) {
        if (v.key.startsWith('APP_') || v.key === 'LOG_') {
            app.push(v);
        } else if (v.key.startsWith('DB_') || v.key === 'DATABASE_URL') {
            db.push(v);
        } else if (v.key.startsWith('MAIL_') || v.key === 'RESEND_API_KEY') {
            mail.push(v);
        } else if (v.key === 'CACHE_STORE' || v.key === 'SESSION_DRIVER' || v.key === 'QUEUE_CONNECTION' || v.key === 'FILESYSTEM_DISK') {
            cache.push(v);
        } else if (v.key.includes('CLIENT_ID') || v.key.includes('CLIENT_SECRET')) {
            oauth.push(v);
        } else {
            other.push(v);
        }
    }

    return [
        { key: 'app',    label: 'App',           vars: app },
        { key: 'db',     label: 'Banco de dados', vars: db },
        { key: 'mail',   label: 'Email',         vars: mail },
        { key: 'cache',  label: 'Cache / Sessão / Fila', vars: cache },
        { key: 'oauth',  label: 'Login social (FASE 8)', vars: oauth },
        { key: 'other',  label: 'Outros',        vars: other },
    ].filter(s => s.vars.length > 0);
});

const submit = () => {
    form.post(route('setup.store'));
};

const skip = () => {
    router.post(route('setup.skip'));
};

const requiredCount = computed(() => props.env_vars.filter(v => v.required).length);
const setRequiredCount = computed(() => props.env_vars.filter(v => v.required && v.is_set).length);
</script>

<template>
    <Head title="Solar Money · Setup" />

    <GuestLayout>
        <div class="setup-wizard">
            <div class="setup-header">
                <h1 class="font-display text-display-md tracking-tight">
                    Bem-vindo ao Solar Money ☀️
                </h1>
                <p class="setup-subtitle">
                    Este é o primeiro boot. Verificamos abaixo tudo que precisa estar configurado.
                    Os secrets (chaves de API) você ajusta no painel do seu provedor de hospedagem — aqui só lemos o estado atual.
                </p>
                <div v-if="setup_completed" class="setup-banner setup-banner--ok">
                    ✓ Setup já foi concluído em uma data anterior. Você pode re-validar para garantir que tudo continua OK.
                </div>
                <div v-else class="setup-banner setup-banner--warn">
                    ⚠ <strong>{{ setRequiredCount }}/{{ requiredCount }}</strong> env vars obrigatórias configuradas.
                </div>
            </div>

            <div class="setup-sections">
                <details
                    v-for="section in sections"
                    :key="section.key"
                    class="setup-section"
                    :open="section.key === 'app' || section.key === 'db' || section.key === 'mail'"
                >
                    <summary class="setup-section__summary">
                        <span class="setup-section__title">{{ section.label }}</span>
                        <span class="setup-section__count">
                            {{ section.vars.filter(v => v.is_set).length }}/{{ section.vars.length }}
                        </span>
                    </summary>

                    <div class="setup-section__body">
                        <div
                            v-for="v in section.vars"
                            :key="v.key"
                            class="setup-var"
                            :class="{ 'setup-var--missing': !v.is_set, 'setup-var--required': v.required }"
                        >
                            <div class="setup-var__head">
                                <code class="setup-var__key">{{ v.label }}</code>
                                <span v-if="v.required" class="setup-var__badge setup-var__badge--required">obrigatório</span>
                                <span v-else class="setup-var__badge">opcional</span>
                                <span v-if="v.is_set" class="setup-var__badge setup-var__badge--ok">✓ configurado</span>
                                <span v-else class="setup-var__badge setup-var__badge--missing">✗ faltando</span>
                            </div>
                            <p class="setup-var__description">{{ v.description }}</p>
                            <div v-if="v.is_set" class="setup-var__value">
                                <span class="setup-var__value-label">Valor atual:</span>
                                <code>{{ v.current_value }}</code>
                            </div>
                            <div v-else class="setup-var__hint">
                                Defina <code>{{ v.key }}</code> no painel do seu host (Render / Railway / VPS) antes de continuar.
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="setup-actions">
                <button
                    type="button"
                    class="setup-btn setup-btn--primary"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <span v-if="form.processing">Validando...</span>
                    <span v-else>Salvar e validar (migrate + seed + health)</span>
                </button>

                <button
                    type="button"
                    class="setup-btn setup-btn--ghost"
                    :disabled="form.processing"
                    @click="skip"
                >
                    Pular por agora
                </button>
            </div>

            <details v-if="form.errors && Object.keys(form.errors).length" class="setup-errors">
                <summary>Erros do formulário</summary>
                <pre>{{ JSON.stringify(form.errors, null, 2) }}</pre>
            </details>

            <p class="setup-footer">
                As configurações ficam salvas em <code>app_meta.setup_completed_at</code>. Para re-rodar o wizard depois,
                execute <code>DELETE FROM app_meta WHERE key = 'setup_completed_at'</code>.
            </p>
        </div>
    </GuestLayout>
</template>

<style scoped>
.setup-wizard {
    max-width: 56rem;
    margin: 2rem auto;
    padding: 0 1rem;
}
.setup-header { margin-bottom: 2rem; }
.setup-subtitle {
    color: rgb(var(--ink-500, 107 114 128));
    margin-top: 0.5rem;
    line-height: 1.6;
}
.setup-banner {
    margin-top: 1.5rem;
    padding: 1rem 1.25rem;
    border-radius: 1rem;
    font-size: 0.875rem;
}
.setup-banner--ok   { background: rgb(220 252 231); color: rgb(22 101 52); }
.setup-banner--warn { background: rgb(254 249 195); color: rgb(133 77 14); }

.setup-sections { display: flex; flex-direction: column; gap: 0.75rem; }
.setup-section {
    background: rgb(var(--ink-0, 255 255 255));
    border: 1px solid rgb(var(--ink-200, 229 231 235));
    border-radius: 1rem;
    overflow: hidden;
}
.setup-section__summary {
    padding: 1rem 1.25rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    background: rgb(var(--ink-50, 249 250 251));
}
.setup-section__title { font-size: 1rem; }
.setup-section__count {
    font-size: 0.875rem;
    color: rgb(var(--ink-500, 107 114 128));
    font-weight: 400;
}
.setup-section__body {
    padding: 1rem 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.setup-var {
    padding: 1rem;
    border-radius: 0.75rem;
    background: rgb(var(--ink-50, 249 250 251));
}
.setup-var--missing { background: rgb(254 242 242); }
.setup-var__head {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 0.5rem;
}
.setup-var__key { font-size: 0.875rem; font-weight: 600; }
.setup-var__badge {
    font-size: 0.75rem;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    background: rgb(var(--ink-200, 229 231 235));
    color: rgb(var(--ink-700, 55 65 81));
}
.setup-var__badge--required { background: rgb(254 226 226); color: rgb(153 27 27); }
.setup-var__badge--ok        { background: rgb(220 252 231); color: rgb(22 101 52); }
.setup-var__badge--missing   { background: rgb(254 226 226); color: rgb(153 27 27); }
.setup-var__description {
    font-size: 0.875rem;
    color: rgb(var(--ink-600, 75 85 99));
    line-height: 1.5;
    margin: 0.25rem 0;
}
.setup-var__value {
    font-size: 0.8125rem;
    color: rgb(var(--ink-700, 55 65 81));
    margin-top: 0.5rem;
}
.setup-var__value code {
    background: rgb(var(--ink-100, 243 244 246));
    padding: 0.125rem 0.5rem;
    border-radius: 0.375rem;
    font-family: ui-monospace, SFMono-Regular, monospace;
}
.setup-var__hint {
    font-size: 0.8125rem;
    color: rgb(153 27 27);
    margin-top: 0.5rem;
}

.setup-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 2rem;
    justify-content: flex-end;
}
.setup-btn {
    padding: 0.75rem 1.25rem;
    border-radius: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border: 0;
    font-size: 0.875rem;
    transition: transform 120ms ease-out;
}
.setup-btn:active { transform: scale(0.98); }
.setup-btn--primary {
    background: rgb(var(--primary-500, 245 158 11));
    color: white;
}
.setup-btn--primary:disabled { opacity: 0.6; cursor: not-allowed; }
.setup-btn--ghost {
    background: transparent;
    color: rgb(var(--ink-600, 75 85 99));
}

.setup-errors {
    margin-top: 1.5rem;
    padding: 1rem;
    border-radius: 0.75rem;
    background: rgb(254 242 242);
}
.setup-errors pre {
    margin: 0.5rem 0 0;
    font-size: 0.75rem;
    color: rgb(153 27 27);
}
.setup-footer {
    margin-top: 2rem;
    font-size: 0.8125rem;
    color: rgb(var(--ink-500, 107 114 128));
    text-align: center;
}
</style>