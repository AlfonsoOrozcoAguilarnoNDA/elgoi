<?php
/**
 * EVE Online Skill Inspector
 * Identifica pilotos con/sin una habilidad específica
 * 
 * @author    Alfonso Orozco Aguilar (VibeCodingMexico.com) 
 * @coauthor  Kimi K2.6 (Moonshot AI)
 * @license   GPL-2.0-or-later
 * @version   1.1.0
 * @date      2026-05-26
 */

require_once '../config.php';

// ---------------------------------------------------------------------------
// CONFIGURACIÓN Y VALIDACIÓN
// ---------------------------------------------------------------------------
$selected_skill = isset($_GET['skill']) ? trim($_GET['skill']) : '';
$selected_pocket = isset($_GET['pocket6']) ? trim($_GET['pocket6']) : 'ALL';

// Filtro de exclusión: personajes de administración/inventario
$exclusion_sql = "LOWER(p.toon_name) NOT LIKE '%catalog%' AND LOWER(p.toon_name) NOT LIKE '%vps%'";

// Obtener lista de skills disponibles
$skills_available = [];
$q_skills = "SELECT DISTINCT TRIM(Description) as Description FROM EVE_CHARSKILLS WHERE Description != '' ORDER BY TRIM(Description) ASC";
$r_skills = $link->query($q_skills);
if ($r_skills) {
    while ($row = $r_skills->fetch_assoc()) {
        $skills_available[] = $row['Description'];
    }
}

// Obtener lista de Pocket6 disponibles (para el combo)
$pockets_available = [];
$q_pockets = "SELECT DISTINCT Pocket6 FROM PILOTS WHERE Pocket6 IS NOT NULL AND Pocket6 != '' ORDER BY Pocket6 ASC";
$r_pockets = $link->query($q_pockets);
if ($r_pockets) {
    while ($row = $r_pockets->fetch_assoc()) {
        $pockets_available[] = $row['Pocket6'];
    }
}

// ---------------------------------------------------------------------------
// CONSULTA: PILOTOS QUE SÍ TIENEN LA SKILL
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
// CONSULTA: PILOTOS QUE NO TIENEN LA SKILL
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
// CÁLCULO DE NIVEL APROXIMADO (EVE Online skill level formula)
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
// HEADER HTML
// ---------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="es">
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
    </style>
</head>
<body>

<!-- NAVBAR FIJA -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-rocket"></i> EVE Skill Inspector
            <span class="badge badge-info ml-2">Kimi K2.6</span>
        </a>
        <span class="navbar-text text-light">
            <i class="fas fa-robot"></i> Coautor: Kimi K2.6 (Moonshot AI)
        </span>
    </div>
</nav>

