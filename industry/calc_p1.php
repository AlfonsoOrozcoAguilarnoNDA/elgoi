<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Mosaic Dashboard
 * Date: 2026-03-31 00:22
 * 
 * This checks if the pilot has enough P1 to do some things. For now, it uses my pilots; change to use yours.
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();

// Set Mexico timezone
date_default_timezone_set('America/Mexico_City');
$timestamp_calculo = date('Y-m-d H:i:s');

// ============================================================================
// CONFIGURATION
// ============================================================================

// Character string (editable) - names as they appear in PILOTS.toon_name
$characters_string = "Hypervisor,Khadima,Sue Rtuda";

// P1 items to search (editable) - exact names from EVE_ASSETS.type_description
$items_string = "Biofuels,Water,Toxic Metals,Reactive Metals,Plasmoids,Bacteria,Electrolytes,Precious Metals,Chiral Structures,Silicon,Oxygen,Industrial Fibers,Oxidizing Compound,Biomass";

// Pastel colors per pilot (configurable) - RGB format
$pilot_colors = [
    1 => '#FFE5E5', // Pastel pink
    2 => '#E5F5FF', // Pastel blue
    3 => '#E5FFE5', // Pastel green
    4 => '#FFF5E5'  // Pastel orange
];

// Constants
define('M3_POR_UNIDAD', 0.19);
define('M3_POR_PLANETA', 92000);
define('M3_PARA_DST', 62500);

// Surplus rules
$reglas_sobrantes = [
    [
        'nombre' => 'Toxic Metals vs (Precious + Chiral)',
        'sobrante' => 'Toxic Metals',
        'comparar_con' => ['Precious Metals', 'Chiral Structures']
    ],
    [
        'nombre' => 'Water vs (Electrolytes + Plasmoids)',
        'sobrante' => 'Water',
        'comparar_con' => ['Electrolytes', 'Plasmoids']
    ],
    [
        'nombre' => 'Bacteria vs Reactive Metals',
        'sobrante' => 'Bacteria',
        'comparar_con' => ['Reactive Metals']
    ],
    [
        'nombre' => 'Biofuels vs Precious Metals',
        'sobrante' => 'Biofuels',
        'comparar_con' => ['Precious Metals']
    ]
];

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function aValues319($Qx){
    global $link;    
    $rsX = mysqli_query($link,$Qx);
    $Qx2 = strtolower($Qx);
    if (substr($Qx2, 0, 6) != 'select') return "";    
    $aDataX = array();
    $rows = mysqli_num_rows($rsX);
    if ($rows == 0) return array("",""); 

    $Campos = mysqli_num_fields($rsX);
    while ($regX = mysqli_fetch_array($rsX)) {
        for($iX=0; $iX<$Campos; $iX++){
            $finfo = mysqli_fetch_field_direct($rsX,$iX);
            $name = $finfo->name;
            $aDataX[] = $regX[$name];
        }
    }
    return $aDataX;
}

/**
 * Gets P1 data for the specified characters.
 * Uses EVE_ASSETS.type_description directly (no invTypes join).
 * Uses EVE_ASSETS.toon_number joined with PILOTS.toon_number to get names.
 */
