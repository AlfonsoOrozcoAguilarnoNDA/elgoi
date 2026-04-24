<?php
// Obtener datos de memoria desde /proc/meminfo o el comando free
$free = shell_exec('free -m');
$free_lines = explode("\n", trim($free));
$mem_info = preg_split('/\s+/', $free_lines[1]);

$total_mem = $mem_info[1];
$used_mem = $mem_info[2];
$percent_mem = round(($used_mem / $total_mem) * 100, 2);

// Obtener datos de disco
$disk_total = disk_total_space("/") / 1024 / 1024 / 1024; // GB
$disk_free = disk_free_space("/") / 1024 / 1024 / 1024;   // GB
$disk_used = $disk_total - $disk_free;
$percent_disk = round(($disk_used / $disk_total) * 100, 2);

echo "<h2>Estado del Servidor (Debian 13)</h2>";
echo "<b>Memoria RAM:</b> $used_mem MB usados de $total_mem MB ($percent_mem%)<br>";
echo "<div style='width:300px; background:#ddd;'><div style='width:$percent_mem%; background:red; height:10px;'></div></div>";

echo "<br><b>Disco Duro:</b> " . round($disk_used, 2) . " GB usados de " . round($disk_total, 2) . " GB ($percent_disk%)<br>";
echo "<div style='width:300px; background:#ddd;'><div style='width:$percent_disk%; background:blue; height:10px;'></div></div>";

echo "<br><b>Carga de CPU (1, 5, 15 min):</b> " . implode(", ", sys_getloadavg());
?>