@php
    $cropped = $photo && isset($photo['crop']) && !(isset($photo['frame']) && $photo['crop'] === 'natural');
    $ratio = $photo ? ['natural' => $photo['width'] / max(1, $photo['height']), 'portrait' => 3 / 4, 'landscape' => 4 / 3, 'square' => 1][$photo['crop'] ?? 'natural'] : 1;
    $innerStyle = $cropped ? 'width:min(100cqw,calc(100cqh * '.$ratio.'));height:min(100cqh,calc(100cqw / '.$ratio.'));position:absolute;left:50%;top:50%;transform:translate(-50%,-50%)' : 'width:100%;height:100%';
@endphp
@if ($photo)
<div class="ee-photo {{ $class }}" style="container-type:size">
    @if ($photo)
        <div style="{{ $innerStyle }}">
            <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" loading="lazy" decoding="async"
                style="object-fit:{{ ($photo['frame'] ?? null) === 'fit' ? 'contain' : ((isset($photo['frame']) || isset($photo['crop'])) ? 'cover' : ($fit ?? 'cover')) }};object-position:{{ ['center' => '50% 50%', 'top' => '50% 0%', 'bottom' => '50% 100%', 'left' => '0% 50%', 'right' => '100% 50%'][$photo['focal']] }}">
        </div>
    @else
        <div class="ee-photo-fallback" role="img" aria-label="Couple photograph unavailable"><span aria-hidden="true">✧</span><span>A moment to remember</span></div>
    @endif
</div>
@endif
