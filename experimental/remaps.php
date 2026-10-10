<?php
/**
 * remap.php
 * EVE Online Pilot Remap Dashboard
 * PHP 8.x Procedural - GPL License
 *
 * Purpose: Display pilot attributes and remap cooldown status
 * to help identify which pilots can reconfigure their attributes.
 */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include "../config.php";
include_once '../ui_functions.php';
check_authorization();

global $link;

// Si la fecha de cooldown ya pasó hace más de estos días, se muestra READY
// en lugar de "N days ago".
const READY_THRESHOLD_DAYS = 365;

// --- Database Query ---
$sql = "SELECT `toon_name`, `DOB`, `pocket6`, `attrib`, `remaps`
        FROM `PILOTS`
        WHERE `toon_name` NOT LIKE '%catalog%'
        ORDER BY `DOB` ASC";

$result = mysqli_query($link, $sql);

if (!$result) {
    die("Database Error: " . mysqli_error($link));
}

$pocket_colors = [
    'EXPER' => '#28a745', 'CLEAN' => '#0078d7', 'SANGO' => '#ffc107',
    'LUCKY' => '#6f42c1', 'NOKIA' => '#e81123', 'YENN'  => '#cccccc',
    'OTHER' => '#fd7e14',
];

$pilots = [];
$ready_count = 0;
$now = new DateTime('now', new DateTimeZone('UTC'));

