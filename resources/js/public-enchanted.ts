import { createApp } from 'vue';
import EnchantedEditorialInvitation from './components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue';
import EnchantedPersonalInvitation from './components/wedding/enchanted-editorial/EnchantedPersonalInvitation.vue';
import { personalRsvpTransport } from './lib/personal-rsvp-http';
import type { EnchantedEditorialPresentation } from './types/enchanted-editorial';
import type { PersonalRsvpState } from './types/personal-rsvp';

const data = document.getElementById('enchanted-data');
try {
    if (data?.textContent) {
        const presentation = JSON.parse(data.textContent) as EnchantedEditorialPresentation;
        if (presentation.templateKey === 'enchanted-editorial' && presentation.templateVersion === 1) {
            const personal = document.getElementById('personal-rsvp-data');
            if (personal?.textContent) {
                const state = JSON.parse(personal.textContent) as { initial: PersonalRsvpState; csrfUrl: string };
                createApp(EnchantedPersonalInvitation, {
                    presentation,
                    initial: state.initial,
                    transport: personalRsvpTransport(state.csrfUrl),
                }).mount('#public-enchanted');
            } else {
                createApp(EnchantedEditorialInvitation, { presentation, publicWebsite: true }).mount('#public-enchanted');
            }
        }
    }
} finally {
    delete document.documentElement.dataset.eeBoot;
}
