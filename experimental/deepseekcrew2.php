<?php
/**
 * Fleet Commander - Pilot Management System with Supergroup Editing
 * Date: 2026-03-31 09:03
 * 
 * INITIAL CONFIGURATION - Always work this way
 */

session_start();

// ============================================
// SESSION VERIFICATION
// ============================================
if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    header('Location: ../fleet_login.php');
    exit;
}

// ============================================
// INCLUDE DB CONFIGURATION
// ============================================
require_once '../config.php';
check_authorization();

// Assumes $link is already available as a MySQLi connection

// ============================================
// VERIFY AND CREATE supergroup FIELD IF IT DOES NOT EXIST
// ============================================
$check_column = mysqli_query($link, "SHOW COLUMNS FROM PILOTS LIKE 'supergroup'");
if (mysqli_num_rows($check_column) == 0) {
    $alter_sql = "ALTER TABLE PILOTS ADD COLUMN supergroup INT DEFAULT 1";
    mysqli_query($link, $alter_sql);
}

// ============================================
// PROCESS AJAX SUPERGROUP UPDATE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_supergroup') {
    header('Content-Type: application/json');
    
    $toon_number = intval($_POST['toon_number'] ?? 0);
    $new_supergroup = intval($_POST['supergroup'] ?? 1);
    
    // Verify that the pilot belongs to this commander
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
// SECURITY VALIDATION 1: Check other Fleet Commanders
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
// SECURITY VALIDATION 2: Verify pilot integrity
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
// IF THERE ARE SECURITY ERRORS, STOP
// ============================================
if (!empty($security_error) || !empty($integrity_error)) {
    $error_message = !empty($security_error) ? $security_error : $integrity_error;
    $show_data = false;
    $pilots = [];
} else {
    // ============================================
    // GET PILOTS FOR THE FLEET COMMANDER
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
                        supergroup,
                        current_ship,
                        current_location,
                        lastsaved,
                        pocket6
                     FROM PILOTS 
                     WHERE parent_toon_number = ? 
                     ORDER BY supergroup ASC, toon_name ASC";
    
    $pilot_stmt = mysqli_prepare($link, $pilots_query);
    $char_id = $_SESSION['character_id'] ?? 0;
    mysqli_stmt_bind_param($pilot_stmt, "i", $char_id);
    mysqli_stmt_execute($pilot_stmt);
    $pilots_result = mysqli_stmt_get_result($pilot_stmt);
    
    $pilots = [];
    $supergroups = []; // For DataTables filter
    
    while ($row = mysqli_fetch_assoc($pilots_result)) {
        $pilots[] = $row;
        $supergroups[$row['supergroup']] = true;
    }
    
    // Sort supergroups for the select
    ksort($supergroups);
}

