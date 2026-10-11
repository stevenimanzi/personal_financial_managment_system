<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

if (is_post() && post('action') === 'delete') {
    csrf_verify();
    $deleted = db_exec('DELETE FROM transactions WHERE id = ? AND user_id = ?', [(int) post('id'), $uid]);
    flash($deleted ? 'success' : 'error', $deleted ? 'Transaction deleted.' : 'Transaction not found.');
    redirect('transactions.php' . (isset($_POST['back']) && is_string($_POST['back']) && preg_match('/^[\w=&%.\-]*$/', $_POST['back']) && $_POST['back'] !== '' ? '?' . $_POST['back'] : ''));
}

$f = [
    'q' => query('q'),
    'type' => query('type'),
    'account' => (int) query('account'),
    'category' => (int) query('category'),
    'from' => query('from'),
    'to' => query('to'),
];

$where = ['t.user_id = ?'];
$params = [$uid];

if ($f['q'] !== '') {
    $like = '%' . addcslashes($f['q'], '%_\\') . '%';
    $where[] = '(t.reason LIKE ? OR t.party LIKE ? OR t.notes LIKE ?)';
    array_push($params, $like, $like, $like);
}
if (in_array($f['type'], ['income', 'expense', 'transfer'], true)) {
    $where[] = 't.type = ?';
    $params[] = $f['type'];
} else {
    $f['type'] = '';
}
if ($f['account'] > 0) {
    $where[] = '(t.account_id = ? OR t.to_account_id = ?)';
    array_push($params, $f['account'], $f['account']);
}
if ($f['category'] > 0) {
    $where[] = 't.category_id = ?';
    $params[] = $f['category'];
}
if (valid_date($f['from'])) {
    $where[] = 't.txn_date >= ?';
    $params[] = $f['from'];
} else {
    $f['from'] = '';
}
if (valid_date($f['to'])) {
    $where[] = 't.txn_date <= ?';
    $params[] = $f['to'];
} else {
    $f['to'] = '';
}

$whereSql = implode(' AND ', $where);
$from = "FROM transactions t
    LEFT JOIN categories c ON c.id = t.category_id
    JOIN accounts a ON a.id = t.account_id
    LEFT JOIN accounts a2 ON a2.id = t.to_account_id
    WHERE $whereSql";

if (query('export') === 'csv') {
    $rows = db_all("SELECT t.txn_date, t.type, t.amount, t.reason, t.party, c.name AS category_name, a.name AS account_name, a2.name AS to_account_name, t.necessity, t.notes $from ORDER BY t.txn_date DESC, t.id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date', 'Type', 'Amount', 'Reason', 'Party', 'Category', 'Account', 'To account', 'Necessity', 'Notes']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['txn_date'], $r['type'], $r['amount'], $r['reason'], $r['party'], $r['category_name'], $r['account_name'], $r['to_account_name'], $r['necessity'], $r['notes']]);
    }
    fclose($out);
    exit;
}

$totals = db_row("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount END), 0) AS income, COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount END), 0) AS expense $from", $params);
$total = (int) $totals['n'];
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = max(1, min($pages, (int) query('page', '1')));
$rows = db_all(
    "SELECT t.*, c.name AS category_name, c.color AS category_color, a.name AS account_name, a2.name AS to_account_name $from
     ORDER BY t.txn_date DESC, t.id DESC LIMIT " . PER_PAGE . ' OFFSET ' . (($page - 1) * PER_PAGE),
    $params
);

$accounts = db_all('SELECT id, name FROM accounts WHERE user_id = ? ORDER BY name', [$uid]);
$categories = db_all('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY type DESC, name', [$uid]);

$baseQuery = array_filter($f, fn($v) => $v !== '' && $v !== 0);
$filtered = count($baseQuery) > 0;
$urlFor = fn(array $extra) => 'transactions.php?' . http_build_query(array_merge($baseQuery, $extra));
$backQuery = http_build_query(array_merge($baseQuery, $page > 1 ? ['page' => $page] : []));

page_start('Transactions', 'transactions', number_format($total) . ($total === 1 ? ' record' : ' records') . ($filtered ? ' match your filters' : ' in total'));
?>

<div class="toolbar">
    <div class="tabs">
        <a href="transactions.php" class="<?= $f['type'] === '' ? 'is-active' : '' ?>">All</a>
        <a href="<?= e($urlFor(['type' => 'income', 'page' => null])) ?>" class="<?= $f['type'] === 'income' ? 'is-active' : '' ?>">Income</a>
        <a href="<?= e($urlFor(['type' => 'expense', 'page' => null])) ?>" class="<?= $f['type'] === 'expense' ? 'is-active' : '' ?>">Expenses</a>
        <a href="<?= e($urlFor(['type' => 'transfer', 'page' => null])) ?>" class="<?= $f['type'] === 'transfer' ? 'is-active' : '' ?>">Transfers</a>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-ghost" href="<?= e($urlFor(['export' => 'csv'])) ?>"><?= icon('arrow-down-tray') ?>Export CSV</a>
        <a class="btn btn-primary" href="transaction.php"><?= icon('plus') ?>Add transaction</a>
    </div>
