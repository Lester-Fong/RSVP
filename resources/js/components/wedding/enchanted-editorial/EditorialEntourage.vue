<script setup lang="ts">
import type { EditorialEntourageSection } from '@/types/enchanted-editorial';
import { computed } from 'vue';
import EditorialOrnament from './EditorialOrnament.vue';
const props = defineProps<{ section: EditorialEntourageSection; editorPreview?: boolean }>();
const groups = computed(() => props.section.groups.filter((group) => group.people.some((person) => person.name.trim())));
</script>

<template>
    <section id="entourage" class="ee-entourage ee-container" aria-labelledby="ee-entourage-heading" tabindex="-1">
        <EditorialOrnament class="ee-landing-marker ee-landing-section" data-landing-section="entourage" data-landing-id="section:entourage" />
        <header class="ee-centered-heading">
            <p class="ee-eyebrow">{{ section.eyebrow }}</p>
            <h2 id="ee-entourage-heading" class="ee-section-heading">{{ section.heading }}</h2>
            <p class="ee-body">{{ section.message }}</p>
        </header>
        <p v-if="editorPreview && !groups.length" class="ee-body" role="status">Add a role group in Entourage to celebrate your loved ones here.</p>
        <div class="ee-entourage-groups">
            <section
                v-for="group in groups"
                :key="group.id"
                class="ee-entourage-group"
                :class="{ 'ee-entourage-wide': group.people.length > 8 }"
                :aria-label="group.title"
            >
                <h3>{{ group.title }}</h3>
                <ul>
                    <li v-for="person in group.people.filter((person) => person.name.trim())" :key="person.id">{{ person.name }}</li>
                </ul>
            </section>
        </div>
    </section>
</template>
