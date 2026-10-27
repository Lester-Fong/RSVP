<script setup lang="ts">
import CmsImageField from '@/components/cms/CmsImageField.vue';
import CmsImagePicker from '@/components/cms/CmsImagePicker.vue';
import CmsPreviewFrame from '@/components/cms/CmsPreviewFrame.vue';
import CmsPublishAction from '@/components/cms/CmsPublishAction.vue';
import EntourageSectionEditor from '@/components/cms/EntourageSectionEditor.vue';
import GallerySectionEditor from '@/components/cms/GallerySectionEditor.vue';
import StorySectionEditor from '@/components/cms/StorySectionEditor.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import CmsEditorLayout from '@/layouts/CmsEditorLayout.vue';
import { editorErrorMessages, editorPresentation, editorRequest, editorSectionLabels as labels, newGuestGuideSection } from '@/lib/cms-editor';
import { guestGuideValidationIssues } from '@/lib/cms-guest-guide';
import { photoboothExperience } from '@/lib/photobooth-opening';
import type { WeddingPublicationSummary } from '@/types';
import type { Editable, EditorImage, EditorProps, EditorSlots, PhotoChoice } from '@/types/cms-editor';
import {
    CMS_GUEST_GUIDE_FAQ_LIMIT,
    CMS_GUEST_GUIDE_SWATCH_LIMIT,
    type CmsGuestGuideSection,
    type CmsImageReference,
    type CmsV2Document,
} from '@/types/cms-v2';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import '../../../css/cms-editor.css';
const props = defineProps<EditorProps>();
const content = reactive<Editable<CmsV2Document>>(JSON.parse(JSON.stringify(props.initialDocument)));
const slots = reactive<EditorSlots>(JSON.parse(JSON.stringify(content.content.photo_slots ?? { hero: null, details: null, story: null })));
const images = ref<EditorImage[]>(props.initialImages);
const draftVersion = ref(props.draftVersion),
    savedSource = ref(JSON.stringify(content)),
    saving = ref(false),
    saveFeedback = ref('');
const feedbackKind = ref<'info' | 'success' | 'error' | 'conflict'>('info');
const errorSummary = ref<HTMLElement | null>(null);
const reloadDialog = ref(false);
const publication = ref(props.publication);
let leaving = false;
const dirty = computed(() => JSON.stringify(content) !== savedSource.value);
watch(
    slots,
    () => {
        content.content.photo_slots = JSON.parse(JSON.stringify(slots));
    },
    { deep: true, flush: 'sync' },
);
const selected = ref(content.content.sections[0]?.id ?? ''),
    view = ref<'edit' | 'preview'>('edit');
const picker = ref(false),
    choice = ref<PhotoChoice | null>(null),
    errors = ref<Record<string, string[]>>({}),
    status = ref('Checking preview content…');

