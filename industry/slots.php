<?php
// ============================================
// EVE ONLINE - PRODUCTION SLOTS DASHBOARD
// PHP 8.x Procedural | Bootstrap 4.6 | Font Awesome 5.4.15
// Alfonso Orozco Aguilar
// Contacto en eve: existe como piloto
// kimi 2.6 plus chat
// Fecha: 2026-05-28
// Licencia: GPL
// ============================================

include 'config.php';

// Verificar conexión
if (!isset($link) || $link === false) {
    die("Error: No hay conexión a la base de datos.");
}

// ============================================
// CONSTANTES DE SKILLS
// ============================================
define('MASS_PRODUCTION_TYPEID', 3387);
define('ADVANCED_MASS_PRODUCTION_TYPEID', 24625);
define('MAX_SLOTS', 11);

// ============================================
// FUNCIONES ESI API (sin token)
// ============================================

/**
 * Hace una petición GET a la ESI API de EVE Online
 */
function esi_get(string $endpoint): ?array {
    $url = 'https://esi.evetech.net/latest' . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'EVE-ProductionDashboard/1.0 (contact@example.com)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response !== false) {
        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
    }
    
    return null;
}

/**
 * Obtiene el corporation_id de un personaje
 */
function get_character_corp(int $character_id): ?int {
    $data = esi_get('/characters/' . $character_id . '/');
    return isset($data['corporation_id']) ? (int)$data['corporation_id'] : null;
}

/**
 * Obtiene el ceo_id de una corporación
 */
function get_corp_ceo(int $corporation_id): ?int {
    $data = esi_get('/corporations/' . $corporation_id . '/');
    return isset($data['ceo_id']) ? (int)$data['ceo_id'] : null;
}

/**
 * Verifica si un personaje es CEO de su corporación
 * Usa caché en sesión para no saturar la API
 */
function is_ceo(int $character_id): bool {
    $cache_key = 'ceo_check_' . $character_id;
    
    // Si ya está en caché de sesión, devolver
    if (isset($_SESSION[$cache_key])) {
        return $_SESSION[$cache_key];
    }
    
    // Obtener corporación del personaje
    $corp_id = get_character_corp($character_id);
    if ($corp_id === null) {
        $_SESSION[$cache_key] = false;
        return false;
    }
    
    // Obtener CEO de la corporación
    $ceo_id = get_corp_ceo($corp_id);
    if ($ceo_id === null) {
        $_SESSION[$cache_key] = false;
        return false;
    }
    
    // Comparar
    $is_ceo = ($ceo_id === $character_id);
    $_SESSION[$cache_key] = $is_ceo;
    
    // Pequeña pausa para respetar rate limits (sin token = 20 req/s)
    usleep(100000); // 0.1 segundos = max 10 req/s (conservador)
    
    return $is_ceo;
}

// Iniciar sesión para caché
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// OBTENER FILTROS DINÁMICOS
// ============================================

// Pocket6
$pockets = [];
$res = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 IS NOT NULL AND pocket6 != '' ORDER BY pocket6");
while ($row = mysqli_fetch_assoc($res)) {
    $pockets[] = $row['pocket6'];
}

// Account Type
$acctypes = [];
$res = mysqli_query($link, "SELECT DISTINCT acctype FROM PILOTS WHERE acctype IS NOT NULL AND acctype != '' ORDER BY acctype");
while ($row = mysqli_fetch_assoc($res)) {
    $acctypes[] = $row['acctype'];
}

// Tradefield
$tradefields = [];
$res = mysqli_query($link, "SELECT DISTINCT tradefield FROM PILOTS WHERE tradefield IS NOT NULL AND tradefield != '' ORDER BY tradefield");
while ($row = mysqli_fetch_assoc($res)) {
    $tradefields[] = $row['tradefield'];
}

// ============================================
// FILTROS APLICADOS (GET)
// ============================================
$filtro_pocket = isset($_GET['pocket6']) ? $_GET['pocket6'] : 'TODOS';
$filtro_acctype = isset($_GET['acctype']) ? $_GET['acctype'] : 'TODOS';
$filtro_tradefield = isset($_GET['tradefield']) ? $_GET['tradefield'] : 'TODOS';

