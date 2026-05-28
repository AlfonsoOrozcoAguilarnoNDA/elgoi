<?php
/**
 * License GPL 3.0
 * Alfonso Orozco Aguilar
 * Fleet Commander - The Six
 * Fecha: 2026-03-31 00:22
 * 
 * An idea for monitoring a group. Here i use my own test chars to do so. Use yours.
 */
session_start();

include_once '../config.php';
include_once '../ui_functions.php';
// =========================================================================
// MARCA DE TIEMPO DE ACTUALIZACIÓN (México/CST)
// =========================================================================
date_default_timezone_set('America/Mexico_City');
$timestamp_mexico = date('d/m/Y H:i:s');

// =========================================================================
// 1. CONFIGURACIÓN INICIAL Y DATOS FIJOS (MODIFICABLES POR EL USUARIO)
// =========================================================================

// !!! ATENCIÓN: DEBES ASEGURARTE QUE LA VARIABLE $link (CONEXIÓN A LA BASE DE DATOS) ESTÉ DEFINIDA Y ACTIVA
// Si $link no está definida, el script fallará con un mensaje de error.
if (!isset($link) || !is_object($link)) {
    echo '<div class="alert alert-danger">ERROR: La variable $link (Conexión a la base de datos) no está definida. Por favor, define la conexión antes de ejecutar este script.</div>';
    exit;
}

// IDs REALES de los seis pilotos en el orden exacto solicitado (3x3)
$pilots_ids = ['2124036322','2123978503','2123978462','2123978514', '2123978451','2123978440'];

// Definición de los grupos de habilidades: ¡MODIFICA LOS 'ids' CON TUS typeID REALES!
// Los colores son para diferenciar el fondo de la fila de la tabla de habilidades.
$skill_groups = [
    "Misiles" => [
        'ids' => [3319,3320,3321,3324,12441,12442,20209,20210,20312,20314,20315,21071,],
        'color' => '#E0BBE4', // Lila pastel
    ],
    "Turrets" => [
        'ids' => [3301,3302,3303,3304,3305,3310,3311,3312,3315,3316,3317,11083,12213], // IDs de Turrets (Placeholder)
        'color' => '#957DAD', // Morado claro
    ],
    "Social" => [
        'ids' => [3555,3356,3357,3359], // IDs Sociales (Placeholder)
        'color' => '#D291BC', // Rosa pálido
    ],
    "Otros" => [
        'ids' => [3318,3327,3328,3329,3330,3331,3334,3387,3392,3393,3394,3405,3411,3413,3416,3417,3418,3419,3420,3424,3425,3426,33091,33092,33093,33094], // IDs Varios (Placeholder)
        'color' => '#FEC8D8', // Rosa claro
    ]
];


// =========================================================================
// 2. FUNCIONES PROCEDURALES PARA OBTENCIÓN Y PROCESAMIENTO DE DATOS
// =========================================================================

/**
 * Función auxiliar para asegurar que la salida no es NULL, evitando warnings de htmlspecialchars.
 * @param mixed $value Valor a formatear.
 * @param string $default Valor por defecto si es nulo.
 * @return string Valor seguro para htmlspecialchars.
 */
function format_output($value, $default = 'N/D') {
    return htmlspecialchars($value ?? $default);
}

/**
 * Función para obtener el 'pseudo' de PANELS para un toon_number dado.
 * Utiliza sentencias preparadas para seguridad.
 */
function get_pilot_pseudo($link, $toon_number) {
    // ... (El cuerpo de la función se mantiene igual) ...
    $sql_pseudo = "SELECT pseudo FROM PANELS 
                   WHERE pilot_1 = ? OR pilot_2 = ? OR pilot_3 = ? LIMIT 1";
    
    $stmt = mysqli_prepare($link, $sql_pseudo);
    mysqli_stmt_bind_param($stmt, "sss", $toon_number, $toon_number, $toon_number); 
    mysqli_stmt_execute($stmt);
    
    $result_pseudo = mysqli_stmt_get_result($stmt);
    
    if ($result_pseudo && mysqli_num_rows($result_pseudo) > 0) {
        $row_pseudo = mysqli_fetch_assoc($result_pseudo);
        mysqli_free_result($result_pseudo);
        //mysqli_stmt_close($stmt);
        return $row_pseudo['pseudo'];
    }
    
    //mysqli_stmt_close($stmt);
    return 'N/D';
}

