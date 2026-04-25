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

// Aplicar seguridad
check_authorization();

function aValues319($Qx){
global $link;    
    $rsX = mysqli_query($link,$Qx);// sqlerror("error checking avalues<hr>$Qx"); //  or die("<hr>Avalues 319<hr>$Qx");
    $Qx2=strtolower($Qx);
    if (left($Qx2,6)<>'select') return "";    
    $aDataX = array();
    $rows=mysqli_num_rows($rsX);
    if ($rows==0) return array("",""); 
        
        $Campos = mysqli_num_fields($rsX);
        while ($regX = mysqli_fetch_array($rsX)) {
            for($iX=0; $iX<$Campos; $iX++){
               $finfo=mysqli_fetch_field_direct($rsX,$iX);
               $name=$finfo->name;
                $aDataX[] = $regX[ $name ];
            }
        }
      // echo ($Qx ."/". $aDataX[0]);
    return $aDataX;
}
function left($str, $length) {
     return substr($str, 0, $length);
}

function right($str, $length) {
     return substr($str, -$length);
}



// Establecer zona horaria de México
//date_default_timezone_set('America/Mexico_City');

/**
 * Obtiene pilotos con jobs activos
 */
function obtenerPilotosConJobs() {
    global $link;
    
    $query = "SELECT toon_number, toon_name, jobs, skillpoints 
              FROM PILOTS 
              WHERE jobs != '[]' AND jobs IS NOT NULL 
              ORDER BY skillpoints DESC";
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        die("Error en la consulta de jobs: " . mysqli_error($link));
    }
    
    $jobs_data = [];
    $total_jobs = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data=stripslashes($row['jobs']);
        $jobs = json_decode($data, true);
        if (is_array($jobs) && count($jobs) > 0) {
            $total_jobs += count($jobs);
            $jobs_data[] = $row;
        }
    }
    
    mysqli_free_result($result);
    
    return ['data' => $jobs_data, 'total' => $total_jobs];
}

/**
 * Obtiene pilotos con planets activos
 */
function obtenerPilotosConPlanets() {
    global $link;
    
    $query = "SELECT toon_number, toon_name, planets, skillpoints 
              FROM PILOTS 
              WHERE planets != '[]' AND planets IS NOT NULL 
              ORDER BY skillpoints DESC";
    
    $result = mysqli_query($link, $query);
    
    if (!$result) {
        die("Error en la consulta de planets: " . mysqli_error($link));
    }
    
    $planets_data = [];
    $total_planets = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data=stripslashes($row['planets']);
        $planets = json_decode($data, true);
        if (is_array($planets) && count($planets) > 0) {
            $total_planets += count($planets);
            $planets_data[] = $row;
        }
    }
    
    mysqli_free_result($result);
    
    return ['data' => $planets_data, 'total' => $total_planets];
}

/**
 * Renderiza la tabla de Manufacturing Jobs
 */
