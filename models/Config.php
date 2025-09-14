<?php
/**
 * Config.php - Acceso centralizado a la configuración general del sistema
 * Actualmente almacenamos la configuración en la tabla `restaurante` fila única (rowid 1).
 * Este modelo ofrece métodos genéricos get()/update() y helpers específicos.
 */
class Config
{
    private \PDO $pdo;
    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function get(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM restaurante LIMIT 1');
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function update(array $data): bool
    {
        if (!$data) return false;
        $keys = array_keys($data);
        $set = implode(', ', array_map(fn($k)=>"$k = :$k", $keys));
        $sql = "UPDATE restaurante SET $set WHERE rowid = 1";
        $stmt = $this->pdo->prepare($sql);
        // Asegurar fila base antes de UPDATE (por si se llama fuera de orden)
        $this->pdo->exec("INSERT INTO restaurante (rowid,nombre) VALUES (1,'') ON CONFLICT(rowid) DO NOTHING");
        foreach ($data as $k=>$v) {
            $stmt->bindValue(':'.$k, $v);
        }
        // Asegurar fila base antes de UPDATE
        $this->pdo->exec("INSERT INTO restaurante (rowid,nombre) VALUES (1,'') ON CONFLICT(rowid) DO NOTHING");
        return $stmt->execute();
    }

    /* Helpers específicos */
    public function setTheme(string $theme): bool { return $this->update(['tema'=>$theme]); }
    public function setSeo(array $seo): bool { return $this->update($seo); }
}
