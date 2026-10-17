<?php
session_start();
header('Expires: Thu, 1 Jan 1970 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0',false);
header('Pragma: no-cache');

ini_set("memory_limit","384M");

date_default_timezone_set('America/Mexico_City');
if (!function_exists('check_authorization')) {
    // Si NO existe la función, incluimos el archivo que la define
    include_once "../config.php";
}
check_authorization(); // of config.php
if ($_SESSION['pilot_name']==""){
  // redirige a fleet_login.php
   header('Location: ../fleet_login.php');
   exit;
}

//$_SESSION['character_id']=000000; // debug your pilot number
//$_SESSION['pilot_name']="Your name"; // debug
$_SESSION['youremail']="admin@example.com"; // temporal


//date_default_timezone_set('UTC'); // Potential for mistakes

if (file_exists(__DIR__.'/index161.php')) {
    require __DIR__.'/index161.php';
} else {
    function barrido(){ return ""; }
}

if(!isset($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on")
{
    //Tell the browser to redirect to the HTTPS URL.
    header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
    //Prevent the rest of the script from executing.
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
$m=$_GET['module'] ?? "";
$ipaddress = $_SERVER['REMOTE_ADDR'];
if ($m=="steptwo") steptwobit();
if ($m=='logout') {
  session_destroy();
  session_start();
}
//databaseconnect();

echo "<!DOCTYPE html>
<html lang='en'>
  <title>".domain()."</title>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>
</head>
<body>";

echo "<script type='text/javascript' src='includes/sortable.js'></script>";
if ($m=='dt2') { echo dt2().footboot(); die(); }
if ($m=='dp2') { echo dp2().footboot(); die(); }
if ($m=='161') { echo boot().barrido().footboot(); die(); }
if ($m=='162') { echo boot().inventory().footboot(); die(); }
if ($m=='170') { echo boot().whatship().footboot(); die(); }
if ($m=='190') { echo css().boot().showmyships().footboot(); die(); }
if ($m=='191') { echo css().boot().Limpiador().footboot(); die(); }
if ($m=='settings') { echo settings().footboot(); die(); }

if ($m=='113v2') { echo charskills_and_queue().footboot(); die(); }
if ($m=='119') { echo module119().footboot(); die(); }
if ($m=='201') { echo boot().gempanels().footboot(); die(); }
if ($m=='202') { echo boot().gemplanets().footboot(); die(); }
if ($m=='207') { echo boot().gemjerarquia().footboot(); die(); }

if ($m=='fit') {
     $who=$_GET['who'];
     $u=$_GET['u']; 
     $sql="select type_description,quantity from EVE_ASSETS where eveunique='$u' or location_id=$u";
     echo $sql; 
     $pass =sql2table($sql,"assets for $name","#c3dde0");
     echo $pass.footboot();
     die ();
} 
if ($m=='item') { echo boot().whoitem().footboot(); die(); }
if ($m=='122') { echo checkalpha().footboot(); die(); }
if ($m=='127') { echo planets().footboot();  die(); }
if ($m=='400') { echo is400().footboot(); die(); }

if ($_SESSION['character_id']<>'') die (insidebox().footboot());
die("error 404");

function randomstring($leng= 10){
if (intval($leng)==0) $leng=10;
return left(rtrim(base64_encode(md5(microtime())),"="),$leng);
} // tandomstring
function left($str, $length) {
     return substr($str, 0, $length);
}

function right($str, $length) {
     return substr($str, -$length);
}
function css(){
return "<style type='text/css'>
<!--

.our-team .team_img{
    position: relative;
    overflow: hidden;
}
.our-team .team_img:after{
    content: '';
    width: 100%;
    height: 100%;
    background-color: rgba(255,255,255,0.2);
    position: absolute;
    bottom: -100%;
    left: 0;
    transition: all 0.3s ease 0s;
}
.our-team:hover .team_img:after{
    bottom: 0;
}
.our-team img{
    width: 100%;
    height: auto;
}

.success { padding: .5em 1em; font-family:Lucid; background-color:#dff0d8; font-weight:bold; color:#468847;}
.error {  padding: .5em 1em; font-family:Lucid; background-color:#f2dede; font-weight:bold; color:#b94a48;}
table.hovertable {
	font-family: verdana,arial,sans-serif;
	font-size:11px;
	color:#333333;
	border-width: 1px;
	border-color: #999999;
	border-collapse: collapse;
}
table.hovertable th {
	background-color:#c3dde0;
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #a9c6c9;
	color:#000000;
}
table.hovertable tr {
	background-color:#d4e3e5;
}
table.hovertable td {
	border-width: 1px;
	padding: 8px;
	border-style: solid;
	border-color: #a9c6c9;
}
-->
</style>
";
} //css
function inventory(){
global $link;
$pass = "<script type='text/javascript' src='Includes/sortable.js'></script>";
$rs=mysqli_query($link,"SELECT toon_number,toon_name,lastsaved,skillpoints,
 DATEDIFF(now(), lastsaved) as DIAS  from PILOTS 
where toon_name not in ('All Others Catalog','Nokia Catalog','Experiment Catalog') 
order by jitav desc,skillpoints desc,toon_name");
$pass .= mysqli_error($link);
$num=0;
$tforge=0;
$pass .="<div style='overflow-x: auto; property'>"; 
$table="<table border=2>
<tr><th>#</th><th>Pilot</th><th>Group</th><th>Days</th><th>m SP</th><th>Items</th><th>Jita Value</th></tr>";
while ($row = mysqli_fetch_object($rs)) {
  $sql2="delete from EVE_ASSETS where type_description like 'Civilian%' and toon_number='$row->toon_number'";
  list($dummy)=avalues319($sql2);
  $sql2="delete from EVE_ASSETS where type_description in ('Reaper','Velator','Ibis','Impairor','1MN Civilian Afterburner','Capsule') and toon_number='$row->toon_number'";
  list($dummy)=avalues319($sql2);  

  list($items)=avalues319("select count(*) from EVE_ASSETS where toon_number='$row->toon_number'" );
  list($forgev)=avalues319("select sum(forge_value) from EVE_ASSETS where toon_number='$row->toon_number'" );
  $sk=round($row->skillpoints/1000000,2);
  $forgev=round($forgev/1000000/1000,2);
  list($dummy)=avalues319("update PILOTS set jitav=$forgev where toon_number='$row->toon_number'");
     
  $backg="";
  if ($items<31) $backg=" style='background-color:yellow; text-align:center'";
  if ($items>0 or $row->DIAS>8) {
     $num ++;
     $table .="<tr><th>$num</th><td>$row->toon_name</td><td>".pilotclass($row->toon_name)."</td>
     <td>$row->DIAS</td><td align='right'> $sk</td><td $backg  style='text-align:right'>$items</td><td align='right'>$forgev</td></tr>";
     $tforge +=$forgev;
  }  
}
$table .="</table>Tf B $tforge";
$pass .= $table . pseudonotes() . more8().Alfaomega("Omega").alfaomega("Alpha");
$pass .= gridships();

$pass .= gridminerals("Filaments","'Tranquil Exotic Filament','Tranquil Dark Filament','Tranquil Electrical Filament','Tranquil Gamma Filament','Tranquil Firestorm Filament','Calm Exotic Filament','Calm Dark Filament','Calm Electrical Filament','Calm Gamma Filament','Calm Firestorm Filament','Agitated Exotic Filament','Agitated Dark Filament','Agitated Electrical Filament','Agitated Gamma Filament','Agitated Firestorm Filament','Fierce Exotic Filament','Fierce Dark Filament','Fierce Electrical Filament','Fierce Gamma Filament','Fierce Firestorm Filament'");
$pass .= gridminerals("minerals","'tritanium','Isogen','Mexallon','Nocxium','Zydrine','Megacyte','Pyerite','Metal Scraps'");
$pass .= gridminerals("Planetary T0","'Microorganisms','Carbon Compounds','Planktic Colonies','Non-CS Crystals','Ionic Solutions','Autotrophs','Reactive Gas','Noble Gas','Suspended Plasma','Noble Metals','Complex Organisms','Base Metals','Felsic Magma','Heavy Metals','Aqueous Liquids'");
$pass .= gridminerals("Planetary T1","'Bacteria','Biofuels','Biomass','Chiral Structures','Electrolytes','Industrial Fibers','Oxidizing Compound','Oxygen','Plasmoids','Precious Metals','Proteins','Reactive Metals','Silicon','Toxic Metals','Water'");
$pass .= gridminerals("Planetary T2","'Biocells','Construction Blocks','Consumer Electronics','Coolant','Enriched Uranium','Fertilizer','Genetically Enhanced Livestock','Livestock','Mechanical Parts','Microfiber Shielding','Miniature Electronics','Nanites','Oxides','Polyaramids','Polytextiles','Rocket Fuel','Silicate Glass','Superconductors','Supertensile Plastics','Synthetic Oil','Test Cultures','Transmitter','Viral Agent','Water-Cooled CPU'");
$pass .= gridminerals("Planetary T3","'Biotech Research Reports','Camera Drones','Condensates','Cryoprotectant Solution','Data Chips','Gel-Matrix Biopaste','Guidance Systems','Hazmat Detection Systems','Hermetic Membranes','High-Tech Transmitters','Industrial Explosives','Neocoms','Nuclear Reactors','Planetary Vehicles','Robotics','Smartfab Units','Supercomputers','Synthetic Synapses','Transcranial Microcontrollers','Ukomi Superconductors','Vaccines'");
$pass .= gridminerals("Planetary T4","'Broadcast Node','Integrity Response Drones','Nano-Factory','Organic Mortar Applicators','Recursive Computing Module','Self-Harmonizing Power Core','Sterile Conduits','Wetware Mainframe'");
$pass .= gridminerals("Orca Porpoise Kernite","'Orca','Porpoise','Kernite','Compressed Kernite','Occator'");
$pass .= gridminerals("Ore","'Veldspar','Dense Veldspar','Concentrated Veldspar','Compressed Veldspar','Compressed Dense Veldspar','Compressed Concentrated Veldspar',
'Compressed Scordite','Compressed Massive Scordite','Compressed Condensed Scordite',
'Compressed Rich Plagioclase','Compressed Plagioclase','Compressed Azure Plagioclase','Plagioclase','Azure Plagioclase','Compressed Bezdnacine'");
return $pass."</div>";
} // inventory
function pseudonotes(){
global $link;
$csh=0;
$pass ="<table border=2 style='background-color:99ffcc'><thead><tr><th>#</th><th>pseudo</th><th>Notes</th></thead>";
$rs=mysqli_query($link,"select  * from PANELS where notes<>'' order by notes desc"); 
while ($row = mysqli_fetch_object($rs)) {
  $pseudo=$row->pseudo;
  $notes=$row->notes;
  $csh ++;
  $pass .= "<tr><th>$csh</th><td>$pseudo</td><td>$notes</td></tr>";
}
$pass .="</table>";
$pass .= OrcaPorpoise();
return $pass;
} // pseudonotes
function more8(){
global $link;
$csh=0;
$pass ="<h3>more 8</h3><table border=2 style='background-color:#ddeeff;'><thead><tr><th>#</th><th>Name</th><th>SP</th><th>UN</th><th>Note</th></thead>";
$rs=mysqli_query($link,"SELECT toon_name,commentcard,round((skillpoints/1000000),2) as SP,round((unalloc/1000000),2) as UN FROM `PILOTS` where
 skillpoints>8000000 and (toon_name not like '%Catalog%') order by skillpoints desc;");
while ($row = mysqli_fetch_object($rs)) {
  $name=$row->toon_name;
  $sp=$row->SP;
  $un=$row->UN;
  $csh ++;
  $note="$row->commentcard";
  if ($name=="Nokia Catalog") $note="n/a";
  if ($name=="Experiment Catalog") $note="n/a";
  $pass .= "<tr><th>$csh</th><td>$name</td><td align='right'>$sp</td><td align='right'>$un</td><td>$note</td></tr>";
}
$pass .="</table>";
return $pass;
} // more8

function alfaomega($WHAT){
global $link;
$shipnames="";
$csh=0;
$sql="select ShipName from EVE_SHIPS where Tech='$WHAT'";
$rs=mysqli_query($link,$sql); 
while ($row = mysqli_fetch_object($rs)) {
  $shipnames .="'$row->ShipName',";
}
$shipnames="($shipnames 'DontMatter')";
$sql="select description,toon_number,location_id,type_description,quantity,forge_value,date_insert from EVE_ASSETS where 
type_description in $shipnames order by type_description,toon_number";
//die($sql);
$rs=mysqli_query($link,$sql);
$csh=0; 
$csh2=0; 
$pass ="<table border=2>
<th>#</th><th>Pilot</th><th>Group</th><th>Location</th><th>ship</th><th>Qty</th><th>forge_value</th><th>Pocket</th></tr>";
while ($row = mysqli_fetch_object($rs)) {   
   list($name,$mail)=avalues319("select toon_name,supergroup from PILOTS where toon_number='$row->toon_number'");
   $ignore="NO";
   if ($mail=='') $ignore="YES";
   $color="";
   IF ($row->quantity>1) $color="style='background-color:yellow'";
   if ($ignore=='NO'){
     $csh++;
     $pass .="<tr $color><th>$csh</th>";
     $pass .="<td>$name</td>";
     $pass .="<td>$mail</td>";
	 
	 $location=$row->description;
	 if ($row->location_id==1021011818054) $location="<font color='red'><b>Perimeter Verite citadel</b></font>";
	 $pass .="<td>$location</td>";
     $pass .="<td>$row->type_description</td>"; // ship name
     
//     $pass .="<td>$row->TypeName</td>";
     $pass .="<td>$row->quantity</td>";
	 $csh2 +=$row->quantity;
     //echo "<h3>$row->toon_number</h3>";
     $pass .="<td>".cfdinumbers($row->forge_value/1000000)."</td>";
     //$pass .="<td>$row->date_insert</td>";
     $pass .="<td>".pilotclass($name)."</td>";     
     $pass .="</tr>";
  }
}  
$pass .="</table><h3>total ships: $csh2</h3><hr>";
return $pass;
} // checkt3
function gridships(){
global $link;
/// pilots
$muleta="";
$sql2="select toon_number from PILOTS where toon_name not in ('Nokia Catalog','All Others Catalog','Experiment Catalog') 
$muleta order by DOB"; 
$rs=mysqli_query($link,$sql2);
$aDataX = array();
while ($row = mysqli_fetch_object($rs)) {
  $aDataX[] = $row->toon_number;
}  
$pilots=implode(",",$aDataX);
/*
echo "<pre>";
print_r($pilots);
echo "</pre>";
*/
//die();
$numpilot=sizeof($aDataX);
$sql="select ShipName from EVE_SHIPS order by Tech,ShipName";
$rs=mysqli_query($link,$sql);
$theid=randomstring();  // identify table for navigation script
$pass="<h3>Ships in Xnumpilot pilots</h3><table id='$theid' border=2 class='table sortable'>
<thead class='thead-dark'><tr><th>Ship</th><th>Ship Type</th><th>Alpha/Omega</th><th>Total</th>";
$csh2=0;    
    while ($csh2<$numpilot){
      list($pilotname)=avalues319("select toon_name from PILOTS where toon_number=$aDataX[$csh2]");      
	  // Prefix with 'p_' so class names never start with a digit (breaks for characters with numeric-only names)
      $sinespacio='p_'.str_replace(" ","_","$pilotname");
      $_SESSION[$sinespacio]=0; // depura puede no ser de sesion
      $pass .="<th class='$sinespacio'>$pilotname</th>";
      $csh2 ++;
    }
$pass .="</tr></thead><tbody>";    
$totalomega=0;
while ($row = mysqli_fetch_object($rs)) {  
  $sqx="select sum(quantity) from EVE_ASSETS  where type_description ='$row->ShipName' and  ID>150421 and toon_number in ($pilots)";
  //die($sqx);
  list($cuantas)=avalues319($sqx);   
   if ($cuantas=="") $cuantas=0;
  if ($cuantas>0){
    list($theclass,$alfaomega)=avalues319("select typename,tech from EVE_SHIPS where Shipname='$row->ShipName'");
    //$alfaomega=alfaomega($row->ShipName,$theclass);
    if ($alfaomega=="Omega") $totalomega += $cuantas;
    $pass .= "<tr><th>$row->ShipName</th><th>$theclass</th><th>$alfaomega</th><th>$cuantas</th>";
    $csh2=0;    
    while ($csh2<$numpilot){
      list($here)=avalues319("select sum(quantity) from EVE_ASSETS  where type_description ='$row->ShipName' and toon_number='$aDataX[$csh2]'");
      if ($here=="") $here=0;      
      list($pilotname)=avalues319("select toon_name from PILOTS where toon_number='$aDataX[$csh2]'");
	  // Prefix with 'p_' so class names never start with a digit (breaks for characters with numeric-only names)
      $sinespacio='p_'.str_replace(" ","_",$pilotname);
      $pass .="<td class='$sinespacio'>$here</td>";
	  // no auitar comillas de session
      $_SESSION["$sinespacio"] += $here; // depura puede no ser de sesion
      $csh2 ++;
    }
    $pass .="</tr>\n";
    //if ($_SESSION[$sinespacio]==0) $numpilot --;
  }    
}
$pass .="</tbody></table>Omega Ships : $totalomega";
// nos preparamos para ocultar
$minus=0;
$csh2=0;    
    while ($csh2<$numpilot){
      
      list($pilotname)=avalues319("select toon_name from PILOTS where toon_number='$aDataX[$csh2]'");      
	  // Prefix with 'p_' so class names never start with a digit (breaks for characters with numeric-only names)
      $sinespacio='p_'.str_replace(" ","_",$pilotname);
      if ($_SESSION["$sinespacio"]==0){
        //die("llego 2 class='$sinespacio' style='display:none;'");
        $minus ++;
        $pass=str_replace(" class='$sinespacio'"," style='display:none;'",$pass);
      } // depura puede no ser de sesion      
      $csh2 ++;
    }
$pass=str_replace("Xnumpilot",$numpilot-$minus,$pass);
// fin de ocultar
//echo "<pre>";
//var_dump($_SESSION);
//echo "</pre>";
$pass=str_replace(">0<","><",$pass); 
return $pass."<br />&nbsp;";
} // gridships

function whohas($arreglo){
global $link;
$sql2="select distinct(toon_number) from EVE_ASSETS where type_description in ($arreglo) order by toon_number"; 
$rs=mysqli_query($link,$sql2);
$aDataX = array();
while ($row = mysqli_fetch_object($rs)) {
    $aDataX[] = $row->toon_number;
    }
$pilots=implode(",",$aDataX);
return $pilots;
} // whohas
function gridminerals($title,$what){
global $link;
$whowhat=whohas($what);
$pilots=$whowhat; // default
if ($whowhat<>""){
	$sql2="select toon_number from PILOTS where toon_number  in (".$whowhat.") order by PILOTS.DOB";
//	echo "<li>$sql2 $title";
	$rs=mysqli_query($link,$sql2);
	$aDataX = array();
	while ($row = mysqli_fetch_object($rs)) {
	  $aDataX[] = $row->toon_number;
	}  
	$pilots=implode(",",$aDataX);
};
//$pilots=$whowhat;
if ($pilots=="") return "<h3>No data for $title</h3>";
/*
echo "<pre>";
print_r($pilots);
echo "</pre>";
*/
//die();
$numpilot=sizeof($aDataX);
$sql="select distinct(type_Description) as ShipName from EVE_ASSETS where type_Description in ($what)";
$rs=mysqli_query($link,$sql);
$theid=randomstring();  // identify table for navigation script
$pass="<h3>$title in $numpilot pilots</h3><table id='$theid' border=2 class='sortable'><thead class='table-dark'><tr><th>Ship</th><th>Total</th>";
$csh2=0;    
    while ($csh2<$numpilot){
      list($pilotname)=avalues319("select toon_name from PILOTS where toon_number='$aDataX[$csh2]'");
      $pass .="<th>$pilotname</th>";
      $csh2 ++;
    }
$pass .="</tr></thead><tbody>";    

while ($row = mysqli_fetch_object($rs)) {  
  $sqx="select sum(quantity) from EVE_ASSETS  where type_description ='$row->ShipName' and  ID>150421 and toon_number in ($pilots)";
  //die($sqx);
  list($cuantas)=avalues319($sqx);   
   if ($cuantas=="") $cuantas=0;
  if ($cuantas>0){
    $pass .= "<tr><th>$row->ShipName</th><th>$cuantas</th>";
    $csh2=0;    
    while ($csh2<$numpilot){
      list($here)=avalues319("select sum(quantity) from EVE_ASSETS  where type_description ='$row->ShipName' and toon_number='$aDataX[$csh2]'");
      $pass .="<td>$here</td>";
      $csh2 ++;
    }
    $pass .="</tr>";
  }    
}
$pass .="</tbody></table>";
return $pass;
} // gridminerals

function insidebox(){
//echo "<li>Your account was verified, you are logged now";
$sess=$_SESSION['character_id'];
if ($sess==3 or $sess==''){ 
     session_destroy();     
//     session_start();
     //Echo ("Error in anonymous auth ($sess)");     
     die();
}

echo css(). boot();
$m=$_GET['module'] ?? "";
if ($m=='newdate'){
  $fec=$_POST['fec'];
  $acco=$_POST['acco'];
  $sql="update PANELS set manualExpiration='$fec' where pseudo='$acco';";
  //echo "<h3>$sql</h3>";
  list($dummy)=avalues319($sql);
}
$sql="update PILOTS set finishqueue='0000-00-00' where queue='[]'"; // sometines finisqueues is not correct for esi response
$rs = doaction($sql,"error checking roster queues");

//echo updatecharacters();
//echo showpanels(); roster antioguo
//echo roster(); // muestra estadisticas del cuadrov verde abajo
/*echo "<br /><br /><br /><br /><br /><br /><a href='cardsu.php' class='btn btn-primary btn-lg' role='button' target='_blank'>
    Abrir cardsu.php
</a>";
*/
echo generarcards(); // dee gems.php
echo footboot();
die();
} // insidebox

function CFDINumbers($val, $p = 2) {
// requiere bcmath
// https://stackoverflow.com/questions/9944001/delete-digits-after-two-decimal-points-without-rounding-the-value

//echo "<li>$val";
if ($val==0) return "0.00";
$res=bcdiv($val, 1, 2);
//echo "<li>$val $res";
return $res;
}

function is400(){
$who=valpilot("CHECK");
list($name,$data)=avalues319("select toon_name,skills from PILOTS where toon_number='$who'");
if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (3)");
//$name=addslashes($name);
//if ($pilot<>$_SESSION['character_id']) die("The pilot $who ".PilotfromInternet($who)." is in the database but belong to other user (3)");
return Brave();  
}
// is 400

function planets(){
    $who = (int)valpilot("CHECK");
    list($name,$pilot,$data)=avalues319("select toon_name,supergroup,planets from PILOTS where toon_number='$who'");
    if ($name=="") die("The pilot $who ".PilotfromInternet($who)." is not in the database (12) or belong to other user");
    // die("The pilot $who $name is in the database but belong to other user (12)");

    $nameH = htmlspecialchars($name);
    $pass = css().boot()."<h3>Installed Planetary Colonies of $nameH</h3>";
    if ($data=='[]' or $data=='') return "<h3>no data planets for $nameH</h3>";

    $planets = json_decode(stripslashes($data), true);
    if (!is_array($planets) or !$planets) {
        return "<h3>no valid planet data for $nameH</h3>";
    }

    $pass .= "<table class='hovertable'><tr><th>#</th><th>Last Update</th><th>Pins</th><th>Planet</th><th>Planet type</th><th>Solar System</th><th>Ugrade Level</th></tr>";
    $csh = 0;
    foreach ($planets as $item) {
        $csh++;
        $planetId = (int)($item['planet_id'] ?? 0);
        $systemId = (int)($item['solar_system_id'] ?? 0);

        list($planetName) = avalues319("select itemName from invUniqueNames where itemID='$planetId'");
        if ($planetName == '') $planetName = 'n/a';

        list($system) = avalues319("select itemName from invUniqueNames where itemID='$systemId'");
        if ($system == '') {
            $system = 'n/a';
        } else {
            $sysH = htmlspecialchars($system);
            $system = "<a href='http://evemaps.dotlan.net/system/" . rawurlencode($system) . "' target='_blank'>$sysH</a>";
        }

        $pass .= "<tr><th>$csh</th>"
               . "<td>" . htmlspecialchars($item['last_update'] ?? '') . "</td>"
               . "<td>" . htmlspecialchars((string)($item['num_pins'] ?? '')) . "</td>"
               . "<td>" . htmlspecialchars($planetName) . "</td>"
               . "<td>" . htmlspecialchars($item['planet_type'] ?? '') . "</td>"
               . "<td>$system</td>"
               . "<td>" . htmlspecialchars((string)($item['upgrade_level'] ?? '')) . "</td>"
               . "</tr>";
    }
    $pass .= "</table>";
    return $pass;
} // planets

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
function sql2table($sql,$title,$color) {
global $link;
//databaseconnect();
    $result = mysqli_query($link,$sql);
    sqlerror("Avalues 394<hr>$sql");
    $nrow = 1;    
    $head="<table border=1 cellpadding=5 cellspacing=0 style='background-color:$color; caption-side:top;'>";
    if ($color=="#c3dde0") $head="<table class='hovertable' style='caption-side: top;'>";
    $head .="<caption style='caption-side: top;'>$title</caption>";
    
    $pass ="$head<tr><th>Row</th>\n";
    $fields=mysqli_num_fields($result);
    for($i = 0; $i < $fields; $i++) {
      $finfo=mysqli_fetch_field_direct($result,$i);
      $name=$finfo->name;
      $pass .="<th>$name</th>\n";
    }  
    $pass.= "</tr>\n";
    while($row = mysqli_fetch_array($result)) {        
        $pass.="<tr><td>$nrow</td>";
        for($i = 0; $i < $fields; $i++) {
          $rig='';
          $finfo=mysqli_fetch_field_direct($result,$i);
          $ft=$finfo->type;                    
          if($ft=='real') $rig=" align='right'";
          if($ft=='246') $rig=" align='right'";
          $realX=($row[$i]);
          if($ft=='246') $realX=number_format($row[$i],2);
          //if($ft=='246') $realX=number_format($row[$i]);
          
          
          $pass .= "<td $rig>$realX</td>\n";
        }
        $pass .="</tr>\n";
        $nrow++;
    }
    $pass .="</table>\n";
    if ($nrow==1) $pass="$head<tr><td>No data to show</td></tr></table>";
    if ($nrow==1) $pass=""; // nada
    //echo $pass;
    return $pass;
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

/*function getskillt($number){
list($one,$two)= avalues319("select primaryAttribute,secondaryAttribute from EVE_CATSKILLS  where typeID='$number'");
if ($one=='' or $two=='') return "n/a";
$one=Ucwords(strtolower($one));
$two=Ucwords(strtolower($two));
return "$one/$two";
} // gertskillt
*/
function typea($value){

list($pass)=avalues319("Select Combination from SkillAttributes where TypeId='$value'");
if ($pass=="") $pass="n/a";
//$pass=getskillt($value);

$color="";
if ($pass=="Perception/Willpower") $color=" style='background-color:cyan'";
if ($pass=="Willpower/Perception") $color=" style='background-color:yellow'";
if ($pass=="Intelligence/Perception") $color=" style='background-color:lime'";
if ($pass=="Intelligence/Memory") $color=" style='background-color:cccccc'";
if ($pass=="Memory/Intelligence") $color=" style='background-color:pink'";
if ($pass=="Charisma/Intelligence") $color=" style='background-color:cc99cc'";
if ($pass=="Charisma/Willpower") $color=" style='background-color:dcb59f'";
if ($pass=="Perception/Willpower") $pass ="Per/Wil";
if ($pass=="Willpower/Perception") $pass ="Wil/Per";
if ($pass=="Willpower/Intelligence") $pass ="Wil/Int";
if ($pass=="Perception/Memory") $pass ="Per/Mem";
if ($pass=="Memory/Perception") $pass ="Mem/Per";
if ($pass=="Memory/Charisma") $pass ="Mem/Cha";
if ($pass=="Charisma/Willpower") $pass ="Cha/Wil";
if ($pass=="Memory/Intelligence") $pass ="Mem/Int";
if ($pass=="Intelligence/Memory") $pass ="Int/Mem";
if ($pass=="Intelligence/Perception") $pass ="Int/Per";
if ($pass=="Charisma/Intelligence") $pass ="Cha/Int";
if ($pass=="Willpower/Charisma") $pass ="Wil/Cha";
if ($pass=="Charisma/Memory") $pass ="Cha/Mem";

$pass= "<td $color>$pass</td>";
return $pass;
} // typea
function checkalpha(){
global $link;
$number=$_GET['t'];
list($name) = avalues319("select toon_name from PILOTS where toon_number=$number");
 if ($name=='') die ("Can't detect name of Pilot $number");
//$name,$race){
$race="EXPANDED";
$omega="";
$p2=0;
$o6=0;
$csh=0;
$csh2=0;
$pass=0;
$n2=0;
$perfect="";
$learn="";
$train="";
$sql="select * from ALPHA_CLONES where $race>0";

//die ("num:$number");
$rs= mysqli_query($link,$sql);
sqlerror("error:$sql:");
$pass = css().topboot()."<h1>Ckecking alpha state of $number - $name - $race </h1><ol>";
while ($fila = mysqli_fetch_object($rs)) {
    $type= $fila->numberskill;
    $desc= $fila->SKILL;    
    $cad = "<li><a href='./dossier/skill_detail.php?module=dt2&what=$type' target='_blank'>$type</a> $desc";
    if ($type==0) {
      $sql2="select typeID from EVE_CHARSKILLS where description='$desc'";
      
      list($newn)=avalues319 ($sql2);
      if ($newn<>''){
        // $cad .= " - $newn";
        $sql="update ALPHA_CLONES set numberskill='$newn' where SKILL='$desc'";
        doaction($sql,"Error updating alpha clones");
      }
    }
    if ($type==0 and $newn=='') $cad .= "<li><font color='red'>$desc without number</font>";
    list($max)=avalues319("select $race from ALPHA_CLONES where numberskill='$type'");
    list($yours,$skillpoints)=avalues319("select rank,skillpoints from EVE_CHARSKILLS where toon=$number and typeID=$type");    
    $cad .= " - max=$max you=$yours s=$skillpoints";
    
    if ($yours=='') {
      // need learn
      $csh ++;      
      $cad="<tr><th>$csh</th>".typea($type)."</td><td>$cad</td></tr>";
      $learn .=$cad;
      $cad='';
    }  
    if ($yours>$max) {
      //$cad .= "<b><font color='red'>OMEGA</font></b>";
      //$cad .=$ok;
      
      $omega .= $cad;
      $o6 += $skillpoints;
      $cad='';
    }
    if ($yours==$max) {
      // perfect alpha
      //$cad .= "<b><font color='lime'>PERFECT</font></b>";
      $p2 += $skillpoints;
      $perfect .=$cad;
      $cad='';
    }
    if ($yours<$max and $cad<>'') {
      $ok="";
      $csh2 ++;
      $train .="<tr><th>$csh2</th>".typea($type)."</td><td>$cad</td></tr>";
      $n2 += $skillpoints;
      $cad='';
    }
    
  }
  //mysqli_free_result($rs);
  $perfect="$perfect<hr>skill points:$p2";
  
  $pass .= "</ol>";
  $omegaonly=omegaonly($number,$race);
  $learn="<table class='hovertable'>$learn</table>";
  $omega="<ol>$omega</ol><hr>skill points:$o6";
  $train="<table class='hovertable'>$train</table><hr>skill points:$n2";
  $perfect="<ol>$perfect</ol>";
  $omegaonly="<ol>$omegaonly</ol>";
  $r=" valign='top'";
  $pass .= "<table class='hovertable'><tr><th>Must learn</th><th>Need train</th><th>Perfect Alpha</th><th>Useful as omega</th><th>omega only</th></tr>
  <tr><td $r>$learn</td><td $r>$train</td><td $r>$perfect</td><td $r>$omega</td><td $r>$omegaonly</td></tr>
  </table>";
return ($pass);  
} // checkalpha
function omegaonly($number,$race){
global $link;
$sql="select * from EVE_CHARSKILLS where toon='$number'";
$rs= mysqli_query($link,$sql);
$cad="";
$pass="";
$o3=0;
  while ($fila = mysqli_fetch_object($rs)) {
    $type= $fila->typeID;
    $desc= $fila->Description;
    $rank= $fila->rank;
    $skillpoints= $fila->skillpoints;
        
    list($inrace) =avalues319("select count(numberskill) from ALPHA_CLONES where numberskill='$type' and $race>0");
    if ($inrace=='') $inrace=0;
    if ($inrace==0) {
      $showzeros="NO"; // config extra  
      if ($rank>0 or $skillpoints>0) { $cad .="<li><a href='./dossier/skill_detail.php?module=dt2&what=$type' target='_blank'>$type</a> $desc r:$rank $skillpoints"; $o3 +=$skillpoints; }
      if ($rank==0 and $skillpoints==0 and $showzeros<>'NO') { $cad .="<li>$type $desc r:$rank $skillpoints"; $o3 +=$skillpoints; }
      }
  }
  //mysqli_free_result($rs);
  $pass .="$cad<hr>skill points:$o3";
  
return $pass;
} // omega only
function describeplan($nameplan){
global $link;
$paso=0;
$pass="<tr><th colspan=7>$nameplan</th></tr>";
$sql="select nameskill from skillplans where nameplan='$nameplan' order by id";
$rs = mysqli_query($link,$sql);
sqlerror("error dscribing plan<hr>$sql");
while ($row = mysqli_fetch_object($rs)) {
  $paso ++;
  $pass .= numtreat($row->nameskill,$paso);
 
}
if ($paso==0) return "";
return $pass;
} // descibeplan

FUNCTION BRAVE(){
$name=nametoon($_GET['t']);
 return css().topboot()."
 <h3>400 days is a plan for training for max fun Created by Brave Newbies, and now i calculate for pilot $name</h3>
 <table class='hovertable'> ".
describeplan("Brave 2 weeks starter").
describeplan("Brave 400 step by step").
describeplan("400 grouped (not step)").
describeplan("Another Brave Plan 25 nov 2018").
//describeplan("Confessor Mastery IV").
describeplan("Porpoise Missions/Booster").
describeplan("Capswarm Carriers (plus racial IV)").
describeplan("Capswarm Fax (plus racial V)").
describeplan("Capswarm Dread (plus racial IV)").
describeplan("Capswarm SuperCarrier (plus racial IV)").
describeplan("Capswarm Titan (plus racial IV)").
describeplan("Basic Rorqual No Boosting - New (testing)").
describeplan("T2 Rorqual Non Empire - New (testing)").
describeplan("Plan Trial 132m").
describeplan("Missions 45m").
describeplan("Skirmish Frigate Extras").
Describeplan("Marauder/Absolution Fleet Booster").
Describeplan("16.5m Adaptable Solo PvP with Ishtar").
Describeplan("Obelisk Perfect Pilot").
Describeplan("High Sec Ganker 5m").
Describeplan("High Sec Lone Miner").
Describeplan("Stealth Bomber (Plus Racial Frigate V)").
Describeplan("Draft Titan Avatar (DIY)").
Describeplan("Ferox Sniper Mission II (6.5m)").
describeplan("Novice SPC").
describeplan("Brave Core Alpha").
describeplan("Fun Alpha Brave").
describeplan("Fun Alpha Brave Optimal").
describeplan("Brave Alpha Army Feb 2024").
"</table>"
 ;
 } // brave
 function numtreat($treat,$number =""){
   $pass=$treat."@";
   //die("n $number");   
   $pass=str_replace("1","I",$pass); // remove tabs
   $pass=str_replace("2","II",$pass); // remove tabs
   $pass=str_replace("3","III",$pass); // remove tabs
   $pass=str_replace("4","IV",$pass); // remove tabs
   $pass=str_replace("5","V",$pass); // remove tabs
   $pass=str_replace("1 ","I",$pass); // remove tabs
   $pass=str_replace("2 ","II",$pass); // remove tabs
   $pass=str_replace("3 ","III",$pass); // remove tabs
   $pass=str_replace("4 ","IV",$pass); // remove tabs
   $pass=str_replace("5 ","V",$pass); // remove tabs   

   $pass=str_replace(chr(8),"",$pass); // remove tabs
   $pass=str_replace(chr(9),"",$pass); // remove tabs   
   $pass=str_replace(chr(10),"",$pass); // remove tabs
   $pass=str_replace(chr(13),"",$pass); // remove tabs
   $pass=str_replace(chr(32).chr(32)," ",$pass); // remove tabs
   $pass=str_replace("  "," ",$pass); // remove tabs
   $pass=str_replace("  "," ",$pass); // remove tabs
   if (str_replace(" V@","",$pass)<>$pass  ) { $need=5; $pass=str_replace(" V@","",$pass); }
   if (str_replace(" IV@","",$pass)<>$pass ) { $need=4; $pass=str_replace(" IV@","",$pass); }
   if (str_replace(" III@","",$pass)<>$pass) { $need=3; $pass=str_replace(" III@","",$pass); }
   if (str_replace(" II@","",$pass)<>$pass ) { $need=2; $pass=str_replace(" II@","",$pass);}
   if (str_replace(" I@","",$pass)<>$pass  ) { $need=1; $pass=str_replace(" I@","",$pass);}
   
   //list($description)=avalues319("select typeName from invTypes where typeID='$item->type_id'");
   list($thenum)=avalues319("select typeID from invTypes where typeName='$pass'");
   if ($thenum<>"") {     
     $thechar= $_GET['t'];
     list($barge)=avalues319("select rank from EVE_CHARSKILLS where toon='$thechar' and typeID=17940");     
     
     list($maxalpha)=avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$thenum'");
      
     list($level,$mine)=avalues319("select rank,skillpoints from EVE_CHARSKILLS where toon='$thechar' and typeID=$thenum");
     if ($level=="") $level="<span style='background-color:ff0000'>LEARN</span>";
     if ($level>=$need) return "";//                                         $treat $level / $need";
     $new=typea($thenum);     
     //$new="<table><tr>$new</tr></table>";
     $cad="";
     if ($maxalpha=="" or ($need> $maxalpha) ) $cad = "need Omega";
     list($when) = avalues319("select min(skillpoints) from EVE_CHARSKILLS where rank='$need' and typeID=$thenum ");
     if ($mine=='') $mine=0;
     $mine2=$when-$mine;
     $when="<td>$when</td>";
     
     $mine3="<td>$mine2</td>";
     return "<tr><th>$number</th><td>$treat</td>$new<td> Now:$level/$need</td><td>$cad</td>$when"."$mine3</tr>";
   }     
   return "<tr><td>$treat</td><td>n/a</td></tr>";
 }
  

function nametoon($who){
if ($who=='') return "n/a";
  $sql="select toon_name from PILOTS where toon_number='$who'";
  
  list($toon)=avalues319($sql);
  //if ($toon=='') $toon='n/a';
return $toon;  
} // nametoon

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

function erase2($who){
//echo "<script>alert('K $who')</script>";
list($name)=avalues319("select toon_name from PILOTS where toon_number='$who'");
$name=addslashes($name);
  list($pilot)=avalues319("select toon_number from PILOTS where toon_name='$name'");

$aaa="update DIPLOMATIC SET pilot_name='All Others Catalog',pilot_positive=999 where pilot_name='$name'";
  doaction($aaa,"Error AAA");
  // temporal: the characters erased sent all the skills to all others catalog, is temporal for depuration
  $bbb="update EVE_CHARSKILLS set toon='2122782650' where toon='$pilot'"; // All Others Catalog
  doaction($bbb,"Error BBB");

// debug next line
$sql="delete from PILOTS where toon_number='$pilot'"; doaction($sql,"error Biom - 1");
$sql="update PANELS set pilot_1='',name_1='' where pilot_1='$pilot'"; doaction($sql,"error Biom - 2");
$sql="update PANELS set pilot_2='',name_2='' where pilot_2='$pilot'"; doaction($sql,"error Biom - 3");
$sql="update PANELS set pilot_3='',name_3='' where pilot_3='$pilot'"; doaction($sql,"error Biom - 4");
$sql="delete from EVE_CHARSKILLS where toon='$pilot'";
doaction($sql,"error Biom - 5");
$sql="delete from DREAMTEAM where toon='$pilot'";
doaction($sql,"error Biom - 6");
if ($name<>''){
	// debug next line
  $sql="delete from DIPLOMATIC where pilot_name='$name'";
  doaction($sql,"error Biom - 7");
}
//erase2($name);
}  // erasemypilot

//------------
function dt2(){
//databaseconnect();
$num=abs($_GET['what']);
echo css(). topboot();
$sql="select EVE_CHARSKILLS.skillpoints,rank,PILOTS.toon_name from EVE_CHARSKILLS 
LEFT JOIN PILOTS
ON EVE_CHARSKILLS.toon=PILOTS.toon_number
where PILOTS.email_pilot='".$_SESSION['youremail']."' and typeID='$num' order by EVE_CHARSKILLS.skillpoints desc,PILOTS.toon_name";
list($desc)=avalues319("select typeName from invTypes where typeID='$num'"); 
echo sql2table($sql,"Pilots of your roster with skill $num $desc","ffffcc") ;
die();
} // dt2
function dp2(){
//databaseconnect();
$num=abs($_GET['what']);
echo css(). topboot();
list($corpName)=avalues319("select itemName from invUniqueNames where itemId='$num'");
$sql="select target,target_description,reputation,pilot_name,pilot_positive from DIPLOMATIC 
where owner_email='".$_SESSION['youremail']."' and target='$num' order by reputation desc,pilot_name";
echo sql2table($sql,"Pilots of your roster with diplomatic with $num $corpName","ffffcc") .footboot();
die();
} // dp2
  function specialcolorNPC($corp){  
      $der=" style='background-color:#99ccff';";
      $rower="";
    if ($corp=='1000035') $rower=$der;
    if ($corp=='1000057') $rower=$der;
    if ($corp=='1000086') $rower=$der;
    if ($corp=='1000049') $rower=$der;
    if ($corp=='1000120') $rower=$der;
    if ($corp=='1000130') $rower=$der;      
      // no in hogh sec
      $elk="orange";      
    if ($corp==1000124) $rower=" style='background-color:$elk'";
    if ($corp==1000127) $rower=" style='background-color:$elk'";
    if ($corp==1000128) $rower=" style='background-color:$elk'";
    if ($corp==1000129) $rower=" style='background-color:$elk'"; // epic arc only
    if ($corp==1000134) $rower=" style='background-color:$elk'";
    if ($corp==1000135) $rower=" style='background-color:$elk'";
    if ($corp==1000136) $rower=" style='background-color:$elk'";
    if ($corp==1000138) $rower=" style='background-color:$elk'";
    if ($corp==1000141) $rower=" style='background-color:$elk'";
    if ($corp==1000159) $rower=" style='background-color:$elk'";
    if ($corp==1000161) $rower=" style='background-color:$elk'";
    if ($corp==1000162) $rower=" style='background-color:$elk'";
    // warfare
    $elk="yellow";
    if ($corp==1000179) $rower=" style='background-color:$elk'";
    if ($corp==1000180) $rower=" style='background-color:$elk'";
    if ($corp==1000181) $rower=" style='background-color:$elk'";
    if ($corp==1000182) $rower=" style='background-color:$elk'";
    return $rower;
    }       // specialcor

function settings(){
global $link;
$pass= css().topboot();
//databaseconnect();
$me=$_SESSION['youremail'];
$what=$_GET['what'] ?? "";
if ($what=='delT') {
   //$pass topboot().barrido().footboot(); die(); }
   $pass .=erasemypilot();
   $pass .=footboot();
   die($pass);
}   

if ($what==''){
  $sql="select * from PILOTS where email_pilot='$me'"; 
  $rs= mysqli_query($link,$sql);
  sqlerror("error checking biomassed");
  while ($fila = mysqli_fetch_object($rs)) {
    $toon= $fila->toon_number;
//    UpdateCorpAlliance($toon);
  } // WHILE
} // if    

$sql="select * from PILOTS where corpID='1000001' and email_pilot='$me'"; 
$rs= mysqli_query($link,$sql);
sqlerror("error checking biomassed 2");
  $biomc=0;
  $pass .="<table class='hovertable'><tr><th colspan=5>Biomassed chars</th>";
  while ($fila = mysqli_fetch_object($rs)) {
    $toon_name= trim($fila->toon_name);
    $toon= $fila->toon_number;
    $biomc ++;    
	$portrait="https://images.evetech.net/characters/$toon/portrait";  
    $photo="<img src='$portrait' width='128' height='128' alt='namer'>";
    //$linkdel="<a href='?module=settings&t=$toon&what=delT'>click for delete</a>";
    $linkdel="";
    $pass .= "<tr><td>$photo</td><td>$toon</td><th>$toon_name</th><td> is Biomassed !<br />$linkdel</td></tr>";
  } // while
  $pass .="</table>";
  if ($biomc==0) $pass="";  
  $pass .="<form method='POST' action='?module=settings&what=delT'>
  Select a char to delete of your account<br /> <select name='pilot' REQUIRED>
  <option>";
  $sql="select * from PILOTS where email_pilot='$me' order by toon_name"; 
  $rs= mysqli_query($link,$sql);
  sqlerror("error checking biomassed 3");
  $csh=0;
  while ($fila = mysqli_fetch_object($rs)) {
    $dis="";
    $csh ++;
    $toon_name= trim($fila->toon_name);
    $toon= $fila->toon_number;
    $bio="";
    if ($fila->corpID=='1000001') $bio=" BIOMASSED";
    if ($toon_name=="Experiment Catalog") $dis=" DISABLED "; // internal use of the programmer and dont want erase my test data
    if ($toon_name=="Nokia Catalog")      $dis=" DISABLED "; // nokia catalog internal use of the programmer and dont want erase my test data    
    if ($toon_name=="All Others Catalog") $dis=" DISABLED "; // internal use of the programmer and dont want erase my test data    
    $pass .="<option value='$toon' $dis>$toon_name"."$bio  ($csh)</option>\n";
  }  
  $pass .="</select><br />
  <input type='submit' value='revoke token'></form>
  ";
  return $pass;
  //die();
} // settinngs
function erasemypilot(){
$pilot=addslashes($_GET['t'] ?? "");
if ($_POST['pilot']<>'') $pilot=$_POST['pilot'];
$me=$_SESSION['youremail'];
list($name) = avalues319("select toon_name from PILOTS where toon_number='$pilot'");
$name=addslashes($name);
erase2($name);
$sql="delete from PILOTS where email_pilot='$me' and toon_number='$pilot'";
doaction($sql,"error Biom - 1");
$sql="update PANELS set pilot_1='',name_1='' where pilot_1='$pilot'"; doaction($sql,"error Biom - 2");
$sql="update PANELS set pilot_2='',name_2='' where pilot_2='$pilot'"; doaction($sql,"error Biom - 3");
$sql="update PANELS set pilot_3='',name_3='' where pilot_3='$pilot'"; doaction($sql,"error Biom - 4");
$sql="delete from EVE_CHARSKILLS where toon='$pilot'";
doaction($sql,"error Biom - 5");
$sql="delete from DREAMTEAM where toon='$pilot'";
doaction($sql,"error Biom - 6");
if ($name<>''){
  $sql="delete from DIPLOMATIC where pilot_name='$name' and owner_email='$me'";
  doaction($sql,"error Biom - 7");
}
return "<br/>Pilot $name erased normally";  
}  // erasemypilot

function steptwobit(){
$steptwo=$_POST['steptwo'];
$nombre_fichero = "tmp/knife_$steptwo.html";

if (file_exists($nombre_fichero)) {
    echo file_get_contents($nombre_fichero);
} else {
    echo "The token $steptwo not point to a valid row in the database";
}
die();
} // steptwobit
function Q($skill,$t){
list($type2,$rank)=avalues319("select typeID,rank from EVE_CHARSKILLS where toon='$t' and typeID=$skill");
if ($type2<>$skill) return "<td></td>";
if ($rank=='') $extra=''; 
if ($rank=='0') $extra=" style=' background-color:red;'";
if ($rank=='1') $extra=" style=' background-color:orange;'";
if ($rank=='2') $extra=" style=' background-color:orange;'";
if ($rank=='3') $extra=" style=' background-color:yellow;'";
if ($rank=='4') $extra=" style=' background-color:green;'";
if ($rank=='5') $extra=" style=' background-color:lime;'";
return "<td $extra>$rank</td>";
} // return $q;

function get_prices_item_fuzzwork($item2){
$description2=str_replace(chr(92),"",Description($item2));

if (str_replace('Blueprint','',$description2)<>$description2) return -3; // blue prints -3
if (str_replace('SKIN','',$description2)<>$description2) return -4; // Skin -4
if (str_replace("Women's",'',$description2)<>$description2) return -5; // wardrobe -5
if (str_replace("Men's",'',$description2)<>$description2) return -5; // wardrobe -5
if (str_replace('NEO YC','',$description2)<>$description2) return -6; // prizes

if ($item2==3958 )return -5; // wardrobe -5 combat googles
list($price)=avalues319("select jita_value from jita_value where type_id='$item2'");
if ($price<>'') return $price;

/*
https://market.fuzzwork.co.uk/api/
https://market.fuzzwork.co.uk/aggregates/?region=10000002&types=34,35,36,37,38,39,40

https://market.fuzzwork.co.uk/aggregates/?station=60003760&types=34,35,36,37,38,39,40

    Jita 4-4 CNAP - 60003760
    Amarr VIII - 60008494
    Dodixie - 60011866
    Rens - 60004588
    Hek - 60005686
*/     
$turl2="https://market.fuzzwork.co.uk/aggregates/?region=10000002&types=$item2";
//die($turl2);
//echo "<li><a href='$turl2' target='_blank'>Calling to fuzzworks</a>";
$respuesta=curl2file($turl2,"fuzzprices.json"); // read data of the site and save file
//--------------------
  // Gets JSON file
  $json = $respuesta;//file_get_contents("fuzzprices.json"); // read file , is json type
  //die (htmlentities($json));
  $r=json_decode($json,true); // convert json to array
  //unlink("fuzzprices.json");
  /*  echo "<hr><pre>";
    print_r($r);
    echo "</pre><hr>";
    */     
    
    $numbers=explode(",",$item2); // split item numbers to array
    foreach ($numbers as $item){ // walk array of number item, for prices
    
    //$item=20; // exmple
    // directly read in array of that item,m this is the magic
       $buyavg= $r[$item]['buy']['percentile']; 
       $sellavg= $r[$item]['sell']['percentile'];  
       //$sql="update EVE_PRICES set BUY=$buyavg,SELL=$sellavg where ITEMID='$item'";
      //echo "<li>$sql";
if ($buyavg=="" and $sellavg==''){
    //echo "Nobody selling item $item: ".description($item);
    $buyavg=-2;
    $sellavg=-2;
  }
  $sql="insert into jita_value (type_id,jita_value,date_insert) values($item2,$sellavg,now())";
  doaction($sql,"error 1231b:");  
  } // each
   //}    
     
    //--------------

//die (htmlentities($cad));
//echo ("<font color='green'> .... market prices fuzzworks saved !</font><br /><br />");
//die($turl2);
return $sellavg;
} //get_prices_item_fuzzwork($item){
function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}



function doaction($sql,$errormessage){
global $link;
mysqli_query($link,$sql);
sqlerror("$errormessage<hr>$sql");
}
function sqlerror($message){
global $link;
$error=mysqli_error($link);
if ($error=='') return; 
die ("$message<hr>$error");
}
function valpilot($def){
if ($def<>''){
  if (is_numeric($def)) return $def;
}
$forc="";
if (isset($_SESSION['forcetoon'])) {  
  $forc =$_SESSION['forcetoon'];
}  
$t=$forc;
$lete="";
if (isset($_GET['t'])) {
  $lete=$_GET['t'];
  if ($lete<>'') $t=$lete;
}
if ($forc=='') $t=$lete;  
$who=addslashes($t);
return $who;
} // valpilot
function step(){
return $_GET['step'] ?? 0;
}
function whoitem(){
global $link;
$item=$_POST['partial'] ?? "";
$item=trim(addslashes($item));
$pass=css()."<form method='POST' method='POST' action='?module=item&step=2';
<h4>Partial description of a item</h4>
<input name='partial' maxlength='50' size=50><br /><br /><input type='submit' value='Search'>
</form>";
$csh=0;
if ($item<>""){
    list($dummy)=avalues319("delete from EVE_ASSETS where type_description='#System'");
    $sql="select * from EVE_ASSETS where     
    (type_description like '%$item%') or eveunique='$item' order by date_insert desc,type_description	,location_id";
    //die($sql);    
    $pass .="<h3>$item</h3><table class='hovertable'>";
    $pass .="<tr><th>#</th>";
      //$pass .="<td>ID</td>";
      $pass .="<td>toon_number</td>";
      $pass .="<td>Name Pilot</td>";
      $pass .="<td>location_flag</td>";
      $pass .="<td>location_id</td>";
      $pass .="<td>description</td>";
      $pass .="<td>quantity</td>";
      
      $pass .="<td>type_id</td>";
      $pass .="<td>type_description</td>";
      $pass .="<td>eveunique</td>";
      //$pass .="<td>date_insert</td>";
      $pass .="<td>unit_price</td>";
      $pass .="<td>forge_value</td>";              
      $pass .="</tr>";
    $rs = mysqli_query($link,$sql);
    while ($obj = mysqli_fetch_object($rs)) {
    $csh ++;
      $pass .="<tr><th>$csh</th>";
      //$pass .="<td>$obj->ID</td>";
      $pass .="<td>$obj->toon_number</td>";
      $pass .="<td>".nametoon($obj->toon_number)."</td>";
      $pass .="<td>$obj->location_flag</td>";
      $pass .="<td>$obj->location_id</td>";
      $pass .="<td>$obj->description</td>";
      $pass .="<td>$obj->quantity</td>";
      $pass .="<td>$obj->type_id</td>";
      $pass .="<td>$obj->type_description</td>";
      $pass .="<td>$obj->eveunique</td>";
      //$pass .="<td>$obj->date_insert</td>";
      $pass .="<td>$obj->unit_price</td>";
      $pass .="<td align='right'>".number_format($obj->forge_value)."</td>";              
      $pass .="</tr>";
    }
    $pass .="</table>";
}
return $pass;
} // whoitem

function whatship(){
global $link;
$who=$_SESSION['youremail'];
$pass ="<h3>Checking Ships Usable by pilots</h3><table class='hovertable'>";
$sql="select toon_number as num,toon_name,race,skillpoints/1000000 as SP from PILOTS where email_pilot='$who'
and toon_name not in ('Nokia Catalog','Experiment Catalog','All Others Catalog')  order by SP Desc";
$csh=0;
$rs = mysqli_query($link,$sql);
    while ($obj = mysqli_fetch_object($rs)) {
      $dem="";
      $csh ++;
      $x=cfdinumbers($obj->SP);      
      $who=$obj->toon_name;
      
      list($freighter)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%reighter%' and rank>0");
      
      if ($freighter>0) $dem .="<td>freighter $freighter</td>";
      if ($freighter==0) $dem .="<td></td>";
      list($dst)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%Hauler' and rank=5");
      if ($dst>0) $dem .="<td>dst $dst</td>";
      if ($dst==0) $dem .="<td></td>";
      list($barge)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$obj->num' and Description ='Mining Barge' and rank>0");
      if ($barge>0) $dem .="<td>barge $barge</td>";
      if ($barge==0) $dem .="<td></td>";

      list($exhum)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$obj->num' and Description ='Exhumers'");
      if ($exhum<>"") $dem .="<td>Exhumers $exhum</td>";
      if ($exhum=="") $dem .="<td></td>";
      
      list($orca)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like 'Industrial Command Ships'");
      
      if ($orca>0) $dem .="<th>orca/porpoise</th>";
      if ($orca==0) $dem .="<th></th>";
      
      list($frigate)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%rigate%' and rank>0");
      
      if ($frigate>0) $dem .="<td>frigate</td>";
      if ($frigate==0) $dem .="<td></td>";
      
      list($destroyer)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%destroyer%' and rank>0");      
      if ($destroyer>0) $dem .="<td>destroyer</td>";
      if ($destroyer==0) $dem .="<td></td>";
      
      list($cruiser)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%cruiser%' and rank>0");      
      if ($cruiser>0) $dem .="<td>cruiser</td>";
      if ($cruiser==0) $dem .="<td></td>";

      list($battlec)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%attlecruiser%' and rank>0");      
      if ($battlec>0) $dem .="<td>battlec $battlec</td>";
      if ($battlec==0) $dem .="<td></td>";
      
      list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$obj->num' and Description like '%attleship%' and rank>0");      
      if ($battle>0) $dem .="<td>battle $battle</td>";
      if ($battle==0) $dem .="<td></td>";
      
      list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$obj->num' and Description ='Capital Ships'");      
      if ($capital>0) $dem .="<th>Capital Pilot</th>";
      if ($capital==0) $dem .="<th></th>";
      
      $pass .="<tr><th>$csh</th><th>$obj->toon_name</th>".family($obj->toon_name)."<td align='right'>$x</td>$dem</tr>";
    }
$pass .="</table>";    
return css().$pass;
} // whatship
function family($name){
$pass ="<td></td>";
if (PilotClass($name)=='YENN')  return "<td style='background-color:white'>YENN</td>";
if (PilotClass($name)=='NOKIA') return "<td style='background-color:red'>NOKIA</td>";
if (PilotClass($name)=='LUCKY') return "<td style='background-color:lime'>LUCKY</td>";
if (PilotClass($name)=='EXPER') return "<td style='background-color:cyan'>EXPER</td>";
if (PilotClass($name)=='CLEAN') return "<td style='background-color:#ffcccc'>CLEAN</td>";
if (PilotClass($name)=='SANGO') return "<td style='background-color:yellow'>SANGO</td>";
if (PilotClass($name)=='OTHER') return "<td style='background-color:#ffccff'>".PilotClass($name)."</td>";
return $pass;
} // family
function shiproles($char){
$pass="";
list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Advanced Broker Relations' and rank>3");
if ($capital>0) $pass .="<span class='badge badge-secondary'>Trader</span> ";
list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%hauler%' and rank>0");
if ($battle==5) $pass .="<span class='badge badge-primary'>DST</span> ";
list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%barge%' and rank>0");
if ($battle>0) $pass .="<span class='badge badge-primary'>Barge</span> ";
if ($battle==5) {
  list($exhu)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%xhumer%' and rank>0");
  if ($exhu>0) $pass .="<span class='badge badge-primary'>Exhumer</span> ";
}  
list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description like '%Freighter' and rank>0");
if ($capital>0) $pass .="<span class='badge badge-warning'>Freighter</span> ";
list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Industrial Command Ships' and rank>0");
if ($capital>0) $pass .="<span class='badge badge-warning'>ORCA</span> ";
if ($capital>0){
  list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Mining Director' and rank=5");
  if ($capital>0) $pass .="<span class='badge badge-success'>Orca Booster</span> ";
  list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Mining Drone Specialization' and rank>3");
  if ($capital>0) $pass .="<span class='badge badge-success'>Orca Miner</span> ";
}

list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Interplanetary Consolidation' and rank=5");
if ($capital>0) $pass .="<span class='badge badge-success'>PI</span> ";  
list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Interdictors' and rank>0");
if ($capital>0) $pass .="<span class='badge badge-primary'>Interdictor</span> ";
list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '% cruiser%' and rank>0");
if ($battle==5) $pass .="<span class='badge badge-primary'>HAC</span> ";
list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%Tactical Destroyer%' and rank>0");
if ($battle>0) $pass .="<span class='badge badge-primary'>T3D</span> ";
list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%Strategic Cruiser%' and rank>0");
if ($battle>0) $pass .="<span class='badge badge-primary'>T3C</span> ";


list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%attleship%' and rank>0");
if ($battle==5) $pass .="<span class='badge badge-primary'>Marauder</span> ";
if ($battle==5){
  list($battle)=avalues319("select max(rank) from EVE_CHARSKILLS where toon='$char' and Description like '%Black Ops%' and rank>0");
  if ($battle>0) $pass .="<span class='badge badge-danger'>Black Ops</span> ";
}
list($capital)=avalues319("select count(*) from EVE_CHARSKILLS where toon='$char' and Description ='Capital Ships'");
if ($capital>0) $pass .="<span class='badge badge-danger'>Capital</span> ";

// draugur
// purifier
// expedition frigates
// scanner

// covops
// refiner

if ($pass<>"") $pass ="<br />$pass<br />";
return $pass;
}
function pilotclass($name){
list($pass)=avalues319("select pocket6 from PILOTS where toon_name='$name'");
return $pass;
} // pilot class
function showmyships($number = 0){
$number=$_GET['number'] ?? 0;
$number=intval($number);
global $link;
$pass="";
$sql ="select * from EVE_SHIPS order by typename,race,tech";
$rs = mysqli_query($link,$sql);
sqlerror("error checking ships<hr>$sql");
$last=""; // ultimo tipo
//$name2=PilotfromInternet($number);
list($name2)=avalues319("select toon_name from PILOTS where toon_number='$number'");
if ($name2=="") $name2 ="Pilot $number";
$pass .="<div><h3>Pilot License: $number Name: $name2</h3>";
$total=0;  
while ($row = mysqli_fetch_object($rs)) {
  $howm=0;
  list($howm)=avalues319("select sum(quantity) from EVE_ASSETS where toon_number='$number' and type_description='$row->ShipName'");
  if ($howm>0) {
      $total += $howm;
      list($getnumber)=avalues319("select type_id from EVE_ASSETS where type_description='$row->ShipName'");
      if ($last<>trim($row->TypeName)){      
        
        $pass .="</div><h3>$row->TypeName</h3><div style='background-color:white;'>";
        $last=trim("$row->TypeName");
      }  
      $pass .="<span style='padding: 16px;'><span style='display:inline-block; width:64px;  padding: 8px;'><img src='https://images.evetech.net/types/$getnumber/icon?size=64' border=0 title='$row->Race $row->Tech $row->ShipName'><br /><center>$howm</center></span></span>"; 
  }  
}
$pass .="</div>Total Ships: $total";
list($dummy)=avalues319("UPDATE PILOTS set numships=$total where toon_number='$number'");
return $pass;
} // whowmyships
function limpiador(){
global $link;
$sql ="select distinct(toon_number) as PILOTO from EVE_ASSETS order by toon_number";
$rs = mysqli_query($link,$sql);
sqlerror("error cleaning ships<hr>$sql");
$pass="<table class='hovertable'>";
$csh =0;
while ($row = mysqli_fetch_object($rs)) {
  list($existe)=avalues319("select count(*) from PILOTS where toon_number='$row->PILOTO'");
  if ($existe==0){
      list($howmany)=avalues319("select count(*) from EVE_ASSETS where toon_number='$row->PILOTO'");
      if ($howmany<90500) {
        $csh ++;
        $pass .="<tr><th>$csh</th><td>$row->PILOTO</td><td>".pilotfrominternet($row->PILOTO)."<br /><b>$howmany</b></td><td><img src='https://images.evetech.net/characters/$row->PILOTO/portrait' style='width:256px;height:256px;'></td></tr>";
      }   
  }
}
$pass .="</table>";
return $pass;
} // limpiador

function footboot(){
$ip=get_client_ip();
$ip2=gethostbyaddr($ip);
$m=" $ip | $ip2 Php Ver:".phpversion();
   return "</div></div>
   </div></main><footer id='myFooter'>
        <div class='footer-copyright'>
            <p>

                <a href='' target='_blank'>Alfonso Orozco 2021-".date("Y")." $m</a>
                <a href='' target='_blank'></a>0.198?? s
            </p>
        </div>
    </footer>
</script>
<script src='https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js'  crossorigin='anonymous'></script>
</body></html>";
die($footboot);
} // foot boot
function domain(){
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $host = strtolower(preg_replace('/:\d+$/', '', $host)); // quita el puerto
    $host = preg_replace('/[^a-z0-9.\-]/', '', $host);      // solo caracteres válidos de dominio
    return preg_replace('/^www\./', '', $host);              // quita "www."
}
function topboot(){
$domain=domain();
$userr=$_SESSION['pilot_name'] ?? "";
return "<!DOCTYPE html>
<html lang='en'>

<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
    <meta name='description' content=''>
    <title>".$domain."</title>
    

    <link rel='icon' type='image/jpg' href='Includes/logo.jpg'>        
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css' crossorigin='anonymous'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css'>
         

<script src='https://unpkg.com/sweetalert/dist/sweetalert.min.js'></script>
    <!-- Custom styles -->   
	
	<style>
html, body { overflow-x: hidden; height: 100%; }
footer { margin-top: 100px; position: absolute; bottom: 0; width: 100%; height: 40px; }
#myFooter .footer-copyright { background-color: #333333; padding-top: 3px; padding-bottom: 3px; text-align: center; }
#myFooter .footer-copyright p { margin: 10px; color: #ccc; }
#myFooter a { color: #d2d1d1; text-decoration: none; }
#myFooter .footer-copyright a:hover { color: #d84b6b; }
</style>
	


</head><script>
    $(document).ready(function() {
        function disableBack() { window.history.forward() }

        window.onload = disableBack();
        window.onpageshow = function(evt) { if (evt.persisted) disableBack() }
    });
</script>
<body class='d-flex flex-column'><nav class='navbar fixed-top navbar-expand-lg navbar-dark bg-dark shadow-sm p-2'><font color='yellow'><b>$userr</b></font>
            <div class='container'>
                <a class='navbar-brand' href='./home'>
                <!--  <img src='Includes/logo.png' alt='' width='50' height='50' class='mr-3'>-->$domain</a>
                <button class='navbar-toggler' type='button' data-toggle='collapse' data-target='#navbarSupportedContent' aria-controls='navbarSupportedContent' aria-expanded='false' aria-label='{{ __('Toggle navigation') }}'>
                    <span class='navbar-toggler-icon'></span>
                </button>

                <div class='collapse navbar-collapse' id='navbarSupportedContent'>

                    <!-- Left Side Of Navbar -->
                    <ul class='navbar-nav mr-auto'>

                    </ul>

                    <!-- Right Side Of Navbar -->
                            
                    <ul class='navbar-nav ml-auto'>
                       <li class='nav-item'><a target='_blank' class='nav-link' href='?'><i class='fas fa-home'></i> Home</a></li>
					   
                    <div class='dropdown'>
                                <button class='btn btn-dark dropdown-toggle' type='button' id='imgdropdown' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                                    <i class='fas fa-microchip'></i> Auto
                                    <span class='caret'></span>
                                </button>
                                <div class='dropdown-menu' aria-labelledby='imgdropdown'>                                    
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=201'><i class='fas fa-object-group'></i> 201 Panels</a>
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=202'><i class='fas fa-globe'></i> 202 Planets</a>                                    
<a target='_blank' class='dropdown-item text-muted' href='?module=207'><i class='fa fa-question'></i>
</i> 207 Jerarchy</a>
                                </div>
                    </div>
                    
                    <div class='dropdown'>
                                <button class='btn btn-dark dropdown-toggle' type='button' id='imgdropdown' data-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
                                    <i class='fas fa-tools'></i> Tools
                                    <span class='caret'></span>
                                </button>
                                <div class='dropdown-menu' aria-labelledby='imgdropdown'>                                    
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=item'><i class='fas fa-search pr-2'></i>Search item</a>
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=settings'><i class='fas fa-user-minus pr-2'></i>Erase Pilot</a>
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=170'><i class='fa fa-house-user pr-2'></i>What Ship</a>
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=162'><i class='fas fa-boxes pr-2'></i>Inventory</a>
                                    <hr class='dropdown-divider'>
                                    <a target='_blank' class='dropdown-item text-muted' href='?module=161'><i class='fas fa-sync-alt pr-2'></i>Barrido</a>                                    
                                </div>
                            </div>                            
                        </ul>
                </div>
            </div>
            </nav><main class='flex-shrink-0' style='padding-bottom:50px; padding-top:90px'>
            <div name='main' id='main' class='flex-fill p-3'>            
            <div class='row'>
            <div class='col-md-12'>        
            ";
} // topboot
function fixstandings(){
global $link;
// fix a problem when i am talking of "eaten" characters
$rs=mysqli_query($link,"select distinct(target) from DIPLOMATIC where pilot_name='All Others Catalog' order by target"); 
while ($row = mysqli_fetch_object($rs)) {
   $t=$row->target;
   list($maxim)=avalues319("select max(reputation) from DIPLOMATIC where pilot_name='All Others Catalog' and target='$t'");
   //echo "<li>$t - $maxim";
   if ($maxim>0) {
     $q2="delete from DIPLOMATIC where pilot_name='All Others Catalog' and target='$t' and reputation<>$maxim;";
     $rs2=mysqli_query($link,$q2);
     sqlerror("error <hr>$q2<hr>");
   }
   }
   //echo "standings fixed !";
} // fixstandings

function OrcaPorpoise(){
global $link;
$csh=0;
$cad="'Orca','Porpoise','Kernite'";
$pass ="<table border=2><theader><tr><th>#</th><th>Ship Unique</th><th>Ship</th><th>qty</th><th>Where is the Ship</th><th>Insert Date</th><th>Pilot Number</th><th>Pilot Name</th></theader>";
$rs=mysqli_query($link,"SELECT eveunique as unique2,type_description,quantity as qty,description,date_insert,toon_number FROM `EVE_ASSETS` WHERE `type_description`
 IN ($cad) and year (date_insert)>2021 order by date_insert");
echo mysqli_error($link); 
while ($row = mysqli_fetch_object($rs)) {
  
  list($name,$email)=avalues319("select toon_name,email_pilot from PILOTS where toon_number='$row->toon_number'");
  //if ($name=='') $name=pilotfrominternet($row->toon_number);
  if ($name=='') $name=($row->toon_number);
   $csh ++;
  $pass .= "<tr><th>$csh</th><td>$row->unique2</td><td>$row->type_description</td><td>$row->qty</td><td>$row->description</td>
  <td>$row->date_insert</td><td>$row->toon_number</td><td>$name</td><td>$email</td></tr>"; 
}
$pass .="</table>";
return $pass;
} // orcaporpoise

function giveMyPilot_Minerals($toon_name,$ore){
list($toon)=avalues319("select toon_number from PILOTS where toon_name='$toon_name'");
$ccc="select sum(quantity) from EVE_ASSETS where toon_number=$toon and type_description='$ore' and quantity>1";

list($dime)=avalues319($ccc);
if ($dime=='') $dime=0;
return $dime;
} // givemypilot_minerals
function truncate($val, $f="0")
{
    if(($p = strpos($val, '.')) !== false) {
        $val = floatval(substr($val, 0, $p + 1 + $f));
    }
    return $val;
}

function gempanels(){
global $link;
// Establecer charset para evitar problemas con caracteres especiales
mysqli_set_charset($link, "utf8mb4");

// --- 3. Definiciones ---
$panel_types = ['n/a', 'cyan', 'white', 'black', 'purple', 'red', 'yellow', 'gray', 'brown'];
$message = ""; // Para mensajes de éxito o error, ahora se mostrará en la parte superior.

// --- 4. Función para escapar HTML ---
function html_escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// --- 5. Obtener todos los pilotos para los dropdowns ---
$all_pilots = [];
$sql_pilots = "SELECT toon_number, toon_name FROM PILOTS ORDER BY toon_name ASC";
$result_pilots = mysqli_query($link, $sql_pilots);
if ($result_pilots) {
    while ($row = mysqli_fetch_assoc($result_pilots)) {
        $all_pilots[$row['toon_number']] = $row['toon_name'];
    }
    mysqli_free_result($result_pilots);
} else {
    error_log("Error al obtener pilotos: " . mysqli_error($link));
}

// --- 6. Procesar Solicitud POST (Actualización de Panel por formulario simple) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_panel') {
    $idpanel = isset($_POST['idpanel']) ? intval($_POST['idpanel']) : 0;
    $pseudo = isset($_POST['pseudo']) ? trim($_POST['pseudo']) : '';
    $panel_type = isset($_POST['panel_type']) ? trim($_POST['panel_type']) : '';
    $pilot_1_num = isset($_POST['pilot_1']) ? trim($_POST['pilot_1']) : ''; // Puede ser vacío
    $pilot_2_num = isset($_POST['pilot_2']) ? trim($_POST['pilot_2']) : ''; // Puede ser vacío
    $pilot_3_num = isset($_POST['pilot_3']) ? trim($_POST['pilot_3']) : ''; // Puede ser vacío
    $manual_expiration = isset($_POST['manualExpiration']) ? trim($_POST['manualExpiration']) : null; // Nuevo: Campo de fecha editable
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // Validar idpanel
    if ($idpanel <= 0) {
        $message = "<div class='alert alert-danger'>ID de panel inválido.</div>";
    } else {
        // Validar panel_type
        if (!in_array($panel_type, $panel_types)) {
            $panel_type = 'n/a'; // Default a n/a si es inválido
        }

        // Deducir nombres de pilotos (name_1, name_2, name_3)
        $name_1 = isset($all_pilots[$pilot_1_num]) ? $all_pilots[$pilot_1_num] : '';
        $name_2 = isset($all_pilots[$pilot_2_num]) ? $all_pilots[$pilot_2_num] : '';
        $name_3 = isset($all_pilots[$pilot_3_num]) ? $all_pilots[$pilot_3_num] : '';

        // Preparar la consulta de actualización
        $sql_update = "
            UPDATE PANELS SET
                pseudo = ?,
                panel_type = ?,
                pilot_1 = ?,
                pilot_2 = ?,
                pilot_3 = ?,
                manualExpiration = ?, -- CAMBIO: Ahora editable
                name_1 = ?,
                name_2 = ?,
                name_3 = ?,
                refresh = refresh, -- No se edita en este formulario
                notes = ?
            WHERE idpanel = ?
        ";

        $stmt = mysqli_prepare($link, $sql_update);
        if ($stmt) {
            // "ssssssssssi" -> 10 strings (pseudo, panel_type, pilot_1, pilot_2, pilot_3, manualExpiration, name_1, name_2, name_3, notes), 1 integer (idpanel)
            mysqli_stmt_bind_param(
                $stmt, "ssssssssssi",
                $pseudo, $panel_type, $pilot_1_num, $pilot_2_num, $pilot_3_num,
                $manual_expiration, // CAMBIO: Añadido aquí
                $name_1, $name_2, $name_3, $notes, $idpanel
            );

            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $message = "<div class='alert alert-success'>Panel actualizado exitosamente.</div>";
                } else {
                    $message = "<div class='alert alert-info'>No se realizaron cambios o el panel no existe.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Error al ejecutar la actualización: " . mysqli_stmt_error($stmt) . "</div>";
                error_log("Error al ejecutar UPDATE en PANELS: " . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "<div class='alert alert-danger'>Error al preparar la consulta de actualización: " . mysqli_error($link) . "</div>";
            error_log("Error al preparar UPDATE en PANELS: " . mysqli_error($link));
        }
    }
    // Después de procesar el POST, puedes redirigir para evitar reenvío de formulario (PRG pattern)
    // Esto es útil para que al recargar la página, el mensaje no se duplique si el usuario refresca.
    // header("Location: panels_manager.php?filter_user_owner=" . $filter_user_owner . "&message=" . urlencode(strip_tags($message)));
    // exit();
}

// --- 7. Obtener el filtro de user_owner ---
$filter_user_owner = isset($_GET['filter_user_owner']) ? intval($_GET['filter_user_owner']) : 0;

// --- 8. Obtener user_owners únicos para el filtro ---
$unique_user_owners = [];
$sql_user_owners = "SELECT DISTINCT user_owner FROM PANELS ORDER BY user_owner ASC";
$result_user_owners = mysqli_query($link, $sql_user_owners);
if ($result_user_owners) {
    while ($row = mysqli_fetch_assoc($result_user_owners)) {
        $unique_user_owners[] = $row['user_owner'];
    }
    mysqli_free_result($result_user_owners);
} else {
    error_log("Error al obtener user_owners: " . mysqli_error($link));
}

// --- 9. Obtener Datos de Paneles (con filtro y ordenamiento) ---
$panels_data = [];
$sql_panels = "SELECT * FROM PANELS";
$where_clause = "";
if ($filter_user_owner > 0) {
	// ocultamos los que no se deben mostrar
    $where_clause = " WHERE refresh<>'NO' and user_owner = ?";
}
// CAMBIO: Ordenar por manualExpiration DESC y luego por idpanel DESC
$sql_panels .= $where_clause . " ORDER BY manualExpiration DESC, idpanel DESC";

$stmt_panels = mysqli_prepare($link, $sql_panels);
if ($stmt_panels) {
    if ($filter_user_owner > 0) {
        mysqli_stmt_bind_param($stmt_panels, "i", $filter_user_owner);
    }
    mysqli_stmt_execute($stmt_panels);
    $result_panels = mysqli_stmt_get_result($stmt_panels);
    if ($result_panels) {
        while ($row = mysqli_fetch_assoc($result_panels)) {
            $panels_data[] = $row;
        }
        mysqli_free_result($result_panels);
    } else {
        error_log("Error al obtener datos de paneles: " . mysqli_stmt_error($stmt_panels));
    }
    mysqli_stmt_close($stmt_panels);
} else {
    error_log("Error al preparar consulta de paneles: " . mysqli_error($link));
}

// --- Cerrar conexión a la base de datos ---
//mysqli_close($link);

$pass ="
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container-fluid {
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,.05);
        }
        .table th, .table td {
            vertical-align: middle;
            font-size: 0.9em;
            padding: 0.5rem; /* Compactar celdas */
        }
        .table th {
            background-color: #e9ecef;
        }
        .form-control-sm {
            font-size: 0.8em; /* Tamaño de fuente más pequeño para inputs/selects */
            height: calc(1.8125rem + 2px); /* Ajuste de altura para sm */
        }
        textarea.form-control-sm {
            height: auto; /* Permite que el textarea se ajuste a su contenido */
            min-height: 40px; /* Altura mínima para el textarea */
        }
        .form-inline .form-group {
            margin-bottom: 0;
            margin-right: 5px;
        }
        .form-inline .form-control {
            width: auto; /* Permitir que los controles se ajusten */
        }
        .form-inline .btn {
            white-space: nowrap;
        }
        .message-container {
            margin-top: 15px;
        }
        .status-message {
            font-size: 0.8em;
            margin-top: 5px;
            min-height: 20px; /* Para mantener el espacio */
        }
    </style>
</head>
<body>
<div class='container-fluid'>
    <div class='card'>
        <div class='card-header bg-dark text-white'>
            <h3 class='mb-0'><i class='fas fa-cogs mr-2'></i>Gestor de Paneles EVE Online</h3>
        </div>
        <div class='card-body'>
            $message
            <!-- Filtro por user_owner -->
            <form action='?module=201' method='GET' class='form-inline mb-4'>
            <input type='text' style='display:none' readonly name='module' value='201'>
                <div class='form-group mr-3'>
                    <label for='filter_user_owner' class='mr-2'>Filtrar por User Owner:</label>
                    <select class='form-control form-control-sm' id='filter_user_owner' name='filter_user_owner' onchange='this.form.submit()'>
                        <option value='0'>Todos</option>";
                        foreach ($unique_user_owners as $owner_id) { 
                            $pass .="<option value='".html_escape($owner_id)."'";
                            $pass .= ($filter_user_owner == $owner_id) ? 'selected' : '';
                                $pass .=">". html_escape($owner_id) ."</option>";
                         } 
                    $pass .="</select>
                </div>
            </form>

            <div class='table-responsive'>
                <table class='table table-striped table-hover table-bordered'>
                    <thead class='thead-light'>
                        <tr>
                            <th>No.</th> <!-- Nuevo: Columna para el número de renglón -->
                            <th>ID Panel</th>
                            <th>Pseudo</th>
                            <th>Tipo Panel</th>
                            <th>User Owner</th>
                            <th>Piloto 1</th>
                            <th>Piloto 2</th>
                            <th>Piloto 3</th>
                            <th>Exp. Manual</th> <!-- CAMBIO: Ahora editable -->
                            <th>Refresh</th>
                            <th>Notas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>";
                        
                        if (!empty($panels_data)) {
                            $row_number = 1; // Inicializar contador de renglones
                            foreach ($panels_data as $panel) {

                                $pass .="<tr id='panel-row-".html_escape($panel['idpanel'])."'>
                                    <td>".$row_number++ ."</td>
                                    <td>".html_escape($panel['idpanel'])."</td>
                                    <form action='?module=201' method='POST' class='d-inline'>
                                    <input type='hidden' style='display:none' readonly name='module' value='201'>
                                        <input type='hidden' name='action' value='update_panel'>
                                        <input type='hidden' name='idpanel' value='".html_escape($panel['idpanel'])."'>
                                        <!-- Mantener el filtro actual en el POST para que la página recargue con el mismo filtro -->
                                        <input type='hidden' name='filter_user_owner' value='".html_escape($filter_user_owner)."'>
                                        <td>
                                            <input type='text' class='form-control form-control-sm' name='pseudo' value='".html_escape($panel['pseudo'])."'>
                                        </td>
                                        <td>
                                            <select class='form-control form-control-sm' name='panel_type'>";
                                                foreach ($panel_types as $type) {
                                                    $def="";
                                                    if ($panel['panel_type'] == $type) $def ='selected'; 
                                                    $pass .="<option value='".html_escape($type)."' $def>"; 
                                                    $pass .= html_escape($type) ."</option>";
                                                } 
                                            $pass .="</select>
                                        </td>
                                        <td>".html_escape($panel['user_owner'])."</td>
                                        <td>
                                            <select class='form-control form-control-sm' name='pilot_1'>
                                                <option value=''>-- Ninguno --</option>";
                                                foreach ($all_pilots as $toon_number => $toon_name) {
                                                    $def="";
                                                    if ($panel['pilot_1'] == $toon_number) $def ='selected'; 
                                                    $pass .="<option value='".html_escape($toon_number). "' $def >"; 
                                                    $pass .=html_escape($toon_name);
                                                    $pass .="</option>";
                                                }
                                            $pass .="</select>
                                        </td>
                                        <td>
                                            <select class='form-control form-control-sm' name='pilot_2'>
                                                <option value=''>-- Ninguno --</option>";
                                                foreach ($all_pilots as $toon_number => $toon_name) {
                                                    $def="";
                                                    if ($panel['pilot_2'] == $toon_number) $def ='selected'; 
                                                    $pass .="<option value='".html_escape($toon_number). "' $def >";
                                                        $pass .=html_escape($toon_name);  
                                                    $pass .="</option>";
                                                 }
                                            $pass .="</select>
                                        </td>
                                        <td>
                                            <select class='form-control form-control-sm' name='pilot_3'>
                                                <option value=''>-- Ninguno --</option>";
                                                foreach ($all_pilots as $toon_number => $toon_name) { 
                                                    $def="";
                                                    if ($panel['pilot_3'] == $toon_number) $def ='selected'; 
                                                    $pass .="<option value='".html_escape($toon_number). "' $def >";
                                                        $pass .=html_escape($toon_name);  
                                                    $pass .="</option>";
                                                }
                                            $pass .="</select>
                                        </td>
                                        <td>
                                            <input type='date' class='form-control form-control-sm' name='manualExpiration' value='".html_escape($panel['manualExpiration'])."'>
                                        </td>
                                        <td>". html_escape($panel['refresh'])."</td>
                                        <td>
                                            <textarea class='form-control form-control-sm' name='notes' rows='2'>".html_escape($panel['notes'])."</textarea>
                                        </td>
                                        <td>
                                            <button type='submit' class='btn btn-primary btn-sm'>
                                                <i class='fas fa-save mr-1'></i> Guardar
                                            </button>
                                        </td>
                                    </form>
                                </tr>";
                                
                            }
                        } else {
                            $pass .="<tr><td colspan='12' class='text-center'>No se encontraron paneles.</td></tr>";
                        }
                        
                    $pass .="</tbody>
                </table>
            </div>
        </div>
    </div>
</div>";
return $pass.createpanel();
} // gempanels
function createpanel(){
    global $link;
	$message="";
    
    // Obtener el email del usuario de la sesión
    $selected_email = isset($_SESSION['youremail']) ? $_SESSION['youremail'] : '';
    list($number)=avalues319("select user_owner from users where email='$selected_email'");
    $user_owner=$number;
    // Establecer charset
    mysqli_set_charset($link, "utf8mb4");
    if(step()==3){
    // Obtener nombres de pilotos
            $all_pilots = [];
            $sql_pilots = "SELECT toon_number, toon_name FROM PILOTS";
            $result_pilots = mysqli_query($link, $sql_pilots);
            if ($result_pilots) {
                while ($row = mysqli_fetch_assoc($result_pilots)) {
                    $all_pilots[$row['toon_number']] = $row['toon_name'];
                }
                mysqli_free_result($result_pilots);
            }
            
            $pilot_1_num=$_POST['pilot_1'];
            $pilot_2_num=$_POST['pilot_2'];
            $pilot_3_num=$_POST['pilot_3'];
            $manual_expiration=$_POST['manualExpiration'];
            $pseudo=$_POST['pseudo'];
            $panel_type=$_POST['panel_type'];
            
            $name_1 = isset($all_pilots[$pilot_1_num]) ? $all_pilots[$pilot_1_num] : '';
            $name_2 = isset($all_pilots[$pilot_2_num]) ? $all_pilots[$pilot_2_num] : '';
            $name_3 = isset($all_pilots[$pilot_3_num]) ? $all_pilots[$pilot_3_num] : '';
            
            $notes=trim(addslashes($_POST['notes']));
            /* esto debe funcionar mejor $name_1=nametoon($pilot_1_num);
            $name_2=nametoon($pilot_2_num);
            $name_3=nametoon($pilot_3_num); */
            
            // Insertar nuevo panel
            $sql_insert = "
                INSERT INTO PANELS (pseudo, panel_type, user_owner, pilot_1, pilot_2, pilot_3, 
                                    manualExpiration, name_1, name_2, name_3, refresh, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'YES', ?)
            ";
            
            $stmt = mysqli_prepare($link, $sql_insert);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt, "sssssssssss",
                    $pseudo, $panel_type, $user_owner, $pilot_1_num, $pilot_2_num, $pilot_3_num,
                    $manual_expiration, $name_1, $name_2, $name_3, $notes
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "<div class='alert alert-success'>Panel creado exitosamente con ID: " . mysqli_insert_id($link) . "</div>";
                } else {
                    $message = "<div class='alert alert-danger'>Error al crear el panel: " . mysqli_stmt_error($stmt) . "</div>";
                    error_log("Error al ejecutar INSERT en PANELS: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "<div class='alert alert-danger'>Error al preparar la consulta: " . mysqli_error($link) . "</div>";
                error_log("Error al preparar INSERT en PANELS: " . mysqli_error($link));
            }
    
    } // es alta 
    
    // Obtener todos los pilotos para los dropdowns
    $all_pilots = [];
    $sql_pilots = "SELECT toon_number, toon_name FROM PILOTS ORDER BY toon_name ASC";
    $result_pilots = mysqli_query($link, $sql_pilots);
    if ($result_pilots) {
        while ($row = mysqli_fetch_assoc($result_pilots)) {
            $all_pilots[$row['toon_number']] = $row['toon_name'];
        }
        mysqli_free_result($result_pilots);
    } else {
        error_log("Error al obtener pilotos: " . mysqli_error($link));
    }
    
    // Definir colores disponibles
    $panel_colors = ['n/a', 'cyan', 'black', 'purple', 'white', 'red', 'brown', 'yellow', 'gray'];
    
    //mysqli_close($link);
    
    $pass = "
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container-fluid {
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,.05);
        }
        .form-group label {
            font-weight: bold;
        }
        .required-field::after {
            content: ' *';
            color: red;
        }
    </style>";
//</head>
//<body>
$pass .="<div class='container-fluid'>
    $message
    <div class='card'>
        <div class='card-header bg-dark text-white'>
            <h3 class='mb-0'><i class='fas fa-plus-circle mr-2'></i>Create/Edit Eve Panel</h3>
        </div>
        <div class='card-body'>
            <form action='?module=201&step=3' method='POST' id='createPanelForm'>
                <input type='hidden' name='action' value='create_panel'>
                <input type='hidden' name='user_owner' value='".html_escape($selected_email)."'>
                
                <div class='form-group'>
                    <label for='pseudo' class='required-field'>Pseudo</label>
                    <input type='text' class='form-control' id='pseudo' name='pseudo' required maxlength='20'>
                </div>
                
                <div class='form-group'>
                    <label for='panel_type' class='required-field'>Panel Color</label>
                    <select class='form-control' id='panel_type' name='panel_type' required>
                        <option value=''>-- Seleccionar Color --</option>";
                        foreach ($panel_colors as $color) {
                            $pass .= "<option value='".html_escape($color)."'>".html_escape($color)."</option>";
                        }
                    $pass .= "</select>
                </div>
                
                <div class='form-group'>
                    <label for='pilot_1'>Piloto 1</label>
                    <select class='form-control' id='pilot_1' name='pilot_1'>
                        <option value=''>-- Ninguno --</option>";
                        foreach ($all_pilots as $toon_number => $toon_name) {
                            $pass .= "<option value='".html_escape($toon_number)."'>".html_escape($toon_name)."</option>";
                        }
                    $pass .= "</select>
                </div>
                
                <div class='form-group'>
                    <label for='pilot_2'>Piloto 2</label>
                    <select class='form-control' id='pilot_2' name='pilot_2'>
                        <option value=''>-- Ninguno --</option>";
                        foreach ($all_pilots as $toon_number => $toon_name) {
                            $pass .= "<option value='".html_escape($toon_number)."'>".html_escape($toon_name)."</option>";
                        }
                    $pass .= "</select>
                </div>
                
                <div class='form-group'>
                    <label for='pilot_3'>Piloto 3</label>
                    <select class='form-control' id='pilot_3' name='pilot_3'>
                        <option value=''>-- Ninguno --</option>";
                        foreach ($all_pilots as $toon_number => $toon_name) {
                            $pass .= "<option value='".html_escape($toon_number)."'>".html_escape($toon_name)."</option>";
                        }
                    $pass .= "</select>
                </div>
                
                <div class='form-group'>
                    <label for='manualExpiration'>Fecha de Expiración Manual</label>
                    <input type='date' class='form-control' id='manualExpiration' name='manualExpiration' value='1969-12-31'>
                </div>
                
                <div class='form-group'>
                    <label for='notes'>Notas</label>
                    <textarea class='form-control' id='notes' name='notes' rows='4' maxlength='2048'></textarea>
                </div>
                
                <div class='form-group'>
                    <div class='custom-control custom-checkbox'>
                        <input type='checkbox' class='custom-control-input' id='confirmData' name='confirmData' required>
                        <label class='custom-control-label required-field' for='confirmData'>
                            Confirmo que los datos son correctos
                        </label>
                    </div>
                </div>
                
                <div class='form-group'>
                    <button type='submit' class='btn btn-success btn-lg'>
                        <i class='fas fa-save mr-2'></i>Crear Panel
                    </button>
                    <a href='?module=201' class='btn btn-secondary btn-lg ml-2'>
                        <i class='fas fa-arrow-left mr-2'></i>Volver
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>";    
    return $pass;
} // create panel

function gemplanets(){
global $link;
// Get selected email
$selected_email=$_SESSION['youremail'];
//$selected_email = isset($_POST['supergroup']) ? $_POST['supergroup'] : '';

// Function to update planet_comment
if (isset($_POST['update_comment'])) {
    $toon_number = mysqli_real_escape_string($link, $_POST['toon_number']);
    $new_comment = mysqli_real_escape_string($link, $_POST['planet_comment']);
    
    $update_sql = "UPDATE PILOTS SET planet_comment = '$new_comment' WHERE toon_number = $toon_number";
    mysqli_query($link, $update_sql);
}

// Get list of unique emails (excluding Catalog)
$email_sql = "SELECT DISTINCT supergroup FROM PILOTS 
              WHERE toon_name NOT LIKE '%Catalog%' 
              AND (planets IS NOT NULL) and (planets != '' ) 
              ORDER BY supergroup";
$email_result = mysqli_query($link, $email_sql);

$csh=0;
$pass="<style>
        .planet-table {
            font-size: 0.9em;
        }
        .editable-comment {
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
    </style>
    <div class='container-fluid mt-3'>
        <h1>EVE Online - Planetary Interaction</h1>
        
";

        if ($selected_email<>""){
            // Get pilots for the selected email that have planets
            $pilots_sql = "SELECT toon_number, toon_name, skillpoints, planets, planet_comment 
                          FROM PILOTS 
                          WHERE 1=1
                          AND toon_name NOT LIKE '%Catalog%' 
                          AND (planets IS NOT NULL) and (planets<>'[]' AND planets != '' ) 
                          ORDER BY skillpoints DESC";
        }
        if ($selected_email==""){
            // Get pilots for the selected email that have planets
            $pilots_sql = "SELECT toon_number, toon_name, skillpoints, planets, planet_comment 
                          FROM PILOTS 
                          WHERE 1=1 
                          AND toon_name NOT LIKE '%Catalog%' 
                          AND (planets IS NOT NULL) and (planets<>'[]' AND planets != '' ) 
                          ORDER BY skillpoints DESC";
        }                  
            $pilots_result = mysqli_query($link, $pilots_sql) or die(mysql_error($link));
            //die($pilots_sql);
            if (mysqli_num_rows($pilots_result) == 0)        $pass .="<div class='alert alert-warning'>
                    No pilots with planet data were found for this email. $selected_email
                </div>";
            if (mysqli_num_rows($pilots_result) > 0){
            $pass .="
                <div class='alert alert-info'>
                    <strong>Selected email:</strong>".htmlspecialchars($selected_email) .
                    "</div>";

                while ($pilot = mysqli_fetch_assoc($pilots_result)){
                    //die($pilot['toon_name']);
                    $pass .="<div class='card mb-4'>
                        <div class='card-body'>
                            <h3>"; 
                            
                                $csh ++;
                                $pass .=" $csh " .htmlspecialchars($pilot['toon_name']). 
                                "<small class='text-muted'> (". number_format($pilot['skillpoints'])." SP)</small>
                            </h3>
                            
                            <!-- Editable field for planet_comment -->
                            <form method='POST' class='mb-3'>
                                <input type='hidden' name='toon_number' value='". $pilot['toon_number'] ."'>
                                <div class='form-group'>
                                    <label>Planet Comment:</label>
                                    <div class='input-group'>
                                        <input type='text' name='planet_comment' class='form-control' 
                                               value='". htmlspecialchars($pilot['planet_comment']). "' placeholder='Add comment...'>
                                        <div class='input-group-append'>
                                            <button type='submit' name='update_comment' class='btn btn-primary btn-sm'>Update</button>
                                        </div>
                                    </div>
                                </div>
                            </form>";
                            
                            // Decode planets JSON                            
                            $planets_data = json_decode(stripslashes($pilot['planets']), true);
                            if ($planets_data && is_array($planets_data)){
                            
                                $pass .="<div class='table-responsive'>
                                    <table class='table table-striped table-bordered planet-table'>
                                        <thead class='thead-dark'>
                                            <tr>
                                                <th>#</th>
                                                <th>Planet ID</th>
                                                <th>Type</th>
                                                <th>Solar System ID</th>
                                                <th>Level</th>
                                                <th>Pins</th>
                                                <th>Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>";
                                            foreach ($planets_data as $index => $planet){
                                                $pass .="<tr>
                                                    <td>". ($index + 1); 
                                                    $planetname=$planet['planet_id'];
                                                    list($planetname)=avalues319("select itemName from invUniqueNames where itemID=$planetname");
                                                    if ($planetname=='')$planetname=$planet['planet_id'];
                                                    
                                                    $pass .='</td><td>'.htmlspecialchars(ucfirst($planetname));                                                                                                         
                                                    
                                                    $solsystem=($planet['solar_system_id']);
                                                    list($solsystem)=avalues319("select itemName from invUniqueNames where itemID=$solsystem");
                                                     
                                                    $pass .="</td>
                                                    <td>
                                                        <span class='badge badge-info'>
                                                            ".htmlspecialchars(ucfirst($planet['planet_type']))."
                                                        </span>
                                                    </td>
                                                    <td>
                                                    <a target'_blank' href='https://evemaps.dotlan.net/system/$solsystem'>$solsystem</a>
                                                    </td>
                                                    <td>
                                                        <span class='badge badge-success'>".
                                                             htmlspecialchars($planet['upgrade_level'])."
                                                        </span>
                                                    </td>
                                                    <td>".htmlspecialchars($planet['num_pins'])."</td>
                                                    <td>";                                                         
                                                        if (isset($planet['last_update'])) {
                                                            $date = new DateTime($planet['last_update']);
                                                            $pass .= $date->format('Y-m-d H:i:s');
                                                        }
                                                        
                                                    $pass .="</td>
                                                </tr>";
                                            }
                                        $pass .="</tbody>
                                    </table>
                                </div>
                                <p class='text-muted'>
                                    <small>Total planets: ".count($planets_data)."</small>
                                </p>";
                                }
                             else
                                $pass .="<div class='alert alert-warning'>
                                    Could not load planet data for this pilot.
                                </div>";                            
                        $pass .="</div>                    
                    </div>";                                    
            } // view planets 
            
        } 
        
        // endif    
return $pass;
} // gemplanets

function analyzeEveHierarchy($db_connection, $email_filter = '', $min_amount = 500000, $hide_no_history = false) {
    $output = '';
    
    if (!$db_connection) {
        return "<div class='alert alert-danger'>Error: No hay conexión a la base de datos</div>";
    }
    
    // Construir query con filtros
    $where_clause = "WHERE toon_name NOT LIKE '%Catalog%'";
    if (!empty($email_filter)) {
        $email_filter_safe = mysqli_real_escape_string($db_connection, $email_filter);
        $where_clause .= " AND supergroup = '{$email_filter_safe}'";
    }
    
    $query = "SELECT toon_name, toon_number, supergroup, journal, lastsaved FROM PILOTS {$where_clause} ORDER BY supergroup, toon_name";
    $result = mysqli_query($db_connection, $query);
    //die($query);
    if (!$result) {
        return "<div class='alert alert-danger'>Error en la consulta: " . mysqli_error($db_connection) . "</div>";
    }
    
    $pilots_data = array();
    $pilot_lookup = array();
    $current_date = new DateTime();
    
    // Recopilar todos los datos de pilots
    while ($row = mysqli_fetch_assoc($result)) {
        // Calcular días sin actualizar
        $last_saved = new DateTime($row['lastsaved']);
        $days_diff = $current_date->diff($last_saved)->days;
        $row['days_without_update'] = $days_diff;
        
        $pilots_data[] = $row;
        $pilot_lookup[$row['toon_number']] = array(
            'name' => $row['toon_name'],
            'email' => $row['supergroup']
        );
    }
    
    $output .= "<div class='alert alert-success'>Conexión exitosa a la base de datos</div>";
    $output .= "<h5>Total de pilots encontrados: " . count($pilots_data) . "</h5>";
    
    // Agrupar por email
    $email_groups = array();
    $all_donations = array();
    $pilot_donation_summary = array();
    
    foreach ($pilots_data as $pilot) {
        $email = $pilot['supergroup'];
        $pilot_id = $pilot['toon_number'];
        $pilot_name = $pilot['toon_name'];
        $days_without_update = $pilot['days_without_update'];
        
        if (!isset($email_groups[$email])) {
            $email_groups[$email] = array();
        }
        $email_groups[$email][] = $pilot;
        
        // Inicializar resumen del pilot
        $pilot_donation_summary[$pilot_id] = array(
            'name' => $pilot_name,
            'email' => $email,
            'days_without_update' => $days_without_update,
            'last_saved' => $pilot['lastsaved'],
            'donated_to' => array(),
            'received_from' => array(),
            'donated_to_count' => 0,
            'received_from_count' => 0,
            'has_donation_history' => false
        );
        
        // Procesar journal de este pilot
		//if ($pilot['journal']<>'[]' ) die ($pilot['journal']);
        $journal_data = json_decode(stripslashes($pilot['journal']), true);
        if ($journal_data && is_array($journal_data)) {
            foreach ($journal_data as $transaction) {
                if (isset($transaction['ref_type']) && $transaction['ref_type'] === 'player_donation') {
                    $amount = floatval($transaction['amount']);
                    if (abs($amount) >= $min_amount) {
                        $pilot_donation_summary[$pilot_id]['has_donation_history'] = true;
                        
                        $donation = array(
                            'donor_id' => $transaction['first_party_id'],
                            'recipient_id' => $transaction['second_party_id'],
                            'amount' => $amount,
                            'date' => $transaction['date'],
                            'description' => $transaction['description'] ?? '',
                            'donor_name' => $pilot['toon_name'],
                            'donor_email' => $pilot['supergroup']
                        );
                        $all_donations[] = $donation;
                        
                        // Actualizar resumen de donaciones
                        if ($amount < 0) {
                            // Este pilot donó a alguien
                            $recipient_id = $transaction['second_party_id'];
                            $recipient_name = isset($pilot_lookup[$recipient_id]) ? 
                                $pilot_lookup[$recipient_id]['name'] : 
                                "EXTERNO-ID:<a href='https://evewho.com/character/{$recipient_id}' target='_blank'>{$recipient_id}</a>";
                            
                            $pilot_donation_summary[$pilot_id]['donated_to'][] = $recipient_name;
                            $pilot_donation_summary[$pilot_id]['donated_to_count']++;
                        }
                    }
                }
            }
        }
    }
    
    // Procesar quién recibió donaciones (segunda pasada)
    foreach ($all_donations as $donation) {
        if ($donation['amount'] < 0) {
            $recipient_id = $donation['recipient_id'];
            $donor_name = $donation['donor_name'];
            
            if (isset($pilot_donation_summary[$recipient_id])) {
                $pilot_donation_summary[$recipient_id]['received_from'][] = $donor_name;
                $pilot_donation_summary[$recipient_id]['received_from_count']++;
                $pilot_donation_summary[$recipient_id]['has_donation_history'] = true;
            }
        }
    }
    
    // Aplicar filtro de historial si está activado
    if ($hide_no_history) {
        $pilot_donation_summary = array_filter($pilot_donation_summary, function($summary) {
            return $summary['has_donation_history'];
        });
        
        // También filtrar los grupos de email
        foreach ($email_groups as $email => $pilots) {
            $filtered_pilots = array_filter($pilots, function($pilot) use ($pilot_donation_summary) {
                return isset($pilot_donation_summary[$pilot['toon_number']]);
            });
            
            if (empty($filtered_pilots)) {
                unset($email_groups[$email]);
            } else {
                $email_groups[$email] = array_values($filtered_pilots);
            }
        }
    }
    
    $output .= "<h5>Total de donaciones encontradas: " . count($all_donations) . "</h5>";
    
    // Mostrar análisis por grupo de email
    foreach ($email_groups as $email => $pilots) {
        $output .= "<div class='card email-group hierarchy-card'>";
        $output .= "<div class='card-header bg-primary text-white'>";
        $output .= "<h5 class='mb-0'>📧 Grupo Email: {$email} (" . count($pilots) . " pilots)</h5>";
        $output .= "</div>";
        $output .= "<div class='card-body'>";
        
        // Mostrar pilots en este grupo con días sin actualizar
        $output .= "<div class='row mb-3'>";
        foreach ($pilots as $pilot) {
            $days_class = '';
            $days_text = '';
            
            if ($pilot['days_without_update'] <= 7) {
                $days_class = 'text-success';
                $days_text = 'Reciente';
            } elseif ($pilot['days_without_update'] <= 30) {
                $days_class = 'text-warning';
                $days_text = 'Algo viejo';
            } else {
                $days_class = 'text-danger';
                $days_text = 'Muy viejo';
            }
            
            $output .= "<div class='col-md-4 mb-2'>";
            $output .= "<div class='card bg-light'>";
            $output .= "<div class='card-body p-2'>";
            $output .= "<small class='pilot-name'>{$pilot['toon_name']}</small><br>";
            $output .= "<small class='text-muted'>ID: {$pilot['toon_number']}</small><br>";
            $output .= "<small class='{$days_class}'><strong>{$pilot['days_without_update']} días</strong> ({$days_text})</small>";
            $output .= "</div></div></div>";
        }
        $output .= "</div>";
        
        // Filtrar donaciones de este grupo
        $group_donations = array_filter($all_donations, function($donation) use ($email) {
            return $donation['donor_email'] === $email;
        });
        
        if (count($group_donations) > 0) {
            $output .= "<h6>💰 Análisis de Donaciones:</h6>";
            
            // Estadísticas del grupo
            $total_donated = 0;
            $internal_donations = 0;
            $external_donations = 0;
            
            foreach ($group_donations as $donation) {
                if ($donation['amount'] < 0) {
                    $total_donated += abs($donation['amount']);
                    
                    // Verificar si es interno o externo
                    $recipient_id = $donation['recipient_id'];
                    $is_external = !isset($pilot_lookup[$recipient_id]);
                    
                    if ($is_external) {
                        $external_donations++;
                    } else {
                        $internal_donations++;
                    }
                    
                    // Mostrar la donación
                    $css_class = $is_external ? 'external-transaction' : 'internal-transaction';
                    $status_text = $is_external ? '🌐 EXTERNO' : '🏠 INTERNO';
                    
                    $output .= "<div class='donation-flow {$css_class}'>";
                    $output .= "<div class='row'>";
                    $output .= "<div class='col-md-8'>";
                    $output .= "<strong>{$donation['donor_name']}</strong> → ";
                    
                    if ($is_external) {
                        $output .= "<span class='text-warning'>ID Externo: {$recipient_id}</span>";
                    } else {
                        $recipient_info = $pilot_lookup[$recipient_id];
                        $output .= "<span class='pilot-name'>{$recipient_info['name']}</span>";
                        $output .= " <small class='text-muted'>({$recipient_info['email']})</small>";
                    }
                    
                    $output .= "</div>";
                    $output .= "<div class='col-md-2 text-right'>";
                    $output .= "<span class='amount-negative'>" . number_format(abs($donation['amount']), 0, '.', ',') . " ISK</span>";
                    $output .= "</div>";
                    $output .= "<div class='col-md-2 text-right'>";
                    $output .= "<small class='badge badge-" . ($is_external ? 'warning' : 'info') . "'>{$status_text}</small>";
                    $output .= "</div>";
                    $output .= "</div>";
                    $output .= "<small class='text-muted'>" . date('Y-m-d H:i', strtotime($donation['date'])) . "</small>";
                    $output .= "</div>";
                }
            }
            
            // Resumen estadístico del grupo
            $output .= "<div class='row mt-3'>";
            $output .= "<div class='col-md-4'>";
            $output .= "<div class='card bg-danger text-white'>";
            $output .= "<div class='card-body text-center'>";
            $output .= "<h6>Total Donado</h6>";
            $output .= "<h4>" . number_format($total_donated, 0, '.', ',') . " ISK</h4>";
            $output .= "</div></div></div>";
            
            $output .= "<div class='col-md-4'>";
            $output .= "<div class='card bg-info text-white'>";
            $output .= "<div class='card-body text-center'>";
            $output .= "<h6>Donaciones Internas</h6>";
            $output .= "<h4>{$internal_donations}</h4>";
            $output .= "</div></div></div>";
            
            $output .= "<div class='col-md-4'>";
            $output .= "<div class='card bg-warning text-white'>";
            $output .= "<div class='card-body text-center'>";
            $output .= "<h6>Donaciones Externas</h6>";
            $output .= "<h4>{$external_donations}</h4>";
            $output .= "</div></div></div>";
            $output .= "</div>";
            
        } else {
            $output .= "<div class='alert alert-info'>No se encontraron donaciones para este grupo de email.</div>";
        }
        
        $output .= "</div>";
        $output .= "</div>";
    }
    
    // TABLA RESUMEN DE RELACIONES ACTUALIZADA
    $output .= "<div class='card mt-4'>";
    $output .= "<div class='card-header bg-success text-white'>";
    $output .= "<h5 class='mb-0'>📋 Resumen de Relaciones de Donación por Pilot</h5>";
    $output .= "</div>";
    $output .= "<div class='card-body'>";
    
    $output .= "<div class='table-responsive'>";
    $output .= "<table class='table table-striped table-hover summary-table'>";
    $output .= "<thead class='thead-dark'>";
    $output .= "<tr>";
    $output .= "<th style='width: 4%'>#</th>";
    $output .= "<th style='width: 18%'>Nombre Pilot</th>";
    $output .= "<th style='width: 8%'>Días</th>";
    $output .= "<th style='width: 8%'>Recibió (#)</th>";
    $output .= "<th style='width: 8%'>Donó (#)</th>";
    $output .= "<th style='width: 27%'>Quiénes le Donaron</th>";
    $output .= "<th style='width: 27%'>A Quiénes Donó</th>";
    $output .= "</tr>";
    $output .= "</thead>";
    $output .= "<tbody>";
    
    $row_number = 1;
    foreach ($pilot_donation_summary as $pilot_id => $summary) {
        $output .= "<tr>";
        $output .= "<td class='text-center'><strong>$row_number</strong></td>";
        
        $output .= "<td>";
        $output .= "<strong class='pilot-name'>{$summary['name']}</strong><br>";
        $output .= "<small class='text-muted'>{$summary['email']}</small>";
        $output .= "</td>";
        
        // Columna de días sin actualizar
        $days = $summary['days_without_update'];
        $days_class = '';
        if ($days <= 7) {
            $days_class = 'badge-success';
        } elseif ($days <= 30) {
            $days_class = 'badge-warning';
        } else {
            $days_class = 'badge-danger';
        }        
        $output .= "<td class='text-center'>";
        $output .= "<span class='badge {$days_class}' title='Última actualización: {$summary['last_saved']}'>{$days}</span>";
        $output .= "</td>";
        
        $has_activity = $summary['has_donation_history'];
        
        $output .= "<td class='text-center'>";
        if ($has_activity && $summary['received_from_count'] > 0) {
            $output .= "<span class='badge badge-success'>{$summary['received_from_count']}</span>";
        } else {
            $output .= "<span class='badge badge-secondary'>0</span>";
        }
        $output .= "</td>";
        
        $output .= "<td class='text-center'>";
        if ($has_activity && $summary['donated_to_count'] > 0) {
            $output .= "<span class='badge badge-danger'>{$summary['donated_to_count']}</span>";
        } else {
            $output .= "<span class='badge badge-secondary'>0</span>";
        }
        $output .= "</td>";
        
        $output .= "<td>";
        if (!$has_activity) {
            $output .= "<small class='text-muted font-italic'>Sin historial</small>";
        } else if (!empty($summary['received_from'])) {
            $unique_donors = array_unique($summary['received_from']);
            $output .= "<div class='detail-list'>";
            $output .= implode(', ', $unique_donors);
            $output .= "</div>";
        } else {
            $output .= "<small class='text-muted'>Ninguno</small>";
        }
        $output .= "</td>";
        
        $output .= "<td>";
        if (!$has_activity) {
            $output .= "<small class='text-muted font-italic'>Sin historial</small>";
        } else if (!empty($summary['donated_to'])) {
            $unique_recipients = array_unique($summary['donated_to']);
            $output .= "<div class='detail-list'>";
            $output .= implode(', ', $unique_recipients);
            $output .= "</div>";
        } else {
            $output .= "<small class='text-muted'>Ninguno</small>";
        }
        $output .= "</td>";
        
        $output .= "</tr>";
        $row_number++;
    }
    
    $output .= "</tbody>";
    $output .= "</table>";
    $output .= "</div>";
    
    $output .= "</div>";
    $output .= "</div>";
    
    return $output;
}

// Función para obtener la lista de emails (para el dropdown)
function getEmailList($db_connection) {
    $emails = array();
    
    if ($db_connection) {
        $email_query = "SELECT DISTINCT supergroup FROM PILOTS WHERE toon_name NOT LIKE '%Catalog%' ORDER BY supergroup";
        $email_result = mysqli_query($db_connection, $email_query);
        
        if ($email_result) {
            while ($email_row = mysqli_fetch_assoc($email_result)) {
                $emails[] = $email_row['supergroup'];
            }
        }
    }
    
    return $emails;
}

// EJEMPLO DE USO:
/*

// Parámetros de filtrado
$email_filter = $_POST['email_filter'] ?? '';
$min_amount = floatval($_POST['min_amount'] ?? 500000);
$hide_no_history = isset($_POST['hide_no_history']) && $_POST['hide_no_history'] === '1';

// Ejecutar análisis
$analysis_html = analyzeEveHierarchy($connection, $email_filter, $min_amount, $hide_no_history);

// Obtener lista de emails para dropdown
$email_list = getEmailList($connection);

// Cerrar conexión
mysqli_close($connection);

// Usar $analysis_html donde necesites mostrar los resultados
echo $analysis_html;
*/


// Función para generar el formulario de filtros
function generateFilterForm($email_list, $selected_values = array()) {
    $selected_email = $selected_values['email_filter'] ?? '';
    $min_amount = $selected_values['min_amount'] ?? 500000;
    $hide_no_history = isset($selected_values['hide_no_history']) && $selected_values['hide_no_history'] === '1';
    
    $form_html = '
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0">EVE Online - Análisis de Jerarquía por Email Groups 2</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="?module=207">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="email_filter">Filtrar por Email:</label>
                            <select class="form-control" id="email_filter" name="email_filter">
                                <option value="">-- Todos los emails --</option>';
    
    foreach ($email_list as $email) {
        $email_escaped = htmlspecialchars($email);
        $selected = ($email === $selected_email) ? 'selected' : '';
        $form_html .= "<option value='{$email_escaped}' {$selected}>{$email_escaped}</option>";
    }    
    $form_html .= '           </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="min_amount">Monto mínimo de donación:</label>
                            <input type="number" class="form-control" id="min_amount" name="min_amount" 
                                   placeholder="500000" value="' . htmlspecialchars($min_amount) . '">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="hide_no_history">Filtro de Historial:</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" 
                                       id="hide_no_history" name="hide_no_history" ' . ($hide_no_history ? 'checked' : '') . '>
                                <label class="form-check-label" for="hide_no_history">
                                    <strong>Ocultar pilots sin historial de donaciones</strong>
                                    <br><small class="text-muted">Solo mostrar pilots que han donado o recibido donaciones</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="analyze" class="btn btn-primary btn-lg">
                            <i class="fa fa-search"></i> Analizar Jerarquías
                        </button>
                        <small class="form-text text-muted mt-2">
                            <strong>Nueva función:</strong> La columna "Días" muestra cuántos días han pasado desde la última actualización del personaje.
                            <span class="badge badge-success">Verde ≤7 días</span>
                            <span class="badge badge-warning">Amarillo ≤30 días</span>  
                            <span class="badge badge-danger">Rojo >30 días</span>
                        </small>
                    </div>
                </div>
            </form>
        </div>
    </div>';
    
    return $form_html;
}

function gemjerarquia(){
global $link;
// EJEMPLO DE IMPLEMENTACIÓN COMPLETA:

$pass="<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>EVE Online Hierarchy Analyzer</title>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css'>";
$pass="    
    <style>
        .hierarchy-card { margin-bottom: 20px; }
        .donation-flow { 
            background: linear-gradient(90deg, #e3f2fd 0%, #f3e5f5 100%); 
            padding: 10px; 
            border-radius: 5px; 
            margin: 5px 0; 
        }
        .external-transaction { 
            background: #fff3cd; 
            border-left: 4px solid #ffc107; 
        }
        .internal-transaction { 
            background: #d1ecf1; 
            border-left: 4px solid #17a2b8; 
        }
        .amount-positive { color: #28a745; font-weight: bold; }
        .amount-negative { color: #dc3545; font-weight: bold; }
        .pilot-name { font-weight: bold; color: #495057; }
        .email-group { border: 2px solid #007bff; border-radius: 10px; }
        .summary-table td { vertical-align: top; }
        .detail-list { font-size: 0.9em; max-height: 100px; overflow-y: auto; }
    </style>";
/*</head>
<body>
*/
$pass .=    "<div class='container-fluid mt-4'>
        <div class='row'>
            <div class='col-12'>";                
                // Obtener lista de emails
                $email_list = getEmailList($link);
                
                // Mostrar formulario
                $pass .=generateFilterForm($email_list, $_POST);
                
                // Procesar análisis si se envió el formulario
                if (isset($_POST['analyze'])) {
                    $email_filter = $_POST['email_filter'] ?? '';
                    $min_amount = floatval($_POST['min_amount'] ?? 500000);
                    $hide_no_history = isset($_POST['hide_no_history']) && $_POST['hide_no_history'] === '1';
                    
                    // Ejecutar análisis
                    $pass .= analyzeEveHierarchy($link, $email_filter, $min_amount, $hide_no_history);
                }
                
/*                if ($connection) {
                    mysqli_close($connection);
                }
*/                

            $pass.= "</div>
        </div>
    </div>";
/*
</body>
</html>"
*/;
return $pass;
} // gemjerarquia

// Función para obtener usuarios únicos
function obtener_usuarios() {
    global $link;
    $query = "SELECT DISTINCT user_owner FROM PANELS ORDER BY user_owner ASC";
    $result = mysqli_query($link, $query);
    
    $usuarios = array();
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $usuarios[] = $row['user_owner'];
        }
    }    
    //mysqli_close($link);
    return $usuarios;
}

// Función para calcular días restantes
function calcular_dias_restantes($fecha_expiracion) {
    $hoy = new DateTime();
    $expira = new DateTime($fecha_expiracion);
    $diferencia = $hoy->diff($expira);
    
    if ($expira < $hoy) {
        return -$diferencia->days; // Días vencidos (negativo)
    }
    return $diferencia->days;
}

// Función para convertir skillpoints a millones
function skillpoints_millones($skillpoints) {
    if ($skillpoints === null || $skillpoints == 0) {
        return "0.00";
    }
    return number_format($skillpoints / 1000000, 2);
}

// Función para obtener panels con sus pilotos
function obtener_panels_con_pilotos($user_owner) {
    global $link;
    
    $query = "SELECT 
                p.idpanel,
                p.pseudo,
--                p.race as tribal,
                p.manualExpiration,
                p.panel_type as combutton,
                p.pilot_1, p.pilot_2, p.pilot_3,
                p.name_1, p.name_2, p.name_3,
                pi1.corporation as corp_1, pi1.skillpoints as sp_1,
                pi2.corporation as corp_2, pi2.skillpoints as sp_2,
                pi3.corporation as corp_3, pi3.skillpoints as sp_3
              FROM PANELS p
              LEFT JOIN PILOTS pi1 ON p.pilot_1 = pi1.toon_number
              LEFT JOIN PILOTS pi2 ON p.pilot_2 = pi2.toon_number
              LEFT JOIN PILOTS pi3 ON p.pilot_3 = pi3.toon_number
              WHERE refresh<>'NO' and p.user_owner = ?
              ORDER BY p.manualExpiration DESC";
    
    $stmt = mysqli_prepare($link, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_owner);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    $panels = array();
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $panels[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    //mysqli_close($link);
    return $panels;
}

// Función para obtener pilotos huérfanos (no en panels)
function obtener_pilotos_huerfanos() {
global $link;    
    $query = "SELECT DISTINCT pi.toon_number, pi.toon_name, pi.corporation, pi.skillpoints
              FROM PILOTS pi
              WHERE pi.toon_number NOT IN (
                  SELECT pilot_1 FROM PANELS WHERE pilot_1 IS NOT NULL AND pilot_1 != ''
                  UNION
                  SELECT pilot_2 FROM PANELS WHERE pilot_2 IS NOT NULL AND pilot_2 != ''
                  UNION
                  SELECT pilot_3 FROM PANELS WHERE pilot_3 IS NOT NULL AND pilot_3 != ''
              )
              AND pi.toon_name NOT LIKE '%Catalog%'
              ORDER BY pi.toon_name ASC";
    
    $result = mysqli_query($link, $query);
    $huerfanos = array();
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $huerfanos[] = $row;
        }
    }
    
    //mysqli_close($link);
    return $huerfanos;
}

// Función para generar card de personaje
function generar_card_personaje($toon_number, $nombre, $corporacion, $skillpoints) {
    if (empty($toon_number) || empty($nombre)) {
        return '<div class="col"><div class="card mb-2 card-fixed"><div class="card-body text-center p-2"><small class="text-muted">Not Assigned</small></div></div></div>';
    }
    
    $imagen_url = "https://images.evetech.net/characters/{$toon_number}/portrait";
    $sp_millones = skillpoints_millones($skillpoints);
    $corp_display = $corporacion ? $corporacion : 'Sin corporación';
    
    // Truncar corporación si es muy larga
    if (strlen($corp_display) > 20) {
        $corp_display = substr($corp_display, 0, 17) . '...';
    }

list($tribal,$pocket,$skillpoints2,$commen,$nums,$tqueue,$daysq,$longterm)=avalues319("select race,pocket6,unalloc,commentcard,numships,queue,daysq,longtermplan from PILOTS where toon_number=$toon_number");

$link2 = "?module=190&number=$toon_number";
$hang = "<a target='_blank' class='btn btn-dark btn-sm' href='$link2' role='button'>
    <i class='fas fa-space-shuttle mr-2'></i>
    Hangar <span class='badge badge-light'>$nums</span>
</a>";
if ($nums==100) $hang=str_replace("-dark","-danger",$hang);
if ($nums==0) $hang="";

if ($tqueue<>"[]"){
  $link2="?module=113v2&t=$toon_number";
  $hang .= " 
  <a target='_blank' class='btn btn-dark btn-sm' href='$link2' role='button'>
      <i class='fas fa-graduation-cap mr-2'></i>
      Queue <span class='badge badge-light'>$daysq</span>
  </a>";
}  
$hang .="&nbsp; <a target='_blank' class='btn btn-dark btn-sm' href='dossier/alphaassets.php?mode=alpha&t=$toon_number' role='button'>
    <i class='fas fa-star mr-2' style='display:none'></i>
    &#945 <span class='badge badge-light'>-</span>
</a>";

$spu_millones = skillpoints_millones($skillpoints2);
if ($tribal=='Caldari') $fondo="99ffff";
if ($tribal=='Minmatar') $fondo="ffcccc";
if ($tribal=='Gallente') $fondo="99ffcc";
if ($tribal=='Amarr') $fondo="ffffcc";
$thename=$nombre;

if ($longterm=="SELL")  $fondo="cc99ff";
if ($longterm=="SELL2")  $fondo="ccff99"; // semi lime
if ($longterm=="DESTROY_SELL")  $fondo="ffffff";

//die($fondo);
$shiproles=shiproles($toon_number);
$casc="<style type='text/css'>
<!--

.our-team .team_img{
    position: relative;
    overflow: hidden;
}
.our-team .team_img:after{
    content: '';
    width: 100%;
    height: 100%;
    background-color: rgba(255,255,255,0.2);
    position: absolute;
    bottom: -100%;
    left: 0;
    transition: all 0.3s ease 0s;
}
.our-team:hover .team_img:after{
    bottom: 0;
}
.our-team img{
    width: 100%;
    height: auto;
}

-->
</style>";
    //if (left($corp,1)=='1')
    
    $bak=$corporacion; 
     list($corporacion)=avalues319("select itemName from invUniqueNames where itemId='$corporacion'");
     
     $q="select ESI_NAME from ESI_CACHE where ESI_ID='$bak'";
     //die("c $corporacion bak $bak $q" );
     
     if ($corporacion=="") list($corp_display)=avalues319($q);
     //die("c $corporacion bak $bak $q" );    
     
     if ($corp_display === "") $corp_display=$bak;
    $statbar=barstats($toon_number); // color bar of stats    
	$commen2 ='<br /><small class="text-muted" style="font-size: 0.7rem;">Comment: </small>'."<br><b>$commen</b>";
	if (trim($commen)=="") $commen2="";
    return $casc."
    <div class='col' >
        <div class='card mb-2 card-fixed'  style='width: 18rem; background-color:#$fondo'>
            <div class='card-body text-center p-2 our-team'>               
            	<div class='team_img'>                          
                <img src='$imagen_url' alt='" . htmlspecialchars($nombre) . "' class='img-fluid rounded-circle mb-1 card-img-top' style='width: 200px; height: 200px;'>
                </div>".
                '<h2 class="card-title mb-1" style="font-size: 1.4rem;">' . htmlspecialchars($nombre) . '</h2>                
                <small class="text-muted d-block" style="font-size: 0.75rem;">ID: ' . $toon_number . " - group : <b><font color='black'>$pocket</font></b></small>".'
                <small class="text-muted d-block" style="font-size: 0.75rem;" title="' . htmlspecialchars($corporacion) . '">' . "<font color='black'>".htmlspecialchars($corp_display) . '</font></small>
                <small class="text-primary d-block font-weight-bold" style="font-size: 0.8rem;">' . $sp_millones . 'M SP'.
                " + $spu_millones"."M SP (free)"
                .'</small>
                <div class="mt-1">
                    '.$statbar.'                    
                    <div style="min-height: 15px; border: 1px dashed #ddd; padding: 3px; margin-top: 2px; font-size: 0.7rem;">
                        <!-- Espacio para comentarios -->
                        '.$hang.$shiproles.$commen2.'
                    </div>
                </div>               
            </div>
        </div>
    </div>';
}

// Función para renderizar panels con personajes
function generarcards() {
//$user_owner_seleccionado = isset($_POST['user_owner']) ? (int)$_POST['user_owner'] : null;
$usuarios = obtener_usuarios();
$javahider="";
    global $link;
    
    $html2 = "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>EVE Online - Grid of pilots</title>    
	<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css'>
    <style>
        .dias-vencido { color: #dc3545; font-weight: bold; }
        .dias-proximo { color: #ffc107; font-weight: bold; }
        .dias-normal { color: #28a745; font-weight: bold; }
        .panel-info {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .card-fixed {
            min-height: 450px;
            max-height: 450px;
        }
        .row-panels {
            margin-bottom: 15px;
        }
        .col-panel {
            max-width: 200px;
            min-width: 180px;
        }
    </style>
</head>
<body>";
$html = "<style>
        .dias-vencido { color: #dc3545; font-weight: bold; }
        .dias-proximo { color: #ffc107; font-weight: bold; }
        .dias-normal { color: #28a745; font-weight: bold; }
        .panel-info {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .card-fixed {
            min-height: 450px;
            max-height: 450px;
        }
        .row-panels {
            margin-bottom: 15px;
        }
        .col-panel {
            max-width: 200px;
            min-width: 180px;
        }
    </style>";
$usun=$_SESSION['character_id'] ?? 0;
$supa=1; // debug, supergroup
//echo "<pre>";
//print_r($_SESSION);
$user_owner_seleccionado=$usun;
//echo "</pre>";
//echo "<br><br><br><br><br><br><br><h1>U: $usun</h>";
//die();
$show="";
if ($usun>0){
if ($usun>0) $show=" style='display'none'";
if ($usun>0) $show=" d-none";
$sql="select email,evename from users where user_owner=$usun";
    list($mail2,$name2)=avalues319($sql);

$html .="<div class='container-fluid mt-4'>
        <h2 style='display:none' class='mb-4'>EVE Online - Grid of Pilots and accounts</h2>
        
        <!-- Formulario de selección de usuario -->
        <div class='row mb-4 $show ''>
            <div class='col-md-4'>
                <form method='POST' class='form-inline'>
                    <label for='user_owner' class='mr-2'>Usuario:</label>
                    <select name='user_owner' id='user_owner' class='form-control mr-2'>
                        <option value=''>Seleccionar usuario...</option>";
    
    foreach ($usuarios as $usuario) {
        $selected = ($user_owner_seleccionado == $usuario) ? " selected" : "";
        $html .= "<option value='$usuario'$selected>$usuario</option>";
    }
    
    $html .= "</select>
                    <button type='submit' class='btn btn-primary'>Mostrar</button>
                </form>
                </br />
            </div>
        </div>";
}// usun >0
    if ($user_owner_seleccionado>0) {
        $panels = obtener_panels_con_pilotos($user_owner_seleccionado);
        $javahider = "";
        $numpa = 0;
        
        $html .= "<!-- Tabla de panels -->
            <div class='row'>
                <div class='col-12'>
                    <div class='card'>
                        <div class='card-header'>
                            <h5>Panels of Supergroup: $supa for $usun</h5>
                        </div>
                        <div class='card-body'>";
        
        if (empty($panels)) {
            $html .= "<div class='alert alert-warning'>No se encontraron panels para este usuario.</div>";
        } else {
            foreach ($panels as $panel) {
                $accname = $panel["pseudo"];
                $numpa++;
                
                $javahider .= "<script>
function toggle$accname() {
 if( document.getElementById('$accname').style.display=='none' ){
   document.getElementById('$accname').style.display = '';
 }else{
   document.getElementById('$accname').style.display = 'none';
 }
}
</script>";
                
                $html .= "<div class='row row-panels' id='$accname'>
                            <div class='col-panel'>
                                <div class='panel-info h-100'>
                                    <h6 class='mb-1' style='font-size: 0.9rem;'>";
                
                $html .= "acc $numpa<br />" . htmlspecialchars($panel['pseudo']) . "</h6>";
                
                $tipo = $panel['combutton'];
                $dias_restantes = calcular_dias_restantes($panel['manualExpiration']);
                $clase_dias = '';
                $texto_dias = '';
                
                if ($dias_restantes < 0) {
                    $clase_dias = 'dias-vencido';
                    $texto_dias = 'Expired ' . abs($dias_restantes) . ' days ago';
                } elseif ($dias_restantes <= 7) {
                    $clase_dias = 'dias-proximo';
                    $texto_dias = $dias_restantes . ' days remaining';
                } else {
                    $clase_dias = 'dias-normal';
                    $texto_dias = $dias_restantes . ' days remaining';
                }
                
                $fecha_vencimiento = date('d/m/Y', strtotime($panel['manualExpiration']));
                $colort = "primary";
                if ($tipo == 'black') $colort = "dark";
                if ($tipo == 'red') $colort = "danger";
                
                $html .= "
                                    <small class='$clase_dias' style='font-size: 0.75rem;'>$texto_dias</small>
                                    <br>
                                    <small class='text-muted' style='font-size: 0.7rem;'>Expire: $fecha_vencimiento
                                    <br /><br /><a href='#' class='btn btn-$colort disabled' role='button' aria-disabled='true'>
                                            <i class='fa fa-home'></i> $tipo
                                    </a>
                                    <br /><br /><span onClick='toggle$accname();' style='cursor: pointer;'>Hide</span></small>
                                </div>
                            </div>
                            
                            <!-- Columnas de personajes -->
                            <div class='col'>
                                <div class='row'>";
                
                $html .= generar_card_personaje($panel['pilot_1'], $panel['name_1'], $panel['corp_1'], $panel['sp_1']);
                $html .= generar_card_personaje($panel['pilot_2'], $panel['name_2'], $panel['corp_2'], $panel['sp_2']);
                $html .= generar_card_personaje($panel['pilot_3'], $panel['name_3'], $panel['corp_3'], $panel['sp_3']);
                
                $html .= "
                                </div>
                            </div>
                        </div>
                        "; // aqui iba el hr
            }
        }
        
        $html .= "
                        </div>
                    </div>
                </div>
            </div>";
        
        // Agregar tabla de info
        $html .= tableinfo($user_owner_seleccionado);
        
        // Agregar scripts
        $html .= $javahider;
        
        // Pilotos huérfanos
        $huerfanos = obtener_pilotos_huerfanos();
        $html .= "
        <!-- Pilotos huérfanos -->
        <div class='row mt-4'>
            <div class='col-12'>
                <div class='card'>
                    <div class='card-header'>
                        <h5>Pilotos no asignados a Panels</h5>
                    </div>
                    <div class='card-body'>";
        
        if (empty($huerfanos)) {
            $html .= "<div class='alert alert-info'>Todos los pilotos están asignados a panels.</div>";
        } else {
            $html .= "<div class='row'>";
            foreach ($huerfanos as $huerfano) {
                $toon_id = $huerfano['toon_number'];
                $toon_name = htmlspecialchars($huerfano['toon_name']);
                $corporation = htmlspecialchars($huerfano['corporation'] ?: 'Sin corporación');
                $sp = skillpoints_millones($huerfano['skillpoints']);
                
                $html .= "
                            <div class='col-md-3 mb-3'>
                                <div class='card'>
                                    <div class='card-body text-center'>
                                        <img src='https://images.evetech.net/characters/$toon_id/portrait' 
                                             alt='$toon_name' 
                                             class='img-fluid rounded-circle mb-2' 
                                             style='width: 48px; height: 48px;'>
                                        <h6 class='card-title mb-1'>$toon_name</h6>
                                        <small class='text-muted d-block'>ID: $toon_id</small>
                                        <small class='text-muted d-block'>$corporation</small>
                                        <small class='text-primary d-block'>{$sp}M SP</small>
                                    </div>
                                </div>
                            </div>";
            }
            $html .= "</div>";
        }
        
        $html .= "
                    </div>
                </div>
            </div>";
    }
    
    $html .= "
    </div>";    
    return $html;
} // generarcards

function tableinfo($user_owner){
// se usa para borrar
$pass ="<script>
function confirmAction(url) {
    // La función 'confirm()' muestra la ventana de diálogo y retorna true si se pulsa 'Aceptar', y false si se pulsa 'Cancelar'.
    const isConfirmed = confirm('¿Está usted seguro? Esta acción es irreversible.');

    if (isConfirmed) {
        // Si el usuario confirma, navega a la URL en una nueva pestaña/ventana (target='_blank').
        window.open(url, '_blank');
    }
    
    // Al retornar 'false', evitas que el navegador siga la acción por defecto del 'href' (que es '#').
    return false; 
}
</script>";

if ($user_owner=="") return "Seleccione una cuenta";
$pilotos = obtener_pilotos_owner($user_owner);
$total_sp_owner = obtener_total_sp_owner($user_owner);
$usun=$_SESSION['character_id'] ?? 0;
$supa=1; // debug
$sql="select email,evename from users where user_owner=$supa";
    list($mail2,$name2)=avalues319($sql);
$pass .="<style>
        .table-sm th, .table-sm td {
            padding: 0.3rem;
            font-size: 0.85rem;
        }
        .btn-sm {
            padding: 0.2rem 0.4rem;
            font-size: 0.75rem;
        }
        .form-control-sm {
            height: calc(1.5em + 0.5rem + 2px);
            font-size: 0.75rem;
        }
        .text-right {
            text-align: right;
        }
    </style>
    <div class='container-fluid mt-3'>
        <div class='row'>
            <div class='col-12'>
                <div class='d-flex justify-content-between align-items-center mb-3'>
                    <h4>Pilots of supergroup: $supa for $usun</h4>
                    <div>
                        <small class='text-muted'>Number of pilots: ". count($pilotos)."</small> |
                        <small class='text-muted'>Total SP: ".skillpoints_millones($total_sp_owner).">M</small>
                    </div>
                </div>";
                
                if (empty($pilotos)) $pass .="<div class='alert alert-warning'>No se encontraron pilotos para el usuario $user_owner</div>";
                if (!empty($pilotos)){
                    $pass .="<div class='table-responsive'>
                        <table class='table table-sm table-striped table-bordered'>
                            <thead class='thead-dark'>
                                <tr>
                                    <th style=''>#</th>                                    
                                    <th style=''>Pilot Name</th>
                                    <th style=''>Last Update</th>
                                    <th style=''>Finish Queue</th>
                                    <th style=''>Skillpoints</th>
                                    <th style=''>% SP</th>
                                    <th style=''>Pocket</th>
                                    <th style=''>AccType</th>
                                    <th style=''>Main Data</th>
                                    <th style='width: 170px;'>Websites</th>
                                </tr>
                            </thead>
                            <tbody>";
 
                                $contador = 0;
                                foreach ($pilotos as $piloto){
                                    $contador++; 
                                    $porcentaje_sp = $total_sp_owner > 0 ? round(($piloto['skillpoints'] / $total_sp_owner) * 100, 1) : 0;
                                    $finish_info = dias_finish_queue($piloto['finishqueue']);
/*
                                    $fecha_actual = new DateTime();

// 3. Convertir la variable a un objeto DateTime (IMPORTANTE: PHP necesita este formato)
$fecha_finalizacion = new DateTime($piloto['finishqueue']);

// 4. Comparar las fechas
$finish_info2 = "si";
if ($fecha_finalizacion < $fecha_actual) {
    // Si la fecha de finalización es menor que la fecha actual (está en el pasado)
    $finish_info2 = "no";
}
*/ 

                                
                                    $pass .="<tr>
                                        <td class='text-center'>$contador</td>                                        
                                        <td>
                                            <strong>". htmlspecialchars($piloto['toon_name'])."</strong>
                                            <br>
                                            <small class='text-muted'>ID: ". $piloto['toon_number'] ."</small>";
                                            
                                        if ($piloto['DOB']) {
                                                $pass.= " - <small class='text-muted'>DOB : ". date('d/m/Y', strtotime($piloto['DOB']))."</small>";
                                            } else {
                                                $pass.= " - <small class='text-muted'>Sin datos</small>";
                                            }
                                            
                                        $pass .= "</td>
                                        <td>
                                            <small>". tiempo_desde_update($piloto['lastsaved']). "</small>
                                        </td>
                                        <td>
                                            <span class='". $finish_info['clase']."'>";
                                                if ($finish_info['dias']>0) $pass.=$finish_info['dias'];
                                                if ($finish_info['dias']==0) $pass .="<small class='text-muted'>Without Queue</small>";
                                                //if ($finish_info2=="no") $pass.="xxxx";
                                            $pass.="</span>
                                        </td>
                                        <td class='text-right'>
                                            <strong>". skillpoints_millones($piloto['skillpoints'])."M</strong>
                                        </td>
                                        <td class='text-right'>
                                            <strong>$porcentaje_sp%</strong>
                                        </td>
                                        <td>". ($piloto['pocket6'])."
                                        <td>";                                             
                                            if ($piloto['acctype']) {
                                                $pass.= $piloto['acctype'];
                                            } else {
                                                $pass.= "<small class='text-muted'>Sin datos</small>";
                                            }                                            
                                        $pass .="</td>
                                        <td>
                                            ".generar_botones_acctype($piloto)."
                                        </td>
                                        <td>
                                            ".generar_combo_sitios($piloto['toon_number'])."
                                        </td>
                                    </tr>";
                                 
                                
                                } 
                            $pass .="</tbody>
                        </table>
                    </div>
                    <!-- Resumen -->
                    <div class='row mt-3'>
                        <div class='col-12'>
                            <div class='card'>
                                <div class='card-body'>
                                    <h6>Data of supergroup $user_owner</h6>
                                    <div class='row'>
                                        <div class='col-md-3'>
                                            <strong>Number of Pilots:</strong>". count($pilotos)."
                                        </div>
                                        <div class='col-md-3'>
                                            <strong>Total Skillpoints:</strong>".
                                            skillpoints_millones($total_sp_owner)."M
                                        </div>
                                        <div class='col-md-3'>
                                            <strong>Average SP/ Pilot: </strong>"; 
                                            $average = count($pilotos) > 0 ? $total_sp_owner / count($pilotos) : 0;
                                            $pass .= skillpoints_millones($average) ."M";
                                        $pass .="</div>
                                        <div class='col-md-3'>
                                            <strong>Date of report:</strong> ".date('d/m/Y H:i')."
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>";
                }
            $pass .="</div>
        </div>
    </div>";
return $pass;
} // tableinfo

// Función para calcular tiempo transcurrido desde last update
function tiempo_desde_update($lastsaved) {
    if (empty($lastsaved)) {
        return "Sin datos";
    }
    
    $ahora = new DateTime();
    $ultima = new DateTime($lastsaved);
    $diferencia = $ahora->diff($ultima);
    
    $dias = $diferencia->days;
    $horas = $diferencia->h;
    
    if ($dias > 0) {
        return $dias . " días, " . $horas . " horas";
    } else {
        return $horas . " horas";
    }
}

// Función para calcular días restantes finish queue
function dias_finish_queue($finishqueue) {
    if (empty($finishqueue)) {
        return array('dias' => 0, 'clase' => '');
    }
    
    $ahora = new DateTime();
    $finish = new DateTime($finishqueue);
    $diferencia = $ahora->diff($finish);
    
    if ($finish < $ahora) {
        return array('dias' => 0, 'clase' => '');
    }
    
    $dias_totales = $diferencia->days;
    $horas = $diferencia->h;
    
    // Si es menos de 1 día (menos de 24 horas total)
    if ($dias_totales == 0) {
        $clase = 'text-danger font-weight-bold';
        $texto = $horas . " horas";
    } else {
        $clase = '';
        $texto = $dias_totales . " días";
    }
    
    return array('dias' => $texto, 'clase' => $clase);
}

// Función para obtener pilotos de un owner específico
function obtener_pilotos_owner($user_owner) {    
    global $link;
    //$sql="select email from users where user_owner=$user_owner";
    //die("u: $user_owner");    
    //list($mail)=avalues319($sql);    
	$mail=$user_owner;
    // Primero obtener pilotos del owner (desde PANELS)
    $query = "SELECT DISTINCT 
                p.toon_number, p.toon_name, p.DOB, p.lastsaved, 
                p.finishqueue, p.skillpoints, p.acctype
              FROM PILOTS p
              WHERE p.toon_number IN (
                  SELECT pilot_1 FROM PANELS WHERE user_owner = ? AND pilot_1 IS NOT NULL AND pilot_1 != ''
                  UNION
                  SELECT pilot_2 FROM PANELS WHERE user_owner = ? AND pilot_2 IS NOT NULL AND pilot_2 != ''
                  UNION
                  SELECT pilot_3 FROM PANELS WHERE user_owner = ? AND pilot_3 IS NOT NULL AND pilot_3 != ''
              )
              AND p.toon_name NOT LIKE '%Catalog%'
              ORDER BY p.finishqueue DESC,skillpoints desc";
    $query = "SELECT DISTINCT 
                p.toon_number, p.toon_name, p.DOB, p.lastsaved, 
                p.finishqueue, p.skillpoints, p.acctype, p.pocket6
              FROM PILOTS p
              WHERE p.parent_toon_number='$user_owner' and p.toon_name NOT LIKE '%Catalog%'
              ORDER BY p.finishqueue DESC";
    //          die($query);
    $result = mysqli_query($link, $query);
    
    $pilotos = array();
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $pilotos[] = $row;
        }
   }    
    //mysqli_close($link);
    return $pilotos;
}

// Función para calcular total de skillpoints del owner
function obtener_total_sp_owner($user_owner) {
    global $link;
    $sql="select email from users where user_owner=$user_owner";
    list($mail)=avalues319($sql);
    $query = "SELECT SUM(p.skillpoints) as total_sp
              FROM PILOTS p
              WHERE p.toon_number IN (
                  SELECT pilot_1 FROM PANELS WHERE user_owner = ? AND pilot_1 IS NOT NULL AND pilot_1 != ''
                  UNION
                  SELECT pilot_2 FROM PANELS WHERE user_owner = ? AND pilot_2 IS NOT NULL AND pilot_2 != ''
                  UNION
                  SELECT pilot_3 FROM PANELS WHERE user_owner = ? AND pilot_3 IS NOT NULL AND pilot_3 != ''
              )
              AND p.toon_name NOT LIKE '%Catalog%'";
    $query = "SELECT SUM(p.skillpoints) as total_sp                
              FROM PILOTS p
              WHERE p.supergroup='$mail' and p.toon_name NOT LIKE '%Catalog%'
              ORDER BY p.finishqueue DESC";
              //die($query);
    $result = mysqli_query($link, $query);    
    $row = mysqli_fetch_assoc($result);    
    //mysqli_close($link);    
    return $row['total_sp'] ?: 0;
}

// Función para generar botones acctype
function generar_botones_acctype($piloto) {
$number=$piloto['toon_number'];
// esta funcion se usa para borrar
list($jobs,$planets,$standings,$queue,$vencido)=avalues319("select jobs,planets,standings,queue, IF(
        NOW() > finishqueue, 
        'VENCIDO',
        'NORMAL'
    ) from PILOTS where toon_number=$number");
    
    $botones = "<a href='dossier/alphaassets.php?mode=assets&who=$number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Assets'>A</a>";
    if ($standings<>'[]') $botones .= "<a href='?module=111&t=$number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Standings'>S</a>";
    if ($queue<>'[]' and $vencido<>'VENCIDO') $botones .= "<a href='?module=113v2&t=$number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Queue'><i class='fas fa-tachometer-alt'></i></a>";
    if ($queue<>'[]' and $vencido=='VENCIDO') $botones .= "<a href='?module=113v2&t=$number' target='_blank' class='btn btn-danger btn-sm mr-1' role='button' title='Queue'><i class='fas fa-tachometer-alt'></i></a>";
                                              $botones .= "<a href='dossier/jack.php?t=$number'           target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Knife'> <i class='fas fa-address-card'></i></a>"; // knife
                                              //$botones .= "<a href='dossier/alphaassets.php?mode=alpha&t=$number'           target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Alpha'> &#945</a>"; // alpha 122
                                              
// inicia borrar
$botones .= "<a href='#' 
              onclick='return confirmAction(\"?module=settings&t=$number&what=delT\")'
              target='_blank' 
              class='btn btn-outline-secondary btn-sm mr-1' 
              role='button' 
              title='Borrar'> 
              <i class='fa fa-trash'></i>
            </a>";
// termina borrar                                                          
                                              
                                              //$opciones .="<option value='?module=122&t=$toon_number'>Alpha</option>"; // alpha
    if ($planets<>'[]') $botones .= "<a href='?module=127&t=$number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Pi Planets'>Pi</a>";
    return $botones;
}

// Función para generar combo de sitios web
function generar_combo_sitios($toon_number) {
$botones="";
list($toon_name)=avalues319("select toon_name from PILOTS where toon_number='$toon_number'");
$toon_name=strtolower(str_replace(" ","_",$toon_name));
$botones .= "<a href='?module=400&t=$toon_number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Plans'>400</a>";
$botones .= "<a href='https://forums.eveonline.com/u/$toon_name/summary' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Forum'>Forum</a>";
$botones .= "<a href='https://zkillboard.com/character/$toon_number/' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='Killboard'>ZK</a>";
$botones .= "<a href='https://evewho.com/character/$toon_number' target='_blank' class='btn btn-outline-secondary btn-sm mr-1' role='button' title='who'>?</a>";
 
return $botones;
}

function barstats($char){
list($wallet,$security,$respecs,$int,$mem,$cha,$per,$wil,$queue)=avalues319("select wallet,security,remaps,inteli,mem,cha,per,wil,queue from PILOTS where toon_number='$char'");
if ($wallet=='') die("error (wallet) in toon $char $name3");
//if ($corpID=='') die("error (corp) in toon $char $name3");
$stats = "Int:$int "; 
$stats .= "Mem:$mem ";
$stats .= "cha:$cha ";
$stats .= "per:$per ";
$stats .= "wil:$wil";

$back=" style='background-color:white'";
$intmem=$int+$mem;
$perwil=$per+$wil;
$intper=$per+$int;
$chaint=$cha+$int;
//echo ("intmem:$intmem; perwil:$perwil<hr>");
if ($intmem>$perwil){
  if ($int>$mem) $back =" style='background-color:#cccccc'";
  if ($int<$mem) $back=" style='background-color:pink'";
} // int chars
if ($intmem<$perwil){  
  if ($per>$wil) $back=" style='background-color:cyan'";
  if ($per<$wil) $back=" style='background-color:yellow'";   
} // wil chars
if ($intper>$intmem and $intper>$perwil) $back=" style='background-color:lime'";  
if ($chaint>$intmem and $chaint>$perwil) $back=" style='background-color:#cc99cc'"; // 99 33ff
$stats="<div $back><small>$stats</small></div>";
return $stats;
} // barstats
/*
function dom2020(){
$campos="dominio,registered ,	expiration ,	registrar ,	showit ,	iscustomer , 	type";
$query="select $campos from dominios2020 where showit='YES' and iscustomer='NO'";
//$query="select $campos from dominios2020";
//$query="select $campos from dominios2020 where iscustomer='NO'";

$result = dbx_query($query); SQL_ERROR ("Error account:",$query);
$pass=dbx_result_all($result,"dominios") ;
dbx_free_result($result);
$pass .=explica_campo("right(dominio,3)","dominios2020","TLD");
$pass .=explica_campo("registrar","dominios2020","Registrar");
$pass .=explica_campo("type","dominios2020","type");
$pass .=explica_campo("servidores","dominios2020","Servidores");
return "$pass"; 
}

FUNCTION explica_campo($campo,$tabla,$titulo){ // depura no se esta usando
$ff=$campo;
$cad="SELECT $ff AS Description, COUNT( $ff ) AS qty,
 ((COUNT( $ff ) / 
 (SELECT COUNT( $ff ) FROM $tabla as selecttwo )
 ) * 100) AS porcentaje
  FROM $tabla as sqlservertrick GROUP BY $ff ORDER BY qty DESC ,Description ASC";
$result = dbx_query($cad); SQL_ERROR ("Error account:",$cad);
$pass=dbx_result_all($result,$titulo) ;
dbx_free_result($result);
return $pass;
} //explica_campo

*/
function charskills_and_queue(){
    $loadim  = $_SESSION['loading'] ?? "";
    $acctype = "Maybe Omega";

    // Pick the pilot: the 't' parameter takes priority in module 113
    $m = $_GET['module'] ?? "";
    if ($m === '113v2') $m = '113';

    if ($m === '113' && isset($_GET['t'])) {
        $who = (int)$_GET['t'];
    } else {
        $who = (int)valpilot("CHECK");
        if ($loadim <> '') $who = (int)$loadim;
    }

    // Make sure the pilot exists and belongs to the user
    list($name, $pilot, $data_skills, $data_queue) = avalues319("select toon_name, email_pilot, skills, queue from PILOTS where toon_number='$who'");

    if ($name == "") die("The pilot $who " . PilotfromInternet($who) . " is not in the database");

    // Outside module 113, check ownership
    if ($m !== '113' && $pilot <> $_SESSION['youremail']) {
        die("The pilot $who " . PilotfromInternet($who) . " is in the database but belong to other user");
    }

    // Loading mode with no data: exit silently
    if ($loadim <> '' && $data_skills == '[]' && $data_queue == '[]') return;

    $nameH = htmlspecialchars($name);

    // True if the stored value is an error message instead of JSON
    $damaged = function($raw) {
        foreach (['Timeout contacting tranquility', 'unexpected end of JSON', '504 Gateway'] as $err) {
            if (strpos($raw, $err) !== false) return true;
        }
        return false;
    };

    $output  = css() . topboot() . "<h3>Skills and Queue of $nameH</h3>";
    $output .= "<div class='container-fluid'><div class='row'>";

    // ==================== LEFT COLUMN: SKILLS ====================
    $output .= "<div class='col-md-6'>";
    $output .= "<h4>Skills</h4>";

    if ($data_skills == '[]' || $data_skills == '') {
        $output .= "<div class='alert alert-warning'>No skill data available for $nameH</div>";
    } else {
        $skills = json_decode(stripslashes($data_skills), true);

        if ($damaged($data_skills) || !is_array($skills)) {
            $output .= "<div class='alert alert-danger'>Sorry, skills are damaged in pilot $nameH, try update him/her later</div>";
        } else {
            $acctype = "Maybe Omega";
            $theid   = randomstring();
            $pass_skills = "<table id='$theid' class='sortable hovertable'>"
                         . "<tr><th>#</th><th>Skill</th><th>Description</th><th>SP</th><th>Category</th><th>Active</th><th>Trained</th><th>Alpha Max</th></tr>";
            $csh = 0;
            $sp  = 0;

            doaction("delete from EVE_CHARSKILLS where toon='$who'", "error checking skills $who");

            $unalloc = (int)($skills['unallocated_sp'] ?? 0);
            doaction("update PILOTS set unalloc=$unalloc where toon_number='$who'", "error updating unallocated sp");

            foreach (($skills['skills'] ?? []) as $item) {
                $csh++;
                $what    = (int)($item['skill_id'] ?? 0);
                $skillSp = (int)($item['skillpoints_in_skill'] ?? 0);
                $trained = (int)($item['trained_skill_level'] ?? 0);
                $active  = (int)($item['active_skill_level'] ?? 0);

                $description = description($what);

                $pass_skills .= "<tr><th>$csh</th>";
                $pass_skills .= "<td><a href='./dossier/skill_detail.php?module=dt2&what=$what' target='_blank'>$what</a></td>";
                $pass_skills .= "<td>$description</td>";
                if ($description == '') $description = 'n/a';

                $pass_skills .= "<td>$skillSp</td>";
                $pass_skills .= typea($what);
                $pass_skills .= "<td>$active</td>";
                $pass_skills .= "<td>$trained</td>";

                $sp += $skillSp;
                if (abs($trained - $active) > 0) $acctype = 'Alpha';

                list($maxalpha) = avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$what'");
                $pass_skills .= "<td>$maxalpha</td></tr>";
                if ($active > (int)$maxalpha && $active > 0) $acctype = "Omega";

                $descSql = addslashes($description);
                doaction("insert into EVE_CHARSKILLS (toon,typeID,skillpoints,rank,description) values
                          ('$who',$what,$skillSp,$trained,'$descSql')", "error inserting skills");
            }

            $pass_skills .= "</table>";
            $pass_skills .= "<br>Skill points: $sp<br>Account type: $acctype";

            $output .= $pass_skills;
        }
    }

    $output .= "</div>"; // end left column

    // ==================== RIGHT COLUMN: QUEUE ====================
    $output .= "<div class='col-md-6'>";
    $output .= "<h4>Training Queue</h4>";

    if ($data_queue == '[]' || $data_queue == '') {
        $output .= "<div class='alert alert-warning'>No queue data available for $nameH</div>";
    } else {
        $queue = json_decode(stripslashes($data_queue), true);

        if ($damaged($data_queue) || !is_array($queue)) {
            $output .= "<div class='alert alert-danger'>Sorry, the queue is damaged in pilot $nameH, try update him/her later</div>";
        } else {
            $pass_queue = "<table class='hovertable'><tr><th>#</th><th style='display:none'>queue position</th><th>real Sp</th><th>goal sp</th><th>skill</th><th>Description</th><th>start date</th><th>End date</th><th colspan=3>stats</th><th>Need SP</th><th>alpha/Omega</th></tr>";
            $csh      = 0;
            $totalsum = 0;
            $extra2   = 0;
            $todate   = "";

            foreach ($queue as $item) {
                $csh++;
                $skillId  = (int)($item['skill_id'] ?? 0);
                $startSp  = (int)($item['level_start_sp'] ?? 0);
                $endSp    = (int)($item['level_end_sp'] ?? 0);
                $finished = (int)($item['finished_level'] ?? 0);

                $pass_queue .= "<tr><th>$csh</th>";
                $pass_queue .= "<td style='display:none'>" . (int)($item['queue_position'] ?? 0) . "</td>";
                $pass_queue .= "<td>$startSp</td>";
                $pass_queue .= "<td>$endSp<br /><br />lvl $finished</td>";
                $pass_queue .= "<td>$skillId</td>";

                list($maxalpha) = avalues319("select EXPANDED from ALPHA_CLONES where numberskill='$skillId'");
                $maxalpha = ($maxalpha == "") ? 0 : (int)$maxalpha;
                $acctype  = ($finished > $maxalpha) ? "Omega" : "Alpha";

                $pass_queue .= "<td>" . description($skillId) . "</td>";

                $fromdate = (string)($item['start_date'] ?? '');
                $todate   = left((string)($item['finish_date'] ?? ''), 19);
                $pass_queue .= "<td>" . htmlspecialchars($fromdate) . "</td>";
                $pass_queue .= "<td>" . htmlspecialchars($todate) . "</td>";

                $from_time = $fromdate !== '' ? strtotime($fromdate) : 0;
                $to_time   = $todate   !== '' ? strtotime($todate)   : 0;

                $minutes2  = round(abs($to_time - $from_time) / 60, 2);
                $totalsum += $minutes2;

                // Skill points per hour
                $sph = 0;
                if ($minutes2 > 0) {
                    $sph = ($endSp - $startSp) / ($minutes2 / 60);
                }

                // Elapsed time as days / hours / minutes
                $mins  = $minutes2;
                $days  = (int)floor($mins / 1440);
                $mins -= $days * 1440;
                $hours = (int)floor($mins / 60);
                $mins -= $hours * 60;
                $mins  = number_format($mins, 0);
                if ($hours < 10) $hours = "0$hours";

                $elapsed = "$days d $hours h $mins m";
                if (left($elapsed, 3) == "0 d")  $elapsed = str_replace("0 d", "", $elapsed);
                if (left($elapsed, 4) == "00 h") $elapsed = str_replace("00 h", "", $elapsed);

                $pass_queue .= "<td>$elapsed</td>";
                $pass_queue .= "<td>" . cfdinumbers($sph) . "</td>";

                list($realhave) = avalues319("select max(skillpoints) from EVE_CHARSKILLS where typeID='$skillId' and toon=$who");
                $realhave = (int)$realhave;
                $extrapoints = ($realhave < $startSp) ? ($endSp - $startSp) : ($endSp - $realhave);
                if ($sph > 1500) $acctype = "OMEGA";

                $pass_queue .= typea($skillId);
                $pass_queue .= "<td align=right>$extrapoints</td>";
                $extra2 += $extrapoints;
                $pass_queue .= "<td>$acctype</td>";
                $pass_queue .= "</tr>";
            }

            $pass_queue .= "</table>";

            $hoursTotal = $totalsum / 60;
            $pass_queue .= "<br>Total Hours: " . cfdinumbers($hoursTotal)
                         . "<br>Days: " . cfdinumbers($hoursTotal / 24);
            if ($extra2 > 0) {
                $pass_queue .= "<br>Extra Points when finished: $extra2 | " . cfdinumbers($extra2 / 1000000) . "m sp";
            }

            // Save when the queue finishes
            if ($todate == '') $todate = '0000-00-00';
            $todateSql = addslashes($todate);
            doaction("update PILOTS set finishqueue='$todateSql' where toon_number='$who'", "error calculating queue");

            $output .= $pass_queue;
        }
    }

    $output .= "</div>";       // end right column
    $output .= "</div></div>"; // end row and container
    $output .= footboot();

    // Outside loading mode, print the page; otherwise return it
    if ($loadim == '') die($output);
    return $output;
}
// charskills_and_queue
?>
