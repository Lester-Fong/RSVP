<script setup lang="ts">
import '@/../css/enchanted-loading.css';
import { discoverWeddingWaypoints } from '@/lib/wedding-waypoints';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import EnchantedHummingbird from './EnchantedHummingbird.vue';

type Orientation = 'LEFT' | 'FRONT_LEFT' | 'FRONT' | 'FRONT_RIGHT' | 'RIGHT';
type FlightPoint = { id: string; x: number; y: number; scrollY: number; orientation: 'LEFT' | 'RIGHT' };
type FlightTarget = { x: number; y: number; scale: number; rotate: number; orientation: Orientation; waypoint: string; resting: boolean };

const props = withDefaults(defineProps<{ hold?: boolean; opening?: boolean }>(), { hold: false, opening: false });
const emit = defineEmits<{ ready: [] }>();

const root = ref<HTMLElement | null>(null),
    content = ref<HTMLElement | null>(null);
const ready = ref(false),
    phase = ref<'loading' | 'landing' | 'landed'>('loading');
const enhancing = ref(false);
const orientation = ref<Orientation>('FRONT');
const position = ref({ x: 0, y: 0, scale: 1, rotate: 0 });
const guided = ref(false),
    flightState = ref<'resting' | 'traveling'>('resting');
const activeWaypoint = ref('section:invitation'),
    labelTop = ref(0);
const cancellation = new AbortController();
let observer: ResizeObserver | undefined, mutationObserver: MutationObserver | undefined;
let landingTimer: ReturnType<typeof setTimeout> | undefined,
    flightFrame = 0,
    measureFrame = 0;
let windowRef: Window | undefined,
    points: FlightPoint[] = [],
    target: FlightTarget | undefined;
let motionPreference: MediaQueryList | undefined,
    coordinatorBound = false;
let geometryDirty = true,
    previousScroll = 0,
    scrollDirection = 1;
let heroMarker: SVGElement | undefined,
    heroLift = 0;
// 200×180 artwork is centred inside the existing 76px square (xMidYMid meet).
// Profile bill tips: RIGHT (193,38), LEFT (7,38), relative to artwork centre (100,90).
const billOffset = (93 * 76) / 200,
    birdYOffset = (52 * 76) / 200;

const birdStyle = computed(() => ({
    transform: `translate3d(${position.value.x}px, ${position.value.y}px, 0) scale(${position.value.scale}) rotate(${position.value.rotate}deg)`,
}));

function reducedMotion(): boolean {
    return windowRef?.matchMedia('(prefers-reduced-motion: reduce)').matches ?? false;
}

function headingFor(deltaX: number): Orientation {
    if (deltaX <= -26) return 'LEFT';
    if (deltaX < -6) return 'FRONT_LEFT';
    if (deltaX >= 26) return 'RIGHT';
    if (deltaX > 6) return 'FRONT_RIGHT';
    return 'FRONT';
}

function readPoints(): FlightPoint[] {
    const element = root.value;
    const view = windowRef;
    if (!element || !view) return [];
    const scrollY = view.scrollY;
    const discovered = discoverWeddingWaypoints(element);
    const hero = discovered.find((point) => point.id === 'section:invitation')?.element as SVGElement | undefined;
    if (hero) {
        if (heroMarker !== hero) {
            heroMarker = hero;
            heroLift = 0;
        }
        const nectar = (hero.querySelector('[data-nectar-center], circle') ?? hero).getBoundingClientRect();
        // On short/landscape screens or unusually long names, keep the first blossom in view.
        const baseY = nectar.top + nectar.height / 2 + scrollY - heroLift;
        const lift = Math.min(0, view.innerHeight - 110 - baseY);
        if (Math.abs(lift - heroLift) > 0.1) {
            heroLift = lift;
            hero.style.translate = `0 ${lift}px`;
        }
    }

    const maxScroll = Math.max(0, element.ownerDocument.documentElement.scrollHeight - view.innerHeight);
    return discovered.map((waypoint, index) => {
        const nectar = (waypoint.element.querySelector('[data-nectar-center], circle') ?? waypoint.element).getBoundingClientRect();
        const x = nectar.left + nectar.width / 2;
        const y = nectar.top + nectar.height / 2 + scrollY;
        // Approach from the free side of the flower; Hero uses the outer photo edge.
        const faceLeft = waypoint.id === 'section:invitation' ? x + billOffset + 38 < view.innerWidth : x < view.innerWidth / 2;
        return {
            id: waypoint.id,
            x: x + (faceLeft ? billOffset : -billOffset),
            y: y + birdYOffset,
            orientation: faceLeft ? 'LEFT' : 'RIGHT',
            scrollY: index === 0 ? 0 : Math.min(maxScroll, Math.max(0, y - view.innerHeight * 0.38)),
        };
    });
}

