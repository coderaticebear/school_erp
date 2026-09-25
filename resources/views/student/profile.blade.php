@extends('adminlte::page')

@php($fullName = $student->first_name.' '.$student->last_name)
@php($genders = ['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])

@section('title', $fullName)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-0">{{ $fullName }}</h1>
            <p class="text-muted mb-0">
                {{ $enrolment?->division?->label ?? 'Not assigned to a class' }}{{ $academicYear ? ' · '.$academicYear->year : '' }}
            </p>
        </div>
        <a href="{{ route('admin.students.edit', $student) }}" class="btn btn-primary mt-2 mt-sm-0">
            <i class="fas fa-pen mr-1" aria-hidden="true"></i> Edit Student
        </a>
    </div>
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Student ID</dt>
                        <dd>{{ $student->id }}</dd>

                        <dt>Status</dt>
                        <dd>{{ $student->login?->is_active ? 'Active' : 'Inactive' }}</dd>

                        <dt>Date of Birth</dt>
                        <dd>{{ $student->date_of_birth ? \Illuminate\Support\Carbon::parse($student->date_of_birth)->format('M j, Y') : 'Not provided' }}</dd>

                        <dt>Gender</dt>
                        <dd>{{ $genders[$student->gender] ?? 'Not provided' }}</dd>

                        <dt>Blood Group</dt>
                        <dd>{{ $student->blood_group ?: 'Not provided' }}</dd>

                        <dt>Address</dt>
                        <dd class="mb-0">
                            {{ $student->address_line_1 }}<br>
                            @if ($student->address_line_2)
                                {{ $student->address_line_2 }}<br>
                            @endif
                            {{ $student->city }}, {{ $student->province }} {{ $student->postal }}<br>
                            {{ $student->country }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Parent / Guardian</h2></div>
                <div class="card-body">
                    @if ($parent)
                        <dl class="mb-0">
                            <dt>Name</dt>
                            <dd>{{ $parent->first_name }} {{ $parent->last_name }}</dd>

                            <dt>Email</dt>
                            <dd>
                                @if ($parent->login?->email)
                                    <a href="mailto:{{ $parent->login->email }}">{{ $parent->login->email }}</a>
                                @else
                                    Not provided
                                @endif
                            </dd>

                            <dt>Phone</dt>
                            <dd class="mb-0">
                                @if ($parent->phone_number)
                                    <a href="tel:{{ preg_replace('/\D+/', '', $parent->area_code.$parent->phone_number) }}">({{ $parent->area_code }}) {{ $parent->phone_number }}</a>
                                @else
                                    Not provided
                                @endif
                            </dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">No parent or guardian is linked to this student.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Class</h2></div>
                <div class="card-body">
                    @if ($enrolment)
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Class</dt>
                            <dd class="col-sm-8">{{ $enrolment->division->class->class_name ?? '—' }}</dd>

                            <dt class="col-sm-4">Division</dt>
                            <dd class="col-sm-8">{{ $enrolment->division->division_name }}</dd>

                            <dt class="col-sm-4">Academic Year</dt>
                            <dd class="col-sm-8">{{ $academicYear->year }}</dd>

                            <dt class="col-sm-4">Class Teacher</dt>
                            <dd class="col-sm-8 mb-0">{{ $enrolment->division->teachers->first()?->full_name ?? 'Not assigned yet' }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">
                            Not assigned to a class{{ $academicYear ? ' for '.$academicYear->year : '' }}.
                            <a href="{{ route('admin.students.edit', $student) }}">Choose a class</a>.
                        </p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Attendance{{ $academicYear ? ' · '.$academicYear->year : '' }}</h2></div>
                <div class="card-body">
                    @if ($attendance['total'] === 0)
                        <p class="text-muted mb-0">No attendance has been recorded yet.</p>
                    @else
                        <p class="h4 mb-1">{{ $attendance['percent'] }}% attended</p>
                        <p class="text-muted">
                            {{ $attendance['present'] }} present · {{ $attendance['late'] }} late ·
                            {{ $attendance['absent'] }} absent · {{ $attendance['excused'] }} excused
                            ({{ $attendance['total'] }} {{ Str::plural('day', $attendance['total']) }} recorded)
                        </p>

                        @if ($recentAbsences->isNotEmpty())
                            <h3 class="h6 mt-3">Recent Absences and Late Arrivals</h3>
                            <ul class="list-unstyled mb-0">
                                @foreach ($recentAbsences as $record)
                                    <li>
                                        {{ $record->date->format('D, M j, Y') }} ·
                                        {{ \App\Models\Attendance::STATUSES[$record->status] }}{{ $record->remark ? ' · '.$record->remark : '' }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Report Cards</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($exams as $exam)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a href="{{ route('admin.exams.report-card', [$exam, $student]) }}">{{ $exam->name }}</a>
                            <span class="text-muted small">{{ $exam->isPublished() ? 'Published' : 'Draft' }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No exams yet this year.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@stop