/**
 * Evalúa el desempeño de un grupo de pilotos en un grupo de habilidades (Función MuestraSkills).
 */
function MuestraSkills_Group($link, $skill_ids, $pilots_ids) {
    // ... (El cuerpo de la función se mantiene igual) ...
    if (empty($skill_ids) || empty($pilots_ids)) {
        return ['best_pilot_name' => 'N/D', 'pilot_scores' => []];
    }
    
    $ids_string = implode(',', array_map('intval', $skill_ids));
    $pilots_string = implode(',', array_map('intval', $pilots_ids));
    
    $sql = "SELECT E.toon, SUM(E.skillpoints) AS total_sp, P.toon_name 
            FROM EVE_CHARSKILLS E
            JOIN PILOTS P ON E.toon = P.toon_number
            WHERE E.toon IN ($pilots_string) AND E.typeID IN ($ids_string)
            GROUP BY E.toon
            ORDER BY total_sp DESC";
            
    $result = mysqli_query($link, $sql);

    $pilot_scores = [];
    $max_sp = -1;
    $best_pilot_name = 'N/D';

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $toon = $row['toon'];
            $sp = (int)$row['total_sp'];
            $name = $row['toon_name'];
            
            $pilot_scores[$toon] = $sp;
            
            if ($sp > $max_sp) {
                $max_sp = $sp;
                $best_pilot_name = $name;
            }
        }
        mysqli_free_result($result);
    }
    
    foreach ($pilots_ids as $id) {
        if (!isset($pilot_scores[$id])) {
            $pilot_scores[$id] = 0;
        }
    }

    return [
        'best_pilot_name' => $best_pilot_name,
        'pilot_scores' => $pilot_scores,
    ];
}

/**
 * Extrae el nombre de la nave del string JSON (CORRECCIÓN DE ESCAPES).
 */
function get_ship_name_from_json($json_ship) {
    if (empty($json_ship) || $json_ship === 'null' || !is_string($json_ship)) {
        return 'Sin Nave';
    }
    
    // --- CORRECCIÓN CLAVE: Eliminar los slashes extra que PHP puede agregar a JSON ---
    $json_ship_cleaned = stripslashes($json_ship);
    
    $data = json_decode($json_ship_cleaned, true);
    
    if ($data && isset($data['ship_name'])) {
        return $data['ship_name'];
    }
    
    return 'Error JSON';
}


// =========================================================================
// 3. OBTENER Y PROCESAR DATOS DE LA DB
// =========================================================================

// Consulta SQL. Seleccionamos todos los campos solicitados
$ids_string = implode(',', array_map('intval', $pilots_ids));
$sql_pilots = "SELECT toon_number, toon_name, race, skillpoints, unalloc, 
                      finishqueue, pocket6, current_ship, current_location, 
                      attrib, commentcard, numitems, lastdate
               FROM PILOTS
               WHERE toon_number IN ($ids_string)";

$pilots_data = [];
$error_msg = null;
$result_pilots = mysqli_query($link, $sql_pilots);

if ($result_pilots) {
    while ($pilot_row = mysqli_fetch_assoc($result_pilots)) {
        $toon_number = $pilot_row['toon_number'];
        $pilot_row['pseudo'] = get_pilot_pseudo($link, $toon_number);
        $pilots_data[$toon_number] = $pilot_row;
    }
    mysqli_free_result($result_pilots);
} else {
    $error_msg = mysqli_error($link);
}

// Ejecutar el análisis de habilidades para CADA GRUPO
$group_analysis = [];
foreach ($skill_groups as $group_name => $group_info) {
    $analysis_result = MuestraSkills_Group($link, $group_info['ids'], $pilots_ids);
    $group_analysis[$group_name] = $analysis_result;
}

