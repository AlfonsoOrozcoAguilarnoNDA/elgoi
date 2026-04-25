<?php
/* 
License GPL 3.0
Alfobnso Orozco Aguilar
*/
require "../config.php";

/**
 * LOGÍSTICA DE INDUSTRIA Y COLONIAS (PI & JOBS)
 * Sección 1: Actividad detectada en planets o jobs
 * Sección 2: gf=1 Y planets=[] (en revisión sin PI activa)
 * Sección 3: gf=1 (todos los personajes en revisión)
 */

// ─── CONSULTA 1: Pilotos con actividad industrial o planetaria ───────────────
$sqlLogistica = "SELECT *, 
                (IFNULL(wallet, 0) / 1000000) as Wallet_M,
                ((skillpoints + IFNULL(unalloc, 0)) / 1000000) as TotalSP_M
                FROM PILOTS 
                WHERE (planets != '[]' AND planets IS NOT NULL)
                   OR (jobs != '[]' AND jobs IS NOT NULL)
                ORDER BY acctype, pocket6 ASC, toon_name ASC";
$resLogistica = mysqli_query($link, $sqlLogistica);

// ─── CONSULTA 2: gf=1 Y planets=[] ──────────────────────────────────────────
$sqlGfNoPlanets = "SELECT *, 
                (IFNULL(wallet, 0) / 1000000) as Wallet_M,
                ((skillpoints + IFNULL(unalloc, 0)) / 1000000) as TotalSP_M
                FROM PILOTS 
                WHERE gf = 1
                  AND (planets = '[]' OR planets IS NULL)
                ORDER BY acctype, pocket6 ASC, toon_name ASC";
$resGfNoPlanets = mysqli_query($link, $sqlGfNoPlanets);

// ─── CONSULTA 3: Todos los gf=1 ──────────────────────────────────────────────
$sqlGf = "SELECT *, 
                (IFNULL(wallet, 0) / 1000000) as Wallet_M,
                ((skillpoints + IFNULL(unalloc, 0)) / 1000000) as TotalSP_M
                FROM PILOTS 
                WHERE gf = 1
                ORDER BY acctype, pocket6 ASC, toon_name ASC";
$resGf = mysqli_query($link, $sqlGf);

// ─── Helper: estilo AccType ──────────────────────────────────────────────────
function getAccTypeStyle($type) {
    $type = strtolower($type);
    if ($type == 'omega') return ['icon' => 'fa-crown',          'color' => '#f1c40f', 'label' => 'OMEGA'];
    if ($type == 'alpha') return ['icon' => 'fa-rocket',         'color' => '#95a5a6', 'label' => 'ALPHA'];
    return                       ['icon' => 'fa-question-circle','color' => '#6c757d', 'label' => 'N/A'];
}

