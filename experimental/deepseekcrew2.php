<?php
/**
 * File: deepseekcrew2.php
 * Date: 2026-06-20
 * Description: Fleet pilot dashboard with fleet integrity.
 *              Merged from two scripts: Qwen logic (top) + DeepSeek (bottom).
 *              Redesigned with Fleet Commander dark aesthetic.
 * Models: DeepSeek R1 (March 2026) | Aesthetic: Fleet Commander
 */

// ============================================================================
// GROUP CONFIGURATION - MODIFY GROUP NAMES HERE (TEXT STRINGS)
// ============================================================================
$GRUPO_1_NOMBRES = ["Alpha", "Bravo", "Charlie", "Delta"];   // Section 1 pilots
$GRUPO_2_NOMBRES = ["Echo", "Foxtrot", "Golf", "Hotel"];      // Section 2 comparison

$GRUPO_1_SUPERGROUP = 1;   // Section Qwen (top)
$GRUPO_2_SUPERGROUP = 2;   // Section DeepSeek / comparison (bottom)

// ============================================================================
// DATABASE CONNECTION - USES $link (established in abyss/config.php)
// ============================================================================
require "../config.php";
check_authorization();

// Fleet commander variable
$fleet_commander_character_id = isset($_GET["commander"]) ? (int)$_GET["commander"] : 0;
$fleet_commander_character_id = 2112061747;
if ($fleet_commander_character_id === 0 && isset($_SESSION["fleet_commander_character_id"])) {
    $fleet_commander_character_id = (int)$_SESSION["fleet_commander_character_id"];
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function formatDate($date) {
    if (empty($date) || $date === "0000-00-00 00:00:00") {
        return "<span style=\"color:#484f58\">N/A</span>";
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

function formatMillions($value) {
    if (empty($value)) return '0.00';
    return number_format($value / 1000000, 2);
}

function getPilotStatus($lastsaved) {
    if (empty($lastsaved) || $lastsaved === "0000-00-00 00:00:00") {
        return ["class" => "secondary", "label" => "No Data"];
    }
    $last = strtotime($lastsaved);
    $now = time();
    $diff = ($now - $last) / 3600;
    if ($diff < 24) {
        return ["class" => "success", "label" => "Active"];
    } elseif ($diff < 168) {
        return ["class" => "warning", "label" => "Recent"];
    } else {
        return ["class" => "danger", "label" => "Inactive"];
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
        $resultado["mensaje"] = "No fleet commander has been defined (fleet_commander_character_id)";
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
        $resultado["valido"] = false;
        $resultado["mensaje"] = "Found " . $otros_count . " pilot(s) assigned to another fleet commander (not ID: " . $commander_id . ")";
        if ($huerfanos_count > 0) {
            $resultado["mensaje"] .= " and " . $huerfanos_count . " orphan pilot(s) (no commander assigned)";
        }
    } else {
        $resultado["valido"] = true;
        $resultado["mensaje"] = "The " . $pilotos_validos . " pilots belong to the fleet commander (ID: " . $commander_id . ")";
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
// OBTENER PILOTOS PARA SECCION 2 (DeepSeek) - por supergroup con nombres
// ============================================================================
$pilots_grupo2 = [];
if ($link && !$link->connect_error) {
    $stmt2 = $link->prepare("SELECT toon_number, toon_name, corporation_name, race, security, skillpoints,
                                    current_ship, current_location, numships, wallet, lastsaved, acctype,
                                    email_pilot, NPC_rep, tradefield, pocket6
                             FROM PILOTS
                             WHERE supergroup = ?
                             ORDER BY toon_name ASC");
    $stmt2->bind_param("i", $GRUPO_2_SUPERGROUP);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($result2 && $result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            $pilots_grupo2[] = $row;
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

$db_error = ($link && $link->connect_error) ? "Connection error: " . $link->connect_error : "";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Commander - Pilot Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f2e 50%, #0d1117 100%);
            min-height: 100vh;
            color: #c9d1d9;
            padding-top: 20px;
            padding-bottom: 20px;
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .main-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            color: #58a6ff;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #8b949e;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #238636 0%, #1a6328 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(35, 134, 54, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #d29922 0%, #9e6f17 100%);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(210, 153, 34, 0.4);
        }

        /* ============================================
           SECURITY ALERTS
           ============================================ */
        .security-alert {
            background: rgba(248, 81, 73, 0.15);
            border: 2px solid #f85149;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .security-alert i {
            font-size: 48px;
            color: #f85149;
            margin-bottom: 15px;
        }

        .security-alert h2 {
            color: #f85149;
            margin-bottom: 10px;
        }

        .security-alert p {
            color: #c9d1d9;
            font-size: 16px;
        }

        .security-alert table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            background: rgba(13, 17, 23, 0.8);
            border-radius: 8px;
            overflow: hidden;
        }

        .security-alert table thead th {
            background: rgba(248, 81, 73, 0.2);
            color: #f85149;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .security-alert table tbody td {
            padding: 10px 15px;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
        }

        .security-alert table tbody tr:hover {
            background: rgba(248, 81, 73, 0.05);
        }

        .alert-success-custom {
            background: rgba(63, 185, 80, 0.15);
            border: 1px solid rgba(63, 185, 80, 0.3);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success-custom i {
            color: #3fb950;
            font-size: 20px;
        }

        .alert-success-custom h5 {
            color: #3fb950;
            margin: 0;
            font-size: 15px;
        }

        .alert-danger-custom {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid rgba(248, 81, 73, 0.3);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 20px;
        }

        .alert-danger-custom h5 {
            color: #f85149;
            margin-bottom: 12px;
        }

        .alert-danger-custom table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(13, 17, 23, 0.8);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
        }

        .alert-danger-custom table thead th {
            background: rgba(248, 81, 73, 0.2);
            color: #f85149;
            padding: 10px 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .alert-danger-custom table tbody td {
            padding: 8px 15px;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
        }

        /* ============================================
           STAT CARDS
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 24px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #58a6ff;
            box-shadow: 0 8px 25px rgba(88, 166, 255, 0.1);
        }

        .stat-icon {
            font-size: 2.2rem;
            color: #58a6ff;
            margin-bottom: 12px;
        }

        .stat-value {
            font-family: 'Courier New', monospace;
            font-size: 1.6rem;
            font-weight: 700;
            color: #c9d1d9;
            margin-bottom: 6px;
        }

        .stat-label {
            color: #8b949e;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ============================================
           TABLES
           ============================================ */
        .pilots-container {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .pilots-container h5 {
            color: #58a6ff;
            margin-bottom: 15px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: transparent;
        }

        table thead th {
            background: rgba(13, 17, 23, 0.95);
            color: #58a6ff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 2px solid #30363d;
            padding: 12px 10px;
            text-align: center;
        }

        table thead th:first-child {
            text-align: left;
        }

        table tbody td {
            padding: 10px;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
            vertical-align: middle;
            text-align: center;
            font-size: 13px;
        }

        table tbody td:first-child {
            text-align: left;
        }

        table tbody tr:hover {
            background: rgba(48, 54, 61, 0.3);
        }

        /* ============================================
           PILOT INFO CELL
           ============================================ */
        .pilot-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pilot-portrait {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid #30363d;
            object-fit: cover;
        }

        .pilot-name {
            font-weight: 600;
            color: #c9d1d9;
            font-size: 13px;
        }

        .pilot-number {
            font-size: 10px;
            color: #6e7681;
            font-family: 'Courier New', monospace;
        }

        /* ============================================
           BADGES & STATUS
           ============================================ */
        .pocket-status {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .pocket-clean {
            background: rgba(63, 185, 80, 0.15);
            color: #3fb950;
        }

        .pocket-warning {
            background: rgba(210, 153, 34, 0.15);
            color: #d29922;
        }

        .pocket-danger {
            background: rgba(248, 81, 73, 0.15);
            color: #f85149;
        }

        .pocket-secondary {
            background: rgba(139, 148, 158, 0.15);
            color: #8b949e;
        }

        .badge {
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-omega {
            background: rgba(255, 107, 107, 0.2);
            color: #ff6b6b;
        }

        .badge-alpha {
            background: rgba(78, 205, 196, 0.2);
            color: #4ecdc4;
        }

        .badge-primary {
            background: rgba(88, 166, 255, 0.2);
            color: #58a6ff;
        }

        .badge-success {
            background: rgba(63, 185, 80, 0.2);
            color: #3fb950;
        }

        .security-high { color: #3fb950; font-weight: 600; font-family: 'Courier New', monospace; }
        .security-medium { color: #d29922; font-weight: 600; font-family: 'Courier New', monospace; }
        .security-low { color: #f85149; font-weight: 600; font-family: 'Courier New', monospace; }

        .sp-value {
            font-family: 'Courier New', monospace;
            color: #7ee787;
            font-weight: 600;
            font-size: 12px;
        }

        .wallet-value {
            font-family: 'Courier New', monospace;
            color: #3fb950;
            font-weight: 600;
            font-size: 12px;
        }

        .ship-name-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #a371f7;
            font-weight: 600;
        }

        .stat-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 12px;
        }

        .stat-good { color: #3fb950; }
        .stat-warning { color: #d29922; }
        .stat-danger { color: #f85149; }
        .stat-inactive { color: #484f58; }

        .race-icon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .race-caldari { background: #3498db; }
        .race-gallente { background: #2ecc71; }
        .race-amarr { background: #f39c12; }
        .race-minmatar { background: #e74c3c; }

        /* ============================================
           SECTION DIVIDER
           ============================================ */
        .section-divider {
            margin: 40px 0;
            position: relative;
            text-align: center;
        }

        .section-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #30363d, transparent);
        }

        .section-divider span {
            position: relative;
            background: #0d1117;
            color: #58a6ff;
            padding: 8px 25px;
            border-radius: 20px;
            border: 1px solid #30363d;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ============================================
           COMMANDER INFO
           ============================================ */
        .commander-info {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 15px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #d29922;
            font-weight: 600;
            font-size: 14px;
        }

        .commander-info i {
            color: #d29922;
            font-size: 18px;
        }

        /* ============================================
           LIST GROUP (TOP PILOTS)
           ============================================ */
        .list-group {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid #21262d;
            transition: background 0.2s ease;
        }

        .list-group-item:hover {
            background: rgba(48, 54, 61, 0.3);
        }

        .list-group-item:last-child {
            border-bottom: none;
        }

        .list-group-item strong {
            color: #c9d1d9;
            font-size: 13px;
        }

        .list-group-item small {
            color: #6e7681;
            font-size: 11px;
        }

        /* ============================================
           CHART CONTAINER
           ============================================ */
        .chart-container {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .chart-container h5 {
            color: #58a6ff;
            margin-bottom: 15px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ============================================
           COMING SOON / PLACEHOLDER
           ============================================ */
        .placeholder-box {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: #6e7681;
        }

        .placeholder-box i {
            font-size: 3rem;
            color: #484f58;
            margin-bottom: 15px;
        }

        /* ============================================
           ATTRIBUTION
           ============================================ */
        .attribution-note {
            background: rgba(22, 27, 34, 0.6);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            color: #8b949e;
            font-size: 0.8rem;
            text-align: center;
            line-height: 1.8;
        }

        .attribution-note i {
            color: #58a6ff;
        }

        /* ============================================
           GRID LAYOUT
           ============================================ */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .col-md-3, .col-md-4, .col-md-6, .col-md-12 {
            padding: 0 10px;
            margin-bottom: 20px;
        }

        .col-md-3 { flex: 0 0 25%; max-width: 25%; }
        .col-md-4 { flex: 0 0 33.333%; max-width: 33.333%; }
        .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        .col-md-12 { flex: 0 0 100%; max-width: 100%; }

        @media (max-width: 992px) {
            .col-md-3, .col-md-4, .col-md-6 { flex: 0 0 50%; max-width: 50%; }
        }

        @media (max-width: 768px) {
            .col-md-3, .col-md-4, .col-md-6, .col-md-12 { flex: 0 0 100%; max-width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }

        .text-center { text-align: center; }
        .text-white { color: #c9d1d9; }
        .text-white-50 { color: #8b949e; }
        .text-muted { color: #6e7681; }
        .mb-0 { margin-bottom: 0; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 25px; }
        .mt-3 { margin-top: 15px; }
        .p-3 { padding: 15px; }
        .p-4 { padding: 25px; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }
        .ml-2 { margin-left: 8px; }
        .gap-12 { gap: 12px; }

        /* Chart.js dark theme overrides */
        canvas {
            max-height: 200px;
        }
    </style>
</head>
<body>

    <main class="main-content">

        <!-- Titulo principal -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-astronaut"></i> Pilot Dashboard</h1>
                <p>Fleet Integrity and Control</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Informacion del comandante de flota -->
        <?php if ($fleet_commander_character_id > 0): ?>
        <div class="commander-info">
            <i class="fas fa-crown"></i>
            <span>Fleet Commander ID: <?php echo $fleet_commander_character_id; ?> <i class="fas fa-chevron-right" style="margin:0 8px;color:#484f58;"></i> Verifying fleet integrity...</span>
        </div>
        <?php endif; ?>

        <!-- Alerta de Error de Base de Datos -->
        <?php if (!empty($db_error)): ?>
        <div class="security-alert">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>Database Error</h2>
            <p><?php echo htmlspecialchars($db_error); ?></p>
        </div>
        <?php endif; ?>

        <!-- Verificacion de integridad de la flota -->
        <?php if (!$integridad["valido"]): ?>
        <div class="security-alert">
            <i class="fas fa-shield-alt"></i>
            <h2>ALERT! Fleet Integrity Problem</h2>
            <p><strong><?php echo $integridad["mensaje"]; ?></strong></p>
            <p style="margin-top:15px;">
                <i class="fas fa-chart-line"></i> Total pilots in DB: <?php echo $integridad["total_pilotos"]; ?><br>
                <i class="fas fa-check-circle" style="color:#3fb950;"></i> Valid pilots: <?php echo $integridad["pilotos_validos"]; ?><br>
                <i class="fas fa-times-circle" style="color:#f85149;"></i> Pilots assigned to another commander: <?php echo $integridad["pilotos_invalidos"]; ?>
            </p>

            <?php if (count($integridad["pilotos_inconsistentes"]) > 0): ?>
            <h5 style="color:#f85149;margin:20px 0 10px;text-align:left;"><i class="fas fa-list"></i> Pilots assigned to another commander:</h5>
            <table>
                <thead>
                    <tr>
                        <th>Toon Number</th>
                        <th>Name</th>
                        <th>Parent Toon Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($integridad["pilotos_inconsistentes"] as $piloto): ?>
                    <tr>
                        <td><?php echo $piloto["toon_number"]; ?></td>
                        <td><?php echo htmlspecialchars($piloto["toon_name"]); ?></td>
                        <td><?php echo $piloto["parent_toon_number"] ?: "0"; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (count($integridad["huerfanos"]) > 0): ?>
            <h5 style="color:#f85149;margin:20px 0 10px;text-align:left;"><i class="fas fa-list"></i> Orphan pilots (no commander assigned):</h5>
            <table>
                <thead>
                    <tr>
                        <th>Toon Number</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($integridad["huerfanos"] as $huerfano): ?>
                    <tr>
                        <td><?php echo $huerfano["toon_number"]; ?></td>
                        <td><?php echo htmlspecialchars($huerfano["toon_name"]); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <p style="margin-top:20px;">
                <i class="fas fa-info-circle"></i>
                To continue, make sure all pilots have <strong>parent_toon_number = <?php echo $fleet_commander_character_id; ?></strong>
            </p>
        </div>

        <div class="text-center mt-3">
            <a href="?commander=<?php echo $fleet_commander_character_id; ?>&forzar=1" class="btn btn-warning">
                <i class="fas fa-eye"></i> View anyway (not recommended)
            </a>
        </div>

        <?php else: ?>

        <!-- Integrity success message -->
        <div class="alert-success-custom">
            <i class="fas fa-check-circle"></i>
            <h5><?php echo $integridad["mensaje"]; ?></h5>
        </div>

        <!-- Orphan pilots warning -->
        <?php if ($integridad["huerfanos_count"] > 0): ?>
        <div class="alert-danger-custom">
            <h5><i class="fas fa-exclamation-triangle"></i> Found <?php echo $integridad["huerfanos_count"]; ?> orphan pilot(s) (no commander assigned)</h5>
            <table>
                <thead>
                    <tr>
                        <th>Toon Number</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($integridad["huerfanos"] as $huerfano): ?>
                    <tr>
                        <td><?php echo $huerfano["toon_number"]; ?></td>
                        <td><?php echo htmlspecialchars($huerfano["toon_name"]); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- STATISTICS CARDS -->
        <!-- ================================================================ -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-astronaut"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["total_pilotos"], 0, ",", "."); ?></div>
                    <div class="stat-label">Total Pilots</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["total_sp"] / 1000000, 1); ?>M</div>
                    <div class="stat-label">Total Skillpoints</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["promedio_sp"] / 1000000, 1); ?>M</div>
                    <div class="stat-label">Average SP</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="stat-value"><?php echo number_format($estadisticas["seguridad"]["avg_security"] ?? 0, 2); ?></div>
                    <div class="stat-label">Average Security</div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users" style="color:#a371f7;"></i></div>
                    <div class="stat-value"><?php echo $totalPilots_grupo1; ?></div>
                    <div class="stat-label">Group <?php echo $GRUPO_1_SUPERGROUP; ?> - Total Pilots</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-check" style="color:#3fb950;"></i></div>
                    <div class="stat-value"><?php echo $activePilots_grupo1; ?></div>
                    <div class="stat-label">Group <?php echo $GRUPO_1_SUPERGROUP; ?> - Actives</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shuttle-space" style="color:#d29922;"></i></div>
                    <div class="stat-value"><?php echo formatNumber($totalShips_grupo1); ?></div>
                    <div class="stat-label">Group <?php echo $GRUPO_1_SUPERGROUP; ?> - Total Ships</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calculator" style="color:#58a6ff;"></i></div>
                    <div class="stat-value"><?php echo $totalPilots_grupo1 > 0 ? round($totalShips_grupo1 / $totalPilots_grupo1, 1) : 0; ?></div>
                    <div class="stat-label">Group <?php echo $GRUPO_1_SUPERGROUP; ?> - Ships/Pilot</div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- SECCION 1: TABLA DE PILOTOS GRUPO 1 -->
        <!-- ================================================================ -->
        <div class="section-divider"><span>Section 1: Group <?php echo $GRUPO_1_SUPERGROUP; ?> Pilots</span></div>

        <div class="pilots-container">
            <h5><i class="fas fa-users"></i> Group <?php echo $GRUPO_1_SUPERGROUP; ?> Pilot Roster</h5>
            <table>
                <thead>
                    <tr>
                        <th>Pilot</th>
                        <th>Race</th>
                        <th>Security</th>
                        <th>SP (M)</th>
                        <th>Ship</th>
                        <th>Location</th>
                        <th>Wallet (M)</th>
                        <th>Status</th>
                        <th>Pocket6</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pilots_grupo1 as $pilot):
                        $status = getPilotStatus($pilot["lastsaved"] ?? null);
                        $status_class_map = ['success' => 'pocket-clean', 'warning' => 'pocket-warning', 'danger' => 'pocket-danger', 'secondary' => 'pocket-secondary'];
                        $status_css = $status_class_map[$status['class']] ?? 'pocket-secondary';
                        $pocket6 = strtoupper($pilot['pocket6'] ?? 'CLEAN');
                        $ship_display = parseShipName($pilot['current_ship'] ?? '');
                        $location_display = '-';
                        if (!empty($pilot['current_location'])) {
                            $location_data = json_decode(stripslashes($pilot['current_location']), true);
                            if (!empty($location_data['station_id'])) {
                                $location_display = 'Station ' . $location_data['station_id'];
                            } elseif (!empty($location_data['solar_system_id'])) {
                                $location_display = 'System: ' . $location_data['solar_system_id'];
                            }
                        }
                        $sec_val = (float)($pilot['security'] ?? 0);
                        $sec_class = $sec_val >= 0 ? 'security-high' : ($sec_val > -5 ? 'security-medium' : 'security-low');
                    ?>
                    <tr>
                        <td>
                            <div class="pilot-info-cell">
                                <img src="https://images.evetech.net/characters/<?php echo $pilot['toon_number']; ?>/portrait?size=64"
                                     alt="<?php echo htmlspecialchars($pilot['toon_name']); ?>"
                                     class="pilot-portrait"
                                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect fill=%22%2330363d%22 width=%2264%22 height=%2264%22/><text fill=%22%238b949e%22 x=%2232%22 y=%2236%22 text-anchor=%22middle%22 font-size=%2224%22>?</text></svg>'">
                                <div>
                                    <div class="pilot-name"><?php echo htmlspecialchars($pilot['toon_name']); ?></div>
                                    <div class="pilot-number">#<?php echo $pilot['toon_number']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="race-icon race-<?php echo strtolower($pilot['race'] ?? 'minmatar'); ?>"></span>
                            <?php echo htmlspecialchars($pilot['race'] ?? 'Unknown'); ?>
                        </td>
                        <td class="<?php echo $sec_class; ?>"><?php echo number_format($sec_val, 2); ?></td>
                        <td><div class="sp-value"><?php echo formatMillions($pilot['skillpoints']); ?></div></td>
                        <td class="ship-name-cell"><?php echo htmlspecialchars($ship_display); ?></td>
                        <td><?php echo htmlspecialchars($location_display); ?></td>
                        <td><div class="wallet-value"><?php echo formatMillions($pilot['wallet']); ?></div></td>
                        <td><span class="pocket-status <?php echo $status_css; ?>"><?php echo htmlspecialchars($status['label']); ?></span></td>
                        <td>
                            <?php if ($pocket6 !== 'CLEAN'): ?>
                            <span class="pocket-status pocket-danger"><?php echo htmlspecialchars($pocket6); ?></span>
                            <?php else: ?>
                            <span class="pocket-status pocket-clean">CLEAN</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (strtolower($pilot['acctype'] ?? '') === 'omega'): ?>
                            <span class="badge badge-omega">OMEGA</span>
                            <?php else: ?>
                            <span class="badge badge-alpha">ALPHA</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ================================================================ -->
        <!-- SECCION 2: SKILL DISTRIBUTION COMPARISON (GRUPO 2) -->
        <!-- ================================================================ -->
        <div class="section-divider"><span>Section 2: Skill Distribution Comparison - <?php echo implode(", ", $GRUPO_2_NOMBRES); ?></span></div>

        <div class="row mb-4">
            <div class="col-md-12 text-center mb-4">
                <h2 style="color:#58a6ff;font-size:22px;"><i class="fas fa-chart-bar"></i> Skill Distribution Comparison</h2>
                <p style="color:#8b949e;font-size:14px;">Comparing skill distribution among pilots: <?php echo implode(", ", $GRUPO_2_NOMBRES); ?></p>
            </div>
        </div>

        <!-- Tabla de pilotos Grupo 2 -->
        <div class="pilots-container">
            <h5><i class="fas fa-users"></i> Group <?php echo $GRUPO_2_SUPERGROUP; ?> Pilot Roster</h5>
            <table>
                <thead>
                    <tr>
                        <th>Pilot</th>
                        <th>Race</th>
                        <th>Security</th>
                        <th>SP (M)</th>
                        <th>Ship</th>
                        <th>Location</th>
                        <th>Wallet (M)</th>
                        <th>Status</th>
                        <th>Pocket6</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pilots_grupo2 as $pilot):
                        $status = getPilotStatus($pilot["lastsaved"] ?? null);
                        $status_class_map = ['success' => 'pocket-clean', 'warning' => 'pocket-warning', 'danger' => 'pocket-danger', 'secondary' => 'pocket-secondary'];
                        $status_css = $status_class_map[$status['class']] ?? 'pocket-secondary';
                        $pocket6 = strtoupper($pilot['pocket6'] ?? 'CLEAN');
                        $ship_display = parseShipName($pilot['current_ship'] ?? '');
                        $location_display = '-';
                        if (!empty($pilot['current_location'])) {
                            $location_data = json_decode(stripslashes($pilot['current_location']), true);
                            if (!empty($location_data['station_id'])) {
                                $location_display = 'Station ' . $location_data['station_id'];
                            } elseif (!empty($location_data['solar_system_id'])) {
                                $location_display = 'System: ' . $location_data['solar_system_id'];
                            }
                        }
                        $sec_val = (float)($pilot['security'] ?? 0);
                        $sec_class = $sec_val >= 0 ? 'security-high' : ($sec_val > -5 ? 'security-medium' : 'security-low');
                    ?>
                    <tr>
                        <td>
                            <div class="pilot-info-cell">
                                <img src="https://images.evetech.net/characters/<?php echo $pilot['toon_number']; ?>/portrait?size=64"
                                     alt="<?php echo htmlspecialchars($pilot['toon_name']); ?>"
                                     class="pilot-portrait"
                                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect fill=%22%2330363d%22 width=%2264%22 height=%2264%22/><text fill=%22%238b949e%22 x=%2232%22 y=%2236%22 text-anchor=%22middle%22 font-size=%2224%22>?</text></svg>'">
                                <div>
                                    <div class="pilot-name"><?php echo htmlspecialchars($pilot['toon_name']); ?></div>
                                    <div class="pilot-number">#<?php echo $pilot['toon_number']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="race-icon race-<?php echo strtolower($pilot['race'] ?? 'minmatar'); ?>"></span>
                            <?php echo htmlspecialchars($pilot['race'] ?? 'Unknown'); ?>
                        </td>
                        <td class="<?php echo $sec_class; ?>"><?php echo number_format($sec_val, 2); ?></td>
                        <td><div class="sp-value"><?php echo formatMillions($pilot['skillpoints']); ?></div></td>
                        <td class="ship-name-cell"><?php echo htmlspecialchars($ship_display); ?></td>
                        <td><?php echo htmlspecialchars($location_display); ?></td>
                        <td><div class="wallet-value"><?php echo formatMillions($pilot['wallet']); ?></div></td>
                        <td><span class="pocket-status <?php echo $status_css; ?>"><?php echo htmlspecialchars($status['label']); ?></span></td>
                        <td>
                            <?php if ($pocket6 !== 'CLEAN'): ?>
                            <span class="pocket-status pocket-danger"><?php echo htmlspecialchars($pocket6); ?></span>
                            <?php else: ?>
                            <span class="pocket-status pocket-clean">CLEAN</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (strtolower($pilot['acctype'] ?? '') === 'omega'): ?>
                            <span class="badge badge-omega">OMEGA</span>
                            <?php else: ?>
                            <span class="badge badge-alpha">ALPHA</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($estadisticas["total_pilotos"] > 0): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-chart-pie"></i> Distribution by Race</h5>
                    <canvas id="raceChart" height="180"></canvas>
                </div>
            </div>

            <div class="col-md-6">
                <div class="chart-container">
                    <h5><i class="fas fa-crown"></i> Top 4 Skillpoints</h5>
                    <div class="list-group">
                        <?php if (count($estadisticas["top_skillpoints"]) > 0): ?>
                            <?php foreach ($estadisticas["top_skillpoints"] as $top): ?>
                            <div class="list-group-item">
                                <div>
                                    <span class="race-icon race-<?php echo strtolower($top["race"] ?? "minmatar"); ?>"></span>
                                    <strong><?php echo htmlspecialchars($top["toon_name"]); ?></strong>
                                    <small class="ml-2">(#<?php echo $top["toon_number"]; ?>)</small>
                                </div>
                                <span class="badge badge-primary"><?php echo number_format($top["skillpoints"] / 1000000, 1); ?>M SP</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item" style="color:#6e7681;">No data available</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chart-container">
                    <h5><i class="fas fa-money-bill-wave" style="color:#3fb950;"></i> Top 4 Wallets</h5>
                    <div class="list-group">
                        <?php if (count($estadisticas["top_wallets"]) > 0): ?>
                            <?php foreach ($estadisticas["top_wallets"] as $topw): ?>
                            <div class="list-group-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($topw["toon_name"]); ?></strong>
                                    <small class="ml-2">(#<?php echo $topw["toon_number"]; ?>)</small>
                                </div>
                                <span class="badge badge-success"><?php echo number_format(($topw["wallet"] ?? 0) / 1000000, 2); ?> M ISK</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-group-item" style="color:#6e7681;">No wallet data available</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PLACEHOLDER -->
        <div class="placeholder-box">
            <i class="fas fa-tools"></i>
            <h5 style="color:#8b949e;margin-bottom:10px;">COMING SOON - More content on the way</h5>
            <p>This section is reserved for future content.</p>
        </div>

        <?php else: ?>
        <div class="security-alert" style="border-color:#d29922;background:rgba(210,153,34,0.1);">
            <i class="fas fa-info-circle" style="color:#d29922;"></i>
            <h2 style="color:#d29922;">No pilots registered for this commander</h2>
            <p>Add pilots with parent_toon_number = <?php echo $fleet_commander_character_id; ?> or parent_toon_number = 0</p>
        </div>
        <?php endif; ?>

        <?php endif; ?>

        <!-- Attribution Note -->
        <div class="attribution-note">
            <i class="fas fa-code"></i> <strong>Attribution:</strong> This file was created partly by <strong>Qwen</strong> (top navigation menu)
            and partly by <strong>DeepSeek R1</strong> (fleet integrity section, advanced statistics, Chart.js graphs, search with pagination, Omega/Alpha badges, supergroup).
            <br><i class="fas fa-palette"></i> Colors and presentation: Fleet Commander Dark Theme.
            <br><i class="fas fa-calendar"></i> Merge date: 2026-06-20 | <i class="fas fa-file-code"></i> File: <?php echo basename(__FILE__); ?> | <i class="fas fa-database"></i> PHP <?php echo phpversion(); ?>
        </div>

    </main>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>

<?php if ($mostrar_contenido && isset($estadisticas["razas"]) && count($estadisticas["razas"]) > 0): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("raceChart").getContext("2d");
    var raceData = <?php
        $razas = ["labels" => [], "data" => []];
        foreach ($estadisticas["razas"] as $raza) {
            $razas["labels"][] = $raza["race"] ?: "Unknown";
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
                    backgroundColor: ["#58a6ff", "#3fb950", "#d29922", "#f85149", "#a371f7"],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: { color: "#8b949e", font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

</body>
</html>
<?php $link->close(); ?>
