<?php
// index.php - Menú público del restaurante
// Seguridad: XSS, SQLi, lazy load, SEO, paleta visual

require_once 'config.php';

// Conexión segura a SQLite
try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Error de conexión a la base de datos.');
}

// Cargar datos del restaurante
$stmt = $pdo->prepare('SELECT nombre, direccion, horario, telefono, facebook, instagram, logo, tema, slogan, seo_desc, seo_img, fondo_header FROM restaurante LIMIT 1');
$stmt->execute();
$rest = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rest) die('Restaurante no configurado.');

// Cargar categorías y platos
$cat_stmt = $pdo->prepare('SELECT id, nombre FROM categorias ORDER BY orden ASC');
$cat_stmt->execute();
$categorias = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

$platos_por_cat = [];
foreach ($categorias as $cat) {
    $plato_stmt = $pdo->prepare('SELECT nombre, descripcion, precio, imagen FROM platos WHERE categoria_id = ? ORDER BY orden ASC');
    $plato_stmt->execute([$cat['id']]);
    $platos = $plato_stmt->fetchAll(PDO::FETCH_ASSOC);
    // Si la imagen está vacía, usar demo
    foreach ($platos as &$plato) {
        if (empty($plato['imagen']) || !file_exists($plato['imagen'])) {
            $plato['imagen'] = 'img/plato-demo.jpg';
        }
    }
    $platos_por_cat[$cat['id']] = $platos;
}

// Función para escapar XSS
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Paleta visual
$tema = esc($rest['tema'] ?? 'chilena');

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= esc($rest['nombre']) ?> | <?= esc($rest['slogan']) ?></title>
    <meta name="description" content="<?= esc($rest['seo_desc']) ?>">
    <meta property="og:title" content="<?= esc($rest['nombre']) ?>">
    <meta property="og:description" content="<?= esc($rest['seo_desc']) ?>">
    <meta property="og:image" content="<?= esc($rest['seo_img']) ?>">
    <link rel="stylesheet" href="assets/style-restaurants.css">
    <link rel="preload" as="image" href="<?= esc($rest['logo']) ?>">
    <script defer src="assets/scripts.js"></script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Restaurant",
      "name": "<?= esc($rest['nombre']) ?>",
      "address": "<?= esc($rest['direccion']) ?>",
      "telephone": "<?= esc($rest['telefono']) ?>"
    }
    </script>