// Reestructurar los datos de PILOTS para incluir los scores por grupo
foreach ($pilots_data as $toon_number => &$pilot_row) {
    $pilot_row['skill_totals'] = [];
    $pilot_row['is_best_in'] = [];
    
    foreach ($skill_groups as $group_name => $group_info) {
        $total_sp = $group_analysis[$group_name]['pilot_scores'][$toon_number] ?? 0;
        $pilot_row['skill_totals'][$group_name] = $total_sp;
        
        $best_toon_name = $group_analysis[$group_name]['best_pilot_name'];
        if (format_output($best_toon_name) === format_output($pilot_row['toon_name'])) {
            $pilot_row['is_best_in'][] = $group_name;
        }
    }
}
unset($pilot_row); 

// Cierre de la conexión a la base de datos
//mysqli_close($link);


// =========================================================================
// 4. ESTRUCTURA HTML Y GENERACIÓN DE CARDS (Bootstrap 4.6)
// =========================================================================
echo ui_header("Panel de Pilotos EVE Online");
echo crew_navbar(); echo "<br /><br /><br />";
// Hacemos visible el timestamp
echo '<div class="alert alert-secondary text-center small mb-4">Análisis de Pilotos Actualizado: ' . $timestamp_mexico . ' Tiempo de México</div>';

?>

<div class="container mt-5">
    <h1 class="mb-4">🚀 Estado de los Pilotos Clave</h1>

<?php if (isset($error_msg)): ?>
    <div class="alert alert-danger" role="alert">Error de Consulta de Pilotos: <?= format_output($error_msg) ?></div>
<?php endif; ?>

    <div class="row">
