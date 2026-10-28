<script setup lang="ts">
import '@/../css/enchanted-editorial-sections.css';
import '@/../css/enchanted-editorial.css';
import { openingSessionKey, shouldShowOpening } from '@/lib/photobooth-opening';
import type { EnchantedEditorialPresentation } from '@/types/enchanted-editorial';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import serifFont from '../../../../assets/enchanted-editorial/fonts/cormorant-garamond.ttf?url';
import sansFont from '../../../../assets/floral-trial/fonts/roboto.ttf?url';
import EditorialEntourage from './EditorialEntourage.vue';
import EditorialGallery from './EditorialGallery.vue';
import EditorialGuestGuide from './EditorialGuestGuide.vue';
import EditorialOrnament from './EditorialOrnament.vue';
import EditorialPhoto from './EditorialPhoto.vue';
import EditorialRsvpClosing from './EditorialRsvpClosing.vue';
import EditorialStory from './EditorialStory.vue';
import EnchantedBotanical from './EnchantedBotanical.vue';
import EnchantedLoading from './EnchantedLoading.vue';
import EnchantedPhotobooth from './EnchantedPhotobooth.vue';
import EnchantedWeddingDetails from './EnchantedWeddingDetails.vue';
import WeddingCountdown from './WeddingCountdown.vue';

const props = defineProps<{ presentation: EnchantedEditorialPresentation; editorPreview?: boolean; publicWebsite?: boolean }>();
const openingVisible = ref(shouldShowOpening(props.presentation, Boolean(props.publicWebsite)));
const openingAtEntry = Boolean(props.publicWebsite && props.presentation.opening);
const invitationReady = ref(false);
let openingDismissed = false;
function focusInvitation() {
    if (!openingDismissed || !invitationReady.value) return;
    const doc = header.value?.ownerDocument;
    const hash = doc?.defaultView?.location.hash.slice(1);
    (doc?.getElementById(hash || 'invitation') ?? doc?.getElementById('invitation'))?.focus({ preventScroll: true });
}
async function openInvitation() {
    if (!openingVisible.value) return;
    openingVisible.value = false;
    openingDismissed = true;
    try {
        sessionStorage.setItem(openingSessionKey(props.presentation.opening!.weddingKey), 'opened');
    } catch {
        /* Direct access still works. */
    }
    await nextTick();
    focusInvitation();
}
async function contentReady() {
    invitationReady.value = true;
    await nextTick();
    focusInvitation();
}
function onHashChange() {
    if (openingVisible.value) void openInvitation();
}
const header = ref<HTMLElement | null>(null);
const menuButton = ref<HTMLButtonElement | null>(null);
const menuOpen = ref(false);
const enhanced = ref(false);
let mobile: MediaQueryList | undefined;
function closeMenu(restoreFocus = false) {
    if (!menuOpen.value) return;
    menuOpen.value = false;
    if (restoreFocus) menuButton.value?.focus();
}
async function toggleMenu() {
    if (menuOpen.value) return closeMenu(true);
    menuOpen.value = true;
    await nextTick();
    header.value?.querySelector<HTMLAnchorElement>('nav a')?.focus();
}
async function navigate(event: MouseEvent) {
    const link = (event.target as Element).closest<HTMLAnchorElement>('a[href^="#"]');
    if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const target = link.ownerDocument.getElementById(link.hash.slice(1));
    if (!target) return;
    event.preventDefault();
    closeMenu();
    await nextTick();
    target.focus({ preventScroll: true });
    const win = link.ownerDocument.defaultView;
    if (!win) return;
    if (win.location.protocol !== 'about:' && win.location.hash !== link.hash) win.history.pushState(null, '', link.hash);
    const sticky = header.value && ['fixed', 'sticky'].includes(win.getComputedStyle(header.value).position);
    const offset = Math.max(parseFloat(win.getComputedStyle(target).scrollMarginTop) || 0, sticky ? header.value!.getBoundingClientRect().bottom : 0);
    win.scrollTo({
        top: Math.max(0, target.getBoundingClientRect().top + win.scrollY - offset),
        behavior: win.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
    });
}
function outside(event: PointerEvent) {
    if (!header.value?.contains(event.target as Node)) closeMenu(true);
}
function resize() {
    const focusInMenu = header.value?.querySelector('nav')?.contains(header.value.ownerDocument.activeElement);
    if (!mobile?.matches && header.value?.ownerDocument.activeElement === menuButton.value)
        header.value?.querySelector<HTMLAnchorElement>('nav a')?.focus();
    closeMenu(Boolean(mobile?.matches && focusInMenu));
}
onMounted(() => {
    enhanced.value = true;
    const doc = header.value?.ownerDocument;
    mobile = doc?.defaultView?.matchMedia('(max-width: 820px)');
    mobile?.addEventListener('change', resize);
    doc?.addEventListener('pointerdown', outside);
    doc?.defaultView?.addEventListener('hashchange', onHashChange);
});
onBeforeUnmount(() => {
    mobile?.removeEventListener('change', resize);
    header.value?.ownerDocument.removeEventListener('pointerdown', outside);
    header.value?.ownerDocument.defaultView?.removeEventListener('hashchange', onHashChange);
});
const sections = computed(() =>
    props.presentation.sections.filter((section) => {
        if (!section.enabled) return false;
        if (props.editorPreview) return true;
        if (section.type === 'story')
            return Boolean(section.photo || section.paragraphs.some((paragraph) => paragraph.trim()) || section.milestones?.length);
        if (section.type === 'gallery') return section.items.length > 0;
        if (section.type === 'entourage') return section.groups.some((group) => group.people.some((person) => person.name.trim()));
        return true;
    }),
);
const navigation = {
    story: ['our-story', 'Our story'],
    event_details: ['wedding-details', 'Wedding details'],
    gallery: ['our-photos', 'Photos'],
    entourage: ['entourage', 'Entourage'],
    guest_guide: ['guest-guide', 'Guest Guide'],
    rsvp_information: ['rsvp', 'RSVP'],
    countdown: ['countdown', 'Countdown'],
    closing: ['closing', 'With love'],
} as const;
const initials = computed(() =>
    [props.presentation.wedding.partner_one_name, props.presentation.wedding.partner_two_name].map((name) => Array.from(name.trim())[0] || ''),
);
const dateLabel = computed(() => {
    const date = props.presentation.wedding.wedding_date;
    if (!date) return 'Our wedding day';
    const parsed = new Date(`${date}T00:00:00Z`);
    return Number.isNaN(parsed.getTime())
        ? 'Date to be announced'
        : new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(parsed);
});
</script>

