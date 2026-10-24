<?php
// License GPL
// Alfonso Orozco Aguilar
// Control in the database the accounts of Eve Online. Experimental, can be erased in any moment
// Tab 1: Panels (flags / hide / reactivate)  |  Tab 2: PLEX and Comments (inline edit)
require_once('../config.php');
check_authorization();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function daysBadge($d) {
    if ($d === null) return 'secondary';
    $d = (int)$d;
    return $d < 0 ? 'danger' : ($d === 0 ? 'warning' : 'success');
}

/** --- STATE: filters of tab 2 survive every redirect --- **/
$self = basename($_SERVER['PHP_SELF']);
$keep = array_intersect_key($_GET, array_flip(['f_plex', 'f_notes']));
$qs   = http_build_query($keep);
$base = $self . ($qs !== '' ? "?$qs" : '');

function go($tab, $extra = []) {
    global $keep, $self;
    $q = http_build_query($extra + $keep);
    header("Location: $self" . ($q !== '' ? "?$q" : '') . "#$tab");
    exit;
}

function act($a, $id, $extra = '') {
    global $self, $qs;
    return e("$self?action=$a&id=$id$extra" . ($qs !== '' ? "&$qs" : ''));
}

/** --- CONTROLLER: tab 1 (flags / hide / reactivate) --- **/
if (isset($_GET['action'], $_GET['id'])) {
    $id    = (int)$_GET['id'];
    $flags = ['toggle_yellow' => 'yellowflag', 'toggle_group' => 'groupflag'];
    if (isset($flags[$_GET['action']])) {
        $col = $flags[$_GET['action']];
        $st  = mysqli_prepare($link, "UPDATE PANELS SET `$col` = 1 - `$col` WHERE idpanel = ?");
        mysqli_stmt_bind_param($st, 'i', $id);
        mysqli_stmt_execute($st);
    } elseif ($_GET['action'] === 'set_refresh') {
        $status = (($_GET['status'] ?? '') === 'YES') ? 'YES' : 'NO';
        $st = mysqli_prepare($link, "UPDATE PANELS SET refresh = ? WHERE idpanel = ?");
        mysqli_stmt_bind_param($st, 'si', $status, $id);
        mysqli_stmt_execute($st);
    }
    go('panels');
}

/** --- CONTROLLER: tab 2 (inline update by row) --- **/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_row') {
    $id    = (int)($_POST['id'] ?? 0);
    $plex  = max(0, (int)($_POST['plex'] ?? 0));
    $notes = trim($_POST['notes'] ?? '');
    $st = mysqli_prepare($link, "UPDATE PANELS SET plex = ?, notes = ? WHERE idpanel = ?");
    mysqli_stmt_bind_param($st, 'isi', $plex, $notes, $id);
    if (mysqli_stmt_execute($st)) go('plex', ['msg' => 'success']);
    go('plex');
}

/** --- DATA --- **/
$res_active = mysqli_query($link, "SELECT *, DATEDIFF(manualExpiration, CURDATE()) AS dias FROM PANELS WHERE refresh = 'YES' ORDER BY manualExpiration DESC");
$res_hidden = mysqli_query($link, "SELECT idpanel, pseudo FROM PANELS WHERE refresh = 'NO'");
$n_active = mysqli_num_rows($res_active);
$n_hidden = mysqli_num_rows($res_hidden);

