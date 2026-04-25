<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar

*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();

// ===============================================
// SINCRONIZACIÓN AUTOMÁTICA DE EVE_CHARSKILLS
// ===============================================
$actual_supergroup = 1;

$sql_pilots = "SELECT toon_number, toon_name, skillpoints, supergroup 
               FROM PILOTS 
               WHERE supergroup = '$actual_supergroup'";
//die($sql_pilots);
$result_pilots = mysqli_query($link, $sql_pilots);
if ($result_pilots) {
    $sync_count = 0;
    while ($pilot = mysqli_fetch_assoc($result_pilots)) {
        $sql_update = "UPDATE EVE_CHARSKILLS 
                       SET toon_name = '" . mysqli_real_escape_string($link, $pilot['toon_name']) . "',
                           PILOT_SP = " . (int)$pilot['skillpoints'] . ",
                           owner_email = '" . mysqli_real_escape_string($link, $pilot['supergroup']) . "'
                       WHERE toon = " . (int)$pilot['toon_number'];
        //die($sql_update);
		if (mysqli_query($link, $sql_update)) {
            $sync_count += mysqli_affected_rows($link);
        }
    }
    mysqli_free_result($result_pilots);
}

// ===============================================
// PARÁMETROS
// ===============================================
$module = isset($_GET['module']) ? $_GET['module'] : '';
$typeID = isset($_GET['what'])   ? (int)$_GET['what'] : 0;

if (empty($module) || $typeID <= 0) {
    die("<div class='alert alert-danger'>Error: Parámetros inválidos. Use ?module=dt2&what=[typeID]</div>");
}

// ===============================================
// INFO DEL SKILL
// ===============================================
$sql_skill_info   = "SELECT Description FROM EVE_CHARSKILLS WHERE typeID = $typeID LIMIT 1";
$result_skill_info = mysqli_query($link, $sql_skill_info);
$skill_description = "Skill ID: $typeID";
if ($result_skill_info && mysqli_num_rows($result_skill_info) > 0) {
    $skill_info = mysqli_fetch_assoc($result_skill_info);
    $skill_description = $skill_info['Description'];
    mysqli_free_result($result_skill_info);
}

// ===============================================
// PILOTOS CON SKILL
// ===============================================
$sql_have_skill = "SELECT s.toon_name, s.skillpoints, s.rank, p.pocket6
                   FROM EVE_CHARSKILLS s
                   INNER JOIN PILOTS p ON s.toon = p.toon_number
                   WHERE s.typeID = $typeID
                   AND s.owner_email = '" . mysqli_real_escape_string($link, $actual_supergroup) . "'
                   ORDER BY s.skillpoints DESC";

$result_have = mysqli_query($link, $sql_have_skill);
if (!$result_have) die("<div class='alert alert-danger'>Error: " . mysqli_error($link) . "</div>");

$pilots_with_skill    = [];
$pilot_names_with_skill = [];
while ($row = mysqli_fetch_assoc($result_have)) {
    $pilots_with_skill[]      = $row;
    $pilot_names_with_skill[] = $row['toon_name'];
}
mysqli_free_result($result_have);

// ===============================================
// PILOTOS SIN SKILL
// ===============================================
$sql_no_skill = "SELECT toon_name, pocket6 FROM PILOTS
                 WHERE supergroup = '" . mysqli_real_escape_string($link, $actual_supergroup) . "'";

if (count($pilot_names_with_skill) > 0) {
    $names_escaped = array_map(fn($n) => "'" . mysqli_real_escape_string($link, $n) . "'", $pilot_names_with_skill);
    $sql_no_skill .= " AND toon_name NOT IN (" . implode(',', $names_escaped) . ")";
}
$sql_no_skill .= " ORDER BY toon_name ASC";

$result_no_skill = mysqli_query($link, $sql_no_skill);
if (!$result_no_skill) die("<div class='alert alert-danger'>Error: " . mysqli_error($link) . "</div>");

$pilots_without_skill = [];
while ($row = mysqli_fetch_assoc($result_no_skill)) $pilots_without_skill[] = $row;
mysqli_free_result($result_no_skill);

// ===============================================
// BADGE POCKET — paleta consistente con el resto
// ===============================================
function get_pocket_badge($val) {
    $v = strtoupper(trim($val ?? 'N/A'));
    $colors = [
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14',
    ];
    $bg    = $colors[$v] ?? '#495057';
    $color = in_array($v, ['SANGO','YENN']) ? '#111' : '#fff';
    return "<span style='background-color:{$bg};color:{$color};padding:2px 9px;border-radius:2px;font-size:0.72rem;font-weight:700;'>{$v}</span>";
}

