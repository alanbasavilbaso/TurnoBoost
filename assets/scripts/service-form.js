document.addEventListener('DOMContentLoaded', function() {
    const serviceTypeInputs = document.querySelectorAll('input[name="service[serviceType]"]');
    const frequencyRow = document.querySelector('.frequency-row');
    const frequencyField = document.querySelector('.frequency-field');
    const frequencyInputs = document.querySelectorAll('input[name="service[frequencyWeeks]"]');
    
    // Crear elemento para mostrar el mensaje dinámico
    const frequencyMessage = document.createElement('div');
    frequencyMessage.className = 'alert alert-info mt-2 frequency-message';
    frequencyMessage.style.display = 'none';
    
    // Insertar el mensaje después del campo de frecuencia
    if (frequencyField) {
        frequencyField.appendChild(frequencyMessage);
    }
    
    function toggleFrequencyField() {
        const selectedValue = document.querySelector('input[name="service[serviceType]"]:checked')?.value;
        
        if (selectedValue === 'recurring') {
            if (frequencyRow) frequencyRow.style.display = 'block';
            if (frequencyField) frequencyField.style.display = 'block';
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
            frequencyMessage.style.display = 'block';
        } else if (frequencyMessage) {
            frequencyMessage.style.display = 'none';
        }
    }
    
    // Ejecutar al cargar la página
    toggleFrequencyField();
    
    // Ejecutar cuando cambie la selección del tipo de servicio
    serviceTypeInputs.forEach(input => {
        input.addEventListener('change', toggleFrequencyField);
    });
    
    // Ejecutar cuando cambie la selección de frecuencia
    frequencyInputs.forEach(input => {
        input.addEventListener('change', updateFrequencyMessage);
    });
});


// Función para inicializar el formulario (usada por service-modal.js)
function initializeServiceForm() {
    const serviceTypeInputs = document.querySelectorAll('input[name="service[serviceType]"]');
    const frequencyRow = document.querySelector('.frequency-row');
    const frequencyField = document.querySelector('.frequency-field');
    const frequencyInputs = document.querySelectorAll('input[name="service[frequencyWeeks]"]');
    
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
            if (frequencyRow) frequencyRow.style.display = 'block';
            if (frequencyField) frequencyField.style.display = 'block';
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
            frequencyMessage.style.display = 'block';
        } else if (frequencyMessage) {
            frequencyMessage.style.display = 'none';
        }
    }
    
    // Ejecutar al cargar
    toggleFrequencyField();
    
    // Event listeners
    serviceTypeInputs.forEach(input => {
        input.addEventListener('change', toggleFrequencyField);
    });
    
    frequencyInputs.forEach(input => {
        input.addEventListener('change', updateFrequencyMessage);
    });
}