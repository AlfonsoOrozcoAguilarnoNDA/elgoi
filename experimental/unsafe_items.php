<?php
/* License GPL
CLaude Sonnet 5.5 Bajo ordenes de Alfonso Orozco Aguilar
Objetivo: reformular un script de kimi sobrre objetos en el espacio o citadels.
* /
/**
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

// Structure IDs (location_id) that you know sit in wormhole space.
// There is no Asset Safety there: if the structure dies, everything inside is lost.
$WORMHOLE_STRUCTURES = [
    // 1021011818054,
];

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

// Black or white text, whichever reads better on the given hex background.
function badge_text_color($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return (($r * 299 + $g * 587 + $b * 114) / 1000) > 150 ? '#212529' : '#ffffff';
}

function pocket_badge($pocket) {
    $label = trim((string)$pocket);
    if ($label === '') {
        $label = 'n/a';
    }
    $bg = getColorPocket($pocket);
    return '<span class="badge pocket-badge" style="background:' . h($bg)
         . ';color:' . h(badge_text_color($bg)) . '">' . h($label) . '</span>';
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
                    'icon'  => 'fa-skull-crossbones', 'color' => '#7a1f1f'],
    'safety'    => ['label' => 'In Asset Safety',
                    'hint'  => 'These items were wrapped after a structure was lost. Recover them.',
                    'icon'  => 'fa-hourglass-half',   'color' => '#b42318'],
    'structure' => ['label' => 'In player structures',
                    'hint'  => 'Safe only while the structure stands and you keep docking access.',
                    'icon'  => 'fa-building',         'color' => '#b54708'],
    'space'     => ['label' => 'In space',
                    'hint'  => 'Not docked anywhere. Anyone can find and destroy these.',
                    'icon'  => 'fa-rocket',           'color' => '#175cd3'],
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

// Totals per category
$totals = [];
foreach ($CATS as $c => $meta) {
    $totals[$c] = ['wraps' => 0, 'items' => 0, 'value' => 0.0, 'groups' => 0];
}
foreach ($groups as $g) {
    $totals[$g['cat']]['wraps']  += $g['wraps'];
    $totals[$g['cat']]['items']  += $g['items'];
    $totals[$g['cat']]['value']  += $g['value'];
    $totals[$g['cat']]['groups'] ++;
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unsafe assets</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
    body { background: #eef1f4; color: #1d2733; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .page-head { background: #17212b; color: #f1f4f7; padding: 1.6rem 0 1.4rem; margin-bottom: 1.5rem; }
    .page-head h1 { font-size: 1.7rem; font-weight: 600; margin: 0 0 .25rem; }
    .page-head p { margin: 0; color: #aebccb; max-width: 60ch; }
    .pocket-badge { font-size: .78rem; font-weight: 600; padding: .3em .6em; letter-spacing: .02em; }
    .filter-bar { background: #fff; border-radius: 4px; padding: .75rem 1rem; margin-bottom: 1.25rem; }
    .stat { background: #fff; border-radius: 4px; padding: .9rem 1rem; border-left: 5px solid var(--c); height: 100%; }
    .stat .num { font-size: 1.7rem; font-weight: 600; line-height: 1.1; }
    .stat .sub { color: #5b6876; font-size: .85rem; }
    .stat .ttl { font-size: .9rem; font-weight: 600; color: var(--c); }
    .block { background: #fff; border-radius: 4px; border-left: 5px solid var(--c); margin-bottom: 1.5rem; }
    .block-head { padding: .85rem 1rem .5rem; }
    .block-head h2 { font-size: 1.15rem; font-weight: 600; margin: 0; color: var(--c); }
    .block-head p { margin: .15rem 0 0; color: #5b6876; font-size: .9rem; }
    .block table { margin-bottom: 0; }
    .block thead th { border-top: 0; font-size: .85rem; color: #5b6876; font-weight: 600; }
    .num-col { text-align: right; white-space: nowrap; }
    .loc-id { color: #7a8794; font-size: .8rem; }
    .detail-cell { padding: 0 !important; border-top: 0 !important; }
    .detail-inner { padding: .5rem 1rem 1rem 2.5rem; background: #f7f8fa; }
    .stale { color: #b54708; }
    @media (prefers-reduced-motion: reduce) { .collapsing { transition: none; } }
</style>
</head>
<body>

<div class="page-head">
    <div class="container">
        <h1><i class="fas fa-exclamation-triangle mr-2"></i>Unsafe assets</h1>
        <p>Everything that is not docked in an NPC station. Asset Safety wraps are always listed so they get recovered.</p>
    </div>
</div>

<div class="container">

<?php if ($error !== null): ?>
    <div class="alert alert-danger"><?= h($error) ?> Check the server error log.</div>
<?php endif; ?>

    <form method="get" class="filter-bar form-inline">
        <label for="pocket" class="mr-2 font-weight-bold">Pocket</label>
        <select name="pocket" id="pocket" class="custom-select custom-select-sm mr-3" onchange="this.form.submit()">
            <option value="">All pockets</option>
<?php foreach ($pockets as $p): ?>
            <option value="<?= h($p) ?>"<?= $p === $selected ? ' selected' : '' ?>><?= h($p) ?></option>
<?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-sm btn-primary mr-3">Apply</button></noscript>
<?php if ($selected !== ''): ?>
        <?= pocket_badge($selected) ?>
        <a href="<?= h(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="btn btn-sm btn-link">Clear filter</a>
<?php endif; ?>
        <span class="ml-auto text-muted small">
            Latest refresh in this view: <?= $newest !== '' ? h(substr($newest, 0, 16)) : 'no data' ?>
        </span>
    </form>

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
                <div class="num"><?= number_format($main) ?> <span class="sub"><?= $unit ?></span></div>
                <div class="sub">
<?php if ($c === 'safety' && $t['items'] > 0): ?>
                    <?= number_format($t['items']) ?> items inside &middot;
<?php endif; ?>
                    <?= isk($t['value']) ?>
                </div>
            </div>
        </div>
<?php endforeach; ?>
        <div class="col-6 col-lg mb-3">
            <div class="stat" style="--c: #1d2733">
                <div class="ttl">Total outside NPC stations</div>
                <div class="num"><?= isk($grandValue) ?></div>
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
    <div class="alert alert-success"><i class="fas fa-check-circle mr-1"></i>No Asset Safety wraps for this selection.</div>
<?php         }
              continue;
          }
?>
    <section class="block" style="--c: <?= h($meta['color']) ?>">
        <div class="block-head">
            <h2><i class="fas <?= h($meta['icon']) ?> mr-2"></i><?= h($meta['label']) ?></h2>
            <p><?= h($meta['hint']) ?></p>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
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
?>
                <tr>
                    <td><?= h($pilotName) ?> <?= pocket_badge($pocket) ?></td>
                    <td>
                        <?php if ($c === 'space'): ?>
                            Solar system <?= h($g['loc']) ?><?= $isJ ? ' <span class="badge badge-dark">Wormhole</span>' : '' ?>
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
                    <td class="num-col"><?= isk($g['value']) ?></td>
                    <td class="<?= $isStale ? 'stale' : '' ?>" style="white-space:nowrap">
                        <?= h(substr($g['updated'], 0, 10)) ?>
                        <?php if ($isStale): ?><i class="fas fa-history ml-1" title="Not refreshed in over <?= (int)$STALE_DAYS ?> days"></i><?php endif; ?>
                    </td>
                    <td class="text-right">
<?php if ($hasDetail): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="collapse"
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
                                            <td class="num-col"><?= isk($it['value']) ?></td>
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
    <p class="text-muted small">
        <i class="fas fa-info-circle mr-1"></i><?= number_format($unresolved) ?> item lines could not be traced to a location
        (their ship or container is missing from the data, usually because that pilot has not been refreshed recently) and are not listed above.
    </p>
<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
