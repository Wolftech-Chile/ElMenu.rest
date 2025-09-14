<?php
require_once 'helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Categoria.php';
require_once __DIR__ . '/../models/Plato.php';
require_once __DIR__ . '/../models/Config.php';

function handlePost($pdo) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
              
    csrf_validate($isAjax);
    $accion = $_POST['accion'] ?? '';
    
    switch($accion) {
        case 'editar_restaurante':
            return handleEditarRestaurante($pdo, $isAjax);
            
        case 'agregar_plato':
            return handleAgregarPlato($pdo, $isAjax);
            
        case 'eliminar_plato':
            return handleEliminarPlato($pdo, $isAjax);
            
        case 'guardar_categorias':
            return handleGuardarCategorias($pdo, $isAjax);
        case 'guardar_platos':
            return handleGuardarPlatos($pdo, $isAjax);
            
        case 'editar_mapa':
            return handleEditarMapa($pdo, $isAjax);
            
        case 'editar_seo':
            return handleEditarSeo($pdo, $isAjax);
        case 'guardar_config':
            return handleGuardarConfig($pdo, $isAjax);
        
        // --- nuevas acciones categoría ---
        case 'agregar_categoria':
            return handleAgregarCategoria($pdo, $isAjax);
        case 'eliminar_categoria':
            return handleEliminarCategoria($pdo, $isAjax);
            
        default:
            responderError($isAjax, 'Acción no válida');
    }
}

function handleEditarRestaurante($pdo, $isAjax) {
    $nombre = filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING);
    $descripcion = filter_input(INPUT_POST, 'descripcion', FILTER_SANITIZE_STRING);
    
    if (!$nombre) {
        responderError($isAjax, 'El nombre es requerido');
    }
    
    $logo_path = procesarImagen($_FILES['logo'] ?? null, 'logo');
    $portada_path = procesarImagen($_FILES['portada'] ?? null, 'portada', 1200);
    
    $query = "UPDATE restaurantes SET nombre = ?, descripcion = ?";
    $params = [$nombre, $descripcion];
    
    if ($logo_path) {
        $query .= ", logo = ?";
        $params[] = $logo_path;
    }
    if ($portada_path) {
        $query .= ", portada = ?";
        $params[] = $portada_path;
    }
    
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        registrarAccion("Edit info restaurante");
        responderExito($isAjax, 'info');
    }
    
    responderError($isAjax);
}

function handleAgregarPlato($pdo, $isAjax) {
    $categoriaId = (int)($_POST['categoria_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0;
    if ($categoriaId <= 0 || $nombre === '') {
        responderError($isAjax, 'Datos de plato inválidos');
    }
    // Manejo opcional de imagen
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['imagen']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            responderError($isAjax, 'Formato de imagen no soportado');
        }
        $dest = '../uploads/plato_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $dest)) {
            responderError($isAjax, 'No se pudo guardar la imagen');
        }
        $imagen = ltrim($dest, '../'); // ruta relativa
    }
    $platoModel = new Plato($pdo);
    $platoModel->create($categoriaId, $nombre, $descripcion, $precio, $imagen);
    registrarAccion('Agregar plato');
    responderExito($isAjax, 'platos');
}

function handleEliminarPlato($pdo, $isAjax) {
    $id = (int)($_POST['plato_id'] ?? 0);
    if ($id <= 0) responderError($isAjax, 'ID inválido');
    $platoModel = new Plato($pdo);
    $platoModel->delete($id);
    registrarAccion('Eliminar plato');
    responderExito($isAjax, 'platos');
}

function handleAgregarCategoria($pdo, $isAjax) {
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') responderError($isAjax, 'Nombre requerido');
    $catModel = new Categoria($pdo);
    $catModel->create($nombre);
    registrarAccion('Agregar categoría');
    responderExito($isAjax, 'categorias');
}

