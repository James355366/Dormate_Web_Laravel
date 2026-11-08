# Run DorMate with Laravel and XAMPP MySQL

**Already running DorMate? Follow `UPDATE_FIRST.md` to retain your current .env, APP_KEY, uploads and database.**

This is the full Laravel integration of your supplied DorMate website. It includes the landing page, login, four role dashboards, rooms, accounts, billing, maintenance, announcements, profile and theme toggle. The earlier small Laravel ZIP only added a dorm directory; this edition replaces that partial integration.

Laravel uses the MySQL/MariaDB server in XAMPP. A `.sql` file imports or exports database tables and records. No SQLite database file is needed or included.

## 1. Extract and open the correct folder

1. Keep your existing PHP website as a backup.
2. Extract this ZIP to a NEW location, for example `C:\Users\USER\DorMate-Laravel`.
3. In VS Code choose **File → Open Folder**. Open the folder containing **artisan**, **composer.json**, **app**, **routes** and **public**.
4. Choose **Terminal → New Terminal** and run:

```powershell
Get-ChildItem artisan
```

The output must list `artisan`. Otherwise, open the inner extraction folder containing that file. Do not move just the artisan file by itself.

PHP and Composer are already installed on your computer. This project uses Laravel 12 and supports your PHP 8.2.12. The `vendor` dependencies are included. If extraction omitted `vendor/autoload.php`, run `composer install` from this folder.

## 2. Start XAMPP and choose ONE database option

Start **MySQL** in XAMPP. Start **Apache** too when using phpMyAdmin at `http://localhost/phpmyadmin/`. Laravel's own web server will run separately on port 8000.

### A. Keep your existing DorMate accounts and records (recommended for you)

Your screenshots show a database named `dormate`.

1. In phpMyAdmin select **dormate**. It should contain tables such as `users`, `dorms`, `rooms`, `bills` and `maintenance_reports`.
2. Use **Export → Quick → SQL → Export** to keep a backup.
3. **Do not import either supplied SQL file into this existing database.**
4. Use `DB_DATABASE=dormate` in step 3.
5. Copy the original website's `storage\uploads` contents into this Laravel project's `storage\app\private\uploads`, keeping the filenames. The image supplied in your ZIP is already included, but your computer may have newer uploads.

### B. Create a separate database with the original demonstration data

1. In phpMyAdmin choose **New**.
2. Create **dormate_laravel**, with collation `utf8mb4_unicode_ci`.
3. Select that database on the left and click **Import**.
4. Choose this project's **database\dormate.sql**, then click **Go/Import**.
5. Use `DB_DATABASE=dormate_laravel` in step 3.

The SQL contains the original fictional demo records from your PHP ZIP. It does not contain a live export of later changes made on your computer. It creates tables in the database you select and does not switch to the original `dormate` database.

For a completely empty database instead, import **database\schema.sql** into a new empty database. Import only ONE SQL file. After step 4, run `php artisan dormate:create-admin` to create the first Super Admin. That account can create a Dorm Admin and dorm; the Dorm Admin can then add rooms and approve residents.

## 3. Create and configure .env

In the new project's terminal run:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
```

Open **.env** in VS Code. For your existing database, use:

```dotenv
APP_NAME=DorMate
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dormate
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

For option B, change `DB_DATABASE` to `dormate_laravel`. If your MySQL root account has a password, enter it in `DB_PASSWORD`. Save with **Ctrl+S**.

## 4. Start Laravel

Stop any previous Laravel server using port 8000 with **Ctrl+C** in its terminal. Otherwise you could keep seeing the welcome page from your earlier project.

Run these commands one at a time in the NEW project's terminal:

```powershell
php artisan key:generate
php artisan config:clear
php artisan dormate:adopt-existing
php artisan migrate
php artisan serve
```

- `key:generate` is needed once for this newly extracted project's blank APP_KEY. Keep that key on later runs.
- `dormate:adopt-existing` checks DorMate's tables and columns and registers Laravel's baseline migration. It adds migration metadata and retains business records. If it reports missing tables/columns, check the database selected in `.env` before proceeding.
- `migrate` applies the mobile sign-in upgrade after adoption. It keeps existing business records.
- Do not run `migrate:fresh` or import SQL over a populated existing database.

Open **http://127.0.0.1:8000/** for DorMate's landing page, or **http://127.0.0.1:8000/login** for sign-in. Leave the terminal running. “Press Ctrl+C to stop the server” means it is ready.

For option A, use your existing role, email and password. The original password hashes are supported.

Only if you imported the demo SQL in option B, these accounts use **Dormate123!**:

| Select role | Email |
| --- | --- |
| Super Admin | super@dormate.test |
| Dorm Admin | admin@dormate.test |
| Maintenance Staff | staff@dormate.test |
| User (Resident) | resident@dormate.test |

These are fictional local demo credentials. Change them before using the application beyond a local demonstration. `php artisan dormate:create-admin` can create a new Super Admin with an email/password you choose; it does not replace existing accounts.

For later runs, start XAMPP MySQL, open the same folder and run only:

```powershell
php artisan serve
```

## 5. How this follows your instructor's steps 2–5

| Instructor's step | This project |
| --- | --- |
| 2. Create/open a Laravel project | The ZIP already contains Laravel. Open the folder containing artisan; another create-project command is unnecessary. |
| 3. Configure the database | `.env` connects to XAMPP MySQL. SQL files support fresh imports. |
| 4. Open the host | Run `php artisan serve` and open `http://127.0.0.1:8000`. |
| 5. Explore Laravel using your study | DorMate uses routes, controllers, Eloquent models, Blade views, authentication, sessions, forms and MySQL. |

To demonstrate a model/controller flow, open `routes/web.php`, `app/Http/Controllers/RoomController.php`, `app/Models/Room.php` and `resources/views/dormate/rooms.blade.php`. The route calls the controller, the model queries room data, and the Blade view displays it.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Could not open input file: artisan | Open the inner folder containing artisan. Verify with `Get-ChildItem artisan`. |
| Laravel welcome page still appears | Stop the old server, start this project's server and use its printed URL/port. |
| Connect DorMate to MySQL | Start XAMPP MySQL, check DB settings in `.env`, then run `php artisan config:clear`. |
| Missing tables/columns | Check the selected database. For a new empty database, import one supplied SQL file. For an existing database, inspect the differences without overwriting its records. |
| Access denied for user root | Put the actual MySQL username/password in `.env` and clear config. |
| Could not find driver | Enable `extension=pdo_mysql` once in the CLI PHP configuration, then restart the terminal. `php --ini` shows the active file. |
| Missing application encryption key | Run `php artisan key:generate` once in this newly configured project. |
| 419 Page Expired | Refresh and resubmit. Use the same hostname consistently: 127.0.0.1 or localhost. |
| Missing receipt/photo | Copy the old `storage/uploads` contents to `storage/app/private/uploads`. |
| Port 8000 already in use | Stop the other server or use `php artisan serve --port=8001`. |

You do not need to edit `httpd.conf` for Laravel's development server. If you later serve this through Apache, its document root must point to Laravel's **public** folder.

Password-reset messages are saved under `storage/app/private/mail` for local development. Real delivery requires SMTP settings and `DORMATE_MAIL_TRANSPORT=mail`.
