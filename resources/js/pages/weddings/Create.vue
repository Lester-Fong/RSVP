<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import WeddingForm from '@/components/WeddingForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem } from '@/types';

defineProps<{
    account: AccountSummary | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Create wedding', href: '/weddings/create' },
];
</script>

<template>
    <Head title="Create wedding" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
            <div class="mb-6">
                <p v-if="account" class="text-sm text-muted-foreground">{{ account.name }}</p>
                <h1 class="text-2xl font-semibold tracking-tight">Wedding details</h1>
                <p class="mt-1 text-sm text-muted-foreground">Add the details you know. You can update them later.</p>
            </div>
            <div class="rounded-xl border bg-card p-5 sm:p-7">
                <WeddingForm :account="account" />
            </div>
        </div>
    </AppLayout>
</template>
