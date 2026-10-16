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

<style scoped>
.invitation-hero-photo {
    position: relative;
    display: block;
    width: 100%;
    max-width: 36rem;
    height: auto;
    margin: 2rem auto;
}

.invitation-page {
    --invitation-ink: #342f29;
    --invitation-muted: #756b5d;
    --invitation-gold: #806440;
    --invitation-line: #d8cdbb;
    --invitation-paper: #fbf9f4;

    display: grid;
    min-height: calc(100svh - 3.5rem);
    place-items: center;
    padding: clamp(1rem, 4vw, 3.5rem);
    color: var(--invitation-ink);
    background: #f1ece2;
    font-family: Georgia, 'Times New Roman', serif;
}

.invitation-card {
    position: relative;
    width: min(100%, 58rem);
    overflow: hidden;
    padding: clamp(3.5rem, 9vw, 7rem) clamp(1.25rem, 8vw, 6rem);
    border: 1px solid var(--invitation-line);
    background: var(--invitation-paper);
    text-align: center;
    box-shadow: 0 1.25rem 3rem rgb(52 47 41 / 8%);
}

.invitation-frame {
    position: absolute;
    inset: 0.65rem;
    border: 1px solid rgb(155 128 87 / 38%);
    pointer-events: none;
}

.invitation-heading,
.invitation-details,
.invitation-footer {
    position: relative;
}

.invitation-eyebrow,
.invitation-section-title,
.invitation-venue h3 {
    color: var(--invitation-gold);
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    line-height: 1.5;
    text-transform: uppercase;
}

.invitation-eyebrow {
    margin: 0 0 1.5rem;
}

.invitation-names {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1em;
    margin: 0;
    font-size: clamp(2.5rem, 8vw, 5.3rem);
    font-weight: 400;
    letter-spacing: -0.045em;
    line-height: 1.03;
    overflow-wrap: anywhere;
}

.invitation-ampersand {
    color: var(--invitation-gold);
    font-size: 0.58em;
    font-style: italic;
    line-height: 1.1;
}

.invitation-ornament {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.8rem;
    margin: 2rem 0;
    color: var(--invitation-gold);
    font-size: 0.85rem;
}

.invitation-ornament span {
    width: 3.2rem;
    height: 1px;
    background: var(--invitation-line);
}

.invitation-intro,
.invitation-footer p {
    margin: 0;
    color: var(--invitation-muted);
    font-size: clamp(1rem, 2.4vw, 1.25rem);
    font-style: italic;
    line-height: 1.75;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.invitation-details {
    margin: clamp(3rem, 8vw, 5rem) auto 0;
    padding-top: clamp(2rem, 5vw, 3rem);
    border-top: 1px solid var(--invitation-line);
}

.invitation-section-title {
    margin: 0 0 1.25rem;
}

.invitation-facts {
    display: grid;
    gap: 0.4rem;
    font-size: clamp(1.1rem, 3vw, 1.5rem);
    line-height: 1.5;
}

.invitation-facts p,
.invitation-venue p,
.invitation-note {
    margin: 0;
}

.invitation-venues {
    display: grid;
    gap: 1.5rem;
    margin: 2rem auto 0;
}

.invitation-venue h3 {
    margin: 0 0 0.4rem;
}

.invitation-venue p {
    color: var(--invitation-muted);
    font-size: 1rem;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.invitation-note {
    color: var(--invitation-muted);
    font-family: ui-sans-serif, system-ui, sans-serif;
    font-size: 0.9rem;
    line-height: 1.6;
}

.invitation-footer {
    margin-top: clamp(3rem, 8vw, 5rem);
}

.invitation-footer .invitation-ornament {
    margin-bottom: 1.2rem;
}

@media (min-width: 40rem) {
    .invitation-venues {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 2rem;
        max-width: 42rem;
    }
}
</style>
