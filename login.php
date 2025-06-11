<?php
// login.php - Acceso seguro para admin/restaurante
require_once 'config.php';
session_start();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Bloqueo por intentos fallidos
if (!isset($_SESSION['login_intentos'])) $_SESSION['login_intentos'] = 0;
if (!isset($_SESSION['login_bloqueo'])) $_SESSION['login_bloqueo'] = 0;

$bloqueado = ($_SESSION['login_intentos'] >= 3 && time() < $_SESSION['login_bloqueo']);
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = 'Token CSRF inválido.';
    } else {
        $usuario = trim($_POST['usuario'] ?? '');
        $clave = $_POST['clave'] ?? '';
        // Consulta segura
        $pdo = new PDO('sqlite:' . $db_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare('SELECT id, usuario, clave, rol FROM usuarios WHERE usuario = ? LIMIT 1');
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($clave, $user['clave'])) {
            // Login OK
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['login_intentos'] = 0;
            $_SESSION['login_bloqueo'] = 0;
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_intentos']++;
            if ($_SESSION['login_intentos'] >= 3) {
                $_SESSION['login_bloqueo'] = time() + 60 * 5; // 5 minutos
                $mensaje = 'Demasiados intentos. Espere 5 minutos.';
            } else {
                $mensaje = 'Usuario o contraseña incorrectos.';
            }
        }
    }
}
if ($bloqueado) {
    $resta = $_SESSION['login_bloqueo'] - time();
    $mensaje = 'Demasiados intentos. Espere ' . ceil($resta/60) . ' minutos.';
}
function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login | Menú Digital</title>
    <link rel="stylesheet" href="assets/style-dashboard.css">
    <style>body{display:flex;align-items:center;justify-content:center;height:100vh;background:#f7f7f7;}form{background:#fff;padding:2em 2.5em;border-radius:10px;box-shadow:0 2px 12px #0001;min-width:300px;}input{width:100%;margin-bottom:1em;padding:.7em;border-radius:5px;border:1px solid #ccc;}button{width:100%;padding:.7em;background:#2196F3;color:#fff;border:none;border-radius:5px;font-weight:bold;}small{color:#888;}</style>
</head>
<body>
    <form method="post" autocomplete="off">
        <h2>Acceso</h2>
        <?php if ($mensaje): ?><div style="color:#b00; margin-bottom:1em;"><?= esc($mensaje) ?></div><?php endif; ?>
        <input type="text" name="usuario" placeholder="Usuario" required autofocus maxlength="32">
        <input type="password" name="clave" placeholder="Contraseña" required maxlength="64">
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
        <button type="submit" <?= $bloqueado ? 'disabled' : '' ?>>Ingresar</button>
        <small>¿Olvidó su clave? Contacte al administrador.</small>
    </form>
</body>
</html>
