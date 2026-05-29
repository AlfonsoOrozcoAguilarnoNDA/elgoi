<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Mosaic Dashboard
 * Fecha: 2026-03-31 00:22
 * 
 * This is amn experiment to check  if i can with MY pilot, create ammo or ships to clear m,y inventory, Change the character name.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once 'config.php';
include_once 'ui_functions.php';

// Aplicar seguridad
check_authorization();

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Configuración del personaje
$toon_number = 2119379715;
$character_name = "Abyssal Firestorm";

// Colores del tema
$color = "#00ff41";
$color2 = "#00ff41";

// Recetas de municiones
$recetas_minerales = [
    'Antimatter Charge M' => ['Tritanium' => 40006, 'Pyerite' => 37155, 'Mexallon' => 624],
    'Antimatter Charge S' => ['Tritanium' => 16360, 'Pyerite' => 1530, 'Nocxium' => 100]
];

// Volúmenes de municiones (m³ por unidad)
$volumenes_municiones = [
    'Antimatter Charge M' => 0.0125,
    'Antimatter Charge S' => 0.0125
];

// Recetas de fuel blocks
$receta_fuel = [
    'Fuel Blocks' => ['Heavy Water' => 17000, 'Liquid Ozone' => 35000, 'Strontium Clathrates' => 2000]
];

// Recetas de naves
$recetas_naves = [
    'Porpoise' => ['Tritanium' => 3240000, 'Pyerite' => 607500, 'Mexallon' => 151875, 'Isogen' => 81000, 'Nocxium' => 30375, 'Zydrine' => 5063, 'Megacyte' => 2835],
    'Hurricane' => ['Tritanium' => 2520000, 'Pyerite' => 900000, 'Mexallon' => 162000, 'Isogen' => 18000, 'Nocxium' => 7200, 'Zydrine' => 1800, 'Megacyte' => 360],
    'Caracal' => ['Tritanium' => 486000, 'Pyerite' => 162000, 'Mexallon' => 32400, 'Isogen' => 9000, 'Nocxium' => 1350, 'Zydrine' => 315, 'Megacyte' => 126],
    'Corax' => ['Tritanium' => 72000, 'Pyerite' => 13500, 'Mexallon' => 4500, 'Isogen' => 900],
    'Condor' => ['Tritanium' => 28800, 'Pyerite' => 5400, 'Mexallon' => 2250, 'Isogen' => 450]
];

// Volúmenes de minerales (m³ por unidad)
$volumenes_minerales = [
    'Tritanium' => 0.01,
    'Pyerite' => 0.01,
    'Mexallon' => 0.01,
    'Isogen' => 0.01,
    'Nocxium' => 0.01,
    'Zydrine' => 0.01,
    'Megacyte' => 0.01
];

$minerales = ['Tritanium', 'Pyerite', 'Isogen', 'Megacyte', 'Nocxium', 'Zydrine', 'Mexallon'];
$bloques = ['Heavy Water', 'Liquid Ozone', 'Strontium Clathrates'];

/**
 * Obtiene assets de la base de datos
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
 * Agrupa assets por ubicación y suma duplicados
 */
