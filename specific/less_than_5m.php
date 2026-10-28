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

// Apply security
check_authorization();

// Set Mexico timezone
date_default_timezone_set('America/Mexico_City');

// ===============================================
// CONSTANTS
// ===============================================
const SP_LIMIT          = 5000000; // Only pilots with combined SP below this value are shown
const SP_PER_DAY        = 10000;   // Daily reward SP
const SP_PER_MONTH_OPT  = 525000;  // Daily + monthly rewards, approx. SP per month

// ===============================================
// PARAMETERS
// ===============================================
$pocketFiltro = isset($_GET['pocket'])  ? $_GET['pocket']  : 'all';
$ordenMinus   = isset($_GET['orden_m']) ? $_GET['orden_m'] : 'sp_desc';

// Base filter: combined SP (skillpoints + unallocated) below the limit
$whereConditions = [
    "(skillpoints + unalloc) < " . SP_LIMIT,
    "toon_name NOT LIKE '%Catalog%'",
    "toon_name NOT LIKE '%VPS%'"
];

if ($pocketFiltro != 'all') {
    $whereConditions[] = "pocket6 = '" . mysqli_real_escape_string($link, $pocketFiltro) . "'";
}

$orderByMinus = "(skillpoints + unalloc) DESC";
switch ($ordenMinus) {
    case 'pocket':  $orderByMinus = "pocket6 ASC, (skillpoints + unalloc) DESC"; break;
    case 'sp_desc': $orderByMinus = "(skillpoints + unalloc) DESC";               break;
}

$whereClause = implode(" AND ", $whereConditions);

$queryMinus = "SELECT toon_number, toon_name, skillpoints, unalloc, evermarks, pocket6, lastsaved
               FROM PILOTS
               WHERE $whereClause
               ORDER BY $orderByMinus";

// ===============================================
// FUNCTIONS
// ===============================================

/**
 * Returns the Bootstrap color class for a pilot's pocket.
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
 * Minutes elapsed between now (Mexico time) and lastsaved (stored in UTC).
 */
function calcularDiferenciaMinutos($fechaMexico, $fechaUTC) {
    $dtMexico = new DateTime($fechaMexico, new DateTimeZone('America/Mexico_City'));
    $dtMexico->setTimezone(new DateTimeZone('UTC'));
    $dtUTC = new DateTime($fechaUTC, new DateTimeZone('UTC'));
    $diferencia = $dtMexico->getTimestamp() - $dtUTC->getTimestamp();
    return abs($diferencia / 60);
}

/**
 * Formats total SP as "X.XX MSP".
 */
function formatSP($skillpoints, $unalloc) {
    $totalSP = ($skillpoints + $unalloc) / 1000000;
    return number_format($totalSP, 2) . ' MSP';
}

/**
 * Days needed to reach the SP limit using only the daily reward.
 */
function calcularDiasSoloDiario($totalSP) {
    if ($totalSP >= SP_LIMIT) return 0;
    return (SP_LIMIT - $totalSP) / SP_PER_DAY;
}

/**
 * Months needed to reach the SP limit in optimal mode (daily + monthly rewards).
 */
function calcularMesesOptimo($totalSP) {
    if ($totalSP >= SP_LIMIT) return 0;
    return (SP_LIMIT - $totalSP) / SP_PER_MONTH_OPT;
}

/**
 * Progress percentage towards the SP limit.
 */
function calcularPorcentaje($totalSP) {
    return min(($totalSP / SP_LIMIT) * 100, 100);
}

/**
 * Estimated date of reaching the SP limit.
 */
function calcularFechaEstimada($dias) {
    if ($dias <= 0) return 'READY';
    $fecha = new DateTime('now', new DateTimeZone('America/Mexico_City'));
    $fecha->modify('+' . ceil($dias) . ' days');
    return $fecha->format('d/M/Y');
}

// ===============================================
// LOAD DATA (single query)
// ===============================================
$pilots = [];
$result = mysqli_query($link, $queryMinus);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pilots[] = $row;
    }
}

