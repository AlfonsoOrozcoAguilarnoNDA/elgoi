<?php
/**
 * remap.php
 * EVE Online Pilot Remap Dashboard
 * PHP 8.x Procedural - GPL License
 * 
 * Purpose: Display pilot attributes and remap cooldown status
 * to help identify which pilots can reconfigure their attributes.
 */

require_once '../config.php';
check_authorization();
// --- Database Query ---
$sql = "SELECT `toon_number`, `toon_name`, `DOB`, `pocket6`, `attrib`, `remaps`
        FROM `PILOTS`
        WHERE `toon_name` NOT LIKE '%catalog%'
        ORDER BY `DOB` ASC";

$result = mysqli_query($link, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($link));
}

$pilots = [];
$now = new DateTime('now', new DateTimeZone('UTC'));

while ($row = mysqli_fetch_assoc($result)) {
    $pilot = [
        'toon_number' => (int)$row['toon_number'],
        'toon_name'   => htmlspecialchars($row['toon_name'], ENT_QUOTES, 'UTF-8'),
        'DOB'         => $row['DOB'],
        'pocket6'     => htmlspecialchars($row['pocket6'] ?? 'CLEAN', ENT_QUOTES, 'UTF-8'),
        'remaps'      => (int)($row['remaps'] ?? 0),
        'attributes'  => [],
        'cooldown_date' => null,
        'days_diff'   => null,
        'status'      => 'unknown',
    ];

    // Parse attrib JSON with stripslashes
    if (!empty($row['attrib'])) {
        $clean_json = stripslashes($row['attrib']);
        $attrs = json_decode($clean_json, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($attrs)) {
            $pilot['attributes'] = [
                'intelligence' => (int)($attrs['intelligence'] ?? 0),
                'memory'       => (int)($attrs['memory'] ?? 0),
                'perception'   => (int)($attrs['perception'] ?? 0),
                'willpower'    => (int)($attrs['willpower'] ?? 0),
                'charisma'     => (int)($attrs['charisma'] ?? 0),
            ];
            
            if (!empty($attrs['accrued_remap_cooldown_date'])) {
                try {
                    $cooldown = new DateTime($attrs['accrued_remap_cooldown_date'], new DateTimeZone('UTC'));
                    $pilot['cooldown_date'] = $cooldown->format('Y-m-d H:i');
                    
                    $interval = $now->diff($cooldown);
                    $pilot['days_diff'] = (int)$interval->format('%r%a');
                    
                    if ($pilot['days_diff'] < 0) {
                        $pilot['status'] = 'ready';      // Cooldown expired
                    } elseif ($pilot['days_diff'] === 0) {
                        $pilot['status'] = 'today';      // Expires today
                    } else {
                        $pilot['status'] = 'waiting';    // Still on cooldown
                    }
                } catch (Exception $e) {
                    $pilot['cooldown_date'] = 'Invalid Date';
                }
            }
        }
    }
    
    $pilots[] = $pilot;
}

