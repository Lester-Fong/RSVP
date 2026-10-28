<script setup lang="ts">
import { ref } from 'vue';
import EditorialStoryTimeline from '../../../resources/js/components/wedding/enchanted-editorial/EditorialStoryTimeline.vue';
import { enchantedEditorialContent } from './enchanted-editorial-content';
const story = enchantedEditorialContent('timeline').sections.find((section) => section.type === 'story');
const milestones = ref(story?.type === 'story' ? [...(story.milestones || [])] : []);
const visible = ref(true);
</script>
<template>
    <div class="enchanted-editorial">
        <nav aria-label="Story test controls">
            <button type="button" @click="milestones.reverse()">Reverse moments</button>
            <button type="button" @click="milestones.shift()">Remove first moment</button>
            <button type="button" @click="visible = !visible">{{ visible ? 'Hide timeline' : 'Show timeline' }}</button>
        </nav>
        <div style="height: 90vh" aria-hidden="true"></div>
        <EditorialStoryTimeline v-if="visible" :milestones="milestones" />
        <div style="height: 100vh" aria-hidden="true"></div>
    </div>
</template>