// Summary statistics
$totalPilotos = count($pilots);
$totalSPSum   = 0;
$totalDias    = 0;
$totalEvermarks = 0;

foreach ($pilots as $p) {
    $spTotal = $p['skillpoints'] + $p['unalloc'];
    $totalSPSum += $spTotal;
    $totalDias  += calcularDiasSoloDiario($spTotal);
    $totalEvermarks += (int)$p['evermarks'];
}

$promedioSP   = $totalPilotos > 0 ? $totalSPSum / $totalPilotos : 0;
$promedioDias = $totalPilotos > 0 ? $totalDias / $totalPilotos : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EVE Online Panel - Pilots under 5M SP</title>

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
            min-height: 410px;
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

        .tile-pilot .sp-total {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .tile-pilot .evermarks {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 6px;
            opacity: 0.95;
        }

        /* ---- ESI update badge ---- */
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

        /* ---- Stats tile ---- */
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
            min-height: 410px;
        }

        .tile-stats i            { font-size: 50px; margin-bottom: 10px; }
        .tile-stats .stat-number { font-size: 28px; font-weight: bold; }
        .tile-stats .stat-label  { font-size: 14px; opacity: 0.9; margin-bottom: 15px; }

        /* ---- Progress bar ---- */
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

        /* ---- Miscellaneous ---- */
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
// Fixed navbar
crew_navbar(__FILE__, [
    'pocket'  => $pocketFiltro,
    'orden_m' => $ordenMinus
]);
echo ui_generate_navbar();
?>

<!-- LAST UPDATE TIME -->
<div class="update-time">
    <i class="fas fa-clock"></i> Updated at <?php echo date('H:i:s'); ?> (Mexico time)
</div>

<!-- SCOPE NOTICE -->
<div class="alert alert-info mx-4">
    <i class="fas fa-info-circle"></i>
    Only pilots with a <strong>combined total below 5M SP</strong> (skillpoints + unallocated) are shown.
</div>

<!-- FILTERS -->
<div class="filter-form">
    <form method="GET" action="?" class="form-inline">
        <label>Pocket:</label>
        <select name="pocket" class="form-control form-control-sm">
            <option value="all"   <?php echo $pocketFiltro == 'all'   ? 'selected' : ''; ?>>All</option>
            <option value="EXPER" <?php echo $pocketFiltro == 'EXPER' ? 'selected' : ''; ?>>EXPER</option>
            <option value="LUCKY" <?php echo $pocketFiltro == 'LUCKY' ? 'selected' : ''; ?>>LUCKY</option>
            <option value="NOKIA" <?php echo $pocketFiltro == 'NOKIA' ? 'selected' : ''; ?>>NOKIA</option>
            <option value="CLEAN" <?php echo $pocketFiltro == 'CLEAN' ? 'selected' : ''; ?>>CLEAN</option>
            <option value="SANGO" <?php echo $pocketFiltro == 'SANGO' ? 'selected' : ''; ?>>SANGO</option>
            <option value="YENN"  <?php echo $pocketFiltro == 'YENN'  ? 'selected' : ''; ?>>YENN</option>
        </select>

        <label>Sort by:</label>
        <select name="orden_m" class="form-control form-control-sm">
            <option value="sp_desc" <?php echo $ordenMinus == 'sp_desc' ? 'selected' : ''; ?>>SP Descending</option>
            <option value="pocket"  <?php echo $ordenMinus == 'pocket'  ? 'selected' : ''; ?>>Pocket</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm ml-2">
            <i class="fas fa-filter"></i> Filter
        </button>
    </form>
</div>

