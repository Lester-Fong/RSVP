<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Heart, LayoutGrid, MailOpen, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Home',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const page = usePage<SharedData & { account?: { id: number }; wedding?: { id: number } }>();
const weddingNavItems = computed<NavItem[]>(() => {
    const account = page.props.account?.id;
    const wedding = page.props.wedding?.id;
    if (!account || !wedding) return [];
    const parameters = { account, wedding };
    return [
        {
            title: 'Overview',
            href: route('accounts.weddings.show', parameters),
            icon: Heart,
            isActive: page.url.split('?')[0] === route('accounts.weddings.edit', parameters, false),
        },
        { title: 'Design invitation', href: route('accounts.weddings.invitation.edit', parameters), icon: MailOpen },
        { title: 'Guests & RSVPs', href: route('accounts.weddings.guests.index', parameters), icon: UsersRound },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" aria-label="Imbitado navigation">
        <SidebarHeader class="border-b border-[#ddcfc0] px-3 py-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-5 pt-5">
            <NavMain v-if="!weddingNavItems.length" :items="mainNavItems" label="Your space" />
            <NavMain v-else :items="weddingNavItems" label="Your wedding" />
            <div
                class="brand-sidebar-note mx-4 mt-auto hidden rounded-xl border border-[#dccab8] bg-[#fbf5ed] p-4 text-sm leading-6 text-[#66574d] group-data-[collapsible=icon]:hidden md:block"
            >
                <CalendarDays class="mb-3 size-5 text-[#88624b]" aria-hidden="true" />
                A thoughtful space to plan, publish, and welcome your guests.
            </div>
        </SidebarContent>

        <SidebarFooter class="px-3 py-4">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
