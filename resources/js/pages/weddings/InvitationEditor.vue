<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import ModernClassicInvitation from '@/components/wedding/ModernClassicInvitation.vue';
import WeddingNavigation from '@/components/wedding/WeddingNavigation.vue';
import { useAppearance } from '@/composables/useAppearance';
import type { AccountSummary, InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from '@/types';

const props = defineProps<{
    account: AccountSummary;
    weddingId: number;
    wedding: WeddingPresentation;
    customization: InvitationCustomization;
    draftVersion: number;
    heroPhoto: InvitationHeroPhoto | null;
}>();

const parameters = computed(() => ({ account: props.account.id, wedding: props.weddingId }));
const { appearance, updateAppearance } = useAppearance();
const form = useForm({
    content: { ...props.customization, sections: { ...props.customization.sections } },
    draft_version: props.draftVersion,
    hero_photo_action: 'keep' as 'keep' | 'replace' | 'remove',
    hero_photo: null as File | null,
});
const errors = computed(() => form.errors as Record<string, string | undefined>);
const saved = ref(false);
const photoInput = ref<HTMLInputElement | null>(null);
const localPhotoUrl = ref<string | null>(null);
const photoError = ref('');
const removeDialogOpen = ref(false);
const unavailablePhoto = ref(false);
const previewPhoto = computed(() =>
    form.hero_photo_action === 'remove' ? null : localPhotoUrl.value ? { url: localPhotoUrl.value } : props.heroPhoto,
);
watch(
    () => previewPhoto.value?.url,
    () => {
        unavailablePhoto.value = false;
    },
);

function releaseLocalPhoto() {
    if (localPhotoUrl.value) URL.revokeObjectURL(localPhotoUrl.value);
    localPhotoUrl.value = null;
}

function selectPhoto(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    photoError.value = '';
    form.clearErrors('hero_photo');
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        photoError.value = 'Choose a JPEG, PNG, or WebP photo up to 5 MiB.';
        if (photoInput.value) photoInput.value.value = '';
        return;
    }
    releaseLocalPhoto();
    form.hero_photo = file;
    form.hero_photo_action = 'replace';
    localPhotoUrl.value = URL.createObjectURL(file);
    saved.value = false;
}

function undoPhotoChange() {
    releaseLocalPhoto();
    form.hero_photo = null;
    form.hero_photo_action = 'keep';
    photoError.value = '';
    form.clearErrors('hero_photo');
    if (photoInput.value) photoInput.value.value = '';
}

function removePhoto() {
    undoPhotoChange();
    form.hero_photo_action = 'remove';
    removeDialogOpen.value = false;
    saved.value = false;
}

onBeforeUnmount(releaseLocalPhoto);
const sections = [
    { key: 'ceremony', label: 'Ceremony details', description: 'Show your ceremony venue.' },
    { key: 'reception', label: 'Reception details', description: 'Show your reception venue.' },
    { key: 'closing', label: 'Closing message', description: 'End with your personal sign-off.' },
] as const;

function saveDraft() {
    saved.value = false;
    form.transform((data) => ({ ...data, _method: 'put', content: JSON.stringify(data.content) })).post(
        route('accounts.weddings.invitation.update', parameters.value),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.content = { ...props.customization, sections: { ...props.customization.sections } };
                form.draft_version = props.draftVersion;
                undoPhotoChange();
                form.defaults();
                saved.value = true;
            },
        },
    );
}
</script>

