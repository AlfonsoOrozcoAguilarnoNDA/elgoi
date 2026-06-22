<?php
session_start();
require "../config.php";
include_once '../ui_functions.php';
echo ui_header('Apex Roster');

// Configurar zona horaria de México
date_default_timezone_set('America/Mexico_City');

check_authorization();
echo ui_generate_navbar();

//function dreamteam(){

echo "<div style='overflow-x: auto;' class='row flex-row flex-nowrap ml-3 mt-4 pb-4 pt-2'><div>";

$pocket="";
$usuario=$_SESSION['character_id']; // debugm is the supergroup
set_time_limit(60);
echo Showdashboard_skills($pocket,$usuario);

echo showdashboard_diplomatic($pocket,$usuario); // no necesita filtro por pocket 


echo "</span>";
echo "</div>";


//die();
echo ui_footer();
// } // dreamteam

function Showdashboard_diplomatic($pocket,$usuario){ // no necesita pocket
//die($_SESSION['character_id']);

//print_r($_SESSION);
global $link;
$cad="";
// emmpezamos a llenar un valor para simplificar laconsulta
$sql = "SELECT toon_number,toon_name,skillpoints from PILOTS where parent_toon_number	='$usuario' order by skillpoints desc";
//$cad .= "$sql";
//die($sql);
$cadenapilotos="";
if ($result = mysqli_query($link, $sql)) {
  while ($obj = mysqli_fetch_object($result)) {
    
    $pilotos[]="'$obj->toon_name'";    
    
  }
  mysqli_free_result($result);
}
$cadenapilotos=implode(",",$pilotos);
//return "$cadenapilotos";
// valor llenado.
$filtropocket="";
if ($pocket<>"") {
   $filtropocket= " and pocket6='$pocket'";  
}
$sql = "SELECT toon_number,toon_name,skillpoints,race from PILOTS where parent_toon_number	='$usuario'
and toon_name in ($cadenapilotos)
$filtropocket order by NPC_rep desc";

$cad .= "<table class='table-bordered'><tr>";
$dash1="";
if ($result = mysqli_query($link, $sql)) {
  $pil=0;
  while ($obj = mysqli_fetch_object($result)) {
    $visible=0;
    
    $rudimento ="<br /><table style='width:360px' class='table table-bordered'>
    <thead class='thead-dark'>
    <tr><th>Row</th><th>Corp</th><th>rep</th><th>Description</th></tr>
    </thead>";
    $sql="select * from DIPLOMATIC where pilot_name='$obj->toon_name'  order by reputation desc";
if ($result2 = mysqli_query($link, $sql)) {
  $ren=0;
  $total=0;
  while ($obj2 = mysqli_fetch_object($result2)) {
        $total += $obj2->reputation;
    $enlace="<a target='_blank' href='index.php?module=dp2&what=$obj2->target'>$obj2->target</a>";
    $sty="";
    list($maxh)=avalues319b("select max(reputation) from DIPLOMATIC where pilot_name in ($cadenapilotos) and target='$obj2->target'");
    
    
          
      $id=$obj2->target;
      $candistri="";
      $lopp="";
      // if exist level4 distribution missions then  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000003')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000004')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000005')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000010')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000011')  $candistri=" style='background-color:ffc0cb'";      
      if ($id=='1000012')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000013')  $candistri=" style='background-color:ffc0cb'";      
      if ($id=='1000017')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000019')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000020')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000022')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000023')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000026')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000027')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000028')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000029')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000030')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000032')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000033')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000036')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000039')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000041')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000045')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000046')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000048')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000051')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000053')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000058')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000060')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000061')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000069')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000073')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000084')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000086')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000107')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000109')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000111')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000115')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000165')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000167')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000168')  $candistri=" style='background-color:ffc0cb'";
      if ($id=='1000169')  $candistri=" style='background-color:ffc0cb'";
      
      // if NOT exist level4 distribution missions then  $candistri=" style='background-color:red'";
        if ($id=='1000002')  $candistri=" style='background-color:red'";
        if ($id=='1000013')  $candistri=" style='background-color:red'";
        if ($id=='1000006')  $candistri=" style='background-color:red'";
        if ($id=='1000031')  $candistri=" style='background-color:red'";
        if ($id=='1000035')  $candistri=" style='background-color:red'";
        if ($id=='1000038')  $candistri=" style='background-color:red'";
        if ($id=='1000040')  $candistri=" style='background-color:red'";
        if ($id=='1000042')  $candistri=" style='background-color:red'";
        if ($id=='1000043')  $candistri=" style='background-color:red'";
        if ($id=='1000054')  $candistri=" style='background-color:red'";
        if ($id=='1000082')  $candistri=" style='background-color:red'";
        if ($id=='1000096')  $candistri=" style='background-color:red'";
        if ($id=='1000102')  $candistri=" style='background-color:red'";
        if ($id=='1000104')  $candistri=" style='background-color:red'";
        if ($id=='1000154')  $candistri=" style='background-color:red'";
        
        $lopp="";
        if  ( $obj2->reputation> 5.90) $lopp=" style='background-color:cyan'";
        if ( $obj2->reputation < -5) $lopp=" style='background-color:red'";
            
    $rower= specialcolorNPC($id);
    if ($maxh==$obj2->reputation){
      $ren++;
      $visible += $obj2->reputation;      
      // $sty=" style='background-color:#ffc0cb'";
      $rudimento .= "<tr><th $candistri>$ren</th><td>$enlace</td><td $lopp>$obj2->reputation</td><td $rower>$obj2->target_description</td></tr>";
    }
  }
  $rudimento .="</table>";
   
  if($total>0 and $visible>0) {
    $pil ++;
    
    $cad .= "<td style='width:400px' valign='top'><center><h1>$pil</h1><b>$obj->toon_name</b><br >$rudimento<br>Visible: $visible<br />Total=$total</center></td>";
    $dash1 .="<tr><th>$pil</th><th>$obj->toon_name</th><td>$visible</td><td>$total</td></tr>";
  }  
  $sql="update PILOTS set NPC_rep=$total where toon_name='$obj->toon_name'";
  list($dummy)=avalues319b($sql);
  mysqli_free_result($result2);
  $rudimento="";
    
  $cad .= "</td>";
  //$cad .= "</td></tr></table>";
}  
}
$cad.= "</tr></table>
<h3><i class='fas fa-trophy'></i> Diplomatic Roster (Npc made your pilots unique)</h3>
<table style='width:360px' class='table table-secondary'>
<thead class='thead-dark'>
<tr><th>place</th><th>Pilot Name</th><th>Visible</th><th>Total</th></tr></thead>
$dash1</table>";
mysqli_free_result($result);
//$cad .= "</ol>";
}
// valor llenado.
return $cad;
} // Showdashboarddiplomatic

