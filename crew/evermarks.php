<?php
/*
LICENSE MIT
Alfonso Orozco Aguilar
*/
/*
 * SISTEMA DE AUDITORÍA DE FLOTA Y EVERMARKS - EVE ONLINE
 * Versión: 2026-03 (Edición Soberana)
 * Stack: PHP 8.x Procedimental, MariaDB, Bootstrap 4.6.2, Font Awesome 5.15.4
*/

require "../config.php";
$conn=$link;
mysqli_set_charset($conn, "utf8mb4");

$mensaje_exito = "";

// ==============================================================================
// LÓGICA DE ACTUALIZACIÓN (POST)
// ==============================================================================
if (isset($_POST['update_evermarks'])) {
    $target_toon = mysqli_real_escape_string($conn, $_POST['toon_name']);
    $new_val = (int)$_POST['evermarks_val'];
    
    $sqlUpd = "UPDATE PILOTS 
               SET evermarks = $new_val, 
                   lastdateevermark = NOW() 
               WHERE toon_name = '$target_toon'";
    
    if (mysqli_query($conn, $sqlUpd)) {
        $mensaje_exito = "Se actualizó el total de evermarks del piloto: <strong>" . htmlspecialchars($target_toon) . "</strong>";
    }
}

// ==============================================================================
// CONSULTA Y FILTROS
// ==============================================================================
$filterCorp = $_POST['filter_corp'] ?? 'ALL';
$filterEver = $_POST['filter_ever'] ?? '500';

$where = ["1=1"];
if ($filterCorp !== 'ALL') { 
    $where[] = "P.corporation_name = '" . mysqli_real_escape_string($conn, $filterCorp) . "'"; 
}
if ($filterEver === '500') { 
    $where[] = "P.evermarks > 500"; 
}
$whereClause = "WHERE " . implode(" AND ", $where);

$resList = mysqli_query($conn, "SELECT DISTINCT corporation_name FROM PILOTS WHERE corporation_name IS NOT NULL ORDER BY corporation_name ASC");

$sqlPilots = "SELECT P.*, 
              ((P.skillpoints + IFNULL(P.unalloc, 0)) / 1000000) as TotalSP_M,
              (IFNULL(P.wallet, 0) / 1000000) as Wallet_M
              FROM PILOTS P 
              $whereClause 
              ORDER BY P.evermarks DESC, TotalSP_M DESC";
$resPilots = mysqli_query($conn, $sqlPilots);

