/**
 * dish-sync.js - Sincronización forzada de platos en el DOM
 * Se ejecuta después de cada recarga para asegurar consistencia entre frontend/backend
 */

(function() {
    // Ejecutar después de que DishModule esté disponible y el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[DishSync] Inicializando sistema de sincronización...');
        setTimeout(initDishSync, 300); // Pequeño retraso para asegurar que todo esté cargado
    });
    
    function initDishSync() {
        // Verificar si hay una operación pendiente post-recarga
        const actionJson = sessionStorage.getItem('postReloadAction');
        
        if (!actionJson) {
            console.log('[DishSync] Sin acciones pendientes.');
            return;
        }
        
        try {
            const action = JSON.parse(actionJson);
            console.log('[DishSync] Acción detectada:', action);
            
            // Manejar diferentes tipos de acciones
            if (action.type === 'dishAdded') {
                handleDishAddedPostReload(action);
            }
            
            // Limpiar después de procesar
            sessionStorage.removeItem('postReloadAction');
            
        } catch (error) {
            console.error('[DishSync] Error procesando acción post-recarga:', error);
        }
        
        // Mostrar mensajes de éxito si existen
        const successMsg = sessionStorage.getItem('dishes_reload_success');
        if (successMsg) {
            if (window.NotificationSystem && typeof NotificationSystem.show === 'function') {
                NotificationSystem.show(successMsg, 'success');
            }
            sessionStorage.removeItem('dishes_reload_success');
        }
    }
    
    // Manejar plato añadido post-recarga
    function handleDishAddedPostReload(action) {
        const { id, categoryId, name } = action;
        console.log(`[DishSync] Sincronizando plato añadido ID=${id} en categoría ID=${categoryId}`);
        
        // 1. Buscar elemento del plato en el DOM
        const dishItem = document.querySelector(`.dish-item[data-id="${id}"]`);
        
        // 2. Si el plato ya está en el DOM, solo destacarlo
        if (dishItem) {
            console.log('[DishSync] Plato encontrado en DOM, destacando...');
            highlightElement(dishItem);
            return;
        }
        
        // 3. Si no existe, forzar reconstrucción de la lista de platos
        console.log('[DishSync] Plato no encontrado en DOM, forzando actualización...');
        fetchAndRebuildDishList(categoryId, id);
    }
    
    // Función para obtener y reconstruir la lista de platos de una categoría
    async function fetchAndRebuildDishList(categoryId, highlightDishId = null) {
        try {
            console.log(`[DishSync] Obteniendo datos actualizados para categoría ID=${categoryId}`);
            
            // 1. Encontrar el contenedor de la categoría y su lista de platos
            const dishListContainer = document.querySelector(`.dish-list[data-cat="${categoryId}"]`);
            if (!dishListContainer) {
                console.error(`[DishSync] No se encontró lista para categoría ID=${categoryId}`);
                return;
            }
            
            // 2. Obtener datos actualizados desde el servidor
            const formData = new FormData();
            formData.append('accion', 'obtener_platos_categoria');
            formData.append('categoria_id', categoryId);
            formData.append('csrf_token', CSRF_TOKEN);
            
            const response = await fetch('endpoint.php', {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('[DishSync] Error parseando respuesta:', text);
                throw new Error('Respuesta inválida del servidor');
            }
            
            if (!result.ok || !Array.isArray(result.platos)) {
                throw new Error(result.msg || 'Error obteniendo platos');
            }
            
            console.log(`[DishSync] Recibidos ${result.platos.length} platos para categoría ID=${categoryId}`);
// Log de datos que llegan desde backend
console.table(result.platos);
// Log del HTML existente antes de vaciar
console.log('[DishSync] HTML antes de reconstruir:', dishListContainer.innerHTML);
            
            // 3. Reconstruir completamente la lista de platos
            // Guardar la estructura existente antes de vaciar
            const dragDrop = dishListContainer.getAttribute('data-drag') === 'true';
            
            // Vaciar el contenedor
            dishListContainer.innerHTML = '';
            
            // Recrear cada elemento de plato
            result.platos.forEach(plato => {
                const platoEl = document.createElement('li');
                platoEl.className = 'dish-item';
                platoEl.setAttribute('draggable', dragDrop ? 'true' : 'false');
                platoEl.dataset.id = plato.id;
                
                // Usar el mismo formato HTML que el resto de la aplicación
                platoEl.innerHTML = `
                    <div class="grab">☰</div>
                    <input type="text" class="dish-name" value="${escapeHtml(plato.nombre)}">
                    <textarea class="dish-desc">${escapeHtml(plato.descripcion || '')}</textarea>
                    <input type="text" class="dish-price" value="${plato.precio ? '$'+Number(plato.precio).toLocaleString('es-CL') : ''}">
                    <div class="actions">
                        <a href="#" class="btn-img" data-id="${plato.id}">🖼️</a>
                        <a href="#" class="del-dish" data-id="${plato.id}">🗑️</a>
                    </div>
                `;
                
                // Añadir al contenedor
                dishListContainer.appendChild(platoEl);
                
                // Resaltar si corresponde
                if (highlightDishId && plato.id == highlightDishId) {
                    highlightElement(platoEl);
                }
            });
            
            // 4. Reiniciar cualquier evento o funcionalidad adicional
            if (window.DishModule && typeof DishModule.setupDragDrop === 'function') {
                console.log('[DishSync] Reactivando drag & drop...');
                DishModule.setupDragDrop();
            }
            
            // Log del HTML final después de reconstruir
console.log('[DishSync] HTML después de reconstruir:', dishListContainer.innerHTML);
console.log('[DishSync] Lista de platos reconstruida exitosamente');
            
        } catch (error) {
            console.error('[DishSync] Error reconstruyendo lista:', error);
            if (window.NotificationSystem && typeof NotificationSystem.show === 'function') {
                NotificationSystem.show('Error actualizando la lista de platos. Intente recargar.', 'error');
            }
        }
    }
    
    // Función para resaltar visualmente un elemento
    function highlightElement(element, duration = 3000) {
        if (!element) return;
        
        // Añadir clase de resaltado
        element.classList.add('highlight-added');
        
        // Crear animación si no existe el estilo
        if (!document.querySelector('#highlight-style')) {
            const style = document.createElement('style');
            style.id = 'highlight-style';
            style.textContent = `
                @keyframes highlightPulse {
                    0% { background-color: rgba(76, 175, 80, 0.3); }
                    50% { background-color: rgba(76, 175, 80, 0.6); }
                    100% { background-color: rgba(76, 175, 80, 0.3); }
                }
                .highlight-added {
                    animation: highlightPulse 2s ease-in-out infinite;
                    border: 2px solid #4caf50 !important;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Desplazar a la vista
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Quitar resaltado después del tiempo especificado
        setTimeout(() => {
            element.classList.remove('highlight-added');
        }, duration);
    }
    
    // Función para escapar HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();
