<script setup lang="ts">
import ModernClassicInvitation from '@/components/wedding/ModernClassicInvitation.vue';
import PersonalRsvpForm from '@/components/wedding/PersonalRsvpForm.vue';
import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from '@/types';
import type { PersonalRsvpState, PersonalRsvpTransport } from '@/types/personal-rsvp';
import { ref, watch } from 'vue';

const props = defineProps<{
    published: { wedding: WeddingPresentation; customization: InvitationCustomization; heroPhoto: InvitationHeroPhoto | null };
    initial: PersonalRsvpState;
    transport: PersonalRsvpTransport;
}>();
const greeting = ref(props.initial.greeting.name);
const unavailable = ref(false);
watch(
    () => props.initial,
    (state) => {
        greeting.value = state.greeting.name;
        unavailable.value = false;
    },
);
</script>

<template>
    <div>
        <section v-if="!unavailable" class="invitation-greeting" aria-label="Your personal invitation">
            <h2>You're invited, {{ greeting }}!</h2>
        </section>
        <ModernClassicInvitation v-if="!unavailable" v-bind="published" />
        <section class="invitation-rsvp">
            <PersonalRsvpForm
                :initial="initial"
                :transport="transport"
                @saved="greeting = $event.greeting.name"
                @unavailable="
                    unavailable = true;
                    greeting = '';
                "
            />
        </section>
    </div>
</template>
