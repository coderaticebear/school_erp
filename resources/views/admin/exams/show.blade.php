@extends('adminlte::page')
@section('title', $exam->name)

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-0">{{ $exam->name }}</h1>
            <small class="text-muted">Out of {{ $exam->max_marks }} · pass mark {{ $exam->pass_marks }}{{ $exam->starts_on ? ' · starts '.$exam->starts_on->format('M j, Y') : '' }}</small>
        </div>
        <form action="{{ route('admin.exams.publish', $exam) }}" method="post" class="mt-2 mt-sm-0">
            @csrf
            @if ($exam->isPublished())
                <span class="badge badge-success mr-2">Published {{ $exam->results_published_at->diffForHumans() }}</span>
                <button type="submit" class="btn btn-outline-warning">Unpublish</button>
            @else
                <button type="submit" class="btn btn-success" onclick="return confirm('Publish results? Students and parents will see them, and marks will be locked.')"><i class="fas fa-upload mr-1"></i> Publish Results</button>
            @endif
        </form>
    </div>
@stop

@section('content')
    @include('partials.alerts')

    <div class="row">
        @foreach ($matrix as $row)
            <div class="col-md-6 col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ $row['division']->label }} <small class="text-muted">· {{ $row['students'] }} students</small></h3>
                        <div class="card-tools">
                            <a href="{{ route('admin.exams.results', [$exam, $row['division']]) }}" class="btn btn-tool" title="Results"><i class="fas fa-poll"></i> Results</a>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        @forelse ($row['subjects'] as $cell)
                            @php($done = $row['students'] > 0 && $cell['entered'] >= $row['students'])
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <a href="{{ route('marks.sheet', [$exam, $row['division'], $cell['subject']]) }}">{{ $cell['subject']->subject_name }}</a>
                                <span @class(['badge', 'badge-success' => $done, 'badge-warning' => ! $done && $cell['entered'] > 0, 'badge-light' => $cell['entered'] === 0])>{{ $cell['entered'] }}/{{ $row['students'] }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">No subjects — assign teachers to this division.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@stop
