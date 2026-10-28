<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import originalArtwork from '../../../../assets/enchanted-editorial/hummingbird.svg?raw';

const props = withDefaults(
    defineProps<{ orientation?: 'LEFT' | 'FRONT_LEFT' | 'FRONT' | 'FRONT_RIGHT' | 'RIGHT'; hovering?: boolean; paused?: boolean }>(),
    { orientation: 'FRONT', hovering: true, paused: false },
);
const root = ref<HTMLElement | null>(null);
const active = ref(true);
const prefix = `ee-hb-${useId().replaceAll(':', '')}`;
const artwork = originalArtwork.replaceAll('id="bird-', `id="${prefix}-`).replaceAll('url(#bird-', `url(#${prefix}-`);
const headings = { LEFT: -1, FRONT_LEFT: -0.5, FRONT: 0, FRONT_RIGHT: 0.5, RIGHT: 1 };
const numbers = /-?\d*\.?\d+/g;
let yaw = headings[props.orientation],
    frame = 0;
let view: Window | undefined, media: MediaQueryList | undefined, observer: IntersectionObserver | undefined;
let inView = true;
let parts: { element: SVGPathElement; front: string; coordinates: number[]; profile: number[] }[] = [];
const cleanup = new AbortController();

function draw(value: number) {
    yaw = value;
    root.value?.setAttribute('data-yaw', value.toFixed(3));
    for (const part of parts) {
        let index = 0;
        const path = part.front.replace(numbers, () => {
            const coordinate = part.coordinates[index] + (part.profile[index] - part.coordinates[index]) * Math.abs(value);
            const reflected = value < 0 && index % 2 === 0 ? 200 - coordinate : coordinate;
            index++;
            return reflected.toFixed(2);
        });
        part.element.setAttribute('d', path);
    }
    root.value?.querySelector('[data-far-eye]')?.setAttribute('opacity', String(Math.max(0, 1 - Math.abs(value) * 1.7)));
}
function turn() {
    if (!view) return;
    view.cancelAnimationFrame(frame);
    frame = 0;
    const target = headings[props.orientation];
    if (media?.matches || !active.value || props.paused || Math.abs(target - yaw) < 0.001) return draw(target);
    const from = yaw,
        started = view.performance.now();
    const step = (now: number) => {
        const progress = Math.min(1, (now - started) / 460);
        draw(from + (target - from) * (progress * progress * (3 - 2 * progress)));
        frame = progress < 1 ? view!.requestAnimationFrame(step) : 0;
    };
    frame = view.requestAnimationFrame(step);
}
function synchronize() {
    active.value = inView && root.value?.ownerDocument.visibilityState === 'visible' && !props.paused;
    turn();
}
watch(() => props.orientation, turn);
watch(() => props.paused, synchronize);
onMounted(() => {
    view = root.value?.ownerDocument.defaultView ?? undefined;
    if (!root.value || !view) return;
    parts = [...root.value.querySelectorAll<SVGPathElement>('path[data-profile]')].map((element) => {
        const front = element.getAttribute('d')!;
        return { element, front, coordinates: front.match(numbers)!.map(Number), profile: element.dataset.profile!.match(numbers)!.map(Number) };
    });
    draw(yaw);
    media = view.matchMedia('(prefers-reduced-motion: reduce)');
    media.addEventListener('change', synchronize, { signal: cleanup.signal });
    root.value.ownerDocument.addEventListener('visibilitychange', synchronize, { signal: cleanup.signal });
    observer = new IntersectionObserver(([entry]) => {
        inView = entry.isIntersecting;
        synchronize();
    });
    observer.observe(root.value);
    synchronize();
});
onBeforeUnmount(() => {
    cleanup.abort();
    observer?.disconnect();
    view?.cancelAnimationFrame(frame);
    parts = [];
});
</script>

<template>
    <span
        ref="root"
        class="ee-hummingbird"
        :data-orientation="orientation"
        :data-hovering="hovering"
        :data-active="active"
        aria-hidden="true"
        v-html="artwork"
    />
</template>
