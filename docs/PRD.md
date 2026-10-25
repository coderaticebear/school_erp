# School ERP: Product Requirements Document (reverse PRD)

Version 1, 2026-10-23. This document describes what the code does today. It also lists planned items.

Status values:
- **Built**: the code exists in the repository. A code path is given.
- **Planned**: the code does not exist. The item needs its own PRD before work starts.

Terms:
- **Class**: a grade (for example, Grade 5).
- **Division**: a section of a class (for example, 5-A).
- **Admin**, **teacher**, **student**, **parent**: the four roles.

## 1. Product summary and purpose

School ERP is a web application that runs the daily work of a school. It covers people, classes, timetables, attendance, exams and report cards.

The product is for many schools. Each school gets its own install. The product does not share one database between schools.

- Target school size: 300 to 1500 students.
- Target region: Canada. PIPEDA and provincial privacy rules apply.
- Languages: English now. French is planned.
- Target buyer: not chosen (see section 10).

Technology (from `CLAUDE.md` and `README.md`):
- Laravel 12, PHP 8.2 or later, PostgreSQL 18.
- Server-rendered Blade pages with AdminLTE 3 and Bootstrap.
- Tests use Pest 4. The app runs in Docker (Laravel Sail).

## 2. Goals and non-goals

### Goals
- G1: An admin sets up a school year, classes, divisions, subjects and people.
- G2: The system builds a timetable with no teacher clash and no division clash.
- G3: A teacher marks attendance and enters marks for the teacher's own divisions and subjects.
- G4: A student or parent sees the timetable, attendance and published results.
- G5: The product protects student data (see section 7).
- G6: One install serves one school with 300 to 1500 students.

### Non-goals
- NG1: Transport management.
- NG2: Staff leave and payroll.
- NG3: A multi-tenant SaaS (many schools in one database).

## 3. Users and roles

### 3.1 Built: four fixed roles

The role is stored in `login.role` (`app/Models/Login.php`). Routes use the `role:N` middleware (`routes/web.php`, `app/Http/Middleware/RoleMiddleware.php`).

| Role | Value | Can do (from `routes/web.php`) |
|---|---|---|
| Admin | 1 | Manage students, teachers, subjects, academic years, classes, divisions and division teachers. Manage the bell schedule and the timetable. Manage exams and publish results. Enter marks for any division and subject. View the attendance and exam reports. Export CSV files. View report cards. |
| Teacher | 2 | View own dashboard, timetable and divisions with rosters. Mark attendance for assigned divisions. Enter marks for assigned divisions and subjects. |
| Student | 3 | View own dashboard, timetable, attendance, published results and report cards. |
| Parent | 4 | View the same pages as a student, for each own child. |

Access rules (`app/Providers/AppServiceProvider.php`):
- Gate `teach-division`: an admin, or a teacher assigned to the division.
- Gate `enter-marks`: an admin, or a teacher assigned to the division who teaches the subject.
- Gate `view-student`: an admin, the student, or the parent of the student.

Notes:
- A login has one role. Only an active login can sign in.
- An admin creates all accounts. Public registration is off (`Auth::routes(['register' => false])`).

### 3.2 Planned: custom roles and per-section permissions

| ID | Requirement | Status |
|---|---|---|
| FR-ROLE-01 | An admin can create custom roles. | Planned |
| FR-ROLE-02 | An admin sets a permission of Hidden, Read or Write for each role on each page. | Planned |
| FR-ROLE-03 | An admin sets the same permission for each section of a page. Example: one role sees the contact section of a student profile. An Accountant role sees only the fee section. | Planned |
| FR-ROLE-04 | The four fixed roles become default roles. | Planned |
| FR-ROLE-05 | One person can hold many roles. The permissions of all roles add together. | Planned |
| FR-ROLE-06 | The design allows new permission types later. | Planned |

Needs its own PRD: yes.

## 4. Built features by area

All items have Status: Built. Paths are relative to the project root.

### 4.1 Authentication and accounts

