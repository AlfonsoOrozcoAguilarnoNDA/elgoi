<?php
/*
License Mit
Alfonso Orozco Aguilar
*/
/**
 * EVE Online - Diplomatic Control Tool
 * Stack: PHP 8.x Procedural, MariaDB, Bootstrap 4.6.2, FA 5.15.4
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../config.php';
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

$self = basename(__FILE__);

$f_owner  = $_POST['owner_email']        ?? '';
$f_pilot  = $_POST['pilot_name']         ?? '';
$f_target = $_POST['target_description'] ?? '';
$f_pocket = $_POST['pocket6']            ?? '';

$res_owners      = mysqli_query($link, "SELECT DISTINCT owner_email FROM DIPLOMATIC WHERE owner_email != '' ORDER BY owner_email ASC");
$res_pilots_list = mysqli_query($link, "SELECT DISTINCT toon_name FROM PILOTS ORDER BY toon_name ASC");
$res_corps       = mysqli_query($link, "SELECT DISTINCT target_description FROM DIPLOMATIC WHERE target_description != '' ORDER BY target_description ASC");
$res_pockets     = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 != '' ORDER BY pocket6 ASC");

function get_pocket_color($val) {
    $v = strtoupper(trim($val ?? ''));
    return match($v) {
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14',
        default => '#444444'
    };
}

function get_pocket_text($val) {
    $v = strtoupper(trim($val ?? ''));
    return in_array($v, ['SANGO', 'YENN']) ? '#111' : '#fff';
}

$where = "WHERE 1=1";
if (!empty($f_owner))  $where .= " AND D.owner_email = '"        . mysqli_real_escape_string($link, $f_owner)  . "'";
if (!empty($f_pilot))  $where .= " AND D.pilot_name = '"         . mysqli_real_escape_string($link, $f_pilot)  . "'";
if (!empty($f_target)) $where .= " AND D.target_description = '" . mysqli_real_escape_string($link, $f_target) . "'";
if (!empty($f_pocket)) $where .= " AND P.pocket6 = '"            . mysqli_real_escape_string($link, $f_pocket) . "'";

// CAMBIO 1 — Se agrega P.toon_number al SELECT
$sql_main = "SELECT D.*, P.pocket6, P.tradefield, P.toon_number
             FROM DIPLOMATIC D
             LEFT JOIN PILOTS P ON D.pilot_name = P.toon_name
             $where
             ORDER BY D.reputation DESC, D.id DESC";

$res_main   = mysqli_query($link, $sql_main);
$total_rows = mysqli_num_rows($res_main);

echo ui_header("Control Diplomatico - EVE Online");
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Online - Diplomatic Control</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #0d0f11;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 70px;
            padding-bottom: 70px;
        }
        .filter-section {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .filter-section label {
            color: #adb5bd;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .filter-section .form-control,
        .filter-section .form-control:focus {
            background-color: #1e2126;
            border-color: #495057;
            color: #e0e0e0;
            box-shadow: none;
        }
        .total-badge {
            background-color: #0d0f11;
            border: 1px solid #007bff;
            color: #007bff;
            font-size: 0.8rem;
            padding: 3px 10px;
            border-radius: 3px;
        }
        .table-eve {
            background-color: #1a1d21;
            color: #ced4da;
            font-size: 0.85rem;
        }
        .table-eve thead th {
            background-color: #0d0f11;
            color: #adb5bd;
            border-color: #343a40;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .table-eve tbody tr:nth-child(odd)  { background-color: #1e2126; }
        .table-eve tbody tr:nth-child(even) { background-color: #1a1d21; }
        .table-eve tbody tr:hover           { background-color: #2a3040 !important; color: #fff; }
        .table-eve td { border-color: #2c3035; vertical-align: middle; }
        .reputation-num { font-family: 'Consolas', monospace; font-weight: bold; font-size: 0.95rem; }
        .rep-pos { color: #28a745; }
        .rep-neg { color: #dc3545; }
        .rep-neu { color: #adb5bd; }
        .pocket-badge {
            display: inline-block;
            padding: 2px 10px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 2px;
            min-width: 70px;
            text-align: center;
        }
        .trade-pill {
            background-color: #2d3748;
            color: #f39c12;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .row-num { color: #6c757d; font-size: 0.78rem; }

        /* CAMBIO 2 — Estilos para geticons() */
        .industry-icons {
            display: inline-flex;
            gap: 9px;
            align-items: center;
            font-size: 1.05rem;
        }
        .industry-icons i { cursor: default; }
        .industry-icons .icon-action { cursor: pointer; transition: color 0.15s; }
        .industry-icons .icon-action:hover { color: #fff !important; }
    </style>
</head>
<body>

<div class="filter-section">
    <div class="container-fluid">
        <form action="<?php echo $self; ?>" method="POST" class="form-row align-items-end">

            <div class="form-group col-6 col-md-2 mb-2">
                <label><i class="fas fa-user-tie mr-1"></i>Owner</label>
                <select name="owner_email" class="form-control form-control-sm">
                    <option value="">-- Todos --</option>
                    <?php while ($r = mysqli_fetch_assoc($res_owners)): ?>
                        <option value="<?php echo htmlspecialchars($r['owner_email']); ?>"
                            <?php echo ($f_owner == $r['owner_email']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['owner_email']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group col-6 col-md-2 mb-2">
                <label><i class="fas fa-user mr-1"></i>Piloto</label>
                <select name="pilot_name" class="form-control form-control-sm">
                    <option value="">-- Todos --</option>
                    <?php while ($r = mysqli_fetch_assoc($res_pilots_list)): ?>
                        <option value="<?php echo htmlspecialchars($r['toon_name']); ?>"
                            <?php echo ($f_pilot == $r['toon_name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['toon_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group col-6 col-md-3 mb-2">
                <label><i class="fas fa-building mr-1"></i>Corp Target</label>
                <select name="target_description" class="form-control form-control-sm">
                    <option value="">-- Todas --</option>
                    <?php while ($r = mysqli_fetch_assoc($res_corps)): ?>
                        <option value="<?php echo htmlspecialchars($r['target_description']); ?>"
                            <?php echo ($f_target == $r['target_description']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['target_description']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group col-6 col-md-2 mb-2">
                <label><i class="fas fa-folder mr-1"></i>Pocket</label>
                <select name="pocket6" class="form-control form-control-sm">
                    <option value="">-- Todos --</option>
                    <?php while ($r = mysqli_fetch_assoc($res_pockets)): ?>
                        <option value="<?php echo htmlspecialchars($r['pocket6']); ?>"
                            <?php echo ($f_pocket == $r['pocket6']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['pocket6']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group col-12 col-md-3 mb-2 d-flex align-items-end" style="gap:8px;">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="<?php echo $self; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-sync-alt mr-1"></i> Limpiar
                </a>
                <span class="total-badge ml-2">
                    <i class="fas fa-list mr-1"></i><?php echo $total_rows; ?> registros
                </span>
            </div>

        </form>
    </div>
</div>

<div class="container-fluid">
    <div class="table-responsive rounded shadow">
        <table class="table table-sm table-eve mb-0">
            <thead>
                <tr>
                    <th width="40" class="text-center">#</th>
                    <th>Piloto</th>
                    <th>Tradefield</th>
                    <th>Corp Target</th>
                    <th class="text-right">Reputacion</th>
                    <th class="text-center">Pocket</th>
                    <!-- CAMBIO 3 — Nueva columna Actions -->
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $cnt = 1;
                if ($total_rows > 0):
                    while ($row = mysqli_fetch_assoc($res_main)):
                        $rep        = (float)$row['reputation'];
                        $rep_class  = ($rep > 0) ? 'rep-pos' : (($rep < 0) ? 'rep-neg' : 'rep-neu');
                        $p6_val     = $row['pocket6'] ?? 'N/A';
                        $p6_color   = get_pocket_color($p6_val);
                        $p6_text    = get_pocket_text($p6_val);
                        $tradefield = $row['tradefield'] ?? '';
                ?>
                <tr>
                    <td class="text-center row-num"><?php echo $cnt++; ?></td>
                    <td><strong class="text-white"><?php echo htmlspecialchars($row['pilot_name']); ?></strong></td>
                    <td>
                        <?php if (!empty($tradefield) && $tradefield !== 'n/a'): ?>
                            <span class="trade-pill"><?php echo htmlspecialchars($tradefield); ?></span>
                        <?php else: ?>
                            <small class="text-muted">-</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['target_description']); ?></td>
                    <td class="text-right reputation-num <?php echo $rep_class; ?>">
                        <?php echo number_format($rep, 2); ?>
                    </td>
                    <td class="text-center">
                        <span class="pocket-badge"
                              style="background-color:<?php echo $p6_color; ?>; color:<?php echo $p6_text; ?>;">
                            <?php echo htmlspecialchars($p6_val); ?>
                        </span>
                    </td>
                    <!-- CAMBIO 3 — Iconos via geticons() -->
                    <td class="text-center">
                        <?php echo geticons($row['toon_number']); ?>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fas fa-satellite mr-2"></i>Sin datos disponibles.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php echo ui_footer(); ?>
