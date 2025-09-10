class LocationFormManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupScheduleHandlers();
        this.loadExistingSchedules();
        this.setMondayDefault();
    }

    setupScheduleHandlers() {
        // Manejar habilitación/deshabilitación de horarios
        const checkboxes = document.querySelectorAll('.day-toggle');
        console.log('Found checkboxes:', checkboxes.length); // Debug
        
        checkboxes.forEach(checkbox => {
            const day = checkbox.dataset.day;
            const timeInputs = document.querySelectorAll(`[data-day="${day}"].time-input`);
            
            // Estado inicial
            this.updateTimeInputs(checkbox, timeInputs);
            
            // Remover event listeners existentes para evitar duplicados
            checkbox.removeEventListener('change', this.handleCheckboxChange);
            
            // Evento de cambio
            checkbox.addEventListener('change', (e) => {
                console.log('Checkbox changed for day:', day, 'checked:', e.target.checked);
                this.updateTimeInputs(checkbox, timeInputs);
                if (e.target.checked) {
                    this.setDefaultSchedule(parseInt(day));
                }
            });
        });
    }

    updateTimeInputs(checkbox, timeInputs) {
        if (checkbox.checked) {
            timeInputs.forEach(input => {
                input.disabled = false;
                input.required = true;
            });
            
            // Valores por defecto si están vacíos
            const day = checkbox.dataset.day;
            const startHour = document.querySelector(`[name="location[day_${day}_start_hour]"]`);
            const startMinute = document.querySelector(`[name="location[day_${day}_start_minute]"]`);
            const endHour = document.querySelector(`[name="location[day_${day}_end_hour]"]`);
            const endMinute = document.querySelector(`[name="location[day_${day}_end_minute]"]`);
            
            if (startHour && !startHour.value) startHour.value = '09';
            if (startMinute && !startMinute.value) startMinute.value = '00';
            if (endHour && !endHour.value) endHour.value = '20';
            if (endMinute && !endMinute.value) endMinute.value = '00';
        } else {
            timeInputs.forEach(input => {
                input.disabled = true;
                input.required = false;
                input.value = '';
            });
        }
    }

    loadExistingSchedules() {
        // Solo cargar si existen datos y estamos en modo edición
        if (typeof window.locationExistingSchedules !== 'undefined') {
            Object.keys(window.locationExistingSchedules).forEach(day => {
                const schedule = window.locationExistingSchedules[day];
                const checkbox = document.querySelector(`input[name="location[day_${day}_enabled]"]`);
                
                if (checkbox) {
                    checkbox.checked = true;
                    
                    // Establecer valores de tiempo
                    const startHour = document.querySelector(`select[name="location[day_${day}_start_hour]"]`);
                    const startMinute = document.querySelector(`select[name="location[day_${day}_start_minute]"]`);
                    const endHour = document.querySelector(`select[name="location[day_${day}_end_hour]"]`);
                    const endMinute = document.querySelector(`select[name="location[day_${day}_end_minute]"]`);
                    
                    if (startHour) startHour.value = schedule.startHour;
                    if (startMinute) startMinute.value = schedule.startMinute;
                    if (endHour) endHour.value = schedule.endHour;
                    if (endMinute) endMinute.value = schedule.endMinute;
                }
            });
        }
    }

    reinitialize() {
        console.log('Reinitializing LocationFormManager'); // Debug
        this.init();
    }

    setMondayDefault() {
        // Solo en modo "new" (cuando no hay datos existentes)
        const isNewForm = typeof window.locationExistingSchedules === 'undefined' || Object.keys(window.locationExistingSchedules).length === 0;
        if (isNewForm) {
            const mondayCheckbox = document.querySelector('input[name="location[day_0_enabled]"]');
            if (mondayCheckbox && !mondayCheckbox.checked) {
                mondayCheckbox.checked = true;
                mondayCheckbox.dispatchEvent(new Event('change'));
            }
        }
    }

    setDefaultSchedule(day) {
        const startHourSelect = document.querySelector(`select[name="location[day_${day}_start_hour]"]`);
        const startMinuteSelect = document.querySelector(`select[name="location[day_${day}_start_minute]"]`);
        const endHourSelect = document.querySelector(`select[name="location[day_${day}_end_hour]"]`);
        const endMinuteSelect = document.querySelector(`select[name="location[day_${day}_end_minute]"]`);
        
        if (startHourSelect && !startHourSelect.value) {
            startHourSelect.value = '09';
        }
        if (startMinuteSelect && !startMinuteSelect.value) {
            startMinuteSelect.value = '00';
        }
        if (endHourSelect && !endHourSelect.value) {
            endHourSelect.value = '20';
        }
        if (endMinuteSelect && !endMinuteSelect.value) {
            endMinuteSelect.value = '00';
        }
    }
}

// Instancia global para poder acceder desde el modal
window.locationFormManager = null;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Solo inicializar si estamos en una página que no usa modal
    if (document.querySelector('.day-toggle')) {
        window.locationFormManager = new LocationFormManager();
    }
});

// Función para reinicializar cuando se carga contenido dinámico en el modal
window.initializeLocationForm = function() {
    console.log('initializeLocationForm called'); // Debug
    // Esperar un poco para que el DOM se renderice completamente
    setTimeout(() => {
        if (window.locationFormManager) {
            window.locationFormManager.reinitialize();
        } else {
            window.locationFormManager = new LocationFormManager();
        }
    }, 100);
};


// Validación antes de enviar el formulario
function validateScheduleForm() {
    let isValid = true;
    
    for (let day = 0; day <= 6; day++) {
        const enabled = document.querySelector(`input[name="location[day_${day}_enabled]"]`);
        
        if (enabled && enabled.checked) {
            const startHour = document.querySelector(`select[name="location[day_${day}_start_hour]"]`);
            const startMinute = document.querySelector(`select[name="location[day_${day}_start_minute]"]`);
            const endHour = document.querySelector(`select[name="location[day_${day}_end_hour]"]`);
            const endMinute = document.querySelector(`select[name="location[day_${day}_end_minute]"]`);
            
            if (!startHour.value || !startMinute.value || !endHour.value || !endMinute.value) {
                alert(`Por favor complete todos los horarios para el día seleccionado`);
                isValid = false;
                break;
            }
        }
    }
    
    return isValid;
}

// Agregar al evento submit del formulario
document.addEventListener('DOMContentLoaded', function() {
    console.log('*************');
    const form = document.querySelector('form[name="location"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateScheduleForm()) {
                e.preventDefault();
            }
        });
    }
});


// Función para establecer horarios por defecto
function setDefaultSchedule(day) {
    const startHourSelect = document.querySelector(`select[name="location[day_${day}_start_hour]"]`);
    const startMinuteSelect = document.querySelector(`select[name="location[day_${day}_start_minute]"]`);
    const endHourSelect = document.querySelector(`select[name="location[day_${day}_end_hour]"]`);
    const endMinuteSelect = document.querySelector(`select[name="location[day_${day}_end_minute]"]`);
    
    // Solo establecer valores por defecto si los campos están vacíos
    if (startHourSelect && !startHourSelect.value) {
        startHourSelect.value = '09';
    }
    if (startMinuteSelect && !startMinuteSelect.value) {
        startMinuteSelect.value = '00';
    }
    if (endHourSelect && !endHourSelect.value) {
        endHourSelect.value = '20';
    }
    if (endMinuteSelect && !endMinuteSelect.value) {
        endMinuteSelect.value = '00';
    }
}