| ID | Requirement | Code path |
|---|---|---|
| FR-AUTH-01 | A user signs in with email and password. | `app/Http/Controllers/Auth/LoginController.php` |
| FR-AUTH-02 | The system blocks sign-in for an account that is not active. | `app/Http/Controllers/Auth/LoginController.php`, `app/Models/Login.php` |
| FR-AUTH-03 | The route `/dashboard` sends each user to the dashboard for the user's role. A role that is not known gets 403. | `routes/web.php` |
| FR-AUTH-04 | The system returns 403 when a user opens a page for another role. | `app/Http/Middleware/RoleMiddleware.php` |
| FR-AUTH-05 | A user asks for a password-reset email. The reply is the same for known, unknown, inactive and throttled emails. | `app/Http/Controllers/Auth/ForgotPasswordController.php`, `lang/en/passwords.php` |
| FR-AUTH-06 | The reset form accepts only a valid token for an active account. | `app/Http/Controllers/Auth/ResetPasswordController.php` |
| FR-AUTH-07 | The system limits reset-link requests to 5 a minute and 20 an hour for each IP address. | `app/Providers/AppServiceProvider.php` (limiter `password-reset`) |
| FR-AUTH-08 | Every password has at least 12 characters and is not in a known data breach. | `app/Providers/AppServiceProvider.php` (`Password::defaults`) |
| FR-AUTH-09 | An admin activates or deactivates a student or teacher account on the Edit page. | `app/Http/Controllers/StudentController.php`, `app/Http/Controllers/TeacherController.php` (`toggleActive`), `resources/views/partials/account-status.blade.php` |
| FR-AUTH-10 | A command creates an admin with a generated password. The password shows once. | `app/Console/Commands/CreateAdmin.php` |
| FR-AUTH-11 | Logout works only with POST. | `config/adminlte.php`, `CLAUDE.md` |
| FR-AUTH-12 | After a correct password, every user must enter a 6-digit authenticator code or a one-time recovery code. The secret is encrypted and the recovery codes are hashed. A user with no 2FA can open only the set-up page. | `app/Http/Middleware/EnsureTwoFactor.php`, `app/Http/Controllers/TwoFactorController.php`, `app/Services/TwoFactorAuth.php`, `config/two-factor.php` (SEC-06) |
| FR-AUTH-13 | A user can see 2FA status and make new recovery codes on the Security page. The dashboard warns when fewer than 3 codes are left. | `app/Http/Controllers/TwoFactorController.php`, `resources/views/two-factor/`, `resources/views/partials/dashboard-notices.blade.php` |
| FR-AUTH-14 | An admin resets the 2FA of a student, parent or teacher, and a command does it by email. A reset ends all sessions of that account. | `app/Http/Controllers/Admin/TwoFactorResetController.php`, `app/Console/Commands/ResetTwoFactor.php` |
| FR-AUTH-15 | An admin must enter the password again (valid 15 minutes) before account deactivation, CSV export and setting a password for another person. | `routes/web.php`, `app/Http/Middleware/ConfirmPasswordForCsv.php`, `app/Http/Requests/UpdateStudentRequest.php`, `app/Http/Requests/TeacherRequest.php` |

### 4.2 Students, parents and teachers

| ID | Requirement | Code path |
|---|---|---|
| FR-STU-01 | An admin lists students. The list has search and sort. | `app/Http/Controllers/StudentController.php` (`getStudent`), `resources/views/student/list.blade.php` |
| FR-STU-02 | An admin adds a student with name, address, date of birth, gender and blood group. The student gets a login. | `StudentController::store`, `app/Http/Requests/StoreStudentRequest.php` |
| FR-STU-03 | An admin enrols the student in a division for an academic year when the admin adds the student. | `StudentController::store`, `app/Models/StudentClass.php` |
| FR-STU-04 | An admin links a student to a parent. The admin finds an existing parent by email or creates a new parent account. | `AdminController::getParentByEmail`, `StudentController::store` |
| FR-STU-05 | An admin edits a student. | `StudentController::edit`, `update`, `app/Http/Requests/UpdateStudentRequest.php` |
| FR-STU-06 | An admin opens a student profile. | `AdminController::viewStudent`, `resources/views/student/profile.blade.php` |
| FR-TCH-01 | An admin lists, adds, edits and views teachers. | `app/Http/Controllers/TeacherController.php`, `app/Http/Requests/TeacherRequest.php` |
| FR-TCH-02 | An admin assigns subjects to a teacher (many-to-many). | `app/Models/Teachers.php`, table `subject_teacher` |
| FR-PAR-01 | A parent record holds name, address, area code and phone number. | `app/Models/Parents.php` |
| FR-PAR-02 | A parent sees every child linked to the parent. | `app/Http/Controllers/Portal/ParentPortalController.php` |
| FR-PEO-01 | The system cleans all text input before it validates the input. Password fields are not changed. | `app/Pipelines/SanitizeInput.php`, `app/Http/Requests/` |
| FR-PEO-02 | A failed form does not keep password fields in the session. | `bootstrap/app.php` (`dontFlash`), `StudentController::PASSWORD_FIELDS` |

