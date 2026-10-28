<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cmsCsrfHeaders, editorRequest } from '@/lib/cms-editor';
import type { EditorEndpoints, EditorImage } from '@/types/cms-editor';
import type { CmsImageReference } from '@/types/cms-v2';
import { onBeforeUnmount, ref, watch } from 'vue';
const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    label: string;
    endpoints: EditorEndpoints;
    csrfToken: string;
    multiple?: boolean;
    maximum?: number;
    excludedIds?: string[];
}>();
const emit = defineEmits<{ selected: [image: CmsImageReference]; selectedMany: [images: CmsImageReference[]]; assets: [images: EditorImage[]] }>();
const images = ref<EditorImage[]>([]),
    page = ref(1),
    lastPage = ref(1),
    busy = ref(false),
    progress = ref<number | null>(null),
    error = ref('');
const selected = ref<EditorImage[]>([]);
const failedFiles = ref<File[]>([]);
const completed = ref(0),
    total = ref(0);
let committed = false;
let controller: AbortController | undefined, upload: XMLHttpRequest | undefined;
let generation = 0;
async function load(n = 1) {
    controller?.abort();
    controller = new AbortController();
    const version = ++generation;
    busy.value = true;
    error.value = '';
    try {
        const data = await editorRequest(props.endpoints.images + '?page=' + n, props.csrfToken, undefined, controller.signal);
        if (version !== generation) return;
        images.value = data.images as EditorImage[];
        page.value = data.page as number;
        lastPage.value = data.lastPage as number;
        emit('assets', images.value);
    } catch (e) {
        if (version === generation && !(e instanceof DOMException && e.name === 'AbortError'))
            error.value = e instanceof Error ? e.message : 'Photos could not load. Retry.';
    } finally {
        if (version === generation) busy.value = false;
    }
}
function reference(image: EditorImage): CmsImageReference {
    return { asset_id: image.id, alt: 'Wedding photograph', focal: 'center', crop: 'natural', frame: 'fill' };
}
function choose(image: EditorImage) {
    if (!open.value || busy.value || committed || props.excludedIds?.includes(image.id)) return;
    if (props.multiple) {
        const index = selected.value.findIndex((item) => item.id === image.id);
        if (index >= 0) selected.value.splice(index, 1);
        else if (selected.value.length < (props.maximum ?? 1)) selected.value.push(image);
        else error.value = `Choose at most ${props.maximum} more photographs. Remove a selection first.`;
        return;
    }
    committed = true;
    emit('selected', reference(image));
    open.value = false;
}
function confirm() {
    if (!open.value || busy.value || committed || !selected.value.length) return;
    committed = true;
    emit('selectedMany', selected.value.map(reference));
    open.value = false;
}
function send(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = [...(input.files ?? [])];
    input.value = '';
    void sendFiles(files);
}
async function sendFiles(files: File[]) {
    if (!open.value || busy.value || !files.length) return;
    const available = (props.maximum ?? 1) - selected.value.length;
    if (props.multiple && files.length > available) {
        error.value = `You can add ${available} more photographs. Choose fewer files; nothing was uploaded.`;
        return;
    }
    busy.value = true;
    error.value = '';
    failedFiles.value = [];
    completed.value = 0;
    total.value = files.length;
    const version = generation;
    for (const file of files) {
        try {
            if (file.size > 5242880) throw Error('Choose photographs up to 5 MiB each.');
            const image = await sendFile(file);
            if (!open.value || version !== generation) return;
            images.value = [image, ...images.value.filter((item) => item.id !== image.id)];
            emit('assets', [image]);
            if (props.multiple && !selected.value.some((item) => item.id === image.id)) selected.value.push(image);
        } catch (failure) {
            if (!open.value || version !== generation) return;
            failedFiles.value.push(file);
            error.value = failure instanceof Error ? failure.message : 'Upload failed. Try again.';
        }
        completed.value++;
    }
    if (version === generation) {
        busy.value = false;
        progress.value = null;
    }
}
function sendFile(file: File): Promise<EditorImage> {
    return new Promise((resolve, reject) => {
        progress.value = 0;
        const xhr = new XMLHttpRequest();
        upload = xhr;
        xhr.open('POST', props.endpoints.upload);
        xhr.setRequestHeader('Accept', 'application/json');
        for (const [header, value] of Object.entries(cmsCsrfHeaders(props.csrfToken))) xhr.setRequestHeader(header, value);
        xhr.timeout = 90000;
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) progress.value = Math.round((e.loaded / e.total) * 100);
        };
        xhr.onload = () => {
            if (upload !== xhr) return;
            progress.value = null;
            try {
                const data = JSON.parse(xhr.responseText);
                if (xhr.status !== 201) {
                    reject(
                        new Error(
                            Object.values(data.errors ?? {})
                                .flat()
                                .join(' ') ||
                                data.message ||
                                'Upload failed. Try again.',
                        ),
                    );
                    return;
                }
                const image = data.image as EditorImage;
                resolve(image);
            } catch {
                reject(new Error('The upload response could not be read. Reopen Photos before retrying.'));
            }
        };
        const failed = () => {
            if (upload === xhr) {
                progress.value = null;
                reject(new Error('Upload interrupted. Reopen Photos to check whether it finished, then retry.'));
            }
        };
        xhr.onerror = failed;
        xhr.ontimeout = failed;
        xhr.onabort = () => reject(new Error('Upload cancelled.'));
        const body = new FormData();
        body.append('image', file);
        xhr.send(body);
    });
}
function stop() {
    generation++;
    controller?.abort();
    const xhr = upload;
    upload = undefined;
    xhr?.abort();
    busy.value = false;
    progress.value = null;
}
watch(open, (value) => {
    if (value) {
        committed = false;
        selected.value = [];
        failedFiles.value = [];
        total.value = 0;
        void load();
    } else stop();
});
onBeforeUnmount(stop);
</script>
<template>
    <Dialog v-model:open="open"
        ><DialogContent
            class="cms-picker max-h-[88dvh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl"
            overlay-class="cms-editor-overlay bg-black/30 backdrop-blur-sm"
        >
            <DialogHeader
                ><DialogTitle>{{ label }}</DialogTitle
                ><DialogDescription
                    >Private wedding photographs. Uploading stores a photo; choosing it changes only this preview.</DialogDescription
                ></DialogHeader
            >
            <label
                class="cms-primary inline-flex cursor-pointer justify-center focus-within:ring-2 focus-within:ring-foreground focus-within:ring-offset-2"
                :class="{ 'pointer-events-none opacity-50': busy }"
                >{{ multiple ? 'Upload photographs' : 'Upload photograph'
                }}<input type="file" class="sr-only" accept="image/jpeg,image/png,image/webp" :multiple="multiple" :disabled="busy" @change="send"
            /></label>
            <p class="text-xs text-muted-foreground">JPEG, PNG or WebP · up to 5 MiB and 12 million pixels.</p>
            <p v-if="busy" role="status">
                {{ progress === null ? 'Loading photographs…' : progress === 100 ? 'Processing photograph…' : 'Uploading ' + progress + '%' }}
            </p>
            <progress v-if="progress !== null" :value="progress" max="100" class="w-full" aria-label="Photo upload progress" />
            <p v-if="multiple" role="status" class="text-sm">
                {{ selected.length }} selected · {{ (maximum ?? 1) - selected.length }} remaining in this selection.
            </p>
            <p v-if="total" role="status" class="text-xs">
                {{ completed }} of {{ total }} uploads finished. Successful photos stay in your private library.
            </p>
            <div v-if="error" role="alert" class="space-y-2 text-sm">
                <p>{{ error }}</p>
                <p v-if="failedFiles.length">{{ failedFiles.length }} {{ failedFiles.length === 1 ? 'upload needs' : 'uploads need' }} attention.</p>
                <button v-if="failedFiles.length" type="button" class="cms-secondary" :disabled="busy" @click="sendFiles([...failedFiles])">
                    Retry failed uploads
                </button>
                <button type="button" class="cms-secondary" :disabled="busy" @click="load()">Reload photos</button>
            </div>
            <p v-if="!busy && !error && !images.length" class="py-6 text-sm text-muted-foreground">
                No photographs yet. Upload a favourite photograph to start your wedding's private library.
            </p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <button
                    v-for="(image, index) in images"
                    :key="image.id"
                    type="button"
                    class="min-w-0 rounded-lg border p-1 text-left focus-visible:ring-2 focus-visible:ring-foreground"
                    :aria-label="'Use photograph ' + (index + 1)"
                    :aria-pressed="multiple ? selected.some((item) => item.id === image.id) : undefined"
                    :class="{ 'ring-2 ring-foreground': selected.some((item) => item.id === image.id) }"
                    :disabled="busy || excludedIds?.includes(image.id)"
                    @click="choose(image)"
                >
                    <img :src="image.preview_url" alt="" class="aspect-square w-full rounded-md object-cover" loading="lazy" /><span
                        class="block p-2 text-xs"
                        >{{ image.width }} × {{ image.height }}</span
                    >
                    <span v-if="excludedIds?.includes(image.id)" class="block px-2 pb-2 text-xs">Already in gallery</span>
                </button>
            </div>
            <button v-if="multiple" type="button" class="cms-primary" :disabled="busy || !selected.length" @click="confirm">
                Add selected photographs ({{ selected.length }})
            </button>
            <div class="flex items-center justify-between gap-2">
                <button class="cms-secondary" :disabled="busy || page === 1" @click="load(page - 1)">Previous photos</button
                ><span class="text-xs">Page {{ page }} of {{ lastPage }}</span
                ><button class="cms-secondary" :disabled="busy || page >= lastPage" @click="load(page + 1)">Next photos</button>
            </div>
        </DialogContent></Dialog
    >
</template>