// ============================================
// CONSTRUIR QUERY PRINCIPAL
// ============================================
// CORREGIDO: Sin COALESCE para distinguir NULL (no tiene skill) de 0 (nivel 0)
$sql = "
    SELECT 
        p.toon_number,
        p.toon_name,
        p.pocket6,
        p.acctype,
        p.tradefield,
        p.skillpoints,
        p.unalloc,
        (p.skillpoints + p.unalloc) / 1000000 AS sp_millones,
        mp.skill_level AS mass_prod_level,
        amp.skill_level AS adv_mass_prod_level
    FROM PILOTS p
    LEFT JOIN (
        SELECT toon, rank AS skill_level 
        FROM EVE_CHARSKILLS 
        WHERE typeID = " . MASS_PRODUCTION_TYPEID . "
    ) mp ON p.toon_number = mp.toon
    LEFT JOIN (
        SELECT toon, rank AS skill_level 
        FROM EVE_CHARSKILLS 
        WHERE typeID = " . ADVANCED_MASS_PRODUCTION_TYPEID . "
    ) amp ON p.toon_number = amp.toon
    WHERE 1=1
";

// Aplicar filtros
if ($filtro_pocket !== 'TODOS') {
    $safe_pocket = mysqli_real_escape_string($link, $filtro_pocket);
    $sql .= " AND p.pocket6 = '$safe_pocket'";
}

if ($filtro_acctype !== 'TODOS') {
    $safe_acctype = mysqli_real_escape_string($link, $filtro_acctype);
    $sql .= " AND p.acctype = '$safe_acctype'";
}

if ($filtro_tradefield !== 'TODOS') {
    $safe_tradefield = mysqli_real_escape_string($link, $filtro_tradefield);
    $sql .= " AND p.tradefield = '$safe_tradefield'";
}

// Ordenar por SP descendente
$sql .= " ORDER BY sp_millones DESC";

$result = mysqli_query($link, $sql);

// ============================================
// PROCESAR RESULTADOS Y DETECTAR CEOs
// ============================================

$total_pilotos = 0;
$total_slots = 0;
$max_slots_pilot = 0;
$pilotos_con_slots = [];
$ceos_list = []; // Array para almacenar CEOs

if ($result) {
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        
        // ============================================
        // CORREGIDO: Lógica de slots con distinción NULL vs 0
        // ============================================
        $mass_level = $row['mass_prod_level'];     // NULL = no tiene, 0-5 = tiene
        $adv_level = $row['adv_mass_prod_level'];  // NULL = no tiene, 0-5 = tiene
        
        // Si no tiene Mass Production (NULL), no puede tener Advanced
        if ($mass_level === null) {
            $mass_level = null;
            $adv_level = null; // Forzar NULL aunque la DB diga otra cosa (prerrequisito no cumplido)
        }
        
        // Calcular slots: base 1 + Mass Production (0-5) + Advanced Mass Production (0-5)
        // Solo sumar si tiene la skill (no es NULL)
        $mass_bonus = ($mass_level !== null) ? (int)$mass_level : 0;
        $adv_bonus = ($adv_level !== null) ? (int)$adv_level : 0;
        
        $slots = 1 + $mass_bonus + $adv_bonus;
        $slots = min($slots, MAX_SLOTS);
        
        // Verificar si es CEO (solo si hay toon_number válido)
        $es_ceo = false;
        if (!empty($row['toon_number']) && is_numeric($row['toon_number'])) {
            $es_ceo = is_ceo((int)$row['toon_number']);
        }
        
        $piloto_data = array_merge($row, [
            'slots' => $slots,
            'es_ceo' => $es_ceo,
            'mass_prod_level' => $mass_level,     // Preservar NULL
            'adv_mass_prod_level' => $adv_level   // Preservar NULL
        ]);
        
        $total_pilotos++;
        $total_slots += $slots;
        
        if ($slots > $max_slots_pilot) {
            $max_slots_pilot = $slots;
        }
        
        $pilotos_con_slots[] = $piloto_data;
        
        // Si es CEO, añadir a la lista
        if ($es_ceo) {
            $ceos_list[] = $piloto_data;
        }
    }
}

