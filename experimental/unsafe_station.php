<?php
/**
 * EVE Assets Analysis Dashboard
 * Recursive CTE to properly trace asset location chains
 * MariaDB 11.8.6+ / MySQL 8.0+
 */

require_once '../config.php';

// ============================================================
// RECURSIVE CTE: Trace each asset up the location chain
// ============================================================
$recursive_sql = "
WITH RECURSIVE asset_chain AS (
    -- Anchor: all assets as starting points
    SELECT 
        a.ID,
        a.toon_number,
        a.location_flag,
        a.location_id,
        a.description,
        a.quantity,
        a.type_id,
        a.type_description,
        a.eveunique,
        a.date_insert,
        a.unit_price,
        a.forge_value,
        a.location_id AS root_location,
        1 AS depth,
        CAST(a.location_id AS CHAR) AS chain_path
    FROM EVE_ASSETS a

    UNION ALL

    -- Recursive step: follow location_id -> eveunique of parent
    SELECT 
        ac.ID,
        ac.toon_number,
        ac.location_flag,
        parent.location_id,
        ac.description,
        ac.quantity,
        ac.type_id,
        ac.type_description,
        ac.eveunique,
        ac.date_insert,
        ac.unit_price,
        ac.forge_value,
        ac.root_location,
        ac.depth + 1,
        CONCAT(ac.chain_path, ' -> ', CAST(parent.location_id AS CHAR))
    FROM asset_chain ac
    JOIN EVE_ASSETS parent ON ac.location_id = parent.eveunique
    WHERE ac.depth < 20
      AND CAST(ac.location_id AS CHAR) NOT LIKE '600%'
      AND ac.location_id IS NOT NULL
)
SELECT 
    ac.ID,
    ac.toon_number,
    ac.location_flag,
    ac.location_id AS final_location_id,
    ac.description,
    ac.quantity,
    ac.type_id,
    ac.type_description,
    ac.eveunique,
    ac.date_insert,
    ac.unit_price,
    ac.forge_value,
    ac.depth,
    ac.chain_path,
    CASE 
        WHEN CAST(ac.location_id AS CHAR) LIKE '600%' THEN 'STATION'
        WHEN ac.location_id IS NULL THEN 'SPACE'
        ELSE 'OFFSTATION'
    END AS final_status
FROM asset_chain ac
INNER JOIN (
    SELECT ID, MAX(depth) AS max_depth
    FROM asset_chain
    GROUP BY ID
) deepest ON ac.ID = deepest.ID AND ac.depth = deepest.max_depth
";

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
// SECTION 2 & 3: Use recursive CTE for proper off-station detection
// ============================================================
$offstation_sql = "
WITH RECURSIVE asset_chain AS (
    SELECT 
        a.ID,
        a.toon_number,
        a.location_flag,
        a.location_id,
        a.description,
        a.quantity,
        a.type_id,
        a.type_description,
        a.eveunique,
        a.date_insert,
        a.unit_price,
        a.forge_value,
        1 AS depth
    FROM EVE_ASSETS a

    UNION ALL

    SELECT 
        ac.ID,
        ac.toon_number,
        ac.location_flag,
        parent.location_id,
        ac.description,
        ac.quantity,
        ac.type_id,
        ac.type_description,
        ac.eveunique,
        ac.date_insert,
        ac.unit_price,
        ac.forge_value,
        ac.depth + 1
    FROM asset_chain ac
    JOIN EVE_ASSETS parent ON ac.location_id = parent.eveunique
    WHERE ac.depth < 20
      AND CAST(ac.location_id AS CHAR) NOT LIKE '600%'
      AND ac.location_id IS NOT NULL
)
SELECT 
    ac.ID,
    ac.toon_number,
    ac.location_flag,
    ac.location_id AS final_location_id,
    ac.description,
    ac.quantity,
    ac.type_id,
    ac.type_description,
    ac.eveunique,
    ac.date_insert,
    ac.unit_price,
    ac.forge_value,
    ac.depth
