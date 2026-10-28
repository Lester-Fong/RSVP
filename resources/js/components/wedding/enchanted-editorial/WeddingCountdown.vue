<script setup lang="ts">
import { countdownUnits, weddingInstant } from '@/lib/wedding-countdown';
import type { WeddingPresentation } from '@/types';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';

const props = defineProps<{ wedding: WeddingPresentation; timeZone: string; eyebrow: string; heading: string; message: string }>();
const target = computed(() => weddingInstant(props.wedding.wedding_date, props.wedding.wedding_time, props.timeZone));
const now = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | undefined;
const labels = ['Days', 'Hours', 'Minutes', 'Seconds'];
const remaining = computed(() => (target.value !== null && now.value !== null ? countdownUnits(target.value, now.value) : null));
const started = computed(() => target.value !== null && now.value !== null && now.value >= target.value);
const eventLabel = computed(() =>
    target.value === null
        ? null
        : new Intl.DateTimeFormat('en-GB', {
              timeZone: props.timeZone,
              dateStyle: 'long',
              timeStyle: 'short',
          }).format(target.value),
);
function stopTimer() {
    if (timer !== undefined) clearInterval(timer);
    timer = undefined;
}
function startTimer() {
    stopTimer();
    now.value = Date.now();
    if (target.value !== null && now.value < target.value)
        timer = setInterval(() => {
            now.value = Date.now();
            if (started.value) stopTimer();
        }, 1000);
}
onMounted(startTimer);
watch(target, () => {
    if (now.value !== null) startTimer();
});
onUnmounted(stopTimer);
</script>

<template>
    <section id="countdown" class="ee-countdown" aria-labelledby="ee-countdown-heading" tabindex="-1">
        <EditorialOrnament class="ee-landing-marker ee-landing-section" data-landing-section="countdown" data-landing-id="section:countdown" />
        <div class="ee-container ee-countdown-inner">
            <EnchantedBotanical class="ee-countdown-branch" />
            <p class="ee-eyebrow">{{ eyebrow }}</p>
            <h2 id="ee-countdown-heading" class="ee-section-heading">{{ heading }}</h2>
            <p class="ee-countdown-message">{{ message }}</p>
            <dl v-if="remaining && !started" class="ee-countdown-units" aria-label="Time until the wedding">
                <div v-for="(value, index) in remaining" :key="labels[index]">
                    <dt>{{ labels[index] }}</dt>
                    <dd>{{ String(value).padStart(2, '0') }}</dd>
                </div>
            </dl>
            <p v-if="started" class="ee-countdown-arrived" role="status">Our wedding day has arrived.</p>
            <p v-else-if="target === null" class="ee-countdown-note">The wedding date and time will be confirmed soon.</p>
            <p v-else-if="now === null" class="ee-countdown-note">Counting down to a day filled with love.</p>
            <p v-if="eventLabel" class="ee-countdown-date">
                <time :datetime="new Date(target!).toISOString()">{{ eventLabel }}</time
                ><span>{{ timeZone }}</span>
            </p>
        </div>
    </section>
</template>
