<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
    label?: string;
}>();

const page = usePage<SharedData>();
const isActive = (href: string) => page.url.split('?')[0] === href.replace(/^https?:\/\/[^/]+/, '').split('?')[0];
</script>

<template>
    <SidebarGroup class="px-3 py-0">
        <SidebarGroupLabel class="brand-nav-label mb-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-[#876f5e]">{{
            label
        }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="item.isActive || isActive(item.href)"
                    :tooltip="item.title"
                    class="min-h-10 rounded-lg px-3 text-sm"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" aria-hidden="true" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
