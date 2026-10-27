<?php
/*
 * EVE Online - CREW Reputation (NPC diplomatic control)
 * License: GPL
 * Author: Alfonso Orozco Aguilar
 * Standalone version of the "Reputation" tab from the Unified CREW Audit Tool.
 * Same look & tab structure, so more tabs can be added later.
 * Stack: PHP 8.x Procedural, MariaDB, Bootstrap 4.6.2, Font Awesome 5.15.4
 */

// ── HEADERS & INIT ─────────────────────────────────────────────────────────────
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require "../config.php";
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

mysqli_set_charset($link, "utf8mb4");

$self       = basename(__FILE__);
$active_tab = $_GET['tab'] ?? 'reputation';

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════
function get_pocket_color($val) {
    return match(strtoupper(trim($val ?? ''))) {
        'EXPER' => '#28a745', 'CLEAN' => '#0078d7', 'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1', 'NOKIA' => '#e81123', 'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14', default => '#444444'
    };
}
function get_pocket_text($val) {
    return in_array(strtoupper(trim($val ?? '')), ['SANGO','YENN']) ? '#111' : '#fff';
}

// ══════════════════════════════════════════════════════════════════════════════
// TAB — REPUTATION: diplomatic control
// ══════════════════════════════════════════════════════════════════════════════
function renderTabReputation() {
    global $link, $self;
    $f_owner  = $_POST['owner_email']        ?? '';
    $f_pilot  = $_POST['pilot_name']         ?? '';
    $f_target = $_POST['target_description'] ?? '';
    $f_pocket = $_POST['pocket6_dip']        ?? '';

    $res_owners      = mysqli_query($link, "SELECT DISTINCT owner_email FROM DIPLOMATIC WHERE owner_email != '' ORDER BY owner_email ASC");
    $res_pilots_list = mysqli_query($link, "SELECT DISTINCT toon_name FROM PILOTS ORDER BY toon_name ASC");
    $res_corps       = mysqli_query($link, "SELECT DISTINCT target_description FROM DIPLOMATIC WHERE target_description != '' ORDER BY target_description ASC");
    $res_pockets     = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 != '' ORDER BY pocket6 ASC");

    $where = "WHERE 1=1";
    if (!empty($f_owner))  $where .= " AND D.owner_email = '"        . mysqli_real_escape_string($link, $f_owner)  . "'";
    if (!empty($f_pilot))  $where .= " AND D.pilot_name = '"         . mysqli_real_escape_string($link, $f_pilot)  . "'";
    if (!empty($f_target)) $where .= " AND D.target_description = '" . mysqli_real_escape_string($link, $f_target) . "'";
    if (!empty($f_pocket)) $where .= " AND P.pocket6 = '"            . mysqli_real_escape_string($link, $f_pocket) . "'";

    $sql_main   = "SELECT D.*, P.pocket6, P.tradefield
                   FROM DIPLOMATIC D
                   LEFT JOIN PILOTS P ON D.pilot_name = P.toon_name
                   $where
                   ORDER BY D.reputation DESC, D.id DESC";
    $res_main   = mysqli_query($link, $sql_main);
    $total_rows = mysqli_num_rows($res_main);
    ob_start();
    ?>
    <!-- Filter bar -->
    <form action="<?php echo $self; ?>?tab=reputation" method="POST" class="form-row align-items-end mb-4 p-3 rounded" style="background:#16191c;border-bottom:2px solid #007bff;">
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-user-tie mr-1"></i>Owner</label>
            <select name="owner_email" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_owners)): ?>
                    <option value="<?php echo htmlspecialchars($r['owner_email']); ?>" <?php echo ($f_owner == $r['owner_email']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['owner_email']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-user mr-1"></i>Pilot</label>
            <select name="pilot_name" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_pilots_list)): ?>
                    <option value="<?php echo htmlspecialchars($r['toon_name']); ?>" <?php echo ($f_pilot == $r['toon_name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['toon_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-3 mb-2">
            <label class="filter-label"><i class="fas fa-building mr-1"></i>Corp Target</label>
            <select name="target_description" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_corps)): ?>
                    <option value="<?php echo htmlspecialchars($r['target_description']); ?>" <?php echo ($f_target == $r['target_description']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['target_description']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-6 col-md-2 mb-2">
            <label class="filter-label"><i class="fas fa-folder mr-1"></i>Pocket</label>
            <select name="pocket6_dip" class="form-control form-control-sm eve-select">
                <option value="">-- All --</option>
                <?php while ($r = mysqli_fetch_assoc($res_pockets)): ?>
                    <option value="<?php echo htmlspecialchars($r['pocket6']); ?>" <?php echo ($f_pocket == $r['pocket6']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($r['pocket6']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group col-12 col-md-3 mb-2 d-flex align-items-end" style="gap:8px;">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i> Filter</button>
            <a href="<?php echo $self; ?>?tab=reputation" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sync-alt mr-1"></i> Clear</a>
            <span class="total-badge ml-2"><i class="fas fa-list mr-1"></i><?php echo $total_rows; ?> records</span>
        </div>
    </form>

    <div class="table-responsive rounded shadow">
        <table id="tbl-reputation" class="table table-sm table-eve mb-0">
            <thead>
                <tr>
                    <th width="40" class="text-center">#</th>
                    <th>Pilot</th>
                    <th>Trade Field</th>
                    <th>Corp Target</th>
                    <th class="text-right">Reputation</th>
                    <th class="text-center">Pocket</th>
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
                    $tradefield = $row['tradefield'] ?? '';
            ?>
            <tr>
                <td class="text-center row-num"><?php echo $cnt++; ?></td>
                <td><strong class="text-white"><?php echo htmlspecialchars($row['pilot_name']); ?></strong></td>
                <td>
                    <?php if (!empty($tradefield) && $tradefield !== 'n/a'): ?>
                        <span class="trade-tag"><?php echo htmlspecialchars($tradefield); ?></span>
                    <?php else: ?><small class="text-muted">-</small><?php endif; ?>
                </td>
                <td style="color:#ffffff;"><?php echo htmlspecialchars($row['target_description']); ?></td>
                <td class="text-right reputation-num <?php echo $rep_class; ?>"><?php echo number_format($rep, 2); ?></td>
                <td class="text-center">
                    <span class="pocket-badge-dip" style="background-color:<?php echo get_pocket_color($p6_val); ?>;color:<?php echo get_pocket_text($p6_val); ?>;">
                        <?php echo htmlspecialchars($p6_val); ?>
                    </span>
                </td>
            </tr>
            <?php endwhile;
            else: ?>
            <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-satellite mr-2"></i>No data available.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ══════════════════════════════════════════════════════════════════════════════
echo ui_header("EVE Online - CREW Reputation");
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online - CREW Reputation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        /* ── BASE ── */
        body {
            background-color: #0b0c0e;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 40px;
            padding-bottom: 70px;
        }

        /* ── TABS ── */
        .eve-tabs {
            background-color: #0d0f11;
            border-bottom: 2px solid #007bff;
            padding: 0 20px;
            position: sticky;
            top: 120;
            z-index: 100;
        }
        .eve-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            padding: 14px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: color 0.2s, border-color 0.2s;
        }
        .eve-tabs .nav-link:hover { color: #adb5bd; text-decoration: none; }
        .eve-tabs .nav-link.active {
            color: #ffffff;
            border-bottom: 3px solid #007bff;
            background: transparent;
        }
        .eve-tabs .nav-link i { margin-right: 7px; }

        /* ── SHARED TABLE ── */
        .table-eve { background-color: #16191c; font-size: 0.82rem; border-collapse: separate; border-spacing: 0; }
        .table-eve thead th { background-color: #212529; border-bottom: 2px solid #007bff; position: sticky; top: 0px; z-index: 10; color: #adb5bd !important; }
        .table-eve td { vertical-align: middle; border-top: 1px solid #2d3238; }

        /* ── DATATABLES DARK OVERRIDES ── */
        .dataTables_wrapper { color: #ced4da; }
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            background-color: #1e2126; border: 1px solid #495057; color: #e0e0e0;
            border-radius: 3px; padding: 2px 6px;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate { color: #adb5bd; margin-bottom: 8px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #adb5bd !important; border: 1px solid #343a40 !important;
            background: #16191c !important; border-radius: 2px !important; margin: 0 2px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2a3040 !important; color: #fff !important; border-color: #007bff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #007bff !important; color: #fff !important; border-color: #007bff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover { opacity: 0.4; }

        /* ── TRADE FIELD (purple, same as Graduation tab) ── */
        .trade-tag { color: #bb86fc; font-weight: 500; letter-spacing: 0.5px; }

        /* ── REPUTATION ── */
        .reputation-num { font-family: 'Consolas', monospace; font-weight: bold; font-size: 0.95rem; }
        .rep-pos { color: #28a745; } .rep-neg { color: #dc3545; } .rep-neu { color: #adb5bd; }
        .pocket-badge-dip { display: inline-block; padding: 2px 10px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; border-radius: 2px; min-width: 70px; text-align: center; }
        .row-num { color: #6c757d; font-size: 0.78rem; }
        .total-badge { background-color: #0d0f11; border: 1px solid #007bff; color: #007bff; font-size: 0.8rem; padding: 3px 10px; border-radius: 3px; }

        /* ── FILTER ── */
        .filter-label { color: #adb5bd; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block; }
        .eve-select, .eve-select:focus { background-color: #1e2126; border-color: #495057; color: #e0e0e0; box-shadow: none; }

        /* ── TAB CONTENT ── */
        .tab-content-eve { padding: 25px 20px; }
    </style>
</head>
<body>

<!-- ── TABS NAV (add more tabs here later) ── -->
<div class="eve-tabs">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab == 'reputation') ? 'active' : ''; ?>" href="?tab=reputation">
                <i class="fas fa-passport"></i> Reputation
            </a>
        </li>
    </ul>
</div>

<!-- ── TAB CONTENT ── -->
<div class="tab-content-eve">
    <?php
    switch ($active_tab) {
        case 'reputation':
        default:
            echo renderTabReputation();
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#tbl-reputation').length) {
        $('#tbl-reputation').DataTable({
            pageLength: 50,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
            order: [],
            language: { search: "Search:", lengthMenu: "Show _MENU_ records" }
        });
    }
});
</script>
</body>
</html>
