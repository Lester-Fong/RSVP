<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
defineProps<{ accountId: number; weddingId: number; active: 'overview' | 'invitation' | 'guests' | 'attendance' }>();
const items = [
    { key: 'overview', label: 'Overview', route: 'accounts.weddings.show' },
    { key: 'invitation', label: 'Invitation', route: 'accounts.weddings.invitation.edit' },
    { key: 'guests', label: 'Guests', route: 'accounts.weddings.guests.index' },
    { key: 'attendance', label: 'Attendance', route: 'accounts.weddings.attendance.index' },
] as const;
</script>

<template>
    <nav aria-label="Wedding workspace" class="flex min-w-0 flex-wrap gap-x-5 border-b sm:gap-x-7">
        <!-- A document boundary lets the editor's native unsaved warning protect browser Back as well as links. -->
        <component
            :is="item.key === 'invitation' ? 'a' : Link"
            v-for="item in items"
            :key="item.key"
            :href="route(item.route, { account: accountId, wedding: weddingId })"
            :aria-current="active === item.key ? 'page' : undefined"
            class="inline-flex min-h-11 items-center border-b-2 px-0.5 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            :class="
                active === item.key
                    ? 'border-foreground font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            {{ item.label }}
        </component>
    </nav>
</template>
