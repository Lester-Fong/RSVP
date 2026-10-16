<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import type { InvitationCustomization, InvitationHeroPhoto, WeddingPresentation } from '@/types';

const props = defineProps<{
    wedding: WeddingPresentation;
    customization: InvitationCustomization;
    heroPhoto?: InvitationHeroPhoto | null;
}>();

const photoFailed = ref(false);
watch(
    () => props.heroPhoto?.url,
    () => {
        photoFailed.value = false;
    },
);

const formattedDate = computed(() => {
    if (!props.wedding.wedding_date) return null;

    const [year, month, day] = props.wedding.wedding_date.split('-').map(Number);

    return new Intl.DateTimeFormat('en-PH', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, day));
});

const formattedTime = computed(() => {
    if (!props.wedding.wedding_time) return null;

    const [hour, minute] = props.wedding.wedding_time.split(':').map(Number);

    return new Intl.DateTimeFormat('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(2000, 0, 1, hour, minute));
});

const hasVenue = computed(() =>
    Boolean(
        (props.customization.sections.ceremony && props.wedding.ceremony_venue) ||
            (props.customization.sections.reception && props.wedding.reception_venue),
    ),
);
</script>

<template>
    <div class="invitation-page">
        <article class="invitation-card" aria-labelledby="couple-names">
            <div class="invitation-frame" aria-hidden="true"></div>

            <header class="invitation-heading">
                <p class="invitation-eyebrow">The wedding of</p>
                <h1 id="couple-names" class="invitation-names">
                    <span>{{ wedding.partner_one_name }}</span>
                    <span class="invitation-ampersand" aria-hidden="true">&amp;</span>
                    <span>{{ wedding.partner_two_name }}</span>
                </h1>
                <div class="invitation-ornament" aria-hidden="true"><span></span>✦<span></span></div>
                <p class="invitation-intro">{{ customization.introduction_message }}</p>
            </header>

            <img
                v-if="heroPhoto && !photoFailed"
                :key="heroPhoto.url"
                :src="heroPhoto.url"
                :alt="`Wedding photo of ${wedding.partner_one_name} and ${wedding.partner_two_name}`"
                class="invitation-hero-photo"
                decoding="async"
                @error="photoFailed = true"
            />

            <section class="invitation-details" aria-labelledby="celebration-heading">
                <h2 id="celebration-heading" class="invitation-section-title">The celebration</h2>
                <div v-if="formattedDate || formattedTime" class="invitation-facts">
                    <p v-if="formattedDate">{{ formattedDate }}</p>
                    <p v-if="formattedTime">{{ formattedTime }}</p>
                </div>
                <p v-else class="invitation-note">Date and time details will be added soon.</p>

                <div v-if="hasVenue" class="invitation-venues">
                    <div v-if="customization.sections.ceremony && wedding.ceremony_venue" class="invitation-venue">
                        <h3>Ceremony</h3>
                        <p>{{ wedding.ceremony_venue }}</p>
                    </div>
                    <div v-if="customization.sections.reception && wedding.reception_venue" class="invitation-venue">
                        <h3>Reception</h3>
                        <p>{{ wedding.reception_venue }}</p>
                    </div>
                </div>
                <p v-else-if="customization.sections.ceremony || customization.sections.reception" class="invitation-note">
                    Venue details will be added soon.
                </p>
            </section>

            <footer v-if="customization.sections.closing" class="invitation-footer">
                <div class="invitation-ornament" aria-hidden="true"><span></span>✦<span></span></div>
                <p>{{ customization.closing_message }}</p>
            </footer>
        </article>
    </div>
</template>

<style src="../../../css/modern-classic.css"></style>
