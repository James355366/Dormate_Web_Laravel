# November 8 update

Existing installation: follow **UPDATE_FIRST.md** before starting. The update adds email/mobile password sign-up, removes decorative accent stripes and the public Super Admin section, and restricts regular users to announcements from their own dorm administrator. Keep the existing MySQL database.

# DorMate — Laravel + MySQL

Start with [START_HERE.md](START_HERE.md) for Windows, VS Code and XAMPP instructions.

This integrates the supplied DorMate PHP application into Laravel 12, retaining its Bootstrap design, four role workflows and MySQL/MariaDB schema. `/` serves DorMate's landing page and `/login` serves its role-based login. PHP 8.2+ is required. CSS and Composer dependencies are bundled; no npm build is needed.

## Project map

| File/folder | Purpose |
| --- | --- |
| routes/web.php | Named URLs and POST endpoints |
| app/Http/Controllers/PageController.php | Public pages, session checks and Blade responses |
| app/Http/Controllers/RoomController.php | Eloquent room listing and occupancy |
| app/Http/Controllers/ActionController.php | Form processing and error responses |
| app/Http/Controllers/MediaController.php | Authorized private image delivery |
| app/Models | User, Dorm, Room, Bill, MaintenanceReport, Announcement |
| app/Services/DormateActions.php | DorMate business rules through Laravel authentication, requests and transactions |
| app/Support/dormate.php | Shared query, validation, presentation and upload helpers |
| resources/views/dormate | DorMate pages rendered through Blade |
| public/assets | Original CSS and branding |
| database/dormate.sql | Schema and original fictional demo records |
| database/schema.sql | Empty schema; alternative to the demo SQL |
| database/migrations | Baseline for a new empty MySQL database |
| storage/app/private/uploads | Private receipts, avatars, maintenance photos and QR images |

This is an incremental Laravel integration: prepared SQL and some presentation helpers remain to preserve existing workflows. Laravel handles authentication and sessions; RoomController and announcement queries use Eloquent. Not every query has been rewritten into Eloquent. The Android/Firebase edition is separate and is not synchronized by this project.

## Database initialization

For existing data or an imported SQL database, run `php artisan dormate:adopt-existing`, then `php artisan migrate` to apply the mobile sign-in update. For an entirely empty database without an SQL import, the alternative is `php artisan migrate` followed by `php artisan dormate:create-admin`. Do not initialize a populated database again. Default Laravel users/cache/jobs migrations have been replaced with the DorMate baseline; sessions/cache use files.

Neither SQL file switches the database selected in phpMyAdmin. Export your current database there to obtain a fresh `.sql` copy of your own records. No SQLite database file is included.

## Development tests

Create a separate empty MySQL database named **dormate_laravel_test**. Copy `.env.example` to `.env.testing`, set that database name and your local test connection, and provide an APP_KEY (for example `php artisan key:generate --env=testing`). Then run `php artisan test`.

Tests refuse other database names, initialize that disposable database if needed and roll back their business records. Do not put real data in the test database.

See [VALIDATION.md](VALIDATION.md) for completed checks and limits. Browser checks on your Windows/XAMPP computer remain to be done.
