<script setup lang="ts">
import type { AttendanceGuest } from '@/types';
defineProps<{ guests: AttendanceGuest[]; caption: string; showGroup?: boolean }>();
const labels = { attending: 'Attending', declined: 'Declined', pending: 'Pending' };
function timestamp(value: string): string {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
</script>

<template>
    <table class="w-full table-fixed text-left text-sm">
        <caption class="sr-only">
            {{
                caption
            }}
        </caption>
        <thead class="border-b font-mono text-xs uppercase tracking-wider text-muted-foreground">
            <tr>
                <th scope="col" class="pb-3 pr-3 font-normal">Guest</th>
                <th v-if="showGroup" scope="col" class="hidden w-1/4 pb-3 pr-3 font-normal sm:table-cell">Invitation group</th>
                <th scope="col" class="w-24 pb-3 pr-2 font-normal">RSVP</th>
                <th scope="col" class="hidden w-48 pb-3 font-normal lg:table-cell">Responded</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            <tr v-for="guest in guests" :key="guest.id" class="align-top">
                <td class="min-w-0 py-4 pr-3">
                    <p class="font-medium [overflow-wrap:anywhere]">{{ guest.name }}</p>
                    <p v-if="showGroup" class="mt-1 text-muted-foreground [overflow-wrap:anywhere] sm:hidden">{{ guest.group }}</p>
                    <p class="mt-1 text-xs text-muted-foreground lg:hidden">
                        <time v-if="guest.respondedAt" :datetime="guest.respondedAt">{{ timestamp(guest.respondedAt) }}</time>
                        <span v-else>No response yet</span>
                    </p>
                </td>
                <td v-if="showGroup" class="hidden py-4 pr-3 text-muted-foreground [overflow-wrap:anywhere] sm:table-cell">{{ guest.group }}</td>
                <td class="py-4 pr-2">
                    <span class="inline-block rounded-md border px-2 py-1 text-xs">{{ labels[guest.status] }}</span>
                </td>
                <td class="hidden py-4 text-muted-foreground lg:table-cell">
                    <time v-if="guest.respondedAt" :datetime="guest.respondedAt">{{ timestamp(guest.respondedAt) }}</time>
                    <span v-else>No response yet</span>
                </td>
            </tr>
        </tbody>
    </table>
</template>
