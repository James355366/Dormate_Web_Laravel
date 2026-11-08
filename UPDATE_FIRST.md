# Update your existing DorMate project

This update removes the decorative colored card/side stripes, adds mobile-number-and-password registration/sign-in, removes Super Admin from the public landing page, and isolates each dorm's announcements.

## Keep your existing database and accounts

1. Stop the running Laravel server with **Ctrl+C**.
2. Back up your existing project folder and export the **dormate** database using phpMyAdmin → Export → Quick → SQL.
3. Extract this ZIP. Open its **DorMate-Laravel** folder.
4. Copy the **contents** of that folder into your existing project folder:
   `C:\Users\USER\DorMate_Web_Laravel\DorMate-Laravel`
   Merge folders and replace matching code files. Keep your existing `.env` and `storage\app\private\uploads`. This package does not include an `.env`, so your MySQL settings and application key remain yours. Copy into the existing folder; do not create another nested DorMate-Laravel folder.
5. Start **MySQL** in XAMPP. In VS Code, open the folder containing `artisan`, then run these commands one at a time:

```powershell
cd "C:\Users\USER\DorMate_Web_Laravel\DorMate-Laravel"
Get-ChildItem artisan
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate
php artisan serve
```

The migration adds a nullable, unique `users.mobile_number` column and allows an empty email for mobile-only registrations. Existing accounts, passwords, contact numbers, dorms, bills, rooms, uploads and announcements remain intact. It does not enroll old contact numbers as login credentials because a contact number may be shared or belong to someone else.

If the migration reports that existing DorMate tables need to be adopted, run `php artisan dormate:adopt-existing`, then `php artisan migrate`. Do not run `migrate:fresh`, delete the database, reimport the SQL files, or generate a new APP_KEY for this existing project.

6. Open the URL printed by `php artisan serve`, normally **http://127.0.0.1:8000/**. Refresh using **Ctrl+F5** to load the new CSS and form script. Keep the server terminal running.

If you use a new extraction folder instead, first copy your current `.env` and the entire `storage\app\private\uploads` folder into it. Run the same commands from that folder. For a brand-new installation, see `START_HERE.md`.

## Mobile sign-up and sign-in

- Registration offers **Email Address & Password** or **Mobile Number & Password** for Residents and Maintenance Staff.
- Philippine mobile formats `09171234567`, `639171234567` and `+639171234567` refer to the same number. Spaces and hyphens are accepted. Duplicate mobile identities are blocked.
- With mobile sign-up, email is optional. Add a recovery email to receive password-reset links. This is password-based sign-in; no SMS code or number-ownership verification is sent.
- Registration still requires selecting a managing dorm administrator and waiting for approval.
- Existing accounts still sign in with their email and password. An existing contact number is not automatically a mobile sign-in identity.
- Editing the contact number in My Profile does not change the mobile sign-in identity.
- The existing local-development password-reset messages are written to `storage\app\private\mail`. Real email delivery still needs SMTP configuration. Mobile-only accounts without a recovery email cannot use email password reset.

## Announcement privacy

- Dorm Admins, Residents and Maintenance Staff see only announcements assigned to their dorm and posted by that dorm's owner/administrator.
- The same rule protects the community board, dashboard previews/counts, editing, deletion and “Mark as read.” Changing a URL or submitting another announcement ID does not bypass it.
- Global announcements, including the old demonstration welcome post, are hidden from regular dorm communities. No announcements are deleted by this update.
- Super Admin retains oversight of announcements across dorms. Announcements authored by Super Admin remain visible only to Super Admin accounts.
- Super Admin is removed from the public landing page. Its existing sign-in option and administrative account remain available.

## Instructor's step 5

The project continues to use Laravel models and controllers. Announcement privacy is implemented in `app/Models/Announcement.php`, with actions in `app/Services/DormateActions.php` called by `ActionController`. Mobile registration uses the `User` model, Laravel migration, authentication/session handling and the existing approval workflow.
