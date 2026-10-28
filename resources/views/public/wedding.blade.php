<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="referrer" content="no-referrer">
        <link rel="canonical" href="{{ $url }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $url }}">
        <meta name="twitter:card" content="{{ $photoUrl ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if ($photoUrl)
            <meta property="og:image" content="{{ $photoUrl }}">
            <meta property="og:image:alt" content="{{ $photoDescription }}">
            <meta name="twitter:image" content="{{ $photoUrl }}">
        @endif
        @vite(['resources/css/modern-classic.css', 'resources/js/public-invitation.ts', 'resources/css/public-invitation.css'])
    </head>
    <body>
        @if ($personal)<div id="personal-invitation">@endif
        @if ($greeting)
            <section class="invitation-greeting" aria-labelledby="invitation-group-greeting">
                <h2 id="invitation-group-greeting">You're invited, {{ $greeting['label'] }}!</h2>
                <ul aria-label="Named invitees">
                    @foreach ($greeting['guests'] as $name)
                        <li>{{ $name }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
        <main id="public-invitation">
            <div class="invitation-page">
                <article class="invitation-card" aria-labelledby="couple-names">
                    <div class="invitation-frame" aria-hidden="true"></div>
                    <header class="invitation-heading">
                        <p class="invitation-eyebrow">The wedding of</p>
                        <h1 id="couple-names" class="invitation-names">
                            <span>{{ $wedding['partner_one_name'] }}</span>
                            <span class="invitation-ampersand" aria-hidden="true">&amp;</span>
                            <span>{{ $wedding['partner_two_name'] }}</span>
                        </h1>
                        <div class="invitation-ornament" aria-hidden="true"><span></span>✦<span></span></div>
                        <p class="invitation-intro">{{ $customization['introduction_message'] }}</p>
                    </header>
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Wedding photo of {{ $wedding['partner_one_name'] }} and {{ $wedding['partner_two_name'] }}" class="invitation-hero-photo" decoding="async">
                    @endif
                    <section class="invitation-details" aria-labelledby="celebration-heading">
                        <h2 id="celebration-heading" class="invitation-section-title">The celebration</h2>
                        <div class="invitation-facts">
                            <p>{{ $formattedDate }}</p>
                            @if ($formattedTime)<p>{{ $formattedTime }}</p>@endif
                        </div>
                        @if (($customization['sections']['ceremony'] && $wedding['ceremony_venue']) || ($customization['sections']['reception'] && $wedding['reception_venue']))
                            <div class="invitation-venues">
                                @if ($customization['sections']['ceremony'] && $wedding['ceremony_venue'])
                                    <div class="invitation-venue"><h3>Ceremony</h3><p>{{ $wedding['ceremony_venue'] }}</p></div>
                                @endif
                                @if ($customization['sections']['reception'] && $wedding['reception_venue'])
                                    <div class="invitation-venue"><h3>Reception</h3><p>{{ $wedding['reception_venue'] }}</p></div>
                                @endif
                            </div>
                        @elseif ($customization['sections']['ceremony'] || $customization['sections']['reception'])
                            <p class="invitation-note">Venue details will be added soon.</p>
                        @endif
                    </section>
                    @if ($customization['sections']['closing'])
                        <footer class="invitation-footer">
                            <div class="invitation-ornament" aria-hidden="true"><span></span>✦<span></span></div>
                            <p>{{ $customization['closing_message'] }}</p>
                        </footer>
                    @endif
                </article>
            </div>
        </main>
        @if ($rsvp)
            <section id="invitation-rsvp" class="invitation-rsvp" aria-labelledby="rsvp-heading">
                <h2 id="rsvp-heading">Your RSVP</h2>
                <p>Respond for each named person. Unanswered people remain pending.</p>
                <ul>
                    @foreach ($rsvp['guests'] as $guest)
                        <li>{{ $guest['name'] }} — {{ $guest['status'] === 'pending' ? 'Pending — not answered' : ($guest['status'] === 'attending' ? 'Attending' : 'Declining') }}</li>
                    @endforeach
                </ul>
                <noscript>Enable JavaScript to save or update your responses. Your saved choices are shown above.</noscript>
            </section>
            <script id="{{ $personal ? 'personal-rsvp-data' : 'rsvp-data' }}" type="application/json">{!! \Illuminate\Support\Js::encode(['initial' => $rsvp, 'csrfUrl' => route('public.invitations.csrf', absolute: false)]) !!}</script>
        @endif
        <script id="invitation-data" type="application/json">{!! \Illuminate\Support\Js::encode($props) !!}</script>
        @if ($personal)</div>@endif
    </body>
</html>
