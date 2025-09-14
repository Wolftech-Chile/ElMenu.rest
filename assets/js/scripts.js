/**
 * Sistema de pedidos del menú digital
 * Maneja el resumen del pedido, propinas, persistencia local y envío por WhatsApp
 */

const MenuDigital = {
    // Configuración
    config: {
        propinaPorcentaje: 0.10,
        whatsappNumero: '56945787874',
        formatoMoneda: 'es-CL'
    },

    // Cache de elementos DOM
    elements: {
        inputs: () => document.querySelectorAll('.plato input[type=number]'),
        total: () => document.getElementById('resumen-total'),
        propina: () => document.getElementById('resumen-propina'),
        final: () => document.getElementById('resumen-final')
    },

    // Inicialización
    init() {
        this.cargarPedidoGuardado();
        this.inicializarEventos();
        this.calcularResumen();
    },

    // Cálculo del resumen
    calcularResumen() {
        // Actualizar listado y total en el resumen visual
        renderResumen();
        const total = this.calcularTotal();
        let propina = Math.round(total * 0.10);
        if(document.getElementById('resumen-total'))
            document.getElementById('resumen-total').textContent = '$' + total.toLocaleString('es-CL');
        if(document.getElementById('resumen-propina'))
            document.getElementById('resumen-propina').textContent = '$' + propina.toLocaleString('es-CL');
        if(document.getElementById('resumen-final'))
            document.getElementById('resumen-final').textContent = '$' + (total+propina).toLocaleString('es-CL');
    },
    calcularTotal() {
        return Array.from(this.elements.inputs()).reduce((total, input) => {
            return total + (parseInt(input.value) || 0) * (parseInt(input.dataset.precio) || 0);
        }, 0);
    },

    actualizarResumen(total) {
        const propina = Math.round(total * this.config.propinaPorcentaje);
        const final = total + propina;

        this.actualizarElemento(this.elements.total(), total);
        this.actualizarElemento(this.elements.propina(), propina);
        this.actualizarElemento(this.elements.final(), final);

        return { total, propina, final };
    },

    actualizarElemento(elemento, valor) {
        if (elemento) {
            elemento.textContent = this.formatearMoneda(valor);
        }
    },

    formatearMoneda(valor) {
        return '$' + valor.toLocaleString(this.config.formatoMoneda);
    },

    // Gestión del pedido
    limpiarPedido() {
        // Reinicia todos los inputs y storage
        document.querySelectorAll('.cantidad-num').forEach(el=>{
            el.value = 0;
            this.guardarEnLocal(el.name, 0);
        });
        renderResumen();
        this.elements.inputs().forEach(input => {
            input.value = 0;
            this.guardarEnLocal(input.name, 0);
        });
        this.calcularResumen();
    },

    // Integración con WhatsApp
    enviarWhatsApp() {
        const pedido = this.obtenerResumenPedido();
        if (!pedido.items.length) {
            notifications?.warning?.('Agregue items al pedido primero');
            return;
        }

        const mensaje = this.construirMensajeWhatsApp(pedido);
        window.open(`https://wa.me/${this.config.whatsappNumero}?text=${mensaje}`, '_blank');
    },

    obtenerResumenPedido() {
        const items = [];
        let total = 0;

        this.elements.inputs().forEach(input => {
            const cantidad = parseInt(input.value) || 0;
            if (cantidad > 0) {
                const nombre = input.closest('.plato').querySelector('h3').textContent;
                const precio = parseInt(input.dataset.precio) || 0;
                items.push({
                    cantidad,
                    nombre,
                    precio,
                    subtotal: cantidad * precio
                });
                total += cantidad * precio;
            }
        });

        return { items, total };
    },

    construirMensajeWhatsApp(pedido) {
        const items = pedido.items.map(item => `${item.cantidad}x ${item.nombre}`).join(', ');
        const total = this.elements.final()?.textContent || '';
        return encodeURIComponent(`Hola! Quiero pedir: ${items}. Total: ${total}`);
    },

    // Persistencia local
    cargarPedidoGuardado() {
        this.elements.inputs().forEach(input => {
            const key = this.getStorageKey(input.name);
            input.value = localStorage.getItem(key) || 0;
        });
    },

    guardarEnLocal(nombre, valor) {
        const key = this.getStorageKey(nombre);
        localStorage.setItem(key, valor);
    },

    getStorageKey(nombre) {
        return `plato_${nombre}`;
    },

    // Event Listeners
    inicializarEventos() {
        this.elements.inputs().forEach(input => {
            input.addEventListener('input', () => {
                this.guardarEnLocal(input.name, input.value);
                this.calcularResumen();
            });
        });

        // Debounce para actualización en tiempo real
        let timeout;
        document.addEventListener('input', (e) => {
            if (e.target.matches('.plato input[type=number]')) {
                clearTimeout(timeout);
                timeout = setTimeout(() => this.calcularResumen(), 300);
            }
        });
    }
};

