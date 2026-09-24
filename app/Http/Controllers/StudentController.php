<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicYear;
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
    public function index(): View
    {
        return view('student.dashboard');
    }

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
        $data = Students::with('parent')->get()->map(fn (Students $student) => [
            'sid' => $student->id,
            'sfname' => $student->first_name,
            'slname' => $student->last_name,
            'pfname' => $student->parent->first_name ?? '',
            'plname' => $student->parent->last_name ?? '',
        ])->toArray();

        return view('student.list')->with('data', $data);
    }
}
