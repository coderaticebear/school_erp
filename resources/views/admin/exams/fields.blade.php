<div class="form-group">
    <label for="name-{{ $exam?->id ?? 'new' }}">Name</label>
    <input id="name-{{ $exam?->id ?? 'new' }}" type="text" name="name" class="form-control" maxlength="100" placeholder="Term 1 Midterm" value="{{ $exam ? $exam->name : old('name') }}">
</div>
<div class="form-group">
    <label for="starts-on-{{ $exam?->id ?? 'new' }}">Start Date <small class="text-muted">(optional)</small></label>
    <input id="starts-on-{{ $exam?->id ?? 'new' }}" type="date" name="starts_on" class="form-control" value="{{ $exam ? $exam->starts_on?->toDateString() : old('starts_on') }}">
</div>
<div class="form-row">
    <div class="form-group col-6 mb-0">
        <label for="max-marks-{{ $exam?->id ?? 'new' }}">Maximum Marks</label>
        <input id="max-marks-{{ $exam?->id ?? 'new' }}" type="number" name="max_marks" class="form-control" min="1" max="1000" value="{{ $exam ? $exam->max_marks : old('max_marks', 100) }}">
    </div>
    <div class="form-group col-6 mb-0">
        <label for="pass-marks-{{ $exam?->id ?? 'new' }}">Pass Mark</label>
        <input id="pass-marks-{{ $exam?->id ?? 'new' }}" type="number" name="pass_marks" class="form-control" min="0" value="{{ $exam ? $exam->pass_marks : old('pass_marks', 40) }}">
    </div>
</div>
