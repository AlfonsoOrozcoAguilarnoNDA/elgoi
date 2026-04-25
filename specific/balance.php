<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include_once '../config.php';
include_once '../ui_functions.php';

check_authorization();
date_default_timezone_set('America/Mexico_City');

// ---------------------------------------------------------------------
// CREAR TABLA SI NO EXISTE
// ---------------------------------------------------------------------
$sql_create_economy = "
CREATE TABLE IF NOT EXISTS POCKET_ECONOMY (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pocket VARCHAR(50) NOT NULL,
    sistema TEXT NOT NULL,
    millones_isk DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";
mysqli_query($link, $sql_create_economy);

// ---------------------------------------------------------------------
// PROCESAR ACCIONES DE ECONOMÍA
// ---------------------------------------------------------------------
$mensaje = '';
$tipo_mensaje = '';

// AGREGAR REGISTRO
if (isset($_POST['action']) && $_POST['action'] === 'add_economy') {
    $pocket = mysqli_real_escape_string($link, trim($_POST['pocket']));
    $sistema = mysqli_real_escape_string($link, trim($_POST['sistema']));
    $millones = (float)$_POST['millones_isk'];

    if (!empty($pocket) && !empty($sistema)) {
        $sql = "INSERT INTO POCKET_ECONOMY (pocket, sistema, millones_isk) VALUES ('$pocket', '$sistema', $millones)";
        if (mysqli_query($link, $sql)) {
            $hora = date('H:i:s');
            $mensaje = "Se agregó sistema <strong>$sistema</strong> en <strong>$pocket</strong> a las $hora";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al agregar: " . mysqli_error($link);
            $tipo_mensaje = "danger";
        }
    }
}

// ACTUALIZAR REGISTRO
if (isset($_POST['action']) && $_POST['action'] === 'update_economy') {
    $id = (int)$_POST['id'];
    $millones = (float)$_POST['millones_isk'];

    $sql_info = "SELECT pocket, sistema FROM POCKET_ECONOMY WHERE id = $id";
    $result_info = mysqli_query($link, $sql_info);
    $info = mysqli_fetch_assoc($result_info);

    $sql = "UPDATE POCKET_ECONOMY SET millones_isk = $millones WHERE id = $id";
    if (mysqli_query($link, $sql)) {
        $fecha_hora = date('d/m/Y') . ' a las ' . date('H:i:s');
        $mensaje = "Sistema <strong>" . $info['sistema'] . "</strong> del pocket <strong>" . $info['pocket'] . "</strong> se actualizó a " . number_format($millones, 2) . " millones el $fecha_hora (México)";
        $tipo_mensaje = "info";
    } else {
        $mensaje = "Error al actualizar: " . mysqli_error($link);
        $tipo_mensaje = "danger";
    }
    mysqli_free_result($result_info);
}

// MODIFICAR CANTIDAD (SUMAR/RESTAR)
if (isset($_POST['action']) && $_POST['action'] === 'modify_economy') {
    $id = (int)$_POST['sistema_id'];
    $modificacion = (float)$_POST['modificacion'];

    $sql_info = "SELECT pocket, sistema, millones_isk FROM POCKET_ECONOMY WHERE id = $id";
    $result_info = mysqli_query($link, $sql_info);
    $info = mysqli_fetch_assoc($result_info);

    if ($info) {
        $valor_actual = (float)$info['millones_isk'];
        $nuevo_valor = $valor_actual + $modificacion;

        $sql = "UPDATE POCKET_ECONOMY SET millones_isk = $nuevo_valor WHERE id = $id";
        if (mysqli_query($link, $sql)) {
            $hora = date('H:i:s');
            $signo = $modificacion >= 0 ? '+' : '';
            $mensaje = "Sistema <strong>" . $info['sistema'] . "</strong> en <strong>" . $info['pocket'] . "</strong> modificado " . $signo . number_format($modificacion, 2) . ", nuevo total: <strong>" . number_format($nuevo_valor, 2) . "</strong> a las $hora";
            $tipo_mensaje = "success";
        } else {
            $mensaje = "Error al modificar: " . mysqli_error($link);
            $tipo_mensaje = "danger";
        }
    }
    mysqli_free_result($result_info);
}

// ELIMINAR REGISTRO
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];

    $sql_info = "SELECT pocket, sistema FROM POCKET_ECONOMY WHERE id = $id";
    $result_info = mysqli_query($link, $sql_info);
    $info = mysqli_fetch_assoc($result_info);

    $sql = "DELETE FROM POCKET_ECONOMY WHERE id = $id";
    if (mysqli_query($link, $sql)) {
        $fecha_hora = date('d/m/Y') . ' a las ' . date('H:i:s');
        $mensaje = "Sistema <strong>" . $info['sistema'] . "</strong> del pocket <strong>" . $info['pocket'] . "</strong> se eliminó el $fecha_hora (México)";
        $tipo_mensaje = "warning";
    } else {
        $mensaje = "Error al eliminar: " . mysqli_error($link);
        $tipo_mensaje = "danger";
    }
    mysqli_free_result($result_info);
}