FROM asset_chain ac
INNER JOIN (
    SELECT ID, MAX(depth) AS max_depth
    FROM asset_chain
    GROUP BY ID
) deepest ON ac.ID = deepest.ID AND ac.depth = deepest.max_depth
WHERE CAST(ac.location_id AS CHAR) NOT LIKE '600%'
  AND ac.location_id IS NOT NULL
ORDER BY ac.toon_number, ac.description
";

$offstation_result = mysqli_query($link, $offstation_sql);

if (!$offstation_result) {
    die("Recursive query error: " . mysqli_error($link));
}

$offstation_items = [];
$summary_data = [];
$detail_count = 0;

while ($row = mysqli_fetch_assoc($offstation_result)) {
    $tn = $row['toon_number'];
    if (!isset($summary_data[$tn])) {
        $summary_data[$tn] = [
            'toon_number' => $tn,
            'count' => 0
        ];
    }
    $summary_data[$tn]['count']++;
    $offstation_items[] = $row;
    $detail_count++;
}

// Get pilot names and pocket6 for summary
$pilot_info = [];
if (!empty($summary_data)) {
    $pilot_ids = implode(',', array_keys($summary_data));
    $pilot_sql = "SELECT toon_number, toon_name, pocket6 FROM PILOTS WHERE toon_number IN ($pilot_ids)";
    $pilot_result = mysqli_query($link, $pilot_sql);
    while ($p = mysqli_fetch_assoc($pilot_result)) {
        $pilot_info[$p['toon_number']] = $p;
    }
}

$summary_count = count($summary_data);

