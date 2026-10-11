<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];
$id = (int) query('id', (string) (int) post('id', '0'));
$existing = $id ? db_row('SELECT * FROM transactions WHERE id = ? AND user_id = ?', [$id, $uid]) : null;

if ($id && !$existing) {
    flash('error', 'That transaction could not be found.');
    redirect('transactions.php');
}

$accounts = db_all('SELECT id, name, is_archived FROM accounts WHERE user_id = ? ORDER BY is_archived, name', [$uid]);
$categories = db_all('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY name', [$uid]);

$accountIds = array_map('intval', array_column($accounts, 'id'));
$activeAccounts = array_values(array_filter($accounts, fn($a) => !$a['is_archived'] || ($existing && ((int) $existing['account_id'] === (int) $a['id'] || (int) $existing['to_account_id'] === (int) $a['id']))));

$presetType = in_array(query('type'), ['income', 'expense', 'transfer'], true) ? query('type') : 'expense';

$values = $existing ?: [
    'type' => $presetType,
    'amount' => '',
    'txn_date' => date('Y-m-d'),
    'account_id' => $activeAccounts[0]['id'] ?? '',
    'to_account_id' => '',
    'category_id' => '',
    'party' => '',
    'reason' => '',
    'necessity' => '',
    'notes' => '',
];

$errors = [];

if (is_post()) {
    csrf_verify();

    $values = [
        'type' => post('type'),
        'amount' => post('amount'),
        'txn_date' => post('txn_date'),
        'account_id' => post('account_id'),
        'to_account_id' => post('to_account_id'),
        'category_id' => post('category_id'),
        'party' => mb_substr(post('party'), 0, 120),
        'reason' => mb_substr(post('reason'), 0, 255),
        'necessity' => post('necessity'),
        'notes' => post('notes'),
    ];

    $type = $values['type'];
    $amount = parse_amount($values['amount']);
    $accountId = (int) $values['account_id'];
    $toAccountId = (int) $values['to_account_id'];
    $categoryId = (int) $values['category_id'];

    if (!in_array($type, ['income', 'expense', 'transfer'], true)) {
        $errors[] = 'Choose whether this is income, an expense or a transfer.';
    }
    if ($amount === null || $amount <= 0) {
        $errors[] = 'Enter an amount greater than zero.';
    }
    if (!valid_date($values['txn_date'])) {
        $errors[] = 'Enter a valid date.';
    }
    if (!in_array($accountId, $accountIds, true)) {
        $errors[] = 'Choose an account.';
    }

    if ($type === 'transfer') {
        if (!in_array($toAccountId, $accountIds, true)) {
            $errors[] = 'Choose the account receiving the money.';
        } elseif ($toAccountId === $accountId) {
            $errors[] = 'A transfer needs two different accounts.';
        }
        $categoryId = 0;
        $values['necessity'] = '';
        $values['party'] = '';
    } else {
        $toAccountId = 0;
        if ($values['reason'] === '') {
            $errors[] = $type === 'income' ? 'Say why you received this money.' : 'Say why you spent this money.';
        }
        if ($categoryId) {
            $categoryType = db_val('SELECT type FROM categories WHERE id = ? AND user_id = ?', [$categoryId, $uid]);
            if ($categoryType !== $type) {
                $errors[] = 'Choose a category that matches the transaction type.';
            }
        }
        if ($type === 'income' || !in_array($values['necessity'], ['need', 'want', 'saving'], true)) {
            $values['necessity'] = '';
        }
    }

    if (!$errors) {
        $data = [
            $accountId,
            $toAccountId ?: null,
            $categoryId ?: null,
            $type,
            $amount,
            $values['txn_date'],
            $values['party'],
            $values['reason'],
            $values['necessity'] ?: null,
            $values['notes'] !== '' ? $values['notes'] : null,
        ];

        if ($existing) {
            db_exec(
                'UPDATE transactions SET account_id = ?, to_account_id = ?, category_id = ?, type = ?, amount = ?, txn_date = ?, party = ?, reason = ?, necessity = ?, notes = ? WHERE id = ? AND user_id = ?',
                array_merge($data, [$id, $uid])
            );
            flash('success', 'Transaction updated.');
        } else {
            db_exec(
                'INSERT INTO transactions (account_id, to_account_id, category_id, type, amount, txn_date, party, reason, necessity, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($data, [$uid])
            );
            flash('success', ucfirst($type) . ' of ' . money($amount) . ' recorded.');
        }

        if ($type !== 'income') {
            foreach (account_balances($uid) as $a) {
                if ((int) $a['id'] === $accountId && $a['balance'] < 0) {
                    flash('info', $a['name'] . ' now has a negative balance. Check that every income and opening balance has been recorded.');
                }
            }
        }

        if (!$existing && isset($_POST['again'])) {
            redirect('transaction.php?type=' . urlencode($type));
        }
        redirect('transactions.php');
    }
}

