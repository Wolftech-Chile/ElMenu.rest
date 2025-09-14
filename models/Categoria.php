<?php
// models/Categoria.php - Modelo CRUD para la tabla categorias
require_once __DIR__ . '/../core/Database.php';

class Categoria {
    /** @var PDO */
    private $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?: Database::get();
    }

    // Obtener todas las categorías ordenadas
    public function all(): array {
        $stmt = $this->pdo->query('SELECT * FROM categorias ORDER BY orden ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener una categoría por ID
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM categorias WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear nueva categoría y devolver ID
    public function create(string $nombre, int $orden = null): int {
        if ($orden === null) {
            // Si no se entrega, asignar último orden +1
            $orden = (int)$this->pdo->query('SELECT COALESCE(MAX(orden),0)+1 FROM categorias')->fetchColumn();
        }
        $stmt = $this->pdo->prepare('INSERT INTO categorias (nombre, orden) VALUES (?, ?)');
        $stmt->execute([$nombre, $orden]);
        return (int)$this->pdo->lastInsertId();
    }

    // Actualizar categoría
    public function update(int $id, string $nombre, int $orden): bool {
        $stmt = $this->pdo->prepare('UPDATE categorias SET nombre = ?, orden = ? WHERE id = ?');
        return $stmt->execute([$nombre, $orden, $id]);
    }

    // Verificar si la categoría tiene platos
    public function hasPlatos(int $categoriaId): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM platos WHERE categoria_id = ?');
        $stmt->execute([$categoriaId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // Eliminar categoría (solución directa)
    public function delete(int $id): bool {
        // Verificar si la categoría tiene platos
        if ($this->hasPlatos($id)) {
            throw new Exception('No se puede eliminar la categoría porque contiene platos.');
        }
        
        // Eliminar la categoría directamente
        $stmt = $this->pdo->prepare('DELETE FROM categorias WHERE id = ?');
        $stmt->execute([$id]);
        
        // Reordenar las categorías restantes
        $categorias = $this->all();
        $updateStmt = $this->pdo->prepare('UPDATE categorias SET orden = ? WHERE id = ?');
        
        foreach ($categorias as $index => $categoria) {
            $updateStmt->execute([$index + 1, $categoria['id']]);
        }
        
        return true;
    }
}