// ─── Helper: renderiza una tarjeta de piloto ─────────────────────────────────
function renderCard($p) {
    $acc      = getAccTypeStyle($p['acctype']);
    $hasPlanets = ($p['planets'] != '[]' && !empty($p['planets']));
    $hasJobs    = ($p['jobs']    != '[]' && !empty($p['jobs']));
    $isGf       = (isset($p['gf']) && $p['gf'] == 1);

    $flagColor  = $isGf ? '#e74c3c' : '#7f8c8d';   // roja o gris
    $flagTitle  = $isGf ? 'En Revisión (GF)' : 'Normal';
    ?>
    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
        <div class="card card-logistica <?php echo $isGf ? 'card-gf' : ''; ?>">
            <div class="card-body p-3">

                <!-- Header: pocket + acctype + bandera -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="pocket-badge"><?php echo htmlspecialchars($p['pocket6'] ?? 'SIN POCKET'); ?></span>
                    <div class="d-flex align-items-center">
                        <!-- Bandera GF -->
                        <i class="fas fa-flag mr-2"
                           style="color:<?php echo $flagColor; ?>; font-size:0.95rem;"
                           title="<?php echo $flagTitle; ?>"></i>
                        <!-- AccType -->
                        <i class="fas <?php echo $acc['icon']; ?>"
                           style="color:<?php echo $acc['color']; ?>"
                           title="<?php echo $acc['label']; ?>"></i>
                    </div>
                </div>

                <!-- Portrait + datos -->
                <div class="d-flex align-items-start">
                    <img src="https://images.evetech.net/characters/<?php echo $p['toon_number']; ?>/portrait?size=128"
                         class="portrait-log mr-3">
                    <div class="flex-grow-1 overflow-hidden">
                        <h6 class="text-white text-truncate mb-0"><?php echo htmlspecialchars($p['toon_name']); ?></h6>
                        <div class="corp-tag text-truncate">
                            <i class="fas fa-building mr-1"></i>
                            <?php echo !empty($p['corporation_name']) ? htmlspecialchars($p['corporation_name']) : 'N/A'; ?>
                        </div>

                        <div class="mt-2 d-flex justify-content-between">
                            <div class="industry-icons">
                                <i class="fas fa-globe-asia mr-2 <?php echo $hasPlanets ? 'text-success' : 'text-dark'; ?>"
                                   title="Planetas Activos"></i>
                                <i class="fas fa-tools <?php echo $hasJobs ? 'text-warning' : 'text-dark'; ?>"
                                   title="Trabajos de Fábrica"></i>
                            </div>
                            <div class="text-right">
                                <small class="d-block text-muted">Evermarks</small>
                                <span class="badge badge-secondary"><?php echo number_format($p['evermarks']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wallet -->
                <div class="mt-3 pt-2 border-top border-secondary d-flex justify-content-between align-items-center">
                    <small class="text-secondary">WALLET BALANCE:</small>
                    <span class="val-money"><?php echo number_format($p['Wallet_M'], 2); ?> M ISK</span>
                </div>

            </div>
        </div>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Logística de Industria - EVE Online</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background-color: #0b0c0e; color: #ced4da; font-family: 'Segoe UI', sans-serif; }

        /* Tarjeta base */
        .card-logistica {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-left: 4px solid #007bff;
            border-radius: 0;
            margin-bottom: 20px;
        }
        /* Tarjeta GF: borde rojo */
        .card-logistica.card-gf {
            border-left-color: #e74c3c;
            background-color: #1e1618;
        }

        .pocket-badge    { background-color: #007bff; color: white; padding: 2px 10px; font-weight: bold; font-size: 0.8rem; text-transform: uppercase; }
        .portrait-log    { width: 70px; height: 70px; border: 1px solid #444; }
        .val-money       { color: #f39c12; font-family: monospace; font-weight: bold; }
        .corp-tag        { color: #5dade2; font-size: 0.8rem; }
        .industry-icons  { font-size: 1.1rem; }

        /* Cabeceras de sección */
        .section-header {
            border-left: 4px solid;
            padding: 8px 16px;
            margin-bottom: 20px;
            background-color: #12141a;
        }
        .section-header.sec-industry { border-color: #007bff; }
        .section-header.sec-gf-nopi  { border-color: #e67e22; }
        .section-header.sec-gf-all   { border-color: #e74c3c; }

        hr.section-divider { border-color: #2c3138; margin: 30px 0; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">

    <!-- ════════════════════════════════════════════════════
         SECCIÓN 1 — Actividad Industrial / Planetaria
    ════════════════════════════════════════════════════ -->
    <div class="section-header sec-industry">
        <h5 class="mb-0 text-white">
            <i class="fas fa-industry mr-2"></i> Auditoría de Producción y Colonias
            <small class="text-muted ml-2" style="font-size:0.75rem;">Pilotos con planets o jobs activos</small>
        </h5>
    </div>

    <div class="row">
        <?php
        if ($resLogistica && mysqli_num_rows($resLogistica) > 0):
            while ($p = mysqli_fetch_assoc($resLogistica)):
                renderCard($p);
            endwhile;
        else:
            echo "<div class='col-12'><p class='alert alert-dark'>No se detectaron pilotos con actividad industrial o planetaria.</p></div>";
        endif;
        ?>
    </div>

    <hr class="section-divider">

    <!-- ════════════════════════════════════════════════════
         SECCIÓN 2 — En Revisión SIN Planetas
    ════════════════════════════════════════════════════ -->
    <div class="section-header sec-gf-nopi">
        <h5 class="mb-0 text-white">
            <i class="fas fa-flag mr-2" style="color:#e74c3c;"></i> En Revisión — Sin Actividad Planetaria
            <small class="text-muted ml-2" style="font-size:0.75rem;">gf=1 y planets=[]</small>
        </h5>
    </div>

    <div class="row">
        <?php
        if ($resGfNoPlanets && mysqli_num_rows($resGfNoPlanets) > 0):
            while ($p = mysqli_fetch_assoc($resGfNoPlanets)):
                renderCard($p);
            endwhile;
        else:
            echo "<div class='col-12'><p class='alert alert-dark'>No hay pilotos en revisión sin actividad planetaria.</p></div>";
        endif;
        ?>
    </div>

    <hr class="section-divider">

    <!-- ════════════════════════════════════════════════════
         SECCIÓN 3 — Todos los Pilotos en Revisión (gf=1)
    ════════════════════════════════════════════════════ -->
    <div class="section-header sec-gf-all">
        <h5 class="mb-0 text-white">
            <i class="fas fa-exclamation-triangle mr-2" style="color:#e74c3c;"></i> Todos los Pilotos en Revisión
            <small class="text-muted ml-2" style="font-size:0.75rem;">gf=1 (sin filtro adicional)</small>
        </h5>
    </div>

    <div class="row">
        <?php
        if ($resGf && mysqli_num_rows($resGf) > 0):
            while ($p = mysqli_fetch_assoc($resGf)):
                renderCard($p);
            endwhile;
        else:
            echo "<div class='col-12'><p class='alert alert-dark'>No hay pilotos marcados en revisión.</p></div>";
        endif;
        ?>
    </div>

</div><!-- /container -->

</body>
</html>
