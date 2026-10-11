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
        $kind = post('kind');
        $person = mb_substr(post('person'), 0, 120);
        $amount = parse_amount(post('amount'));
        $due = post('due_date');
        $reason = mb_substr(post('reason'), 0, 255);

        if (!in_array($kind, ['owed_to_me', 'i_owe'], true) || $person === '' || $amount === null || $amount <= 0 || ($due !== '' && !valid_date($due))) {
            flash('error', 'Choose a direction, enter a name, an amount above zero and a valid date.');
        } elseif ($id) {
            db_exec('UPDATE debts SET kind = ?, person = ?, amount = ?, due_date = ?, reason = ? WHERE id = ? AND user_id = ?', [$kind, $person, $amount, $due !== '' ? $due : null, $reason, $id, $uid]);
            flash('success', 'Record updated.');
        } else {
            db_exec('INSERT INTO debts (user_id, kind, person, amount, due_date, reason) VALUES (?, ?, ?, ?, ?, ?)', [$uid, $kind, $person, $amount, $due !== '' ? $due : null, $reason]);
            flash('success', 'Record added.');
        }
    } elseif ($action === 'pay') {
        $amount = parse_amount(post('amount'));
        $debt = db_row('SELECT amount, paid_amount FROM debts WHERE id = ? AND user_id = ?', [$id, $uid]);

        if (!$debt || $amount === null || $amount <= 0) {
            flash('error', 'Enter an amount above zero.');
        } else {
            $newPaid = min((float) $debt['amount'], (float) $debt['paid_amount'] + $amount);
            db_exec('UPDATE debts SET paid_amount = ? WHERE id = ? AND user_id = ?', [$newPaid, $id, $uid]);
            flash('success', $newPaid >= (float) $debt['amount'] ? 'Fully settled.' : 'Payment recorded.');
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM debts WHERE id = ? AND user_id = ?', [$id, $uid]);
        flash('success', 'Record deleted.');
    }

    redirect('debts.php');
}

$all = db_all('SELECT *, (amount - paid_amount) AS remaining FROM debts WHERE user_id = ? ORDER BY due_date IS NULL, due_date, id DESC', [$uid]);
$sections = ['i_owe' => [], 'owed_to_me' => []];
$settled = [];
$totals = ['i_owe' => 0.0, 'owed_to_me' => 0.0];

foreach ($all as $d) {
    if ((float) $d['remaining'] <= 0.004) {
        $settled[] = $d;
        continue;
    }
    $sections[$d['kind']][] = $d;
    $totals[$d['kind']] += (float) $d['remaining'];
}

page_start('Debts & loans', 'debts', 'Money you owe and money owed to you');

function debt_card(array $d): void
{
    $ratio = $d['amount'] > 0 ? $d['paid_amount'] / $d['amount'] : 0;
    $days = $d['due_date'] ? days_until($d['due_date']) : null;
    $settledFlag = (float) $d['remaining'] <= 0.004;
    $tone = $settledFlag ? 'good' : ($days !== null && $days < 0 ? 'bad' : ($days !== null && $days <= 7 ? 'warn' : ''));
    ?>
    <article class="card tile">
        <div class="tile-head">
            <span class="dot-ico" style="background:<?= $d['kind'] === 'i_owe' ? 'var(--expense)' : 'var(--income)' ?>"><?= icon($d['kind'] === 'i_owe' ? 'arrow-up-circle' : 'arrow-down-circle') ?></span>
            <div class="list-main"><strong><?= e($d['person']) ?></strong><span><?= e($d['reason'] !== '' ? $d['reason'] : ($d['kind'] === 'i_owe' ? 'You owe' : 'Owes you')) ?></span></div>
            <div class="tile-actions">
                <button class="icon-btn sm" type="button" aria-label="Edit" data-open-dialog="debtDialog" data-title="Edit record" data-fill="<?= e(json_encode(['id' => $d['id'], 'kind' => $d['kind'], 'person' => $d['person'], 'amount' => $d['amount'] + 0, 'due_date' => $d['due_date'], 'reason' => $d['reason']])) ?>"><?= icon('pencil-square') ?></button>
                <form method="post" data-confirm="This record and its payment history will be deleted." data-confirm-title="Delete record?">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button>
                </form>
            </div>
        </div>
        <div>
            <div class="tile-value"><?= e(money($d['remaining'])) ?> <small style="font-size:13px;color:var(--muted);font-weight:600">remaining</small></div>
            <div class="progress <?= e($tone) ?>" style="margin:10px 0 8px"><i style="width:<?= min(100, round($ratio * 100)) ?>%"></i></div>
            <div class="tile-foot"><span><?= e(money($d['paid_amount'])) ?> of <?= e(money($d['amount'])) ?> paid</span>
                <?php if ($settledFlag): ?><span class="badge good">Settled</span>
                <?php elseif ($days !== null): ?><span class="badge <?= e($tone ?: 'info') ?>"><?= $days < 0 ? abs($days) . ' days overdue' : ($days === 0 ? 'Due today' : 'Due ' . e(fmt_date($d['due_date']))) ?></span>
                <?php else: ?><span class="badge">No due date</span><?php endif; ?>
            </div>
        </div>
        <?php if (!$settledFlag): ?>
            <button class="btn btn-ghost btn-sm" type="button" data-open-dialog="payDialog" data-title="<?= $d['kind'] === 'i_owe' ? 'Record a payment to ' : 'Record a payment from ' ?><?= e($d['person']) ?>" data-fill="<?= e(json_encode(['id' => $d['id']])) ?>"><?= icon('banknotes', 'icon-sm') ?>Record payment</button>
        <?php endif; ?>
    </article>
    <?php
}
?>

