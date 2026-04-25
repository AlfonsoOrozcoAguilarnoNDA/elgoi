<?php
/*
License GPL 3.0
Alfons Orozco Aguilar
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

// Aplicar seguridad
check_authorization();

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

// ===============================================
// TAB ACTIVO
// ===============================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'crew';

// ===============================================
// PARÁMETROS TAB CREW
// ===============================================
$view  = isset($_GET['view'])     ? $_GET['view']              : 'hangars';
$orden = isset($_GET['orden'])    ? $_GET['orden']             : ($view == 'assets' ? 'items_desc' : 'naves_desc');

if ($view == 'assets') {
    $minItems = isset($_GET['min_items']) ? (int)$_GET['min_items']   : 2;
    $minJitav = isset($_GET['min_jitav']) ? (float)$_GET['min_jitav'] : 0.10;
    $minNaves = 0;

    $orderBy = "numitems DESC";
    switch ($orden) {
        case 'pocket':       $orderBy = "pocket6 ASC, numitems DESC";  break;
        case 'items_desc':   $orderBy = "numitems DESC";               break;
        case 'items_pocket': $orderBy = "numitems DESC, pocket6 ASC";  break;
        case 'jitav_desc':   $orderBy = "jitav DESC";                  break;
    }

    $queryCrew = "SELECT toon_number, toon_name, numitems, pocket6, jitav, lastsaved
                  FROM PILOTS
                  WHERE numitems > 12
                    AND numitems >= $minItems
                    AND jitav >= $minJitav
                    AND toon_name NOT LIKE '%Catalog%'
                  ORDER BY $orderBy";
} else {
    $minNaves = isset($_GET['min_naves']) ? (int)$_GET['min_naves'] : 2;
    $minItems = 0;
    $minJitav = 0;

    $orderBy = "numships DESC";
    switch ($orden) {
        case 'pocket':      $orderBy = "pocket6 ASC, numships DESC";  break;
        case 'naves_desc':  $orderBy = "numships DESC";               break;
        case 'naves_pocket':$orderBy = "numships DESC, pocket6 ASC";  break;
    }

    $queryCrew = "SELECT toon_number, toon_name, numships, pocket6, skillpoints, unalloc, lastsaved
                  FROM PILOTS
                  WHERE numships > 0
                    AND numships >= $minNaves
                    AND toon_name NOT LIKE '%Catalog%'
                  ORDER BY $orderBy";
}

// ===============================================
// PARÁMETROS TAB MINUS6
// ===============================================
$rangoSP     = isset($_GET['rango_sp']) ? $_GET['rango_sp'] : 'todos';
$pocketFiltro = isset($_GET['pocket'])  ? $_GET['pocket']   : 'todos';
$ordenMinus  = isset($_GET['orden_m'])  ? $_GET['orden_m']  : 'sp_desc';

$whereConditions = [
    "(skillpoints + unalloc) < 6000000",
    "toon_name NOT LIKE '%Catalog%'",
    "toon_name NOT LIKE '%VPS%'"
];

switch ($rangoSP) {
    case 'menos-5': $whereConditions[] = "(skillpoints + unalloc) < 5000000";                                                              break;
    case '0-1':     $whereConditions[] = "(skillpoints + unalloc) < 1000000";                                                              break;
    case '1-2':     $whereConditions[] = "(skillpoints + unalloc) >= 1000000 AND (skillpoints + unalloc) < 2000000";                       break;
    case '2-3':     $whereConditions[] = "(skillpoints + unalloc) >= 2000000 AND (skillpoints + unalloc) < 3000000";                       break;
    case '3-4':     $whereConditions[] = "(skillpoints + unalloc) >= 3000000 AND (skillpoints + unalloc) < 4000000";                       break;
    case '4-5':     $whereConditions[] = "(skillpoints + unalloc) >= 4000000 AND (skillpoints + unalloc) < 5000000";                       break;
    case '5-6':     $whereConditions[] = "(skillpoints + unalloc) >= 5000000 AND (skillpoints + unalloc) < 6000000";                       break;
}

if ($rangoSP == 'todos') {
	$whereConditions = [
		"toon_name NOT LIKE '%Catalog%'",
		"toon_name NOT LIKE '%VPS%'"
	];
}
if ($pocketFiltro != 'todos') {
    $whereConditions[] = "pocket6 = '" . mysqli_real_escape_string($link, $pocketFiltro) . "'";
}

$orderByMinus = "(skillpoints + unalloc) DESC";
switch ($ordenMinus) {
    case 'pocket':  $orderByMinus = "pocket6 ASC, (skillpoints + unalloc) DESC"; break;
    case 'sp_desc': $orderByMinus = "(skillpoints + unalloc) DESC";               break;
}

$whereClause = implode(" AND ", $whereConditions);

$queryMinus = "SELECT toon_number, toon_name, skillpoints, unalloc, pocket6, lastsaved
               FROM PILOTS
               WHERE $whereClause
               ORDER BY $orderByMinus";
//echo("$queryMinus"); //depuracion
//echo $pocketFiltro;
// ===============================================
// FUNCIONES COMPARTIDAS
// ===============================================

/**
 * Devuelve la clase Bootstrap de color según el pocket del piloto.
 */
