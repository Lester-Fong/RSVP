import type { EditorialPhoto, EnchantedEditorialPresentation } from '../../../resources/js/types/enchanted-editorial';

const photo = (file: string, alt: string, width: number, height: number, focalPoint: EditorialPhoto['focalPoint'] = 'center'): EditorialPhoto => ({
    url: `/__enchanted-assets/${file}.jpg`,
    alt,
    width,
    height,
    focalPoint,
});

/** Synthetic wedding facts. Supplied family photos are served only by the loopback fixture host. */
export function enchantedEditorialContent(variant: string | null): EnchantedEditorialPresentation {
    const base: EnchantedEditorialPresentation = {
        templateKey: 'enchanted-editorial',
        templateVersion: 1,
        timeZone: 'Asia/Manila',
        sections: [
            {
                id: '51000000-0000-4000-8000-000000000001',
                type: 'story',
                enabled: true,
                eyebrow: 'Of all the stories, ours is my favourite',
                heading: 'A thousand little moments. One beautiful forever.',
                paragraphs: [
                    'It was never just one grand moment. It was the easy conversations, the shared playlists, and finding a reason to laugh at the end of an ordinary day. Somewhere along the way, being together began to feel like coming home.',
                    'From our first adventure to the quiet afternoons in between, we have been choosing each other, one little moment at a time. Now, with our favourite people beside us, we are ready for the most beautiful chapter yet.',
                ],
                photo: photo('story-photo', 'The couple laughing together while playing a game', 1280, 853),
                insetPhoto: photo('gallery-01', 'The couple leaning together on a quiet garden path', 1280, 853, 'upper'),
            },
            { id: '51000000-0000-4000-8000-000000000002', type: 'event_details', enabled: true },
            {
                id: '51000000-0000-4000-8000-000000000003',
                type: 'countdown',
                enabled: true,
                eyebrow: 'Every moment brings us closer',
                heading: 'Until we say “I do”',
                message: 'We cannot wait to share this day with you.',
            },
            {
                id: '51000000-0000-4000-8000-000000000004',
                type: 'gallery',
                enabled: true,
                eyebrow: 'The moments in between',
                heading: 'A love like ours',
                message: 'A few of our favourite memories, before the beginning of forever.',
                items: [
                    {
                        id: '61000000-0000-4000-8000-000000000001',
                        photo: photo('gallery-01', 'The couple smiling together on a garden path', 1280, 853),
                        caption: 'With you, every place feels like home.',
                    },
                    {
                        id: '61000000-0000-4000-8000-000000000002',
                        photo: photo('gallery-02', 'The couple standing together against a stone wall', 1280, 830),
                        caption: 'Side by side, through every chapter.',
                    },
                    {
                        id: '61000000-0000-4000-8000-000000000003',
                        photo: photo('gallery-03', 'The couple raising a toast together', 1280, 853),
                        caption: 'Here is to all the little celebrations.',
                    },
                    {
                        id: '61000000-0000-4000-8000-000000000004',
                        photo: photo('gallery-04', 'The couple enjoying a quiet afternoon outdoors', 1280, 980),
                        caption: 'The ordinary days are our favourite.',
                    },
                    {
                        id: '61000000-0000-4000-8000-000000000005',
                        photo: photo('gallery-05', 'The couple sharing a picnic on the lawn', 1280, 853),
                        caption: 'A little laughter. A lifetime of love.',
                    },
                    {
                        id: '61000000-0000-4000-8000-000000000006',
                        photo: photo('gallery-06', 'The couple dressed elegantly together', 1280, 1083),
                        caption: 'Always, in our own way.',
                    },
                ],
            },
            {
                id: '51000000-0000-4000-8000-000000000005',
                type: 'entourage',
                enabled: true,
                eyebrow: 'With love and gratitude',
                heading: 'The people beside us',
                message: 'Our families, our dearest friends, and the people who have helped us become who we are.',
                groups: [
                    { id: 'parents-bride', title: 'Parents of the Bride', people: [{ id: 'pb-1', name: 'Rafael & Elena Mendoza' }] },
                    { id: 'parents-groom', title: 'Parents of the Groom', people: [{ id: 'pg-1', name: 'Antonio & Sofia Reyes' }] },
                    {
                        id: 'principal',
                        title: 'Principal Sponsors',
                        people: ['Ricardo & Lucia Santos', 'Eduardo & Carmen Villanueva', 'Manuel & Teresa Castillo', 'Roberto & Isabel Navarro'].map(
                            (name, index) => ({ id: `principal-${index}`, name }),
                        ),
                    },
                    {
                        id: 'secondary',
                        title: 'Secondary Sponsors',
                        people: ['Marco & Patricia Cruz', 'Daniel & Clara Santiago', 'Luis & Ana Rivera'].map((name, index) => ({
                            id: `secondary-${index}`,
                            name,
                        })),
                    },
                    { id: 'best-man', title: 'Best Man', people: [{ id: 'bm-1', name: 'Nicolas Reyes' }] },
                    { id: 'maid-honor', title: 'Maid of Honor', people: [{ id: 'mh-1', name: 'Sofia Mendoza' }] },
                    {
                        id: 'bridesmaids',
                        title: 'Bridesmaids',
                        people: ['Camille Santos', 'Andrea Cruz', 'Julia Ramos'].map((name, index) => ({ id: `bridesmaid-${index}`, name })),
                    },
                    {
                        id: 'groomsmen',
                        title: 'Groomsmen',
                        people: ['Mateo Garcia', 'Paolo Rivera', 'Enzo Santiago'].map((name, index) => ({ id: `groomsman-${index}`, name })),
                    },
                ],
            },
            {
                id: '51000000-0000-4000-8000-000000000006',
                type: 'rsvp_information',
                enabled: true,
                eyebrow: 'The celebration is sweeter with you',
                heading: 'We would love to see you there.',
                message:
                    'Your presence will make our day even more meaningful. We look forward to gathering, celebrating, and making memories with you.',
                closing: 'Until then, all our love.',
            },
        ],
        wedding: {
            partner_one_name: 'Isabel',
            partner_two_name: 'Gabriel',
            wedding_date: '2027-02-20',
            wedding_time: '15:00',
            ceremony_venue: 'The Garden Chapel',
            reception_venue: 'Hacienda de la Rosa',
        },
        hero: {
            eyebrow: 'Together with our families',
            message: 'With full hearts, we invite you to celebrate the beginning of our forever.',
            photo: photo('hero', 'The couple sharing a playful moment in the sunshine', 1280, 853, 'upper'),
            insetPhoto: photo('couple-portrait', 'A portrait of the couple together', 1280, 1351, 'upper'),
        },
        details: {
            eyebrow: 'A day to remember',
            heading: 'Our favourite day. Our favourite people.',
            message:
                'Some days stay with us forever. This will be one of them. Please join us as we exchange our vows, share a meal, and begin a new chapter surrounded by the people we love.',
            photo: photo('couple-closeup', 'The couple holding a bouquet beneath a leafy arch', 1280, 1170, 'upper'),
            insetPhoto: photo('romantic-photo', 'The couple celebrating together in a garden', 1280, 1083),
            events: [
                {
                    id: '41000000-0000-4000-8000-000000000001',
                    kind: 'ceremony',
                    address: 'Tagaytay City, Cavite',
                    directions: 'Please arrive a little early to settle in before the ceremony.',
                    map_url: 'https://www.openstreetmap.org/#map=13/14.1153/120.9621',
                },
                {
                    id: '41000000-0000-4000-8000-000000000002',
                    kind: 'reception',
                    address: 'Silang, Cavite',
                    directions: 'Dinner, warm conversations, and a little dancing.',
                    map_url: 'https://www.openstreetmap.org/#map=13/14.2306/120.9747',
                },
            ],
        },
    };
    if (variant === 'portrait-gallery')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'gallery'
                    ? {
                          ...section,
                          items: [
                              {
                                  ...section.items[0],
                                  photo: photo('couple-portrait', 'A portrait-orientation gallery photograph', 1280, 1351, 'upper'),
                              },
                              ...section.items.slice(1),
                          ],
                      }
                    : section,
            ),
        };
    if (variant === 'single-photo')
        return {
            ...base,
            sections: base.sections.map((section) => (section.type === 'gallery' ? { ...section, items: section.items.slice(0, 1) } : section)),
        };
    if (variant === 'date-pending') return { ...base, wedding: { ...base.wedding, wedding_time: null } };
    if (variant === 'escaped')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? { ...section, heading: '<img src=x onerror="window.__xss=1">', paragraphs: ['<script>window.__xss=1</script>'] }
                    : section.type === 'entourage'
                      ? {
                            ...section,
                            groups: [
                                { id: 'custom', title: 'Our Chosen Family', people: [{ id: 'safe-name', name: '<svg onload="window.__xss=1">' }] },
                            ],
                        }
                      : section.type === 'gallery'
                        ? {
                              ...section,
                              items: section.items.map((item, index) =>
                                  index === 0 ? { ...item, caption: '<img src=x onerror="window.__xss=1">' } : item,
                              ),
                          }
                        : section,
            ),
        };
    if (variant === 'timeline')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? {
                          ...section,
                          milestones: Array.from({ length: 12 }, (_, index) => ({
                              id: 'synthetic-moment-' + index,
                              title: 'A meaningful moment ' + (index + 1),
                              description:
                                  index % 2
                                      ? 'A long memory, told with warmth and gratitude. '.repeat(20)
                                      : 'A simple moment that brought us closer.',
                              when:
                                  index === 0
                                      ? { precision: 'year' as const, value: 2020 }
                                      : index === 1
                                        ? { precision: 'date' as const, value: '2025-03-09' }
                                        : null,
                              photo: index % 3 === 0 ? section.photo : null,
                          })),
                      }
                    : section,
            ),
        };
    if (variant === 'minimal')
        return { ...base, sections: base.sections.filter((section) => section.type === 'event_details' || section.type === 'rsvp_information') };
    if (variant === 'reordered') return { ...base, sections: [...base.sections].reverse() };
    if (variant === 'short-story')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? { ...section, paragraphs: ['Our favourite adventure is the one we share.'], photo: null, insetPhoto: null }
                    : section,
            ),
        };
    if (variant === 'long-story')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? { ...section, paragraphs: [...section.paragraphs, ...section.paragraphs, ...section.paragraphs] }
                    : section,
            ),
        };
    if (variant === 'many-sponsors')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'entourage'
                    ? {
                          ...section,
                          groups: [
                              ...section.groups,
                              {
                                  id: 'custom-sponsors',
                                  title: 'Our Cherished Friends and Mentors',
                                  people: Array.from({ length: 48 }, (_, index) => ({
                                      id: `mentor-${index}`,
                                      name: `Alexandra Maria & Gabriel Antonio de los Santos ${index + 1}`,
                                  })),
                              },
                          ],
                      }
                    : section,
            ),
        };
    if (variant === 'empty')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? { ...section, paragraphs: [], photo: null, insetPhoto: null }
                    : section.type === 'gallery'
                      ? { ...section, items: [] }
                      : section.type === 'entourage'
                        ? { ...section, groups: [] }
                        : section,
            ),
        };
    if (variant === 'long')
        return {
            ...base,
            wedding: {
                ...base.wedding,
                partner_one_name: 'Isabella-Maria Alexandra',
                partner_two_name: 'Gabriel Antonio de los Santos',
                ceremony_venue: 'The Chapel of Our Lady of Everlasting Hope and Thanksgiving',
                reception_venue: 'Hacienda de la Rosa Botanical Conservatory and Garden Pavilion',
            },
        };
    if (variant === 'unbroken')
        return { ...base, wedding: { ...base.wedding, partner_one_name: 'Alexandria'.repeat(6), reception_venue: 'GardenPavilion'.repeat(12) } };
    if (variant === 'alternate')
        return {
            ...base,
            sections: base.sections.map((section) =>
                section.type === 'gallery'
                    ? { ...section, items: [...section.items].reverse() }
                    : section.type === 'story'
                      ? {
                            ...section,
                            photo: photo('couple-portrait', 'An alternate portrait of the couple', 1280, 1351, 'upper'),
                            insetPhoto: photo('story-photo', 'A playful moment together', 1280, 853),
                        }
                      : section,
            ),
            hero: {
                ...base.hero,
                photo: photo('couple-portrait', 'An alternate portrait crop of the couple', 1280, 1351, 'upper'),
                insetPhoto: photo('romantic-photo', 'An alternate landscape crop in the garden', 1280, 1083),
            },
            details: {
                ...base.details,
                photo: photo('romantic-photo', 'An alternate landscape crop of the couple celebrating', 1280, 1083),
                insetPhoto: photo('hero', 'The couple enjoying the sunshine', 1280, 853, 'upper'),
            },
        };
    if (variant === 'missing')
        return {
            ...base,
            hero: { ...base.hero, photo: null, insetPhoto: null },
            details: { ...base.details, photo: null, insetPhoto: null },
            sections: base.sections.map((section) =>
                section.type === 'story'
                    ? { ...section, photo: null, insetPhoto: null }
                    : section.type === 'gallery'
                      ? { ...section, items: section.items.map((item) => ({ ...item, photo: null })) }
                      : section,
            ),
        };
    return base;
}
