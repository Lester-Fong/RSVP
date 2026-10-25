<script setup lang="ts">
import type { EnchantedEditorialPresentation } from '@/types/enchanted-editorial';
import { computed } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';
import EditorialPhoto from './EditorialPhoto.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';

const props = defineProps<{ presentation: EnchantedEditorialPresentation; dateLabel: string }>();
const ceremonyTime = computed(() => {
    const time = props.presentation.wedding.wedding_time;
    if (!time || !/^\d{2}:\d{2}$/.test(time)) return 'Time to be announced';
    const [hours, minutes] = time.split(':').map(Number);
    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours >= 12 ? 'PM' : 'AM'}`;
});

function safeMapUrl(value: string | null): string | null {
    if (!value) return null;
    try {
        const url = new URL(value);
        return url.protocol === 'https:' && !url.username && !url.password ? url.href : null;
    } catch {
        return null;
    }
}
</script>

<template>
    <section id="wedding-details" class="ee-details ee-container" aria-labelledby="ee-details-heading" tabindex="-1">
        <EditorialOrnament
            class="ee-landing-marker ee-landing-section"
            data-landing-section="wedding-details"
            data-landing-id="section:wedding-details"
        />
        <div class="ee-details-composition">
            <EnchantedBotanical class="ee-details-branch-top" />
            <EditorialPhoto :photo="presentation.details.photo" class="ee-details-main" />
            <EditorialPhoto v-if="presentation.details.insetPhoto" :photo="presentation.details.insetPhoto" class="ee-details-inset" />
            <EnchantedBotanical class="ee-details-branch-bottom" />
            <span class="ee-photo-note">A little moment, a lifetime of love.</span>
        </div>

        <div class="ee-details-copy">
            <p class="ee-eyebrow">{{ presentation.details.eyebrow }}</p>
            <h2 id="ee-details-heading">{{ presentation.details.heading }}</h2>
            <p class="ee-body">{{ presentation.details.message }}</p>
            <p class="ee-details-date">
                {{ dateLabel }} <span>· {{ presentation.timeZone === 'Asia/Manila' ? 'Philippine time' : presentation.timeZone }}</span>
            </p>

            <div class="ee-events">
                <section v-for="event in presentation.details.events" :key="event.id" class="ee-event" :aria-label="event.kind">
                    <div class="ee-event-label">
                        <h3>{{ event.kind === 'ceremony' ? 'The ceremony' : 'The reception' }}</h3>
                        <span>{{ event.kind === 'ceremony' ? ceremonyTime : 'Celebration to follow' }}</span>
                    </div>
                    <p class="ee-venue">
                        {{
                            (event.kind === 'ceremony' ? presentation.wedding.ceremony_venue : presentation.wedding.reception_venue) ||
                            'Venue to be announced'
                        }}
                    </p>
                    <p v-if="event.address" class="ee-address">{{ event.address }}</p>
                    <p v-if="event.directions" class="ee-directions">{{ event.directions }}</p>
                    <a
                        v-if="safeMapUrl(event.map_url)"
                        :href="safeMapUrl(event.map_url)!"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="ee-map-link"
                    >
                        View {{ event.kind }} map <span aria-hidden="true">↗</span><span class="ee-sr-only"> (opens in a new tab)</span>
                    </a>
                </section>
            </div>
        </div>
    </section>
</template>
