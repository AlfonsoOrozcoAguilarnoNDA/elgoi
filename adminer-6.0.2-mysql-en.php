<?php
/** Adminer - Compact database management
* @link https://www.adminer.org/
* @author Jakub Vrana, https://www.vrana.cz/
* @copyright 2007 Jakub Vrana
* @license https://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
* @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
* @version 6.0.2
*/namespace
Adminer;const
VERSION="6.0.2";error_reporting(24575);set_error_handler(function($Mc,$Oc){return!!preg_match('~^Undefined (array key|offset|index)~',$Oc);},E_WARNING|E_NOTICE);$rd=!preg_match('~^(unsafe_raw)?$~',ini_get("filter.default"));if($rd||ini_get("filter.default_flags")){foreach(array('_GET','_POST','_COOKIE','_SERVER')as$X){$Hk=filter_input_array(constant("INPUT$X"),FILTER_UNSAFE_RAW);if($Hk)$$X=$Hk;}}$_COOKIE=array_filter($_COOKIE,'is_scalar');if(function_exists("mb_internal_encoding"))mb_internal_encoding("8bit");function
connection($f=null){return($f?:Db::$instance);}function
adminer(){return
Adminer::$instance;}function
driver(){return
Driver::$instance;}function
connect(){$Hb=adminer()->credentials();$J=Driver::connect($Hb[0],$Hb[1],$Hb[2]);return(is_object($J)?$J:null);}function
idf_unescape($u){if(!preg_match('~^[`\'"[]~',$u))return$u;$kf=substr($u,-1);return
str_replace($kf.$kf,$kf,substr($u,1,-1));}function
q($Q){return
connection()->quote($Q);}function
idx($wa,$x,$j=null){return($wa&&array_key_exists($x,$wa)?$wa[$x]:$j);}function
number($X){return
preg_replace('~[^0-9]+~','',$X);}function
int_type(){return'(tiny|small|medium|big)?int(eger|\d)?';}function
number_type(){return'(^('.int_type().'|decimal|numeric|number|real|(binary_|half_|scaled_)?float\d?|(binary_)?double( precision)?|(small)?money)$)';}function
text_type(){return'char|text'.(JUSH=="sql"?'|enum|set':'');}function
is_searchable(array$l,array$X){if(!isset($l["privileges"]["where"]))return
false;$U=$l["type"];$Mi=$X["val"];$Ma='binary$|bytea|raw|image|bfile|^vector$'.(JUSH=="mssql"?'|^timestamp$':'|^bit').(JUSH=="oracle"?'|^blob|^long|rowid':'');if(preg_match("~$Ma~",$U))return
false;if(preg_match(number_type(),$U)){$Cg='-?\d+(\.\d+)?';return(bool)preg_match('~^'.$Cg.(preg_match('~IN$~',$X["op"])?"( *, *$Cg)*":'').'$~',$Mi);}if(preg_match('~^(small)?date|^timestamp~',$U))return(bool)preg_match('~^\d+-\d+-\d+~',$Mi);if(preg_match('~^time~',$U))return(bool)preg_match('~^\d+:\d+~',$Mi);if(preg_match('~^bool~',$U)||(JUSH=="mssql"&&$U=="bit"))return(bool)preg_match('~^(t|f|true|false|[01])$~i',$Mi);return
true;}function
remove_slashes(array$bl,$rd=false){$J=array();foreach($bl
as$x=>$X)$J[stripslashes($x)]=(is_array($X)?remove_slashes($X,$rd):($rd?$X:stripslashes($X)));return$J;}function
bracket_escape($u,$Fa=false){static$rk=array(':'=>':1',']'=>':2','['=>':3','"'=>':4','='=>':5');return
strtr($u,($Fa?array_flip($rk):$rk));}function
url_escape($Q){static$rk=array();if(!$rk){$rk=array(' '=>'+');foreach(str_split("\"'<>#%&+=?".ini_get("arg_separator.input"))as$Xa)$rk[$Xa]=sprintf('%%%02X',ord($Xa));for($s=0;$s<256;$s++){if($s<32||$s>126)$rk[chr($s)]=sprintf('%%%02X',$s);}}return
strtr((string)$Q,$rk);}function
min_version($el,$Ef="",$f=null){$f=connection($f);$Zi=$f->server_info;if($Ef&&preg_match('~([\d.]+)-MariaDB~',$Zi,$A)){$Zi=$A[1];$el=$Ef;}return$el&&version_compare($Zi,$el)>=0;}function
charset(Db$e){return(min_version("5.5.3",0,$e)?"utf8mb4":"utf8");}function
ini_set($Wg,$Y){return(function_exists('ini_set')?\ini_set($Wg,$Y):false);}function
ini_bool($Fe){$X=ini_get($Fe);return(preg_match('~^(on|true|yes)$~i',$X)||(int)$X);}function
ini_bytes($Fe){$X=ini_get($Fe);switch(strtolower(substr($X,-1))){case'g':$X=(int)$X*1024;case'm':$X=(int)$X*1024;case'k':$X=(int)$X*1024;}return$X;}function
max_input_vars($K,$jh){$Hf=(int)ini_get("max_input_vars");return($Hf?(int)floor(($Hf-$jh)/$K):0);}function
max_input_vars_error(){$Fe="max_input_vars";return
sprintf('Maximum number of allowed fields exceeded. Please increase %s.',"<b>$Fe = ".ini_get($Fe)."</b>");}function
sid(){static$J;if($J===null)$J=(SID&&!($_COOKIE&&ini_bool("session.use_cookies")));return$J;}function
set_password($dl,$N,$V,$F){$_SESSION["pwds"][$dl][$N][$V]=($_COOKIE["adminer_key"]&&is_string($F)?array(encrypt_string($F,$_COOKIE["adminer_key"])):$F);}function
get_password(){$J=get_session("pwds");if(is_array($J))$J=($_COOKIE["adminer_key"]?decrypt_string($J[0],$_COOKIE["adminer_key"]):false);return$J;}function
get_val($H,$l=0,$wb=null){$wb=connection($wb);$I=$wb->query($H);if(!is_object($I))return
false;$K=$I->fetch_row();return($K?$K[$l]:false);}function
get_vals($H,$c=0){$J=array();$I=connection()->query($H);if(is_object($I)){while($K=$I->fetch_row())$J[]=$K[$c];}return$J;}function
get_key_vals($H,$f=null,$cj=true){$f=connection($f);$J=array();$I=$f->query($H);if(is_object($I)){while($K=$I->fetch_row()){if($cj)$J[$K[0]]=$K[1];else$J[]=$K[0];}}return$J;}function
get_rows($H,$f=null,$k="<p class='error'>"){$wb=connection($f);$J=array();$I=$wb->query($H);if(is_object($I)){while($K=$I->fetch_assoc())$J[]=$K;}elseif(!$I&&!$f&&$k&&(defined('Adminer\PAGE_HEADER')||$k=="-- "))echo$k.adminer()->error()."\n";return$J;}function
unique_array($K,array$w){foreach($w
as$v){if(preg_match("~^(PRIMARY|UNIQUE)$~",$v["type"])&&!$v["partial"]){$J=array();foreach($v["columns"]as$x){if(!isset($K[$x]))continue
2;$J[$x]=$K[$x];}return$J;}}}function
escape_key($x){if(preg_match('(^([\w(]+)('.str_replace("_",".*",preg_quote(idf_escape("_"))).')([ \w)]+)$)',$x,$A))return$A[1].idf_escape(idf_unescape($A[2])).$A[3];return
idf_escape($x);}function
where(array$Z,array$m=array()){$J=array();foreach((array)$Z["where"]as$x=>$X){$x=bracket_escape($x,true);$c=escape_key($x);$l=idx($m,$x,array());$ld=$l["type"];$Se=$l&&(is_blob($l)||preg_match('~binary~',$ld));$J[]=$c.($Se&&!is_utf8($X)?" = ".driver()->quoteBinary($X):(JUSH=="sql"&&$ld=="json"?" = CAST(".q($X)." AS JSON)":(JUSH=="pgsql"&&preg_match('~^jsonb?$~',$l["full_type"])?"::jsonb = ".q($X)."::jsonb":(JUSH=="sql"&&is_numeric($X)&&preg_match('~\.~',$X)?" LIKE ".q($X):(JUSH=="mssql"&&strpos($ld,"datetime")===false?" LIKE ".q(preg_replace('~[_%[]~','[\0]',$X)):" = ".unconvert_field($l,q($X)))))));if(JUSH=="sql"&&preg_match('~char|text~',$ld)&&preg_match("~[^ -@]~",$X))$J[]="$c = ".q($X)." COLLATE ".charset(connection())."_bin";}foreach((array)$Z["null"]as$x)$J[]=escape_key($x)." IS NULL";return
implode(" AND ",$J);}function
where_columns(array$m){$J=array();foreach((array)$_GET["null"]as$x)$J[$x]=true;foreach((array)$_GET["where"]as$x=>$X){$x=bracket_escape($x,true);foreach($m
as$B=>$l){if($x==$B||strpos($x,idf_escape($B))!==false)$J[$B]=true;}}return$J;}function
where_check($X,array$m=array()){parse_str($X,$ab);remove_slashes(array(&$ab));return
where($ab,$m);}function
where_link($s,$c,$Y,$Tg="="){$Qg=($Y!==null?$Tg:"IS NULL");return"&where[$s][col]=".url_escape($c).($Qg!=first(adminer()->operators())?"&where[$s][op]=".url_escape($Qg):"")."&where[$s][val]=".url_escape($Y);}function
convert_fields(array$d,array$m,array$M=array()){$J="";foreach($d
as$x=>$X){if($M&&!in_array(idf_escape($x),$M))continue;$xa=convert_field($m[$x]);if($xa)$J
.=", $xa AS ".idf_escape($x);}return$J;}function
cookie_path(){return
strtr(preg_replace('~\?.*~','',$_SERVER["REQUEST_URI"]),array(";"=>"%3B",","=>"%2C"));}function
cookie($B,$Y,$vf=2592000){header("Set-Cookie: $B=".rawurlencode($Y).($vf?"; expires=".gmdate("D, d M Y H:i:s",time()+$vf)." GMT":"")."; path=".cookie_path().(HTTPS?"; secure":"").($B=="adminer_import"?"":"; HttpOnly")."; SameSite=lax",false);}function
get_url($Pk,$_b){$http_response_header=null;$Nc=array();set_error_handler(function($Mc,$k)use(&$Nc){$Nc[]=preg_replace('~^file_get_contents\([^)]*\):\s*~','',$k);return
true;});$J=file_get_contents($Pk,false,$_b);restore_error_handler();$ee=(function_exists('http_get_last_response_headers')?http_get_last_response_headers():$http_response_header);return
array($J,(preg_match('~^HTTP/[\d.]+ (\d+)~',idx($ee,0,''),$A)?$A[1]:''),(array)$ee,($J===false?implode("\n",$Nc):''),);}function
get_settings($Cb){parse_str($_COOKIE[$Cb],$dj);return$dj;}function
get_setting($x,$Cb="adminer_settings",$j=null){return
idx(get_settings($Cb),$x,$j);}function
save_settings(array$dj,$Cb="adminer_settings"){$Y=http_build_query($dj+get_settings($Cb));cookie($Cb,$Y);$_COOKIE[$Cb]=$Y;}function
restart_session(){if(!ini_bool("session.use_cookies")&&(!function_exists('session_status')||session_status()==PHP_SESSION_NONE))session_start();}function
stop_session($xd=false){$Sk=ini_bool("session.use_cookies");if(!$Sk||$xd){session_write_close();if($Sk&&ini_set("session.use_cookies",'0')===false)session_start();}}function&get_session($x){return$_SESSION[$x][DRIVER][SERVER][$_GET["username"]];}function
set_session($x,$X){$_SESSION[$x][DRIVER][SERVER][$_GET["username"]]=$X;}function
auth_url($dl,$N,$V,$i=null){$Ok=remove_from_uri(implode("|",array_keys(SqlDriver::$drivers))."|username|ext|".($i!==null?"db|":"").($dl=='mssql'||$dl=='pgsql'?"":"ns|").session_name());preg_match('~([^?]*)\??(.*)~',$Ok,$A);return"$A[1]?".(sid()?SID."&":"").($_GET["ext"]?"ext=".url_escape($_GET["ext"])."&":"").($dl!="server"||$N!=""?url_escape($dl)."=".url_escape($N)."&":"")."username=".url_escape($V).($i!=""?"&db=".url_escape($i):"").($A[2]?"&$A[2]":"");}function
is_ajax(){return($_SERVER["HTTP_X_REQUESTED_WITH"]=="XMLHttpRequest");}function
redirect($_,$Xf=null){if($Xf!==null){restart_session();$_SESSION["messages"][preg_replace('~^[^?]*~','',($_!==null?$_:$_SERVER["REQUEST_URI"]))][]=$Xf;}if($_!==null){if($_=="")$_=".";header("Location: $_");exit;}}function
query_redirect($H,$_,$Xf,$li=true,$Vc=true,$gd=false,$ek=""){if($Vc){$wj=microtime(true);$gd=!connection()->query($H);$ek=format_time($wj);}$qj=($H?adminer()->messageQuery($H,$ek,$gd):"");if($gd){adminer()->error
.=adminer()->error().$qj.script("messagesPrint();")."<br>";return
false;}if($li)redirect($_,$Xf.$qj);return
true;}class
Queries{static$queries=array();static$start=0;}function
queries($H){if(!Queries::$start)Queries::$start=microtime(true);Queries::$queries[]=(driver()->delimiter!=';'?$H:(preg_match('~;$~',$H)?"DELIMITER ;;\n$H;\nDELIMITER ":$H).";");return
connection()->query($H);}function
apply_queries($H,array$T,$Pc='Adminer\table'){foreach($T
as$R){if(!queries("$H ".$Pc($R)))return
false;}return
true;}function
queries_redirect($_,$Xf,$li){$gi=implode("\n",Queries::$queries);$ek=format_time(Queries::$start);return
query_redirect($gi,$_,$Xf,$li,false,!$li,$ek);}function
format_time($wj){return
sprintf('%.3f s',max(0,microtime(true)-$wj));}function
relative_uri($Ok=''){return
preg_replace_callback('~^[^?]*~',function($A){return
str_replace(":","%3A",$A[0]);},preg_replace('~^[^?]*/([^?]*)~','\1',($Ok?:$_SERVER["REQUEST_URI"])));}function
remove_from_uri($oh=""){return
substr(preg_replace("~(?<=[?&])($oh".(SID?"":"|".session_name()).")=[^&]*&~",'',relative_uri()."&"),0,-1);}function
get_files($B,$Vb=false){$nd=$_FILES[$B];if(!$nd)return
null;foreach($nd
as$x=>$X)$nd[$x]=(array)$X;$J=array();foreach($nd["error"]as$x=>$k){if($k)return$k;$n=$nd["name"][$x];$mk=$nd["tmp_name"][$x];$yb=file_get_contents($Vb&&preg_match('~\.gz$~',$n)?"compress.zlib://$mk":$mk);if($Vb){$wj=substr($yb,0,3);if(function_exists("iconv")&&preg_match("~^\xFE\xFF|^\xFF\xFE~",$wj))$yb=iconv("utf-16","utf-8",$yb);elseif($wj=="\xEF\xBB\xBF")$yb=substr($yb,3);}$J[]=array($n,$yb);}return$J;}function
get_file($x,$Vb=false,$cc=""){$qd=get_files($x,$Vb);if(!is_array($qd))return$qd;$J='';foreach($qd
as$nd){$yb=$nd[1];$J
.=$yb;if($cc)$J
.=(preg_match("($cc\\s*\$)",$yb)?"":$cc)."\n\n";}return$J;}function
upload_error($k){$Pf=($k==UPLOAD_ERR_INI_SIZE?ini_get("upload_max_filesize"):0);return($k?'Unable to upload a file.'.($Pf?" ".sprintf('Maximum allowed file size is %sB.',$Pf):""):'File does not exist.');}function
is_utf8($X){return(preg_match('~~u',$X)&&!preg_match('~[\0-\x8\xB\xC\xE-\x1F]~',$X));}function
format_number($X){preg_match('~^#+([^#0]+)(?:(#+)\1)?(#*0)$~u','#,##0',$A);$hj=strlen($A[3]);$J=number_format($X,0,".","");$J=preg_replace('~\B(?=(\d{'.(strlen($A[2])?:$hj).'})*\d{'.$hj.'}$)~',$A[1],$J);return
strtr($J,preg_split('~~u','0123456789',-1,PREG_SPLIT_NO_EMPTY));}function
format_status(array$S,$x){$X=idx($S,$x,'?');if(!is_numeric($X))return
h($X);if($X<0)return'?';$ta=($x=="Rows"&&(JUSH=="sqlite"||$S["Engine"]==(JUSH=="pgsql"?"table":"InnoDB")));return($ta?"~ ":"").format_number($X);}function
friendly_url($X){return
preg_replace('~\W~i','-',$X);}function
table_status1($R,$hd=false){$J=table_status($R,$hd);return($J?reset($J):array("Name"=>$R));}function
column_foreign_keys($R){$J=array();foreach(adminer()->foreignKeys($R)as$o){foreach($o["source"]as$X)$J[$X][]=$o;}return$J;}function
fields_from_edit(){$J=array();foreach((array)$_POST["field_keys"]as$x=>$X){if($X!=""){$X=bracket_escape($X);$_POST["function"][$X]=$_POST["field_funs"][$x];$_POST["fields"][$X]=$_POST["field_vals"][$x];}}foreach((array)$_POST["fields"]as$x=>$X){$B=bracket_escape($x,true);$J[$B]=array("field"=>$B,"full_type"=>"","type"=>"","privileges"=>array("insert"=>1,"update"=>1,"where"=>1,"order"=>1),"null"=>true,"auto_increment"=>($B==driver()->primary),);}return$J;}function
dump_headers($qe,$ng=false){$J=adminer()->dumpHeaders($qe,$ng);$lh=$_POST["output"];if($lh!="text"||$J=="tar"){$tb=($lh!="text"&&$lh!="file"&&preg_match('~^[0-9a-z]+$~',$lh)?".$lh":"");header("Content-Disposition: attachment; filename=".adminer()->dumpFilename($qe).".$J$tb");}session_write_close();if(!ob_get_level())ob_start(null,4096);ob_flush();flush();return$J;}function
dump_csv(array$K){$zk=$_POST["format"]=="tsv";foreach($K
as$x=>$X){if(preg_match('~["\n]|^0[^.]|\.\d*0$|'.($zk?'\t':'[,;]|^$').'~',$X))$K[$x]='"'.str_replace('"','""',$X).'"';}echo
implode(($_POST["format"]=="csv"?",":($zk?"\t":";")),$K)."\r\n";}function
parse_csv($Kb,$Ui){$J=array();preg_match_all('~(?>"[^"]*"|[^"\r\n]+)+~',$Kb,$Ff);foreach($Ff[0]as$K){preg_match_all("~((?>\"[^\"]*\")+|[^$Ui]*)$Ui~",$K.$Ui,$Gf);$J[]=$Gf[1];}return$J;}function
csv_value($X){return(preg_match('~^".*"$~s',$X)?str_replace('""','"',substr($X,1,-1)):$X);}function
apply_sql_function($q,$c){return($q?($q=="unixepoch"?"DATETIME($c, '$q')":($q=="count distinct"?"COUNT(DISTINCT ":strtoupper("$q("))."$c)"):$c);}function
get_temp_dir(){return
ini_get("upload_tmp_dir")?:sys_get_temp_dir();}function
file_open_lock($n){if(is_link($n))return;$p=@fopen($n,"c+");if(!$p)return;@chmod($n,0660);if(!flock($p,LOCK_EX)){fclose($p);return;}return$p;}function
file_write_unlock($p,$Ob){rewind($p);fwrite($p,$Ob);ftruncate($p,strlen($Ob));file_unlock($p);}function
file_unlock($p){flock($p,LOCK_UN);fclose($p);}function
first(array$wa){return
reset($wa);}function
password_file($g){$n=get_temp_dir()."/adminer.key";if(!$g&&!file_exists($n))return'';$p=file_open_lock($n);if(!$p)return'';$J=stream_get_contents($p);if(!$J){$J=rand_string();file_write_unlock($p,$J);}else
file_unlock($p);return$J;}function
rand_string(){return(function_exists('random_bytes')?bin2hex(random_bytes(16)):md5(uniqid(strval(mt_rand()),true)));}function
select_value($X,$z,array$l,$ck){if(is_array($X)){$J="";if(array_filter($X,'is_array')==array_values($X)){$df=array();foreach($X
as$W)$df+=array_fill_keys(array_keys($W),null);foreach(array_keys($df)as$cf)$J
.="<th>".h($cf);foreach($X
as$W){$J
.="<tr>";foreach(array_merge($df,$W)as$Xk)$J
.="<td>".select_value($Xk,$z,$l,$ck);}}else{foreach($X
as$cf=>$W)$J
.="<tr>".($X!=array_values($X)?"<th>".h($cf):"")."<td>".select_value($W,$z,$l,$ck);}return"<table>$J</table>";}if(!$z)$z=adminer()->selectLink($X,$l);if($z===null){if(is_mail($X))$z="mailto:$X";if(is_url($X))$z=$X;}$X=driver()->value($X,$l);$J=adminer()->editVal($X,$l);if($J!==null){if(!is_utf8($J))$J="\0";elseif($ck!=""&&is_shortable($l))$J=shorten_utf8($J,max(0,+$ck));else$J=h($J);}return
adminer()->selectVal($J,$z,$l,$X);}function
is_blob(array$l){return
preg_match('~blob|bytea|raw|file'.(JUSH=="mssql"?'|binary|image':'').'~',$l["type"])&&!in_array($l["type"],idx(driver()->structuredTypes(),'User types',array()));}function
is_mail($Dc){$za='[-a-z0-9!#$%&\'*+/=?^_`{|}~]';$tc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';$Eh="$za+(\\.$za+)*@($tc?\\.)+$tc";return
is_string($Dc)&&preg_match("(^$Eh(,\\s*$Eh)*\$)i",$Dc);}function
is_url($Q){$tc='[a-z0-9]([-a-z0-9]{0,61}[a-z0-9])';return
preg_match("~^((https?):)?//($tc?\\.)+$tc(:\\d+)?(/.*)?(\\?.*)?(#.*)?\$~i",$Q);}function
is_ipv6($ha){$r='[\da-f]{1,4}';$Re='\d{1,3}(\.\d{1,3}){3}';return(bool)preg_match("~^(($r:){7}$r|($r:){6}$Re|(($r:)*$r)?::(($r:)*($r|$Re))?)$~iD",$ha);}function
is_shortable(array$l){return!preg_match('~'.number_type().'|date|time|year~',$l["type"]);}function
url_host($me){return(strpos($me,":")!==false?"[$me]":$me);}function
server_parts(array$zh){return
array("scheme"=>(string)$zh["scheme"],"host"=>(string)$zh["host"],"port"=>(string)$zh["port"],"socket"=>(string)$zh["socket"],"path"=>(string)$zh["path"],);}function
parse_server($N){if($N=="")return
server_parts(array());if($N[0]==":"&&!is_ipv6($N)){$xi=substr($N,1);if(preg_match('~^\d+$~D',$xi))return
server_parts(array("port"=>$xi));return(preg_match('~^/[-\w.:/]*$~D',$xi)?server_parts(array("socket"=>$xi)):null);}$Ki="";if(preg_match('~^([-+.\w]+)://~',$N,$A)){$Ki=strtolower($A[1]);$N=substr($N,strlen($A[0]));}if(preg_match('~^\[(.+)](:(\d+))?(/[-\w./]*)?$~D',$N,$A))return(is_ipv6($A[1])?server_parts(array("scheme"=>$Ki,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);if(is_ipv6($N))return
server_parts(array("scheme"=>$Ki,"host"=>$N));if(preg_match('~^(/[-\w./]*)(:(\d+))?$~D',$N,$A))return
server_parts(array("scheme"=>$Ki,"host"=>$A[1],"port"=>$A[3]));return(preg_match('~^([-\w.]*)(:(\d+))?(/[-\w./]*)?$~D',$N,$A)?server_parts(array("scheme"=>$Ki,"host"=>$A[1],"port"=>$A[3],"path"=>$A[4])):null);}function
count_rows($R,array$Z,$Te,array$r){$H=" FROM ".table($R).($Z?" WHERE ".implode(" AND ",$Z):"");return($Te&&(JUSH=="sql"||count($r)==1)?"SELECT COUNT(DISTINCT ".implode(", ",$r).")$H":"SELECT COUNT(*)".($Te?" FROM (SELECT 1$H GROUP BY ".implode(", ",$r).") x":$H));}function
slow_query($H){$i=adminer()->database();$fk=adminer()->queryTimeout();$ij=driver()->slowQuery($H,$fk);$f=null;if(!$ij&&support("kill")){$f=connect();if($f&&($i==""||$f->select_db($i))){$ef=number(get_val(connection_id(),0,$f));echo
script("const timeout = setTimeout(() => { ajax('".js_escape(ME)."script=kill', function () {}, 'kill=$ef&token=".get_token()."'); }, 1000 * $fk);");}}ob_flush();flush();$J=@get_key_vals(($ij?:$H),$f,false);if($f){echo
script("clearTimeout(timeout);");ob_flush();flush();}return$J;}function
get_token(){$ji=rand(1,1e6);return($ji^$_SESSION["token"]).":$ji";}function
verify_token(){list($nk,$ji)=explode(":",$_POST["token"]);return($ji^$_SESSION["token"])==$nk&&in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"));}function
compress_alphabet(){return
strtr(implode(range('"','~')),"'\\","!\n");}function
decompress_string($Q,$ic=""){$pa=array_flip(str_split(compress_alphabet()));$rf=strlen($Q);$Zk=($rf?13*($rf-1)/2-$pa[$Q[0]]:0);$Ma="";$xi=0;$yi=0;for($s=1;$s<$rf;$s+=2){$xi=($xi<<13)+$pa[$Q[$s]]*93+$pa[$Q[$s+1]];$yi+=13;while($yi>=8&&$Zk>=8){$yi-=8;$Zk-=8;$Ma
.=chr($xi>>$yi);$xi&=(1<<$yi)-1;}}if($Ma=="")return"";if($ic!=""&&function_exists('inflate_init'))return
inflate_add(inflate_init(ZLIB_ENCODING_RAW,array('dictionary'=>$ic)),$Ma,ZLIB_FINISH);return($ic==""&&function_exists('gzinflate')?gzinflate($Ma):inflate($Ma,$ic));}function
inflate($Ma,$ic=""){$sf=array(3,4,5,6,7,8,9,10,11,13,15,17,19,23,27,31,35,43,51,59,67,83,99,115,131,163,195,227,258);$tf=array(0,0,0,0,0,0,0,0,1,1,1,1,2,2,2,2,3,3,3,3,4,4,4,4,5,5,5,5,0);$mc=array(1,2,3,4,5,7,9,13,17,25,33,49,65,97,129,193,257,385,513,769,1025,1537,2049,3073,4097,6145,8193,12289,16385,24577);$oc=array(0,0,0,0,1,1,2,2,3,3,4,4,5,5,6,6,7,7,8,8,9,9,10,10,11,11,12,12,13,13);$J=$ic;$G=0;do{$sd=inflate_bits($Ma,$G,1);$U=inflate_bits($Ma,$G,2);if(!$U){$G=($G+7)&~7;$rf=inflate_bits($Ma,$G,16);$G+=16;$J
.=substr($Ma,$G>>3,$rf);$G+=$rf<<3;}else{if($U==1){$_f=array_merge(array_fill(0,144,8),array_fill(0,112,9),array_fill(0,24,7),array_fill(0,8,8));$pc=array_fill(0,30,5);}else{$zf=inflate_bits($Ma,$G,5)+257;$nc=inflate_bits($Ma,$G,5)+1;$D=array(16,17,18,0,8,7,9,6,10,5,11,4,12,3,13,2,14,1,15);$dg=array_fill(0,19,0);$cg=inflate_bits($Ma,$G,4)+4;for($s=0;$s<$cg;$s++)$dg[$D[$s]]=inflate_bits($Ma,$G,3);$eg=inflate_table($dg);$uf=array();while(count($uf)<$zf+$nc){$Gj=inflate_symbol($Ma,$G,$eg);if($Gj==16)$uf=array_merge($uf,array_fill(0,inflate_bits($Ma,$G,2)+3,end($uf)));elseif($Gj==17)$uf=array_merge($uf,array_fill(0,inflate_bits($Ma,$G,3)+3,0));elseif($Gj==18)$uf=array_merge($uf,array_fill(0,inflate_bits($Ma,$G,7)+11,0));else$uf[]=$Gj;}$_f=array_slice($uf,0,$zf);$pc=array_slice($uf,$zf);}$Af=inflate_table($_f);$rc=inflate_table($pc);while(($Gj=inflate_symbol($Ma,$G,$Af))!=256){if($Gj<256)$J
.=chr($Gj);else{$rf=$sf[$Gj-257]+inflate_bits($Ma,$G,$tf[$Gj-257]);$qc=inflate_symbol($Ma,$G,$rc);$Ig=strlen($J)-$mc[$qc]-inflate_bits($Ma,$G,$oc[$qc]);for($s=0;$s<$rf;$s++)$J
.=$J[$Ig+$s];}}}}while(!$sd);return($ic==""?$J:substr($J,strlen($ic)));}function
inflate_bits($Ma,&$G,$Eb){$J=0;for($s=0;$s<$Eb;$s++){$J+=((ord($Ma[$G>>3])>>($G&7))&1)<<$s;$G++;}return$J;}function
inflate_table(array$uf){$R=array();$ib=0;for($Na=1;$Na<=max($uf);$Na++){foreach($uf
as$Gj=>$rf){if($rf==$Na){$R[$Na][$ib]=$Gj;$ib++;}}$ib<<=1;}return$R;}function
inflate_symbol($Ma,&$G,array$R){$ib=0;$Na=0;do{$ib=($ib<<1)+inflate_bits($Ma,$G,1);$Na++;}while(!isset($R[$Na][$ib]));return$R[$Na][$ib];}function
script($nj,$qk="\n"){return"<script".nonce().">$nj</script>$qk";}function
script_src($Pk,$Yb=false){return"<script src='".h($Pk)."'".nonce().($Yb?" defer":"")."></script>\n";}function
nonce(){return' nonce="'.get_nonce().'"';}function
on($Qc,$Wd,$ua=null){$va=array();foreach(array_slice(func_get_args(),2)as$X)$va[]=json_encode($X,256);return" data-on$Qc='".str_replace(array('&','<',"'"),array('&amp;','&lt;','&#039;'),"$Wd(".implode(", ",$va).")")."'";}function
input_hidden($B,$Y=""){return"<input type='hidden' name='".h($B)."' value='".h($Y)."'>\n";}function
input_token(){return
input_hidden("token",get_token());}function
target_blank(){return' target="_blank" rel="noreferrer noopener"';}function
h($Q){return
str_replace(array('&','<','"',"'","\0"),array('&amp;','&lt;','&quot;','&#039;','&#0;'),$Q);}function
nl_br($Q){return
str_replace("\n","<br>",$Q);}function
checkbox($B,$Y,$cb,$gf="",$b="",$hb="",$if=""){$J="<input type='checkbox' name='$B' value='".h($Y)."'".($cb?" checked":"").($gf==""&&$hb?" class='$hb'":"").($if?" aria-labelledby='$if'":"").$b.">";return($gf!=""?"<label".($hb?" class='$hb'":"").">$J".h($gf)."</label>":$J);}function
optionlist($C,$Ri=null,$Tk=false){$J="";foreach($C
as$cf=>$W){$Yg=array($cf=>$W);if(is_array($W)){$J
.='<optgroup label="'.h($cf).'">';$Yg=$W;}foreach($Yg
as$x=>$X)$J
.='<option'.($Tk||is_string($x)?' value="'.h($x).'"':'').($Ri!==null&&($Tk||is_string($x)?(string)$x:$X)===$Ri?' selected':'').'>'.h($X);if(is_array($W))$J
.='</optgroup>';}return$J;}function
html_select($B,array$C,$Y="",$b="",$if=""){static$gf=0;$hf="";if(!$if&&substr($C[""],0,1)=="("){$gf++;$if="label-$gf";$hf="<option value='' id='$if'>".h($C[""]);unset($C[""]);}return"<select name='".h($B)."'".($if?" aria-labelledby='$if'":"")."$b>".$hf.optionlist($C,$Y)."</select>";}function
html_radios($B,array$C,$Y="",$Ui=""){$J="";foreach($C
as$x=>$X)$J
.="<label><input type='radio' name='".h($B)."' value='".h($x)."'".($x==$Y?" checked":"").">".h($X)."</label>$Ui";return$J;}function
confirm($Xf=""){return
on('click','confirmClick',$Xf?:'Are you sure?');}function
print_fieldset($t,$qf,$hl=false){echo"<fieldset><legend>","<a href='#fieldset-$t' class='toggle'>$qf</a>","</legend>","<div id='fieldset-$t'".($hl?"":" class='hidden'").">\n";}function
bold($Pa,$hb=""){return($Pa?" class='active $hb'":($hb?" class='$hb'":""));}function
js_escape($Q){return
str_replace("<","\\x3C",addcslashes($Q,"\r\n'\\"));}function
js_escape_re($Q){return
addcslashes(preg_quote($Q,"/"),"\r\n");}function
pagination_href($E){return
remove_from_uri("page|next").($E?"&page=$E".($_GET["next"]!=""?"&next=".url_escape($_GET["next"]):""):"");}function
pagination($E,$Lb){return" ".($E==$Lb?($E?"<b>".($E+1)."</b>":$E+1):'<a href="'.h(pagination_href($E)).'">'.($E+1)."</a>");}function
hidden_fields(array$ci,array$te=array(),$Th=''){$J=false;foreach($ci
as$x=>$X){if(!in_array($x,$te)){if(is_array($X))hidden_fields($X,array(),$x);else{$J=true;echo
input_hidden(($Th?$Th."[$x]":$x),$X);}}}return$J;}function
hidden_fields_get(){echo(sid()?input_hidden(session_name(),session_id()):''),($_GET["ext"]?input_hidden("ext",$_GET["ext"]):""),(isset($_GET[DRIVER])?input_hidden(DRIVER,SERVER):""),input_hidden("username",$_GET["username"]);}function
on_upload_progress(&$Nk){$Nk=(ini_bool("session.upload_progress.enabled")&&ini_get("session.upload_progress.name")?rand_string():"");return($Nk?on('submit','uploadProgress',ME."upload=$Nk",SESSION_NAME."=$Nk"):"");}function
file_input($b,$xi=""){$Jf="max_file_uploads";$Kf=ini_get($Jf);$Pf="upload_max_filesize";$Qf=ini_bytes($Pf);$Qh=ini_bytes("post_max_size");if($Qh&&$Qh<$Qf){$Pf="post_max_size";$Qf=$Qh;}$Rf=ini_get($Pf);return(ini_bool("file_uploads")?"<input type='file'$b".on('change','fileChange',(int)$Kf,sprintf('Increase %s.',"$Jf = $Kf"),$Qf,sprintf('Increase %s.',"$Pf = $Rf")).">$xi":'File uploads are disabled.');}function
enum_input($U,$b,array$l,$Y,$Gc=""){preg_match_all("~'((?:[^']|'')*)'~",$l["length"],$Ff);$Th=($l["type"]=="enum"?"val-":"");$cb=(is_array($Y)?in_array("null",$Y):$Y===null);$J=($l["null"]&&$Th?"<label><input type='$U'$b value='null'".($cb?" checked":"")."><i>$Gc</i></label>":"");foreach($Ff[1]as$X){$X=stripcslashes(str_replace("''","'",$X));$cb=(is_array($Y)?in_array($Th.$X,$Y):$Y===$X);$J
.=" <label><input type='$U'$b value='".h($Th.$X)."'".($cb?' checked':'').'>'.h(adminer()->editVal($X,$l)).'</label>';}return$J;}function
input(array$l,$Y,$q,$Da=false,$Lk=false){$B=h(bracket_escape($l["field"]));echo"<td class='function'>";$Lc=driver()->enumLength($l);if($Lc){$l["type"]="enum";$l["length"]=$Lc;}$C=($l["type"]=="enum"||$l["type"]=="set");if(is_array($Y)&&!$q&&!$C)$q="json";$af=($q=="json"||preg_match('~^jsonb?$~',$l["full_type"]));if($af&&$Y!=''&&(JUSH!="pgsql"||$l["type"]!="json")&&(is_array($Y)||!$_POST["save"]))$Y=json_encode(is_array($Y)?$Y:json_decode($Y),128|64|256);$wi=(JUSH=="mssql"&&$Lk&&$l["auto_increment"]);if($wi&&!$_POST["save"])$q=null;$Jd=(isset($_GET["select"])||$wi?array("orig"=>'original'):array())+adminer()->editFunctions($l);$b=" name='fields[$B]".($C?"[]":"")."'".($Da?" autofocus":"");echo
driver()->unconvertFunction($l)." ";$R=$_GET["edit"]?:$_GET["select"];if($l["type"]=="enum")echo
h($Jd[""])."<td>".adminer()->editInput($R,$l,$b,$Y);else{$Yd=(in_array($q,$Jd)||isset($Jd[$q]));$td=0;foreach($Jd
as$x=>$X){if($x===""||!$X)break;$td++;}echo(count($Jd)>1?"<select name='function[$B]'".on('change','functionChange').on_help_value('^SQL$').">".optionlist($Jd,$q===null||$Yd?$q:"")."</select>":h(reset($Jd)))."<td".($td&&count($Jd)>1?on('input','skipOriginal',$td):"").">";$He=adminer()->editInput($R,$l,$b,$Y);if($He!="")echo$He;elseif(preg_match('~bool~',$l["type"]))echo"<input type='hidden'$b value='0'>"."<input type='checkbox'".(preg_match('~^(1|t|true|y|yes|on)$~i',$Y)?" checked":"")."$b value='1'>";elseif($l["type"]=="set")echo
enum_input("checkbox",$b,$l,(is_string($Y)?explode(",",$Y):$Y));elseif(is_blob($l)&&ini_bool("file_uploads"))echo"<input type='file' name='fields-$B'>";elseif($af)echo"<textarea$b cols='50' rows='12' class='jush-json'>".h($Y).'</textarea>';elseif(($bk=preg_match('~text|lob|memo~i',$l["type"]))||preg_match("~\n~",$Y)){if($bk&&JUSH!="sqlite")$b
.=" cols='50' rows='12'";else{$L=min(12,substr_count($Y,"\n")+1);$b
.=" cols='30' rows='$L'";}echo"<textarea$b>".h($Y).'</textarea>';}else{$Ck=driver()->types();$Ak=$Ck[$l["type"]];if(preg_match('~date|time|year~',$l["type"])){$Dd=(preg_match('~time~',$l["type"])&&preg_match('~^\d+$~',$l["length"])?$l["length"]+1:0);$Sf=($Ak?$Ak+$Dd:0);}elseif(!preg_match('~int|vector~',$l["type"])&&preg_match('~^(\d+)(,(\d+))?$~',$l["length"],$A))$Sf=(preg_match("~binary~",$l["type"])?2:1)*$A[1]+($A[3]?1:0)+($A[2]&&!$l["unsigned"]?1:0);else$Sf=($Ak?$Ak+($l["unsigned"]?0:1):0);echo"<input".((!$Yd||$q==="")&&preg_match('~^'.int_type().'$~',$l["type"])&&!preg_match('~\[]~',$l["full_type"])?" type='number'":"")." value='".h($Y)."'".($Sf?" data-maxlength='$Sf'":"").(preg_match('~char|binary~',$l["type"])&&$Sf>20?" size='".($Sf>99?60:40)."'":"")."$b>";}echo
adminer()->editHint($R,$l,$Y),(count($Jd)>1?script("fire(qs('select', qsl('td').previousSibling), 'change');",""):"");}}function
process_input(array$l){$u=bracket_escape($l["field"]);$q=idx($_POST["function"],$u);if($q=="orig")return(preg_match('~^CURRENT_TIMESTAMP~i',$l["on_update"])?idf_escape($l["field"]):false);if($q=="NULL")return"NULL";if(is_blob($l)&&ini_bool("file_uploads")){$nd=get_file("fields-$u");if(!is_string($nd))return
false;return
driver()->quoteBinary($nd);}$Y=idx($_POST["fields"],$u);if($Y===null)return
false;if($l["type"]=="enum"||driver()->enumLength($l)){$Y=idx($Y,0);if($Y=="orig"||!$Y)return
false;if($Y=="null")return"NULL";$Y=substr($Y,4);}if($l["auto_increment"]&&$Y=="")return
null;if($l["type"]=="set")$Y=implode(",",(array)$Y);if($q=="json"){$Y=json_decode($Y,true);if(!is_array($Y))return
false;return$Y;}return
adminer()->processInput($l,$Y,$q);}function
search_tables(){$_GET["where"][0]["val"]=$_POST["query"];$Ti="<ul>\n";foreach(table_status('',true)as$R=>$S){$B=adminer()->tableName($S);if(isset($S["Engine"])&&$B!=""&&(!$_POST["tables"]||in_array($R,$_POST["tables"]))){$I=connection()->query("SELECT".limit("1 FROM ".table($R)," WHERE ".implode(" AND ",adminer()->selectSearchProcess(fields($R),array(),$S)),1));if(!$I||$I->fetch_row()){$Yh="<a href='".h(ME."select=".url_escape($R)."&where[0][op]=".url_escape($_GET["where"][0]["op"])."&where[0][val]=".url_escape($_GET["where"][0]["val"]))."'>$B</a>";echo"$Ti<li>".($I?$Yh:"<p class='error'>$Yh: ".adminer()->error())."\n";$Ti="";}}}echo($Ti?"<p class='message'>".'No tables.':"</ul>")."\n";}function
on_help($bk,$gj=0){return
on('mouseover','helpMouseover',$bk,$gj).on('mouseout','helpMouseout');}function
on_help_value($si="",$vi=""){return
on('mouseover','helpValueMouseover',$si,$vi).on('mouseout','helpMouseout');}function
edit_form($R,array$m,$K,$Lk,$k='',$H='',$ek=''){$Kj=adminer()->tableName(table_status1($R,true));page_header(($Lk?'Edit':'Insert'),$k,array("select"=>array($R,$Kj)),$Kj);adminer()->editRowPrint($R,$m,$K,$Lk,$H,$ek);if($K===false){echo"<p class='error'>".'No rows.'."\n";return;}echo"<form action='' method='post' enctype='multipart/form-data' id='form'>\n";$Bc=false;$nl=($Lk&&!isset($_GET["select"])?where_columns($m):array());$Ab=(count($nl)!=count($m));if(!$Ab)$nl=array();if(!$m)echo"<p class='error'>".'You have no privileges to update this table.'."\n";else{echo"<table class='layout nowrap'".on('keydown','editingKeydown').">\n";$Da=!$_POST;foreach($m
as$B=>$l){echo"<tr".($nl[$B]?on('change','whereChange'):"")."><th>".adminer()->fieldName($l);$j=idx($_GET["set"],bracket_escape($B));if($j===null){$j=$l["default"];if($l["type"]=="bit"&&preg_match("~^b'([01]*)'\$~",$j,$ti))$j=$ti[1];if(JUSH=="sql"&&preg_match('~binary~',$l["type"]))$j=bin2hex($j);}$Y=($K!==null?($l["type"]=="set"&&is_array($K[$B])?implode(",",$K[$B]):(is_bool($K[$B])?+$K[$B]:$K[$B])):(!$Lk&&$l["auto_increment"]?"":(isset($_GET["select"])?false:$j)));if(!$_POST["save"]&&is_string($Y))$Y=adminer()->editVal($Y,$l);if(($Lk&&!isset($l["privileges"]["update"]))||$l["generated"])echo"<td class='function'><td>".select_value($Y,'',$l,null);else{$Bc=true;$q=($_POST["save"]?idx($_POST["function"],bracket_escape($B),""):($Lk&&preg_match('~^CURRENT_TIMESTAMP~i',$l["on_update"])?"now":($Y===false?null:($Y!==null?'':'NULL'))));if(!$_POST&&!$Lk&&$Y==$l["default"]&&preg_match('~^[\w.]+\(~',$Y))$q="SQL";if(preg_match("~time~",$l["type"])&&preg_match('~^CURRENT_TIMESTAMP~i',$Y)){$Y="";$q="now";}if($l["type"]=="uuid"&&$Y=="uuid()"){$Y="";$q="uuid";}if($Da!==false)$Da=($l["auto_increment"]||$q=="now"||$q=="uuid"?null:true);input($l,$Y,$q,$Da,$Lk);if($Da)$Da=false;}}if(!fields($R)&&driver()->primary!="")echo"<tr>"."<th><input name='field_keys[]'".on('input','fieldChange').">"."<td class='function'>".html_select("field_funs[]",adminer()->editFunctions(array("null"=>isset($_GET["select"]))))."<td><input name='field_vals[]'>";echo"</table>\n";}echo"<p>\n";if($Bc){echo"<input type='submit' value='".'Save'."'>\n";if(!isset($_GET["select"])&&$Ab){$jc=($nl&&($k!=""||adminer()->error!="")?" disabled":"");echo"<input type='submit' name='insert' value='".($Lk?'Save and continue editing':'Save and insert next')."' title='Ctrl+Shift+Enter'$jc".($Lk?on('click','ajaxForm','Saving…'):"").">\n";}}echo($Lk?"<input type='submit' name='delete' value='".'Delete'."'".confirm().">\n":"");if(isset($_GET["select"]))hidden_fields(array("check"=>(array)$_POST["check"],"clone"=>$_POST["clone"],"all"=>$_POST["all"]));echo
input_hidden("referer",(isset($_POST["referer"])?$_POST["referer"]:$_SERVER["HTTP_REFERER"])),input_hidden("save",1),input_token(),"</form>\n";}function
repeat_pattern($Eh,$rf){return
str_repeat("$Eh{0,65535}",$rf/65535)."$Eh{0,".($rf%65535)."}";}function
shorten_utf8($Q,$rf=80,$Cj=""){if(!preg_match("(^(".repeat_pattern("[\t\r\n -\x{10FFFF}]",$rf).")($)?)u",$Q,$A))preg_match("(^(".repeat_pattern("[\t\r\n -~]",$rf).")($)?)",$Q,$A);return(isset($A[2])?h($A[1]).$Cj:h(preg_replace('~\n[^\n]*\z~',"\n",$A[1]))."$Cj<i>…</i>");}function
icon($pe,$B,$oe,$hk,$b=""){return"<button ".($B?"type='submit' name='$B'":"draggable='true' tabindex='-1'")." title='".h($hk)."' class='icon icon-$pe".($B?"":" jsonly")."'$b><span>$oe</span></button>";}function
copy_icon(){$Db='Copy';return"<a href='' class='jsonly icon-copy' title='$Db'><span>$Db</span></a>";}if(isset($_GET["file"])){if(substr(VERSION,-4)!='-dev'){if($_SERVER["HTTP_IF_MODIFIED_SINCE"]){header("HTTP/1.1 304 Not Modified");exit;}header("Expires: ".gmdate("D, d M Y H:i:s",time()+365*24*60*60)." GMT");header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");header("Cache-Control: immutable");}ini_set("zlib.output_compression",'1');if($_GET["file"]=="default.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string('%c(ADg~Z9.uC>]~.3$i#V4&_?UqjkCbUi6!n7_^@%:>!T#8;}*:pbFpd%ydIC""!*lpu2Rd:XrOM1l~#m;h*tmNtn-K%;J/^Y*Q4P1@XxoU:<QWC~Ad
*wPUjMZVuxYpXE#]<&s3ePkKT#dKk1E:Ul2tyYT$30t^mt2IE._i,ba[]QH;&^G>XeCRnZ7F0S>T&;8AGU1t~%>-+Z0WfbKWl>U:ijN_z+h1*84v9?R/g<"kr`70Q,eAMX30u"wX8T$kB(|"|H0:,cYV2.iD~aXAjj1$~>,`?&Z9_<(!
qrT<(,_y%ATmH^FSdYHTXuUHpb2R3nhiJ=6B9zbpQiHwSWo:Fy
iA)+i]r,Z>=ERflWaj|1)rr"HHn)9CNWV<s
a8s*vQw0Q)
vcwcD.Wb5!3Tg9BKn%bJD*5Q5t[^6tIF=@(|pe],Vv1:0?fqkYe|lVr.Aj%xiFt!D.)?#3j$)/DW!"l{6LO&dBI5$(J+")2,j`tT8]n:oKf
gw:;1
Q5qe+(QGKmV5*nc<OAd+7atqW^^@"(^<HoB^&aPW20o.#;m#HB0b<Ml+"8bxP$)XLG8g5H*^0:Lik[-D"
^U]w?_&s)x6G=#;<k.VW8&kOm!*%NL"g+}OvS;Fa`c3RY]s}&8(oFCWd=@G,
O;)3?53PGPO#~0PHvP8u$S#sj
>=?FH8{R^MV.mJf8l53,&#z`fuue1wS[?1]bVxc_:<c&);2C0@,GJ"PkZ,y^`3M+qdTaLdM1to%O)Se:5oM[e5&^_10$Vdl<q[_7z<6rht[Qa`l[wHxmSBSA+ljgc-Qo,(j?)yk(g+o,H4NAm1h27wS2!b>LL@]vD^W$Q+I^`]TUND2tRJ7vqf!NW*=$iZhrakYxxyrcaey2pJ)h!T?`=<{-+tc*lC?&
#/28r(1}W56^E@-"WSdAB%^h
lU3Ne^/KQHrW^w<4`Tq7|5<2.J%;JSC3B?5;|-N$pL2Ad4FgH"[9!6{^|)uh3X-Vrec8<.kP4w#`i,^X"AkN!dLS`5tqW-&"q!Yp
Nh<uHt;6PFruK^TBkkQ#*8@Q57e-&Xa!Xk]u7zK6J8tfByUB
&aao]/B-|W-2,U)iHM]b=j6)r*,s4E:>j
t.^7_hzF9glrNKW83b38O<~MM0PT*r
Y6VFVGx:m6R.y!s[LbxY`eN~Pv(?f5[M4=DZPTr#7xA}B*c|N?2:8KrdfH#+3W%ghLi]ipdyn,r;%}lJn6z!NvU"*eiRc7,a-Qg@J{m@9j^m^d$r/q+K&Cj}:[J3;5Syu[.@-06A/BRwlm_kRi,!<yCM+}Fs*U3$^WZ(XdbjMYuY`{omV|0Q2U61UDIm0p&j0eB<=SUkE!$3b?E[il65<R(c95Tv&jGxE9R_RT
amR4HIml08>-"uN=WQ9E_;yLmOm[ZF66Smep_,*l(J
Y>Tv4P+zc=QCe"UiN+wGmd0*0tt1tUO>Eu<MU34>P<1C0M&
v+nG$Pv(-P$k
wyAI9;o=f>E!*CM<[J9<p+B%p8CvW0Q3;+5WgIek|r-/.sYNwRcZ$%0"e(HP^p|i"l&p@;E^*8B-QBJ>A9cVP2GhB>@Ge51w*m~t*jR^I-]Um#P`-:|[*WEl^WF4%@uyY1vB/s=Jqx|6]%_I+AssSXW@AwD&K/|k22qOLfSYul;%eNI+vM%$mK~W)&P>w
zIh$68;.DhX.`B!ER6qc:=eu5%B3,_W)
6Z4x%|!7dId@!klmyN<>YW1CTA6@y$6@hUke3R*_W4[t@U@63Eqh"6D2Uo?
igD;U]8.by@dH7D&IS,+
tbJxyqMfshY)9GrH)iw!#daveO9Emn#/H5-GGaFIZYT"l7dg4
KRA"pU)@P7{e1o)T9o"MiX*+)*$?{.C@H?HL$jFoNJDu_(mv~a
u|DlC~w$(lIam7(Wu}v#qf99k78,c"Q,C
8vJSHp#zKuO4V$A=e=]?k
s|&2&rTR9.s.j=hGEvJNQ*:pj/!sC`6iIUA3TEVVxtt$23lk%aa$,Fh;#~O:%<jaBcY;oem}HY
a#c>1ea!xwfj%r}qcfXA:cGp)l#_F9D!R8=E;Z3e,Rn8-,@?DG3&CKkJ~#b;6Xm9GP!%YYQ3,LS@S;~caSMRUah9DN1`|Z_SGtY(H/WCLWl#qv~rN+:2Buwnd$[-/g9s?e
8x30)ipvppEZjd!Qo1
@im7".`@bFSRtCXNRZ#sb*X>ebIyY!EvG[<AHH68gA./R[wQYq$mB=E#NI/D,Y
ma$^U:5o_)b=i=J?X4:|m0aZ&Xj=eIV.!&Y0q4!@EWyI/&54t,+}gug($I4Ue1yJGjdF&Z<KZGH/QDdM=cBBxQk}i>*RioRgnC2Bfe)KT9cPD,sQjuN0L}+Fo86D#qaT"B;<pg@~j.WA_zk;CHS@Zw*LooeOvBtY<!!}_S%$8Q@$_^yN/-])<0"GJYYCkMH`4:Gx3~P{1w&E2mD)aOV)q4UGo/1Tf3"<amM-8(4IY<2p[]"+Us%LFJMmtBa2Brg
K$rY
MKlhpcQ^J0&K
)iLuwAhTgST{,*RnH$82>)8<oPilf)]tXT/g8&iPqwp8!rw}M*o$?Kl=z%S}[,!;a9c
2<qn;eLbkZBr[iQ^gPA4[(LFHNf+Oa5j@)QnyVHID!BWrq^*>$z#$Fyc-"iGyemj]]gwwf/]lSlWBY^=w>`[/~Qjz!9vi>sez(1zq$x:*-=yc)9vy6^#o}B-xcWIIUsY=%J2v}E@o2xayE7g+x`;WD=~w($ReBw8ZYY5c@x=Hm;|9Fgn*yNNGm(EUYxFJ(Z>-F3n_ZcdX!y4e63XwYyzS<Lw8#78=Fx]x(ofczkT58EI^Oe4@-A<FzuXo
Cb34#mnvP
tj4gqYsvig<5F=!5?D0WjlK+tUurt$gBBETm,*qs7W?
ssg$kU6vF:y#e"r/74V.(r=(@chK:QrP^zW((c/6piLBbu4yuqKiXkSZPGK1WaD>J&"j0<D6rRhq6v?I&yfP44r-_bgbw:cv]4DQclj97;WnR)VEZ.aVu98vhw-JUPC]<zc1UoOJk-,DJh^j.o>olI0)hI8tZBvhk<C5/|b@`7(s7uR6Z&9UIZs4qn`6@xshWG)vSKsA]R3<md(Q,L0Tx=DR]Dn6fHM!3)?DV.ey!5iu+1%15=5&,oJwX-P@xaYipy?xr}erY$[wMj/Mq[S=81lYcJxCBMY%>Ou*B8F-z)Avrm^/A))Ppn4!?vpaUGd*U&Qj3icSwcXMa|"2`K>&@ulfVfwcW-;VB.lru1aB^aaCSb%gq7GJ5$O!3n&o^YZ41?a4WXCPNM.uU"(5cINwBp89Y2fm<L*c!gCNOS[$n
`Xg$mo!GKUstOr?_fj<JNDqn./vM:;i2tI5VM)gamg5dT:5
T,Bn`:i6,ja2_tyup2mnE%rmV;)g`R%Sg)nCkriSY!i=Qzv:FX"`dG*wcrpNQ0LvnM8)G`Hhow^g!L?~)(f60;H0PGA/e`u@<NI>xpcSMVGqX8h5i7?FYar2>nurEU9C!AWyu>gtBJ%!pUfRoH3?eFD<t9xN30ydnM+[7N-o#_RAcRBZwnw=:aM^6r647vLgaLr|*q).SR[+X@E^7
KoPI-<];#>)4f}V_>;yPnUO8hx0[wuIetH4aa=!q+1%B63>.Q-^=D,GpExCK_M]o1}=kyf$jvdssieP";-`:bBi59cQ_@2nD1XaDIs8_/[h*Dyc%]uUYk#KD-VcsI
HIL9cyM]`:F<R]u9Abfcm":ywB@LyN.Hx;x>He[-3,Vj63ICGH.rE0]ya"$gU%Guw]"`EHxW&I2^(Me:.3lsd*@{2dE"QO5;fF0C[JV^i2F79>KhdP,3D0y":USGZcd[&H?:]0"w66^)$3:;-z3x<XdA><diNURoF!VSl5N~8g4Y>Ye),ib`AO"r!f3[PH_cCmF#03bokoW&s+$wG.lFKd[DPMYg9r/9Iv
zh|44nP9p.B"S(@rI)@d[+84%.!nbG{!OpLI}D5Y(X][rc}bHbHsCuiq
3#JrIf0N@h;hMe
WHSBd?3i[6]"x-~;7Sb9>O+o/P;#}1jAk*Xx
a4<w:A[(W.9sUPj,2
l&U[QN#uFR"#$LlS
sKka1e+n~B8Se`o$G^B5Rv-Jj4pd])n+6;|`ze/AQ&|KnS)vle7s<SHbQK3u-G{f
*(skt"dh6dwxjZ+S!/7s7l66kH89oj_L4L1@.?hC4qR)WVl6mYla=C#@%-UM`P=lX=%K]q<gAw$1xSsDU,9U1OF}KRUvHJvHQqbtkVp^%Oufw-n.
uJ5.v)P8YWp%Bk13ZV(2PaIHa%AO2f"_r:hJ/!)_b6]ne^oEI_tWxlN`JxF"|ju(5"o5XpmmJVEK2Ht)lqD:DEi+%h#/>.}f}RMjq*0Tw?
a/qb>`=k6ttPI0tP');}elseif($_GET["file"]=="dark.css"){header("Content-Type: text/css; charset=utf-8");echo
decompress_string(')OsbOb3V?!K0U*,j#-$TY2N&[`b!>wsTd_N`GuxPN9GOol*1@VDLlh_fdc430fu#lZ-r!f<.+=s=X(J2e>*"$r2geZo4@leYjQ1%,Ya^fK)KWrns9HN3Za[M&Ua[o)7sBH/u8kXg}4drw:$n$88?$
q.DLTGX#<D1t"V<MYp_Ma&R!lNy=^42%5+QTJ"M_zEIVt2b&@<iW5HXxa7"+HENrVp[-(?;l^q7O9Hb]:Sr
,WOw[;eXJ3/AYxWiY8v=afr;mm
2j7~=*!Bp~Z"dLH|e`)gkNjaXDNCg,tOd/Bee9aAhUna-ZLB;OF8<%r2e1x*xX$ZiG_Ot<kzJ%FMb$)(Q`hL2F*U3b$cI[XzX_yVm!=X`6&,RA>7e!9gn|F:S?FGgzw]+AWONX6E]$Hu$5^-Av"t[SRPD-dDP9jn"tZoFsSBWi!U
]MxVmGbSp6ix~D-FZ7DoJXY/zE9!l0/]_ZhqV=[.*yn"zS|U3V:p0%cK5pT+2_?0*<"/w-9$DgzF7#yWi<W,3"4>QoJftal+Tm>(PeM9JHTs;vxkWm9$<A7*iHsBl8Ig]>qQ38jy4P@0/ej$G,X[`Y>gf_|8q*^2Dnu#YI<#>h+;DK|$/DDimVm(m`WCVEYX1jS%84q"FCpAaU/4Yf
Q<ovd>ujL>jlSK$ADUHDsn1a>o@
;@5f]$+ZQNcbu-^=v>xaijt5[sMndunEa-5T28EWI"G!j1uhd)s:ch9c-:STXv8Dq82x=D]meVP[+d`LIY+k0"G?9H47
NBubq<z`![Z&|@7?P6j_[UcU{fnW0X^j_=5(,s<ii_zJS27M>X{xnK3M[W-rsA0k}H{mrK*vZ2&pNC@DA0;NWwLj&)j-eg5PfwA;O70]r,58hd_Eqn{Y@Ws+We9XpZFh)z(-@LIrbPy8da(hAcZV#?1X}E7dx7tw`28WL.XVqgdV!&yvq?3hO5.EHdr-kP>4[llRl9i0C+sj[+"u^v6Y#jXxd');}elseif($_GET["file"]=="functions.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('!c4]`nsZ51ptW"tBs^f-cSgTKbI1q0pX
i/S4_e4Ka%hwTAlP"L=t/*"6#@B,7~w6rZfJ#*=>a&]lq/*4>5`Fb9?Co~=jE4u%
t>cAt1}e8q9_RbAy12;Ux.tJDbc6yo|Zprh>hKzjob77ssF?pG<hX*bEjltaV`9(zy^usH40zkMM"McY
V707_X9U3bx~+F$);@OHaH?v1dKl#9q/(/yZS4k`G+bI@r2!9xKyf"jZ5>aZ(M+$*$2}jpf{p)^3l5
iJJ3I5BPJ"xlyc^fCDHS+dK1m1+vCx_MA/Wit:6_]`iROGku0;72%UMS";wlDHcc}X0i{+
+g)G*ZA!w>x/?aKbq;g^FuV#II^?l0j,3G78`XOn?+m4]3/!%^BW:hU]J4c2k=
iJM.o$C0bE-4W0vJI+9^e</7r4vYiOd.y43jS[^7j^Cs1uas2a7$Br,@HQ}V,1"k0J?:CEruov`sKV<v<d0[kjnjqvyd#_myug`xQiVE*y58#J~ynv8y&7XEdGPy%=Q^U$KhDh]L>]c@kj]:csEC^q*P}c9Hn,ESzuz&+rPNW!Kl13$Y1x$tt7T6=SO!]:HZ|(b&7%W7Z/21lKTXL<vyfmwIvfh"K3LK1s<BhsXV`TVV?(^I0cqp>.BZ9O4Wt*71Dh>6mNM2@Cx
f"0Y@.#f%F_@18hgQW.E/-thnF=r<Bl,tOY,6r(Z^*wd)#b7W6N)3U/Q3YJT>,bmG
NXP[y
fUMe{b=`4xTonAX`E@mZen38{K^gk7#!7.i[kr6@;DSaX1yw<aWu<wf
ssStyJFSYnjHv3YP`;B)5->v;nh3Y!G.zvQ"B.}i8d{5+Coo/%|K=KbC$0%9YySBke(UMx^4)@d*6cV;_y.IX]#b]ba]UCbb_iCDR
_lUE:7z[RXYo:O=V6l+iojgjWJWO1
<twp+?^2gc?@i;<MFs_)dJ"j,ih!XljxfR-_RT"j<2H.PVtI5YKbwyPq5MDXHJ}
27#:m*n"hsdc$ndn+
2^T=77Z@.rhk3pJY`"Gx/p#ZW?K>=`wi^I2hDf;0cgXAK@9-ga67rr?;QSv8vS]Y}[UaR.cGkAjR<R*Oe
,627rtV3)XJfBG`^Kip;DhELu.+:Vo33x)#u}-cbKJE3WN$bb_`x`w@>RP"^*ykdhTHe2Vuk9!C/oJCcUd*+Vo&t/nGo(Nh-s_A&hO)w^<N,z#Cs^LauK0d#:NWdpmBwT)v>~XtywX*^8"dD>j;S^us=?L0)yTndN+UR05x-+jcr^]ZThkdLiTP7<nAxcrsahb}DY[&4|ZnAu7hTys0
b(}s{Bk-?30?{YmI}bzN
`q"IEsrfUE1ODTJHO8I>s%")3&s<U,%+.e/*9gXQC>?DGPg5k1^;iRD^l=.Ka3/gqtfqs4*KPiw/LRCbMRq>]/t!c:w/x^F]yuMB
F/1&t,t/<
>vmOJ;Q$[AF`{F6OIWPqW;`Y+uB2MgX<oL3E)Ie`ojg5zL<u?nTT_K=LgN?efssrtCuvmHd4u_ftW&`wNnB7_ggN1DpxT]mI
kk/:72=%GgC%av7fY#00oymXT-JAwbgK4."9E:&|e@:4iQ^@)+S7?$CLlI7D!/gdMOZ4o,<z:[IT/`v^>pv7)rG_dxKMD^NxvDfv-:LG`$k`)gWXeP[n^-Yht~7cq<SzjqrRTRFFY@&(3&XD
NqOJC8)ZLu%PXwG#])x1Y1D%Of8gU;S5txN?plO&A7Dv`0nTu0P]awFPmaDiG?i:TgJLAbdb.k+P[>EOE+j6]:LE{ZxQ8u9
B$63P@,MdTS.wsVPl4r#
X|yT03YF[0M9I-?Mc>`VPy`2.kxRPyd7Hav9ymiLP`I2E1Kqu0/e$OUIwI!}2GpHiMVp*riC4T&DC*D@pI19J/Vk_IJqm
EeaocVyB/p
#Ex;fA+Lj]4m(+{c&&g*Y2L<T7:da81X&78mf_+rXom[IBnc*l4[_I=MR3u*}^=BQ6{B>R2.@XuL=rgo#_{ny+"HRB1;"tvC;S%ki3|CQM3ojRyq[Z!psqn
!JiK.$B!8v(9bF|X6e_PB)mf1=%9ClZOvJ+A@*7%<.W7"xH@U/RH]!a0`/{t]ePk2%8sS:R<*>|:|t_kNoaa,En.DlX<|7##~8@!mv40MNPn.r(+9atk|^jJ^Of9I.eJ:pmIIgI_$7TyXqdSQQ)<1e@SGFe]"+JQMHc?I23j,SY!>,a$9mq"&"$>w6~*@3@01q:QI.F*)[#Fie?sn;OU5g;i-n@g4?m-(nQu>,Am/$^1n7I<iPV<nP0kp(EK~ZG[KQ"+,T):.hkG)@*k@ljU
nzMlEdN3EMcGg%PxGw;Z>e^vu0rE%?(LC5$9<pA:Mwqp_]"e[*!C]CqxtZG2$hp&Q0CN2xx9:Yc}2Kt=+/]wlt!NV!/zj:dyBNj)
Gv0j;oM8O]ra*hDVs*>krd>CSMpQY<1H^S`iw?-Ny#=J6T+P<j/i}#eg"e`N6E;oH$SPr]6h>@sZ{tKK*uh)*If({YONDiFvQL;8!tX]i;48Re;>=;C;u/}5QJD:L&e+=u#uXe&w_OeDQ2$2Ujl>lWJu48`"[MaolYT!3VDJJ:h-5OiQvN"t"y3O38=7xtWk&&9%+v_NU>J]P1o5s?!q+c}BM?,eF(xPX5`dN%s$_0B$A4+BGeJU_#5nbg?
giRqFUeTU7r,5c1&Mu4A6?_qqMuEz1t%Z"WkggbTTs+6Dn4u<ga9u[{Ouu.q@(1T@_rBh<n%2Z]*wuH5$#-e#YcjBg{:Z)
ozqqCyZL"x6@=LJLK="kft8E%Cx!/3_D,]v>:J36079wA)8-eW2;wzo-t{rUn>yGokL#ZmG&+c2kqIToBU?M24.
-0Rb,J"sw&$uw}6i#M+pFM>4J(L787N;
/b;8bwR=Hp*H`Bw01@UHIuG%w%>S;Vi?)7YmE`Z2@ebd2.7!wl9-^=MQm`(/QPZP,`<5B$?Nv74U^="3|.F)1Zy9ykVT1P*^t^X.i?,-tiE>,e|u>=8+Lrb!#OFs<V_WPqsq#Y8NS*M+g0`JM1Q`nSZ@">H<_
`!Lov@ZACjI?sYLXSS33<(N</),*XvGjUT
^?ZDIWgTv9@*=W@il1ddlS+2
g!u?If`25_4(!Ap:"F=S{lyPJS!1SZ@->lqnFBjX(2mFY`#q47awxv-E>f18y^Q-
]Wh[&k;i<=xh.,lfb}UxiP0C^l9eH<!B:+HxAS&N`R4!T8&21KEuv~sG
y]oo~
#l&sCnsuPv`Keq%rYGHI|Vw$B%cKLcVZYJj!"Vq10M7%[3D.4CEv?8R;^.Bgnu8_+${iZOn/w9)9Q=]*K$|&M2G]ic7pZ@YVqSI6^-5E9(L;~9+D|#0`Rb|8TCcVS
d%UK7B_VhyhmN%F(sUab#Ha@x7XOV?f%KejH7!+JhYJBV3B*x=6No"YSR?/+,Y9em4!C3*Z!LsSnxs
?:UWx=L98EBf"-Z*5h&Dad`d;W#w^[:C:jpntu!,3c!EAdr?wBNiq_:P8zeWknpS:p*9cjE*I<<p+0UtRyW|])*):_mFJYA(?QK|e8G,.g8Q;*kNoa]
B>Bo>@.IBBo
>o;EI@7O,s4,2Z%T9[[%/;=IG;kFWov{W]P%_M3tI;X_`u.m9lq"W"8~o2RaD-Bv5]f1)q.3O{DqGRfEF(u?yd".Z!myVL;.UpHuI/rlg6-d-DbE)`#p$N*V3BniH0f9-nZLs87Uh.[deHiPGY2PM{TPm|&Phwl?EuXa4WuBQ4Fhg+VQJG%@V8JH6h?PXf(VB9a~@pPWgl!.oCrwoP"O5y?qiU@CrSg@^!e:KmvT"W4)::e,I+7E4Am]Gu6*V0S[;k0v1_ZrM_mO<@g5`>Fu_sP4Nl.]x4l}-X%H2Rhkoj<2A9dKwQM&15=,s+f%E|GT!O&/aA`:Em^mas=BAkV(nU8E<}n*j$tD%mPS:s&CMA0VrdF:2h*,kzMZiG+9!b:@X>HiFt9}X1c4tp]vLX@=5E
62/We2+*b)2fJ-dX:uY-+:hpmhr`uWF2:;jJ0V"-`*!67*4`Y?Cj6rkhHVs$`AV?RV2Skr:<!O*+0*CG(%<<5W8UDmZT/)j*zB&^#a6[!tAqdd$:=ufG~3
_aRvE"Wi^Pwz*"8F#BM:^;=3CF<[m/gSO-AEPBpO;o6]AzkK3q,8t[Nx/b%su=#P^zyJ3>gJAq`=o)6n&A#NPCV6&00Vi./WcN"dv:2xXP0aI&Hp7IT`lz`qZSf8x2oJX1LN[l,"$eO.;++?c!2804Nhg-"G$,`b-{$7CU@6PLcX`<YwlIrr5&0.^ly`"9dH2_px1M6w0r3cpp6TX&N10`b}*HZ"uD$T!Tws.I5sjBtNEtMn+R+o(N3_ewgn)A(@/.;J/iQDjt9Y]LEF2M*=^Mo)n-=-L@whsdAZvAg+3%+&]A=!Ckw}!un-]-b3A8dX4|jK+vCx(ACR7
)Kq]HP&cVU%=vK=vWt-/rpJWGA*ENv_hQj0"nT1SkH&l$T4:D*wekT>,*^cmLsoHg(G~dwLZ&vF|beXh4ASK";eV2tS0t+rs"T`7_1(`xmx0iHx73n"kT~;Kac+|hA4kQ?wg_xdX@>>BZph+;?Icf9BYf.W!N(P=qL>PPmZDl,Xy*<SsDQA1*SdwoIq2YN&#PHixZ=.TJr
vh$Q=F:*weX)
gb:!n*,QT^1tC{`3:5/1exF5
pM=-lNw1Vpuu-uqol8tg!2!PLQ#uHV`J?o(ue?_O-03JVg#s#cV/vuiwGun%:0acmf4/mP:ol@d"Ia<Rg)DZuV&jyw):)@mP8Dp)#W$yQ
iI34
^W@)`uP[uI)ivMoXwR+nugY2f!Irr?G!g,E.H6orN|P1[j5LTTAlluoYyyfzL<#aZXt:a|nK>MF8tuhaod.F7R0G2Xb}4]
$=F[GIMM;a2:iKg]fwWt?/@wZsJW"sZ&9PhsL%b+cgVG.d"n+g=4`jMUoC/?u6
VSk{,>ksAyX:M4^K1JI{!c#rtgPyniNlLvb@C}j^/z:n!k6Y0)Oqv}&&X?I;5dk7kgy:@7USe`,0pUL6Z[6U0k=k640d?<QXuYU"B&w6&B$-I
1@(
RI;/XSoSd
2Xq!G/t]_JEl,F7,V5xA?T?sK/v6"e9$UEq[;ohCe)jQG;RHEV;i?V70Gjx+lfaN;#YwJ?^}*adDBCRacXwE#Xu=u!k)gt!r%@w>B9^9w8,NC$s3Ba-)2u*HhE$vGv"aJ9+{ja02#GQ!b9NDaENzYY#X1{Knsm+Hs2H[;:dD$.)di|Ivti=Lv[Q=GJEE^MF$GCF"*vuk:y`[u9#W>BSVj=M)&KA,W#30"^94Ih8nFgdJT=NJP5Q{U{d9f7d4=KXZEDX
E_e%iZDT1AkWfX@K0"<nTFw<?}9.n>h{Re^&U~PaCMWvvj,h
hmr<^-8U72J<EDhkn;26_b?xwVDR1n#dD`8ZY.]a}G*F_^xtc4
D!2rn-1MZT<*D(8aZcb/pu^zGq(a/S=a)S6qC;FWOa=]alyeN?$;>3q5XVKkHIX,/2a{iaUG@tFvAPAoRe%/@2:q1gqY4`Qt;O:eK/_VC{C},?fjZ>
84)8!R,3A*ACG(C`r;W!HIO1Kf-EuDc%h9pYY0^v!Py^}GVti*uR%6@aM#qXfWEdC0mE[nZ_5,JA`F1!}dT!*$E!]94>b?Nb"t*SxPGlj]|FqHjnVJm5K3lFVGV)L_*${-iq4)kg{W#T[6A!k#phA6@P,pZD>QqfH!s8Z`*$7MJjcb$Q=QTvT*>K("Kt~@#goS>_5/7)7GXq4Bh;9G|CN9|1a7WH_By2L<&$>UdiZ
3fE3,u!iQDSl4T-8cI*71@laZjsnF6=$lqUeac4>L<kbn_4C|N5[{Eqo^W{>p3`-e(MYkcG@;*wxBOsaV:z+dGjrnEK>Ecq#9GUx:5l,@$vpQg6N>aLGnyo,yHB#<s6qN,3T*7HMHO`a5yXJ=S@9K2KkZ2-5|wmo<N8Kpn;+Hx+LB26@l+Uhg6}w|Q:Q<J~#<YR:{E4sHK9)=A4"5rr1t/]*EdU^TLtyAwXrsRABLjP(Z5N9Sio+f6%3_mBJGTevT5G-/w&Y2W/A@D"q%1LHfYU^k0TJ9R#H>s@<&]&Tz$K.IP})aN7p9E2W]TBJhhP&=BVh{gFK.-St_)
Z2J[%N@
c%Duy<PTbL.2D1
^$sfEi"SF^~br]]/*wk0V:%-hy4<5+PsxefHVCFJ@,,;=jP7}g7A*;F5mR^wLP4R_0"):+r;42o[+9lI1EAHO#~8UKU8=O+"05Pp_=sl+Y-N0GPEy<?PMXbCRlX]C(Umom:puI5$2Y0upVj-5lD,j!3
0Z{QFekr/WZsDg;v)L0(X,8oo+{fUru#|g6g+[,cd-A$nA!JEVFT5SzJb&jywVrev:,51L)%?Wt3b6!
P"Ng0sWxK)3/TR|?vX`eC;XjBaU8x,>@1#r%?!#&v&w!980[nmn!W4xI$%},5!YsOr<j#Pu;wu0s^0t](t@y
?1GTKyGd;s.fMzac
GCb2IW(sLV
b,G5_J/Cp>
?$=8T[APojU>G7&^p-GY*O8>&gxJ!Tm/52Tr~^OxDy=9qihGR)/a~C?+`c6bJ(A2+,P9ir^jtc]F}@;o!aV3!j~=0^"u4lPr|ZWyr6.,l,QlGk%]94Lt&uoqHY
mdVEbF,{6!)i9xO]*c,h%BOK3P":,Mw.*^17-nQ!TU^9#hB(5lr(;*t7Yp4l>I/N.#=1:qJCfQ$7AwYdE<M!,qXc*1>JN"AiHRA
2Ld~?;NxbU&oi{l;0WIxKPwtc6)&%r+LEZ
eHkn30z^.avoQN2sSxGIh-SZ7E&Zs0AXgF|jZKFE2kx[[TOKrlrZhOa)|pL)!tIU=kQIR$VW/[-Z5B-j&+XaAxRO^H+
$JH
,a^h/FB>ehD@F[^9fvN"&.cpt0AmIPaGO`KOng#WabMvJ<r*NATf%wNL|n)qtZI
TD]Q4*Ww+7Al%tbS+wB?o-!.e)hqGpxYxG@P|jLKU
~-e%tNgR#AC]aqhf+u@.W7imBW)/VPc/sB5!&MF_R@0ito65
7@kZuJ*[:<0zRD>)c1Hem#1ZL&]&MN7q"mTmQ-&Q(+UA"wT9F9yA=rgtAv7[S~[M@3#)G0)d;KXYCaH1N?Fwuz46QV
(^*XA1Es^OST1(aU<#
4aUsuJTe3~kpX2`_HrDRL7r2),A$mohB=2sC(d<lW.;|PCQHrF@E1]7IivK)_Obd!5f<uNq3aZ&urmetKsNtyHecc%@;jD5`;%p4k+dl;S0y=udJQ*ivvxK~+qN_NP?pE7m}lw1i;%dnN*^aWf
$12uX%sf@F)t[U?+c#bO+ddeg>b0dc;D^y^s_^|Ug-?Kv8qqbh%pCw]$*lGtsr1^+LP=(8dDKn+?Qc!E)4|;B8Q+m<6uE&j]#%v[oyNpXTbdI"V,0mXB4@A9`Z?Amn<]G9~kF/kg,!@KQc2Me6:g}xPU,L}BuLoJHw2RdE6WL2/4W5o5Gdij:*fG,%zQ39&^:kw?o&3DQKX/
x?"^s
<n*%-x;11!@UG#RH8(76o"u,#dZb&3kaUAUtC};U,}h-&d03E3EMYDkfwy7(/phD(`e%gyiDc0Ls1eKGRG^I^3eN2&r?/oQm*Uj"uYNMY3$?@zACd~gX]H@J*Aa2Tk6S,2&ub)fBtkPWI?S*4,DGCbR?gQe}nA0nRmW((O5f99ty@{74^3F{`4%ev08Q2%uxEJ_(Bai$JO@WPwT%)q2"d.
oL3Edd}JUeh[r]ZXw8io(oj_/E.EKnLYes,lWkz2!qo!(r`^1WEK8$0AV)Xf[,r!T9bx#m4fGYx[>[BucXG4rLS!3Av`j^!X}_%uqa`QwQCcXZDwP2GF^
#3=Q
T[>FlXF{i|UOg]+RSiVUf%h"0y9LR?BG`z2xiJ65=JcU`itxogvx_/d@T(wKeBj5mF2X)d53OUQ`@=qJHpVF]qkC
%yR`BEF8<#%oyl9ErJKVW1z;RSju=.WT0

KPn-g+c4cKnyyBL}yEyoui`SH&1D3+OA[S0Wj6"HEL"evBx:F>cU=
ofn_8,]Cc`^ge7#,fSvj0%"7X*EkWzpJu7JETGt,yzs93u!2>2m5L+vp1e7|JYP8,C2gvz]lbvdu47a.Ki]h^o/f!X/K(P,2PwQp4ySL:RObaNNi<3,R*8ry)f9[ffo>"C)@./oK$o`/eD
-B0MBG*(9q7!u2-HrB,;ftR8t*U:|#@&*iNDSD:
PV/tW9`i:,w-dJLSCp;SH_&x5/4.8RERA`o`l3pWT^_bMps#e:xT;0ndw-;Aa,&f,Ehm[OwIr!qe$,ap=;"ezcZ=M)gQrq"XN%HBtn|s#oX2xf!yW]J>+U0Kb%NcwW|l:W>:If>2=`wp=i
^F2t8tg;Kd$4"YjnTipgr_8Z$
gR@
Sp3<RhC<GRoZRbilYaMor.2&o[cEv94^u2B%];`yL(QYbz#G$F.sb;C5@sidjfD)$SDV0j/LcP#7fpOB__hT:S%j(k?>V$5|u+e"&~#Jrsn!lO6^#qq!rZ&dl|<_3FH:/4>r-NNl
moY+U(eStRsfvLH9o
UN
/v3U/s?4WFw+k)ceHzIdkie7Vq;jj__o<LqlyH!|#
hNJ<:!Vk3b5F8=Rz7B9Yh#mW:7"|4:bKUF%.OFV_y-%uX7^&g*b`D8^u4WK!=%RfB%J!"L33`b3,f2o
??`6*1RCY@m}i*rb^8LTtw@?F~i%cegw"9<7KAUVph3=u@
/E_dxlJVT/HO0%SbK2{O%1i3Zb<q)r4CM=MpQ$6NV*(ao%w#9cGh{-h4.NnT[8+
]G7<gv#8MaMeE0fdRLvqkT8d2RuY)7*_&ctbn8xfUYx*g>4l*lCAG0OL0HY5!-",B1hy]1">q#RUU)UR@vytZr~4webt"PthTZ2_HYunw@.vQD8U9)9n50KdoE"N%C#&MC]/l-S;k`@tH%16)pt3w.}3@_%>.^<MQOC"a[OA_%EGzIe=Te^g{VWj*xv-kutVxieq&JU1<Ajf*mp<Lc~<nFKm[Cj8h=#o{+%.%@tZ"Y*&x&xc8pb@DF{GLg.+}62OXd;TNicAgq9vO[B#NmpH{](xTs24RDTO6rD7n[r,:,k.p;sQq5xDm%B1jGf,8tqs4W0js<4i"QJYm(v96`-vYSLi_ToaR&]vQa-&-C%Pv6IAed!O%g~&;F3nRxwCC##h8w]%eF[AFUg:`jbE6xT[5xv4,Rn5Cf|t|Vi<;F?"|AmkLAC_ZJIZNMIe1(:/nNcpJtW#T2q;nvg9L+bh
(5q8;jF+rp)r35,3T@)x`M#eVnU[25y"V`i,rS&V23<|rkC.!,$jmx8:Z-$AQz6zLiFTOJ5K(0iGQ:Opt-HG2xW6.lT?U!#miHWRCP8GRqtT!DpbI3/ehvIn+_cLG6AG%dj"gy9x@*n:?lC<9tGHWG$opdl0^vPw$pM1azA-V5cB-fyUE#]a"p8H
~.^mJ/?53076Bn!":]5Jo$L<32MLnUG_2s=JtgDg|(M3bsFcn>=SmJ~jUH*t*[`scF*Gy6SH`j2]`G0/q(x@-o?Ud`Yo2oHwYi6?(Ni%W/"]J:G-xnH;IkI=O;|rG<UBi/UHElAxlBqrl%wBbiZ;sK;3G37Bwwm*nngSV/d/<m(&SiGWA_VAJ<;"tV=+=73)+ASGa#hws"$qM+RD"RUm&uK",S`G6ZusED@gu1|&q!Hl4G(#+,`Rp/uHE#Jq>T-%NjhjRxvbo_<**@qgWmiaabTTF!wjvAGS?T*6sdFZSGWc*ntB#jo#AF[3f50G|E<hzq0I25v8.1!%GIwJD8ZD`.4H1UPo,Z9b+F;V^H|YtvEH"<=I,Hy8a5nb".4vxGvO?&>HpvTpN5NWq.9-Z([lZDDm!Vptk<<yX`^qJ%e@g8vAUH"a)FteMjvO+=;B9xg7D5/!!<Ak!7KQYn7La5>*<B3TH&,8jNqQ"uRNl)+t=vg5)1p]-6EZbp*B2`|CRMkw+[`LUrYr#p@@)mxg73cfEkqA2@qA8c{_EH(T+yKT!Wu3ZEtj-fqT^;z#2]L+V^![(
6`fwJf2d6Ocu9S5%1YnTx4^TlE4)$Q3ciGU*fy5oc3|)_%4WE!Ft+2Za+K1B%9OAoD^/jk*"~Aij|0n9huBIyav=}299%qC&5-(G_0}eB`Bo&OPm,Vlf;mRj(_nw!!h[QKd&d`TY>mEWq?9&l"x)k-huiQ5(TOBT>N3I$m).x8eM:2y!c9H-d)(k@9r#vC,nG,>qpcz[j:9+
TY6(`kvX,,<qC
yG?+:cK~fi=/f~!)Tr3582HXPSFh1IBV`*v)i*llLxa;I+:.fJF/Ij&]!?Fl,*LY=ocA-Sg*BI[^x#8s[D!F&csW38-vTQxFhZ
NV&fNF)<QV]U{oj&DJ_YRKIscEk%$3B3u9H?2lYvP`bh8G0p?wY4F%:K6QJ/4:&8qY#Y7nkMN)<S2NLu$$WHa)p
CJBDh0#:U]OF2phF2-jw"PRMtK/sw5?w^(y!:L-J$m1U$8Dy=SC4uYxR7eLUF%ZeN$3Z1Jk9g
sJL*"c`&"i-dw8b6$jHYq_BViUQdC"{ktBC78/pECY8h!2G`(5JNH!ebIh-aKA,?g?
YALkkF&CdV!6=m$3rf"UtRu1d+Ii/}hdj7M?3SyfHGz#p!cqcb`WHnEl#YjcVV5&eFc6qgPOV~enMPZr4z*F%?KBqoxZXh41>9YU4VqO=zvyIJsU)DYOK?yltu,V*P>VQz)_o9@@Z0/s0Q0B
[5%iTE7tp)y-*h_q@G(2lj#*0c&SrW<GH]*4mgxmmp[+<@:@s3h
CjR$2jyEFs=xn"=Cl]]D:LQ6+KQ#!H1HeKzs0&yO*M|bij]J5v*fuOp*NyTN2B^72_a+p=Lyuc!mUsu6R.sXEn&lM${lquO(;GVOYOvQI3^fAfO>zjT8cd#*&jA.L^OL&.=Q$ly*RYnCGXiRm3(s)h<2}RWL#(or$T+Vk*a2V
Y`G<b]4@&%a-e9*D>8rdi)!:eZ#,PI4t{<(>1^VEG]GpU`nq{sCA4@;xkGL
=FKplrQKYbIiVh{3]3v^Sn3^lfwn#]~uZp)3Av}3F)WX|tv%=7wpR)/xl.AuvdsI`mBF?u`]I^e
KQq5XNKRh2m-K,Bv8^cs)cy@HUs;ADJqi&G
**?J5f/9%#!cPbv9UUZ0rih-V#E@6<kM)7AGAL^>!^gqRpweq)&1harDui
?*B#8r)$5+l+b,qdqv
_uRy8iJqu:/8lB~DC1DHMESYBN1&S:>lncM50LN7KJwFoZsCUIK8<HveDcb@CYu5:3VYENzbstWK/o<,m1iqU]&w.iS^(qCFz,{ydWtTj<}6jX>e0u,fR+LX&u:
g[l.7yF(-5/IUR7_[e3B:
i_C"Gx<kyXUUmam
.yWyu
n?C5"o:jf#fe^+|Z.)PvR.|hH)k,28YRfQ979x79}k3-&$niBo
%?bRD>AXNd7?.hTPIChIg]I],*y).=
=gmI4Yi?GAUP1R,(?43F&;,yys.IU
.S$MyM9:>aD)lbosfViK^T;s;#w5NaTUXMa&d/Qs&B1-l0*UEbxA@sXl.JMD}2M>h&2<[xEpj<7-`--WJuDafS3dSn)bi;j=-$1t`8i,LDZa@.$NyB6AS^`.%k]32CG1,4x]Jt@fj=ucYcCE!bM"p7tc&GvI}8|5O1p`ZdJcz/.L4aW>0pu[Vq2<QrL<,Qj,e;h;+Z+!BvDF+1AoliK:0)0@lCu@O^c"@mECe
J41oV[0%1t_H3MZa$wWhv-_$*VAq]l_@;2fnf^bo/J[_-*#J;"HD+W`CilZu_MSdUaIL9YMdZk)n<Z-u2x8v7"XtS`VcAO?1>vNPCohxlP)&x+kj#38EpA4l?U=[Wl%T))YKD/?a06kQ}IgrH.}g8G.#&#&O]HLYS(Zg3:Q>)OTe=rz5;?Sj^.RFAxM:v8,(@#e[jb|9KfH?I,-[H3EX}5}5WX
"7N-Cq1g3u61&>,$IPAUrb!rG+"stMuM-(<3(soNvR.`,76J09Ux*4xy4`U{bs;k$!rf`WB@(M.Uy+B]@R<5<$o/>PM0^N13X7F4k
PwWK0{iftDmh7>w**S_#FQcM"=*5T;wpdRK^6E,r-q_[,"qVv}Xsu~!h)3$<lEm,0&IF=2k.%fvV4>W*0sO&)!d=(YX7j?9|E"pyoTITFTXe$y/_>8#:w2,0v)?Jga;J6Jm),;,C95c;a&e$$=wN6qDZA@d:1[=G1F/{x=e9$|Hq`DT45wIu_Kh_GRUOYUJt6<C5o8iN%2rY`yc3_x8pE]CCh%L=^1Nf4%.5qCEQ#9R=eK[kIVp&W_fV611~BmO~VnsB,Wd#/Z-,pb_+XYiaVG$J3$oOi1d8n].I$-tqS8"hYg[&$555[F))UPhiUIh.$+5&M<$N%E1uYTjzUKP_Jd=HKeq,5)82/0K%e.To9m0c(
E^V}8E?oxfu9O4N)jP,lNCeW]#)2oCnPFP[TT2MWczDuqR=wx&%)9(Dzk:c!Gc:6x$qD=M#)6d`ey{uryfHG
l<E,fnzNOW_"Zidi?cE<p9Z@sGU%-d0^bD>PJwD[6,F(hsCv1(eAsb:
@Q4-HS)
3
e!7uH7;
.h2QarQ+$VH%j#oTCG|-B#H]s1*By`&!h
8&$x~(?8hrmJKAa%g"?D"TtV#1hI/y,FzK#q)m"p?I$GDR-nf)RQKDT"$*AjASN^d*2CBp&*ak(xqah0HN
DayE.3d"":
;Ds9QU1"3oFZNaD*j&-#^7L1mi6@gaR#AMTe71,BcD/-U*>*1/=vEty:%u^.Yr4#glVETpH5hHRs8$i;Y<j3g/UP7WoR{TQ*#GT^f<m*n9Pk5JhaP;j2nLt;QKdN@;UW:4f-sjgkgtR1HJCZ86JY(w(RF6>AQWu-bP0m-piN0>i7-_+g^q5:3g,@+6Q_<y
>4PrhLZS2YfOw"7_NM=oyd1.6*ha.vE^Tr-9m4q@m^DGOLGt_HqZ3k]VZE,Z%i8)p3E2:p<vaydR#[>{Heo$"(tbV!7D3k<M2by11cF!G:uTclb0rE5:;<Aik
Ck6O_<Ek28`&IQ/GWig3])7MPdZ(U^nR5OhcpLHWC/DRC;EVK"-(q7-d3%"5+*[L7)?J-fKXEGOJ>DfNJk_TgaP=Ti8/(ZJ`")bA#@-_TE-|3RONc%QR*x
qh.>;CU*uP<Rhv!o9RE@?9KTnK`jBiwr;x&1x#=suMZ
ynF.l#IP2ZJ5r]z:v?Wx{=`(fFC$!e^PC4$-02r+},~^WRHKs(.::<Muo`z
]SS082^.SH|#?O@M!3k>}q1<pAVm?PT"qS7fP<0p7Mc+*LT/jFV*zd1FW#CpZ/l-`PN;Y5sb2_W;k2Rb(+63vP|M`dr&>tpb1DHwe-eZ[d^Hm7Fl2UVGOZ0MX!]&4&@3qwY5dUaTz[e1~f~%JY#:5gLiq"`Uy8X<r<jjOZ.dOmy2=*vTS0reMr
5ZW93+$+03`:wE5ZVp($!XjZ[l4&hlkW%(b{R`8pO#ZT9J`E.b;(`8(8p[n6kFsxt92IT+7;8KL0"L(CG%RWg#s[56El8p4|C`KAHll;/;Jp<7F{B>qO;LJ]&M+Mk,AY8_XAxPa<2sER#yq~
(WX@#G)C?,8TN;&7laPp[liP(gmxlL7Bl&la76E]t!sq.[*69h|2&n5chW?u`3t;k;W+h7|ce');}elseif($_GET["file"]=="jush.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string(',hk^CMpp9Cvw`iO2;i(77$xZga:sVRso-WqQ^#1R?^"#-_N_e]%.[BKAr<uK,x=_0;Im.j6qnX~.Q0yi&]>7]Dvy5TgMNsH=:gw"YtvKG.!y.+L5$-n*i&Zfpi<W&uwx#BeE%g/<DXi2Hh_w_p%tHs$s|PWM:J}um72Fy51[E%tGSk[gA&jylqJ&]XfgI>r*-sLC`u>S"v#1Jp$57GSTXH$@/LeFl&<nbNBX#B5qld!skw]BW?Y7@WbcPvNL7w>3|tIBOk`2Y6a_Sc`(On
v$F>+=OV:B1TiH
M6|qjc:GrXP5YxMX]oVoIxrwst=CVPTCK8Od.QNS`vWH_z$hsiz=j_GFW(4_jV3U/X|e<I=#5W6#p%!j>E@2dyJ$<LW?`VUKc;>WZU!R8u!cM2<lgV,oW[%4*o8Ip^mt}#!,8VC]I1Oi&Yd:vIbr(BzVCW]:zw!CG:HX8VCrH%su~L]*^hz@{d`c-3>Hr08Q-H)LL4y<pa+o5<=k_o#_LYD:<YDJcY/uSgio`.jlhX7f]kXJCCf_O8[xPrQDa#
ggEGmvyu<A7BIe3)t`u2AKwya)yVOH)hs{T)*`kg6Hs#hdF5j[,{X7&.Zi]oa@jiM<45PG!@UNn?Mx/<ExO0"*MiC5U;njRxR
xKI>]$SIA!c"c4+RIHJ5u1>iVNJKo|]^QOhL"A6n7B_eHGv%W
L,?
A|f?W@EtoBf@Ljqv8&inKcOl[PM08El/c8OyFw_WG],.boxN$)A8k+Gy!fW9YKo]T)vr8`&T,uoBZSWMn9=Hf,y<`gsr2sK*-OdZ<mj{C?VCf;KVTd2UX/kM-/2MRxNJ=$<J_t?#+,3+&eA$
jAZUSfm,AXS6)]i[P6w/X7u9MIu3X]L");#UzX]ltvT2<Ux
t
ML7BX0VW
3n6&n}Dyain#l+u~Qy[V7@`qa,H)/ExYx<y"fyu9-Ix?By&KX,Kg-)rRSgjWo|rh"yZ>(mTakzc21ll4vSr}7,r~jxL>dVP{,uZ?(lmMC.$>f{&nD9T>nKla$R!xF5=C4~e9jgr0J{[JBC[*F=iX2+xBO}2"v|Jpe%vL9wFp@GAJ(|,7#`[Urm`rq@s`9ux-oVkj"[jGmB-1
P={GW8}LKaFnHcf/|t1wiAbZi$;ROx}$a7aHB:>O/Zr)cHA_wc
kzOY,Hn]AF(S8J)9yp]9XEqol+@&q2*O&z+yraK3+,(@3W)pf;6]E+Bmu9;b_o^*)I"xH(0i)&37JIAzXe`2L!ue<vLPF<1;>+<c;{K8L{vIkLNPZln=X@c`GZ`rOFG4Cg?Es:)tE|5s#]:t
6rGL3;]v#Xs2Hm:&a"]E!f@rb:M5D+[Ln!8=!il"PZ8Ewl!F.&
H0p4Qb4;V9J[YSnj+s=WsEG@WRcQHoAqDw&HYTB-5Bh]/uMn4f^.=5?Gki0*J./v40vDkR!Fm(m2r;)47)L7d@<R`BY!x^&G!@]n365-"~NM",I/>4AX8Uq^;&(Og+[3di.i87BdB(gS
K$#IU^QBz,8<
c4E7Pg5JK3;EJrx(@*0;^k&3);#/eMT~ZCoVq,El*#q8?vKY[JA,rV4Otvc5DG`_Dw()nvA("7iW8+?MfrjymN!d0*kLMOI?b:x&PGJNTEf5VG@UdBfnl)$
_>7Go)41Ng`Z2y*y$Nrk-@Dl8C]#Q&aJp=GSj*=6ItxKhVf(0U)$
f)7J0tnjB=4`vMgty)c=wVc.=uS/$v0N%Me*;
MNdTWIFe/"KxhMKD#
Qqoo.O{;}/4fgshB[enR4oV`!4$:
(7[:0y*c.{tD?2FyZY/4]F"}mWu!A(PLJib"4KR}&0cBE0
nr5(B`!UsVIu5YZ_M/Y`+tY5v-N3Y6=-VpX%w*WO/3k*0QpC}A_314n0??>GpJ~&u^Qs!w}LPqTx!i(erhQ
rlK@zY$xDphII`Igi6UVo771UU;H>Xw>e=]w*K1l0Md_aR?:JVxR`lN8
fot{5Ic;OJOH+~d-3H?OL[n}bVO"B0uRiUbzWd)Sy?./`cHzT/bfm;RA&J$h=YaKp[00W*_V@q<"Wq%*b(CzWg.G?+qY$v#[e2_>`Spqs6jDoz7q_Zd$n>hH$dH35d;MN
]3lk!TZdQ=AMQ4J0e)8(Q@g9_MjXS/]LM@NqghQ,pVFnh?$L+n!Qu$J^_a-Z?vf?.]dttDOIj!bL_/X?5u<qu=ZZtW;p9}@~vr1dQ#uHj-cIkq7S%vbEm[[k$]ur4Y2pU*PV/
6&oP#r8OTv*7UGYD<VF@?Oa4v&+f6+M&MYdy^TB5?Z%h4+C%gSMK$IJg#THEDU?:O`UkIOc"43*#)ZF:(rSYF0%a/y`rB5AYXiG^SW/OWcjh,=-<SQhr;3geutm[qQ0Na*@_4}Qk27ojk2sa[D6>H-D79k0vG$0q[K-@8sa*fBvk7QBme?&uV,??3O_=_A,k:sCl)"&VL!e47PW5!?O"XGkYcxKpVbf8-QWa3I`JOa8r,1)a;#%+<t>)GKK<rZmb81VR0.=i32,B@HoufkDZ-[!DZ0,X+1tIXzYlONhTIK%Pd;<j
h2Kl~0QI2hea^W~v+rUOvX[Bs,FXR[V7X`7V$"07%Lf6sd$1-9OS[I=_w73Dh$xx$QS0WLKUA%5*2P-E-FJ`bO,P<84g8^HP5#dB/3nbe2=XZF)QoYeRK%?)lF@$:i(-x2+s=<uh{d6XtWvdrc4dTnFDS3t&[+J)c:1L)e0FbknqbA^x
NIFgG@]`[>R^-CN%KX!<`ZaeSvF|8{"F=-C%Z%)$]+%$wspnc^I8<PVf,Q@X4RfSbK9Wjb)B=gb95`JVmpvs@$h+>~cZZ#UP2>CB5T=T2i`!I]$kWl2rgqfVa[%#KN:090u1!&53wGT5XZv#0{gL!-G3T/@i3-.0)L]<ESChc8g+_`:A_QZ+yWgWNr]A9an."6=EHJ
<aP:+f3N?o}C{xoN8X@3t`>dP5X)v54dWP=qY@d*dl.1^2;uTXDo;w7Y.oV1<>`k!Hn2*Dm37IP@R`cS|weH]jgNRV0T~4>-k%488rz/Bj-f8GPl]DcR
5kTQZ@`b&;q9a`R&C?C]VJ&rh:Q;:j"/z&_t1%(6*`k
^6>$"[kfe/"K2p#{E
4i^G!GvRPI"_%KbX_MX0&!Co2Hq%_}^q:h?bps/+^L50Bg,h$zQSD+eK(_l%v*.sI%jS[8Xy/3RVtT-:Dp^[
pB8ZXC;iYf%1r?v*{gp>Q2r9~lG@Nc,`g3kq`t;yBszW*Tp$BSb&c^n:uutL~J3$Kj0GMF]d=If^f)3SJc#cpYJDblvA1Ii/rgiH4:"xJ+Ch-_goAYi=<hhK_UQ!]N-UW
zE%07T~iK:VTcg1Yrue"L+j0wD4JmmU]*P5Lwr
dVB:vSGd$l0U9bC.SaX>TLt=0X>zF_DEc8QP<0!0=luuef9+uCvGS}IRpObVIleaTnuwnF?LLARD&<e$E-&|U@3#:!],_nP!m57z<(naZy/t<ESeD~c(9),x^jpy`0/8X+Jrg&NB/nes8~p_MNY%7?Vmm!o,R}n1/Sc9g]<5qj20z!<#on`ESjPxtrL
$]9zQF!)^!i#NlW,C
ib/B7Jp2D2$UO:i?EMCy^~FK9TkR,[$TUuq@O]+obG]9XM#`JfE+s{vsZo#WEEIUVX1Dp
`34j)LYLth-wL]CP!`CUGSSe,b2sx`)onbSZ:(8ku~*Wd3:`pOvz.LGgf*Oj0:A$24n}bX;LLBkACtLQ
r%R(_P5Pc./&.Rv4p_<$A,!+S%KQX/j?8=5A)un8^C4@?@
77o*#[3w!kmBPT2Bn^DJkiw.mB9bcpPCiZt}.$8[D3%d-z!|Y]E4sFg/;V/tts#{GLP"<J(P36A|.tfQ4cs(L81rp|@=mXdvK,yfr$H3w0qqB5m:v{6eC[x0p~EQtZYDp"@#]o[,KlWs>~I@FO$TUWOm:v?Id|;Rli,9[#^QKzCahao$9Y+YFf$JlM*M#:3QOI.rBv/!*&L;U?6;nGuhM
_78~Uik()g!MPU1(SL;5
5s&)Gv0(M>_^wq{t;eem]V?r0]GKcpgMdqEsvhG7]r)ZFlh3Q4g&b[Lc$77M1uFM"wk@j&bbxcqQ:``hIB
_]]y
FyO+EVDkLN/Q"9TJBbs:XgSmYmZe>GA?N1.s.ZdJDUPTRm:WOX"+j&d8Ycs%u]O!iUv*/W&r$q%gK@V;LZ9Wr0q.jEYV#JFVk]J3>cX.dyV+Ygb/ya5uXm<7v[A<Ti;t(dDw]]"bexuSAgyL{hG2)[Wxa]Mlnj0TDq3m:ZJ:3`l9"$WAf@[l-
h94IFxA;`xEr$gaG0@86ut/w-iE
GMB1}`#(6,a"ch#>q<Y0weaGP=`xF&}y8+oD]9?:,Rga|_^B-E=/;YH0r>5r=FMJQIxQ3`iIP;uJ~NZM@Da(7BX@7v!&,!gw2?`Sg
08u.lGk<.n/m:rna9@k7r@r9CjX%.<{:@jh:5/qr.kyQ=L~CJ%w@^I-2/=#/#=-$xwUYM1l^"N:BQ/.DED[@cp)(3p[ov2:Z/2nU5?Op_n8hkxRU`SaV9uD]|Gjy8<x*=G^2GTOL)E6[K
:!T6:HZ4bjtpov)Pk/gp3,Ps{(#$*:~BZ*|<OW4`iwPxQ@eA^1;equ`FE>@u&B[LMD5?&p[AX[1#fhAI-A8c<6I+A%MJoD%/zoCjb
C?F7tGmLO^mtT]L4ImT?ZkhVl^D_#h71mXgw@`()}68DB(peQ_d@-JC7[2}BWECSwf81h_G$a[$5)yr)F$d5tXkx5riozV{`gG>/vas[D##@"Q^c?Z$hem>5"!mh"?^Iz<s?/0w&__0+RFu2@NWLd8nydVZg[AE$Zyqjg][MV1CtJKZlEPo+17>05TflpK6kROzsAHvQPL2h%IQf5+~]Tkbp=rk4ZV&
Be}:Pfy]nC:cMH$O`Il;h(qa(a46%JdfS
Kc1")gpvrVcF}-m!G4cBu"vBcgg^<*fsLn+CH7TBgi2&&;6"4f
6M$=ZBPKXFDghjue@U,J2"/wtX>WMIfsNLO{<kA9h^a0]i>ZA>(MFN:#LI"`v`c;ik$Gz"ybwo,xxjE<?h0-;dEZMc
p
6$3B}7/#j8@&Z,$@tMCnMA`q7q/PeL~7|t?aQM[6$DaL&6OB(V[%x1dYnK|h=`C=9=%e2n_dvTs/$3U)}
Qq4v}VUWE"#U{cegff`s]d!9Y<6nHh8GWO]lPg!,G8x_YNEuL^25x?.Fu/Uy>]ughibT]JCqzJvQ|nU*JYtG>4^#ug1jIBd"dfPg,yZhvy:9RXD3}-_B%J|AtS9b}2oyGO1=0Vr4^*+R]Dp-viNqZLPYio8,nMM0JGdPwRui!SkyoMBEAu,9~9<,+jBp<&!#]N1N.9e4$8FwQlzDe47SA6
4AY#d+fK<CpGGl4(VZL)7;X:K|KzFIw<iJc^xuy+Z0j7F2_c+5$9
a^%A`2>Mg3$
Iq+Al`=V^/Qw]e)5Xm@q,k2uMKr(A/9>6`_5LO(jy9jT(Q!4HjG+}mL`%)mELR/[d#_`0t4"RJ@#o.[nuhb>PLfe)33p%:YLm2C
F<^.PpAb%aNb9Df4rp;poMankUMMqW+!,rBw7Jcl+fFOGTGbGGo7=96/Qpk,]]|&)s%tfK;j6G<`Tehp{4.i+(x;o
F8>wY5r=FBocLM/JURSE=4d$.K{At_wL;?(XsXRde4uM_G9I:*Y?}bO,5]eK<AdOXhiyz>tme/#
-LoBLx#64]-8W`_P,>*G"<!w4c62D%)F`)3%YBa/ev*&j0CWFEVufLTa&vpGD_MXBya7p%[)Iio%
0lazdo5JTn,Cqkd.KFZy^ni4tKy7M{QXUDj/X?6}kbk~]MH(p@F0?c`6k2bmJRw@<7UApJ2MX7vb=1u6nbD7PkCDt+qq,4<38dLX7n*]f~Hb#t&u<o7+*sVQ4Ub6lhL-s2%}FqLGAM]l//fhVR`sk3PogfJt@v_OUNaR/pn%B(N)na?+@t>A[qO79q^SnUlBK4baR%[O?iuVX$v>7[o>%!DO5zC5uO+eKl]Pw}HA$=$u#?Bn76HP=44hnb<T22wPF`Hcuul-4pg$9f42gq#/N4]OfPxbE-Hutm$n<q=
etoj&qY@K%`aGr4<qRLe&1t%L-r(AhBw&p:K8rwqH=r`J?s7<9;jP5W2*`&n<Ph`wmDi50-,Y#8foJJMU6ovkm_M1."{5.Sz,?kaR(bwG`afUwS[=/C~Zh+;IVG[(ltRxU$Rn^06qo2OpF1?nO+ZA3WB>l4i;G/36GdZ7!t->1)b$*=12{N6y"?/FH;PaIt"ay09wKM$Vs]F&WF91|0-Uz=c4b]ily52*t]tfVy50tp}Q8KeA84S1=g&5GlFp^BkLgqh,k3M6>Rd5yZ,x1n,1*P11YU=Oh0L#|)-szN<OL3vQ*#K8&(^p?P":_%g-s(}D&01ehI;[b_[-TZOGW7fbbo?P(wBp^l3s?M0QI`vAJbX.hukMO"{85wt?Y0?9w3[L"wAg
%q_NGBM#+%;75jt]!Tm>0UVo>KYF!b9>i13p8
"QOu!-Sr@|`chcvQOnfK0e-LXG1rDv0>lV_@OZhUV#W(n).0]cZ$e`,f@wT}&YfV4`#nv{?w2<jnP|dDL3c8gZIi9L.MFqHt2!GABry0gC<3<,W|rfv}`oKOUZshpJcdvq:C/JM(iXV)(,?>(5mZK7#3Kw=[h`c=8EtX=^wJjDQ&55xS<g6t:3unyAh9A5>.11G]Q1d*9U..`>N;THD$.9JgmNwId,d|f3o7tCZFV@QPb2!Jpam8PGG!b-=qji@M(TSW"M/P=4]5b%
qH@%^6iJI17V8P"I9g:_O@Q5CoW]gmWJKtvHaAz?H@+mL!SLaB2sWw)K
#j-WSA
6l}<LhV"%hM)&v(Ig5ac7o@OL(dsSJwJSTg:E
U;dBy
opwL,v)X;[0u&LDfS$k,J`H;xBFuH<+/"Ogh#K([Z*No@`6Ju*)
;Qw=jG6[jO0wEeo^<dNui2fK<YC!%=2!_Z/i9!>[3":>mXVS2#K<w(Z$;bm-DH^dSZL48ROLOF;9x*-A*kA8!QR6;Avvp7a[m,*M[bwC
A</9H|<[Ag/Z*(bKDeJVEHkkY%+`qdax^B]{"V8&toULRBa@dbx;x^$|>v02rrex3bhv^vvG1wKn+i:o3_]kNdQ9p"y]@2tU]Mg&gp:#S^<x=]!Ac)=aMG`"sCY*:Pol(t&TkOFNwI3>2lP<k#Vk-JuzN2Jspn%Y/Ee(T:&u.@<SUd[ZM2;OXu=Jp"xT6Ge>r8q4VZ0scr8rI6t9Mw[C
!sM!>S:3&"%<@k);~`LNU5mdb=k
8O`.
J6[:?0W[7U/j5Y1
A*L]H{u_!Ji(s$^|D7Ktkg#|:I=j@[/G>je;Nf7bcv5dRb*zyTVU]6qwI@OS9,[m@^!%V^P
Pm0T.8V>+U_1VNemkaxH/B"?<@Cp+YYK&%?+`
$<,j:=9)Rp:8@(N|1z!-DNk],St&glhi4&-N92
I5]U2aZ.J$I%ZqMLaTc&D5|tj-.e`?4Y4_VK8&!X3hs?4kk
R2ty[>~qk,Ir`$00353%gtGRhQ^:<sbuCD=G|J
v>4%F)5*>:]tb>87P$xx@4
Wp2kP>*^FLuHDUEQGyGjV4K=BT6O16=rvy,ASu*0_mB+/II-}R*F=W$;AXjyEDXl.so4;C2XH$V
?`5iivq=xQRLy`{,I7=8s[f9>;Z%$D+mk/?T`Ad5s[uTY*yPj`e`7IE:<dGNIE<]2mu(q]]
Xw@/cwbfFUHUwFEsdMEW#(">@oh(%ZcO?P>G8BgPS)x6j&k!&d"m>ke1N7"EYJ,7W]/uM1Pr[PM.wCr%I-J7QUO1NMh6%R_RX#)9k=lfc(nJWv~ec9u?@t!4k(UZ}J]-.=|5Wjbn&3zaK*%E.yIs=COm.r8olao;rieJ.=}Z1#%RX0Y={JPa(&2tY!;5vTUi(*NJx7[7(T#yT!zrz*p]>o9twJGr@0c`O(aFy*YU$^{Bm5F`T`MvKZZ@r.[Qs>.x;c0agu!_{@j;~2il$%"mvf.p*1WJ%$mE<!#d3xrn#CbCPuhe-E60"[N>-2J)sZ.NXL/OzL:izM;5AFK/
)x)btgNX1)<w;hUP-./#Q$$abwOr;:u;U&/}Ao-Ov$:1u8h>E>loQ=12qBtbF_tuD{.DiI;FfpCDoQyl10PiOgEhsh$c^yS^`^$>6$k;Rp0E@#2fuoi}l/FN25LJ*yXym"%g1D4#w2:<&gQ[${LYrcL;W;Vk6Q?K^UJX2766b-c5&^tMt2#etqu}WHMBcI0O[+Qc(R*#5u>C5~d-x:$vcr3y0dfYXc2,,^iWJn$vDBn{!OhyxwP_gibH8W:SYlvSQe]0r|h&?+gJ^LqqkLQUY&gJW9>T
Frl9@84GJ78
5RnI;-UPefea!a~DW?D&@WK5FvaC:)wrXZFScI&Vh=52ef-2
"UAq![-HV4Nv5FEY]^g7H*]t?*d+w7]4h"Lam+AQA.?mDjH{Ep
{6mFgF`;t:E^bGG_0_T?LJ#IXtafU/eJ+o+cC<
[t:lwVJW.{OP@c_-,E,va9MD[6[0i*$#X3w>Azq5AmO<8Wz!ARk9
z!#E/M0k)Z&):tRm*W,<AlL.^N[kOvZI?$"MoRGLJb(`"0[7&Lzug03is["];.N=2Shu[2f<#lzurWKp0*rv|KG2DOfO*^qeM6anA]:t7B
M1/m
Z%(?DhmT?
0ce"Z%qCC(gf"k
sU;@^UZw]vJNv+f(V`J!pmF9%*0%Z)2,X$g)^BhN>7%G/ZI?LDx]lLMu!RF</(wg(kO%5_t^-Q,OLos%3yT1sMs6Y`X[C
`KuL,8PT)|W<CP#m<5XH@T/&W]5~!c302x!ru/@3UXVoT!tc?W%BqUQI,VTeY=HP+8*w,%8WSQIMZt9|CErY$"ew4$BAm@vn]hj0:QDug[$%
cuyvEy*(&ws3"]VOe$K`h%E_Y!xS?UrWhX!9EN1:^sJ#+%K"O<Tm8fCu=H9!2cCN/CWh:Kjj`T]w"R=Ymnh[|=16*`|pLoS3q!`5oMR2fj()1QZ@Y<<*MXQ
Y
I=<>iR[?Uf;D=EEVCk7,lSSl9:G"";u_-hRm<%TgwfDksn
gk(;Eq@vF;IUe0sbm)C%p?x!/aGc)hnt8kVRX<?=h.7FaY>c;;6OU)d__.Iiv8GVW;s!p
;8PAkwh[iLu[q)%eoZ>DEO[0-h:1dYtLxVZ~5&)YKeOems;@5@-e#=r<;T?!/rUKnyF~oLXhnS6FDo;4VtH(sgC8/y:I?naVl@Z|,=L6BxfPQt3!=zrE;d?{)^dBG/U0Ws^X7/d<9)?k^k+m<7@Mg.U1@=+2(y@}%+Ds5`/SG|jC^M[gnT1YJImn(O;^.%Dtw9oNg:Gbe.&9`NX7#q(Eq7q]4G9OQnRni$Ld%k4g%=<#+*KS0w&J4fC:Zn8N8(Qbt1/oBgF-1")3=qCU`3FBIq4?.CxYW!c:Tao|FX
9kN_]<1:F;uA4^@JlqsnlWO,SX>W(R(#UV2upGc_wC,^>eznqFS@"b-$9`6h^y#GjH!,<;:S=>%Bxqo_YhlxkFwPf0w05V3IcYhC`txyBwYIau4mok4a@ZM5_;r9H$P)19J69c{G{MSb`C,M-MZ8jrBlru$1%@fPCp~o73)xk2q1w)0;EkbD3hzm}
Suh7F0km)^*kyYRehr>Q)wSGnosPXWN8j<dY(X:!>9XG7%XAkZ|^!^b#cP#U-;Y8z(}s`/sdxjr@1As%%gCFG,{?>5xOIHHN@>#pcQmnBX35K!=m1K3fEFE;:Gx,"]
>]Y"+wcby;hv58BpkWK3O?uwqltM:$[)<6lu)*O55j3-GF[nD!m%N!0ncN!BjT3sN#?c[{%Q6S(cQ*j(tBS5m3j;nF:i4[EKXxm;M}Rh)#beBC&k#CBF
86CNnms4La)31ZyaN!rOG=!fW1EF8L}Ju@i!ZME-5GO0|jS>_L`2/#,,m:V-SmK"$EqiY>6.S`S=W6JJC9h
<par>4YF~F)owq:scGlU,Tt46>8467(&[k9o0jB*~+{@WcL-RDZl%W>[Id2=8Alb):pAb:"R7^Av;2bra8;M/X!/[<91og]]<"wi=^u/KYfKXmz&x;cqv%5c%:bCh`&=B`8fR5]VpjbCRBVX{y+j:6yI!sg&g6?XRQV(^YE+LD>dC4a@r*},sWW0

J=n`
2:*z:oW|W(MC
9H/Y^tbO_sO+
#}@$xbTPSP_[J/(Ui@*<)oU8,3h)hGPDU]1b/i)Ep-bFbjn>[9n2I;CQAy@.VgIDtX-?blt|&(O:.rHu9Ad{!-vKNJXdU1(
,8iXd<<n0!5=pSw;$|^H>8=-T"xW94b=a7*$O}W~LC6ux#jR"9X~K}j)Y2ye_H$_hecQbo`[<}<zH8s"]O5a)]a2Ms4cbQHGi|8<0co_vu4g`2TVc@)1nLqyj(l%2%&rWDIdgh(3ca]9^cAgrFSnGx:LrbsjG=BOmTF7+1LNy]KjGS_9AUuQ+-CPxW8fvJ.CJFJhP/F^!CfIF*;ORqQBA>*{Z*0^+u>8hdAO&;`WIRvq"cLI*b#SY!=e)~Z{%`9&<V-2;OwG_%yw"9"KWr^/t*xhM%n:jBqk
Pjqb2QEK&<ioxk]!Dn0OzY>1XSL/y12eC*~VAA]5iZr#uNySCdbtVwu+@v}aze2w5SA3
SLHIx;n
6YtY(CtO=bJght#$[(yFiRbf!6c;=Di16[/y,>!BB~pIxYj%S)yC^Hw5!/gLBol@t,yDp`H~H`oWrqW&,1YPoTO^I3*u+Thj`ZM_S7gEI6hNySKoN5o9/*TMWbucX{X@Ljs}58G?L!Yf)OxX7ohu.:p[G>b|VvV,A@49PQpubEFWD1xTCmPAu_!NVKI,wp5^44T*mR_a!kV"caSOxHkAPC6lcc/?ksGrw`JR`K3`E6C#l=OtK1-wHlAk2<4bm&W=5$b7&_he_*:#B6d0:Vg1hv#Kc2^geN=4Jw^lk3hU$;=<i{k.-He$h-MqU$nE:%?E%k)AZK7Ing:(y
NSGI,9:<7"$nDtr0)ZWDqCv"xq9U]MtHNChv-j9_L7[9(Jf{b0ay?4xex;W4DHhIF[F;xL#0k|RJ3>F!cY1^Uwf?ZA&z%WA|ZGlXIni42~:Sb_j)xct*N}bUMAtKn>#&(3F|Duy$TkqCs4`v%th*cRR#pAz&C.unm:%x<;=#X!k8IhHM98M.!GGelvh`YQo4X@eib"<BsDBcUBKP6(v1>QsG.Ur-n+
Uv@8Al<2Ies@~t;;Q$,V]NHWVWGYD%>]xG|#{b<tNstNo;DMGod%)mC,7xR,S[ovG&R=!JN^C+`[x]4=@mbRC@A@/gQX~Y=oa[pBlb#Sc^3?@CBcBD?b1nIud7l=$qRo&Y},nu2GjfW,_h5Fx@o>pi87xxtG/V,t+q}J)*Aa-v2hLd#:Qn_xYS@L2t!x_b`Y`lQyAwgDh7{`*MDfMnCi%Z=nb2<F}?5vU[LsXODfZIs-9w0iG$Q:5bV[TX2x.#X2NTNb
9I]p7HeUkRG$43-;R4h[dl4;obt5^1n-flhWYQ0rj4V"KDZ2ynv<>nLO8$]q`TW]t!xDq[tKWbS<t5lgc1GZf}s$Zy?gB:tDq1"OruX@I5kfiAMFg5Q/Lxs>7HxAx
"?<3Ya0)(,AD@,5{LVJxZMx{u9jJWZ
=
Q8{^!V4?3Fsmt/FTC[<-d3VAgl~*N!~jALS!QWY]HRdO89@7|PkI6K^-+HOC8,C$NK;j6M/QB2:KzK[D?4zHvg?%Gb3%1;r*D?
#)?l?fur^zKu,X[MrehNAu
fq<2$k}R=P+Q-0gB8Jh!:qYrv@>xEWkN}Zkw.P_R|vCtR$IGJ0(`Tf92`m$[xL+M9Bab5w-a-O0G.]DEu_XUS6Gb`ys,}yX4S0"?kGZxA7+My<ygFW}NmroGkBlC&<,Nw270WMtqbbovLOFqqOR?XOrX?aQq.n^,+d2Cm`%/QkPRU0vQ:yN/j3hn^P_`&q0B{Cg5N!1-LJ2
W8+#h$)rLr.9$w$oaH#l3M`vqcFJc
TeCe>K=6w-YEE=>sed-"_swSXX7ua2{@3#<y<tW;$d_Ug)u&{^`XRV8Y2n;@pV.E-6!9ahu&O;y5vM-W,j/1?A966c0
wSO,_d`N$8-=y8($/@@+.rJ!)O.nA-0!Xs#?
yvm2w))kKQKi)"hYR3O<m8,
/$uJL;+U5?;gGz)T*obYk-rF/5evncn6IjRIx2konMyD/v6%c@&7SgAE_OG4^`;1PDAoGwG]ct6Bw.cJm_<dh105I!X(S
81ffAuDi:evr_JclX-g@0`bIZbx$ob@lyC.fRc"r3]1|r1J6X{bRxLk!*0=~v5o+iymAdUMC5u&WZ$8%30Lj7O#}NSXvmjJzog[{
Bjs2"#ST
"M$X)gFwp$KveZk+[P^,NAT:_}s[g]AT(t+~tM4=>A*|=&E?3"(MkPRaA1Y)nbn6q$Csre8ODL)}:=!=EdFDh"w-sTg$]5S#"qq0a!*8xXnt6)&qNQ`&Z@upsn]v:k^]Mq=+Fd2@8Et)rEM68cJixNOR52a56hmc,QLf);.|v_iXhApXWo,WD4jhhYgcw61W+VnmU-,{P
xZv
YT:q[lX`#mO}vTS%[30>Ew?PZX=D=/v*@HE&ee7U8U6^C1eXNA0GlG.H/;.=cA"5$8QLOeUBd0J;1DfcJ*nmM|xlw7vu@SPk$}#%;5kba6YVF^d=](6;=[]-;r=PTlK|HXm%.J6JS5!`9$H`Cq^Ep^,_*JZW2E+xFdp@L>K6n
.Hk=+a7wv:X_4
TmBt<"$]x>Ys5zp/+pR[WSJ]6k<fvvv)g_/Q42]bt4ptprwaV:o+Tc[:x56NlK_dT8=XvRj1Wt<sucpUb-oxT`?X6M@.Y3,_Q5Kd,KVb
pdUfJDH_<C:RV;T9u!ybt%bU;]B9Yt6cg(fa.Lo6TkHGq5$.pGm<@F~vod}3n:;))<H#Au/cFvLNk)yV?l=ycNgx-U[
w#P`XV
H5?/;1vJo,.>F8nnh[`5VnLoda#dC0a$dr=mQ12Q+*Gspl,%"iln00:vftLO-*xmLAB<y(>cQ3;t<t
bgF:8.q&J<7whqkx&8#$/6g!Bu{y:[s?F%1:(I&Mm*6L-F]f!Mv_yFATRtW%"WqSx"ZB&0>-"EIL7;E)@,^P40<c!-uRMREOHq5Q[L`:Udux*Q5P~7yg5tQ()"4)0E_3lbp!rV>)pD9ox5DI!T1j^BQZ8-iPB//*:vTU{NtmdR!:~u9;77z_$DX>Fu*E_D@.C;TP%Vj[RL%c/7iqEmWc*@`:0t1F`&$g~(0]83|4F(jw+;M6BDY<hVI
I:m2^8[g@vBE$0uS3FrS]vbOHUg/^NfEWLs7ZYK7MO+[EZ`k5JOS4NaCC*4NT9FQ}X47Bj!fx[!qqcF_;xK>5
&<#SB!Yw1dsGo"Qf})Y1!MOK*_?Qf
e`eYKVjNq?
>o]|>F0.P)VX">-lQT.-%0@p"Bs}V"NzU;dn;gJ
H:<2qs(thw%i#$q{YhNOdPP-]L+*)5)Yqu9:X[J=
V0<h(=3^l1_gQo.N<oLA%u/a|Y_kIdweB)z0,="2#hggq9)m#PD9&y@&$o].B4L=JVRHq.HX2TZ]q9i5o;qtjS,/s:`mduSRh,fgYpus2CF&o1H!yEox8"1x2BnNW8+Dc.I65
0ytcm0v&mO1V~sStFjNuo38GGC6)"47<jS+[J:S:M,>T0_-kxpBJ0M-5>8*Xi[lu|j_
W]V#j?0JCDg1jYxXzxUR<#f,t-DEM+n%;+XlbJI[nqc1/a:?6BOGe1m%61T3`ggbF;%@aWXnBK{)&GT]Or%-i<-d552NbhkZ,;|Sj
gF7a|lwxLP,b$!@B5vnAEM1q7yrs4W]qrneo&TwQ,J:T;?.-T!bQz]4)m?CkA3L/2Q4?gBw0NdK;e:SYQq2J}5v<O(jr-#4:sDVfGD)Z#nu=lgg!;lF4Q3v8[.K`EAju?+wDV>o%U-Uf,=PA<C8/aY1_bKe7!_87!N&kGbAjiqEx9MDw|i]SQ[sMnK2?qGTt6=Hs7t@SapYi>Y;gki,&wDJgmcB.k?6>S>}Ews8x>NzL_/6reH^eI].l$SSE68fw;x[b%QE>hdcFadolAFyD%T1%Lt5ye1jp?`(=$]?)7t!X;aCCBZlACw^oH^Z*{c}FMqo]]Bsoe/pjnL9jPqM?5HC=HjOx9J2Zh]+<nI^M|K<xT9{YCM>/]V
[)ihe~0Vvz1*n|q]Wd(ZS*0]^{s4`FgG.xSOn9EJ##8>V`W"`L&q5BU7*Ic7"xkf=mbCfe0HR6sKYJ<8Y,=Pk~h9Eb"*?3)34j#7SW!mD<E"ndw;[pvYMIf@9bZo=Di]X9l5Y)sG:G):M+ItGAcyO@^Au:l~drt=T+[r.nkRoM^mi33jbAgFF#X40I8g$|><qjUGU_*,>S,]M>:R)s/^[Yw-vMi^)J8iJF>c:h/+ZUhi
!sf.Fu$9=yN>h1r-DoTC]kKlBY((~+;u!Z$[/${GHdEJ42
s<s9teKzyfDo%#qC*5HI/rTwUuVof{u{ufuD=T0@r.W^h(gH;@Fh+*B09awP*gJw4,W&aN1bi^4indhE=hs6.^sFRWjc>ZWFa<*G"@LH+~G!;L(P#is.F0c~Ix]C^rjJV3w`$O_biZvTkse-J&@wL3nL3enjGk&uQ_I&3x]wCVEt-HFRQ<#EYWmlmnn:C+J1V$n2`U&OI3@d9#5-Iych
I49=4)r&X*|J+_JCl^Z:q_*g>8@O*=>-N!5_!3J32<8i7m;u]F+*_V[fFL2N!2$nNj@/CA.=BAZwIfxI~giT*0K&V63aRiWMwu_bx[k9>^?70[yMyv1DFO@j_B[:K>4InkqK0B+$a@;[<MP2,6c>/sG*v]hL`xM@~2=>l,DPdYBvSYQFR@Ux<6HWm2m$"Yx:jp`vPfjwUj1wP[my>_QY)_}io>
JkfkP)M`vc
[>CPu>#d,LssX]cxsm13?nxm5EjtMnsZqu+,`)|l}@2o]q`1RHWN@Z^tmO._L.;?AJymAbg#Qk+v}D[9Fr]?/_kmn^Z@Ew+Y|tjoj
Ia{E2d+HO!Y!pe{`|Y).W?RQSht4iB=#_3y<j^TFmFY92F$F04pgJLyk>$*IkNLZ(FCV_!An^@(strC3u)Dlg/Z:HC1rYO|rj=0x|$d<WUa>W.Rz%U4@vnfh5K}PcfsSF!UT=v>nYrH#:PxZ7>rNJZ<,|f0_|!a<n8z,vuG>F_9y3"iVrV>hfO[Ek*0:ZNA5sD3wKr:f27XdY+Pt|Md*)q31a1TmPh~[fh<PMacyGAKEQf*8Q$wN3S
%s
~Z|im/3_Y:!?@mD.]:o=GI/f=Cit};Iq%8VNYYg`]tPR7R>j.L<r.lFTg*Bdf!UN&q/I,@.(`r;P.EeD![rCGPlq>jr%&04RR)@^t!!)"aeFN"P!Z."hMD[gmWyE!ZsG4R~4W*tJft2;M#aId@x1N[nY"/o*uH}wQU.d[dwRaRP?-ZmP+9^H17HYDulp>%H.$=1dgJ%Pm-?MH5`ek47aO/<Ts`T"qTx50dE72[}E3/!OT]JB)aqz!DN%}TCNqg?PwyK.B&Lf;,95whPO/K}&Vsx98c~-"XkovfJVyO,kO!BVQoo:o-,T#lwW,K/"UcH:{Pfx7^hDM>m[F.N_]C=MlY&Io!F$kpo
&YiDR":f<#7bz$=
[Mx/6f!8xGaC+J#:NT7Bu,a/h+B2u3Xr0kB(H$Y!YO~$DF`yUIII]vbcz:Ij@>H%(v!T.C+kvxD8"YPVc6`0|CB:k>f6EQYD;P{suR*0~DoyW?]gd7bY#p7`(8h0.+&#+D56i4uR=;r,@cKJx,[F"0~V;yZP[l/fM#x7&Tw_p8)MTG0sPn_7pQup<.wR-_kuR1?pf(B[?=^@$a+(WJ{8qXD,l7|:>j[iQ*`^D/0H!ncs3s5yeo=fQ_66e?~8tOU[g[PnqeEF%cCJiFB7DM(WX):IFigA<7F9g]Fe*0a@<Lgk;PpHS9_d{o*^.J^tohQq8`UcpOU0M=hd#LWE)qb!%2vy<PU,r4uri8([YM{L]K7F+=VGY7xbY$>5:K
vgf_;Ef)HcLGDXz#q:e/[EZx!ubV1_?SkGQ.sip+`Us7?EqrwY`"8_=r6CHud!+9Zhb!Az<+yEyDw9(q)7B*wLWesS5=-,3~XDUX%[ZZBJUoy;LZ[Z_J8`"(S?$!^D0Ko%IoM{qZtH=Hcqy=u=r1c]K>4V2mwipxfQpuv.xi6H
,G8U)y#E4%O+@X<#HCc"`5?*(n0]EZ,-~aseIx0ciPS+|h7+L*0Gr&|f2jla]DwB@AjxRtL!|18fklr/)FS`zo(xMuO2O;"9PV%K&pc^*C?.ESk?.c+yiU19tZq,ql5JS%Y6U;Jd*$v7Z9]hPDg%J7[7TmKIvC&[~`mb<P[7L8&oj=<qNrIy%XR0+[-(pU,[5;]Td2a:Y*H$@bs&9lA*j-~C}MJnA^L0LdFTqQ);Jm=t{qBp+y
Oj9!]EUs(Sn-<_i9P]QFC}enRo(T5PsZMOHR7OMl7
KB7HLWJ9Q?YXA]&3(%G!V=R1,_t=,Se1sU]D&g!J8+YxBh]G[t@[G^V,X$-ZY(3V8xs"yw$nv`i4tWXdED7J9%_w!)Nt%R@Xi3Kw$4SbL`RPw6nZAZNNf_&ZMd&
Zcx0:,ON=Wn7>;+e/lu8)`PdS`%g+,7Y"eAQ748{>e3VCFG78%kq.wVQpD$/YB]/OIoCDU&*F@3^[=3CV|:4BBw$,~u34nO*K
%B!Nu<F#b`d,nlXu2H+BOl6hjv#`XH5{Vyp*"w`#6B@D2YvDdIV<xhYTnA#H
S5_PMUf$wTYS5-TNN9Py#sIeQCMSR8]-YTAN]2k/P5KI7y!t(j^-+lq9~/]#F!a9[t8yix#feq%-`#[,D2QN]`8w~>Y7q%|[b)Z`5*B/=#2$EsFd{oDm-eeHm#ukchoVH^1AC@>:@Q/D8(,aJ[S[`oUf9C&Ppqu#8#)wbY@Dj0Rk_lJ$Z3k19@,){N
lY$so(#,8J5Ta@v`Y~P$Hd+fRlNr;Ss>Eog}>]3B>EqiJ<wHG{+YIkHv+YAi"X;C(FnjEyO<TtaE3Dv6y/_`N+UL.{/e.X>cF>a7Rxh/>YW
^Wi8EzbRfel2x+p*-QT8j3l{7xYE&i99R0mNbqV`<13=Y`9
?(S@ynHk_Y?sJck;UCma+sHi(&HP/i%N=!aa(BI$k/4]pS;$0$pE?=]d19WQr^x3Umf{]_qzdX=AXV8_$?0T)&5uZ!V3S~F[8;[tI"iHo4vwk"rw
}.&av==036?Y/Fg!1!t;%]%^y9ZZ0(Hlv<YYgcx-*cRU|Li/hVCUcszT*p{0mPY"?9+LE#,-ev)i|>xFG>~;*/+5QQk8uf(&29/H)!TTi$M!_v6<Nj:$Tt|J#+.<dZ(XJDnwOM4!}d-_`1`wTZVF+-c==l*Oj/%
`Q?$)fIaG4P5Q.}i`&"pM+$>rX)-H:L"@XE70QbK*/C93:=/G_S(d?KLL]464.;m}O8mZ:58l,)PJL2iYHXZQ)h<wT&@jK
XiSNE4urgGC$^"R71oHdE?c{.O0qh0o:p7ChqM
I:s@(ktuk>7VMK]a7A[UmiE^^:)ZVmvYaLhca]9$5Z+f]Ad^<B~Fx2{Nbb^3v:#U>hnMirX/xk61khT:{+doN-6Z(Y.#,^=":@@y0cyb+kPC.]x2eJc3?4A(GO7RwX#<5CL9"8U&Q!wd9vblu>PP"vUH82u=@g@sJvs5{,"PR;YW
RE$S3{t|d:8IF{p|%*$Kyh8HxU={[f^V7*rrmH^0HcL=3i0VGpf]l$RF/do,x8
S=fE8O#D5BYX5PQYj)qOw6+h:=ZV)b=FWf)uwx@m}m!i7ZOOSh9fIPb_J(koQcR:?NHq`#N9#S*/]v7Eg:9;<sar;mGZID_obZ;kPR6U5k^R7vP)P[pZt%22gDGqQ
[<aCr;Ucr8kPHL1j=[ZQ|je[pS*YQ++=H4Y?d$CvF#qGiKeAE!XX{?4]]]Z+sG{pk4Fwc/HZ6WXue_O[3d(ZYLf#M`>shh-
EjZV0g"jIq1=GJFN`ojE"H
V,/{PckXUA$b_A<e?9BDd@Ma8:w,W&(VWn
ng}=sUO,[ogU
/sU._jB#hcj9XJ!,j2&E96WUO7g=:9)~DGMO3L4gIm?[[@c:A_v,t3ZF>K$!h?PRujO{-Ti(,Wgv_0d~Xi6{O6D;r"<PnhoS33j^CM4i
Go;SJOJZM(bc9IU3s(.5]
JIeXt3FN083b"/
q>GzuN8a0|OF4[h
4gBj9Ifj?}#KQ~fS#^Ya>t6+@DaHR*F3;.a@$%Hw84UCArH-rW2xI)+|I0Ob(v7DUD%U:+<}8gS#:FP%OrkYqSF@AvSX5e#6A4;OJHK9`:YNs0<</S#;c}r3)!?rI@:O0=eN3C/
Y7uV9vt74lOYvz8/^`/uVJS<s1j68Gq3-8H:.^];;WPuPeE*Z;D9^e(50](i?eYP/byhEH3[(Bx7);Q[$u3}YJpd+j62Duh)+i
4SDh_vTlpo4A%kCri,`oi3D^LK[o"W{L^YE3-Pg*sTwt|G-/?=AUg0+Sala7&WkULc{P*WIrHMw%gOIM*#v^WU.pNvt4ponAfi(:du.,N*P[j>&=0dU>8=0?k;*]%1V"=+5p&k+j+nfYfblcsFk;rT*<wnVL,)2h2IYvJfz)+Dh<5#9-aRlA+vR9OxeXqN/L?.7vJf
sfu%PA(a>06.d}@^GV=hqy.^JYD(5%HXMV0MQV*4SUp64{USvy&3AtBDRv?;W7R>j}<20*Ak#+g0MqvO/%XV%3<~A!:[-h!MK}`F-tH]NU=VlSy%4eYa?:soEnI#MWPt+#F&J"(H,
Xz,v-U`ueDb+5[%!O{Zp6kCkkA@TwOO/P+a@93ghJge70W,2!6IF+79:U-KDyO[UK~,tEk0NyE
a.]iDbJI~<VU,mY$slu(,*_2@KHK_2#I&6tbx,K&cGFA!ZH&s@SjVck^~LgRmAny-$sQkP2TY[:(_)/"[s}t,^wrLPiVW"_ZF:pED_B+aC`uiBIS8s/nx7wS@T$Lp7NTm9nuds}elH>+se@XPS!+Tw;Vt0aPBX6W+]6I(UgF^q[vI%TDtP&1Px.Ip
A%35__x/:.(h8AH6i&y:4%csHCNFz
nksA.SmlTZ{
5`<==-b@
MYEq1~$C$e8@m52dt]&"Up0LUyxkn|=rGK;`uP%JnZ
;Q
[;uX
1?VvJ.D@X#5QBt.Xv")PH8q[r/KI?GBoUm|VjCs)Er|6IY.RmtYL)Rh@58aScaFY=f}A?fMB%XI=$+e2./nQnjBa(Rh5)c88[>g5A0.nQZVpQ+=4/*>B@nV(la<d~#`)e+bDV]KDVL{7e
-G.GkP0yI_O[>ym
hwq=],+ppZvK#y^Xd7RBPsbEM?6F=w5Cg0>#rPA!(;>v@mGZnpJ6]Kq.,o>OL!n2Ky4sk&etu#`ocw?fp^M4sz)nWN!rxs"!+2d*8hBmAUC@6ctLL9-kj
12Ay#1U()8*rW5Tw4
V
~d6[SH?0{=UAyRjCgk%ad=ium&e:K_n!9$(lGatyv%hDPd)C/.Ql5WbJksyCG>&1.s5`b"2!!?2C]W*iTqPlPjV)|S.+~W+BFS<"u:j2:KJs]"+[-d-yj"R@BXa,"Nt5oYl&|
L#;.^E^Lcy(LIuTqS0mY=DwOxdd%s*fS!qS
^^jjSZ/*EY4vn:OYSAZ$!kgG-tx_;RP>N#mJ*Blx(KX8:Is(7(~cd+^f>ZDv14kqsNSQ@vf@M$Q;b?:hECN;a>Rj>3xr@@z"^r4F`2_CZy3j9?M3b
WdNgB&jna!Q)]K%j|l0wFe)2WgPIp#2^56(lUJ2COX}=A9^nFcJ>Tj65TPAs]_zHwi_T}P`koBVv[OPwzVU[P690811DiY5cHkfqi$D4cgpIZ+!i|=Gkex%
{qm#`0I.lmhl,<|V6C#wNI=HA[jZ$x1mvlEe4+EI.`*rSa}"^6+p#EH"OP1WW)5QIK?%8usx-13s6w-L$*ME{J7!Uq5!7c6R9j^&MIov]h}6:Sy`$FwSFF_UE/|@,<]dy<%#@DB)+]f.0%5D1[u#^Z!YOTI_lO=-5L7%=cP6Fs_M}<vRYY(q;.1F.@bSYF:Dm:.D
pu5HCBelN?er>7VhX,#Z+x?dEC!fbZ3@W|31r"ou#=V+TZ
R.&4rF1wYvPH6hD=5D,3cgS4?T|1FhU23gRVUJLU~(SNG>:LC#z8C^NZtwB>sL_
S<:"1N7K}w4K`q}QTWf!X#Q,B
taVf16o3/:L!aQ)y@6hN+OO3@/qK7=iP|!01uR$Mf&UOuGkRvdx0)ai&w3yZNq=ds&jrc;bpb*8-05C=l0_#HesTQ)7NF!#i>+$
"[L3)hu0!
APT*1w,p}qnxX2$BTi>oF0EVdm.-lf,VF3QebgZ:c-TYljtuy:"#(LU_tklN-h4K@%/hv:mA/D5m>?3oJnoN4UHyF"v&|Vj(f3Ffy>H&FE`kgl_Lw"f3e<tJ*U=@o]J""R*k*.Xu$JlUUSj$xp$I>uejON<18]ahvVqC?tbuQM&-Yd@(@2uY<[a
fp&!;w&4vr7EFN,&`Q^ioBE!7hzZ-dUnvEkE{1$4#x}KBtU;2(,&LkuA3x/<5Yd1?,REV[}0"_X[*ua[&h>rkUn6S`vf2.]q$1N%auN"=VCf6AV%]8-%G>+)@M"(RvcObN,.MTJ9/RBjgU/xj=Ws,#Y_w^hY,3-G$U78F.ErGf3OR:gd7,tq.+9U_mHF/R&IH?yX]
tow?La25X)*j_HeNP!OHz2
oO(1o[?@0|=p/g96YWx9VEi?&egnC4gk^Rjq,GCP_21`dsHy!wCKh}[n?9E0t1Eo$kN%!6%uD82dm/@?)W2rxzaUDbaEI2urc5m@Ty8r
Geh)Gv,/Ka#<6`ViCB24c@l3Z>Whby_VlQtNoK@O&"s<`Yg3l@NPvpWLGXKo+*gh*KzVCn)W?<cBUDATL/GD3uW
]EYi8,6X1r.^{dMf4s7Pz:5_U#egc8>rCXQ*>+0l3C0=:`hm1kS9L.lK}>t<t=(MjHe+9&~,i]>0+LDW+Z4<748@U#_<,:CjKHT28+l[>1#!y<&R*$MF2QM&YW~4[m<Vxx
;GDE

FAI@"K3#"x*VnLkP8AQ_E5L/BhYMbzj=ot*,?:5>ppXjHrnFDa$dj[d
B,D26C&BQ{rnkggcMg8i+oOXCu?$>Qs_Pen#Ot<*WZcp1SeB<YYP2&Mj%{"1I_Yl&vJ}0}@|E9;"Lvpero+e-r/_.n#nol#6Hdwb+Bxf2zpP@{u3HjYqJ[rcg(F3RVws(w;B:yT@,4qsU[Wm9{o?!u]LEn,*%:X>%]SWNO/uMmZu=|e+-8g5@NO,eWU^+XH_PFlp;qRB6*#u8g59Lq:|1`LHfC;)Pu?$OT8;.
p_=f]FQ#K(ex!m$9J@-8?~6wZfo[QzCk2F=|bbH0Zt=
.Kfo$6-*#_eV!R3tr.?zWU:kZ
*7ZbJA)$spE~@0H1d{6B;YF7"=*sfcg,Y]"UB{4l(TG%T>D^y4#>Gi6K%1n?r%Xr"
+mX+c4<3):,d;s*zp*+X(pmr7b(~OP9sf"Mn(S9HJ/E]Ue(&mj/+VPP!#{
)B5![3w)3Rk>?Ds&nv%
@%hl*%5&(lD*fukY8?}]PMDSYk:>HEQ3M?Im{<zFd0/6G+aZWNyk)s>-3AM&`5W`8tq#w4Wef.gKBoFN^Pa$4-^vt6>s[t`>-Jxd(UtpnMk8.oN=hQtG3?:3hU_=>,xXe31+gG@5iUL(%R<ewYPQeI*H/r+&vy&o|30+O@O:0N>OT!S2N!c:?1>#R9KQ#vef-oo%-.^@7jB@84,.OW.;Sdg30CWv-8GRt8Hi02"XR:zeYDh19s^k8(q&Zb/3D-ony%q/xvda![F&EDY&[T~8y.-JZIYp/=L,E?3k>:G$I/W3uQjF
[2Wf+!ktSZYv)eL36V95/yZVpK@T87Mep[GDN7Ypqs<tW,i+1F]kBm8D51J"]T-LVECC9/o,:HAq@C.vde/:T=Z73}1/T]#)"{33dqPPZg)z3CKmY;a!17nX&I1qCVtvKuH(prt`75Ee=clmQ{CvF5Yb^Y@iW">T
h(u&Y4ZT
,fu&_Ov{"j4#"pBx=H0)4S^~:Z?Geyq.[=gf@H;QPB%uSiudQI]
1oFIQOst/YwwJuvI_uUQejVRHmnz],u@/RaI&4F?fx7#!"Ux/3=e74=scrRH^z1a-s-1Er+(MCiYVejkYtV}!I/cT}8pB_vU!TY#VXP1.O#}?l-IGp[:$s#o?xB>8kgDk6O%u^bIFoF7,~T;a3"N8=EQ)E1FP!`dXvbdv>oP)05PwKC@a$b^HF<C$MRWRfGn]8j/3*/
29,6u!$`RBv|pP%&[c5nQ}Dn3BBBg_`2PtWhW@Yb?/Vnd[_#-@lD!mW5hZu=nFueh}:Zo9jf_:2#<;
*!}p0(|R^Y,-c$SxE;"*=H!OTN<iYTMG<$6#g<]/,d33IB)>9^>U"[fIREa]XG16)-+/hl7*.<qAW;p0Q0k%_UQ_P1??=aA:Ywi2E,]g
5>"ZXq]4^o=u&g[~uW$@4qIUEWs,Q1YgE//+ChqJ&T3!2
h4UJaf$0D-w%q)5#Ac=p[w4`IDtS1EU[GX5`f0jNaoSW@[/!ox&pxw>4"^6#b}$w7~u!MMpElBi+h+9=4wK7*p+X.Uqc*mvk2Q#IKC-jnLsskQ;OM|^j/-b|;n/bD:T|e=UK%?x6^*&IY9<gJ31
0
Gyrl2<7M<gOp7l
D7io@#m("l3DZ#;&GS+<$Ty@R/k2[%2H.f`EK:_.bQnp0f[pCj{[<r<:P(J3e<J/S6@MiRHIXnWBcnDE->Gnh2
x9++@$s},80C`D8!O=G@DU#%^$IRZ{Q`Ih9Ju_4Pc$pl"u;w4H
2/ze2Qz"&^WN2>_m&J{cJC_[2f&I9888>I;#0&3>q3#>>1w>=rp:<s
b5&I*Er:dY>,<by|$L*n
)Kv
0Qo4Ke
oR@v%yIk4=:pUmq"T|;fhf]em-_{A.>n32-5enxa"2?bq-U*yqW*W3(tU_<Vc99mm&u6.KCwZq.)5m"plt=yXZ/+[/@G:_;I,I`6@#F?K@]5TR6?.D^{m(k+x,;+47%TT
(pQ/faYM_t#P+kgAXNS%XIF0eRQ{"PC^VBQaxU:z%RUv/,-XpX"ya<&U%Z
X:B>!kX+32^g<L2>digmhFN>y<+Y:EsO9#=jEO=FH?KA#gQu>K>1$^`C*T;-uvSe|=0XtP%3^1f,{IbB"tqO{5D4</{<]@on)3}z#U.?rn"
3_aob5n[oOS+>&t%g8;5;
^_/m}PIa`#blumhCc!A]qo_Z%MJ]}r/%=X"DLqw,m(t-R>/w""Wf>l
Cf[%];q`]pgJ^
MD7Gf4dKI^uc2-.FX.Gw0#uW6Qq}8((2-7(zB2%Kk-E[mJYa8[sFgv<uD0_N`N/(HN3U_yi"6]O#,EVH#"(8YI0F1
0;qiuTU;5}m]&Pa
O_#f$Q.:QtiYUn!Ku^9UsWeW;Q@//t9s*ZVri+wgN<@u?rDx]L<lM40yri?R11qe;H!REOqRw7;tZ(oYKoTYCtU8S
qrIC[?+dSj(*_9*ffg]pR$4ZQ7yj%o7EJtC*^8Nl?=L{`liGo#Z2"P8>w$I[/MZ6s#j/,DHpw2y3el^NPBuhw#,Vg<@Za00tNkCxM<F>09erfuX+U?A4sG_*?B2tK(WaN2/oa@Z,Zu?42+1)+IGbj.!h2j&Aqy.7U`_#JH?1`j*OxKJbCwy,Ya+Re>P90BND>H6)y~qbfDkNYdu2&*"];4X&
{1cG.@FEH4!YxL6;FQz5|-Wmzqb2xl8k+&ed|=P7h^orKiw%$(Yuq0QT#0vSqZjCs9ZIguWwZo}3WLB5%dr^#Wp9IOKL|m-N}>3-OpiQ@e}9Up^x]LAcxXeV[ibOeD(ID]sSz@O;S/E08r{^^?H./3S5mDjpym*f3kO@M9W;]BM1AD4*=(Pvb)DdNSA;mZ,KnSg)JW1!vtI>i.[&2Q"Ph[JJk+bUIgmvtYKr(jCO5dJ;4qDil+bi_n;EL!>*HY!x@U91}+(`fuf%ub+W[w]<@IP!!,z^n<F$*<s
^f~04gcFME,b-o#g`k[i)BpxoC"s{Jd`(lT%Z^Wav!8d-Uff,0>;[+REv
u2Q"E8`1>*?:iatis?H(&f*Q>L;9=.<B5b~+MsG69CHHERqHnW3ZSJe
s(xq[PFqSL($4B852x_pH0`NN_4VK_VS];.SV2mT1<Q6&FS5QB1;uKr`DHD#N//-Jxi;;E?3Te?kZg^%X-Z/
iapzGyl^Y
Q51/G5Xp[wD
Y-END#LJ1SgTUYrZRG>8&57EWetI(9[#%TGN`Gh(g#Ds-vog``m"<5;>,AGDt6?96Wm#
pA".G({*7UK#1f)?Sco5E>#o&0gFIhqLN+O60$"v$a;J];CAq#8J?F+)Cat%1g}e)6W@ym$j&"4E=X>GzSh!gX%NH_[r4#QX@`=.O+9%3du+bU3gK%u>2.2aYO|Y+GH?h)KYUFZ7B2ujZ=fO8G+V]IE4{g#xNghfO_i+r.8ubBo+u#;]XV:egD+<Yg:CrH^dYVZdq3*0cQL4]vPZ91t4]q*eJ*lpv%"^I5[
J]F(y+}W#V_Gh9yU%Ye"vO%9ug_rJ<{La@S;T9-o/T>3u*//xV:L.Qa=rAovNgH:aNv).-V<Gp:l-uk*5uPV>("T?6ibtmDgFlp+`1S_?_^G/M3ED3UDa.>L8iORU5>4lQ-XiwH-
Ac0u]iW2<QI~$<&^_N8uuG`*.ye:Ki
YVfJh`%g3W?sGVK-k/=eMIVIBr,p3]lh/Rb(I_XBuO`c0Al.nYh94TB3M;~7inWHGr_ML.~EweI8(f|R0b732Q|sjOj!|n-QFd-+@9XwrH#oSV,h*z(!jV}ETL>#0^]R!ID]Gsn-R!08T8xN%RwGzvTW^%#li*1J8(fN][%[Z8SKfOAXmfDme@hh%S4B46
p&F}w=4}U7gKjUbLO;^{Vi,dpim{SsNfs
bI5qwo:zN4_wwgeh!HM_<1q(gwG6:FHB^!/8Mp5z$Ww-yXOmsDK#Tb].YrmMgsA0[dkQl%b9vvwRn.HH7($h+NH!LN(oH
dQdU;ujE"@s9CXn-FI1caPL<>BKTK;
sJEbT("1w0-sI5R2WmbVNLH3aPncxsNwZiX3BxCx2^jjc4l:@ECmA-grR*hVy`
U>Rxhu@*t<Lp&Air-)M{8hxp_$y@G2LL6!`WdkOLZGN`Zbnj!uDy.qQ>?beZkBC9g|"m::2WGy/38Lq2Z.@/^_q%24R.B5<"Wo"Zmu:@O4_==YQiPmFH)S>H5PR"S([IV1OCdI118e*-Oae>Y`iR)zuz>hS9pN:r#&b</9@z3~@Xh;acIjStmvce0N<igW"-6W/<Z"3H/
%7MX1?>|>Z[m.Uw29*!$6-_&RRW2^i"Fj1gk6;C3((&Fr6jdwP>ERuZW"4:r,N3ohIg089J3gaJb/pq0lM%tFev-(gupY@,+ti5$lUjs*uk95U/Tr)G[g.UpM?PI#=uUV@V{vw"C*|aFQf5lXK#b)wE:-;q^BUdL)mXOcpB(m7C`A{dWU0Tx?eeN$=;kY<(?"PY]jwIht[:MU[a&
6[`*4$)[eUFlRZhr*]XDW"yI#%<]s-ENYTJ-2a-_}:nCWmCy,B"HcL*"_1(dppcFx8wk>gfYGXpaF?;^E6kC9R{qyQbR!A^3~sT*GPo
3W/`6w4^KWFAY1.%>kfI(x3e$3Ge39i+}uF2+?7tM%1:TrtVllUE~>kW2,1=[@xaVL0^YO/q.31+[O@)9=K014cd|R9F:ek#=jxJ*8e>p#Lde*fWzjM[RA[W)AM-tI|=iy+(=VJ"};lL6UGdwh^,ib+[Har`TY87p)}D2/|&1!7o_v"SU.]R2NtX?O.gU%_>QGkm+){NJ&@H7AAlq%~@3^SgQ+(r<f,F;"s<h5^HC]Sq;2Dww=/k2Qj2dZKTfHb#,?.2V(]jM%U:B:.Sp>F$zM3T).F=]%B"|#W?jEeg6vW#OXXdRF2.gU.@rfvdCkV.!3=6?e+B#Ou##g,9q;~BgZ}%/ec",iO:L#K+TiSP[Yn_AIV.$D#owFQZ2DK5PsIb`<Z>u+K$1FcokHfi-BE]Wy@WC>-`30=J>@|Zq$V2D2xbQ!z2$l`TO,ShXe..Y$P3Zps4(yk.Q`i#a@Ww*0:Bq>i$+YPQs,Dc
UWu/;tUTE:d[6or|_Xd-
ZJw(Xdl00.,7sst(_bL={j;%<Tz>&Dl?*Ol:Ks%glkB:=Mh?[-dq"OE%X[|]2HW^4Ist
QFJ/8>]f:^T`:m9
_IhYF%FPQQro;vo<`>)n)1_>h^RY%-LHwR?IP=Eo5qVFJT3}k{O0N+j$<Ds_Q3I1w
9iZxE[(iev4Vk)B.t`y(G
x_^64[CEwr&p>9?XHjbL*B+^WhKB4
4<(3L:[tZE:]ABhI;+1l"dL`kT-(D6d,[3h5&6@cDfR89wC;,Z</6z/95/lrT}ur1M,cPv4w>>4>xZ#z%i9?UcXzQVpa3<Xh8T7B/([NV:=Xk=J~T7CAo2j(T&UgqO)vrx3$r]"Tlkh~EU$x!&qgf-:>:"=#0QVdlLC*<k2<w")4q+7bfG)TUZWv<c${?#
F>!_jAOtaqYWJR5"gZ|,,rr3.Quvfwan{?#%[A!XKOW;kphHS3x,y;)(JCUw,aP#:j@eC(dy(B$w}c*9dwe/U@
Vq&A(kH=<O#9rEK}H%nI(?/2V5:mRp;)$IKm>9:C;[ar^4h0pk,.g=1x^.Vw`.pAJ,gMCAs^P91hr6O>U,Q<V;1#vawY@oEz.dqCC,kgt<TrB9f{U14
N&P4Ja/m3x(b&723Jh.].cX(D"8J5Z.I2
Np"Z]F_pMcJ&2T]0vRqAQeW{Q*JK.
MMY|k
5
wS(+JN[=K8h$yE08VM82bpa`$O.vt~8iC,C<h{1|&l.>^_xJ.AU4Y+kyT}S&-H9)vD`"*V+5=roQE"r>#I_^f.#@wE@`_~u@8n+vu]i"tw6Lb@Bi=p(>w&v$&Ziv#jl]"^$X-0$&<g.CN6TLy2$j$upR^|xcMn,sz&F4q,.O&CX`-Gs.I-`i!Ujuml7uex9P(!XK6B9YZ:LiEL%t/MMXp(V4MR6uWB?(blW[K?-WaDR$-~J<1)I!T5hxL~XrT8rG,Wvl#t;Kky8($SE?!Tem]^*JjBx|_[%73$^C*a"|&dg11gCfjDkrUHfRCNY.T<&hO"taP&>jqg)ZFI+v({Qo19ar?~0H7PvP,rbB)v,Yq(E~YA"VyVRPvl@=glOs
dh@/N4*?e<0=d=Sue/$&aZLj^mjv7RvbCph)Bv7S!4&[j!=E
<qA}j0i-(C27J6#v2~_"8Q8/4":s<fl"([u2%H(OR-!dyT@X?hon1AAu6=^<^u^y5D@SDY05YxU&5^
-=&a+Y@Zi4zI%sq-TtZ;&`Gi(rxfa;SD(U}(JSde>;fH
62u3?aap(!/X)5p~%1kwPi&vdT/5QGr?LzgeP_Lk
J(dd_dLvTc6;*gedzwPDLq[U4)hg$(krUM*T"E"
jm9"px!q}teeMyHo]PT`ET{0r,NeCy._9/NvO?6-J^T=j7:=WmAnYr4Krg|U*B.@d]tB=0>;[2gbe5O&o>PT@sg1r2P9C3i/>J{+*#$:MkYE(lp$E"iryNXsJCGD]g0eBa6Bb?p_]*f,#1^Aq$mOO(42"EU5n4%x+E?9,[&JsI$y7?Q(Ek@NaJd&/j,"p[hPw5*bw")3[>E_o8.WZc;7.0-"m`PL"`4!Xtrb)YGt8v<06f`L{:c-u0SQZ;[^Q-2_gR?"dKUg;k^7%Il(mU7sCV>AxZZ(L<r9PFPL-*e%WKdu
R
P![)427^-d
P$_KI@OVPR~,".*]zg3[7]gAfWU&{F#+Ypt:.aX`Qwf2BR@r1C7H}%F;xt(`O$?/TY>#xanY+`nme7qlA2:5^`Ll0c*-0?{%d]l]`_`5BcmxTGTD"3h%NuOZN:58v^FP/XGVrj;#idCqDY!a7MC!eJH0q/n1"QW-:P<fMQ{2Do#+3=7t@Ou
?y96L`T"Li}jt[a>!C*_<4x:a,lqnt,NY&7;a^HJ"2MWb$Al#fPf"E^uhd(AJXksQ4jy[_=Q1_z7ZpMlxphdH9wLvl($2T)dL%6,-ha.D(*<$V^oo68/S#+d+<?``(>3XGo":z%yBTy/V6J>+nt9[:7qR5CvDxx@XwEUK.8q)WeN+DYeB9-,T5-%+pjuwN?`q%5Wj]5_g1djNdM4IF!*Bm^%*9?Y1KYE*7Q-@;wjlO]R}#G(|1B:1[0Q2g,,]gmU?MT>}2M7]CLs~YkiZogPP2p99`JJ
KjRr12yLY
7DYKg#)uB<x<5$]vHA@x
,T1^@S_R+]^tw_boby9;z&oTI=!F^]N>SW{q{6
j<vfsZ/~whKg_d*t#DL5U*4m<Jc.L{@)PdS~_Us?QF$4K.q0$e
q)%!
JJ"Ic)vq#%(qEk8j2j6S,GK4.k)PH_!nIWCuJ,VZD0?3RlwkYfNUn$$_dvf(G%80)QmejPj?F8t-jbI{WR,<?u"vj`u<LBD.P|!M[oCW^`^l0PHNZ|]@d?T-YV(2(]1R61w{mu)GI|&|CNZ1?0G8.i*6nLU/Gj=h=rX]"hAS44in(o4=<KXn#6V3c]3e5kYhc*1Qpqx;/i0au&5
gZ4mJ`8n06mE`kD8WcLl1w5Ho|
pLFHALjh%1F;33Gwt#l9F(?Q;x@n./O9)-rom6`QlVlZ..DZCplOp-r)$6Ci9(4tULIf&wqGa)P$xCSdL>P5x/%E/u|;f<$B|CvsSZ|vis@COn`?%2zGqmCd+tU<Y2.?[ggKxT7/a;U2[U%[z3iU:A)]$3I>~_YF1>frbY10r]IU_Ko?Mi$vSlBN<mls|gt!aUcVbF()Me=!qgmDUl7P)
3]VYU(F@dr~:4iGNgE^.KeIL0nqL6n~,U;DJX=kMNQ}Lu>,D
!PJ]4vQsHPi?nZ@Q?8;98:4vsqHrtCs!Z`Drh!&cvhw@svB{%q;UBj
TqTiIp|Zk^Pk]Olm4[bk**^?VyRC6l+."j82Qiu={vujJRViRI#K,.vB
LxFUvx0*"fv<Cfs$]?*<g;dM3Ye(Y!(WBo*|,[cv5A?~QnrC
V<S1%<icS8}Sbl"l<<VUk7l&:a1Yj*3D:gZ$obuN)`IATF:N73_BcHh#wp+(lpg?4pEUx!Pw:Jr;+_PZ-DxcFLRm.hl@_<"nmC):?&e)IAWki?*@X_Nd6t0v%!/u=%`$0Ovyho)');}elseif($_GET["file"]=="worker.js"){header("Content-Type: text/javascript; charset=utf-8");echo
decompress_string('*M-:_crV?&ivhwW00>hyk#FBR?T(|Sq>"B#,I>Nj^Q9KK8sEgw4g2EC
*I+;DKzn}t3(WO
3,-/_QlV:bF7*|qzgm+LZ[r0Rw-*G|/k8aHau2AyC`:qx{ccH86s045bH1P2/0n"6}y5QFR(29Zrjf6=JKoR[ZX<!#*8i<5q.ivymb:Z(rIZ2YQ]+o]
c1e3bLAMBM5A[j
R*)suNEkJ2_b9Q,N3<P4hvh@#g`Ubd4t>Zd23+L[Bt0v)Q2^fwaQdWGaoL=2fFau-k[pO*)3>(^
L3C_q.O7n@ic/h_PH^?3P(D2iqS^rMAuO_swO`)*TiGN,)~(n+#u:.`d2HKi,UCsH@.]A%*j08LgKJ|@kX+bbB8Zu(St;xKfJ=}=9(nou8]":5u&EO~jfR@9f.[9)!m!m>c&!6F6vOL1`]MawSXj)67Xp@ygy7)9*q,X#[h/L6mfb@W@"#ngdi7B#e$3RlPyr[q*H-nfIGG."G="QLE1bbI!fYOm7erh[>m4s7/_nwA');}elseif($_GET["file"]=="logo.png"){header("Content-Type: image/png");echo
base64_decode('iVBORw0KGgoAAAANSUhEUgAAADkAAAA5BAMAAAB+Np62AAAAMFBMVEUAAACDl60rTnZZdJNziaOerr60vszI0tr8jZH8c3X8SUr309T8Ly78Bgf8r7H6/PpDBKXXAAAAAXRSTlMAQObYZgAAAAlwSFlzAAALEwAACxMBAJqcGAAAAbRJREFUOI3VlM1OwkAQx/sGG0Xh7GwTz7b1AaRwNhqIRy4kPRKjpcc+geEJDHc1chYPfYJ6N7I+gJFQE+UjJIyzS6FqqzeN/A/dtr/Mzsx/PzRtlYSI0fd0Ju5+wDMhHjCTMIqaXoS9QWYw3iLlvRHtLMrwKqDnNLyM4m+lReizCOjXWCgqWdPzvLgJNgnvUGNPV6IVyc7cim2SrHKDMMN+L6DhTKgBDVhqCyPWFW3KwfpqwEOAXUembeYAtn0W3ssErN+RdbxBOcBYowrU2Di8VrEdWcQrx0QjqGlx3m5LUThK4DFRNhGy5lkwp2CVHZ9Qs2ICUY1cGmiUfj7zOnBTyYAdo6a8otjzR0X1UT3uSc97kiqfFzPrMqM39woVZcoUTOhCin7QL1IoJLAOKcrniyCXwUhRboBplTYPSrYJPJ3XLS6Wd8fJqmrqVm2r6vxtvz9T3kigm3bDzPvxxqmn3QDg1l7VcasbtgEpqg+X2133ixlVuTky0Sw7/8eNF+4ncPi1oyFYy4Pk2tz/TPFELrt0w6aX/S93FMPT5OwXUvcbnQl3rWTT1nIy78akqjRbPb0DRTX3Uyvxl2MAAAAASUVORK5CYII=');}exit;}if(preg_match('~^/[-\w.]~',$_SERVER["HTTP_X_FORWARDED_PREFIX"]))$_SERVER["REQUEST_URI"]=$_SERVER["HTTP_X_FORWARDED_PREFIX"].$_SERVER["REQUEST_URI"];define('Adminer\HTTPS',($_SERVER["HTTPS"]&&strcasecmp($_SERVER["HTTPS"],"off"))||ini_bool("session.cookie_secure"));ini_set("session.use_trans_sid",'0');ini_set("arg_separator.output","&");define('Adminer\SESSION_NAME',session_name());if(isset($_GET["upload"])){$ei=null;if(!defined("SID")&&$_COOKIE[SESSION_NAME]!=""){session_start();$ei=$_SESSION[ini_get("session.upload_progress.prefix").$_GET["upload"]];}header("Content-Type: application/json; charset=utf-8");echo
json_encode(isset($ei["bytes_processed"])?array($ei["bytes_processed"],$ei["content_length"]):array());exit;}if(function_exists('session_status')?session_status()==PHP_SESSION_NONE:!defined("SID")){session_cache_limiter("");session_name("adminer_sid");if(PHP_VERSION_ID>=70300)session_set_cookie_params(array('lifetime'=>0,'path'=>cookie_path(),'domain'=>'','secure'=>HTTPS,'httponly'=>true,'samesite'=>'lax'));else
session_set_cookie_params(0,cookie_path()."; SameSite=lax","",HTTPS,true);session_start();}if(function_exists("get_magic_quotes_gpc")&&get_magic_quotes_gpc()){$_GET=remove_slashes($_GET,$rd);$_POST=remove_slashes($_POST,$rd);$_COOKIE=remove_slashes($_COOKIE,$rd);}if(function_exists("get_magic_quotes_runtime")&&get_magic_quotes_runtime())set_magic_quotes_runtime(false);if(function_exists('set_time_limit'))set_time_limit(0);ini_set("precision",'16');function
lang($u,$Cg=null){$va=func_get_args();$va[0]=$u;return
call_user_func_array('Adminer\lang_format',$va);}function
lang_format($sk,$Cg=null){if(is_array($sk)){$G=($Cg==1?0:1);$sk=$sk[$G];}$sk=str_replace("'",'’',$sk);$va=func_get_args();array_shift($va);$Ad=str_replace("%d","%s",$sk);if($Ad!=$sk)$va[0]=format_number($Cg);return
vsprintf($Ad,$va);}define('Adminer\LANG','en');abstract
class
SqlDb{static$instance;static$untrusted=false;var$extension;var$flavor='';var$server_info;var$affected_rows=0;var$info='';var$errno=0;var$error='';protected$multi;abstract
function
attach(array$N,$V,$F);abstract
function
quote($Q);abstract
function
select_db($Rb);abstract
function
query($H,$Dk=false);function
multi_query($H){return$this->multi=$this->query($H);}function
store_result(){return$this->multi;}function
next_result(){return
false;}function
inTransaction(){return
false;}}if(extension_loaded('pdo')){abstract
class
PdoDb
extends
SqlDb{protected$pdo;function
dsn($zc,$V,$F,array$C=array()){$C[\PDO::ATTR_ERRMODE]=\PDO::ERRMODE_SILENT;$C[\PDO::ATTR_STATEMENT_CLASS]=array('Adminer\PdoResult');try{$this->pdo=new
\PDO($zc,$V,$F,$C);}catch(\Exception$Tc){return$Tc->getMessage();}$this->server_info=@$this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);return'';}function
quote($Q){return$this->pdo->quote($Q);}function
query($H,$Dk=false){$I=$this->pdo->query($H);$this->error="";if(!$I){list(,$this->errno,$this->error)=$this->pdo->errorInfo();if(!$this->error)$this->error='Unknown error.';return
false;}$this->store_result($I);return$I;}function
store_result($I=null){if(!$I){$I=$this->multi;if(!$I)return
false;}if($I->columnCount()){$I->num_rows=$I->rowCount();return$I;}$this->affected_rows=$I->rowCount();return
true;}function
next_result(){$I=$this->multi;if(!is_object($I))return
false;$I->_offset=0;return@$I->nextRowset();}function
inTransaction(){return$this->pdo->inTransaction();}}class
PdoResult
extends
\PDOStatement{var$_offset=0,$num_rows;function
fetch_assoc(){return$this->fetch_array(\PDO::FETCH_ASSOC);}function
fetch_row(){return$this->fetch_array(\PDO::FETCH_NUM);}private
function
fetch_array($jg){$J=$this->fetch($jg);return($J?array_map(array($this,'normalize'),$J):$J);}private
function
normalize($X){if(is_bool($X))return(JUSH=='pgsql'?($X?"t":"f"):+$X);return(is_resource($X)?stream_get_contents($X):$X);}function
fetch_field(){$K=(object)$this->getColumnMeta($this->_offset++);$U=$K->pdo_type;$K->type=($U==\PDO::PARAM_INT?0:15);$K->charsetnr=($U==\PDO::PARAM_LOB||(isset($K->flags)&&in_array("blob",(array)$K->flags))?63:0);return$K;}function
seek($Ig){for($s=0;$s<$Ig;$s++)$this->fetch();}}}function
add_driver($t,$B){SqlDriver::$drivers[$t]=$B;}function
get_driver($t){return
SqlDriver::$drivers[$t];}abstract
class
SqlDriver{static$instance;static$drivers=array();static$extensions=array();static$jush;static$passwords=true;static$serverSchemes=array();static$serverSocket=false;static$serverPath=false;static$serverFile=false;protected$conn;protected$types=array();var$delimiter=";";var$insertFunctions=array();var$editFunctions=array();var$unsigned=array();var$fulltextOperator="AGAINST";var$functions=array();var$grouping=array();var$onActions="RESTRICT|NO ACTION|CASCADE|SET NULL|SET DEFAULT";var$partitionBy=array();var$inout="IN|OUT|INOUT";var$enumLength="'(?:''|[^'\\\\]|\\\\.)*'";var$generated=array();var$primary="";var$query="";static
function
jushModule(){return"";}static
function
jushAutocomplete(array$T,$xj){$Rj=array();foreach($T
as$R=>$P){if(!$P["dependent"])$Rj[$R]=array();}foreach(driver()->allFields()as$R=>$m){foreach($m
as$l)$Rj[$R][]=$l["field"];}return"jush.autocompleteSql('".idf_escape("")."', ".json_encode($Rj).", ".json_encode($xj).")";}static
function
connect($N,$V,$F){if(static::$serverFile)$zh=server_parts(array("path"=>$N));else{$zh=parse_server($N);if(!$zh||($zh["scheme"]&&!in_array($zh["scheme"],static::$serverSchemes))||($zh["socket"]&&!static::$serverSocket)||($zh["path"]&&!static::$serverPath)||(substr($zh["host"],0,1)=="/"&&!static::$serverSocket))return'Invalid server.';if($zh["port"]!=""&&($zh["port"]<1024||$zh["port"]>65535))return'Connecting to privileged ports is not allowed.';}$e=new
Db;return($e->attach($zh,$V,$F)?:$e);}function
__construct(Db$e){$this->conn=$e;}function
types(){return
call_user_func_array('array_merge',array_values($this->types));}function
structuredTypes(){return
array_map('array_keys',$this->types);}function
enumLength(array$l){}function
unconvertFunction(array$l){}function
select($R,array$M,array$Z,array$r,array$D=array(),$y=1,$E=0,$Yh=false){$Te=(count($r)<count($M));$H=adminer()->selectQueryBuild($M,$Z,$r,$D,$y,$E);if(!$H)$H="SELECT".limit(($_GET["page"]!="last"&&$y&&$r&&$Te&&JUSH=="sql"?"SQL_CALC_FOUND_ROWS ":"").implode(", ",$M)."\nFROM ".table($R),($Z?"\nWHERE ".implode(" AND ",$Z):"").($r&&$Te?"\nGROUP BY ".implode(", ",$r):"").($D?"\nORDER BY ".implode(", ",$D):""),$y,($E?$y*$E:0),"\n");$this->query=$H;$wj=microtime(true);$J=$this->conn->query($H,(!$y&&!$Yh?1:0));if($Yh)echo
adminer()->selectQuery($H,$wj,!$J);return$J;}function
delete($R,$hi,$y=0){$H="FROM ".table($R);return
queries("DELETE".($y?limit1($R,$H,$hi):" $H$hi"));}function
update($R,array$O,$hi,$y=0,$Ui="\n"){$bl=array();foreach($O
as$x=>$X)$bl[]="$x = $X";$H=table($R)." SET$Ui".implode(",$Ui",$bl);return
queries("UPDATE".($y?limit1($R,$H,$hi,$Ui):" $H$hi"));}function
insert($R,array$O){return
queries("INSERT INTO ".table($R).($O?" (".implode(", ",array_keys($O)).")\nVALUES (".implode(", ",$O).")":" DEFAULT VALUES").$this->insertReturning($R));}function
insertReturning($R){return"";}function
insertUpdate($R,array$L,array$Xh){foreach($L
as$O){$Z=array();foreach($O
as$x=>$X){if(isset($Xh[idf_unescape($x)]))$Z[]="$x = $X";}if(!($Z&&$this->update($R,$O," WHERE ".implode(" AND ",$Z))&&$this->conn->affected_rows)&&!$this->insert($R,$O))return
false;}return
true;}function
begin(){return
queries("BEGIN");}function
commit(){return
queries("COMMIT");}function
rollback(){return
queries("ROLLBACK");}function
slowQuery($H,$fk){}function
operators($Ij){return
array();}function
convertSearch($u,array$X,array$l){return$u;}function
value($X,array$l){return(method_exists($this->conn,'value')?$this->conn->value($X,$l):$X);}function
quoteBinary($Hi){return
q($Hi);}function
typeName(\stdClass$l){return(isset($l->native_type)?$l->native_type:"");}function
warnings(){}function
tableHelp($B,$Xe=false){}function
inheritsFrom($R){return
array();}function
inheritedTables($R){return
array();}function
partitionsInfo($R){return
array();}function
hasCStyleEscapes(){return
false;}function
lineComment(){return"--";}function
engines(){return
array();}function
supportsIndex(array$S){return!is_view($S);}function
supportsAlterIndex(array$S){return
true;}function
supportsAlterTable(array$Ij){return
true;}function
indexAlgorithms(array$Ij){return
array();}function
indexOpclasses(){return
array();}function
shadowTables($R){return
array();}function
fulltextSql($B,array$v,$H,$Qa){return"MATCH (".implode(", ",array_map('Adminer\idf_escape',$v["columns"])).") AGAINST (".q($H).($Qa?" IN BOOLEAN MODE":"").")";}function
checkConstraints($R){return
get_key_vals("SELECT c.CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS c
JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t
	ON c.CONSTRAINT_SCHEMA = t.CONSTRAINT_SCHEMA AND c.CONSTRAINT_NAME = t.CONSTRAINT_NAME".($this->conn->flavor=='maria'?" AND c.TABLE_NAME = ".q($R):"")."
WHERE c.CONSTRAINT_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
AND t.TABLE_NAME = ".q($R).(JUSH=="pgsql"?"
AND CHECK_CLAUSE NOT LIKE '% IS NOT NULL'":""),$this->conn);}function
allFields(){$J=array();if(DB!=""){foreach(get_rows("SELECT c.TABLE_NAME AS tab, c.COLUMN_NAME AS field, c.IS_NULLABLE AS nullable,
	c.DATA_TYPE AS type, c.CHARACTER_MAXIMUM_LENGTH AS length,
	".(JUSH=='sql'?"c.COLUMN_KEY = 'PRI'":"k.COLUMN_NAME")." AS ".idf_escape("primary")."
FROM INFORMATION_SCHEMA.COLUMNS c".(JUSH=='sql'?"":"
LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS t ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND t.CONSTRAINT_TYPE = 'PRIMARY KEY'
LEFT JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
	ON t.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME")."
WHERE c.TABLE_SCHEMA = ".q($_GET["ns"]!=""?$_GET["ns"]:DB)."
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",$this->conn)as$K){$K["null"]=($K["nullable"]=="YES");$J[$K["tab"]][]=$K;}}return$J;}}class
Adminer{static$instance;var$error='';function
name(){return"<a href='https://www.adminer.org/'".target_blank()." id='h1'><img src='".h(preg_replace("~\\?.*~","",ME)."?file=logo.png&version=6.0.2")."' width='24' height='24' alt='' id='logo'>Adminer</a>";}function
credentials(){return
array(SERVER,$_GET["username"],get_password());}function
connectSsl(){}function
permanentLogin($g=false){return
password_file($g);}function
bruteForceKey(){return$_SERVER["REMOTE_ADDR"];}function
serverName($N){return
h($N);}function
database(){return
DB;}function
databases($wd=true){return
get_databases($wd);}function
pluginsLinks(){}function
operators($Ij=null){return
driver()->operators($Ij);}function
schemas(){$J=schemas();if($_GET["ns"]!=""&&!in_array($_GET["ns"],$J))array_unshift($J,$_GET["ns"]);return$J;}function
queryTimeout(){return
2;}function
afterConnect(){}function
headers(){}function
csp(array$Ib){return$Ib;}function
verifyVersion(){return
true;}function
serviceWorker(){service_worker();}function
head($Nb=null){return
true;}function
bodyClass(){echo" adminer";}function
css(){$J=array();foreach(array("","-dark")as$jg){$n="adminer$jg.css";if(file_exists($n)){$nd=file_get_contents($n);$J["$n?v=".crc32($nd)]=($jg?"dark":(preg_match('~prefers-color-scheme:\s*dark~',$nd)?'':'light'));}}return$J;}function
loginForm(){echo"<table class='layout'>\n",adminer()->loginFormField('driver','<tr><th>'.'System'.'<td>',input_hidden("auth[driver]","server")."MySQL / MariaDB"),adminer()->loginFormField('server','<tr><th>'.'Server'.'<td>',"<input name='auth[server]' value='".h(SERVER)."' title='".'hostname[:port] or :socket'."' placeholder='localhost' autocapitalize='off'>"),adminer()->loginFormField('username','<tr><th>'.'Username'.'<td>','<input name="auth[username]" id="username" autofocus value="'.h($_GET["username"]).'" autocomplete="username" autocapitalize="off">'),adminer()->loginFormField('password','<tr><th>'.'Password'.'<td>','<input type="password" name="auth[password]" autocomplete="current-password">'),adminer()->loginFormField('db','<tr><th>'.'Database'.'<td>','<input name="auth[db]" value="'.h($_GET["db"]).'" autocapitalize="off">'),"</table>\n","<p><input type='submit' value='".'Login'."'>\n",checkbox("auth[permanent]",1,$_COOKIE["adminer_permanent"],'Permanent login')."\n";}function
loginFormField($B,$fe,$Y){return$fe.$Y."\n";}function
login($Bf,$F){if($F=="")return'Adminer does not support accessing a database without a password.'.require_password_link(null);if(!Driver::$passwords)return'The database does not support passwords.'.require_password_link($F);if(!password_required())return'The server accepts any password, so filling it in protects nothing.'.require_password_link($F);return
true;}function
tableName(array$Ij){return
h($Ij["Name"]);}function
fieldName(array$l,$D=0){$U=$l["full_type"].($l["null"]?" NULL":"");$qb=$l["comment"];return'<span title="'.h($U.($qb!=""?($U?": ":"").$qb:'')).'">'.h($l["field"]).'</span>';}function
commentValue($U,$qb){if($qb==""||$U=='TABLE'||$U=='COLUMN')return
h($qb);$Sh=function($Hi){return
preg_replace('~^~m','<tr>',preg_replace('~\|~','<td>',preg_replace('~\|$~m',"",rtrim($Hi))));};$R='(\+--[-+]+\+\n)';$K='(\| .* \|\n)';return"<pre>\n".preg_replace_callback("~^$R?$K$R?($K*)$R?~m",function($A)use($Sh){$ud=$Sh($A[2]);return"<table>\n".($A[1]?"<thead>$ud<tbody>\n":$ud).$Sh($A[4])."\n</table>";},preg_replace('~(\n(    -|mysql)&gt; )(.+)~',"\\1<code class='jush-sql'>\\3</code>",preg_replace('~(.+)\n---+\n~',"<b>\\1</b>\n",h($qb))))."</pre>\n";}function
commentInput($U,$b,$qb){$Y=h($qb);return(preg_match('~\n~',$Y)?"<textarea$b rows='2' cols='".($U=='TABLE'?20:30)."' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");}function
selectLinks(array$Ij,$O=""){$B=$Ij["Name"];echo'<p class="links">';$yf=array();if($B!="")$yf["select"]='Select data';if(support("table")||support("indexes"))$yf["table"]='Show structure';$Xe=false;if(support("table")){$Xe=is_view($Ij);if($Xe){if(support("view"))$yf["view"]='Alter view';}elseif(function_exists('Adminer\alter_table')&&$B!="")$yf["create"]='Alter table';}if($O!==null)$yf["edit"]='New item';foreach($yf
as$x=>$X)echo" <a href='".h(ME)."$x=".url_escape($B).($x=="edit"?$O:"")."'".bold(isset($_GET[$x])).">$X</a>";echo
doc_link(array(JUSH=>driver()->tableHelp($B,$Xe)),"?"),"\n";}function
foreignKeys($R){return
foreign_keys($R);}function
backwardKeys($R,$Hj){return
array();}function
backwardKeysPrint(array$Ga,array$K){}function
selectQuery($H,$wj,$gd=false){$J="\n";if(!$gd&&($jl=driver()->warnings())){$t="warnings";$J=", <a href='#$t' class='toggle'>".'Warnings'."</a>"."$J<div id='$t' class='hidden'>\n$jl</div>\n";}return"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>(".format_time($wj).")</span>".(support("sql")?" <a href='".h(ME)."sql=".url_escape($H)."' class='hover'>".'Edit'."</a>":"").$J;}function
sqlCommandQuery($H){return
shorten_utf8(trim($H),1000);}function
sqlPrintAfter(){}function
rowDescription($R){return"";}function
rowDescriptions(array$L,array$zd){return$L;}function
selectLink($X,array$l){}function
selectVal($X,$z,array$l,$ih){$J=($X===null?"<i>NULL</i>":(preg_match("~char|binary|boolean~",$l["type"])&&!preg_match("~var~",$l["type"])?"<code>$X</code>":(preg_match('~^jsonb?$~',$l["full_type"])?"<code class='jush-json'>$X</code>":$X)));if(is_blob($l)&&!is_utf8($X))$J="<i>".lang_format(array('%d byte','%d bytes'),strlen($ih))."</i>";return($z?"<a href='".h($z)."'".(is_url($z)?target_blank():"").">$J</a>":$J);}function
editVal($X,array$l){return$X;}function
config(){return
array();}function
tableStructurePrint(array$m,$Ij=null){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr><th>".'Column'."<td>".'Type'.(support("comment")?"<td>".'Comment':"")."<tbody>\n";$_j=driver()->structuredTypes();foreach($m
as$l){echo"<tr><th>".h($l["field"]);$U=h($l["full_type"]);$lb=h($l["collation"]);echo"<td><span title='$lb'>".(in_array($U,(array)$_j['User types'])?"<a href='".h(ME.'type='.url_escape($U))."'>$U</a>":$U.($lb&&isset($Ij["Collation"])&&$lb!=$Ij["Collation"]?" $lb":""))."</span>",($l["null"]?" <i>NULL</i>":""),($l["auto_increment"]?" <i>".'Auto Increment'."</i>":""),(isset($l["default"])?" <span title='".'Default value'."'>[<b>".($l["generated"]?"<code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($l["default"])),80,"</code>"):h($l["default"]))."</b>]</span>":""),(support("comment")?"<td>".adminer()->commentValue('COLUMN',$l["comment"]):""),"\n";}echo"</table>\n","</div>\n";}function
tableIndexesPrint(array$w,array$Ij){$rh=false;foreach($w
as$B=>$v)$rh|=!!$v["partial"];echo"<table>\n";$Wb=first(driver()->indexAlgorithms($Ij));foreach($w
as$B=>$v){ksort($v["columns"]);$Yh=array();foreach($v["columns"]as$x=>$X)$Yh[]="<i>".h($X)."</i>".($v["lengths"][$x]?"(".h($v["lengths"][$x]).")":"").($v["descs"][$x]?" DESC":"");echo"<tr title='".h($B)."'>","<th>".h($v["type"]).($Wb&&$v['algorithm']!=$Wb?" (".h($v['algorithm']).")":""),"<td>".implode(", ",$Yh);if($rh)echo"<td>".($v['partial']?"<code class='jush-".JUSH."'>WHERE ".h($v['partial']):"");echo"\n";}echo"</table>\n";}function
selectColumnsPrint(array$M,array$d){print_fieldset("select",'Select',$M);$s=0;$M[""]=array();foreach($M
as$x=>$X){$X=idx($_GET["columns"],$x,array());$c=select_input(" name='columns[$s][col]' data-default=''".on('change',($x!==""?'selectFieldChange':'selectAddRow')),$d,$X["col"]);echo"<div>".(driver()->functions||driver()->grouping?html_select("columns[$s][fun]",array(-1=>"")+array_filter(array('Functions'=>driver()->functions,'Aggregation'=>driver()->grouping)),$X["fun"]," data-default=''".on('change',($x!==""?'helpClose':'selectFunAddRow')).on_help_value(' (.*)|$','($1)'))."($c)":$c)."</div>\n";$s++;}echo"</div></fieldset>\n";}function
selectSearchPrint(array$Z,array$d,array$w,$Ij=null){print_fieldset("search",'Search',$Z);foreach($w
as$s=>$v){if($v["type"]=="FULLTEXT")echo"<div>(<i>".implode("</i>, <i>",array_map('Adminer\h',$v["columns"]))."</i>) ".h(driver()->fulltextOperator)," <input type='search' name='fulltext[$s]' value='".h(idx($_GET["fulltext"],$s))."' data-default=''".on('input','selectFieldChange').">",(JUSH=='sql'?checkbox("boolean[$s]",1,isset($_GET["boolean"][$s]),"BOOL"):''),"</div>\n";}$Ug=adminer()->operators($Ij);foreach(array_merge((array)$_GET["where"],array(array()))as$s=>$X){if(!$X||("$X[col]$X[val]"!=""&&in_array($X["op"],$Ug)))echo"<div>".select_input(" name='where[$s][col]' data-default=''".on('change',($X?'selectFieldChange':'selectAddRow')),$d,$X["col"],"(".'anywhere'.")"),html_select("where[$s][op]",$Ug,$X["op"]," data-default='".h(first($Ug))."'".on('change','selectFirstChange')),"<input type='search' name='where[$s][val]' value='".h($X["val"])."' data-default=''".on('input','selectFirstChange').on('keydown','selectSearchKeydown').on('search','selectSearchSearch').">","</div>\n";}echo"</div></fieldset>\n";}function
selectOrderPrint(array$D,array$d,array$w){print_fieldset("sort",'Sort',$D);$s=0;foreach((array)$_GET["order"]as$x=>$X){if($X!=""){echo"<div>".select_input(" name='order[$s]' data-default=''".on('change','selectFieldChange'),$d,$X),checkbox("desc[$s]",1,isset($_GET["desc"][$x]),'descending')."</div>\n";$s++;}}echo"<div>".select_input(" name='order[$s]' data-default=''".on('change','selectAddRow'),$d),checkbox("desc[$s]",1,false,'descending')."</div>\n","</div></fieldset>\n";}function
selectLimitPrint($y){echo"<fieldset><legend>".'Limit'."</legend><div>","<input type='number' name='limit' class='size' value='".h($y?:"")."' data-default='50'".on('input','selectFieldChange').">","</div></fieldset>\n";}function
selectLengthPrint($ck){echo"<fieldset><legend>".'Text length'."</legend><div>","<input type='number' name='text_length' class='size' value='".h($ck)."' data-default='100'>","</div></fieldset>\n";}function
selectActionPrint(array$w){echo"<fieldset><legend>".'Action'."</legend><div>","<input type='submit' value='".'Select'."'>"," <span id='noindex' title='".'Full table scan'."'></span>","<script".nonce().">\n","const indexColumns = ";$d=array();foreach($w
as$v){$Mb=reset($v["columns"]);if($v["type"]!="FULLTEXT"&&$Mb)$d[$Mb]=1;}$d[""]=1;foreach($d
as$x=>$X)json_row($x);echo";\n","selectFieldChange.call(qs('#form')['select']);\n","</script>\n","</div></fieldset>\n";}function
selectCommandPrint(){return!information_schema(DB);}function
selectImportPrint(){return!information_schema(DB);}function
selectEmailPrint(array$Ec,array$d){}function
selectColumnsProcess(array$d,array$w){$M=array();$r=array();foreach((array)$_GET["columns"]as$x=>$X){if($X["fun"]=="count"||($X["col"]!=""&&(!$X["fun"]||in_array($X["fun"],driver()->functions)||in_array($X["fun"],driver()->grouping)))){$M[$x]=apply_sql_function($X["fun"],($X["col"]!=""?idf_escape($X["col"]):"*"));if(!in_array($X["fun"],driver()->grouping))$r[]=$M[$x];}}return
array($M,$r);}function
selectSearchProcess(array$m,array$w,$Ij=null){$J=array();foreach($w
as$s=>$v){if($v["type"]=="FULLTEXT"&&idx($_GET["fulltext"],$s)!="")$J[]=driver()->fulltextSql($s,$v,$_GET["fulltext"][$s],isset($_GET["boolean"][$s]));}$Ug=adminer()->operators($Ij);foreach((array)$_GET["where"]as$x=>$X){$X+=array("col"=>"","op"=>first($Ug),"val"=>"");$_GET["where"][$x]=$X;$jb=$X["col"];if("$jb$X[val]"!=""&&in_array($X["op"],$Ug)){if($X["op"]=="SQL"&&(!$_POST||!verify_token()))SqlDb::$untrusted=true;$vb=array();foreach(($jb!=""?array($jb=>$m[$jb]):$m)as$B=>$l){$Th="";$ub=" $X[op]";if(preg_match('~IN$~',$X["op"]))$ub
.=" ".($X["val"]!=""?process_in($X["val"]):"(NULL)");elseif($X["op"]=="SQL")$ub=" $X[val]";elseif(preg_match('~^(I?LIKE) %%$~',$X["op"],$A))$ub=" $A[1] ".q("%$X[val]%");elseif($X["op"]=="FIND_IN_SET"){$Th="$X[op](".q($X["val"]).", ";$ub=")";}elseif(!preg_match('~NULL$~',$X["op"]))$ub
.=" ".q($X["val"]);if($jb!=""||is_searchable($l,$X))$vb[]=$Th.driver()->convertSearch(idf_escape($B),$X,$l).$ub;}$J[]=(count($vb)==1?$vb[0]:($vb?"(".implode(" OR ",$vb).")":"1 = 0"));}}return$J;}function
selectOrderProcess(array$m,array$w){$J=array();foreach((array)$_GET["order"]as$x=>$X){if($X!="")$J[]=(preg_match('~^((COUNT\(DISTINCT |[A-Z0-9_]+\()(`(?:[^`]|``)+`|"(?:[^"]|"")+")\)|COUNT\(\*\))$~',$X)?$X:idf_escape($X)).(isset($_GET["desc"][$x])?" DESC".(JUSH=='pgsql'&&idx($m[$X],"null")?" NULLS LAST":""):"");}return$J;}function
selectLimitProcess(){return(isset($_GET["limit"])?intval($_GET["limit"]):50);}function
selectLengthProcess(){return(isset($_GET["text_length"])?"$_GET[text_length]":"100");}function
selectEmailProcess(array$Z,array$zd){return
false;}function
selectQueryBuild(array$M,array$Z,array$r,array$D,$y,$E){return"";}function
messageQuery($H,$ek,$gd=false){restart_session();$je=&get_session("queries");if(!idx($je,$_GET["db"]))$je[$_GET["db"]]=array();if(strlen($H)>1e6)$H=preg_replace('~[\x80-\xFF]+$~','',substr($H,0,1e6))."\n…";$je[$_GET["db"]][]=array($H,time(),$ek);$sj="sql-".count($je[$_GET["db"]]);$J="<a href='#$sj' class='toggle'>".'SQL command'."</a> ".copy_icon()."\n";if(!$gd&&($jl=driver()->warnings())){$t="warnings-".count($je[$_GET["db"]]);$J="<a href='#$t' class='toggle'>".'Warnings'."</a>, $J<div id='$t' class='hidden'>\n$jl</div>\n";}return" <span class='time'>".@date("H:i:s")."</span>"." $J<div id='$sj' class='hidden'><pre><code class='jush-".JUSH."'>".shorten_utf8($H,1e4)."</code></pre>".($ek?" <span class='time'>($ek)</span>":'').(support("sql")?'<p><a href="'.h(str_replace("db=".url_escape(DB),"db=".url_escape($_GET["db"]),ME).'sql=&history='.(count($je[$_GET["db"]])-1)).'">'.'Edit'.'</a>':'').'</div>';}function
error(){return
error();}function
editRowPrint($R,array$m,$K,$Lk,$H='',$ek=''){echo($H!=""?"<p><code class='jush-".JUSH."'>".h(str_replace("\n"," ",$H))."</code> <span class='time'>($ek)</span>\n":"");}function
editFunctions(array$l){$J=($l["null"]?"NULL/":"");$be=isset($_GET["select"])||where($_GET);foreach(array(driver()->insertFunctions,driver()->editFunctions)as$x=>$Jd){if(!$x||(!isset($_GET["call"])&&$be)){foreach($Jd
as$Eh=>$X){if(!$Eh||preg_match("~$Eh~",$l["type"]))$J
.="/$X";}}if($x&&$Jd&&!preg_match('~set|bool~',$l["type"])&&!is_blob($l))$J
.="/SQL";}if($l["auto_increment"]&&!$be)$J='Auto Increment';return
explode("/",$J);}function
editInput($R,array$l,$b,$Y){if($l["type"]=="enum")return(isset($_GET["select"])?"<label><input type='radio'$b value='orig' checked><i>".'original'."</i></label> ":"").enum_input("radio",$b,$l,$Y,"NULL");return"";}function
editHint($R,array$l,$Y){return"";}function
processInput(array$l,$Y,$q=""){if($q=="SQL")return$Y;$B=$l["field"];$J=q($Y);if(preg_match('~^(now|getdate|uuid)$~',$q))$J="$q()";elseif(preg_match('~^current_(date|timestamp)$~',$q))$J=$q;elseif(preg_match('~^([+-]|\|\|)$~',$q))$J=idf_escape($B)." $q $J";elseif(preg_match('~^[+-] interval$~',$q))$J=idf_escape($B)." $q ".(preg_match("~^(\\d+|'[0-9.: -]') [A-Z_]+\$~i",$Y)&&JUSH!="pgsql"?$Y:$J);elseif(preg_match('~^(addtime|subtime|concat)$~',$q))$J="$q(".idf_escape($B).", $J)";elseif(preg_match('~^(md5|sha1|password|encrypt)$~',$q))$J="$q($J)";return
unconvert_field($l,$J);}function
dumpOutput(){$J=array('text'=>'open','file'=>'save');if(function_exists('gzencode'))$J['gz']='gzip';return$J;}function
dumpFormat(){return(support("dump")?array('sql'=>'SQL'):array())+array('csv'=>'CSV,','csv;'=>'CSV;','tsv'=>'TSV');}function
dumpPrint(){}function
dumpDatabase($i){}function
dumpTable($R,$Aj,$Xe=0){if($_POST["format"]!="sql"){echo"\xef\xbb\xbf";if($Aj)dump_csv(array_keys(fields($R)));}else{if($Xe==2){$m=array();foreach(fields($R)as$B=>$l)$m[]=idf_escape($B)." $l[full_type]";$g="CREATE TABLE ".table($R)." (".implode(", ",$m).")";}else$g=create_sql($R,$_POST["auto_increment"],$Aj);set_utf8mb4($g);if($Aj&&$g){if(($Aj=="DROP+CREATE"&&!function_exists('Adminer\drop_sql'))||$Xe==1)echo"DROP ".($Xe==2?"VIEW":"TABLE")." IF EXISTS ".table($R).";\n";if($Xe==1)$g=remove_definer($g);echo"$g;\n\n";}}}function
dumpData($R,$Aj,$H,array$M=array(),array$Z=array(),array$r=array(),array$D=array()){if($Aj){$Lf=(JUSH=="sqlite"?0:1048576);$m=array();$re=false;if($_POST["format"]=="sql"){if($Aj=="TRUNCATE+INSERT"&&!function_exists('Adminer\truncate_all_sql'))echo
truncate_sql($R).";\n";$m=fields($R);if(JUSH=="mssql"){foreach($m
as$l){if($l["auto_increment"]){echo"SET IDENTITY_INSERT ".table($R)." ON;\n";$re=true;break;}}}}$I=($H!=""?connection()->query($H,1):driver()->select($R,($M?:array("*")),$Z,$r,$D,0));if($I){$Ie="";$Sa="";$df=array();$Kd=array();$Cj="";$jd=($R!=''?'fetch_assoc':'fetch_row');$Eb=0;while($K=$I->$jd()){if(!$df){$bl=array();foreach($K
as$X){$l=$I->fetch_field();if(idx($m[$l->name],'generated')){$Kd[$l->name]=true;continue;}$df[]=$l->name;$x=idf_escape($l->name);$bl[]="$x = VALUES($x)";}$Cj=($Aj=="INSERT+UPDATE"?"\nON DUPLICATE KEY UPDATE ".implode(", ",$bl):"").";\n";}if($_POST["format"]!="sql"){if($Aj=="table"){dump_csv($df);$Aj="INSERT";}dump_csv($K);}else{if(!$Ie)$Ie="INSERT INTO ".table($R)." (".implode(", ",array_map('Adminer\idf_escape',$df)).") VALUES";foreach($K
as$x=>$X){if($Kd[$x]){unset($K[$x]);continue;}$l=$m[$x];$K[$x]=($X===null?"NULL":($X===false?0:unconvert_field($l,preg_match(number_type(),$l["type"])&&!preg_match('~\[~',$l["full_type"])&&is_numeric($X)?$X:(!is_blob($l)||is_utf8($X)?q($X):driver()->quoteBinary($X)))));}$Hi=($Lf?"\n":" ")."(".implode(",\t",$K).")";if(!$Sa)$Sa=$Ie.$Hi;elseif(JUSH=='mssql'?$Eb%1000!=0:strlen($Sa)+4+strlen($Hi)+strlen($Cj)<$Lf)$Sa
.=",$Hi";else{echo$Sa.$Cj;$Sa=$Ie.$Hi;}}$Eb++;}if($Sa)echo$Sa.$Cj;}elseif($_POST["format"]=="sql")echo"-- ".str_replace("\n"," ",connection()->error)."\n";if($re)echo"SET IDENTITY_INSERT ".table($R)." OFF;\n";}}function
dumpFilename($qe){return
friendly_url($qe!=""?$qe:(SERVER?:"localhost"));}function
dumpHeaders($qe,$ng=false){$lh=$_POST["output"];$bd=(preg_match('~sql~',$_POST["format"])?"sql":($ng?"tar":"csv"));header("Content-Type: ".($lh=="gz"?"application/x-gzip":($bd=="tar"?"application/x-tar":($bd=="sql"||$lh!="file"?"text/plain":"text/csv")."; charset=utf-8")));if($lh=="gz"){ob_start(function($Q){return
gzencode($Q);},1e6);}return$bd;}function
dumpFooter(){if($_POST["format"]=="sql")echo"-- ".gmdate("Y-m-d H:i:s e")."\n";}function
importServerPath(){return"adminer.sql";}function
importPrint(){}function
importProcess(){return
false;}function
homepage(){echo'<p class="links">'.($_GET["ns"]==""&&support("database")?'<a href="'.h(ME).'database=">'.'Alter database'."</a>\n":""),(support("scheme")?"<a href='".h(ME)."scheme='>".($_GET["ns"]!=""?'Alter schema':'Create schema')."</a>\n":""),($_GET["ns"]!==""?'<a href="'.h(ME).'schema=">'.'Database schema'."</a>\n":""),(support("privileges")?"<a href='".h(ME)."privileges='>".'Privileges'."</a>\n":"");if($_GET["ns"]!=="")echo(support("routine")?"<a href='#routines'>".'Routines'."</a>\n":""),(support("sequence")?"<a href='#sequences'>".'Sequences'."</a>\n":""),(support("type")?"<a href='#user-types'>".'User types'."</a>\n":""),(support("event")?"<a href='#events'>".'Events'."</a>\n":"");return
true;}function
navigation($ig){echo"<h1>".adminer()->name()." <span class='version'>".VERSION;$yg=$_COOKIE["adminer_version"];echo" <a href='https://www.adminer.org/#download'".target_blank()." id='version'>".(version_compare(VERSION,$yg)<0?h($yg):"").version_iframe()."</a>","</span></h1>\n";if($ig=="auth"){$lh="";foreach((array)$_SESSION["pwds"]as$dl=>$aj){foreach($aj
as$N=>$Wk){$B=h(get_setting("vendor-$dl-$N")?:get_driver($dl));foreach($Wk
as$V=>$F){if($B&&$F!==null){$Ub=$_SESSION["db"][$dl][$N][$V];foreach(($Ub?array_keys($Ub):array(""))as$i)$lh
.="<li><a href='".h(auth_url($dl,$N,$V,$i))."'>($B) ".h("$V@").($N!=""?adminer()->serverName($N):"").h($i!=""?" - $i":"")."</a>\n";}}}}if($lh)echo"<ul id='logins'".on('mouseover','menuOver').on('mouseout','menuOut').">\n$lh</ul>\n";}else{$T=array();if($_GET["ns"]!==""&&!$ig&&DB!=""){connection()->select_db(DB);$T=table_status('',true);}adminer()->syntaxHighlighting($T);adminer()->databasesPrint($ig);$ga=array();if(DB==""||!$ig){if(support("sql")){$ga['sql']="<a href='".h(ME)."sql='".bold(isset($_GET["sql"])&&!isset($_GET["import"])).">".'SQL command'."</a>";$ga['import']="<a href='".h(ME)."import='".bold(isset($_GET["import"])).">".'Import'."</a>";}$ga['dump']="<a href='".h(ME)."dump=".url_escape(isset($_GET["table"])?$_GET["table"]:$_GET["select"])."' id='dump'".bold(isset($_GET["dump"])).">".'Export'."</a>";}$we=$_GET["ns"]!==""&&!$ig&&DB!="";if($we&&function_exists('Adminer\alter_table'))$ga['create']='<a href="'.h(ME).'create="'.bold($_GET["create"]==="").">".'Create table'."</a>";$ga=adminer()->menuActions($ga,$ig);echo($ga?"<p class='links'>\n".implode("\n",$ga)."\n":"");if($we){if($T)adminer()->tablesPrint($T);else
echo"<p class='message'>".'No tables.'."</p>\n";}}}function
syntaxHighlighting(array$T){echo
script_src(preg_replace("~\\?.*~","",ME)."?file=jush.js&version=6.0.2",true);$kg=preg_replace('~<(?=/script)~i','<\\',Driver::jushModule());echo($kg?script("addEventListener('DOMContentLoaded', () => {\n$kg\n});"):"");if(support("sql")){echo"<script".nonce().">\n";if($T){$yf=array();foreach($T
as$R=>$U)$yf[]=js_escape_re($R);echo"var jushLinks = { ".JUSH.":";json_row(js_escape(ME).(support("table")?"table":"select").'=$&','/\b(?<!\$)('.implode('|',$yf).')(?!\$)\b/g',false);$uj=array("sql","check","event","procedure","trigger","view","type","table","processlist");if(support("routine")&&array_intersect_key($_GET,array_flip($uj))){foreach(routines()as$K)json_row(js_escape(ME).'function='.url_escape($K["SPECIFIC_NAME"]).'&name=$&','/\b'.js_escape_re($K["ROUTINE_NAME"]).'(?=["`\]]?\()/g',false);}json_row('');echo"};\n";foreach(array("bac","bra","sqlite_quo","mssql_bra")as$X)echo"jushLinks.$X = jushLinks.".JUSH.";\n";if(array_intersect_key($_GET,array_flip(array("sql","check","event","procedure","trigger","view")))){$xj=(isset($_GET["trigger"])?array('INSERT INTO','UPDATE','DELETE FROM'):(isset($_GET["check"])?array():(isset($_GET["view"])?array('SELECT'):null)));$Ca=Driver::jushAutocomplete($T,$xj);echo($Ca?"addEventListener('DOMContentLoaded', () => { autocompleter = $Ca; });\n":"");}}echo"</script>\n";}echo
script("syntaxHighlighting('".doc_version()."', '".connection()->flavor."');");}function
databasesPrint($ig){if(support("single_db"))return;$h=adminer()->databases();if(DB&&$h&&!in_array(DB,$h))array_unshift($h,DB);echo"<form action=''>\n<p id='dbs'>\n";hidden_fields_get();$Sb=on('mousedown','dbMouseDown').on('change','dbChange');echo"<label title='".'Database'."'>".'DB'.": ".($h?html_select("db",array(""=>"")+$h,DB,$Sb):"<input name='db' value='".h(DB)."' autocapitalize='off' size='19'>\n")."</label>","<input type='submit' value='".'Use'."'".($h?" class='hidden'":"").">\n";foreach(array("import","sql","schema","dump","privileges")as$X){if(isset($_GET[$X])){echo
input_hidden($X);break;}}echo"</p></form>\n";}function
menuActions(array$ga,$ig){return$ga;}function
tablesPrint(array$T){echo"<ul id='tables'".on('mouseover','menuOver').on('mouseout','menuOut').">";foreach($T
as$R=>$P){$R="$R";$B=adminer()->tableName($P);if($B!=""&&!$P["dependent"])echo'<li><a href="'.h(ME).'select='.url_escape($R).'"'.bold($_GET["select"]==$R||$_GET["edit"]==$R,"select hover")." title='".'Select data'."'>".'select'."</a> ",(support("table")||support("indexes")?'<a href="'.h(ME).'table='.url_escape($R).'"'.bold(in_array($R,array($_GET["table"],$_GET["create"],$_GET["indexes"],$_GET["foreign"],$_GET["trigger"],$_GET["check"],$_GET["view"])),(is_view($P)?"view":"structure"))." title='".'Show structure'."'>$B</a>":"<span>$B</span>")."\n";}echo"</ul>\n";}function
showVariables(){return
show_variables();}function
showStatus(){return
show_status();}function
processList(){return
process_list();}function
killProcess($t){return
kill_process($t);}}class
Plugins{private
static$append=array('dumpFormat'=>true,'dumpOutput'=>true,'editRowPrint'=>true,'editFunctions'=>true,'config'=>true);var$plugins;var$drivers=array();var$driverFiles=array();var$error='';private$hooks=array();function
__construct($Lh){$vc=SqlDriver::$drivers;$he=" href='https://www.adminer.org/plugins/#use'".target_blank();if($Lh===null){$Lh=array();$Ka="adminer-plugins";if(is_dir($Ka)){foreach(glob("$Ka/*.php")as$n){$od=SqlDriver::$drivers;$this->includeOnce($n);foreach(array_diff_key(SqlDriver::$drivers,$od)as$t=>$B)$this->driverFiles[$t]=$n;}}if(file_exists("$Ka.php")){$ye=$this->includeOnce("$Ka.php");if(is_array($ye)){foreach($ye
as$x=>$Ih)$Lh[is_object($Ih)?get_class($Ih):$x]=$Ih;}else$this->error
.=sprintf('%s must <a%s>return an array</a>.',"<b>$Ka.php</b>",$he)."<br>";}foreach(get_declared_classes()as$hb){if(!$Lh[$hb]&&(preg_match('~^Adminer\w~i',$hb)||is_subclass_of($hb,'Adminer\Plugin'))){$qi=new
\ReflectionClass($hb);$xb=$qi->getConstructor();if($xb&&$xb->getNumberOfRequiredParameters())$this->error
.=sprintf('<a%s>Configure</a> %s in %s.',$he,"<b>$hb</b>","<b>$Ka.php</b>")."<br>";else$Lh[$hb]=new$hb;}}}$Ne=array_filter($Lh,function($Ih){return!is_object($Ih);});if($Ne){$this->error
.=sprintf('Every plugin must <a%s>be an object</a>.',$he)."<br>";$Lh=array_diff_key($Lh,$Ne);}$this->drivers=array_diff_key(SqlDriver::$drivers,$vc);$this->plugins=$Lh;$ia=new
Adminer;$Lh[]=$ia;$qi=new
\ReflectionObject($ia);foreach($qi->getMethods()as$fg){foreach($Lh
as$Ih){$B=$fg->getName();if(method_exists($Ih,$B))$this->hooks[$B][]=$Ih;}}}function
includeOnce($n){return
include_once"./$n";}static
function
checksum($n){$nd=str_replace("\r","",file_get_contents($n));$nd=preg_replace('~\n\tprotected \$translations = array\(.*?\n\t\);~s','',$nd);return
dechex(crc32($nd));}function
checksums(){$pd=array_values($this->driverFiles);foreach($this->plugins
as$Ih){$qi=new
\ReflectionObject($Ih);$pd[]=$qi->getFileName();}$J=array();foreach($pd
as$n)$J[basename($n,'.php')]=self::checksum($n);return$J;}static
function
officialChecksums(){return
array('adminer.js'=>'a0599090','backward-keys'=>'ed1ef78f','before-unload'=>'2a613523','config'=>'722eb4af','dark-switcher'=>'3d490dea','database-hide'=>'e304a899','designs'=>'ed7e44e3','dump-alter'=>'896b579e','dump-bz2'=>'f0d0e336','dump-date'=>'adc7f1c7','dump-json'=>'767dd321','dump-xml'=>'4fc3cd60','dump-zip'=>'93817d96','edit-foreign'=>'72ad1562','edit-textarea'=>'a24c3cc','editor-setup'=>'a7dc3a37','editor-views'=>'5c12b185','enum-option'=>'1e24970e','file-upload'=>'10add0e8','foreign-system'=>'ebb4c654','frames'=>'b0e1d11a','highlight-codemirror'=>'c5716555','highlight-monaco'=>'edd1b0af','highlight-prism'=>'267948e5','import-csv'=>'d429c77','login-ip'=>'4d174fea','login-otp'=>'5b5a68af','login-passkey'=>'f69f2f06','login-password-less'=>'e150daac','login-reverse-proxy'=>'24558ea2','login-servers'=>'19c42e45','login-ssl'=>'6ed147bc','login-table'=>'811f8cef','menu-links'=>'c78461b3','remote-color'=>'ddeecc48','row-numbers'=>'eec8698c','select-email'=>'f84fbd2c','select-image'=>'f55c0231','slugify'=>'dec64713','sql-gemini'=>'c60ab309','sql-log'=>'8e435000','table-indexes-structure'=>'a90cc0c9','table-structure'=>'a8458e02','tables-filter'=>'ec2bcd6e','timeout'=>'97321caf','version-github'=>'627cadf9','version-noverify'=>'966937e9','clickhouse'=>'c66e1af6','elastic'=>'da03fb2a','firebird'=>'2f32108a','igdb'=>'ac7fbeff','imap'=>'c9dd2dd6','mongo'=>'f33a5c03','redis'=>'8603c834','simpledb'=>'1ef5b158',);}function
__call($B,array$ph){$va=array();foreach($ph
as$x=>$X)$va[]=&$ph[$x];$J=null;foreach($this->hooks[$B]as$Ih){$Y=call_user_func_array(array($Ih,$B),$va);if($Y!==null){if(!self::$append[$B])return$Y;$J=$Y+(array)$J;}}return$J;}}abstract
class
Plugin{protected$translations=array();function
description(){return$this->lang('');}function
screenshot(){return"";}protected
function
lang($u,$Cg=null){$va=func_get_args();$va[0]=idx($this->translations[LANG],$u)?:$u;return
call_user_func_array('Adminer\lang_format',$va);}}class
Password{private$password_hash;private$password_matches=null;function
__construct($Ah){$this->password_hash=$Ah;}function
description(){return'Require a password verified by Adminer';}function
credentials(){$F=get_password();return
array(SERVER,$_GET["username"],($this->passwordMatches($F)&&!password_required()?"":$F));}function
login($Bf,$F){if($this->passwordMatches($F))return
true;}protected
function
passwordMatches($F){if($this->password_matches===null)$this->password_matches=(function_exists('password_verify')&&password_verify(strval($F),$this->password_hash));return$this->password_matches;}}Adminer::$instance=(function_exists('adminer_object')?adminer_object():(is_dir("adminer-plugins")||file_exists("adminer-plugins.php")?new
Plugins(null):new
Adminer));SqlDriver::$drivers=array("server"=>"MySQL / MariaDB")+SqlDriver::$drivers;if(!defined('Adminer\DRIVER')){define('Adminer\DRIVER',"server");if(extension_loaded("mysqli")&&$_GET["ext"]!="pdo"){class
Db
extends
\mysqli{static$instance;var$extension="MySQLi",$flavor='';function
__construct(){parent::init();}function
attach(array$N,$V,$F){mysqli_report(MYSQLI_REPORT_OFF);$Mh=$N["port"];$Gc=("$N[host]$Mh$N[socket]"=="");$vj=adminer()->connectSsl();$Uk=($vj&&($vj['key']||$vj['cert']||$vj['ca']||isset($vj['verify'])));if($Uk)$this->ssl_set($vj['key'],$vj['cert'],$vj['ca'],'','');$J=@$this->real_connect((!$Gc?$N["host"]:ini_get("mysqli.default_host")),(!$Gc||$V!=""?$V:ini_get("mysqli.default_user")),(!$Gc||$V.$F!=""?$F:ini_get("mysqli.default_pw")),null,($Mh!=""?intval($Mh):ini_get("mysqli.default_port")),($Mh!=""?null:$N["socket"]),($Uk?($vj['verify']!==false?MYSQLI_CLIENT_SSL:64):0));$this->options(MYSQLI_OPT_LOCAL_INFILE,0);return($J?'':$this->error);}function
set_charset($Ya){if(parent::set_charset($Ya))return
true;parent::set_charset('utf8');return$this->query("SET NAMES $Ya");}function
next_result(){return
self::more_results()&&parent::next_result();}function
quote($Q){return"'".$this->escape_string($Q)."'";}function
inTransaction(){return
false;}}}elseif(extension_loaded("mysql")&&!((ini_bool("sql.safe_mode")||ini_bool("mysql.allow_local_infile"))&&extension_loaded("pdo_mysql"))){class
Db
extends
SqlDb{private$link;function
attach(array$N,$V,$F){if(ini_bool("mysql.allow_local_infile"))return
sprintf('Disable %s or enable the %s or %s extension.',"'mysql.allow_local_infile'","MySQLi","PDO_MySQL");$Mh="$N[port]$N[socket]";$B=$N["host"].($Mh!=""?":$Mh":"");$this->link=@mysql_connect(($B!=""?$B:ini_get("mysql.default_host")),($B.$V!=""?$V:ini_get("mysql.default_user")),($B.$V.$F!=""?$F:ini_get("mysql.default_password")),true,131072);if(!$this->link)return
mysql_error();$this->server_info=mysql_get_server_info($this->link);return'';}function
set_charset($Ya){return
mysql_set_charset($Ya,$this->link)||mysql_set_charset('utf8',$this->link);}function
quote($Q){return"'".mysql_real_escape_string($Q,$this->link)."'";}function
select_db($Rb){return
mysql_select_db($Rb,$this->link);}function
query($H,$Dk=false){$I=@($Dk?mysql_unbuffered_query($H,$this->link):mysql_query($H,$this->link));$this->error="";if(!$I){$this->errno=mysql_errno($this->link);$this->error=mysql_error($this->link);return
false;}if($I===true){$this->affected_rows=mysql_affected_rows($this->link);$this->info=mysql_info($this->link);return
true;}return
new
Result($I);}}class
Result{var$num_rows;private$result;private$offset=0;function
__construct($I){$this->result=$I;$this->num_rows=mysql_num_rows($I);}function
fetch_assoc(){return
mysql_fetch_assoc($this->result);}function
fetch_row(){return
mysql_fetch_row($this->result);}function
fetch_field(){$J=mysql_fetch_field($this->result,$this->offset++);$J->orgtable=$J->table;$J->charsetnr=($J->blob?63:0);return$J;}}}elseif(extension_loaded("pdo_mysql")){class
Db
extends
PdoDb{var$extension="PDO_MySQL";function
attach(array$N,$V,$F){$C=array(\PDO::MYSQL_ATTR_LOCAL_INFILE=>false);if(isset($_GET["select"]))$C[\PDO::MYSQL_ATTR_MULTI_STATEMENTS]=false;$vj=adminer()->connectSsl();if($vj){if($vj['key'])$C[\PDO::MYSQL_ATTR_SSL_KEY]=$vj['key'];if($vj['cert'])$C[\PDO::MYSQL_ATTR_SSL_CERT]=$vj['cert'];if($vj['ca'])$C[\PDO::MYSQL_ATTR_SSL_CA]=$vj['ca'];if(isset($vj['verify']))$C[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=$vj['verify'];}$me=$N["host"];$Mh=$N["port"];$jj=$N["socket"];return$this->dsn("mysql:charset=utf8".($me!=""?";host=$me":'').($Mh!=""?";port=$Mh":($jj!=""?";unix_socket=$jj":"")),$V,$F,$C);}function
set_charset($Ya){return$this->query("SET NAMES $Ya");}function
select_db($Rb){return$this->query("USE ".idf_escape($Rb));}function
query($H,$Dk=false){$this->pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,!$Dk);return
parent::query($H,$Dk);}}}class
Driver
extends
SqlDriver{static$extensions=array("MySQLi","MySQL","PDO_MySQL");static$jush="sql";static$serverSocket=true;var$unsigned=array("unsigned","zerofill","unsigned zerofill");var$functions=array("char_length","date","from_unixtime","lower","round","floor","ceil","sec_to_time","time_to_sec","upper");var$grouping=array("avg","count","count distinct","group_concat","max","min","sum");var$partitionBy=array("HASH","LINEAR HASH","KEY","LINEAR KEY","RANGE","LIST");function
operators($Ij){return
array("=","<",">","<=",">=","!=","LIKE","LIKE %%","REGEXP","IN","FIND_IN_SET","IS NULL","NOT LIKE","NOT REGEXP","NOT IN","IS NOT NULL","SQL");}static
function
connect($N,$V,$F){$e=parent::connect($N,$V,$F);if(is_string($e)){if(function_exists('iconv')&&!is_utf8($e)&&strlen($Hi=iconv("windows-1252","utf-8//IGNORE",$e))>strlen($e))$e=$Hi;return$e;}$e->set_charset(charset($e));$e->query("SET sql_quote_show_create = 1, autocommit = 1");$e->flavor=(preg_match('~MariaDB~',$e->server_info)?'maria':'mysql');add_driver(DRIVER,($e->flavor=='maria'?"MariaDB":"MySQL"));return$e;}function
__construct(Db$e){parent::__construct($e);$this->types=array('Numbers'=>array("tinyint"=>3,"smallint"=>5,"mediumint"=>8,"int"=>10,"bigint"=>20,"decimal"=>66,"float"=>12,"double"=>21),'Date and time'=>array("date"=>10,"datetime"=>19,"timestamp"=>19,"time"=>10,"year"=>4),'Strings'=>array("char"=>255,"varchar"=>65535,"tinytext"=>255,"text"=>65535,"mediumtext"=>16777215,"longtext"=>4294967295),'Lists'=>array("enum"=>65535,"set"=>64),'Binary'=>array("bit"=>20,"binary"=>255,"varbinary"=>65535,"tinyblob"=>255,"blob"=>65535,"mediumblob"=>16777215,"longblob"=>4294967295),'Geometry'=>array("geometry"=>0,"point"=>0,"linestring"=>0,"polygon"=>0,"multipoint"=>0,"multilinestring"=>0,"multipolygon"=>0,"geometrycollection"=>0),);$this->insertFunctions=array("char"=>"md5/sha1/password/encrypt/uuid","binary"=>"md5/sha1","date|time"=>"now",);$this->editFunctions=array(number_type()=>"+/-","date"=>"+ interval/- interval","time"=>"addtime/subtime","char|text"=>"concat",);if(min_version('5.7.8',10.2,$e))$this->types['Strings']["json"]=4294967295;if(min_version('',10.7,$e)){$this->types['Strings']["uuid"]=128;$this->insertFunctions['uuid']='uuid';}if(min_version('',10.5,$e)){$this->types['Network']["inet6"]=39;if(min_version('','10.10',$e))$this->types['Network']["inet4"]=15;}if(min_version(9,11.7,$e))$this->types['Numbers']["vector"]=16383;if(min_version(5.7,10.2,$e))$this->generated=array("STORED","VIRTUAL");}function
unconvertFunction(array$l){return(preg_match("~binary~",$l["type"])?"<code class='jush-sql'>UNHEX</code>":($l["type"]=="bit"?doc_link(array('sql'=>'bit-value-literals.html'),"<code>b''</code>"):($l["type"]=="vector"?"<code class='jush-sql'>".($this->conn->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."</code>":(preg_match("~geom|point|linestring|polygon~",$l["type"])?"<code class='jush-sql'>GeomFromText</code>":""))));}function
insert($R,array$O){return($O?parent::insert($R,$O):queries("INSERT INTO ".table($R)." ()\nVALUES ()"));}function
insertUpdate($R,array$L,array$Xh){$d=array_keys(reset($L));$Th="INSERT INTO ".table($R)." (".implode(", ",$d).") VALUES\n";$bl=array();foreach($d
as$x)$bl[$x]="$x = VALUES($x)";$Cj="\nON DUPLICATE KEY UPDATE ".implode(", ",$bl);$bl=array();$rf=0;foreach($L
as$O){$Y="(".implode(", ",$O).")";if($bl&&(strlen($Th)+$rf+strlen($Y)+strlen($Cj)>1e6)){if(!queries($Th.implode(",\n",$bl).$Cj))return
false;$bl=array();$rf=0;}$bl[]=$Y;$rf+=strlen($Y)+2;}return
queries($Th.implode(",\n",$bl).$Cj);}function
slowQuery($H,$fk){if(min_version('5.7.8','10.1.2')){if($this->conn->flavor=='maria')return"SET STATEMENT max_statement_time=$fk FOR $H";elseif(preg_match('~^(SELECT\b)(.+)~is',$H,$A))return"$A[1] /*+ MAX_EXECUTION_TIME(".($fk*1000).") */ $A[2]";}}function
convertColumn($u,array$l){if(preg_match("~binary~",$l["type"]))return"HEX($u)";if($l["type"]=="bit")return"BIN($u + 0)";if($l["type"]=="vector")return($this->conn->flavor=='maria'?"VEC_ToText":"VECTOR_TO_STRING")."($u)";if(preg_match("~geom|point|linestring|polygon~",$l["type"]))return(min_version(8)?"ST_":"")."AsWKT($u)";return"";}function
convertSearch($u,array$X,array$l){return($this->convertColumn($u,$l)?:(preg_match('~'.text_type().'~',$l["type"])&&!preg_match("~^utf8~",$l["collation"])&&preg_match('~[\x80-\xFF]~',$X['val'])?"CONVERT($u USING ".charset($this->conn).")":$u));}function
typeName(\stdClass$l){$Ck=array("decimal","tinyint","smallint","int","float","double",7=>"timestamp","bigint","mediumint","date","time","datetime","year",15=>"varchar","bit",242=>"vector",245=>"json","decimal","enum","set","tinytext","mediumtext","longtext","text","varchar","char","geometry",);$J=idx($Ck,$l->type,"");return
parent::typeName($l)?:($l->charsetnr==63?str_replace(array("text","varchar","char"),array("blob","varbinary","binary"),$J):$J);}function
quoteBinary($Hi){return"X".q(bin2hex($Hi));}function
warnings(){$I=$this->conn->query("SHOW WARNINGS");if($I&&$I->num_rows){ob_start();print_select_result($I);return
ob_get_clean();}}function
tableHelp($B,$Xe=false){$Df=($this->conn->flavor=='maria');if(information_schema(DB))return
strtolower(str_replace("_","-",DB)."-".($Df?"$B-table/":str_replace("_","-",$B)."-table.html"));if(DB=="sys")return($Df?"sys-schema/":strtolower("sys-".str_replace("_","-",preg_replace('~^x\$~','',$B)).".html"));if(DB=="mysql")return($Df?"mysql$B-table/":"system-schema.html");}function
partitionsInfo($R){$Ed="FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA = ".q(DB)." AND TABLE_NAME = ".q($R);$I=$this->conn->query("SELECT PARTITION_METHOD, PARTITION_EXPRESSION, PARTITION_ORDINAL_POSITION $Ed ORDER BY PARTITION_ORDINAL_POSITION DESC LIMIT 1");$K=($I?$I->fetch_row():null);if(!$K)return
array();$J=array();list($J["partition_by"],$J["partition"],$J["partitions"])=$K;$xh=get_key_vals("SELECT PARTITION_NAME, PARTITION_DESCRIPTION $Ed AND PARTITION_NAME != '' ORDER BY PARTITION_ORDINAL_POSITION");$J["partition_names"]=array_keys($xh);$J["partition_values"]=array_values($xh);return$J;}function
checkConstraints($R){$J=parent::checkConstraints($R);return($this->conn->flavor=='maria'?$J:array_map('stripslashes',$J));}function
hasCStyleEscapes(){static$Ta;if($Ta===null){$tj=get_val("SHOW VARIABLES LIKE 'sql_mode'",1,$this->conn);$Ta=(strpos($tj,'NO_BACKSLASH_ESCAPES')===false);}return$Ta;}function
lineComment(){return"#|-- ";}function
engines(){$J=array();foreach(get_rows("SHOW ENGINES")as$K){if(preg_match("~YES|DEFAULT~",$K["Support"]))$J[]=$K["Engine"];}return$J;}function
indexAlgorithms(array$Ij){return(preg_match('~^(MEMORY|NDB)$~',$Ij["Engine"])?array("HASH","BTREE"):array());}}function
idf_escape($u){return"`".str_replace("`","``",$u)."`";}function
table($u){return
idf_escape($u);}function
get_databases($wd){$J=get_session("dbs");if($J===null){$H="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME";$wj=microtime(true);$J=($wd?slow_query($H):get_vals($H));if(microtime(true)-$wj>0.1){restart_session();set_session("dbs",$J);stop_session();}}return$J;}function
limit($H,$Z,$y,$Ig=0,$Ui=" "){return" $H$Z".($y?$Ui."LIMIT $y".($Ig?" OFFSET $Ig":""):"");}function
limit1($R,$H,$Z,$Ui="\n"){return
limit($H,$Z,1,0,$Ui);}function
db_collation($i,array$mb){$J=null;$g=get_val("SHOW CREATE DATABASE ".idf_escape($i),1);if(preg_match('~ COLLATE ([^ ]+)~',$g,$A))$J=$A[1];elseif(preg_match('~ CHARACTER SET ([^ ]+)~',$g,$A))$J=$mb[$A[1]][-1];return$J;}function
logged_user(){return
get_val("SELECT USER()");}function
tables_list(){return
get_key_vals("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");}function
count_tables(array$h){$J=array();foreach($h
as$i)$J[$i]=count(get_vals("SHOW TABLES IN ".idf_escape($i)));return$J;}function
table_status($B="",$hd=false){$J=array();$H="SELECT ENGINE AS Engine, TABLE_NAME AS Name, TABLE_COMMENT AS Comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ".($B!=""?"AND TABLE_NAME = ".q($B):"ORDER BY Name");$Ji=array();foreach(($hd?array():get_rows($H))as$K)$Ji[$K["Name"]]=$K;$Wh=null;foreach(get_rows($hd?$H:"SHOW TABLE STATUS".($B!=""?" LIKE ".q(addcslashes($B,"%_\\")):""))as$K){$ih=idx($Ji,$K["Name"]);if($ih){if($K["Comment"]!==$ih["Comment"]&&$K["Comment"]!==$Wh)$K["Error"]=$K["Comment"];$Wh=$K["Comment"];$K["Comment"]=$ih["Comment"];$K["Engine"]=$ih["Engine"];}if($K["Engine"]=="InnoDB")$K["Comment"]=preg_replace('~(?:(.+); )?InnoDB free: .*~','\1',$K["Comment"]);if(!isset($K["Engine"]))$K["Comment"]="";if($B!="")$K["Name"]=$B;$J[$K["Name"]]=$K;}return$J;}function
is_view(array$S){return$S["Engine"]===null;}function
fk_support(array$S){return
preg_match('~InnoDB|IBMDB2I'.(min_version(5.6)?'|NDB':'').'~i',$S["Engine"]);}function
parse_type($Gd){preg_match('~^([^( ]+)(?:\((.+)\))?( unsigned)?( zerofill)?$~',$Gd,$A);return
array($A[1],$A[2],ltrim($A[3].$A[4]));}function
fields($R){$Df=(connection()->flavor=='maria');$J=array();foreach(get_rows("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".q($R)." ORDER BY ORDINAL_POSITION")as$K){$l=$K["COLUMN_NAME"];$U=$K["COLUMN_TYPE"];$Ld=$K["GENERATION_EXPRESSION"];$ed=$K["EXTRA"];preg_match('~^(VIRTUAL|PERSISTENT|STORED)~',$ed,$Kd);list($Bk,$rf,$Jk)=parse_type($U);$j=$K["COLUMN_DEFAULT"];if($j!=""){$We=preg_match('~text|json~',$Bk);if(!$Df&&$We)$j=preg_replace("~^(_\w+)?('.*')$~",'\2',stripslashes($j));if($Df||$We){$j=($j=="NULL"?null:preg_replace_callback("~^'(.*)'$~",function($A){return
stripslashes(str_replace("''","'",$A[1]));},$j));}if(!$Df&&preg_match('~binary~',$Bk)&&preg_match('~^0x(\w*)$~',$j,$A))$j=pack("H*",$A[1]);}$J[$l]=array("field"=>$l,"full_type"=>$U,"type"=>$Bk,"length"=>$rf,"unsigned"=>$Jk,"default"=>($Kd?($Df?$Ld:stripslashes($Ld)):$j),"null"=>($K["IS_NULLABLE"]=="YES"),"auto_increment"=>($ed=="auto_increment"),"on_update"=>(preg_match('~\bon update (\w+)~i',$ed,$A)?$A[1]:""),"collation"=>$K["COLLATION_NAME"],"privileges"=>array_flip(explode(",","$K[PRIVILEGES],where,order")),"comment"=>$K["COLUMN_COMMENT"],"primary"=>($K["COLUMN_KEY"]=="PRI"),"generated"=>($Kd[1]=="PERSISTENT"?"STORED":$Kd[1]),);}return$J;}function
indexes($R,$f=null){$J=array();foreach(get_rows("SHOW INDEX FROM ".table($R),$f)as$K){$B=$K["Key_name"];$J[$B]["type"]=($B=="PRIMARY"?"PRIMARY":($K["Index_type"]=="FULLTEXT"?"FULLTEXT":($K["Non_unique"]?(preg_match('~^(SPATIAL|VECTOR)$~',$K["Index_type"])?$K["Index_type"]:"INDEX"):"UNIQUE")));$J[$B]["columns"][]=$K["Column_name"];$J[$B]["lengths"][]=($K["Index_type"]=="SPATIAL"?null:$K["Sub_part"]);$J[$B]["descs"][]=null;$J[$B]["algorithm"]=$K["Index_type"];}return$J;}function
foreign_keys($R){static$Eh='(?:`(?:[^`]|``)+`|"(?:[^"]|"")+")';$J=array();$Fb=get_val("SHOW CREATE TABLE ".table($R),1);if($Fb){preg_match_all("~CONSTRAINT ($Eh) FOREIGN KEY ?\\(((?:$Eh,? ?)+)\\) REFERENCES ($Eh)(?:\\.($Eh))? \\(((?:$Eh,? ?)+)\\)(?: ON DELETE (".driver()->onActions."))?(?: ON UPDATE (".driver()->onActions."))?~",$Fb,$Ff,PREG_SET_ORDER);foreach($Ff
as$A){preg_match_all("~$Eh~",$A[2],$nj);preg_match_all("~$Eh~",$A[5],$Vj);$J[idf_unescape($A[1])]=array("db"=>idf_unescape($A[4]!=""?$A[3]:$A[4]),"table"=>idf_unescape($A[4]!=""?$A[4]:$A[3]),"source"=>array_map('Adminer\idf_unescape',$nj[0]),"target"=>array_map('Adminer\idf_unescape',$Vj[0]),"on_delete"=>($A[6]?:"RESTRICT"),"on_update"=>($A[7]?:"RESTRICT"),);}}return$J;}function
view($B){return
array("select"=>preg_replace('~^(?:[^`]|`[^`]*`)*\s+AS\s+~isU','',get_val("SHOW CREATE VIEW ".table($B),1)));}function
collations(){$J=array();foreach(get_rows("SHOW COLLATION")as$K){if($K["Default"])$J[$K["Charset"]][-1]=$K["Collation"];else$J[$K["Charset"]][]=$K["Collation"];}ksort($J);foreach($J
as$x=>$X)sort($J[$x]);return$J;}function
information_schema($i,$Ji=""){return($i=="information_schema")||(min_version(5.5)&&$i=="performance_schema");}function
error(){return
h(preg_replace('~^You have an error.*syntax to use~U',"Syntax error",connection()->error));}function
create_database($i,$lb){return
queries("CREATE DATABASE ".idf_escape($i).($lb?" COLLATE ".q($lb):""));}function
drop_databases(array$h){$J=apply_queries("DROP DATABASE",$h,'Adminer\idf_escape');restart_session();set_session("dbs",null);return$J;}function
rename_database($B,$lb){$J=false;if(create_database($B,$lb)){$T=array();$gl=array();foreach(tables_list()as$R=>$U){if($U=='VIEW')$gl[]=$R;else$T[]=$R;}$J=(!$T&&!$gl)||move_tables($T,$gl,$B);drop_databases($J?array(DB):array());}return$J;}function
auto_increment(){$Ba=" PRIMARY KEY";if($_GET["create"]!=""&&$_POST["auto_increment_col"]){foreach(indexes($_GET["create"])as$v){if(in_array($_POST["fields"][$_POST["auto_increment_col"]]["orig"],$v["columns"],true)){$Ba="";break;}if($v["type"]=="PRIMARY")$Ba=" UNIQUE";}}return" AUTO_INCREMENT$Ba";}function
alter_table($R,$B,array$m,array$yd,$qb,$Hc,$lb,$Aa,$wh){$qa=array();foreach($m
as$l){if($l[1]){$j=$l[1][3];if(preg_match('~ GENERATED~',$j)){$l[1][3]=(connection()->flavor=='maria'?"":$l[1][2]);$l[1][2]=$j;}$qa[]=($R!=""?($l[0]!=""?"CHANGE ".idf_escape($l[0]):"ADD"):" ")." ".implode($l[1]).($R!=""?$l[2]:"");}else$qa[]="DROP ".idf_escape($l[0]);}$qa=array_merge($qa,$yd);$P=($qb!==null?" COMMENT=".q($qb):"").($Hc?" ENGINE=".q($Hc):"").($lb?" COLLATE ".q($lb):"").($Aa!=""?" AUTO_INCREMENT=$Aa":"");if($wh){$xh=array();if($wh["partition_by"]=='RANGE'||$wh["partition_by"]=='LIST'){foreach($wh["partition_names"]as$x=>$X){$Y=$wh["partition_values"][$x];$xh[]="\n  PARTITION ".idf_escape($X)." VALUES ".($wh["partition_by"]=='RANGE'?"LESS THAN":"IN").($Y!=""?" ($Y)":" MAXVALUE");}}$P
.="\nPARTITION BY $wh[partition_by]($wh[partition])";if($xh)$P
.=" (".implode(",",$xh)."\n)";elseif($wh["partitions"])$P
.=" PARTITIONS ".(+$wh["partitions"]);}elseif($wh===null)$P
.="\nREMOVE PARTITIONING";if($R=="")return
queries("CREATE TABLE ".table($B)." (\n".implode(",\n",$qa)."\n)$P");if($R!=$B)$qa[]="RENAME TO ".table($B);if($P)$qa[]=ltrim($P);return($qa?queries("ALTER TABLE ".table($R)."\n".implode(",\n",$qa)):true);}function
alter_indexes($R,$qa){$Wa=array();foreach($qa
as$X)$Wa[]=($X[2]=="DROP"?"\nDROP INDEX ".idf_escape($X[1]):"\nADD $X[0] ".($X[0]=="PRIMARY"?"KEY ":"").($X[1]!=""?idf_escape($X[1])." ":"")."(".implode(", ",$X[2]).")");return
queries("ALTER TABLE ".table($R).implode(",",$Wa));}function
truncate_tables(array$T){return
apply_queries("TRUNCATE TABLE",$T);}function
drop_views(array$gl){return
queries("DROP VIEW ".implode(", ",array_map('Adminer\table',$gl)));}function
drop_tables(array$T){return
queries("DROP TABLE ".implode(", ",array_map('Adminer\table',$T)));}function
move_tables(array$T,array$gl,$Vj){$ui=array();foreach($T
as$R)$ui[]=table($R)." TO ".idf_escape($Vj).".".table($R);if(!$ui||queries("RENAME TABLE ".implode(", ",$ui))){$bc=array();foreach($gl
as$R)$bc[table($R)]=view($R);connection()->select_db($Vj);$i=idf_escape(DB);foreach($bc
as$B=>$fl){if(!queries("CREATE VIEW $B AS ".str_replace(" $i."," ",$fl["select"]))||!queries("DROP VIEW $i.$B"))return
false;}return
true;}return
false;}function
copy_tables(array$T,array$gl,$Vj){queries("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");foreach($T
as$R){$B=($Vj==DB?table("copy_$R"):idf_escape($Vj).".".table($R));if(($_POST["overwrite"]&&!queries("\nDROP TABLE IF EXISTS $B"))||!queries("CREATE TABLE $B LIKE ".table($R))||!queries("INSERT INTO $B SELECT * FROM ".table($R)))return
false;foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){$uk=$K["Trigger"];list($Qc,$Eg)=trigger_event($K);if(!queries("CREATE TRIGGER ".($Vj==DB?idf_escape("copy_$uk"):idf_escape($Vj).".".idf_escape($uk))." $K[Timing] $Qc".($Eg!=""?" $Eg":"")." ON $B FOR EACH ROW\n$K[Statement];"))return
false;}}foreach($gl
as$R){$B=($Vj==DB?table("copy_$R"):idf_escape($Vj).".".table($R));$fl=view($R);if(($_POST["overwrite"]&&!queries("DROP VIEW IF EXISTS $B"))||!queries("CREATE VIEW $B AS $fl[select]"))return
false;}return
true;}function
trigger_event(array$K){$Sc=explode(",",$K["Event"]);$J=array();foreach(array("DELETE","INSERT","UPDATE")as$Qc){if(in_array($Qc,$Sc))$J[]=$Qc;}$J=implode(" OR ",$J);if(in_array("UPDATE",$Sc)&&min_version('','12.0.1')&&preg_match('~\s(?:BEFORE|AFTER)\s+(.+?)\s+ON\s~is',get_val("SHOW CREATE TRIGGER ".idf_escape($K["Trigger"]),2),$A)&&preg_match('~\bOF\s+(.+)~is',$A[1],$Eg))return
array("$J OF",$Eg[1]);return
array($J,"");}function
trigger($B,$R){if($B=="")return
array();$L=get_rows("SHOW TRIGGERS WHERE `Trigger` = ".q($B));$J=reset($L);if($J)list($J["Event"],$J["Of"])=trigger_event($J);return$J;}function
triggers($R){$J=array();foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")))as$K){list($Qc)=trigger_event($K);$J[$K["Trigger"]]=array($K["Timing"],$Qc);}return$J;}function
trigger_options(){return
array("Timing"=>array("BEFORE","AFTER"),"Event"=>(min_version('','12.0.1')?array("INSERT","UPDATE","UPDATE OF","DELETE","INSERT OR UPDATE","INSERT OR UPDATE OF","DELETE OR INSERT","DELETE OR UPDATE","DELETE OR UPDATE OF","DELETE OR INSERT OR UPDATE","DELETE OR INSERT OR UPDATE OF",):array("INSERT","UPDATE","DELETE")),"Type"=>array("FOR EACH ROW"),);}function
routine($B,$U){$L=get_rows("SELECT PARAMETER_NAME, DTD_IDENTIFIER, PARAMETER_MODE, COLLATION_NAME
FROM information_schema.PARAMETERS
WHERE SPECIFIC_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND SPECIFIC_NAME = ".q($B)."
ORDER BY ORDINAL_POSITION");$m=array();foreach($L
as$K){$Gd=$K["DTD_IDENTIFIER"];list($Bk,$rf,$Jk)=parse_type($Gd);$m[]=array("field"=>$K["PARAMETER_NAME"],"type"=>$Bk,"length"=>$rf,"unsigned"=>$Jk,"null"=>true,"full_type"=>$Gd,"inout"=>($U=="FUNCTION"?"":$K["PARAMETER_MODE"]),"collation"=>$K["COLLATION_NAME"],);}$J=(array)connection()->query("SELECT
	ROUTINE_COMMENT comment,
	ROUTINE_DEFINITION definition,
	LOWER(EXTERNAL_LANGUAGE) language,
	IF(DEFINER = CURRENT_USER(), '', DEFINER) definer,
	IF(IS_DETERMINISTIC = 'YES', 'DETERMINISTIC', 'NOT DETERMINISTIC') is_deterministic,
	SQL_DATA_ACCESS data_access,
	CONCAT('SQL SECURITY ', SECURITY_TYPE) security
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_TYPE = '$U' AND ROUTINE_NAME = ".q($B))->fetch_assoc();$J['options']=array("DEFINER"=>$J['definer'],"DETERMINISTIC"=>$J['is_deterministic'],"SQL_DATA_ACCESS"=>$J['data_access'],"SQL_SECURITY"=>$J['security'],"COMMENT"=>$J['comment'],);if($m&&$m[0]['field']=='')$J['returns']=array_shift($m);$J['fields']=$m;return$J;}function
routines(){return
get_rows("SELECT SPECIFIC_NAME, ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");}function
routine_languages(){return(min_version(9,99)?array("sql"=>"sql","javascript"=>"js"):array());}function
routine_options($Ci){return
array("DEFINER"=>array(),"DETERMINISTIC"=>array("NOT DETERMINISTIC","DETERMINISTIC"),"SQL_DATA_ACCESS"=>array("CONTAINS SQL","NO SQL","READS SQL DATA","MODIFIES SQL DATA"),"SQL_SECURITY"=>array("SQL SECURITY DEFINER","SQL SECURITY INVOKER"),"COMMENT"=>array(),);}function
routine_id($B,array$K){return
idf_escape($B);}function
last_id($I){return
get_val("SELECT LAST_INSERT_ID()");}function
explain(Db$e,$H){return$e->query("EXPLAIN ".(min_version(5.7)?"":"PARTITIONS ").$H);}function
found_rows(array$S,array$Z){return($Z||$S["Engine"]!="InnoDB"?null:$S["Rows"]);}function
create_sql($R,$Aa,$Aj){$J=get_val("SHOW CREATE TABLE ".table($R),1);if(!$Aa)$J=preg_replace('~(\n\)[^\n]*?) AUTO_INCREMENT=\d+~','\1',$J);return$J;}function
truncate_sql($R){return"TRUNCATE ".table($R);}function
use_sql($Rb,$Aj=""){$B=idf_escape($Rb);$J="";if(preg_match('~CREATE~',$Aj)&&($g=get_val("SHOW CREATE DATABASE $B",1))){set_utf8mb4($g);if($Aj=="DROP+CREATE")$J="DROP DATABASE IF EXISTS $B;\n";$J
.="$g;\n";}return$J."USE $B";}function
trigger_sql($R){$J="";foreach(get_rows("SHOW TRIGGERS LIKE ".q(addcslashes($R,"%_\\")),null,"-- ")as$K){list($K["Event"],$K["Of"])=trigger_event($K);$J
.="\n".create_trigger(" ON ".table($K["Table"]),$K+array("Type"=>"FOR EACH ROW")).";\n";}return$J;}function
show_variables(){return
get_rows("SHOW VARIABLES");}function
show_status(){return
get_rows("SHOW STATUS");}function
process_list(){return
get_rows("SHOW FULL PROCESSLIST");}function
convert_field(array$l){return
driver()->convertColumn(idf_escape($l["field"]),$l);}function
unconvert_field(array$l,$J){if(preg_match("~binary~",$l["type"]))$J="UNHEX($J)";if($l["type"]=="bit")$J="CONVERT(b$J, UNSIGNED)";if($l["type"]=="vector")$J=(connection()->flavor=='maria'?"VEC_FromText":"STRING_TO_VECTOR")."($J)";if(preg_match("~geom|point|linestring|polygon~",$l["type"])){$Th=(min_version(8)?"ST_":"");$J=$Th."GeomFromText($J, $Th"."SRID($l[field]))";}return$J;}function
support($id){return
preg_match('~^(comment|columns|copy|database|drop_col|dump|event|indexes|kill|privileges|move_col|procedure|processlist|routine|sql|status|table|trigger|variables|view'.(min_version(8)?'|descidx':'').(min_version('8.0.16','10.2.1')?'|check':'').(min_version(8,99)?'|fast_status':'').')$~',$id);}function
kill_process($t){return
queries("KILL ".number($t));}function
connection_id(){return"SELECT CONNECTION_ID()";}function
max_connections(){return
get_val("SELECT @@max_connections");}function
types($dd=false){return
array();}function
type_values($t){return"";}function
type_definition($t){return
array("kind"=>"","definition"=>"");}function
schemas(){return
array();}function
get_schema(){return"";}function
set_schema($Ji,$f=null){return
true;}}define('Adminer\JUSH',Driver::$jush);define('Adminer\SERVER',"".$_GET[DRIVER]);define('Adminer\DB',"$_GET[db]");define('Adminer\ME',preg_replace('~\?.*~','',relative_uri()).'?'.(sid()?SID.'&':'').($_GET["ext"]?"ext=".url_escape($_GET["ext"]).'&':'').(isset($_GET[DRIVER])?DRIVER."=".url_escape(SERVER).'&':'').(isset($_GET["username"])?"username=".url_escape($_GET["username"]).'&':'').(isset($_GET["db"])?'db='.url_escape(DB).'&'.(isset($_GET["ns"])?"ns=".url_escape($_GET["ns"])."&":""):''));function
page_header($hk,$k="",$Ra=array(),$ik=""){page_headers();if(is_ajax()&&$k){page_messages($k);exit;}if(!ob_get_level())ob_start('ob_gzhandler',4096);$jk=$hk.($ik!=""?": $ik":"");$kk=strip_tags($jk.(SERVER!=""&&SERVER!="localhost"?h(" - ".SERVER):"")." - ".adminer()->name());echo'<!DOCTYPE html>
<html lang=\'en\' dir=\'ltr\' class=\'ltr nojs\'>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>',$kk,'</title>
<link rel="stylesheet" href="',h(preg_replace("~\\?.*~","",ME)."?file=default.css&version=6.0.2"),'">
';$Jb=adminer()->css();if(is_int(key($Jb)))$Jb=array_fill_keys($Jb,'light');$Zd=in_array('light',$Jb)||in_array('',$Jb);$Xd=in_array('dark',$Jb)||in_array('',$Jb);$Nb=($Zd?($Xd?null:false):($Xd?:null));$Uf=" media='(prefers-color-scheme: dark)'";if($Nb!==false)echo"<link rel='stylesheet'".($Nb?"":$Uf)." href='".h(preg_replace("~\\?.*~","",ME)."?file=dark.css&version=6.0.2")."'>\n";echo"<meta name='color-scheme' content='".($Nb===null?"light dark":($Nb?"dark":"light"))."'>\n",script_src(preg_replace("~\\?.*~","",ME)."?file=functions.js&version=6.0.2");if(adminer()->head($Nb))echo"<link rel='icon' href='data:image/gif;base64,"."R0lGODlhEAAQAJEAAAQCBPz+/PwCBAROZCH5BAEAAAAALAAAAAAQABAAAAI2hI+pGO1rmghihiUdvUBnZ3XBQA7f05mOak1RWXrNq5nQWHMKvuoJ37BhVEEfYxQzHjWQ5qIAADs='>\n","<link rel='apple-touch-icon' href='".h(preg_replace("~\\?.*~","",ME)."?file=logo.png&version=6.0.2")."'>\n";foreach($Jb
as$Pk=>$jg){$b=($jg=='dark'&&!$Nb?$Uf:($jg=='light'&&$Xd?" media='(prefers-color-scheme: light)'":""));echo"<link rel='stylesheet'$b href='".h($Pk)."'>\n";}echo"\n<body class='";adminer()->bodyClass();echo"'>\n",script((isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"onload = partial(verifyVersion, '".VERSION."');\n")."
const offlineMessage = '".js_escape('You are offline.')."';
const numberFormat = '".js_escape('#,##0')."';
const numberDigits = '".js_escape('0123456789')."';
const urlSeparators = '".js_escape(ini_get("arg_separator.input"))."';"),"<div id='help' class='jush-".JUSH." jsonly hidden'".on('mouseover','helpKeep').on('mouseout','helpMouseout')."></div>\n","<div id='content'>\n","<span id='menuopen' class='jsonly'".on('click','menuToggle')."><button title='".'Menu'."' class='icon icon-move' aria-expanded='false'></button></span>\n";if($Ra!==null){$z=substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1);echo'<p id="breadcrumb"><a href="'.h($z?:".").'">'.get_driver(DRIVER).'</a> » ';$z=substr(preg_replace('~\b(db|ns)=[^&]*&~','',ME),0,-1);$N=adminer()->serverName(SERVER);$N=($N!=""?$N:'Server');if($Ra===false)echo"$N\n";else{echo"<a href='".h($z.(DB!=""&&support("single_db")?"&db=":""))."' accesskey='1' title='Alt+Shift+1'>$N</a> » ";if($_GET["ns"]!=""||(DB!=""&&is_array($Ra)))echo'<a href="'.h($z."&db=".url_escape(DB).(support("scheme")?"&ns=":"").(support("single_table")?"&select=":"")).'">'.h(DB).'</a> » ';if(is_array($Ra)){if($_GET["ns"]!="")echo'<a href="'.h(substr(ME,0,-1)).'">'.h($_GET["ns"]).'</a> » ';foreach($Ra
as$x=>$X){$dc=(is_array($X)?$X[1]:h($X));if($dc!="")echo"<a href='".h(ME."$x=").url_escape(is_array($X)?$X[0]:$X)."'>$dc</a> » ";}}echo"$hk\n";}}echo"<h2>$jk</h2>\n","<div id='ajaxstatus' role='status' class='jsonly'></div>\n";restart_session();page_messages($k);adminer()->serviceWorker();$h=&get_session("dbs");if(DB!=""&&$h&&!in_array(DB,$h,true))$h=null;stop_session();define('Adminer\PAGE_HEADER',1);ob_flush();flush();}function
service_worker(){$ib=(has_passwords()?"navigator.serviceWorker.register('".js_escape(preg_replace('~\?.*~','',ME)."?file=worker.js&version=".VERSION)."', {scope: location.pathname}).catch(() => {});":"navigator.serviceWorker.getRegistration().then(registration => registration && registration.unregister());
	caches.keys().then(keys => keys.forEach(key => key.startsWith('adminer-') && caches.delete(key)));");echo
script("if (navigator.serviceWorker) {\n\t$ib\n}");}function
has_passwords(){foreach((array)$_SESSION["pwds"]as$aj){foreach($aj
as$Wk){foreach($Wk
as$F){if($F!==null)return
true;}}}return
false;}function
page_headers(){header("Content-Type: text/html; charset=utf-8");header("Cache-Control: no-cache");header("X-Frame-Options: deny");header("X-XSS-Protection: 0");header("X-Content-Type-Options: nosniff");header("Referrer-Policy: origin-when-cross-origin");foreach(adminer()->csp(csp())as$Ib){$de=array();foreach($Ib
as$x=>$X)$de[]="$x $X";header("Content-Security-Policy: ".implode("; ",$de));}adminer()->headers();}function
csp(){return
array(array("script-src"=>"'self' 'unsafe-inline' 'nonce-".get_nonce()."' 'strict-dynamic'","connect-src"=>"'self' https://www.adminer.org","frame-src"=>"https://www.adminer.org","object-src"=>"'none'","base-uri"=>"'none'","form-action"=>"'self'",),);}function
design_checksums(){$Vk=array();foreach(array_keys(adminer()->css())as$Pk)$Vk[preg_replace('~\?.*~','',$Pk)]=true;$J=array();foreach(array("adminer.css","adminer-dark.css")as$n){if($Vk[$n]&&file_exists($n)){preg_match('~^/\* Adminer design ([-\w]+) \*/~',file_get_contents($n),$A);$J[$n]=array((string)$A[1],Plugins::checksum($n));}}return$J;}function
official_design_checksums(){return
array('adminer-border/adminer.css'=>'ec757f3e','adminer-dark/adminer-dark.css'=>'a26bcd7b','brade/adminer.css'=>'be4161f0','bueltge/adminer.css'=>'1a8f00b4','cpanel/adminer.css'=>'59ce604e','dracula/adminer-dark.css'=>'cfaf61dd','esterka/adminer.css'=>'1f805f36','flat/adminer.css'=>'49a61af9','galkaev/adminer-dark.css'=>'16c46f94','haeckel/adminer.css'=>'147a3565','hever/adminer.css'=>'ef0e1948','konya/adminer.css'=>'2b409696','lavender-light/adminer.css'=>'bf03f5d7','lucas-sandery/adminer.css'=>'6596353','mancave/adminer-dark.css'=>'e1ac813d','mvt/adminer.css'=>'ebd3afdc','nette/adminer.css'=>'5ab360e7','ng9/adminer.css'=>'488583cf','nicu/adminer.css'=>'ecb9bd1e','pappu687/adminer.css'=>'b58d128c','paranoiq/adminer.css'=>'64d27e5','pepa-linha/adminer.css'=>'baf25f0','pokorny/adminer.css'=>'ee9eea6d','price/adminer.css'=>'81be9a85','rmsoft/adminer.css'=>'6cd4a237','rmsoft_blue-dark/adminer.css'=>'32102a8','rmsoft_blue/adminer.css'=>'7d8d5b18','win98/adminer.css'=>'e82d63c3',);}function
version_iframe(){return(isset($_COOKIE["adminer_version"])||!adminer()->verifyVersion()?"":"<noscript><iframe sandbox src='https://www.adminer.org/version/?current=".VERSION."&amp;noscript=1'></iframe></noscript>");}function
get_nonce(){static$_g;if(!$_g)$_g=base64_encode(rand_string());return$_g;}function
page_messages($k){$Ok=preg_replace('~^[^?]*~','',$_SERVER["REQUEST_URI"]);$bg=idx($_SESSION["messages"],$Ok);if($bg){echo"<div class='message'>".implode("</div>\n<div class='message'>",$bg)."</div>".script("messagesPrint();");unset($_SESSION["messages"][$Ok]);}if($k)echo"<div class='error'>$k</div>\n";if(adminer()->error)echo"<div class='error'>".adminer()->error."</div>\n";}function
page_footer($ig=""){echo"</div>\n\n<div id='foot' class='foot'>\n<div id='menu'>\n";adminer()->navigation($ig);echo"</div>\n";if($ig!="auth")echo'<form action="" method="post">
<p class="logout">
<span title="Username">',h($_GET["username"])."\n",'</span>
<input type=\'submit\' name=\'logout\' value=\'Logout\' id=\'logout\'>
',input_token(),'</form>
';echo"</div>\n\n",script("setupSubmitHighlight(document);");}function
int32($pg){while($pg>=2147483648)$pg-=4294967296;while($pg<=-2147483649)$pg+=4294967296;return(int)$pg;}function
long2str(array$W,$il){$Hi='';foreach($W
as$X)$Hi
.=pack('V',$X);if($il)return
substr($Hi,0,end($W));return$Hi;}function
str2long($Hi,$il){$W=array_values(unpack('V*',str_pad($Hi,4*ceil(strlen($Hi)/4),"\0")));if($il)$W[]=strlen($Hi);return$W;}function
xxtea_mx($sl,$rl,$Dj,$cf){return
int32((($sl>>5&0x7FFFFFF)^$rl<<2)+(($rl>>3&0x1FFFFFFF)^$sl<<4))^int32(($Dj^$rl)+($cf^$sl));}function
encrypt_string($zj,$x){if($zj=="")return"";$x=array_values(unpack("V*",pack("H*",md5($x))));$W=str2long($zj,true);$pg=count($W)-1;$sl=$W[$pg];$rl=$W[0];$fi=floor(6+52/($pg+1));$Dj=0;while($fi-->0){$Dj=int32($Dj+0x9E3779B9);$_c=$Dj>>2&3;for($mh=0;$mh<$pg;$mh++){$rl=$W[$mh+1];$og=xxtea_mx($sl,$rl,$Dj,$x[$mh&3^$_c]);$sl=int32($W[$mh]+$og);$W[$mh]=$sl;}$rl=$W[0];$og=xxtea_mx($sl,$rl,$Dj,$x[$mh&3^$_c]);$sl=int32($W[$pg]+$og);$W[$pg]=$sl;}return
long2str($W,false);}function
decrypt_string($zj,$x){if($zj=="")return"";if(!$x)return
false;$x=array_values(unpack("V*",pack("H*",md5($x))));$W=str2long($zj,false);$pg=count($W)-1;$sl=$W[$pg];$rl=$W[0];$fi=floor(6+52/($pg+1));$Dj=int32($fi*0x9E3779B9);while($Dj){$_c=$Dj>>2&3;for($mh=$pg;$mh>0;$mh--){$sl=$W[$mh-1];$og=xxtea_mx($sl,$rl,$Dj,$x[$mh&3^$_c]);$rl=int32($W[$mh]-$og);$W[$mh]=$rl;}$sl=$W[$pg];$og=xxtea_mx($sl,$rl,$Dj,$x[$mh&3^$_c]);$rl=int32($W[0]-$og);$W[0]=$rl;$Dj=int32($Dj-0x9E3779B9);}return
long2str($W,true);}$Gh=array();if($_COOKIE["adminer_permanent"]){foreach(explode(" ",$_COOKIE["adminer_permanent"])as$X){list($x)=explode(":",$X);$Gh[$x]=$X;}}function
add_invalid_login(){$Ia=get_temp_dir()."/adminer-invalid";foreach(glob("$Ia*")?:array($Ia)as$n){$p=file_open_lock($n);if($p)break;}if(!$p)$p=file_open_lock("$Ia-".rand_string());if(!$p)return;$Pe=json_decode(stream_get_contents($p),true);$ek=time();if($Pe){foreach($Pe
as$Qe=>$X){if($X[0]<$ek)unset($Pe[$Qe]);}}$Ne=&$Pe[adminer()->bruteForceKey()];if(!$Ne)$Ne=array($ek+30*60,0);$Ne[1]++;file_write_unlock($p,json_encode($Pe));}function
check_invalid_login(array&$Gh){$Pe=array();foreach(glob(get_temp_dir()."/adminer-invalid*")as$n){$p=file_open_lock($n);if($p){$Pe=json_decode(stream_get_contents($p),true);file_unlock($p);break;}}$x=adminer()->bruteForceKey();$Ne=idx($Pe,$x,array());$zg=($Ne[1]>29?$Ne[0]-time():0);if($zg>0){$k=lang_format(array('Too many unsuccessful logins, try again in %d minute.','Too many unsuccessful logins, try again in %d minutes.'),ceil($zg/60));if($_SERVER["HTTP_X_FORWARDED_FOR"]!=""&&$x==$_SERVER["REMOTE_ADDR"])$k
.='<br>'.sprintf('Use the %s <a%s>plugin</a> if Adminer runs behind a reverse proxy.','<b>login-reverse-proxy</b>'," href='https://www.adminer.org/plugins/?version=".VERSION."'".target_blank());auth_error($k,$Gh,false);}}function
password_required(){static$J;if($J===null){$J=(bool)get_session("password_required");if(!$J){$Hb=adminer()->credentials();$J=!is_object(Driver::connect($Hb[0],$Hb[1],""));if($J)set_session("password_required",true);}}return$J;}function
require_password_link($F){$lg="<a href='https://www.adminer.org/password/'".target_blank().">".'More options'."</a>";if(!function_exists('password_hash'))return" $lg";$Jh=($F!==null?$F:base64_encode(substr(pack("H*",rand_string()),0,12)));$ce=password_hash($Jh,PASSWORD_DEFAULT);$n="adminer-plugins.php";$Xc=file_exists("adminer-plugins.php");if($Xc)$Le=($F!==null?sprintf('Add this line to %s to require the entered password:',"<b>$n</b>"):sprintf('Add this line to %s to require the password %s:',"<b>$n</b>","<b>$Jh</b>"));else{$n="<button name='password_less' value='".h($ce)."' class='link'>$n</button>";$Le=($F!==null?sprintf('Save %s next to Adminer to require the entered password:',$n):sprintf('Save %s next to Adminer to require the password %s:',$n,"<b>$Jh</b>"));}$wf="\t<a>new</a> Adminer\\Password(<span class='jush-apo'>'".h($ce)."'</span>),";$J="<p>$Le
<pre><code class='jush'>".($Xc?$wf:"&lt;?php\n<a>return</a> <a>array</a>(\n$wf\n);")."</code></pre>
<p>$lg
";return" <a href='#password-less' class='toggle'>".'Require a password.'."</a>
<div id='password-less' class='hidden'>".($Xc?$J:"<form action='' method='post'>\n".$J.input_token()."</form>")."</div>";}if(preg_match('~^[-\w$./]+$~',$_POST["password_less"])&&verify_token()){header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=adminer-plugins.php");echo"<?php\nreturn array(\n\tnew Adminer\\Password('$_POST[password_less]'),\n);\n";exit;}$_a=$_POST["auth"];if($_a&&verify_token()){session_regenerate_id();$dl=$_a["driver"];$N=$_a["server"];$V=$_a["username"];$F=(string)$_a["password"];$i=$_a["db"];set_password($dl,$N,$V,$F);$_SESSION["db"][$dl][$N][$V][$i]=true;if($_a["permanent"]){$x=implode("-",array_map('base64_encode',array($dl,$N,$V,$i)));$Zh=adminer()->permanentLogin(true);$Gh[$x]="$x:".base64_encode($Zh?encrypt_string($F,$Zh):"");cookie("adminer_permanent",implode(" ",$Gh));}if(!array_diff(array_keys($_POST),array("auth","token"))||$dl!=DRIVER||$N!=SERVER||$V!==$_GET["username"]||$i!=DB)redirect(auth_url($dl,$N,$V,$i));}elseif($_POST["logout"]&&(!$_SESSION["token"]||verify_token())){foreach(array("pwds","db","dbs","queries")as$x)set_session($x,null);unset_permanent($Gh);redirect(substr(preg_replace('~\b(username|db|ns)=[^&]*&~','',ME),0,-1),'Logout successful.'.' '.'Thanks for using Adminer. Consider <a href="https://www.adminer.org/en/donation/">donating</a>.');}elseif($Gh&&!$_SESSION["pwds"]){session_regenerate_id();$Zh=adminer()->permanentLogin();foreach($Gh
as$x=>$X){list(,$gb)=explode(":",$X);list($dl,$N,$V,$i)=array_map('base64_decode',explode("-",$x));set_password($dl,$N,$V,decrypt_string(base64_decode($gb),$Zh));$_SESSION["db"][$dl][$N][$V][$i]=true;}}function
unset_permanent(array&$Gh){foreach($Gh
as$x=>$X){list($dl,$N,$V,$i)=array_map('base64_decode',explode("-",$x));if($dl==DRIVER&&$N==SERVER&&$V==$_GET["username"]&&$i==DB)unset($Gh[$x]);}cookie("adminer_permanent",implode(" ",$Gh));}function
auth_error($k,array&$Gh,$Oe=true){$bj=session_name();if(isset($_GET["username"])){header("HTTP/1.1 403 Forbidden");if(($_COOKIE[$bj]||$_GET[$bj])&&!$_SESSION["token"])$k='Session expired. Please log in again.';elseif($Oe&&($F=get_password())!==null){restart_session();add_invalid_login();if($F===false)$k
.=($k?'<br>':'').sprintf('Master password expired. <a href="https://www.adminer.org/en/extension/"%s>Implement</a> the %s method to make it permanent.',target_blank(),'<code>permanentLogin()</code>');set_password(DRIVER,SERVER,$_GET["username"],null);unset_permanent($Gh);}}if(!$_COOKIE[$bj]&&$_GET[$bj]&&ini_bool("session.use_only_cookies"))$k='Session support must be enabled.';$ph=session_get_cookie_params();cookie("adminer_key",($_COOKIE["adminer_key"]?:rand_string()),$ph["lifetime"]);if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);page_header('Login',$k,null);echo"<form action='' method='post'>\n","<div>";if(hidden_fields($_POST,array("auth","token")))echo"<p class='message'>".'The action will be performed after successful login with the same credentials.'."\n";echo
input_token(),"</div>\n";adminer()->loginForm();echo"</form>\n";page_footer("auth");exit;}if(isset($_GET["username"])&&!class_exists('Adminer\Db')){unset($_SESSION["pwds"][DRIVER]);unset_permanent($Gh);page_header('No extension',sprintf('None of the supported PHP extensions (%s) are available.',implode(", ",Driver::$extensions)),false);page_footer("auth");exit;}$e='';if(isset($_GET["username"])&&is_string(get_password())){check_invalid_login($Gh);$Hb=adminer()->credentials();$e=Driver::connect($Hb[0],$Hb[1],$Hb[2]);if(is_object($e)){Db::$instance=$e;Driver::$instance=new
Driver($e);if($e->flavor)save_settings(array("vendor-".DRIVER."-".SERVER=>get_driver(DRIVER)));}}$Bf=null;if(!is_object($e)||($Bf=adminer()->login($_GET["username"],get_password()))!==true){$k=(is_string($e)?nl_br(h($e)):(is_string($Bf)?$Bf:'Invalid credentials.')).(preg_match('~^ | $~',get_password())?'<br>'.'There is a space in the entered password, which might be the cause.':'');auth_error($k,$Gh);}if($_POST["logout"]&&$_SESSION["token"]&&!verify_token()){page_header('Logout','Invalid CSRF token. Submit the form again.');page_footer("db");exit;}if(!$_SESSION["token"])$_SESSION["token"]=rand(1,1e6);stop_session(true);if($_a&&$_POST["token"])$_POST["token"]=get_token();$k='';if($_POST){if(!verify_token()){header("HTTP/1.1 403 Forbidden");$k='Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.';}}elseif($_SERVER["REQUEST_METHOD"]=="POST"){header("HTTP/1.1 413 Content Too Large");$k=sprintf('The POST data is too large. Reduce the data or increase the %s configuration directive.',"<b>post_max_size</b>");if(isset($_GET["sql"]))$k
.=' '.'You can upload a large SQL file via FTP and import it from the server.';}function
print_select_result($I,$f=null,array$ch=array(),&$y=0){$yf=array();$w=array();$d=array();$Oa=array();$Ck=array();$J=array();for($s=0;(!$y||$s<$y)&&($K=$I->fetch_row());$s++){if(!$s){echo"<div class='scrollable'>\n","<table class='nowrap odds'>\n","<thead><tr>";for($Ze=0;$Ze<count($K);$Ze++){$l=$I->fetch_field();$B=$l->name;$bh=(isset($l->orgtable)?$l->orgtable:"");$ah=(isset($l->orgname)?$l->orgname:$B);if($ch&&JUSH=="sql")$yf[$Ze]=($B=="table"?"table=":($B=="possible_keys"?"indexes=":null));elseif($bh!=""){if(isset($l->table))$J[$l->table]=$bh;if(!isset($w[$bh])){$w[$bh]=array();foreach(indexes($bh,$f)as$v){if($v["type"]=="PRIMARY"){$w[$bh]=array_flip($v["columns"]);break;}}$d[$bh]=$w[$bh];}if(isset($d[$bh][$ah])){unset($d[$bh][$ah]);$w[$bh][$ah]=$Ze;$yf[$Ze]=$bh;}}if($l->charsetnr==63)$Oa[$Ze]=true;$Ck[$Ze]=$l->type;echo"<th title='".h(trim(($bh!=""?"$bh.$ah":($l->name!=$ah?$ah:""))." ".driver()->typeName($l)))."'>".h($B).($ch?doc_link(array('sql'=>"explain-output.html#explain_".strtolower($B),'mariadb'=>"explain/#the-columns-in-explain-select",)):"");}echo"<tbody>\n";}echo"<tr>";foreach($K
as$x=>$X){$z="";if(isset($yf[$x])&&!$d[$yf[$x]]){if($ch&&JUSH=="sql"){$R=$K[array_search("table=",$yf)];$z=ME.$yf[$x].url_escape($ch[$R]!=""?$ch[$R]:$R);}else{$z=ME."edit=".url_escape($yf[$x]);foreach($w[$yf[$x]]as$jb=>$Ze){if($K[$Ze]===null){$z="";break;}$z
.="&where[".url_escape(bracket_escape($jb))."]=".url_escape($K[$Ze]);}}}$l=array('type'=>($Oa[$x]?'blob':($Ck[$x]==254?'char':'')),);$X=select_value($X,$z,$l,null);echo"<td".($Ck[$x]<=9||$Ck[$x]==246?" class='number'":"").">$X";}}$y=$s;echo($s?"</table>\n</div>":"<p class='message'>".'No rows.')."\n";return$J;}function
textarea($B,$Y,$L=10,$nb=80,$bf=JUSH){echo"<textarea name='".h($B)."' rows='$L' cols='$nb' class='sqlarea jush-".h($bf)."' spellcheck='false' wrap='off'>";if(is_array($Y)){foreach($Y
as$X)echo
h($X[0])."\n\n\n";}else
echo
h($Y);echo"</textarea>";}function
select_input($b,array$C,$Y="",$Hh=""){if($C&&$Y!=""&&!isset($C[$Y]))$C=array($Y=>$Y)+$C;$Uj=($C?"select":"input");return"<$Uj$b".($C?"><option value=''>$Hh".optionlist($C,$Y,true)."</select>":" size='10' value='".h($Y)."' placeholder='$Hh'>");}function
json_row($x,$X=null,$Pc=true){static$td=true;if($td)echo"{";if($x!=""){echo($td?"":",")."\n\t\"".addcslashes($x,"\r\n\t\"\\/").'": '.($X!==null?($Pc?'"'.addcslashes($X,"\r\n\"\\/").'"':$X):'null');$td=false;}else{echo"\n}\n";$td=true;}}function
flat_collations(){$mb=collations();return(is_array(reset($mb))?call_user_func_array('array_merge',array_values($mb)):$mb);}function
edit_type($x,array$l,array$mb,array$_d=array(),array$fd=array()){$U=(string)$l["type"];echo"<td><select name='".h($x)."[type]' class='type' aria-labelledby='label-type'".on_help_value().">";if($U&&!array_key_exists($U,driver()->types())&&!isset($_d[$U])&&!in_array($U,$fd))$fd[]=$U;$_j=driver()->structuredTypes();if($_d)$_j['Foreign keys']=$_d;echo
optionlist(array_merge($fd,$_j),$U),"</select><td>","<input name='".h($x)."[length]' value='".h($l["length"])."' size='3'".(!$l["length"]&&preg_match('~var(char|binary)$~',$U)?" class='required'":"")." aria-labelledby='label-length'>","<td class='options'>",($mb?"<input list='collations' name='".h($x)."[collation]'".option_types($U,'('.text_type().')$')." value='".h($l["collation"])."' placeholder='(".'collation'.")'>":''),(driver()->unsigned?"<select name='".h($x)."[unsigned]'".option_types($U,'^$|'.number_type()).'><option>'.optionlist(driver()->unsigned,$l["unsigned"]).'</select>':''),(isset($l['on_update'])?"<select name='".h($x)."[on_update]'".option_types($U,'timestamp|datetime').'>'.optionlist(array(""=>"(".'ON UPDATE'.")","CURRENT_TIMESTAMP"),(preg_match('~^CURRENT_TIMESTAMP~i',$l["on_update"])?"CURRENT_TIMESTAMP":$l["on_update"])).'</select>':''),($_d?"<select name='".h($x)."[on_delete]'".option_types($U,'`')."><option value=''>(".'ON DELETE'.")".optionlist(explode("|",driver()->onActions),$l["on_delete"])."</select> ":" ");}function
option_types($U,$Ck){return" data-types='".h($Ck)."'".(preg_match("~$Ck~",$U)?"":" class='hidden'");}function
process_length($rf){$Kc=driver()->enumLength;return(preg_match("~^\\s*\\(?\\s*$Kc(?:\\s*,\\s*$Kc)*+\\s*\\)?\\s*\$~",$rf)&&preg_match_all("~$Kc~",$rf,$Ff)?"(".implode(",",$Ff[0]).")":preg_replace('~^[0-9].*~','(\0)',preg_replace('~[^-0-9,+()[\]]~','',$rf)));}function
process_in($X){$Kc=driver()->enumLength;if(preg_match("~^\\s*\\(?\\s*$Kc(?:\\s*,\\s*$Kc)*+\\s*\\)?\\s*\$~",$X)&&preg_match_all("~$Kc~",$X,$Ff))return"(".implode(", ",$Ff[0]).")";$J=array();foreach(explode(",",$X)as$Ye)$J[]=q(trim($Ye));return"(".implode(", ",$J).")";}function
process_type(array$l,$kb="COLLATE"){return" $l[type]".process_length($l["length"]).(preg_match(number_type(),$l["type"])&&in_array($l["unsigned"],driver()->unsigned)?" $l[unsigned]":"").(preg_match('~'.text_type().'~',$l["type"])&&$l["collation"]?" $kb ".(JUSH=="mssql"?$l["collation"]:q($l["collation"])):"");}function
process_field(array$l,array$_k){if($l["on_update"])$l["on_update"]=str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",$l["on_update"]);return
array(idf_escape(trim($l["field"])),process_type($_k),($l["null"]?" NULL":" NOT NULL"),default_value($l),(preg_match('~timestamp|datetime~',$l["type"])&&$l["on_update"]?" ON UPDATE $l[on_update]":""),(support("comment")&&$l["comment"]!=""?" COMMENT ".q($l["comment"]):""),($l["auto_increment"]?auto_increment():null),);}function
default_value(array$l){if($l["default"]===null)return"";$j=str_replace("\r","",$l["default"]);$Kd=$l["generated"];return(in_array($Kd,driver()->generated)?(JUSH=="mssql"?" AS ($j)".($Kd=="VIRTUAL"?"":" $Kd"):" GENERATED ALWAYS AS ($j) $Kd"):(preg_match('~^GENERATED ~i',$j)?" $j":" DEFAULT ".(preg_match('~char|binary|text|json|enum|set|String~',$l["type"])||preg_match('~^(?![a-z])~i',$j)?(JUSH=="sql"&&preg_match('~text|json~',$l["type"])?"(".q($j).")":q($j)):str_ireplace("current_timestamp()","CURRENT_TIMESTAMP",(JUSH=="sqlite"?"($j)":$j)))));}function
edit_fields(array$m,array$mb,$U="TABLE",array$_d=array()){$m=array_values($m);$Xb=(($_POST?$_POST["defaults"]:get_setting("defaults"))?"":" class='hidden'");$rb=(($_POST?$_POST["comments"]:get_setting("comments"))?"":" class='hidden'");echo"<thead><tr>\n",($U=="PROCEDURE"?"<td>":""),"<th id='label-name'>".($U=="TABLE"?'Column name':'Parameter name'),"<td id='label-type'>".'Type'."<textarea id='enum-edit' rows='4' cols='12' wrap='off' hidden></textarea>".script("qs('#enum-edit').onblur = editingLengthBlur;"),"<td id='label-length'>".'Length',"<td>".'Options';if($U=="TABLE")echo"<td id='label-null'>NULL\n","<td><input type='radio' name='auto_increment_col' value=''><abbr id='label-ai' title='".'Auto Increment'."'>AI</abbr>",doc_link(array('sql'=>"example-auto-increment.html",'mariadb'=>"auto_increment/",)),"<td id='label-default'$Xb>".'Default value',(support("comment")?"<td id='label-comment'$rb>".'Comment':"");$lf=!support("move_col");echo"<td>".icon("plus","add[".($lf?count($m):0)."]","+",'Add next',($lf?on('click','editingAddLastRow'):"")),"<tbody".on('click','editingClick').on('input','editingInput').on('keydown','editingKeydown').">\n";foreach($m
as$s=>$l){$s++;$dh=$l[($_POST?"orig":"field")];$kc=(isset($_POST["add"][$s-1])||(isset($l["field"])&&!idx($_POST["drop_col"],$s)))&&(support("drop_col")||$dh=="");echo"<tr".($kc?"":" hidden").">\n",($U=="PROCEDURE"?"<td>".html_select("fields[$s][inout]",explode("|",driver()->inout),$l["inout"]):"")."<th>",(support("move_col")?icon("move","","↕",'Move')." ":"");if($kc)echo"<input name='fields[$s][field]' value='".h($l["field"])."' data-maxlength='64' autocapitalize='off' aria-labelledby='label-name'".(isset($_POST["add"][$s-1])?" autofocus":"").">";echo
input_hidden("fields[$s][orig]",$dh);edit_type("fields[$s]",$l,$mb,$_d);if($U=="TABLE"){echo"<td><label class='block'>".checkbox("fields[$s][null]",1,$l["null"],"","","","label-null")."</label>","<td><label class='block'><input type='radio' name='auto_increment_col' value='$s'".($l["auto_increment"]?" checked":"")." aria-labelledby='label-ai'></label>","<td$Xb>".(driver()->generated?html_select("fields[$s][generated]",array_merge(array("","DEFAULT"),driver()->generated),$l["generated"])." ":checkbox("fields[$s][generated]",1,$l["generated"],"","","","label-default"));$b=" name='fields[$s][default]' aria-labelledby='label-default'";$Y=h($l["default"]);echo(preg_match('~\n~',$l["default"])?"<textarea$b rows='2' cols='30' style='vertical-align: bottom;'>\n$Y</textarea>":"<input$b value='$Y'>");if(support("comment")){$b=" name='fields[$s][comment]' data-maxlength='".(min_version(5.5)?1024:255)."' aria-labelledby='label-comment'";echo"<td$rb>".adminer()->commentInput('COLUMN',$b,$l["comment"]);}}echo"<td>",(support("move_col")?icon("plus","add[$s]","+",'Add next')." ":""),($dh==""||support("drop_col")?icon("cross","drop_col[$s]","x",'Remove'):"");}}function
process_fields(array&$m){if($_POST["add"]){$m=array_values($m);array_splice($m,key($_POST["add"]),0,array(array()));}return$_POST["add"]||$_POST["drop_col"];}function
drop_create($wc,$g,$xc,$ak,$yc,$_,$ag,$Yf,$Zf,$Mg,$wg){if($_POST["drop"])query_redirect($wc,$_,$ag);elseif($Mg=="")query_redirect($g,$_,$Zf);elseif(support("transaction_ddl")){driver()->begin();queries_redirect($_,$Yf,queries($wc)&&queries($g)&&driver()->commit());driver()->rollback();}elseif($Mg!=$wg){$Gb=queries($g);queries_redirect($_,$Yf,$Gb&&queries($wc));if($Gb&&$xc)queries($xc);}else
queries_redirect($_,$Yf,queries($ak)&&queries($yc)&&queries($wc)&&queries($g));}function
create_trigger($Og,array$K){$gk=" $K[Timing] $K[Event]".(preg_match('~ OF~',$K["Event"])?" $K[Of]":"");return"CREATE TRIGGER ".idf_escape($K["Trigger"]).(JUSH=="mssql"?$Og.$gk:$gk.$Og).rtrim(" $K[Type]\n$K[Statement]",";").";";}function
q_dollar($Q){$cc='$$';while(strpos($Q.$cc,$cc)!=strlen($Q))$cc='$_'.substr($cc,1);return$cc.$Q.$cc;}function
routine_collate($lb){static$Za=array();if($lb&&!$Za){foreach(collations()as$Ya=>$al){foreach((array)$al
as$X)$Za[$X]=$Ya;}}return($Za[$lb]?"CHARACTER SET ".q($Za[$lb])." ":"")."COLLATE";}function
create_routine($Ci,array$K){$O=array();$m=(array)$K["fields"];ksort($m);foreach($m
as$l){if($l["field"]!="")$O[]="\n  ".(preg_match("~^(".driver()->inout.")\$~",$l["inout"])?"$l[inout] ":"").idf_escape($l["field"]).process_type($l,routine_collate($l["collation"]));}$Zb="";$C=array();foreach(routine_options($Ci)as$x=>$bl){$Y=idx((array)$K["options"],$x,"");if($x=="DEFINER")$Zb=($Y?" $x=".implode("@",array_map('Adminer\q',explode("@",$Y,2))):"");elseif(!$bl){if($Y!="")$C[]="$x ".q($Y);}elseif($Y!=reset($bl)&&in_array($Y,$bl))$C[]=$Y;}$jf=$K["language"];$ac=rtrim($K["definition"],";");$sc=(JUSH=="pgsql"||($jf&&$jf!="sql"));return"CREATE$Zb $Ci ".idf_escape(trim($K["name"]))." (".($O?implode(",",$O)."\n":"").")".($Ci=="FUNCTION"?"\nRETURNS".process_type($K["returns"],routine_collate($K["returns"]["collation"])):"").($jf?" LANGUAGE $jf":"").($C?"\n".implode(" ",$C):"").($sc?" AS ".q_dollar("\n".trim($ac)."\n"):"\n$ac;");}function
remove_definer($H){return
preg_replace('~^([A-Z =]+) DEFINER=`'.preg_replace('~@(.*)~','`@`(%|\1)',logged_user()).'`~','\1',$H);}function
format_foreign_key(array$o){$i=$o["db"];$Ag=$o["ns"];return" FOREIGN KEY (".implode(", ",array_map('Adminer\idf_escape',$o["source"])).") REFERENCES ".($i!=""&&$i!=$_GET["db"]?idf_escape($i).".":"").($Ag!=""&&$Ag!=$_GET["ns"]?idf_escape($Ag).".":"").idf_escape($o["table"])." (".implode(", ",array_map('Adminer\idf_escape',$o["target"])).")".(preg_match("~^(".driver()->onActions.")\$~",$o["on_delete"])?" ON DELETE $o[on_delete]":"").(preg_match("~^(".driver()->onActions.")\$~",$o["on_update"])?" ON UPDATE $o[on_update]":"").($o["deferrable"]?" $o[deferrable]":"");}function
tar_file($n,$lk){$J=pack("a100a8a8a8a12a12",$n,644,0,0,decoct($lk->size),decoct(time()));$eb=8*32;for($s=0;$s<strlen($J);$s++)$eb+=ord($J[$s]);$J
.=sprintf("%06o",$eb)."\0 ";echo$J,str_repeat("\0",512-strlen($J));$lk->send();echo
str_repeat("\0",511-($lk->size+511)%512);}function
doc_version(){$Zi=connection()->server_info;if(JUSH=='oracle'){preg_match('~(?:.* |^)(\d+)\.\d+\.\d+\.\d+\.\d+~s',$Zi,$A);return($A[1]>=18?$A[1]:"19");}$si=(JUSH=='sql'?'~^\d+\.\d+~':'~^\d\.?\d~');$el=(preg_match($si,$Zi,$A)?$A[0]:"");if(JUSH=='mssql')return($el>=15?"sql-server-ver$el":($el==12?"azuresqldb-current":"sql-server-2017"));return$el;}function
doc_link(array$Dh,$bk="<sup>?</sup>"){$el=doc_version();$Qk=array('sql'=>"https://dev.mysql.com/doc/refman/$el/en/",'sqlite'=>"https://www.sqlite.org/",'pgsql'=>"https://www.postgresql.org/docs/".(connection()->flavor=='cockroach'?"current":$el)."/",'mssql'=>"https://learn.microsoft.com/en-us/sql/",'oracle'=>"https://docs.oracle.com/en/database/oracle/oracle-database/$el/",);if(connection()->flavor=='maria'){$Qk['sql']="https://mariadb.com/kb/en/";$Dh['sql']=(isset($Dh['mariadb'])?$Dh['mariadb']:str_replace(".html","/",$Dh['sql']));}return($Dh[JUSH]?"<a href='".h($Qk[JUSH].$Dh[JUSH].(JUSH=='mssql'?"?view=$el":""))."'".target_blank().">$bk</a>":"");}function
db_size($i){if(!connection()->select_db($i))return"?";$J=0;foreach(table_status()as$S)$J+=$S["Data_length"]+$S["Index_length"];return
format_number($J);}function
set_utf8mb4($g){static$O=false;if(!$O&&preg_match('~\butf8mb4~i',$g)){$O=true;echo"SET NAMES ".charset(connection()).";\n\n";}}if(isset($_GET["status"]))$_GET["variables"]=$_GET["status"];if(isset($_GET["import"]))$_GET["sql"]=$_GET["import"];if(DB==""&&isset($_GET["ns"]))redirect(remove_from_uri('ns'));if(!(DB!=""?connection()->select_db(DB):isset($_GET["sql"])||isset($_GET["dump"])||isset($_GET["database"])||isset($_GET["processlist"])||isset($_GET["privileges"])||isset($_GET["user"])||isset($_GET["variables"])||$_GET["script"]=="connect"||$_GET["script"]=="kill")){if(DB!=""||$_GET["refresh"]){restart_session();set_session("dbs",null);}if(DB!=""){header("HTTP/1.1 404 Not Found");page_header('Database'.": ".h(DB),'Invalid database.',true);}else{if(!isset($_GET["db"])&&support("single_db")){$h=adminer()->databases();if($h)redirect(ME."db=".url_escape($h[0]));}if($_POST["db"]&&!$k)queries_redirect(substr(ME,0,-1),'Databases have been dropped.',drop_databases($_POST["db"]));page_header('Select database',$k,false);echo"<p class='links'>\n";foreach(array('database'=>'Create database','privileges'=>'Privileges','processlist'=>'Process list','variables'=>'Variables','status'=>'Status',)as$x=>$X){if(support($x))echo"<a href='".h(ME)."$x='>$X</a>\n";}echo"<p>".sprintf('%s version: %s through PHP extension %s',get_driver(DRIVER),"<b>".h(connection()->server_info)."</b>","<b>".connection()->extension."</b>")."\n","<p>".sprintf('Logged in as: %s',"<b>".h(logged_user())."</b>")."\n";$h=adminer()->databases();if($h){$Ki=support("scheme");$mb=collations();echo"<form action='' method='post'>\n","<table class='checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n","<thead><tr>".(support("database")?"<td class='hover'>":"")."<th".(JUSH!='mssql'?" aria-sort='ascending'":"").">".'Database'.(get_session("dbs")!==null?" - <a href='".h(ME)."refresh=1'>".'Refresh'."</a>":"")."<td>".'Collation'."<td>".'Tables'."<td>".'Size'." - <a href='".h(ME)."dbsize=1'".on('click','ajaxSetHtml',ME."script=connect").">".'Compute'."</a>"."<tbody>\n";$h=($_GET["dbsize"]?count_tables($h):array_flip($h));foreach($h
as$i=>$T){$Bi=h(preg_replace('~&db=[^&]*~','',ME))."db=".url_escape($i);$t=h("Db-".$i);echo"<tr>".(support("database")?"<td class='hover'>".checkbox("db[]",$i,in_array($i,(array)$_POST["db"]),"","","",$t):""),"<th><a href='$Bi' id='$t'>".h($i)."</a>";$lb=h(db_collation($i,$mb));echo"<td>".(support("database")?"<a href='$Bi".($Ki?"&amp;ns=":"")."&amp;database=' title='".'Alter database'."'>$lb</a>":$lb),"<td align='right'><a href='$Bi&amp;schema=' id='tables-".h($i)."' title='".'Database schema'."'>".($_GET["dbsize"]?format_number($T):"?")."</a>","<td align='right' id='size-".h($i)."'>".($_GET["dbsize"]?db_size($i):"?"),"\n";}echo"</table>\n",(support("database")?"<div class='footer'><div>\n"."<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>\n"."<input type='hidden' name='all' value=''".on('click','countDbs').">\n"."<input type='submit' name='drop' value='".'Drop'."'".confirm().">\n"."</div></fieldset>\n"."</div></div>\n":""),input_token(),"</form>\n",script("tableCheck();");}$ia=adminer();$Lh=($ia
instanceof
Plugins?$ia->plugins:array());$vc=($ia
instanceof
Plugins?$ia->drivers:array());$hc=design_checksums();if($Lh||$vc||$hc){$fb=($ia
instanceof
Plugins?$ia->checksums():array());$Fg=Plugins::officialChecksums();$Mk=function($Pk){return" (<a href='$Pk'".target_blank()." class='update'>".VERSION."</a>)";};$Kh=function($nd)use($fb,$Fg,$Mk){return($fb[$nd]&&$Fg[$nd]&&$fb[$nd]!==$Fg[$nd]?$Mk("https://www.adminer.org/plugins/?version=".VERSION):"");};echo"<div class='plugins'>\n","<h3>".'Loaded plugins'."</h3>\n<ul>\n";foreach($Lh
as$Ih){$qi=new
\ReflectionObject($Ih);$ec=(method_exists($Ih,'description')?$Ih->description():"");if(!$ec){if(preg_match('~^/[\s*]+(.+)~',$qi->getDocComment(),$A))$ec=$A[1];}$Li=(method_exists($Ih,'screenshot')?$Ih->screenshot():"");echo"<li><b>".get_class($Ih)."</b>".h($ec?": $ec":"").($Li?" (<a href='".h($Li)."'".target_blank().">".'screenshot'."</a>)":"").$Kh(basename((string)$qi->getFileName(),'.php'))."\n";}foreach($vc
as$t=>$B)echo"<li><b>".h($t)."</b>: ".h($B).$Kh(basename((string)$ia->driverFiles[$t],'.php'))."\n";if($hc){$Hg=official_design_checksums();foreach($hc
as$n=>$gc){list($B,$eb)=$gc;$Gg=$Hg["$B/$n"];echo"<li><b>".h($n)."</b>".h($B?": $B":"").($Gg&&$Gg!==$eb?$Mk("https://www.adminer.org/?version=".VERSION."#extras"):"")."\n";}}echo"</ul>\n";adminer()->pluginsLinks();echo"</div>\n";}}page_footer("db");exit;}adminer()->afterConnect();class
TmpFile{private$handler;var$size=0;function
__construct(){$this->handler=tmpfile();}function
write($zb){$this->size+=strlen($zb);fwrite($this->handler,$zb);}function
send(){fseek($this->handler,0);fpassthru($this->handler);fclose($this->handler);}}if($_GET["select"]!=""&&($_POST["edit"]||$_POST["clone"])&&!$_POST["save"])$_GET["edit"]=$_GET["select"];if(isset($_GET["callf"]))$_GET["call"]=$_GET["callf"];if(isset($_GET["function"]))$_GET["procedure"]=$_GET["function"];if(isset($_GET["download"])){$a=$_GET["download"];$m=fields($a);header("Content-Type: application/octet-stream");header("Content-Disposition: attachment; filename=".friendly_url("$a-".implode("_",$_GET["where"])).".".friendly_url($_GET["field"]));$M=array(idf_escape($_GET["field"]));$I=driver()->select($a,$M,array(where($_GET,$m)),$M);$K=($I?$I->fetch_row():array());echo
driver()->value($K[0],$m[$_GET["field"]]);exit;}elseif(isset($_GET["table"])){$a=$_GET["table"];$m=fields($a);if(!$m)$k=adminer()->error()?:'No tables.';$S=table_status1($a);$B=adminer()->tableName($S);$k=$k?:h($S["Error"]);page_header(($m&&is_view($S)?$S['Engine']=='materialized view'?'Materialized view':'View':'Table').": ".($B!=""?$B:h($a)),$k);$Ai=array();foreach($m
as$x=>$l)$Ai+=$l["privileges"];adminer()->selectLinks($S,(isset($Ai["insert"])||!support("table")?"":null));$qb=$S["Comment"];if($qb!="")echo"<p class='nowrap'>".'Comment'.": ".adminer()->commentValue('TABLE',$qb)."\n";if($m)adminer()->tableStructurePrint($m,$S);function
tables_links(array$T){echo"<ul>\n";foreach($T
as$K){$z=preg_replace('~ns=[^&]*~',"ns=".url_escape($K["ns"]),ME);echo"<li><a href='".h($z."table=".url_escape($K["table"]))."'>".($K["ns"]!=$_GET["ns"]?"<b>".h($K["ns"])."</b>.":"").h($K["table"])."</a>";}echo"</ul>\n";}$Ee=driver()->inheritsFrom($a);if($Ee){echo"<h3>".'Inherits from'."</h3>\n";tables_links($Ee);}if(support("indexes")&&driver()->supportsIndex($S)){echo"<div>\n","<h3 id='indexes'>".'Indexes'."</h3>\n";$w=indexes($a);if($w)adminer()->tableIndexesPrint($w,$S);if(driver()->supportsAlterIndex($S))echo'<p class="links hover"><a href="'.h(ME).'indexes='.url_escape($a).'">'.'Alter indexes'."</a>\n";echo"</div>\n";}if(!is_view($S)&&driver()->supportsAlterTable($S)){if(fk_support($S)){echo"<div>\n","<h3 id='foreign-keys'>".'Foreign keys'."</h3>\n";$_d=foreign_keys($a);if($_d){echo"<table>\n","<thead><tr><th>".'Source'."<td>".'Target'."<td>".'ON DELETE'."<td>".'ON UPDATE'."<td class='hover'><tbody>\n";foreach($_d
as$B=>$o){echo"<tr title='".h($B)."'>","<th><i>".implode("</i>, <i>",array_map('Adminer\h',$o["source"]))."</i>";$z=($o["db"]!=""?preg_replace('~db=[^&]*~',"db=".url_escape($o["db"]),ME):($o["ns"]!=""?preg_replace('~ns=[^&]*~',"ns=".url_escape($o["ns"]),ME):ME));echo"<td><a href='".h($z."table=".url_escape($o["table"]))."'>".($o["db"]!=""&&$o["db"]!=DB?"<b>".h($o["db"])."</b>.":"").($o["ns"]!=""&&$o["ns"]!=$_GET["ns"]?"<b>".h($o["ns"])."</b>.":"").h($o["table"])."</a>","(<i>".implode("</i>, <i>",array_map('Adminer\h',$o["target"]))."</i>)","<td>".h($o["on_delete"]),"<td>".h($o["on_update"]),'<td class="hover"><a href="'.h(ME.'foreign='.url_escape($a).'&name='.url_escape($B)).'">'.'Alter'.'</a>',"\n";}echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'foreign='.url_escape($a).'">'.'Create foreign key'."</a>\n","</div>\n";}if(support("check")){echo"<div>\n","<h3 id='checks'>".'Checks'."</h3>\n";$bb=driver()->checkConstraints($a);if($bb){echo"<table>\n";foreach($bb
as$x=>$X)echo"<tr title='".h($x)."'>","<td><code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim($X)),80,"</code>"),"<td class='hover'><a href='".h(ME.'check='.url_escape($a).'&name='.url_escape($x))."'>".'Alter'."</a>","\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'check='.url_escape($a).'">'.'Create check'."</a>\n","</div>\n";}}if(support(is_view($S)?"view_trigger":"trigger")&&driver()->supportsAlterTable($S)){echo"<div>\n","<h3 id='triggers'>".'Triggers'."</h3>\n";$xk=triggers($a);if($xk){echo"<table>\n";foreach($xk
as$x=>$X)echo"<tr valign='top'><td>".h($X[0])."<td>".h($X[1])."<th>".h($x)."<td class='hover'><a href='".h(ME.'trigger='.url_escape($a).'&name='.url_escape($x))."'>".'Alter'."</a>\n";echo"</table>\n";}echo'<p class="links hover"><a href="'.h(ME).'trigger='.url_escape($a).'">'.'Create trigger'."</a>\n","</div>\n";}$ej=driver()->shadowTables($a);if($ej){echo"<h3 id='shadow-tables'>".'Shadow tables'."</h3>\n";tables_links($ej);}$De=driver()->inheritedTables($a);if($De){echo"<h3 id='partitions'>".'Inherited by'."</h3>\n";$sh=driver()->partitionsInfo($a);if($sh)echo"<p><code class='jush-".JUSH."'>BY ".h("$sh[partition_by]($sh[partition])")."</code>\n";tables_links($De);}}elseif(isset($_GET["schema"])){page_header('Database schema',"",array(),h(DB.($_GET["ns"]?".$_GET[ns]":"")));function
schema_column($R,array$pi,array&$d){if(!isset($d[$R])){$d[$R]=0;foreach((array)idx($pi,$R)as$B=>$ri){if($B!=$R)$d[$R]=max($d[$R],schema_column($B,$pi,$d)+1);}}return$d[$R];}function
type_class($U){foreach(array('char'=>'text','date'=>'time|year','binary'=>'blob','enum'=>'set',)as$x=>$X){if(preg_match("~$x|$X~",$U))return" class='$x'";}}$Lj=array();$Nj=array();$Mj=array();$kd=array();$ca=($_GET["schema"]?:$_COOKIE["adminer_schema-".str_replace(".","_",DB)]);preg_match_all('~([^:]+):([-0-9.]+)x([-0-9.]+)(_|$)~',$ca,$Ff,PREG_SET_ORDER);foreach($Ff
as$s=>$A){$Lj[$A[1]]=array((float)$A[2],(float)$A[3]);$Nj[]="\n\t'".js_escape($A[1])."': [ $A[2], $A[3] ]";}$Ji=array();$pi=array();$_d=array();$oa=driver()->allFields();$ie=array();$Oj=array();foreach(table_status('',true)as$R=>$S){if(!is_view($S)){if(adminer()->tableName($S)!=""&&!$S["dependent"])$Oj[$R]=$S;else$ie[$R]=true;}}foreach($Oj
as$R=>$S){$G=0;$Ji[$R]["fields"]=array();foreach($oa[$R]as$l){$G+=1.25;$kd[$R][$l["field"]]=$G;$Ji[$R]["fields"][$l["field"]]=$l;}foreach(adminer()->foreignKeys($R)as$X){if($X["db"]==""&&$X["ns"]==""&&!$ie[$X["table"]]){$_d[$R][]=$X;$pi[$X["table"]][$R]=array();}}}$d=array();$Od=array();$ql=array();$Td=array();foreach(array_keys($Ji)as$B)schema_column($B,$pi,$d);arsort($d);foreach($d
as$B=>$c){$gg=null;foreach((array)idx($_d,$B)as$X){if($X["table"]!=$B&&$Ji[$X["table"]])$gg=($gg===null?$d[$X["table"]]:min($gg,$d[$X["table"]]));}$d[$B]=max($c,(int)$gg-1);}foreach($Ji
as$B=>$R){$c=$d[$B];$Od[$c][]=$B;$dk=.75*strlen($B);foreach($R["fields"]as$l)$dk=max($dk,.65*strlen($l["field"]));$ql[$c]=max(idx($ql,$c,0),ceil($dk)+1);}foreach($_d
as$B=>$al){foreach($al
as$X){$Sd=$d[$B]+(idx($d,$X["table"],$d[$B])>$d[$B]?1:0);$Td[$Sd]=idx($Td,$Sd,0)+1;}}ksort($Od);$ge=0;$pl=0;$ob=0;$Vh=null;$Jj=array();$Qj=array();foreach($Od
as$c=>$T){if($Vh!==null){$ob=round($ob+$ql[$Vh]+1.7+idx($Td,$c,0)*.1,1);$D=array();foreach($T
as$B){$Dj=0;$Eb=0;$tg=array_keys((array)idx($pi,$B));foreach((array)idx($_d,$B)as$X)$tg[]=$X["table"];foreach($tg
as$qg){if($Ji[$qg]&&$d[$qg]<$c){$Dj+=$Ji[$qg]["pos"][0];$Eb++;}}$D[$B]=($Eb?$Dj/$Eb:$ge);}asort($D);$T=array_keys($D);}$ok=0;foreach($T
as$B){$G=1.25*count($Ji[$B]["fields"]);$Ji[$B]["pos"]=($Lj[$B]?:array($ok,$ob));$Jj[$B]=$Ji[$B]["pos"][1];$Qj[$B]=$ql[$c];$ok+=2.5+$G;$ge=max($ge,$Ji[$B]["pos"][0]+2.5+$G);$pl=max($pl,round($Ji[$B]["pos"][1]+$ql[$c],1));if(!$Lj[$B])$Mj[]="\n\t'".js_escape($B)."': [ ".$Ji[$B]["pos"][0].", ".$Ji[$B]["pos"][1]." ]";}$Vh=$c;}$pf=array();$Ja=array();foreach($_d
as$B=>$al){foreach($al
as$X){$Wj=idx($Jj,$X["table"],$Jj[$B]);$oj=$Jj[$B]+$Qj[$B];$_i=($Wj-1>$oj);$nf=($_i?$oj+1:min($Jj[$B],$Wj)-1);$Ia=idx($Ja,(string)$nf,0);$Ja[(string)$nf]=$Ia+1;$nf=round($_i?min($nf+$Ia*.1,$Wj-1):$nf-$Ia*.1,1);while($pf[(string)$nf])$nf-=.0001;$Ji[$B]["references"][$X["table"]][(string)$nf]=array($X["source"],$X["target"]);$pi[$X["table"]][$B][(string)$nf]=$X["target"];$pf[(string)$nf]=true;}}echo'<div id="schema" style="height: ',$ge,'em; width: ',$pl,'em;">
<script',nonce(),'>
const tablePos = {',implode(",",$Nj)."\n",'};
const tablePosDefault = {',implode(",",$Mj)."\n",'};
const em = qs(\'#schema\').offsetHeight / ',$ge,';
document.onmousemove = schemaMousemove;
document.onmouseup = event => schemaMouseup(event, \'',js_escape(DB),'\');
</script>
';foreach($Ji
as$B=>$R){echo"<div class='table'".on('mousedown','schemaMousedown')." style='top: ".$R["pos"][0]."em; left: ".$R["pos"][1]."em; width: ".$Qj[$B]."em;'>",'<a href="'.h(ME).'table='.url_escape($B).'"><b>'.h($B)."</b></a>";foreach($R["fields"]as$l){$X='<span'.type_class($l["type"]).' title="'.h($l["type"].($l["length"]?"($l[length])":"").($l["null"]?" NULL":'')).'">'.h($l["field"]).'</span>';echo"<br>".($l["primary"]?"<i>$X</i>":$X);}foreach((array)$R["references"]as$Xj=>$ri){foreach($ri
as$nf=>$mi){$of=$nf-$R["pos"][1];$Aj=($of>0?"left: 100%; width: calc($of"."em - 100%)":"left: $of"."em");$pl=($of>0?"100%":(-$of)."em");$s=0;foreach($mi[0]as$nj)echo"\n<div class='references' title='".h($Xj)."' id='refs$nf-".($s++)."' style='$Aj"."; top: ".$kd[$B][$nj]."em; padding-top: .5em;'>"."<div style='border-top: 1px solid gray; width: $pl;'></div></div>";}}foreach((array)$pi[$B]as$Xj=>$ri){foreach($ri
as$nf=>$Yj){$of=$nf-$R["pos"][1];$s=0;foreach($Yj
as$Vj)echo"\n<div class='references arrow' title='".h($Xj)."' id='refd$nf-".($s++)."' style='left: $of"."em; top: ".$kd[$B][$Vj]."em;'>"."<div style='height: .5em; border-bottom: 1px solid gray; width: ".(-$of)."em;'></div>"."</div>";}}echo"\n</div>\n";}foreach($Ji
as$B=>$R){foreach((array)$R["references"]as$Xj=>$ri){if($Ji[$Xj]){foreach($ri
as$nf=>$mi){$hg=$ge;$Nf=-10;foreach($mi[0]as$x=>$nj){$Nh=$R["pos"][0]+$kd[$B][$nj];$Oh=$Ji[$Xj]["pos"][0]+$kd[$Xj][$mi[1][$x]];$hg=min($hg,$Nh,$Oh);$Nf=max($Nf,$Nh,$Oh);}echo"<div class='references' id='refl$nf' style='left: $nf"."em; top: $hg"."em; padding: .5em 0;'><div style='border-right: 1px solid gray; margin-top: 1px; height: ".($Nf-$hg)."em;'></div></div>\n";}}}}echo'</div>
<p class="links"><a href="',h(ME."schema=".url_escape($ca)),'" id="schema-link">Permanent link</a>
';}elseif(isset($_GET["dump"])){$a=$_GET["dump"];if($_POST&&!$k){$j=array("auto_increment"=>'');foreach(array("type","routine","event","trigger")as$Fj){if(support($Fj))$j[$Fj."s"]='';}save_settings(array_intersect_key($_POST+$j,array_flip(array("output","format","db_style","table_style","data_style"))+$j),"adminer_export");$T=array_flip((array)$_POST["tables"])+array_flip((array)$_POST["data"]);$bd=dump_headers((count($T)==1?key($T):DB),(DB==""||$_GET["ns"]===""||count($T)>1));$Ve=preg_match('~sql~',$_POST["format"]);if($Ve){echo"-- Adminer ".VERSION." ".get_driver(DRIVER)." ".str_replace("\n"," ",connection()->server_info)." dump\n\n";if(JUSH=="sql"){echo"SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
".($_POST["data_style"]?"SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
":"")."
";connection()->query("SET time_zone = '+00:00'");connection()->query("SET sql_mode = ''");}}$Aj=$_POST["db_style"];$h=array(DB);if(DB==""){$h=$_POST["databases"];if(is_string($h))$h=explode("\n",rtrim(str_replace("\r","",$h),"\n"));}foreach((array)$h
as$i){adminer()->dumpDatabase($i);if(connection()->select_db($i)){if($Ve&&$Aj)echo
use_sql($i,$Aj).";\n\n";foreach(($_GET["ns"]===""?(array)$_POST["schemas"]:(DB!=""||!support("scheme")?array(""):adminer()->schemas()))as$Ji){if($Ji!=""){if(DB==""&&information_schema(DB,$Ji))continue;set_schema($Ji);}$yj=($_POST["table_style"]||$_POST["data_style"]?table_status('',true):array());$ad=array();$Qb=array();foreach($yj
as$B=>$S){if(DB==""||$_GET["ns"]===""||in_array($B,(array)$_POST["tables"]))$ad[$B]=$S;if(DB==""||$_GET["ns"]===""||in_array($B,(array)$_POST["data"]))$Qb[$B]=$S;}if($Ve){if($_POST["table_style"]=="DROP+CREATE"&&function_exists('Adminer\drop_sql'))echo
drop_sql($ad);if($_POST["data_style"]=="TRUNCATE+INSERT"&&function_exists('Adminer\truncate_all_sql')){$yk=array();foreach($Qb
as$B=>$S){if(!is_view($S)&&!($_POST["table_style"]=="DROP+CREATE"&&isset($ad[$B])))$yk[]=$B;}echo
truncate_all_sql($yk);}$kh="";if($_POST["types"]){foreach(types()as$t=>$U){$ac=type_definition($t);$Dg=($ac["kind"]=='d'?"DOMAIN":"TYPE");if($ac["definition"])$kh
.=($Aj!='DROP+CREATE'?"DROP $Dg IF EXISTS ".idf_escape($U).";;\n":"")."CREATE $Dg ".idf_escape($U)." $ac[definition];\n\n";else$kh
.="-- Could not export type $U\n\n";}}if($_POST["routines"]){foreach(routines()as$K){$B=$K["ROUTINE_NAME"];$Ci=$K["ROUTINE_TYPE"];$g=create_routine($Ci,array("name"=>$B)+routine($K["SPECIFIC_NAME"],$Ci));set_utf8mb4($g);$kh
.=($Aj!='DROP+CREATE'?"DROP $Ci IF EXISTS ".idf_escape($B).";;\n":"")."$g;\n\n";}}if($_POST["events"]){foreach(get_rows("SHOW EVENTS",null,"-- ")as$K){$g=remove_definer(get_val("SHOW CREATE EVENT ".idf_escape($K["Name"]),3));set_utf8mb4($g);$kh
.=($Aj!='DROP+CREATE'?"DROP EVENT IF EXISTS ".idf_escape($K["Name"]).";;\n":"")."$g;;\n\n";}}echo($kh&&JUSH=='sql'?"DELIMITER ;;\n\n$kh"."DELIMITER ;\n\n":$kh);}if($_POST["table_style"]||$_POST["data_style"]){$gl=array();foreach($yj
as$B=>$S){$R=array_key_exists($B,$ad);$Ob=array_key_exists($B,$Qb);if($R||$Ob){$lk=null;if($bd=="tar"){$lk=new
TmpFile;ob_start(array($lk,'write'),1e5);}adminer()->dumpTable($B,($R?$_POST["table_style"]:""),(is_view($S)?2:0));if(is_view($S))$gl[]=$B;elseif($Ob){$m=fields($B);$M=array("*");$Bb=convert_fields($m,$m);if($Bb)$M[]=substr($Bb,2);adminer()->dumpData($B,$_POST["data_style"],"",$M);}if($Ve&&$_POST["triggers"]&&$R&&($xk=trigger_sql($B)))echo"\nDELIMITER ;;\n$xk\nDELIMITER ;\n";if($bd=="tar"){ob_end_flush();tar_file((DB!=""?"":"$i/")."$B.csv",$lk);}elseif($Ve)echo"\n";}}if($Ve&&$_POST["table_style"]&&function_exists('Adminer\foreign_keys_sql')){foreach($ad
as$B=>$S){if(!is_view($S))echo
foreign_keys_sql($B);}}if($Ve){foreach($gl
as$fl)adminer()->dumpTable($fl,$_POST["table_style"],1);}if($bd=="tar")echo
pack("x1024");}}}}adminer()->dumpFooter();exit;}page_header('Export',$k,($_GET["export"]!=""?array("table"=>$_GET["export"]):array()),h(DB));echo'
<form action="" method="post">
<table class="layout">
';$Tb=array('','USE','DROP+CREATE','CREATE');$Pj=array('','DROP+CREATE','CREATE');$Pb=array('','TRUNCATE+INSERT','INSERT');if(JUSH=="sql")$Pb[]='INSERT+UPDATE';$K=get_settings("adminer_export");if(!$K)$K=array("output"=>"text","format"=>"sql","db_style"=>(DB!=""?"":"CREATE"),"table_style"=>"DROP+CREATE","data_style"=>"INSERT");echo"<tr><th>".'Output'."<td>".html_radios("output",adminer()->dumpOutput(),$K["output"])."\n","<tr><th>".'Format'."<td>".html_radios("format",adminer()->dumpFormat(),$K["format"])."\n",(JUSH=="sqlite"?"":"<tr><th>".'Database'."<td>".html_select('db_style',$Tb,$K["db_style"]).(support("type")?checkbox("types",1,$K["types"],'User types'):"").(support("routine")?checkbox("routines",1,$K["routines"],'Routines'):"").(support("event")?checkbox("events",1,$K["events"],'Events'):"")),"<tr><th>".'Tables'."<td>".html_select('table_style',$Pj,$K["table_style"]).checkbox("auto_increment",1,$K["auto_increment"],'Auto Increment').(support("trigger")?checkbox("triggers",1,$K["triggers"],'Triggers'):""),"<tr><th>".'Data'."<td>".html_select('data_style',$Pb,$K["data_style"]),'</table>
';adminer()->dumpPrint();echo'<p><input type=\'submit\' value=\'Export\'>
',input_token(),'
<table',on('click','dumpClick'),'>
';$Uh=array();if($_GET["ns"]===""){echo"<thead><tr><th style='text-align: left;'>","<label class='block'><input type='checkbox' id='check-schemas' checked class='jsonly' title='".'All'."'".on('click','formCheck','^schemas\[').">".'Schema'."</label>","<tbody>\n";foreach(adminer()->schemas()as$Ji){if(!information_schema(DB,$Ji))echo"<tr><td>".checkbox("schemas[]",$Ji,true,$Ji,"","block")."\n";}}elseif(DB!=""){$cb=($a!=""?"":" checked");echo"<thead><tr>","<th style='text-align: left;'><label class='block'><input type='checkbox' id='check-tables'$cb class='jsonly' title='".'All'."'".on('click','formCheck','^tables\[').">".'Table'."</label>","<th style='text-align: right;'><label class='block'>".'Data'."<input type='checkbox' id='check-data'$cb class='jsonly' title='".'All'."'".on('click','formCheck','^data\[')."></label>","<tbody>\n";$gl="";$Sj=tables_list();foreach($Sj
as$B=>$U){$Th=preg_replace('~_.*~','',$B);$cb=($a==""||$a==(substr($a,-1)=="%"?"$Th%":$B));$Yh="<tr><td>".checkbox("tables[]",$B,$cb,$B,"","block");if($U!==null&&!preg_match('~table~i',$U))$gl
.="$Yh\n";else
echo"$Yh<td align='right'><label class='block'><span id='Rows-".h($B)."'></span>".checkbox("data[]",$B,$cb)."</label>\n";$Uh[$Th]++;}echo$gl;if($Sj)echo
script("ajaxSetHtml('".js_escape(ME)."script=db');");}else{$h=adminer()->databases();echo"<thead><tr><th style='text-align: left;'>","<label class='block'>".($h?"<input type='checkbox' id='check-databases'".($a==""?" checked":"")." class='jsonly' title='".'All'."'".on('click','formCheck','^databases\[').">":"").'Database'."</label>","<tbody>\n";if($h){foreach($h
as$i){if(!information_schema($i)){$Th=preg_replace('~_.*~','',$i);echo"<tr><td>".checkbox("databases[]",$i,$a==""||$a=="$Th%",$i,"","block")."\n";$Uh[$Th]++;}}}else
echo"<tr><td><textarea name='databases' rows='10' cols='20'></textarea>";}echo'</table>
</form>
';$td=true;foreach($Uh
as$x=>$X){if($x!=""&&$X>1){echo($td?"<p>":" ")."<a href='".h(ME)."dump=".url_escape("$x%")."'>".h($x)."</a>";$td=false;}}}elseif(isset($_GET["privileges"])){page_header('Privileges');echo'<p class="links"><a href="'.h(ME).'user=">'.'Create user'."</a>";$I=connection()->query("SELECT User, Host FROM mysql.".(DB==""?"user":"db WHERE ".q(DB)." LIKE Db")." ORDER BY Host, User");$Md=$I;if(!$I)$I=connection()->query("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', 1) AS User, SUBSTRING_INDEX(CURRENT_USER, '@', -1) AS Host");echo"<form action=''><p>\n";hidden_fields_get();echo
input_hidden("db",DB),($Md?"":input_hidden("grant")),"<table class='odds'>\n","<thead><tr><th>".'Username'."<th>".'Server'."<td class='hover'><tbody>\n";while($K=$I->fetch_assoc())echo'<tr><td>'.h($K["User"]),"<td>".h($K["Host"]),'<td class="hover"><a href="'.h(ME.'user='.url_escape($K["User"]).'&host='.url_escape($K["Host"])).'">'.'Edit'."</a>\n";if(!$Md||DB!="")echo"<tr><td><input name='user' autocapitalize='off'>","<td><input name='host' value='localhost' autocapitalize='off'>","<td class='hover'><input type='submit' value='".'Edit'."'>\n";echo"</table>\n","</form>\n";}elseif(isset($_GET["sql"])){if(!$k&&$_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers("sql");if($_POST["format"]=="sql")echo"$_POST[query]\n";else{adminer()->dumpTable("","");adminer()->dumpData("","table",$_POST["query"]);adminer()->dumpFooter();}exit;}restart_session();$ke=&get_session("queries");$je=&$ke[DB];if(!$k&&$_POST["clear"]){$je=array();redirect(remove_from_uri("history"));}stop_session();$ja=get_settings("adminer_import");if($_POST&&$ja)save_settings($ja,"adminer_import");page_header((isset($_GET["import"])?'Import':'SQL command'),$k);$xf=driver()->lineComment();if(!$k&&$_POST&&!(isset($_GET["import"])&&adminer()->importProcess())){$cc=driver()->delimiter;$p=false;if(!isset($_GET["import"]))$H=$_POST["query"];elseif($_POST["webfile"]){$rj=adminer()->importServerPath();$p=@fopen((file_exists($rj)?$rj:"compress.zlib://$rj.gz"),"rb");$H=($p?fread($p,1e6):false);}else$H=get_file("sql_file",true,$cc);if(is_string($H)){if(($Vf=ini_bytes("memory_limit"))!="-1")ini_set("memory_limit",max($Vf,strval(2*strlen($H)+memory_get_usage()+8e6)));if($H!=""&&strlen($H)<1e6){$fi=$H.(preg_match("~$cc\\s*\$~",$H)?"":$cc);if(!$je||first(end($je))!=$fi){restart_session();$je[]=array($fi,time());set_session("queries",$ke);stop_session();}}$pj="(?:\\s|/\\*[\s\S]*?\\*/|(?:$xf)[^\n]*\n?|--\r?\n)";$Ig=0;$Gc=true;$Db=false;$f=connect();if($f&&DB!=""){$f->select_db(DB);if($_GET["ns"]!="")set_schema($_GET["ns"],$f);}$pb=0;$Nc=array();$qh='[\'"'.(JUSH=="sql"?'`':(JUSH=="sqlite"?'`[':(JUSH=="mssql"?'[':''))).']|/\*|'.$xf.'|$'.(JUSH=="pgsql"?'|\$([a-zA-Z]\w*)?\$':'');$pk=microtime(true);while($H!=""){if(!$Ig&&preg_match("~^$pj*+DELIMITER\\s+(\\S+)~i",$H,$A)){$cc=preg_quote($A[1]);$H=substr($H,strlen($A[0]));}elseif(!$Ig&&JUSH=='pgsql'&&preg_match("~^($pj*+COPY\\s+)[^;]+\\s+FROM\\s+stdin;~i",$H,$A)){$cc="\n\\\\\\.\r?\n";$Db=true;$Ig=strlen($A[0]);}else{preg_match("($cc\\s*|$qh)",$H,$A,PREG_OFFSET_CAPTURE,$Ig);list($Bd,$G)=$A[0];if(!$Bd&&$p&&!feof($p))$H
.=fread($p,1e5);else{if(!$Bd&&rtrim($H)=="")break;$Ig=$G+strlen($Bd);if($Bd&&!preg_match("(^$cc)",$Bd)){$Ua=driver()->hasCStyleEscapes()||(JUSH=="pgsql"&&($G>0&&strtolower($H[$G-1])=="e"));$Eh=($Bd=='/*'?'\*/':($Bd=='['?']':(preg_match("~^(?:$xf)~",$Bd)?"\n":preg_quote($Bd).($Ua?'|\\\\.':''))));while(preg_match("($Eh|\$)s",$H,$A,PREG_OFFSET_CAPTURE,$Ig)){$Hi=$A[0][0];if(!$Hi&&$p&&!feof($p))$H
.=fread($p,1e5);else{$Ig=$A[0][1]+strlen($Hi);if(!$Hi||$Hi[0]!="\\")break;}}}else{$fi=substr($H,0,$G+($Db?3:0));$H=substr($H,$Ig);$Ig=0;if($Db){$cc=driver()->delimiter;$Db=false;}$ib="<code class='jush-".JUSH."'>".adminer()->sqlCommandQuery($fi)."</code>";if(preg_match("~^$pj*+\$~",$fi)&&!preg_match('~/\*M?!~',$fi)){echo($_POST["only_errors"]?"":"<pre>$ib</pre>\n");continue;}$Gc=false;$pb++;$Yh="<pre id='sql-$pb'>$ib</pre>\n";if(JUSH=="sqlite"&&preg_match("~^$pj*+(ATTACH|VACUUM\\b.*\\bINTO)\\b~is",$fi,$A)!==0){echo$Yh,"<p class='error'>".sprintf('%s queries are not supported.',preg_match('~ATTACH~i',$A[1])?'ATTACH':'VACUUM INTO')."\n";$Nc[]=" <a href='#sql-$pb'>$pb</a>";if($_POST["error_stops"])break;}else{if(!$_POST["only_errors"]){echo$Yh;ob_flush();flush();}$wj=microtime(true);if(connection()->multi_query($fi)&&$f&&preg_match("~^$pj*+USE\\b~i",$fi))$f->query($fi);do{$I=connection()->store_result();if(connection()->error){echo($_POST["only_errors"]?$Yh:""),"<p class='error'>".'Error in query'.(connection()->errno?" (".connection()->errno.")":"").": ".adminer()->error()."\n";$Nc[]=" <a href='#sql-$pb'>$pb</a>";if($_POST["error_stops"])break
2;}else{$z=ME."sql=".url_escape(trim($fi));$ek=" <span class='time'>(".format_time($wj).")</span>".(strlen($z)<1900?" <a href='".h($z)."'>".'Edit'."</a>":"");$la=connection()->affected_rows;$jl=($_POST["only_errors"]?"":driver()->warnings());$kl="warnings-$pb";if($jl)$ek
.=", <a href='#$kl' class='toggle'>".'Warnings'."</a>";$Yc=null;$ch=null;$Zc="explain-$pb";if(is_object($I)){$y=$_POST["limit"];$Bg=$y;$ch=print_select_result($I,$f,array(),$Bg);if(!$_POST["only_errors"]){echo"<form action='' method='post'>\n";$Bg=max($I->num_rows,$Bg);echo"<p class='sql-footer'>".($Bg?($y&&$Bg>$y?sprintf('%d / ',$y):"").lang_format(array('%d row','%d rows'),$Bg):""),$ek;if($f&&preg_match("~^($pj|\\()*+SELECT\\b~i",$fi)&&($Yc=explain($f,$fi)))echo", <a href='#$Zc' class='toggle'>Explain</a>";$t="export-$pb";echo", <a href='#$t' class='toggle'>".'Export'."</a><span id='$t' class='hidden'>: ".html_select("output",adminer()->dumpOutput(),$ja["output"])." ".html_select("format",adminer()->dumpFormat(),$ja["format"]).input_hidden("query",$fi)."<input type='submit' name='export' value='".'Export'."'".($y?"":on('click','sqlExport')).">".input_token()."</span>\n"."</form>\n";}}else{if(preg_match("~^$pj*+(CREATE|DROP|ALTER)$pj++(DATABASE|SCHEMA)\\b~i",$fi)){restart_session();set_session("dbs",null);stop_session();}if(!$_POST["only_errors"])echo"<p class='message' title='".h(connection()->info)."'>".lang_format(array('Query executed OK, %d row affected.','Query executed OK, %d rows affected.'),$la)."$ek\n";}echo($jl?"<div id='$kl' class='hidden'>\n$jl</div>\n":"");if($Yc){echo"<div id='$Zc' class='hidden explain'>\n";print_select_result($Yc,$f,$ch);echo"</div>\n";}}$wj=microtime(true);}while(connection()->next_result());}}}}}if($Gc)echo"<p class='message'>".'No commands to execute.'."\n";else{$xe=connection()->inTransaction();driver()->rollback();if($xe)echo"<pre><code class='jush-".JUSH."'>ROLLBACK -- Adminer</code></pre>\n";if($_POST["only_errors"])echo"<p class='message'>".lang_format(array('%d query executed OK.','%d queries executed OK.'),$pb-count($Nc))," <span class='time'>(".format_time($pk).")</span>\n";elseif($Nc&&$pb>1)echo"<p class='error'>".'Error in query'.": ".implode("",$Nc)."\n";}}else
echo"<p class='error'>".upload_error($H)."\n";}echo'
<form action="" method="post" enctype="multipart/form-data" id="form"';$Nk="";if(!isset($_GET["import"]))echo
on('submit','sqlSubmit',remove_from_uri("sql|limit|error_stops|only_errors|history"));else
echo
on_upload_progress($Nk);echo'>
';$Vc="<input type='submit' value='".'Execute'."' title='Ctrl+Enter'>";if(!isset($_GET["import"])){$fi=$_GET["sql"];if($_POST)$fi=$_POST["query"];elseif($_GET["history"]=="all")$fi=$je;elseif($_GET["history"]!="")$fi=idx($je[$_GET["history"]],0);echo"<p>";textarea("query",$fi,20);echo($_POST?"":script("qs('textarea').focus();")),"<p>";adminer()->sqlPrintAfter();echo"$Vc\n",'Limit rows'.": <input type='number' name='limit' class='size' value='".h($_POST?$_POST["limit"]:$_GET["limit"])."'>\n";}else{$Ud=(extension_loaded("zlib")?"[.gz]":"");echo"<fieldset><legend>".'File upload'."</legend><div>",($Nk?input_hidden(ini_get("session.upload_progress.name"),$Nk):""),"SQL$Ud: ".file_input(" name='sql_file[]' multiple","\n$Vc"),($Nk?" <progress class='jsonly hidden' max='1' value='0'></progress>":""),"</div></fieldset>\n";$ue=adminer()->importServerPath();if($ue)echo"<fieldset><legend>".'From server'."</legend><div>",sprintf('Webserver file %s',"<code>".h($ue)."$Ud</code>")," <input type='submit' name='webfile' value='".'Run file'."'>","</div></fieldset>\n";adminer()->importPrint();echo"<p>";}echo
checkbox("error_stops",1,($_POST?$_POST["error_stops"]:isset($_GET["import"])||$_GET["error_stops"]),'Stop on error')."\n",checkbox("only_errors",1,($_POST?$_POST["only_errors"]:isset($_GET["import"])||$_GET["only_errors"]),'Show only errors')."\n",input_token();if(!isset($_GET["import"])&&$je){print_fieldset("history",'History',$_GET["history"]!="");for($X=end($je);$X;$X=prev($je)){$x=key($je);list($fi,$ek,$Cc)=$X;echo'<div><a href="'.h(ME."sql=&history=$x").'" class="hover">'.'Edit'."</a>"." <span class='time' title='".@date('Y-m-d',$ek)."'>".@date("H:i:s",$ek)."</span>"." <code class='jush-".JUSH."'>".shorten_utf8(preg_replace('~\s+~',' ',ltrim(preg_replace("~^(?:$xf).*~m",'',$fi))),80,"</code>").($Cc?" <span class='time'>($Cc)</span>":"")."</div>\n";}echo"<input type='submit' name='clear' value='".'Clear'."'>\n","<a href='".h(ME."sql=&history=all")."'>".'Edit all'."</a>\n","</div></fieldset>\n";}echo'</form>
';}elseif(isset($_GET["edit"])){$a=$_GET["edit"];$m=fields($a);$Z=(isset($_GET["select"])?($_POST["check"]&&count($_POST["check"])==1?where_check($_POST["check"][0],$m):""):where($_GET,$m));$Lk=(isset($_GET["select"])?$_POST["edit"]:$Z);foreach($m
as$B=>$l){if((!$Lk&&!isset($l["privileges"]["insert"]))||adminer()->fieldName($l)=="")unset($m[$B]);}if($_POST&&!$k&&!isset($_GET["select"])){$_=relative_uri((string)$_POST["referer"]);if($_POST["insert"])$_=($Lk?null:relative_uri());elseif(!preg_match('~^.+&select=.+$~',$_))$_=ME."select=".url_escape($a);$w=indexes($a);$Fk=unique_array($_GET["where"],$w);$ii="\nWHERE $Z";if(isset($_POST["delete"]))queries_redirect($_,'Item has been deleted.',driver()->delete($a,$ii,$Fk?0:1));else{$O=array();foreach($m
as$B=>$l){$X=process_input($l);if($X!==false&&$X!==null)$O[idf_escape($B)]=$X;}if($Lk){if(!$O)redirect($_);queries_redirect($_,'Item has been updated.',driver()->update($a,$O,$ii,$Fk?0:1));if(is_ajax()){page_headers();page_messages($k);exit;}}else{$I=driver()->insert($a,$O);$mf=($I?last_id($I):0);queries_redirect($_,sprintf('Item%s has been inserted.',($mf?" $mf":"")),$I);}}}$K=null;$H="";$ek="";if($Z){$M=array();$Qi=array("*");foreach($m
as$B=>$l){if(isset($l["privileges"]["select"])){$xa=($_POST["clone"]&&$l["auto_increment"]?"''":convert_field($l));$c=($xa?"$xa AS ":"").idf_escape($B);$M[]=$c;if($xa)$Qi[]=$c;}}$K=array();if(!support("table")){$M=array("*");$Qi=$M;}if($M){$wj=microtime(true);$I=driver()->select($a,$M,array($Z),$M,array(),(isset($_GET["select"])?2:1));$H=str_replace("SELECT ".implode(", ",$M),"SELECT ".implode(", ",$Qi),driver()->query);$ek=format_time($wj);if(!$I)$k=adminer()->error();else{$K=$I->fetch_assoc();if(!$K)$K=false;}if(isset($_GET["select"])&&(!$K||$I->fetch_assoc()))$K=null;}}if(!$m&&driver()->primary!=""){if(!$Z){$I=driver()->select($a,array("*"),array(),array("*"));$K=($I?$I->fetch_assoc():false);if(!$K)$K=array(driver()->primary=>"");}if($K){foreach($K
as$x=>$X){if(!$Z)$K[$x]=null;$m[$x]=array("field"=>$x,"null"=>($x!=driver()->primary),"auto_increment"=>($x==driver()->primary));}}}if($_POST["save"]){$Ph=array();foreach((array)$_POST["fields"]as$x=>$X)$Ph[bracket_escape($x,true)]=$X;$K=$Ph+($K?$K:array());}edit_form($a,$m,$K,$Lk,$k,$H,$ek);}elseif(isset($_GET["create"])){function
referencable_primary($Si){$J=array();foreach(table_status('',true)as$Kj=>$R){if($Kj!=$Si&&!$R["dependent"]&&fk_support($R)){foreach(fields($Kj)as$l){if($l["primary"]){if($J[$Kj]){unset($J[$Kj]);break;}$J[$Kj]=$l;}}}}return$J;}$a=$_GET["create"];$uh=driver()->partitionBy;$yh=($uh&&$a!=""?driver()->partitionsInfo($a):array());$oi=referencable_primary($a);$_d=array();foreach($oi
as$Kj=>$l)$_d[str_replace("`","``",$Kj)."`".str_replace("`","``",$l["field"])]=$Kj;$fh=array();$S=array();if($a!=""){$fh=fields($a);$S=table_status1($a);if(count($S)<2)$k='No tables.';}$ra=($a==""||driver()->supportsAlterTable($S));$K=$_POST;$K["fields"]=(array)$K["fields"];if($K["auto_increment_col"])$K["fields"][$K["auto_increment_col"]]["auto_increment"]=true;if($_POST&&!$k)save_settings(array("comments"=>$_POST["comments"],"defaults"=>$_POST["defaults"]));if($_POST&&!process_fields($K["fields"])&&!$k){if($_POST["drop"])queries_redirect(substr(ME,0,-1),'Table has been dropped.',drop_tables(array($a)));else{$m=array();$oa=array();$Rk=false;$yd=array();$eh=reset($fh);$na=" FIRST";foreach($K["fields"]as$l){$o=$_d[$l["type"]];$_k=($o!==null?$oi[$o]:$l);if($l["field"]!=""){if(!$l["generated"])$l["default"]=null;$di=process_field($l,$_k);$oa[]=array($l["orig"],$di,$na);if(!$eh||$di!==process_field($eh,$eh)){$m[]=array($l["orig"],$di,$na);if($l["orig"]!=""||$na)$Rk=true;}if($o!==null)$yd[idf_escape($l["field"])]=($a!=""&&JUSH!="sqlite"?"ADD":" ").format_foreign_key(array('table'=>$_d[$l["type"]],'source'=>array($l["field"]),'target'=>array($_k["field"]),'on_delete'=>$l["on_delete"],));$na=" AFTER ".idf_escape($l["field"]);}elseif($l["orig"]!=""){$Rk=true;$m[]=array($l["orig"]);}if($l["orig"]!=""){$eh=next($fh);if(!$eh)$na="";}}$wh=array();if(in_array($K["partition_by"],$uh)){foreach($K
as$x=>$X){if(preg_match('~^partition~',$x))$wh[$x]=$X;}foreach($wh["partition_names"]as$x=>$B){if($B==""){unset($wh["partition_names"][$x]);unset($wh["partition_values"][$x]);}}$wh["partition_names"]=array_values($wh["partition_names"]);$wh["partition_values"]=array_values($wh["partition_values"]);if($wh==$yh)$wh=array();}elseif(preg_match("~partitioned~",$S["Create_options"]))$wh=null;$Xf='Table has been altered.';if($a==""){cookie("adminer_engine",$K["Engine"]);$Xf='Table has been created.';}$B=trim($K["name"]);$_=ME.(support("table")?"table=":"select=").url_escape($B);$I=alter_table($a,$B,(JUSH=="sqlite"&&($Rk||$yd)?$oa:$m),$yd,($K["Comment"]!=$S["Comment"]?$K["Comment"]:null),($K["Engine"]&&$K["Engine"]!=$S["Engine"]?$K["Engine"]:""),($K["Collation"]&&$K["Collation"]!=$S["Collation"]?$K["Collation"]:""),($K["Auto_increment"]!=""?number($K["Auto_increment"]):""),$wh);if($I&&!Queries::$queries&&$a!=""&&!$m&&!$yd)redirect($_);queries_redirect($_,$Xf,$I);}}page_header(($a!=""?'Alter table':'Create table'),$k,array("table"=>$a),h($a));if(!$_POST){$Ck=driver()->types();$K=array("Engine"=>$_COOKIE["adminer_engine"],"fields"=>array(array("field"=>"","type"=>(isset($Ck["int"])?"int":(isset($Ck["integer"])?"integer":"")),"on_update"=>"")),"partition_names"=>array(""),);if($a!=""){$K=$S;$K["name"]=$a;$K["fields"]=array();if(!$_GET["auto_increment"])$K["Auto_increment"]="";foreach($fh
as$l){if($l["generated"])$l["default"]=ltrim($l["default"]);$l["generated"]=$l["generated"]?:(isset($l["default"])?"DEFAULT":"");$K["fields"][]=$l;}if($uh){$K+=$yh;$K["partition_names"][]="";$K["partition_values"][]="";}}}$mb=flat_collations();$Ic=driver()->engines();foreach($Ic
as$Hc){if(!strcasecmp($Hc,$K["Engine"])){$K["Engine"]=$Hc;break;}}$If=max_input_vars(12,20);if($If){$ie=(count($K["fields"])>$If?"":" hidden");echo"<p".($ie?" id='max-fields' data-columns='$If'":"")." class='error$ie'>".max_input_vars_error()."\n";}echo'
<form action="" method="post" id="form">
<p>
';if(support("columns")||$a==""){echo'Table name'.": <input name='name'".($a==""&&!$_POST?" autofocus":"")." data-maxlength='64' value='".h($K["name"])."' autocapitalize='off'>\n",(!$ra?h($S["Engine"])."\n":($Ic?html_select("Engine",array(""=>"(".'engine'.")")+$Ic,$K["Engine"],on('change','helpClose').on_help_value())."\n":""));if($mb)echo"<datalist id='collations'>".optionlist($mb)."</datalist>\n",(preg_match("~sqlite|mssql~",JUSH)?"":"<input list='collations' name='Collation' value='".h($K["Collation"])."' placeholder='(".'collation'.")'>\n");echo"<input type='submit' value='".'Save'."'>\n";}if(support("columns")&&$ra){echo"<div class='scrollable'>\n","<table id='edit-fields' class='nowrap'>\n";edit_fields($K["fields"],$mb,"TABLE",$_d);echo"</table>\n",script("editFields();"),"</div>\n<p>\n",'Auto Increment'.": <input type='number' name='Auto_increment' class='size' value='".h($K["Auto_increment"])."'>\n",checkbox("defaults",1,($_POST?$_POST["defaults"]:get_setting("defaults")),'Default values',on('click','columnShowClick',5),"jsonly");$sb=($_POST?$_POST["comments"]:get_setting("comments"));if(support("comment")){echo
checkbox("comments",1,$sb,'Comment',on('click','editingCommentsClick',true),"jsonly").' ';$b=" name='Comment' data-maxlength='".(min_version(5.5)?2048:60)."'".($sb?"":" class='hidden'");echo
adminer()->commentInput('TABLE',$b,$K["Comment"]);}echo'<p>
<input type=\'submit\' value=\'Save\'>
';}echo'
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';if($uh&&(JUSH=='sql'||$a=="")){$vh=preg_match('~RANGE|LIST~',$K["partition_by"]);print_fieldset("partition",'Partition by',$K["partition_by"]);echo"<p>".html_select("partition_by",array_merge(array(""),$uh),$K["partition_by"],on('change','partitionByChange').on_help_value('.','PARTITION BY $&'))."\n","(<input name='partition' value='".h($K["partition"])."'>)\n",'Partitions'.": <input type='number' name='partitions' class='size".($vh||!$K["partition_by"]?" hidden":"")."' value='".h($K["partitions"])."'>\n","<table id='partition-table'".($vh?"":" class='hidden'").">\n","<thead><tr><th>".'Partition name'."<th>".'Values'."<tbody>\n";foreach($K["partition_names"]as$x=>$X)echo'<tr>','<td><input name="partition_names[]" value="'.h($X).'" autocapitalize="off"'.($x==count($K["partition_names"])-1?on('input','partitionNameChange'):'').'>','<td><input name="partition_values[]" value="'.h(idx($K["partition_values"],$x)).'">';echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["indexes"])){$a=$_GET["indexes"];$Be=array("PRIMARY","UNIQUE","INDEX");$S=table_status1($a,true);$_e=driver()->indexAlgorithms($S);if(preg_match('~MyISAM|M?aria'.(min_version(5.6,'10.0.5')?'|InnoDB':'').'~i',$S["Engine"]))$Be[]="FULLTEXT";if(preg_match('~MyISAM|M?aria'.(min_version(5.7,'10.2.2')?'|InnoDB':'').'~i',$S["Engine"]))$Be[]="SPATIAL";if(min_version('',11.7)&&preg_match('~MyISAM|InnoDB~i',$S["Engine"]))$Be[]="VECTOR";$w=indexes($a);$m=fields($a);$Xh=array();if(JUSH=="mongo"){$Xh=$w["_id_"];unset($Be[0]);unset($w["_id_"]);}$K=$_POST;if($K)save_settings(array("index_options"=>$K["options"]));if($_POST&&!$k&&!$_POST["add"]&&!$_POST["drop_col"]){$qa=array();foreach($K["indexes"]as$v){$B=$v["name"];if(in_array($v["type"],$Be)){$d=array();$uf=array();$fc=array();$Sg=array();$Ae=(support("partial_indexes")?$v["partial"]:"");$ze=(in_array($v["algorithm"],$_e)?$v["algorithm"]:"");$O=array();ksort($v["columns"]);foreach($v["columns"]as$x=>$c){if($c!=""){$rf=idx($v["lengths"],$x);$dc=idx($v["descs"],$x);$Rg=idx($v["opclasses"],$x);$O[]=($m[$c]?idf_escape($c):$c).($rf?"(".(+$rf).")":"").($Rg!=""?" ".idf_escape($Rg):"").($dc?" DESC":"");$d[]=$c;$uf[]=($rf?:null);$fc[]=$dc;$Sg[]="$Rg";}}$Wc=$w[$B];if($Wc){ksort($Wc["columns"]);ksort($Wc["lengths"]);ksort($Wc["descs"]);if($v["type"]==$Wc["type"]&&array_values($Wc["columns"])===$d&&(!$Wc["lengths"]||array_values($Wc["lengths"])===$uf)&&array_values($Wc["descs"])===$fc&&(!$Wc["opclasses"]||array_values($Wc["opclasses"])===$Sg)&&$Wc["partial"]==$Ae&&(!$_e||$Wc["algorithm"]==$ze)){unset($w[$B]);continue;}}if($d)$qa[]=array($v["type"],$B,$O,$ze,$Ae);}}foreach($w
as$B=>$Wc)$qa[]=array($Wc["type"],$B,"DROP");if(!$qa)redirect(ME."table=".url_escape($a));queries_redirect(ME."table=".url_escape($a),'Indexes have been altered.',alter_indexes($a,$qa));}page_header('Indexes',$k,array("table"=>$a),h($a));$md=array_keys($m);if($_POST["add"]){foreach($K["indexes"]as$x=>$v){if($v["columns"][count($v["columns"])]!="")$K["indexes"][$x]["columns"][]="";}$v=end($K["indexes"]);if($v["type"]||array_filter($v["columns"],'strlen'))$K["indexes"][]=array("columns"=>array(1=>""));}if(!$K){foreach($w
as$x=>$v){$w[$x]["name"]=$x;$w[$x]["columns"][]="";}$w[]=array("columns"=>array(1=>""));$K["indexes"]=$w;}$uf=(JUSH=="sql"||JUSH=="mssql");$Sg=driver()->indexOpclasses();$fj=($_POST?$_POST["options"]:get_setting("index_options"));echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap odds">
<thead><tr>
<th id="label-type">Index Type
';$se=" class='idxopts".($fj?"":" hidden")."'";if($_e)echo"<th id='label-algorithm'$se>".'Algorithm'.doc_link(array('sql'=>'create-index.html#create-index-storage-engine-index-types','mariadb'=>'storage-engine-index-types/',));echo'<th><input type="submit" hidden>','Columns'.($uf?"<span$se> (".'length'.")</span>":"");if($uf||support("descidx"))echo
checkbox("options",1,$fj,'Options',on('click','indexOptionsShow'),"jsonly")."\n";echo'<th id="label-name">Name
';if(support("partial_indexes"))echo"<th id='label-condition'$se>".'Condition';echo'<th><noscript>',icon("plus","add[0]","+",'Add next'),'</noscript>
<tbody>
';if($Xh){echo"<tr><td>PRIMARY<td>";foreach($Xh["columns"]as$x=>$c)echo
select_input(" disabled",array_combine($md,$md),$c),"<label><input disabled type='checkbox'>".'descending'."</label> ";echo"<td><td>\n";}$Ze=1;foreach($K["indexes"]as$v){if(!$_POST["drop_col"]||$Ze!=key($_POST["drop_col"])){echo"<tr><td>".html_select("indexes[$Ze][type]",array(-1=>"")+$Be,$v["type"],($Ze==count($K["indexes"])?on('change','indexesAddRow'):""),"label-type");if($_e)echo"<td$se>".html_select("indexes[$Ze][algorithm]",array_merge(array(""),$_e),$v['algorithm'],"","label-algorithm");echo"<td>";ksort($v["columns"]);$s=1;foreach($v["columns"]as$x=>$c){echo"<span>".select_input(" name='indexes[$Ze][columns][$s]' title='".'Column'."'".on('change','indexesChangeColumn',(JUSH=="sql"?"":$_GET["indexes"]."_")),($m&&($c==""||$m[$c])?array_combine($md,$md):array()),$c)," <span$se>",($uf?"<input type='number' name='indexes[$Ze][lengths][$s]' class='size' value='".h(idx($v["lengths"],$x))."' title='".'Length'."'>":"");if($Sg){$Rg=idx($v["opclasses"],$x);echo
html_select("indexes[$Ze][opclasses][$s]",array(""=>"(".'operator class'.")")+array_combine($Sg,$Sg)+($Rg!=""?array($Rg=>$Rg):array()),$Rg),'';}echo(support("descidx")?checkbox("indexes[$Ze][descs][$s]",1,idx($v["descs"],$x),'descending'):""),"<br>","</span></span>";$s++;}echo"<td><input name='indexes[$Ze][name]' value='".h($v["name"])."' autocapitalize='off' aria-labelledby='label-name'>\n";if(support("partial_indexes"))echo"<td$se><input name='indexes[$Ze][partial]' value='".h($v["partial"])."' autocapitalize='off' aria-labelledby='label-condition'>\n";echo"<td>".icon("cross","drop_col[$Ze]","x",'Remove',on('click','editingRemoveRow','indexes$1[type]'));}$Ze++;}echo'</table>
</div>
<p>
<input type=\'submit\' value=\'Save\'>
',input_token(),'</form>
';}elseif(isset($_GET["database"])){$K=$_POST;if($_POST&&!$k&&!$_POST["add"]){$B=trim($K["name"]);if($_POST["drop"]){$_GET["db"]="";queries_redirect(remove_from_uri("db|database"),'Database has been dropped.',drop_databases(array(DB)));}elseif($B!==DB){if(DB!=""){$_GET["db"]=$B;queries_redirect(preg_replace('~\bdb=[^&]*&~','',ME)."db=".url_escape($B),'Database has been renamed.',rename_database($B,(string)$K["collation"]));}else{$h=explode("\n",str_replace("\r","",$B));$Bj=true;$kf="";foreach($h
as$i){if(count($h)==1||$i!=""){if(!create_database($i,(string)$K["collation"]))$Bj=false;$kf=$i;}}restart_session();set_session("dbs",null);queries_redirect(preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($kf),'Database has been created.',$Bj);}}else{if(!$K["collation"])redirect(substr(ME,0,-1));query_redirect("ALTER DATABASE ".idf_escape($B).(preg_match('~^[a-z0-9_]+$~i',$K["collation"])?" COLLATE $K[collation]":""),substr(ME,0,-1),'Database has been altered.');}}page_header(DB!=""?'Alter database':'Create database',$k,array(),h(DB));$mb=collations();$B=DB;if($_POST)$B=$K["name"];elseif(DB!="")$K["collation"]=db_collation(DB,$mb);elseif(JUSH=="sql"){foreach(get_vals("SHOW GRANTS")as$Md){if(preg_match('~ ON (`(([^\\\\`]|``|\\\\.)*)%`\.\*)?~',$Md,$A)&&$A[1]){$B=stripcslashes(idf_unescape("`$A[2]`"));break;}}}echo'
<form action="" method="post">
<p>
',($_POST["add"]||strpos($B,"\n")?'<textarea autofocus name="name" rows="10" cols="40">'.h($B).'</textarea><br>':'<input name="name" autofocus value="'.h($B).'" data-maxlength="64" autocapitalize="off">')."\n",($mb?html_select("collation",array(""=>"(".'collation'.")")+$mb,$K["collation"]).doc_link(array('sql'=>"charset-charsets.html",'mariadb'=>"supported-character-sets-and-collations/",)):"")."\n",'<input type=\'submit\' value=\'Save\'>
';if(DB!="")echo"<input type='submit' name='drop' value='".'Drop'."'".confirm(sprintf('Drop %s?',DB)).">\n";elseif(!$_POST["add"]&&$_GET["db"]=="")echo
icon("plus","add[0]","+",'Add next')."\n";echo
input_token(),'</form>
';}elseif(isset($_GET["call"])){$ba=($_GET["name"]?:$_GET["call"]);page_header('Call'.": ".h($ba),$k);$Fi=(isset($_GET["callf"])?"FUNCTION":"PROCEDURE");$Ci=routine($_GET["call"],$Fi);$ve=array();$kh=array();foreach($Ci["fields"]as$s=>$l){if(substr($l["inout"],-3)=="OUT"&&JUSH=='sql')$kh[$s]="@".idf_escape($l["field"])." AS ".idf_escape($l["field"]);if(!$l["inout"]||substr($l["inout"],0,2)=="IN")$ve[]=$s;}if(!$k&&$_POST){$Va=array();foreach($Ci["fields"]as$x=>$l){$X="";if(in_array($x,$ve)){$X=process_input($l);if($X===false)$X="''";if(isset($kh[$x]))connection()->query("SET @".idf_escape($l["field"])." = $X");}if(isset($kh[$x]))$Va[]="@".idf_escape($l["field"]);elseif(in_array($x,$ve))$Va[]=$X;}$H=(isset($_GET["callf"])?"SELECT ":"CALL ").(idx($Ci["returns"],"type")=="record"?"* FROM ":"").table($ba)."(".implode(", ",$Va).")";$wj=microtime(true);$I=connection()->multi_query($H);$la=connection()->affected_rows;echo
adminer()->selectQuery($H,$wj,!$I);if(!$I)echo"<p class='error'>".adminer()->error()."\n";else{$f=connect();if($f)$f->select_db(DB);do{$I=connection()->store_result();if(is_object($I))print_select_result($I,$f);else
echo"<p class='message'>".lang_format(array('Routine has been called, %d row affected.','Routine has been called, %d rows affected.'),$la)." <span class='time'>".@date("H:i:s")."</span>\n";}while(connection()->next_result());if($kh)print_select_result(connection()->query("SELECT ".implode(", ",$kh)));}}echo'
<form action="" method="post">
';if($ve){echo"<table class='layout'>\n";foreach($ve
as$x){$l=$Ci["fields"][$x];$B=$l["field"];echo"<tr><th>".adminer()->fieldName($l);$Y=idx($_POST["fields"],$B);if($Y!=""){if($l["type"]=="set")$Y=implode(",",$Y);}input($l,$Y,idx($_POST["function"],$B,""));echo"\n";}echo"</table>\n";}echo'<p>
<input type=\'submit\' value=\'Call\'>
',input_token(),'</form>

',adminer()->commentValue($Fi,$Ci['comment']);}elseif(isset($_GET["foreign"])){$a=$_GET["foreign"];$B=$_GET["name"];$K=$_POST;if($_POST&&!$k&&!$_POST["add"]&&!$_POST["change"]&&!$_POST["change-js"]){if(!$_POST["drop"]){$K["source"]=array_filter($K["source"],'strlen');ksort($K["source"]);$Vj=array();foreach($K["source"]as$x=>$X)$Vj[$x]=$K["target"][$x];$K["target"]=$Vj;}if(JUSH=="sqlite")$I=recreate_table($a,$a,array(),array(),array(" $B"=>($K["drop"]?"":" ".format_foreign_key($K))));else{$qa="ALTER TABLE ".table($a);$I=($B==""||queries("$qa DROP ".(JUSH=="sql"?"FOREIGN KEY ":"CONSTRAINT ").idf_escape($B)));if(!$K["drop"])$I=queries("$qa ADD".format_foreign_key($K));}queries_redirect(ME."table=".url_escape($a),($K["drop"]?'Foreign key has been dropped.':($B!=""?'Foreign key has been altered.':'Foreign key has been created.')),$I);if(!$K["drop"])$k='Source and target columns must have the same data type, there must be an index on the target columns and the referenced data must exist.';}page_header(($B!=""?'Alter foreign key':'Create foreign key'),$k,array("table"=>$a),h($B!=""?$B:$a));if($_POST){ksort($K["source"]);if($_POST["change"]||$_POST["change-js"])$K["target"]=array();else$K["source"][]="";}elseif($B!=""){$_d=foreign_keys($a);$K=$_d[$B];$K["source"][]="";}else{$K["table"]=$a;$K["source"]=array("");}echo'
<form action="" method="post">
';$nj=array_keys(fields($a));if($K["db"]!="")connection()->select_db($K["db"]);if($K["ns"]!=""){$gh=get_schema();set_schema($K["ns"]);}$ni=array_keys(array_filter(table_status('',true),function(array$S){return!$S["dependent"]&&fk_support($S);}));$Vj=array_keys(fields(in_array($K["table"],$ni)?$K["table"]:reset($ni)));$b=on('change','foreignChange');echo"<p><label>".'Target table'.": ".html_select("table",$ni,$K["table"],$b)."</label>\n";if(JUSH!="sqlite"){$Ub=array();foreach(adminer()->databases()as$i){if(!information_schema($i))$Ub[]=$i;}echo"<label>".'DB'.": ".html_select("db",$Ub,$K["db"]!=""?$K["db"]:$_GET["db"],$b)."</label>";}echo
input_hidden("change-js"),'<noscript><p><input type=\'submit\' name=\'change\' value=\'Change\'></noscript>
<table>
<thead><tr><th id="label-source">Source<th id="label-target">Target<tbody>
';$Ze=0;foreach($K["source"]as$x=>$X){echo"<tr>","<td>".html_select("source[".(+$x)."]",array(-1=>"")+$nj,$X,($Ze==count($K["source"])-1?on('change','foreignAddRow'):""),"label-source"),"<td>".html_select("target[".(+$x)."]",$Vj,idx($K["target"],$x),"","label-target");$Ze++;}echo'</table>
<p>
<label>ON DELETE: ',html_select("on_delete",array(-1=>"")+explode("|",driver()->onActions),$K["on_delete"]),'</label>
<label>ON UPDATE: ',html_select("on_update",array(-1=>"")+explode("|",driver()->onActions),$K["on_update"]),'</label>
',(support("deferrable")?html_select("deferrable",array('NOT DEFERRABLE','DEFERRABLE','DEFERRABLE INITIALLY DEFERRED'),$K["deferrable"]).' ':''),doc_link(array('sql'=>"innodb-foreign-key-constraints.html",'mariadb'=>"foreign-keys/",)),'<p>
<input type=\'submit\' value=\'Save\'>
<noscript><p><input type=\'submit\' name=\'add\' value=\'Add column\'></noscript>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["view"])){$a=$_GET["view"];$K=$_POST;$hh="VIEW";if(JUSH=="pgsql"&&$a!=""){$P=table_status1($a);$hh=strtoupper($P["Engine"]);}if($_POST&&!$k){$B=trim($K["name"]);$xa=" AS\n$K[select]";$_=ME."table=".url_escape($B);$Xf='View has been altered.';$U=($_POST["materialized"]?"MATERIALIZED VIEW":"VIEW");if(!$_POST["drop"]&&$a==$B&&JUSH!="sqlite"&&$U=="VIEW"&&$hh=="VIEW")query_redirect((JUSH=="mssql"?"ALTER":"CREATE OR REPLACE")." VIEW ".table($B).$xa,$_,$Xf);else{$Zj="adminer_".uniqid();drop_create("DROP $hh ".table($a),"CREATE $U ".table($B).$xa,"DROP $U ".table($B),"CREATE $U ".table($Zj).$xa,"DROP $U ".table($Zj),($_POST["drop"]?substr(ME,0,-1):$_),'View has been dropped.',$Xf,'View has been created.',$a,$B);}}if(!$_POST&&$a!=""){$K=view($a);$K["name"]=$a;$K["materialized"]=($hh!="VIEW");if(!$k)$k=adminer()->error();}page_header(($a!=""?'Alter view':'Create view'),$k,array("table"=>$a),h($a));echo'
<form action="" method="post">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',(support("materializedview")?" ".checkbox("materialized",1,$K["materialized"],'Materialized view'):""),'<p>';textarea("select",$K["select"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($a!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$a)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["event"])){$aa=$_GET["event"];$Me=array("YEAR","QUARTER","MONTH","DAY","HOUR","MINUTE","WEEK","SECOND","YEAR_MONTH","DAY_HOUR","DAY_MINUTE","DAY_SECOND","HOUR_MINUTE","HOUR_SECOND","MINUTE_SECOND");$yj=array("ENABLED"=>"ENABLE","DISABLED"=>"DISABLE","SLAVESIDE_DISABLED"=>"DISABLE ON SLAVE");$K=$_POST;if($_POST&&!$k){if($_POST["drop"])query_redirect("DROP EVENT ".idf_escape($aa),substr(ME,0,-1),'Event has been dropped.');elseif(in_array($K["INTERVAL_FIELD"],$Me)&&isset($yj[$K["STATUS"]])){$Ii="\nON SCHEDULE ".($K["INTERVAL_VALUE"]?"EVERY ".q($K["INTERVAL_VALUE"])." $K[INTERVAL_FIELD]".($K["STARTS"]?" STARTS ".q($K["STARTS"]):"").($K["ENDS"]?" ENDS ".q($K["ENDS"]):""):"AT ".q($K["STARTS"]))." ON COMPLETION".($K["ON_COMPLETION"]?"":" NOT")." PRESERVE";queries_redirect(substr(ME,0,-1),($aa!=""?'Event has been altered.':'Event has been created.'),queries(($aa!=""?"ALTER EVENT ".idf_escape($aa).$Ii.($aa!=$K["EVENT_NAME"]?"\nRENAME TO ".idf_escape($K["EVENT_NAME"]):""):"CREATE EVENT ".idf_escape($K["EVENT_NAME"]).$Ii)."\n".$yj[$K["STATUS"]]." COMMENT ".q($K["EVENT_COMMENT"]).rtrim(" DO\n$K[EVENT_DEFINITION]",";").";"));}}page_header(($aa!=""?'Alter event'.": ".h($aa):'Create event'),$k);if(!$K&&$aa!=""){$L=get_rows("SELECT * FROM information_schema.EVENTS WHERE EVENT_SCHEMA = ".q(DB)." AND EVENT_NAME = ".q($aa));$K=reset($L);}echo'
<form action="" method="post">
<table class="layout">
<tr><th>Name<td><input name="EVENT_NAME" value="',h($K["EVENT_NAME"]),'" data-maxlength="64" autocapitalize="off">
<tr><th title="datetime">Start<td><input name="STARTS" value="',h("$K[EXECUTE_AT]$K[STARTS]"),'">
<tr><th title="datetime">End<td><input name="ENDS" value="',h($K["ENDS"]),'">
<tr><th>Every
<td><input type="number" name="INTERVAL_VALUE" value="',h($K["INTERVAL_VALUE"]),'" class="size"> ',html_select("INTERVAL_FIELD",$Me,$K["INTERVAL_FIELD"]),'<tr><th>Status<td>',html_select("STATUS",$yj,$K["STATUS"]),'<tr><th>Comment<td><input name="EVENT_COMMENT" value="',h($K["EVENT_COMMENT"]),'" data-maxlength="64">
<tr><th><td>',checkbox("ON_COMPLETION","PRESERVE",$K["ON_COMPLETION"]=="PRESERVE",'On completion preserve'),'</table>
<p>';textarea("EVENT_DEFINITION",$K["EVENT_DEFINITION"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($aa!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$aa)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["procedure"])){$ba=($_GET["name"]?:$_GET["procedure"]);$Ci=(isset($_GET["function"])?"FUNCTION":"PROCEDURE");$K=$_POST;$K["fields"]=(array)$K["fields"];if($_POST&&!process_fields($K["fields"])&&!$k){foreach($K["fields"]as$x=>$l){if($l["field"]=="")unset($K["fields"][$x]);}$Lg=routine_id($ba,routine($_GET["procedure"],$Ci));$vg=routine_id($K["name"],$K);$g=create_routine($Ci,$K);$_=substr(ME,0,-1);$Xf='Routine has been altered.';if(!$_POST["drop"]&&$Lg==$vg&&connection()->flavor!="mysql")query_redirect(substr_replace($g,' OR REPLACE',6,0),$_,$Xf);else{$Zj="adminer_".uniqid();drop_create("DROP $Ci $Lg",$g,"DROP $Ci $vg",create_routine($Ci,array("name"=>$Zj)+$K),"DROP $Ci ".routine_id($Zj,$K),$_,'Routine has been dropped.',$Xf,'Routine has been created.',$ba,$K["name"]);}}page_header(($ba!=""?(isset($_GET["function"])?'Alter function':'Alter procedure').": ".h($ba):(isset($_GET["function"])?'Create function':'Create procedure')),$k);if(!$_POST){if($ba=="")$K["language"]="sql";else{$K=routine($_GET["procedure"],$Ci);$K["name"]=$ba;}}$mb=(JUSH=="sql"?flat_collations():array());$Di=routine_languages();echo($mb?"<datalist id='collations'>".optionlist($mb)."</datalist>":""),'
<form action="" method="post" id="form">
<p>Name: <input name="name" value="',h($K["name"]),'" data-maxlength="64" autocapitalize="off">
',($Di?"<label>".'Language'.": ".html_select("language",array_keys($Di),$K["language"],on('change','routineLanguage',$Di))."</label>\n":""),'<input type=\'submit\' value=\'Save\'>
',doc_link(array('sql'=>"create-procedure.html",'mariadb'=>($Ci=="FUNCTION"?"create-function/":"create-procedure/"),),"?"),'<div class="scrollable">
<table id="edit-fields" class="nowrap">
';edit_fields($K["fields"],$mb,$Ci);if(isset($_GET["function"])){echo"<tr><td>".'Return type';edit_type("returns",(array)$K["returns"],$mb,array(),(JUSH=="pgsql"?array("void","trigger"):array()));}echo'</table>
',script("editFields();"),'</div>
<p>';textarea("definition",$K["definition"],20,80,($Di[$K["language"]]?:JUSH));echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($ba!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$ba)),'>
';$Ei=routine_options($Ci);if($Ei){$K["options"]=(array)$K["options"];$Xg=false;foreach($Ei
as$x=>$bl){$j=($bl?reset($bl):"");$K["options"][$x]=idx($K["options"],$x,$j);if($K["options"][$x]!=$j)$Xg=true;}print_fieldset("options",'Options',$Xg);echo"<table class='layout'>\n";foreach($Ei
as$x=>$bl){$gf="label-option-$x";$hk=str_replace("_"," ",$x);$M=array();foreach($bl
as$Y)$M[$Y]=(strpos($Y,"$hk ")===0?substr($Y,strlen($hk)+1):$Y);echo"<tr><th id='$gf'>$hk<td>".($M?html_select("options[$x]",$M,$K["options"][$x],"",$gf):"<input name='options[$x]' value='".h($K["options"][$x])."' aria-labelledby='$gf' autocapitalize='off'>")."\n";}echo"</table>\n</div></fieldset>\n";}echo
input_token(),'</form>
';}elseif(isset($_GET["check"])){$a=$_GET["check"];$B="$_GET[name]";$K=$_POST;if($K&&!$k){$_=ME."table=".url_escape($a);$ag='Check has been dropped.';$Yf='Check has been altered.';$Zf='Check has been created.';if(JUSH=="sqlite")queries_redirect($_,($K["drop"]?$ag:($B!=""?$Yf:$Zf)),recreate_table($a,$a,array(),array(),array(),"",array(),"$B",($K["drop"]?"":$K["clause"])));else{$qa="ALTER TABLE ".table($a);$ab=" CHECK ($K[clause])";$Zj="adminer_".uniqid();drop_create("$qa DROP CONSTRAINT ".idf_escape($B),"$qa ADD".($K["name"]!=""?" CONSTRAINT ".idf_escape($K["name"]):"").$ab,"$qa DROP CONSTRAINT ".idf_escape($K["name"]),"$qa ADD CONSTRAINT ".idf_escape($Zj).$ab,"$qa DROP CONSTRAINT ".idf_escape($Zj),$_,$ag,$Yf,$Zf,$B,$K["name"]);}}page_header(($B!=""?'Alter check':'Create check'),$k,array("table"=>$a),h($B!=""?$B:$a));if(!$K){$db=driver()->checkConstraints($a);$K=array("name"=>$B,"clause"=>$db[$B]);}echo'
<form action="" method="post">
<p>';if(JUSH!="sqlite")echo'Name'.': <input name="name" value="'.h($K["name"]).'" data-maxlength="64" autocapitalize="off"> ';echo
doc_link(array('sql'=>"create-table-check-constraints.html",'mariadb'=>"constraint/",),"?"),'<p>';textarea("clause",$K["clause"]);echo'<p><input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["trigger"])){$a=$_GET["trigger"];$B="$_GET[name]";$wk=trigger_options();$K=(array)trigger($B,$a)+array("Trigger"=>$a."_bi");if($_POST){if(!$k&&in_array($_POST["Timing"],$wk["Timing"])&&in_array($_POST["Event"],$wk["Event"])&&in_array($_POST["Type"],$wk["Type"])){$Og=" ON ".table($a);$wc="DROP TRIGGER ".idf_escape($B).(JUSH=="pgsql"?$Og:"");$_=ME."table=".url_escape($a);if($_POST["drop"])query_redirect($wc,$_,'Trigger has been dropped.');else{if($B!="")queries($wc);queries_redirect($_,($B!=""?'Trigger has been altered.':'Trigger has been created.'),queries(create_trigger($Og,$_POST)));if($B!="")queries(create_trigger($Og,$K+array("Type"=>reset($wk["Type"]))));}}$K=$_POST;}page_header(($B!=""?'Alter trigger':'Create trigger'),$k,array("table"=>$a),h($B!=""?$B:$a));$vk=on('change','triggerChange',"^".preg_quote($a,"/")."_[ba][iud]$",$a);echo'
<form action="" method="post" id="form">
<table class="layout">
<tr><th>Time
<td>',html_select("Timing",$wk["Timing"],$K["Timing"],$vk),'<tr><th>Event<td>',html_select("Event",$wk["Event"],$K["Event"],$vk),(in_array("UPDATE OF",$wk["Event"])?" <input name='Of' value='".h($K["Of"])."' class='hidden'>":""),'<tr><th>Type<td>',html_select("Type",$wk["Type"],$K["Type"]),'<tr><th>Name<td><input name="Trigger" value="',h($K["Trigger"]),'" data-maxlength="64" autocapitalize="off">
</table>
',script("fire(qs('#form')['Timing'], 'change');"),'<p>';textarea("Statement",$K["Statement"]);echo'<p>
<input type=\'submit\' value=\'Save\'>
';if($B!="")echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',$B)),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["user"])){function
grant($Md,array$bi,$d,$Og){if(!$bi)return
true;if($bi==array("ALL PRIVILEGES","GRANT OPTION"))return($Md=="GRANT"?queries("$Md ALL PRIVILEGES$Og WITH GRANT OPTION"):queries("$Md ALL PRIVILEGES$Og")&&queries("$Md GRANT OPTION$Og"));return
queries("$Md ".preg_replace('~(GRANT OPTION)\([^)]*\)~','\1',implode("$d, ",$bi).$d).$Og);}$da=$_GET["user"];$bi=array(""=>array("All privileges"=>""));foreach(get_rows("SHOW PRIVILEGES")as$K){foreach(explode(",",($K["Privilege"]=="Grant option"?"":$K["Context"]))as$_b)$bi[$_b=="File access on server"?"Server Admin":$_b][$K["Privilege"]]=$K["Comment"];}unset($bi["Server Admin"]["Usage"]);foreach($bi["Tables"]as$x=>$X)unset($bi["Databases"][$x]);$ug=array();if($_POST){foreach($_POST["objects"]as$x=>$X)$ug[$X]=(array)$ug[$X]+idx($_POST["grants"],$x,array());}$Nd=array();if(isset($_GET["host"])&&($I=connection()->query("SHOW GRANTS FOR ".q($da)."@".q($_GET["host"])))){while($K=$I->fetch_row()){if(preg_match('~GRANT (.*) ON (.*) TO ~',$K[0],$A)&&preg_match_all('~ *([^(,]*[^ ,(])( *\([^)]+\))?~',$A[1],$Ff,PREG_SET_ORDER)){foreach($Ff
as$X){if($X[1]!="USAGE")$Nd["$A[2]$X[2]"][$X[1]]=true;if(preg_match('~ WITH GRANT OPTION~',$K[0]))$Nd["$A[2]$X[2]"]["GRANT OPTION"]=true;}}}}if($_POST&&!$k){$Ng=(isset($_GET["host"])?q($da)."@".q($_GET["host"]):"''");if($_POST["drop"])query_redirect("DROP USER $Ng",ME."privileges=",'User has been dropped.');else{$xg=q($_POST["user"])."@".q($_POST["host"]);$_h=$_POST["pass"];$Gb=false;$I=true;if($Ng!=$xg){$Gb=queries("CREATE USER $xg IDENTIFIED BY ".($_POST["hashed"]?"PASSWORD ":"").q($_h));$I=$Gb;}elseif($_h!="")$I=queries("SET PASSWORD FOR $xg = ".(min_version(8,99)||$_POST["hashed"]?q($_h):"PASSWORD(".q($_h).")"));if($I){$zi=array();foreach($ug
as$Dg=>$Md){if(isset($_GET["grant"]))$Md=array_filter($Md);$Md=array_keys($Md);if(isset($_GET["grant"]))$zi=array_diff(array_keys(array_filter($ug[$Dg],'strlen')),$Md);elseif($Ng==$xg){$Kg=array_keys((array)$Nd[$Dg]);$zi=array_diff($Kg,$Md);$Md=array_diff($Md,$Kg);unset($Nd[$Dg]);}if(preg_match('~^(.+)\s*(\(.*\))?$~U',$Dg,$A)&&(!grant("REVOKE",$zi,$A[2]," ON $A[1] FROM $xg")||!grant("GRANT",$Md,$A[2]," ON $A[1] TO $xg"))){$I=false;break;}}}if($I&&isset($_GET["host"])){if($Ng!=$xg)queries("DROP USER $Ng");elseif(!isset($_GET["grant"])){foreach($Nd
as$Dg=>$zi){if(preg_match('~^(.+)(\(.*\))?$~U',$Dg,$A))grant("REVOKE",array_keys($zi),$A[2]," ON $A[1] FROM $xg");}}}if($I&&!Queries::$queries)redirect(ME."privileges=");queries_redirect(ME."privileges=",(isset($_GET["host"])?'User has been altered.':'User has been created.'),$I);if($Gb)connection()->query("DROP USER $xg");}}page_header((isset($_GET["host"])?'Username'.": ".h("$da@$_GET[host]"):'Create user'),$k,array("privileges"=>array('','Privileges')));$K=$_POST;if($K)$Nd=$ug;else{$K=$_GET+array("host"=>get_val("SELECT SUBSTRING_INDEX(CURRENT_USER, '@', -1)"));$Nd[(DB==""||$Nd?"":idf_escape(addcslashes(DB,"%_\\"))).".*"]=array();}echo'<form action="" method="post">
<table class="layout">
<tr><th>Server<td><input name="host" data-maxlength="60" value="',h($K["host"]),'" autocapitalize="off">
<tr><th>Username<td><input name="user" data-maxlength="80" value="',h($K["user"]),'" autocapitalize="off">
<tr><th>Password<td><input name="pass" id="pass" value="',h($K["pass"]),'" autocomplete="new-password">
',($K["hashed"]?"":script("typePassword(qs('#pass'));")),(min_version(8,99)?"":checkbox("hashed",1,$K["hashed"],'Hashed',on('click','hashedClick'))),'</table>

',"<table class='odds'>\n","<thead><tr><th colspan='2'>".'Privileges'.doc_link(array('sql'=>"grant.html#priv_level"));$s=0;foreach($Nd
as$Dg=>$Md){echo'<th>'.($Dg!="*.*"?"<input name='objects[$s]' value='".h($Dg)."' size='10' autocapitalize='off'>":input_hidden("objects[$s]","*.*")."*.*");$s++;}echo"<tbody>\n";foreach(array(""=>"","Server Admin"=>'Server',"Databases"=>'Database',"Tables"=>'Table',"Procedures"=>'Routine',)as$_b=>$dc){foreach((array)$bi[$_b]as$ai=>$qb){echo"<tr><td".($dc?">$dc<td":" colspan='2'").' lang="en" title="'.h($qb).'">'.h($ai);$s=0;foreach($Nd
as$Dg=>$Md){$B="'grants[$s][".h(strtoupper($ai))."]'";$Y=$Md[strtoupper($ai)];if($_b=="Server Admin"&&$Dg!=(isset($Nd["*.*"])?"*.*":".*"))echo"<td>";elseif(isset($_GET["grant"]))echo"<td><select name=$B><option><option value='1'".($Y?" selected":"").">".'Grant'."<option value='0'".($Y=="0"?" selected":"").">".'Revoke'."</select>";else
echo"<td align='center'><label class='block'>","<input type='checkbox' name=$B value='1'".($Y?" checked":"").($ai=="All privileges"?" id='grants-$s-all'":($ai=="Grant option"?"":on('click','grantsClick',"grants-$s-all"))).">","</label>";$s++;}}}echo"</table>\n",'<p>
<input type=\'submit\' value=\'Save\'>
';if(isset($_GET["host"]))echo'<input type=\'submit\' name=\'drop\' value=\'Drop\'',confirm(sprintf('Drop %s?',"$da@$_GET[host]")),'>
';echo
input_token(),'</form>
';}elseif(isset($_GET["processlist"])){if(support("kill")){if($_POST&&!$k){$ff=0;foreach((array)$_POST["kill"]as$X){if(adminer()->killProcess($X))$ff++;}queries_redirect(ME."processlist=",lang_format(array('%d process has been killed.','%d processes have been killed.'),$ff),$ff||!$_POST["kill"]);}}page_header('Process list',$k);echo'
<form action="" method="post">
<div class="scrollable">
<table class="nowrap checkable odds"',on('click','tableClick').on('dblclick','tableClick'),'>
';$s=-1;foreach(adminer()->processList()as$s=>$K){if(!$s){echo"<thead><tr lang='en'>".(support("kill")?"<td class='hover'>":"");foreach($K
as$x=>$X)echo"<th>$x".doc_link(array('sql'=>"show-processlist.html#processlist_".strtolower($x),));echo"<tbody>\n";}echo"<tr>".(support("kill")?"<td class='hover'>".checkbox("kill[]",$K[JUSH=="sql"?"Id":"pid"],0):"");foreach($K
as$x=>$X)echo"<td>".($X!=""&&((JUSH=="sql"&&$x=="Info"&&preg_match("~Query|Killed~",$K["Command"]))||(JUSH=="pgsql"&&$x=="query")||(JUSH=="oracle"&&$x=="sql_text"))?"<code class='jush-".JUSH."' data-full='".h($X)."'>".shorten_utf8($X,100,"</code>").' <a href="'.h(($K["db"]!=""?preg_replace('~&db=[^&]*~','',ME)."db=".url_escape($K["db"])."&":ME)."sql=".url_escape($X)).'">'.'Clone'.'</a>'.' '.copy_icon():h($X));echo"\n";}echo'</table>
</div>
<p>
',script("copyCode(qsl('table'));");if(support("kill"))echo
format_number($s+1)."/".sprintf('%d in total',max_connections()),"<p><input type='submit' value='".'Kill'."'>\n";echo
input_token(),'</form>
',script("tableCheck();");}elseif($_GET["select"]!=""){$a=$_GET["select"];$S=table_status1($a);$w=indexes($a);$m=fields($a);$_d=column_foreign_keys($a);$Jg=$S["Oid"];$Ai=array();$d=array();$Ni=array();$Zg=array();$ck=null;foreach($m
as$x=>$l){$B=adminer()->fieldName($l);$rg=html_entity_decode(strip_tags($B),ENT_QUOTES);if(isset($l["privileges"]["select"])&&$B!=""){$d[$x]=$rg;if(is_shortable($l))$ck=adminer()->selectLengthProcess();}if(isset($l["privileges"]["where"])&&$B!="")$Ni[$x]=$rg;if(isset($l["privileges"]["order"])&&$B!="")$Zg[$x]=$rg;$Ai+=$l["privileges"];}list($M,$r)=adminer()->selectColumnsProcess($d,$w);$M=array_unique($M);$r=array_unique($r);$Te=count($r)<count($M);$Z=adminer()->selectSearchProcess($m,$w,$S);$D=adminer()->selectOrderProcess($m,$w);$y=adminer()->selectLimitProcess();if($_GET["val"]&&is_ajax()){header("Content-Type: text/plain; charset=utf-8");foreach($_GET["val"]as$Gk=>$K){$xa=convert_field($m[key($K)]);$M=array($xa?:idf_escape(key($K)));$Z[]=where_check(bracket_escape($Gk,true),$m);$J=driver()->select($a,$M,$Z,$M);if($J)echo
first($J->fetch_row());}exit;}$Xh=$Ik=array();foreach($w
as$v){if($v["type"]=="PRIMARY"){$Xh=array_flip($v["columns"]);$Ik=($M?$Xh:array());foreach($Ik
as$x=>$X){if(in_array(idf_escape($x),$M))unset($Ik[$x]);}break;}}if($Jg&&!$Xh){$Xh=$Ik=array($Jg=>0);$w[]=array("type"=>"PRIMARY","columns"=>array($Jg));}if($_POST&&!$k){$ml=$Z;if(!$_POST["all"]&&is_array($_POST["check"])){$db=array();foreach($_POST["check"]as$ab)$db[]=where_check($ab,$m);$ml[]="((".implode(") OR (",$db)."))";}$ol=$ml;$ml=($ml?"\nWHERE ".implode(" AND ",$ml):"");if($_POST["export"]){save_settings(array("output"=>$_POST["output"],"format"=>$_POST["format"]),"adminer_import");dump_headers($a);adminer()->dumpTable($a,"");$Pi=($M?:array("*"));$Bb=convert_fields($d,$m,$M);if($Bb)$Pi[]=substr($Bb,2);$H="";if(is_array($_POST["check"])&&!$Xh){$Ed=implode(", ",$Pi)."\nFROM ".table($a);$Qd=($r&&$Te?"\nGROUP BY ".implode(", ",$r):"").($D?"\nORDER BY ".implode(", ",$D):"");$Ek=array();foreach($_POST["check"]as$X)$Ek[]="(SELECT".limit($Ed,"\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$m).$Qd,1).")";$H=implode(" UNION ALL ",$Ek);}adminer()->dumpData($a,"table",$H,$Pi,$ol,($Te?$r:array()),$D);adminer()->dumpFooter();exit;}if(!adminer()->selectEmailProcess($Z,$_d)){if($_POST["save"]||$_POST["delete"]){$I=true;$la=0;$La=false;$O=array();if(!$_POST["delete"]){foreach($m
as$B=>$X){$u=bracket_escape($B);if(isset($_POST["fields"][$u])||$_FILES["fields-$u"]){$X=process_input($m[$B]);if($X!==null&&($_POST["clone"]||$X!==false))$O[idf_escape($B)]=($X!==false?$X:idf_escape($B));}}}if($_POST["delete"]||$O){$H=($_POST["clone"]?"INTO ".table($a)." (".implode(", ",array_keys($O)).")\nSELECT ".implode(", ",$O)."\nFROM ".table($a):"");if($_POST["all"]||($Xh&&is_array($_POST["check"]))||$Te){$I=($_POST["delete"]?driver()->delete($a,$ml):($_POST["clone"]?queries("INSERT $H$ml".driver()->insertReturning($a)):driver()->update($a,$O,$ml)));$la=connection()->affected_rows;if(is_object($I))$la+=$I->num_rows;}else{$La=count((array)$_POST["check"])>1&&driver()->begin();foreach((array)$_POST["check"]as$X){$ll="\nWHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check($X,$m);$I=($_POST["delete"]?driver()->delete($a,$ll,1):($_POST["clone"]?queries("INSERT".limit1($a,$H,$ll)):driver()->update($a,$O,$ll,1)));if(!$I)break;$la+=connection()->affected_rows;}if($La&&$I&&!driver()->commit())$I=false;}}$Xf=lang_format(array('%d item has been affected.','%d items have been affected.'),$la);if($_POST["clone"]&&$I&&$la==1){$mf=last_id($I);if($mf)$Xf=sprintf('Item%s has been inserted.'," $mf");}queries_redirect(remove_from_uri($_POST["all"]&&$_POST["delete"]?"page|next":""),$Xf,$I);if($La)driver()->rollback();if(!$_POST["delete"]){$Ph=(array)$_POST["fields"];edit_form($a,array_intersect_key($m,$Ph),$Ph,!$_POST["clone"],$k);page_footer();exit;}}elseif(!$_POST["import"]){$I=true;$la=0;$La=count((array)$_POST["val"])>1&&driver()->begin();foreach((array)$_POST["val"]as$Gk=>$K){$O=array();foreach($K
as$x=>$X){$x=bracket_escape($x,true);$O[idf_escape($x)]=(preg_match('~char|text~',$m[$x]["type"])||$X!=""?adminer()->processInput($m[$x],$X):"NULL");}$I=driver()->update($a,$O," WHERE ".($Z?implode(" AND ",$Z)." AND ":"").where_check(bracket_escape($Gk,true),$m),($Te||$Xh?0:1)," ");if(!$I)break;$la+=connection()->affected_rows;}if($La)$I=$I&&driver()->commit();queries_redirect(remove_from_uri(),lang_format(array('%d item has been affected.','%d items have been affected.'),$la),$I);if($La)driver()->rollback();}else{save_settings(array("format"=>$_POST["separator"]),"adminer_import");$nd=get_file("csv_file",true);if(!is_string($nd))$k=upload_error($nd);elseif(!preg_match('~~u',$nd))$k='File must be in UTF-8 encoding.';else{$nb=array_keys($m);$Ui=($_POST["separator"]=="csv"?",":($_POST["separator"]=="tsv"?"\t":";"));$Kb=parse_csv($nd,$Ui);$la=count($Kb);driver()->begin();$L=array();foreach($Kb
as$x=>$bl){if(!$x&&!array_diff($bl,$nb)){$nb=$bl;$la--;}else{$O=array();foreach($bl
as$s=>$jb)$O[idf_escape($nb[$s])]=($jb==""&&$m[$nb[$s]]["null"]?"NULL":q(csv_value($jb)));$L[]=$O;}}$I=(!$L||driver()->insertUpdate($a,$L,$Xh));if($I)driver()->commit();queries_redirect(remove_from_uri("page|next"),lang_format(array('%d row has been imported.','%d rows have been imported.'),$la),$I);driver()->rollback();}}}}$Kj=adminer()->tableName($S);if(is_ajax()){page_headers();ob_start();}else
page_header('Select'.": $Kj",$k);$O=null;if(isset($Ai["insert"])||!support("table")){$O="";foreach((array)$_GET["where"]as$X){$Y=$X["val"];if(is_array($Y))$Y=(count($Y)==1&&preg_match('~^val-(.*)~s',reset($Y),$A)?$A[1]:"");if($X["col"]!=""&&$Y!=""&&($X["op"]=="="||(!$X["op"]&&(is_array($X["val"])||!preg_match('~[_%]~',$Y)))))$O
.="&set[".url_escape(bracket_escape($X["col"]))."]=".url_escape($Y);}}adminer()->selectLinks($S,$O);if(!$d&&support("table"))echo"<p class='error'>".'Unable to select the table'.($m?".":": ".adminer()->error())."\n";else{echo"<form action='' id='form'>\n","<div hidden>";hidden_fields_get();echo(DB!=""?input_hidden("db",DB).(isset($_GET["ns"])?input_hidden("ns",$_GET["ns"]):""):""),input_hidden("select",$a),"</div>\n";adminer()->selectColumnsPrint($M,$d);adminer()->selectSearchPrint($Z,$Ni,$w,$S);adminer()->selectOrderPrint($D,$Zg,$w);adminer()->selectLimitPrint($y);if($ck!==null)adminer()->selectLengthPrint($ck);adminer()->selectActionPrint($w);echo"</form>\n";foreach((array)$_GET["where"]as$X){if($X["op"]=="SQL"&&!in_array($_SERVER["HTTP_SEC_FETCH_SITE"],array("","same-origin"))){echo"<p class='error'>".'Invalid CSRF token. Submit the form again.'.' '.'If you did not send this request from Adminer, close this page.'."\n";page_footer();exit;}}$E=$_GET["page"];$Cd=null;if($E=="last"){$Cd=get_val(count_rows($a,$Z,$Te,$r));$E=floor(max(0,intval($Cd)-1)/$y);}$Oi=$M;$Pd=$r;if(!$Oi){$Oi[]="*";$Bb=convert_fields($d,$m,$M);if($Bb)$Oi[]=substr($Bb,2);}foreach($M
as$x=>$X){$l=$m[idf_unescape($X)];if($l&&($xa=convert_field($l)))$Oi[$x]="$xa AS $X";}if(JUSH=="pgsql"||JUSH=="mssql"){foreach((array)$_GET["columns"]as$x=>$X){if(isset($Oi[$x])&&$X["fun"])$Oi[$x].=" AS ".idf_escape(apply_sql_function($X["fun"],($X["col"]!=""?$X["col"]:"*")));}}if(!$Te&&$Ik){foreach($Ik
as$x=>$X){$Oi[]=idf_escape($x);if($Pd)$Pd[]=idf_escape($x);}}$I=driver()->select($a,$Oi,$Z,$Pd,$D,$y,$E,true);if(!is_object($I))echo"<p class='error'>".(adminer()->error()?:'Unknown error.')."\n";else{if(JUSH=="mssql"&&$E)$I->seek($y*$E);$Fc=array();$L=array();while($K=$I->fetch_assoc()){if($E&&JUSH=="oracle")unset($K["RNUM"]);$L[]=$K;}$ae=($y&&(support("cursor")?$_GET["next"]!="":count($L)>=$y));if(is_ajax()&&$ae)header("X-Next-Page: ".pagination_href($E+1));if($_GET["modify"]&&$L){$Of=max_input_vars(count($L[0])+1,20);echo($Of&&count($L)>$Of?"<p class='error'>".max_input_vars_error()."\n":"");}echo"<form action='' method='post' enctype='multipart/form-data'".on_upload_progress($Nk).">\n";if($_GET["page"]!="last"&&$y&&$r&&$Te&&JUSH=="sql")$Cd=get_val(" SELECT FOUND_ROWS()");if(!$L)echo"<p class='message'>".'No rows.'."\n";else{$Ha=adminer()->backwardKeys($a,$Kj);echo"<div class='scrollable'>","<table id='table' class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').on('keydown','editingKeydown').">\n","<thead><tr>".(!$r&&$M?"":"<td class='hover check'><input type='checkbox' id='all-page' class='jsonly' title='".'All rows on this page'."'".on('click','formCheck','^check').">");$sg=array();$Jd=array();reset($M);$ki=1;foreach($L[0]as$x=>$X){if(!isset($Ik[$x])){$X=idx($_GET["columns"],key($M))?:array();$l=$m[$M?($X?$X["col"]:current($M)):$x];$B=($l?adminer()->fieldName($l,$ki):($X["fun"]?"*":h($x)));if($B!=""){$ki++;$sg[$x]=$B;$c=idf_escape($x);$ne=remove_from_uri('(order|desc)[^=]*|page|next').'&order[0]='.url_escape($x);$dc="&desc[0]=1";$kj=preg_replace('~ DESC( NULLS LAST)?$~','',$D[0]);$mj=($kj==$c||$kj==$x);echo"<th id='th[".h(bracket_escape($x))."]'".($mj?" aria-sort='".($kj==$D[0]?"ascending":"descending")."'":"").">";$Id=apply_sql_function($X["fun"],$B);$lj=isset($l["privileges"]["order"])||$Id!=$B;echo($lj?"<a href='".h($ne.($mj&&$kj==$D[0]?$dc:''))."'>$Id</a>":$Id);$Wf=($lj?"<a href='".h($ne.$dc)."' title='".'descending'."' class='text'> ↓</a>":'');if(!$X["fun"]&&isset($l["privileges"]["where"]))$Wf
.="<a href='#fieldset-search' title='".'Search'."' class='text jsonly'".on('click','selectSearch',$x)."> =</a>";echo($Wf?"<span class='column'>$Wf</span>":"");}$Jd[$x]=$X["fun"];next($M);}}$uf=array();if($_GET["modify"]){foreach($L
as$K){foreach($K
as$x=>$X)$uf[$x]=max($uf[$x],min(40,strlen(utf8_decode($X))));}}echo($Ha?"<th>".'Relations':"")."<tbody>\n";if(is_ajax())ob_end_clean();foreach(adminer()->rowDescriptions($L,$_d)as$pg=>$K){$Fk=unique_array($L[$pg],$w);if(!$Fk){$Fk=array();reset($M);foreach($L[$pg]as$x=>$X){if(!preg_match('~^(COUNT|AVG|GROUP_CONCAT|MAX|MIN|SUM)\(~',current($M)))$Fk[$x]=$X;next($M);}}$Gk="";foreach($Fk
as$x=>$X){$l=(array)$m[$x];$Se=is_blob($l);if((JUSH=="sql"||JUSH=="pgsql")&&($Se||preg_match('~'.text_type().'~',$l["type"]))&&strlen($X)>64){$x=(strpos($x,'(')?$x:idf_escape($x));$x="MD5(".($Se||JUSH!='sql'||preg_match("~^utf8~",$l["collation"])?$x:"CONVERT($x USING ".charset(connection()).")").")";$X=md5($Se?(string)driver()->value($X,$l):$X);}$Gk
.="&".($X!==null?"where[".url_escape(bracket_escape($x))."]=".url_escape($X===false?"f":$X):"null[]=".url_escape($x));}echo"<tr>".(!$r&&$M?"":"<td class='hover check'>".($Te||information_schema(DB)?"":"<a href='".h(ME."edit=".url_escape($a).$Gk)."' class='edit'>".'edit'."</a> ").checkbox("check[]",substr($Gk,1),in_array(substr($Gk,1),(array)$_POST["check"])));reset($M);foreach($K
as$x=>$X){if(isset($sg[$x])){$c=current($M);$l=(array)$m[$x];if($X!=""&&(!isset($Fc[$x])||$Fc[$x]!=""))$Fc[$x]=(is_mail($X)?$sg[$x]:"");$z="";if(is_blob($l)&&$X!="")$z=ME.'download='.url_escape($a).'&field='.url_escape($x).$Gk;if(!$z&&$X!==null){foreach((array)$_d[$x]as$o){if(count($_d[$x])==1||end($o["source"])==$x){$z="";foreach($o["source"]as$s=>$nj)$z
.=where_link($s,$o["target"][$s],$L[$pg][$nj]);$z=($o["db"]!=""?preg_replace('~([?&]db=)[^&]+~','\1'.url_escape($o["db"]),ME):ME).'select='.url_escape($o["table"]).$z;if($o["ns"])$z=preg_replace('~([?&]ns=)[^&]+~','\1'.url_escape($o["ns"]),$z);if(count($o["source"])==1)break;}}}if($c=="COUNT(*)"){$z=ME."select=".url_escape($a);$s=0;foreach((array)$_GET["where"]as$W){if(!array_key_exists($W["col"],$Fk))$z
.=where_link($s++,$W["col"],$W["val"],$W["op"]);}foreach($Fk
as$cf=>$W)$z
.=where_link($s++,$cf,$W);}$oe=select_value($X,$z,$l,$ck);$u=bracket_escape($Gk);$t=h("val[$u][".bracket_escape($x)."]");$Rh=idx(idx($_POST["val"],$u),bracket_escape($x));$Lk=idx($l["privileges"],"update");$Bc=!is_array($K[$x])&&!is_blob($l)&&is_utf8($X)&&$L[$pg][$x]==$X&&!$Jd[$x]&&!$l["generated"]&&$Lk;$U=(preg_match('~^(AVG|MIN|MAX)\((.+)\)~',$c,$A)?$m[idf_unescape($A[2])]["type"]:$l["type"]);$bk=preg_match('~text|json|lob~',$U);$Ue=preg_match(number_type(),$U)||preg_match('~^(CHAR_LENGTH|ROUND|FLOOR|CEIL|TIME_TO_SEC|COUNT|SUM)\(~',$c);echo"<td id='$t'".($Ue&&($X===null||is_numeric(strip_tags($oe))||$U=="money")?" class='number'":"");if(($_GET["modify"]&&$Bc&&$X!==null)||$Rh!==null){$Vd=h($Rh!==null?$Rh:$X);echo">".($bk?"<textarea name='$t' cols='30' rows='".(substr_count($X,"\n")+1)."'>$Vd</textarea>":"<input name='$t' value='$Vd' size='$uf[$x]'>");}else{$Cf=strpos($oe,"<i>…</i>");echo($Lk?" data-text='".($Cf?2:($bk?1:0))."'".($Bc?"":" data-warning='".'Use the edit link to modify this value.'."'"):"").">$oe";}}next($M);}if($Ha)echo"<td>";adminer()->backwardKeysPrint($Ha,$L[$pg]);echo"</tr>\n";}if(is_ajax())exit;echo"</table>\n","</div>\n";}if(!is_ajax()){$ka=get_settings("adminer_import");if($L||$E||$ae){$Uc=true;if($_GET["page"]!="last"){if(!$y||(count($L)<$y&&($L||!$E)))$Cd=($E?$E*$y:0)+count($L);elseif(JUSH!="sql"||!$Te){$Cd=($Te?false:found_rows($S,$Z));if(intval($Cd)<max(1e4,2*($E+1)*$y))$Cd=first(slow_query(count_rows($a,$Z,$Te,$r)));elseif(JUSH=='sql'||JUSH=='pgsql')$Uc=false;}}if(!support("cursor"))$ae=(($Cd===false?count($L)+1:$Cd-$E*$y)>$y);$nh=($y&&($ae||$E));if($nh)echo($ae?'<p><a href="'.h(pagination_href($E+1)).'" class="loadmore"'.on('click','selectLoadMore','Loading…').'>'.'Load more data'.'</a>':''),"\n";echo"<div class='footer'><div>\n";if($nh){$Mf=($Cd===false?$E+($L?(count($L)>=$y?2:1):0):floor(($Cd-1)/$y));echo"<fieldset><legend>".'Page'."</legend>";if(!support("cursor")){echo
pagination(0,$E).($E>5?" …":"");for($s=max(1,$E-4);$s<min($Mf,$E+5);$s++)echo
pagination($s,$E);if($Mf>0)echo($E+5<$Mf?" …":""),($Uc&&$Cd!==false?pagination($Mf,$E):" <a href='".h(remove_from_uri("page")."&page=last")."' title='~$Mf'>".'last'."</a>");}else
echo
pagination(0,$E).($E>1?" …":""),($E?pagination($E,$E):""),($ae?pagination($E+1,$E)." …":"");echo"</fieldset>\n";}echo"<fieldset>","<legend>".'Whole result'."</legend>";$lc=($Uc?"":"~ ").$Cd;$gf=($Cd!==false?($Uc?"":"~ ").lang_format(array('%d row','%d rows'),$Cd):"");echo
checkbox("all",1,0,$gf,on('click','countRows',$lc))."\n","</fieldset>\n";if(adminer()->selectCommandPrint())echo'<fieldset',($_GET["modify"]?'':" title='".'Ctrl+click on a value to modify it.'."'"),'>
<legend><a href=\'',h($_GET["modify"]?remove_from_uri("modify"):relative_uri()."&modify=1"),'\'>Modify</a></legend><div>
<input type=\'submit\' id=\'save\' value=\'Save\'',($_GET["modify"]?'':" class='jsonly' disabled"),'>
</div></fieldset>

<fieldset><legend>Selected <span id="selected"></span></legend><div>
<input type=\'submit\' name=\'edit\' value=\'Edit\'>
<input type=\'submit\' name=\'clone\' value=\'Clone\'>
<input type=\'submit\' name=\'delete\' value=\'Delete\'',confirm(),'>
</div></fieldset>
';$Ad=adminer()->dumpFormat();foreach((array)$_GET["columns"]as$c){if($c["fun"]){unset($Ad['sql']);break;}}if($Ad){print_fieldset("export",'Export'." <span id='selected2'></span>");$lh=adminer()->dumpOutput();echo($lh?html_select("output",$lh,$ka["output"])." ":""),html_select("format",$Ad,$ka["format"])," <input type='submit' name='export' value='".'Export'."'>\n","</div></fieldset>\n";}adminer()->selectEmailPrint(array_filter($Fc,'strlen'),$d);echo"</div></div>\n";}if(adminer()->selectImportPrint())echo"<p>","<a href='#import' class='toggle'>".'Import'."</a>","<span id='import'".($_POST["import"]?"":" class='hidden'").">: ",($Nk?input_hidden(ini_get("session.upload_progress.name"),$Nk):""),file_input(" name='csv_file'"," ".html_select("separator",array("csv"=>"CSV,","csv;"=>"CSV;","tsv"=>"TSV"),$ka["format"])." <input type='submit' name='import' value='".'Import'."'>".($Nk?" <progress class='jsonly hidden' max='1' value='0'></progress>":"")),"</span>";echo
input_token(),"</form>\n",(!$r&&$M?"":script("tableCheck();"));}}}if(is_ajax()){ob_end_clean();exit;}}elseif(isset($_GET["variables"])){$P=isset($_GET["status"]);page_header($P?'Status':'Variables');$cl=($P?adminer()->showStatus():adminer()->showVariables());if(!$cl)echo"<p class='message'>".'No rows.'."\n";else{echo"<table>\n";foreach($cl
as$K){echo"<tr>";$x=array_shift($K);echo"<th><code class='jush-".JUSH.($P?"status":"set")."'>".h($x)."</code>";foreach($K
as$X)echo"<td>".nl_br(h($X));}echo"</table>\n";}}elseif(isset($_GET["script"])){header("Content-Type: application/json; charset=utf-8");if($_GET["script"]=="db"){$Ej=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach(table_status()as$B=>$S){json_row("Comment-$B",h($S["Comment"]).($S["Error"]?" <span class='error'>".h($S["Error"])."</span>":""));if(!is_view($S)||preg_match('~materialized~i',$S["Engine"])){foreach(array("Engine","Collation")as$x)json_row("$x-$B",h($S[$x]));foreach(array_keys($Ej+array("Auto_increment"=>0,"Rows"=>0))as$x){if(array_key_exists($x,$S))json_row("$x-$B",format_status($S,$x));if($S[$x]!=""&&isset($Ej[$x]))$Ej[$x]+=($S["Engine"]!="InnoDB"||$x!="Data_free"?$S[$x]:0);}}}if(function_exists('Adminer\db_status'))$Ej=db_status();foreach($Ej
as$x=>$X)json_row("sum-$x",format_number($X));json_row("");}elseif($_GET["script"]=="kill"){if(!$k)connection()->query("KILL ".number($_POST["kill"]));}else{foreach(count_tables(adminer()->databases(false))as$i=>$X){json_row("tables-$i",format_number($X));json_row("size-$i",db_size($i));}json_row("");}exit;}else{if(!isset($_GET["select"])&&support("single_table")){$T=tables_list();if($T)redirect(ME.(support("table")?"table=":"select=").url_escape(key($T)));}$Tf=ME.(isset($_GET["select"])?"select=&":"");$Tj=array_merge((array)$_POST["tables"],(array)$_POST["views"]);if($Tj&&!$k&&!$_POST["search"]){$I=true;$Xf="";if(JUSH=="sql"&&$_POST["tables"]&&count($_POST["tables"])>1&&($_POST["drop"]||$_POST["truncate"]||$_POST["copy"]))queries("SET foreign_key_checks = 0");if($_POST["truncate"]){if($_POST["tables"])$I=truncate_tables($_POST["tables"]);$Xf='Tables have been truncated.';}elseif($_POST["move"]){$I=move_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$Xf='Tables have been moved.';}elseif($_POST["copy"]){$I=copy_tables((array)$_POST["tables"],(array)$_POST["views"],$_POST["target"]);$Xf='Tables have been copied.';}elseif($_POST["drop"]){if($_POST["views"])$I=drop_views($_POST["views"]);if($I&&$_POST["tables"])$I=drop_tables($_POST["tables"]);$Xf='Tables have been dropped.';}elseif(JUSH=="sqlite"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("PRAGMA integrity_check(".q($R).")")as$K)$Xf
.="<b>".h($R)."</b>: ".h($K["integrity_check"])."<br>";}}elseif(JUSH=="mssql"&&$_POST["check"]){foreach((array)$_POST["tables"]as$R){foreach(get_rows("DBCC CHECKTABLE (".q(table($R)).") WITH TABLERESULTS")as$K)$Xf
.="<b>".h($R)."</b>: ".h($K["MessageText"])."<br>";}}elseif(JUSH!="sql"){$I=(JUSH=="sqlite"?queries("VACUUM"):apply_queries("VACUUM".($_POST["optimize"]?" ANALYZE":""),(array)$_POST["tables"]));$Xf='Tables have been optimized.';}elseif(!$_POST["tables"])$Xf='No tables.';elseif($I=queries(($_POST["optimize"]?"OPTIMIZE":($_POST["check"]?"CHECK":($_POST["repair"]?"REPAIR":"ANALYZE")))." TABLE ".implode(", ",array_map('Adminer\idf_escape',$_POST["tables"])))){while($K=$I->fetch_assoc())$Xf
.="<b>".h($K["Table"])."</b>: ".h($K["Msg_text"])."<br>";}queries_redirect(relative_uri(),$Xf,$I);}page_header(($_GET["ns"]==""?'Database'.": ".h(DB):'Schema'.": ".h($_GET["ns"])),$k,true);if(adminer()->homepage()){if($_GET["ns"]!==""){$D=$_GET["order"];$Fd=($D||support("fast_status"));echo"<div>\n","<h3 id='tables-views'>".'Tables and views'."</h3>\n";$Sj=($Fd?table_status():tables_list());if(!$Sj)echo"<p class='message'>".'No tables.'."\n";else{echo"<form action='' method='post'>\n";if(support("table")){echo"<fieldset><legend>".'Search data in tables'." <span id='selected2'></span></legend><div>",html_select("op",adminer()->operators(),idx($_POST,"op",JUSH=="elastic"?"should":"LIKE %%"))," <input type='search' name='query' value='".h($_POST["query"])."'".on('keydown','submitKeydown','search').">"," <input type='submit' name='search' value='".'Search'."'>\n","</div></fieldset>\n";if(!$k&&$_POST["search"]&&$_POST["query"]!=""){$_GET["where"][0]["op"]=$_POST["op"];search_tables();}}echo"<div class='scrollable'>\n","<table class='nowrap checkable odds'".on('click','tableClick').on('dblclick','tableClick').">\n",'<thead><tr class="wrap">','<td class="hover"><input id="check-all" type="checkbox" class="jsonly" title="'.'All'.'"'.on('click','formCheck','^(tables|views)\[').'>','<th'.(!$D&&JUSH!='sqlite'?" aria-sort='ascending'":'').'><a href="'.h(substr($Tf,0,-1)).'">'.'Table'.'</a>';$d=array("Engine"=>array('Engine'.doc_link(array('sql'=>'storage-engines.html'))));if(collations())$d["Collation"]=array('Collation'.doc_link(array('sql'=>'charset-charsets.html','mariadb'=>'supported-character-sets-and-collations/')));if(function_exists('Adminer\alter_table'))$d["Data_length"]=array('Data Length'.doc_link(array('sql'=>'show-table-status.html',)),"create",'Alter table',);if(support("indexes"))$d["Index_length"]=array('Index Length'.doc_link(array('sql'=>'show-table-status.html',)),"indexes",'Alter indexes',);$d["Data_free"]=array('Data Free'.doc_link(array('sql'=>'show-table-status.html')),"edit",'New item');if(function_exists('Adminer\alter_table'))$d["Auto_increment"]=array('Auto Increment'.doc_link(array('sql'=>'example-auto-increment.html','mariadb'=>'auto_increment/')),"auto_increment=1&create",'Alter table',);$d["Rows"]=array('Rows'.doc_link(array('sql'=>'show-table-status.html',)),"select",'Select data',);if(support("comment"))$d["Comment"]=array('Comment'.doc_link(array('sql'=>'show-table-status.html',)));$ya=array('Engine','Collation','Comment');foreach($d
as$x=>$c)echo"<th".($D==$x?" aria-sort='".(in_array($x,$ya)?"ascending":"descending")."'":"")."><a href='".h($Tf)."order=$x'>$c[0]</a>";echo"<tbody>\n";if($D){uasort($Sj,function($fa,$Ea)use($D,$ya){$J=($fa[$D]<$Ea[$D]?-1:($fa[$D]>$Ea[$D]?1:0));return(in_array($D,$ya)?$J:-$J);});}$T=0;$Ej=array("Data_length"=>0,"Index_length"=>0,"Data_free"=>0);foreach($Sj
as$B=>$P){$fl=($Fd?is_view($P):$P!==null&&!preg_match('~table|sequence~i',$P));$P=($Fd?$P:array('Engine'=>$P));$t=h("Table-".$B);echo'<tr><td class="hover">'.checkbox(($fl?"views[]":"tables[]"),$B,in_array("$B",$Tj,true),"","","",$t),'<th>'.(support("table")||support("indexes")?"<a href='".h(ME)."table=".url_escape($B)."' title='".'Show structure'."' id='$t'>".h($B).'</a>':h($B));if($fl&&!preg_match('~materialized~i',$P['Engine'])){$hk='View';echo'<td colspan="'.(count($d)-(support("comment")?2:1)).'">'.(support("view")?"<a href='".h(ME)."view=".url_escape($B)."' title='".'Alter view'."'>$hk</a>":$hk),"<td align='right'><a href='".h(ME)."select=".url_escape($B)."' title='".'Select data'."'>?</a>";if(support("comment"))echo'<td>'.h($P['Comment']);}else{if($Fd){foreach(array_keys($Ej)as$x)$Ej[$x]+=($P["Engine"]!="InnoDB"||$x!="Data_free"?idx($P,$x):0);}foreach($d
as$x=>$c){$t=" id='$x-".h($B)."'";echo($c[1]?"<td align='right'><a href='".h(ME."$c[1]=").url_escape($B)."'$t title='$c[2]'>".format_status($P,$x)."</a>":"<td$t>".h(idx($P,$x,'?')).($x=="Comment"&&$P["Error"]?" <span class='error'>".h($P["Error"])."</span>":""));}$T++;}echo"\n";}echo"<tr><td class='hover'><th>".sprintf('%d in total',count($Sj)),"<td>".h(JUSH=="sql"?get_val("SELECT @@default_storage_engine"):""),(collations()?"<td>".h(db_collation(DB,collations())):'');if($Fd&&function_exists('Adminer\db_status'))$Ej=db_status();foreach($Ej
as$x=>$Dj)echo($d[$x]?"<td align='right' id='sum-$x'>".($Fd?format_number($Dj):""):"");echo"\n","</table>\n",($Fd?'':script("ajaxSetHtml('".js_escape(ME)."script=db');")),"</div>\n";if(!information_schema(DB)){$Yk="<input type='submit' value='".'Vacuum'."'".on_help("VACUUM")."> ";$Vg="<input type='submit' name='optimize' value='".'Optimize'."'".on_help(JUSH=="sql"?"OPTIMIZE TABLE":"VACUUM ANALYZE")."> ";$Yh=(JUSH=="sqlite"?$Yk."<input type='submit' name='check' value='".'Check'."'".on_help("PRAGMA integrity_check")."> ":(JUSH=="pgsql"?$Yk.$Vg:(JUSH=="mssql"?"<input type='submit' name='check' value='".'Check'."'".on_help("DBCC CHECKTABLE")."> ":(JUSH=="sql"?"<input type='submit' value='".'Analyze'."'".on_help("ANALYZE TABLE")."> ".$Vg."<input type='submit' name='check' value='".'Check'."'".on_help("CHECK TABLE")."> "."<input type='submit' name='repair' value='".'Repair'."'".on_help("REPAIR TABLE")."> ":"")))).(function_exists('Adminer\truncate_tables')?"<input type='submit' name='truncate' value='".'Truncate'."'".confirm().on_help(JUSH=="sqlite"?"DELETE":"TRUNCATE".(JUSH=="pgsql"?"":" TABLE"))."> ":"").(function_exists('Adminer\drop_tables')?"<input type='submit' name='drop' value='".'Drop'."'".confirm().on_help("DROP TABLE").">":"");echo($Yh?"<div class='footer'><div>\n<fieldset><legend>".'Selected'." <span id='selected'></span></legend><div>$Yh\n</div></fieldset>\n":"");$h=(support("scheme")?adminer()->schemas():adminer()->databases());if(count($h)!=1&&function_exists('Adminer\move_tables')){echo"<fieldset><legend>".'Move to another database'." <span id='selected3'></span></legend><div>";$i=(isset($_POST["target"])?$_POST["target"]:(support("scheme")?$_GET["ns"]:DB));echo($h?html_select("target",$h,$i):'<input name="target" value="'.h($i).'" autocapitalize="off">'),"</label> <input type='submit' name='move' value='".'Move'."'>",(support("copy")?" <input type='submit' name='copy' value='".'Copy'."'> ".checkbox("overwrite",1,$_POST["overwrite"],'overwrite'):""),"</div></fieldset>\n";}echo"<input type='hidden' name='all' value=''".on('click','countTables',$T).">\n",input_token(),"</div></div>\n";}echo"</form>\n",script("tableCheck();");}echo(function_exists('Adminer\alter_table')?"<p class='links hover'><a href='".h(ME)."create='>".'Create table'."</a>\n":''),(support("view")?"<a href='".h(ME)."view='>".'Create view'."</a>\n":""),"</div>\n";if(support("routine")){echo"<div>\n","<h3 id='routines'>".'Routines'."</h3>\n";$Gi=routines();if($Gi){echo"<table class='odds'>\n",'<thead><tr><th>'.'Name'.'<td>'.'Type'.'<td>'.'Return type'."<td class='hover'><tbody>\n";foreach($Gi
as$K){$B=($K["SPECIFIC_NAME"]==$K["ROUTINE_NAME"]?"":"&name=".url_escape($K["ROUTINE_NAME"]));echo'<tr>','<th><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'callf=':'call=').url_escape($K["SPECIFIC_NAME"]).$B).'" title="'.'Call'.'">'.h($K["ROUTINE_NAME"]).'</a>','<td>'.h($K["ROUTINE_TYPE"]),'<td>'.h($K["DTD_IDENTIFIER"]),'<td class="hover"><a href="'.h(ME.($K["ROUTINE_TYPE"]!="PROCEDURE"?'function=':'procedure=').url_escape($K["SPECIFIC_NAME"]).$B).'">'.'Alter'."</a>";}echo"</table>\n";}echo'<p class="links hover">'.(support("procedure")?'<a href="'.h(ME).'procedure=">'.'Create procedure'.'</a>':'').'<a href="'.h(ME).'function=">'.'Create function'."</a>\n","</div>\n";}if(support("event")){echo"<div>\n","<h3 id='events'>".'Events'."</h3>\n";$L=get_rows("SHOW EVENTS");if($L){echo"<table>\n","<thead><tr><th>".'Name'."<td>".'Schedule'."<td>".'Start'."<td>".'End'."<td class='hover'><tbody>\n";foreach($L
as$K)echo"<tr>","<th>".h($K["Name"]),"<td>".($K["Execute at"]?'At given time'."<td>".h($K["Execute at"]):'Every'." ".h($K["Interval value"])." ".h($K["Interval field"])."<td>".h($K["Starts"])),"<td>".h($K["Ends"]),'<td class="hover"><a href="'.h(ME).'event='.url_escape($K["Name"]).'">'.'Alter'.'</a>';echo"</table>\n";$Rc=get_val("SELECT @@event_scheduler");if($Rc&&$Rc!="ON")echo"<p class='error'><code class='jush-sqlset'>event_scheduler</code>: ".h($Rc)."\n";}echo'<p class="links hover"><a href="'.h(ME).'event=">'.'Create event'."</a>\n","</div>\n";}}}}page_footer();