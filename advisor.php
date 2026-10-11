<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/charts.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];
$s = financial_snapshot($uid, $user);
$health = health_score($s);
$insights = build_insights($s);

$cover = $s['avgExpense'] > 0 ? $s['liquid'] / $s['avgExpense'] : null;
$cushion = $s['avgExpense'] * $s['emergency'];
$cushionRatio = $cushion > 0 ? min(1, max(0, $s['liquid'] / $cushion)) : 0;
$monthsToCushion = ($cushion > $s['liquid'] && $s['avgSurplus'] > 0) ? (int) ceil(($cushion - $s['liquid']) / $s['avgSurplus']) : null;
$netWorth = $s['liquid'] + $s['owed'] - $s['owe'];

$base = $s['cur']['income'] > 0 ? $s['cur']['income'] : $s['avgIncome'];
$plan = [
    ['Needs', 50, $s['necessity']['need'], 'info'],
    ['Wants', 30, $s['necessity']['want'], 'warn'],
    ['Savings', 20, max($s['necessity']['saving'], $s['cur']['net'] > 0 ? $s['cur']['net'] : 0), 'good'],
];

$counts = ['bad' => 0, 'warn' => 0, 'info' => 0, 'good' => 0];
foreach ($insights as $i) {
    $counts[$i['level']]++;
}

page_start('Advisor', 'advisor', 'Plain advice drawn from your own records');
?>

<section class="grid grid-main">
    <article class="card">
        <div class="card-head"><div><h2>Financial health</h2><p>A score from 0 to 100 built from four habits</p></div></div>
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
                <?= empty_state('light-bulb', 'Not enough data yet', 'Add a few transactions and the advisor will start giving you specific guidance.', 'Add transaction', 'transaction.php') ?>
            <?php endif; ?>
        </div>
    </article>

    <article class="card">
        <div class="card-head"><div><h2>Key figures</h2><p>Typical month, last <?= max(1, $s['historyMonths']) ?> <?= $s['historyMonths'] === 1 ? 'month' : 'months' ?></p></div></div>
        <div class="card-body">
            <div class="metric-list">
                <div class="metric"><span>Average income</span><b class="amt-income"><?= e(money($s['avgIncome'])) ?></b></div>
                <div class="metric"><span>Average spending</span><b class="amt-expense"><?= e(money($s['avgExpense'])) ?></b></div>
                <div class="metric"><span>Average surplus</span><b><?= e(money($s['avgSurplus'])) ?></b></div>
                <div class="metric"><span>Cash covers</span><b><?= $cover === null ? '-' : number_format($cover, 1) . ' months' ?></b></div>
                <div class="metric"><span>Net worth</span><b><?= e(money($netWorth)) ?></b></div>
            </div>
        </div>
    </article>
</section>

<section class="grid grid-main mt">
    <div class="stack">
        <div class="toolbar" style="margin:0">
            <h2 class="section-title" style="margin:0">Advice for you</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php if ($counts['bad']): ?><span class="badge bad"><?= $counts['bad'] ?> urgent</span><?php endif; ?>
                <?php if ($counts['warn']): ?><span class="badge warn"><?= $counts['warn'] ?> to watch</span><?php endif; ?>
                <?php if ($counts['good']): ?><span class="badge good"><?= $counts['good'] ?> going well</span><?php endif; ?>
            </div>
        </div>
        <div class="insights">
            <?php foreach ($insights as $i): ?>
                <div class="insight <?= e($i['level']) ?>"><span class="insight-ico"><?= icon($i['icon']) ?></span><div><h3><?= e($i['title']) ?></h3><p><?= e($i['text']) ?></p></div></div>
            <?php endforeach; ?>
            <?php if (!$insights): ?><div class="card"><?= empty_state('check-circle', 'Nothing to flag', 'Keep recording and advice will appear as patterns emerge.') ?></div><?php endif; ?>
        </div>
    </div>

    <div class="stack">
        <article class="card">
            <div class="card-head"><div><h2>Emergency fund</h2><p><?= (int) $s['emergency'] ?> months of expenses</p></div></div>
            <div class="card-body">
                <?php if ($cushion > 0): ?>
                    <div class="tile-value"><?= e(money($s['liquid'])) ?></div>
                    <div class="progress <?= $cushionRatio >= 1 ? 'good' : ($cushionRatio < 0.34 ? 'bad' : 'warn') ?>" style="margin:12px 0 8px"><i style="width:<?= round($cushionRatio * 100) ?>%"></i></div>
                    <div class="tile-foot"><span><?= round($cushionRatio * 100) ?>% of <?= e(money($cushion)) ?></span>
                        <b><?= $cushionRatio >= 1 ? 'Target met' : ($monthsToCushion ? 'About ' . $monthsToCushion . ' months to go' : 'Needs a monthly surplus') ?></b></div>
                <?php else: ?>
                    <?= chart_empty('Record expenses to size your fund') ?>
                <?php endif; ?>
            </div>
        </article>

        <article class="card">
            <div class="card-head"><div><h2>50 / 30 / 20 check</h2><p>Needs, wants and savings against income</p></div></div>
            <div class="card-body">
                <?php if ($base > 0): ?>
                    <div class="list-rows">
                        <?php foreach ($plan as [$label, $pct, $actual, $tone]):
                            $targetAmount = $base * $pct / 100;
                            $ratio = $targetAmount > 0 ? $actual / $targetAmount : 0;
                            $bad = $label === 'Savings' ? $ratio < 0.6 : $ratio > 1.15;
                            ?>
                            <div class="list-row" style="display:block">
                                <div style="display:flex;justify-content:space-between;margin-bottom:8px;gap:8px"><strong><?= e($label) ?> <small style="color:var(--muted);font-weight:500"><?= $pct ?>%</small></strong><span style="font-size:13px;color:var(--muted)"><?= e(compact_number($actual)) ?> / <?= e(compact_number($targetAmount)) ?></span></div>
                                <div class="progress <?= $bad ? 'warn' : 'good' ?>"><i style="width:<?= min(100, round($ratio * 100)) ?>%"></i></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:13px;color:var(--muted);margin-top:14px">Based on <?= $s['cur']['income'] > 0 ? 'this month\'s income' : 'your average income' ?> of <?= e(money($base)) ?>. Tag expenses as need or want to make this accurate.</p>
                <?php else: ?>
                    <?= chart_empty('Record income to see your split') ?>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<?php page_end();
