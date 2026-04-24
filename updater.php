<?php
/**
 * 🕵️ Sincronizador de Alta Seguridad 
 * Autodetector de Dominio, Ruta y Doble Validación Estricta
 * Licencia MIT
 * Autor: Alfonso Orozco Aguilar
 * Fecha : 25/03/2026 Actualizado 23 abr 2026
 * Actualización: Protección de config.php durante git clean
 * Pendiente    : Que verifique la IP antes de proceder 
 */

$current_path = getcwd();
$dominio = $_SERVER['HTTP_HOST'];
$confirmado_check = isset($_POST['confirmar_check']);
$confirmado_texto = isset($_POST['confirmar_texto']) ? $_POST['confirmar_texto'] : '';

echo "<h2 style='font-family:sans-serif;'>🌐 Dominio Detectado: <span style='color:red;'>" . htmlspecialchars($dominio) . "</span></h2>";
echo "<h3 style='font-family:sans-serif; color:gray;'>📁 Ruta Local: " . htmlspecialchars($current_path) . "</h3>";

// Aviso de Configuración Previa
echo "<div style='background:#e7f3ff; border-left:5px solid #2196F3; padding:15px; margin-bottom:20px; font-family:sans-serif;'>";
echo "<b>ℹ️ AVISO DE PROTOCOLO:</b> Se asume que el administrador ya configuró el <code>remote origin</code> ";
echo "utilizando un <b>Personal Access Token (PAT)</b> de GitHub o Gitea para este dominio.";
echo "</div>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirmado_check && $confirmado_texto === "SI") {

    echo "<pre style='background:#000; color:#0f0; padding:15px; border-radius:5px; font-family:monospace;'>";
    echo "🚨 VALIDACIÓN ACEPTADA EN [" . htmlspecialchars($dominio) . "]\n";
    echo "Iniciando sincronización...\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // A. Registrar el directorio actual como seguro en la configuración global
    //    de Git. Esto evita el error "dubious ownership" en entornos de servidor
    //    donde el usuario del proceso web difiere del dueño del repositorio.
    // ─────────────────────────────────────────────────────────────────────────
    echo "🔐 [SAFE DIR] Registrando directorio como seguro para Git...\n";
    exec("git config --global --add safe.directory " . escapeshellarg($current_path));
    echo "   ✔ Directorio registrado: " . htmlspecialchars($current_path) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // B. Fetch: Descarga todos los cambios del repositorio remoto sin aplicarlos
    //    todavía. Actualiza las referencias remotas (origin/main, etc.) localmente.
    // ─────────────────────────────────────────────────────────────────────────
    echo "📡 [FETCH] Conectando con el origen remoto y descargando cambios...\n";
    exec("cd " . escapeshellarg($current_path) . " && git fetch --all 2>&1", $out_fetch);
    echo implode("\n", $out_fetch) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // C. Reset Hard: Fuerza que el estado local sea idéntico a origin/main.
    //    Descarta cualquier modificación local en archivos trackeados por Git.
    //    Los archivos NO trackeados (como config.php) NO son afectados aquí.
    // ─────────────────────────────────────────────────────────────────────────
    echo "🔄 [RESET] Forzando espejo exacto del estado de 'origin/main'...\n";
    exec("cd " . escapeshellarg($current_path) . " && git reset --hard origin/main 2>&1", $out_reset);
    echo implode("\n", $out_reset) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // D. Clean (opcional): Elimina archivos y carpetas que NO están trackeados
    //    por Git (archivos huérfanos, temporales, subidas, etc.).
    //
    //    🛡️ PROTECCIÓN ACTIVA: Se usa --exclude=config.php para que Git omita
    //    ese archivo durante la limpieza, preservando la configuración local
    //    del servidor aunque no esté incluida en el repositorio remoto.
    // ─────────────────────────────────────────────────────────────────────────
    $borrar_basura = isset($_POST['borrar_basura']) && $_POST['borrar_basura'] === "SI";
    if ($borrar_basura) {
        echo "🧹 [CLEAN] Eliminando archivos huérfanos no trackeados por Git...\n";
        echo "   🛡️ config.php está EXCLUIDO de la limpieza (--exclude=config.php)\n";
        exec(
            "cd " . escapeshellarg($current_path) . " && git clean -fd --exclude=config.php 2>&1",
            $out_clean
        );
        echo implode("\n", $out_clean) . "\n";
    } else {
        echo "⏭️  [CLEAN] Omitido por el usuario (no se borrarán archivos huérfanos).\n";
    }

    echo "\n✅ OPERACIÓN TERMINADA. El servidor está sincronizado con origin/main.";
    echo "</pre>";
    echo '<br><a href="?">⬅️ Volver al Panel</a>';

} else {

    // ─────────────────────────────────────────────────────────────────────────
    // Formulario de doble validación antes de ejecutar cualquier operación.
    // Se requiere: marcar el checkbox Y escribir "SI" en el campo de texto.
    // ─────────────────────────────────────────────────────────────────────────
    echo '<form method="POST" style="background:#fff3cd; padding:25px; border:2px solid #ffeeba; border-radius:10px; font-family:sans-serif; max-width:650px;">';
    echo '<h3 style="margin-top:0; color:#856404;">⚠️ Confirmación de Sincronización Forzada</h3>';
    echo '<p>Estás a punto de resetear el contenido en <b>' . htmlspecialchars($dominio) . '</b>.</p>';

    echo '<label style="display:block; margin-bottom:15px; cursor:pointer;">';
    echo '<input type="checkbox" name="confirmar_check"> 1. Acepto que los archivos locales serán sobreescritos.';
    echo '</label>';

    echo '<div style="margin-bottom:20px;">';
    echo '2. Escribe <b>SI</b> para autorizar el Reset Hard:<br>';
    echo '<input type="text" name="confirmar_texto" placeholder="Escribe SI" style="padding:8px; width:100px; margin-top:5px; text-align:center;">';
    echo '</div>';

    echo '<div style="background:#f8d7da; padding:15px; border-radius:5px;">';
    echo '<b>🔥 OPCIONAL: ¿Borrar archivos que no están en Git?</b><br>';
    echo '<small style="color:#555;">🛡️ <code>config.php</code> está protegido y <b>nunca será borrado</b> por esta operación.</small><br><br>';
    echo 'Escribe <b>SI</b> para confirmar <code>git clean</code>: ';
    echo '<input type="text" name="borrar_basura" value="NO" placeholder="SI o NO" style="padding:5px; width:80px; text-align:center;">';
    echo '</div>';

    echo '<br><button type="submit" style="padding:15px; cursor:pointer; background:#dc3545; color:#fff; border:none; border-radius:5px; font-weight:bold; width:100%;">🚀 SINCRONIZAR AHORA</button>';
    echo '</form>';
}
?>
