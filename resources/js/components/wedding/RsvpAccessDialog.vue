<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type {
    CredentialCommand,
    DelegationCommand,
    OwnerRsvpGroup,
    OwnerRsvpMember,
    OwnerRsvpTransport,
    PersonalFailureKind,
    PersonalResult,
} from '@/types/personal-rsvp';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';

const props = defineProps<{ open: boolean; group: OwnerRsvpGroup; transport: OwnerRsvpTransport; returnFocus?: HTMLElement | null }>();
const emit = defineEmits<{ 'update:open': [open: boolean]; changed: [group: OwnerRsvpGroup] }>();
const group = ref(props.group);
const selectedId = ref<number | null>(null);
const member = computed(() => group.value.members.find((person) => person.guest.id === selectedId.value && person.groupId === group.value.id));
const mode = ref<'details' | 'delegation' | 'revoke' | 'regenerate' | 'confirm-delegation'>('details');
const representativeId = ref('');
const busy = ref(false);
const blocked = ref(false);
const message = ref('');
const error = ref('');
const url = ref('');
const notice = ref<HTMLElement | null>(null);
const urlField = ref<HTMLInputElement | null>(null);
const heading = ref<HTMLElement | null>(null);
const id = useId();
let controller: AbortController | null = null;
let request = 0;
let secretVersion = 0;
let opener: HTMLElement | null = null;
const members = computed(() => group.value.members.filter((person) => person.groupId === group.value.id));
const options = computed(() =>
    !member.value || member.value.dependents.length
        ? []
        : members.value.filter(
              (person) =>
                  person.guest.id !== member.value?.guest.id &&
                  person.mode === 'independent' &&
                  person.representative === null &&
                  person.link_status === 'active',
          ),
);
const title = computed(
    () =>
        ({
            details: 'RSVP Access',
            delegation: 'Choose a representative',
            revoke: 'Revoke personal link?',
            regenerate: 'Regenerate personal link?',
            'confirm-delegation': 'Change RSVP representation?',
        })[mode.value],
);
const statusLabel = (state: OwnerRsvpMember['link_status']) => ({ active: 'Active', revoked: 'Revoked', not_generated: 'Not generated' })[state];
function clearSecret() {
    secretVersion++;
    url.value = '';
}
function synchronize(snapshot: OwnerRsvpGroup) {
    group.value = snapshot;
    if (!member.value) selectedId.value = null;
}
function reset() {
    request++;
    controller?.abort();
    busy.value = false;
    clearSecret();
    group.value = props.group;
    selectedId.value = null;
    mode.value = 'details';
    blocked.value = false;
    error.value = '';
    message.value = '';
}
watch(
    () => props.open,
    (open) => {
        if (open) opener = props.returnFocus ?? (document.activeElement as HTMLElement | null);
        reset();
    },
    { immediate: true },
);
watch(
    () => props.group,
    (snapshot) => {
        if (!props.open || snapshot === group.value) return;
        if (snapshot.id !== group.value.id) reset();
        else {
            clearSecret();
            synchronize(snapshot);
        }
    },
);
function close(open: boolean) {
    if (busy.value) return;
    if (!open) clearSecret();
    emit('update:open', open);
}
function restoreFocus(event: Event) {
    event.preventDefault();
    if (opener?.isConnected) opener.focus();
}
async function focusHeading() {
    await nextTick();
    heading.value?.focus();
}
async function select(person: OwnerRsvpMember | null) {
    if (busy.value) return;
    selectedId.value = person?.guest.id ?? null;
    mode.value = 'details';
    clearSecret();
    error.value = '';
    message.value = '';
    await focusHeading();
}
async function stage(next: typeof mode.value) {
    clearSecret();
    error.value = '';
    message.value = '';
    if (next === 'delegation') representativeId.value = member.value?.representative?.id.toString() ?? '';
    mode.value = next;
    await nextTick();
    document.getElementById(id + '-cancel')?.focus();
}
function fail(kind: PersonalFailureKind, retryAfter?: number) {
    clearSecret();
    const messages: Record<PersonalFailureKind, string> = {
        stale_access: 'RSVP access changed in another window. Refresh access and review the current people before trying again.',
        stale_response: 'This information changed in another window. Refresh access before trying again.',
        unavailable: 'These people or permissions are no longer available. Refresh access or reopen Guest Management.',
        session: 'Your session expired. Reload Guest Management before making changes.',
        validation: 'The change was rejected. Refresh access and review the current representative and link states.',
        network: 'We could not confirm the change. Refresh access before trying again.',
        rate_limited: `Please wait ${Math.max(1, Math.min(3600, Math.ceil(retryAfter || 60)))} seconds before trying again.`,
    };
    error.value = messages[kind];
    blocked.value = kind !== 'rate_limited';
}
async function run<T extends { group: OwnerRsvpGroup }>(
    operation: (signal: AbortSignal) => Promise<PersonalResult<T>>,
    success: (value: T, canReveal: boolean) => Promise<void> | void,
) {
    if (busy.value) return;
    busy.value = true;
    clearSecret();
    const currentSecret = secretVersion;
    message.value = '';
    error.value = '';
    const current = ++request;
    controller = new AbortController();
    try {
        const result = await operation(controller.signal);
        if (current !== request || !props.open) return;
        if (!result.ok) fail(result.kind, result.retryAfter);
        else if (result.value.group.id !== props.group.id) fail('unavailable');
        else {
            synchronize(result.value.group);
            mode.value = 'details';
            blocked.value = false;
            await success(result.value, currentSecret === secretVersion && !document.hidden);
            emit('changed', group.value);
        }
    } catch {
        if (current === request && props.open) fail('network');
    } finally {
        if (current === request && props.open) {
            busy.value = false;
            await nextTick();
            (url.value ? urlField.value : notice.value)?.focus();
        }
    }
}
async function credential(action: CredentialCommand['action']) {
    if (!member.value || blocked.value) return;
    const command: CredentialCommand = {
        guestId: member.value.guest.id,
        action,
        expectedVersion: member.value.link_version,
        expectedAccessVersion: member.value.access_version,
    };
    await run(
        (signal) => props.transport.credential(command, signal),
        (value, canReveal) => {
            if (value.url && canReveal) {
                // The authenticated endpoint supplies the canonical URL; portal host aliases can differ.
                const recovered = new URL(value.url);
                if (!['http:', 'https:'].includes(recovered.protocol) || recovered.username || recovered.password) {
                    fail('unavailable');
                    return;
                }
                url.value = recovered.href;
            }
            message.value =
                action === 'revoke'
                    ? 'Link revoked and outgoing representation removed. Saved attendance responses are preserved.'
                    : 'Link ready for private manual sharing. No invitation has been sent.';
        },
    );
}
async function copyLink() {
    if (!url.value || busy.value || blocked.value) return;
    const current = request;
    const currentSecret = secretVersion;
    busy.value = true;
    error.value = '';
    message.value = '';
    try {
        await navigator.clipboard.writeText(url.value);
        if (current === request && currentSecret === secretVersion && props.open) {
            message.value = 'Link copied. No invitation has been sent.';
        }
    } catch {
        if (current === request && currentSecret === secretVersion && props.open) {
            error.value = 'Clipboard access failed. Select and copy the temporary link below. No invitation has been sent.';
            await nextTick();
            urlField.value?.focus();
            urlField.value?.select();
        }
    } finally {
        if (current === request) busy.value = false;
    }
}
async function delegate() {
    if (!member.value || blocked.value) return;
    const target = member.value;
    const next = representativeId.value === '' ? null : options.value.find((person) => person.guest.id.toString() === representativeId.value);
    if (representativeId.value !== '' && !next) return;
    const affected = members.value.filter((person) => [target.guest.id, target.representative?.id, next?.guest.id].includes(person.guest.id));
    const command: DelegationCommand = {
        dependentId: target.guest.id,
        representativeId: next?.guest.id ?? null,
        expectedDependentLinkVersion: target.link_version,
        expectedAccessVersions: Object.fromEntries(affected.map((person) => [person.guest.id, person.access_version])),
    };
    await run(
        (signal) => props.transport.delegation(command, signal),
        () => {
            message.value = 'Representation updated. Saved attendance responses are preserved. Old personal links are not restored.';
        },
    );
}
async function refresh() {
    await run(
        (signal) => props.transport.refresh(signal),
        () => {
            message.value = 'Current RSVP access refreshed. Review the people before making changes.';
        },
    );
}
function hidden() {
    if (document.hidden) clearSecret();
}
onMounted(() => {
    document.addEventListener('visibilitychange', hidden);
    window.addEventListener('pagehide', clearSecret);
});
onBeforeUnmount(() => {
    request++;
    controller?.abort();
    clearSecret();
    document.removeEventListener('visibilitychange', hidden);
    window.removeEventListener('pagehide', clearSecret);
});
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent
            :aria-busy="busy"
            class="rsvp-access-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-2xl sm:rounded-2xl"
            overlay-class="bg-black/30 backdrop-blur-sm"
            @close-auto-focus="restoreFocus"
            @escape-key-down="busy && $event.preventDefault()"
            @interact-outside="busy && $event.preventDefault()"
        >
            <DialogHeader class="pr-12">
                <DialogTitle
                    ><span ref="heading" tabindex="-1" class="block focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring">{{
                        title
                    }}</span></DialogTitle
                >
                <DialogDescription
                    >Choose who responds for each person. Anyone holding a personal link can answer for its respondent and specifically assigned
                    dependents.</DialogDescription
                >
            </DialogHeader>
            <p class="min-w-0 text-sm font-medium [overflow-wrap:anywhere]">{{ group.label }}</p>
            <p
                v-if="message || error"
                ref="notice"
                :role="error ? 'alert' : 'status'"
                tabindex="-1"
                class="text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
            >
                {{ error || message }}
            </p>
            <Button v-if="blocked" variant="outline" :disabled="busy" @click="refresh">Refresh access</Button>
            <template v-if="!member">
                <p class="text-sm text-muted-foreground">
                    Names and representation changes affect current link holders. Wedding draft changes stay private until published.
                </p>
                <ul v-if="members.length" class="min-w-0 divide-y">
                    <li
                        v-for="person in members"
                        :key="person.guest.id"
                        class="flex min-w-0 flex-col gap-3 py-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1 text-sm [overflow-wrap:anywhere]">
                            <p class="font-medium">{{ person.guest.name }}</p>
                            <p v-if="person.invitation_state === 'withdrawn'" class="text-amber-800">
                                Invitation withdrawn · RSVP access unavailable until reinstated
                            </p>
                            <p v-if="person.mode === 'represented'">Represented by {{ person.representative?.name }} · Uses their RSVP link</p>
                            <p v-else>Independent · Personal link: {{ statusLabel(person.link_status) }}</p>
                            <p v-if="person.dependents.length" class="text-muted-foreground">
                                Represents {{ person.dependents.map((dependent) => dependent.name).join(', ') }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            class="min-h-11 shrink-0"
                            :disabled="busy"
                            :aria-label="'Manage RSVP access for ' + person.guest.name"
                            @click="select(person)"
                            >Manage access</Button
                        >
                    </li>
                </ul>
                <p v-else class="py-4 text-sm text-muted-foreground">
                    No saved members are available. Return to Guest Management to review this group.
                </p>
                <DialogFooter><Button variant="outline" :disabled="busy" @click="close(false)">Done</Button></DialogFooter>
            </template>
            <template v-else>
                <p class="min-w-0 font-medium [overflow-wrap:anywhere]">{{ member.guest.name }}</p>
                <template v-if="mode === 'details'">
                    <p v-if="member.invitation_state === 'withdrawn'" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                        RSVP access unavailable while this invitation is withdrawn. Its saved response is retained. Reinstate it in Guest Management
                        before arranging fresh access.
                    </p>
                    <p class="text-sm [overflow-wrap:anywhere]">
                        {{
                            member.mode === 'represented'
                                ? 'Represented by ' + member.representative?.name
                                : 'Independent · Personal link: ' + statusLabel(member.link_status)
                        }}
                    </p>
                    <p v-if="member.mode === 'independent'" class="text-sm text-muted-foreground [overflow-wrap:anywhere]">
                        This link answers for {{ [member.guest.name, ...member.dependents.map((person) => person.name)].join(' and ') }}.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Uses their representative's RSVP link. No independent link is available while represented.
                    </p>
                    <div v-if="member.invitation_state !== 'withdrawn' && member.mode === 'independent'" class="flex flex-wrap gap-2">
                        <Button v-if="member.link_status === 'not_generated'" :disabled="busy || blocked" @click="credential('generate')"
                            >Generate link</Button
                        >
                        <Button v-if="member.link_status === 'active' && !url" :disabled="busy || blocked" @click="credential('copy')"
                            >Recover link</Button
                        >
                        <Button v-if="url" :disabled="busy || blocked" @click="copyLink">Copy link</Button>
                        <Button v-if="member.link_status === 'revoked'" :disabled="busy || blocked" @click="stage('regenerate')"
                            >Regenerate link</Button
                        >
                        <DropdownMenu v-if="member.link_status === 'active'">
                            <DropdownMenuTrigger as-child
                                ><Button variant="outline" :disabled="busy || blocked">More link actions</Button></DropdownMenuTrigger
                            >
                            <DropdownMenuContent align="end" @close-auto-focus="mode !== 'details' && $event.preventDefault()">
                                <DropdownMenuItem @select="stage('regenerate')">Regenerate link</DropdownMenuItem>
                                <DropdownMenuItem @select="stage('revoke')">Revoke link</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <div v-if="url && member.invitation_state !== 'withdrawn'" class="min-w-0 space-y-2">
                        <label :for="id + '-url'" class="text-sm font-medium">Personal invitation URL</label>
                        <input
                            :id="id + '-url'"
                            ref="urlField"
                            :value="url"
                            readonly
                            spellcheck="false"
                            autocomplete="off"
                            class="h-11 w-full min-w-0 rounded-md border bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                            @focus="urlField?.select()"
                        />
                        <p class="text-xs text-muted-foreground">
                            Keep this bearer link private. It is cleared when you leave these details or close the dialog.
                        </p>
                    </div>
                    <Button
                        v-if="member.invitation_state !== 'withdrawn'"
                        variant="outline"
                        :disabled="busy || blocked || member.dependents.length > 0"
                        @click="stage('delegation')"
                        >{{ member.mode === 'represented' ? 'Change or remove representative' : 'Assign a representative' }}</Button
                    >
                    <p v-if="member.dependents.length" class="text-sm text-muted-foreground">
                        Reassign or remove this person's dependents first before choosing a representative for them.
                    </p>
                    <DialogFooter><Button variant="outline" :disabled="busy" @click="select(null)">Back to group</Button></DialogFooter>
                </template>
                <template v-else-if="mode === 'delegation'">
                    <label :for="id + '-representative'" class="text-sm font-medium">Representative</label>
                    <select
                        :id="id + '-representative'"
                        v-model="representativeId"
                        :disabled="busy || blocked"
                        class="min-h-11 w-full min-w-0 max-w-full rounded-md border bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        <option value="">Independent — no representative</option>
                        <option v-for="person in options" :key="person.guest.id" :value="person.guest.id.toString()">{{ person.guest.name }}</option>
                    </select>
                    <p class="text-sm text-muted-foreground">
                        Only independent members of this group with active personal links are eligible. No eligible person? Go back and generate their
                        link first.
                    </p>
                    <DialogFooter
                        ><Button :id="id + '-cancel'" variant="outline" @click="stage('details')">Cancel</Button
                        ><Button
                            :disabled="busy || blocked || representativeId === (member.representative?.id.toString() ?? '')"
                            @click="stage('confirm-delegation')"
                            >Review change</Button
                        ></DialogFooter
                    >
                </template>
                <template v-else>
                    <p v-if="mode === 'revoke'" class="text-sm [overflow-wrap:anywhere]">
                        The link stops working immediately.
                        {{
                            member.dependents.length
                                ? 'Outgoing representation will be removed for: ' +
                                  member.dependents.map((person) => person.name).join(', ') +
                                  '. They will need a new designation or personal link.'
                                : ''
                        }}
                        Saved attendance responses are preserved.
                    </p>
                    <p v-else-if="mode === 'regenerate'" class="text-sm">
                        The previous link stops working immediately. Existing dependent assignments stay in place. Share the replacement privately; no
                        invitation is sent.
                    </p>
                    <template v-else>
                        <p class="text-sm [overflow-wrap:anywhere]">
                            {{
                                representativeId
                                    ? 'Allow ' +
                                      options.find((person) => person.guest.id.toString() === representativeId)?.guest.name +
                                      ' to answer for ' +
                                      member.guest.name +
                                      '?'
                                    : 'Remove the representative for ' + member.guest.name + '?'
                            }}
                        </p>
                        <p v-if="representativeId && member.link_status === 'active'" class="text-sm font-medium">
                            This person's existing personal link will stop working immediately.
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Saved attendance responses and timestamps are preserved. Removing a representative does not reactivate an old personal
                            link; issue a new one explicitly when needed.
                        </p>
                    </template>
                    <DialogFooter
                        ><Button :id="id + '-cancel'" variant="outline" :disabled="busy" @click="stage('details')">Cancel</Button
                        ><Button
                            :disabled="busy || blocked"
                            @click="mode === 'confirm-delegation' ? delegate() : credential(mode as 'revoke' | 'regenerate')"
                            >{{
                                busy
                                    ? 'Working…'
                                    : mode === 'confirm-delegation'
                                      ? 'Confirm representation change'
                                      : mode === 'revoke'
                                        ? 'Revoke this link'
                                        : 'Regenerate this link'
                            }}</Button
                        ></DialogFooter
                    >
                </template>
            </template>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
:global(.rsvp-access-dialog button) {
    min-width: 2.75rem;
    min-height: 2.75rem;
}
@media (prefers-reduced-motion: reduce) {
    :global(body:has(.rsvp-access-dialog) [data-state]) {
        animation: none !important;
        transition: none !important;
    }
}
</style>
