<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_start();
include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'alpha';

if ($mode === 'alpha') {
    $toon_number = isset($_GET['t']) ? (int)$_GET['t'] : 0;
} else {
    $toon_number = isset($_GET['who']) ? (int)$_GET['who'] : 0;
}

if ($toon_number <= 0) {
    die("<div class='alert alert-danger'>Error: Toon number inválido.</div>");
}

if ($mode === 'alpha') {
    $sql_pilot = "SELECT toon_name, email_pilot, race FROM PILOTS WHERE toon_number = $toon_number";
} else {
    $sql_pilot = "SELECT toon_name, skillpoints, corporation, wallet, pocket6, numitems, race, security,
                  jitav, unalloc, DOB, current_ship, current_location, email_pilot
                  FROM PILOTS WHERE toon_number = $toon_number";
}

$result_pilot = mysqli_query($link, $sql_pilot);
if (!$result_pilot || mysqli_num_rows($result_pilot) == 0) {
    die("<div class='alert alert-danger'>Error: No se puede detectar el piloto $toon_number</div>");
}

$pilot = mysqli_fetch_assoc($result_pilot);
mysqli_free_result($result_pilot);

if ($mode === 'alpha' && $pilot['email_pilot'] != $_SESSION['youremail']) {
    die("<div class='alert alert-danger'>Error: Este piloto pertenece a otro usuario o usted se ha desconectado</div>");
}

$pilot_name  = htmlspecialchars($pilot['toon_name']);
$page_title  = $mode === 'alpha' ? "Alpha/Omega Check - $pilot_name" : "Inventario - $pilot_name";
$portrait    = "https://images.evetech.net/characters/{$toon_number}/portrait";