// Format currency in CLP
function formatCLP(num) {
  return num.toLocaleString('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 });
}

// Render order summary for both desktop and mobile
function renderResumen() {
  const pedido = MenuDigital.obtenerResumenPedido();
  // Desktop/tablet resumen
  const contDesktop = document.querySelector('#pedido-resumen-desktop');
  if (contDesktop && window.innerWidth >= 900) {
    let html = '<h4>Resumen de tu pedido</h4>';
    if (pedido.items.length === 0) {
      html += '<div style="color:#888;margin:1em 0;">Tu pedido está vacío</div>';
    } else {
      html += '<ul>';
      pedido.items.forEach(item => {
        const subtotal = item.subtotal ?? (item.cantidad * (item.precio || 0));
        html += `<li style='display:flex;justify-content:space-between;margin-bottom:0.7em;'><span>${item.nombre} × ${item.cantidad}</span><span>${formatCLP(subtotal)}</span></li>`;
      });
      html += `</ul><div class='total-row' style='display:flex;justify-content:space-between;font-weight:bold;font-size:1.1em;margin:1em 0;'>
        <span>Total</span>
        <span>${formatCLP(MenuDigital.calcularTotal())}</span>
      </div>
      <button class='btn-limpiar' onclick='limpiarPedido()'>Limpiar pedido</button>`;
    }
    contDesktop.innerHTML = html;
  }
  // Mobile resumen (bottom sheet)
  const contSheet = document.querySelector('#pedido-resumen-sheet');
  if (contSheet && window.innerWidth < 900) {
    let html = '';
    if (pedido.items.length === 0) {
      html = '<div style="text-align:center;color:#888;margin-top:2em;">Tu pedido está vacío</div>';
    } else {
      html = '<ul>';
      pedido.items.forEach(item => {
        const subtotal = item.subtotal ?? (item.cantidad * (item.precio || 0));
        html += `<li style='display:flex;justify-content:space-between;margin-bottom:0.7em;'><span>${item.nombre} × ${item.cantidad}</span><span>${formatCLP(subtotal)}</span></li>`;
      });
      html += `</ul><div class='total-row' style='display:flex;justify-content:space-between;font-weight:bold;font-size:1.1em;margin:1em 0;'>
        <span>Total</span>
        <span>${formatCLP(MenuDigital.calcularTotal())}</span>
      </div>
      <button class='btn-limpiar' onclick='limpiarPedido()'>Limpiar pedido</button>`;
    }
    contSheet.innerHTML = html;
  }
  // Actualizar barra inferior
  const barCount = document.getElementById('cart-bar-count');
  const barTotal = document.getElementById('cart-bar-total');
  if (barCount) barCount.textContent = pedido.items.length;
  if (barTotal) barTotal.textContent = formatCLP(MenuDigital.calcularTotal());
}
// Re-render resumen al cambiar tamaño de pantalla
window.addEventListener('resize', renderResumen);

// Mostrar/ocultar bottom sheet
function openBottomSheet() {
  const sheet = document.getElementById('pedido-bottom-sheet');
  sheet?.classList.add('open');
  // Si es móvil o tablet, hacer scroll para que el bottom sheet sea visible
  if (window.innerWidth < 900 && sheet) {
    setTimeout(() => {
      sheet.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }, 80);
  }
}
function closeBottomSheet() {
  document.getElementById('pedido-bottom-sheet')?.classList.remove('open');
}

// Cerrar bottom sheet al hacer click fuera o en el botón
window.addEventListener('DOMContentLoaded', function() {
  const btnBar = document.getElementById('cart-bar-btn');
  const sheet = document.getElementById('pedido-bottom-sheet');
  const closeBtn = document.getElementById('close-bottom-sheet');
  if (btnBar) btnBar.addEventListener('click', openBottomSheet);
  if (closeBtn) closeBtn.addEventListener('click', closeBottomSheet);
  // Cerrar si se hace click fuera del sheet
  sheet?.addEventListener('click', function(e) {
    if (e.target === sheet) closeBottomSheet();
  });
});

// Clear order
function limpiarPedido() {
  // Reset all quantity inputs to 0
  document.querySelectorAll('.cantidad-num').forEach(el => {
    el.value = 0;
    // Trigger input event to update the order
    el.dispatchEvent(new Event('input'));
  });
  
  // Clear localStorage
  localStorage.removeItem('pedido');
  
  // Re-render the summary
  renderResumen();
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // --- Limpiar pedido al cargar la página ---
  document.querySelectorAll('.cantidad-num').forEach(el => {
    el.value = 0;
    // Si usas persistencia, actualiza también localStorage
    if (typeof MenuDigital !== 'undefined' && MenuDigital.guardarEnLocal) {
      MenuDigital.guardarEnLocal(el.name, 0);
    }
  });
  // Limpiar el resumen visual
  if (typeof renderResumen === 'function') renderResumen();
  if (typeof MenuDigital !== 'undefined' && MenuDigital.calcularResumen) {
    MenuDigital.calcularResumen();
  }

  // Initialize MenuDigital if it exists
  if (typeof MenuDigital !== 'undefined') {
    MenuDigital.init();
  }
  
  // Initial render
  renderResumen();
  
  // Update summary when quantities change
  document.querySelectorAll('.cantidad-num').forEach(el => {
    el.addEventListener('input', renderResumen);
  });
});

