<?php

declare(strict_types=1);
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

const DORMATE_PUBLIC_SIGNUP_ROLES = ['RESIDENT' => 'User (Resident)', 'MAINTENANCE_STAFF' => 'Maintenance Staff'];
const DORMATE_ROLES = ['RESIDENT' => 'Resident', 'DORM_ADMIN' => 'Dorm Admin', 'MAINTENANCE_STAFF' => 'Maintenance Staff', 'SUPER_ADMIN' => 'Super Admin'];

function dm_db(): PDO
{
    $pdo = DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

function dm_query(string $sql, array $args = []): PDOStatement
{
    $s = dm_db()->prepare($sql);
    $s->execute($args);

    return $s;
}

function dm_all(string $sql, array $args = []): array
{
    return dm_query($sql, $args)->fetchAll();
}

function dm_one(string $sql, array $args = []): ?array
{
    return dm_query($sql, $args)->fetch() ?: null;
}

function dm_esc($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dm_url(string $page = 'dashboard', array $params = []): string
{
    return route('dormate.'.$page, $params);
}

function dm_redirect(string $page = 'dashboard', array $params = []): never
{
    throw new HttpResponseException(redirect()->to(dm_url($page, $params), 303));
}

function dm_flash(string $message, string $type = 'success'): void
{
    session()->flash('notice', compact('message', 'type'));
}

function dm_csrf(): string
{
    return (string) csrf_field();
}

function dm_check_csrf(): void
{
    if (! is_string(request()->input('_token')) || ! hash_equals(session()->token(), request()->input('_token'))) {
        throw new TokenMismatchException('Your form expired. Refresh and try again.');
    }
}

function dm_current_user(): ?array
{
    $model = Auth::user();
    if (! $model) {
        return null;
    }
    if (! $model instanceof User) {
        throw new LogicException('The authenticated user must be an instance of '.User::class.'.');
    }
    $model->refresh()->load(['room', 'dorm']);
    if (in_array($model->status, ['disabled', 'rejected'], true)
        || (int) $model->session_version !== (int) session('sv')) {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return null;
    }

    return $model->getAttributes() + ['room_number' => $model->room?->room_number, 'dorm_name' => $model->dorm?->name];
}

function dm_require_user(): array
{
    $u = dm_current_user();
    if (! $u) {
        dm_redirect('login');
    }

return $u;
}

function dm_allow_roles(array $u, array $roles): void
{
    if (! in_array($u['role'], $roles, true)) {
        throw new RuntimeException('You do not have permission to perform this action.');
    }if ($u['status'] !== 'active') {
        throw new RuntimeException('Your account is waiting for approval.');
    }
}

function dm_is_admin(array $u): bool
{
    return $u['role'] === 'DORM_ADMIN';
}

function dm_scope_sql(array $u, string $alias = ''): array
{
    $p = $alias === '' ? '' : $alias.'.';
    if ($u['role'] === 'SUPER_ADMIN') {
        return ['1=1', []];
    }

return [$p.'dorm_id=?', [(int) $u['dorm_id']]];
}

function dm_bill_scope(array $u, string $alias = 'b'): array
{
    if ($u['role'] === 'RESIDENT') {
        return [$alias.'.resident_id=?', [$u['id']]];
    }if ($u['role'] === 'DORM_ADMIN') {
        return [$alias.'.dorm_id=?', [$u['dorm_id']]];
    }

return ['1=0', []];
}

function dm_report_scope(array $u, string $alias = 'm'): array
{
    if ($u['role'] === 'MAINTENANCE_STAFF') {
        return [$alias.'.assigned_staff_id=?', [$u['id']]];
    }

return dm_bill_scope($u, $alias);
}

function dm_get_bill(array $u, int $id, bool $lock = false): array
{
    [$w,$a] = dm_bill_scope($u);
    $r = dm_one("SELECT b.*,u.name resident_name,r.room_number FROM bills b JOIN users u ON u.id=b.resident_id JOIN rooms r ON r.id=b.room_id WHERE b.id=? AND $w".($lock ? ' FOR UPDATE' : ''), [$id, ...$a]);
    if (! $r) {
        throw new RuntimeException('Bill not found in your workspace.');
    }

return $r;
}

function dm_get_report(array $u, int $id, bool $lock = false): array
{
    [$w,$a] = dm_report_scope($u);
    $r = dm_one("SELECT m.*,u.name resident_name,r.room_number,s.name staff_name FROM maintenance_reports m JOIN users u ON u.id=m.resident_id JOIN rooms r ON r.id=m.room_id LEFT JOIN users s ON s.id=m.assigned_staff_id WHERE m.id=? AND $w".($lock ? ' FOR UPDATE' : ''), [$id, ...$a]);
    if (! $r) {
        throw new RuntimeException('Request not found in your workspace.');
    }

return $r;
}

function dm_text_input(string $key, int $max = 255, bool $required = true): string
{
    $v = trim((string) (request()->input($key) ?? ''));
    if (($required && $v === '') || mb_strlen($v) > $max) {
        throw new RuntimeException('Enter a valid '.str_replace('_', ' ', $key).'.');
    }

return $v;
}

function dm_amount_input(string $key): int
{
    $s = dm_text_input($key, 14);
    if (! preg_match('/^\d{1,7}(\.\d{1,2})?$/D', $s)) {
        throw new RuntimeException('Enter a valid non-negative amount for '.str_replace('_', ' ', $key).'.');
    }

return dm_cents($s);
}

function dm_cents($value): int
{
    return (int) round((float) $value * 100);
}

function dm_decimal(int $value): string
{
    return number_format($value / 100, 2, '.', '');
}

function dm_effective_cents(array $b): int
{
    if ($b['status'] === 'paid') {
        return dm_cents($b['paid_amount'] ?? $b['total_amount']);
    }$base = dm_cents($b['total_amount']);

    return $b['due_date'] < date('Y-m-d') ? (int) round($base * 1.05) : $base;
}

function dm_money($value): string
{
    return '₱'.number_format((float) $value, 2);
}

function dm_bill_state(array $b): string
{
    return $b['status'] === 'unpaid' && $b['due_date'] < date('Y-m-d') ? 'overdue' : $b['status'];
}

function dm_transaction(callable $fn)
{
    return DB::transaction($fn);
}

function dm_audit(array $u, string $action, ?int $id = null): void
{
    dm_query('INSERT INTO audit_log(actor_id,action,entity_id) VALUES(?,?,?)', [$u['id'], $action, $id]);
}

function dm_valid_email(string $email): string
{
    $e = strtolower(trim($email));
    if (! filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($e) > 190) {
        throw new RuntimeException('Enter a valid email address.');
    }

return $e;
}

function dm_new_password(): string
{
    $p = (string) (request()->input('password') ?? '');
    if (strlen($p) < 8 || strlen($p) > 72) {
        throw new RuntimeException('Use a password between 8 and 72 characters.');
    }

return $p;
}

function dm_date_input(string $key, string $format = 'Y-m-d'): string
{
    $s = dm_text_input($key, 10);
    $d = DateTimeImmutable::createFromFormat('!'.$format, $s);
    if (! $d || $d->format($format) !== $s) {
        throw new RuntimeException('Enter a valid date.');
    }

return $s;
}

function dm_occupancy(int $roomId): int
{
    return (int) dm_query("SELECT COUNT(*) FROM users WHERE room_id=? AND role='RESIDENT' AND status='active'", [$roomId])->fetchColumn();
}

function dm_lock_room(int $roomId, int $dormId, ?int $exclude = null): array
{
    $r = dm_one('SELECT * FROM rooms WHERE id=? AND dorm_id=? AND active=1 FOR UPDATE', [$roomId, $dormId]);
    if (! $r) {
        throw new RuntimeException('Choose a room in the correct dorm.');
    }$n = (int) dm_query("SELECT COUNT(*) FROM users WHERE room_id=? AND role='RESIDENT' AND status='active' AND id<>?", [$roomId, $exclude ?? 0])->fetchColumn();
    if ($n >= (int) $r['capacity']) {
        throw new RuntimeException('This room is full. Choose another room.');
    }

return $r;
}

function dm_upload_image(string $key, bool $required = false): ?string
{
    $file = request()->file($key);
    if (! $file) {
        if ($required) {
            throw new RuntimeException('Please attach an image.');
        }

        return null;
    }
    $validator = validator([$key => $file], [$key => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
    if ($validator->fails()) {
        throw new RuntimeException($validator->errors()->first());
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $extension = $extensions[$file->getMimeType()] ?? null;
    if (! $extension) {
        throw new RuntimeException('Only JPG, PNG and WebP images are accepted.');
    }
    $name = bin2hex(random_bytes(20)).'.'.$extension;
    if (! $file->storeAs('uploads', $name, 'local')) {
        throw new RuntimeException('Unable to save the image.');
    }

    return $name;
}

function dm_delete_upload(?string $path): void
{
    if ($path && preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/D', $path)) {
        Storage::disk('local')->delete('uploads/'.$path);
    }
}

function dm_icon(string $name): string
{
    $paths = ['moon' => 'M21 13a9 9 0 0 1-10-10A9 9 0 1 0 21 13Z', 'sun' => 'M12 3V1M12 23v-2M3 12H1M23 12h-2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0', 'mail' => 'M3 5h18v14H3zM3 5l9 7 9-7', 'lock' => 'M6 11h12v10H6zM8 11V7a4 4 0 0 1 8 0v4M12 15v2', 'home' => 'M3 10 12 3l9 7M5 9v12h14V9M9 21v-8h6v8', 'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z', 'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-4M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0', 'room' => 'M4 21h16M6 21V3h12v18M10 3v18M14 12h.01', 'bill' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 7h6M9 11h6M9 15h3', 'tool' => 'M14.7 6.3a5 5 0 0 0-6.4 6.4L3 18a2.12 2.12 0 0 0 3 3l5.3-5.3a5 5 0 0 0 6.4-6.4L14 13l-3-3 3.7-3.7Z', 'post' => 'M3 4h18v14H8l-5 4V4ZM7 9h10M7 13h6', 'user' => 'M20 21v-2a8 8 0 0 0-16 0v2M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0', 'settings' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1 1-3Z', 'arrow' => 'M5 12h14M13 6l6 6-6 6', 'plus' => 'M12 5v14M5 12h14', 'check' => 'm5 12 4 4L19 6', 'clock' => 'M12 7v6l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0', 'logout' => 'M9 21H3V3h6M9 12h12M16 7l5 5-5 5', 'calendar' => 'M8 2v4M16 2v4M3 10h18M3 4h18v18H3z', 'shield' => 'M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7l-9-4ZM8 12l3 3 5-6', 'wallet' => 'M3 6h18v15H3V4l15-2v4M21 11h-6v5h6M17 13.5h.01', 'heart' => 'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z'];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name] ?? $paths['grid']).'"/></svg>';
}

function dm_brand(string $page = 'dashboard'): string
{
    return '<a class="brand" href="'.dm_url($page).'"><img class="brand-logo" src="'.asset('assets/dormate-logo.webp').'" width="42" height="42" alt="">Dor<span>Mate</span></a>';
}

function dm_avatar(array $u, string $class = ''): string
{
    $initials = implode('', array_map(fn ($n) => mb_substr($n, 0, 1), array_slice(explode(' ', $u['name']), 0, 2)));

    return '<span class="avatar '.dm_esc($class).'">'.(! empty($u['avatar_path']) ? '<img alt="" src="'.route('media').'?kind=avatar&amp;id='.(int) $u['id'].'">' : dm_esc($initials)).'</span>';
}

function dm_badge(string $state): string
{
    $map = ['active' => ['Active', 'green'], 'pending' => ['Awaiting approval', 'amber'], 'disabled' => ['Disabled', 'red'], 'rejected' => ['Declined', 'red'], 'unpaid' => ['Unpaid', 'red'], 'overdue' => ['Overdue', 'red'], 'paid' => ['Paid', 'green'], 'verification' => ['For verification', 'purple'], 'payment_rejected' => ['Payment rejected', 'red'], 'PENDING' => ['Pending', 'amber'], 'IN_PROGRESS' => ['In progress', 'blue'], 'RESOLVED' => ['Resolved', 'green']];
    [$label,$color] = $map[$state] ?? [$state, 'blue'];

    return '<span class="status '.dm_esc($color).'">'.dm_esc($label).'</span>';
}

function dm_bill_badge(array $b): string
{
    $s = dm_bill_state($b);

    return dm_badge($s === 'pending' ? 'verification' : ($s === 'rejected' ? 'payment_rejected' : $s));
}

function dm_nav_items(array $u): array
{
    $base = ['dashboard' => ['Overview', 'grid']];
    if ($u['role'] === 'SUPER_ADMIN') {
        return $base + ['users' => ['Manage admins & staff', 'users'], 'announcements' => ['Announcements', 'post']];
    }if (dm_is_admin($u)) {
        $base += ['residents' => ['Residents', 'users'], 'staff_requests' => ['Staff requests', 'shield'], 'rooms' => ['Rooms & occupancy', 'room'], 'bills' => ['Bills & payments', 'bill']];
    } elseif ($u['role'] === 'RESIDENT') {
        $base += ['rooms' => ['Room directory', 'room'], 'bills' => ['My bills', 'bill']];
    }

return $base + ['maintenance' => [$u['role'] === 'MAINTENANCE_STAFF' ? 'My work queue' : 'Maintenance', 'tool'], 'announcements' => ['Announcements', 'post']];
}

function dm_head(string $title, ?string $stylesheet = null): void
{
    echo view('layouts.head', compact('title', 'stylesheet'))->render();
}

function dm_notices(): void
{
    if ($f = session()->pull('notice')) {
        echo '<div class="alert alert-'.dm_esc($f['type']).'" role="status">'.dm_esc($f['message']).'</div>';
    }
}

function dm_layout_start(array $u, string $title, string $page): void
{
    dm_head($title);
    $nav = dm_nav_items($u);
    $active = match ($page) {
        'bill','bill_form' => 'bills','report','report_form' => 'maintenance','room_form' => 'rooms','resident','account_form' => $u['role'] === 'SUPER_ADMIN' ? 'users' : 'residents','team' => 'users','post_form' => 'announcements','password' => 'profile',default => $page
    }; ?>
<aside class="sidebar"><?= dm_brand()?><div class="workspace"><span class="eyebrow<?= $u['role'] === 'RESIDENT' ? ' resident-dorm-label' : ''?>"><?= $u['role'] === 'RESIDENT' ? 'Your Dorm' : 'YOUR WORKSPACE'?></span><strong><?= dm_esc($u['dorm_name'] ?? 'Platform administration')?></strong><span class="role role-<?= strtolower($u['role'])?>"><?= dm_esc(DORMATE_ROLES[$u['role']])?></span></div><div class="nav-label">WORKSPACE</div><nav><?php foreach ($nav as $key => [$label,$ic]) { ?><a class="nav-link <?= $active === $key ? 'active' : ''?>" href="<?= dm_url($key)?>" <?= $active === $key ? 'aria-current="page"' : ''?>><?= dm_icon($ic)?><?= dm_esc($label)?></a><?php }?></nav><div class="nav-label mt-4">ACCOUNT</div><nav><a class="nav-link <?= $active === 'profile' ? 'active' : ''?>" href="<?= dm_url('profile')?>"><?= dm_icon('user')?>My profile</a><?php if (dm_is_admin($u)) { ?><a class="nav-link <?= $page === 'settings' ? 'active' : ''?>" href="<?= dm_url('settings')?>"><?= dm_icon('settings')?>Dorm settings</a><?php }?></nav><div class="sidebar-bottom"><div class="sidebar-note"><?= dm_icon('heart')?><strong>A better place to call home.</strong><span>Less paperwork. More peace of mind.</span></div><div class="person"><?= dm_avatar($u)?><div><strong><?= dm_esc($u['name'])?></strong><small><?= dm_esc(DORMATE_ROLES[$u['role']])?></small></div><form method="post" action="<?= dm_url()?>" class="ms-auto"><?= dm_csrf()?><input type="hidden" name="action" value="logout"><button class="icon-button" title="Sign out" aria-label="Sign out"><?= dm_icon('logout')?></button></form></div></div></aside>
<div class="app-shell"><header class="topbar"><div class="crumb">Workspace <span>/</span> <strong><?= dm_esc($title)?></strong></div><div class="d-flex align-items-center gap-3"><?= dm_theme_toggle()?><span class="date-label"><?= dm_icon('calendar')?><?= date('D, M j, Y')?></span><span class="role role-<?= strtolower($u['role'])?>"><?= dm_esc(DORMATE_ROLES[$u['role']])?></span><a href="<?= dm_url('profile')?>" aria-label="My profile"><?= dm_avatar($u)?></a></div></header><details class="mobile-menu"><summary><?= dm_icon('grid')?> Menu <span><?= dm_esc($title)?></span></summary><nav><?php foreach ($nav + ['profile' => ['My profile', 'user']] + (dm_is_admin($u) ? ['settings' => ['Settings', 'settings']] : []) as $key => [$label,$ic]) { ?><a href="<?= dm_url($key)?>"><?= dm_icon($ic)?><?= dm_esc($label)?></a><?php }?></nav></details><main id="main" class="main-content"><?php dm_notices();
}

function dm_layout_end(): void
{?><footer class="page-footer"><span>© <?= date('Y')?> DorMate</span><span>Your dorm, your people, together.</span></footer></main></div></body></html><?php }

function dm_heading(string $title, string $sub, string $actions = ''): void
{
    echo '<div class="page-heading"><div><h1>'.dm_esc($title).'</h1><p>'.dm_esc($sub).'</p></div><div class="heading-actions">'.$actions.'</div></div>';
}

function dm_link_button(string $label, string $page, array $params = [], string $kind = 'primary', string $ic = ''): string
{
    return '<a class="btn btn-'.dm_esc($kind).'" href="'.dm_esc(dm_url($page, $params)).'">'.($ic ? dm_icon($ic) : '').dm_esc($label).'</a>';
}

function dm_begin_form(string $action, string $page, array $params = [], bool $files = false): void
{
    echo '<form method="post" action="'.dm_esc(dm_url($page, $params)).'"'.($files ? ' enctype="multipart/form-data"' : '').'>'.dm_csrf().'<input type="hidden" name="action" value="'.dm_esc($action).'">';
}

function dm_hidden(string $name, $value): void
{
    echo '<input type="hidden" name="'.dm_esc($name).'" value="'.dm_esc($value).'">';
}

function dm_field(string $name, string $label, $value = '', string $type = 'text', string $attrs = ''): void
{
    echo '<div class="mb-3"><label class="form-label" for="f-'.dm_esc($name).'">'.dm_esc($label).'</label><input class="form-control" id="f-'.dm_esc($name).'" name="'.dm_esc($name).'" type="'.dm_esc($type).'" value="'.dm_esc($value).'" '.$attrs.'></div>';
}

function dm_select_field(string $name, string $label, array $options, $value = '', string $attrs = ''): void
{
    echo '<div class="mb-3"><label class="form-label" for="f-'.dm_esc($name).'">'.dm_esc($label).'</label><select class="form-select" id="f-'.dm_esc($name).'" name="'.dm_esc($name).'" '.$attrs.'>';
    foreach ($options as $v => $text) {
        echo '<option value="'.dm_esc($v).'"'.((string) $value === (string) $v ? ' selected' : '').'>'.dm_esc($text).'</option>';
    }echo '</select></div>';
}

function dm_textarea_field(string $name, string $label, string $value = '', int $max = 4000): void
{
    echo '<div class="mb-3"><label class="form-label" for="f-'.dm_esc($name).'">'.dm_esc($label).'</label><textarea class="form-control" id="f-'.dm_esc($name).'" name="'.dm_esc($name).'" rows="5" required maxlength="'.$max.'">'.dm_esc($value).'</textarea></div>';
}

function dm_submit_button(string $text, string $kind = 'primary'): void
{
    echo '<button type="submit" class="btn btn-'.dm_esc($kind).'">'.dm_esc($text).'</button>';
}

function dm_detail(string $label, $value): void
{
    echo '<div class="detail-row"><span>'.dm_esc($label).'</span><strong>'.dm_esc($value).'</strong></div>';
}

function dm_empty_state(string $title, string $sub, string $ic = 'grid'): void
{
    echo '<div class="empty-state">'.dm_icon($ic).'<h3>'.dm_esc($title).'</h3><p>'.dm_esc($sub).'</p></div>';
}

function dm_metric(string $label, $value, string $sub, string $ic, string $color = 'blue'): void
{
    echo '<div class="col-6 col-xl-3"><div class="metric-card"><div class="metric-top"><span>'.dm_esc($label).'</span><span class="metric-icon '.dm_esc($color).'">'.dm_icon($ic).'</span></div><div class="metric-value">'.dm_esc($value).'</div><div class="metric-foot">'.dm_esc($sub).'</div></div></div>';
}

function dm_search_filters(string $page, array $filters, string $current, string $search, string $placeholder = 'Search…'): void
{
    echo '<div class="filters"><div class="filter-pills">';
    foreach ($filters as $value => $text) {
        echo '<a class="filter-pill '.($value === $current ? 'active' : '').'" href="'.dm_esc(dm_url($page, ['filter' => $value, 'q' => $search])).'">'.dm_esc($text).'</a>';
    }echo '</div>';
    if ($placeholder === '') {
        echo '</div>';

        return;
    }echo '<form method="get" action="'.dm_url($page).'" class="filter-search"><input type="hidden" name="page" value="'.dm_esc($page).'"><input type="hidden" name="filter" value="'.dm_esc($current).'"><input class="form-control" type="search" name="q" placeholder="'.dm_esc($placeholder).'" aria-label="'.dm_esc($placeholder).'" value="'.dm_esc($search).'"><button class="btn btn-light" type="submit">Search</button></form></div>';
}

function dm_pretty_date(?string $value): string
{
    return $value ? date('M j, Y', strtotime($value)) : '—';
}

function dm_building_art(): string
{
    return '<svg class="building-art" viewBox="0 0 260 170" fill="none" aria-hidden="true"><ellipse cx="140" cy="152" rx="107" ry="9" fill="#d1e4fa"/><circle cx="199" cy="48" r="37" fill="#cae4ff"/><path d="m72 57 65-30 55 33-64 33-56-36Z" fill="#5997d1"/><path d="M78 62v73l51 25V88L78 62Z" fill="#9ec6e3"/><path d="M129 88v72l59-28V60l-59 28Z" fill="#d9ecf8"/><path d="M75 60v7l54 27 63-32v-8l-63 33-54-27Z" fill="#427db1"/><path d="m88 84 15 7v18l-15-7V84Zm0 31 15 7v18l-15-7v-18Zm23-21 11 5v18l-11-5V94Z" fill="#eef7ff"/><path d="m142 94 14-7v18l-14 7V94Zm23-12 14-7v18l-14 7V82Zm-23 38 14-7v18l-14 7v-18Zm23-12 14-7v18l-14 7v-18Z" fill="#7fa8d1"/><path d="m111 127 11 5v24l-11-5v-24Z" fill="#4e83b5"/><path d="M213 141c-16-4-14-22-2-27-4-17 12-28 23-16 11 9 3 20 0 23 10 16-2 24-21 20Z" fill="#7faeda"/><path d="M220 111v44m0-20-8-8m8-2 8-7" stroke="#5087b9" stroke-width="2"/><path d="M41 143c0-18 8-22 12-32 7 12 15 15 15 32" fill="#a7ccee"/><path d="M54 126v29" stroke="#6299c9" stroke-width="2"/></svg>';
}

function dm_auth_field(string $name, string $label, string $value = '', string $type = 'text', string $ic = 'user', string $attrs = ''): void
{
    echo '<div class="auth-control"><label for="auth-'.dm_esc($name).'">'.dm_esc($label).'</label><span class="auth-control-icon">'.dm_icon($ic).'</span><input class="form-control" id="auth-'.dm_esc($name).'" name="'.dm_esc($name).'" type="'.dm_esc($type).'" value="'.dm_esc($value).'" '.$attrs.'></div>';
}

function dm_auth_select(string $name, string $label, array $options, string $value, string $ic = 'user'): void
{
    echo '<div class="auth-control"><label for="auth-'.dm_esc($name).'">'.dm_esc($label).'</label><span class="auth-control-icon">'.dm_icon($ic).'</span><select class="form-select" id="auth-'.dm_esc($name).'" name="'.dm_esc($name).'" required>';
    foreach ($options as $key => $label) {
        echo '<option value="'.dm_esc($key).'"'.((string) $key === $value ? ' selected' : '').'>'.dm_esc($label).'</option>';
    }echo '</select></div>';
}

function dm_get_posts(array $u): array
{
    return Announcement::query()
        ->select('announcements.*', 'users.name as author_name', 'announcement_reads.read_at')
        ->join('users', 'users.id', '=', 'announcements.posted_by')
        ->leftJoin('announcement_reads', function ($join) use ($u) {
            $join->on('announcement_reads.announcement_id', '=', 'announcements.id')->where('announcement_reads.user_id', $u['id']);
        })
        ->visibleTo($u)
        ->orderByDesc('announcements.updated_at')->get()->toArray();
}

function dm_reports_table(array $rows): void
{
    if (! $rows) {
        dm_empty_state('All clear for now', 'New maintenance requests will appear here.', 'tool');

        return;
    }?><div class="table-responsive"><table class="table"><thead><tr><th>Request</th><th>Room</th><th>Status</th><th>Assigned to</th><th></th></tr></thead><tbody><?php foreach ($rows as $r) { ?><tr><td><strong><?= dm_esc($r['title'])?></strong><small class="d-block muted mt-1"><?= dm_esc($r['resident_name'])?></small></td><td><?= dm_esc($r['room_number'])?></td><td><?= dm_badge($r['status'])?></td><td><?= dm_esc($r['staff_name'] ?? 'Unassigned')?></td><td><?= dm_link_button('View', 'report', ['id' => $r['id']], 'light btn-sm')?></td></tr><?php }?></tbody></table></div><?php }

function dm_staff_review_forms(int $id, string $page, array $params = []): void
{
    echo '<div class="staff-review-actions">';
    dm_begin_form('review_staff', $page, $params);
    dm_hidden('id', $id);
    dm_hidden('decision', 'approve');
    dm_submit_button('Approve staff');
    echo '</form><details><summary>Decline registration</summary>';
    dm_begin_form('review_staff', $page, $params);
    dm_hidden('id', $id);
    dm_hidden('decision', 'reject');
    echo '<p class="small mt-2">This account will not be allowed to sign in.</p>';
    dm_submit_button('Confirm decline', 'outline-danger');
    echo '</form></details></div>';
}

function dm_current_theme(): string
{
    return request()->cookie('dormate_theme') === 'dark' ? 'dark' : 'light';
}

function dm_theme_return_url(): string
{
    return dm_url(request()->route('page') ?? 'home', request()->only(['id', 'q', 'filter', 'role', 'token']));
}

function dm_theme_toggle(): string
{
    $dark = dm_current_theme() === 'dark';
    $next = $dark ? 'light' : 'dark';

    return '<form class="theme-toggle-form" method="post" action="'.dm_esc(dm_theme_return_url()).'">'.dm_csrf().
        '<input type="hidden" name="action" value="set_theme"><input type="hidden" name="theme" value="'.$next.'">'.
        '<button class="theme-toggle" type="submit" aria-label="Switch to '.$next.' mode" title="Switch to '.$next.' mode">'.
        dm_icon($dark ? 'sun' : 'moon').'<span class="theme-toggle-label">'.($dark ? 'Light' : 'Dark').'</span></button></form>';
}
