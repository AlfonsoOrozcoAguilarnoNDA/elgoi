<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
// =====================================================================
// EVE PANEL — Planetary Inventory · WhoHas · HangarAll
// The page name shown in the header and the session keys are derived
// from this file's name, so the file can be renamed freely.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

// Detected from the current file name (e.g. eve_panel.php => EVE_PANEL)
$nombre_archivo = strtoupper(basename(__FILE__, '.php'));

// Session keys are namespaced by file name so several copies never collide
$sessKeyTab    = 'tab_' . $nombre_archivo;
$sessKeyPocket = 'whohas_pocket_' . $nombre_archivo;

// =====================================================================
// TABS — state handling (first tab is the default)
// =====================================================================
$valid_tabs = ['pi', 'whohas', 'hangar'];

if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)) {
    $_SESSION[$sessKeyTab] = $_GET['tab'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_hangar_submit'])) { $_SESSION[$sessKeyTab] = 'hangar'; }
    if (isset($_POST['_whohas_submit'])) { $_SESSION[$sessKeyTab] = 'whohas'; }
}
$tabActivo = $_SESSION[$sessKeyTab] ?? 'pi';
if (!in_array($tabActivo, $valid_tabs)) $tabActivo = 'pi';

// =====================================================================
// POCKET COLORS
// =====================================================================
// HangarAll palette
$pocket6_colors = [
    'SANGO' => '#FFF9C4', 'EXPER' => '#C8E6C9', 'NOKIA' => '#FFCDD2',
    'CLEAN' => '#BBDEFB', 'LUCKY' => '#FFFFFF',  'YENN'  => '#E0E0E0',
];
// WhoHas palette
$pocket_colors = [
    'NOKIA' => '#FFE5E5', 'CLEAN' => '#E5F5FF',
    'EXPER' => '#E5FFE5', 'SANGO' => '#fff3cd', 'DEFAULT' => '#FFFFFF',
];
// Planetary Inventory palette (solid badge colors)
$pi_pocket_colors = [
    'EXPER' => '#28a745', 'CLEAN' => '#0078d7', 'SANGO' => '#ffc107',
    'LUCKY' => '#6f42c1', 'NOKIA' => '#e81123', 'YENN'  => '#cccccc',
    'OTHER' => '#fd7e14',
];

define('TOON_IGNORE', 2114226800);
$timestamp = date('d/m/Y H:i:s');

// =====================================================================
// PLANETARY INVENTORY — query (only when the tab is active)
// =====================================================================
$pi_result     = null;
$pi_total_rows = 0;

$pi_tiers = [
    1042 => ['label' => 'P1 - Basic',       'color' => '#6c757d'],
    1034 => ['label' => 'P2 - Refined',     'color' => '#17a2b8'],
    1040 => ['label' => 'P3 - Specialized', 'color' => '#007bff'],
    1041 => ['label' => 'P4 - Advanced',    'color' => '#6f42c1'],
];

if ($tabActivo === 'pi') {
    $sql = "SELECT
                T.typeName,
                T.groupID,
                P.toon_name,
                P.pocket6,
                P.gf,
                P.acctype,
                SUM(A.quantity) as total_qty
            FROM invTypes T
            INNER JOIN EVE_ASSETS A ON T.typeID = A.type_id
            INNER JOIN PILOTS P ON A.toon_number = P.toon_number
            WHERE T.groupID IN (1042, 1034, 1040, 1041)
            GROUP BY T.typeID, P.toon_number, P.acctype
            ORDER BY T.groupID ASC, T.typeName ASC";

    $pi_result     = mysqli_query($link, $sql);
    $pi_total_rows = $pi_result ? mysqli_num_rows($pi_result) : 0;
}

// =====================================================================
// HANGARALL — filters and query
// =====================================================================
$query_pocket6 = "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 IS NOT NULL AND pocket6 != '' ORDER BY pocket6";
$result_pocket6 = mysqli_query($link, $query_pocket6);
$pocket6_values = [];
while ($row = mysqli_fetch_assoc($result_pocket6)) $pocket6_values[] = $row['pocket6'];

$query_types = "SELECT DISTINCT TypeName FROM EVE_SHIPS WHERE TypeName != '' ORDER BY TypeName";
$result_types = mysqli_query($link, $query_types);
$type_values = [];
while ($row = mysqli_fetch_assoc($result_types)) $type_values[] = $row['TypeName'];

$selected_pocket6 = []; $selected_type = ''; $all_types = false;
$pilots_data = []; $ships_list = []; $report_generated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_hangar_submit'])) {
    $selected_pocket6 = $_POST['pocket6'] ?? [];
    $selected_type    = $_POST['type_name'] ?? '';
    $all_types        = ($selected_type === 'ALL');

    if (!empty($selected_type)) {
        $report_generated = true;
        $pocket6_where = '';
        if (!empty($selected_pocket6)) {
            $pocket6_escaped = array_map(fn($p) => "'" . mysqli_real_escape_string($link, $p) . "'", $selected_pocket6);
            $pocket6_where = ' AND p.pocket6 IN (' . implode(',', $pocket6_escaped) . ')';
        }
        $type_where = $all_types
            ? " AND s.TypeName != 'Corvette'"
            : " AND s.TypeName = '" . mysqli_real_escape_string($link, $selected_type) . "'";

        $query = "SELECT p.toon_number, p.toon_name, p.DOB, p.pocket6,
                         s.ShipName, s.TypeName, SUM(a.quantity) as total_naves
                  FROM EVE_ASSETS a
                  INNER JOIN PILOTS p ON a.toon_number = p.toon_number
                  INNER JOIN EVE_SHIPS s ON a.type_description = s.ShipName
                  WHERE 1=1 $type_where $pocket6_where
                  GROUP BY p.toon_number, s.ShipName
                  ORDER BY p.DOB ASC, s.ShipName ASC";

        $result = mysqli_query($link, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $tn = $row['toon_number']; $sn = $row['ShipName'];
                if (!isset($pilots_data[$tn]))
                    $pilots_data[$tn] = ['name' => $row['toon_name'], 'dob' => $row['DOB'],
                                         'pocket6' => $row['pocket6'], 'ships' => [], 'total' => 0];
                $pilots_data[$tn]['ships'][$sn] = (int)$row['total_naves'];
                $pilots_data[$tn]['total'] += (int)$row['total_naves'];
                $ships_list[$sn] = ($ships_list[$sn] ?? 0) + (int)$row['total_naves'];
            }
        }
        ksort($ships_list);
    }
}

