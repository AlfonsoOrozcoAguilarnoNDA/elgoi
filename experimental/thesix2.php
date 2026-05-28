<?php
/**
 * EVE Pilot Skills Dashboard - Vibecoding Edition v2
 * Stack: PHP 8.x Procedural, MariaDB, Bootstrap 4.6.x, FontAwesome 5.15.4
 */
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Mosaic Dashboard
 * Fecha: 2026-03-31 00:22
 * 
 * This is A second experimental dashboard, using some of my test chars. Use yours.
 */

// 1. CONFIGURACIÓN Y CONEXIÓN
include "../config.php"; 

if (!$link) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($link, "utf8mb4");

// 2. PROCESAMIENTO DE LA CADENA (Corregido: Sue Rtuda)
$cadena = "Aridam, Hypervisor, Woo Soo-ji, Sue Rtuda, R1h net";
$nombres_array = array_map('trim', explode(',', $cadena));
$lista_para_sql = "'" . implode("','", $nombres_array) . "'";

// 3. CONSULTA PRINCIPAL
$sql = "SELECT p.toon_number, p.toon_name, p.pocket6, p.skillpoints as total_sp
        FROM PILOTS p
        WHERE p.toon_name IN ($lista_para_sql)
        ORDER BY FIELD(p.toon_name, $lista_para_sql)";

$res = mysqli_query($link, $sql);

/**
 * Funciones auxiliares
 */
function getSkillLevel($link, $toon, $skill_name) {
    $skill_name = mysqli_real_escape_string($link, $skill_name);
    $q = "SELECT rank FROM EVE_CHARSKILLS 
          WHERE toon = $toon AND Description = '$skill_name' LIMIT 1";
    $r = mysqli_query($link, $q);
    $row = mysqli_fetch_assoc($r);
    return $row ? $row['rank'] : 0;
}

function getGroupSP($link, $toon, $group_name) {
    $group_name = mysqli_real_escape_string($link, $group_name);
    $q = "SELECT SUM(skillpoints) as total FROM EVE_CHARSKILLS 
          WHERE toon = $toon AND group_name LIKE '%$group_name%'";
    $r = mysqli_query($link, $q);
    $row = mysqli_fetch_assoc($r);
    return $row ? $row['total'] : 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vibecoding: EVE Abyssal Ready Check</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body { background-color: #0f0f0f; color: #d1d1d1; }
        .card-pilot { background-color: #1a1a1a; border: 1px solid #333; }
        .skill-grid { font-size: 0.65rem; border-collapse: collapse; width: 100%; }
        .skill-grid td, .skill-grid th { border: 1px solid #333; padding: 3px; text-align: center; }
        .skill-name { color: #888; text-transform: uppercase; font-size: 0.55rem; }
        .level-v { color: #00ff00; font-weight: bold; }
        .pocket-badge { position: absolute; top: 10px; right: 10px; z-index: 10; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <?php while($p = mysqli_fetch_assoc($res)): 
            $t = $p['toon_number'];
            // Skills de Turret
            $hybrid = getSkillLevel($link, $t, 'Small Hybrid Turret');
            $proj = getSkillLevel($link, $t, 'Small Projectile Turret');
            $energy = getSkillLevel($link, $t, 'Small Energy Turret');
            // Skills de Destroyer Específicos
            $minmD = getSkillLevel($link, $t, 'Minmatar Destroyer');
            $caldD = getSkillLevel($link, $t, 'Caldari Destroyer');
            $gallD = getSkillLevel($link, $t, 'Gallente Destroyer');
            // Amarr Frigate y Misiles
            $amarrF = getSkillLevel($link, $t, 'Amarr Frigate');
            $missileSP = getGroupSP($link, $t, 'Missile');
        ?>
            <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                <div class="card card-pilot h-100">
                    <span class="badge badge-info pocket-badge"><?php echo htmlspecialchars($p['pocket6']); ?></span>
                    <img src="https://images.evetech.net/characters/<?php echo $t; ?>/portrait?size=256" class="card-img-top" alt="Avatar">
                    
                    <div class="card-body p-2">
                        <h6 class="text-info text-truncate"><?php echo $p['toon_name']; ?></h6>
                        
                        <table class="skill-grid mb-2">
                            <thead>
                                <tr>
                                    <th class="skill-name">Hybrid</th>
                                    <th class="skill-name">Proj</th>
                                    <th class="skill-name">Energy</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="<?php echo ($hybrid == 5 ? 'level-v' : ''); ?>"><?php echo ($hybrid ?: '-'); ?></td>
                                    <td class="<?php echo ($proj == 5 ? 'level-v' : ''); ?>"><?php echo ($proj ?: '-'); ?></td>
                                    <td class="<?php echo ($energy == 5 ? 'level-v' : ''); ?>"><?php echo ($energy ?: '-'); ?></td>
                                </tr>
                            </tbody>
                            <thead>
                                <tr>
                                    <th class="skill-name">Minm Dest</th>
                                    <th class="skill-name">Cald Dest</th>
                                    <th class="skill-name">Gall Dest</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="<?php echo ($minmD == 5 ? 'level-v' : ''); ?>"><?php echo ($minmD ?: '-'); ?></td>
                                    <td class="<?php echo ($caldD == 5 ? 'level-v' : ''); ?>"><?php echo ($caldD ?: '-'); ?></td>
                                    <td class="<?php echo ($gallD == 5 ? 'level-v' : ''); ?>"><?php echo ($gallD ?: '-'); ?></td>
                                </tr>
                            </tbody>
                            <thead>
                                <tr>
                                    <th colspan="3" class="skill-name">Amarr Frigate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" class="<?php echo ($amarrF == 5 ? 'level-v' : ''); ?>"><?php echo ($amarrF ?: '-'); ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <?php if($missileSP > 0): ?>
                            <div class="small text-warning border border-warning p-1 text-center">
                                <i class="fas fa-crosshairs"></i> MISSILES: <?php echo number_format($missileSP); ?> SP
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>