echo ui_header($page_title);
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
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

        /* ── NAVEGACIÓN ENTRE MODOS ── */
        .mode-nav {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 10px 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ── CARDS OSCURAS ── */
        .card-eve {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .card-eve .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            color: #e0e0e0;
        }
        .card-eve .card-header h3,
        .card-eve .card-header h4,
        .card-eve .card-header h5 { margin-bottom: 0; }
        .card-eve .card-body  { background-color: #1a1d21; }
        .card-eve .card-footer { background-color: #0d0f11; border-top: 1px solid #343a40; }

        /* Header accent colors */
        .card-header-blue   { border-left: 4px solid #007bff; }
        .card-header-green  { border-left: 4px solid #28a745; }
        .card-header-yellow { border-left: 4px solid #ffc107; }
        .card-header-cyan   { border-left: 4px solid #17a2b8; }
        .card-header-red    { border-left: 4px solid #dc3545; }

        /* ── STAT CARDS ── */
        .stat-card {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            text-align: center;
            padding: 15px;
            margin-bottom: 15px;
        }
        .stat-card .stat-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 700; margin-top: 5px; }

        /* ── TABLA ALPHA/OMEGA ── */
        .table-eve { color: #ced4da; font-size: 0.82rem; }
        .table-eve thead th {
            background-color: #0d0f11;
            color: #adb5bd;
            border-color: #343a40;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .table-eve tbody td  { border-color: #2c3035; background-color: #1a1d21; }
        .table-eve tbody tr:hover td { background-color: #22262c; }
        .table-eve tfoot td {
            background-color: #0d0f11;
            border-color: #343a40;
            color: #adb5bd;
            font-size: 0.82rem;
        }

        /* Sub-tablas dentro de celdas */
        .table-eve .table-inner { font-size: 0.8rem; }
        .table-eve .table-inner td { border-color: #2c3035; background-color: transparent; }
        .table-eve .table-inner tr:hover td { background-color: rgba(255,255,255,0.04); }

        /* Columna izquierda (must learn) fondo ligeramente distinto */
        .col-must { background-color: #1e2228 !important; }

        /* ── ASSETS ── */
        .location-card {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .location-card .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            padding: 8px 14px;
        }
        .location-card .table { color: #ced4da; font-size: 0.82rem; }
        .location-card .table th {
            background-color: #16191c;
            color: #6c757d;
            border-color: #343a40;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .location-card .table td  { border-color: #2c3035; }
        .location-card .table tr:hover td { background-color: #22262c; }
        .location-card tfoot td {
            background-color: #0d0f11;
            border-color: #343a40;
            color: #adb5bd;
        }

        /* Valor total final */
        .total-final {
            background-color: #0d2a0d;
            border: 1px solid #28a745;
            padding: 20px 25px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-final .label { color: #adb5bd; font-size: 1rem; font-weight: 600; }
        .total-final .value { color: #28a745; font-size: 1.6rem; font-weight: 700; font-family: monospace; }

        /* Top 50 header */
        .top50-header {
            background-color: #1a1200;
            border-left: 4px solid #ffc107;
            color: #ffc107;
        }

        /* Pocket badge */
        .pocket-inline {
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 2px;
            font-weight: 700;
            background-color: #007bff;
            color: #fff;
        }

        /* Filter form */
        .filter-bar-eve {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-left: 4px solid #007bff;
            padding: 12px 16px;
            margin-bottom: 18px;
        }
        .filter-bar-eve .form-check-label { color: #ced4da; }
        .filter-bar-eve .form-check-input { border-color: #495057; }
    </style>
</head>
<body>

<!-- NAVEGACIÓN ENTRE MODOS -->
<div class="mode-nav">
    <div class="btn-group">
        <a href="?mode=alpha&t=<?php echo $toon_number; ?>"
           class="btn btn-sm <?php echo $mode === 'alpha' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
            <i class="fas fa-graduation-cap mr-1"></i> Alpha/Omega Skills
        </a>
        <a href="?mode=assets&who=<?php echo $toon_number; ?>"
           class="btn btn-sm <?php echo $mode === 'assets' ? 'btn-success' : 'btn-outline-secondary'; ?>">
            <i class="fas fa-boxes mr-1"></i> Assets & Wealth
        </a>
    </div>
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Volver
    </a>
</div>

<div class="container-fluid">

<?php
// =====================================================================
// MODO ALPHA
// =====================================================================
if ($mode === 'alpha') {
    $race = "EXPANDED";
    ?>

    <!-- Header piloto -->
    <div class="card-eve">
        <div class="card-header card-header-blue">
            <h4><i class="fas fa-user-check mr-2"></i>Alpha/Omega Skills Analysis</h4>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <img src="<?php echo $portrait; ?>" width="100" height="100"
                         alt="<?php echo $pilot_name; ?>"
                         style="border: 2px solid #495057; border-radius: 50%;">
                </div>
                <div class="col-md-10">
                    <h4 class="text-white mb-1"><?php echo $pilot_name; ?></h4>
                    <div class="text-muted"><i class="fas fa-dna mr-1"></i><?php echo htmlspecialchars($pilot['race']); ?></div>
                    <div class="text-muted mt-1"><i class="fas fa-info-circle mr-1"></i>Análisis EXPANDED — todas las razas</div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $analysis = check_alpha_omega($toon_number, $race);
    ?>

    <!-- Resumen SP -->
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 3px solid #28a745;">
                <div class="stat-label"><i class="fas fa-graduation-cap mr-1"></i>Perfect Alpha SP</div>
                <div class="stat-value" style="color:#28a745;"><?php echo number_format($analysis['perfect_sp']/1000000,2); ?> M</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 3px solid #ffc107;">
                <div class="stat-label"><i class="fas fa-arrow-up mr-1"></i>Need Training SP</div>
                <div class="stat-value" style="color:#ffc107;"><?php echo number_format($analysis['train_sp']/1000000,2); ?> M</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 3px solid #007bff;">
                <div class="stat-label"><i class="fas fa-star mr-1"></i>Useful Omega SP</div>
                <div class="stat-value" style="color:#007bff;"><?php echo number_format($analysis['omega_sp']/1000000,2); ?> M</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card" style="border-left: 3px solid #17a2b8;">
                <div class="stat-label"><i class="fas fa-crown mr-1"></i>Omega Only SP</div>
                <div class="stat-value" style="color:#17a2b8;"><?php echo number_format($analysis['omega_only_sp']/1000000,2); ?> M</div>
            </div>
        </div>
    </div>

    <!-- Tabla principal 5 columnas -->
    <div class="card-eve">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-eve mb-0" style="table-layout:fixed;">
                    <thead>
                        <tr>
                            <th style="width:20%;" class="text-center">
                                <i class="fas fa-book mr-1"></i>Must Learn<br>
                                <small class="text-muted">(<?php echo count($analysis['must_learn']); ?> skills)</small>
                            </th>
                            <th style="width:20%;" class="text-center">
                                <i class="fas fa-level-up-alt mr-1"></i>Need Train<br>
                                <small class="text-muted">(<?php echo count($analysis['need_train']); ?> skills)</small>
                            </th>
                            <th style="width:20%;" class="text-center">
                                <i class="fas fa-check-circle mr-1"></i>Perfect Alpha<br>
                                <small class="text-muted">(<?php echo count($analysis['perfect_alpha']); ?> skills)</small>
                            </th>
                            <th style="width:20%;" class="text-center">
                                <i class="fas fa-star-half-alt mr-1"></i>Useful as Omega<br>
                                <small class="text-muted">(<?php echo count($analysis['useful_omega']); ?> skills)</small>
                            </th>
                            <th style="width:20%;" class="text-center">
                                <i class="fas fa-crown mr-1"></i>Omega Only<br>
                                <small class="text-muted">(<?php echo count($analysis['omega_only']); ?> skills)</small>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="vertical-align:top;">

                            <!-- MUST LEARN -->
                            <td class="col-must">
                                <?php if (empty($analysis['must_learn'])): ?>
                                    <p class="text-muted text-center mt-2"><em>Ninguno</em></p>
                                <?php else: ?>
                                    <table class="table table-sm table-inner mb-0">
                                        <tbody>
                                        <?php $count=1; foreach ($analysis['must_learn'] as $skill): ?>
                                            <tr>
                                                <td style="width:25px;" class="text-muted"><?php echo $count++; ?></td>
                                                <?php echo typea($skill['type_id']); ?>
                                                <td>
                                                    <a href="https://esiknife.com/abyss/skill_detail.php?module=dt2&what=<?php echo $skill['type_id']; ?>"
                                                       target="_blank" class="text-info"><?php echo $skill['type_id']; ?></a>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($skill['name']); ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>

                            <!-- NEED TRAIN -->
                            <td>
                                <?php if (empty($analysis['need_train'])): ?>
                                    <p class="text-muted text-center mt-2"><em>Ninguno</em></p>
                                <?php else: ?>
                                    <table class="table table-sm table-inner mb-0">
                                        <tbody>
                                        <?php $count=1; foreach ($analysis['need_train'] as $skill): ?>
                                            <tr>
                                                <td style="width:25px;" class="text-muted"><?php echo $count++; ?></td>
                                                <?php echo typea($skill['type_id']); ?>
                                                <td>
                                                    <a href="https://esiknife.com/abyss/skill_detail.php?module=dt2&what=<?php echo $skill['type_id']; ?>"
                                                       target="_blank" class="text-info"><?php echo $skill['type_id']; ?></a>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($skill['name']); ?></small>
                                                    <span class="badge badge-warning" style="font-size:0.7rem;">Nivel <?php echo $skill['current']; ?>/<?php echo $skill['max']; ?></span>
                                                    <small class="text-muted">(<?php echo number_format($skill['sp']); ?> SP)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>

                            <!-- PERFECT ALPHA -->
                            <td>
                                <?php if (empty($analysis['perfect_alpha'])): ?>
                                    <p class="text-muted text-center mt-2"><em>Ninguno</em></p>
                                <?php else: ?>
                                    <table class="table table-sm table-inner mb-0">
                                        <tbody>
                                        <?php foreach ($analysis['perfect_alpha'] as $skill): ?>
                                            <tr>
                                                <td>
                                                    <a href="https://esiknife.com/abyss/skill_detail.php?module=dt2&what=<?php echo $skill['type_id']; ?>"
                                                       target="_blank" class="text-info"><?php echo $skill['type_id']; ?></a>
                                                </td>
                                                <td>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($skill['name']); ?></small>
                                                    <span class="badge badge-success" style="font-size:0.7rem;">Nivel <?php echo $skill['level']; ?></span>
                                                    <small class="text-muted">(<?php echo number_format($skill['sp']); ?> SP)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>

                            <!-- USEFUL AS OMEGA -->
                            <td>
                                <?php if (empty($analysis['useful_omega'])): ?>
                                    <p class="text-muted text-center mt-2"><em>Ninguno</em></p>
                                <?php else: ?>
                                    <table class="table table-sm table-inner mb-0">
                                        <tbody>
                                        <?php foreach ($analysis['useful_omega'] as $skill): ?>
                                            <tr>
                                                <td>
                                                    <a href="https://esiknife.com/abyss/skill_detail.php?module=dt2&what=<?php echo $skill['type_id']; ?>"
                                                       target="_blank" class="text-info"><?php echo $skill['type_id']; ?></a>
                                                </td>
                                                <td>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($skill['name']); ?></small>
                                                    <span class="badge badge-danger" style="font-size:0.7rem;">Nivel <?php echo $skill['current']; ?></span>
                                                    <small class="text-muted">(Max Alpha: <?php echo $skill['max']; ?>)</small><br>
                                                    <small class="text-muted">(<?php echo number_format($skill['sp']); ?> SP)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>

                            <!-- OMEGA ONLY -->
                            <td>
                                <?php if (empty($analysis['omega_only'])): ?>
                                    <p class="text-muted text-center mt-2"><em>Ninguno</em></p>
                                <?php else: ?>
                                    <table class="table table-sm table-inner mb-0">
                                        <tbody>
                                        <?php foreach ($analysis['omega_only'] as $skill): ?>
                                            <tr>
                                                <td>
                                                    <a href="https://esiknife.com/abyss/skill_detail.php?module=dt2&what=<?php echo $skill['type_id']; ?>"
                                                       target="_blank" class="text-info"><?php echo $skill['type_id']; ?></a>
                                                </td>
                                                <td>
                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($skill['name']); ?></small>
                                                    <span class="badge badge-info" style="font-size:0.7rem;">Nivel <?php echo $skill['level']; ?></span>
                                                    <small class="text-muted">(<?php echo number_format($skill['sp']); ?> SP)</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="row text-center" style="font-size:0.82rem;">
                <div class="col"><span class="text-muted">Perfect Alpha:</span> <strong class="text-white"><?php echo number_format($analysis['perfect_sp']); ?> SP</strong></div>
                <div class="col"><span class="text-muted">Need Train:</span>    <strong class="text-white"><?php echo number_format($analysis['train_sp']); ?> SP</strong></div>
                <div class="col"><span class="text-muted">Useful Omega:</span>  <strong class="text-white"><?php echo number_format($analysis['omega_sp']); ?> SP</strong></div>
                <div class="col"><span class="text-muted">Omega Only:</span>    <strong class="text-white"><?php echo number_format($analysis['omega_only_sp']); ?> SP</strong></div>
            </div>
        </div>
    </div>

<?php
// =====================================================================
// MODO ASSETS
// =====================================================================
} else {
    $hide_negative = isset($_GET['hide_negative']) && $_GET['hide_negative'] == '1';
    ?>

    <!-- Header piloto -->
    <div class="card-eve">
        <div class="card-header card-header-blue">
            <h4>
                <i class="fas fa-user-astronaut mr-2"></i><?php echo $pilot_name; ?>
                <span class="pocket-inline ml-2"><?php echo htmlspecialchars($pilot['pocket6']); ?></span>
            </h4>
            <small class="text-muted">
                <i class="fas fa-rocket mr-1"></i><?php echo htmlspecialchars($pilot['current_ship'] ?: 'Docked'); ?>
                &nbsp;|&nbsp;
                <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($pilot['current_location'] ?: 'Unknown'); ?>
            </small>
        </div>
        <div class="card-body">
            <div class="row" style="font-size:0.85rem;">
                <div class="col-md-2">
                    <div class="text-muted"><i class="fas fa-birthday-cake mr-1"></i>Fecha Nac.</div>
                    <div class="text-white"><?php echo $pilot['DOB'] ? date('Y-m-d', strtotime($pilot['DOB'])) : 'N/A'; ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted"><i class="fas fa-brain mr-1"></i>Skill Points</div>
                    <div class="text-white"><?php echo number_format($pilot['skillpoints']/1000000,2); ?> M</div>
                    <?php if ($pilot['unalloc'] > 0): ?>
                    <div class="text-success" style="font-size:0.78rem;">+ <?php echo number_format($pilot['unalloc']/1000000,2); ?> M (free)</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <div class="text-muted"><i class="fas fa-building mr-1"></i>Corporación</div>
                    <div class="text-white"><?php echo htmlspecialchars(substr($pilot['corporation'],0,30)); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="text-muted"><i class="fas fa-wallet mr-1"></i>Wallet ISK</div>
                    <div style="color:#f39c12;font-family:monospace;"><?php echo number_format($pilot['wallet'],0); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted"><i class="fas fa-chart-line mr-1"></i>Valor Activos (jitav)</div>
                    <div style="color:#28a745;font-weight:700;"><?php echo number_format($pilot['jitav']/1000000,2); ?> MM</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats row -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid #007bff;">
                <div class="stat-label"><i class="fas fa-boxes mr-1"></i>Total Items</div>
                <div class="stat-value" style="color:#007bff;"><?php echo number_format($pilot['numitems']); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid <?php echo $pilot['security']>=0?'#28a745':'#dc3545'; ?>;">
                <div class="stat-label"><i class="fas fa-shield-alt mr-1"></i>Seguridad</div>
                <div class="stat-value" style="color:<?php echo $pilot['security']>=0?'#28a745':'#dc3545'; ?>;"><?php echo number_format($pilot['security'],2); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid #17a2b8;">
                <div class="stat-label"><i class="fas fa-calendar-alt mr-1"></i>Edad</div>
                <div class="stat-value" style="color:#17a2b8;">
                    <?php echo $pilot['DOB'] ? number_format(floor((time()-strtotime($pilot['DOB']))/86400)).' d' : 'N/A'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtro -->
    <div class="filter-bar-eve">
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="form-inline">
            <input type="hidden" name="mode"  value="assets">
            <input type="hidden" name="who"   value="<?php echo $toon_number; ?>">
            <div class="form-check mr-3">
                <input class="form-check-input" type="checkbox" value="1" id="hide_negative" name="hide_negative"
                       <?php echo $hide_negative ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hide_negative">
                    <i class="fas fa-eye-slash mr-1"></i> Ocultar items filtrados (precios negativos)
                </label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-redo mr-1"></i> Aplicar
            </button>
        </form>
    </div>

    <?php
    // TOP 50
    $where_top = "WHERE toon_number = $toon_number";
    if ($hide_negative) $where_top .= " AND unit_price >= 0";

    $sql_top50 = "SELECT type_description, quantity, unit_price, forge_value, type_id, location_flag, description
                  FROM EVE_ASSETS $where_top ORDER BY forge_value DESC LIMIT 50";
    $result_top50 = mysqli_query($link, $sql_top50);

    if ($result_top50 && mysqli_num_rows($result_top50) > 0) {
        echo "<div class='card-eve'>";
        echo "<div class='card-header top50-header'>";
        echo "<h5 class='mb-0'><i class='fas fa-crown mr-2'></i>Top 50 Items Más Caros</h5>";
        echo "</div>";
        echo "<div class='card-body p-0'>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm table-eve mb-0'>";
        echo "<thead><tr>";
        echo "<th class='text-center' style='width:5%;'>#</th>";
        echo "<th style='width:35%;'><i class='fas fa-cube mr-1'></i>Item</th>";
        echo "<th class='text-center' style='width:10%;'>Qty</th>";
        echo "<th class='text-right' style='width:15%;'>Precio Unit.</th>";
        echo "<th class='text-right' style='width:20%;'>Valor Total</th>";
        echo "<th style='width:15%;'>Ubicación</th>";
        echo "</tr></thead><tbody>";

        $rank=1; $top50_total=0;
        while ($item = mysqli_fetch_assoc($result_top50)) {
            $fv = (float)$item['forge_value'];
            $top50_total += $fv;
            $row_style = '';
            if ($fv >= 100000000)     $row_style = 'style="background-color:#0d2a0d;"';
            elseif ($fv >= 10000000)  $row_style = 'style="background-color:#1a1a00;"';
            echo "<tr $row_style>";
            echo "<td class='text-center text-muted'>{$rank}</td>";
            echo "<td><strong class='text-white'>" . htmlspecialchars($item['type_description']) . "</strong>";
            echo "<br><small class='text-muted'>Type ID: {$item['type_id']}</small></td>";
            echo "<td class='text-center'>" . number_format($item['quantity']) . "</td>";
            echo "<td class='text-right'>" . number_format($item['unit_price'],2) . "</td>";
            echo "<td class='text-right' style='color:#f39c12;font-family:monospace;font-weight:700;'>" . number_format($fv,2) . "</td>";
            echo "<td><span class='badge badge-secondary'>" . htmlspecialchars($item['location_flag']) . "</span></td>";
            echo "</tr>";
            $rank++;
        }
        echo "</tbody>";
        echo "<tfoot><tr>";
        echo "<td colspan='4' class='text-right text-muted'>TOTAL TOP 50:</td>";
        echo "<td class='text-right' style='color:#28a745;font-weight:700;'>" . number_format($top50_total,2) . " ISK</td>";
        echo "<td><small class='text-muted'>(" . number_format($top50_total/1000000,2) . " MM)</small></td>";
        echo "</tr></tfoot>";
        echo "</table></div></div></div>";
        mysqli_free_result($result_top50);
    }

    // ASSETS AGRUPADOS
    $where_clause = "WHERE toon_number = $toon_number";
    if ($hide_negative) $where_clause .= " AND unit_price >= 0";

    $sql_assets = "SELECT location_flag, description, type_description, quantity, unit_price, forge_value, type_id
                   FROM EVE_ASSETS $where_clause ORDER BY location_flag ASC, forge_value DESC";
    $result_assets = mysqli_query($link, $sql_assets);

    if (!$result_assets) {
        echo "<div class='alert alert-danger'>Error al obtener assets: " . mysqli_error($link) . "</div>";
        echo ui_footer(); exit;
    }

    $assets_grouped = []; $total_value = 0; $total_items = 0;
    while ($asset = mysqli_fetch_assoc($result_assets)) {
        $location = $asset['location_flag'] ?: 'Unknown';
        if (!isset($assets_grouped[$location]))
            $assets_grouped[$location] = ['items' => [], 'total_value' => 0, 'total_quantity' => 0];
        $assets_grouped[$location]['items'][] = $asset;
        $assets_grouped[$location]['total_value']    += (float)$asset['forge_value'];
        $assets_grouped[$location]['total_quantity']  += (int)$asset['quantity'];
        $total_value += (float)$asset['forge_value'];
        $total_items += (int)$asset['quantity'];
    }
    mysqli_free_result($result_assets);
    ?>

    <!-- Resumen ubicaciones -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid #007bff;">
                <div class="stat-label"><i class="fas fa-boxes mr-1"></i>Items Contados</div>
                <div class="stat-value" style="color:#007bff;"><?php echo number_format($total_items); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid #17a2b8;">
                <div class="stat-label"><i class="fas fa-map-marker-alt mr-1"></i>Ubicaciones</div>
                <div class="stat-value" style="color:#17a2b8;"><?php echo count($assets_grouped); ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-left:3px solid #28a745;">
                <div class="stat-label"><i class="fas fa-money-bill-wave mr-1"></i>Valor Calculado</div>
                <div class="stat-value" style="color:#28a745;"><?php echo number_format($total_value/1000000,2); ?> MM</div>
            </div>
        </div>
    </div>

    <?php
    // Badge color por tipo de ubicación
    $loc_colors = [
        'Hangar'   => '#007bff',
        'Cargo'    => '#ffc107',
        'DroneBay' => '#17a2b8',
        'Skill'    => '#28a745',
    ];

    foreach ($assets_grouped as $location => $data) {
        $loc_mm = $data['total_value'] / 1000000;
        $badge_color = '#6c757d';
        foreach ($loc_colors as $key => $color) {
            if (stripos($location, $key) !== false) { $badge_color = $color; break; }
        }

        echo "<div class='location-card'>";
        echo "<div class='card-header'>";
        echo "<h6 class='mb-0'>";
        echo "<i class='fas fa-folder-open mr-2' style='color:{$badge_color};'></i>";
        echo "<span style='background-color:{$badge_color};color:#fff;padding:2px 8px;font-size:0.75rem;font-weight:700;border-radius:2px;'>" . htmlspecialchars($location) . "</span> ";
        echo "<small class='text-muted ml-2'>" . number_format($data['total_quantity']) . " items &nbsp;|&nbsp; " . number_format($loc_mm,2) . " MM ISK</small>";
        echo "</h6></div>";
        echo "<div class='card-body p-0'>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm mb-0' style='color:#ced4da;font-size:0.82rem;'>";
        echo "<thead><tr>";
        echo "<th style='width:40%;background:#16191c;color:#6c757d;border-color:#343a40;'>Item</th>";
        echo "<th class='text-center' style='width:12%;background:#16191c;color:#6c757d;border-color:#343a40;'>Cantidad</th>";
        echo "<th class='text-right' style='width:18%;background:#16191c;color:#6c757d;border-color:#343a40;'>Precio Unit.</th>";
        echo "<th class='text-right' style='width:20%;background:#16191c;color:#6c757d;border-color:#343a40;'>Valor Total</th>";
        echo "<th style='width:10%;background:#16191c;color:#6c757d;border-color:#343a40;'>Estación</th>";
        echo "</tr></thead><tbody>";

        foreach ($data['items'] as $item) {
            $up = (float)$item['unit_price'];
            $fv = (float)$item['forge_value'];
            $val_style = '';
            $val_text  = number_format($fv,2) . ' ISK';
            if ($up < 0) {
                $val_style = "style='color:#6c757d;font-style:italic;'";
                $val_text  = 'Filtrado (' . $up . ')';
            } elseif ($fv >= 1000000) {
                $val_style = "style='color:#28a745;font-weight:700;font-family:monospace;'";
            }
            echo "<tr style='border-color:#2c3035;'>";
            echo "<td style='border-color:#2c3035;'><strong class='text-white'>" . htmlspecialchars($item['type_description']) . "</strong><br><small class='text-muted'>Type ID: {$item['type_id']}</small></td>";
            echo "<td class='text-center' style='border-color:#2c3035;'>" . number_format($item['quantity']) . "</td>";
            echo "<td class='text-right' style='border-color:#2c3035;'>" . number_format($up,2) . " ISK</td>";
            echo "<td class='text-right' style='border-color:#2c3035;' {$val_style}>{$val_text}</td>";
            echo "<td style='border-color:#2c3035;'><small class='text-muted'>" . htmlspecialchars(substr($item['description'],0,30)) . "</small></td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "<tfoot><tr>";
        echo "<td colspan='3' class='text-right text-muted' style='background:#0d0f11;border-color:#343a40;font-size:0.78rem;'>SUBTOTAL " . htmlspecialchars($location) . ":</td>";
        echo "<td class='text-right' style='background:#0d0f11;border-color:#343a40;color:#f39c12;font-weight:700;font-family:monospace;'>" . number_format($data['total_value'],2) . " ISK</td>";
        echo "<td style='background:#0d0f11;border-color:#343a40;'></td>";
        echo "</tr></tfoot>";
        echo "</table></div></div></div>";
    }
    ?>

    <!-- Total final -->
    <div class="total-final">
        <div class="label"><i class="fas fa-chart-line mr-2"></i>VALOR TOTAL DE ACTIVOS</div>
        <div class="text-right">
            <div class="value"><?php echo number_format($total_value/1000000,2); ?> MM ISK</div>
            <small class="text-muted"><?php echo number_format($total_value,2); ?> ISK</small>
        </div>
    </div>

<?php } // fin if mode ?>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php echo ui_footer(); ?>
</body>
</html>

<?php
// =====================================================================
// FUNCIONES — SIN CAMBIOS
// =====================================================================
function check_alpha_omega($toon_number, $race) {
    global $link;
    $result = ['must_learn'=>[],'need_train'=>[],'perfect_alpha'=>[],'useful_omega'=>[],'omega_only'=>[],
               'perfect_sp'=>0,'train_sp'=>0,'omega_sp'=>0,'omega_only_sp'=>0];
    $sql_alpha = "SELECT numberskill, SKILL, $race as max_level FROM ALPHA_CLONES WHERE $race > 0 ORDER BY THEGROUP, SKILL";
    $rs_alpha = mysqli_query($link, $sql_alpha);
    while ($alpha_skill = mysqli_fetch_assoc($rs_alpha)) {
        $type_id=$alpha_skill['numberskill']; $skill_name=$alpha_skill['SKILL']; $max_level=(int)$alpha_skill['max_level'];
        $sql_pilot = "SELECT rank, skillpoints FROM EVE_CHARSKILLS WHERE toon = $toon_number AND typeID = $type_id";
        list($current_level, $sp) = avalues319($sql_pilot);
        if ($current_level===''||$current_level===null) {
            $result['must_learn'][] = ['type_id'=>$type_id,'name'=>$skill_name];
        } elseif ($current_level < $max_level) {
            $result['need_train'][] = ['type_id'=>$type_id,'name'=>$skill_name,'current'=>$current_level,'max'=>$max_level,'sp'=>$sp];
            $result['train_sp'] += $sp;
        } elseif ($current_level == $max_level) {
            $result['perfect_alpha'][] = ['type_id'=>$type_id,'name'=>$skill_name,'level'=>$current_level,'sp'=>$sp];
            $result['perfect_sp'] += $sp;
        } elseif ($current_level > $max_level) {
            $result['useful_omega'][] = ['type_id'=>$type_id,'name'=>$skill_name,'current'=>$current_level,'max'=>$max_level,'sp'=>$sp];
            $result['omega_sp'] += $sp;
        }
    }
    mysqli_free_result($rs_alpha);
    $sql_all_skills = "SELECT typeID, Description, rank, skillpoints FROM EVE_CHARSKILLS WHERE toon = $toon_number AND (rank > 0 OR skillpoints > 0)";
    $rs_all = mysqli_query($link, $sql_all_skills);
    while ($pilot_skill = mysqli_fetch_assoc($rs_all)) {
        $type_id = $pilot_skill['typeID'];
        $sql_check = "SELECT COUNT(numberskill) as count FROM ALPHA_CLONES WHERE numberskill = $type_id AND $race > 0";
        list($in_alpha) = avalues319($sql_check);
        if ($in_alpha == 0) {
            $result['omega_only'][] = ['type_id'=>$type_id,'name'=>$pilot_skill['Description'],'level'=>$pilot_skill['rank'],'sp'=>$pilot_skill['skillpoints']];
            $result['omega_only_sp'] += $pilot_skill['skillpoints'];
        }
    }
    mysqli_free_result($rs_all);
    return $result;
}

function avalues319($Qx) {
    global $link;
    $rsX = mysqli_query($link, $Qx);
    $Qx2 = strtolower($Qx);
    if (left($Qx2,6)<>'select') return "";
    $aDataX = [];
    $rows = mysqli_num_rows($rsX);
    if ($rows==0) return ["",""];
    $Campos = mysqli_num_fields($rsX);
    while ($regX = mysqli_fetch_array($rsX)) {
        for($iX=0;$iX<$Campos;$iX++){
            $finfo=mysqli_fetch_field_direct($rsX,$iX);
            $aDataX[]=$regX[$finfo->name];
        }
    }
    return $aDataX;
}

function left($str,$length)  { return substr($str,0,$length); }
function right($str,$length) { return substr($str,-$length); }

function typea($value) {
    list($pass)=avalues319("Select Combination from SkillAttributes where TypeId='$value'");
    if ($pass=="") $pass="n/a";
    $color="";
    if ($pass=="Perception/Willpower")  $color=" style='background-color:cyan'";
    if ($pass=="Willpower/Perception")  $color=" style='background-color:yellow'";
    if ($pass=="Intelligence/Perception") $color=" style='background-color:lime'";
    if ($pass=="Intelligence/Memory")   $color=" style='background-color:#cccccc'";
    if ($pass=="Memory/Intelligence")   $color=" style='background-color:pink'";
    if ($pass=="Charisma/Intelligence") $color=" style='background-color:#cc99cc'";
    if ($pass=="Charisma/Willpower")    $color=" style='background-color:#dcb59f'";
    if ($pass=="Perception/Willpower")  $pass="Per/Wil";
    if ($pass=="Willpower/Perception")  $pass="Wil/Per";
    if ($pass=="Willpower/Intelligence") $pass="Wil/Int";
    if ($pass=="Perception/Memory")     $pass="Per/Mem";
    if ($pass=="Memory/Perception")     $pass="Mem/Per";
    if ($pass=="Memory/Charisma")       $pass="Mem/Cha";
    if ($pass=="Charisma/Willpower")    $pass="Cha/Wil";
    if ($pass=="Memory/Intelligence")   $pass="Mem/Int";
    if ($pass=="Intelligence/Memory")   $pass="Int/Mem";
    if ($pass=="Intelligence/Perception") $pass="Int/Per";
    if ($pass=="Charisma/Intelligence") $pass="Cha/Int";
    if ($pass=="Willpower/Charisma")    $pass="Wil/Cha";
    if ($pass=="Charisma/Memory")       $pass="Cha/Mem";
    return "<td $color>$pass</td>";
}
?>
