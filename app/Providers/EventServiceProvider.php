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
     * Sidebar items for a role. Only link to pages that exist.
     *
     * @return list<array{text: string, url: string, icon: string}>
     */
    public static function menuFor(int $role): array
    {
        return match ($role) {
            Login::ROLE_ADMIN => [
                ['text' => 'Dashboard', 'url' => 'admin/dashboard', 'icon' => 'fas fa-tachometer-alt'],
                ['text' => 'Manage Students', 'url' => 'students', 'icon' => 'fas fa-user-graduate'],
                ['text' => 'Manage Teachers', 'url' => 'teachers', 'icon' => 'fas fa-chalkboard-teacher'],
                ['text' => 'Manage Subjects', 'url' => 'subjects', 'icon' => 'fas fa-book'],
                ['text' => 'Classes & Divisions', 'url' => 'admin/classes', 'icon' => 'fas fa-school'],
                ['text' => 'Academic Years', 'url' => 'admin/academic-years', 'icon' => 'fas fa-calendar'],
                ['text' => 'Timetable Manager', 'url' => 'admin/timetable', 'icon' => 'fas fa-calendar-alt'],
                ['text' => 'Bell Schedule', 'url' => 'admin/periods', 'icon' => 'fas fa-bell'],
            ],
            Login::ROLE_TEACHER => [
                ['text' => 'Dashboard', 'url' => 'teacher/dashboard', 'icon' => 'fas fa-tachometer-alt'],
                ['text' => 'My Timetable', 'url' => 'teacher/timetable', 'icon' => 'fas fa-calendar-alt'],
                ['text' => 'My Classes', 'url' => 'teacher/classes', 'icon' => 'fas fa-chalkboard'],
                ['text' => 'Attendance', 'url' => 'teacher/attendance', 'icon' => 'fas fa-clipboard-check'],
            ],
            Login::ROLE_STUDENT => [
                ['text' => 'Dashboard', 'url' => 'student/dashboard', 'icon' => 'fas fa-tachometer-alt'],
            ],
            Login::ROLE_PARENT => [
                ['text' => 'Dashboard', 'url' => 'parent/dashboard', 'icon' => 'fas fa-tachometer-alt'],
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

            // Shared header
            $event->menu->add('MAIN NAVIGATION');

            foreach (self::menuFor((int) Auth::user()->role) as $item) {
                $event->menu->add($item);
            }
        });
    }
}
