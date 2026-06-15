<?php
// 1. Conexión y Control de Datos
/*
Mecamnismo de control temporal de accounts por expansion
Licencia GPL
Experimento conjunto Gemini

https://vibecodingmexico.com/gemini-como-wikipedia/

Tabla de datos :

CREATE TABLE IF NOT EXISTS `sucursales_flota` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `numero_cuenta` INT(3) NOT NULL,
  `pseudo` VARCHAR(50) NOT NULL,
  `piloto_principal` VARCHAR(100) NOT NULL,
  `plex` INT(11) NOT NULL DEFAULT 0,
  `activar_hoy` ENUM('SI', 'NO') NOT NULL DEFAULT 'NO',
  `activos_redimibles` VARCHAR(255) DEFAULT 'Ninguno',
  `caso_especial` ENUM('SI', 'NO') NOT NULL DEFAULT 'NO',
  `notas_auditoria` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_numero_cuenta` (`numero_cuenta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

*/
// 1. Conexión y Control de Datos
require "../config.php";

// Sanitización rápida procedimental
function safe_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 2. Procesamiento del CRUD (Actualización en línea por Fila)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_row') {
    $id = (int)$_POST['id'];
    $plex = (int)$_POST['plex'];
    $activar_hoy = ($_POST['activar_hoy'] === 'SI') ? 'SI' : 'NO';
    $caso_especial = ($_POST['caso_especial'] === 'SI') ? 'SI' : 'NO';
    $activos_redimibles = mysqli_real_escape_string($link, safe_input($_POST['activos_redimibles']));
    $notas_auditoria = mysqli_real_escape_string($link, safe_input($_POST['notas_auditoria']));

    $update_query = "UPDATE `sucursales_flota` SET 
                        `plex` = $plex, 
                        `activar_hoy` = '$activar_hoy', 
                        `caso_especial` = '$caso_especial', 
                        `activos_redimibles` = '$activos_redimibles', 
                        `notas_auditoria` = '$notas_auditoria' 
                    WHERE `id` = $id";
    
    if (mysqli_query($link, $update_query)) {
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=success");
        exit;
    }
}

// 3. Persistencia de Filtros en la consulta
$filter_especial = isset($_GET['f_especial']) ? $_GET['f_especial'] : '';
$filter_activar  = isset($_GET['f_activar']) ? $_GET['f_activar'] : '';
$filter_plex     = isset($_GET['f_plex']) ? $_GET['f_plex'] : ''; // Nuevo filtro PLEX

