<?php
class ThemeManager {
    // Lee los temas y colores desde el CSS
    public static function getTemas() {
        $cssFile = dirname(__DIR__) . '/assets/css/style-restaurants.css';
        if (!file_exists($cssFile)) return [];
        $css = file_get_contents($cssFile);
        $temas = [];
        // Definiciones personalizadas de nombres y descripciones
        // Nombre visible y pequeña descripción comercial
        $meta = [
            'italiana' => ['nombre'=>'Italiana','descripcion'=>'Estilo ideal para restaurantes de comida italiana'],
            'asiatica' => ['nombre'=>'Asiática','descripcion'=>'Perfecto para locales de cocina oriental'],
            'chilena'  => ['nombre'=>'Chilena','descripcion'=>'Inspirado en colores patrios y gastronomía chilena'],
            'peruana'  => ['nombre'=>'Peruana','descripcion'=>'Ideal para cevicherías y cocina peruana'],
            'gourmet'  => ['nombre'=>'Gourmet','descripcion'=>'Acabado elegante para cartas de autor'],
            'pubbar'   => ['nombre'=>'Pub / Bar','descripcion'=>'Pensado para bares y cervecerías'],
            'comidarapida_usa'=> ['nombre'=>'Comida Rápida Americana','descripcion'=>'Vibrante para fast-food y delivery estilo USA'],
            'comidarapida_cl'=> ['nombre'=>'Comida Rápida Chilena','descripcion'=>'Adaptado a completos, papas fritas y churrascos'],
            'comidarapida_col'=> ['nombre'=>'Comida Rápida Colombiana','descripcion'=>'Hamburguesas, salchipapas y perros calientes'],
            'comidarapida_ve'=> ['nombre'=>'Comida Rápida Venezolana','descripcion'=>'Perfecto para areperas y comida callejera VE'],
            'pollos'   => ['nombre'=>'Pollos','descripcion'=>'Enfocado en pollerías y asados'],
            'mexicana' => ['nombre'=>'Mexicana','descripcion'=>'Colores vivos para taquerías y tex-mex'],
            'mariscos' => ['nombre'=>'Mariscos','descripcion'=>'Fresco para pescados y marisquerías'],
            'vegetariana'=> ['nombre'=>'Vegetariana','descripcion'=>'Tonos verdes para opciones saludables'],
            'parrillada'=> ['nombre'=>'Parrillada','descripcion'=>'Rústico para carnes y parrillas'],
            'cafepasteleria'=> ['nombre'=>'Café / Pastelería','descripcion'=>'Cálido para cafeterías, pastelerías y brunch'],
            'cafe'=> ['nombre'=>'Café','descripcion'=>'Minimalista para espresso bar'],
            'sushi'=> ['nombre'=>'Sushi','descripcion'=>'Minimalista y fresco para sushi'],
            'arabe'=> ['nombre'=>'Árabe','descripcion'=>'Inspirado en colores y formas de medio oriente'],
            'mediterranea'=> ['nombre'=>'Mediterránea','descripcion'=>'Fresco para dieta mediterránea'],
            'exotica'=> ['nombre'=>'Exótica','descripcion'=>'Atrevido para sabores del mundo'],
            'brasilena'=> ['nombre'=>'Brasileña','descripcion'=>'Tropical para churrasquerías'],
            'pizzeria'=> ['nombre'=>'Pizzería','descripcion'=>'Clásico horno de leña'],
            'sangucheria'=> ['nombre'=>'Sanguchería','descripcion'=>'Rápido y sabroso en panes'],
            'alemana'=> ['nombre'=>'Comida Alemana','descripcion'=>'Inspirado en tradición bávara'],
            'francesa'=> ['nombre'=>'Comida Francesa','descripcion'=>'Sofisticado y refinado, ideal para bistrós y restaurantes gourmet'],
            'pizza'=> ['nombre'=>'Pizza','descripcion'=>'Tonos cálidos y sabrosos como una pizza recién horneada'],
            'argentina'=> ['nombre'=>'Parrillada Argentina','descripcion'=>'Representa la bandera argentina y el estilo parrillero tradicional'],
            'colombiana'=> ['nombre'=>'Comida Colombiana','descripcion'=>'Inspirado en la bandera colombiana, ideal para fritangas y salchipapas'],
            'venezolana'=> ['nombre'=>'Comida Venezolana','descripcion'=>'Arepas, tequeños y más con colores intensos y modernos'],
            'tailandesa'=> ['nombre'=>'Comida Tailandesa','descripcion'=>'Sabores exóticos con colores ricos y especiados'],
            'hindu'=> ['nombre'=>'Comida Hindú','descripcion'=>'Cálido y especiado, perfecto para curries y masalas'],
            'china'=> ['nombre'=>'Comida China','descripcion'=>'Clásico, fuerte y tradicional para comida china'],
            'empanadas'=> ['nombre'=>'Empanadas','descripcion'=>'Colores de masa, horno y relleno'],
            'comidarapida'=> ['nombre'=>'Comida Rápida I','descripcion'=>'Ideal para comida rápida general'],
            'comidarapida2'=> ['nombre'=>'Comida Rápida II','descripcion'=>'Ideal para comida rápida general'],
            'heladeria'=> ['nombre'=>'Heladería','descripcion'=>'Dulce y colorido para helados y postres'],
            'arabemediterranea'=> ['nombre'=>'Árabe / Mediterránea','descripcion'=>'Inspirado en sabores de medio oriente y mediterráneo'],
            'india'=> ['nombre'=>'India','descripcion'=>'Colores vibrantes para cocina hindú'],
            'fusion'=> ['nombre'=>'Fusión','descripcion'=>'Moderno para propuestas gastronómicas mixtas'],
        ];
        // Regex para bloques body[data-theme="NOMBRE"] { ... } o :root[data-theme="NOMBRE"] { ... }
        if (preg_match_all('/(?:body|:root)\\[data-theme=\\"([a-z0-9_-]+)\\"\\]\\s*\\{([^}]+)\\}/i', $css, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $id = $m[1];
                $vars = $m[2];
                $colors = [];
                // Nombre y descripción amigables
                if(isset($meta[$id])){
                    $nombre = $meta[$id]['nombre'];
                    $desc   = $meta[$id]['descripcion'];
                }else{
                    // Convertir id a palabras capitalizadas
                    $nombre = ucwords(preg_replace('/[_-]+/',' ', $id));
                    $desc   = 'Tema visual personalizado';
                }
                // Extraer colores
                if (preg_match('/--color1:\s*([^;]+);/i', $vars, $c1)) $colors[] = trim($c1[1]);
                if (preg_match('/--color2:\s*([^;]+);/i', $vars, $c2)) $colors[] = trim($c2[1]);
                if (preg_match('/--color3:\s*([^;]+);/i', $vars, $c3)) $colors[] = trim($c3[1]);
                $temas[$id] = [
                    'id' => $id,
                    'nombre' => $nombre,
                    'colors' => $colors,
                    'descripcion' => $desc,
                    'preview_style' => (function() use($colors){
                        // aplicar 20% más transparencia (80% opacidad) con formato #RRGGBBCC
                        $toAlpha=function(string $c){
                            if(preg_match('/^#([0-9a-f]{6})$/i',$c,$m)) return '#'.$m[1].'cc';
                            return $c; // fallback
                        };
                        if(isset($colors[0],$colors[1],$colors[2])){
                            return 'linear-gradient(45deg, '. $toAlpha($colors[0]).' 0%, '. $toAlpha($colors[1]).' 50%, '. $toAlpha($colors[2]).' 100%)';
                        }
                        if(isset($colors[0],$colors[1])){
                            return 'linear-gradient(45deg, '. $toAlpha($colors[0]).' 0%, '. $toAlpha($colors[1]).' 100%)';
                        }
                        return '';
                    })(),
                ];
            }
        }
        return $temas;
    }

    public static function getTema($id) {
        $temas = self::getTemas();
        return $temas[$id] ?? null;
    }

    public static function validarTema($id) {
        $temas = self::getTemas();
        return isset($temas[$id]);
    }

    public static function aplicarTema($tema, $contenido) {
        // Ya no es necesario inyectar CSS, el data-theme y el CSS hacen el trabajo
        return $contenido;
    }

    public static function getPreviewHtml($temaId) {
        $tema = self::getTema($temaId);
        if (!$tema) return '';
        
        return sprintf('
            <div class="tema-preview" style="background: %s">
                <div class="tema-colors">
                    %s
                </div>
                <div class="tema-info">
                    <h4>%s</h4>
                    <p>%s</p>
                </div>
            </div>',
            $tema['preview_style'],
            implode('', array_map(function($color) {
                return sprintf('<div class="color-sample" style="background-color: %s"></div>', $color);
            }, $tema['colors'])),
            htmlspecialchars($tema['nombre']),
            htmlspecialchars($tema['descripcion'])
        );
    }

}
