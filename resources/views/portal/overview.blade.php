@extends('adminlte::page')
@section('title', $student->first_name.' '.$student->last_name)

@section('content_header')
    @php($classTeacher = $enrolment?->division?->teachers->first())
    <x-page-header
        :title="$routePrefix === 'student.' ? 'Welcome, '.$student->first_name : $student->first_name.' '.$student->last_name"
        :subtitle="($enrolment?->division?->label ?? 'Not enrolled in a class this year').($classTeacher ? ' · Class teacher: '.$classTeacher->full_name : '')" />
@stop

@section('content')
    @include('portal.nav')

    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <p class="small-box-value">{{ $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</p>
                    <p>Attendance this year</p>
                </div>
                <div class="icon"><i class="fas fa-clipboard-check"></i></div>
                <a href="{{ route($routePrefix.'attendance', $routeParams) }}" class="small-box-footer">Details <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <p class="small-box-value">{{ $latestResult && $latestResult['row']['percent'] !== null ? number_format($latestResult['row']['percent'], 1).'%' : '—' }}</p>
                    <p>{{ $latestResult ? $latestResult['exam']->name.' · '.$latestResult['row']['result'] : 'No published results yet' }}</p>
                </div>
                <div class="icon"><i class="fas fa-poll"></i></div>
                <a href="{{ route($routePrefix.'results', $routeParams) }}" class="small-box-footer">Results <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <div class="col-lg-4 col-md-12">
            <div class="small-box bg-warning">
                <div class="inner">
                    <p class="small-box-value">{{ $attendance['absent'] }}</p>
                    <p>Days absent · {{ $attendance['late'] }} late</p>
                </div>
                <div class="icon"><i class="fas fa-user-clock"></i></div>
                <a href="{{ route($routePrefix.'attendance', $routeParams) }}" class="small-box-footer">History <i class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-calendar-day mr-2"></i>Today, {{ now()->format('l, M j') }}</h2></div>
                <div class="card-body p-0">
                    @if ($todaysLessons->isEmpty())
                        <p class="text-muted p-3 mb-0">No lessons to show today.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <tbody>
                                    @foreach ($todaysLessons as $lesson)
                                        <tr>
                                            <td class="text-nowrap text-muted">{{ $lesson->period->time_range }}</td>
                                            <td><strong>{{ $lesson->subject->subject_name }}</strong></td>
                                            <td>{{ $lesson->teacher->full_name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><h2 class="card-title"><i class="fas fa-user-clock mr-2"></i>Recent Absences and Late Arrivals</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentAbsences as $record)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $record->date->format('D, M j') }}{{ $record->remark ? ' · '.$record->remark : '' }}</span>
                            <span class="badge {{ \App\Models\Attendance::badgeClass($record->status) }}">{{ \App\Models\Attendance::STATUSES[$record->status] }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">None this year.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@stop
