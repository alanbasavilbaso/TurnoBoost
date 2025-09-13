class SpecialSchedulesManager {
    constructor() {
        this.modal = null;
        this.formModal = null;
        this.currentProfessionalId = null;
        this.locationSchedules = window.locationSchedules || {};
        this.eventsInitialized = false; // Bandera para evitar múltiples inicializaciones
        this.initializeEventListeners();
        this.initializeFormModal();
    }

    initializeEventListeners() {
        // Listener para botones de jornadas especiales (ver lista)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.special-hours-btn')) {
                const btn = e.target.closest('.special-hours-btn');
                const professionalId = btn.dataset.professionalId;
                this.openSpecialSchedulesModal(professionalId);
            }
            
            // Listener para botón "Habilitar jornada especial" (agregar directamente)
            if (e.target.closest('.enable-special-hours-btn')) {
                const btn = e.target.closest('.enable-special-hours-btn');
                const professionalId = btn.dataset.professionalId;
                this.currentProfessionalId = professionalId;
                this.showSpecialScheduleForm();
            }
        });
    }

    initializeFormModal() {
        // Buscar el modal existente en el DOM (debe estar en el template)
        const modalElement = document.getElementById('specialScheduleFormModal');
        if (modalElement) {
            this.formModal = new bootstrap.Modal(modalElement);
            this.initializeFormEvents();
        } else {
            console.error('Modal specialScheduleFormModal no encontrado en el DOM');
        }
    }

    // Modificar el método generateHourOptions para aceptar parámetros de filtro
    generateHourOptions(minHour = 0, maxHour = 23, includeEmptyOption = true) {
        let options = '';
        
        // Solo incluir opción vacía si se especifica y no hay filtros específicos
        if (includeEmptyOption && minHour === 0 && maxHour === 23) {
            options = '<option value="">Seleccionar hora</option>';
        }
        
        for (let hour = minHour; hour <= maxHour; hour++) {
            const hourStr = hour.toString().padStart(2, '0');
            options += `<option value="${hourStr}">${hourStr}</option>`;
        }
        return options;
    }

    generateMinuteOptions() {
        const minutes = [0, 15, 30, 45];
        let options = '';
        minutes.forEach(minute => {
            const min = minute.toString().padStart(2, '0');
            options += `<option value="${min}">${min}</option>`;
        });
        return options;
    }

    getLocationHours() {
        if (window.globalLocationHours) {
            return {
                minHour: window.globalLocationHours.minHour,
                maxHour: window.globalLocationHours.maxHour
            };
        }
        
        return { minHour: 8, maxHour: 18 };
    }

    // Método para actualizar las opciones de horas (simplificado)
    updateHourOptions() {
        const locationHours = this.getLocationHours();
        if (locationHours) {
            // Actualizar opciones de hora desde
            const startTimeHour = document.getElementById('startTimeHour');
            if (startTimeHour) {
                startTimeHour.innerHTML = this.generateHourOptions(locationHours.minHour, locationHours.maxHour, false);
                // Establecer valor por defecto
                startTimeHour.value = locationHours.minHour.toString().padStart(2, '0');
            }
            
            // Actualizar opciones de hora hasta
            const endTimeHour = document.getElementById('endTimeHour');
            if (endTimeHour) {
                endTimeHour.innerHTML = this.generateHourOptions(locationHours.minHour, locationHours.maxHour, false);
                // Establecer valor por defecto
                endTimeHour.value = locationHours.maxHour.toString().padStart(2, '0');
            }
        }
    }

    initializeFormEvents() {
        // Solo inicializar una vez
        if (this.eventsInitialized) {
            return;
        }

        // Event delegation para el formulario - usar document para capturar todos los eventos
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'specialScheduleFormElement') {
                this.handleFormSubmit(e);
            }
        });

        document.addEventListener('change', (e) => {
            if (e.target.id === 'date') {
                if (e.target.value) {
                    this.updateHourOptions();
                }
            }
            
            if (e.target.id === 'selectAllServices') {
                this.toggleAllServices();
            }
        });

        document.addEventListener('click', (e) => {
            if (e.target.id === 'toggleAdvancedConfig') {
                this.toggleAdvancedConfig();
            }
        });

        document.addEventListener('hidden.bs.modal', (e) => {
            if (e.target.id === 'specialScheduleFormModal') {
                if (this.modal && this.currentProfessionalId) {
                    this.openSpecialSchedulesModal(this.currentProfessionalId);
                }
            }
        });

        // Marcar como inicializado
        this.eventsInitialized = true;
    }

    // NUEVO: Toggle configuración avanzada
    toggleAdvancedConfig() {
        console.log('+++++');
        const advancedConfig = document.getElementById('advancedServiceConfig');
        const button = document.getElementById('toggleAdvancedConfig');
        const servicesSection = document.getElementById('servicesSection');
        
        if (advancedConfig.classList.contains('d-none')) {
            advancedConfig.classList.remove('d-none');
            servicesSection.classList.remove('d-none');
            button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Ocultar Configuración';
        } else {
            advancedConfig.classList.add('d-none');
            servicesSection.classList.add('d-none');
            button.innerHTML = '<i class="fas fa-cog me-1"></i>Configuración Avanzada';
        }
    }

    // NUEVO: Seleccionar/deseleccionar todos los servicios
    toggleAllServices() {
        const selectAllCheckbox = document.getElementById('selectAllServices');
        const serviceCheckboxes = document.querySelectorAll('#servicesContainer input[type="checkbox"]');
        
        serviceCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    }

    // NUEVO: Cargar servicios del profesional
    async loadProfessionalServices() {
        const professionalId = this.currentProfessionalId;
        if (!professionalId) return;
        
        try {
            const response = await fetch(`/profesionales/${professionalId}/services`);
            if (!response.ok) {
                throw new Error('Error al cargar los servicios');
            }
            
            const services = await response.json();
            this.renderServices(services);
            
        } catch (error) {
            console.error('Error loading services:', error);
            this.showNotification('Error al cargar los servicios', 'error');
        }
    }

    // NUEVO: Renderizar servicios en el formulario
    renderServices(services) {
        const container = document.getElementById('servicesContainer');
        container.innerHTML = '';
        
        services.forEach(service => {
            const serviceHtml = `
                <div class="col-md-6 mb-2">
                    <div class="form-check">
                        <input class="form-check-input service-checkbox" 
                               type="checkbox" 
                               id="service_${service.id}" 
                               name="services[]" 
                               value="${service.id}" 
                               checked>
                        <label class="form-check-label" for="service_${service.id}">
                            ${service.name}
                            <small class="text-muted d-block">${service.duration} min - $${service.price}</small>
                        </label>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', serviceHtml);
        });
        
        // Actualizar el checkbox "Seleccionar todos" cuando cambie algún servicio
        document.querySelectorAll('.service-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => this.updateSelectAllCheckbox());
        });
    }

    // NUEVO: Actualizar estado del checkbox "Seleccionar todos"
    updateSelectAllCheckbox() {
        const allCheckboxes = document.querySelectorAll('.service-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.service-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAllServices');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
        }
    }

    // NUEVO: Obtener servicios seleccionados
    getSelectedServices() {
        const checkedBoxes = document.querySelectorAll('.service-checkbox:checked');
        return Array.from(checkedBoxes).map(checkbox => parseInt(checkbox.value));
    }

    // NUEVO: Mostrar notificaciones
    showNotification(message, type = 'info') {
        // Implementar sistema de notificaciones o usar alert como fallback
        if (window.showNotification) {
            window.showNotification(message, type);
        } else {
            alert(message);
        }
    }

    async openSpecialSchedulesModal(professionalId) {
        this.currentProfessionalId = professionalId;
        
        try {
            const response = await fetch(`/profesionales/${professionalId}/special-schedules`);
            if (!response.ok) {
                throw new Error('Error al cargar las jornadas especiales');
            }
            
            const html = await response.text();
            
            // Crear o actualizar modal
            this.createModal(html);
            
            // Mostrar modal
            this.modal.show();
            
            // Inicializar eventos del modal
            this.initializeModalEvents();
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cargar las jornadas especiales');
        }
    }

    createModal(content) {
        // Limpiar modal existente completamente
        const existingModal = document.getElementById('specialSchedulesModal');
        if (existingModal) {
            // Si hay una instancia de Bootstrap Modal, destruirla primero
            const modalInstance = bootstrap.Modal.getInstance(existingModal);
            if (modalInstance) {
                modalInstance.dispose();
            }
            
            // Remover el elemento del modal
            existingModal.remove();
            
            // Limpiar cualquier backdrop residual
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.remove();
            });
            
            // Restaurar el estado del body
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        }

        // Crear nuevo modal
        const modalHtml = `
            <div class="modal fade" id="specialSchedulesModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.modal = new bootstrap.Modal(document.getElementById('specialSchedulesModal'));
        
        // Agregar event listener para limpieza automática al cerrar
        const modalElement = document.getElementById('specialSchedulesModal');
        modalElement.addEventListener('hidden.bs.modal', () => {
            // Limpiar cualquier backdrop que pueda quedar
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                    backdrop.remove();
                });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
                document.body.style.removeProperty('overflow');
            }, 100);
        }, { once: true });
    }

    initializeModalEvents() {
        const modalElement = document.getElementById('specialSchedulesModal');
        
        // Inicializar modal del formulario
        this.initializeFormModal();
        
        // NO llamar initializeFormEvents() aquí - ya se inicializó una vez
        
        const addBtn = modalElement.querySelector('#addSpecialScheduleBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                this.modal.hide();
                this.showSpecialScheduleForm();
            });
        }

        // Botones de eliminar
        modalElement.querySelectorAll('.delete-special-schedule').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const scheduleId = e.target.closest('.delete-special-schedule').dataset.scheduleId;
                this.deleteSpecialSchedule(scheduleId);
            });
        });
    }

    async showSpecialScheduleForm() {
        if (this.formModal) {
            // Limpiar formulario
            this.resetForm();
            
            // Establecer el ID del profesional
            document.getElementById('professionalId').value = this.currentProfessionalId;
            
            // Mostrar modal
            this.formModal.show();
            
            // Actualizar opciones de hora
            this.updateHourOptions();

            // Cargar servicios del profesional inmediatamente
            await this.loadProfessionalServices();
        }
    }

    resetForm() {
        const form = document.getElementById('specialScheduleFormElement');
        if (form) {
            form.reset();
            
            // Limpiar servicios
            const servicesContainer = document.getElementById('servicesContainer');
            if (servicesContainer) {
                servicesContainer.innerHTML = '';
            }
            
            // Ocultar configuración avanzada
            const advancedConfig = document.getElementById('advancedServiceConfig');
            if (advancedConfig) {
                advancedConfig.classList.add('d-none');
            }
            
            const servicesSection = document.getElementById('servicesSection');
            if (servicesSection) {
                servicesSection.classList.add('d-none');
            }
            
            // Resetear texto del botón
            const toggleBtn = document.getElementById('toggleAdvancedConfig');
            if (toggleBtn) {
                toggleBtn.innerHTML = '<i class="fas fa-cog me-1"></i>Configuración Avanzada';
            }
        }
    }

    async deleteSpecialSchedule(scheduleId) {
        if (!confirm('¿Está seguro de que desea eliminar esta jornada especial?')) {
            return;
        }
        
        try {
            const response = await fetch(`/profesionales/special-schedules/${scheduleId}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                this.showNotification('Jornada especial eliminada exitosamente', 'success');
                // Recargar la lista
                this.openSpecialSchedulesModal(this.currentProfessionalId);
            } else {
                this.showNotification('Error al eliminar la jornada especial', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error al eliminar la jornada especial', 'error');
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        
        const professionalId = document.getElementById('professionalId').value;
        
        // Obtener valores del formulario
        const date = document.getElementById('date').value;
        const startTimeHour = document.getElementById('startTimeHour').value;
        const startTimeMinute = document.getElementById('startTimeMinute').value;
        const endTimeHour = document.getElementById('endTimeHour').value;
        const endTimeMinute = document.getElementById('endTimeMinute').value;
        
        // Combinar fecha y hora para crear los campos que espera el controlador
        const startTime = `${date} ${startTimeHour}:${startTimeMinute}:00`;
        const endTime = `${date} ${endTimeHour}:${endTimeMinute}:00`;
        
        // Crear objeto con los datos en el formato esperado por el controlador
        const requestData = {
            date: date,
            startTime: startTime,
            endTime: endTime
        };
        
        // Siempre agregar servicios seleccionados (independientemente de si la sección está visible)
        const selectedServices = this.getSelectedServices();
        if (selectedServices.length > 0) {
            requestData.services = selectedServices;
        }
        
        try {
            const response = await fetch(`/profesionales/${professionalId}/special-schedules`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            });
            
            if (response.ok) {
                this.formModal.hide();
                this.showNotification('Jornada especial creada exitosamente', 'success');
            } else {
                const errorData = await response.json();
                this.showNotification(errorData.message || 'Error al crear la jornada especial', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Error al crear la jornada especial', 'error');
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new SpecialSchedulesManager();
});