// =====================================================================
// WHOHAS — pocket filter
// =====================================================================
$filtro_pocket_global = 'ALL';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_whohas_submit'])) {
    $filtro_pocket_global = $_POST['pocket_filter'] ?? 'ALL';
    $_SESSION[$sessKeyPocket] = $filtro_pocket_global;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pocket_filter']) && !isset($_POST['_hangar_submit'])) {
    $filtro_pocket_global = $_POST['pocket_filter'] ?? 'ALL';
    $_SESSION[$sessKeyPocket] = $filtro_pocket_global;
} else {
    $filtro_pocket_global = $_SESSION[$sessKeyPocket] ?? 'ALL';
}

// =====================================================================
// WHOHAS — functions
// =====================================================================

// Queries the given comma-separated items and renders one grid
function cuadricula($items_string, $titulo_grid, $icono_fa) {
    global $link, $filtro_pocket_global, $pocket_colors;
    $items = array_map('trim', explode(',', $items_string));
    $item_list = "'" . implode("','", array_map(fn($i) => mysqli_real_escape_string($link, $i), $items)) . "'";
    $where_pocket = "";
    if ($filtro_pocket_global !== 'ALL') {
        $pe = mysqli_real_escape_string($link, $filtro_pocket_global);
        $where_pocket = "AND p.pocket6 = '$pe'";
    }
    $query = "SELECT p.toon_number, p.toon_name, p.DOB, p.pocket6,
                     ea.type_description, SUM(ea.quantity) as total_quantity
              FROM EVE_ASSETS ea
              INNER JOIN PILOTS p ON ea.toon_number = p.toon_number
              WHERE ea.type_description IN ($item_list)
              AND ea.toon_number != " . TOON_IGNORE . "
              $where_pocket
              GROUP BY p.toon_number, p.toon_name, p.DOB, p.pocket6, ea.type_description
              ORDER BY p.DOB ASC, ea.type_description";
    $result = mysqli_query($link, $query);
    if (!$result) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error: ' . mysqli_error($link) . '</div>';
        return;
    }
    $data = []; $pilotos_orden = []; $pilotos_info = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tn = $row['toon_number'];
        if (!isset($data[$tn])) {
            $data[$tn] = []; $pilotos_orden[] = $tn;
            $pilotos_info[$tn] = ['name' => $row['toon_name'], 'dob' => $row['DOB'], 'pocket6' => $row['pocket6']];
        }
        $data[$tn][$row['type_description']] = $row['total_quantity'];
    }
    mysqli_free_result($result);
    renderizarCuadricula($data, $pilotos_orden, $pilotos_info, $items, $titulo_grid, $icono_fa, $pocket_colors);
}

