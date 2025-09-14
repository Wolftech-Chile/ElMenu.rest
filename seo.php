<?php
function render_seo_tags($restaurante) {
    if (!is_array($restaurante)) return;
    $nombre = isset($restaurante['nombre']) ? htmlspecialchars($restaurante['nombre']) : '';
    $slogan = isset($restaurante['slogan']) ? htmlspecialchars($restaurante['slogan']) : '';
    $seo_desc = isset($restaurante['meta_descripcion']) ? htmlspecialchars($restaurante['meta_descripcion']) : '';
    $meta_keywords = isset($restaurante['meta_keywords']) ? htmlspecialchars($restaurante['meta_keywords']) : '';
    $google_analytics = isset($restaurante['google_analytics']) ? htmlspecialchars($restaurante['google_analytics']) : '';
    $google_search_console = isset($restaurante['google_search_console']) ? htmlspecialchars($restaurante['google_search_console']) : '';
    $seo_img = isset($restaurante['seo_img']) ? htmlspecialchars($restaurante['seo_img']) : '';
    $seo_img_alt = isset($restaurante['seo_img_alt']) ? htmlspecialchars($restaurante['seo_img_alt']) : '';
    $ciudad = isset($restaurante['ciudad']) ? htmlspecialchars($restaurante['ciudad']) : '';
    $logo = isset($restaurante['logo']) ? htmlspecialchars($restaurante['logo']) : '';
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];

    if (!empty($slogan)) {
        echo "<title>{$nombre} | {$slogan}</title>";
    } else {
        echo "<title>{$nombre}</title>";
    }
    echo "\n";
    echo "<meta name=\"description\" content=\"{$seo_desc}\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "<link rel=\"canonical\" href=\"{$url}\">\n";
    if (!empty($meta_keywords)) {
        echo "<meta name=\"keywords\" content=\"{$meta_keywords}\">\n";
    }
    if (!empty($seo_img)) {
        echo "<meta property=\"og:image\" content=\"{$seo_img}\">\n";
        if(!empty($seo_img_alt)){
            echo "<meta property=\"og:image:alt\" content=\"{$seo_img_alt}\">\n";
        }
    }
    if (!empty($google_search_console)) {
        echo "<meta name=\"google-site-verification\" content=\"{$google_search_console}\">\n";
    }
    // JSON-LD Schema.org Restaurant
    $json_ld = [
        "@context" => "https://schema.org",
        "@type" => "Restaurant",
        "name" => $nombre,
        "url" => $url,
        "image" => $seo_img,
        "telephone" => $restaurante['telefono'] ?? '',
        "servesCuisine" => $restaurante['tipo_cocina'] ?? 'Chilena',
        "address" => [
            "@type" => "PostalAddress",
            "streetAddress" => $restaurante['direccion'] ?? '',
            "addressLocality" => $ciudad,
            "addressCountry" => "CL"
        ]
    ];
    echo '<script type="application/ld+json">' . json_encode($json_ld, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
    // Google Analytics
    if (!empty($google_analytics)) {
        echo "<!-- Google Analytics -->";
        echo "<script async src='https://www.googletagmanager.com/gtag/js?id={$google_analytics}'></script>\n";
        echo "<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n  gtag('config', '{$google_analytics}');\n</script>\n";
    }
}