function renderizarTablaJobs($jobs_info) {
    $jobs_data = $jobs_info['data'];
    $total_jobs = $jobs_info['total'];
    
    if (count($jobs_data) == 0) {
        return '<tr><td colspan="11" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> No hay jobs activos
                </td></tr>';
    }
    
    $html = '';
    $current_date = new DateTime('now', new DateTimeZone('UTC'));
    $csh=0;
    foreach ($jobs_data as $row) {
        $data=stripslashes($row['jobs']);
        $jobs = json_decode($data, true);
        
        if (is_array($jobs) && count($jobs) > 0) {
            foreach ($jobs as $job) {
                $csh++;
                $status_badge = $job['status'] == 'active' ? 'badge-success' : 'badge-info';
                $start_date = date('Y-m-d H:i', strtotime($job['start_date']));
                $end_date = date('Y-m-d H:i', strtotime($job['end_date']));
                $cost_formatted = number_format($job['cost'], 2);
                $skillpoints_formatted = number_format($row['skillpoints'] / 1000000, 2);
                
                // Calcular días restantes
                $end_datetime = new DateTime($job['end_date']);
                $diff = $current_date->diff($end_datetime);
                $days_remaining = $diff->invert == 0 ? $diff->days : -$diff->days;
                
                // Verificar si el trabajo está listo
                $is_ready = $end_datetime < $current_date;
                $row_class = $is_ready ? 'table-success font-weight-bold' : '';
                $where2=$job['facility_id'];
                list($where)=avalues319("select itemName from invUniqueNames where itemID='$where2'");
                if ($where=="") $where=$where2;
                $html .= "<tr class='{$row_class}'>";
                $html .= "<td><strong>$csh </strong></td>";
                $html .= "<td><strong>{$row['toon_name']}</strong><br><small class='text-muted'>#{$row['toon_number']}</small></td>";
                $html .= "<td>{$skillpoints_formatted}M</td>";
                $html .= "<td>$where</td>";
                $html .= "<td>".description($job['product_type_id'])."</td>";
                $html .= "<td>{$job['runs']}</td>";
                $html .= "<td>{$cost_formatted} ISK</td>";
                $html .= "<td><small>{$start_date}</small></td>";
                $html .= "<td><small>{$end_date}</small></td>";
                $html .= "<td><span class='badge {$status_badge}'>" . ucfirst($job['status']) . "</span></td>";
                $html .= "<td><strong>{$days_remaining}</strong> días</td>";
                $html .= "</tr>";
            }
        }
    }
    
    return $html;
}
function description($value){
//http://eve-files.com/chribba/typeid.txt
$sql="select typeName as description from invTypes where typeID='$value'";
     list($pass)=avalues319($sql);
/*     
    if ($value==54818) $pass="Capsuleer Day XVII Cap and T-Shirt Crate";
        
    https://market.fuzzwork.co.uk/type/23061/
    
*/      
        //if ($value==47911) $pass="Entropic Radiation Sink II";
     if ($pass=='') $pass=$value;
     $pass=addslashes($pass);
return $pass;
} // description
/**
 * Renderiza la tabla de Planetary Industry
 */
function renderizarTablaPlanets($planets_info) {
    $planets_data = $planets_info['data'];
    $total_planets = $planets_info['total'];
    
    if (count($planets_data) == 0) {
        return '<tr><td colspan="10" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> No hay planetas activos
                </td></tr>';
    }
    
    $html = '';
    $current_date = new DateTime('now', new DateTimeZone('UTC'));
    
    // Traducción de tipos de planeta
    $planet_types = [
        'temperate' => 'Temperate',
        'ice' => 'Ice',
        'oceanic' => 'Oceanic',
        'lava' => 'Lava',
        'barren' => 'Barren',
        'gas' => 'Gas',
        'storm' => 'Storm',
        'plasma' => 'Plasma'
    ];
    $csh=0;
    foreach ($planets_data as $row) {
        $data=stripslashes($row['planets']);
        $planets = json_decode($data, true);
        
        if (is_array($planets) && count($planets) > 0) {
            foreach ($planets as $planet) {
                 $csh++;
                $last_update = date('Y-m-d H:i', strtotime($planet['last_update']));
                $skillpoints_formatted = number_format($row['skillpoints'] / 1000000, 2);
                
                // Calcular días desde última actualización
                $last_update_datetime = new DateTime($planet['last_update']);
                $diff_days = $current_date->diff($last_update_datetime)->days;
                $is_outdated = $diff_days > 7;
                $row_class = $is_outdated ? 'table-danger font-weight-bold' : '';
                
                $planet_type = isset($planet_types[$planet['planet_type']]) 
                    ? $planet_types[$planet['planet_type']] 
                    : ucfirst($planet['planet_type']);
                
                
                $where2=$planet['planet_id'];
                list($where)=avalues319("select itemName from invUniqueNames where itemID='$where2'");
                if ($where=="") $where=$where2;
                
                $html .= "<tr class='{$row_class}'>";
                $html .= "<td><strong>$csh</strong></td>";
                $html .= "<td><strong>{$row['toon_name']}</strong><br><small class='text-muted'>#{$row['toon_number']}</small></td>";
                $html .= "<td>{$skillpoints_formatted}M</td>";
                $html .= "<td>$where</td>";
                $html .= "<td>{$planet_type}</td>";
                $html .= "<td>{$planet['solar_system_id']}</td>";
                $html .= "<td>{$planet['num_pins']}</td>";
                $html .= "<td>{$planet['upgrade_level']}</td>";
                $html .= "<td><small>{$last_update}</small></td>";
                $html .= "<td><strong>{$diff_days}</strong> días</td>";
                $html .= "</tr>";
            }
        }
    }
    
    return $html;
}

