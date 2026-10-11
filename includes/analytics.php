<?php

function account_balances(int $uid): array
{
    $rows = db_all(
        "SELECT a.id, a.name, a.type, a.color, a.opening_balance, a.is_archived,
            a.opening_balance
            + COALESCE((SELECT SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE -t.amount END) FROM transactions t WHERE t.account_id = a.id), 0)
            + COALESCE((SELECT SUM(t2.amount) FROM transactions t2 WHERE t2.to_account_id = a.id AND t2.type = 'transfer'), 0) AS balance
         FROM accounts a WHERE a.user_id = ? ORDER BY a.is_archived, a.name",
        [$uid]
    );

    foreach ($rows as &$row) {
        $row['balance'] = (float) $row['balance'];
        $row['opening_balance'] = (float) $row['opening_balance'];
    }

    return $rows;
}

function liquid_balance(array $accounts): float
{
    return array_sum(array_column($accounts, 'balance'));
}

function period_totals(int $uid, string $from, string $to): array
{
    $result = ['income' => 0.0, 'expense' => 0.0, 'income_count' => 0, 'expense_count' => 0];
    $rows = db_all(
        "SELECT type, SUM(amount) AS total, COUNT(*) AS n FROM transactions
         WHERE user_id = ? AND txn_date BETWEEN ? AND ? AND type IN ('income', 'expense') GROUP BY type",
        [$uid, $from, $to]
    );

    foreach ($rows as $row) {
        $result[$row['type']] = (float) $row['total'];
        $result[$row['type'] . '_count'] = (int) $row['n'];
    }

    $result['net'] = $result['income'] - $result['expense'];
    $result['rate'] = $result['income'] > 0 ? $result['net'] / $result['income'] * 100 : null;

    return $result;
}

function monthly_totals(int $uid, string $from, string $to): array
{
    $rows = db_all(
        "SELECT DATE_FORMAT(txn_date, '%Y-%m') AS ym, type, SUM(amount) AS total FROM transactions
         WHERE user_id = ? AND txn_date BETWEEN ? AND ? AND type IN ('income', 'expense') GROUP BY ym, type",
        [$uid, $from, $to]
    );

    $series = [];
    $cursor = new DateTimeImmutable(substr($from, 0, 7) . '-01');
    $last = new DateTimeImmutable(substr($to, 0, 7) . '-01');

    while ($cursor <= $last) {
        $series[$cursor->format('Y-m')] = ['income' => 0.0, 'expense' => 0.0];
        $cursor = $cursor->modify('+1 month');
    }

    foreach ($rows as $row) {
        if (isset($series[$row['ym']])) {
            $series[$row['ym']][$row['type']] = (float) $row['total'];
        }
    }

    return $series;
}

function daily_totals(int $uid, string $from, string $to): array
{
    $rows = db_all(
        "SELECT txn_date, type, SUM(amount) AS total FROM transactions
         WHERE user_id = ? AND txn_date BETWEEN ? AND ? AND type IN ('income', 'expense') GROUP BY txn_date, type",
        [$uid, $from, $to]
    );

    $series = [];
    $cursor = new DateTimeImmutable($from);
    $last = new DateTimeImmutable($to);

    while ($cursor <= $last) {
        $series[$cursor->format('Y-m-d')] = ['income' => 0.0, 'expense' => 0.0];
        $cursor = $cursor->modify('+1 day');
    }

    foreach ($rows as $row) {
        $series[$row['txn_date']][$row['type']] = (float) $row['total'];
    }

    return $series;
}

function category_breakdown(int $uid, string $from, string $to, string $type): array
{
    $rows = db_all(
        "SELECT t.category_id AS id, COALESCE(c.name, 'Uncategorised') AS name, COALESCE(c.color, '#94a3b8') AS color,
            SUM(t.amount) AS total, COUNT(*) AS n
         FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ? AND t.type = ? AND t.txn_date BETWEEN ? AND ?
         GROUP BY t.category_id, c.name, c.color ORDER BY total DESC",
        [$uid, $type, $from, $to]
    );

    foreach ($rows as &$row) {
        $row['total'] = (float) $row['total'];
        $row['n'] = (int) $row['n'];
    }

    return $rows;
}

