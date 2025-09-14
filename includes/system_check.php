<?php
// Verificador de requisitos del sistema
class SystemCheck {
    private $errors = [];
    private $warnings = [];
    
    public function checkAll() {
        $this->checkPHPVersion();
        $this->checkSQLite();
        $this->checkWritePermissions();
        $this->checkExtensions();
        $this->checkMemoryLimit();
        
        return [
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'ok' => empty($this->errors)
        ];
    }
    
    private function checkPHPVersion() {
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $this->errors[] = 'Se requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION;
        }
    }
    
    private function checkSQLite() {
        if (!extension_loaded('sqlite3')) {
            $this->errors[] = 'La extensión SQLite3 es requerida';
        }
    }
    
    private function checkWritePermissions() {
        $paths = [
            'img/',
            'img/temas/',
            './', // Para sitemap.xml
        ];
        
        foreach ($paths as $path) {
            if (!is_writable($path)) {
                $this->errors[] = "El directorio '{$path}' no tiene permisos de escritura";
            }
        }
    }
    
    private function checkExtensions() {
        $required = ['gd', 'json', 'xml'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $this->errors[] = "La extensión '{$ext}' es requerida";
            }
        }
    }
    
    private function checkMemoryLimit() {
        $limit = ini_get('memory_limit');
        $limitBytes = $this->returnBytes($limit);
        
        if ($limitBytes < 128 * 1024 * 1024) { // 128MB
            $this->warnings[] = "Se recomienda un límite de memoria de al menos 128M. Actual: {$limit}";
        }
    }
    
    private function returnBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}

// Uso en install.php o donde sea necesario
if (isset($_GET['check'])) {
    $checker = new SystemCheck();
    $results = $checker->checkAll();
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}
