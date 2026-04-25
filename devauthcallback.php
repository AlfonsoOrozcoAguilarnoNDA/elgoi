<?php
/**
 * EVE Online ESI Integration - Versión Simplificada
 * No es proceso temrinado. Version al 25 abril 2026
 * PHP 8.x con Bootstrap 4.5
 * Almacenamiento en Base de Datos (sin JSON)
 * GPL 3.0
 * Alfonso Orozco Aguilar
 * 
 * Cambios principales:
 * - Tokens guardados en BD (token20min, refreshtoken, daterefresh)
 * - Una sola función genérica getCharacterData()
 * - Eliminado saveTokenToFile() y loadTokenFromFile()
 * - Soporte para jerarquía de pilotos (parent_toon_number)
 * - Autenticación por IP
 * - Combos agrupados por email_pilot
 */

session_start();
ini_set('output_buffering', '4096');
error_reporting(E_ALL);
ini_set('display_errors', '1');

include('config.php'); // Incluye autenticación IP y conexión BD
//check_authorization(); // Valida IP autorizada

//require_once('secret.php'); // Comentado - funciones por verificar

// Configuración ESI
list($fleet_token)=avalues319b("select token_fleet from fleet_config");
list($fleet_client)=avalues319b("select clientid_fleet from fleet_config");
define('CLIENT_ID', $fleet_token);
define('CLIENT_SECRET', $fleet_client);

//define('CALLBACK_URL', 'https://elgoi.com/devauthcallback.php');

// Detectamos si es http o https
$protocol = $_SERVER['REQUEST_SCHEME'] ?? 'https';

// Obtenemos el dominio actual (ej: miservidor.com o localhost)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Definimos la constante de forma dinámica
define('CALLBACK_URL', "$protocol://$host/devauthcallback.php");

define('ESI_BASE_URL', 'https://esi.evetech.net/latest');
define('AUTH_URL', 'https://login.eveonline.com/v2/oauth/authorize/');
define('TOKEN_URL', 'https://login.eveonline.com/v2/oauth/token');

// IDs de pilotos históricos que NO deben ser actualizados
define('HISTORICAL_PILOTS', '2122782650, 2122783972, 2122782609');

// Scopes necesarios
$SCOPES = [
    'esi-skills.read_skills.v1',
    'esi-skills.read_skillqueue.v1',
    'esi-characters.read_standings.v1',
    'esi-assets.read_assets.v1',
    'esi-industry.read_character_jobs.v1',
    'esi-planets.manage_planets.v1',
    'esi-wallet.read_character_wallet.v1',
    'esi-location.read_location.v1',
    'esi-location.read_ship_type.v1',
    'esi-characters.read_contacts.v1',
    'esi-characters.read_notifications.v1',
    'esi-fittings.read_fittings.v1',
    'esi-characters.read_blueprints.v1', // <--- Agregado
    'esi-clones.read_implants.v1',      // <--- Tenía una coma faltante
    'esi-clones.read_clones.v1',
    'esi-mail.read_mail.v1'
];


// ============================================================================
// FUNCIONES DE TOKEN - GUARDADO Y CARGA DESDE BD
// ============================================================================

/**
 * Guarda el token en la base de datos
 */
function saveTokenToDB($tokenData, $character_id) {
    global $link;
    
    $access_token = mysqli_real_escape_string($link, $tokenData['access_token']);
    $refresh_token = mysqli_real_escape_string($link, $tokenData['refresh_token']);
    
    // El access_token expira en 20 minutos
    $sql = "UPDATE PILOTS SET 
            token20min = '$access_token',
            refreshtoken = '$refresh_token',
            daterefresh = DATE_ADD(NOW(), INTERVAL 20 MINUTE)
            WHERE toon_number = $character_id";
    
    list($dummy) = aValues319b($sql);
    
    echo "<div class='alert alert-success'>Token guardado en BD para piloto $character_id</div>";
    return true;
}

/**
 * Carga el token desde la base de datos
 */
function loadTokenFromDB($character_id) {
    list($access_token, $refresh_token, $daterefresh, $char_name) = aValues319b(
        "SELECT token20min, refreshtoken, daterefresh, toon_name 
         FROM PILOTS 
         WHERE toon_number = $character_id"
    );
    
    if (empty($access_token) || empty($refresh_token)) {
        echo "<div class='alert alert-warning'>No hay token en BD para piloto $character_id</div>";
        return false;
    }
    
    $tokenData = [
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'character_id' => $character_id,
        'character_name' => $char_name,
        'expires_at' => $daterefresh
    ];
    
    echo "<div class='alert alert-info'>Token cargado desde BD para: $char_name (ID: $character_id)</div>";
    return $tokenData;
}

// ============================================================================
// FUNCIONES HTTP Y ESI
// ============================================================================

function makeHttpRequest($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST' && $data !== null) {
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['code' => 0, 'body' => 'Error cURL: ' . $error];
    }
    
    return ['code' => $httpCode, 'body' => $response];
}

/**
 * Obtiene token usando código de autorización
 */
function getAuthorizationToken($auth_code) {
    $authHeader = base64_encode(CLIENT_ID . ':' . CLIENT_SECRET);
    
    $headers = [
        'Authorization: Basic ' . $authHeader,
        'Content-Type: application/x-www-form-urlencoded'
    ];
    
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $auth_code
    ];
    
    $response = makeHttpRequest(TOKEN_URL, 'POST', $headers, $data);
    
    if ($response['code'] != 200) {
        echo "<div class='alert alert-danger'>Error al obtener token: {$response['code']}<br>{$response['body']}</div>";
        return false;
    }
    
    $tokenData = json_decode($response['body'], true);
    
    // Decodificar JWT para obtener character_id
    $tokenParts = explode('.', $tokenData['access_token']);
    $payload = str_pad(strtr($tokenParts[1], '-_', '+/'), strlen($tokenParts[1]) % 4, '=', STR_PAD_RIGHT);
    $jwtData = json_decode(base64_decode($payload), true);
    
    $characterIdParts = explode(':', $jwtData['sub']);
    $characterId = end($characterIdParts);
    
    $tokenData['character_id'] = $characterId;
    $tokenData['character_name'] = $jwtData['name'];
    
    return $tokenData;
}

