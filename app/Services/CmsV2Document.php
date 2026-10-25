<?php

namespace App\Services;

/**
 * @phpstan-type MessageSection array{id: string, type: 'welcome'|'closing'|'rsvp_information', enabled: bool, data: array{message: string}}
 * @phpstan-type EventEntry array{id: string, kind: 'ceremony'|'reception', address: string, directions: string, map_url: ?string}
 * @phpstan-type EventSection array{id: string, type: 'event_details', enabled: bool, data: array{events: list<EventEntry>}}
 * @phpstan-type IntroSettings array{opening_text: string, open_label: string, font_preset: string, palette_preset: string, variant: array<string, string>}
 * @phpstan-type ImageReference array{asset_id: string, alt: string, focal: string, crop: string}
 * @phpstan-type StoryMilestone array{id: string, enabled: bool, title: string, description?: string, when?: array{precision: 'year'|'date', value: int|string}|null, photo?: ImageReference|null}
 * @phpstan-type StorySection array{id: string, type: 'story', enabled: bool, data: array{heading: string, paragraphs: list<string>, milestones?: list<StoryMilestone>}}
 * @phpstan-type GallerySection array{id: string, type: 'gallery', enabled: bool, data: array{heading: string, items: list<array{id: string, enabled: bool, image: ImageReference, caption?: string}>}}
 * @phpstan-type EntourageSection array{id: string, type: 'entourage', enabled: bool, data: array{heading: string, groups: list<array{id: string, enabled: bool, title: string, people: list<array{id: string, name: string}>}>}}
 * @phpstan-type GuestGuideSection array{id: string, type: 'guest_guide', enabled: bool, data: array{attire: array{enabled: bool, dress_code: string, description: string, swatches: list<array{id: string, label: string, color: string}>}, gifts: array{enabled: bool, heading: string, message: string, url: ?string}, faqs: array{enabled: bool, items: list<array{id: string, question: string, answer: string}>}}}
 * @phpstan-type IntroExperience array{enabled: false}|array{enabled: true, intro_style: string, intro_version: int, intro_settings: IntroSettings}
 * @phpstan-type CmsContent array{theme: array{palette: string, font_pair: string}, experience: IntroExperience, photo_slots?: array{hero: ImageReference|null, details: ImageReference|null, story: ImageReference|null}, sections: list<MessageSection|EventSection|StorySection|GallerySection|EntourageSection|GuestGuideSection>}
 */
final readonly class CmsV2Document
{
    /** @param CmsContent $content */
    private function __construct(
        public string $templateKey,
        public int $templateVersion,
        private array $content,
        private CmsV2Contract $contract,
    ) {}

    /**
     * @param  array<string, mixed>  $document
     */
    public static function fromArray(array $document, CmsV2Contract $contract): self
    {
        $validated = $contract->validate($document);

        return new self($validated['template_key'], $validated['template_version'], $validated['content'], $contract);
    }

    /** @return array{schema_version: int, template_key: string, template_version: int, content: CmsContent} */
    public function toArray(): array
    {
        return ['schema_version' => 2, 'template_key' => $this->templateKey, 'template_version' => $this->templateVersion, 'content' => $this->content];
    }

    /**
     * Presentation content only, not a public response or proof of publication/RSVP authority.
     *
     * @return CmsContent
     */
    public function publicContent(): array
    {
        return $this->contract->project($this->toArray());
    }

    /** @return list<string> Unique, deterministic retention or visible-projection references; not delivery authority. */
    public function assetIds(bool $visible = false): array
    {
        $content = $visible ? $this->publicContent() : $this->content;
        $ids = [];
        foreach ($content['photo_slots'] ?? [] as $reference) {
            if ($reference !== null) {
                $ids[] = $reference['asset_id'];
            }
        }
        foreach ($content['sections'] as $section) {
            $references = match ($section['type']) {
                'story' => array_column($section['data']['milestones'] ?? [], 'photo'),
                'gallery' => array_column($section['data']['items'], 'image'),
                default => [],
            };
            foreach ($references as $reference) {
                if ($reference !== null) {
                    $ids[] = $reference['asset_id'];
                }
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /** Change only the internal template selection, preserving every known section and its order. */
    public function withTemplate(string $key, int $version): self
    {
        $document = $this->toArray();
        $document['template_key'] = $key;
        $document['template_version'] = $version;

        return self::fromArray($document, $this->contract);
    }
}
