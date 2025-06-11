<?php
// dashboard.php - Panel de administración seguro
require_once 'config.php';
session_start();

// Seguridad: solo usuarios logueados
if (empty($_SESSION['usuario_id']) || empty($_SESSION['rol'])) {
    header('Location: login.php');
    exit;
}

// Conexión a la base de datos
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cargar datos del restaurante
$stmt = $pdo->prepare('SELECT nombre, direccion, horario, telefono, facebook, instagram, logo, tema, slogan, seo_desc, seo_img, fecha_licencia, fondo_header FROM restaurante LIMIT 1');
$stmt->execute();
$rest = $stmt->fetch(PDO::FETCH_ASSOC);

// Cargar categorías para usarlas en el dashboard
$stmt = $pdo->prepare('SELECT * FROM categorias ORDER BY orden ASC, id ASC');
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Control de licencia
$fecha_licencia = $rest['fecha_licencia'] ?? '01-10-2025';
$fecha_obj = DateTime::createFromFormat('d-m-Y', $fecha_licencia);
$hoy = new DateTime();
if ($fecha_obj) {
    $dias_restantes = (int)$hoy->diff($fecha_obj)->format('%r%a');
    $dias_totales = (int)((new DateTime())->diff($fecha_obj))->format('%a');
} else {
    $dias_restantes = 0;
    $dias_totales = 0;
}
$avisos_criticos = [30,15,7,6,5,4,3,2,1];
$es_admin = ($_SESSION['rol'] === 'admin');

function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Guardar cambio de tema visual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tema'])) {
    $tema = preg_replace('/[^a-z0-9_]/i','', $_POST['tema']);
    $stmt = $pdo->prepare('UPDATE restaurante SET tema = ?');
    $stmt->execute([$tema]);
    // Refrescar para ver el cambio
    header('Location: dashboard.php#personalizacion');
    exit;
}

