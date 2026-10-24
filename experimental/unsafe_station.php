<?php
/**
 * EVE Assets Analysis Dashboard
 * Recursive CTE with exclusions and orphan detection
 * MariaDB 11.8.6+ 24/oct/2026
 * License GPL
 * Alfonso Orozco Aguilar 
 * Claude Sonnet 5.5 under prompts and database form Alfonso Orozco Aguilar

 * unsafe_assets.php
 * Lists every EVE asset that is NOT safely docked in an NPC station:
 *   - Asset Safety wraps (always listed, so they get recovered)
 *   - Items in a player structure (citadel, engineering complex, refinery...)
 *   - Items floating in space (ships, containers, deployables)
 *
 * Single file, procedural. Requires config.php, which must create $link (mysqli).
 * Tables used: EVE_ASSETS (items) and PILOTS (toon_name, pocket6).
 */

require_once '../config.php';

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

// Names for structure IDs (location_id). Purely cosmetic: they stay listed as unsafe.
$STRUCTURE_NAMES = [
    1021011818054 => "Verite's Stellar Cartography",
];

// Structure IDs you know sit in wormhole space.
// There is no Asset Safety there: if the structure dies, everything inside is lost.
$WORMHOLE_STRUCTURES = [
    // 1021011818054,
];

// Starter ships and default gear of new pilots: not worth listing.
$IGNORE_NAMES    = ['ibis', 'reaper', 'velator', 'impairor'];   // exact type name, any case
$IGNORE_PREFIXES = ['civilian'];                                // type name starts with

$STALE_DAYS   = 14;   // flag data older than this many days
$DETAIL_LIMIT = 30;   // max items shown when a row is expanded

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// Pocket colors (for badge)
function getColorPocket($pocket) {
    $p = strtoupper(trim($pocket ?? ''));
    return match($p) {
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14',
        default => '#444444'
    };
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isk($v) {
    return number_format((float)$v, 0, '.', ',') . ' ISK';
}

function pocket_badge($pocket) {
    $p     = strtoupper(trim((string)$pocket));
    $label = $p !== '' ? $p : 'n/a';
    $dark  = ($p === 'SANGO' || $p === 'YENN') ? ' dark-text' : '';
    return '<span class="pocket-badge' . $dark . '" style="background-color:'
         . h(getColorPocket($pocket)) . ';">' . h($label) . '</span>';
}

// True for starter ships and default rookie gear.
function is_ignored_type($name, $names, $prefixes) {
    $n = strtolower(trim((string)$name));
    if ($n === '') {
        return false;
    }
    if (in_array($n, $names, true)) {
        return true;
    }
    foreach ($prefixes as $p) {
        if (strncmp($n, $p, strlen($p)) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Follow the chain item -> container/ship -> ... until reaching the place
 * where the whole stack really sits, and say how safe that place is.
 *
 * $map is keyed "toon|item_id" => ['loc' => location_id, 'flag' => location_flag]
 *
 * Returns [category, root location_id] where category is one of:
 *   npc        docked in an NPC station (safe)
 *   safety     inside an Asset Safety wrap
 *   structure  hangar of a player structure
 *   wormhole   hangar of a structure listed in $WORMHOLE_STRUCTURES
 *   space      in a solar system, outside any station
 *   unresolved parent item missing from the table (usually stale data)
 */
function resolve_root($toon, $locId, $flag, $map, $wormholes) {
    $seen  = [];
    $depth = 0;
    while (true) {
        if ($flag === 'AssetSafety') {
            return ['safety', $locId];
        }
        if ($locId >= 60000000 && $locId < 64000000) {
            return ['npc', $locId];
        }
        if ($locId >= 30000000 && $locId < 33000000) {
            return ['space', $locId];
        }
        if ($locId >= 1000000000000) {
            $key = $toon . '|' . $locId;
            if (isset($map[$key]) && !isset($seen[$key]) && $depth < 15) {
                // location_id is another item (ship or container): keep climbing
                $seen[$key] = true;
                $flag  = $map[$key]['flag'];
                $locId = $map[$key]['loc'];
                $depth++;
                continue;
            }
            // Not an item we know. Only flags that exist directly in a structure
            // hangar can mean "this is a structure"; anything else means the
            // parent ship/container is missing from the table.
            $direct = ($flag === 'Hangar' || $flag === 'Deliveries'
                    || $flag === 'CorpDeliveries' || strncmp($flag, 'CorpSAG', 7) === 0);
            if (!$direct) {
                return ['unresolved', $locId];
            }
            return [in_array($locId, $wormholes, true) ? 'wormhole' : 'structure', $locId];
        }
        return ['unresolved', $locId];
    }
}

// ---------------------------------------------------------------------------
// Category definitions (display order = priority)
// ---------------------------------------------------------------------------

$CATS = [
    'wormhole'  => ['label' => 'Structures in wormhole space',
                    'hint'  => 'No Asset Safety in wormholes. If the structure falls, everything inside is lost.',
                    'icon'  => 'fa-skull-crossbones', 'color' => '#dc3545'],
    'safety'    => ['label' => 'In Asset Safety',
                    'hint'  => 'These items were wrapped after a structure was lost. Recover them.',
                    'icon'  => 'fa-hourglass-half',   'color' => '#f39c12'],
    'structure' => ['label' => 'In player structures',
                    'hint'  => 'Safe only while the structure stands and you keep docking access.',
                    'icon'  => 'fa-building',         'color' => '#5dade2'],
    'space'     => ['label' => 'In space',
                    'hint'  => 'Not docked anywhere. Anyone can find and destroy these.',
                    'icon'  => 'fa-rocket',           'color' => '#a29bfe'],
];

// ---------------------------------------------------------------------------
// Data
// ---------------------------------------------------------------------------

$error   = null;
$pilots  = [];   // toon_number => ['name' => ..., 'pocket' => ...]
$pockets = [];   // distinct pocket6 values for the combo
$rows    = [];

$res = mysqli_query($link, "SELECT toon_number, toon_name, pocket6 FROM PILOTS");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $pilots[(int)$r['toon_number']] = ['name' => $r['toon_name'], 'pocket' => $r['pocket6']];
    }
    mysqli_free_result($res);
} else {
    $error = 'Could not read the pilot list.';
    error_log('unsafe_assets.php PILOTS: ' . mysqli_error($link));
}

$res = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS
                            WHERE pocket6 IS NOT NULL AND pocket6 <> '' ORDER BY pocket6");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $pockets[] = $r['pocket6'];
    }
    mysqli_free_result($res);
}

