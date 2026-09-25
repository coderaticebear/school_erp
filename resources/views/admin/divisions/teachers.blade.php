@extends('adminlte::page')

@section('title', 'Division Teachers')

@section('content_header')
    <x-page-header :title="'Teachers for '.$division->label" />
@stop

@section('content')
    @include('partials.alerts')

    @php
        $assignedIds = collect(old('teacher_ids', $division->teachers->pluck('id')->all()))->map(fn ($id) => (int) $id);
        $classTeacherId = (int) old('class_teacher_id', $division->teachers->firstWhere('pivot.class_teacher', true)?->id);
    @endphp

    <form action="{{ route('admin.divisions.teachers.update', $division) }}" method="post">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h2 class="card-title float-none">Assign Teachers</h2>
                <p class="text-muted small mb-0 mt-1">Tick the teachers who teach this division and choose one class teacher.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="pl-3" style="width: 90px">Teaches</th>
                                <th style="width: 120px">Class Teacher</th>
                                <th>Name</th>
                                <th>Subjects</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($teachers as $teacher)
                                <tr @class(['text-muted' => ! $teacher->login?->is_active])>
                                    <td class="pl-3">
                                        <label class="hit-target"><input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" class="teacher-toggle" aria-label="{{ $teacher->full_name }} teaches this division" @checked($assignedIds->contains($teacher->id))></label>
                                    </td>
                                    <td>
                                        <label class="hit-target"><input type="radio" name="class_teacher_id" value="{{ $teacher->id }}" class="class-teacher" aria-label="{{ $teacher->full_name }} is the class teacher" @checked($classTeacherId === $teacher->id)></label>
                                    </td>
                                    <td>
                                        {{ $teacher->full_name }}
                                        @unless ($teacher->login?->is_active)
                                            <span class="badge badge-secondary">Inactive</span>
                                        @endunless
                                    </td>
                                    <td>{{ $teacher->subjects->pluck('subject_name')->join(', ') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No teachers yet. <a href="{{ route('admin.teachers.create') }}">Add a teacher</a>.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('admin.classes.index') }}" class="btn btn-outline-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
@stop

@section('js')
    <script>
        $(function () {
            // A class teacher must also teach the division.
            $('.class-teacher').on('change', function () {
                $(this).closest('tr').find('.teacher-toggle').prop('checked', true);
            });
            $('.teacher-toggle').on('change', function () {
                if (! this.checked) {
                    $(this).closest('tr').find('.class-teacher').prop('checked', false);
                }
            });
        });
    </script>
@stop
