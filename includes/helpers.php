<?php
function procesarImagen($file, $tipo, $max_width = 800) {
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    
    $img = null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        return null;
    }
    
    // Crear directorio si no existe
    $upload_dir = 'img/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . $tipo . '_' . time() . '.' . 'webp';
    $dest = $upload_dir . $filename;
    
    // Procesar imagen
    if ($ext === 'jpg' || $ext === 'jpeg') {
        $img = imagecreatefromjpeg($file['tmp_name']);
    } elseif ($ext === 'png') {
        $img = imagecreatefrompng($file['tmp_name']);
    } elseif ($ext === 'webp') {
        $img = imagecreatefromwebp($file['tmp_name']);
    }
    
    if (!$img) {
        return null;
    }
    
    // Redimensionar si es necesario
    $width = imagesx($img);
    $height = imagesy($img);
    
    if ($width > $max_width) {
        $new_width = $max_width;
        $new_height = floor($height * ($max_width / $width));
        $tmp = imagecreatetruecolor($new_width, $new_height);
        
        // Preservar transparencia
        imagepalettetotruecolor($tmp);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        
        imagecopyresampled($tmp, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($img);
        $img = $tmp;
    }
    
    // Guardar como WebP
    imagewebp($img, $dest, 85);
    imagedestroy($img);
    
    return $dest;
}

function responderJSON($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function responderExito($isAjax, $tipo = '') {
    if ($isAjax) {
        responderJSON(['ok' => true]);
    }
    header('Location: desktop.php' . ($tipo ? "?ok=$tipo" : ''));
    exit;
}

function responderError($isAjax, $mensaje = 'Error al procesar la solicitud') {
    if ($isAjax) {
        responderJSON(['ok' => false, 'msg' => $mensaje], 400);
    }
    header('Location: desktop.php?error=' . urlencode($mensaje));
    exit;
}

function validarIframeMaps($iframe) {
    $iframe = trim($iframe);
    
    if (!preg_match('/^<iframe.*<\/iframe>$/i', $iframe)) {
        return false;
    }
    
    if (!preg_match('/src=(["\'])https:\/\/(www\.)?(google\.com\/maps|maps\.google\.com).*?\1/i', $iframe)) {
        return false;
    }
    
    if (preg_match('/(onclick|onload|onerror|javascript:)/i', $iframe)) {
        return false;
    }
    
    return true;
}

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function registrarAccion($mensaje) {
    $usuario = $_SESSION['usuario'] ?? 'Sistema';
    $fecha = date('c');
    file_put_contents(
        '../logs/acciones.log',
        "[$fecha] $mensaje por $usuario\n",
        FILE_APPEND
    );
}

/* ---------------- CSRF helpers ---------------- */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string {
        return '<input type="hidden" name="csrf_token" value="'.esc(csrf_token()).'">';
    }
}

if (!function_exists('csrf_validate')) {
    /**
     * Valida token CSRF presente en $_POST['csrf_token'].
     * Si no es válido, termina la ejecución con error.
     */
    function csrf_validate(bool $isAjax = false): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $token = $_POST['csrf_token'] ?? '';
        $valid = !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
        if (!$valid) {
            responderError($isAjax, 'Token CSRF inválido');
        }
    }
}
