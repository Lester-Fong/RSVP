<script setup lang="ts">
import type { EditorialGallerySection } from '@/types/enchanted-editorial';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';
import EditorialPhoto from './EditorialPhoto.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';

const props = defineProps<{ section: EditorialGallerySection; editorPreview?: boolean }>();
const enhanced = ref(false);
const current = ref(0);
const outgoing = ref<EditorialGallerySection['items'][number] | null>(null);
const element = ref<HTMLElement | null>(null);
const paused = ref(false),
    focused = ref(false),
    visible = ref(false),
    hidden = ref(false),
    reduced = ref(true);
let timer: ReturnType<typeof setInterval> | undefined;
let observer: IntersectionObserver | undefined;
let preference: MediaQueryList | undefined;
const enabled = computed(() => props.section.slideshow?.autoplay ?? true);
const playing = computed(
    () =>
        enhanced.value &&
        enabled.value &&
        props.section.items.length > 1 &&
        !paused.value &&
        !focused.value &&
        visible.value &&
        !hidden.value &&
        !reduced.value,
);
function syncPreference() {
    reduced.value = preference?.matches ?? true;
    if (reduced.value) outgoing.value = null;
}
function syncVisibility() {
    hidden.value = document.hidden;
}
function stop() {
    clearInterval(timer);
    timer = undefined;
}
watch([playing, () => props.section.slideshow?.interval_seconds], () => {
    stop();
    if (playing.value)
        timer = setInterval(
            () => {
                if (playing.value) select(current.value + 1, false);
            },
            (props.section.slideshow?.interval_seconds ?? 5) * 1000,
        );
});
onMounted(() => {
    preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    syncPreference();
    syncVisibility();
    preference.addEventListener('change', syncPreference);
    document.addEventListener('visibilitychange', syncVisibility);
    observer = new IntersectionObserver(([entry]) => (visible.value = entry.isIntersecting && entry.intersectionRatio >= 0.25), {
        threshold: [0, 0.25],
    });
    if (element.value) observer.observe(element.value);
});
onBeforeUnmount(() => {
    stop();
    observer?.disconnect();
    preference?.removeEventListener('change', syncPreference);
    document.removeEventListener('visibilitychange', syncVisibility);
});
let pointer: { x: number; y: number; id: number } | null = null;
onMounted(() => (enhanced.value = true));
watch(
    () => props.section.items,
    () => {
        current.value = 0;
        outgoing.value = null;
        pointer = null;
    },
);
function select(index: number, interaction = true) {
    if (interaction) paused.value = true;
    const count = props.section.items.length;
    const next = count ? (index + count) % count : 0;
    if (next === current.value) return;
    outgoing.value = reduced.value ? null : props.section.items[current.value];
    current.value = next;
}
function keyboard(event: KeyboardEvent) {
    const actions: Record<string, number> = {
        ArrowLeft: current.value - 1,
        ArrowRight: current.value + 1,
        Home: 0,
        End: props.section.items.length - 1,
    };
    if (event.key in actions) {
        event.preventDefault();
        select(actions[event.key]);
    }
}
function pointerDown(event: PointerEvent) {
    if (event.isPrimary && event.pointerType !== 'mouse') pointer = { x: event.clientX, y: event.clientY, id: event.pointerId };
}
function pointerUp(event: PointerEvent) {
    if (!pointer || event.pointerId !== pointer.id) return;
    const dx = event.clientX - pointer.x;
    const dy = event.clientY - pointer.y;
    pointer = null;
    if (Math.abs(dx) >= 50 && Math.abs(dx) > Math.abs(dy) * 1.3) select(current.value + (dx < 0 ? 1 : -1));
}
</script>

