<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import '../../../resources/css/enchanted-loading.css';
import EditorialOrnament from '../../../resources/js/components/wedding/enchanted-editorial/EditorialOrnament.vue';
import EnchantedHummingbird from '../../../resources/js/components/wedding/enchanted-editorial/EnchantedHummingbird.vue';
import { discoverWeddingWaypoints } from '../../../resources/js/lib/wedding-waypoints';

type Orientation = 'LEFT' | 'FRONT_LEFT' | 'FRONT' | 'FRONT_RIGHT' | 'RIGHT';
const orientations: Orientation[] = ['LEFT', 'FRONT_LEFT', 'FRONT', 'FRONT_RIGHT', 'RIGHT'];
const orientation = ref<Orientation>('FRONT');
const stage = ref<HTMLElement | null>(null),
    bird = ref<HTMLElement | null>(null);
const visible = ref(true),
    paused = ref(false),
    reduced = ref(false),
    playing = ref(false);
const elapsed = ref(0),
    position = ref({ x: 0, y: 0 });
const phase = ref('Hover');
const style = computed(() => ({ transform: `translate3d(${position.value.x}px, ${position.value.y}px, 0)` }));
let frame = 0,
    previous = 0,
    resize: ResizeObserver | undefined,
    visibility: IntersectionObserver | undefined;
let media: MediaQueryList | undefined;
let start = { x: 0, y: 0 },
    right = { x: 0, y: 0 },
    left = { x: 0, y: 0 };
const cleanup = new AbortController();
function mix(a: number, b: number, t: number) {
    return a + (b - a) * t;
}
function travel(from: typeof start, to: typeof start, progress: number, lift: number) {
    const t = Math.max(0, Math.min(1, progress));
    const eased = t * t * (3 - 2 * t);
    return { x: mix(from.x, to.x, eased), y: mix(from.y, to.y, eased) - Math.sin(Math.PI * eased) * lift };
}
function sample() {
    const time = elapsed.value;
    if (time < 700) {
        phase.value = 'Takeoff';
        orientation.value = 'FRONT_RIGHT';
        position.value = travel(start, { ...start, y: start.y - 18 }, time / 700, 4);
    } else if (time < 3000) {
        phase.value = 'Curved flight';
        orientation.value = 'RIGHT';
        position.value = travel({ ...start, y: start.y - 18 }, { ...right, y: right.y - 8 }, (time - 700) / 2300, 46);
    } else if (time < 3600) {
        phase.value = 'Gentle landing';
        position.value = travel({ ...right, y: right.y - 8 }, right, (time - 3000) / 600, 0);
    } else if (time < 4900) {
        phase.value = 'Nectar hover';
        orientation.value = 'RIGHT';
        position.value = right;
    } else if (time < 6400) {
        phase.value = 'Turning';
        position.value = right;
        orientation.value = time < 5250 ? 'FRONT_RIGHT' : time < 5600 ? 'FRONT' : time < 6000 ? 'FRONT_LEFT' : 'LEFT';
    } else if (time < 8800) {
        phase.value = 'Return flight';
        orientation.value = 'LEFT';
        position.value = travel(right, { ...left, y: left.y - 8 }, (time - 6400) / 2400, 66);
    } else if (time < 9400) {
        phase.value = 'Gentle landing';
        position.value = travel({ ...left, y: left.y - 8 }, left, (time - 8800) / 600, 0);
    } else {
        phase.value = 'Nectar hover';
        orientation.value = 'LEFT';
        position.value = left;
    }
}
function measure() {
    if (!stage.value || !bird.value) return;
    const bounds = stage.value.getBoundingClientRect(),
        size = bird.value.clientWidth;
    const stops = discoverWeddingWaypoints(stage.value);
    const point = (id: string, direction: number) => {
        const marker = stops.find((entry) => entry.id === id)?.element;
        const flower = marker?.querySelector('circle')?.getBoundingClientRect();
        return flower
            ? {
                  x: flower.left + flower.width / 2 - bounds.left - (direction * 93 * size) / 200,
                  y: flower.top + flower.height / 2 - bounds.top + (52 * size) / 200,
              }
            : start;
    };
    start = { x: bounds.width * 0.34, y: bounds.height * 0.6 };
    right = point('section:showcase-right', 1);
    left = point('section:showcase-left', -1);
    if (playing.value || elapsed.value) sample();
    else position.value = start;
}
function tick(now: number) {
    if (!playing.value || paused.value || reduced.value) return;
    elapsed.value = Math.min(10800, elapsed.value + (previous ? now - previous : 0));
    previous = now;
    sample();
    if (elapsed.value < 10800) frame = requestAnimationFrame(tick);
    else playing.value = false;
}
function play() {
    if (reduced.value) return;
    cancelAnimationFrame(frame);
    elapsed.value = 0;
    previous = 0;
    paused.value = false;
    playing.value = true;
    measure();
    frame = requestAnimationFrame(tick);
}
function togglePause() {
    paused.value = !paused.value;
    cancelAnimationFrame(frame);
    previous = 0;
    if (!paused.value && playing.value) frame = requestAnimationFrame(tick);
}
function choose(value: Orientation) {
    cancelAnimationFrame(frame);
    playing.value = false;
    elapsed.value = 0;
    phase.value = 'Hover';
    orientation.value = value;
    measure();
}
function toggleBird() {
    visible.value = !visible.value;
    cancelAnimationFrame(frame);
    playing.value = false;
    elapsed.value = 0;
    phase.value = 'Hover';
}
onMounted(() => {
    media = matchMedia('(prefers-reduced-motion: reduce)');
    const preference = () => {
        reduced.value = media!.matches;
        if (reduced.value) {
            choose('FRONT');
            paused.value = false;
        }
    };
    preference();
    media.addEventListener('change', preference, { signal: cleanup.signal });
    document.addEventListener(
        'visibilitychange',
        () => {
            if (document.hidden && !paused.value) togglePause();
        },
        { signal: cleanup.signal },
    );
    resize = new ResizeObserver(measure);
    resize.observe(stage.value!);
    visibility = new IntersectionObserver(([entry]) => {
        if (!entry.isIntersecting && playing.value && !paused.value) togglePause();
    });
    visibility.observe(stage.value!);
    measure();
});
onBeforeUnmount(() => {
    cleanup.abort();
    cancelAnimationFrame(frame);
    resize?.disconnect();
    visibility?.disconnect();
});
</script>

