<?php
// config.php - Configuración del restaurante
define('DB_FILE', 'restaurante.db');
define('APP_VERSION', '2.0.0');
define('MIN_PASSWORD_LENGTH', 8);
define('UPLOAD_DIR', 'img/');
define('MAX_IMAGE_SIZE', 2097152); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('DEFAULT_THEME', 1);

// Credenciales por defecto (solo para instalación inicial)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123');
define('DEFAULT_REST_USER', 'restaurant');
define('DEFAULT_REST_PASS', 'restaurant123');

// Configuración de SEO
define('DEFAULT_META_DESC', 'Menú digital y página web de {nombre_restaurante}. Descubre nuestra carta y realiza tu pedido online.');
define('DEFAULT_META_KEYWORDS', 'restaurante, menú digital, comida, delivery');

// Variables globales
$db_file = DB_FILE;
$upload_dir = UPLOAD_DIR;

// Funciones de validación
function validarPassword($password) {
    return strlen($password) >= MIN_PASSWORD_LENGTH && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Configuración de permisos por rol
$permisos_rol = [
    'admin' => ['editar_todo', 'ver_seo', 'editar_licencia', 'editar_usuarios'],
    'restaurant' => ['editar_menu', 'editar_info_basica']
];
