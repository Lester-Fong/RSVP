<script setup lang="ts">
import { ref } from 'vue';
import EnchantedEditorialInvitation from '../../../resources/js/components/wedding/enchanted-editorial/EnchantedEditorialInvitation.vue';
import { enchantedEditorialContent } from './enchanted-editorial-content';
import HummingbirdShowcase from './HummingbirdShowcase.vue';
import StoryRevealLifecycleFixture from './StoryRevealLifecycleFixture.vue';
defineProps<{ variant: string | null; canvas: boolean }>();
const previewVisible = ref(true);
</script>

<template>
    <div>
        <StoryRevealLifecycleFixture v-if="variant === 'timeline-lifecycle'" />
        <HummingbirdShowcase v-if="variant === 'hummingbird'" />
        <aside
            v-if="!canvas && variant !== 'hummingbird' && variant !== 'timeline-lifecycle'"
            class="enchanted-preview-bar"
            aria-label="Visual trial controls"
        >
            <div><strong>Enchanted Editorial</strong><span>Isolated visual trial · synthetic wedding details</span></div>
            <nav aria-label="Preview variations">
                <a
                    v-for="[key, label] in [
                        ['', 'Default'],
                        ['alternate', 'Alternate photos'],
                        ['long', 'Long names'],
                        ['missing', 'Missing photos'],
                        ['minimal', 'Optional sections off'],
                        ['many-sponsors', 'Many sponsors'],
                    ]"
                    :key="key"
                    :href="`?case=${key}`"
                    :aria-current="(variant || '') === key ? 'page' : undefined"
                    >{{ label }}</a
                >
            </nav>
        </aside>
        <button v-if="variant === 'timer-lifecycle'" type="button" @click="previewVisible = !previewVisible">
            {{ previewVisible ? 'Hide preview' : 'Show preview' }}
        </button>
        <EnchantedEditorialInvitation
            v-if="previewVisible && variant !== 'hummingbird' && variant !== 'timeline-lifecycle'"
            :presentation="enchantedEditorialContent(variant)"
        />
    </div>
</template>
