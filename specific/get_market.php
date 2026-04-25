<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
// ========================================
// INCLUDES
// ========================================
require_once '../config.php';
require_once '../ui_functions.php';

// Aplicar seguridad
check_authorization();

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

// ========================================
// CONFIGURACIÓN
// ========================================
// Configuración para Regiones
define('UPDATE_INTERVAL_MINUTES', 15);
define('ESI_USER_AGENT', 'EVE Market Dashboard/1.0 (your@email.com)');
define('BATCH_SIZE', 2000);
define('MAX_EXECUTION_TIME', 240);

// Configuración para Arbitraje
define('MIN_BUY_PRICE', 4);
define('FUZZWORK_API', 'https://market.fuzzwork.co.uk/aggregates/');
define('MAX_FUZZWORK_CALLS', 200);

// Configuración PHP
set_time_limit(0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');

// Determinar qué tab mostrar
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'regions';

// ========================================
// FUNCIONES PARA REGIONES
// ========================================

function fetch_and_save_orders_progressive($region_id) {
    global $link;
    
    $page = 1;
    $total_orders_saved = 0;
    $start_time = time();
    
    $delete_sql = "DELETE FROM market_orders WHERE region_id = ?";
    $stmt = mysqli_prepare($link, $delete_sql);
    mysqli_stmt_bind_param($stmt, "i", $region_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $batch = [];
    
    do {
        if ((time() - $start_time) > MAX_EXECUTION_TIME) {
            if (count($batch) > 0) {
                save_batch_to_db(null, $region_id, $batch);
                $total_orders_saved += count($batch);
            }
            
            $update_sql = "UPDATE regions SET last_update = NOW(), total_orders = ? WHERE id = ?";
            $stmt = mysqli_prepare($link, $update_sql);
            mysqli_stmt_bind_param($stmt, "ii", $total_orders_saved, $region_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            return ['error' => 'Timeout: Process exceeded maximum execution time. Partial data saved.', 'total_orders' => $total_orders_saved];
        }
        
        $url = "https://esi.evetech.net/latest/markets/{$region_id}/orders/?datasource=tranquility&order_type=all&page={$page}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, ESI_USER_AGENT);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => 'CURL Error: ' . $error, 'total_orders' => $total_orders_saved];
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            return ['error' => "ESI returned HTTP {$http_code}", 'total_orders' => $total_orders_saved];
        }
        
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        
        $orders = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON decode error: ' . json_last_error_msg(), 'total_orders' => $total_orders_saved];
        }
        
        if (!is_array($orders)) {
            return ['error' => 'Invalid response format', 'total_orders' => $total_orders_saved];
        }
        
        foreach ($orders as $order) {
            $batch[] = $order;
            
            if (count($batch) >= BATCH_SIZE) {
                $result = save_batch_to_db(null, $region_id, $batch);
                if (isset($result['error'])) {
                    return $result;
                }
                $total_orders_saved += count($batch);
                $batch = [];
                gc_collect_cycles();
            }
        }
        
        preg_match('/X-Pages:\s*(\d+)/i', $header, $matches);
        $total_pages = isset($matches[1]) ? (int)$matches[1] : 1;
        
        $page++;
        
        if ($page <= $total_pages) {
            usleep(50000);
        }
        
    } while ($page <= $total_pages);
    
    if (count($batch) > 0) {
        $result = save_batch_to_db(null, $region_id, $batch);
        if (isset($result['error'])) {
            return $result;
        }
        $total_orders_saved += count($batch);
    }
    
    $update_sql = "UPDATE regions 
                  SET last_update = NOW(), total_orders = ? 
                  WHERE id = ?";
    $stmt = mysqli_prepare($link, $update_sql);
    mysqli_stmt_bind_param($stmt, "ii", $total_orders_saved, $region_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return ['success' => true, 'total_orders' => $total_orders_saved];
}

function save_batch_to_db($stmt, $region_id, $batch) {
    global $link;
    
    try {
        $values = [];
        $params = [];
        $types = '';
        
        foreach ($batch as $order) {
            $values[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params[] = $order['order_id'];
            $params[] = $region_id;
            $params[] = $order['type_id'];
            $params[] = $order['location_id'];
            $params[] = $order['is_buy_order'] ? 1 : 0;
            $params[] = $order['price'];
            $params[] = $order['volume_remain'];
            $params[] = $order['volume_total'];
            $params[] = $order['min_volume'] ?? 1;
            $params[] = $order['duration'];
            $params[] = date('Y-m-d H:i:s', strtotime($order['issued']));
            
            $types .= 'iiiiidiiiis';
        }
        
        $sql = "INSERT INTO market_orders 
                (order_id, region_id, type_id, location_id, is_buy_order, 
                 price, volume_remain, volume_total, min_volume, duration, 
                 issued, fetched_at) 
                VALUES " . implode(', ', $values);
        
        $stmt_bulk = mysqli_prepare($link, $sql);
        
        if (!$stmt_bulk) {
            throw new Exception("Error preparing bulk insert: " . mysqli_error($link));
        }
        
        mysqli_stmt_bind_param($stmt_bulk, $types, ...$params);
        
        if (!mysqli_stmt_execute($stmt_bulk)) {
            throw new Exception("Error executing bulk insert: " . mysqli_stmt_error($stmt_bulk));
        }
        
        mysqli_stmt_close($stmt_bulk);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function get_all_regions() {
    global $link;
    
    $sql = "SELECT id, name, last_update, total_orders, ultima_revision_contratos, ultima_compra_contrato 
            FROM regions ORDER BY total_orders DESC";
    $result = mysqli_query($link, $sql);
    
    if (!$result) {
        die("Error en query: " . mysqli_error($link));
    }
    
    $regions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $regions[] = $row;
    }
    
    return $regions;
}

function can_update_region($last_update) {
    if ($last_update === null || $last_update === '') {
        return true;
    }
    
    $last = strtotime($last_update);
    $now = time();
    $diff_minutes = ($now - $last) / 60;
    
    return $diff_minutes >= UPDATE_INTERVAL_MINUTES;
}

function get_time_remaining($last_update) {
    if ($last_update === null || $last_update === '') {
        return 0;
    }
    
    $last = strtotime($last_update);
    $now = time();
    $diff_minutes = ($now - $last) / 60;
    $remaining = UPDATE_INTERVAL_MINUTES - $diff_minutes;
    
    return max(0, ceil($remaining));
}

function get_time_ago($datetime) {
    if ($datetime === null || $datetime === '') {
        return 'Never';
    }
    
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;
    
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    if ($days > 0) {
        return $days . 'd ' . $hours . 'h ago';
    } elseif ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm ago';
    } elseif ($minutes > 0) {
        return $minutes . 'm ago';
    } else {
        return 'Just now';
    }
}

function get_time_color_class($datetime) {
    if ($datetime === null || $datetime === '') {
        return 'never-updated';
    }
    
    $timestamp = strtotime($datetime);
    $now = time();
    $diff_hours = ($now - $timestamp) / 3600;
    
    if ($diff_hours < 8) {
        return 'time-fresh';
    } elseif ($diff_hours < 24) {
        return 'time-warning';
    } else {
        return 'time-old';
    }
}

// ========================================
// FUNCIONES PARA ARBITRAJE
// ========================================

function obtenerRegiones() {
    global $link;
    
    $sql = "SELECT id, name FROM regions ORDER BY name ASC";
    $result = mysqli_query($link, $sql);
    
    $regions = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $regions[] = $row;
        }
        mysqli_free_result($result);
    }
    
    return $regions;
}

function verificarActualizacionRegion($region_id) {
    global $link;
    
    $sql = "SELECT last_update FROM regions WHERE id = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $region_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$row || !$row['last_update']) {
        return ['needs_update' => true, 'hours_ago' => null];
    }
    
    $last_update = strtotime($row['last_update']);
    $now = time();
    $diff_hours = ($now - $last_update) / 3600;
    
    return [
        'needs_update' => $diff_hours > 12,
        'hours_ago' => $diff_hours,
        'last_update' => $row['last_update']
    ];
}

