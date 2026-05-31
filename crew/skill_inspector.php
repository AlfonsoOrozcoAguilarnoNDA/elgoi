<?php
/**
 * EVE Online Skill Inspector
 * Identifies pilots with/without a specific skill
 * 
 * @author    Alfonso Orozco Aguilar (VibeCodingMexico.com) 
 * @coauthor  Kimi K2.6 (Moonshot AI)
 * @license   GPL-2.0-or-later
 * @version   1.2.0
 * @date      2026-05-31
 */

require_once '../config.php';

// ---------------------------------------------------------------------------
// CONFIGURATION & VALIDATION
// ---------------------------------------------------------------------------
$selected_skill = isset($_GET['skill']) ? trim($_GET['skill']) : '';
$selected_pocket = isset($_GET['pocket6']) ? trim($_GET['pocket6']) : 'ALL';

// Exclusion filter: admin/inventory characters
$exclusion_sql = "LOWER(p.toon_name) NOT LIKE '%catalog%' AND LOWER(p.toon_name) NOT LIKE '%vps%'";

// Get available skills list
$skills_available = [];
$q_skills = "SELECT DISTINCT TRIM(Description) as Description FROM EVE_CHARSKILLS WHERE Description != '' ORDER BY TRIM(Description) ASC";
$r_skills = $link->query($q_skills);
if ($r_skills) {
    while ($row = $r_skills->fetch_assoc()) {
        $skills_available[] = $row['Description'];
    }
}

// Get available Pocket6 list (for the dropdown)
$pockets_available = [];
$q_pockets = "SELECT DISTINCT Pocket6 FROM PILOTS WHERE Pocket6 IS NOT NULL AND Pocket6 != '' ORDER BY Pocket6 ASC";
$r_pockets = $link->query($q_pockets);
if ($r_pockets) {
    while ($row = $r_pockets->fetch_assoc()) {
        $pockets_available[] = $row['Pocket6'];
    }
}

// ---------------------------------------------------------------------------
// QUERY: PILOTS WITH THE SKILL
// ---------------------------------------------------------------------------
$have_skill = [];
if ($selected_skill !== '') {
    $sql_have = "SELECT 
                    p.toon_name,
                    s.rank,
                    s.skillpoints,
                    p.Pocket6
                 FROM EVE_CHARSKILLS s
                 INNER JOIN PILOTS p ON p.toon_number = s.toon
                 WHERE s.Description = ?
                 AND " . $exclusion_sql;

    $params = [$selected_skill];
    $types = 's';

    if ($selected_pocket !== 'ALL') {
        $sql_have .= " AND p.Pocket6 = ?";
        $params[] = $selected_pocket;
        $types .= 's';
    }

    $sql_have .= " ORDER BY s.skillpoints DESC";

    $stmt = $link->prepare($sql_have);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $have_skill[] = $row;
        }
        $stmt->close();
    }
}

// ---------------------------------------------------------------------------
// QUERY: PILOTS WITHOUT THE SKILL
// ---------------------------------------------------------------------------
$missing_skill = [];
if ($selected_skill !== '') {
    $sql_missing = "SELECT 
                        p.toon_name,
                        p.Pocket6,
                        p.skillpoints AS total_sp,
                        p.unalloc
                     FROM PILOTS p
                     WHERE p.toon_number NOT IN (
                         SELECT toon FROM EVE_CHARSKILLS WHERE TRIM(Description) = ?
                     )
                     AND " . $exclusion_sql;

    $params2 = [$selected_skill];
    $types2 = 's';

    if ($selected_pocket !== 'ALL') {
        $sql_missing .= " AND p.Pocket6 = ?";
        $params2[] = $selected_pocket;
        $types2 .= 's';
    }

    $sql_missing .= " ORDER BY p.toon_name ASC";

    $stmt2 = $link->prepare($sql_missing);
    if ($stmt2) {
        $stmt2->bind_param($types2, ...$params2);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        while ($row = $result2->fetch_assoc()) {
            $missing_skill[] = $row;
        }
        $stmt2->close();
    }
}

// ---------------------------------------------------------------------------
// APPROXIMATE LEVEL CALCULATION (EVE Online skill level formula)
// ---------------------------------------------------------------------------
function eve_skill_level($skillpoints, $rank) {
    if ($rank <= 0 || $skillpoints <= 0) return 0;
    $levels = [0, 250, 1414, 8000, 45255, 256000];
    for ($i = 5; $i >= 0; $i--) {
        $threshold = $levels[$i] * $rank;
        if ($skillpoints >= $threshold) {
            return $i;
        }
    }
    return 0;
}