function refreshPoints() {
    if (geometryDirty) {
        points = readPoints();
        geometryDirty = false;
    }
    if (guided.value && !reducedMotion()) updateTarget();
}

function scheduleRefresh(remeasure = true) {
    geometryDirty ||= remeasure;
    if (!windowRef || measureFrame) return;
    measureFrame = windowRef.requestAnimationFrame(() => {
        measureFrame = 0;
        refreshPoints();
    });
}

function positionOnPath(first: FlightPoint, second: FlightPoint, progress: number): FlightTarget {
    // Small dwell bands allow genuine nectar contact before takeoff / after approach.
    const travel = Math.max(0, Math.min(1, (progress - 0.16) / 0.68));
    if (travel === 0) return restingTarget(first);
    if (travel === 1) return restingTarget(second);
    const fromY = first.y - (windowRef?.scrollY ?? 0),
        toY = second.y - (windowRef?.scrollY ?? 0);
    const dx = second.x - first.x,
        dy = toY - fromY;
    const distance = Math.hypot(dx, dy) || 1;
    const arc = Math.min(120, Math.max(40, distance * 0.18));
    const controlX = (first.x + second.x) / 2 - (dy / distance) * arc;
    const controlY = (fromY + toY) / 2 + (dx / distance) * arc - Math.min(50, distance * 0.1);
    // Smootherstep: deliberate slower flower approaches and quicker mid-flight travel.
    const t = travel ** 3 * (10 + travel * (-15 + 6 * travel)),
        inverse = 1 - t;
    const peak = (windowRef?.innerWidth ?? 0) <= 820 ? 0.1 : 0.18;
    const scale = 1 + Math.sin(Math.PI * t) ** 2 * peak;
    const margin = 42 * scale;
    const x = Math.min(
        Math.max(inverse * inverse * first.x + 2 * inverse * t * controlX + t * t * second.x, margin),
        Math.max(margin, (windowRef?.innerWidth ?? 84) - margin),
    );
    const y = Math.min(
        Math.max(inverse * inverse * fromY + 2 * inverse * t * controlY + t * t * toY, margin),
        Math.max(margin, (windowRef?.innerHeight ?? 96) - margin),
    );
    const tangentX = 2 * (inverse * (controlX - first.x) + t * (second.x - controlX));
    const tangentY = 2 * (inverse * (controlY - fromY) + t * (toY - controlY));

    return {
        x,
        y,
        scale,
        rotate: Math.sin(Math.PI * t) * Math.max(-4, Math.min(4, (tangentY / Math.max(1, Math.abs(tangentX))) * 1.3)),
        orientation: travel < 0.15 ? first.orientation : travel > 0.85 ? second.orientation : headingFor(tangentX * scrollDirection),
        waypoint: progress > 0.72 ? second.id : first.id,
        resting: false,
    };
}

function restingTarget(point: FlightPoint): FlightTarget {
    return {
        x: point.x,
        y: Math.min(Math.max(point.y - (windowRef?.scrollY ?? 0), 42), Math.max(42, (windowRef?.innerHeight ?? 84) - 42)),
        scale: 1,
        rotate: 0,
        orientation: point.orientation,
        waypoint: point.id,
        resting: true,
    };
}

function updateTarget() {
    if (!windowRef || points.length === 0 || reducedMotion()) return;
    const scroll = windowRef.scrollY;

    if (scroll <= points[0].scrollY || points.length === 1) {
        target = restingTarget(points[0]);
    } else {
        let index = points.length - 1;
        for (let i = 0; i < points.length - 1; i++) {
            if (scroll <= points[i + 1].scrollY) {
                index = i;
                break;
            }
        }
        if (index === points.length - 1) {
            target = restingTarget(points[index]);
        } else {
            const first = points[index],
                second = points[index + 1];
            const span = Math.max(1, second.scrollY - first.scrollY);
            target = positionOnPath(first, second, Math.max(0, Math.min(1, (scroll - first.scrollY) / span)));
        }
    }

    activeWaypoint.value = target.waypoint;
    orientation.value = target.orientation;
    flightState.value = target.resting ? 'resting' : 'traveling';
    startFlightFrame();
}

