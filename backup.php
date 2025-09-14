<?php
// backup.php - Exportar e importar toda la base de datos SQLite en CSV (todas las tablas, delimitadas)
// Llamado desde desktop.php (sin JS, solo PHP clásico)
session_start();
require_once 'core/Database.php';

function get_all_tables($pdo) {
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Exporta todas las tablas SQLite a un solo archivo CSV con delimitador punto y coma (;)
// Incluye marcadores --TABLA:nombre-- y --FIN_TABLA:nombre-- para separar tablas
// Compatible con Excel y soporta UTF-8 con BOM
function export_csv($pdo) {
    $tables = get_all_tables($pdo);
    $out = "\xEF\xBB\xBF"; // BOM UTF-8 para Excel
    foreach ($tables as $table) {
        $out .= "--TABLA:$table--\n";
        $stmt = $pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            // Cabecera
            $out .= implode(';', array_map('csv_escape', array_keys($rows[0]))) . "\n";
            foreach ($rows as $row) {
                $out .= implode(';', array_map('csv_escape', $row)) . "\n";
            }
        }
        $out .= "--FIN_TABLA:$table--\n";
    }
    $filename = 'backup_'.date('Ymd_His').'.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $out;
    exit;
}

// Escapa valores para CSV compatible con Excel y delimitador ;
function csv_escape($value) {
    if ($value === null) return '';
    // Escapa punto y coma y salto de línea
    $escaped = str_replace(["\n", ";"], ["\\n", "\\;"], $value);
    // Escapa comillas dobles duplicándolas
    $escaped = str_replace('"', '""', $escaped);
    // Si contiene punto y coma, salto de línea o comillas, encierra entre comillas
    if (strpos($escaped, ';') !== false || strpos($escaped, "\n") !== false || strpos($escaped, '"') !== false) {
        $escaped = '"' . $escaped . '"';
    }
    return $escaped;
}

$pdo = Database::get();

// --- IMPORTAR/RESTAURAR BASE DE DATOS COMPLETA (.db) ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) && $_POST['accion'] === 'importar_db'
) {
    if (!isset($_FILES['db_file']) || $_FILES['db_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['mensaje'] = ['tipo'=>'error','texto'=>'Error al subir el archivo .db.'];
        header('Location: desktop.php#info'); exit;
    }
    $file = $_FILES['db_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['db', 'sqlite'])) {
        $_SESSION['mensaje'] = ['tipo'=>'error','texto'=>'Solo se permiten archivos .db o .sqlite.'];
        header('Location: desktop.php#info'); exit;
    }
    $db_path = __DIR__ . '/restaurante.db';
    $backup_path = __DIR__ . '/restaurante_backup_' . date('Ymd_His') . '.db';
    // Hacer backup previo
    if (file_exists($db_path)) {
        copy($db_path, $backup_path);
    }
    // Reemplazar base de datos
    if (!move_uploaded_file($file['tmp_name'], $db_path)) {
        $_SESSION['mensaje'] = ['tipo'=>'error','texto'=>'No se pudo restaurar la base de datos.'];
        header('Location: desktop.php#info'); exit;
    }
    $_SESSION['mensaje'] = ['tipo'=>'success','texto'=>'¡Base de datos restaurada correctamente!'];
    header('Location: desktop.php#info'); exit;
}


if (isset($_GET['accion']) && $_GET['accion']==='exportar_csv') {
    export_csv($pdo);
}
// (No hay lógica de importación CSV, solo restauración .db y exportación CSV)

// Si se accede directamente, mostrar advertencia
// --- EXPORTAR BASE DE DATOS COMPLETA (.db) ---
if (isset($_GET['accion']) && $_GET['accion']==='exportar_db') {
    $db_path = __DIR__ . '/restaurante.db';
    if (!file_exists($db_path)) {
        http_response_code(404);
        echo 'No se encontró la base de datos.';
        exit;
    }
    $filename = 'restaurante_backup_' . date('Ymd_His') . '.db';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($db_path));
    readfile($db_path);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
// --- EXPORTAR BASE DE DATOS COMPLETA (.db) ---
if (isset($_GET['accion']) && $_GET['accion']==='exportar_db') {
    $db_path = __DIR__ . '/restaurante.db';
    if (!file_exists($db_path)) {
        http_response_code(404);
        echo 'No se encontró la base de datos.';
        exit;
    }
    $filename = 'restaurante_backup_' . date('Ymd_His') . '.db';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($db_path));
    readfile($db_path);
    exit;
}

<head><meta charset="UTF-8"><title>Backup - Importar/Exportar</title></head>
<body>
<h2>Exportar datos a Excel (CSV)</h2>
<form method="get">
  <input type="hidden" name="accion" value="exportar_csv">
  <button type="submit">Exportar datos CSV</button>
</form>
</body>
</html>
