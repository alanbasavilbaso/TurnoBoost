// ===== CONFIGURACIÓN GLOBAL =====
let servicesConfiguration = {};

// ===== UTILIDADES =====
const Utils = {
    getNextTimeSlot(timeValue) {
        if (!timeValue) return '00:00';
        const [hours, minutes] = timeValue.split(':').map(Number);
        let nextMinutes = minutes + 15;
        let nextHours = hours;
        
        if (nextMinutes >= 60) {
            nextMinutes = 0;
            nextHours += 1;
        }
        
        return nextHours >= 24 ? '23:45' : 
            `${nextHours.toString().padStart(2, '0')}:${nextMinutes.toString().padStart(2, '0')}`;
    },

    getDefaultAvailabilityDays() {
        const defaultDays = {
            availableMonday: false,
            availableTuesday: false,
            availableWednesday: false,
            availableThursday: false,
            availableFriday: false,
            availableSaturday: false,
            availableSunday: false
        };
        
        // Leer los días habilitados desde los checkboxes de disponibilidad
        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
        
        dayKeys.forEach((dayKey, index) => {
            const availabilityCheckbox = document.querySelector(`input[id*="availability_${index}_enabled"]`);
            if (availabilityCheckbox && availabilityCheckbox.checked) {
                defaultDays[dayKey] = true;
            }
        });
        
        return defaultDays;
    },

    // Nueva función para sincronizar servicios con disponibilidad
    syncAllServicesWithAvailability() {
        const currentAvailability = this.getDefaultAvailabilityDays();
        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
        
        // Actualizar todos los servicios configurados
        Object.keys(servicesConfiguration).forEach(serviceId => {
            dayKeys.forEach(dayKey => {
                servicesConfiguration[serviceId][dayKey] = currentAvailability[dayKey];
            });
        });
        // Actualizar la visualización y sincronizar con el formulario
        if (typeof ServiceManager !== 'undefined') {
            ServiceManager.updateDisplay();
            ServiceManager.syncWithOriginalFormHidden();
        }
        
        console.log('Servicios sincronizados con disponibilidad del profesional');
    },

    showValidationMessage(element, message) {
        this.hideValidationMessage(element);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'time-validation-error';
        errorDiv.style.cssText = `
            color: #dc3545; font-size: 0.75rem; margin-top: 4px; padding: 4px 8px;
            background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;
        `;
        errorDiv.textContent = message;
        
        element.parentNode.insertBefore(errorDiv, element.nextSibling);
        element.style.borderColor = '#dc3545';
        element.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
    },

    hideValidationMessage(element) {
        const errorDiv = element.parentNode.querySelector('.time-validation-error');
        if (errorDiv) errorDiv.remove();
        
        element.style.borderColor = '';
        element.style.boxShadow = '';
    }
};

