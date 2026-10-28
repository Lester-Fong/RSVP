<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="brand-auth">
        <div class="brand-auth-shell">
            <aside class="brand-auth-story" aria-label="About Imbitado">
                <Link
                    :href="route('home')"
                    class="inline-flex w-fit items-center gap-3 text-sm font-semibold tracking-wide"
                    aria-label="Imbitado home"
                >
                    <AppLogoIcon class-name="size-9 text-[#765441]" aria-hidden="true" />
                    <span class="brand-serif text-2xl">Imbitado</span>
                </Link>
                <div class="my-10 space-y-6">
                    <p class="brand-eyebrow">A beautiful beginning</p>
                    <h2 class="brand-serif">A place for your story to unfold.</h2>
                    <p>Create your wedding website, shape your invitation, and welcome your guests in one thoughtful space.</p>
                </div>
                <p class="brand-story-footer text-xs tracking-wide">Your love story, beautifully shared.</p>
            </aside>

            <main class="brand-auth-main">
                <div class="brand-auth-form">
                    <p class="brand-eyebrow mb-4">Your wedding, beautifully begun</p>
                    <h1 class="brand-serif">{{ title }}</h1>
                    <p class="mb-8 mt-3 max-w-md text-sm leading-6 text-[#685d56]">{{ description }}</p>
                    <slot />
                </div>
                <p class="text-center text-xs leading-5 text-[#75685e]">Your wedding website stays private until you choose to publish.</p>
            </main>
        </div>
    </div>
</template>
