<?php
// ============================================
// EVE ONLINE - PRODUCTION SLOTS DASHBOARD
// PHP 8.x Procedural | Bootstrap 4.6 | Font Awesome 5.15.4
// Alfonso Orozco Aguilar
// Contact in EVE: exists as a pilot
// kimi 2.6 plus chat
// Date: 2026-05-28
// License: GPL
// ============================================

include '../config.php';

// Verify connection
if (!isset($link) || $link === false) {
    die("Error: No database connection.");
}

// ============================================
// SKILL CONSTANTS
// ============================================
define('MASS_PRODUCTION_TYPEID', 3387);
define('ADVANCED_MASS_PRODUCTION_TYPEID', 24625);
define('MAX_SLOTS', 11);

// ============================================
// ESI API FUNCTIONS (no token)
// ============================================

/**
 * Makes a GET request to the EVE Online ESI API
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
 * Gets the corporation_id of a character
 */
function get_character_corp(int $character_id): ?int {
    $data = esi_get('/characters/' . $character_id . '/');
    return isset($data['corporation_id']) ? (int)$data['corporation_id'] : null;
}

/**
 * Gets the ceo_id of a corporation
 */
function get_corp_ceo(int $corporation_id): ?int {
    $data = esi_get('/corporations/' . $corporation_id . '/');
    return isset($data['ceo_id']) ? (int)$data['ceo_id'] : null;
}

/**
 * Checks if a character is CEO of their corporation
 * Uses session cache to avoid saturating the API
 */
function is_ceo(int $character_id): bool {
    $cache_key = 'ceo_check_' . $character_id;
    
    // If already in session cache, return
    if (isset($_SESSION[$cache_key])) {
        return $_SESSION[$cache_key];
    }
    
    // Get character's corporation
    $corp_id = get_character_corp($character_id);
    if ($corp_id === null) {
        $_SESSION[$cache_key] = false;
        return false;
    }
    
    // Get corporation's CEO
    $ceo_id = get_corp_ceo($corp_id);
    if ($ceo_id === null) {
        $_SESSION[$cache_key] = false;
        return false;
    }
    
    // Compare
    $is_ceo = ($ceo_id === $character_id);
    $_SESSION[$cache_key] = $is_ceo;
    
    // Small pause to respect rate limits (no token = 20 req/s)
    usleep(100000); // 0.1 seconds = max 10 req/s (conservative)
    
    return $is_ceo;
}

// Start session for cache
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// GET DYNAMIC FILTERS
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
// APPLIED FILTERS (GET)
// ============================================
$filter_pocket = isset($_GET['pocket6']) ? $_GET['pocket6'] : 'ALL';
$filter_acctype = isset($_GET['acctype']) ? $_GET['acctype'] : 'ALL';
$filter_tradefield = isset($_GET['tradefield']) ? $_GET['tradefield'] : 'ALL';

// ============================================
// BUILD MAIN QUERY
// ============================================
// FIXED: No COALESCE to distinguish NULL (doesn't have skill) from 0 (level 0)
$sql = "
    SELECT 
        p.toon_number,
        p.toon_name,
        p.pocket6,
        p.acctype,
        p.tradefield,
        p.skillpoints,
        p.unalloc,
        (p.skillpoints + p.unalloc) / 1000000 AS sp_millions,
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

// Apply filters
if ($filter_pocket !== 'ALL') {
    $safe_pocket = mysqli_real_escape_string($link, $filter_pocket);
    $sql .= " AND p.pocket6 = '$safe_pocket'";
}

if ($filter_acctype !== 'ALL') {
    $safe_acctype = mysqli_real_escape_string($link, $filter_acctype);
    $sql .= " AND p.acctype = '$safe_acctype'";
}

if ($filter_tradefield !== 'ALL') {
    $safe_tradefield = mysqli_real_escape_string($link, $filter_tradefield);
    $sql .= " AND p.tradefield = '$safe_tradefield'";
}

// Order by SP descending
$sql .= " ORDER BY sp_millions DESC";

$result = mysqli_query($link, $sql);

// ============================================
// PROCESS RESULTS AND DETECT CEOs
// ============================================

$total_pilots = 0;
$total_slots = 0;
$max_slots_pilot = 0;
$pilots_with_slots = [];
$ceos_list = []; // Array to store CEOs

