<?php
/* ============================================================================
 * Pilot Group Control Panel
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

// Fetch pilots ordered alphabetically by toon_name
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

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }

        .header-info {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .header-info h1 {
            margin: 0 0 15px 0;
            font-size: 28px;
        }

        .header-info .description {
            line-height: 1.6;
            font-size: 14px;
            opacity: 0.95;
        }

        .header-info .field-desc {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .header-info .field-desc ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .header-info .field-desc li {
            margin: 5px 0;
        }

        .header-info .field-desc code {
            background: rgba(255,255,255,0.2);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }

        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
        }

        table.dataTable thead th {
            background-color: #2c3e50;
            color: white;
            padding: 12px;
            font-weight: 600;
        }

        table.dataTable tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        table.dataTable tbody tr:hover {
            background-color: #f8f9fa;
        }

        .tradefield-cell {
            color: #8e44ad;
            font-weight: 600;
        }

        input[type="text"], input[type="number"] {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 13px;
            width: 120px;
        }

        input[type="text"]:focus, input[type="number"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52,152,219,0.2);
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
            transition: background-color 0.2s;
        }

        .btn-save:hover {
            background-color: #229954;
        }

        .btn-save:disabled {
            background-color: #95a5a6;
            cursor: not-allowed;
        }

        .save-status {
            margin-left: 10px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }

        .save-status.success {
            color: #27ae60;
        }

        .save-status.error {
            color: #e74c3c;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 15px;
        }
    </style>
</head>
<body>

<div class="header-info">
    <h1>🎯 Pilot Group Control Panel</h1>
    <div class="description">
        This screen is designed to assign pilots to various control groups for organizational management and statistical tracking.
    </div>
    <div class="field-desc">
        <strong>Field Definitions:</strong>
        <ul>
            <li><code>pocket6</code> — Political control group. Determines the pilot's political alignment or restrictions.</li>
            <li><code>supergroup</code> — Visibility group for queries. Controls which query groups can access this pilot's data.</li>
            <li><code>longtermplan</code> — Statistics tracking field. Used exclusively for analytics and reporting on this screen.</li>
            <li><code>tradefield</code> — <span style="color: #d8a8e8;">Read-only display</span>. Shows the pilot's automatically detected profession (purple = display only).</li>
        </ul>
        <em>Note: pocket6 and longtermplan values are automatically trimmed and converted to UPPERCASE upon saving.</em>
    </div>
</div>

<div class="container">
    <table id="pilotsTable" class="display">
        <thead>
            <tr>
                <th>Pilot Name</th>
                <th>Trade Field</th>
                <th>Supergroup</th>
                <th>Pocket6</th>
                <th>Long Term Plan</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr data-toon="<?php echo htmlspecialchars($row['toon_number']); ?>">
                <td><strong><?php echo htmlspecialchars($row['toon_name']); ?></strong></td>
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
                    <button class="btn-save" onclick="saveRow(this)">Save</button>
                    <span class="save-status"></span>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- jQuery & DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    $('#pilotsTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, 200], [10, 25, 50, 100, 200]],
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
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: 5 }
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
                statusSpan.text('✓ Saved').removeClass('error').addClass('success').show();
                setTimeout(function() {
                    statusSpan.fadeOut();
                }, 2000);
            } else {
                statusSpan.text('✗ Error: ' + (response.error || 'Unknown')).removeClass('success').addClass('error').show();
            }
        },
        error: function(xhr, status, error) {
            statusSpan.text('✗ Failed: ' + error).removeClass('success').addClass('error').show();
        },
        complete: function() {
            $(button).prop('disabled', false);
        }
    });
}
</script>

</body>
</html>