### 4.3 Subjects

| ID | Requirement | Code path |
|---|---|---|
| FR-SUB-01 | An admin adds, edits, lists and deletes subjects. | `app/Http/Controllers/SubjectController.php`, `app/Http/Requests/SubjectRequest.php` |
| FR-SUB-02 | A subject name is unique without regard to case. | `app/Rules/UniqueCaseInsensitive.php`, database index on `lower(subject_name)` |
| FR-SUB-03 | An admin sets an optional number of periods per week for a subject. | `app/Models/Subjects.php` (`periods_per_week`) |
| FR-SUB-04 | Each subject shows in its own colour. | `resources/views/components/subject-chip.blade.php`, `app/Services/TimetableGrid.php` |

### 4.4 Classes and divisions

| ID | Requirement | Code path |
|---|---|---|
| FR-CLS-01 | An admin adds, edits and deletes classes. | `app/Http/Controllers/Admin/ClassController.php`, `app/Http/Requests/ClassRequest.php` |
| FR-CLS-02 | An admin adds, edits and deletes divisions in a class. | `ClassController::storeDivision`, `updateDivision`, `destroyDivision`, `app/Http/Requests/DivisionRequest.php` |
| FR-CLS-03 | An admin assigns teachers to a division. | `app/Http/Controllers/Admin/DivisionTeacherController.php`, table `teacher_division` |
| FR-CLS-04 | A division has at most one class teacher. A partial unique index enforces this rule. | `database/migrations/2026_01_09_040247_create_teacher_division_table.php`, `DivisionTeacherController` |
| FR-CLS-05 | Class and division names are unique without regard to case. | `app/Rules/UniqueCaseInsensitive.php`, database indexes |

### 4.5 Academic years

| ID | Requirement | Code path |
|---|---|---|
| FR-YR-01 | An admin adds, edits and deletes academic years. | `app/Http/Controllers/Admin/AcademicYearController.php`, `app/Http/Requests/AcademicYearRequest.php` |
| FR-YR-02 | An admin activates one academic year. Only one year is active at a time. A database index enforces this rule. | `AcademicYearController::activate`, `app/Models/AcademicYear.php` |
| FR-YR-03 | A student has one enrolment for each academic year. A database index enforces this rule. | `app/Models/StudentClass.php`, table `student_classes` |

### 4.6 Timetable

