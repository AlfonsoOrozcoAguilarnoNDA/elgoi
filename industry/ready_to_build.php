<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Simple Mineral Burn Dashboard
 * Date: 2026-06-07
 * 
 * Purpose: Verify a simple and useful way to use the seven basic minerals
 * by breaking down production capacity per location.
 * This helps decide whether to manufacture ammo or ships to clear inventory.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

// Security
check_authorization();

// Set timezone
date_default_timezone_set('America/Mexico_City');

// Fetch all pilots for the dropdown
$pilots_query = "SELECT `toon_number`, `toon_name` FROM `PILOTS` ORDER BY `toon_name` ASC";
$pilots_result = mysqli_query($link, $pilots_query);
$pilots = [];
while ($row = mysqli_fetch_assoc($pilots_result)) {
    $pilots[] = $row;
}

// Get selected pilot (from GET or default to first)
$selected_toon = isset($_GET['toon_number']) ? intval($_GET['toon_number']) : ($pilots[0]['toon_number'] ?? 0);
$selected_name = '';
foreach ($pilots as $p) {
    if ($p['toon_number'] == $selected_toon) {
        $selected_name = $p['toon_name'];
        break;
    }
}

// Theme colors
$color = "#3498db";
$color2 = "#74b9ff";

// Ship recipes
$recetas_naves = [
    'Porpoise' => ['Tritanium' => 3240000, 'Pyerite' => 607500, 'Mexallon' => 151875, 'Isogen' => 81000, 'Nocxium' => 30375, 'Zydrine' => 5063, 'Megacyte' => 2835],
    'Hurricane' => ['Tritanium' => 2520000, 'Pyerite' => 900000, 'Mexallon' => 162000, 'Isogen' => 18000, 'Nocxium' => 7200, 'Zydrine' => 1800, 'Megacyte' => 360],
    'Caracal' => ['Tritanium' => 486000, 'Pyerite' => 162000, 'Mexallon' => 32400, 'Isogen' => 9000, 'Nocxium' => 1350, 'Zydrine' => 315, 'Megacyte' => 126],
    'Corax' => ['Tritanium' => 72000, 'Pyerite' => 13500, 'Mexallon' => 4500, 'Isogen' => 900],
    'Condor' => ['Tritanium' => 28800, 'Pyerite' => 5400, 'Mexallon' => 2250, 'Isogen' => 450]
];

// Ammo recipe (Antimatter Charge S)
$receta_ammo = [
    'Antimatter Charge S' => ['Tritanium' => 16360, 'Pyerite' => 1530, 'Nocxium' => 100]
];

// Mineral volumes (m³ per unit)
$volumenes_minerales = [
    'Tritanium' => 0.01,
    'Pyerite' => 0.01,
    'Mexallon' => 0.01,
    'Isogen' => 0.01,
    'Nocxium' => 0.01,
    'Zydrine' => 0.01,
    'Megacyte' => 0.01
];

// Ammo volume (m³ per unit)
$volumen_ammo = 0.0125;

$minerales = ['Tritanium', 'Pyerite', 'Isogen', 'Megacyte', 'Nocxium', 'Zydrine', 'Mexallon'];

/**
 * Fetch assets from database
 */
function obtenerAssets($toon_number, $tipos) {
    global $link;

    $tipos_str = "'" . implode("','", array_map(function($t) use ($link) {
        return mysqli_real_escape_string($link, $t);
    }, $tipos)) . "'";

    $query = "SELECT * FROM EVE_ASSETS 
              WHERE toon_number = ? 
              AND type_description IN ($tipos_str) 
              ORDER BY description, location_id ASC, type_id ASC, quantity ASC";

    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "i", $toon_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $assets = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $assets[] = $row;
    }

    mysqli_stmt_close($stmt);
    return $assets;
}

/**
 * Group assets by location and sum duplicates
 */
