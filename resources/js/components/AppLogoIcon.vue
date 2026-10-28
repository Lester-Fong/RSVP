<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" :class="className" fill="none" v-bind="$attrs">
        <circle cx="19" cy="24" r="11" stroke="currentColor" stroke-width="1.7" />
        <circle cx="29" cy="24" r="11" stroke="currentColor" stroke-width="1.7" />
        <path d="M24 8v4M24 36v4" stroke="currentColor" stroke-linecap="round" stroke-width="1.7" />
    </svg>
</template>
