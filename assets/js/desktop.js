// assets/desktop.js – Navegación SPA y helpers del nuevo panel

// --- Funciones de utilidad ---
function showNotification(message, type = 'info') {
  // Crear el contenedor de notificaciones si no existe
  let container = document.querySelector('.notification-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'notification-container';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
  }

  // Crear la notificación
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  notification.style.padding = '15px 20px';
  notification.style.marginBottom = '10px';
  notification.style.borderRadius = '4px';
  notification.style.color = '#fff';
  notification.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
  notification.style.animation = 'slideIn 0.3s ease-out';
  
  // Estilos según el tipo de notificación
  if (type === 'success') {
    notification.style.backgroundColor = '#4CAF50';
  } else if (type === 'error') {
    notification.style.backgroundColor = '#f44336';
  } else if (type === 'warning') {
    notification.style.backgroundColor = '#ff9800';
  } else {
    notification.style.backgroundColor = '#2196F3';
  }
  
  notification.textContent = message;
  container.appendChild(notification);
  
  // Eliminar la notificación después de 5 segundos
  setTimeout(() => {
    notification.style.animation = 'fadeOut 0.5s ease-out';
    setTimeout(() => {
      notification.remove();
    }, 500);
  }, 5000);
}

// Agregar estilos CSS para las animaciones
const style = document.createElement('style');
style.textContent = `
  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
  }
`;
document.head.appendChild(style);

// --- Confirmación custom para importación de base ---
// Función para manejar el envío de formularios de usuario
function setupUserForms() {
  // Formulario de creación de usuario
  const userForm = document.querySelector('#form-crear-usuario');
  if (userForm) {
    userForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      console.log('[User Form] Iniciando envío de formulario de usuario');
      
      // Mostrar indicador de carga
      const submitButton = userForm.querySelector('button[type="submit"]');
      const originalButtonText = submitButton.innerHTML;
      submitButton.disabled = true;
      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando usuario...';
      
      try {
        const formData = new FormData(userForm);
        console.log('[User Form] Datos del formulario:', Object.fromEntries(formData.entries()));
        
        // Validar contraseña
        const password = formData.get('clave');
        if (password.length < 8) {
          throw new Error('La contraseña debe tener al menos 8 caracteres');
        }
        
        const response = await fetch('desktop.php', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        
        const responseData = await response.json().catch(() => ({}));
        
        console.log('[User Form] Respuesta del servidor:', {
          status: response.status,
          ok: response.ok,
          data: responseData
        });
        
        if (response.ok) {
          // Mostrar mensaje de éxito
          showNotification('Usuario creado exitosamente', 'success');
          
          // Recargar después de un breve retraso
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          const errorMessage = responseData.error || 'Error al procesar la solicitud';
          console.error('[User Form] Error en la respuesta del servidor:', errorMessage);
          showNotification(errorMessage, 'error');
        }
      } catch (error) {
        console.error('[User Form] Error al enviar el formulario:', error);
        showNotification(error.message || 'Error inesperado. Por favor, revise la consola para más detalles.', 'error');
      } finally {
        // Restaurar el botón
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
      }
    });
  }

  // Manejar formularios de usuario (eliminar, cambiar contraseña)
  document.addEventListener('submit', async function(e) {
    const form = e.target.closest('form.form-eliminar-usuario, form.form-cambiar-pass');
    if (!form) return;
    
    e.preventDefault();
    
    // Mostrar confirmación personalizada
    const confirmMessage = form.dataset.confirm || '¿Está seguro de realizar esta acción?';
    if (!confirm(confirmMessage)) {
      console.log('[User Form] Acción cancelada por el usuario');
      return;
    }
    
    console.log('[User Form] Procesando formulario de usuario:', form.action);
    
    try {
      const formData = new FormData(form);
      formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
      
      console.log('[User Form] Datos del formulario:', Object.fromEntries(formData.entries()));
      
      const response = await fetch(form.action || 'desktop.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      
      console.log('[User Form] Respuesta del servidor:', {
        status: response.status,
        ok: response.ok
      });
      
      if (response.ok) {
        window.location.reload();
      } else {
        const error = await response.text();
        console.error('[User Form] Error en la respuesta:', error);
        alert('Error al procesar la solicitud. Por favor, intente nuevamente.');
      }
    } catch (error) {
      console.error('[User Form] Error al enviar el formulario:', error);
      alert('Error inesperado. Por favor, revise la consola para más detalles.');
    }
  });
}

