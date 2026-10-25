<script setup lang="ts">
import EnchantedEditorialInvitation from '@/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue';
import type { CmsSectionType } from '@/types/cms-v2';
import type { EnchantedEditorialPresentation } from '@/types/enchanted-editorial';
import { createApp, h, nextTick, onBeforeUnmount, onMounted, ref, watch, type App } from 'vue';
import sectionStyles from '../../../css/enchanted-editorial-sections.css?inline';
import styles from '../../../css/enchanted-editorial.css?inline';
import loadingStyles from '../../../css/enchanted-loading.css?inline';
const props = defineProps<{ presentation: EnchantedEditorialPresentation; activeSection?: CmsSectionType; revealKey?: string }>();
const frame = ref<HTMLIFrameElement | null>(null);
let app: App | undefined;
let mounting = false,
    disposed = false;
let lastSection: string | null = null;
async function reveal() {
    await nextTick();
    const ids = {
        welcome: 'invitation',
        event_details: 'wedding-details',
        countdown: 'countdown',
        story: 'our-story',
        gallery: 'our-photos',
        entourage: 'entourage',
        guest_guide: 'guest-guide',
        rsvp_information: 'rsvp',
        closing: 'closing',
    };
    const id = props.activeSection ? ids[props.activeSection] : 'invitation';
    const node = frame.value?.contentDocument?.getElementById(id);
    if (!node) {
        lastSection = null;
        return;
    }
    if (lastSection !== id) {
        const win = frame.value?.contentWindow;
        win?.scrollTo(0, id === 'invitation' ? 0 : Math.max(0, node.getBoundingClientRect().top + win.scrollY - 16));
        lastSection = id;
    }
}
watch(
    () => [props.activeSection, props.presentation],
    () => void reveal(),
    { flush: 'post' },
);
watch(
    () => props.revealKey,
    () => {
        lastSection = null;
        void reveal();
    },
    { flush: 'post' },
);
async function mount() {
    const doc = frame.value?.contentDocument;
    if (!doc || app || mounting || disposed) return;
    mounting = true;
    doc.documentElement.lang = 'en';
    const style = doc.createElement('style');
    style.textContent = 'body{margin:0}html{scroll-behavior:auto!important}' + styles + sectionStyles + loadingStyles;
    doc.head.append(style);
    if (!frame.value?.isConnected || app || disposed) return;
    const root = doc.createElement('div');
    doc.body.append(root);
    app = createApp({ setup: () => () => h(EnchantedEditorialInvitation, { presentation: props.presentation, editorPreview: true }) });
    app.mount(root);
    void reveal();
}
onMounted(mount);
onBeforeUnmount(() => {
    disposed = true;
    app?.unmount();
});
</script>
<template><iframe ref="frame" title="Wedding website preview" class="h-[900px] w-full border-0 bg-[#f9f5ed]" @load="mount" /></template>
