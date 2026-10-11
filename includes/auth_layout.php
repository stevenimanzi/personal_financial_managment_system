<?php

function auth_start(string $title): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title . ' | ' . APP_NAME) . '</title>';
    echo '<script>try{var t=localStorage.getItem("theme");if(t)document.documentElement.setAttribute("data-theme",t)}catch(e){}</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="assets/css/app.css?v=' . filemtime(ROOT . '/assets/css/app.css') . '">';
    echo '</head><body>';
    echo '<div class="auth"><section class="auth-hero"><span class="auth-word">' . e(APP_NAME) . '</span>';
    echo '<div><h2>Know exactly where your <em>money</em> goes.</h2>';
    echo '<p class="lead">Record what you receive and what you spend, understand the reason behind each movement and make calmer financial decisions.</p>';
    echo '<ul class="auth-points">';
    echo '<li>' . icon('clipboard-document-list') . '<span>Log every income and expense together with the reason for it.</span></li>';
    echo '<li>' . icon('presentation-chart-line') . '<span>Reports that reveal patterns in your spending and saving.</span></li>';
    echo '<li>' . icon('light-bulb') . '<span>Straight advice and an affordability check before you spend.</span></li>';
    echo '</ul>';
    echo '<div class="auth-cards" aria-hidden="true">';
    echo '<div class="glass glass-a"><small>Net balance</small><b>RWF 1,248,500</b></div>';
    echo '<div class="glass glass-b"><small>Savings rate</small><b>28%</b><span class="tag-up">on target</span></div>';
    echo '<div class="glass glass-c"><small>Last 6 months</small><div class="mini-bars"><i style="height:45%"></i><i style="height:62%"></i><i style="height:50%"></i><i style="height:78%"></i><i style="height:66%"></i><i style="height:92%"></i></div></div>';
    echo '</div></div>';
    echo '<span class="auth-foot">Your records stay private to your account.</span></section>';
    echo '<section class="auth-panel"><div class="auth-form"><span class="auth-mobile-brand">' . e(APP_NAME) . '</span>';
}

function auth_end(): void
{
    echo '</div></section></div><script src="assets/js/app.js?v=' . filemtime(ROOT . '/assets/js/app.js') . '"></script></body></html>';
}

function password_field(string $name, string $label, string $autocomplete, bool $strength = false): string
{
    $html = '<div class="field"><label for="' . e($name) . '">' . e($label) . '</label><div class="input-group">';
    $html .= '<input class="input" type="password" id="' . e($name) . '" name="' . e($name) . '" autocomplete="' . e($autocomplete) . '" required' . ($strength ? ' data-strength-input minlength="8"' : '') . '>';
    $html .= '<button type="button" data-toggle-password aria-label="Show password"><span class="eye-on">' . icon('eye') . '</span><span class="eye-off" style="display:none">' . icon('eye-slash') . '</span></button></div>';

    if ($strength) {
        $html .= '<div class="strength" data-strength data-level="0"><i></i><i></i><i></i><i></i></div><small>Use at least 8 characters with letters and numbers.</small>';
    }

    return $html . '</div>';
}
