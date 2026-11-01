# Support Activity Tracker

A web application for an applications support team to record, hand over and report on their daily operational checks, such as *"Daily SMS count in comparison to SMS count from logs"*.

Built with Laravel 13 for the Npontu Technologies Platforms Developer assessment.

---

## Live demo

**URL:** [https://activity-tracker-xomw.onrender.com]

> Hosted on a free tier that sleeps when idle. The first visit after a quiet period can take up to a minute to load.

| Role | Email | Password |
|---|---|---|
| Admin (team lead) | admin@example.com | password |
| Support | kwame@example.com | password |
| Support | ama@example.com | password |
| Support | kofi@example.com | password |

- Everyone signs in at `/login`.
- Admins also see an **Administration** link that opens the management panel at `/admin`.
- The demo database is seeded with ten activities and two weeks of realistic update history, including items left pending and handed over between staff.

---

## What it does

- **Daily Board**: every activity for the day, its current status, who last updated it, when, and their remark.
- **One-click updates**: mark an activity *Done* or *Pending*. A remark is required when pending, so the next person knows why.
- **Pending handover panel**: everything still open, including items **carried over** from the previous day.
- **Timeline**: every update made that day, newest first.
- **History by day**: view any past day, read-only.
- **Reports**: query activity history over any date range, filter by person, activity, category and status, see a daily chart and insights, and export to CSV or print.
- **Administration**: admins manage activities (create, edit, retire) and staff accounts (create, assign roles, deactivate).

---

## How the requirements were interpreted

**1. Inputting activities**
Activities are treated as **recurring daily checks** defined once by an admin (title, category, description of how to perform the check). They are not one-off tasks. Retired activities are hidden from the board but keep their history.

**2. Updating status and remarks**
Every status change is stored as a **new, permanent record** rather than overwriting a single status field. This is the key design decision: it preserves the full history that requirements 3, 4 and 5 depend on. A remark is mandatory when marking an activity pending and optional when done.

**3. Capturing bio details and time**
Each update is linked to the signed-in user and timestamped automatically, so it cannot be faked or backdated. Users have bio details (name, staff ID, phone, department). Staff ID is assigned by an admin and is read-only to the user, protecting the integrity of the audit trail.

**4. Daily view for handover**
The Daily Board answers *"what was done today, by whom, when, and what is still open?"* at a glance. The handover panel lists pending items with the person and remark that left them open, and items left pending yesterday are flagged as **carried over**. The board refreshes itself every 30 seconds, so teammates see each other's updates without reloading.

**5. Reporting over custom durations**
The Reports page accepts any date range (with quick presets) plus filters, and shows summary figures, a day-by-day chart, updates per person, the activities most often left pending, and a paginated history table. Results can be exported to CSV or printed.

**6. Authentication**
All pages require sign-in. Accounts are created by admins only (no public registration), access is role-based, and deactivated users cannot sign in.

---

## Key design decisions

- **Append-only update history.** Updates cannot be edited or deleted once recorded. The model rejects changes, and the database refuses to delete an activity or user that has updates.
- **Business logic outside the UI.** Recording an update lives in a dedicated action class (`app/Actions/RecordActivityUpdate.php`). Report queries live in one report class (`app/Reports/ActivityReport.php`) shared by the screen and the CSV export.
- **A separate activity date.** Each update stores the day it belongs to as well as its exact timestamp, keeping daily queries simple and correct.
- **Status as an enum.** `Done` and `Pending` are defined once, along with their labels and colours, so every screen is consistent.
- **Purpose-built daily screens, generated admin screens.** The board and reports are hand-built with Livewire for the best day-to-day experience. Back-office management uses Filament for speed and reliability.
- **No multi-team support.** The brief describes a single team whose members need a shared view for handover. Splitting data into teams would add complexity and could hide updates from the people who need them. Departments and categories provide grouping instead.

---

## Security

- Admin-created accounts only; public registration is disabled
- Role-based access (admin, support) using Spatie Permission; only active admins can open `/admin`
- Deactivated accounts are blocked at sign-in
- Optional two-factor authentication, with password confirmation for sensitive actions
- Rate-limited sign-in, CSRF protection, hashed passwords, validation on every input
- Immutable update records, and restrict-on-delete foreign keys to protect history
- Staff cannot delete their own accounts; admins deactivate instead
- CSV export protected against spreadsheet formula injection
- HTTPS in production, secure cookies and a 60-minute session lifetime
- Past days are read-only, and updates cannot be backdated

---

## Non-functional considerations

**Performance**
Indexes on the columns used for daily and report queries, eager loading to avoid repeated queries, pagination, streamed CSV export that reads in batches, and a one-year cap on report ranges.

**Usability**
Responsive layout for phones and desktops, dark mode, colour-coded statuses, live refresh on today's board, clear empty states, bookmarkable report filters, and a clean print layout.

**Reliability**
Automated tests for the update rules, the board, reports and access control. Invalid or future dates in the address bar fall back safely instead of failing.

**Maintainability**
Thin UI components, logic in action and report classes, typed code checked by PHPStan, consistent formatting with Pint, and continuous integration on every push.

**Portability**
Runs on MySQL, PostgreSQL or SQLite without code changes. The live demo uses PostgreSQL; local development uses MySQL; tests use SQLite.

---

## Tech stack

| Area | Technology |
|---|---|
| Framework | Laravel 13 (PHP 8.4) |
| Interactive UI | Livewire 4, Flux, Tailwind CSS |
| Admin panel | Filament 5 |
| Authentication | Laravel Fortify |
| Roles | Spatie Laravel Permission |
| Database | MySQL / PostgreSQL |
| Testing and quality | Pest, PHPStan (Larastan), Laravel Pint, GitHub Actions |
| Hosting | Render (Docker) |

---

## Running locally

**Requirements:** PHP 8.3+, Composer, Node.js 20+, and MySQL (or SQLite).

```bash
git clone https://github.com/Darkvader007/activity-tracker.git
cd activity-tracker

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database details and set `APP_TIMEZONE=Africa/Accra`, then:

```bash
php artisan migrate --seed
composer run dev
```

Open http://localhost:8000 and sign in with the demo accounts above. Locally, the password is `password` unless `DEMO_PASSWORD` is set.

---

## Testing and code quality

```bash
php artisan test        # run the test suite
vendor/bin/pint         # fix code style
composer ci:check       # style, static analysis and tests together
```

The same checks run automatically on GitHub for every push.

---

## Project structure (key files)

```
app/
  Actions/RecordActivityUpdate.php      Rules for recording an update
  Enums/ActivityStatus.php              Done / Pending, labels and colours
  Models/Activity.php                   Activities, categories, relationships
  Models/ActivityUpdate.php             Immutable update records
  Reports/ActivityReport.php            Report queries and CSV export
  Filament/Resources/                   Admin screens for activities and users
resources/views/pages/
  board.blade.php                       Daily Board
  reports.blade.php                     Reports
database/
  migrations/                           Tables and indexes
  seeders/                              Demo roles, users, activities and history
tests/Feature/                          Automated tests
```

---

## Future improvements

- Email or Slack alerts for activities still pending at the end of a shift
- A scheduled daily summary email to the team lead
- Single sign-on and passkeys
- Support for multiple teams, if the system is rolled out more widely
- Per-activity schedules (for example, weekly checks)
- A production web server (Nginx with PHP-FPM, or FrankenPHP) in place of the demo server

---

## Author

[YOUR_NAME] · dekimelz@gmail.com
