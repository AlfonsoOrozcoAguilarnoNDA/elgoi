<?php
/* ============================================================================
 * Pilot Group Control Panel
 * Author : Alfonso Orozco Aguilar
 * 
 * Purpose: Assign pilots to various control groups.
 *   - pocket6     : Political control group
 *   - supergroup  : Visibility group for queries
 *   - longtermplan: Statistics tracking for this screen only
 * 
 * Copyright (C) 2026
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * ============================================================================
 * Date: 2026-06-02
 * ============================================================================
 */

include "../config.php";

header('Content-Type: text/html; charset=utf-8');

// Handle AJAX save request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    $toon_number = intval($_POST['toon_number']);
    $supergroup = intval($_POST['supergroup']);
    $pocket6 = isset($_POST['pocket6']) ? strtoupper(trim($_POST['pocket6'])) : 'CLEAN';
    $longtermplan = isset($_POST['longtermplan']) ? strtoupper(trim($_POST['longtermplan'])) : 'UNKNOWN';

    $stmt = mysqli_prepare($link, "UPDATE `PILOTS` SET `supergroup` = ?, `pocket6` = ?, `longtermplan` = ? WHERE `toon_number` = ?");
    mysqli_stmt_bind_param($stmt, "issi", $supergroup, $pocket6, $longtermplan, $toon_number);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($link)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Fetch ALL pilots for statistics (not limited to 200)
$statsQuery = "SELECT `supergroup`, `pocket6`, `longtermplan` FROM `PILOTS`";
$statsResult = mysqli_query($link, $statsQuery);
$totalPilots = mysqli_num_rows($statsResult);

// Calculate statistics
$longtermplanStats = [];
$pocket6Stats = [];
$supergroupStats = [];

while ($statRow = mysqli_fetch_assoc($statsResult)) {
    $ltp = strtoupper(trim($statRow['longtermplan'] ?: 'UNKNOWN'));
    $p6 = strtoupper(trim($statRow['pocket6'] ?: 'CLEAN'));
    $sg = $statRow['supergroup'] ?: 1;

    $longtermplanStats[$ltp] = ($longtermplanStats[$ltp] ?? 0) + 1;
    $pocket6Stats[$p6] = ($pocket6Stats[$p6] ?? 0) + 1;
    $supergroupStats[$sg] = ($supergroupStats[$sg] ?? 0) + 1;
}

// Fetch pilots ordered alphabetically by toon_name (limit 200 for display)
$query = "SELECT `toon_number`, `toon_name`, `tradefield`, `supergroup`, `pocket6`, `longtermplan` 
          FROM `PILOTS` 
          ORDER BY `toon_name` ASC 
          LIMIT 200";
