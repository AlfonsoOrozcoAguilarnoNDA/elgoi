<?php
/**
 * EVE Assets Analysis Dashboard
 * Shows safety assets, off-station asset counts, and detailed off-station assets
 */

require_once '../config.php';

// ============================================================
// SECTION 1: Safety Assets
// ============================================================
$safety_sql = "SELECT 
    a.*, 
    p.toon_name, 
    p.pocket6 
FROM EVE_ASSETS a 
JOIN PILOTS p ON a.toon_number = p.toon_number 
WHERE a.location_flag = 'AssetSafety' 
ORDER BY a.toon_number, a.description";

$safety_result = mysqli_query($link, $safety_sql);
$safety_count = mysqli_num_rows($safety_result);

// ============================================================
// SECTION 2: Summary - Off-Station Asset Count per Pilot
// ============================================================
$summary_sql = "SELECT 
    p.toon_number,
    p.toon_name,
    p.pocket6,
    COUNT(a.ID) AS off_station_count
FROM PILOTS p
LEFT JOIN EVE_ASSETS a ON p.toon_number = a.toon_number 
    AND (a.location_id IS NULL OR CAST(a.location_id AS CHAR) NOT LIKE '600%')
GROUP BY p.toon_number, p.toon_name, p.pocket6
HAVING off_station_count > 0
ORDER BY off_station_count DESC, p.toon_name";

$summary_result = mysqli_query($link, $summary_sql);
$summary_count = mysqli_num_rows($summary_result);

// ============================================================
// SECTION 3: Detailed Off-Station Assets (for DataTable)
// ============================================================
$detail_sql = "SELECT 
    a.ID,
    a.toon_number,
    p.toon_name,
    p.pocket6,
    a.location_flag,
    a.location_id,
    a.description,
    a.quantity,
    a.type_id,
    a.type_description,
    a.eveunique,
    a.date_insert,
    a.unit_price,
    a.forge_value
FROM EVE_ASSETS a
JOIN PILOTS p ON a.toon_number = p.toon_number
WHERE a.location_id IS NULL OR CAST(a.location_id AS CHAR) NOT LIKE '600%'
ORDER BY p.toon_name, a.description";