function party_breakdown(int $uid, string $from, string $to, string $type, int $limit = 8): array
{
    $rows = db_all(
        "SELECT COALESCE(NULLIF(t.party, ''), c.name, 'Unspecified') AS label, SUM(t.amount) AS total, COUNT(*) AS n
         FROM transactions t LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ? AND t.type = ? AND t.txn_date BETWEEN ? AND ?
         GROUP BY label ORDER BY total DESC LIMIT " . (int) $limit,
        [$uid, $type, $from, $to]
    );

    foreach ($rows as &$row) {
        $row['total'] = (float) $row['total'];
        $row['n'] = (int) $row['n'];
    }

    return $rows;
}

function necessity_totals(int $uid, string $from, string $to): array
{
    $result = ['need' => 0.0, 'want' => 0.0, 'saving' => 0.0, 'untagged' => 0.0];
    $rows = db_all(
        "SELECT COALESCE(necessity, 'untagged') AS k, SUM(amount) AS total FROM transactions
         WHERE user_id = ? AND type = 'expense' AND txn_date BETWEEN ? AND ? GROUP BY k",
        [$uid, $from, $to]
    );

    foreach ($rows as $row) {
        $result[$row['k']] = (float) $row['total'];
    }

    return $result;
}

function top_transactions(int $uid, string $from, string $to, string $type, int $limit = 5): array
{
    return db_all(
        "SELECT t.*, c.name AS category_name, c.color AS category_color, a.name AS account_name
         FROM transactions t
         LEFT JOIN categories c ON c.id = t.category_id
         JOIN accounts a ON a.id = t.account_id
         WHERE t.user_id = ? AND t.type = ? AND t.txn_date BETWEEN ? AND ?
         ORDER BY t.amount DESC LIMIT " . (int) $limit,
        [$uid, $type, $from, $to]
    );
}

function recent_transactions(int $uid, int $limit = 8): array
{
    return db_all(
        "SELECT t.*, c.name AS category_name, c.color AS category_color, a.name AS account_name, a2.name AS to_account_name
         FROM transactions t
         LEFT JOIN categories c ON c.id = t.category_id
         JOIN accounts a ON a.id = t.account_id
         LEFT JOIN accounts a2 ON a2.id = t.to_account_id
         WHERE t.user_id = ?
         ORDER BY t.txn_date DESC, t.id DESC LIMIT " . (int) $limit,
        [$uid]
    );
}

function balance_series(int $uid, string $from, string $to, float $liquid): array
{
    $after = (float) db_val(
        "SELECT COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) FROM transactions
         WHERE user_id = ? AND txn_date >= ? AND type IN ('income', 'expense')",
        [$uid, $from]
    );

    $running = $liquid - $after;
    $series = [];

    foreach (daily_totals($uid, $from, $to) as $date => $flow) {
        $running += $flow['income'] - $flow['expense'];
        $series[$date] = $running;
    }

    return $series;
}

function budget_status(int $uid, string $from, string $to): array
{
    $rows = db_all(
        "SELECT b.id, b.amount, c.id AS category_id, c.name, c.color,
            COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.user_id = b.user_id AND t.category_id = b.category_id
                AND t.type = 'expense' AND t.txn_date BETWEEN ? AND ?), 0) AS spent
         FROM budgets b JOIN categories c ON c.id = b.category_id
         WHERE b.user_id = ? ORDER BY c.name",
        [$from, $to, $uid]
    );

    foreach ($rows as &$row) {
        $row['amount'] = (float) $row['amount'];
        $row['spent'] = (float) $row['spent'];
        $row['ratio'] = $row['amount'] > 0 ? $row['spent'] / $row['amount'] : 0;
    }

    return $rows;
}

function open_debts(int $uid): array
{
    $rows = db_all(
        "SELECT *, (amount - paid_amount) AS remaining FROM debts WHERE user_id = ? AND amount - paid_amount > 0.004
         ORDER BY due_date IS NULL, due_date, id",
        [$uid]
    );

    foreach ($rows as &$row) {
        $row['amount'] = (float) $row['amount'];
        $row['paid_amount'] = (float) $row['paid_amount'];
        $row['remaining'] = (float) $row['remaining'];
    }

    return $rows;
}

function active_goals(int $uid): array
{
    $rows = db_all('SELECT * FROM goals WHERE user_id = ? ORDER BY (saved_amount >= target_amount), target_date IS NULL, target_date, id', [$uid]);

    foreach ($rows as &$row) {
        $row['target_amount'] = (float) $row['target_amount'];
        $row['saved_amount'] = (float) $row['saved_amount'];
    }

    return $rows;
}

