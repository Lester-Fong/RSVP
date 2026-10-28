<script setup lang="ts">
import EnchantedEditorialInvitation from '@/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue';
import { editorPresentation } from '@/lib/cms-editor';
import type { WeddingPresentation } from '@/types';
import type { EditorImage } from '@/types/cms-editor';
import type { CmsSectionType, CmsV2Document } from '@/types/cms-v2';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{
    initialDocument: CmsV2Document;
    wedding: WeddingPresentation;
    timeZone: string;
    initialImages: EditorImage[];
    supported: CmsSectionType[];
    editorUrl: string;
}>();
const presentation = computed(() =>
    editorPresentation(
        props.initialDocument,
        props.wedding,
        props.initialDocument.content.photo_slots ?? { hero: null, details: null, story: null },
        props.initialImages,
        props.supported,
        props.timeZone,
    ),
);
</script>
<template>
    <div>
        <Head title="Private saved wedding draft" />
        <div class="flex flex-wrap items-center justify-between gap-3 border-b bg-background p-4 text-sm text-foreground">
            <p>Private saved draft · not your published website</p>
            <a :href="editorUrl" class="underline underline-offset-4">Return to editor</a>
        </div>
        <EnchantedEditorialInvitation :presentation="presentation" />
    </div>
</template>
