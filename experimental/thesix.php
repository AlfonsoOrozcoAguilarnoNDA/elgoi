<?php
/**
 * EVE Fleet Dashboard - Unified v2.0
 * Stack: PHP 8.x Procedural, MariaDB, Bootstrap 4.6.x, FontAwesome 5.15.4
 * License: GNU General Public License v3.0
 * Author: Alfonso Orozco Aguilar
 * Fleet Commander
 * Date: 2026-06-07
 * 
 * Unified dashboard combining Abyssal Tier 0 Ready Check and Key Pilots Status.
 * Both sections now use pilot name strings for easy editing.
 */

// =========================================================================
// EDITABLE CONFIGURATION - Pilot Name Strings (Modify as needed)
// =========================================================================

// Section 1: Abyssal Tier 0 Ready Check pilots
$section1_pilots = "Aridam, Hypervisor, Woo Soo-ji, Sue Rtuda, r1h net, Distant Master";

// Section 2: Skill Distribution Comparison pilots
$section2_pilots = "Aridam, Hypervisor, Woo Soo-ji, Sue Rtuda, r1h net, Distant Master";

// =========================================================================
// 1. CONFIGURATION AND DATABASE CONNECTION
// =========================================================================

include "../config.php";

if (!$link) {
    die("Connection Error: " . mysqli_connect_error());
}

mysqli_set_charset($link, "utf8mb4");
date_default_timezone_set('America/Mexico_City');
$timestamp_mexico = date('d/m/Y H:i:s');

// =========================================================================
// 2. SKILL GROUPS DEFINITION (for Section 2)
// =========================================================================

$skill_groups = [
    "Missiles" => [
        'ids' => [3319,3320,3321,3324,12441,12442,20209,20210,20312,20314,20315,21071,],
        'color' => '#E0BBE4',
    ],
    "Turrets" => [
        'ids' => [3301,3302,3303,3304,3305,3310,3311,3312,3315,3316,3317,11083,12213],
        'color' => '#957DAD',
    ],
    "Social" => [
        'ids' => [3555,3356,3357,3359],
        'color' => '#D291BC',
    ],
    "Others" => [
        'ids' => [3318,3327,3328,3329,3330,3331,3334,3387,3392,3393,3394,3405,3411,3413,3416,3417,3418,3419,3420,3424,3425,3426,33091,33092,33093,33094],
        'color' => '#FEC8D8',
    ]
];

// =========================================================================
// 3. AUXILIARY FUNCTIONS
// =========================================================================

function format_output($value, $default = 'N/A') {
    return htmlspecialchars($value ?? $default);
}

function getSkillLevel($link, $toon, $skill_name) {
    $skill_name = mysqli_real_escape_string($link, $skill_name);
    $q = "SELECT rank FROM EVE_CHARSKILLS WHERE toon = $toon AND Description = '$skill_name' LIMIT 1";
    $r = mysqli_query($link, $q);
    $row = mysqli_fetch_assoc($r);
    return $row ? (int)$row['rank'] : 0;
}

function getGroupSP($link, $toon, $group_name) {
    $group_name = mysqli_real_escape_string($link, $group_name);
    $q = "SELECT SUM(skillpoints) as total FROM EVE_CHARSKILLS WHERE toon = $toon AND group_name LIKE '%$group_name%'";
    $r = mysqli_query($link, $q);
    $row = mysqli_fetch_assoc($r);
    return $row ? (int)$row['total'] : 0;
}

function get_pilot_pseudo($link, $toon_number) {
    $sql_pseudo = "SELECT pseudo FROM PANELS WHERE pilot_1 = ? OR pilot_2 = ? OR pilot_3 = ? LIMIT 1";
    $stmt = mysqli_prepare($link, $sql_pseudo);
    mysqli_stmt_bind_param($stmt, "sss", $toon_number, $toon_number, $toon_number);
    mysqli_stmt_execute($stmt);
    $result_pseudo = mysqli_stmt_get_result($stmt);
    if ($result_pseudo && mysqli_num_rows($result_pseudo) > 0) {
        $row_pseudo = mysqli_fetch_assoc($result_pseudo);
        mysqli_free_result($result_pseudo);
        return $row_pseudo['pseudo'];
    }
    return 'N/A';
}

