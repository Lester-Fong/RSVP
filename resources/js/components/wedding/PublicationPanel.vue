<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { WeddingPublicationSummary } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ accountId: number; weddingId: number; publication: WeddingPublicationSummary }>();
const publishOpen = ref(false);
const unpublishOpen = ref(false);
const urlInput = ref<HTMLInputElement | null>(null);
const heading = ref<HTMLHeadingElement | null>(null);
const copyMessage = ref('');
const form = useForm({
    draft_version: props.publication.draftVersion,
    publication_version: props.publication.publicationVersion,
    snapshot_token: props.publication.snapshotToken,
});
const unpublishForm = useForm({ publication_version: props.publication.publicationVersion });
const parameters = computed(() => ({ account: props.accountId, wedding: props.weddingId }));
const errors = computed(() => [...Object.values(form.errors), ...Object.values(unpublishForm.errors)]);
const publishedAt = computed(() =>
    props.publication.publishedAt
        ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.publication.publishedAt))
        : null,
);
watch(
    () => props.publication,
    (publication) => {
        form.draft_version = publication.draftVersion;
        form.publication_version = publication.publicationVersion;
        form.snapshot_token = publication.snapshotToken;
        unpublishForm.publication_version = publication.publicationVersion;
        copyMessage.value = '';
    },
);

function publish() {
    form.post(route('accounts.weddings.publish', parameters.value), {
        preserveScroll: true,
        onSuccess: () => {
            publishOpen.value = false;
        },
    });
}

function unpublish() {
    unpublishForm.post(route('accounts.weddings.unpublish', parameters.value), {
        preserveScroll: true,
        onSuccess: () => {
            unpublishOpen.value = false;
        },
    });
}

async function copyUrl() {
    try {
        await navigator.clipboard.writeText(props.publication.publicUrl);
        copyMessage.value = 'Public URL copied.';
    } catch {
        urlInput.value?.focus();
        urlInput.value?.select();
        copyMessage.value = 'Select and copy the URL above.';
    }
}

function restorePublishFocus(event: Event) {
    if (props.publication.published && !props.publication.hasChanges) {
        event.preventDefault();
        urlInput.value?.focus();
    }
}

function restoreUnpublishFocus(event: Event) {
    event.preventDefault();
    if (!props.publication.published) heading.value?.focus();
    else document.getElementById('website-actions')?.focus();
}
</script>