// ============================================================
// SECTION 3 Detail: Get full data with pilot info
// ============================================================
$detail_with_pilot = [];
if (!empty($offstation_items)) {
    $ids = array_column($offstation_items, 'ID');
    $id_list = implode(',', $ids);
    $detail_sql = "SELECT 
        a.*,
        p.toon_name,
        p.pocket6
    FROM EVE_ASSETS a
    JOIN PILOTS p ON a.toon_number = p.toon_number
    WHERE a.ID IN ($id_list)
    ORDER BY p.toon_name, a.description";
    $detail_result = mysqli_query($link, $detail_sql);
    while ($row = mysqli_fetch_assoc($detail_result)) {
        $detail_with_pilot[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Assets Analysis - Recursive Location Trace</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    <style>
        body { background-color: #0d1117; color: #c9d1d9; padding-top: 20px; font-family: 'Segoe UI', system-ui, sans-serif; }
        .card { background-color: #161b22; border: 1px solid #30363d; margin-bottom: 25px; }
        .card-header { background-color: #21262d; color: #58a6ff; font-weight: 600; border-bottom: 1px solid #30363d; }
        .table { color: #c9d1d9; }
        .table thead th { border-top: none; border-bottom: 2px solid #30363d; color: #8b949e; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .table tbody tr:hover { background-color: #1f242c; }
        .badge-safety { background-color: #da3633; color: #fff; }
        .badge-offstation { background-color: #f0883e; color: #000; }
        .badge-station { background-color: #238636; color: #fff; }
        .pocket-badge { background-color: #1f6feb; color: #fff; }
        .section-icon { margin-right: 10px; width: 20px; display: inline-block; text-align: center; }
        .stat-number { font-size: 2.2rem; font-weight: 700; }
        .stat-label { color: #8b949e; font-size: 0.9rem; }
        .stat-card { text-align: center; padding: 25px 15px; }
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #c9d1d9 !important; }
        .dataTables_wrapper .dataTables_filter input { background-color: #21262d; color: #c9d1d9; border: 1px solid #30363d; }
        .dataTables_wrapper .dataTables_length select { background-color: #21262d; color: #c9d1d9; border: 1px solid #30363d; }
        .page-item.active .page-link { background-color: #1f6feb; border-color: #1f6feb; }
        .page-link { background-color: #21262d; color: #58a6ff; border-color: #30363d; }
        .page-link:hover { background-color: #30363d; color: #58a6ff; border-color: #30363d; }
        .alert-dark { background-color: #21262d; border-color: #30363d; color: #8b949e; }
        .risk-high { color: #da3633; }
        .risk-moderate { color: #f0883e; }
        .risk-low { color: #3fb950; }
        code { background-color: #21262d; color: #ff7b72; padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        .depth-indicator { font-size: 0.75rem; color: #8b949e; }
    </style>
</head>
<body>

<div class="container-fluid">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1><i class="fas fa-project-diagram section-icon"></i>EVE Assets Analysis Dashboard</h1>
            <p class="text-muted">Recursive Location Chain Tracing &mdash; Off-Station Detection</p>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number text-danger"><?php echo $safety_count; ?></div>
                <div class="stat-label"><i class="fas fa-shield-alt section-icon"></i>Safety Assets</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number text-warning"><?php echo $summary_count; ?></div>
                <div class="stat-label"><i class="fas fa-users section-icon"></i>Pilots with Off-Station Items</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card">
                <div class="stat-number" style="color: #f0883e;"><?php echo $detail_count; ?></div>
                <div class="stat-label"><i class="fas fa-box-open section-icon"></i>Total Off-Station Items</div>
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
                            <th>Qty</th>
                            <th>Type</th>
                            <th>Location Flag</th>
                            <th>Location ID</th>
                            <th>Unit Price</th>
                            <th>Forge Value</th>
                            <th>Date</th>
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
                            <td class="text-right"><?php echo number_format($row['unit_price'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($row['forge_value'], 2); ?></td>
                            <td><small><?php echo $row['date_insert']; ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-dark"><i class="fas fa-info-circle"></i> No safety assets found.</div>
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
                            <th>Risk Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($summary_data as $tn => $data):
                            $pilot = $pilot_info[$tn] ?? ['toon_name' => 'Unknown', 'pocket6' => 'N/A'];
                            $count = $data['count'];
                        ?>
                        <tr>
                            <td class="text-muted"><?php echo $rank++; ?></td>
                            <td><strong><?php echo htmlspecialchars($pilot['toon_name']); ?></strong></td>
                            <td><span class="badge pocket-badge"><?php echo htmlspecialchars($pilot['pocket6']); ?></span></td>
                            <td class="text-right">
                                <span class="badge badge-offstation" style="font-size: 1.1em;">
                                    <?php echo number_format($count); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($count > 100): ?>
                                    <span class="risk-high"><i class="fas fa-skull-crossbones"></i> Critical</span>
                                <?php elseif ($count > 20): ?>
                                    <span class="risk-moderate"><i class="fas fa-exclamation-triangle"></i> High</span>
                                <?php elseif ($count > 5): ?>
                                    <span class="text-warning"><i class="fas fa-exclamation-circle"></i> Moderate</span>
                                <?php else: ?>
                                    <span class="risk-low"><i class="fas fa-check-circle"></i> Low</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-dark"><i class="fas fa-check-circle text-success"></i> All assets are safely stored in stations.</div>
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
                            <th>Qty</th>
                            <th>Type</th>
                            <th>Location Flag</th>
                            <th>Final Location ID</th>
                            <th>EVE Unique</th>
                            <th>Unit Price</th>
                            <th>Forge Value</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_with_pilot as $row): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['toon_name']); ?></strong></td>
                            <td><span class="badge pocket-badge"><?php echo htmlspecialchars($row['pocket6']); ?></span></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo number_format($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['type_description']); ?></td>
                            <td><code><?php echo htmlspecialchars($row['location_flag']); ?></code></td>
                            <td><code><?php echo $row['location_id']; ?></code></td>
                            <td><small class="text-muted"><?php echo $row['eveunique']; ?></small></td>
                            <td class="text-right"><?php echo number_format($row['unit_price'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($row['forge_value'], 2); ?></td>
                            <td><small><?php echo $row['date_insert']; ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-dark"><i class="fas fa-check-circle text-success"></i> No off-station assets detected.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

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
            lengthMenu: "Show _MENU_ per page",
            info: "Showing _START_ to _END_ of _TOTAL_ items",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        columnDefs: [
            { targets: [8, 9], className: "text-right" }
        ]
    });
});
</script>

</body>
</html>
