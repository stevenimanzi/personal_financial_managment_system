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
        $name = mb_substr(post('name'), 0, 80);
        $type = post('type');
        $opening = post('opening_balance') === '' ? 0.0 : parse_amount(ltrim(post('opening_balance'), '-'));
        $negative = str_starts_with(post('opening_balance'), '-');
        $color = valid_color(post('color'), '#1f6fb8');

        if ($name === '' || !isset(account_types()[$type]) || $opening === null) {
            flash('error', 'Enter a name, choose a type and use a valid opening balance.');
        } else {
            $opening = $negative ? -$opening : $opening;
            if ($id) {
                db_exec('UPDATE accounts SET name = ?, type = ?, opening_balance = ?, color = ? WHERE id = ? AND user_id = ?', [$name, $type, $opening, $color, $id, $uid]);
                flash('success', 'Account updated.');
            } else {
                db_exec('INSERT INTO accounts (user_id, name, type, opening_balance, color) VALUES (?, ?, ?, ?, ?)', [$uid, $name, $type, $opening, $color]);
                flash('success', 'Account added.');
            }
        }
    } elseif ($action === 'archive') {
        db_exec('UPDATE accounts SET is_archived = 1 - is_archived WHERE id = ? AND user_id = ?', [$id, $uid]);
        flash('success', 'Account updated.');
    } elseif ($action === 'delete') {
        $used = (int) db_val('SELECT COUNT(*) FROM transactions WHERE user_id = ? AND (account_id = ? OR to_account_id = ?)', [$uid, $id, $id]);
        if ($used > 0) {
            flash('error', 'This account has transactions. Archive it instead to hide it without losing history.');
        } else {
            db_exec('DELETE FROM accounts WHERE id = ? AND user_id = ?', [$id, $uid]);
            flash('success', 'Account deleted.');
        }
    }

    redirect('accounts.php');
}

$accounts = account_balances($uid);
$active = array_filter($accounts, fn($a) => !$a['is_archived']);
$archived = array_filter($accounts, fn($a) => $a['is_archived']);
$total = liquid_balance($accounts);

page_start('Accounts', 'accounts', 'Where your money is kept');
?>

<div class="toolbar">
    <div><span class="section-title" style="margin:0">Total across accounts</span><div class="tile-value"><?= e(money($total)) ?></div></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-ghost" href="transaction.php?type=transfer"><?= icon('arrows-right-left') ?>Move money</a>
        <button class="btn btn-primary" type="button" data-open-dialog="accountDialog" data-title="Add account"><?= icon('plus') ?>Add account</button>
    </div>
</div>

<?php if ($active): ?>
<div class="tile-grid">
    <?php foreach ($active as $a): ?>
        <article class="card tile">
            <div class="tile-head">
                <span class="dot-ico" style="--c:<?= e($a['color']) ?>"><?= icon(account_icon($a['type'])) ?></span>
                <div class="list-main"><strong><?= e($a['name']) ?></strong><span><?= e(account_types()[$a['type']]) ?></span></div>
                <div class="tile-actions">
                    <button class="icon-btn sm" type="button" aria-label="Edit" data-open-dialog="accountDialog" data-title="Edit account" data-fill="<?= e(json_encode(['id' => $a['id'], 'name' => $a['name'], 'type' => $a['type'], 'opening_balance' => $a['opening_balance'] + 0, 'color' => $a['color']])) ?>"><?= icon('pencil-square') ?></button>
                    <form method="post" data-confirm="The account will be hidden from new entries. Its history stays in your reports." data-confirm-title="Archive account?" data-confirm-button="Archive">
                        <?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                        <button class="icon-btn sm" type="submit" aria-label="Archive"><?= icon('minus') ?></button>
                    </form>
                </div>
            </div>
            <div class="tile-value <?= $a['balance'] < 0 ? 'amt-expense' : '' ?>"><?= e(money($a['balance'])) ?></div>
            <div class="tile-foot"><span>Opening balance</span><b><?= e(money($a['opening_balance'])) ?></b></div>
        </article>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="card"><?= empty_state('wallet', 'No accounts yet', 'Add the places your money is kept, such as cash, mobile money or a bank.') ?></div>
<?php endif; ?>

<?php if ($archived): ?>
<h2 class="section-title mt">Archived</h2>
<div class="card">
    <div class="card-body">
        <div class="list-rows">
            <?php foreach ($archived as $a): ?>
                <div class="list-row">
                    <span class="dot-ico" style="--c:<?= e($a['color']) ?>;opacity:.6"><?= icon(account_icon($a['type'])) ?></span>
                    <div class="list-main"><strong><?= e($a['name']) ?></strong><span><?= e(money($a['balance'])) ?></span></div>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon('arrow-uturn-left', 'icon-sm') ?>Restore</button></form>
                    <form method="post" data-confirm="This permanently removes the account. It only works when no transactions use it." data-confirm-title="Delete account?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php dialog_start('accountDialog', 'Add account'); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="field"><label for="acc_name">Account name</label><input class="input" id="acc_name" name="name" maxlength="80" placeholder="For example: MoMo, Equity Bank" required></div>
    <div class="field"><label for="acc_type">Type</label>
        <select class="input" id="acc_type" name="type">
            <?php foreach (account_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <?= amount_input('acc_open', 'opening_balance', 'Opening balance') ?>
    <div class="field"><label>Colour</label><?= swatch_picker('color') ?></div>
<?php dialog_end('Save account'); ?>

<?php page_end();