</head>
<body data-theme="<?= $tema ?>">
    <header style="background:<?php if (!empty($rest['fondo_header'])): ?> url('<?= esc($rest['fondo_header']) ?>') center/cover no-repeat;<?php else: ?> var(--color1, #fff);<?php endif; ?> color:#fff; text-align:center; padding:1.5em 0 1em 0; border-radius:0 0 20px 20px; box-shadow:0 2px 8px #0001; position:relative; overflow:hidden;">
        <img src="<?= esc($rest['logo']) ?>" alt="Logo" width="150" height="150" loading="lazy" style="position:relative;z-index:2;">
        <h1 style="position:relative;z-index:2; color:#fff;"><?= esc($rest['nombre']) ?></h1>
        <div class="rest-info slogan" style="position:relative;z-index:2; color:#fff;"><?= esc($rest['slogan']) ?></div>
        <div class="rest-info" style="position:relative;z-index:2; color:#fff;">
            <span>📍 <?= esc($rest['direccion']) ?></span> |
            <span>⏰ <?= esc($rest['horario']) ?></span> |
            <span>📞 <a href="tel:<?= esc($rest['telefono']) ?>" style="color:#fff;"><?= esc($rest['telefono']) ?></a></span>
        </div>
        <div class="rest-social" style="position:relative;z-index:2; color:#fff;">
            <?php if ($rest['facebook']): ?>
                <a href="<?= esc($rest['facebook']) ?>" target="_blank"><img src="img-app/facebook.svg" alt="Facebook" width="28" height="28"></a>
            <?php endif; ?>
            <?php if ($rest['instagram']): ?>
                <a href="<?= esc($rest['instagram']) ?>" target="_blank"><img src="img-app/instagram.svg" alt="Instagram" width="28" height="28"></a>
            <?php endif; ?>
        </div>
    </header>
    <nav class="categorias-scroll" tabindex="0" aria-label="Categorías" style="-webkit-overflow-scrolling:touch;overflow-x:auto;white-space:nowrap;">
        <?php foreach ($categorias as $cat): ?>
            <button class="categoria" onclick="document.getElementById('cat<?= $cat['id'] ?>').scrollIntoView({behavior:'smooth'});" type="button">
                <?= esc($cat['nombre']) ?>
            </button>
        <?php endforeach; ?>
    </nav>
    <script>
    // Mejora UX: arrastrar con el dedo/cursor para scroll horizontal en móvil
    const catScroll = document.querySelector('.categorias-scroll');
    let isDown = false, startX, scrollLeft;
    catScroll.addEventListener('mousedown', e => {
      isDown = true;
      catScroll.classList.add('dragging');
      startX = e.pageX - catScroll.offsetLeft;
      scrollLeft = catScroll.scrollLeft;
    });
    catScroll.addEventListener('mouseleave', () => { isDown = false; catScroll.classList.remove('dragging'); });
    catScroll.addEventListener('mouseup', () => { isDown = false; catScroll.classList.remove('dragging'); });
    catScroll.addEventListener('mousemove', e => {
      if (!isDown) return;
      e.preventDefault();
      const x = e.pageX - catScroll.offsetLeft;
      catScroll.scrollLeft = scrollLeft - (x - startX);
    });
    // Touch para móvil
    catScroll.addEventListener('touchstart', e => {
      isDown = true;
      startX = e.touches[0].pageX - catScroll.offsetLeft;
      scrollLeft = catScroll.scrollLeft;
    });
    catScroll.addEventListener('touchend', () => { isDown = false; });
    catScroll.addEventListener('touchmove', e => {
      if (!isDown) return;
      const x = e.touches[0].pageX - catScroll.offsetLeft;
      catScroll.scrollLeft = scrollLeft - (x - startX);
    });
    </script>
    <main>
        <div class="resumen-pedido">
            <h4>Resumen de tu pedido</h4>
            <div>Total: <span id="resumen-total">$0</span></div>
            <div>Propina 10%: <span id="resumen-propina">$0</span></div>
            <div style="font-weight:bold;">Total a pagar: <span id="resumen-final">$0</span></div>
            <button class="btn-limpiar" onclick="limpiarPedido()">Limpiar pedido</button>
        </div>
        <?php foreach ($categorias as $cat): ?>
            <section id="cat<?= $cat['id'] ?>" class="categoria-bloque">
                <h2><?= esc($cat['nombre']) ?></h2>
                <div class="platos grid-3">
                <?php foreach ($platos_por_cat[$cat['id']] as $plato): ?>
                    <article class="plato">
                        <img src="<?= esc($plato['imagen']) ?>" alt="<?= esc($plato['nombre']) ?>" loading="lazy">
                        <div class="plato-info">
                            <h3><?= esc($plato['nombre']) ?></h3>
                            <p><?= esc($plato['descripcion']) ?></p>
                            <div class="precio-cantidad">
                                <span class="precio">$<?= number_format($plato['precio'], 0, '', '.') ?></span>
                                <div class="cantidad-control">
                                    <button type="button" class="cantidad-btn" onclick="this.nextElementSibling.stepDown();this.nextElementSibling.dispatchEvent(new Event('input'))">-</button>
                                    <input type="number" name="<?= esc($plato['nombre']) ?>" min="0" max="99" value="0" data-precio="<?= $plato['precio'] ?>" class="cantidad-num" readonly>
                                    <button type="button" class="cantidad-btn" onclick="this.previousElementSibling.stepUp();this.previousElementSibling.dispatchEvent(new Event('input'))">+</button>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
    <footer>
        <small>Menú digital por <a href="https://andesbytes.cl" target="_blank">Andesbytes</a></small>
    </footer>
</body>
</html>
