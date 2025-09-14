<?php
// API segura para crear y editar usuarios vía AJAX
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
        exit;
    }
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'No autorizado']);
        exit;
    }
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Token CSRF inválido o expirado']);
        exit;
    }

    $pdo = Database::get();
    $datos = [];
    $errores = [];
    $esEdicion = isset($_POST['edit_id']) && ctype_digit($_POST['edit_id']);
    $original = null;
    if ($esEdicion) {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id=?');
        $stmt->execute([$_POST['edit_id']]);
        $original = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$original) {
            echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }
    }

    // Usuario requerido
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

    // Rol requerido
    $rol_actual = $_POST['rol'] ?? ($original['rol'] ?? '');
    if (!in_array($rol_actual, ['admin', 'restaurant'])) {
        $errores[] = 'Rol no válido';
    } else {
        $datos['rol'] = $rol_actual;
    }

    // Preguntas solo requeridas en alta o si se modifican
    for ($i=1; $i<=3; $i++) {
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

    if (!empty($errores)) {
        echo json_encode(['ok' => false, 'error' => implode('\n', $errores)]);
        exit;
    }

    // Verificar duplicados
    if (!$esEdicion || ($usuario_actual !== ($original['usuario'] ?? ''))) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ?');
        $stmt->execute([$datos['usuario']]);
        $existe = $stmt->fetch();
        if ($existe && (!$esEdicion || $existe['id'] != $_POST['edit_id'])) {
            echo json_encode(['ok' => false, 'error' => 'El nombre de usuario ya está en uso']);
            exit;
        }
    }
    if (!$esEdicion || ($email_actual !== ($original['email'] ?? ''))) {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$datos['email']]);
        $existe = $stmt->fetch();
        if ($existe && (!$esEdicion || $existe['id'] != $_POST['edit_id'])) {
            echo json_encode(['ok' => false, 'error' => 'El correo electrónico ya está en uso']);
            exit;
        }
    }

    $preguntas = [
        'Ciudad de nacimiento',
        'Comuna actual',
        'Nombre de tu mejor amigo(a) de la infancia'
    ];

    if ($esEdicion) {
        $edit_id = (int)$_POST['edit_id'];
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
        if (!empty($datos['clave'])) {
            $sql = str_replace("ultima_modificacion=datetime('now')", "clave=:clave, ultima_modificacion=datetime('now')", $sql);
            $params[':clave'] = password_hash($datos['clave'], PASSWORD_DEFAULT);
        }
        $stmt = $pdo->prepare($sql);
        $resultado = $stmt->execute($params);
        echo json_encode(['ok' => $resultado, 'message' => $resultado ? 'Usuario actualizado exitosamente' : 'Error al actualizar el usuario']);
        exit;
    } else {
        // Insertar nuevo usuario
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
        echo json_encode(['ok' => $resultado, 'message' => $resultado ? 'Usuario creado exitosamente' : 'Error al crear el usuario']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