<template>
    <main class="hb-showcase">
        <header class="hb-intro">
            <p class="hb-eyebrow">Enchanted Editorial · DEL-07a</p>
            <h1>A little wonder, in motion.</h1>
            <p>Original layered botanical illustration. Explore five views, then watch a short journey between the flowers.</p>
            <a href="/enchanted-editorial.html?canvas=1&case=minimal">View the bird on the invitation →</a>
        </header>
        <section aria-label="Hummingbird motion demonstration" class="hb-demo">
            <div ref="stage" class="hb-stage" :data-motion-state="phase" :data-playing="playing" :data-paused="paused">
                <EditorialOrnament class="hb-flower hb-flower-left" data-landing-id="section:showcase-left" data-landing-section="showcase-left" />
                <EditorialOrnament class="hb-flower hb-flower-right" data-landing-id="section:showcase-right" data-landing-section="showcase-right" />
                <div ref="bird" class="hb-flight-bird" :style="style">
                    <EnchantedHummingbird v-if="visible" :orientation="orientation" :paused="paused" />
                </div>
                <p class="hb-stage-note">{{ reduced ? 'Reduced motion · static illustration' : phase }}</p>
            </div>
            <div class="hb-controls" aria-label="Motion controls">
                <button type="button" :disabled="reduced || !visible" @click="play">Play flower flight</button>
                <button type="button" :disabled="reduced" :aria-pressed="paused" @click="togglePause">
                    {{ paused ? 'Resume motion' : 'Pause motion' }}
                </button>
                <button type="button" @click="toggleBird">{{ visible ? 'Hide bird' : 'Show bird' }}</button>
            </div>
            <div class="hb-controls" aria-label="Choose bird orientation">
                <button v-for="view in orientations" :key="view" type="button" :aria-pressed="orientation === view" @click="choose(view)">
                    {{ view.replaceAll('_', ' ') }}
                </button>
            </div>
            <p class="hb-caption">The wings keep their own rhythm through every turn and arrival. At each stop, the beak meets the blossom.</p>
        </section>
        <section class="hb-views" aria-label="Five directional studies">
            <figure v-for="view in orientations" :key="view">
                <div class="hb-study"><EnchantedHummingbird v-if="visible" :orientation="view" :hovering="false" :paused="paused" /></div>
                <figcaption>{{ view.replaceAll('_', ' ') }}</figcaption>
            </figure>
        </section>
        <p class="hb-caption">Decorative prototype · no scroll following · reduced motion follows your device preference.</p>
    </main>