function financial_snapshot(int $uid, array $user): array
{
    $today = new DateTimeImmutable('today');
    $todayStr = $today->format('Y-m-d');
    [$monthStart] = month_bounds(0);
    [$prevStart, $prevEnd] = month_bounds(-1);
    [$histStart] = month_bounds(-3);

    $cur = period_totals($uid, $monthStart, $todayStr);
    $prev = period_totals($uid, $prevStart, $prevEnd);
    $history = array_filter(monthly_totals($uid, $histStart, $prevEnd), fn($m) => $m['income'] > 0 || $m['expense'] > 0);
    $months = count($history);

    if ($months > 0) {
        $avgIncome = array_sum(array_column($history, 'income')) / $months;
        $avgExpense = array_sum(array_column($history, 'expense')) / $months;
    } else {
        $avgIncome = $cur['income'];
        $avgExpense = $cur['expense'];
    }

    $accounts = account_balances($uid);
    $liquid = liquid_balance($accounts);
    $debts = open_debts($uid);
    $owe = 0.0;
    $owed = 0.0;

    foreach ($debts as $debt) {
        if ($debt['kind'] === 'i_owe') {
            $owe += $debt['remaining'];
        } else {
            $owed += $debt['remaining'];
        }
    }

    $lastDate = db_val('SELECT MAX(txn_date) FROM transactions WHERE user_id = ?', [$uid]);

    return [
        'today' => $today,
        'monthStart' => $monthStart,
        'day' => (int) $today->format('j'),
        'daysInMonth' => (int) $today->format('t'),
        'cur' => $cur,
        'prev' => $prev,
        'historyMonths' => $months,
        'avgIncome' => $avgIncome,
        'avgExpense' => $avgExpense,
        'avgSurplus' => $avgIncome - $avgExpense,
        'accounts' => $accounts,
        'liquid' => $liquid,
        'debts' => $debts,
        'owe' => $owe,
        'owed' => $owed,
        'budgets' => budget_status($uid, $monthStart, $todayStr),
        'goals' => active_goals($uid),
        'necessity' => necessity_totals($uid, $monthStart, $todayStr),
        'categories' => category_breakdown($uid, $monthStart, $todayStr, 'expense'),
        'histCategories' => $months > 0 ? category_breakdown($uid, $histStart, $prevEnd, 'expense') : [],
        'topExpense' => top_transactions($uid, $monthStart, $todayStr, 'expense', 1)[0] ?? null,
        'lastDate' => $lastDate,
        'txnCount' => (int) db_val('SELECT COUNT(*) FROM transactions WHERE user_id = ?', [$uid]),
        'target' => (int) $user['savings_target_pct'],
        'emergency' => (int) $user['emergency_months'],
    ];
}

