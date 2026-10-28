<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { invitationLifecycleTransport } from '@/lib/personal-rsvp-http';
import type { InvitationLifecycleConfirmation, PersonalFailureKind } from '@/types/personal-rsvp';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    accountId: number;
    weddingId: number;
    groupId: number;
    guestId: number;
    action: 'withdraw' | 'reinstate';
}>();
const emit = defineEmits<{ 'update:open': [open: boolean]; changed: [] }>();
const loading = ref(false);
const confirmation = ref<InvitationLifecycleConfirmation | null>(null);
const error = ref('');
let controller: AbortController | null = null;
const title = computed(() => (props.action === 'withdraw' ? 'Withdraw this invitation?' : 'Reinstate this invitation?'));
const statusLabel = computed(() => confirmation.value?.status ?? 'pending');

function failure(kind: PersonalFailureKind): string {
    return kind === 'stale_access'
        ? 'Invitation details changed. Close this dialog and review the latest guest list before confirming again.'
        : kind === 'session'
          ? 'Your session expired. Reload Guest Management and try again.'
          : kind === 'rate_limited'
            ? 'Please wait briefly before trying again.'
            : kind === 'validation'
              ? 'This change was rejected. Refresh the guest list and review current details.'
              : 'We could not confirm the change. Refresh the guest list before trying again.';
}

async function load() {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    confirmation.value = null;
    error.value = '';
    const transport = invitationLifecycleTransport(props.accountId, props.weddingId, props.groupId, props.guestId);
    const result = await transport.inspect(props.action, controller.signal);
    if (controller.signal.aborted) return;
    loading.value = false;
    if (result.ok) confirmation.value = result.value.confirmation;
    else error.value = failure(result.kind);
}

async function confirm() {
    if (!confirmation.value || loading.value) return;
    loading.value = true;
    error.value = '';
    controller?.abort();
    controller = new AbortController();
    const transport = invitationLifecycleTransport(props.accountId, props.weddingId, props.groupId, props.guestId);
    const result = await transport.change(props.action, confirmation.value.confirmation, controller.signal);
    if (controller.signal.aborted) return;
    loading.value = false;
    if (!result.ok) {
        error.value = failure(result.kind);
        return;
    }
    emit('update:open', false);
    emit('changed');
}

watch(
    () => [props.open, props.action, props.guestId],
    ([open]) => {
        if (open) void load();
        else {
            controller?.abort();
            loading.value = false;
            confirmation.value = null;
            error.value = '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="action === 'withdraw'">
                    The invitation is withdrawn immediately. Any personal link and representative permissions are revoked. Saved answers and response
                    history stay on the retained guest record.
                </DialogDescription>
                <DialogDescription v-else>
                    The invitation becomes active again and the saved answer returns to attendance totals. A previous personal link or representative
                    designation will not be restored.
                </DialogDescription>
            </DialogHeader>
            <p v-if="loading && !confirmation" role="status" class="text-sm text-muted-foreground">Loading current invitation details…</p>
            <div v-if="confirmation" class="space-y-4 text-sm">
                <p>
                    <span class="font-medium">{{ confirmation.guest.name }}</span> · saved RSVP: <span class="capitalize">{{ statusLabel }}</span>
                </p>
                <p v-if="confirmation.respondedAt" class="text-muted-foreground">
                    Response saved {{ new Date(confirmation.respondedAt).toLocaleString() }}.
                </p>
                <div v-if="action === 'withdraw' && confirmation.affected.length > 1">
                    <p class="font-medium">People whose access will change</p>
                    <ul class="mt-1 list-inside list-disc text-muted-foreground">
                        <li v-for="person in confirmation.affected" :key="person.id">{{ person.name }}</li>
                    </ul>
                    <p class="mt-2 text-muted-foreground">Other dependents remain invited but will need a fresh authorized arrangement to respond.</p>
                </div>
                <p v-if="action === 'reinstate'" class="text-muted-foreground">
                    No old credential or delegation is restored. You can arrange fresh RSVP access separately after reinstatement.
                </p>
            </div>
            <p v-if="error" role="alert" class="rounded-md bg-destructive/10 p-3 text-sm">{{ error }}</p>
            <DialogFooter>
                <Button type="button" variant="outline" :disabled="loading" @click="emit('update:open', false)">Cancel</Button>
                <Button type="button" :disabled="loading || !confirmation" @click="confirm">{{
                    loading ? 'Working…' : action === 'withdraw' ? 'Withdraw invitation' : 'Reinstate invitation'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
