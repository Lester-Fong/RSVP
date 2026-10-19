import { createApp } from 'vue';
import InvitationRsvpForm from './components/wedding/InvitationRsvpForm.vue';
import ModernClassicInvitation from './components/wedding/ModernClassicInvitation.vue';
import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from './types';

const data = document.getElementById('invitation-data');
if (data?.textContent) {
    const props = JSON.parse(data.textContent) as {
        wedding: WeddingPresentation;
        customization: InvitationCustomization;
        heroPhoto: InvitationHeroPhoto | null;
    };
    createApp(ModernClassicInvitation, props).mount('#public-invitation');
}

const rsvp = document.getElementById('rsvp-data');
if (rsvp?.textContent) {
    createApp(InvitationRsvpForm, JSON.parse(rsvp.textContent)).mount('#invitation-rsvp');
}
