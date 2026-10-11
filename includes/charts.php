<?php

function nice_max(float $max): float
{
    if ($max <= 0) {
        return 1;
    }

    $exp = floor(log10($max));
    $base = pow(10, $exp);
    $fraction = $max / $base;

    foreach ([1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10] as $step) {
        if ($fraction <= $step) {
            return $step * $base;
        }
    }

    return 10 * $base;
}

function chart_empty(string $message = 'No data for this period'): string
{
    return '<div class="chart-empty">' . icon('chart-bar', 'icon-lg') . '<span>' . e($message) . '</span></div>';
}

function chart_bars(array $labels, array $series, int $height = 280): string
{
    $count = count($labels);
    $all = [];
    foreach ($series as $s) {
        $all = array_merge($all, $s['values']);
    }

    if ($count === 0 || max($all ?: [0]) <= 0) {
        return chart_empty();
    }

    $width = 680;
    $left = 52;
    $right = 8;
    $top = 14;
    $bottom = 30;
    $plotW = $width - $left - $right;
    $plotH = $height - $top - $bottom;
    $max = nice_max(max($all));
    $groupW = $plotW / $count;
    $seriesCount = count($series);
    $barW = min(26, ($groupW * 0.7) / $seriesCount);
    $gap = min(4, $barW * 0.2);
    $groupInner = $barW * $seriesCount + $gap * ($seriesCount - 1);
    $labelEvery = $count > 16 ? (int) ceil($count / 12) : 1;

    $svg = '<svg class="chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" preserveAspectRatio="xMidYMid meet">';

    for ($i = 0; $i <= 4; $i++) {
        $y = $top + $plotH - ($plotH * $i / 4);
        $svg .= '<line class="chart-grid" x1="' . $left . '" x2="' . ($width - $right) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
        $svg .= '<text class="chart-axis" x="' . ($left - 8) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . compact_number($max * $i / 4) . '</text>';
    }

    foreach ($labels as $i => $label) {
        $gx = $left + $groupW * $i + ($groupW - $groupInner) / 2;
        foreach ($series as $k => $s) {
            $value = (float) ($s['values'][$i] ?? 0);
            $h = $plotH * $value / $max;
            $x = $gx + $k * ($barW + $gap);
            $y = $top + $plotH - $h;
            if ($value > 0) {
                $svg .= '<rect class="' . e($s['class']) . '" x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round(max($h, 1), 1) . '" rx="4"><title>' . e($label . ' - ' . $s['name'] . ': ' . money($value)) . '</title></rect>';
            }
        }
        if ($i % $labelEvery === 0) {
            $svg .= '<text class="chart-axis" x="' . round($left + $groupW * ($i + 0.5), 1) . '" y="' . ($height - 8) . '" text-anchor="middle">' . e($label) . '</text>';
        }
    }

    return $svg . '</svg>';
}

