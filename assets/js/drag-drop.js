// Sistema modular de drag & drop con manejo de errores y animaciones
const dragDropHelpers = {
    dragSrc: null,
    placeholder: null,
    animations: true,
    
    /**
     * Inicializa el sistema de drag & drop
     * @param {HTMLElement} container - Contenedor de los elementos arrastrables
     * @param {string} itemSelector - Selector CSS para los elementos arrastrables
     * @param {string} orderSelector - Selector CSS para los elementos de orden
     * @param {Object} options - Opciones de configuración
     */
    initDragDrop: function(container, itemSelector, orderSelector, options = {}) {
        if (!container || !itemSelector) {
            console.error('Container and itemSelector are required');
            return;
        }

        const items = container.querySelectorAll(itemSelector);
        if (!items.length) {
            console.warn('No draggable items found with selector:', itemSelector);
            return;
        }

        this.dragSrc = null;
        
        this.placeholder = document.createElement(container.children[0]?.tagName || 'div');
        this.placeholder.className = `${itemSelector.replace('.', '')} placeholder`;
        this.placeholder.style.cssText = `
            background: #e3e3e3;
            border: 2px dashed #2196F3;
            min-height: ${options.minHeight || '38px'};
            transition: all 0.3s ease;
        `;
        
        items.forEach(item => {
            this.setupDraggableItem(item, container, itemSelector, orderSelector, options);
            this.setupTouchEvents(item, container, itemSelector, orderSelector, options);
        });
        
        this.setupContainerEvents(container, orderSelector);
    },
    
    setupDraggableItem(item, container, itemSelector, orderSelector, options) {
        item.draggable = true;
        
        item.addEventListener('dragstart', e => {
            this.dragSrc = item;
            e.dataTransfer.effectAllowed = 'move';
            
            if (this.animations) {
                item.classList.add('dragging');
                setTimeout(() => { item.style.opacity = '0.4'; }, 0);
            }
        });
        
        item.addEventListener('dragend', e => {
            this.handleDragEnd(container, itemSelector, options);
        });
        
        item.addEventListener('dragover', e => {
            e.preventDefault();
            if (item !== this.dragSrc && !item.classList.contains('placeholder')) {
                const rect = item.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                
                if (e.clientY < midY) {
                    container.insertBefore(this.placeholder, item);
                } else {
                    container.insertBefore(this.placeholder, item.nextSibling);
                }
            }
        });
        
        item.addEventListener('drop', e => this.handleDrop(e, container, orderSelector));
    },
    
    setupTouchEvents(item, container, itemSelector, orderSelector, options) {
        let touchStartY;
        let currentY;
        let initialPosition;
        
        item.addEventListener('touchstart', e => {
            touchStartY = e.touches[0].clientY;
            initialPosition = Array.from(container.children).indexOf(item);
            item.classList.add('touching');
        }, { passive: true });
        
        item.addEventListener('touchmove', e => {
            e.preventDefault();
            currentY = e.touches[0].clientY;
            const deltaY = currentY - touchStartY;
            
            item.style.transform = `translateY(${deltaY}px)`;
            this.updateTouchPosition(item, container, deltaY);
        });
        
        item.addEventListener('touchend', () => {
            item.classList.remove('touching');
            item.style.transform = '';
            this.finalizeTouchMove(container, orderSelector, options);
        });
    },
    
    handleDragEnd(container, itemSelector, options) {
        this.dragSrc.classList.remove('dragging');
        this.dragSrc.style.opacity = '';
        this.dragSrc = null;
        
        container.querySelectorAll('.placeholder').forEach(p => p.remove());
        container.querySelectorAll(itemSelector).forEach(x => {
            x.style.display = '';
            x.style.opacity = '';
        });
        
        if (options.onReorder) {
            try {
                options.onReorder(container);
            } catch (err) {
                console.error('Error in onReorder callback:', err);
                notifications?.error?.('Error al reordenar elementos');
            }
        }
    },
    
    handleDrop(e, container, orderSelector) {
        e.preventDefault();
        if (this.dragSrc && this.placeholder.parentNode === container) {
            container.insertBefore(this.dragSrc, this.placeholder);
            this.placeholder.remove();
            this.updateOrder(container, orderSelector);
        }
    },
    
    setupContainerEvents(container, orderSelector) {
        container.addEventListener('dragover', e => {
            e.preventDefault();
            if (!container.querySelector('.placeholder')) {
                container.appendChild(this.placeholder);
            }
        });
        
        container.addEventListener('drop', e => this.handleDrop(e, container, orderSelector));
    },
    
    updateOrder(container, orderSelector) {
        if (!orderSelector) return;
        
        container.querySelectorAll(orderSelector).forEach((el, idx) => {
            el.textContent = idx + 1;
        });
    },
    
    updateTouchPosition(item, container, deltaY) {
        const items = Array.from(container.children);
        const itemHeight = item.offsetHeight;
        const currentIndex = items.indexOf(item);
        
        const newIndex = Math.floor(deltaY / itemHeight) + currentIndex;
        if (newIndex >= 0 && newIndex < items.length && newIndex !== currentIndex) {
            if (deltaY > 0) {
                container.insertBefore(item, items[newIndex + 1]);
            } else {
                container.insertBefore(item, items[newIndex]);
            }
        }
    },
    
    finalizeTouchMove(container, orderSelector, options) {
        this.updateOrder(container, orderSelector);
        if (options.onReorder) {
            try {
                options.onReorder(container);
            } catch (err) {
                console.error('Error in onReorder callback:', err);
                notifications?.error?.('Error al reordenar elementos');
            }
        }
    }
};
