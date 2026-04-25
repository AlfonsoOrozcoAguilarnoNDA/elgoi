<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
// ============================================
// ABYSSAL TRACKER - ARCHIVO CONSOLIDADO
// ============================================
// Incluir archivos de configuración y funciones
session_start();
include_once '../config.php';
include_once '../ui_functions.php';

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Aplicar seguridad
check_authorization();

// Determinar la sección actual (por defecto: dashboard)
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// ============================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================

$message = '';

// --- SECCIÓN: REGISTRAR RUN ---
if ($section === 'run_log' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_save'])) {
    $form_token = $_POST['form_token'] ?? '';
    $session_token = $_SESSION['run_token'] ?? '';

    if ($form_token && $form_token === $session_token) {
        unset($_SESSION['run_token']);
        $result = db_insert_abyssal_run($_POST);
        
        if ($result['success']) {
            $_SESSION['flash_message'] = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' . $result['message'] . '</div>';
            header('Location: ?section=run_log');
            exit;
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error al registrar la Run: ' . $result['message'] . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Esta Run ya fue procesada o el formulario es inválido.</div>';
    }
}

// --- SECCIÓN: NUEVO FIT ---
if ($section === 'fit_new' && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_save'])) {
    $result = db_insert_fit($_POST);
    
    if ($result['success']) {
        $message = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Fit "' . htmlspecialchars($_POST['nombre_corto']) . '" dado de alta con éxito.</div>';
    } else {
        $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error al dar de alta el Fit: ' . $result['message'] . '</div>';
    }
}

// --- SECCIÓN: DASHBOARD - Toggle Estado de Fit ---
if ($section === 'dashboard' && isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $fit_id = (int)$_GET['id'];
    $current_status = isset($_GET['status']) && strtoupper($_GET['status']) === 'YES' ? 'YES' : 'NO';
    $new_status = $current_status === 'YES' ? 'NO' : 'YES';

    $result = db_update_fit_status($fit_id, $new_status);
    
    if ($result['success']) {
        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> Estado del Fit ID ' . $fit_id . ' actualizado a <strong>' . $new_status . '</strong>.
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    } else {
        $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> Error: ' . $result['message'] . '
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>';
    }
}

// Mensajes Flash
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// ============================================
// GENERACIÓN DE HEADER Y NAVBAR
// ============================================
$page_titles = [
    'dashboard' => 'Dashboard - Abyssal Tracker',
    'run_log' => 'Registrar Nueva Run',
    'fit_new' => 'Dar de Alta Nuevo Fit',
    'weather_stats' => 'Weather Stats - Reporte por Tier'
];

echo ui_header($page_titles[$section] ?? 'Abyssal Tracker');
?>

<!-- BARRA DE NAVEGACIÓN PERSONALIZADA -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <a class="navbar-brand" href="?section=dashboard">
        <i class="fas fa-space-shuttle"></i> Abyssal Tracker
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item <?php echo ($section === 'dashboard') ? 'active' : ''; ?>">
                <a class="nav-link" href="?section=dashboard">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item <?php echo ($section === 'run_log') ? 'active' : ''; ?>">
                <a class="nav-link" href="?section=run_log">
                    <i class="fas fa-plus-square"></i> Registrar Run
                </a>
            </li>
            <li class="nav-item <?php echo ($section === 'fit_new') ? 'active' : ''; ?>">
                <a class="nav-link" href="?section=fit_new">
                    <i class="fas fa-rocket"></i> Nuevo Fit
                </a>
            </li>
            <li class="nav-item <?php echo ($section === 'weather_stats') ? 'active' : ''; ?>">
                <a class="nav-link" href="?section=weather_stats">
                    <i class="fas fa-cloud-sun"></i> Weather Stats
                </a>
            </li>
        </ul>
    </div>
</nav>

<!-- CONTENEDOR PRINCIPAL -->
<div class="container-fluid">
    <?php echo $message; ?>

    <?php
    // ============================================
    // RENDERIZADO DE SECCIONES
    // ============================================
    
    switch ($section) {
        case 'run_log':
            render_run_log_section();
            break;
        case 'fit_new':
            render_fit_new_section();
            break;
        case 'weather_stats':
            render_weather_stats_section();
            break;
        case 'dashboard':
        default:
            render_dashboard_section();
            break;
    }
    ?>
</div>

<?php
echo ui_footer();

// ============================================
// FUNCIONES DE RENDERIZADO
// ============================================

function render_run_log_section() {
    // Obtener datos para los combos
    $pilots = db_get_available_pilots();
    $fits = db_get_active_fits();
    $weather_options = ['Electrical' => 'Electrical', 'Firestorm' => 'Firestorm', 'Gamma' => 'Gamma', 'Exotic' => 'Exotic', 'Dark' => 'Dark'];
    $tier_options = array_combine(range(0, 6), range(0, 6));
    $dead_options = ['YES' => 'YES (Perdida)', 'NO' => 'NO (Sobrevivió)'];
    $skybreakers_options = array_combine(range(0, 3), range(0, 3));

    // Generar token
    if (!isset($_SESSION['run_token'])) {
        $_SESSION['run_token'] = db_generate_unique_token(20);
    }
    $new_token = $_SESSION['run_token'];
    ?>
    
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-plus-square"></i> Datos de la Carrera
        </div>
        <div class="card-body">
            <form method="POST" action="?section=run_log" id="runForm">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($new_token); ?>">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mt-1"><i class="fas fa-user-astronaut"></i> Piloto y Diseño</h5>
                        
                        <?php echo ui_generate_select('piloto_id', $pilots, 'Piloto', true, null); ?>
                        <?php echo ui_generate_select('fit_id', $fits, 'Fit Utilizado', true, null); ?>
                        
                        <hr>
                        <p class="text-muted">La Nave y la Clase serán leídas automáticamente desde la base de datos al guardar.</p>
                    </div>

                    <div class="col-md-6">
                        <h5 class="mt-1"><i class="fas fa-tasks"></i> Resultados de la Carrera</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <?php echo ui_generate_select('tier', $tier_options, 'Tier (Nivel)', true, 0); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo ui_generate_select('weather', $weather_options, 'Clima (Weather)', true, null); ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <?php echo ui_generate_select('dead', $dead_options, 'Resultado (Nave Perdida)', true, null); ?>
                            </div>
                            <div class="col-md-6">
                                <?php echo ui_generate_select('skybreakers_eliminados', $skybreakers_options, 'Skybreakers Eliminados', true, 0); ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="comentario_piloto"><i class="fas fa-comment"></i> Comentario del Piloto</label>
                            <textarea class="form-control" id="comentario_piloto" name="comentario_piloto" rows="3" placeholder="Comentario opcional sobre este run..."></textarea>
                        </div>
                    </div>
                </div>

                <hr>
                
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="confirm_save" name="confirm_save" value="1" required>
                    <label class="form-check-label" for="confirm_save">Estoy seguro de guardar esta nueva carrera.</label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Guardar Run</button>
            </form>
        </div>
    </div>
    <?php
}

function render_fit_new_section() {
    $ship_class_options = ['Frigate' => 'Frigate', 'Destroyer' => 'Destroyer', 'Cruiser' => 'Cruiser'];
    $active_options = ['YES' => 'YES (Activo)', 'NO' => 'NO (Inactivo)'];
    ?>
    
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <i class="fas fa-rocket"></i> Detalle del Nuevo Fit
        </div>
        <div class="card-body">
            <form method="POST" action="?section=fit_new">
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mt-1"><i class="fas fa-tag"></i> Identificación del Diseño</h5>

                        <div class="form-group">
                            <label for="nombre_corto">Nombre Corto del Fit (Máx. 40)</label>
                            <input type="text" class="form-control" id="nombre_corto" name="nombre_corto" maxlength="40" required>
                        </div>

                        <?php echo ui_generate_select('ship_class', $ship_class_options, 'Clase de Nave', true); ?>

                        <div class="form-group">
                            <label for="hull_ship">Nombre del Casco (Hull Ship)</label>
                            <input type="text" class="form-control" id="hull_ship" name="hull_ship" required>
                        </div>
                        
                        <?php echo ui_generate_select('activo', $active_options, 'Estado del Fit (Activo/Inactivo)', true); ?>
                    </div>

                    <div class="col-md-6">
                        <h5 class="mt-1"><i class="fas fa-clipboard-list"></i> Fit EFT</h5>
                        
                        <div class="form-group">
                            <label for="fit_eft">Fit en Formato EFT (Texto Completo Requerido)</label>
                            <textarea class="form-control" id="fit_eft" name="fit_eft" rows="5" required></textarea>
                        </div>
                        
                        <h5 class="mt-4"><i class="fas fa-money-bill-wave"></i> Valor Económico (Manual)</h5>
                        
                        <div class="form-group">
                            <label for="valor_hull">Valor ISK Total del Fit (DECIMAL 14,6)</label>
                            <input type="number" step="0.000001" class="form-control" id="valor_hull" name="valor_hull" value="0.00">
                        </div>

                        <div class="form-group">
                            <label for="fecha_jita">Fecha de Consulta de Valor (Jita)</label>
                            <input type="datetime-local" class="form-control" id="fecha_jita" name="fecha_jita" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="comentario"><i class="fas fa-comment"></i> Comentario General del Diseño</label>
                            <textarea class="form-control" id="comentario" name="comentario" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <hr>
                
                <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-plus"></i> Guardar Nuevo Fit</button>
            </form>
        </div>
    </div>
    <?php
}

function render_dashboard_section() {
    // Obtener filtro de clase de nave
    $ship_class_filter = isset($_GET['filter_class']) ? $_GET['filter_class'] : 'Todas';
    $available_classes = db_get_unique_ship_classes();

    // Obtener datos para el dashboard
    $top10_runs = db_get_top10_successful_runs();
    $top10_skybreakers = db_get_top10_skybreakers();
    $most_used_stats = db_get_most_used_stats();
    $fits_data = db_get_all_fits_with_skybreakers($ship_class_filter);

    // Definición del mapeo de colores y orden para climas
    $weather_colors = [
        'Dark' => 'badge-dark',
        'Exotic' => 'badge-primary',
        'Electrical' => 'badge-warning',
        'Gamma' => 'badge-success',
        'Firestorm' => 'badge-danger'
    ];
    $weather_order = ['Dark', 'Exotic', 'Electrical', 'Gamma', 'Firestorm'];
    ?>

    <!-- ESTADÍSTICAS GENERALES -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Estadísticas de Uso General</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <p class="h5">Clima Más Común (Weather)</p>
                            <h2 class="display-4"><?php echo htmlspecialchars($most_used_stats['weather']); ?></h2>
                        </div>
                        <div class="col-md-4">
                            <p class="h5">Nivel Más Común (Tier)</p>
                            <h2 class="display-4">T-<?php echo htmlspecialchars($most_used_stats['tier']); ?></h2>
                        </div>
                        <div class="col-md-4">
                            <p class="h5">Nave Más Usada (Hull)</p>
                            <h2 class="display-4"><?php echo htmlspecialchars($most_used_stats['hull_ship']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP 10 RANKINGS -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <?php
            echo ui_generate_ranking_card(
                "Top 10 Pilotos por Runs Exitosas",
                $top10_runs,
                "Runs Exitosas",
                "total_exitosos"
            );
            ?>
        </div>
        <div class="col-md-6 mb-4">
            <?php
            echo ui_generate_ranking_card(
                "Top 10 Pilotos por Skybreakers Eliminados",
                $top10_skybreakers,
                "Skybreakers Elim.",
                "total_skybreakers"
            );
            ?>
        </div>
    </div>

    <!-- GESTIÓN DE FITS -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-list-alt"></i> Lista de Fits (Ordenados por Éxito y Skybreakers)
            </h5>
        </div>
        <div class="card-body">
            <!-- Filtro por Clase de Nave -->
            <form method="GET" class="form-inline mb-3">
                <input type="hidden" name="section" value="dashboard">
                <label class="mr-2" for="filter_class"><strong>Filtrar por Clase:</strong></label>
                <select name="filter_class" id="filter_class" class="form-control mr-2" onchange="this.form.submit()">
                    <option value="Todas" <?php echo ($ship_class_filter === 'Todas') ? 'selected' : ''; ?>>Todas</option>
                    <?php foreach ($available_classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>" 
                                <?php echo ($ship_class_filter === $class) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
            </form>

            <!-- Tabla de Fits -->
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th>ID</th>
                            <th>Nombre Corto</th>
                            <th>Clase / Hull</th>
                            <th><i class="fas fa-check"></i> Éxitos</th>
                            <th><i class="fas fa-times"></i> Fracasos</th>
                            <th><i class="fas fa-crosshairs"></i> Skybreakers</th>
                            <th>Valor ISK</th>
                            <th>Éxitos por Clima</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fits_data)): ?>
                            <tr>
                                <td colspan="10" class="text-center">No hay Fits registrados para este filtro.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fits_data as $fit): 
                                $weather_counts = db_get_fit_weather_success_counts($fit['fit_id']);
                                $run_comments = db_get_fit_run_comments($fit['fit_id']);
                                
                                $is_active = $fit['activo'] === 'YES';
                                $status_class = $is_active ? 'badge-success' : 'badge-danger';
                                $btn_text = $is_active ? 'Desactivar' : 'Activar';
                                $btn_class = $is_active ? 'btn-danger' : 'btn-success';
                                $icon_class = $is_active ? 'fa-toggle-off' : 'fa-toggle-on';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fit['fit_id']); ?></td>
                                <td><strong><?php echo htmlspecialchars($fit['nombre_corto']); ?></strong></td>
                                <td><?php echo htmlspecialchars($fit['ship_class']) . ' (' . htmlspecialchars($fit['hull_ship']) . ')'; ?></td>
                                <td><?php echo number_format($fit['runs_exitosos']); ?></td>
                                <td><?php echo number_format($fit['runs_fracaso']); ?></td>
                                <td><span class="badge badge-info"><?php echo number_format($fit['total_skybreakers']); ?></span></td>
                                <td><?php echo number_format($fit['valor_hull'], 2) . ' ISK'; ?></td>
                                
                                <td>
                                    <?php 
                                    foreach ($weather_order as $weather_name): 
                                        $count = $weather_counts[$weather_name] ?? 0;
                                        $color_class = $weather_colors[$weather_name];
                                        if ($count > 0):
                                    ?>
                                        <span class="badge <?php echo $color_class; ?>" 
                                              title="<?php echo htmlspecialchars($weather_name); ?>: <?php echo $count; ?> éxitos">
                                            <?php echo $count; ?>
                                        </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </td>
                                
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($fit['activo']); ?></span></td>
                                
                                <td>
                                    <button type="button" 
                                            class="btn btn-sm btn-info mb-1" 
                                            data-toggle="modal" 
                                            data-target="#eftModal<?php echo $fit['fit_id']; ?>"
                                            title="Ver Fit EFT">
                                        <i class="fas fa-eye"></i> Ver EFT
                                    </button>
                                    
                                    <a href="?section=dashboard&action=toggle&id=<?php echo $fit['fit_id']; ?>&status=<?php echo $fit['activo']; ?>&filter_class=<?php echo urlencode($ship_class_filter); ?>" 
                                       class="btn btn-sm <?php echo $btn_class; ?> mb-1">
                                        <i class="fas <?php echo $icon_class; ?>"></i> <?php echo $btn_text; ?>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal para mostrar EFT y Comentarios -->
                            <div class="modal fade" id="eftModal<?php echo $fit['fit_id']; ?>" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title">
                                                <i class="fas fa-rocket"></i> Fit: <?php echo htmlspecialchars($fit['nombre_corto']); ?>
                                            </h5>
                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-info">
                                                <strong>Clase:</strong> <?php echo htmlspecialchars($fit['ship_class']); ?> | 
                                                <strong>Hull:</strong> <?php echo htmlspecialchars($fit['hull_ship']); ?>
                                            </div>
                                            
                                            <?php if (!empty($fit['comentario'])): ?>
                                            <div class="alert alert-warning">
                                                <h6><i class="fas fa-sticky-note"></i> <strong>Comentario del Fit:</strong></h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($fit['comentario'])); ?></p>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <h6><i class="fas fa-code"></i> Fit EFT:</h6>
                                            <pre class="bg-light p-3 border rounded" style="max-height: 400px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($fit['fit_eft']); ?></pre>
                                            
                                            <?php if (!empty($run_comments)): ?>
                                            <hr>
                                            <h6><i class="fas fa-comments"></i> Comentarios de Runs (Últimos 10):</h6>
                                            <div style="max-height: 300px; overflow-y: auto;">
                                                <?php foreach ($run_comments as $comment): 
                                                    $fecha_formato = date('d/m/Y H:i', strtotime($comment['fecha']));
                                                ?>
                                                <div class="card mb-2">
                                                    <div class="card-body p-2">
                                                        <small class="text-muted">
                                                            <strong><?php echo htmlspecialchars($comment['piloto']); ?></strong> 
                                                            - <?php echo $fecha_formato; ?> hrs
                                                        </small>
                                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($comment['comentario'])); ?></p>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                <i class="fas fa-times"></i> Cerrar
                                            </button>
                                            <button type="button" class="btn btn-primary" onclick="copyToClipboard<?php echo $fit['fit_id']; ?>()">
                                                <i class="fas fa-copy"></i> Copiar EFT
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                            function copyToClipboard<?php echo $fit['fit_id']; ?>() {
                                const eftText = <?php echo json_encode($fit['fit_eft']); ?>;
                                navigator.clipboard.writeText(eftText).then(function() {
                                    alert('Fit EFT copiado al portapapeles!');
                                }, function(err) {
                                    alert('Error al copiar: ' + err);
                                });
                            }
                            </script>
                            
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function render_weather_stats_section() {
    global $link;
    
    // DEFINIR COLORES PARA WEATHER
    $weather_colors = [
        'Electrical' => ['bg' => '#FFF9C4', 'text' => '#000000'],  // Amarillo pastel
        'Exotic'     => ['bg' => '#BBDEFB', 'text' => '#000000'],  // Azul pastel
        'Dark'       => ['bg' => '#424242', 'text' => '#FFFFFF'],  // Gris oscuro
        'Gamma'      => ['bg' => '#C8E6C9', 'text' => '#000000'],  // Verde pastel
        'Firestorm'  => ['bg' => '#FFCDD2', 'text' => '#000000']   // Rojo pastel
    ];

    // OBTENER PILOTOS Y SHIP_CLASS ÚNICOS
    $query_pilots = "SELECT DISTINCT p.toon_number, p.toon_name 
                     FROM abyssal_runs ar 
                     INNER JOIN PILOTS p ON ar.piloto_id = p.toon_number 
                     ORDER BY p.toon_name";
    $result_pilots = mysqli_query($link, $query_pilots);
    $pilots = [];
    while ($row = mysqli_fetch_assoc($result_pilots)) {
        $pilots[$row['toon_number']] = $row['toon_name'];
    }

    $query_classes = "SELECT DISTINCT ship_class FROM abyssal_runs WHERE ship_class != '' ORDER BY ship_class";
    $result_classes = mysqli_query($link, $query_classes);
    $ship_classes = [];
    while ($row = mysqli_fetch_assoc($result_classes)) {
        $ship_classes[] = $row['ship_class'];
    }

    // PROCESAR FILTROS
    $selected_pilot = '';
    $selected_class = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weather_filter'])) {
        $selected_pilot = $_POST['piloto_id'] ?? '';
        $selected_class = $_POST['ship_class'] ?? '';
    }

    // CONSTRUIR WHERE PARA FILTROS
    $where_conditions = [];

    if (!empty($selected_pilot) && $selected_pilot !== 'TODOS') {
        $where_conditions[] = "ar.piloto_id = " . (int)$selected_pilot;
    }

    if (!empty($selected_class) && $selected_class !== 'TODOS') {
        $escaped_class = mysqli_real_escape_string($link, $selected_class);
        $where_conditions[] = "ar.ship_class = '$escaped_class'";
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = ' AND ' . implode(' AND ', $where_conditions);
    }

    // OBTENER DATOS POR TIER
    $tiers_data = [];

    for ($tier = 0; $tier <= 6; $tier++) {
        $query = "
            SELECT 
                ar.tier,
                ar.weather,
                f.nombre_corto as fit_name,
                ar.dead,
                ar.skybreakers_eliminados,
                p.toon_name as piloto_name
            FROM abyssal_runs ar
            INNER JOIN fits f ON ar.fit_id = f.fit_id
            INNER JOIN PILOTS p ON ar.piloto_id = p.toon_number
            WHERE ar.tier = $tier
            $where_sql
            ORDER BY f.nombre_corto, ar.weather
        ";
        
        $result = mysqli_query($link, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $tiers_data[$tier] = [];
            
            while ($row = mysqli_fetch_assoc($result)) {
                $fit = $row['fit_name'];
                $weather = $row['weather'];
                $is_success = ($row['dead'] === 'NO');
                $skybreakers = (int)$row['skybreakers_eliminados'];
                $piloto = $row['piloto_name'];
                
                // Inicializar estructura
                if (!isset($tiers_data[$tier][$fit])) {
                    $tiers_data[$tier][$fit] = [];
                }
                
                if (!isset($tiers_data[$tier][$fit][$weather])) {
                    $tiers_data[$tier][$fit][$weather] = [
                        'exitosos' => 0,
                        'muertes' => 0,
                        'skybreakers' => []
                    ];
                }
                
                // Contabilizar
                if ($is_success) {
                    $tiers_data[$tier][$fit][$weather]['exitosos']++;
                } else {
                    $tiers_data[$tier][$fit][$weather]['muertes']++;
                }
                
                // Skybreakers
                if ($skybreakers > 0) {
                    if (!isset($tiers_data[$tier][$fit][$weather]['skybreakers'][$piloto])) {
                        $tiers_data[$tier][$fit][$weather]['skybreakers'][$piloto] = 0;
                    }
                    $tiers_data[$tier][$fit][$weather]['skybreakers'][$piloto] += $skybreakers;
                }
            }
        }
    }

    $current_datetime = date('d/m/Y H:i:s');
    ?>

    <div class="row mb-3">
        <div class="col-12">
            <a href="?section=dashboard" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-filter"></i> Filtros de Búsqueda
        </div>
        <div class="card-body">
            <form method="POST" action="?section=weather_stats">
                <input type="hidden" name="weather_filter" value="1">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="piloto_id"><i class="fas fa-user-astronaut"></i> Piloto</label>
                            <select class="form-control" id="piloto_id" name="piloto_id">
                                <option value="TODOS" <?php echo ($selected_pilot === 'TODOS' || empty($selected_pilot)) ? 'selected' : ''; ?>>
                                    ** TODOS **
                                </option>
                                <?php foreach ($pilots as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" 
                                        <?php echo ($selected_pilot == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="ship_class"><i class="fas fa-space-shuttle"></i> Tipo de Nave</label>
                            <select class="form-control" id="ship_class" name="ship_class">
                                <option value="TODOS" <?php echo ($selected_class === 'TODOS' || empty($selected_class)) ? 'selected' : ''; ?>>
                                    ** TODOS **
                                </option>
                                <?php foreach ($ship_classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class); ?>" 
                                        <?php echo ($selected_class === $class) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-search"></i> Aplicar Filtros
                </button>
            </form>
        </div>
    </div>

    <h3 class="mb-4"><i class="fas fa-chart-line"></i> Reporte de Runs Abyssal al <?php echo $current_datetime; ?></h3>

    <?php
    // MOSTRAR TABLAS POR TIER
    $weather_order = ['Electrical', 'Exotic', 'Dark', 'Gamma', 'Firestorm'];

    for ($tier = 0; $tier <= 6; $tier++) {
        if (!isset($tiers_data[$tier]) || empty($tiers_data[$tier])) {
            echo '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay runs de Tier ' . $tier . '</div>';
            continue;
        }
        
        $tier_info = $tiers_data[$tier];
        ?>
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-layer-group"></i> Tier <?php echo $tier; ?></h5>
            </div>
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table class="table table-bordered table-sm">
                        <thead class="thead-dark">
                            <tr>
                                <th style="min-width: 150px;">Fit</th>
                                <?php foreach ($weather_order as $weather): ?>
                                    <?php 
                                        $color = $weather_colors[$weather] ?? ['bg' => '#FFFFFF', 'text' => '#000000'];
                                    ?>
                                    <th style="background-color: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; text-align: center; min-width: 120px;">
                                        <?php echo htmlspecialchars($weather); ?>
                                    </th>
                                <?php endforeach; ?>
                                <th class="bg-success text-white" style="text-align: center; min-width: 100px;">Total Exitosos</th>
                                <th class="bg-info text-white" style="text-align: center; min-width: 100px;">Total (E/F)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totals_exitosos = array_fill_keys($weather_order, 0);
                            $totals_skybreakers = array_fill_keys($weather_order, 0);
                            
                            // Calcular totales de exitosos por fit para ordenar
                            $fits_with_totals = [];
                            foreach ($tier_info as $fit_name => $weathers) {
                                $fit_total_exitosos = 0;
                                foreach ($weathers as $weather => $data) {
                                    $fit_total_exitosos += $data['exitosos'];
                                }
                                $fits_with_totals[$fit_name] = $fit_total_exitosos;
                            }
                            
                            // Ordenar: primero por exitosos (descendente), luego alfabético
                            uasort($fits_with_totals, function($a, $b) {
                                if ($b != $a) {
                                    return $b - $a;
                                }
                                return 0;
                            });
                            uksort($tier_info, function($a, $b) use ($fits_with_totals) {
                                $total_a = $fits_with_totals[$a];
                                $total_b = $fits_with_totals[$b];
                                if ($total_b != $total_a) {
                                    return $total_b - $total_a;
                                }
                                return strcmp($a, $b);
                            });
                            
                            foreach ($tier_info as $fit_name => $weathers): 
                                $fit_total_exitosos = 0;
                                $fit_total_fracasos = 0;
                            ?>
                                <tr>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($fit_name); ?></td>
                                    
                                    <?php foreach ($weather_order as $weather): ?>
                                        <?php 
                                            $color = $weather_colors[$weather] ?? ['bg' => '#FFFFFF', 'text' => '#000000'];
                                            $data = $weathers[$weather] ?? ['exitosos' => 0, 'muertes' => 0, 'skybreakers' => []];
                                            
                                            $exitosos = $data['exitosos'];
                                            $muertes = $data['muertes'];
                                            $skybreakers = $data['skybreakers'];
                                            
                                            $fit_total_exitosos += $exitosos;
                                            $fit_total_fracasos += $muertes;
                                            
                                            $totals_exitosos[$weather] += $exitosos;
                                            
                                            foreach ($skybreakers as $count) {
                                                $totals_skybreakers[$weather] += $count;
                                            }
                                        ?>
                                        <td style="background-color: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; text-align: center; vertical-align: top;">
                                            <?php if ($exitosos > 0 || $muertes > 0): ?>
                                                <strong><?php echo $exitosos; ?> / <?php echo $muertes; ?></strong>
                                                <?php if (!empty($skybreakers)): ?>
                                                    <br><small>skybreakers:</small>
                                                    <?php foreach ($skybreakers as $piloto => $count): ?>
                                                        <br><small><?php echo htmlspecialchars($piloto); ?> <?php echo $count; ?></small>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="bg-success text-white" style="text-align: center; font-weight: bold;">
                                        <?php echo $fit_total_exitosos; ?>
                                    </td>
                                    <td class="bg-light" style="text-align: center; font-weight: bold;">
                                        <?php echo $fit_total_exitosos; ?> / <?php echo $fit_total_fracasos; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- FILA DE TOTALES EXITOSOS -->
                            <tr class="bg-warning">
                                <td style="font-weight: bold;"><i class="fas fa-check-circle"></i> Total Exitosos</td>
                                <?php 
                                $grand_total_exitosos = 0;
                                foreach ($weather_order as $weather): 
                                    $grand_total_exitosos += $totals_exitosos[$weather];
                                ?>
                                    <?php 
                                        $color = $weather_colors[$weather] ?? ['bg' => '#FFFFFF', 'text' => '#000000'];
                                    ?>
                                    <td style="background-color: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; text-align: center; font-weight: bold;">
                                        <?php echo $totals_exitosos[$weather]; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="bg-success text-white" style="text-align: center; font-weight: bold;">
                                    <?php echo $grand_total_exitosos; ?>
                                </td>
                                <td class="bg-success text-white" style="text-align: center; font-weight: bold;">
                                    <?php echo $grand_total_exitosos; ?>
                                </td>
                            </tr>
                            
                            <!-- FILA DE TOTAL SKYBREAKERS -->
                            <tr class="bg-warning">
                                <td style="font-weight: bold;"><i class="fas fa-star"></i> Total Skybreakers</td>
                                <?php 
                                $grand_total_skybreakers = 0;
                                foreach ($weather_order as $weather):
                                    $grand_total_skybreakers += $totals_skybreakers[$weather];
                                ?>
                                    <?php 
                                        $color = $weather_colors[$weather] ?? ['bg' => '#FFFFFF', 'text' => '#000000'];
                                    ?>
                                    <td style="background-color: <?php echo $color['bg']; ?>; color: <?php echo $color['text']; ?>; text-align: center; font-weight: bold;">
                                        <?php echo $totals_skybreakers[$weather]; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="bg-danger text-white" style="text-align: center; font-weight: bold; font-size: 0.8em;">
                                    N/A
                                </td>
                                <td class="bg-danger text-white" style="text-align: center; font-weight: bold;">
                                    <?php echo $grand_total_skybreakers; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    <?php } ?>
    
    <?php
} // render_weather_stats_section

// ===============================================
// FUNCIONES DE INSERCIÓN Y ACTUALIZACIÓN (CRUD)
// ===============================================

/**
 * Inserta un registro de Abyssal Run y actualiza los contadores del fit asociado.
 * Utiliza transacciones para asegurar que ambas operaciones (INSERT y UPDATE) sean atómicas.
 *
 * @param array $data Los datos del formulario $_POST.
 * @return array ['success' => bool, 'message' => string]
 */
function db_insert_abyssal_run($data) {
    global $link;
    $response = ['success' => false, 'message' => ''];

    // 1. Validar datos requeridos que NO pueden ser CERO (0) o vacíos
    // Excluimos 'tier' y 'skybreakers_eliminados' de esta verificación estricta.
    $strict_required_fields = ['piloto_id', 'fit_id', 'weather', 'dead'];
    foreach ($strict_required_fields as $field) {
        if (empty($data[$field])) {
            $response['message'] = "Falta el campo requerido: " . $field;
            return $response;
        }
    }

    // 2. OBTENER DATOS DERIVADOS DEL FIT (HULL y CLASE)
    $fit_id = (int)$data['fit_id'];
    $stmt_fit_data = $link->prepare("SELECT ship_class, hull_ship FROM fits WHERE fit_id = ?");
    $stmt_fit_data->bind_param("i", $fit_id);
    $stmt_fit_data->execute();
    $res_fit_data = $stmt_fit_data->get_result();

    if (!$row_fit = $res_fit_data->fetch_assoc()) {
        $response['message'] = "Error: El Fit ID {$fit_id} no existe en la base de datos.";
        $stmt_fit_data->close();
        return $response;
    }
    $stmt_fit_data->close();

    $ship_class = $row_fit['ship_class']; // Leído desde la DB
    $hull_ship = $row_fit['hull_ship'];   // Leído desde la DB
    
    // Asignar los valores del POST (tier y skybreakers ahora pueden ser 0)
    $piloto_id = (int)$data['piloto_id'];
    
    // Configurar zona horaria de México
    date_default_timezone_set('America/Mexico_City');
    $fecha = date('Y-m-d H:i:s');
    $fecha_display = date('d/m/Y H:i'); // Para mostrar al usuario
    
    // Usamos isset() para verificar si la clave existe, permitiendo valor 0.
    $tier = isset($data['tier']) ? (int)$data['tier'] : 0;
    $skybreakers = isset($data['skybreakers_eliminados']) ? (int)$data['skybreakers_eliminados'] : 0;
    
    // Resto de los datos del POST
    $weather = $data['weather'];
    $dead = $data['dead'];
    $comentario = !empty($data['comentario_piloto']) ? $data['comentario_piloto'] : null;

    // 3. Iniciar Transacción
    $link->begin_transaction();

    try {
        // --- A. INSERCIÓN EN abyssal_runs ---
        
        $stmt_run = $link->prepare("INSERT INTO abyssal_runs 
            (piloto_id, fit_id, fecha, tier, weather, ship_class, hull_ship, dead, skybreakers_eliminados, comentario_piloto) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // CORREGIDO: El último parámetro es 's' (string) no 'i' (integer)
        $stmt_run->bind_param("iisissssis", 
            $piloto_id, $fit_id, $fecha, $tier, $weather, $ship_class, $hull_ship, $dead, $skybreakers, $comentario);

        if (!$stmt_run->execute()) {
            throw new Exception("Error al insertar Run: " . $stmt_run->error);
        }
        
        // Obtener el ID del run insertado
        $run_id = $link->insert_id;
        
        $stmt_run->close();

        // --- B. ACTUALIZACIÓN DE CONTADORES EN fits ---

        $success_increment = ($dead === 'NO') ? 1 : 0;
        $failure_increment = ($dead === 'YES') ? 1 : 0;
        $piloto_name = '';

        // 1. Obtener el nombre del piloto
        $stmt_name = $link->prepare("SELECT toon_name FROM PILOTS WHERE toon_number = ?");
        $stmt_name->bind_param("i", $piloto_id);
        $stmt_name->execute();
        $res_name = $stmt_name->get_result();
        if ($row = $res_name->fetch_assoc()) {
            $piloto_name = $row['toon_name'];
        }
        $stmt_name->close();

        // 2. Consulta de Actualización
        $update_query = "
            UPDATE fits 
            SET 
                runs_exitosos = runs_exitosos + ?,
                runs_fracaso = runs_fracaso + ?,
                fecha_ultimo_run = ?,
                pilotos_que_usan = 
                    CASE 
                        WHEN pilotos_que_usan NOT LIKE CONCAT('%', ?, '%') 
                        THEN CONCAT(pilotos_que_usan, IF(pilotos_que_usan = '', '', ', '), ?)
                        ELSE pilotos_que_usan 
                    END
            WHERE fit_id = ?";

        $stmt_fit = $link->prepare($update_query);
        $stmt_fit->bind_param("iisssi", 
            $success_increment, $failure_increment, $fecha, 
            $piloto_name, $piloto_name, $fit_id);

        if (!$stmt_fit->execute()) {
            throw new Exception("Error al actualizar Fit: " . $stmt_fit->error);
        }
        $stmt_fit->close();
        
        // 4. Confirmar Transacción
        $link->commit();
        $response['success'] = true;
        $response['message'] = "Run #$run_id registrada exitosamente a las $fecha_display hrs (Horario de México)";

    } catch (Exception $e) {
        // En caso de cualquier error, revertir la transacción
        $link->rollback();
        $response['message'] = "Error en la transacción: " . $e->getMessage();
        error_log("Error de Transacción Abyssal Run: " . $e->getMessage());
    }

    return $response;
}

/**
 * Inserta un nuevo Fit en la tabla 'fits'.
 * Inicializa contadores a cero.
 *
 * @param array $data Los datos del formulario $_POST.
 * @return array ['success' => bool, 'message' => string]
 */
function db_insert_fit($data) {
    global $link;
    $response = ['success' => false, 'message' => ''];

    // 1. Sanitizar y mapear datos
    $nombre_corto = trim($data['nombre_corto']);
    $fit_eft = $data['fit_eft'];
    $ship_class = $data['ship_class'];
    $hull_ship = $data['hull_ship'];
    $activo = $data['activo'];
    $comentario = !empty($data['comentario']) ? $data['comentario'] : null;

    // Campos de valor ISK
    $valor_hull = (float)$data['valor_hull'];
    // Convertir el formato datetime-local a formato SQL DATETIME (si no está vacío)
    $fecha_jita = !empty($data['fecha_jita']) ? date('Y-m-d H:i:s', strtotime($data['fecha_jita'])) : null;

    // 2. Consulta de Inserción (Los campos derivados se inicializan en la tabla)
    $query = "INSERT INTO fits (
                nombre_corto, fit_eft, ship_class, hull_ship, activo, comentario, 
                valor_hull, fecha_jita
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $link->prepare($query);
    
    // Los tipos: s=string, d=double (para valor_hull), s=string (para fecha_jita)
    $stmt->bind_param("ssssssds", 
        $nombre_corto, $fit_eft, $ship_class, $hull_ship, $activo, $comentario, 
        $valor_hull, $fecha_jita);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Inserción exitosa.";
    } else {
        $response['message'] = "Error de inserción en tabla fits: " . $stmt->error;
        error_log("Error al insertar fit: " . $stmt->error);
    }

    $stmt->close();
    return $response;
}

// ===============================================
// FUNCIONES PARA EL DASHBOARD (Estadísticas)
// ===============================================

/**
 * Obtiene el Top 10 de pilotos con más runs exitosas (dead = 'NO').
 *
 * @return array Una lista de arrays asociativos (nombre, total_exitosos).
 */
function db_get_top10_successful_runs() {
    global $link;
    $query = "
        SELECT 
            p.toon_name AS nombre_piloto, 
            COUNT(r.run_id) AS total_exitosos
        FROM 
            abyssal_runs r
        JOIN 
            PILOTS p ON r.piloto_id = p.toon_number
        WHERE 
            r.dead = 'NO'
        GROUP BY 
            r.piloto_id, p.toon_name
        ORDER BY 
            total_exitosos DESC
        LIMIT 10
    ";
    
    $result = $link->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    }
    return $data;
}