if ($result) {
    mysqli_data_seek($result, 0);
    while ($row = mysqli_fetch_assoc($result)) {
        
        // ============================================
        // FIXED: Slot logic with NULL vs 0 distinction
        // ============================================
        $mass_level = $row['mass_prod_level'];     // NULL = doesn't have, 0-5 = has
        $adv_level = $row['adv_mass_prod_level'];  // NULL = doesn't have, 0-5 = has
        
        // If doesn't have Mass Production (NULL), can't have Advanced
        if ($mass_level === null) {
            $mass_level = null;
            $adv_level = null; // Force NULL even if DB says otherwise (prerequisite not met)
        }
        
        // Calculate slots: base 1 + Mass Production (0-5) + Advanced Mass Production (0-5)
        // Only add if has the skill (not NULL)
        $mass_bonus = ($mass_level !== null) ? (int)$mass_level : 0;
        $adv_bonus = ($adv_level !== null) ? (int)$adv_level : 0;
        
        $slots = 1 + $mass_bonus + $adv_bonus;
        $slots = min($slots, MAX_SLOTS);
        
        // Check if CEO (only if valid toon_number)
        $is_ceo = false;
        if (!empty($row['toon_number']) && is_numeric($row['toon_number'])) {
            $is_ceo = is_ceo((int)$row['toon_number']);
        }
        
        $pilot_data = array_merge($row, [
            'slots' => $slots,
            'es_ceo' => $is_ceo,
            'mass_prod_level' => $mass_level,     // Preserve NULL
            'adv_mass_prod_level' => $adv_level   // Preserve NULL
        ]);
        
        $total_pilots++;
        $total_slots += $slots;
        
        if ($slots > $max_slots_pilot) {
            $max_slots_pilot = $slots;
        }
        
        $pilots_with_slots[] = $pilot_data;
        
        // If CEO, add to list
        if ($is_ceo) {
            $ceos_list[] = $pilot_data;
        }
    }
}

// Sort CEOs alphabetically by name
usort($ceos_list, function($a, $b) {
    return strcasecmp($a['toon_name'], $b['toon_name']);
});