// Selected filter must be one of the real values; anything else means "All".
$selected = '';
if (isset($_GET['pocket']) && in_array($_GET['pocket'], $pockets, true)) {
    $selected = $_GET['pocket'];
}

$res = mysqli_query($link, "SELECT toon_number, location_flag, location_id, description, quantity,
                                   type_id, type_description, eveunique, date_insert, forge_value
                            FROM EVE_ASSETS");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_free_result($res);
} elseif ($error === null) {
    $error = 'Could not read the asset list.';
    error_log('unsafe_assets.php EVE_ASSETS: ' . mysqli_error($link));
}

// item lookup across ALL rows, so containers can be traced even when filtered
$map = [];
foreach ($rows as $r) {
    $map[(int)$r['toon_number'] . '|' . $r['eveunique']] = [
        'loc'  => (int)$r['location_id'],
        'flag' => (string)$r['location_flag'],
    ];
}

// ---------------------------------------------------------------------------
// Classify and group
// ---------------------------------------------------------------------------

$groups     = [];
$unresolved = 0;
$ignored    = 0;
$newest     = '';

foreach ($rows as $r) {
    $toon = (int)$r['toon_number'];

    if ($selected !== '') {
        if (!isset($pilots[$toon]) || trim((string)$pilots[$toon]['pocket']) !== $selected) {
            continue;
        }
    }

    [$cat, $root] = resolve_root($toon, (int)$r['location_id'], (string)$r['location_flag'], $map, $WORMHOLE_STRUCTURES);

    if ($cat === 'npc') {
        continue;
    }
    if ($cat === 'unresolved') {
        $unresolved++;
        continue;
    }
    // Starter ships and rookie gear (only counted when they would have been listed)
    if (is_ignored_type($r['type_description'], $IGNORE_NAMES, $IGNORE_PREFIXES)) {
        $ignored++;
        continue;
    }

    $key = $toon . '|' . $cat . '|' . $root;
    if (!isset($groups[$key])) {
        $groups[$key] = [
            'toon' => $toon, 'cat' => $cat, 'loc' => $root, 'name' => '',
            'wraps' => 0, 'items' => 0, 'value' => 0.0, 'updated' => '', 'list' => [],
        ];
    }
    $g = &$groups[$key];

    if ($g['name'] === '' && trim((string)$r['description']) !== '') {
        $g['name'] = trim($r['description']);
    }
    $g['value'] += (float)$r['forge_value'];
    if ($r['date_insert'] > $g['updated']) {
        $g['updated'] = $r['date_insert'];
    }
    if ($r['date_insert'] > $newest) {
        $newest = $r['date_insert'];
    }

    if ((int)$r['type_id'] === 60) {            // Asset Safety Wrap
        $g['wraps']++;
    } else {
        $qty = max(1, (int)$r['quantity']);
        $g['items'] += $qty;
        $g['list'][] = [
            'name'  => trim((string)$r['type_description']) !== '' ? $r['type_description'] : $r['description'],
            'qty'   => $qty,
            'value' => (float)$r['forge_value'],
        ];
    }
    unset($g);
}

