// assets/dishes.js - Módulo para manejo de platos
// v2.0 - Rediseño completo para garantizar sincronización

// Estado global para tracking de operaciones
window.dishOperations = {
  lastOp: null,
  success: false,
  message: ''
};

(()=>{
  if(window.DishModule){ return; } // evitar doble carga

  // Asegurar confirmación interna disponible
if(!window.NotificationSystem){ window.NotificationSystem = {show:(m,t)=>alert(m), confirm:(m,o)=>confirm(m)}; }
// Ya no definimos un poly-fill custom, usamos confirm nativo como fallback

class DishModule{
    constructor(){
      this.init();
    }

    /**
     * Helper genérico para llamar al backend.
     * @param {string} action Acción en desktop.php
     * @param {object|FormData} payload Datos a enviar
     * @param {boolean} isForm Si true, payload es FormData
     */
    async api(action, payload={}, isForm=false){
      if(isForm){
        payload.append('accion', action);
        payload.append('csrf_token', CSRF_TOKEN);
      }
      const body = isForm ? payload : new URLSearchParams({...payload, accion: action, csrf_token: CSRF_TOKEN});
      const resp = await fetch('desktop.php',{method:'POST',body});
      const text = await resp.text();
      let json;
      try{ json = JSON.parse(text); }catch(err){ json = {ok:true}; }
      return json;
    }

    /* -------------------- Eliminación -------------------- */
    async deleteDish(btn){
      console.debug('[DishModule] deleteDish click');
      const li = btn.closest('.dish-item');
      if(!li) return;
      const ok = await NotificationSystem.confirm('¿Eliminar este plato?', 'warning');
      console.debug('[DishModule] confirm result', ok);
      if(!ok) return; // usuario canceló
      const res = await this.api('eliminar_plato', {plato_id: li.dataset.id});
      if(!res.ok){ NotificationSystem.show(res.msg||'Error al eliminar','error'); return; }
      NotificationSystem.show('Plato eliminado','success');
      sessionStorage.setItem('dishes_reload_success', 'Plato eliminado correctamente');
      window.location.reload(); // para recargar ordenamiento, etc.
    }

    /* -------------------- Cambio de imagen -------------------- */
    async changeDishImage(btn) {
      const li = btn.closest('.dish-item');
      if(!li) return;
      const dishId = li.dataset.id;
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.accept = 'image/*';
      fileInput.onchange = async () => {
        if(!fileInput.files[0]) return;
        const fd = new FormData();
        fd.append('plato_id', dishId);
        fd.append('imagen', fileInput.files[0]);
        const res = await this.api('cambiar_img_plato', fd, true);
        if(res.ok){
          // Actualizar imagen en UI con timestamp para evitar caché
          li.querySelector('img.dish-thumb').src = res.path + '?' + Date.now();
          NotificationSystem.show('Imagen actualizada','success');
          sessionStorage.setItem('dishes_reload_success', 'Imagen actualizada correctamente');
          window.location.reload();
        } else {
          NotificationSystem.show(res.msg || 'Error al subir imagen','error');
        }
      };
      fileInput.click();
    }

    /* -------------------- Alta de platos -------------------- */
    async handleDishAdd(form) {
      try {
        console.log('[DishModule] Iniciando alta de plato');
        // Verificar que tenemos los elementos requeridos antes de continuar
        if(!form) throw new Error('Formulario no encontrado');
        
        // Validaciones básicas para datos críticos
        const fd = new FormData(form);
        const nombre = (fd.get('nombre')||'').toString().trim();
        const descripcion = (fd.get('descripcion')||'').toString().trim();
        const imgInput = form.querySelector('input[type="file"]');
        const categoriaSelect = form.querySelector('select[name="categoria_id"]');
        let categoriaId = fd.get('categoria_id');
        
        // Verificar si hay una categoría recién creada en sessionStorage
        const lastCreatedCatId = sessionStorage.getItem('lastCreatedCategoryId');
        const lastCreatedCatName = sessionStorage.getItem('lastCreatedCategoryName');
        const lastCreatedTime = sessionStorage.getItem('lastCreatedCategoryTime');
        
        // Si existe una categoría recién creada y no han pasado más de 2 minutos, usarla
        if (lastCreatedCatId && lastCreatedCatName && lastCreatedTime) {
          const timeSinceCreation = Date.now() - new Date(lastCreatedTime).getTime();
          const minutesSinceCreation = timeSinceCreation / (1000 * 60);
          
          if (minutesSinceCreation < 2) {
            console.log(`[DishModule] Detectada categoría recién creada (${minutesSinceCreation.toFixed(1)} min): ${lastCreatedCatName} (ID: ${lastCreatedCatId})`);
            
            // Verificar si la categoría está seleccionada
            if (categoriaId !== lastCreatedCatId) {
              console.log(`[DishModule] La categoría seleccionada (${categoriaId}) no coincide con la recién creada (${lastCreatedCatId})`);
              
              // Si hay un select, buscar la opción para la categoría recién creada
              if (categoriaSelect) {
                const option = categoriaSelect.querySelector(`option[value="${lastCreatedCatId}"]`);
                if (option) {
                  console.log(`[DishModule] Auto-seleccionando la categoría recién creada: ${option.textContent}`);
                  categoriaSelect.value = lastCreatedCatId;
                  // Actualizar el FormData con el nuevo valor
                  fd.set('categoria_id', lastCreatedCatId);
                  categoriaId = lastCreatedCatId;
                } else {
                  console.warn(`[DishModule] No se encontró opción para la categoría recién creada (ID: ${lastCreatedCatId})`);
                }
              }
            } else {
              console.log(`[DishModule] La categoría recién creada ya está seleccionada (ID: ${categoriaId})`);
            }
          }
        }
        
        // Validaciones adicionales
        if(!nombre) throw new Error('El nombre del plato es requerido');
        if(!descripcion) throw new Error('La descripción es requerida');
        if(!imgInput || !imgInput.files.length) throw new Error('Imagen requerida');
        if(!categoriaId) throw new Error('Categoría no especificada');
        
        // Deshabilitar el botón para evitar doble submit
        const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
        const btnText = btn ? btn.textContent : 'Guardar';
        if(btn) {
          btn.disabled = true;
          btn.textContent = 'Guardando…';
        }
        
        // Crear marcador para seguimiento post-recarga
        const trackingId = 'dish_' + Date.now();
        sessionStorage.setItem('lastAddedDishTime', new Date().toISOString());
        sessionStorage.setItem('lastAddedDishCatId', categoriaId);
        
        // Notificar inicio del proceso
        NotificationSystem.show(`Guardando plato en su categoría`);
        
        console.log(`[DishModule] Enviando datos al servidor para categoría ID: ${categoriaId}`);
        
        // Enviar datos directamente como estaban en la versión original (sin manipular)
        const res = await this.api('guardar_plato', fd, true);
        
        if(!res.ok) {
          throw new Error(res.msg || 'Error al guardar el plato');
        }
        
        // Éxito - resetear formulario
        form.reset();
        
        // Guardar datos de éxito para post-recarga
        sessionStorage.setItem('dishes_reload_success', `Plato guardado correctamente en la categoría ${categoriaId}`);
        sessionStorage.setItem('lastAddedDishId', res.id || '');

        // IMPORTANTE: Marcar operación para identificación post-recarga
        sessionStorage.setItem('postReloadAction', JSON.stringify({
          type: 'dishAdded',
          id: res.id,
          categoryId: categoriaId,
          name: nombre,
          timestamp: Date.now()
        }));

        // Limpiar sessionStorage de categorías recién creadas para evitar confusión en siguientes altas
        sessionStorage.removeItem('lastCreatedCategoryId');
        sessionStorage.removeItem('lastCreatedCategoryName');
        sessionStorage.removeItem('lastCreatedCategoryTime');

        console.log('[DishModule] Plato guardado con éxito, recargando página...');
        
        // Forzar recarga total en index.php para evitar HTML cacheado
        sessionStorage.setItem('force_reload_index','1');
        window.location.reload();
        
      } catch(err) {
        console.error('[DishModule] Error al guardar plato:', err);
        NotificationSystem.show(err.message || 'Error al guardar', 'error');
        
        // Re-habilitar botón
        const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
        if(btn) {
          btn.disabled = false;
          btn.textContent = btnText || 'Guardar';
        }
      }
    }

    /* -------------------- Guardado masivo -------------------- */
    async saveDishes(btnDishSave) {
      if(!btnDishSave) return;
      btnDishSave.disabled = true;
      btnDishSave.textContent = 'Guardando...';
      
      try {
        const ids=[]; const names=[]; const prices=[]; const descs=[]; const ordenes={};
        const dishLists = document.querySelectorAll('.dish-list');
        
        dishLists.forEach(list=>{
          const catId = list.dataset.cat;
          const order = [];
          list.querySelectorAll('.dish-item').forEach(li=>{
            const id = li.dataset.id;
            ids.push(id);
            names.push(li.querySelector('.dish-name').value.trim());
            descs.push(li.querySelector('.dish-desc').value.trim());
            prices.push(li.querySelector('.dish-price').value);
            order.push(id);
          });
          ordenes[catId] = order.join(',');
        });
        
        const fd = new FormData();
        ids.forEach((id,i)=>{
          fd.append('plato_id[]', id);
          fd.append('nombre[]', names[i]);
          fd.append('descripcion[]', descs[i]);
          fd.append('precio[]', prices[i]);
        });
        
        for(const cid in ordenes){
          fd.append(`orden_platos[${cid}]`, ordenes[cid]);
        }
        
        const res = await this.api('guardar_platos', fd, true);
        NotificationSystem.show(res.ok ? 'Platos guardados' : (res.msg || 'Error al guardar'), res.ok ? 'success' : 'error');
        sessionStorage.setItem('dishes_reload_success', 'Cambios guardados correctamente');
        window.location.reload();
      } catch(err) {
        console.error(err);
        NotificationSystem.show('Error al guardar los platos', 'error');
      } finally {
        btnDishSave.disabled = false;
        btnDishSave.textContent = 'Guardar cambios';
      }
    }

    /* -------------------- Drag & Drop -------------------- */
    setupDragDrop() {
      const dishLists = document.querySelectorAll('.dish-list');
      if(!dishLists.length) return;
      
      let draggedItem = null;
      
      dishLists.forEach(list => {
        list.querySelectorAll('.dish-item').forEach(item => {
          // Evento de arrastre iniciado
          item.addEventListener('dragstart', function(e) {
            draggedItem = this;
            setTimeout(() => this.classList.add('dragging'), 0);
          });
          
          // Eventos de finalización del arrastre
          item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
          });
          
          // Evento para el agarre manual
          const grabber = item.querySelector('.grab');
          if(grabber) {
            grabber.addEventListener('mousedown', (e) => {
              // Solo inicia drag si se hace click en el grabber
              item.setAttribute('draggable', 'true');
            });
            grabber.addEventListener('mouseup', (e) => {
              item.setAttribute('draggable', 'false');
            });
          }
        });
        
        // Permitir soltar elementos
        list.addEventListener('dragover', function(e) {
          e.preventDefault();
          const afterElement = getDragAfterElement(list, e.clientY);
          if (draggedItem) {
            if (!afterElement) {
              list.appendChild(draggedItem);
            } else {
              list.insertBefore(draggedItem, afterElement);
            }
          }
        });
      });
      
      // Función auxiliar para determinar después de qué elemento insertar
      function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.dish-item:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
          const box = child.getBoundingClientRect();
          const offset = y - box.top - box.height / 2;
          
          if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
          } else {
            return closest;
          }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
      }
    }

    /* -------------------- Bindings -------------------- */
    bindEvents(){
      // 1. Delegación para botones de eliminación
      document.addEventListener('click', e=>{
        const path = e.composedPath ? e.composedPath() : (e.path||[]);
        
        // Botón eliminar
        const delBtn = path.find(el => el && el.classList && el.classList.contains('del-dish')) || e.target.closest?.('.del-dish');
        console.debug('[DishModule] global click', delBtn||e.target, '-> found', !!delBtn);
        if(delBtn){
          e.preventDefault();
          this.deleteDish(delBtn);
          return;
        }
        
        // Botón cambiar imagen
        const imgBtn = path.find(el => el && el.classList && el.classList.contains('btn-img')) || e.target.closest?.('.btn-img');
        if(imgBtn){
          e.preventDefault();
          this.changeDishImage(imgBtn);
          return;
        }
        
        // Botón guardar cambios
        const saveBtn = path.find(el => el && el.id === 'btn-dish-save') || e.target.closest?.('#btn-dish-save');
        if(saveBtn){
          e.preventDefault();
          this.saveDishes(saveBtn);
          return;
        }
      });
      
      // 2. Formularios de alta de platos (delegado para soportar categorías nuevas)
      document.addEventListener('submit', e => {
        if(e.target.classList.contains('form-dish-add')){
          e.preventDefault();
          this.handleDishAdd(e.target);
        }
      });
      
      // 3. Configurar drag & drop para platos existentes
      this.setupDragDrop();
    }

    /* -------------------- Inicialización -------------------- */
    init(){
      this.bindEvents();
      console.debug('[DishModule] inicializado');
    }
  }

  // Instanciar módulo globalmente
  window.DishModule = new DishModule();
})();