// Pocket colors (for badge)
function getColorPocket($pocket) {
    $p = strtoupper(trim($pocket ?? ''));
    return match($p) {
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14',
        default => '#444444'
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online — Production Slots Dashboard</title>
    
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    
    <style>
        body {
            background-color: #1a1d21;
            color: #e0e0e0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 60px;
        }
        .navbar-eve {
            background-color: #0b0c0e;
            border-bottom: 2px solid #495057;
            margin-bottom: 0;
        }

        /* ── FILTER BAR ── */
        .filter-bar {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        .filter-bar .form-control {
            background-color: #2a2d31;
            border-color: #495057;
            color: #e0e0e0;
            max-width: 180px;
        }
        .filter-bar .form-control:focus {
            background-color: #2a2d31;
            color: #fff;
            border-color: #007bff;
            box-shadow: none;
        }

        /* ── CARDS ── */
        .card {
            background-color: #1e2126;
            border: 1px solid #343a40;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            color: #adb5bd;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* ── STATS CARDS ── */
        .stats-card {
            text-align: center;
            padding: 15px;
            background-color: #1e2126;
            border: 1px solid #343a40;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #5dade2;
            font-family: 'Courier New', monospace;
        }
        .stats-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── TABLE ── */
        .table {
            color: #e0e0e0;
            font-size: 0.82rem;
        }
        .table thead th {
            background-color: #0d0f11;
            color: #adb5bd;
            border-color: #343a40;
            white-space: nowrap;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .table tbody tr {
            background-color: #1e2126;
        }
        .table tbody td {
            color: #e0e0e0;
            border-color: #343a40;
            vertical-align: middle;
        }
        .table tbody tr:nth-child(odd) {
            background-color: #22262c;
        }
        .table tbody tr:hover {
            background-color: #2a3040 !important;
        }

        /* ── POCKET BADGE ── */
        .pocket-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            border-radius: 2px;
        }
        .pocket-badge.dark-text { color: #111; }

        /* ── SKILL LEVELS ── */
        .skill-level {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 0.75rem;
            margin: 0 1px;
        }
        .skill-active {
            background-color: #28a745;
            color: #fff;
        }
        .skill-inactive {
            background-color: #343a40;
            color: #6c757d;
        }
        .skill-null {
            background-color: #2a2d31;
            color: #495057;
            border: 1px dashed #495057;
        }

        /* ── SLOT BADGES ── */
        .badge-slot {
            font-size: 0.85rem;
            padding: 4px 10px;
            border-radius: 4px;
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

        /* ── ACCOUNT TYPE BADGES ── */
        .omega-badge {
            background-color: #f1c40f;
            color: #000;
        }
        .maybe-badge {
            background-color: #e67e22;
            color: #000;
        }
        .alpha-badge {
            background-color: #95a5a6;
            color: #fff;
        }

        /* ── SP VALUE ── */
        .sp-value {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #5dade2;
        }

        /* ── CEO BADGE ── */
        .ceo-badge {
            background-color: #f1c40f;
            color: #000;
            font-weight: bold;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 5px;
            text-transform: uppercase;
        }

        /* ── CEO CARD ── */
        .ceo-card {
            border-left: 3px solid #f1c40f;
        }
        .ceo-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid #f1c40f;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #f1c40f;
            font-weight: bold;
            margin-right: 10px;
            background-color: #1e2126;
        }

        /* ── TRADEFIELD PILL ── */
        .trade-pill {
            background-color: #2d3748;
            color: #bb86fc;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 0.75rem;
            white-space: nowrap;
        }

        /* ── FORMULA ── */
        .formula-text {
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* ── STATUS TEXT ── */
        .status-max { color: #28a745; }
        .status-high { color: #17a2b8; }
        .status-mid { color: #ffc107; }
        .status-low { color: #dc3545; }

        /* ── SKILL NULL TEXT ── */
        .skill-null-text {
            color: #495057;
            font-size: 0.7rem;
            font-style: italic;
        }

        /* ── BUTTONS ── */
        .btn-filter {
            background-color: #007bff;
            border-color: #007bff;
            color: #fff;
        }
        .btn-filter:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: #fff;
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-dark navbar-eve px-3">
        <span class="navbar-brand mb-0 h5">
            <i class="fas fa-industry mr-2"></i>Production Slots Dashboard
        </span>
        <span class="text-muted small">
            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M Y'); ?>
        </span>
    </nav>

    <!-- FILTERS -->
    <div class="filter-bar">
        <form method="GET" class="form-inline flex-wrap" style="gap:10px;">

            <label class="text-light mr-1"><i class="fas fa-user-secret mr-1"></i>Pocket:</label>
            <select name="pocket6" class="form-control form-control-sm mr-3">
                <option value="ALL" <?php echo ($filter_pocket === 'ALL') ? 'selected' : ''; ?>>-- All --</option>
                <?php foreach ($pockets as $p): ?>
                    <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($filter_pocket === $p) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="text-light mr-1"><i class="fas fa-id-card mr-1"></i>Account:</label>
            <select name="acctype" class="form-control form-control-sm mr-3">
                <option value="ALL" <?php echo ($filter_acctype === 'ALL') ? 'selected' : ''; ?>>-- All --</option>
                <?php foreach ($acctypes as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>" <?php echo ($filter_acctype === $a) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label class="text-light mr-1"><i class="fas fa-briefcase mr-1"></i>Trade:</label>
            <select name="tradefield" class="form-control form-control-sm mr-3">
                <option value="ALL" <?php echo ($filter_tradefield === 'ALL') ? 'selected' : ''; ?>>-- All --</option>
                <?php foreach ($tradefields as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($filter_tradefield === $t) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-sm btn-filter mr-2">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>

            <?php if ($filter_pocket !== 'ALL' || $filter_acctype !== 'ALL' || $filter_tradefield !== 'ALL'): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
            <?php endif; ?>

        </form>
    </div>

    <div class="container-fluid">

        <!-- CEOs SECTION -->
        <?php if (!empty($ceos_list)): ?>
        <div class="card ceo-card">
            <div class="card-header">
                <i class="fas fa-crown mr-2" style="color:#f1c40f;"></i>
                <strong>Chief Executive Officers (CEOs)</strong>
                <span class="badge badge-warning ml-2"><?php echo count($ceos_list); ?></span>
                <small class="float-right text-muted">
                    <i class="fas fa-info-circle mr-1"></i>Data from ESI API
                </small>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($ceos_list as $ceo): 
                        $ceoPocketColor = getColorPocket($ceo['pocket6']);
                        $ceoPocketClass = in_array(strtoupper(trim($ceo['pocket6'] ?? '')), ['YENN','SANGO']) ? 'dark-text' : '';
                    ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="d-flex align-items-center p-2" style="background: #22262c; border-radius: 6px; border: 1px solid #343a40;">
                            <img src="https://images.evetech.net/characters/<?php echo (int)$ceo['toon_number']; ?>/portrait?size=64"
                                 class="mr-2" style="width:38px; height:38px; border-radius:50%; border:2px solid #f1c40f;" 
                                 alt="<?php echo htmlspecialchars($ceo['toon_name']); ?>">
                            <div>
                                <div class="font-weight-bold text-white">
                                    <?php echo htmlspecialchars($ceo['toon_name']); ?>
                                    <span class="ceo-badge"><i class="fas fa-crown"></i> CEO</span>
                                </div>
                                <small class="text-muted">
                                    <span class="pocket-badge <?php echo $ceoPocketClass; ?>" style="background-color:<?php echo $ceoPocketColor; ?>;">
                                        <?php echo htmlspecialchars($ceo['pocket6']); ?>
                                    </span>
                                    <span class="ml-1"><i class="fas fa-industry mr-1"></i><?php echo $ceo['slots']; ?> slots</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $total_pilots; ?></div>
                    <div class="stats-label">Pilots</div>
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
                    <div class="stats-number"><?php echo ($total_pilots > 0) ? round($total_slots / $total_pilots, 1) : 0; ?></div>
                    <div class="stats-label">Avg Slots</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="stats-number"><?php echo $max_slots_pilot; ?></div>
                    <div class="stats-label">Max Slots (1 pilot)</div>
                </div>
            </div>
        </div>

        <!-- MAIN TABLE -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list mr-2"></i>Pilots and Production Slots
                <span class="float-right text-muted" style="font-size:0.75rem;">
                    <i class="fas fa-sort-amount-down mr-1"></i>Sorted by SP (Descending)
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pilot</th>
                                <th>Pocket</th>
                                <th>Account</th>
                                <th>Trade</th>
                                <th class="text-right">SP (Millions)</th>
                                <th class="text-center">Mass Prod</th>
                                <th class="text-center">Adv. Mass Prod</th>
                                <th class="text-center">Slots</th>
                                <th>Status</th>
                                <th>Formula</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($pilots_with_slots as $pilot): 
                                
                                // Slot badge class
                                if ($pilot['slots'] >= 11) {
                                    $slot_class = 'badge-slot-max';
                                    $slot_icon = 'fa-check-circle';
                                } elseif ($pilot['slots'] >= 7) {
                                    $slot_class = 'badge-slot-high';
                                    $slot_icon = 'fa-arrow-up';
                                } elseif ($pilot['slots'] >= 4) {
                                    $slot_class = 'badge-slot-mid';
                                    $slot_icon = 'fa-minus';
                                } else {
                                    $slot_class = 'badge-slot-low';
                                    $slot_icon = 'fa-arrow-down';
                                }

                                // Account type badge class
                                $acc_class = '';
                                $acc_icon = '';
                                switch(strtolower($pilot['acctype'])) {
                                    case 'omega':
                                        $acc_class = 'omega-badge';
                                        $acc_icon = 'fa-crown';
                                        break;
                                    case 'maybe alpha':
                                        $acc_class = 'maybe-badge';
                                        $acc_icon = 'fa-question-circle';
                                        break;
                                    case 'alpha':
                                        $acc_class = 'alpha-badge';
                                        $acc_icon = 'fa-rocket';
                                        break;
                                    default:
                                        $acc_class = 'badge-secondary';
                                        $acc_icon = 'fa-question';
                                }

                                $pocketColor = getColorPocket($pilot['pocket6']);
                                $pocketClass = in_array(strtoupper(trim($pilot['pocket6'] ?? '')), ['YENN','SANGO']) ? 'dark-text' : '';
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?php echo $counter++; ?></td>
                                <td>
                                    <strong class="text-white"><?php echo htmlspecialchars($pilot['toon_name']); ?></strong>
                                    <?php if ($pilot['es_ceo']): ?>
                                        <span class="ceo-badge"><i class="fas fa-crown"></i> CEO</span>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">ID: <?php echo $pilot['toon_number']; ?></small>
                                </td>
                                <td>
                                    <span class="pocket-badge <?php echo $pocketClass; ?>" style="background-color:<?php echo $pocketColor; ?>;">
                                        <?php echo htmlspecialchars($pilot['pocket6'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $acc_class; ?>">
                                        <i class="fas <?php echo $acc_icon; ?>"></i> <?php echo htmlspecialchars($pilot['acctype']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($pilot['tradefield']) && $pilot['tradefield'] !== 'n/a'): ?>
                                    <span class="trade-pill"><?php echo htmlspecialchars($pilot['tradefield']); ?></span>
                                    <?php else: ?>
                                    <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <span class="sp-value"><?php echo number_format($pilot['sp_millions'], 2); ?> M</span>
                                </td>
                                <!-- Mass Production -->
                                <td class="text-center">
                                    <?php if ($pilot['mass_prod_level'] === null): ?>
                                        <span class="skill-level skill-null" title="Does not have this skill">-</span>
                                        <br><small class="skill-null-text">No skill</small>
                                    <?php else: ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="skill-level <?php echo ($i <= $pilot['mass_prod_level']) ? 'skill-active' : 'skill-inactive'; ?>">
                                                <?php echo $i; ?>
                                            </span>
                                        <?php endfor; ?>
                                        <?php if ($pilot['mass_prod_level'] == 0): ?>
                                            <br><small class="skill-null-text">Level 0</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <!-- Advanced Mass Production -->
                                <td class="text-center">
                                    <?php if ($pilot['adv_mass_prod_level'] === null): ?>
                                        <span class="skill-level skill-null" title="Does not have this skill (requires Mass Production)">-</span>
                                        <br><small class="skill-null-text">No skill</small>
                                    <?php else: ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="skill-level <?php echo ($i <= $pilot['adv_mass_prod_level']) ? 'skill-active' : 'skill-inactive'; ?>">
                                                <?php echo $i; ?>
                                            </span>
                                        <?php endfor; ?>
                                        <?php if ($pilot['adv_mass_prod_level'] == 0): ?>
                                            <br><small class="skill-null-text">Level 0</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-slot <?php echo $slot_class; ?>">
                                        <i class="fas <?php echo $slot_icon; ?>"></i> <?php echo $pilot['slots']; ?>/11
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pilot['slots'] >= MAX_SLOTS): ?>
                                        <span class="status-max"><i class="fas fa-crown mr-1"></i>Max</span>
                                    <?php elseif ($pilot['slots'] >= 7): ?>
                                        <span class="status-high"><i class="fas fa-thumbs-up mr-1"></i>Good</span>
                                    <?php elseif ($pilot['slots'] >= 4): ?>
                                        <span class="status-mid"><i class="fas fa-exclamation-triangle mr-1"></i>Average</span>
                                    <?php else: ?>
                                        <span class="status-low"><i class="fas fa-exclamation-circle mr-1"></i>Low</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $mass_display = ($pilot['mass_prod_level'] === null) ? '-' : $pilot['mass_prod_level'];
                                    $adv_display = ($pilot['adv_mass_prod_level'] === null) ? '-' : $pilot['adv_mass_prod_level'];
                                    ?>
                                    <span class="formula-text">1 + <?php echo $mass_display; ?> + <?php echo $adv_display; ?> = <?php echo $pilot['slots']; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pilots_with_slots)): ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No pilots found with the selected filters.</p>
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
                <i class="fas fa-code mr-1"></i>EVE Online Production Dashboard | 
                <i class="fas fa-database mr-1"></i>Real-time data | 
                Slots = 1 + Mass Production + Advanced Mass Production (Max: 11)
            </small>
        </div>

    </div>

    <!-- Bootstrap 4.6 JS -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
<?php
// Close connection
if (isset($link)) {
    mysqli_close($link);
}
?>