function build_insights(array $s): array
{
    $items = [];
    $add = function (string $level, string $icon, string $title, string $text) use (&$items) {
        $items[] = ['level' => $level, 'icon' => $icon, 'title' => $title, 'text' => $text];
    };

    if ($s['txnCount'] === 0) {
        $add('info', 'rocket-launch', 'Record your first transaction', 'Once you log money received and money spent, this page will show where your money goes, how fast you are saving and what needs attention.');

        return $items;
    }

    $cur = $s['cur'];
    $day = $s['day'];
    $dim = $s['daysInMonth'];
    $baseIncome = $cur['income'] > 0 ? $cur['income'] : $s['avgIncome'];

    if ($cur['income'] <= 0 && $cur['expense'] > 0) {
        $add('info', 'banknotes', 'No income recorded this month', 'You have spent ' . money($cur['expense']) . ' so far. Record the money you received so your savings rate stays accurate.');
    } elseif ($cur['income'] > 0) {
        $rate = $cur['rate'];
        if ($rate < 0) {
            $add('bad', 'exclamation-triangle', 'You are spending more than you earn', 'This month you have spent ' . money($cur['expense']) . ' against ' . money($cur['income']) . ' received, a gap of ' . money(abs($cur['net'])) . '. Pause non-essential spending until income catches up.');
        } elseif ($rate < $s['target']) {
            $gap = $cur['income'] * $s['target'] / 100 - $cur['net'];
            $level = $rate < 10 ? 'warn' : 'info';
            $add($level, 'chart-pie', 'Savings rate is ' . percent($rate) . ', target ' . $s['target'] . '%', 'You have kept ' . money($cur['net']) . ' of ' . money($cur['income']) . '. Holding back another ' . money($gap) . ' this month would reach your target.');
        } else {
            $add('good', 'check-circle', 'Strong savings rate', 'You are keeping ' . percent($rate) . ' of what you earn this month, above your ' . $s['target'] . '% target.');
        }
    }

    if ($s['historyMonths'] > 0 && $s['avgExpense'] > 0 && $day >= 7 && $cur['expense'] > 0) {
        $projected = $cur['expense'] / $day * $dim;
        $diff = ($projected / $s['avgExpense'] - 1) * 100;
        if ($diff > 15) {
            $add('warn', 'arrow-trending-up', 'Spending is running ahead of normal', 'At this pace you will spend about ' . money($projected) . ' this month, ' . percent($diff) . ' above your recent monthly average of ' . money($s['avgExpense']) . '.');
        } elseif ($diff < -15) {
            $add('good', 'arrow-trending-down', 'Spending is below your usual pace', 'You are on track for about ' . money($projected) . ' this month, ' . percent(abs($diff)) . ' less than your recent average.');
        }
    }

    if ($s['historyMonths'] > 0) {
        $hist = [];
        foreach ($s['histCategories'] as $c) {
            $hist[(int) $c['id']] = $c['total'] / $s['historyMonths'];
        }
        $spikes = 0;
        foreach ($s['categories'] as $c) {
            $avg = $hist[(int) $c['id']] ?? 0;
            if (!$c['id'] || $avg <= 0 || $spikes >= 2) {
                continue;
            }
            $expected = $avg * $day / $dim;
            if ($c['total'] > $expected * 1.5 && $c['total'] - $expected > max($baseIncome * 0.03, 1)) {
                $spikes++;
                $add('warn', 'fire', 'Higher than usual on ' . $c['name'], 'You have spent ' . money($c['total']) . ' on ' . $c['name'] . ' so far, while you normally spend about ' . money($expected) . ' by this point in the month.');
            }
        }
    }

    if ($s['budgets']) {
        $flagged = 0;
        foreach ($s['budgets'] as $b) {
            if ($flagged >= 3) {
                break;
            }
            $left = max(1, $dim - $day + 1);
            if ($b['ratio'] >= 1) {
                $flagged++;
                $add('bad', 'exclamation-circle', $b['name'] . ' budget exceeded', 'You have spent ' . money($b['spent']) . ' of ' . money($b['amount']) . ', over by ' . money($b['spent'] - $b['amount']) . '.');
            } elseif ($b['ratio'] >= 0.85) {
                $flagged++;
                $remaining = $b['amount'] - $b['spent'];
                $add('warn', 'exclamation-triangle', $b['name'] . ' budget almost used', percent($b['ratio'] * 100) . ' is used. ' . money($remaining) . ' remains for the next ' . $left . ' days, about ' . money($remaining / $left) . ' a day.');
            }
        }
        if ($flagged === 0) {
            $add('good', 'check-circle', 'All budgets are on track', 'None of your ' . count($s['budgets']) . ' category budgets is close to its limit.');
        }
    } elseif ($cur['expense'] > 0 && $s['categories']) {
        $top = $s['categories'][0];
        $add('info', 'adjustments-horizontal', 'Set a budget for ' . $top['name'], 'It is your largest expense this month at ' . money($top['total']) . '. A monthly limit turns it from something you notice afterwards into something you control.');
    }

    if ($s['avgExpense'] > 0) {
        $cover = $s['liquid'] / $s['avgExpense'];
        if ($s['liquid'] <= 0) {
            $add('bad', 'lifebuoy', 'No cash available', 'Your accounts add up to ' . money($s['liquid']) . '. Rebuilding even a small reserve should be the first priority.');
        } elseif ($cover < 1) {
            $add('bad', 'lifebuoy', 'Less than one month of expenses in reserve', 'Your accounts hold ' . money($s['liquid']) . ', enough for about ' . round($cover * 30) . ' days at your average spending of ' . money($s['avgExpense']) . ' a month.');
        } elseif ($cover < $s['emergency']) {
            $gap = $s['avgExpense'] * $s['emergency'] - $s['liquid'];
            $add('warn', 'shield-check', 'Emergency fund is below target', 'Your balance covers ' . number_format($cover, 1) . ' months of expenses and your target is ' . $s['emergency'] . '. Another ' . money($gap) . ' would close the gap.');
        } else {
            $add('good', 'shield-check', 'Healthy cash reserve', 'Your balance covers ' . number_format($cover, 1) . ' months of expenses, meeting your ' . $s['emergency'] . '-month target.');
        }
    }

    $need = $s['necessity']['need'];
    $want = $s['necessity']['want'];
    if ($baseIncome > 0 && $want / $baseIncome > 0.3) {
        $add('warn', 'scale', 'Wants take ' . percent($want / $baseIncome * 100) . ' of income', 'Spending tagged as wants is ' . money($want) . ', above the common 30% guideline. Trimming it is the fastest way to raise your savings.');
    } elseif ($baseIncome > 0 && $need / $baseIncome > 0.5) {
        $add('info', 'scale', 'Needs take ' . percent($need / $baseIncome * 100) . ' of income', 'Essentials above 50% leave little room to save. Review housing, transport and food first, since they move the total most.');
    }

    $dueSoon = 0;
    foreach ($s['debts'] as $d) {
        if (!$d['due_date'] || $dueSoon >= 3) {
            continue;
        }
        $days = days_until($d['due_date']);
        if ($d['kind'] === 'i_owe' && $days < 0) {
            $dueSoon++;
            $add('bad', 'clock', 'Overdue to ' . $d['person'], money($d['remaining']) . ' was due ' . abs($days) . ' days ago. Settle it or agree a new date to protect the relationship.');
        } elseif ($d['kind'] === 'i_owe' && $days <= 7) {
            $dueSoon++;
            $add('warn', 'clock', 'Payment due soon to ' . $d['person'], money($d['remaining']) . ' is due ' . ($days === 0 ? 'today' : 'in ' . $days . ' days') . '. Set it aside now.');
        } elseif ($d['kind'] === 'owed_to_me' && $days < 0) {
            $dueSoon++;
            $add('warn', 'hand-raised', $d['person'] . ' is late paying you', money($d['remaining']) . ' was due ' . abs($days) . ' days ago. A reminder is worth sending.');
        }
    }

    if ($s['owe'] > 0 && $s['avgIncome'] > 0 && $s['owe'] > $s['avgIncome']) {
        $add('warn', 'scale', 'Debt is larger than a month of income', 'You owe ' . money($s['owe']) . ' against average monthly income of ' . money($s['avgIncome']) . '. Clear the smallest balances first to free up cash flow.');
    }

    $goalNotes = 0;
    foreach ($s['goals'] as $g) {
        if ($goalNotes >= 2) {
            break;
        }
        $remaining = $g['target_amount'] - $g['saved_amount'];
        if ($remaining <= 0 || !$g['target_date']) {
            continue;
        }
        $days = days_until($g['target_date']);
        $goalNotes++;
        if ($days < 0) {
            $add('bad', 'flag', $g['name'] . ' missed its date', money($remaining) . ' is still missing. Move the date or raise your monthly contribution.');
            continue;
        }
        $needed = $remaining / max(1, $days / 30.44);
        if ($s['avgSurplus'] > 0 && $needed <= $s['avgSurplus']) {
            $add('good', 'flag', $g['name'] . ' is within reach', 'Setting aside ' . money($needed) . ' a month reaches it by ' . fmt_date($g['target_date']) . ', which fits inside your average surplus of ' . money($s['avgSurplus']) . '.');
        } else {
            $add('warn', 'flag', $g['name'] . ' needs ' . money($needed) . ' a month', 'To reach it by ' . fmt_date($g['target_date']) . ' you must save more than your average surplus of ' . money(max(0, $s['avgSurplus'])) . '. Extend the date or cut spending.');
        }
    }

    if ($s['topExpense'] && $baseIncome > 0 && $s['topExpense']['amount'] / $baseIncome > 0.3) {
        $t = $s['topExpense'];
        $label = $t['reason'] ?: ($t['party'] ?: ($t['category_name'] ?? 'One expense'));
        $add('info', 'receipt-percent', 'One expense stands out', $label . ' cost ' . money($t['amount']) . ', which is ' . percent($t['amount'] / $baseIncome * 100) . ' of this month\'s income.');
    }

    if ($s['lastDate']) {
        $gapDays = -days_until($s['lastDate']);
        if ($gapDays >= 4) {
            $add('info', 'clipboard-document-list', 'Your records may be behind', 'The last entry was ' . $gapDays . ' days ago. Recording daily keeps every figure here reliable.');
        }
    }

    $order = ['bad' => 0, 'warn' => 1, 'info' => 2, 'good' => 3];
    usort($items, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);

    return $items;
}

