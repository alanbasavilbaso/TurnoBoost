// Gestor específico para servicios
class ServiceModalManager extends EntityModalManager {
    constructor() {
        super({
            entityName: 'servicio',
            entityNamePlural: 'servicios',
            baseUrl: '/servicios',
            deleteWarning: 'Esta acción no se puede deshacer.'
        });
        
        // Agregar listener para reactivación
        this.initializeReactivateHandler();
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

    // Nuevo método para manejar reactivación
    handleReactivateClick(button) {
        const entityId = button.dataset.serviceId;
        const entityName = button.dataset.serviceName;
        const reactivateUrl = button.dataset.reactivateUrl;
        const csrfToken = button.dataset.csrfToken;
        
        if (entityId && entityName) {
            this.showReactivateConfirmation(entityId, entityName, reactivateUrl, csrfToken);
        }
    }

    showReactivateConfirmation(entityId, entityName, reactivateUrl, csrfToken) {
        if (confirm(`¿Estás seguro de que deseas reactivar el ${this.config.entityName} "${entityName}"?`)) {
            // Crear formulario para envío POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = reactivateUrl;
            
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrfToken;
            
            form.appendChild(tokenInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    initializeReactivateHandler() {
        // Delegar eventos para botones de reactivación
        document.addEventListener('click', (e) => {
            if (e.target.closest('.reactivate-btn')) {
                e.preventDefault();
                this.handleReactivateClick(e.target.closest('.reactivate-btn'));
            }
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    new ServiceModalManager();
});


// Función para inicializar el formulario de servicios
function initializeServiceForm() {
    const serviceTypeInputs = document.querySelectorAll('input[name="service[serviceType]"]');
    const frequencyRow = document.querySelector('.frequency-row');
    const frequencyField = document.querySelector('.frequency-field');
    const frequencyInputs = document.querySelectorAll('input[name="service[frequencyWeeks]"]');
    
    // Lógica para opciones de reserva online
    const onlineBookingCheckbox = document.getElementById('service_onlineBookingEnabled');
    const bookingOptions = document.getElementById('bookingOptions');
    
    if (!serviceTypeInputs.length) return;
    
    // Crear elemento para mostrar el mensaje dinámico
    let frequencyMessage = document.querySelector('.frequency-message');
    if (!frequencyMessage) {
        frequencyMessage = document.createElement('div');
        frequencyMessage.className = 'alert alert-info mt-2 frequency-message';
        frequencyMessage.style.display = 'none';
        
        // Insertar el mensaje después del campo de frecuencia
        if (frequencyField) {
            frequencyField.appendChild(frequencyMessage);
        }
    }
    
    function toggleFrequencyField() {
        const selectedValue = document.querySelector('input[name="service[serviceType]"]:checked')?.value;
        
        if (selectedValue === 'recurring') {
            if (frequencyRow) frequencyRow.style.display = 'flex';
            if (frequencyField) frequencyField.style.display = 'flex';
        } else {
            if (frequencyRow) frequencyRow.style.display = 'none';
            if (frequencyField) frequencyField.style.display = 'none';
            // Limpiar selección de frecuencia
            frequencyInputs.forEach(input => {
                input.checked = false;
            });
            if (frequencyMessage) frequencyMessage.style.display = 'none';
        }
    }
    
    function toggleBookingOptions() {
        if (onlineBookingCheckbox && bookingOptions) {
            bookingOptions.style.display = onlineBookingCheckbox.checked ? 'flex' : 'none';
        }
    }
    
    function updateFrequencyMessage() {
        const selectedFrequency = document.querySelector('input[name="service[frequencyWeeks]"]:checked')?.value;
        
        if (selectedFrequency && frequencyMessage) {
            const weeks = parseInt(selectedFrequency);
            let message;
            
            if (weeks === 1) {
                message = 'Los turnos se repetirían cada semana';
            } else {
                message = `Los turnos se repetirían cada ${weeks} semanas`;
            }
            
            frequencyMessage.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
            frequencyMessage.style.display = 'flex';
        } else if (frequencyMessage) {
            frequencyMessage.style.display = 'none';
        }
    }
    
    // Ejecutar al cargar
    toggleFrequencyField();
    toggleBookingOptions();
    
    // Event listeners
    serviceTypeInputs.forEach(input => {
        input.addEventListener('change', toggleFrequencyField);
    });
    
    frequencyInputs.forEach(input => {
        input.addEventListener('change', updateFrequencyMessage);
    });
    
    // Event listener para opciones de reserva online
    if (onlineBookingCheckbox) {
        onlineBookingCheckbox.addEventListener('change', toggleBookingOptions);
    }
}