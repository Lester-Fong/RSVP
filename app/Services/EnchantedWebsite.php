<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;

/** Template adapter only. WeddingPublication owns the existing snapshot/pointer transaction. */
final class EnchantedWebsite
{
    public function __construct(private CmsV2Contract $contract) {}

    public static function enabled(): bool
    {
        return config('cms_editor.publishing_enabled') === true && config('cms_editor.preview_enabled') === true
            && app()->environment(['local', 'testing']);
    }

    public function document(WebsiteDraft|WebsiteRevision $source): CmsV2Document
    {
        if ($source->hero_photo_disk !== null || $source->hero_photo_path !== null) {
            throw ValidationException::withMessages(['publication' => 'This website contains an incompatible legacy photograph. Its source is preserved; resolve the template transition before publishing.']);
        }
        if ($source->schema_version !== 2 || $source->template_key !== 'enchanted-editorial' || $source->template_version !== 1) {
            throw ValidationException::withMessages(['publication' => 'This website template or version is not available for publication.']);
        }
        $document = CmsV2Document::fromArray(['schema_version' => 2, 'template_key' => $source->template_key,
            'template_version' => $source->template_version, 'content' => $source->content], $this->contract);
        $experience = $source->content['experience'];
        if ($experience['enabled'] && ($experience['intro_style'] !== 'photobooth' || $experience['intro_version'] !== 1)) {
            throw ValidationException::withMessages(['publication' => 'Introductions are not available for publication yet.']);
        }

        return $document;
    }

    /** @return array<string, string|null> */
    public function facts(Wedding $wedding, bool $strict = false): array
    {
        $facts = $wedding->presentationData();
        $zone = $wedding->timezone;
        $validZone = is_string($zone) && in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
        if ($strict && ! $validZone) {
            throw ValidationException::withMessages(['publication' => 'Choose a supported wedding timezone before publishing.']);
        }
        $instant = $validZone ? $this->instant($facts['wedding_date'], $facts['wedding_time'], $zone) : null;
        if ($strict && $facts['wedding_time'] !== null && $instant === null) {
            throw ValidationException::withMessages(['publication' => 'This wedding time is ambiguous or does not exist in its timezone. Choose an unambiguous local time before publishing.']);
        }

        return $facts + ['timezone' => $zone, 'wedding_instant_utc' => $instant];
    }

    private function instant(?string $date, ?string $time, string $zone): ?string
    {
        if ($date === null || $time === null) {
            return null;
        }
        $wall = $date.' '.$time;
        $naive = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $wall, new DateTimeZone('UTC'));
        if (! $naive || $naive->format('Y-m-d H:i') !== $wall) {
            return null;
        }
        $timezone = new DateTimeZone($zone);
        $seconds = $naive->getTimestamp();
        $offsets = array_unique(array_column($timezone->getTransitions($seconds - 172800, $seconds + 172800), 'offset'));
        $instants = [];
        foreach ($offsets as $offset) {
            $candidate = $naive->setTimestamp($seconds - $offset);
            if ($candidate->setTimezone($timezone)->format('Y-m-d H:i') === $wall) {
                $instants[] = $candidate->format('Y-m-d\TH:i:s\Z');
            }
        }

