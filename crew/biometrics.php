<?php
/*
License Mit
Alfonso Orozco Aguilar
*/
/**
 * Dashboard de Pilotos EVE Online
 * Stack: PHP 8.x Procedimental, MariaDB, Bootstrap 4.6.2, Font Awesome 5.15.4
 * Integra: ESI API para nombres de categorías de habilidades.
 * Versión: 2026.03 — Filtros por tradefield y corporation_name
 */
include "../config.php";

$conn = $link;

// ==============================================================================
// FUNCIONES DE SKILLS Y GRÁFICAS
// ==============================================================================
function getSkillCategoryNameFromESI($skillID) {
    $ctx     = stream_context_create(['http' => ['timeout' => 2]]);
    $typeUrl = "https://esi.evetech.net/latest/universe/types/{$skillID}/?datasource=tranquility";
    $typeJson = @file_get_contents($typeUrl, false, $ctx);
    if ($typeJson === false) return null;
    $typeData = json_decode($typeJson, true);
    if (!isset($typeData['group_id'])) return null;
    $groupId  = $typeData['group_id'];
    $groupUrl = "https://esi.evetech.net/latest/universe/groups/{$groupId}/?datasource=tranquility";
    $groupJson = @file_get_contents($groupUrl, false, $ctx);
    if ($groupJson === false) return null;
    $groupData = json_decode($groupJson, true);
    return $groupData['name'] ?? null;
}

function getPilotSkillGraph($conn, $toon_name) {
    $safeName = mysqli_real_escape_string($conn, $toon_name);
    $sql = "SELECT group_name, SUM(skillpoints) as total_group_sp 
            FROM EVE_CHARSKILLS 
            WHERE toon_name = '$safeName' 
            GROUP BY group_name 
            ORDER BY total_group_sp DESC";
    $result = mysqli_query($conn, $sql);
    $data = [];
    $totalPilotoSP = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
        $totalPilotoSP += $row['total_group_sp'];
    }
    if ($totalPilotoSP == 0) return "<small class='text-muted'>Sin datos de habilidades</small>";

    $html   = '<div class="skill-stats" style="font-size:0.75rem;text-align:left;margin-top:5px;">';
    $labels = [];
    $values = [];
    foreach (array_slice($data, 0, 4) as $item) {
        $percent  = ($item['total_group_sp'] / $totalPilotoSP) * 100;
        $labels[] = $item['group_name'];
        $values[] = $item['total_group_sp'];
        $html .= '<div class="d-flex justify-content-between border-bottom border-secondary mb-1">';
        $html .= '<span>' . htmlspecialchars($item['group_name']) . '</span>';
        $html .= '<span class="text-info">' . number_format($percent, 1) . '%</span>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $canvasId = "chart_" . md5($toon_name);
    $GLOBALS['chart_data'][$canvasId] = ['labels' => $labels, 'values' => $values];
    return '<canvas id="' . $canvasId . '" width="100" height="100"></canvas>' . $html;
}

// ==============================================================================
// MANTENIMIENTO DE CATÁLOGOS (PASO 1 Y 2)
// ==============================================================================
$sqlPending = "SELECT DISTINCT typeID, Description FROM EVE_CHARSKILLS WHERE group_name IS NULL OR group_name = ''";
$resPending = mysqli_query($conn, $sqlPending);
if ($resPending && mysqli_num_rows($resPending) > 0) {
    while ($rowSkill = mysqli_fetch_assoc($resPending)) {
        $currentTypeID = $rowSkill['typeID'];
        $currentDesc   = mysqli_real_escape_string($conn, $rowSkill['Description']);
        $sqlCheckCat   = "SELECT group_name FROM cat_typeofskill WHERE typeID = $currentTypeID LIMIT 1";
        $resCheckCat   = mysqli_query($conn, $sqlCheckCat);
        $finalGroupName = '';
        if (mysqli_num_rows($resCheckCat) > 0) {
            $catRow = mysqli_fetch_assoc($resCheckCat);
            $finalGroupName = $catRow['group_name'];
        } else {
            $esiGroupName = getSkillCategoryNameFromESI($currentTypeID);
            if ($esiGroupName) {
                $finalGroupName   = $esiGroupName;
                $safeGroupName    = mysqli_real_escape_string($conn, $esiGroupName);
                mysqli_query($conn, "INSERT INTO cat_typeofskill (typeID, Description, group_name) VALUES ($currentTypeID, '$currentDesc', '$safeGroupName')");
            }
        }
        if (!empty($finalGroupName)) {
            $safeGN = mysqli_real_escape_string($conn, $finalGroupName);
            mysqli_query($conn, "UPDATE EVE_CHARSKILLS SET group_name = '$safeGN' WHERE typeID = $currentTypeID");
        }
    }
}

// ==============================================================================
// FILTROS
// ==============================================================================
$filterTrade = $_GET['filter_trade'] ?? 'ALL';
$filterCorp  = $_GET['filter_corp']  ?? 'ALL';