function get_ship_name_from_json($json_ship) {
    if (empty($json_ship) || $json_ship === 'null' || !is_string($json_ship)) {
        return 'No Ship';
    }
    $json_ship_cleaned = stripslashes($json_ship);
    $data = json_decode($json_ship_cleaned, true);
    if ($data && isset($data['ship_name'])) {
        return $data['ship_name'];
    }
    return 'JSON Error';
}

function MuestraSkills_Group($link, $skill_ids, $pilots_names_array) {
    if (empty($skill_ids) || empty($pilots_names_array)) {
        return ['best_pilot_name' => 'N/A', 'pilot_scores' => []];
    }
    $names_string = "'" . implode("','", array_map(function($n) use ($link) { return mysqli_real_escape_string($link, $n); }, $pilots_names_array)) . "'";
    $ids_string = implode(',', array_map('intval', $skill_ids));
    $sql = "SELECT E.toon, SUM(E.skillpoints) AS total_sp, P.toon_name, P.toon_number 
            FROM EVE_CHARSKILLS E 
            JOIN PILOTS P ON E.toon = P.toon_number 
            WHERE P.toon_name IN ($names_string) AND E.typeID IN ($ids_string) 
            GROUP BY E.toon 
            ORDER BY total_sp DESC";
    $result = mysqli_query($link, $sql);
    $pilot_scores = [];
    $max_sp = -1;
    $best_pilot_name = 'N/A';
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $toon = $row['toon'];
            $sp = (int)$row['total_sp'];
            $name = $row['toon_name'];
            $pilot_scores[$toon] = ['sp' => $sp, 'name' => $name];
            if ($sp > $max_sp) {
                $max_sp = $sp;
                $best_pilot_name = $name;
            }
        }
        mysqli_free_result($result);
    }
    return ['best_pilot_name' => $best_pilot_name, 'pilot_scores' => $pilot_scores];
}

function left($str, $length) {
    return substr($str, 0, $length);
}

function aValues319($Qx) {
    global $link;
    $rsX = mysqli_query($link, $Qx);
    if ($link->error <> '') {
        return array("");
    }
    $tipoOP = strtoupper(left($Qx, 6));
    if ($tipoOP != 'SELECT') return array("");
    if (mysqli_num_rows($rsX) == 0) return array("");
    $aDataX = array();
    $Campos = mysqli_num_fields($rsX);
    while ($regX = mysqli_fetch_array($rsX)) {
        for ($iX = 0; $iX < $Campos; $iX++) {
            $finfo = mysqli_fetch_field_direct($rsX, $iX);
            $name = $finfo->name;
            $aDataX[] = $regX[$name];
        }
    }
    return $aDataX;
}

