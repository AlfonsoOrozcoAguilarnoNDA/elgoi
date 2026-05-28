<?php
// EVE Online Market Price Checker
// Requiere: Bootstrap 4.6.x, Font Awesome 5.15.4 (CDN), PHP 8.x
// Conexión MySQLi en $link ya inicializada desde config.php (no se usa pero se mantiene por estructura)
// Licencia GPL
// Realizado por Alfonso Orozco Aguilar con ayuda de KIMI

// ============================================
// CONFIGURACIÓN DE REGIONES
// ============================================
$regiones = [
    'jita'      => ['id' => 10000002, 'nombre' => 'The Forge (Jita)',      'color' => 'primary',   'icono' => 'fa-globe'],
    'amarr'     => ['id' => 10000043, 'nombre' => 'Domain (Amarr)',        'color' => 'warning',   'icono' => 'fa-globe'],
    'rens'      => ['id' => 10000030, 'nombre' => 'Heimatar (Rens)',       'color' => 'success',   'icono' => 'fa-globe'],
    'dodixie'   => ['id' => 10000032, 'nombre' => 'Sinq Laison (Dodixie)', 'color' => 'info',      'icono' => 'fa-globe'],
    'hek'       => ['id' => 10000042, 'nombre' => 'Metropolis (Hek)',      'color' => 'secondary', 'icono' => 'fa-globe'],
];

// ============================================
// FUNCIÓN PRINCIPAL: Genera la fila de botones
// ============================================
function preciosmarket($typeID, $descripcion) {
    global $regiones;
    
    // Determinar icono según tipo de munición
    $iconoItem = (stripos($descripcion, 'missile') !== false || stripos($descripcion, 'rocket') !== false || stripos($descripcion, 'torpedo') !== false) 
        ? 'fa-rocket' 
        : 'fa-crosshairs';
    
    $html = '<div class="card mb-4 shadow-sm">';
    $html .= '  <div class="card-header bg-dark text-white">';
    $html .= '    <h5 class="mb-0"><i class="fas ' . $iconoItem . ' mr-2"></i>' . htmlspecialchars($descripcion) . ' <small class="text-muted">[ID: ' . (int)$typeID . ']</small></h5>';
    $html .= '  </div>';
    $html .= '  <div class="card-body">';
    $html .= '    <div class="row">';
    
    foreach ($regiones as $key => $region) {
        $url = 'https://market.fuzzwork.co.uk/region/' . $region['id'] . '/type/' . (int)$typeID . '/';
        $html .= '      <div class="col-12 col-sm-6 col-md-4 col-lg mb-2">';
        $html .= '        <a href="' . $url . '" target="_blank" class="btn btn-' . $region['color'] . ' btn-block">';
        $html .= '          <i class="fas ' . $region['icono'] . ' mr-1"></i> ' . $region['nombre'];
        $html .= '        </a>';
        $html .= '      </div>';
    }
    
    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVE Online - Market Price Checker</title>
    <!-- Bootstrap 4.6.2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <!-- Font Awesome 5.15.4 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous">
    <style>
        body { padding-top: 70px; padding-bottom: 60px; }
        .card-header h5 { font-weight: 600; }
        .btn { font-weight: 500; }
    </style>
</head>
<body>

    <!-- NAVBAR FIJO -->
    <nav class="navbar navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#">
            <i class="fas fa-space-shuttle mr-2"></i>
            <strong>EVE Market Checker</strong> <span class="badge badge-warning ml-2">Fuzzwork</span>
        </a>
        <span class="navbar-text text-light">
            <i class="fas fa-chart-line mr-1"></i> Precios por Región
        </span>
    </nav>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="container-fluid">
        
        <?php
        // ============================================
        // ARTÍCULOS: Edita aquí los IDs y descripciones
        // ============================================
        
        // Artículo 1: Antimatter Charge S
        echo preciosmarket(222, "Antimatter Charge S");
        
        // Artículo 2: (Edita ID y nombre)
        echo preciosmarket(230, "Antimatter Charge M");
        
        // Artículo 3: (Edita ID y nombre)
        echo preciosmarket(210, "Scourge Light Missile");
        ?>

    </div>

    <!-- FOOTER FIJO -->
    <footer class="bg-dark text-white text-center py-2 fixed-bottom">
        <small>
            <i class="fas fa-code mr-1"></i> PHP <?php echo phpversion(); ?> 
            <span class="mx-2">|</span>
            <i class="fas fa-database mr-1"></i> MySQL <?php echo isset($link) ? $link->server_info : 'N/A'; ?>
            <span class="mx-2">|</span>
            <i class="fas fa-gamepad mr-1"></i> EVE Online
        </small>
    </footer>

    <!-- Bootstrap 4.6.2 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct" crossorigin="anonymous"></script>
</body>
</html>
