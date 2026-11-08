<?php
$register = $page === 'register';
$forgot = $page === 'forgot';
$reset = $page === 'reset';
$login = $page === 'login';
$method = request()->input($register ? 'signup_method' : 'login_method', 'email');
$mobile = $method === 'mobile';
$title = $register ? 'Create account' : ($forgot ? 'Forgot password?' : ($reset ? 'Reset password' : 'Sign in to your account'));
dm_head($register ? 'Create account' : 'Welcome to DorMate');
?>
<main id="main" class="auth-screen">
    <section class="auth-card <?= $register ? 'auth-card-register' : '' ?>">
        <div class="auth-toolbar">
            <a class="auth-back" href="<?= dm_url($login ? 'home' : 'login') ?>"><span aria-hidden="true">←</span> <?= $login ? 'Back to home' : 'Back to sign in' ?></a>
            <?= dm_theme_toggle() ?>
        </div>
        <div class="auth-brand">
            <img src="<?= asset('assets/dormate-logo.webp') ?>" width="112" height="112" alt="DorMate">
            <h1><?= dm_esc($title) ?></h1>
            <p><?= $register ? 'Join your dorm community.' : ($forgot ? 'We’ll help you get back into your account.' : ($reset ? 'Choose a new password for your account.' : 'Your dorm, your people, together.')) ?></p>
        </div>
        <?php
        dm_notices();
        dm_begin_form($page, $page, $reset ? ['token' => request()->query('token') ?? ''] : []);
        if ($login) {
            dm_auth_select('role', 'Select role', ['RESIDENT' => 'User (Resident)', 'MAINTENANCE_STAFF' => 'Maintenance Staff', 'DORM_ADMIN' => 'Dorm Admin', 'SUPER_ADMIN' => 'Super Admin'], (string) request()->input('role', 'RESIDENT'));
        }
        if ($register) {
            dm_auth_field('name', 'Full name', (string) request()->input('name', ''), 'text', 'user', 'required maxlength="100" autocomplete="name"');
        }
        if ($register || $login) {
            dm_auth_select($register ? 'signup_method' : 'login_method', $register ? 'Sign-up method' : 'Sign-in method', ['email' => 'Email Address & Password', 'mobile' => 'Mobile Number & Password'], $mobile ? 'mobile' : 'email', 'mail');
        }
        if (! $reset) {
            // Both fields remain usable without JavaScript; the server enforces the chosen method.
            dm_auth_field('email', $register && $mobile ? 'Recovery email (optional)' : 'Email address', (string) request()->input('email', ''), 'email', 'mail', 'maxlength="190" autocomplete="email"'.($forgot ? ' required' : ''));
        }
        if ($register || $login) {
            dm_auth_field('mobile_number', $register && ! $mobile ? 'Mobile number (optional)' : 'Mobile number', (string) request()->input('mobile_number', ''), 'tel', 'user', 'maxlength="30" autocomplete="tel" inputmode="tel" aria-describedby="auth-method-help"');
            echo '<p id="auth-method-help" class="auth-help">'.($register
                ? 'Choose email or mobile sign-up. Mobile format: 09171234567 or +639171234567. Add a recovery email if you want password-reset links. Mobile sign-up uses a password; no SMS code is sent.'
                : 'Enter the email or mobile number you registered with, according to your selected sign-in method.').'</p>';
        }
        if (! $forgot) {
            dm_auth_field('password', $reset ? 'New password' : 'Password', '', 'password', 'lock', ($register || $reset ? 'minlength="8" maxlength="72" autocomplete="new-password"' : 'autocomplete="current-password"').' required');
        }
        if ($register || $reset) {
            dm_auth_field('password_confirm', 'Confirm password', '', 'password', 'lock', 'required minlength="8" maxlength="72" autocomplete="new-password"');
        }
        if ($reset) {
            dm_hidden('token', request()->query('token') ?? request()->input('token') ?? '');
        }
        if ($register) {
            dm_auth_select('role', 'Select role', DORMATE_PUBLIC_SIGNUP_ROLES, (string) request()->input('role', 'RESIDENT'));
            $options = ['' => 'Choose your managing dorm admin'];
            foreach (dm_all("SELECT d.id,d.name,u.name admin_name FROM dorms d JOIN users u ON u.id=d.owner_id WHERE u.status='active' AND u.role='DORM_ADMIN' ORDER BY u.name,d.name") as $d) {
                $options[$d['id']] = $d['admin_name'].' · '.$d['name'];
            }
            dm_auth_select('dorm_id', 'Select managing dorm admin', $options, (string) request()->input('dorm_id', ''), 'users');
            echo '<details class="auth-extra"><summary>Additional details (optional)</summary><div class="pt-3">';
            dm_auth_field('requested_room', 'Preferred room (residents only)', (string) request()->input('requested_room', ''), 'text', 'room', 'maxlength="20"');
            echo '</div></details><p class="auth-help">Your selected dorm administrator will review your account before you can access the workspace.</p>';
        }
        if ($forgot) {
            echo '<p class="auth-help">Enter your registered email or recovery email. Mobile-only accounts without a recovery email cannot receive an email reset link.</p>';
        }
        if ($login) {
            echo '<div class="auth-forgot"><a href="'.dm_url('forgot').'">Forgot password?</a></div>';
        }
        dm_submit_button($register ? 'Create account' : ($forgot ? 'Send reset link' : ($reset ? 'Reset password' : 'Sign in')));
        echo '</form>';
        if ($login) {
            echo '<div class="auth-divider"><span>WELCOME TO DORMATE</span></div><p class="auth-switch">Don’t have an account? <a href="'.dm_url('register').'">Register</a></p>';
        } elseif ($register) {
            echo '<p class="auth-switch">Already have an account? <a href="'.dm_url('login').'">Sign in</a></p><p class="auth-admin-note">Dorm Admin accounts are created by Super Admin.</p>';
        }
        ?>
    </section>
    <p class="auth-copyright">DorMate · A better place to call home.</p>
</main>
<?php if ($register || $login): ?>
<script src="<?= asset('assets/auth.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
<?php /**PATH C:\Users\USER\DorMate_Web_Laravel\DorMate-Laravel\resources\views/dormate/auth.blade.php ENDPATH**/ ?>