<?php

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/analytics.php';
require __DIR__ . '/includes/charts.php';
require __DIR__ . '/includes/layout.php';

$user = require_login();
$uid = (int) $user['id'];

if (is_post() && post('action') === 'delete') {
    csrf_verify();
    db_exec('DELETE FROM decisions WHERE id = ? AND user_id = ?', [(int) post('id'), $uid]);
    flash('success', 'Removed from history.');
    redirect('decisions.php');
}

$input = ['item' => '', 'cost' => '', 'kind' => 'want', 'mode' => 'once', 'monthly' => '', 'months' => '12'];
$errors = [];
$result = null;

if (is_post()) {
    csrf_verify();
    $input['item'] = mb_substr(post('item'), 0, 160);
    $input['cost'] = post('cost');
    $input['kind'] = post('kind') === 'need' ? 'need' : 'want';
    $input['mode'] = post('mode') === 'installments' ? 'installments' : 'once';
    $input['monthly'] = post('monthly');
    $input['months'] = post('months', '12');

    $cost = parse_amount($input['cost']);
    $monthly = $input['mode'] === 'installments' ? parse_amount($input['monthly']) : 0.0;
    $months = (int) $input['months'];

    if ($input['item'] === '') {
        $errors[] = 'Say what you are thinking of buying.';
    }
    if ($cost === null || $cost <= 0) {
        $errors[] = 'Enter the total price.';
    }
    if ($input['mode'] === 'installments') {
        if ($monthly === null || $monthly <= 0) {
            $errors[] = 'Enter the monthly instalment.';
        }
        if ($months < 2 || $months > 120) {
            $errors[] = 'Instalments must run for between 2 and 120 months.';
        }
    }

    if (!$errors) {
        $s = financial_snapshot($uid, $user);
        $result = evaluate_decision($s, [
            'cost' => $cost,
            'kind' => $input['kind'],
            'mode' => $input['mode'],
            'monthly' => (float) $monthly,
            'months' => $months,
        ]);

        db_exec(
            'INSERT INTO decisions (user_id, item, cost, kind, verdict, summary) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $input['item'], $cost, $input['kind'], $result['verdict'], mb_substr($result['headline'], 0, 500)]
        );
    }
}

