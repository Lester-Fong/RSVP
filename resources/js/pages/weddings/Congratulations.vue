<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps<{ receipt: string; partnerOne: string; partnerTwo: string }>();
const celebrate = ref(false);
onMounted(() => {
    try {
        const key = 'wedding-onboarding:' + props.receipt;
        if (!sessionStorage.getItem(key)) {
            sessionStorage.setItem(key, 'seen');
            celebrate.value = true;
        }
    } catch {
        // A static acknowledgment remains available when browser storage is unavailable.
    }
});
</script>

<template>
    <main class="flex min-h-svh items-center justify-center bg-background px-6 py-12 text-foreground">
        <Head title="Your wedding workspace is ready" />
        <section :class="{ 'welcome-celebration': celebrate }" class="flex w-full max-w-xl flex-col items-center gap-6 text-center">
            <span class="flex size-20 items-center justify-center rounded-full border bg-card" aria-hidden="true"><Heart class="size-8" /></span>
            <div class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">Your wedding workspace is ready</p>
                <h1 class="break-words text-3xl font-semibold tracking-tight [overflow-wrap:anywhere] sm:text-4xl">
                    Congratulations, {{ partnerOne }} &amp; {{ partnerTwo }}!
                </h1>
                <p class="leading-relaxed text-muted-foreground">
                    A beautiful beginning, saved just for you. Your invitation is waiting to become yours.
                </p>
            </div>
            <Button as-child size="lg"><Link :href="route('dashboard')" replace>Enter your wedding dashboard</Link></Button>
        </section>
    </main>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .welcome-celebration {
        animation: welcome-arrival 650ms ease-out both;
    }
    @keyframes welcome-arrival {
        from {
            opacity: 0;
            transform: translateY(12px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
}
</style>