function obtenerDatosItem($type_id) {
    global $link;
    
    $sql = "SELECT typeName, volume FROM invTypes2 WHERE typeID = ?";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $type_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        return [
            'name' => $row['typeName'],
            'volume' => floatval($row['volume'])
        ];
    }
    
    $url = "https://esi.evetech.net/latest/universe/types/{$type_id}/";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EVE-Market-Dashboard/1.0');
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) {
        return [
            'name' => "Unknown Item ({$type_id})",
            'volume' => 0
        ];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['name'])) {
        return [
            'name' => "Unknown Item ({$type_id})",
            'volume' => 0
        ];
    }
    
    $name = $data['name'];
    $description = isset($data['description']) ? $data['description'] : '';
    $volume = isset($data['volume']) ? floatval($data['volume']) : 0;
    $group_id = isset($data['group_id']) ? $data['group_id'] : null;
    
    $insert_sql = "INSERT INTO invTypes2 (typeID, typeName, description, volume, groupID, published) 
                   VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = mysqli_prepare($link, $insert_sql);
    mysqli_stmt_bind_param($stmt, "issdi", $type_id, $name, $description, $volume, $group_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return [
        'name' => $name,
        'volume' => $volume
    ];
}

function obtenerPrecioJita($type_id) {
    global $link;
    
    $sql = "SELECT jita_value FROM jita_value WHERE type_id = ? ORDER BY date_insert DESC LIMIT 1";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "i", $type_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        return floatval($row['jita_value']);
    }
    
    if (!isset($GLOBALS['fuzzwork_call_count'])) {
        $GLOBALS['fuzzwork_call_count'] = 0;
    }
    
    if ($GLOBALS['fuzzwork_call_count'] >= MAX_FUZZWORK_CALLS) {
        return null;
    }
    
    $url = FUZZWORK_API . "?types={$type_id}&region=10000002";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || !$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data[$type_id]['sell']['percentile'])) {
        return null;
    }
    
    $jita_value = floatval($data[$type_id]['sell']['percentile']);
    
    $insert_sql = "INSERT INTO jita_value (type_id, jita_value, date_insert) VALUES (?, ?, NOW())";
    $stmt = mysqli_prepare($link, $insert_sql);
    mysqli_stmt_bind_param($stmt, "id", $type_id, $jita_value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    $GLOBALS['fuzzwork_call_count']++;
    
    return $jita_value;
}

