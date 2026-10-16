<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import ModernClassicInvitation from '@/components/wedding/ModernClassicInvitation.vue';
import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from '@/types';

defineProps<{
    overviewUrl: string;
    wedding: WeddingPresentation;
    customization: InvitationCustomization;
    heroPhoto: InvitationHeroPhoto | null;
}>();
</script>

<template>
    <Head title="Invitation preview" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b bg-background px-4 py-3 sm:px-6">
            <p class="text-sm font-medium">Private invitation preview</p>
            <Link
                :href="overviewUrl"
                class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium underline-offset-4 hover:bg-muted hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                Back to wedding
            </Link>
        </header>

        <main>
            <ModernClassicInvitation :wedding="wedding" :customization="customization" :hero-photo="heroPhoto" />
        </main>
    </div>
</template>
