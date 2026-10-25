<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title><meta name="description" content="{{ $description }}">
    <meta name="robots" content="noindex, nofollow, noarchive"><meta name="referrer" content="no-referrer">
    <link rel="canonical" href="{{ $url }}"><meta property="og:type" content="website"><meta property="og:title" content="{{ $title }}"><meta property="og:url" content="{{ $url }}">
    <script>
        document.documentElement.dataset.eeBoot = 'pending';
        function enchantedBootstrapError(event) {
            if (event.target === window || event.target?.tagName === 'SCRIPT') {
                delete document.documentElement.dataset.eeBoot;
                window.removeEventListener('error', enchantedBootstrapError, true);
            }
        }
        window.addEventListener('error', enchantedBootstrapError, true);
        @if (isset($presentation['opening']))
        setTimeout(() => {
            if (document.documentElement.dataset.eeBoot === 'pending') {
                document.documentElement.dataset.eeOpeningBypass = 'true';
                delete document.documentElement.dataset.eeBoot;
            }
        }, 2500);
        @endif
    </script>
    <style>
        .ee-bootstrap-loader{display:none}html[data-ee-boot=pending] .ee-server-content{visibility:hidden}
        html[data-ee-boot=pending] .ee-bootstrap-loader{display:grid;place-content:center;position:fixed;inset:0;background:#f9f5ed;z-index:30;text-align:center;color:#806b5b;font:13px Georgia,serif}
        .ee-bootstrap-loader svg{width:76px;height:76px;margin:0 auto}
        .ee-bootstrap-open{color:#925e46;padding:15px 25px;border:1px solid #b99e7e;text-decoration:none;margin-top:20px}
        @media(prefers-reduced-motion:no-preference){.ee-bootstrap-loader svg{animation:ee-bootstrap-hover 4300ms ease-in-out infinite}}
        @keyframes ee-bootstrap-hover{0%,100%{transform:translate(0,0) rotate(-.3deg)}23%{transform:translate(.7px,-1.8px) rotate(.4deg)}57%{transform:translate(-.9px,-.7px) rotate(-.5deg)}81%{transform:translate(.3px,1.1px) rotate(.15deg)}}
    </style>
    @vite('resources/js/public-enchanted.ts')
</head>
<body style="margin:0">
@php
    $facts = $presentation['wedding'];
    $date = null;
    if (is_string($facts['wedding_date'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $facts['wedding_date'])) {
        $parsedDate = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $facts['wedding_date']);
        $date = $parsedDate && $parsedDate->format('Y-m-d') === $facts['wedding_date'] ? $parsedDate : null;
    }
    $dateLabel = $date?->format('j F Y') ?? 'Date to be announced';
    $time = null;
    if (is_string($facts['wedding_time'] ?? null) && preg_match('/^\d{2}:\d{2}$/', $facts['wedding_time'])) {
        $parsedTime = \Carbon\CarbonImmutable::createFromFormat('!H:i', $facts['wedding_time']);
        $time = $parsedTime && $parsedTime->format('H:i') === $facts['wedding_time'] ? $parsedTime : null;
    }
    $anchors = ['story' => ['our-story', 'Our story'], 'event_details' => ['wedding-details', 'Wedding details'], 'countdown' => ['countdown', 'Countdown'], 'gallery' => ['our-photos', 'Photos'], 'entourage' => ['entourage', 'Entourage'], 'guest_guide' => ['guest-guide', 'Guest Guide'], 'rsvp_information' => ['rsvp', 'RSVP'], 'closing' => ['closing', 'With love']];
    $personalRsvpTarget = in_array('rsvp_information', array_column($presentation['sections'], 'type'), true) ? '#rsvp' : '#personal-rsvp';
@endphp
<div id="public-enchanted">
@if (isset($presentation['opening']))
<div class="ee-bootstrap-loader"><p>{{ $facts['partner_one_name'] }} &amp; {{ $facts['partner_two_name'] }}</p><a class="ee-bootstrap-open" href="#invitation" onclick="document.documentElement.dataset.eeOpeningBypass='true';delete document.documentElement.dataset.eeBoot">Open Invitation</a></div>
@else
<div class="ee-bootstrap-loader" role="status"><span aria-hidden="true">{!! file_get_contents(resource_path('assets/enchanted-editorial/hummingbird.svg')) !!}</span><p>Preparing your invitation…</p></div>
@endif
<div class="enchanted-editorial ee-server-content">
    <a class="ee-skip" href="#invitation">Skip to invitation</a>
    <header class="ee-header ee-container">
        <a href="#invitation" class="ee-identity" aria-label="Our wedding invitation"><span class="ee-monogram" aria-hidden="true">{{ mb_substr($facts['partner_one_name'], 0, 1) }}<span>&amp;</span>{{ mb_substr($facts['partner_two_name'], 0, 1) }}</span><span class="ee-identity-label">Together, always</span></a>
        <nav class="ee-navigation" aria-label="Wedding sections"><a href="#invitation">Invitation</a>@foreach ($presentation['sections'] as $section)@if ($section['type'] !== 'countdown')<a href="#{{ $anchors[$section['type']][0] }}">{{ $anchors[$section['type']][1] }}</a>@endif @endforeach
            @if ($personal && !in_array('rsvp_information', array_column($presentation['sections'], 'type'), true))<a href="#personal-rsvp">RSVP</a>@endif
        </nav>
        @if ($date)<time datetime="{{ $date->format('Y-m-d') }}" class="ee-header-date">{{ $dateLabel }}</time>@endif
    </header>
    <main aria-label="Wedding invitation">
        <section id="invitation" class="ee-hero ee-container" aria-labelledby="ee-couple-names" tabindex="-1">
            <div class="ee-hero-copy">
                @if ($personal)<p class="ee-personal-greeting" aria-label="Your personal invitation">You're invited, {{ $rsvp['greeting']['name'] }}!</p>@endif
                <p class="ee-eyebrow">{{ $presentation['hero']['eyebrow'] }}</p>
                <h1 id="ee-couple-names"><span>{{ $facts['partner_one_name'] }}</span><span class="ee-ampersand">&amp;</span><span>{{ $facts['partner_two_name'] }}</span></h1>
                <p class="ee-hero-message">{{ $presentation['hero']['message'] }}</p><p class="ee-wedding-date"><span class="ee-date-rule" aria-hidden="true"></span>@if ($date)<time datetime="{{ $date->format('Y-m-d') }}">{{ $dateLabel }}</time>@else<span>{{ $dateLabel }}</span>@endif</p>
                @if (in_array('event_details', array_column($presentation['sections'], 'type'), true))<a href="#wedding-details" class="ee-outline-link">The wedding details <span aria-hidden="true">↗</span></a>@endif
            </div>
            <div class="ee-hero-composition">@if ($presentation['hero']['photo'])<span class="ee-hero-backdrop" aria-hidden="true"></span>@endif
                @include('public.enchanted-botanical', ['class' => 'ee-hero-branch-top'])
                @include('public.enchanted-photo', ['photo' => $presentation['hero']['photo'], 'class' => 'ee-hero-main'])
                @include('public.enchanted-botanical', ['class' => 'ee-hero-branch-bottom'])
                @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-hero', 'landing' => 'invitation'])
                @if ($presentation['hero']['photo'])<p class="ee-composition-caption">The beginning of our forever</p>@endif
            </div>
        </section>
        @foreach ($presentation['sections'] as $section)
            @php($anchor = $anchors[$section['type']][0])
            @if ($section['type'] === 'event_details')
                <section id="wedding-details" class="ee-details ee-container" aria-labelledby="ee-details-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => $anchor])
                    <div class="ee-details-composition">
                        @include('public.enchanted-botanical', ['class' => 'ee-details-branch-top'])
                        @include('public.enchanted-photo', ['photo' => $presentation['details']['photo'], 'class' => 'ee-details-main'])
                        @include('public.enchanted-botanical', ['class' => 'ee-details-branch-bottom'])
                        <span class="ee-photo-note">A little moment, a lifetime of love.</span>
                    </div>
                    <div class="ee-details-copy"><p class="ee-eyebrow">{{ $presentation['details']['eyebrow'] }}</p><h2 id="ee-details-heading">{{ $presentation['details']['heading'] }}</h2><p class="ee-body">{{ $presentation['details']['message'] }}</p><p class="ee-details-date">{{ $dateLabel }} <span>· {{ $presentation['timeZone'] === 'Asia/Manila' ? 'Philippine time' : $presentation['timeZone'] }}</span></p>
                        <div class="ee-events">@foreach ($presentation['details']['events'] as $event)
                            <section class="ee-event" aria-label="{{ $event['kind'] }}"><div class="ee-event-label"><h3>{{ $event['kind'] === 'ceremony' ? 'The ceremony' : 'The reception' }}</h3><span>{{ $event['kind'] === 'ceremony' ? ($facts['wedding_time'] ? \Carbon\CarbonImmutable::createFromFormat('!H:i', $facts['wedding_time'])->format('g:i A') : 'Time to be announced') : 'Celebration to follow' }}</span></div>
                                <p class="ee-venue">{{ ($event['kind'] === 'ceremony' ? $facts['ceremony_venue'] : $facts['reception_venue']) ?: 'Venue to be announced' }}</p>
                                @if ($event['address'])<p class="ee-address">{{ $event['address'] }}</p>@endif
                                @if ($event['directions'])<p class="ee-directions">{{ $event['directions'] }}</p>@endif
                                @if ($event['map_url'])<a class="ee-map-link" href="{{ $event['map_url'] }}" target="_blank" rel="noopener noreferrer">View {{ $event['kind'] }} map <span aria-hidden="true">↗</span><span class="ee-sr-only"> (opens in a new tab)</span></a>@endif
                            </section>
                        @endforeach</div>
                    </div>
                </section>
            @elseif ($section['type'] === 'countdown')
                <section id="countdown" class="ee-countdown" aria-labelledby="ee-countdown-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => $anchor])
                    <div class="ee-container ee-countdown-inner">
                        @include('public.enchanted-botanical', ['class' => 'ee-countdown-branch'])
                        <p class="ee-eyebrow">Our wedding day</p><h2 id="ee-countdown-heading" class="ee-section-heading">{{ $section['heading'] }}</h2>
                        <p class="ee-countdown-message">{{ $section['message'] }}</p>
                        @if ($date)<p class="ee-countdown-date"><time datetime="{{ $date->format('Y-m-d') }}">{{ $dateLabel }}</time>
                            @if ($time)<span>{{ $time->format('g:i A') }} | {{ $presentation['timeZone'] === 'Asia/Manila' ? 'Philippine time' : $presentation['timeZone'] }}</span>@endif
                        </p>@endif
                        @if (!$date || !$time)<p class="ee-countdown-note">The wedding date and time will be confirmed soon.</p>@endif
                    </div>
                </section>
            @elseif ($section['type'] === 'story')
                <section id="our-story" class="ee-story-section" aria-labelledby="ee-story-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-story', 'landing' => $anchor])
                    <div class="ee-story ee-container {{ $section['photo'] ? '' : 'ee-story-text-only' }}"><div class="ee-story-copy"><p class="ee-eyebrow">{{ $section['eyebrow'] }}</p><h2 id="ee-story-heading" class="ee-section-heading">{{ $section['heading'] }}</h2><div class="ee-story-prose">@foreach ($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>@include('public.enchanted-ornament', ['class' => 'ee-story-plane', 'kind' => 'plane'])</div>
                        @if ($section['photo'])<div class="ee-story-composition">@include('public.enchanted-botanical', ['class' => 'ee-story-branch'])@include('public.enchanted-photo', ['photo' => $section['photo'], 'class' => 'ee-story-main'])</div>@endif
                    </div>
                    @if ($section['milestones'])<section class="ee-story-timeline ee-container" aria-labelledby="ee-timeline-heading"><p class="ee-eyebrow">One moment at a time</p><h3 id="ee-timeline-heading" class="ee-section-heading">The Moments That Led Us Here</h3><ol class="ee-timeline-list">
                        @foreach ($section['milestones'] as $milestone)<li class="ee-timeline-entry" data-milestone-id="{{ $milestone['id'] }}"><span class="ee-timeline-node" aria-hidden="true"></span>
                            @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-milestone', 'landing' => $anchor, 'landingMilestone' => $milestone['id']])
                            <div class="ee-timeline-content">@if ($milestone['when'])<time class="ee-eyebrow" datetime="{{ $milestone['when']['value'] }}">{{ $milestone['when']['precision'] === 'year' ? $milestone['when']['value'] : \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $milestone['when']['value'])->format('j F Y') }}</time>@endif<h4>{{ $milestone['title'] }}</h4>@if ($milestone['description'])<p class="ee-timeline-description">{{ $milestone['description'] }}</p>@endif
                                @if ($milestone['photo'])@include('public.enchanted-photo', ['photo' => $milestone['photo'], 'class' => 'ee-timeline-photo'])@endif
                            </div></li>@endforeach
                    </ol></section>@endif
                </section>
            @elseif ($section['type'] === 'gallery')
                <section id="our-photos" class="ee-gallery" aria-labelledby="ee-gallery-heading" tabindex="-1"><div class="ee-container">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-gallery', 'landing' => $anchor])
                    <header class="ee-gallery-header"><div><p class="ee-eyebrow">{{ $section['eyebrow'] }}</p><h2 id="ee-gallery-heading" class="ee-section-heading">{{ $section['heading'] }}</h2></div></header>
                    <div class="ee-gallery-composition">@include('public.enchanted-botanical', ['class' => 'ee-gallery-branch'])<div class="ee-gallery-carousel" role="group" aria-roledescription="carousel" aria-label="Prenup photographs"><p class="ee-gallery-hint">Scroll sideways to see our photographs.</p><div class="ee-gallery-stage" tabindex="0" aria-label="Photograph viewer">
                        @foreach ($section['items'] as $item)<figure class="ee-gallery-slide">@include('public.enchanted-photo', ['photo' => $item['photo'], 'class' => 'ee-gallery-photo', 'fit' => 'contain'])@if ($item['caption'])<figcaption>{{ $item['caption'] }}</figcaption>@endif</figure>@endforeach
                    </div></div></div>
                </div></section>
            @elseif ($section['type'] === 'entourage')
                <section id="entourage" class="ee-entourage ee-container" aria-labelledby="ee-entourage-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => $anchor])
                    <header class="ee-centered-heading"><p class="ee-eyebrow">{{ $section['eyebrow'] }}</p><h2 id="ee-entourage-heading" class="ee-section-heading">{{ $section['heading'] }}</h2><p class="ee-body">{{ $section['message'] }}</p></header><div class="ee-entourage-groups">
                        @foreach ($section['groups'] as $group)<section class="ee-entourage-group {{ count($group['people']) > 8 ? 'ee-entourage-wide' : '' }}" aria-label="{{ $group['title'] }}"><h3>{{ $group['title'] }}</h3><ul>@foreach ($group['people'] as $person)<li>{{ $person['name'] }}</li>@endforeach</ul></section>@endforeach
                    </div>
                </section>
            @elseif ($section['type'] === 'guest_guide')
                <section id="guest-guide" class="ee-guest-guide" aria-labelledby="ee-guest-guide-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => $anchor])
                    <div class="ee-container ee-guest-guide-inner"><p class="ee-eyebrow">A few thoughtful details</p><h2 id="ee-guest-guide-heading" class="ee-section-heading">For our guests</h2>
                        <div class="ee-guest-guide-grid">
                            @if (!empty($section['attire']))<section class="ee-guide-card ee-guide-attire" aria-labelledby="ee-attire-heading"><p class="ee-eyebrow">Attire</p><h3 id="ee-attire-heading">{{ $section['attire']['dress_code'] }}</h3>
                                @if ($section['attire']['description'])<p class="ee-guide-copy">{{ $section['attire']['description'] }}</p>@endif
                                @if ($section['attire']['swatches'])<ul class="ee-guide-swatches" aria-label="Suggested colors">@foreach ($section['attire']['swatches'] as $swatch)<li><span class="ee-guide-swatch" style="background-color: {{ $swatch['color'] }}" aria-hidden="true"></span><span>{{ $swatch['label'] }}</span></li>@endforeach</ul>@endif
                            </section>@endif
                            @if (!empty($section['gifts']))<section class="ee-guide-card ee-guide-gifts" aria-labelledby="ee-gifts-heading"><p class="ee-eyebrow">A note on gifts</p><h3 id="ee-gifts-heading">{{ $section['gifts']['heading'] }}</h3><p class="ee-guide-copy">{{ $section['gifts']['message'] }}</p>
                                @if ($section['gifts']['url'])<a class="ee-guide-link" href="{{ $section['gifts']['url'] }}" target="_blank" rel="noopener noreferrer">View gift details <span aria-hidden="true">â†—</span><span class="ee-sr-only"> (opens in a new tab)</span></a>@endif
                            </section>@endif
                            @if (!empty($section['faqs']))<section class="ee-guide-card ee-guide-faqs" aria-labelledby="ee-faqs-heading"><p class="ee-eyebrow">A few helpful notes</p><h3 id="ee-faqs-heading">Frequently asked questions</h3><div class="ee-guide-accordion">
                                @foreach ($section['faqs']['items'] as $item)<details class="ee-guide-faq"><summary>{{ $item['question'] }}</summary><div class="ee-guide-answer"><p>{{ $item['answer'] }}</p>
                                    @if (preg_match('/\\brsvp\\b/i', $item['question']))<p class="ee-guide-rsvp-note">@if ($personal)<a href="{{ $personalRsvpTarget }}">Open your personal RSVP</a>@else For RSVP details, please use the personal invitation link shared with you. Contact the couple if you need help.@endif</p>@endif
                                </div></details>@endforeach
                                @if (!$section['faqs']['items'])<p class="ee-guide-copy">More details will be shared soon.</p>@endif
                            </div></section>@endif
                        </div>
                    </div>
                </section>
            @elseif ($section['type'] === 'rsvp_information')
                <section id="rsvp" class="ee-rsvp" aria-labelledby="ee-rsvp-heading" tabindex="-1">
                    @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => $anchor])
                    <div class="ee-container ee-rsvp-inner">@include('public.enchanted-botanical', ['class' => 'ee-rsvp-branch'])<p class="ee-eyebrow">{{ $section['eyebrow'] }}</p><h2 id="ee-rsvp-heading" class="ee-section-heading">{{ $section['heading'] }}</h2>
                        @if (!$personal || $section['message'] !== 'Personal RSVP is not connected in this editor preview.')<p class="ee-rsvp-message">{{ $section['message'] }}</p>@endif
                        @if ($personal) @include('public.enchanted-rsvp')
                        @else <p class="ee-rsvp-information">Please follow the RSVP instructions provided by the couple.</p> @endif
                    </div>
                </section>
            @elseif ($section['type'] === 'closing')
                <section id="closing" class="ee-closing ee-container" aria-label="Closing message" tabindex="-1">@include('public.enchanted-ornament', ['class' => 'ee-landing-marker', 'landing' => $anchor])<span class="ee-eyebrow">With love and gratitude</span><p class="ee-rsvp-closing">{{ $section['message'] }}</p></section>
            @endif
        @endforeach
        @if ($personal && !in_array('rsvp_information', array_column($presentation['sections'], 'type'), true))
            <section id="personal-rsvp" class="ee-rsvp" aria-label="Your RSVP" tabindex="-1">
                @include('public.enchanted-ornament', ['class' => 'ee-landing-marker ee-landing-section', 'landing' => 'personal-rsvp'])
                <div class="ee-container ee-rsvp-inner">@include('public.enchanted-rsvp')</div>
            </section>
        @endif
    </main>
    <footer class="ee-footer ee-container">@include('public.enchanted-ornament', ['class' => 'ee-footer-rings', 'kind' => 'rings'])<p>With love, {{ $facts['partner_one_name'] }} &amp; {{ $facts['partner_two_name'] }}</p></footer>
</div></div>
<script id="enchanted-data" type="application/json">{!! \Illuminate\Support\Js::encode($presentation) !!}</script>
@if ($personal)<script id="personal-rsvp-data" type="application/json">{!! \Illuminate\Support\Js::encode(['initial' => $rsvp, 'csrfUrl' => route('public.invitations.csrf', absolute: false)]) !!}</script>@endif
</body></html>
