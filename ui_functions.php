<?php
/* License MIT
Alfonso Orozco Agbuilar
*/
// ===============================================
// FUNCIONES DE INTERFAZ DE USUARIO (HTML/BOOTSTRAP)
// ===============================================

/**
 * Genera el pie de página HTML, incluyendo scripts de Bootstrap.
 *
 * @return string El HTML del pie de página.
 */

function ui_footer() {
$phpv= "<i class='fab fa-php mr-1'></i> PHP: <strong>".phpversion()."</strong>";
$server=htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? '?');
    $pass = "</div>
    <footer class='bg-dark text-white text-center py-3 fixed-bottom'>
        <div class='container'>
            <div class='mb-0'><div><i class='fas fa-network-wired mr-1'></i> IP: <strong>$server</strong> - $phpv
			</div>&copy; " . date('Y') . ' EVE Online Tools - Made with <i class="fas fa-heart text-danger"></i> for Capsuleers</div>
        </div>		
    </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
return $pass;
}

function ui_generate_select($name, $options, $label, $required = true, $default_value = null) {
    $required_attr = $required ? 'required' : '';
    
    $html = '<div class="form-group">
        <label for="' . htmlspecialchars($name) . '">' . htmlspecialchars($label) . '</label>
        <select class="form-control" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '" ' . $required_attr . '>
            <option value="">-- Seleccionar --</option>';
    
    foreach ($options as $value => $text) {
        // Si hay default_value, usarlo; si no, usar current_value (para edición)
        $selected = ($value == $default_value) ? 'selected' : '';
        $html .= '<option value="' . htmlspecialchars($value) . '" ' . $selected . '>' . htmlspecialchars($text) . '</option>';
    }
    
    $html .= '</select></div>';
    return $html;
}

/**
 * Genera el bloque de navegación con botones para Alta de Runs y Fits.
 * Ahora con scroll horizontal para muchos botones.
 *
 * @return string El HTML de la navegación.
 */
function ui_generate_navbar() {
    $html = '
    <style>
        .navbar-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            padding: 15px 0;
        }
        .navbar-scroll::-webkit-scrollbar {
            height: 8px;
        }
        .navbar-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .navbar-scroll::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .navbar-scroll::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .navbar-scroll .btn {
            margin: 0 5px;
            white-space: nowrap;
            display: inline-block;
        }
    </style>
    <div class="row mb-4">
        <div class="col-12">
            <div class="navbar-scroll text-center">
                <a href="index.php" class="btn btn-light btn-lg"><i class="fas fa-home"></i> Home</a>                
                <a href="skybreaker.php" class="btn btn-warning btn-lg"><i class="fas fa-list-alt"></i> Skybreaker</a>
                <a href="comparar.php" class="btn btn-dark btn-lg"><i class="fas fa-balance-scale"></i> Comparar Pilotos</a>
                <a href="newmarket.php" class="btn btn-info btn-lg"><i class="fas fa-chart-line"></i> Region Market</a>
                <a href="ammo.php" class="btn btn-primary btn-lg"><i class="fas fa-industry"></i> Gestión Industrial</a>
                <a href="jobs.php" class="btn btn-secondary btn-lg"><i class="fas fa-tasks"></i> Jobs</a>
                <a href="favoritos.php" class="btn btn-danger btn-lg"><i class="fas fa-star"></i> Favoritos</a>
                
            </div>
        </div>
    </div>
    <hr>';
    return $html;
}

// --- CONSTANTES DE ESTILO ---
const SKILL_ATTRIBUTE_COLORS = [
    'Cha/Int' => 'badge-info',
    'Int/Mem' => 'badge-warning',
    'Per/Wil' => 'badge-success',
    'Cha/Mem' => 'badge-secondary',
    'Per/Int' => 'badge-danger',
    'Mem/Wil' => 'badge-primary',
    'N/A'     => 'badge-light'
];

