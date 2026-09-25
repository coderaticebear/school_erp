<div class="form-group">
    <label>Name</label>
    <input type="text" name="name" class="form-control" maxlength="100" placeholder="Term 1 Midterm" value="{{ $exam ? $exam->name : old('name') }}">
</div>
<div class="form-group">
    <label>Start date <small class="text-muted">(optional)</small></label>
    <input type="date" name="starts_on" class="form-control" value="{{ $exam ? $exam->starts_on?->toDateString() : old('starts_on') }}">
</div>
<div class="form-row">
    <div class="form-group col-6 mb-0">
        <label>Maximum marks</label>
        <input type="number" name="max_marks" class="form-control" min="1" max="1000" value="{{ $exam ? $exam->max_marks : old('max_marks', 100) }}">
    </div>
    <div class="form-group col-6 mb-0">
        <label>Pass mark</label>
        <input type="number" name="pass_marks" class="form-control" min="0" value="{{ $exam ? $exam->pass_marks : old('pass_marks', 40) }}">
    </div>
</div>
