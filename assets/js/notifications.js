// Notifications system
class NotificationSystem {
    static show(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const icon = this.getIcon(type);
        
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${icon}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animación de entrada
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Auto-eliminar después de 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    static confirm(message, options = {}) {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            
            const {
                confirmText = 'Confirmar',
                cancelText = 'Cancelar',
                type = 'warning'
            } = options;
            
            modal.innerHTML = `
                <div class="confirm-content">
                    <div class="confirm-icon">${this.getIcon(type)}</div>
                    <p class="confirm-message">${message}</p>
                    <div class="confirm-buttons">
                        <button class="btn-confirm">${confirmText}</button>
                        <button class="btn-cancel">${cancelText}</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Animación de entrada
            setTimeout(() => modal.classList.add('show'), 10);
            
            const handleResult = (result) => {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.remove();
                    resolve(result);
                }, 300);
            };
            
            modal.querySelector('.btn-confirm').onclick = () => handleResult(true);
            modal.querySelector('.btn-cancel').onclick = () => handleResult(false);
        });
    }
    
    static getIcon(type) {
        switch (type) {
            case 'success': return '✅';
            case 'error': return '❌';
            case 'warning': return '⚠️';
            case 'info': return 'ℹ️';
            default: return '';
        }
    }
}

// Estilos para las notificaciones
const styles = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateX(120%);
    transition: transform 0.3s ease;
    z-index: 1000;
}

.notification.show {
    transform: translateX(0);
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notification-icon {
    font-size: 1.2em;
}

.notification-message {
    color: #333;
    font-size: 0.95em;
}

.notification-success {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
}

.notification-error {
    background: #fde8e8;
    border-left: 4px solid #f44336;
}

.notification-warning {
    background: #fff3e0;
    border-left: 4px solid #ff9800;
}

.notification-info {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
}

.confirm-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 1001;
}

.confirm-modal.show {
    opacity: 1;
}

.confirm-content {
    background: white;
    padding: 24px;
    border-radius: 8px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    transform: translateY(-20px);
    transition: transform 0.3s ease;
}

.confirm-modal.show .confirm-content {
    transform: translateY(0);
}

.confirm-icon {
    font-size: 2em;
    margin-bottom: 16px;
}

.confirm-message {
    margin: 0 0 20px;
    color: #333;
    line-height: 1.4;
}

.confirm-buttons {
    display: flex;
    justify-content: center;
    gap: 12px;
}

.btn-confirm, .btn-cancel {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.95em;
    transition: background 0.2s;
}

.btn-confirm {
    background: #4caf50;
    color: white;
}

.btn-confirm:hover {
    background: #43a047;
}

.btn-cancel {
    background: #f44336;
    color: white;
}

.btn-cancel:hover {
    background: #e53935;
}
`;

// Agregar estilos al documento
const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);

// Exportar para uso global
window.NotificationSystem = NotificationSystem;
