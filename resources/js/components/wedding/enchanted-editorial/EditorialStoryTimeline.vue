<script setup lang="ts">
import type { EditorialStorySection } from '@/types/enchanted-editorial';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';
import EditorialPhoto from './EditorialPhoto.vue';
const props = defineProps<{ milestones: NonNullable<EditorialStorySection['milestones']> }>();
const timeline = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | undefined;
let motion: MediaQueryList | undefined;
let entered = new WeakSet<Element>();
let visibleRows = new WeakSet<Element>();
const active = new Map<Element, Animation>();

function settle(element: Element) {
    const animation = active.get(element);
    active.delete(element);
    if (animation) {
        animation.onfinish = null;
        animation.cancel();
    }
}

function stopReveals() {
    observer?.disconnect();
    observer = undefined;
    [...active.keys()].forEach(settle);
    entered = new WeakSet();
    visibleRows = new WeakSet();
}

function reveal(element: HTMLElement, fromAbove: boolean) {
    const kind = element.dataset.storyReveal;
    const photograph = kind === 'photo';
    const opacity = Number(getComputedStyle(element).opacity) || 1;
    const startOpacity = photograph || kind === 'decoration' ? 0.62 : kind === 'title' ? 0.82 : 0.92;
    const fade = { opacity: [opacity * startOpacity, opacity] };
    // Animate text inside the static row. Flowers and observed photo geometry never move.
    const frames =
        kind === 'photo' || kind === 'decoration'
            ? fade
            : { ...fade, transform: [`translate3d(0, ${fromAbove ? -8 : 8}px, 0)`, 'translate3d(0, 0, 0)'] };
    const animation = element.animate(frames, {
        id: 'ee-story-reveal-' + kind,
        duration: photograph ? 720 : 560,
        delay: kind === 'title' ? 40 : kind === 'description' ? 90 : photograph ? 50 : 0,
        easing: 'cubic-bezier(0.2, 0.65, 0.3, 1)',
        fill: 'backwards',
    });
    active.set(element, animation);
    animation.onfinish = () => {
        if (active.get(element) === animation) active.delete(element);
    };
}

function observeReveals() {
    stopReveals();
    if (!timeline.value || motion?.matches || typeof IntersectionObserver !== 'function' || typeof HTMLElement.prototype.animate !== 'function')
        return;
    try {
        const current = new IntersectionObserver(
            (entries) => {
                if (observer !== current) return;
                try {
                    // Only the static row rearms its parts, preventing edge jitter from text's 8px drift.
                    for (const entry of entries.filter((entry) => entry.target.matches('.ee-timeline-entry'))) {
                        if (entry.isIntersecting) visibleRows.add(entry.target);
                        else {
                            visibleRows.delete(entry.target);
                            entry.target.querySelectorAll('[data-story-reveal]').forEach((part) => {
                                entered.delete(part);
                                settle(part);
                            });
                        }
                    }
                    for (const entry of entries.filter((entry) => entry.target.matches('[data-story-reveal]'))) {
                        const row = entry.target.closest('.ee-timeline-entry');
                        if (row && visibleRows.has(row) && entry.isIntersecting && !entered.has(entry.target)) {
                            entered.add(entry.target);
                            reveal(entry.target as HTMLElement, entry.boundingClientRect.top < (entry.rootBounds?.top ?? 0));
                        }
                    }
                } catch {
                    stopReveals();
                }
            },
            { rootMargin: '0px 0px -24px 0px', threshold: 0 },
        );
        observer = current;
        timeline.value.querySelectorAll('.ee-timeline-entry, [data-story-reveal]').forEach((element) => current.observe(element));
    } catch {
        stopReveals();
    }
}

onMounted(() => {
    if (typeof window.matchMedia !== 'function') return;
    motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    motion.addEventListener('change', observeReveals);
    observeReveals();
});
watch(
    () => props.milestones.map((milestone) => [milestone.id, Boolean(milestone.photo), Boolean(milestone.description), Boolean(milestone.when)]),
    observeReveals,
    { flush: 'post' },
);
onBeforeUnmount(() => {
    motion?.removeEventListener('change', observeReveals);
    stopReveals();
});
function dateLabel(when: NonNullable<EditorialStorySection['milestones']>[number]['when']) {
    if (!when) return '';
    if (when.precision === 'year') return Number.isInteger(when.value) && when.value > 0 && when.value <= 9999 ? String(when.value) : '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(when.value)) return '';
    const date = new Date(when.value + 'T00:00:00Z');
    return Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== when.value || when.value.startsWith('0000-')
        ? ''
        : new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(date);
}
</script>
<template>
    <section v-if="milestones.length" ref="timeline" class="ee-story-timeline ee-container" aria-labelledby="ee-timeline-heading">
        <p class="ee-eyebrow">One moment at a time</p>
        <h3 id="ee-timeline-heading" class="ee-section-heading">The Moments That Led Us Here</h3>
        <ol class="ee-timeline-list">
            <li v-for="milestone in milestones" :key="milestone.id" class="ee-timeline-entry" :data-milestone-id="milestone.id">
                <span class="ee-timeline-node" data-story-reveal="decoration" aria-hidden="true"></span>
                <EditorialOrnament
                    class="ee-landing-marker ee-landing-milestone"
                    data-landing-section="our-story"
                    :data-landing-milestone="milestone.id"
                    :data-landing-id="'milestone:' + milestone.id"
                />
                <div class="ee-timeline-content">
                    <time v-if="dateLabel(milestone.when)" :datetime="String(milestone.when?.value)" class="ee-eyebrow" data-story-reveal="date">{{
                        dateLabel(milestone.when)
                    }}</time>
                    <h4 data-story-reveal="title">{{ milestone.title }}</h4>
                    <p v-if="milestone.description" class="ee-timeline-description" data-story-reveal="description">{{ milestone.description }}</p>
                    <EditorialPhoto v-if="milestone.photo" :photo="milestone.photo" class="ee-timeline-photo" data-story-reveal="photo" />
                </div>
            </li>
        </ol>
    </section>
</template>
