<?php
/* 
License MIT
Alfonso Orozco Aguilar
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "../config.php";
include_once '../ui_functions.php';
check_authorization();

global $link;

$sql = "SELECT 
            T.typeName,
            T.groupID,
            P.toon_name,
            P.pocket6,
            P.gf,
            P.acctype,
            SUM(A.quantity) as total_qty
        FROM invTypes2 T
        INNER JOIN EVE_ASSETS A ON T.typeID = A.type_id
        INNER JOIN PILOTS P ON A.toon_number = P.toon_number
        WHERE T.groupID IN (1042, 1034, 1040, 1041)
        GROUP BY T.typeID, P.toon_number, P.acctype
        ORDER BY T.groupID ASC, T.typeName ASC";

$result = mysqli_query($link, $sql);
$total_rows = $result ? mysqli_num_rows($result) : 0;

$tiers = [
    1042 => ['label' => 'P1 - Basic',       'color' => '#6c757d'],
    1034 => ['label' => 'P2 - Refined',     'color' => '#17a2b8'],
    1040 => ['label' => 'P3 - Specialized', 'color' => '#007bff'],
    1041 => ['label' => 'P4 - Advanced',    'color' => '#6f42c1'],
];

$pocket_colors = [
    'EXPER' => '#28a745', 'CLEAN' => '#0078d7', 'SANGO' => '#ffc107',
    'LUCKY' => '#6f42c1', 'NOKIA' => '#e81123', 'YENN'  => '#cccccc',
    'OTHER' => '#fd7e14',
];

echo ui_header("PI Inventory Manager");
echo crew_navbar();
?>    
    <style>
        body {
            background-color: #0d0f11;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 70px;
            padding-bottom: 70px;
        }

        /* ── HEADER DE PÁGINA ── */
        .page-header {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 14px 20px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h4 { color: #fff; margin: 0; font-weight: 600; }
        .total-badge {
            background-color: #0d0f11;
            border: 1px solid #007bff;
            color: #007bff;
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 3px;
        }

        /* ── DATATABLES DARK ── */
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info { color: #adb5bd !important; }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background-color: #1e2126 !important;
            border: 1px solid #495057 !important;
            color: #e0e0e0 !important;
            border-radius: 3px;
            padding: 3px 8px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #adb5bd !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #007bff !important;
            color: #fff !important;
            border-color: #007bff !important;
            border-radius: 3px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #0056b3 !important;
            color: #fff !important;
            border-color: #0056b3 !important;
            border-radius: 3px;
        }

        /* ── TABLA ── */
        #piTable { font-size: 0.83rem; }
        #piTable thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            text-transform: uppercase;
            font-size: 0.73rem;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        #piTable tbody tr { background-color: #1e2126; }
        #piTable tbody tr:nth-child(even) { background-color: #1a1d21; }
        #piTable tbody tr:hover { background-color: #2a3040 !important; color: #fff; }
        #piTable td, #piTable th { border-color: #2c3035 !important; vertical-align: middle !important; }

        /* Tier badge */
        .tier-badge {
            display: inline-block;
            padding: 2px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            border-radius: 2px;
            color: #fff;
            white-space: nowrap;
        }

        /* Pocket badge */
        .pocket-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
        }

        /* AccType badge */
        .acc-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
            border: 1px solid #495057;
            color: #ced4da;
            background-color: #1a1d21;
        }
        .acc-omega { border-color: #f1c40f; color: #f1c40f; }
        .acc-alpha { border-color: #95a5a6; color: #95a5a6; }

        /* Cantidad */
        .qty-val { color: #28a745; font-family: monospace; font-weight: 700; }

        /* Bandera GF */
        .flag-active   { color: #dc3545; font-size: 1rem; }
        .flag-inactive { color: #2c3035; font-size: 1rem; }
    </style>

<!-- HEADER -->
<div class="page-header">
    <h4><i class="fas fa-microchip mr-2" style="color:#007bff;"></i>PI Inventory Manager</h4>
    <span class="total-badge"><i class="fas fa-list mr-1"></i><?php echo number_format($total_rows); ?> registros</span>
</div>

<!-- TABLA -->
<div class="container-fluid">
    <div class="table-responsive rounded shadow">
        <table id="piTable" class="table table-sm table-bordered w-100">
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Material</th>
                    <th>Piloto</th>
                    <th class="text-center">Pocket</th>
                    <th class="text-center">GF</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-center">Acc</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)):
                    $tier   = $tiers[$row['groupID']];
                    $pv     = strtoupper(trim($row['pocket6'] ?? ''));
                    $pb     = $pocket_colors[$pv] ?? '#495057';
                    $pt     = in_array($pv, ['SANGO','YENN']) ? '#111' : '#fff';
                    $acctype = strtolower($row['acctype'] ?? '');
                    $accClass = ($acctype === 'omega') ? 'acc-omega' : (($acctype === 'alpha') ? 'acc-alpha' : '');
                ?>
                <tr>
                    <td data-order="<?php echo $row['groupID']; ?>">
                        <span class="tier-badge" style="background-color:<?php echo $tier['color']; ?>;">
                            <?php echo $tier['label']; ?>
                        </span>
                    </td>
                    <td class="text-white font-weight-bold"><?php echo htmlspecialchars($row['typeName']); ?></td>
                    <td><?php echo htmlspecialchars($row['toon_name']); ?></td>
                    <td class="text-center">
                        <span class="pocket-badge" style="background-color:<?php echo $pb; ?>;color:<?php echo $pt; ?>;">
                            <?php echo htmlspecialchars($row['pocket6']); ?>
                        </span>
                    </td>
                    <td class="text-center" data-order="<?php echo $row['gf']; ?>">
                        <i class="fas fa-flag <?php echo ($row['gf'] == 1) ? 'flag-active' : 'flag-inactive'; ?>"
                           title="<?php echo ($row['gf'] == 1) ? 'GF activo' : 'Sin GF'; ?>"></i>
                    </td>
                    <td class="text-right qty-val" data-order="<?php echo $row['total_qty']; ?>">
                        <?php echo number_format($row['total_qty'], 0, '.', ','); ?>
                    </td>
                    <td class="text-center">
                        <span class="acc-badge <?php echo $accClass; ?>">
                            <?php echo htmlspecialchars(strtoupper($row['acctype'])); ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php echo ui_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#piTable').DataTable({
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        order: [[0, "asc"], [1, "asc"]],
        language: {
            search:       "Buscar:",
            lengthMenu:   "Mostrar _MENU_ registros",
            info:         "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty:    "Sin resultados",
            infoFiltered: "(filtrado de _MAX_ totales)",
            zeroRecords:  "No se encontraron resultados",
            paginate: { first: "«", last: "»", next: "›", previous: "‹" }
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
    });
});
</script>
