<?php
// Wrapper para mantener compatibilidad retro con el nombre original.
// Ahora la clase UserManager vive en core/Auth.php.
require_once __DIR__ . '/../core/Auth.php';
// Nada más requerido: la clase UserManager ya está cargada.

require_once 'config.php';

class UserManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['clave'])) {
            return $user;
        }
        return false;
    }
    
    public function changePassword($userId, $oldPassword, $newPassword) {
        // Solo admin puede cambiar sin old password
        if ($_SESSION['rol'] !== 'admin') {
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
    
    public function hasPermission($userId, $permission) {
        $stmt = $this->pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        global $permisos_rol;
        return in_array($permission, $permisos_rol[$user['rol']] ?? []);
    }
    
    public function createUser($username, $password, $rol = 'restaurant', $email = null) {
        if ($_SESSION['rol'] !== 'admin') {
            return false;
        }
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (!validarPassword($password)) {
            return false;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare('INSERT INTO usuarios (usuario, email, clave, rol) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$username, $email, $hash, $rol]);
    }
}
