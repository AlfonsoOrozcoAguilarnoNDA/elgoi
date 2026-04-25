<?php
require "../config.php";
/**
 * AUDITORÍA MAESTRA: ENTRENAMIENTO + PANEL + TRADEFIELD
 * Versión: Corregida (tradefield) 2026-03
 */

// 1. MANTENIMIENTO E INICIALIZACIÓN
mysqli_query($link, "UPDATE PILOTS SET finishqueue = NULL WHERE finishqueue = '0000-00-00 00:00:00'");

/**
 * LÓGICA DE DETECCIÓN DE PROFESIÓN (tradefield)
 */
function obtenerOficio($p) {
    global $link;
    
    $toon_name = mysqli_real_escape_string($link, $p['toon_name']);
    
    // Buscamos el group_name con la sumatoria de SP más alta para este piloto
    // Asumiendo que la tabla se llama 'EVE_CHARSKILLS' (ajustar si tiene otro nombre)
    $sqlSkills = "SELECT group_name, SUM(skillpoints) as total_group_sp 
                  FROM EVE_CHARSKILLS 
                  WHERE toon_name = '$toon_name' 
                  GROUP BY group_name 
                  ORDER BY total_group_sp DESC 
                  LIMIT 1";
                  
    $res = mysqli_query($link, $sqlSkills);
    
    if ($res && mysqli_num_rows($res) > 0) {
        $dato = mysqli_fetch_assoc($res);
        return $dato['group_name']; // Retorna la profesión real basada en entrenamiento
    }

    // Fallback por si no tiene skills registradas aún
    $sp = (int)($p['skillpoints'] / 1000000);
    if ($sp < 10) return "n/a";
    
    return "Especialista Independiente";
}
// 2. CONSULTA CON CRUCE A PANELS
$sqlMaster = "SELECT P.*, 
             ((P.skillpoints + IFNULL(P.unalloc, 0)) / 1000000) as TotalSP_M,
             PAN.manualExpiration
             FROM PILOTS P
             LEFT JOIN PANELS PAN ON (P.toon_name = PAN.name_1 OR P.toon_name = PAN.name_2 OR P.toon_name = PAN.name_3)
             ORDER BY P.finishqueue DESC, P.toon_name ASC";

$resMaster = mysqli_query($link, $sqlMaster);

// Funciones de apoyo visual
function getPanelStatus($expiration) {
    if (empty($expiration) || $expiration == '0000-00-00') return '<span style="color: #ff4d4d; font-weight: bold;">SIN PANEL</span>';
    $hoy = new DateTime(date('Y-m-d'));
    $vence = new DateTime($expiration);
    if ($vence < $hoy) return '<span class="text-secondary">n/a</span>';
    return '<span class="text-white font-weight-bold">' . $hoy->diff($vence)->format('%a') . ' D</span>';
}

function getBirreteStyle($finishQueue, $planets) {
    $ahora = date('Y-m-d H:i:s');
    if (!empty($finishQueue) && $finishQueue > $ahora) return 'text-success'; 
    if (empty($planets) || $planets == '[]') return 'text-secondary';
    return 'text-warning';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE - Master Audit (Tradefield)</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #0b0c0e; color: #ced4da; font-family: 'Segoe UI', sans-serif; }
        .table-eve { background-color: #16191c; font-size: 0.82rem; border-collapse: separate; border-spacing: 0; }
        .table-eve thead th { background-color: #212529; border-bottom: 2px solid #007bff; position: sticky; top: 0; z-index: 10; }
        .table-eve td { vertical-align: middle; border-top: 1px solid #2d3238; }
        .portrait-mini { width: 32px; height: 32px; border: 1px solid #444; }
        .text-sp { color: #5dade2; font-family: 'Courier New', monospace; font-weight: bold; }
        .trade-tag { color: #bb86fc; font-weight: 500; letter-spacing: 0.5px; }
        .col-panel { background-color: rgba(255, 255, 255, 0.02); border-left: 1px solid #333; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <h4 class="mb-4 text-white"><i class="fas fa-microscope mr-2"></i> Auditoría Estratégica: <span class="text-primary">Tradefield & Queue</span></h4>

    <div class="table-responsive">
        <table class="table table-eve table-hover table-dark">
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Piloto</th>
                    <th class="text-right">SP (Millones)</th>
                    <th class="text-center">Acc</th>
                    <th class="text-center">Oficio (Tradefield)</th>
                    <th>Finaliza Queue</th>
                    <th class="text-center">Días Cola</th>
                    <th class="text-center col-panel">Días Panel</th>
                    <th class="text-center">Icons</th>
                    <th>Pocket</th>
                    <th class="text-right">Evermarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $contador = 1;
                $ahora = new DateTime();
                
                while ($p = mysqli_fetch_assoc($resMaster)): 
                    // Detección y persistencia de tradefield
                    $oficio = obtenerOficio($p);
					
					
                    //if ($p['tradefield'] == 'n/a') {
                        $safeTrade = mysqli_real_escape_string($link, $oficio);
                        mysqli_query($link, "UPDATE PILOTS SET tradefield = '$safeTrade' WHERE toon_name = '".mysqli_real_escape_string($link, $p['toon_name'])."'");
                    //}

                    $diasCola = "---";
                    if (!empty($p['finishqueue'])) {
                        $fTermino = new DateTime($p['finishqueue']);
                        $diasCola = ($fTermino > $ahora) ? $ahora->diff($fTermino)->format('%a d') : '<span class="text-danger">0 d</span>';
                    }
                ?>
                <tr>
                    <td class="text-center text-muted"><?php echo $contador++; ?></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=64" class="portrait-mini mr-2">
                            <strong><?php echo htmlspecialchars($p['toon_name']); ?></strong>
                        </div>
                    </td>
                    
                    <td class="text-right text-sp"><?php echo number_format($p['TotalSP_M'], 2); ?> M</td>
                    
                    <td class="text-center">
                        <?php echo (strtolower($p['acctype']) == 'omega') ? '<i class="fas fa-crown text-warning"></i>' : '<i class="fas fa-rocket text-secondary"></i>'; ?>
                    </td>

                    <td class="text-center trade-tag">
                        <?php echo htmlspecialchars($oficio); ?>
                    </td>

                    <td><?php echo !empty($p['finishqueue']) ? date('d/m/H:i', strtotime($p['finishqueue'])) : '<span class="text-muted">N/A</span>'; ?></td>
                    
                    <td class="text-center font-weight-bold"><?php echo $diasCola; ?></td>
                    
                    <td class="text-center col-panel">
                        <?php echo getPanelStatus($p['manualExpiration']); ?>
                    </td>

                    <td class="text-center" style="font-size: 1.1rem;">
                        <i class="fas fa-globe-asia mx-1 <?php echo ($p['planets'] != '[]') ? 'text-success' : 'text-dark'; ?>"></i>
                        <i class="fas fa-tools mx-1 <?php echo ($p['jobs'] != '[]') ? 'text-warning' : 'text-dark'; ?>"></i>
                        <i class="fas fa-graduation-cap mx-1 <?php echo getBirreteStyle($p['finishqueue'], $p['planets']); ?>"></i>
                    </td>
                    
                    <td><small class="text-info"><?php echo htmlspecialchars($p['pocket6'] ?? 'N/A'); ?></small></td>
                    
                    <td class="text-right">
                        <?php echo number_format($p['evermarks']); ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
