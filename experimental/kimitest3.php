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
    header('Location: fleet_login.php');
    exit;
}

// ============================================
// INCLUDE DB CONFIGURATION
// ============================================
require_once '../config.php';
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
        
        /* Pagination */
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
           SPECIFIC CELLS
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
        
        .queue-finish {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #a371f7;
        }
        
        .stat-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-icon {
            font-size: 16px;
        }
        
        .status-update {
            color: #58a6ff;
            display: inline-block;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .status-update:hover {
            transform: rotate(180deg);
        }
        
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
        
        .pocket-secondary {
            background: rgba(139, 148, 158, 0.15);
            color: #8b949e;
        }
        
        /* ============================================
           SUPERGROUP (READ-ONLY)
           ============================================ */
        .supergroup-value {
            font-weight: 600;
            color: #58a6ff;
            background: rgba(88, 166, 255, 0.1);
            border: 1px solid #30363d;
            padding: 5px 10px;
            border-radius: 6px;
            display: inline-block;
            min-width: 30px;
        }
        
        /* ============================================
           SHIP / LOCATION (MERGED)
           ============================================ */
        .ship-location-cell {
            text-align: left;
            line-height: 1.4;
        }
        
        .ship-line {
            color: #c9d1d9;
            font-weight: 500;
            font-size: 12px;
        }
        
        .location-line {
            color: #8b949e;
            font-size: 11px;
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
                        <th>DaysQ</th>
                        <th>Ship / Location</th>
                        <th>Status</th>
                        <th>Pocket6</th>
                        <th><i class="fas fa-sync-alt"></i></th>
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
                        
                        // Pocket6
                        $pocket6 = strtoupper($pilot['pocket6'] ?? 'CLEAN');
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
                        
                        <!-- SUPERGROUP (READ-ONLY) -->
                        <td>
                            <span class="supergroup-value"><?php echo intval($pilot['supergroup'] ?? 1); ?></span>
                        </td>
                        
                        <td>
                            <div class="sp-value"><?php echo formatMillions($pilot['skillpoints']); ?></div>
                            <?php if (!empty($pilot['unalloc']) && $pilot['unalloc'] > 0): ?>
                            <div class="sp-unalloc">+<?php echo formatMillions($pilot['unalloc']); ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="queue-finish">
                            <?php echo !empty($pilot['finishqueue']) ? date('Y-m-d H:i', strtotime($pilot['finishqueue'])) : '<span style="color:#484f58">-</span>'; ?>
                        </td>
                        
                        <td class="stat-number"><?php echo $pilot['daysq'] ?? 0; ?></td>
                        
                        <!-- SHIP / LOCATION (MERGED) -->
                        <td class="ship-location-cell">
                            <div class="ship-line"><?php echo htmlspecialchars($ship_display); ?></div>
                            <div class="location-line"><?php echo htmlspecialchars($location_display); ?></div>
                        </td>
                        
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
                        
                        <td class="status-icon">
                            <a href="../devauthcallback.php?pilot_id=<?php echo $pilot['toon_number']; ?>" 
                               target="_blank" 
                               title="Update pilot data"
                               class="status-update">
                                <i class="fas fa-sync-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </main>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#pilotsTable').DataTable({
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
                    { width: "120px", targets: 1 }, // Fixed width for supergroup
                    { orderable: false, targets: [8] } // Link icon not sortable
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
    </script>
</body>
</html>
