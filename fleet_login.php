<?php
/**
 * Fleet Commander - Sistema de gestión de flota EVE Online
 * Login via ESI OAuth y gestión de pilotos secundarios
 * Deepseek modified
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * 30 marzo 2026
 */

session_start();

// Detectamos si es http o https
$protocol = $_SERVER['REQUEST_SCHEME'] ?? 'https';

// Obtenemos el dominio actual (ej: miservidor.com o localhost)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Definimos la constante de forma dinámica
define('ESI_CALLBACK_URL', "$protocol://$host/fleet_login.php");


define('ESI_AUTH_URL', 'https://login.eveonline.com/v2/oauth/authorize');
define('ESI_TOKEN_URL', 'https://login.eveonline.com/v2/oauth/token');
define('ESI_VERIFY_URL', 'https://login.eveonline.com/oauth/verify');

// Incluir configuración de BD
require_once 'config.php';
// Asume que $link ya está disponible como conexión MySQLi

// ============================================
// PROCESAR CALLBACK DE EVE ONLINE (OAuth)
// ============================================
if (isset($_GET['code'])) {
    // Intercambiar código por token
    $auth_code = $_GET['code'];
    
    $post_data = [
        'grant_type' => 'authorization_code',
        'code' => $auth_code,
        'client_id' => ESI_CLIENT_ID,
        'client_secret' => ESI_CLIENT_SECRET,
        'redirect_uri' => ESI_CALLBACK_URL
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ESI_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Host: login.eveonline.com'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $token_data = json_decode($response, true);
        $access_token = $token_data['access_token'];
        
        // Verificar token y obtener datos del personaje
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, ESI_VERIFY_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Host: login.eveonline.com'
        ]);
        
        $verify_response = curl_exec($ch);
        curl_close($ch);
        
        if ($verify_response) {
            $character_data = json_decode($verify_response, true);
            $character_id = $character_data['CharacterID'];
            $character_name = $character_data['CharacterName'];
            
            // ============================================
            // CORRECCIÓN: VERIFICAR SI EL FC YA EXISTE POR CHARACTER_ID
            // ============================================
            $query = "SELECT fleet_commander_number, pilot_name, character_id 
                      FROM fleet_commanders 
                      WHERE character_id = ? AND installation_id = 1 
                      LIMIT 1";
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "i", $character_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                // ============================================
                // FC YA REGISTRADO - DAR BIENVENIDA
                // ============================================
                $existing = mysqli_fetch_assoc($result);
                
                // Establecer variables de sesión
                $_SESSION['fleet_commander_number'] = $existing['fleet_commander_number'];
                $_SESSION['pilot_name'] = $existing['pilot_name'];
                $_SESSION['character_id'] = $existing['character_id'];
                $_SESSION['is_authenticated'] = true;
                $_SESSION['welcome_message'] = "Welcome back, Fleet Commander";
                $_SESSION['login_type'] = 'existing'; // Marcar como login existente
                
                // Actualizar último login (opcional)
                $update_query = "UPDATE fleet_commanders 
                               SET last_login = NOW() 
                               WHERE character_id = ?";
                $update_stmt = mysqli_prepare($link, $update_query);
                mysqli_stmt_bind_param($update_stmt, "i", $character_id);
                mysqli_stmt_execute($update_stmt);
                
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                
            } else {
                // ============================================
                // VERIFICAR SI EXISTE OTRO FC EN LA INSTALACIÓN
                // ============================================
                $check_query = "SELECT fleet_commander_number, pilot_name 
                              FROM fleet_commanders 
                              WHERE installation_id = 1 
                              LIMIT 1";
                $check_result = mysqli_query($link, $check_query);
                
                if (mysqli_num_rows($check_result) > 0) {
                    // Ya existe un FC diferente - Bloquear
                    $other_fc = mysqli_fetch_assoc($check_result);
                    $_SESSION['error_message'] = "Access Denied. Fleet Commander already registered: " . 
                        htmlspecialchars($other_fc['pilot_name']) . " (ID: " . 
                        htmlspecialchars($other_fc['fleet_commander_number']) . "). " .
                        "Only one Fleet Commander allowed per installation.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                    
                } else {
                    // ============================================
                    // NUEVO FC - REGISTRAR POR PRIMERA VEZ
                    // ============================================
                    $fc_number = 'FC-' . strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    $insert_query = "INSERT INTO fleet_commanders 
                                   (fleet_commander_number, pilot_name, character_id, 
                                    installation_id, created_at, last_login) 
                                   VALUES (?, ?, ?, 1, NOW(), NOW())";
                    $insert_stmt = mysqli_prepare($link, $insert_query);
                    mysqli_stmt_bind_param($insert_stmt, "ssi", 
                        $fc_number, $character_name, $character_id);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        // Establecer variables de sesión
                        $_SESSION['fleet_commander_number'] = $fc_number;
                        $_SESSION['pilot_name'] = $character_name;
                        $_SESSION['character_id'] = $character_id;
                        $_SESSION['is_authenticated'] = true;
                        $_SESSION['welcome_message'] = "Welcome Fleet Commander";
                        $_SESSION['login_type'] = 'new'; // Marcar como nuevo registro
                        
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $_SESSION['error_message'] = "Database error. Failed to register new Fleet Commander.";
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    }
                }
            }
        } else {
            $_SESSION['error_message'] = "Failed to verify character data.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Authentication failed. Please try again.";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// ============================================
// PROCESAR LOGOUT
// ============================================
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ============================================
// OBTENER MENSAJES DE SESIÓN
// ============================================
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$welcome_message = isset($_SESSION['welcome_message']) ? $_SESSION['welcome_message'] : '';
$login_type = isset($_SESSION['login_type']) ? $_SESSION['login_type'] : '';
$public_message = ''; // Placeholder: se leerá de base de datos posteriormente

// Limpiar mensajes de error después de mostrarlos
if (isset($_SESSION['error_message'])) {
    unset($_SESSION['error_message']);
}

// Variables de sesión para el template
$fc_number = isset($_SESSION['fleet_commander_number']) ? $_SESSION['fleet_commander_number'] : '';
$pilot_name = isset($_SESSION['pilot_name']) ? $_SESSION['pilot_name'] : '';
$is_authenticated = isset($_SESSION['is_authenticated']) && $_SESSION['is_authenticated'] === true;

// Generar URL de autorización de EVE Online
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$auth_params = [
    'response_type' => 'code',
    'redirect_uri' => ESI_CALLBACK_URL,
    'client_id' => ESI_CLIENT_ID,
    'scope' => 'publicData',
    'state' => $state
];

$login_url = ESI_AUTH_URL . '?' . http_build_query($auth_params);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Commander - EVE Online Fleet Management System</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            color: #c9d1d9;
        }
        
        .login-container {
            background: rgba(22, 27, 34, 0.95);
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #58a6ff;
            font-size: 28px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header .subtitle {
            color: #8b949e;
            font-size: 14px;
        }
        
        .restriction-notice {
            background: rgba(187, 128, 9, 0.15);
            border: 1px solid #bb8009;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .restriction-notice p {
            color: #d29922;
            font-size: 14px;
            font-weight: 500;
        }
        
        .error-message {
            background: rgba(248, 81, 73, 0.15);
            border: 1px solid #f85149;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #f85149;
            font-size: 14px;
            display: <?php echo empty($error_message) ? 'none' : 'block'; ?>;
        }
        
        .public-message {
            background: rgba(56, 139, 253, 0.15);
            border: 1px solid #388bfd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #58a6ff;
            font-size: 14px;
            display: <?php echo empty($public_message) ? 'none' : 'block'; ?>;
        }
        
        .welcome-section {
            text-align: center;
            padding: 30px 0;
        }
        
        .welcome-section h2 {
            color: #3fb950;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .welcome-section h2.returning {
            color: #58a6ff; /* Azul para returning */
        }
        
        .pilot-info {
            background: rgba(48, 54, 61, 0.5);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .pilot-info .label {
            color: #8b949e;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .pilot-info .value {
            color: #c9d1d9;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .login-badge {
            display: inline-block;
            background: rgba(88, 166, 255, 0.2);
            color: #58a6ff;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 20px;
        }
        
        .login-badge.new {
            background: rgba(63, 185, 80, 0.2);
            color: #3fb950;
        }
        
        .eve-login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #5865F2 0%, #4752C4 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .eve-login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(88, 101, 242, 0.4);
        }
        
        .enter-system-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #238636 0%, #1a6328 100%);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        
        .enter-system-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(35, 134, 54, 0.4);
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #30363d;
            color: #6e7681;
            font-size: 12px;
        }
        
        .divider {
            height: 1px;
            background: #30363d;
            margin: 25px 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h1>⚡ Fleet Commander</h1>
            <div class="subtitle">EVE Online Fleet Management System</div>
        </div>
        
        <!-- Mensaje de error desde POST/sesión -->
        <?php if (!empty($error_message)): ?>
        <div class="error-message">
            ⚠️ <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Mensaje público desde base de datos (placeholder) -->
        <?php if (!empty($public_message)): ?>
        <div class="public-message">
            ℹ️ <?php echo htmlspecialchars($public_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Nota de restricción (solo mostrar si no está autenticado) -->
        <?php if (!$is_authenticated): ?>
        <div class="restriction-notice">
            <p>⚠️ Only one Fleet Commander allowed per installation</p>
        </div>
        <?php endif; ?>
        
        <?php if ($is_authenticated): ?>
            <!-- Usuario autenticado - Mostrar bienvenida -->
            <div class="welcome-section">
                <!-- Badge de tipo de login -->
                <?php if ($login_type === 'new'): ?>
                    <span class="login-badge new">🆕 First Login</span>
                    <h2><?php echo htmlspecialchars($welcome_message); ?></h2>
                <?php else: ?>
                    <span class="login-badge">🔄 Returning</span>
                    <h2 class="returning"><?php echo htmlspecialchars($welcome_message); ?></h2>
                <?php endif; ?>
                
                <div class="pilot-info">
                    <div class="label">Fleet Commander ID</div>
                    <div class="value"><?php echo htmlspecialchars($fc_number); ?></div>
                    
                    <div class="label">Pilot Name</div>
                    <div class="value"><?php echo htmlspecialchars($pilot_name); ?></div>
                    
                    <div class="label">Character ID</div>
                    <div class="value"><?php echo htmlspecialchars($_SESSION['character_id']); ?></div>
                </div>
                
                <a href="mosaic.php" class="enter-system-btn">
                    🚀 Enter Fleet Command System
                </a>
                
                <div class="divider"></div>
                
                <a href="?logout=1" style="color: #f85149; text-decoration: none; font-size: 14px;">
                    ← Logout
                </a>
            </div>
        <?php else: ?>
            <!-- Usuario no autenticado - Mostrar login -->
            <a href="<?php echo htmlspecialchars($login_url); ?>" class="eve-login-btn">
                <span>🔐 Login with EVE Online</span>
            </a>
            
            <div class="footer">
                Secure authentication via EVE ESI OAuth<br>
                <small>Public Data Scope Only</small>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