$result = mysqli_query($link, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilot Group Control</title>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #0f1419;
            color: #e0e0e0;
        }

        .header-info {
            background: linear-gradient(135deg, #1a2332 0%, #2d3e50 100%);
            color: #e0e0e0;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 1px solid #2a3f54;
        }

        .header-info h1 {
            margin: 0 0 15px 0;
            font-size: 28px;
            color: #ffffff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .header-info h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .header-info .description {
            line-height: 1.6;
            font-size: 14px;
            color: #b0b8c4;
        }

        .header-info .field-desc {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #3a4f64;
        }

        .header-info .field-desc ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .header-info .field-desc li {
            margin: 5px 0;
            color: #b0b8c4;
        }

        .header-info .field-desc code {
            background: rgba(52, 152, 219, 0.2);
            color: #74b9ff;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .header-info .field-desc em {
            color: #fdcb6e;
            font-size: 12px;
        }

        .container {
            background: #1a2332;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 1px solid #2a3f54;
            margin-bottom: 25px;
        }

        /* DataTables Dark Theme Overrides */
        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
            background: #1a2332;
            color: #e0e0e0;
        }

        table.dataTable thead th {
            background-color: #2d3e50;
            color: #ffffff;
            padding: 12px;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
        }

        table.dataTable tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #2a3f54;
        }

        table.dataTable tbody tr {
            background-color: #1a2332;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #1f2d3d;
        }

        table.dataTable tbody tr:hover {
            background-color: #2d3e50;
        }

        table.dataTable tbody tr:hover td {
            color: #ffffff;
        }

        /* Row number styling */
        .row-number {
            color: #636e72;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        /* Pilot name - CLEAR and BRIGHT for readability */
        .pilot-name {
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            text-shadow: 0 0 1px rgba(255,255,255,0.1);
        }

        .tradefield-cell {
            color: #a29bfe;
            font-weight: 600;
        }

        input[type="text"], input[type="number"] {
            padding: 6px 10px;
            border: 1px solid #3a4f64;
            border-radius: 4px;
            font-size: 13px;
            width: 120px;
            background-color: #0f1419;
            color: #e0e0e0;
        }

        input[type="text"]:focus, input[type="number"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.3);
            background-color: #1a2332;
        }

        .btn-save {
            background-color: #27ae60;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background-color: #2ecc71;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
        }

        .btn-save:disabled {
            background-color: #636e72;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .save-status {
            margin-left: 10px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }

        .save-status.success {
            color: #2ecc71;
        }

        .save-status.error {
            color: #e74c3c;
        }

        /* DataTables controls dark theme */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: #b0b8c4 !important;
            margin-bottom: 15px;
        }

        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: #0f1419;
            color: #e0e0e0;
            border: 1px solid #3a4f64;
            border-radius: 4px;
            padding: 4px 8px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background: #2d3e50 !important;
            color: #e0e0e0 !important;
            border: 1px solid #3a4f64 !important;
            border-radius: 4px;
            margin: 0 2px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #3498db !important;
            color: #ffffff !important;
            border-color: #3498db !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #3498db !important;
            color: #ffffff !important;
            border-color: #3498db !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            background: #1a2332 !important;
            color: #636e72 !important;
            border-color: #2a3f54 !important;
        }

        /* Statistics Section */
        .stats-section {
            background: linear-gradient(135deg, #1a2332 0%, #1f2d3d 100%);
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4);
            border: 1px solid #2a3f54;
        }

        .stats-section h2 {
            margin: 0 0 20px 0;
            color: #ffffff;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-section h2 i {
            color: #e17055;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .stats-card {
            background: #0f1419;
            border: 1px solid #2a3f54;
            border-radius: 8px;
            padding: 20px;
        }

        .stats-card h3 {
            margin: 0 0 15px 0;
            color: #74b9ff;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-card h3 i {
            color: #fdcb6e;
        }

        .stats-card .total-line {
            color: #b0b8c4;
            font-size: 13px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #2a3f54;
        }

        .stats-card .total-line strong {
            color: #ffffff;
            font-size: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #1a2332;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #b0b8c4;
            font-size: 13px;
        }

        .stat-bar-container {
            flex: 1;
            margin: 0 15px;
            height: 8px;
            background: #1a2332;
            border-radius: 4px;
            overflow: hidden;
        }

        .stat-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .stat-bar.longterm { background: linear-gradient(90deg, #6c5ce7, #a29bfe); }
        .stat-bar.pocket { background: linear-gradient(90deg, #00b894, #55efc4); }
        .stat-bar.supergroup { background: linear-gradient(90deg, #e17055, #fab1a0); }

        .stat-values {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: 100px;
            justify-content: flex-end;
        }

        .stat-count {
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
        }

        .stat-percent {
            color: #fdcb6e;
            font-weight: 600;
            font-size: 13px;
            background: rgba(253, 203, 110, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
        }

        .empty-stat {
            color: #636e72;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="header-info">
    <h1><i class="fas fa-map-marked-alt"></i> Pilot Group Control Panel</h1>
    <div class="description">
        This screen is designed to assign pilots to various control groups for organizational management and statistical tracking.
    </div>
    <div class="field-desc">
        <strong>Field Definitions:</strong>
        <ul>
            <li><code>pocket6</code> — Political control group. Determines the pilot's political alignment or restrictions.</li>
            <li><code>supergroup</code> — Visibility group for queries. Controls which query groups can access this pilot's data.</li>
            <li><code>longtermplan</code> — Statistics tracking field. Used exclusively for analytics and reporting on this screen.</li>
            <li><code>tradefield</code> — <span style="color: #a29bfe;">Read-only display</span>. Shows the pilot's automatically detected profession (purple = display only).</li>
        </ul>
        <em>Note: pocket6 and longtermplan values are automatically trimmed and converted to UPPERCASE upon saving.</em>
    </div>
</div>

<div class="container">
    <table id="pilotsTable" class="display">
        <thead>
            <tr>
                <th>#</th>
                <th>Pilot Name</th>
                <th>Trade Field</th>
                <th>Supergroup</th>
                <th>Pocket6</th>
                <th>Long Term Plan</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rowNum = 0;
            while ($row = mysqli_fetch_assoc($result)): 
                $rowNum++;
            ?>
            <tr data-toon="<?php echo htmlspecialchars($row['toon_number']); ?>">
                <td class="row-number"><?php echo $rowNum; ?></td>
                <td class="pilot-name"><?php echo htmlspecialchars($row['toon_name']); ?></td>
                <td class="tradefield-cell"><?php echo htmlspecialchars($row['tradefield']); ?></td>
                <td>
                    <input type="number" class="supergroup-input" 
                           value="<?php echo htmlspecialchars($row['supergroup']); ?>" 
                           min="1" max="999999">
                </td>
                <td>
                    <input type="text" class="pocket6-input" 
                           value="<?php echo htmlspecialchars($row['pocket6']); ?>" 
                           maxlength="20" placeholder="CLEAN">
                </td>
                <td>
                    <input type="text" class="longtermplan-input" 
                           value="<?php echo htmlspecialchars($row['longtermplan']); ?>" 
                           maxlength="100" placeholder="UNKNOWN">
                </td>
                <td>
                    <button class="btn-save" onclick="saveRow(this)"><i class="fas fa-save"></i> Save</button>
                    <span class="save-status"></span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Statistics Section -->
<div class="stats-section">
    <h2><i class="fas fa-chart-pie"></i> Pilot Distribution Statistics</h2>
    <div class="stats-grid">

        <!-- Long Term Plan Stats -->
        <div class="stats-card">
            <h3><i class="fas fa-calendar-alt"></i> Long Term Plan</h3>
            <div class="total-line">Total Pilots: <strong><?php echo number_format($totalPilots); ?></strong></div>
            <?php if (empty($longtermplanStats)): ?>
                <div class="empty-stat">No data available</div>
            <?php else: ?>
                <?php 
                arsort($longtermplanStats);
                foreach ($longtermplanStats as $value => $count): 
                    $percent = round(($count / $totalPilots) * 100, 1);
                ?>
                <div class="stat-row">
                    <span class="stat-label"><?php echo htmlspecialchars($value); ?></span>
                    <div class="stat-bar-container">
                        <div class="stat-bar longterm" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div class="stat-values">
                        <span class="stat-count"><?php echo number_format($count); ?></span>
                        <span class="stat-percent"><?php echo $percent; ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pocket6 Stats -->
        <div class="stats-card">
            <h3><i class="fas fa-shield-alt"></i> Pocket6 (Political Group)</h3>
            <div class="total-line">Total Pilots: <strong><?php echo number_format($totalPilots); ?></strong></div>
            <?php if (empty($pocket6Stats)): ?>
                <div class="empty-stat">No data available</div>
            <?php else: ?>
                <?php 
                arsort($pocket6Stats);
                foreach ($pocket6Stats as $value => $count): 
                    $percent = round(($count / $totalPilots) * 100, 1);
                ?>
                <div class="stat-row">
                    <span class="stat-label"><?php echo htmlspecialchars($value); ?></span>
                    <div class="stat-bar-container">
                        <div class="stat-bar pocket" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div class="stat-values">
                        <span class="stat-count"><?php echo number_format($count); ?></span>
                        <span class="stat-percent"><?php echo $percent; ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Supergroup Stats -->
        <div class="stats-card">
            <h3><i class="fas fa-users"></i> Supergroup (Visibility)</h3>
            <div class="total-line">Total Pilots: <strong><?php echo number_format($totalPilots); ?></strong></div>
            <?php if (empty($supergroupStats)): ?>
                <div class="empty-stat">No data available</div>
            <?php else: ?>
                <?php 
                arsort($supergroupStats);
                foreach ($supergroupStats as $value => $count): 
                    $percent = round(($count / $totalPilots) * 100, 1);
                ?>
                <div class="stat-row">
                    <span class="stat-label">Group <?php echo htmlspecialchars($value); ?></span>
                    <div class="stat-bar-container">
                        <div class="stat-bar supergroup" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    <div class="stat-values">
                        <span class="stat-count"><?php echo number_format($count); ?></span>
                        <span class="stat-percent"><?php echo $percent; ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- jQuery & DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#pilotsTable').DataTable({
        pageLength: 200,
        lengthMenu: [[50, 100, 200], [50, 100, 200]],
        language: {
            search: "Filter pilots:",
            lengthMenu: "Show _MENU_ pilots per page",
            info: "Showing _START_ to _END_ of _TOTAL_ pilots",
            infoEmpty: "No pilots found",
            infoFiltered: "(filtered from _MAX_ total pilots)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            zeroRecords: "No matching pilots found"
        },
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: [0, 6] },
            { searchable: false, targets: [0, 6] }
        ]
    });
});

function saveRow(button) {
    var row = $(button).closest('tr');
    var toonNumber = row.data('toon');
    var supergroup = row.find('.supergroup-input').val();
    var pocket6 = row.find('.pocket6-input').val().toUpperCase().trim();
    var longtermplan = row.find('.longtermplan-input').val().toUpperCase().trim();
    var statusSpan = row.find('.save-status');

    // Update inputs with trimmed/uppercase values
    row.find('.pocket6-input').val(pocket6);
    row.find('.longtermplan-input').val(longtermplan);

    $(button).prop('disabled', true);
    statusSpan.hide();

    $.ajax({
        url: window.location.href,
        type: 'POST',
        data: {
            action: 'save',
            toon_number: toonNumber,
            supergroup: supergroup,
            pocket6: pocket6,
            longtermplan: longtermplan
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                statusSpan.html('<i class="fas fa-check"></i> Saved').removeClass('error').addClass('success').show();
                setTimeout(function() {
                    statusSpan.fadeOut();
                }, 2000);
            } else {
                statusSpan.html('<i class="fas fa-times"></i> Error').removeClass('success').addClass('error').show();
            }
        },
        error: function(xhr, status, error) {
            statusSpan.html('<i class="fas fa-times"></i> Failed').removeClass('success').addClass('error').show();
        },
        complete: function() {
            $(button).prop('disabled', false);
        }
    });
}
</script>

</body>
</html>