| ID | Requirement | Code path |
|---|---|---|
| FR-TT-01 | An admin manages the bell schedule: periods with label, start time, end time and a break flag. | `app/Http/Controllers/Admin/PeriodController.php`, `app/Http/Requests/PeriodRequest.php`, `app/Models/Period.php` |
| FR-TT-02 | The grid shows break periods. The system never gives a lesson to a break period. | `app/Services/TimetableGrid.php`, `app/Services/TimetableGenerator.php` |
| FR-TT-03 | The school days come from the config value `SCHOOL_DAYS` (ISO day numbers). The default is 1 to 6 (Monday to Saturday). | `config/school.php` |
| FR-TT-04 | An admin generates a timetable for chosen divisions. The generator uses only active teachers assigned to the division. | `TimeTableController::generate`, `app/Services/TimetableGenerator.php`, `app/Http/Requests/GenerateTimetableRequest.php` |
| FR-TT-05 | The generator keeps one teacher for each subject in each division. | `app/Services/TimetableGenerator.php` |
| FR-TT-06 | The generator limits the lessons of one subject per day. The default limit is 2 (`TIMETABLE_MAX_SUBJECT_PER_DAY`). | `config/school.php`, `TimetableGenerator` |
| FR-TT-07 | The generator uses `subjects.periods_per_week` as the weekly target. A subject with no value shares the week evenly. | `TimetableGenerator::targetsFor` |
| FR-TT-08 | The generator fills all divisions together. It then repairs gaps by swapping lessons. | `TimetableGenerator::generate` |
| FR-TT-09 | The database rejects a double-booked division and a double-booked teacher. | `database/migrations/2026_09_24_144448_create_timetable_tables.php` (two unique indexes) |
| FR-TT-10 | An admin edits or clears one slot. The teacher must be active, assigned to the division, teach the subject and be free. | `TimeTableController::saveEntry`, `clearEntry`, `app/Http/Requests/TimetableEntryRequest.php`, `ClearTimetableEntryRequest.php` |
| FR-TT-11 | An admin publishes the timetable. Teachers, students and parents see it only after publication (`timetable_published_at`). | `TimeTableController::publish`, `app/Models/AcademicYear.php` |
| FR-TT-12 | An admin exports a timetable as a CSV file. | `TimeTableController::export`, `app/Services/CsvExport.php` |
| FR-TT-13 | An admin prints a timetable with a print style sheet. | `resources/views/timetable/styles.blade.php`, `public/js/school.js` (`data-print`) |
| FR-TT-14 | A teacher sees a personal timetable. | `Teacher\PortalController::timetable`, `TimetableGrid` |
| FR-TT-15 | The phone layout shows a day picker instead of the grid. | `resources/views/timetable/day-list.blade.php` |

### 4.7 Attendance

| ID | Requirement | Code path |
|---|---|---|
| FR-ATT-01 | A teacher marks attendance for a division and a date. The statuses are Present, Absent, Late and Excused. | `app/Http/Controllers/Teacher/AttendanceController.php`, `app/Models/Attendance.php`, `app/Http/Requests/SaveAttendanceRequest.php` |
| FR-ATT-02 | A teacher can mark only the divisions assigned to the teacher. | Gate `teach-division` in `AppServiceProvider` |
| FR-ATT-03 | The system keeps one attendance row for each student and each date. A database constraint limits the status values. | `database/migrations/2026_09_24_145355_create_attendance_table.php` |
| FR-ATT-04 | The attendance page takes no entries on days that are not school days. | `AttendanceController`, `config/school.php` |
| FR-ATT-05 | Present and Late count as attended in attendance percentages. | `Attendance::ATTENDED` |
| FR-ATT-06 | The system stores which login saved each record (`marked_by`). | `app/Models/Attendance.php` |
| FR-ATT-07 | The phone layout has large status buttons and a sticky save bar with live totals. | `resources/views/teacher/attendance.blade.php` |

### 4.8 Exams, marks, results and report cards

| ID | Requirement | Code path |
|---|---|---|
| FR-EXM-01 | An admin adds, edits and deletes exams with name, start date, maximum marks and pass marks. | `app/Http/Controllers/Admin/ExamController.php`, `app/Http/Requests/ExamRequest.php`, `app/Models/Exam.php` |
| FR-EXM-02 | An admin or a teacher opens a marks sheet for an exam, a division and a subject. | `app/Http/Controllers/MarksController.php` |
| FR-EXM-03 | The system saves one mark per exam, student and subject. A row holds a mark or an absent flag, not both. A database check enforces this rule. | `database/migrations/2026_09_24_145851_create_exams_and_marks_tables.php`, `app/Models/Mark.php` |
| FR-EXM-04 | The system rejects a mark above the exam maximum. | `app/Http/Requests/SaveMarksRequest.php` |
| FR-EXM-05 | A blank mark row deletes the mark of that student. | `MarksController::save` |
| FR-EXM-06 | A teacher enters marks only for a division and subject that the teacher is assigned to. | Gate `enter-marks` |
| FR-EXM-07 | The system locks marks while results are published. | `MarksController`, `Exam::results_published_at` |
| FR-EXM-08 | An absent subject counts as 0 and fails the student. | `app/Services/ExamResults.php` |
| FR-EXM-09 | A student with a missing subject mark gets the result Incomplete. The system does not rank that student. | `ExamResults` |
| FR-EXM-10 | The system ranks students with competition ranks (1, 2, 2, 4). | `ExamResults` |
| FR-EXM-11 | The grade comes from one grade scale in `config('school.grades')`. A mark below the pass mark is grade F. | `config/school.php`, `Exam::gradeFor()` |
| FR-EXM-12 | An admin publishes or unpublishes the results of an exam. | `ExamController::togglePublish` |
| FR-EXM-13 | An admin sees marks entry progress and results for each division. | `ExamController::show`, `results` |
| FR-EXM-14 | An admin sees a report card for a student. | `ExamController::reportCard`, `resources/views/exams/report-card.blade.php` |