function health_score(array $s): ?array
{
    if ($s['txnCount'] === 0) {
        return null;
    }

    $cur = $s['cur'];
    $rate = $cur['rate'];
    if ($rate === null && $s['prev']['income'] > 0) {
        $rate = $s['prev']['rate'];
    }

    $target = max(1, $s['target']);
    $savings = $rate === null ? 0 : max(0, min(1, $rate / $target)) * 35;

    $runway = 0;
    if ($s['avgExpense'] > 0) {
        $runway = max(0, min(1, ($s['liquid'] / $s['avgExpense']) / max(1, $s['emergency']))) * 25;
    } elseif ($s['liquid'] > 0) {
        $runway = 25;
    }

    if ($s['budgets']) {
        $over = count(array_filter($s['budgets'], fn($b) => $b['ratio'] > 1));
        $budget = (1 - $over / count($s['budgets'])) * 20;
    } else {
        $budget = 10;
    }

    $debt = 20;
    if ($s['owe'] > 0) {
        $debt = $s['avgIncome'] > 0 ? 20 * (1 - min(1, $s['owe'] / ($s['avgIncome'] * 2))) : 0;
        foreach ($s['debts'] as $d) {
            if ($d['kind'] === 'i_owe' && $d['due_date'] && days_until($d['due_date']) < 0) {
                $debt -= 5;
            }
        }
        $debt = max(0, $debt);
    }

    $score = (int) round($savings + $runway + $budget + $debt);
    $label = $score >= 80 ? 'Excellent' : ($score >= 65 ? 'Good' : ($score >= 45 ? 'Fair' : 'Needs attention'));
    $tone = $score >= 65 ? 'good' : ($score >= 45 ? 'warn' : 'bad');

    return [
        'score' => $score,
        'label' => $label,
        'tone' => $tone,
        'parts' => [
            ['name' => 'Savings rate', 'value' => $savings, 'max' => 35],
            ['name' => 'Cash reserve', 'value' => $runway, 'max' => 25],
            ['name' => 'Budget discipline', 'value' => $budget, 'max' => 20],
            ['name' => 'Debt health', 'value' => $debt, 'max' => 20],
        ],
    ];
}