<div class="row mb-3 px-4">
    <div class="col-12 text-right">
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<div class="tiles-container">

    <!-- Summary tile -->
    <div class="tile-stats">
        <i class="fa fa-user-astronaut"></i>
        <div class="stat-number"><?php echo $totalPilotos; ?></div>
        <div class="stat-label">Alpha Pilots</div>
        <div class="stat-number" style="font-size: 20px;">
            <?php echo number_format($promedioSP / 1000000, 2); ?> MSP
        </div>
        <div class="stat-label">Average SP</div>
        <div class="stat-number" style="font-size: 20px;">
            <?php echo number_format($promedioDias, 2); ?>
        </div>
        <div class="stat-label">Average days to 5M</div>
        <div class="stat-number" style="font-size: 20px;">
            <?php echo number_format($totalEvermarks); ?>
        </div>
        <div class="stat-label">Total Evermarks</div>
    </div>

<?php
$nowMexico = date('Y-m-d H:i:s');

foreach ($pilots as $row) {
    $toonNumber  = $row['toon_number'];
    $toonName    = htmlspecialchars($row['toon_name']);
    $spTotal     = $row['skillpoints'] + $row['unalloc'];
    $pocket      = $row['pocket6'];
    $color       = getColorByPocket($pocket);
    $imgUrl      = "https://images.evetech.net/characters/{$toonNumber}/portrait";
    $linkUrl     = "https://elgoi.com/index.php?module=190&number={$toonNumber}";
    $spFormatted = formatSP($row['skillpoints'], $row['unalloc']);
    $porcentaje  = calcularPorcentaje($spTotal);
    $evermarks   = number_format((int)$row['evermarks']);

    // Show the clock badge if the last ESI save is more than 60 minutes old
    $dif       = calcularDiferenciaMinutos($nowMexico, $row['lastsaved']);
    $badgeHTML = "";
    if ($dif > 60) {
        $badgeHTML = "<div class=\"update-badge\" title=\"ESI update available\"><i class=\"fas fa-clock\"></i></div>";
    }

    $dias          = calcularDiasSoloDiario($spTotal);
    $meses         = calcularMesesOptimo($spTotal);
    $fechaEstimada = calcularFechaEstimada($dias);
    $pct           = number_format($porcentaje, 2, '.', '');
    $pctLabel      = number_format($porcentaje, 1);
    $diasFmt       = number_format($dias, 2);
    $mesesFmt      = number_format($meses, 2);

    echo "
    <a href=\"$linkUrl\" target=\"_blank\" class=\"tile-pilot $color\">
        $badgeHTML
        <img src=\"$imgUrl\" alt=\"$toonName\" onerror=\"this.src='https://images.evetech.net/characters/1/portrait'\">
        <div class=\"pilot-name\">$toonName</div>
        <div class=\"sp-total\">$spFormatted</div>
        <div class=\"evermarks\"><i class=\"fas fa-medal\"></i> Evermarks: $evermarks</div>

        <div class=\"progress-bar-container\">
            <div class=\"progress-bar-fill\" style=\"width: $pct%;\">$pctLabel%</div>
        </div>

        <div class=\"training-info\">
            <div class=\"label\">Daily only:</div>
            <div class=\"value\">$diasFmt days</div>
        </div>

        <div class=\"training-info\">
            <div class=\"label\">Optimal (Daily + Monthly):</div>
            <div class=\"value\">$mesesFmt months</div>
        </div>

        <div class=\"training-info\" style=\"margin-top: 10px;\">
            <div class=\"label\">Estimated date:</div>
            <div class=\"value\" style=\"font-size: 15px;\">$fechaEstimada</div>
        </div>
    </a>
    ";
}

if ($totalPilotos == 0) {
    echo "<div class=\"alert alert-info m-3\">No pilots match the selected filter.</div>";
}
?>

</div><!-- /tiles-container -->

<!-- ============================================================ -->
<!-- FIXED FOOTER                                                 -->
<!-- ============================================================ -->
<footer>
    EVE Pilots Panel &copy; <?php echo date('Y'); ?> |
    IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?> |
    PHP: <?php echo phpversion(); ?>
</footer>

<!-- jQuery 3.7.1 -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<!-- Bootstrap 4.6.2 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
