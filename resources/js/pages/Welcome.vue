<script setup lang="ts">
import type { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Login from './auth/Login.vue';

const page = usePage<SharedData>();
</script>

<template>
    <Login v-if="!page.props.auth.user" :can-reset-password="true" />
    <div v-else class="brand-entry flex min-h-svh items-center justify-center bg-[#f6eee5] p-6">
        <Head title="Welcome back" />
        <main class="w-full max-w-xl rounded-3xl border border-[#deccbb] bg-[#fffdfa] p-8 text-center shadow-sm sm:p-12">
            <p class="brand-eyebrow">A place for your story</p>
            <h1 class="brand-serif mt-5 text-5xl leading-none sm:text-6xl">Welcome back.</h1>
            <p class="mx-auto mt-5 max-w-sm leading-7 text-[#685d56]">Your Imbitado wedding space is ready whenever inspiration finds you.</p>
            <Link
                :href="route('dashboard')"
                class="mt-8 inline-flex min-h-12 items-center justify-center rounded-lg bg-[#735040] px-7 text-sm font-semibold text-white hover:bg-[#593d31]"
            >
                Return to your workspace
            </Link>
        </main>
    </div>
</template>