function obtenerDatosP1($characters_string, $items_string) {
    global $link;

    // Process strings
    $characters = array_map('trim', explode(',', $characters_string));
    $items = array_map('trim', explode(',', $items_string));

    // Create character name list for query (filtering via PILOTS.toon_name)
    $char_list = "'" . implode("','", array_map(function($c) use ($link) {
        return mysqli_real_escape_string($link, $c);
    }, $characters)) . "'";

    // Create item list for IN() - using type_description directly
    $item_list = "'" . implode("','", array_map(function($i) use ($link) {
        return mysqli_real_escape_string($link, $i);
    }, $items)) . "'";

    // Main query: JOIN EVE_ASSETS with PILOTS to get toon_name
    // Filter by PILOTS.toon_name and EVE_ASSETS.type_description
    $query = "
        SELECT 
            p.toon_number,
            p.toon_name,
            ea.type_id,
            ea.type_description as item_name,
            SUM(ea.quantity) as total_quantity
        FROM EVE_ASSETS ea
        INNER JOIN PILOTS p ON ea.toon_number = p.toon_number
        WHERE p.toon_name IN ($char_list)
        AND ea.type_description IN ($item_list)
        GROUP BY p.toon_number, p.toon_name, ea.type_id, ea.type_description
        ORDER BY ea.type_id, p.toon_name
    ";

    $result = mysqli_query($link, $query);

    if (!$result) {
        die("Query error: " . mysqli_error($link));
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }

    mysqli_free_result($result);

    // Organize data by type_id and character
    $organized = [];
    $all_chars = [];

    foreach ($data as $row) {
        $type_id = $row['type_id'];
        $char_name = $row['toon_name'];

        if (!isset($organized[$type_id])) {
            $organized[$type_id] = [
                'item_name' => $row['item_name'],
                'type_id' => $type_id,
                'characters' => []
            ];
        }

        $organized[$type_id]['characters'][$char_name] = $row['total_quantity'];
        $all_chars[$char_name] = true;
    }

    return [
        'data' => $organized,
        'characters' => array_keys($all_chars),
        'requested_order' => $characters
    ];
}

/**
 * Builds a string of which pilots actually have assets in the results.
 */
function construirCadenaPilotosDetectados($p1_data) {
    $detected = [];
    foreach ($p1_data['requested_order'] as $char) {
        // Check if this pilot appears in any item data
        foreach ($p1_data['data'] as $item_data) {
            if (isset($item_data['characters'][$char]) && $item_data['characters'][$char] > 0) {
                $detected[] = $char;
                break;
            }
        }
    }
    return implode(', ', $detected);
}

/**
 * Calculates surplus per pilot according to rules
 */
function calcularSobrantes($p1_data, $reglas_sobrantes) {
    $characters = $p1_data['requested_order'];
    $data = $p1_data['data'];

    // Prepare quantity structure per pilot
    $cantidades_por_piloto = [];
    foreach ($characters as $char) {
        $cantidades_por_piloto[$char] = [];
    }

    // Fill quantities
    foreach ($data as $item_data) {
        $item_name = $item_data['item_name'];
        foreach ($characters as $char) {
            $qty = isset($item_data['characters'][$char]) ? $item_data['characters'][$char] : 0;
            $cantidades_por_piloto[$char][$item_name] = $qty;
        }
    }

    // Calculate surplus per pilot
    $sobrantes_por_piloto = [];

    foreach ($characters as $char) {
        $sobrantes_por_piloto[$char] = [];
        $total_sobrante = 0;

        foreach ($reglas_sobrantes as $regla) {
            $item_sobrante = $regla['sobrante'];
            $items_comparar = $regla['comparar_con'];

            $cantidad_sobrante = isset($cantidades_por_piloto[$char][$item_sobrante]) 
                ? $cantidades_por_piloto[$char][$item_sobrante] 
                : 0;

            $suma_comparar = 0;
            foreach ($items_comparar as $item_comp) {
                $suma_comparar += isset($cantidades_por_piloto[$char][$item_comp]) 
                    ? $cantidades_por_piloto[$char][$item_comp] 
                    : 0;
            }

            if ($cantidad_sobrante > $suma_comparar) {
                $sobrante = $cantidad_sobrante - $suma_comparar;
                $sobrantes_por_piloto[$char][$item_sobrante] = $sobrante;
                $total_sobrante += $sobrante;
            }
        }

        // Calculate m3 and DST
        $m3_sobrante = $total_sobrante * M3_POR_UNIDAD;
        $dst = $m3_sobrante / M3_PARA_DST;

        $sobrantes_por_piloto[$char]['_total_unidades'] = $total_sobrante;
        $sobrantes_por_piloto[$char]['_m3'] = $m3_sobrante;
        $sobrantes_por_piloto[$char]['_dst'] = $dst;
    }

    return $sobrantes_por_piloto;
}

/**
 * Renders the P1 table
 */
