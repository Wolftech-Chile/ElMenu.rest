<?php
/**
 * Funciones auxiliares para ElMenu.rest
 */

// Prevenir acceso directo
if (!defined('IN_APP') && !defined('DIAGNOSTICO_MODE')) {
    define('IN_APP', true);
}

/**
 * Sanitiza texto para prevenir XSS
 */
function sanitizeOutput($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Función para obtener todas las categorías con sus platos
 * Utilizada por diagnostico.php
 */
function obtenerTodasLasCategorias() {
    global $db;
    $categorias = [];
    
    try {
        // Obtener categorías
        $stmt = $db->prepare("SELECT id, nombre, orden FROM categorias ORDER BY orden");
        $stmt->execute();
        $cats = $stmt->fetchAll();
        
        foreach ($cats as $cat) {
            $categoria = $cat;
            $categoria['platos'] = [];
            
            // Obtener platos de esta categoría
            $stmt = $db->prepare("SELECT id, nombre, precio, descripcion, categoria_id FROM platos WHERE categoria_id = ? ORDER BY orden");
            $stmt->execute([$cat['id']]);
            $categoria['platos'] = $stmt->fetchAll();
            
            $categorias[] = $categoria;
        }
        
        return $categorias;
    } catch (PDOException $e) {
        if (defined('DIAGNOSTICO_MODE')) {
            echo "Error al obtener categorías: " . $e->getMessage();
        }
        return [];
    }
}

/**
 * Función para generar un ID único para sesión
 */
function generateUniqueId($prefix = '') {
    return $prefix . uniqid() . bin2hex(random_bytes(8));
}

/**
 * Redireccionar con un mensaje
 */
function redirect($url, $message = null) {
    if ($message) {
        $_SESSION['message'] = $message;
    }
    header("Location: $url");
    exit;
}

/**
 * Normaliza una respuesta eliminando acentos, mayúsculas y caracteres especiales
 */
function normalizar_respuesta($txt) {
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = strtr($txt, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
        'Ä'=>'a','Ë'=>'e','Ï'=>'i','Ö'=>'o','Ü'=>'u',
    ]);
    return preg_replace('/[^a-z0-9 ]/u','',$txt);
}

