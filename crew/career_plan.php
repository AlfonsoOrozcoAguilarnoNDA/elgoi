<?php
/* 
License MIT
Alfonso Orozco Aguilar
*/
require "../config.php";
/**
 * PERFIL DE CARRERA Y RADAR DE TALENTO PARA AUDITORÍA ESTRATÉGICA
 * Compara un piloto contra toda la flota para identificar jerarquías técnicas.
 * Comparación por: Description (Habilidad específica)
 * Estricto: Colaborador solo si SkillPoints de DESC es MAYOR (>) al Base. 
 * Incluye: Foto de Referencia y Contadores de Segmentación
 */

// 1. SELECTOR DE PILOTOS
$resToons = mysqli_query($link, "SELECT toon_name FROM PILOTS ORDER BY toon_name ASC");
$selectedToon = $_POST['target_toon'] ?? null;

$basePilot = null;
$subordinados = [];
$colaboradores = [];
$superiores = [];

if ($selectedToon) {
    // 2. DATOS DEL PILOTO BASE
    $sqlBase = "SELECT *, (skillpoints + IFNULL(unalloc, 0)) as total_sp 
                FROM PILOTS 
                WHERE toon_name = '".mysqli_real_escape_string($link, $selectedToon)."'";
    $basePilot = mysqli_fetch_assoc(mysqli_query($link, $sqlBase));
    
    // Mapeo de habilidades base
    $baseSkills = [];
    $qSkills = mysqli_query($link, "SELECT Description, skillpoints FROM EVE_CHARSKILLS WHERE toon_name = '".$basePilot['toon_name']."'");
    while($sk = mysqli_fetch_assoc($qSkills)) { 
        $baseSkills[$sk['Description']] = $sk['skillpoints']; 
    }

    // 3. COMPARATIVA CONTRA LA FLOTA
    $sqlOthers = "SELECT *, (skillpoints + IFNULL(unalloc, 0)) as total_sp 
                  FROM PILOTS 
                  WHERE toon_name != '".$basePilot['toon_name']."'";
    $resOthers = mysqli_query($link, $sqlOthers);

    while ($other = mysqli_fetch_assoc($resOthers)) {
        $diffPercent = (($other['total_sp'] - $basePilot['total_sp']) / $basePilot['total_sp']) * 100;
        $other['diff'] = round($diffPercent, 1);

        // MENTORES
        if ($other['total_sp'] > $basePilot['total_sp']) {
            $superiores[] = $other;
            continue;
        }

        // ESPECIALISTAS (Colaboradores)
        $ventajas = [];
        $qOtherSkills = mysqli_query($link, "SELECT Description, skillpoints FROM EVE_CHARSKILLS WHERE toon_name = '".$other['toon_name']."'");
        while($osk = mysqli_fetch_assoc($qOtherSkills)) {
            $baseVal = $baseSkills[$osk['Description']] ?? 0;
            if ($osk['skillpoints'] > $baseVal) {
                $ventajas[] = [
                    'habilidad' => $osk['Description'],
                    'pts_extra' => $osk['skillpoints'] - $baseVal
                ];
            }
        }

        if (count($ventajas) > 0) {
            usort($ventajas, function($a, $b) { return $b['pts_extra'] <=> $a['pts_extra']; });
            $other['ventajas'] = array_slice($ventajas, 0, 5);
            $colaboradores[] = $other;
        } else {
            $subordinados[] = $other;
        }
    }

    usort($superiores, function($a, $b) { return $b['total_sp'] <=> $a['total_sp']; });
    $superiores = array_slice($superiores, 0, 10);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Carrera - Auditoría Final</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #0b0c0e; color: #ced4da; font-family: 'Segoe UI', sans-serif; }
        .header-panel { background: #16191c; border-bottom: 3px solid #007bff; padding: 25px; margin-bottom: 30px; border-radius: 4px; }
        .section-label { font-size: 0.95rem; font-weight: bold; border-left: 5px solid #444; padding-left: 15px; margin: 40px 0 20px; text-transform: uppercase; }
        .badge-count { background: #333; color: #fff; padding: 2px 10px; border-radius: 20px; font-size: 0.8rem; margin-left: 10px; border: 1px solid #555; }
        
        .card-talent { background: #1a1d21; border: 1px solid #333; margin-bottom: 12px; }
        .card-mentor { border-left: 4px solid #5dade2; }
        .card-specialist { border-left: 4px solid #f39c12; }
        .card-support { border-left: 4px solid #28a745; opacity: 0.85; }
        
        .portrait-base { width: 80px; height: 80px; border: 2px solid #007bff; box-shadow: 0 0 15px rgba(0,123,255,0.3); }
        .portrait-lg { width: 55px; height: 55px; border: 1px solid #222; }
        .skill-chip { background: rgba(243, 156, 18, 0.1); color: #f39c12; border: 1px solid rgba(243, 156, 18, 0.2); font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; display: inline-block; margin: 2px; }
        .sp-num { font-family: 'Courier New', monospace; font-weight: bold; color: #5dade2; }
    </style>
</head>
<body>

<div class="container-fluid p-4">
    <div class="header-panel">
        <div class="row align-items-center">
            <div class="col-md-5">
                <form method="POST" id="selectorForm">
                    <label class="small text-muted font-weight-bold">PILOTO DE REFERENCIA:</label>
                    <select name="target_toon" class="form-control bg-dark text-white border-secondary" onchange="document.getElementById('selectorForm').submit()">
                        <option value="">Seleccione Piloto...</option>
                        <?php mysqli_data_seek($resToons, 0); while($t = mysqli_fetch_assoc($resToons)): ?>
                            <option value="<?php echo $t['toon_name']; ?>" <?php echo ($selectedToon == $t['toon_name']) ? 'selected' : ''; ?>>
                                <?php echo $t['toon_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            <?php if ($basePilot): ?>
            <div class="col-md-7 d-flex align-items-center justify-content-end">
                <div class="text-right mr-3">
                    <h2 class="mb-0 text-white font-weight-bold"><?php echo strtoupper($basePilot['toon_name']); ?></h2>
                    <div class="sp-num text-uppercase small"><?php echo number_format($basePilot['total_sp']/1000000, 2); ?>M SP ACUMULADOS</div>
                </div>
                <img src="https://images.evetech.net/characters/<?php echo $basePilot['toon_number']; ?>/portrait?size=256" class="portrait-base rounded">
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedToon): ?>

    <div class="section-label text-info">
        <i class="fas fa-chevron-up mr-2"></i> Mentores Potenciales
        <span class="badge-count"><?php echo count($superiores); ?></span>
    </div>
    <div class="row">
        <?php foreach($superiores as $p): ?>
        <div class="col-md-3">
            <div class="card card-talent card-mentor p-2 d-flex flex-row align-items-center">
                <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=128" class="portrait-lg mr-3">
                <div class="overflow-hidden">
                    <div class="text-white text-truncate font-weight-bold small"><?php echo $p['toon_name']; ?></div>
                    <div class="small text-info font-weight-bold">+<?php echo abs($p['diff']); ?>% Eficiencia</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-label text-warning">
        <i class="fas fa-star mr-2"></i> Colaboradores Especialistas 
        <span class="badge-count text-warning"><?php echo count($colaboradores); ?> registrados</span>
    </div>
    <div class="row">
        <?php foreach($colaboradores as $p): ?>
        <div class="col-md-4">
            <div class="card card-talent card-specialist p-3">
                <div class="d-flex align-items-center mb-2">
                    <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=128" class="portrait-lg mr-3 shadow-sm">
                    <div>
                        <div class="text-white font-weight-bold"><?php echo $p['toon_name']; ?></div>
                        <div class="small text-warning font-weight-bold"><?php echo $p['diff']; ?>% SP Total</div>
                    </div>
                </div>
                <div class="pt-2 border-top border-secondary">
                    <?php foreach($p['ventajas'] as $v): ?>
                        <span class="skill-chip"><?php echo $v['habilidad']; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-label text-success">
        <i class="fas fa-users mr-2"></i> Flota de Apoyo / Subordinados 
        <span class="badge-count text-success"><?php echo count($subordinados); ?> unidades</span>
    </div>
    <div class="row">
        <?php foreach($subordinados as $p): ?>
        <div class="col-md-2 col-6">
            <div class="card card-talent card-support p-2 mb-2 d-flex flex-row align-items-center justify-content-between">
                <div class="overflow-hidden">
                    <div class="text-white small text-truncate"><?php echo $p['toon_name']; ?></div>
                    <div class="text-success small font-weight-bold"><?php echo $p['diff']; ?>%</div>
                </div>
                <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=64" style="width:28px; height:28px;" class="rounded-circle">
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

</body>
</html>
