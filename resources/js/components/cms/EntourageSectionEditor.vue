<script setup lang="ts">
import type { Editable, EditorLimits } from '@/types/cms-editor';
import type { CmsEntourageSection } from '@/types/cms-v2';
import { computed, nextTick, reactive, ref } from 'vue';
import CmsRemoveButton from './CmsRemoveButton.vue';
const model = defineModel<Editable<CmsEntourageSection>>({ required: true });
const props = defineProps<{ limits: EditorLimits; errorFields?: readonly string[] }>();
const pending = reactive<Record<string, string>>({});
const feedback = ref('');
const expanded = reactive(new Set<string>());
const dragging = ref<{ group: string; person: string } | null>(null);
const count = computed(() => model.value.data.groups.reduce((sum, g) => sum + g.people.length, 0));
function move<T>(list: T[], index: number, to: number) {
    if (to < 0 || to >= list.length || to === index) return;
    list.splice(to, 0, list.splice(index, 1)[0]);
}
function addPerson(group: Editable<CmsEntourageSection>['data']['groups'][number]) {
    const name = (pending[group.id] ?? '').trim();
    if (!name) {
        feedback.value = 'Enter a name before adding it.';
        return;
    }
    if (name.length > props.limits.nameLength || group.people.length >= props.limits.peoplePerGroup || count.value >= props.limits.people) return;
    group.people.push({ id: crypto.randomUUID(), name });
    pending[group.id] = '';
    feedback.value = 'Name added. This changes website content only.';
}
function enter(event: KeyboardEvent, group: Editable<CmsEntourageSection>['data']['groups'][number]) {
    if (event.isComposing) return;
    event.preventDefault();
    addPerson(group);
}
function order(group: Editable<CmsEntourageSection>['data']['groups'][number], index: number, to: number) {
    const person = group.people[index];
    if (to < 0 || to >= group.people.length || to === index) return;
    move(group.people, index, to);
    feedback.value = person.name + ' moved to position ' + (to + 1) + ' of ' + group.people.length + '.';
    void nextTick(() => document.getElementById('order-' + person.id)?.focus());
}
function keyOrder(event: KeyboardEvent, group: Editable<CmsEntourageSection>['data']['groups'][number], index: number) {
    if (!event.altKey || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
    event.preventDefault();
    order(group, index, index + (['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1));
}
function start(event: DragEvent, group: string, person: string) {
    dragging.value = { group, person };
    event.dataTransfer?.setData('text/plain', 'Reorder website name');
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
}
function drop(group: Editable<CmsEntourageSection>['data']['groups'][number], target: number) {
    if (dragging.value?.group === group.id) {
        const index = group.people.findIndex((p) => p.id === dragging.value?.person);
        if (index >= 0) order(group, index, target);
    }
    dragging.value = null;
}
function addGroup() {
    const id = crypto.randomUUID();
    expanded.clear();
    expanded.add(id);
    model.value.data.groups.push({ id, enabled: true, title: '', people: [] });
}
function toggle(id: string, event: Event) {
    if ((event.target as HTMLDetailsElement).open) expanded.add(id);
    else expanded.delete(id);
}
function done(id: string) {
    expanded.delete(id);
    void nextTick(() => document.getElementById('group-summary-' + id)?.focus());
}
</script>
<template>
    <div class="space-y-5">
        <label class="cms-label"
            >Entourage heading<input v-model="model.data.heading" data-cms-field="data.heading" class="cms-input" :maxlength="limits.titleLength"
        /></label>
        <p class="text-xs text-muted-foreground">Website names are separate from your private RSVP guest list. Select a name to edit it.</p>
        <div v-for="(group, index) in model.data.groups" :key="group.id" class="cms-collection-row">
            <details :open="expanded.has(group.id)" @toggle="toggle(group.id, $event)" :data-role-group="group.id">
                <summary :id="'group-summary-' + group.id" class="cms-collection-summary">
                    {{ group.title || 'New role group' }} · {{ group.people.length }} {{ group.people.length === 1 ? 'name' : 'names'
                    }}{{ group.enabled ? '' : ' · Hidden' }}
                    <span v-if="errorFields?.some((field) => field.startsWith('data.groups.' + index + '.'))" class="block text-xs"
                        >Needs attention</span
                    >
                </summary>
                <div class="space-y-4 pt-2">
                    <label class="cms-label"
                        >Role group title<input
                            v-model="group.title"
                            :data-cms-field="'data.groups.' + index + '.title'"
                            class="cms-input"
                            :maxlength="limits.titleLength"
                    /></label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="group.enabled" type="checkbox" />Show this role group</label>
                    <label class="cms-label" :for="'name-' + group.id">Add a name</label>
                    <div class="flex min-w-0 flex-wrap gap-2">
                        <input
                            :id="'name-' + group.id"
                            v-model="pending[group.id]"
                            :data-cms-field="'data.groups.' + index + '.people'"
                            class="cms-input min-w-0 flex-1 basis-40"
                            :maxlength="limits.nameLength"
                            enterkeyhint="enter"
                            :disabled="group.people.length >= limits.peoplePerGroup || count >= limits.people"
                            @keydown.enter="enter($event, group)"
                        />
                        <button
                            class="cms-secondary"
                            :disabled="group.people.length >= limits.peoplePerGroup || count >= limits.people"
                            @click="addPerson(group)"
                        >
                            Add person
                        </button>
                    </div>
                    <p :id="'order-help-' + group.id" class="text-xs text-muted-foreground">
                        Press Enter to add. Drag the grip to reorder, or focus it and use Alt + arrow keys. Names may repeat.
                    </p>
                    <ul :aria-label="(group.title || 'Role group') + ' names'" class="flex min-w-0 flex-wrap gap-2">
                        <li
                            v-for="(person, personIndex) in group.people"
                            :key="person.id"
                            class="cms-name-chip"
                            @dragover.prevent
                            @drop.prevent="drop(group, personIndex)"
                        >
                            <button
                                :id="'order-' + person.id"
                                type="button"
                                draggable="true"
                                class="cms-chip-grip"
                                :aria-label="'Reorder person ' + (personIndex + 1) + ': ' + person.name"
                                :aria-describedby="'order-help-' + group.id"
                                aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown Alt+ArrowLeft Alt+ArrowRight"
                                @keydown="keyOrder($event, group, personIndex)"
                                @dragstart="start($event, group.id, person.id)"
                                @dragend="dragging = null"
                            >
                                ⠿
                            </button>
                            <input
                                v-model="person.name"
                                :aria-label="'Person ' + (personIndex + 1)"
                                :data-cms-field="'data.groups.' + index + '.people.' + personIndex + '.name'"
                                class="cms-chip-name"
                                :style="{ width: Math.min(Math.max(person.name.length + 1, 5), 22) + 'ch' }"
                                :title="person.name"
                                :maxlength="limits.nameLength"
                            />
                            <CmsRemoveButton
                                :label="'Remove person ' + (personIndex + 1)"
                                compact
                                :description="
                                    'Remove ' +
                                    (person.name || 'this name') +
                                    ' from this local website section? This does not change your private guest list.'
                                "
                                @confirm="group.people.splice(personIndex, 1)"
                            />
                        </li>
                    </ul>
                    <button class="cms-secondary" @click="done(group.id)">Done editing role group</button>
                </div>
            </details>
            <div class="cms-collection-actions">
                <button
                    class="cms-collection-action"
                    :disabled="index === 0"
                    aria-label="Move role group up"
                    @click="move(model.data.groups, index, index - 1)"
                >
                    <span aria-hidden="true">↑</span>
                </button>
                <button
                    class="cms-collection-action"
                    :disabled="index === model.data.groups.length - 1"
                    aria-label="Move role group down"
                    @click="move(model.data.groups, index, index + 1)"
                >
                    <span aria-hidden="true">↓</span>
                </button>
                <CmsRemoveButton compact label="Remove role group" @confirm="model.data.groups.splice(index, 1)" />
            </div>
        </div>
        <button class="cms-primary" :disabled="model.data.groups.length >= limits.entourageGroups || count >= limits.people" @click="addGroup">
            Add role group
        </button>
        <p role="status" class="text-xs text-muted-foreground">{{ feedback }}</p>
        <p class="text-xs text-muted-foreground">{{ count }} of {{ limits.people }} names · up to {{ limits.entourageGroups }} role groups.</p>
    </div>
</template>
