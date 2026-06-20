<?php
// License GPL
// Alfonso Orozco Aguilar
// Control in the database the  accounts of Eve Online. Experimental, can be erased in any moment
require_once('..//config.php');
check_authorization();

/** --- CONTROLLER --- **/
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = mysqli_real_escape_string($link, $_GET['id']);
    $action = $_GET['action'];
    $self = $_SERVER['PHP_SELF'];

    switch ($action) {
        case 'toggle_yellow':
            mysqli_query($link, "UPDATE PANELS SET yellowflag = 1 - yellowflag WHERE idpanel = '$id'");
            break;
        case 'toggle_group':
            mysqli_query($link, "UPDATE PANELS SET groupflag = 1 - groupflag WHERE idpanel = '$id'");
            break;
        case 'set_refresh':
            $status = ($_GET['status'] == 'YES') ? 'YES' : 'NO';
            mysqli_query($link, "UPDATE PANELS SET refresh = '$status' WHERE idpanel = '$id'");
            break;
    }
    header("Location: $self");
    exit;
}

/** --- DATA --- **/
// 1. Active Panels
$res_activos = mysqli_query($link, "SELECT *, DATEDIFF(manualExpiration, CURDATE()) as dias FROM PANELS WHERE refresh = 'YES' ORDER BY manualExpiration DESC");

// 2. Hidden Panels
$res_ocultos = mysqli_query($link, "SELECT idpanel, pseudo FROM PANELS WHERE refresh = 'NO'");

// 3. Audit with Jita Value (Forge Value) Recalculation and Totals
// We use a LEFT JOIN to get the sum of assets for each pilot in real time
$sql_pilots = "SELECT 
                P.toon_name, 
                P.pocket6, 
                P.numitems, 
                P.wallet,
				P.supergroup,
                COALESCE(A.total_forge, 0) as real_jitav
               FROM PILOTS P
               LEFT JOIN (
                   SELECT toon_number, SUM(forge_value) as total_forge 
                   FROM EVE_ASSETS 
                   GROUP BY toon_number
               ) A ON P.toon_number = A.toon_number
               WHERE (COALESCE(A.total_forge, 0) >= 0.5 OR P.numitems > 5 OR P.numships > 1 OR P.wallet > 50000)
               AND (P.supergroup=1)
			   -- AND (P.toon_name not like '%Catalog%')
               AND P.toon_number NOT IN (
                   SELECT pilot_1 FROM PANELS WHERE yellowflag = 1
                   UNION SELECT pilot_2 FROM PANELS WHERE yellowflag = 1
                   UNION SELECT pilot_3 FROM PANELS WHERE yellowflag = 1
               )";

$res_pilots = mysqli_query($link, $sql_pilots);

// Variables for the audit grand total
$total_jitav_global = 0;
$total_items_global = 0;
$total_wallet_global = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EVE Logistics v2.5 - Forge Update</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap4.min.css">
    <style>
        body { background: #0b0b0b; color: #dcdcdc; font-size: 0.85rem; }
        .card { background: #1a1a1a; border: 1px solid #333; margin-bottom: 20px; }
        .table-dark { background: #121212; border: none; }
        .tfoot-totals { background: #252525; font-weight: bold; color: #ffc107; }
        .text-gold { color: #ffc107; }
    </style>
</head>
<body class="p-4">

<div class="container-fluid">
    
    <?php if(mysqli_num_rows($res_ocultos) > 0): ?>
    <div class="card p-3 shadow-sm">
        <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Reactivate Hidden Panels</h6>
        <div class="d-flex flex-wrap">
            <?php while($p = mysqli_fetch_assoc($res_ocultos)): ?>
                <a href="?action=set_refresh&status=YES&id=<?php echo $p['idpanel']; ?>" 
                   class="badge badge-pill badge-secondary p-2 mr-2 mb-2">
                   <i class="fas fa-undo mr-1"></i> <?php echo htmlspecialchars($p['pseudo']); ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card p-3 shadow">
        <h6 class="text-uppercase small font-weight-bold mb-3 text-primary">Active Panels</h6>
        <div class="table-responsive">
            <table class="table table-dark table-hover table-sm">
                <thead class="thead-light text-dark">
                    <tr>
                        <th>#</th><th>Pseudo</th><th>P1</th><th>P2</th><th>P3</th><th>Expiration</th><th class="text-center">Flags</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($row = mysqli_fetch_assoc($res_activos)): 
                        $y_color = ($row['yellowflag'] == 1) ? 'text-warning' : 'text-secondary';
                        $g_color = ($row['groupflag'] == 1) ? 'text-danger' : 'text-secondary';
                    ?>
                    <tr>
                        <td class="text-muted"><?php echo $i++; ?></td>
                        <td><strong><?php echo htmlspecialchars($row['pseudo']); ?></strong> <span class="badge badge-<?php echo $row['panel_type']; ?>"><?php echo $row['panel_type']; ?></span></td>
                        <td><?php echo htmlspecialchars($row['name_1']); ?></td>
                        <td><?php echo htmlspecialchars($row['name_2']); ?></td>
                        <td><?php echo htmlspecialchars($row['name_3']); ?></td>
                        <td><?php echo $row['manualExpiration']; ?> <span class="badge badge-info"><?php echo $row['dias']; ?>d</span></td>
                        <td class="text-center">
                            <a href="?action=toggle_yellow&id=<?php echo $row['idpanel']; ?>"><i class="fas fa-flag <?php echo $y_color; ?> mx-2"></i></a>
                            <a href="?action=toggle_group&id=<?php echo $row['idpanel']; ?>"><i class="fas fa-flag <?php echo $g_color; ?> mx-2"></i></a>
                            <a href="?action=set_refresh&status=NO&id=<?php echo $row['idpanel']; ?>" class="text-info mx-2"><i class="fas fa-eye-slash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-3 shadow">
        <h6 class="text-uppercase small font-weight-bold mb-3 text-gold">Asset Audit (Real-Time Forge Recalculation)</h6>
        <table id="tablePilotos" class="table table-striped table-bordered table-dark table-sm w-100">
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Pocket</th><th>Items</th><th>Jita V (Forge)</th><th>Wallet (M)</th>
                </tr>
            </thead>
            <tbody>
                <?php $j=1; while($p = mysqli_fetch_assoc($res_pilots)): 
                    $total_jitav_global += $p['real_jitav'];
                    $total_items_global += $p['numitems'];
                    $total_wallet_global += $p['wallet'];
                ?>
                <tr>
                    <td><?php echo $j++; ?></td>
                    <td class="text-info font-weight-bold"><?php echo htmlspecialchars($p['toon_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['pocket6']); ?></td>
                    <td class="text-center"><?php echo $p['numitems']; ?></td>
                    <td class="text-right"><?php echo number_format($p['real_jitav']/1000000, 2); ?></td>
                    <td class="text-right text-success"><?php echo number_format($p['wallet'] / 1000000, 2); ?>M</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot class="tfoot-totals">
                <tr>
                    <td colspan="3" class="text-right">ACCUMULATED TOTALS:</td>
                    <td class="text-center"><?php echo number_format($total_items_global); ?></td>
                    <td class="text-right"><?php echo number_format($total_jitav_global/1000000, 2); ?></td>
                    <td class="text-right"><?php echo number_format($total_wallet_global / 1000000, 2); ?>M</td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap4.min.js"></script>

<script>
    $(document).ready(function() {
        $('#tablePilotos').DataTable({
            "pageLength": 25,
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/English.json" },
            "order": [[ 4, "desc" ]] // Sort by Jita V (Forge) by default
        });
    });
</script>

</body>
</html>
