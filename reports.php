<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/charts.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

$presets = [
    'this_month' => 'This month',
    'last_month' => 'Last month',
    '3_months' => 'Last 3 months',
    '6_months' => 'Last 6 months',
    'year' => 'This year',
    'custom' => 'Custom range',
];
$preset = isset($presets[query('period')]) ? query('period') : 'this_month';
[$from, $to] = period_range($preset, query('from'), query('to'));

$start = new DateTimeImmutable($from);
$end = new DateTimeImmutable($to);
$spanDays = (int) $start->diff($end)->format('%a') + 1;

$prevEnd = $start->modify('-1 day');
$prevStart = $prevEnd->modify('-' . ($spanDays - 1) . ' days');

$totals = period_totals($uid, $from, $to);
$prevTotals = period_totals($uid, $prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d'));
$expenseCats = category_breakdown($uid, $from, $to, 'expense');
$prevCats = [];
foreach (category_breakdown($uid, $prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d'), 'expense') as $c) {
    $prevCats[(int) $c['id']] = $c['total'];
}
$incomeSources = party_breakdown($uid, $from, $to, 'income', 8);
$necessity = necessity_totals($uid, $from, $to);
$topExpenses = top_transactions($uid, $from, $to, 'expense', 5);
$topIncome = top_transactions($uid, $from, $to, 'income', 5);

$useMonthly = $spanDays > 62;
if ($useMonthly) {
    $series = monthly_totals($uid, $from, $to);
    $longRange = count($series) > 12;
    $labels = array_map(fn($ym) => (new DateTimeImmutable($ym . '-01'))->format($longRange ? 'M y' : 'M'), array_keys($series));
} else {
    $series = daily_totals($uid, $from, $to);
    $labels = array_map(fn($d) => (new DateTimeImmutable($d))->format('j M'), array_keys($series));
}

$liquid = liquid_balance(account_balances($uid));
$balance = balance_series($uid, $from, $to, $liquid);
$balanceLabels = array_map(fn($d) => (new DateTimeImmutable($d))->format('j M'), array_keys($balance));

$donutItems = array_map(fn($c) => ['label' => $c['name'], 'value' => $c['total'], 'color' => $c['color']], $expenseCats);
$avgDaily = $spanDays > 0 ? $totals['expense'] / $spanDays : 0;
$necessityTotal = $necessity['need'] + $necessity['want'] + $necessity['saving'] + $necessity['untagged'];

$rangeLabel = fmt_date($from) . ' to ' . fmt_date($to);
page_start('Reports', 'reports', $rangeLabel);
?>

<form class="card filters filters-report no-print" method="get" action="reports.php">
    <div class="field"><label for="period">Period</label>
        <select class="input" id="period" name="period" data-period-select>
            <?php foreach ($presets as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($preset, $key) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <div class="field" data-custom-range><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field" data-custom-range><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" style="height:40px" type="submit"><?= icon('funnel') ?>Apply</button></div>
    <div class="field"><label>&nbsp;</label>
        <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" style="height:40px" type="button" data-print><?= icon('printer') ?>Print</button>
            <a class="btn btn-ghost btn-sm" style="height:40px" href="transactions.php?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv"><?= icon('arrow-down-tray') ?>CSV</a>
        </div>
    </div>
</form>

<section class="grid grid-4 mt keep-2">
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Income</span><span class="stat-ico income"><?= icon('arrow-down-circle') ?></span></div>
        <div class="stat-value"><?= e(money($totals['income'])) ?></div>
        <div class="stat-note"><?= $totals['income_count'] ?> entries</div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Spending</span><span class="stat-ico expense"><?= icon('arrow-up-circle') ?></span></div>
        <div class="stat-value"><?= e(money($totals['expense'])) ?></div>
        <div class="stat-note"><?= e(money($avgDaily)) ?> per day</div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Net savings</span><span class="stat-ico info"><?= icon('banknotes') ?></span></div>
        <div class="stat-value <?= $totals['net'] < 0 ? 'amt-expense' : '' ?>"><?= e(money($totals['net'])) ?></div>
        <div class="stat-note"><?= $prevTotals['net'] != 0 || $prevTotals['income'] > 0 ? 'Previous period ' . e(money($prevTotals['net'])) : 'No earlier period to compare' ?></div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Savings rate</span><span class="stat-ico warn"><?= icon('chart-pie') ?></span></div>
        <div class="stat-value"><?= $totals['rate'] === null ? '-' : e(percent($totals['rate'])) ?></div>
        <div class="stat-note">Target <?= (int) $user['savings_target_pct'] ?>%</div>
    </article>
</section>

<section class="grid grid-even mt">
    <article class="card">
        <div class="card-head">
            <div><h2>Income vs spending</h2><p><?= $useMonthly ? 'By month' : 'By day' ?></p></div>
            <div class="legend"><span><i style="background:var(--income)"></i>Income</span><span><i style="background:var(--expense)"></i>Spending</span></div>
        </div>
        <div class="card-body">
            <?= chart_bars($labels, [
                ['name' => 'Income', 'values' => array_column($series, 'income'), 'class' => 'bar-income'],
                ['name' => 'Spending', 'values' => array_column($series, 'expense'), 'class' => 'bar-expense'],
            ], 270) ?>
        </div>
    </article>
    <article class="card">
        <div class="card-head"><div><h2>Balance over time</h2><p>Total across all accounts</p></div></div>
        <div class="card-body"><?= chart_line($balanceLabels, array_values($balance), 270) ?></div>
    </article>
</section>

<section class="grid grid-main mt">
    <article class="card">
        <div class="card-head"><div><h2>Spending by category</h2><p>Compared with the <?= $spanDays ?> days before</p></div></div>
        <div class="card-body">
            <?php if ($expenseCats): ?>
                <div class="donut-wrap" style="margin-bottom:22px">
                    <div><?= chart_donut($donutItems, 'Spent', compact_number($totals['expense'])) ?></div>
                    <div class="cat-list">
                        <?php foreach (array_slice($expenseCats, 0, 6) as $c): ?>
                            <div class="cat-item"><i style="background:<?= e($c['color']) ?>"></i><span><?= e($c['name']) ?></span><b><?= round($c['total'] / max(1, $totals['expense']) * 100) ?>%</b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table table-stack">
                        <thead><tr><th>Category</th><th class="num">Entries</th><th class="num">Amount</th><th class="num">Share</th><th class="num">Change</th></tr></thead>
                        <tbody>
                        <?php foreach ($expenseCats as $c):
                            $before = $prevCats[(int) $c['id']] ?? 0;
                            $change = $before > 0 ? ($c['total'] - $before) / $before * 100 : null;
                            ?>
                            <tr>
                                <td data-label="Category"><span class="cell-cat"><i style="background:<?= e($c['color']) ?>"></i><?= e($c['name']) ?></span></td>
                                <td data-label="Entries" class="num"><?= (int) $c['n'] ?></td>
                                <td data-label="Amount" class="num"><strong><?= e(money($c['total'])) ?></strong></td>
                                <td data-label="Share" class="num"><?= e(percent($c['total'] / max(1, $totals['expense']) * 100, 1)) ?></td>
                                <td data-label="Change" class="num">
                                    <?php if ($change === null): ?><span class="badge info">New</span>
                                    <?php else: ?><span class="badge <?= $change > 10 ? 'bad' : ($change < -10 ? 'good' : '') ?>"><?= $change >= 0 ? '+' : '' ?><?= e(percent($change)) ?></span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= chart_empty('No spending in this period') ?>
            <?php endif; ?>
        </div>
    </article>

    <div class="stack">
        <article class="card">
            <div class="card-head"><div><h2>Where income came from</h2></div></div>
            <div class="card-body">
                <?php if ($incomeSources): ?>
                    <div class="list-rows">
                        <?php foreach ($incomeSources as $src): ?>
                            <div class="list-row">
                                <div class="list-main"><strong><?= e($src['label']) ?></strong><span><?= (int) $src['n'] ?> <?= (int) $src['n'] === 1 ? 'payment' : 'payments' ?></span></div>
                                <div style="text-align:right"><b class="amt amt-income"><?= e(money($src['total'])) ?></b><div style="font-size:12px;color:var(--muted)"><?= round($src['total'] / max(1, $totals['income']) * 100) ?>%</div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= chart_empty('No income in this period') ?>
                <?php endif; ?>
            </div>
        </article>

        <article class="card">
            <div class="card-head"><div><h2>Needs, wants and savings</h2><p>Based on tagged expenses</p></div></div>
            <div class="card-body">
                <?php if ($necessityTotal > 0): ?>
                    <div class="list-rows">
                        <?php foreach (['need' => ['Needs', 'info'], 'want' => ['Wants', 'warn'], 'saving' => ['Savings', 'good'], 'untagged' => ['Not tagged', '']] as $key => [$label, $tone]):
                            if ($necessity[$key] <= 0) { continue; } ?>
                            <div class="list-row" style="display:block">
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><strong><?= e($label) ?></strong><span style="font-size:13px;color:var(--muted)"><?= e(money($necessity[$key])) ?> · <?= round($necessity[$key] / $necessityTotal * 100) ?>%</span></div>
                                <div class="progress <?= e($tone === 'info' ? '' : $tone) ?>"><i style="width:<?= round($necessity[$key] / $necessityTotal * 100) ?>%"></i></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= chart_empty('Tag expenses as need or want to see this') ?>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<section class="grid grid-even mt">
    <?php foreach (['Biggest expenses' => $topExpenses, 'Biggest income' => $topIncome] as $heading => $rows): ?>
        <article class="card">
            <div class="card-head"><div><h2><?= e($heading) ?></h2></div></div>
            <div style="padding-top:12px">
                <?php if ($rows): ?>
                    <ul class="txn-list"><?php foreach ($rows as $t) { echo txn_row($t + ['to_account_name' => null]); } ?></ul>
                <?php else: ?>
                    <div class="card-body"><?= chart_empty('Nothing to show') ?></div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php page_end();
