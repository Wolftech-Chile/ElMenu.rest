<?php
/**
 * Herramienta de diagnóstico para problemas de visualización de platos y categorías
 * Esta herramienta muestra datos directos de la base de datos para comparar con la visualización en pantalla
 */
define('DIAGNOSTICO_MODE', true);
require_once('includes/db.php');

// Título de la página
$titulo = 'Herramienta de Diagnóstico';

// Obtener categorías y platos directamente de la base de datos
try {
    $categorias = [];
    
    // Consulta SQL directa para categorías (adaptada para SQLite)
    $stmt = $db->prepare("SELECT id, nombre, orden FROM categorias ORDER BY orden");
    $stmt->execute();
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada categoría, obtener sus platos
    foreach ($cats as $cat) {
        $catInfo = $cat;
        $catInfo['platos'] = [];
        
        $stmt = $db->prepare("SELECT id, nombre, descripcion, precio, imagen, orden, categoria_id FROM platos WHERE categoria_id = ? ORDER BY orden");
        $stmt->execute([$cat['id']]);
        $catInfo['platos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $categorias[] = $catInfo;
    }
    
} catch (Exception $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
        h1, h2, h3 { color: #333; }
        .debug-section { 
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .categoria { 
            margin-bottom: 15px;
            padding: 10px;
            background: #f5f5f5;
            border-left: 4px solid #2196F3;
        }
        .plato {
            padding: 8px;
            margin: 5px 0;
            background: white;
            border: 1px solid #eee;
        }
        .plato-info { display: flex; justify-content: space-between; }
        .no-platos { color: #999; font-style: italic; }
        .actions { margin-top: 20px; }
        button, .btn {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-right: 10px;
        }
        button:hover, .btn:hover { background: #388E3C; }
        .btn-secondary {
            background: #2196F3;
        }
        .btn-secondary:hover {
            background: #1565C0;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            overflow: auto;
            max-height: 400px;
        }
    </style>
</head>
<body>
    <h1><?php echo $titulo; ?></h1>
    
    <?php if (isset($error)): ?>
        <div class="error">
            <p><?php echo $error; ?></p>
        </div>
    <?php else: ?>
        <div class="debug-section">
            <h2>Información de Diagnóstico</h2>
            <p>Esta herramienta muestra los datos directamente desde la base de datos, sin pasar por la lógica de presentación de la aplicación.</p>
            
            <div class="actions">
                <a href="index.php" class="btn">Volver al panel principal</a>
                <button id="btn-compare" class="btn-secondary">Comparar con DOM actual</button>
                <button id="btn-inspect" class="btn-secondary">Inspeccionar estructura HTML</button>
            </div>
        </div>
        
        <div class="debug-section">
            <h2>Datos directos de la Base de Datos</h2>
            <p>Total de categorías: <strong><?php echo count($categorias); ?></strong></p>
            
            <?php foreach ($categorias as $categoria): ?>
                <div class="categoria">
                    <h3>Categoría: <?php echo htmlspecialchars($categoria['nombre']); ?> (ID: <?php echo $categoria['id']; ?>)</h3>
                    <p>Orden: <?php echo $categoria['orden']; ?></p>
                    
                    <?php if (empty($categoria['platos'])): ?>
                        <p class="no-platos">Esta categoría no tiene platos</p>
                    <?php else: ?>
                        <p>Total de platos: <strong><?php echo count($categoria['platos']); ?></strong></p>
                        <?php foreach ($categoria['platos'] as $plato): ?>
                            <div class="plato">
                                <div class="plato-info">
                                    <strong><?php echo htmlspecialchars($plato['nombre']); ?></strong>
                                    <span>ID: <?php echo $plato['id']; ?></span>
                                </div>
                                <div>Descripción: <?php echo htmlspecialchars($plato['descripcion']); ?></div>
                                <div>Precio: $<?php echo number_format($plato['precio'], 0, '', '.'); ?></div>
                                <div>Orden: <?php echo $plato['orden']; ?></div>
                                <div>Pertenece a categoría ID: <?php echo $plato['categoria_id']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="debug-section">
            <h2>Estructura de la aplicación</h2>
            <div id="estructura-container">
                <button id="btn-analizar" class="btn-secondary">Analizar estructura de la aplicación</button>
                <div id="estructura-resultado" style="margin-top: 15px;"></div>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        // Función para comparar datos de la BD con el DOM actual
        document.getElementById('btn-compare').addEventListener('click', function() {
            // Abrir en una nueva ventana/pestaña la página principal
            const mainWindow = window.open('index.php', 'main_window');
            
            // Esperar a que cargue y luego ejecutar análisis
            setTimeout(() => {
                try {
                    // Extraer datos del DOM
                    const domData = analyzeDOMStructure(mainWindow);
                    
                    // Mostrar resultados
                    const resultContainer = document.createElement('div');
                    resultContainer.innerHTML = `
                        <h3>Resultados de la comparación</h3>
                        <pre>${JSON.stringify(domData, null, 2)}</pre>
                    `;
                    
                    document.querySelector('.debug-section').appendChild(resultContainer);
                } catch (error) {
                    alert('Error analizando DOM: ' + error.message);
                }
            }, 3000);
        });
        
        // Función para inspeccionar la estructura HTML
        document.getElementById('btn-inspect').addEventListener('click', function() {
            const iframe = document.createElement('iframe');
            iframe.src = 'index.php';
            iframe.style.width = '100%';
            iframe.style.height = '500px';
            iframe.style.border = '1px solid #ddd';
            
            const container = document.createElement('div');
            container.innerHTML = '<h3>Vista previa de la página</h3>';
            container.appendChild(iframe);
            
            document.querySelector('.debug-section').appendChild(container);
        });
        
        // Función para analizar la estructura de la aplicación
        document.getElementById('btn-analizar').addEventListener('click', function() {
            const resultadoDiv = document.getElementById('estructura-resultado');
            
            resultadoDiv.innerHTML = `
                <h3>Análisis del flujo de datos</h3>
                <pre>
1. Flujo de creación de categorías:
   - El formulario form-cat-add envía datos a desktop.php
   - La acción 'agregar_categoria' crea la categoría en la base de datos
   - Se recarga la página para mostrar la nueva categoría
   - Se utiliza sessionStorage para rastrear la categoría recién creada

2. Flujo de creación de platos:
   - El formulario form-dish-add envía datos a desktop.php
   - La acción 'agregar_plato' crea el plato en la base de datos
   - Se asocia el plato con la categoría mediante el ID de categoría
   - Se recarga la página para mostrar el nuevo plato
   
3. Posibles puntos de fallo:
   a. El ID de categoría no se transmite correctamente al enviar el plato
   b. La recarga de página no actualiza correctamente la visualización
   c. La estructura HTML no refleja adecuadamente los datos de la base de datos
   d. El código JavaScript no manipula correctamente los elementos del DOM

4. Diagnóstico recomendado:
   - Verificar en desktop.php cómo se procesa la acción 'agregar_plato'
   - Comprobar si hay lógica condicional que pueda estar filtrando platos
   - Examinar el HTML generado para verificar si contiene todos los datos
   - Revisar si hay diferencias entre el HTML inicial y después de la recarga
</pre>
            `;
        });
        
        // Función para analizar la estructura del DOM
        function analyzeDOMStructure(targetWindow) {
            try {
                const categorias = [];
                
                // Analizar categorías en el DOM
                const catItems = targetWindow.document.querySelectorAll('.cat-item');
                catItems.forEach(catItem => {
                    const catId = catItem.dataset.id;
                    const catNombre = catItem.querySelector('input').value;
                    const platos = [];
                    
                    // Buscar lista de platos para esta categoría
                    const dishList = targetWindow.document.querySelector(`.dish-list[data-cat="${catId}"]`);
                    if (dishList) {
                        const dishItems = dishList.querySelectorAll('.dish-item');
                        dishItems.forEach(dishItem => {
                            platos.push({
                                id: dishItem.dataset.id,
                                nombre: dishItem.querySelector('.dish-name').value,
                                descripcion: dishItem.querySelector('.dish-desc').value,
                                precio: dishItem.querySelector('.dish-price').value,
                                categoria_id: catId
                            });
                        });
                    }
                    
                    categorias.push({
                        id: catId,
                        nombre: catNombre,
                        platos: platos
                    });
                });
                
                return { categorias };
            } catch (error) {
                console.error('Error analizando DOM:', error);
                return { error: error.message };
            }
        }
    </script>
</body>
</html>