</div>

<form class="card filters" method="get" action="transactions.php">
    <?php if ($f['type'] !== ''): ?><input type="hidden" name="type" value="<?= e($f['type']) ?>"><?php endif; ?>
    <div class="field"><label for="q">Search</label><input class="input" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Reason, person or note"></div>
    <div class="field"><label for="account">Account</label>
        <select class="input" id="account" name="account"><option value="">All</option>
            <?php foreach ($accounts as $a): ?><option value="<?= (int) $a['id'] ?>"<?= selected($f['account'], $a['id']) ?>><?= e($a['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="field"><label for="category">Category</label>
        <select class="input" id="category" name="category"><option value="">All</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($f['category'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div class="field"><label for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
    <div class="field"><label for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" style="height:40px" type="submit"><?= icon('funnel') ?>Filter</button></div>
    <?php if ($filtered): ?><div class="field"><label>&nbsp;</label><a class="btn btn-ghost btn-sm" style="height:40px" href="transactions.php">Clear</a></div><?php endif; ?>
</form>

<div class="card mt">
    <?php if ($rows): ?>
        <div class="table-wrap">
            <table class="table table-stack">
                <thead><tr><th>Date</th><th>Reason</th><th>Category</th><th>Account</th><th class="num">Amount</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $t):
                    $isTransfer = $t['type'] === 'transfer';
                    $sign = $t['type'] === 'income' ? '+' : ($t['type'] === 'expense' ? '-' : '');
                    ?>
                    <tr>
                        <td data-label="Date"><?= e(fmt_date($t['txn_date'])) ?></td>
                        <td data-label="Reason">
                            <div class="cell-main">
                                <strong><?= e($t['reason'] !== '' ? $t['reason'] : ($isTransfer ? 'Transfer' : '-')) ?></strong>
                                <?php if ($t['party'] !== ''): ?><span><?= e(($t['type'] === 'income' ? 'From ' : 'To ') . $t['party']) ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td data-label="Category">
                            <?php if ($isTransfer): ?><span class="badge">Transfer</span>
                            <?php else: ?><span class="cell-cat"><i style="background:<?= e($t['category_color'] ?? '#94a3b8') ?>"></i><?= e($t['category_name'] ?? 'Uncategorised') ?></span>
                                <?php if ($t['necessity']): ?> <span class="badge <?= $t['necessity'] === 'want' ? 'warn' : ($t['necessity'] === 'saving' ? 'good' : 'info') ?>"><?= e(necessity_labels()[$t['necessity']]) ?></span><?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td data-label="Account"><?= e($t['account_name']) ?><?= $isTransfer ? ' &rarr; ' . e($t['to_account_name']) : '' ?></td>
                        <td data-label="Amount" class="num"><strong class="amt amt-<?= e($t['type']) ?>"><?= $sign . e(money($t['amount'])) ?></strong></td>
                        <td class="actions">
                            <a class="icon-btn sm" href="transaction.php?id=<?= (int) $t['id'] ?>" aria-label="Edit"><?= icon('pencil-square') ?></a>
                            <form method="post" data-confirm="This transaction will be removed and your balances will update." data-confirm-title="Delete transaction?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                <button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="summary-strip">
            <div><span>Income</span><b class="amt-income"><?= e(money($totals['income'])) ?></b></div>
            <div><span>Expenses</span><b class="amt-expense"><?= e(money($totals['expense'])) ?></b></div>
            <div><span>Net</span><b><?= e(money($totals['income'] - $totals['expense'])) ?></b></div>
        </div>
        <?php if ($pages > 1): ?>
            <div class="pagination">
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <div class="pager">
                    <?php
                    $window = array_unique(array_filter([1, $page - 1, $page, $page + 1, $pages], fn($p) => $p >= 1 && $p <= $pages));
                    sort($window);
                    $prev = 0;
                    foreach ($window as $p):
                        if ($p - $prev > 1): ?><span class="gap">&hellip;</span><?php endif;
                        $prev = $p;
                        if ($p === $page): ?><span class="current"><?= $p ?></span>
                        <?php else: ?><a href="<?= e($urlFor(['page' => $p])) ?>"><?= $p ?></a><?php endif;
                    endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?= $filtered
            ? empty_state('magnifying-glass', 'No matching transactions', 'Try widening the dates or clearing a filter.', 'Clear filters', 'transactions.php')
            : empty_state('arrows-right-left', 'No transactions yet', 'Record money you received or spent, along with the reason.', 'Add transaction', 'transaction.php') ?>
    <?php endif; ?>
</div>

<?php page_end();