$title = "Skill Detail — " . htmlspecialchars($skill_description);
echo ui_header($title);
echo crew_navbar();
echo ui_generate_navbar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
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

        /* ── CARDS ── */
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
        .card-eve .card-body { background-color: #1a1d21; }

        /* Accent izquierdo */
        .accent-blue   { border-left: 4px solid #007bff; }
        .accent-green  { border-left: 4px solid #28a745; }
        .accent-red    { border-left: 4px solid #dc3545; }
        .accent-yellow { border-left: 4px solid #ffc107; }

        /* ── STAT BOXES ── */
        .stat-box {
            background-color: #0d0f11;
            border: 1px solid #343a40;
            padding: 14px;
            text-align: center;
        }
        .stat-box .stat-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-box .stat-value { font-size: 1.8rem; font-weight: 700; margin-top: 4px; }

        /* ── TABLAS ── */
        .table-eve { color: #ced4da; font-size: 0.83rem; margin-bottom: 0; }
        .table-eve thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .table-eve tbody tr:nth-child(odd)  { background-color: #1e2126; }
        .table-eve tbody tr:nth-child(even) { background-color: #1a1d21; }
        .table-eve tbody tr:hover           { background-color: #2a3040 !important; color: #fff; }
        .table-eve td { border-color: #2c3035; vertical-align: middle; }

        /* Fila "tiene skill" con tinte verde sutil */
        .row-has { background-color: #0d1f0d !important; }
        .row-has:hover { background-color: #142814 !important; }

        /* SP badge */
        .sp-pill {
            background-color: #001a2a;
            border: 1px solid #17a2b8;
            color: #17a2b8;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-family: monospace;
            font-weight: 700;
        }

        /* Rank badge */
        .rank-pill {
            background-color: #1a1200;
            border: 1px solid #ffc107;
            color: #ffc107;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* Row number */
        .row-num { color: #6c757d; font-size: 0.78rem; }

        /* ── LEYENDA ── */
        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 4px 10px 4px 0;
            font-size: 0.82rem;
            color: #adb5bd;
        }
    </style>
</head>
<body>

<div class="container-fluid">

    <!-- Header principal -->
    <div class="card-eve accent-blue mb-4">
        <div class="card-header">
            <h4 class="mb-0">
                <i class="fas fa-graduation-cap mr-2"></i>
                <?php echo htmlspecialchars($skill_description); ?>
                <small class="text-muted ml-2" style="font-size:0.75rem;">Type ID: <?php echo $typeID; ?></small>
            </h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="fas fa-check-circle mr-1"></i>Pilotos con Skill</div>
                        <div class="stat-value" style="color:#28a745;"><?php echo count($pilots_with_skill); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="fas fa-times-circle mr-1"></i>Pilotos sin Skill</div>
                        <div class="stat-value" style="color:#dc3545;"><?php echo count($pilots_without_skill); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box">
                        <div class="stat-label"><i class="fas fa-users mr-1"></i>Total Pilotos</div>
                        <div class="stat-value" style="color:#17a2b8;"><?php echo count($pilots_with_skill) + count($pilots_without_skill); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botón volver -->
    <div class="mb-3">
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver
        </a>
    </div>

    <!-- TABLA 1: CON SKILL -->
    <?php if (count($pilots_with_skill) > 0): ?>
    <div class="card-eve accent-green">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-check-circle mr-2" style="color:#28a745;"></i>
                Pilotos que tienen este skill
                <small class="text-muted ml-2">(<?php echo count($pilots_with_skill); ?> — ordenados por SP)</small>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-eve">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:6%;">#</th>
                            <th style="width:38%;"><i class="fas fa-user mr-1"></i>Piloto</th>
                            <th class="text-center" style="width:22%;"><i class="fas fa-star mr-1"></i>Skillpoints</th>
                            <th class="text-center" style="width:12%;"><i class="fas fa-layer-group mr-1"></i>Rank</th>
                            <th class="text-center" style="width:22%;"><i class="fas fa-folder mr-1"></i>Pocket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n=1; foreach ($pilots_with_skill as $p): ?>
                        <tr class="row-has">
                            <td class="text-center row-num"><?php echo $n++; ?></td>
                            <td><strong class="text-white"><?php echo htmlspecialchars($p['toon_name']); ?></strong></td>
                            <td class="text-center"><span class="sp-pill"><?php echo number_format($p['skillpoints']); ?></span></td>
                            <td class="text-center"><span class="rank-pill"><?php echo number_format($p['rank']); ?></span></td>
                            <td class="text-center"><?php echo get_pocket_badge($p['pocket6']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card-eve" style="border-left:4px solid #17a2b8;">
        <div class="card-body">
            <i class="fas fa-info-circle mr-2 text-info"></i>
            Ningún piloto de tu grupo tiene este skill actualmente.
        </div>
    </div>
    <?php endif; ?>

    <!-- TABLA 2: SIN SKILL -->
    <?php if (count($pilots_without_skill) > 0): ?>
    <div class="card-eve accent-red">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-times-circle mr-2" style="color:#dc3545;"></i>
                Pilotos que NO tienen este skill
                <small class="text-muted ml-2">(<?php echo count($pilots_without_skill); ?> — orden alfabético)</small>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-eve">
                    <thead>
                        <tr>
                            <th class="text-center" style="width:10%;">#</th>
                            <th style="width:65%;"><i class="fas fa-user mr-1"></i>Piloto</th>
                            <th class="text-center" style="width:25%;"><i class="fas fa-folder mr-1"></i>Pocket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $n=1; foreach ($pilots_without_skill as $p): ?>
                        <tr>
                            <td class="text-center row-num"><?php echo $n++; ?></td>
                            <td class="text-white"><?php echo htmlspecialchars($p['toon_name']); ?></td>
                            <td class="text-center"><?php echo get_pocket_badge($p['pocket6']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card-eve" style="border-left:4px solid #28a745;">
        <div class="card-body" style="color:#28a745;">
            <i class="fas fa-check-circle mr-2"></i>
            ¡Excelente! Todos los pilotos de tu grupo tienen este skill.
        </div>
    </div>
    <?php endif; ?>

    <!-- Leyenda -->
    <div class="card-eve accent-yellow">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-palette mr-2"></i>Leyenda de Pocket</h6>
        </div>
        <div class="card-body py-2">
            <?php
            $legend = [
                'EXPER' => ['#28a745','Experimental'],
                'NOKIA' => ['#e81123','Nokia'],
                'CLEAN' => ['#0078d7','Clean'],
                'LUCKY' => ['#6f42c1','Lucky'],
                'SANGO' => ['#ffc107','Sango'],
                'YENN'  => ['#cccccc','Yenn'],
                'OTHER' => ['#fd7e14','Otro'],
            ];
            foreach ($legend as $key => [$bg, $label]):
                $txt = in_array($key,['SANGO','YENN']) ? '#111' : '#fff';
            ?>
            <span class="legend-item">
                <span style="background-color:<?php echo $bg; ?>;color:<?php echo $txt; ?>;padding:2px 9px;border-radius:2px;font-size:0.72rem;font-weight:700;"><?php echo $key; ?></span>
                <?php echo $label; ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<?php
// ===============================================
// FUNCIÓN CLEANUP — SIN CAMBIOS
// ===============================================
function cleanup_all_others_catalog() {
    global $link;
    $catalog_toon = 2122782650;
    $deleted_count = 0;
    $sql_duplicates = "SELECT typeID, MAX(skillpoints) as max_sp FROM EVE_CHARSKILLS
                       WHERE toon = $catalog_toon GROUP BY typeID HAVING COUNT(*) > 1";
    $result_dup = mysqli_query($link, $sql_duplicates);
    if ($result_dup) {
        while ($dup = mysqli_fetch_assoc($result_dup)) {
            $typeID = (int)$dup['typeID']; $max_sp = (int)$dup['max_sp'];
            $sql_del = "DELETE FROM EVE_CHARSKILLS WHERE toon=$catalog_toon AND typeID=$typeID AND skillpoints<$max_sp";
            if (mysqli_query($link, $sql_del)) $deleted_count += mysqli_affected_rows($link);
        }
        mysqli_free_result($result_dup);
    }
    $sql_catalog_skills = "SELECT ID, typeID, skillpoints FROM EVE_CHARSKILLS WHERE toon = $catalog_toon";
    $result_catalog = mysqli_query($link, $sql_catalog_skills);
    if ($result_catalog) {
        while ($cs = mysqli_fetch_assoc($result_catalog)) {
            $skill_id=$cs['ID']; $typeID=$cs['typeID']; $catalog_sp=$cs['skillpoints'];
            $sql_check = "SELECT ID FROM EVE_CHARSKILLS WHERE typeID=$typeID AND toon!=$catalog_toon AND skillpoints>=$catalog_sp LIMIT 1";
            $result_check = mysqli_query($link, $sql_check);
            if ($result_check && mysqli_num_rows($result_check) > 0) {
                $sql_del2 = "DELETE FROM EVE_CHARSKILLS WHERE ID = $skill_id";
                if (mysqli_query($link, $sql_del2)) $deleted_count += mysqli_affected_rows($link);
            }
            if ($result_check) mysqli_free_result($result_check);
        }
        mysqli_free_result($result_catalog);
    }
    return $deleted_count;
}

$cleanup_result = cleanup_all_others_catalog();
echo ui_footer();
?>
</body>
</html>