// Obtener valores únicos para los selects
$resTrades = mysqli_query($conn, "SELECT DISTINCT tradefield FROM PILOTS WHERE tradefield IS NOT NULL AND tradefield <> '' ORDER BY tradefield ASC");
$resCorp   = mysqli_query($conn, "SELECT DISTINCT corporation_name FROM PILOTS WHERE corporation_name IS NOT NULL AND corporation_name <> '' ORDER BY corporation_name ASC");

// ==============================================================================
// CONSULTA PRINCIPAL CON FILTROS
// ==============================================================================
$where = ["toon_name NOT LIKE 'VPS%'"];

if ($filterTrade !== 'ALL') {
    $safeTrade = mysqli_real_escape_string($conn, $filterTrade);
    $where[] = "tradefield = '$safeTrade'";
}
if ($filterCorp !== 'ALL') {
    $safeCorp = mysqli_real_escape_string($conn, $filterCorp);
    $where[] = "corporation_name = '$safeCorp'";
}

$whereClause = "WHERE " . implode(" AND ", $where);

$sqlRanking = "SELECT 
                toon_number as character_id,
                toon_name,
                pocket6,
                acctype,
                skillpoints,
                unalloc,
                tradefield,
                corporation_name,
                gf,
                ((skillpoints + IFNULL(unalloc, 0)) / 1000000) as TotalSP_M
               FROM PILOTS
               $whereClause
               ORDER BY (skillpoints + IFNULL(unalloc, 0)) DESC";

$resRanking = mysqli_query($conn, $sqlRanking);
$totalPilotos = $resRanking ? mysqli_num_rows($resRanking) : 0;

