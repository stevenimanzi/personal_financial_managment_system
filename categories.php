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
        $color = valid_color(post('color'));

        if ($name === '' || !in_array($type, ['income', 'expense'], true)) {
            flash('error', 'Enter a name and choose income or expense.');
        } else {
            $duplicate = db_val('SELECT id FROM categories WHERE user_id = ? AND type = ? AND name = ? AND id <> ?', [$uid, $type, $name, $id]);
            if ($duplicate) {
                flash('error', 'You already have a ' . $type . ' category with that name.');
            } elseif ($id) {
                $current = db_val('SELECT type FROM categories WHERE id = ? AND user_id = ?', [$id, $uid]);
                if ($current && $current !== $type && db_val('SELECT id FROM transactions WHERE category_id = ? LIMIT 1', [$id])) {
                    flash('error', 'This category already has transactions, so its type cannot change.');
                } else {
                    db_exec('UPDATE categories SET name = ?, type = ?, color = ? WHERE id = ? AND user_id = ?', [$name, $type, $color, $id, $uid]);
                    flash('success', 'Category updated.');
                }
            } else {
                db_exec('INSERT INTO categories (user_id, name, type, color) VALUES (?, ?, ?, ?)', [$uid, $name, $type, $color]);
                flash('success', 'Category added.');
            }
        }
    } elseif ($action === 'delete') {
        db_exec('DELETE FROM categories WHERE id = ? AND user_id = ?', [$id, $uid]);
        flash('success', 'Category deleted. Its transactions are now uncategorised.');
    }

    redirect('categories.php');
}

[$from, $to] = month_bounds(0);
$rows = db_all(
    "SELECT c.id, c.name, c.type, c.color,
        (SELECT COUNT(*) FROM transactions t WHERE t.category_id = c.id) AS uses,
        COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.category_id = c.id AND t.txn_date BETWEEN ? AND ?), 0) AS month_total
     FROM categories c WHERE c.user_id = ? ORDER BY c.name",
    [$from, $to, $uid]
);

$groups = ['income' => [], 'expense' => []];
foreach ($rows as $r) {
    $groups[$r['type']][] = $r;
}

page_start('Categories', 'categories', 'Group your money by source and purpose');
?>

<div class="toolbar">
    <p style="color:var(--muted)">Categories explain where money comes from and where it goes. Totals shown are for this month.</p>
    <button class="btn btn-primary" type="button" data-open-dialog="categoryDialog" data-title="Add category"><?= icon('plus') ?>Add category</button>
</div>

<div class="grid grid-even">
    <?php foreach (['income' => 'Income categories', 'expense' => 'Expense categories'] as $type => $heading): ?>
        <section class="card">
            <div class="card-head"><div><h2><?= e($heading) ?></h2><p><?= count($groups[$type]) ?> categories</p></div></div>
            <div class="card-body">
                <?php if ($groups[$type]): ?>
                    <div class="list-rows">
                        <?php foreach ($groups[$type] as $c): ?>
                            <div class="list-row">
                                <span class="dot-ico" style="--c:<?= e($c['color']) ?>"><?= icon($type === 'income' ? 'arrow-down-circle' : 'arrow-up-circle') ?></span>
                                <div class="list-main"><strong><?= e($c['name']) ?></strong><span><?= (int) $c['uses'] ?> <?= (int) $c['uses'] === 1 ? 'transaction' : 'transactions' ?></span></div>
                                <b class="amt amt-<?= e($type) ?>" style="font-size:14px"><?= e(money($c['month_total'])) ?></b>
                                <button class="icon-btn sm" type="button" aria-label="Edit" data-open-dialog="categoryDialog" data-title="Edit category" data-fill="<?= e(json_encode(['id' => $c['id'], 'name' => $c['name'], 'type' => $c['type'], 'color' => $c['color']])) ?>"><?= icon('pencil-square') ?></button>
                                <form method="post" data-confirm="Transactions in this category will become uncategorised and any budget for it will be removed." data-confirm-title="Delete category?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button class="icon-btn sm danger" type="submit" aria-label="Delete"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= empty_state('tag', 'No categories', 'Add one to start grouping your ' . $type . '.') ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php dialog_start('categoryDialog', 'Add category'); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="field"><label>Type</label>
        <div class="segmented">
            <label><input type="radio" name="type" value="income"><span>Income</span></label>
            <label><input type="radio" name="type" value="expense" checked><span>Expense</span></label>
        </div></div>
    <div class="field"><label for="cat_name">Name</label><input class="input" id="cat_name" name="name" maxlength="80" placeholder="For example: School fees" required></div>
    <div class="field"><label>Colour</label><?= swatch_picker('color') ?></div>
<?php dialog_end('Save category'); ?>

<?php page_end();
