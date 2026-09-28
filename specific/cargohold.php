<?php
/**
 * EVE Online Real price detector
 * Detects duplicate fittings across pilots by content (different name, same items)
 * and by name (same name, different items).
 * Also updates PILOTS.numberfits with the actual count of fittings per pilot.
 *
 * Copyright (C) 2026  Alfonso Orozco Aguilar
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
// Include database connection ($link must be defined here)
require_once '../config.php';
include_once '../ui_functions.php';

check_authorization();

echo ui_header("Checking CargoHold");
echo ui_generate_navbar();



$raw_text = "";
$items = [];
$total_volume = 0.00;
$total_price = 0.00;
$total_items_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['raw_data'])) {
    $raw_text = trim($_POST['raw_data']);
    $lines = explode("\n", str_replace("\r", "", $raw_text));

    // Prepare SQL to fetch type_id, unit_price, and volume from your EVE_ASSETS table
    $sql = "SELECT type_id, unit_price, forge_value FROM EVE_ASSETS WHERE type_description = ? OR description = ? LIMIT 1";
    $stmt = mysqli_prepare($link, $sql);

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $cols = explode("\t", $line);

        $item_name  = trim($cols[0] ?? '');
        $raw_qty    = str_replace(',', '', trim($cols[1] ?? ''));
        $quantity   = is_numeric($raw_qty) && (int)$raw_qty > 0 ? (int)$raw_qty : 1;
        $category   = trim($cols[2] ?? 'N/A');
        $group_type = trim($cols[3] ?? 'N/A');
        $slot       = trim($cols[4] ?? 'N/A');

        // Parse Volume from raw text
        $raw_vol   = str_replace(['m3', ',', ' '], '', trim($cols[5] ?? '0'));
        $unit_vol  = is_numeric($raw_vol) ? (float)$raw_vol : 0.00;

        // Parse Price from raw text (Fallback if not found in DB)
        $raw_price  = str_replace(['ISK', ',', ' '], '', trim($cols[6] ?? '0'));
        $unit_price = is_numeric($raw_price) ? (float)$raw_price : 0.00;

        $type_id = null;

        // Query Database for type_id and unit_price
        if ($stmt && !empty($item_name)) {
            mysqli_stmt_bind_param($stmt, "ss", $item_name, $item_name);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($row = mysqli_fetch_assoc($result)) {
                $type_id = $row['type_id'];
                if ($row['unit_price'] > 0) {
                    $unit_price = (float)$row['unit_price'];
                }
            }
        }

        $unit_vol=$unit_vol/$quantity;
		
		$line_volume = $unit_vol * $quantity;
        $line_price  = $unit_price * $quantity;

        $total_volume += $line_volume;
        $total_price  += $line_price;
        $total_items_count += $quantity;

        $items[] = [
            'name'        => $item_name,
            'quantity'    => $quantity,
            'category'    => $category,
            'group'       => $group_type,
            'slot'        => $slot,
            'unit_vol'    => $unit_vol,
            'total_vol'   => $line_volume,
            'unit_price'  => $unit_price,
            'total_price' => $line_price,
            'type_id'     => $type_id
        ];
    }
    if ($stmt) mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online — Cargo Hold & Market Inspector</title>
    <!-- Bootstrap 4.6.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <!-- DataTables Bootstrap 4 Styling -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">

    <style>
        body {
            background-color: #1a1d21;
            color: #e0e0e0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 60px;
        }
        .navbar-eve {
            background-color: #0b0c0e;
            border-bottom: 2px solid #495057;
            margin-bottom: 20px;
        }
        .card {
            background-color: #1e2126;
            border: 1px solid #343a40;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            color: #adb5bd;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
            background-color: #1e2126;
            border: 1px solid #343a40;
        }
        .stats-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #5dade2;
            font-family: 'Courier New', monospace;
        }
        .stats-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table {
            color: #e0e0e0;
            font-size: 0.82rem;
        }
        .table thead th {
            background-color: #0d0f11;
            color: #adb5bd;
            border-color: #343a40;
            white-space: nowrap;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            background-color: #1e2126;
        }
        .table tbody td {
            color: #e0e0e0;
            border-color: #343a40;
            vertical-align: middle;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #22262c;
        }
        .table tbody tr:hover {
            background-color: #2a3040 !important;
        }
        .trade-pill {
            background-color: #2d3748;
            color: #bb86fc;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .sp-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #5dade2;
        }
        .isk-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #2ecc71;
        }
        .textarea-eve {
            background-color: #16191c;
            border: 1px solid #343a40;
            color: #e0e0e0;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }
        .textarea-eve:focus {
            background-color: #16191c;
            color: #fff;
            border-color: #007bff;
            box-shadow: none;
        }
        /* DataTables Custom Dark Theme Adjustments */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            color: #adb5bd !important;
            padding: 10px 15px;
            font-size: 0.8rem;
        }
        .dataTables_wrapper .dataTables_filter input {
            background-color: #2a2d31;
            border: 1px solid #495057;
            color: #fff;
            border-radius: 4px;
            margin-left: 0.5em;
        }
        .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
        }
        .page-link {
            background-color: #0d0f11;
            border-color: #343a40;
            color: #adb5bd;
        }
        .page-link:hover {
            background-color: #2a3040;
            color: #fff;
            border-color: #343a40;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark navbar-eve px-3">
        <span class="navbar-brand mb-0 h5">
            <i class="fas fa-boxes mr-2"></i>EVE Online — Cargo Hold Inspector
        </span>
        <span class="text-muted small">
            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M Y'); ?>
        </span>
    </nav>

    <div class="container-fluid px-4">

        <!-- INPUT FORM CARD -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-paste mr-2"></i><strong>Paste Cargo Hold Data</strong>
                <small class="float-right text-muted">
                    <i class="fas fa-info-circle mr-1"></i>Copy directly from EVE Online client inventory
                </small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="form-group mb-2">
                        <textarea name="raw_data" class="form-control textarea-eve" rows="5" placeholder="Scourge Light Missile&#09;13,000&#09;Light Missile&#09;&#09;&#09;195 m3&#09;184,080.00 ISK..."><?php echo htmlspecialchars($raw_text); ?></textarea>
                    </div>
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-calculator mr-1"></i> Process Cargo Hold
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <!-- TOTALS & STATISTICS -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo number_format(count($items)); ?></div>
                    <div class="stats-label">Unique Item Types</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo number_format($total_items_count); ?></div>
                    <div class="stats-label">Total Units</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number text-info"><?php echo number_format($total_volume, 2); ?> m³</div>
                    <div class="stats-label">Total Volume</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number text-success"><?php echo number_format($total_price, 2); ?> ISK</div>
                    <div class="stats-label">Estimated Value</div>
                </div>
            </div>
        </div>

        <!-- MAIN DATATABLE -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list mr-2"></i><strong>Cargo Hold Inventory Breakdown</strong>
                <span class="float-right text-muted" style="font-size:0.75rem;">
                    <i class="fas fa-sort mr-1"></i>Click columns to sort dynamically
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="cargoTable" class="table table-hover mb-0 w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Group</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit Vol (m³)</th>
                                <th class="text-right">Total Vol (m³)</th>
                                <th class="text-right">Unit Price (ISK)</th>
                                <th class="text-right">Total Price (ISK)</th>
                                <th class="text-center">Fuzzwork Type ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                                <td>
                                    <strong class="text-white"><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <?php if ($item['slot'] !== 'N/A' && !empty($item['slot'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-cog mr-1"></i><?php echo htmlspecialchars($item['slot']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="trade-pill"><?php echo htmlspecialchars($item['category']); ?></span></td>
                                <td class="text-muted"><?php echo htmlspecialchars($item['group']); ?></td>
                                <td class="text-right font-weight-bold" data-order="<?php echo $item['quantity']; ?>">
                                    <?php echo number_format($item['quantity']); ?>
                                </td>
                                <td class="text-right sp-value" data-order="<?php echo $item['unit_vol']; ?>">
                                    <?php echo number_format($item['unit_vol'], 2); ?>
                                </td>
                                <td class="text-right sp-value" data-order="<?php echo $item['total_vol']; ?>">
                                    <?php echo number_format($item['total_vol'], 2); ?>
                                </td>
                                <td class="text-right isk-value" data-order="<?php echo $item['unit_price']; ?>">
                                    <?php echo number_format($item['unit_price'], 2); ?>
                                </td>
                                <td class="text-right isk-value" data-order="<?php echo $item['total_price']; ?>">
                                    <?php echo number_format($item['total_price'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($item['type_id'])): ?>
                                        <a href="https://market.fuzzwork.co.uk/type/<?php echo $item['type_id']; ?>/" target="_blank" class="btn btn-xs btn-outline-info py-0 px-2">
                                            <i class="fas fa-external-link-alt mr-1"></i><?php echo $item['type_id']; ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4 mb-4 text-muted">
            <small>
                <i class="fas fa-code mr-1"></i>EVE Online Cargo Hold Inspector | 
                <i class="fas fa-shopping-cart mr-1"></i>Fuzzwork Market Integration | 
                Designed for high-speed market research
            </small>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#cargoTable').DataTable({
                "order": [[ 6, "desc" ]], // Default sorting by Total Volume (Column Index 6)
                "pageLength": 100,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "language": {
                    "search": "<i class='fas fa-search mr-1'></i> Filter records:",
                    "lengthMenu": "Display _MENU_ items per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ items",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "<i class='fas fa-angle-right'></i>",
                        "previous": "<i class='fas fa-angle-left'></i>"
                    }
                }
            });
        });
    </script>
</body>
</html>
