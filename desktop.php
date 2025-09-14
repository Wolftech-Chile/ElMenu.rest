<?php
// desktop.php - Panel de administración principal
// Versión: Diseño profesional minimalista con modo claro/oscuro

require_once 'config.php';
require_once 'core/Database.php';
require_once 'includes/helpers.php';
require_once 'core/License.php';
require_once 'models/Config.php';
require_once 'models/Categoria.php';
require_once 'models/Plato.php';
require_once 'core/Auth.php';
require_once 'includes/temas.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Autenticación -------------------------
session_start();
if (empty($_SESSION['usuario_id']) || empty($_SESSION['rol'])) {
    header('Location: login.php');
    exit;
}
$es_admin = ($_SESSION['rol'] === 'admin');

// --- Datos generales -----------------------
$pdo     = Database::get();
$config  = new Config($pdo);
$license = new License($pdo);
$lic     = $license->getInfo();
$rest    = $config->get();
$temas   = ThemeManager::getTemas();
// Verificar columna sitemap_xml
try{
    $colExists=false;
    foreach($pdo->query("PRAGMA table_info(restaurante)") as $row){
        if($row['name']=='sitemap_xml') $colExists=true;
        if($row['name']=='seo_img_alt') $colAlt=true;
    }
    if(!$colExists){ $pdo->exec("ALTER TABLE restaurante ADD COLUMN sitemap_xml TEXT"); }
    if(empty($colAlt)){ $pdo->exec("ALTER TABLE restaurante ADD COLUMN seo_img_alt TEXT"); }
}catch(Exception $e){ /* ignore */ }
$categoriaModel = new Categoria($pdo);
$cats = $categoriaModel->all();
$platoModel = new Plato($pdo);
$platosAll = $platoModel->all();
$platosByCat = [];
$platosIndex = [];
$userManager = new UserManager($pdo);
$users = $es_admin ? $pdo->query('SELECT id, usuario, email, rol FROM usuarios')->fetchAll(PDO::FETCH_ASSOC) : [];
foreach($platosAll as $p){
    $platosByCat[$p['categoria_id']][] = $p;
    $platosIndex[$p['id']] = $p;
}

$csrf = csrf_token(); // Generar token para AJAX

