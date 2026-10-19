<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { InvitationGroupSummary, InvitationLinkStatus } from '@/types';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ open: boolean; accountId: number; weddingId: number; group: InvitationGroupSummary | null }>();
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>();
const status = ref<InvitationLinkStatus>('not_generated');
const version = ref(0);
const url = ref('');
const busy = ref(false);
const error = ref('');
const message = ref('');
const confirmation = ref<'revoke' | 'regenerate' | null>(null);
const urlField = ref<HTMLInputElement | null>(null);
let controller: AbortController | null = null;
const stateLabel = computed(() => ({ not_generated: 'Not generated', active: 'Active', revoked: 'Revoked' })[status.value]);
const title = computed(() =>
    confirmation.value === 'revoke'
        ? 'Revoke this invitation link?'
        : confirmation.value === 'regenerate'
          ? 'Regenerate this invitation link?'
          : 'Invitation link',
);
watch(
    () => props.open,
    (open) => {
        url.value = '';
        error.value = '';
        message.value = '';
        confirmation.value = null;
        if (open && props.group) {
            status.value = props.group.link_status;
            version.value = props.group.link_version;
        }
    },
);
watch(
    () => props.group?.link_version,
    (value) => {
        if (props.open && props.group && value !== version.value) {
            url.value = '';
            version.value = props.group.link_version;
            status.value = props.group.link_status;
        }
    },
);
onBeforeUnmount(() => {
    controller?.abort();
    url.value = '';
});

function close(value: boolean) {
    if (!busy.value) {
        if (!value) url.value = '';
        emit('update:open', value);
    }
}
function restoreFocus(event: Event) {
    event.preventDefault();
    const trigger = document.getElementById('group-actions-' + props.group?.id);
    (trigger ?? document.getElementById('guest-management-heading'))?.focus();
}
async function confirm(action: 'revoke' | 'regenerate') {
    confirmation.value = action;
    error.value = '';
    url.value = '';
    await nextTick();
    document.getElementById('invitation-link-confirm-cancel')?.focus();
}
async function cancelConfirmation() {
    confirmation.value = null;
    await nextTick();
    document.getElementById('invitation-link-primary')?.focus();
}
async function act(action: 'generate' | 'copy' | 'revoke' | 'regenerate') {
    if (!props.group || busy.value) return;
    busy.value = true;
    error.value = '';
    message.value = '';
    url.value = '';
    controller = new AbortController();
    const timeout = setTimeout(() => controller?.abort(), 15000);
    try {
        const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
        const response = await fetch(
            route('accounts.weddings.invitation-links.update', {
                account: props.accountId,
                wedding: props.weddingId,
                invitationGroup: props.group.id,
                action,
            }),
            {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '',
                },
                body: JSON.stringify({ link_version: version.value }),
            },
        );
        if (!response.ok) {
            const payload = await response.json().catch(() => null);
            error.value =
                response.status === 401 || response.status === 419
                    ? 'Your session expired. Reload the page and try again.'
                    : (Object.values(payload?.errors ?? {}).flat()[0] as string) ||
                      'The request could not be completed. Reload the guest list and try again.';
            return;
        }
        const result = (await response.json()) as { status: InvitationLinkStatus; version: number; url: string | null };
        status.value = result.status;
        version.value = result.version;
        url.value = result.url ?? '';
        confirmation.value = null;
        emit('changed');
        if (action === 'copy') {
            try {
                await navigator.clipboard.writeText(url.value);
                message.value = 'Invitation link copied.';
            } catch {
                message.value = 'Select and copy the link below.';
            }
        } else {
            message.value =
                action === 'revoke'
                    ? 'Link revoked. Previous holders can no longer open it.'
                    : 'Link ready for manual sharing. No invitation has been sent.';
        }
        busy.value = false;
        await nextTick();
        if (url.value) {
            urlField.value?.focus();
            urlField.value?.select();
        } else {
            document.getElementById('invitation-link-primary')?.focus();
        }
    } catch {
        error.value = 'The connection was interrupted. Reload the guest list before trying again.';
    } finally {
        clearTimeout(timeout);
        controller = null;
        busy.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent
            class="invitation-link-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl sm:rounded-2xl"
            overlay-class="bg-black/30 backdrop-blur-sm"
            @close-auto-focus="restoreFocus"
            @escape-key-down="busy && $event.preventDefault()"
            @interact-outside="busy && $event.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="confirmation">{{
                    confirmation === 'revoke'
                        ? 'The current link will stop working immediately. You can regenerate a new link later.'
                        : 'The previous link will stop working immediately. Share the new link with your invitees afterward.'
                }}</DialogDescription>
                <DialogDescription v-else
                    >Anyone with this link can view this group's saved names and your published invitation. Share it only with intended
                    recipients.</DialogDescription
                >
            </DialogHeader>
            <p class="text-sm font-medium [overflow-wrap:anywhere]">{{ group?.label }}</p>
            <p class="font-mono text-xs uppercase tracking-wider">Link: {{ stateLabel }}</p>
            <p v-if="error" role="alert" class="text-sm">{{ error }}</p>
            <p v-if="message" role="status" class="text-sm">{{ message }}</p>
            <template v-if="!confirmation">
                <p class="text-sm text-muted-foreground">
                    Changes to the group label or saved guests appear to existing link holders immediately. Wedding draft changes stay private until
                    published. An unpublished wedding returns an unavailable page.
                </p>
                <p v-if="version > 0" class="text-xs text-muted-foreground">
                    Groups with issued links are kept even after revocation. Revoke access instead of deleting the group.
                </p>
                <div v-if="url" class="space-y-2">
                    <label for="personalized-invitation-url" class="text-sm font-medium">Invitation URL</label>
                    <input
                        id="personalized-invitation-url"
                        ref="urlField"
                        :value="url"
                        readonly
                        spellcheck="false"
                        class="h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                    />
                    <p class="text-xs text-muted-foreground">This is a private bearer link. It is cleared here when you close this dialog.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="status === 'not_generated'" id="invitation-link-primary" :disabled="busy" @click="act('generate')">{{
                        busy ? 'Working…' : 'Generate link'
                    }}</Button>
                    <Button v-if="status === 'active'" id="invitation-link-primary" :disabled="busy" @click="act('copy')">{{
                        busy ? 'Working…' : 'Copy link'
                    }}</Button>
                    <Button v-if="status === 'active'" variant="outline" :disabled="busy" @click="confirm('revoke')">Revoke link</Button>
                    <Button
                        v-if="version > 0"
                        :id="status === 'revoked' ? 'invitation-link-primary' : undefined"
                        variant="outline"
                        :disabled="busy"
                        @click="confirm('regenerate')"
                        >Regenerate link</Button
                    >
                </div>
                <DialogFooter><Button variant="outline" :disabled="busy" @click="close(false)">Done</Button></DialogFooter>
            </template>
            <DialogFooter v-else>
                <Button id="invitation-link-confirm-cancel" variant="outline" :disabled="busy" @click="cancelConfirmation">Cancel</Button>
                <Button :disabled="busy" @click="act(confirmation!)">{{
                    busy ? 'Working…' : confirmation === 'revoke' ? 'Revoke this link' : 'Regenerate this link'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    :global(body:has(.invitation-link-dialog) [data-state]) {
        animation: none !important;
        transition: none !important;
    }
}
</style>
