<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div
        class="brand-logo-mark flex aspect-square size-10 items-center justify-center rounded-full border border-[#cbb39d] bg-[#fffaf3] text-[#795540]"
    >
        <AppLogoIcon class-name="size-7" aria-hidden="true" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="brand-serif truncate text-[22px] font-semibold leading-none">Imbitado</span>
        <span class="brand-logo-tagline truncate text-[11px] text-[#756558]">Your wedding website</span>
    </div>
</template>
