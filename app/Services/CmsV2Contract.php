<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

final class CmsV2Contract
{
    public const MAX_BYTES = 131072;

    public const SECTION_TYPES = ['welcome', 'event_details', 'countdown', 'closing', 'rsvp_information', 'story', 'gallery', 'entourage', 'guest_guide'];

    public const MAX_SECTIONS = 9;

    public const MAX_PARAGRAPHS = 4;

    public const MAX_PARAGRAPH_LENGTH = 1000;

    public const MAX_MILESTONES = 12;

    public const MAX_TITLE_LENGTH = 140;

    public const MAX_DESCRIPTION_LENGTH = 600;

    public const MAX_GALLERY_IMAGES = 10;

    public const MAX_ENTOURAGE_GROUPS = 16;

    public const MAX_PEOPLE_PER_GROUP = 64;

    public const MAX_ENTOURAGE_PEOPLE = 250;

    public const MAX_GUEST_GUIDE_SWATCHES = 5;

    public const MAX_GUEST_GUIDE_FAQS = 7;

    public const MAX_NAME_LENGTH = 160;

    public const FOCAL_PRESETS = ['center', 'top', 'bottom', 'left', 'right'];

    public const CROP_PRESETS = ['natural', 'portrait', 'landscape', 'square'];

    public function __construct(private WebsiteDesignRegistry $designs) {}

    /**
     * Validate internal contract data only; this does not enable saving, publishing or rendering.
     *
     * @param  array<string, mixed>  $document
     * @return array{schema_version: int, template_key: string, template_version: int, content: array<string, mixed>}
     */
    public function validate(array $document): array
    {
        try {
            $encoded = json_encode($document, JSON_THROW_ON_ERROR, 12);
        } catch (JsonException) {
            throw ValidationException::withMessages(['document' => 'Use JSON content with no more than 12 nesting levels.']);
        }
        if (strlen($encoded) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['document' => 'CMS content must not exceed 128 KiB.']);
        }

        $disabledIntro = data_get($document, 'content.experience.enabled') === false;
        $baseRules = [
            'document' => ['required', 'array:schema_version,template_key,template_version,content'],
            'document.schema_version' => ['required', 'integer:strict', Rule::in([2])],
            'document.template_key' => ['required', 'string', 'max:60'],
            'document.template_version' => ['required', 'integer:strict', 'min:1'],
            'document.content' => ['required', 'array:theme,experience,sections,photo_slots'],
            'document.content.photo_slots' => ['sometimes', 'array:hero,details,story', 'required_array_keys:hero,details,story'],
            'document.content.theme' => ['required', 'array:palette,font_pair'],
            'document.content.experience' => ['required', $disabledIntro ? 'array:enabled' : 'array:enabled,intro_style,intro_version,intro_settings'],
            'document.content.experience.enabled' => ['required', 'boolean:strict'],
            'document.content.sections' => ['present', 'array', 'list', 'max:'.self::MAX_SECTIONS],
            'document.content.sections.*' => ['required', 'array:id,type,enabled,data'],
            'document.content.sections.*.id' => ['required', 'string', 'uuid', 'lowercase', 'distinct:strict'],
            'document.content.sections.*.type' => ['required', 'string', Rule::in(self::SECTION_TYPES), 'distinct:strict'],
            'document.content.sections.*.enabled' => ['required', 'boolean:strict'],
            'document.content.sections.*.data' => ['required', 'array'],
        ];
        if (! $disabledIntro) {
            $baseRules += [
                'document.content.experience.intro_style' => ['required', 'string', 'max:60'],
                'document.content.experience.intro_version' => ['required', 'integer:strict', 'min:1'],
                'document.content.experience.intro_settings' => ['required', 'array:opening_text,open_label,font_preset,palette_preset,variant'],
            ];
        }
        $base = Validator::make(['document' => $document], $baseRules)->validate()['document'];