$history = db_all('SELECT * FROM decisions WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$uid]);
$verdictIcon = ['go' => 'check-circle', 'wait' => 'clock', 'avoid' => 'exclamation-triangle'];
$reasonIcon = ['good' => 'check-circle', 'warn' => 'exclamation-triangle', 'bad' => 'exclamation-circle', 'neutral' => 'information-circle'];

page_start('Decision helper', 'decisions', 'Check whether a purchase fits your finances');
?>

<section class="grid grid-main">
    <div class="stack">
        <form class="card" method="post" novalidate>
            <?= csrf_field() ?>
            <div class="card-pad" style="display:grid;gap:20px">
                <div>
                    <h2 style="font-size:17px">Can I afford it?</h2>
                    <p style="color:var(--muted);font-size:14px">Describe what you want to buy. The result uses your balances, typical income, spending, debts and goals.</p>
                </div>

                <?php if ($errors): ?><div class="flash flash-error"><?= icon('exclamation-circle') ?><span><?= e(implode(' ', $errors)) ?></span></div><?php endif; ?>

                <div class="form-grid">
                    <div class="field full"><label for="item">What do you want to buy?</label><input class="input" id="item" name="item" maxlength="160" value="<?= e($input['item']) ?>" placeholder="For example: Laptop, motorbike, wedding gift" required></div>
                    <div class="field"><label for="cost">Total price</label>
                        <div class="input-prefix"><span><?= e(current_currency()) ?></span><input class="input" id="cost" name="cost" inputmode="decimal" autocomplete="off" value="<?= e($input['cost']) ?>" placeholder="0" required></div></div>
                    <div class="field"><label>Is it a need or a want?</label>
                        <div class="chips">
                            <label><input type="radio" name="kind" value="need"<?= checked($input['kind'], 'need') ?>><span>Need</span></label>
                            <label><input type="radio" name="kind" value="want"<?= checked($input['kind'], 'want') ?>><span>Want</span></label>
                        </div></div>
                    <div class="field full"><label>How would you pay?</label>
                        <div class="chips">
                            <label><input type="radio" name="mode" value="once"<?= checked($input['mode'], 'once') ?>><span>Pay in full now</span></label>
                            <label><input type="radio" name="mode" value="installments"<?= checked($input['mode'], 'installments') ?>><span>Monthly instalments</span></label>
                        </div></div>
                    <div class="field" data-mode="installments"><label for="monthly">Monthly instalment</label>
                        <div class="input-prefix"><span><?= e(current_currency()) ?></span><input class="input" id="monthly" name="monthly" inputmode="decimal" autocomplete="off" value="<?= e($input['monthly']) ?>" placeholder="0"></div></div>
                    <div class="field" data-mode="installments"><label for="months">Number of months</label><input class="input" id="months" name="months" type="number" min="2" max="120" value="<?= e($input['months']) ?>"></div>
                </div>

                <div><button class="btn btn-primary" type="submit"><?= icon('calculator') ?>Check affordability</button></div>
            </div>
        </form>

        <?php if ($result): ?>
            <article class="hero-verdict <?= e($result['verdict']) ?>">
                <span class="verdict-ico"><?= icon($verdictIcon[$result['verdict']]) ?></span>
                <div>
                    <h2><?= e($result['headline']) ?></h2>
                    <p><?= e($input['item']) ?> · affordability score <?= (int) $result['score'] ?>/100</p>
                </div>
            </article>

            <article class="card">
                <div class="card-head"><div><h2>Why</h2></div></div>
                <div class="card-body">
                    <div class="reason-list">
                        <?php foreach ($result['reasons'] as [$tone, $text]): ?>
                            <div class="reason <?= e($tone) ?>"><?= icon($reasonIcon[$tone]) ?><span><?= e($text) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="card">
                <div class="card-head"><div><h2>What to do</h2></div></div>
                <div class="card-body">
                    <div class="reason-list">
                        <?php foreach ($result['tips'] as $tip): ?>
                            <div class="reason good"><?= icon('light-bulb') ?><span><?= e($tip) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <div class="stack">
        <article class="card">
            <div class="card-head"><div><h2><?= $result ? 'Numbers used' : 'How it decides' ?></h2></div></div>
            <div class="card-body">
                <?php if ($result): ?>
                    <div class="metric-list">
                        <?php foreach ($result['metrics'] as $label => $value): ?>
                            <div class="metric"><span><?= e($label) ?></span><b class="<?= $value < 0 ? 'amt-expense' : '' ?>"><?= e(money($value)) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="reason-list">
                        <div class="reason neutral"><?= icon('wallet') ?><span>Whether the money is actually there once the purchase is paid.</span></div>
                        <div class="reason neutral"><?= icon('shield-check') ?><span>Whether your emergency cushion survives.</span></div>
                        <div class="reason neutral"><?= icon('scale') ?><span>How large the price is next to a normal month of income.</span></div>
                        <div class="reason neutral"><?= icon('users') ?><span>Debts you still owe and instalments against your monthly surplus.</span></div>
                    </div>
                <?php endif; ?>
            </div>
        </article>

        <article class="card">
            <div class="card-head"><div><h2>Recent checks</h2></div></div>
            <div class="card-body">
                <?php if ($history): ?>
                    <div class="list-rows">
                        <?php foreach ($history as $h): ?>
                            <div class="list-row">
                                <div class="list-main"><strong><?= e($h['item']) ?></strong><span><?= e(money($h['cost'])) ?> · <?= e(fmt_date(substr($h['created_at'], 0, 10))) ?></span></div>
                                <span class="badge <?= $h['verdict'] === 'go' ? 'good' : ($h['verdict'] === 'wait' ? 'warn' : 'bad') ?>"><?= e(['go' => 'Go ahead', 'wait' => 'Wait', 'avoid' => 'Avoid'][$h['verdict']]) ?></span>
                                <form method="post" data-confirm="This check will be removed from your history." data-confirm-title="Remove check?" data-confirm-button="Remove">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                    <button class="icon-btn sm danger" type="submit" aria-label="Remove"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?= chart_empty('Your checks will be listed here') ?>
                <?php endif; ?>
            </div>
        </article>
    </div>
</section>

<?php page_end();