### 4.9 Reports and CSV export

| ID | Requirement | Code path |
|---|---|---|
| FR-REP-01 | An admin sees attendance by division and by student for a date range. | `app/Http/Controllers/Admin/ReportController.php::attendance`, `app/Services/SchoolReports.php` |
| FR-REP-02 | The report flags students below `school.low_attendance_percent` (default 75). | `config/school.php`, `SchoolReports` |
| FR-REP-03 | An admin sees exam results by division and by subject. | `ReportController::exams`, `SchoolReports::examByDivision`, `examBySubject` |
| FR-REP-04 | Every CSV download uses one service. The service adds a `'` before text that starts with `=`, `+`, `-`, `@`, tab or carriage return. | `app/Services/CsvExport.php`, `tests/Unit/Services/CsvExportTest.php` |

### 4.10 Dashboards

| ID | Requirement | Code path |
|---|---|---|
| FR-DSH-01 | The admin dashboard shows live attendance figures for today and result figures. | `AdminController::index`, `SchoolReports::attendanceToday`, `attendanceTodayByDivision` |
| FR-DSH-02 | The teacher dashboard shows the lessons of today. | `Teacher\PortalController::dashboard` |
| FR-DSH-03 | A teacher sees a list of own divisions and a class roster. | `Teacher\PortalController::classes`, `roster` |
| FR-DSH-04 | The student dashboard shows enrolment, attendance summary and the lessons of today. | `Portal\StudentPortalController::dashboard`, `app/Services/StudentOverview.php` |
| FR-DSH-05 | The parent dashboard lists the children of the parent. | `Portal\ParentPortalController::dashboard`, `resources/views/parent/dashboard.blade.php` |

### 4.11 Student and parent portals

| ID | Requirement | Code path |
|---|---|---|
| FR-POR-01 | A student sees own timetable, attendance and results. | `Portal\StudentPortalController` |
| FR-POR-02 | A parent sees the timetable, attendance and results of each own child. | `Portal\ParentPortalController` (routes `/parent/children/{student}/*`) |
| FR-POR-03 | A parent cannot open the pages of a child that is not the child of the parent. | Gate `view-student` |
| FR-POR-04 | The portals show the timetable only after the admin publishes it. | `StudentRecords` trait, `AcademicYear::timetable_published_at` |
| FR-POR-05 | The portals show only exams with published results. An unpublished report card returns 404. | `app/Http/Controllers/Portal/StudentRecords.php` |
| FR-POR-06 | The student and parent portals share the same views, so links stay in the current portal. | `resources/views/portal/*`, `StudentRecords::portalRoutes()` |

## 5. Planned features

None of these features exist in the code. This PRD does not design them. Each needs its own PRD.