/**
 * Obtiene el Top 10 de pilotos con más Skybreakers eliminados.
 *
 * @return array Una lista de arrays asociativos (nombre, total_skybreakers).
 */
function db_get_top10_skybreakers() {
    global $link;
    $query = "
        SELECT 
            p.toon_name AS nombre_piloto, 
            SUM(r.skybreakers_eliminados) AS total_skybreakers
        FROM 
            abyssal_runs r
        JOIN 
            PILOTS p ON r.piloto_id = p.toon_number
        GROUP BY 
            r.piloto_id, p.toon_name
        ORDER BY 
            total_skybreakers DESC
        LIMIT 10
    ";
    
    $result = $link->query($query);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $result->free();
    }
    return $data;
}


/**
 * Obtiene las estadísticas de uso: Most Used Weather, Tier, y Hull.
 *
 * @return array Un array con los elementos más usados (e.g., ['weather' => 'Gamma', 'tier' => 6, ...]).
 */
function db_get_most_used_stats() {
    global $link;
    $stats = [];
    
    // Función auxiliar para obtener el valor más frecuente de una columna
    $get_most_frequent = function($column) use ($link) {
        $query = "SELECT $column, COUNT(*) as count 
                  FROM abyssal_runs 
                  GROUP BY $column 
                  ORDER BY count DESC 
                  LIMIT 1";
        $result = $link->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            return $row[$column];
        }
        return 'N/A';
    };

    $stats['weather'] = $get_most_frequent('weather');
    $stats['tier'] = $get_most_frequent('tier');
    $stats['hull_ship'] = $get_most_frequent('hull_ship');
    
    return $stats;
}

