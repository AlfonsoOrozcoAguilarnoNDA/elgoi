<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - Mosaic Dashboard
 * Fecha: 2026-03-31 00:22
 * 
 * This is the Lobby of the system, showing you can pass the initial screen
 */

session_start();

// ============================================
// VERIFICACIÓN DE SESIÓN
// ============================================
if (!isset($_SESSION['is_authenticated']) || $_SESSION['is_authenticated'] !== true) {
    header('Location: fleet_login.php');
    exit;
}

// ============================================
// PROCESAR LOGOUT CON CONFIRMACIÓN
// ============================================
if (isset($_GET['logout']) && $_GET['logout'] === 'confirm') {
    session_destroy();
    header('Location: fleet_login.php');
    exit;
}

// ============================================
// OBTENER VARIABLES DE SESIÓN PARA MOSTRAR
// ============================================
$session_vars = [];
foreach ($_SESSION as $key => $value) {
    $session_vars[$key] = $value;
}

/*
Creamos mosaico
*/

$tiles = [
    showTile("crew/moneymakers.php", "fa-coins", "primary"),
    showTile("crew/evermarks.php", "fa-medal", "primary"),
    showTile("crew/biometrics.php", "fa-fingerprint", "primary"),
    showTile("crew/reputation.php", "fa-passport", "primary"),
    showTile("crew/graduation.php", "fa-graduation-cap", "success"),
    showTile("crew/28_days_control.php", "fa-user-clock", "danger"),
    showTile("crew/career_plan.php", "fa-sitemap", "dark"),
    showTile("crew/compare.php", "fa-balance-scale", "light"),
    showTile("specific/balance.php", "fa-coins", "warning"),
    showTile("specific/get_market.php", "fa-chart-line", "success"),
    showTile("specific/skybreaker.php", "fa-rocket", "danger"),
    showTile("updater.php", "fa-satellite-dish", "dark"),    
    showTile("industry/crewplanets.php", "fa-globe", "secondary"),
    showTile("industry/inventory_pi.php", "fa-layer-group", "secondary"),
    showTile("industry/jobs.php", "fa-industry", "info"),
    
    
    showTile("mining/ores.php", "fa-gem", "warning"),
    showTile("specific/settings.php", "fa-cogs", "secondary"),
    //showTile("combat/pvp.php", "fa-crosshairs", "danger"),
    showTile("industry/blueprints.php", "fa-print", "info"),
    showTile("abyss/crew.php", "fa-users", "primary"),
    showTile("logistics/hauling.php", "fa-truck", "secondary"),
    showTile("specific/ships_and_5m.php", "fa-id-badge", "info"),  
    showTile("intel/spy.php", "fa-eye", "dark")  
    
];
// ============================================
// LISTAR ARCHIVOS DEL DIRECTORIO
// ============================================
$php_files = [];
$non_php_files = [];

$directory = __DIR__; // Directorio actual donde está mosaic.php

if (is_dir($directory)) {
    $files = scandir($directory);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filepath = $directory . DIRECTORY_SEPARATOR . $file;
        
        if (is_file($filepath)) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $php_files[] = $file;
            } else {
                $non_php_files[] = $file;
            }
        }
    }
}