const CORP_DESCRIPTION_COLORS = [
    'Sisters of EVE' => 'bg-info text-white',
    'The Scope'      => 'bg-success text-white',
    'Militia'        => 'bg-warning',
    'Expert Housing' => 'bg-secondary text-white',
    'DEFAULT'        => ''
];

// --- FUNCIONES DE UI (HEAD, FOOTER, SELECTS) ---

function ui_header($title) {
    return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">    
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" crossorigin="anonymous">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        html {
            height: 100%;
        }
        body {
            min-height: 100%;
            display: flex;
            flex-direction: column;
			padding-bottom: 70px; /* Ajusta este valor según el alto de tu footer */
            margin: 0;
        }
        .container-fluid {
            flex: 1 0 auto;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table thead th {
            white-space: nowrap;
        }
        .card-header h5 {
            font-size: 1.1rem;
        }
        footer {
            flex-shrink: 0;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4">';
}

// --- FUNCIONES DE ESTILO DE CONTENIDO ---

/**
 * Función para renderizar el navbar principal del sistema EVE Online
 * 
 * @param string $currentPage Nombre del archivo actual (opcional, se detecta automáticamente)
 * @param array $crewParams Parámetros específicos de crew.php (view, orden, minNaves, minItems, minJitav)
 * @return void (imprime HTML directamente)
 */
function crew_navbar($currentPage = '', $crewParams = []) {
    // Detectar página actual si no se proporciona
    if (empty($currentPage)) {
        $currentPage = basename($_SERVER['PHP_SELF']);
    }
    
    // Parámetros por defecto para crew.php
    $view = isset($crewParams['view']) ? $crewParams['view'] : 'hangars';
    $orden = isset($crewParams['orden']) ? $crewParams['orden'] : 'naves_desc';
    $minNaves = isset($crewParams['minNaves']) ? $crewParams['minNaves'] : 2;
    $minItems = isset($crewParams['minItems']) ? $crewParams['minItems'] : 2;
    $minJitav = isset($crewParams['minJitav']) ? $crewParams['minJitav'] : 0.10;
    
    // Determinar si estamos en crew.php
    $isCrewPage = ($currentPage == 'crew.php');
    
    ?>
    <!-- NAVBAR FIJO -->
    <nav class='navbar navbar-expand-lg navbar-dark bg-dark fixed-top'>
        <a class='navbar-brand' href='crew.php'>
            <i class='fa fa-rocket'></i> EVE Panel
        </a>

        <button class='navbar-toggler' type='button' data-toggle='collapse' data-target='#menu'>
            <span class='navbar-toggler-icon'></span>
        </button>

        <div class='collapse navbar-collapse' id='menu'>
            <ul class='navbar-nav mr-auto'>
                
                <!-- DROPDOWN HERRAMIENTAS -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuHerramientas' role='button' data-toggle='dropdown'>
                        <i class='fa fa-tools'></i> Herramientas
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuHerramientas'>
                        <h6 class='dropdown-header'><i class='fa fa-chart-line'></i> Económico</h6>
                        <a class='dropdown-item' href='explotar.php'>
                            <i class='fa fa-database'></i> Explotar Inventario
                        </a>
                        <a class='dropdown-item' href='p1.php'>
                            <i class='fa fa-globe'></i> Existencias P1 Planetary
                        </a>
                        <a class='dropdown-item' href='stocks.php'>
                            <i class='fa fa-coins'></i> Pocket Economy
                        </a>
                        <a class='dropdown-item' href='newmarket.php'>
                            <i class='fa fa-shopping-cart'></i> Análisis de Mercado
                        </a>
                        <a class='dropdown-item' href='jobs.php'>
                            <i class='fa fa-industry'></i> Planetas y Jobs
                        </a>
                        
                        <div class='dropdown-divider'></div>
                        <h6 class='dropdown-header'><i class='fa fa-microscope'></i> Análisis</h6>
                        <a class='dropdown-item' href='ammo.php'>
                            <i class='fa fa-cube'></i> Ice y Minerales
                        </a>
                        <a class='dropdown-item' href='hangarall.php'>
                            <i class='fa fa-space-shuttle'></i> Filtro de Naves
                        </a>
                        <a class='dropdown-item' href='thesix.php'>
                            <i class='fa fa-users'></i> Análisis The Six
                        </a>
                        
                        <div class='dropdown-divider'></div>
                        <h6 class='dropdown-header'><i class='fa fa-star'></i> Especiales</h6>
                        <a class='dropdown-item' href='skybreaker.php' target='_blank'>
                            <i class='fa fa-portal-enter'></i> Abyss Tracker
                        </a>
                        <a class='dropdown-item' href='reasignar.php'>
                            <i class='fa fa-exchange-alt'></i> Reasignar Runs
                        </a>
                    </div>
                </li>
                
                <?php if ($isCrewPage) { ?>
                <!-- Toggle de Vista (SOLO en crew.php) -->
                <li class='nav-item view-toggle'>
                    <a href='?view=hangars' class='btn btn-sm <?php echo $view == "hangars" ? "btn-primary" : "btn-outline-light"; ?>'>
                        <i class='fa fa-warehouse'></i> Hangars
                    </a>
                    <a href='?view=assets' class='btn btn-sm <?php echo $view == "assets" ? "btn-primary" : "btn-outline-light"; ?>'>
                        <i class='fa fa-boxes'></i> Assets
                    </a>
                </li>

                <?php if ($view == 'hangars') { ?>
                <!-- Dropdown Ordenamiento HANGARS -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuOrden' role='button' data-toggle='dropdown'>
                        <i class='fa fa-sort'></i> Ordenar por
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuOrden'>
                        <a class='dropdown-item <?php echo $orden == 'naves_desc' ? 'active' : ''; ?>' 
                           href='?view=hangars&orden=naves_desc&min_naves=<?php echo $minNaves; ?>'>
                            <i class='fa fa-arrow-down-9-1'></i> Naves (Mayor a Menor)
                        </a>
                        <a class='dropdown-item <?php echo $orden == 'pocket' ? 'active' : ''; ?>' 
                           href='?view=hangars&orden=pocket&min_naves=<?php echo $minNaves; ?>'>
                            <i class='fa fa-users'></i> Por Pocket
                        </a>
                        <a class='dropdown-item <?php echo $orden == 'naves_pocket' ? 'active' : ''; ?>' 
                           href='?view=hangars&orden=naves_pocket&min_naves=<?php echo $minNaves; ?>'>
                            <i class='fa fa-list'></i> Naves + Pocket
                        </a>
                    </div>
                </li>

                <!-- Dropdown Filtro Mínimo NAVES -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuFiltro' role='button' data-toggle='dropdown'>
                        <i class='fa fa-filter'></i> Mín. Naves (<?php echo $minNaves; ?>)
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuFiltro'>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=0'>Todas (0+)</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=1'>1+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=2'>2+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=5'>5+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=10'>10+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=20'>20+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=50'>50+</a>
                        <a class='dropdown-item' href='?view=hangars&orden=<?php echo $orden; ?>&min_naves=100'>100+</a>
                    </div>
                </li>
                
                <?php } else { ?>
                <!-- Dropdown Ordenamiento ASSETS -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuOrden' role='button' data-toggle='dropdown'>
                        <i class='fa fa-sort'></i> Ordenar por
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuOrden'>
                        <a class='dropdown-item <?php echo $orden == 'items_desc' ? 'active' : ''; ?>' 
                           href='?view=assets&orden=items_desc&min_items=<?php echo $minItems; ?>&min_jitav=<?php echo $minJitav; ?>'>
                            <i class='fa fa-arrow-down-9-1'></i> Items (Mayor a Menor)
                        </a>
                        <a class='dropdown-item <?php echo $orden == 'jitav_desc' ? 'active' : ''; ?>' 
                           href='?view=assets&orden=jitav_desc&min_items=<?php echo $minItems; ?>&min_jitav=<?php echo $minJitav; ?>'>
                            <i class='fa fa-coins'></i> Valor ISK (Mayor a Menor)
                        </a>
                        <a class='dropdown-item <?php echo $orden == 'pocket' ? 'active' : ''; ?>' 
                           href='?view=assets&orden=pocket&min_items=<?php echo $minItems; ?>&min_jitav=<?php echo $minJitav; ?>'>
                            <i class='fa fa-users'></i> Por Pocket
                        </a>
                        <a class='dropdown-item <?php echo $orden == 'items_pocket' ? 'active' : ''; ?>' 
                           href='?view=assets&orden=items_pocket&min_items=<?php echo $minItems; ?>&min_jitav=<?php echo $minJitav; ?>'>
                            <i class='fa fa-list'></i> Items + Pocket
                        </a>
                    </div>
                </li>

                <!-- Dropdown Filtro Mínimo ITEMS -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuFiltroItems' role='button' data-toggle='dropdown'>
                        <i class='fa fa-filter'></i> Mín. Items (<?php echo $minItems; ?>)
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuFiltroItems'>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=0&min_jitav=<?php echo $minJitav; ?>'>Todos (0+)</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=1&min_jitav=<?php echo $minJitav; ?>'>1+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=2&min_jitav=<?php echo $minJitav; ?>'>2+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=5&min_jitav=<?php echo $minJitav; ?>'>5+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=10&min_jitav=<?php echo $minJitav; ?>'>10+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=50&min_jitav=<?php echo $minJitav; ?>'>50+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=100&min_jitav=<?php echo $minJitav; ?>'>100+</a>
                    </div>
                </li>

                <!-- Dropdown Filtro Valor ISK -->
                <li class='nav-item dropdown'>
                    <a class='nav-link dropdown-toggle' href='#' id='menuFiltroJitav' role='button' data-toggle='dropdown'>
                        <i class='fa fa-coins'></i> Mín. Valor (<?php echo $minJitav; ?>B)
                    </a>
                    <div class='dropdown-menu' aria-labelledby='menuFiltroJitav'>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=0'>Todos (>0)</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=0.10'>0.10B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=0.50'>0.50B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=1'>1B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=5'>5B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=10'>10B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=50'>50B+</a>
                        <a class='dropdown-item' href='?view=assets&orden=<?php echo $orden; ?>&min_items=<?php echo $minItems; ?>&min_jitav=100'>100B+</a>
                    </div>
                </li>
                <?php } ?>
                <?php } // Fin if crew.php ?>

            </ul>
            
            <!-- Botón Update -->
            <a href='https://elgoi.com/devauthcallback.php' 
               class='btn btn-success mr-2' 
               target='_blank'
               title='Actualizar datos ESI'>
                <i class='fa fa-user-plus'></i> Update
            </a>
            
            <!-- Botón Logout -->
            <a href='?module=logout' 
               class='btn btn-danger'
               onclick='return confirm("¿Seguro que deseas salir?");'>
                <i class='fa fa-sign-out-alt' style='color:yellow;'></i> 
                <span style='color:yellow;'>Salir</span>
            </a>
        </div>
    </nav>
    <?php
} // crew_navbar
/**
 * Obtiene una lista simple de pilotos (toon_number => toon_name) para los combos de comparación.
 *
 * @return array Lista de pilotos disponibles.
 */
function db_get_all_pilots_for_compare() {
    global $link;
    $pilots = [];

    // Obtenemos todos los pilotos, ordenados alfabéticamente
    $query = "SELECT toon_number, toon_name FROM PILOTS ORDER BY toon_name ASC";
    
    $result = $link->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pilots[$row['toon_number']] = $row['toon_name'];
        }
        $result->free();
    } else {
        error_log("Error al obtener pilotos para comparación: " . $link->error);
    }
    
    return $pilots;
}
function db_get_pilot_name_by_id($toon_number) {
    global $link;
    $data = null;
    
    $stmt = $link->prepare("SELECT toon_name, toon_number FROM PILOTS WHERE toon_number = ?");
    $stmt->bind_param("i", $toon_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $data = $row;
    }
    
    $stmt->close();
    return $data;
}

?>