// ===============================================
// FUNCIONES DE INTERACCIÓN CON LA BASE DE DATOS
// Requiere 'config.php' para usar la conexión global $link
// ===============================================

// Usar 'global $link;' dentro de cada función que acceda a la DB.

/**
 * Obtiene la lista filtrada de pilotos para la selección de runs.
 * Excluye pilotos con 'catalog' o 'VPS' en el nombre.
 *
 * @return array Un array asociativo de pilotos (toon_number => toon_name) o un array vacío si hay error.
 */
function db_get_available_pilots() {
    global $link;
    $pilots = [];

    // NOT LIKE '%catalog%' AND NOT LIKE '%VPS%' (case insensitive)
    $query = "SELECT toon_number, toon_name 
              FROM PILOTS 
              WHERE toon_name NOT LIKE '%catalog%' 
                AND toon_name NOT LIKE '%VPS%'
              ORDER BY toon_name ASC";
    
    $result = $link->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Usamos el toon_number como clave (ID) y toon_name como valor.
            $pilots[$row['toon_number']] = $row['toon_name'];
        }
        $result->free();
    } else {
        error_log("Error al obtener pilotos: " . $link->error);
    }
    
    return $pilots;
}

/**
 * Obtiene la lista de fits activos (YES) para la selección de runs.
 *
 * @return array Un array asociativo de fits (fit_id => nombre_corto) o un array vacío si hay error.
 */