function analizarOportunidades($region_id, $min_spread_percent) {
    global $link;
    
    $sql = "SELECT DISTINCT type_id, 
                   MAX(price) as highest_buy_price,
                   SUM(volume_remain) as total_volume
            FROM market_orders 
            WHERE region_id = ? 
            AND is_buy_order = 1 
            AND price >= ?
            GROUP BY type_id
            ORDER BY highest_buy_price DESC";
    
    $stmt = mysqli_prepare($link, $sql);
    $min_price = MIN_BUY_PRICE;
    mysqli_stmt_bind_param($stmt, "id", $region_id, $min_price);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $opportunities = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $type_id = $row['type_id'];
        $buy_price = floatval($row['highest_buy_price']);
        $volume = intval($row['total_volume']);
        
        $item_data = obtenerDatosItem($type_id);
        $jita_value = obtenerPrecioJita($type_id);
        
        if ($jita_value === null || $jita_value <= 0) {
            continue;
        }
        
        $spread_percent = (($buy_price - $jita_value) / $jita_value) * 100;
        
        if ($spread_percent < $min_spread_percent) {
            continue;
        }
        
        $total_volume_m3 = $item_data['volume'] * $volume;
        
        $opportunities[] = [
            'type_id' => $type_id,
            'type_name' => $item_data['name'],
            'buy_price' => $buy_price,
            'jita_value' => $jita_value,
            'spread_percent' => $spread_percent,
            'volume' => $volume,
            'unit_volume' => $item_data['volume'],
            'total_volume_m3' => $total_volume_m3,
            'profit_per_unit' => $buy_price - $jita_value
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    usort($opportunities, function($a, $b) {
        return $b['spread_percent'] <=> $a['spread_percent'];
    });
    
    return $opportunities;
}

function renderizarTablaOportunidades($opportunities, $region_id) {
    if (empty($opportunities)) {
        return '<div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No se encontraron oportunidades con los criterios especificados.
                </div>';
    }
    
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-striped table-hover table-sm">';
    $html .= '<thead class="thead-dark">';
    $html .= '<tr>';
    $html .= '<th>Type ID</th>';
    $html .= '<th>Item Name</th>';
    $html .= '<th class="text-right">Buy Price</th>';
    $html .= '<th class="text-right">Jita Value</th>';
    $html .= '<th class="text-right">Spread %</th>';
    $html .= '<th class="text-right">Profit/Unit</th>';
    $html .= '<th class="text-right">Units</th>';
    $html .= '<th class="text-right">Total m³</th>';
    $html .= '<th class="text-right">Potential ISK</th>';
    $html .= '<th class="text-center">Acción</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($opportunities as $opp) {
        $spread_color = $opp['spread_percent'] >= 50 ? 'text-success font-weight-bold' : 
                       ($opp['spread_percent'] >= 30 ? 'text-success' : 'text-warning');
        
        $potential_isk = $opp['profit_per_unit'] * $opp['volume'];
        $fuzzwork_url = 'https://market.fuzzwork.co.uk/region/' . $region_id . '/type/' . $opp['type_id'] . '/';
        
        $html .= '<tr>';
        $html .= '<td><a href="' . htmlspecialchars($fuzzwork_url) . '" target="_blank" class="text-primary font-weight-bold">' . htmlspecialchars($opp['type_id']) . '</a></td>';
        $html .= '<td><strong>' . htmlspecialchars($opp['type_name']) . '</strong></td>';
        $html .= '<td class="text-right">' . number_format($opp['buy_price'], 2) . ' ISK</td>';
        $html .= '<td class="text-right">' . number_format($opp['jita_value'], 2) . ' ISK</td>';
        $html .= '<td class="text-right ' . $spread_color . '">+' . number_format($opp['spread_percent'], 1) . '%</td>';
        $html .= '<td class="text-right text-success">+' . number_format($opp['profit_per_unit'], 2) . ' ISK</td>';
        $html .= '<td class="text-right">' . number_format($opp['volume']) . '</td>';
        $html .= '<td class="text-right"><span class="badge badge-info">' . number_format($opp['total_volume_m3'], 2) . ' m³</span></td>';
        $html .= '<td class="text-right font-weight-bold text-success">' . number_format($potential_isk, 0) . ' ISK</td>';
        $html .= '<td class="text-center">';
        $html .= '<a href="?tab=arbitrage&action=reload_price&type_id=' . $opp['type_id'] . '" class="btn btn-warning btn-sm" onclick="return confirm(\'¿Recargar precio de Jita para ' . htmlspecialchars($opp['type_name']) . '?\');">';
        $html .= '<i class="fas fa-sync-alt"></i> Recargar';
        $html .= '</a>';
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    
    return $html;
}

// ========================================
// MANEJAR ACCIONES DE REGIONES
// ========================================
if (isset($_GET['action']) && isset($_GET['region_id']) && $_GET['tab'] !== 'arbitrage') {
    $region_id = intval($_GET['region_id']);
    $action = $_GET['action'];
    
    if ($action === 'check_contracts') {
        $sql = "UPDATE regions SET ultima_revision_contratos = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $region_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: ?tab=regions");
        exit;
    }
    
    if ($action === 'purchase_contract') {
        $sql = "UPDATE regions SET ultima_compra_contrato = NOW() WHERE id = ?";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "i", $region_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: ?tab=regions");
        exit;
    }
}