// ---------------------------------------------------------------------
// OBTENER DATOS ECONOMÍA
// ---------------------------------------------------------------------
$pockets_disponibles = array('Other', 'Clean', 'Exper', 'Lucky', 'Nokia', 'Yenn', 'Sango');
$datos_por_pocket = array();
$gran_total = 0;

foreach ($pockets_disponibles as $pocket) {
    $pocket_escaped = mysqli_real_escape_string($link, $pocket);
    $sql = "SELECT * FROM POCKET_ECONOMY WHERE pocket = '$pocket_escaped' ORDER BY millones_isk DESC";
    $result = mysqli_query($link, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $registros = array();
        $total_pocket = 0;

        while ($row = mysqli_fetch_assoc($result)) {
            $registros[] = $row;
            $total_pocket += (float)$row['millones_isk'];
        }

        $datos_por_pocket[$pocket] = array(
            'registros' => $registros,
            'total' => $total_pocket
        );

        $gran_total += $total_pocket;
        mysqli_free_result($result);
    }
}

$colores_pocket = array(
    'Nokia' => '#ffcccc',
    'Yenn' => '#f5f5f5',
    'Exper' => '#ccffcc',
    'Sango' => '#ffffcc',
    'Clean' => '#4169e1',
    'Lucky' => '#d3d3d3',
    'Other' => '#ffd8b3'
);

// Todos los sistemas para el combo de modificar
$sql_todos_sistemas = "SELECT id, pocket, sistema, millones_isk FROM POCKET_ECONOMY ORDER BY pocket ASC, sistema ASC";
$result_todos_sistemas = mysqli_query($link, $sql_todos_sistemas);
$todos_sistemas = array();
if ($result_todos_sistemas) {
    while ($row = mysqli_fetch_assoc($result_todos_sistemas)) {
        $todos_sistemas[] = $row;
    }
    mysqli_free_result($result_todos_sistemas);
}

