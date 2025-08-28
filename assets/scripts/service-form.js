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
        
        // Cambiar de '2' a 'recurring'
        if (selectedValue === '1') { // ServiceTypeEnum::RECURRING tiene valor 'recurring'
            frequencyRow.style.display = 'block';
            frequencyField.style.display = 'block';
        } else {
            frequencyRow.style.display = 'none';
            frequencyField.style.display = 'none';
            // Limpiar selección de frecuencia
            frequencyInputs.forEach(input => {
                input.checked = false;
            });
            frequencyMessage.style.display = 'none';
        }
    }
    
    function updateFrequencyMessage() {
        const selectedFrequency = document.querySelector('input[name="service[frequencyWeeks]"]:checked')?.value;
        
        if (selectedFrequency) {
            const weeks = parseInt(selectedFrequency);
            let message;
            
            if (weeks === 1) {
                message = 'Los turnos se repetirían cada semana';
            } else {
                message = `Los turnos se repetirían cada ${weeks} semanas`;
            }
            
            frequencyMessage.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
            frequencyMessage.style.display = 'block';
        } else {
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