function handleEliminarCategoria($pdo, $isAjax) {
    $id = (int)($_POST['cat_id'] ?? 0);
    if ($id <= 0) responderError($isAjax, 'ID inválido');

    // Verificar si la categoría aún tiene platos
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM platos WHERE categoria_id = ?');
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        responderError($isAjax, 'Primero elimina o mueve los platos de esta categoría');
    }

    $catModel = new Categoria($pdo);
    $catModel->delete($id);
    registrarAccion('Eliminar categoría');
    responderExito($isAjax, 'categorias');
}

function handleGuardarPlatos($pdo,$isAjax) {
    // Recibe arrays plato_id[], nombre[], precio[], descripcion[] y reordenamiento
    $ids = $_POST['plato_id'] ?? [];
    $nombres = $_POST['nombre'] ?? [];
    $precios = $_POST['precio'] ?? [];
    $descripciones = $_POST['descripcion'] ?? [];
    if (!$ids) responderError($isAjax, 'Sin datos');
    $platoModel = new Plato($pdo);
    // Map id -> datos recogidos
    $datos = [];
    foreach ($ids as $i => $id) {
        $datos[$id] = [
            'nombre' => $nombres[$i] ?? '',
            'descripcion' => $descripciones[$i] ?? '',
            'precio' => (float)($precios[$i] ?? 0)
        ];
    }
    // Reordenamiento por categoría
    $ordenes = $_POST['orden_platos'] ?? [];
    if (!$ordenes) {
        // Si no se envió orden explícito, usar secuencia global como fallback
        $ordenes = ['global' => implode(',', $ids)];
    }
    foreach ($ordenes as $catId => $cadena) {
        $idList = array_filter(explode(',', $cadena));
        foreach ($idList as $idx => $pid) {
            if (!isset($datos[$pid])) continue;
            $platoModel->update((int)$pid, (int)$catId, $datos[$pid]['nombre'], $datos[$pid]['descripcion'], $datos[$pid]['precio'], null, $idx + 1);
        }
    }
    registrarAccion('Actualizar platos');
    responderExito($isAjax, 'platos');
}

function handleGuardarCategorias($pdo, $isAjax) {
    $orden = $_POST['orden'] ?? '';
    $nombresStr = $_POST['nombres'] ?? '';
    if ($orden === '' || $nombresStr === '') {
        responderError($isAjax, 'Datos incompletos');
    }
    $ids = array_map('intval', explode(',', $orden));
    $nombres = explode('|', $nombresStr);
    if (count($ids) !== count($nombres)) {
        responderError($isAjax, 'Desfase de datos');
    }
    $catModel = new Categoria($pdo);
    foreach ($ids as $idx => $id) {
        $catModel->update($id, $nombres[$idx] ?? '', $idx + 1);
    }
    registrarAccion('Actualizar categorías');
    responderExito($isAjax, 'categorias');
}

function handleEditarSeo($pdo, $isAjax) {
    $meta_desc = trim($_POST['meta_descripcion'] ?? '');
    $keywords = trim($_POST['meta_keywords'] ?? '');
    $ga = trim($_POST['google_analytics'] ?? '');
    $gsc = trim($_POST['google_search_console'] ?? '');

    $seo_img_path = null;
    if (isset($_FILES['seo_img']) && $_FILES['seo_img']['error'] === UPLOAD_ERR_OK) {
        $seo_img_path = procesarImagen($_FILES['seo_img'],'seo',1200);
        if (!$seo_img_path) {
            responderError($isAjax,'Imagen inválida');
        }
    }
    $config = new Config($pdo);
    $data = [
        'meta_descripcion'=>$meta_desc,
        'meta_keywords'=>$keywords,
        'google_analytics'=>$ga,
        'google_search_console'=>$gsc
    ];
    if ($seo_img_path) $data['seo_img']=$seo_img_path;
    if (!$config->setSeo($data)) {
        responderError($isAjax,'No se pudo guardar');
    }
    registrarAccion('Editar SEO');
    responderExito($isAjax,'seo');
}

function handleEditarMapa($pdo,$isAjax) {
    // Implementación pendiente
    responderExito($isAjax, 'mapa');
}

function handleGuardarConfig($pdo,$isAjax) {
    responderExito($isAjax, 'config');
}

