import { createApp } from 'vue';
import ModernClassicInvitation from './components/wedding/ModernClassicInvitation.vue';
import PersonalInvitation from './components/wedding/PersonalInvitation.vue';
import { personalRsvpTransport } from './lib/personal-rsvp-http';
import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from './types';
import type { PersonalRsvpState } from './types/personal-rsvp';

const data = document.getElementById('invitation-data');
const personal = document.getElementById('personal-rsvp-data');
if (data?.textContent) {
    const props = JSON.parse(data.textContent) as {
        wedding: WeddingPresentation;
        customization: InvitationCustomization;
        heroPhoto: InvitationHeroPhoto | null;
    };
    if (personal?.textContent) {
        const state = JSON.parse(personal.textContent) as { initial: PersonalRsvpState; csrfUrl: string };
        createApp(PersonalInvitation, { published: props, initial: state.initial, transport: personalRsvpTransport(state.csrfUrl) }).mount(
            '#personal-invitation',
        );
    } else {
        createApp(ModernClassicInvitation, props).mount('#public-invitation');
    }
}
