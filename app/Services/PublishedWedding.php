<?php

namespace App\Services;

use App\Models\WebsiteDraft;
use App\Models\WebsiteRevision;
use App\Models\Wedding;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PublishedWedding
{
    public function revision(Wedding $wedding): WebsiteRevision
    {
        $revision = $wedding->websiteRevisions()->whereKey($wedding->published_revision_id)->first();
        abort_unless($revision && $revision->schema_version === WebsiteDraft::SCHEMA_VERSION, 404);

        return $revision;
    }

    /** @param ?array{label: string, guests: array<int, string>} $greeting */
    public function render(Wedding $wedding, ?array $greeting = null, ?array $rsvp = null): Response
    {
        $revision = $this->revision($wedding);
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
        $props = ['wedding' => $facts, 'customization' => $customization, 'heroPhoto' => $photoUrl ? ['url' => $photoUrl] : null];

        return response()->view('public.wedding', [
            'props' => $props, 'wedding' => $facts, 'customization' => $customization, 'photoUrl' => $photoUrl, 'url' => $url,
            'greeting' => $greeting,
            'rsvp' => $rsvp,
            'title' => $greeting ? 'Wedding invitation' : $facts['partner_one_name'].' & '.$facts['partner_two_name'].' — Wedding invitation',
            'description' => $greeting ? 'You are invited to celebrate this wedding.' : Str::limit(preg_replace('/\s+/u', ' ', $revision->content['introduction_message']), 160),
            'photoDescription' => $greeting ? 'Wedding photo' : 'Wedding photo of '.$facts['partner_one_name'].' and '.$facts['partner_two_name'],
            'formattedDate' => CarbonImmutable::createFromFormat('!Y-m-d', $facts['wedding_date'])->locale('en')->isoFormat('dddd, MMMM D, YYYY'),
            'formattedTime' => $facts['wedding_time'] ? CarbonImmutable::createFromFormat('!H:i', $facts['wedding_time'])->format('g:i A') : null,
        ], 200, InvitationLink::HEADERS);
    }
}