// ===== VALIDACIÓN DE HORARIOS =====
const TimeValidation = {
    validateSingleRange(startSelect, endSelect) {
        if (!startSelect || !endSelect) return;
        
        const updateEndOptions = () => {
            const startValue = startSelect.value;
            
            Array.from(endSelect.options).forEach(option => {
                const isInvalid = option.value && option.value <= startValue;
                option.disabled = isInvalid;
                option.style.color = isInvalid ? '#ccc' : '';
                option.style.backgroundColor = isInvalid ? '#f8f9fa' : '';
            });
            
            if (endSelect.value && endSelect.value <= startValue) {
                endSelect.value = '';
                Utils.showValidationMessage(endSelect, 'La hora de fin debe ser posterior a la hora de inicio');
            } else {
                Utils.hideValidationMessage(endSelect);
            }
        };
        
        const validateEndTime = () => {
            const startValue = startSelect.value;
            const endValue = endSelect.value;
            
            if (startValue && endValue && endValue <= startValue) {
                endSelect.value = '';
                Utils.showValidationMessage(endSelect, 'La hora de fin debe ser posterior a la hora de inicio');
                return false;
            }
            Utils.hideValidationMessage(endSelect);
            return true;
        };
        
        startSelect.addEventListener('change', updateEndOptions);
        endSelect.addEventListener('change', validateEndTime);
        updateEndOptions();
    },

    validateTimeRanges(dayNumber) {
        const dayContainer = document.querySelector(`.availability-ranges[data-day="${dayNumber}"]`);
        if (!dayContainer) return;
        
        const selectors = {
            range1Start: '[data-range="1"] select[id*="_start"]',
            range1End: '[data-range="1"] select[id*="_end"]',
            range2Start: '[data-range="2"] select[id*="_start"]',
            range2End: '[data-range="2"] select[id*="_end"]'
        };
        
        const elements = Object.fromEntries(
            Object.entries(selectors).map(([key, selector]) => 
                [key, dayContainer.querySelector(selector)]
            )
        );
        
        // Validar rangos individuales
        if (elements.range1Start && elements.range1End) {
            this.validateSingleRange(elements.range1Start, elements.range1End);
        }
        
        if (elements.range2Start && elements.range2End) {
            this.validateSingleRange(elements.range2Start, elements.range2End);
        }
        
        // Validar no superposición entre rangos
        if (Object.values(elements).every(el => el)) {
            const updateRange2Options = () => {
                const range1EndValue = elements.range1End.value;
                
                if (range1EndValue) {
                    const minStartTime = Utils.getNextTimeSlot(range1EndValue);
                    
                    Array.from(elements.range2Start.options).forEach(option => {
                        const isInvalid = option.value && option.value <= range1EndValue;
                        option.disabled = isInvalid;
                        option.style.color = isInvalid ? '#ccc' : '';
                    });
                    
                    if (elements.range2Start.value && elements.range2Start.value <= range1EndValue) {
                        elements.range2Start.value = minStartTime;
                        Utils.showValidationMessage(elements.range2Start, 'El segundo rango debe comenzar después del primer rango');
                    } else {
                        Utils.hideValidationMessage(elements.range2Start);
                    }
                }
            };
            
            elements.range1End.addEventListener('change', updateRange2Options);
            updateRange2Options();
        }
    }
};

// ===== MANEJO DE DISPONIBILIDAD =====
const AvailabilityManager = {
    setupDayToggle() {
        const dayCheckboxes = document.querySelectorAll('input[id*="availability_"][id*="_enabled"]');
        
        dayCheckboxes.forEach(checkbox => {
            const dayMatch = checkbox.id.match(/availability_(\d+)_enabled/);
            if (!dayMatch) return;
            
            const dayNumber = dayMatch[1];
            
            const toggleTimeFields = (isInitialSetup = false) => {
                const isEnabled = checkbox.checked;
                const dayContainer = document.querySelector(`.availability-ranges[data-day="${dayNumber}"]`);
                
                if (!dayContainer) return;
                
                // Toggle selects de tiempo
                dayContainer.querySelectorAll('select.time-select').forEach(select => {
                    select.disabled = !isEnabled;
                    if (!isEnabled && !isInitialSetup) {
                        select.value = '';
                    } else if (isEnabled && !isInitialSetup && !select.value) {
                        // AGREGAR: Establecer valores por defecto cuando se habilita un día nuevo
                        if (select.id.includes('_range1_start')) {
                            select.value = '09:00';
                        } else if (select.id.includes('_range1_end')) {
                            select.value = '18:00';
                        }
                    }
                    select.style.backgroundColor = isEnabled ? '' : '#f8f9fa';
                    select.style.opacity = isEnabled ? '' : '0.6';
                });
                
                // Toggle botones
                ['add-range-btn', 'remove-range-btn'].forEach(btnClass => {
                    const btn = dayContainer.querySelector(`.${btnClass}`);
                    if (btn) {
                        btn.disabled = !isEnabled;
                        btn.style.opacity = isEnabled ? '' : '0.6';
                    }
                });
                
                // Manejar segundo rango
                const range2 = dayContainer.querySelector('[data-range="2"]');
                const addBtn = dayContainer.querySelector('.add-range-btn');
                
                if (!isEnabled) {
                    if (range2 && !range2.classList.contains('hide')) {
                        range2.classList.add('hide');
                        if (addBtn) addBtn.classList.remove('hide');
                    }
                } else if (isInitialSetup && range2) {
                    const hasData = ['_start', '_end'].some(suffix => {
                        const select = range2.querySelector(`select[id*="${suffix}"]`);
                        return select && select.value;
                    });
                    
                    range2.classList.toggle('hide', !hasData);
                    if (addBtn) addBtn.classList.toggle('hide', hasData);
                }
                
                if (isEnabled) TimeValidation.validateTimeRanges(dayNumber);
                
                // NUEVA FUNCIONALIDAD: Sincronizar servicios automáticamente
                if (typeof Utils.syncAllServicesWithAvailability === 'function') {
                    // Usar setTimeout para asegurar que el cambio se procese primero
                    Promise.resolve().then(() => {
                        Utils.syncAllServicesWithAvailability();
                    });
                }
            };
            
            toggleTimeFields(true);
            checkbox.addEventListener('change', () => toggleTimeFields(false));
        });
    },

    setupRangeButtons() {
        // Botones agregar rango
        document.querySelectorAll('.add-range-btn').forEach(button => {
            button.addEventListener('click', function() {
                const day = this.dataset.day;
                const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
                const dayContainer = document.querySelector(`.availability-ranges[data-day="${day}"]`);
                
                if (range2 && dayContainer) {
                    range2.classList.remove('hide');
                    this.classList.add('hide');
                    
                    // Auto-configurar inicio del segundo rango
                    const range1End = dayContainer.querySelector('[data-range="1"] select[id*="_end"]');
                    const range2Start = dayContainer.querySelector('[data-range="2"] select[id*="_start"]');
                    
                    if (range1End?.value && range2Start) {
                        range2Start.value = Utils.getNextTimeSlot(range1End.value);
                    }
                    
                    TimeValidation.validateTimeRanges(day);
                }
            });
        });
        
        // Botones quitar rango
        document.querySelectorAll('.remove-range-btn').forEach(button => {
            button.addEventListener('click', function() {
                const day = this.dataset.day;
                const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
                const addBtn = document.querySelector(`[data-day="${day}"] .add-range-btn`);
                
                if (range2) {
                    range2.classList.add('hide');
                    range2.querySelectorAll('select').forEach(select => {
                        select.value = '';
                        Array.from(select.options).forEach(option => {
                            option.disabled = false;
                            option.style.color = '';
                        });
                    });
                }
                
                if (addBtn) addBtn.classList.remove('hide');
            });
        });
    }
};

