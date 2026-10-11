<?php

function auth_start(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title . ' | ' . APP_NAME) . '</title>';
    echo '<script>try{var t=localStorage.getItem("theme");if(t)document.documentElement.setAttribute("data-theme",t)}catch(e){}</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="assets/css/app.css?v=' . filemtime(ROOT . '/assets/css/app.css') . '">';
    echo '</head><body class="auth-page">';
    echo '<div class="auth"><section class="auth-main"><span class="auth-word">' . e(APP_NAME) . '</span><div class="auth-form">';
}

function auth_end(): void
{
    $rows = [
        ['Monthly salary', 'Today', '+850,000', 'in'],
        ['House rent', '2 days ago', '-250,000', 'out'],
        ['Weekly groceries', '3 days ago', '-40,849', 'out'],
        ['Website design job', '5 days ago', '+163,000', 'in'],
        ['Data bundle', '6 days ago', '-20,000', 'out'],
    ];
    $nav = ['Dashboard', 'Transactions', 'Accounts', 'Categories', 'Budgets', 'Savings goals', 'Debts & loans', 'Reports', 'Advisor', 'Decision helper'];

    echo '</div><p class="auth-note">Your records are private to your account.</p></section>';
    echo '<aside class="auth-art"><h2>Take control of your money</h2><p>Record what you receive and spend, and see clearly where it goes.</p>';
    echo '<div class="mock" aria-hidden="true"><div class="mock-frame"><div class="mock-screen">';
    echo '<div class="mock-side"><b>' . e(APP_NAME) . '</b>';
    foreach ($nav as $i => $item) {
        echo '<span' . ($i === 0 ? ' class="on"' : '') . '>' . e($item) . '</span>';
    }
    echo '</div><div class="mock-main"><div class="mock-head"><b>Overview</b><small>Your money this month</small></div>';
    echo '<div class="mock-kpis"><div><small>Total balance</small><b>RWF 1,539,765</b></div><div><small>Income</small><b>RWF 850,000</b></div><div><small>Spent</small><b>RWF 407,501</b></div></div>';
    echo '<div class="mock-panel"><b>Recent transactions</b>';
    foreach ($rows as [$name, $when, $amount, $dir]) {
        echo '<div class="mock-row"><span><i>' . e($name) . '</i><small>' . e($when) . '</small></span><em class="' . e($dir) . '">' . e($amount) . '</em></div>';
    }
    echo '</div></div></div></div></div></aside></div>';
    echo '<script src="assets/js/app.js?v=' . filemtime(ROOT . '/assets/js/app.js') . '"></script></body></html>';
}

function text_field(string $name, string $label, string $icon, array $attrs = []): string
{
    $html = '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label><div class="input-lead">' . icon($icon, 'icon-sm');
    $html .= '<input class="input"';

    foreach ($attrs as $key => $value) {
        $html .= $value === true ? ' ' . e($key) : ' ' . e($key) . '="' . e($value) . '"';
    }

    return $html . ' id="' . e($name) . '" name="' . e($name) . '"></div></div>';
}

function password_field(string $name, string $label, string $autocomplete, bool $strength = false): string
{
    $html = '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label><div class="input-lead input-group">' . icon('lock-closed', 'icon-sm');
    $html .= '<input class="input" type="password" id="' . e($name) . '" name="' . e($name) . '" autocomplete="' . e($autocomplete) . '" required' . ($strength ? ' data-strength-input minlength="8"' : '') . '>';
    $html .= '<button type="button" data-toggle-password aria-label="Show password"><span class="eye-on">' . icon('eye', 'icon-sm') . '</span><span class="eye-off" style="display:none">' . icon('eye-slash', 'icon-sm') . '</span></button></div>';

    if ($strength) {
        $html .= '<div class="strength" data-strength data-level="0"><i></i><i></i><i></i><i></i></div><small>Use at least 8 characters with letters and numbers.</small>';
    }

    return $html . '</div>';
}
