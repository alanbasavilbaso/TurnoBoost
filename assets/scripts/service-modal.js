// Función para encontrar el botón de eliminar correcto
function findDeleteButton(element) {
    // Si el elemento clickeado es el botón mismo
    if (element.hasAttribute('data-service-id')) {
        return element;
    }
    
    // Si el elemento clickeado es un hijo (como el ícono <i>), buscar el botón padre
    let parent = element.parentElement;
    while (parent && !parent.hasAttribute('data-service-id')) {
        parent = parent.parentElement;
    }
    
    return parent;
}

// Función para resetear el modal
function resetModal() {
    const modal = document.getElementById('deleteModal');
    const form = modal.querySelector('form');
    const serviceName = modal.querySelector('#serviceName');
    const csrfInput = modal.querySelector('input[name="_token"]');
    
    if (form) {
        form.action = '';
    }
    if (serviceName) {
        serviceName.textContent = '';
    }
    if (csrfInput) {
        csrfInput.value = '';
    }
}

// Función para configurar el modal con datos del botón
function configureModalFromButton(button) {
    if (!button) {
        return false;
    }
    
    const serviceId = button.getAttribute('data-service-id');
    const serviceName = button.getAttribute('data-service-name');
    const deleteUrl = button.getAttribute('data-delete-url');
    const csrfToken = button.getAttribute('data-csrf-token');
    
    const modal = document.getElementById('deleteModal');
    const form = modal.querySelector('form');
    const serviceNameElement = modal.querySelector('#serviceName');
    const csrfInput = modal.querySelector('input[name="_token"]');
    
    if (form && deleteUrl) {
        form.action = deleteUrl;
    }
    
    if (serviceNameElement && serviceName) {
        serviceNameElement.textContent = serviceName;
    }
    
    if (csrfInput && csrfToken) {
        csrfInput.value = csrfToken;
    }
    
    return true;
}

// Configurar event listeners cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('deleteModal');
    
    if (modal) {
        // Reset cuando el modal se cierra completamente
        modal.addEventListener('hidden.bs.modal', function() {
            resetModal();
        });
    }
    
    // Event delegation para los botones de eliminar
    document.addEventListener('click', function(event) {
        // Buscar el botón de eliminar correcto
        const deleteButton = findDeleteButton(event.target);
        
        if (deleteButton && deleteButton.hasAttribute('data-bs-toggle') && deleteButton.getAttribute('data-bs-target') === '#deleteModal') {
            // Configurar el modal antes de que se abra
            const configured = configureModalFromButton(deleteButton);
            
            if (!configured) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
        }
    });
});