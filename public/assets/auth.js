/* Progressive enhancement: validation is also enforced by Laravel. */
(() => {
    const signup = document.getElementById('auth-signup_method');
    const method = signup || document.getElementById('auth-login_method');
    const email = document.getElementById('auth-email');
    const mobile = document.getElementById('auth-mobile_number');
    if (!method || !email || !mobile) return;
    const update = () => {
        const byMobile = method.value === 'mobile';
        email.required = !byMobile;
        mobile.required = byMobile;
        email.closest('.auth-control').hidden = !signup && byMobile;
        mobile.closest('.auth-control').hidden = !signup && !byMobile;
        email.disabled = !signup && byMobile;
        mobile.disabled = !signup && !byMobile;
        document.querySelector('label[for="auth-email"]').textContent =
            signup && byMobile ? 'Recovery email (optional)' : 'Email address';
        document.querySelector('label[for="auth-mobile_number"]').textContent =
            signup && !byMobile ? 'Mobile number (optional)' : 'Mobile number';
    };
    method.addEventListener('change', update);
    update();
})();