$detail_result = mysqli_query($link, $detail_sql);
$detail_count = mysqli_num_rows($detail_result);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Assets Analysis - Off-Station & Safety</title>

    <!-- Bootstrap 4.6.x -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- FontAwesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    <style>
        body { background-color: #1a1a2e; color: #e0e0e0; padding-top: 20px; }
        .card { background-color: #16213e; border: 1px solid #0f3460; margin-bottom: 25px; }
        .card-header { background-color: #0f3460; color: #fff; font-weight: 600; }
        .table { color: #e0e0e0; }
        .table thead th { border-top: none; border-bottom: 2px solid #0f3460; color: #a0c4ff; }
        .table tbody tr:hover { background-color: #1f3050; }
        .badge-safety { background-color: #e94560; }
        .badge-offstation { background-color: #f4a261; }
        .pocket-badge { background-color: #2a9d8f; }
        .section-icon { margin-right: 8px; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #e94560; }
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #e0e0e0 !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { color: #e0e0e0 !important; }
        .page-item.active .page-link { background-color: #0f3460; border-color: #0f3460; }
        .form-control, .custom-select { background-color: #1a1a2e; color: #e0e0e0; border-color: #0f3460; }
    </style>
</head>
<body>

<div class="container-fluid">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1><i class="fas fa-space-shuttle section-icon"></i>EVE Assets Analysis Dashboard</h1>
            <p class="text-muted">Off-Station Assets & Safety Wrap Detection</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number"><?php echo $safety_count; ?></div>
                    <div class="text-muted"><i class="fas fa-shield-alt section-icon"></i>Safety Assets</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number"><?php echo $summary_count; ?></div>
                    <div class="text-muted"><i class="fas fa-users section-icon"></i>Pilots with Off-Station Items</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number"><?php echo $detail_count; ?></div>
                    <div class="text-muted"><i class="fas fa-box-open section-icon"></i>Total Off-Station Items</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION 1: Safety Assets -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-shield-alt section-icon"></i>
            Section 1: Asset Safety Wraps
            <span class="badge badge-safety float-right"><?php echo $safety_count; ?> items</span>
        </div>
        <div class="card-body">
            <?php if ($safety_count > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Pilot</th>
                            <th>Pocket</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Type</th>
                            <th>Location Flag</th>
                            <th>Location ID</th>
                            <th>Unit Price</th>
                            <th>Forge Value</th>
                            <th>Date Insert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($safety_result)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['toon_name']); ?></strong></td>
                            <td><span class="badge pocket-badge"><?php echo htmlspecialchars($row['pocket6']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo number_format($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['type_description']); ?></td>
                            <td><span class="badge badge-safety"><?php echo htmlspecialchars($row['location_flag']); ?></span></td>
                            <td><code><?php echo $row['location_id']; ?></code></td>
                            <td><?php echo number_format($row['unit_price'], 2); ?></td>
                            <td><?php echo number_format($row['forge_value'], 2); ?></td>
                            <td><?php echo $row['date_insert']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info"><i class="fas fa-info-circle"></i> No safety assets found.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION 2: Summary by Pilot -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-chart-bar section-icon"></i>
            Section 2: Off-Station Asset Count by Pilot
            <span class="badge badge-offstation float-right"><?php echo $summary_count; ?> pilots</span>
        </div>
        <div class="card-body">
            <?php if ($summary_count > 0): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Pilot Name</th>
                            <th>Pocket</th>
                            <th class="text-right">Off-Station Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($row = mysqli_fetch_assoc($summary_result)): 
                        ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><strong><?php echo htmlspecialchars($row['toon_name']); ?></strong></td>
                            <td><span class="badge pocket-badge"><?php echo htmlspecialchars($row['pocket6']); ?></span></td>
                            <td class="text-right"><span class="badge badge-offstation" style="font-size: 1.1em;"><?php echo number_format($row['off_station_count']); ?></span></td>
                            <td>
                                <?php if ($row['off_station_count'] > 50): ?>
                                    <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> High Risk</span>
                                <?php elseif ($row['off_station_count'] > 10): ?>
                                    <span class="badge badge-warning"><i class="fas fa-exclamation-circle"></i> Moderate</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> All assets are safely stored in stations.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION 3: Detailed Off-Station Assets (DataTable) -->
    <!-- ============================================================ -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list-alt section-icon"></i>
            Section 3: Detailed Off-Station Assets
            <span class="badge badge-offstation float-right"><?php echo $detail_count; ?> items</span>
        </div>
        <div class="card-body">
            <?php if ($detail_count > 0): ?>
            <div class="table-responsive">
                <table id="detailTable" class="table table-sm table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Pilot</th>
                            <th>Pocket</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Type Description</th>
                            <th>Location Flag</th>
                            <th>Location ID</th>
                            <th>EVE Unique</th>
                            <th>Unit Price</th>
                            <th>Forge Value</th>
                            <th>Date Insert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($detail_result)): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['toon_name']); ?></strong></td>
                            <td><span class="badge pocket-badge"><?php echo htmlspecialchars($row['pocket6']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo number_format($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['type_description']); ?></td>
                            <td><code><?php echo htmlspecialchars($row['location_flag']); ?></code></td>
                            <td><code><?php echo $row['location_id']; ?></code></td>
                            <td><small><?php echo $row['eveunique']; ?></small></td>
                            <td><?php echo number_format($row['unit_price'], 2); ?></td>
                            <td><?php echo number_format($row['forge_value'], 2); ?></td>
                            <td><?php echo $row['date_insert']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> No off-station assets found.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#detailTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc'], [2, 'asc']],
        language: {
            search: "Filter assets:",
            lengthMenu: "Show _MENU_ assets per page",
            info: "Showing _START_ to _END_ of _TOTAL_ off-station assets",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        columnDefs: [
            { targets: [7, 8, 9], className: "text-right" }
        ]
    });
});
</script>

</body>
</html>
