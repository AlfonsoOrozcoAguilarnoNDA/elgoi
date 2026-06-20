<?php
/**
 * Archivo: deepseekcrew2.php.php
 * Fecha: 2026-06-20
 * Descripcion: Panel de control de pilotos con integridad de flota.
 *              Fusion de dos scripts: logica de Qwen (arriba) + DeepSeek (abajo).
 * Modelos: DeepSeek R1 (marzo 2026)
 */

// ============================================================================
// CONFIGURACION DE GRUPOS - MODIFICAR AQUI LOS VALORES DE SUPERGROUP
// ============================================================================
$GRUPO_1_SUPERGROUP = 1;   // Seccion Qwen (arriba)
$GRUPO_2_SUPERGROUP = 2;   // Seccion DeepSeek / comparacion (abajo)

// ============================================================================
// CONEXION A BASE DE DATOS - USA $link (establecido en abyss/config.php)
// ============================================================================
require "../config.php";
check_authorization();

// Variable del comandante de flota
$fleet_commander_character_id = isset($_GET["commander"]) ? (int)$_GET["commander"] : 0;
$fleet_commander_character_id=2112061747;
if ($fleet_commander_character_id === 0 && isset($_SESSION["fleet_commander_character_id"])) {
    $fleet_commander_character_id = (int)$_SESSION["fleet_commander_character_id"];
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function formatDate($date) {
    if (empty($date) || $date === "0000-00-00 00:00:00") {
        return "<span class=\"text-muted\">N/A</span>";
    }
    return date("d/m/Y H:i", strtotime($date));
}

function formatNumber($number) {
    if (empty($number)) return "0";
    return number_format((float)$number, 0, ",", ".");
}

function formatISK($isk) {
    if (empty($isk)) return "0.00";
    return number_format((float)$isk, 2, ",", ".");
}

function getPilotStatus($lastsaved) {
    if (empty($lastsaved) || $lastsaved === "0000-00-00 00:00:00") {
        return ["class" => "secondary", "label" => "Sin Datos"];
    }
    $last = strtotime($lastsaved);
    $now = time();
    $diff = ($now - $last) / 3600;
    if ($diff < 24) {
        return ["class" => "success", "label" => "Activo"];
    } elseif ($diff < 168) {
        return ["class" => "warning", "label" => "Reciente"];
    } else {
        return ["class" => "danger", "label" => "Inactivo"];
    }
}

function parseShipName($current_ship_json) {
    if (empty($current_ship_json)) return "-";
    $decoded = json_decode($current_ship_json, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded["ship_name"])) {
        return htmlspecialchars($decoded["ship_name"]);
    }
    return htmlspecialchars(substr($current_ship_json, 0, 30));
}