function renderizarTablaP1($p1_data, $pilot_colors) {
    $data = $p1_data['data'];
    $characters = $p1_data['requested_order'];

    if (count($data) == 0) {
        return '<tr><td colspan="' . (count($characters) + 3) . '" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> No P1 items found for the specified characters
                </td></tr>';
    }

    $html = '';
    $counter = 1;

    // Initialize column totals
    $totales_columna = [];
    foreach ($characters as $char) {
        $totales_columna[$char] = 0;
    }

    foreach ($data as $type_id => $item_data) {
        $total_fila = 0;

        $html .= "<tr>";
        $html .= "<td class='text-center'><strong>{$counter}</strong></td>";
        $html .= "<td><strong>{$item_data['item_name']}</strong><br><small class='text-muted'>Type ID: {$type_id}</small></td>";

        $pilot_index = 1;
        foreach ($characters as $char_name) {
            $quantity = isset($item_data['characters'][$char_name]) 
                ? $item_data['characters'][$char_name]
                : 0;

            $quantity_formatted = $quantity > 0 ? number_format($quantity) : '-';
            $bg_color = isset($pilot_colors[$pilot_index]) ? $pilot_colors[$pilot_index] : '#F5F5F5';

            $html .= "<td style='background-color: {$bg_color};' class='text-right'><strong>{$quantity_formatted}</strong></td>";

            $totales_columna[$char_name] += $quantity;
            $total_fila += $quantity;
            $pilot_index++;
        }

        // Row total
        $html .= "<td class='text-right' style='background-color: #FFF9C4; font-weight: bold;'>" . number_format($total_fila) . "</td>";
        $html .= "</tr>";
        $counter++;
    }

    // Totals row
    $html .= "<tr style='background-color: #E0E0E0; font-weight: bold;'>";
    $html .= "<td colspan='2' class='text-right'>TOTAL UNITS:</td>";

    $total_general = 0;
    foreach ($characters as $char_name) {
        $total_col = $totales_columna[$char_name];
        $total_general += $total_col;
        $html .= "<td class='text-right'>" . number_format($total_col) . "</td>";
    }
    $html .= "<td class='text-right'>" . number_format($total_general) . "</td>";
    $html .= "</tr>";

    // m3 row
    $html .= "<tr style='background-color: #EEEEEE; font-weight: bold;'>";
    $html .= "<td colspan='2' class='text-right'>TOTAL m&sup3;:</td>";

    $total_m3_general = 0;
    foreach ($characters as $char_name) {
        $m3 = $totales_columna[$char_name] * M3_POR_UNIDAD;
        $total_m3_general += $m3;
        $html .= "<td class='text-right'>" . number_format($m3, 2) . "</td>";
    }
    $html .= "<td class='text-right'>" . number_format($total_m3_general, 2) . "</td>";
    $html .= "</tr>";

    // Planets row
    $html .= "<tr style='background-color: #E1F5FE; font-weight: bold;'>";
    $html .= "<td colspan='2' class='text-right'>PLANETS NEEDED:</td>";

    $total_planetas_general = 0;
    foreach ($characters as $char_name) {
        $m3 = $totales_columna[$char_name] * M3_POR_UNIDAD;
        $planetas = $m3 / M3_POR_PLANETA;
        $total_planetas_general += $planetas;
        $html .= "<td class='text-right'>" . number_format($planetas, 2) . "</td>";
    }
    $html .= "<td class='text-right'>" . number_format($total_planetas_general, 2) . "</td>";
    $html .= "</tr>";

    return $html;
}

/**
 * Renders the surplus table
 */
