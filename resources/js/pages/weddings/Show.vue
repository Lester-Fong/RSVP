<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem, WeddingDetails } from '@/types';

defineProps<{
    account: AccountSummary;
    wedding: WeddingDetails;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Wedding overview', href: '#' },
];

const formatDate = (date: string | null) => {
    if (!date) return 'Not set';

    const [year, month, day] = date.split('-').map(Number);
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'long' }).format(new Date(year, month - 1, day));
};
</script>

<template>
    <Head :title="`${wedding.partner_one_name} & ${wedding.partner_two_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm text-muted-foreground">{{ account.name }}</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ formatDate(wedding.wedding_date) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline">
                        <Link :href="route('accounts.weddings.invitation.edit', { account: account.id, wedding: wedding.id })">
                            Customize invitation
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="route('accounts.weddings.preview', { account: account.id, wedding: wedding.id })">Preview invitation</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('accounts.weddings.edit', { account: account.id, wedding: wedding.id })">Edit wedding</Link>
                    </Button>
                </div>
            </header>

            <section class="rounded-xl border bg-card p-5 sm:p-7">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="text-lg font-medium">Wedding information</h2>
                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium capitalize">{{ wedding.status }}</span>
                </div>
                <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted-foreground">Date</dt>
                        <dd class="mt-1 font-medium">{{ formatDate(wedding.wedding_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Time</dt>
                        <dd class="mt-1 font-medium">{{ wedding.wedding_time || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Ceremony venue</dt>
                        <dd class="mt-1 font-medium">{{ wedding.ceremony_venue || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Reception venue</dt>
                        <dd class="mt-1 font-medium">{{ wedding.reception_venue || 'Not set' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </AppLayout>
</template>
