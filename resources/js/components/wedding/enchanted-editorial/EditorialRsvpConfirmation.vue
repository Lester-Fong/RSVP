<script setup lang="ts">
import type { PersonalRsvpState } from '@/types/personal-rsvp';
import { computed } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';

const props = defineProps<{ state: PersonalRsvpState; changed: boolean }>();
const counts = computed(() => ({
    attending: props.state.guests.filter((guest) => guest.status === 'attending').length,
    declined: props.state.guests.filter((guest) => guest.status === 'declined').length,
    pending: props.state.guests.filter((guest) => guest.status === 'pending').length,
}));
const kind = computed(() =>
    !props.changed
        ? 'unchanged'
        : props.state.guests.length && counts.value.attending === props.state.guests.length
          ? 'attending'
          : props.state.guests.length && counts.value.declined === props.state.guests.length
            ? 'declined'
            : 'mixed',
);
const heading = computed(
    () =>
        ({
            attending: "We can't wait to celebrate with you",
            declined: 'Thank you for letting us know',
            mixed: 'Thank you for sharing your plans',
            unchanged: 'Your saved choices are up to date',
        })[kind.value],
);
</script>

<template>
    <div class="ee-rsvp-confirmation" :data-rsvp-confirmation="kind" :data-rsvp-celebrating="kind === 'attending'">
        <div class="ee-rsvp-confirmation-botanical" aria-hidden="true"><EditorialOrnament /></div>
        <p class="ee-eyebrow">{{ changed ? 'With our thanks' : 'Your saved RSVP' }}</p>
        <h3>{{ heading }}</h3>
        <p v-if="kind === 'unchanged'">These responses were already saved. No changes were needed.</p>
        <p v-else>
            Your responses have been saved.
            {{
                kind === 'attending'
                    ? 'Thank you for being part of our day.'
                    : kind === 'declined'
                      ? "You'll be with us in spirit."
                      : 'You can return to update the people listed here.'
            }}
        </p>
    </div>
</template>
