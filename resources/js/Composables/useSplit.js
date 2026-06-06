import { ref, computed, watch } from 'vue';

/**
 * Helpers for transaction splits (FASE 3B).
 *
 * All amounts handled by this composable are in CENTS (integers), the same
 * representation the API stores. UI display uses divide-by-100 via formatCents.
 */
export function useSplit({ totalCents, participants, mode = 'equal' }) {
    // participants: ref of [{ user_id, amount_cents?, percentage? }]
    const splitMode = ref(mode); // 'equal' | 'percentage' | 'amount'
    const localParticipants = ref(
        Array.isArray(participants) && participants.length
            ? JSON.parse(JSON.stringify(participants))
            : []
    );

    watch(splitMode, () => recompute());

    function addParticipant(user) {
        if (!user || !user.id) return;
        if (localParticipants.value.some(p => p.user_id === user.id)) return;
        localParticipants.value.push({ user_id: user.id, name: user.name });
        recompute();
    }

    function removeParticipant(idx) {
        localParticipants.value.splice(idx, 1);
        recompute();
    }

    /**
     * Recompute the cents of each participant based on the current mode.
     */
    function recompute() {
        const n = localParticipants.value.length;
        if (n === 0) return;

        if (splitMode.value === 'equal') {
            const base = Math.trunc(totalCents.value / n);
            const remainder = totalCents.value - base * n;
            localParticipants.value.forEach((p, i) => {
                p.amount_cents = base + (i < Math.abs(remainder) ? Math.sign(remainder) || 1 : 0);
                p.percentage = n > 0 ? +(100 / n).toFixed(2) : 0;
            });
        } else if (splitMode.value === 'percentage') {
            let allocated = 0;
            localParticipants.value.forEach((p, i) => {
                const pct = Number(p.percentage || 0);
                if (i === localParticipants.value.length - 1) {
                    // last one absorbs rounding
                    p.amount_cents = totalCents.value - allocated;
                    p.percentage = +(100 - (allocated / totalCents.value) * 100).toFixed(2);
                } else {
                    const amt = Math.round(totalCents.value * pct / 100);
                    p.amount_cents = amt;
                    allocated += amt;
                }
            });
        } else if (splitMode.value === 'amount') {
            // user-typed amounts; nothing to recompute
        }
    }

    const totalAllocated = computed(() =>
        localParticipants.value.reduce((s, p) => s + Number(p.amount_cents || 0), 0)
    );

    const isBalanced = computed(() => totalAllocated.value === totalCents.value);

    const difference = computed(() => totalCents.value - totalAllocated.value);

    const percentageSum = computed(() =>
        localParticipants.value.reduce((s, p) => s + Number(p.percentage || 0), 0)
    );

    /**
     * Build the payload to send to the backend.
     */
    function payload() {
        return localParticipants.value.map(p => ({
            user_id: p.user_id,
            amount_cents: Number(p.amount_cents) || 0,
            description: p.description || null,
            category_id: p.category_id || null,
        }));
    }

    return {
        splitMode,
        participants: localParticipants,
        addParticipant,
        removeParticipant,
        recompute,
        totalAllocated,
        isBalanced,
        difference,
        percentageSum,
        payload,
    };
}

/**
 * Format cents as a BRL string (e.g. 12345 -> "R$ 123,45").
 */
export function formatSplitCents(cents) {
    const sign = cents < 0 ? '-' : '';
    const abs = Math.abs(Number(cents) || 0);
    const reais = Math.floor(abs / 100);
    const cs = abs % 100;
    return `${sign}R$ ${reais.toLocaleString('pt-BR')},${cs.toString().padStart(2, '0')}`;
}

/**
 * Validate a candidate split payload against a total (in cents).
 * Returns null on success, or a string with the reason on failure.
 */
export function validateSplit(totalCents, parts) {
    if (!Array.isArray(parts) || parts.length < 2) {
        return 'A divisao precisa ter pelo menos 2 pessoas.';
    }
    const sum = parts.reduce((s, p) => s + Number(p.amount_cents || 0), 0);
    if (sum !== Number(totalCents)) {
        return `A soma das partes (${formatSplitCents(sum)}) difere do total (${formatSplitCents(totalCents)}).`;
    }
    return null;
}
