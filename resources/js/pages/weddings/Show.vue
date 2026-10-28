<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import PublicationPanel from '@/components/wedding/PublicationPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem, WeddingDetails, WeddingPublicationSummary } from '@/types';
import { ChevronDown } from 'lucide-vue-next';

defineProps<{
    account: AccountSummary;
    wedding: WeddingDetails;
    publication: WeddingPublicationSummary;
    cmsEditorUrl?: string | null;
    cmsSavedPreviewUrl?: string | null;
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
        <div class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-8 p-4 sm:p-6">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="font-mono text-xs uppercase tracking-widest text-muted-foreground [overflow-wrap:anywhere]">Imbitado workspace</p>
                    <h1 class="mt-1 break-words text-2xl font-semibold tracking-tight [overflow-wrap:anywhere]">
                        {{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">{{ formatDate(wedding.wedding_date) }}</p>
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child
                        ><Button variant="outline">More <ChevronDown class="ml-2 h-4 w-4" aria-hidden="true" /></Button
                    ></DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="motion-reduce:!animate-none motion-reduce:!transition-none">
                        <DropdownMenuItem as-child
                            ><Link :href="route('accounts.weddings.edit', { account: account.id, wedding: wedding.id })"
                                >Edit wedding details</Link
                            ></DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>

            <PublicationPanel :account-id="account.id" :wedding-id="wedding.id" :publication="publication" />
            <div v-if="cmsEditorUrl" class="flex flex-wrap gap-3">
                <a :href="cmsEditorUrl" class="text-sm underline underline-offset-4">Edit wedding website</a>
                <a v-if="cmsSavedPreviewUrl" :href="cmsSavedPreviewUrl" target="_blank" rel="noopener" class="text-sm underline underline-offset-4"
                    >Review saved website</a
                >
            </div>

            <section class="min-w-0 pb-6">
                <div class="mb-5">
                    <h2 class="text-lg font-medium">Wedding information</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Saved wedding details. Website changes appear publicly when you publish or republish.
                    </p>
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
                    <div v-if="wedding.timezone">
                        <dt class="text-sm text-muted-foreground">Timezone</dt>
                        <dd class="mt-1 font-medium">{{ wedding.timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Ceremony venue</dt>
                        <dd class="mt-1 font-medium [overflow-wrap:anywhere]">{{ wedding.ceremony_venue || 'Not set' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Reception venue</dt>
                        <dd class="mt-1 font-medium [overflow-wrap:anywhere]">{{ wedding.reception_venue || 'Not set' }}</dd>
                    </div>
                </dl>
            </section>
        </div>
    </AppLayout>
</template>
