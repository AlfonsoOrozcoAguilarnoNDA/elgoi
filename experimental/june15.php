<?php
// 1. Connection and Data Control
/*
Temporary account control mechanism by expansion
GPL License
Joint experiment Gemini, corrected by kimi afterwards

https://vibecodingmexico.com/gemini-como-wikipedia/
*/
// 1. Connection and Data Control
require "../config.php";

// Quick procedural sanitization
function safe_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 2. CRUD Processing (Inline Update by Row)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_row') {
    $id = (int)$_POST['id'];
    $plex = (int)$_POST['plex'];
    $notes = mysqli_real_escape_string($link, safe_input($_POST['notes']));

    $update_query = "UPDATE `PANELS` SET 
                        `plex` = $plex, 
                        `notes` = '$notes' 
                    WHERE `idpanel` = $id";

    if (mysqli_query($link, $update_query)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
        exit;
    }
}

// 3. Filter Persistence in Query
$filter_plex     = isset($_GET['f_plex']) ? $_GET['f_plex'] : '';
$filter_redimibles = isset($_GET['f_redimibles']) ? $_GET['f_redimibles'] : '';

$where_clauses = [];
if ($filter_plex === 'mayor_cero') {
    $where_clauses[] = "`plex` > 0";
}
if ($filter_redimibles === 'con_comentario') {
    $where_clauses[] = "(`notes` IS NOT NULL AND `notes` != '')";
} elseif ($filter_redimibles === 'sin_comentario') {
    $where_clauses[] = "(`notes` IS NULL OR `notes` = '')";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Data query sorted by stepped ID
$query = "SELECT * FROM `PANELS` $where_sql ORDER BY `idpanel` ASC";
$result = mysqli_query($link, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panels Audit - Internal Control</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.7/css/dataTables.bootstrap4.min.css">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .table-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .input-inline { min-width: 70px; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 text-secondary font-weight-bold">⚡ Panels Control System (Continuous Audit)</h2>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success py-1 px-3 mb-0" id="alert-msg">Record updated successfully.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4 table-card">
        <div class="card-body py-3">
            <form method="GET" action="" class="form-inline">
                <label class="mr-2 font-weight-bold" for="f_plex">Filter PLEX:</label>
                <select name="f_plex" id="f_plex" class="form-control form-control-sm mr-4" onchange="this.form.submit()">
                    <option value="">-- All --</option>
                    <option value="mayor_cero" <?php echo ($filter_plex === 'mayor_cero') ? 'selected' : ''; ?>>Greater than 0</option>
                </select>

                <label class="mr-2 font-weight-bold" for="f_redimibles">Notes:</label>
                <select name="f_redimibles" id="f_redimibles" class="form-control form-control-sm mr-4" onchange="this.form.submit()">
                    <option value="">-- All --</option>
                    <option value="con_comentario" <?php echo ($filter_redimibles === 'con_comentario') ? 'selected' : ''; ?>>With comment</option>
                    <option value="sin_comentario" <?php echo ($filter_redimibles === 'sin_comentario') ? 'selected' : ''; ?>>Without comment</option>
                </select>

                <?php if ($filter_plex !== '' || $filter_redimibles !== ''): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-outline-danger">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="p-3 table-card">
        <table id="tablaFlota" class="table table-striped table-bordered table-hover table-sm w-100">
            <thead class="thead-dark">
                <tr>
                    <th width="5%">ID</th>
                    <th width="10%">Pseudo</th>
                    <th width="8%">Type</th>
                    <th width="8%">Pilots</th>
                    <th width="8%">PLEX</th>
                    <th width="8%">Refresh</th>
                    <th width="8%">Expiration</th>
                    <th width="30%">Notes</th>
                    <th width="15%">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_row">
                            <input type="hidden" name="id" value="<?php echo $row['idpanel']; ?>">

                            <td class="align-middle text-center font-weight-bold text-muted"><?php echo $row['idpanel']; ?></td>
                            <td class="align-middle"><strong><?php echo htmlspecialchars($row['pseudo']); ?></strong></td>
                            <td class="align-middle text-center"><?php echo htmlspecialchars($row['panel_type']); ?></td>
                            <td class="align-middle small">
                                1: <?php echo htmlspecialchars($row['pilot_1']); ?><br>
                                2: <?php echo htmlspecialchars($row['pilot_2']); ?><br>
                                3: <?php echo htmlspecialchars($row['pilot_3']); ?>
                            </td>
                            <td class="align-middle">
                                <input type="number" name="plex" class="form-control form-control-sm input-inline" value="<?php echo $row['plex']; ?>" required min="0">
                            </td>
                            <td class="align-middle text-center"><?php echo htmlspecialchars($row['refresh']); ?></td>
                            <td class="align-middle text-center"><?php echo htmlspecialchars($row['manualExpiration']); ?></td>
                            <td class="align-middle">
                                <input type="text" name="notes" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row['notes']); ?>">
                            </td>

                            <td class="align-middle text-center">
                                <button type="submit" class="btn btn-sm btn-primary btn-block font-weight-bold">Save</button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.7/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaFlota').DataTable({
        "pageLength": 50,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        "ordering": true,
        "order": [[0, "asc"]],
        "language": {
            "lengthMenu": "Show _MENU_ records per page",
            "zeroRecords": "No results found",
            "info": "Showing page _PAGE_ of _PAGES_ (Total: _TOTAL_ accounts)",
            "infoEmpty": "No records available",
            "infoFiltered": "(filtered from _MAX_ total records)",
            "search": "Search:",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        }
    });

    setTimeout(function() {
        $('#alert-msg').fadeOut('slow');
    }, 3000);
});
</script>
</body>
</html>
