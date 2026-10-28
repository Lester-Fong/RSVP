<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ workspaces: { id: number; name: string; weddings: { id: number; partner_one_name: string; partner_two_name: string }[] }[] }>();
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'Workspace review', href: '/dashboard' }]">
        <Head title="Workspace review" />
        <main class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-6">
            <header class="flex flex-col gap-3">
                <h1 class="text-2xl font-semibold">Your workspace needs a review</h1>
                <p class="text-muted-foreground">
                    Your account has existing workspaces or weddings from an earlier setup. Please contact the service owner to review them. Your
                    saved invitations are preserved, and you can continue managing them below. New wedding creation is unavailable.
                </p>
            </header>
            <section v-for="workspace in workspaces" :key="workspace.id" class="flex flex-col gap-4 rounded-xl border p-5">
                <h2 class="break-words font-medium [overflow-wrap:anywhere]">{{ workspace.name }}</h2>
                <Link
                    v-for="wedding in workspace.weddings"
                    :key="wedding.id"
                    :href="route('accounts.weddings.show', { account: workspace.id, wedding: wedding.id })"
                    class="break-words text-sm underline underline-offset-4 [overflow-wrap:anywhere]"
                    >{{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}</Link
                >
                <p v-if="!workspace.weddings.length" class="text-sm text-muted-foreground">No wedding in this workspace.</p>
            </section>
        </main>
    </AppLayout>
</template>
