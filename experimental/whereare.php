<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Mosaic Dashboard
 * Fecha: 2026-03-31 00:22
 * 
 * This is a experimental feature showing where are some things. use your own items and pilots
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

// ---------------------------------------------------------------------
// CONFIGURACIÓN INVENTARIO
// ---------------------------------------------------------------------
$verificando = "Corax,Catalyst,Cormorant,Kestrel,Caracal,Oxygen Isotopes,Hydrogen Isotopes,Nitrogen Isotopes,Helium Isotopes,Antimatter Charge M,Scourge Light Missile,Antimatter Charge S";
$pilotos = "Abyssal Firestorm,Hypervisor,Sue Rtuda";

// ---------------------------------------------------------------------
// PROCESAR ACCIONES DE REASIGNACIÓN
// ---------------------------------------------------------------------
$mensaje_reasignar = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reassign') {
    $run_id           = intval($_POST['run_id']);
    $piloto_origen_id = intval($_POST['piloto_origen_id']);
    $piloto_destino_id = intval($_POST['piloto_destino_id']);

    $query_destino = "SELECT toon_name FROM PILOTS WHERE toon_number = $piloto_destino_id";
    $result_destino = mysqli_query($link, $query_destino);
    $piloto_destino_nombre = mysqli_fetch_assoc($result_destino)['toon_name'];

    $query_update = "UPDATE abyssal_runs SET piloto_id = $piloto_destino_id WHERE run_id = $run_id";
    if (mysqli_query($link, $query_update)) {
        $fecha_actual = date('d/m/Y H:i:s');
        $mensaje_reasignar = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Run #' . $run_id . ' asignado correctamente al piloto <strong>' . htmlspecialchars($piloto_destino_nombre) . '</strong> el ' . $fecha_actual . ' hora de México.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
    } else {
        $mensaje_reasignar = '<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-triangle"></i> Error al reasignar: ' . mysqli_error($link) . '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>';
    }
}

// ---------------------------------------------------------------------
// DATOS REASIGNAR — pilotos con runs
// ---------------------------------------------------------------------
$query_pilotos_runs = "
    SELECT
        p.toon_number,
        p.toon_name,
        COUNT(ar.run_id) as total_runs,
        SUM(CASE WHEN ar.dead = 'NO' THEN 1 ELSE 0 END) as runs_exitosos,
        SUM(CASE WHEN ar.dead = 'YES' THEN 1 ELSE 0 END) as runs_fallidos,
        SUM(ar.skybreakers_eliminados) as total_skybreakers
    FROM PILOTS p
    INNER JOIN abyssal_runs ar ON p.toon_number = ar.piloto_id
    GROUP BY p.toon_number, p.toon_name
    ORDER BY p.toon_name
";
$result_pilotos_runs = mysqli_query($link, $query_pilotos_runs);
$pilotos_runs = [];
if ($result_pilotos_runs) {
    while ($row = mysqli_fetch_assoc($result_pilotos_runs)) {
        $pilotos_runs[] = $row;
    }
}

// Piloto seleccionado para ver sus runs
$piloto_seleccionado_id     = isset($_GET['piloto_id']) ? intval($_GET['piloto_id']) : null;
$runs_detalle               = [];
$piloto_seleccionado_nombre = '';

if ($piloto_seleccionado_id) {
    $query_nombre = "SELECT toon_name FROM PILOTS WHERE toon_number = $piloto_seleccionado_id";
    $result_nombre = mysqli_query($link, $query_nombre);
    if ($row_nombre = mysqli_fetch_assoc($result_nombre)) {
        $piloto_seleccionado_nombre = $row_nombre['toon_name'];
    }

    $query_runs = "
        SELECT
            ar.run_id, ar.piloto_id, ar.fit_id, ar.fecha, ar.tier,
            ar.weather, ar.ship_class, ar.hull_ship, ar.dead,
            ar.skybreakers_eliminados, ar.comentario_piloto,
            f.nombre_corto as fit_nombre
        FROM abyssal_runs ar
        LEFT JOIN fits f ON ar.fit_id = f.fit_id
        WHERE ar.piloto_id = $piloto_seleccionado_id
        ORDER BY ar.ship_class, ar.fit_id, ar.weather, ar.fecha
    ";
    $result_runs = mysqli_query($link, $query_runs);
    if ($result_runs) {
        while ($row = mysqli_fetch_assoc($result_runs)) {
            $runs_detalle[] = $row;
        }
    }
}

