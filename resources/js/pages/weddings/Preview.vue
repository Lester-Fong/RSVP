<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import ModernClassicInvitation from '@/components/wedding/ModernClassicInvitation.vue';
import WeddingNavigation from '@/components/wedding/WeddingNavigation.vue';
import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from '@/types';

defineProps<{
    overviewUrl: string;
    accountId: number;
    weddingId: number;
    wedding: WeddingPresentation;
    customization: InvitationCustomization;
    heroPhoto: InvitationHeroPhoto | null;
}>();
</script>

<template>
    <Head title="Invitation preview" />

    <div class="min-h-screen bg-background text-foreground">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b bg-background px-4 py-3 sm:px-6">
            <WeddingNavigation :account-id="accountId" :wedding-id="weddingId" active="invitation" />
            <p class="text-sm font-medium">Private invitation preview</p>
        </header>

        <main>
            <ModernClassicInvitation :wedding="wedding" :customization="customization" :hero-photo="heroPhoto" />
        </main>
    </div>
</template>
