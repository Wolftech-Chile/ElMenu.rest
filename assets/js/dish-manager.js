/**
 * Gestor de platos y categorías (DishManager)
 * Sistema modular para manejo de platos y categorías con renderizado consistente
 * Reemplaza la implementación anterior para garantizar sincronización frontend/backend
 */

const DishManager = {
    // Configuración
    config: {
        endpoints: {
            base: 'desktop.php',
            addDish: 'guardar_plato',
            deleteDish: 'eliminar_plato',
            getDishes: 'obtener_platos_categoria',
            addCategory: 'agregar_categoria'
        },
        selectors: {
            categoryItems: '.cat-item',
            dishLists: '.dish-list',
            dishItems: '.dish-item',
            addDishForms: '.form-dish-add',
            addCategoryForm: '#form-cat-add',
            deleteDishBtn: '.del-dish'
        }
    },

    // Estado interno para tracking
    state: {
        lastAddedDish: null,
        lastAddedCategory: null
    },

    // Inicialización
    init() {
        console.log('[DishManager] Inicialización...');
        this.loadPostReloadState();
        this.bindEvents();
        this.setupDragDrop();
    },

    // Cargar estado post-recarga
    loadPostReloadState() {
        try {
            // Detectar acciones post-recarga
            const actionJson = sessionStorage.getItem('postReloadAction');
            if (actionJson) {
                const action = JSON.parse(actionJson);
                console.log('[DishManager] Acción post-recarga detectada:', action);
                
                // Manejar diferentes tipos de acciones
                if (action.type === 'dishAdded') {
                    this.handleDishAddedPostReload(action);
                } else if (action.type === 'categoryAdded') {
                    this.handleCategoryAddedPostReload(action);
                }
                
                // Limpiar después de procesar
                sessionStorage.removeItem('postReloadAction');
            }
            
            // Mostrar mensajes de éxito si existen
            const successMsg = sessionStorage.getItem('operation_success');
            if (successMsg) {
                NotificationSystem.show(successMsg, 'success');
                sessionStorage.removeItem('operation_success');
            }
        } catch (error) {
            console.error('[DishManager] Error cargando estado post-recarga:', error);
        }
    },

    // Manejar plato agregado post-recarga
    handleDishAddedPostReload(action) {
        const { id, categoryId, name } = action;
        console.log(`[DishManager] Manejando plato agregado ID=${id} en categoría ID=${categoryId}`);
        
        // Verificar si el plato ya existe en el DOM
        const dishItem = document.querySelector(`.dish-item[data-id="${id}"]`);
        if (dishItem) {
            console.log('[DishManager] Plato encontrado en DOM, destacando...');
            this.highlightElement(dishItem);
            return;
        }
        
        // Si no existe, forzar reconstrucción completa de la lista
        console.log('[DishManager] Plato no encontrado en DOM, reconstruyendo lista...');
        this.forceRebuildDishList(categoryId, id);
    },

    // Manejar categoría agregada post-recarga
    handleCategoryAddedPostReload(action) {
        const { id, name } = action;
        console.log(`[DishManager] Manejando categoría agregada ID=${id}`);
        
        // Buscar y destacar la categoría
        const categoryItem = document.querySelector(`.cat-item[data-id="${id}"]`);
        if (categoryItem) {
            this.highlightElement(categoryItem);
            
            // Auto-seleccionar esta categoría en todos los selectores
            document.querySelectorAll('select[name="categoria_id"]').forEach(select => {
                const option = select.querySelector(`option[value="${id}"]`);
                if (option) {
                    select.value = id;
                }
            });
        }
    },

    // Resaltar elemento visualmente
    highlightElement(element, duration = 3000) {
        element.classList.add('highlight');
        setTimeout(() => element.classList.remove('highlight'), duration);
        
        // Scroll hacia el elemento
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
    
    // Forzar reconstrucción de lista de platos
    async forceRebuildDishList(categoryId, highlightDishId = null) {
        try {
            // 1. Encontrar el contenedor de la categoría
            const catContainer = document.querySelector(`.cat-item[data-id="${categoryId}"]`);
            if (!catContainer) {
                console.error(`[DishManager] No se encontró contenedor para categoría ID=${categoryId}`);
                return;
            }
            
            // 2. Encontrar la lista de platos
            const dishListContainer = document.querySelector(`.dish-list[data-cat="${categoryId}"]`);
            if (!dishListContainer) {
                console.error(`[DishManager] No se encontró lista de platos para categoría ID=${categoryId}`);
                return;
            }
            
            // 3. Solicitar datos actualizados del backend
            console.log(`[DishManager] Solicitando platos actualizados para categoría ID=${categoryId}`);
            const formData = new FormData();
            formData.append('accion', this.config.endpoints.getDishes);
            formData.append('categoria_id', categoryId);
            formData.append('csrf_token', CSRF_TOKEN);
            
            const response = await fetch(this.config.endpoints.base, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log(`[DishManager] Datos recibidos:`, result);
            
            if (!result.ok || !Array.isArray(result.platos)) {
                throw new Error('Formato de respuesta inválido o error en servidor');
            }
            
            // 4. Reconstruir completamente la lista de platos
            console.log(`[DishManager] Reconstruyendo lista con ${result.platos.length} platos`);
            
            // Vaciar contenedor actual
            dishListContainer.innerHTML = '';
            
            // Recrear cada elemento de plato
            result.platos.forEach(plato => {
                const platoEl = document.createElement('li');
                platoEl.className = 'dish-item';
                platoEl.setAttribute('draggable', 'true');
                platoEl.dataset.id = plato.id;
                
                // Estructura interna del plato (mantener consistente con el resto del código)
                platoEl.innerHTML = `
                    <div class="grab">☰</div>
                    <input type="text" class="dish-name" value="${this.escapeHtml(plato.nombre)}">
                    <textarea class="dish-desc">${this.escapeHtml(plato.descripcion || '')}</textarea>
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
                    this.highlightElement(platoEl);
                }
            });
            
            // 5. Restablecer funcionalidad de drag & drop
            this.setupDragDrop();
            
            console.log('[DishManager] Reconstrucción completada exitosamente');
        } catch (error) {
            console.error('[DishManager] Error reconstruyendo lista:', error);
            NotificationSystem.show('Error actualizando la lista de platos', 'error');
        }
    },
    
    // Agregar plato
    async handleAddDish(form) {
        if (!form || !form.matches(this.config.selectors.addDishForms)) return;
        
        // 1. Obtener y validar datos del formulario
        const formData = new FormData(form);
        const categoriaId = formData.get('categoria_id');
        const nombre = formData.get('nombre');
        
        if (!categoriaId || !nombre) {
            NotificationSystem.show('Faltan datos requeridos', 'error');
            return;
        }
        
        try {
            // 2. Deshabilitar botón de envío
            const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            if (submitBtn) {
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Guardando...';
            }
            
            // 3. Enviar datos al servidor
            console.log(`[DishManager] Guardando plato "${nombre}" en su categoría`);
            
            // Asegurar que tenga el token CSRF
            formData.append('csrf_token', CSRF_TOKEN);
            
            const response = await fetch(this.config.endpoints.base, {
                method: 'POST',
                body: formData
            });
            
            const result = await this.parseResponse(response);
            
            if (!result.ok) {
                throw new Error(result.msg || 'Error al guardar el plato');
            }
            
            // 4. Preparar para post-recarga
            console.log(`[DishManager] Plato guardado exitosamente con ID=${result.id}`);
            
            // Guardar datos para post-recarga
            sessionStorage.setItem('operation_success', 'Plato guardado correctamente');
            sessionStorage.setItem('postReloadAction', JSON.stringify({
                type: 'dishAdded',
                id: result.id,
                categoryId: categoriaId,
                name: nombre,
                timestamp: Date.now()
            }));
            
            // 5. Resetear formulario y recargar
            form.reset();
            window.location.reload();
            
        } catch (error) {
            console.error('[DishManager] Error guardando plato', error);
            NotificationSystem.show(error.message || 'Error al guardar el plato', 'error');
            
            // Re-habilitar botón
            const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('button');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Agregar';
            }
        }
    },
    
    // Eliminar plato
    async handleDeleteDish(button) {
        if (!button || !button.matches(this.config.selectors.deleteDishBtn)) return;
        
        const platoId = button.dataset.id;
        if (!platoId) {
            console.error('[DishManager] ID de plato no encontrado');
            return;
        }
        
        try {
            // 1. Mostrar confirmación
            const confirmed = await NotificationSystem.confirm('¿Estás seguro de eliminar este plato?');
            if (!confirmed) return;
            
            // 2. Enviar solicitud al servidor
            const formData = new FormData();
            formData.append('accion', this.config.endpoints.deleteDish);
            formData.append('plato_id', platoId);
            formData.append('csrf_token', CSRF_TOKEN);
            
            const response = await fetch(this.config.endpoints.base, {
                method: 'POST',
                body: formData
            });
            
            const result = await this.parseResponse(response);
            
            if (!result.ok) {
                throw new Error(result.msg || 'Error al eliminar el plato');
            }
            
            // 3. Guardar éxito para post-recarga
            sessionStorage.setItem('operation_success', 'Plato eliminado correctamente');
            
            // 4. Recargar página
            window.location.reload();
            
        } catch (error) {
            console.error('[DishManager] Error eliminando plato:', error);
            NotificationSystem.show(error.message || 'Error al eliminar el plato', 'error');
        }
    },
    
    // Configurar drag & drop
    setupDragDrop() {
        const dishLists = document.querySelectorAll(this.config.selectors.dishLists);
        if (!dishLists.length) return;
        
        let draggedItem = null;
        
        dishLists.forEach(list => {
            // Configurar items arrastrables
            list.querySelectorAll(this.config.selectors.dishItems).forEach(item => {
                // Inicio del arrastre
                item.addEventListener('dragstart', function(e) {
                    draggedItem = this;
                    setTimeout(() => this.classList.add('dragging'), 0);
                });
                
                // Fin del arrastre
                item.addEventListener('dragend', function() {
                    this.classList.remove('dragging');
                });
                
                // Manejar el grabber
                const grabber = item.querySelector('.grab');
                if (grabber) {
                    grabber.addEventListener('mousedown', () => {
                        item.setAttribute('draggable', 'true');
                    });
                    grabber.addEventListener('mouseup', () => {
                        item.setAttribute('draggable', 'false');
                    });
                }
            });
            
            // Permitir soltar
            list.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!draggedItem) return;
                
                const afterElement = this.getDragAfterElement(list, e.clientY);
                if (afterElement) {
                    list.insertBefore(draggedItem, afterElement);
                } else {
                    list.appendChild(draggedItem);
                }
            });
        });
    },
    
    // Determinar posición en drag & drop
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll(`${this.config.selectors.dishItems}:not(.dragging)`)];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },
    
    // Helpers
    async parseResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('[DishManager] Error parseando respuesta:', text);
            throw new Error('Respuesta inválida del servidor');
        }
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    // Eventos
    bindEvents() {
        // 1. Delegación para eventos de clic
        document.addEventListener('click', (e) => {
            // Botón eliminar plato
            if (e.target.closest(this.config.selectors.deleteDishBtn)) {
                e.preventDefault();
                this.handleDeleteDish(e.target.closest(this.config.selectors.deleteDishBtn));
            }
            
            // Otros botones de acción aquí...
        });
        
        // 2. Delegación para formularios
        document.addEventListener('submit', (e) => {
            // Formulario agregar plato
            if (e.target.matches(this.config.selectors.addDishForms)) {
                e.preventDefault();
                this.handleAddDish(e.target);
            }
            
            // Formulario agregar categoría
            if (e.target.matches(this.config.selectors.addCategoryForm)) {
                // Esto se maneja por separado en desktop.js
            }
        });
    }
};

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    console.log('[DishManager] Inicializando sistema mejorado de gestión de platos...');
    DishManager.init();
});
