<?php

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function currencies(): array
{
    return [
        'RWF' => ['name' => 'Rwandan Franc', 'decimals' => 0],
        'USD' => ['name' => 'US Dollar', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'decimals' => 2],
        'GBP' => ['name' => 'British Pound', 'decimals' => 2],
        'KES' => ['name' => 'Kenyan Shilling', 'decimals' => 2],
        'UGX' => ['name' => 'Ugandan Shilling', 'decimals' => 0],
        'TZS' => ['name' => 'Tanzanian Shilling', 'decimals' => 0],
        'BIF' => ['name' => 'Burundian Franc', 'decimals' => 0],
        'CDF' => ['name' => 'Congolese Franc', 'decimals' => 0],
        'ZAR' => ['name' => 'South African Rand', 'decimals' => 2],
        'NGN' => ['name' => 'Nigerian Naira', 'decimals' => 2],
        'GHS' => ['name' => 'Ghanaian Cedi', 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'decimals' => 2],
        'AED' => ['name' => 'UAE Dirham', 'decimals' => 2],
    ];
}

function current_currency(): string
{
    $user = auth_user();

    return $user['currency'] ?? 'RWF';
}

function money($amount, bool $signed = false): string
{
    $code = current_currency();
    $decimals = currencies()[$code]['decimals'] ?? 2;
    $amount = (float) $amount;
    $formatted = number_format(abs($amount), $decimals, '.', ',');
    $prefix = $amount < 0 ? '-' : ($signed && $amount > 0 ? '+' : '');

    return $prefix . $code . "\u{00A0}" . $formatted;
}

function plain_number($amount): string
{
    $decimals = currencies()[current_currency()]['decimals'] ?? 2;

    return number_format((float) $amount, $decimals, '.', ',');
}

function compact_number($value): string
{
    $value = (float) $value;
    $abs = abs($value);
    $sign = $value < 0 ? '-' : '';

    if ($abs >= 1000000000) {
        return $sign . round($abs / 1000000000, 1) . 'B';
    }
    if ($abs >= 1000000) {
        return $sign . round($abs / 1000000, 1) . 'M';
    }
    if ($abs >= 1000) {
        return $sign . round($abs / 1000, 1) . 'k';
    }

    return $sign . round($abs);
}

function percent($value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals) . '%';
}

function parse_amount(string $raw): ?float
{
    $clean = preg_replace('/[\s,]/', '', $raw);

    if ($clean === '' || !is_numeric($clean)) {
        return null;
    }

    $value = round((float) $clean, 2);

    return $value >= 0 && $value < 1000000000000 ? $value : null;
}

function valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);

    return $parsed && $parsed->format('Y-m-d') === $date;
}

function fmt_date(?string $date, bool $withYear = true): string
{
    if (!$date) {
        return '';
    }

    return (new DateTimeImmutable($date))->format($withYear ? 'd M Y' : 'd M');
}

function month_name(string $ym): string
{
    return (new DateTimeImmutable($ym . '-01'))->format('M Y');
}