<?php
$counter = 0;
foreach ($pilots_ids as $id) {
    $pilot = $pilots_data[$id] ?? null;

    // Manejo de Piloto No Encontrado
    if (!$pilot) {
        $pilot = [
            'toon_name' => 'Piloto Desconocido (ID: ' . $id . ')',
            'pseudo' => 'N/D', 'race' => 'N/D', 'skillpoints' => 0, 'unalloc' => 0,
            'finishqueue' => 'N/D', 'pocket6' => 'N/D', 'current_ship' => 'N/D',
            'current_location' => 'N/D', 'attrib' => 'N/D', 'commentcard' => 'Piloto no encontrado.',
            'numitems' => 0, 'lastdate' => 'N/D', 'toon_number' => $id,
            'skill_totals' => [], 'is_best_in' => []
        ];
    }
    
    // --- PROCESAMIENTO DE DATOS ---
    $sp_total_m = number_format(($pilot['skillpoints'] / 1000000), 2, '.', ',');
    $sp_unalloc_m = number_format(($pilot['unalloc'] / 1000000), 2, '.', ',');
    $ship_name = get_ship_name_from_json($pilot['current_ship']);
    
    // Apertura de nueva fila para 3x3
    if ($counter > 0 && $counter % 3 === 0) {
        echo '</div><div class="row mt-4">';
    }

    // Generar la columna con la tarjeta (col-md-4 para 3 tarjetas por fila)
    echo '<div class="col-md-4 mb-4">';
    echo '    <div class="card shadow-lg h-100">';
    
    // --- IMAGEN DEL AVATAR ---
    $image_url = "https://images.evetech.net/characters/{$id}/portrait?size=256";
    
    echo '        <img src="' . format_output($image_url) . '" 
                    class="card-img-top mx-auto mt-3 rounded-circle border border-primary p-1" 
                    alt="Retrato de ' . format_output($pilot['toon_name']) . '"
                    style="width: 180px; height: 180px; object-fit: cover;">';

    echo '        <div class="card-header border-0 bg-white pt-2 pb-0">';
    echo '            <h5 class="mb-0 text-center font-weight-bold">' . format_output($pilot['toon_name']) . '</h5>';
    echo '            <p class="text-center text-muted small">(#' . format_output($pilot['toon_number']) . ')</p>';
    echo '        </div>';
    
    echo '        <div class="card-body pt-0">';

    // Sección de Datos Clave
    echo '            <p class="card-text mb-1"><strong>Pseudo (Cuenta):</strong> ' . format_output($pilot['pseudo']) . '</p>';
	echo '            <p class="card-text mb-1"><strong>Pocket6:</strong> ' . format_output($pilot['pocket6']) . '</p>';
    echo '            <p class="card-text mb-1"><strong>Raza:</strong> ' . format_output($pilot['race']) . '</p>';
    echo '            <p class="card-text mb-1"><strong>SP Totales:</strong> <span class="badge badge-success">' . $sp_total_m . 'M</span></p>';
    echo '            <p class="card-text mb-1"><strong>SP Unalloc:</strong> <span class="badge badge-warning">' . $sp_unalloc_m . 'M</span></p>';
    echo '            <p class="card-text mb-1"><strong>Nave Actual:</strong> ' . format_output($ship_name) . '</p>';
    
    echo '            <hr>';

    // --- TABLA DE HABILIDADES POR GRUPO Y RANKING ---
    echo '            <h6 class="card-subtitle mb-2">Análisis de Habilidades:</h6>';
    echo '            <table class="table table-sm table-borderless table-responsive-sm small">';
    echo '                <tbody>';

    foreach ($skill_groups as $group_name => $group_info) {
        $total_sp = $pilot['skill_totals'][$group_name] ?? 0;
        $sp_formatted = number_format(($total_sp / 1000000), 1, '.', ',') . 'M';
        $is_best = in_array($group_name, $pilot['is_best_in']);
        
        $row_class = $is_best ? 'font-weight-bold text-dark' : '';
        $color_style = $is_best ? 'style="background-color: ' . $group_info['color'] . ';"' : '';
        $best_badge = $is_best ? '<span class="badge badge-danger">BEST</span>' : '';
        
        echo '                <tr ' . $color_style . ' class="' . $row_class . '">';
        echo '                    <td>' . format_output($group_name) . ':</td>';
        echo '                    <td class="text-right">' . format_output($sp_formatted) . ' ' . $best_badge . '</td>';
        echo '                </tr>';
    }
    echo '                </tbody>';
    echo '            </table>';

    // Mostrar el resumen del ranking (Global)
    echo '            <div class="alert alert-info py-1 px-2 small mt-2">';
    echo '                <p class="mb-0 font-weight-bold">Mejores Pilotos:</p>';
    foreach ($skill_groups as $group_name => $group_info) {
        $best_name = $group_analysis[$group_name]['best_pilot_name'] ?? 'N/D';
        echo '                <p class="mb-0 small">' . format_output($group_name) . ': <strong>' . format_output($best_name) . '</strong></p>';
    }
    echo '            </div>';

    echo '            <hr>';
    
    // COMENTARIO Y DETALLES OCULTOS/VISIBLES
    echo '            <p class="card-text small mb-1">Items: ' . format_output($pilot['numitems']) . '</p>';
    echo '            <p class="card-text small mb-1">Queue Fin: ' . format_output($pilot['finishqueue']) . '</p>';
    echo '            <p class="card-text small mb-1">Última Act: ' . format_output($pilot['lastdate']) . '</p>';
    echo '            <p class="card-text small mb-1">Pocket 6: ' . format_output($pilot['pocket6']) . '</p>';
    
    echo '            <h6 class="card-subtitle mt-3 mb-2 text-muted">Comentario:</h6>';
    echo '            <p class="card-text small font-italic mb-2">' . nl2br(format_output($pilot['commentcard'])) . '</p>';

    // Elementos con display: none
    echo '            <div style="display: none;">';
    echo '                <p class="card-text small mb-1">Ubicación (Oculto): ' . format_output($pilot['current_location']) . '</p>';
    echo '                <p class="card-text small mb-1">Atributos (Oculto): ' . format_output($pilot['attrib']) . '</p>';
    echo '            </div>';

    // Botón
    echo '            <a href="https://www.google.com" target="_blank" class="btn btn-dark btn-block btn-sm mt-3">';
    echo '                Ir a Hangar (Google.com)';
    echo '            </a>';
    
    echo '        </div>';
    echo '    </div>';
    echo '</div>';
    
    $counter++;
}
function left($str, $length) {
     return substr($str, 0, $length);
}
 
function right($str, $length) {
     return substr($str, -$length);
}