// ===== MANEJO DE SERVICIOS =====
const ServiceManager = {
    initNativeSelect() {
        const servicesSelect = document.getElementById('services-select');
        if (!servicesSelect) {
            console.warn('Elemento #services-select no encontrado. El modal puede no estar cargado aún.');
            return;
        }

        // Crear contenedor personalizado para el select
        const selectContainer = document.createElement('div');
        selectContainer.className = 'custom-select-container';
        selectContainer.style.cssText = `
            position: relative;
            width: 100%;
        `;

        // Crear input de búsqueda
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control';
        searchInput.placeholder = 'Buscar y agregar servicios...';
        searchInput.style.cssText = `
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        `;

        // Crear dropdown de opciones
        const dropdown = document.createElement('div');
        dropdown.className = 'custom-select-dropdown';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        `;

        // Obtener todas las opciones del select original
        const options = Array.from(servicesSelect.options).filter(option => option.value);
        
        // Función para mostrar opciones filtradas
        const showFilteredOptions = (searchTerm = '') => {
            dropdown.innerHTML = '';
            
            const filteredOptions = options.filter(option => 
                option.text.toLowerCase().includes(searchTerm.toLowerCase())
            );

            if (filteredOptions.length === 0) {
                const noResults = document.createElement('div');
                noResults.className = 'custom-select-option';
                noResults.style.cssText = `
                    padding: 8px 12px;
                    color: #6c757d;
                    font-style: italic;
                `;
                noResults.textContent = 'No se encontraron servicios';
                dropdown.appendChild(noResults);
            } else {
                filteredOptions.forEach(option => {
                    const optionDiv = document.createElement('div');
                    optionDiv.className = 'custom-select-option';
                    optionDiv.style.cssText = `
                        padding: 8px 12px;
                        cursor: pointer;
                        border-bottom: 1px solid #f8f9fa;
                        transition: background-color 0.2s;
                    `;
                    
                    // Verificar si el servicio ya está agregado
                    const serviceId = option.value;
                    const isAdded = servicesConfiguration[serviceId];
                    
                    if (isAdded) {
                        optionDiv.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">${option.text}</span>
                                <small class="badge bg-success">Ya agregado</small>
                            </div>
                        `;
                        optionDiv.style.backgroundColor = '#f8f9fa';
                    } else {
                        optionDiv.textContent = option.text;
                        
                        // Hover effect
                        optionDiv.addEventListener('mouseenter', () => {
                            optionDiv.style.backgroundColor = '#e9ecef';
                        });
                        optionDiv.addEventListener('mouseleave', () => {
                            optionDiv.style.backgroundColor = '';
                        });
                        
                        // Click handler
                        optionDiv.addEventListener('click', () => {
                            const serviceId = option.value;
                            const serviceName = option.dataset.name || option.text;
                            const serviceDuration = option.dataset.duration;
                            const servicePrice = option.dataset.price;
                            
                            ServiceManager.addService(serviceId, serviceName, serviceDuration, servicePrice);
                            
                            // Limpiar búsqueda y ocultar dropdown
                            searchInput.value = '';
                            dropdown.style.display = 'none';
                            
                            // Actualizar opciones para mostrar "Ya agregado"
                            showFilteredOptions();
                        });
                    }
                    
                    dropdown.appendChild(optionDiv);
                });
            }
            
            dropdown.style.display = 'block';
        };

        // Event listeners
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value;
            showFilteredOptions(searchTerm);
        });

        searchInput.addEventListener('focus', () => {
            showFilteredOptions(searchInput.value);
        });

        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!selectContainer.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Ensamblar el componente
        selectContainer.appendChild(searchInput);
        selectContainer.appendChild(dropdown);
        
        // Reemplazar el select original
        servicesSelect.style.display = 'none';
        servicesSelect.parentNode.insertBefore(selectContainer, servicesSelect.nextSibling);
        
        console.log('Select nativo inicializado correctamente');
    },
    
    addService(serviceId, serviceName, serviceDuration, servicePrice) {
        // Verificar si el servicio ya está agregado
        if (servicesConfiguration[serviceId]) {
            console.warn(`El servicio ${serviceName} ya está agregado`);
            return;
        }
        
        // Agregar el servicio a la configuración
        servicesConfiguration[serviceId] = {
            id: serviceId,
            name: serviceName,
            customDurationMinutes: serviceDuration || null,
            customPrice: servicePrice || null,
            ...Utils.getDefaultAvailabilityDays()
        };
        
        // Actualizar la visualización
        this.updateDisplay();
        this.syncWithOriginalFormHidden();
        
        console.log(`Servicio agregado: ${serviceName}`);
    },

    loadExistingConfigs() {
        // Leer desde input hidden en lugar de variable global
        const hiddenInput = document.getElementById('existing-service-configs');
        if (!hiddenInput || !hiddenInput.value) return;
        
        let existingServiceConfigs;
        try {
            existingServiceConfigs = JSON.parse(hiddenInput.value);
        } catch (e) {
            console.error('Error parsing existing service configs:', e);
            return;
        }
        
        const dayMapping = {
            0: 'availableMonday', 1: 'availableTuesday', 2: 'availableWednesday',
            3: 'availableThursday', 4: 'availableFriday', 5: 'availableSaturday', 6: 'availableSunday'
        };
        
        Object.entries(existingServiceConfigs).forEach(([serviceId, config]) => {
            const processedConfig = {
                id: config.id || serviceId,
                name: config.name || 'Servicio ' + serviceId,
                customDurationMinutes: config.customDurationMinutes || null,
                customPrice: config.customPrice || null,
                ...Object.fromEntries(Object.values(dayMapping).map(day => [day, false]))
            };
            
            if (config.days && Array.isArray(config.days)) {
                config.days.forEach(dayIndex => {
                    if (dayMapping[dayIndex]) {
                        processedConfig[dayMapping[dayIndex]] = true;
                    }
                });
            }
            
            servicesConfiguration[serviceId] = processedConfig;
        });
        
        this.updateDisplay();
        this.syncWithOriginalFormHidden();
    },

    updateDisplay() {
        const container = document.getElementById('services-config-container');
        if (!container) return;
        
        if (Object.keys(servicesConfiguration).length === 0) {
            container.innerHTML = '<p class="text-muted">No hay servicios configurados</p>';
            return;
        }
        
        container.innerHTML = Object.entries(servicesConfiguration).map(([serviceId, config]) => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="card-title">${config.name || 'Servicio ' + serviceId}</h6>
                            <p class="card-text small text-muted">
                                Duración: ${config.customDurationMinutes || 'Por defecto'} min | 
                                Precio: $${config.customPrice || 'Por defecto'}
                            </p>
                            <div class="availability-days">
                                <small class="text-muted">Días disponibles:</small>
                                <div class="mt-1">
                                    ${['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map((day, index) => {
                                        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
                                        const isAvailable = config[dayKeys[index]] === true;
                                        return `<span class="badge ${isAvailable ? 'bg-success' : 'bg-secondary'} me-1">${day}</span>`;
                                    }).join('')}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="ServiceManager.configureServiceDays('${serviceId}')" title="Configurar días disponibles">
                                <i class="fas fa-cog"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="ServiceManager.removeService('${serviceId}')" title="Eliminar servicio">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    syncWithOriginalForm() {
        const servicesSelect = document.getElementById('services-select');
        if (!servicesSelect) return;
        
        const selectedValue = servicesSelect.value;
        if (selectedValue && !servicesConfiguration[selectedValue]) {
            const selectedOption = servicesSelect.querySelector(`option[value="${selectedValue}"]`);
            if (selectedOption) {
                servicesConfiguration[selectedValue] = {
                    id: selectedValue,
                    name: selectedOption.textContent.trim(),
                    customDurationMinutes: null,
                    customPrice: null,
                    ...Utils.getDefaultAvailabilityDays()
                };
                
                this.updateDisplay();
                this.syncWithOriginalFormHidden();
                
                servicesSelect.value = '';
                if (typeof $.fn.select2 !== 'undefined') {
                    $(servicesSelect).val(null).trigger('change');
                }
            }
        }
    },

    syncWithOriginalFormHidden() {
        // Sincronizar select de servicios
        const servicesField = document.querySelector('select[id*="services"]');
        if (servicesField) {
            Array.from(servicesField.options).forEach(option => {
                option.selected = Object.keys(servicesConfiguration).includes(option.value);
            });
            
            if (typeof $.fn.select2 !== 'undefined') {
                $(servicesField).trigger('change');
            }
        }
        
        // Crear inputs hidden
        const hiddenContainer = document.getElementById('service-hidden-inputs');
        if (hiddenContainer) {
            hiddenContainer.innerHTML = '';
            
            Object.entries(servicesConfiguration).forEach(([serviceId, config]) => {
                const inputs = [
                    { name: `service_configs[${serviceId}][id]`, value: serviceId },
                    ...(config.customDurationMinutes ? [{ name: `service_configs[${serviceId}][duration]`, value: config.customDurationMinutes }] : []),
                    ...(config.customPrice ? [{ name: `service_configs[${serviceId}][price]`, value: config.customPrice }] : [])
                ];
                
                // Agregar días disponibles
                const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
                dayKeys.forEach((dayKey, index) => {
                    if (config[dayKey] === true) {
                        inputs.push({ name: `service_configs[${serviceId}][days][]`, value: index });
                    }
                });
                
                inputs.forEach(({ name, value }) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    hiddenContainer.appendChild(input);
                });
            });
        }
    },

    removeService(serviceId) {
        if (servicesConfiguration[serviceId]) {
            delete servicesConfiguration[serviceId];
            this.updateDisplay();
            this.syncWithOriginalFormHidden();
            
            // Disparar evento para notificar al wizard
            const event = new CustomEvent('servicesUpdated', {
                detail: { services: servicesConfiguration }
            });
            document.dispatchEvent(event);
        }
    },

    configureServiceDays(serviceId) {
        const config = servicesConfiguration[serviceId];
        if (!config) {
            console.error('Service configuration not found for ID:', serviceId);
            return;
        }
        
        document.getElementById('current-service-name').textContent = config.name;
        
        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
        dayKeys.forEach((dayKey, index) => {
            const checkbox = document.getElementById(`service-day-${index}`);
            const availabilityCheckbox = document.querySelector(`input[id*="availability_${index}_enabled"]`);
            
            if (checkbox) {
                // Verificar si el día está habilitado en la disponibilidad general
                const isDayAvailable = availabilityCheckbox && availabilityCheckbox.checked;
                
                // Configurar el estado del checkbox
                checkbox.checked = config[dayKey] && isDayAvailable;
                checkbox.disabled = !isDayAvailable;
                
                // Aplicar estilos visuales
                const checkboxContainer = checkbox.closest('.form-check');
                const label = checkboxContainer?.querySelector('label');
                
                if (checkboxContainer && label) {
                    if (!isDayAvailable) {
                        checkboxContainer.classList.add('text-muted');
                        label.style.opacity = '0.5';
                        label.style.cursor = 'not-allowed';
                    } else {
                        checkboxContainer.classList.remove('text-muted');
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                }
            }
        });
        
        document.getElementById('serviceDaysModal').setAttribute('data-current-service-id', serviceId);
        new bootstrap.Modal(document.getElementById('serviceDaysModal')).show();
    },

    copyAvailabilityDays() {
        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
        
        dayKeys.forEach((dayKey, index) => {
            const availabilityCheckbox = document.querySelector(`input[id*="availability_${index}_enabled"]`);
            const serviceCheckbox = document.getElementById(`service-day-${index}`);
            
            if (availabilityCheckbox && serviceCheckbox) {
                const isDayAvailable = availabilityCheckbox.checked;
                
                // Solo marcar si el día está disponible
                serviceCheckbox.checked = isDayAvailable;
                serviceCheckbox.disabled = !isDayAvailable;
                
                // Aplicar estilos visuales
                const checkboxContainer = serviceCheckbox.closest('.form-check');
                const label = checkboxContainer?.querySelector('label');
                
                if (checkboxContainer && label) {
                    if (!isDayAvailable) {
                        checkboxContainer.classList.add('text-muted');
                        label.style.opacity = '0.5';
                        label.style.cursor = 'not-allowed';
                    } else {
                        checkboxContainer.classList.remove('text-muted');
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                }
            }
        });
    },

    saveServiceDays() {
        const modal = document.getElementById('serviceDaysModal');
        const serviceId = modal.getAttribute('data-current-service-id');
        
        if (!serviceId || !servicesConfiguration[serviceId]) {
            console.error('No service ID found or service not configured');
            return;
        }
        
        const dayKeys = ['availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday', 'availableSunday'];
        
        dayKeys.forEach((dayKey, index) => {
            const checkbox = document.getElementById(`service-day-${index}`);
            if (checkbox && !checkbox.disabled) {
                // Solo guardar el estado si el checkbox no está deshabilitado
                servicesConfiguration[serviceId][dayKey] = checkbox.checked;
            } else {
                // Si está deshabilitado, asegurar que esté en false
                servicesConfiguration[serviceId][dayKey] = false;
            }
        });
        
        this.updateDisplay();
        this.syncWithOriginalFormHidden();
        
        // Disparar evento para notificar al wizard
        const event = new CustomEvent('servicesUpdated', {
            detail: { services: servicesConfiguration }
        });
        document.dispatchEvent(event);
        
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) modalInstance.hide();
    }
};

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    AvailabilityManager.setupDayToggle();
    AvailabilityManager.setupRangeButtons();
});