function db_get_active_fits() {
    global $link;
    $fits = [];

    $query = "SELECT fit_id, nombre_corto, ship_class, hull_ship 
              FROM fits 
              WHERE activo = 'YES' 
              ORDER BY nombre_corto ASC";
    
    $result = $link->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Usamos fit_id como clave y nombre_corto como valor visible.
            $fits[$row['fit_id']] = $row['nombre_corto'];
        }
        $result->free();
    } else {
        error_log("Error al obtener fits activos: " . $link->error);
    }
    
    return $fits;
}

/**
 * Actualiza el estado 'activo' (YES/NO) de un fit.
 *
 * @param int $fit_id ID del fit a modificar.
 * @param string $status El nuevo estado ('YES' o 'NO').
 * @return array ['success' => bool, 'message' => string]
 */
function db_update_fit_status($fit_id, $status) {
    global $link;
    $response = ['success' => false, 'message' => ''];

    // Aseguramos que el estado sea válido
    $new_status = (strtoupper($status) === 'YES') ? 'YES' : 'NO';
    
    $stmt = $link->prepare("UPDATE fits SET activo = ? WHERE fit_id = ?");
    $stmt->bind_param("si", $new_status, $fit_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Estado actualizado a '{$new_status}'.";
    } else {
        $response['message'] = "Error al actualizar el estado: " . $stmt->error;
        error_log("Error al actualizar estado del fit: " . $stmt->error);
    }
    
    $stmt->close();
    return $response;
}

