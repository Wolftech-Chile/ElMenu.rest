<?php
// core/Auth.php - Manejo de usuarios y autenticación
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helpers.php';

/**
 * Clase UserManager (compatibilidad con implementación anterior)
 * Encapsula todas las operaciones sobre la tabla `usuarios`.
 */
class UserManager {
    /** @var PDO */
    private $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?: Database::get();
    }

    /**
     * Verifica credenciales. Devuelve el array del usuario o false.
     */
    public function authenticate(string $username, string $password) {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['clave'])) {
            return $user;
        }
        return false;
    }

    /**
     * Cambia la contraseña de un usuario.
     */
    public function changePassword(int $userId, string $oldPassword, string $newPassword) {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            $stmt = $this->pdo->prepare('SELECT clave FROM usuarios WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($oldPassword, $user['clave'])) {
                return false;
            }
        }
        if (!validarPassword($newPassword)) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('UPDATE usuarios SET clave = ? WHERE id = ?');
        return $stmt->execute([$hash, $userId]);
    }

    /**
     * Revisa si el usuario posee un permiso específico.
     */
    public function hasPermission(int $userId, string $permission): bool {
        $stmt = $this->pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return false;
        global $permisos_rol;
        return in_array($permission, $permisos_rol[$user['rol']] ?? []);
    }

    /**
     * Crea un nuevo usuario (solo admin).
     */
    public function createUser(string $username, string $password, string $rol = 'restaurant') {
        if (($_SESSION['rol'] ?? '') !== 'admin') {
            return false;
        }
        if (!validarPassword($password)) {
            return false;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO usuarios (usuario, clave, rol) VALUES (?, ?, ?)');
        return $stmt->execute([$username, $hash, $rol]);
    }
}