function startFlightFrame() {
    if (!windowRef || flightFrame || !target || reducedMotion()) return;
    let lastTime = windowRef.performance.now();
    const step = (now: number) => {
        flightFrame = 0;
        if (!target || !guided.value || reducedMotion()) return;
        const current = position.value,
            delta = Math.min(40, now - lastTime),
            easing = 1 - Math.exp(-delta / (target.resting ? 85 : 65));
        lastTime = now;
        const next = {
            x: current.x + (target.x - current.x) * easing,
            y: current.y + (target.y - current.y) * easing,
            scale: current.scale + (target.scale - current.scale) * easing,
            rotate: current.rotate + (target.rotate - current.rotate) * easing,
        };
        position.value = next;
        const settled = Math.abs(target.x - next.x) < 0.15 && Math.abs(target.y - next.y) < 0.15 && Math.abs(target.scale - next.scale) < 0.002;
        if (!settled) flightFrame = windowRef!.requestAnimationFrame(step);
        else position.value = { x: target.x, y: target.y, scale: target.scale, rotate: target.rotate };
    };
    flightFrame = windowRef.requestAnimationFrame(step);
}

function onScroll() {
    if (!guided.value || reducedMotion()) return;
    const scroll = windowRef?.scrollY ?? 0;
    if (Math.abs(scroll - previousScroll) > 1) scrollDirection = Math.sign(scroll - previousScroll);
    previousScroll = scroll;
    scheduleRefresh(false);
}

function bindCoordinator() {
    if (!windowRef || coordinatorBound || !content.value) return;
    coordinatorBound = true;
    windowRef.addEventListener('scroll', onScroll, { passive: true, signal: cancellation.signal });
    windowRef.addEventListener('resize', () => scheduleRefresh(), { passive: true, signal: cancellation.signal });
    mutationObserver = new MutationObserver((records) => {
        if (
            records.some(
                (record) => record.type === 'attributes' || [...record.addedNodes, ...record.removedNodes].some((node) => node.nodeType === 1),
            )
        )
            scheduleRefresh();
    });
    mutationObserver.observe(content.value, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'hidden', 'inert'],
    });
}

function onMotionPreference() {
    if (phase.value === 'loading') return;
    if (reducedMotion()) {
        guided.value = false;
        if (windowRef && flightFrame) windowRef.cancelAnimationFrame(flightFrame);
        flightFrame = 0;
        target = undefined;
        land();
        orientation.value = 'FRONT';
        return;
    }
    if (phase.value !== 'landed') return;
    guided.value = true;
    bindCoordinator();
    geometryDirty = true;
    refreshPoints();
    updateTarget();
}

function land() {
    if (!root.value || !ready.value) return;
    const first = discoverWeddingWaypoints(root.value).find((point) => point.id === 'section:invitation');
    if (!first) return;
    const bounds = root.value.getBoundingClientRect();
    const point = readPoints().find((point) => point.id === first.id);
    if (!point) return;
    const reduced = reducedMotion();
    const blossomX = point.x + (point.orientation === 'RIGHT' ? billOffset : -billOffset) - bounds.left;
    const blossomY = point.y - birdYOffset - (windowRef?.scrollY ?? 0) - bounds.top;
    position.value = reduced
        ? { x: blossomX, y: blossomY - 25, scale: 1, rotate: 0 }
        : { x: point.x - bounds.left, y: point.y - (windowRef?.scrollY ?? 0) - bounds.top, scale: 1, rotate: 0 };
    if (phase.value === 'landed' && !guided.value) orientation.value = reduced ? 'FRONT' : point.orientation;
}

async function imageReady(image: HTMLImageElement) {
    if (!image.complete)
        await new Promise<void>((resolve) => {
            const done = () => {
                image.removeEventListener('load', done);
                image.removeEventListener('error', done);
                cancellation.signal.removeEventListener('abort', done);
                resolve();
            };
            image.addEventListener('load', done, { once: true });
            image.addEventListener('error', done, { once: true });
            cancellation.signal.addEventListener('abort', done, { once: true });
        });
    if (!cancellation.signal.aborted) await image.decode?.().catch(() => undefined);
}