function aValues319($Qx){
global $link; 
    $rsX = mysqli_query($link,$Qx);
    //sql_error("Avalues 319 error",$Qx);
    if ($link->error<>'') echo cssalert("red","<br /><br /><br /><br /><li>$link->error<hr>$Qx");
    $tipoOP=strtoupper(left($Qx,6));
    if ($tipoOP<>'SELECT') return array (""); // por cuando pasamos delete or update
    if (mysqli_num_rows($rsX)==0) return array ("");
    $aDataX = array();    
        $Campos = mysqli_num_fields($rsX);
        while ($regX = mysqli_fetch_array($rsX)) {
            for($iX=0; $iX<$Campos; $iX++){
               $finfo=mysqli_fetch_field_direct($rsX,$iX);
               $name=$finfo->name;
                $aDataX[] = $regX[$name];
            }
        }
      // echo ($Qx ."/". $aDataX[0]);
    return $aDataX;
}

/**
 * Genera una tabla detallada de habilidades específicas y realiza un ranking.
 * Utiliza el array global $skill_groups.
 * @param mysqli $link Conexión a la DB.
 * @param string $title Título del grupo (ej: "Detalle del Grupo: Misiles").
 * @param array $skill_ids Array de typeID a analizar.
 * @param array $pilots_ids Array de toon_number de los 6 pilotos.
 * @param array $pilots_names Array de toon_number => toon_name (para ranking).
 */
