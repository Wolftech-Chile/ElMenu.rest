<?php
// install.php - Inicializa la base de datos con datos de ejemplo
require_once 'config.php';
if (file_exists($db_file)) {
    die('La base de datos ya existe. Elimina el archivo para reinstalar.');
}
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Crear tablas
$pdo->exec('
CREATE TABLE restaurante (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  direccion TEXT,
  horario TEXT,
  telefono TEXT,
  facebook TEXT,
  instagram TEXT,
  logo TEXT,
  tema TEXT DEFAULT "chilena",
  slogan TEXT,
  seo_desc TEXT,
  seo_img TEXT,
  fecha_licencia TEXT,
  fondo_header TEXT
);
CREATE TABLE usuarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  usuario TEXT UNIQUE NOT NULL,
  clave TEXT NOT NULL,
  rol TEXT NOT NULL
);
CREATE TABLE categorias (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  orden INTEGER DEFAULT 0
);
CREATE TABLE platos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  categoria_id INTEGER NOT NULL,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  precio INTEGER NOT NULL,
  imagen TEXT,
  orden INTEGER DEFAULT 0,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);
');

$pdo->beginTransaction();
try {
    // Insertar restaurante demo
    $pdo->prepare('INSERT INTO restaurante (nombre, direccion, horario, telefono, facebook, instagram, logo, tema, slogan, seo_desc, seo_img, fecha_licencia, fondo_header) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            'Don Gussini',
            'Av. Siempre Viva 742',
            '12:00 - 23:00',
            '+56945787874',
            'https://facebook.com/dongussini',
            'https://instagram.com/dongussini',
            'img/logo-demo.png',
            'chilena',
            '¡El mejor sabor italiano en tu mesa!',
            'Restaurante Don Gussini: pastas, pizzas y más. Menú digital actualizado.',
            'img/logo-demo.png',
            '01-10-2025',
            'img/fondo-demo.jpg'
        ]);
    // Insertar usuarios demo
    $pdo->prepare('INSERT INTO usuarios (usuario, clave, rol) VALUES (?,?,?)')->execute([
        'admin', password_hash('admin123', PASSWORD_BCRYPT, ['cost'=>12]), 'admin'
    ]);
    $pdo->prepare('INSERT INTO usuarios (usuario, clave, rol) VALUES (?,?,?)')->execute([
        'restaurant', password_hash('restaurant123', PASSWORD_BCRYPT, ['cost'=>12]), 'restaurant'
    ]);
    // Insertar categorías y platos demo
    $cats = [
        'Entradas', 'Pastas', 'Pizzas', 'Carnes', 'Postres', 'Bebidas'
    ];
    $platos_demo = [
        // Entradas
        ['Bruschetta', 'Pan tostado con tomate y albahaca', 3500, 'img/plato-demo.jpg'],
        ['Carpaccio', 'Láminas de carne con parmesano', 4800, 'img/plato-demo.jpg'],
        ['Ensalada Caprese', 'Mozzarella, tomate y albahaca fresca', 3900, 'img/plato-demo.jpg'],
        ['Antipasto', 'Selección de fiambres y quesos', 4200, 'img/plato-demo.jpg'],
        ['Sopa Minestrone', 'Sopa italiana de verduras', 3200, 'img/plato-demo.jpg'],
        ['Focaccia', 'Pan italiano con romero y aceite de oliva', 3100, 'img/plato-demo.jpg'],
        // Pastas
        ['Spaghetti Bolognesa', 'Pasta con salsa de carne', 6500, 'img/plato-demo.jpg'],
        ['Ravioli Ricotta', 'Ravioles rellenos de ricotta', 6900, 'img/plato-demo.jpg'],
        ['Lasagna', 'Láminas de pasta, carne y bechamel', 7200, 'img/plato-demo.jpg'],
        ['Fettuccine Alfredo', 'Pasta con salsa cremosa', 6700, 'img/plato-demo.jpg'],
        ['Penne Arrabiata', 'Pasta picante con tomate', 6300, 'img/plato-demo.jpg'],
        ['Gnocchi', 'Ñoquis de papa con salsa pesto', 6800, 'img/plato-demo.jpg'],
        // Pizzas
        ['Pizza Margarita', 'Tomate, mozzarella y albahaca', 8000, 'img/plato-demo.jpg'],
        ['Pizza Pepperoni', 'Mozzarella y pepperoni', 8500, 'img/plato-demo.jpg'],
        ['Pizza Cuatro Quesos', 'Mozzarella, gorgonzola, parmesano, fontina', 8900, 'img/plato-demo.jpg'],
        ['Pizza Vegetariana', 'Verduras frescas y mozzarella', 8200, 'img/plato-demo.jpg'],
        ['Pizza Hawaiana', 'Jamón, piña y mozzarella', 8300, 'img/plato-demo.jpg'],
        ['Pizza Prosciutto', 'Jamón crudo y rúcula', 9000, 'img/plato-demo.jpg'],
        // Carnes
        ['Lomo Saltado', 'Trozos de lomo con papas', 9500, 'img/plato-demo.jpg'],
        ['Pollo Parmesano', 'Pechuga con salsa y queso', 8900, 'img/plato-demo.jpg'],
        ['Costillas BBQ', 'Costillas de cerdo con salsa BBQ', 10500, 'img/plato-demo.jpg'],
        ['Bife de Chorizo', 'Corte argentino a la parrilla', 11200, 'img/plato-demo.jpg'],
        ['Milanesa Napolitana', 'Filete empanizado con salsa y queso', 8700, 'img/plato-demo.jpg'],
        ['Brochetas Mixtas', 'Brochetas de carne y verduras', 8800, 'img/plato-demo.jpg'],
        // Postres
        ['Tiramisú', 'Postre italiano clásico', 4000, 'img/plato-demo.jpg'],
        ['Panna Cotta', 'Postre de nata y frutos rojos', 3800, 'img/plato-demo.jpg'],
        ['Gelato', 'Helado artesanal italiano', 3500, 'img/plato-demo.jpg'],
        ['Cannoli', 'Dulce relleno de ricotta', 3700, 'img/plato-demo.jpg'],
        ['Zabaione', 'Crema de huevo y vino dulce', 3600, 'img/plato-demo.jpg'],
        ['Brownie con helado', 'Brownie tibio y bola de helado', 3900, 'img/plato-demo.jpg'],
        // Bebidas
        ['Agua Mineral', 'Botella 500ml', 1500, 'img/plato-demo.jpg'],
        ['Jugo Natural', 'Jugo de frutas natural', 2000, 'img/plato-demo.jpg'],
        ['Coca Cola', 'Lata 350ml', 1800, 'img/plato-demo.jpg'],
        ['Sprite', 'Lata 350ml', 1800, 'img/plato-demo.jpg'],
        ['Cerveza Artesanal', 'Botella 330ml', 2500, 'img/plato-demo.jpg'],
        ['Vino de la Casa', 'Copa de vino tinto/blanco', 3200, 'img/plato-demo.jpg'],
    ];
    $cat_ids = [];
    foreach ($cats as $i => $cat) {
        $pdo->prepare('INSERT INTO categorias (nombre, orden) VALUES (?,?)')->execute([$cat, $i]);
        $cat_ids[] = $pdo->lastInsertId();
    }
    for ($i=0; $i<count($cats); $i++) {
        for ($j=0; $j<6; $j++) {
            $p = $platos_demo[$i*6+$j];
            $pdo->prepare('INSERT INTO platos (categoria_id, nombre, descripcion, precio, imagen, orden) VALUES (?,?,?,?,?,?)')
                ->execute([$cat_ids[$i], $p[0], $p[1], $p[2], $p[3], $j]);
        }
    }
    $pdo->commit();
    echo 'Base de datos creada y precargada con éxito.';
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error al crear la base de datos: ' . $e->getMessage());
}
