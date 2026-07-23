<?php
/**
 * EVE Online - Ship Readiness Dashboard
 * PHP 8.4 Procedural | Bootstrap 6.4.x | DataTables 2.x | Font Awesome 5.15.4
 * 
 * Requiere: include "config.php" con $link (mysqli)
 * 
 * Lógica:
 * - Ready to Fly: qty=1 AND tiene hijos (módulos fitted) en EVE_ASSETS
 * - Hangar/Transporte: qty>1 (stack vendedor) OR qty=1 sin hijos (vacía)
 * - Excluye toon_number = 2114226800 (personaje de control)
 */

// ─── CONFIG ─────────────────────────────────────────────────────────
include "../config.php";

// ─── HELPERS ────────────────────────────────────────────────────────

function get_pocket6_badge(string $pocket6): string {
    $pocket6 = strtoupper(trim($pocket6));
    $map = [
        'NOKIA'   => ['class' => 'danger',  'icon' => 'fa-exclamation-triangle'],
        'EXPER'   => ['class' => 'success', 'icon' => 'fa-check-circle'],
        'CLEAN'   => ['class' => 'primary', 'icon' => 'fa-shield-alt'],
        'SANGO'   => ['class' => 'warning', 'icon' => 'fa-exclamation-circle'],
    ];

    if (isset($map[$pocket6])) {
        $m = $map[$pocket6];
        return sprintf(
            '<span class="badge text-bg-%s"><i class="fas %s me-1"></i>%s</span>',
            $m['class'], $m['icon'], htmlspecialchars($pocket6)
        );
    }

    return sprintf(
        '<span class="badge text-bg-secondary"><i class="fas fa-question-circle me-1"></i>%s</span>',
        htmlspecialchars($pocket6 ?: 'UNKNOWN')
    );
}

function get_substate_badge(string $substate): string {
    $map = [
        'stack'  => ['class' => 'info',    'label' => 'Stack Vendedor', 'icon' => 'fa-layer-group'],
        'empty'  => ['class' => 'secondary', 'label' => 'Vacía / Sin Equipo', 'icon' => 'fa-box-open'],
    ];
    $m = $map[$substate] ?? ['class' => 'secondary', 'label' => $substate, 'icon' => 'fa-question'];
    return sprintf(
        '<span class="badge text-bg-%s"><i class="fas %s me-1"></i>%s</span>',
        $m['class'], $m['icon'], $m['label']
    );
}

// ─── DATA FETCHING ─────────────────────────────────────────────────

function fetch_ready_ships($link) {
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
            a.forge_value
        FROM EVE_ASSETS a
        INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
        INNER JOIN PILOTS p ON a.toon_number = p.toon_number
        WHERE a.toon_number != 2114226800
          AND a.quantity = 1
          AND EXISTS (
              SELECT 1 FROM EVE_ASSETS child 
              WHERE child.location_id = a.eveunique
                AND child.toon_number = a.toon_number
          )
        ORDER BY p.toon_name, s.ShipName
    ";

    $result = mysqli_query($link, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_free_result($result);
    return $data;
}

function fetch_hangar_ships($link) {
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
            a.forge_value,
            CASE 
                WHEN a.quantity > 1 THEN 'stack'
                ELSE 'empty'
            END AS substate
        FROM EVE_ASSETS a
        INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
        INNER JOIN PILOTS p ON a.toon_number = p.toon_number
        WHERE a.toon_number != 2114226800
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

    $result = mysqli_query($link, $sql);
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    mysqli_free_result($result);
    return $data;
}

// ─── POCKET6 FILTER OPTIONS ────────────────────────────────────────

function fetch_pocket6_values($link) {
    $sql = "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 IS NOT NULL AND pocket6 != '' ORDER BY pocket6";
    $result = mysqli_query($link, $sql);
    $values = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $values[] = $row['pocket6'];
    }
    mysqli_free_result($result);
    return $values;
}

