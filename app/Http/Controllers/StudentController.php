<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Login;
use App\Models\Parents;
use App\Models\StudentClass;
use App\Models\Students;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StudentController extends Controller
{
    /**
     * Create the student's login and profile, attach (or create) the parent,
     * and enrol the student in a division for the active academic year.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->withInput()->with('error', 'There is no active academic year. Activate one before adding students.');
        }

        $address = [
            'address_line_1' => $validated['address_line_1'],
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'],
            'province' => $validated['province'],
            'country' => $validated['country'],
            'postal' => $validated['postal'],
        ];

        try {
            DB::transaction(function () use ($validated, $address, $academicYear) {
                $parentId = $validated['parent_id'] ?? null;

                if (! $parentId) {
                    $parentLogin = Login::create([
                        'email' => $validated['p_email'],
                        'password' => bcrypt($validated['parent_password']),
                        'role' => Login::ROLE_PARENT,
                        'is_active' => true,
                    ]);

                    $parentId = Parents::create([
                        'login_id' => $parentLogin->id,
                        'first_name' => $validated['parent_first_name'],
                        'last_name' => $validated['parent_last_name'],
                        'area_code' => $validated['parent_area_code'],
                        'phone_number' => $validated['parent_phone'],
                        ...$address,
                    ])->id;
                }

                $login = Login::create([
                    'email' => $validated['email'],
                    'password' => bcrypt($validated['password']),
                    'role' => Login::ROLE_STUDENT,
                    'is_active' => true,
                ]);

                $student = Students::create([
                    'login_id' => $login->id,
                    'parent_id' => $parentId,
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'date_of_birth' => $validated['dob'],
                    'gender' => $validated['gender'],
                    'blood_group' => $validated['blood_group'],
                    ...$address,
                ]);

                StudentClass::create([
                    'student_id' => $student->id,
                    'class_division_id' => $validated['class_division_id'],
                    'academic_year_id' => $academicYear->id,
                    'is_active' => true,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to add student', ['exception' => $e]);

            return back()->withInput()->with('error', 'The student could not be saved. Please try again.');
        }

        return redirect()->route('admin.addStudent')->with('success', 'Student added successfully!');
    }

    public function getStudent(): View
    {
        $academicYear = AcademicYear::current();

        $students = Students::query()
            ->with([
                'parent',
                'login',
                'StudentClasses' => fn ($query) => $query
                    ->where('academic_year_id', $academicYear?->id)
                    ->with('division.class'),
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('student.list', compact('students'));
    }

    public function edit(Students $student): View
    {
        $academicYear = AcademicYear::current();
        $student->load('login');

        $currentDivisionId = $academicYear
            ? StudentClass::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $academicYear->id)
                ->value('class_division_id')
            : null;

        $divisions = Divisions::with('class')->get()
            ->sortBy(fn (Divisions $division) => [$division->class->class_name ?? '', $division->division_name])
            ->values();

        return view('student.edit', compact('student', 'divisions', 'currentDivisionId', 'academicYear'));
    }

    /**
     * Update the student's profile and login, and move them to a division for the active year.
     */
    public function update(UpdateStudentRequest $request, Students $student): RedirectResponse
    {
        $validated = $request->validated();
        $academicYear = AcademicYear::current();

        if (! $academicYear) {
            return back()->withInput()->with('error', 'There is no active academic year. Activate one before changing classes.');
        }

        DB::transaction(function () use ($validated, $student, $academicYear) {
            $student->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'date_of_birth' => $validated['dob'],
                'gender' => $validated['gender'],
                'blood_group' => $validated['blood_group'],
                'address_line_1' => $validated['address_line_1'],
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'],
                'province' => $validated['province'],
                'country' => $validated['country'],
                'postal' => $validated['postal'],
            ]);

            $student->login->email = $validated['email'];

            if (filled($validated['password'] ?? null)) {
                $student->login->password = bcrypt($validated['password']);
            }

            $student->login->save();

            StudentClass::updateOrCreate(
                ['student_id' => $student->id, 'academic_year_id' => $academicYear->id],
                ['class_division_id' => $validated['class_division_id'], 'is_active' => true],
            );
        });

        return redirect()->route('admin.students.show', $student)->with('success', 'Student updated.');
    }

    /**
     * Activate or deactivate the student's login.
     */
    public function toggleActive(Students $student): RedirectResponse
    {
        $student->login->update(['is_active' => ! $student->login->is_active]);

        $state = $student->login->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$student->first_name} {$student->last_name} {$state}.");
    }
}
