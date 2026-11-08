# Validation — November 8, 2026 update

## Passed

- PHP syntax validation for 51 application, migration, Blade and test files; Node syntax check for the new form enhancement script.
- Laravel feature tests against an isolated MySQL-compatible MariaDB database: **26 tests, 338 assertions**.
- Existing email/password login and approval flow retained; mobile registration for both public roles, normalization, duplicate checks, wrong password/role, disabled/rejected accounts, shared login throttling and recovery-email reset exercised.
- Two-dorm isolation exercised on announcement lists, dashboard previews, read actions and direct edit/delete requests, including global posts, mismatched authors and unassigned accounts. Super Admin oversight remains available.
- Populated upgrade check imported the supplied SQL into a disposable MySQL database, adopted the baseline and ran migrations. All original column values in all **10 business tables** remained identical. Unique email/mobile indexes remained in place. Re-running migration/adoption was safe.
- Browser layout review used HTML rendered by the actual Laravel views with test fixtures: login method switching, mobile registration, the three public landing roles, all four stakeholder dashboards, light/dark styles and a 390px mobile sign-up viewport. The reviewed cards have uniform ordinary outlines, with no colored accent stripe.

## Scope

The functional suite ran on Linux with PHP 8.3.6 and MariaDB 10.11. The project retains its Composer PHP 8.2.12 platform constraint and existing dependencies. The browser check reviewed rendered pages; authentication and database changes were exercised by Laravel tests. This was not a test on the user's Windows/XAMPP installation or live database.

SMS delivery/number verification is not implemented. Mobile sign-in uses a password. The existing email reset transport needs real SMTP configuration for external delivery.

## Re-run locally (separate test database only)

Create an empty database named `dormate_laravel_test`. From the project folder, use the appropriate local MySQL host/port/credentials and run:

```powershell
php artisan config:clear
php artisan test
```

`phpunit.xml` forces the separate test database, and the tests refuse any other database name. Never point tests at a live DorMate database.
