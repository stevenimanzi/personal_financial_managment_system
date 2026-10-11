<?php

function auth_user(bool $refresh = false): ?array
{
    static $cached = false;

    if ($refresh) {
        $cached = false;
    }

    if ($cached !== false) {
        return $cached;
    }

    $id = $_SESSION['user_id'] ?? null;

    if (!$id) {
        return $cached = null;
    }

    $user = db_row('SELECT id, name, email, currency, emergency_months, savings_target_pct, created_at FROM users WHERE id = ?', [(int) $id]);

    if (!$user) {
        unset($_SESSION['user_id']);
    }

    return $cached = $user;
}

function require_login(): array
{
    $user = auth_user();

    if (!$user) {
        redirect('login.php');
    }

    return $user;
}

function require_guest(): void
{
    if (auth_user()) {
        redirect('dashboard.php');
    }
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function login_blocked(string $email): bool
{
    $window = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $count = (int) db_val(
        'SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= ? AND (email = ? OR ip = ?)',
        [$window, $email, client_ip()]
    );

    return $count >= LOGIN_MAX_ATTEMPTS;
}

function record_failed_login(string $email): void
{
    db_exec('INSERT INTO login_attempts (email, ip) VALUES (?, ?)', [$email, client_ip()]);
    db_exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
}

function clear_failed_logins(string $email): void
{
    db_exec('DELETE FROM login_attempts WHERE email = ? OR ip = ?', [$email, client_ip()]);
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    db_exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    auth_user(true);
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
