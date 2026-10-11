<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/charts.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];
$s = financial_snapshot($uid, $user);
$cur = $s['cur'];
$today = $s['today'];

$prevFrom = (new DateTimeImmutable('first day of last month'))->format('Y-m-d');
$prevTo = (new DateTimeImmutable($prevFrom))->modify('+' . (min($s['day'], (int) (new DateTimeImmutable($prevFrom))->format('t')) - 1) . ' days')->format('Y-m-d');
$prevSame = period_totals($uid, $prevFrom, $prevTo);

[$sixStart] = month_bounds(-5);
$months = monthly_totals($uid, $sixStart, $today->format('Y-m-d'));
$labels = array_map(fn($ym) => (new DateTimeImmutable($ym . '-01'))->format('M'), array_keys($months));

$health = health_score($s);
$insights = array_slice(build_insights($s), 0, 3);
$recent = recent_transactions($uid, 7);

$donutItems = [];
foreach ($s['categories'] as $c) {
    $donutItems[] = ['label' => $c['name'], 'value' => $c['total'], 'color' => $c['color']];
}

function delta_badge(float $now, float $before, bool $upIsGood = true): string
{
    if ($before <= 0) {
        return '';
    }

    $change = ($now - $before) / $before * 100;

    if (abs($change) < 0.5) {
        return '<span class="delta flat">0%</span>';
    }

    $up = $change > 0;
    $good = $up === $upIsGood;

    return '<span class="delta ' . ($good ? 'up' : 'down') . '">' . icon($up ? 'arrow-trending-up' : 'arrow-trending-down', 'icon-sm') . percent(abs($change)) . '</span>';
}

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

page_start('Dashboard', 'dashboard', $greeting . ', ' . explode(' ', $user['name'])[0]);
?>

<section class="grid grid-4 keep-2">
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Total balance</span></div>
        <div class="stat-value"><?= e(money($s['liquid'])) ?></div>
        <div class="stat-note">Across <?= count(array_filter($s['accounts'], fn($a) => !$a['is_archived'])) ?> accounts</div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Income this month</span></div>
        <div class="stat-value"><?= e(money($cur['income'])) ?></div>
        <div class="stat-note"><?= delta_badge($cur['income'], $prevSame['income'], true) ?> vs same days last month</div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Spent this month</span></div>
        <div class="stat-value"><?= e(money($cur['expense'])) ?></div>
        <div class="stat-note"><?= delta_badge($cur['expense'], $prevSame['expense'], false) ?> vs same days last month</div>
    </article>
    <article class="card stat">
        <div class="stat-top"><span class="stat-label">Saved this month</span></div>
        <div class="stat-value"><?= e(money($cur['net'])) ?></div>
        <div class="stat-note"><?= $cur['rate'] === null ? 'No income recorded yet' : 'Savings rate <b>' . e(percent($cur['rate'])) . '</b>' ?></div>
    </article>
</section>

