<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(BuildingMenu::class, function (BuildingMenu $event) {

            // If user is not logged in, skip menu building
            if (!Auth::check()) {
                return;
            }

            $role = Auth::user()->role;

            // Shared header
            $event->menu->add('MAIN NAVIGATION');

            // -------------------------
            // ROLE: ADMIN (1)
            // -------------------------
            if ($role == 1) {
                $event->menu->add([
                    'text' => 'Dashboard',
                    'url'  => 'admin/dashboard',
                    'icon' => 'fas fa-tachometer-alt',
                ]);

                $event->menu->add([
                    'text' => 'Manage Students',
                    'url'  => 'students',
                    'icon' => 'fas fa-user-graduate',
                ]);

                $event->menu->add([
                    'text' => 'Manage Teachers',
                    'url'  => 'teachers',
                    'icon' => 'fas fa-chalkboard-teacher',
                ]);

                $event->menu->add([
                    'text' => 'Subjects',
                    'url'  => 'subjects',
                    'icon' => 'fas fa-book',
                ]);
            }

            // -------------------------
            // ROLE: TEACHER (2)
            // -------------------------
            if ($role == 2) {
                $event->menu->add([
                    'text' => 'My Classes',
                    'url'  => 'teacher/classes',
                    'icon' => 'fas fa-chalkboard',
                ]);

                $event->menu->add([
                    'text' => 'Attendance',
                    'url'  => 'teacher/attendance',
                    'icon' => 'fas fa-clipboard-check',
                ]);
            }

            // -------------------------
            // ROLE: PARENT (3)
            // -------------------------
            if ($role == 3) {
                $event->menu->add([
                    'text' => 'My Children',
                    'url'  => 'parent/children',
                    'icon' => 'fas fa-users',
                ]);

                $event->menu->add([
                    'text' => 'Attendance Report',
                    'url'  => 'parent/attendance',
                    'icon' => 'fas fa-clipboard-list',
                ]);

                $event->menu->add([
                    'text' => 'Grades',
                    'url'  => 'parent/grades',
                    'icon' => 'fas fa-graduation-cap',
                ]);
            }
        });
    }
}
