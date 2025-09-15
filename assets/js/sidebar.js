// sidebar.js - Manejo del sidebar y navegación

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar el tema
    initTheme();
    
    // Inicializar el sidebar
    initSidebar();
    
    // Manejar el cambio de tema
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('change', toggleTheme);
    }
});

// Inicializar el sidebar
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // Manejar clics en los elementos del menú
    const menuItems = sidebar.querySelectorAll('.nav-item');
    menuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Prevenir el comportamiento por defecto de los enlaces
            e.preventDefault();
            
            // Remover clase activa de todos los items
            menuItems.forEach(i => i.classList.remove('active'));
            
            // Agregar clase activa al item clickeado
            this.classList.add('active');
            
            // Si el item tiene un data-section, mostrarlo
            const sectionId = this.getAttribute('data-section');
            if (sectionId) {
                showSection(sectionId);
            }
            
            // Cerrar el sidebar en móvil después de hacer clic
            if (window.innerWidth < 768) {
                toggleSidebar();
            }
        });
    });

    // Mostrar la sección activa basada en el hash de la URL
    const hash = window.location.hash.substring(1) || 'sys';
    showSection(hash);
    
    // Marcar el ítem activo
    const activeItem = sidebar.querySelector(`[data-section="${hash}"]`);
    if (activeItem) {
        menuItems.forEach(i => i.classList.remove('active'));
        activeItem.classList.add('active');
    } else if (menuItems.length > 0) {
        // Mostrar la primera sección por defecto
        menuItems[0].click();
    }
}

// Mostrar una sección específica
function showSection(sectionId) {
    // Ocultar todas las secciones
    document.querySelectorAll('.panel-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Mostrar la sección solicitada
    const section = document.getElementById(`section-${sectionId}`);
    if (section) {
        section.classList.add('active');
        // Actualizar la URL sin recargar la página
        history.pushState(null, '', `#${sectionId}`);
    }
}

// Manejar el botón de alternar el tema
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    // Actualizar el atributo de tema
    html.setAttribute('data-theme', newTheme);
    
    // Guardar la preferencia en localStorage
    localStorage.setItem('theme', newTheme);
    
    // Actualizar el estado del toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.checked = newTheme === 'dark';
    }
}

// Inicializar el tema
function initTheme() {
    const html = document.documentElement;
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    // Aplicar el tema guardado
    html.setAttribute('data-theme', savedTheme);
    
    // Actualizar el estado del toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.checked = savedTheme === 'dark';
    }
}

// Manejar el botón de alternar el sidebar en móvil
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.app-wrapper');
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('sidebar-collapsed');
    
    // Guardar el estado del sidebar en localStorage
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

// Hacer la función accesible globalmente
window.toggleSidebar = toggleSidebar;