function agruparPorUbicacion($assets) {
    $agrupado = [];
    foreach ($assets as $asset) {
        $loc = $asset['description'] ?: "Contenedor " . $asset['location_id'];
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
 * Calcula ciclos de producción posibles
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
 * Calcula volumen de minerales necesarios para una receta
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
 * Calcula el ahorro de volumen al convertir minerales a municiones
 */
function calcularAhorroVolumen($municion, $receta, $ciclos_numericos) {
    global $volumenes_municiones;
    
    if ($ciclos_numericos <= 0) {
        return ['ahorro_m3' => 0, 'porcentaje' => 0];
    }
    
    // Volumen de minerales necesarios
    $volumen_minerales = calcularVolumenMinerales($receta, $ciclos_numericos);
    
    // Volumen de munición producida (cada ciclo produce 100 unidades)
    $unidades_producidas = $ciclos_numericos * 100;
    $volumen_municion = $unidades_producidas * $volumenes_municiones[$municion];
    
    // Ahorro
    $ahorro = $volumen_minerales - $volumen_municion;
    $porcentaje = $volumen_minerales > 0 ? ($ahorro / $volumen_minerales) * 100 : 0;
    
    return [
        'volumen_minerales' => $volumen_minerales,
        'volumen_municion' => $volumen_municion,
        'ahorro_m3' => $ahorro,
        'porcentaje' => $porcentaje
    ];
}

// Obtener datos
$assets_minerales = obtenerAssets($toon_number, $minerales);
$assets_bloques = obtenerAssets($toon_number, $bloques);

$minerales_agrupados = agruparPorUbicacion($assets_minerales);
$bloques_agrupados = agruparPorUbicacion($assets_bloques);

// Calcular totales
$total_valor_minerales = 0;
$total_valor_bloques = 0;

foreach ($assets_minerales as $asset) {
    $total_valor_minerales += $asset['forge_value'];
}
foreach ($assets_bloques as $asset) {
    $total_valor_bloques += $asset['forge_value'];
}

// Mostrar interfaz
echo ui_header($character_name . " - Production Dashboard");
echo crew_navbar(); echo "<br />";
?>

<style>
.eve-container {
    border: 2px solid <?php echo $color; ?>;
    border-radius: 8px;
    box-shadow: 0 0 20px rgba(0, 255, 65, 0.3);
    padding: 20px;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
}
.eve-header {
    border: 1px solid <?php echo $color; ?>;
    padding: 15px;
    margin-bottom: 20px;
    text-align: center;
    box-shadow: 0 0 15px rgba(0, 255, 65, 0.2);
    border-radius: 8px;
    background: rgba(0, 255, 65, 0.05);
}
.eve-header h2 {
    color: <?php echo $color; ?>;
    text-shadow: 0 0 10px <?php echo $color; ?>;
    margin: 0;
}
.eve-header p {
    color: #888;
    margin: 5px 0 0 0;
}
.eve-section {
    margin-bottom: 30px;
    border-left: 3px solid <?php echo $color; ?>;
    padding: 15px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 4px;
}
.eve-section h3 {
    color: <?php echo $color2; ?>;
    font-size: 1.5em;
    margin-bottom: 15px;
    text-shadow: 0 0 8px <?php echo $color2; ?>;
}
.eve-section h4 {
    color: <?php echo $color2; ?>;
    margin-top: 20px;
    font-size: 1.2em;
}
.eve-location {
    color: <?php echo $color; ?>;
    font-weight: bold;
}
.eve-mineral {
    color: #ffaa00;
}
.eve-na {
    color: #888;
    font-style: italic;
}
.eve-stats {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.eve-stat-box {
    border: 1px solid <?php echo $color2; ?>;
    padding: 12px 15px;
    border-radius: 4px;
    flex: 1;
    min-width: 200px;
    background: rgba(0, 255, 65, 0.05);
}
.eve-stat-label {
    color: #888;
    font-size: 0.85em;
    text-transform: uppercase;
}
.eve-stat-value {
    color: <?php echo $color; ?>;
    font-size: 1.3em;
    font-weight: bold;
    margin-top: 5px;
    text-shadow: 0 0 5px <?php echo $color; ?>;
}
.eve-cycles {
    border: 1px solid #ffaa00;
    color: #ffaa00;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 0.9em;
    text-align: center;
    font-weight: bold;
    background: rgba(255, 170, 0, 0.1);
}
.eve-cycles.zero {
    border-color: #cc3333;
    color: #cc3333;
    background: rgba(204, 51, 51, 0.1);
}
.table {
    color: #fff;
}
.table thead th {
    background: rgba(0, 255, 65, 0.2);
    color: <?php echo $color; ?>;
    border-color: <?php echo $color; ?>;
}
.table tbody tr:hover {
    background: rgba(0, 255, 65, 0.05);
}
.table td, .table th {
    border-color: rgba(0, 255, 65, 0.2);
}
.volume-savings {
    background: rgba(0, 255, 65, 0.1);
    border: 1px solid <?php echo $color; ?>;
    padding: 10px;
    border-radius: 4px;
    margin-top: 10px;
}
.volume-savings strong {
    color: <?php echo $color; ?>;
}
</style>

<div class="container-fluid mt-4">    
    <div class="eve-container">
        <div class="eve-header">
            <h2><i class="fas fa-cube"></i> <?php echo $character_name; ?> <i class="fas fa-cube"></i></h2>
            <p><i class="fas fa-satellite"></i> Estado: Conectado | Última actualización: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        
        <!-- SECCIÓN MINERALES -->
        <div class="eve-section">
            <h3><i class="fas fa-gem"></i> INVENTARIO DE MINERALES</h3>
            
            <div class="eve-stats">
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Ubicaciones Activas</div>
                    <div class="eve-stat-value"><?php echo count($minerales_agrupados); ?></div>
                </div>
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Valor Total ISK</div>
                    <div class="eve-stat-value"><?php echo number_format($total_valor_minerales, 2, '.', ','); ?></div>
                </div>
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Minerales Únicos</div>
                    <div class="eve-stat-value"><?php echo count($minerales); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="25%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
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
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <?php foreach ($minerales as $mineral): ?>
                                <?php 
                                $cant = $minerales_data[$mineral]['quantity'] ?? 0;
                                if ($cant < 10): ?>
                                    <td><span class="eve-na">N/A</span></td>
                                <?php else: ?>
                                    <td class="eve-mineral"><?php echo number_format($cant, 0, '.', ','); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4><i class="fas fa-cogs"></i> Capacidad de Producción de Municiones</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="30%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
                            <th width="35%">Antimatter Charge M</th>
                            <th width="35%">Antimatter Charge S</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($minerales_agrupados as $ubicacion => $minerales_data): ?>
                            <?php 
                            $ciclos_m = calcularCiclos($minerales_data, $recetas_minerales['Antimatter Charge M']);
                            $ciclos_s = calcularCiclos($minerales_data, $recetas_minerales['Antimatter Charge S']);
                            
                            $ciclos_m_num = (float)str_replace(',', '', $ciclos_m);
                            $ciclos_s_num = (float)str_replace(',', '', $ciclos_s);
                            
                            $ahorro_m = calcularAhorroVolumen('Antimatter Charge M', $recetas_minerales['Antimatter Charge M'], $ciclos_m_num);
                            $ahorro_s = calcularAhorroVolumen('Antimatter Charge S', $recetas_minerales['Antimatter Charge S'], $ciclos_s_num);
                            ?>
                        <tr>
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <td>
                                <div class="eve-cycles <?php echo ($ciclos_m == 0 ? 'zero' : ''); ?>">
                                    <?php echo $ciclos_m == 0 ? 'n/a' : $ciclos_m . ' ciclos'; ?>
                                </div>
                                <?php if ($ahorro_m['ahorro_m3'] > 0): ?>
                                <div class="volume-savings mt-2">
                                    <small>
                                        <i class="fas fa-compress-arrows-alt"></i>
                                        <strong>Ahorro: <?php echo number_format($ahorro_m['ahorro_m3'], 2); ?> m³</strong>
                                        (<?php echo number_format($ahorro_m['porcentaje'], 1); ?>% menos volumen)
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="eve-cycles <?php echo ($ciclos_s == 0 ? 'zero' : ''); ?>">
                                    <?php echo $ciclos_s == 0 ? 'n/a' : $ciclos_s . ' ciclos'; ?>
                                </div>
                                <?php if ($ahorro_s['ahorro_m3'] > 0): ?>
                                <div class="volume-savings mt-2">
                                    <small>
                                        <i class="fas fa-compress-arrows-alt"></i>
                                        <strong>Ahorro: <?php echo number_format($ahorro_s['ahorro_m3'], 2); ?> m³</strong>
                                        (<?php echo number_format($ahorro_s['porcentaje'], 1); ?>% menos volumen)
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIÓN BLOQUES DE HIELO -->
        <div class="eve-section">
            <h3><i class="fas fa-snowflake"></i> INVENTARIO DE BLOQUES DE HIELO</h3>
            
            <div class="eve-stats">
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Ubicaciones Activas</div>
                    <div class="eve-stat-value"><?php echo count($bloques_agrupados); ?></div>
                </div>
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Valor Total ISK</div>
                    <div class="eve-stat-value"><?php echo number_format($total_valor_bloques, 2, '.', ','); ?></div>
                </div>
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Ingredientes Únicos</div>
                    <div class="eve-stat-value"><?php echo count($bloques); ?></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="35%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
                            <th>Heavy Water</th>
                            <th>Liquid Ozone</th>
                            <th>Strontium Clathrates</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bloques_agrupados as $ubicacion => $bloques_data): ?>
                        <tr>
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <?php foreach ($bloques as $bloque): ?>
                                <?php 
                                $cant = $bloques_data[$bloque]['quantity'] ?? 0;
                                if ($cant < 100): ?>
                                    <td><span class="eve-na">N/A</span></td>
                                <?php else: ?>
                                    <td class="eve-mineral"><?php echo number_format($cant, 0, '.', ','); ?></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4><i class="fas fa-cogs"></i> Capacidad de Producción de Fuel Blocks</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="50%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
                            <th width="50%">Fuel Blocks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bloques_agrupados as $ubicacion => $bloques_data): ?>
                            <?php 
                            $ciclos_fuel = calcularCiclos($bloques_data, $receta_fuel['Fuel Blocks']);
                            ?>
                        <tr>
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <td>
                                <div class="eve-cycles <?php echo ($ciclos_fuel == 0 ? 'zero' : ''); ?>">
                                    <?php echo $ciclos_fuel == 0 ? 'n/a' : $ciclos_fuel . ' ciclos'; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIÓN NAVES -->
        <div class="eve-section">
            <h3><i class="fas fa-rocket"></i> MANUFACTURA DE NAVES</h3>
            
            <div class="eve-stats">
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Tipos de Naves</div>
                    <div class="eve-stat-value"><?php echo count($recetas_naves); ?></div>
                </div>
                <div class="eve-stat-box">
                    <div class="eve-stat-label">Ubicaciones con Minerales</div>
                    <div class="eve-stat-value"><?php echo count($minerales_agrupados); ?></div>
                </div>
            </div>

            <h4><i class="fas fa-cogs"></i> Capacidad de Producción por Ubicación</h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="20%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
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
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <?php foreach ($recetas_naves as $nombre_nave => $receta_nave): ?>
                                <?php 
                                $ciclos = calcularCiclos($minerales_data, $receta_nave);
                                ?>
                                <td>
                                    <div class="eve-cycles <?php echo ($ciclos == 0 ? 'zero' : ''); ?>">
                                        <?php echo $ciclos == 0 ? 'n/a' : $ciclos; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4><i class="fas fa-table"></i> Matriz de Capacidad Detallada (con decimales)</h4>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th width="20%"><i class="fas fa-map-marker-alt"></i> Ubicación</th>
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
                            <td class="eve-location"><?php echo htmlspecialchars($ubicacion); ?></td>
                            <?php foreach ($recetas_naves as $nombre_nave => $receta_nave): ?>
                                <?php 
                                $ciclos = calcularCiclos($minerales_data, $receta_nave, true);
                                ?>
                                <td class="text-end eve-mineral"><?php echo $ciclos; ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="eve-header mt-4" style="text-align: right;">
            <p style="margin: 0;">
                <i class="fas fa-shield-alt"></i> Dashboard Secured | 
                EVE Online Asset Tracker v2.0 | 
                <i class="fas fa-clock"></i> <?php echo date('H:i:s'); ?>
            </p>
        </div>
    </div>
</div>

<?php
echo ui_footer();
?>