// ─── MAIN ──────────────────────────────────────────────────────────

$ready_ships   = fetch_ready_ships($link);
$hangar_ships  = fetch_hangar_ships($link);
$pocket6_vals  = fetch_pocket6_values($link);

$ready_count   = count($ready_ships);
$hangar_count  = count($hangar_ships);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online - Ship Readiness Dashboard</title>

    <!-- Bootstrap 6.4.x -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 5.15.4 -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" rel="stylesheet">

    <!-- DataTables 2.x Bootstrap 5 -->
    <link href="https://cdn.datatables.net/2.2.0/css/dataTables.bootstrap4.min.css" rel="stylesheet">

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
            --bs-table-color: var(--eve-text);
            --bs-table-border-color: var(--eve-border);
        }

        .table-dark-custom thead th {
            background-color: #1f2937;
            color: var(--eve-accent);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--eve-border);
        }

        .table-dark-custom tbody tr {
            border-bottom: 1px solid var(--eve-border);
        }

        .table-dark-custom tbody tr:hover {
            background-color: rgba(245, 158, 11, 0.05);
        }

        .dt-search input, .dt-length select {
            background-color: #1f2937 !important;
            color: var(--eve-text) !important;
            border: 1px solid var(--eve-border) !important;
        }

        .dt-paging .page-link {
            background-color: #1f2937;
            color: var(--eve-text);
            border-color: var(--eve-border);
        }

        .dt-paging .page-item.active .page-link {
            background-color: var(--eve-accent);
            border-color: var(--eve-accent);
            color: #000;
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
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Fix DataTables 2.x with hidden columns */
        .dt-column-order {
            color: var(--eve-accent);
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
                        Dashboard de flota — <?= date('Y-m-d H:i') ?> UTC
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

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="filter-section">
                <div class="d-flex align-items-center mb-3">
                    <i class="fas fa-filter text-warning me-2"></i>
                    <strong>Filtros Globales</strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted small">Pocket6 (Multiselect)</label>
                        <div class="pocket6-filter d-flex flex-wrap gap-3">
                            <?php foreach ($pocket6_vals as $p6): 
                                $p6_upper = strtoupper($p6);
                                $checked = in_array($p6_upper, ['CLEAN','EXPER','NOKIA','SANGO']) ? 'checked' : '';
                            ?>
                            <div class="form-check">
                                <input class="form-check-input pocket6-check" type="checkbox" 
                                       value="<?= htmlspecialchars($p6) ?>" id="p6_<?= md5($p6) ?>" <?= $checked ?>>
                                <label class="form-check-label" for="p6_<?= md5($p6) ?>">
                                    <?= get_pocket6_badge($p6) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Nave</label>
                                <input type="text" id="filter-ship" class="form-control form-control-sm" 
                                       placeholder="Ej: Abaddon, Svipul...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Clase</label>
                                <input type="text" id="filter-class" class="form-control form-control-sm" 
                                       placeholder="Ej: Battleship, Destroyer...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Rol</label>
                                <input type="text" id="filter-role" class="form-control form-control-sm" 
                                       placeholder="Ej: Sniper, Drones...">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-sm btn-warning" onclick="applyFilters()">
                        <i class="fas fa-sync-alt me-1"></i>Aplicar Filtros
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="resetFilters()">
                        <i class="fas fa-undo me-1"></i>Limpiar
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
                        Hangar / Transporte
                        <span class="badge text-bg-secondary tab-badge"><?= $hangar_count ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="shipTabContent">
                <!-- READY TO FLY PANEL -->
                <div class="tab-pane fade show active" id="ready-panel" role="tabpanel">
                    <div class="eve-card">
                        <div class="eve-card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-check-circle text-success me-2"></i>Naves Listas para Volar</span>
                            <span class="text-muted small">Equipadas con módulos</span>
                        </div>
                        <div class="p-0">
                            <table class="table table-dark-custom table-hover mb-0" id="table-ready" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Piloto</th>
                                        <th>Pocket6</th>
                                        <th>Nave</th>
                                        <th>Clase</th>
                                        <th>Tech</th>
                                        <th>Raza</th>
                                        <th>Rol</th>
                                        <th>Ubicación</th>
                                        <th>Valor (ISK)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ready_ships as $ship): ?>
                                    <tr data-pocket6="<?= htmlspecialchars(strtoupper($ship['pocket6'] ?? '')) ?>"
                                        data-ship="<?= htmlspecialchars(strtolower($ship['ShipName'])) ?>"
                                        data-class="<?= htmlspecialchars(strtolower($ship['TypeName'])) ?>"
                                        data-role="<?= htmlspecialchars(strtolower($ship['TypicalRole'])) ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($ship['toon_name']) ?></strong>
                                        </td>
                                        <td><?= get_pocket6_badge($ship['pocket6'] ?? '') ?></td>
                                        <td>
                                            <span class="ship-icon"><?= substr($ship['ShipName'], 0, 2) ?></span>
                                            <?= htmlspecialchars($ship['ShipName']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($ship['TypeName']) ?></td>
                                        <td>
                                            <span class="badge tech-badge-<?= strtolower($ship['Tech']) ?>">
                                                <?= htmlspecialchars($ship['Tech']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($ship['Race']) ?></td>
                                        <td>
                                            <?php if ($ship['TypicalRole']): ?>
                                                <span class="badge text-bg-info"><?= htmlspecialchars($ship['TypicalRole']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="location-cell" title="<?= htmlspecialchars($ship['location_desc'] ?? '') ?>">
                                            <?= htmlspecialchars($ship['location_desc'] ?? '—') ?>
                                        </td>
                                        <td class="text-end font-monospace">
                                            <?= number_format((float)($ship['forge_value'] ?? 0), 2) ?>
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
                            <span><i class="fas fa-warehouse text-secondary me-2"></i>Hangar / Transporte / Venta</span>
                            <span class="text-muted small">Sin equipo o en stacks</span>
                        </div>
                        <div class="p-0">
                            <table class="table table-dark-custom table-hover mb-0" id="table-hangar" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Piloto</th>
                                        <th>Pocket6</th>
                                        <th>Nave</th>
                                        <th>Clase</th>
                                        <th>Tech</th>
                                        <th>Raza</th>
                                        <th>Rol</th>
                                        <th>Sub-Estado</th>
                                        <th>Cantidad</th>
                                        <th>Ubicación</th>
                                        <th>Valor (ISK)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hangar_ships as $ship): ?>
                                    <tr data-pocket6="<?= htmlspecialchars(strtoupper($ship['pocket6'] ?? '')) ?>"
                                        data-ship="<?= htmlspecialchars(strtolower($ship['ShipName'])) ?>"
                                        data-class="<?= htmlspecialchars(strtolower($ship['TypeName'])) ?>"
                                        data-role="<?= htmlspecialchars(strtolower($ship['TypicalRole'])) ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($ship['toon_name']) ?></strong>
                                        </td>
                                        <td><?= get_pocket6_badge($ship['pocket6'] ?? '') ?></td>
                                        <td>
                                            <span class="ship-icon"><?= substr($ship['ShipName'], 0, 2) ?></span>
                                            <?= htmlspecialchars($ship['ShipName']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($ship['TypeName']) ?></td>
                                        <td>
                                            <span class="badge tech-badge-<?= strtolower($ship['Tech']) ?>">
                                                <?= htmlspecialchars($ship['Tech']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($ship['Race']) ?></td>
                                        <td>
                                            <?php if ($ship['TypicalRole']): ?>
                                                <span class="badge text-bg-info"><?= htmlspecialchars($ship['TypicalRole']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= get_substate_badge($ship['substate']) ?></td>
                                        <td class="text-center">
                                            <span class="badge text-bg-dark"><?= (int)$ship['quantity'] ?></span>
                                        </td>
                                        <td class="location-cell" title="<?= htmlspecialchars($ship['location_desc'] ?? '') ?>">
                                            <?= htmlspecialchars($ship['location_desc'] ?? '—') ?>
                                        </td>
                                        <td class="text-end font-monospace">
                                            <?= number_format((float)($ship['forge_value'] ?? 0), 2) ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.2.0/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.2.0/js/dataTables.bootstrap4.min.js"></script>

<script>
let tableReady, tableHangar;

$(document).ready(function() {
    // Initialize DataTables 2.x - NO columnDefs con orderable en indices inexistentes
    tableReady = $('#table-ready').DataTable({
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/2.2.0/i18n/es-ES.json'
        },
        order: [[0, 'asc']],
        // No usar columnDefs con targets que no existan
        initComplete: function() {
            applyFilters();
        }
    });

    tableHangar = $('#table-hangar').DataTable({
        pageLength: 25,
        language: {
            url: '//cdn.datatables.net/plug-ins/2.2.0/i18n/es-ES.json'
        },
        order: [[0, 'asc']],
        initComplete: function() {
            applyFilters();
        }
    });
});

function applyFilters() {
    // Get selected pocket6 values
    const selectedP6 = [];
    document.querySelectorAll('.pocket6-check:checked').forEach(cb => {
        selectedP6.push(cb.value.toUpperCase());
    });

    const shipFilter = document.getElementById('filter-ship').value.toLowerCase();
    const classFilter = document.getElementById('filter-class').value.toLowerCase();
    const roleFilter = document.getElementById('filter-role').value.toLowerCase();

    // Custom search function for each table
    const customSearch = function(settings, data, dataIndex) {
        const tableId = settings.nTable.id;
        const row = settings.aoData[dataIndex].nTr;

        if (!row) return true;

        const pocket6 = (row.getAttribute('data-pocket6') || '').toUpperCase();
        const ship = (row.getAttribute('data-ship') || '').toLowerCase();
        const cls = (row.getAttribute('data-class') || '').toLowerCase();
        const role = (row.getAttribute('data-role') || '').toLowerCase();

        // Pocket6 filter
        if (selectedP6.length > 0 && !selectedP6.includes(pocket6)) {
            return false;
        }

        // Ship name filter
        if (shipFilter && !ship.includes(shipFilter)) {
            return false;
        }

        // Class filter
        if (classFilter && !cls.includes(classFilter)) {
            return false;
        }

        // Role filter
        if (roleFilter && !role.includes(roleFilter)) {
            return false;
        }

        return true;
    };

    // Apply custom search and redraw
    if (tableReady) {
        tableReady.search('').draw(); // Clear text search first
        $.fn.dataTable.ext.search = [customSearch];
        tableReady.draw();
    }

    if (tableHangar) {
        tableHangar.search('').draw();
        // Need to set search array for hangar too - but ext.search is global
        // So we handle both tables in one function
        $.fn.dataTable.ext.search = [function(settings, data, dataIndex) {
            return customSearch(settings, data, dataIndex);
        }];
        tableHangar.draw();
    }
}

function resetFilters() {
    document.getElementById('filter-ship').value = '';
    document.getElementById('filter-class').value = '';
    document.getElementById('filter-role').value = '';

    // Reset pocket6 to defaults
    document.querySelectorAll('.pocket6-check').forEach(cb => {
        const val = cb.value.toUpperCase();
        cb.checked = ['CLEAN','EXPER','NOKIA','SANGO'].includes(val);
    });

    // Clear custom search
    $.fn.dataTable.ext.search = [];

    if (tableReady) {
        tableReady.search('').columns().search('').draw();
    }
    if (tableHangar) {
        tableHangar.search('').columns().search('').draw();
    }
}

// Live filter on Enter key
['filter-ship', 'filter-class', 'filter-role'].forEach(id => {
    document.getElementById(id).addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilters();
    });
});
</script>

</body>
</html>