function renderizarTablaSobrantes($sobrantes_por_piloto, $characters, $pilot_colors) {
    $html = '';

    $pilot_index = 1;
    foreach ($characters as $char) {
        $sobrantes = $sobrantes_por_piloto[$char];
        $bg_color = isset($pilot_colors[$pilot_index]) ? $pilot_colors[$pilot_index] : '#F5F5F5';

        $html .= "<tr>";
        $html .= "<td style='background-color: {$bg_color};'><strong>{$char}</strong></td>";

        $items_sobrantes = [];
        foreach ($sobrantes as $item => $cantidad) {
            if (substr($item, 0, 1) != '_') {
                $items_sobrantes[] = "{$item}: " . number_format($cantidad);
            }
        }

        $items_texto = count($items_sobrantes) > 0 ? implode('<br>', $items_sobrantes) : '-';
        $html .= "<td>{$items_texto}</td>";

        $total_unidades = isset($sobrantes['_total_unidades']) ? $sobrantes['_total_unidades'] : 0;
        $html .= "<td class='text-right'><strong>" . number_format($total_unidades) . "</strong></td>";

        $m3 = isset($sobrantes['_m3']) ? $sobrantes['_m3'] : 0;
        $html .= "<td class='text-right'>" . number_format($m3, 2) . "</td>";

        $dst = isset($sobrantes['_dst']) ? $sobrantes['_dst'] : 0;
        $html .= "<td class='text-right'><strong>" . number_format($dst, 3) . "</strong></td>";

        $html .= "</tr>";
        $pilot_index++;
    }

    // General totals
    $total_unidades_general = 0;
    $total_m3_general = 0;
    $total_dst_general = 0;

    foreach ($sobrantes_por_piloto as $sobrantes) {
        $total_unidades_general += isset($sobrantes['_total_unidades']) ? $sobrantes['_total_unidades'] : 0;
        $total_m3_general += isset($sobrantes['_m3']) ? $sobrantes['_m3'] : 0;
        $total_dst_general += isset($sobrantes['_dst']) ? $sobrantes['_dst'] : 0;
    }

    $html .= "<tr style='background-color: #E0E0E0; font-weight: bold;'>";
    $html .= "<td colspan='2' class='text-right'>GRAND TOTAL:</td>";
    $html .= "<td class='text-right'>" . number_format($total_unidades_general) . "</td>";
    $html .= "<td class='text-right'>" . number_format($total_m3_general, 2) . "</td>";
    $html .= "<td class='text-right'>" . number_format($total_dst_general, 3) . "</td>";
    $html .= "</tr>";

    return $html;
}

// ============================================================================
// PROCESSING
// ============================================================================

$p1_data = obtenerDatosP1($characters_string, $items_string);
$sobrantes_por_piloto = calcularSobrantes($p1_data, $reglas_sobrantes);
$cadena_pilotos_detectados = construirCadenaPilotosDetectados($p1_data);

// ============================================================================
// INTERFACE
// ============================================================================

echo ui_header("P1 Inventory Grid");
//echo ui_generate_navbar();
echo crew_navbar(); echo "<br /><br />";
?>

