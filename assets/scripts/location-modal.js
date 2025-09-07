class LocationModalManager extends EntityModalManager {
    constructor() {
        super({
            entityType: 'local',
            entityName: 'local',
            baseUrl: '/location',
            newUrl: '/location/new',
            editUrl: '/location/{id}/edit'
        });
    }

    // Sobrescribir métodos para usar data-location-id en lugar de data-entity-id
    handleViewClick(button) {
        const locationId = button.dataset.locationId;
        
        if (!locationId) {
            console.error('Location ID not found');
            return;
        }

        // Usar el método correcto de la clase base
        this.loadEntityDetails(locationId);
    }

    handleEditClick(button) {
        const locationId = button.dataset.locationId;
        
        if (!locationId) {
            console.error('Location ID not found');
            return;
        }

        // Usar el método correcto de la clase base
        this.loadEditForm(locationId);
    }

    handleDeleteClick(button) {
        const locationId = button.dataset.locationId;
        const locationName = button.dataset.locationName;
        const csrfToken = button.dataset.csrfToken;
        const deleteUrl = button.dataset.deleteUrl;
        
        if (!locationId || !locationName) {
            console.error('Location data not found');
            return;
        }

        this.showDeleteModal(locationId, locationName, deleteUrl, csrfToken);
    }

    // Sobrescribir showDeleteModal para usar el token CSRF del botón
    showDeleteModal(entityId, entityName, deleteUrl, csrfToken) {
        const modal = document.getElementById('entityDeleteModal');
        const form = document.getElementById('entityDeleteForm');
        const entityTypeLabel = document.getElementById('entityTypeLabel');
        const entityNameElement = document.getElementById('entityName');
        const entityDeleteWarning = document.getElementById('entityDeleteWarning');
        const tokenInput = document.getElementById('entityDeleteToken');
        
        // Elementos adicionales para personalizar el modal
        const modalTitle = modal.querySelector('.modal-title');
        const submitButton = modal.querySelector('button[type="submit"]');
        const modalBody = modal.querySelector('.modal-body p'); // Para cambiar el texto principal
    
        if (!modal || !form || !entityTypeLabel || !entityNameElement) {
            console.error('Delete modal elements not found');
            return;
        }
    
        // Configurar el modal con textos específicos para soft delete
        if (modalTitle) {
            modalTitle.innerHTML = '<i class="fas fa-power-off me-2"></i>Desactivar Local';
        }
        
        // Cambiar el texto principal del modal
        if (modalBody) {
            modalBody.innerHTML = `¿Deseas desactivar <span id="entityTypeLabel">${this.config.entityType}</span> <strong id="entityName">${entityName}</strong>?`;
        }
        
        entityTypeLabel.textContent = this.config.entityType;
        entityNameElement.textContent = entityName;
        
        if (entityDeleteWarning) {
            entityDeleteWarning.innerHTML = `
                Esta acción desactivará el local. Podrás reactivarlo más tarde si es necesario.
            `;
        }
        
        // Cambiar el texto del botón de submit
        if (submitButton) {
            submitButton.innerHTML = '<i class="fas fa-power-off me-2"></i>Desactivar';
            submitButton.className = 'btn btn-warning'; // Cambiar color a warning en lugar de danger
        }
    
        // Configurar el formulario
        form.action = deleteUrl;
        if (tokenInput && csrfToken) {
            tokenInput.value = csrfToken;
        }
    
        // Mostrar el modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    renderEntityDetails(data) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre del Local</label>
                        <p class="form-control-plaintext">${data.name || 'N/A'}</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Teléfono 
                            <i class="fas fa-phone text-muted me-2"></i>
                        </label>
                        <p class="form-control-plaintext">
                            ${data.phone || 'N/A'}
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Dirección Completa 
                            <i class="fas fa-map-marker-alt text-muted me-2"></i>
                        </label>
                        <p class="form-control-plaintext">
                            ${data.address || 'N/A'}
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Email 
                            <i class="fas fa-envelope text-muted me-2"></i>
                        </label>
                        <p class="form-control-plaintext">
                            ${data.email || 'N/A'}
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new LocationModalManager();
});