// ============================================
// PROCESS LOGOUT WITH CONFIRMATION
// ============================================
if (isset($_GET['logout']) && $_GET['logout'] === 'confirm') {
    session_destroy();
    header('Location: fleet_login.php');
    exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function formatMillions($value) {
    if (empty($value)) return '0.00';
    return number_format($value / 1000000, 2);
}

function getPilotStatus($lastsaved) {
    if (empty($lastsaved) || $lastsaved === '0000-00-00 00:00:00') {
        return ['class' => 'secondary', 'label' => 'No Data'];
    }
    $last = strtotime($lastsaved);
    $now = time();
    $diff = ($now - $last) / 3600;
    if ($diff < 24) {
        return ['class' => 'success', 'label' => 'Active'];
    } elseif ($diff < 168) {
        return ['class' => 'warning', 'label' => 'Recent'];
    } else {
        return ['class' => 'danger', 'label' => 'Inactive'];
    }
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            color: #333;
            padding: 20px;
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
        }
        
        .page-header h1 {
            color: #ffd700;
            font-size: 28px;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .page-header p {
            color: rgba(255,255,255,0.7);
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 15px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-primary:hover {
            background: #ff5252;
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
        }
        
        /* ============================================
           SECURITY ALERTS
           ============================================ */
        .security-alert {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #dc3545;
        }
        
        .security-alert i {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .security-alert h2 {
            color: #dc3545;
            margin-bottom: 10px;
        }
        
        .security-alert p {
            color: #333;
            font-size: 16px;
        }
        
        /* ============================================
           DATATABLES CUSTOM STYLES
           ============================================ */
        .pilots-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* Override DataTables styles */
        .dataTables_wrapper {
            color: #333;
        }
        
        .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate {
            margin-bottom: 15px;
            color: #6c757d !important;
        }
        
        .dataTables_length select, .dataTables_filter input {
            background: white;
            border: 1px solid #dee2e6;
            color: #333;
            padding: 5px 10px;
            border-radius: 10px;
        }
        
        .dataTables_filter input:focus {
            outline: none;
            border-color: #2a5298;
            box-shadow: 0 0 0 3px rgba(42, 82, 152, 0.2);
        }
        
        table.dataTable {
            background: transparent;
            border-collapse: collapse;
            width: 100% !important;
        }
        
        table.dataTable thead th {
            background: #1e3c72;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 1px;
            border-bottom: 2px solid #2a5298;
            padding: 12px 10px;
            text-align: center;
        }
        
        table.dataTable thead th:first-child {
            text-align: left;
        }
        
        table.dataTable tbody td {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            color: #333;
            vertical-align: middle;
            text-align: center;
        }
        
        table.dataTable tbody td:first-child {
            text-align: left;
        }
        
        table.dataTable tbody tr:hover {
            background: rgba(42, 82, 152, 0.05);
        }
        
        /* Pagination */
        .dataTables_paginate .paginate_button {
            background: white !important;
            border: 1px solid #dee2e6 !important;
            color: #2a5298 !important;
            border-radius: 10px !important;
            margin: 0 2px !important;
            transition: all 0.3s ease;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: #2a5298 !important;
            border-color: #2a5298 !important;
            color: white !important;
        }
        
        .dataTables_paginate .paginate_button.current {
            background: #2a5298 !important;
            border-color: #2a5298 !important;
            color: white !important;
        }
        
        /* ============================================
           SPECIFIC CELLS
           ============================================ */
        .pilot-info-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .pilot-portrait {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #2a5298;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .pilot-name {
            font-weight: 600;
            color: #1e3c72;
            font-size: 14px;
        }
        
        .pilot-number {
            font-size: 11px;
            color: #6c757d;
            font-family: 'Courier New', monospace;
        }
        
        .profession {
            background: rgba(42, 82, 152, 0.15);
            color: #2a5298;
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
        
        .security-high { color: #28a745; }
        .security-medium { color: #ffc107; }
        .security-low { color: #dc3545; }
        
        .sp-value {
            font-family: 'Courier New', monospace;
            color: #1e3c72;
            font-weight: 600;
            font-size: 13px;
        }
        
        .sp-unalloc {
            font-size: 10px;
            color: #ffc107;
            font-weight: 500;
        }
        
        .acctype {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .acctype-omega {
            background: #ff6b6b;
            color: white;
        }
        
        .acctype-alpha {
            background: #4ecdc4;
            color: white;
        }
        
        .queue-finish {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #6f42c1;
            font-weight: 500;
        }
        
        .wallet {
            font-family: 'Courier New', monospace;
            color: #28a745;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-icon {
            font-size: 16px;
        }
        
        .status-active { color: #28a745; }
        .status-inactive { color: #6c757d; }
        .status-training { color: #6f42c1; }
        .status-update { 
            color: #2a5298; 
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .status-update:hover {
            transform: rotate(180deg);
            color: #1e3c72;
        }
        
        .stat-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 12px;
        }
        
        .stat-good { color: #28a745; }
        .stat-warning { color: #ffc107; }
        .stat-danger { color: #dc3545; }
        .stat-inactive { color: #6c757d; }
        
        .pocket-status {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .pocket-clean {
            background: #d4edda;
            color: #155724;
        }
        
        .pocket-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .pocket-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .pocket-secondary {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .evermarks {
            color: #6f42c1;
            font-weight: 600;
            font-size: 12px;
        }
        
        /* ============================================
           EDITABLE SUPERGROUP
           ============================================ */
        .supergroup-cell {
            position: relative;
        }
        
        .supergroup-display {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 15px;
            background: rgba(42, 82, 152, 0.1);
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .supergroup-display:hover {
            background: rgba(42, 82, 152, 0.2);
            border-color: #2a5298;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(42, 82, 152, 0.2);
        }
        
        .supergroup-value {
            font-weight: 700;
            color: #2a5298;
            min-width: 30px;
            text-align: center;
            font-size: 14px;
        }
        
        .supergroup-edit {
            color: #6c757d;
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
            background: white;
            border: 2px solid #2a5298;
            color: #1e3c72;
            padding: 5px 8px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
        }
        
        .supergroup-input:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(42, 82, 152, 0.2);
        }
        
        .sg-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .sg-btn-save {
            background: #28a745;
            color: white;
        }
        
        .sg-btn-save:hover {
            background: #218838;
            transform: scale(1.1);
        }
        
        .sg-btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .sg-btn-cancel:hover {
            background: #5a6268;
            transform: scale(1.1);
        }
        
        .sg-saving {
            color: #2a5298;
            font-size: 12px;
            font-weight: 600;
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
            background: rgba(30, 60, 114, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        
        .modal h3 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .modal p {
            color: #6c757d;
            margin-bottom: 25px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 10px 25px;
            border-radius: 15px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .modal-btn.confirm {
            background: #ff6b6b;
            color: white;
        }
        
        .modal-btn.confirm:hover {
            background: #ff5252;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }
        
        .modal-btn.cancel {
            background: #e9ecef;
            color: #333;
        }
        
        .modal-btn.cancel:hover {
            background: #dee2e6;
            transform: translateY(-2px);
        }
        
        /* Toast notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 30px;
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 3000;
            animation: slideIn 0.3s ease;
            font-weight: 600;
        }
        
        .toast.show {
            display: flex;
        }
        
        .toast.error {
            background: #dc3545;
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
        
        /* Attribution note */
        .attribution-note {
            background: rgba(0,0,0,0.2);
            border-radius: 15px;
            padding: 15px;
            margin-top: 30px;
            color: rgba(255,255,255,0.8);
            font-size: 0.85rem;
            text-align: center;
        }
    </style>
</head>
<body>
    
    <!-- ============================================
         MAIN CONTENT
         ============================================ -->
    <main class="main-content">
        
        <div class="page-header">
            <div>
                <h1><i class="fas fa-users"></i> Pilot Management</h1>
                <p>Manage your fleet pilots and their supergroups</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showLogoutModal()">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
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
                        <th>SP (M)</th>
                        <th>Queue End</th>
                        <th><i class="fas fa-sync-alt"></i></th>
                        <th>DaysQ</th>
                        <th>Ship</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Pocket6</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pilots as $pilot): 
                        // Decode current_ship (JSON with escaped slashes)
                        $ship_display = '-';
                        if (!empty($pilot['current_ship'])) {
                            $ship_data = json_decode(stripslashes($pilot['current_ship']), true);
                            if (!empty($ship_data['ship_name'])) {
                                $ship_display = $ship_data['ship_name'];
                            }
                        }
                        
                        // Decode current_location (JSON with escaped slashes)
                        $location_display = '-';
                        if (!empty($pilot['current_location'])) {
                            $location_data = json_decode(stripslashes($pilot['current_location']), true);
                            if (!empty($location_data['station_id'])) {
                                $location_display = 'Station ' . $location_data['station_id'];
                            } elseif (!empty($location_data['solar_system_id'])) {
                                $location_display = 'System: ' . $location_data['solar_system_id'];
                            }
                        }
                        
                        // Pilot status based on lastsaved
                        $status = getPilotStatus($pilot['lastsaved'] ?? null);
                        
                        // Account type badge
                        $acctype_class = (strtolower($pilot['acctype'] ?? '') === 'omega') ? 'acctype-omega' : 'acctype-alpha';
                        
                        // Pocket6
                        $pocket6 = strtoupper($pilot['pocket6'] ?? 'CLEAN');
                    ?>
                    <tr data-toon="<?php echo $pilot['toon_number']; ?>">
                        <td>
                            <div class="pilot-info-cell">
                                <img src="https://images.evetech.net/characters/<?php echo $pilot['toon_number']; ?>/portrait?size=64" 
                                     alt="<?php echo htmlspecialchars($pilot['toon_name']); ?>"
                                     class="pilot-portrait"
                                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect fill=%22%23dee2e6%22 width=%2264%22 height=%2264%22/><text fill=%22%236c757d%22 x=%2232%22 y=%2236%22 text-anchor=%22middle%22 font-size=%2224%22>?</text></svg>'">
                                <div>
                                    <div class="pilot-name"><?php echo htmlspecialchars($pilot['toon_name']); ?></div>
                                    <div class="pilot-number">#<?php echo $pilot['toon_number']; ?> <span class="acctype <?php echo $acctype_class; ?>"><?php echo strtoupper($pilot['acctype'] ?? 'alpha'); ?></span></div>
                                </div>
                            </div>
                        </td>
                        
                        <!-- EDITABLE SUPERGROUP -->
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
                        
                        <td>
                            <div class="sp-value"><?php echo formatMillions($pilot['skillpoints']); ?></div>
                            <?php if (!empty($pilot['unalloc']) && $pilot['unalloc'] > 0): ?>
                            <div class="sp-unalloc">+<?php echo formatMillions($pilot['unalloc']); ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="queue-finish">
                            <?php echo !empty($pilot['finishqueue']) ? date('Y-m-d H:i', strtotime($pilot['finishqueue'])) : '<span style="color:#adb5bd">-</span>'; ?>
                        </td>
                        
                        <td class="status-icon">
                            <i class="fas fa-sync-alt status-update" 
                               title="Update pilot data"
                               onclick="updatePilot(<?php echo $pilot['toon_number']; ?>)"></i>
                        </td>
                        
                        <td class="stat-number"><?php echo $pilot['daysq'] ?? 0; ?></td>
                        
                        <td><?php echo htmlspecialchars($ship_display); ?></td>
                        
                        <td><?php echo htmlspecialchars($location_display); ?></td>
                        
                        <?php
                            $status_class_map = ['success' => 'pocket-clean', 'warning' => 'pocket-warning', 'danger' => 'pocket-danger', 'secondary' => 'pocket-secondary'];
                            $status_css = $status_class_map[$status['class']] ?? 'pocket-secondary';
                        ?>
                        <td><span class="pocket-status <?php echo $status_css; ?>"><?php echo htmlspecialchars($status['label']); ?></span></td>
                        
                        <td>
                            <?php if ($pocket6 !== 'CLEAN'): ?>
                            <span class="pocket-status pocket-danger"><?php echo htmlspecialchars($pocket6); ?></span>
                            <?php else: ?>
                            <span class="pocket-status pocket-clean">CLEAN</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Attribution Note -->
        <div class="attribution-note text-center">
            <i class="fas fa-code"></i> <strong>Attribution:</strong> Fleet Commander Pilot Management System
            <br><i class="fas fa-calendar"></i> Date: 2026-03-31 | <i class="fas fa-file-code"></i> File: <?php echo basename(__FILE__); ?> | <i class="fas fa-database"></i> PHP <?php echo phpversion(); ?>
            <br><i class="fas fa-users"></i> Pilots: <?php echo count($pilots); ?> | <i class="fas fa-user-shield"></i> FC: <?php echo htmlspecialchars($_SESSION['fleet_commander_number'] ?? 'N/A'); ?>
        </div>
        
    </main>
    
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
            // Initialize DataTable
            table = $('#pilotsTable').DataTable({
                pageLength: 200,
                order: [[1, 'asc']], // Sort by supergroup by default
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
                    { orderable: false, targets: [4] }, // Update icon not sortable
                    { width: "120px", targets: 1 } // Fixed width for supergroup
                ],
                initComplete: function() {
                    // Custom filter by supergroup
                    this.api().columns(1).every(function() {
                        var column = this;
                        var select = $('<select class="supergroup-filter"><option value="">All Groups</option></select>')
                            .appendTo($(column.header()).empty())
                            .on('change', function() {
                                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                                column.search(val ? '^' + val + '$' : '', true, false).draw();
                            });
                        
                        // Get unique supergroup values
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
        // SUPERGROUP EDITING
        // ============================================
        function editSupergroup(toonNumber) {
            // Cancel previous edit if any
            if (editingToon && editingToon !== toonNumber) {
                cancelEdit(editingToon);
            }
            
            editingToon = toonNumber;
            
            // Hide display, show form
            document.getElementById('sg-value-' + toonNumber).parentElement.style.display = 'none';
            document.getElementById('sg-form-' + toonNumber).classList.add('active');
            
            // Focus on input
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
            
            // Validate
            if (newValue < 1 || newValue > 999) {
                showToast('Supergroup must be between 1 and 999', true);
                return;
            }
            
            // Show saving indicator
            var form = document.getElementById('sg-form-' + toonNumber);
            form.innerHTML = '<span class="sg-saving"><i class="fas fa-spinner fa-spin"></i> Saving...</span>';
            
            // Send AJAX
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
                        // Update displayed value
                        document.getElementById('sg-value-' + toonNumber).textContent = newValue;
                        
                        // Restore form for next time
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
                        
                        // Redraw table to reorder
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
        // UTILITIES
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
            // ESI call to update pilot data would go here
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
        
        // Close modal when clicking outside
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) hideLogoutModal();
        });
        
        // Close with ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLogoutModal();
                if (editingToon) cancelEdit(editingToon);
            }
        });
    </script>
</body>
</html>