<div class="toolbar">
    <p style="color:var(--muted)">Track loans so nothing is forgotten, from a friend you lent to or a supplier you owe.</p>
    <button class="btn btn-primary" type="button" data-open-dialog="debtDialog" data-title="Add record"><?= icon('plus') ?>Add record</button>
</div>

<section class="grid grid-even" style="margin-bottom:20px">
    <article class="card stat"><div class="stat-top"><span class="stat-label">You owe</span><span class="stat-ico expense"><?= icon('arrow-up-circle') ?></span></div><div class="stat-value"><?= e(money($totals['i_owe'])) ?></div><div class="stat-note"><?= count($sections['i_owe']) ?> open</div></article>
    <article class="card stat"><div class="stat-top"><span class="stat-label">Owed to you</span><span class="stat-ico income"><?= icon('arrow-down-circle') ?></span></div><div class="stat-value"><?= e(money($totals['owed_to_me'])) ?></div><div class="stat-note"><?= count($sections['owed_to_me']) ?> open</div></article>
</section>

<?php foreach (['i_owe' => 'Money you owe', 'owed_to_me' => 'Money owed to you'] as $kind => $heading): ?>
    <h2 class="section-title"><?= e($heading) ?></h2>
    <?php if ($sections[$kind]): ?>
        <div class="tile-grid" style="margin-bottom:28px"><?php foreach ($sections[$kind] as $d) { debt_card($d); } ?></div>
    <?php else: ?>
        <div class="card" style="margin-bottom:28px"><?= empty_state($kind === 'i_owe' ? 'check-circle' : 'users', $kind === 'i_owe' ? 'You owe nothing' : 'No one owes you', $kind === 'i_owe' ? 'Open debts you add will show up here.' : 'Add money you lent so you remember to collect it.') ?></div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($settled): ?>
    <h2 class="section-title">Settled</h2>
    <div class="tile-grid"><?php foreach ($settled as $d) { debt_card($d); } ?></div>
<?php endif; ?>

<?php dialog_start('debtDialog', 'Add record'); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="field"><label>Direction</label>
        <div class="segmented">
            <label><input type="radio" name="kind" value="i_owe" checked><span>I owe</span></label>
            <label><input type="radio" name="kind" value="owed_to_me"><span>Owed to me</span></label>
        </div></div>
    <div class="field"><label for="debt_person">Person or business</label><input class="input" id="debt_person" name="person" maxlength="120" required></div>
    <?= amount_input('debt_amount', 'amount', 'Amount') ?>
    <div class="field"><label for="debt_due">Due date <small>(optional)</small></label><input class="input" type="date" id="debt_due" name="due_date"></div>
    <div class="field"><label for="debt_reason">What is it for? <small>(optional)</small></label><input class="input" id="debt_reason" name="reason" maxlength="255"></div>
<?php dialog_end('Save record'); ?>

<?php dialog_start('payDialog', 'Record payment'); ?>
    <input type="hidden" name="action" value="pay">
    <input type="hidden" name="id" value="">
    <?= amount_input('pay_amount', 'amount', 'Amount paid') ?>
<?php dialog_end('Record payment'); ?>

<?php page_end();
