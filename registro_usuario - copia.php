<?php
// registro_usuario.php
require_once 'config.php';
require_once 'core/Database.php';
require_once 'includes/functions.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Función para mostrar mensajes
function mostrarMensaje($mensaje, $tipo = 'info') {
    $_SESSION['mensaje'] = [
        'texto' => $mensaje,
        'tipo' => $tipo
    ];
}

// Procesar edición o eliminación si es admin
if (isset($_POST['delete_user'], $_POST['delete_id'], $_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $pdo = Database::get();
    $id = (int)$_POST['delete_id'];
    if ($id > 0) {
        $pdo->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
        mostrarMensaje('Usuario eliminado correctamente', 'success');
        header('Location: registro_usuario.php');
        exit;
    }
}

// Precargar datos para edición si es admin
$datos_editar = null;
if (isset($_POST['edit_user'], $_POST['edit_id'], $_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    $pdo = Database::get();
    $id = (int)$_POST['edit_id'];
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id=?');
        $stmt->execute([$id]);
        $datos_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Procesar el formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_user']) && !isset($_POST['edit_user'])) {
    try {
        // Validar token CSRF
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            throw new Exception('Token CSRF inválido o expirado');
        }

        // Validar campos requeridos
        $datos = [];
        $errores = [];
        $esEdicion = isset($_POST['edit_id']) && $_SESSION['rol'] === 'admin' && ctype_digit($_POST['edit_id']);

        // Usuario siempre requerido
        $usuario_actual = trim($_POST['usuario'] ?? '');
        if (empty($usuario_actual)) {
            $errores[] = 'El campo Nombre de usuario es obligatorio';
        } else {
            $datos['usuario'] = $usuario_actual;
        }

        // Email solo requerido si se modifica (o en alta)
        $original = $datos_editar ?? [];
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
            throw new Exception(implode('<br>', $errores));
        }

        // Conectar a la base de datos
        $pdo = Database::get();

        // Verificar si el usuario ya existe SOLO si se cambió
        if (!$esEdicion || ($usuario_actual !== ($original['usuario'] ?? ''))) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$datos['usuario']]);
            $existe = $stmt->fetch();
            if ($existe && (!$esEdicion || $existe['id'] != $_POST['edit_id'])) {
                throw new Exception('El nombre de usuario ya está en uso');
            }
        }
        // Verificar si el email ya existe SOLO si se cambió
        if (!$esEdicion || ($email_actual !== ($original['email'] ?? ''))) {
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$datos['email']]);
            $existe = $stmt->fetch();
            if ($existe && (!$esEdicion || $existe['id'] != $_POST['edit_id'])) {
                throw new Exception('El correo electrónico ya está en uso');
            }
        }

        // Preguntas de seguridad
        $preguntas = [
            'Ciudad de nacimiento',
            'Comuna actual',
            'Nombre de tu mejor amigo(a) de la infancia'
        ];

        // Si es edición (solo admin)
        if (isset($_POST['edit_id']) && $_SESSION['rol'] === 'admin' && ctype_digit($_POST['edit_id'])) {
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
            // Si se cambia la clave, actualizarla también
            if (!empty($datos['clave'])) {
                $sql = str_replace("ultima_modificacion=datetime('now')", "clave=:clave, ultima_modificacion=datetime('now')", $sql);
                $params[':clave'] = password_hash($datos['clave'], PASSWORD_DEFAULT);
            }
            $stmt = $pdo->prepare($sql);
            $resultado = $stmt->execute($params);
            if ($resultado) {
                mostrarMensaje('Usuario actualizado exitosamente', 'success');
                header('Location: registro_usuario.php');
                exit;
            } else {
                throw new Exception('Error al actualizar el usuario');
            }
        } else {
            // Insertar el nuevo usuario
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
            if ($resultado) {
                mostrarMensaje('Usuario creado exitosamente', 'success');
                header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'desktop.php'));
                exit;
            } else {
                throw new Exception('Error al crear el usuario');
            }
        }

    } catch (Exception $e) {
        mostrarMensaje($e->getMessage(), 'error');
    }
}