function chart_line(array $labels, array $values, int $height = 260): string
{
    $count = count($values);

    if ($count < 2) {
        return chart_empty();
    }

    $width = 680;
    $left = 52;
    $right = 12;
    $top = 14;
    $bottom = 30;
    $plotW = $width - $left - $right;
    $plotH = $height - $top - $bottom;
    $min = min($values);
    $max = max($values);

    if ($max == $min) {
        $max = $min + 1;
    }

    $span = $max - $min;
    $pad = $span * 0.1;
    $min -= $pad;
    $max += $pad;
    $span = $max - $min;
    $points = [];

    foreach ($values as $i => $v) {
        $x = $left + $plotW * $i / ($count - 1);
        $y = $top + $plotH - $plotH * ($v - $min) / $span;
        $points[] = [round($x, 1), round($y, 1)];
    }

    $line = '';
    foreach ($points as $i => [$x, $y]) {
        $line .= ($i === 0 ? 'M' : 'L') . $x . ' ' . $y . ' ';
    }
    $area = $line . 'L' . $points[$count - 1][0] . ' ' . ($top + $plotH) . ' L' . $points[0][0] . ' ' . ($top + $plotH) . ' Z';

    $svg = '<svg class="chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" preserveAspectRatio="xMidYMid meet">';
    $svg .= '<defs><linearGradient id="lineFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" class="stop-top"/><stop offset="100%" class="stop-bottom"/></linearGradient></defs>';

    for ($i = 0; $i <= 4; $i++) {
        $y = $top + $plotH - ($plotH * $i / 4);
        $svg .= '<line class="chart-grid" x1="' . $left . '" x2="' . ($width - $right) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '"/>';
        $svg .= '<text class="chart-axis" x="' . ($left - 8) . '" y="' . round($y + 4, 1) . '" text-anchor="end">' . compact_number($min + $span * $i / 4) . '</text>';
    }

    $svg .= '<path class="chart-area" d="' . $area . '"/>';
    $svg .= '<path class="chart-stroke" d="' . trim($line) . '"/>';

    $every = max(1, (int) ceil($count / 8));
    foreach ($labels as $i => $label) {
        if ($i % $every === 0) {
            $svg .= '<text class="chart-axis" x="' . $points[$i][0] . '" y="' . ($height - 8) . '" text-anchor="middle">' . e($label) . '</text>';
        }
    }

    $hitW = $plotW / max(1, $count - 1);
    foreach ($points as $i => [$x, $y]) {
        $svg .= '<g class="chart-point"><circle cx="' . $x . '" cy="' . $y . '" r="4"/><rect x="' . round($x - $hitW / 2, 1) . '" y="' . $top . '" width="' . round($hitW, 1) . '" height="' . $plotH . '" fill="transparent"><title>' . e($labels[$i] . ': ' . money($values[$i])) . '</title></rect></g>';
    }

    return $svg . '</svg>';
}

function chart_donut(array $items, string $centerLabel = '', string $centerValue = ''): string
{
    $total = array_sum(array_column($items, 'value'));

    if ($total <= 0) {
        return chart_empty('Nothing recorded yet');
    }

    $svg = '<svg class="donut" viewBox="0 0 42 42" role="img">';
    $svg .= '<circle class="donut-track" cx="21" cy="21" r="15.9155" fill="none" stroke-width="5"/>';
    $offset = 0;

    foreach ($items as $item) {
        $pct = $item['value'] / $total * 100;
        if ($pct <= 0) {
            continue;
        }
        $svg .= '<circle cx="21" cy="21" r="15.9155" fill="none" stroke="' . e($item['color']) . '" stroke-width="5" pathLength="100" stroke-dasharray="' . round(max($pct - 0.4, 0.1), 2) . ' ' . round(100 - max($pct - 0.4, 0.1), 2) . '" stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 21 21)"><title>' . e($item['label'] . ': ' . money($item['value']) . ' (' . round($pct) . '%)') . '</title></circle>';
        $offset += $pct;
    }

    if ($centerValue !== '') {
        $svg .= '<text class="donut-label" x="21" y="19.5" text-anchor="middle">' . e($centerLabel) . '</text>';
        $svg .= '<text class="donut-value" x="21" y="24.5" text-anchor="middle">' . e($centerValue) . '</text>';
    }

    return $svg . '</svg>';
}

function chart_ring(int $score, string $tone): string
{
    $score = max(0, min(100, $score));

    return '<svg class="ring ring-' . e($tone) . '" viewBox="0 0 42 42" role="img">'
        . '<circle class="ring-track" cx="21" cy="21" r="15.9155" fill="none" stroke-width="3.2"/>'
        . '<circle class="ring-fill" cx="21" cy="21" r="15.9155" fill="none" stroke-width="3.2" stroke-linecap="round" pathLength="100" stroke-dasharray="' . $score . ' ' . (100 - $score) . '" transform="rotate(-90 21 21)"/>'
        . '<text class="ring-value" x="21" y="23.2" text-anchor="middle">' . $score . '</text>'
        . '</svg>';
}