<template>
    <section aria-labelledby="publication-heading" class="min-w-0 border-y py-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="font-mono text-xs uppercase tracking-widest text-muted-foreground">Invitation website</p>
                <h2
                    id="publication-heading"
                    ref="heading"
                    tabindex="-1"
                    class="mt-2 rounded-sm text-lg font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    {{ publication.published ? 'Published' : 'Draft' }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        publication.published
                            ? publication.hasChanges
                                ? 'Your saved draft has unpublished changes.'
                                : 'Your published invitation matches the saved draft.'
                            : 'Your invitation is private until you publish it.'
                    }}
                </p>
                <p v-if="publishedAt" class="mt-2 text-xs text-muted-foreground">Last published {{ publishedAt }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Dialog v-model:open="publishOpen">
                    <DialogTrigger v-if="!publication.published || publication.hasChanges" as-child>
                        <Button :disabled="!publication.draftSaved || !publication.supportedDraft" @click="form.clearErrors()">
                            {{ publication.published ? 'Republish changes' : 'Publish invitation' }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent
                        overlay-class="bg-black/30 backdrop-blur-sm"
                        class="publication-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-2xl"
                        @close-auto-focus="restorePublishFocus"
                        @escape-key-down="form.processing && $event.preventDefault()"
                        @interact-outside="form.processing && $event.preventDefault()"
                    >
                        <DialogHeader>
                            <DialogTitle>{{ publication.published ? 'Republish your invitation?' : 'Publish your invitation?' }}</DialogTitle>
                            <DialogDescription>
                                Anyone with the link can view your published names, wedding date, venues, messages and photo. Only your saved draft is
                                published. Later draft edits stay private until you republish. Search engines are asked not to index the page.
                            </DialogDescription>
                        </DialogHeader>
                        <div v-if="Object.keys(form.errors).length" role="alert" class="space-y-2 text-sm">
                            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" :disabled="form.processing" @click="publishOpen = false">Cancel</Button>
                            <Button type="button" :disabled="form.processing" @click="publish">{{
                                form.processing ? 'Publishing…' : publication.published ? 'Republish' : 'Publish now'
                            }}</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <DropdownMenu v-if="publication.published">
                    <DropdownMenuTrigger as-child
                        ><Button id="website-actions" variant="outline"
                            >Website actions <ChevronDown class="ml-2 h-4 w-4" aria-hidden="true" /></Button
                    ></DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        class="motion-reduce:!animate-none motion-reduce:!transition-none"
                        @close-auto-focus="unpublishOpen && $event.preventDefault()"
                    >
                        <DropdownMenuItem
                            @select="
                                unpublishForm.clearErrors();
                                unpublishOpen = true;
                            "
                            >Unpublish invitation</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
                <Dialog v-model:open="unpublishOpen">
                    <DialogContent
                        overlay-class="bg-black/30 backdrop-blur-sm"
                        class="publication-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-2xl"
                        @close-auto-focus="restoreUnpublishFocus"
                        @escape-key-down="unpublishForm.processing && $event.preventDefault()"
                        @interact-outside="unpublishForm.processing && $event.preventDefault()"
                    >
                        <DialogHeader>
                            <DialogTitle>Unpublish your invitation?</DialogTitle>
                            <DialogDescription
                                >The public page and photo will become unavailable. Your wedding, saved draft and previous published versions will be
                                kept. Copies already downloaded or cached by other services cannot be recalled.</DialogDescription
                            >
                        </DialogHeader>
                        <div v-if="Object.keys(unpublishForm.errors).length" role="alert" class="space-y-2 text-sm">
                            <p v-for="(error, key) in unpublishForm.errors" :key="key">{{ error }}</p>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" :disabled="unpublishForm.processing" @click="unpublishOpen = false"
                                >Cancel</Button
                            >
                            <Button type="button" :disabled="unpublishForm.processing" @click="unpublish">{{
                                unpublishForm.processing ? 'Unpublishing…' : 'Unpublish invitation'
                            }}</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
        <p v-if="!publication.draftSaved" class="mt-3 text-sm">Customize your invitation and save the draft before publishing.</p>
        <p v-if="publication.cmsV2" class="mt-3 rounded-md bg-muted p-3 text-sm">
            {{
                publication.supportedDraft
                    ? 'Enchanted publishes your saved website only. Guests respond through their own private invitation links; the public website stays informational.'
                    : 'Enchanted publication is unavailable in this environment. Your saved website is preserved.'
            }}
        </p>
        <p v-else-if="!publication.supportedDraft" class="mt-3 text-sm">
            This draft uses an unsupported format. Please contact support before publishing.
        </p>
        <div v-if="publication.published" class="mt-5 space-y-2">
            <label for="public-wedding-url" class="text-sm font-medium">Public wedding URL</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input
                    id="public-wedding-url"
                    ref="urlInput"
                    :value="publication.publicUrl"
                    readonly
                    class="min-h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                />
                <Button variant="outline" @click="copyUrl">Copy public URL</Button>
                <Button as-child variant="outline"
                    ><a :href="publication.publicUrl" target="_blank" rel="noopener noreferrer">View public invitation</a></Button
                >
            </div>
            <p aria-live="polite" class="text-sm">{{ copyMessage }}</p>
        </div>
        <div v-if="errors.length && !publishOpen && !unpublishOpen" role="alert" class="mt-4 space-y-2 text-sm">
            <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
        </div>
    </section>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    :deep(*),
    :global(body:has(.publication-dialog) [data-state]) {
        animation: none !important;
        transition: none !important;
    }
}
</style>