function verificarIntegridadFlota($conexion, $commander_id) {
    $resultado = [
        "valido" => true,
        "mensaje" => "",
        "pilotos_inconsistentes" => [],
        "total_pilotos" => 0,
        "pilotos_validos" => 0,
        "pilotos_invalidos" => 0,
        "huerfanos" => [],
        "huerfanos_count" => 0
    ];

    if ($commander_id === 0) {
        $resultado["valido"] = false;
        $resultado["mensaje"] = "No se ha definido un comandante de flota (fleet_commander_character_id)";
        return $resultado;
    }

    $stmt = $conexion->prepare("SELECT toon_number, toon_name, parent_toon_number FROM PILOTS WHERE parent_toon_number != ? AND parent_toon_number != 0");
    $stmt->bind_param("i", $commander_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $pilotos_inconsistentes = [];
    while ($row = $result->fetch_assoc()) {
        $pilotos_inconsistentes[] = $row;
    }

    // Pilotos huerfanos: parent_toon_number = 0 (sin commander asignado)
    $stmt_huerfanos = $conexion->prepare("SELECT toon_number, toon_name, parent_toon_number FROM PILOTS WHERE parent_toon_number = 0");
    $stmt_huerfanos->execute();
    $result_huerfanos = $stmt_huerfanos->get_result();

    $huerfanos = [];
    while ($row = $result_huerfanos->fetch_assoc()) {
        $huerfanos[] = $row;
    }

    $total_result = $conexion->query("SELECT COUNT(*) as total FROM PILOTS");
    $total_pilotos = $total_result->fetch_assoc()["total"];

    $stmt_validos = $conexion->prepare("SELECT COUNT(*) as validos FROM PILOTS WHERE parent_toon_number = ?");
    $stmt_validos->bind_param("i", $commander_id);
    $stmt_validos->execute();
    $validos_result = $stmt_validos->get_result();
    $pilotos_validos = $validos_result->fetch_assoc()["validos"];

    $resultado["total_pilotos"] = $total_pilotos;
    $resultado["pilotos_validos"] = $pilotos_validos;
    $resultado["pilotos_invalidos"] = count($pilotos_inconsistentes);
    $resultado["pilotos_inconsistentes"] = $pilotos_inconsistentes;
    $resultado["huerfanos"] = $huerfanos;
    $resultado["huerfanos_count"] = count($huerfanos);

    $otros_count = count($pilotos_inconsistentes);
    $huerfanos_count = count($huerfanos);

    if ($otros_count > 0) {
        // Pilotos de otro comandante SI bloquean el dashboard
        $resultado["valido"] = false;
        $resultado["mensaje"] = "Se encontraron " . $otros_count . " piloto(s) asignados a otro comandante de flota (no al ID: " . $commander_id . ")";
        if ($huerfanos_count > 0) {
            $resultado["mensaje"] .= " y " . $huerfanos_count . " piloto(s) huerfano(s) (sin comandante asignado)";
        }
    } else {
        // Los huerfanos NO bloquean el dashboard, solo se muestran como aviso aparte
        $resultado["valido"] = true;
        $resultado["mensaje"] = "Los " . $pilotos_validos . " pilotos pertenecen al comandante de flota (ID: " . $commander_id . ")";
    }

    return $resultado;
}

function obtenerEstadisticasFlota($conexion, $commander_id = 0) {
    $estadisticas = [];
    $filtro_commander = "";
    $params = [];
    $types = "";

    if ($commander_id > 0) {
        $filtro_commander = "WHERE parent_toon_number = ? OR parent_toon_number = 0";
        $params[] = $commander_id;
        $types .= "i";
    }

    $sql = "SELECT COUNT(*) as total FROM PILOTS " . $filtro_commander;
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $estadisticas["total_pilotos"] = $stmt->get_result()->fetch_assoc()["total"];

    $sql = "SELECT SUM(skillpoints) as total_sp FROM PILOTS " . $filtro_commander;
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $estadisticas["total_sp"] = $stmt->get_result()->fetch_assoc()["total_sp"] ?: 0;

    $estadisticas["promedio_sp"] = $estadisticas["total_pilotos"] > 0 ? 
        round($estadisticas["total_sp"] / $estadisticas["total_pilotos"], 0) : 0;

    $sql = "SELECT race, COUNT(*) as cantidad FROM PILOTS " . $filtro_commander . " GROUP BY race ORDER BY cantidad DESC";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $estadisticas["razas"] = [];
    while ($row = $result->fetch_assoc()) {
        $estadisticas["razas"][] = $row;
    }

    $sql = "SELECT acctype, COUNT(*) as cantidad FROM PILOTS " . $filtro_commander . " GROUP BY acctype";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $estadisticas["tipos_cuenta"] = [];
    while ($row = $result->fetch_assoc()) {
        $estadisticas["tipos_cuenta"][] = $row;
    }

    // CHANGED: LIMIT 5 to LIMIT 4
    $sql = "SELECT toon_number, toon_name, skillpoints, race, acctype FROM PILOTS " . $filtro_commander . " ORDER BY skillpoints DESC LIMIT 4";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $estadisticas["top_skillpoints"] = [];
    while ($row = $result->fetch_assoc()) {
        $estadisticas["top_skillpoints"][] = $row;
    }

    $sql = "SELECT toon_number, toon_name, lastsaved, skillpoints FROM PILOTS " . $filtro_commander . " ORDER BY lastsaved DESC LIMIT 4";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $estadisticas["ultimos_actualizados"] = [];
    while ($row = $result->fetch_assoc()) {
        $estadisticas["ultimos_actualizados"][] = $row;
    }

    // ADDED: Top 4 wallets
    $sql = "SELECT toon_number, toon_name, wallet FROM PILOTS " . $filtro_commander . " and wallet IS NOT NULL ORDER BY wallet DESC LIMIT 4";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $estadisticas["top_wallets"] = [];
    while ($row = $result->fetch_assoc()) {
        $estadisticas["top_wallets"][] = $row;
    }

    $sql = "SELECT AVG(security) as avg_security, MIN(security) as min_security, MAX(security) as max_security FROM PILOTS " . $filtro_commander . " and security IS NOT NULL";
    $stmt = $conexion->prepare($sql);
    if ($commander_id > 0) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $estadisticas["seguridad"] = $stmt->get_result()->fetch_assoc();

    return $estadisticas;
}

// ============================================================================
// OBTENER DATOS DE PILOTOS PARA SECCION 1 (Qwen) - por supergroup
// ============================================================================
$pilots_grupo1 = [];
$totalPilots_grupo1 = 0;
$totalShips_grupo1 = 0;
$activePilots_grupo1 = 0;

if ($link && !$link->connect_error) {
    $stmt1 = $link->prepare("SELECT toon_number, toon_name, corporation_name, race, security, skillpoints, 
                                    current_ship, current_location, numships, wallet, lastsaved, acctype, 
                                    email_pilot, NPC_rep, tradefield, pocket6
                             FROM PILOTS 
                             WHERE supergroup = ?
                             ORDER BY toon_name ASC");
    $stmt1->bind_param("i", $GRUPO_1_SUPERGROUP);
    $stmt1->execute();
    $result1 = $stmt1->get_result();

    if ($result1 && $result1->num_rows > 0) {
        while ($row = $result1->fetch_assoc()) {
            $pilots_grupo1[] = $row;
            $totalPilots_grupo1++;
            $totalShips_grupo1 += (int)$row["numships"];
            if (!empty($row["current_ship"])) {
                $activePilots_grupo1++;
            }
        }
    }
}

// ============================================================================
// DATOS SECCION 2 (DeepSeek)
// ============================================================================
$integridad = verificarIntegridadFlota($link, $fleet_commander_character_id);
$mostrar_contenido = $integridad["valido"];

$estadisticas = [];

if ($mostrar_contenido) {
    $estadisticas = obtenerEstadisticasFlota($link, $fleet_commander_character_id);
}

$db_error = ($link && $link->connect_error) ? "Error de conexion: " . $link->connect_error : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilot Manager - Fleet Integrity</title>

    <!-- Bootstrap 4.6.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #2a5298;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e3c72;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .pilot-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .table thead th {
            background: #1e3c72;
            color: white;
            border: none;
        }

        .badge-omega {
            background: #ff6b6b;
            color: white;
        }

        .badge-alpha {
            background: #4ecdc4;
            color: white;
        }

        .badge-commander {
            background: #ffd700;
            color: #1e3c72;
        }

        .search-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .pagination-custom {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .race-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .race-caldari { background: #3498db; }
        .race-gallente { background: #2ecc71; }
        .race-amarr { background: #f39c12; }
        .race-minmatar { background: #e74c3c; }

        .alert-warning-custom {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .alert-danger-custom {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .alert-success-custom {
            background: #d4edda;
            border-left: 4px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        footer {
            margin-top: 40px;
            text-align: center;
            color: white;
        }

        .btn-flota {
            background: #ff6b6b;
            color: white;
            border: none;
        }

        .btn-flota:hover {
            background: #ff5252;
            color: white;
        }

        .commander-info {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #1e3c72;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .supergroup-badge {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .section-divider {
            border-top: 3px solid rgba(255,255,255,0.3);
            margin: 40px 0;
            position: relative;
        }

        .section-divider::after {
            content: attr(data-label);
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #2a5298;
            color: white;
            padding: 5px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .pilot-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 10px;
        }

        .status-badge {
            min-width: 80px;
        }

        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(30, 60, 114, 0.95);
            border-top: 2px solid rgba(255,255,255,0.2);
            padding: 10px 0;
            z-index: 1000;
        }

        .content-wrapper {
            padding-bottom: 80px;
        }

        .attribution-note {
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 30px;
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
        }

        /* Menu de navegacion superior */
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            border-bottom: 3px solid #ffd700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            padding: 0.8rem 1rem;
        }

        .navbar-custom .navbar-brand {
            font-weight: bold;
            font-size: 1.3rem;
            color: #ffd700 !important;
        }

        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
        }

        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active {
            color: #ffd700 !important;
            background: rgba(255,255,255,0.1);
        }

        .navbar-custom .dropdown-menu {
            background: #1e3c72;
            border: 1px solid rgba(255,255,255,0.2);
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .navbar-custom .dropdown-item {
            color: rgba(255,255,255,0.9);
            transition: all 0.2s;
        }

        .navbar-custom .dropdown-item:hover {
            background: rgba(255,215,0,0.2);
            color: #ffd700;
        }

        .navbar-custom .dropdown-divider {
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .navbar-custom .navbar-text {
            color: rgba(255,255,255,0.8) !important;
        }

        .pocket6-badge {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .ship-name-cell {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #1e3c72;
            font-weight: 600;
        }

        .cmdr-under-name {
            display: block;
            font-size: 0.75rem;
            margin-top: 2px;
        }
    </style>
</head>
<body>

<!-- ============================================================================ -->
<!-- MENU DE NAVEGACION SUPERIOR - DEL SCRIPT 1 (Qwen)                           -->
<!-- ============================================================================ -->
<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo basename(__FILE__); ?>"></a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarTop">
            <span class="navbar-toggler-icon" style="color: white;"><i class="fas fa-bars"></i></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarTop">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                        <i class="fas fa-list"></i> Gestión
                    </a>
                    <div class="dropdown-menu">
                        <a class="dropdown-item" href="mosaic.php"><i class="fas fa-th-large"></i> Mosaic</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#"><i class="fas fa-users"></i> Ver Todos</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-user-check"></i> Activos</a>
                        <a class="dropdown-item" href="#"><i class="fas fa-user-times"></i> Inactivos</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#"><i class="fas fa-file-export"></i> Exportar</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container content-wrapper" style="padding-top: 90px;">

    <!-- Titulo principal -->
    <div class="text-center mb-4">
        <h1 class="display-4 text-white">
            <i class="fas fa-user-astronaut"></i> Pilot Manager
        </h1>
        <p class="lead text-white-50">
            Integridad y control de la flota
        </p>
    </div>

    <!-- Informacion del comandante de flota -->
    <?php if ($fleet_commander_character_id > 0): ?>
        <div class="commander-info text-center">
            <i class="fas fa-crown"></i> 
            <strong>Comandante de Flota ID: <?php echo $fleet_commander_character_id; ?></strong>
            <i class="fas fa-chevron-right"></i> 
            Verificando integridad de la flota...
        </div>
    <?php endif; ?>

    <!-- Alerta de Error de Base de Datos -->
    <?php if (!empty($db_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <strong>Error de Base de Datos:</strong> <?php echo $db_error; ?>
        <button type="button" class="close" data-dismiss="alert">
            <span>&times;</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Verificacion de integridad de la flota -->
    <?php if (!$integridad["valido"]): ?>
        <div class="alert-danger-custom">
            <h4 class="alert-heading">
                <i class="fas fa-exclamation-triangle"></i> 
                ¡ALERTA! Problema de integridad en la flota
            </h4>
            <p class="mb-2">
                <strong><?php echo $integridad["mensaje"]; ?></strong>
            </p>
            <p>
                <i class="fas fa-chart-line"></i> Total de pilotos en BD: <?php echo $integridad["total_pilotos"]; ?><br>
                <i class="fas fa-check-circle text-success"></i> Pilotos válidos: <?php echo $integridad["pilotos_validos"]; ?><br>
                <i class="fas fa-times-circle text-danger"></i> Pilotos asignados a otro comandante: <?php echo $integridad["pilotos_invalidos"]; ?>
            </p>

            <?php if (count($integridad["pilotos_inconsistentes"]) > 0): ?>
                <hr>
                <h6><i class="fas fa-list"></i> Pilotos asignados a otro comandante:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered bg-white">
                        <thead class="thead-dark">
                            <tr>
                                <th>Número de Toon</th>
                                <th>Nombre</th>
                                <th>Parent Toon Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($integridad["pilotos_inconsistentes"] as $piloto): ?>
                                <tr class="table-danger">
                                    <td><?php echo $piloto["toon_number"]; ?></td>
                                    <td><?php echo htmlspecialchars($piloto["toon_name"]); ?></td>
                                    <td><?php echo $piloto["parent_toon_number"] ?: "0"; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (count($integridad["huerfanos"]) > 0): ?>
                <hr>
                <h6><i class="fas fa-list"></i> Pilotos huérfanos (sin comandante asignado):</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered bg-white">
                        <thead class="thead-dark">
                            <tr>
                                <th>Número de Toon</th>
                                <th>Nombre</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($integridad["huerfanos"] as $huerfano): ?>
                                <tr class="table-danger">
                                    <td><?php echo $huerfano["toon_number"]; ?></td>
                                    <td><?php echo htmlspecialchars($huerfano["toon_name"]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <hr>
            <p class="mb-0">
                <i class="fas fa-info-circle"></i> 
                Para continuar, asegúrate de que todos los pilotos tengan 
                <strong>parent_toon_number = <?php echo $fleet_commander_character_id; ?></strong>
            </p>
        </div>

        <div class="text-center mt-3">
            <a href="?commander=<?php echo $fleet_commander_character_id; ?>&forzar=1" class="btn btn-warning">
                <i class="fas fa-eye"></i> Ver de todas formas (no recomendado)
            </a>
        </div>

    <?php else: ?>

        <!-- Mensaje de exito en integridad -->
        <div class="alert-success-custom">
            <h5 class="mb-0">
                <i class="fas fa-check-circle"></i> 
                <?php echo $integridad["mensaje"]; ?>
            </h5>
        </div>

        <!-- Aviso de pilotos huerfanos (parent_toon_number = 0) -->
        <?php if ($integridad["huerfanos_count"] > 0): ?>
        <div class="alert-danger-custom">
            <h5 class="mb-2">
                <i class="fas fa-exclamation-triangle"></i>
                Se encontraron <?php echo $integridad["huerfanos_count"]; ?> piloto(s) huérfano(s) (sin comandante asignado)
            </h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>Número de Toon</th>
                            <th>Nombre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integridad["huerfanos"] as $huerfano): ?>
                            <tr class="table-danger">
                                <td><?php echo $huerfano["toon_number"]; ?></td>
                                <td><?php echo htmlspecialchars($huerfano["toon_name"]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- TARJETAS DE ESTADISTICAS:                                        -->
        <!-- ================================================================ -->
        <div class="row mb-4">
            <!-- Fila 1: Estadisticas DeepSeek (4 tarjetas) -->
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-user-astronaut"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["total_pilotos"], 0, ",", "."); ?></div>
                    <div class="stat-label">Total Pilotos</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["total_sp"] / 1000000, 1); ?>M</div>
                    <div class="stat-label">Skillpoints Totales</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["promedio_sp"] / 1000000, 1); ?>M</div>
                    <div class="stat-label">Promedio SP</div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["seguridad"]["avg_security"] ?? 0, 2); ?></div>
                    <div class="stat-label">Seguridad Promedio</div>
                </div>
            </div>
        </div>

        <!-- Fila 2: Estadisticas  -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-users text-primary"></i></div>
                    <div class="stat-value"><?php echo $totalPilots_grupo1; ?></div>
                    <div class="stat-label">Grupo <?php echo $GRUPO_1_SUPERGROUP; ?> - Total Pilotos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-user-check text-success"></i></div>
                    <div class="stat-value"><?php echo $activePilots_grupo1; ?></div>
                    <div class="stat-label">Grupo <?php echo $GRUPO_1_SUPERGROUP; ?> - Activos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-shuttle-space text-warning"></i></div>
                    <div class="stat-value"><?php echo formatNumber($totalShips_grupo1); ?></div>
                    <div class="stat-label">Grupo <?php echo $GRUPO_1_SUPERGROUP; ?> - Total Naves</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon"><i class="fas fa-calculator text-info"></i></div>
                    <div class="stat-value"><?php echo $totalPilots_grupo1 > 0 ? round($totalShips_grupo1 / $totalPilots_grupo1, 1) : 0; ?></div>
                    <div class="stat-label">Grupo <?php echo $GRUPO_1_SUPERGROUP; ?> - Naves/Piloto</div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECCION 2: CONTENIDO DEEPSEEK - ESTADISTICAS AVANZADAS (GRUPO 2) -->
        <!-- ================================================================ -->
        <div class="section-divider" data-label="SECCION 2: Comparación de Distribución de Habilidades (DeepSeek)"></div>

        <div class="row mb-4">
            <div class="col-md-12 text-center">
                <h2 class="text-white">
                    <i class="fas fa-chart-bar"></i> Comparación de Distribución de Habilidades
                </h2>
                <p class="text-white-50">Comparando la distribución de habilidades entre pilotos del Grupo <?php echo $GRUPO_2_SUPERGROUP; ?> (supergroup = <?php echo $GRUPO_2_SUPERGROUP; ?>)</p>
            </div>
        </div>

        <!-- Segunda fila de estadisticas -->
        <?php if ($estadisticas["total_pilotos"] > 0): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <h5><i class="fas fa-chart-pie"></i> Distribucion por Raza</h5>
                        <canvas id="raceChart" height="140"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="stat-card">
                        <h5><i class="fas fa-crown"></i> Top 4 Skillpoints</h5>
                        <div class="list-group">
                            <?php if (count($estadisticas["top_skillpoints"]) > 0): ?>
                                <?php foreach ($estadisticas["top_skillpoints"] as $top): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="race-icon race-<?php echo strtolower($top["race"] ?? "minmatar"); ?>"></span>
                                            <strong><?php echo htmlspecialchars($top["toon_name"]); ?></strong>
                                            <small class="text-muted ml-2">(#<?php echo $top["toon_number"]; ?>)</small>
                                        </div>
                                        <span class="badge badge-primary badge-pill">
                                            <?php echo number_format($top["skillpoints"] / 1000000, 1); ?>M SP
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item text-center text-muted">
                                    No hay datos disponibles
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <h5><i class="fas fa-money-bill-wave text-success"></i> Top 4 Wallets</h5>
                        <div class="list-group">
                            <?php if (count($estadisticas["top_wallets"]) > 0): ?>
                                <?php foreach ($estadisticas["top_wallets"] as $topw): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($topw["toon_name"]); ?></strong>
                                            <small class="text-muted ml-2">(#<?php echo $topw["toon_number"]; ?>)</small>
                                        </div>
                                        <span class="badge badge-success badge-pill">
                                            <?php echo number_format(($topw["wallet"] ?? 0) / 1000000, 2); ?> M ISK
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item text-center text-muted">
                                    No hay datos de wallet disponibles
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PLACEHOLDER -->
            <div class="pilot-table mb-4">
                <h5 class="p-3 mb-0 bg-secondary text-white text-center">
                    <i class="fas fa-tools"></i> PRÓXIMAMENTE - Más contenido en camino
                </h5>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-hard-hat fa-3x mb-3"></i>
                    <p>Esta sección está reservada para contenido futuro.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="alert-warning-custom text-center">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <h5>No hay pilotos registrados para este comandante</h5>
                <p class="mb-0">Agrega pilotos con parent_toon_number = <?php echo $fleet_commander_character_id; ?> o parent_toon_number = 0</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Nota de Atribucion -->
    <div class="attribution-note text-center">
        <i class="fas fa-code"></i> <strong>Atribucion:</strong> Este archivo fue realizado parte por <strong>Qwen</strong> (menu de navegacion superior) 
        y parte por <strong>DeepSeek R1</strong> (seccion de integridad de flota, estadisticas avanzadas, graficos Chart.js, buscador con paginacion, badges Omega/Alpha, supergroup). 
        <br><i class="fas fa-palette"></i> Colores y presentacion: estilo DeepSeek.
        <br><i class="fas fa-calendar"></i> Fecha de fusion: 2026-06-20 | <i class="fas fa-file-code"></i> Archivo: <?php echo basename(__FILE__); ?> | <i class="fas fa-database"></i> PHP <?php echo phpversion(); ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <hr class="bg-light">
            <p>
                <i class="fas fa-code"></i> Modelos: Qwen + DeepSeek R1 | 
                <i class="fas fa-calendar"></i> Fecha: 2026-06-20 |
                <i class="fas fa-file-code"></i> Archivo: <?php echo basename(__FILE__); ?> |
                <i class="fas fa-database"></i> PHP <?php echo phpversion(); ?>
            </p>
            <p>
                <i class="fas fa-chart-line"></i> Integridad de la flota verificada
                <?php if ($fleet_commander_character_id > 0): ?>
                    | <i class="fas fa-user-tie"></i> Commander ID: <?php echo $fleet_commander_character_id; ?>
                <?php endif; ?>
                | <i class="fas fa-database"></i> <?php echo empty($db_error) ? "Conectado" : "Error DB"; ?>
            </p>
        </div>
    </footer>
</div>

<!-- Fixed Footer Bar -->
<div class="fixed-footer">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-4 text-left">
                <span class="text-white-50 small ml-3">
                    <i class="fas fa-anchor mr-1"></i> Estado de Flota: En línea
                </span>
            </div>
            <div class="col-4 text-center">
                <div class="btn-group">
                    <a href="mosaic.php" class="btn btn-link text-white p-2" title="Mosaic"><i class="fas fa-th-large"></i></a>
                    <a href="<?php echo basename(__FILE__); ?>" class="btn btn-link text-white p-2" title="Pilotos"><i class="fas fa-user-astronaut"></i></a>
                    <a href="#" class="btn btn-link text-white p-2" title="Buscar"><i class="fas fa-search"></i></a>
                    <a href="#" class="btn btn-link text-white p-2" title="Configuracion"><i class="fas fa-cog"></i></a>
                </div>
            </div>
            <div class="col-4 text-right">
                <span class="text-muted small mr-3">v1.0.3 | <?php echo date("H:i"); ?> EVE</span>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($mostrar_contenido && isset($estadisticas["razas"]) && count($estadisticas["razas"]) > 0): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("raceChart").getContext("2d");
    var raceData = <?php 
        $razas = ["labels" => [], "data" => []];
        foreach ($estadisticas["razas"] as $raza) {
            $razas["labels"][] = $raza["race"] ?: "Desconocida";
            $razas["data"][] = $raza["cantidad"];
        }
        echo json_encode($razas);
    ?>;

    if (raceData.labels && raceData.labels.length > 0) {
        new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: raceData.labels,
                datasets: [{
                    data: raceData.data,
                    backgroundColor: ["#3498db", "#2ecc71", "#f39c12", "#e74c3c", "#95a5a6"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { position: "bottom" } }
            }
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
<?php $link->close(); ?>
