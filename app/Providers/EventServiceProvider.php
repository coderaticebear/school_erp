<?php

namespace App\Providers;

use App\Models\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Sidebar items for a role: links (text, url, icon) and optional group headers.
     * Only link to pages that exist.
     *
     * @return list<array{text: string, url: string, icon: string}|array{header: string}>
     */
    public static function menuFor(int $role): array
    {
        return match ($role) {
            Login::ROLE_ADMIN => [
                ['text' => 'Dashboard', 'url' => 'admin/dashboard', 'icon' => 'fas fa-tachometer-alt'],
                ['header' => 'People'],
                ['text' => 'Students', 'url' => 'students', 'icon' => 'fas fa-user-graduate'],
                ['text' => 'Teachers', 'url' => 'teachers', 'icon' => 'fas fa-chalkboard-teacher'],
                ['header' => 'Academics'],
                ['text' => 'Classes & Divisions', 'url' => 'admin/classes', 'icon' => 'fas fa-school'],
                ['text' => 'Subjects', 'url' => 'subjects', 'icon' => 'fas fa-book'],
                ['text' => 'Academic Years', 'url' => 'admin/academic-years', 'icon' => 'fas fa-calendar'],
                ['header' => 'Timetable'],
                ['text' => 'Timetable', 'url' => 'admin/timetable', 'icon' => 'fas fa-calendar-alt'],
                ['text' => 'Bell Schedule', 'url' => 'admin/periods', 'icon' => 'fas fa-bell'],
                ['header' => 'Exams'],
                ['text' => 'Exams & Results', 'url' => 'admin/exams', 'icon' => 'fas fa-poll'],
                ['text' => 'Marks Entry', 'url' => 'marks', 'icon' => 'fas fa-pen'],
                ['header' => 'Reports'],
                ['text' => 'Attendance Report', 'url' => 'admin/reports/attendance', 'icon' => 'fas fa-chart-bar'],
                ['text' => 'Exam Report', 'url' => 'admin/reports/exams', 'icon' => 'fas fa-chart-line'],
            ],
            Login::ROLE_TEACHER => [
                ['text' => 'Dashboard', 'url' => 'teacher/dashboard', 'icon' => 'fas fa-tachometer-alt'],
                ['text' => 'My Timetable', 'url' => 'teacher/timetable', 'icon' => 'fas fa-calendar-alt'],
                ['text' => 'My Classes', 'url' => 'teacher/classes', 'icon' => 'fas fa-chalkboard'],
                ['text' => 'Attendance', 'url' => 'teacher/attendance', 'icon' => 'fas fa-clipboard-check'],
                ['text' => 'Marks Entry', 'url' => 'marks', 'icon' => 'fas fa-pen'],
            ],
            Login::ROLE_STUDENT => [
                ['text' => 'Dashboard', 'url' => 'student/dashboard', 'icon' => 'fas fa-tachometer-alt'],
                ['text' => 'My Timetable', 'url' => 'student/timetable', 'icon' => 'fas fa-calendar-alt'],
                ['text' => 'My Attendance', 'url' => 'student/attendance', 'icon' => 'fas fa-clipboard-check'],
                ['text' => 'My Results', 'url' => 'student/results', 'icon' => 'fas fa-poll'],
            ],
            Login::ROLE_PARENT => [
                ['text' => 'My Children', 'url' => 'parent/dashboard', 'icon' => 'fas fa-users'],
            ],
            default => [],
        };
    }

    public function boot(): void
    {
        Event::listen(BuildingMenu::class, function (BuildingMenu $event) {

            // If user is not logged in, skip menu building
            if (! Auth::check()) {
                return;
            }

            foreach (self::menuFor((int) Auth::user()->role) as $item) {
                $event->menu->add($item);
            }
        });
    }
}