function evaluate_decision(array $s, array $in): array
{
    $cost = $in['cost'];
    $kind = $in['kind'];
    $liquid = $s['liquid'];
    $income = $s['avgIncome'];
    $expense = $s['avgExpense'];
    $surplus = $s['avgSurplus'];
    $cushion = $expense * $s['emergency'];
    $spendable = $liquid - $cushion;
    $score = 100;
    $reasons = [];
    $tips = [];

    $payNow = $in['mode'] === 'installments' ? 0.0 : $cost;
    $monthly = $in['mode'] === 'installments' ? $in['monthly'] : 0.0;

    if ($in['mode'] === 'installments') {
        $total = $monthly * $in['months'];
        $reasons[] = ['neutral', 'Paying ' . money($monthly) . ' for ' . $in['months'] . ' months totals ' . money($total) . ($total > $cost ? ', which is ' . money($total - $cost) . ' more than the price.' : '.')];

        if ($surplus <= 0) {
            $score -= 45;
            $reasons[] = ['bad', 'You have no monthly surplus right now, so every instalment would come out of savings or new debt.'];
        } elseif ($monthly > $surplus) {
            $score -= 40;
            $reasons[] = ['bad', 'The instalment of ' . money($monthly) . ' is larger than your average monthly surplus of ' . money($surplus) . '.'];
        } elseif ($monthly > $surplus * 0.5) {
            $score -= 18;
            $reasons[] = ['warn', 'The instalment would use ' . percent($monthly / $surplus * 100) . ' of your monthly surplus, leaving little room for anything else.'];
        } else {
            $reasons[] = ['good', 'The instalment uses ' . percent($monthly / $surplus * 100) . ' of your monthly surplus, which is manageable.'];
        }

        if ($income > 0 && $monthly / $income > 0.2) {
            $score -= 15;
            $reasons[] = ['warn', 'Repayments would take ' . percent($monthly / $income * 100) . ' of your monthly income. Staying below 20% keeps debt comfortable.'];
        }
    } else {
        $after = $liquid - $payNow;
        $cover = $expense > 0 ? $after / $expense : null;

        if ($after < 0) {
            $score -= 100;
            $reasons[] = ['bad', 'The cost is ' . money($cost) . ' but your accounts only hold ' . money($liquid) . '.'];
        } elseif ($cover !== null && $cover < 1) {
            $score -= 50;
            $reasons[] = ['bad', 'After paying you would have ' . money($after) . ', less than one month of your usual expenses.'];
        } elseif ($cover !== null && $cover < $s['emergency']) {
            $score -= 25;
            $reasons[] = ['warn', 'After paying you would hold ' . number_format($cover, 1) . ' months of expenses, below your ' . $s['emergency'] . '-month emergency target.'];
        } else {
            $reasons[] = ['good', 'After paying you would still have ' . money($after) . ($cover !== null ? ', about ' . number_format($cover, 1) . ' months of expenses.' : '.')];
        }

        if ($income > 0) {
            $share = $cost / $income;
            if ($share > 0.5) {
                $score -= 25;
                $reasons[] = ['warn', 'The price equals ' . percent($share * 100) . ' of a typical month\'s income.'];
            } elseif ($share > 0.25) {
                $score -= 10;
                $reasons[] = ['neutral', 'The price equals ' . percent($share * 100) . ' of a typical month\'s income.'];
            }
        }

        if ($surplus <= 0) {
            $score -= 15;
            $reasons[] = ['warn', 'You are not saving on average right now, so this purchase would not be replaced by future savings.'];
        }
    }

    if ($s['owe'] > 0 && $kind === 'want') {
        $score -= 10;
        $reasons[] = ['warn', 'You still owe ' . money($s['owe']) . '. Clearing debts usually beats a new want.'];
    }

    foreach ($s['goals'] as $g) {
        if ($g['saved_amount'] < $g['target_amount'] && $kind === 'want' && $payNow > 0) {
            $reasons[] = ['neutral', 'The same money could add ' . percent(min(100, $payNow / max(1, $g['target_amount']) * 100)) . ' of the way to your "' . $g['name'] . '" goal.'];
            break;
        }
    }

    if ($kind === 'need') {
        $score += 15;
        $reasons[] = ['neutral', 'Marked as a need, so the assessment is more forgiving. Still compare prices before paying.'];
    }

    $score = max(0, min(100, $score));
    $verdict = $score >= 70 ? 'go' : ($score >= 40 ? 'wait' : 'avoid');

    $headline = [
        'go' => 'You can afford this',
        'wait' => 'Wait before buying',
        'avoid' => 'Not affordable right now',
    ][$verdict];

    if ($verdict !== 'go') {
        $required = $payNow > 0 ? $payNow - max(0, $spendable) : 0;
        if ($payNow > 0 && $required > 0 && $surplus > 0) {
            $months = (int) ceil($required / $surplus);
            $tips[] = 'Set aside ' . money($required / $months) . ' each month and you can pay in about ' . $months . ' ' . ($months === 1 ? 'month' : 'months') . ' without touching your emergency cushion.';
        } elseif ($payNow > 0 && $required > 0) {
            $tips[] = 'Raise your monthly surplus first. At the moment there is nothing left over to save toward this purchase.';
        }
        if ($kind === 'want') {
            $tips[] = 'Wait 30 days. If you still want it after a month, the purchase is more likely to be a good one.';
        } else {
            $tips[] = 'Look for a cheaper option, a second-hand version or a split payment to protect your reserve.';
        }
        if ($in['mode'] !== 'installments' && $monthly === 0.0) {
            $tips[] = 'Paying in instalments is an option, but only if the repayment fits comfortably inside your monthly surplus.';
        }
    } else {
        $tips[] = 'Record the purchase as a transaction after you pay so your reports stay accurate.';
    }

    return [
        'verdict' => $verdict,
        'headline' => $headline,
        'score' => $score,
        'reasons' => $reasons,
        'tips' => $tips,
        'metrics' => [
            'Available now' => $liquid,
            'Above emergency cushion' => $spendable,
            'Average monthly income' => $income,
            'Average monthly surplus' => $surplus,
        ],
    ];
}