function agruparPorUbicacion($assets) {
    $agrupado = [];
    foreach ($assets as $asset) {
        $loc = $asset['description'] ?: "Container " . $asset['location_id'];
        if (!isset($agrupado[$loc])) {
            $agrupado[$loc] = [];
        }

        $tipo = $asset['type_description'];

        if (!isset($agrupado[$loc][$tipo])) {
            $agrupado[$loc][$tipo] = [
                'quantity' => 0,
                'unit_price' => $asset['unit_price'],
                'forge_value' => 0
            ];
        }

        $agrupado[$loc][$tipo]['quantity'] += $asset['quantity'];
        $agrupado[$loc][$tipo]['forge_value'] += $asset['forge_value'];
    }
    return $agrupado;
}

/**
 * Calculate possible production cycles
 */
function calcularCiclos($ubicacion_assets, $receta, $con_decimales = false) {
    $ciclos = PHP_INT_MAX;
    $limitante = '';

    foreach ($receta as $mineral => $cantidad_necesaria) {
        $cantidad_disponible = $ubicacion_assets[$mineral]['quantity'] ?? 0;
        $ciclos_posibles = $cantidad_disponible / $cantidad_necesaria;
        if ($ciclos_posibles < $ciclos) {
            $ciclos = $ciclos_posibles;
            $limitante = $mineral;
        }
    }

    if ($ciclos === PHP_INT_MAX) {
        return $con_decimales ? '0.00' : 0;
    }

    if ($con_decimales) {
        return number_format($ciclos, 2, '.', ',');
    } else {
        return number_format(floor($ciclos), 0, '.', ',');
    }
}

/**
 * Calculate mineral volume needed for a recipe
 */
function calcularVolumenMinerales($receta, $ciclos) {
    global $volumenes_minerales;

    $volumen_total = 0;
    foreach ($receta as $mineral => $cantidad) {
        $volumen_unitario = $volumenes_minerales[$mineral] ?? 0.01;
        $volumen_total += ($cantidad * $ciclos * $volumen_unitario);
    }
    return $volumen_total;
}

/**
 * Calculate volume savings when converting minerals to ammo
 */
function calcularAhorroVolumen($receta, $ciclos_numericos) {
    global $volumen_ammo;

    if ($ciclos_numericos <= 0) {
        return ['ahorro_m3' => 0, 'porcentaje' => 0];
    }

    $volumen_minerales = calcularVolumenMinerales($receta, $ciclos_numericos);
    $unidades_producidas = $ciclos_numericos * 100;
    $volumen_municion = $unidades_producidas * $volumen_ammo;

    $ahorro = $volumen_minerales - $volumen_municion;
    $porcentaje = $volumen_minerales > 0 ? ($ahorro / $volumen_minerales) * 100 : 0;

    return [
        'volumen_minerales' => $volumen_minerales,
        'volumen_municion' => $volumen_municion,
        'ahorro_m3' => $ahorro,
        'porcentaje' => $porcentaje
    ];
}

// Fetch data
$assets_minerales = obtenerAssets($selected_toon, $minerales);
$minerales_agrupados = agruparPorUbicacion($assets_minerales);

// Calculate totals
$total_valor_minerales = 0;
foreach ($assets_minerales as $asset) {
    $total_valor_minerales += $asset['forge_value'];
}

// Count locations with minerals
$active_locations = count($minerales_agrupados);

// Calculate stats for each mineral across all locations
$mineral_totals = [];
foreach ($minerales as $mineral) {
    $mineral_totals[$mineral] = 0;
}
foreach ($minerales_agrupados as $ubicacion => $minerales_data) {
    foreach ($minerales as $mineral) {
        $mineral_totals[$mineral] += $minerales_data[$mineral]['quantity'] ?? 0;
    }
}

