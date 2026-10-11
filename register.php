<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth_layout.php';

require_guest();

$errors = [];
$old = ['name' => '', 'email' => '', 'currency' => 'RWF'];

if (is_post()) {
    csrf_verify();
    $old['name'] = post('name');
    $old['email'] = mb_strtolower(post('email'));
    $old['currency'] = post('currency', 'RWF');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (mb_strlen($old['name']) < 2 || mb_strlen($old['name']) > 120) {
        $errors[] = 'Enter your full name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!isset(currencies()[$old['currency']])) {
        $errors[] = 'Choose a currency from the list.';
    }
    if (!is_string($password) || strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'The password needs at least 8 characters, including a letter and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }
    if (!$errors && db_val('SELECT id FROM users WHERE email = ?', [$old['email']])) {
        $errors[] = 'An account with this email already exists.';
    }

    if (!$errors) {
        $userId = db_transaction(function () use ($old, $password) {
            $id = db_insert(
                'INSERT INTO users (name, email, password_hash, currency) VALUES (?, ?, ?, ?)',
                [$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT), $old['currency']]
            );
            seed_user_defaults($id);

            return $id;
        });

        login_user($userId);
        flash('success', 'Your account is ready. Start by recording your opening balances in Accounts.');
        redirect('dashboard.php');
    }
}

auth_start('Create account');
?>
<h1>Create your account</h1>
<p class="sub">It takes a minute and you can start recording right away.</p>

<?php if ($errors): ?>
    <div class="flash flash-error" style="margin-bottom:18px"><?= icon('exclamation-circle') ?><span><?= e(implode(' ', $errors)) ?></span></div>
<?php endif; ?>

<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="name">Full name</label>
        <input class="input" type="text" id="name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required autofocus>
    </div>
    <div class="field">
        <label for="email">Email address</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" placeholder="you@example.com" required>
    </div>
    <div class="field">
        <label for="currency">Currency</label>
        <select class="input" id="currency" name="currency">
            <?php foreach (currencies() as $code => $meta): ?>
                <option value="<?= e($code) ?>"<?= selected($old['currency'], $code) ?>><?= e($code . ' - ' . $meta['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?= password_field('password', 'Password', 'new-password', true) ?>
    <?= password_field('password_confirm', 'Confirm password', 'new-password') ?>
    <button class="btn btn-primary btn-block" type="submit" style="height:48px">Create account</button>
</form>

<p class="auth-switch">Already have an account? <a href="login.php">Sign in</a></p>
<?php auth_end();
