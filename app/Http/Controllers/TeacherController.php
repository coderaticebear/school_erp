<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\Login;
use App\Models\Subjects;
use App\Models\Teachers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(): View
    {
        return view('teacher.dashboard');
    }

    public function getTeacher(): View
    {
        $teachers = Teachers::query()
            ->with(['subjects', 'login', 'divisions.class'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('teacher.list', compact('teachers'));
    }

    public function viewTeacher(Teachers $teacher): JsonResponse
    {
        $teacher->load(['subjects', 'login', 'divisions.class']);

        return response()->json([
            'id' => $teacher->id,
            'fname' => $teacher->first_name,
            'lname' => $teacher->last_name,
            'email' => $teacher->login?->email,
            'active' => (bool) $teacher->login?->is_active,
            'subjects' => $teacher->subjects->pluck('subject_name')->values(),
            'divisions' => $teacher->divisions->map(fn ($division) => [
                'label' => $division->label,
                'class_teacher' => (bool) $division->pivot->class_teacher,
            ])->values(),
        ]);
    }

    public function create(): View
    {
        return view('teacher.form', [
            'teacher' => new Teachers,
            'subjects' => Subjects::query()->orderBy('subject_name')->get(),
        ]);
    }

    public function store(TeacherRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $login = Login::create([
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'role' => Login::ROLE_TEACHER,
                'is_active' => true,
            ]);

            $teacher = Teachers::create([...$this->profileFields($validated), 'login_id' => $login->id]);
            $teacher->subjects()->sync($validated['subject_ids']);
        });

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher added.');
    }

    public function edit(Teachers $teacher): View
    {
        $teacher->load(['subjects', 'login']);

        return view('teacher.form', [
            'teacher' => $teacher,
            'subjects' => Subjects::query()->orderBy('subject_name')->get(),
        ]);
    }

    public function update(TeacherRequest $request, Teachers $teacher): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $teacher) {
            $teacher->update($this->profileFields($validated));
            $teacher->subjects()->sync($validated['subject_ids']);

            $teacher->login->email = $validated['email'];

            if (filled($validated['password'] ?? null)) {
                $teacher->login->password = bcrypt($validated['password']);
            }

            $teacher->login->save();
        });

        return redirect()->route('admin.teachers.index')->with('success', 'Teacher updated.');
    }

    /**
     * Activate or deactivate the teacher's login.
     */
    public function toggleActive(Teachers $teacher): RedirectResponse
    {
        $teacher->login->update(['is_active' => ! $teacher->login->is_active]);

        $state = $teacher->login->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$teacher->full_name} {$state}.");
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function profileFields(array $validated): array
    {
        return collect($validated)->only([
            'first_name', 'last_name', 'address_line_1', 'address_line_2',
            'city', 'province', 'country', 'postal',
        ])->all();
    }
}
