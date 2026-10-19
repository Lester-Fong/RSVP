<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InvitationLinkDialog from '@/components/wedding/InvitationLinkDialog.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AccountSummary, BreadcrumbItem, GuestSummary, InvitationGroupSummary } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal, Plus, Users, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    account: AccountSummary;
    wedding: { id: number; partner_one_name: string; partner_two_name: string };
    groups: {
        data: InvitationGroupSummary[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { search: string };
    totals: { groups: number; people: number };
    maxGuestsPerGroup: number;
}>();
type Mode = 'create' | 'group' | 'add' | 'rename' | 'deleteGuest' | 'deleteGroup';
const parameters = { account: props.account.id, wedding: props.wedding.id };
const indexUrl = route('accounts.weddings.guests.index', parameters);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Wedding overview', href: route('accounts.weddings.show', parameters) },
    { title: 'Guest management', href: indexUrl },
];
const search = ref(props.filters.search);
const linkOpen = ref(false);
const linkGroupId = ref<number | null>(null);
const linkGroup = computed(() => props.groups.data.find((group) => group.id === linkGroupId.value) ?? null);
function openInvitationLink(group: InvitationGroupSummary) {
    linkGroupId.value = group.id;
    linkOpen.value = true;
}
function refreshLinks() {
    router.reload({ only: ['groups'] });
}
const searching = ref(false);
const notice = ref('');
const open = ref(false);
const mode = ref<Mode>('create');
const selectedGroup = ref<InvitationGroupSummary | null>(null);
const selectedGuest = ref<GuestSummary | null>(null);
const returnFocus = ref<HTMLElement | null>(null);
const heading = ref<HTMLHeadingElement | null>(null);
const groupForm = useForm({ label: '', contact_email: '', contact_phone: '', guests: [{ name: '' }] });
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
    if (mode.value === 'group') return 'Update the label and optional contact information. This does not send an invitation.';
    if (mode.value === 'deleteGroup')
        return 'This permanently deletes the group and all its named guests. Your wedding and invitation website will be kept.';
    if (mode.value === 'deleteGuest') return 'This permanently removes this person from the guest list. It cannot be undone.';
    return 'Each invited person has a name. Adding a guest does not record an RSVP.';
});
const formErrors = computed(() => (groupMode.value ? groupForm.errors : deleting.value ? deleteForm.errors : guestForm.errors));
const errorEntries = computed(() => Object.entries(formErrors.value));
const guestCount = computed(() => selectedGroup.value?.guests_count ?? 0);
watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

