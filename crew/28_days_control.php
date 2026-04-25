<?php
/**
 * Panel de Control de Flota - EVE Pocket Economy
 * Autor: Alfonso Orozco Aguilar
 * License GPL 3.0
 * Versión: 2026.03
 */

require_once '../config.php';

// ---------------------------------------------------------------------
// CONFIGURACIÓN HARDCODED
// false = gráficas ocultas por default | true = visibles por default
// ---------------------------------------------------------------------
$mostrar_graficas_default = false;

// ==============================================================================
// FUNCIÓN DE GRÁFICAS (tomada del dashboard de skills)
// ==============================================================================
$GLOBALS['chart_data'] = [];

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

    $html = '<div class="skill-stats" style="font-size: 0.75rem; text-align: left; margin-top: 5px;">';
    $labels = [];
    $values = [];

    foreach (array_slice($data, 0, 4) as $item) {
        $percent = ($item['total_group_sp'] / $totalPilotoSP) * 100;
        $labels[] = $item['group_name'];
        $values[] = $item['total_group_sp'];

        $html .= '<div class="d-flex justify-content-between border-bottom border-secondary mb-1">';
        $html .= '<span>' . htmlspecialchars($item['group_name']) . '</span>';
        $html .= '<span class="text-info">' . number_format($percent, 1) . '%</span>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $canvasId = "chart_" . md5($toon_name);
    $finalOutput = '<canvas id="' . $canvasId . '" width="100" height="100"></canvas>' . $html;

    $GLOBALS['chart_data'][$canvasId] = ['labels' => $labels, 'values' => $values];

    return $finalOutput;
}

// --- 1. LÓGICA DE ORDENAMIENTO ---
$sort_param = $_GET['sort'] ?? 'last';
switch ($sort_param) {
    case 'p6':   $order_query = "Pocket6 DESC"; break;
    case 'last': $order_query = "lastsaved ASC"; break;
    case 'wall': $order_query = "wallet DESC"; break;
    case 'name': $order_query = "toon_name ASC"; break;
    case 'item': $order_query = "numitems DESC"; break;
    case 'dob':  $order_query = "DOB ASC"; break;
    case 'sp':
    default:     $order_query = "(skillpoints + unalloc) DESC"; break;
}

// --- 2. CONSULTA A BASE DE DATOS ---
$sql = "SELECT 
            toon_number, toon_name, skillpoints, unalloc, 
            numitems, lastsaved, pocket6, DOB, tradefield
        FROM PILOTS 
        WHERE toon_name NOT LIKE '%VPS%' 
          AND toon_name NOT LIKE '%CATALOG%' 
        ORDER BY $order_query";

$result = mysqli_query($link, $sql);

// --- 3. MAPEADO DE COLORES POR POCKET ---
function getColorPocket($pocket) {
    $p = strtoupper(trim($pocket));
    return match($p) {
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#ffffff',
        default => '#444444'
    };
}

