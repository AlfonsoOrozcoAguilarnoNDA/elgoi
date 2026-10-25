<?php
// =====================================================================
// EVE BLUEPRINTS — Live ESI blueprint inventory per pilot
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
// ESI BLUEPRINTS — Constants and functions
// =====================================================================
// NOTE: variable names below are kept exactly as in the original script
// (they work in production), even though they look swapped.
list($fleet_token)=avalues319b("select token_fleet from fleet_config");
list($fleet_client)=avalues319b("select clientid_fleet from fleet_config");
define('CLIENT_ID', $fleet_token);
define('CLIENT_SECRET', $fleet_client);

//define('CALLBACK_URL',  'https://elgoi.com/devauthcallback.php');
define('ESI_BASE_URL',  'https://esi.evetech.net/latest');
//define('AUTH_URL',      'https://login.eveonline.com/v2/oauth/authorize/');
define('TOKEN_URL',     'https://login.eveonline.com/v2/oauth/token');

// Blueprint type_ids that must be skipped in the report
$ignored_blueprints = [
    77526, // Industrialist - Hauler Blueprint Crate
];

// Generic cURL wrapper — returns ['code' => HTTP status, 'body' => response]
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

// Exchange a refresh token for a new access token
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

// Persist the refreshed tokens in the PILOTS table
function saveTokenToDB($tokenData, $character_id) {
    global $link;
    $at = mysqli_real_escape_string($link, $tokenData['access_token']);
    $rt = mysqli_real_escape_string($link, $tokenData['refresh_token']);
    mysqli_query($link, "UPDATE PILOTS SET token20min='$at', refreshtoken='$rt', daterefresh=DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE toon_number=$character_id");
    return true;
}

// Call an ESI endpoint for a character; on 401 refresh the token and retry once
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
// BLUEPRINTS — data processing
// =====================================================================
$reporteGlobal   = [];
$totalesGlobales = ['BPO' => 0, 'BPC' => 0];
$totalPilotosConPlanos = 0;

// Pilots that own at least one blueprint asset (excluding specific toons)
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

        // runs == -1 means original (BPO); anything else is a copy (BPC)
        $esBPO = ($bp['runs'] == -1);
        $tipo  = $esBPO ? 'BPO' : 'BPC';
        $tID   = $bp['type_id'];

        // Resolve the blueprint name from the assets table
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

// =====================================================================
// HTML
// =====================================================================
echo ui_header("EVE Blueprints — " . $nombre_archivo);
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EVE Blueprints</title>
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

        /* ── CONTENT ── */
        .tab-body { padding: 25px 15px; }

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

<div class="tab-body">
<div class="container-fluid">

<!-- ================================================================
     BLUEPRINTS — ESI LIVE
     ================================================================ -->

<div class="bp-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h4>
            <i class="fas fa-drafting-compass mr-2"></i>Blueprint Inventory (ESI Live)
        </h4>
        <div class="d-flex align-items-center" style="gap:8px;">
            <span class="badge-pilotos">
                <i class="fas fa-users mr-1"></i><?php echo $totalPilotosConPlanos; ?> pilots with blueprints
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
                <small class="text-muted"><?php echo count($data['items']); ?> blueprints</small>
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
                <th class="text-center">Type</th>
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
    No blueprints found for the active pilots.
</div>
<?php endif; ?>

</div><!-- /container-fluid -->
</div><!-- /tab-body -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php echo ui_footer(); ?>
</body>
</html>
