<script setup lang="ts">
import type { Editable, EditorImage, EditorLimits, PhotoChoice } from '@/types/cms-editor';
import type { CmsStorySection } from '@/types/cms-v2';
import { nextTick, reactive, ref } from 'vue';
import CmsImageField from './CmsImageField.vue';
import CmsRemoveButton from './CmsRemoveButton.vue';
const model = defineModel<Editable<CmsStorySection>>({ required: true });
const props = defineProps<{ limits: EditorLimits; images: readonly EditorImage[]; errorFields?: readonly string[] }>();
const expanded = reactive(new Set<string>());
const feedback = ref('');
const emit = defineEmits<{ pick: [choice: PhotoChoice] }>();
function addMilestone() {
    const id = crypto.randomUUID();
    (model.value.data.milestones ??= []).push({ id, enabled: true, title: '', description: '', when: null, photo: null });
    expanded.add(id);
    void nextTick(() => document.querySelector<HTMLElement>('[data-milestone-editor="' + id + '"] input')?.focus());
}
function move(index: number, offset: number) {
    const list = model.value.data.milestones ?? [],
        to = index + offset;
    if (to < 0 || to >= list.length) return;
    const milestone = list[index];
    [list[index], list[to]] = [list[to], list[index]];
    feedback.value = (milestone.title || 'Milestone') + ' moved to position ' + (to + 1) + '.';
    void nextTick(() => document.getElementById('milestone-summary-' + milestone.id)?.focus());
}
function toggle(id: string, event: Event) {
    if ((event.target as HTMLDetailsElement).open) expanded.add(id);
    else expanded.delete(id);
}
function done(id: string) {
    expanded.delete(id);
    void nextTick(() => document.getElementById('milestone-summary-' + id)?.focus());
}
function keyboard(event: KeyboardEvent, index: number) {
    if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    move(index, event.key === 'ArrowUp' ? -1 : 1);
}
function hasErrors(index: number) {
    return props.errorFields?.some((field) => field.startsWith('data.milestones.' + index + '.'));
}
function precision(index: number, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const m = model.value.data.milestones?.[index];
    if (m) m.when = value === 'none' ? null : value === 'year' ? { precision: 'year', value: 0 } : { precision: 'date', value: '' };
}
</script>
<template>
    <div class="space-y-5">
        <label class="cms-label"
            >Story heading<input v-model="model.data.heading" data-cms-field="data.heading" class="cms-input" :maxlength="limits.titleLength"
        /></label>
        <fieldset v-for="(_, index) in model.data.paragraphs" :key="index" class="space-y-2">
            <label class="cms-label"
                >Story paragraph {{ index + 1
                }}<textarea
                    v-model="model.data.paragraphs[index]"
                    :data-cms-field="'data.paragraphs.' + index"
                    rows="5"
                    class="cms-input"
                    :maxlength="limits.paragraphLength"
                /></label
            ><CmsRemoveButton label="Remove paragraph" @confirm="model.data.paragraphs.splice(index, 1)" />
        </fieldset>
        <button class="cms-secondary" :disabled="model.data.paragraphs.length >= limits.paragraphs" @click="model.data.paragraphs.push('')">
            Add paragraph
        </button>
        <div class="border-t pt-5">
            <h3 class="text-sm font-medium">Your milestones <span class="font-normal text-muted-foreground">(optional)</span></h3>
            <p class="mt-1 text-xs text-muted-foreground">Short moments are enough. Dates and photographs are optional.</p>
        </div>
        <div v-for="(milestone, index) in model.data.milestones" :key="milestone.id" class="cms-collection-row" :data-milestone-editor="milestone.id">
            <details :open="expanded.has(milestone.id)" @toggle="toggle(milestone.id, $event)">
                <summary
                    :id="'milestone-summary-' + milestone.id"
                    class="cms-collection-summary"
                    aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
                    @keydown="keyboard($event, index)"
                >
                    <span
                        >{{ (milestone.when?.value || '') + (milestone.when?.value ? ' · ' : '') }}{{ milestone.title || 'New milestone'
                        }}{{ milestone.enabled ? '' : ' · Hidden' }}</span
                    >
                    <span v-if="hasErrors(index)" class="block text-xs font-normal">Needs attention</span>
                </summary>
                <div class="space-y-3 py-3">
                    <label class="cms-label"
                        >Milestone title<input
                            v-model="milestone.title"
                            :data-cms-field="'data.milestones.' + index + '.title'"
                            class="cms-input"
                            :maxlength="limits.titleLength"
                    /></label>
                    <label class="cms-label"
                        >Milestone description<textarea
                            v-model="milestone.description"
                            :data-cms-field="'data.milestones.' + index + '.description'"
                            class="cms-input"
                            :maxlength="limits.descriptionLength"
                        />
                    </label>
                    <label class="cms-label"
                        >Milestone date precision<select
                            class="cms-input"
                            :value="milestone.when?.precision ?? 'none'"
                            @change="precision(index, $event)"
                        >
                            <option value="none">No date</option>
                            <option value="year">Year only</option>
                            <option value="date">Exact date</option>
                        </select></label
                    >
                    <label v-if="milestone.when?.precision === 'year'" class="cms-label"
                        >Milestone year<input
                            :value="milestone.when.value || ''"
                            @input="milestone.when.value = Number(($event.target as HTMLInputElement).value)"
                            :data-cms-field="'data.milestones.' + index + '.when.value'"
                            type="number"
                            min="1"
                            max="9999"
                            class="cms-input"
                    /></label>
                    <label v-if="milestone.when?.precision === 'date'" class="cms-label"
                        >Milestone date<input
                            v-model="milestone.when.value"
                            :data-cms-field="'data.milestones.' + index + '.when.value'"
                            type="date"
                            class="cms-input"
                    /></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="milestone.enabled" type="checkbox" />Show this milestone</label>
                    <CmsImageField
                        :model-value="milestone.photo ?? null"
                        label="Timeline milestone image"
                        :field="'data.milestones.' + index + '.photo'"
                        :images="images"
                        @update:model-value="milestone.photo = $event"
                        @pick="emit('pick', $event)"
                    />
                    <button class="cms-secondary" @click="done(milestone.id)">Done editing milestone</button>
                </div>
            </details>
            <div class="cms-collection-actions">
                <button class="cms-collection-action" :disabled="index === 0" aria-label="Move milestone up" @click="move(index, -1)">
                    <span aria-hidden="true">↑</span>
                </button>
                <button
                    class="cms-collection-action"
                    :disabled="index === (model.data.milestones?.length ?? 0) - 1"
                    aria-label="Move milestone down"
                    @click="move(index, 1)"
                >
                    <span aria-hidden="true">↓</span>
                </button>
                <CmsRemoveButton compact label="Remove milestone" @confirm="model.data.milestones?.splice(index, 1)" />
            </div>
        </div>
        <button class="cms-secondary" :disabled="(model.data.milestones?.length ?? 0) >= limits.milestones" @click="addMilestone">
            Add milestone
        </button>
        <p class="text-xs text-muted-foreground">Focus a milestone summary and use Alt + Up/Down to reorder, or use its arrow buttons.</p>
        <p role="status" class="text-xs text-muted-foreground">{{ feedback }}</p>
        <p class="text-xs text-muted-foreground">Up to {{ limits.paragraphs }} paragraphs and {{ limits.milestones }} milestones.</p>
    </div>
</template>