while ($row = mysqli_fetch_assoc($result)) {
    $pocket_raw = strtoupper(trim($row['pocket6'] ?? '')) ?: 'CLEAN';

    $pilot = [
        'toon_name'     => htmlspecialchars($row['toon_name'], ENT_QUOTES, 'UTF-8'),
        'DOB'           => $row['DOB'],
        'pocket6'       => htmlspecialchars($pocket_raw, ENT_QUOTES, 'UTF-8'),
        'pocket_key'    => $pocket_raw,
        'remaps'        => (int)($row['remaps'] ?? 0),
        'attributes'    => [],
        'cooldown_date' => null,
        'days_diff'     => null,
        'status'        => 'unknown',
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
                        $ready_count++;
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

echo ui_header("Pilot Remap Dashboard");
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
        .ready-badge {
            border-color: #28a745;
            color: #28a745;
            margin-right: 8px;
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
        #remapTable { font-size: 0.83rem; color: #ced4da; }
        #remapTable thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            text-transform: uppercase;
            font-size: 0.73rem;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        #remapTable tbody tr { background-color: #1e2126; color: #ced4da; }
        #remapTable tbody tr:nth-child(even) { background-color: #1a1d21; }
        #remapTable td, #remapTable th { border-color: #2c3035 !important; vertical-align: middle !important; }

        /* Hover: fondo claro-oscuro y texto SIEMPRE legible */
        #remapTable tbody tr:hover { background-color: #2a3040 !important; }
        #remapTable tbody tr:hover > td { color: #fff; }
        #remapTable tbody tr:hover .text-muted { color: #adb5bd !important; }

        /* Los colores semánticos se conservan en hover (mayor especificidad) */
        #remapTable tbody tr:hover > td.attr-intel { color: #4dabf7; }
        #remapTable tbody tr:hover > td.attr-mem   { color: #69db7c; }
        #remapTable tbody tr:hover > td.attr-per   { color: #ffa94d; }
        #remapTable tbody tr:hover > td.attr-will  { color: #ff6b6b; }
        #remapTable tbody tr:hover > td.attr-cha   { color: #da77f2; }

        .row-num { color: #6c757d; font-size: 0.8rem; text-align: center; }
        .pilot-name-cell { line-height: 1.4; }
        .dob-cell { white-space: nowrap; }

        /* Pocket badge */
        .pocket-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
            text-transform: uppercase;
        }

        /* Remaps badge */
        .remap-badge {
            display: inline-block;
            padding: 1px 8px;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 2px;
            color: #fff;
            margin-top: 3px;
        }
        .remap-yes { background-color: #28a745; }
        .remap-no  { background-color: #dc3545; }

        /* Atributos */
        .attr-value { font-family: monospace; font-weight: 700; font-size: 1rem; }
        .attr-intel { color: #4dabf7; }
        .attr-mem   { color: #69db7c; }
        .attr-per   { color: #ffa94d; }
        .attr-will  { color: #ff6b6b; }
        .attr-cha   { color: #da77f2; }

        /* Estado del cooldown */
        .status-ready   { color: #28a745; font-weight: 700; }
        .status-waiting { color: #dc3545; font-weight: 700; }
        .status-today   { color: #ffc107; font-weight: 700; }

        .legend { color: #6c757d; font-size: 0.8rem; margin-top: 14px; }
    </style>

<!-- HEADER -->
<div class="page-header">
    <h4><i class="fas fa-dna mr-2" style="color:#007bff;"></i>Pilot Remap Dashboard</h4>
    <div>
        <span class="total-badge ready-badge"><i class="fas fa-check-circle mr-1"></i><?php echo number_format($ready_count); ?> READY</span>
        <span class="total-badge"><i class="fas fa-list mr-1"></i><?php echo number_format(count($pilots)); ?> pilotos</span>
    </div>
</div>

<!-- TABLA -->
<div class="container-fluid">
    <div class="table-responsive rounded shadow">
        <table id="remapTable" class="table table-sm table-bordered w-100">
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th><i class="fas fa-user-astronaut"></i> Pilot Name</th>
                    <th class="text-center"><i class="fas fa-shield-alt"></i> Pocket</th>
                    <th><i class="fas fa-birthday-cake"></i> DOB</th>
                    <th class="text-center"><i class="fas fa-brain"></i> INT</th>
                    <th class="text-center"><i class="fas fa-memory"></i> MEM</th>
                    <th class="text-center"><i class="fas fa-eye"></i> PER</th>
                    <th class="text-center"><i class="fas fa-fist-raised"></i> WIL</th>
                    <th class="text-center"><i class="fas fa-comments"></i> CHA</th>
                    <th><i class="fas fa-clock"></i> Cooldown Date</th>
                    <th><i class="fas fa-hourglass-half"></i> Days</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNum = 1; foreach ($pilots as $p):
                    $pb = $pocket_colors[$p['pocket_key']] ?? '#495057';
                    $pt = in_array($p['pocket_key'], ['SANGO', 'YENN']) ? '#111' : '#fff';
                ?>
                <tr>
                    <td class="row-num"><?php echo $rowNum++; ?></td>
                    <td class="pilot-name-cell">
                        <strong class="text-white"><?php echo $p['toon_name']; ?></strong><br>
                        <?php if ($p['remaps'] > 0): ?>
                            <span class="remap-badge remap-yes" title="Remaps">
                                <i class="fas fa-check"></i> <?php echo $p['remaps']; ?>
                            </span>
                        <?php else: ?>
                            <span class="remap-badge remap-no" title="Remaps">
                                <i class="fas fa-times"></i> 0
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="pocket-badge" style="background-color:<?php echo $pb; ?>;color:<?php echo $pt; ?>;">
                            <?php echo $p['pocket6']; ?>
                        </span>
                    </td>
                    <td class="dob-cell"><?php echo ($p['DOB'] ? date('Y-m-d', strtotime($p['DOB'])) : 'N/A'); ?></td>

                    <!-- Attributes -->
                    <td class="text-center attr-value attr-intel"><?php echo $p['attributes']['intelligence'] ?? '-'; ?></td>
                    <td class="text-center attr-value attr-mem"><?php echo $p['attributes']['memory'] ?? '-'; ?></td>
                    <td class="text-center attr-value attr-per"><?php echo $p['attributes']['perception'] ?? '-'; ?></td>
                    <td class="text-center attr-value attr-will"><?php echo $p['attributes']['willpower'] ?? '-'; ?></td>
                    <td class="text-center attr-value attr-cha"><?php echo $p['attributes']['charisma'] ?? '-'; ?></td>

                    <!-- Cooldown Date -->
                    <td>
                        <?php if ($p['cooldown_date']): ?>
                            <?php echo $p['cooldown_date']; ?><?php echo ($p['cooldown_date'] !== 'Invalid Date') ? ' UTC' : ''; ?>
                        <?php else: ?>
                            <span class="text-muted">No data</span>
                        <?php endif; ?>
                    </td>

                    <!-- Days Difference -->
                    <td data-order="<?php echo ($p['days_diff'] !== null) ? $p['days_diff'] : 999999; ?>">
                        <?php if ($p['cooldown_date'] && $p['days_diff'] !== null): ?>
                            <?php if ($p['status'] === 'ready'): ?>
                                <span class="status-ready">
                                    <i class="fas fa-check-circle"></i>
                                    <?php if (abs($p['days_diff']) > READY_THRESHOLD_DAYS): ?>
                                        READY
                                    <?php else: ?>
                                        <?php echo abs($p['days_diff']); ?> days ago
                                    <?php endif; ?>
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

    <!-- Legend -->
    <div class="legend">
        <i class="fas fa-info-circle"></i>
        <strong>Legend:</strong>
        <span class="status-ready">Green</span> = Cooldown expired, remap available
        (<span class="status-ready">READY</span> = expired more than <?php echo READY_THRESHOLD_DAYS; ?> days ago).
        <span class="status-waiting">Red</span> = Still on cooldown.
        <span class="status-today">Yellow</span> = Cooldown expires today.
    </div>
</div>
<?php echo ui_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#remapTable').DataTable({
        pageLength: 100,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        order: [[3, "asc"]], // DOB por defecto
        columnDefs: [
            { orderable: false, targets: [0] }
        ],
        language: {
            search:       "Buscar:",
            lengthMenu:   "Mostrar _MENU_ pilotos",
            info:         "Mostrando _START_ a _END_ de _TOTAL_ pilotos",
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