| ID | Goal | Status | Needs its own PRD |
|---|---|---|---|
| FR-FEE-01 | Fees and payments: the school records fees and payments for each student. | Planned | yes |
| FR-MSG-01 | Announcements and messaging: the school sends notices and messages to users. | Planned | yes |
| FR-HW-01 | Homework and assignments: teachers set work and students see it. | Planned | yes |
| FR-ADM-01 | Admissions: the school records applicants and moves them to students. | Planned | yes |
| FR-IMP-01 | Bulk CSV import of students, parents and teachers. | Planned | yes |
| FR-PRO-01 | Year promotion: the admin moves students to the next class and year. | Planned | yes |
| FR-EVT-01 | Events and calendar: the school publishes a calendar of events. | Planned | yes |
| FR-LIB-01 | Library: the school tracks books and loans. | Planned | yes |
| FR-GRD-01 | Terms and weighted exams with a final grade for each term. | Planned | yes |
| FR-EML-01 | Email in production: the system sends email for password reset and for other notices. | Planned | yes |
| FR-I18N-01 | French: all pages and emails are available in French and English. | Planned | yes |
| FR-ROLE-01..06 | Custom roles and per-section permissions (see section 3.2). | Planned | yes |
| FR-DAT-01 | Data residency: all school data stays in Canada. | Planned | yes |
| FR-DAT-02 | Retention and deletion policy for students who leave. | Planned | yes |
| FR-DAT-03 | Audit log of admin actions. | Planned | yes |
| FR-DAT-04 | Student data export. | Planned | yes |

Checks made for "not built":
- Only `PasswordReset` mail exists (`Login` uses `Notifiable`). No other mail class exists in `app/`.
- No table exists for fees, messages, homework, events or books (see `database/migrations`).
- No grade model for terms or weights exists. `exams` has no term column.
- `lang/` has English files only.
- No audit-log table exists. Only `attendance.marked_by` and the marks `entered_by` field record who saved data.

## 6. Data model summary

Source: `database/migrations`, `app/Models`. Model names are plural, except `StudentClass`, `AcademicYear` and `Login`.

| Table | Model | Main links |
|---|---|---|
| `login` | `Login` | Has one `students`, `teachers` or `parents` row through `login_id`. Field `role` (1 to 4) and `is_active`. |
| `parents` | `Parents` | Has many `students`. |
| `students` | `Students` | Belongs to `parents` (`parent_id`) and `login`. Has many `student_classes`, `attendance`, `marks`. |
| `teachers` | `Teachers` | Belongs to `login`. Many-to-many with `subjects` (`subject_teacher`) and `divisions` (`teacher_division`, flag `class_teacher`). |
| `subjects` | `Subjects` | Field `periods_per_week`. |
| `classes` | `Classes` | Has many `divisions` (`class_id`). |
| `divisions` | `Divisions` | Belongs to `classes`. |
| `academic_year` | `AcademicYear` | Fields `is_active`, `timetable_published_at`. |
| `student_classes` | `StudentClass` | Links student, division and academic year. |
| `periods` | `Period` | Bell schedule. Field `is_break`. |
| `timetable_entries` | `TimetableEntry` | One row for each academic year, division, day and period. Links subject and teacher. |
| `attendance` | `Attendance` | One row for each student and date. Fields `status`, `marked_by`. |
| `exams` | `Exam` | Belongs to `academic_year`. Fields `max_marks`, `pass_marks`, `results_published_at`. |
| `marks` | `Mark` | One row for each exam, student and subject. Fields `marks`, `is_absent`. |
| `sessions`, `cache`, `jobs`, `password_reset_tokens` | none | Laravel system tables. Sessions are encrypted. |

The legacy `users` table was dropped (`2026_10_05_162918_drop_legacy_users_table.php`).

## 7. Non-functional requirements

### 7.1 Security (Built)

