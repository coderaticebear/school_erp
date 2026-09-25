@extends('adminlte::page')
@section('title', $division->label.' Students')

@section('content_header')
    <x-page-header :title="$division->label">
        <a href="{{ route('teacher.attendance', ['division' => $division->id]) }}" class="btn btn-primary"><i class="fas fa-clipboard-check mr-1" aria-hidden="true"></i> Take Attendance</a>
    </x-page-header>
@stop

@section('content')
    <p class="text-muted">
        Class teacher: {{ $division->teachers->first()?->full_name ?? 'not set' }}
        @if ($academicYear) · {{ $academicYear->year }} @endif
    </p>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Parent / Guardian</th>
                            <th>Parent Contact</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $index => $student)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                <td>{{ trim(($student->parent->first_name ?? '').' '.($student->parent->last_name ?? '')) ?: '—' }}</td>
                                <td>
                                    {{ $student->parent?->login?->email }}
                                    @if ($student->parent?->phone_number)
                                        <br><small class="text-muted">({{ $student->parent->area_code }}) {{ $student->parent->phone_number }}</small>
                                    @endif
                                </td>
                                <td>
                                    @isset($attendanceRates[$student->id])
                                        <span @class(['badge', 'badge-success' => $attendanceRates[$student->id] >= 90, 'badge-warning' => $attendanceRates[$student->id] < 90 && $attendanceRates[$student->id] >= 75, 'badge-danger' => $attendanceRates[$student->id] < 75])>{{ $attendanceRates[$student->id] }}%</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endisset
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">No students are enrolled in this division.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
