<?php
/*
License GPL 3.0
Alfonso Orozco Aguilar
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
session_start();
include_once '../config.php';
include_once '../ui_functions.php';

// Aplicar seguridad
check_authorization();

// 1. LÓGICA DE CONTROL DEL CHECKBOX
// Determinar si se debe ejecutar la explosión y actualización de precios
$should_explode = isset($_GET['explode']) && $_GET['explode'] == '1';

echo ui_header("Explode inventory accounts Eve online");
echo crew_navbar(); echo "<br /><br />";

// 2. FORMULARIO CHECKBOX (Controla la recarga de la página)
?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="form-inline">
            <div class="form-group mb-0 mr-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="explode_check" name="explode" 
                           <?php echo $should_explode ? 'checked' : ''; ?>>
                    <label class="form-check-label font-weight-bold mr-3" for="explode_check">
                        <i class="fas fa-hammer"></i> Habilitar Explosión de Inventario y Actualización de Precios (Lento)
                    </label>
                </div>
            </div>
            <div class="form-group mb-0 mr-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="filter_check" name="filter_low_value" 
                           <?php echo (isset($_GET['filter_low_value']) && $_GET['filter_low_value'] == '1') ? 'checked' : ''; ?>>
                    <label class="form-check-label font-weight-bold" for="filter_check">
                        <i class="fas fa-filter"></i> Filtrar pilotos con menos de 0.10 M ISK
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-redo"></i> Recargar
            </button>
        </form>
    </div>
</div>
<?php

// 3. SECCIÓN CONDICIONAL
if ($should_explode) {
    echo "<h2><i class='fas fa-exclamation-triangle text-danger'></i> Ejecución LENTA Activada</h2>";
    
    // --- INICIO: Explosión de Inventario ---
    $acco=$_SESSION['youremail'];
    $acco = "redrodac@gmail.com";
    echo "<h3>Exploding $acco items:</h3>";
    $report = update_fleet_assets_inventory($acco);
    $dummy = generate_assets_update_report($report); // no mostramos

    // --- FIN: Explosión de Inventario ---
    
    $sql="update EVE_ASSETS  set description='' where type_description=description and location_flag='Hangar'";
    list($dummy)=avalues319($sql);

    // La función actualizaprecios() dentro de generate_assets_update_report()
    // Ya contiene la llamada a la API y el JOIN masivo.
    
    // Si la explosión incluye la actualización de precios, la quitamos del final
    // y solo dejamos las correcciones de datos y el reporte final.
    
} else {
    echo "<h2><i class='fas fa-toggle-off text-muted'></i> Explosión Deshabilitada (Mostrando solo reporte)</h2>";
}

// Estas operaciones de corrección de datos y reporte final SÍ se ejecutan siempre,
// ya que usan los datos de EVE_ASSETS, sin importar si se explotaron ahora o antes.

$extra2= corregir_precios_cero_eve_assets();
$extra=aplicar_filtros_de_exclusion_eve_assets();
$filtrar_bajos = isset($_GET['filter_low_value']) && $_GET['filter_low_value'] == '1';
echo mostrar_resumen_pilotos_activos($filtrar_bajos) .$extra .$extra2;




echo ui_footer();


/**
 * Actualiza el inventario completo de assets para todos los pilotos de un owner
 * Procesa los campos assets1-5, los inserta en EVE_ASSETS y actualiza el contador numitems
 * 
 * @param string $owner_email - Email del propietario de los pilotos
 * @return array - ['updated' => cantidad_pilotos, 'total_items' => total_items_procesados, 'details' => array_detalles]
 */

function doaction($sql,$errormessage){
global $link;
mysqli_query($link,$sql);
sqlerror("$errormessage<hr>$sql");
}


function curl2file($url,$filesaved){
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);

// $response contains the XML response string from the API call
$response = curl_exec($ch);
curl_close($ch);
//die("solo 16");
// If curl_exec() fails/throws an error, the function will return false
if($response === false)
{
        // Could add some 404 headers here
        echo 'Curl error: ' . curl_error($ch);
}
else
{
//   file_put_contents($filesaved,$response);
return $response;
}
} // curl2file

