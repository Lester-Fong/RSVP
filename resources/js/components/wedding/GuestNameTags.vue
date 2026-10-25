<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { X } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
const props = defineProps<{ modelValue: { name: string }[]; max: number; disabled?: boolean; errors?: Partial<Record<string, string>> }>();
const emit = defineEmits<{ 'update:modelValue': [value: { name: string }[]] }>();
const name = ref('');
const error = ref('');
watch(name, () => {
    error.value = '';
});
function focusInput(): void {
    document.getElementById('new-guest-name')?.focus();
}
function add(): boolean {
    if (props.disabled) return false;
    const value = name.value.trim();
    if (!value) {
        error.value = 'Enter a guest name before adding it.';
        focusInput();
        return false;
    }
    if (value.length > 255) {
        error.value = 'Guest names may be up to 255 characters.';
        focusInput();
        return false;
    }
    if (props.modelValue.length >= props.max) {
        error.value = `A group can have up to ${props.max} guests.`;
        return false;
    }
    emit('update:modelValue', [...props.modelValue, { name: value }]);
    name.value = '';
    void nextTick(() => {
        if (props.modelValue.length >= props.max) document.getElementById('field-guests.' + (props.modelValue.length - 1) + '.name')?.focus();
        else focusInput();
    });
    return true;
}
function enter(event: KeyboardEvent): void {
    if (event.isComposing) return;
    event.preventDefault();
    add();
}
async function remove(index: number): Promise<void> {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, position) => position !== index),
    );
    await nextTick();
    focusInput();
}
function commitPending(): boolean {
    if (name.value.trim()) return add();
    if (props.modelValue.length) return true;
    error.value = 'Add at least one named guest.';
    focusInput();
    return false;
}
defineExpose({ commitPending });
</script>

<template>
    <fieldset class="min-w-0 space-y-3">
        <legend class="mb-2 text-sm font-medium">Named guests</legend>
        <Label for="new-guest-name">Guest name</Label>
        <div class="flex flex-wrap items-start gap-2">
            <Input
                id="new-guest-name"
                v-model="name"
                maxlength="255"
                class="min-w-0 flex-1 basis-48"
                autocomplete="off"
                enterkeyhint="enter"
                :disabled="disabled || modelValue.length >= max"
                :aria-invalid="!!error"
                :aria-describedby="error ? 'guest-name-hint guest-name-error' : 'guest-name-hint'"
                @keydown.enter="enter"
            />
            <Button type="button" variant="outline" :disabled="disabled || modelValue.length >= max" @click="add">Add another guest</Button>
        </div>
        <p id="guest-name-hint" class="text-xs text-muted-foreground">
            Press Enter or Add another guest for each name. Up to {{ max }} people. Names may repeat.
        </p>
        <p v-if="error" id="guest-name-error" role="alert" class="text-sm">{{ error }}</p>
        <ul v-if="modelValue.length" aria-label="Guests to add" class="flex min-w-0 flex-wrap gap-2">
            <li
                v-for="(guest, index) in modelValue"
                :id="'field-guests.' + index + '.name'"
                :key="index"
                tabindex="-1"
                class="flex min-w-0 max-w-full items-center gap-1 rounded-md border pl-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
            >
                <span :title="guest.name" class="line-clamp-2 min-w-0 text-sm [overflow-wrap:anywhere]">{{ guest.name }}</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    :disabled="disabled"
                    :aria-label="'Remove unsaved guest ' + (index + 1) + ': ' + guest.name"
                    @click="remove(index)"
                >
                    <X class="h-3.5 w-3.5" aria-hidden="true" />
                </Button>
                <span v-if="errors?.['guests.' + index + '.name']" class="sr-only">{{ errors['guests.' + index + '.name'] }}</span>
            </li>
        </ul>
        <p role="status" class="text-xs text-muted-foreground">
            {{ modelValue.length }} {{ modelValue.length === 1 ? 'guest' : 'guests' }} ready to add.
        </p>
    </fieldset>
</template>
