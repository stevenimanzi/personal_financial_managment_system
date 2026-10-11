<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

if (is_post()) {
    csrf_verify();
    $action = post('action');
    $id = (int) post('id');

    if ($action === 'save') {
        $categoryId = (int) post('category_id');
        $amount = parse_amount(post('amount'));
        $validCategory = db_val("SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = 'expense'", [$categoryId, $uid]);

        if (!$validCategory || $amount === null || $amount <= 0) {
            flash('error', 'Choose an expense category and enter a limit above zero.');
        } else {
            db_exec(
                'INSERT INTO budgets (user_id, category_id, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount)',
                [$uid, $categoryId, $amount]
            );
            flash('success', 'Budget saved.');
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM budgets WHERE id = ? AND user_id = ?', [$id, $uid]);
        flash('success', 'Budget removed.');
    }

    redirect('budgets.php');
}

$today = new DateTimeImmutable('today');
[$from] = month_bounds(0);
$budgets = budget_status($uid, $from, $today->format('Y-m-d'));
$budgeted = array_sum(array_column($budgets, 'amount'));
$spent = array_sum(array_column($budgets, 'spent'));
$daysLeft = (int) $today->format('t') - (int) $today->format('j') + 1;
$categories = db_all("SELECT id, name FROM categories WHERE user_id = ? AND type = 'expense' ORDER BY name", [$uid]);
$expectedRatio = (int) $today->format('j') / (int) $today->format('t');

page_start('Budgets', 'budgets', $today->format('F Y') . ' · ' . $daysLeft . ' days left');
?>

<div class="toolbar">
    <p style="color:var(--muted)">Monthly limits for the categories you want to keep under control. They reset every month.</p>
    <button class="btn btn-primary" type="button" data-open-dialog="budgetDialog" data-title="Set budget"><?= icon('plus') ?>Set budget</button>
</div>

<?php if ($budgets): ?>
<section class="grid grid-3" style="margin-bottom:20px">
    <article class="card stat"><div class="stat-top"><span class="stat-label">Budgeted</span><span class="stat-ico"><?= icon('chart-pie') ?></span></div><div class="stat-value"><?= e(money($budgeted)) ?></div></article>
    <article class="card stat"><div class="stat-top"><span class="stat-label">Spent so far</span><span class="stat-ico expense"><?= icon('arrow-up-circle') ?></span></div><div class="stat-value"><?= e(money($spent)) ?></div><div class="stat-note"><?= $budgeted > 0 ? e(percent($spent / $budgeted * 100)) . ' of total budget' : '' ?></div></article>
    <article class="card stat"><div class="stat-top"><span class="stat-label">Remaining</span><span class="stat-ico income"><?= icon('banknotes') ?></span></div><div class="stat-value"><?= e(money($budgeted - $spent)) ?></div><div class="stat-note"><?= $budgeted - $spent > 0 ? 'About ' . e(money(($budgeted - $spent) / max(1, $daysLeft))) . ' a day' : 'Over budget overall' ?></div></article>
</section>

<div class="tile-grid">
    <?php foreach ($budgets as $b):
        $tone = $b['ratio'] >= 1 ? 'bad' : ($b['ratio'] >= 0.85 ? 'warn' : 'good');
        $left = $b['amount'] - $b['spent'];
        ?>
        <article class="card tile">
            <div class="tile-head">
                <span class="dot-ico" style="background:<?= e($b['color']) ?>"><?= icon('tag') ?></span>
                <div class="list-main"><strong><?= e($b['name']) ?></strong><span><?= $b['ratio'] > $expectedRatio + 0.1 && $b['ratio'] < 1 ? 'Ahead of the month' : 'Monthly limit ' . e(money($b['amount'])) ?></span></div>
                <div class="tile-actions">
                    <button class="icon-btn sm" type="button" aria-label="Edit" data-open-dialog="budgetDialog" data-title="Edit budget" data-fill="<?= e(json_encode(['category_id' => $b['category_id'], 'amount' => $b['amount'] + 0])) ?>"><?= icon('pencil-square') ?></button>
                    <form method="post" data-confirm="The limit will be removed. Your transactions are not affected." data-confirm-title="Remove budget?" data-confirm-button="Remove">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <button class="icon-btn sm danger" type="submit" aria-label="Remove"><?= icon('trash') ?></button>
                    </form>
                </div>
            </div>
            <div>
                <div class="tile-value"><?= e(money($b['spent'])) ?></div>
                <div class="progress <?= $tone ?>" style="margin:10px 0 8px"><i style="width:<?= min(100, round($b['ratio'] * 100)) ?>%"></i></div>
                <div class="tile-foot">
                    <span><?= e(percent($b['ratio'] * 100)) ?> used</span>
                    <b class="<?= $left < 0 ? 'amt-expense' : '' ?>"><?= $left >= 0 ? e(money($left)) . ' left' : e(money(-$left)) . ' over' ?></b>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="card"><?= empty_state('chart-pie', 'No budgets yet', 'Pick a category you want to control, such as food or transport, and give it a monthly limit.') ?></div>
<?php endif; ?>

<?php dialog_start('budgetDialog', 'Set budget'); ?>
    <input type="hidden" name="action" value="save">
    <div class="field"><label for="bud_cat">Category</label>
        <select class="input" id="bud_cat" name="category_id" required>
            <option value="">Choose category</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <?= amount_input('bud_amount', 'amount', 'Monthly limit') ?>
    <div class="hint"><?= icon('information-circle') ?><span>Choosing a category that already has a budget replaces its limit.</span></div>
<?php dialog_end('Save budget'); ?>

<?php page_end();