/**
 * Refresca el token usando refresh_token
 */
function refreshToken($tokenData) {
    if (!isset($tokenData['refresh_token'])) {
        echo "<div class='alert alert-danger'>No hay refresh token disponible</div>";
        return false;
    }
    
    $authHeader = base64_encode(CLIENT_ID . ':' . CLIENT_SECRET);
    
    $headers = [
        'Authorization: Basic ' . $authHeader,
        'Content-Type: application/x-www-form-urlencoded'
    ];
    
    $data = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokenData['refresh_token']
    ];
    
    $response = makeHttpRequest(TOKEN_URL, 'POST', $headers, $data);
    
    if ($response['code'] != 200) {
        echo "<div class='alert alert-danger'>Error al refrescar token: {$response['code']}<br>{$response['body']}</div>";
        return false;
    }
    
    $newTokenData = json_decode($response['body'], true);
    
    // Conservar refresh_token si no viene en la respuesta
    if (!isset($newTokenData['refresh_token'])) {
        $newTokenData['refresh_token'] = $tokenData['refresh_token'];
    }
    
    // Conservar info del personaje
    $newTokenData['character_id'] = $tokenData['character_id'];
    $newTokenData['character_name'] = $tokenData['character_name'];
    
    echo "<div class='alert alert-success'>Token refrescado exitosamente</div>";
    return $newTokenData;
}

// ============================================================================
// FUNCIÓN GENÉRICA PARA OBTENER DATOS DE PERSONAJE
// ============================================================================

/**
 * Función genérica para obtener cualquier dato de un personaje
 * Reemplaza todas las funciones getCharacterXXXJSON()
 * 
 * @param array $tokenData Datos del token
 * @param string $endpoint Endpoint ESI (ej: '/characters/{character_id}/wallet/')
 * @return string|false JSON con los datos o false en error
 */