function MuestraSkills_Detalle($link, $title, $skill_ids, $pilots_names_array) {
    if (empty($skill_ids) || empty($pilots_names_array)) {
        echo '<div class="alert alert-warning">No skill or pilot names provided.</div>';
        return;
    }
    $names_string = "'" . implode("','", array_map(function($n) use ($link) { return mysqli_real_escape_string($link, $n); }, $pilots_names_array)) . "'";
    $ids_string = implode(',', array_map('intval', $skill_ids));
    $sql = "SELECT E.typeID, E.Description, E.toon, E.skillpoints, E.rank, P.toon_name 
            FROM EVE_CHARSKILLS E 
            JOIN PILOTS P ON E.toon = P.toon_number 
            WHERE P.toon_name IN ($names_string) AND E.typeID IN ($ids_string)";
    $result = mysqli_query($link, $sql);
    $skill_data = [];
    $skill_totals = [];
    $best_pilot_per_skill = [];
    $pilot_names_map = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $toon = $row['toon'];
            $toon_name = $row['toon_name'];
            $typeID = $row['typeID'];
            $sp = (int)$row['skillpoints'];
            $skill_data[$typeID]['Description'] = format_output($row['Description']);
            $skill_data[$typeID]['Pilots'][$toon_name] = ['rank' => (int)$row['rank'], 'sp' => $sp];
            if (!isset($skill_totals[$toon_name])) $skill_totals[$toon_name] = 0;
            $skill_totals[$toon_name] += $sp;
            $pilot_names_map[$toon] = $toon_name;
            if (!isset($best_pilot_per_skill[$typeID]) || $sp > $best_pilot_per_skill[$typeID]['sp']) {
                $best_pilot_per_skill[$typeID] = ['toon_name' => $toon_name, 'sp' => $sp];
            } elseif ($sp === $best_pilot_per_skill[$typeID]['sp'] && $sp > 0) {
                if (!is_array($best_pilot_per_skill[$typeID]['toon_name'])) {
                    $best_pilot_per_skill[$typeID]['toon_name'] = [$best_pilot_per_skill[$typeID]['toon_name']];
                }
                $best_pilot_per_skill[$typeID]['toon_name'][] = $toon_name;
            }
        }
        mysqli_free_result($result);
    }
    echo '<h3 class="mt-5 mb-3 text-primary">' . format_output($title) . '</h3>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-sm table-striped small">';
    echo '<thead><tr><th>Skill (Description)</th>';
    foreach ($pilots_names_array as $pname) {
        echo '<th class="text-center">' . format_output($pname) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($skill_data as $typeID => $data) {
        echo '<tr><td>' . format_output($data['Description']) . '</td>';
        foreach ($pilots_names_array as $pname) {
            $pilot_skill = $data['Pilots'][$pname] ?? null;
            $is_best = false;
            if (isset($best_pilot_per_skill[$typeID])) {
                $best_toons = $best_pilot_per_skill[$typeID]['toon_name'];
                if (!is_array($best_toons)) $best_toons = [$best_toons];
                $is_best = in_array($pname, $best_toons);
            }
            $cell_content = '';
            $cell_class = '';
            if ($pilot_skill) {
                $sp_k = number_format(($pilot_skill['sp'] / 1000), 0, '', ',');
                $rank_level = $pilot_skill['rank'];
                $cell_content = "{$rank_level} ({$sp_k}k)";
                if ($is_best) {
                    $cell_class = 'bg-success text-white font-weight-bold';
                }
            }
            echo '<td class="' . $cell_class . ' text-center">' . format_output($cell_content, '&nbsp;') . '</td>';
        }
        echo '</tr>';
    }
    echo '<tr class="table-info font-weight-bold"><td>Group Total SP</td>';
    $max_total_sp = !empty($skill_totals) ? max($skill_totals) : 0;
    $best_total_pilot_names = [];
    if ($max_total_sp > 0) {
        foreach ($skill_totals as $pname => $sp) {
            if ($sp === $max_total_sp) $best_total_pilot_names[] = $pname;
        }
    }
    foreach ($pilots_names_array as $pname) {
        $sp_total = $skill_totals[$pname] ?? 0;
        $sp_total_k = number_format(($sp_total / 1000), 0, '', ',');
        $cell_class = (in_array($pname, $best_total_pilot_names) && $max_total_sp > 0) ? 'bg-primary text-white' : '';
        echo '<td class="' . $cell_class . ' text-center">' . $sp_total_k . 'k</td>';
    }
    echo '</tr></tbody></table></div>';
    echo '<p class="lead mt-3">';
    if ($max_total_sp > 0) {
        $sp_total_m = number_format(($max_total_sp / 1000000), 2, '.', ',');
        $best_names_str = implode(' and ', $best_total_pilot_names);
        echo 'Best in ' . format_output(str_replace('Group Detail: ', '', $title)) . ': <strong>' . format_output($best_names_str) . '</strong> (' . $sp_total_m . 'M SP)';
    } else {
        echo 'No pilot has points in this group.';
    }
    echo '</p>';
}

// =========================================================================
// 4. SECTION 1: ABYSSAL TIER 0 READY CHECK DATA
// =========================================================================

$nombres_array1 = array_map('trim', explode(',', $section1_pilots));
$lista_para_sql1 = "'" . implode("','", $nombres_array1) . "'";
$sql1 = "SELECT p.toon_number, p.toon_name, p.pocket6, p.skillpoints as total_sp, p.race, p.unalloc, p.finishqueue, p.current_ship, p.current_location, p.attrib, p.commentcard, p.numitems, p.lastdate 
        FROM PILOTS p 
        WHERE p.toon_name IN ($lista_para_sql1) 
        ORDER BY FIELD(p.toon_name, $lista_para_sql1)";