<template>
    <div class="invitation-editor min-h-screen bg-background text-foreground">
        <Head title="Customize invitation" />

        <header class="border-b px-4 py-3 sm:px-8">
            <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-3">
                <WeddingNavigation :account-id="account.id" :wedding-id="weddingId" active="invitation" />
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <label for="editor-theme">Theme</label>
                    <select
                        id="editor-theme"
                        :value="appearance"
                        class="editor-input min-h-10 w-auto rounded-md px-2 py-1 text-sm"
                        @change="updateAppearance(($event.target as HTMLSelectElement).value as 'light' | 'dark' | 'system')"
                    >
                        <option value="system">System</option>
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1440px] px-4 py-8 sm:px-8 sm:py-10">
            <header class="flex flex-col gap-4 border-b pb-8 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="font-mono text-xs uppercase tracking-widest text-muted-foreground">Invitation / Draft</p>
                    <h1 class="mt-3 text-3xl font-medium tracking-tight sm:text-4xl">Make it yours.</h1>
                    <p class="mt-3 break-words text-sm text-muted-foreground">
                        {{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }} · Modern Classic
                    </p>
                </div>
                <Link :href="route('accounts.weddings.edit', parameters)" class="editor-link self-start text-sm">Edit wedding details</Link>
            </header>

            <div class="grid min-w-0 gap-10 pt-8 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)] lg:gap-12">
                <form class="min-w-0" :aria-busy="form.processing" @submit.prevent="saveDraft">
                    <fieldset :disabled="form.processing" class="flex min-w-0 flex-col gap-8">
                        <legend class="sr-only">Invitation customization</legend>
                        <section aria-labelledby="photo-heading" class="flex flex-col gap-4">
                            <div>
                                <h2 id="photo-heading" class="text-lg font-medium">Your photo</h2>
                                <p id="photo-help" class="mt-1 text-sm text-muted-foreground">
                                    Optional JPEG, PNG, or WebP. Up to 5 MiB, 12 million pixels, and 6,000 pixels per side. HEIC is not supported.
                                </p>
                            </div>
                            <div v-if="previewPhoto" class="flex flex-col gap-2">
                                <img
                                    v-if="!unavailablePhoto"
                                    :key="previewPhoto.url"
                                    :src="previewPhoto.url"
                                    alt="Selected wedding photo"
                                    class="max-h-48 max-w-full self-start rounded-md border object-contain"
                                    @error="unavailablePhoto = true"
                                />
                                <p v-else role="status" class="text-sm">This photo is unavailable. Choose a replacement or remove it.</p>
                            </div>
                            <label for="hero-photo" class="text-sm font-medium">{{ previewPhoto ? 'Replace photo' : 'Choose photo' }}</label>
                            <input
                                id="hero-photo"
                                ref="photoInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="editor-input w-full min-w-0 rounded-md p-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-foreground file:px-3 file:py-2 file:text-background"
                                :aria-invalid="Boolean(photoError || errors.hero_photo)"
                                aria-describedby="photo-help photo-error photo-privacy"
                                @change="selectPhoto"
                            />
                            <p id="photo-error" role="alert" class="text-sm">{{ photoError || errors.hero_photo || errors.hero_photo_action }}</p>
                            <p id="photo-privacy" class="text-xs leading-relaxed text-muted-foreground">
                                Changes stay on this device until Save Draft. Saved photos are private. We optimize them and discard original files
                                and location metadata; animation is not retained.
                            </p>
                            <div class="flex flex-wrap gap-3">
                                <Dialog v-model:open="removeDialogOpen">
                                    <DialogTrigger v-if="previewPhoto || form.hero_photo_action === 'remove'" as-child
                                        ><Button type="button" variant="outline">Remove photo</Button></DialogTrigger
                                    >
                                    <DialogContent
                                        class="photo-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-2xl border bg-background p-6 text-foreground"
                                        @pointer-down-outside.prevent
                                    >
                                        <DialogHeader>
                                            <DialogTitle>Remove this photo?</DialogTitle>
                                            <DialogDescription
                                                >The photo will disappear from your draft when you select Save Draft. You can undo this change before
                                                saving.</DialogDescription
                                            >
                                        </DialogHeader>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" @click="removeDialogOpen = false">Cancel</Button>
                                            <Button type="button" @click="removePhoto">Remove photo</Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                                <Button v-if="form.hero_photo_action !== 'keep'" type="button" variant="outline" @click="undoPhotoChange"
                                    >Undo photo change</Button
                                >
                            </div>
                            <p v-if="form.hero_photo_action === 'remove'" class="text-sm" role="status">Photo will be removed when you save.</p>
                        </section>
                        <section aria-labelledby="messages-heading" class="flex flex-col gap-5">
                            <div>
                                <h2 id="messages-heading" class="text-lg font-medium">Your words</h2>
                                <p class="mt-1 text-sm text-muted-foreground">A few words to welcome the people you love.</p>
                            </div>
                            <div v-for="field in ['introduction_message', 'closing_message'] as const" :key="field" class="flex flex-col gap-2">
                                <label :for="field" class="text-sm font-medium">
                                    {{ field === 'introduction_message' ? 'Introduction message' : 'Closing message' }}
                                </label>
                                <textarea
                                    :id="field"
                                    v-model="form.content[field]"
                                    required
                                    maxlength="500"
                                    rows="3"
                                    class="editor-input w-full resize-y rounded-md px-3 py-2.5 text-sm leading-relaxed"
                                    :aria-invalid="Boolean(errors[`content.${field}`])"
                                    :aria-describedby="`${field}-help ${field}-error`"
                                />
                                <p :id="`${field}-help`" class="flex justify-between gap-3 text-xs text-muted-foreground">
                                    <span>Plain text. Line breaks are welcome.</span>
                                    <span class="shrink-0 font-mono">{{ form.content[field].length }}/500</span>
                                </p>
                                <p :id="`${field}-error`" class="text-sm" role="alert">{{ errors[`content.${field}`] }}</p>
                            </div>
                        </section>

                        <section aria-labelledby="sections-heading" class="border-t pt-7">
                            <h2 id="sections-heading" class="text-lg font-medium">What to include</h2>
                            <p class="mt-1 text-sm text-muted-foreground">Hidden details stay saved for later.</p>
                            <div class="mt-4 divide-y border-y">
                                <div v-for="section in sections" :key="section.key" class="py-1">
                                    <label class="flex min-h-16 cursor-pointer items-center justify-between gap-4 py-3">
                                        <span>
                                            <span class="block text-sm font-medium">{{ section.label }}</span>
                                            <span class="mt-1 block text-xs text-muted-foreground">{{ section.description }}</span>
                                        </span>
                                        <input
                                            v-model="form.content.sections[section.key]"
                                            type="checkbox"
                                            class="editor-checkbox h-5 w-5 shrink-0 rounded"
                                            :aria-invalid="Boolean(errors[`content.sections.${section.key}`])"
                                            :aria-describedby="`${section.key}-error`"
                                        />
                                    </label>
                                    <p :id="`${section.key}-error`" role="alert" class="text-sm">{{ errors[`content.sections.${section.key}`] }}</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs leading-relaxed text-muted-foreground">
                                Your names, invitation heading, and celebration date stay visible.
                            </p>
                        </section>
                    </fieldset>

                    <div v-if="form.hasErrors" role="alert" class="mt-6 border-l-2 border-foreground pl-3 text-sm">
                        <p class="font-medium">Your draft could not be saved.</p>
                        <p v-if="errors.draft_version" class="mt-1">{{ errors.draft_version }}</p>
                        <p v-else class="mt-1">Check the highlighted fields and try again.</p>
                    </div>
                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Draft' }}</Button>
                        <p role="status" aria-live="polite" class="text-xs text-muted-foreground">
                            <template v-if="form.processing">{{
                                form.progress?.percentage !== undefined && form.progress.percentage < 100
                                    ? `Uploading ${form.progress.percentage}%…`
                                    : 'Saving your draft…'
                            }}</template>
                            <template v-else-if="form.isDirty">Unsaved changes</template>
                            <template v-else-if="saved">Draft saved</template>
                            <template v-else>{{ draftVersion ? 'Saved draft' : 'Using template defaults' }}</template>
                        </p>
                    </div>
                    <p class="mt-3 text-xs leading-relaxed text-muted-foreground">
                        Save before leaving. Your draft is private and has not been published.
                    </p>
                </form>

                <section aria-labelledby="preview-heading" class="min-w-0">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h2 id="preview-heading" class="font-mono text-xs uppercase tracking-widest text-muted-foreground">Live preview</h2>
                        <a
                            :href="route('accounts.weddings.preview', parameters)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="editor-link text-xs"
                            >View saved draft <span class="sr-only">(opens in a new tab)</span><span aria-hidden="true">↗</span></a
                        >
                    </div>
                    <div class="overflow-hidden rounded-xl border">
                        <ModernClassicInvitation :wedding="wedding" :customization="form.content" :hero-photo="previewPhoto" />
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>

<style scoped>
.invitation-editor {
    font-family: Geist, var(--font-sans);
}

.invitation-editor .font-mono {
    font-family: 'Geist Mono', ui-monospace, monospace;
}

.editor-input {
    border: 1px solid hsl(var(--input));
    color: hsl(var(--foreground));
    background: hsl(var(--muted));
    color-scheme: light;
}

:global(.dark) .editor-input {
    color-scheme: dark;
}

.editor-link {
    display: inline-flex;
    min-height: 2.5rem;
    align-items: center;
    gap: 0.3rem;
    border-radius: 0.375rem;
    text-underline-offset: 4px;
}

.editor-link:hover {
    text-decoration: underline;
}

.editor-checkbox {
    accent-color: hsl(var(--foreground));
}

.invitation-editor :focus-visible {
    outline: 2px solid hsl(var(--foreground));
    outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
    .invitation-editor :deep(*) {
        transition: none;
        animation: none;
    }

    :global(body:has(.photo-dialog) [data-state]) {
        animation: none !important;
        transition: none !important;
    }
}
</style>
