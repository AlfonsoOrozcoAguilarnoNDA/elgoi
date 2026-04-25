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

$toon_number = isset($_GET['t']) ? (int)$_GET['t'] : 0;
if ($toon_number <= 0) die("<div class='alert alert-danger'>Error: Toon number inválido.</div>");

$sql_pilot = "SELECT toon_name, skillpoints, DOB, corporation, pocket6, numitems,
              email_pilot, acctype, lastsaved, race, security, unalloc, wallet
              FROM PILOTS WHERE toon_number = " . mysqli_real_escape_string($link, $toon_number);

$result_pilot = mysqli_query($link, $sql_pilot);
if (!$result_pilot || mysqli_num_rows($result_pilot) == 0)
    die("<div class='alert alert-danger'>Error: Piloto no encontrado.</div>");

$pilot = mysqli_fetch_assoc($result_pilot);
mysqli_free_result($result_pilot);

$pilot_name = htmlspecialchars($pilot['toon_name']);
$portrait   = "https://images.evetech.net/characters/{$toon_number}/portrait";

echo ui_header("Jack Knife - " . $pilot_name);
echo crew_navbar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Jack Knife — <?php echo $pilot_name; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-color: #0d0f11;
            color: #ced4da;
            font-family: 'Segoe UI', sans-serif;
            padding-top: 70px;
            padding-bottom: 70px;
        }

        /* ── CARDS BASE ── */
        .card-eve {
            background-color: #1a1d21;
            border: 1px solid #343a40;
            border-radius: 0;
            margin-bottom: 20px;
        }
        .card-eve .card-header {
            background-color: #0d0f11;
            border-bottom: 1px solid #343a40;
            padding: 10px 15px;
        }
        .card-eve .card-header h5 { margin-bottom: 0; color: #e0e0e0; }
        .card-eve .card-body { background-color: #1a1d21; }

        /* Accents */
        .acc-blue   { border-left: 4px solid #007bff; }
        .acc-green  { border-left: 4px solid #28a745; }
        .acc-yellow { border-left: 4px solid #ffc107; }
        .acc-cyan   { border-left: 4px solid #17a2b8; }
        .acc-red    { border-left: 4px solid #dc3545; }
        .acc-purple { border-left: 4px solid #6f42c1; }

        /* Accent icon colors */
        .ic-blue   { color: #007bff; }
        .ic-green  { color: #28a745; }
        .ic-yellow { color: #ffc107; }
        .ic-cyan   { color: #17a2b8; }
        .ic-red    { color: #dc3545; }
        .ic-purple { color: #6f42c1; }

        /* ── PORTRAIT HEADER ── */
        .portrait-img {
            width: 180px;
            height: 180px;
            border: 2px solid #495057;
            border-radius: 4px;
        }
        .pocket-badge {
            display: inline-block;
            padding: 3px 12px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            border-radius: 2px;
            background-color: #007bff;
            color: #fff;
            margin-top: 8px;
        }
        .data-label {
            font-size: 0.72rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .data-value { color: #e0e0e0; font-size: 0.88rem; margin-bottom: 10px; }

        /* ── TABLAS ── */
        .table-eve {
            color: #ced4da;
            font-size: 0.82rem;
            margin-bottom: 0;
        }
        .table-eve thead th {
            background-color: #0d0f11;
            color: #6c757d;
            border-color: #343a40;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .table-eve tbody tr:nth-child(odd)  { background-color: #1e2126; }
        .table-eve tbody tr:nth-child(even) { background-color: #1a1d21; }
        .table-eve tbody tr:hover           { background-color: #2a3040 !important; color: #fff; }
        .table-eve td, .table-eve th        { border-color: #2c3035; vertical-align: middle; }
        .table-eve tfoot td {
            background-color: #0d0f11;
            border-color: #343a40;
            color: #adb5bd;
        }

        /* Montos positivos/negativos */
        .val-pos { color: #28a745; font-weight: 700; font-family: monospace; }
        .val-neg { color: #dc3545; font-weight: 700; font-family: monospace; }
        .val-mon { color: #f39c12; font-family: monospace; }
    </style>
</head>
<body>
<div class="container-fluid">

    <!-- ── HEADER PILOTO ── -->
    <div class="card-eve acc-blue mb-4">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-id-card mr-2 ic-blue"></i>Jack Knife Operational Dossier</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 text-center">
                    <img src="<?php echo $portrait; ?>" class="portrait-img" alt="<?php echo $pilot_name; ?>">
                    <div class="mt-2 text-white font-weight-bold"><?php echo $pilot_name; ?></div>
                    <?php
                    $pocket_colors = ['EXPER'=>'#28a745','CLEAN'=>'#0078d7','SANGO'=>'#ffc107',
                                      'LUCKY'=>'#6f42c1','NOKIA'=>'#e81123','YENN'=>'#cccccc','OTHER'=>'#fd7e14'];
                    $pv = strtoupper(trim($pilot['pocket6']??''));
                    $pb = $pocket_colors[$pv] ?? '#495057';
                    $pt = in_array($pv,['SANGO','YENN']) ? '#111' : '#fff';
                    ?>
                    <div style="display:inline-block;background-color:<?php echo $pb;?>;color:<?php echo $pt;?>;padding:3px 12px;font-size:0.75rem;font-weight:700;border-radius:2px;margin-top:6px;">
                        <?php echo htmlspecialchars($pilot['pocket6']); ?>
                    </div>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-building mr-1"></i>Corporación</div>
                            <div class="data-value"><?php echo htmlspecialchars($pilot['corporation']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-user-tag mr-1"></i>Tipo de Cuenta</div>
                            <div class="data-value" style="color:<?php echo strtolower($pilot['acctype'])=='omega'?'#f1c40f':'#95a5a6';?>;">
                                <?php echo htmlspecialchars($pilot['acctype']); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-flag mr-1"></i>Raza</div>
                            <div class="data-value"><?php echo htmlspecialchars($pilot['race']); ?></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-birthday-cake mr-1"></i>Fecha de Nacimiento</div>
                            <div class="data-value"><?php echo $pilot['DOB'] ? date('Y-m-d', strtotime($pilot['DOB'])) : 'N/A'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-shield-alt mr-1"></i>Seguridad</div>
                            <div class="data-value" style="color:<?php echo $pilot['security']>=0?'#28a745':'#dc3545';?>;">
                                <?php echo number_format($pilot['security'],2); ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-brain mr-1"></i>Skill Points</div>
                            <div class="data-value">
                                <?php echo number_format($pilot['skillpoints']/1000000,2); ?> M
                                <?php if ($pilot['unalloc']>0): ?>
                                <span class="text-success" style="font-size:0.78rem;">+ <?php echo number_format($pilot['unalloc']/1000000,2); ?> M free</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-boxes mr-1"></i>Items Totales</div>
                            <div class="data-value"><?php echo number_format($pilot['numitems']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-wallet mr-1"></i>ISK en Wallet</div>
                            <div class="data-value val-mon"><?php echo number_format($pilot['wallet'],2); ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="data-label"><i class="fas fa-clock mr-1"></i>Última Actualización</div>
                            <div class="data-value"><small><?php echo $pilot['lastsaved']; ?></small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── WEAPONS / SHIPS + MAGIC 14 ── -->
    <div class="row mb-2">
        <div class="col-md-6"><?php echo extrapilotdata2($toon_number); ?></div>
        <div class="col-md-6"><?php echo magic14($toon_number); ?></div>
    </div>

    <!-- ── SECCIONES ── -->
    <?php echo corpstory2($toon_number); ?>
    <?php echo contacts2($toon_number); ?>
    <?php echo mails2($toon_number); ?>
    <?php echo notifications2($toon_number); ?>
    <?php echo journal2($toon_number); ?>
    <?php echo transactions2($toon_number); ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<?php echo ui_footer(); ?>
</body>
</html>

<?php
// ====================================================================
// FUNCIONES — lógica intacta, solo clases CSS actualizadas
// ====================================================================

function extrapilotdata2($t) {
    $th = "<thead><tr style='background-color:#0d0f11;'>";
    $h  = "<div class='card-eve acc-cyan mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-crosshairs mr-2 ic-cyan'></i>Weapons &amp; Ships Skills</h5></div>";
    $h .= "<div class='card-body p-0'>";

    // Weapons
    $h .= "<table class='table table-bordered table-sm table-eve mb-0'>";
    $h .= "<thead><tr style='background-color:#0d0f11;'><th colspan='5' style='color:#17a2b8;'>Weapons</th></tr>";
    $h .= "<tr><th>System</th><th>Small</th><th>Medium</th><th>Large</th><th>Capital</th></tr></thead><tbody>";
    $h .= "<tr><th>Energy</th>"    .q2(3303,$t).q2(3306,$t).q2(3309,$t).q2(20327,$t)."</tr>";
    $h .= "<tr><th>Hybrid</th>"    .q2(3301,$t).q2(3304,$t).q2(3307,$t).q2(21666,$t)."</tr>";
    $h .= "<tr><th>Projectile</th>".q2(3302,$t).q2(3305,$t).q2(3308,$t).q2(21667,$t)."</tr>";
    $h .= "<tr><th>Trig</th>"      .q2(47870,$t).q2(47871,$t).q2(47872,$t).q2(52998,$t)."</tr>";
    $h .= "<tr><th>Drones</th>"    .q2(24241,$t).q2(33699,$t).q2(3441,$t)."<td class='text-muted'>n/a</td></tr>";
    $h .= "</tbody></table>";

    // Ships
    $h .= "<table class='table table-bordered table-sm table-eve mb-0'>";
    $h .= "<thead><tr style='background-color:#0d0f11;'><th colspan='6' style='color:#17a2b8;'>Ships</th></tr>";
    $h .= "<tr><th>Race</th><th>Amarr</th><th>Caldari</th><th>Gallente</th><th>Minmatar</th><th>Trig</th></tr></thead><tbody>";
    $h .= "<tr><th>Frigate</th>"     .q2(3331,$t).q2(3330,$t).q2(3328,$t).q2(3329,$t).q2(47867,$t)."</tr>";
    $h .= "<tr><th>Destroyer</th>"   .q2(33091,$t).q2(33092,$t).q2(33093,$t).q2(33094,$t).q2(49742,$t)."</tr>";
    $h .= "<tr><th>Cruiser</th>"     .q2(3335,$t).q2(3334,$t).q2(3332,$t).q2(3333,$t).q2(47868,$t)."</tr>";
    $h .= "<tr><th>BattleCruiser</th>".q2(33095,$t).q2(33096,$t).q2(33097,$t).q2(33098,$t).q2(49743,$t)."</tr>";
    $h .= "<tr><th>Battleship</th>"  .q2(3339,$t).q2(3338,$t).q2(3336,$t).q2(3337,$t).q2(47869,$t)."</tr>";
    $h .= "<tr><th>Dreadnought</th>" .q2(20525,$t).q2(20530,$t).q2(20531,$t).q2(20532,$t).q2(52997,$t)."</tr>";
    $h .= "<tr><th>Carrier</th>"     .q2(24311,$t).q2(24312,$t).q2(24313,$t).q2(24314,$t)."<td class='text-muted'>n/a</td></tr>";
    $h .= "</tbody></table>";

    // Others
    $h .= "<table class='table table-bordered table-sm table-eve mb-0'>";
    $h .= "<thead><tr style='background-color:#0d0f11;'><th colspan='2' style='color:#17a2b8;'>Others</th></tr>";
    $h .= "<tr><th>Skill</th><th>Level</th></tr></thead><tbody>";
    $h .= "<tr><th>Mining Barge</th>"       .q2(17940,$t)."</tr>";
    $h .= "<tr><th>Exhumers</th>"           .q2(22551,$t)."</tr>";
    $h .= "<tr><th>Astrogeology</th>"       .q2(3410,$t)."</tr>";
    $h .= "<tr><th>Orca Industrial</th>"    .q2(3184,$t)."</tr>";
    $h .= "<tr><th>Rorqual Industrial</th>" .q2(28374,$t)."</tr>";
    $h .= "<tr><th>Advanced Spaceship</th>" .q2(20342,$t)."</tr>";
    $h .= "<tr><th>Cybernetics</th>"        .q2(3411,$t)."</tr>";
    $h .= "<tr><th>Astrometric</th>"        .q2(3412,$t)."</tr>";
    $h .= "</tbody></table>";

    $h .= "</div></div>";
    return $h;
}

function magic14($t) {
    $h  = "<div class='card-eve acc-yellow mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-star mr-2 ic-yellow'></i><a href='https://wiki.eveuniversity.org/The_Magic_14' target='_blank' style='color:#ffc107;'>The Magic 14</a></h5></div>";
    $h .= "<div class='card-body p-0'>";
    $h .= "<table class='table table-bordered table-sm table-eve mb-0'>";
    $h .= "<thead><tr><th>#</th><th>Skill</th><th>Level</th></tr></thead><tbody>";
    $h .= "<tr><th>1</th><th>CPU Management</th>"              .q2(3426,$t)."</tr>";
    $h .= "<tr><th>2</th><th>Power Grid Management</th>"       .q2(3413,$t)."</tr>";
    $h .= "<tr><th>3</th><th>Capacitor Management</th>"        .q2(3418,$t)."</tr>";
    $h .= "<tr><th>4</th><th>Capacitor Systems Operation</th>" .q2(3417,$t)."</tr>";
    $h .= "<tr><th>5</th><th>Mechanics</th>"                   .q2(3392,$t)."</tr>";
    $h .= "<tr><th>6</th><th>Hull Upgrades</th>"               .q2(3394,$t)."</tr>";
    $h .= "<tr><th>7</th><th>Shield Management</th>"           .q2(3419,$t)."</tr>";
    $h .= "<tr><th>8</th><th>Shield Operation</th>"            .q2(3416,$t)."</tr>";
    $h .= "<tr><th>9</th><th>Long Range Targeting</th>"        .q2(3428,$t)."</tr>";
    $h .= "<tr><th>10</th><th>Signature Analysis</th>"         .q2(3431,$t)."</tr>";
    $h .= "<tr><th>11</th><th>Navigation</th>"                 .q2(3449,$t)."</tr>";
    $h .= "<tr><th>12</th><th>Evasive Maneuvering</th>"        .q2(3453,$t)."</tr>";
    $h .= "<tr><th>13</th><th>Warp Drive Operation</th>"       .q2(3455,$t)."</tr>";
    $h .= "<tr><th>14</th><th>Spaceship Command</th>"          .q2(3327,$t)."</tr>";
    $h .= "</tbody></table></div></div>";
    return $h;
}

function Q2($skill, $t) {
    global $link;
    $sql = "SELECT typeID, rank FROM EVE_CHARSKILLS WHERE toon = $t AND typeID = $skill";
    list($type2, $rank) = avalues319($sql);
    if ($type2 != $skill) return "<td></td>";
    $class = '';
    switch($rank) {
        case '0': $class = 'table-danger';  break;
        case '1': case '2': $class = 'table-warning'; break;
        case '3': $class = 'bg-warning';   break;
        case '4': $class = 'table-success'; break;
        case '5': $class = 'bg-success text-white'; break;
    }
    return "<td class='text-center {$class}'><strong>{$rank}</strong></td>";
}

function corpstory2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, corp_story FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-blue mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-blue'></i>No hay historial de corporaciones para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-blue mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-history mr-2 ic-blue'></i>Historial Corporativo — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>Corporación</th><th>Link</th><th>Record ID</th><th>Fecha Inicio</th><th>Días</th></tr></thead><tbody>";
    $csh=0; $old_date="now()";
    foreach ($xml->item as $item) {
        $csh++;
        list($duration) = avalues319("SELECT DATEDIFF($old_date, '$item->start_date')");
        $corp     = $item->corporation_id;
        $corpName = CorpfromInternet($corp);
        $npc      = (substr($corp,0,3)=='100') ? "<span class='text-muted'>(NPC)</span>" : "";
        $linkA    = "";
        if ($npc == "") {
            $cu = str_replace(" ","+",$corpName);
            $linkA = "<a href='https://evewho.com/corp/$cu' target='_blank' class='text-info'>Evewho</a>";
        }
        $h .= "<tr><td class='text-muted'>{$csh}</td><td class='text-white'>" . htmlspecialchars($corpName) . " {$npc}</td><td>{$linkA}</td><td>{$item->record_id}</td><td>{$item->start_date}</td><td class='text-right'>{$duration}</td></tr>";
        $old_date = "'$item->start_date'";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

function contacts2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, contacts FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-green mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-green'></i>No hay contactos para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-green mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-address-book mr-2 ic-green'></i>Contactos — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>ID</th><th>Tipo</th><th>Nombre</th><th>Standing</th></tr></thead><tbody>";
    $csh=0;
    foreach ($xml->item as $item) {
        $csh++;
        $who=$item->contact_id; $wt=$item->contact_type; $st=(float)$item->standing;
        $sc = $st>=5?'#28a745':($st>=0?'#007bff':($st<-5?'#dc3545':'#ffc107'));
        $name_val = ($wt=='character') ? PilotfromInternet($who) : (($wt=='alliance') ? AlliancefromInternet($who) : '-');
        $h .= "<tr><td class='text-muted'>{$csh}</td><td>{$who}</td>";
        $h .= "<td><span style='background:#1a1d21;border:1px solid #17a2b8;color:#17a2b8;padding:1px 7px;border-radius:2px;font-size:0.72rem;'>{$wt}</span></td>";
        $h .= "<td class='text-white'>" . htmlspecialchars($name_val) . "</td>";
        $h .= "<td><span style='background-color:{$sc};color:#fff;padding:2px 8px;border-radius:2px;font-size:0.72rem;font-weight:700;'>{$st}</span></td></tr>";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

function mails2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, mails FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-cyan mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-cyan'></i>No hay correos para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-cyan mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-envelope mr-2 ic-cyan'></i>Últimos 50 Correos — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>De</th><th>Leído</th><th>Mail ID</th><th>Asunto</th><th>Fecha</th><th>Destinatarios</th></tr></thead><tbody>";
    $csh=0;
    foreach ($xml->item as $item) {
        $csh++;
        $from     = PilotfromInternet($item->from);
        $from_url = str_replace(" ","+",$from);
        $linkA    = ($item->from==$t) ? $from : "<a href='https://evewho.com/pilot/$from_url' target='_blank' class='text-info'>" . htmlspecialchars($from) . "</a>";
        $read_b   = ($item->is_read=='true')
            ? "<span style='background:#343a40;color:#adb5bd;padding:1px 7px;border-radius:2px;font-size:0.7rem;'>Leído</span>"
            : "<span style='background:#1a1200;border:1px solid #ffc107;color:#ffc107;padding:1px 7px;border-radius:2px;font-size:0.7rem;'>No leído</span>";
        $h .= "<tr><td class='text-muted'>{$csh}</td><td>{$linkA}</td><td>{$read_b}</td><td>{$item->mail_id}</td>";
        $h .= "<td class='text-white'>" . htmlspecialchars($item->subject) . "</td><td><small class='text-muted'>{$item->timestamp}</small></td><td>";
        if (isset($item->recipients)) {
            foreach ($item->recipients->item as $rec) {
                $rt=$rec->recipient_type; $ri=$rec->recipient_id;
                if ($rt=='character')    { $rn=PilotfromInternet($ri); $ru=str_replace(" ","+",$rn); $h.="<a href='https://evewho.com/pilot/$ru' target='_blank' class='text-info'>" . htmlspecialchars($rn) . "</a> "; }
                elseif ($rt=='corporation') { $rn=CorpfromInternet($ri); $ru=str_replace(" ","+",$rn); $h.="<a href='https://evewho.com/corp/$ru' target='_blank' class='text-info'>" . htmlspecialchars($rn) . "</a> "; }
                elseif ($rt=='alliance')    { $rn=AlliancefromInternet($ri); $ru=str_replace(" ","+",$rn); $h.="<a href='https://evewho.com/alli/$ru' target='_blank' class='text-info'>" . htmlspecialchars($rn) . "</a> "; }
                else $h .= "{$ri} ({$rt}) ";
            }
        }
        $h .= "</td></tr>";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

function notifications2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, notifications FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-yellow mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-yellow'></i>No hay notificaciones para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-yellow mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-bell mr-2 ic-yellow'></i>Notificaciones — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>ID</th><th>Sender ID</th><th>Tipo</th><th>Texto</th><th>Tipo Notif</th><th>Timestamp</th></tr></thead><tbody>";
    $csh=0;
    foreach ($xml->item as $item) {
        $csh++;
        $st=$item->sender_type; $who='';
        if ($st=='corporation') $who=CorpfromInternet($item->sender_id);
        if ($st=='character')   $who=PilotfromInternet($item->sender_id);
        $killer="";
        if ($item->type=='KillRightEarned') {
            $killer=trim(str_replace("charID:","",$item->text));
            if ($killer!='') { $kn=PilotfromInternet($killer); $ku=str_replace(" ","+",$kn); $killer="<a href='https://evewho.com/pilot/$ku' target='_blank' class='text-info'>" . htmlspecialchars($kn) . "</a>"; }
        }
        $h .= "<tr><td class='text-muted'>{$csh}</td><td>{$item->notification_id}</td>";
        $h .= "<td>{$item->sender_id} <span class='text-info'>" . htmlspecialchars($who) . "</span></td>";
        $h .= "<td><span style='background:#001a2a;border:1px solid #17a2b8;color:#17a2b8;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>{$st}</span></td>";
        $h .= "<td class='text-muted'><small>{$item->text} {$killer}</small></td>";
        $h .= "<td><span style='background:#343a40;color:#adb5bd;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>{$item->type}</span></td>";
        $h .= "<td><small class='text-muted'>{$item->timestamp}</small></td></tr>";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

function journal2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, journal FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-blue mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-blue'></i>No hay journal para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-blue mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-book mr-2 ic-blue'></i>Wallet Journal — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>Monto</th><th>Balance</th><th>Context ID</th><th>Tipo Context</th><th>Fecha</th><th>Descripción</th><th>Party 1</th><th>ID</th><th>Ref Type</th><th>Party 2</th></tr></thead><tbody>";
    $csh=0;
    foreach ($xml->item as $item) {
        $csh++;
        $first=$item->first_party_id; $second=$item->second_party_id;
        if (substr($first,0,4)=='1000') list($first)=avalues319("SELECT itemName FROM invUniqueNames WHERE itemId='$first'");
        if (substr($second,0,4)=='1000') list($second)=avalues319("SELECT itemName FROM invUniqueNames WHERE itemId='$second'");
        if ($first==$item->first_party_id)   $first=PilotfromInternet($first);
        if ($second==$item->second_party_id) $second=PilotfromInternet($second);
        $amount=(float)$item->amount;
        $ac = $amount>=0 ? 'val-pos' : 'val-neg';
        $h .= "<tr><td class='text-muted'>{$csh}</td>";
        $h .= "<td class='text-right {$ac}'>" . cfdinumbers($amount) . "</td>";
        $h .= "<td class='text-right val-mon'>" . cfdinumbers($item->balance) . "</td>";
        $h .= "<td>{$item->context_id}</td>";
        $h .= "<td><span style='background:#343a40;color:#adb5bd;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>{$item->context_id_type}</span></td>";
        $h .= "<td><small class='text-muted'>{$item->date}</small></td>";
        $h .= "<td class='text-muted'><small>" . htmlspecialchars($item->description) . "</small></td>";
        $h .= "<td class='text-info'>" . htmlspecialchars($first) . "</td>";
        $h .= "<td>{$item->id}</td>";
        $h .= "<td><span style='background:#001a2a;border:1px solid #17a2b8;color:#17a2b8;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>{$item->ref_type}</span></td>";
        $h .= "<td class='text-info'>" . htmlspecialchars($second) . "</td></tr>";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

function transactions2($t) {
    global $link;
    $sql = "SELECT toon_name, email_pilot, transactions FROM PILOTS WHERE toon_number = $t";
    list($name, $pilot, $data) = avalues319($sql);
    if ($data == '[]' || empty($data))
        return "<div class='card-eve acc-green mb-3'><div class='card-body text-muted'><i class='fas fa-info-circle mr-1 ic-green'></i>No hay transacciones para " . htmlspecialchars($name) . "</div></div>";
    $data = stripslashes($data);
    $xml  = new SimpleXMLElement(json2xml($data));
    $h  = "<div class='card-eve acc-green mb-3'>";
    $h .= "<div class='card-header'><h5><i class='fas fa-exchange-alt mr-2 ic-green'></i>Transacciones — " . htmlspecialchars($name) . "</h5></div>";
    $h .= "<div class='card-body p-0'><div class='table-responsive'>";
    $h .= "<table class='table table-sm table-eve mb-0'><thead><tr><th>#</th><th>Trans ID</th><th>Cliente</th><th>Fecha</th><th>Compra</th><th>Personal</th><th>Journal Ref</th><th>Ubicación</th><th>Type ID</th><th>Item</th><th>Cantidad</th><th>Precio Unit</th><th>Total</th></tr></thead><tbody>";
    $csh=0;
    foreach ($xml->item as $item) {
        $csh++;
        $customer=$item->client_id;
        if (substr($customer,0,4)=='1000') list($customer)=avalues319("SELECT itemName FROM invUniqueNames WHERE itemId='$customer'");
        if ($customer==$item->client_id) $customer=PilotfromInternet($customer);
        $station=$item->location_id;
        list($sn)=avalues319("SELECT stationName FROM staStations WHERE stationID='$station'");
        if ($sn=='') $sn=CitadelfromInternet($station);
        if ($sn==$station) $sn="Citadel #$station";
        list($desc)=avalues319("SELECT typeName FROM invTypes WHERE typeID='{$item->type_id}'");
        if ($desc=='') $desc='n/a';
        $ib = ($item->is_buy=='true')
            ? "<span style='background:#1a0000;border:1px solid #dc3545;color:#dc3545;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>Compra</span>"
            : "<span style='background:#0d1f0d;border:1px solid #28a745;color:#28a745;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>Venta</span>";
        $ip = ($item->is_personal=='true')
            ? "<span style='background:#001a2a;border:1px solid #17a2b8;color:#17a2b8;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>Sí</span>"
            : "<span style='background:#343a40;color:#6c757d;padding:1px 6px;border-radius:2px;font-size:0.7rem;'>No</span>";
        $total=CFDINumbers($item->unit_price*$item->quantity);
        $h .= "<tr><td class='text-muted'>{$csh}</td><td>{$item->transaction_id}</td>";
        $h .= "<td class='text-info'>" . htmlspecialchars($customer) . "</td>";
        $h .= "<td><small class='text-muted'>{$item->date}</small></td>";
        $h .= "<td>{$ib}</td><td>{$ip}</td><td>{$item->journal_ref_id}</td>";
        $h .= "<td><small class='text-muted'>" . htmlspecialchars($sn) . "</small></td>";
        $h .= "<td>{$item->type_id}</td>";
        $h .= "<td class='text-white'>" . htmlspecialchars($desc) . "</td>";
        $h .= "<td class='text-right'>" . cfdinumbers($item->quantity) . "</td>";
        $h .= "<td class='text-right val-mon'>" . cfdinumbers($item->unit_price) . "</td>";
        $h .= "<td class='text-right val-pos'><strong>" . cfdinumbers($total) . "</strong></td></tr>";
    }
    $h .= "</tbody></table></div></div></div>";
    return $h;
}

// ── Funciones auxiliares — SIN CAMBIOS ──

function json2xml($json) {
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
}

function randomstring($leng=10){if(intval($leng)==0)$leng=10;return substr(rtrim(base64_encode(md5(microtime())),"="),0,$leng);}
function PilotfromInternet($pn){global $link;if($pn=='')return '';list($cache)=avalues319("SELECT ESI_NAME FROM ESI_CACHE WHERE ESI_TYPE='PILOT' AND ESI_ID='$pn'");if($cache!='')return trim($cache);$ch=curl_init();curl_setopt($ch,CURLOPT_URL,"https://esi.evetech.net/latest/characters/$pn/");curl_setopt($ch,CURLOPT_USERAGENT,"EsiKnife Auth agent.");curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);$r=curl_exec($ch);curl_close($ch);if($r===false||$r=='{"error":"Character not found"}')return "Character not found";$cd=json_decode($r);if(!property_exists($cd,"name"))return "Character not found";$name=$cd->name;if($name!=''){$name=addslashes($name);doaction("INSERT INTO ESI_CACHE (ESI_ID,ESI_TYPE,ESI_NAME,ESI_WHEN) VALUES ('$pn','PILOT','$name',NOW())","");} return trim($name);}
function CorpfromInternet($cid){global $link;if($cid=='')return "Unknown";list($cache)=avalues319("SELECT ESI_NAME FROM ESI_CACHE WHERE ESI_TYPE='CORP' AND ESI_ID='$cid'");if($cache!='')return $cache;$ch=curl_init();curl_setopt($ch,CURLOPT_URL,"https://esi.evetech.net/latest/corporations/$cid/");curl_setopt($ch,CURLOPT_USERAGENT,"EsiKnife Auth agent.");curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);$r=curl_exec($ch);curl_close($ch);if($r===false)return $cid;$cd=json_decode($r);$name=$cd->name??'';if($name!=''){$name=addslashes($name);doaction("INSERT INTO ESI_CACHE (ESI_ID,ESI_TYPE,ESI_NAME,ESI_WHEN) VALUES ('$cid','CORP','$name',NOW())","");} return $name;}
function AlliancefromInternet($aid){global $link;list($cache)=avalues319("SELECT ESI_NAME FROM ESI_CACHE WHERE ESI_TYPE='ALLIANCE' AND ESI_ID='$aid'");if($cache!='')return $cache;$ch=curl_init();curl_setopt($ch,CURLOPT_URL,"https://esi.evetech.net/latest/alliances/names/?alliance_ids=$aid&datasource=tranquility");curl_setopt($ch,CURLOPT_USERAGENT,"EsiKnife Auth agent.");curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);$r=curl_exec($ch);curl_close($ch);if($r===false)return $aid;$r2=str_replace(['"','{','}','[',']'],'',$r);$dim=explode("alliance_name:",$r2);$name=$dim[1]??'';if($name!=''){$name=addslashes($name);doaction("INSERT INTO ESI_CACHE (ESI_ID,ESI_TYPE,ESI_NAME,ESI_WHEN) VALUES ('$aid','ALLIANCE','$name',NOW())","");} return $name;}
function CitadelfromInternet($cn){global $link;list($cache)=avalues319("SELECT ESI_NAME FROM ESI_CACHE WHERE ESI_TYPE='CITADEL' AND ESI_ID='$cn'");if($cache!='')return $cache;$ch=curl_init();curl_setopt($ch,CURLOPT_URL,"https://stop.hammerti.me.uk/api/citadel/$cn");curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);$r=curl_exec($ch);curl_close($ch);if($r===false)return $cn;$c2=stripslashes(str_replace($cn,'item',$r));$xml=new SimpleXMLElement(json2xml($c2));$name=addslashes($xml->item->name??'');if($name!='')doaction("INSERT INTO ESI_CACHE (ESI_ID,ESI_TYPE,ESI_NAME,ESI_WHEN) VALUES ('$cn','CITADEL','$name',NOW())","");return $name;}
function CFDINumbers($val,$p=2){if($val==0)return "0.00";return bcdiv($val,1,2);}
//function cfdinumbers($val,$p=2){return CFDINumbers($val,$p);}
function aValues319($Qx){global $link;$rsX=mysqli_query($link,$Qx);$Qx2=strtolower($Qx);if(left($Qx2,6)<>'select')return "";$aDataX=[];$rows=mysqli_num_rows($rsX);if($rows==0)return ["",""];$Campos=mysqli_num_fields($rsX);while($regX=mysqli_fetch_array($rsX)){for($iX=0;$iX<$Campos;$iX++){$finfo=mysqli_fetch_field_direct($rsX,$iX);$aDataX[]=$regX[$finfo->name];}}return $aDataX;}
//function avalues319($Qx){return aValues319($Qx);}
function left($str,$length){return substr($str,0,$length);}
function right($str,$length){return substr($str,-$length);}
function doaction($sql,$msg){return "";}
?>
