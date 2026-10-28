<script setup lang="ts">
import '@/../css/enchanted-personal.css';
import PersonalRsvpForm from '@/components/wedding/PersonalRsvpForm.vue';
import type { EnchantedEditorialPresentation } from '@/types/enchanted-editorial';
import type { PersonalRsvpState, PersonalRsvpTransport } from '@/types/personal-rsvp';
import { ref, watch } from 'vue';
import EditorialRsvpConfirmation from './EditorialRsvpConfirmation.vue';
import EnchantedEditorialInvitation from './EnchantedEditorialInvitation.vue';

const props = defineProps<{ presentation: EnchantedEditorialPresentation; initial: PersonalRsvpState; transport: PersonalRsvpTransport }>();
const greeting = ref(props.initial.greeting.name),
    unavailable = ref(false);
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
        <EnchantedEditorialInvitation v-if="!unavailable" :presentation="presentation" public-website>
            <template #personal-greeting
                ><p class="ee-personal-greeting" aria-label="Your personal invitation">You're invited, {{ greeting }}!</p></template
            >
            <template #personal-rsvp>
                <div class="ee-personal-rsvp">
                    <PersonalRsvpForm
                        :initial="initial"
                        :transport="transport"
                        @saved="greeting = $event.greeting.name"
                        @unavailable="
                            unavailable = true;
                            greeting = '';
                        "
                    >
                        <template #confirmation="{ state, changed }"><EditorialRsvpConfirmation :state="state" :changed="changed" /></template>
                    </PersonalRsvpForm>
                </div>
            </template>
        </EnchantedEditorialInvitation>
        <div v-else class="ee-personal-unavailable"><p role="alert">This invitation is unavailable. Ask the couple for a current link.</p></div>
    </div>
</template>
