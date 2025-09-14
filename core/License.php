<?php
/**
 * License.php
 * Encapsula toda la lógica de licenciamiento del sistema.
 * Guarda y obtiene fechas de inicio / expiración, calcula días restantes
 * y provee helpers para renovación y ajustes manuales.
 *
 * Formato de fechas almacenadas en la BD: dd-mm-YYYY (ej: 01-10-2025)
 * Tabla usada: restaurante (rowid=1)
 */
class License
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene información completa de la licencia.
     * @return array{
     *     start_date:string|null,
     *     end_date:string|null,
     *     days_remaining:int,
     *     expired:bool,
     *     days_expired:int
     * }
     */
    public function getInfo(): array
    {
        $row = $this->pdo->query('SELECT fecha_inicio_licencia, fecha_licencia FROM restaurante LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $start = $row['fecha_inicio_licencia'] ?? null;
        $end = $row['fecha_licencia'] ?? null;

        $now = new \DateTime();
        $endObj = $this->parseDate($end);

        $daysRemaining = $endObj ? (int)$now->diff($endObj)->format('%r%a') : 0;
        $expired = $daysRemaining < 0;
        $daysExpired = $expired ? (int)$endObj->diff($now)->format('%a') : 0;

        return [
            'start_date'     => $start,
            'end_date'       => $end,
            'days_remaining' => $daysRemaining,
            'expired'        => $expired,
            'days_expired'   => $daysExpired,
        ];
    }

    /**
     * Ajusta manualmente la fecha de inicio y duración total (en días).
     * Guarda fecha_inicio_licencia y fecha_licencia resultante.
     */
    public function updateManualStart(string $startDateDmy, int $daysTotal): bool
    {
        $daysTotal = max(1, min(730, $daysTotal));
        $startObj = $this->parseDate($startDateDmy);
        if (!$startObj) return false;
        $expireObj = clone $startObj;
        $expireObj->modify('+' . $daysTotal . ' days');
        $expireDmy = $expireObj->format('d-m-Y');

        $stmt = $this->pdo->prepare('UPDATE restaurante SET fecha_inicio_licencia = ?, fecha_licencia = ? WHERE rowid = 1');
        return $stmt->execute([$startDateDmy, $expireDmy]);
    }

    /**
     * Renueva la licencia sumando $days a la fecha actual de expiración
     */
    public function renew(int $days): bool
    {
        $days = max(1, min(365, $days));
        $row = $this->pdo->query('SELECT fecha_licencia FROM restaurante LIMIT 1')->fetchColumn();
        $endObj = $this->parseDate($row) ?: new \DateTime();
        $now = new \DateTime();
        if ($endObj < $now) {
            $endObj = $now; // Si ya expiró, partir desde hoy
        }
        $endObj->modify('+' . $days . ' days');
        $newEnd = $endObj->format('d-m-Y');
        $stmt = $this->pdo->prepare('UPDATE restaurante SET fecha_licencia = ? WHERE rowid = 1');
        return $stmt->execute([$newEnd]);
    }

    /**
     * Si no hay fechas, crea valores por defecto.
     */
    public function ensureDefaults(string $defaultStart, string $defaultEnd): void
    {
        $row = $this->pdo->query('SELECT fecha_inicio_licencia, fecha_licencia FROM restaurante LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if (empty($row['fecha_inicio_licencia']) || empty($row['fecha_licencia'])) {
            $stmt = $this->pdo->prepare('UPDATE restaurante SET fecha_inicio_licencia = ?, fecha_licencia = ? WHERE rowid = 1');
            $stmt->execute([$defaultStart, $defaultEnd]);
        }
    }

    /* -------------------- helpers ---------------------- */

    private function parseDate(?string $dmy): ?\DateTime
    {
        if (!$dmy) return null;
        $obj = \DateTime::createFromFormat('d-m-Y', $dmy);
        return $obj ?: null;
    }
}
