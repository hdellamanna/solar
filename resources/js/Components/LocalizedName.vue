<template>
    <span
        class="localized-name"
        :class="$attrs.class"
        :title="titleText"
    >
        <slot :name="displayText">
            {{ displayText }}
        </slot>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { useLocalizedName } from '@/Composables/useLocalizedName';

const props = defineProps({
    /**
     * The entity to render. Must carry `name_pt`, `name_es`, `name_en`
     * columns (Category, Tag, etc.) — or a legacy `name` column.
     * Required.
     */
    entity: {
        type: Object,
        required: true,
    },
    /**
     * Optional override for the displayed text. When provided,
     * the component skips the locale lookup and renders the
     * fallback string verbatim. Useful for tests and for cases
     * where the parent already resolved the name.
     */
    fallback: {
        type: String,
        default: null,
    },
});

defineOptions({ inheritAttrs: false });

const computedName = useLocalizedName(() => props.entity);

const displayText = computed(() => {
    if (props.fallback) return props.fallback;
    return computedName.value;
});

/**
 * Hover tooltip: always show the pt-BR name (the seed language)
 * for now, so power users can see the canonical form regardless
 * of the active UI locale. Falls back to the active locale name
 * if pt-BR is not set.
 */
const titleText = computed(() => {
    const e = props.entity;
    if (!e || typeof e !== 'object') return '';
    return e.name_pt || e.name_en || e.name_es || displayText.value;
});
</script>

<style scoped>
.localized-name {
    /* Visually transparent wrapper; styling comes from the slot
       consumer or the inherited class via $attrs. We use
       display: contents so a parent flex/grid layout is not
       broken by the <span>. */
    display: inline;
}
</style>
