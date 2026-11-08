<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Support\MobileNumber;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/** Existing DorMate rules, executed through Laravel's connection and session. */
class DormateActions
{
    public function handle(): void
    {

        dm_check_csrf();
        $action = (string) (request()->input('action') ?? '');
        if ($action === 'login') {
            $role = dm_text_input('role', 30);
            if (! array_key_exists($role, DORMATE_ROLES)) {
                throw new RuntimeException('Choose a valid account role.');
            }
            $method = request()->input('login_method', 'email');
            if (! in_array($method, ['email', 'mobile'], true)) {
                throw new RuntimeException('Choose a valid sign-in method.');
            }
            $identifier = $method === 'mobile'
                ? MobileNumber::normalize(dm_text_input('mobile_number', 30))
                : dm_valid_email(dm_text_input('email', 190));
            $account = User::where($method === 'mobile' ? 'mobile_number' : 'email', $identifier)->first();
            // Both identifiers share a throttle once they resolve to the same account.
            $key = hash('sha256', ($account ? 'user:'.$account->id : $method.':'.$identifier).'|'.request()->ip());
            $n = (int) dm_query('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)', [$key])->fetchColumn();
            if ($n >= 5) {
                throw new RuntimeException('Too many attempts. Please try again in 15 minutes.');
            }
            $u = $account?->getAttributes();
            if (! $u || $u['role'] !== $role || ! password_verify((string) (request()->input('password') ?? ''), $u['password_hash']) || in_array($u['status'], ['disabled', 'rejected'], true)) {
                dm_query('INSERT INTO login_attempts(attempt_key) VALUES(?)', [$key]);
                throw new RuntimeException('The selected role, sign-in details, or password is incorrect, or the account is inactive.');
            }
            dm_query('DELETE FROM login_attempts WHERE attempt_key=?', [$key]);
            Auth::login(User::findOrFail($u['id']));
            session()->regenerate();
            session()->put('sv', $u['session_version']);
            session()->regenerateToken();
            dm_redirect();
        }
        if ($action === 'register') {
            $role = dm_text_input('role', 30);
            if (! array_key_exists($role, DORMATE_PUBLIC_SIGNUP_ROLES)) {
                throw new RuntimeException('Only residents and maintenance staff can register here. Admin accounts are created by Super Admin.');
            }
            $method = request()->input('signup_method', 'email');
            if (! in_array($method, ['email', 'mobile'], true)) {
                throw new RuntimeException('Choose Email Address & Password or Mobile Number & Password.');
            }
            $name = dm_text_input('name', 100);
            $emailInput = dm_text_input('email', 190, $method === 'email');
            $email = $emailInput === '' ? null : dm_valid_email($emailInput);
            $mobileInput = dm_text_input('mobile_number', 30, $method === 'mobile');
            $mobile = $mobileInput === '' ? null : MobileNumber::normalize($mobileInput);
            $p = dm_new_password();
            if ($p !== (request()->input('password_confirm') ?? '')) {
                throw new RuntimeException('Passwords do not match.');
            }
            $dorm = (int) (request()->input('dorm_id') ?? 0);
            $room = $role === 'RESIDENT' ? dm_text_input('requested_room', 20, false) : '';
            $phone = $mobile ?? dm_text_input('phone', 20, false);
            if (! dm_one("SELECT d.id FROM dorms d JOIN users u ON u.id=d.owner_id WHERE d.id=? AND u.status='active' AND u.role='DORM_ADMIN'", [$dorm])) {
                throw new RuntimeException('Choose an available dorm.');
            }
            if ($email && dm_one('SELECT id FROM users WHERE email=?', [$email])) {
                throw new RuntimeException('This email is already registered.');
            }
            if ($mobile && dm_one('SELECT id FROM users WHERE mobile_number=?', [$mobile])) {
                throw new RuntimeException('This mobile number is already registered.');
            }
            dm_query("INSERT INTO users(name,email,mobile_number,password_hash,role,status,dorm_id,requested_room,phone) VALUES(?,?,?,?,?,'pending',?,?,?)", [$name, $email, $mobile, password_hash($p, PASSWORD_DEFAULT), $role, $dorm, $room, $phone]);
            Auth::login(User::findOrFail((int) dm_db()->lastInsertId()));
            session()->regenerate();
            session()->put('sv', 1);
            session()->regenerateToken();
            dm_redirect('pending');
        }
        if ($action === 'forgot') {
            $config = config('dormate');
            $email = dm_valid_email(dm_text_input('email', 190));
            $key = hash('sha256', 'reset|'.$email.'|'.request()->ip());
            $n = (int) dm_query('SELECT COUNT(*) FROM login_attempts WHERE attempt_key=? AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)', [$key])->fetchColumn();
            if ($n >= 3) {
                throw new RuntimeException('Please wait 15 minutes before requesting another reset link.');
            }
            dm_query('INSERT INTO login_attempts(attempt_key) VALUES(?)', [$key]);
            $u = dm_one("SELECT * FROM users WHERE email=? AND status IN ('active','pending')", [$email]);
            if ($u) {
                $token = bin2hex(random_bytes(32));
                dm_transaction(function () use ($u, $token) {
                    dm_query('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL', [$u['id']]);
                    dm_query('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))', [$u['id'], hash('sha256', $token)]);
                });
                $link = route('dormate.reset', ['token' => $token]);
                $body = "Reset your DorMate password:\n$link\n\nThis link expires in 30 minutes. If you did not request this, ignore this message.";
                if ($config['mail_transport'] === 'mail') {
                    Mail::raw($body, fn ($message) => $message->to($email)->subject('Reset your DorMate password'));
                } else {
                    $dir = storage_path('app/private/mail');
                    if (! is_dir($dir)) {
                        mkdir($dir, 0700, true);
                    }if (file_put_contents($dir.'/reset-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.txt', "To: $email\nSubject: Reset your DorMate password\n\n$body", LOCK_EX) === false) {
                        throw new RuntimeException('Unable to create the local reset message. Check storage permissions.');
                    }
                }
            }
            dm_flash('If an eligible account exists, a reset message has been prepared.');
            dm_redirect('login');
        }
        if ($action === 'reset') {
            $token = dm_text_input('token', 64);
            $p = dm_new_password();
            if ($p !== (request()->input('password_confirm') ?? '')) {
                throw new RuntimeException('Passwords do not match.');
            }
            dm_transaction(function () use ($token, $p) {
                $r = dm_one('SELECT * FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE', [hash('sha256', $token)]);
                if (! $r) {
                    throw new RuntimeException('This reset link has expired or has already been used.');
                }dm_query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?', [password_hash($p, PASSWORD_DEFAULT), $r['user_id']]);
                dm_query('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL', [$r['user_id']]);
            });
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
            dm_flash('Password updated. Sign in with your new password.');
            dm_redirect('login');
        }
        $u = dm_require_user();
        $id = (int) (request()->input('id') ?? 0);
        if ($action === 'logout') {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
            dm_redirect('login');
        }
        if ($action === 'profile') {
            $name = dm_text_input('name', 100);
            $phone = dm_text_input('phone', 20, false);
            $path = dm_upload_image('photo');
            try {
                dm_query('UPDATE users SET name=?,phone=?,avatar_path=COALESCE(?,avatar_path) WHERE id=?', [$name, $phone, $path, $u['id']]);
            } catch (Throwable $e) {
                dm_delete_upload($path);
                throw $e;
            }
            if ($path) {
                dm_delete_upload($u['avatar_path']);
            }dm_flash('Profile updated.');
            dm_redirect('profile');
        }
        if ($action === 'password') {
            if (! password_verify((string) (request()->input('current_password') ?? ''), $u['password_hash'])) {
                throw new RuntimeException('Your current password is incorrect.');
            }$p = dm_new_password();
            if ($p !== (request()->input('password_confirm') ?? '')) {
                throw new RuntimeException('Passwords do not match.');
            }
            dm_query('UPDATE users SET password_hash=?,session_version=session_version+1 WHERE id=?', [password_hash($p, PASSWORD_DEFAULT), $u['id']]);
            session()->put('sv', (int) $u['session_version'] + 1);
            session()->regenerate();
            session()->regenerateToken();
            dm_flash('Password changed. Other sessions have been signed out.');
            dm_redirect('profile');
        }
        if ($u['status'] !== 'active') {
            throw new RuntimeException('Your account is waiting for approval.');
        }
        switch ($action) {
            case 'review_staff':
                dm_allow_roles($u, ['DORM_ADMIN', 'SUPER_ADMIN']);
                $decision = dm_text_input('decision', 10);
                if (! in_array($decision, ['approve', 'reject'], true)) {
                    throw new RuntimeException('Choose a valid decision.');
                }
                dm_transaction(function () use ($u, $id, $decision) {
                    $target = dm_one("SELECT * FROM users WHERE id=? AND role='MAINTENANCE_STAFF' AND status='pending' FOR UPDATE", [$id]);
                    if (! $target || ($u['role'] === 'DORM_ADMIN' && (int) $target['dorm_id'] !== (int) $u['dorm_id'])) {
                        throw new RuntimeException('Pending staff registration not found in your workspace.');
                    }dm_query('UPDATE users SET status=?,session_version=session_version+1 WHERE id=?', [$decision === 'approve' ? 'active' : 'rejected', $id]);
                    dm_audit($u, 'staff_registration_'.$decision, $id);
                });
                dm_flash($decision === 'approve' ? 'Maintenance staff account approved. They can now sign in.' : 'Staff registration declined.');
                dm_redirect($u['role'] === 'SUPER_ADMIN' ? 'users' : 'staff_requests');
            case 'save_room':
                dm_allow_roles($u, ['DORM_ADMIN']);
                $number = dm_text_input('room_number', 20);
                $capacity = filter_var(request()->input('capacity') ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
                if (! $capacity) {
                    throw new RuntimeException('Capacity must be between 1 and 100.');
                }$rent = dm_decimal(dm_amount_input('monthly_rent'));
                dm_transaction(function () use ($id, $u, $number, $capacity, $rent) {
                    if ($id) {
                        $r = dm_one('SELECT * FROM rooms WHERE id=? AND dorm_id=? AND active=1 FOR UPDATE', [$id, $u['dorm_id']]);
                        if (! $r) {
                            throw new RuntimeException('Room not found.');
                        }if (dm_occupancy($id) > $capacity) {
                            throw new RuntimeException('Capacity cannot be lower than current occupancy.');
                        }dm_query('UPDATE rooms SET room_number=?,capacity=?,monthly_rent=? WHERE id=?', [$number, $capacity, $rent, $id]);
                    } else {
                        dm_query('INSERT INTO rooms(dorm_id,room_number,capacity,monthly_rent) VALUES(?,?,?,?)', [$u['dorm_id'], $number, $capacity, $rent]);
                    }dm_audit($u, 'room_saved', $id ?: null);
                });
                dm_flash('Room saved.');
                dm_redirect('rooms');
            case 'archive_room':
                dm_allow_roles($u, ['DORM_ADMIN']);
                dm_transaction(function () use ($u, $id) {
                    $r = dm_one('SELECT * FROM rooms WHERE id=? AND dorm_id=? FOR UPDATE', [$id, $u['dorm_id']]);
                    if (! $r) {
                        throw new RuntimeException('Room not found.');
                    }if (dm_occupancy($id)) {
                        throw new RuntimeException('Reassign active residents before archiving this room.');
                    }dm_query('UPDATE rooms SET active=0 WHERE id=?', [$id]);
                    dm_audit($u, 'room_archived', $id);
                });
                dm_flash('Room archived. Historical records are retained.');
                dm_redirect('rooms');
            case 'approve_resident':case 'assign_room':
                dm_allow_roles($u, ['DORM_ADMIN']);
                $room = (int) (request()->input('room_id') ?? 0);
                dm_transaction(function () use ($u, $id, $room) {
                    $resident = dm_one("SELECT * FROM users WHERE id=? AND dorm_id=? AND role='RESIDENT' AND status IN ('pending','active') FOR UPDATE", [$id, $u['dorm_id']]);
                    if (! $resident) {
                        throw new RuntimeException('Resident not found.');
                    }dm_lock_room($room, (int) $u['dorm_id'], $id);
                    dm_query("UPDATE users SET room_id=?,status='active' WHERE id=?", [$room, $id]);
                    dm_audit($u, 'resident_approved_or_assigned', $id);
                });
                dm_flash('Resident approved and room assignment saved.');
                dm_redirect('residents');
            case 'reject_resident':
                dm_allow_roles($u, ['DORM_ADMIN']);
                $changed = dm_query("UPDATE users SET status='rejected',session_version=session_version+1 WHERE id=? AND dorm_id=? AND role='RESIDENT' AND status='pending'", [$id, $u['dorm_id']])->rowCount();
                if (! $changed) {
                    throw new RuntimeException('Pending application not found.');
                }dm_audit($u, 'resident_rejected', $id);
                dm_flash('Application declined.');
                dm_redirect('residents');
            case 'account_status':
                dm_allow_roles($u, ['DORM_ADMIN', 'SUPER_ADMIN']);
                $new = dm_text_input('status', 10);
                if (! in_array($new, ['active', 'disabled'], true)) {
                    throw new RuntimeException('Invalid account status.');
                }
                dm_transaction(function () use ($u, $id, $new) {
                    $target = dm_one('SELECT * FROM users WHERE id=? FOR UPDATE', [$id]);
                    if (! $target || $id === $u['id'] || $target['role'] === 'SUPER_ADMIN') {
                        throw new RuntimeException('This account cannot be changed here.');
                    }if ($u['role'] === 'DORM_ADMIN' && ($target['role'] !== 'RESIDENT' || (int) $target['dorm_id'] !== (int) $u['dorm_id'])) {
                        throw new RuntimeException('This resident is outside your dorm.');
                    }if ($u['role'] === 'SUPER_ADMIN' && ! in_array($target['role'], ['DORM_ADMIN', 'MAINTENANCE_STAFF'], true)) {
                        throw new RuntimeException('Only staff and administrators can be managed here.');
                    }if ($target['status'] === 'pending' || $target['status'] === 'rejected') {
                        throw new RuntimeException('Use the resident approval workflow.');
                    }if ($new === 'active' && $target['role'] === 'RESIDENT') {
                        dm_lock_room((int) $target['room_id'], (int) $target['dorm_id'], $id);
                    }dm_query('UPDATE users SET status=?,session_version=session_version+1 WHERE id=?', [$new, $id]);
                    dm_audit($u, 'account_'.$new, $id);
                });
                dm_flash('Account status updated.');
                dm_redirect($u['role'] === 'SUPER_ADMIN' ? 'users' : 'residents');
            case 'create_account':
                dm_allow_roles($u, ['DORM_ADMIN', 'SUPER_ADMIN']);
                $role = dm_text_input('role', 30);
                $allowed = $u['role'] === 'SUPER_ADMIN' ? ['DORM_ADMIN', 'MAINTENANCE_STAFF'] : ['RESIDENT'];
                if (! in_array($role, $allowed, true)) {
                    throw new RuntimeException('Invalid role for this action.');
                }$name = dm_text_input('name', 100);
                $email = dm_valid_email(dm_text_input('email', 190));
                $p = dm_new_password();
                $phone = dm_text_input('phone', 20, false);
                dm_transaction(function () use ($u, $role, $name, $email, $p, $phone) {
                    $dorm = (int) ($u['role'] === 'DORM_ADMIN' ? $u['dorm_id'] : (request()->input('dorm_id') ?? 0));
                    $room = null;
                    if ($role === 'DORM_ADMIN') {
                        dm_query('INSERT INTO dorms(name,address) VALUES(?,?)', [dm_text_input('dorm_name', 120), dm_text_input('address', 255, false)]);
                        $dorm = (int) dm_db()->lastInsertId();
                    } elseif (! dm_one('SELECT id FROM dorms WHERE id=?', [$dorm])) {
                        throw new RuntimeException('Choose a dorm.');
                    }if ($role === 'RESIDENT') {
                        $room = (int) (request()->input('room_id') ?? 0);
                        dm_lock_room($room, $dorm);
                    }
                    dm_query("INSERT INTO users(name,email,password_hash,role,status,dorm_id,room_id,phone) VALUES(?,?,?,?,'active',?,?,?)", [$name, $email, password_hash($p, PASSWORD_DEFAULT), $role, $dorm, $room, $phone]);
                    $created = (int) dm_db()->lastInsertId();
                    if ($role === 'DORM_ADMIN') {
                        dm_query('UPDATE dorms SET owner_id=? WHERE id=?', [$created, $dorm]);
                    }dm_audit($u, 'account_created', $created);
                });
                dm_flash('Account created. Share its temporary password with the new user.');
                dm_redirect($u['role'] === 'SUPER_ADMIN' ? 'users' : 'residents');
            case 'edit_team':
                dm_allow_roles($u, ['SUPER_ADMIN']);
                $target = dm_one("SELECT * FROM users WHERE id=? AND role IN ('DORM_ADMIN','MAINTENANCE_STAFF')", [$id]);
                if (! $target) {
                    throw new RuntimeException('Team member not found.');
                }$name = dm_text_input('name', 100);
                $phone = dm_text_input('phone', 20, false);
                $dorm = (int) $target['dorm_id'];
                if ($target['role'] === 'MAINTENANCE_STAFF') {
                    $dorm = (int) (request()->input('dorm_id') ?? 0);
                    if (! dm_one('SELECT id FROM dorms WHERE id=?', [$dorm])) {
                        throw new RuntimeException('Choose a dorm.');
                    }if ($dorm !== (int) $target['dorm_id'] && (int) dm_query("SELECT COUNT(*) FROM maintenance_reports WHERE assigned_staff_id=? AND status<>'RESOLVED'", [$id])->fetchColumn() > 0) {
                        throw new RuntimeException('Resolve or reassign this staff member’s open work before moving dorms.');
                    }
                }
                dm_query('UPDATE users SET name=?,phone=?,dorm_id=? WHERE id=?', [$name, $phone, $dorm, $id]);
                dm_audit($u, 'team_profile_updated', $id);
                dm_flash('Team member updated.');
                dm_redirect('users');
            case 'generate_bill':
                dm_allow_roles($u, ['DORM_ADMIN']);
                $resident = (int) (request()->input('resident_id') ?? 0);
                $period = dm_date_input('billing_month', 'Y-m').'-01';
                $due = dm_date_input('due_date');
                $amounts = array_map('dm_amount_input', ['room_rent', 'electricity', 'water', 'wifi']);
                dm_transaction(function () use ($u, $resident, $period, $due, $amounts) {
                    $r = dm_one("SELECT * FROM users WHERE id=? AND dorm_id=? AND role='RESIDENT' AND status='active' FOR UPDATE", [$resident, $u['dorm_id']]);
                    if (! $r || ! $r['room_id']) {
                        throw new RuntimeException('Choose an active resident with an assigned room.');
                    }dm_query('INSERT INTO bills(dorm_id,resident_id,room_id,billing_month,room_rent,electricity,water,wifi,total_amount,due_date) VALUES(?,?,?,?,?,?,?,?,?,?)', [$u['dorm_id'], $resident, $r['room_id'], $period, ...array_map('dm_decimal', $amounts), dm_decimal(array_sum($amounts)), $due]);
                    dm_audit($u, 'bill_generated', (int) dm_db()->lastInsertId());
                });
                dm_flash('Bill generated.');
                dm_redirect('bills');
            case 'submit_payment':
                dm_allow_roles($u, ['RESIDENT']);
                $ref = dm_text_input('reference_number', 40);
                if (! preg_match('/^[0-9A-Za-z-]{6,40}$/D', $ref)) {
                    throw new RuntimeException('Enter a valid GCash reference number.');
                }$b = dm_get_bill($u, $id);
                if (in_array($b['status'], ['paid', 'pending'], true)) {
                    throw new RuntimeException('This payment is already submitted or paid.');
                }$path = dm_upload_image('receipt', true);
                try {
                    dm_transaction(function () use ($u, $id, $ref, $path) {
                        $b = dm_get_bill($u, $id, true);
                        if (in_array($b['status'], ['paid', 'pending'], true)) {
                            throw new RuntimeException('This payment is already submitted or paid.');
                        }dm_query("UPDATE bills SET status='pending',payment_method='GCASH',reference_number=?,receipt_path=?,submitted_at=NOW(),rejection_reason=NULL WHERE id=?", [$ref, $path, $id]);
                        dm_audit($u, 'payment_submitted', $id);
                    });
                } catch (Throwable $e) {
                    dm_delete_upload($path);
                    throw $e;
                }dm_delete_upload($b['receipt_path']);
                dm_flash('Receipt submitted for administrator verification.');
                dm_redirect('bill', ['id' => $id]);
            case 'verify_payment':case 'cash_payment':case 'reject_payment':
                dm_allow_roles($u, ['DORM_ADMIN']);
                dm_transaction(function () use ($u, $id, $action) {
                    $b = dm_get_bill($u, $id, true);
                    if ($b['status'] === 'paid') {
                        throw new RuntimeException('This bill has already been paid.');
                    }if ($action !== 'cash_payment' && $b['status'] !== 'pending') {
                        throw new RuntimeException('This bill has no payment awaiting verification.');
                    }
                    if ($action === 'reject_payment') {
                        dm_query("UPDATE bills SET status='rejected',rejection_reason=?,reviewed_by=? WHERE id=?", [dm_text_input('rejection_reason', 255), $u['id'], $id]);
                    } else {
                        dm_query("UPDATE bills SET status='paid',paid_amount=?,paid_at=NOW(),reviewed_by=?,payment_method=? WHERE id=?", [dm_decimal(dm_effective_cents($b)), $u['id'], $action === 'cash_payment' ? 'CASH' : 'GCASH', $id]);
                    }dm_audit($u, $action, $id);
                });
                dm_flash($action === 'reject_payment' ? 'Payment rejected. The resident can resubmit.' : 'Payment recorded successfully.');
                dm_redirect('bill', ['id' => $id]);
            case 'submit_report':
                dm_allow_roles($u, ['RESIDENT']);
                if (! $u['room_id'] || ! $u['dorm_id']) {
                    throw new RuntimeException('You need a room assignment before submitting a report.');
                }$title = dm_text_input('title', 120);
                $body = dm_text_input('description', 4000);
                $path = dm_upload_image('photo');
                try {
                    dm_query('INSERT INTO maintenance_reports(dorm_id,resident_id,room_id,title,description,photo_path) VALUES(?,?,?,?,?,?)', [$u['dorm_id'], $u['id'], $u['room_id'], $title, $body, $path]);
                    dm_audit($u, 'report_submitted', (int) dm_db()->lastInsertId());
                } catch (Throwable $e) {
                    dm_delete_upload($path);
                    throw $e;
                }dm_flash('Maintenance request submitted.');
                dm_redirect('maintenance');
            case 'assign_staff':
                dm_allow_roles($u, ['DORM_ADMIN']);
                $staff = (int) (request()->input('staff_id') ?? 0);
                dm_transaction(function () use ($u, $id, $staff) {
                    $r = dm_get_report($u, $id, true);
                    if ($r['status'] === 'RESOLVED') {
                        throw new RuntimeException('This request is already resolved.');
                    }$s = dm_one("SELECT * FROM users WHERE id=? AND dorm_id=? AND role='MAINTENANCE_STAFF' AND status='active' FOR UPDATE", [$staff, $u['dorm_id']]);
                    if (! $s) {
                        throw new RuntimeException('Select an active staff member in your dorm.');
                    }dm_query("UPDATE maintenance_reports SET assigned_staff_id=?,status='IN_PROGRESS',updated_at=NOW() WHERE id=?", [$staff, $id]);
                    dm_audit($u, 'staff_assigned', $id);
                });
                dm_flash('Staff assigned.');
                dm_redirect('report', ['id' => $id]);
            case 'update_report':
                dm_allow_roles($u, ['DORM_ADMIN', 'MAINTENANCE_STAFF']);
                dm_transaction(function () use ($u, $id) {
                    $r = dm_get_report($u, $id, true);
                    if ($r['status'] === 'RESOLVED') {
                        throw new RuntimeException('This request has already been resolved.');
                    }if (! $r['assigned_staff_id']) {
                        throw new RuntimeException('Assign a staff member before starting work.');
                    }$next = $r['status'] === 'PENDING' ? 'IN_PROGRESS' : 'RESOLVED';
                    dm_query('UPDATE maintenance_reports SET status=?,updated_at=NOW() WHERE id=?', [$next, $id]);
                    dm_audit($u, 'report_'.$next, $id);
                });
                dm_flash('Request status updated.');
                dm_redirect('report', ['id' => $id]);
            case 'save_post':
                dm_allow_roles($u, ['DORM_ADMIN', 'SUPER_ADMIN']);
                if ($u['role'] === 'DORM_ADMIN' && ! dm_one('SELECT id FROM dorms WHERE id=? AND owner_id=?', [$u['dorm_id'], $u['id']])) {
                    throw new RuntimeException('You can only post for the dorm you manage.');
                }
                $title = dm_text_input('title', 140);
                $body = dm_text_input('body', 10000);
                if ($id) {
                    $p = Announcement::visibleTo($u)->whereKey($id)->where('posted_by', $u['id'])->first();
                    if (! $p) {
                        throw new RuntimeException('Only the author can edit this announcement.');
                    }dm_query('UPDATE announcements SET title=?,body=?,updated_at=NOW() WHERE id=?', [$title, $body, $id]);
                } else {
                    dm_query('INSERT INTO announcements(dorm_id,posted_by,title,body) VALUES(?,?,?,?)', [$u['role'] === 'SUPER_ADMIN' ? null : $u['dorm_id'], $u['id'], $title, $body]);
                }dm_audit($u, 'announcement_saved', $id ?: null);
                dm_flash('Announcement saved.');
                dm_redirect('announcements');
            case 'delete_post':
                dm_allow_roles($u, ['DORM_ADMIN', 'SUPER_ADMIN']);
                if (! Announcement::visibleTo($u)->whereKey($id)->where('posted_by', $u['id'])->delete()) {
                    throw new RuntimeException('Only the author can remove this announcement.');
                }dm_audit($u, 'announcement_removed', $id);
                dm_flash('Announcement removed.');
                dm_redirect('announcements');
            case 'read_post':
                $p = Announcement::visibleTo($u)->whereKey($id)->first();
                if (! $p) {
                    throw new RuntimeException('Announcement not available.');
                }dm_query('INSERT INTO announcement_reads(announcement_id,user_id) VALUES(?,?) ON DUPLICATE KEY UPDATE read_at=NOW()',[$id, $u['id']]);
                dm_redirect('announcements');
            case 'dorm_settings':
                dm_allow_roles($u,['DORM_ADMIN']);
                $name = dm_text_input('dorm_name',120);
                $address = dm_text_input('address',255,false);
                $gn = dm_text_input('gcash_name',100,false);
                $num = dm_text_input('gcash_number',20,false);
                if ($num !== '' && ! preg_match('/^(09\d{9}|\+639\d{9})$/D',$num)) {
                    throw new RuntimeException('Use a valid Philippine mobile number for GCash.');
                }$old = dm_one('SELECT * FROM dorms WHERE id=?',[$u['dorm_id']]);
                $path = dm_upload_image('qr');
                try {
                    dm_query('UPDATE dorms SET name=?,address=?,gcash_name=?,gcash_number=?,gcash_qr=COALESCE(?,gcash_qr) WHERE id=? AND owner_id=?',[$name, $address, $gn, $num, $path, $u['dorm_id'], $u['id']]);
                } catch (Throwable $e) {
                    dm_delete_upload($path);
                    throw $e;
                }if ($path) {
                    dm_delete_upload($old['gcash_qr']);
                }dm_flash('Dorm and GCash settings updated.');
                dm_redirect('settings');
            default:throw new RuntimeException('Unknown action.');
        }

    }
}
