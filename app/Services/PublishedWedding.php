<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublishedWedding
{
    public function __construct(private EnchantedWebsite $enchanted, private WeddingImageAssetStorage $images) {}

    public function revision(Wedding $wedding, bool $personal = false): WebsiteRevision
    {
        $revision = $wedding->websiteRevisions()->whereKey($wedding->published_revision_id)->first();
        abort_unless($revision, 404);
        if ($revision->schema_version === 2) {
            abort_unless(EnchantedWebsite::enabled(), 404);
            try {
                $this->enchanted->document($revision);
            } catch (ValidationException) {
                abort(404);
            }
            abort_unless(isset($revision->wedding_facts['timezone']) && array_key_exists('wedding_instant_utc', $revision->wedding_facts), 404);
        } else {
            abort_unless($revision->schema_version === WebsiteDraft::SCHEMA_VERSION
                && ($revision->template_key ?? 'modern-classic') === 'modern-classic' && ($revision->template_version ?? 1) === 1, 404);
        }

        return $revision;
    }

    /** @param ?array{label: string, guests: array<int, string>} $greeting */
    public function render(Wedding $wedding, ?array $greeting = null, ?array $rsvp = null, bool $personal = false): Response
    {
        $revision = $this->revision($wedding, $personal);
        if ($revision->schema_version === 2) {
            abort_if($personal ? $greeting === null || $rsvp === null : $greeting !== null || $rsvp !== null, 404);
            $presentation = $this->enchanted->presentation($revision, $wedding->public_slug);

            return response()->view('public.enchanted', [
                'presentation' => $presentation,
                'personal' => $personal, 'rsvp' => $personal ? $rsvp : null,
                'title' => $personal ? 'Wedding invitation' : $presentation['wedding']['partner_one_name'].' & '.$presentation['wedding']['partner_two_name'].' — Wedding invitation',
                'description' => $personal ? 'You are invited to celebrate this wedding.' : $presentation['hero']['message'],
                'url' => $personal ? app(RsvpMode::class)->trustedUrl(route('public.weddings.show', ['slug' => $wedding->public_slug], absolute: false))
                    : route('public.weddings.show', ['slug' => $wedding->public_slug]),
            ], 200, InvitationLink::HEADERS);
        }
        $facts = $revision->wedding_facts;
        $customization = $revision->content;
        if (! $customization['sections']['ceremony']) {
            $facts['ceremony_venue'] = null;
        }
        if (! $customization['sections']['reception']) {
            $facts['reception_venue'] = null;
        }
        if (! $customization['sections']['closing']) {
            $customization['closing_message'] = '';
        }
        $url = route('public.weddings.show', ['slug' => $wedding->public_slug]);
        $photoUrl = $revision->hero_photo_path ? route('public.weddings.photo', ['slug' => $wedding->public_slug, 'photo' => pathinfo($revision->hero_photo_path, PATHINFO_FILENAME)]) : null;
        if ($personal) {
            $mode = app(RsvpMode::class);
            $url = $mode->trustedUrl(route('public.weddings.show', ['slug' => $wedding->public_slug], absolute: false));
            $photoUrl = $revision->hero_photo_path ? $mode->trustedUrl(route('public.weddings.photo', ['slug' => $wedding->public_slug, 'photo' => pathinfo($revision->hero_photo_path, PATHINFO_FILENAME)], absolute: false)) : null;
        }
        $props = ['wedding' => $facts, 'customization' => $customization, 'heroPhoto' => $photoUrl ? ['url' => $photoUrl] : null];

        return response()->view('public.wedding', [
            'props' => $props, 'wedding' => $facts, 'customization' => $customization, 'photoUrl' => $photoUrl, 'url' => $url,
            'greeting' => $greeting, 'personal' => $personal,
            'rsvp' => $rsvp,
            'title' => $greeting ? 'Wedding invitation' : $facts['partner_one_name'].' & '.$facts['partner_two_name'].' — Wedding invitation',
            'description' => $greeting ? 'You are invited to celebrate this wedding.' : Str::limit(preg_replace('/\s+/u', ' ', $revision->content['introduction_message']), 160),
            'photoDescription' => $greeting ? 'Wedding photo' : 'Wedding photo of '.$facts['partner_one_name'].' and '.$facts['partner_two_name'],
            'formattedDate' => CarbonImmutable::createFromFormat('!Y-m-d', $facts['wedding_date'])->locale('en')->isoFormat('dddd, MMMM D, YYYY'),
            'formattedTime' => $facts['wedding_time'] ? CarbonImmutable::createFromFormat('!H:i', $facts['wedding_time'])->format('g:i A') : null,
        ], 200, InvitationLink::HEADERS);
    }

    /** Current-visible delivery is independent of the complete retained revision-media bindings. */
    public function media(Wedding $wedding, string $assetId): Response
    {
        return $wedding->getConnection()->transaction(function () use ($wedding, $assetId): Response {
            $current = $wedding->newQuery()->whereKey($wedding->id)->sharedLock()->firstOrFail();
            $revision = $this->revision($current);
            abort_unless($revision->schema_version === 2 && in_array($assetId, $this->enchanted->document($revision)->assetIds(visible: true), true), 404);
            $asset = $revision->mediaAssets()->where('wedding_media_assets.wedding_id', $current->id)->where('wedding_media_assets.id', $assetId)->firstOrFail();
            try {
                $bytes = $this->images->readVerified($asset);
            } catch (Throwable) {
                abort(404);
            }

            return response($bytes, 200, [...InvitationLink::HEADERS, 'Content-Type' => 'image/webp',
                'Cross-Origin-Resource-Policy' => 'same-origin', 'Content-Disposition' => 'inline']);
        });
    }
}