// Convertir default PHP a string JS
$js_graficas_default = $mostrar_graficas_default ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Fleet Monitor - <?php echo date('Y'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #1a1a1a; color: #eee; padding-top: 20px; }

        .pilot-tile {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            background-color: #262626;
            cursor: default;
            margin-bottom: 20px;
        }
        .pilot-tile:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
        }
        .portrait-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 3px solid;
            margin: 15px auto;
            background-color: #111;
        }
        .sp-amount    { font-size: 1.2rem; font-weight: bold; color: #fff; }
        .info-label   { font-size: 0.75rem; color: #888; text-transform: uppercase; }
        .days-warning { font-weight: bold; }
        .btn-update   { font-size: 0.8rem; letter-spacing: 1px; }

        /* tradefield */
        .trade-field  { font-size: 0.78rem; color: #adb5bd; font-style: italic; }

        /* botón gráfica */
        .btn-graph    { font-size: 0.75rem; letter-spacing: 1px; }

        /* contenedor gráfica */
        .grafica-container { background-color: #1a1a1a; padding: 8px; }

        .filter-bar { background: #333; padding: 10px; border-radius: 5px; margin-bottom: 30px; }
    </style>
</head>
<body>

<div class="container-fluid">

    <div class="filter-bar d-flex justify-content-between align-items-center">
        <div>
            <h4 class="m-0"><i class="fa fa-users"></i> Monitor de Pilotos</h4>
        </div>
        <div class="btn-group">
            <a href="?sort=sp"   class="btn btn-sm btn-outline-light <?php echo $sort_param=='sp'   ?'active':''; ?>">Skillpoints</a>
            <a href="?sort=last" class="btn btn-sm btn-outline-light <?php echo $sort_param=='last' ?'active':''; ?>">Última Act.</a>
            <a href="?sort=name" class="btn btn-sm btn-outline-light <?php echo $sort_param=='name' ?'active':''; ?>">Nombre</a>
            <a href="?sort=dob"  class="btn btn-sm btn-outline-light <?php echo $sort_param=='dob'  ?'active':''; ?>">Antigüedad</a>
            <a href="?sort=item" class="btn btn-sm btn-outline-light <?php echo $sort_param=='item' ?'active':''; ?>"># Items</a>
            <a href="?sort=p6"   class="btn btn-sm btn-outline-light <?php echo $sort_param=='p6'   ?'active':''; ?>">Pocket</a>
            <a href="?sort=wall" class="btn btn-sm btn-outline-light <?php echo $sort_param=='wall' ?'active':''; ?>">Wallet</a>
        </div>
    </div>

    <div class="row">
        <?php while ($row = mysqli_fetch_assoc($result)):
            $color      = getColorPocket($row['pocket6']);
            $total_sp   = ($row['skillpoints'] + $row['unalloc']) / 1000000;
            $fecha_sql  = new DateTime($row['lastsaved']);
            $ahora      = new DateTime();
            $dias       = $ahora->diff($fecha_sql)->days;
            $color_dias = ($dias >= 25) ? '#ff4444' : '#00ff00';

            // ID único para el panel de gráfica de este piloto
            $grafica_id = "grafica_" . md5($row['toon_name']);
        ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
            <div class="card pilot-tile shadow" style="border-top: 4px solid <?php echo $color; ?>;">

                <img src="https://images.evetech.net/characters/<?php echo $row['toon_number']; ?>/portrait?size=128"
                     class="portrait-img d-block"
                     style="border-color: <?php echo $color; ?>;"
                     alt="Pilot">

                <div class="card-body p-2 text-center">

                    <!-- Nombre del piloto -->
                    <h6 class="text-truncate mb-0" title="<?php echo $row['toon_name']; ?>">
                        <?php echo $row['toon_name']; ?>
                    </h6>

                    <!-- tradefield debajo del nombre -->
                    <?php if (!empty($row['tradefield'])): ?>
                    <div class="trade-field text-truncate mb-2" title="<?php echo htmlspecialchars($row['tradefield']); ?>">
                        <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($row['tradefield']); ?>
                    </div>
                    <?php else: ?>
                    <div class="mb-2"></div>
                    <?php endif; ?>

                    <!-- SP -->
                    <div class="mb-2">
                        <span class="info-label">Capacidad Mental</span><br>
                        <span class="sp-amount"><?php echo number_format($total_sp, 2); ?>M</span>
                    </div>

                    <!-- Items / Estado -->
                    <div class="row no-gutters border-top border-secondary pt-2">
                        <div class="col-6 border-right border-secondary">
                            <span class="info-label">Items</span><br>
                            <small><?php echo number_format($row['numitems']); ?></small>
                        </div>
                        <div class="col-6">
                            <span class="info-label">Estado</span><br>
                            <small class="days-warning" style="color: <?php echo $color_dias; ?>;">
                                <?php echo $dias; ?>d atrás
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Botón ACTUALIZAR -->
                <div class="card-footer p-0 border-0">
                    <a href="../devauthcallback.php?pilot_id=<?php echo $row['toon_number']; ?>"
                       target="_blank"
                       class="btn btn-block btn-update btn-dark rounded-0"
                       style="border-bottom: 3px solid <?php echo $color; ?>;">
                        <i class="fa fa-sync-alt"></i> ACTUALIZAR
                    </a>

                    <!-- Botón GRÁFICA -->
                    <button type="button"
                            class="btn btn-block btn-graph btn-secondary rounded-0 btn-toggle-graph"
                            data-target="#<?php echo $grafica_id; ?>"
                            style="font-size:0.75rem; border-top: 1px solid #444;">
                        <i class="fas fa-chart-pie mr-1"></i> GRÁFICA
                    </button>
                </div>

                <!-- Panel de gráfica (visible u oculto según $mostrar_graficas_default) -->
                <div id="<?php echo $grafica_id; ?>"
                     class="grafica-container"
                     style="display:<?php echo $mostrar_graficas_default ? 'block' : 'none'; ?>;">
                    <?php echo getPilotSkillGraph($link, $row['toon_name']); ?>
                </div>

            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <hr style="border-color: #444;">
    <div class="text-muted pb-4 small">
        <i class="fa fa-check-circle text-success"></i> Fin del archivo de monitoreo cargado correctamente.
        Ejecutado el: <?php echo date('d/m/Y H:i:s'); ?> |
        Base: <font color='yellow'><b><?php echo mysqli_num_rows($result); ?> Pilotos activos.</b></font>
    </div>

</div><!-- /container-fluid -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Toggle gráfica por piloto ──
document.querySelectorAll('.btn-toggle-graph').forEach(function(btn) {
    var targetId = btn.getAttribute('data-target');
    var panel    = document.querySelector(targetId);

    // Aplicar estado default hardcoded desde PHP
    var defaultVisible = <?php echo $js_graficas_default; ?>;
    if (defaultVisible) {
        panel.style.display = 'block';
        btn.classList.replace('btn-secondary', 'btn-info');
    }

    btn.addEventListener('click', function() {
        if (panel.style.display === 'none') {
            panel.style.display = 'block';
            btn.classList.replace('btn-secondary', 'btn-info');
            // Inicializar chart si aún no se ha hecho
            if (!panel.dataset.chartInit) {
                initChart(panel);
                panel.dataset.chartInit = '1';
            }
        } else {
            panel.style.display = 'none';
            btn.classList.replace('btn-info', 'btn-secondary');
        }
    });

    // Si arranca visible, inicializar chart de inmediato
    if (defaultVisible && !panel.dataset.chartInit) {
        initChart(panel);
        panel.dataset.chartInit = '1';
    }
});

// ── Inicialización de Charts ──
const chartData = <?php echo json_encode($GLOBALS['chart_data'] ?? []); ?>;
const chartColors = ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6610f2'];

function initChart(panel) {
    var canvas = panel.querySelector('canvas');
    if (!canvas) return;
    var id   = canvas.id;
    var data = chartData[id];
    if (!data) return;

    new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: chartColors,
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

// Si default es visible, los charts ya se inicializaron arriba en el forEach
// Si default es false, se inicializan al primer click (lazy init)
</script>
</body>
</html>