<template>
    <EnchantedLoading :hold="openingVisible" :opening="openingAtEntry" @ready="contentReady">
        <template #opening>
            <Transition name="ee-booth-exit"
                ><EnchantedPhotobooth
                    v-if="openingVisible"
                    :presentation="presentation"
                    :ready="invitationReady"
                    :date-label="dateLabel"
                    @open="openInvitation"
            /></Transition>
        </template>
        <div class="enchanted-editorial">
            <link rel="preload" :href="serifFont" as="font" type="font/ttf" crossorigin="anonymous" />
            <link rel="preload" :href="sansFont" as="font" type="font/ttf" crossorigin="anonymous" />
            <a class="ee-skip" href="#invitation">Skip to invitation</a>
            <header
                ref="header"
                class="ee-header ee-container"
                :data-menu-enhanced="enhanced"
                :data-menu-open="menuOpen"
                @keydown.esc="
                    if (menuOpen) {
                        $event.preventDefault();
                        closeMenu(true);
                    }
                "
                @focusout="if (!($event.relatedTarget && header?.contains($event.relatedTarget as Node))) closeMenu();"
            >
                <a href="#invitation" class="ee-identity" aria-label="Our wedding invitation">
                    <span class="ee-monogram" aria-hidden="true">{{ initials[0] }}<span>&amp;</span>{{ initials[1] }}</span>
                    <span class="ee-identity-label">Together, always</span>
                </a>
                <button
                    v-if="enhanced"
                    ref="menuButton"
                    type="button"
                    class="ee-menu-toggle"
                    aria-controls="ee-wedding-navigation"
                    :aria-expanded="menuOpen"
                    :aria-label="menuOpen ? 'Close wedding menu' : 'Open wedding menu'"
                    @click="toggleMenu"
                >
                    <span class="ee-menu-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                    {{ menuOpen ? 'Close' : 'Menu' }}
                </button>
                <nav id="ee-wedding-navigation" class="ee-navigation" aria-label="Wedding sections" @click="navigate">
                    <a href="#invitation">Invitation</a>
                    <a
                        v-for="section in sections.filter((section) => section.type !== 'countdown')"
                        :key="section.id"
                        :href="`#${navigation[section.type][0]}`"
                        >{{ navigation[section.type][1] }}</a
                    >
                    <a v-if="$slots['personal-rsvp'] && !sections.some((section) => section.type === 'rsvp_information')" href="#personal-rsvp"
                        >RSVP</a
                    >
                </nav>
                <time v-if="presentation.wedding.wedding_date" :datetime="presentation.wedding.wedding_date" class="ee-header-date">{{
                    dateLabel
                }}</time>
            </header>

            <main aria-label="Wedding invitation">
                <section id="invitation" class="ee-hero ee-container" aria-labelledby="ee-couple-names" tabindex="-1">
                    <div class="ee-hero-copy">
                        <slot name="personal-greeting" />
                        <p class="ee-eyebrow">{{ presentation.hero.eyebrow }}</p>
                        <h1 id="ee-couple-names">
                            <span>{{ presentation.wedding.partner_one_name }}</span>
                            <span class="ee-ampersand">&amp;</span>
                            <span>{{ presentation.wedding.partner_two_name }}</span>
                        </h1>
                        <p class="ee-hero-message">{{ presentation.hero.message }}</p>
                        <p class="ee-wedding-date">
                            <span class="ee-date-rule" aria-hidden="true"></span
                            ><time :datetime="presentation.wedding.wedding_date || undefined">{{ dateLabel }}</time>
                        </p>
                        <a v-if="sections.some((section) => section.type === 'event_details')" href="#wedding-details" class="ee-outline-link"
                            >The wedding details <span aria-hidden="true">↗</span></a
                        >
                    </div>

                    <div class="ee-hero-composition">
                        <span v-if="presentation.hero.photo" class="ee-hero-backdrop" aria-hidden="true"></span>
                        <EnchantedBotanical class="ee-hero-branch-top" />
                        <EditorialPhoto :photo="presentation.hero.photo" eager class="ee-hero-main" />
                        <EditorialPhoto v-if="presentation.hero.insetPhoto" :photo="presentation.hero.insetPhoto" class="ee-hero-inset" />
                        <EnchantedBotanical class="ee-hero-branch-bottom" />
                        <EditorialOrnament
                            kind="flower"
                            class="ee-landing-marker ee-landing-hero"
                            data-landing-section="invitation"
                            data-landing-id="section:invitation"
                        />
                        <p v-if="presentation.hero.photo" class="ee-composition-caption">The beginning of our forever</p>
                    </div>
                </section>

                <template v-for="section in sections" :key="section.id">
                    <EditorialStory v-if="section.type === 'story'" :section="section" />
                    <EnchantedWeddingDetails v-else-if="section.type === 'event_details'" :presentation="presentation" :date-label="dateLabel" />
                    <WeddingCountdown
                        v-else-if="section.type === 'countdown'"
                        :wedding="presentation.wedding"
                        :time-zone="presentation.timeZone"
                        :eyebrow="section.eyebrow"
                        :heading="section.heading"
                        :message="section.message"
                    />
                    <EditorialGallery v-else-if="section.type === 'gallery'" :section="section" :editor-preview="editorPreview" />
                    <EditorialEntourage v-else-if="section.type === 'entourage'" :section="section" :editor-preview="editorPreview" />
                    <EditorialGuestGuide
                        v-else-if="section.type === 'guest_guide'"
                        :section="section"
                        :personal-rsvp-href="
                            $slots['personal-rsvp']
                                ? sections.some((entry) => entry.type === 'rsvp_information')
                                    ? '#rsvp'
                                    : '#personal-rsvp'
                                : undefined
                        "
                    />
                    <EditorialRsvpClosing v-else-if="section.type === 'rsvp_information'" :section="section" :public-website="publicWebsite">
                        <template v-if="$slots['personal-rsvp']" #personal-rsvp><slot name="personal-rsvp" /></template>
                    </EditorialRsvpClosing>
                    <section
                        v-else-if="section.type === 'closing'"
                        id="closing"
                        class="ee-closing ee-container"
                        aria-label="Closing message"
                        tabindex="-1"
                    >
                        <EditorialOrnament kind="flower" class="ee-landing-marker" data-landing-section="closing" data-landing-id="section:closing" />
                        <span class="ee-eyebrow">With love and gratitude</span>
                        <p class="ee-rsvp-closing">{{ section.message }}</p>
                    </section>
                </template>
                <section
                    v-if="$slots['personal-rsvp'] && !sections.some((section) => section.type === 'rsvp_information')"
                    id="personal-rsvp"
                    class="ee-rsvp"
                    aria-label="Your RSVP"
                    tabindex="-1"
                >
                    <EditorialOrnament
                        class="ee-landing-marker ee-landing-section"
                        data-landing-section="personal-rsvp"
                        data-landing-id="section:personal-rsvp"
                    />
                    <div class="ee-container ee-rsvp-inner"><slot name="personal-rsvp" /></div>
                </section>
            </main>
            <footer class="ee-footer ee-container">
                <EditorialOrnament kind="rings" class="ee-footer-rings" />
                <p>With love, {{ presentation.wedding.partner_one_name }} &amp; {{ presentation.wedding.partner_two_name }}</p>
            </footer>
        </div>
    </EnchantedLoading>
</template>
