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

        if (!modal || !form || !entityTypeLabel || !entityNameElement) {
            console.error('Delete modal elements not found');
            return;
        }

        // Configurar el modal
        entityTypeLabel.textContent = this.config.entityType;
        entityNameElement.textContent = entityName;
        
        if (entityDeleteWarning) {
            entityDeleteWarning.innerHTML = `
                Esta acción eliminará el local y todos los datos asociados (servicios, profesionales, citas).
            `;
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
                        <label class="form-label fw-bold">Teléfono</label>
                        <p class="form-control-plaintext">
                            <i class="fas fa-phone text-muted me-2"></i>${data.phone || 'N/A'}
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <p class="form-control-plaintext">
                            <i class="fas fa-envelope text-muted me-2"></i>${data.email || 'N/A'}
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dirección Completa</label>
                        <p class="form-control-plaintext">
                            <i class="fas fa-map-marker-alt text-muted me-2"></i>${data.address || 'N/A'}
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dominio</label>
                        <p class="form-control-plaintext">
                            ${data.domain ? 
                                `<i class="fas fa-globe text-muted me-2"></i>${data.domain}` : 
                                '<span class="text-muted">Sin dominio configurado</span>'
                            }
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">URL de Reservas</label>
                        <p class="form-control-plaintext">
                            ${data.domain ? 
                                `<a href="${window.location.protocol}//${window.location.host}/${data.domain}" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-external-link-alt text-muted me-2"></i>${window.location.protocol}//${window.location.host}/${data.domain}
                                </a>` : 
                                '<span class="text-muted">No configurada</span>'
                            }
                        </p>
                    </div>
                </div>
            </div>
        `;
    }

    initializeForm(formContainer) {
        // Aquí puedes agregar inicialización específica del formulario de locations
        // Por ejemplo, validaciones, selectores especiales, etc.
        console.log('Location form initialized');
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new LocationModalManager();
});