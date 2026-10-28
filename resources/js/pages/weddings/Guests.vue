<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import GuestNameTags from '@/components/wedding/GuestNameTags.vue';
import InvitationLifecycleDialog from '@/components/wedding/InvitationLifecycleDialog.vue';
import RsvpAccessDialog from '@/components/wedding/RsvpAccessDialog.vue';
import { usePortalFilters } from '@/composables/usePortalFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { ownerRsvpTransport } from '@/lib/personal-rsvp-http';
import type {
    AccountSummary,
    AttendanceGuest,
    AttendancePage,
    AttendanceSummary,
    BreadcrumbItem,
    GuestSummary,
    InvitationGroupSummary,
} from '@/types';
import type { OwnerRsvpGroup, OwnerRsvpTransport } from '@/types/personal-rsvp';
import { Head, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal, Plus, Users } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    account: AccountSummary;
    wedding: { id: number; partner_one_name: string; partner_two_name: string };
    groups: AttendancePage<InvitationGroupSummary> | null;
    individuals: AttendancePage<AttendanceGuest> | null;
    filters: {
        search: string;
        status: 'all' | 'attending' | 'declined' | 'pending';
        view: 'groups' | 'guests';
        invitation_state: 'active' | 'withdrawn' | 'all';
    };
    summary: AttendanceSummary;
    matchedGuests: number;
    refreshedAt: string;
    totals: { groups: number; people: number; active_people: number; withdrawn_people: number };
    withdrawalEnabled: boolean;
    maxGuestsPerGroup: number;
}>();
type Mode = 'create' | 'group' | 'add' | 'rename' | 'deleteGuest' | 'deleteGroup';
const parameters = { account: props.account.id, wedding: props.wedding.id };
const indexUrl = route('accounts.weddings.guests.index', parameters);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Wedding overview', href: route('accounts.weddings.show', parameters) },
    { title: 'Guests & RSVPs', href: indexUrl },
];
const {
    search,
    status,
    invitationState,
    filtering: searching,
    error: searchError,
    visit: findGroups,
    clear: clearSearch,
    cancel: cancelFiltering,
} = usePortalFilters({ url: indexUrl, filters: () => props.filters });
const rows = computed(() => props.individuals ?? props.groups);
const cards: { key: keyof AttendanceSummary; label: string }[] = [
    { key: 'invited', label: 'Total invited' },
    { key: 'attending', label: 'Attending' },
    { key: 'declined', label: 'Declined' },
    { key: 'pending', label: 'Pending' },
];
const updated = computed(() => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.refreshedAt)));
function respondedAt(value: string): string {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
const expandedGroups = ref(new Set<number>());
const guestTags = ref<InstanceType<typeof GuestNameTags> | null>(null);
function isExpanded(group: InvitationGroupSummary): boolean {
    return !!props.filters.search || expandedGroups.value.has(group.id);
}
function displayedGuests(group: InvitationGroupSummary): GuestSummary[] {
    return isExpanded(group) ? group.guests : group.guests.slice(0, 5);
}
function toggleMembers(group: InvitationGroupSummary): void {
    if (expandedGroups.value.has(group.id)) expandedGroups.value.delete(group.id);
    else expandedGroups.value.add(group.id);
}
const accessOpen = ref(false);
const accessLoading = ref(false);
const accessError = ref('');
const accessGroup = ref<OwnerRsvpGroup | null>(null);
const accessTransport = ref<OwnerRsvpTransport | null>(null);
const accessTrigger = ref<HTMLElement | null>(null);
let accessController: AbortController | null = null;
async function openAccess(group: { id: number }, trigger?: HTMLElement) {
    if (accessLoading.value) return;
    accessTrigger.value = trigger ?? document.getElementById('group-actions-' + group.id);
    accessLoading.value = true;
    accessError.value = '';
    accessController = new AbortController();
    const controller = accessController;
    const transport = ownerRsvpTransport(props.account.id, props.wedding.id, group.id);
    try {
        const result = await transport.refresh(controller.signal);
        if (controller.signal.aborted) return;
        if (result.ok) {
            accessGroup.value = result.value.group;
            accessTransport.value = transport;
            accessOpen.value = true;
        } else accessError.value = 'RSVP access could not be loaded. Refresh the page and try again.';
    } finally {
        if (!controller.signal.aborted) accessLoading.value = false;
    }
}
const stopAccessNavigation = router.on('start', () => {
    accessController?.abort();
    accessOpen.value = false;
    accessGroup.value = null;
    accessTransport.value = null;
    accessLoading.value = false;
});
onBeforeUnmount(() => {
    accessController?.abort();
    stopAccessNavigation();
});
const notice = ref('');
const open = ref(false);
const mode = ref<Mode>('create');
const selectedGroup = ref<InvitationGroupSummary | null>(null);
const selectedGuest = ref<GuestSummary | null>(null);
const returnFocus = ref<HTMLElement | null>(null);
const heading = ref<HTMLHeadingElement | null>(null);
const groupForm = useForm({ label: '', guests: [] as { name: string }[] });
const guestForm = useForm({ name: '' });
const deleteForm = useForm({});
const busy = computed(() => groupForm.processing || guestForm.processing || deleteForm.processing);
const groupMode = computed(() => mode.value === 'create' || mode.value === 'group');
const deleting = computed(() => mode.value === 'deleteGuest' || mode.value === 'deleteGroup');
const title = computed(
    () =>
        ({
            create: 'New invitation group',
            group: 'Edit invitation group',
            add: 'Add a guest',
            rename: 'Edit guest',
            deleteGuest: 'Remove this guest?',
            deleteGroup: 'Delete this invitation group?',
        })[mode.value],
);
const description = computed(() => {
    if (mode.value === 'create') return 'Create a family, couple or individual invitation. Add a name for every invited person.';
    if (mode.value === 'group') return 'Update the group label. This does not send an invitation.';
    if (mode.value === 'deleteGroup')
        return 'This permanently deletes the group and all its named guests. Your wedding and invitation website will be kept.';
    if (mode.value === 'deleteGuest') return 'This permanently removes this person from the guest list. It cannot be undone.';
    return 'Each invited person has a name. Adding a guest does not record an RSVP.';
});
const formErrors = computed(() => (groupMode.value ? groupForm.errors : deleting.value ? deleteForm.errors : guestForm.errors));
const errorEntries = computed(() => Object.entries(formErrors.value));
const guestCount = computed(() => selectedGroup.value?.guests_count ?? 0);
const lifecycleTarget = ref<{ groupId: number; guestId: number; action: 'withdraw' | 'reinstate' } | null>(null);
const lifecycleDialogOpen = computed({
    get: () => lifecycleTarget.value !== null,
    set: (value: boolean) => {
        if (!value) lifecycleTarget.value = null;
    },
});
function manageInvitation(group: InvitationGroupSummary, guest: GuestSummary): void {
    if (!props.withdrawalEnabled || !guest.invitation_state) return;
    lifecycleTarget.value = { groupId: group.id, guestId: guest.id, action: guest.invitation_state === 'active' ? 'withdraw' : 'reinstate' };
}
watch(
    () => open.value || accessOpen.value,
    (dialogOpen) => {
        if (dialogOpen) cancelFiltering();
        else if (search.value.trim() !== props.filters.search) findGroups();
    },
    { flush: 'sync' },
);
watch(search, () => {
    notice.value = '';
});
watch(
    () => [props.filters.search, props.filters.status, props.filters.invitation_state],
    () => expandedGroups.value.clear(),
);

function showDialog(nextMode: Mode, group: InvitationGroupSummary | null, guest: GuestSummary | null, trigger?: HTMLElement) {
    mode.value = nextMode;
    selectedGroup.value = group;
    selectedGuest.value = guest;
    returnFocus.value = trigger ?? (document.activeElement as HTMLElement);
    groupForm
        .defaults({
            label: group?.label ?? '',
            guests: [] as { name: string }[],
        })
        .reset();
    groupForm.clearErrors();
    guestForm.reset();
    guestForm.clearErrors();
    deleteForm.clearErrors();
    guestForm.name = guest?.name ?? '';
    notice.value = '';
    open.value = true;
}
function groupAction(nextMode: Mode, group: InvitationGroupSummary) {
    showDialog(nextMode, group, null, document.getElementById('group-actions-' + group.id) ?? undefined);
}
function setOpen(value: boolean) {
    if (!busy.value) open.value = value;
}
function restoreFocus(event: Event) {
    event.preventDefault();
    const target = returnFocus.value?.isConnected ? returnFocus.value : heading.value;
    target?.focus();
}
function errorFor(key: string): string | undefined {
    return (formErrors.value as Record<string, string>)[key];
}
async function focusError() {
    await nextTick();
    const key = errorEntries.value[0]?.[0];
    document.getElementById('field-' + key)?.focus();
}
function submit() {
    cancelFiltering();
    if (mode.value === 'create' && !guestTags.value?.commitPending()) return;
    const options = {
        preserveScroll: true,
        onError: focusError,
        onSuccess: () => {
            open.value = false;
            notice.value = deleting.value ? 'Guest list updated.' : mode.value === 'create' ? 'Invitation group created.' : 'Changes saved.';
        },
    };
    const groupParameters = { ...parameters, invitationGroup: selectedGroup.value?.id };
    if (mode.value === 'create') {
        groupForm.transform((data) => data).post(route('accounts.weddings.invitation-groups.store', parameters), options);
    } else if (mode.value === 'group') {
        groupForm.transform(({ label }) => ({ label })).patch(route('accounts.weddings.invitation-groups.update', groupParameters), options);
    } else if (mode.value === 'add') {
        guestForm.post(route('accounts.weddings.guests.store', groupParameters), options);
    } else if (mode.value === 'rename') {
        guestForm.patch(route('accounts.weddings.guests.update', { ...groupParameters, guest: selectedGuest.value?.id }), options);
    } else {
        const url =
            mode.value === 'deleteGroup'
                ? route('accounts.weddings.invitation-groups.destroy', groupParameters)
                : route('accounts.weddings.guests.destroy', { ...groupParameters, guest: selectedGuest.value?.id });
        deleteForm.delete(url, options);
    }
}
function confirmGuestRemoval() {
    mode.value = 'deleteGuest';
    guestForm.clearErrors();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Guests & RSVPs" />
        <div class="guest-management mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-8 p-4 sm:p-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="break-words font-mono text-xs uppercase tracking-widest text-muted-foreground [overflow-wrap:anywhere]">
                        {{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}
                    </p>
                    <h1
                        ref="heading"
                        tabindex="-1"
                        id="guest-management-heading"
                        class="mt-2 rounded-sm text-2xl font-medium tracking-tight focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Guests &amp; RSVPs
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">Manage invitations and see how your guests have responded, all in one place.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" :disabled="searching" @click="findGroups(filters.view, rows?.current_page ?? 1)">{{
                        searching ? 'Refreshing…' : 'Refresh'
                    }}</Button>
                    <Button @click="showDialog('create', null, null, $event.currentTarget as HTMLElement)"
                        ><Plus class="mr-2 h-4 w-4" aria-hidden="true" />New invitation group</Button
                    >
                </div>
            </header>

            <section aria-labelledby="guest-hub-totals-heading">
                <h2 id="guest-hub-totals-heading" class="sr-only">Private wedding-wide RSVP totals</h2>
                <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div v-for="card in cards" :key="card.key" class="min-w-0 rounded-xl border border-[#ddcfc0] bg-background/80 px-4 py-4">
                        <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ card.label }}</dt>
                        <dd :data-testid="'attendance-' + card.key" class="mt-2 text-3xl font-medium tabular-nums">
                            <span :data-testid="card.key === 'invited' ? 'people-total' : undefined">{{ summary[card.key] }}</span>
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-muted-foreground">
                    Wedding-wide totals include {{ withdrawalEnabled ? 'active invitations only' : 'all invited guests' }} and do not change with
                    filters. <span data-testid="group-total">{{ totals.groups }}</span> invitation {{ totals.groups === 1 ? 'group' : 'groups'
                    }}<span v-if="withdrawalEnabled">
                        · <span data-testid="withdrawn-total">{{ totals.withdrawn_people }}</span> withdrawn guests retained in history</span
                    >. Updated <time :datetime="refreshedAt">{{ updated }}</time
                    >.
                </p>
            </section>
            <p v-if="notice" role="status" class="text-sm">{{ notice }}</p>
            <p v-if="accessLoading" role="status" class="text-sm">Loading RSVP access…</p>
            <p v-if="accessError" role="alert" class="text-sm">{{ accessError }}</p>

            <section aria-label="Guests and RSVPs" class="min-w-0">
                <form role="search" class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent>
                    <div class="w-full sm:max-w-sm">
                        <Label for="guest-search">Search groups or guest names</Label>
                        <Input id="guest-search" v-model="search" type="search" maxlength="255" class="mt-2" />
                    </div>
                    <div class="w-full sm:w-auto">
                        <Label for="guest-rsvp-status">RSVP status</Label>
                        <select
                            id="guest-rsvp-status"
                            v-model="status"
                            class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="all">All</option>
                            <option value="attending">Attending</option>
                            <option value="declined">Declined</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div v-if="withdrawalEnabled" class="w-full sm:w-auto">
                        <Label for="invitation-state">Invitation status</Label>
                        <select
                            id="invitation-state"
                            v-model="invitationState"
                            class="mt-2 flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="active">Active</option>
                            <option value="withdrawn">Withdrawn</option>
                            <option value="all">All retained guests</option>
                        </select>
                    </div>
                    <Button
                        v-if="search || filters.search || status !== 'all' || invitationState !== 'active'"
                        type="button"
                        variant="ghost"
                        @click="clearSearch"
                        >Clear filters</Button
                    >
                </form>
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div role="group" aria-label="Guest view" class="flex gap-2">
                        <Button
                            :variant="filters.view === 'groups' ? 'default' : 'outline'"
                            :aria-pressed="filters.view === 'groups'"
                            :disabled="searching"
                            @click="findGroups('groups')"
                            >Invitation groups</Button
                        >
                        <Button
                            :variant="filters.view === 'guests' ? 'default' : 'outline'"
                            :aria-pressed="filters.view === 'guests'"
                            :disabled="searching"
                            @click="findGroups('guests')"
                            >Individual guests</Button
                        >
                    </div>
                    <p role="status" class="text-sm text-muted-foreground">
                        {{ matchedGuests }} matching guests<span v-if="searching"> · Loading…</span>
                    </p>
                </div>
                <div v-if="searchError" role="alert" class="mb-4 flex flex-wrap items-center gap-3 text-sm">
                    <p>{{ searchError }}</p>
                    <Button variant="outline" :disabled="searching" @click="findGroups()">Try again</Button>
                </div>
                <p
                    v-if="filters.search || filters.status !== 'all' || filters.invitation_state !== 'active'"
                    class="mb-4 text-sm text-muted-foreground"
                >
                    {{ rows?.total ?? 0 }} matching {{ filters.view === 'groups' ? 'groups' : 'guests' }}. Group counts reflect the selected
                    invitation state; the four totals above remain wedding-wide.
                </p>
                <div v-if="!rows?.data.length" class="flex flex-col items-start gap-3 py-10">
                    <Users class="h-6 w-6 text-muted-foreground" aria-hidden="true" />
                    <h2 class="font-medium">{{ summary.invited === 0 ? 'Start your guest list' : 'No guests match these filters' }}</h2>
                    <p class="max-w-md text-sm text-muted-foreground">
                        {{
                            summary.invited === 0
                                ? 'Use New invitation group to add a family, couple or individual. Every invited seat needs a name.'
                                : 'Try another name, RSVP status or invitation state.'
                        }}
                    </p>
                </div>
                <table v-else-if="groups" class="w-full table-fixed text-left text-sm" :aria-busy="searching">
                    <caption class="sr-only">
                        Invitation groups and their named guests. Select a guest name to edit or remove that person.
                    </caption>
                    <thead class="border-b font-mono text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th scope="col" class="pb-3 pr-3 font-normal">Group / guests</th>
                            <th scope="col" class="w-16 pb-3 text-right font-normal">People</th>
                            <th scope="col" class="w-14 pb-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="group in groups.data" :key="group.id" class="align-top hover:bg-muted/30">
                            <td class="min-w-0 py-5 pr-3">
                                <p class="font-medium [overflow-wrap:anywhere]">{{ group.label }}</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ group.attending }} attending · {{ group.declined }} declined · {{ group.pending }} pending
                                </p>
                                <ul :id="'members-' + group.id" class="mt-2 flex flex-wrap gap-x-3 gap-y-1" aria-label="Named guests">
                                    <li
                                        v-for="guest in displayedGuests(group)"
                                        :key="guest.id"
                                        class="flex min-w-0 max-w-full flex-wrap items-center gap-x-2"
                                    >
                                        <button
                                            type="button"
                                            class="min-h-9 rounded-sm text-left text-muted-foreground underline decoration-border underline-offset-4 [overflow-wrap:anywhere] hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                                            :aria-label="'Edit guest ' + guest.name"
                                            @click="showDialog('rename', group, guest, $event.currentTarget as HTMLElement)"
                                        >
                                            {{ guest.name }}
                                        </button>
                                        <span class="text-xs capitalize text-muted-foreground"
                                            >{{ withdrawalEnabled ? guest.invitation_state + ' · ' : '' }}{{ guest.rsvp_status }}</span
                                        >
                                        <time v-if="guest.responded_at" :datetime="guest.responded_at" class="text-xs text-muted-foreground">{{
                                            respondedAt(guest.responded_at)
                                        }}</time>
                                        <Button
                                            v-if="withdrawalEnabled"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="min-h-9"
                                            @click="manageInvitation(group, guest)"
                                        >
                                            {{ guest.invitation_state === 'withdrawn' ? 'Reinstate' : 'Withdraw' }}
                                        </Button>
                                    </li>
                                </ul>
                                <div
                                    v-if="group.guests.length > 5 && !filters.search"
                                    class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span v-if="!isExpanded(group)" aria-hidden="true">…</span>
                                    <button
                                        type="button"
                                        :aria-expanded="isExpanded(group)"
                                        :aria-controls="'members-' + group.id"
                                        class="min-h-9 rounded-sm underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                                        @click="toggleMembers(group)"
                                    >
                                        {{ isExpanded(group) ? 'Show less' : 'Show ' + (group.guests.length - 5) + ' more' }}
                                    </button>
                                </div>
                            </td>
                            <td class="py-5 text-right tabular-nums">{{ group.guests_count }}</td>
                            <td class="py-3 text-right">
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child
                                        ><Button
                                            :id="'group-actions-' + group.id"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="'Actions for ' + group.label"
                                            ><MoreHorizontal class="h-4 w-4" aria-hidden="true" /></Button
                                    ></DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" @close-auto-focus="(open || accessOpen) && $event.preventDefault()">
                                        <DropdownMenuItem :disabled="accessLoading" @select="openAccess(group)">RSVP Access</DropdownMenuItem>
                                        <DropdownMenuItem @select="groupAction('group', group)">Edit group</DropdownMenuItem>
                                        <DropdownMenuItem :disabled="group.guests_count >= maxGuestsPerGroup" @select="groupAction('add', group)"
                                            >Add guest</DropdownMenuItem
                                        >
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem v-if="group.link_version === 0" @select="groupAction('deleteGroup', group)"
                                            >Delete group</DropdownMenuItem
                                        >
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="individuals" class="w-full table-fixed text-left text-sm" :aria-busy="searching">
                    <caption class="sr-only">
                        Individual guests, invitation groups and saved RSVP responses
                    </caption>
                    <thead class="border-b text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th scope="col" class="pb-3 pr-3 font-normal">Guest</th>
                            <th scope="col" class="hidden pb-3 pr-3 font-normal sm:table-cell">Invitation group</th>
                            <th scope="col" class="w-24 pb-3 pr-2 font-normal">RSVP</th>
                            <th scope="col" class="hidden w-44 pb-3 font-normal lg:table-cell">Responded</th>
                            <th scope="col" class="w-24 pb-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="guest in individuals.data" :key="guest.id" class="align-top">
                            <td class="min-w-0 py-4 pr-3">
                                <p class="font-medium [overflow-wrap:anywhere]">{{ guest.name }}</p>
                                <p class="mt-1 text-xs text-muted-foreground sm:hidden">{{ guest.group }}</p>
                                <p v-if="withdrawalEnabled" class="mt-1 text-xs capitalize text-muted-foreground">{{ guest.invitationState }}</p>
                                <p class="mt-1 text-xs text-muted-foreground lg:hidden">
                                    {{ guest.respondedAt ? respondedAt(guest.respondedAt) : 'No response yet' }}
                                </p>
                            </td>
                            <td class="hidden py-4 pr-3 text-muted-foreground sm:table-cell">{{ guest.group }}</td>
                            <td class="py-4 pr-2 capitalize">{{ guest.status }}</td>
                            <td class="hidden py-4 text-muted-foreground lg:table-cell">
                                <time v-if="guest.respondedAt" :datetime="guest.respondedAt">{{ respondedAt(guest.respondedAt) }}</time>
                                <span v-else>No response yet</span>
                            </td>
                            <td class="py-3 text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :disabled="accessLoading"
                                    :aria-label="'RSVP access for ' + guest.name"
                                    @click="openAccess({ id: guest.groupId }, $event.currentTarget as HTMLElement)"
                                    >RSVP access</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
                <nav v-if="rows && rows.last_page > 1" aria-label="Guest list pages" class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Page {{ rows.current_page }} of {{ rows.last_page }}</p>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            :disabled="searching || !rows.prev_page_url"
                            @click="findGroups(filters.view, rows.current_page - 1)"
                            >Previous</Button
                        >
                        <Button
                            variant="outline"
                            :disabled="searching || !rows.next_page_url"
                            @click="findGroups(filters.view, rows.current_page + 1)"
                            >Next</Button
                        >
                    </div>
                </nav>
            </section>

            <Dialog :open="open" @update:open="setOpen">
                <DialogContent
                    overlay-class="bg-black/30 backdrop-blur-sm"
                    class="guest-dialog max-h-[calc(100svh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-2xl"
                    @close-auto-focus="restoreFocus"
                    @escape-key-down="busy && $event.preventDefault()"
                    @interact-outside="busy && $event.preventDefault()"
                >
                    <DialogHeader
                        ><DialogTitle>{{ title }}</DialogTitle
                        ><DialogDescription>{{ description }}</DialogDescription></DialogHeader
                    >
                    <form class="flex min-w-0 flex-col gap-5" @submit.prevent="submit">
                        <div v-if="errorEntries.length" role="alert" class="space-y-1 text-sm">
                            <p v-for="[key, message] in errorEntries" :key="key">{{ message }}</p>
                        </div>
                        <template v-if="groupMode">
                            <div class="space-y-2">
                                <Label for="field-label">Group label</Label
                                ><Input
                                    id="field-label"
                                    v-model="groupForm.label"
                                    maxlength="255"
                                    required
                                    placeholder="e.g. Santos Family"
                                    :aria-invalid="!!errorFor('label')"
                                />
                            </div>
                            <GuestNameTags
                                v-if="mode === 'create'"
                                ref="guestTags"
                                v-model="groupForm.guests"
                                :max="maxGuestsPerGroup"
                                :disabled="busy"
                                :errors="groupForm.errors"
                            />
                        </template>
                        <div v-else-if="!deleting" class="space-y-2">
                            <p class="text-sm text-muted-foreground [overflow-wrap:anywhere]">{{ selectedGroup?.label }}</p>
                            <Label for="field-name">Guest name</Label
                            ><Input id="field-name" v-model="guestForm.name" maxlength="255" required :aria-invalid="!!errorFor('name')" />
                            <template v-if="mode === 'rename'">
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="guestCount <= 1 || selectedGuest?.has_response || busy"
                                    @click="confirmGuestRemoval"
                                    >Remove guest</Button
                                >
                                <p v-if="selectedGuest?.has_response" class="text-xs text-muted-foreground">
                                    This guest has an RSVP response and cannot be removed. Renaming preserves their response.
                                </p>
                                <p v-else-if="guestCount <= 1" class="text-xs text-muted-foreground">
                                    A group needs at least one guest. Delete the group to remove its final person.
                                </p>
                            </template>
                        </div>
                        <p v-else class="text-sm font-medium [overflow-wrap:anywhere]">
                            {{ mode === 'deleteGroup' ? selectedGroup?.label + ' · ' + guestCount + ' invited people' : selectedGuest?.name }}
                        </p>
                        <DialogFooter>
                            <Button type="button" variant="outline" :disabled="busy" @click="open = false">Cancel</Button>
                            <Button type="submit" :disabled="busy">{{
                                busy
                                    ? 'Saving…'
                                    : mode === 'create'
                                      ? 'Create group'
                                      : mode === 'add'
                                        ? 'Add guest'
                                        : mode === 'deleteGroup'
                                          ? 'Delete group permanently'
                                          : mode === 'deleteGuest'
                                            ? 'Remove guest permanently'
                                            : 'Save changes'
                            }}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
        <RsvpAccessDialog
            v-if="accessGroup && accessTransport"
            v-model:open="accessOpen"
            :group="accessGroup"
            :transport="accessTransport"
            :return-focus="accessTrigger"
            @changed="accessGroup = $event"
        />
        <InvitationLifecycleDialog
            v-if="lifecycleTarget"
            v-model:open="lifecycleDialogOpen"
            :account-id="account.id"
            :wedding-id="wedding.id"
            :group-id="lifecycleTarget.groupId"
            :guest-id="lifecycleTarget.guestId"
            :action="lifecycleTarget.action"
            @changed="router.reload({ only: ['groups', 'individuals', 'filters', 'totals', 'summary', 'matchedGuests', 'refreshedAt'] })"
        />
    </AppLayout>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    :deep(*),
    :global(body:has(.guest-management) [data-state]) {
        animation: none !important;
        transition: none !important;
    }
}
</style>
