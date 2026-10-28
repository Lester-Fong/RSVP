<script setup lang="ts">
import type { EditorialGuestGuideSection } from '@/types/enchanted-editorial';
import EditorialOrnament from './EditorialOrnament.vue';

defineProps<{ section: EditorialGuestGuideSection; personalRsvpHref?: string }>();

function isRsvpQuestion(question: string): boolean {
    return /\brsvp\b/i.test(question);
}
</script>

<template>
    <section id="guest-guide" class="ee-guest-guide" aria-labelledby="ee-guest-guide-heading" tabindex="-1">
        <EditorialOrnament class="ee-landing-marker ee-landing-section" data-landing-section="guest-guide" data-landing-id="section:guest-guide" />
        <div class="ee-container ee-guest-guide-inner">
            <p class="ee-eyebrow">A few thoughtful details</p>
            <h2 id="ee-guest-guide-heading" class="ee-section-heading">For our guests</h2>
            <div class="ee-guest-guide-grid">
                <section v-if="section.attire" class="ee-guide-card ee-guide-attire" aria-labelledby="ee-attire-heading">
                    <p class="ee-eyebrow">Attire</p>
                    <h3 id="ee-attire-heading">{{ section.attire.dress_code }}</h3>
                    <p v-if="section.attire.description" class="ee-guide-copy">{{ section.attire.description }}</p>
                    <ul v-if="section.attire.swatches.length" class="ee-guide-swatches" aria-label="Suggested colors">
                        <li v-for="swatch in section.attire.swatches" :key="swatch.id">
                            <span class="ee-guide-swatch" :style="{ backgroundColor: swatch.color }" aria-hidden="true"></span>
                            <span>{{ swatch.label }}</span>
                        </li>
                    </ul>
                </section>
                <section v-if="section.gifts" class="ee-guide-card ee-guide-gifts" aria-labelledby="ee-gifts-heading">
                    <p class="ee-eyebrow">A note on gifts</p>
                    <h3 id="ee-gifts-heading">{{ section.gifts.heading }}</h3>
                    <p class="ee-guide-copy">{{ section.gifts.message }}</p>
                    <a v-if="section.gifts.url" class="ee-guide-link" :href="section.gifts.url" target="_blank" rel="noopener noreferrer">
                        View gift details <span aria-hidden="true">↗</span><span class="ee-sr-only"> (opens in a new tab)</span>
                    </a>
                </section>
                <section v-if="section.faqs" class="ee-guide-card ee-guide-faqs" aria-labelledby="ee-faqs-heading">
                    <p class="ee-eyebrow">A few helpful notes</p>
                    <h3 id="ee-faqs-heading">Frequently asked questions</h3>
                    <div class="ee-guide-accordion">
                        <details v-for="item in section.faqs.items" :key="item.id" class="ee-guide-faq">
                            <summary>{{ item.question }}</summary>
                            <div class="ee-guide-answer">
                                <p>{{ item.answer }}</p>
                                <p v-if="isRsvpQuestion(item.question)" class="ee-guide-rsvp-note">
                                    <a v-if="personalRsvpHref" :href="personalRsvpHref">Open your personal RSVP</a>
                                    <template v-else
                                        >For RSVP details, please use the personal invitation link shared with you. Contact the couple if you need
                                        help.</template
                                    >
                                </p>
                            </div>
                        </details>
                        <p v-if="!section.faqs.items.length" class="ee-guide-copy">More details will be shared soon.</p>
                    </div>
                </section>
            </div>
        </div>
    </section>
</template>
