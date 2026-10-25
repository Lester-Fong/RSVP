<script setup lang="ts">
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { nextTick, ref } from 'vue';
defineProps<{ label: string; description?: string; compact?: boolean }>();
const emit = defineEmits<{ confirm: [] }>();
const open = ref(false);
const trigger = ref<HTMLElement | null>(null);
let fallback: HTMLElement | null = null;
function confirm() {
    fallback = trigger.value?.closest<HTMLElement>('section[aria-label$=" editing"]') ?? null;
    emit('confirm');
    open.value = false;
}
function restoreFocus(event: Event) {
    if (!fallback) return;
    event.preventDefault();
    const section = fallback;
    void nextTick(() => section.querySelector<HTMLElement>('input:not([type="checkbox"]), textarea, button:not([disabled])')?.focus());
}
</script>
<template>
    <Dialog v-model:open="open"
        ><DialogTrigger as-child
            ><button ref="trigger" type="button" :class="compact ? 'cms-chip-remove' : 'cms-secondary'" :aria-label="label">
                <span v-if="compact" aria-hidden="true">×</span><template v-else>{{ label }}</template>
            </button></DialogTrigger
        >
        <DialogContent
            class="cms-confirm w-[calc(100%-2rem)] max-w-md rounded-2xl"
            overlay-class="cms-editor-overlay bg-black/30 backdrop-blur-sm"
            @close-auto-focus="restoreFocus"
            ><DialogHeader
                ><DialogTitle>{{ label }}?</DialogTitle
                ><DialogDescription>{{
                    description || 'This removes content from your local preview only. It does not delete uploaded photographs.'
                }}</DialogDescription></DialogHeader
            >
            <div class="flex justify-end gap-2">
                <DialogClose as-child><button class="cms-secondary">Keep content</button></DialogClose
                ><button class="cms-primary" @click="confirm">
                    {{ label }}
                </button>
            </div></DialogContent
        ></Dialog
    >
</template>