// Generar nuevo token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --color-primario: #7ac943;
            --color-secundario: #333;
            --color-fondo: #f5f5f5;
            --color-texto: #333;
            --color-borde: #ddd;
            --color-error: #e74c3c;
            --color-exito: #2ecc71;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--color-fondo);
            color: var(--color-texto);
            line-height: 1.6;
            padding: 20px;
        }

        .contenedor {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: var(--color-primario);
            margin-bottom: 30px;
        }

        .mensaje {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
        }

        .mensaje.error {
            background-color: #fde8e8;
            color: var(--color-error);
            border: 1px solid #f5c6cb;
        }

        .mensaje.success {
            background-color: #e8f9f0;
            color: var(--color-exito);
            border: 1px solid #c3e6cb;
        }

        .formulario-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .grupo-formulario {
            margin-bottom: 20px;
        }

        .grupo-formulario label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-secundario);
        }

        .grupo-formulario input[type="text"],
        .grupo-formulario input[type="email"],
        .grupo-formulario input[type="password"],
        .grupo-formulario select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-borde);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .grupo-formulario input:focus,
        .grupo-formulario select:focus {
            outline: none;
            border-color: var(--color-primario);
            box-shadow: 0 0 0 2px rgba(122, 201, 67, 0.2);
        }

        .grupo-formulario .contrasena-con-ojito {
            position: relative;
        }

        .grupo-formulario .contrasena-con-ojito input {
            padding-right: 40px;
        }

        .grupo-formulario .ojito {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 18px;
        }

        .boton {
            display: inline-block;
            background-color: var(--color-primario);
            color: white;
            padding: 08px 25px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            text-align: center;
            text-decoration: none;
            margin-block: 7px;
        }

        .boton:hover {
            background-color: #68b336;
        }

        .boton-block {
            display: block;
            width: 100%;
        }

        .texto-centrado {
            text-align: center;
            margin-top: 20px;
        }

        .texto-centrado a {
            color: var(--color-primario);
            text-decoration: none;
            font-weight: 600;
        }

        .texto-centrado a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .contenedor {
                padding: 20px;
            }

            .formulario-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1><i class="fas fa-user-plus"></i> Registro de Usuario</h1>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="mensaje <?= $_SESSION['mensaje']['tipo'] === 'error' ? 'error' : 'success' ?>">
                <?= htmlspecialchars($_SESSION['mensaje']['texto']) ?>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <?php
        // Mostrar tabla de usuarios solo si es admin
        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
            $pdo = Database::get();
            $usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo '<h2 style="margin-top:1em;">Usuarios existentes</h2>';
            echo '<table style="width:100%;margin-bottom:2em;"><thead><tr><th>ID</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead><tbody>';
            foreach ($usuarios as $u) {
                echo '<tr>';
                echo '<td>' . $u['id'] . '</td>';
                echo '<td>' . htmlspecialchars($u['usuario']) . '</td>';
                echo '<td>' . htmlspecialchars($u['email']) . '</td>';
                echo '<td>' . htmlspecialchars($u['rol']) . '</td>';
                echo '<td>';
                echo '<form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">
                        <input type="hidden" name="edit_id" value="' . $u['id'] . '">
                        <button type="submit" name="edit_user" class="boton" style="background:#e8f9f0;color:#333;">Editar</button>
                      </form>';
                echo '<form method="post" style="display:inline;margin-left:5px;" onsubmit="return confirm(\'¿Seguro que desea eliminar este usuario?\');">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">
                        <input type="hidden" name="delete_id" value="' . $u['id'] . '">
                        <button type="submit" name="delete_user" class="boton" style="background:#fde8e8;color:#e74c3c;">Eliminar</button>
                      </form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        ?>

        <form method="post" class="formulario-registro" <?= isset($datos_editar) ? 'novalidate' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="formulario-grid">
                <!-- Columna Izquierda -->
                <div>
                    <div class="grupo-formulario">
                        <label for="usuario">Nombre de Usuario *</label>
                        <input type="text" id="usuario" name="usuario" required 
                               value="<?= htmlspecialchars($datos_editar['usuario'] ?? ($_POST['usuario'] ?? '')) ?>" <?= isset($datos_editar) ? 'readonly style="background:#eee;color:#888;"' : '' ?>>
                    </div>

                    <div class="grupo-formulario">
                        <label for="email">Correo Electrónico *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= htmlspecialchars($datos_editar['email'] ?? ($_POST['email'] ?? '')) ?>">
                    </div>

                    <div class="grupo-formulario">
                        <label for="clave">Contraseña *</label>
                        <div class="contrasena-con-ojito">
                            <input type="password" id="clave" name="clave" value="<?= isset($datos_editar) ? htmlspecialchars($datos_editar['clave']) : '' ?>" autocomplete="new-password">
                            <button type="button" class="ojito" onclick="mostrarOcultarContrasena('clave')">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grupo-formulario">
                        <label for="confirmar_clave">Confirmar Contraseña *</label>
                        <div class="contrasena-con-ojito">
                            <input type="password" id="confirmar_clave" name="confirmar_clave" value="" autocomplete="new-password">
                            <button type="button" class="ojito" onclick="mostrarOcultarContrasena('confirmar_clave')">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grupo-formulario">
                        <label for="rol">Rol *</label>
                        <select id="rol" name="rol" required>
                            <option value="">Seleccione un rol</option>
                            <option value="admin" <?= (isset($datos_editar['rol']) && $datos_editar['rol'] === 'admin') || (isset($_POST['rol']) && $_POST['rol'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
                            <option value="restaurant" <?= (isset($datos_editar['rol']) && $datos_editar['rol'] === 'restaurant') || (isset($_POST['rol']) && $_POST['rol'] === 'restaurant') ? 'selected' : '' ?>>Restaurante</option>
                        </select>
                    </div>
                </div>

                <!-- Columna Derecha -->
                <div>
                    <div class="grupo-formulario">
                        <label for="respuesta1">¿Cuál es tu ciudad de nacimiento? *</label>
                        <input type="text" id="respuesta1" name="respuesta1" required
                               value="<?= htmlspecialchars($datos_editar['respuesta1'] ?? ($_POST['respuesta1'] ?? '')) ?>">
                    </div>

                    <div class="grupo-formulario">
                        <label for="respuesta2">¿En qué comuna vives actualmente? *</label>
                        <input type="text" id="respuesta2" name="respuesta2" required
                               value="<?= htmlspecialchars($datos_editar['respuesta2'] ?? ($_POST['respuesta2'] ?? '')) ?>">
                    </div>

                    <div class="grupo-formulario">
                        <label for="respuesta3">¿Cuál es el nombre de tu mejor amigo/a de la infancia? *</label>
                        <input type="text" id="respuesta3" name="respuesta3" required
                               value="<?= htmlspecialchars($datos_editar['respuesta3'] ?? ($_POST['respuesta3'] ?? '')) ?>">
                    </div>

                    <div class="grupo-formulario" style="margin-top: 30px;">
                        <?php if(isset($datos_editar)): ?>
                        <input type="hidden" name="edit_id" value="<?= (int)$datos_editar['id'] ?>">
                        <button type="submit" class="boton boton-block">
                            <i class="fas fa-save"></i> Guardar cambios
                        </button>
                        <?php else: ?>
                        <button type="submit" class="boton boton-block">
                            <i class="fas fa-user-plus"></i> Registrar Usuario
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="texto-centrado">
                        <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Función para mostrar/ocultar contraseña
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

        // Validación del formulario
        document.querySelector('.formulario-registro').addEventListener('submit', function(e) {
            const clave = document.getElementById('clave').value;
            const confirmarClave = document.getElementById('confirmar_clave').value;
            const esEdicion = document.querySelector('input[name="edit_id"]') !== null;
            
            // Solo validar si se quiere cambiar la clave o es registro nuevo
            if (!esEdicion || clave !== '' || confirmarClave !== '') {
                if (clave !== confirmarClave) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden');
                    return false;
                }
                
                if (clave !== '' && clave.length < 8) {
                    e.preventDefault();
                    alert('La contraseña debe tener al menos 8 caracteres');
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>
