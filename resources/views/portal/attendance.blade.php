@extends('adminlte::page')
@section('title', 'Attendance')

@section('content_header')
    <x-page-header title="Attendance" :subtitle="$student->first_name.' '.$student->last_name" />
@stop

@section('content')
    @include('portal.nav')

    @if (! $academicYear)
        <div class="alert alert-info">There is no active academic year.</div>
    @else
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="info-box"><span class="info-box-icon bg-success"><i class="fas fa-percent"></i></span>
                    <div class="info-box-content"><span class="info-box-text">Attendance</span><span class="info-box-number">{{ $summary['percent'] !== null ? $summary['percent'].'%' : '—' }}</span></div>
                </div>
            </div>
            @foreach (\App\Models\Attendance::STATUSES as $status => $label)
                @continue($status === 'present')
                <div class="col-md-3 col-6">
                    <div class="info-box"><span class="info-box-icon {{ str_replace('badge-', 'bg-', \App\Models\Attendance::badgeClass($status)) }}"><i class="fas fa-calendar-times"></i></span>
                        <div class="info-box-content"><span class="info-box-text">{{ $label }}</span><span class="info-box-number">{{ $summary[$status] }}</span></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header">
                <form method="get" class="form-inline">
                    <label class="mr-2" for="month">Month</label>
                    <select name="month" id="month" class="custom-select custom-select-sm" onchange="this.form.submit()">
                        <option value="">All of {{ $academicYear->year }}</option>
                        @foreach ($months as $value)
                            <option value="{{ $value }}" @selected($month === $value)>{{ $monthLabel($value) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th class="pl-3">Date</th><th>Status</th><th>Remark</th></tr></thead>
                        <tbody>
                            @forelse ($records as $record)
                                <tr>
                                    <td class="pl-3">{{ $record->date->format('D, M j, Y') }}</td>
                                    <td><span class="badge {{ \App\Models\Attendance::badgeClass($record->status) }}">{{ \App\Models\Attendance::STATUSES[$record->status] }}</span></td>
                                    <td>{{ $record->remark }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">No attendance recorded{{ $month ? ' this month' : '' }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@stop