$res1 = mysqli_query($link, $sql1);

// =========================================================================
// 5. SECTION 2: SKILL DISTRIBUTION COMPARISON DATA
// =========================================================================

$nombres_array2 = array_map('trim', explode(',', $section2_pilots));
$lista_para_sql2 = "'" . implode("','", $nombres_array2) . "'";
$sql2 = "SELECT toon_number, toon_name, race, skillpoints, unalloc, finishqueue, pocket6, current_ship, current_location, attrib, commentcard, numitems, lastdate 
        FROM PILOTS 
        WHERE toon_name IN ($lista_para_sql2) 
        ORDER BY FIELD(toon_name, $lista_para_sql2)";
$pilots_data2 = [];
$error_msg = null;
$result_pilots2 = mysqli_query($link, $sql2);
if ($result_pilots2) {
    while ($pilot_row = mysqli_fetch_assoc($result_pilots2)) {
        $toon_number = $pilot_row['toon_number'];
        $pilot_row['pseudo'] = get_pilot_pseudo($link, $toon_number);
        $pilots_data2[$pilot_row['toon_name']] = $pilot_row;
    }
    mysqli_free_result($result_pilots2);
} else {
    $error_msg = mysqli_error($link);
}

$group_analysis2 = [];
foreach ($skill_groups as $group_name => $group_info) {
    $analysis_result = MuestraSkills_Group($link, $group_info['ids'], $nombres_array2);
    $group_analysis2[$group_name] = $analysis_result;
}

foreach ($pilots_data2 as $toon_name => &$pilot_row) {
    $pilot_row['skill_totals'] = [];
    $pilot_row['is_best_in'] = [];
    foreach ($skill_groups as $group_name => $group_info) {
        $total_sp = $group_analysis2[$group_name]['pilot_scores'][$pilot_row['toon_number']]['sp'] ?? 0;
        $pilot_row['skill_totals'][$group_name] = $total_sp;
        $best_toon_name = $group_analysis2[$group_name]['best_pilot_name'];
        if (format_output($best_toon_name) === format_output($pilot_row['toon_name'])) {
            $pilot_row['is_best_in'][] = $group_name;
        }
    }
}
unset($pilot_row);

