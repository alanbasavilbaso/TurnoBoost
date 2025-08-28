// Manejo de horarios de disponibilidad
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM fully loaded and parsed');
    
    // Función para obtener el siguiente slot de tiempo disponible
    function getNextTimeSlot(timeValue) {
        if (!timeValue) return '00:00';
        
        const [hours, minutes] = timeValue.split(':').map(Number);
        let nextMinutes = minutes + 15;
        let nextHours = hours;
        
        if (nextMinutes >= 60) {
            nextMinutes = 0;
            nextHours += 1;
        }
        
        if (nextHours >= 24) {
            return '23:45'; // Máximo permitido
        }
        
        return `${nextHours.toString().padStart(2, '0')}:${nextMinutes.toString().padStart(2, '0')}`;
    }
    
    // Función para validar que no haya superposición entre rangos
    function validateTimeRanges(dayNumber) {
        const dayContainer = document.querySelector(`.availability-ranges[data-day="${dayNumber}"]`);
        if (!dayContainer) return;
        
        const range1Start = dayContainer.querySelector('[data-range="1"] select[id*="_start"]');
        const range1End = dayContainer.querySelector('[data-range="1"] select[id*="_end"]');
        const range2Start = dayContainer.querySelector('[data-range="2"] select[id*="_start"]');
        const range2End = dayContainer.querySelector('[data-range="2"] select[id*="_end"]');
        
        if (!range1Start || !range1End || !range2Start || !range2End) return;
        
        // Función para filtrar opciones del segundo rango
        function updateRange2Options() {
            const range1EndValue = range1End.value;
            
            if (range1EndValue) {
                const minStartTime = getNextTimeSlot(range1EndValue);
                
                // Filtrar opciones del select de inicio del rango 2
                Array.from(range2Start.options).forEach(option => {
                    if (option.value && option.value <= range1EndValue) {
                        option.disabled = true;
                        option.style.color = '#ccc';
                    } else {
                        option.disabled = false;
                        option.style.color = '';
                    }
                });
                
                // Si el valor actual del rango 2 start es inválido, cambiarlo
                if (range2Start.value && range2Start.value <= range1EndValue) {
                    range2Start.value = minStartTime;
                }
                
                // Actualizar opciones del select de fin del rango 2
                updateRange2EndOptions();
            }
        }
        
        function updateRange2EndOptions() {
            const range2StartValue = range2Start.value;
            
            if (range2StartValue) {
                // Filtrar opciones del select de fin del rango 2
                Array.from(range2End.options).forEach(option => {
                    if (option.value && option.value <= range2StartValue) {
                        option.disabled = true;
                        option.style.color = '#ccc';
                    } else {
                        option.disabled = false;
                        option.style.color = '';
                    }
                });
                
                // Si el valor actual del rango 2 end es inválido, limpiarlo
                if (range2End.value && range2End.value <= range2StartValue) {
                    range2End.value = '';
                }
            }
        }
        
        // Event listeners para validación en tiempo real
        range1End.addEventListener('change', updateRange2Options);
        range2Start.addEventListener('change', updateRange2EndOptions);
        
        // Validación inicial
        updateRange2Options();
    }
    
    // Función para manejar la habilitación/deshabilitación de días
    function setupDayToggle() {
        console.log('🚀 Iniciando setupDayToggle...');
        const dayCheckboxes = document.querySelectorAll('input[id*="availability_"][id*="_enabled"]');
        console.log('📋 Checkboxes encontrados:', dayCheckboxes.length);
        
        dayCheckboxes.forEach(checkbox => {
            const dayMatch = checkbox.id.match(/availability_(\d+)_enabled/);
            if (dayMatch) {
                const dayNumber = dayMatch[1];
                
                // Función simplificada para toggle de campos de tiempo
                function toggleTimeFields(isInitialSetup = false) {
                    console.log(`🔄 toggleTimeFields ejecutado para día ${dayNumber}`);
                    const isEnabled = checkbox.checked;
                    const dayContainer = document.querySelector(`.availability-ranges[data-day="${dayNumber}"]`);
                    
                    if (dayContainer) {
                        // Obtener todos los selects de tiempo para este día
                        const timeSelects = dayContainer.querySelectorAll('select.time-select');
                        console.log(`⏰ Selects de tiempo encontrados para día ${dayNumber}:`, timeSelects.length);
                        
                        timeSelects.forEach(select => {
                            if (isEnabled) {
                                // Habilitar select
                                select.disabled = false;
                                select.style.backgroundColor = '';
                                select.style.opacity = '';
                            } else {
                                // Deshabilitar select
                                select.disabled = true;
                                if (!isInitialSetup) {
                                    select.value = '';
                                }
                                select.style.backgroundColor = '#f8f9fa';
                                select.style.opacity = '0.6';
                            }
                        });
                        
                        // Habilitar/deshabilitar botones
                        const addBtn = dayContainer.querySelector('.add-range-btn');
                        const removeBtn = dayContainer.querySelector('.remove-range-btn');
                        
                        if (addBtn) {
                            addBtn.disabled = !isEnabled;
                            addBtn.style.opacity = isEnabled ? '' : '0.6';
                        }
                        if (removeBtn) {
                            removeBtn.disabled = !isEnabled;
                            removeBtn.style.opacity = isEnabled ? '' : '0.6';
                        }
                        
                        // Si se deshabilita, ocultar el segundo rango
                        if (!isEnabled) {
                            const range2 = dayContainer.querySelector('[data-range="2"]');
                            if (range2 && range2.style.display === 'block') {
                                range2.style.display = 'none';
                                if (addBtn) addBtn.style.display = 'inline-block';
                            }
                        } else if (isInitialSetup && isEnabled) {
                            // NUEVA LÓGICA: Verificar si el segundo rango tiene datos al inicializar
                            const range2 = dayContainer.querySelector('[data-range="2"]');
                            if (range2) {
                                const range2Start = range2.querySelector('select[id*="_start"]');
                                const range2End = range2.querySelector('select[id*="_end"]');
                                
                                // Si cualquiera de los selects del rango 2 tiene valor, mostrar el rango
                                if ((range2Start && range2Start.value) || (range2End && range2End.value)) {
                                    range2.style.display = 'block';
                                    if (addBtn) addBtn.style.display = 'none';
                                    console.log(`📅 Rango 2 mostrado automáticamente para día ${dayNumber} (tiene datos cargados)`);
                                } else {
                                    range2.style.display = 'none';
                                    if (addBtn) addBtn.style.display = 'inline-block';
                                }
                            }
                        }
                        
                        // Configurar validación de rangos si está habilitado
                        if (isEnabled) {
                            validateTimeRanges(dayNumber);
                        }
                    }
                }
                
                // Configurar estado inicial
                toggleTimeFields(true);
                
                // Agregar event listener para cambios
                checkbox.addEventListener('change', function() {
                    toggleTimeFields(false);
                });
            }
        });
    }
    
    setupDayToggle();
    
    // Manejo de botones de agregar/quitar rangos
    document.querySelectorAll('.add-range-btn').forEach(button => {
        button.addEventListener('click', function() {
            const day = this.dataset.day;
            const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
            const dayContainer = document.querySelector(`.availability-ranges[data-day="${day}"]`);
            
            if (range2 && dayContainer) {
                // Mostrar el segundo rango
                range2.style.display = 'block';
                this.style.display = 'none';
                
                // Auto-configurar el inicio del segundo rango
                const range1End = dayContainer.querySelector('[data-range="1"] select[id*="_end"]');
                const range2Start = dayContainer.querySelector('[data-range="2"] select[id*="_start"]');
                
                if (range1End && range1End.value && range2Start) {
                    const suggestedStartTime = getNextTimeSlot(range1End.value);
                    range2Start.value = suggestedStartTime;
                }
                
                // Configurar validación para este día
                validateTimeRanges(day);
            }
        });
    });
    
    document.querySelectorAll('.remove-range-btn').forEach(button => {
        button.addEventListener('click', function() {
            const day = this.dataset.day;
            const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
            const addBtn = document.querySelector(`[data-day="${day}"] .add-range-btn`);
            
            if (range2) {
                range2.style.display = 'none';
                // Limpiar valores de los selects
                const selects = range2.querySelectorAll('select');
                selects.forEach(select => {
                    select.value = '';
                    // Restaurar todas las opciones
                    Array.from(select.options).forEach(option => {
                        option.disabled = false;
                        option.style.color = '';
                    });
                });
            }
            
            if (addBtn) {
                addBtn.style.display = 'inline-block';
            }
        });
    });
});

