<script setup lang="ts">
// Synthetic component host only. Not imported by an application entry point or Laravel route.
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import PersonalInvitation from '@/components/wedding/PersonalInvitation.vue';
import RsvpAccessDialog from '@/components/wedding/RsvpAccessDialog.vue';
import type {
    OwnerRsvpGroup,
    OwnerRsvpMember,
    OwnerRsvpTransport,
    PersonalFailureKind,
    PersonalResult,
    PersonalRsvpState,
    PersonalRsvpTransport,
} from '@/types/personal-rsvp';
import { ref } from 'vue';

const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));
const person = (id: number, name: string, link_status: OwnerRsvpMember['link_status'] = 'active'): OwnerRsvpMember => ({
    groupId: 1,
    guest: { id, name },
    access_version: 3,
    link_version: link_status === 'not_generated' ? 0 : 1,
    link_status,
    mode: 'independent',
    representative: null,
    dependents: [],
});
const group = ref<OwnerRsvpGroup>({
    id: 1,
    label: 'Santos Family',
    members: [
        person(1, 'Juan Santos'),
        person(2, 'Maria Santos', 'not_generated'),
        person(3, 'Andrea Santos'),
        person(4, 'Miguel Santos', 'revoked'),
        person(5, 'Alex Santos'),
        person(6, 'Alex Santos'),
        person(7, 'VeryLongGuestName'.repeat(16)),
        { ...person(99, 'Foreign wedding person'), groupId: 99 },
    ],
});
group.value.members[0].dependents = [{ id: 4, name: 'Miguel Santos' }];
group.value.members[3].mode = 'represented';
group.value.members[3].representative = { id: 1, name: 'Juan Santos' };
const open = ref(false);
const trigger = ref<InstanceType<typeof Button> | null>(null);
const guestMode = new URLSearchParams(location.search).get('view') === 'guest';
const initial = ref<PersonalRsvpState>({
    greeting: { name: 'Juan Santos' },
    access_version: 8,
    guests: [
        { reference: 'a'.repeat(64), name: 'Juan Santos', version: 2, represented: false, status: 'pending', respondedAt: null },
        { reference: 'b'.repeat(64), name: 'Miguel Santos', version: 6, represented: true, status: 'pending', respondedAt: null },
    ],
});
let persisted = clone(initial.value);
const calls: { action: string; input: unknown }[] = [];
let failNext: PersonalFailureKind | null = null;
let delay = false;
let release: (() => void) | null = null;
async function result<T>(action: string, input: unknown, work: () => T): Promise<PersonalResult<T>> {
    calls.push({ action, input: clone(input) });
    if (delay)
        await new Promise<void>((resolve) => {
            release = resolve;
        });
    if (failNext) {
        const kind = failNext;
        failNext = null;
        return { ok: false, kind, retryAfter: 17 };
    }
    return { ok: true, value: clone(work()) };
}
const ownerTransport: OwnerRsvpTransport = {
    credential: async (command) =>
        result(command.action, command, () => {
            const member = group.value.members.find((person) => person.guest.id === command.guestId)!;
            if (command.action !== 'copy') {
                member.link_status = command.action === 'revoke' ? 'revoked' : 'active';
                member.link_version++;
                member.access_version++;
            }
            if (command.action === 'revoke') {
                for (const dependent of member.dependents) {
                    const target = group.value.members.find((person) => person.guest.id === dependent.id)!;
                    target.representative = null;
                    target.mode = 'independent';
                    target.access_version++;
                }
                member.dependents = [];
            }
            return {
                group: group.value,
                url: command.action === 'revoke' ? null : location.origin + '/synthetic-only/no-credential-' + member.guest.id,
            };
        }),
    delegation: async (command) =>
        result('delegation', command, () => {
            const member = group.value.members.find((person) => person.guest.id === command.dependentId)!;
            const representative = group.value.members.find((person) => person.guest.id === command.representativeId);
            for (const person of group.value.members) person.dependents = person.dependents.filter((dependent) => dependent.id !== member.guest.id);
            member.mode = representative ? 'represented' : 'independent';
            member.representative = representative?.guest ?? null;
            if (representative) {
                representative.dependents.push(member.guest);
                if (member.link_status === 'active') {
                    member.link_status = 'revoked';
                    member.link_version++;
                }
            }
            for (const person of group.value.members) if (Object.hasOwn(command.expectedAccessVersions, person.guest.id)) person.access_version++;
            return { group: group.value };
        }),
    refresh: async () => result('refresh', {}, () => ({ group: group.value })),
};
const guestTransport: PersonalRsvpTransport = {
    submit: async (command) =>
        result('submit', command, () => {
            for (const entry of command.responses) {
                const guest = persisted.guests.find((person) => person.reference === entry.guest)!;
                guest.status = entry.status;
                guest.version++;
                guest.respondedAt = '2026-10-19T08:00:00Z';
            }
            return persisted;
        }),
    reload: async () => result('reload', {}, () => persisted),
};
const published = {
    wedding: {
        partner_one_name: 'Alex',
        partner_two_name: 'Jamie',
        wedding_date: '2027-05-12',
        wedding_time: '16:30',
        ceremony_venue: 'Garden Chapel',
        reception_venue: 'Reception Hall',
    },
    customization: {
        introduction_message: 'Together with our families',
        closing_message: 'See you soon',
        sections: { ceremony: true, reception: true, closing: true },
    },
    heroPhoto: {
        url:
            'data:image/svg+xml,' +
            encodeURIComponent(
                '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="300"><rect width="600" height="300" fill="#d8cdbb"/></svg>',
            ),
    },
};
Object.assign(window, {
    __personalRsvpFixture: {
        calls,
        fail: (kind: PersonalFailureKind) => {
            failNext = kind;
        },
        delay: () => {
            delay = true;
        },
        release: () => {
            delay = false;
            release?.();
            release = null;
        },
        close: () => {
            open.value = false;
        },
        selfOnly: () => {
            persisted = { ...clone(initial.value), guests: [clone(initial.value.guests[0])] };
            initial.value = clone(persisted);
        },
        longName: () => {
            initial.value = {
                ...clone(initial.value),
                greeting: { name: '<script>private<' + '/script>' },
                guests: [{ ...clone(initial.value.guests[0]), name: 'UnbrokenGuestName'.repeat(20) }],
            };
            persisted = clone(initial.value);
        },
    },
});
</script>

<template>
    <PersonalInvitation v-if="guestMode" :published="published" :initial="initial" :transport="guestTransport" />
    <main v-else class="min-h-screen bg-background p-4 font-sans text-foreground sm:p-8">
        <div class="mx-auto max-w-5xl space-y-6">
            <p class="font-mono text-xs uppercase tracking-wider text-muted-foreground">Synthetic component fixture · not connected to Laravel</p>
            <h1 class="text-2xl font-semibold">Guest Management</h1>
            <div class="flex min-w-0 items-center justify-between gap-4 border-y py-4">
                <p class="min-w-0 font-medium">Santos Family</p>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child
                        ><Button ref="trigger" variant="outline" aria-label="Actions for Santos Family">More</Button></DropdownMenuTrigger
                    >
                    <DropdownMenuContent align="end" @close-auto-focus="open && $event.preventDefault()">
                        <DropdownMenuItem @select="open = true">RSVP Access</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
        <RsvpAccessDialog v-model:open="open" :group="group" :transport="ownerTransport" :return-focus="trigger?.$el" />
    </main>
</template>
