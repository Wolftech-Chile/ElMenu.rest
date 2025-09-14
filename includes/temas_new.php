<?php
class ThemeManager {
    private static $temas = [
        'classic' => [
            'id' => 'classic',
            'nombre' => 'Clásico',
            'colors' => ['#2196F3', '#1976D2', '#FFFFFF'],
            'descripcion' => 'Diseño tradicional y elegante',
            'preview_style' => 'linear-gradient(45deg, #2196F3 0%, #1976D2 100%)'
        ],
        'modern' => [
            'id' => 'modern',
            'nombre' => 'Moderno',
            'colors' => ['#00BCD4', '#006064', '#FFFFFF'],
            'descripcion' => 'Estilo contemporáneo y minimalista',
            'preview_style' => 'linear-gradient(45deg, #00BCD4 0%, #006064 100%)'
        ],
        'vintage' => [
            'id' => 'vintage',
            'nombre' => 'Vintage',
            'colors' => ['#795548', '#4E342E', '#EFEBE9'],
            'descripcion' => 'Aspecto retro y acogedor',
            'preview_style' => 'linear-gradient(45deg, #795548 0%, #4E342E 100%)'
        ],
        'minimal' => [
            'id' => 'minimal',
            'nombre' => 'Minimalista',
            'colors' => ['#212121', '#000000', '#FFFFFF'],
            'descripcion' => 'Diseño limpio y simple',
            'preview_style' => 'linear-gradient(45deg, #212121 0%, #000000 100%)'
        ],
        'elegant' => [
            'id' => 'elegant',
            'nombre' => 'Elegante',
            'colors' => ['#9C27B0', '#4A148C', '#F3E5F5'],
            'descripcion' => 'Sofisticado y refinado',
            'preview_style' => 'linear-gradient(45deg, #9C27B0 0%, #4A148C 100%)'
        ]
    ];

    public static function getTemas() {
        return self::$temas;
    }

    public static function getTema($id) {
        return self::$temas[$id] ?? null;
    }

    public static function validarTema($id) {
        return isset(self::$temas[$id]);
    }

    public static function aplicarTema($tema, $contenido) {
        $tema = self::getTema($tema);
        if (!$tema) return $contenido;

        // Aplicar colores del tema
        $vars = [
            '--primary-color: ' . $tema['colors'][0] . ';',
            '--secondary-color: ' . $tema['colors'][1] . ';',
            '--background-color: ' . $tema['colors'][2] . ';'
        ];
        
        // Inyectar variables CSS
        $style = '<style>:root {' . implode('', $vars) . '}</style>';
        $contenido = str_replace('</head>', $style . '</head>', $contenido);
        
        return $contenido;
    }
}