function sqlerror($message){
global $link;
$error=mysqli_error($link);
if ($error=='') return; 
die ("$message<hr>$error");
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
function aValues319($Qx){
global $link;    
    $rsX = mysqli_query($link,$Qx); sqlerror("error checking avalues<hr>$Qx"); //  or die("<hr>Avalues 319<hr>$Qx");
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

/**
 * Actualiza el inventario completo de assets para todos los pilotos de un owner
 * Procesa los campos assets1-5, los inserta en EVE_ASSETS y actualiza el contador numitems
 * 
 * @param string $owner_email - Email del propietario de los pilotos
 * @return array - ['updated' => cantidad_pilotos, 'total_items' => total_items_procesados, 'details' => array_detalles]
 */
function update_fleet_assets_inventory($owner_email) {
    global $link;
    
    $updated_pilots = 0;
    $total_items_processed = 0;
    $details = [];
    
    // 1. OBTENER LISTA DE PILOTOS (Ordenados por numitems DESC, excluyendo "catalog")
    $sql = "SELECT toon_number, toon_name, pocket6, assets, assets2, assets3, assets4, assets5 
            FROM PILOTS 
            WHERE email_pilot = '" . mysqli_real_escape_string($link, $owner_email) . "' 
            AND toon_name NOT LIKE '%catalog%'
            ORDER BY numitems DESC";
    
    $rs = mysqli_query($link, $sql);
    
    if (!$rs) {
        sqlerror("Error obteniendo pilotos: " . $sql);
        return ['updated' => 0, 'total_items' => 0, 'error' => mysqli_error($link)];
    }
    
    // 2. PROCESAR CADA PILOTO
    while ($pilot = mysqli_fetch_object($rs)) {
        $toon_number = $pilot->toon_number;
        $toon_name = $pilot->toon_name;
        $pocket6 = $pilot->pocket6;
        $pilot_total_items = 0;
        
        // 3. DELETE de EVE_ASSETS para este piloto
        $delete_sql = "DELETE FROM EVE_ASSETS WHERE toon_number = $toon_number";
        mysqli_query($link, $delete_sql);
        sqlerror("Error eliminando assets del piloto $toon_number: " . $delete_sql);
        
        // 4. PROCESAR LOS 5 CAMPOS DE ASSETS
        $asset_fields = ['assets', 'assets2', 'assets3', 'assets4', 'assets5'];
        
        foreach ($asset_fields as $field) {
            $json_data = $pilot->$field;
            
            // Si el campo está vacío o es "[]", saltar
            if (empty($json_data) || $json_data == '[]') {
                continue;
            }
            
            // Quitar slashes y decodificar JSON
            $json_data = stripslashes($json_data);
            $assets_array = json_decode($json_data, true);
            
            // Si no se pudo decodificar o está vacío, saltar
            if (!is_array($assets_array) || empty($assets_array)) {
                continue;
            }
            
            // 5. INSERTAR CADA ASSET EN EVE_ASSETS
            foreach ($assets_array as $asset) {
                $item_id = $asset['item_id'] ?? 0;
                $location_flag = mysqli_real_escape_string($link, $asset['location_flag'] ?? '');
                $location_id = $asset['location_id'] ?? 0;
                $quantity = $asset['quantity'] ?? 0;
                $type_id = $asset['type_id'] ?? 0;
                
                $description="";
                if ($asset['location_type']=='station'){
                           list($description)=avalues319("select stationName from staStations where stationID='".$asset['location_id']."'");
                }
                
                // Obtener descripción del type_id (asumiendo que tienes una función para esto)
                $type_description = description($type_id); // Función que ya tienes
                $type_description = mysqli_real_escape_string($link, $type_description);
                
                $insert_sql = "INSERT INTO EVE_ASSETS 
                              (toon_number, location_flag, location_id, description, quantity, 
                               type_id, type_description, eveunique, date_insert, unit_price, forge_value)
                              VALUES 
                              ($toon_number, '$location_flag', $location_id, '$description', $quantity,
                               $type_id, '$type_description', $item_id, NOW(), 0, 0)";
                
                mysqli_query($link, $insert_sql);
                sqlerror("Error insertando asset: " . $insert_sql);
                
                $pilot_total_items++;
            }
        }
        
        // 6. ACTUALIZAR numitems EN PILOTS
        avalues319("UPDATE PILOTS SET numitems = $pilot_total_items WHERE toon_number = $toon_number");
        
        // 7. REGISTRAR DETALLES
        $details[] = [
            'toon_number' => $toon_number,
            'toon_name' => $toon_name,
            'pocket6' => $pocket6,
            'items_count' => $pilot_total_items
        ];
        
        $updated_pilots++;
        $total_items_processed += $pilot_total_items;
    }
    
    mysqli_free_result($rs);
    
    return [
        'updated' => $updated_pilots,
        'total_items' => $total_items_processed,
        'details' => $details
    ];
}


/**
 * Genera tabla HTML Bootstrap con los resultados de la actualización de inventario
 * 
 * @param array $result - Array retornado por update_fleet_assets_inventory()
 * @return string - HTML con tabla Bootstrap
 */
function generate_assets_update_report($result) {
    if (isset($result['error'])) {
        return '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error: ' . 
               htmlspecialchars($result['error']) . '</div>';
    }
    
    $html = '<div class="card shadow-lg mb-4">';
    $html .= '<div class="card-header bg-success text-white">';
    $html .= '<i class="fas fa-check-circle"></i> Actualización de Inventario Completada';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    
    // Resumen
    $html .= '<div class="row mb-4">';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="alert alert-info">';
    $html .= '<h5><i class="fas fa-users"></i> Pilotos Actualizados</h5>';
    $html .= '<h2 class="mb-0">' . number_format($result['updated']) . '</h2>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="col-md-6">';
    $html .= '<div class="alert alert-primary">';
    $html .= '<h5><i class="fas fa-boxes"></i> Items Procesados</h5>';
    $html .= '<h2 class="mb-0">' . number_format($result['total_items']) . '</h2>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Tabla detallada
    if (!empty($result['details'])) {
        $html .= '<h5 class="mt-4"><i class="fas fa-list"></i> Detalle por Piloto</h5>';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table table-striped table-bordered table-hover">';
        $html .= '<thead class="thead-dark">';
        $html .= '<tr>';
        $html .= '<th class="text-center">#</th>';
        $html .= '<th class="text-center">Toon Number</th>';
        $html .= '<th>Nombre del Piloto</th>';
        $html .= '<th class="text-center">Pocket</th>';
        $html .= '<th class="text-right">Items Procesados</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $row_num = 1;
        foreach ($result['details'] as $detail) {
            // Determinar color del badge según pocket6
            $pocket_class = 'badge-secondary';
            $pocket_value = $detail['pocket6'] ?? 'CLEAN';
            
            // Asignar colores según pocket (puedes ajustar estos)
            if (stripos($pocket_value, 'EXPER') !== false) {
                $pocket_class = 'badge-success';
            } elseif (stripos($pocket_value, 'NOKIA') !== false) {
                $pocket_class = 'badge-danger';
            } elseif (stripos($pocket_value, 'CLEAN') !== false) {
                $pocket_class = 'badge-primary';
            } elseif (stripos($pocket_value, 'LUCKY') !== false) {
                $pocket_class = 'badge-dark';
            } elseif (stripos($pocket_value, 'SANGO') !== false) {
                $pocket_class = 'badge-warning';
            }
            
            
            
            $html .= '<tr>';
            $html .= '<td class="text-center text-muted">' . $row_num++ . '</td>';
            $html .= '<td class="text-center"><code>' . $detail['toon_number'] . '</code></td>';
            $html .= '<td><strong>' . htmlspecialchars($detail['toon_name']) . '</strong></td>';
            $html .= '<td class="text-center">';
            $html .= '<span class="badge ' . $pocket_class . '">' . htmlspecialchars($pocket_value) . '</span>';
            $html .= '</td>';
            $html .= '<td class="text-right">';
            
            if ($detail['items_count'] > 0) {
                $html .= '<span class="badge badge-success badge-pill">' . 
                         number_format($detail['items_count']) . '</span>';
            } else {
                $html .= '<span class="badge badge-secondary badge-pill">0</span>';
            }
            
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    $html .=actualizaprecios();
    return $html;
    
}


/**
 * EJEMPLO DE USO
 */
/*
$owner_email = $_SESSION['youremail'];

$result = update_fleet_assets_inventory($owner_email);

// Generar y mostrar la tabla Bootstrap
echo generate_assets_update_report($result);
*/
// empezamos a actualizar precios
// Función auxiliar para actualizar masivamente EVE_ASSETS con un JOIN
function actualizar_eve_assets_masivamente(): bool {
    global $link;
    
    // Consulta para actualizar EVE_ASSETS usando los precios de jita_value
    // Este UPDATE es crucial y se realiza UNA SOLA VEZ al final.
    $sql_update_join = "
        UPDATE EVE_ASSETS AS T1
        INNER JOIN jita_value AS T2 ON T1.type_id = T2.type_id
        SET 
            T1.unit_price = T2.jita_value,
            T1.forge_value = CASE 
                                WHEN T2.jita_value < 0 THEN T2.jita_value 
                                ELSE T2.jita_value * T1.quantity
                            END,
            T1.date_insert = NOW()
        WHERE 
            T1.type_description NOT IN ('Revelation', 'Thanatos')
    ";
    
    // Usamos mysqli_query() para consultas sin datos de entrada variable
    $ejecucion_exitosa = mysqli_query($link, $sql_update_join);
    
    if ($ejecucion_exitosa === false) {
        echo "<div class='alert alert-danger'>❌ Error de UPDATE JOIN masivo: " . mysqli_error($link) . "</div>";
        return false;
    }
    
    return true;
}


// FUNCIÓN PRINCIPAL REQUERIDA
function actualizaprecios(): string {
    $start_time = microtime(true);
    set_time_limit(300); // Límite de 5 minutos
    global $link;
    
    $ids_procesados_count = 0;
    
    // 1. Obtener TODOS los DISTINCT(type_id) en una sola consulta
    $sql_distinct_types = "
        SELECT DISTINCT(type_id) 
        FROM EVE_ASSETS 
        WHERE type_description NOT IN ('Revelation', 'Thanatos')
    ";

    $resultado_tipos = mysqli_query($link, $sql_distinct_types);

    if ($resultado_tipos === false) {
        return "<div class='alert alert-danger' role='alert'><i class='fas fa-exclamation-triangle'></i> Error al obtener IDs únicos: " . mysqli_error($link) . "</div>";
    }

    // 2. Recolectar todos los IDs en un array
    $all_type_ids = [];
    while ($fila = mysqli_fetch_assoc($resultado_tipos)) {
        $all_type_ids[] = (int) $fila['type_id'];
    }
    mysqli_free_result($resultado_tipos);

    // 3. Dividir el array en lotes de 200 y llamar a la API
    $id_chunks = array_chunk($all_type_ids, 200);
    $lotes_procesados = 0;
    
    echo "<h3><i class='fas fa-exchange-alt'></i> Procesando Lotes de Fuzzwork API...</h3>";
    
    foreach ($id_chunks as $type_id_chunk) {
        // Llamar a la función optimizada para obtener e insertar precios en jita_value
        if (get_prices_item_fuzzwork200($type_id_chunk)) {
            $ids_procesados_count += count($type_id_chunk);
            $lotes_procesados++;
        }
    }

    // 4. Ejecutar la actualización masiva de EVE_ASSETS
    $update_success = false;
    if ($ids_procesados_count > 0) {
        echo "<h3><i class='fas fa-database'></i> Ejecutando Actualización Masiva de EVE_ASSETS...</h3>";
        $update_success = actualizar_eve_assets_masivamente();
    }
    
    // 5. Medir y reportar el tiempo final
    $end_time = microtime(true);
    $tiempo_transcurrido = round($end_time - $start_time, 2); 
    
    $mensaje_update = $update_success ? "Actualización de activos exitosa." : "Actualización de activos fallida o no necesaria.";

    // 6. Generar la tabla de resultados formateada en Bootstrap 4.x
    $html_resultado = "
        <hr>
        <div class='card shadow-sm'>
            <div class='card-header bg-success text-white'>
                <h5 class='mb-0'><i class='fas fa-check-circle'></i> Resumen Final de Precios (Optimizado)</h5>
            </div>
            <div class='card-body p-0'>
                <table class='table table-bordered table-sm mb-0'>
                    <thead class='thead-light'>
                        <tr>
                            <th><i class='fas fa-cubes'></i> Items Diferentes Procesados</th>
                            <th><i class='fas fa-layer-group'></i> Lotes API (Max 200)</th>
                            <th><i class='fas fa-clock'></i> Tiempo Empleado (segundos)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class='font-weight-bold'>{$ids_procesados_count}</td>
                            <td>{$lotes_procesados}</td>
                            <td>{$tiempo_transcurrido}s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class='card-footer text-muted'>
                <small>{$mensaje_update} Límite de ejecución: 5 minutos.</small>
            </div>
        </div>
    ";

    return $html_resultado;
}
// finalizamos actualizar precios ...

// Nota: Asume que $link, curl2file(), y doaction() están disponibles globalmente.
// Asegúrate de que esta función está definida ANTES de que se llame en actualizaprecios().

/**
 * Obtiene los precios de venta (sell percentile) de hasta 200 ítems de la API de Fuzzwork
 * y los inserta/actualiza en la tabla local jita_value.
 * * @param array $type_ids_array Array de hasta 200 type_id (enteros).
 * @return bool True si la comunicación con la API y la inserción/actualización en DB fueron exitosas.
 */
function get_prices_item_fuzzwork200(array $type_ids_array): bool {
    global $link;
    
    // 1. Validaciones iniciales
    if (empty($type_ids_array) || count($type_ids_array) > 200) {
        // Retorno explícito para evitar TypeError
        return false; 
    }

    $type_ids_string = implode(',', $type_ids_array);
    $turl = "https://market.fuzzwork.co.uk/aggregates/?region=10000002&types=" . $type_ids_string;

    // 2. Llamar a la API
    $respuesta = curl2file($turl, "fuzzprices_batch.json"); 
    $r = json_decode($respuesta, true);

    if (empty($r)) {
        echo "<div class='alert alert-warning'>⚠️ Fuzzwork API no devolvió datos para los IDs: {$type_ids_string}</div>";
        // Retorno explícito si la API falla o devuelve vacío
        return false; 
    }

    $sql_values = [];
    $insert_count = 0;

    // 3. Procesar la respuesta y preparar la inserción masiva
    foreach ($type_ids_array as $type_id) {
        $type_id = (int)$type_id; 
        $sellavg = -2; // Valor por defecto: nadie vendiendo

        if (isset($r[$type_id]['sell']['percentile'])) {
            $sellavg = (float)$r[$type_id]['sell']['percentile'];
        }
        
        $sql_values[] = "({$type_id}, {$sellavg}, NOW())";
        $insert_count++;
    }

    if (empty($sql_values)) {
        // Retorno explícito si el array de valores está vacío (aunque es poco probable a estas alturas)
        return false;
    }

    // 4. Ejecutar la inserción/actualización masiva en jita_value
    $sql_insert_mass = "
        INSERT INTO jita_value (type_id, jita_value, date_insert) 
        VALUES " . implode(', ', $sql_values) . "
        ON DUPLICATE KEY UPDATE jita_value = VALUES(jita_value), date_insert = VALUES(date_insert)
    ";

    $success = doaction($sql_insert_mass, "Error al insertar precios masivos en jita_value:");

    if ($success) {
        echo "<div class='alert alert-success alert-sm'>✅ {$insert_count} precios guardados/actualizados en **jita_value** desde Fuzzwork.</div>";
        // Retorno TRUE si la inserción fue exitosa
        return true; 
    }

    // 5. Retorno por defecto (si la inserción falla)
    return false; 
} // function get_prices_item_fuzzwork200
function corregir_precios_cero_eve_assets(): string {
    global $link;
    $start_time = microtime(true);
    
    // 1. Consulta UPDATE JOIN
    // Se actualizan los campos unit_price y forge_value de EVE_ASSETS (T1)
    // usando los valores de jita_value (T2).
    // La condición crucial es: T2.jita_value > 0
    $sql_correction = "
        UPDATE EVE_ASSETS AS T1
        INNER JOIN jita_value AS T2 
            ON T1.type_id = T2.type_id
        SET 
            T1.unit_price = T2.jita_value,
            -- El valor de forja se calcula como (precio * cantidad) solo si es positivo
            T1.forge_value = T2.jita_value * T1.quantity,
            T1.date_insert = NOW()
        WHERE 
            T2.jita_value > 0 
            AND T1.unit_price <= 0 -- Solo actualiza los que tienen precio cero o negativo
    ";
    
    // 2. Ejecutar la consulta (no requiere sentencia preparada ya que no hay input de usuario)
    $ejecucion_exitosa = mysqli_query($link, $sql_correction);
    
    // 3. Verificar resultados
    if ($ejecucion_exitosa === false) {
        $error_msg = mysqli_error($link);
        return "<div class='alert alert-danger' role='alert'>
                    <i class='fas fa-exclamation-triangle'></i> ERROR al corregir precios: {$error_msg}
                </div>";
    }
    
    $filas_afectadas = mysqli_affected_rows($link);
    $end_time = microtime(true);
    $tiempo_transcurrido = round($end_time - $start_time, 2);
    
    // 4. Generar el reporte Bootstrap 4.x
    $html_resultado = "
        <div class='card shadow-sm mt-3'>
            <div class='card-header bg-warning text-dark'>
                <h5 class='mb-0'><i class='fas fa-wrench'></i> Corrección de Precios Cero Completada</h5>
            </div>
            <div class='card-body p-0'>
                <table class='table table-bordered table-sm mb-0'>
                    <thead class='thead-light'>
                        <tr>
                            <th><i class='fas fa-sync'></i> Registros Corregidos (Filas Afectadas)</th>
                            <th><i class='fas fa-clock'></i> Tiempo Empleado (segundos)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class='font-weight-bold'>{$filas_afectadas}</td>
                            <td>{$tiempo_transcurrido}s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    ";
    
    return $html_resultado;
}

/**
 * Muestra una tabla con el nombre del piloto, Skill Points en millones, 
 * número de ítems y valor total de activos en millones.
 * @param bool $filtrar_minimo Si es true, solo muestra pilotos con >= 0.10 MM ISK
 * @return string HTML formateado con el reporte en Bootstrap 4.x.
 */
function mostrar_resumen_pilotos_activos($filtrar_minimo = false): string {
    global $link;
    
    // Consulta SQL optimizada con COALESCE
    $sql_reporte = "
        SELECT 
            T1.toon_name,
            T1.toon_number,
            ROUND(T1.skillpoints / 1000000, 2) AS sp_millones,
            T1.numitems,
            T1.pocket6,
            ROUND(COALESCE(SUM(T2.forge_value), 0) / 1000000, 2) AS valor_activos_millones
        FROM 
            PILOTS AS T1
        LEFT JOIN 
            EVE_ASSETS AS T2 ON T1.toon_number = T2.toon_number
        GROUP BY 
            T1.toon_number, T1.toon_name, T1.skillpoints, T1.numitems, T1.pocket6
        " . ($filtrar_minimo ? "HAVING valor_activos_millones > 0.10" : "") . "
        ORDER BY 
            T1.jitav DESC, T1.numitems DESC
    ";

    $resultado = mysqli_query($link, $sql_reporte);

    if ($resultado === false) {
        $error_msg = mysqli_error($link);
        return "<div class='alert alert-danger' role='alert'>
                    <i class='fas fa-exclamation-triangle'></i> Error en la consulta de reporte: {$error_msg}
                </div>";
    }

    $total_activos_millones = 0.00;
    $renglon_numero = 1;
    $total_pilotos = mysqli_num_rows($resultado);

    $titulo_filtro = $filtrar_minimo 
        ? "<span class='badge badge-warning ml-2'><i class='fas fa-filter'></i> Filtro: > 0.10 M ISK activo</span>" 
        : "";


    $html_tabla = "
        <div class='card shadow-sm mt-4'>
            <div class='card-header bg-primary text-white'>
                <h5 class='mb-0'>
                    <i class='fas fa-user-astronaut'></i> Resumen de Pilotos y Valor de Activos
                    {$titulo_filtro}
                </h5>
                <small class='text-white-50'>Mostrando {$total_pilotos} piloto(s)</small>
            </div>
            <div class='card-body p-0'>
                <table class='table table-striped table-bordered table-hover table-sm mb-0'>
                    <thead class='thead-dark'>
                        <tr>
                            <th>#</th>
                            <th><i class='fas fa-id-badge'></i> Piloto</th>
                            <th><i class='fas fa-brain'></i> SP (Millones)</th>
                            <th><i class='fas fa-box'></i> # Items</th>
                            <th><i class='fas fa-map-marker-alt'></i> Pocket6</th>
                            <th><i class='fas fa-money-bill-wave'></i> Activos (M ISK)</th>
                        </tr>
                    </thead>
                    <tbody>";

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $sp_format = number_format($fila['sp_millones'], 2, '.', ',');
        $activos_float = (float)$fila['valor_activos_millones'];
        $activos_format = number_format($activos_float, 2, '.', ',');
        
        $total_activos_millones += $activos_float;


$pocket_class = 'badge-secondary';
            $pocket_value = $fila['pocket6'] ?? 'CLEAN';
            
            // Asignar colores según pocket (puedes ajustar estos)
            if (stripos($pocket_value, 'EXPER') !== false) {
                $pocket_class = 'badge-success';
            } elseif (stripos($pocket_value, 'NOKIA') !== false) {
                $pocket_class = 'badge-danger';
            } elseif (stripos($pocket_value, 'CLEAN') !== false) {
                $pocket_class = 'badge-primary';
            } elseif (stripos($pocket_value, 'LUCKY') !== false) {
                $pocket_class = 'badge-dark';
            } elseif (stripos($pocket_value, 'SANGO') !== false) {
                $pocket_class = 'badge-warning';
            }
            


        // Resaltar valores altos en verde, valores muy bajos en rojo
        if ($activos_float >= 1000.00) {
            $valor_class = 'text-success font-weight-bold';
        } elseif ($activos_float <= 0.10) {
            $valor_class = 'text-danger font-italic';
        } else {
            $valor_class = '';
        }

        $fil=$_GET['filter_low_value'] ?? 0;
        if ($fil==0 or $activos_float > 0.10){
        $number=$fila['toon_number'];
            $html_tabla .= "
                <tr>
                    <td>{$renglon_numero}</td>
                    <td><a target='_blank' href='alphaassets.php?mode=assets&who=$number'>" . htmlspecialchars($fila['toon_name']) . "</a></td>
                    <td>{$sp_format} M</td>
                    <td>" . number_format($fila['numitems']) . "</td>                
                    <td><span class='badge $pocket_class '>$pocket_value</span></td>
                    <td class='{$valor_class}'>{$activos_format}</td>
                </tr>";
            
            $renglon_numero++;
        }
    }

    $total_format = number_format($total_activos_millones, 2, '.', ',');

    $html_tabla .= "
                    </tbody>
                    <tfoot>
                        <tr class='bg-light font-weight-bold'>
                            <td colspan='5' class='text-right'>TOTAL ACTIVO DE TODOS LOS PILOTOS:</td>
                            <td>{$total_format} M</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>";

    mysqli_free_result($resultado);

    return $html_tabla;
}// --- Ejemplo de Uso ---
// echo mostrar_resumen_pilotos_activos();
/**
 * Aplica las reglas de exclusión (filtros) actualizando unit_price y forge_value 
 * con valores negativos en la tabla EVE_ASSETS.
 * * CORRECCIÓN: Busca 'Women\'s' y 'Men\'s' para manejar datos guardados con addslashes.
 * * * @return string Mensaje HTML con el resultado de la operación.
 */
function aplicar_filtros_de_exclusion_eve_assets(): string {
    global $link;
    $start_time = microtime(true);
    $ajustes_aplicados = 0;
    
    // Reglas de exclusión:
    $reglas = [
        // 1. Blueprints (-3) (Sin cambio, la barra invertida no afecta)
        "UPDATE EVE_ASSETS 
         SET unit_price = -3, forge_value = -3 
         WHERE type_description LIKE '%Blueprint%' AND unit_price >= 0",
         
        // 2. SKINS (-4) (Sin cambio)
        "UPDATE EVE_ASSETS 
         SET unit_price = -4, forge_value = -4
         WHERE type_description LIKE '%SKIN%' AND unit_price >= 0",
         
        // 3. Wardrobe - Women's (-5) -> CORRECCIÓN: Eliminar la barra invertida del dato para buscar 'Women's'
        "UPDATE EVE_ASSETS 
         SET unit_price = -5, forge_value = -5
         WHERE REPLACE(type_description, '\\\\\'', '\'') LIKE '%Women\'s%' AND unit_price >= 0",
         
        // 4. Wardrobe - Men's (-5) -> CORRECCIÓN: Eliminar la barra invertida del dato para buscar 'Men's'
        "UPDATE EVE_ASSETS 
         SET unit_price = -5, forge_value = -5
         WHERE REPLACE(type_description, '\\\\\'', '\'') LIKE '%Men\'s%' AND unit_price >= 0",
         
        // 5. Prizes (NEO YC) (-6) (Sin cambio)
        "UPDATE EVE_ASSETS 
         SET unit_price = -6, forge_value = -6
         WHERE type_description LIKE '%NEO YC%' AND unit_price >= 0",
         
        // 6. Wardrobe específico (item 3958) (-5) (Sin cambio)
        "UPDATE EVE_ASSETS 
         SET unit_price = -5, forge_value = -5 
         WHERE type_id in (3958,45018,44219,45483) AND unit_price >= 0",
         
         // resulta que los skins tienen problemas de precios, asique se hace un cambio exclusivo en el personaje jane Hek
         
         "update `EVE_ASSETS` set unit_price =-7, forge_value=-7 wHERE `toon_number` = '2118999352' AND `type_description` LIKE '%- limited%';",
         "update `EVE_ASSETS` set unit_price =-7, forge_value=-7 wHERE `toon_number` = '2118999352' AND `type_description` LIKE '%- unlimited%';"
         
    ];

    $errores = [];

    // Ejecutar cada regla
    foreach ($reglas as $index => $sql) {
        $ejecucion_exitosa = mysqli_query($link, $sql);
        
        if ($ejecucion_exitosa === false) {
            // Se incluye el SQL para depuración
            $errores[] = "Regla " . ($index + 1) . " falló: " . mysqli_error($link) . " (SQL: " . htmlentities($sql) . ")";
        } else {
            $ajustes_aplicados += mysqli_affected_rows($link);
        }
    }
    
    // --- Reporte Final (el mismo formato Bootstrap) ---
    $end_time = microtime(true);
    $tiempo_transcurrido = round($end_time - $start_time, 2);

    if (empty($errores)) {
        $msg_class = 'bg-success';
        $icon = 'fas fa-check-circle';
        $mensaje = "Todos los filtros de exclusión se aplicaron correctamente en EVE_ASSETS.";
    } else {
        $msg_class = 'bg-danger';
        $icon = 'fas fa-exclamation-triangle';
        $mensaje = "Errores encontrados durante la aplicación de filtros. Detalles: " . implode('<br>', $errores);
    }
    
    $html_resultado = "
        <div class='card shadow-sm mt-3'>
            <div class='card-header {$msg_class} text-white'>
                <h5 class='mb-0'><i class='{$icon}'></i> Aplicación de Filtros de Exclusión en Activos (CORRECCIÓN FINAL)</h5>
            </div>
            <div class='card-body p-0'>
                <table class='table table-bordered table-sm mb-0'>
                    <thead class='thead-light'>
                        <tr>
                            <th><i class='fas fa-filter'></i> Registros Marcados</th>
                            <th><i class='fas fa-clock'></i> Tiempo Empleado (segundos)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class='font-weight-bold'>{$ajustes_aplicados}</td>
                            <td>{$tiempo_transcurrido}s</td>
                        </tr>
                    </tbody>
                </table>
            </div>
             <div class='card-footer text-muted'>
                <small>{$mensaje}</small>
            </div>
        </div>
    ";
    
    return $html_resultado;
}
?>