<template>
    <section
        ref="element"
        id="our-photos"
        class="ee-gallery"
        aria-labelledby="ee-gallery-heading"
        tabindex="-1"
        :data-enhanced="enhanced"
        :data-playing="playing"
    >
        <div class="ee-container">
            <EditorialOrnament
                kind="flower"
                class="ee-landing-marker ee-landing-gallery"
                data-landing-section="our-photos"
                data-landing-id="section:our-photos"
            />
            <header class="ee-gallery-header">
                <div>
                    <p class="ee-eyebrow">{{ section.eyebrow }}</p>
                    <h2 id="ee-gallery-heading" class="ee-section-heading">{{ section.heading }}</h2>
                </div>
                <p>{{ section.message }}</p>
            </header>
            <p v-if="editorPreview && !section.items.length" class="ee-body" role="status">
                Your photographs will appear here. Upload your favourites in Gallery to begin.
            </p>
            <div v-if="section.items.length" class="ee-gallery-composition">
                <EnchantedBotanical class="ee-gallery-branch" />
                <div
                    class="ee-gallery-carousel"
                    role="group"
                    aria-roledescription="carousel"
                    aria-label="Prenup photographs"
                    @keydown="keyboard"
                    @focusin="focused = true"
                    @focusout="
                        focused = Boolean($event.relatedTarget && ($event.currentTarget as HTMLElement).contains($event.relatedTarget as Node))
                    "
                    @pointerdown="if (!($event.target as HTMLElement).closest('.ee-slideshow-playback')) paused = true;"
                >
                    <p v-if="!enhanced" class="ee-gallery-hint">Scroll sideways to see our photographs.</p>
                    <div
                        class="ee-gallery-stage"
                        tabindex="0"
                        aria-label="Photograph viewer. Use Left and Right arrows to browse."
                        @pointerdown="pointerDown"
                        @pointerup="pointerUp"
                        @pointercancel="pointer = null"
                        @lostpointercapture="pointer = null"
                    >
                        <figure
                            v-for="(item, index) in section.items"
                            :key="item.id"
                            class="ee-gallery-slide"
                            :hidden="enhanced && index !== current"
                            role="group"
                            aria-roledescription="slide"
                            :aria-label="`${index + 1} of ${section.items.length}`"
                        >
                            <EditorialPhoto :photo="item.photo" fit="contain" class="ee-gallery-photo" />
                            <figcaption v-if="!enhanced && item.caption">{{ item.caption }}</figcaption>
                        </figure>
                        <figure
                            v-if="outgoing"
                            :key="outgoing.id"
                            class="ee-gallery-outgoing"
                            aria-hidden="true"
                            inert
                            @animationend="if ($event.target === $event.currentTarget) outgoing = null;"
                        >
                            <EditorialPhoto :photo="outgoing.photo" fit="contain" class="ee-gallery-photo" />
                        </figure>
                    </div>
                    <div v-if="enhanced" class="ee-gallery-controls">
                        <button
                            v-if="section.items.length > 1"
                            type="button"
                            class="ee-gallery-arrow"
                            aria-label="Previous photograph"
                            @click="select(current - 1)"
                        >
                            <span aria-hidden="true">←</span>
                        </button>
                        <p class="ee-gallery-caption" :aria-live="playing ? 'off' : 'polite'" aria-atomic="true">
                            <span>{{ String(current + 1).padStart(2, '0') }} / {{ String(section.items.length).padStart(2, '0') }}</span
                            >{{ section.items[current]?.caption }}
                        </p>
                        <button
                            v-if="section.items.length > 1"
                            type="button"
                            class="ee-gallery-arrow"
                            aria-label="Next photograph"
                            @click="select(current + 1)"
                        >
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                    <div v-if="enhanced && section.items.length > 1 && enabled" class="ee-slideshow-playback">
                        <button
                            type="button"
                            class="ee-outline-link"
                            :disabled="reduced"
                            :aria-label="!paused && !reduced ? 'Pause slideshow' : 'Play slideshow'"
                            @click="paused = !paused"
                        >
                            {{ !paused && !reduced ? 'Pause slideshow' : 'Play slideshow' }}
                        </button>
                        <p class="ee-gallery-hint">
                            {{
                                reduced
                                    ? 'Automatic playback is off for reduced motion.'
                                    : paused
                                      ? 'Paused — browse at your own pace.'
                                      : focused
                                        ? 'Paused while using the controls. Playback resumes when focus leaves.'
                                        : 'Plays only while the gallery is in view.'
                            }}
                        </p>
                    </div>
                    <div v-if="enhanced && section.items.length > 1" class="ee-gallery-pagination" role="group" aria-label="Choose a photograph">
                        <button
                            v-for="(item, index) in section.items"
                            :key="item.id"
                            type="button"
                            :aria-label="`Show photograph ${index + 1}`"
                            :aria-current="index === current ? 'true' : undefined"
                            @click="select(index)"
                        >
                            <span aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