$GLOBALS['chart_data'] = [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online - Auditoría de Flota</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #1a1d21;
            color: #e0e0e0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 40px;
        }
        .navbar-eve {
            background-color: #0b0c0e;
            border-bottom: 2px solid #495057;
        }

        /* ── BARRA DE FILTROS ── */
        .filter-bar {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 12px 20px;
            margin-bottom: 25px;
        }
        .filter-bar .form-control {
            background-color: #2a2d31;
            border-color: #495057;
            color: #e0e0e0;
        }
        .filter-bar .form-control:focus {
            background-color: #2a2d31;
            color: #fff;
            border-color: #007bff;
            box-shadow: none;
        }

        /* ── CARDS ── */
        .card-eve {
            background-color: #25292e;
            border: 1px solid #444;
            border-radius: 0;
            transition: all 0.2s ease-in-out;
            margin-bottom: 20px;
        }
        .card-eve:hover {
            border-color: #007bff;
            box-shadow: 0 0 10px rgba(0,123,255,0.5);
            transform: translateY(-2px);
        }
        .pilot-portrait {
            border-bottom: 2px solid #444;
            width: 100%;
            height: auto;
        }
        .sp-total {
            font-size: 1.25rem;
            color: #f8f9fa;
            font-weight: bold;
            text-align: center;
        }
        .acc-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #6c757d;
        }
        .pocket-info  { color: #a7aeb5; font-size: 0.85rem; }
        .corp-tag     { color: #5dade2; font-size: 0.8rem; }
        .trade-tag    { color: #f39c12; font-size: 0.8rem; }

        /* Gráfica */
        .grafica-container { background-color: #1a1d21; padding: 8px; }
        .skill-stats div   { padding: 2px 0; }

        /* ── BADGE GF — esquina superior izquierda ── */
        .gf-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color: rgba(0,0,0,0.7);
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #6c757d;
            z-index: 2;
        }

        /* Botón actualizar dummy */
        .btn-actualizar-dummy {
            font-size: 0.8rem;
            letter-spacing: 1px;
            cursor: not-allowed;
            opacity: 0.65;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-eve">
    <a class="navbar-brand" href="#"><i class="fas fa-space-shuttle mr-2"></i>Auditoría de Flota</a>
    <span class="text-muted small ml-auto mr-3">
        <i class="fas fa-users mr-1"></i> <?php echo $totalPilotos; ?> pilotos
    </span>
</nav>

<!-- BARRA DE FILTROS -->
<div class="filter-bar">
    <form method="GET" class="form-inline flex-wrap" style="gap: 10px;">

        <!-- Filtro Trade Field -->
        <label class="mr-2 text-light"><i class="fas fa-tag mr-1"></i> Trade:</label>
        <select name="filter_trade" class="form-control form-control-sm mr-3">
            <option value="ALL">-- Todos --</option>
            <?php
            while ($t = mysqli_fetch_assoc($resTrades)) {
                $sel = ($filterTrade === $t['tradefield']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($t['tradefield']) . "' $sel>" . htmlspecialchars($t['tradefield']) . "</option>";
            }
            ?>
        </select>

        <!-- Filtro Corporación -->
        <label class="mr-2 text-light"><i class="fas fa-building mr-1"></i> Corp:</label>
        <select name="filter_corp" class="form-control form-control-sm mr-3">
            <option value="ALL">-- Todas --</option>
            <?php
            while ($c = mysqli_fetch_assoc($resCorp)) {
                $sel = ($filterCorp === $c['corporation_name']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($c['corporation_name']) . "' $sel>" . htmlspecialchars($c['corporation_name']) . "</option>";
            }
            ?>
        </select>

        <!-- Botón Filtrar -->
        <button type="submit" class="btn btn-sm btn-primary mr-3">
            <i class="fas fa-filter mr-1"></i> Filtrar
        </button>

        <!-- Botón Limpiar filtros -->
        <?php if ($filterTrade !== 'ALL' || $filterCorp !== 'ALL'): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary mr-3">
            <i class="fas fa-times mr-1"></i> Limpiar
        </a>
        <?php endif; ?>

        <!-- Botón ACTUALIZAR (dummy / placeholder) -->
        <button type="button" class="btn btn-sm btn-secondary btn-actualizar-dummy ml-auto" disabled title="Próximamente">
            <i class="fas fa-sync-alt mr-1"></i> ACTUALIZAR
        </button>

    </form>
</div>

<!-- GRID DE PILOTOS -->
<div class="container-fluid">
    <div class="row">
        <?php
        if ($resRanking && mysqli_num_rows($resRanking) > 0):
            while ($pilot = mysqli_fetch_assoc($resRanking)):
                $formattedSP = number_format($pilot['TotalSP_M'], 2, '.', ',');

                $accIcon  = 'fa-question';
                $accColor = 'text-muted';
                if (strtolower($pilot['acctype']) == 'omega') {
                    $accIcon  = 'fa-crown';
                    $accColor = 'text-warning';
                } elseif (strtolower($pilot['acctype']) == 'alpha') {
                    $accIcon  = 'fa-rocket';
                    $accColor = 'text-info';
                }

                $charID      = !empty($pilot['character_id']) ? $pilot['character_id'] : '1';
                $portraitUrl = "https://images.evetech.net/characters/{$charID}/portrait?size=256";
        ?>
        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 col-12 d-flex align-items-stretch">
            <div class="card card-eve w-100 position-relative">

                <!-- Badge GF — esquina superior izquierda -->
                <div class="gf-badge text-center" title="GF: <?php echo (int)($pilot['gf'] ?? 0); ?>">
                    <i class="fas fa-flag" style="color: <?php echo ($pilot['gf'] > 0) ? '#dc3545' : '#495057'; ?>;"></i>
                </div>

                <!-- Ícono tipo cuenta — esquina superior derecha -->
                <div class="acc-type-badge text-center" title="Tipo de Cuenta: <?php echo htmlspecialchars($pilot['acctype']); ?>">
                    <i class="fas <?php echo $accIcon; ?> <?php echo $accColor; ?>"></i>
                </div>

                <img src="<?php echo $portraitUrl; ?>" class="card-img-top pilot-portrait"
                     alt="Retrato de <?php echo htmlspecialchars($pilot['toon_name']); ?>">

                <div class="card-body d-flex flex-column p-3">

                    <!-- Nombre -->
                    <h5 class="card-title text-truncate text-center mb-1"
                        title="<?php echo htmlspecialchars($pilot['toon_name']); ?>">
                        <?php echo htmlspecialchars($pilot['toon_name']); ?>
                    </h5>

                    <!-- SP -->
                    <div class="sp-total mb-2">
                        <?php echo $formattedSP; ?> <small class="text-muted">SP</small>
                    </div>

                    <!-- Trade Field -->
                    <?php if (!empty($pilot['tradefield'])): ?>
                    <div class="trade-tag text-truncate mb-1" title="<?php echo htmlspecialchars($pilot['tradefield']); ?>">
                        <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($pilot['tradefield']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Corporación -->
                    <?php if (!empty($pilot['corporation_name'])): ?>
                    <div class="corp-tag text-truncate mb-2" title="<?php echo htmlspecialchars($pilot['corporation_name']); ?>">
                        <i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($pilot['corporation_name']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Pocket -->
                    <div class="pocket-info mt-auto mb-2">
                        <i class="fas fa-folder mr-1"></i> Pocket: <strong><?php echo htmlspecialchars($pilot['pocket6'] ?? 'N/A'); ?></strong>
                    </div>

                    <!-- Gráfica de skills -->
                    <div class="grafica-container p-2" style="min-height: 150px;">
                        <?php echo getPilotSkillGraph($conn, $pilot['toon_name']); ?>
                    </div>

                </div>
            </div>
        </div>
        <?php
            endwhile;
        else:
        ?>
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                No se encontraron pilotos con los filtros seleccionados.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartData = <?php echo json_encode($GLOBALS['chart_data'] ?? []); ?>;
    const colors = ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6610f2'];

    for (const [id, data] of Object.entries(chartData)) {
        const canvas = document.getElementById(id);
        if (!canvas) continue;
        new Chart(canvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: colors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                legend:  { display: false },
                plugins: { tooltip: { enabled: true } }
            }
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