/**
 * Obtiene el conteo de runs exitosas (dead='NO') por tipo de clima para un fit específico.
 *
 * @param int $fit_id ID del fit.
 * @return array Array asociativo [weather => count] o array vacío.
 */
function db_get_fit_weather_success_counts($fit_id) {
    global $link;
    $counts = [];

    $query = "
        SELECT 
            weather, 
            COUNT(run_id) AS total_success
        FROM 
            abyssal_runs
        WHERE 
            fit_id = ? AND dead = 'NO'
        GROUP BY 
            weather
        ORDER BY 
            total_success DESC
    ";
    
    $stmt = $link->prepare($query);
    $stmt->bind_param("i", $fit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Almacena el conteo usando el nombre del clima como clave
        $counts[$row['weather']] = (int)$row['total_success'];
    }
    
    $stmt->close();
    return $counts;
}





/**
 * Obtiene todos los fits ordenados por runs exitosas DESC y skybreakers DESC (como segundo criterio).
 * Opcionalmente filtra por ship_class.
 *
 * @param string|null $ship_class_filter Filtro opcional por clase de nave ('Frigate', 'Destroyer', 'Cruiser', null para todas)
 * @return array Una lista de arrays asociativos con todos los datos del fit incluyendo skybreakers totales.
 */
function db_get_all_fits_with_skybreakers($ship_class_filter = null) {
    global $link;
    $data = [];

    // Consulta que incluye el total de skybreakers por fit y el comentario del fit
    $query = "
        SELECT 
            f.fit_id, 
            f.nombre_corto, 
            f.ship_class, 
            f.hull_ship, 
            f.runs_exitosos, 
            f.runs_fracaso, 
            f.valor_hull, 
            f.activo,
            f.fit_eft,
            f.comentario,
            COALESCE(SUM(r.skybreakers_eliminados), 0) AS total_skybreakers
        FROM 
            fits f
        LEFT JOIN 
            abyssal_runs r ON f.fit_id = r.fit_id
    ";
    
    // Agregar filtro si se especifica
    if ($ship_class_filter !== null && $ship_class_filter !== 'Todas') {
        $query .= " WHERE f.ship_class = ?";
    }
    
    $query .= "
        GROUP BY 
            f.fit_id
        ORDER BY 
            f.runs_exitosos DESC, 
            total_skybreakers DESC,
            f.runs_fracaso ASC
    ";
    
    if ($ship_class_filter !== null && $ship_class_filter !== 'Todas') {
        $stmt = $link->prepare($query);
        $stmt->bind_param("s", $ship_class_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
    } else {
        $result = $link->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $result->free();
        } else {
            error_log("Error al obtener fits con skybreakers: " . $link->error);
        }
    }
    
    return $data;
}