<section class="grid grid-main mt">
    <article class="card">
        <div class="card-head">
            <div><h2>Cash flow</h2><p>Income and spending over the last 6 months</p></div>
            <div class="legend"><span><i class="k-income"></i>Income</span><span><i class="k-expense"></i>Spending</span></div>
        </div>
        <div class="card-body">
            <?= chart_bars($labels, [
                ['name' => 'Income', 'values' => array_column($months, 'income'), 'class' => 'bar-income'],
                ['name' => 'Spending', 'values' => array_column($months, 'expense'), 'class' => 'bar-expense'],
            ], 260) ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head"><div><h2>Financial health</h2><p>Based on your recent activity</p></div></div>
        <div class="card-body">
            <?php if ($health): ?>
                <div class="score-card">
                    <?= chart_ring($health['score'], $health['tone']) ?>
                    <div class="score-parts">
                        <div><span class="badge <?= e($health['tone']) ?>"><?= e($health['label']) ?></span></div>
                        <?php foreach ($health['parts'] as $part): ?>
                            <div class="score-part">
                                <div><span><?= e($part['name']) ?></span><span><?= round($part['value']) ?>/<?= $part['max'] ?></span></div>
                                <div class="progress"><i style="width:<?= round($part['value'] / $part['max'] * 100) ?>%"></i></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <?= empty_state('chart-pie', 'No score yet', 'Record a few transactions and your financial health score will appear here.', 'Add transaction', 'transaction.php') ?>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="grid grid-main mt">
    <article class="card">
        <div class="card-head"><div><h2>Recent transactions</h2><p>Your latest activity</p></div><a class="link-more" href="transactions.php">View all <?= icon('chevron-right', 'icon-sm') ?></a></div>
        <div>
            <?php if ($recent): ?>
                <ul class="txn-list">
                    <?php foreach ($recent as $t): ?><?= txn_row($t) ?><?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= empty_state('arrows-right-left', 'Nothing recorded yet', 'Add the money you received or spent and it will show up here.', 'Add transaction', 'transaction.php') ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head"><div><h2>Where it went</h2><p>Spending by category this month</p></div></div>
        <div class="card-body">
            <?php if ($donutItems): ?>
                <div class="donut-wrap" style="grid-template-columns:minmax(0,1fr)">
                    <div style="max-width:200px;margin:0 auto;width:100%"><?= chart_donut($donutItems, 'Spent', compact_number($cur['expense'])) ?></div>
                    <div class="cat-list">
                        <?php foreach (array_slice($s['categories'], 0, 6) as $c): ?>
                            <div class="cat-item"><i style="background:<?= e($c['color']) ?>"></i><span><?= e($c['name']) ?></span><b><?= e(money($c['total'])) ?><small><?= round($c['total'] / max(1, $cur['expense']) * 100) ?>%</small></b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <?= chart_empty('No spending recorded this month') ?>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="grid grid-3 mt">
    <article class="card">
        <div class="card-head"><div><h2>Accounts</h2></div><a class="link-more" href="accounts.php">Manage <?= icon('chevron-right', 'icon-sm') ?></a></div>
        <div class="card-body">
            <div class="list-rows">
                <?php foreach (array_filter($s['accounts'], fn($a) => !$a['is_archived']) as $a): ?>
                    <div class="list-row">
                        <span class="dot-ico" style="--c:<?= e($a['color']) ?>"><?= icon(account_icon($a['type'])) ?></span>
                        <div class="list-main"><strong><?= e($a['name']) ?></strong><span><?= e(account_types()[$a['type']]) ?></span></div>
                        <b class="amt"><?= e(money($a['balance'])) ?></b>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </article>

    <article class="card">
        <div class="card-head"><div><h2>Budgets</h2><p>This month</p></div><a class="link-more" href="budgets.php">Manage <?= icon('chevron-right', 'icon-sm') ?></a></div>
        <div class="card-body">
            <?php if ($s['budgets']): ?>
                <div class="list-rows">
                    <?php foreach (array_slice($s['budgets'], 0, 4) as $b): $tone = $b['ratio'] >= 1 ? 'bad' : ($b['ratio'] >= 0.85 ? 'warn' : ''); ?>
                        <div class="list-row" style="display:block">
                            <div style="display:flex;justify-content:space-between;margin-bottom:8px;gap:8px"><strong><?= e($b['name']) ?></strong><span style="font-size:13px;color:var(--muted)"><?= e(compact_number($b['spent'])) ?> / <?= e(compact_number($b['amount'])) ?></span></div>
                            <div class="progress <?= $tone ?>"><i style="width:<?= min(100, round($b['ratio'] * 100)) ?>%"></i></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('chart-pie', 'No budgets yet', 'Set limits for the categories you spend most on.', 'Create budget', 'budgets.php') ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head"><div><h2>Coming up</h2><p>Goals and debts</p></div></div>
        <div class="card-body">
            <?php
            $upcoming = [];
            foreach ($s['debts'] as $d) {
                if ($d['due_date']) {
                    $upcoming[] = ['date' => $d['due_date'], 'title' => $d['kind'] === 'i_owe' ? 'Pay ' . $d['person'] : $d['person'] . ' owes you', 'amount' => $d['remaining'], 'icon' => 'users', 'tone' => $d['kind'] === 'i_owe' ? 'expense' : 'income'];
                }
            }
            foreach ($s['goals'] as $g) {
                if ($g['target_date'] && $g['saved_amount'] < $g['target_amount']) {
                    $upcoming[] = ['date' => $g['target_date'], 'title' => $g['name'], 'amount' => $g['target_amount'] - $g['saved_amount'], 'icon' => 'flag', 'tone' => 'transfer'];
                }
            }
            usort($upcoming, fn($a, $b) => strcmp($a['date'], $b['date']));
            $upcoming = array_slice($upcoming, 0, 4);
            ?>
            <?php if ($upcoming): ?>
                <div class="list-rows">
                    <?php foreach ($upcoming as $u): $days = days_until($u['date']); ?>
                        <div class="list-row">
                            <span class="dot-ico" style="--c:var(--muted)"><?= icon($u['icon']) ?></span>
                            <div class="list-main"><strong><?= e($u['title']) ?></strong><span><?= $days < 0 ? abs($days) . ' days overdue' : e(fmt_date($u['date'])) ?></span></div>
                            <b class="amt amt-<?= e($u['tone']) ?>"><?= e(money($u['amount'])) ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('flag', 'Nothing scheduled', 'Add a savings goal or a debt with a due date to see it here.') ?>
            <?php endif; ?>
        </div>
    </article>
</section>

<?php if ($insights): ?>
<section class="mt">
    <div class="toolbar" style="margin-bottom:12px"><h2 class="section-title" style="margin:0">What to pay attention to</h2><a class="link-more" href="advisor.php">Open advisor <?= icon('chevron-right', 'icon-sm') ?></a></div>
    <div class="insights">
        <?php foreach ($insights as $i): ?>
            <div class="insight <?= e($i['level']) ?>"><span class="insight-ico"><?= icon($i['icon']) ?></span><div><h3><?= e($i['title']) ?></h3><p><?= e($i['text']) ?></p></div></div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php page_end();