// Estética de AccType
function getAccTypeStyle($type) {
    $type = strtolower($type);
    if ($type == 'omega') return ['icon' => 'fa-crown',           'color' => '#f1c40f', 'label' => 'OMEGA'];
    if ($type == 'alpha') return ['icon' => 'fa-rocket',          'color' => '#95a5a6', 'label' => 'ALPHA'];
    return                       ['icon' => 'fa-question-circle', 'color' => '#6c757d', 'label' => 'N/A'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Online - Auditoría Pro</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #0d0f11; color: #ced4da; font-family: 'Segoe UI', sans-serif; }

        /* ── FILTER BAR ── */
        .filter-section {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 15px;
            margin-bottom: 25px;
        }

        /* ── MINI TOOLBAR ── */
        .mini-toolbar {
            display: inline-flex;
            gap: 3px;
            margin-left: 14px;
            vertical-align: middle;
        }
        .btn-tool {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 9px;
            font-size: 0.76rem;
            font-weight: 600;
            border-radius: 3px;
            border: 1px solid #555;
            cursor: pointer;
            text-decoration: none;
            transition: opacity 0.15s, transform 0.1s;
            line-height: 1.5;
            white-space: nowrap;
        }
        .btn-tool:hover { opacity: 0.72; transform: translateY(-1px); text-decoration: none; }
        .btn-tool-dark  { background-color: #1c1c1c; color: #bbb; border-color: #444; }
        .btn-tool-white { background-color: #f0f0f0; color: #111; border-color: #ccc; }

        /* ── CARDS ── */
        .card-eve {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .card-eve:hover { border-color: #007bff; box-shadow: 0 0 12px rgba(0,123,255,0.2); }

        .acctype-corner {
            position: absolute;
            top: 10px;
            right: 12px;
            font-size: 1.15rem;
            line-height: 1;
        }

        .portrait { width: 100px; height: 100px; border: 1px solid #444; }

        .corp-tag { color: #5dade2; font-size: 0.8rem; }

        /* NUEVO — Tradefield en morado */
        .trade-tag { color: #bb86fc; font-size: 0.78rem; font-weight: 500; letter-spacing: 0.5px; }

        .badge-em { font-size: 0.9rem; padding: 6px 12px; font-weight: bold; border-radius: 2px; display: inline-block; }
        .bg-hoy       { background-color: #28a745; color: white; }
        .bg-pendiente { background-color: #dc3545; color: white; }

        .data-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; }
        .val-sp { color: #5dade2; font-weight: bold; }

        .industry-icons {
            display: flex;
            gap: 11px;
            align-items: center;
            font-size: 1.05rem;
            margin-top: 10px;
        }
        .industry-icons i { cursor: default; }
        .industry-icons .icon-action { cursor: pointer; transition: color 0.15s; }
        .industry-icons .icon-action:hover { color: #fff !important; }

        .pocket-badge {
            background-color: #007bff;
            color: white;
            padding: 2px 10px;
            font-weight: bold;
            font-size: 0.78rem;
            text-transform: uppercase;
            border-radius: 2px;
            white-space: nowrap;
        }

        .input-ever { background-color: #000; color: #00ff00; border: 1px solid #444; width: 90px; text-align: center; }

        .wallet-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding-top: 9px;
            border-top: 1px solid #343a40;
        }
        .val-wallet { color: #f39c12; font-family: 'Courier New', monospace; font-weight: bold; }
    </style>
</head>
<body>

<!-- ── FILTER BAR ── -->
<div class="filter-section">
    <div class="container-fluid">
        <form method="POST" class="form-inline flex-wrap" style="gap:6px; row-gap:8px;">
            <label class="mr-2">Corporación:</label>
            <select name="filter_corp" class="form-control form-control-sm mr-3 bg-dark text-white border-secondary">
                <option value="ALL">-- TODAS --</option>
                <?php while($c = mysqli_fetch_assoc($resList)): ?>
                    <option value="<?php echo htmlspecialchars($c['corporation_name']); ?>"
                        <?php echo ($filterCorp == $c['corporation_name']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['corporation_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label class="mr-2">Evermarks:</label>
            <select name="filter_ever" class="form-control form-control-sm mr-3 bg-dark text-white border-secondary">
                <option value="ALL" <?php echo ($filterEver == 'ALL') ? 'selected' : ''; ?>>Todos</option>
                <option value="500" <?php echo ($filterEver == '500') ? 'selected' : ''; ?>>Mayores a 500</option>
            </select>

            <button type="submit" class="btn btn-sm btn-primary px-4">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar Vista
            </button>

            <div class="mini-toolbar">
                <a href="#" class="btn-tool btn-tool-dark"  title="Acción A"><i class="fas fa-star"></i>   A</a>
                <a href="#" class="btn-tool btn-tool-dark"  title="Acción B"><i class="fas fa-bolt"></i>   B</a>
                <a href="#" class="btn-tool btn-tool-dark"  title="Acción C"><i class="fas fa-flag"></i>   C</a>
                <a href="#" class="btn-tool btn-tool-dark"  title="Acción D"><i class="fas fa-cog"></i>    D</a>
                <a href="#" class="btn-tool btn-tool-white" title="Acción E"><i class="fas fa-bell"></i>   E</a>
            </div>
        </form>
    </div>
</div>

<!-- ── CARDS ── -->
<div class="container-fluid">

    <?php if ($mensaje_exito): ?>
    <div class="alert alert-success alert-dismissible fade show bg-dark text-success border-success" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?php echo $mensaje_exito; ?>
        <button type="button" class="close text-white" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <div class="row">
    <?php
    $hoy = date('Y-m-d');
    while ($p = mysqli_fetch_assoc($resPilots)):
        $fechaAuditoria = (!empty($p['lastdateevermark'])) ? date('Y-m-d', strtotime($p['lastdateevermark'])) : '';
        $esHoy          = ($fechaAuditoria === $hoy);
        $claseSemaforo  = $esHoy ? 'bg-hoy' : 'bg-pendiente';
        $acc            = getAccTypeStyle($p['acctype']);
        $pocket         = !empty($p['pocket6'])  ? htmlspecialchars($p['pocket6']) : 'S/P';
        $tradefield     = (!empty($p['tradefield'])) ? htmlspecialchars($p['tradefield']) : '-';
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
        <div class="card card-eve">
            <div class="card-body p-3">

                <!-- Tipo de cuenta — esquina superior derecha -->
                <div class="acctype-corner">
                    <i class="fas <?php echo $acc['icon']; ?>"
                       style="color: <?php echo $acc['color']; ?>;"
                       title="<?php echo $acc['label']; ?>"></i>
                </div>

                <!-- RETRATO + NOMBRE + CORP + SP + TRADEFIELD -->
                <div class="d-flex align-items-center mb-3" style="padding-right: 26px;">
                    <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=128"
                         class="portrait mr-3" alt="<?php echo htmlspecialchars($p['toon_name']); ?>">

                    <div class="flex-grow-1 overflow-hidden">
                        <h6 class="text-white text-truncate mb-0"><?php echo htmlspecialchars($p['toon_name']); ?></h6>

                        <small class="corp-tag d-block text-truncate mb-2">
                            <i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($p['corporation_name'] ?? 'N/A'); ?>
                        </small>

                        <div class="val-sp">
                            <i class="fas fa-microchip mr-1"></i>
                            <?php echo number_format($p['TotalSP_M'], 2); ?> <small>M SP</small>
                        </div>

                        <!-- NUEVO: Tradefield en morado -->
                        <div class="trade-tag mt-1">
                            <i class="fas fa-briefcase mr-1"></i><?php echo $tradefield; ?>
                        </div>
                    </div>
                </div>

                <!-- EVERMARKS (semáforo) -->
                <div class="border-top border-secondary pt-3 mt-2 text-center">
                    <div class="data-label mb-1">Evermarks</div>
                    <div class="badge-em <?php echo $claseSemaforo; ?>" title="Auditado: <?php echo $p['lastdateevermark']; ?>">
                        <i class="fas fa-shield-alt mr-1"></i> <?php echo number_format($p['evermarks']); ?>
                    </div>
                </div>

                <!-- ICONOS (ahora via geticons) + POCKET -->
                <div class="d-flex justify-content-between align-items-center">
                    <?php echo geticons($p['toon_number']); ?>
                    <span class="pocket-badge"><?php echo $pocket; ?></span>
                </div>

                <!-- FORM EVERMARKS -->
                <form method="POST" class="mt-3">
                    <input type="hidden" name="toon_name" value="<?php echo htmlspecialchars($p['toon_name']); ?>">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-dark text-secondary border-secondary">EM</span>
                        </div>
                        <input type="number" name="evermarks_val" class="form-control input-ever"
                               value="<?php echo $p['evermarks']; ?>" required min="0">
                        <div class="input-group-append">
                            <button class="btn btn-success" type="submit" name="update_evermarks">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Wallet Balance -->
                <div class="wallet-bar">
                    <small class="text-secondary">WALLET BALANCE</small>
                    <span class="val-wallet">
                        <i class="fas fa-wallet mr-1"></i><?php echo number_format($p['Wallet_M'], 2); ?> M ISK
                    </span>
                </div>

            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>
</div>
<?php echo ui_footer(); ?>
