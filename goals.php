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
        $name = mb_substr(post('name'), 0, 120);
        $target = parse_amount(post('target_amount'));
        $saved = post('saved_amount') === '' ? 0.0 : parse_amount(post('saved_amount'));
        $date = post('target_date');

        if ($name === '' || $target === null || $target <= 0 || $saved === null || ($date !== '' && !valid_date($date))) {
            flash('error', 'Enter a name, a target above zero and a valid date.');
        } elseif ($id) {
            db_exec('UPDATE goals SET name = ?, target_amount = ?, saved_amount = ?, target_date = ? WHERE id = ? AND user_id = ?', [$name, $target, $saved, $date !== '' ? $date : null, $id, $uid]);
            flash('success', 'Goal updated.');
        } else {
            db_exec('INSERT INTO goals (user_id, name, target_amount, saved_amount, target_date) VALUES (?, ?, ?, ?, ?)', [$uid, $name, $target, $saved, $date !== '' ? $date : null]);
            flash('success', 'Goal created.');
        }
    } elseif ($action === 'fund') {
        $amount = parse_amount(post('amount'));
        $direction = post('direction') === 'withdraw' ? -1 : 1;

        if ($amount === null || $amount <= 0) {
            flash('error', 'Enter an amount above zero.');
        } else {
            db_exec('UPDATE goals SET saved_amount = GREATEST(0, saved_amount + ?) WHERE id = ? AND user_id = ?', [$amount * $direction, $id, $uid]);
            $goal = db_row('SELECT name, saved_amount, target_amount FROM goals WHERE id = ? AND user_id = ?', [$id, $uid]);
            if ($goal && $direction > 0 && $goal['saved_amount'] >= $goal['target_amount']) {
                flash('success', 'You reached your "' . $goal['name'] . '" goal. Well done.');
            } else {
                flash('success', $direction > 0 ? 'Funds added to your goal.' : 'Funds withdrawn from your goal.');
            }
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM goals WHERE id = ? AND user_id = ?', [$id, $uid]);
        flash('success', 'Goal deleted.');
    }

    redirect('goals.php');
}

$goals = active_goals($uid);
$totalTarget = array_sum(array_column($goals, 'target_amount'));
$totalSaved = array_sum(array_column($goals, 'saved_amount'));

page_start('Savings goals', 'goals', 'Turn what you want into a plan');
?>

<div class="toolbar">
    <?php if ($goals): ?>
        <div><span class="section-title" style="margin:0">Saved toward goals</span><div class="tile-value"><?= e(money($totalSaved)) ?> <small style="font-size:14px;color:var(--muted);font-weight:600">of <?= e(money($totalTarget)) ?></small></div></div>
    <?php else: ?>
        <p style="color:var(--muted)">Name something you are saving for, set the amount and the date, and track your progress.</p>
    <?php endif; ?>
    <button class="btn btn-primary" type="button" data-open-dialog="goalDialog" data-title="New goal"><?= icon('plus') ?>New goal</button>
</div>

<?php if ($goals): ?>
<div class="tile-grid">
    <?php foreach ($goals as $g):
        $ratio = $g['target_amount'] > 0 ? $g['saved_amount'] / $g['target_amount'] : 0;
        $done = $ratio >= 1;
        $remaining = max(0, $g['target_amount'] - $g['saved_amount']);
        $days = $g['target_date'] ? days_until($g['target_date']) : null;
        $monthly = (!$done && $days !== null && $days > 0) ? $remaining / max(1, $days / 30.44) : null;
        ?>
        <article class="card tile">
            <div class="tile-head">
                <span class="dot-ico" style="background:<?= $done ? 'var(--good)' : 'var(--brand-strong)' ?>"><?= icon($done ? 'check-circle' : 'flag') ?></span>
                <div class="list-main"><strong><?= e($g['name']) ?></strong><span><?= $g['target_date'] ? ($days < 0 && !$done ? 'Date passed ' . e(fmt_date($g['target_date'])) : 'By ' . e(fmt_date($g['target_date']))) : 'No deadline' ?></span></div>
                <div class="tile-actions">
                    <button class="icon-btn sm" type="button" aria-label="Edit" data-open-dialog="goalDialog" data-title="Edit goal" data-fill="<?= e(json_encode(['id' => $g['id'], 'name' => $g['name'], 'target_amount' => $g['target_amount'] + 0, 'saved_amount' => $g['saved_amount'] + 0, 'target_date' => $g['target_date']])) ?>"><?= icon('pencil-square') ?></button>
                    <form method="post" data-confirm="The goal and its progress will be deleted." data-confirm-title="Delete goal?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                        <button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button>
                    </form>
                </div>
            </div>
            <div>
                <div class="tile-value"><?= e(money($g['saved_amount'])) ?></div>
                <div class="progress <?= $done ? 'good' : '' ?>" style="margin:10px 0 8px"><i style="width:<?= min(100, round($ratio * 100)) ?>%"></i></div>
                <div class="tile-foot"><span><?= e(percent(min(100, $ratio * 100))) ?> of <?= e(money($g['target_amount'])) ?></span><b><?= $done ? 'Completed' : e(money($remaining)) . ' to go' ?></b></div>
            </div>
            <?php if ($monthly !== null): ?>
                <div class="hint"><?= icon('calendar-days') ?><span>Save about <?= e(money($monthly)) ?> a month to finish on time.</span></div>
            <?php endif; ?>
            <div class="tile-buttons">
                <button class="btn btn-primary btn-sm" type="button" data-open-dialog="fundDialog" data-title="Add to <?= e($g['name']) ?>" data-fill="<?= e(json_encode(['id' => $g['id'], 'direction' => 'add'])) ?>"><?= icon('plus', 'icon-sm') ?>Add funds</button>
                <button class="btn btn-ghost btn-sm" type="button" data-open-dialog="fundDialog" data-title="Withdraw from <?= e($g['name']) ?>" data-fill="<?= e(json_encode(['id' => $g['id'], 'direction' => 'withdraw'])) ?>"><?= icon('minus', 'icon-sm') ?>Withdraw</button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="card"><?= empty_state('flag', 'No goals yet', 'An emergency fund, school fees or a new phone all work well as goals.') ?></div>
<?php endif; ?>

<?php dialog_start('goalDialog', 'New goal'); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="field"><label for="goal_name">Goal name</label><input class="input" id="goal_name" name="name" maxlength="120" placeholder="For example: Emergency fund" required></div>
    <?= amount_input('goal_target', 'target_amount', 'Target amount') ?>
    <?= amount_input('goal_saved', 'saved_amount', 'Already saved') ?>
    <div class="field"><label for="goal_date">Target date <small>(optional)</small></label><input class="input" type="date" id="goal_date" name="target_date"></div>
<?php dialog_end('Save goal'); ?>

<?php dialog_start('fundDialog', 'Add funds'); ?>
    <input type="hidden" name="action" value="fund">
    <input type="hidden" name="id" value="">
    <div class="field"><label>Action</label>
        <div class="segmented">
            <label><input type="radio" name="direction" value="add" checked><span>Add</span></label>
            <label><input type="radio" name="direction" value="withdraw"><span>Withdraw</span></label>
        </div></div>
    <?= amount_input('fund_amount', 'amount', 'Amount') ?>
<?php dialog_end('Confirm'); ?>

<?php page_end();
