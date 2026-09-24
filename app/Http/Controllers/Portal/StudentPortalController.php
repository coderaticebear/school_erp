<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Students;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalController extends Controller
{
    use StudentRecords;

    public function dashboard(Request $request): View
    {
        return $this->studentHome($request->user()->studentProfile());
    }

    public function timetable(Request $request): View
    {
        return $this->studentTimetable($request->user()->studentProfile());
    }

    public function attendance(Request $request): View
    {
        return $this->studentAttendance($request->user()->studentProfile(), $request->query('month'));
    }

    public function results(Request $request): View
    {
        return $this->studentResults($request->user()->studentProfile());
    }

    public function reportCard(Request $request, Exam $exam): View
    {
        return $this->studentReportCard($request->user()->studentProfile(), $exam);
    }

    protected function portalRoutes(Students $student): array
    {
        return ['routePrefix' => 'student.', 'routeParams' => []];
    }
}
