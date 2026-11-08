<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DorMateTest extends TestCase
{
    private array $users = [];

    private int $dorm;

    private int $room;

    private int $otherDorm;

    private const PASSWORD = 'Test-password-42';

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'dormate_laravel_test') {
            throw new \RuntimeException('Tests require the separate dormate_laravel_test MySQL database.');
        }
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'mobile_number')) {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        }
        DB::beginTransaction();
        $this->dorm = DB::table('dorms')->insertGetId(['name' => 'Test DorMate', 'address' => 'Test address']);
        $this->otherDorm = DB::table('dorms')->insertGetId(['name' => 'Other dorm']);
        $this->room = DB::table('rooms')->insertGetId(['dorm_id' => $this->dorm, 'room_number' => 'A101', 'capacity' => 4, 'monthly_rent' => 2000]);
        foreach (['super' => 'SUPER_ADMIN', 'admin' => 'DORM_ADMIN', 'resident' => 'RESIDENT', 'staff' => 'MAINTENANCE_STAFF', 'pending' => 'RESIDENT', 'other' => 'DORM_ADMIN'] as $name => $role) {
            $this->users[$name] = User::create([
                'name' => 'Test '.$name, 'email' => $name.'@example.test',
                'password_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
                'role' => $role, 'status' => $name === 'pending' ? 'pending' : 'active',
                'dorm_id' => $name === 'super' ? null : ($name === 'other' ? $this->otherDorm : $this->dorm),
                'room_id' => $name === 'resident' ? $this->room : null,
            ]);
        }
        DB::table('dorms')->where('id', $this->dorm)->update(['owner_id' => $this->users['admin']->id]);
        DB::table('dorms')->where('id', $this->otherDorm)->update(['owner_id' => $this->users['other']->id]);
    }

    protected function tearDown(): void
    {
        if ($this->app) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function asUser(string $name): self
    {
        $user = $this->users[$name]->refresh();
        $this->actingAs($user)->withSession(['sv' => $user->session_version]);

        return $this;
    }

    private function submit(string $url, string $action, array $data = [])
    {
        return $this->withSession(['_token' => 'test-token'])->post($url, ['action' => $action, '_token' => 'test-token'] + $data);
    }

    private function bill(): int
    {
        return DB::table('bills')->insertGetId(['dorm_id' => $this->dorm, 'resident_id' => $this->users['resident']->id, 'room_id' => $this->room, 'billing_month' => date('Y-m-01'), 'room_rent' => 2000, 'total_amount' => 2000, 'due_date' => date('Y-m-d', strtotime('+7 days'))]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    public function test_public_pages_and_guest_protection(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/reset-password'] as $path) {
            $this->get($path)->assertOk()->assertSee('DorMate');
        }
        $this->get('/')->assertDontSee('Laravel has an incredibly rich ecosystem');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/media?kind=receipt&id=1')->assertRedirect('/login');
        $this->get('/index.php?page=login')->assertRedirect('/login');
    }

    public function test_existing_password_hash_authenticates_and_logout_works(): void
    {
        $this->submit('/login', 'login', ['email' => 'resident@example.test', 'password' => self::PASSWORD, 'role' => 'RESIDENT'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->users['resident']);
        $this->get('/dashboard')->assertOk()->assertSee('Test resident');
        $this->submit('/dashboard', 'logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_login_and_role_do_not_authenticate(): void
    {
        $this->submit('/login', 'login', ['email' => 'resident@example.test', 'password' => self::PASSWORD, 'role' => 'SUPER_ADMIN'])->assertStatus(400);
        $this->assertGuest();
        $this->assertDatabaseCount('login_attempts', 1);
    }

    public function test_role_dashboards_and_allowed_pages_render(): void
    {
        $pages = [
            'super' => ['/dashboard', '/users', '/accounts/create', '/team/edit?id='.$this->users['admin']->id, '/announcements', '/announcements/edit', '/profile', '/password'],
            'admin' => ['/dashboard', '/residents', '/residents/view?id='.$this->users['resident']->id, '/staff-requests', '/rooms', '/rooms/edit', '/accounts/create', '/bills', '/bills/create', '/maintenance', '/announcements', '/announcements/edit', '/settings', '/profile', '/password'],
            'resident' => ['/dashboard', '/rooms', '/bills', '/maintenance', '/maintenance/create', '/announcements', '/profile', '/password', '/contact'],
            'staff' => ['/dashboard', '/maintenance', '/announcements', '/profile', '/password'],
        ];
        foreach ($pages as $name => $paths) {
            $this->asUser($name);
            foreach ($paths as $path) {
                $this->get($path)->assertOk();
            }
        }
        $this->asUser('resident')->get('/users')->assertForbidden();
        $this->asUser('pending')->get('/dashboard')->assertOk()->assertSee('Awaiting approval');
    }

    public function test_registration_is_pending_and_admin_approval_assigns_room(): void
    {
        $this->submit('/register', 'register', ['name' => 'New Resident', 'email' => 'new@example.test', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD, 'role' => 'RESIDENT', 'dorm_id' => $this->dorm])->assertRedirect('/pending');
        $new = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('pending', $new->status);
        $this->get('/dashboard')->assertOk()->assertSee('Awaiting approval');
        $this->asUser('admin')->submit('/residents', 'approve_resident', ['id' => $new->id, 'room_id' => $this->room])->assertRedirect('/residents');
        $this->assertDatabaseHas('users', ['id' => $new->id, 'status' => 'active', 'room_id' => $this->room]);
    }

    public function test_public_signup_cannot_create_an_administrator(): void
    {
        $this->submit('/register', 'register', ['role' => 'SUPER_ADMIN'])->assertStatus(400);
        $this->assertGuest();
    }

    public function test_room_changes_are_scoped_to_admin_dorm(): void
    {
        $this->asUser('admin')->submit('/rooms/edit', 'save_room', ['room_number' => 'A102', 'capacity' => 2, 'monthly_rent' => '2500'])->assertRedirect('/rooms');
        $this->assertDatabaseHas('rooms', ['room_number' => 'A102', 'dorm_id' => $this->dorm]);
        $this->asUser('other')->submit('/rooms', 'save_room', ['id' => $this->room, 'room_number' => 'HACK', 'capacity' => 2, 'monthly_rent' => '1'])->assertStatus(400);
        $this->assertDatabaseHas('rooms', ['id' => $this->room, 'room_number' => 'A101']);
        $this->get('/rooms')->assertDontSee('A101');
    }

    public function test_bill_generation_receipt_and_verification(): void
    {
        Storage::fake('local');
        $this->asUser('admin')->submit('/bills/create', 'generate_bill', ['resident_id' => $this->users['resident']->id, 'billing_month' => date('Y-m'), 'due_date' => date('Y-m-d', strtotime('+7 days')), 'room_rent' => '2000', 'electricity' => '100', 'water' => '50', 'wifi' => '150'])->assertRedirect('/bills');
        $bill = DB::table('bills')->first();
        $this->assertEquals('2300.00', $bill->total_amount);
        $this->asUser('resident')->get('/bills/view?id='.$bill->id)->assertOk();
        $this->submit('/bills/view?id='.$bill->id, 'submit_payment', ['id' => $bill->id, 'reference_number' => 'GCASH123456', 'receipt' => $this->image()])->assertRedirect('/bills/view?id='.$bill->id);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'status' => 'pending']);
        Storage::disk('local')->assertExists('uploads/'.DB::table('bills')->value('receipt_path'));
        $this->get('/media?kind=receipt&id='.$bill->id)->assertOk()->assertHeader('content-type', 'image/png');
        $this->asUser('other')->get('/bills/view?id='.$bill->id)->assertForbidden();
        $this->get('/media?kind=receipt&id='.$bill->id)->assertNotFound();
        $this->asUser('admin')->submit('/bills/view?id='.$bill->id, 'verify_payment', ['id' => $bill->id])->assertRedirect('/bills/view?id='.$bill->id);
        $this->assertDatabaseHas('bills', ['id' => $bill->id, 'status' => 'paid', 'paid_amount' => '2300.00']);
    }

    public function test_maintenance_submission_assignment_and_completion(): void
    {
        $this->asUser('resident')->submit('/maintenance/create', 'submit_report', ['title' => 'Leaking faucet', 'description' => 'Kitchen faucet needs repair.'])->assertRedirect('/maintenance');
        $report = DB::table('maintenance_reports')->first();
        $this->get('/maintenance/view?id='.$report->id)->assertOk()->assertSee('Leaking faucet');
        $this->asUser('staff')->get('/maintenance/view?id='.$report->id)->assertForbidden();
        $this->asUser('admin')->submit('/maintenance/view?id='.$report->id, 'assign_staff', ['id' => $report->id, 'staff_id' => $this->users['staff']->id])->assertRedirect('/maintenance/view?id='.$report->id);
        $this->asUser('staff')->get('/maintenance/view?id='.$report->id)->assertOk();
        $this->submit('/maintenance/view?id='.$report->id, 'update_report', ['id' => $report->id])->assertRedirect('/maintenance/view?id='.$report->id);
        $this->assertDatabaseHas('maintenance_reports', ['id' => $report->id, 'status' => 'RESOLVED']);
    }

    public function test_announcements_keep_dorm_scope_and_escape_content(): void
    {
        $this->asUser('admin')->submit('/announcements/edit', 'save_post', ['title' => 'Dorm notice', 'body' => '<script>alert(1)</script>'])->assertRedirect('/announcements');
        $this->asUser('resident')->get('/announcements')->assertOk()->assertSee('Dorm notice')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false);
        $this->asUser('other')->get('/announcements')->assertOk()->assertDontSee('Dorm notice');
    }

    public function test_password_change_invalidates_old_session_and_preserves_current_one(): void
    {
        $this->asUser('resident')->submit('/password', 'password', ['current_password' => self::PASSWORD, 'password' => 'New-password-123', 'password_confirm' => 'New-password-123'])->assertRedirect('/profile');
        $this->get('/profile')->assertOk();
        $this->assertTrue(password_verify('New-password-123', $this->users['resident']->refresh()->password_hash));
        $this->withSession(['sv' => 1])->get('/dashboard')->assertRedirect('/login');
    }

    public function test_theme_toggle_sets_cookie_and_returns_to_page(): void
    {
        $this->submit('/login', 'set_theme', ['theme' => 'dark'])->assertRedirect('/login')->assertCookie('dormate_theme', 'dark');
    }

    public function test_password_reset_is_single_use_and_updates_existing_hash(): void
    {
        config(['dormate.mail_transport' => 'mail']);
        Mail::fake();
        $this->submit('/forgot-password', 'forgot', ['email' => 'resident@example.test'])->assertRedirect('/login');
        $this->assertDatabaseHas('password_resets', ['user_id' => $this->users['resident']->id]);
        DB::table('password_resets')->where('user_id', $this->users['resident']->id)->update(['token_hash' => hash('sha256', str_repeat('a', 64))]);
        $data = ['token' => str_repeat('a', 64), 'password' => 'Reset-password-123', 'password_confirm' => 'Reset-password-123'];
        $this->submit('/reset-password', 'reset', $data)->assertRedirect('/login');
        $this->assertTrue(password_verify($data['password'], $this->users['resident']->refresh()->password_hash));
        $this->submit('/reset-password', 'reset', $data)->assertStatus(400)->assertSee('already been used');
    }

    public function test_super_admin_can_create_dorm_admin_and_staff(): void
    {
        $this->asUser('super')->submit('/accounts/create', 'create_account', ['role' => 'DORM_ADMIN', 'name' => 'New owner', 'email' => 'owner@example.test', 'password' => self::PASSWORD, 'dorm_name' => 'New dorm'])->assertRedirect('/users');
        $owner = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertDatabaseHas('dorms', ['id' => $owner->dorm_id, 'owner_id' => $owner->id, 'name' => 'New dorm']);
        $this->submit('/accounts/create?role=MAINTENANCE_STAFF', 'create_account', ['role' => 'MAINTENANCE_STAFF', 'name' => 'New staff', 'email' => 'newstaff@example.test', 'password' => self::PASSWORD, 'dorm_id' => $owner->dorm_id])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'newstaff@example.test', 'role' => 'MAINTENANCE_STAFF', 'status' => 'active']);
    }

    public function test_create_admin_command_uses_legacy_password_column(): void
    {
        $this->artisan('dormate:create-admin', ['--name' => 'First owner', '--email' => 'first@example.test'])
            ->expectsQuestion('Password (8 to 72 characters)', self::PASSWORD)
            ->expectsQuestion('Confirm password', self::PASSWORD)->assertSuccessful();
        $created = User::where('email', 'first@example.test')->firstOrFail();
        $this->assertSame('SUPER_ADMIN', $created->role);
        $this->assertTrue(password_verify(self::PASSWORD, $created->password_hash));
    }

    public function test_laravel_csrf_rejects_missing_token(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['action' => 'login'])->assertStatus(419);
    }

    public function test_existing_schema_adoption_does_not_change_business_records(): void
    {
        $before = DB::table('users')->orderBy('id')->get()->toJson();
        $this->artisan('dormate:adopt-existing')->assertSuccessful();
        $this->assertSame($before, DB::table('users')->orderBy('id')->get()->toJson());
    }

    public function test_landing_only_promotes_the_three_public_roles(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Super Admin')->assertDontSee('explore-super', false)->assertSee('/ 03');
        $this->get('/login')->assertOk()->assertSee('Super Admin')->assertSee('Mobile Number &amp; Password', false);
        $this->get('/register')->assertOk()->assertSee('Mobile Number &amp; Password', false);
    }

    public function test_mobile_registration_preserves_approval_and_supports_both_number_formats(): void
    {
        $this->submit('/register', 'register', [
            'name' => 'Mobile resident', 'signup_method' => 'mobile', 'mobile_number' => '0917 123 4567',
            'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD, 'role' => 'RESIDENT', 'dorm_id' => $this->dorm,
        ])->assertRedirect('/pending');
        $new = User::where('mobile_number', '+639171234567')->firstOrFail();
        $this->assertNull($new->email);
        $this->assertSame('pending', $new->status);
        $this->assertSame('+639171234567', $new->phone);
        $this->asUser('admin')->get('/residents?q=639171234567')->assertOk()->assertSee('Mobile resident');
        $this->submit('/residents', 'approve_resident', ['id' => $new->id, 'room_id' => $this->room])->assertRedirect('/residents');
        $this->submit('/dashboard', 'logout');
        foreach (['09171234567', '+63 917 123 4567', '639171234567'] as $number) {
            $this->submit('/login', 'login', ['login_method' => 'mobile', 'mobile_number' => $number, 'role' => 'RESIDENT', 'password' => self::PASSWORD])->assertRedirect('/dashboard');
            $this->assertAuthenticatedAs($new);
            $this->get('/profile')->assertOk()->assertSee('+639171234567');
            $this->submit('/profile', 'profile', ['name' => 'Mobile resident', 'phone' => '09991234567'])->assertRedirect('/profile');
            $this->assertDatabaseHas('users', ['id' => $new->id, 'mobile_number' => '+639171234567', 'phone' => '09991234567']);
            $this->submit('/dashboard', 'logout');
        }
    }

    public function test_mobile_staff_can_register_without_email_and_be_reviewed(): void
    {
        $this->submit('/register', 'register', [
            'name' => 'Mobile staff', 'signup_method' => 'mobile', 'mobile_number' => '09181234567',
            'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD, 'role' => 'MAINTENANCE_STAFF', 'dorm_id' => $this->dorm,
        ])->assertRedirect('/pending');
        $staff = User::where('mobile_number', '+639181234567')->firstOrFail();
        $this->asUser('admin')->get('/staff-requests')->assertOk()->assertSee('+639181234567');
        $this->asUser('super')->get('/team/edit?id='.$staff->id)->assertOk()->assertSee('+639181234567');
        $this->get('/users?q=639181234567')->assertOk()->assertSee('Mobile staff');
        $this->asUser('admin')->submit('/staff-requests', 'review_staff', ['id' => $staff->id, 'decision' => 'approve'])->assertRedirect('/staff-requests');
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'status' => 'active']);
    }

    public function test_registration_rejects_invalid_methods_mobile_numbers_and_duplicate_identities(): void
    {
        $base = ['name' => 'Phone user', 'signup_method' => 'mobile', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD, 'role' => 'RESIDENT', 'dorm_id' => $this->dorm];
        foreach (['', '1234', '+12025550123', '0917123456', '091712345678', '0917abc4567'] as $bad) {
            $this->submit('/register', 'register', $base + ['mobile_number' => $bad])->assertStatus(400);
            $this->assertGuest();
        }
        $this->submit('/register', 'register', array_replace($base, ['signup_method' => 'sms', 'mobile_number' => '09171234567']))->assertStatus(400);
        $this->users['resident']->update(['mobile_number' => '+639171234567']);
        $this->submit('/register', 'register', $base + ['mobile_number' => '0917-123-4567'])->assertStatus(400)->assertSee('already registered');
        $this->submit('/register', 'register', $base + ['mobile_number' => '09181234567', 'email' => 'resident@example.test'])->assertStatus(400)->assertSee('already registered');
        $this->assertDatabaseCount('users', 6);
        $this->assertGuest();
    }

    public function test_mobile_login_requires_correct_password_role_and_active_or_pending_account(): void
    {
        $user = $this->users['resident'];
        $user->update(['mobile_number' => '+639171234567']);
        $base = ['login_method' => 'mobile', 'mobile_number' => '09171234567', 'role' => 'RESIDENT', 'password' => self::PASSWORD];
        $this->submit('/login', 'login', array_replace($base, ['password' => 'wrong']))->assertStatus(400);
        $this->submit('/login', 'login', array_replace($base, ['role' => 'SUPER_ADMIN']))->assertStatus(400);
        $user->update(['status' => 'disabled']);
        $this->submit('/login', 'login', $base)->assertStatus(400);
        $user->update(['status' => 'rejected']);
        $this->submit('/login', 'login', $base)->assertStatus(400);
        $this->assertGuest();
        $user->update(['status' => 'pending']);
        $this->submit('/login', 'login', $base)->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('Awaiting approval');
    }

    public function test_email_and_mobile_share_login_throttle_and_contact_number_is_not_a_login(): void
    {
        $this->users['resident']->update(['mobile_number' => '+639171234567', 'phone' => '09991234567']);
        for ($i = 0; $i < 3; $i++) {
            $this->submit('/login', 'login', ['email' => 'resident@example.test', 'password' => 'wrong', 'role' => 'RESIDENT'])->assertStatus(400);
        }
        for ($i = 0; $i < 2; $i++) {
            $this->submit('/login', 'login', ['login_method' => 'mobile', 'mobile_number' => '09171234567', 'password' => 'wrong', 'role' => 'RESIDENT'])->assertStatus(400);
        }
        $this->submit('/login', 'login', ['login_method' => 'mobile', 'mobile_number' => '+639171234567', 'password' => self::PASSWORD, 'role' => 'RESIDENT'])->assertStatus(400)->assertSee('Too many attempts');
        $this->submit('/login', 'login', ['login_method' => 'mobile', 'mobile_number' => '09991234567', 'password' => self::PASSWORD, 'role' => 'RESIDENT'])->assertStatus(400);
        $this->assertGuest();
    }

    public function test_mobile_signup_recovery_email_supports_existing_password_reset_flow(): void
    {
        config(['dormate.mail_transport' => 'mail']);
        Mail::fake();
        $this->submit('/register', 'register', ['name' => 'Mobile recovery', 'signup_method' => 'mobile', 'mobile_number' => '09171234567', 'email' => 'recovery@example.test', 'password' => self::PASSWORD, 'password_confirm' => self::PASSWORD, 'role' => 'RESIDENT', 'dorm_id' => $this->dorm])->assertRedirect('/pending');
        $new = User::where('mobile_number', '+639171234567')->firstOrFail();
        $this->submit('/pending', 'logout');
        $this->submit('/forgot-password', 'forgot', ['email' => 'recovery@example.test'])->assertRedirect('/login');
        DB::table('password_resets')->where('user_id', $new->id)->update(['token_hash' => hash('sha256', str_repeat('b', 64))]);
        $this->submit('/reset-password', 'reset', ['token' => str_repeat('b', 64), 'password' => 'New-phone-password-42', 'password_confirm' => 'New-phone-password-42'])->assertRedirect('/login');
        $this->submit('/login', 'login', ['login_method' => 'mobile', 'mobile_number' => '09171234567', 'password' => 'New-phone-password-42', 'role' => 'RESIDENT'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($new);
    }

    public function test_announcements_are_private_in_lists_dashboard_and_read_actions(): void
    {
        $own = DB::table('announcements')->insertGetId(['dorm_id' => $this->dorm, 'posted_by' => $this->users['admin']->id, 'title' => 'Own dorm update', 'body' => 'Own body']);
        $foreign = DB::table('announcements')->insertGetId(['dorm_id' => $this->otherDorm, 'posted_by' => $this->users['other']->id, 'title' => 'Other dorm secret', 'body' => 'Foreign body']);
        $global = DB::table('announcements')->insertGetId(['dorm_id' => null, 'posted_by' => $this->users['super']->id, 'title' => 'Global private notice', 'body' => 'Global body']);
        $mismatched = DB::table('announcements')->insertGetId(['dorm_id' => $this->dorm, 'posted_by' => $this->users['other']->id, 'title' => 'Mismatched author', 'body' => 'Bad legacy data']);
        foreach (['resident', 'staff', 'admin'] as $name) {
            $this->asUser($name);
            foreach (['/announcements', '/dashboard'] as $path) {
                $this->get($path)->assertOk()->assertSee('Own dorm update')->assertDontSee('Other dorm secret')->assertDontSee('Global private notice')->assertDontSee('Mismatched author');
            }
            $this->assertCount(1, dm_get_posts($this->users[$name]->getAttributes()));
            foreach ([$foreign, $global, $mismatched] as $id) {
                $this->submit('/announcements', 'read_post', ['id' => $id])->assertStatus(400);
                $this->assertDatabaseMissing('announcement_reads', ['announcement_id' => $id, 'user_id' => $this->users[$name]->id]);
            }
            $this->submit('/announcements', 'read_post', ['id' => $own])->assertRedirect('/announcements');
        }
        $this->asUser('other')->get('/announcements')->assertOk()->assertSee('Other dorm secret')->assertDontSee('Own dorm update')->assertDontSee('Global private notice')->assertDontSee('Mismatched author');
        $this->get('/announcements/edit?id='.$own)->assertForbidden();
        $this->submit('/announcements/edit', 'save_post', ['id' => $own, 'title' => 'Changed', 'body' => 'Bad'])->assertStatus(400);
        $this->submit('/announcements', 'delete_post', ['id' => $own])->assertStatus(400);
        $this->get('/announcements/edit?id='.$mismatched)->assertForbidden();
        $this->asUser('super')->get('/announcements')->assertOk()->assertSee('Own dorm update')->assertSee('Other dorm secret')->assertSee('Global private notice');
        $this->submit('/announcements', 'read_post', ['id' => 99999999])->assertStatus(400);
    }

    public function test_unassigned_accounts_cannot_read_global_or_dorm_posts(): void
    {
        $id = DB::table('announcements')->insertGetId(['dorm_id' => null, 'posted_by' => $this->users['super']->id, 'title' => 'Private global', 'body' => 'Private']);
        $this->users['staff']->update(['dorm_id' => null]);
        $this->asUser('staff')->get('/announcements')->assertOk()->assertDontSee('Private global');
        $this->submit('/announcements', 'read_post', ['id' => $id])->assertStatus(400);
        $this->assertCount(0, dm_get_posts($this->users['staff']->refresh()->getAttributes()));
        $this->users['admin']->update(['dorm_id' => null]);
        $this->asUser('admin')->submit('/announcements/edit', 'save_post', ['title' => 'Bad post', 'body' => 'No dorm'])->assertStatus(400);
        $this->assertDatabaseMissing('announcements', ['title' => 'Bad post']);
    }
}