$f_plex  = $_GET['f_plex']  ?? '';
$f_notes = $_GET['f_notes'] ?? '';
$where = [];
if ($f_plex === 'gt0') $where[] = "plex > 0";
if ($f_notes === 'with')         $where[] = "(notes IS NOT NULL AND notes != '')";
elseif ($f_notes === 'without')  $where[] = "(notes IS NULL OR notes = '')";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$res_plex = mysqli_query($link, "SELECT *, DATEDIFF(manualExpiration, CURDATE()) AS dias FROM PANELS $where_sql ORDER BY idpanel ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online - Accounts Control</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        body { background-color: #1a1d21; color: #e0e0e0; font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding-bottom: 60px; }
        .navbar-eve { background-color: #0b0c0e; border-bottom: 2px solid #495057; margin-bottom: 0; }

        /* Tabs */
        .nav-tabs { border-bottom: 2px solid #495057; }
        .nav-tabs .nav-link { color: #adb5bd; background: #16191c; border: 1px solid #343a40; border-bottom: 0; margin-right: 3px; }
        .nav-tabs .nav-link:hover { color: #fff; border-color: #495057; }
        .nav-tabs .nav-link.active { color: #fff; background: #1a1d21; border-top: 2px solid #007bff; }

        /* Boxes, filter bar, flash */
        .box { background: #16191c; border: 1px solid #343a40; border-radius: 4px; padding: 14px 18px; }
        .filter-bar { background-color: #16191c; border-bottom: 2px solid #007bff; padding: 12px 20px; margin-bottom: 20px; }
        .msg-flash { background-color: #1a2a1a; border-left: 4px solid #28a745; padding: 7px 16px; font-size: 0.82rem; color: #7ee07e; margin-bottom: 14px; }

        /* Inputs */
        .filter-bar .form-control, .dt-dark .form-control { background-color: #2a2d31; border-color: #495057; color: #e0e0e0; }
        .filter-bar .form-control { max-width: 180px; }
        .filter-bar .form-control:focus, .dt-dark .form-control:focus { background-color: #2a2d31; color: #fff; border-color: #007bff; box-shadow: none; }
        .input-inline { min-width: 80px; }

        /* Dark DataTables */
        .dataTables_wrapper .dataTables_length label, .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_paginate .paginate_button { color: #aaa !important; }
        .dataTables_wrapper .dataTables_filter input, .dataTables_wrapper .dataTables_length select {
            background-color: #2a2d31; border: 1px solid #495057; color: #e0e0e0; border-radius: 3px; padding: 2px 6px; }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: #007bff !important; color: #fff !important; border-color: #007bff !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0056b3 !important; color: #fff !important; border-color: #0056b3 !important; }

        /* Dark tables */
        .dt-dark { font-size: 0.82rem; }
        .dt-dark thead th { background-color: #0d0f11; color: #adb5bd; border-color: #343a40; white-space: nowrap; }
        .dt-dark tbody tr { background-color: #1e2126; }
        .dt-dark tbody tr:nth-child(odd) { background-color: #22262c; }
        .dt-dark tbody tr:hover { background-color: #2a3040 !important; }
        .dt-dark tbody td { color: #e0e0e0; }
        .dt-dark td, .dt-dark th { border-color: #343a40 !important; vertical-align: middle !important; }

        .flag-link i { transition: transform 0.3s ease; }
        .flag-link:hover i { transform: rotate(180deg); }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark navbar-eve px-3">
    <span class="navbar-brand mb-0 h5"><i class="fas fa-space-shuttle mr-2"></i>EVE Accounts Control</span>
    <span class="text-muted small"><i class="fas fa-layer-group mr-1"></i><?= $n_active ?> active &middot; <?= $n_hidden ?> hidden</span>
</nav>

<div class="container-fluid pt-3">

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#panels" role="tab"><i class="fas fa-layer-group mr-1"></i>Panels</a></li>
        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#plex" role="tab"><i class="fas fa-gem mr-1"></i>PLEX and Comments</a></li>
    </ul>

    <div class="tab-content pt-3">

        <!-- ═══ TAB 1: PANELS ═══ -->
        <div class="tab-pane fade show active" id="panels" role="tabpanel">

            <?php if ($n_hidden > 0): ?>
            <div class="box mb-3">
                <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Reactivate Hidden Panels</h6>
                <div class="d-flex flex-wrap">
                    <?php while ($p = mysqli_fetch_assoc($res_hidden)): ?>
                        <a href="<?= act('set_refresh', (int)$p['idpanel'], '&status=YES') ?>" class="badge badge-pill badge-secondary p-2 mr-2 mb-2">
                            <i class="fas fa-undo mr-1"></i><?= e($p['pseudo']) ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="box">
                <h6 class="text-uppercase small font-weight-bold mb-3 text-primary">Active Panels</h6>
                <div class="table-responsive">
                    <table id="tablaPanels" class="table table-sm table-bordered dt-dark" style="width:100%">
                        <thead>
                            <tr><th>#</th><th>Pseudo</th><th>P1</th><th>P2</th><th>P3</th><th>Expiration</th><th class="text-center">Flags</th></tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($row = mysqli_fetch_assoc($res_active)):
                                $id      = (int)$row['idpanel'];
                                $y_color = $row['yellowflag'] == 1 ? 'text-warning' : 'text-secondary';
                                $g_color = $row['groupflag']  == 1 ? 'text-danger'  : 'text-secondary';
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?= $i++ ?></td>
                                <td><strong class="text-white"><?= e($row['pseudo']) ?></strong>
                                    <span class="badge badge-<?= e($row['panel_type']) ?>"><?= e($row['panel_type']) ?></span></td>
                                <td><?= e($row['name_1']) ?></td>
                                <td><?= e($row['name_2']) ?></td>
                                <td><?= e($row['name_3']) ?></td>
                                <td><?= e($row['manualExpiration']) ?>
                                    <span class="badge badge-<?= daysBadge($row['dias']) ?>"><?= $row['dias'] === null ? '&mdash;' : (int)$row['dias'] . 'd' ?></span></td>
                                <td class="text-center text-nowrap">
                                    <a class="flag-link" href="<?= act('toggle_yellow', $id) ?>" title="Yellow flag"><i class="fas fa-flag <?= $y_color ?> mx-2"></i></a>
                                    <a class="flag-link" href="<?= act('toggle_group', $id) ?>" title="Group flag"><i class="fas fa-flag <?= $g_color ?> mx-2"></i></a>
                                    <a class="flag-link text-info" href="<?= act('set_refresh', $id, '&status=NO') ?>" title="Hide panel"><i class="fas fa-eye-slash mx-2"></i></a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══ TAB 2: PLEX AND COMMENTS ═══ -->
        <div class="tab-pane fade" id="plex" role="tabpanel">

            <?php if (($_GET['msg'] ?? '') === 'success'): ?>
                <div class="msg-flash" id="alert-msg"><i class="fas fa-check-circle mr-1"></i>Record updated successfully.</div>
            <?php endif; ?>

            <div class="filter-bar">
                <form method="GET" action="<?= e($self) ?>#plex" class="form-inline flex-wrap" style="gap:10px;">
                    <label class="text-light mr-1"><i class="fas fa-gem mr-1"></i>PLEX:</label>
                    <select name="f_plex" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                        <option value="">-- All --</option>
                        <option value="gt0" <?= $f_plex === 'gt0' ? 'selected' : '' ?>>Greater than 0</option>
                    </select>

                    <label class="text-light mr-1"><i class="fas fa-comment mr-1"></i>Notes:</label>
                    <select name="f_notes" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                        <option value="">-- All --</option>
                        <option value="with"    <?= $f_notes === 'with'    ? 'selected' : '' ?>>With comment</option>
                        <option value="without" <?= $f_notes === 'without' ? 'selected' : '' ?>>Without comment</option>
                    </select>

                    <?php if ($f_plex !== '' || $f_notes !== ''): ?>
                        <a href="<?= e($self) ?>#plex" class="btn btn-sm btn-outline-danger"><i class="fas fa-times mr-1"></i>Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table id="tablaPlex" class="table table-sm table-bordered dt-dark" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th><th>Pseudo</th><th>Type</th><th>Names</th>
                            <th><i class="fas fa-gem"></i> PLEX</th><th>Days Left</th><th>Expiration</th><th>Notes</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($res_plex)):
                            $id  = (int)$row['idpanel'];
                            $fid = "f$id";
                        ?>
                        <tr>
                            <td class="text-center text-muted font-weight-bold">
                                <form id="<?= $fid ?>" method="POST" action="<?= e($base) ?>#plex">
                                    <input type="hidden" name="action" value="update_row">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                </form><?= $id ?>
                            </td>
                            <td><strong class="text-white"><?= e($row['pseudo']) ?></strong></td>
                            <td class="text-center"><?= e($row['panel_type']) ?></td>
                            <td class="small">1: <?= e($row['name_1']) ?><br>2: <?= e($row['name_2']) ?><br>3: <?= e($row['name_3']) ?></td>
                            <td data-order="<?= (int)$row['plex'] ?>">
                                <input type="number" name="plex" form="<?= $fid ?>" class="form-control form-control-sm input-inline" value="<?= (int)$row['plex'] ?>" required min="0">
                            </td>
                            <td class="text-center" data-order="<?= $row['dias'] === null ? -99999 : (int)$row['dias'] ?>">
                                <span class="badge badge-<?= daysBadge($row['dias']) ?>"><?= $row['dias'] === null ? '&mdash;' : (int)$row['dias'] ?></span>
                            </td>
                            <td class="text-center"><?= e($row['manualExpiration']) ?></td>
                            <td><input type="text" name="notes" form="<?= $fid ?>" class="form-control form-control-sm" value="<?= e($row['notes']) ?>"></td>
                            <td class="text-center">
                                <button type="submit" form="<?= $fid ?>" class="btn btn-sm btn-primary btn-block font-weight-bold"><i class="fas fa-save mr-1"></i>Save</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function () {
    var paginate = { first: "«", last: "»", next: "›", previous: "‹" };

    $('#tablaPanels').DataTable({
        pageLength: 100,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        ordering: false, // server-side order already applied (expiration DESC)
        language: { search: "Search:", lengthMenu: "Show _MENU_ panels", info: "Showing _START_ to _END_ of _TOTAL_ panels",
                    infoEmpty: "No results", infoFiltered: "(filtered from _MAX_ total)", zeroRecords: "No panels found", paginate: paginate }
    });

    $('#tablaPlex').DataTable({
        pageLength: 50,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[0, "asc"]],
        columnDefs: [{ orderable: false, targets: [3, 7, 8] }],
        language: { search: "Search:", lengthMenu: "Show _MENU_ records per page", info: "Showing page _PAGE_ of _PAGES_ (Total: _TOTAL_ accounts)",
                    infoEmpty: "No records available", infoFiltered: "(filtered from _MAX_ total records)", zeroRecords: "No results found", paginate: paginate }
    });

    // Open the tab named in the URL hash (survives redirects and reloads)
    var h = location.hash;
    $('.nav-tabs a').filter(function () { return this.hash === h; }).tab('show');

    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.replaceState(null, '', e.target.hash);
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });

    setTimeout(function () { $('#alert-msg').fadeOut('slow'); }, 3000);
});
</script>
</body>
</html>