$type = $values['type'];
$title = $existing ? 'Edit transaction' : 'New transaction';

page_start($title, 'transactions', $existing ? 'Update the details below' : 'Record money received, spent or moved');
?>

<div style="max-width:760px;margin:0 auto">
    <form class="card" method="post" id="txnForm" novalidate>
        <?= csrf_field() ?>
        <?php if ($existing): ?><input type="hidden" name="id" value="<?= (int) $existing['id'] ?>"><?php endif; ?>
        <div class="card-pad" style="display:grid;gap:22px">
            <?php if ($errors): ?>
                <div class="flash flash-error"><?= icon('exclamation-circle') ?><span><?= e(implode(' ', $errors)) ?></span></div>
            <?php endif; ?>

            <div class="segmented" role="radiogroup" aria-label="Transaction type">
                <label><input type="radio" name="type" value="income"<?= checked($type, 'income') ?>><span><?= icon('arrow-down-circle') ?>Income</span></label>
                <label><input type="radio" name="type" value="expense"<?= checked($type, 'expense') ?>><span><?= icon('arrow-up-circle') ?>Expense</span></label>
                <label><input type="radio" name="type" value="transfer"<?= checked($type, 'transfer') ?>><span><?= icon('arrows-right-left') ?>Transfer</span></label>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="amount">Amount</label>
                    <div class="input-prefix"><span><?= e(current_currency()) ?></span><input class="input" id="amount" name="amount" inputmode="decimal" autocomplete="off" placeholder="0" value="<?= e(str_contains((string) $values['amount'], '.') ? rtrim(rtrim((string) $values['amount'], '0'), '.') : $values['amount']) ?>" required></div>
                </div>
                <div class="field">
                    <label for="txn_date">Date</label>
                    <input class="input" type="date" id="txn_date" name="txn_date" value="<?= e($values['txn_date']) ?>" max="<?= e(date('Y-m-d', strtotime('+1 year'))) ?>" required>
                </div>

                <div class="field">
                    <label for="account_id">Account</label>
                    <select class="input" id="account_id" name="account_id" required>
                        <?php foreach ($activeAccounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($values['account_id'], $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field" data-show="transfer">
                    <label for="to_account_id">Move to</label>
                    <select class="input" id="to_account_id" name="to_account_id">
                        <option value="">Choose account</option>
                        <?php foreach ($activeAccounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($values['to_account_id'], $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div class="field" data-show="income expense">
                    <label for="category_id">Category</label>
                    <select class="input" id="category_id" name="category_id">
                        <option value="">Uncategorised</option>
                        <?php foreach ($categories as $c): ?><option data-type="<?= e($c['type']) ?>" value="<?= (int) $c['id'] ?>"<?= selected($values['category_id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field" data-show="income expense">
                    <label for="party" data-party-label>Paid to</label>
                    <input class="input" id="party" name="party" maxlength="120" value="<?= e($values['party']) ?>" placeholder="Person, employer or shop">
                </div>

                <div class="field full">
                    <label for="reason" data-reason-label>Why did you spend it?</label>
                    <input class="input" id="reason" name="reason" maxlength="255" value="<?= e($values['reason']) ?>" placeholder="For example: monthly rent, salary for September, school fees">
                </div>

                <div class="field full" data-show="expense">
                    <label>How important was it?</label>
                    <div class="chips">
                        <label><input type="radio" name="necessity" value=""<?= checked((string) $values['necessity'], '') ?>><span>Not sure</span></label>
                        <label><input type="radio" name="necessity" value="need"<?= checked($values['necessity'], 'need') ?>><span>Need</span></label>
                        <label><input type="radio" name="necessity" value="want"<?= checked($values['necessity'], 'want') ?>><span>Want</span></label>
                        <label><input type="radio" name="necessity" value="saving"<?= checked($values['necessity'], 'saving') ?>><span>Saving</span></label>
                    </div>
                    <small>Tagging helps the advisor see how much goes to essentials and how much to extras.</small>
                </div>

                <div class="field full">
                    <label for="notes">Notes <small>(optional)</small></label>
                    <textarea class="input" id="notes" name="notes" rows="3"><?= e($values['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        <div class="dialog-actions" style="border-top:1px solid var(--line);padding-top:18px">
            <a class="btn btn-ghost" href="transactions.php">Cancel</a>
            <?php if (!$existing): ?><button class="btn btn-ghost" type="submit" name="again" value="1">Save and add another</button><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span data-submit-label data-income="Save income" data-expense="Save expense" data-transfer="Save transfer">Save</span></button>
        </div>
    </form>
</div>

<?php page_end();