/**
 * Obtiene los comentarios de los runs de un fit específico.
 *
 * @param int $fit_id ID del fit.
 * @return array Lista de comentarios con fecha.
 */
function db_get_fit_run_comments($fit_id) {
    global $link;
    $comments = [];

    $query = "
        SELECT 
            r.comentario_piloto,
            r.fecha,
            p.toon_name
        FROM 
            abyssal_runs r
        JOIN
            PILOTS p ON r.piloto_id = p.toon_number
        WHERE 
            r.fit_id = ? 
            AND r.comentario_piloto IS NOT NULL
            AND r.comentario_piloto != ''
        ORDER BY 
            r.fecha DESC
        LIMIT 10
    ";
    
    $stmt = $link->prepare($query);
    $stmt->bind_param("i", $fit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'comentario' => $row['comentario_piloto'],
            'fecha' => $row['fecha'],
            'piloto' => $row['toon_name']
        ];
    }
    
    $stmt->close();
    return $comments;
}

/**
 * Obtiene las clases de naves únicas disponibles en fits.
 *
 * @return array Lista de clases de naves.
 */
function db_get_unique_ship_classes() {
    global $link;
    $classes = [];
    
    $query = "SELECT DISTINCT ship_class FROM fits ORDER BY ship_class ASC";
    $result = $link->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row['ship_class'];
        }
        $result->free();
    }
    
    return $classes;
}