// Al final del archivo, modifica la sección de inicialización:
$(document).ready(function() {
    // Event listeners para modal de servicios
    $('#copy-availability-btn').on('click', () => ServiceManager.copyAvailabilityDays());
    $('#save-service-days').on('click', () => ServiceManager.saveServiceDays());
});

// Escuchar cuando se abra el modal de profesionales
// $(document).on('shown.bs.modal', '.modal', function() {
//     // Verificar si este modal contiene el formulario de profesionales
//     if ($(this).find('#services-select').length) {
//         ServiceManager.reinitialize();
//     }
// });

// Reinicializar en SPAs
// document.addEventListener('turbo:load', function() {
//     if (typeof $ !== 'undefined' && $('#services-select').length) {
//         ServiceManager.reinitialize();
//     }
// });

// Exponer funciones globales
window.ServiceManager = ServiceManager;
window.removeService = (serviceId) => ServiceManager.removeService(serviceId);
window.configureServiceDays = (serviceId) => ServiceManager.configureServiceDays(serviceId);
window.copyAvailabilityDays = () => ServiceManager.copyAvailabilityDays();
window.saveServiceDays = () => ServiceManager.saveServiceDays();

// Función de inicialización para el modal de profesionales
function initializeProfessionalForm() {
    console.log('Initializing professional form in modal...');
    
    // Inicializar WizardManager
    if (typeof WizardManager !== 'undefined') {
        window.wizardManager = new WizardManager();
    }
    
    // Inicializar ServiceManager cuando el formulario se carga en el modal
    if (document.getElementById('services-select')) {
        ServiceManager.initNativeSelect();
        ServiceManager.loadExistingConfigs();
    }
    
    // Reinicializar AvailabilityManager
    AvailabilityManager.setupDayToggle();
    AvailabilityManager.setupRangeButtons();
}

// Exponer la función globalmente
window.initializeProfessionalForm = initializeProfessionalForm;