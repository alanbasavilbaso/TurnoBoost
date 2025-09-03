// Gestor específico para servicios
class ServiceModalManager extends EntityModalManager {
    constructor() {
        super({
            entityName: 'servicio',
            entityNamePlural: 'servicios',
            baseUrl: '/servicios',
            deleteWarning: 'Esta acción no se puede deshacer y eliminará todas las citas asociadas.'
        });
    }

    // Sobrescribir los métodos para usar los atributos correctos
    handleViewClick(button) {
        const entityId = button.dataset.serviceId; // Usar serviceId en lugar de entityId
        if (entityId) {
            this.loadEntityDetails(entityId);
        }
    }

    handleEditClick(button) {
        const entityId = button.dataset.serviceId; // Usar serviceId en lugar de entityId
        if (entityId) {
            this.loadEditForm(entityId);
        }
    }

    handleDeleteClick(button) {
        const entityId = button.dataset.serviceId; // Usar serviceId en lugar de entityId
        const entityName = button.dataset.serviceName; // Usar serviceName en lugar de entityName
        const csrfToken = button.dataset.csrfToken; // Obtener el token del botón
        if (entityId && entityName) {
            this.showDeleteModal(entityId, entityName, csrfToken);
        }
    }

    // Sobrescribir showDeleteModal para usar el token del botón
    showDeleteModal(entityId, entityName, csrfToken) {
        document.getElementById('entityTypeLabel').textContent = `el ${this.config.entityName}`;
        document.getElementById('entityName').textContent = entityName;
        document.getElementById('entityDeleteWarning').textContent = this.config.deleteWarning;
        document.getElementById('entityDeleteForm').action = `${this.config.baseUrl}/${entityId}`;
        document.getElementById('entityDeleteToken').value = csrfToken; // Usar el token pasado como parámetro
        
        const modal = new bootstrap.Modal(document.getElementById('entityDeleteModal'));
        modal.show();
    }

    renderEntityDetails(data) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-tag text-primary me-2"></i>Información Básica</h6>
                    <p><strong>Nombre:</strong> ${data.name}</p>
                    <p><strong>Descripción:</strong> ${data.description || 'Sin descripción'}</p>
                    <p><strong>Duración:</strong> ${data.duration} minutos</p>
                    <p><strong>Precio:</strong> $${data.price}</p>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-cogs text-info me-2"></i>Configuración</h6>
                    <p><strong>Estado:</strong> ${data.active ? 'Activo' : 'Inactivo'}</p>
                    <p><strong>Tipo de Entrega:</strong> ${data.delivery_type}</p>
                    <p><strong>Tipo de Servicio:</strong> ${data.service_type}</p>
                    ${data.frequency_weeks ? `<p><strong>Frecuencia:</strong> Cada ${data.frequency_weeks} semanas</p>` : ''}
                    <p><strong>Profesionales:</strong> ${data.professionals_count}</p>
                </div>
            </div>
        `;
    }

    initializeForm() {
        // Inicializar funcionalidad específica de servicios
        if (typeof initializeServiceForm === 'function') {
            initializeServiceForm();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new ServiceModalManager();
});