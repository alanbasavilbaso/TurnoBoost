let agendaManager;
// Agenda Management JavaScript
class AgendaManager {
    constructor() {
        this.calendar = null;
        this.calendarEl = null;
        this.allAppointments = []; // Agregar esta línea
        this.currentFilters = {
            professional: '',
            service: '',
            view: 'timeGridWeek'
        };
    }

    async init() {
        this.calendarEl = document.getElementById('calendar');
        await this.initializeCalendar();
        this.bindEvents();
        await this.loadAppointments();
    }

    async initializeCalendar() {
        // Cargar configuración de horarios desde la base de datos
        const businessHoursConfig = await this.loadBusinessHours();
        
        this.calendar = new FullCalendar.Calendar(this.calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            timeZone: 'UTC',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',
            selectable: true,
            selectMirror: true,
            
            // Configuración específica por vista
            views: {
                dayGridMonth: {
                    dayMaxEvents: false,
                    moreLinkClick: false,
                    selectable: false // Deshabilitar selección en vista mensual
                },
                timeGridWeek: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true // Habilitar selección en vista semanal
                },
                timeGridDay: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true // Habilitar selección en vista diaria
                }
            },
            
            weekends: true,
            editable: true,
            allDaySlot: false,
            
            // Configuraciones generales
            eventConstraint: 'businessHours',
            
            // Ocultar eventos en vista mensual
            eventDidMount: (info) => {
                if (this.calendar.view.type === 'dayGridMonth') {
                    info.el.style.display = 'none';
                }
            },
            
