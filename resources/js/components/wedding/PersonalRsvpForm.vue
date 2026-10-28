<script setup lang="ts">
import type { PersonalFailureKind, PersonalRsvpState, PersonalRsvpSubmission, PersonalRsvpTransport, RsvpStatus } from '@/types/personal-rsvp';
import { computed, nextTick, onBeforeUnmount, ref, useId, useSlots, watch } from 'vue';

const props = defineProps<{ initial: PersonalRsvpState; transport: PersonalRsvpTransport }>();
const emit = defineEmits<{ saved: [state: PersonalRsvpState]; unavailable: [] }>();
const slots = useSlots();
const saved = ref(props.initial);
const choices = ref<Record<string, RsvpStatus>>({});
const busy = ref(false);
const needsReload = ref(false);
const unavailable = ref(false);
const feedback = ref('');
const confirmation = ref<{ state: PersonalRsvpState; changed: boolean } | null>(null);
const reloading = ref(false);
const error = ref('');
const notice = ref<HTMLElement | null>(null);
const id = useId();
function timestamp(value: string): string {
    return new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}
let controller: AbortController | null = null;
let request = 0;
watch(() => props.initial, reset, { immediate: true });
function reset(state: PersonalRsvpState) {
    controller?.abort();
    request++;
    busy.value = false;
    saved.value = state;
    choices.value = Object.fromEntries(state.guests.map((guest) => [guest.reference, guest.status]));
    needsReload.value = false;
    unavailable.value = false;
    feedback.value = '';
    confirmation.value = null;
    error.value = '';
}
const changes = computed<PersonalRsvpSubmission['responses']>(() =>
    saved.value.guests.flatMap((guest) => {
        const status = choices.value[guest.reference];
        return status && status !== 'pending' && status !== guest.status ? [{ guest: guest.reference, version: guest.version, status }] : [];
    }),
);
function failure(kind: PersonalFailureKind, retryAfter?: number) {
    const messages: Record<PersonalFailureKind, string> = {
        stale_response: 'A response or name changed in another window. Reload and review the saved choices before trying again.',
        stale_access: 'Who this link can answer for has changed. Reload the invitation to review your current access.',
        unavailable: 'This invitation is unavailable. Ask the couple for a current link.',
        session: 'Your session expired. Reload the invitation before saving again.',
        rate_limited: `Please wait ${Math.max(1, Math.min(3600, Math.ceil(retryAfter || 60)))} seconds before trying again.`,
        validation: 'Check your choices and reload the invitation before trying again. Your saved responses have not changed.',
        network: 'We could not confirm the save. Your choices are still here. Retry or reload to check the saved responses.',
    };
    error.value = messages[kind];
    needsReload.value = ['stale_response', 'stale_access', 'session', 'validation'].includes(kind);
    if (kind === 'unavailable') {
        unavailable.value = true;
        choices.value = {};
        saved.value = { greeting: { name: '' }, access_version: 0, guests: [] };
        emit('unavailable');
    }
}
function editChoice() {
    if (confirmation.value && slots.confirmation) {
        confirmation.value = null;
        feedback.value = '';
    }
}
async function act(reload = false) {
    if (busy.value || unavailable.value || (!reload && needsReload.value)) return;
    feedback.value = '';
    confirmation.value = null;
    error.value = '';
    if (!reload && changes.value.length === 0) {
        feedback.value = 'No changed responses to save. Unanswered people remain pending.';
        await nextTick();
        notice.value?.focus();
        return;
    }
    busy.value = true;
    reloading.value = reload;
    const current = ++request;
    controller = new AbortController();
    try {
        const result: Awaited<ReturnType<PersonalRsvpTransport['submit']>> = reload
            ? await props.transport.reload(controller.signal)
            : await props.transport.submit({ access_version: saved.value.access_version, responses: changes.value }, controller.signal);
        if (request !== current) return;
        if (!result.ok) failure(result.kind, result.retryAfter);
        else {
            saved.value = result.value;
            choices.value = Object.fromEntries(result.value.guests.map((guest) => [guest.reference, guest.status]));
            needsReload.value = false;
            feedback.value = reload
                ? 'Saved choices and current access reloaded.'
                : 'Your responses have been saved. Unanswered people remain pending.';
            if (!reload && typeof result.receipt?.changed === 'boolean') {
                confirmation.value = { state: result.value, changed: result.receipt.changed };
            }
            emit('saved', result.value);
        }
    } catch {
        if (request === current) failure('network');
    } finally {
        if (request === current) {
            busy.value = false;
            await nextTick();
            notice.value?.focus();
        }
    }
}
onBeforeUnmount(() => {
    request++;
    controller?.abort();
});
</script>

<template>
    <form :aria-labelledby="id + '-heading'" :aria-busy="busy" @submit.prevent="act()">
        <h2 :id="id + '-heading'">Your RSVP</h2>
        <div v-if="confirmation && !error && $slots.confirmation" ref="notice" tabindex="-1" role="status" aria-atomic="true">
            <slot name="confirmation" :state="confirmation.state" :changed="confirmation.changed" />
        </div>
        <template v-if="!unavailable">
            <p>Respond for yourself and the people specifically listed below. You can answer some now and return to update your choices.</p>
            <p class="rsvp-note">Anyone with this private link can submit these responses. Share it carefully.</p>
            <div class="rsvp-members">
                <div v-for="(guest, index) in saved.guests" :key="guest.reference" class="rsvp-member">
                    <div class="rsvp-name">
                        <label :for="id + '-choice-' + index">{{ guest.name }}</label>
                        <p v-if="guest.represented" class="rsvp-note">Responding on behalf of {{ guest.name }}</p>
                        <p v-else class="rsvp-note">Your response</p>
                        <p :id="id + '-status-' + index" class="rsvp-note">
                            Saved:
                            {{ guest.status === 'pending' ? 'Pending — not answered' : guest.status === 'attending' ? 'Attending' : 'Declining' }}
                        </p>
                        <p v-if="guest.respondedAt" class="rsvp-note">
                            Responded: <time :datetime="guest.respondedAt">{{ timestamp(guest.respondedAt) }}</time>
                        </p>
                    </div>
                    <select
                        :id="id + '-choice-' + index"
                        v-model="choices[guest.reference]"
                        :aria-describedby="id + '-status-' + index"
                        :disabled="busy || needsReload"
                        @change="editChoice"
                    >
                        <option value="pending" :disabled="guest.status !== 'pending'">Not answered yet</option>
                        <option value="attending">Attending</option>
                        <option value="declined">Declining</option>
                    </select>
                </div>
            </div>
        </template>
        <p v-if="busy && $slots.confirmation" role="status" class="rsvp-note">
            {{ reloading ? 'Reloading saved choices…' : 'Saving your responses…' }}
        </p>
        <p
            v-if="(feedback || error) && (!confirmation || error || !$slots.confirmation)"
            ref="notice"
            tabindex="-1"
            :role="error ? 'alert' : 'status'"
        >
            {{ error || feedback }}
        </p>
        <div v-if="!unavailable" class="rsvp-actions">
            <button type="submit" :disabled="busy || needsReload">{{ busy ? (reloading ? 'Reloading…' : 'Saving…') : 'Save responses' }}</button>
            <button type="button" class="rsvp-secondary" :disabled="busy" @click="act(true)">Reload saved choices</button>
        </div>
    </form>
</template>