sort($php_files);
sort($non_php_files);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Commander - Mosaic Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f2e 50%, #0d1117 100%);
            min-height: 100vh;
            color: #c9d1d9;
            padding-top: 120px; /* Espacio para las dos barras fijas */
            padding-bottom: 60px; /* Espacio para el footer */
        }
        
        /* ============================================
           BARRAS DE NAVEGACIÓN FIJAS
           ============================================ */
        .nav-bar {
            position: fixed;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(22, 27, 34, 0.98);
            border-bottom: 1px solid #30363d;
            backdrop-filter: blur(10px);
        }
        
        .nav-bar-top {
            top: 0;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }
        
        .nav-bar-bottom {
            top: 60px;
            height: 50px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            background: rgba(13, 17, 23, 0.95);
        }
        
        .logo {
            color: #58a6ff;
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
        }
        
        .nav-links a {
            color: #8b949e;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover {
            color: #58a6ff;
        }
        
        .nav-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .pilot-badge {
            background: rgba(88, 166, 255, 0.15);
            border: 1px solid #58a6ff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            color: #58a6ff;
        }
        /* INICIA MOSAICO  */
        /* ============================================
           MOSAICO METRO / WINDOWS 8 STYLE
           ============================================ */
        .metro-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .metro-tile {
            position: relative;
            height: 120px;
            border-radius: 4px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.3s ease;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .metro-tile:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
            filter: brightness(1.1);
        }
        
        .tile-link {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            text-decoration: none;
            color: inherit;
        }
        
        .tile-icon {
            font-size: 32px;
            opacity: 0.9;
            align-self: flex-start;
        }
        
        .tile-name {
            font-size: 16px;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            margin-top: auto;
            line-height: 1.2;
        }
        
        .tile-lines {
            font-size: 11px;
            opacity: 0.8;
            font-family: 'Courier New', monospace;
            margin-top: 4px;
        }
        
        .tile-missing {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 10px;
            background: rgba(0, 0, 0, 0.6);
            padding: 2px 8px;
            border-radius: 4px;
            color: #ffc107;
        }
        /* FIN MOSAICO */
        
        /* ============================================
           CONTENIDO PRINCIPAL
           ============================================ */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .section {
            background: rgba(22, 27, 34, 0.8);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .section h2 {
            color: #58a6ff;
            font-size: 18px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #30363d;
            padding-bottom: 10px;
        }
        
        /* ============================================
           TABLA DE VARIABLES DE SESIÓN
           ============================================ */
        .session-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .session-table th,
        .session-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #30363d;
        }
        
        .session-table th {
            background: rgba(48, 54, 61, 0.5);
            color: #58a6ff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }
        
        .session-table td {
            color: #c9d1d9;
        }
        
        .session-table tr:hover td {
            background: rgba(48, 54, 61, 0.3);
        }
        
        .var-name {
            color: #7ee787;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .var-value {
            color: #ffa657;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        
        .var-type {
            color: #8b949e;
            font-size: 12px;
            font-style: italic;
        }
        
        /* ============================================
           LISTAS DE ARCHIVOS
           ============================================ */
        .file-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .file-list {
            background: rgba(13, 17, 23, 0.5);
            border-radius: 8px;
            padding: 20px;
        }
        
        .file-list h3 {
            color: #c9d1d9;
            font-size: 14px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .file-list.php h3 {
            color: #7ee787;
        }
        
        .file-list.non-php h3 {
            color: #ffa657;
        }
        
        .file-count {
            float: right;
            background: rgba(48, 54, 61, 0.8);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            color: #8b949e;
        }
        
        .file-item {
            padding: 8px 0;
            border-bottom: 1px solid #21262d;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #8b949e;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-item.php {
            color: #7ee787;
        }
        
        /* ============================================
           BOTÓN DE SALIR
           ============================================ */
        .logout-btn {
            background: linear-gradient(135deg, #da3633 0%, #b62318 100%);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(218, 54, 51, 0.4);
        }
        
        /* ============================================
           FOOTER FIJO
           ============================================ */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 50px;
            background: rgba(13, 17, 23, 0.98);
            border-top: 1px solid #30363d;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            font-size: 12px;
            color: #6e7681;
        }
        
        .footer-left {
            display: flex;
            gap: 20px;
        }
        
        .footer-right {
            color: #484f58;
        }
        
        /* ============================================
           MODAL DE CONFIRMACIÓN
           ============================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: rgba(22, 27, 34, 0.98);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 30px;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .modal h3 {
            color: #f85149;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .modal p {
            color: #8b949e;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .modal-btn {
            padding: 10px 25px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .modal-btn.confirm {
            background: #da3633;
            color: white;
        }
        
        .modal-btn.confirm:hover {
            background: #f85149;
        }
        
        .modal-btn.cancel {
            background: #30363d;
            color: #c9d1d9;
        }
        
        .modal-btn.cancel:hover {
            background: #484f58;
        }
    </style>
</head>
<body>
    <!-- ============================================
         BARRA DE NAVEGACIÓN SUPERIOR
         ============================================ -->
    <nav class="nav-bar nav-bar-top">
        <div class="logo">⚡ Fleet Commander</div>
        <div class="nav-links">
            <a href="mosaic.php">Dashboard</a>
            <a href="#">Fleet</a>
            <a href="#">Pilots</a>
            <a href="#">Settings</a>
        </div>
        <div class="nav-info">
            <span class="pilot-badge">
                <?php echo htmlspecialchars($_SESSION['pilot_name'] ?? 'Unknown'); ?>
            </span>
            <button class="logout-btn" onclick="showLogoutModal()">Logout</button>
        </div>
    </nav>
    
    <!-- ============================================
         BARRA DE NAVEGACIÓN INFERIOR
         ============================================ -->
    <nav class="nav-bar nav-bar-bottom">
        <div class="nav-links">
            <a href="#">Active Fleets</a>
            <a href="#">Pending</a>
            <a href="#">History</a>
            <a href="#">Reports</a>
        </div>
    </nav>
    
    <!-- ============================================
         CONTENIDO PRINCIPAL
         ============================================ -->
    <main class="main-content">
        
        <!-- Variables de Sesión -->
        <section class="section">
            <h2>🚀 Quick Access Mosaic</h2>
            <div class="metro-grid">
                <?php 
                foreach ($tiles as $tile) {
                    echo $tile;
                }

            echo "</div>";
?>
        </section>    
        <section class="section">
            <h2>🔐 Session Variables</h2>
            <table class="session-table">
                <thead>
                    <tr>
                        <th>Variable Name</th>
                        <th>Value</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($session_vars)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: #8b949e;">
                                No session variables found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($session_vars as $key => $value): ?>
                            <tr>
                                <td class="var-name">$_SESSION['<?php echo htmlspecialchars($key); ?>']</td>
                                <td class="var-value">
                                    <?php 
                                    if (is_array($value)) {
                                        echo htmlspecialchars(json_encode($value));
                                    } elseif (is_bool($value)) {
                                        echo $value ? 'true' : 'false';
                                    } elseif (is_null($value)) {
                                        echo 'NULL';
                                    } else {
                                        echo htmlspecialchars((string)$value);
                                    }
                                    ?>
                                </td>
                                <td class="var-type"><?php echo gettype($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
        
        <!-- Listado de Archivos -->
        <section class="section">
            <h2>📁 Directory Contents</h2>
            <div class="file-grid">
                <!-- Archivos PHP -->
                <div class="file-list php">
                    <h3>
                        PHP Files
                        <span class="file-count"><?php echo count($php_files); ?></span>
                    </h3>
                    <?php if (empty($php_files)): ?>
                        <div class="file-item">No PHP files found</div>
                    <?php else: ?>
                        <?php foreach ($php_files as $file): ?>
                            <div class="file-item php">📄 <?php echo htmlspecialchars($file); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Archivos No PHP -->
                <div class="file-list non-php">
                    <h3>
                        Non-PHP Files
                        <span class="file-count"><?php echo count($non_php_files); ?></span>
                    </h3>
                    <?php if (empty($non_php_files)): ?>
                        <div class="file-item">No non-PHP files found</div>
                    <?php else: ?>
                        <?php foreach ($non_php_files as $file): ?>
                            <div class="file-item">📎 <?php echo htmlspecialchars($file); ?></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        
    </main>
    
    <!-- ============================================
         FOOTER
         ============================================ -->
    <footer class="footer">
        <div class="footer-left">
            <span>Fleet Commander System</span>
            <span>|</span>
            <span>FC: <?php echo htmlspecialchars($_SESSION['fleet_commander_number'] ?? 'N/A'); ?></span>
        </div>
        <div class="footer-right">
            EVE Online ESI Integration • v1.0
        </div>
    </footer>
    
    <!-- ============================================
         MODAL DE CONFIRMACIÓN DE LOGOUT
         ============================================ -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal">
            <h3>⚠️ Confirm Logout</h3>
            <p>Are you sure you want to logout from Fleet Commander system?</p>
            <div class="modal-buttons">
                <button class="modal-btn cancel" onclick="hideLogoutModal()">Cancel</button>
                <a href="?logout=confirm" class="modal-btn confirm" style="text-decoration: none; display: inline-flex; align-items: center;">
                    Yes, Logout
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }
        
        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('logoutModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutModal();
            }
        });
        
        // Cerrar con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideLogoutModal();
            }
        });
    </script>
</body>
</html>
<?php
// ============================================
// FUNCIÓN PARA GENERAR TILES DEL MOSAICO
// ============================================
function showTile($file, $icon, $color) {
    $directory = __DIR__;
    $filepath = $directory . DIRECTORY_SEPARATOR . $file;
    $exists = file_exists($filepath) && is_file($filepath);
    
    // Quitar extensión y directorio para mostrar
    $displayName = basename($file);
    $displayName = pathinfo($displayName, PATHINFO_FILENAME);
    
    // Contar líneas si existe
    $lineCount = $exists ? count(file($filepath)) : 0;
    
    // Colores Bootstrap válidos
    $validColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    $bgColor = in_array($color, $validColors) ? $color : 'primary';
    
    // Mapeo de colores Bootstrap a colores hex para el estilo Metro
    $colorMap = [
        'primary'   => '#0d6efd',
        'secondary' => '#6c757d',
        'success'   => '#198754',
        'danger'    => '#dc3545',
        'warning'   => '#ffc107',
        'info'      => '#0dcaf0',
        'light'     => '#f8f9fa',
        'dark'      => '#212529'
    ];
    
    $bgHex = $colorMap[$bgColor] ?? '#0d6efd';
    $textColor = in_array($bgColor, ['warning', 'light', 'info']) ? '#212529' : '#ffffff';
    
    $tileHtml = '<div class="metro-tile" style="background-color: ' . $bgHex . '; color: ' . $textColor . ';">';
    
    if ($exists) {
        $tileHtml .= '<a href="' . htmlspecialchars($file) . '" target="_blank" class="tile-link">';
    }
    
    $tileHtml .= '<div class="tile-icon"><i class="fas ' . htmlspecialchars($icon) . '"></i></div>';
    $tileHtml .= '<div class="tile-name">' . htmlspecialchars($displayName) . '</div>';
    $tileHtml .= '<div class="tile-lines">' . $lineCount . ' lines</div>';
    
    if ($exists) {
        $tileHtml .= '</a>';
    } else {
        $tileHtml .= '<div class="tile-missing">⚠ Missing</div>';
        //return "";
    }
    
    $tileHtml .= '</div>';
    
    return $tileHtml;
} // showtile
?>
