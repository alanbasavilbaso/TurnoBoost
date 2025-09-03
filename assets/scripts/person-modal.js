// Gestor específico para clientes/pacientes
class PersonModalManager extends EntityModalManager {
    constructor() {
        super({
            entityName: 'cliente',
            entityNamePlural: 'clientes',
            baseUrl: '/clients',
            deleteWarning: 'Esta acción no se puede deshacer y eliminará todas las citas asociadas.'
        });
    }

    // Sobrescribir los métodos para usar los atributos correctos
    handleViewClick(button) {
        const entityId = button.dataset.patientId; // Usar patientId en lugar de entityId
        if (entityId) {
            this.loadEntityDetails(entityId);
        }
    }

    handleEditClick(button) {
        const entityId = button.dataset.patientId; // Usar patientId en lugar de entityId
        if (entityId) {
            this.loadEditForm(entityId);
        }
    }

    handleDeleteClick(button) {
        const entityId = button.dataset.patientId; // Usar patientId en lugar de entityId
        const entityName = button.dataset.patientName; // Usar patientName en lugar de entityName
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
        document.getElementById('entityDeleteToken').value = csrfToken;
        
        const modal = new bootstrap.Modal(document.getElementById('entityDeleteModal'));
        modal.show();
    }

    renderEntityDetails(data) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Información Personal</h6>
                    <p><strong>Nombre:</strong> ${data.name}</p>
                    <p><strong>Teléfono:</strong> ${data.phone || 'No especificado'}</p>
                    <p><strong>Email:</strong> ${data.email || 'No especificado'}</p>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-2">Información Adicional</h6>
                    <p><strong>Notas:</strong> ${data.notes || 'Sin notas'}</p>
                    <p><strong>Fecha de registro:</strong> ${new Date(data.createdAt).toLocaleDateString()}</p>
                </div>
            </div>
        `;
    }

    initializeForm() {
        // Inicializar funcionalidad específica de clientes
        if (typeof initializePersonForm === 'function') {
            initializePersonForm();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new PersonModalManager();
});