// Renders the pilot x item grid
function renderizarCuadricula($data, $pilotos_orden, $pilotos_info, $items, $titulo, $icono, $pocket_colors) {
    $num_pilotos = count($pilotos_orden);
    if ($num_pilotos == 0) {
        echo '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> No pilots found with these items.</div>';
        return;
    }
    ?>
    <div class="wh-section-title">
        <i class="fas <?php echo htmlspecialchars($icono); ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
        <span class="badge badge-light ml-2"><?php echo $num_pilotos; ?> pilots</span>
        <span class="badge badge-light ml-2"><?php echo count($items); ?> items</span>
    </div>
    <div class="card mb-4 wh-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width:50px;">#</th>
                            <th><i class="fas fa-cube"></i> Item</th>
                            <?php foreach ($pilotos_orden as $tn):
                                $info = $pilotos_info[$tn];
                                $bg   = $pocket_colors[$info['pocket6']] ?? $pocket_colors['DEFAULT'];
                            ?>
                            <th class="text-center" style="background-color:<?php echo $bg; ?>;color:#000;min-width:110px;">
                                <strong><?php echo htmlspecialchars($info['name']); ?></strong><br>
                                <small style="font-size:.72em;"><?php echo htmlspecialchars($info['dob']); ?></small><br>
                                <span class="badge badge-secondary" style="font-size:.65em;"><?php echo htmlspecialchars($info['pocket6']); ?></span>
                            </th>
                            <?php endforeach; ?>
                            <th class="text-center" style="background-color:#343a40;color:white;"><i class="fas fa-calculator"></i> TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $totales_piloto = array_fill_keys($pilotos_orden, 0);
                    $counter = 1;
                    foreach ($items as $item):
                        $total_item = 0;
                        foreach ($pilotos_orden as $tn) $total_item += $data[$tn][$item] ?? 0;
                        if ($total_item == 0) continue;
                    ?>
                        <tr>
                            <td style="background-color:#343a40;color:white;" class="text-center"><strong><?php echo $counter; ?></strong></td>
                            <td style="background-color:#343a40;color:white;"><strong><?php echo htmlspecialchars($item); ?></strong></td>
                            <?php foreach ($pilotos_orden as $tn):
                                $bg  = $pocket_colors[$pilotos_info[$tn]['pocket6']] ?? $pocket_colors['DEFAULT'];
                                $qty = $data[$tn][$item] ?? 0;
                                $totales_piloto[$tn] += $qty;
                            ?>
                            <td class="text-right" style="background-color:<?php echo $bg; ?>;">
                                <?php echo $qty > 0 ? number_format($qty) : '-'; ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="text-right" style="background-color:#FFF9C4;font-weight:bold;"><?php echo number_format($total_item); ?></td>
                        </tr>
                    <?php $counter++; endforeach; ?>
                        <tr style="background-color:#E0E0E0;font-weight:bold;">
                            <td colspan="2" class="text-right">TOTAL:</td>
                            <?php $total_gral = 0; foreach ($pilotos_orden as $tn): $total_gral += $totales_piloto[$tn]; ?>
                            <td class="text-right"><?php echo number_format($totales_piloto[$tn]); ?></td>
                            <?php endforeach; ?>
                            <td class="text-right"><?php echo number_format($total_gral); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// =====================================================================
// HTML
// =====================================================================
echo ui_header("EVE Panel — " . $nombre_archivo);
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EVE Panel — <?php echo htmlspecialchars($nombre_archivo); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        body {
            background-color: #0d0f11;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 70px;
            padding-bottom: 70px;
        }

        /* ── TABS ── */
        .eve-tabs {
            display: flex;
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 0 16px;
            position: sticky;
            top: 56px;
            z-index: 900;
            margin-bottom: 0;
        }
        .eve-tabs .eve-tab {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 12px 22px;
            color: #6c757d;
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
        }
        .eve-tabs .eve-tab:hover { color: #ced4da; text-decoration: none; }
        .eve-tabs .eve-tab.active { color: #fff; border-bottom: 3px solid #007bff; }

        /* ── CONTENT ── */
        .tab-body { padding: 25px 15px; }

        /* ── PLANETARY INVENTORY ── */
        .page-header {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 14px 20px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h4 { color: #fff; margin: 0; font-weight: 600; }
        .total-badge {
            background-color: #0d0f11;
            border: 1px solid #007bff;
            color: #007bff;
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 3px;
        }

        /* DataTables dark theme */
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info { color: #adb5bd !important; }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background-color: #1e2126 !important;
            border: 1px solid #495057 !important;
            color: #e0e0e0 !important;
            border-radius: 3px;
            padding: 3px 8px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #adb5bd !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #007bff !important;
            color: #fff !important;
            border-color: #007bff !important;
            border-radius: 3px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #0056b3 !important;
            color: #fff !important;
            border-color: #0056b3 !important;
            border-radius: 3px;
        }

        /* PI table */
        #piTable { font-size: 0.83rem; }
        #piTable thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            text-transform: uppercase;
            font-size: 0.73rem;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        #piTable tbody tr { background-color: #1e2126; }
        #piTable tbody tr:nth-child(even) { background-color: #1a1d21; }
        #piTable tbody tr:hover { background-color: #2a3040 !important; color: #fff; }
        #piTable td, #piTable th { border-color: #2c3035 !important; vertical-align: middle !important; }

        /* Tier badge */
        .tier-badge {
            display: inline-block;
            padding: 2px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            border-radius: 2px;
            color: #fff;
            white-space: nowrap;
        }

        /* Pocket badge */
        .pocket-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
        }

        /* Account type badge */
        .acc-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
            border: 1px solid #495057;
            color: #ced4da;
            background-color: #1a1d21;
        }
        .acc-omega { border-color: #f1c40f; color: #f1c40f; }
        .acc-alpha { border-color: #95a5a6; color: #95a5a6; }

        /* Quantity */
        .qty-val { color: #28a745; font-family: monospace; font-weight: 700; }

        /* GF flag */
        .flag-active   { color: #dc3545; font-size: 1rem; }
        .flag-inactive { color: #2c3035; font-size: 1rem; }

        /* ── FILTER BARS ── */
        .filter-dark {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .filter-dark .card-header {
            background-color: #0d0f11;
            color: #adb5bd;
            border-bottom: 1px solid #343a40;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-dark .card-body { background-color: #1a1d21; }
        .filter-dark .form-control,
        .filter-dark .form-control:focus {
            background-color: #0d0f11;
            border-color: #495057;
            color: #e0e0e0;
            box-shadow: none;
        }
        .filter-dark label { color: #adb5bd; font-size: 0.82rem; }
        .filter-dark .pocket-scroll {
            border: 1px solid #495057;
            border-radius: 3px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            background-color: #0d0f11;
        }
        .filter-dark .form-check-label { color: #ced4da; }

        /* ── HANGARALL REPORT ── */
        .report-card {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .report-card .card-header {
            background-color: #0d0f11;
            color: #adb5bd;
            border-bottom: 1px solid #343a40;
            font-size: 0.85rem;
        }
        .report-card table { color: #ced4da; }
        .report-card .summary-box {
            background-color: #0d0f11;
            border: 1px solid #343a40;
            border-radius: 3px;
            padding: 15px;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        .report-card .summary-box li { margin-bottom: 4px; color: #ced4da; }
        .report-card .summary-box strong { color: #fff; }

        /* ── WHOHAS ── */
        .wh-section-title {
            background-color: #1a1d21;
            border-left: 4px solid #007bff;
            color: #fff;
            padding: 12px 18px;
            margin-top: 22px;
            margin-bottom: 14px;
            border-radius: 0;
            font-size: 1rem;
            font-weight: 700;
            border-top: 1px solid #343a40;
            border-right: 1px solid #343a40;
            border-bottom: 1px solid #343a40;
        }
        .wh-card {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
        }
        .wh-filter-box {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-left: 4px solid #007bff;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .wh-filter-box label { color: #adb5bd; }
        .wh-filter-box .form-control,
        .wh-filter-box .form-control:focus {
            background-color: #0d0f11;
            border-color: #495057;
            color: #e0e0e0;
            box-shadow: none;
        }
        .wh-timestamp {
            background-color: #1a1d21;
            border: 1px solid #495057;
            border-left: 4px solid #28a745;
            padding: 8px 14px;
            margin-bottom: 15px;
            font-size: 0.83rem;
            color: #adb5bd;
        }
        .wh-info-box {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-left: 4px solid #17a2b8;
            padding: 12px 16px;
            margin-bottom: 18px;
            font-size: 0.85rem;
            color: #adb5bd;
        }
        .wh-info-box h5 { color: #e0e0e0; font-size: 0.95rem; }
        .pocket-legend {
            display: inline-block;
            padding: 4px 12px;
            margin: 3px;
            border-radius: 2px;
            border: 1px solid #495057;
            font-weight: bold;
            font-size: 0.78rem;
            color: #111;
        }

        /* ── FIXED FOOTER ── */
        .footer-fixed {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 7px 15px;
            background-color: #0d0f11;
            color: #6c757d;
            font-size: .8rem;
            border-top: 2px solid #007bff;
            z-index: 1040;
        }
    </style>
</head>
<body>

<!-- ── TABS ── -->
<div class="eve-tabs">
    <a href="?tab=pi" class="eve-tab <?php echo $tabActivo === 'pi' ? 'active' : ''; ?>">
        <i class="fas fa-globe-americas"></i> Planetary
    </a>
    <a href="?tab=whohas" class="eve-tab <?php echo $tabActivo === 'whohas' ? 'active' : ''; ?>">
        <i class="fas fa-search"></i> WhoHas
        <?php if ($filtro_pocket_global !== 'ALL'): ?>
        <span class="badge badge-primary" style="font-size:.68rem;"><?php echo htmlspecialchars($filtro_pocket_global); ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=hangar" class="eve-tab <?php echo $tabActivo === 'hangar' ? 'active' : ''; ?>">
        <i class="fas fa-warehouse"></i> HangarAll
    </a>
</div>

<div class="tab-body">
<div class="container-fluid">

<!-- ================================================================
     TAB 1 — PLANETARY INVENTORY (default)
     ================================================================ -->
<?php if ($tabActivo === 'pi'): ?>

<div class="page-header">
    <h4><i class="fas fa-globe-americas mr-2" style="color:#007bff;"></i>PI Inventory Manager</h4>
    <span class="total-badge"><i class="fas fa-list mr-1"></i><?php echo number_format($pi_total_rows); ?> records</span>
</div>

<div class="table-responsive rounded shadow">
    <table id="piTable" class="table table-sm table-bordered w-100">
        <thead>
            <tr>
                <th>Tier</th>
                <th>Material</th>
                <th>Pilot</th>
                <th class="text-center">Pocket</th>
                <th class="text-center">Flag</th>
                <th class="text-right">Quantity</th>
                <th class="text-center">Acc</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($pi_result): while ($row = mysqli_fetch_assoc($pi_result)):
                $tier    = $pi_tiers[$row['groupID']];
                $pv      = strtoupper(trim($row['pocket6'] ?? ''));
                $pb      = $pi_pocket_colors[$pv] ?? '#495057';
                $pt      = in_array($pv, ['SANGO','YENN']) ? '#111' : '#fff';
                $acctype = strtolower($row['acctype'] ?? '');
                $accClass = ($acctype === 'omega') ? 'acc-omega' : (($acctype === 'alpha') ? 'acc-alpha' : '');
            ?>
            <tr>
                <td data-order="<?php echo $row['groupID']; ?>">
                    <span class="tier-badge" style="background-color:<?php echo $tier['color']; ?>;">
                        <?php echo $tier['label']; ?>
                    </span>
                </td>
                <td class="text-white font-weight-bold"><?php echo htmlspecialchars($row['typeName']); ?></td>
                <td class="text-white font-weight-bold"><?php echo htmlspecialchars($row['toon_name']); ?></td>
                <td class="text-center">
                    <span class="pocket-badge" style="background-color:<?php echo $pb; ?>;color:<?php echo $pt; ?>;">
                        <?php echo htmlspecialchars($row['pocket6']); ?>
                    </span>
                </td>
                <td class="text-center" data-order="<?php echo $row['gf']; ?>">
                    <i class="fas fa-flag <?php echo ($row['gf'] == 1) ? 'flag-active' : 'flag-inactive'; ?>"
                       title="<?php echo ($row['gf'] == 1) ? 'GF active' : 'No GF'; ?>"></i>
                </td>
                <td class="text-right qty-val" data-order="<?php echo $row['total_qty']; ?>">
                    <?php echo number_format($row['total_qty'], 0, '.', ','); ?>
                </td>
                <td class="text-center">
                    <span class="acc-badge <?php echo $accClass; ?>">
                        <?php echo htmlspecialchars(strtoupper($row['acctype'])); ?>
                    </span>
                </td>
            </tr>
            <?php endwhile; endif; ?>
        </tbody>
    </table>
</div>

<?php endif; // end pi ?>

<!-- ================================================================
     TAB 2 — WHOHAS
     ================================================================ -->
<?php if ($tabActivo === 'whohas'): ?>

<div class="wh-timestamp">
    <i class="fas fa-clock mr-1"></i> Generated: <strong><?php echo $timestamp; ?></strong> (Mexico City time)
</div>

<div class="wh-info-box">
    <h5><i class="fas fa-info-circle mr-1"></i> Custom Item Inventory</h5>
    <p class="mb-0">Query inventories of specific items organized by pilot. Real-time data. Pilots are sorted by date of birth and colored by pocket.</p>
</div>

<div class="wh-filter-box">
    <form method="POST" action="?tab=whohas">
        <input type="hidden" name="_whohas_submit" value="1">
        <div class="row align-items-center">
            <div class="col-md-2">
                <label class="mb-0"><i class="fas fa-folder mr-1"></i> <strong>Filter by Pocket:</strong></label>
            </div>
            <div class="col-md-7">
                <select name="pocket_filter" class="form-control" onchange="this.form.submit()">
                    <option value="ALL"   <?php echo $filtro_pocket_global === 'ALL'   ? 'selected' : ''; ?>>ALL POCKETS</option>
                    <option value="NOKIA" <?php echo $filtro_pocket_global === 'NOKIA' ? 'selected' : ''; ?>>NOKIA</option>
                    <option value="CLEAN" <?php echo $filtro_pocket_global === 'CLEAN' ? 'selected' : ''; ?>>CLEAN</option>
                    <option value="EXPER" <?php echo $filtro_pocket_global === 'EXPER' ? 'selected' : ''; ?>>EXPER</option>
                    <option value="SANGO" <?php echo $filtro_pocket_global === 'SANGO' ? 'selected' : ''; ?>>SANGO</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sync-alt mr-1"></i> Apply
                </button>
            </div>
        </div>
        <?php if ($filtro_pocket_global !== 'ALL'): ?>
        <div class="mt-2">
            <span class="badge badge-primary" style="font-size:0.85em;padding:5px 12px;">
                <i class="fas fa-filter mr-1"></i> Active pocket: <strong><?php echo htmlspecialchars($filtro_pocket_global); ?></strong>
            </span>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Color legend -->
<div class="card wh-card mb-4">
    <div class="card-body py-2">
        <small class="text-muted mr-2"><i class="fas fa-palette mr-1"></i> Legend:</small>
        <span class="pocket-legend" style="background-color:#FFE5E5;">NOKIA</span>
        <span class="pocket-legend" style="background-color:#E5F5FF;">CLEAN</span>
        <span class="pocket-legend" style="background-color:#E5FFE5;">EXPER</span>
        <span class="pocket-legend" style="background-color:#FFF5E5;">SANGO</span>
        <span class="pocket-legend" style="background-color:#FFFFFF;border:1px solid #495057;">OTHER</span>
    </div>
</div>

<?php
// Item names must stay exactly as they appear in the EVE_ASSETS table
cuadricula('Tritanium,Pyerite,Mexallon,Isogen,Nocxium,Zydrine,Megacyte',
           'Basic Minerals Inventory', 'fa-gem');
cuadricula('Compressed Bezdnacine,Compressed Concentrated Veldspar,Compressed Condensed Scordite,Compressed Dense Veldspar,Compressed Massive Scordite,Compressed Rich Plagioclase,Compressed Scordite,Compressed Veldspar,Concentrated Veldspar,Dense Veldspar,Veldspar',
           'Specific Asteroid Ore Inventory', 'fa-mountain');
cuadricula('Caracal,Catalyst,Punisher,Kestrel,Cormorant,Merlin,Condor,Thrasher,Thorax,Talwar',
           'Abyss Ships Inventory', 'fa-rocket');
cuadricula('Orca,Porpoise,Occator,Kernite,Compressed Kernite',
           'Pocket Control Overview', 'fa-info-circle');
cuadricula('Tranquil Dark Filament,Tranquil Electrical Filament,Tranquil Exotic Filament,Tranquil Firestorm Filament,Tranquil Gamma Filament,Calm Dark Filament,Calm Electrical Filament,Calm Exotic Filament,Calm Firestorm Filament,Calm Gamma Filament,Agitated Dark Filament,Agitated Electrical Filament,Agitated Exotic Filament,Agitated Firestorm Filament,Agitated Gamma Filament',
           'Abyss Filaments', 'fa-bolt');
cuadricula('Scourge Light Missile,Antimatter Charge S,Antimatter Charge M,Nuclear S',
           'Trade Ammunition Inventory', 'fa-crosshairs');
cuadricula('PLEX,Skill Injector,Skill Extractor',
           'Valuable Items Inventory', 'fa-coins');
?>

<?php endif; // end whohas ?>

<!-- ================================================================
     TAB 3 — HANGARALL
     ================================================================ -->
<?php if ($tabActivo === 'hangar'): ?>

<div class="card filter-dark mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i> Search Filters
    </div>
    <div class="card-body">
        <form method="POST" action="?tab=hangar">
            <input type="hidden" name="_hangar_submit" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><i class="fas fa-space-shuttle mr-1"></i> Ship Type *</label>
                        <select class="form-control" name="type_name" required>
                            <option value="">-- Select a type --</option>
                            <option value="ALL" <?php echo $selected_type === 'ALL' ? 'selected' : ''; ?>>
                                ** ALL (Except Corvette) **
                            </option>
                            <?php foreach ($type_values as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"
                                <?php echo $selected_type === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><i class="fas fa-folder mr-1"></i> Pocket6 (optional — multiple)</label>
                        <div class="pocket-scroll">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pocket_all"
                                       onclick="toggleAllPockets(this)">
                                <label class="form-check-label" for="pocket_all"><strong>ALL</strong></label>
                            </div>
                            <hr style="margin:5px 0;border-color:#495057;">
                            <?php foreach ($pocket6_values as $pocket): ?>
                            <div class="form-check">
                                <input class="form-check-input pocket-checkbox" type="checkbox"
                                       name="pocket6[]"
                                       value="<?php echo htmlspecialchars($pocket); ?>"
                                       id="pck_<?php echo htmlspecialchars($pocket); ?>"
                                       <?php echo in_array($pocket, $selected_pocket6) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pck_<?php echo htmlspecialchars($pocket); ?>">
                                    <?php echo htmlspecialchars($pocket); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search mr-1"></i> Generate Report
            </button>
        </form>
    </div>
</div>

<?php if ($report_generated): ?>
<div class="card report-card">
    <div class="card-header">
        <i class="fas fa-chart-bar mr-1"></i> Report as of <?php echo $timestamp; ?>
    </div>
    <div class="card-body">
        <?php if (empty($pilots_data)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i> No ships found with the selected filters.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table table-bordered table-sm" style="min-width:1200px;color:#ced4da;">
                <thead class="thead-dark">
                    <tr>
                        <th style="position:sticky;left:0;background:#0d0f11;z-index:10;">#</th>
                        <th style="position:sticky;left:40px;background:#0d0f11;z-index:10;min-width:200px;">Ship</th>
                        <?php foreach ($pilots_data as $tn => $pilot): ?>
                        <th style="min-width:120px;text-align:center;">
                            <div class="text-white"><?php echo htmlspecialchars($pilot['name']); ?></div>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($pilot['pocket6']); ?></small>
                            <small class="d-block text-muted"><?php echo date('d/m/Y', strtotime($pilot['dob'])); ?></small>
                        </th>
                        <?php endforeach; ?>
                        <th class="text-center" style="background-color:#007bff;color:#fff;">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php $row_num = 1; foreach ($ships_list as $ship_name => $total_ship): ?>
                    <tr>
                        <td style="position:sticky;left:0;background:#1a1d21;z-index:5;font-weight:bold;color:#6c757d;"><?php echo $row_num; ?></td>
                        <td style="position:sticky;left:40px;background:#1a1d21;z-index:5;color:#fff;"><?php echo htmlspecialchars($ship_name); ?></td>
                        <?php foreach ($pilots_data as $tn => $pilot):
                            $bg  = $pocket6_colors[$pilot['pocket6']] ?? '#FFFFFF';
                            $qty = $pilot['ships'][$ship_name] ?? 0;
                        ?>
                        <td style="background-color:<?php echo $bg; ?>;text-align:center;color:#111;">
                            <?php echo $qty > 0 ? $qty : '-'; ?>
                        </td>
                        <?php endforeach; ?>
                        <td style="background-color:#007bff;color:#fff;font-weight:bold;text-align:center;"><?php echo $total_ship; ?></td>
                    </tr>
                <?php $row_num++; endforeach; ?>
                    <tr>
                        <td colspan="2" style="position:sticky;left:0;background:#343a40;z-index:5;font-weight:bold;color:#ffc107;text-align:right;">
                            <i class="fas fa-calculator mr-1"></i> TOTAL PER PILOT
                        </td>
                        <?php foreach ($pilots_data as $tn => $pilot): ?>
                        <td style="text-align:center;font-weight:bold;background:#343a40;color:#ffc107;"><?php echo $pilot['total']; ?></td>
                        <?php endforeach; ?>
                        <td style="background:#dc3545;color:#fff;font-weight:bold;text-align:center;"><?php echo array_sum($ships_list); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="summary-box">
            <strong><i class="fas fa-info-circle mr-1"></i> Summary</strong>
            <ul class="mt-2 mb-0">
                <li><strong>Type:</strong> <?php echo $all_types ? 'ALL (Except Corvette)' : htmlspecialchars($selected_type); ?></li>
                <li><strong>Pilots:</strong> <?php echo count($pilots_data); ?></li>
                <li><strong>Total ships:</strong> <?php echo array_sum($ships_list); ?></li>
                <li><strong>Distinct models:</strong> <?php echo count($ships_list); ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // end hangar ?>

</div><!-- /container-fluid -->
</div><!-- /tab-body -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Checks / unchecks every pocket checkbox (HangarAll filter)
function toggleAllPockets(checkbox) {
    document.querySelectorAll('.pocket-checkbox').forEach(cb => cb.checked = checkbox.checked);
}
</script>
<?php echo ui_footer(); ?>
<?php if ($tabActivo === 'pi'): ?>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
// DataTable for the Planetary Inventory tab
$(document).ready(function() {
    $('#piTable').DataTable({
        pageLength: 50,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[0, "asc"], [1, "asc"]],
        language: {
            search:       "Search:",
            lengthMenu:   "Show _MENU_ records",
            info:         "Showing _START_ to _END_ of _TOTAL_ records",
            infoEmpty:    "No results",
            infoFiltered: "(filtered from _MAX_ total)",
            zeroRecords:  "No matching records found",
            paginate: { first: "«", last: "»", next: "›", previous: "‹" }
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });
});
</script>
<?php endif; ?>
</body>
</html>