</template>

<style>
body:has(.hb-showcase) {
    background: #f8f5ee;
}
.hb-showcase {
    max-width: 1120px;
    margin: auto;
    padding: 64px 32px;
    color: #775b4d;
    font:
        15px/1.7 'Enchanted Sans',
        Arial,
        sans-serif;
}
.hb-intro {
    text-align: center;
    max-width: 660px;
    margin: 0 auto 36px;
}
.hb-intro h1 {
    font:
        500 clamp(36px, 5vw, 60px) / 1.12 'Enchanted Serif',
        Georgia,
        serif;
    margin: 16px 0;
    color: #946a52;
}
.hb-eyebrow,
.hb-stage-note,
.hb-views figcaption {
    font-size: 10px;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}
.hb-intro a {
    color: #956b53;
    text-underline-offset: 5px;
}
.hb-demo {
    border: 1px solid #dfcebb;
    border-radius: 80px 0 48px 0;
    overflow: hidden;
    background: linear-gradient(135deg, #f5efe4, #faf7f0 65%, #f1e7da);
}
.hb-stage {
    height: 340px;
    position: relative;
    overflow: hidden;
}
.hb-flower {
    position: absolute;
    width: 100px;
    height: 100px;
    color: #b18c70;
    pointer-events: none;
}
.hb-flower-left {
    left: 7%;
    top: 42%;
    transform: rotate(-16deg);
}
.hb-flower-right {
    right: 6%;
    top: 28%;
    transform: rotate(12deg);
}
.hb-flight-bird {
    position: absolute;
    width: 148px;
    height: 148px;
    margin: -74px;
    pointer-events: none;
}
.hb-stage-note {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 8px;
    text-align: center;
}
.hb-controls {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 16px;
}
.hb-controls button {
    border: 1px solid #cab39d;
    border-radius: 999px;
    background: transparent;
    color: #795a46;
    font: inherit;
    font-size: 12px;
    padding: 10px 16px;
    min-height: 44px;
    cursor: pointer;
}
.hb-controls button[aria-pressed='true'] {
    background: #e8d9c8;
    border-color: #9a765b;
}
.hb-controls button:focus-visible {
    outline: 2px solid #546f60;
    outline-offset: 3px;
}
.hb-controls button:disabled {
    opacity: 0.5;
    cursor: default;
}
.hb-caption {
    text-align: center;
    font-size: 12px;
    max-width: 660px;
    margin: 20px auto 28px;
    padding: 0 16px;
}
.hb-views {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin: 36px 0;
}
.hb-views figure {
    margin: 0;
    text-align: center;
    border-bottom: 1px solid #dfcebb;
    padding-bottom: 18px;
}
.hb-study {
    width: 100%;
    height: 168px;
}
@media (max-width: 600px) {
    .hb-showcase {
        padding: 36px 16px;
    }
    .hb-stage {
        height: 300px;
    }
    .hb-demo {
        border-radius: 48px 0 32px 0;
    }
    .hb-flight-bird {
        width: 116px;
        height: 116px;
        margin: -58px;
    }
    .hb-flower {
        width: 76px;
        height: 76px;
    }
    .hb-flower-left {
        left: 2%;
    }
    .hb-flower-right {
        right: 0;
    }
    .hb-views {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .hb-views figure:last-child {
        grid-column: 1/-1;
        max-width: 200px;
        width: 100%;
        justify-self: center;
    }
}
</style>