mysqli_free_result($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online - Pilot Remap Dashboard</title>
    
    <!-- Bootstrap 4.6.x CSS (jsDelivr) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 5.15.4 (jsDelivr) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    
    <!-- DataTables Bootstrap 4 CSS (jsDelivr) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/css/dataTables.bootstrap4.min.css">
    
    <style>
        body {
            background-color: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-fluid {
            padding: 20px;
        }
        .page-header {
            background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 4px solid #e94560;
        }
        .page-header h1 {
            color: #fff;
            margin: 0;
            font-size: 1.8rem;
        }
        .page-header p {
            color: #a0a0a0;
            margin: 10px 0 0 0;
            font-size: 0.95rem;
        }
        .card {
            background-color: #16213e;
            border: 1px solid #0f3460;
            border-radius: 8px;
        }
        .table {
            color: #e0e0e0;
            background-color: #16213e;
        }
        .table thead th {
            background-color: #0f3460;
            color: #fff;
            border-bottom: 2px solid #e94560;
            font-weight: 600;
            white-space: nowrap;
        }
        .table tbody tr:hover {
            background-color: #1f3050;
        }
        .table td {
            vertical-align: middle;
            border-color: #2a3f5f;
        }
        .badge-remap {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .status-ready {
            color: #28a745;
            font-weight: bold;
        }
        .status-waiting {
            color: #dc3545;
            font-weight: bold;
        }
        .status-today {
            color: #ffc107;
            font-weight: bold;
        }
        .attr-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .attr-intel { color: #4dabf7; }
        .attr-mem   { color: #69db7c; }
        .attr-per   { color: #ffa94d; }
        .attr-will  { color: #ff6b6b; }
        .attr-cha   { color: #da77f2; }
        .dob-cell {
            white-space: nowrap;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: #a0a0a0 !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            background-color: #1a1a2e;
            color: #e0e0e0;
            border: 1px solid #0f3460;
            border-radius: 4px;
            padding: 5px 10px;
        }
        .dataTables_wrapper .dataTables_length select {
            background-color: #1a1a2e;
            color: #e0e0e0;
            border: 1px solid #0f3460;
            border-radius: 4px;
        }
        .page-item.active .page-link {
            background-color: #e94560;
            border-color: #e94560;
        }
        .page-link {
            background-color: #16213e;
            color: #e0e0e0;
            border-color: #0f3460;
        }
        .page-link:hover {
            background-color: #0f3460;
            color: #fff;
        }
        .pocket-badge {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    
    <!-- Page Header -->
    <div class="page-header">
        <h1><i class="fas fa-dna"></i> Pilot Remap Dashboard</h1>
        <p>
            This dashboard helps you detect which pilots have <strong>attribute remaps available</strong> and how many 
            <strong>days remain until the next remap cooldown expires</strong>. Use this information to plan 
            attribute reconfiguration for optimal skill training. Pilots are sorted by Date of Birth (DOB).
        </p>
    </div>

    <!-- Data Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="pilotsTable" class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th><i class="fas fa-hashtag"></i> ID</th>
                            <th><i class="fas fa-user-astronaut"></i> Pilot Name</th>
                            <th><i class="fas fa-shield-alt"></i> Pocket6</th>
                            <th><i class="fas fa-birthday-cake"></i> DOB</th>
                            <th><i class="fas fa-brain"></i> INT</th>
                            <th><i class="fas fa-memory"></i> MEM</th>
                            <th><i class="fas fa-eye"></i> PER</th>
                            <th><i class="fas fa-fist-raised"></i> WIL</th>
                            <th><i class="fas fa-comments"></i> CHA</th>
                            <th><i class="fas fa-sync-alt"></i> Remaps</th>
                            <th><i class="fas fa-clock"></i> Cooldown Date</th>
                            <th><i class="fas fa-hourglass-half"></i> Days</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pilots as $p): ?>
                        <tr>
                            <td><?php echo $p['toon_number']; ?></td>
                            <td>
                                <strong><?php echo $p['toon_name']; ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-secondary pocket-badge">
                                    <?php echo $p['pocket6']; ?>
                                </span>
                            </td>
                            <td class="dob-cell"><?php echo ($p['DOB'] ? date('Y-m-d', strtotime($p['DOB'])) : 'N/A'); ?></td>
                            
                            <!-- Attributes -->
                            <td class="attr-value attr-intel"><?php echo $p['attributes']['intelligence'] ?? '-'; ?></td>
                            <td class="attr-value attr-mem"><?php echo $p['attributes']['memory'] ?? '-'; ?></td>
                            <td class="attr-value attr-per"><?php echo $p['attributes']['perception'] ?? '-'; ?></td>
                            <td class="attr-value attr-will"><?php echo $p['attributes']['willpower'] ?? '-'; ?></td>
                            <td class="attr-value attr-cha"><?php echo $p['attributes']['charisma'] ?? '-'; ?></td>
                            
                            <!-- Remaps -->
                            <td>
                                <?php if ($p['remaps'] > 0): ?>
                                    <span class="badge badge-success badge-remap">
                                        <i class="fas fa-check"></i> <?php echo $p['remaps']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-danger badge-remap">
                                        <i class="fas fa-times"></i> 0
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Cooldown Date -->
                            <td>
                                <?php if ($p['cooldown_date']): ?>
                                    <?php echo $p['cooldown_date']; ?> UTC
                                <?php else: ?>
                                    <span class="text-muted">No data</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Days Difference -->
                            <td>
                                <?php if ($p['cooldown_date'] && $p['days_diff'] !== null): ?>
                                    <?php if ($p['status'] === 'ready'): ?>
                                        <span class="status-ready">
                                            <i class="fas fa-check-circle"></i> 
                                            <?php echo abs($p['days_diff']); ?> days ago
                                        </span>
                                    <?php elseif ($p['status'] === 'today'): ?>
                                        <span class="status-today">
                                            <i class="fas fa-exclamation-circle"></i> Today
                                        </span>
                                    <?php else: ?>
                                        <span class="status-waiting">
                                            <i class="fas fa-hourglass-start"></i> 
                                            <?php echo $p['days_diff']; ?> days
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-3 text-muted small">
        <i class="fas fa-info-circle"></i> 
        <strong>Legend:</strong> 
        <span class="status-ready">Green</span> = Cooldown expired, remap available. 
        <span class="status-waiting">Red</span> = Still on cooldown. 
        <span class="status-today">Yellow</span> = Cooldown expires today.
    </div>

</div>

<!-- jQuery (jsDelivr) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

<!-- Bootstrap 4.6.x JS Bundle (jsDelivr) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables Core (jsDelivr) -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.8/js/jquery.dataTables.min.js"></script>

<!-- DataTables Bootstrap 4 (jsDelivr) -->
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.8/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#pilotsTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[3, 'asc']], // Sort by DOB column by default
        columnDefs: [
            { orderable: false, targets: [] }
        ],
        language: {
            search: "<i class='fas fa-search'></i> Search:",
            lengthMenu: "Show _MENU_ pilots per page",
            info: "Showing _START_ to _END_ of _TOTAL_ pilots",
            infoEmpty: "No pilots found",
            infoFiltered: "(filtered from _MAX_ total pilots)",
            paginate: {
                first: "<i class='fas fa-angle-double-left'></i>",
                previous: "<i class='fas fa-angle-left'></i>",
                next: "<i class='fas fa-angle-right'></i>",
                last: "<i class='fas fa-angle-double-right'></i>"
            }
        }
    });
});
</script>

</body>
</html>