// IP y versión PHP
$user_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'IP desconocida';
$php_version = phpversion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>EVE Pocket Economy</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" crossorigin="anonymous">
    <style>
        body {
            padding-top: 70px;
            padding-bottom: 70px;
            background-color: #111;
            color: #f8f9fa;
        }
        .navbar-brand { font-weight: 600; }
        .btn-salir { color: #ffeb3b !important; }

        .footer-fixed {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 8px 15px;
            background-color: #222;
            color: #ddd;
            font-size: 0.9rem;
            border-top: 1px solid #444;
            z-index: 1030;
        }

        .form-dark {
            background-color: #222;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-dark label { color: #ddd; font-weight: 500; }
        .form-dark .form-control,
        .form-dark .form-control:focus {
            background-color: #333;
            border-color: #555;
            color: #fff;
        }

        .pocket-table { margin-bottom: 30px; }
        .pocket-header {
            padding: 12px 15px;
            border-radius: 8px 8px 0 0;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            color: #000;
        }
        .table-pocket-wrapper { border-radius: 0 0 8px 8px; overflow: hidden; }
        .table-pocket { color: #000; margin-bottom: 0; }
        .table-pocket th { background-color: rgba(0,0,0,0.15); font-weight: 600; border: none; }
        .table-pocket td { border: 1px solid rgba(0,0,0,0.1); }
        .table-pocket tbody tr:hover { background-color: rgba(0,0,0,0.05); }
        .pocket-total { background-color: rgba(0,0,0,0.2) !important; font-weight: 700; font-size: 1.05rem; }

        .gran-total-box {
            background-color: #222;
            padding: 25px;
            border-radius: 8px;
            border: 3px solid #0078d7;
            text-align: center;
            margin-bottom: 30px;
            margin-top: 30px;
        }
        .gran-total-box h3 { color: #0078d7; font-weight: 700; margin-bottom: 10px; }
        .gran-total-amount { font-size: 2.5rem; font-weight: 700; color: #00ff00; }

        .form-inline-edit { display: inline-flex; align-items: center; gap: 5px; }
        .form-inline-edit input { width: 110px; padding: 2px 6px; font-size: 0.9rem; }
        .btn-sm-custom { padding: 2px 8px; font-size: 0.85rem; }
    </style>
</head>
<body>
<?php echo crew_navbar(); ?>

<div class="container-fluid">

    <?php if (!empty($mensaje)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensaje; ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row" id="agregar">
        <div class="col-12 col-lg-6">
            <div class="form-dark">
                <h5><i class="fas fa-plus-circle"></i> Agregar Nuevo Sistema</h5>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_economy">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="pocket">Pocket *</label>
                            <select class="form-control" id="pocket" name="pocket" required>
                                <option value="">-- Selecciona --</option>
                                <option value="Other">Other</option>
                                <option value="Clean">Clean</option>
                                <option value="Exper">Exper</option>
                                <option value="Lucky">Lucky</option>
                                <option value="Nokia">Nokia</option>
                                <option value="Yenn">Yenn</option>
                                <option value="Sango">Sango</option>
                            </select>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="sistema">Sistema *</label>
                            <input type="text" class="form-control" id="sistema" name="sistema"
                                   placeholder="Ej: JITA, AMARR, etc." required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="millones_isk">Millones ISK *</label>
                            <input type="number" step="0.01" class="form-control" id="millones_isk"
                                   name="millones_isk" placeholder="0.00" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Agregar Sistema
                    </button>
                </form>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="form-dark">
                <h5><i class="fas fa-edit"></i> Modificar Cantidad de Sistema</h5>
                <?php if (empty($todos_sistemas)): ?>
                <div class="alert alert-warning">Primero debes agregar al menos un sistema.</div>
                <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="modify_economy">
                    <div class="form-group">
                        <label for="sistema_id">Sistema *</label>
                        <select class="form-control" id="sistema_id" name="sistema_id" required>
                            <option value="">-- Selecciona un sistema --</option>
                            <?php foreach ($todos_sistemas as $sys): ?>
                            <option value="<?php echo $sys['id']; ?>">
                                <?php echo htmlspecialchars($sys['pocket']); ?> - <?php echo htmlspecialchars($sys['sistema']); ?>
                                (actual: <?php echo number_format($sys['millones_isk'], 2); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modificacion">Cantidad a Sumar/Restar *</label>
                        <input type="number" step="0.01" class="form-control" id="modificacion"
                               name="modificacion" placeholder="Ej: +3.16 o -5.50" required>
                        <small class="text-muted">Usa números positivos para sumar (+3.16) o negativos para restar (-5.50)</small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator"></i> Modificar Cantidad
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($datos_por_pocket)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay sistemas registrados aún.
                <a href="#agregar" class="alert-link">Agrega tu primer sistema</a>.
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($datos_por_pocket as $pocket => $data): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="pocket-table">
                <div class="pocket-header" style="background-color: <?php echo $colores_pocket[$pocket]; ?>;">
                    <?php echo htmlspecialchars($pocket); ?>
                </div>
                <div class="table-pocket-wrapper" style="background-color: <?php echo $colores_pocket[$pocket]; ?>;">
                    <table class="table table-pocket table-sm table-bordered">
                        <thead>
                            <tr>
                                <th width="50">#</th>
                                <th>Sistema</th>
                                <th width="120">M ISK</th>
                                <th width="80">Acc.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['registros'] as $reg): ?>
                            <tr>
                                <td><?php echo $reg['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($reg['sistema']); ?></strong></td>
                                <td>
                                    <form method="POST" action="" class="form-inline-edit">
                                        <input type="hidden" name="action" value="update_economy">
                                        <input type="hidden" name="id" value="<?php echo $reg['id']; ?>">
                                        <input type="number" step="0.01" name="millones_isk"
                                               value="<?php echo number_format($reg['millones_isk'], 2, '.', ''); ?>"
                                               class="form-control form-control-sm" required>
                                        <button type="submit" class="btn btn-primary btn-sm-custom" title="Guardar">
                                            <i class="fas fa-save"></i>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <a href="?delete_id=<?php echo $reg['id']; ?>"
                                       class="btn btn-danger btn-sm-custom"
                                       title="Eliminar"
                                       onclick="return confirm('¿Eliminar <?php echo htmlspecialchars($reg['sistema']); ?>?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="pocket-total">
                                <td colspan="2" class="text-right"><strong>TOTAL:</strong></td>
                                <td colspan="2"><strong><?php echo number_format($data['total'], 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row" id="totales">
        <div class="col-12 col-lg-6 offset-lg-3">
            <div class="gran-total-box">
                <h3><i class="fas fa-wallet"></i> GRAN TOTAL</h3>
                <div class="gran-total-amount">
                    <?php echo number_format($gran_total, 2); ?> M ISK
                </div>
                <small class="text-muted">Suma de todos los pockets</small>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmarSalida() {
        return confirm('¿Seguro que deseas salir?');
    }
</script>
<?php echo ui_footer(); ?>
</body>
</html>
