<script setup lang="ts">
import type { Editable, EditorImage, PhotoChoice } from '@/types/cms-editor';
import type { CmsImageReference } from '@/types/cms-v2';
import { useId } from 'vue';
const frameId = useId();
const model = defineModel<Editable<CmsImageReference> | null>({ required: true });
const props = withDefaults(
    defineProps<{
        label: string;
        images: readonly EditorImage[];
        clearable?: boolean;
        field?: string;
    }>(),
    { clearable: true },
);
const emit = defineEmits<{ pick: [choice: PhotoChoice] }>();
function pick() {
    emit('pick', {
        label: props.label,
        choose: (image) => (model.value = { ...image, crop: 'natural', frame: model.value?.frame ?? 'fill' }),
    });
}
</script>
<template>
    <fieldset class="space-y-3 border-t py-4" :data-cms-field="field">
        <legend class="text-sm font-medium">{{ label }}</legend>
        <img
            v-if="model && images.find((i) => i.id === model?.asset_id)"
            :src="images.find((i) => i.id === model?.asset_id)?.preview_url"
            :alt="model.alt"
            class="h-28 w-full rounded-md object-cover"
            :style="{ objectFit: model.frame === 'fit' ? 'contain' : 'cover' }"
        />
        <div class="flex flex-wrap gap-2">
            <button type="button" class="cms-secondary" @click="pick">{{ model ? 'Change photo' : 'Choose photo' }}</button
            ><button v-if="model && clearable !== false" type="button" class="cms-secondary" @click="model = null">Remove from section</button>
        </div>
        <template v-if="model"
            ><label class="cms-label">Photo description<input v-model="model.alt" class="cms-input" maxlength="500" /></label>
            <div class="cms-label">
                <label :for="frameId">Photo frame</label
                ><select
                    :id="frameId"
                    :value="model.frame ?? ''"
                    class="cms-input"
                    @change="model.frame = ($event.target as HTMLSelectElement).value as 'fit' | 'fill'"
                >
                    <option v-if="!model.frame" value="" disabled>Original framing — choose Fit or Fill to change</option>
                    <option value="fill">Fill — crop to the frame</option>
                    <option value="fit">Fit — show the whole photo</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="cms-label"
                    >Photo focus<select v-model="model.focal" class="cms-input">
                        <option v-for="v in ['center', 'top', 'bottom', 'left', 'right']" :key="v" :value="v">{{ v }}</option>
                    </select></label
                >
                <label class="cms-label"
                    >Photo crop<select v-model="model.crop" class="cms-input">
                        <option v-for="v in ['natural', 'portrait', 'landscape', 'square']" :key="v" :value="v">{{ v }}</option>
                    </select></label
                >
            </div></template
        >
    </fieldset>
</template>
