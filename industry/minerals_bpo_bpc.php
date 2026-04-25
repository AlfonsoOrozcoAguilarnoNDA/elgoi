<?php
// =====================================================================
// EVE PANEL UNIFICADO — HangarAll · WhoHas · Blueprints
// =====================================================================
if (session_status() === PHP_SESSION_NONE) session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

$nombre_archivo = strtoupper(basename(__FILE__, '.php'));

// =====================================================================
// TABS — gestión de estado
// =====================================================================
$valid_tabs = ['hangar', 'whohas', 'blueprints'];

if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)) {
    $_SESSION['eve_tab'] = $_GET['tab'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_hangar_submit']))   { $_SESSION['eve_tab'] = 'hangar';     }
    if (isset($_POST['_whohas_submit']))   { $_SESSION['eve_tab'] = 'whohas';     }
}
$tabActivo = $_SESSION['eve_tab'] ?? 'hangar';

// =====================================================================
// COLORES POCKET
// =====================================================================
$pocket6_colors = [
    'SANGO' => '#FFF9C4', 'EXPER' => '#C8E6C9', 'NOKIA' => '#FFCDD2',
    'CLEAN' => '#BBDEFB', 'LUCKY' => '#FFFFFF',  'YENN'  => '#E0E0E0',
];
$pocket_colors = [
    'NOKIA' => '#FFE5E5', 'CLEAN' => '#E5F5FF',
    'EXPER' => '#E5FFE5', 'SANGO' => '#fff3cd', 'DEFAULT' => '#FFFFFF',
];

define('TOON_IGNORE', 2114226800);
$timestamp = date('d/m/Y H:i:s');

// =====================================================================
// BLUEPRINTS ESI — Constantes y funciones
// =====================================================================
list($fleet_token)=avalues319b("select token_fleet from fleet_config");
list($fleet_client)=avalues319b("select clientid_fleet from fleet_config");
define('CLIENT_ID', $fleet_token);
define('CLIENT_SECRET', $fleet_client);

//define('CALLBACK_URL',  'https://elgoi.com/devauthcallback.php');
define('ESI_BASE_URL',  'https://esi.evetech.net/latest');
//define('AUTH_URL',      'https://login.eveonline.com/v2/oauth/authorize/');
define('TOKEN_URL',     'https://login.eveonline.com/v2/oauth/token');

$ignored_blueprints = [
    77526, // Industrialist - Hauler Blueprint Crate
];

function makeHttpRequest($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST' && $data !== null)
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response];
}

function refreshToken($tokenData) {
    $authHeader = base64_encode(CLIENT_ID . ':' . CLIENT_SECRET);
    $headers = ['Authorization: Basic ' . $authHeader, 'Content-Type: application/x-www-form-urlencoded'];
    $data    = ['grant_type' => 'refresh_token', 'refresh_token' => $tokenData['refresh_token']];
    $response = makeHttpRequest(TOKEN_URL, 'POST', $headers, $data);
    if ($response['code'] != 200) return false;
    $newTokenData = json_decode($response['body'], true);
    if (!isset($newTokenData['refresh_token'])) $newTokenData['refresh_token'] = $tokenData['refresh_token'];
    $newTokenData['character_id'] = $tokenData['character_id'];
    return $newTokenData;
}

function saveTokenToDB($tokenData, $character_id) {
    global $link;
    $at = mysqli_real_escape_string($link, $tokenData['access_token']);
    $rt = mysqli_real_escape_string($link, $tokenData['refresh_token']);
    mysqli_query($link, "UPDATE PILOTS SET token20min='$at', refreshtoken='$rt', daterefresh=DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE toon_number=$character_id");
    return true;
}

