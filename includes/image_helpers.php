<?php
function procesarImagen($file, $directorio, $prefijo = '', $width = 800) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }
    
    // Validar tipo de archivo
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        throw new Exception('Tipo de archivo no permitido');
    }
    
    // Validar tamaño
    if ($file['size'] > MAX_IMAGE_SIZE) {
        throw new Exception('La imagen excede el tamaño máximo permitido');
    }
    
    // Crear directorio si no existe
    if (!file_exists($directorio)) {
        mkdir($directorio, 0777, true);
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $nombre_archivo = $prefijo . uniqid() . '.' . $extension;
    $ruta_destino = $directorio . '/' . $nombre_archivo;
    
    // Procesar imagen
    list($ancho_orig, $alto_orig) = getimagesize($file['tmp_name']);
    
    if ($ancho_orig > $width) {
        $ratio = $width / $ancho_orig;
        $nuevo_ancho = $width;
        $nuevo_alto = $alto_orig * $ratio;
    } else {
        $nuevo_ancho = $ancho_orig;
        $nuevo_alto = $alto_orig;
    }
    
    $imagen_nueva = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Mantener transparencia para PNG
    if ($file['type'] === 'image/png') {
        imagealphablending($imagen_nueva, false);
        imagesavealpha($imagen_nueva, true);
    }
    
    // Cargar imagen original
    switch($file['type']) {
        case 'image/jpeg':
            $imagen_orig = imagecreatefromjpeg($file['tmp_name']);
            break;
        case 'image/png':
            $imagen_orig = imagecreatefrompng($file['tmp_name']);
            break;
        case 'image/webp':
            $imagen_orig = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            throw new Exception('Formato de imagen no soportado');
    }
    
    // Redimensionar
    imagecopyresampled(
        $imagen_nueva, $imagen_orig,
        0, 0, 0, 0,
        $nuevo_ancho, $nuevo_alto,
        $ancho_orig, $alto_orig
    );
    
    // Guardar como WebP
    $ruta_webp = $directorio . '/' . pathinfo($nombre_archivo, PATHINFO_FILENAME) . '.webp';
    imagewebp($imagen_nueva, $ruta_webp, 80);
    
    // Liberar memoria
    imagedestroy($imagen_orig);
    imagedestroy($imagen_nueva);
    
    return str_replace('\\', '/', $ruta_webp);
}

function eliminarImagen($ruta) {
    if (file_exists($ruta)) {
        unlink($ruta);
        return true;
    }
    return false;
}

function validarImagen($file) {
    $errores = [];
    
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errores[] = 'No se subió ningún archivo';
    } elseif (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        $errores[] = 'Tipo de archivo no permitido';
    } elseif ($file['size'] > MAX_IMAGE_SIZE) {
        $errores[] = 'La imagen excede el tamaño máximo permitido';
    }
    
    return $errores;
}