function getCharacterData($tokenData, $endpoint) {
global $link;
    if (!isset($tokenData['character_id'])) {
        echo "<div class='alert alert-danger'>No se ha autenticado ningún personaje</div>";
        return false;
    }
    
    // Reemplazar {character_id} en el endpoint
    $endpoint = str_replace('{character_id}', $tokenData['character_id'], $endpoint);
    
    $headers = [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $url = ESI_BASE_URL . $endpoint;
    $response = makeHttpRequest($url, 'GET', $headers);
    
    // Si el token expiró, intentar refrescar
    if ($response['code'] == 401) {
        echo "<div class='alert alert-warning'>Token expirado, refrescando...</div>";
        $newTokenData = refreshToken($tokenData);
        
        if ($newTokenData) {
            saveTokenToDB($newTokenData, $tokenData['character_id']);
            
            // Reintentar con nuevo token
            $headers[0] = 'Authorization: Bearer ' . $newTokenData['access_token'];
            $response = makeHttpRequest($url, 'GET', $headers);
        } else {
            return false;
        }
    }
    
    if ($response['code'] < 200 || $response['code'] >= 300) {
        echo "<div class='alert alert-danger'>Error ESI $endpoint: {$response['code']}<br>{$response['body']}</div>";
        return false;
    }
    
    return addslashes($response['body']);
    //mysqli_real_escape_string($link,$response['body']);
    //mysqli_real_escape_string(
}

// ============================================================================
// FUNCIÓN PARA ACTUALIZAR CAMPO EN BD
// ============================================================================

function updatePilotField($field, $value, $pilotId) {
global $link;        
    $value = mysqli_real_escape_string($link, $value);
    $sql = "UPDATE PILOTS SET $field='$value' WHERE toon_number='$pilotId'";
    
    list($dummy) = aValues319b($sql);
    echo "<li><b>$field</b> actualizado</li>";
}

// ============================================================================
// FUNCIÓN PRINCIPAL: MOSTRAR INFO Y ACTUALIZAR PILOTO
// ============================================================================

function displayCharacterInfo($tokenData) {
    if (!isset($tokenData['character_id']) || !isset($tokenData['character_name'])) {
        echo "<div class='alert alert-danger'>No hay información del personaje</div>";
        return;
    }
    
    $mytoonid = $tokenData['character_id'];
    $name = mysqli_real_escape_string($GLOBALS['link'], $tokenData['character_name']);
    
    echo "<div class='card mt-3'>";
    echo "<div class='card-header bg-primary text-white'><h3>Personaje: $name (ID: $mytoonid)</h3></div>";
    echo "<div class='card-body'>";
    
    // Verificar si existe en BD
    list($exists) = aValues319b("SELECT COUNT(*) FROM PILOTS WHERE toon_number='$mytoonid'");
    
    if ($exists == 0) {
        // Insertar nuevo piloto
        $sql = "INSERT INTO PILOTS (toon_number, toon_name, ownertoken, lastsaved, email_pilot, DOB, parent_toon_number)
                VALUES ($mytoonid, '$name', 'ownerHash', NOW(), 'me', '1969-12-31', 0)";
        list($dummy) = aValues319b($sql);
        echo "<div class='alert alert-success'>Piloto $name guardado en BD</div>";
    } else {
        // Actualizar piloto existente
		// we blank assets because maybe the cha have much in the past and contract to other char.
        $sql = "UPDATE PILOTS SET toon_name='$name', lastsaved=NOW(),assets='',assets2='',assets3='',assets4='',assets5='' WHERE toon_number='$mytoonid'";
        list($dummy) = aValues319b($sql);
        echo "<div class='alert alert-info'>Piloto $name actualizado en BD</div>";
    }
    
    // Guardar token en BD
    saveTokenToDB($tokenData, $mytoonid);
    
    echo "<h4>Actualizando datos del personaje...</h4>";
    echo "<ul class='list-unstyled'>";
    
    // Usar la función genérica para obtener todos los datos
    $endpoints = [
        'general' => '/characters/{character_id}/',
        'queue' => '/characters/{character_id}/skillqueue/',
        'wallet' => '/characters/{character_id}/wallet/',        
        'fittings'    => '/characters/{character_id}/fittings/', // Guardar en campo 'fittings' o 'character_shipfits'
        'journal' => '/characters/{character_id}/wallet/journal/',
        'transactions' => '/characters/{character_id}/wallet/transactions/',
        'attrib' => '/characters/{character_id}/attributes/',
        'skills' => '/characters/{character_id}/skills/',
        'current_ship' => '/characters/{character_id}/ship/',
        'current_location' => '/characters/{character_id}/location/',
        'planets' => '/characters/{character_id}/planets/',
        'standings' => '/characters/{character_id}/standings/',
        'contacts' => '/characters/{character_id}/contacts/',
        'corp_story' => '/characters/{character_id}/corporationhistory/',
        'notifications' => '/characters/{character_id}/notifications/',
        'mails' => '/characters/{character_id}/mail/',
        'jobs' => '/characters/{character_id}/industry/jobs/'
    ];
    
    foreach ($endpoints as $field => $endpoint) {
        $data = getCharacterData($tokenData, $endpoint);
        if ($data !== false) {
            updatePilotField($field, $data, $mytoonid);
        }
    }
    
    // Assets (5 páginas)
    for ($page = 1; $page <= 5; $page++) {
        $field = ($page == 1) ? 'assets' : "assets$page";
        $data = getCharacterData($tokenData, "/characters/{character_id}/assets/?page=$page");
        if ($data !== false) {
            updatePilotField($field, $data, $mytoonid);
        }
    }
    
    echo "</ul>";
    echo "</div>";
    echo "<div class='card-footer text-center'>";
    $num=$mytoonid;
    echo "<div class='alert alert-info'>Actualizando Datos Generales 1/2</div>";
    echo generales($num); // update general data
    echo "<div class='alert alert-info'>Actualizando Datos Generales 2/2</div>";
    echo generales2($num); // update general data atributes
    echo "<div class='alert alert-info'>Actualizando queue</div>";
    charqueue($num);
    echo "<div class='alert alert-info'>Actualizando Standings</div>";
    standings($num);
    echo "<div class='alert alert-info'>Actualizando Skills</div>";
    charskills(); // update skills
    
    $authUrl = generateAuthUrl($GLOBALS['SCOPES']);
    echo "<a href='$authUrl' class='btn btn-primary'><img src='evesso.png' style='max-width:200px;' alt='Autorizar otro piloto'></a>";
    echo "<a href='?' class='btn btn-secondary ml-2'>Volver al inicio</a>";
    
    echo "</div></div>";
    
    $_SESSION['loading'] = $mytoonid;
    
    // Funciones adicionales (mantenlas si las tienes)
    updatepanelnagual();
    charskills(); // hace explotar los datos
}

// ============================================================================
// FUNCIÓN PARA GENERAR URL DE AUTORIZACIÓN
// ============================================================================

function generateAuthUrl($scopes) {
    $state = bin2hex(random_bytes(8));
    
    $params = [
        'response_type' => 'code',
        'redirect_uri' => CALLBACK_URL,
        'client_id' => CLIENT_ID,
        'scope' => implode(' ', $scopes),
        'state' => $state
    ];
    return AUTH_URL . '?' . http_build_query($params);
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================


function updatepanelnagual(){
// update panels nagual
global $link;

$sql="update PILOTS SET email_pilot='redrodac@gmail.com'";
 // where toon_name not in ('Nokia Catalog','Experiment Catalog','All Others Catalog')
    
  $rs= mysqli_query($link,$sql) or die("error showm - 644".mysqli_error($link));   

       
  $sql="update PILOTS SET email_pilot='whiteknight@hostchess.com' where toon_name in   ( 
  -- '96400','Dallas Riordan','Eva Levett',  
  -- 'Karla Sofen','Abner Jenkins','Erik Josten',
  -- 'Dakota Wallace','Morrigan Salot','Procurer Veldspar',
  -- 'Woo Soo-ji','Daisy Wallace','Procurer Scordite',
  -- 'Lady Maurasi','Lady Sobaseki','Procurer Plagioclase',  	
  'VPS 01','VPS 02','VPS 03',
  'VPS 04','VPS 05','VPS 06',
  'VPS 07','VPS 08','VPS 09',
  'VPS 010','VPS 011','VPS 012',
  'VPS 13','VPS 14','VPS 15',
  'VPS 16','VPS 17','VPS 18',
  'VPS 19','VPS 20','VPS 21',
  'VPS 22','VPS 23','VPS 24',
  'VPS 25','VPS 26','VPS 27',
  'VPS 28','VPS 29','VPS 30',  
  'VPS 31','VPS 32','VPS 33',
  'VPS 34','VPS 35','VPS 36',
  'VPS 37','VPS 38','VPS 39',
  'VPS 40','VPS 41','VPS 42',
  'VPS 43','VPS 44','VPS 45',
  'VPS 46','VPS 47','VPS 48',
  'VPS 49','VPS 50','VPS 51',
  'VPS 52','VPS 53','VPS 54',
  'VPS 55','VPS 56','VPS 57',
  'VPS 58','VPS 59','VPS 60',
  'VPS 01'    
   )";

     
  $rs= mysqli_query($link,$sql) or die("error showm - 60".mysqli_error($link));    

  
  //die("88");    
// updates panel nagual end
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online ESI - Gestión de Pilotos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { 
            padding: 20px; 
            background-color: #f8f9fa;
        }
        .eve-logo { max-width: 200px; }
        .email-group {
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
            padding-left: 15px;
        }
        .pilot-count {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <h1><i class="fas fa-shuttle"></i> EVE Online ESI - Gestión de Pilotos</h1>
        </div>
        
        <?php
        // Procesar código de autorización
        if (isset($_GET['code'])) {
            $authCode = $_GET['code'];
            $tokenData = getAuthorizationToken($authCode);
            
            if ($tokenData) {
                echo "<div class='alert alert-success'><h4><i class='fas fa-check-circle'></i> Autenticación exitosa!</h4>";
                echo "<p>Personaje: <strong>{$tokenData['character_name']}</strong></p></div>";
                
                displayCharacterInfo($tokenData);
            } else {
                echo "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error en autenticación. <a href='?'>Reintentar</a></div>";
            }
        } 
        // Cargar piloto existente por ID
        elseif (isset($_GET['pilot_id'])) {
            $pilot_id = intval($_GET['pilot_id']);
            $tokenData = loadTokenFromDB($pilot_id);
            
            if ($tokenData) {
                displayCharacterInfo($tokenData);
            } else {
                echo "<div class='alert alert-warning'><i class='fas fa-exclamation-circle'></i> Token no válido para piloto $pilot_id. <a href='?'>Autorizar de nuevo</a></div>";
            }
        } 
        // Pantalla inicial con combos por email
        else {
            echo "<div class='card'>";
            echo "<div class='card-header bg-dark text-white'>";
            echo "<h3><i class='fas fa-users'></i> Selecciona un Piloto para Actualizar</h3>";
            echo "</div>";
            echo "<div class='card-body'>";
            
            // Obtener emails únicos con pilotos válidos
            $sql = "SELECT DISTINCT email_pilot 
                    FROM PILOTS 
                    WHERE token20min IS NOT NULL 
                    AND token20min != ''
                    AND toon_number NOT IN (" . HISTORICAL_PILOTS . ")
                    ORDER BY email_pilot";
            
            $rs = mysqli_query($link, $sql);
            
            if ($rs && mysqli_num_rows($rs) > 0) {
                
                // Botón SSO principal
                $authUrl = generateAuthUrl($SCOPES);
                echo "<div class='text-center mb-4'>";
                echo "<a href='$authUrl'><img src='evesso.png' class='eve-logo' alt='Autorizar nuevo piloto'></a>";
                echo "<p class='text-muted mt-2'>Autoriza un nuevo piloto o re-autoriza uno existente</p>";
                echo "</div>";
                
                echo "<hr>";
                echo "<h4 class='mb-3'><i class='fas fa-list'></i> Actualizar Pilotos Existentes</h4>";
                
                $groupColors = ['primary', 'success', 'info', 'warning', 'danger'];
                $colorIndex = 0;
                
                while ($emailRow = mysqli_fetch_assoc($rs)) {
                    $email = $emailRow['email_pilot'];
                    
                    // Obtener pilotos de este email
                    $sqlPilots = "SELECT toon_number, toon_name, daterefresh, token20min, refreshtoken 
                                  FROM PILOTS 
                                  WHERE email_pilot = '" . mysqli_real_escape_string($link, $email) . "'
                                  AND toon_number NOT IN (" . HISTORICAL_PILOTS . ")
                                  ORDER BY toon_name";
                    
                    $rsPilots = mysqli_query($link, $sqlPilots);
                    $pilotCount = 0;
                    $pilotsArray = [];
                    
                    // Procesar pilotos y contar solo los que tienen token válido o refrescable
                    while ($pilot = mysqli_fetch_assoc($rsPilots)) {
                        $hasToken = !empty($pilot['token20min']) && $pilot['token20min'] !== 'token';
                        $hasRefreshToken = !empty($pilot['refreshtoken']);
                        
                        if ($hasToken || $hasRefreshToken) {
                            $pilotsArray[] = $pilot;
                            $pilotCount++;
                        }
                    }
                    
                    if ($pilotCount > 0) {
                        $borderColor = $groupColors[$colorIndex % count($groupColors)];
                        $colorIndex++;
                        
                        echo "<div class='email-group border-{$borderColor}'>";
                        echo "<h5><i class='fas fa-envelope'></i> {$email}</h5>";
                        
                        echo "<form method='get' class='form-inline'>";
                        echo "<div class='form-group mr-2'>";
                        echo "<select name='pilot_id' class='form-control' required style='min-width: 350px;'>";
                        echo "<option value=''>-- Selecciona un piloto --</option>";
                        
                        foreach ($pilotsArray as $pilot) {
                            // Determinar estado del token
                            $tokenStatus = "";
                            
                            if (empty($pilot['token20min']) || $pilot['token20min'] === 'token') {
                                $tokenStatus = " ❌ Token inexistente";
                            } elseif (strtotime($pilot['daterefresh']) < time()) {
                                if (!empty($pilot['refreshtoken'])) {
                                    $tokenStatus = " ⚠️ Token expirado (se refrescará)";
                                } else {
                                    $tokenStatus = " ❌ Token expirado sin refresh";
                                }
                            } else {
                                $tokenStatus = " ✓ Token válido";
                            }
                            
                            echo "<option value='{$pilot['toon_number']}'>{$pilot['toon_name']}{$tokenStatus}</option>";
                        }
                        
                        echo "</select>";
                        echo "</div>";
                        echo "<button type='submit' class='btn btn-{$borderColor}'><i class='fas fa-sync-alt'></i> Actualizar</button>";
                        echo "</form>";
                        
                        echo "<div class='pilot-count'><i class='fas fa-user-friends'></i> {$pilotCount} piloto(s) encontrado(s)</div>";
                        echo "</div>";
                    }
                }
                
            } else {
                echo "<div class='alert alert-info'>";
                echo "<i class='fas fa-info-circle'></i> No hay pilotos con token guardado. ";
                echo "Usa el botón de arriba para autorizar tu primer piloto.";
                echo "</div>";
                
                $authUrl = generateAuthUrl($SCOPES);
                echo "<div class='text-center'>";
                echo "<a href='$authUrl'><img src='evesso.png' class='eve-logo' alt='Autorizar piloto'></a>";
                echo "</div>";
            }
            
            echo "</div></div>";
        }
        ?>
    </div>    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
function generales($who){
//if ($who=='') $who=valpilot($who);
list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,general from PILOTS where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (13)");
$name=addslashes($name);
//if ($pilot<>$_SESSION['youremail']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (13)");
//echo "<h3>General info of pilot $name</h3>";
if ($data=='[]') die("no data generals for $name");
$data=stripslashes($data);
$man=json_decode($data);
//$ancestry=$man->ancestry_id;
$tribal=$man->race_id;

$corp=$man->corporation_id;
$DOB=left($man->birthday,10);
$sec=cfdinumbers($man->security_status);
$blood=$man->bloodline_id;
//list($ancestry)=avalues319("select ancestryName from chrAncestries where ancestryID='$ancestry'");
list($tribal)=avalues319("select raceName from chrRaces where raceID='$tribal'");
list($blood)=avalues319("select bloodlineName from chrBloodlines where bloodlineID='$blood'");

$sql="update PILOTS set blood='$blood',corporation='$corp',race='$tribal',DOB='$DOB',security='$sec' where toon_number=$who";
doaction($sql,"error updating general data for $who");
} // generales
function aValues319($Qx){
global $link;    
    $rsX = mysqli_query($link,$Qx); sqlerror("error checking avalues<hr>$Qx"); //  or die("<hr>Avalues 319<hr>$Qx");
    $Qx2=strtolower($Qx);
    if (left($Qx2,6)<>'select') return "";    
    $aDataX = array();
    $rows=mysqli_num_rows($rsX);
    if ($rows==0) return array("",""); 
        
        $Campos = mysqli_num_fields($rsX);
        while ($regX = mysqli_fetch_array($rsX)) {
            for($iX=0; $iX<$Campos; $iX++){
               $finfo=mysqli_fetch_field_direct($rsX,$iX);
               $name=$finfo->name;
                $aDataX[] = $regX[ $name ];
            }
        }
      // echo ($Qx ."/". $aDataX[0]);
    return $aDataX;
}
function sqlerror($message){
global $link;
$error=mysqli_error($link);
if ($error=='') return; 
die ("$message<hr>$error");
}
function left($str, $length) {
     return substr($str, 0, $length);
}

function right($str, $length) {
     return substr($str, -$length);
}
function CFDINumbers($val, $p = 2) {
// requiere bcmath
// https://stackoverflow.com/questions/9944001/delete-digits-after-two-decimal-points-without-rounding-the-value

//echo "<li>$val";
if ($val==0) return "0.00";
$res=bcdiv($val, 1, 2);
//echo "<li>$val $res";
return $res;
}
function doaction($sql,$errormessage){
global $link;
mysqli_query($link,$sql);
sqlerror("$errormessage<hr>$sql");
}
function generales2($who){
//if ($who=='') $who=valpilot($who);
list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,attrib from PILOTS where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (14)");
$name=addslashes($name);
//if ($pilot<>$_SESSION['youremail']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (14)");
//echo "<h3>General info of pilot $name</h3>";
if ($data=='[]') die("no data generals2 for $name");

/*echo "<pre>";
print_r($data);
echo "</pre><hr>";
die();
*/
$data=stripslashes($data);
$man=json_decode($data);
/*
if (!isset($man->name)) {
     echo "<pre>";
      print_r($man);
      echo "</pre>";
      echo "Esi is not returning NAME in character";
      die();
}      
*/        
$remaps=$man->bonus_remaps;
//$name=$man->name;
$cha=$man->charisma;
$int=$man->intelligence;
$mem=$man->memory;
$per=$man->perception;
$wil=$man->willpower;
if ($cha<>''){
  $sql="update PILOTS set remaps=$remaps,cha='$cha',inteli='$int',mem='$mem',per='$per',wil='$wil' where toon_number=$who";
  doaction($sql,"error updating general attributes for $name");
}
} // generales2
function charqueue(){
$loadim=$_SESSION['loading'] ?? "";
$todate="";
$who=valpilot("CHECK");
if ($loadim<>'') $who=$loadim;
$m=$_GET['module'] ?? "";
if ($m==113) $who=$_GET['t'];
$pilo=$_GET['pilot_id'] ?? 0;
if ($pilo>0) $who= $pilo;

list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,queue from PILOTS where toon_number='$who'");
//if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (2)");
if ($data == null) $data='[]';
$name=addslashes($name);
//if ($pilot<>$_SESSION['youremail']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (2)");
if ($data=='[]' and $loadim<>'') return ;
if ($data=='[]') return "[]";;

/*
echo "<pre>";
print_r($data);
echo "</pre><hr>";
*/
$data=stripslashes($data);
$xml=json2xml($data);
$xml = new SimpleXMLElement($xml);

/*echo "<pre>";
var_dump($xml);
echo "</pre>";
*/

$pass= css()."<table class='hovertable'><tr><th>#</th><th style='display:none'>queue position</th><th>real Sp</th><th>goal sp/lvl</th><th>skill</th><th>Description</th><th>start date</th><th>End date</th><th colspan=3>stats</th><th>Need SP</th><th>alpha/Omega</th></tr>";
$csh=0;
$totalsum=0;
$extra2=0;
$sql='';
foreach($xml->item as $item)
{   $csh ++;
    $pass .="<tr><th>$csh</th>";
    $pass .="<td style='display:none'>$item->queue_position</td>";    
    
    
    $pass .="<td>$item->level_start_sp</td>";
    $pass .="<td>$item->level_end_sp<br /><br />lvl $item->finished_level</td>";       
    $pass .="<td>$item->skill_id</td>";
    
    list($maxalpha)=avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$item->skill_id'");
    if ($maxalpha=="") $maxalpha=0;
    $greek="Alpha";
    if ($item->finished_level>$maxalpha) $greek="Omega";    
    
    //list($description)=avalues319("select typeName from invTypes where typeID='$item->skill_id'");
    $description=description($item->skill_id);
        $pass .="<td>$description</td>";
    
    $fromdate=$item->start_date;
    $todate=left($item->finish_date,19);
    $pass .="<td>$fromdate</td>";
    $pass .="<td>$todate</td>";
    
    $from_time = strtotime($fromdate);
    $to_time = strtotime($todate);

$minutes2 =round(abs($to_time - $from_time) / 60,2);
  $totalsum += $minutes2; 
  $minutes=$minutes2;
  $days=0;
  $hours=0;
  $sph=0;
  if ($minutes>"" and $minutes<>0) $sph=cfdinumbers(($item->level_end_sp-$item->level_start_sp)/($minutes/60));
  if ($minutes=="") $sph=0;
  while ($minutes>=1440){
    $days ++;
    $minutes -= 1440;
  }
  while ($minutes>=60){
    $hours ++;
    $minutes -= 60;
  }
  $minutes=number_format($minutes,0);
  
  if ($hours<10) $hours="0$hours";
  if ($minutes2<10) $minutes2="0$minutes";  
   
   $elapsed="$days d $hours h $minutes m";
   if (0>$minutes2) $elapsed='NEGATIVE';
   if (left($elapsed,3)=="0 d") $elapsed=str_replace("0 d","",$elapsed);
   if (left($elapsed,4)=="00 h") $elapsed=str_replace("00 h","",$elapsed);
      
    //$elapsed="elapsed";
    $pass .="<td>$elapsed</td>";
    $pass .="<td>$sph</td>";
    list($realhave)= avalues319("select max(skillpoints) from EVE_CHARSKILLS where typeID='$item->skill_id' and toon =$who"); 
    if ($realhave<$item->level_start_sp) $extrapoints = $item->level_end_sp - $item->level_start_sp;
    if ($realhave>= $item->level_start_sp) $extrapoints = $item->level_end_sp - $realhave;
    if ($sph>1500) $sql="OMEGA";    
    $pass .=typea($item->skill_id);
    $pass .= "<td  align=right>$extrapoints</td>";    
    $extra2 += $extrapoints;
    $pass .="<td>$greek</td>";
    $pass .="</tr>";    
        
}
//echo "<h1>$csh</h1>";
$pass .="</table>";
$tdays=0;
$th=cfdinumbers($totalsum /60);
$td=cfdinumbers($th/24);
        
        $pass .="<br>Total Hours:$th<br> days=$td";
        if ($extra2<>"") $pass .="<br>Extra Points when finished : $extra2 | " . cfdinumbers($extra2/1000000)."m sp"; 
$pass .=footboot();
//$todate="";
list($dummy)=avalues319("update PILOTS set daysq=$td where toon_number='$who'");
if ($sql=='OMEGA') $sql="update PILOTS set acctype='Omega',finishqueue='$todate' where toon_number='$who'";
if ($sql=='') {
if ($todate=='') {
  $todate='0000-00-00';
  //die($who);
  }
  $sql="update PILOTS set finishqueue='$todate' where toon_number='$who'";
}  
doaction($sql,"error calculating queue");
//if ($loadim=='')  die($pass);
$m=$_GET['module'] ?? "";
if ($m==113) {
  $pass =css().topboot()."<h3>Queue of $name</h3>$pass";
  return $pass;
  }
} // charqueue
function valpilot($def){
if ($def<>''){
  if (is_numeric($def)) return $def;
}
$forc="";
if (isset($_SESSION['forcetoon'])) {  
  $forc =$_SESSION['forcetoon'];
}  
$t=$forc;
$lete="";
if (isset($_GET['t'])) {
  $lete=$_GET['t'];
  if ($lete<>'') $t=$lete;
}
if ($forc=='') $t=$lete;  
$who=addslashes($t);
return $who;
} // valpilot
function json2xml($json) {
// Copyright: Maurits van der Schee <maurits@vdschee.nl>
// Description: Convert from JSON to XML and back.
// License: MIT
    $a = json_decode($json);
    $d = new DOMDocument();
    $c = $d->createElement("root");
    $d->appendChild($c);
    $t = function($v) {
        $type = gettype($v);
        switch($type) {
            case 'integer': return 'number';
            case 'double':  return 'number';
            default: return strtolower($type);
        }
    };
    $f = function($f,$c,$a,$s=false) use ($t,$d) {
        $c->setAttribute('type', $t($a));
        if ($t($a) != 'array' && $t($a) != 'object') {
            if ($t($a) == 'boolean') {
                $c->appendChild($d->createTextNode($a?'true':'false'));
            } else {
                if (!is_null($a)) $c->appendChild($d->createTextNode($a));
            }
        } else {
            foreach($a as $k=>$v) {
                if ($k == '__type' && $t($a) == 'object') {
                    $c->setAttribute('__type', $v);
                } else {
                    if ($t($v) == 'object') {
                        $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                        $f($f, $ch, $v);
                    } else if ($t($v) == 'array') {
                        $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                        $f($f, $ch, $v, true);
                    } else {
                        $va = $d->createElementNS(null, $s ? 'item' : $k);
                        if ($t($v) == 'boolean') {
                            $va->appendChild($d->createTextNode($v?'true':'false'));
                        } else {
                            $va->appendChild($d->createTextNode($v));
                        }
                        $ch = $c->appendChild($va);
                        $ch->setAttribute('type', $t($v));
                    }
                }
            }
        }
    };
    $f($f,$c,$a,$t($a)=='array');
    return $d->saveXML($d->documentElement);
} //json2xml
function css(){
	return "";
}	
function description($value){
//http://eve-files.com/chribba/typeid.txt
$sql="select typeName as description from invTypes where typeID='$value'";
     list($pass)=avalues319($sql);
/*     
    if ($value==54818) $pass="Capsuleer Day XVII Cap and T-Shirt Crate";
        
    https://market.fuzzwork.co.uk/type/23061/
    
*/      
        //if ($value==47911) $pass="Entropic Radiation Sink II";
     if ($pass=='') $pass=$value;
     $pass=addslashes($pass);
return $pass;
} // description
function typea($value){

list($pass)=avalues319("Select Combination from SkillAttributes where TypeId='$value'");
if ($pass=="") $pass="n/a";
//$pass=getskillt($value);

$color="";
if ($pass=="Perception/Willpower") $color=" style='background-color:cyan'";
if ($pass=="Willpower/Perception") $color=" style='background-color:yellow'";
if ($pass=="Intelligence/Perception") $color=" style='background-color:lime'";
if ($pass=="Intelligence/Memory") $color=" style='background-color:cccccc'";
if ($pass=="Memory/Intelligence") $color=" style='background-color:pink'";
if ($pass=="Charisma/Intelligence") $color=" style='background-color:cc99cc'";
if ($pass=="Charisma/Willpower") $color=" style='background-color:dcb59f'";
if ($pass=="Perception/Willpower") $pass ="Per/Wil";
if ($pass=="Willpower/Perception") $pass ="Wil/Per";
if ($pass=="Willpower/Intelligence") $pass ="Wil/Int";
if ($pass=="Perception/Memory") $pass ="Per/Mem";
if ($pass=="Memory/Perception") $pass ="Mem/Per";
if ($pass=="Memory/Charisma") $pass ="Mem/Cha";
if ($pass=="Charisma/Willpower") $pass ="Cha/Wil";
if ($pass=="Memory/Intelligence") $pass ="Mem/Int";
if ($pass=="Intelligence/Memory") $pass ="Int/Mem";
if ($pass=="Intelligence/Perception") $pass ="Int/Per";
if ($pass=="Charisma/Intelligence") $pass ="Cha/Int";
if ($pass=="Willpower/Charisma") $pass ="Wil/Cha";
if ($pass=="Charisma/Memory") $pass ="Cha/Mem";

$pass= "<td $color>$pass</td>";
return $pass;
} // typea	
function footboot(){
	return "";
}
function standings($who){
$loadim=$_SESSION['loading'] ?? "";
if ($who=='') $who=valpilot("CHECK");
if ($loadim<>'') $who=$loadim;
if ($loadim=='') echo css().topboot();
list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,standings from PILOTS  where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (15)");
//if ($pilot<>$_SESSION['youremail']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (15)");
$name=addslashes($name);
$pass = "<h3>Standings of $name</h3>";
if ($loadim<>'' and $data=='[]') return ""; 
if ($data=='[]') return '[]'; // die("no data standings for $name");
$sql="delete from DIPLOMATIC where pilot_name='$name' and pilot_name<>'All Others Catalog'";
doaction($sql,"error in saving npc standing");
/*
echo "<pre>";
print_r($data);
echo "</pre><hr>";
*/

// init faction
$data=stripslashes($data);
$xml=json2xml($data);
$xml = new SimpleXMLElement($xml);

/*
echo "<pre>";
var_dump($xml);
echo "</pre>";
*/
$pass .= css();
$theid=randomstring();  // identify table for navigation script
$cabec ="<table id='$theid' class='sortable hovertable'>";
$pass .="<li>6.16 in 4 and 5.97 in 5";
$pass .="<table border=0 cellspacing=20><tr><td  valign='top'>";
$pass .="<center><h4>Factions</h4></center>";
$pass .="$cabec<tr><th>#</th><th>Id</th><th>Type</th><th>Description</th><th>Value</th></tr>";
$csh=0;
foreach($xml->item as $item)
{   
    $what=$item->from_type;
    $description="n/a";
    if ($what=='faction') {
      list($description)=avalues319("select factionName from chrFactions where factionId='$item->from_id'");    
      $csh ++;
      $pass .="<tr><th>$csh</th>";
      $pass .="<td>$item->from_id</td>";
      $pass .="<td>$what</td>";
      $pass .="<td>$description</td>";
      $lopp="";
      if ( $item->standing> 5.90) $lopp=" style='background-color:cyan'";
      if ( $item->standing < -5) $lopp=" style='background-color:red'";
      $pass .="<td $lopp>".cfdinumbers($item->standing)."</td>";
      $pass .="</tr>";
    }  
}
$pass .="</table>";
// finish faction
$pass .="</td><td  valign='top'>";
// init corps
$data=stripslashes($data);
$xml=json2xml($data);
$xml = new SimpleXMLElement($xml);

/*
echo "<pre>";
var_dump($xml);
echo "</pre>";
*/
$positive=0;
$theid=randomstring();  // identify table for navigation script
$cabec ="<table id='$theid' class='sortable hovertable'>";
$pass .="<center><h4>Npc Corporations</h4></center>";
$pass .= "$cabec<tr><th>#</th><th>Id</th><th>Type</th><th>Description</th><th>Value</th></tr>";
$csh=0;
foreach($xml->item as $item)
{   
    $what=$item->from_type;
    $description="n/a";
    if ($what=='npc_corp') {
      list($description)=avalues319("select itemName from invUniqueNames where itemId='$item->from_id'");
      $description=addslashes($description);
      $id=$item->from_id;    
      $csh ++;
      $candistri="";
      // if exist level4 distribution missions then  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000003')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000005')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000011')  $candistri=" style='background-color:ffc0cb'";      
      if ($id=='1000028')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000029')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000033')  $candistri=" style='background-color:ffc0cb'";
      
      $pass .="<tr><th>$csh</th>";
      $pass .="<td $candistri>$item->from_id</td>";
      $pass .="<td>$what</td>";
      
      // warfare
      
      $rower="";
      // imortnt npc corps
      $rower= specialcolorNPC($id);    

      $pass .="<td $rower>$description</td>";
      $lopp="";
      $sta=floatval($item->standing);
      if ( $item->standing> 5.90) $lopp=" style='background-color:cyan'";
      if ( $item->standing < -5) $lopp=" style='background-color:red'";
      $pass .="<td $lopp>".cfdinumbers($sta)."</td>";
      $pass .="</tr>";
      $sta2=cfdinumbers($sta);
      if ($sta>0)  {
        $positive += $sta;
        list($me)=avalues319("select email_pilot from PILOTS where toon_name='$name'");
        //$me=$_SESSION['youremail'];
        
        $sql="insert into DIPLOMATIC (target,target_description,target_type,reputation,pilot_name,owner_email)
        values ($id,'$description','$what',$sta2,'$name','$me')";
        doaction($sql,"error in npc insert");
        $sql="update DIPLOMATIC SET pilot_positive='$positive' where pilot_name='$name'";
        doaction($sql,"error in npc insert 2");
      }  
    } // npc corp  
}
$pass .="</table><br />
The pilot have a total sumatory of ".cfdinumbers($positive)." positive standings";
// finish corps
$pass .="</td><td valign='top'>";
// init agents
$data=stripslashes($data);
$xml=json2xml($data);
$xml = new SimpleXMLElement($xml);

/*
echo "<pre>";
var_dump($xml);
echo "</pre>";
*/
$theid=randomstring();  // identify table for navigation script
$cabec ="<table id='$theid' class='sortable hovertable'>";
$pass .="<center><h4>Agents</h4></center>";
$pass .= "$cabec<tr><th>#</th><th>Id</th><th>Type</th><th>Description</th><th>Value</th></tr>";
$csh=0;
$name2=$name;
foreach($xml->item as $item)
{   
    $what=$item->from_type;
    $description="n/a";         
    if ($what=='agent') {
      $csh ++;
      list($description)=avalues319("select itemName from invUniqueNames where itemId='$item->from_id'");
      $pass2 ="<tr><th>$csh</th>";
      $pass2 .="<td>$item->from_id</td>";
      $pass2 .="<td>$what</td>";
      $name=$description;
             $namelink="http://evemaps.dotlan.net/agent/$name";
             $namelink=str_replace(" ","_",$namelink);
             $oculto="";
             $namelink="<a href='$namelink' target='_blank'>$name</a>";
      $pass2 .="<td>$namelink</td>";
      $pass2 .="<td>".cfdinumbers($item->standing)."</td>";
      $pass2 .="</tr>";
      $do="yes";
      if ($do=="yes") $pass .=$pass2;
      if ($do<>"yes") $csh --;
    }  
}
$pass .="</table>";
// finish agents
$pass .="</td><tr></table>";
if ($loadim<>'') return ""; // nada     
return ($pass);
} // standings
function topboot(){
	return "";
}
function randomstring($leng= 10){
if (intval($leng)==0) $leng=10;
return left(rtrim(base64_encode(md5(microtime())),"="),$leng);	
}
function specialcolorNPC($corp){  
return "";
}
function charskills(){
//return charskills_and_queue();
$loadim=$_SESSION['loading'] ?? "";
$pass ="";
$who=valpilot("CHECK");
if ($who==2122782609 // nokia catalog
 or $who==2117759705
 or $who==2122782650
 or $who==2122783972) return; // depura, es catalog
if ($loadim<>'') $who=$loadim;
$pilo=$_GET['pilot_id'] ?? 0;
if ($pilo>0) $who= $pilo;
list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,skills from PILOTS where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (4)");
$name=addslashes($name);
//if ($pilot<>$_SESSION['youremail']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (4) (belong to $pilot)");
if ($loadim=='') $pass .= css().topboot()."<h3>Skills of $name</h3>";
if ($data=='[]' and $loadim<>'') return ;
if ($data=='[]') die("no data(1) for $name");

if ($name=='Dallas Riordan2'){
  echo "<pre>";
  print_r($data);
  echo "</pre><hr>";
  die();
}  

if (str_replace("Timeout contacting tranquility","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later");
if (str_replace("unexpected end of JSON","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later");
if (str_replace("504 Gateway","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later");
$data=stripslashes($data);
$xml=json2xml($data);
$xml = new SimpleXMLElement($xml);
/*
echo "<li>$who";

echo "<pre>";
var_dump($xml);
echo "</pre>";
*/
$acctype="Maybe Omega";
$theid=randomstring();  // identify table for navigation script
$cabec ="<table id='$theid' class='sortable hovertable'>";
$pass= "$cabec<tr><th>#</th><th>Skill</th><th>Description</th><th>SP</th><th>Category</th><th>Active</th><th>Trained</th><th>Alpha Max</th></tr>";
$csh=0;
$sp=0;
$sql="delete from EVE_CHARSKILLS where toon='$who'";
doaction($sql,"error checking skills $who");
 isset($xml->unallocated_sp)  ? $xml->unallocated_sp : 0;
 $unalloc=0;
 if  (xml_child_exists($xml,"unallocated_sp")){
   $unalloc=$xml->unallocated_sp;
  }
//echo "<h1>char $who | $unalloc</h1>";
list($dummy)=avalues319("update PILOTS set unalloc=$unalloc where toon_number='$who'");
//echo "<h3>Pilot: $name (2)</h3>";     
foreach($xml->skills->item as $item)
{   $csh ++;
    /*
    if ($item->is_singleton) $single='';
    if ($item->is_singleton<>'false') $single='Container';
    */
    $what=$item->skill_id;
//    list($description)=avalues319("select typeName from invTypes where typeID='$what'"); 
    $description=description($what);
    $pass .="<tr><th>$csh</th>";

    //$pass .="<td>$what</td>";
    //$pass .="<td><a href='?module=dt2&what=$what' target='_blank'>$what</a></td>";
    $pass .="<td><a href='?module=dt2&what=$what' target='_blank'>$what</a></td>";
   
    $pass .="<td>$description</td>";    
    
    if ($description=='') $description='n/a';    
    
    $pass .="<td>$item->skillpoints_in_skill</td>";
    $pass .=typea($what);
    $active=$item->active_skill_level;
    if ($active=='') $active=0;    
    $pass .="<td>$active</td>";
    $pass .="<td>$item->trained_skill_level</td>";
    $dif=abs($item->trained_skill_level-$item->active_skill_level);
    $sp +=$item->skillpoints_in_skill;
    
    if ($dif>0) $acctype='Alpha';            
    list($maxalpha)=avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$what'");
    $pass .="<td>$maxalpha</td></tr>";
    if ($active >$maxalpha and $active>0)  $acctype="Omega";
    $sql="insert into EVE_CHARSKILLS (toon,typeID,skillpoints,rank,description) values 
       ('$who',$what,$item->skillpoints_in_skill,$item->trained_skill_level,'$description')";
     doaction($sql,"error inserting skills");  
}
$pass .="</table>";
$pass .="skill points $sp<br />Acc type:$acctype";
$sql="update PILOTS set acctype='$acctype',skillpoints=$sp where toon_number='$who'";
doaction($sql,"cant update pilot");
if ($loadim=='' and $pilo=="") die($pass);
} // charskills
function xml_child_exists($xml, $childpath)
{
    $result = $xml->xpath($childpath); 
    return (bool) (count($result));
}

?>