function getColorByPocket($pocket) {
    $colores = [
        'EXPER' => 'bg-success',
        'LUCKY' => 'bg-info',
        'NOKIA' => 'bg-danger',
        'CLEAN' => 'bg-primary',
        'SANGO' => 'bg-warning',
        'YENN'  => 'bg-light'
    ];
    return isset($colores[$pocket]) ? $colores[$pocket] : 'bg-secondary';
}

/**
 * Calcula la diferencia en minutos entre la hora actual (México) y lastsaved (UTC).
 */
function calcularDiferenciaMinutos($fechaMexico, $fechaUTC) {
    $dtMexico = new DateTime($fechaMexico, new DateTimeZone('America/Mexico_City'));
    $dtMexico->setTimezone(new DateTimeZone('UTC'));
    $dtUTC = new DateTime($fechaUTC, new DateTimeZone('UTC'));
    $diferencia = $dtMexico->getTimestamp() - $dtUTC->getTimestamp();
    return abs($diferencia / 60);
}


// ===============================================
// FUNCIONES TAB CREW
// ===============================================

/**
 * Formatea skill points como "X.X+Y.Y MSP" o "X.X MSP"
 */
function formatMSP($skillpoints, $unalloc) {
    $mainSP   = round($skillpoints / 1000000, 1);
    $unallocSP = round($unalloc / 1000000, 1);
    if ($unallocSP > 0) {
        return $mainSP . '+' . $unallocSP . ' MSP';
    }
    return $mainSP . ' MSP';
}

/**
 * Formatea valor ISK en billones.
 */
function formatISK($jitav) {
    return number_format($jitav, 1) . 'B ISK';
}


// ===============================================
// FUNCIONES TAB MINUS6
// ===============================================

/**
 * Formatea SP total como "X.XX MSP"
 */
function formatSP($skillpoints, $unalloc) {
    $totalSP = ($skillpoints + $unalloc) / 1000000;
    return number_format($totalSP, 2) . ' MSP';
}

/**
 * Días para llegar a 5M usando solo reward diario (10,000 SP/día).
 */
function calcularDiasSoloDiario($totalSP) {
    if ($totalSP >= 5000000) return 0;
    return (5000000 - $totalSP) / 10000;
}

/**
 * Meses para llegar a 5M en modo óptimo (Daily + Mensual, ~525,000 SP/mes).
 */
function calcularMesesOptimo($totalSP) {
    if ($totalSP >= 5000000) return 0;
    return (5000000 - $totalSP) / 525000;
}

/**
 * Porcentaje de progreso hacia 5M SP.
 */
function calcularPorcentaje($totalSP) {
    return min(($totalSP / 5000000) * 100, 100);
}

/**
 * Fecha estimada de llegada a 5M SP.
 */