// Obtener datos
$jobs_info = obtenerPilotosConJobs();
$planets_info = obtenerPilotosConPlanets();

// Mostrar interfaz
echo ui_header("Manufacturing & Planetary Industry");
echo crew_navbar(); echo "<br />";
?>

<style>
.section-title {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 20px;
    margin-top: 20px;
    margin-bottom: 15px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.table-success {
    background-color: #d4edda !important;
}
.table-danger {
    background-color: #f8d7da !important;
}
.card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.badge-success {
    background-color: #28a745;
}
.badge-info {
    background-color: #17a2b8;
}
</style>

<div class="container-fluid mt-4">
    
    <!-- MANUFACTURING JOBS TABLE -->
    <h3 class="section-title">
        <i class="fas fa-industry"></i> Manufacturing Jobs 
        <span class="badge badge-light ml-2"><?php echo $jobs_info['total']; ?></span>
    </h3>
    
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fas fa-calculator"></i> Total</th>
                            <th><i class="fas fa-user"></i> Pilot</th>
                            <th><i class="fas fa-trophy"></i> Skillpoints</th>
                            <th><i class="fas fa-list-ol"></i> Facility ID</th>
                            <th><i class="fas fa-cube"></i> Product Type</th>
                            <th><i class="fas fa-play"></i> Runs</th>
                            <th><i class="fas fa-dollar-sign"></i> Cost</th>
                            <th><i class="fas fa-calendar-alt"></i> Start Date</th>
                            <th><i class="fas fa-calendar-check"></i> End Date</th>
                            <th><i class="fas fa-info-circle"></i> Status</th>
                            <th><i class="fas fa-hourglass-end"></i> Days Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderizarTablaJobs($jobs_info); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PLANETARY INDUSTRY TABLE -->
    <h3 class="section-title">
        <i class="fas fa-globe"></i> Planetary Industry
        <span class="badge badge-light ml-2"><?php echo $planets_info['total']; ?></span>
    </h3>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead class="thead-dark">
                        <tr>
                            <th><i class="fas fa-calculator"></i> Total</th>
                            <th><i class="fas fa-user"></i> Pilot</th>
                            <th><i class="fas fa-trophy"></i> Skillpoints</th>
                            <th><i class="fas fa-globe"></i> Planet ID</th>
                            <th><i class="fas fa-leaf"></i> Planet Type</th>
                            <th><i class="fas fa-map-marker-alt"></i> Solar System</th>
                            <th><i class="fas fa-thumbtack"></i> Pins</th>
                            <th><i class="fas fa-arrow-up"></i> Upgrade Level</th>
                            <th><i class="fas fa-clock"></i> Last Update</th>
                            <th><i class="fas fa-calendar-times"></i> Days Since Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php echo renderizarTablaPlanets($planets_info); ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="row mt-4 mb-4">
        <div class="col-md-6">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <strong>Jobs listos:</strong> Filas resaltadas en verde
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Planetas desactualizados:</strong> Más de 7 días sin actualizar (en rojo)
            </div>
        </div>
    </div>
</div>

<?php
echo ui_footer();

?>
