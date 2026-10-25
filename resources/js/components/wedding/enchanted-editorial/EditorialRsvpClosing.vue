<script setup lang="ts">
import type { EditorialRsvpSection } from '@/types/enchanted-editorial';
import EditorialOrnament from './EditorialOrnament.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';
defineProps<{ section: EditorialRsvpSection; publicWebsite?: boolean }>();
</script>

<template>
    <section id="rsvp" class="ee-rsvp" aria-labelledby="ee-rsvp-heading" tabindex="-1">
        <EditorialOrnament class="ee-landing-marker ee-landing-section" data-landing-section="rsvp" data-landing-id="section:rsvp" />
        <div class="ee-container ee-rsvp-inner">
            <EnchantedBotanical class="ee-rsvp-branch" />
            <p class="ee-eyebrow">{{ section.eyebrow }}</p>
            <h2 id="ee-rsvp-heading" class="ee-section-heading">{{ section.heading }}</h2>
            <p
                v-if="!$slots['personal-rsvp'] || section.message !== 'Personal RSVP is not connected in this editor preview.'"
                class="ee-rsvp-message"
            >
                {{ section.message }}
            </p>
            <slot name="personal-rsvp"
                ><p v-if="publicWebsite" class="ee-rsvp-information">Please follow the RSVP instructions provided by the couple.</p>
                <p v-else class="ee-rsvp-information">
                    Invitation information · visual preview<br />Please use your personal invitation link to respond. This preview does not collect
                    RSVP responses.
                </p></slot
            >
            <p v-if="section.closing" class="ee-rsvp-closing">{{ section.closing }}</p>
        </div>
    </section>
</template>
