<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/auth_layout.php';

$user = require_login();
$uid = (int) $user['id'];
$errors = ['profile' => [], 'password' => []];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'profile') {
        $name = mb_substr(post('name'), 0, 120);
        $email = mb_strtolower(post('email'));
        $currency = post('currency');
        $emergency = (int) post('emergency_months');
        $target = (int) post('savings_target_pct');

        if (mb_strlen($name) < 2) {
            $errors['profile'][] = 'Enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['profile'][] = 'Enter a valid email address.';
        } elseif (db_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid])) {
            $errors['profile'][] = 'Another account already uses that email.';
        }
        if (!isset(currencies()[$currency])) {
            $errors['profile'][] = 'Choose a currency from the list.';
        }
        if ($emergency < 1 || $emergency > 24) {
            $errors['profile'][] = 'Emergency fund target must be between 1 and 24 months.';
        }
        if ($target < 1 || $target > 90) {
            $errors['profile'][] = 'Savings target must be between 1% and 90%.';
        }

        if (!$errors['profile']) {
            db_exec('UPDATE users SET name = ?, email = ?, currency = ?, emergency_months = ?, savings_target_pct = ? WHERE id = ?', [$name, $email, $currency, $emergency, $target, $uid]);
            flash('success', 'Your settings were saved.');
            redirect('profile.php');
        }
        $user = array_merge($user, compact('name', 'email', 'currency'), ['emergency_months' => $emergency, 'savings_target_pct' => $target]);
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $hash = db_val('SELECT password_hash FROM users WHERE id = ?', [$uid]);

        if (!is_string($current) || !password_verify($current, $hash)) {
            $errors['password'][] = 'Your current password is not correct.';
        }
        if (!is_string($new) || strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['password'][] = 'The new password needs at least 8 characters, including a letter and a number.';
        }
        if ($new !== $confirm) {
            $errors['password'][] = 'The new passwords do not match.';
        }

        if (!$errors['password']) {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Your password was changed.');
            redirect('profile.php');
        }
    }
}

page_start('Settings', 'profile', 'Your profile and preferences');
?>

<div class="grid grid-even">
    <form class="card" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="profile">
        <div class="card-head"><div><h2>Profile and preferences</h2><p>Targets here shape the advice you receive</p></div></div>
        <div class="card-body" style="display:grid;gap:16px">
            <?php if ($errors['profile']): ?><div class="flash flash-error"><?= icon('exclamation-circle') ?><span><?= e(implode(' ', $errors['profile'])) ?></span></div><?php endif; ?>
            <div class="field"><label for="name">Full name</label><input class="input" id="name" name="name" value="<?= e($user['name']) ?>" required></div>
            <div class="field"><label for="email">Email address</label><input class="input" type="email" id="email" name="email" value="<?= e($user['email']) ?>" required></div>
            <div class="field"><label for="currency">Currency</label>
                <select class="input" id="currency" name="currency">
                    <?php foreach (currencies() as $code => $meta): ?><option value="<?= e($code) ?>"<?= selected($user['currency'], $code) ?>><?= e($code . ' - ' . $meta['name']) ?></option><?php endforeach; ?>
                </select>
                <small>Changing currency only changes how amounts are displayed. Existing amounts are not converted.</small></div>
            <div class="form-grid">
                <div class="field"><label for="savings_target_pct">Savings target (% of income)</label><input class="input" type="number" min="1" max="90" id="savings_target_pct" name="savings_target_pct" value="<?= (int) $user['savings_target_pct'] ?>"></div>
                <div class="field"><label for="emergency_months">Emergency fund (months)</label><input class="input" type="number" min="1" max="24" id="emergency_months" name="emergency_months" value="<?= (int) $user['emergency_months'] ?>"></div>
            </div>
            <div><button class="btn btn-primary" type="submit"><?= icon('check') ?>Save changes</button></div>
        </div>
    </form>

    <form class="card" method="post" novalidate style="align-self:start">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <div class="card-head"><div><h2>Change password</h2><p>Use a password you do not use anywhere else</p></div></div>
        <div class="card-body" style="display:grid;gap:16px">
            <?php if ($errors['password']): ?><div class="flash flash-error"><?= icon('exclamation-circle') ?><span><?= e(implode(' ', $errors['password'])) ?></span></div><?php endif; ?>
            <?= password_field('current_password', 'Current password', 'current-password') ?>
            <?= password_field('new_password', 'New password', 'new-password', true) ?>
            <?= password_field('confirm_password', 'Confirm new password', 'new-password') ?>
            <div><button class="btn btn-primary" type="submit"><?= icon('lock-closed') ?>Update password</button></div>
        </div>
    </form>
</div>

<?php page_end();