// JavaScript específico para el formulario de profesionales
$(document).ready(function() {
    console.log('Inicializando Select2...');
    
    // Verificar si Select2 está disponible y si el elemento existe
    if (typeof $.fn.select2 !== 'undefined' && $('#services-select').length > 0) {
        // Destruir instancia previa si existe
        if ($('#services-select').hasClass('select2-hidden-accessible')) {
            try {
                $('#services-select').select2('destroy');
            } catch (e) {
                console.warn('Error al destruir Select2:', e);
            }
        }
        // Inicializar Select2 con configuración simple
        $('#services-select').select2({
            theme: 'default', // o 'classic', o quitar esta línea para el tema por defecto
            placeholder: 'Buscar y seleccionar servicios...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return "No se encontraron servicios";
                },
                searching: function() {
                    return "Buscando servicios...";
                }
            }
        });
        
        // Manejar cambios y sincronizar con el formulario original
        $('#services-select').on('change', function() {
            syncWithOriginalForm();
        });
        
    } else {
        console.error('Select2 no está disponible o el elemento #services-select no existe');
    }
    
    function syncWithOriginalForm() {
        var selectedValues = $('#services-select').val() || [];
        console.log('Valores seleccionados:', selectedValues);
        
        // Remover todos los campos previos de servicios
        $('input[name^="professional[services]"]').remove();
        
        // Crear un campo hidden individual para cada servicio seleccionado
        selectedValues.forEach(function(value) {
            var hiddenInput = $('<input type="hidden" name="professional[services][]" value="' + value + '">');
            $('#services-select').closest('form').append(hiddenInput);
        });
        
        console.log('Campos hidden creados:', selectedValues.length, 'servicios');
    }
});

// Para SPAs - reinicializar en navegación
document.addEventListener('turbo:load', function() {
    if (typeof $ !== 'undefined' && $('#services-select').length) {
        $(document).ready();
    }
});