function Showdashboard_skills($pocket,$usuario){
global $link;
$cad="";
// en ocasiones, por detalles de eve algunos skills tiene un valor masalto e imposible.esta es una correccion
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=1024000 where typeID=2495 and Skillpoints>1024000");
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=256000 where typeID=3301 and Skillpoints>256000");
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=256000 where typeID=3380 and Skillpoints>256000");
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=256000 where typeID=3413 and Skillpoints>256000");
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=512000 where typeID=3394 and Skillpoints>512000");
list($dummy)=avalues319b("update EVE_CHARSKILLS set skillpoints=768000 where typeID=3411 and Skillpoints>768000");
// emmpezamos a llenar un valor para simplificar laconsulta
$sql = "SELECT toon_number,toon_name,skillpoints from PILOTS where parent_toon_number	='$usuario'";
//$cad .= "$sql";
if ($result = mysqli_query($link, $sql)) {
  $commander= $_SESSION['pilot_name'];
  while ($obj = mysqli_fetch_object($result)) {
    $cad2="update EVE_CHARSKILLS set toon_name='$obj->toon_name',PILOT_SP=$obj->skillpoints,owner_email='$commander' where toon='$obj->toon_number'";
    //$cad .= "<li>$obj->toon_number $obj->toon_name $cad";
    list($dummy)=avalues319b($cad2);
  }
  mysqli_free_result($result);
}
// valor llenado.
$filtropocket="";
if ($pocket<>"") {
   $filtropocket= " and pocket6='$pocket'";  
}
$usuario= $_SESSION['character_id']; // fleete cpommander  
$sql = "SELECT toon_number,toon_name,skillpoints,race from PILOTS where parent_toon_number	='$usuario' $filtropocket order by skillpoints desc";

$cad .= "<table class='table table-bordered'><tr>";
$dash1="";
if ($result = mysqli_query($link, $sql)) {
  $pil=0;
  while ($obj = mysqli_fetch_object($result)) {
    $visible=0;   
    
    
    $rudimento ="<br /><table class='table table-bordered'>
    <thead class='thead-dark'>
    <tr><th>Row</th><th>Skill</th><th>SP</th><th>rank</th><th>Description</th><th>Alpha<br>max</th><th>Family<br>skill</th></tr>
    </thead>";
    $sql="select * from EVE_CHARSKILLS where toon='$obj->toon_number'  order by typeID";
if ($result2 = mysqli_query($link, $sql)) {
  $ren=0;
  $usuario=$_SESSION['pilot_name']; // name of commander
  while ($obj2 = mysqli_fetch_object($result2)) {    
    list($quien)=avalues319b("select toon_name from EVE_CHARSKILLS where typeID=$obj2->typeID and owner_email='$usuario' order by skillpoints desc,PILOT_SP desc");
    if ($quien==$obj->toon_name and $obj2->rank > 0){
      $ren++;
      $visible += $obj2->skillpoints;
      $enlace="<a target='_blank' href='abyss/skill_detail.php?module=dt2&what=$obj2->typeID'>$obj2->typeID</a>";
      list($maxa)=avalues319b("select EXPANDED from ALPHA_CLONES where numberskill=$obj2->typeID");
      $sty="";
      if ($maxa>0) $sty=" style='background-color:#ffc0cb'";
      $typecolor=typeA($obj2->typeID);    
      $rudimento .= "<tr><th $sty>$ren</th><td>$enlace</td><td>$obj2->skillpoints</td><td>$obj2->rank</td><td>$obj2->Description</td><td>$maxa</td>$typecolor</tr>";
    }
  }
  $rudimento .="</table>";
  
  $mill=($obj->skillpoints)/1000000;
  if ($mill>0) $mill=number_format($mill,2); 
  if($visible>0) {
    $pil ++;
    $ndays=number_format($visible/2700/24,2);
    $cad .= "<td><center><h1>$pil</h1><b>$obj->toon_number $obj->toon_name $mill"."m SP $obj->race</b></center>$rudimento<br>Visible: $visible<br />days(2700sp/h)=$ndays</td>";
    $dash1 .="<tr><th>$pil</th><th>$obj->toon_name</th><td>$visible</td><td>$ndays</td></tr>";
  }  
  
  mysqli_free_result($result2);
  $rudimento="";
    
    
  }
  $cad .= "</td>";
  //$cad .= "</td></tr></table>";
  
}
$cad.= "</tr></table>";
$cad.= "<h3><i class='fas fa-graduation-cap'></i> Dream Team (Skills made your pilots unique)</h3><table  style='width:360px' class='table table-primary'><thead class='thead-dark'>
<tr><th>place</th><th>Pilot Name</th><th>Unique SP</th><th>Days</th></tr></thead>
$dash1</table>";
mysqli_free_result($result);
//$cad .= "</ol>";
}
// valor llenado.
return $cad;
} // Showdashboardskills
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

?>
