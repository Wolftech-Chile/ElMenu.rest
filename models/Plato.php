<?php
// models/Plato.php - Modelo CRUD para la tabla platos
require_once __DIR__ . '/../core/Database.php';

class Plato {
    /** @var PDO */
    private $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?: Database::get();
    }

    // Obtener todos los platos (opcionalmente por categoría)
    public function all(int $categoriaId = null): array {
        if ($categoriaId === null) {
            $stmt = $this->pdo->query('SELECT * FROM platos ORDER BY categoria_id, orden ASC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->prepare('SELECT * FROM platos WHERE categoria_id = ? ORDER BY orden ASC');
        $stmt->execute([$categoriaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener un plato
    public function find(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM platos WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Crear plato
    public function create(int $categoriaId, string $nombre, string $descripcion, float $precio, string $imagen, int $orden = null): int {
        if ($orden === null) {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(orden),0)+1 as next_orden FROM platos WHERE categoria_id = ?');
            $stmt->execute([$categoriaId]);
            $orden = (int)($stmt->fetchColumn() ?: 1);
        }
        $stmt = $this->pdo->prepare('INSERT INTO platos (categoria_id, nombre, descripcion, precio, imagen, orden) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$categoriaId, $nombre, $descripcion, $precio, $imagen, $orden]);
        return (int)$this->pdo->lastInsertId();
    }

    // Actualizar plato
    public function update(int $id, int $categoriaId, string $nombre, string $descripcion, float $precio, string $imagen, int $orden): bool {
        $stmt = $this->pdo->prepare('UPDATE platos SET categoria_id=?, nombre=?, descripcion=?, precio=?, imagen=?, orden=? WHERE id=?');
        return $stmt->execute([$categoriaId, $nombre, $descripcion, $precio, $imagen, $orden, $id]);
    }

    // Eliminar plato y reajustar orden de la categoría
    public function delete(int $id): bool {
        // Obtener categoría para reordenar después
        $stmt = $this->pdo->prepare('SELECT categoria_id FROM platos WHERE id = ?');
        $stmt->execute([$id]);
        $catId = $stmt->fetchColumn();

        $ok = $this->pdo->prepare('DELETE FROM platos WHERE id = ?')->execute([$id]);
        if ($ok && $catId) {
            $this->pdo->prepare('WITH ranked AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY orden) AS rn FROM platos WHERE categoria_id = ?
            ) UPDATE platos SET orden = rn WHERE id IN (SELECT id FROM ranked)')
                ->execute([$catId]);
        }
        return $ok;
    }

    // Cambiar orden de un plato dentro de su categoría
    public function move(int $id, int $nuevoOrden): bool {
        // Obtiene la categoría y orden actual
        $plato = $this->find($id);
        if (!$plato) return false;
        $catId = $plato['categoria_id'];

        // Normalizar para evitar colisiones
        $this->pdo->beginTransaction();
        $this->pdo->prepare('UPDATE platos SET orden = orden + 1 WHERE categoria_id = ? AND orden >= ?')
                  ->execute([$catId, $nuevoOrden]);
        $this->pdo->prepare('UPDATE platos SET orden = ? WHERE id = ?')->execute([$nuevoOrden, $id]);
        $this->pdo->commit();
        return true;
    }
}