// ========================================
// MANEJAR ACCIÓN DE RECARGAR PRECIO (ARBITRAJE)
// ========================================
if (isset($_GET['action']) && $_GET['action'] === 'reload_price' && isset($_GET['type_id'])) {
    $type_id = intval($_GET['type_id']);
    
    $delete_sql = "DELETE FROM jita_value WHERE type_id = ?";
    $stmt = mysqli_prepare($link, $delete_sql);
    mysqli_stmt_bind_param($stmt, "i", $type_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    header("Location: ?tab=arbitrage");
    exit;
}

// ========================================
// MANEJAR PETICIONES AJAX (Update Orders)
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['region_id']) && !isset($_POST['analyze'])) {
    header('Content-Type: application/json');
    
    try {
        $region_id = intval($_POST['region_id']);
        
        $check_sql = "SELECT id, name, last_update FROM regions WHERE id = ?";
        $stmt = mysqli_prepare($link, $check_sql);
        mysqli_stmt_bind_param($stmt, "i", $region_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $region = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if (!$region) {
            echo json_encode(['success' => false, 'error' => 'Region not found']);
            exit;
        }
        
        if (!can_update_region($region['last_update'])) {
            $remaining = get_time_remaining($region['last_update']);
            echo json_encode([
                'success' => false, 
                'error' => "Please wait {$remaining} more minutes before updating"
            ]);
            exit;
        }
        
        $result = fetch_and_save_orders_progressive($region_id);
        
        if (isset($result['error'])) {
            echo json_encode(['success' => false, 'error' => $result['error'], 'total_orders' => $result['total_orders'] ?? 0]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'total_orders' => $result['total_orders']
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}

// ========================================
// PROCESAR ANÁLISIS DE ARBITRAJE
// ========================================
$selected_region = 0;
$min_spread = 30;
$results = [];
$show_update_warning = false;

if ($active_tab === 'arbitrage' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['analyze'])) {
    $selected_region = isset($_POST['region_id']) ? intval($_POST['region_id']) : 0;
    $min_spread = isset($_POST['min_spread']) ? floatval($_POST['min_spread']) : 30;
    
    if ($selected_region > 0) {
        $region_status = verificarActualizacionRegion($selected_region);
        
        if ($region_status['needs_update']) {
            $show_update_warning = true;
            $hours_ago = $region_status['hours_ago'];
            if ($hours_ago !== null) {
                $hours_display = floor($hours_ago);
                $minutes_display = floor(($hours_ago - $hours_display) * 60);
            }
        }
        
        $results = analizarOportunidades($selected_region, $min_spread);
        
        if (isset($GLOBALS['fuzzwork_call_count']) && $GLOBALS['fuzzwork_call_count'] >= MAX_FUZZWORK_CALLS) {
            echo '<script>
                alert("Se actualizaron ' . $GLOBALS['fuzzwork_call_count'] . ' precios nuevos desde Fuzzwork.\nRecargando página para continuar...");
                setTimeout(function() { 
                    document.getElementById("analyzeForm").submit(); 
                }, 2000);
            </script>';
        }
    }
}

// ========================================
// MOSTRAR INTERFAZ
// ========================================
echo ui_header("Market Dashboard");
echo crew_navbar(); echo "<br /><br />";
?>

<style>
    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #007bff;
        font-weight: bold;
    }
    .btn-update:disabled {
        cursor: not-allowed;
    }
    .never-updated {
        color: #6c757d;
        font-style: italic;
        background-color: transparent;
    }
    .time-fresh {
        background-color: #d4edda !important;
        color: #155724;
    }
    .time-warning {
        background-color: #fff3cd !important;
        color: #856404;
    }
    .time-old {
        background-color: #f8d7da !important;
        color: #721c24;
    }
    .total-regions {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
        font-weight: bold;
    }
    .text-success { color: #28a745 !important; }
    .text-warning { color: #ffc107 !important; }
    .text-danger { color: #dc3545 !important; }
    .tab-content {
        margin-top: 20px;
    }
</style>

<!-- Pestañas de Navegación -->
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab === 'regions' ? 'active' : ''; ?>" 
           href="?tab=regions">
            <i class="fas fa-globe"></i> Regiones y Contratos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?php echo $active_tab === 'arbitrage' ? 'active' : ''; ?>" 
           href="?tab=arbitrage">
            <i class="fas fa-chart-line"></i> Análisis de Arbitraje
        </a>
    </li>
</ul>

<!-- Contenido de las Pestañas -->
<div class="tab-content">
    
    <!-- TAB: REGIONES Y CONTRATOS -->
    <?php if ($active_tab === 'regions'): ?>
        <?php
        $regions = get_all_regions();
        $total_regions = count($regions);
        ?>
        
        <div class="total-regions">
            <i class="fas fa-globe"></i> Total de Regiones: <?= $total_regions ?>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>Region Name</th>
                        <th>Last Update</th>
                        <th>Total Orders</th>
                        <th>Update Orders</th>
                        <th>Last Contract Check</th>
                        <th>Contracts</th>
                        <th>Last Purchase</th>
                        <th>Purchase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regions as $region): ?>
                        <?php 
                            $can_update = can_update_region($region['last_update']);
                            $time_remaining = get_time_remaining($region['last_update']);
                            $last_update_ago = get_time_ago($region['last_update']);
                            $last_update_color = get_time_color_class($region['last_update']);
                            $contract_check_ago = get_time_ago($region['ultima_revision_contratos']);
                            $contract_check_color = get_time_color_class($region['ultima_revision_contratos']);
                            $purchase_ago = get_time_ago($region['ultima_compra_contrato']);
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($region['name']) ?></strong></td>
                            <td class="<?= $last_update_color ?>">
                                <?= $last_update_ago ?>
                            </td>
                            <td class="<?= $region['total_orders'] == -1 ? 'never-updated' : '' ?>">
                                <?= $region['total_orders'] == -1 ? '-' : number_format($region['total_orders']) ?>
                            </td>
                            <td>
                                <?php if ($can_update): ?>
                                    <button class="btn btn-primary btn-sm btn-update" 
                                            data-region-id="<?= $region['id'] ?>" 
                                            data-region-name="<?= htmlspecialchars($region['name']) ?>">
                                        Update Now
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        Wait <?= $time_remaining ?> min
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="<?= $contract_check_color ?>">
                                <?= $contract_check_ago ?>
                            </td>
                            <td>
                                <a href="?tab=regions&action=check_contracts&region_id=<?= $region['id'] ?>" 
                                   class="btn btn-info btn-sm"
                                   onclick="return confirm('Mark contracts as checked for <?= htmlspecialchars($region['name']) ?>?')">
                                    ✓ Checked
                                </a>
                            </td>
                            <td class="<?= $purchase_ago === 'Never' ? 'never-updated' : '' ?>">
                                <?= $purchase_ago ?>
                            </td>
                            <td>
                                <a href="?tab=regions&action=purchase_contract&region_id=<?= $region['id'] ?>" 
                                   class="btn btn-success btn-sm"
                                   onclick="return confirm('Mark contract as purchased for <?= htmlspecialchars($region['name']) ?>?')">
                                    $ Purchased
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <script src='https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js' integrity='sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=' crossorigin='anonymous'></script>
        <script>
        $(document).ready(function() {
            $('.btn-update').click(function() {
                const btn = $(this);
                const regionId = btn.data('region-id');
                const regionName = btn.data('region-name');
                
                if (btn.prop('disabled')) {
                    return;
                }
                
                if (!confirm(`Update market data for ${regionName}?\n\nThis may take several minutes for large regions.`)) {
                    return;
                }
                
                btn.prop('disabled', true);
                btn.html('<span class="spinner-border spinner-border-sm"></span> Updating...');
                
                $.ajax({
                    url: window.location.pathname + '?tab=regions',
                    method: 'POST',
                    data: { region_id: regionId },
                    dataType: 'json',
                    timeout: 300000,
                    success: function(response) {
                        if (response.success) {
                            alert(`Success!\n\nRegion: ${regionName}\nOrders downloaded: ${response.total_orders.toLocaleString()}`);
                            location.href = '?tab=regions';
                        } else {
                            const orders = response.total_orders ? `\nPartial data saved: ${response.total_orders.toLocaleString()} orders` : '';
                            alert(`Error: ${response.error}${orders}`);
                            location.href = '?tab=regions';
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', xhr.responseText);
                        let errorMsg = `AJAX Error: ${error}`;
                        if (status === 'timeout') {
                            errorMsg = 'Request timeout. The region may be too large. Check if partial data was saved.';
                        }
                        alert(errorMsg + '\n\nCheck browser console for details.');
                        location.href = '?tab=regions';
                    }
                });
            });
        });
        </script>
    
    <!-- TAB: ANÁLISIS DE ARBITRAJE -->
    <?php elseif ($active_tab === 'arbitrage'): ?>
        
        <div class="container-fluid mt-4">
            <?php if ($show_update_warning): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <h5 class="alert-heading">
                        <i class="fas fa-exclamation-triangle"></i> ¡Atención! Datos desactualizados
                    </h5>
                    <p class="mb-0">
                        <strong>La región seleccionada no se ha actualizado en más de 12 horas.</strong>
                        <?php if (isset($hours_display)): ?>
                            <br>Última actualización: hace <strong><?php echo $hours_display; ?> horas <?php echo $minutes_display; ?> minutos</strong>
                        <?php endif; ?>
                        <br><span class="text-white">Por favor, actualiza los datos de mercado de esta región desde el módulo de Regiones antes de continuar.</span>
                    </p>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-chart-line"></i> Análisis de Oportunidades de Arbitraje
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        <i class="fas fa-info-circle"></i> 
                        Encuentra items con buy orders superiores al precio de Jita
                    </p>
                    
                    <form method="POST" id="analyzeForm" action="?tab=arbitrage">
                        <input type="hidden" name="analyze" value="1">
                        <div class="form-row">
                            <div class="form-group col-md-5">
                                <label for="region_id"><strong>Región:</strong></label>
                                <select name="region_id" id="region_id" class="form-control" required>
                                    <option value="">-- Selecciona una región --</option>
                                    <?php 
                                    $regions_arb = obtenerRegiones();
                                    foreach ($regions_arb as $region): 
                                        if ($region['id'] == 10000032 || $region['id'] == 10000042) continue;
                                        $selected = ($region['id'] == $selected_region) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $region['id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($region['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group col-md-3">
                                <label for="min_spread"><strong>Spread Mínimo (%):</strong></label>
                                <input type="number" name="min_spread" id="min_spread" class="form-control" 
                                       value="<?php echo $min_spread; ?>" min="0" step="1" required>
                                <small class="form-text text-muted">
                                    Precio mínimo: <?php echo MIN_BUY_PRICE; ?> ISK
                                </small>
                            </div>
                            
                            <div class="form-group col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-success btn-lg btn-block">
                                    <i class="fas fa-search"></i> Analizar Región
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (!empty($results)): ?>
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle"></i> 
                            Oportunidades Encontradas: <strong><?php echo count($results); ?></strong>
                            <?php if (isset($GLOBALS['fuzzwork_call_count']) && $GLOBALS['fuzzwork_call_count'] > 0): ?>
                                <span class="badge badge-light ml-2">
                                    <i class="fas fa-sync-alt"></i> 
                                    <?php echo $GLOBALS['fuzzwork_call_count']; ?> precios actualizados
                                </span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php echo renderizarTablaOportunidades($results, $selected_region); ?>
                    </div>
                </div>
            <?php elseif ($selected_region > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Sin resultados:</strong> 
                    No se encontraron oportunidades con spread mínimo de <?php echo $min_spread; ?>% 
                    y precio mínimo de <?php echo MIN_BUY_PRICE; ?> ISK.
                </div>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    
</div>

<?php
echo ui_footer();
mysqli_close($link);
?>
