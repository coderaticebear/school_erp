{{--
    Phone view of a weekly timetable: a day picker and one day's lessons as a list.
    Same data as timetable.grid ($grid, $mode); shown below 768px, the grid above.
--}}
@php($mode = $mode ?? 'division')
@php($schoolDays = array_keys($grid['days']))
@php($today = now()->isoWeekday())
@php($selectedDay = in_array($today, $schoolDays, true) ? $today : $schoolDays[0])
@php($uid = 'tt-'.substr(md5(json_encode(array_keys($grid['cells'])).$mode), 0, 6))

<div class="timetable-day-list d-md-none" data-timetable-days>
    <div class="timetable-day-tabs" role="tablist" aria-label="Day">
        @foreach ($grid['days'] as $day => $dayName)
            <button type="button" role="tab" id="{{ $uid }}-tab-{{ $day }}" aria-controls="{{ $uid }}-day-{{ $day }}"
                    aria-selected="{{ $day === $selectedDay ? 'true' : 'false' }}" tabindex="{{ $day === $selectedDay ? '0' : '-1' }}"
                    @class(['timetable-day-tab', 'active' => $day === $selectedDay])>
                {{ substr($dayName, 0, 3) }}
                @if ($day === $today)
                    <span class="timetable-day-today">Today</span>
                @endif
            </button>
        @endforeach
    </div>

    @foreach ($grid['days'] as $day => $dayName)
        <section role="tabpanel" id="{{ $uid }}-day-{{ $day }}" aria-labelledby="{{ $uid }}-tab-{{ $day }}" class="timetable-day-panel" @if ($day !== $selectedDay) hidden @endif>
            <h3 class="h5 mt-3 mb-2">{{ $dayName }}{{ $day === $today ? ' · Today' : '' }}</h3>
            <ol class="list-unstyled mb-0">
                @foreach ($grid['periods'] as $period)
                    @php($entry = $grid['cells'][$day][$period->id] ?? null)
                    <li class="timetable-day-row">
                        <span class="timetable-day-time">{{ substr($period->starts_at, 0, 5) }}<br>{{ substr($period->ends_at, 0, 5) }}</span>
                        @if ($period->is_break)
                            <span class="timetable-day-break">{{ $period->label }}</span>
                        @elseif ($entry)
                            <span class="timetable-day-lesson {{ \App\Services\TimetableGrid::colorFor($entry->subject_id) }}">
                                <span class="timetable-day-subject"><span class="subject-dot {{ \App\Services\TimetableGrid::dotFor($entry->subject_id) }}" aria-hidden="true"></span>{{ $entry->subject->subject_name }}</span>
                                <span class="timetable-day-meta">{{ $period->label }} · {{ $mode === 'teacher' ? $entry->division->label : $entry->teacher->full_name }}</span>
                            </span>
                        @else
                            <span class="timetable-day-free">{{ $period->label }} · Free</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>

@once
    @push('js')
        <script>
            // Day picker: switch panels, with arrow-key support between tabs.
            document.addEventListener('click', function (event) {
                const tab = event.target.closest('.timetable-day-tab');
                if (tab) { selectDay(tab); }
            });
            document.addEventListener('keydown', function (event) {
                const tab = event.target.closest && event.target.closest('.timetable-day-tab');
                if (! tab || ! ['ArrowLeft', 'ArrowRight'].includes(event.key)) { return; }
                const tabs = [...tab.parentElement.querySelectorAll('.timetable-day-tab')];
                const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                selectDay(next); next.focus();
            });
            function selectDay(tab) {
                const root = tab.closest('[data-timetable-days]');
                root.querySelectorAll('.timetable-day-tab').forEach(function (t) {
                    const on = t === tab;
                    t.classList.toggle('active', on);
                    t.setAttribute('aria-selected', on ? 'true' : 'false');
                    t.tabIndex = on ? 0 : -1;
                    document.getElementById(t.getAttribute('aria-controls')).hidden = ! on;
                });
            }
        </script>
    @endpush
@endonce
