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
    
    // NUEVA FUNCIÓN: Validar que hora fin > hora inicio para un rango específico
    function validateSingleRange(startSelect, endSelect) {
        if (!startSelect || !endSelect) return;
        
        function updateEndOptions() {
            const startValue = startSelect.value;
            
            if (startValue) {
                // Filtrar opciones del select de fin
                Array.from(endSelect.options).forEach(option => {
                    if (option.value && option.value <= startValue) {
                        option.disabled = true;
                        option.style.color = '#ccc';
                        option.style.backgroundColor = '#f8f9fa';
                    } else {
                        option.disabled = false;
                        option.style.color = '';
                        option.style.backgroundColor = '';
                    }
                });
                
                // Si el valor actual del fin es inválido, limpiarlo y mostrar mensaje
                if (endSelect.value && endSelect.value <= startValue) {
                    endSelect.value = '';
                    showTimeValidationMessage(endSelect, 'La hora de fin debe ser posterior a la hora de inicio');
                } else {
                    hideTimeValidationMessage(endSelect);
                }
            } else {
                // Si no hay hora de inicio, habilitar todas las opciones de fin
                Array.from(endSelect.options).forEach(option => {
                    option.disabled = false;
                    option.style.color = '';
                    option.style.backgroundColor = '';
                });
                hideTimeValidationMessage(endSelect);
            }
        }
        
        function validateEndTime() {
            const startValue = startSelect.value;
            const endValue = endSelect.value;
            
            if (startValue && endValue && endValue <= startValue) {
                endSelect.value = '';
                showTimeValidationMessage(endSelect, 'La hora de fin debe ser posterior a la hora de inicio');
                return false;
            } else {
                hideTimeValidationMessage(endSelect);
                return true;
            }
        }
        
        // Event listeners
        startSelect.addEventListener('change', updateEndOptions);
        endSelect.addEventListener('change', validateEndTime);
        
        // Validación inicial
        updateEndOptions();
    }
    
    // NUEVA FUNCIÓN: Mostrar mensaje de validación
    function showTimeValidationMessage(element, message) {
        // Remover mensaje previo si existe
        hideTimeValidationMessage(element);
        
        // Crear mensaje de error
        const errorDiv = document.createElement('div');
        errorDiv.className = 'time-validation-error';
        errorDiv.style.cssText = `
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 4px;
            padding: 4px 8px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            display: block;
        `;
        errorDiv.textContent = message;
        
        // Insertar después del select
        element.parentNode.insertBefore(errorDiv, element.nextSibling);
        
        // Agregar estilo de error al select
        element.style.borderColor = '#dc3545';
        element.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
    }
    
    // NUEVA FUNCIÓN: Ocultar mensaje de validación
    function hideTimeValidationMessage(element) {
        const errorDiv = element.parentNode.querySelector('.time-validation-error');
        if (errorDiv) {
            errorDiv.remove();
        }
        
        // Remover estilo de error del select
        element.style.borderColor = '';
        element.style.boxShadow = '';
    }
    
    // Función para validar que no haya superposición entre rangos
    function validateTimeRanges(dayNumber) {
        const dayContainer = document.querySelector(`.availability-ranges[data-day="${dayNumber}"]`);
        if (!dayContainer) return;
        
        const range1Start = dayContainer.querySelector('[data-range="1"] select[id*="_start"]');
        const range1End = dayContainer.querySelector('[data-range="1"] select[id*="_end"]');
        const range2Start = dayContainer.querySelector('[data-range="2"] select[id*="_start"]');
        const range2End = dayContainer.querySelector('[data-range="2"] select[id*="_end"]');
        
        // NUEVA VALIDACIÓN: Configurar validación individual para cada rango
        if (range1Start && range1End) {
            validateSingleRange(range1Start, range1End);
        }
        
        if (range2Start && range2End) {
            validateSingleRange(range2Start, range2End);
        }
        
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
                    showTimeValidationMessage(range2Start, 'El segundo rango debe comenzar después del primer rango');
                } else {
                    hideTimeValidationMessage(range2Start);
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
                    showTimeValidationMessage(range2End, 'La hora de fin debe ser posterior a la hora de inicio');
                } else {
                    hideTimeValidationMessage(range2End);
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
                            if (range2 && !range2.classList.contains('hide')) {
                                range2.classList.add('hide');
                                if (addBtn) addBtn.classList.remove('hide');
                            }
                        } else if (isInitialSetup && isEnabled) {
                            // NUEVA LÓGICA: Verificar si el segundo rango tiene datos al inicializar
                            const range2 = dayContainer.querySelector('[data-range="2"]');
                            if (range2) {
                                const range2Start = range2.querySelector('select[id*="_start"]');
                                const range2End = range2.querySelector('select[id*="_end"]');
                                
                                // Si cualquiera de los selects del rango 2 tiene valor, mostrar el rango
                                if ((range2Start && range2Start.value) || (range2End && range2End.value)) {
                                    range2.classList.remove('hide');
                                    if (addBtn) addBtn.classList.add('hide');
                                    console.log(`📅 Rango 2 mostrado automáticamente para día ${dayNumber} (tiene datos cargados)`);
                                } else {
                                    range2.classList.add('hide');
                                    if (addBtn) addBtn.classList.remove('hide');
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
                // Mostrar el segundo rango removiendo la clase hide
                range2.classList.remove('hide');
                this.classList.add('hide');
                
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
                range2.classList.add('hide');
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
                addBtn.classList.remove('hide');
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