<!-- CONTENIDO PRINCIPAL -->
<div class="container-fluid">

    <!-- FORMULARIO DE FILTROS -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-filter"></i> Filtros de Búsqueda
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="form-inline">
                        <div class="form-group mr-3">
                            <label for="skill" class="mr-2"><strong>Habilidad:</strong></label>
                            <select name="skill" id="skill" class="form-control" required>
                                <option value="">-- Selecciona una skill --</option>
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
                                    -- Todos los Pockets --
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
                            <i class="fas fa-search"></i> Consultar
                        </button>

                        <?php if ($selected_skill !== ''): ?>
                            <a href="?" class="btn btn-secondary ml-2">
                                <i class="fas fa-undo"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_skill !== ''): ?>

    <!-- NOTA DE EXCLUSIÓN -->
    <div class="row">
        <div class="col-12">
            <div class="exclusion-note">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <strong>Exclusión activa en ambos paneles:</strong> Los personajes con los términos 
                <code>"catalog"</code> o <code>"vps"</code> en su nombre han sido excluidos 
                intencionalmente de AMBOS paneles ya que corresponden a cuentas de 
                administración/inventario del usuario y no a pilotos operativos.
            </div>
        </div>
    </div>

    <!-- RESULTADOS EN DOS PANELES -->
    <div class="row">

        <!-- PANEL IZQUIERDO: PILOTOS CON LA SKILL -->
        <div class="col-md-6">
            <div class="card have-skill">
                <div class="panel-header bg-success">
                    <i class="fas fa-check-circle"></i> 
                    Pilotos CON "<?php echo htmlspecialchars($selected_skill); ?>"
                    <span class="badge badge-light float-right"><?php echo count($have_skill); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($have_skill) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Piloto</th>
                                        <th>Pocket6</th>
                                        <th>Rank</th>
                                        <th>Skillpoints</th>
                                        <th>Nivel</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; foreach ($have_skill as $pilot): 
                                        $level = eve_skill_level($pilot['skillpoints'], $pilot['rank']);
                                        $level_class = ($level >= 4) ? 'badge-success' : (($level >= 2) ? 'badge-warning' : 'badge-secondary');
                                    ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pilot['toon_name']); ?></strong></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($pilot['Pocket6']); ?></span></td>
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
                            <i class="fas fa-info-circle"></i> Ningún piloto operativo posee esta habilidad 
                            <?php echo ($selected_pocket !== 'ALL') ? 'en el Pocket seleccionado' : ''; ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PANEL DERECHO: PILOTOS SIN LA SKILL -->
        <div class="col-md-6">
            <div class="card missing-skill">
                <div class="panel-header bg-danger">
                    <i class="fas fa-times-circle"></i> 
                    Pilotos SIN "<?php echo htmlspecialchars($selected_skill); ?>"
                    <span class="badge badge-light float-right"><?php echo count($missing_skill); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($missing_skill) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Piloto</th>
                                        <th>Pocket6</th>
                                        <th>SP Total</th>
                                        <th>SP Sin Asignar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $j = 1; foreach ($missing_skill as $pilot): ?>
                                    <tr>
                                        <td><?php echo $j++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($pilot['toon_name']); ?></strong></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($pilot['Pocket6']); ?></span></td>
                                        <td><span class="badge badge-dark sp-badge"><?php echo number_format($pilot['total_sp']); ?></span></td>
                                        <td>
                                            <?php if ($pilot['unalloc'] > 0): ?>
                                                <span class="badge badge-warning sp-badge">
                                                    <?php echo number_format($pilot['unalloc']); ?> 
                                                    <i class="fas fa-bolt" title="SP disponible para inyección"></i>
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
                            <i class="fas fa-info-circle"></i> Todos los pilotos operativos 
                            <?php echo ($selected_pocket !== 'ALL') ? 'en este Pocket ' : ''; ?>
                            poseen esta habilidad (o han sido excluidos por filtro).
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- RESUMEN EJECUTIVO -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h5><i class="fas fa-chart-pie"></i> Resumen</h5>
                    <p class="mb-0">
                        Skill: <strong><?php echo htmlspecialchars($selected_skill); ?></strong> | 
                        Pocket6: <strong><?php echo ($selected_pocket === 'ALL') ? 'Todos' : htmlspecialchars($selected_pocket); ?></strong> | 
                        Con skill: <span class="badge badge-success"><?php echo count($have_skill); ?></span> | 
                        Sin skill: <span class="badge badge-danger"><?php echo count($missing_skill); ?></span> | 
                        Total evaluados: <span class="badge badge-primary"><?php echo count($have_skill) + count($missing_skill); ?></span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>

    <!-- MENSAJE INICIAL -->
    <div class="row">
        <div class="col-12 text-center py-5">
            <div class="jumbotron">
                <h1 class="display-4"><i class="fas fa-rocket"></i> EVE Skill Inspector</h1>
                <p class="lead">Selecciona una habilidad y opcionalmente un Pocket6 para ver la distribución de skills entre tus pilotos operativos.</p>
                <hr class="my-4">
                <p><strong>Exclusión activa:</strong> Los personajes con "catalog" o "vps" en su nombre son excluidos automáticamente de ambos paneles.</p>
                <p class="text-muted">
                    <i class="fas fa-database"></i> 
                    Skills disponibles en base de datos: <?php echo count($skills_available); ?> | 
                    Pockets registrados: <?php echo count($pockets_available); ?>
                </p>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<!-- FOOTER FIJO -->
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
                    <i class="fas fa-robot"></i> Coautor: Kimi K2.6 (Moonshot AI) | 
                    <i class="fas fa-user"></i> Autor: VibeCodingMexico.com
                </small>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap JS + dependencias -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>

</body>
</html>