const presentation = computed(() =>
    editorPresentation(content, props.wedding, slots, images.value, props.capabilities[content.template_key] ?? [], props.timeZone),
);
const issues = computed(() =>
    Object.entries(errors.value).flatMap(([path, messages]) => {
        const index = /(?:document\.)?content\.sections\.(\d+)/.exec(path)?.[1];
        const slot = /^(?:slots|content\.photo_slots)\.(hero|details|story)/.exec(path)?.[1];
        const type = slot === 'hero' ? 'welcome' : slot === 'details' ? 'event_details' : slot;
        const section = index !== undefined ? content.content.sections[Number(index)] : content.content.sections.find((s) => s.type === type);
        return editorErrorMessages({ [path]: messages }, content).map((message) => ({ path, message, section }));
    }),
);
async function locateIssue(issue: (typeof issues.value)[number]) {
    if (issue.section) selected.value = issue.section.id;
    view.value = 'edit';
    await nextTick();
    const region = document.querySelector<HTMLElement>('[data-editor-section="' + selected.value + '"]');
    const field = issue.path.replace(/^(?:document\.)?content\.sections\.\d+\./, '');
    const target = fieldTarget(region, field);
    for (let parent = target?.parentElement; parent && parent !== region; parent = parent.parentElement) {
        if (parent instanceof HTMLDetailsElement) parent.open = true;
    }
    const control = target?.matches('input,textarea,select,button') ? target : target?.querySelector<HTMLElement>('input,textarea,select,button');
    (control ?? region?.querySelector<HTMLElement>('input:not([type=checkbox]),textarea,select'))?.focus();
}
function fieldTarget(region: HTMLElement | null, field: string) {
    return [...(region?.querySelectorAll<HTMLElement>('[data-cms-field]') ?? [])]
        .filter((el) => el.dataset.cmsField === field || field.startsWith(el.dataset.cmsField + '.'))
        .sort((a, b) => (b.dataset.cmsField?.length ?? 0) - (a.dataset.cmsField?.length ?? 0))[0];
}
watch(
    issues,
    async () => {
        await nextTick();
        document.querySelectorAll('[data-cms-invalid]').forEach((el) => {
            el.removeAttribute('aria-invalid');
            el.removeAttribute('aria-describedby');
            el.removeAttribute('data-cms-invalid');
        });
        issues.value.forEach((issue, index) => {
            const region = document.querySelector<HTMLElement>('[data-editor-section="' + issue.section?.id + '"]');
            const field = issue.path.replace(/^(?:document\.)?content\.sections\.\d+\./, '');
            const target = fieldTarget(region, field);
            const control = target?.matches('input,textarea,select') ? target : target?.querySelector<HTMLElement>('input,textarea,select');
            if (control) {
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', 'cms-error-' + index);
                control.setAttribute('data-cms-invalid', '');
            }
            if (issue.section?.id === selected.value) {
                for (let parent = target?.parentElement; parent && parent !== region; parent = parent.parentElement) {
                    if (parent instanceof HTMLDetailsElement) parent.open = true;
                }
            }
        });
    },
    { flush: 'post' },
);
function rememberAssets(assets: EditorImage[]) {
    const map = new Map(images.value.map((i) => [i.id, i]));
    assets.forEach((i) => map.set(i.id, i));
    images.value = [...map.values()];
}
function pick(value: PhotoChoice) {
    choice.value = value;
    picker.value = true;
}
function choose(image: CmsImageReference) {
    const action = choice.value;
    choice.value = null;
    action?.choose({ ...image });
}
function chooseMany(images: CmsImageReference[]) {
    const action = choice.value;
    choice.value = null;
    action?.multiple?.choose(images.map((image) => ({ ...image })));
}
function addGuestGuideSwatch(section: Editable<CmsGuestGuideSection>) {
    if (section.data.attire.swatches.length >= 5) return;
    section.data.attire.swatches.push({ id: crypto.randomUUID(), label: 'Color', color: '#A36E59' });
}
function addGuestGuide() {
    if (
        !props.capabilities[content.template_key]?.includes('guest_guide') ||
        content.content.sections.some((section) => section.type === 'guest_guide')
    )
        return;
    const section = newGuestGuideSection();
    const entourage = content.content.sections.findIndex((entry) => entry.type === 'entourage');
    const rsvp = content.content.sections.findIndex((entry) => entry.type === 'rsvp_information');
    const index = entourage >= 0 ? entourage + 1 : rsvp >= 0 ? rsvp : content.content.sections.length;
    content.content.sections.splice(index, 0, section as Editable<CmsGuestGuideSection>);
    selected.value = section.id;
}
function addGuestGuideFaq(section: Editable<CmsGuestGuideSection>) {
    if (section.data.faqs.items.length >= 7) return;
    section.data.faqs.items.push({ id: crypto.randomUUID(), question: 'New question', answer: 'Add a helpful answer for your guests.' });
}
function moveGuestGuideFaq(section: Editable<CmsGuestGuideSection>, index: number, direction: -1 | 1) {
    const next = index + direction;
    if (next < 0 || next >= section.data.faqs.items.length) return;
    const [item] = section.data.faqs.items.splice(index, 1);
    section.data.faqs.items.splice(next, 0, item);
}
let timer: ReturnType<typeof setTimeout> | undefined,
    controller: AbortController | undefined,
    generation = 0;