// ---------------------------------------------------------------------------
// POCKET6 COLOR FUNCTIONS
// ---------------------------------------------------------------------------
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

// ---------------------------------------------------------------------------
// HTML HEADER
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Skill Inspector — Kimi K2.6</title>

    <!-- Bootstrap 4.6.x -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">

    <style>
        body { padding-top: 70px; padding-bottom: 60px; }
        .navbar-brand { font-weight: bold; }
        .panel-header { 
            background: #343a40; 
            color: white; 
            padding: 10px 15px; 
            border-radius: 4px 4px 0 0;
            font-weight: bold;
        }
        .have-skill { border-left: 4px solid #28a745; }
        .missing-skill { border-left: 4px solid #dc3545; }
        .exclusion-note { 
            background: #fff3cd; 
            border: 1px solid #ffc107; 
            border-radius: 4px; 
            padding: 10px; 
            margin-bottom: 15px;
        }
        .sp-badge { font-size: 0.85em; }
        .level-badge { font-size: 0.9em; }
        .footer-fixed {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 50px;
            background: #343a40;
            color: #adb5bd;
            line-height: 50px;
            z-index: 1030;
        }
        .pocket-badge-dip {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>

<!-- FIXED NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-rocket"></i> EVE Skill Inspector
            <span class="badge badge-info ml-2">Kimi K2.6</span>
        </a>
        <span class="navbar-text text-light">
            <i class="fas fa-robot"></i> Co-author: Kimi K2.6 (Moonshot AI)
        </span>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container-fluid">

    <!-- FILTER FORM -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-filter"></i> Search Filters
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="form-group mr-3">
                            <label for="skill" class="mr-2"><strong>Skill:</strong></label>
                            <select name="skill" id="skill" class="form-control" required>
                                <option value="">-- Select a skill --</option>
                                <?php foreach ($skills_available as $skill): ?>
                                    <option value="<?php echo htmlspecialchars($skill); ?>" 
                                        <?php echo ($selected_skill === $skill) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($skill); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group mr-3">
                            <label for="pocket6" class="mr-2"><strong>Pocket6:</strong></label>
                            <select name="pocket6" id="pocket6" class="form-control">
                                <option value="ALL" <?php echo ($selected_pocket === 'ALL') ? 'selected' : ''; ?>>
                                    -- All Pockets --
                                </option>
                                <?php foreach ($pockets_available as $pocket): ?>
                                    <option value="<?php echo htmlspecialchars($pocket); ?>" 
                                        <?php echo ($selected_pocket === $pocket) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pocket); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>

                        <?php if ($selected_skill !== ''): ?>
                            <a href="?" class="btn btn-secondary ml-2">
                                <i class="fas fa-undo"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_skill !== ''): ?>

    <!-- EXCLUSION NOTE -->
    <div class="row">
        <div class="col-12">
            <div class="exclusion-note">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <strong>Exclusion active on both panels:</strong> Characters with the terms 
                <code>"catalog"</code> or <code>"vps"</code> in their name have been intentionally 
                excluded from BOTH panels as they correspond to administration/inventory accounts 
                and not operational pilots.
            </div>
        </div>
    </div>

    <!-- RESULTS IN TWO PANELS -->
    <div class="row">

        <!-- LEFT PANEL: PILOTS WITH THE SKILL -->
        <div class="col-md-6">
            <div class="card have-skill">
                <div class="panel-header bg-success">
                    <i class="fas fa-check-circle"></i> 
                    Pilots WITH "<?php echo htmlspecialchars($selected_skill); ?>"
                    <span class="badge badge-light float-right"><?php echo count($have_skill); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($have_skill) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Pilot</th>
                                        <th>Pocket6</th>
                                        <th>Rank</th>
                                        <th>Skillpoints</th>
                                        <th>Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($have_skill as $pilot): 
                                        $level = eve_skill_level($pilot['skillpoints'], $pilot['rank']);
                                        $level_class = ($level >= 4) ? 'badge-success' : (($level >= 2) ? 'badge-warning' : 'badge-secondary');
                                        $p6_val = $pilot['Pocket6'];
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pilot['toon_name']); ?></strong></td>
                                        <td>
                                            <span class="pocket-badge-dip" style="background-color:<?php echo get_pocket_color($p6_val); ?>;color:<?php echo get_pocket_text($p6_val); ?>;">
                                                <?php echo htmlspecialchars($p6_val); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $pilot['rank']; ?></td>
                                        <td><span class="badge badge-dark sp-badge"><?php echo number_format($pilot['skillpoints']); ?></span></td>
                                        <td><span class="badge <?php echo $level_class; ?> level-badge"><?php echo $level; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-info-circle"></i> No operational pilot has this skill 
                            <?php echo ($selected_pocket !== 'ALL') ? 'in the selected Pocket' : ''; ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: PILOTS WITHOUT THE SKILL -->
        <div class="col-md-6">
            <div class="card missing-skill">
                <div class="panel-header bg-danger">
                    <i class="fas fa-times-circle"></i> 
                    Pilots WITHOUT "<?php echo htmlspecialchars($selected_skill); ?>"
                    <span class="badge badge-light float-right"><?php echo count($missing_skill); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($missing_skill) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Pilot</th>
                                        <th>Pocket6</th>
                                        <th>Total SP</th>
                                        <th>Unallocated SP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $j = 1; foreach ($missing_skill as $pilot): 
                                        $p6_val = $pilot['Pocket6'];
                                    ?>
                                    <tr>
                                        <td><?php echo $j++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pilot['toon_name']); ?></strong></td>
                                        <td>
                                            <span class="pocket-badge-dip" style="background-color:<?php echo get_pocket_color($p6_val); ?>;color:<?php echo get_pocket_text($p6_val); ?>;">
                                                <?php echo htmlspecialchars($p6_val); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-dark sp-badge"><?php echo number_format($pilot['total_sp']); ?></span></td>
                                        <td>
                                            <?php if ($pilot['unalloc'] > 0): ?>
                                                <span class="badge badge-warning sp-badge">
                                                    <?php echo number_format($pilot['unalloc']); ?> 
                                                    <i class="fas fa-bolt" title="SP available for injection"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary sp-badge">0</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-info-circle"></i> All operational pilots 
                            <?php echo ($selected_pocket !== 'ALL') ? 'in this Pocket ' : ''; ?>
                            have this skill (or have been excluded by filter).
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- EXECUTIVE SUMMARY -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h5><i class="fas fa-chart-pie"></i> Summary</h5>
                    <p class="mb-0">
                        Skill: <strong><?php echo htmlspecialchars($selected_skill); ?></strong> | 
                        Pocket6: <strong><?php echo ($selected_pocket === 'ALL') ? 'All' : htmlspecialchars($selected_pocket); ?></strong> | 
                        With skill: <span class="badge badge-success"><?php echo count($have_skill); ?></span> | 
                        Without skill: <span class="badge badge-danger"><?php echo count($missing_skill); ?></span> | 
                        Total evaluated: <span class="badge badge-primary"><?php echo count($have_skill) + count($missing_skill); ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- WELCOME MESSAGE -->
    <div class="row">
        <div class="col-12 text-center py-5">
            <div class="jumbotron">
                <h1 class="display-4"><i class="fas fa-rocket"></i> EVE Skill Inspector</h1>
                <p class="lead">Select a skill and optionally a Pocket6 to see the skill distribution among your operational pilots.</p>
                <hr class="my-4">
                <p><strong>Active exclusion:</strong> Characters with "catalog" or "vps" in their name are automatically excluded from both panels.</p>
                <p class="text-muted">
                    <i class="fas fa-database"></i> 
                    Skills available in database: <?php echo count($skills_available); ?> | 
                    Pockets registered: <?php echo count($pockets_available); ?>
                </p>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- FIXED FOOTER -->
<footer class="footer-fixed">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 text-left">
                <small>
                    <i class="fas fa-code"></i> PHP <?php echo PHP_VERSION; ?> | 
                    <i class="fas fa-database"></i> MySQL <?php echo $link->server_info; ?> | 
                    <i class="fas fa-shield-alt"></i> GPL-2.0+
                </small>
            </div>
            <div class="col-md-6 text-right">
                <small>
                    <i class="fas fa-robot"></i> Co-author: Kimi K2.6 (Moonshot AI) | 
                    <i class="fas fa-user"></i> Author: VibeCodingMexico.com
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS + dependencies -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>

</body>
</html>
