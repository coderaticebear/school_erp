# School ERP System

A Laravel 12-based School ERP system built with **PostgreSQL**, **Blade templates**, and **AdminLTE** for the frontend. The system implements **role-based authentication** for Admins, Teachers, Students, and Parents, following a clean **MVC architecture**.

---

## Features

* **Role-based Authentication**

  * Admin, Teacher, Student, Parent roles
  * Middleware enforces role-based route access
* **User Management**

  * Custom `login` table with hashed passwords
  * User-specific dashboards
* **Student, Teacher, Parent, and Subject Management**

  * Full CRUD operations
  * One-to-one and one-to-many Eloquent relationships
* **Class & Division Management**

  * Classes have multiple divisions
  * Student assignment by parent, class, and division
* **Frontend**

  * Blade templates with **HTML, CSS, JS**
  * AdminLTE integrated for a clean, responsive UI
* **Database**

  * PostgreSQL support
  * Seeder and migration files for all tables

---

## System Requirements

* PHP >= 8.4
* Composer
* PostgreSQL
* Node.js & npm (optional, for frontend assets)
* Laravel 12

---

## Installation

1. **Clone the repository**

   ```bash
   git clone <repo-url>
   cd <repo-folder>
   ```

2. **Install dependencies**

   ```bash
   composer install
   npm install
   npm run dev   # optional, for compiling assets
   ```

3. **Create environment file**

   ```bash
   cp .env.example .env
   ```

   Update `.env` with your database credentials:

   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=school_erp
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

4. **Generate application key**

   ```bash
   php artisan key:generate
   ```

5. **Run migrations and seeders**

   ```bash
   php artisan migrate
   php artisan db:seed --class=LoginSeeder
   ```

6. **Serve the application**

   ```bash
   php artisan serve
   ```

7. Visit [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

---

## Usage

* **Login credentials (from seeder)**:

| Role    | Email                                             | Password   |
| ------- | ------------------------------------------------- | ---------- |
| Admin   | [admin@example.com](mailto:admin@example.com)     | admin123   |
| Teacher | [teacher@example.com](mailto:teacher@example.com) | teacher123 |
| Student | [student@example.com](mailto:student@example.com) | student123 |
| Parent  | [parent@example.com](mailto:parent@example.com)   | parent123  |

* After login, users are redirected to dashboards based on their role.
* Middleware prevents unauthorized access to role-specific routes.

---

## Database Structure

* **login** → Stores all users with `role` and `is_active` flags.
* **students** → Linked to `login` and `parents`.
* **teachers** → Linked to `login` and `subjects`.
* **parents** → Linked to `login` and has many students.
* **subjects** → Subjects taught by teachers.
* **classes** → Academic classes.
* **divisions** → Subdivisions of classes.

Eloquent relationships ensure referential integrity and ease of data retrieval.

---

## Role-based Middleware

* Custom middleware `RoleMiddleware` checks the authenticated user’s role.
* Routes are protected using:

```php
Route::get('/admin/dashboard', [AdminController::class, 'index'])
     ->middleware(['auth', 'role:1']);
```

* Roles: 1 = Admin, 2 = Teacher, 3 = Student, 4 = Parent

---

## Contributing

1. Fork the repository
2. Create a new feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add new feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Create a pull request

---

## License

This project is licensed under the MIT License.

---

## Notes

* Laravel 12 handles middleware registration in `bootstrap/app.php`.
* Use `$app->routeMiddleware([...])` for custom route middleware.
* AdminLTE templates are fully integrated; Blade templates can be customized freely.
* Always hash passwords using `Hash::make()` before seeding or creating users.
