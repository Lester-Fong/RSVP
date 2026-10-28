<script setup lang="ts">
import type { EditorialStorySection } from '@/types/enchanted-editorial';
import EditorialOrnament from './EditorialOrnament.vue';
import EditorialPhoto from './EditorialPhoto.vue';
import EditorialStoryTimeline from './EditorialStoryTimeline.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';
defineProps<{ section: EditorialStorySection }>();
</script>

<template>
    <section id="our-story" class="ee-story-section" aria-labelledby="ee-story-heading" tabindex="-1">
        <EditorialOrnament
            kind="flower"
            class="ee-landing-marker ee-landing-story"
            data-landing-section="our-story"
            data-landing-id="section:our-story"
        />
        <div class="ee-story ee-container" :class="{ 'ee-story-text-only': !section.photo }">
            <div class="ee-story-copy">
                <p class="ee-eyebrow">{{ section.eyebrow }}</p>
                <h2 id="ee-story-heading" class="ee-section-heading">{{ section.heading }}</h2>
                <div class="ee-story-prose">
                    <p v-for="(paragraph, index) in section.paragraphs" :key="index">{{ paragraph }}</p>
                </div>
                <EditorialOrnament kind="plane" class="ee-story-plane" />
            </div>
            <div v-if="section.photo" class="ee-story-composition">
                <EnchantedBotanical class="ee-story-branch" />
                <EditorialPhoto :photo="section.photo" class="ee-story-main" />
                <EditorialPhoto v-if="section.insetPhoto" :photo="section.insetPhoto" class="ee-story-inset" />
            </div>
        </div>
        <EditorialStoryTimeline v-if="section.milestones?.length" :milestones="section.milestones" />
    </section>
</template>
