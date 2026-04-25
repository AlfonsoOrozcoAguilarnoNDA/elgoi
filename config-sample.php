<?php
/*
License MIT
Alfonso Orozco Aguilar
*/
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// ===============================================
// CONFIGURACIÓN DE SEGURIDAD Y BASE DE DATOS
// ===============================================

// Variable global para la conexión a la base de datos
global $link;

// Configuración ESI OAuth
define('ESI_CLIENT_ID', 'dummyhereuseyours');
define('ESI_CLIENT_SECRET', 'summyhereuseyours');

// -----------------------------------------------
// 1. Configuración de Acceso por IP (Lista Blanca)
// -----------------------------------------------

// Lista de direcciones IP autorizadas para modificar los datos.
define('AUTHORIZED_IPS', serialize([
    '127.0.0.1',  // IP local para pruebas
    '189.146.88.146' // 'TU.DIRECCION.IP.PUBLICA', // REEMPLAZAR con tu IP real.
]));

/**
 * Verifica si la IP del usuario actual está autorizada.
 * Si no lo está, termina la ejecución y muestra un mensaje de error.
 * @return void
 */
function check_authorization() {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    $allowed_ips = unserialize(AUTHORIZED_IPS);

    if (!in_array($client_ip, $allowed_ips)) {
        header('HTTP/1.0 403 Forbidden');
		    if (!in_array($client_ip, $allowed_ips)) {
        header('HTTP/1.0 403 Forbidden');
        die('<div style="padding: 20px; border: 1px solid #dc3545; background-color: #f8d7da; color: #721c24;"><h4>Acceso Denegado</h4><p>Tu dirección IP (' . htmlspecialchars($client_ip) . ') no está autorizada para acceder.</p></div>');
    
        die('<div class="alert alert-danger" role="alert">
                <h4 class="alert-heading"><i class="fas fa-lock"></i> Acceso Denegado</h4>
                <p>Tu dirección IP (' . htmlspecialchars($client_ip) . ') no está autorizada para acceder a esta aplicación.</p>
             </div>');
    }
}
}

// -----------------------------------------------
// 2. Configuración de Conexión a la Base de Datos
// -----------------------------------------------

// **IMPORTANTE: REEMPLAZAR CON TUS CREDENCIALES REALES**
define('DB_HOST', 'localhost');
define('DB_USER', 'sfggfdfgd');
define('DB_PASS', 'sdfgdfgdfg');
define('DB_NAME', 'dfgdfgdf'); 

/**
 * Intenta establecer la conexión a la base de datos MySQL y la guarda en la variable global $link.
 * @global mysqli $link Objeto de conexión a la base de datos.
 * @return void
 */
function db_connect() {
    global $link;

    $link = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($link->connect_error) {
        die("Error de Conexión a la Base de Datos: " . $link->connect_error);
    }

    // Configurar la codificación UTF8 MB4
    if (!$link->set_charset("utf8mb4")) {
        error_log("Error al cargar utf8mb4: " . $link->error);
    }
}

// Conectar la base de datos inmediatamente al incluir el archivo
db_connect();

function aValues319b($Qx) {
    global $link;
    
    $rsX = mysqli_query($link, $Qx) or die("<hr>Avalues 319<hr>$Qx<hr>" . mysqli_error($link));
    $Qx2 = strtolower($Qx);
    
    if (substr($Qx2, 0, 6) !== 'select') {
        return "";
    }
    
    $rows = mysqli_num_rows($rsX);
    if ($rows == 0) {
        return ["", ""];
    }
    
    $aDataX = [];
    $Campos = mysqli_num_fields($rsX);
    
    while ($regX = mysqli_fetch_array($rsX)) {
        for ($iX = 0; $iX < $Campos; $iX++) {
            $finfo = mysqli_fetch_field_direct($rsX, $iX);
            $name = $finfo->name;
            $aDataX[] = $regX[$name];
        }
    }
    
    return $aDataX;
}


/**
 * geticons($toon_number) — Iconos de actividad de un piloto EVE Online
 * Parámetro: toon_number (int) — ID único del piloto en la tabla PILOTS.
 * Usa $link (global) para consultar la BD.
 * Retorna HTML con 4 iconos listos para mostrar.
 * Reutilizable en cualquier script con solo el ID del piloto.
 *
 * Iconos:
 *   🌍 Planeta   — verde si tiene planetas activos, gris si no
 *   🏭 Industria — amarillo si tiene jobs activos, gris si no
 *   🎓 Birrete   — verde si está entrenando (finishqueue en el futuro)
 *                  amarillo si tiene planetas pero no entrena
 *                  gris si no entrena ni tiene planetas
 *   🔄 Sync      — enlace a devauthcallback.php para refrescar el piloto
 */
function geticons($toon_number) {
    global $link;

    $toon_number = (int)$toon_number;
    $res = mysqli_query($link, "SELECT planets, jobs, finishqueue FROM PILOTS WHERE toon_number = $toon_number LIMIT 1");

    if (!$res || mysqli_num_rows($res) === 0) {
        return '<span class="text-muted">—</span>';
    }

    $p = mysqli_fetch_assoc($res);

    $hasPlanets = (!empty($p['planets']) && $p['planets'] !== '[]');
    $hasJobs    = (!empty($p['jobs'])    && $p['jobs']    !== '[]');
    $ahora      = date('Y-m-d H:i:s');

    // Lógica del birrete
    if (!empty($p['finishqueue']) && $p['finishqueue'] > $ahora) {
        $birrete = 'text-success';
    } elseif ($hasPlanets) {
        $birrete = 'text-warning';
    } else {
        $birrete = 'text-secondary';
    }

    return '
	<style>.industry-icons {
    display: inline-flex;
    gap: 9px;
    align-items: center;
    font-size: 1.05rem;
}
.industry-icons i { cursor: default; }
</style>
    <div class="industry-icons">
        <i class="fas fa-globe-asia '     . ($hasPlanets ? 'text-success' : 'text-secondary') . '" title="Planetas Activos"></i>
        <i class="fas fa-industry '       . ($hasJobs    ? 'text-warning' : 'text-secondary') . '" title="Trabajos de Fábrica"></i>
        <i class="fas fa-graduation-cap ' . $birrete                                          . '" title="Entrenamiento"></i>
        <a href="../devauthcallback.php?pilot_id=' . $toon_number . '" target="_blank">
            <i class="fas fa-sync-alt text-secondary icon-action" title="Actualizar"></i>
        </a>
    </div>';
} // geticons
?>