$where_clauses = [];
if ($filter_especial !== '') {
    $where_clauses[] = "`caso_especial` = '" . mysqli_real_escape_string($link, $filter_especial) . "'";
}
if ($filter_activar !== '') {
    $where_clauses[] = "`activar_hoy` = '" . mysqli_real_escape_string($link, $filter_activar) . "'";
}
// Lógica para filtrar por volumen de PLEX
if ($filter_plex === 'mayor_cero') {
    $where_clauses[] = "`plex` > 0";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Consulta de datos ordenada por el ID escalonado
$query = "SELECT * FROM `sucursales_flota` $where_sql ORDER BY `id` ASC";
$result = mysqli_query($link, $query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría de Flota - Control Interno</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.7/css/dataTables.bootstrap4.min.css">
    <style>
        body { background-color: #f8f9fa; font-size: 0.9rem; }
        .table-card { background: #fff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .input-inline { min-width: 70px; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 text-secondary font-weight-bold">⚡ Sistema de Control de Flota (Auditoría Continua)</h2>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success py-1 px-3 mb-0" id="alert-msg">Registro actualizado con éxito.</div>
        <?php endif; ?>
    </div>

    <div class="card mb-4 table-card">
        <div class="card-body py-3">
            <form method="GET" action="" class="form-inline">
                <label class="mr-2 font-weight-bold" for="f_especial">Caso Especial:</label>
                <select name="f_especial" id="f_especial" class="form-control form-control-sm mr-4" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="SI" <?php echo ($filter_especial === 'SI') ? 'selected' : ''; ?>>SI</option>
                    <option value="NO" <?php echo ($filter_especial === 'NO') ? 'selected' : ''; ?>>NO</option>
                </select>

                <label class="mr-2 font-weight-bold" for="f_activar">Activar Hoy:</label>
                <select name="f_activar" id="f_activar" class="form-control form-control-sm mr-4" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="SI" <?php echo ($filter_activar === 'SI') ? 'selected' : ''; ?>>SI</option>
                    <option value="NO" <?php echo ($filter_activar === 'NO') ? 'selected' : ''; ?>>NO</option>
                </select>

                <label class="mr-2 font-weight-bold" for="f_plex">Filtrar PLEX:</label>
                <select name="f_plex" id="f_plex" class="form-control form-control-sm mr-4" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <option value="mayor_cero" <?php echo ($filter_plex === 'mayor_cero') ? 'selected' : ''; ?>>Mayor a 0</option>
                </select>

                <?php if ($filter_especial !== '' || $filter_activar !== '' || $filter_plex !== ''): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-sm btn-outline-danger">Limpiar Filtros</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="p-3 table-card">
        <table id="tablaFlota" class="table table-striped table-bordered table-hover table-sm w-100">
            <thead class="thead-dark">
                <tr>
                    <th width="5%">ID</th>
                    <th width="5%"># Cta</th>
                    <th width="10%">Pseudo</th>
                    <th width="10%">Piloto Principal</th>
                    <th width="8%">PLEX</th>
                    <th width="10%">Activar Hoy</th>
                    <th width="10%">Especial</th>
                    <th width="18%">Activos (Caso Especial)</th>
                    <th width="18%">Notas Auditoría</th>
                    <th width="6%">Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_row">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            
                            <td class="align-middle text-center font-weight-bold text-muted"><?php echo $row['id']; ?></td>
                            <td class="align-middle text-center"><?php echo $row['numero_cuenta']; ?></td>
                            <td class="align-middle"><strong><?php echo htmlspecialchars($row['pseudo']); ?></strong></td>
                            <td class="align-middle"><?php echo htmlspecialchars($row['piloto_principal']); ?></td>
                            
                            <td class="align-middle">
                                <input type="number" name="plex" class="form-control form-control-sm input-inline" value="<?php echo $row['plex']; ?>" required min="0">
                            </td>
                            
                            <td class="align-middle">
                                <select name="activar_hoy" class="form-control form-control-sm">
                                    <option value="NO" <?php echo ($row['activar_hoy'] === 'NO') ? 'selected' : ''; ?>>NO</option>
                                    <option value="SI" <?php echo ($row['activar_hoy'] === 'SI') ? 'selected' : ''; ?>>SI</option>
                                </select>
                            </td>
                            
                            <td class="align-middle">
                                <select name="caso_especial" class="form-control form-control-sm">
                                    <option value="NO" <?php echo ($row['caso_especial'] === 'NO') ? 'selected' : ''; ?>>NO</option>
                                    <option value="SI" <?php echo ($row['caso_especial'] === 'SI') ? 'selected' : ''; ?>>SI</option>
                                </select>
                            </td>
                            
                            <td class="align-middle">
                                <input type="text" name="activos_redimibles" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row['activos_redimibles']); ?>">
                            </td>
                            
                            <td class="align-middle">
                                <textarea name="notas_auditoria" class="form-control form-control-sm" rows="1"><?php echo htmlspecialchars($row['notas_auditoria']); ?></textarea>
                            </td>
                            
                            <td class="align-middle text-center">
                                <button type="submit" class="btn btn-sm btn-primary btn-block font-weight-bold">Guardar</button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.7/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    $('#tablaFlota').DataTable({
        "pageLength": 50,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        "ordering": true,
        "order": [[0, "asc"]],
        "language": {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando página _PAGE_ de _PAGES_ (Total: _TOTAL_ cuentas)",
            "infoEmpty": "No hay registros disponibles",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        }
    });

    setTimeout(function() {
        $('#alert-msg').fadeOut('slow');
    }, 3000);
});
</script>
</body>
</html>