        return count($instants) === 1 ? $instants[0] : null;
    }

    /** Resolved visible display values only. Never returns the full source, private paths, guests or owner data.
     * @return array<string, mixed>
     */
    public function presentation(WebsiteRevision $revision, string $slug): array
    {
        $document = $this->document($revision);
        $content = $document->publicContent();
        $assets = $revision->mediaAssets()->where('wedding_media_assets.wedding_id', $revision->wedding_id)
            ->whereIn('wedding_media_assets.id', $document->assetIds(visible: true))->get()->keyBy('id');
        $photo = function (?array $reference) use ($assets, $slug): ?array {
            $asset = $reference ? $assets->get($reference['asset_id']) : null;
            if (! $asset) {
                return null;
            }

            return ['url' => route('public.weddings.media', ['slug' => $slug, 'asset' => $asset->id], false),
                'alt' => $reference['alt'], 'width' => $asset->width, 'height' => $asset->height, 'focalPoint' => 'center',
                'focal' => $reference['focal'], 'crop' => $reference['crop'],
                ...(isset($reference['frame']) ? ['frame' => $reference['frame']] : [])];
        };
        $sections = [];
        $welcome = '';
        $events = [];
        foreach ($content['sections'] as $section) {
            $data = $section['data'];
            $base = ['id' => $section['id'], 'enabled' => true, 'type' => $section['type']];
            if ($section['type'] === 'welcome') {
                $welcome = $data['message'];
            } elseif ($section['type'] === 'event_details') {
                $events = $data['events'];
                $sections[] = $base;
            } elseif ($section['type'] === 'countdown') {
                $sections[] = $base + ['eyebrow' => 'Our wedding day', 'heading' => $data['heading'], 'message' => $data['subtitle']];
            } elseif ($section['type'] === 'story') {
                $main = $content['photo_slots']['story'] ?? collect($data['milestones'] ?? [])->first(fn (array $milestone): bool => isset($milestone['photo']))['photo'] ?? null;
                $milestones = array_map(fn (array $milestone): array => ['id' => $milestone['id'], 'title' => $milestone['title'],
                    'description' => $milestone['description'] ?? '', 'when' => $milestone['when'] ?? null,
                    'photo' => $photo($milestone['photo'] ?? null)], $data['milestones'] ?? []);
                if ($main || count(array_filter($data['paragraphs'], fn (string $text): bool => trim($text) !== '')) || $milestones) {
                    $sections[] = $base + ['eyebrow' => 'A love like ours', 'heading' => $data['heading'], 'paragraphs' => $data['paragraphs'],
                        'photo' => $photo($main), 'insetPhoto' => null, 'milestones' => $milestones];
                }
            } elseif ($section['type'] === 'gallery' && $data['items']) {
                $sections[] = $base + ['eyebrow' => 'Moments to treasure', 'heading' => $data['heading'], 'message' => '',
                    'items' => array_map(fn (array $item): array => ['id' => $item['id'], 'photo' => $photo($item['image']), 'caption' => $item['caption'] ?? ''], $data['items']),
                    ...(isset($data['slideshow']) ? ['slideshow' => $data['slideshow']] : [])];
            } elseif ($section['type'] === 'entourage' && $data['groups']) {
                $sections[] = $base + ['eyebrow' => 'With grateful hearts', 'heading' => $data['heading'], 'message' => '', 'groups' => $data['groups']];
            } elseif ($section['type'] === 'guest_guide') {
                $sections[] = $base + array_filter([
                    'attire' => $data['attire'] ?? null,
                    'gifts' => $data['gifts'] ?? null,
                    'faqs' => $data['faqs'] ?? null,
                ], fn (?array $block): bool => $block !== null);
            } elseif ($section['type'] === 'rsvp_information') {
                $sections[] = $base + ['eyebrow' => 'RSVP', 'heading' => 'We would love to celebrate with you', 'message' => $data['message'], 'closing' => ''];
            } elseif ($section['type'] === 'closing') {
                $sections[] = $base + ['message' => $data['message']];
            }
        }

        $facts = array_intersect_key($revision->wedding_facts, array_flip(['partner_one_name', 'partner_two_name', 'wedding_date', 'wedding_time', 'ceremony_venue', 'reception_venue']));
        $kinds = array_column($events, 'kind');
        $hasVisibleCountdown = collect($content['sections'])->contains(fn (array $section): bool => $section['type'] === 'countdown');
        if (! in_array('ceremony', $kinds, true)) {
            $facts['ceremony_venue'] = null;
            if (! $hasVisibleCountdown) {
                $facts['wedding_time'] = null;
            }
        }
        if (! in_array('reception', $kinds, true)) {
            $facts['reception_venue'] = null;
        }

        return ['templateKey' => 'enchanted-editorial', 'templateVersion' => 1,
            ...($content['experience']['enabled'] ? ['opening' => ['style' => 'photobooth', 'version' => 1,
                'weddingKey' => $slug, 'heading' => $content['experience']['intro_settings']['opening_text'],
                'openLabel' => $content['experience']['intro_settings']['open_label']]] : []),
            'wedding' => $facts, 'timeZone' => $revision->wedding_facts['timezone'],
            'sections' => $sections,
            'hero' => ['eyebrow' => 'You are warmly invited', 'message' => $welcome, 'photo' => $photo($content['photo_slots']['hero'] ?? null), 'insetPhoto' => null],
            'details' => ['eyebrow' => 'Our wedding day', 'heading' => 'A celebration of love', 'message' => '',
                'photo' => $photo($content['photo_slots']['details'] ?? null), 'insetPhoto' => null, 'events' => $events]];
    }
}