// =========================================================================
// 6. HTML OUTPUT
// =========================================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EVE Fleet Dashboard - Unified</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-main {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-bottom: 2px solid #e94560;
            padding: 0.8rem 1rem;
        }
        .navbar-brand {
            color: #e0e0e0 !important;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .navbar-brand i {
            color: #e94560;
            margin-right: 8px;
        }
        .navbar-text {
            color: #a0a0a0;
            font-size: 0.85rem;
        }
        .section-title {
            border-left: 5px solid #e94560;
            padding-left: 15px;
            margin: 30px 0 20px 0;
            font-weight: 700;
            color: #1a1a2e;
        }
        .card-pilot {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card-pilot:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .skill-grid {
            font-size: 0.7rem;
            border-collapse: collapse;
            width: 100%;
            margin-top: 8px;
        }
        .skill-grid td, .skill-grid th {
            border: 1px solid #dee2e6;
            padding: 4px;
            text-align: center;
        }
        .skill-grid thead th {
            background-color: #f8f9fa;
            color: #555;
            text-transform: uppercase;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .level-v {
            color: #28a745;
            font-weight: bold;
        }
        .pocket-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .missile-box {
            font-size: 0.8rem;
            border: 1px solid #ffc107;
            color: #856404;
            background-color: #fff3cd;
            padding: 6px;
            border-radius: 4px;
            text-align: center;
            margin-top: 8px;
        }
        .experimental-banner {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
            border-right: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .experimental-title {
            color: #856404;
            font-weight: bold;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .experimental-desc {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .experimental-warning {
            color: #856404;
            font-size: 0.85rem;
            font-style: italic;
        }
        .feature-tag {
            display: inline-block;
            background: rgba(255, 193, 7, 0.3);
            color: #856404;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin: 2px;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }
        .comparison-banner {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-left: 4px solid #2196f3;
            border-right: 4px solid #2196f3;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .comparison-title {
            color: #1565c0;
            font-weight: bold;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .comparison-desc {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 10px;
        }
        .comparison-note {
            color: #1565c0;
            font-size: 0.85rem;
            font-style: italic;
        }
        .comparison-tag {
            display: inline-block;
            background: rgba(33, 150, 243, 0.2);
            color: #1565c0;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            margin: 2px;
            border: 1px solid rgba(33, 150, 243, 0.3);
        }
        .timestamp-bar {
            background-color: #e9ecef;
            color: #495057;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .card-img-top {
            width: 180px;
            height: 180px;
            object-fit: cover;
            border-radius: 50% !important;
            margin: 15px auto 0;
            border: 3px solid #e94560;
            padding: 3px;
        }
        .section-divider {
            border-top: 2px dashed #dee2e6;
            margin: 40px 0;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-main">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-rocket"></i>
                EVE Fleet Dashboard - Unified
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text">
                    <i class="far fa-calendar-alt"></i> <?php echo date('d M Y'); ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid">

        <!-- TIMESTAMP -->
        <div class="timestamp-bar mt-3">
            <i class="far fa-clock"></i> Pilot Analysis Updated: <?php echo $timestamp_mexico; ?> (Mexico City Time)
        </div>

        <!-- ================================================================
             SECTION 1: ABYSSAL TIER 0 READY CHECK
             ================================================================ -->
        <h2 class="section-title"><i class="fas fa-vial"></i> Abyssal Tier 0 Ready Check</h2>

        <div class="experimental-banner">
            <div class="experimental-title">
                <i class="fas fa-flask"></i> Experimental Features
            </div>
            <div class="experimental-desc">
                This section checks how suitable your pilots are for <strong>Abyssal Tier 0</strong> encounters.
                Skill requirements and thresholds are still being tested and may change based on fleet feedback.
            </div>
            <div class="experimental-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>No warranty.</strong> Data, thresholds, and even this entire section may be wiped or modified without prior notice. Use at your own risk.
            </div>
            <div class="mt-2">
                <span class="feature-tag"><i class="fas fa-crosshairs"></i> Turret Skills</span>
                <span class="feature-tag"><i class="fas fa-rocket"></i> Destroyer Skills</span>
                <span class="feature-tag"><i class="fas fa-shield-alt"></i> Frigate Skills</span>
                <span class="feature-tag"><i class="fas fa-bomb"></i> Missile SP Check</span>
            </div>
        </div>

        <div class="row">
            <?php while($p = mysqli_fetch_assoc($res1)):
                $t = $p['toon_number'];
                $hybrid = getSkillLevel($link, $t, 'Small Hybrid Turret');
                $proj = getSkillLevel($link, $t, 'Small Projectile Turret');
                $energy = getSkillLevel($link, $t, 'Small Energy Turret');
                $minmD = getSkillLevel($link, $t, 'Minmatar Destroyer');
                $caldD = getSkillLevel($link, $t, 'Caldari Destroyer');
                $gallD = getSkillLevel($link, $t, 'Gallente Destroyer');
                $amarrF = getSkillLevel($link, $t, 'Amarr Frigate');
                $missileSP = getGroupSP($link, $t, 'Missile');
            ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                    <div class="card card-pilot h-100 position-relative">
                        <span class="badge badge-info pocket-badge"><?php echo htmlspecialchars($p['pocket6']); ?></span>
                        <img src="https://images.evetech.net/characters/<?php echo $t; ?>/portrait?size=256" class="card-img-top" alt="Avatar">
                        <div class="card-body p-3">
                            <h5 class="text-center font-weight-bold text-dark"><?php echo htmlspecialchars($p['toon_name']); ?></h5>
                            <p class="text-center text-muted small">(<?php echo number_format($p['total_sp']); ?> SP)</p>

                            <table class="skill-grid">
                                <thead><tr>
                                    <th>Hybrid</th><th>Proj</th><th>Energy</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td class="<?php echo ($hybrid == 5 ? 'level-v' : ''); ?>"><?php echo ($hybrid ?: '-'); ?></td>
                                    <td class="<?php echo ($proj == 5 ? 'level-v' : ''); ?>"><?php echo ($proj ?: '-'); ?></td>
                                    <td class="<?php echo ($energy == 5 ? 'level-v' : ''); ?>"><?php echo ($energy ?: '-'); ?></td>
                                </tr></tbody>
                                <thead><tr>
                                    <th>Minm Dest</th><th>Cald Dest</th><th>Gall Dest</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td class="<?php echo ($minmD == 5 ? 'level-v' : ''); ?>"><?php echo ($minmD ?: '-'); ?></td>
                                    <td class="<?php echo ($caldD == 5 ? 'level-v' : ''); ?>"><?php echo ($caldD ?: '-'); ?></td>
                                    <td class="<?php echo ($gallD == 5 ? 'level-v' : ''); ?>"><?php echo ($gallD ?: '-'); ?></td>
                                </tr></tbody>
                                <thead><tr>
                                    <th colspan="3">Amarr Frigate</th>
                                </tr></thead>
                                <tbody><tr>
                                    <td colspan="3" class="<?php echo ($amarrF == 5 ? 'level-v' : ''); ?>"><?php echo ($amarrF ?: '-'); ?></td>
                                </tr></tbody>
                            </table>

                            <?php if($missileSP > 0): ?>
                                <div class="missile-box">
                                    <i class="fas fa-crosshairs"></i> MISSILES: <?php echo number_format($missileSP); ?> SP
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- DIVIDER -->
        <div class="section-divider"></div>

        <!-- ================================================================
             SECTION 2: SKILL DISTRIBUTION COMPARISON
             ================================================================ -->
        <h2 class="section-title"><i class="fas fa-chart-bar"></i> Skill Distribution Comparison</h2>

        <div class="comparison-banner">
            <div class="comparison-title">
                <i class="fas fa-balance-scale"></i> Comparative Skill Analysis
            </div>
            <div class="comparison-desc">
                This section provides a <strong>side-by-side comparison</strong> of skill distribution across the second pilot group.
                Use it to identify specialization gaps, training priorities, and optimal role assignments for fleet operations.
            </div>
            <div class="comparison-note">
                <i class="fas fa-info-circle"></i>
                <strong>Both pilot groups are editable</strong> in the source code. Modify the <code>$section1_pilots</code> and <code>$section2_pilots</code> strings at the top of this file to customize your fleet roster.
            </div>
            <div class="mt-2">
                <span class="comparison-tag"><i class="fas fa-crosshairs"></i> Missiles</span>
                <span class="comparison-tag"><i class="fas fa-bolt"></i> Turrets</span>
                <span class="comparison-tag"><i class="fas fa-users"></i> Social</span>
                <span class="comparison-tag"><i class="fas fa-cogs"></i> Others</span>
            </div>
        </div>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger" role="alert">Pilot Query Error: <?= format_output($error_msg) ?></div>
        <?php endif; ?>

        <div class="row">
            <?php
            $counter = 0;
            foreach ($nombres_array2 as $pname) {
                $pilot = $pilots_data2[$pname] ?? null;
                if (!$pilot) {
                    $pilot = [
                        'toon_name' => 'Unknown Pilot: ' . $pname,
                        'pseudo' => 'N/A', 'race' => 'N/A', 'skillpoints' => 0, 'unalloc' => 0,
                        'finishqueue' => 'N/A', 'pocket6' => 'N/A', 'current_ship' => 'N/A',
                        'current_location' => 'N/A', 'attrib' => 'N/A', 'commentcard' => 'Pilot not found in database.',
                        'numitems' => 0, 'lastdate' => 'N/A', 'toon_number' => 'N/A',
                        'skill_totals' => [], 'is_best_in' => []
                    ];
                }
                $sp_total_m = number_format(($pilot['skillpoints'] / 1000000), 2, '.', ',');
                $sp_unalloc_m = number_format(($pilot['unalloc'] / 1000000), 2, '.', ',');
                $ship_name = get_ship_name_from_json($pilot['current_ship']);
                if ($counter > 0 && $counter % 3 === 0) {
                    echo '</div><div class="row mt-4">';
                }
            ?>
                <div class="col-md-4 mb-4">
                    <div class="card card-pilot h-100">
                        <img src="https://images.evetech.net/characters/<?php echo $pilot['toon_number']; ?>/portrait?size=256"
                             class="card-img-top" alt="Portrait of <?php echo format_output($pilot['toon_name']); ?>">
                        <div class="card-header border-0 bg-white pt-2 pb-0">
                            <h5 class="mb-0 text-center font-weight-bold"><?php echo format_output($pilot['toon_name']); ?></h5>
                            <p class="text-center text-muted small">(#<?php echo format_output($pilot['toon_number']); ?>)</p>
                        </div>
                        <div class="card-body pt-0">
                            <p class="card-text mb-1"><strong>Pseudo (Account):</strong> <?php echo format_output($pilot['pseudo']); ?></p>
                            <p class="card-text mb-1"><strong>Pocket6:</strong> <?php echo format_output($pilot['pocket6']); ?></p>
                            <p class="card-text mb-1"><strong>Race:</strong> <?php echo format_output($pilot['race']); ?></p>
                            <p class="card-text mb-1"><strong>Total SP:</strong> <span class="badge badge-success"><?php echo $sp_total_m; ?>M</span></p>
                            <p class="card-text mb-1"><strong>Unallocated SP:</strong> <span class="badge badge-warning"><?php echo $sp_unalloc_m; ?>M</span></p>
                            <p class="card-text mb-1"><strong>Current Ship:</strong> <?php echo format_output($ship_name); ?></p>
                            <hr>
                            <h6 class="card-subtitle mb-2">Skill Analysis:</h6>
                            <table class="table table-sm table-borderless table-responsive-sm small">
                                <tbody>
                                    <?php foreach ($skill_groups as $group_name => $group_info):
                                        $total_sp = $pilot['skill_totals'][$group_name] ?? 0;
                                        $sp_formatted = number_format(($total_sp / 1000000), 1, '.', ',') . 'M';
                                        $is_best = in_array($group_name, $pilot['is_best_in']);
                                        $row_class = $is_best ? 'font-weight-bold text-dark' : '';
                                        $color_style = $is_best ? 'style="background-color: ' . $group_info['color'] . ';"' : '';
                                        $best_badge = $is_best ? '<span class="badge badge-danger">BEST</span>' : '';
                                    ?>
                                        <tr <?php echo $color_style; ?> class="<?php echo $row_class; ?>">
                                            <td><?php echo format_output($group_name); ?>:</td>
                                            <td class="text-right"><?php echo format_output($sp_formatted); ?> <?php echo $best_badge; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="alert alert-info py-1 px-2 small mt-2">
                                <p class="mb-0 font-weight-bold">Best Pilots:</p>
                                <?php foreach ($skill_groups as $group_name => $group_info):
                                    $best_name = $group_analysis2[$group_name]['best_pilot_name'] ?? 'N/A';
                                ?>
                                    <p class="mb-0 small"><?php echo format_output($group_name); ?>: <strong><?php echo format_output($best_name); ?></strong></p>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <p class="card-text small mb-1">Items: <?php echo format_output($pilot['numitems']); ?></p>
                            <p class="card-text small mb-1">Queue End: <?php echo format_output($pilot['finishqueue']); ?></p>
                            <p class="card-text small mb-1">Last Activity: <?php echo format_output($pilot['lastdate']); ?></p>
                            <h6 class="card-subtitle mt-3 mb-2 text-muted">Comment:</h6>
                            <p class="card-text small font-italic mb-2"><?php echo nl2br(format_output($pilot['commentcard'])); ?></p>
                            <div style="display: none;">
                                <p class="card-text small mb-1">Location (Hidden): <?php echo format_output($pilot['current_location']); ?></p>
                                <p class="card-text small mb-1">Attributes (Hidden): <?php echo format_output($pilot['attrib']); ?></p>
                            </div>
                            <a href="https://www.google.com" target="_blank" class="btn btn-dark btn-block btn-sm mt-3">
                                Go to Hangar (Google.com)
                            </a>
                        </div>
                    </div>
                </div>
            <?php
                $counter++;
            }
            ?>
        </div>

        <!-- ================================================================
             SECTION 2: DETAILED SKILL TABLES
             ================================================================ -->
        <div class="container mt-5">
            <?php
            foreach ($skill_groups as $group_name => $group_info) {
                MuestraSkills_Detalle(
                    $link,
                    "Group Detail: " . $group_name,
                    $group_info['ids'],
                    $nombres_array2
                );
                echo '<hr class="my-5">';
            }
            ?>
        </div>

    </div>

</body>
</html>