        $template = $this->designs->template($base['template_key'], $base['template_version'], $base['schema_version']);
        foreach ($base['content']['sections'] as $index => $section) {
            if (in_array($section['type'], ['countdown', 'guest_guide'], true) && ! in_array($section['type'], $template->sectionTypes, true)) {
                throw ValidationException::withMessages(['content.sections.'.$index.'.type' => ucfirst(str_replace('_', ' ', $section['type'])).' is available in Enchanted Editorial only.']);
            }
        }

        $rules = [
            'content.theme.palette' => ['required', 'string', Rule::in($template->themeCapabilities['palettes'])],
            'content.theme.font_pair' => ['required', 'string', Rule::in($template->themeCapabilities['font_pairs'])],
        ];
        if (array_key_exists('photo_slots', $base['content'])) {
            foreach (['hero', 'details', 'story'] as $slot) {
                $rules += $this->imageRules('content.photo_slots.'.$slot, false);
            }
        }
        if (! $disabledIntro) {
            $experience = $base['content']['experience'];
            $intro = $this->designs->introduction($experience['intro_style'], $experience['intro_version'], 2, $template);
            if ($intro->key === 'photobooth' && $template->key !== 'enchanted-editorial') {
                throw ValidationException::withMessages(['content.experience.intro_style' => 'The photobooth opening is available in Enchanted Editorial only.']);
            }
            $rules += [
                'content.experience.intro_settings.opening_text' => $this->textRules(500),
                'content.experience.intro_settings.open_label' => ['required', 'string', 'max:60', 'not_regex:/[<>]/u'],
                'content.experience.intro_settings.font_preset' => ['required', 'string', Rule::in($intro->key === 'photobooth' ? ['editorial-serif'] : ['editorial-serif', 'script-serif'])],
                'content.experience.intro_settings.palette_preset' => ['required', 'string', Rule::in($intro->key === 'photobooth' ? ['ivory-ink'] : ['ivory-ink', 'blush-terracotta'])],
                'content.experience.intro_settings.variant' => ['required', 'array:'.implode(',', array_keys($intro->variantOptions))],
            ];
            foreach ($intro->variantOptions as $key => $options) {
                $rules['content.experience.intro_settings.variant.'.$key] = ['required', 'string', Rule::in($options)];
            }
        }