// Ordenar CEOs alfabéticamente por nombre
usort($ceos_list, function($a, $b) {
    return strcasecmp($a['toon_name'], $b['toon_name']);
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online - Production Slots Dashboard</title>
    
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 5.4.15 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    
    <style>
        body {
            background-color: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar {
            background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
            border-bottom: 2px solid #e94560;
        }
        .card {
            background-color: #16213e;
            border: 1px solid #0f3460;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .card-header {
            background: linear-gradient(135deg, #0f3460 0%, #16213e 100%);
            border-bottom: 1px solid #e94560;
            color: #fff;
        }
        .table {
            color: #e0e0e0;
        }
        .table thead th {
            background-color: #0f3460;
            border-color: #1a1a2e;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            border-color: #1a1a2e;
        }
        .table tbody tr:hover {
            background-color: #0f3460;
        }
        .table td {
            border-color: #1a1a2e;
            vertical-align: middle;
        }
        .badge-slot {
            font-size: 1.1rem;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: bold;
        }
        .badge-slot-max {
            background-color: #28a745;
            color: #fff;
        }
        .badge-slot-high {
            background-color: #17a2b8;
            color: #fff;
        }
        .badge-slot-mid {
            background-color: #ffc107;
            color: #000;
        }
        .badge-slot-low {
            background-color: #dc3545;
            color: #fff;
        }
        .sp-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #17a2b8;
        }
        /* ============================================
           CORREGIDO: Estilos de skills con 3 estados
           ============================================ */
        .skill-level {
            display: inline-block;
            width: 28px;
            height: 28px;
            line-height: 28px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.85rem;
            margin: 0 2px;
        }
        .skill-active {
            background-color: #28a745;
            color: #fff;
        }
        .skill-inactive {
            background-color: #6c757d;
            color: #fff;
        }
        /* NUEVO: Skill no poseída (NULL) - gris más oscuro */
        .skill-null {
            background-color: #3a3a4a;
            color: #6c757d;
            border: 1px dashed #555;
        }
        .form-control {
            background-color: #0f3460;
            border-color: #1a1a2e;
            color: #e0e0e0;
        }
        .form-control:focus {
            background-color: #16213e;
            border-color: #e94560;
            color: #e0e0e0;
            box-shadow: 0 0 0 0.2rem rgba(233, 69, 96, 0.25);
        }
        .btn-filter {
            background-color: #e94560;
            border-color: #e94560;
            color: #fff;
        }
        .btn-filter:hover {
            background-color: #c73e54;
            border-color: #c73e54;
            color: #fff;
        }
        .omega-badge {
            background-color: #ffd700;
            color: #000;
        }
        .maybe-badge {
            background-color: #ff8c00;
            color: #000;
        }
        .alpha-badge {
            background-color: #6c757d;
            color: #fff;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #e94560;
        }
        .stats-label {
            font-size: 0.85rem;
            color: #a0a0a0;
            text-transform: uppercase;
        }
        /* Badge CEO */
        .ceo-badge {
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            color: #000;
            font-weight: bold;
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 4px;
            margin-left: 5px;
            box-shadow: 0 2px 4px rgba(255, 215, 0, 0.3);
        }
        /* Tarjeta de CEOs */
        .ceo-card {
            border-left: 4px solid #ffd700;
        }
        .ceo-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd700 0%, #ff8c00 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-weight: bold;
            margin-right: 10px;
        }
        /* NUEVO: Tooltip para skill no poseída */
        .skill-null-text {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-industry"></i> EVE Online - Production Slots Dashboard
            </a>
            <span class="navbar-text text-light">
                <i class="far fa-calendar-alt"></i> <?php echo date('d M Y'); ?>
            </span>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filtros
            </div>
            <div class="card-body">
                <form method="GET" action="" class="form-inline">
                    
                    <!-- Pocket6 -->
                    <div class="form-group mr-3 mb-2">
                        <label for="pocket6" class="mr-2"><i class="fas fa-user-secret"></i> Pocket:</label>
                        <select name="pocket6" id="pocket6" class="form-control">
                            <option value="TODOS" <?php echo ($filtro_pocket === 'TODOS') ? 'selected' : ''; ?>>-- TODOS --</option>
                            <?php foreach ($pockets as $p): ?>
                                <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($filtro_pocket === $p) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Account Type -->
                    <div class="form-group mr-3 mb-2">
                        <label for="acctype" class="mr-2"><i class="fas fa-id-card"></i> Account Type:</label>
                        <select name="acctype" id="acctype" class="form-control">
                            <option value="TODOS" <?php echo ($filtro_acctype === 'TODOS') ? 'selected' : ''; ?>>-- TODOS --</option>
                            <?php foreach ($acctypes as $a): ?>
                                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo ($filtro_acctype === $a) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($a); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tradefield -->
                    <div class="form-group mr-3 mb-2">
                        <label for="tradefield" class="mr-2"><i class="fas fa-briefcase"></i> Trade Field:</label>
                        <select name="tradefield" id="tradefield" class="form-control">
                            <option value="TODOS" <?php echo ($filtro_tradefield === 'TODOS') ? 'selected' : ''; ?>>-- TODOS --</option>
                            <?php foreach ($tradefields as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($filtro_tradefield === $t) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-filter mb-2">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    
                    <a href="?" class="btn btn-outline-secondary mb-2 ml-2">
                        <i class="fas fa-undo"></i> Limpiar
                    </a>
                </form>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- SECCIÓN CEOs - LISTA ALFABÉTICA -->
        <!-- ============================================ -->
        <?php if (!empty($ceos_list)): ?>
        <div class="card mb-4 ceo-card">
            <div class="card-header">
                <i class="fas fa-crown text-warning"></i> 
                <strong>Directores Ejecutivos (CEOs)</strong>
                <span class="badge badge-warning ml-2"><?php echo count($ceos_list); ?></span>
                <small class="float-right text-muted">
                    <i class="fas fa-info-circle"></i> Datos obtenidos de ESI API
                </small>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($ceos_list as $ceo): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="d-flex align-items-center p-2" style="background: rgba(255,215,0,0.1); border-radius: 8px; border: 1px solid rgba(255,215,0,0.3);">
                            <div class="ceo-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <div class="font-weight-bold text-warning">
                                    <?php echo htmlspecialchars($ceo['toon_name']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-user-secret"></i> <?php echo htmlspecialchars($ceo['pocket6']); ?> | 
                                    <i class="fas fa-industry"></i> <?php echo $ceo['slots']; ?> slots
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $total_pilotos; ?></div>
                    <div class="stats-label">Pilotos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $total_slots; ?></div>
                    <div class="stats-label">Total Slots</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo ($total_pilotos > 0) ? round($total_slots / $total_pilotos, 1) : 0; ?></div>
                    <div class="stats-label">Slots Promedio</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $max_slots_pilot; ?></div>
                    <div class="stats-label">Max Slots (1 piloto)</div>
                </div>
            </div>
        </div>

        <!-- Tabla Principal -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Pilotos y Slots de Producción
                <span class="float-right">
                    <i class="fas fa-sort-amount-down"></i> Ordenado por SP (Descendente)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Piloto</th>
                                <th>Pocket</th>
                                <th>Account Type</th>
                                <th>Trade Field</th>
                                <th class="text-right">SP (Millones)</th>
                                <th class="text-center">Mass Prod</th>
                                <th class="text-center">Adv. Mass Prod</th>
                                <th class="text-center">Slots</th>
                                <th>Estado</th>
                                <th>Fórmula</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $contador = 1;
                            foreach ($pilotos_con_slots as $piloto): 
                                
                                // Clase del badge de slots
                                if ($piloto['slots'] >= 11) {
                                    $slot_class = 'badge-slot-max';
                                    $slot_icon = 'fa-check-circle';
                                } elseif ($piloto['slots'] >= 7) {
                                    $slot_class = 'badge-slot-high';
                                    $slot_icon = 'fa-arrow-up';
                                } elseif ($piloto['slots'] >= 4) {
                                    $slot_class = 'badge-slot-mid';
                                    $slot_icon = 'fa-minus';
                                } else {
                                    $slot_class = 'badge-slot-low';
                                    $slot_icon = 'fa-arrow-down';
                                }

                                // Clase del badge de account type
                                $acc_class = '';
                                $acc_icon = '';
                                switch(strtolower($piloto['acctype'])) {
                                    case 'omega':
                                        $acc_class = 'omega-badge';
                                        $acc_icon = 'fa-gem';
                                        break;
                                    case 'maybe alpha':
                                        $acc_class = 'maybe-badge';
                                        $acc_icon = 'fa-question-circle';
                                        break;
                                    case 'alpha':
                                        $acc_class = 'alpha-badge';
                                        $acc_icon = 'fa-user';
                                        break;
                                    default:
                                        $acc_class = 'badge-secondary';
                                        $acc_icon = 'fa-question';
                                }
                            ?>
                            <tr>
                                <td><?php echo $contador++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($piloto['toon_name']); ?></strong>
                                    <?php if ($piloto['es_ceo']): ?>
                                        <span class="ceo-badge">
                                            <i class="fas fa-crown"></i> CEO
                                        </span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">ID: <?php echo $piloto['toon_number']; ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-dark">
                                        <i class="fas fa-user-secret"></i> <?php echo htmlspecialchars($piloto['pocket6']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $acc_class; ?>">
                                        <i class="fas <?php echo $acc_icon; ?>"></i> <?php echo htmlspecialchars($piloto['acctype']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($piloto['tradefield']); ?></td>
                                <td class="text-right">
                                    <span class="sp-value">
                                        <?php echo number_format($piloto['sp_millones'], 2); ?> M
                                    </span>
                                </td>
                                <!-- ============================================
                                     CORREGIDO: Mass Production con 3 estados visuales
                                     ============================================ -->
                                <td class="text-center">
                                    <?php if ($piloto['mass_prod_level'] === null): ?>
                                        <!-- NO TIENE LA SKILL: muestra "-" en gris oscuro -->
                                        <span class="skill-level skill-null" title="No posee esta skill">
                                            -
                                        </span>
                                        <br><small class="skill-null-text">Sin skill</small>
                                    <?php else: ?>
                                        <!-- TIENE LA SKILL: muestra niveles 0-5 -->
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="skill-level <?php echo ($i <= $piloto['mass_prod_level']) ? 'skill-active' : 'skill-inactive'; ?>">
                                                <?php echo $i; ?>
                                            </span>
                                        <?php endfor; ?>
                                        <?php if ($piloto['mass_prod_level'] == 0): ?>
                                            <br><small class="skill-null-text">Nivel 0</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <!-- ============================================
                                     CORREGIDO: Advanced Mass Production con 3 estados
                                     ============================================ -->
                                <td class="text-center">
                                    <?php if ($piloto['adv_mass_prod_level'] === null): ?>
                                        <!-- NO TIENE LA SKILL: muestra "-" en gris oscuro -->
                                        <span class="skill-level skill-null" title="No posee esta skill (requiere Mass Production)">
                                            -
                                        </span>
                                        <br><small class="skill-null-text">Sin skill</small>
                                    <?php else: ?>
                                        <!-- TIENE LA SKILL: muestra niveles 0-5 -->
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="skill-level <?php echo ($i <= $piloto['adv_mass_prod_level']) ? 'skill-active' : 'skill-inactive'; ?>">
                                                <?php echo $i; ?>
                                            </span>
                                        <?php endfor; ?>
                                        <?php if ($piloto['adv_mass_prod_level'] == 0): ?>
                                            <br><small class="skill-null-text">Nivel 0</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-slot <?php echo $slot_class; ?>">
                                        <i class="fas <?php echo $slot_icon; ?>"></i> <?php echo $piloto['slots']; ?>/11
                                    </span>
                                </td>
                                <td>
                                    <?php if ($piloto['slots'] >= MAX_SLOTS): ?>
                                        <span class="text-success">
                                            <i class="fas fa-crown"></i> Máximo
                                        </span>
                                    <?php elseif ($piloto['slots'] >= 7): ?>
                                        <span class="text-info">
                                            <i class="fas fa-thumbs-up"></i> Bueno
                                        </span>
                                    <?php elseif ($piloto['slots'] >= 4): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Regular
                                        </span>
                                    <?php else: ?>
                                        <span class="text-danger">
                                            <i class="fas fa-exclamation-circle"></i> Bajo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    // ============================================
                                    // CORREGIDO: Fórmula clara con NULL vs 0
                                    // ============================================
                                    $mass_display = ($piloto['mass_prod_level'] === null) ? '-' : $piloto['mass_prod_level'];
                                    $adv_display = ($piloto['adv_mass_prod_level'] === null) ? '-' : $piloto['adv_mass_prod_level'];
                                    echo "1 + " . $mass_display . " + " . $adv_display . " = " . $piloto['slots'];
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pilotos_con_slots)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No se encontraron pilotos con los filtros seleccionados.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 mb-4 text-muted">
            <small>
                <i class="fas fa-code"></i> EVE Online Production Dashboard | 
                <i class="fas fa-database"></i> Datos en tiempo real | 
                Slots = 1 + Mass Production + Advanced Mass Production (Máx: 11)
            </small>
        </div>

    </div>

    <!-- Bootstrap 4.6 JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
<?php
// Cerrar conexión
if (isset($link)) {
    mysqli_close($link);
}
?>
