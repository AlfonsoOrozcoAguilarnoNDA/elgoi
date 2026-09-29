<?php
/** 29 sep 2026
 * EVE Online Fitting Duplicates Detector
 * Detects duplicate fittings across pilots by content (different name, same items)
 * and by name (same name, different items).
 * Also updates PILOTS.numberfits with the actual count of fittings per pilot.
 *
 * Copyright (C) 2026  Alfonso Orozco Aguilar
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

require_once '../config.php';
include_once '../ui_functions.php';

function normalizeFittingItems(array $items): string
{
    $grouped = [];
    foreach ($items as $item) {
        $flag     = $item['flag']     ?? '';
        $typeId   = $item['type_id']  ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $key      = $flag . ':' . $typeId;
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'flag'     => $flag,
                'type_id'  => $typeId,
                'quantity' => 0,
            ];
        }
        $grouped[$key]['quantity'] += $quantity;
    }

    usort($grouped, function ($a, $b) {
        $cmp = strcmp($a['flag'], $b['flag']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return $a['type_id'] <=> $b['type_id'];
    });

    $parts = [];
    foreach ($grouped as $g) {
        $parts[] = $g['flag'] . ':' . $g['type_id'] . 'x' . $g['quantity'];
    }
    return implode('|', $parts);
}

function getFittingContentHash(array $fitting): string
{
    $shipTypeId = $fitting['ship_type_id'] ?? 0;
    $items      = $fitting['items']        ?? [];
    $canonical  = $shipTypeId . '::' . normalizeFittingItems($items);
    return md5($canonical);
}

function getFittingNameKey(array $fitting): string
{
    $shipTypeId = $fitting['ship_type_id'] ?? 0;
    $name       = $fitting['name']        ?? '';
    return $shipTypeId . '|' . strtolower(trim($name));
}

function getPocketBadgeClass(string $pocket): string
{
    $pocket = strtoupper($pocket);
    if (stripos($pocket, 'EXPER') !== false) {
        return 'badge-success';
    } elseif (stripos($pocket, 'NOKIA') !== false) {
        return 'badge-danger';
    } elseif (stripos($pocket, 'CLEAN') !== false) {
        return 'badge-primary';
    } elseif (stripos($pocket, 'LUCKY') !== false) {
        return 'badge-dark';
    } elseif (stripos($pocket, 'SANGO') !== false) {
        return 'badge-warning';
    }
    return 'badge-secondary';
}

// ---------------------------------------------------------------------------
// MAIN EXECUTION
// ---------------------------------------------------------------------------

check_authorization();

echo ui_header("Fittings Duplicator Finder - Fleet Overview");
echo ui_generate_navbar();



// Fetch all pilots (we need everyone to update numberfits, even those with 0)
$sql    = "SELECT `toon_number`, `toon_name`, `pocket6`, `fittings` FROM `PILOTS`";
$result = $link->query($sql);

if (!$result) {
    die('Database error: ' . $link->error);
}

$allFittings      = [];
$contentMap       = [];
$nameMap          = [];
$pilotFitSummary  = [];
$totalPilots      = 0;
$totalFittings    = 0;

while ($row = $result->fetch_assoc()) {
    $totalPilots++;
    $toonNumber  = (int) $row['toon_number'];
    $toonName    = $row['toon_name'];
    $pocket6     = $row['pocket6'] ?? 'CLEAN';
    $rawFittings = $row['fittings'];

    $fittings = json_decode(stripslashes($rawFittings), true);
    if (!is_array($fittings)) {
        $fittings = [];
    }

    $fitCount = count($fittings);

    // Update numberfits in database for ALL pilots
    $updateStmt = $link->prepare("UPDATE `PILOTS` SET `numberfits` = ? WHERE `toon_number` = ?");
    $updateStmt->bind_param("ii", $fitCount, $toonNumber);
    $updateStmt->execute();
    $updateStmt->close();

    // Only add to summary table if pilot has fittings
    if ($fitCount > 0) {
        $pilotFitSummary[] = [
            'toon_number' => $toonNumber,
            'toon_name'   => $toonName,
            'pocket6'     => $pocket6,
            'fit_count'   => $fitCount,
        ];
        $totalFittings += $fitCount;
    }

    // Only process fittings for duplicate detection if there are any
    if ($fitCount === 0) {
        continue;
    }

    foreach ($fittings as $idx => $fitting) {
        if (!is_array($fitting)) {
            continue;
        }

        $contentHash = getFittingContentHash($fitting);
        $nameKey     = getFittingNameKey($fitting);

        $entry = [
            'toon_number' => $toonNumber,
            'toon_name'   => $toonName,
            'fitting'     => $fitting,
            'index'       => $idx,
        ];

        $allFittings[$toonNumber][$idx] = $entry;

        if (!isset($contentMap[$contentHash])) {
            $contentMap[$contentHash] = [];
        }
        $contentMap[$contentHash][] = $entry;

        if (!isset($nameMap[$nameKey])) {
            $nameMap[$nameKey] = [];
        }
        $nameMap[$nameKey][] = $entry;
    }
}

$result->free();

// Sort summary by fit_count descending, then by toon_name
usort($pilotFitSummary, function ($a, $b) {
    if ($a['fit_count'] !== $b['fit_count']) {
        return $b['fit_count'] <=> $a['fit_count'];
    }
    return strcmp($a['toon_name'], $b['toon_name']);
});

$pilotsWithFits = count($pilotFitSummary);
$pilotsWithoutFits = $totalPilots - $pilotsWithFits;

// ---------------------------------------------------------------------------
// DETECT DUPLICATES
// ---------------------------------------------------------------------------

$contentDuplicates = [];
foreach ($contentMap as $hash => $entries) {
    if (count($entries) > 1) {
        $contentDuplicates[] = $entries;
    }
}

$nameDuplicates = [];
foreach ($nameMap as $key => $entries) {
    if (count($entries) > 1) {
        $hashes = [];
        foreach ($entries as $e) {
            $hashes[] = getFittingContentHash($e['fitting']);
        }
        $uniqueHashes = array_unique($hashes);
        if (count($uniqueHashes) > 1) {
            $nameDuplicates[] = $entries;
        }
    }
}

// ---------------------------------------------------------------------------
// HTML OUTPUT
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EVE Online — Fitting Duplicate Detector</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #0b0b0b; color: #ccc; margin: 20px; }
        h1, h2 { color: #ffcc00; border-bottom: 1px solid #333; padding-bottom: 8px; }
        .duplicate-group { background: #1a1a1a; border: 1px solid #333; border-radius: 6px; margin: 15px 0; padding: 15px; }
        .duplicate-group h3 { color: #66ccff; margin-top: 0; font-size: 1.1em; }
        .fitting-entry { background: #222; border-left: 3px solid #ffcc00; padding: 10px 15px; margin: 8px 0; border-radius: 0 4px 4px 0; }
        .fitting-entry .pilot { color: #66ccff; font-weight: bold; }
        .fitting-entry .name { color: #fff; font-size: 1.05em; }
        .fitting-entry .ship { color: #aaa; font-size: 0.9em; }
        .fitting-entry .hash { color: #888; font-family: monospace; font-size: 0.85em; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-left: 8px; font-weight: bold; }
        .badge-same-content { background: #2d5a27; color: #8aff80; }
        .badge-same-name   { background: #5a2727; color: #ff8080; }
        .no-dupes { color: #8aff80; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid #333; }
        th { color: #ffcc00; font-weight: normal; }
        .summary { background: #1a1a1a; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .summary span { margin-right: 25px; }
        .summary .num { color: #ffcc00; font-weight: bold; font-size: 1.2em; }
        .fit-summary-table { margin-bottom: 30px; }
        .fit-summary-table tr:hover { background: #1a1a1a; }
        .fit-count-nonzero { color: #ffcc00; font-weight: bold; }
        .updated-badge { color: #8aff80; font-size: 0.85em; }
        .notice { background: #1a1a1a; border-left: 3px solid #66ccff; padding: 10px 15px; margin: 10px 0; border-radius: 0 4px 4px 0; color: #aaa; }
        /* Pocket badge colors */
        .badge-success { background: #2d5a27; color: #8aff80; }
        .badge-danger  { background: #5a2727; color: #ff8080; }
        .badge-primary { background: #1a3a5c; color: #66ccff; }
        .badge-dark    { background: #333; color: #ccc; }
        .badge-warning { background: #5a4a1a; color: #ffcc00; }
        .badge-secondary { background: #444; color: #aaa; }
    </style>
</head>
<body>

<h1>🔍 EVE Online — Fitting Duplicate Detector</h1>

<div class="summary">
    <span>Total pilots scanned: <span class="num"><?php echo (int) $totalPilots; ?></span></span>
    <span>Pilots with fittings: <span class="num"><?php echo (int) $pilotsWithFits; ?></span></span>
    <span>Pilots without fittings: <span class="num"><?php echo (int) $pilotsWithoutFits; ?></span></span>
    <span>Total fittings: <span class="num"><?php echo (int) $totalFittings; ?></span></span>
    <span>Content duplicates: <span class="num"><?php echo count($contentDuplicates); ?></span></span>
    <span>Name duplicates: <span class="num"><?php echo count($nameDuplicates); ?></span></span>
    <br><br>
    <span class="updated-badge">✓ Field `numberfits` updated in database</span>
</div>

<?php if ($pilotsWithoutFits > 0): ?>
<div class="notice">
    ℹ <?php echo (int) $pilotsWithoutFits; ?> pilot(s) have no fittings stored and are hidden from the summary below.
</div>
<?php endif; ?>

<h2>📊 Fitting Summary by Pilot</h2>
<table class="fit-summary-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Toon Number</th>
            <th>Pilot</th>
            <th>Pocket</th>
            <th>Fittings</th>
        </tr>
    </thead>
    <tbody>
        <?php $rank = 1; foreach ($pilotFitSummary as $pilot): ?>
            <tr>
                <td><?php echo $rank++; ?></td>
                <td><?php echo (int) $pilot['toon_number']; ?></td>
                <td><?php echo htmlspecialchars($pilot['toon_name']); ?></td>
                <td>
                    <span class="badge <?php echo getPocketBadgeClass($pilot['pocket6']); ?>">
                        <?php echo htmlspecialchars($pilot['pocket6']); ?>
                    </span>
                </td>
                <td class="fit-count-nonzero">
                    <?php echo (int) $pilot['fit_count']; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2>⚠️ Fittings with identical content (name may vary)</h2>
<?php if (empty($contentDuplicates)): ?>
    <p class="no-dupes">No fittings with identical content found across pilots.</p>
<?php else: ?>
    <?php foreach ($contentDuplicates as $group): ?>
        <div class="duplicate-group">
            <h3>
                <?php echo htmlspecialchars($group[0]['fitting']['name'] ?? 'Unnamed'); ?>
                <span class="badge badge-same-content">SAME CONTENT</span>
            </h3>
            <?php foreach ($group as $entry): ?>
                <div class="fitting-entry">
                    <div>
                        <span class="pilot"><?php echo htmlspecialchars($entry['toon_name']); ?></span>
                        <span class="name">— <?php echo htmlspecialchars($entry['fitting']['name'] ?? 'Unnamed'); ?></span>
                    </div>
                    <div class="ship">
                        Ship Type ID: <?php echo (int) ($entry['fitting']['ship_type_id'] ?? 0); ?>
                        | Items: <?php echo count($entry['fitting']['items'] ?? []); ?>
                    </div>
                    <div class="hash">Hash: <?php echo getFittingContentHash($entry['fitting']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>⚠️ Fittings with the same name (different content)</h2>
<?php if (empty($nameDuplicates)): ?>
    <p class="no-dupes">No fittings with the same name but different content found.</p>
<?php else: ?>
    <?php foreach ($nameDuplicates as $group): ?>
        <div class="duplicate-group">
            <h3>
                <?php echo htmlspecialchars($group[0]['fitting']['name'] ?? 'Unnamed'); ?>
                <span class="badge badge-same-name">SAME NAME</span>
            </h3>
            <?php foreach ($group as $entry): ?>
                <div class="fitting-entry">
                    <div>
                        <span class="pilot"><?php echo htmlspecialchars($entry['toon_name']); ?></span>
                        <span class="name">— <?php echo htmlspecialchars($entry['fitting']['name'] ?? 'Unnamed'); ?></span>
                    </div>
                    <div class="ship">
                        Ship Type ID: <?php echo (int) ($entry['fitting']['ship_type_id'] ?? 0); ?>
                        | Items: <?php echo count($entry['fitting']['items'] ?? []); ?>
                    </div>
                    <div class="hash">Hash: <?php echo getFittingContentHash($entry['fitting']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>📋 Complete fitting listing</h2>
<table>
    <thead>
        <tr>
            <th>Pilot</th>
            <th>Fitting Name</th>
            <th>Ship Type ID</th>
            <th>Items</th>
            <th>Content Hash</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($allFittings as $toonNumber => $fittings): ?>
            <?php foreach ($fittings as $entry): ?>
                <tr>
                    <td><?php echo htmlspecialchars($entry['toon_name']); ?></td>
                    <td><?php echo htmlspecialchars($entry['fitting']['name'] ?? 'Unnamed'); ?></td>
                    <td><?php echo (int) ($entry['fitting']['ship_type_id'] ?? 0); ?></td>
                    <td><?php echo count($entry['fitting']['items'] ?? []); ?></td>
                    <td style="font-family:monospace;font-size:0.85em;color:#888;"><?php echo getFittingContentHash($entry['fitting']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php echo ui_footer(); ?>
