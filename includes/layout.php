<?php

function nav_items(): array
{
    return [
        'Overview' => [
            ['dashboard', 'Dashboard', 'squares-2x2', 'dashboard.php'],
        ],
        'Money' => [
            ['transactions', 'Transactions', 'arrows-right-left', 'transactions.php'],
            ['accounts', 'Accounts', 'wallet', 'accounts.php'],
            ['categories', 'Categories', 'tag', 'categories.php'],
        ],
        'Planning' => [
            ['budgets', 'Budgets', 'chart-pie', 'budgets.php'],
            ['goals', 'Savings goals', 'flag', 'goals.php'],
            ['debts', 'Debts & loans', 'users', 'debts.php'],
        ],
        'Insights' => [
            ['reports', 'Reports', 'presentation-chart-line', 'reports.php'],
            ['advisor', 'Advisor', 'light-bulb', 'advisor.php'],
            ['decisions', 'Decision helper', 'calculator', 'decisions.php'],
        ],
    ];
}

function page_start(string $title, string $active, string $subtitle = ''): void
{
    $user = require_login();
    $flashes = take_flashes();
    $fullTitle = $title . ' | ' . APP_NAME;

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($fullTitle) . '</title>';
    echo '<script>try{var t=localStorage.getItem("theme");if(t)document.documentElement.setAttribute("data-theme",t)}catch(e){}</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="assets/css/app.css?v=' . filemtime(ROOT . '/assets/css/app.css') . '">';
    echo '</head><body class="app">';
    echo '<div class="scrim" data-sidebar-close></div>';
    echo '<aside class="sidebar" id="sidebar"><div class="sidebar-head"><span class="brand">' . e(APP_NAME) . '</span>';
    echo '<button class="icon-btn sidebar-close" type="button" data-sidebar-close aria-label="Close menu">' . icon('x-mark') . '</button></div><nav class="nav">';

    foreach (nav_items() as $group => $items) {
        echo '<div class="nav-group" aria-label="' . e($group) . '">';
        foreach ($items as [$key, $label, $ico, $href]) {
            echo '<a class="nav-link' . ($key === $active ? ' is-active' : '') . '" href="' . e($href) . '">' . icon($ico) . '<span>' . e($label) . '</span></a>';
        }
        echo '</div>';
    }

    echo '</nav><div class="sidebar-foot">';
    echo '<a class="nav-link' . ($active === 'profile' ? ' is-active' : '') . '" href="profile.php">' . icon('cog-6-tooth') . '<span>Settings</span></a>';
    echo '<form method="post" action="logout.php">' . csrf_field() . '<button class="nav-link nav-button" type="submit">' . icon('arrow-right-on-rectangle') . '<span>Sign out</span></button></form>';
    echo '</div></aside>';

    echo '<div class="shell"><header class="topbar">';
    echo '<button class="icon-btn menu-btn" type="button" data-sidebar-open aria-label="Open menu">' . icon('bars-3') . '</button>';
    echo '<span class="topbar-section">' . e(APP_NAME) . '</span>';
    echo '<form class="search" method="get" action="transactions.php" role="search">' . icon('magnifying-glass', 'icon-sm') . '<input type="search" name="q" placeholder="Search transactions" aria-label="Search transactions"></form>';
    echo '<div class="topbar-actions">';
    echo '<a class="btn btn-primary" href="transaction.php">' . icon('plus', 'icon-sm') . '<span>Add transaction</span></a>';
    echo '<button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle theme"><span class="theme-sun">' . icon('sun') . '</span><span class="theme-moon">' . icon('moon') . '</span></button>';
    echo '<div class="menu"><button class="avatar-btn" type="button" data-menu-toggle aria-label="Account menu"><span class="avatar">' . e(initials($user['name'])) . '</span><span class="avatar-name">' . e($user['name']) . '</span>' . icon('chevron-down', 'icon-sm') . '</button>';
    echo '<div class="menu-panel"><div class="menu-user"><strong>' . e($user['name']) . '</strong><span>' . e($user['email']) . '</span></div>';
    echo '<a href="profile.php">' . icon('user-circle') . 'Profile & settings</a>';
    echo '<form method="post" action="logout.php">' . csrf_field() . '<button type="submit">' . icon('arrow-right-on-rectangle') . 'Sign out</button></form></div></div>';
    echo '</div></header><main class="content">';
    echo '<div class="page-head"><h1>' . e($title) . '</h1>';
    echo '<p>' . e($subtitle !== '' ? $subtitle : (new DateTimeImmutable())->format('l, d F Y')) . '</p></div>';

    if ($flashes) {
        echo '<div class="flashes">';
        foreach ($flashes as $f) {
            $ico = $f['type'] === 'error' ? 'exclamation-circle' : ($f['type'] === 'success' ? 'check-circle' : 'information-circle');
            echo '<div class="flash flash-' . e($f['type']) . '" role="status">' . icon($ico) . '<span>' . e($f['message']) . '</span><button type="button" class="flash-close" aria-label="Dismiss">' . icon('x-mark', 'icon-sm') . '</button></div>';
        }
        echo '</div>';
    }
}

