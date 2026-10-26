<script setup lang="ts">
import WeddingForm from '@/components/WeddingForm.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <Head title="Welcome to your wedding workspace" />
        <header class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-6 py-6">
            <span class="text-sm font-medium">Your wedding workspace</span>
            <Link :href="route('logout')" method="post" as="button" class="rounded px-3 py-2 text-sm underline underline-offset-4">Log out</Link>
        </header>
        <main class="mx-auto flex max-w-3xl flex-col gap-8 px-6 pb-12 pt-6 sm:pt-12">
            <div class="flex flex-col gap-3">
                <p class="text-xs font-medium uppercase tracking-widest text-muted-foreground">A lovely beginning</p>
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">Let's make room for your story.</h1>
                <p class="max-w-xl leading-relaxed text-muted-foreground">
                    Start with your names. Your workspace is dedicated to your wedding, and you can add the date and venues whenever you're ready.
                </p>
            </div>
            <section aria-label="Your wedding details" class="rounded-2xl border bg-card p-5 shadow-sm sm:p-8">
                <WeddingForm :account="null" onboarding />
            </section>
            <p class="text-sm text-muted-foreground">
                Only the two names are required. Nothing is public until you explicitly publish your invitation.
            </p>
        </main>
    </div>
</template>