| ID | Requirement | Code path |
|---|---|---|
| NFR-SEC-04 | Logs do not hold personal data. A formatter masks database row values, emails and reset tokens. | `app/Logging/RedactPersonalData.php`, `RedactingFormatter.php`, `config/logging.php` |
| NFR-SEC-05 | No page makes the browser contact another server. Fonts and DataTables are self-hosted. | `public/vendor`, `tests/Feature/Security/SelfHostedAssetsTest.php` |
| NFR-SEC-07 | Demo seeders throw in production. | `database/seeders/DemoSeeder.php` |
| NFR-SEC-06 | Two-factor sign-in and password confirmation for sensitive admin actions (see FR-AUTH-12 to FR-AUTH-15). The switch `TWO_FACTOR_REQUIRED` is ignored in production. | `config/two-factor.php`, `tests/Feature/Auth/TwoFactorEdgeCasesTest.php` |
| NFR-SEC-09 | Sessions are encrypted. The system deletes old sessions every hour. | `config/session.php`, `app/Console/Commands/PruneSessions.php`, `routes/console.php` |
| NFR-SEC-10 | A strict Content-Security-Policy and other security headers are sent on all responses. Inline scripts need a nonce. | `app/Http/Middleware/SecurityHeaders.php` |
| NFR-SEC-11 | Password-reset pages do not show whether an email has an account. | `ForgotPasswordController`, `ResetPasswordController` |
| NFR-SEC-12 | CSV downloads block formula injection. | `app/Services/CsvExport.php` |
| NFR-SEC-14 | One password rule everywhere (see FR-AUTH-08). | `AppServiceProvider` |
| NFR-SEC-15 | A workflow runs `composer audit` and `npm audit` on pull requests, on pushes to `main` and every Monday. | `.github/workflows/audit.yml` |
| NFR-SEC-19 | Reset-link requests have a rate limit for each IP address. | `AppServiceProvider` |
| NFR-SEC-20 | Reset pages ignore inactive accounts. | `ForgotPasswordController`, `ResetPasswordController` |
| NFR-SEC-24 | Unused scaffolding (the `User` model, `users` table and register pages) is removed. | `tests/Feature/Security/LegacyUsersTableTest.php` |
| NFR-RTE-01 | A test fails when a route lacks `auth` and a `role:` middleware. | `tests/Feature/Auth/RouteProtectionTest.php` |

The security audit also has SEC items that are not done yet (for example SEC-01). The owner tracks them outside this repository.

### 7.2 Database roles and logging (Built)

- The app connects as `school_app`. This role has only `SELECT`, `INSERT`, `UPDATE` and `DELETE`.
- Migrations run as `school_owner` through the `pgsql_migrations` connection.
- The superuser role is used only by `php artisan db:provision-roles`. Code path: `app/Console/Commands/ProvisionDatabaseRoles.php`, `config/database.php`.
- Test: `tests/Feature/Security/DatabaseRoleTest.php`.
- Logs are daily files. The system keeps them for `LOG_DAILY_DAYS` (default 14). The command `db:provision-roles` sets `log_error_verbosity = terse`.

### 7.3 Accessibility, dark mode and phone layouts (Built)

- NFR-A11Y-01: Every page meets WCAG 2.2 AA. Each page has one `<h1>`, a skip link, labelled fields and one `<main>`. Tests: `tests/Feature/AccessibilityMarkupTest.php`, `PageStructureTest.php`. Browser check: `tests/e2e/smoke.cjs`.
- NFR-A11Y-02: Colour is never the only sign of meaning. Theme: `public/css/school-theme.css`.
- NFR-DM-01: Dark mode follows the system setting. Code path: `public/css/school-theme.css`.
- NFR-PH-01: Student, parent and teacher timetables and the attendance sheet have phone layouts. Browser check: `tests/e2e/browser-suite.cjs`.

### 7.4 Privacy (Built rules)

- NFR-PRV-01: The system sends no visitor data to outside servers (SEC-05).
- NFR-PRV-02: Logs and sessions hold no readable personal data (SEC-04, SEC-09).
- NFR-PRV-03: A parent sees only own children. A student sees only own records.

### 7.5 Planned non-functional requirements

| ID | Requirement | Status |
|---|---|---|
| NFR-CA-01 | All school data and backups stay in Canada. | Planned |
| NFR-UP-01 | An uptime target. The value is not decided (see section 10). | Planned |
| NFR-BK-01 | A backup and restore target. The value is not decided (see section 10). | Planned |
| NFR-FR-01 | French user interface and French email. | Planned |

## 8. Deployment state

Source: `docs/aws-deployment.md`, `compose.prod.yaml`, `docker/prod/`, `scripts/deploy.sh`.

- Purpose: demo and showcase only. No real student data is allowed on the demo.
- Plan: one EC2 instance (`t3.small`, Ubuntu 24.04) in region **us-east-2 (Ohio)**.
- Stack: Docker Compose with php-fpm, Caddy (HTTPS), PostgreSQL 18 and a scheduler.
- Backup plan: nightly `pg_dump` to S3.
- Domain plan: a free DuckDNS name with a Let's Encrypt certificate.
- Mail: `MAIL_MAILER=array`. The demo discards mail. An admin sets passwords.
- Seeding: none in production. The first admin comes from `php artisan create-admin`.
- Progress on 2026-10-23: no AWS resources exist. The next step is "One-time AWS setup" step 1.
- Local development: Laravel Sail (`compose.yaml`) with Mailpit for local mail.

