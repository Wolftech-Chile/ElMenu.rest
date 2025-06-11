# ELMENU.REST - README.txt

Bienvenido a ELMENU.REST
========================

Este sistema permite crear menús digitales para restaurantes. Cada restaurante tiene su propia carpeta y base de datos.

INSTALACIÓN RÁPIDA:
-------------------
1. Sube todos los archivos a tu hosting/cPanel.
2. Accede a install.php para crear la base de datos y datos de ejemplo.
3. Ingresa a login.php con:
   - Admin: admin / admin123
   - Restaurant: restaurant / restaurant123
4. Personaliza tu menú desde el dashboard.

ESTRUCTURA DE CARPETAS:
-----------------------
- index.php: Menú público
- login.php: Acceso seguro
- dashboard.php: Panel de administración
- config.php: Configuración
- restaurante.db: Base de datos SQLite
- /assets/: CSS y JS
- /img/: Imágenes
- /img-app/: Íconos sociales

FUNCIONALIDADES:
----------------
- CRUD de categorías y platos
- Personalización visual (17 paletas)
- Control de licencia
- Seguridad avanzada (bcrypt, CSRF, SQLi, XSS)
- Responsive y optimizado para móviles

¿CÓMO CREAR OTRO RESTAURANTE?
-----------------------------
1. Copia la carpeta de un restaurante y renómbrala.
2. Cambia el nombre de la base de datos en config.php.
3. ¡Listo!

¿DUDAS?
-------
Lee el manual o contacta a soporte Andesbytes.cl

¡Disfruta tu menú digital!
