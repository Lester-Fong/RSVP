<div class="ee-personal-rsvp" aria-labelledby="ee-personal-rsvp-heading">
    <h2 id="ee-personal-rsvp-heading">Your RSVP</h2>
    <p>Respond for each named person. Unanswered people remain pending.</p>
    <ul>
        @foreach ($rsvp['guests'] as $guest)
            <li>{{ $guest['name'] }} — {{ $guest['status'] === 'pending' ? 'Pending — not answered' : ($guest['status'] === 'attending' ? 'Attending' : 'Declining') }}</li>
        @endforeach
    </ul>
    <p class="rsvp-note">Enable JavaScript to save or update your responses. Your saved choices are shown above.</p>
</div>