## 9. Known gaps and risks

| ID | Gap or risk | Evidence |
|---|---|---|
| GAP-01 | Three student methods stay in the legacy `AdminController` (`viewStudent`, `addStudent`, `getParentByEmail`). The other student methods are in `StudentController`. | `routes/web.php`, `app/Http/Controllers/AdminController.php` |
| GAP-02 | Route styles are mixed. Some routes use `/admin/...`, some use `/students`, `/teachers`, `/subjects`, `/marks`. Some use camelCase (`/admin/addStudent`). | `routes/web.php` |
| GAP-03 | A route has no name: `POST /admin/getParentByEmail`. | `routes/web.php` |
| GAP-04 | A user cannot change the own password when signed in. Only the reset by email exists. | `routes/web.php` has no such route |
| GAP-05 | No year promotion exists. An admin enrols each student by hand each year. | Section 5 (FR-PRO-01) |
| GAP-06 | The hosting plan uses us-east-2. This conflicts with the rule that data stays in Canada. The runbook forbids real student data on the demo. | `docs/aws-deployment.md` |
| GAP-07 | No audit trail exists for admin actions. | Section 5 (FR-DAT-03) |
| GAP-08 | The parent menu has one item ("My Children"). | `app/Providers/EventServiceProvider.php` (`menuFor`) |
| GAP-09 | A student has one parent link (`parent_id`). The system does not support two guardians. | `app/Models/Students.php` |
| GAP-10 | Email does not work in the demo, so a password reset email does not arrive. | `docs/aws-deployment.md` |
| GAP-11 | The email verification controller exists, but the verify routes are off. | `app/Http/Controllers/Auth/VerificationController.php`, `Auth::routes` call |
| GAP-12 | The app has no queue worker for mail. Mail runs in the request. | `compose.prod.yaml` runs only app, web, pgsql, scheduler |
| GAP-13 | Behind a load balancer, trusted proxies are not set. All visitors share one rate limit. | `CLAUDE.md` (SEC-19 note) |
| GAP-14 | One grade scale applies to all exams. No terms or weights exist. | `config/school.php` |
| GAP-15 | The Tailwind and Vite setup in `package.json` is unused. | `CLAUDE.md` |
| GAP-16 | The bug and improvement backlog is kept outside the repository. Its items are not listed here. | Owner notes |

## 10. Open questions

| ID | Question | Owner |
|---|---|---|
| OQ-01 | Who is the target buyer: a private school, a school board or a district? | Product owner |
| OQ-02 | What are the uptime and backup targets (availability, RPO, RTO)? | Product owner |
| OQ-03 | Which GPA rules apply (scale, credit weights, rounding)? | Product owner |
| OQ-04 | Is a multi-tenant model needed later? | Product owner |
| OQ-05 | Which Canadian hosting provider and region? Which provinces apply first? | Product owner |
| OQ-06 | How long does the school keep records of students who leave? | Product owner, legal |
| OQ-07 | Which email provider sends production mail, and in which country does it process data? | Product owner |
| OQ-08 | Does the school need two parents or guardians for each student? | Product owner |
| OQ-09 | Does a school need more than one grade scale (for example, by class)? | Product owner |
| OQ-10 | Does a school need a different bell schedule for each class? | Product owner |
| OQ-11 | Which French terms replace "class" and "division" in the user interface? | Product owner |

## 11. Change log

| Date | Change |
|---|---|
| 2026-10-23 | First version (reverse PRD) |
| 2026-10-25 | SEC-06 two-factor sign-in moved to Built (FR-AUTH-12 to FR-AUTH-15, NFR-SEC-06). Design in `docs/prd/two-factor-sign-in.md` |

## How to use this PRD

- This PRD describes the product as it is on 2026-10-23.
- Every new feature gets its own PRD in `docs/prd/` before work starts.
- Move an item from Planned to Built only after the code and tests exist. Add the code path.
- Add a row to the change log for each change to this file.