function getCharacterData($tokenData, $endpoint) {
    $endpoint = str_replace('{character_id}', $tokenData['character_id'], $endpoint);
    $headers  = ['Authorization: Bearer ' . $tokenData['access_token'], 'Content-Type: application/json'];
    $url      = ESI_BASE_URL . $endpoint;
    $response = makeHttpRequest($url, 'GET', $headers);
    if ($response['code'] == 401) {
        $newTokenData = refreshToken($tokenData);
        if ($newTokenData) {
            saveTokenToDB($newTokenData, $tokenData['character_id']);
            $headers[0] = 'Authorization: Bearer ' . $newTokenData['access_token'];
            $response   = makeHttpRequest($url, 'GET', $headers);
        }
    }
    return ($response['code'] >= 200 && $response['code'] < 300) ? $response['body'] : false;
}

// =====================================================================
// HANGARALL — filtros y consulta
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
    $all_types        = ($selected_type === 'TODOS');

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
// WHOHAS — filtro pocket
// =====================================================================
$filtro_pocket_global = 'TODOS';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_whohas_submit'])) {
    $filtro_pocket_global = $_POST['pocket_filter'] ?? 'TODOS';
    $_SESSION['whohas_pocket'] = $filtro_pocket_global;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pocket_filter']) && !isset($_POST['_hangar_submit'])) {
    $filtro_pocket_global = $_POST['pocket_filter'] ?? 'TODOS';
    $_SESSION['whohas_pocket'] = $filtro_pocket_global;
} else {
    $filtro_pocket_global = $_SESSION['whohas_pocket'] ?? 'TODOS';
}

// =====================================================================
// BLUEPRINTS — procesamiento (solo si el tab está activo)
// =====================================================================
$reporteGlobal   = [];
$totalesGlobales = ['BPO' => 0, 'BPC' => 0];
$totalPilotosConPlanos = 0;

if ($tabActivo === 'blueprints') {
    $sqlPilotos = "SELECT DISTINCT p.toon_name, p.toon_number, p.token20min, p.refreshtoken
                   FROM PILOTS p
                   INNER JOIN EVE_ASSETS a ON p.toon_number = a.toon_number
                   WHERE a.type_description LIKE '%Blueprint%'
                   AND p.toon_number NOT IN (2122782650, 2122783972, 2122782609)";

    $resPilotos = mysqli_query($link, $sqlPilotos);

    while ($p = mysqli_fetch_assoc($resPilotos)) {
        $tokenData = [
            'access_token'  => $p['token20min'],
            'refresh_token' => $p['refreshtoken'],
            'character_id'  => $p['toon_number']
        ];

        $jsonBlueprints = getCharacterData($tokenData, "/characters/{character_id}/blueprints/");
        if (!$jsonBlueprints) continue;

        $blueprints = json_decode($jsonBlueprints, true);
        if (!is_array($blueprints)) continue;

        foreach ($blueprints as $bp) {
            if (in_array($bp['type_id'], $ignored_blueprints)) continue;

            $esBPO = ($bp['runs'] == -1);
            $tipo  = $esBPO ? 'BPO' : 'BPC';
            $tID   = $bp['type_id'];

            $qN = mysqli_query($link, "SELECT type_description FROM EVE_ASSETS WHERE type_id = $tID LIMIT 1");
            $rN = mysqli_fetch_assoc($qN);
            $nombrePlano = $rN ? str_replace(' Blueprint', '', $rN['type_description']) : "Unknown ID $tID";

            $reporteGlobal[$p['toon_name']]['items'][] = [
                'nombre' => $nombrePlano,
                'tipo'   => $tipo,
                'runs'   => $esBPO ? 'INF' : $bp['runs'],
                'me'     => $bp['material_efficiency'],
                'te'     => $bp['time_efficiency']
            ];

            if (!isset($reporteGlobal[$p['toon_name']][$tipo])) $reporteGlobal[$p['toon_name']][$tipo] = 0;
            $reporteGlobal[$p['toon_name']][$tipo]++;
            $totalesGlobales[$tipo]++;
        }
        $reporteGlobal[$p['toon_name']]['id'] = $p['toon_number'];
    }

    $totalPilotosConPlanos = count($reporteGlobal);
}