// Función para inicializar el toggle de contraseña
function initPasswordToggle() {
  document.addEventListener('click', function(e) {
    const toggleBtn = e.target.closest('.toggle-password');
    if (!toggleBtn) return;
    
    const input = toggleBtn.previousElementSibling;
    if (input && input.type === 'password') {
      input.type = 'text';
      toggleBtn.classList.add('visible');
    } else if (input) {
      input.type = 'password';
      toggleBtn.classList.remove('visible');
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Inicializar formularios de usuario
  setupUserForms();
  
  // Inicializar el toggle de contraseña
  initPasswordToggle();
  
  // Manejar el botón de importar base de datos
  var btnImportar = document.getElementById('btn-importar-db');
  if(btnImportar){
    btnImportar.addEventListener('click', async function(){
      var form = btnImportar.closest('form');
      var confirmFn = (window.NotificationSystem && NotificationSystem.confirm) ? NotificationSystem.confirm : (NotificationSystem.ask ? NotificationSystem.ask : null);
      if(!confirmFn){
        if(confirm('ADVERTENCIA: Esto reemplazará la base de datos completa por el archivo seleccionado. ¿Desea continuar?')){
          form.submit();
        }
        return;
      }
      var ok = await confirmFn('ADVERTENCIA: Esto reemplazará la base de datos completa por el archivo seleccionado. ¿Desea continuar?', {type:'warning', confirmText:'Sí, importar', cancelText:'Cancelar'});
      if(ok){
        var fileInput = form.querySelector('input[type="file"][name="db_file"]');
        if(!fileInput || !fileInput.files.length){
          NotificationSystem.show('Seleccione un archivo .db o .sqlite antes de importar','error');
          return;
        }
        form.submit();
      }
    });
  }
});

(function(){
  // IMPORTANTE: Código de inicialización para acciones post-recarga
  function handlePostReloadActions() {
    try {
      // DIAGNÓSTICO: Verificación extendida para depurar problema persistente
      console.log('%c=== DIAGNÓSTICO EXTENDIDO ===', 'background:#ff0;color:#000;padding:5px;font-weight:bold');
      
      // Verificar todas las categorías en el DOM
      const categorias = document.querySelectorAll('.cat-item');
      console.log(`[DIAGNÓSTICO] Categorías encontradas en el DOM: ${categorias.length}`);
      
      // Mostrar cada categoría con sus IDs
      categorias.forEach((cat, index) => {
        const id = cat.dataset.id;
        const nombre = cat.querySelector('.cat-name')?.textContent || 'Sin nombre';
        const platos = cat.querySelectorAll('.dish-item')?.length || 0;
        console.log(`[DIAGNÓSTICO] Categoría #${index+1}: ID=${id}, Nombre="${nombre}", Platos=${platos}`);
        
        // Verificar platos en esta categoría
        const platosItems = cat.querySelectorAll('.dish-item');
        if (platosItems.length) {
          console.log(`  Platos en categoría ID=${id}:`);
          platosItems.forEach((plato, idx) => {
            const platoId = plato.dataset.id;
            const platoNombre = plato.querySelector('.dish-name')?.textContent || 'Sin nombre';
            console.log(`  #${idx+1}: ID=${platoId}, Nombre="${platoNombre}"`);
          });
        }
      });
      
      // Verificar selectores de categorías
      const selectores = document.querySelectorAll('select[name="categoria_id"]');
      console.log(`[DIAGNÓSTICO] Selectores de categorías: ${selectores.length}`);
      selectores.forEach((sel, idx) => {
        console.log(`[DIAGNÓSTICO] Selector #${idx+1}: Valor actual=${sel.value}, Opciones=${sel.options.length}`);
        Array.from(sel.options).forEach(opt => 
          console.log(`  Opción: value="${opt.value}", text="${opt.text}"`))
      });
      
      // Comprobar si hay una acción pendiente en sessionStorage
      const actionJson = sessionStorage.getItem('postReloadAction');
      if (!actionJson) {
        console.log('[DIAGNÓSTICO] No hay acción pendiente en sessionStorage');
        return;
      }
      
      // Parsear la acción
      const action = JSON.parse(actionJson);
      console.log('[PostReload] Procesando acción:', action);
      
      // Ejecutar acción según su tipo
      if (action.type === 'categoryCreated') {
        // Mostrar notificación de categoría creada
        setTimeout(() => {
          NotificationSystem.show(
            `Categoría "${action.name}" creada correctamente`, 
            'success'
          );
          
          // Verificar que la categoría esté en el DOM
          const catElement = document.querySelector(`.cat-item[data-id="${action.id}"]`);
          if (catElement) {
            console.log('[PostReload] Categoría encontrada en DOM:', catElement);
            // Resaltar la categoría
            catElement.style.backgroundColor = '#f0fff4';
            setTimeout(() => {
              catElement.style.transition = 'background-color 1s';
              catElement.style.backgroundColor = '';
            }, 2000);
            
            // Auto-seleccionar la nueva categoría en el selector de categorías
            const catSelectors = document.querySelectorAll('select[name="categoria_id"]');
            catSelectors.forEach(select => {
              const option = select.querySelector(`option[value="${action.id}"]`);
              if (option) {
                select.value = action.id;
                console.log('[PostReload] Categoría auto-seleccionada en selector:', select);
              }
            });
          } else {
            console.warn('[PostReload] Categoría NO encontrada en DOM. ID:', action.id);
          }
        }, 500);
      } else if (action.type === 'dishAdded') {
        // Mostrar notificación de plato creado
        setTimeout(() => {
          NotificationSystem.show(
            `Plato "${action.name}" guardado correctamente en su categoría`, 
            'success'
          );
          
          // Intentar encontrar y resaltar el plato en el DOM
          // Necesitamos buscar en la sección de platos de la categoría correcta
          const dishContainer = document.querySelector(`.cat-item[data-id="${action.categoryId}"] .dish-list`);
          
          if (dishContainer) {
            console.log('[PostReload] Contenedor de platos encontrado para categoría ID:', action.categoryId);
            
            // Buscar el nuevo plato por su ID
            let dishElement;
            if (action.id) {
              dishElement = dishContainer.querySelector(`.dish-item[data-id="${action.id}"]`);
            }
            
            // Si no encontramos el plato por ID (quizás el backend no devuelve el ID), buscamos por nombre
            if (!dishElement && action.name) {
              // Buscar platos que contengan el nombre (puede ser aproximado)
              const allDishItems = dishContainer.querySelectorAll('.dish-item');
              
              // Convertimos a array para poder usar Array.find
              const dishElementsArray = Array.from(allDishItems);
              dishElement = dishElementsArray.find(item => {
                const nameElement = item.querySelector('.dish-name');
                return nameElement && nameElement.textContent.includes(action.name);
              });
            }
            
            if (dishElement) {
              console.log('[PostReload] Plato encontrado en DOM:', dishElement);
              // Resaltar el plato
              dishElement.style.backgroundColor = '#f0fff4';
              dishElement.style.boxShadow = '0 0 8px rgba(0, 128, 0, 0.5)';
              
              // Scroll al plato (si está fuera de vista)
              dishElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
              
              // Quitar resaltado después de un tiempo
              setTimeout(() => {
                dishElement.style.transition = 'all 1s';
                dishElement.style.backgroundColor = '';
                dishElement.style.boxShadow = '';
              }, 3000);
            } else {
              console.warn('[PostReload] Plato NO encontrado en DOM. Datos:', action);
            }
          } else {
            console.warn('[PostReload] Contenedor de platos NO encontrado para categoría ID:', action.categoryId);
          }
          
          // Limpiar el sessionStorage para evitar confusiones en futuras operaciones
          sessionStorage.removeItem('lastAddedDishId');
          sessionStorage.removeItem('lastAddedDishCatId');
          sessionStorage.removeItem('lastAddedDishTime');
        }, 700);
      }
      
      // Limpiar para evitar ejecuciones repetidas
      sessionStorage.removeItem('postReloadAction');
    } catch (error) {
      console.error('[PostReload] Error al procesar acciones post-recarga:', error);
    }
  }
  
  /**
   * Función radical para sincronizar el DOM con el backend
   * Esta función hace un refresh completo de platos y categorías desde el backend
   */
  async function sincronizarDesdeBE() {
    try {
      console.log('%c[SINCRONIZAR] Iniciando sincronización completa con backend', 'background:blue;color:white;padding:3px');
      
      // 1. Hacer petición al backend para obtener datos actualizados
      const response = await fetch('endpoint.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `accion=obtener_todas_categorias&csrf_token=${CSRF_TOKEN}`
      });
      
      const result = await response.json();
      console.log('[SINCRONIZAR] Datos recibidos:', result);
      
      if (!result.ok || !result.categorias || !Array.isArray(result.categorias)) {
        throw new Error('Error al obtener datos del servidor o formato inválido');
      }
      
      // 2. Actualizar selectores de categorías (para que muestren las opciones correctas)
      const selectores = document.querySelectorAll('select[name="categoria_id"]');
      if (selectores.length) {
        console.log(`[SINCRONIZAR] Actualizando ${selectores.length} selectores de categorías`);
        
        selectores.forEach(select => {
          // Preservar valor seleccionado actual
          const valorActual = select.value;
          
          // Vaciar opciones excepto la primera (si es placeholder)
          while (select.options.length > 1 && select.options[0].value === '') {
            select.remove(1);
          }
          // O vaciar completamente si no hay placeholder
          while (select.options.length > 0 && select.options[0].value !== '') {
            select.remove(0);
          }
          
          // Añadir opciones desde datos del servidor
          result.categorias.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.nombre;
            select.appendChild(option);
            
            // Si este valor estaba seleccionado antes, volver a seleccionarlo
            if (valorActual === cat.id) {
              select.value = valorActual;
            }
          });
          
          console.log(`[SINCRONIZAR] Selector actualizado con ${result.categorias.length} categorías`);
        });
      }
      
      // Recuperar cualquier valor de categoría recién creada en sessionStorage
      const lastCreatedCatId = sessionStorage.getItem('lastCreatedCategoryId');
      if (lastCreatedCatId) {
        console.log(`[SINCRONIZAR] Encontrada categoría recién creada ID: ${lastCreatedCatId}`);
        
        // Aplicar selección en todos los selectores si corresponde
        selectores.forEach(select => {
          const option = select.querySelector(`option[value="${lastCreatedCatId}"]`);
          if (option) {
            console.log(`[SINCRONIZAR] Auto-seleccionando categoría recién creada en selector`);
            select.value = lastCreatedCatId;
          }
        });
      }
      
      // 3. SOLUCIÓN RADICAL: Reconstrucción completa de contenedores de platos
      // Esta solución fuerza la reconstrucción de los contenedores de platos basándose en datos del backend
      try {
        // 3.1 Obtener datos de platos por categoría directamente desde el backend
        console.log('%c[RECONSTRUCCIÓN] Iniciando reconstrucción completa de platos', 'background:red;color:white;padding:3px');
        
        // Verificar si hay alguna acción post-recarga relacionada con platos
        const actionJson = sessionStorage.getItem('postReloadAction');
        if (actionJson) {
          const action = JSON.parse(actionJson);
          
          // Si se agregó un plato, asegurarnos de que se muestra en la categoría correcta
          if (action.type === 'dishAdded') {
            console.log(`[RECONSTRUCCIÓN] Detectada adición de plato ID=${action.id} en categoría ID=${action.categoryId}`);
            
            // Verificar si la categoría donde se agregó el plato existe en el DOM
            const categoriaContainer = document.querySelector(`.cat-item[data-id="${action.categoryId}"]`);
            if (!categoriaContainer) {
              console.error(`[RECONSTRUCCIÓN] No se encontró el contenedor de la categoría ID=${action.categoryId}`);
              return; // No podemos continuar sin el contenedor
            }
            
            // Buscar platos en esta categoría
            const platosContainer = categoriaContainer.querySelector('.dish-list');
            if (!platosContainer) {
              console.error(`[RECONSTRUCCIÓN] No se encontró el contenedor de platos en categoría ID=${action.categoryId}`);
              return;
            }
            
            // Buscar si ya existe este plato en el DOM
            const platoExistente = platosContainer.querySelector(`.dish-item[data-id="${action.id}"]`);
            if (platoExistente) {
              console.log(`[RECONSTRUCCIÓN] Plato ID=${action.id} ya existe en el DOM, no es necesario reconstruir`);
              // Destacar el plato existente
              platoExistente.classList.add('highlight');
              setTimeout(() => platoExistente.classList.remove('highlight'), 3000);
              return;
            }
            
            // FORZAR RECONSTRUCCIÓN: Obtener nuevamente todos los platos de esta categoría
            console.log(`[RECONSTRUCCIÓN] Solicitando platos para categoría ID=${action.categoryId}`);
            
            fetch('endpoint.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `accion=obtener_platos_categoria&categoria_id=${action.categoryId}&csrf_token=${CSRF_TOKEN}`
            })
            .then(response => response.json())
            .then(data => {
              if (!data.ok || !Array.isArray(data.platos)) {
                throw new Error('Formato de respuesta inválido');
              }
              
              console.log(`[RECONSTRUCCIÓN] Recibidos ${data.platos.length} platos para categoría ID=${action.categoryId}`);
              
              // Vaciar completamente el contenedor actual
              while (platosContainer.firstChild) {
                platosContainer.removeChild(platosContainer.firstChild);
              }
              
              // Reconstruir todos los platos
              data.platos.forEach(plato => {
                // Recrear estructura HTML del plato según el formato esperado
                const platoElement = document.createElement('li');
                platoElement.className = 'dish-item';
                platoElement.dataset.id = plato.id;
                
                // Resaltar visualmente el plato que acabamos de agregar
                if (plato.id === action.id) {
                  platoElement.className += ' highlight';
                  setTimeout(() => platoElement.classList.remove('highlight'), 3000);
                }
                
                // Estructura interna del plato (mantener consistente con resto del código)
                platoElement.innerHTML = `
                  <div class="grab">☰</div>
                  <input type="text" class="dish-name" value="${plato.nombre}">
                  <textarea class="dish-desc">${plato.descripcion || ''}</textarea>
                  <input type="text" class="dish-price" value="${plato.precio}">
                  <div class="actions">
                    <a href="#" class="btn-img" data-id="${plato.id}">🖼️</a>
                    <a href="#" class="del-dish" data-id="${plato.id}">🗑️</a>
                  </div>
                `;
                
                // Agregar al contenedor de platos
                platosContainer.appendChild(platoElement);
              });
              
              console.log('%c[RECONSTRUCCIÓN] Reconstrucción de platos completada con éxito', 'background:green;color:white;padding:3px');
              
              // Reconfigurar eventos de drag & drop
              if (window.DishModule) {
                window.DishModule.setupDragDrop();
              }
              
              // NOTIFICAR ÉXITO
              const msg = sessionStorage.getItem('dishes_reload_success');
              if (msg) {
                NotificationSystem.show(msg, 'success');
                sessionStorage.removeItem('dishes_reload_success');
              }
              
            })
            .catch(error => {
              console.error('[RECONSTRUCCIÓN] Error:', error);
            });
          }
        }
      } catch (innerError) {
        console.error('[RECONSTRUCCIÓN] Error en reconstrucción:', innerError);
      }
      
      console.log('%c[SINCRONIZAR] Sincronización completada con éxito', 'background:green;color:white;padding:3px');
    } catch (error) {
      console.error('[SINCRONIZAR] Error en sincronización:', error);
    }
  }
  
  // Ejecutar sincronización completa al cargar la página
  window.addEventListener('DOMContentLoaded', () => {
    // Primero manejar acciones post-recarga
    handlePostReloadActions();
    
    // Luego forzar sincronización
    sincronizarDesdeBE();
    
    // Diagnosticar cualquier discrepancia después de sincronizar
    setTimeout(() => {
      console.log('%c[CHECKEO FINAL] Verificando estado final del DOM', 'background:purple;color:white;padding:3px');
      const selectores = document.querySelectorAll('select[name="categoria_id"]');
      selectores.forEach((sel, idx) => {
        console.log(`Selector #${idx+1}: Valor=${sel.value}, Opciones=${sel.options.length}`);
      });
    }, 1500);
  });

  const sections = document.querySelectorAll('.panel-section');
  const links = document.querySelectorAll('.sidebar li');

  function show(id){
    sections.forEach(s=>s.classList.toggle('active', s.id === 'section-'+id));
    links.forEach(l=>l.classList.toggle('active', l.dataset.section === id));
  }

  links.forEach(l=>{
    l.addEventListener('click', ()=>{
      show(l.dataset.section);
      history.pushState({},'', '#'+l.dataset.section);
    });
  });

  // On load – open hash or first
  const hash = location.hash.replace('#','');
  show(hash || links[0].dataset.section);

  // Reemplazar alert por notificaciones con auto-desaparición
  window.alert = function(message){
    const text = String(message);
    const type = /error|falló|falla|incorrect|vencid|vencida/i.test(text) ? 'error'
               : (/guardad|renovad|generad|cambiad|ok|éxito|hecho/i.test(text) ? 'success' : 'info');
    NotificationSystem.show(text, type);
  };

  // Confirmación interna (Promise<boolean>)
  if(!NotificationSystem.ask){
    NotificationSystem.ask = function(msg,type='info'){
      return new Promise(resolve=>{
        const box=document.createElement('div');
        box.className='notif confirm '+type;
        box.innerHTML=`<p>${msg}</p><div class="actions"><button class="btn-ok">Aceptar</button><button class="btn-cancel">Cancelar</button></div>`;
        document.body.appendChild(box);
        box.querySelector('.btn-ok').onclick=()=>{box.remove();resolve(true);};
        box.querySelector('.btn-cancel').onclick=()=>{box.remove();resolve(false);};
      });
    };
  }


  // Simple fetch helper with CSRF
  window.$post = async function(url, data){
    data = Object.assign({csrf_token: CSRF_TOKEN}, data);
    const res = await fetch(url,{method:'POST',body:new URLSearchParams(data)});
    return res.json();
  };

  // Toggle theme function
  window.toggleTheme = function() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'night';
    const newTheme = currentTheme === 'night' ? 'day' : 'night';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    console.log(`Theme changed from ${currentTheme} to ${newTheme}`);
  };

  // Initialize theme on page load
  function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'night';
    document.documentElement.setAttribute('data-theme', savedTheme);
  }

  // Initialize theme when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initTheme();
      setupUserForms();
    });
  } else {
    initTheme();
    setupUserForms();
  }
  // --- License forms ---
  const formRenovar = document.getElementById('form-renovar');
  if(formRenovar){
    formRenovar.addEventListener('submit', async e => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(formRenovar));
      try {
        const res = await $post('desktop.php', data);
        if(res.ok){
          alert('Licencia renovada');
          updateLicSummary(res.lic);
        } else alert(res.msg||'Error');
      }catch(err){ alert('Error'); }
    });
  }
  const formManual = document.getElementById('form-manual');
  if(formManual){
    formManual.addEventListener('submit', async e => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(formManual));
      try{
        const res = await $post('desktop.php', data);
        if(res.ok){
          alert('Datos guardados');
          updateLicSummary(res.lic);
        } else alert(res.msg||'Error');
      }catch(err){ alert('Error'); }
    });
  }
  function updateLicSummary(lic){
    const el = document.getElementById('lic-summary');
    if(!el||!lic)return;
    if(lic.expired){
      el.innerHTML = `<strong style="color:#dc2626;">Licencia vencida hace ${lic.days_expired} días</strong>`;
    }else{
      el.innerHTML = `Vigente – quedan <strong>${lic.days_remaining}</strong> días (expira el ${lic.end_date})`;
    }
    document.querySelector('.lic-status').textContent = lic.expired ? 'Licencia vencida' : 'Licencia vigente · '+lic.days_remaining+' días';
    document.querySelector('.lic-status').className = 'lic-status ' + (lic.expired?'error':'ok');
  }
  // --- Tabs toggle ---
  const tabButtons = document.querySelectorAll('.tabs-inline button');
  tabButtons.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const tab = btn.dataset.tab;
      // buttons
      tabButtons.forEach(b=>b.classList.toggle('active', b===btn));
      // contents
      document.querySelectorAll('#section-rest .tab-content').forEach(c=>c.classList.toggle('active', c.id==='tab-'+tab));
    });
  });

  // --- Categories ---
  const catList = document.getElementById('cat-list');
  if(catList){
    let dragSrc;
    catList.addEventListener('dragstart', e=>{
      dragSrc = e.target.closest('.cat-item');
      e.dataTransfer.effectAllowed='move';
    });
    catList.addEventListener('dragover', e=>{
      e.preventDefault();
      const over = e.target.closest('.cat-item');
      if(!over||over===dragSrc)return;
      const rect = over.getBoundingClientRect();
      const next = (e.clientY - rect.top)/(rect.bottom-rect.top) > .5;
      catList.insertBefore(dragSrc, next? over.nextSibling : over);
    });

    // delete
    catList.addEventListener('click', async e=>{
      if(!e.target.classList.contains('del-cat')) return;
      
      const li = e.target.closest('.cat-item');
      const id = li.dataset.id;
      const btn = e.target;
      const originalHTML = btn.innerHTML;
      
      
      btn.disabled = true;
      btn.innerHTML = 'Eliminando...';
      
      try {
        // Intentar eliminar directamente
        const response = await fetch('desktop.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `accion=eliminar_categoria&cat_id=${id}&csrf_token=${CSRF_TOKEN}`
        });
        
        const result = await response.json();
        
        if (result && result.ok) {
          // Eliminar del DOM
          li.remove();
          // Remover todos los elementos de la sección Platos ligados a la categoría
          const formDel = document.querySelector(`#section-platos form.form-dish-add[data-cat="${id}"]`);
          if(formDel){
            // h3 inmediatamente antes
            const heading = formDel.previousElementSibling;
            if(heading && heading.tagName==='H3') heading.remove();
            // párrafo de "vacía" entre form y ul (si existe)
            const maybeEmpty = formDel.nextElementSibling;
            if(maybeEmpty && maybeEmpty.classList.contains('cat-empty-alert')) maybeEmpty.remove();
            // ul lista
            const list = document.querySelector(`#section-platos ul.dish-list[data-cat="${id}"]`);
            if(list) list.remove();
            // finalmente el propio formulario
            formDel.remove();
          }
          // Eliminar la opción en selects de categoría (forms de platos)
          document.querySelectorAll(`select[name="categoria"], select[name="cat_id"], select[name="categoria_id"]`).forEach(sel=>{
            const opt = sel.querySelector(`option[value="${id}"]`);
            if(opt) opt.remove();
          });
          
          // Actualizar números
          const categorias = catList.querySelectorAll('.cat-item');
          categorias.forEach((cat, index) => {
            const num = cat.querySelector('.cat-num');
            if (num) num.textContent = index + 1;
          });
          
          alert('Categoría eliminada correctamente');
          
        } else {
          const errorMsg = (result && result.msg) || 'Error al eliminar la categoría';
          throw new Error(errorMsg);
        }
        
      } catch (error) {
        console.error('Error:', error);
        alert(error.message || 'Error al eliminar la categoría');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHTML;
      }
    });
  }

  const formCatAdd = document.getElementById('form-cat-add');
  if(formCatAdd){
    formCatAdd.addEventListener('submit', async e=>{
      e.preventDefault();
      
      // VERSIÓN TOTALMENTE RECONSTRUIDA PARA DEPURAR PROBLEMA DE SINCRONIZACIÓN
      // 1. Desactivar todo el formulario
      const btn = formCatAdd.querySelector('button');
      const nombreInput = formCatAdd.querySelector('input[name="nombre"]');
      const originalBtnText = btn.textContent;
      
      // Guardar estado original
      const nombreOriginal = nombreInput.value.trim();
      
      // Validar directamente
      if (!nombreOriginal) {
        NotificationSystem.show('El nombre de la categoría es requerido', 'error');
        return;
      }
      
      // Bloquear entrada
      btn.disabled = true;
      nombreInput.disabled = true;
      btn.textContent = 'Agregando...';
      
      // Preparar ID único para tracking
      const trackingId = 'cat_' + Date.now();
      
      try {
        // Mostrar mensaje inicial
        NotificationSystem.show(`Creando categoría "${nombreOriginal}"...`, 'info');
        
        // Construir FormData explícitamente para máximo control
        const formData = new FormData();
        formData.append('accion', 'agregar_categoria');
        formData.append('nombre', nombreOriginal);
        formData.append('csrf_token', CSRF_TOKEN);
        
        // Request directo sin helpers para máximo control
        const response = await fetch('desktop.php', {
          method: 'POST',
          body: formData
        });
        
        // Parsear respuesta cuidadosamente
        let result;
        try {
          const text = await response.text();
          result = JSON.parse(text);
        } catch (parseError) {
          throw new Error('Error al procesar la respuesta del servidor');
        }
        
        if (!result.ok) {
          throw new Error(result.msg || 'Error al crear la categoría');
        }
        
        // Capturar ID de la categoría creada
        const newCatId = result.id;
        if (!newCatId) {
          throw new Error('El servidor no devolvió el ID de la nueva categoría');
        }
        
        // Guardar ID en sessionStorage para verificación post-recarga
        sessionStorage.setItem('lastCreatedCategoryId', newCatId);
        sessionStorage.setItem('lastCreatedCategoryName', nombreOriginal);
        sessionStorage.setItem('lastCreatedCategoryTime', new Date().toISOString());
        
        // Limpiar entrada
        nombreInput.value = '';
        
        // Mostrar mensaje de éxito con instrucciones claras
        // NotificationSystem.show(`Categoría "${nombreOriginal}" creada correctamente.`, 'success', 3000);
        
        // Esperar para asegurar que el backend completó la operación
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // CLAVE DE LA SOLUCIÓN: Recargar con mensaje especial para el código post-recarga
        sessionStorage.setItem('postReloadAction', JSON.stringify({
          type: 'categoryCreated',
          id: newCatId,
          name: nombreOriginal,
          timestamp: Date.now()
        }));
        
        // Forzar recarga completa SIN modificar URL
        window.location.reload(true);
        
      } catch (error) {
        console.error('Error al crear categoría:', error);
        NotificationSystem.show(error.message || 'Error al crear la categoría', 'error');
        
        // Restaurar estado
        btn.disabled = false;
        nombreInput.disabled = false;
        btn.textContent = originalBtnText;
      } finally {
        // Restaurar estado del botón
        btn.disabled = false;
        btn.textContent = originalBtnText;
      }
    });
  }
  const btnCatSave = document.getElementById('btn-cat-save');
  if(btnCatSave){
    btnCatSave.addEventListener('click', async ()=>{
      const ids=[]; const nombres=[];
      catList.querySelectorAll('.cat-item').forEach(li=>{
        ids.push(li.dataset.id);
        nombres.push(li.querySelector('input').value.trim());
      });
      const res = await $post('desktop.php',{accion:'guardar_categorias',orden:ids.join(','),nombres:nombres.join('|')});
      alert(res.ok?'Categorias guardadas':'Error');
    });
  }

  // --- Dishes ---