function showDialog(nextMode: Mode, group: InvitationGroupSummary | null, guest: GuestSummary | null, trigger?: HTMLElement) {
    mode.value = nextMode;
    selectedGroup.value = group;
    selectedGuest.value = guest;
    returnFocus.value = trigger ?? (document.activeElement as HTMLElement);
    groupForm
        .defaults({
            label: group?.label ?? '',
            contact_email: group?.contact_email ?? '',
            contact_phone: group?.contact_phone ?? '',
            guests: [{ name: '' }],
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
        groupForm
            .transform(({ label, contact_email, contact_phone }) => ({ label, contact_email, contact_phone }))
            .patch(route('accounts.weddings.invitation-groups.update', groupParameters), options);
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
function findGroups() {
    notice.value = '';
    router.get(indexUrl, search.value.trim() ? { search: search.value.trim() } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            searching.value = true;
        },
        onFinish: () => {
            searching.value = false;
        },
    });
}
function confirmGuestRemoval() {
    mode.value = 'deleteGuest';
    guestForm.clearErrors();
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Guest management" />
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
                        Guest management
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">Organize named guests into shared invitations. No invitations are sent here.</p>
                </div>
                <Button @click="showDialog('create', null, null, $event.currentTarget as HTMLElement)"
                    ><Plus class="mr-2 h-4 w-4" aria-hidden="true" />New invitation group</Button
                >
            </header>

            <dl class="flex flex-wrap gap-x-12 gap-y-4 border-y py-5" aria-label="Wedding guest totals">
                <div>
                    <dt class="font-mono text-xs uppercase tracking-widest text-muted-foreground">Invitation groups</dt>
                    <dd data-testid="group-total" class="mt-2 text-2xl tabular-nums">{{ totals.groups }}</dd>
                </div>
                <div>
                    <dt class="font-mono text-xs uppercase tracking-widest text-muted-foreground">Invited people</dt>
                    <dd data-testid="people-total" class="mt-2 text-2xl tabular-nums">{{ totals.people }}</dd>
                </div>
            </dl>
            <p v-if="notice" role="status" class="text-sm">{{ notice }}</p>

            <section aria-label="Invitation groups" class="min-w-0">
                <form role="search" class="mb-6 flex flex-wrap items-end gap-2" @submit.prevent="findGroups">
                    <div class="w-full sm:max-w-sm">
                        <Label for="guest-search">Search groups or guest names</Label>
                        <Input id="guest-search" v-model="search" type="search" maxlength="255" class="mt-2" />
                    </div>
                    <Button variant="outline" :disabled="searching">{{ searching ? 'Searching…' : 'Search' }}</Button>
                    <Button
                        v-if="filters.search"
                        type="button"
                        variant="ghost"
                        :disabled="searching"
                        @click="
                            search = '';
                            findGroups();
                        "
                        >Clear search</Button
                    >
                </form>
                <p v-if="filters.search" class="mb-4 text-sm text-muted-foreground">
                    {{ groups.total }} matching groups. Totals above include your entire wedding.
                </p>
                <div v-if="!groups.data.length" class="flex flex-col items-start gap-3 py-10">
                    <Users class="h-6 w-6 text-muted-foreground" aria-hidden="true" />
                    <h2 class="font-medium">{{ filters.search ? 'No matching invitations' : 'Start your guest list' }}</h2>
                    <p class="max-w-md text-sm text-muted-foreground">
                        {{
                            filters.search
                                ? 'Try another group label or guest name.'
                                : 'Use New invitation group to add a family, couple or individual. Every invited seat needs a name.'
                        }}
                    </p>
                </div>
                <table v-else class="w-full table-fixed text-left text-sm" :aria-busy="searching">
                    <caption class="sr-only">
                        Invitation groups and their named guests. Select a guest name to edit or remove that person.
                    </caption>
                    <thead class="border-b font-mono text-xs uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th scope="col" class="pb-3 pr-3 font-normal">Group / guests</th>
                            <th scope="col" class="hidden w-1/4 pb-3 pr-3 font-normal md:table-cell">Contact</th>
                            <th scope="col" class="w-16 pb-3 text-right font-normal">People</th>
                            <th scope="col" class="w-14 pb-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="group in groups.data" :key="group.id" class="align-top hover:bg-muted/30">
                            <td class="min-w-0 py-5 pr-3">
                                <p class="font-medium [overflow-wrap:anywhere]">{{ group.label }}</p>
                                <p class="mt-1 font-mono text-xs text-muted-foreground">
                                    Link:
                                    {{ group.link_status === 'active' ? 'Active' : group.link_status === 'revoked' ? 'Revoked' : 'Not generated' }}
                                </p>
                                <p
                                    v-if="group.contact_email || group.contact_phone"
                                    class="mt-1 text-xs text-muted-foreground [overflow-wrap:anywhere] md:hidden"
                                >
                                    {{ [group.contact_email, group.contact_phone].filter(Boolean).join(' · ') }}
                                </p>
                                <ul class="mt-2 flex flex-wrap gap-x-3 gap-y-1" aria-label="Named guests">
                                    <li v-for="guest in group.guests" :key="guest.id" class="min-w-0 max-w-full">
                                        <button
                                            type="button"
                                            class="min-h-9 rounded-sm text-left text-muted-foreground underline decoration-border underline-offset-4 [overflow-wrap:anywhere] hover:text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                                            :aria-label="'Edit guest ' + guest.name"
                                            @click="showDialog('rename', group, guest, $event.currentTarget as HTMLElement)"
                                        >
                                            {{ guest.name }}
                                        </button>
                                    </li>
                                </ul>
                            </td>
                            <td class="hidden py-5 pr-3 text-muted-foreground [overflow-wrap:anywhere] md:table-cell">
                                <p>{{ group.contact_email || 'No email' }}</p>
                                <p class="mt-1">{{ group.contact_phone || 'No phone' }}</p>
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
                                    <DropdownMenuContent align="end" @close-auto-focus="(open || linkOpen) && $event.preventDefault()">
                                        <DropdownMenuItem @select="openInvitationLink(group)">Invitation link</DropdownMenuItem>
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
                <nav v-if="groups.last_page > 1" aria-label="Guest list pages" class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Page {{ groups.current_page }} of {{ groups.last_page }}</p>
                    <div class="flex gap-2">
                        <Button v-if="groups.prev_page_url" as-child variant="outline"
                            ><Link :href="groups.prev_page_url" preserve-scroll>Previous</Link></Button
                        >
                        <Button v-else variant="outline" disabled>Previous</Button>
                        <Button v-if="groups.next_page_url" as-child variant="outline"
                            ><Link :href="groups.next_page_url" preserve-scroll>Next</Link></Button
                        >
                        <Button v-else variant="outline" disabled>Next</Button>
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
                            <div class="space-y-2">
                                <Label for="field-contact_email">Contact email (optional)</Label
                                ><Input
                                    id="field-contact_email"
                                    v-model="groupForm.contact_email"
                                    type="email"
                                    maxlength="254"
                                    :aria-invalid="!!errorFor('contact_email')"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="field-contact_phone">Contact phone (optional)</Label
                                ><Input
                                    id="field-contact_phone"
                                    v-model="groupForm.contact_phone"
                                    type="tel"
                                    maxlength="50"
                                    :aria-invalid="!!errorFor('contact_phone')"
                                />
                            </div>
                            <fieldset v-if="mode === 'create'" class="space-y-3">
                                <legend class="mb-2 text-sm font-medium">Named guests</legend>
                                <div v-for="(guest, index) in groupForm.guests" :key="index" class="flex items-end gap-2">
                                    <div class="min-w-0 flex-1 space-y-2">
                                        <Label :for="'field-guests.' + index + '.name'">Guest {{ index + 1 }} name</Label
                                        ><Input
                                            :id="'field-guests.' + index + '.name'"
                                            v-model="guest.name"
                                            maxlength="255"
                                            required
                                            :aria-invalid="!!errorFor('guests.' + index + '.name')"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="groupForm.guests.length === 1 || busy"
                                        :aria-label="'Remove unsaved guest ' + (index + 1)"
                                        @click="groupForm.guests.splice(index, 1)"
                                        ><X class="h-4 w-4" aria-hidden="true"
                                    /></Button>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="groupForm.guests.length >= maxGuestsPerGroup || busy"
                                    @click="groupForm.guests.push({ name: '' })"
                                    >Add another guest</Button
                                >
                                <p class="text-xs text-muted-foreground">Up to {{ maxGuestsPerGroup }} named guests per group. Names may repeat.</p>
                            </fieldset>
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
        <InvitationLinkDialog v-model:open="linkOpen" :account-id="account.id" :wedding-id="wedding.id" :group="linkGroup" @changed="refreshLinks" />
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
