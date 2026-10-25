<script setup lang="ts">
import type { Editable, EditorImage, EditorLimits, PhotoChoice } from '@/types/cms-editor';
import type { CmsGallerySection } from '@/types/cms-v2';
import { CMS_GALLERY_IMAGE_LIMIT } from '@/types/cms-v2';
import { computed, ref } from 'vue';
import CmsImageField from './CmsImageField.vue';
import CmsRemoveButton from './CmsRemoveButton.vue';
const model = defineModel<Editable<CmsGallerySection>>({ required: true });
const props = defineProps<{ limits: EditorLimits; images: readonly EditorImage[] }>();
const emit = defineEmits<{ pick: [choice: PhotoChoice] }>();
const feedback = ref('');
const maximum = computed(() => Math.min(props.limits.galleryImages, CMS_GALLERY_IMAGE_LIMIT));
const autoplay = computed({
    get: () => model.value.data.slideshow?.autoplay ?? true,
    set: (value: boolean) => (model.value.data.slideshow = { autoplay: value, interval_seconds: model.value.data.slideshow?.interval_seconds ?? 5 }),
});
const interval = computed({
    get: () => model.value.data.slideshow?.interval_seconds ?? 5,
    set: (value: 5 | 8 | 12) => (model.value.data.slideshow = { autoplay: autoplay.value, interval_seconds: value }),
});
function append(images: Editable<CmsGallerySection>['data']['items'][number]['image'][]) {
    const existing = new Set(model.value.data.items.map((item) => item.image.asset_id));
    let added = 0;
    for (const image of images) {
        if (existing.has(image.asset_id) || model.value.data.items.length >= maximum.value) continue;
        existing.add(image.asset_id);
        model.value.data.items.push({ id: crypto.randomUUID(), enabled: true, image, caption: '' });
        added++;
    }
    feedback.value = `${added} ${added === 1 ? 'photograph added' : 'photographs added'}. Already selected photos are kept once.`;
}
function add() {
    if (model.value.data.items.length >= maximum.value) return;
    emit('pick', {
        label: 'Gallery photographs',
        choose: (image) => append([image]),
        excludedIds: model.value.data.items.map((item) => item.image.asset_id),
        multiple: { maximum: maximum.value - model.value.data.items.length, choose: append },
    });
}
function replace(index: number, choice: PhotoChoice) {
    emit('pick', {
        ...choice,
        excludedIds: model.value.data.items.filter((_, i) => i !== index).map((item) => item.image.asset_id),
        choose: (image) => {
            if (model.value.data.items.some((item, i) => i !== index && item.image.asset_id === image.asset_id)) {
                feedback.value = 'That photograph is already in this gallery. Choose another.';
                return;
            }
            choice.choose(image);
        },
    });
}
function move(index: number, offset: number) {
    const list = model.value.data.items,
        to = index + offset;
    if (to < 0 || to >= list.length) return;
    [list[index], list[to]] = [list[to], list[index]];
}
</script>
<template>
    <div class="space-y-5">
        <p v-if="!model.data.items.length" class="rounded-md bg-muted p-4 text-sm text-muted-foreground">
            Make this space yours. Upload your favourite photographs to start your gallery.
        </p>
        <label class="cms-label"
            >Gallery heading<input v-model="model.data.heading" data-cms-field="data.heading" class="cms-input" :maxlength="limits.titleLength"
        /></label>
        <p class="text-xs text-muted-foreground">
            {{ model.data.items.length }} of {{ maximum }} photos · {{ maximum - model.data.items.length }} remaining. Removing a photograph here
            keeps it in your private library.
        </p>
        <p v-if="feedback" role="status" class="text-xs text-muted-foreground">{{ feedback }}</p>
        <label class="flex items-center gap-2 text-sm"><input v-model="autoplay" type="checkbox" />Auto-play slideshow</label>
        <div v-if="autoplay" class="cms-label">
            <label for="cms-slideshow-interval">Time between photographs</label
            ><select id="cms-slideshow-interval" v-model="interval" class="cms-input">
                <option :value="5">5 seconds</option>
                <option :value="8">8 seconds</option>
                <option :value="12">12 seconds</option>
            </select>
        </div>
        <p class="text-xs text-muted-foreground">
            Starts only with two or more photos. Visitors can pause; interaction and reduced-motion preferences stop automatic playback.
        </p>
        <fieldset v-for="(item, index) in model.data.items" :key="item.id" class="space-y-3 border-b pb-4">
            <legend class="py-2 text-sm font-medium">Gallery photograph {{ index + 1 }}</legend>
            <CmsImageField
                :model-value="item.image"
                :clearable="false"
                :label="'Gallery photograph ' + (index + 1)"
                :field="'data.items.' + index + '.image'"
                :images="images"
                @update:model-value="
                    (value) => {
                        if (value) item.image = value;
                    }
                "
                @pick="replace(index, $event)"
            />
            <label class="cms-label"
                >Caption {{ index + 1
                }}<textarea
                    v-model="item.caption"
                    :data-cms-field="'data.items.' + index + '.caption'"
                    class="cms-input"
                    :maxlength="limits.descriptionLength"
                />
            </label>
            <label class="flex items-center gap-2 text-sm"><input v-model="item.enabled" type="checkbox" />Show this photograph</label>
            <div class="flex flex-wrap gap-2">
                <button class="cms-secondary" :disabled="index === 0" @click="move(index, -1)">Move photo up</button
                ><button class="cms-secondary" :disabled="index === model.data.items.length - 1" @click="move(index, 1)">Move photo down</button
                ><CmsRemoveButton label="Remove gallery photograph" @confirm="model.data.items.splice(index, 1)" />
            </div>
        </fieldset>
        <button class="cms-primary" :disabled="model.data.items.length >= maximum" @click="add">Add gallery photograph</button>
    </div>
</template>
