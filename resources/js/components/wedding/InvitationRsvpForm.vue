<script setup lang="ts">
import type { InvitationRsvpState } from '@/types';
import { computed, ref } from 'vue';

const props = defineProps<{ initial: InvitationRsvpState; csrfUrl: string }>();
const saved = ref(props.initial);
const choices = ref<Record<string, string>>(Object.fromEntries(saved.value.guests.map((guest) => [guest.reference, guest.status])));
const busy = ref(false);
const feedback = ref('');
const error = ref('');
const needsReload = ref(false);
const changes = computed(() =>
    saved.value.guests
        .filter((guest) => choices.value[guest.reference] !== 'pending' && choices.value[guest.reference] !== guest.status)
        .map((guest) => ({ guest: guest.reference, status: choices.value[guest.reference] })),
);

function reload(): void {
    window.location.reload();
}

async function submit(): Promise<void> {
    if (busy.value || needsReload.value) return;
    feedback.value = '';
    error.value = '';
    if (changes.value.length === 0) {
        feedback.value = 'No changed responses to save. Unanswered people remain pending.';
        return;
    }
    busy.value = true;
    try {
        const setup = await fetch(props.csrfUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        const csrf = await setup.json();
        if (!setup.ok) {
            error.value = csrf.message || 'Your session could not be prepared. Please try again.';
            return;
        }
        const response = await fetch(window.location.pathname.replace(/\/$/, '') + '/rsvp', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf.csrfToken },
            body: JSON.stringify({ version: saved.value.version, responses: changes.value }),
        });
        const result = await response.json();
        if (!response.ok) {
            error.value = result.message || 'Your responses could not be saved. Please try again.';
            needsReload.value = response.status === 422 || response.status === 404;
            return;
        }
        saved.value = result.rsvp;
        choices.value = Object.fromEntries(saved.value.guests.map((guest) => [guest.reference, guest.status]));
        feedback.value = 'Your responses have been saved. Unanswered people remain pending.';
    } catch {
        error.value = 'We could not confirm your save. Your choices are still here. Try again or reload to check the saved responses.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <form @submit.prevent="submit" :aria-busy="busy" aria-labelledby="rsvp-heading">
        <h2 id="rsvp-heading">Your RSVP</h2>
        <p>Let us know who can celebrate with us. You may respond for some people now and return to this link to update your choices.</p>
        <p class="rsvp-note">Anyone with this invitation link can view and update these responses. Please share it carefully.</p>
        <div class="rsvp-members">
            <div v-for="(guest, index) in saved.guests" :key="guest.reference" class="rsvp-member">
                <div class="rsvp-name">
                    <label :for="'rsvp-choice-' + index">{{ guest.name }}</label>
                    <p :id="'rsvp-status-' + index" class="rsvp-note">
                        Saved: {{ guest.status === 'pending' ? 'Pending — not answered' : guest.status === 'attending' ? 'Attending' : 'Declining' }}
                    </p>
                </div>
                <select
                    :id="'rsvp-choice-' + index"
                    v-model="choices[guest.reference]"
                    :aria-describedby="'rsvp-status-' + index"
                    :disabled="busy || needsReload"
                >
                    <option value="pending" :disabled="guest.status !== 'pending'">Not answered yet</option>
                    <option value="attending">Attending</option>
                    <option value="declined">Declining</option>
                </select>
            </div>
        </div>
        <p v-if="feedback" role="status">{{ feedback }}</p>
        <p v-if="error" role="alert">{{ error }}</p>
        <div class="rsvp-actions">
            <button type="submit" :disabled="busy || needsReload">{{ busy ? 'Saving responses…' : 'Save responses' }}</button>
            <button v-if="needsReload" type="button" class="rsvp-secondary" @click="reload">Reload invitation</button>
        </div>
    </form>
</template>