// ---------------------------------------------------------------------
// DATOS INVENTARIO
// ---------------------------------------------------------------------
$ship_names_raw = explode(',', $verificando);
$ship_names = array();
foreach ($ship_names_raw as $name) {
    $name = trim($name);
    if ($name !== '') $ship_names[] = $name;
}

$ships = array();
if (!empty($ship_names)) {
    $safe_names = array();
    foreach ($ship_names as $name) {
        $safe_names[] = "'" . mysqli_real_escape_string($link, $name) . "'";
    }
    $in_clause = implode(',', $safe_names);
    $sql = "SELECT DISTINCT type_id, type_description FROM EVE_ASSETS WHERE type_description IN ($in_clause) ORDER BY type_description ASC";
    $result = mysqli_query($link, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $ships[] = $row;
        }
        mysqli_free_result($result);
    }
}

$datos_inventario = tabla_existencias_pilotos_naves($link, $pilotos, $verificando);

$user_ip    = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'IP desconocida';
$php_version = phpversion();

// ---------------------------------------------------------------------
// FUNCIÓN TABLA INVENTARIO
// ---------------------------------------------------------------------
function tabla_existencias_pilotos_naves($link, $pilotos_cadena, $naves_cadena) {
    $pilotos_raw = explode(',', $pilotos_cadena);
    $naves_raw   = explode(',', $naves_cadena);
    $pilotos = array();
    $naves   = array();
    foreach ($pilotos_raw as $p) { $p = trim($p); if ($p !== '') $pilotos[] = $p; }
    foreach ($naves_raw   as $n) { $n = trim($n); if ($n !== '') $naves[]   = $n; }

    if (empty($pilotos) || empty($naves)) return "<div class='alert alert-warning'>No hay pilotos o naves válidas.</div>";

    $safe_pilots = array();
    foreach ($pilotos as $p) $safe_pilots[] = "'" . mysqli_real_escape_string($link, $p) . "'";
    $in_pilots = implode(',', $safe_pilots);

    $sql_pilots = "SELECT toon_number, toon_name FROM PILOTS WHERE toon_name IN ($in_pilots)";
    $result = mysqli_query($link, $sql_pilots);
    $pilotos_info = array();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $pilotos_info[$row['toon_number']] = $row['toon_name'];
        mysqli_free_result($result);
    }
    if (empty($pilotos_info)) return "<div class='alert alert-danger'>No se encontraron pilotos en la base de datos.</div>";

    $safe_naves = array();
    foreach ($naves as $n) $safe_naves[] = "'" . mysqli_real_escape_string($link, $n) . "'";
    $in_naves = implode(',', $safe_naves);

    $sql_naves = "SELECT DISTINCT type_id, type_description FROM EVE_ASSETS WHERE type_description IN ($in_naves)";
    $result = mysqli_query($link, $sql_naves);
    $naves_info = array();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) $naves_info[$row['type_id']] = $row['type_description'];
        mysqli_free_result($result);
    }
    if (empty($naves_info)) return "<div class='alert alert-danger'>No se encontraron objetos en la base de datos.</div>";

    $matriz = array();
    foreach ($naves_info as $type_id => $type_desc) {
        $matriz[$type_id] = array();
        foreach ($pilotos_info as $toon_number => $toon_name) {
            $sql_sum = "SELECT SUM(quantity) AS total FROM EVE_ASSETS WHERE toon_number = $toon_number AND type_id = $type_id";
            $res_sum = mysqli_query($link, $sql_sum);
            $total = "";
            if ($res_sum) {
                $row = mysqli_fetch_assoc($res_sum);
                if (!empty($row['total'])) $total = (int)$row['total'];
                mysqli_free_result($res_sum);
            }
            $matriz[$type_id][$toon_number] = $total;
        }
    }

    $html = "<table class='table table-bordered table-sm table-hover' style='background-color: #fff; color: #000;'>";
    $html .= "<thead style='background-color: #343a40; color: #fff;'><tr><th>Objeto \\ Piloto</th>";
    foreach ($pilotos_info as $toon_number => $toon_name) $html .= "<th>" . htmlspecialchars($toon_name) . "</th>";
    $html .= "</tr></thead><tbody>";
    foreach ($naves_info as $type_id => $type_desc) {
        $html .= "<tr><td><strong>" . htmlspecialchars($type_desc) . "</strong></td>";
        foreach ($pilotos_info as $toon_number => $toon_name) {
            $val = $matriz[$type_id][$toon_number];
            $html .= "<td class='text-center'>" . ($val === "" ? "-" : number_format($val)) . "</td>";
        }
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Inventory & Reasignar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" crossorigin="anonymous">
    <style>
        body {
            padding-top: 70px;
            padding-bottom: 70px;
            background-color: #111;
            color: #f8f9fa;
        }
        .navbar-brand { font-weight: 600; }
        .btn-salir { color: #ffeb3b !important; }

        /* ── FOOTER FIJO ── */
        .footer-fixed {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px 15px;
            background-color: #222;
            color: #ddd;
            font-size: 0.9rem;
            border-top: 1px solid #444;
            z-index: 1030;
        }

        .form-dark {
            background-color: #222;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-dark label { color: #ddd; font-weight: 500; }
        .form-dark .form-control,
        .form-dark .form-control:focus {
            background-color: #333;
            border-color: #555;
            color: #fff;
        }

        /* Metro tiles */
        .metro-tile {
            display: block;
            position: relative;
            padding: 20px;
            margin-bottom: 20px;
            color: #fff;
            text-align: left;
            border-radius: 4px;
            text-decoration: none;
            transition: transform 0.1s ease-in-out, box-shadow 0.1s ease-in-out, opacity 0.1s;
            min-height: 110px;
        }
        .metro-tile:hover { text-decoration: none; transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.4); opacity: 0.95; }
        .metro-tile i { font-size: 32px; margin-bottom: 10px; }
        .metro-tile-title { font-size: 1.1rem; font-weight: 600; }
        .metro-blue   { background-color: #0078d7; }
        .metro-green  { background-color: #107c10; }
        .metro-orange { background-color: #d83b01; }
        .metro-purple { background-color: #5c2d91; }
        .metro-teal   { background-color: #008272; }
        .metro-red    { background-color: #e81123; }

        .section-content { display: none; }
        .section-content.active { display: block; }

        .nav-tabs .nav-link { color: #aaa; }
        .nav-tabs .nav-link.active { background-color: #222; color: #fff; border-color: #444 #444 #222; }

        /* Reasignar — tabla oscura */
        #seccion-reasignar .table { color: #f8f9fa; }
        #seccion-reasignar .card { background-color: #1e1e1e; border-color: #444; }
        #seccion-reasignar .card-header { border-bottom-color: #444; }
        #seccion-reasignar .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(255,255,255,0.05); }
        #seccion-reasignar .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.08); }
        #seccion-reasignar .table td, #seccion-reasignar .table th { border-color: #444; }
        #seccion-reasignar .form-control {
            background-color: #333;
            border-color: #555;
            color: #fff;
        }
    </style>
</head>
<body>
<?php echo crew_navbar(); ?>

<div class="container-fluid">

    <?php if (!empty($mensaje_reasignar)) echo $mensaje_reasignar; ?>

    <!-- TABS DE NAVEGACIÓN -->
    <div class="row">
        <div class="col-12">
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" href="#" onclick="return mostrarSeccion('inventario')">
                        <i class="fas fa-boxes"></i> Inventario Pilotos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return mostrarSeccion('market')">
                        <i class="fas fa-shopping-cart"></i> Market Links
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#" onclick="return mostrarSeccion('reasignar')">
                        <i class="fas fa-exchange-alt"></i> Reasignar Runs
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- SECCIÓN INVENTARIO -->
    <div id="seccion-inventario" class="section-content active">
        <div class="row">
            <div class="col-12">
                <div class="form-dark">
                    <h4><i class="fas fa-boxes"></i> Inventario de Objetos por Piloto</h4>
                    <p class="mb-1"><strong>Objetos monitoreados:</strong> <code><?php echo htmlspecialchars($verificando); ?></code></p>
                    <p class="mb-3"><strong>Pilotos:</strong> <code><?php echo htmlspecialchars($pilotos); ?></code></p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <?php echo $datos_inventario; ?>
            </div>
        </div>
    </div>

    <!-- SECCIÓN MARKET LINKS -->
    <div id="seccion-market" class="section-content">
        <div class="row">
            <div class="col-12 mb-3">
                <div class="form-dark">
                    <h4><i class="fas fa-shopping-cart"></i> Market Rápido (Fuzzwork)</h4>
                    <p class="mb-1">Cada mosaico abre en una nueva pestaña la página de <strong>market.fuzzwork.co.uk</strong>.</p>
                    <p class="mb-0"><small class="text-muted">Enlaces basados en los objetos monitoreados en tu inventario.</small></p>
                </div>
            </div>
        </div>
        <div class="row">
            <?php
            if (empty($ships)) {
                echo "<div class='col-12'><div class='alert alert-warning'>No se encontraron registros en <strong>EVE_ASSETS</strong> para los nombres indicados.</div></div>";
            } else {
                $metro_colors = ['metro-blue','metro-green','metro-orange','metro-purple','metro-teal','metro-red'];
                $color_count = count($metro_colors);
                $index = 0;
                foreach ($ships as $ship) {
                    $type_id   = (int)$ship['type_id'];
                    $type_desc = $ship['type_description'];
                    $url       = "https://market.fuzzwork.co.uk/type/" . $type_id . "/";
                    $color_class = $metro_colors[$index % $color_count];
                    $index++;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                        <a href="<?php echo htmlspecialchars($url); ?>" class="metro-tile <?php echo $color_class; ?>" target="_blank">
                            <i class="fas fa-rocket"></i>
                            <div class="metro-tile-title"><?php echo htmlspecialchars($type_desc); ?></div>
                            <div><small>type_id: <?php echo $type_id; ?></small></div>
                        </a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <div class="form-dark">
                    <h4><i class="fas fa-shopping-cart"></i> Ammo quick market (Fuzzwork)</h4>
                    <p class="mb-1">Cada mosaico abre en una nueva pestaña la página de <strong>market.fuzzwork.co.uk</strong>.</p>
                    <p class="mb-0"><small class="text-muted">Enlaces basados en los objetos monitoreados en tu inventario.</small></p>
                </div>
            </div>
        </div>
        
    </div>

    <!-- SECCIÓN REASIGNAR RUNS -->
    <div id="seccion-reasignar" class="section-content">

        <?php if (!$piloto_seleccionado_id): ?>
        <!-- PANTALLA 1: LISTA DE PILOTOS -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-users"></i> Pilotos con Runs Abisales Registrados
            </div>
            <div class="card-body">
                <?php if (empty($pilotos_runs)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay pilotos con runs abisales registrados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>Piloto</th>
                                    <th class="text-center">Total Runs</th>
                                    <th class="text-center">Exitosos</th>
                                    <th class="text-center">Fallidos</th>
                                    <th class="text-center">Skybreakers</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pilotos_runs as $p): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($p['toon_name']); ?></strong></td>
                                    <td class="text-center"><?php echo $p['total_runs']; ?></td>
                                    <td class="text-center"><span class="badge badge-success"><?php echo $p['runs_exitosos']; ?></span></td>
                                    <td class="text-center"><span class="badge badge-danger"><?php echo $p['runs_fallidos']; ?></span></td>
                                    <td class="text-center"><?php echo $p['total_skybreakers']; ?></td>
                                    <td class="text-center">
                                        <a href="?piloto_id=<?php echo $p['toon_number']; ?>#reasignar"
                                           class="btn btn-sm btn-info"
                                           onclick="return irAReasignar(<?php echo $p['toon_number']; ?>)">
                                            <i class="fas fa-list"></i> Ver Runs
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- PANTALLA 2: DETALLE DE RUNS DEL PILOTO -->
        <div class="mb-3">
            <a href="?#reasignar" onclick="return irAReasignar(null)" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Lista de Pilotos
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <i class="fas fa-clipboard-list"></i> Runs de <?php echo htmlspecialchars($piloto_seleccionado_nombre); ?>
            </div>
            <div class="card-body">
                <?php if (empty($runs_detalle)): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-circle"></i> Este piloto no tiene runs registrados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Run ID</th>
                                    <th>Fecha</th>
                                    <th>Fit</th>
                                    <th>Weather</th>
                                    <th>Ship Class</th>
                                    <th>Hull</th>
                                    <th>Tier</th>
                                    <th>Estado</th>
                                    <th>Sky</th>
                                    <th>Coment.</th>
                                    <th>Reasignar a</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $contador = 1; foreach ($runs_detalle as $run): ?>
                                <tr>
                                    <td><?php echo $contador++; ?></td>
                                    <td><?php echo $run['run_id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($run['fecha'])); ?></td>
                                    <td><?php echo htmlspecialchars($run['fit_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($run['weather']); ?></td>
                                    <td><?php echo htmlspecialchars($run['ship_class']); ?></td>
                                    <td><?php echo htmlspecialchars($run['hull_ship']); ?></td>
                                    <td class="text-center"><?php echo $run['tier']; ?></td>
                                    <td class="text-center">
                                        <?php if ($run['dead'] === 'NO'): ?>
                                            <span class="badge badge-success">Exitoso</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Fallido</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo $run['skybreakers_eliminados']; ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($run['comentario_piloto'])): ?>
                                            <i class="fas fa-comment text-success" title="<?php echo htmlspecialchars($run['comentario_piloto']); ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-comment-slash text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="reassign">
                                            <input type="hidden" name="run_id" value="<?php echo $run['run_id']; ?>">
                                            <input type="hidden" name="piloto_origen_id" value="<?php echo $piloto_seleccionado_id; ?>">
                                            <select name="piloto_destino_id" class="form-control form-control-sm" required>
                                                <option value="">-- Seleccionar --</option>
                                                <?php foreach ($pilotos_runs as $p): ?>
                                                    <?php if ($p['toon_number'] != $piloto_seleccionado_id): ?>
                                                        <option value="<?php echo $p['toon_number']; ?>">
                                                            <?php echo htmlspecialchars($p['toon_name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                    </td>
                                    <td class="text-center">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /seccion-reasignar -->

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    var tabsMap = { 'inventario': 0, 'market': 1, 'reasignar': 2 };

    function mostrarSeccion(seccion) {
        document.querySelectorAll('.section-content').forEach(function(el) {
            el.classList.remove('active');
        });
        document.querySelectorAll('.nav-tabs .nav-link').forEach(function(el) {
            el.classList.remove('active');
        });

        var el = document.getElementById('seccion-' + seccion);
        if (el) el.classList.add('active');

        var idx = tabsMap[seccion];
        if (idx !== undefined) {
            var tabs = document.querySelectorAll('.nav-tabs .nav-link');
            if (tabs[idx]) tabs[idx].classList.add('active');
        }
        return false;
    }

    // Si viene con ?piloto_id=X abrimos directo el tab reasignar
    function irAReasignar(piloto_id) {
        if (piloto_id) {
            window.location.href = '?piloto_id=' + piloto_id;
        } else {
            window.location.href = '?';
        }
        return false;
    }

    // Al cargar, si hay piloto_id en la URL, activar tab reasignar
    (function() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('piloto_id')) {
            mostrarSeccion('reasignar');
        }
    })();

    function confirmarSalida() {
        return confirm('¿Seguro que deseas salir?');
    }
</script>
<?php echo ui_footer(); ?>
</body>
</html>