function page_end(): void
{
    echo '</main></div>';
    echo '<dialog class="dialog confirm" id="confirmDialog"><form method="dialog"><div class="confirm-icon">' . icon('exclamation-triangle') . '</div><h3 id="confirmTitle">Are you sure?</h3><p id="confirmText"></p><div class="dialog-actions"><button class="btn btn-ghost" value="cancel">Cancel</button><button class="btn btn-danger" value="ok">Delete</button></div></form></dialog>';
    echo '<script src="assets/js/app.js?v=' . filemtime(ROOT . '/assets/js/app.js') . '"></script></body></html>';
}

function dialog_start(string $id, string $title): void
{
    echo '<dialog class="dialog" id="' . e($id) . '"><form method="post" novalidate>' . csrf_field();
    echo '<div class="dialog-head"><h2 data-dialog-title>' . e($title) . '</h2><button type="button" class="icon-btn sm" data-close-dialog aria-label="Close">' . icon('x-mark') . '</button></div>';
    echo '<div class="dialog-body">';
}

function dialog_end(string $submitLabel): void
{
    echo '</div><div class="dialog-actions"><button type="button" class="btn btn-ghost" data-close-dialog>Cancel</button><button type="submit" class="btn btn-primary">' . e($submitLabel) . '</button></div></form></dialog>';
}

function swatch_picker(string $name, string $selected = ''): string
{
    $html = '<div class="swatches">';

    foreach (palette() as $i => $color) {
        $html .= '<label><input type="radio" name="' . e($name) . '" value="' . e($color) . '"' . (($selected === $color || ($selected === '' && $i === 0)) ? ' checked' : '') . '><span style="--c:' . e($color) . '"></span></label>';
    }

    return $html . '</div>';
}

function amount_input(string $id, string $name, string $label, string $placeholder = '0'): string
{
    return '<div class="field"><label for="' . e($id) . '">' . e($label) . '</label><div class="input-prefix"><span>' . e(current_currency()) . '</span><input class="input" id="' . e($id) . '" name="' . e($name) . '" inputmode="decimal" autocomplete="off" placeholder="' . e($placeholder) . '"></div></div>';
}

function empty_state(string $iconName, string $title, string $text, string $actionLabel = '', string $actionHref = ''): string
{
    $html = '<div class="empty">' . icon($iconName, 'icon-lg') . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p>';

    if ($actionLabel !== '') {
        $html .= '<a class="btn btn-primary" href="' . e($actionHref) . '">' . icon('plus') . e($actionLabel) . '</a>';
    }

    return $html . '</div>';
}

function txn_row(array $t): string
{
    $isTransfer = $t['type'] === 'transfer';
    $title = $t['reason'] !== '' ? $t['reason'] : ($isTransfer ? 'Transfer' : ($t['category_name'] ?? 'Uncategorised'));
    $meta = [];

    if ($isTransfer) {
        $meta[] = $t['account_name'] . ' to ' . ($t['to_account_name'] ?? '');
    } else {
        if ($t['party'] !== '') {
            $meta[] = $t['party'];
        }
        $meta[] = $t['category_name'] ?? 'Uncategorised';
        $meta[] = $t['account_name'];
    }

    $sign = $t['type'] === 'income' ? '+' : ($t['type'] === 'expense' ? '-' : '');
    $ico = $t['type'] === 'income' ? 'arrow-down-circle' : ($t['type'] === 'expense' ? 'arrow-up-circle' : 'arrows-right-left');

    return '<li class="txn"><span class="txn-icon txn-' . e($t['type']) . '">' . icon($ico) . '</span>'
        . '<div class="txn-body"><strong>' . e($title) . '</strong><span>' . e(implode(' · ', $meta)) . '</span></div>'
        . '<div class="txn-side"><strong class="amt amt-' . e($t['type']) . '">' . $sign . e(money($t['amount'])) . '</strong><span>' . e(relative_day($t['txn_date'])) . '</span></div></li>';
}
