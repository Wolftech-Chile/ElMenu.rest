<?php
// Archivo endpoint.php - Endpoints específicos para consultas AJAX
// Este archivo maneja consultas específicas para obtener datos

// Verificar que no sea acceso directo
if (!defined('IN_APP')) {
    define('IN_APP', true);
}

// Incluir archivo de base de datos
require_once('includes/db.php');

// Iniciar sesión
session_start();

// Identificar la acción solicitada (antes de exigir auth)
$accion = $_POST['accion'] ?? '';

// --- Seguridad flexible ---
// Para acciones de solo lectura como "obtener_todas_categorias" permitimos acceso sin login ni CSRF.
if ($accion !== 'obtener_todas_categorias') {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
        exit;
    }
    
    // CSRF protection
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Token CSRF inválido']);
        exit;
    }
}

// Verificar que sea petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}



// Obtener acción solicitada
$accion = $_POST['accion'] ?? '';

// Header por defecto para JSON
header('Content-Type: application/json');

// Manejar la acción solicitada
switch ($accion) {
    // Obtener todas las categorías
    case 'obtener_todas_categorias':
        // Obtener todas las categorías ordenadas
        $stmt = $db->prepare("SELECT id, nombre FROM categorias ORDER BY orden");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'categorias' => $result
        ]);
        break;
        
    // Obtener platos de una categoría específica
    case 'obtener_platos_categoria':
        // Validar parámetros
        if (empty($_POST['categoria_id'])) {
            echo json_encode(['ok' => false, 'msg' => 'Se requiere ID de categoría']);
            exit;
        }
        
        $categoria_id = $_POST['categoria_id'];
        
        // Obtener platos de esta categoría ordenados
        $stmt = $db->prepare("SELECT id, nombre, descripcion, precio, imagen FROM platos WHERE categoria_id = ? ORDER BY orden");
        $stmt->execute([$categoria_id]);
        $platos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'ok' => true,
            'platos' => $platos
        ]);
        break;
        
    default:
        echo json_encode(['ok' => false, 'msg' => 'Acción desconocida']);
}
?>