            // Agregar contadores personalizados en vista mensual
            dayCellDidMount: (info) => {
                if (info.view.type === 'dayGridMonth') {
                    // Usar el mismo formato que FullCalendar usa internamente
                    const cellDate = info.date;
                    const cellDateStr = cellDate.toISOString().split('T')[0]; // YYYY-MM-DD
                    
                    // Filtrar eventos del día usando comparación de fechas mejorada
                    const dayEvents = this.calendar.getEvents().filter(event => {
                        // Obtener solo la fecha (sin hora) del evento
                        const eventDate = new Date(event.start);
                        const eventDateStr = eventDate.toISOString().split('T')[0]; // YYYY-MM-DD
                        return eventDateStr === cellDateStr;
                    });
                    
                    if (dayEvents.length > 0) {
                        // Remover contador existente si existe
                        const existingCounter = info.el.querySelector('.turnos-counter');
                        if (existingCounter) {
                            existingCounter.remove();
                        }
                        
                        // Crear nuevo contador
                        const counter = document.createElement('div');
                        counter.className = 'turnos-counter';
                        counter.textContent = `${dayEvents.length} turno${dayEvents.length !== 1 ? 's' : ''}`;
                        counter.style.cssText = `
                            background: #007bff;
                            color: white;
                            padding: 2px 6px;
                            border-radius: 3px;
                            font-size: 11px;
                            margin-top: 2px;
                            cursor: pointer;
                            text-align: center;
                        `;
                        
                        // Agregar evento click para mostrar detalles
                        counter.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this.showDayAppointments(dayEvents);
                        });
                        
                        // Agregar al contenido de la celda
                        const dayContent = info.el.querySelector('.fc-daygrid-day-top');
                        if (dayContent) {
                            dayContent.appendChild(counter);
                        }
                    }
                }
            },
            
            // Configuración mejorada para eventos concurrentes
            eventMaxStack: 9,
            dayMaxEventRows: true,
            
            // Eventos del calendario - Modificar para controlar selección por vista
            select: (info) => {
                // Solo permitir selección si NO estamos en vista mensual
                if (this.calendar.view.type !== 'dayGridMonth') {
                    this.handleDateSelect(info);
                } else {
                    // Deseleccionar si estamos en vista mensual
                    this.calendar.unselect();
                }
            },
            eventClick: (info) => this.handleEventClick(info),
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            
            // Configuración de eventos
            eventDisplay: 'block',
            eventTextColor: '#fff',
            
            // Configuración dinámica de horarios desde la base de datos
            slotMinTime: businessHoursConfig.slotMinTime,
            slotMaxTime: businessHoursConfig.slotMaxTime,
            slotDuration: businessHoursConfig.slotDuration || '00:15:00',
            
            // Configuración dinámica de días laborables desde la base de datos
            businessHours: {
                daysOfWeek: businessHoursConfig.daysOfWeek,
                startTime: businessHoursConfig.startTime,
                endTime: businessHoursConfig.endTime
            }
        });
        
        this.calendar.render();
    }

    async loadBusinessHours() {
        try {
            // Usar los filtros actuales para obtener horarios específicos
            const params = new URLSearchParams();
            
            if (this.currentFilters.professional) {
                params.append('professional', this.currentFilters.professional);
            }
            
            if (this.currentFilters.service) {
                params.append('service', this.currentFilters.service);
            }
            
            const url = `/agenda/business-hours${params.toString() ? '?' + params.toString() : ''}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error('Error al cargar horarios de negocio');
            }
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error loading business hours:', error);
            // Valores por defecto en caso de error
            return {
                daysOfWeek: [1, 2, 3, 4, 5, 6],
                startTime: '08:00',
                endTime: '18:00',
                slotMinTime: '08:00:00',
                slotMaxTime: '18:00:00',
                slotDuration: '00:15:00'
            };
        }
    }

    async updateBusinessHours() {
        const businessHours = await this.loadBusinessHours();
        
        // Actualizar las opciones del calendario
        this.calendar.setOption('slotMinTime', businessHours.slotMinTime);
        this.calendar.setOption('slotMaxTime', businessHours.slotMaxTime);
        this.calendar.setOption('slotDuration', businessHours.slotDuration || '00:15:00');
        this.calendar.setOption('businessHours', {
            daysOfWeek: businessHours.daysOfWeek,
            startTime: businessHours.startTime,
            endTime: businessHours.endTime
        });
        
        // Refrescar la vista del calendario
        this.calendar.render();
    }

    bindEvents() {
        // Filtros - IDs corregidos para coincidir con el template
        const professionalFilter = document.getElementById('professionalFilter');
        if (professionalFilter) {
            professionalFilter.addEventListener('change', async (e) => {
                this.currentFilters.professional = e.target.value;
                await this.updateBusinessHours(); // Actualizar horarios cuando cambie el filtro
                this.loadAppointments();
            });
        }
        
        const serviceFilter = document.getElementById('serviceFilter');
        if (serviceFilter) {
            serviceFilter.addEventListener('change', async (e) => {
                this.currentFilters.service = e.target.value;
                await this.updateBusinessHours(); // Actualizar horarios cuando cambie el filtro
                this.loadAppointments();
            });
        }
        
        // Modal de turno - verificar que existan los elementos
        const saveAppointment = document.getElementById('save-appointment');
        if (saveAppointment) {
            saveAppointment.addEventListener('click', () => {
                this.saveAppointment();
            });
        }
        
        // Búsqueda de pacientes
        const patientSearch = document.getElementById('patient-search');
        if (patientSearch) {
            patientSearch.addEventListener('input', (e) => {
                this.searchPatients(e.target.value);
            });
        }
        
        // Crear nuevo paciente
        const createNewPatient = document.getElementById('create-new-patient');
        if (createNewPatient) {
            createNewPatient.addEventListener('click', () => {
                this.showNewPatientForm();
            });
        }
        
        // Cambio de profesional en modal
        const modalProfessional = document.getElementById('modal-professional');
        if (modalProfessional) {
            modalProfessional.addEventListener('change', (e) => {
                this.loadProfessionalServices(e.target.value);
                this.loadAvailableSlots();
            });
        }
        
        // Cambio de servicio en modal
        const modalService = document.getElementById('modal-service');
        if (modalService) {
            modalService.addEventListener('change', () => {
                this.loadAvailableSlots();
            });
        }
        
        // Cambio de fecha en modal
        const appointmentDate = document.getElementById('appointment-date');
        if (appointmentDate) {
            appointmentDate.addEventListener('change', () => {
                this.loadAvailableSlots();
            });
        }
    }

    async loadAppointments() {
        try {
            this.showLoading(true);
            
            const params = new URLSearchParams();
            if (this.currentFilters.professional) {
                params.append('professional', this.currentFilters.professional);
            }
            if (this.currentFilters.service) {
                params.append('service', this.currentFilters.service);
            }
            
            const response = await fetch(`/agenda/appointments?${params}`);
            const appointments = await response.json();
            
            // Limpiar eventos existentes
            this.calendar.removeAllEvents();
            
            // Agregar nuevos eventos
            appointments.forEach(appointment => {
                this.calendar.addEvent({
                    id: appointment.id,
                    title: appointment.title,
                    start: appointment.start,
                    end: appointment.end,
                    backgroundColor: appointment.backgroundColor,
                    borderColor: appointment.borderColor,
                    extendedProps: appointment.extendedProps
                });
            });
            
            // Si estamos en vista mensual, forzar re-render para actualizar contadores
            if (this.calendar.view.type === 'dayGridMonth') {
                setTimeout(() => {
                    this.calendar.render();
                }, 100);
            }
            
        } catch (error) {
            console.error('Error loading appointments:', error);
            this.showAlert('Error al cargar los turnos', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    handleDateSelect(info) {
        // Extraer fecha y hora del clic en el calendario
        const selectedDate = new Date(info.start);
        const dateStr = selectedDate.toISOString().split('T')[0]; // YYYY-MM-DD
        const timeStr = selectedDate.toTimeString().slice(0, 5); // HH:MM
        
        this.openAppointmentModal({
            date: dateStr,
            time: timeStr,
            isNew: true
        });
        this.calendar.unselect();
    }

    handleEventClick(info) {
        const event = info.event;
        this.showAppointmentDetails({
            id: event.id,
            title: event.title,
            start: event.start,
            end: event.end,
            patientId: event.extendedProps.patientId, // Agregar esta línea
            patientName: event.extendedProps.patientName,
            patientEmail: event.extendedProps.patientEmail || event.extendedProps.email,
            patientPhone: event.extendedProps.phone,
            professionalName: event.extendedProps.professionalName,
            serviceName: event.extendedProps.serviceName,
            status: event.extendedProps.status,
            notes: event.extendedProps.notes,
            professionalId: event.extendedProps.professionalId,
            serviceId: event.extendedProps.serviceId
        });
    }
    
    showAppointmentDetails(eventData) {
        console.log('showAppointmentDetails', eventData);
        // Formatear fecha en formato DD/MM/YYYY
        const startDate = new Date(eventData.start);
        const formattedDate = startDate.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric'
        });
        
        // Formatear hora en UTC (como está guardado en la base de datos)
        const startTimeUTC = startDate.toISOString().substring(11, 16); // HH:MM en UTC
        const endDate = new Date(eventData.end);
        const endTimeUTC = endDate.toISOString().substring(11, 16); // HH:MM en UTC
        
        // Calcular duración
        const durationMs = endDate - startDate;
        const durationMinutes = Math.round(durationMs / (1000 * 60));
        const hours = Math.floor(durationMinutes / 60);
        const minutes = durationMinutes % 60;
        const durationText = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
        
        // Poblar el modal con los datos
        document.getElementById('detailPatientName').textContent = eventData.patientName || 'No especificado';
        document.getElementById('detailPatientEmail').textContent = eventData.patientEmail || 'No especificado';
        document.getElementById('detailPatientPhone').textContent = eventData.patientPhone || 'No especificado';
        
        document.getElementById('detailAppointmentDate').textContent = formattedDate;
        document.getElementById('detailAppointmentTime').textContent = `${startTimeUTC} - ${endTimeUTC}`;
        document.getElementById('detailAppointmentDuration').textContent = durationText;
        
        // Corregido: usar eventData en lugar de appointmentData
        document.getElementById('detailProfessionalName').textContent = eventData.professionalName || 'No especificado';
        document.getElementById('detailServiceName').textContent = eventData.serviceName || 'No especificado';
        
        // Configurar badge de estado
        const statusElement = document.getElementById('detailAppointmentStatus');
        statusElement.textContent = this.getStatusText(eventData.status);
        statusElement.className = `badge ${this.getStatusBadgeClass(eventData.status)}`;
        
        // Mostrar/ocultar notas
        const notesCard = document.getElementById('detailNotesCard');
        const notesElement = document.getElementById('detailAppointmentNotes');
        if (eventData.notes && eventData.notes.trim()) {
            notesElement.textContent = eventData.notes;
            notesCard.style.display = 'block';
        } else {
            notesCard.style.display = 'none';
        }
        
        // Configurar botones de acción (corregido: usar eventData)
        this.setupAppointmentActions(eventData);
        
        // Mostrar el modal
        const modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
        modal.show();
        
        // Configurar acciones de los botones con TODOS los datos
        this.setupAppointmentActions({
            id: eventData.id,
            title: eventData.title,
            start: eventData.start,
            end: eventData.end,
            professionalId: eventData.professionalId,
            serviceId: eventData.serviceId,
            status: eventData.status,
            notes: eventData.notes,
            // Incluir datos del paciente
            patientId: eventData.patientId,
            patientName: eventData.patientName,
            patientEmail: eventData.patientEmail,
            patientPhone: eventData.patientPhone
        });
    }

    setupAppointmentActions(appointmentData) {
        const editBtn = document.getElementById('editAppointmentBtn');
        const completeBtn = document.getElementById('completeAppointmentBtn');
        const cancelBtn = document.getElementById('cancelAppointmentBtn');
        
        // Limpiar event listeners previos
        const newEditBtn = editBtn.cloneNode(true);
        const newCompleteBtn = completeBtn.cloneNode(true);
        const newCancelBtn = cancelBtn.cloneNode(true);
        
        editBtn.parentNode.replaceChild(newEditBtn, editBtn);
        completeBtn.parentNode.replaceChild(newCompleteBtn, completeBtn);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
        
        // Configurar botón de editar
        newEditBtn.addEventListener('click', () => {
            bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal')).hide();
            this.openAppointmentModal(appointmentData);
        });
        
        // Configurar botón de completar
        newCompleteBtn.addEventListener('click', async () => {
            this.showConfirmationModal(
                '¿Está seguro de marcar este turno como completado?',
                'El turno se marcará como finalizado.',
                'fas fa-check-circle text-success',
                'btn-success',
                async () => {
                    await this.updateAppointmentStatus(appointmentData.id, 'completed');
                    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal')).hide();
                }
            );
        });
        
        // Configurar botón de cancelar
        newCancelBtn.addEventListener('click', async () => {
            this.showConfirmationModal(
                '¿Está seguro de cancelar este turno?',
                'Esta acción no se puede deshacer.',
                'fas fa-times-circle text-danger',
                'btn-danger',
                async () => {
                    await this.updateAppointmentStatus(appointmentData.id, 'cancelled');
                    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal')).hide();
                }
            );
        });
        
        // Mostrar/ocultar botones según el estado del turno
        if (appointmentData.status === 'completed') {
            newCompleteBtn.style.display = 'none';
        } else if (appointmentData.status === 'cancelled') {
            newCompleteBtn.style.display = 'none';
            newCancelBtn.style.display = 'none';
        }
    }

    // Nuevo método para mostrar el modal de confirmación
    showConfirmationModal(message, subtext, iconClass, buttonClass, onConfirm) {
        const modal = document.getElementById('confirmationModal');
        const messageEl = document.getElementById('confirmationMessage');
        const subtextEl = document.getElementById('confirmationSubtext');
        const iconEl = document.getElementById('confirmationIcon');
        const confirmBtn = document.getElementById('confirmActionBtn');
        
        // Configurar contenido del modal
        messageEl.textContent = message;
        subtextEl.textContent = subtext;
        iconEl.className = `${iconClass} fa-3x`;
        
        // Configurar botón de confirmación
        confirmBtn.className = `btn ${buttonClass}`;
        
        // Limpiar event listeners previos
        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
        
        // Agregar nuevo event listener
        newConfirmBtn.addEventListener('click', async () => {
            bootstrap.Modal.getInstance(modal).hide();
            if (onConfirm) {
                await onConfirm();
            }
        });
        
        // Mostrar modal
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
    }

    getStatusText(status) {
        const statusMap = {
            'scheduled': 'Programado',
            'confirmed': 'Confirmado',
            'completed': 'Completado',
            'cancelled': 'Cancelado'
        };
        return statusMap[status] || 'Desconocido';
    }

    getStatusBadgeClass(status) {
        const classMap = {
            'scheduled': 'bg-primary',
            'confirmed': 'bg-success',
            'completed': 'bg-secondary',
            'cancelled': 'bg-danger'
        };
        return classMap[status] || 'bg-secondary';
    }

    async updateAppointmentStatus(appointmentId, newStatus) {
        try {
            this.showLoading(true);
            
            const response = await fetch(`/agenda/appointments/${appointmentId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });
            
            if (response.ok) {
                this.showAlert('Estado del turno actualizado correctamente', 'success');
                
                // Cerrar modal y recargar eventos
                bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal')).hide();
                await this.loadAppointments();
            } else {
                throw new Error('Error al actualizar el estado');
            }
        } catch (error) {
            console.error('Error updating appointment status:', error);
            this.showAlert('Error al actualizar el estado del turno', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    openAppointmentModal(data = {}) {
        const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
        
        // Resetear formulario - verificar que exista
        const appointmentForm = document.getElementById('appointment-form');
        if (appointmentForm) {
            appointmentForm.reset();
        }
        
        // Limpiar completamente los campos del paciente
        this.clearPatientFields();
        
        const appointmentId = document.getElementById('appointment-id');
        if (appointmentId) {
            appointmentId.value = data.id || '';
        }
        
        const modalLabel = document.getElementById('appointmentModalLabel');
        if (modalLabel) {
            if (data.isNew) {
                modalLabel.textContent = 'Nuevo Turno';
                const appointmentDate = document.getElementById('appointment-date');
                if (appointmentDate) {
                    appointmentDate.value = data.date;
                }
            } else {
                modalLabel.textContent = 'Editar Turno';
                this.populateModalFields(data);
            }
        }
        
        modal.show();
    }
    
    // Agregar esta nueva función después del método openAppointmentModal
    clearPatientFields() {
    // Limpiar búsqueda de pacientes
    const patientSearch = document.getElementById('patient-search');
    if (patientSearch) {
        patientSearch.value = '';
    }
    
    // Limpiar resultados de búsqueda
    const patientResults = document.getElementById('patient-results');
    if (patientResults) {
        patientResults.innerHTML = '';
    }
    
    // Limpiar paciente seleccionado
    const selectedPatientId = document.getElementById('selected-patient-id');
    if (selectedPatientId) {
        selectedPatientId.value = '';
    }
    
    // Limpiar información del paciente seleccionado
    const selectedPatientInfo = document.getElementById('selected-patient-info');
    if (selectedPatientInfo) {
        selectedPatientInfo.innerHTML = '';
    }
    
    // Ocultar formulario de nuevo paciente
    const newPatientForm = document.getElementById('new-patient-form');
    if (newPatientForm) {
        newPatientForm.style.display = 'none';
    }
    
    // Limpiar campos del formulario de nuevo paciente
    const patientNameField = document.getElementById('patient-name');
    if (patientNameField) {
        patientNameField.value = '';
    }
    
    const patientEmailField = document.getElementById('patient-email');
    if (patientEmailField) {
        patientEmailField.value = '';
    }
    
    const patientPhoneField = document.getElementById('patient-phone');
    if (patientPhoneField) {
        patientPhoneField.value = '';
    }
    
    const patientBirthDateField = document.getElementById('patient-birth-date');
    if (patientBirthDateField) {
        patientBirthDateField.value = '';
    }
    }

    async populateModalFields(data) {
        document.getElementById('modal-professional').value = data.professionalId;
        // NO establecer el servicio aquí todavía
        document.getElementById('appointment-date').value = data.start.toISOString().split('T')[0];
        
        // Extraer y establecer la hora
        const timeStr = data.start.toTimeString().slice(0, 5); // HH:MM
        const appointmentTime = document.getElementById('appointment-time');
        if (appointmentTime) {
            // Crear opción temporal con la hora del turno existente
            appointmentTime.innerHTML = '';
            const option = document.createElement('option');
            option.value = timeStr;
            option.textContent = timeStr;
            option.selected = true;
            appointmentTime.appendChild(option);
        }
        
        document.getElementById('appointment-notes').value = data.notes || '';
        
        // Cargar servicios del profesional y esperar a que termine
        if (data.professionalId) {
            await this.loadProfessionalServices(data.professionalId, data.serviceId);
        }
        
        // Buscar y seleccionar paciente
        if (data.patientId && data.patientName) {
            this.selectPatient(data.patientId, data.patientName, data.patientEmail, data.patientPhone);
        }
        
        // Ahora cargar horarios disponibles - ya no necesitamos setTimeout
        if (data.professionalId && data.serviceId && data.start) {
            this.loadAvailableSlots();
        }
    }

    async loadProfessionalServices(professionalId, selectedServiceId = null) {
        if (!professionalId) return;
        
        try {
            const response = await fetch(`/agenda/professional/${professionalId}/services`);
            const services = await response.json();
            
            const serviceSelect = document.getElementById('modal-service');
            serviceSelect.innerHTML = '<option value="">Seleccionar servicio...</option>';
            
            services.forEach(service => {
                const option = document.createElement('option');
                option.value = service.id;
                option.textContent = `${service.name} (${service.duration}min - $${service.price})`;
                
                // Seleccionar el servicio si coincide con el selectedServiceId
                if (selectedServiceId && service.id == selectedServiceId) {
                    option.selected = true;
                }
                
                serviceSelect.appendChild(option);
            });
            
            // Si hay un servicio seleccionado y una fecha, cargar horarios disponibles
            if (selectedServiceId && document.getElementById('appointment-date').value) {
                this.loadAvailableSlots();
            }
            
        } catch (error) {
            console.error('Error loading professional services:', error);
        }
    }

    async loadAvailableSlots() {
        const professionalId = document.getElementById('modal-professional').value;
        const serviceId = document.getElementById('modal-service').value;
        const date = document.getElementById('appointment-date').value;
        
        if (!professionalId || !serviceId || !date) return;
        
        try {
            const response = await fetch(`/agenda/available-slots?professional=${professionalId}&service=${serviceId}&date=${date}`);
            const slots = await response.json();
            
            const timeSelect = document.getElementById('appointment-time');
            timeSelect.innerHTML = '<option value="">Seleccionar horario...</option>';
            
            slots.forEach(slot => {
                if (slot.available) { // Solo mostrar slots disponibles
                    const option = document.createElement('option');
                    option.value = slot.datetime; // Usar datetime para el value
                    option.textContent = `${slot.time} - ${slot.end_time}`; // Usar time y end_time
                    timeSelect.appendChild(option);
                }
            });
            
        } catch (error) {
            console.error('Error loading available slots:', error);
        }
    }

    async searchPatients(query) {
        if (query.length < 2) {
            document.getElementById('patient-results').innerHTML = '';
            return;
        }
        
        try {
            const response = await fetch(`/agenda/search-patients?q=${encodeURIComponent(query)}`);
            const patients = await response.json();
            
            this.displayPatientResults(patients);
            
        } catch (error) {
            console.error('Error searching patients:', error);
        }
    }

    displayPatientResults(patients) {
        const resultsContainer = document.getElementById('patient-results');
        resultsContainer.innerHTML = '';
        
        patients.forEach(patient => {
            const patientDiv = document.createElement('div');
            patientDiv.className = 'patient-result p-2 border-bottom cursor-pointer';
            patientDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${patient.name}</strong><br>
                        <small class="text-muted">${patient.email} - ${patient.phone}</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="agendaManager.selectPatient(${patient.id}, '${patient.name}', '${patient.email}', '${patient.phone}')">
                        Seleccionar
                    </button>
                </div>
            `;
            resultsContainer.appendChild(patientDiv);
        });
    }

    selectPatient(patientId, patientName, patientEmail, patientPhone) {
        document.getElementById('selected-patient-id').value = patientId;
        document.getElementById('patient-search').value = '';
        document.getElementById('patient-results').innerHTML = '';
        document.getElementById('new-patient-form').style.display = 'none';
        
        // Mostrar información del paciente seleccionado usando los datos que ya tenemos
        this.displaySelectedPatientInfo(patientId, patientName, patientEmail, patientPhone);
    }

    // Reemplazar displaySelectedPatient con esta nueva función
    displaySelectedPatientInfo(patientId, patientName, patientEmail, patientPhone) {
        document.getElementById('selected-patient-info').innerHTML = `
            <div class="alert alert-info">
                <strong>Paciente seleccionado:</strong> ${patientName}<br>
                <small>${patientEmail} - ${patientPhone}</small>
            </div>
        `;
    }

    showNewPatientForm() {
        document.getElementById('new-patient-form').style.display = 'block';
        document.getElementById('patient-results').innerHTML = '';
    }

    async saveAppointment() {
        const formData = new FormData(document.getElementById('appointment-form'));
        const appointmentId = document.getElementById('appointment-id').value;
        
        try {
            this.showLoading(true);
            
            if (appointmentId) {
                await this.updateAppointment(appointmentId, formData);
            } else {
                const response = await fetch('/agenda/appointment', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.showAlert('Turno creado correctamente', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
                    this.loadAppointments();
                } else {
                    // MEJORADO: Mostrar el error específico del servidor
                    const errorMessage = result.error || result.message || 'Error al crear el turno';
                    this.showAlert(errorMessage, 'error');
                }
            }
            
        } catch (error) {
            console.error('Error saving appointment:', error);
            this.showAlert('Error de conexión al guardar el turno', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    async updateAppointment(appointmentId, formData) {
        // Convertir FormData a objeto JSON
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        const response = await fetch(`/agenda/appointment/${appointmentId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            this.showAlert('Turno actualizado correctamente', 'success');
            bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
            this.loadAppointments();
        } else {
            this.showAlert(result.message || 'Error al actualizar el turno', 'error');
        }
    }

    async updateAppointmentTime(appointmentId, timeData) {
        const response = await fetch(`/agenda/appointment/${appointmentId}/time`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(timeData)
        });
        
        if (!response.ok) {
            throw new Error('Error updating appointment time');
        }
        
        return response.json();
    }

    changeCalendarView(view) {
        const viewMap = {
            'month': 'dayGridMonth',
            'week': 'timeGridWeek',
            'day': 'timeGridDay'
        };
        
        this.currentFilters.view = view;
        
        if (viewMap[view]) {
            this.calendar.changeView(viewMap[view]);
        } else {
            this.calendar.changeView(view);
        }
        
        // Recargar appointments cuando cambie la vista
        this.loadAppointments();
    }

    showLoading(show) {
        const loadingEl = document.getElementById('loading-spinner');
        if (loadingEl) {
            loadingEl.style.display = show ? 'block' : 'none';
        }
    }

    showAlert(message, type = 'info') {
        // Crear alerta temporal
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    // En lugar de solo colores por estado, usar colores por profesional
    getProfessionalColor(professionalId) {
        const colors = [
            '#3498db', '#e74c3c', '#2ecc71', '#f39c12', 
            '#9b59b6', '#1abc9c', '#34495e', '#e67e22'
        ];
        return colors[professionalId % colors.length];
    }

    // Combinar color de profesional con intensidad por estado
    getEventColor(appointment) {
        const baseColor = this.getProfessionalColor(appointment.professionalId);
        const opacity = appointment.status === 'confirmed' ? '1' : '0.7';
        return baseColor + Math.floor(255 * opacity).toString(16);
    }
    
    // Agregar un panel lateral con resumen del día
    showDaySummary(date) {
        const dayAppointments = this.getAppointmentsForDate(date);
        const summary = {
            total: dayAppointments.length,
            byProfessional: {},
            byStatus: {},
            nextAppointment: this.getNextAppointment(dayAppointments)
        };
        
        // Mostrar en panel lateral
        this.updateSummaryPanel(summary);
    }
    
    showDayAppointments(dayEvents) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Turnos del día</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            ${dayEvents.map(event => `
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">${event.title}</h6>
                                         <small>${event.start.toLocaleTimeString('es-ES', {
                                            hour: '2-digit', 
                                            minute: '2-digit',
                                            timeZone: 'UTC'
                                        })}</small>
                                    </div>
                                    <p class="mb-1">${event.extendedProps?.service || ''}</p>
                                    <small>${event.extendedProps?.professional || ''}</small>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', async function() {
    agendaManager = new AgendaManager();
    await agendaManager.init();
});