function calcularFechaEstimada($dias) {
    if ($dias <= 0) return 'READY';
    $fecha = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    $fecha->modify('+' . ceil($dias) . ' days');
    return $fecha->format('d/M/Y');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel EVE Online</title>

    <!-- Bootstrap 4.6.2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        body {
            padding-top: 70px;
            padding-bottom: 60px;
            background: #f2f2f2;
        }

        /* ---- Tabs ---- */
        .nav-tabs-eve {
            background: #fff;
            padding: 10px 20px 0;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 0;
        }

        .nav-tabs-eve .nav-link {
            font-weight: bold;
            color: #495057;
        }

        .nav-tabs-eve .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
        }

        /* ---- Tiles ---- */
        .tile-pilot {
            width: 23%;
            margin: 1%;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            position: relative;
        }

        .tile-pilot.minus6-tile {
            min-height: 380px;
        }

        .tile-pilot:hover {
            transform: scale(1.05);
            text-decoration: none;
            color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .tile-pilot img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.3);
            margin-bottom: 10px;
        }

        .tile-pilot .pilot-name {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }

        .tile-pilot .ship-count,
        .tile-pilot .sp-total {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .tile-pilot .ship-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* ---- Badge ESI ---- */
        .update-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffc107;
            color: #333;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.1); }
        }

        /* ---- Tile estadísticas ---- */
        .tile-stats {
            width: 23%;
            margin: 1%;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            background: #343a40;
            min-height: 380px;
        }

        .tile-stats i         { font-size: 50px; margin-bottom: 10px; }
        .tile-stats .stat-number { font-size: 28px; font-weight: bold; }
        .tile-stats .stat-label  { font-size: 14px; opacity: 0.9; margin-bottom: 15px; }

        /* ---- Barra de progreso Minus6 ---- */
        .progress-bar-container {
            width: 100%;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #5cb85c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }

        .training-info        { width: 100%; text-align: center; font-size: 13px; margin-top: 8px; }
        .training-info .label { opacity: 0.8; font-size: 11px; }
        .training-info .value { font-weight: bold; font-size: 14px; }

        .ready-badge {
            background: #28a745;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }

        /* ---- Misceláneos ---- */
        .update-time {
            text-align: center;
            padding: 10px;
            background: white;
            margin: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-weight: bold;
            color: #333;
        }

        .tiles-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            padding: 20px;
        }

        .tile-pilot.bg-light   { color: #333 !important; }
        .tile-pilot.bg-warning { color: #333 !important; }

        .filter-form {
            padding: 15px;
            background: white;
            border-radius: 5px;
            margin: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .filter-form label  { margin-right: 10px; font-weight: bold; }
        .filter-form select { margin-right: 15px; }

        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 50px;
            background: #343a40;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
    </style>
</head>

<body>

<?php
// Navbar fijo — se pasa el tab activo para que la función pueda destacarlo si lo soporta
crew_navbar(__FILE__, [
    'tab'       => $tab,
    'view'      => $view,
    'orden'     => $orden,
    'minNaves'  => $minNaves,
    'minItems'  => $minItems,
    'minJitav'  => $minJitav,
    'rango_sp'  => $rangoSP,
    'pocket'    => $pocketFiltro,
    'orden_m'   => $ordenMinus
]);
echo ui_generate_navbar();
?>

<!-- ============================================================ -->
<!-- TABS DE NAVEGACIÓN                                           -->
<!-- ============================================================ -->
<div class="nav-tabs-eve">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'crew'   ? 'active' : ''; ?>"
               href="?tab=crew&view=<?php echo $view; ?>&orden=<?php echo $orden; ?>">
                <i class="fas fa-users mr-1"></i> Crew
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'minus6' ? 'active' : ''; ?>"
               href="?tab=minus6&rango_sp=<?php echo $rangoSP; ?>&pocket=<?php echo $pocketFiltro; ?>&orden_m=<?php echo $ordenMinus; ?>">
                <i class="fas fa-user-graduate mr-1"></i> Pilotos &lt;6M SP
            </a>
        </li>
    </ul>
</div>

<!-- HORA DE ACTUALIZACIÓN -->
<div class="update-time">
    <i class="fas fa-clock"></i> Actualizado a las <?php echo date('H:i:s'); ?> (Hora de México)
</div>


<?php if ($tab === 'crew'): ?>
<!-- ============================================================ -->
<!-- TAB 1: CREW                                                  -->
<!-- ============================================================ -->

<div class="row mb-3 px-4">
    <div class="col-12 text-right">
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
    </div>
</div>

<div class="tiles-container">

<?php

if ($view == 'assets') {

    // --- Totales Assets ---
    $tempResult  = mysqli_query($link, $queryCrew);
    $totalPilotos = 0;
    $totalItems   = 0;
    $totalISK     = 0;
    while ($tempRow = mysqli_fetch_assoc($tempResult)) {
        $totalPilotos++;
        $totalItems += $tempRow['numitems'];
        $totalISK   += $tempRow['jitav'];
    }

    echo "
    <div class=\"tile-stats\">
        <i class=\"fa fa-user-astronaut\"></i>
        <div class=\"stat-number\">$totalPilotos</div>
        <div class=\"stat-label\">Pilotos</div>
        <div class=\"stat-number\" style=\"font-size: 20px; margin-top: 10px;\">
            " . number_format($totalItems) . "
        </div>
        <div class=\"stat-label\">Items Totales</div>
        <div class=\"stat-number\" style=\"font-size: 18px; margin-top: 5px;\">
            " . number_format($totalISK, 1) . "B
        </div>
        <div class=\"stat-label\">ISK Total</div>
    </div>
    ";

    $result = mysqli_query($link, $queryCrew);
    while ($row = mysqli_fetch_assoc($result)) {
        $toonNumber = $row['toon_number'];
        $toonName   = htmlspecialchars($row['toon_name']);
        $numItems   = number_format($row['numitems']);
        $pocket     = $row['pocket6'];
        $color      = getColorByPocket($pocket);
        $imgUrl     = "https://images.evetech.net/characters/{$toonNumber}/portrait";
        $linkUrl    = "https://elgoi.com/abyss/alphaassets.php?mode=assets&who={$toonNumber}";
        $iskValue   = formatISK($row['jitav']);

        $dif       = calcularDiferenciaMinutos(date('H:i:s'), $row['lastsaved']);
        $badgeHTML = "";
        if ($dif > 60) {
            $badgeHTML = "<div class=\"update-badge\" title=\"Actualización ESI disponible\"><i class=\"fas fa-clock\"></i></div>";
        }

        echo "
        <a href=\"$linkUrl\" target=\"_blank\" class=\"tile-pilot $color\">
            $badgeHTML
            <img src=\"$imgUrl\" alt=\"$toonName\" onerror=\"this.src='https://images.evetech.net/characters/1/portrait'\">
            <div class=\"pilot-name\">$toonName</div>
            <div class=\"ship-count\">$numItems</div>
            <div class=\"ship-label\">$iskValue</div>
        </a>
        ";
    }

} else {

    // --- Totales Hangars ---
    $tempResult   = mysqli_query($link, $queryCrew);
    $totalPilotos = 0;
    $totalNaves   = 0;
    while ($tempRow = mysqli_fetch_assoc($tempResult)) {
        $totalPilotos++;
        $totalNaves += $tempRow['numships'];
    }

    echo "
    <div class=\"tile-stats\">
        <i class=\"fa fa-user-astronaut\"></i>
        <div class=\"stat-number\">$totalPilotos</div>
        <div class=\"stat-label\">Pilotos</div>
        <div class=\"stat-number\" style=\"font-size: 20px; margin-top: 10px;\">
            " . number_format($totalNaves) . "
        </div>
        <div class=\"stat-label\">Naves Totales</div>
    </div>
    ";

    $result = mysqli_query($link, $queryCrew);
    while ($row = mysqli_fetch_assoc($result)) {
        $toonNumber = $row['toon_number'];
        $toonName   = htmlspecialchars($row['toon_name']);
        $numShips   = number_format($row['numships']);
        $pocket     = $row['pocket6'];
        $color      = getColorByPocket($pocket);
        $imgUrl     = "https://images.evetech.net/characters/{$toonNumber}/portrait";
        $linkUrl    = "https://elgoi.com/index.php?module=190&number={$toonNumber}";
        $msp        = formatMSP($row['skillpoints'], $row['unalloc']);

        $dif       = calcularDiferenciaMinutos(date('H:i:s'), $row['lastsaved']);
        $badgeHTML = "";
        if ($dif > 60) {
            $badgeHTML = "<div class=\"update-badge\" title=\"Actualización ESI disponible\"><i class=\"fas fa-clock\"></i></div>";
        }

        echo "
        <a href=\"$linkUrl\" target=\"_blank\" class=\"tile-pilot $color\">
            $badgeHTML
            <img src=\"$imgUrl\" alt=\"$toonName\" onerror=\"this.src='https://images.evetech.net/characters/1/portrait'\">
            <div class=\"pilot-name\">$toonName</div>
            <div class=\"ship-count\">$numShips</div>
            <div class=\"ship-label\">$msp</div>
        </a>
        ";
    }
}

if ($totalPilotos == 0) {
    echo "<div class=\"alert alert-info m-3\">No hay pilotos con el filtro seleccionado.</div>";
}
?>

</div><!-- /tiles-container crew -->


<?php else: ?>
<!-- ============================================================ -->
<!-- TAB 2: MINUS6                                                -->
<!-- ============================================================ -->

<!-- FILTROS -->
<div class="filter-form">
    <form method="GET" action="?" class="form-inline">
        <input type="hidden" name="tab" value="minus6">

        <label>Rango SP:</label>
        <select name="rango_sp" class="form-control form-control-sm">
            <option value="todos"   <?php echo $rangoSP == 'todos'   ? 'selected' : ''; ?>>Todos</option>
            <option value="menos-5" <?php echo $rangoSP == 'menos-5' ? 'selected' : ''; ?>>Menos de 5M</option>
            <option value="0-1"     <?php echo $rangoSP == '0-1'     ? 'selected' : ''; ?>>0-1M</option>
            <option value="1-2"     <?php echo $rangoSP == '1-2'     ? 'selected' : ''; ?>>1-2M</option>
            <option value="2-3"     <?php echo $rangoSP == '2-3'     ? 'selected' : ''; ?>>2-3M</option>
            <option value="3-4"     <?php echo $rangoSP == '3-4'     ? 'selected' : ''; ?>>3-4M</option>
            <option value="4-5"     <?php echo $rangoSP == '4-5'     ? 'selected' : ''; ?>>4-5M</option>
            <option value="5-6"     <?php echo $rangoSP == '5-6'     ? 'selected' : ''; ?>>5-6M (Ready)</option>
        </select>

        <label>Pocket:</label>
        <select name="pocket" class="form-control form-control-sm">
            <option value="todos" <?php echo $pocketFiltro == 'todos' ? 'selected' : ''; ?>>Todos</option>
            <option value="EXPER" <?php echo $pocketFiltro == 'EXPER' ? 'selected' : ''; ?>>EXPER</option>
            <option value="LUCKY" <?php echo $pocketFiltro == 'LUCKY' ? 'selected' : ''; ?>>LUCKY</option>
            <option value="NOKIA" <?php echo $pocketFiltro == 'NOKIA' ? 'selected' : ''; ?>>NOKIA</option>
            <option value="CLEAN" <?php echo $pocketFiltro == 'CLEAN' ? 'selected' : ''; ?>>CLEAN</option>
            <option value="SANGO" <?php echo $pocketFiltro == 'SANGO' ? 'selected' : ''; ?>>SANGO</option>
            <option value="YENN"  <?php echo $pocketFiltro == 'YENN'  ? 'selected' : ''; ?>>YENN</option>
        </select>

        <label>Ordenar por:</label>
        <select name="orden_m" class="form-control form-control-sm">
            <option value="sp_desc" <?php echo $ordenMinus == 'sp_desc' ? 'selected' : ''; ?>>SP Descendente</option>
            <option value="pocket"  <?php echo $ordenMinus == 'pocket'  ? 'selected' : ''; ?>>Pocket</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm ml-2">
            <i class="fas fa-filter"></i> Filtrar
        </button>
    </form>
</div>

<div class="row mb-3 px-4">
    <div class="col-12 text-right">
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver al Dashboard
        </a>
    </div>
</div>

<div class="tiles-container">

<?php
// --- Estadísticas Minus6 ---
$tempResult          = mysqli_query($link, $queryMinus);
$totalPilotos        = 0;
$totalSPSum          = 0;
$totalDias           = 0;
$pilotosEnEntrenamiento = 0;

while ($tempRow = mysqli_fetch_assoc($tempResult)) {
    $totalPilotos++;
    $spTotal      = $tempRow['skillpoints'] + $tempRow['unalloc'];
    $totalSPSum  += $spTotal;
    if ($spTotal < 5000000) {
        $totalDias += calcularDiasSoloDiario($spTotal);
        $pilotosEnEntrenamiento++;
    }
}

$promedioSP   = $totalPilotos > 0 ? $totalSPSum / $totalPilotos : 0;
$promedioDias = $pilotosEnEntrenamiento > 0 ? $totalDias / $pilotosEnEntrenamiento : 0;

echo "
<div class=\"tile-stats\">
    <i class=\"fa fa-user-astronaut\"></i>
    <div class=\"stat-number\">$totalPilotos</div>
    <div class=\"stat-label\">Pilotos Alpha</div>
    <div class=\"stat-number\" style=\"font-size: 20px;\">
        " . number_format($promedioSP / 1000000, 2) . " MSP
    </div>
    <div class=\"stat-label\">Promedio SP</div>
    <div class=\"stat-number\" style=\"font-size: 20px;\">
        " . number_format($promedioDias, 2) . "
    </div>
    <div class=\"stat-label\">Días promedio a 5M</div>
</div>
";

$result = mysqli_query($link, $queryMinus);
while ($row = mysqli_fetch_assoc($result)) {
    $toonNumber  = $row['toon_number'];
    $toonName    = htmlspecialchars($row['toon_name']);
    $spTotal     = $row['skillpoints'] + $row['unalloc'];
    $pocket      = $row['pocket6'];
    $color       = getColorByPocket($pocket);
    $imgUrl      = "https://images.evetech.net/characters/{$toonNumber}/portrait";
    $linkUrl     = "https://elgoi.com/index.php?module=190&number={$toonNumber}";
    $spFormatted = formatSP($row['skillpoints'], $row['unalloc']);
    $porcentaje  = calcularPorcentaje($spTotal);

    $dif       = calcularDiferenciaMinutos(date('H:i:s'), $row['lastsaved']);
    $badgeHTML = "";
    if ($dif > 60) {
        $badgeHTML = "<div class=\"update-badge\" title=\"Actualización ESI disponible\"><i class=\"fas fa-clock\"></i></div>";
    }

    echo "
    <a href=\"$linkUrl\" target=\"_blank\" class=\"tile-pilot minus6-tile $color\">
        $badgeHTML
        <img src=\"$imgUrl\" alt=\"$toonName\" onerror=\"this.src='https://images.evetech.net/characters/1/portrait'\">
        <div class=\"pilot-name\">$toonName</div>
        <div class=\"sp-total\">$spFormatted</div>
    ";

    if ($spTotal >= 5000000) {
        echo "
        <div class=\"progress-bar-container\">
            <div class=\"progress-bar-fill\" style=\"width: 100%;\">100%</div>
        </div>
        <div class=\"ready-badge\">READY</div>
        ";
    } else {
        $dias          = calcularDiasSoloDiario($spTotal);
        $meses         = calcularMesesOptimo($spTotal);
        $fechaEstimada = calcularFechaEstimada($dias);
        $pct           = number_format($porcentaje, 2);
        $pctLabel      = number_format($porcentaje, 1);
        $diasFmt       = number_format($dias, 2);
        $mesesFmt      = number_format($meses, 2);

        echo "
        <div class=\"progress-bar-container\">
            <div class=\"progress-bar-fill\" style=\"width: $pct%;\">$pctLabel%</div>
        </div>

        <div class=\"training-info\">
            <div class=\"label\">Solo Daily:</div>
            <div class=\"value\">$diasFmt días</div>
        </div>

        <div class=\"training-info\">
            <div class=\"label\">Óptimo (Daily+Mensual):</div>
            <div class=\"value\">$mesesFmt meses</div>
        </div>

        <div class=\"training-info\" style=\"margin-top: 10px;\">
            <div class=\"label\">Fecha estimada:</div>
            <div class=\"value\" style=\"font-size: 15px;\">$fechaEstimada</div>
        </div>
        ";
    }

    echo "</a>";
}

if ($totalPilotos == 0) {
    echo "<div class=\"alert alert-info m-3\">No hay pilotos con el filtro seleccionado.</div>";
}
?>

</div><!-- /tiles-container minus6 -->

<?php endif; ?>


<!-- ============================================================ -->
<!-- FOOTER FIJO                                                  -->
<!-- ============================================================ -->
<footer>
    EVE Pilots Panel &copy; <?php echo date('Y'); ?> |
    IP: <?php echo $_SERVER['REMOTE_ADDR']; ?> |
    PHP: <?php echo phpversion(); ?>
</footer>

<!-- jQuery 3.7.1 -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<!-- Bootstrap 4.6.2 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