// =====================================================================
// FUNCIONES WHOHAS
// =====================================================================
function cuadricula($items_string, $titulo_grid, $icono_fa) {
    global $link, $filtro_pocket_global, $pocket_colors;
    $items = array_map('trim', explode(',', $items_string));
    $item_list = "'" . implode("','", array_map(fn($i) => mysqli_real_escape_string($link, $i), $items)) . "'";
    $where_pocket = "";
    if ($filtro_pocket_global !== 'TODOS') {
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
    renderizarCuadricula($data, $pilotos_orden, $pilotos_info, $items, $titulo_grid, $icono_fa, $GLOBALS['pocket_colors']);
}

function renderizarCuadricula($data, $pilotos_orden, $pilotos_info, $items, $titulo, $icono, $pocket_colors) {
    $num_pilotos = count($pilotos_orden);
    if ($num_pilotos == 0) {
        echo '<div class="alert alert-warning"><i class="fas fa-info-circle"></i> No se encontraron pilotos con estos items.</div>';
        return;
    }
    ?>
    <div class="wh-section-title">
        <i class="fas <?php echo htmlspecialchars($icono); ?>"></i>
        <?php echo htmlspecialchars($titulo); ?>
        <span class="badge badge-light ml-2"><?php echo $num_pilotos; ?> pilotos</span>
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Panel — HangarAll · WhoHas · Blueprints</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
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

        /* ── CONTENIDO ── */
        .tab-body { padding: 25px 15px; }

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

        /* ── REPORTE HANGAR ── */
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

        /* ── BLUEPRINTS ── */
        .bp-page-header {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .bp-page-header h4 {
            color: #fff;
            margin: 0;
            font-weight: 600;
        }
        .badge-pilotos {
            background-color: #007bff;
            color: #fff;
            font-size: 0.82rem;
            padding: 5px 12px;
            border-radius: 3px;
            font-weight: 600;
        }
        .badge-bpo {
            background-color: #1a1200;
            border: 1px solid #f1c40f;
            color: #f1c40f;
            font-size: 0.82rem;
            padding: 5px 12px;
            border-radius: 3px;
            font-weight: 600;
        }
        .badge-bpc {
            background-color: #001220;
            border: 1px solid #3498db;
            color: #3498db;
            font-size: 0.82rem;
            padding: 5px 12px;
            border-radius: 3px;
            font-weight: 600;
        }
        .card-pilot {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 25px;
            transition: border-color 0.2s;
        }
        .card-pilot:hover {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.15);
        }
        .card-pilot .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            padding: 10px 15px;
        }
        .pilot-portrait {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #495057;
        }
        .table-blueprints {
            background-color: #1a1d21;
            color: #ced4da;
            font-size: 0.83rem;
            margin-bottom: 0;
        }
        .table-blueprints thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .table-blueprints tbody tr:nth-child(odd)  { background-color: #1e2126; }
        .table-blueprints tbody tr:nth-child(even) { background-color: #1a1d21; }
        .table-blueprints tbody tr:hover           { background-color: #2a3040 !important; color: #fff; }
        .table-blueprints td { border-color: #2c3035; vertical-align: middle; }
        .row-num {
            color: #00bcd4;
            font-weight: bold;
            text-align: center;
            border-right: 1px solid #2c3035;
            font-family: monospace;
            width: 45px;
        }
        .bpo-tag {
            color: #f1c40f;
            border: 1px solid #f1c40f;
            padding: 1px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            border-radius: 2px;
        }
        .bpc-tag {
            color: #3498db;
            border: 1px solid #3498db;
            padding: 1px 6px;
            font-size: 0.7rem;
            font-weight: bold;
            border-radius: 2px;
        }
        .efficiency { color: #28a745; font-family: monospace; }
        .runs-inf   { color: #f1c40f; font-size: 1.1rem; }

        /* ── FOOTER FIJO ── */
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
    <a href="?tab=hangar" class="eve-tab <?php echo $tabActivo === 'hangar' ? 'active' : ''; ?>">
        <i class="fas fa-warehouse"></i> HangarAll
    </a>
    <a href="?tab=whohas" class="eve-tab <?php echo $tabActivo === 'whohas' ? 'active' : ''; ?>">
        <i class="fas fa-search"></i> WhoHas
        <?php if ($filtro_pocket_global !== 'TODOS'): ?>
        <span class="badge badge-primary" style="font-size:.68rem;"><?php echo htmlspecialchars($filtro_pocket_global); ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=blueprints" class="eve-tab <?php echo $tabActivo === 'blueprints' ? 'active' : ''; ?>">
        <i class="fas fa-drafting-compass"></i> Blueprints
    </a>
</div>

<div class="tab-body">
<div class="container-fluid">

<!-- ================================================================
     TAB 1 — HANGARALL
     ================================================================ -->
<?php if ($tabActivo === 'hangar'): ?>

<div class="card filter-dark mb-4">
    <div class="card-header">
        <i class="fas fa-filter mr-1"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="POST" action="?tab=hangar">
            <input type="hidden" name="_hangar_submit" value="1">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><i class="fas fa-space-shuttle mr-1"></i> Tipo de Nave *</label>
                        <select class="form-control" name="type_name" required>
                            <option value="">-- Seleccione un tipo --</option>
                            <option value="TODOS" <?php echo $selected_type === 'TODOS' ? 'selected' : ''; ?>>
                                ** TODOS (Excepto Corvette) **
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
                        <label><i class="fas fa-folder mr-1"></i> Pocket6 (opcional — múltiple)</label>
                        <div class="pocket-scroll">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="pocket_all"
                                       onclick="toggleAllPockets(this)">
                                <label class="form-check-label" for="pocket_all"><strong>TODOS</strong></label>
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
                <i class="fas fa-search mr-1"></i> Generar Reporte
            </button>
        </form>
    </div>
</div>

<?php if ($report_generated): ?>
<div class="card report-card">
    <div class="card-header">
        <i class="fas fa-chart-bar mr-1"></i> Reporte al <?php echo $timestamp; ?>
    </div>
    <div class="card-body">
        <?php if (empty($pilots_data)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i> No se encontraron naves con los filtros seleccionados.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="table table-bordered table-sm" style="min-width:1200px;color:#ced4da;">
                <thead class="thead-dark">
                    <tr>
                        <th style="position:sticky;left:0;background:#0d0f11;z-index:10;">#</th>
                        <th style="position:sticky;left:40px;background:#0d0f11;z-index:10;min-width:200px;">Nave</th>
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
                            <i class="fas fa-calculator mr-1"></i> TOTAL POR PILOTO
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
            <strong><i class="fas fa-info-circle mr-1"></i> Resumen</strong>
            <ul class="mt-2 mb-0">
                <li><strong>Tipo:</strong> <?php echo $all_types ? 'TODOS (Excepto Corvette)' : htmlspecialchars($selected_type); ?></li>
                <li><strong>Pilotos:</strong> <?php echo count($pilots_data); ?></li>
                <li><strong>Total naves:</strong> <?php echo array_sum($ships_list); ?></li>
                <li><strong>Modelos distintos:</strong> <?php echo count($ships_list); ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; // fin hangar ?>

<!-- ================================================================
     TAB 2 — WHOHAS
     ================================================================ -->
<?php if ($tabActivo === 'whohas'): ?>

<div class="wh-timestamp">
    <i class="fas fa-clock mr-1"></i> Generado: <strong><?php echo $timestamp; ?></strong> (Hora de México)
</div>

<div class="wh-info-box">
    <h5><i class="fas fa-info-circle mr-1"></i> Inventario Personalizado de Items</h5>
    <p class="mb-0">Consulta inventarios de items específicos organizados por piloto. Datos en tiempo real. Pilotos ordenados por fecha de nacimiento y coloreados por pocket.</p>
</div>

<div class="wh-filter-box">
    <form method="POST" action="?tab=whohas">
        <input type="hidden" name="_whohas_submit" value="1">
        <div class="row align-items-center">
            <div class="col-md-2">
                <label class="mb-0"><i class="fas fa-folder mr-1"></i> <strong>Filtrar por Pocket:</strong></label>
            </div>
            <div class="col-md-7">
                <select name="pocket_filter" class="form-control" onchange="this.form.submit()">
                    <option value="TODOS" <?php echo $filtro_pocket_global === 'TODOS' ? 'selected' : ''; ?>>TODOS LOS POCKETS</option>
                    <option value="NOKIA" <?php echo $filtro_pocket_global === 'NOKIA' ? 'selected' : ''; ?>>NOKIA</option>
                    <option value="CLEAN" <?php echo $filtro_pocket_global === 'CLEAN' ? 'selected' : ''; ?>>CLEAN</option>
                    <option value="EXPER" <?php echo $filtro_pocket_global === 'EXPER' ? 'selected' : ''; ?>>EXPER</option>
                    <option value="SANGO" <?php echo $filtro_pocket_global === 'SANGO' ? 'selected' : ''; ?>>SANGO</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-sync-alt mr-1"></i> Aplicar
                </button>
            </div>
        </div>
        <?php if ($filtro_pocket_global !== 'TODOS'): ?>
        <div class="mt-2">
            <span class="badge badge-primary" style="font-size:0.85em;padding:5px 12px;">
                <i class="fas fa-filter mr-1"></i> Pocket activo: <strong><?php echo htmlspecialchars($filtro_pocket_global); ?></strong>
            </span>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Leyenda colores -->
<div class="card wh-card mb-4">
    <div class="card-body py-2">
        <small class="text-muted mr-2"><i class="fas fa-palette mr-1"></i> Leyenda:</small>
        <span class="pocket-legend" style="background-color:#FFE5E5;">NOKIA</span>
        <span class="pocket-legend" style="background-color:#E5F5FF;">CLEAN</span>
        <span class="pocket-legend" style="background-color:#E5FFE5;">EXPER</span>
        <span class="pocket-legend" style="background-color:#FFF5E5;">SANGO</span>
        <span class="pocket-legend" style="background-color:#FFFFFF;border:1px solid #495057;">OTROS</span>
    </div>
</div>

<?php
cuadricula('Tritanium,Pyerite,Mexallon,Isogen,Nocxium,Zydrine,Megacyte',
           'Inventario de Minerales Básicos', 'fa-gem');
cuadricula('Compressed Bezdnacine,Compressed Concentrated Veldspar,Compressed Condensed Scordite,Compressed Dense Veldspar,Compressed Massive Scordite,Compressed Rich Plagioclase,Compressed Scordite,Compressed Veldspar,Concentrated Veldspar,Dense Veldspar,Veldspar',
           'Inventario de ore Asteroides específicos', 'fa-mountain');
cuadricula('Caracal,Catalyst,Punisher,Kestrel,Cormorant,Merlin,Condor,Thrasher,Thorax,Talwar',
           'Inventario de Naves para Abyss', 'fa-rocket');
cuadricula('Orca,Porpoise,Occator,Kernite,Compressed Kernite',
           'Informativo de Control Pocket', 'fa-info-circle');
cuadricula('Tranquil Dark Filament,Tranquil Electrical Filament,Tranquil Exotic Filament,Tranquil Firestorm Filament,Tranquil Gamma Filament,Calm Dark Filament,Calm Electrical Filament,Calm Exotic Filament,Calm Firestorm Filament,Calm Gamma Filament,Agitated Dark Filament,Agitated Electrical Filament,Agitated Exotic Filament,Agitated Firestorm Filament,Agitated Gamma Filament',
           'Filamentos Abyss', 'fa-bolt');
cuadricula('Scourge Light Missile,Antimatter Charge S,Antimatter Charge M,Nuclear S',
           'Inventario de Municiones de Comercio', 'fa-crosshairs');
cuadricula('PLEX,Skill Injector,Skill Extractor',
           'Inventario de Items Valiosos', 'fa-coins');
?>

<?php endif; // fin whohas ?>

<!-- ================================================================
     TAB 3 — BLUEPRINTS ESI LIVE
     ================================================================ -->
<?php if ($tabActivo === 'blueprints'): ?>

<div class="bp-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h4>
            <i class="fas fa-drafting-compass mr-2"></i>Inventario de Planos (ESI Live)
        </h4>
        <div class="d-flex align-items-center" style="gap:8px;">
            <span class="badge-pilotos">
                <i class="fas fa-users mr-1"></i><?php echo $totalPilotosConPlanos; ?> pilotos con planos
            </span>
            <span class="badge-bpo">
                <i class="fas fa-scroll mr-1"></i>BPO: <?php echo $totalesGlobales['BPO']; ?>
            </span>
            <span class="badge-bpc">
                <i class="fas fa-copy mr-1"></i>BPC: <?php echo $totalesGlobales['BPC']; ?>
            </span>
        </div>
    </div>
</div>

<?php foreach ($reporteGlobal as $piloto => $data): ?>
<?php if (!isset($data['items'])) continue; ?>

<div class="card-pilot shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <img src="https://images.evetech.net/characters/<?php echo $data['id']; ?>/portrait?size=64"
                 class="pilot-portrait mr-3" alt="<?php echo htmlspecialchars($piloto); ?>">
            <div>
                <h6 class="text-white mb-0"><?php echo htmlspecialchars($piloto); ?></h6>
                <small class="text-muted"><?php echo count($data['items']); ?> planos</small>
            </div>
        </div>
        <div class="small" style="gap:8px; display:flex;">
            <span class="bpo-tag"><i class="fas fa-scroll mr-1"></i>BPO: <?php echo $data['BPO'] ?? 0; ?></span>
            <span class="bpc-tag"><i class="fas fa-copy mr-1"></i>BPC: <?php echo $data['BPC'] ?? 0; ?></span>
        </div>
    </div>

    <table class="table table-sm table-blueprints">
        <thead>
            <tr>
                <th class="row-num text-center">#</th>
                <th><i class="fas fa-file-alt mr-1"></i>Blueprint</th>
                <th class="text-center">Tipo</th>
                <th class="text-center">Runs</th>
                <th class="text-center">ME / TE</th>
            </tr>
        </thead>
        <tbody>
            <?php $n = 1; foreach ($data['items'] as $item): ?>
            <tr>
                <td class="row-num"><?php echo str_pad($n++, 2, "0", STR_PAD_LEFT); ?></td>
                <td class="text-white font-weight-bold"><?php echo htmlspecialchars($item['nombre']); ?></td>
                <td class="text-center">
                    <span class="<?php echo ($item['tipo'] === 'BPO') ? 'bpo-tag' : 'bpc-tag'; ?>">
                        <?php echo $item['tipo']; ?>
                    </span>
                </td>
                <td class="text-center">
                    <?php if ($item['tipo'] === 'BPO'): ?>
                        <span class="runs-inf">&infin;</span>
                    <?php else: ?>
                        <?php echo $item['runs']; ?>
                    <?php endif; ?>
                </td>
                <td class="text-center efficiency">
                    <?php echo $item['me']; ?>% / <?php echo $item['te']; ?>%
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endforeach; ?>

<?php if (empty($reporteGlobal)): ?>
<div class="alert alert-dark border border-secondary text-center py-5">
    <i class="fas fa-drafting-compass fa-2x mb-3 text-muted d-block"></i>
    No se encontraron blueprints para los pilotos activos.
</div>
<?php endif; ?>

<?php endif; // fin blueprints ?>

</div><!-- /container-fluid -->
</div><!-- /tab-body -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleAllPockets(checkbox) {
    document.querySelectorAll('.pocket-checkbox').forEach(cb => cb.checked = checkbox.checked);
}
</script>
<?php echo ui_footer(); ?>
</body>
</html>
