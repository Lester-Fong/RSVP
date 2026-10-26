<script setup lang="ts">
import '@/../css/enchanted-photobooth.css';
import { observePhotoboothImages, photoboothPhotos } from '@/lib/photobooth-opening';
import type { EnchantedEditorialPresentation } from '@/types/enchanted-editorial';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EditorialPhoto from './EditorialPhoto.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';

const props = defineProps<{ presentation: EnchantedEditorialPresentation; ready: boolean; dateLabel: string }>();
const emit = defineEmits<{ open: [] }>();
const dialog = ref<HTMLElement | null>(null),
    strip = ref<HTMLElement | null>(null),
    openButton = ref<HTMLButtonElement | null>(null);
const stage = ref<'preparing' | 'printing' | 'settled'>('preparing');
const photos = computed(() => photoboothPhotos(props.presentation));
const frames = computed(() => (photos.value.length ? photos.value : [null]));
const fontsReady = ref(false);
const imagesReady = ref(false);
const initials = computed(() =>
    [props.presentation.wedding.partner_one_name, props.presentation.wedding.partner_two_name].map((name) => Array.from(name.trim())[0]).join(' & '),
);
let timer: ReturnType<typeof setTimeout> | undefined, motion: MediaQueryList | undefined;
let preparationTimer: ReturnType<typeof setTimeout> | undefined, stopImages: (() => void) | undefined;
let previousOverflow = '',
    opened = false,
    disposed = false;
function settle() {
    if (!opened && !disposed && imagesReady.value && props.ready) {
        stage.value = 'settled';
        clearTimeout(timer);
    }
}
function prepare() {
    if (opened || disposed || !props.ready || !imagesReady.value || stage.value !== 'preparing') return;
    clearTimeout(preparationTimer);
    if (motion?.matches) settle();
    else {
        stage.value = 'printing';
        timer = setTimeout(settle, 3800);
    }
}
function open() {
    if (!opened && !disposed) {
        opened = true;
        clearTimeout(timer);
        clearTimeout(preparationTimer);
        stopImages?.();
        try {
            dialog.value?.getAnimations({ subtree: true }).forEach((animation) => animation.pause());
        } catch {
            /* Animation failure must never block entry. */
        }
        emit('open');
    }
}
function keydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        open();
    }
    if (event.key !== 'Tab') return;
    const controls = [...dialog.value!.querySelectorAll<HTMLButtonElement>('button:not(:disabled)')];
    const first = controls[0],
        last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}
function motionChanged() {
    if (motion?.matches) settle();
}
function visibilityChanged() {
    if (document.hidden) settle();
}
watch(() => props.ready, prepare);
onMounted(async () => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    motion = matchMedia('(prefers-reduced-motion: reduce)');
    motion.addEventListener('change', motionChanged);
    document.addEventListener('visibilitychange', visibilityChanged);
    window.addEventListener('pagehide', open);
    void document.fonts.ready.then(() => {
        if (!disposed) fontsReady.value = true;
    });
    preparationTimer = setTimeout(open, 8000);
    if (strip.value) {
        stopImages = observePhotoboothImages(strip.value, {
            pending: () => {
                imagesReady.value = false;
                if (stage.value !== 'preparing') {
                    stage.value = 'preparing';
                    // Hide the invalidated strip before the exit transition retains its DOM.
                    void nextTick(open);
                }
            },
            ready: () => {
                imagesReady.value = true;
                prepare();
            },
            failed: () => void nextTick(open),
        });
    }
    await nextTick();
    if (!opened && !disposed) openButton.value?.focus({ preventScroll: true });
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    clearTimeout(preparationTimer);
    stopImages?.();
    disposed = true;
    motion?.removeEventListener('change', motionChanged);
    document.removeEventListener('visibilitychange', visibilityChanged);
    window.removeEventListener('pagehide', open);
    document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <section
        ref="dialog"
        class="ee-photobooth"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ee-booth-title"
        aria-describedby="ee-booth-help"
        :data-print-state="stage"
        :data-fonts-ready="fontsReady"
        :style="{ '--ee-booth-stage-height': `${260 + frames.length * 87}px` }"
        @keydown="keydown"
    >
        <button class="ee-booth-skip" type="button" @click="open">Skip animation <span aria-hidden="true">↗</span></button>
        <EnchantedBotanical class="ee-booth-branch ee-booth-branch-left" />
        <EnchantedBotanical class="ee-booth-branch ee-booth-branch-right" />
        <div class="ee-booth-invitation">
            <p class="ee-booth-eyebrow">{{ presentation.opening?.heading || 'We are getting married' }}</p>
            <h1 id="ee-booth-title">
                <span>{{ presentation.wedding.partner_one_name }}</span
                ><i>&amp;</i><span>{{ presentation.wedding.partner_two_name }}</span>
            </h1>
            <p v-if="presentation.wedding.wedding_date" class="ee-booth-date">{{ dateLabel }}</p>
        </div>
        <div class="ee-booth-stage" aria-hidden="true">
            <div class="ee-booth-machine">
                <div class="ee-booth-cabinet">
                    <div class="ee-booth-trim"></div>
                    <p class="ee-booth-maker">Les souvenirs<span>A moment to remember</span></p>
                    <span class="ee-booth-lens"></span>
                    <div class="ee-booth-window">
                        <span>{{ initials }}</span
                        ><small>For all our tomorrows</small><i></i>
                    </div>
                    <span class="ee-booth-slot"></span>
                    <p class="ee-booth-plaque">Love, captured.</p>
                    <span class="ee-booth-light"></span>
                </div>
                <div class="ee-booth-paper-channel">
                    <div ref="strip" class="ee-booth-strip" @animationend.self="settle">
                        <div v-for="(photo, index) in frames" :key="index" class="ee-booth-print">
                            <EditorialPhoto v-if="photo" :photo="{ ...photo, crop: 'natural', frame: 'fill' }" fit="cover" eager />
                            <template v-else
                                ><EnchantedBotanical /><span>{{ initials }}</span></template
                            >
                            <small>{{ String(index + 1).padStart(2, '0') }}</small>
                        </div>
                        <div class="ee-booth-print-signature">
                            <span>To love &amp; to cherish</span><small>{{ initials }}</small>
                        </div>
                    </div>
                </div>
                <span class="ee-booth-slot-lip" aria-hidden="true"></span>
            </div>
        </div>
        <div class="ee-booth-actions">
            <p id="ee-booth-help" class="ee-booth-help" role="status">
                {{ stage === 'settled' ? 'A little moment. The beginning of forever.' : 'A few moments, made into a memory…' }}
            </p>
            <button ref="openButton" class="ee-booth-open" type="button" @click="open">
                {{ presentation.opening?.openLabel || 'Open Invitation' }} <span aria-hidden="true">↗</span>
            </button>
            <p class="ee-booth-footnote">An invitation, just for you</p>
        </div>
    </section>
</template>