// Known structure names win over whatever the table says
foreach ($groups as $gk => $g) {
    if ($g['cat'] !== 'space' && isset($STRUCTURE_NAMES[$g['loc']])) {
        $groups[$gk]['name'] = $STRUCTURE_NAMES[$g['loc']];
    }
}

// Totals per category
$totals = [];
foreach ($CATS as $c => $meta) {
    $totals[$c] = ['wraps' => 0, 'items' => 0, 'value' => 0.0, 'groups' => 0];
}
$affected = [];
foreach ($groups as $g) {
    $totals[$g['cat']]['wraps']  += $g['wraps'];
    $totals[$g['cat']]['items']  += $g['items'];
    $totals[$g['cat']]['value']  += $g['value'];
    $totals[$g['cat']]['groups'] ++;
    $affected[$g['toon']] = true;
}
$grandValue = 0.0;
foreach ($totals as $t) {
    $grandValue += $t['value'];
}

// Order: category priority, then value (highest first)
$order = array_flip(array_keys($CATS));
uasort($groups, function ($a, $b) use ($order) {
    if ($a['cat'] !== $b['cat']) {
        return $order[$a['cat']] <=> $order[$b['cat']];
    }
    return $b['value'] <=> $a['value'];
});

$staleLimit = strtotime('-' . (int)$STALE_DAYS . ' days');
$detailId   = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Unsafe assets</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
<style>
    body {
        background-color: #1a1d21;
        color: #e0e0e0;
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        padding-bottom: 60px;
    }
    .navbar-eve {
        background-color: #0b0c0e;
        border-bottom: 2px solid #495057;
        margin-bottom: 0;
    }

    /* filter bar */
    .filter-bar {
        background-color: #16191c;
        border-bottom: 2px solid #007bff;
        padding: 12px 20px;
        margin-bottom: 20px;
    }
    .filter-bar .form-control {
        background-color: #2a2d31;
        border-color: #495057;
        color: #e0e0e0;
        max-width: 180px;
    }
    .filter-bar .form-control:focus {
        background-color: #2a2d31;
        color: #fff;
        border-color: #007bff;
        box-shadow: none;
    }

    /* messages */
    .msg-ok, .msg-note, .msg-err {
        padding: 7px 16px;
        font-size: 0.82rem;
        margin-bottom: 14px;
    }
    .msg-ok   { background-color: #1a2a1a; border-left: 4px solid #28a745; color: #7ee07e; }
    .msg-note { background-color: #16191c; border-left: 4px solid #495057; color: #adb5bd; }
    .msg-err  { background-color: #2a1517; border-left: 4px solid #dc3545; color: #f1a1a8; }

    /* summary cards */
    .stat {
        background-color: #1e2126;
        border-left: 5px solid var(--c);
        border-radius: 2px;
        padding: .9rem 1rem;
        height: 100%;
    }
    .stat .ttl { color: var(--c); font-size: .85rem; font-weight: 600; }
    .stat .num { color: #fff; font-size: 1.6rem; font-weight: 600; line-height: 1.2; }
    .stat .num small { color: #adb5bd; font-size: .85rem; font-weight: 400; }
    .stat .sub { color: #adb5bd; font-size: .8rem; }

    /* category blocks */
    .block {
        background-color: #1e2126;
        border-left: 5px solid var(--c);
        border-radius: 2px;
        margin-bottom: 1.5rem;
    }
    .block-head { padding: .85rem 1rem .6rem; }
    .block-head h2 { color: var(--c); font-size: 1.1rem; font-weight: 600; margin: 0; }
    .block-head p { color: #adb5bd; font-size: .85rem; margin: .15rem 0 0; }

    /* dark table */
    .block table.table { color: #e0e0e0; font-size: 0.82rem; margin-bottom: 0; }
    .block thead th {
        background-color: #0d0f11;
        color: #adb5bd;
        border-top: 0;
        white-space: nowrap;
    }
    .block td, .block th { border-color: #343a40 !important; vertical-align: middle !important; }
    .block tr.r-a { background-color: #1e2126; }
    .block tr.r-b { background-color: #22262c; }
    .block tr.r-a:hover, .block tr.r-b:hover { background-color: #2a3040; }
    .num-col { text-align: right; white-space: nowrap; }
    .loc-id { color: #6c757d; font-size: .72rem; }
    .val-money { color: #f39c12; font-family: monospace; }
    .stale { color: #f39c12; }
    .detail-cell { padding: 0 !important; border-top: 0 !important; background-color: #16191c; }
    .detail-inner { padding: .5rem 1rem 1rem 2.5rem; background-color: #16191c; }
    .detail-inner td { border-color: #2a2d31 !important; }
    .btn-items { color: #adb5bd; border-color: #495057; }
    .btn-items:hover { color: #fff; background-color: #495057; border-color: #495057; }

    /* Pocket badge */
    .pocket-badge {
        display: inline-block;
        padding: 2px 8px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #fff;
        border-radius: 2px;
    }
    .pocket-badge.dark-text { color: #111; }

    @media (prefers-reduced-motion: reduce) { .collapsing { transition: none; } }
</style>
</head>
<body>

<nav class="navbar navbar-dark navbar-eve px-3">
    <span class="navbar-brand mb-0 h5">
        <i class="fas fa-truck-loading mr-2 text-warning"></i>Unsafe assets
    </span>
    <span class="text-muted small">
        <i class="fas fa-users mr-1"></i><?= number_format(count($affected)) ?> pilots with assets at risk
    </span>
</nav>

<div class="filter-bar">
    <form method="get" class="form-inline flex-wrap" style="gap:10px;">
        <label for="pocket" class="text-light mr-1"><i class="fas fa-folder mr-1"></i>Pocket:</label>
        <select name="pocket" id="pocket" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
            <option value="">-- All --</option>
<?php foreach ($pockets as $p): ?>
            <option value="<?= h($p) ?>"<?= $p === $selected ? ' selected' : '' ?>><?= h($p) ?></option>
<?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-primary mr-2"><i class="fas fa-filter mr-1"></i> Filter</button></noscript>
<?php if ($selected !== ''): ?>
        <?= pocket_badge($selected) ?>
        <a href="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="btn btn-sm btn-link">Clear filter</a>
<?php endif; ?>
        <span class="ml-auto text-muted small">
            Latest refresh in this view: <?= $newest !== '' ? h(substr($newest, 0, 16)) : 'no data' ?>
        </span>
    </form>
</div>

<div class="container-fluid">

<?php if ($error !== null): ?>
    <div class="msg-err"><i class="fas fa-exclamation-circle mr-1"></i><?= h($error) ?> Check the server error log.</div>
<?php endif; ?>

    <div class="msg-note">
        <i class="fas fa-info-circle mr-1"></i>
        Everything not docked in an NPC station. Asset Safety wraps are always listed.
        Starter ships (<?= h(implode(', ', array_map('ucfirst', $IGNORE_NAMES))) ?>) and every item whose name starts with
        &ldquo;<?= h(ucfirst($IGNORE_PREFIXES[0])) ?>&rdquo; are omitted
        (<?= number_format($ignored) ?> item lines skipped).
    </div>

    <div class="row mb-4">
<?php foreach ($CATS as $c => $meta):
          $t = $totals[$c];
          if ($c === 'wormhole' && $t['groups'] === 0) { continue; }
          $main = ($c === 'safety') ? $t['wraps'] : $t['items'];
          $unit = ($c === 'safety') ? 'wraps' : 'items';
?>
        <div class="col-6 col-lg mb-3">
            <div class="stat" style="--c: <?= h($meta['color']) ?>">
                <div class="ttl"><i class="fas <?= h($meta['icon']) ?> mr-1"></i><?= h($meta['label']) ?></div>
                <div class="num"><?= number_format($main) ?> <small><?= $unit ?></small></div>
                <div class="sub">
<?php if ($c === 'safety' && $t['items'] > 0): ?>
                    <?= number_format($t['items']) ?> items inside &middot;
<?php endif; ?>
                    <span class="val-money"><?= isk($t['value']) ?></span>
                </div>
            </div>
        </div>
<?php endforeach; ?>
        <div class="col-6 col-lg mb-3">
            <div class="stat" style="--c: #adb5bd">
                <div class="ttl">Total outside NPC stations</div>
                <div class="num val-money"><?= isk($grandValue) ?></div>
                <div class="sub">Estimated value (forge_value)</div>
            </div>
        </div>
    </div>

<?php foreach ($CATS as $c => $meta):
          $list = [];
          foreach ($groups as $gk => $g) {
              if ($g['cat'] === $c) { $list[$gk] = $g; }
          }
          if (!$list) {
              if ($c === 'safety') { ?>
    <div class="msg-ok"><i class="fas fa-check-circle mr-1"></i>No Asset Safety wraps for this selection.</div>
<?php         }
              continue;
          }
          $rowNo = 0;
?>
    <section class="block" style="--c: <?= h($meta['color']) ?>">
        <div class="block-head">
            <h2><i class="fas <?= h($meta['icon']) ?> mr-2"></i><?= h($meta['label']) ?></h2>
            <p><?= h($meta['hint']) ?></p>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Pilot</th>
                    <th>Location</th>
                    <th class="num-col"><?= $c === 'safety' ? 'Wraps / items' : 'Items' ?></th>
                    <th class="num-col">Value</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($list as $g):
          $p = $pilots[$g['toon']] ?? null;
          $pilotName = $p ? $p['name'] : ('Unknown pilot #' . $g['toon']);
          $pocket    = $p ? $p['pocket'] : '';
          $isStale   = $g['updated'] !== '' && strtotime($g['updated']) < $staleLimit;
          $isJ       = ($g['loc'] >= 31000000 && $g['loc'] < 32000000);
          usort($g['list'], function ($a, $b) { return $b['value'] <=> $a['value']; });
          $hasDetail = count($g['list']) > 0;
          $detailId++;
          $stripe = (++$rowNo % 2) ? 'r-a' : 'r-b';
?>
                <tr class="<?= $stripe ?>">
                    <td><strong class="text-white"><?= h($pilotName) ?></strong> <?= pocket_badge($pocket) ?></td>
                    <td>
                        <?php if ($c === 'space'): ?>
                            Solar system <?= h($g['loc']) ?><?= $isJ ? ' <span class="badge badge-secondary">Wormhole</span>' : '' ?>
                        <?php else: ?>
                            <?= $g['name'] !== '' ? h($g['name']) : 'Location ' . h($g['loc']) ?>
                            <?php if ($g['name'] !== ''): ?><div class="loc-id">ID <?= h($g['loc']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="num-col">
                        <?php if ($c === 'safety'): ?>
                            <?= number_format($g['wraps']) ?> / <?= number_format($g['items']) ?>
                        <?php else: ?>
                            <?= number_format($g['items']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="num-col val-money"><?= isk($g['value']) ?></td>
                    <td class="<?= $isStale ? 'stale' : '' ?>" style="white-space:nowrap">
                        <?= h(substr($g['updated'], 0, 10)) ?>
                        <?php if ($isStale): ?><i class="fas fa-history ml-1" title="Not refreshed in over <?= (int)$STALE_DAYS ?> days"></i><?php endif; ?>
                    </td>
                    <td class="text-right">
<?php if ($hasDetail): ?>
                        <button type="button" class="btn btn-sm btn-items" data-toggle="collapse"
                                data-target="#d<?= $detailId ?>" aria-expanded="false" aria-controls="d<?= $detailId ?>">
                            <i class="fas fa-list mr-1"></i>Items
                        </button>
<?php endif; ?>
                    </td>
                </tr>
<?php if ($hasDetail): ?>
                <tr>
                    <td colspan="6" class="detail-cell">
                        <div class="collapse" id="d<?= $detailId ?>">
                            <div class="detail-inner">
                                <table class="table table-sm mb-0">
                                    <tbody>
<?php foreach (array_slice($g['list'], 0, $DETAIL_LIMIT) as $it): ?>
                                        <tr>
                                            <td><?= h($it['name']) ?></td>
                                            <td class="num-col">&times; <?= number_format($it['qty']) ?></td>
                                            <td class="num-col val-money"><?= isk($it['value']) ?></td>
                                        </tr>
<?php endforeach; ?>
<?php if (count($g['list']) > $DETAIL_LIMIT): ?>
                                        <tr><td colspan="3" class="text-muted">+ <?= count($g['list']) - $DETAIL_LIMIT ?> more lines of lower value</td></tr>
<?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
<?php endif; ?>
<?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
<?php endforeach; ?>

<?php if ($unresolved > 0): ?>
    <div class="msg-note">
        <i class="fas fa-info-circle mr-1"></i><?= number_format($unresolved) ?> item lines could not be traced to a location
        (their ship or container is missing from the data, usually because that pilot has not been refreshed recently) and are not listed above.
    </div>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
