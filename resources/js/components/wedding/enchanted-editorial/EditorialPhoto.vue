<script setup lang="ts">
import type { EditorialPhoto } from '@/types/enchanted-editorial';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ photo: EditorialPhoto | null; eager?: boolean; fit?: 'cover' | 'contain' }>(), {
    eager: false,
    fit: 'cover',
});
const failed = ref(false);
const imageElement = ref<HTMLImageElement | null>(null);
const element = ref<HTMLElement | null>(null);
const bounds = ref({ width: 0, height: 0 });
let observer: ResizeObserver | undefined;
const cropStyle = computed(() => {
    if (!props.photo?.crop || (props.photo.frame && props.photo.crop === 'natural') || !bounds.value.width || !bounds.value.height)
        return { width: '100%', height: '100%' };
    const ratios = { natural: props.photo.width / props.photo.height, portrait: 3 / 4, landscape: 4 / 3, square: 1 };
    const width = Math.min(bounds.value.width, bounds.value.height * ratios[props.photo.crop]);
    return {
        width: width + 'px',
        height: width / ratios[props.photo.crop] + 'px',
        position: 'absolute' as const,
        left: '50%',
        top: '50%',
        transform: 'translate(-50%, -50%)',
    };
});
onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        bounds.value = { width: entry.contentRect.width, height: entry.contentRect.height };
    });
    if (element.value) observer.observe(element.value);
    if (imageElement.value?.complete && imageElement.value.naturalWidth === 0) failed.value = true;
});
onBeforeUnmount(() => observer?.disconnect());
watch(element, (current, previous) => {
    if (previous) observer?.unobserve(previous);
    if (current) observer?.observe(current);
});
watch(
    () => props.photo?.url,
    () => (failed.value = false),
);
const positions = { center: '50% 50%', top: '50% 0%', upper: '50% 30%', lower: '50% 70%' } as const;
const focalPositions = { center: '50% 50%', top: '50% 0%', bottom: '50% 100%', left: '0% 50%', right: '100% 50%' } as const;
</script>

<template>
    <div v-if="photo" ref="element" class="ee-photo">
        <div v-if="photo && !failed" :style="cropStyle">
            <img
                ref="imageElement"
                v-if="photo && !failed"
                :src="photo.url"
                :alt="photo.alt"
                :width="photo.width"
                :height="photo.height"
                :style="{
                    objectPosition: photo.focal ? focalPositions[photo.focal] : positions[photo.focalPoint],
                    objectFit: photo.frame === 'fit' ? 'contain' : photo.frame === 'fill' || photo.crop ? 'cover' : fit,
                }"
                :loading="eager ? 'eager' : 'lazy'"
                :fetchpriority="eager ? 'high' : 'auto'"
                decoding="async"
                @error="failed = true"
            />
        </div>
        <div v-else class="ee-photo-fallback" role="img" aria-label="Couple photograph unavailable">
            <span aria-hidden="true">✧</span>
            <span>A moment to remember</span>
        </div>
    </div>
</template>
