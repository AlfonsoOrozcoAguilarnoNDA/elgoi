<?php
/**
 * Fleet Commander - Pilot Management System with Supergroup Editing
 * Fecha: 2026-03-31 09:03
 * 
 * CONFIGURACIÓN INICIAL - Siempre trabajar de esta manera
 */

session_start();

// ============================================
// VERIFICACIÓN DE SESIÓN
// ============================================
/*if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    header('Location: ../fleet_login.php');
    exit;
}
*/

// ============================================
// INCLUIR CONFIGURACIÓN DE BD
// ============================================
require_once '../config.php';
check_authorization();
// Asume que $link ya está disponible como conexión MySQLi

// ============================================
// VERIFICAR Y CREAR CAMPO supergroup SI NO EXISTE
// ============================================
$check_column = mysqli_query($link, "SHOW COLUMNS FROM PILOTS LIKE 'supergroup'");
if (mysqli_num_rows($check_column) == 0) {
    $alter_sql = "ALTER TABLE PILOTS ADD COLUMN supergroup INT DEFAULT 1";
    mysqli_query($link, $alter_sql);
}

// ============================================
// PROCESAR ACTUALIZACIÓN AJAX DE SUPERGROUP
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supergroup') {
    header('Content-Type: application/json');
    
    $toon_number = intval($_POST['toon_number'] ?? 0);
    $new_supergroup = intval($_POST['supergroup'] ?? 1);
    
    // Verificar que el piloto pertenezca a este commander
    $verify_query = "SELECT parent_toon_number FROM PILOTS WHERE toon_number = ? LIMIT 1";
    $verify_stmt = mysqli_prepare($link, $verify_query);
    mysqli_stmt_bind_param($verify_stmt, "i", $toon_number);
    mysqli_stmt_execute($verify_stmt);
    $verify_result = mysqli_stmt_get_result($verify_stmt);
    
    if ($row = mysqli_fetch_assoc($verify_result)) {
        if ($row['parent_toon_number'] == ($_SESSION['character_id'] ?? 0)) {
            $update_query = "UPDATE PILOTS SET supergroup = ? WHERE toon_number = ?";
            $update_stmt = mysqli_prepare($link, $update_query);
            mysqli_stmt_bind_param($update_stmt, "ii", $new_supergroup, $toon_number);
            
            if (mysqli_stmt_execute($update_stmt)) {
                echo json_encode(['success' => true, 'message' => 'Supergroup updated']);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Unauthorized or error']);
    exit;
}

// ============================================
// VALIDACIÓN DE SEGURIDAD 1: Verificar otros Fleet Commanders
// ============================================
$fc_check_query = "SELECT fleet_commander_number, pilot_name, character_id 
                   FROM fleet_commanders 
                   WHERE installation_id = 1 AND character_id != ?";
$fc_stmt = mysqli_prepare($link, $fc_check_query);
$current_char_id = $_SESSION['character_id'] ?? 0;
mysqli_stmt_bind_param($fc_stmt, "i", $current_char_id);
mysqli_stmt_execute($fc_stmt);
$fc_result = mysqli_stmt_get_result($fc_stmt);

$security_error = '';
if (mysqli_num_rows($fc_result) > 0) {
    $other_fc = mysqli_fetch_assoc($fc_result);
    $security_error = "SECURITY ALERT: Another Fleet Commander detected in system. " .
                      "Access denied. Contact administrator.";
}

// ============================================
// VALIDACIÓN DE SEGURIDAD 2: Verificar integridad de pilotos
// ============================================
$integrity_error = '';
if (empty($security_error)) {
    $integrity_query = "SELECT COUNT(*) as foreign_pilots 
                        FROM PILOTS 
                        WHERE parent_toon_number != ? AND parent_toon_number != 0";
    $int_stmt = mysqli_prepare($link, $integrity_query);
    $session_char_id = $_SESSION['character_id'] ?? 0;
    mysqli_stmt_bind_param($int_stmt, "i", $session_char_id);
    mysqli_stmt_execute($int_stmt);
    $int_result = mysqli_stmt_get_result($int_stmt);
    $int_data = mysqli_fetch_assoc($int_result);
    
    if ($int_data['foreign_pilots'] > 0) {
        $integrity_error = "INTEGRITY VIOLATION: Found " . $int_data['foreign_pilots'] . 
                          " pilot(s) not belonging to this Fleet Commander. " .
                          "System halted for security reasons.";
    }
}

// ============================================
// SI HAY ERRORES DE SEGURIDAD, DETENER
// ============================================
if (!empty($security_error) || !empty($integrity_error)) {
    $error_message = !empty($security_error) ? $security_error : $integrity_error;
    $show_data = false;
    $pilots = [];
} else {
    // ============================================
    // OBTENER PILOTOS DEL FLEET COMMANDER
    // ============================================
    $show_data = true;
    $pilots_query = "SELECT 
                        toon_number,
                        toon_name,
                        tradefield,
                        security,
                        skillpoints,
                        unalloc,
                        acctype,
                        finishqueue,
                        wallet,
                        planets,
                        jobs,
                        queue,
                        numships,
                        pocket6,
                        daysq,
                        numitems,
                        evermarks,
                        numberfits,
                        supergroup
                     FROM PILOTS 
                     WHERE parent_toon_number = ? 
                     ORDER BY supergroup ASC, toon_name ASC";
    
    $pilot_stmt = mysqli_prepare($link, $pilots_query);
    $char_id = $_SESSION['character_id'] ?? 0;
    mysqli_stmt_bind_param($pilot_stmt, "i", $char_id);
    mysqli_stmt_execute($pilot_stmt);
    $pilots_result = mysqli_stmt_get_result($pilot_stmt);
    
    $pilots = [];
    $supergroups = []; // Para el filtro de DataTables
    
    while ($row = mysqli_fetch_assoc($pilots_result)) {
        $pilots[] = $row;
        $supergroups[$row['supergroup']] = true;
    }
    
    // Ordenar supergroups para el select
    ksort($supergroups);
}

// ============================================
// PROCESAR LOGOUT CON CONFIRMACIÓN
// ============================================
if (isset($_GET['logout']) && $_GET['logout'] === 'confirm') {
    session_destroy();
    header('Location: fleet_login.php');
    exit;
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================
function formatMillions($value) {
    if (empty($value)) return '0.00';
    return number_format($value / 1000000, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Commander - Pilot Management</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
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
            padding-top: 120px;
            padding-bottom: 60px;
        }
        
        /* ============================================
           BARRAS DE NAVEGACIÓN FIJAS
           ============================================ */
        .nav-bar {
            position: fixed;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(22, 27, 34, 0.98);
            border-bottom: 1px solid #30363d;
            backdrop-filter: blur(10px);
        }
        
        .nav-bar-top {
            top: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }
        
        .nav-bar-bottom {
            top: 60px;
            height: 50px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            background: rgba(13, 17, 23, 0.95);
        }
        
        .logo {
            color: #58a6ff;
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
        }
        
        .nav-links a {
            color: #8b949e;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: #58a6ff;
        }
        
        .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .pilot-badge {
            background: rgba(88, 166, 255, 0.15);
            border: 1px solid #58a6ff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            color: #58a6ff;
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #da3633 0%, #b62318 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(218, 54, 51, 0.4);
        }
        
        /* ============================================
           CONTENIDO PRINCIPAL
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
        
        /* ============================================
           ALERTAS DE SEGURIDAD
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
        
        /* ============================================
           DATATABLES CUSTOM STYLES
           ============================================ */
        .pilots-container {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
        }
        
        /* Override DataTables styles */
        .dataTables_wrapper {
            color: #c9d1d9;
        }
        
        .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
            margin-bottom: 15px;
            color: #8b949e !important;
        }
        
        .dataTables_length select, .dataTables_filter input {
            background: rgba(13, 17, 23, 0.95);
            border: 1px solid #30363d;
            color: #c9d1d9;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .dataTables_filter input:focus {
            outline: none;
            border-color: #58a6ff;
        }
        
        table.dataTable {
            background: transparent;
            border-collapse: collapse;
            width: 100% !important;
        }
        
        table.dataTable thead th {
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
        
        table.dataTable thead th:first-child {
            text-align: left;
        }
        
        table.dataTable tbody td {
            padding: 10px;
            border-bottom: 1px solid #21262d;
            color: #c9d1d9;
            vertical-align: middle;
            text-align: center;
        }
        
        table.dataTable tbody td:first-child {
            text-align: left;
        }
        
        table.dataTable tbody tr:hover {
            background: rgba(48, 54, 61, 0.3);
        }
        
        /* Paginación */
        .dataTables_paginate .paginate_button {
            background: rgba(48, 54, 61, 0.5) !important;
            border: 1px solid #30363d !important;
            color: #8b949e !important;
            border-radius: 4px !important;
            margin: 0 2px !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: rgba(88, 166, 255, 0.2) !important;
            border-color: #58a6ff !important;
            color: #58a6ff !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #58a6ff !important;
            border-color: #58a6ff !important;
            color: #0d1117 !important;
        }
        
        /* ============================================
           CELDAS ESPECÍFICAS
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
        
        .profession {
            background: rgba(88, 166, 255, 0.15);
            color: #58a6ff;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 500;
            display: inline-block;
        }
        
        .security-status {
            font-weight: 600;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        
        .security-high { color: #3fb950; }
        .security-medium { color: #d29922; }
        .security-low { color: #f85149; }
        
        .sp-value {
            font-family: 'Courier New', monospace;
            color: #7ee787;
            font-weight: 600;
            font-size: 12px;
        }
        
        .sp-unalloc {
            font-size: 10px;
            color: #d29922;
        }
        
        .acctype {
            background: rgba(139, 148, 158, 0.15);
            color: #8b949e;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            text-transform: uppercase;
        }
        
        .queue-finish {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #a371f7;
        }
        
        .wallet {
            font-family: 'Courier New', monospace;
            color: #3fb950;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-icon {
            font-size: 16px;
        }
        
        .status-active { color: #3fb950; }
        .status-inactive { color: #484f58; }
        .status-training { color: #a371f7; }
        .status-update { 
            color: #58a6ff; 
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .status-update:hover {
            transform: rotate(180deg);
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
        
        .pocket-status {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
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
        
        .evermarks {
            color: #a371f7;
            font-weight: 600;
            font-size: 12px;
        }
        
        /* ============================================
           SUPERGROUP EDITABLE
           ============================================ */
        .supergroup-cell {
            position: relative;
        }
        
        .supergroup-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 6px;
            background: rgba(88, 166, 255, 0.1);
            border: 1px solid #30363d;
            transition: all 0.3s ease;
        }
        
        .supergroup-display:hover {
            background: rgba(88, 166, 255, 0.2);
            border-color: #58a6ff;
        }
        
        .supergroup-value {
            font-weight: 600;
            color: #58a6ff;
            min-width: 30px;
            text-align: center;
        }
        
        .supergroup-edit {
            color: #8b949e;
            font-size: 12px;
        }
        
        .supergroup-form {
            display: none;
            align-items: center;
            gap: 8px;
        }
        
        .supergroup-form.active {
            display: inline-flex;
        }
        
        .supergroup-input {
            width: 60px;
            background: rgba(13, 17, 23, 0.95);
            border: 1px solid #58a6ff;
            color: #c9d1d9;
            padding: 5px 8px;
            border-radius: 4px;
            text-align: center;
            font-weight: 600;
        }
        
        .supergroup-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
        }
        
        .sg-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .sg-btn-save {
            background: #238636;
            color: white;
        }
        
        .sg-btn-save:hover {
            background: #3fb950;
        }
        
        .sg-btn-cancel {
            background: #30363d;
            color: #8b949e;
        }
        
        .sg-btn-cancel:hover {
            background: #484f58;
            color: #c9d1d9;
        }
        
        .sg-saving {
            color: #58a6ff;
            font-size: 12px;
        }
        
        /* ============================================
           FOOTER
           ============================================ */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50px;
            background: rgba(13, 17, 23, 0.98);
            border-top: 1px solid #30363d;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            font-size: 12px;
            color: #6e7681;
        }
        
        .footer-left {
            display: flex;
            gap: 20px;
        }
        
        .footer-right {
            color: #484f58;
        }
        
        /* ============================================
           MODAL
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: rgba(22, 27, 34, 0.98);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
        }
        
        .modal h3 {
            color: #f85149;
            margin-bottom: 15px;
        }
        
        .modal p {
            color: #8b949e;
            margin-bottom: 25px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 10px 25px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        
        .modal-btn.confirm {
            background: #da3633;
            color: white;
        }
        
        .modal-btn.cancel {
            background: #30363d;
            color: #c9d1d9;
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 140px;
            right: 30px;
            background: rgba(35, 134, 54, 0.95);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 3000;
            animation: slideIn 0.3s ease;
        }
        
        .toast.show {
            display: flex;
        }
        
        .toast.error {
            background: rgba(218, 54, 51, 0.95);
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- ============================================
         BARRA DE NAVEGACIÓN SUPERIOR
         ============================================ -->
    <nav class="nav-bar nav-bar-top">
        <div class="logo">⚡ Fleet Commander</div>
        <div class="nav-links">
            <a href="mosaic.php">Dashboard</a>
            <a href="pilots.php" class="active">Pilots</a>
            <a href="#">Fleet</a>
            <a href="#">Settings</a>
        </div>
        <div class="nav-info">
            <span class="pilot-badge">
                <?php echo htmlspecialchars($_SESSION['pilot_name'] ?? 'Unknown'); ?>
            </span>
            <button class="logout-btn" onclick="showLogoutModal()">Logout</button>
        </div>
    </nav>
    
    <!-- ============================================
         BARRA DE NAVEGACIÓN INFERIOR
         ============================================ -->
    <nav class="nav-bar nav-bar-bottom">
        <div class="nav-links">
            <a href="#" class="active">All Pilots</a>
            <a href="#">Active</a>
            <a href="#">Training</a>
            <a href="#">Industry</a>
        </div>
    </nav>
    
    <!-- ============================================
         CONTENIDO PRINCIPAL
         ============================================ -->
    <main class="main-content">
        
        <div class="page-header">
            <div>
                <h1><i class="fas fa-users"></i> Pilot Management</h1>
                <p>Manage your fleet pilots and their supergroups</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="refreshTable()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>
        
        <?php if (!empty($security_error) || !empty($integrity_error)): ?>
        <div class="security-alert">
            <i class="fas fa-shield-alt"></i>
            <h2>Security Violation Detected</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
        
        <?php else: ?>
        <div class="pilots-container">
            <table id="pilotsTable" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Pilot</th>
                        <th>Supergroup</th>
                        <th>Profession</th>
                        <th>Sec</th>
                        <th>SP (M)</th>
                        <th>Type</th>
                        <th>Queue End</th>
                        <th>Wallet (M)</th>
                        <th><i class="fas fa-globe"></i></th>
                        <th><i class="fas fa-industry"></i></th>
                        <th><i class="fas fa-graduation-cap"></i></th>
                        <th><i class="fas fa-sync-alt"></i></th>
                        <th>Ships</th>
                        <th>Pocket</th>
                        <th>DaysQ</th>
                        <th>Items</th>
                        <th>Evermarks</th>
                        <th>Fits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pilots as $pilot): 
                        $sec = floatval($pilot['security'] ?? 0);
                        if ($sec >= 0.5) {
                            $sec_class = 'security-high';
                        } elseif ($sec >= 0.0) {
                            $sec_class = 'security-medium';
                        } else {
                            $sec_class = 'security-low';
                        }
                        
                        $has_planets = ($pilot['planets'] ?? '') <> '[]';
                        $has_jobs = ($pilot['jobs'] ?? '') <> '[]';
                        $has_queue = ($pilot['queue'] ?? '') <> '[]';
                        
                        $pocket = strtoupper($pilot['pocket6'] ?? 'CLEAN');
                        if ($pocket === 'CLEAN') {
                            $pocket_class = 'pocket-clean';
                        } elseif (in_array($pocket, ['WARNING', 'CAUTION'])) {
                            $pocket_class = 'pocket-warning';
                        } else {
                            $pocket_class = 'pocket-danger';
                        }
                    ?>
                    <tr data-toon="<?php echo $pilot['toon_number']; ?>">
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
                        
                        <!-- SUPERGROUP EDITABLE -->
                        <td class="supergroup-cell">
                            <div class="supergroup-display" onclick="editSupergroup(<?php echo $pilot['toon_number']; ?>)">
                                <span class="supergroup-value" id="sg-value-<?php echo $pilot['toon_number']; ?>">
                                    <?php echo intval($pilot['supergroup'] ?? 1); ?>
                                </span>
                                <i class="fas fa-pencil-alt supergroup-edit"></i>
                            </div>
                            <div class="supergroup-form" id="sg-form-<?php echo $pilot['toon_number']; ?>">
                                <input type="number" 
                                       class="supergroup-input" 
                                       id="sg-input-<?php echo $pilot['toon_number']; ?>"
                                       value="<?php echo intval($pilot['supergroup'] ?? 1); ?>"
                                       min="1"
                                       max="999"
                                       onkeypress="handleEnter(event, <?php echo $pilot['toon_number']; ?>)">
                                <button class="sg-btn sg-btn-save" onclick="saveSupergroup(<?php echo $pilot['toon_number']; ?>)" title="Save">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="sg-btn sg-btn-cancel" onclick="cancelEdit(<?php echo $pilot['toon_number']; ?>)" title="Cancel">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </td>
                        
                        <td><span class="profession"><?php echo htmlspecialchars($pilot['tradefield'] ?? 'n/a'); ?></span></td>
                        
                        <td class="security-status <?php echo $sec_class; ?>"><?php echo number_format($sec, 2); ?></td>
                        
                        <td>
                            <div class="sp-value"><?php echo formatMillions($pilot['skillpoints']); ?></div>
                            <?php if (!empty($pilot['unalloc']) && $pilot['unalloc'] > 0): ?>
                            <div class="sp-unalloc">+<?php echo formatMillions($pilot['unalloc']); ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td><span class="acctype"><?php echo htmlspecialchars($pilot['acctype'] ?? 'Unknown'); ?></span></td>
                        
                        <td class="queue-finish">
                            <?php echo !empty($pilot['finishqueue']) ? date('Y-m-d H:i', strtotime($pilot['finishqueue'])) : '<span style="color:#484f58">-</span>'; ?>
                        </td>
                        
                        <td class="wallet"><?php echo formatMillions($pilot['wallet']); ?> M</td>
                        
                        <td class="status-icon">
                            <i class="fas fa-globe <?php echo $has_planets ? 'status-active' : 'status-inactive'; ?>" 
                               title="<?php echo $has_planets ? 'Has Planets' : 'No Planets'; ?>"></i>
                        </td>
                        
                        <td class="status-icon">
                            <i class="fas fa-industry <?php echo $has_jobs ? 'status-active' : 'status-inactive'; ?>" 
                               title="<?php echo $has_jobs ? 'Has Jobs' : 'No Jobs'; ?>"></i>
                        </td>
                        
                        <td class="status-icon">
                            <i class="fas fa-graduation-cap <?php echo $has_queue ? 'status-training' : 'status-inactive'; ?>" 
                               title="<?php echo $has_queue ? 'Training Active' : 'No Training'; ?>"></i>
                        </td>
                        
                        <td class="status-icon">
                            <i class="fas fa-sync-alt status-update" 
                               title="Update pilot data"
                               onclick="updatePilot(<?php echo $pilot['toon_number']; ?>)"></i>
                        </td>
                        
                        <td class="stat-number <?php echo ($pilot['numships'] ?? 0) > 50 ? 'stat-good' : 'stat-warning'; ?>">
                            <?php echo number_format($pilot['numships'] ?? 0); ?>
                        </td>
                        
                        <td><span class="pocket-status <?php echo $pocket_class; ?>"><?php echo htmlspecialchars($pocket); ?></span></td>
                        
                        <td class="stat-number"><?php echo $pilot['daysq'] ?? 0; ?></td>
                        
                        <td class="stat-number <?php echo ($pilot['numitems'] ?? 0) > 1000 ? 'stat-good' : 'stat-warning'; ?>">
                            <?php echo number_format($pilot['numitems'] ?? 0); ?>
                        </td>
                        
                        <td class="evermarks"><?php echo number_format($pilot['evermarks'] ?? 0); ?></td>
                        
                        <td class="stat-number <?php echo ($pilot['numberfits'] ?? -1) > 0 ? 'stat-good' : 'stat-inactive'; ?>">
                            <?php echo $pilot['numberfits'] ?? -1; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </main>
    
    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="footer">
        <div class="footer-left">
            <span>Fleet Commander System</span>
            <span>|</span>
            <span>FC: <?php echo htmlspecialchars($_SESSION['fleet_commander_number'] ?? 'N/A'); ?></span>
            <span>|</span>
            <span>Pilots: <?php echo count($pilots); ?></span>
        </div>
        <div class="footer-right">
            EVE Online ESI Integration • v1.0
        </div>
    </footer>
    
    <!-- ============================================
         MODAL LOGOUT
         ============================================ -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal">
            <h3><i class="fas fa-sign-out-alt"></i> Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideLogoutModal()">Cancel</button>
                <a href="?logout=confirm" class="modal-btn confirm" style="text-decoration:none">Yes, Logout</a>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="fas fa-check-circle"></i>
        <span id="toast-message">Operation successful</span>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    
    <script>
        let table;
        let editingToon = null;
        
        $(document).ready(function() {
            // Inicializar DataTable
            table = $('#pilotsTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc']], // Ordenar por supergroup por defecto
                language: {
                    search: "Search pilots:",
                    lengthMenu: "Show _MENU_ pilots per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ pilots",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [8, 9, 10, 11] }, // Iconos no ordenables
                    { width: "120px", targets: 1 } // Ancho fijo para supergroup
                ],
                initComplete: function() {
                    // Filtro personalizado por supergroup
                    this.api().columns(1).every(function() {
                        var column = this;
                        var select = $('<select class="supergroup-filter"><option value="">All Groups</option></select>')
                            .appendTo($(column.header()).empty())
                            .on('change', function() {
                                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                                column.search(val ? '^' + val + '$' : '', true, false).draw();
                            });
                        
                        // Obtener valores únicos de supergroup
                        column.data().unique().sort().each(function(d, j) {
                            var val = $(d).find('.supergroup-value').text().trim();
                            if (val) {
                                select.append('<option value="' + val + '">Group ' + val + '</option>');
                            }
                        });
                    });
                }
            });
        });
        
        // ============================================
        // EDICIÓN DE SUPERGROUP
        // ============================================
        function editSupergroup(toonNumber) {
            // Cancelar edición anterior si existe
            if (editingToon && editingToon !== toonNumber) {
                cancelEdit(editingToon);
            }
            
            editingToon = toonNumber;
            
            // Ocultar display, mostrar form
            document.getElementById('sg-value-' + toonNumber).parentElement.style.display = 'none';
            document.getElementById('sg-form-' + toonNumber).classList.add('active');
            
            // Focus en el input
            var input = document.getElementById('sg-input-' + toonNumber);
            input.focus();
            input.select();
        }
        
        function cancelEdit(toonNumber) {
            document.getElementById('sg-value-' + toonNumber).parentElement.style.display = 'inline-flex';
            document.getElementById('sg-form-' + toonNumber).classList.remove('active');
            editingToon = null;
        }
        
        function handleEnter(event, toonNumber) {
            if (event.key === 'Enter') {
                saveSupergroup(toonNumber);
            } else if (event.key === 'Escape') {
                cancelEdit(toonNumber);
            }
        }
        
        function saveSupergroup(toonNumber) {
            var newValue = document.getElementById('sg-input-' + toonNumber).value;
            
            // Validar
            if (newValue < 1 || newValue > 999) {
                showToast('Supergroup must be between 1 and 999', true);
                return;
            }
            
            // Mostrar indicador de guardado
            var form = document.getElementById('sg-form-' + toonNumber);
            form.innerHTML = '<span class="sg-saving"><i class="fas fa-spinner fa-spin"></i> Saving...</span>';
            
            // Enviar AJAX
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    action: 'update_supergroup',
                    toon_number: toonNumber,
                    supergroup: newValue
                },
                success: function(response) {
                    if (response.success) {
                        // Actualizar valor mostrado
                        document.getElementById('sg-value-' + toonNumber).textContent = newValue;
                        
                        // Restaurar form para próxima vez
                        form.innerHTML = `
                            <input type="number" class="supergroup-input" id="sg-input-${toonNumber}" 
                                   value="${newValue}" min="1" max="999" 
                                   onkeypress="handleEnter(event, ${toonNumber})">
                            <button class="sg-btn sg-btn-save" onclick="saveSupergroup(${toonNumber})" title="Save">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="sg-btn sg-btn-cancel" onclick="cancelEdit(${toonNumber})" title="Cancel">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        
                        cancelEdit(toonNumber);
                        showToast('Supergroup updated successfully');
                        
                        // Redibujar tabla para reordenar
                        table.draw();
                    } else {
                        showToast(response.message || 'Error updating supergroup', true);
                        cancelEdit(toonNumber);
                    }
                },
                error: function() {
                    showToast('Network error. Please try again.', true);
                    cancelEdit(toonNumber);
                }
            });
        }
        
        // ============================================
        // UTILIDADES
        // ============================================
        function showToast(message, isError = false) {
            var toast = document.getElementById('toast');
            var toastMessage = document.getElementById('toast-message');
            
            toastMessage.textContent = message;
            toast.className = 'toast show' + (isError ? ' error' : '');
            
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }
        
        function updatePilot(toonNumber) {
            showToast('Updating pilot ' + toonNumber + '... (ESI integration pending)');
            // Aquí iría la llamada ESI para actualizar datos del piloto
        }
        
        function refreshTable() {
            location.reload();
        }
        
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }
        
        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) hideLogoutModal();
        });
        
        // Cerrar con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLogoutModal();
                if (editingToon) cancelEdit(editingToon);
            }
        });
    </script>
</body>
</html>
