// Agenda Management JavaScript
class AgendaManager {
    constructor() {
        this.calendar = null;
        this.currentFilters = {
            professional: '',
            service: '',
            view: 'timeGridWeek'
        };
        this.init();
    }

    init() {
        this.initializeCalendar();
        this.bindEvents();
        this.loadAppointments();
    }

    initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            timeZone: 'UTC', // Agregar esta línea para manejar tiempos UTC
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekends: true,
            editable: true,
            allDaySlot: false,
            
            // Eventos del calendario
            select: (info) => this.handleDateSelect(info),
            eventClick: (info) => this.handleEventClick(info),
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            
            // Configuración de eventos
            eventDisplay: 'block',
            eventTextColor: '#fff',
            
            // Configuración de horarios
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            slotDuration: '00:15:00',
            
            // Configuración de días laborables
            businessHours: {
                daysOfWeek: [1, 2, 3, 4, 5, 6, 7], // Lunes a Sábado
                startTime: '08:00',
                endTime: '18:00'
            }
        });
        
        this.calendar.render();
    }

    bindEvents() {
        // Filtros - IDs corregidos para coincidir con el template
        const professionalFilter = document.getElementById('professionalFilter');
        if (professionalFilter) {
            professionalFilter.addEventListener('change', (e) => {
                this.currentFilters.professional = e.target.value;
                this.loadAppointments();
            });
        }
        
        const serviceFilter = document.getElementById('serviceFilter');
        if (serviceFilter) {
            serviceFilter.addEventListener('change', (e) => {
                this.currentFilters.service = e.target.value;
                this.loadAppointments();
            });
        }
        
        const viewType = document.getElementById('viewType');
        if (viewType) {
            viewType.addEventListener('change', (e) => {
                this.currentFilters.view = e.target.value;
                this.changeCalendarView(e.target.value);
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
                    title: appointment.title, // Ya viene formateado del servidor
                    start: appointment.start,
                    end: appointment.end,
                    backgroundColor: appointment.backgroundColor,
                    borderColor: appointment.borderColor,
                    extendedProps: appointment.extendedProps // Ya viene con la estructura correcta
                });
            });
            
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
        this.openAppointmentModal({
            id: event.id,
            title: event.title,
            start: event.start,
            end: event.end,
            patientId: event.extendedProps.patientId,
            professionalId: event.extendedProps.professionalId,
            serviceId: event.extendedProps.serviceId,
            status: event.extendedProps.status,
            notes: event.extendedProps.notes,
            isNew: false
        });
    }

    async handleEventDrop(info) {
        try {
            const event = info.event;
            await this.updateAppointmentTime(event.id, {
                start: event.start.toISOString(),
                end: event.end.toISOString()
            });
            this.showAlert('Turno actualizado correctamente', 'success');
        } catch (error) {
            info.revert();
            this.showAlert('Error al mover el turno', 'error');
        }
    }

    async handleEventResize(info) {
        try {
            const event = info.event;
            await this.updateAppointmentTime(event.id, {
                start: event.start.toISOString(),
                end: event.end.toISOString()
            });
            this.showAlert('Duración del turno actualizada', 'success');
        } catch (error) {
            info.revert();
            this.showAlert('Error al cambiar la duración', 'error');
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

    populateModalFields(data) {
        document.getElementById('modal-professional').value = data.professionalId;
        document.getElementById('modal-service').value = data.serviceId;
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
        
        // Cargar servicios del profesional
        this.loadProfessionalServices(data.professionalId);
        
        // Buscar y seleccionar paciente
        this.selectPatient(data.patientId);
    }

    async loadProfessionalServices(professionalId) {
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
                serviceSelect.appendChild(option);
            });
            
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
            
            const url = appointmentId ? `/agenda/appointment/${appointmentId}` : '/agenda/appointment';
            const method = appointmentId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert(appointmentId ? 'Turno actualizado correctamente' : 'Turno creado correctamente', 'success');
                bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
                this.loadAppointments();
            } else {
                this.showAlert(result.message || 'Error al guardar el turno', 'error');
            }
            
        } catch (error) {
            console.error('Error saving appointment:', error);
            this.showAlert('Error al guardar el turno', 'error');
        } finally {
            this.showLoading(false);
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
        
        if (viewMap[view]) {
            this.calendar.changeView(viewMap[view]);
        }
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
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.agendaManager = new AgendaManager();
});