function days_until(string $date): int
{
    $today = new DateTimeImmutable('today');

    return (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');
}

function relative_day(string $date): string
{
    $diff = days_until($date);

    if ($diff === 0) {
        return 'Today';
    }
    if ($diff === -1) {
        return 'Yesterday';
    }
    if ($diff === 1) {
        return 'Tomorrow';
    }

    return fmt_date($date, abs($diff) > 300);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $items;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['_token'] ?? '';

    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

function icon(string $name, string $class = ''): string
{
    static $paths = null;

    if ($paths === null) {
        $paths = require __DIR__ . '/icon_paths.php';
    }

    $inner = $paths[$name] ?? '';

    return '<svg class="icon ' . e($class) . '" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">' . $inner . '</svg>';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $letters = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters ?: 'U';
}

function selected($current, $value): string
{
    return (string) $current === (string) $value ? ' selected' : '';
}

function checked($current, $value): string
{
    return (string) $current === (string) $value ? ' checked' : '';
}

function palette(): array
{
    return ['#0d9488', '#2563eb', '#7c3aed', '#db2777', '#ea580c', '#ca8a04', '#16a34a', '#0891b2', '#dc2626', '#4f46e5', '#64748b', '#be185d'];
}

function valid_color(string $color, string $fallback = '#64748b'): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $fallback;
}

function account_types(): array
{
    return [
        'cash' => 'Cash',
        'bank' => 'Bank account',
        'mobile' => 'Mobile money',
        'savings' => 'Savings',
        'other' => 'Other',
    ];
}

function account_icon(string $type): string
{
    return [
        'cash' => 'banknotes',
        'bank' => 'building-library',
        'mobile' => 'device-phone-mobile',
        'savings' => 'wallet',
        'other' => 'credit-card',
    ][$type] ?? 'wallet';
}

function necessity_labels(): array
{
    return ['need' => 'Need', 'want' => 'Want', 'saving' => 'Saving'];
}

function period_range(string $preset, string $from = '', string $to = ''): array
{
    $today = new DateTimeImmutable('today');

    switch ($preset) {
        case 'last_month':
            $start = $today->modify('first day of last month');
            $end = $today->modify('last day of last month');
            break;
        case '3_months':
            $start = $today->modify('first day of this month')->modify('-2 months');
            $end = $today;
            break;
        case '6_months':
            $start = $today->modify('first day of this month')->modify('-5 months');
            $end = $today;
            break;
        case 'year':
            $start = new DateTimeImmutable($today->format('Y') . '-01-01');
            $end = $today;
            break;
        case 'custom':
            if (valid_date($from) && valid_date($to) && $from <= $to) {
                return [$from, $to];
            }
            $start = $today->modify('first day of this month');
            $end = $today;
            break;
        default:
            $start = $today->modify('first day of this month');
            $end = $today;
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function month_bounds(int $offset = 0): array
{
    $base = (new DateTimeImmutable('first day of this month'))->modify(($offset >= 0 ? '+' : '') . $offset . ' months');

    return [$base->format('Y-m-d'), $base->modify('last day of this month')->format('Y-m-d')];
}

function default_categories(): array
{
    return [
        ['Salary', 'income', '#0d9488'],
        ['Business', 'income', '#2563eb'],
        ['Freelance', 'income', '#7c3aed'],
        ['Gifts received', 'income', '#db2777'],
        ['Interest & returns', 'income', '#ca8a04'],
        ['Other income', 'income', '#64748b'],
        ['Food & groceries', 'expense', '#ea580c'],
        ['Rent & housing', 'expense', '#4f46e5'],
        ['Transport', 'expense', '#0891b2'],
        ['Utilities', 'expense', '#ca8a04'],
        ['Airtime & internet', 'expense', '#2563eb'],
        ['Health', 'expense', '#dc2626'],
        ['Education', 'expense', '#7c3aed'],
        ['Entertainment', 'expense', '#db2777'],
        ['Shopping', 'expense', '#be185d'],
        ['Family support', 'expense', '#16a34a'],
        ['Savings & investment', 'expense', '#0d9488'],
        ['Other expenses', 'expense', '#64748b'],
    ];
}

function seed_user_defaults(int $userId): void
{
    foreach (default_categories() as [$name, $type, $color]) {
        db_exec('INSERT IGNORE INTO categories (user_id, name, type, color) VALUES (?, ?, ?, ?)', [$userId, $name, $type, $color]);
    }

    $accounts = [['Cash', 'cash', '#16a34a'], ['Mobile money', 'mobile', '#ca8a04'], ['Bank account', 'bank', '#2563eb']];
    foreach ($accounts as [$name, $type, $color]) {
        db_exec('INSERT INTO accounts (user_id, name, type, opening_balance, color) VALUES (?, ?, ?, 0, ?)', [$userId, $name, $type, $color]);
    }
}