async function validate(version: number) {
    controller?.abort();
    controller = new AbortController();
    const guideIssues = content.content.sections.flatMap((section, index) =>
        section.type === 'guest_guide' ? guestGuideValidationIssues(section, index) : [],
    );
    if (guideIssues.length) {
        if (version !== generation) return;
        errors.value = Object.fromEntries(guideIssues.map((issue) => [issue.path, [issue.message]]));
        status.value = 'Some Guest Guide fields need attention Â· local edits are preserved';
        return;
    }
    try {
        await editorRequest(props.endpoints.validate, props.csrfToken, { document: content, slots }, controller.signal);
        if (version !== generation) return;
        errors.value = {};
        status.value = 'Preview content checked';
    } catch (error) {
        if (version !== generation || (error instanceof DOMException && error.name === 'AbortError')) return;
        const failure = error as Error & { errors?: Record<string, string[]> };
        errors.value = failure.errors ?? {};
        status.value = Object.keys(errors.value).length
            ? 'Some sections need attention · local edits are preserved'
            : failure.message || 'Content could not be checked. Local edits remain here.';
    }
}
function queue() {
    status.value = 'Checking preview content…';
    controller?.abort();
    const version = ++generation;
    clearTimeout(timer);
    timer = setTimeout(() => void validate(version), 350);
}
async function save() {
    if (saving.value) return;
    saving.value = true;
    feedbackKind.value = 'info';
    saveFeedback.value = 'Saving draft…';
    const source = JSON.parse(JSON.stringify(content));
    try {
        const result = await editorRequest(
            props.endpoints.draft,
            props.csrfToken,
            { document: source, draft_version: draftVersion.value },
            undefined,
            'PUT',
        );
        if (!Number.isInteger(result.draft_version) || result.draft_version !== draftVersion.value + 1)
            throw new Error('The save could not be confirmed. Review the saved preview before retrying.');
        draftVersion.value = result.draft_version as number;
        publication.value = result.publication as WeddingPublicationSummary;
        savedSource.value = JSON.stringify(source);
        feedbackKind.value = 'success';
        saveFeedback.value = dirty.value
            ? 'Draft saved. Newer edits are still unsaved.'
            : 'Draft saved privately. Your published website has not changed.';
    } catch (error) {
        const failure = error as Error & { errors?: Record<string, string[]> };
        errors.value = failure.errors ?? {};
        feedbackKind.value = failure.errors?.draft_version ? 'conflict' : 'error';
        const message =
            editorErrorMessages(failure.errors ?? {}, content).join(' ') ||
            (error instanceof TypeError
                ? 'The save could not be confirmed. Your edits remain here. Review the saved draft before retrying or reloading.'
                : failure.message) ||
            'The save could not be confirmed. Your edits remain here.';
        saveFeedback.value = issues.value.length && feedbackKind.value !== 'conflict' ? '' : message;
        await nextTick();
        errorSummary.value?.focus();
    } finally {
        saving.value = false;
    }
}
function reloadSaved() {
    leaving = true;
    window.location.reload();
}
watch([content, slots], queue, { deep: true });
function beforeLeave(event: BeforeUnloadEvent) {
    if (!leaving && (dirty.value || saving.value)) {
        event.preventDefault();
        event.returnValue = '';
    }
}
const stopNavigation = router.on('before', (event) => {
    if (!leaving && (dirty.value || saving.value) && !window.confirm('Leave this editor? Unsaved changes will be lost.')) event.preventDefault();
});
onMounted(() => {
    window.addEventListener('beforeunload', beforeLeave);
    void validate(generation);
});
onBeforeUnmount(() => {
    generation++;
    clearTimeout(timer);
    controller?.abort();
    stopNavigation();
    window.removeEventListener('beforeunload', beforeLeave);
});
</script>
<template>
    <CmsEditorLayout>
        <Head title="Wedding website" />
        <div class="cms-editor cms-workspace w-full min-w-0 p-4">
            <header class="flex flex-wrap items-start justify-between gap-3 border-b pb-2">
                <div class="min-w-0">
                    <p class="font-mono text-[11px] uppercase tracking-widest text-muted-foreground">Private workspace · website editor</p>
                    <h1 class="mt-1 text-xl font-medium tracking-tight">Make it yours</h1>
                    <p class="mt-1 max-w-xl text-xs leading-relaxed text-muted-foreground">
                        Drafts and photographs are private. Save, review, then publish when ready.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="overviewUrl" class="cms-secondary">Back to wedding</a>
                    <a v-if="draftVersion" :href="endpoints.preview" target="_blank" rel="noopener" class="cms-secondary">View saved draft</a>
                    <button type="button" class="cms-primary" :disabled="saving || (!dirty && draftVersion > 0)" @click="save">
                        {{ saving ? 'Saving…' : 'Save Draft' }}
                    </button>
                    <CmsPublishAction
                        v-if="publication && endpoints.publish"
                        :publication="publication"
                        :endpoint="endpoints.publish"
                        :csrf-token="csrfToken"
                        :enabled="Boolean(publishingEnabled)"
                        :unsaved="dirty || saving || draftVersion !== publication.draftVersion"
                        @published="publication = $event"
                        @review="reloadDialog = true"
                    />
                </div>
            </header>
            <p role="status" class="cms-draft-state py-1.5 text-xs" :data-dirty="dirty">
                <span aria-hidden="true">{{ dirty ? '◌' : '✓' }}</span>
                {{ dirty ? 'Unsaved changes' : draftVersion ? 'All changes saved' : 'No saved draft yet' }} · Wedding timezone: {{ timeZone }}
            </p>
            <p v-if="saveFeedback && !issues.length" role="status" class="cms-feedback mb-4 text-sm" :data-kind="feedbackKind">
                <strong
                    >{{
                        feedbackKind === 'success'
                            ? 'Saved'
                            : feedbackKind === 'conflict'
                              ? 'Draft changed'
                              : feedbackKind === 'error'
                                ? 'Could not save'
                                : 'Draft'
                    }}
                    · </strong
                >{{ saveFeedback }}
            </p>
            <button v-if="feedbackKind === 'conflict' || feedbackKind === 'error'" class="cms-secondary mb-3" @click="reloadDialog = true">
                Review saved version
            </button>
            <Dialog v-model:open="reloadDialog"
                ><DialogContent class="cms-confirm"
                    ><DialogHeader
                        ><DialogTitle>Review saved version?</DialogTitle
                        ><DialogDescription
                            >Unsaved edits in this tab will be discarded. Uploaded photographs stay in your private library.</DialogDescription
                        ></DialogHeader
                    >
                    <div class="flex flex-wrap gap-2">
                        <button class="cms-secondary" @click="reloadDialog = false">Keep editing</button
                        ><button class="cms-primary" @click="reloadSaved">Discard edits and load saved version</button>
                    </div></DialogContent
                ></Dialog
            >
            <div class="mb-3 flex flex-wrap gap-2 lg:hidden">
                <button class="cms-secondary" :aria-pressed="view === 'edit'" @click="view = 'edit'">Edit sections</button
                ><button class="cms-secondary" :aria-pressed="view === 'preview'" @click="view = 'preview'">View website preview</button>
            </div>
            <nav
                aria-label="Editor sections"
                class="cms-editor-nav mb-2 flex flex-wrap gap-x-1 border-b pb-1"
                :class="view === 'preview' ? 'hidden lg:flex' : ''"
            >
                <button
                    v-for="section in content.content.sections"
                    :key="section.id"
                    class="min-h-11 rounded-md px-3 py-2 text-sm"
                    :class="selected === section.id ? 'bg-muted font-semibold' : 'text-muted-foreground hover:bg-muted'"
                    :aria-current="selected === section.id ? 'step' : undefined"
                    @click="selected = section.id"
                >
                    {{ labels[section.type] }}
                </button>
                <button
                    v-if="
                        props.capabilities[content.template_key]?.includes('guest_guide') &&
                        !content.content.sections.some((section) => section.type === 'guest_guide')
                    "
                    type="button"
                    class="cms-secondary"
                    @click="addGuestGuide"
                >
                    Add Guest Guide
                </button>
            </nav>
            <div class="cms-split grid min-w-0 gap-4 lg:grid-cols-[clamp(340px,27vw,400px)_minmax(0,1fr)]">
                <section class="cms-editor-pane min-w-0" :class="view === 'preview' ? 'hidden lg:flex' : ''" aria-label="Website editing controls">
                    <div class="cms-editor-fields" tabindex="0" role="region" aria-label="Section fields">
                        <p v-if="!content.content.sections.length" class="mb-4 text-sm text-muted-foreground">
                            This draft has no content sections. Its saved data has been preserved.
                        </p>
                        <p v-if="!issues.length" role="status" class="mb-3 text-xs text-muted-foreground">{{ status }}</p>
                        <section
                            v-if="issues.length"
                            ref="errorSummary"
                            tabindex="-1"
                            aria-label="Content errors"
                            class="cms-error-summary mb-4"
                            :data-kind="feedbackKind === 'conflict' ? 'conflict' : 'error'"
                        >
                            <h3 class="text-sm font-semibold" aria-live="polite">
                                Review {{ issues.length }} content {{ issues.length === 1 ? 'issue' : 'issues' }}
                            </h3>
                            <p v-if="feedbackKind === 'conflict'" class="mt-2 text-sm">
                                Your edits are safe here. Review the saved draft before reloading.
                            </p>
                            <ul class="space-y-2 py-2">
                                <li v-for="(issue, index) in issues" :key="issue.path + index">
                                    <button :id="'cms-error-' + index" class="text-left underline underline-offset-4" @click="locateIssue(issue)">
                                        {{ issue.message }}
                                    </button>
                                </li>
                            </ul>
                        </section>
                        <section
                            v-for="section in content.content.sections"
                            v-show="selected === section.id"
                            :key="section.id"
                            :aria-label="labels[section.type] + ' editing'"
                            :data-editor-section="section.id"
                            class="space-y-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h2 class="text-lg font-medium">{{ labels[section.type] }}</h2>
                                <label class="flex items-center gap-2 text-sm"><input v-model="section.enabled" type="checkbox" />Show section</label>
                            </div>
                            <p v-if="!section.enabled" class="text-sm text-muted-foreground">Hidden in the preview. Your content stays here.</p>
                            <template v-if="section.type === 'welcome'"
                                ><div class="space-y-1 break-words text-sm text-muted-foreground">
                                    <p>{{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}</p>
                                    <p>{{ wedding.wedding_date || 'Wedding date not set' }}</p>
                                    <p>Names, date and venues come from your wedding details.</p>
                                </div>
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        :checked="content.content.experience.enabled && content.content.experience.intro_style === 'photobooth'"
                                        @change="
                                            content.content.experience = ($event.target as HTMLInputElement).checked
                                                ? photoboothExperience()
                                                : { enabled: false }
                                        "
                                    />
                                    Photobooth opening
                                </label>
                                <p class="text-xs text-muted-foreground">
                                    A romantic photo-strip welcome on the first visit. Save and publish to apply; direct section links skip it.
                                </p>
                                <label class="cms-label"
                                    >Invitation message<textarea
                                        v-model="section.data.message"
                                        data-cms-field="data.message"
                                        rows="4"
                                        class="cms-input"
                                        maxlength="1000"
                                    /></label
                                ><CmsImageField
                                    v-model="slots.hero"
                                    label="Main wedding photo"
                                    field="content.photo_slots.hero"
                                    :images="images"
                                    @pick="pick"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Photo assignments are included when you save the draft. You can reuse photographs from your private library.
                                </p></template
                            >
                            <template v-else-if="section.type === 'event_details'"
                                ><p class="break-words text-sm">
                                    Ceremony: {{ wedding.ceremony_venue || 'Not set' }}<br />Reception: {{ wedding.reception_venue || 'Not set' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Your venues come from Wedding details. Update them there to keep your invitation consistent.
                                </p>
                                <CmsImageField
                                    v-model="slots.details"
                                    label="Wedding details photo"
                                    field="content.photo_slots.details"
                                    :images="images"
                                    @pick="pick"
                            /></template>
                            <template v-else-if="section.type === 'countdown'"
                                ><label class="cms-label"
                                    >Countdown heading<input
                                        v-model="section.data.heading"
                                        data-cms-field="data.heading"
                                        class="cms-input"
                                        maxlength="140" /></label
                                ><label class="cms-label"
                                    >Countdown subtitle<textarea
                                        v-model="section.data.subtitle"
                                        data-cms-field="data.subtitle"
                                        rows="3"
                                        class="cms-input"
                                        maxlength="500"
                                    />
                                </label>
                                <p class="text-xs text-muted-foreground">
                                    The countdown uses the date, time and timezone from your wedding details.
                                </p></template
                            >
                            <template v-else-if="section.type === 'story'"
                                ><CmsImageField
                                    v-model="slots.story"
                                    label="Our Story photo"
                                    field="content.photo_slots.story"
                                    :images="images"
                                    @pick="pick" /><StorySectionEditor
                                    v-model="content.content.sections[content.content.sections.indexOf(section)] as Editable<typeof section>"
                                    :limits="limits"
                                    :error-fields="
                                        issues
                                            .filter((issue) => issue.section?.id === section.id)
                                            .map((issue) => issue.path.replace(/^(?:document\.)?content\.sections\.\d+\./, ''))
                                    "
                                    :images="images"
                                    @pick="pick"
                            /></template>
                            <GallerySectionEditor
                                v-else-if="section.type === 'gallery'"
                                v-model="content.content.sections[content.content.sections.indexOf(section)] as Editable<typeof section>"
                                :limits="limits"
                                :images="images"
                                @pick="pick"
                            />
                            <EntourageSectionEditor
                                v-else-if="section.type === 'entourage'"
                                v-model="content.content.sections[content.content.sections.indexOf(section)] as Editable<typeof section>"
                                :limits="limits"
                                :error-fields="
                                    issues
                                        .filter((issue) => issue.section?.id === section.id)
                                        .map((issue) => issue.path.replace(/^(?:document\.)?content\.sections\.\d+\./, ''))
                                "
                            />
                            <section v-else-if="section.type === 'guest_guide'" class="space-y-3" aria-label="Guest Guide blocks">
                                <div class="cms-guide-block space-y-3">
                                    <label class="flex min-h-11 items-center justify-between gap-3 text-sm font-medium"
                                        ><span>Attire Guide</span
                                        ><span class="flex items-center gap-2"
                                            ><input v-model="section.data.attire.enabled" type="checkbox" aria-label="Show Attire Guide" />Show</span
                                        ></label
                                    >
                                    <details open>
                                        <summary class="min-h-11 cursor-pointer py-2 text-sm text-muted-foreground">Edit attire details</summary>
                                        <div class="mt-4 space-y-4">
                                            <label class="cms-label"
                                                >Dress code<input
                                                    v-model="section.data.attire.dress_code"
                                                    data-cms-field="data.attire.dress_code"
                                                    class="cms-input"
                                                    maxlength="100"
                                            /></label>
                                            <label class="cms-label"
                                                >Short description<textarea
                                                    v-model="section.data.attire.description"
                                                    data-cms-field="data.attire.description"
                                                    class="cms-input"
                                                    rows="3"
                                                    maxlength="500"
                                                />
                                            </label>
                                            <div class="space-y-2">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <h3 class="text-sm font-medium">
                                                        Color swatches
                                                        <span class="text-muted-foreground">({{ section.data.attire.swatches.length }}/5)</span>
                                                    </h3>
                                                    <button
                                                        type="button"
                                                        class="cms-secondary"
                                                        :disabled="section.data.attire.swatches.length >= CMS_GUEST_GUIDE_SWATCH_LIMIT"
                                                        @click="addGuestGuideSwatch(section as Editable<CmsGuestGuideSection>)"
                                                    >
                                                        Add color
                                                    </button>
                                                </div>
                                                <div
                                                    v-for="(swatch, index) in section.data.attire.swatches"
                                                    :key="swatch.id"
                                                    class="flex flex-wrap items-center gap-3 rounded-md border p-3"
                                                >
                                                    <label class="sr-only" :for="`guest-guide-color-${swatch.id}`"
                                                        >Color swatch {{ index + 1 }}</label
                                                    >
                                                    <input
                                                        :id="`guest-guide-color-${swatch.id}`"
                                                        v-model="swatch.color"
                                                        type="color"
                                                        class="h-11 w-14 cursor-pointer rounded border p-1"
                                                        :data-cms-field="`data.attire.swatches.${index}.color`"
                                                    />
                                                    <label class="cms-label min-w-[140px] flex-1"
                                                        >Color label<input
                                                            v-model="swatch.label"
                                                            :data-cms-field="`data.attire.swatches.${index}.label`"
                                                            class="cms-input"
                                                            maxlength="50"
                                                    /></label>
                                                    <button
                                                        type="button"
                                                        class="cms-secondary"
                                                        :aria-label="`Remove color ${index + 1}`"
                                                        @click="section.data.attire.swatches.splice(index, 1)"
                                                    >
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                                <div class="cms-guide-block space-y-3">
                                    <label class="flex min-h-11 items-center justify-between gap-3 text-sm font-medium"
                                        ><span>Gift Guide</span
                                        ><span class="flex items-center gap-2"
                                            ><input v-model="section.data.gifts.enabled" type="checkbox" aria-label="Show Gift Guide" />Show</span
                                        ></label
                                    >
                                    <details>
                                        <summary class="min-h-11 cursor-pointer py-2 text-sm text-muted-foreground">Edit gift details</summary>
                                        <div class="mt-4 space-y-4">
                                            <label class="cms-label"
                                                >Heading<input
                                                    v-model="section.data.gifts.heading"
                                                    data-cms-field="data.gifts.heading"
                                                    class="cms-input"
                                                    maxlength="140"
                                            /></label>
                                            <label class="cms-label"
                                                >Personalized message<textarea
                                                    v-model="section.data.gifts.message"
                                                    data-cms-field="data.gifts.message"
                                                    class="cms-input"
                                                    rows="4"
                                                    maxlength="1000"
                                                />
                                            </label>
                                            <label class="cms-label"
                                                >Optional HTTPS registry or gift link<input
                                                    v-model="section.data.gifts.url"
                                                    data-cms-field="data.gifts.url"
                                                    type="url"
                                                    inputmode="url"
                                                    placeholder="https://example.com"
                                                    class="cms-input"
                                                    maxlength="2048"
                                            /></label>
                                            <p class="text-xs text-muted-foreground">Gift information is optional. No payments are collected here.</p>
                                        </div>
                                    </details>
                                </div>
                                <div class="cms-guide-block space-y-3">
                                    <label class="flex min-h-11 items-center justify-between gap-3 text-sm font-medium"
                                        ><span>Frequently asked questions</span
                                        ><span class="flex items-center gap-2"
                                            ><input
                                                v-model="section.data.faqs.enabled"
                                                type="checkbox"
                                                aria-label="Show frequently asked questions"
                                            />Show</span
                                        ></label
                                    >
                                    <details open>
                                        <summary class="min-h-11 cursor-pointer py-2 text-sm text-muted-foreground">Edit questions</summary>
                                        <div class="mt-4 space-y-3">
                                            <p class="text-xs text-muted-foreground">
                                                Add, remove, or reorder up to {{ CMS_GUEST_GUIDE_FAQ_LIMIT }} questions. The suggested answers are
                                                editable.
                                            </p>
                                            <details v-for="(item, index) in section.data.faqs.items" :key="item.id" class="cms-guide-faq-editor">
                                                <summary class="flex cursor-pointer items-center justify-between gap-2">
                                                    <span class="truncate">{{ index + 1 }}. {{ item.question || 'New question' }}</span
                                                    ><span class="text-xs text-muted-foreground">Edit</span>
                                                </summary>
                                                <div class="mt-3 space-y-3">
                                                    <label class="cms-label"
                                                        >Question<input
                                                            v-model="item.question"
                                                            :data-cms-field="`data.faqs.items.${index}.question`"
                                                            class="cms-input"
                                                            maxlength="180"
                                                    /></label>
                                                    <label class="cms-label"
                                                        >Answer<textarea
                                                            v-model="item.answer"
                                                            :data-cms-field="`data.faqs.items.${index}.answer`"
                                                            class="cms-input"
                                                            rows="3"
                                                            maxlength="1000"
                                                        />
                                                    </label>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            class="cms-secondary"
                                                            :disabled="index === 0"
                                                            :aria-label="`Move question ${index + 1} up`"
                                                            @click="moveGuestGuideFaq(section as Editable<CmsGuestGuideSection>, index, -1)"
                                                        >
                                                            Move up
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="cms-secondary"
                                                            :disabled="index === section.data.faqs.items.length - 1"
                                                            :aria-label="`Move question ${index + 1} down`"
                                                            @click="moveGuestGuideFaq(section as Editable<CmsGuestGuideSection>, index, 1)"
                                                        >
                                                            Move down
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="cms-secondary"
                                                            :aria-label="`Remove question ${index + 1}`"
                                                            @click="section.data.faqs.items.splice(index, 1)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            </details>
                                            <button
                                                type="button"
                                                class="cms-secondary"
                                                :disabled="section.data.faqs.items.length >= CMS_GUEST_GUIDE_FAQ_LIMIT"
                                                @click="addGuestGuideFaq(section as Editable<CmsGuestGuideSection>)"
                                            >
                                                Add question
                                            </button>
                                        </div>
                                    </details>
                                </div>
                            </section>
                            <label v-else class="cms-label"
                                >{{ labels[section.type] }} message<textarea
                                    v-model="section.data.message"
                                    data-cms-field="data.message"
                                    rows="4"
                                    class="cms-input"
                                    maxlength="1000"
                                />
                            </label>
                        </section>
                    </div>
                </section>
                <section class="cms-preview-pane min-w-0" :class="view === 'edit' ? 'hidden lg:flex' : ''" aria-label="Live invitation preview">
                    <div class="mb-2 flex flex-wrap justify-between gap-1 text-xs text-muted-foreground">
                        <span>Enchanted Editorial · live preview</span
                        ><span>{{
                            publishingEnabled
                                ? 'Unsaved edits appear here · Save, then Publish from this editor'
                                : 'Unsaved edits appear here · publishing unavailable'
                        }}</span>
                    </div>
                    <CmsPreviewFrame
                        :reveal-key="view"
                        :presentation="presentation"
                        :active-section="content.content.sections.find((section) => section.id === selected)?.type"
                    />
                </section>
            </div>
            <CmsImagePicker
                v-model:open="picker"
                :label="choice?.label ?? 'Choose photograph'"
                :endpoints="endpoints"
                :csrf-token="csrfToken"
                :multiple="Boolean(choice?.multiple)"
                :maximum="choice?.multiple?.maximum ?? 1"
                :excluded-ids="choice?.excludedIds ?? []"
                @selected="choose"
                @selected-many="chooseMany"
                @assets="rememberAssets"
            />
        </div>
    </CmsEditorLayout>
</template>
