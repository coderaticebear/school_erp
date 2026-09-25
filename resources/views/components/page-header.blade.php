{{--
    The one page header used by every screen: the page's <h1>, an optional subtitle,
    and the page's actions on the right (they wrap under the title on phones).

    <x-page-header title="Students" subtitle="2025-2026">
        <a href="..." class="btn btn-primary">Add Student</a>
    </x-page-header>
--}}
@props(['title', 'subtitle' => null])

<div class="page-header">
    <div class="page-header-text">
        <h1 class="mb-0">{{ $title }}</h1>
        @if (filled($subtitle))
            <p class="page-header-subtitle text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="page-header-actions no-print">
            {{ $slot }}
        </div>
    @endif
</div>
