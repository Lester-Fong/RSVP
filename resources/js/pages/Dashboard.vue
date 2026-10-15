<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem, WeddingSummary } from '@/types';

defineProps<{
    account: AccountSummary | null;
    weddings: WeddingSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const formatDate = (date: string | null) => {
    if (!date) return 'Date not set';

    const [year, month, day] = date.split('-').map(Number);
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'long',
    }).format(new Date(year, month - 1, day));
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p v-if="account" class="text-sm text-muted-foreground">{{ account.name }}</p>
                    <h1 class="text-2xl font-semibold tracking-tight">Your weddings</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Manage your wedding details in one place.</p>
                </div>

                <Button v-if="account" as-child>
                    <Link :href="route('accounts.weddings.create', { account: account.id })">Create a wedding</Link>
                </Button>
                <Button v-else as-child>
                    <Link :href="route('weddings.create')">Create your first wedding</Link>
                </Button>
            </header>

            <section v-if="weddings.length" class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="wedding in weddings"
                    :key="wedding.id"
                    :href="route('accounts.weddings.show', { account: account!.id, wedding: wedding.id })"
                    class="rounded-xl border bg-card p-5 transition hover:border-primary/50 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-medium">{{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}</h2>
                            <p class="mt-1 text-sm text-muted-foreground">{{ formatDate(wedding.wedding_date) }}</p>
                        </div>
                        <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium capitalize">{{ wedding.status }}</span>
                    </div>
                    <p class="mt-5 text-sm font-medium text-primary">View wedding details →</p>
                </Link>
            </section>

            <section v-else class="rounded-xl border border-dashed p-8 text-center sm:p-12">
                <h2 class="text-lg font-medium">Start with your wedding details</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                    Add the couple's names now. You can fill in the date and venues whenever they're ready.
                </p>
                <Button v-if="account" as-child class="mt-5">
                    <Link :href="route('accounts.weddings.create', { account: account.id })">Create a wedding</Link>
                </Button>
                <Button v-else as-child class="mt-5">
                    <Link :href="route('weddings.create')">Create your first wedding</Link>
                </Button>
            </section>
        </div>
    </AppLayout>
</template>