// Calculate max for stat bars
$max_mineral = max($mineral_totals) ?: 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($selected_name); ?> - Production Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #0f1419;
            color: #e0e0e0;
        }

        .header-info {
            background: linear-gradient(135deg, #1a2332 0%, #2d3e50 100%);
            color: #e0e0e0;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 1px solid #2a3f54;
        }

        .header-info h1 {
            margin: 0 0 15px 0;
            font-size: 28px;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .header-info h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .header-info .description {
            line-height: 1.6;
            font-size: 14px;
            color: #b0b8c4;
        }

        .header-info .note-box {
            margin-top: 15px;
            padding: 12px 15px;
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid #3498db;
            border-radius: 4px;
            font-size: 13px;
            color: #74b9ff;
        }

        .header-info .note-box i {
            margin-right: 8px;
            color: #fdcb6e;
        }

        .pilot-selector {
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pilot-selector label {
            color: #74b9ff;
            font-weight: 600;
            font-size: 14px;
        }

        .pilot-selector select {
            background: #0f1419;
            color: #e0e0e0;
            border: 1px solid #3a4f64;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 14px;
            min-width: 250px;
            cursor: pointer;
        }

        .pilot-selector select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.3);
        }

        .pilot-selector .btn-go {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }

        .pilot-selector .btn-go:hover {
            background: #2ecc71;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
        }

        .container {
            background: #1a2332;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 1px solid #2a3f54;
            margin-bottom: 25px;
        }

        .section-title {
            margin: 0 0 20px 0;
            color: #ffffff;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #e17055;
        }

        /* Stats boxes */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: #0f1419;
            border: 1px solid #2a3f54;
            border-radius: 8px;
            padding: 15px;
        }

        .stat-box .stat-label {
            color: #b0b8c4;
            font-size: 0.85em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-box .stat-value {
            color: #ffffff;
            font-size: 1.4em;
            font-weight: bold;
        }

        .stat-box .stat-value.isk {
            color: #fdcb6e;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            background: #1a2332;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        table thead th {
            background-color: #2d3e50;
            color: #ffffff;
            padding: 12px;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            text-align: left;
        }

        table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #2a3f54;
        }

        table tbody tr {
            background-color: #1a2332;
        }

        table tbody tr:nth-child(even) {
            background-color: #1f2d3d;
        }

        table tbody tr:hover {
            background-color: #2d3e50;
        }

        table tbody tr:hover td {
            color: #ffffff;
        }

        .location-cell {
            color: #74b9ff;
            font-weight: 700;
        }

        .mineral-cell {
            color: #fdcb6e;
            text-align: right;
        }

        .na-cell {
            color: #636e72;
            font-style: italic;
            text-align: center;
        }

        /* Cycles display */
        .cycles-box {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
            text-align: center;
            background: rgba(39, 174, 96, 0.15);
            border: 1px solid #27ae60;
            color: #2ecc71;
        }

        .cycles-box.zero {
            background: rgba(231, 76, 60, 0.1);
            border-color: #e74c3c;
            color: #e74c3c;
        }

        /* Volume savings */
        .volume-savings {
            margin-top: 8px;
            padding: 8px 10px;
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid #3498db;
            border-radius: 4px;
            font-size: 12px;
        }

        .volume-savings strong {
            color: #74b9ff;
        }

        .volume-savings i {
            color: #fdcb6e;
            margin-right: 5px;
        }

        /* Mineral stats bars */
        .mineral-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .mineral-card {
            background: #0f1419;
            border: 1px solid #2a3f54;
            border-radius: 8px;
            padding: 20px;
        }

        .mineral-card h3 {
            margin: 0 0 15px 0;
            color: #74b9ff;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mineral-card h3 i {
            color: #fdcb6e;
        }

        .mineral-card .total-line {
            color: #b0b8c4;
            font-size: 13px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #2a3f54;
        }

        .mineral-card .total-line strong {
            color: #ffffff;
            font-size: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #1a2332;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #b0b8c4;
            font-size: 13px;
        }

        .stat-bar-container {
            flex: 1;
            margin: 0 15px;
            height: 8px;
            background: #1a2332;
            border-radius: 4px;
            overflow: hidden;
        }

        .stat-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
            background: linear-gradient(90deg, #3498db, #74b9ff);
        }

        .stat-values {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 100px;
            justify-content: flex-end;
        }

        .stat-count {
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
        }

        .stat-percent {
            color: #fdcb6e;
            font-weight: 600;
            font-size: 13px;
            background: rgba(253, 203, 110, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* Footer */
        .footer-bar {
            background: linear-gradient(135deg, #1a2332 0%, #2d3e50 100%);
            color: #b0b8c4;
            padding: 15px 25px;
            border-radius: 8px;
            text-align: right;
            font-size: 13px;
            border: 1px solid #2a3f54;
        }

        .footer-bar i {
            color: #3498db;
            margin-right: 5px;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        h4 {
            color: #74b9ff;
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        h4 i {
            color: #e17055;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .empty-msg {
            color: #636e72;
            font-style: italic;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>

<div class="header-info">
    <h1><i class="fas fa-cube"></i> <?php echo htmlspecialchars($selected_name); ?> - Production Dashboard</h1>
    <div class="description">
        This dashboard checks whether your pilot can manufacture ammo or ships to clear inventory.
        Select a pilot from the dropdown to view their mineral assets and production capacity per location.
    </div>
    <div class="note-box">
        <i class="fas fa-info-circle"></i>
        <strong>Purpose:</strong> Verify a simple and useful way to use the seven basic minerals.
        The breakdown by location helps identify where manufacturing is most efficient.
    </div>
    <div class="pilot-selector">
        <label for="pilot-select"><i class="fas fa-user-astronaut"></i> Select Pilot:</label>
        <select id="pilot-select" onchange="changePilot(this.value)">
            <?php foreach ($pilots as $pilot): ?>
                <option value="<?php echo $pilot['toon_number']; ?>" 
                    <?php echo ($pilot['toon_number'] == $selected_toon) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pilot['toon_name']); ?> (<?php echo $pilot['toon_number']; ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn-go" onclick="changePilot(document.getElementById('pilot-select').value)">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    <p style="margin-top: 10px; color: #636e72; font-size: 12px;">
        <i class="fas fa-clock"></i> Last updated: <?php echo date('Y-m-d H:i:s'); ?> | 
        <i class="fas fa-satellite"></i> Status: Connected
    </p>
</div>

<?php if (empty($minerales_agrupados)): ?>
<div class="container">
    <div class="empty-msg">
        <i class="fas fa-box-open" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
        No mineral assets found for this pilot.
    </div>
</div>
<?php else: ?>

<!-- MINERAL INVENTORY -->
<div class="container">
    <h2 class="section-title"><i class="fas fa-gem"></i> Mineral Inventory</h2>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Active Locations</div>
            <div class="stat-value"><?php echo $active_locations; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total ISK Value</div>
            <div class="stat-value isk"><?php echo number_format($total_valor_minerales, 2, '.', ','); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Unique Minerals</div>
            <div class="stat-value"><?php echo count($minerales); ?></div>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th width="25%"><i class="fas fa-map-marker-alt"></i> Location</th>
                    <th>Tritanium</th>
                    <th>Pyerite</th>
                    <th>Isogen</th>
                    <th>Megacyte</th>
                    <th>Nocxium</th>
                    <th>Zydrine</th>
                    <th>Mexallon</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($minerales_agrupados as $ubicacion => $minerales_data): ?>
                <tr>
                    <td class="location-cell"><?php echo htmlspecialchars($ubicacion); ?></td>
                    <?php foreach ($minerales as $mineral): ?>
                        <?php 
                        $cant = $minerales_data[$mineral]['quantity'] ?? 0;
                        if ($cant < 10): ?>
                            <td class="na-cell">N/A</td>
                        <?php else: ?>
                            <td class="mineral-cell"><?php echo number_format($cant, 0, '.', ','); ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MINERAL DISTRIBUTION STATS -->
<div class="container">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> Mineral Distribution</h2>
    <div class="mineral-stats">
        <div class="mineral-card">
            <h3><i class="fas fa-cubes"></i> Minerals by Quantity</h3>
            <div class="total-line">Total across all locations: <strong><?php echo number_format(array_sum($mineral_totals)); ?></strong> units</div>
            <?php foreach ($minerales as $mineral): 
                $count = $mineral_totals[$mineral];
                $percent = $max_mineral > 0 ? round(($count / $max_mineral) * 100, 1) : 0;
            ?>
            <div class="stat-row">
                <span class="stat-label"><?php echo $mineral; ?></span>
                <div class="stat-bar-container">
                    <div class="stat-bar" style="width: <?php echo $percent; ?>%"></div>
                </div>
                <div class="stat-values">
                    <span class="stat-count"><?php echo number_format($count); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- SHIP & AMMO MANUFACTURING -->
<div class="container">
    <h2 class="section-title"><i class="fas fa-rocket"></i> Ship & Ammo Manufacturing</h2>

    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Ship Types</div>
            <div class="stat-value"><?php echo count($recetas_naves); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Ammo Types</div>
            <div class="stat-value"><?php echo count($receta_ammo); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Locations with Minerals</div>
            <div class="stat-value"><?php echo $active_locations; ?></div>
        </div>
    </div>

    <h4><i class="fas fa-cogs"></i> Production Capacity by Location</h4>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th width="18%"><i class="fas fa-map-marker-alt"></i> Location</th>
                    <th>Antimatter Charge S</th>
                    <th>Porpoise</th>
                    <th>Hurricane</th>
                    <th>Caracal</th>
                    <th>Corax</th>
                    <th>Condor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($minerales_agrupados as $ubicacion => $minerales_data): ?>
                <tr>
                    <td class="location-cell"><?php echo htmlspecialchars($ubicacion); ?></td>

                    <!-- Antimatter Charge S -->
                    <?php 
                    $ciclos_s = calcularCiclos($minerales_data, $receta_ammo['Antimatter Charge S']);
                    $ciclos_s_num = (float)str_replace(',', '', $ciclos_s);
                    $ahorro_s = calcularAhorroVolumen($receta_ammo['Antimatter Charge S'], $ciclos_s_num);
                    ?>
                    <td class="text-center">
                        <div class="cycles-box <?php echo ($ciclos_s == 0 ? 'zero' : ''); ?>">
                            <?php echo $ciclos_s == 0 ? 'n/a' : $ciclos_s . ' cycles'; ?>
                        </div>
                        <?php if ($ahorro_s['ahorro_m3'] > 0): ?>
                        <div class="volume-savings">
                            <i class="fas fa-compress-arrows-alt"></i>
                            <strong>Save: <?php echo number_format($ahorro_s['ahorro_m3'], 2); ?> m³</strong>
                            (<?php echo number_format($ahorro_s['porcentaje'], 1); ?>% less volume)
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- Ships -->
                    <?php foreach ($recetas_naves as $nombre_nave => $receta_nave): ?>
                        <?php $ciclos = calcularCiclos($minerales_data, $receta_nave); ?>
                        <td class="text-center">
                            <div class="cycles-box <?php echo ($ciclos == 0 ? 'zero' : ''); ?>">
                                <?php echo $ciclos == 0 ? 'n/a' : $ciclos; ?>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h4><i class="fas fa-table"></i> Detailed Capacity Matrix (with decimals)</h4>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th width="18%"><i class="fas fa-map-marker-alt"></i> Location</th>
                    <th>Antimatter Charge S</th>
                    <th>Porpoise</th>
                    <th>Hurricane</th>
                    <th>Caracal</th>
                    <th>Corax</th>
                    <th>Condor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($minerales_agrupados as $ubicacion => $minerales_data): ?>
                <tr>
                    <td class="location-cell"><?php echo htmlspecialchars($ubicacion); ?></td>
                    <td class="text-end mineral-cell"><?php echo calcularCiclos($minerales_data, $receta_ammo['Antimatter Charge S'], true); ?></td>
                    <?php foreach ($recetas_naves as $nombre_nave => $receta_nave): ?>
                        <td class="text-end mineral-cell"><?php echo calcularCiclos($minerales_data, $receta_nave, true); ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<div class="footer-bar">
    <i class="fas fa-shield-alt"></i> Dashboard Secured | 
    EVE Online Asset Tracker v3.0 | 
    <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?> | 
    License: GPL 3.0
</div>

<script>
function changePilot(toonNumber) {
    window.location.href = window.location.pathname + '?toon_number=' + toonNumber;
}
</script>

</body>
</html>
