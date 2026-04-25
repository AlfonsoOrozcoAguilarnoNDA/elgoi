<?php
/*
Alfonso Orozco Aguilar
License GPL 3.0
*/
/**
 * COMPARAR.PHP - Sistema Unificado de Comparación de Pilotos EVE Online
 * Combina selección, comparación y filtrado en un solo archivo
 * Todas las funciones retornan HTML para fácil integración
 */

// Incluir archivos de configuración y funciones
include_once 'config.php';
include_once 'ui_functions.php';

// Definición de la constante para la capacidad máxima de pilotos
define('MAX_PILOTS', 6);

// Aplicar seguridad
check_authorization();

// ============================================================================
// FUNCIÓN: FILTRAR SOLO MEJORES HABILIDADES DEL PILOTO 1
// ============================================================================
function filter_pilot1_best_skills($final_skills_list, $pilots_data, $pilot_ids) {
    $filtered_skills = [];
    $total_sp_exclusive = 0;
    $skills_count = 0;
    
    foreach ($final_skills_list as $description => $typeID) {
        $pilot1_skill = $pilots_data[1]['skills'][$typeID] ?? null;
        
        if (!$pilot1_skill) {
            continue;
        }
        
        $pilot1_sp = $pilot1_skill['skillpoints'];
        $is_better_than_all = true;
        
        foreach ($pilot_ids as $index => $toon_number) {
            if ($index === 1) {
                continue;
            }
            
            $other_pilot_skill = $pilots_data[$index]['skills'][$typeID] ?? null;
            
            if ($other_pilot_skill) {
                $other_sp = $other_pilot_skill['skillpoints'];
                if ($other_sp >= $pilot1_sp) {
                    $is_better_than_all = false;
                    break;
                }
            }
        }
        
        if ($is_better_than_all) {
            $filtered_skills[$description] = $typeID;
            $total_sp_exclusive += $pilot1_sp;
            $skills_count++;
        }
    }
    
    $sp_in_millions = $total_sp_exclusive / 1000000;
    
    return [
        'filtered_skills' => $filtered_skills,
        'total_sp_exclusive' => $total_sp_exclusive,
        'skills_count' => $skills_count,
        'sp_in_millions' => $sp_in_millions,
        'injectors_500k' => $sp_in_millions / 0.5,
        'injectors_400k' => $sp_in_millions / 0.4
    ];
}