// Delegado global por si la lista es dinámica o no tiene listener propio
DocumentReady=()=>{
    // Si existe el nuevo módulo, salir y no montar listeners legacy
    if(window.DishModule) return;
  document.body.addEventListener('click', async ev=>{
    const btnEliminar = ev.target.closest('.del-dish');
    if(!btnEliminar) return;
    const li = btnEliminar.closest('.dish-item');
    if(!li) return;
    const ok = NotificationSystem.ask ? await NotificationSystem.ask('¿Eliminar este plato?', 'warning') : confirm('¿Eliminar este plato?');
    if(!ok) return;
    try{
      const fd = new FormData();
      fd.append('accion','eliminar_plato');
      fd.append('plato_id', li.dataset.id);
      fd.append('csrf_token', CSRF_TOKEN);
      const resp = await fetch('desktop.php',{method:'POST',body:fd});
      let r={ok:true};
      try{ r= await resp.json(); }catch(err){}
      if(!r.ok) throw new Error(r.msg||'Error');
    }catch(e){
      NotificationSystem.show(e.message||'Error al eliminar','error');
      return;
    }
    NotificationSystem.show('Plato eliminado','success');
    location.reload();
  });
};
DocumentReady();
  if(!window.DishModule){
  const dishLists = document.querySelectorAll('.dish-list');

  dishLists.forEach(list=>{
    let dragSrc;
    list.addEventListener('dragstart', e=>{
      dragSrc = e.target.closest('.dish-item');
      e.dataTransfer.effectAllowed='move';
    });
    list.addEventListener('dragover', e=>{
      e.preventDefault();
      const over = e.target.closest('.dish-item');
      if(!over||over===dragSrc)return;
      const rect = over.getBoundingClientRect();
      const next = (e.clientY-rect.top)/(rect.height)>.5;
      list.insertBefore(dragSrc, next? over.nextSibling: over);
    });
    list.addEventListener('click', async ev=>{
      // --- eliminar plato ---
      const btnEliminar = ev.target.closest('.del-dish');
      if(btnEliminar){
        const li = btnEliminar.closest('.dish-item');
        if(!li) return;
        const ok = NotificationSystem.ask ? await NotificationSystem.ask('¿Eliminar este plato?', 'warning') : confirm('¿Eliminar este plato?');
        if(!ok) return;
        try{
          const fd = new FormData();
          fd.append('accion','eliminar_plato');
          fd.append('plato_id', li.dataset.id);
          fd.append('csrf_token', CSRF_TOKEN);
          const resp = await fetch('desktop.php',{method:'POST',body:fd});
          const text = await resp.text();
          let j={};
          try{ j = JSON.parse(text); }catch(err){ /* respuesta no JSON, asumir ok */ j={ok:true}; }
          if(!j.ok) throw new Error(j.msg||'Error');
        }catch(e){
          NotificationSystem.show(e.message||'Error al eliminar','error');
          return;
        }
        const parent = li.parentElement;
        li.remove();
        if(parent && !parent.querySelector('.dish-item')){
          const p=document.createElement('p');
          p.className='cat-empty-alert';
          p.textContent='Esta categoría está vacía';
          parent.before(p);
        }
        NotificationSystem.show('Plato eliminado','success');
        // Recargar para mantener sincronía global
        location.reload();
        return; // fin flujo del-dish
      }

      // --- cambiar imagen ---
      if(!ev.target.classList.contains('btn-img')) return;
      const liEl = ev.target.closest('.dish-item');
      if(!liEl) return;
      const dishId = liEl.dataset.id;
      const fileInput = document.createElement('input');
      fileInput.type = 'file';
      fileInput.accept = 'image/*';
      fileInput.onchange = async () => {
        if(!fileInput.files[0]) return;
        const fd = new FormData();
        fd.append('accion','cambiar_img_plato');
        fd.append('plato_id', dishId);
        fd.append('imagen', fileInput.files[0]);
        fd.append('csrf_token', CSRF_TOKEN);
        const resp = await fetch('desktop.php',{method:'POST',body:fd});
        const j = await resp.json();
        if(j.ok){
          liEl.querySelector('img.dish-thumb').src = j.path + '?' + Date.now();
          NotificationSystem.show('Imagen actualizada','success');
        }else{
          NotificationSystem.show(j.msg || 'Error al subir imagen','error');
        }
      };
      fileInput.click();
    });
  });

  } // fin guardia legacy
  // add dish forms (delegated, soporta categorías nuevas)
  document.body.addEventListener('submit', async e => {
    if(!e.target.classList.contains('form-dish-add')) return;
    e.preventDefault();

    const form = e.target;
    const fd = new FormData(form);
    fd.append('csrf_token', CSRF_TOKEN);

    try {
      // Validaciones
      const nombre = (fd.get('nombre')||'').toString().trim();
      const descripcion = (fd.get('descripcion')||'').toString().trim();
      let precio = (fd.get('precio')||'').toString().trim().replace(/[^0-9]/g,'');
      const imgInput = form.querySelector('input[type="file"]');
      if(!nombre) throw new Error('El nombre del plato es requerido');
      if(!descripcion) throw new Error('La descripción es requerida');
      if(!precio || isNaN(precio) || Number(precio)<=0) throw new Error('Precio inválido');
      if(!imgInput || !imgInput.files.length) throw new Error('Imagen requerida');
      fd.set('precio', precio);

      // Feedback botón
      const btn = form.querySelector('button[type="submit"]') || form.querySelector('button');
      const btnText = btn ? btn.textContent : '';
      if(btn){ btn.disabled = true; btn.textContent = 'Guardando…'; }

      const res = await fetch('desktop.php',{method:'POST',body:fd});
      const json = await res.json();
      if(!json.ok) throw new Error(json.msg || 'Error al guardar');

      // Ubicar lista correspondiente
      const catBlock = form.closest('[data-cat-id]');
      const list = catBlock?.querySelector('.dish-list');
      if(!list){ location.reload(); return; }

      // Quitar alerta de vacía si existe
      const emptyP = catBlock.querySelector('.cat-empty-alert');
      if(emptyP) emptyP.remove();

      // Crear item
      const li = document.createElement('li');
      li.className = 'dish-item';
      li.draggable = true;
      li.dataset.id = json.id;
      li.innerHTML = `
        <span class="grab">☰</span>
        <input class="dish-name" type="text" value="${nombre}">
        <input class="dish-desc" type="text" value="${descripcion}" style="flex:1">
        <img class="dish-thumb" src="${json.img||'assets/placeholder.png'}">
        <input class="dish-price" type="text" value="$${Number(precio).toLocaleString('es-CL')}" style="width:80px">
        <button class="btn-img"  title="Cambiar imagen">📷</button>
        <button class="del-dish" title="Eliminar">🗑</button>`;
      list.appendChild(li);

      form.reset();
      // NotificationSystem.show('Plato guardado correctamente','success');
      if(btn){ btn.disabled = false; btn.textContent = btnText; }
    } catch(err){
      NotificationSystem.show(err.message || 'Error al guardar','error');
    }
  });
  /* BLOQUE OBSOLETO INICIO – comentado para evitar duplicidad
    f.addEventListener('submit', async e=>{
      e.preventDefault();
      const form = e.target;
      const formData = new FormData(form);
      const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('button');
      const originalText = submitBtn ? submitBtn.textContent : '';
      
      try {
        // Validaciones iniciales
        const nombre = form.querySelector('input[name="nombre"]').value.trim();
        const descripcion = form.querySelector('input[name="descripcion"]').value.trim();
        let precio = form.querySelector('input[name="precio"]').value.trim();
        const fileInput = form.querySelector('input[type="file"]');
        
        if (!nombre) {
          throw new Error('El nombre del plato es requerido');
        }
        
        if (!descripcion) {
          throw new Error('La descripción es requerida');
        }
        
        // Limpiar y validar precio
        precio = precio.replace(/[^0-9]/g, '');
        if (!precio || isNaN(precio) || parseFloat(precio) <= 0) {
          throw new Error('Por favor ingresa un precio válido');
        }
        formData.set('precio', precio);
        
        // Validar imagen
        if (!fileInput.files.length) {
          throw new Error('Por favor selecciona una imagen para el plato');
        }
        
        // Mostrar estado de carga
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner">⌛</span> Guardando...';
        
        // Agregar token CSRF
        formData.append('csrf_token', CSRF_TOKEN);
        // Enviar datos
        const response = await fetch('desktop.php', {
          method: 'POST',
          body: formData
        });
        
        if (!response.ok) {
          throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const result = await response.json();
        
        if (result.ok) {
          NotificationSystem.show('Plato guardado correctamente', 'success');
          // Limpiar formulario
          form.reset();
          // Insertar en DOM sin recargar
          const catId = form.querySelector('input[name="categoria_id"]').value;
          const list = document.querySelector(`#dish-list-${catId}`);
          if(list){
            // Si había alerta de vacía, quitarla
            const alertEmpty = list.previousElementSibling;
            if(alertEmpty && alertEmpty.classList && alertEmpty.classList.contains('cat-empty-alert')) alertEmpty.remove();
            const li = document.createElement('li');
            li.className='dish-item';
            li.draggable=true;
            li.dataset.id = result.id;
            li.innerHTML = `<span class="grab">☰</span>
              <input type="text" class="dish-name" value="${nombre}">
              <input type="text" class="dish-desc" placeholder="Desc" value="${descripcion}" style="flex:1">
              <img src="${result.img||'assets/placeholder.png'}" class="dish-thumb">
              <input type="text" class="dish-price" value="$${Number(precio).toLocaleString('es-CL')}" style="width:80px">
              <button class="btn-img" title="Cambiar imagen">📷</button>
              <button class="del-dish" title="Eliminar">🗑</button>`;
            list.appendChild(li);
          }
        } else {
          throw new Error(result.msg || 'Error al guardar el plato');
        }
      } catch (error) {
        console.error('Error al guardar plato:', error);
        NotificationSystem.show(error.message || 'Error al guardar el plato. Por favor intenta de nuevo.', 'error');
      } finally {
        if (submitBtn) {

  */
