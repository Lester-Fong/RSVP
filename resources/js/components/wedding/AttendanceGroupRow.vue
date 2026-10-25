<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AttendanceGuestList from '@/components/wedding/AttendanceGuestList.vue';
import type { AttendanceFilters, AttendanceGroup, AttendanceGuest, AttendancePage } from '@/types';
import { onBeforeUnmount, ref } from 'vue';
const props = defineProps<{ group: AttendanceGroup; accountId: number; weddingId: number; filters: AttendanceFilters }>();
const members = ref<AttendancePage<AttendanceGuest> | null>(null);
const busy = ref(false);
const error = ref('');
let controller: AbortController | null = null;
let requestedPage = 1;
async function load(page = 1): Promise<void> {
    if (busy.value) return;
    requestedPage = page;
    busy.value = true;
    error.value = '';
    controller = new AbortController();
    try {
        const query = new URLSearchParams({ search: props.filters.search, status: props.filters.status, page: String(page) });
        const response = await fetch(
            route('accounts.weddings.attendance.group', { account: props.accountId, wedding: props.weddingId, invitationGroup: props.group.id }) +
                '?' +
                query,
            {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            },
        );
        if (!response.ok) throw new Error('Attendance unavailable');
        members.value = await response.json();
    } catch {
        if (!controller?.signal.aborted) error.value = 'These guests could not be loaded. Please try again.';
    } finally {
        busy.value = false;
    }
}
function toggle(event: Event): void {
    if ((event.target as HTMLDetailsElement).open && !members.value && !busy.value) void load();
}
onBeforeUnmount(() => controller?.abort());
</script>

<template>
    <details class="border-b py-4" @toggle="toggle">
        <summary class="cursor-pointer rounded-sm py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-ring">
            <span class="ml-1 font-medium [overflow-wrap:anywhere]">{{ group.label }}</span>
            <span class="ml-2 text-sm text-muted-foreground">{{ group.invited }} invited</span>
            <span class="mt-2 block text-sm text-muted-foreground"
                >{{ group.attending }} attending · {{ group.declined }} declined · {{ group.pending }} pending</span
            >
            <span v-if="filters.search || filters.status !== 'all'" class="mt-1 block text-xs text-muted-foreground"
                >{{ group.matching }} matching guests. Group totals include everyone.</span
            >
        </summary>
        <div class="mt-4 min-w-0" :aria-busy="busy">
            <p v-if="busy" role="status" class="py-3 text-sm text-muted-foreground">Loading guests…</p>
            <div v-if="error" role="alert" class="flex flex-wrap items-center gap-3 text-sm">
                <p>{{ error }}</p>
                <Button variant="outline" :disabled="busy" @click="load(requestedPage)">Try again</Button>
            </div>
            <template v-else-if="members && !busy">
                <AttendanceGuestList v-if="members.data.length" :guests="members.data" :caption="'Responses for ' + group.label" />
                <p v-else class="py-3 text-sm text-muted-foreground">
                    No guests match the current filters. Refresh Attendance to update group totals.
                </p>
                <nav
                    v-if="members.last_page > 1"
                    :aria-label="'Guest pages for ' + group.label"
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-sm text-muted-foreground">{{ members.from }}–{{ members.to }} of {{ members.total }} matching guests</p>
                    <div class="flex gap-2">
                        <Button variant="outline" :disabled="members.current_page <= 1" @click="load(members.current_page - 1)">Previous</Button
                        ><Button variant="outline" :disabled="members.current_page >= members.last_page" @click="load(members.current_page + 1)"
                            >Next</Button
                        >
                    </div>
                </nav>
            </template>
        </div>
    </details>
</template>
