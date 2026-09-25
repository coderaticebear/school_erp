<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Divisions;
use App\Models\Exam;
use App\Services\SchoolReports;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected SchoolReports $reports) {}

    /**
     * Attendance by division (or by student within one division) for a date range.
     */
    public function attendance(Request $request): View|StreamedResponse
    {
        $academicYear = AcademicYear::current();
        [$from, $to] = $this->dateRange($request);
        $divisions = Divisions::with('class')->get()->sortBy('label')->values();
        $division = $divisions->firstWhere('id', (int) $request->query('division'));

        $byDivision = $academicYear && ! $division ? $this->reports->attendanceByDivision($academicYear, $from, $to) : collect();
        $byStudent = $academicYear ? $this->reports->attendanceByStudent($academicYear, $from, $to, $division) : collect();

        if ($request->query('format') === 'csv') {
            return $this->csv('attendance-'.$from->toDateString().'-to-'.$to->toDateString().'.csv',
                ['Student', 'Division', 'Present', 'Late', 'Absent', 'Excused', 'Days', 'Percent'],
                $byStudent->map(fn ($row) => [
                    $row['student']->first_name.' '.$row['student']->last_name, $row['division']?->label,
                    $row['present'], $row['late'], $row['absent'], $row['excused'], $row['total'], $row['percent'],
                ]));
        }

        return view('admin.reports.attendance', compact('academicYear', 'from', 'to', 'divisions', 'division', 'byDivision', 'byStudent'));
    }

    /**
     * Pass rates and averages for one exam, by division and by subject.
     */
    public function exams(Request $request): View|StreamedResponse
    {
        $academicYear = AcademicYear::current();
        $exams = $academicYear
            ? Exam::query()->where('academic_year_id', $academicYear->id)->orderByDesc('starts_on')->orderByDesc('id')->get()
            : collect();
        $exam = $exams->firstWhere('id', (int) $request->query('exam')) ?? $exams->first();

        $byDivision = $exam ? $this->reports->examByDivision($exam) : collect();
        $bySubject = $exam ? $this->reports->examBySubject($exam) : collect();

        if ($exam && $request->query('format') === 'csv') {
            return $this->csv('exam-report-'.str($exam->name)->slug().'.csv',
                ['Division', 'Students', 'Complete', 'Passed', 'Pass rate', 'Average %', 'Top student'],
                $byDivision->map(fn ($row) => [
                    $row['division']->label, $row['students'], $row['complete'], $row['passed'], $row['pass_rate'], $row['average'],
                    $row['top'] ? $row['top']['student']->first_name.' '.$row['top']['student']->last_name : '',
                ]));
        }

        return view('admin.reports.exams', compact('academicYear', 'exams', 'exam', 'byDivision', 'bySubject'));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(Request $request): array
    {
        $parse = function (?string $value, Carbon $default): Carbon {
            try {
                return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : $default;
            } catch (\Throwable) {
                return $default;
            }
        };

        $from = $parse($request->query('from'), now()->startOfMonth());
        $to = $parse($request->query('to'), now()->startOfDay());

        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<mixed>>  $rows
     */
    protected function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
