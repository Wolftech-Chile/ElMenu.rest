<?php
require_once 'config.php';

class SitemapGenerator {
    private $pdo;
    private $domain;
    private $sitemapPath;

    public function __construct($pdo, $domain) {
        $this->pdo = $pdo;
        $this->domain = rtrim($domain, '/');
        $this->sitemapPath = dirname(__DIR__) . '/sitemap.xml';
    }

    public function generate() {
        try {
            // Verificar permisos de escritura
            $dir = dirname($this->sitemapPath);
            if (!is_writable($dir)) {
                throw new Exception("El directorio no tiene permisos de escritura: " . $dir);
            }

            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
            
            // Añadir página principal
            $url = $xml->addChild('url');
            $url->addChild('loc', $this->domain);
            $url->addChild('changefreq', 'daily');
            $url->addChild('priority', '1.0');
            
            // Obtener todas las categorías
            $stmt = $this->pdo->query('SELECT id, nombre, ultima_modificacion FROM categorias ORDER BY orden ASC');
            while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $url = $xml->addChild('url');
                $url->addChild('loc', $this->domain . '#categoria-' . urlencode($cat['id']));
                $url->addChild('changefreq', 'weekly');
                $url->addChild('priority', '0.8');
                if (!empty($cat['ultima_modificacion'])) {
                    $url->addChild('lastmod', date('Y-m-d', strtotime($cat['ultima_modificacion'])));
                }
            }

            // Guardar el sitemap con el encoding correcto
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xml->asXML());
            
            if (!$dom->save($this->sitemapPath)) {
                throw new Exception("Error al guardar el sitemap.xml");
            }

            // Notificar a Google
            $ping_url = 'https://www.google.com/ping?sitemap=' . urlencode($this->domain . '/sitemap.xml');
            $context = stream_context_create(['http' => ['timeout' => 3]]); // timeout de 3 segundos
            @file_get_contents($ping_url, false, $context);

            return true;
        } catch (Exception $e) {
            error_log("Error generando sitemap: " . $e->getMessage());
            return false;
        }
    }

    public function validateSitemap() {
        if (!file_exists($this->sitemapPath)) {
            return false;
        }
        
        try {
            $xml = new SimpleXMLElement(file_get_contents($this->sitemapPath));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
