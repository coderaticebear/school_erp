{{--
    The school's crest: a shield with its initials (config/school.php). Decorative;
    the school's name is always written next to it.
--}}
@props(['height' => 34, 'fill' => '#1f3b73', 'ink' => '#ffffff'])

@php($initials = \App\Services\SchoolIdentity::initials())

<svg {{ $attributes->merge(['class' => 'school-crest']) }} width="{{ round($height * 72 / 84) }}" height="{{ $height }}" viewBox="0 0 72 84" aria-hidden="true" focusable="false">
    <path d="M36 2 L68 12 V40 C68 62 52 76 36 82 C20 76 4 62 4 40 V12 Z" fill="{{ $fill }}"></path>
    <text x="36" y="{{ strlen($initials) > 1 ? 52 : 54 }}" text-anchor="middle" font-family="'Source Serif 4', Georgia, serif" font-size="{{ strlen($initials) > 1 ? 28 : 34 }}" font-weight="600" fill="{{ $ink }}">{{ $initials }}</text>
</svg>
