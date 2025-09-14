<?php
/**
 * Configuración de conexión a la base de datos SQLite
 * Este archivo es requerido por múltiples partes de la aplicación
 */

// Prevenir acceso directo
if (!defined('DIAGNOSTICO_MODE') && !defined('APP_MODE')) {
    die('Acceso no autorizado');
}

// Definir la ubicación del archivo de base de datos si no está definido
if (!defined('DB_FILE')) {
    define('DB_FILE', __DIR__ . '/../restaurante.db');
}

// Opciones de PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

// Crear conexión PDO para SQLite
try {
    $db = new PDO('sqlite:' . DB_FILE, null, null, $options);
} catch (PDOException $e) {
    // Manejo de errores con modo de diagnóstico
    if (defined('DIAGNOSTICO_MODE')) {
        die('Error de conexión SQLite: ' . $e->getMessage());
    } else {
        // En producción, mostrar mensaje genérico
        die('Error al conectar con la base de datos. Por favor contacte al administrador.');
    }
}

/**
 * Funciones helper para la base de datos
 */

// Obtener una sola fila
function db_get_row($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Obtener múltiples filas
function db_get_all($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Ejecutar una consulta INSERT/UPDATE/DELETE
function db_execute($sql, $params = []) {
    global $db;
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

// Obtener el último ID insertado
function db_last_insert_id() {
    global $db;
    return $db->lastInsertId();
}