// ============================================================================
// FUNCIÓN: GENERAR ALERTA DE ANÁLISIS DE FLOTA
// ============================================================================
function generate_fleet_analysis_alert($exclusive_stats, $pilot_name) {
    $skills_count = $exclusive_stats['skills_count'];
    
    if ($skills_count == 0) {
        $alert_class = 'alert-success';
        $icon = 'fa-check-circle';
        $message = "Las habilidades del piloto <strong>" . htmlspecialchars($pilot_name) . "</strong> ya las tienen los demás pilotos seleccionados de la flota, por lo mismo <strong>puede ser retirado del servicio</strong>.";
    } else {
        $alert_class = 'alert-danger';
        $icon = 'fa-exclamation-triangle';
        $message = "Este piloto tiene <strong>" . number_format($skills_count) . " habilidades únicas</strong> contra los seleccionados, <strong>no puede ser vendido sin alterar las capacidades de la flota</strong>.";
    }
    
    $html = '<div class="alert ' . $alert_class . '">';
    $html .= '<h5><i class="fas ' . $icon . '"></i> Análisis de Flota</h5>';
    $html .= '<p class="mb-0">' . $message . '</p>';
    
    if ($skills_count > 0) {
        $html .= '<hr>';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-3">';
        $html .= '<strong>Habilidades Únicas:</strong><br>';
        $html .= number_format($exclusive_stats['skills_count']);
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<strong>SP Únicos:</strong><br>';
        $html .= number_format($exclusive_stats['sp_in_millions'], 2) . ' M';
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<strong>Injectors (500k):</strong><br>';
        $html .= number_format($exclusive_stats['injectors_500k'], 2);
        $html .= '</div>';
        $html .= '<div class="col-md-3">';
        $html .= '<strong>Injectors (400k):</strong><br>';
        $html .= number_format($exclusive_stats['injectors_400k'], 2);
        $html .= '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

// ============================================================================
// FUNCIÓN: GENERAR FORMULARIO DE SELECCIÓN
// ============================================================================
function generate_selection_form() {
    $all_pilots = db_get_all_pilots_for_compare(); 
    $pilot_options = ['' => '--- No Seleccionar Piloto ---'] + $all_pilots;
    
    $html = '<div class="card shadow-lg">';
    $html .= '<div class="card-header bg-dark text-white">';
    $html .= '<i class="fas fa-balance-scale"></i> Seleccione Pilotos a Comparar (Máx. ' . MAX_PILOTS . ')';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    $html .= '<form method="POST" action="?">';
    $html .= '<div class="row">';
    
    for ($i = 1; $i <= MAX_PILOTS; $i++) {
        $html .= '<div class="col-md-3">';
        $html .= '<h5 class="mt-3"><i class="fas fa-user-astronaut"></i> Piloto ' . $i . '</h5>';
        $required = ($i == 1) ? true : false;
        $html .= ui_generate_select('pilot_' . $i . '_id', $pilot_options, 'Piloto ' . $i, $required);
        $html .= '</div>';
    }
    
    $html .= '</div>';
    $html .= '<hr>';
    $html .= '<div class="alert alert-info">';
    $html .= '<strong>Nota:</strong> Si no selecciona todos los pilotos, las columnas vacías se ocultarán automáticamente.';
    $html .= '</div>';
    $html .= '<button type="submit" class="btn btn-primary btn-lg mt-3">';
    $html .= '<i class="fas fa-chart-bar"></i> Comparar Habilidades';
    $html .= '</button>';
    $html .= '</form>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// ============================================================================
// FUNCIÓN: GENERAR TABLA DE COMPARACIÓN COMPLETA
// ============================================================================
function generate_comparison_table($final_skills_list, $pilots_data, $pilot_ids, $table_title = "Comparación Completa de Habilidades") {
    $html = '<div class="card shadow-lg mb-4">';
    $html .= '<div class="card-header bg-primary text-white">';
    $html .= '<i class="fas fa-list"></i> ' . htmlspecialchars($table_title);
    $html .= '</div>';
    $html .= '<div class="card-body p-0">';
    $html .= '<div class="table-responsive">';
    $html .= '<table class="table table-bordered table-sm table-striped mb-0">';
    $html .= '<thead class="thead-dark">';
    $html .= '<tr>';
    $html .= '<th class="text-center" style="width: 5%">#</th>';
    $html .= '<th class="text-center" style="width: 10%">TypeID</th>';
    $html .= '<th class="w-25">Habilidad</th>';
    
    for ($i = 1; $i <= MAX_PILOTS; $i++) {
        if (isset($pilots_data[$i])) {
            $html .= '<th class="text-center">';
            $html .= htmlspecialchars($pilots_data[$i]['name']);
            $html .= '<br><small class="text-white-50">(Piloto ' . $i . ')</small>';
            $html .= '</th>';
        }
    }
    
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    $row_number = 1;
    foreach ($final_skills_list as $description => $typeID) {
        $html .= '<tr>';
        $html .= '<td class="text-center text-muted">' . $row_number++ . '</td>';
        $html .= '<td class="text-center">';
        $html .= '<a href="https://market.fuzzwork.co.uk/type/' . $typeID . '/" target="_blank" title="Ver en Market (Fuzzwork)">';
        $html .= $typeID . ' <i class="fas fa-external-link-alt fa-xs"></i>';
        $html .= '</a>';
        $html .= '</td>';
        $html .= '<td><strong>' . htmlspecialchars($description) . '</strong></td>';
        
        // Encontrar el MÁXIMO valor
        $max_rank = 0;
        $max_sp = 0;
        
        foreach ($pilot_ids as $index => $toon_number) {
            if (isset($pilots_data[$index]['skills'][$typeID])) {
                $current_rank = $pilots_data[$index]['skills'][$typeID]['rank'];
                $current_sp = $pilots_data[$index]['skills'][$typeID]['skillpoints'];
                
                if ($current_rank > $max_rank) {
                    $max_rank = $current_rank;
                    $max_sp = $current_sp;
                } elseif ($current_rank === $max_rank && $current_sp > $max_sp) {
                    $max_sp = $current_sp;
                }
            }
        }
        
        // Generar celdas
        for ($i = 1; $i <= MAX_PILOTS; $i++) {
            if (isset($pilots_data[$i])) {
                $skill = $pilots_data[$i]['skills'][$typeID] ?? null;
                $cell_class = '';
                $tooltip = '';
                $rank_is_mixed_flag = false;
                
                if ($skill) {
                    $rank = $skill['rank'];
                    $sp = $skill['skillpoints'];
                    
                    if ($rank === $max_rank && $sp === $max_sp) {
                        $cell_class = 'table-success';
                    } elseif ($rank < $max_rank) {
                        $cell_class = 'table-warning';
                    }
                    
                    if ($rank === $max_rank && $sp < $max_sp) {
                        $rank_is_mixed_flag = true;
                    }
                    
                    $display_content = "V" . $rank . " (" . number_format($sp) . " SP)";
                    $tooltip = $rank_is_mixed_flag ? 'Mismo rango, SP menor al máximo' : '';
                } else {
                    $cell_class = 'table-light text-muted';
                    $display_content = 'N/A';
                }
                
                $html .= '<td class="text-center ' . $cell_class . '" title="' . htmlspecialchars($tooltip) . '">';
                $html .= $display_content;
                if ($rank_is_mixed_flag) {
                    $html .= '<span class="badge badge-danger ml-1" title="Mismo Rango, Puntos Menores"><i class="fas fa-exclamation"></i></span>';
                }
                $html .= '</td>';
            }
        }
        
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '<tfoot class="thead-light">';
    $html .= '<tr>';
    $html .= '<td colspan="3"><strong>TOTALES</strong></td>';
    
    for ($i = 1; $i <= MAX_PILOTS; $i++) {
        if (isset($pilots_data[$i])) {
            $html .= '<td class="text-center">';
            $html .= '<strong>Habilidades:</strong> ' . number_format($pilots_data[$i]['total_skills_count']) . '<br>';
            $html .= '<strong>SP Totales:</strong> ' . number_format($pilots_data[$i]['total_skillpoints'], 0);
            $html .= '</td>';
        }
    }
    
    $html .= '</tr>';
    $html .= '</tfoot>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

// ============================================================================
// LÓGICA PRINCIPAL
// ============================================================================
$show_results = false;
$pilot_ids = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    for ($i = 1; $i <= MAX_PILOTS; $i++) {
        $post_key = 'pilot_' . $i . '_id';
        if (isset($_POST[$post_key]) && !empty($_POST[$post_key])) {
            $pilot_ids[$i] = (int)$_POST[$post_key];
        }
    }
    
    if (!empty($pilot_ids)) {
        $show_results = true;
    }
}

// ============================================================================
// RENDERIZADO
// ============================================================================
echo ui_header("Comparación de Pilotos");
echo ui_generate_navbar();

if (!$show_results) {
    // Mostrar formulario de selección
    echo generate_selection_form();
} else {
    // Procesar y mostrar resultados
    $pilots_data = [];
    $master_skill_list = [];
    $max_skill_count = 0;
    
    foreach ($pilot_ids as $index => $toon_number) {
        $pilot_name_result = db_get_pilot_name_by_id($toon_number);
        $pilot_name = $pilot_name_result['toon_name'] ?? 'Piloto Desconocido (' . $toon_number . ')';
        
        $skills = db_get_pilot_skills($toon_number);
        
        $pilots_data[$index] = [
            'name' => $pilot_name,
            'toon_number' => $toon_number,
            'skills' => $skills['data'],
            'total_skills_count' => $skills['total_skills_count'],
            'total_skillpoints' => $skills['total_skillpoints'],
            'max_rank_skillpoints' => $skills['max_rank_skillpoints']
        ];
        
        // ACUMULAR TODAS las habilidades de TODOS los pilotos (incluso con SP = 0)
        $master_skill_list = array_unique(array_merge($master_skill_list, array_keys($skills['data'])));
    }
    
    // Preparar lista completa
    $final_skills_list = [];
    foreach ($master_skill_list as $typeID) {
        $description = '';
        foreach ($pilots_data as $p) {
            if (isset($p['skills'][$typeID])) {
                $description = $p['skills'][$typeID]['description'];
                break;
            }
        }
        if ($description) {
            $final_skills_list[$description] = $typeID;
        }
    }
    ksort($final_skills_list);
    
    // Aplicar filtro para obtener habilidades únicas del Piloto 1
    $filter_result = null;
    $filtered_skills = [];
    if (count($pilot_ids) > 1) {
        $filter_result = filter_pilot1_best_skills($final_skills_list, $pilots_data, $pilot_ids);
        $filtered_skills = $filter_result['filtered_skills'];
    }
    
    // Botón de regreso
    echo '<div class="row mb-3">';
    echo '<div class="col-12 text-right">';
    echo '<a href="?" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Nueva Comparación</a>';
    echo '</div>';
    echo '</div>';
    
    // Mostrar análisis de flota
    if ($filter_result && count($pilot_ids) > 1) {
        echo generate_fleet_analysis_alert($filter_result, $pilots_data[1]['name']);
    }
    
    // Mostrar AMBAS tablas
    echo '<h4 class="mt-4 mb-3"><i class="fas fa-star"></i> Habilidades Únicas del Piloto 1</h4>';
    echo generate_comparison_table($filtered_skills, $pilots_data, $pilot_ids, "Habilidades donde " . htmlspecialchars($pilots_data[1]['name']) . " es mejor que todos");
    
    echo '<h4 class="mt-4 mb-3"><i class="fas fa-list"></i> Comparación Completa</h4>';
    echo generate_comparison_table($final_skills_list, $pilots_data, $pilot_ids, "Todas las Habilidades");
}

echo ui_footer();
/**
 * Obtiene y organiza las habilidades (skills) de un piloto por su typeID.
 * Devuelve un array con la habilidad de mayor 'rank' en el índice [max_rank_skillpoints]
 * y un array asociativo [typeID => {rank, skillpoints, Description}] para una búsqueda rápida.
 *
 * @param int $toon_number El ID del piloto (toon_number).
 * @return array Array de habilidades indexadas por typeID, y metadatos de totales.
 */
function db_get_pilot_skills($toon_number) {
    global $link;
    $skills = [
        'data' => [],
        'total_skills_count' => 0,
        'total_skillpoints' => 0,
        'max_rank_skillpoints' => 0 // SP de la habilidad con mayor rango (para ordenar)
    ];

    $query = "
        SELECT 
            typeID, 
            skillpoints, 
            rank,
            Description
        FROM 
            EVE_CHARSKILLS
        WHERE 
            toon = ?
        ORDER BY 
            rank DESC, skillpoints DESC"; // Ordenar por rank y luego por SP
    
    $stmt = $link->prepare($query);
    $stmt->bind_param("i", $toon_number);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $typeID = $row['typeID'];
        $skills['data'][$typeID] = [
            'rank' => (int)$row['rank'],
            'skillpoints' => (int)$row['skillpoints'],
            'description' => $row['Description']
        ];
        
        $skills['total_skills_count']++;
        $skills['total_skillpoints'] += (int)$row['skillpoints'];
        
        // El primer registro será el de mayor rank, capturamos sus skillpoints para el orden
        if ($skills['max_rank_skillpoints'] === 0) {
            $skills['max_rank_skillpoints'] = (int)$row['skillpoints'];
        }
    }
    
    $stmt->close();
    return $skills;
}

?>