// --- PROCESAMIENTO UNIFICADO DE FORMULARIOS Y ACCIONES POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. AGREGAR CATEGORÍA
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar_categoria' && !empty($_POST['nombre'])) {
        $nombre = trim($_POST['nombre']);
        $stmt = $pdo->prepare('INSERT INTO categorias (nombre, orden) VALUES (?, (SELECT IFNULL(MAX(orden),0)+1 FROM categorias))');
        $stmt->execute([$nombre]);
        header('Location: dashboard.php#categorias'); exit;
    }
    // 2. AGREGAR PLATO
    if (isset($_POST['accion']) && $_POST['accion'] === 'agregar_plato') {
        $img_path = null;
        if (!empty($_FILES['imagen']['tmp_name'])) {
            $img = $_FILES['imagen'];
            $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $dest = 'img/plato_' . time() . '_' . rand(100,999) . '.webp';
                $src = $img['tmp_name'];
                [$w, $h] = getimagesize($src);
                $max = 400;
                $ratio = min($max/$w, $max/$h, 1);
                $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                $dst_img = imagecreatetruecolor($nw, $nh);
                if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                else $src_img = null;
                if ($src_img) {
                    imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                    imagewebp($dst_img, $dest, 85);
                    imagedestroy($src_img); imagedestroy($dst_img);
                    $img_path = $dest;
                }
            }
        }
        $stmt = $pdo->prepare('INSERT INTO platos (categoria_id, nombre, descripcion, precio, imagen, orden) VALUES (?,?,?,?,?,?)');
        $stmt->execute([
            $_POST['categoria_id'],
            $_POST['nombre'],
            $_POST['descripcion'],
            $_POST['precio'],
            $img_path,
            0
        ]);
        header('Location: dashboard.php#platos'); exit;
    }
    // 3. ELIMINAR PLATO (AJAX o formulario)
    if (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_plato' && !empty($_POST['plato_id'])) {
        $plato_id = (int)$_POST['plato_id'];
        $stmt = $pdo->prepare('DELETE FROM platos WHERE id=?');
        $ok = $stmt->execute([$plato_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=plato_eliminado#platos');
            exit;
        }
    }
    // 4. ELIMINAR CATEGORÍA (AJAX o formulario)
    if (
        (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && isset($_POST['eliminar']) && is_numeric($_POST['eliminar'])) ||
        (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_categoria' && !empty($_POST['cat_id']) && is_numeric($_POST['cat_id']))
    ) {
        $cat_id = isset($_POST['eliminar']) ? (int)$_POST['eliminar'] : (int)$_POST['cat_id'];
        // Eliminar platos asociados primero (integridad referencial)
        $pdo->prepare('DELETE FROM platos WHERE categoria_id=?')->execute([$cat_id]);
        // Eliminar la categoría
        $ok = $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$cat_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=categorias#categorias');
            exit;
        }
    }
    // 5. GUARDAR ORDEN Y NOMBRES DE CATEGORÍAS
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && !empty($_POST['orden']) && !empty($_POST['nombres'])) {
        $orden = explode(',', $_POST['orden']);
        $nombres = explode('|', $_POST['nombres']);
        foreach ($orden as $i => $cat_id) {
            $nombre = isset($nombres[$i]) ? trim($nombres[$i]) : '';
            if ($nombre !== '') {
                $stmt = $pdo->prepare('UPDATE categorias SET nombre=?, orden=? WHERE id=?');
                $stmt->execute([$nombre, $i, $cat_id]);
            }
        }
        header('Location: dashboard.php?ok=categorias#categorias');
        exit;
    }
    // 6. GUARDAR ORDEN Y DATOS DE PLATOS
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_platos' && !empty($_POST['orden_platos']) && is_array($_POST['orden_platos'])) {
        $datos_platos = [];
        if (!empty($_POST['plato_id']) && !empty($_POST['nombre']) && !empty($_POST['precio']) && !empty($_POST['descripcion'])) {
            foreach ($_POST['plato_id'] as $i => $pid) {
                $datos_platos[$pid] = [
                    'nombre' => substr(trim($_POST['nombre'][$i]),0,64),
                    'precio' => (int)$_POST['precio'][$i],
                    'descripcion' => substr(trim($_POST['descripcion'][$i]),0,120)
                ];
            }
        }
        foreach ($_POST['orden_platos'] as $cat_id => $orden_str) {
            $ids = array_filter(explode(',', $orden_str), 'is_numeric');
            foreach ($ids as $orden => $pid) {
                if (isset($datos_platos[$pid])) {
                    $d = $datos_platos[$pid];
                    $stmt = $pdo->prepare('UPDATE platos SET nombre=?, precio=?, descripcion=?, orden=? WHERE id=? AND categoria_id=?');
                    $stmt->execute([$d['nombre'], $d['precio'], $d['descripcion'], $orden, $pid, $cat_id]);
                }
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'msg' => 'Platos guardados correctamente.']);
            exit;
        }
        header('Location: dashboard.php?ok=platos#platos'); exit;
    }
    // 7. GUARDAR RESTAURANTE, SEO, MAPA, CONFIG, ETC. (mantener el resto de acciones ya existentes)
    if (isset($_POST['accion'])) {
        if ($_POST['accion'] === 'editar_restaurante') {
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $horario = trim($_POST['horario']);
            $telefono = trim($_POST['telefono']);
            $facebook = filter_var($_POST['facebook'], FILTER_VALIDATE_URL) ? $_POST['facebook'] : '';
            $instagram = filter_var($_POST['instagram'], FILTER_VALIDATE_URL) ? $_POST['instagram'] : '';
            $logo = $rest['logo'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $img = $_FILES['logo'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/logo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 200;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $logo = $dest;
                    }
                }
            }
            $fondo_header = $rest['fondo_header'];
            if (!empty($_FILES['fondo_header']['tmp_name'])) {
                $img = $_FILES['fondo_header'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/fondo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 800;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $fondo_header = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET nombre=?, direccion=?, horario=?, telefono=?, facebook=?, instagram=?, logo=?, fondo_header=?');
            $stmt->execute([$nombre, $direccion, $horario, $telefono, $facebook, $instagram, $logo, $fondo_header]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit info restaurante por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#info'); exit;
        }
        if ($_POST['accion'] === 'editar_seo') {
            $slogan = trim($_POST['slogan']);
            $seo_desc = trim($_POST['seo_desc']);
            $seo_img = $rest['seo_img'];
            if (!empty($_FILES['seo_img']['tmp_name'])) {
                $img = $_FILES['seo_img'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/seo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 400;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $seo_img = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET slogan=?, seo_desc=?, seo_img=?');
            $stmt->execute([$slogan, $seo_desc, $seo_img]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit SEO por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#seo'); exit;
        }
        if ($_POST['accion'] === 'editar_mapa') {
            $iframe = trim($_POST['iframe_mapa']);
            if (preg_match('/^<iframe[^>]+src="https:\/\/(www\.)?google\.[^\"]+\/maps[^\"]*"[^>]*><\/iframe>$/i', $iframe)) {
                $stmt = $pdo->prepare('UPDATE restaurante SET iframe_mapa=?');
                $stmt->execute([$iframe]);
            }
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit mapa por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#mapa'); exit;
        }
        if ($_POST['accion'] === 'guardar_config') {
            $dias = isset($_POST['dias_renovar']) ? (int)$_POST['dias_renovar'] : 0;
            if ($dias > 0) {
                $nueva_fecha = (new DateTime())->modify("+{$dias} days")->format('d-m-Y');
                $stmt = $pdo->prepare('UPDATE restaurante SET fecha_licencia=?');
                $stmt->execute([$nueva_fecha]);
                file_put_contents('../logs/acciones.log', date('c')." [INFO] Renovar licencia por {$_SESSION['usuario']} a $nueva_fecha\n", FILE_APPEND);
            }
            header('Location: dashboard.php#config-avanzada'); exit;
        }
    }
}

// --- PROCESAMIENTO DE ELIMINACIÓN Y ORDEN DE CATEGORÍAS/PLATOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ELIMINAR PLATO (AJAX o formulario)
    if ((isset($_POST['accion']) && $_POST['accion'] === 'eliminar_plato') && !empty($_POST['plato_id'])) {
        $plato_id = (int)$_POST['plato_id'];
        $stmt = $pdo->prepare('DELETE FROM platos WHERE id=?');
        $ok = $stmt->execute([$plato_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=plato_eliminado#platos');
            exit;
        }
    }
    // ELIMINAR CATEGORÍA (AJAX o formulario)
    if (
        (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && isset($_POST['eliminar']) && is_numeric($_POST['eliminar'])) ||
        (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_categoria' && !empty($_POST['cat_id']) && is_numeric($_POST['cat_id']))
    ) {
        $cat_id = isset($_POST['eliminar']) ? (int)$_POST['eliminar'] : (int)$_POST['cat_id'];
        // Eliminar platos asociados primero (integridad referencial)
        $pdo->prepare('DELETE FROM platos WHERE categoria_id=?')->execute([$cat_id]);
        // Eliminar la categoría
        $ok = $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$cat_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=categorias#categorias');
            exit;
        }
    }
    // GUARDAR ORDEN Y NOMBRES DE CATEGORÍAS
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && !empty($_POST['orden']) && !empty($_POST['nombres'])) {
        $orden = explode(',', $_POST['orden']);
        $nombres = explode('|', $_POST['nombres']);
        foreach ($orden as $i => $cat_id) {
            $nombre = isset($nombres[$i]) ? trim($nombres[$i]) : '';
            if ($nombre !== '') {
                $stmt = $pdo->prepare('UPDATE categorias SET nombre=?, orden=? WHERE id=?');
                $stmt->execute([$nombre, $i, $cat_id]);
            }
        }
        header('Location: dashboard.php?ok=categorias#categorias');
        exit;
    }
    // GUARDAR ORDEN DE PLATOS (por categoría)
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_platos' && !empty($_POST['orden_platos']) && is_array($_POST['orden_platos'])) {
        foreach ($_POST['orden_platos'] as $cat_id => $orden_str) {
            $ids = explode(',', $orden_str);
            foreach ($ids as $i => $plato_id) {
                $stmt = $pdo->prepare('UPDATE platos SET orden=? WHERE id=? AND categoria_id=?');
                $stmt->execute([$i, $plato_id, $cat_id]);
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        } else {
            header('Location: dashboard.php?ok=platos#platos');
            exit;
        }
    }
}

// --- PROCESAMIENTO AVANZADO DE FORMULARIOS DASHBOARD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        // CRUD de categorías y platos ya implementado arriba...

        if ($_POST['accion'] === 'editar_restaurante') {
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $horario = trim($_POST['horario']);
            $telefono = trim($_POST['telefono']);
            $facebook = filter_var($_POST['facebook'], FILTER_VALIDATE_URL) ? $_POST['facebook'] : '';
            $instagram = filter_var($_POST['instagram'], FILTER_VALIDATE_URL) ? $_POST['instagram'] : '';
            $logo = $rest['logo'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $img = $_FILES['logo'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/logo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 200;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $logo = $dest;
                    }
                }
            }
            $fondo_header = $rest['fondo_header'];
            if (!empty($_FILES['fondo_header']['tmp_name'])) {
                $img = $_FILES['fondo_header'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/fondo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 800;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $fondo_header = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET nombre=?, direccion=?, horario=?, telefono=?, facebook=?, instagram=?, logo=?, fondo_header=?');
            $stmt->execute([$nombre, $direccion, $horario, $telefono, $facebook, $instagram, $logo, $fondo_header]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit info restaurante por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#info'); exit;
        }
        if ($_POST['accion'] === 'editar_seo') {
            $slogan = trim($_POST['slogan']);
            $seo_desc = trim($_POST['seo_desc']);
            $seo_img = $rest['seo_img'];
            if (!empty($_FILES['seo_img']['tmp_name'])) {
                $img = $_FILES['seo_img'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/seo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 400;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $seo_img = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET slogan=?, seo_desc=?, seo_img=?');
            $stmt->execute([$slogan, $seo_desc, $seo_img]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit SEO por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#seo'); exit;
        }
        if ($_POST['accion'] === 'editar_mapa') {
            $iframe = trim($_POST['iframe_mapa']);
            if (preg_match('/^<iframe[^>]+src="https:\/\/(www\.)?google\.[^\"]+\/maps[^\"]*"[^>]*><\/iframe>$/i', $iframe)) {
                $stmt = $pdo->prepare('UPDATE restaurante SET iframe_mapa=?');
                $stmt->execute([$iframe]);
            }
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit mapa por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#mapa'); exit;
        }
        if ($_POST['accion'] === 'guardar_config') {
            $dias = isset($_POST['dias_renovar']) ? (int)$_POST['dias_renovar'] : 0;
            if ($dias > 0) {
                $nueva_fecha = (new DateTime())->modify("+{$dias} days")->format('d-m-Y');
                $stmt = $pdo->prepare('UPDATE restaurante SET fecha_licencia=?');
                $stmt->execute([$nueva_fecha]);
                file_put_contents('../logs/acciones.log', date('c')." [INFO] Renovar licencia por {$_SESSION['usuario']} a $nueva_fecha\n", FILE_APPEND);
            }
            header('Location: dashboard.php#config-avanzada'); exit;
        }
    }
}

// Subida/redimensionado de imágenes para platos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'agregar_plato') {
    $img_path = null;
    if (!empty($_FILES['imagen']['tmp_name'])) {
        $img = $_FILES['imagen'];
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $dest = 'img/plato_' . time() . '_' . rand(100,999) . '.webp';
            $src = $img['tmp_name'];
            // Redimensionar a 400x400px máx
            [$w, $h] = getimagesize($src);
            $max = 400;
            $ratio = min($max/$w, $max/$h, 1);
            $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
            $dst_img = imagecreatetruecolor($nw, $nh);
            if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
            elseif ($ext==='png') $src_img = imagecreatefrompng($src);
            elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
            else $src_img = null;
            if ($src_img) {
                imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                imagewebp($dst_img, $dest, 85);
                imagedestroy($src_img); imagedestroy($dst_img);
                $img_path = $dest;
            }
        }
    }
    // Insertar plato
    $stmt = $pdo->prepare('INSERT INTO platos (categoria_id, nombre, descripcion, precio, imagen, orden) VALUES (?,?,?,?,?,?)');
    $stmt->execute([
        $_POST['categoria_id'],
        $_POST['nombre'],
        $_POST['descripcion'],
        $_POST['precio'],
        $img_path,
        0
    ]);
    header('Location: dashboard.php#platos'); exit;
}

// Procesamiento avanzado de formularios dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        // CRUD de categorías y platos ya implementado arriba...

        if ($_POST['accion'] === 'editar_restaurante') {
            $nombre = trim($_POST['nombre']);
            $direccion = trim($_POST['direccion']);
            $horario = trim($_POST['horario']);
            $telefono = trim($_POST['telefono']);
            $facebook = filter_var($_POST['facebook'], FILTER_VALIDATE_URL) ? $_POST['facebook'] : '';
            $instagram = filter_var($_POST['instagram'], FILTER_VALIDATE_URL) ? $_POST['instagram'] : '';
            $logo = $rest['logo'];
            if (!empty($_FILES['logo']['tmp_name'])) {
                $img = $_FILES['logo'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/logo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 200;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $logo = $dest;
                    }
                }
            }
            $fondo_header = $rest['fondo_header'];
            if (!empty($_FILES['fondo_header']['tmp_name'])) {
                $img = $_FILES['fondo_header'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/fondo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 800;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $fondo_header = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET nombre=?, direccion=?, horario=?, telefono=?, facebook=?, instagram=?, logo=?, fondo_header=?');
            $stmt->execute([$nombre, $direccion, $horario, $telefono, $facebook, $instagram, $logo, $fondo_header]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit info restaurante por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#info'); exit;
        }
        if ($_POST['accion'] === 'editar_seo') {
            $slogan = trim($_POST['slogan']);
            $seo_desc = trim($_POST['seo_desc']);
            $seo_img = $rest['seo_img'];
            if (!empty($_FILES['seo_img']['tmp_name'])) {
                $img = $_FILES['seo_img'];
                $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                    $dest = 'img/seo_' . time() . '_' . rand(100,999) . '.webp';
                    $src = $img['tmp_name'];
                    [$w, $h] = getimagesize($src);
                    $max = 400;
                    $ratio = min($max/$w, $max/$h, 1);
                    $nw = (int)($w*$ratio); $nh = (int)($h*$ratio);
                    $dst_img = imagecreatetruecolor($nw, $nh);
                    if ($ext==='jpg'||$ext==='jpeg') $src_img = imagecreatefromjpeg($src);
                    elseif ($ext==='png') $src_img = imagecreatefrompng($src);
                    elseif ($ext==='webp') $src_img = imagecreatefromwebp($src);
                    else $src_img = null;
                    if ($src_img) {
                        imagecopyresampled($dst_img, $src_img, 0,0,0,0, $nw,$nh, $w,$h);
                        imagewebp($dst_img, $dest, 85);
                        imagedestroy($src_img); imagedestroy($dst_img);
                        $seo_img = $dest;
                    }
                }
            }
            $stmt = $pdo->prepare('UPDATE restaurante SET slogan=?, seo_desc=?, seo_img=?');
            $stmt->execute([$slogan, $seo_desc, $seo_img]);
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit SEO por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#seo'); exit;
        }
        if ($_POST['accion'] === 'editar_mapa') {
            $iframe = trim($_POST['iframe_mapa']);
            if (preg_match('/^<iframe[^>]+src="https:\/\/(www\.)?google\.[^\"]+\/maps[^\"]*"[^>]*><\/iframe>$/i', $iframe)) {
                $stmt = $pdo->prepare('UPDATE restaurante SET iframe_mapa=?');
                $stmt->execute([$iframe]);
            }
            file_put_contents('../logs/acciones.log', date('c')." [INFO] Edit mapa por {$_SESSION['usuario']}\n", FILE_APPEND);
            header('Location: dashboard.php#mapa'); exit;
        }
        if ($_POST['accion'] === 'guardar_config') {
            $dias = isset($_POST['dias_renovar']) ? (int)$_POST['dias_renovar'] : 0;
            if ($dias > 0) {
                $nueva_fecha = (new DateTime())->modify("+{$dias} days")->format('d-m-Y');
                $stmt = $pdo->prepare('UPDATE restaurante SET fecha_licencia=?');
                $stmt->execute([$nueva_fecha]);
                file_put_contents('../logs/acciones.log', date('c')." [INFO] Renovar licencia por {$_SESSION['usuario']} a $nueva_fecha\n", FILE_APPEND);
            }
            header('Location: dashboard.php#config-avanzada'); exit;
        }
    }
}

// --- PROCESAMIENTO DE ELIMINACIÓN Y ORDEN DE CATEGORÍAS/PLATOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ELIMINAR PLATO (AJAX o formulario)
    if ((isset($_POST['accion']) && $_POST['accion'] === 'eliminar_plato') && !empty($_POST['plato_id'])) {
        $plato_id = (int)$_POST['plato_id'];
        $stmt = $pdo->prepare('DELETE FROM platos WHERE id=?');
        $ok = $stmt->execute([$plato_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=plato_eliminado#platos');
            exit;
        }
    }
    // ELIMINAR CATEGORÍA (AJAX o formulario)
    if (
        (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && isset($_POST['eliminar']) && is_numeric($_POST['eliminar'])) ||
        (isset($_POST['accion']) && $_POST['accion'] === 'eliminar_categoria' && !empty($_POST['cat_id']) && is_numeric($_POST['cat_id']))
    ) {
        $cat_id = isset($_POST['eliminar']) ? (int)$_POST['eliminar'] : (int)$_POST['cat_id'];
        // Eliminar platos asociados primero (integridad referencial)
        $pdo->prepare('DELETE FROM platos WHERE categoria_id=?')->execute([$cat_id]);
        // Eliminar la categoría
        $ok = $pdo->prepare('DELETE FROM categorias WHERE id=?')->execute([$cat_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => $ok]);
            exit;
        } else {
            header('Location: dashboard.php?ok=categorias#categorias');
            exit;
        }
    }
    // GUARDAR ORDEN Y NOMBRES DE CATEGORÍAS
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_categorias' && !empty($_POST['orden']) && !empty($_POST['nombres'])) {
        $orden = explode(',', $_POST['orden']);
        $nombres = explode('|', $_POST['nombres']);
        foreach ($orden as $i => $cat_id) {
            $nombre = isset($nombres[$i]) ? trim($nombres[$i]) : '';
            if ($nombre !== '') {
                $stmt = $pdo->prepare('UPDATE categorias SET nombre=?, orden=? WHERE id=?');
                $stmt->execute([$nombre, $i, $cat_id]);
            }
        }
        header('Location: dashboard.php?ok=categorias#categorias');
        exit;
    }
    // GUARDAR ORDEN DE PLATOS (por categoría)
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar_platos' && !empty($_POST['orden_platos']) && is_array($_POST['orden_platos'])) {
        foreach ($_POST['orden_platos'] as $cat_id => $orden_str) {
            $ids = explode(',', $orden_str);
            foreach ($ids as $i => $plato_id) {
                $stmt = $pdo->prepare('UPDATE platos SET orden=? WHERE id=? AND categoria_id=?');
                $stmt->execute([$i, $plato_id, $cat_id]);
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        } else {
            header('Location: dashboard.php?ok=platos#platos');
            exit;
        }
    }
}

// Mostrar confirmación visual si hay parámetro ok
$ok_msg = '';
if (isset($_GET['ok'])) {
    $ok_map = [
        'info' => 'Información guardada correctamente.',
        'categorias' => 'Categorías guardadas correctamente.',
        'platos' => 'Plato agregado correctamente.',
        'plato_eliminado' => 'Plato eliminado correctamente.',
        'seo' => 'SEO guardado correctamente.',
        'personalizacion' => 'Personalización guardada correctamente.',
        'config' => 'Configuración avanzada guardada correctamente.',
        'licencia' => 'Licencia renovada correctamente.'
    ];
    $ok = $_GET['ok'];
    if (isset($ok_map[$ok])) {
        $ok_msg = $ok_map[$ok];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Panel | <?= esc($rest['nombre'] ?? 'Restaurante') ?></title>
    <link rel="stylesheet" href="assets/style-dashboard.css">
    <style>body{background:#f7f7f7;}header{padding:1em 2em;background:#fff;box-shadow:0 2px 8px #0001;}main{max-width:900px;margin:2em auto;background:#fff;padding:2em;border-radius:10px;box-shadow:0 2px 12px #0001;}nav{margin-bottom:2em;}footer{margin-top:2em;text-align:center;color:#888;} .ok-msg { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 18px; border-radius: 6px; margin-bottom: 18px; font-size: 1.08em; text-align: center; position: relative; animation: fadein 0.4s; z-index: 10; transition: opacity 1.2s; opacity: 1; } .ok-msg.fadeout { opacity: 0; pointer-events: none; } @keyframes fadein { from { opacity: 0; } to { opacity: 1; } } .platos-categoria { margin-bottom: 32px; } .platos-lista { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; } .plato-item { display: flex; align-items: flex-start; background: #f7f7fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px 8px 8px 8px; gap: 12px; position: relative; min-height: 70px; } .plato-orden { font-weight: bold; color: #2196F3; min-width: 1.5em; text-align: right; margin-right: 6px; font-size: 1.1em; } .plato-img { width: 54px; height: 54px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; background: #fff; } .plato-info { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; gap: 2px; } .plato-nombre-precio { display: flex; align-items: center; gap: 10px; font-size: 1.08em; font-weight: 500; } .plato-precio { color: #388e3c; font-weight: bold; } .plato-desc { color: #555; font-size: 0.98em; margin-top: 2px; word-break: break-word; } .plato-actions { display: flex; flex-direction: column; gap: 4px; margin-left: 8px; } .plato-actions form { display: inline; } .btn-eliminar-plato { background: none; border: none; color: #e53935; font-size: 1.1em; cursor: pointer; padding: 2px 6px; border-radius: 4px; transition: background 0.2s; } .btn-eliminar-plato:hover { background: #ffeaea; } .drag-handle { cursor: move; font-size: 1.2em; color: #888; margin-left: 4px; margin-top: 2px; } @media (max-width: 700px) { .plato-item { flex-direction: column; align-items: stretch; } .plato-nombre-precio { flex-direction: column; align-items: flex-start; gap: 2px; } .plato-img { margin-bottom: 6px; } }
.btn-categoria.saved, .btn-guardar.saved, .btn-seo.saved, .btn-mapa.saved, .btn-paleta.saved, .btn-config.saved {
  background: #43a047 !important;
  color: #fff !important;
  transition: background 0.8s, color 0.8s;
}
/* Ocultar botones individuales de guardado (excepto agregar/eliminar) */
section#info button[type="submit"],
section#categorias #form-categorias button[type="submit"],
section#platos #form-platos button[type="submit"],
section#seo button[type="submit"],
section#mapa button[type="submit"],
section#personalizacion button[type="submit"],
section#config-avanzada button[type="submit"] {
  display: none !important;
}
/* Mostrar solo los botones de agregar/eliminar */
section#categorias form[method="post"] .btn-categoria,
section#platos .form-agregar-plato button[type="submit"],
.btn-eliminar,
.btn-eliminar-plato {
  display: inline-block !important;
}
</style>
</head>
<body>
<?php if ($ok_msg): ?>
<div class="ok-msg" id="ok-msg"><?= esc($ok_msg) ?></div>
<script>
setTimeout(function(){
  var el = document.getElementById('ok-msg');
  if (el) {
    el.classList.add('fadeout');
    setTimeout(function(){ el.style.display = 'none'; }, 1200);
  }
}, 5000);
</script>
<?php endif; ?>

<header>
    <img src="<?= esc($rest['logo']) ?>" alt="Logo" width="60" height="60" style="vertical-align:middle;border-radius:50%;">
    <span style="font-size:1.5em;font-weight:bold;margin-left:1em;">Panel de <?= esc($rest['nombre']) ?></span>
    <span style="float:right;"><a href="logout.php">Cerrar sesión</a></span>
</header>
<main>
    <!-- Control de licencia -->
    <div style='background: #e7f3fe; border-left: 4px solid #2196F3; padding: 10px; margin-bottom: 15px; font-size: 14px;'>
        📅 Licencia: <strong><?= ($dias_totales > 0 ? $dias_totales : 0) ?> días</strong> | Restantes: <strong><?= ($dias_restantes >= 0 ? $dias_restantes : 0) ?> días</strong> | Expira: <strong><?= esc($fecha_licencia) ?></strong>.
    </div>
    <?php if (in_array($dias_restantes, $avisos_criticos)): ?>
    <div style='background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; margin-bottom: 15px; font-weight: bold;'>
        ⚠️ Su licencia vencerá en <strong><?= $dias_restantes ?></strong> día<?= $dias_restantes==1?'':'s' ?>.
        <a href='https://wa.me/+569XXXXXXXXX' target='_blank' style='color:#155724;text-decoration:underline;'>Renueve por WhatsApp</a> para evitar la suspensión.
    </div>
    <?php endif; ?>
    <h2>Bienvenido, <?= esc($_SESSION['usuario']) ?> (<?= esc($_SESSION['rol']) ?>)</h2>
    <nav>
        <a href="#info">Información</a> |
        <a href="#categorias">Categorías</a> |
        <a href="#platos">Platos</a> |
        <a href="#seo">SEO</a> |
        <a href="#personalizacion">Personalización</a>
    </nav>
    <!-- Botón global de guardado (abajo del nav) -->
    <div id="guardar-todo-top" style="text-align:center;margin:18px 0;">
      <button id="btn-guardar-todo" class="btn-categoria" style="font-size:1.15em;padding:10px 32px;">💾 Guardar todos los cambios</button>
    </div>
    <section id="info">
        <h3>Información del restaurante</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="editar_restaurante">
            <label>Nombre:<input type="text" name="nombre" value="<?= esc($rest['nombre']) ?>" maxlength="64" required></label><br>
            <label>Dirección:<input type="text" name="direccion" value="<?= esc($rest['direccion']) ?>" maxlength="128"></label><br>
            <label>Horario:<input type="text" name="horario" value="<?= esc($rest['horario']) ?>" maxlength="32"></label><br>
            <label>Teléfono:<input type="text" name="telefono" value="<?= esc($rest['telefono']) ?>" maxlength="20"></label><br>
            <label>Facebook:<input type="url" name="facebook" value="<?= esc($rest['facebook']) ?>" pattern="https?://.+"></label><br>
            <label>Instagram:<input type="url" name="instagram" value="<?= esc($rest['instagram']) ?>" pattern="https?://.+"></label><br>
            <label>Logo:<input type="file" name="logo" accept="image/*"></label>
            <?php if ($rest['logo']): ?><img src="<?= esc($rest['logo']) ?>" alt="Logo" width="40" style="vertical-align:middle;"><?php endif; ?>
            <br>
            <label>Fondo header:<input type="file" name="fondo_header" accept="image/*"></label>
            <?php if (!empty($rest['fondo_header'])): ?><img src="<?= esc($rest['fondo_header']) ?>" alt="Fondo header" width="80" style="vertical-align:middle;max-width:200px;max-height:60px;object-fit:cover;border-radius:8px;"><?php endif; ?>
            <br>
            <!-- SISTEMA DE LICENCIAS VISIBLE Y FUNCIONAL -->
            <fieldset style="margin-top:18px;padding:12px 16px;border:1px solid #2196F3;border-radius:8px;background:#e7f3fe;">
                <legend style="color:#1769aa;font-weight:bold;">Licencia</legend>
                <div style="margin-bottom:8px;">
                    📅 Fecha de expiración: <strong><?= esc($fecha_licencia) ?></strong><br>
                    Días totales: <strong><?= ($dias_totales > 0 ? $dias_totales : 0) ?></strong><br>
                    Días restantes: <strong><?= ($dias_restantes >= 0 ? $dias_restantes : 0) ?></strong>
                </div>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="accion" value="guardar_config">
                    <label>Renovar licencia por
                        <input type="number" name="dias_renovar" min="1" max="365" value="30" style="width:60px;"> días
                    </label>
                    <button type="submit" class="btn-categoria" style="margin-left:8px;">Renovar</button>
                </form>
            </fieldset>
            <!-- MAPA GOOGLE MAPS EN INFO -->
            <fieldset style="margin-top:18px;padding:12px 16px;border:1px solid #2196F3;border-radius:8px;background:#f8fafd;">
                <legend style="color:#1769aa;font-weight:bold;">Mapa ubicación (iframe Google Maps)</legend>
                <form method="post">
                    <input type="hidden" name="accion" value="editar_mapa">
                    <textarea name="iframe_mapa" rows="3" style="width:100%;font-size:1em;" placeholder="Pega aquí el iframe de Google Maps"><?= esc($rest['iframe_mapa'] ?? '') ?></textarea><br>
                    <button type="submit" class="btn-categoria" style="margin-top:6px;">Guardar mapa</button>
                </form>
                <?php if (!empty($rest['iframe_mapa'])): ?>
                    <div style="margin-top:10px;max-width:100%;overflow:auto;">
                        <?= $rest['iframe_mapa'] ?>
                    </div>
                <?php endif; ?>
            </fieldset>
            <button type="submit">Guardar cambios</button>
        </form>
    </section>
    <section id="categorias">
        <h3>Categorías</h3>
        <form method="post" style="margin-bottom:1em;display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="accion" value="agregar_categoria">
            <input type="text" name="nombre" placeholder="Nueva categoría" required maxlength="32" style="flex:1;max-width:220px;">
            <button type="submit" class="btn-categoria">Agregar</button>
        </form>
        <form method="post" id="form-categorias">
        <div class="categorias-grid">
        <?php foreach (
            $categorias as $i => $cat): ?>
            <div class="categoria-item" draggable="true">
                <span class="cat-orden"><?= ($i+1) ?></span>
                <input type="hidden" name="cat_id[]" value="<?= $cat['id'] ?>">
                <input type="text" name="nombre[]" value="<?= esc($cat['nombre']) ?>" maxlength="32" required class="input-categoria">
                <button type="button" class="btn-eliminar-plato btn-eliminar-categoria" data-cat-id="<?= $cat['id'] ?>" title="Eliminar">🗑️</button>
                <span class="drag-handle" title="Arrastrar para reordenar">⇅</span>
            </div>
        <?php endforeach; ?>
        </div>
        <input type="hidden" name="accion" value="guardar_categorias">
        <button type="submit" class="btn-categoria" style="margin-top:12px;">Guardar cambios</button>
        </form>
        <style>
        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 1em;
        }
        .categoria-item {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 6px 6px 6px;
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            position: relative;
        }
        .cat-orden {
            font-weight: bold;
            color: #2196F3;
            margin-right: 6px;
            min-width: 1.5em;
            text-align: right;
        }
        .input-categoria {
            flex:1;
            min-width:0;
            font-size: 1em;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #fff;
        }
        .btn-categoria {
            background: #2196F3;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 5px 14px;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-categoria:hover { background: #1769aa; }
        .btn-eliminar {
            background: none;
            border: none;
            color: #e53935;
            font-size: 1.1em;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .btn-eliminar:hover { background: #ffeaea; }
        .drag-handle {
            cursor: move;
            font-size: 1.2em;
            color: #888;
            margin-left: 4px;
        }
        .placeholder {
            background: #e3e3e3;
            border: 2px dashed #2196F3;
            min-height: 38px;
        }
        @media (max-width: 700px) {
            .categorias-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <script>
        // Drag & Drop de categorías (orden universal)
        const lista = document.querySelector('.categorias-grid');
        let dragSrc = null;
        let placeholder = document.createElement('div');
        placeholder.className = 'categoria-item placeholder';
        placeholder.style.background = '#e3e3e3';
        placeholder.style.border = '2px dashed #2196F3';
        placeholder.style.minHeight = '38px';
        lista.querySelectorAll('.categoria-item').forEach(li => {
          li.draggable = true;
          li.ondragstart = e => {
            dragSrc = li;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => { li.style.display = 'none'; }, 0);
          };
          li.ondragend = e => {
            dragSrc = null;
            lista.querySelectorAll('.placeholder').forEach(p => p.remove());
            lista.querySelectorAll('.categoria-item').forEach(x => x.style.display = '');
          };
          li.ondragover = e => {
            e.preventDefault();
            if (li !== dragSrc && !li.classList.contains('placeholder')) {
              lista.insertBefore(placeholder, li);
            }
          };
          li.ondrop = e => {
            e.preventDefault();
            if (dragSrc && placeholder.parentNode === lista) {
              lista.insertBefore(dragSrc, placeholder);
              placeholder.remove();
              lista.querySelectorAll('.cat-orden').forEach((el, idx) => { el.textContent = idx+1; });
            }
          };
        });
        lista.ondragover = e => {
          e.preventDefault();
          if (!lista.querySelector('.placeholder')) {
            lista.appendChild(placeholder);
          }
        };
        lista.ondrop = e => {
          e.preventDefault();
          if (dragSrc && placeholder.parentNode === lista) {
            lista.insertBefore(dragSrc, placeholder);
            placeholder.remove();
            lista.querySelectorAll('.cat-orden').forEach((el, idx) => { el.textContent = idx+1; });
          }
        };
        // Al guardar, enviar el nuevo orden y nombres
        document.getElementById('form-categorias').onsubmit = function(e) {
          // Si se presionó eliminar, no hacer nada especial
          if (document.activeElement && document.activeElement.name === 'eliminar') return;
          // Reordenar los cat_id[] según el orden visual
          const items = Array.from(lista.children).filter(x => x.classList.contains('categoria-item'));
          const orden = items.map(x => x.querySelector('input[name="cat_id[]"]').value);
          const nombres = items.map(x => x.querySelector('input[name="nombre[]"]').value);
          // Crear campos ocultos para el orden
          let ordenInput = document.createElement('input');
          ordenInput.type = 'hidden';
          ordenInput.name = 'orden';
          ordenInput.value = orden.join(',');
          this.appendChild(ordenInput);
          // Crear campos ocultos para los nombres
          let nombresInput = document.createElement('input');
          nombresInput.type = 'hidden';
          nombresInput.name = 'nombres';
          nombresInput.value = nombres.join('|');
          this.appendChild(nombresInput);
        };
        </script>
    </section>
    <section id="platos">
        <h3>Platos</h3>
        <form method="post" enctype="multipart/form-data" class="form-agregar-plato">
            <input type="hidden" name="accion" value="agregar_plato">
            <select name="categoria_id" required>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= esc($cat['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="nombre" placeholder="Nombre del plato" required maxlength="64">
            <input type="text" name="descripcion" placeholder="Descripción (máx 120 caracteres)" maxlength="120">
            <input type="number" name="precio" placeholder="Precio CLP" required min="0">
            <input type="file" name="imagen" accept="image/*">
            <button type="submit">Agregar plato</button>
        </form>
        <form method="post" id="form-platos">
            <input type="hidden" name="accion" value="guardar_platos">
            <?php foreach ($categorias as $cat): ?>
            <div class="platos-categoria">
                <h4><?= esc($cat['nombre']) ?></h4>
                <ul class="platos-lista" data-cat="<?= $cat['id'] ?>">
                <?php
                $stmt = $pdo->prepare('SELECT * FROM platos WHERE categoria_id=? ORDER BY orden ASC');
                $stmt->execute([$cat['id']]);
                $platos = $stmt->fetchAll(PDO::FETCH_ASSOC); // <-- Resetear $platos en cada iteración
                foreach ($platos as $i => $plato): ?>
                    <li class="plato-item" data-id="<?= $plato['id'] ?>" draggable="true">
                        <span class="plato-orden"><?= ($i+1) ?></span>
                        <?php if ($plato['imagen']): ?>
                            <img src="<?= esc($plato['imagen']) ?>" class="plato-img" alt="Imagen plato">
                        <?php else: ?>
                            <div class="plato-img" style="display:flex;align-items:center;justify-content:center;color:#bbb;">—</div>
                        <?php endif; ?>
                        <div class="plato-info">
                            <div class="plato-nombre-precio">
                                <input type="hidden" name="plato_id[]" value="<?= $plato['id'] ?>">
                                <input type="text" name="nombre[]" value="<?= esc($plato['nombre']) ?>" maxlength="64" class="input-inline" required style="width:120px;">
                                <input type="number" name="precio[]" value="<?= $plato['precio'] ?>" min="0" class="input-inline" required style="width:80px;">
                            </div>
                            <div class="plato-desc">
                                <textarea name="descripcion[]" maxlength="120" rows="2" class="input-inline" style="width:98%;resize:vertical;"><?= esc($plato['descripcion']) ?></textarea>
                            </div>
                        </div>
                        <div class="plato-actions">
                            <button type="button" class="btn-eliminar-plato" data-plato-id="<?= $plato['id'] ?>" title="Eliminar">🗑️</button>
                        </div>
                        <span class="drag-handle" title="Arrastrar para reordenar">⇅</span>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn-categoria" style="margin-top:16px;">Guardar cambios</button>
        </form>
        <script>
        // Drag & Drop de platos por categoría con numeración visual y reordenamiento
        function initPlatosDragDrop() {
          document.querySelectorAll('.platos-lista').forEach(function(lista) {
            let dragSrc;
            let placeholder = document.createElement('li');
            placeholder.className = 'plato-item placeholder';
            placeholder.style.background = '#e3e3e3';
            placeholder.style.border = '2px dashed #2196F3';
            placeholder.style.minHeight = '54px';
            lista.querySelectorAll('.plato-item').forEach(function(li) {
              li.draggable = true;
              li.ondragstart = e => { dragSrc = li; e.dataTransfer.effectAllowed = 'move'; setTimeout(()=>{li.style.display='none';},0); };
              li.ondragend = e => { dragSrc = null; lista.querySelectorAll('.plato-item').forEach(x => x.style.display = ''); lista.querySelectorAll('.placeholder').forEach(p => p.remove()); };
              li.ondragover = e => { e.preventDefault(); if (li !== dragSrc && !li.classList.contains('placeholder')) { lista.insertBefore(placeholder, li); } };
              li.ondrop = e => { e.preventDefault(); if (dragSrc && placeholder.parentNode === lista) { lista.insertBefore(dragSrc, placeholder); placeholder.remove(); lista.querySelectorAll('.plato-orden').forEach((el, idx) => { el.textContent = idx+1; }); } };
            });
            lista.ondragover = e => { e.preventDefault(); if (!lista.querySelector('.placeholder')) { lista.appendChild(placeholder); } };
            lista.ondrop = e => { e.preventDefault(); if (dragSrc && placeholder.parentNode === lista) { lista.insertBefore(dragSrc, placeholder); placeholder.remove(); lista.querySelectorAll('.plato-orden').forEach((el, idx) => { el.textContent = idx+1; }); } };
          });
        }
        initPlatosDragDrop();
        // Al guardar platos, enviar el orden correcto
        document.getElementById('form-platos').onsubmit = function(e) {
          // Recolectar el orden de cada lista
          this.querySelectorAll('input[name^="orden_platos"]').forEach(x => x.remove());
          document.querySelectorAll('.platos-lista').forEach(function(lista) {
            const catId = lista.getAttribute('data-cat');
            const items = Array.from(lista.children).filter(x => x.classList.contains('plato-item'));
            const ids = items.map(x => x.getAttribute('data-id'));
            let input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'orden_platos['+catId+']';
            input.value = ids.join(',');
            document.getElementById('form-platos').appendChild(input);
          });
        };
        // Eliminar plato al hacer click en el icono (AJAX, sin recargar)
        function mostrarOkMsg(msg) {
          let el = document.getElementById('ok-msg');
          if (!el) {
            el = document.createElement('div');
            el.id = 'ok-msg';
            el.className = 'ok-msg';
            document.body.insertBefore(el, document.body.firstChild);
          }
          el.textContent = msg;
          el.classList.remove('fadeout');
          setTimeout(function(){ el.classList.add('fadeout'); setTimeout(function(){ el.style.display = 'none'; }, 1200); }, 3500);
          el.style.display = '';
        }
        function initEliminarPlato() {
          document.querySelectorAll('.btn-eliminar-plato').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
              e.preventDefault();
              // Confirmación visual personalizada
              if (window.confirmandoPlato) return; // Evita doble click
              window.confirmandoPlato = true;
              mostrarOkMsg('Eliminando plato...');
              const platoId = btn.getAttribute('data-plato-id');
              if (!platoId) return;
              const formData = new FormData();
              formData.append('accion', 'eliminar_plato');
              formData.append('plato_id', platoId);
              fetch('dashboard.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
              })
              .then(r => r.json())
              .then(data => {
                window.confirmandoPlato = false;
                if (data && data.ok) {
                  const li = btn.closest('.plato-item');
                  if (li) li.remove();
                  mostrarOkMsg('Plato eliminado correctamente.');
                } else {
                  mostrarOkMsg(data && data.msg ? data.msg : 'Error al eliminar.');
                }
              })
              .catch(() => {
                window.confirmandoPlato = false;
                mostrarOkMsg('Error al eliminar. Intente nuevamente.');
              });
            });
          });
        }
        initEliminarPlato();
        // Eliminar categoría al hacer click en el icono (AJAX, sin recargar)
        function initEliminarCategoria() {
          document.querySelectorAll('.btn-eliminar-categoria').forEach(function(btn) {
            btn.removeEventListener('click', btn._eliminarCatHandler); // Limpia handlers previos
            btn._eliminarCatHandler = function(e) {
              e.preventDefault();
              e.stopPropagation();
              if (window.confirmandoCategoria) return;
              window.confirmandoCategoria = true;
              mostrarOkMsg('Eliminando categoría...');
              const catId = btn.getAttribute('data-cat-id');
              if (!catId) return;
              const formData = new FormData();
              formData.append('accion', 'eliminar_categoria');
              formData.append('cat_id', catId);
              fetch('dashboard.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
              })
              .then(r => r.json())
              .then(data => {
                window.confirmandoCategoria = false;
                if (data && data.ok) {
                  const div = btn.closest('.categoria-item');
                  if (div) div.remove();
                  mostrarOkMsg('Categoría eliminada correctamente.');
                } else {
                  mostrarOkMsg(data && data.msg ? data.msg : 'Error al eliminar.');
                }
              })
              .catch(() => {
                window.confirmandoCategoria = false;
                mostrarOkMsg('Error al eliminar. Intente nuevamente.');
              });
            };
            btn.addEventListener('click', btn._eliminarCatHandler);
          });
        }
        initEliminarCategoria();
        // Confirmación visual en los botones globales de guardado
        function feedbackBotonGlobal(btns, textoOk = '¡Guardado!', textoError = 'Error al guardar', textoNormal = 'Guardar todos los cambios') {
          btns.forEach(btn => {
            if (!btn) return;
            btn.addEventListener('click', function() {
              btn.disabled = true;
              const textoOriginal = btn.textContent;
              btn.textContent = 'Guardando...';
              btn.classList.remove('saved');
            });
          });
        }
        feedbackBotonGlobal([
          document.getElementById('btn-guardar-todo'),
          document.getElementById('btn-guardar-todo-center'),
          document.getElementById('btn-guardar-todo-bottom')
        ]);
        // Guardado global de todos los cambios
        function guardarTodoHandler(e) {
          e.preventDefault();
          const btns = [
            document.getElementById('btn-guardar-todo'),
            document.getElementById('btn-guardar-todo-center'),
            document.getElementById('btn-guardar-todo-bottom')
          ].filter(Boolean);
          btns.forEach(btn => { btn.disabled = true; btn.textContent = 'Guardando...'; btn.classList.remove('saved'); });
          // Recopilar datos de todos los formularios editables
          const forms = [
            document.querySelector('form[action=""]:not(.form-agregar-plato):not(#form-categorias):not(#form-platos)'),
            document.getElementById('form-categorias'),
            document.getElementById('form-platos'),
            document.querySelector('section#seo form'),
            document.querySelector('section#mapa form'),
            document.querySelector('section#personalizacion form'),
            document.querySelector('section#config-avanzada form')
          ];
          let data = { accion: 'guardar_todo' };
          forms.forEach(form => {
            if (!form) return;
            if (form.id === 'form-platos') {
              form.querySelectorAll('input[name^="orden_platos"]').forEach(x => x.remove());
              document.querySelectorAll('.platos-lista').forEach(function(lista) {
                const catId = lista.getAttribute('data-cat');
                const items = Array.from(lista.children).filter(x => x.classList.contains('plato-item'));
                const ids = items.map(x => x.getAttribute('data-id'));
                let input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'orden_platos['+catId+']';
                input.value = ids.join(',');
                form.appendChild(input);
              });
            }
            if (form.id === 'form-categorias') {
              const lista = document.querySelector('.categorias-grid');
              const items = Array.from(lista.children).filter(x => x.classList.contains('categoria-item'));
              const orden = items.map(x => x.querySelector('input[name="cat_id[]"]').value);
              const nombres = items.map(x => x.querySelector('input[name="nombre[]"]').value);
              let ordenInput = document.createElement('input');
              ordenInput.type = 'hidden';
              ordenInput.name = 'orden';
              ordenInput.value = orden.join(',');
              form.appendChild(ordenInput);
              let nombresInput = document.createElement('input');
              nombresInput.type = 'hidden';
              nombresInput.name = 'nombres';
              nombresInput.value = nombres.join('|');
              form.appendChild(nombresInput);
            }
            Object.assign(data, getFormDataAsObject(form));
          });
          fetch('dashboard.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          })
          .then(r => r.json())
          .then(resp => {
            btns.forEach(btn => {
              if (resp && resp.ok) {
                btn.textContent = '¡Guardado!';
                btn.classList.add('saved');
                mostrarOkMsg('¡Guardado correctamente!');
                setTimeout(function(){
                  btn.textContent = '💾 Guardar todos los cambios';
                  btn.classList.remove('saved');
                  btn.disabled = false;
                }, 3500);
              } else {
                btn.textContent = 'Error al guardar';
                btn.classList.remove('saved');
                mostrarOkMsg(resp && resp.msg ? resp.msg : 'Error al guardar.');
                setTimeout(function(){
                  btn.textContent = '💾 Guardar todos los cambios';
                  btn.disabled = false;
                }, 3500);
              }
            });
            document.querySelectorAll('#form-platos input[name^="orden_platos"]').forEach(x => x.remove());
            document.querySelectorAll('#form-categorias input[name="orden"], #form-categorias input[name="nombres"]').forEach(x => x.remove());
          })
          .catch(() => {
            btns.forEach(btn => {
              btn.textContent = 'Error al guardar';
              btn.classList.remove('saved');
              mostrarOkMsg('Error al guardar.');
              setTimeout(function(){
                btn.textContent = '💾 Guardar todos los cambios';
                btn.disabled = false;
              }, 3500);
            });
          });
        }
        ['btn-guardar-todo','btn-guardar-todo-center','btn-guardar-todo-bottom'].forEach(id => {
          const btn = document.getElementById(id);
          if (btn) btn.addEventListener('click', guardarTodoHandler);
        });
        </script>
    </section>
</main>
</body>
</html>