const btnDishSave=document.getElementById('btn-dish-save');
  if(btnDishSave){
    btnDishSave.addEventListener('click', async ()=>{
      const ids=[]; const names=[]; const prices=[]; const descs=[]; const ordenes={};
      dishLists.forEach(list=>{
        const catId=list.id.replace('dish-list-','');
        const order=[];
        list.querySelectorAll('.dish-item').forEach(li=>{
          const id=li.dataset.id;
          ids.push(id);
          names.push(li.querySelector('.dish-name').value.trim());
          descs.push(li.querySelector('.dish-desc').value.trim());
          prices.push(li.querySelector('.dish-price').value);
          order.push(id);
        });
        ordenes[catId]=order.join(',');
      });
      const fd=new FormData();
      fd.append('accion','guardar_platos');
      ids.forEach((id,i)=>{
        fd.append('plato_id[]',id);
        fd.append('nombre[]',names[i]);
        fd.append('descripcion[]',descs[i]);
        fd.append('precio[]',prices[i]);
      });
      for(const cid in ordenes){ fd.append(`orden_platos[${cid}]`, ordenes[cid]); }
      fd.append('csrf_token',CSRF_TOKEN);
      const res=await fetch('desktop.php',{method:'POST',body:fd}).then(r=>r.json());
      alert(res.ok?'Platos guardados':'Error');
    });
  }

  // --- Footer form ---
  const formFooter=document.getElementById('form-footer');
  if(formFooter){
    formFooter.addEventListener('submit',async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(formFooter));
      const res=await $post('desktop.php',data);
      alert(res.ok?'Footer guardado':'Error');
    });
  }

  // --- Theme form ---
  const formTheme=document.getElementById('form-theme');
  if(formTheme){
    formTheme.addEventListener('submit', async e=>{
      e.preventDefault();
      const data=Object.fromEntries(new FormData(formTheme));
      const res=await $post('desktop.php',data);
      alert(res.ok?'Tema guardado':'Error');
    });
  }

  // --- Form Restaurant ---
  const formRest = document.getElementById('form-rest');
  if(formRest){
    formRest.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(formRest);
      fd.append('csrf_token', CSRF_TOKEN);
      try{
        const res = await fetch('desktop.php',{method:'POST',body:fd});
        const j = await res.json();
        alert(j.ok ? 'Datos guardados' : 'Error');
      }catch(err){ alert('Error'); }
    });
  }

  const formSeo = document.getElementById('form-seo');
  if(formSeo){
    formSeo.addEventListener('submit', async e=>{
      e.preventDefault();
      const fd = new FormData(formSeo);
      fd.append('csrf_token', CSRF_TOKEN);
      try{
        const res = await fetch('desktop.php',{method:'POST',body:fd});
        const j = await res.json();
        alert(j.ok ? 'SEO guardado' : 'Error');
      }catch(err){ alert('Error'); }
    });
  }
  const btnSitemap=document.getElementById('btn-sitemap');
  if(btnSitemap){
    btnSitemap.addEventListener('click', async ()=>{
      const res = await $post('desktop.php',{accion:'generar_sitemap'});
      alert(res.ok?'Sitemap generado':'Error');
      if(res.ok) location.reload();
    });
  }

  // --- SEO Check ---
  const btnSeoCheck = document.getElementById('btn-seo-check');
  if(btnSeoCheck){
    btnSeoCheck.addEventListener('click', async ()=>{
      const res = await $post('desktop.php',{accion:'seo_check'});
      if(!res.ok){ alert('Error en verificación'); return; }
      const reportDiv = document.getElementById('seo-report');
      reportDiv.innerHTML='';
        const banner=document.createElement('div');
        banner.className= res.overall==='OK' ? 'seo-banner-ok' : (res.overall==='WARNING' ? 'seo-banner-warn' : 'seo-banner-err');
        banner.textContent = res.overall==='OK' ? '✔ El contenido SEO está completo' : '✖ El contenido SEO NO está completo, revise los puntos marcados.';
        reportDiv.appendChild(banner);
      const tbl = document.createElement('table');
      tbl.className = 'seo-table';
      tbl.innerHTML = '<tr><th>Elemento</th><th>Estado</th><th>Detalle</th><th>Valor</th></tr>';
      (res.rows||[]).forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r[0]}</td><td>${r[1]}</td><td>${r[2]}</td><td>${r[3]||''}</td>`;
        tr.className = r[1]==='ERROR' ? 'seo-err' : (r[1]==='WARNING' ? 'seo-warn' : 'seo-ok');
        tbl.appendChild(tr);
      });
      reportDiv.appendChild(tbl);
      const btnCsv = document.getElementById('btn-seo-download');
      if(btnCsv){
        btnCsv.style.display='inline-block';
        btnCsv.onclick = ()=>{
          const link = document.createElement('a');
          link.download = 'seo_report.csv';
          link.href = 'data:text/csv;base64,' + res.csv;
          link.click();
        };
      }
    });
  }
})();