<style>
.section-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    margin-top: 20px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.config-box {
    background-color: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.config-label {
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}

.pilot-header {
    font-weight: bold;
    text-align: center;
    vertical-align: middle !important;
    background-color: #343a40 !important;
    color: white !important;
}

.timestamp-box {
    background-color: #fff3cd;
    border: 1px solid #ffc107;
    padding: 10px;
    margin-bottom: 20px;
    border-radius: 4px;
    text-align: center;
    font-weight: bold;
}

.reglas-box {
    background-color: #e7f3ff;
    border-left: 4px solid #2196F3;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.detected-box {
    background-color: #e8f5e9;
    border-left: 4px solid #4caf50;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}
</style>

<div class="container-fluid mt-4">

    <!-- TIMESTAMP -->
    <div class="timestamp-box">
        <i class="fas fa-clock"></i> Calculated at: <strong><?php echo $timestamp_calculo; ?></strong> (Mexico Time)
    </div>

    <!-- VISIBLE CONFIGURATION -->
    <div class="config-box">
        <div class="row">
            <div class="col-md-6">
                <div class="config-label"><i class="fas fa-users"></i> Configured Characters:</div>
                <code><?php echo htmlspecialchars($characters_string); ?></code>
            </div>
            <div class="col-md-6">
                <div class="config-label"><i class="fas fa-cubes"></i> P1 Items Searched:</div>
                <code><?php echo htmlspecialchars($items_string); ?></code>
            </div>
        </div>
    </div>

    <!-- DETECTED PILOTS -->
    <div class="detected-box">
        <div class="config-label"><i class="fas fa-check-circle"></i> Detected Pilots with Assets:</div>
        <code><?php echo htmlspecialchars($cadena_pilotos_detectados); ?></code>
        <?php if (empty($cadena_pilotos_detectados)): ?>
            <span class="text-muted">(None detected)</span>
        <?php endif; ?>
    </div>

    <!-- P1 INVENTORY TABLE -->
    <h3 class="section-title">
        <i class="fas fa-warehouse"></i> P1 Inventory Grid
        <span class="badge badge-light ml-2"><?php echo count($p1_data['data']); ?> items</span>
    </h3>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th class="text-center" style="width: 60px;"><i class="fas fa-hashtag"></i></th>
                            <th><i class="fas fa-cube"></i> Item Name / Type ID</th>
                            <?php 
                            $pilot_index = 1;
                            foreach ($p1_data['requested_order'] as $char_name): 
                            ?>
                                <th class="pilot-header">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($char_name); ?>
                                </th>
                            <?php 
                                $pilot_index++;
                            endforeach; 
                            ?>
                            <th class="text-center" style="background-color: #343a40; color: white;">
                                <i class="fas fa-calculator"></i> TOTAL
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderizarTablaP1($p1_data, $pilot_colors); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- SURPLUS RULES -->
    <div class="reglas-box">
        <h5><i class="fas fa-balance-scale"></i> Applied Balance Rules:</h5>
        <ul class="mb-0">
            <?php foreach ($reglas_sobrantes as $regla): ?>
                <li><strong><?php echo $regla['nombre']; ?>:</strong> 
                    <?php echo $regla['sobrante']; ?> must not exceed 
                    <?php echo implode(' + ', $regla['comparar_con']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- SURPLUS TABLE -->
    <h3 class="section-title">
        <i class="fas fa-exclamation-triangle"></i> Surplus Analysis
    </h3>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="background-color: #343a40; color: white;"><i class="fas fa-user"></i> Pilot</th>
                            <th style="background-color: #343a40; color: white;"><i class="fas fa-list"></i> Surplus Items</th>
                            <th class="text-center" style="background-color: #343a40; color: white;"><i class="fas fa-boxes"></i> Total Units</th>
                            <th class="text-center" style="background-color: #343a40; color: white;"><i class="fas fa-cube"></i> Total m&sup3;</th>
                            <th class="text-center" style="background-color: #343a40; color: white;"><i class="fas fa-shipping-fast"></i> DST</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderizarTablaSobrantes($sobrantes_por_piloto, $p1_data['requested_order'], $pilot_colors); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LEGEND -->
    <div class="card">
        <div class="card-body">
            <h5><i class="fas fa-info-circle"></i> References</h5>
            <div class="row">
                <div class="col-md-3">
                    <strong>m&sup3; per unit:</strong> <?php echo M3_POR_UNIDAD; ?>
                </div>
                <div class="col-md-3">
                    <strong>m&sup3; per planet:</strong> <?php echo number_format(M3_POR_PLANETA); ?>
                </div>
                <div class="col-md-3">
                    <strong>m&sup3; for DST:</strong> <?php echo number_format(M3_PARA_DST); ?>
                </div>
                <div class="col-md-3">
                    <strong>DST:</strong> Deep Space Transport
                </div>
            </div>
            <hr>
            <div class="row">
                <?php 
                $pilot_index = 1;
                foreach ($p1_data['requested_order'] as $char_name): 
                    $bg_color = isset($pilot_colors[$pilot_index]) ? $pilot_colors[$pilot_index] : '#F5F5F5';
                ?>
                    <div class="col-md-3 mb-2">
                        <div style="background-color: <?php echo $bg_color; ?>; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            <strong>Pilot <?php echo $pilot_index; ?>:</strong> <?php echo htmlspecialchars($char_name); ?>
                        </div>
                    </div>
                <?php 
                    $pilot_index++;
                endforeach; 
                ?>
            </div>
        </div>
    </div>

</div>

<?php
echo ui_footer();
?>
