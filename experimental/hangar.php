<?php
/**
 * EVE Online - Ship Readiness Dashboard
 * PHP 8.4 Procedural | Bootstrap 5.3.3 | DataTables 2.x (BS5) | Font Awesome 5.15.4
 *
 * Requires: include "../config.php" providing $link (mysqli)
 *
 * Logic:
 * - Ready to Fly: quantity = 1 AND has children (fitted modules) in EVE_ASSETS
 * - Hangar/Transport: quantity > 1 (seller stack) OR quantity = 1 without children (empty hull)
 * - Excludes toon_number = 2114226800 (control character)
 * - Summary list: ships per pilot (sum of quantity), sorted descending, with update link
 */

// ─── CONFIG ─────────────────────────────────────────────────────────
include "../config.php";
check_authorization();

const CONTROL_TOON = 2114226800;

// ─── HELPERS ────────────────────────────────────────────────────────

/** Null-safe HTML escape (PHP 8.4 deprecates passing NULL to htmlspecialchars). */
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Base URL (scheme + domain) of the current request. */
function get_base_url(): string {
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function get_pocket6_badge(?string $pocket6): string {
    $pocket6 = strtoupper(trim((string)$pocket6));
    $map = [
        'NOKIA' => ['class' => 'danger',  'icon' => 'fa-exclamation-triangle'],
        'EXPER' => ['class' => 'success', 'icon' => 'fa-check-circle'],
        'CLEAN' => ['class' => 'primary', 'icon' => 'fa-shield-alt'],
        'SANGO' => ['class' => 'warning', 'icon' => 'fa-exclamation-circle'],
    ];

    if (isset($map[$pocket6])) {
        $m = $map[$pocket6];
        return sprintf(
            '<span class="badge text-bg-%s"><i class="fas %s me-1"></i>%s</span>',
            $m['class'], $m['icon'], h($pocket6)
        );
    }

    return sprintf(
        '<span class="badge text-bg-secondary"><i class="fas fa-question-circle me-1"></i>%s</span>',
        h($pocket6 !== '' ? $pocket6 : 'UNKNOWN')
    );
}

function get_substate_badge(?string $substate): string {
    $map = [
        'stack' => ['class' => 'info',      'label' => 'Seller Stack',   'icon' => 'fa-layer-group'],
        'empty' => ['class' => 'secondary', 'label' => 'Empty / No Fit', 'icon' => 'fa-box-open'],
    ];
    $m = $map[$substate ?? ''] ?? ['class' => 'secondary', 'label' => (string)$substate, 'icon' => 'fa-question'];
    return sprintf(
        '<span class="badge text-bg-%s"><i class="fas %s me-1"></i>%s</span>',
        $m['class'], $m['icon'], h($m['label'])
    );
}

// ─── DATA ING ─────────────────────────────────────────────────

function _rows($link, string $sql): array {
    $data = [];
    $result = mysqli_query($link, $sql);
    if ($result === false) {
        return $data;
    }
    while ($row = mysqli__assoc($result)) {
        $data[] = $row;
    }
    mysqli_free_result($result);
    return $data;
}

/** Ships per pilot (sum of quantity). Only pilots with more than one ship. */
function fetch_ship_summary($link): array {
    $sql = "
        SELECT
            p.toon_number,
            p.toon_name,
            p.pocket6,
            t.ship_count
        FROM PILOTS p
        INNER JOIN (
            SELECT a.toon_number, SUM(a.quantity) AS ship_count
            FROM EVE_ASSETS a
            INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
            GROUP BY a.toon_number
            HAVING SUM(a.quantity) > 1
        ) t ON t.toon_number = p.toon_number
        WHERE p.toon_number != " . CONTROL_TOON . "
        ORDER BY t.ship_count DESC, p.toon_name ASC
    ";
    return fetch_rows($link, $sql);
}
function fetch_ready_ships($link): array {
    $sql = "
        SELECT
            p.toon_number,
            p.toon_name,
            p.pocket6,
            s.ShipName,
            s.TypeName,
            s.Tech,
            s.Race,
            s.TypicalRole,
            a.location_id,
            a.description AS location_desc,
            a.quantity,
            a.eveunique
        FROM EVE_ASSETS a
        INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
        INNER JOIN PILOTS p ON a.toon_number = p.toon_number
        WHERE a.toon_number != " . CONTROL_TOON . "
          AND a.quantity = 1
          AND EXISTS (
              SELECT 1 FROM EVE_ASSETS child
              WHERE child.location_id = a.eveunique
                AND child.toon_number = a.toon_number
          )
        ORDER BY p.toon_name, s.ShipName
    ";
    return fetch_rows($link, $sql);
}

function fetch_hangar_ships($link): array {
    $sql = "
        SELECT
            p.toon_number,
            p.toon_name,
            p.pocket6,
            s.ShipName,
            s.TypeName,
            s.Tech,
            s.Race,
            s.TypicalRole,
            a.location_id,
            a.description AS location_desc,
            a.quantity,
            a.eveunique,
            CASE
                WHEN a.quantity > 1 THEN 'stack'
                ELSE 'empty'
            END AS substate
        FROM EVE_ASSETS a
        INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
        INNER JOIN PILOTS p ON a.toon_number = p.toon_number
        WHERE a.toon_number != " . CONTROL_TOON . "
          AND (
              a.quantity > 1
              OR (
                  a.quantity = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM EVE_ASSETS child
                      WHERE child.location_id = a.eveunique
                        AND child.toon_number = a.toon_number
                  )
              )
          )
        ORDER BY p.toon_name, s.ShipName
    ";
    return fetch_rows($link, $sql);
}