// Make functions available globally
window.renderResumen = renderResumen;
window.limpiarPedido = limpiarPedido;

// Lazy load para imágenes (opcional, si quieres soporte extra)
document.addEventListener('DOMContentLoaded', function() {
    if ('loading' in HTMLImageElement.prototype) return; // Nativo
    var imgs = document.querySelectorAll('img[loading="lazy"]');
    imgs.forEach(function(img) {
        if (img.dataset.src) img.src = img.dataset.src;
    });
});

// Puedes agregar aquí más funciones para drag & drop, cache, etc.

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar drag & drop para categorías
    const categoriasContainer = document.querySelector('.categorias-grid');
    if (categoriasContainer) {
        dragDropHelpers.initDragDrop(categoriasContainer, '.categoria-item', '.cat-orden', {
            onReorder: function(container) {
                const items = Array.from(container.children).filter(x => x.classList.contains('categoria-item'));
                const ordenData = items.map((el, idx) => ({
                    id: el.dataset.id,
                    orden: idx + 1
                }));
                
                ajaxHelpers.sendFormData('desktop.php', {
                    accion: 'actualizar_orden_categorias',
                    orden: JSON.stringify(ordenData)
                }, true);
            }
        });
    }
    
    // Inicializar drag & drop para platos
    document.querySelectorAll('.platos-lista').forEach(function(lista) {
        dragDropHelpers.initDragDrop(lista, '.plato-item', '.plato-orden', {
            minHeight: '54px',
            onReorder: function(container) {
                const items = Array.from(container.children).filter(x => x.classList.contains('plato-item'));
                const ordenData = items.map((el, idx) => ({
                    id: el.dataset.id,
                    orden: idx + 1
                }));
                
                ajaxHelpers.sendFormData('desktop.php', {
                    accion: 'actualizar_orden_platos',
                    categoria_id: container.dataset.cat,
                    orden: JSON.stringify(ordenData)
                }, true);
            }
        });
    });
    
    // Eliminar plato
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-eliminar-plato');
        if (btn) {
            e.preventDefault();
            const platoId = btn.dataset.platoId;
            
            ajaxHelpers.confirm('¿Estás seguro de eliminar este plato?')
                .then(result => {
                    if (result.isConfirmed) {
                        ajaxHelpers.sendFormData('desktop.php', {
                            accion: 'eliminar_plato',
                            plato_id: platoId
                        })
                        .then(data => {
                            if (data.ok) {
                                btn.closest('.plato-item').remove();
                                ajaxHelpers.showMessage('Plato eliminado correctamente');
                            } else {
                                ajaxHelpers.showMessage(data.msg || 'Error al eliminar el plato', true);
                            }
                        });
                    }
                });
        }
    });
});
