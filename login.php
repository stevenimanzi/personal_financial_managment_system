<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth_layout.php';

require_guest();

$error = '';
$email = '';

if (is_post()) {
    csrf_verify();
    $email = mb_strtolower(post('email'));
    $password = $_POST['password'] ?? '';

    if (login_blocked($email)) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $user = db_row('SELECT id, password_hash FROM users WHERE email = ?', [$email]);

        if ($user && is_string($password) && password_verify($password, $user['password_hash'])) {
            clear_failed_logins($email);
            login_user((int) $user['id']);
            redirect('dashboard.php');
        }

        record_failed_login($email);
        $error = 'The email or password is not correct.';
    }
}

auth_start('Sign in');
?>
<h1>Log in to your account</h1>
<p class="sub">Please enter your details</p>

<?php foreach (take_flashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= icon($f['type'] === 'error' ? 'exclamation-circle' : 'check-circle') ?><span><?= e($f['message']) ?></span></div>
<?php endforeach; ?>

<?php if ($error): ?>
    <div class="flash flash-error"><?= icon('exclamation-circle') ?><span><?= e($error) ?></span></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <?= text_field('email', 'Email', 'envelope', ['type' => 'email', 'value' => $email, 'autocomplete' => 'email', 'placeholder' => 'Enter your email', 'required' => true, 'autofocus' => true]) ?>
    <?= password_field('password', 'Password', 'current-password') ?>
    <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
</form>

<p class="auth-switch">Do not have an account? <a href="register.php">Create one</a></p>
<?php auth_end();
