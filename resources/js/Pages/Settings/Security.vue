<script setup>
/*
 * Settings — Security (FASE 4D / Auth Phase 3 + FASE Polish / v0.10.0).
 *
 * The single home for 2FA + trusted-device management. Three sections:
 *
 *  1. 2FA status — status pill + Enable / Disable buttons (the latter
 *     gated behind a password re-prompt that opens an inline confirm
 *     panel). FASE Polish adds a green "Ativado" / amber "Desativado"
 *     pill, a "Última verificação: há X minutos" line when active, and
 *     a recovery-code count "X de 10 códigos restantes" (with a
 *     placeholder for a "Regenerar códigos" action that ships in
 *     v0.11+).
 *  2. Recovery codes hint — a one-liner pointing at how to see them
 *     (they are only shown at enrollment, so this is mostly an
 *     informational row when 2FA is on).
 *  3. Trusted devices — per-row "Revoke" + a "Revoke all" button. The
 *     list is supplied by the controller (passed in as `trustedDevices`
 *     prop) so we do not N+1 from the front-end.
 *
 * The user is bounced to `two-factor.enable.confirm/{token}` and
 * `two-factor.disable.confirm/{token}` via signed email links — the
 * POSTs here only mint the tokens. The actual confirmation lives in
 * dedicated Vue pages (TwoFactorEnableConfirm / TwoFactorDisableConfirm).
 *
 * Layout: AuthenticatedLayout (same family as Profile/Edit and
 * Subscriptions/Index). The whole page is `max-w-3xl` to keep the
 * form columns readable on ultrawide.
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    twoFactorEnabled: { type: Boolean, required: true },
    enabledAt: { type: String, default: null },
    lastVerifiedAt: { type: String, default: null },
    recoveryCodesRemaining: { type: Number, default: 0 },
    recoveryCodesTotal: { type: Number, default: 10 },
    trustedDevices: { type: Array, default: () => [] },
    // True when the user has clicked "Desativar 2FA" but hasn't yet
    // clicked the email link — drives a red "Desativando..." pill.
    disablePending: { type: Boolean, default: false },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);

// Relative-time formatter (pt-BR) — e.g. "há 2 minutos" / "há 3 dias".
// Re-uses Intl.RelativeTimeFormat so the value tracks the user's locale
// without us hard-coding every plural form.
const relativeTime = (iso) => {
    if (! iso) return null;
    const then = new Date(iso).getTime();
    if (! Number.isFinite(then)) return null;
    const diffSec = Math.round((Date.now() - then) / 1000);
    const absSec = Math.abs(diffSec);
    const fmt = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
    if (absSec < 60) return fmt.format(-diffSec, 'second');
    if (absSec < 3600) return fmt.format(-Math.round(diffSec / 60), 'minute');
    if (absSec < 86400) return fmt.format(-Math.round(diffSec / 3600), 'hour');
    if (absSec < 86400 * 30) return fmt.format(-Math.round(diffSec / 86400), 'day');
    if (absSec < 86400 * 365) return fmt.format(-Math.round(diffSec / (86400 * 30)), 'month');
    return fmt.format(-Math.round(diffSec / (86400 * 365)), 'year');
};
const lastVerifiedLabel = computed(() => {
    const label = relativeTime(props.lastVerifiedAt);
    return label ? `Última verificação: ${label}` : null;
});

// ─── 2FA Enable ──────────────────────────────────────────────────────
// POST /two-factor/enable/begin. The backend mints a one-time email
// link to the confirm page; the user clicks the link, scans the QR,
// and POSTs the 6-digit code. We just bounce back to the same page
// so the success flash ("Enviamos um link de confirmacao...") is
// visible at the top of the section.
const enableForm = useForm({});
const submittingEnable = ref(false);
const submitEnable = () => {
    if (submittingEnable.value || enableForm.processing) return;
    submittingEnable.value = true;
    enableForm.post(route('two-factor.enable.begin'), {
        preserveScroll: true,
        onFinish: () => { submittingEnable.value = false; },
    });
};

// ─── 2FA Disable (with inline password re-prompt) ────────────────────
const showDisablePanel = ref(false);
const disableForm = useForm({ password: '' });
const disableError = computed(() => disableForm.errors?.password ?? null);
const submitDisable = () => {
    disableForm.post(route('two-factor.disable.begin'), {
        preserveScroll: true,
        onSuccess: () => {
            showDisablePanel.value = false;
            disableForm.reset('password');
        },
    });
};
const cancelDisable = () => {
    showDisablePanel.value = false;
    disableForm.reset('password');
    disableForm.clearErrors();
};

// ─── Trusted device revocation ───────────────────────────────────────
const revokeForm = useForm({});
const revokeAllForm = useForm({});
const revokingId = ref(null);
const revokeDevice = (id) => {
    if (revokingId.value !== null) return;
    revokingId.value = id;
    revokeForm.delete(route('trusted-devices.destroy', id), {
        preserveScroll: true,
        onFinish: () => { revokingId.value = null; },
    });
};
const submitRevokeAll = () => {
    if (props.trustedDevices.length === 0) return;
    if (! window.confirm('Remover todos os dispositivos confiaveis? Voce precisara confirmar o codigo 2FA no proximo login em cada um deles.')) {
        return;
    }
    revokeAllForm.delete(route('trusted-devices.destroy-all'), { preserveScroll: true });
};

// Friendly "MacBook do Henrique · 192.168.0.4" or fallback to UA / IP.
const friendlyName = (device) => {
    if (device.friendly_name) return device.friendly_name;
    const ua = device.user_agent || '';
    if (/iPhone/i.test(ua)) return 'iPhone';
    if (/iPad/i.test(ua)) return 'iPad';
    if (/Android/i.test(ua)) return 'Android';
    if (/Mac OS X/i.test(ua)) return 'Mac';
    if (/Windows/i.test(ua)) return 'Windows';
    if (/Linux/i.test(ua)) return 'Linux';
    return 'Dispositivo';
};

const formatDateTime = (iso) => {
    if (! iso) return '—';
    try {
        return new Date(iso).toLocaleString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch (e) {
        return iso;
    }
};
</script>

<template>
    <Head title="Seguranca · Solar Money" />
    <AuthenticatedLayout title="Seguranca">
        <div class="max-w-3xl space-y-6">
            <!-- Header -->
            <div>
                <h1 class="font-display text-2xl font-bold tracking-tight">Seguranca</h1>
                <p class="text-sm text-ink-500 dark:text-ink-400 mt-1">
                    Gerencie a verificacao em duas etapas e os dispositivos confiaveis.
                </p>
            </div>

            <!-- Flash messages -->
            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashSuccess"
                     class="p-3.5 rounded-2xl card-glass
                            bg-emerald-50/70 dark:bg-emerald-500/10
                            border border-emerald-200/70 dark:border-emerald-500/30
                            text-sm text-emerald-700 dark:text-emerald-300 flex items-start gap-2.5"
                     role="status">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ flashSuccess }}</span>
                </div>
            </Transition>

            <Transition
                enter-active-class="transition duration-300 ease-spring"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="flashError"
                     class="p-3.5 rounded-2xl card-glass
                            bg-rose-50/70 dark:bg-rose-500/10
                            border border-rose-200/70 dark:border-rose-500/30
                            text-sm text-rose-700 dark:text-rose-300 flex items-start gap-2.5"
                     role="alert">
                    <svg class="w-4 h-4 mt-0.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <span>{{ flashError }}</span>
                </div>
            </Transition>

            <!-- ─── 2FA status card ───────────────────────────────────── -->
            <section class="card p-6 space-y-4">
                <div class="flex items-start gap-4">
                    <!-- Shield icon -->
                    <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0
                                bg-gradient-to-br from-primary-500 to-primary-700 text-white shadow-soft">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z" />
                            <path d="M9 12l2 2 4-4" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="font-display text-lg font-semibold">
                                Verificacao em duas etapas
                            </h2>
                            <!--
                                FASE Polish / v0.10.0 — unified status
                                pill. 3 visual variants (.status-pill--ok
                                / --warn / --err) for the three states
                                a 2FA status can be in. The `err`
                                variant is the "Desativando..." state
                                when the user has clicked the email
                                link but the disable is still mid-flight.
                            -->
                            <span
                                v-if="twoFactorEnabled"
                                class="status-pill status-pill--ok"
                                role="status"
                                aria-label="Verificacao em duas etapas ativa"
                            >
                                <span class="status-pill__dot" aria-hidden="true"></span>
                                Ativada
                            </span>
                            <span
                                v-else-if="disablePending"
                                class="status-pill status-pill--err"
                                role="status"
                                aria-label="Desativacao em andamento"
                            >
                                <span class="status-pill__dot animate-pulse" aria-hidden="true"></span>
                                Desativando...
                            </span>
                            <span
                                v-else
                                class="status-pill status-pill--warn"
                                role="status"
                                aria-label="Verificacao em duas etapas desativada"
                            >
                                <span class="status-pill__dot" aria-hidden="true"></span>
                                Desativada
                            </span>
                        </div>

                        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 leading-relaxed">
                            <template v-if="twoFactorEnabled">
                                Ativada em <strong>{{ formatDateTime(enabledAt) }}</strong>.
                                <span v-if="lastVerifiedLabel" class="block">
                                    {{ lastVerifiedLabel }}.
                                </span>
                                Para confirmar o codigo, use seu app autenticador
                                (Google Authenticator, 1Password, Authy).
                            </template>
                            <template v-else>
                                Adicione uma camada extra de seguranca. Ao ativar,
                                voce precisara informar um codigo de 6 digitos
                                gerado pelo seu app autenticador a cada login.
                            </template>
                        </p>
                    </div>
                </div>

                <!-- Enable: simple POST that emails a one-time link. -->
                <form v-if="! twoFactorEnabled" @submit.prevent="submitEnable" class="pt-1">
                    <button
                        type="submit"
                        class="btn-primary"
                        :disabled="submittingEnable || enableForm.processing"
                    >
                        <span v-if="enableForm.processing" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                            </svg>
                            Enviando...
                        </span>
                        <span v-else class="flex items-center gap-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M12 2l8 4v6c0 5-3.5 9.4-8 10-4.5-.6-8-5-8-10V6l8-4z" />
                            </svg>
                            Ativar 2FA
                        </span>
                    </button>
                </form>

                <!-- Disable: collapsible password prompt. -->
                <div v-else class="pt-1 space-y-3">
                    <button
                        v-if="! showDisablePanel"
                        type="button"
                        @click="showDisablePanel = true"
                        class="btn-ghost text-rose-600 dark:text-rose-400 border border-rose-200/70 dark:border-rose-500/30"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M18.36 6.64a9 9 0 11-12.73 0M12 2v10" />
                        </svg>
                        Desativar 2FA
                    </button>

                    <div v-else
                         class="p-4 rounded-2xl card-glass
                                bg-rose-50/60 dark:bg-rose-500/5
                                border border-rose-200/70 dark:border-rose-500/30 space-y-3">
                        <p class="text-sm font-semibold text-rose-700 dark:text-rose-300">
                            Confirme sua senha para desativar a verificacao em duas etapas.
                        </p>
                        <p class="text-xs text-ink-500 dark:text-ink-400">
                            Enviaremos um link de confirmacao para o seu email. Apos
                            clicar, a 2FA sera desativada, todos os codigos de
                            recuperacao serao apagados e os dispositivos confiaveis
                            serao removidos.
                        </p>
                        <form @submit.prevent="submitDisable" class="space-y-3">
                            <div>
                                <label for="disable-password" class="block text-xs font-semibold mb-1.5">
                                    Sua senha
                                </label>
                                <input
                                    id="disable-password"
                                    v-model="disableForm.password"
                                    type="password"
                                    class="input-glass"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required
                                    autofocus
                                >
                                <p v-if="disableError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">
                                    {{ disableError }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="submit"
                                    class="btn-primary !bg-rose-600 hover:!bg-rose-700 focus:!ring-rose-500/40"
                                    :disabled="disableForm.processing"
                                >
                                    <span v-if="disableForm.processing" class="flex items-center gap-2">
                                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
                                        </svg>
                                        Enviando...
                                    </span>
                                    <span v-else>Enviar link de desativacao</span>
                                </button>
                                <button
                                    type="button"
                                    @click="cancelDisable"
                                    class="btn-ghost text-sm"
                                    :disabled="disableForm.processing"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- ─── Recovery codes (FASE Polish / v0.10.0) ──────────────
                 Only shown when 2FA is on. Lists how many of the
                 original 10 codes are still unconsumed and offers
                 a "Regenerar códigos" placeholder (the actual
                 endpoint ships in v0.11+; for v0.10.0 we just
                 show the button so the UI surface is locked in).
            -->
            <section v-if="twoFactorEnabled" class="card p-6 space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0
                                bg-gradient-to-br from-amber-500 to-amber-700 text-white shadow-soft">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 7l4 4-4 4M7 17h14M21 7l-4 4 4 4" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-display text-lg font-semibold">Codigos de recuperacao</h2>
                        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 leading-relaxed">
                            Use um dos codigos abaixo caso perca o acesso
                            ao seu app autenticador. Cada codigo so
                            funciona uma vez.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 p-4 rounded-2xl
                            bg-ink-100/60 dark:bg-ink-800/40
                            border border-ink-200/70 dark:border-ink-700/70">
                    <p class="text-sm font-semibold">
                        <span class="num">{{ recoveryCodesRemaining }}</span>
                        de
                        <span class="num">{{ recoveryCodesTotal }}</span>
                        codigos restantes
                    </p>
                    <span
                        v-if="recoveryCodesRemaining < 3"
                        class="status-pill status-pill--warn"
                        role="status"
                    >
                        <span class="status-pill__dot" aria-hidden="true"></span>
                        Baixo
                    </span>
                </div>

                <!--
                    Out of scope for v0.10.0: the regeneration
                    endpoint. We render the button but it is
                    disabled with a tooltip so the user sees the
                    surface even though clicking does nothing.
                -->
                <button
                    type="button"
                    disabled
                    class="btn-ghost text-sm cursor-not-allowed opacity-60"
                    title="Disponivel na proxima versao (v0.11+)"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2" />
                    </svg>
                    Regenerar codigos
                </button>
            </section>

            <!-- ─── Trusted devices ────────────────────────────────────── -->
            <section class="card p-6 space-y-4">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl grid place-items-center shrink-0
                                bg-gradient-to-br from-solar-500 to-solar-600 text-white shadow-soft">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <path d="M8 21h8M12 17v4" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h2 class="font-display text-lg font-semibold">Dispositivos confiaveis</h2>
                        <p class="text-sm text-ink-500 dark:text-ink-400 mt-1 leading-relaxed">
                            Estes dispositivos pulam a verificacao em duas etapas por
                            <strong>90 dias</strong>. Revogue se voce nao os reconhece mais.
                        </p>
                    </div>
                </div>

                <div v-if="trustedDevices.length === 0"
                     class="p-4 rounded-2xl text-sm text-ink-500 dark:text-ink-400
                            bg-ink-100/60 dark:bg-ink-800/40
                            border border-ink-200/70 dark:border-ink-700/70">
                    Nenhum dispositivo confiavel. Marque "Confiar neste dispositivo"
                    no proximo desafio 2FA para pular a verificacao nesse navegador.
                </div>

                <ul v-else class="divide-y divide-ink-200/70 dark:divide-ink-800/70 -mx-2">
                    <li v-for="device in trustedDevices" :key="device.id"
                        class="flex items-center gap-3 p-3 rounded-xl
                               hover:bg-ink-100/40 dark:hover:bg-ink-800/40 transition-colors">
                        <div class="w-9 h-9 rounded-lg grid place-items-center shrink-0
                                    bg-ink-100 dark:bg-ink-800 text-ink-500 dark:text-ink-400">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2" />
                                <path d="M8 21h8M12 17v4" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate">
                                {{ friendlyName(device) }}
                            </p>
                            <p class="text-[11px] text-ink-500 dark:text-ink-400 truncate">
                                IP {{ device.ip || '—' }} · visto em {{ formatDateTime(device.last_seen_at) }} · expira {{ formatDateTime(device.expires_at) }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="revokeDevice(device.id)"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold
                                   text-rose-600 dark:text-rose-400
                                   border border-rose-200/70 dark:border-rose-500/30
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   transition-colors
                                   disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
                            :disabled="revokingId === device.id"
                        >
                            <span v-if="revokingId === device.id">Removendo...</span>
                            <span v-else>Revogar</span>
                        </button>
                    </li>
                </ul>

                <div v-if="trustedDevices.length > 1" class="pt-1">
                    <button
                        type="button"
                        @click="submitRevokeAll"
                        class="btn-ghost text-sm"
                        :disabled="revokeAllForm.processing"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z" />
                        </svg>
                        Revogar todos
                    </button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
