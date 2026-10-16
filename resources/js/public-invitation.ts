import { createApp } from 'vue';
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
