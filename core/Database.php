<?php
// core/Database.php - Singleton de conexión PDO
class Database {
    /** @var \PDO|null */
    private static $pdo = null;

    /**
     * Devuelve una única instancia de PDO conectada a la base SQLite definida en config.php.
     * Si no existe, la crea y configura los atributos requeridos.
     * @return \PDO
     */
    public static function get(): \PDO {
        if (self::$pdo === null) {
            try {
                // Se requiere el archivo de configuración para obtener la constante DB_FILE
                require_once __DIR__ . '/../config.php';
                
                // Verificar si el archivo de la base de datos existe y es escribible
                if (!file_exists(DB_FILE)) {
                    throw new Exception("El archivo de la base de datos no existe en: " . DB_FILE);
                }
                
                if (!is_writable(DB_FILE)) {
                    throw new Exception("El archivo de la base de datos no tiene permisos de escritura: " . DB_FILE);
                }
                
                // Intentar conectar a la base de datos
                self::$pdo = new PDO('sqlite:' . DB_FILE);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                // Habilitar claves foráneas
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                
                // Verificar que la tabla usuarios existe
                $tables = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='usuarios'")->fetchAll();
                if (empty($tables)) {
                    throw new Exception("La tabla 'usuarios' no existe en la base de datos.");
                }
                
            } catch (PDOException $e) {
                $errorMsg = 'Error de conexión a la base de datos: ' . $e->getMessage();
                error_log($errorMsg);
                // debug_log('Error de conexión a la base de datos', [
                //     'message' => $e->getMessage(),
                //     'code' => $e->getCode(),
                //     'file' => $e->getFile(),
                //     'line' => $e->getLine()
                // ]);
                throw new Exception('Error al conectar con la base de datos. Por favor, intente más tarde.');
            } catch (Exception $e) {
                $errorMsg = 'Error en la base de datos: ' . $e->getMessage();
                error_log($errorMsg);
                // debug_log('Error en la base de datos', [
                //     'message' => $e->getMessage(),
                //     'code' => $e->getCode(),
                //     'file' => $e->getFile(),
                //     'line' => $e->getLine()
                // ]);
                throw $e;
            }
        }
        return self::$pdo;
    }

    /**
     * Evitar instanciar la clase
     */
    private function __construct() {}
    private function __clone() {}
}
