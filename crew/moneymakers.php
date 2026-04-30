<?php
/* 
License MIT
Alfonso Orozco Aguilar

*/
/**
 * Tabla Maestra de Pilotos - EVE Online
 * Stack: PHP 8.x Procedimental, MariaDB, Bootstrap 4.6.2, DataTables, Font Awesome 5.15.4
 * Recalcula tradefield automáticamente en cada carga.
 */
require_once '../config.php';

function renewskills($who){
// who = toon number
list($name,$pilot,$data)=avalues319("select toon_name,email_pilot,skills from PILOTS where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (4)");
$name=addslashes($name);
if ($data=='[]') die("no data(1) for $name");
$where="in <a href='../devauthcallback.php?pilot_id=$who' target='_blank'>this link</a> when you do it, press f5 to reaload here";
if (str_replace("Timeout contacting tranquility","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later $where");
if (str_replace("unexpected end of JSON","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later $where");
if (str_replace("504 Gateway","",$data)<>$data) die("Sorry, skills are damaged in pilot $name, try update him/her later $where");

$data=stripslashes($data);
//echo "<li>	$name</li>";
$xml=json2xml($data);
	
$xml = new SimpleXMLElement($xml);
	
$sql="delete from EVE_CHARSKILLS where toon='$who'";
doaction($sql,"error checking skills $who");
list($dummy)=avalues319("select skills from PILOTS where toon_number='$who'");
//echo "<h3>Pilot: $name (9)</h3>";     
$acctype="Maybe Omega";
foreach($xml->skills->item as $item){ 
    $what=$item->skill_id;
    $description=description($what);
    if ($description=='') $description='n/a';    
    
    $active=$item->active_skill_level;
    if ($active=='') $active=0;    
    $dif=abs($item->trained_skill_level-$item->active_skill_level);    
    
    if ($dif>0) $acctype='Alpha';            
    list($maxalpha)=avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$what'");
    if ($active >$maxalpha and $active>0)  $acctype="Omega";
	$toon_name=addslashes($name);
	list($thegroup)=avalues319("select groupid from invTypes2 where typeid='$what'");
	list($group_name)=avalues319("select groupName from invGroups where groupid=$thegroup");;
    $sql="insert into EVE_CHARSKILLS (toon,toon_name,typeID,skillpoints,rank,description,group_name) values 
       ('$who','$name',$what,$item->skillpoints_in_skill,$item->trained_skill_level,'$description','$group_name')";
     doaction($sql,"error inserting skills");  
}
$sql="update PILOTS set acctype='$acctype' where toon_number='$who'";
doaction($sql,"error inserting skills");  
}// renewskills
function json2xml($json) {
// Copyright: Maurits van der Schee <maurits@vdschee.nl>
// Description: Convert from JSON to XML and back.
// License: MIT
    $a = json_decode($json);
    $d = new DOMDocument();
    $c = $d->createElement("root");
    $d->appendChild($c);
    $t = function($v) {
        $type = gettype($v);
        switch($type) {
            case 'integer': return 'number';
            case 'double':  return 'number';
            default: return strtolower($type);
        }
    };
    $f = function($f,$c,$a,$s=false) use ($t,$d) {
        $c->setAttribute('type', $t($a));
        if ($t($a) != 'array' && $t($a) != 'object') {
            if ($t($a) == 'boolean') {
                $c->appendChild($d->createTextNode($a?'true':'false'));
            } else {
                if (!is_null($a)) $c->appendChild($d->createTextNode($a));
            }
        } else {
            foreach($a as $k=>$v) {
                if ($k == '__type' && $t($a) == 'object') {
                    $c->setAttribute('__type', $v);
                } else {
                    if ($t($v) == 'object') {
                        $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                        $f($f, $ch, $v);
                    } else if ($t($v) == 'array') {
                        $ch = $c->appendChild($d->createElementNS(null, $s ? 'item' : $k));
                        $f($f, $ch, $v, true);
                    } else {
                        $va = $d->createElementNS(null, $s ? 'item' : $k);
                        if ($t($v) == 'boolean') {
                            $va->appendChild($d->createTextNode($v?'true':'false'));
                        } else {
                            $va->appendChild($d->createTextNode($v));
                        }
                        $ch = $c->appendChild($va);
                        $ch->setAttribute('type', $t($v));
                    }
                }
            }
        }
    };
    $f($f,$c,$a,$t($a)=='array');
    return $d->saveXML($d->documentElement);
} //json2xml
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

function doaction($sql,$errormessage){
global $link;
mysqli_query($link,$sql);
sqlerror("$errormessage<hr>$sql");
}
function left($str, $length) {
     return substr($str, 0, $length);
}

function right($str, $length) {
     return substr($str, -$length);
}
function sqlerror($message){
global $link;
$error=mysqli_error($link);
if ($error=='') return; 
die ("$message<hr>$error");
}
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
function updatecorpnames(){
global $link;
$csh=0;
// ==============================================================================
// FUNCIÓN OBTENER CORP NAME
// ==============================================================================
// Paso 1: Obtener todas las corporations únicas de la tabla
$query = "SELECT DISTINCT corporation FROM PILOTS WHERE corporation IS NOT NULL";
$result = mysqli_query($link, $query);

if (!$result) {
    die("Error en consulta: " . mysqli_error($link));
}

$corporaciones = [];
while ($row = mysqli_fetch_assoc($result)) {
    $corporaciones[] = $row['corporation'];
}

//echo "Total de corporaciones a actualizar: " . count($corporaciones) . "\n";

// Paso 2: Por cada corporation_id, consultar la ESI y actualizar
foreach ($corporaciones as $corporationId) {
    $url = "https://esi.evetech.net/latest/corporations/{$corporationId}/";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'TuApp/1.0 (tu@email.com)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $nombreLimpio = mysqli_real_escape_string($link, $data['name']);

        // Paso 3: Actualizar TODOS los registros con ese corporation ID
        $updateQuery = "UPDATE PILOTS 
                        SET corporation_name = '{$nombreLimpio}' 
                        WHERE corporation = {$corporationId}";

        if (mysqli_query($link, $updateQuery)) {
            $filas = mysqli_affected_rows($link);
            //echo "✓ Corp {$corporationId} → '{$data['name']}' ({$filas} registros actualizados)\n";
			$csh++;
        } else {
            //echo "✗ Error al actualizar corp {$corporationId}: " . mysqli_error($link) . "\n";
        }
    } else {
        //echo "✗ Corp {$corporationId} → Error HTTP {$httpCode}\n";
    }

    // Pequeña pausa para no saturar la ESI (buena práctica)
	usleep(2000); // 0.2 segundos entre llamadas
    //usleep(200000); // 0.2 segundos entre llamadas
}
//echo "\n✅ Proceso completado.\n";
return "
<div class='msg-tradefield'>
        <i class='fas fa-check-circle mr-1'></i>
        Corp Names actualizado — <strong>$csh</strong> corporaciones actualizadas al cargar.
    </div>";
} // update corpnames


// ==============================================================================
// FUNCIÓN OBTENER OFICIO
// ==============================================================================
function obtenerOficio($p) {
    global $link;
    $toon_name = mysqli_real_escape_string($link, $p['toon_name']);

    $sqlSkills = "SELECT group_name, SUM(skillpoints) as total_group_sp 
                  FROM EVE_CHARSKILLS 
                  WHERE toon_name = '$toon_name' 
                  GROUP BY group_name 
                  ORDER BY total_group_sp DESC
                  LIMIT 1";

    $res = mysqli_query($link, $sqlSkills);
    if ($res && mysqli_num_rows($res) > 0) {
        $dato = mysqli_fetch_assoc($res);
        return $dato['group_name'];
    }
	// si se va a guardar basura
	// pero hay que avisar de reevaluar con dashboard
    return "Skills sin definir, cargue Dashboard"; 
    $sp = (int)(($p['skillpoints'] ?? 0) / 1000000);
    //return $dato['group_name'];
	//if ($sp < 10) return "n/a";
    //return "Especialista Independiente";
}

// ==============================================================================
// RECÁLCULO AUTOMÁTICO DE TRADEFIELD (todos, siempre, antes de mostrar)
// ==============================================================================
$sqlTodos = "SELECT toon_number,toon_name, skillpoints FROM PILOTS WHERE toon_name NOT LIKE '%CATALOG%'";
$resTodos = mysqli_query($link, $sqlTodos);
$actualizados = 0;

if ($resTodos) {
    while ($p = mysqli_fetch_assoc($resTodos)) {
        $oficio    = obtenerOficio($p);
        $safeTrade = mysqli_real_escape_string($link, $oficio);
        $safeName  = mysqli_real_escape_string($link, $p['toon_name']);
		if($safeTrade=='Skills sin definir, cargue Dashboard') {
			// renew the skills
			$dummy=renewskills($p['toon_number']);
			//die($p['toon_number'].$p['toon_name']);
	    }
        mysqli_query($link, "UPDATE PILOTS SET tradefield = '$safeTrade' WHERE toon_name = '$safeName'");
        $actualizados++;
    }
    mysqli_free_result($resTodos);
}

// ==============================================================================
// FILTROS DESPLEGABLES
// ==============================================================================
$filterTrade = $_GET['filter_trade'] ?? 'ALL';
$filterCorp  = $_GET['filter_corp']  ?? 'ALL';
$filterPocket = $_GET['filter_pocket'] ?? 'ALL';

$resTrades  = mysqli_query($link, "SELECT DISTINCT tradefield FROM PILOTS WHERE tradefield IS NOT NULL AND tradefield <> '' AND tradefield <> 'n/a' ORDER BY tradefield ASC");
$resCorp    = mysqli_query($link, "SELECT DISTINCT corporation_name FROM PILOTS WHERE corporation_name IS NOT NULL AND corporation_name <> '' ORDER BY corporation_name ASC");
$resPockets = mysqli_query($link, "SELECT DISTINCT pocket6 FROM PILOTS WHERE pocket6 IS NOT NULL AND pocket6 <> '' ORDER BY pocket6 ASC");

// ==============================================================================
// CONSULTA PRINCIPAL
// ==============================================================================
$where = ["toon_name NOT LIKE '%VPS%'", "toon_name NOT LIKE '%CATALOG%'"];

if ($filterTrade !== 'ALL') {
    $where[] = "tradefield = '" . mysqli_real_escape_string($link, $filterTrade) . "'";
}
if ($filterCorp !== 'ALL') {
    $where[] = "corporation_name = '" . mysqli_real_escape_string($link, $filterCorp) . "'";
}
if ($filterPocket !== 'ALL') {
    $where[] = "pocket6 = '" . mysqli_real_escape_string($link, $filterPocket) . "'";
}

$whereClause = "WHERE " . implode(" AND ", $where);

$sqlPilots = "SELECT
                toon_number,
                toon_name,
                pocket6,
                tradefield,
                corporation_name,
                numitems,
                evermarks,
                numships,
				jitav,
                wallet,
                planets,
                jobs,
                gf,
                numberfits,
                security,
                skillpoints,
                unalloc,
                acctype
              FROM PILOTS
              $whereClause
              ORDER BY (skillpoints + IFNULL(unalloc,0)) DESC";

$resPilots   = mysqli_query($link, $sqlPilots);
$totalPilotos = $resPilots ? mysqli_num_rows($resPilots) : 0;

// Colores por pocket (para badge)
function getColorPocket($pocket) {
    $p = strtoupper(trim($pocket ?? ''));
    return match($p) {
        'EXPER' => '#28a745',
        'CLEAN' => '#0078d7',
        'SANGO' => '#ffc107',
        'LUCKY' => '#6f42c1',
        'NOKIA' => '#e81123',
        'YENN'  => '#cccccc',
        'OTHER' => '#fd7e14',
        default => '#444444'
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>EVE Online — Tabla Maestra de Pilotos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/css/dataTables.bootstrap4.min.css">
    <style>
        body {
            background-color: #1a1d21;
            color: #e0e0e0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 60px;
        }
        .navbar-eve {
            background-color: #0b0c0e;
            border-bottom: 2px solid #495057;
            margin-bottom: 0;
        }

        /* ── BARRA DE FILTROS ── */
        .filter-bar {
            background-color: #16191c;
            border-bottom: 2px solid #007bff;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        .filter-bar .form-control {
            background-color: #2a2d31;
            border-color: #495057;
            color: #e0e0e0;
            max-width: 180px;
        }
        .filter-bar .form-control:focus {
            background-color: #2a2d31;
            color: #fff;
            border-color: #007bff;
            box-shadow: none;
        }

        /* ── MENSAJE TRADEFIELD ── */
        .msg-tradefield {
            background-color: #1a2a1a;
            border-left: 4px solid #28a745;
            padding: 7px 16px;
            font-size: 0.82rem;
            color: #7ee07e;
            margin-bottom: 14px;
        }

        /* ── DATATABLES DARK ── */
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #aaa !important;
        }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background-color: #2a2d31;
            border: 1px solid #495057;
            color: #e0e0e0;
            border-radius: 3px;
            padding: 2px 6px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #007bff !important;
            color: #fff !important;
            border-color: #007bff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #0056b3 !important;
            color: #fff !important;
            border-color: #0056b3 !important;
        }

        /* ── TABLA ── */
        #tablaPilotos {
            font-size: 0.82rem;
        }
        #tablaPilotos thead th {
            background-color: #0d0f11;
            color: #adb5bd;
            border-color: #343a40;
            white-space: nowrap;
        }
        #tablaPilotos tbody tr {
            background-color: #1e2126;
        }
		#tablaPilotos tbody td {
            color: #e0e0e0;
        }
        #tablaPilotos tbody tr:nth-child(odd) {
            background-color: #22262c;
        }
        #tablaPilotos tbody tr:hover {
            background-color: #2a3040 !important;
        }
        #tablaPilotos td, #tablaPilotos th {
            border-color: #343a40 !important;
            vertical-align: middle !important;
        }

        /* Portrait en tabla */
        .portrait-sm {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: 2px solid #495057;
        }

        /* Pocket badge */
        .pocket-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #fff;
            border-radius: 2px;
        }
        .pocket-badge.dark-text { color: #111; }

        /* Valores numéricos */
        .val-money  { color: #f39c12; font-family: monospace; }
        .val-sp     { color: #5dade2; font-weight: bold; }
        .val-em     { color: #a29bfe; }
        .val-sec-pos { color: #28a745; }
        .val-sec-neg { color: #dc3545; }

        /* Iconos de actividad */
        .icon-active   { color: #28a745; font-size: 1rem; }
        .icon-inactive { color: #343a40; font-size: 1rem; }

        /* AccType badge esquina */
        .acctype-icon { font-size: 0.85rem; }

        /* Tradefield pill */
        .trade-pill {
            background-color: #2d3748;
            color: #bb86fc;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 0.75rem;
            white-space: nowrap;
        }
/* Iconos geticons() */
.industry-icons {
    display: inline-flex;
    gap: 9px;
    align-items: center;
    font-size: 1.05rem;
}
.industry-icons i { cursor: default; }
.industry-icons .icon-action { cursor: pointer; transition: color 0.15s; }
.industry-icons .icon-action:hover { color: #fff !important; }		
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-dark navbar-eve px-3">
    <span class="navbar-brand mb-0 h5">
        <i class="fas fa-space-shuttle mr-2"></i>Tabla Maestra de Pilotos
    </span>
    <span class="text-muted small">
        <i class="fas fa-users mr-1"></i><?php echo $totalPilotos; ?> pilotos
    </span>
</nav>

<!-- FILTROS -->
<div class="filter-bar">
    <form method="GET" class="form-inline flex-wrap" style="gap:10px;">

        <label class="text-light mr-1"><i class="fas fa-briefcase mr-1"></i>Oficio:</label>
        <select name="filter_trade" class="form-control form-control-sm mr-3">
            <option value="ALL">-- Todos --</option>
            <?php while ($t = mysqli_fetch_assoc($resTrades)): ?>
                <option value="<?php echo htmlspecialchars($t['tradefield']); ?>"
                    <?php echo ($filterTrade === $t['tradefield']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['tradefield']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label class="text-light mr-1"><i class="fas fa-building mr-1"></i>Corp:</label>
        <select name="filter_corp" class="form-control form-control-sm mr-3">
            <option value="ALL">-- Todas --</option>
            <?php while ($c = mysqli_fetch_assoc($resCorp)): ?>
                <option value="<?php echo htmlspecialchars($c['corporation_name']); ?>"
                    <?php echo ($filterCorp === $c['corporation_name']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['corporation_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label class="text-light mr-1"><i class="fas fa-folder mr-1"></i>Pocket:</label>
        <select name="filter_pocket" class="form-control form-control-sm mr-3">
            <option value="ALL">-- Todos --</option>
            <?php while ($pk = mysqli_fetch_assoc($resPockets)): ?>
                <option value="<?php echo htmlspecialchars($pk['pocket6']); ?>"
                    <?php echo ($filterPocket === $pk['pocket6']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($pk['pocket6']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="btn btn-sm btn-primary mr-2">
            <i class="fas fa-filter mr-1"></i> Filtrar
        </button>

        <?php if ($filterTrade !== 'ALL' || $filterCorp !== 'ALL' || $filterPocket !== 'ALL'): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times mr-1"></i> Limpiar
        </a>
        <?php endif; ?>

    </form>
</div>

<div class="container-fluid">

    <!-- Mensaje recálculo tradefield -->
    <div class="msg-tradefield">
        <i class="fas fa-check-circle mr-1"></i>
        Tradefield actualizado — <strong><?php echo $actualizados; ?></strong> pilotos procesados al cargar.
    </div>
    <?php echo updatecorpnames(); ?> 
    <!-- TABLA -->
    <div class="table-responsive">
        <table id="tablaPilotos" class="table table-sm table-bordered" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Img</th>
                    <th>Nombre</th>
                    <th>Pocket</th>
                    <th>Oficio</th>
                    <th title="Número de items"><i class="fas fa-boxes"></i> Items</th>
                    <th title="Evermarks"><i class="fas fa-shield-alt"></i> EM</th>
                    <th title="Número de naves"><i class="fas fa-rocket"></i> Ships</th>
					<th title="Jita Value"><i class="fas fa-dollar"></i> Jitav</th>
                    <th title="Wallet en millones ISK"><i class="fas fa-wallet"></i> Wallet M</th>
                    <th title="Actividad planetaria e industrial">PI / Jobs</th>
                    <th title="GF">GF</th>
                    <th title="Número de fits">Fits</th>
                    <th title="Security status">Sec.</th>
                    <th title="Skillpoints en millones"><i class="fas fa-microchip"></i> SP M</th>
                    <th title="Tipo de cuenta">Acc</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rowNum = 1;
                while ($p = mysqli_fetch_assoc($resPilots)):
                    $totalSP    = (($p['skillpoints'] ?? 0) + ($p['unalloc'] ?? 0)) / 1000000;
                    $walletM    = ($p['wallet'] ?? 0) / 1000000;                                        
                    $pocketColor = getColorPocket($p['pocket6']);
                    $secVal     = (float)($p['security'] ?? 0);
                    $secClass   = ($secVal >= 0) ? 'val-sec-pos' : 'val-sec-neg';

                    // AccType
                    $accIcon  = 'fa-question-circle';
                    $accColor = 'color:#6c757d';
                    if (strtolower($p['acctype'] ?? '') === 'omega') {
                        $accIcon  = 'fa-crown';
                        $accColor = 'color:#f1c40f';
                    } elseif (strtolower($p['acctype'] ?? '') === 'alpha') {
                        $accIcon  = 'fa-rocket';
                        $accColor = 'color:#95a5a6';
                    }

                    // Pocket texto claro u oscuro
                    $pocketBadgeClass = in_array(strtoupper(trim($p['pocket6'] ?? '')), ['YENN','SANGO']) ? 'dark-text' : '';
                ?>
                <tr>
                    <td class="text-center text-muted"><?php echo $rowNum++; ?></td>

                    <td class="text-center">
                        <img src="https://images.evetech.net/characters/<?php echo (int)$p['toon_number']; ?>/portrait?size=64"
                             class="portrait-sm" alt="<?php echo htmlspecialchars($p['toon_name']); ?>">
                    </td>

                    <td>
                        <strong class="text-white"><?php echo htmlspecialchars($p['toon_name']); ?></strong>
                        <?php if (!empty($p['corporation_name'])): ?>
                        <br><small class="text-muted" style="font-size:0.72rem;">
                            <i class="fas fa-building mr-1" style="color:#5dade2;"></i><?php echo htmlspecialchars($p['corporation_name']); ?>
                        </small>
                        <?php endif; ?>
                    </td>

                    <td class="text-center">
                        <span class="pocket-badge <?php echo $pocketBadgeClass; ?>"
                              style="background-color:<?php echo $pocketColor; ?>;">
                            <?php echo htmlspecialchars($p['pocket6'] ?? 'N/A'); ?>
                        </span>
                    </td>

                    <td>
                        <?php if (!empty($p['tradefield']) && $p['tradefield'] !== 'n/a'): ?>
                        <span class="trade-pill"><?php echo htmlspecialchars($p['tradefield']); ?></span>
                        <?php else: ?>
                        <small class="text-muted">—</small>
                        <?php endif; ?>
                    </td>

                    <td class="text-right"><?php echo number_format($p['numitems'] ?? 0); ?></td>

                    <td class="text-right val-em"><?php echo number_format($p['evermarks'] ?? 0); ?></td>

                    <td class="text-right"><?php echo number_format($p['numships'] ?? 0); ?></td>
                    <td class="text-right"><?php echo number_format($p['jitav'] ?? 0,2); ?></td>
                    <td class="text-right val-money"><?php echo number_format($walletM, 2); ?></td>

                    <td class="text-center">
                        <!-- DESPUÉS -->
    <?php echo geticons($p['toon_number']); ?>

                    </td>

                    <td class="text-center"><?php echo (int)($p['gf'] ?? 0); ?></td>

                    <td class="text-center"><?php echo (int)($p['numberfits'] ?? 0); ?></td>

                    <td class="text-center <?php echo $secClass; ?>">
                        <?php echo number_format($secVal, 2); ?>
                    </td>

                    <td class="text-right val-sp"><?php echo number_format($totalSP, 2); ?></td>

                    <td class="text-center">
                        <i class="fas <?php echo $accIcon; ?> acctype-icon"
                           style="<?php echo $accColor; ?>;"
                           title="<?php echo htmlspecialchars($p['acctype'] ?? 'N/A'); ?>"></i>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div><!-- /container-fluid -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4@1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script>
$(document).ready(function() {
    $('#tablaPilotos').DataTable({
        pageLength: 100,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
        order: [[13, 'desc']], // Ordenar por SP descendente por default
        language: {
            search:         "Buscar:",
            lengthMenu:     "Mostrar _MENU_ pilotos",
            info:           "Mostrando _START_ a _END_ de _TOTAL_ pilotos",
            infoEmpty:      "Sin resultados",
            infoFiltered:   "(filtrado de _MAX_ totales)",
            zeroRecords:    "No se encontraron pilotos",
            paginate: {
                first:    "«",
                last:     "»",
                next:     "›",
                previous: "‹"
            }
        },
        columnDefs: [
            { orderable: false, targets: [1, 10] }, // Imagen y PI/Jobs no ordenables
            { className: "text-center", targets: [0, 1, 3, 9, 10, 11, 12, 14] },
            { className: "text-right",  targets: [5, 6, 7, 8, 13] }
        ]		
    });
});
</script>
</body>
</html>
