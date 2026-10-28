<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AttendanceGroupRow from '@/components/wedding/AttendanceGroupRow.vue';
import AttendanceGuestList from '@/components/wedding/AttendanceGuestList.vue';
import { usePortalFilters } from '@/composables/usePortalFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AttendanceFilters, AttendanceGroup, AttendanceGuest, AttendancePage, AttendanceSummary, BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    account: { id: number; name: string };
    wedding: { id: number; partner_one_name: string; partner_two_name: string };
    summary: AttendanceSummary;
    filters: AttendanceFilters;
    matchedGuests: number;
    refreshedAt: string;
    guests: AttendancePage<AttendanceGuest> | null;
    groups: AttendancePage<AttendanceGroup> | null;
    withdrawalEnabled: boolean;
}>();
const parameters = { account: props.account.id, wedding: props.wedding.id };
const indexUrl = route('accounts.weddings.attendance.index', parameters);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Wedding', href: route('accounts.weddings.show', parameters) },
    { title: 'Attendance', href: indexUrl },
];
const generation = ref(0);
const { search, status, busy, filtering, error, visit, clear } = usePortalFilters({
    url: indexUrl,
    filters: () => props.filters,
    onSuccess: () => {
        generation.value++;
    },
});
const rows = computed(() => props.guests ?? props.groups);
const cards: { key: keyof AttendanceSummary; label: string }[] = [
    { key: 'invited', label: props.withdrawalEnabled ? 'Active Invited' : 'Total Invited' },
    { key: 'attending', label: 'Attending' },
    { key: 'declined', label: 'Declined' },
    { key: 'pending', label: 'Pending' },
];
const updated = computed(() => new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(props.refreshedAt)));
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Attendance" />
        <div class="mx-auto flex w-full min-w-0 max-w-6xl flex-col gap-8 p-4 sm:p-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-mono text-xs uppercase tracking-widest text-muted-foreground [overflow-wrap:anywhere]">
                        {{ wedding.partner_one_name }} &amp; {{ wedding.partner_two_name }}
                    </p>
                    <h1 class="mt-2 text-2xl font-medium tracking-tight">Attendance</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review saved responses. Manage names and invitations in Guests.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" :disabled="filtering" @click="visit(filters.view, rows?.current_page ?? 1)">{{
                        busy ? 'Refreshing…' : 'Refresh'
                    }}</Button>
                </div>
            </header>
            <section aria-labelledby="attendance-totals-heading">
                <h2 id="attendance-totals-heading" class="sr-only">Wedding-wide attendance totals</h2>
                <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div v-for="card in cards" :key="card.key" class="min-w-0 rounded-lg border px-4 py-5">
                        <dt class="font-mono text-xs uppercase tracking-wide text-muted-foreground">{{ card.label }}</dt>
                        <dd :data-testid="'attendance-' + card.key" class="mt-3 text-3xl font-medium tabular-nums">{{ summary[card.key] }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-muted-foreground">
                    {{
                        withdrawalEnabled
                            ? 'Wedding-wide totals include active invitations only; withdrawn responses remain retained for history.'
                            : 'Wedding-wide totals include every current named guest, regardless of search or filters.'
                    }}
                    Refresh to include new responses.
                </p>
            </section>
            <section aria-labelledby="attendance-results-heading" :aria-busy="filtering" class="min-w-0">
                <h2 id="attendance-results-heading" class="text-lg font-medium">Responses</h2>
                <form class="mt-4 flex flex-wrap items-end gap-3 border-b pb-5" @submit.prevent>
                    <div class="min-w-0 flex-1 basis-64 space-y-2">
                        <Label for="attendance-search">Search guest or invitation group</Label
                        ><Input id="attendance-search" v-model="search" type="search" maxlength="255" />
                    </div>
                    <div class="space-y-2">
                        <Label for="attendance-status">RSVP status</Label
                        ><select
                            id="attendance-status"
                            v-model="status"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring"
                        >
                            <option value="all">All</option>
                            <option value="attending">Attending</option>
                            <option value="declined">Declined</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <Button
                        v-if="search || filters.search || status !== 'all' || filters.status !== 'all'"
                        type="button"
                        variant="ghost"
                        @click="clear"
                        >Clear filters</Button
                    >
                </form>
                <div class="my-5 flex flex-wrap items-center justify-between gap-3">
                    <div role="group" aria-label="Attendance view" class="flex gap-2">
                        <Button
                            :variant="filters.view === 'guests' ? 'default' : 'outline'"
                            :aria-pressed="filters.view === 'guests'"
                            :disabled="filtering"
                            @click="visit('guests')"
                            >Individual guests</Button
                        ><Button
                            :variant="filters.view === 'groups' ? 'default' : 'outline'"
                            :aria-pressed="filters.view === 'groups'"
                            :disabled="filtering"
                            @click="visit('groups')"
                            >Invitation groups</Button
                        >
                    </div>
                    <p role="status" class="text-sm text-muted-foreground">
                        {{ matchedGuests }} matching guests<span v-if="groups"> · {{ groups.total }} matching groups</span
                        ><span v-if="filtering"> · Loading…</span>
                    </p>
                </div>
                <p v-if="error" role="alert" class="mb-4 text-sm">{{ error }}</p>
                <p class="mb-5 text-xs text-muted-foreground">
                    Saved responses as of <time :datetime="refreshedAt">{{ updated }}</time
                    >. Times use your device's time zone. This dashboard is read-only.
                </p>
                <div v-if="summary.invited === 0" class="py-8">
                    <h3 class="font-medium">No invited guests yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Add named guests in Guest Management to start tracking attendance.</p>
                </div>
                <div v-else-if="!rows?.data.length" class="py-8">
                    <h3 class="font-medium">No guests match these filters</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Try another name, group label or RSVP status.</p>
                </div>
                <AttendanceGuestList v-else-if="guests" :guests="guests.data" caption="Individual guest responses" show-group />
                <div v-else-if="groups" :key="generation">
                    <AttendanceGroupRow
                        v-for="group in groups.data"
                        :key="group.id + filters.search + filters.status"
                        :group="group"
                        :account-id="account.id"
                        :wedding-id="wedding.id"
                        :filters="filters"
                    />
                </div>
                <nav v-if="rows && rows.last_page > 1" aria-label="Attendance pages" class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">
                        {{ rows.from }}–{{ rows.to }} of {{ rows.total }} {{ guests ? 'matching guests' : 'matching groups' }} · Page
                        {{ rows.current_page }} of {{ rows.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="filtering || rows.current_page <= 1" @click="visit(filters.view, rows.current_page - 1)"
                            >Previous</Button
                        ><Button
                            variant="outline"
                            :disabled="filtering || rows.current_page >= rows.last_page"
                            @click="visit(filters.view, rows.current_page + 1)"
                            >Next</Button
                        >
                    </div>
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
