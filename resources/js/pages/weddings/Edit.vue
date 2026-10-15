<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import WeddingForm from '@/components/WeddingForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem, WeddingDetails } from '@/types';

defineProps<{
    account: AccountSummary;
    wedding: WeddingDetails;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Edit wedding', href: '#' },
];
</script>

<template>
    <Head title="Edit wedding" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl p-4 sm:p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold tracking-tight">Edit wedding details</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Update information for {{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}.
                </p>
            </div>
            <div class="rounded-xl border bg-card p-5 sm:p-7">
                <WeddingForm :account="account" :wedding="wedding" />
            </div>
        </div>
    </AppLayout>
</template>
