{{--
    A subject in its signature colour: <x-subject-chip :subject="$subject" />.
    Pass :plain="true" for just the coloured dot and name (inside tables and tight lists).
--}}
@props(['subject', 'plain' => false])

@if ($plain)
    <span {{ $attributes->merge(['class' => 'd-inline-flex align-items-center text-nowrap']) }} style="gap: .45rem">
        <span class="subject-dot {{ \App\Services\TimetableGrid::dotFor($subject->id) }}" aria-hidden="true"></span>{{ $subject->subject_name }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'subject-chip '.\App\Services\TimetableGrid::colorFor($subject->id)]) }}>
        <span class="subject-dot {{ \App\Services\TimetableGrid::dotFor($subject->id) }}" aria-hidden="true"></span>{{ $subject->subject_name }}
    </span>
@endif
