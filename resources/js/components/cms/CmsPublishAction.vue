<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { editorRequest } from '@/lib/cms-editor';
import type { WeddingPublicationSummary } from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    publication: WeddingPublicationSummary;
    endpoint: string;
    csrfToken: string;
    enabled: boolean;
    unsaved: boolean;
}>();
const emit = defineEmits<{ published: [WeddingPublicationSummary]; review: [] }>();
const open = ref(false),
    processing = ref(false),
    error = ref('');
async function publish() {
    if (props.unsaved || processing.value || !props.enabled) return;
    processing.value = true;
    error.value = '';
    try {
        const result = await editorRequest(props.endpoint, props.csrfToken, {
            draft_version: props.publication.draftVersion,
            publication_version: props.publication.publicationVersion,
            snapshot_token: props.publication.snapshotToken,
        });
        const updated = result.publication as WeddingPublicationSummary | undefined;
        if (!updated?.published || updated.hasChanges)
            throw new Error('Publication could not be confirmed. Review the saved version before retrying.');
        emit('published', updated);
        open.value = false;
    } catch (failure) {
        const issue = failure as Error & { errors?: Record<string, string[]> };
        error.value =
            Object.values(issue.errors ?? {})
                .flat()
                .join(' ') ||
            'Publication could not be confirmed. Your edits are preserved. Review the saved version and publication status before retrying.';
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            class="cms-primary"
            :disabled="
                !enabled ||
                unsaved ||
                processing ||
                !publication.draftSaved ||
                !publication.supportedDraft ||
                (publication.published && !publication.hasChanges)
            "
            :title="!enabled ? 'Publishing is unavailable in this environment.' : unsaved ? 'Save your changes before publishing.' : undefined"
            @click="
                open = true;
                error = '';
            "
        >
            {{ processing ? 'Publishing…' : publication.published ? (publication.hasChanges || unsaved ? 'Republish' : 'Published') : 'Publish' }}
        </button>
        <a v-if="publication.published" :href="publication.publicUrl" target="_blank" rel="noopener" class="cms-secondary">View published website</a>
        <Dialog v-model:open="open">
            <DialogContent
                class="cms-confirm"
                @escape-key-down="processing && $event.preventDefault()"
                @interact-outside="processing && $event.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>{{ publication.published ? 'Republish your website?' : 'Publish your website?' }}</DialogTitle>
                    <DialogDescription
                        >Anyone with the link can view your saved names, wedding details, messages and visible photographs. Later edits stay private
                        until you republish.</DialogDescription
                    >
                </DialogHeader>
                <p v-if="unsaved" class="cms-feedback" data-kind="info">
                    Save your changes before publishing. Keep editing, then save and review again.
                </p>
                <p v-if="error" role="alert" class="cms-feedback" data-kind="error">{{ error }}</p>
                <button
                    v-if="error"
                    class="cms-secondary"
                    :disabled="processing"
                    @click="
                        open = false;
                        emit('review');
                    "
                >
                    Review saved version
                </button>
                <div class="flex flex-wrap gap-2">
                    <button class="cms-secondary" :disabled="processing" @click="open = false">Keep editing</button>
                    <button class="cms-primary" :disabled="unsaved || processing || !enabled" @click="publish">
                        {{ processing ? 'Publishing…' : publication.published ? 'Confirm Republish' : 'Confirm Publish' }}
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
