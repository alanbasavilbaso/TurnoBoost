/**
 * Sistema genérico de notificaciones
 * Reemplaza los alerts nativos con notificaciones elegantes
 */
class NotificationSystem {
    constructor() {
        this.container = null;
        this.init();
    }

    init() {
        // Crear el contenedor de notificaciones si no existe
        if (!document.getElementById('notification-container')) {
            this.createContainer();
        }
        this.container = document.getElementById('notification-container');
    }

    createContainer() {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    show(message, type = 'info', duration = 5000) {
        const notification = this.createNotification(message, type);
        this.container.appendChild(notification);

        // Animación de entrada
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 10);

        // Auto-ocultar después del tiempo especificado
        if (duration > 0) {
            setTimeout(() => {
                this.hide(notification);
            }, duration);
        }

        return notification;
    }

    createNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        
        const colors = {
            success: { bg: '#d4edda', border: '#c3e6cb', text: '#155724', icon: '✓' },
            error: { bg: '#f8d7da', border: '#f5c6cb', text: '#721c24', icon: '✕' },
            warning: { bg: '#fff3cd', border: '#ffeaa7', text: '#856404', icon: '⚠' },
            info: { bg: '#d1ecf1', border: '#bee5eb', text: '#0c5460', icon: 'ℹ' }
        };

        const color = colors[type] || colors.info;

        notification.style.cssText = `
            background-color: ${color.bg};
            border: 1px solid ${color.border};
            color: ${color.text};
            padding: 12px 16px;
            margin-bottom: 10px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            pointer-events: auto;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            position: relative;
            cursor: pointer;
        `;

        notification.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 8px;">
                <span style="font-weight: bold; font-size: 16px;">${color.icon}</span>
                <div style="flex: 1;">${message}</div>
                <button style="
                    background: none;
                    border: none;
                    color: ${color.text};
                    cursor: pointer;
                    font-size: 18px;
                    line-height: 1;
                    padding: 0;
                    margin-left: 8px;
                    opacity: 0.7;
                " onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;

        // Hacer clic en la notificación para cerrarla
        notification.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON') {
                this.hide(notification);
            }
        });

        return notification;
    }

    hide(notification) {
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Métodos de conveniencia
    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 7000) {
        return this.show(message, 'error', duration);
    }

    warning(message, duration = 6000) {
        return this.show(message, 'warning', duration);
    }

    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }
}

// Crear instancia global
window.notifications = new NotificationSystem();

// Función global de conveniencia (compatible con alert)
window.showNotification = function(message, type = 'info', duration = 5000) {
    return window.notifications.show(message, type, duration);
};

// Fallback para compatibilidad
window.alertFallback = window.alert;
window.alert = function(message) {
    if (window.notifications) {
        return window.notifications.info(message);
    } else {
        return window.alertFallback(message);
    }
};