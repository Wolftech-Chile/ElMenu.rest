<?php
function generateRestaurantSchema($rest) {
    $schema = [
        "@context" => "https://schema.org",
        "@type" => "Restaurant",
        "name" => $rest['nombre'],
        "description" => $rest['meta_descripcion'] ?? ($rest['descripcion'] ?? ''),
        "image" => $rest['seo_img'] ?? $rest['logo'],
        "url" => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}",
        "telephone" => $rest['telefono'] ?? '',
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => $rest['direccion'] ?? '',
            "addressLocality" => $rest['ciudad'] ?? '',
            "addressRegion" => $rest['region'] ?? '',
            "addressCountry" => "CL"
        ],
        "openingHoursSpecification" => parseHorario($rest['horario'] ?? ''),
        "menu" => (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/menu/{$rest['id']}",
        "servesCuisine" => $rest['tipo_cocina'] ?? 'Chilena',
        "priceRange" => "$$",
        "paymentAccepted" => "Efectivo, Tarjetas de crédito",
        "currenciesAccepted" => "CLP"
    ];

    if (!empty($rest['facebook'])) {
        $schema['sameAs'][] = $rest['facebook'];
    }
    if (!empty($rest['instagram'])) {
        $schema['sameAs'][] = $rest['instagram'];
    }

    if (!empty($rest['google_search_console'])) {
        // Extraer el contenido del meta tag
        if (preg_match('/content=["\']([^"\']+)["\']/', $rest['google_search_console'], $matches)) {
            $schema['google_site_verification'] = $matches[1];
        }
    }

    return $schema;
}

function parseHorario($horario) {
    // Convertir texto de horario a formato Schema.org
    // Ejemplo: "Lun-Vie 9:00-18:00, Sab 10:00-15:00"
    $dias = [
        'Lun' => 'Monday',
        'Mar' => 'Tuesday',
        'Mie' => 'Wednesday',
        'Jue' => 'Thursday',
        'Vie' => 'Friday',
        'Sab' => 'Saturday',
        'Dom' => 'Sunday'
    ];
    
    $resultado = [];
    $horarios = explode(',', $horario);
    
    foreach ($horarios as $h) {
        if (preg_match('/([A-Za-z]+)-([A-Za-z]+)\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})/', trim($h), $matches)) {
            $resultado[] = [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => [$dias[$matches[1]], $dias[$matches[2]]],
                "opens" => $matches[3],
                "closes" => $matches[4]
            ];
        }
    }
    
    return $resultado;
}

function createRestaurantTables($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS restaurante (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        direccion TEXT,
        ciudad TEXT,
        region TEXT,
        horario TEXT,
        tipo_cocina TEXT DEFAULT 'Chilena',
        telefono TEXT,
        facebook TEXT,
        instagram TEXT,
        logo TEXT,
        tema TEXT DEFAULT 'classic',
        slogan TEXT,
        seo_desc TEXT,
        seo_img TEXT,
         seo_img_alt TEXT,
        fecha_inicio_licencia TEXT,
        fecha_licencia TEXT,
        fondo_header TEXT,
        meta_descripcion TEXT,
        meta_keywords TEXT,
        google_analytics TEXT,
        google_search_console TEXT,
        footer_html TEXT,
        iframe_mapa TEXT,
        ultima_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categorias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        orden INTEGER DEFAULT 0,
        ultima_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS platos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        categoria_id INTEGER,
        nombre TEXT NOT NULL,
        descripcion TEXT,
        precio INTEGER,
        imagen TEXT,
        orden INTEGER DEFAULT 0,
        ultima_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
    )");
    
    // Crear índices para mejor rendimiento
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_categorias_orden ON categorias(orden)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_platos_categoria ON platos(categoria_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_platos_orden ON platos(orden)");
}
?>
