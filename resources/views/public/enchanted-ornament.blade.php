<svg viewBox="0 0 100 80" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="ee-ornament {{ $class }}"
    @isset($landing) data-landing-section="{{ $landing }}" data-landing-id="{{ isset($landingMilestone) ? 'milestone:'.$landingMilestone : 'section:'.$landing }}" @endisset
    @isset($landingMilestone) data-landing-milestone="{{ $landingMilestone }}" @endisset>
    @if (($kind ?? 'flower') === 'rings')
        <ellipse cx="39" cy="44" rx="22" ry="25" transform="rotate(-24 39 44)" /><ellipse cx="62" cy="44" rx="22" ry="25" transform="rotate(24 62 44)" />
        <path d="m31 18 4-8h9l5 8-10 6Zm4-8 4 14 5-14M69 18l-3-7h-8l-4 7" />
    @elseif (($kind ?? 'flower') === 'plane')
        <path d="m12 30 75-17-36 53-8-25Zm0 0 31 11 44-28M43 41l-1 16 9-9M8 61c15 5 24-3 22-11-2-6-12-4-11 3" />
    @else
        <path d="M49 73c-2-18 0-30 6-43M49 60c-12-3-19-12-19-21 12 1 20 9 19 21Zm1-9c12-1 21-10 23-19-12 0-22 8-23 19Z" />
        <path d="M56 26c-16-4-13-18-5-18 4-10 16-5 15 3 13-3 18 9 9 15 4 11-9 17-15 7-10 7-20-4-12-11" /><circle data-nectar-center cx="61" cy="22" r="4" />
    @endif
</svg>
