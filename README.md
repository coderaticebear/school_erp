# School ERP

A containerized School ERP built with **Laravel**, **PostgreSQL**, and **Docker**. It covers the day-to-day running of a school: classes and enrolment, teachers and subjects, timetables, attendance, exams and report cards, with separate portals for admins, teachers, students and parents.

---

## 🚀 Features

**Admin**
* Academic years (one active at a time), classes and divisions
* Subjects with optional weekly period targets; teachers who teach several subjects
* Students with parent accounts (existing parents are found by email), enrolment per year, edit and deactivate
* Teacher assignment per division, with one class teacher
* **Timetable**: bell schedule with breaks, automatic generation with no teacher or class clashes, click-to-edit slots, CSV export, printing, publishing
* **Exams**: marks entry progress, results with grades and ranks, report cards, CSV export, publishing
* Reports: attendance by division and student (low-attendance flags), exam pass rates and averages
* Dashboard with live attendance and results figures

**Teachers**: today's lessons, timetable, class lists, daily attendance, marks entry for their own subjects

**Students and parents**: timetable, attendance history, published results and report cards (parents see each of their children)

Access is role-based (admin, teacher, student, parent). Teachers only reach their own divisions and subjects; parents only their own children.

---

## 🧱 Tech Stack

* **Backend:** Laravel 12 (PHP 8.5 in Sail), Eloquent
* **Database:** PostgreSQL 18
* **UI:** Blade + AdminLTE 3 (Bootstrap)
* **Tests:** Pest 4
* **Containers:** Docker via Laravel Sail (app, PostgreSQL, pgAdmin)

---

## 📦 Setup

Prerequisites: Docker and Docker Compose.

```bash
git clone <your-repo-url>
cd school_erp
cp .env.example .env          # PostgreSQL/Sail defaults are already filled in

# First run only: install PHP dependencies (use `composer install` if Composer is installed locally)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/app" -w /app composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

The app runs at http://localhost and pgAdmin at http://localhost:5050.

> On **Docker Desktop for Linux**, Laravel recommends the `default` Docker context (`docker context use default`). With the Desktop context, the container can briefly see stale copies of files you just edited.

### Demo accounts

The seeder creates demo data (classes, teachers, students, a published timetable, two weeks of attendance and a published exam) plus these logins, all with the password `password`:

| Role | Email |
|---|---|
| Admin | admin@example.com |
| Teacher | teacher@example.com |
| Student | student@example.com |
| Parent | parent@example.com (parent of the demo student) |

### School settings

Set these in `.env` (defaults in `config/school.php`):

| Variable | Default | Meaning |
|---|---|---|
| `SCHOOL_DAYS` | `1,2,3,4,5,6` | Weekdays the timetable covers (1 = Monday) |
| `TIMETABLE_MAX_SUBJECT_PER_DAY` | `2` | Most lessons of one subject per day for a division |
| `TIMETABLE_ATTEMPTS` | `40` | Generator attempts; the best result is kept |
| `LOW_ATTENDANCE_PERCENT` | `75` | Attendance below this is flagged in reports |

The grade scale is in `config/school.php` (`grades`).

---

## 🧪 Useful Commands

```bash
./vendor/bin/sail up -d                        # start containers
./vendor/bin/sail down                         # stop containers
./vendor/bin/sail artisan migrate:fresh --seed # rebuild the database with demo data
./vendor/bin/sail artisan test                 # run the test suite
./vendor/bin/sail artisan test --filter=Timetable
./vendor/bin/sail bin pint                     # format code
./vendor/bin/sail psql                         # PostgreSQL shell
```

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

---

## 📄 License

This project is open-source and available under the MIT License.

---

## 👤 Author

**Richu Thankachan**
Full Stack Developer | Software Engineer

---