        foreach ($base['content']['sections'] as $index => $section) {
            $prefix = 'content.sections.'.$index.'.data';
            if (in_array($section['type'], ['story', 'gallery', 'entourage'], true)) {
                $rules += match ($section['type']) {
                    'story' => $this->storyRules($prefix, $section['data']),
                    'gallery' => $this->galleryRules($prefix),
                    'entourage' => $this->entourageRules($prefix),
                };

                continue;
            }
            if ($section['type'] === 'countdown') {
                $rules[$prefix] = ['required', 'array:heading,subtitle'];
                $rules[$prefix.'.heading'] = $this->titleRules();
                $rules[$prefix.'.subtitle'] = $this->textRules(500);

                continue;
            }
            if ($section['type'] === 'guest_guide') {
                $rules += $this->guestGuideRules($prefix);

                continue;
            }
            if ($section['type'] !== 'event_details') {
                $rules[$prefix] = ['required', 'array:message'];
                $rules[$prefix.'.message'] = $this->textRules(500);

                continue;
            }

            $rules[$prefix] = ['required', 'array:events'];
            $rules[$prefix.'.events'] = ['present', 'array', 'list', 'max:2'];
            $rules[$prefix.'.events.*'] = ['required', 'array:id,kind,address,directions,map_url'];
            $rules[$prefix.'.events.*.id'] = ['required', 'string', 'uuid', 'lowercase', 'distinct:strict'];
            $rules[$prefix.'.events.*.kind'] = ['required', 'string', Rule::in(['ceremony', 'reception']), 'distinct:strict'];
            $rules[$prefix.'.events.*.address'] = $this->textRules(500);
            $rules[$prefix.'.events.*.directions'] = $this->textRules(1000);
            $rules[$prefix.'.events.*.map_url'] = [
                'present', 'nullable', 'string', 'max:2048', 'url:https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $parts = is_string($value) ? parse_url($value) : false;
                    if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f<>\\\\]/', $value)) {
                        $fail('Use an HTTPS map link without credentials or unsafe characters.');
                    }
                },
            ];
        }

        Validator::make($base, $rules, ['*.not_regex' => 'Use plain text without HTML or angle brackets.'])->validate();

        foreach ($base['content']['sections'] as $index => $section) {
            if ($section['type'] === 'entourage' && array_sum(array_map(fn (array $group): int => count($group['people']), $section['data']['groups'])) > self::MAX_ENTOURAGE_PEOPLE) {
                throw ValidationException::withMessages(['content.sections.'.$index.'.data.groups' => 'Use no more than '.self::MAX_ENTOURAGE_PEOPLE.' entourage people in total.']);
            }
            if ($section['type'] === 'guest_guide') {
                $this->assertNoPersonalCredential($section['data'], 'content.sections.'.$index.'.data');
            }
        }

        return $base;
    }

    /**
     * Internal display projection only. Media references grant no delivery or ownership rights.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function project(array $document): array
    {
        $validated = $this->validate($document);
        $template = $this->designs->template($validated['template_key'], $validated['template_version'], 2);
        $visible = $validated['content'];
        if (isset($visible['photo_slots'])) {
            foreach (['hero' => 'welcome', 'details' => 'event_details', 'story' => 'story'] as $slot => $type) {
                $displayed = array_filter($visible['sections'], fn (array $section): bool => $section['type'] === $type
                    && $section['enabled'] && in_array($type, $template->sectionTypes, true));
                if ($displayed === []) {
                    $visible['photo_slots'][$slot] = null;
                }
            }
        }
        $visible['sections'] = array_values(array_filter($visible['sections'], function (array $section) use ($template): bool {
            if (! $section['enabled'] || ! in_array($section['type'], $template->sectionTypes, true)) {
                return false;
            }
            if ($section['type'] === 'guest_guide') {
                return collect($section['data'])->contains(fn (array $block): bool => $block['enabled']);
            }

            return true;
        }));
        foreach ($visible['sections'] as &$section) {
            if ($section['type'] === 'guest_guide') {
                $section['data'] = array_filter($section['data'], fn (array $block): bool => $block['enabled']);

                continue;
            }
            $entries = match ($section['type']) {
                'story' => 'milestones',
                'gallery' => 'items',
                'entourage' => 'groups',
                default => null,
            };
            if ($entries !== null && isset($section['data'][$entries])) {
                $section['data'][$entries] = array_values(array_filter($section['data'][$entries], fn (array $entry): bool => $entry['enabled']));
            }
        }
        unset($section);

        return $visible;
    }

    /**
     * Validate a reusable slot reference, not its ownership, availability or publication.
     *
     * @param  array<string, mixed>  $reference
     * @return array{asset_id: string, alt: string, focal: string, crop: string, frame?: string}
     */
    public function imageReference(array $reference): array
    {
        Validator::make(['image' => $reference], $this->imageRules('image', true))->validate();

        return $reference;
    }

    /** @return array<string, array<int, mixed>> */
    private function storyRules(string $prefix, array $data): array
    {
        $rules = [
            $prefix => ['required', 'array:heading,paragraphs,milestones'],
            $prefix.'.heading' => $this->titleRules(),
            $prefix.'.paragraphs' => ['present', 'array', 'list', 'max:'.self::MAX_PARAGRAPHS],
            $prefix.'.paragraphs.*' => $this->textRules(self::MAX_PARAGRAPH_LENGTH),
            $prefix.'.milestones' => ['sometimes', 'array', 'list', 'max:'.self::MAX_MILESTONES],
            $prefix.'.milestones.*' => ['required', 'array:id,enabled,title,description,when,photo'],
            $prefix.'.milestones.*.id' => $this->idRules(),
            $prefix.'.milestones.*.enabled' => ['required', 'boolean:strict'],
            $prefix.'.milestones.*.title' => $this->titleRules(),
            $prefix.'.milestones.*.description' => ['sometimes', ...$this->textRules(self::MAX_DESCRIPTION_LENGTH)],
            $prefix.'.milestones.*.when' => ['sometimes', 'nullable', 'array:precision,value'],
        ];
        foreach (is_array($data['milestones'] ?? null) ? $data['milestones'] : [] as $index => $milestone) {
            $when = $prefix.'.milestones.'.$index.'.when';
            if (is_array($milestone) && isset($milestone['when'])) {
                $rules[$when.'.precision'] = ['required', 'string', Rule::in(['year', 'date'])];
                $rules[$when.'.value'] = data_get($milestone, 'when.precision') === 'year'
                    ? ['required', 'integer:strict', 'min:1', 'max:9999']
                    : ['required', 'string', 'date_format:Y-m-d', 'not_regex:/^0000-/'];
            }
        }

        return $rules + $this->imageRules($prefix.'.milestones.*.photo', false);
    }

    /** @return array<string, array<int, mixed>> */
    private function galleryRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array:heading,items,slideshow'],
            $prefix.'.slideshow' => ['sometimes', 'array:autoplay,interval_seconds', 'required_array_keys:autoplay,interval_seconds'],
            $prefix.'.slideshow.autoplay' => ['required_with:'.$prefix.'.slideshow', 'boolean:strict'],
            $prefix.'.slideshow.interval_seconds' => ['required_with:'.$prefix.'.slideshow', 'integer:strict', Rule::in([5, 8, 12])],
            $prefix.'.heading' => $this->titleRules(),
            $prefix.'.items' => ['present', 'array', 'list', 'max:'.self::MAX_GALLERY_IMAGES],
            $prefix.'.items.*' => ['required', 'array:id,enabled,image,caption'],
            $prefix.'.items.*.id' => $this->idRules(),
            $prefix.'.items.*.enabled' => ['required', 'boolean:strict'],
            $prefix.'.items.*.caption' => ['sometimes', ...$this->textRules(self::MAX_DESCRIPTION_LENGTH)],
            $prefix.'.items.*.image.asset_id' => ['required', 'string', 'uuid', 'lowercase', 'distinct:strict'],
        ] + $this->imageRules($prefix.'.items.*.image', true);
    }

    /** @return array<string, array<int, mixed>> */
    private function entourageRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array:heading,groups'],
            $prefix.'.heading' => $this->titleRules(),
            $prefix.'.groups' => ['present', 'array', 'list', 'max:'.self::MAX_ENTOURAGE_GROUPS],
            $prefix.'.groups.*' => ['required', 'array:id,enabled,title,people'],
            $prefix.'.groups.*.id' => $this->idRules(),
            $prefix.'.groups.*.enabled' => ['required', 'boolean:strict'],
            $prefix.'.groups.*.title' => $this->titleRules(),
            $prefix.'.groups.*.people' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_PEOPLE_PER_GROUP],
            $prefix.'.groups.*.people.*' => ['required', 'array:id,name'],
            $prefix.'.groups.*.people.*.id' => $this->idRules(),
            $prefix.'.groups.*.people.*.name' => ['required', ...$this->textRules(self::MAX_NAME_LENGTH)],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function guestGuideRules(string $prefix): array
    {
        return [
            $prefix => ['required', 'array:attire,gifts,faqs', 'required_array_keys:attire,gifts,faqs'],
            $prefix.'.attire' => ['required', 'array:enabled,dress_code,description,swatches', 'required_array_keys:enabled,dress_code,description,swatches'],
            $prefix.'.attire.enabled' => ['required', 'boolean:strict'],
            $prefix.'.attire.dress_code' => ['required', ...$this->textRules(100)],
            $prefix.'.attire.description' => $this->textRules(500),
            $prefix.'.attire.swatches' => ['present', 'array', 'list', 'max:'.self::MAX_GUEST_GUIDE_SWATCHES],
            $prefix.'.attire.swatches.*' => ['required', 'array:id,label,color'],
            $prefix.'.attire.swatches.*.id' => $this->idRules(),
            $prefix.'.attire.swatches.*.label' => ['required', ...$this->textRules(50)],
            $prefix.'.attire.swatches.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/D'],
            $prefix.'.gifts' => ['required', 'array:enabled,heading,message,url', 'required_array_keys:enabled,heading,message,url'],
            $prefix.'.gifts.enabled' => ['required', 'boolean:strict'],
            $prefix.'.gifts.heading' => $this->titleRules(),
            $prefix.'.gifts.message' => $this->textRules(1000),
            $prefix.'.gifts.url' => [
                'present', 'nullable', 'string', 'max:2048', 'url:https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }
                    $parts = parse_url($value);
                    if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20\x7f<>\\\\]/', $value)) {
                        $fail('Use an HTTPS gift link without credentials or unsafe characters.');
                    }
                },
            ],
            $prefix.'.faqs' => ['required', 'array:enabled,items', 'required_array_keys:enabled,items'],
            $prefix.'.faqs.enabled' => ['required', 'boolean:strict'],
            $prefix.'.faqs.items' => ['present', 'array', 'list', 'max:'.self::MAX_GUEST_GUIDE_FAQS],
            $prefix.'.faqs.items.*' => ['required', 'array:id,question,answer'],
            $prefix.'.faqs.items.*.id' => $this->idRules(),
            $prefix.'.faqs.items.*.question' => ['required', ...$this->textRules(180)],
            $prefix.'.faqs.items.*.answer' => $this->textRules(1000),
        ];
    }

    /** Reject accidental personal invitation bearer URLs in generic CMS content. */
    private function assertNoPersonalCredential(array $values, string $path): void
    {
        foreach ($values as $key => $value) {
            $field = $path.'.'.$key;
            if (is_array($value)) {
                $this->assertNoPersonalCredential($value, $field);

                continue;
            }
            if (is_string($value) && preg_match('/(?:\/rsvp\/|(?:token|credential)=)[a-f0-9]{64}(?:[\/?#&]|$)/i', $value)) {
                throw ValidationException::withMessages([$field => 'Personal RSVP links cannot be included in Guest Guide content.']);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function imageRules(string $prefix, bool $required): array
    {
        return [
            $prefix => [$required ? 'required' : 'sometimes', ...($required ? [] : ['nullable']), 'array:asset_id,alt,focal,crop,frame', 'required_array_keys:asset_id,alt,focal,crop'],
            $prefix.'.asset_id' => ['required_with:'.$prefix, 'string', 'uuid', 'lowercase'],
            $prefix.'.alt' => ['required_with:'.$prefix, 'string', 'max:500', 'not_regex:/[<>]/u'],
            $prefix.'.focal' => ['required_with:'.$prefix, 'string', Rule::in(self::FOCAL_PRESETS)],
            $prefix.'.crop' => ['required_with:'.$prefix, 'string', Rule::in(self::CROP_PRESETS)],
            $prefix.'.frame' => ['sometimes', 'string', Rule::in(['fit', 'fill'])],
        ];
    }

    /** @return list<string> */
    private function idRules(): array
    {
        return ['required', 'string', 'uuid', 'lowercase', 'distinct:strict'];
    }

    /** @return list<string> */
    private function titleRules(): array
    {
        return ['required', ...$this->textRules(self::MAX_TITLE_LENGTH)];
    }

    /** @return list<string> */
    private function textRules(int $max): array
    {
        return ['present', 'string', 'max:'.$max, 'not_regex:/[<>]/u'];
    }
}