/**
 * Genera una tabla HTML con la distribución de filamentos abisales por piloto
 * Muestra todos los filamentos (Tranquil, Calm, Agitated) agrupados por piloto
 * Los pilotos se ordenan por skillpoints descendente
 * 
 * @global mysqli $link Conexión a la base de datos
 * @return string HTML de la tabla completa
 */
function generar_tabla_filamentos() {
    global $link;
    
    // Definir colores pastel por pocket
    $pocket_colors = [
        'EXPER' => '#d4edda', // Verde pastel
        'CLEAN' => '#d1ecf1', // Azul pastel
        'NOKIA' => '#f8d7da', // Rojo pastel
        'SANGO' => '#fff3cd', // Amarillo pastel
        'LUCKY' => '#cccccc', // gris claro
    ];
    
    // 1. Obtener pilotos ordenados por skillpoints (que tengan filamentos)
    $query_pilotos = "
        SELECT DISTINCT p.toon_number, p.toon_name, p.pocket6, p.skillpoints
        FROM PILOTS p
        INNER JOIN EVE_ASSETS a ON p.toon_number = a.toon_number
        WHERE (a.type_description LIKE 'Tranquil %' 
               OR a.type_description LIKE 'Calm %' 
               OR a.type_description LIKE 'Agitated %')
        ORDER BY p.skillpoints DESC
    ";
    
    $result_pilotos = mysqli_query($link, $query_pilotos);
    $pilotos = [];
    while ($row = mysqli_fetch_assoc($result_pilotos)) {
        $pilotos[] = $row;
    }
    
    if (empty($pilotos)) {
        return '<div class="alert alert-info">No hay pilotos con filamentos registrados.</div>';
    }
    
    // 2. Obtener todos los tipos de filamentos únicos
    $query_filamentos = "
        SELECT DISTINCT type_description
        FROM EVE_ASSETS
        WHERE (type_description LIKE 'Tranquil %' 
               OR type_description LIKE 'Calm %' 
               OR type_description LIKE 'Agitated %')
        ORDER BY type_description
    ";
    
    $result_filamentos = mysqli_query($link, $query_filamentos);
    $filamentos = [];
    while ($row = mysqli_fetch_assoc($result_filamentos)) {
        $filamentos[] = $row['type_description'];
    }
    
    // 3. Obtener cantidades de filamentos por piloto y tipo
    $query_cantidades = "
        SELECT 
            toon_number,
            type_description,
            SUM(quantity) as total_quantity
        FROM EVE_ASSETS
        WHERE (type_description LIKE 'Tranquil %' 
               OR type_description LIKE 'Calm %' 
               OR type_description LIKE 'Agitated %')
        GROUP BY toon_number, type_description
    ";
    
    $result_cantidades = mysqli_query($link, $query_cantidades);
    $cantidades = [];
    while ($row = mysqli_fetch_assoc($result_cantidades)) {
        $cantidades[$row['type_description']][$row['toon_number']] = $row['total_quantity'];
    }
    
    // 4. Generar HTML de la tabla
    $html = '<div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white">
            <i class="fas fa-rocket"></i> Filaments in ' . count($pilotos) . ' pilots
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th style="background-color: #343a40; color: white;">Tipo de Filamento</th>
                            <th class="text-center" style="background-color: #343a40; color: white;">Total</th>';
    
    // Cabeceras de pilotos
    foreach ($pilotos as $piloto) {
        $pocket = htmlspecialchars($piloto['pocket6'] ?? '');
        $bg_color = $pocket_colors[$pocket] ?? '#ffffff';
        
        $html .= '<th class="text-center" style="background-color: #343a40; color: white;">';
        $html .= htmlspecialchars($piloto['toon_name']) . '<br>';
        $html .= '<small>' . $pocket . '</small>';
        $html .= '</th>';
    }
    
    $html .= '</tr></thead><tbody>';
    
    // Filas de filamentos
    foreach ($filamentos as $filamento) {
        $html .= '<tr>';
        
        // Columna de tipo de filamento (fondo dark)
        $html .= '<td style="background-color: #343a40; color: white;"><strong>' . htmlspecialchars($filamento) . '</strong></td>';
        
        // Calcular y mostrar total
        $total = 0;
        foreach ($pilotos as $piloto) {
            $total += $cantidades[$filamento][$piloto['toon_number']] ?? 0;
        }
        $html .= '<td class="text-center" style="background-color: #343a40; color: white;"><strong>' . $total . '</strong></td>';
        
        // Cantidades por piloto (con color de pocket)
        foreach ($pilotos as $piloto) {
            $cantidad = $cantidades[$filamento][$piloto['toon_number']] ?? 0;
            $pocket = $piloto['pocket6'] ?? '';
            $bg_color = $pocket_colors[$pocket] ?? '#ffffff';
            
            $html .= '<td class="text-center" style="background-color: ' . $bg_color . ';">';
            if ($cantidad > 0) {
                $html .= '<strong>' . $cantidad . '</strong>';
            }
            $html .= '</td>';
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>
            </div>
        </div>
    </div>';
    
    return $html;
}
/**
 * Genera una tabla de ranking para el dashboard.
 *
 * @param string $title Título del ranking.
 * @param array $data Los datos del ranking (ej: de db_get_top10_successful_runs).
 * @param string $metric_label Etiqueta de la columna de métrica (ej: 'Runs Exitosas').
 * @param string $metric_key La clave del array que contiene la métrica (ej: 'total_exitosos').
 * @return string El HTML de la tarjeta y la tabla.
 */
function ui_generate_ranking_card($title, $data, $metric_label, $metric_key) {
    $html = '<div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-trophy"></i> ' . htmlspecialchars($title) . '
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width: 10%;">#</th>
                        <th>Piloto</th>
                        <th style="width: 30%;">' . htmlspecialchars($metric_label) . '</th>
                    </tr>
                </thead>
                <tbody>';
    
    if (empty($data)) {
        $html .= '<tr><td colspan="3" class="text-center">No hay datos registrados aún.</td></tr>';
    } else {
        $rank = 1;
        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . $rank++ . '</td>';
            $html .= '<td>' . htmlspecialchars($row['nombre_piloto']) . '</td>';
            $html .= '<td>' . number_format($row[$metric_key], 0, '.', ',') . '</td>';
            $html .= '</tr>';
        }
    }

    $html .= '</tbody>
            </table>
        </div>
    </div>';
    return $html;
}

function db_generate_unique_token($length = 20) {
    $bytes = random_bytes($length);
    return substr(bin2hex($bytes), 0, $length);
}
?>