onMounted(async () => {
    const element = root.value,
        win = element?.ownerDocument.defaultView;
    if (!element || !win) return;
    windowRef = win;
    motionPreference = win.matchMedia('(prefers-reduced-motion: reduce)');
    motionPreference.addEventListener('change', onMotionPreference, { signal: cancellation.signal });
    enhancing.value = true;
    position.value = {
        x: element.clientWidth / 2 - (props.hold ? Math.min(180, element.clientWidth * 0.34) : 0),
        y: Math.min(win.innerHeight, element.clientHeight) / 2 - 20,
        scale: 1,
        rotate: 0,
    };
    labelTop.value = position.value.y + 54;
    win.addEventListener(
        'resize',
        () => {
            if (props.hold && phase.value === 'loading') {
                position.value = {
                    ...position.value,
                    x: element.clientWidth / 2 - Math.min(180, element.clientWidth * 0.34),
                    y: win.innerHeight / 2 - 20,
                };
            }
        },
        { signal: cancellation.signal },
    );
    await nextTick();
    const images = [...content.value!.querySelectorAll<HTMLImageElement>('img')].filter((image) => {
        const bounds = image.getBoundingClientRect();
        return image.loading === 'eager' || (bounds.top < win.innerHeight && bounds.bottom > 0);
    });
    // An opening owns the entrance: published data is already resolved. Its Open action never waits for network photos/fonts.
    if (!props.opening) await Promise.all([element.ownerDocument.fonts.ready, ...images.map(imageReady)]);
    if (cancellation.signal.aborted) return;
    await new Promise<void>((resolve) => win.requestAnimationFrame(() => win.requestAnimationFrame(() => resolve())));
    if (cancellation.signal.aborted) return;
    ready.value = true;
    await nextTick();
    emit('ready');
    arrive();
});

function arrive() {
    const element = root.value,
        win = windowRef;
    if (!element || !win || props.hold || !ready.value || phase.value !== 'loading') return;
    phase.value = 'landing';
    const departureX = position.value.x;
    land();
    orientation.value = Math.abs(position.value.x - departureX) < 8 ? 'FRONT' : position.value.x < departureX ? 'LEFT' : 'RIGHT';
    const resting = () => {
        phase.value = 'landed';
        // Convert the current absolute placement before enabling fixed guidance: same DOM bird, no jump.
        if (!reducedMotion()) {
            const bounds = element.getBoundingClientRect();
            position.value = { ...position.value, x: position.value.x + bounds.left, y: position.value.y + bounds.top };
        }
        guided.value = !reducedMotion();
        orientation.value = reducedMotion() ? 'FRONT' : (readPoints()[0]?.orientation ?? 'FRONT');
        geometryDirty = true;
        refreshPoints();
        if (guided.value) {
            bindCoordinator();
            updateTarget();
        }
    };
    if (reducedMotion()) resting();
    else landingTimer = setTimeout(resting, 760);
    observer = new ResizeObserver(() => {
        if (guided.value) scheduleRefresh();
        else {
            land();
            scheduleRefresh();
        }
    });
    observer.observe(content.value!);
    const hash = win.location.hash.slice(1);
    if (hash) element.ownerDocument.getElementById(hash)?.scrollIntoView({ behavior: 'instant' });
}

watch(
    () => props.hold,
    () => {
        void nextTick(arrive);
    },
);

onBeforeUnmount(() => {
    cancellation.abort();
    observer?.disconnect();
    mutationObserver?.disconnect();
    clearTimeout(landingTimer);
    if (windowRef && flightFrame) windowRef.cancelAnimationFrame(flightFrame);
    if (windowRef && measureFrame) windowRef.cancelAnimationFrame(measureFrame);
});
</script>

<template>
    <div
        ref="root"
        class="ee-loading-boundary"
        :data-ee-phase="phase"
        :data-opening="props.hold"
        :data-intro-entry="props.opening"
        :aria-busy="!props.opening && enhancing && !ready"
    >
        <div
            ref="content"
            class="ee-loaded-content"
            :data-ready="!props.hold && (props.opening || !enhancing || ready)"
            :inert="props.hold || (!props.opening && enhancing && !ready)"
        >
            <slot />
        </div>
        <slot name="opening" />
        <div v-if="enhancing" class="ee-loading-veil" :data-ready="ready" :aria-hidden="ready || undefined">
            <p role="status" class="ee-loading-label" :style="{ top: `${labelTop}px` }">Preparing your invitation…</p>
        </div>
        <div
            v-if="enhancing"
            class="ee-loading-bird"
            :data-guided="guided"
            :data-flight-state="flightState"
            :data-waypoint-id="activeWaypoint"
            :style="birdStyle"
        >
            <EnchantedHummingbird :orientation="orientation" />
        </div>
    </div>
</template>
