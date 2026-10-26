<?php
/*
 * Assets in low-security NPC stations (security < 0.5)
 * Joins: EVE_ASSETS.location_id = staStations.stationID
 *        EVE_ASSETS.toon_number = PILOTS.toon_number
 * Requires config.php to create the mysqli connection in $link.
 * GPL 3.0
 * Alfonso Orozco AGuilar USing CLaude Sonnet 5.5
 */
require '../config.php';

$lsr_sec_limit    = 0.5;           // Report everything strictly below this security level
$lsr_tbl_stations = 'staStations'; // Change the case here if your table is named differently

// Pocket badge color (skipped if your main code already defines it, so merging is safe)
if (!function_exists('getColorPocket')) {
    function getColorPocket($p)
    {
        return match (strtoupper(trim((string)$p))) {
            'EXPER' => '#28a745',
            'CLEAN' => '#0078d7',
            'SANGO' => '#ffc107',
            'LUCKY' => '#6f42c1',
            'NOKIA' => '#e81123',
            'YENN'  => '#cccccc',
            'OTHER' => '#fd7e14',
            default => '#444444',
        };
    }
}

// Readable text color (black or white) for a given badge background
function lsr_text_color($hex)
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return (($r * 299 + $g * 587 + $b * 114) / 1000) > 150 ? '#111111' : '#ffffff';
}

function lsr_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$lsr_sql = "SELECT a.toon_number, p.toon_name, p.pocket6,
                   s.stationName, s.security,
                   a.location_flag, a.type_description, a.quantity, a.forge_value
            FROM EVE_ASSETS a
            INNER JOIN `$lsr_tbl_stations` s ON s.stationID = a.location_id
            LEFT JOIN PILOTS p ON p.toon_number = a.toon_number
            WHERE s.security < " . (float)$lsr_sec_limit . "
            ORDER BY p.toon_name, s.stationName, a.forge_value DESC";

$lsr_rows   = [];
$lsr_error  = '';
$lsr_pilots = [];
$lsr_stats  = [];
$lsr_total  = 0.0;

$lsr_res = mysqli_query($link, $lsr_sql);
if ($lsr_res === false) {
    $lsr_error = mysqli_error($link);
} else {
    while ($r = mysqli_fetch_assoc($lsr_res)) {
        $lsr_rows[] = $r;
        $lsr_pilots[$r['toon_number']] = true;
        $lsr_stats[$r['stationName']]  = true;
        $lsr_total += (float)$r['forge_value'];
    }
    mysqli_free_result($lsr_res);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Low-Security Station Assets</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    :root {
        --bg: #12151b;
        --surface: #1a1f29;
        --line: #2a3140;
        --text: #d6dbe4;
        --muted: #8791a3;
        --lowsec: #f0a43a;
        --nullsec: #e5534b;
    }
    body { background: var(--bg); color: var(--text); }
    .lsr-wrap { max-width: 1280px; }
    .lsr-note {
        background: var(--surface);
        border: 1px solid var(--line);
        border-left: 4px solid var(--lowsec);
        color: var(--text);
    }
    .lsr-stat { background: var(--surface); border: 1px solid var(--line); border-radius: 4px; padding: .6rem 1rem; }
    .lsr-stat .num { font-size: 1.4rem; font-variant-numeric: tabular-nums; }
    .lsr-stat .lbl { color: var(--muted); font-size: .85rem; }
    .lsr-table { color: var(--text); margin-bottom: 0; }
    .lsr-table thead th { background: var(--surface); border-color: var(--line); color: var(--muted); font-weight: 600; position: sticky; top: 0; }
    .lsr-table td { border-color: var(--line); vertical-align: middle; }
    .lsr-table tbody tr:hover { background: #202736; }
    .num-col { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sec-low  { color: var(--lowsec);  font-weight: 600; }
    .sec-null { color: var(--nullsec); font-weight: 600; }
    .badge-pocket { font-size: .8rem; padding: .35em .6em; }
    .muted { color: var(--muted); }
    .lsr-scroll { max-height: 75vh; overflow: auto; border: 1px solid var(--line); border-radius: 4px; }
</style>
</head>
<body>
<div class="container-fluid lsr-wrap py-4">

    <h1 class="h3 mb-3"><i class="fas fa-shield-alt text-warning"></i> Assets in Low-Security Stations</h1>

    <div class="lsr-note p-3 mb-4 rounded">
        <i class="fas fa-info-circle text-warning"></i>
        This report only covers items located in NPC stations with a security level below
        <strong><?= lsr_h(number_format($lsr_sec_limit, 1)) ?></strong>.
        Items inside player-owned structures (citadels) or in space are not included.
    </div>

<?php if ($lsr_error !== ''): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        The query failed: <?= lsr_h($lsr_error) ?>
    </div>
<?php elseif (count($lsr_rows) === 0): ?>
    <div class="lsr-stat">
        <i class="fas fa-check-circle text-success"></i>
        No assets found in stations with security below <?= lsr_h(number_format($lsr_sec_limit, 1)) ?>.
    </div>
<?php else: ?>

    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-2"><div class="lsr-stat"><div class="num"><?= number_format(count($lsr_rows)) ?></div><div class="lbl">Item lines</div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="lsr-stat"><div class="num"><?= number_format(count($lsr_pilots)) ?></div><div class="lbl">Pilots affected</div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="lsr-stat"><div class="num"><?= number_format(count($lsr_stats)) ?></div><div class="lbl">Stations</div></div></div>
        <div class="col-6 col-md-3 mb-2"><div class="lsr-stat"><div class="num"><?= number_format($lsr_total, 0) ?> ISK</div><div class="lbl">Total value</div></div></div>
    </div>

    <div class="lsr-scroll">
        <table class="table table-sm lsr-table">
            <thead>
                <tr>
                    <th>Pilot</th>
                    <th>Pilot #</th>
                    <th>Pocket</th>
                    <th>Station</th>
                    <th class="num-col">Security</th>
                    <th>Location</th>
                    <th>Item</th>
                    <th class="num-col">Qty</th>
                    <th class="num-col">Value (ISK)</th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($lsr_rows as $r):
    $bg  = getColorPocket($r['pocket6']);
    $fg  = lsr_text_color($bg);
    $sec = (float)$r['security'];
    $secClass = ($sec <= 0.0) ? 'sec-null' : 'sec-low';
?>
                <tr>
                    <td><?= $r['toon_name'] !== null ? lsr_h($r['toon_name']) : '<span class="muted">(unknown pilot)</span>' ?></td>
                    <td class="muted"><?= lsr_h($r['toon_number']) ?></td>
                    <td><span class="badge badge-pocket" style="background:<?= lsr_h($bg) ?>;color:<?= lsr_h($fg) ?>"><?= lsr_h($r['pocket6'] ?? 'N/A') ?></span></td>
                    <td><?= lsr_h($r['stationName']) ?></td>
                    <td class="num-col <?= $secClass ?>"><?= number_format($sec, 2) ?></td>
                    <td class="muted"><?= lsr_h($r['location_flag']) ?></td>
                    <td><?= lsr_h($r['type_description']) ?></td>
                    <td class="num-col"><?= number_format((int)$r['quantity']) ?></td>
                    <td class="num-col"><?= number_format((float)$r['forge_value'], 2) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

</div>
</body>
</html>