/* ---------- AJAX actions for license ------------- */
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Función auxiliar para logs
function debug_log($message, $data = null) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    if ($data !== null) {
        $log .= 'Data: ' . print_r($data, true) . "\n";
    }
    // Añadir información de error si hay algún problema al escribir
    $result = @file_put_contents(__DIR__ . '/debug_users.log', $log, FILE_APPEND);
    if ($result === false) {
        error_log('Error al escribir en debug_users.log. Último error: ' . json_encode(error_get_last()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si el directorio es escribible
    $logFile = __DIR__ . '/debug_users.log';
    $logDir = dirname($logFile);
    
    if (!is_writable($logDir)) {
        error_log("El directorio $logDir no tiene permisos de escritura");
    }
    
    // Registrar información detallada de la solicitud
    debug_log('========================================');
    debug_log('Inicio de solicitud POST', [
        'POST' => $_POST,
        'SERVER' => [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ]
    ]);
    
    // Verificar si el archivo de log es escribible
    if (file_exists($logFile) && !is_writable($logFile)) {
        error_log("El archivo $logFile no tiene permisos de escritura");
    }
    
    // Configuración para manejo de respuestas AJAX/JSON
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Función auxiliar para respuestas JSON consistentes
    $responderJSON = function($data, $status = 200) use ($isAjax) {
        if ($isAjax) {
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        return $data;
    };
    
    // Validar token CSRF para todas las acciones POST
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $responderJSON(['ok' => false, 'error' => 'Token CSRF inválido'], 403);
    }
    
    csrf_validate(false); // Validación adicional de CSRF para compatibilidad
    $accion = $_POST['accion'] ?? '';
    
    switch ($accion) {
        case 'editar_licencia_manual':
            if (!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso denegado'],403);
            $fecha   = $_POST['fecha_inicio'] ?? '';
            $diasTot = (int)($_POST['dias_total'] ?? 0);
            // Convertir YYYY-mm-dd a dd-mm-YYYY para License
            if (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $fecha, $m)) {
                $fecha = $m[3].'-'.$m[2].'-'.$m[1];
            }
            $ok = $license->updateManualStart($fecha, $diasTot);
            $lic = $license->getInfo();
            responderJSON(['ok'=>$ok,'lic'=>$lic]);
            break;
        case 'renovar_licencia':
            if (!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso denegado'],403);
            $dias = (int)($_POST['dias'] ?? 0);
            $ok = $license->renew($dias);
            $lic = $license->getInfo();
            responderJSON(['ok'=>$ok,'lic'=>$lic]);
            break;

        case 'guardar_seo':
            // Guardar meta tags SEO
            $seo = [
                'meta_descripcion'=>trim($_POST['meta_descripcion'] ?? ''),
                'meta_keywords'=>trim($_POST['meta_keywords'] ?? ''),
                'google_analytics'=>trim($_POST['google_analytics'] ?? ''),
                'google_search_console'=>trim($_POST['google_search_console'] ?? ''),
                 'seo_img_alt'=>trim($_POST['seo_img_alt'] ?? '')
            ];
            if (isset($_FILES['seo_img']) && $_FILES['seo_img']['error'] === UPLOAD_ERR_OK) {
                $path = procesarImagen($_FILES['seo_img'],'seo',1200);
                if ($path) $seo['seo_img']=$path;
            }
            // Guardar iframe mapa SIEMPRE (permite borrar)
            $iframe = $_POST['iframe_mapa'] ?? '';
            $seo['iframe_mapa'] = $iframe;
            $ok = $config->setSeo($seo);
            responderJSON(['ok'=>$ok]);
            break;
        case 'guardar_tema':
            $tema = preg_replace('/[^a-z0-9_-]/i','', $_POST['tema'] ?? '');
            if(!ThemeManager::validarTema($tema)) responderJSON(['ok'=>false,'msg'=>'Tema inválido']);
            $ok = $config->setTheme($tema);
            responderJSON(['ok'=>$ok]);
            break;
        case 'agregar_categoria':
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre==='') responderJSON(['ok'=>false,'msg'=>'Nombre vacío']);
            $id = $categoriaModel->create($nombre);
            responderJSON(['ok'=>true,'id'=>$id]);
            break;
        case 'verificar_platos_categoria':
            $id = (int)($_POST['cat_id'] ?? 0);
            if ($id<=0) responderJSON(['ok'=>false,'msg'=>'ID inválido']);
            $tienePlatos = $categoriaModel->hasPlatos($id);
            responderJSON(['ok'=>true, 'tiene_platos'=>$tienePlatos]);
            break;
            
        case 'eliminar_categoria':
            $id = (int)($_POST['cat_id'] ?? 0);
            if ($id<=0) responderJSON(['ok'=>false,'msg'=>'ID inválido']);
            try {
                $categoriaModel->delete($id);
                responderJSON(['ok'=>true]);
            } catch (Exception $e) {
                responderJSON(['ok'=>false, 'msg'=>$e->getMessage()]);
            }
            break;
        case 'guardar_categorias':
            $orden = $_POST['orden'] ?? '';
            $nombresStr = $_POST['nombres'] ?? '';
            $ids = array_map('intval', explode(',', $orden));
            $nombres = explode('|', $nombresStr);
            foreach ($ids as $idx=>$id){
                $categoriaModel->update($id,$nombres[$idx] ?? '',$idx+1);
            }
            responderJSON(['ok'=>true]);
            break;
        case 'agregar_plato':
            $catId = (int)($_POST['categoria_id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            // normalizar precio (elim $ y .)
            $precioRaw = $_POST['precio'] ?? '0';
            $precio = (float) str_replace(['$', '.'], '', $precioRaw);
            $imgPath = procesarImagen($_FILES['imagen'] ?? null,'plato',800);
             if($imgPath){
                 // Asegurarse de ruta relativa para guardado
                 $imgPath = ltrim($imgPath,'/');
             }
            if($catId<=0||$nombre==='') responderJSON(['ok'=>false,'msg'=>'Datos']);
            $id = $platoModel->create($catId,$nombre,$descripcion,$precio,$imgPath);
            responderJSON(['ok'=>true,'id'=>$id]);
            break;

        case 'eliminar_plato':
            $id = (int)($_POST['plato_id'] ?? 0);
            if($id<=0) responderJSON(['ok'=>false]);
            $platoModel->delete($id);
            responderJSON(['ok'=>true]);
            break;
        case 'guardar_platos':
            $ids = $_POST['plato_id'] ?? [];
            if(!$ids){ responderJSON(['ok'=>false,'msg'=>'Sin datos']); }
            $names = $_POST['nombre'] ?? [];
            $descs = $_POST['descripcion'] ?? [];
            $prices= $_POST['precio'] ?? [];
            $ordenes = $_POST['orden_platos'] ?? [];
            $platoMd = new Plato($pdo);
            $datos=[];
            foreach($ids as $i=>$pid){
                $datos[$pid] = [
                    'nombre'=>$names[$i] ?? '',
                    'descripcion'=>$descs[$i] ?? '',
                    'precio'=> (float) str_replace(['$', '.'], '', $prices[$i] ?? 0)
                ];
            }
            foreach($ordenes as $cat=>$cadena){
                $idList = array_filter(explode(',', $cadena));
                foreach($idList as $idx=>$pid){
                    if(!isset($datos[$pid])) continue;
                    $old = $platoMd->find((int)$pid);
                    $imgPath = $old['imagen'] ?? '';
                    $platoMd->update((int)$pid,(int)$cat,$datos[$pid]['nombre'],$datos[$pid]['descripcion'],$datos[$pid]['precio'],$imgPath,$idx+1);
                }
            }
            responderJSON(['ok'=>true]);
            break;
        case 'mover_plato':
            $platoId = (int)($_POST['plato_id'] ?? 0);
            $newCat  = (int)($_POST['categoria_id'] ?? 0);
            if($platoId<=0||$newCat<=0) responderJSON(['ok'=>false,'msg'=>'Datos']);
            // Obtener nuevo orden al final
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(orden),0)+1 AS nuevo FROM platos WHERE categoria_id = ?');
            $stmt->execute([$newCat]);
            $newOrden = (int)($stmt->fetchColumn() ?: 1);
            $pdo->prepare('UPDATE platos SET categoria_id = ?, orden = ? WHERE id = ?')->execute([$newCat,$newOrden,$platoId]);
            responderJSON(['ok'=>true]);
            break;
        case 'cambiar_img_plato':
            $pid = (int)($_POST['plato_id'] ?? 0);
            if($pid<=0 || empty($_FILES['imagen']['tmp_name'])) responderJSON(['ok'=>false, 'msg'=>'Datos']);
            $imgPath = 'uploads/platos/'.uniqid().'.jpg';
            if(!is_dir('uploads/platos')) mkdir('uploads/platos',0777,true);
            move_uploaded_file($_FILES['imagen']['tmp_name'],$imgPath);
            $p = $platoModel->find($pid);
            if(!$p) responderJSON(['ok'=>false]);
            $platoModel->update($pid, $p['categoria_id'], $p['nombre'], $p['descripcion'], $p['precio'], $imgPath, $p['orden']);
            responderJSON(['ok'=>true,'path'=>$imgPath]);
            break;
        
        case 'guardar_rest':
            // Campos básicos
            $fields = ['nombre','slogan','direccion','telefono','horario','facebook','instagram','ciudad','region','tipo_cocina','sitemap_xml','iframe_mapa'];
            $data=[];
            foreach($fields as $f){
              if($f === 'iframe_mapa'){
                // Guardar el HTML tal cual, sin escape
                $data[$f] = isset($_POST[$f]) ? $_POST[$f] : '';
              } else {
                $data[$f] = trim($_POST[$f] ?? '');
              }
            }
            // Procesar logo y header img
            if(!empty($_FILES['logo']['tmp_name'])){
                $logoPath = 'uploads/logo.png';
                move_uploaded_file($_FILES['logo']['tmp_name'],$logoPath);
                $data['logo'] = $logoPath;
            }
            if(!empty($_FILES['header_img']['tmp_name'])){
                $hdrPath = 'uploads/header.jpg';
                move_uploaded_file($_FILES['header_img']['tmp_name'],$hdrPath);
                $data['fondo_header']=$hdrPath;
            }
            $ok = $config->update($data);
            responderJSON(['ok'=>$ok]);
            break;
        case 'generar_sitemap':
            // Generar sitemap XML simple
            $base = rtrim($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'],'/');
            $urls = [
                $base . '/',
            ];
            foreach($categoriaModel->all() as $c){
                $urls[] = $base.'/categoria.php?id='.$c['id'];
            }
            foreach($platoModel->all() as $p){
                $urls[] = $base.'/plato.php?id='.$p['id'];
            }
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">";
            foreach($urls as $u){
                $xml .= "\n  <url><loc>{$u}</loc></url>";
            }
            $xml .= "\n</urlset>";
            file_put_contents(__DIR__.'/sitemap.xml',$xml);
            $config->update(['sitemap_xml'=>'sitemap.xml']);
            responderJSON(['ok'=>true]);
            break;
        case 'seo_check':
            // Auditoría SEO básica – simula cómo un bot ve la página
            // Generar HTML de la home directamente (sin llamada HTTP) para evitar bloqueos
            ob_start();
            include __DIR__ . '/index.php';
            $html = ob_get_clean();
            if(!$html){
                responderJSON(['ok'=>false,'msg'=>'No se pudo generar el HTML de la home'],500);
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xp = new DOMXPath($dom);

            $overall='OK';
            $rows = [];
            $add = function (string $el, string $state, string $detail, string $val = '') use (&$rows, &$overall) {
                $rows[] = [$el, $state, $detail, $val];
                if($state==='ERROR') $overall='ERROR';
                elseif($state==='WARNING' && $overall==='OK') $overall='WARNING';
            };

            // Título
            $tNodes = $xp->query('//title');
            $title = $tNodes->length ? trim($tNodes->item(0)->textContent) : '';
            if ($title === '') {
                $add('Título', 'ERROR', 'No se encontró');
            } else {
                $len = mb_strlen($title);
                $add('Título', $len > 60 ? 'WARNING' : 'OK', 'Longitud ' . $len, $title);
            }

            // Meta descripción
            $descNode = $xp->query('//meta[@name="description"]')->item(0);
            $desc = $descNode ? trim($descNode->getAttribute('content')) : '';
            if ($desc === '') {
                $add('Meta descripción', 'ERROR', 'No se encontró');
            } else {
                $len = mb_strlen($desc);
                $add('Meta descripción', ($len < 80 || $len > 160) ? 'WARNING' : 'OK', 'Longitud ' . $len, $desc);
            }

            // Meta keywords
            $kwNode = $xp->query('//meta[@name="keywords"]')->item(0);
            $keywords = $kwNode ? trim($kwNode->getAttribute('content')) : '';
            if($keywords===''){
                $add('Meta keywords','WARNING','Vacío');
            }else{
                $kwCount = count(array_filter(array_map('trim', explode(',', $keywords))));
                $state = ($kwCount>10)?'WARNING':'OK';
                $add('Meta keywords',$state,$kwCount.' palabras', $keywords);
            }

            // Google Analytics
            $gaId = '';
            // extraer ID almacenado en BD
            foreach($pdo->query("SELECT google_analytics FROM restaurante LIMIT 1") as $rowGa){$gaId=trim($rowGa['google_analytics']);}
            if($gaId!==''){
                $gaNode = $xp->query("//script[contains(text(), '$gaId')]")->item(0);
                $add('Google Analytics', $gaNode?'OK':'ERROR', $gaNode?'Encontrado':'No encontrado', $gaId);
            }
            // Google Search Console
            $gscId='';
            foreach($pdo->query("SELECT google_search_console FROM restaurante LIMIT 1") as $rowGsc){$gscId=trim($rowGsc['google_search_console']);}
            if($gscId!==''){
                $gscNode=$xp->query("//meta[@name='google-site-verification' and @content='$gscId']")->item(0);
                $add('Search Console', $gscNode?'OK':'ERROR', $gscNode?'Encontrado':'No encontrado', $gscId);
            }

            // Imagen SEO (og:image)
            $ogImgNode = $xp->query('//meta[@property="og:image" or @name="og:image"]')->item(0);
            $ogImg = $ogImgNode ? trim($ogImgNode->getAttribute('content')) : '';
            if($ogImg===''){
                $add('Imagen SEO','ERROR','og:image no encontrado');
            }else{
                // Comprobar alt
                $imgAltOk=false;
                $altText='';
                // Revisar meta og:image:alt
                $altMeta = $xp->query('//meta[@property="og:image:alt" or @name="og:image:alt"]')->item(0);
                if($altMeta && trim($altMeta->getAttribute('content'))!==''){
                    $altText=trim($altMeta->getAttribute('content'));
                    $imgAltOk=true;
                } else {
                    foreach($xp->query('//img[contains(@src, "'.basename($ogImg).'")]') as $im){
                        $alt = trim($im->getAttribute('alt'));
                        if($alt!==''){ $altText=$alt; $imgAltOk=true; break; }
                    }
                }
                $add('Imagen SEO', $imgAltOk?'OK':'WARNING', $imgAltOk?'Con alt':'Sin alt', $altText ?: $ogImg);
            }

            // Canonical
            $canonNode = $xp->query('//link[@rel="canonical"]')->item(0);
            if (!$canonNode) {
                $add('Canonical', 'ERROR', 'No existe');
            } else {
                $add('Canonical', 'OK', 'Encontrado', $canonNode->getAttribute('href'));
            }

            // Schema.org
            $schemaOk = false;
            foreach ($xp->query('//script[@type="application/ld+json"]') as $scr) {
                $json = json_decode($scr->textContent, true);
                if (!$json || empty($json['@type'])) continue;
                $type = is_array($json['@type']) ? implode(',', $json['@type']) : $json['@type'];
                if (stripos($type, 'Restaurant') !== false) {
                    $schemaOk = true;
                    $missing = [];
                    // Función recursiva para buscar clave
                     $findKey=function($obj,$key) use (&$findKey){
                         if(is_array($obj)){
                             if(array_key_exists($key,$obj) && !empty($obj[$key])) return true;
                             foreach($obj as $v){ if($findKey($v,$key)) return true; }
                         }
                         return false;
                     };
                     // Función para aplanar JSON y generar filas
                     $flatten = function($obj,$prefix='') use (&$flatten,&$add){
                         if(is_array($obj)){
                             foreach($obj as $k=>$v){
                                 if($k==='@type') continue;
                                 $flatten($v, $prefix===''?$k:$prefix.'.'.$k);
                             }
                         }else{
                             $val = is_bool($obj)? ($obj?'true':'false') : trim((string)$obj);
                             $add('Schema '.$prefix, $val===''?'ERROR':'OK', $val===''?'Vacío':'Encontrado', $val);
                         }
                     };
                     foreach (['telephone','servesCuisine','name','address','image'] as $req) {
                         if(!$findKey($json,$req)) $missing[]=$req;
                     }
                    if ($missing) {
                        // Resumen omitido intencionalmente
                        $flatten($json);
                    } else {
                        // Resumen omitido intencionalmente
                        $flatten($json);
                    }
                    break;
                }
            }
            if (!$schemaOk) $add('Schema.org', 'ERROR', 'No se encontró');

            // Sitemap XML
            $sitemapFile = __DIR__ . '/sitemap.xml';
            $siteOk = is_file($sitemapFile);
            $add('Sitemap XML', $siteOk ? 'OK' : 'ERROR', $siteOk ? 'Accesible' : 'No accesible', $sitemapFile);

            // Iframe mapa
            $iframeOk = (bool)$xp->query('//iframe[contains(@src, "google.com/maps")]')->length;
            $add('Iframe mapa', $iframeOk ? 'OK' : 'ERROR', $iframeOk ? 'Encontrado' : 'No encontrado');

            // CSV
            $csv = "Elemento,Estado,Detalle,Valor\n";
            foreach ($rows as $r) {
                $csv .= '"' . implode('","', array_map(fn($v) => str_replace('"', '""', $v), $r)) . "\n";
            }
            responderJSON(['ok'=>true,'rows'=>$rows,'csv'=>base64_encode($csv),'overall'=>$overall]);
            break;

        case 'guardar_footer':
            $footer = trim($_POST['footer'] ?? '');
            if(!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso'],403);
            $footer = trim($_POST['footer'] ?? '');
            $ok = $config->update(['footer'=>$footer]);
            responderJSON(['ok'=>$ok]);
            break;
        // === SISTEMA DE USUARIOS (copiado exacto de registro_usuario.php) ===
        case 'crear_usuario':
            if (!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso denegado'],403);
            try {
                // Validar token CSRF
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                    responderJSON(['ok' => false, 'error' => 'Token CSRF inválido o expirado']);
                }

                // Validar campos requeridos
                $datos = [];
                $errores = [];
                $esEdicion = false; // Siempre false para crear_usuario

                // Usuario siempre requerido
                $usuario_actual = trim($_POST['usuario'] ?? '');
                if (empty($usuario_actual)) {
                    $errores[] = 'El campo Nombre de usuario es obligatorio';
                } else {
                    $datos['usuario'] = $usuario_actual;
                }

                // Email requerido
                $email_actual = trim($_POST['email'] ?? '');
                if (empty($email_actual)) {
                    $errores[] = 'El campo Correo electrónico es obligatorio';
                } else {
                    $datos['email'] = $email_actual;
                    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                        $errores[] = 'El formato del correo electrónico no es válido';
                    }
                }

                // Clave obligatoria en alta
                if (empty($_POST['clave'])) {
                    $errores[] = 'El campo Contraseña es obligatorio';
                }
                if (($_POST['clave'] ?? '') !== ($_POST['confirmar_clave'] ?? '')) {
                    $errores[] = 'Las contraseñas no coinciden';
                }
                if (strlen($_POST['clave'] ?? '') < 8) {
                    $errores[] = 'La contraseña debe tener al menos 8 caracteres';
                }
                $datos['clave'] = $_POST['clave'];

                // Rol siempre requerido
                $rol_actual = $_POST['rol'] ?? '';
                if (!in_array($rol_actual, ['admin', 'restaurant'])) {
                    $errores[] = 'Rol no válido';
                } else {
                    $datos['rol'] = $rol_actual;
                }

                // Preguntas requeridas
                foreach ([1,2,3] as $i) {
                    $campo = 'respuesta'.$i;
                    $valor_actual = trim($_POST[$campo] ?? '');
                    if (empty($valor_actual)) {
                        $errores[] = "El campo Respuesta $i es obligatorio";
                    } else {
                        $datos[$campo] = $valor_actual;
                    }
                }

                // Si hay errores, mostrarlos
                if (!empty($errores)) {
                    responderJSON(['ok' => false, 'error' => implode('<br>', $errores)]);
                }

                // Verificar si el usuario ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt->execute([$datos['usuario']]);
                if ($stmt->fetch()) {
                    responderJSON(['ok' => false, 'error' => 'El nombre de usuario ya está en uso']);
                }
                
                // Verificar si el email ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$datos['email']]);
                if ($stmt->fetch()) {
                    responderJSON(['ok' => false, 'error' => 'El correo electrónico ya está en uso']);
                }

                // Preguntas de seguridad
                $preguntas = [
                    'Ciudad de nacimiento',
                    'Comuna actual',
                    'Nombre de tu mejor amigo(a) de la infancia'
                ];

                // Insertar el nuevo usuario (copiado exacto de registro_usuario.php)
                $sql = "INSERT INTO usuarios (
                    usuario, email, clave, rol, 
                    pregunta1, respuesta1, pregunta2, respuesta2, pregunta3, respuesta3,
                    activo, ultimo_acceso, ultima_modificacion,
                    intentos_fallidos, bloqueado_hasta, reset_token
                ) VALUES (
                    :usuario, :email, :clave, :rol,
                    :pregunta1, :respuesta1, :pregunta2, :respuesta2, :pregunta3, :respuesta3,
                    1, datetime('now'), datetime('now'),
                    0, NULL, NULL
                )";
                $stmt = $pdo->prepare($sql);
                $resultado = $stmt->execute([
                    ':usuario' => $datos['usuario'],
                    ':email' => $datos['email'],
                    ':clave' => password_hash($datos['clave'], PASSWORD_DEFAULT),
                    ':rol' => $datos['rol'],
                    ':pregunta1' => $preguntas[0],
                    ':respuesta1' => normalizar_respuesta($datos['respuesta1']),
                    ':pregunta2' => $preguntas[1],
                    ':respuesta2' => normalizar_respuesta($datos['respuesta2']),
                    ':pregunta3' => $preguntas[2],
                    ':respuesta3' => normalizar_respuesta($datos['respuesta3'])
                ]);
                
                responderJSON(['ok' => $resultado, 'message' => $resultado ? 'Usuario creado exitosamente' : 'Error al crear el usuario']);
            } catch (Exception $e) {
                responderJSON(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;
            
        case 'editar_usuario':
            if (!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso denegado'],403);
            try {
                // Validar token CSRF
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                    responderJSON(['ok' => false, 'error' => 'Token CSRF inválido o expirado']);
                }

                $edit_id = (int)($_POST['edit_id'] ?? 0);
                if ($edit_id <= 0) {
                    responderJSON(['ok' => false, 'error' => 'ID de usuario inválido']);
                }

                // Obtener datos originales
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$edit_id]);
                $original = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$original) {
                    responderJSON(['ok' => false, 'error' => 'Usuario no encontrado']);
                }

                // Validar campos requeridos (copiado exacto de registro_usuario.php)
                $datos = [];
                $errores = [];
                $esEdicion = true;

                // Usuario siempre requerido
                $usuario_actual = trim($_POST['usuario'] ?? '');
                if (empty($usuario_actual)) {
                    $errores[] = 'El campo Nombre de usuario es obligatorio';
                } else {
                    $datos['usuario'] = $usuario_actual;
                }

                // Email solo requerido si se modifica (o en alta)
                $email_actual = trim($_POST['email'] ?? '');
                if (!$esEdicion || ($email_actual !== ($original['email'] ?? ''))) {
                    if (empty($email_actual)) {
                        $errores[] = 'El campo Correo electrónico es obligatorio';
                    } else {
                        $datos['email'] = $email_actual;
                        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                            $errores[] = 'El formato del correo electrónico no es válido';
                        }
                    }
                } else {
                    $datos['email'] = $original['email'] ?? '';
                }

                // Clave solo requerida en alta o si se quiere cambiar
                if (!$esEdicion) {
                    // Alta: clave obligatoria
                    if (empty($_POST['clave'])) {
                        $errores[] = 'El campo Contraseña es obligatorio';
                    }
                    if (($_POST['clave'] ?? '') !== ($_POST['confirmar_clave'] ?? '')) {
                        $errores[] = 'Las contraseñas no coinciden';
                    }
                    if (strlen($_POST['clave'] ?? '') < 8) {
                        $errores[] = 'La contraseña debe tener al menos 8 caracteres';
                    }
                    $datos['clave'] = $_POST['clave'];
                } else if ($esEdicion && (!empty($_POST['clave']) || !empty($_POST['confirmar_clave']))) {
                    // Edición: solo validar si se quiere cambiar la clave
                    if ($_POST['clave'] !== $_POST['confirmar_clave']) {
                        $errores[] = 'Las contraseñas no coinciden';
                    }
                    if (!empty($_POST['clave'])) {
                        if (strlen($_POST['clave']) < 8) {
                            $errores[] = 'La contraseña debe tener al menos 8 caracteres';
                        }
                        $datos['clave'] = $_POST['clave'];
                    }
                }

                // Rol siempre requerido
                $rol_actual = $_POST['rol'] ?? ($original['rol'] ?? '');
                if (!in_array($rol_actual, ['admin', 'restaurant'])) {
                    $errores[] = 'Rol no válido';
                } else {
                    $datos['rol'] = $rol_actual;
                }

                // Preguntas solo requeridas en alta o si se modifican
                foreach ([1,2,3] as $i) {
                    $campo = 'respuesta'.$i;
                    $valor_actual = trim($_POST[$campo] ?? '');
                    if (!$esEdicion || ($valor_actual !== ($original[$campo] ?? ''))) {
                        if (empty($valor_actual)) {
                            $errores[] = "El campo Respuesta $i es obligatorio";
                        } else {
                            $datos[$campo] = $valor_actual;
                        }
                    } else {
                        $datos[$campo] = $original[$campo] ?? '';
                    }
                }

                // Si hay errores, mostrarlos
                if (!empty($errores)) {
                    responderJSON(['ok' => false, 'error' => implode('<br>', $errores)]);
                }

                // Verificar si el usuario ya existe SOLO si se cambió
                if (!$esEdicion || ($usuario_actual !== ($original['usuario'] ?? ''))) {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                    $stmt->execute([$datos['usuario']]);
                    $existe = $stmt->fetch();
                    if ($existe && (!$esEdicion || $existe['id'] != $edit_id)) {
                        responderJSON(['ok' => false, 'error' => 'El nombre de usuario ya está en uso']);
                    }
                }
                // Verificar si el email ya existe SOLO si se cambió
                if (!$esEdicion || ($email_actual !== ($original['email'] ?? ''))) {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$datos['email']]);
                    $existe = $stmt->fetch();
                    if ($existe && (!$esEdicion || $existe['id'] != $edit_id)) {
                        responderJSON(['ok' => false, 'error' => 'El correo electrónico ya está en uso']);
                    }
                }

                // Preguntas de seguridad
                $preguntas = [
                    'Ciudad de nacimiento',
                    'Comuna actual',
                    'Nombre de tu mejor amigo(a) de la infancia'
                ];

                // Actualizar usuario (copiado exacto de registro_usuario.php)
                $sql = "UPDATE usuarios SET usuario=:usuario, email=:email, rol=:rol, 
                    pregunta1=:pregunta1, respuesta1=:respuesta1, pregunta2=:pregunta2, respuesta2=:respuesta2, pregunta3=:pregunta3, respuesta3=:respuesta3, ultima_modificacion=datetime('now') 
                    WHERE id=:id";
                $params = [
                    ':usuario' => $datos['usuario'],
                    ':email' => $datos['email'],
                    ':rol' => $datos['rol'],
                    ':pregunta1' => $preguntas[0],
                    ':respuesta1' => normalizar_respuesta($datos['respuesta1']),
                    ':pregunta2' => $preguntas[1],
                    ':respuesta2' => normalizar_respuesta($datos['respuesta2']),
                    ':pregunta3' => $preguntas[2],
                    ':respuesta3' => normalizar_respuesta($datos['respuesta3']),
                    ':id' => $edit_id
                ];
                // Si se cambia la clave, actualizarla también
                if (!empty($datos['clave'])) {
                    $sql = str_replace("ultima_modificacion=datetime('now')", "clave=:clave, ultima_modificacion=datetime('now')", $sql);
                    $params[':clave'] = password_hash($datos['clave'], PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare($sql);
                $resultado = $stmt->execute($params);
                
                responderJSON(['ok' => $resultado, 'message' => $resultado ? 'Usuario actualizado exitosamente' : 'Error al actualizar el usuario']);
            } catch (Exception $e) {
                responderJSON(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'eliminar_usuario':
            if (!$es_admin) responderJSON(['ok'=>false,'msg'=>'Permiso denegado'],403);
            try {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    responderJSON(['ok' => false, 'error' => 'ID inválido']);
                }
                // No permitir autoeliminación
                if ($id == $_SESSION['usuario_id']) {
                    responderJSON(['ok' => false, 'error' => 'No puedes eliminar tu propio usuario']);
                }
                $ok = $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
                responderJSON(['ok' => $ok, 'message' => $ok ? 'Usuario eliminado' : 'Error al eliminar usuario']);
            } catch (Exception $e) {
                responderJSON(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;
        case 'cambiar_clave':
            // Cambiar clave
            try {
                $id = (int)($_POST['id'] ?? 0);
                $clave = $_POST['clave'] ?? '';
                if ($id <= 0 || strlen($clave) < 8) $responderJSON(['ok' => false, 'error' => 'Datos inválidos'], 400);
                $ok = $pdo->prepare('UPDATE usuarios SET clave=?, ultima_modificacion=datetime("now") WHERE id=?')->execute([
                    password_hash($clave, PASSWORD_DEFAULT), $id
                ]);
                $responderJSON(['ok' => $ok, 'message' => $ok ? 'Clave actualizada' : 'Error al actualizar clave']);
            } catch (Exception $e) {
                $responderJSON(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            break;
        case 'cambiar_rol':
            // Cambiar rol
            try {
                $id = (int)($_POST['id'] ?? 0);
                $rol = $_POST['rol'] ?? 'restaurant';
                if ($id <= 0) $responderJSON(['ok' => false, 'error' => 'ID inválido'], 400);
                $ok = $pdo->prepare('UPDATE usuarios SET rol=?, ultima_modificacion=datetime("now") WHERE id=?')->execute([
                    $rol, $id
                ]);
                $responderJSON(['ok' => $ok, 'message' => $ok ? 'Rol actualizado' : 'Error al actualizar rol']);
            } catch (Exception $e) {
                $responderJSON(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            break;
        // === FIN NUEVO SISTEMA DE USUARIOS ===
            // Iniciar búfer de salida para capturar cualquier salida no deseada
            if (ob_get_level() === 0) {
                ob_start();
            }
            
            try {
                debug_log('Inicio crear_usuario', [
                    'es_admin' => $es_admin,
                    'post_data' => array_merge($_POST, ['clave' => '***REDACTED***']), // No registrar la contraseña
                    'is_ajax' => $isAjax,
                    'session_id' => session_id()
                ]);
                
                // Verificar si es administrador
                if (!$es_admin) {
                    $error = 'No tienes permisos de administrador para crear usuarios';
                    debug_log('Error de permisos: ' . $error);
                    throw new Exception($error, 403);
                }
                
                // Verificar token CSRF
                if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                    $error = 'Token CSRF inválido o expirado';
                    debug_log('Error CSRF: ' . $error);
                    throw new Exception($error, 403);
                }
                
                // Validar campos requeridos
                $requiredFields = [
                    'usuario' => 'Nombre de usuario',
                    'email' => 'Correo electrónico',
                    'clave' => 'Contraseña',
                    'respuesta1' => 'Respuesta 1',
                    'respuesta2' => 'Respuesta 2',
                    'respuesta3' => 'Respuesta 3'
                ];
                
                $missingFields = [];
                foreach ($requiredFields as $field => $name) {
                    if (empty(trim($_POST[$field] ?? ''))) {
                        $missingFields[] = $name;
                    }
                }
                
                if (!empty($missingFields)) {
                    $error = 'Faltan campos obligatorios: ' . implode(', ', $missingFields);
                    debug_log('Error de validación: ' . $error);
                    throw new Exception($error, 400);
                }
                
                // Obtener y limpiar datos
                $usuario = trim($_POST['usuario']);
                $email = trim($_POST['email']);
                $clave = $_POST['clave'];
                $rol = in_array($_POST['rol'] ?? '', ['admin', 'restaurant']) ? $_POST['rol'] : 'restaurant';
                $respuestas = [
                    '1' => trim($_POST['respuesta1']),
                    '2' => trim($_POST['respuesta2']),
                    '3' => trim($_POST['respuesta3'])
                ];
                
                // Validar email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El formato del correo electrónico no es válido';
                    debug_log('Error de validación de email: ' . $email);
                    throw new Exception($error, 400);
                }
                
                // Validar longitud mínima de contraseña
                if (strlen($clave) < 8) {
                    $error = 'La contraseña debe tener al menos 8 caracteres';
                    debug_log('Error de validación de contraseña');
                    throw new Exception($error, 400);
                }
                
                // Verificar si el email ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?)");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'El correo electrónico ya está registrado';
                    debug_log('Error: Email duplicado: ' . $email);
                    throw new Exception($error, 400);
                }
                
                // Verificar si el usuario ya existe (case-insensitive)
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?)");
                $stmt->execute([$usuario]);
                if ($stmt->fetch()) {
                    $error = 'El nombre de usuario ya está en uso';
                    debug_log('Error: Usuario duplicado: ' . $usuario);
                    throw new Exception($error, 400);
                }
                
                // Preguntas de seguridad
                $preguntas = [
                    '1' => 'Ciudad de nacimiento',
                    '2' => 'Comuna actual',
                    '3' => 'Nombre de tu mejor amigo(a) de la infancia'
                ];
                
                // Crear hash de la contraseña
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                
                // Iniciar transacción
                $pdo->beginTransaction();
                
                try {
                    // Insertar el nuevo usuario
                    $sql = 'INSERT INTO usuarios (
                        usuario, email, clave, rol, 
                        pregunta1, respuesta1, pregunta2, respuesta2, pregunta3, respuesta3,
                        activo, ultimo_acceso, ultima_modificacion,
                        intentos_fallidos, bloqueado_hasta, reset_token
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, datetime("now"), datetime("now"), 0, NULL, NULL)';
                    
                    $params = [
                        $usuario,
                        $email,
                        $hash,
                        $rol,
                        $preguntas['1'],
                        normalizar_respuesta($respuestas['1']),
                        $preguntas['2'],
                        normalizar_respuesta($respuestas['2']),
                        $preguntas['3'],
                        normalizar_respuesta($respuestas['3'])
                    ];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $userId = $pdo->lastInsertId();
                    debug_log('Usuario creado exitosamente', ['id' => $userId]);
                    
                    // Confirmar transacción
                    $pdo->commit();
                    
                    // Limpiar búfer de salida
                    if (ob_get_length() > 0) {
                        $output = ob_get_clean();
                        if (!empty(trim($output))) {
                            debug_log('Se detectó salida no esperada durante la creación de usuario', ['output' => $output]);
                        }
                    }
                    
                    // Responder con éxito
                    if ($isAjax) {
                        responderJSON([
                            'ok' => true, 
                            'message' => 'Usuario creado correctamente',
                            'userId' => $userId
                        ]);
                    } else {
                        $_SESSION['mensaje_user'] = [
                            'tipo' => 'success', 
                            'texto' => 'Usuario creado correctamente'
                        ];
                        header('Location: desktop.php#users');
                        exit;
                    }
                    
                } catch (PDOException $e) {
                    debug_log('Excepción al ejecutar la consulta SQL:', [
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    throw $e;
                }              
                $userId = $pdo->lastInsertId();
                debug_log('Usuario creado exitosamente', [
                    'id' => $userId,
                    'usuario' => $username, 
                    'email' => $email
                ]);
                
                // Respuesta de éxito
                $_SESSION['mensaje_user'] = [
                    'texto' => 'Usuario creado correctamente',
                    'tipo'  => 'success'
                ];
                
                if ($isAjax) {
                    responderJSON(['ok' => true, 'message' => 'Usuario creado correctamente']);
                } else {
                    header('Location: desktop.php#users');
                    exit;
                }
                
            } catch (Exception $e) {
                $error = 'Error al crear el usuario: ' . $e->getMessage();
                error_log($error);
                debug_log('Error en la creación de usuario', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                if ($isAjax) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => false, 
                        'error' => 'Error al crear el usuario: ' . $e->getMessage(),
                        'debug' => $e->getTraceAsString()
                    ]);
                    exit;
                } else {
                    $_SESSION['mensaje_user'] = [
                        'texto' => 'Error al crear el usuario. Por favor, intente nuevamente.',
                        'tipo'  => 'error'
                    ];
                    header('Location: desktop.php#users');
                    exit;
                }
            }
            
            // El manejo de redirección ahora está dentro de los bloques condicionales
            break;
        case 'cambiar_pass':
            debug_log('Inicio cambiar_pass', ['es_admin' => $es_admin, 'post_data' => $_POST]);
            
            if (!$es_admin) {
                $error = 'No tiene permisos para realizar esta acción';
                debug_log('Error: ' . $error);
                $responderJSON(['ok' => false, 'error' => $error], 403);
            }
            
            $uid = (int)($_POST['uid'] ?? 0);
            $pass = $_POST['clave'] ?? '';
            
            if ($uid <= 0 || empty($pass)) {
                $responderJSON(['ok' => false, 'error' => 'Datos inválidos'], 400);
            }
            
            try {
                $stmt = $pdo->prepare('UPDATE usuarios SET clave = ? WHERE id = ?');
                $ok = $stmt->execute([password_hash($pass, PASSWORD_DEFAULT), $uid]);
                
                if ($ok) {
                    debug_log('Contraseña actualizada correctamente', ['uid' => $uid]);
                    $responderJSON(['ok' => true, 'message' => 'Contraseña actualizada correctamente']);
                } else {
                    $errorInfo = $pdo->errorInfo();
                    $error = 'Error al actualizar la contraseña: ' . ($errorInfo[2] ?? 'Error desconocido');
                    error_log('Error al cambiar contraseña: ' . print_r($errorInfo, true));
                    $responderJSON(['ok' => false, 'error' => $error], 500);
                }
            } catch (Exception $e) {
                $error = 'Error al cambiar contraseña: ' . $e->getMessage();
                error_log($error);
                $responderJSON(['ok' => false, 'error' => 'Error interno del servidor'], 500);
            }
            
            // Redirección para solicitudes no AJAX
            $_SESSION['mensaje_user'] = [
                'texto' => 'Contraseña cambiada correctamente',
                'tipo'  => 'success'
            ];
            header('Location: desktop.php#users');
            break;
        case 'eliminar_usuario':
            debug_log('Inicio eliminar_usuario', ['es_admin' => $es_admin, 'post_data' => $_POST]);
            
            if (!$es_admin) {
                $error = 'No tiene permisos para realizar esta acción';
                debug_log('Error: ' . $error);
                $responderJSON(['ok' => false, 'error' => $error], 403);
            }
            
            $uid = (int)($_POST['uid'] ?? 0);
            if ($uid <= 0) {
                $error = 'ID de usuario no válido';
                debug_log('Error: ' . $error);
                $responderJSON(['ok' => false, 'error' => $error], 400);
            }
            
            // Evitar que el usuario se elimine a sí mismo
            if ($uid === (int)($_SESSION['usuario_id'] ?? 0)) {
                $error = 'No puede eliminarse a sí mismo';
                debug_log('Error: ' . $error);
                $responderJSON(['ok' => false, 'error' => $error], 400);
            }
            
            try {
                // Verificar si el usuario existe primero
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
                $stmt->execute([$uid]);
                if (!$stmt->fetch()) {
                    $error = 'El usuario no existe';
                    debug_log('Error: ' . $error);
                    $responderJSON(['ok' => false, 'error' => $error], 404);
                }
                
                // Proceder con la eliminación
                debug_log('Intentando eliminar usuario', ['uid' => $uid]);
                $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
                $ok = $stmt->execute([$uid]);
                
                if ($ok) {
                    debug_log('Usuario eliminado correctamente', ['uid' => $uid]);
                    $responderJSON(['ok' => true, 'message' => 'Usuario eliminado correctamente']);
                } else {
                    $errorInfo = $pdo->errorInfo();
                    $error = 'No se pudo eliminar el usuario: ' . ($errorInfo[2] ?? 'Error desconocido');
                    debug_log('Error al eliminar usuario', ['error' => $errorInfo]);
                    $responderJSON(['ok' => false, 'error' => $error], 500);
                }
            } catch (Exception $e) {
                $error = 'Error al eliminar el usuario: ' . $e->getMessage();
                error_log($error);
                $responderJSON([
                    'ok' => false, 
                    'error' => 'Error interno del servidor',
                    'debug' => $isAjax ? $e->getMessage() : null
                ], 500);
            }
            
            // Redirección para solicitudes no AJAX
            $_SESSION['mensaje_user'] = [
                'texto' => 'Usuario eliminado correctamente',
                'tipo'  => 'success'
            ];
            header('Location: desktop.php#users');
            exit;

    }
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Administración – <?= esc($rest['nombre'] ?? 'Restaurante') ?></title>
    <link rel="stylesheet" href="assets/css/desktop.css?v=<?= APP_VERSION ?>">
<script src="assets/js/notifications.js?v=<?= APP_VERSION ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
<?php
// Mensaje de feedback de otras acciones
if(isset($_SESSION['mensaje'])){
    $m = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    echo "NotificationSystem.show(".json_encode($m['texto']).", ".json_encode($m['tipo']??'info').");\n";
}
/* Avisos de licencia ahora solo banner fijo, no popups */
if(false /* popups deshabilitados */){
    if($lic['expired']){
        $d = $lic['days_expired'];
        $txt = "❌ Licencia vencida hace {$d} ".($d==1?'día':'días').". Renueve cuanto antes para evitar bloqueo total.";
        echo "NotificationSystem.show(".json_encode($txt).", 'error');\n";
    } else {
        $d = $lic['days_remaining'];
        if(in_array($d,[30,20,10])){
            $txt = "⚠️ Su licencia vence en {$d} días. Contacte soporte para renovar (+56 9 6499 5384 / info@andesbytes.cl).";
            echo "NotificationSystem.show(".json_encode($txt).", 'warning');\n";
        } elseif($d<=7 && $d>0){
            $txt = "⚠️ Su licencia vence en {$d} ".($d==1?'día':'días').". Renueve inmediatamente para evitar suspensión.";
            echo "NotificationSystem.show(".json_encode($txt).", 'error');\n";
        }
    }
}
?>
});
</script>
</head>
<body>
<header class="app-header">
    <div class="brand">
        <img src="img-app/elmenurest_logo.webp" alt="ElMenu.rest Logo">
    </div>
    <div class="header-info">
        <div class="lic-status <?= $lic['expired'] ? 'error' : 'ok' ?>">
            <?= $lic['expired'] ? 'Licencia vencida' : 'Licencia vigente · ' . $lic['days_remaining'] . ' días' ?> - <?= esc($_SESSION['usuario']) ?>
        </div>
    </div>
    <div class="header-buttons">
        <button class="view-site-btn" onclick="window.open('/', '_blank')">Ver Web</button>
        <button class="logout-btn" onclick="location.href='logout.php'">Salir</button>
    </div>
</header>
<?php
$licBanner='';$licClass='';
if(!$es_admin){
    if($lic['expired']){
        $d=$lic['days_expired'];
        $licBanner="❌ Licencia vencida hace {$d} ".($d==1?'día':'días').". Renueve: +569 6499 5384 · info@andesbytes.cl";
        $licClass='error';
    } else {
        $d=$lic['days_remaining'];
        if(in_array($d,[30,20,10])||($d<=7 && $d>0)){
            $licBanner="⚠️ Licencia vence en {$d} ".($d==1?'día':'días').". Renueve: +569 6499 5384 · info@andesbytes.cl";
            $licClass=$d<=7?'error':'warn';
        }
    }
}
if($licBanner){ echo "<div class=\"lic-banner {$licClass}\">{$licBanner}</div>"; }
?>
<div class="app-wrapper">
    <nav class="sidebar" role="navigation">
        <ul>
            <li data-section="sys">Información &amp; Licencia</li>
            <li data-section="rest">Restaurant &amp; SEO</li>
            <li data-section="cats">Categorías</li>
            <li data-section="platos">Platos</li>
            <li data-section="tema">Tema</li>
            <?php if ($es_admin): ?>
            <li data-section="footer">Footer</li>
            <li data-section="users">Usuarios</li>
            <?php endif; ?>
        </ul>
    </nav>

    <main id="main-content">
        <section id="section-sys" class="panel-section">
            <h2>Información del sistema &amp; licencia</h2>
            
            <div class="theme-toggle">
                <span class="theme-toggle-label">Modo:</span>
                <div class="theme-switch" onclick="toggleTheme()">
                    <div class="theme-switch-slider"></div>
                </div>
            </div>

            <div class="sys-grid">
                <div>
                    <h3>Estado de licencia</h3>
                    <p id="lic-summary">
                        <?php if ($lic['expired']): ?>
                            <strong style="color:#dc2626;">Licencia vencida hace <?= $lic['days_expired'] ?> días</strong>
                        <?php else: ?>
                            Vigente – quedan <strong><?= $lic['days_remaining'] ?></strong> días (expira el <?= esc($lic['end_date']) ?>)
                        <?php endif; ?>
                    </p>
                    <?php if(!$es_admin): ?>
                    <p class="support-note">Para soporte o renovar su licencia contáctenos al <a href="tel:+56964995384">+569&nbsp;6499&nbsp;5384</a> o al correo <a href="mailto:info@andesbytes.cl">info@andesbytes.cl</a></p>
                    <?php endif; ?>
                    <?php if ($es_admin): ?>
                    <form id="form-renovar" class="inline-form">
                        <input type="hidden" name="accion" value="renovar_licencia">
                        <label>Días a renovar:
                            <input type="number" name="dias" min="1" max="365" value="30" required >
                        </label>
                        <button>Renovar</button>
                    </form>
                    <?php endif; ?>
                </div>

                <?php if ($es_admin): ?>
                <div>
                    <h3>Editar inicio &amp; duración</h3>
                    <form id="form-manual">
                        <input type="hidden" name="accion" value="editar_licencia_manual">
                        <label>Fecha inicio:
                            <input type="date" name="fecha_inicio" value="<?= ($lic['start_date']) ? date('Y-m-d', strtotime(str_replace('/', '-', $lic['start_date']))) : date('Y-m-d') ?>" required>
                        </label>
                        <label>Días totales:
                            <input type="number" name="dias_total" min="1" max="730" value="<?= max(1,$lic['days_remaining']+$lic['days_expired']) ?>" required>
                        </label>
                        <button>Guardar</button>
                    </form>
                </div>
                <?php endif; ?>

                <div>
                    <h3>Información del sistema</h3>
                    <ul>
                        <li>PHP: <?= PHP_VERSION ?></li>
                        <li>Versión app: <?= APP_VERSION ?></li>
                        <li>DB tamaño: <?= number_format(filesize(DB_FILE)/1024,0) ?> KB</li>
                    </ul>
                </div>
                <?php if ($es_admin): ?>
                <div class="backup-section">
    <h3>Backup y restauración de base de datos</h3>
    <div class="backup-grid">
        <div class="backup-import">
            <h4>Importar base de datos (.db/.sqlite):</h4>
            <div class="backup-import-controls">
                <form action="backup.php" method="post" enctype="multipart/form-data" class="import-form">
                    <input type="hidden" name="accion" value="importar_db">
                    <input type="file" name="db_file" accept=".db,.sqlite" required class="file-input">
                    <div class="import-button-group">
                        <button type="button" id="btn-importar-db">Importar base</button>
                        <span class="warning-text">(¡Esto eliminará toda la información actual!)</span>
                    </div>
                </form>
                <form action="backup.php" method="get" class="export-db-form">
                    <input type="hidden" name="accion" value="exportar_db">
                    <button type="submit">Exportar base de datos</button>
                </form>
            </div>
        </div>
        
        <div class="backup-export">
            <h4>Exportar datos a Excel (CSV)</h4>
            <form action="backup.php" method="get" class="csv-form">
                <input type="hidden" name="accion" value="exportar_csv">
                <button type="submit">Exportar datos CSV</button>
            </form>
        </div>
    </div>
</div>
                <?php endif; ?>
            </div>
        </section>
        <section id="section-rest" class="panel-section">
            <h2>Restaurant &amp; SEO</h2>
            <div class="tabs-inline">
                <button data-tab="basic" class="active">Información básica</button>
                <button data-tab="seo">SEO</button>
            </div>
            <div id="tab-basic" class="tab-content active">
                <form id="form-rest" class="admin-form" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="guardar_rest">
                    <div class="form-grid">
                        <label>Nombre
                            <input type="text" name="nombre" value="<?= esc($rest['nombre'] ?? '') ?>" required>
                        </label>
                        <label>Slogan
                            <input type="text" name="slogan" value="<?= esc($rest['slogan'] ?? '') ?>">
                        </label>
                        <label>Dirección
                            <input type="text" name="direccion" value="<?= esc($rest['direccion'] ?? '') ?>">
                        </label>
                        <label>Teléfono
                            <input type="text" name="telefono" value="<?= esc($rest['telefono'] ?? '') ?>">
                        </label>
                        <label>Horario
                            <input type="text" name="horario" value="<?= esc($rest['horario'] ?? '') ?>">
                        </label>
                        <label>Facebook
                            <input type="url" name="facebook" value="<?= esc($rest['facebook'] ?? '') ?>">
                        </label>
                        <label>Instagram
                            <input type="url" name="instagram" value="<?= esc($rest['instagram'] ?? '') ?>">
                        </label>
                        <label>Ciudad
                            <input type="text" name="ciudad" value="<?= esc($rest['ciudad'] ?? '') ?>">
                        </label>
                        <label>País
                            <input type="text" name="region" value="<?= esc($rest['region'] ?? '') ?>">
                        </label>
                        <label>Tipo de cocina
                            <input type="text" name="tipo_cocina" value="<?= esc($rest['tipo_cocina'] ?? '') ?>">
                        </label>
                        <label>Imagen Header
                            <input type="file" name="header_img" accept="image/*">
                        </label>
                        <label>Logo
                            <input type="file" name="logo" accept="image/*">
                        </label>
                    </div>
                    <button class="btn-save">Guardar cambios</button>
                </form>
            </div>
            <div id="tab-seo" class="tab-content">
                <form id="form-seo" class="admin-form" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="guardar_seo">
                    <div class="form-grid">
                        <label>Meta descripción
                            <textarea name="meta_descripcion" rows="3" maxlength="160"><?= esc($rest['meta_descripcion'] ?? '') ?></textarea>
                        </label>
                        <label>Meta keywords
                            <input type="text" name="meta_keywords" value="<?= esc($rest['meta_keywords'] ?? '') ?>">
                        </label>
                        <label>Google Analytics ID
                            <input type="text" name="google_analytics" value="<?= esc($rest['google_analytics'] ?? '') ?>">
                        </label>
                        <label>Google Search Console meta tag
                            <input type="text" name="google_search_console" value="<?= esc($rest['google_search_console'] ?? '') ?>">
                        </label>
                        <label>Mapa (iframe embed)
                             <textarea name="iframe_mapa" rows="2"><?= esc($rest['iframe_mapa'] ?? '') ?></textarea>
                        </label>
                        <label>Sitemap XML URL
                             <input type="url" name="sitemap_xml" value="<?= esc($rest['sitemap_xml'] ?? '') ?>" readonly>
                             <button type="button" id="btn-sitemap">Generar sitemap</button>
                        </label>
                        <label>Imagen SEO
                            <input type="file" name="seo_img" accept="image/*">
                        </label>
                        <label>Texto ALT para imagen SEO
                            <input type="text" name="seo_img_alt" value="<?= esc($rest['seo_img_alt'] ?? '') ?>">
                        </label>
                    </div>
                    <button class="btn-save">Guardar SEO</button>
                    <div class="seo-check-tools" >
                        <button type="button" id="btn-seo-check" class="btn-secondary">Verificar SEO</button>
                        <button type="button" id="btn-seo-download" class="btn-secondary" >Exportar CSV</button>
                        <div id="seo-report" style="margin-top:10px;max-height:300px;overflow:auto;"></div>
                    </div>
                </form>
            </div>
        </section>
        <section id="section-cats" class="panel-section">
            <h2>Categorías</h2>
            <form id="form-cat-add" class="inline-form">
                <input type="hidden" name="accion" value="agregar_categoria">
                <input type="text" name="nombre" placeholder="Nueva categoría" required>
                <button>Agregar</button>
            </form>
            <ul id="cat-list" class="cat-list">
                <?php foreach($cats as $c): ?>
                <li class="cat-item" draggable="true" data-id="<?= $c['id'] ?>">
                    <span class="grab">☰</span>
                    <input type="text" value="<?= esc($c['nombre']) ?>">
                    <button class="del-cat" title="Eliminar">🗑</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <button id="btn-cat-save" class="btn-save">Guardar orden/nombres</button>
        </section>

        <section id="section-platos" class="panel-section">
            <h2>Platos</h2>
            <?php foreach($cats as $cat): $cid=$cat['id']; ?>
            <h3><?= esc($cat['nombre']) ?></h3>
            <form class="inline-form form-dish-add" data-cat="<?= $cid ?>" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="agregar_plato">
                <input type="hidden" name="categoria_id" value="<?= $cid ?>">
                <input type="text" name="nombre" placeholder="Nuevo plato" required>
                 <input type="text" name="descripcion" placeholder="Descripción" required >
                <input type="text" name="precio" placeholder="$0" pattern="\$?[0-9\.]+" required >
                <input type="file" name="imagen" accept="image/*" >
                <button>Agregar</button>
            </form>
            <?php if(empty($platosByCat[$cid])): ?>
            <p class="cat-empty-alert" class="cat-empty-alert">Esta categoría está vacía</p>
            <?php endif; ?>
            <ul class="dish-list" id="dish-list-<?= $cid ?>" data-cat="<?= $cid ?>">
                <?php foreach(($platosByCat[$cid]??[]) as $p): ?>
                <li class="dish-item" draggable="true" data-id="<?= $p['id'] ?>">
                    <span class="grab">☰</span>
                    <input type="text" class="dish-name" value="<?= esc($p['nombre']) ?>">
                    <input type="text" class="dish-desc" placeholder="Desc" value="<?= esc($p['descripcion']) ?>" >
                    <img src="<?= esc($p['imagen'] ?: 'assets/placeholder.png') ?>" class="dish-thumb">
                    <input type="text" class="dish-price" value="<?= '$'.number_format($p['precio'],0,'','.') ?>" >
                    <button class="btn-img" title="Cambiar imagen">📷</button>
                    <button class="del-dish" title="Eliminar">🗑</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endforeach; ?>
            <button id="btn-dish-save" class="btn-save">Guardar platos</button>
        </section>
        <section id="section-tema" class="panel-section">
            <h2>Tema del restaurante</h2>
            <?php $current = $rest['tema'] ?? 'chilena'; ?>
            <form id="form-theme" class="admin-form theme-preview-grid">
                <input type="hidden" name="accion" value="guardar_tema">
                <div class="theme-preview-samples">
                    <?php foreach($temas as $t): ?>
                    <label class="tema-preview" style="background:<?= esc($t['preview_style']) ?>;">
                        <input type="radio" name="tema" value="<?= esc($t['id']) ?>" <?= $current===$t['id']?'checked':'' ?> style="margin-bottom:6px;">
                        <h4><?= esc($t['nombre']) ?></h4>
                        <p><?= esc($t['descripcion']) ?></p>
                        <div class="tema-colors">
                            <?php foreach($t['colors'] as $color): ?>
                                <span class="color-sample" style="background:<?= esc($color) ?>;"></span>
                            <?php endforeach; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn-save">Guardar tema</button>
            </form>
        </section>
        <?php if ($es_admin): ?>
        <section id="section-footer" class="panel-section">
            <h2>Footer</h2>
            <form id="form-footer" class="admin-form">
                <input type="hidden" name="accion" value="guardar_footer">
                <label>Texto del footer
                    <textarea name="footer" rows="3" maxlength="200"><?= esc($rest['footer'] ?? '') ?></textarea>
                </label>
                <button class="btn-save">Guardar footer</button>
            </form>
        </section>
        
        <section id="section-users" class="panel-section">
            <h2>Gestión de usuarios</h2>
            <div id="usuarios-mensajes"></div>
            
            <?php
            // Mostrar tabla de usuarios solo si es admin
            $usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo '<table style="width:100%;margin-bottom:2em;"><thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>';
            foreach ($usuarios as $u) {
                echo '<tr>';
                echo '<td>' . $u['id'] . '</td>';
                echo '<td>' . htmlspecialchars($u['usuario']) . '</td>';
                echo '<td>' . htmlspecialchars($u['email']) . '</td>';
                echo '<td>' . htmlspecialchars($u['rol']) . '</td>';
                echo '<td>';
                echo '<button onclick="editarUsuario(' . $u['id'] . ', \'' . htmlspecialchars($u['usuario']) . '\', \'' . htmlspecialchars($u['email']) . '\', \'' . htmlspecialchars($u['rol']) . '\')" class="btn-edit">Editar</button>';
                echo '<button onclick="eliminarUsuario(' . $u['id'] . ')" class="btn-delete" >Eliminar</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            ?>
            
            <form id="form-usuario" class="formulario" method="post" action="includes/registro_usuario.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="edit_id" id="edit_id" value="">
                <div class="formulario-grid">
                    <div>
                        <div class="grupo-formulario">
                            <label for="usuario">Nombre de Usuario *</label>
                            <input type="text" id="usuario" name="usuario" required>
                        </div>
                        <div class="grupo-formulario">
                            <label for="email">Correo Electrónico *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="grupo-formulario">
                            <label for="clave">Contraseña *</label>
                            <div class="password-wrapper">
                                <input type="password" id="clave" name="clave" required autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="mostrarOcultarContrasena('clave', this)"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="grupo-formulario">
                            <label for="confirmar_clave">Confirmar Contraseña *</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirmar_clave" name="confirmar_clave" required autocomplete="new-password">
                                <button type="button" class="toggle-password" onclick="mostrarOcultarContrasena('confirmar_clave', this)"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="grupo-formulario">
                            <label for="rol">Rol *</label>
                            <select id="rol" name="rol" required>
                                <option value="">Seleccione un rol</option>
                                <option value="admin">Administrador</option>
                                <option value="restaurant">Restaurante</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="grupo-formulario">
                            <label for="respuesta1">¿Cuál es tu ciudad de nacimiento? *</label>
                            <input type="text" id="respuesta1" name="respuesta1" required>
                        </div>
                        <div class="grupo-formulario">
                            <label for="respuesta2">¿En qué comuna vives actualmente? *</label>
                            <input type="text" id="respuesta2" name="respuesta2" required>
                        </div>
                        <div class="grupo-formulario">
                            <label for="respuesta3">¿Cuál es el nombre de tu mejor amigo/a de la infancia? *</label>
                            <input type="text" id="respuesta3" name="respuesta3" required>
                        </div>
                        <div class="grupo-formulario" style="margin-top: 30px;">
                            <button type="submit" id="btn-guardar-usuario" class="btn-save">
                                <i class="fas fa-user-plus"></i> Registrar Usuario
                            </button>
                            <button type="button" id="btn-cancelar" class="btn-cancel" style="display:none;margin-left:10px;">Cancelar</button>
                        </div>
                    </div>
                </div>
            </form>
            
            <script>
                function mostrarOcultarContrasena(idInput) {
                    const input = document.getElementById(idInput);
                    const icono = event.currentTarget.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icono.classList.remove('fa-eye');
                        icono.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icono.classList.remove('fa-eye-slash');
                        icono.classList.add('fa-eye');
                    }
                }
                
                function editarUsuario(id, usuario, email, rol) {
                    document.getElementById('edit_id').value = id;
                    document.getElementById('usuario').value = usuario;
                    document.getElementById('usuario').readOnly = true;
                    document.getElementById('usuario').style.background = '#eee';
                    document.getElementById('usuario').style.color = '#888';
                    document.getElementById('email').value = email;
                    document.getElementById('rol').value = rol;
                    document.getElementById('clave').value = '';
                    document.getElementById('confirmar_clave').value = '';
                    document.getElementById('respuesta1').value = '';
                    document.getElementById('respuesta2').value = '';
                    document.getElementById('respuesta3').value = '';
                    document.getElementById('btn-guardar-usuario').innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
                    document.getElementById('btn-cancelar').style.display = 'inline-block';
                    document.getElementById('form-usuario').setAttribute('novalidate', '');
                }
                
                function eliminarUsuario(id) {
                    if (confirm('¿Seguro que desea eliminar este usuario?')) {
                        const fd = new FormData();
                        fd.append('accion', 'eliminar_usuario');
                        fd.append('id', id);
                        fd.append('csrf_token', CSRF_TOKEN);
                        
                        fetch('desktop.php', {
                            method: 'POST',
                            body: fd,
                            headers: {'X-Requested-With': 'XMLHttpRequest'}
                        })
                        .then(r => r.json())
                        .then(data => {
                            mostrarMensajeUsuario(data.ok ? 'Usuario eliminado' : (data.error || data.message), data.ok ? 'success' : 'error');
                            if (data.ok) location.reload();
                        });
                    }
                }
                
                function cancelarEdicion() {
                    document.getElementById('form-usuario').reset();
                    document.getElementById('edit_id').value = '';
                    document.getElementById('usuario').readOnly = false;
                    document.getElementById('usuario').style.background = '';
                    document.getElementById('usuario').style.color = '';
                    document.getElementById('btn-guardar-usuario').innerHTML = '<i class="fas fa-user-plus"></i> Registrar Usuario';
                    document.getElementById('btn-cancelar').style.display = 'none';
                }
                
                function mostrarMensajeUsuario(msg, tipo) {
                    const mensajes = document.getElementById('usuarios-mensajes');
                    mensajes.innerHTML = `<div class="mensaje ${tipo}">${msg}</div>`;
                    setTimeout(() => { mensajes.innerHTML = ''; }, 4000);
                }
                
                // Event listeners
                document.addEventListener('DOMContentLoaded', function() {
                    const formUsuario = document.getElementById('form-usuario');
                    const btnCancelar = document.getElementById('btn-cancelar');
                    
                    if (formUsuario) {
                        formUsuario.addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            const clave = document.getElementById('clave').value;
                            const confirmarClave = document.getElementById('confirmar_clave').value;
                            const esEdicion = document.getElementById('edit_id').value !== '';
                            
                            // Solo validar si se quiere cambiar la clave o es registro nuevo
                            if (!esEdicion || clave !== '' || confirmarClave !== '') {
                                if (clave !== confirmarClave) {
                                    mostrarMensajeUsuario('Las contraseñas no coinciden', 'error');
                                    return false;
                                }
                                
                                if (clave !== '' && clave.length < 8) {
                                    mostrarMensajeUsuario('La contraseña debe tener al menos 8 caracteres', 'error');
                                    return false;
                                }
                            }
                            
                            const fd = new FormData(formUsuario);
                            fd.append('accion', esEdicion ? 'editar_usuario' : 'crear_usuario');
                            
                            fetch('includes/registro_usuario.php', {
                                method: 'POST',
                                body: fd,
                                headers: {'X-Requested-With': 'XMLHttpRequest'}
                            })
                            .then(r => {
                                console.log('Response status:', r.status);
                                return r.text();
                            })
                            .then(text => {
                                console.log('Response text:', text);
                                try {
                                    const data = JSON.parse(text);
                                    NotificationSystem.show(data.ok ? (esEdicion ? 'Usuario actualizado' : 'Usuario creado') : (data.error || data.message), data.ok ? 'success' : 'error');
                                    if (data.ok) location.reload();
                                } catch (e) {
                                    console.error('JSON parse error:', e);
                                    NotificationSystem.show('Error del servidor: ' + text.substring(0, 200), 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Network error:', error);
                                NotificationSystem.show('Error de conexión: ' + error.message, 'error');
                            });
                        });
                    }
                    
                    if (btnCancelar) {
                        btnCancelar.addEventListener('click', cancelarEdicion);
                    }
                });
            </script>
        </section>
        <?php endif; ?>
    </main>
</div>

<footer class="app-footer">
    <div class="footer-content">
        <p>&copy; <?= date('Y') ?> ElMenu.rest - Desarrollado por Andesbytes.cl - Para soporte o renovar su licencia contáctenos al +569 6499 5384 o al correo info@andesbytes.cl</p>
    </div>
</footer>

<script>const CSRF_TOKEN = '<?= esc($csrf) ?>';</script>
<script src="assets/js/notifications.js?v=<?= APP_VERSION ?>"></script>
<script src="assets/js/desktop.js?v=<?= APP_VERSION ?>"></script>
<script src="assets/js/dishes.js?v=<?= APP_VERSION ?>"></script>
<script src="assets/js/dish-sync.js?v=<?= APP_VERSION ?>"></script>
<script>
function togglePassword(span){
    var input = span.parentNode.querySelector('input[type="password"],input[type="text"]');
    if(input.type==='password'){
        input.type='text';
        span.textContent='🙈';
    }else{
        input.type='password';
        span.textContent='👁️';
    }
}
    } else {
        input.type = 'password';
        icono.classList.remove('fa-eye-slash');
        icono.classList.add('fa-eye');
    }
}

function editarUsuario(id, usuario, email, rol) {
    document.getElementById('edit_id').value = id;
    document.getElementById('usuario').value = usuario;
    document.getElementById('usuario').readOnly = true;
    document.getElementById('usuario').style.background = '#eee';
    document.getElementById('usuario').style.color = '#888';
    document.getElementById('email').value = email;
    document.getElementById('rol').value = rol;
    document.getElementById('clave').value = '';
    document.getElementById('confirmar_clave').value = '';
    document.getElementById('respuesta1').value = '';
    document.getElementById('respuesta2').value = '';
    document.getElementById('respuesta3').value = '';
    document.getElementById('btn-guardar-usuario').innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
    document.getElementById('btn-cancelar').style.display = 'inline-block';
    document.getElementById('form-usuario').setAttribute('novalidate', '');
}

function eliminarUsuario(id) {
    if (confirm('¿Seguro que desea eliminar este usuario?')) {
        const fd = new FormData();
        fd.append('accion', 'eliminar_usuario');
        fd.append('id', id);
        fd.append('csrf_token', CSRF_TOKEN);
        
        fetch('desktop.php', {
            method: 'POST',
            body: fd,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
        .then(r => r.json())
        .then(data => {
            mostrarMensajeUsuario(data.ok ? 'Usuario eliminado' : (data.error || data.message), data.ok ? 'success' : 'error');
            if (data.ok) location.reload();
        });
    }
}

function cancelarEdicion() {
    document.getElementById('form-usuario').reset();
    document.getElementById('edit_id').value = '';
    document.getElementById('usuario').readOnly = false;
    document.getElementById('usuario').style.background = '';
    document.getElementById('usuario').style.color = '';
    document.getElementById('btn-guardar-usuario').innerHTML = '<i class="fas fa-user-plus"></i> Registrar Usuario';
    document.getElementById('btn-cancelar').style.display = 'none';
}

function mostrarMensajeUsuario(msg, tipo) {
    const mensajes = document.getElementById('usuarios-mensajes');
    mensajes.innerHTML = `<div class="mensaje ${tipo}">${msg}</div>`;
    setTimeout(() => { mensajes.innerHTML = ''; }, 4000);
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    const formUsuario = document.getElementById('form-usuario');
    const btnCancelar = document.getElementById('btn-cancelar');
    
    if (formUsuario) {
        formUsuario.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const clave = document.getElementById('clave').value;
            const confirmarClave = document.getElementById('confirmar_clave').value;
            const esEdicion = document.getElementById('edit_id').value !== '';
            
            // Solo validar si se quiere cambiar la clave o es registro nuevo
            if (!esEdicion || clave !== '' || confirmarClave !== '') {
                if (clave !== confirmarClave) {
                    mostrarMensajeUsuario('Las contraseñas no coinciden', 'error');
                    return false;
                }
                
                if (clave !== '' && clave.length < 8) {
                    mostrarMensajeUsuario('La contraseña debe tener al menos 8 caracteres', 'error');
                    return false;
                }
            }
            
            const fd = new FormData(formUsuario);
            fd.append('accion', esEdicion ? 'editar_usuario' : 'crear_usuario');
            
            fetch('includes/registro_usuario.php', {
                method: 'POST',
                body: fd,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
            .then(r => {
                console.log('Response status:', r.status);
                return r.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    NotificationSystem.show(data.ok ? (esEdicion ? 'Usuario actualizado' : 'Usuario creado') : (data.error || data.message), data.ok ? 'success' : 'error');
                    if (data.ok) location.reload();
                } catch (e) {
                    console.error('JSON parse error:', e);
                    NotificationSystem.show('Error del servidor: ' + text.substring(0, 200), 'error');
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                NotificationSystem.show('Error de conexión: ' + error.message, 'error');
            });
        });
    }
    
    if (btnCancelar) {
        btnCancelar.addEventListener('click', cancelarEdicion);
    }
});
</script>
</body>
</html>