{{-- Tabs for one student's pages; links stay inside the current portal (student or parent). --}}
@php($isParent = $routePrefix === 'parent.children.')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print">
    <ul class="nav nav-pills">
        @foreach (['dashboard' => 'Overview', 'timetable' => 'Timetable', 'attendance' => 'Attendance', 'results' => 'Results'] as $page => $label)
            <li class="nav-item">
                <a @class(['nav-link', 'active' => request()->routeIs($routePrefix.$page)]) href="{{ route($routePrefix.$page, $routeParams) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>
    @if ($isParent)
        <a href="{{ route('parent.dashboard') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> All Children</a>
    @endif
</div>