function MuestraSkills_Detalle($link, $title, $skill_ids, $pilots_ids, $pilots_names) {
    // ... (Manejo de errores inicial) ...
    if (empty($skill_ids) || empty($pilots_ids)) {
        echo '<div class="alert alert-warning">No se proporcionaron IDs de habilidad o de pilotos.</div>';
        return;
    }

    $ids_string = implode(',', array_map('intval', $skill_ids));
    $pilots_string = implode(',', array_map('intval', $pilots_ids));

    $sql = "SELECT 
                typeID, 
                Description, 
                toon, 
                skillpoints, 
                rank 
            FROM EVE_CHARSKILLS 
            WHERE toon IN ($pilots_string) AND typeID IN ($ids_string)";
            
    $result = mysqli_query($link, $sql);

    // 1. Inicialización de Matrices de Datos y Ranking
    $skill_data = [];
    $skill_totals = array_fill_keys($pilots_ids, 0); // Total SP por piloto
    $best_pilot_per_skill = []; // Ranking por habilidad individual

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $toon = $row['toon'];
            $typeID = $row['typeID'];
            $sp = (int)$row['skillpoints'];

            // Almacenar datos para la tabla [typeID][toon]
            $skill_data[$typeID]['Description'] = format_output($row['Description']);
            $skill_data[$typeID]['Pilots'][$toon] = [
                'rank' => (int)$row['rank'],
                'sp' => $sp
            ];

            // Acumular el total de SP del grupo para el ranking total
            $skill_totals[$toon] += $sp;

            // 2. Lógica de Ranking Individual por Habilidad
            if (!isset($best_pilot_per_skill[$typeID]) || $sp > $best_pilot_per_skill[$typeID]['sp']) {
                $best_pilot_per_skill[$typeID] = ['toon' => $toon, 'sp' => $sp];
            } elseif ($sp === $best_pilot_per_skill[$typeID]['sp'] && $sp > 0) {
                // Manejo de Empate: Añadir toon_number al ranking (solo si tienen SP > 0)
                if (!is_array($best_pilot_per_skill[$typeID]['toon'])) {
                    $best_pilot_per_skill[$typeID]['toon'] = [$best_pilot_per_skill[$typeID]['toon']];
                }
                $best_pilot_per_skill[$typeID]['toon'][] = $toon;
            }
        }
        mysqli_free_result($result);
    }
    
    // 3. Generación de la Tabla HTML
    echo '<h3 class="mt-5 mb-3 text-primary">' . format_output($title) . '</h3>';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-sm table-striped small">';
    
    // Encabezado (Filas de Pilotos)
    echo '<thead><tr>';
    echo '<th>Skill (Description)</th>';
    foreach ($pilots_ids as $toon) {
		list($nombre)=avalues319("select toon_name from PILOTS where toon_number='$toon'");
        echo '<th class="text-center">' . format_output($nombre) . '</th>';
    }
    echo '</tr></thead>';
    
    // Cuerpo de la Tabla (Filas de Habilidades)
    echo '<tbody>';
    foreach ($skill_data as $typeID => $data) {
        echo '<tr>';
        // Columna de Descripción
        echo '<td>' . format_output($data['Description']) . '</td>';
        
        // Columnas de Pilotos
        foreach ($pilots_ids as $toon) {
            $pilot_skill = $data['Pilots'][$toon] ?? null;
            $is_best = false;

            // Determinar si es el mejor en esta habilidad (maneja empates)
            if (isset($best_pilot_per_skill[$typeID])) {
                $best_toons = $best_pilot_per_skill[$typeID]['toon'];
                if (!is_array($best_toons)) $best_toons = [$best_toons];
                $is_best = in_array($toon, $best_toons);
            }

            $cell_content = '';
            $cell_class = '';
            if ($pilot_skill) {
                $sp_k = number_format(($pilot_skill['sp'] / 1000), 0, '', ','); // SP en K
                $rank_level = $pilot_skill['rank'];
                $cell_content = "{$rank_level} ({$sp_k}k)";
                
                // Si tiene SP y es el mejor, aplicar clase
                if ($is_best) {
                    $cell_class = 'bg-success text-white font-weight-bold';
                }
            }
            
            echo '<td class="' . $cell_class . ' text-center">' . format_output($cell_content, '&nbsp;') . '</td>';
        }
        echo '</tr>';
    }

    // Fila Total SP
    echo '<tr class="table-info font-weight-bold">';
    echo '<td>Total SP del Grupo</td>';
    $max_total_sp = max($skill_totals);
    
    // Encontrar el ID del mejor piloto total
    $best_total_pilot_id = array_search($max_total_sp, $skill_totals);
    $best_total_sp_name = $pilots_names[$best_total_pilot_id] ?? 'N/D';

    // Manejar Empates para el Total SP del Grupo
    $best_total_pilot_ids = array_keys($skill_totals, $max_total_sp);
    
    foreach ($pilots_ids as $toon) {
        $sp_total_k = number_format(($skill_totals[$toon] / 1000), 0, '', ',');
        $cell_class = (in_array($toon, $best_total_pilot_ids) && $max_total_sp > 0) ? 'bg-primary text-white' : '';
        echo '<td class="' . $cell_class . ' text-center">' . $sp_total_k . 'k</td>';
    }
    echo '</tr>';
    
    echo '</tbody>';
    echo '</table>';
    echo '</div>'; // Cierre table-responsive

    // 4. Salida del Mejor Piloto Total
    echo '<p class="lead mt-3">';
    if ($max_total_sp > 0) {
        $sp_total_m = number_format(($max_total_sp / 1000000), 2, '.', ',');
        
        $best_names = [];
        foreach ($best_total_pilot_ids as $id) {
		   list($nombre)=avalues319("select toon_name from PILOTS where toon_number='$id'");
			
            $best_names[] = $nombre;
        }
        $best_names_str = implode(' y ', $best_names);
        
        echo '✅ Mejor en ' . format_output(str_replace('Detalle del Grupo: ', '', $title)) . ': <strong>' . format_output($best_names_str) . '</strong> (' . $sp_total_m . 'M SP)';
    } else {
        echo '❌ Ningún piloto tiene puntos en este grupo.';
    }
    echo '</p>';
}
// Inicia el contenedor para las tablas de detalle
echo '<div class="container mt-5">';

// Itera sobre el array de grupos ($skill_groups) para generar la tabla de detalle para cada uno
foreach ($skill_groups as $group_name => $group_info) {
    
    
    $pilots_names=$pilots_ids;
    MuestraSkills_Detalle(
        $link, 
        "Detalle del Grupo: " . $group_name, // Título que se mostrará en la tabla
        $group_info['ids'],                 // Array de typeID (ej: [3340, 3341, 3342, 3343] para Otros)
        $pilots_ids,                        // Array de IDs de pilotos fijos
        $pilots_names                       // Array de nombres de pilotos
    );
    
    // Si quieres un separador visual entre las tablas:
    echo '<hr class="my-5">'; 
}

echo '</div>'; // Cierre del contenedor de detalle
?>
    </div> </div>
<?php echo ui_footer();?>