// ─── POCKET6 FILTER OPTIONS ────────────────────────────────────────

function fetch_pocket6_values($link): array {
    $rows = fetch_rows($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 IS NOT NULL AND pocket6 != '' ORDER BY pocket6");
    return array_column($rows, 'pocket6');
}

// ─── MAIN ──────────────────────────────────────────────────────────

$summary      = fetch_ship_summary($link);
$ready_ships  = fetch_ready_ships($link);
$hangar_ships = fetch_hangar_ships($link);
$pocket6_vals = fetch_pocket6_values($link);

$ready_count  = count($ready_ships);
$hangar_count = count($hangar_ships);
$base_url     = get_base_url();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online - Ship Readiness Dashboard</title>

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 5.15.4 -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet">

    <!-- DataTables 2.x Bootstrap 5 -->
    <link href="https://cdn.datatables.net/2.2.0/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --eve-dark: #0a0e17;
            --eve-panel: #111827;
            --eve-border: #1f2937;
            --eve-text: #e5e7eb;
            --eve-muted: #9ca3af;
            --eve-accent: #f59e0b;
        }

        body {
            background-color: var(--eve-dark);
            color: var(--eve-text);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .eve-card {
            background-color: var(--eve-panel);
            border: 1px solid var(--eve-border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3);
        }

        .eve-card-header {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            border-bottom: 1px solid var(--eve-border);
            padding: 1rem 1.25rem;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }

        .eve-stat {
            font-size: 2rem;
            font-weight: 700;
            color: var(--eve-accent);
        }

        .table-dark-custom {
            --bs-table-bg: transparent;
            --bs-table-color: #d1d5db;
            --bs-table-border-color: var(--eve-border);
        }

        .table-dark-custom thead th {
            background-color: #1f2937;
            color: #fbbf24;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--eve-border);
        }

        .table-dark-custom tbody td {
            color: #d1d5db;
            vertical-align: middle;
        }

        .table-dark-custom tbody tr {
            border-bottom: 1px solid var(--eve-border);
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(245, 158, 11, 0.08);
        }

        .table-dark-custom tbody tr:hover td {
            color: #f3f4f6;
        }

        /* DataTables overrides for dark theme */
        .dt-search input, .dt-length select {
            background-color: #1f2937 !important;
            color: #e5e7eb !important;
            border: 1px solid var(--eve-border) !important;
        }

        .dt-search input::placeholder {
            color: #6b7280;
        }

        .dt-length select option {
            background-color: #1f2937;
            color: #e5e7eb;
        }

        .dt-info, .dt-length label {
            color: #9ca3af !important;
        }

        .dt-paging .page-link {
            background-color: #1f2937;
            color: #e5e7eb;
            border-color: var(--eve-border);
        }

        .dt-paging .page-item.active .page-link {
            background-color: var(--eve-accent);
            border-color: var(--eve-accent);
            color: #000;
        }

        .dt-paging .page-item.disabled .page-link {
            background-color: #1f2937;
            color: #4b5563;
            border-color: var(--eve-border);
        }

        .dt-column-order {
            color: #fbbf24 !important;
        }

        .nav-pills .nav-link {
            color: var(--eve-muted);
            border: 1px solid var(--eve-border);
            margin-right: 0.5rem;
        }

        .nav-pills .nav-link.active {
            background-color: var(--eve-accent);
            color: #000;
            border-color: var(--eve-accent);
            font-weight: 600;
        }

        .filter-section {
            background-color: var(--eve-panel);
            border: 1px solid var(--eve-border);
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .pocket6-filter .form-check-label {
            color: var(--eve-text);
            cursor: pointer;
        }

        .pocket6-filter .form-check-input:checked {
            background-color: var(--eve-accent);
            border-color: var(--eve-accent);
        }

        .ship-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 0.25rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 0.75rem;
            font-weight: 700;
            margin-right: 0.5rem;
        }

        .tech-badge-t1 { background-color: #6b7280; }
        .tech-badge-t2 { background-color: #3b82f6; }
        .tech-badge-t3 { background-color: #8b5cf6; }

        .tab-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.6em;
            margin-left: 0.5rem;
        }

        .location-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        h1 {
            color: #f3f4f6;
        }

        .text-muted {
            color: #9ca3af !important;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h1 class="mb-0">
                        <i class="fas fa-rocket text-warning me-2"></i>
                        EVE Online Ship Readiness
                    </h1>
                    <p class="text-muted mb-0 mt-1">
                        <i class="fas fa-database me-1"></i>
                        Fleet dashboard — <?= gmdate('Y-m-d H:i') ?> UTC
                    </p>
                </div>
                <div class="d-flex gap-3">
                    <div class="text-center">
                        <div class="eve-stat"><?= $ready_count ?></div>
                        <small class="text-muted">Ready to Fly</small>
                    </div>
                    <div class="text-center">
                        <div class="eve-stat text-secondary"><?= $hangar_count ?></div>
                        <small class="text-muted">Hangar</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary: ships per pilot -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="eve-card">
                <div class="eve-card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-users text-warning me-2"></i>Ships per Pilot</span>
                    <span class="text-muted small">Sorted by ship count (descending)</span>
                </div>
                <div class="p-0">
                    <table class="table table-dark-custom table-hover mb-0" id="table-summary" style="width:100%">
                        <thead>
                            <tr>
                                <th>Pilot</th>
                                <th>Pocket6</th>
                                <th class="text-center">Ships</th>
                                <th class="text-center">Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary as $row):
                                $update_url = $base_url . '/specific/oldversion.php?module=190&number=' . urlencode((string)$row['toon_number']);
                            ?>
                            <tr>
                                <td><strong><?= h($row['toon_name']) ?></strong></td>
                                <td><?= get_pocket6_badge($row['pocket6'] ?? '') ?></td>
                                <td class="text-center" data-order="<?= (int)$row['ship_count'] ?>">
                                    <span class="badge text-bg-dark"><?= (int)$row['ship_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= h($update_url) ?>" target="_blank" rel="noopener"
                                       class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-sync-alt me-1"></i>Update ships
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="filter-section">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-filter text-warning me-2"></i>
                    <strong>Global Filters</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Pocket6 (multi-select)</label>
                        <div class="pocket6-filter d-flex flex-wrap gap-3">
                            <?php foreach ($pocket6_vals as $p6):
                                $p6_upper = strtoupper((string)$p6);
                                $checked = in_array($p6_upper, ['CLEAN','EXPER','NOKIA','SANGO'], true) ? 'checked' : '';
                            ?>
                            <div class="form-check">
                                <input class="form-check-input pocket6-check" type="checkbox"
                                       value="<?= h($p6) ?>" id="p6_<?= md5((string)$p6) ?>" <?= $checked ?>>
                                <label class="form-check-label" for="p6_<?= md5((string)$p6) ?>">
                                    <?= get_pocket6_badge($p6) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Ship</label>
                                <input type="text" id="filter-ship" class="form-control form-control-sm"
                                       placeholder="e.g. Abaddon, Svipul...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Class</label>
                                <input type="text" id="filter-class" class="form-control form-control-sm"
                                       placeholder="e.g. Battleship, Destroyer...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Role</label>
                                <input type="text" id="filter-role" class="form-control form-control-sm"
                                       placeholder="e.g. Sniper, Drones...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-sm btn-warning" onclick="applyFilters()">
                        <i class="fas fa-sync-alt me-1"></i>Apply Filters
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="resetFilters()">
                        <i class="fas fa-undo me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-pills mb-3" id="shipTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="ready-tab" data-bs-toggle="pill"
                            data-bs-target="#ready-panel" type="button" role="tab">
                        <i class="fas fa-fighter-jet me-1"></i>
                        Ready to Fly
                        <span class="badge text-bg-success tab-badge"><?= $ready_count ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hangar-tab" data-bs-toggle="pill"
                            data-bs-target="#hangar-panel" type="button" role="tab">
                        <i class="fas fa-warehouse me-1"></i>
                        Hangar / Transport
                        <span class="badge text-bg-secondary tab-badge"><?= $hangar_count ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="shipTabContent">
                <!-- READY TO FLY PANEL -->
                <div class="tab-pane fade show active" id="ready-panel" role="tabpanel">
                    <div class="eve-card">
                        <div class="eve-card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-check-circle text-success me-2"></i>Ships Ready to Fly</span>
                            <span class="text-muted small">Fitted with modules</span>
                        </div>
                        <div class="p-0">
                            <table class="table table-dark-custom table-hover mb-0" id="table-ready" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Pilot</th>
                                        <th>Pocket6</th>
                                        <th>Ship</th>
                                        <th>Class</th>
                                        <th>Tech</th>
                                        <th>Race</th>
                                        <th>Role</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ready_ships as $ship): ?>
                                    <tr data-pocket6="<?= h(strtoupper((string)($ship['pocket6'] ?? ''))) ?>"
                                        data-ship="<?= h(strtolower((string)($ship['ShipName'] ?? ''))) ?>"
                                        data-class="<?= h(strtolower((string)($ship['TypeName'] ?? ''))) ?>"
                                        data-role="<?= h(strtolower((string)($ship['TypicalRole'] ?? ''))) ?>">
                                        <td><strong><?= h($ship['toon_name']) ?></strong></td>
                                        <td><?= get_pocket6_badge($ship['pocket6'] ?? '') ?></td>
                                        <td>
                                            <span class="ship-icon"><?= h(substr((string)$ship['ShipName'], 0, 2)) ?></span>
                                            <?= h($ship['ShipName']) ?>
                                        </td>
                                        <td><?= h($ship['TypeName']) ?></td>
                                        <td>
                                            <span class="badge tech-badge-<?= h(strtolower((string)($ship['Tech'] ?? ''))) ?>">
                                                <?= h($ship['Tech']) ?>
                                            </span>
                                        </td>
                                        <td><?= h($ship['Race']) ?></td>
                                        <td>
                                            <?php if (!empty($ship['TypicalRole'])): ?>
                                                <span class="badge text-bg-info"><?= h($ship['TypicalRole']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="location-cell" title="<?= h($ship['location_desc']) ?>">
                                            <?= h($ship['location_desc'] ?? '—') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- HANGAR PANEL -->
                <div class="tab-pane fade" id="hangar-panel" role="tabpanel">
                    <div class="eve-card">
                        <div class="eve-card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-warehouse text-secondary me-2"></i>Hangar / Transport / For Sale</span>
                            <span class="text-muted small">Unfitted or stacked</span>
                        </div>
                        <div class="p-0">
                            <table class="table table-dark-custom table-hover mb-0" id="table-hangar" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Pilot</th>
                                        <th>Pocket6</th>
                                        <th>Ship</th>
                                        <th>Class</th>
                                        <th>Tech</th>
                                        <th>Race</th>
                                        <th>Role</th>
                                        <th>Sub-State</th>
                                        <th>Quantity</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hangar_ships as $ship): ?>
                                    <tr data-pocket6="<?= h(strtoupper((string)($ship['pocket6'] ?? ''))) ?>"
                                        data-ship="<?= h(strtolower((string)($ship['ShipName'] ?? ''))) ?>"
                                        data-class="<?= h(strtolower((string)($ship['TypeName'] ?? ''))) ?>"
                                        data-role="<?= h(strtolower((string)($ship['TypicalRole'] ?? ''))) ?>">
                                        <td><strong><?= h($ship['toon_name']) ?></strong></td>
                                        <td><?= get_pocket6_badge($ship['pocket6'] ?? '') ?></td>
                                        <td>
                                            <span class="ship-icon"><?= h(substr((string)$ship['ShipName'], 0, 2)) ?></span>
                                            <?= h($ship['ShipName']) ?>
                                        </td>
                                        <td><?= h($ship['TypeName']) ?></td>
                                        <td>
                                            <span class="badge tech-badge-<?= h(strtolower((string)($ship['Tech'] ?? ''))) ?>">
                                                <?= h($ship['Tech']) ?>
                                            </span>
                                        </td>
                                        <td><?= h($ship['Race']) ?></td>
                                        <td>
                                            <?php if (!empty($ship['TypicalRole'])): ?>
                                                <span class="badge text-bg-info"><?= h($ship['TypicalRole']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= get_substate_badge($ship['substate']) ?></td>
                                        <td class="text-center">
                                            <span class="badge text-bg-dark"><?= (int)$ship['quantity'] ?></span>
                                        </td>
                                        <td class="location-cell" title="<?= h($ship['location_desc']) ?>">
                                            <?= h($ship['location_desc'] ?? '—') ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.2.0/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.2.0/js/dataTables.bootstrap5.min.js"></script>

<script>
let tableSummary, tableReady, tableHangar;

// Tables that the Pocket6/Ship/Class/Role filters apply to
const FILTERED_TABLES = ['table-ready', 'table-hangar'];

// Current filter state (read by the custom search function)
let activeFilters = { pocket6: [], ship: '', cls: '', role: '' };

// Custom search: registered once, only affects the ship tables
$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (!FILTERED_TABLES.includes(settings.nTable.id)) return true;

    const row = settings.aoData[dataIndex].nTr;
    if (!row) return true;

    const pocket6 = (row.getAttribute('data-pocket6') || '').toUpperCase();
    const ship    = row.getAttribute('data-ship')  || '';
    const cls     = row.getAttribute('data-class') || '';
    const role    = row.getAttribute('data-role')  || '';

    if (activeFilters.pocket6.length > 0 && !activeFilters.pocket6.includes(pocket6)) return false;
    if (activeFilters.ship && !ship.includes(activeFilters.ship)) return false;
    if (activeFilters.cls  && !cls.includes(activeFilters.cls))   return false;
    if (activeFilters.role && !role.includes(activeFilters.role)) return false;
    return true;
});

$(document).ready(function () {
    const common = {
        pageLength: 100,
        lengthMenu: [[25, 50, 100, 200, -1], [25, 50, 100, 200, 'All']]
    };

    // Summary list: sorted by ship count (column 2) descending, no paging
    tableSummary = $('#table-summary').DataTable({
        paging: false,
        info: false,
        searching: false,
        order: [[2, 'desc']],
        columnDefs: [{ targets: 3, orderable: false }]
    });

    tableReady  = $('#table-ready').DataTable({ ...common, order: [[0, 'asc']] });
    tableHangar = $('#table-hangar').DataTable({ ...common, order: [[0, 'asc']] });

    applyFilters();

    // Fix column widths when a hidden tab becomes visible
    document.querySelectorAll('#shipTabs button[data-bs-toggle="pill"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', () => {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        });
    });
});

function applyFilters() {
    activeFilters = {
        pocket6: Array.from(document.querySelectorAll('.pocket6-check:checked'))
                      .map(cb => cb.value.toUpperCase()),
        ship: document.getElementById('filter-ship').value.toLowerCase(),
        cls:  document.getElementById('filter-class').value.toLowerCase(),
        role: document.getElementById('filter-role').value.toLowerCase()
    };

    if (tableReady)  tableReady.draw();
    if (tableHangar) tableHangar.draw();
}

function resetFilters() {
    document.getElementById('filter-ship').value  = '';
    document.getElementById('filter-class').value = '';
    document.getElementById('filter-role').value  = '';

    document.querySelectorAll('.pocket6-check').forEach(cb => {
        cb.checked = ['CLEAN', 'EXPER', 'NOKIA', 'SANGO'].includes(cb.value.toUpperCase());
    });

    applyFilters();
}

// Apply filters on Enter key
['filter-ship', 'filter-class', 'filter-role'].forEach(id => {
    document.getElementById(id).addEventListener('keypress', function (e) {
        if (e.key === 'Enter') applyFilters();
    });
});
</script>

</body>
</html>
