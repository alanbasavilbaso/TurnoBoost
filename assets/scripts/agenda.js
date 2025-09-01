let agendaManager;

// Agenda Management JavaScript
class AgendaManager {
    constructor() {
        this.calendar = null;
        this.calendarEl = null;
        this.miniCalendar = null;
        this.allAppointments = [];
        this.selectedProfessionals = [];
        this.allProfessionals = []; // Agregar esta línea
        this.currentDate = new Date();
        this.currentView = 'week';
        this.currentFilters = {
            professional: '',
            service: '',
            view: 'timeGridWeek'
        };
    }

    async init() {
        this.calendarEl = document.getElementById('calendar');
        this.loadProfessionalsFromDOM(); // Agregar esta línea
        await this.initializeCalendar();
        this.initializeMiniCalendar();
        this.initializeProfessionalFilter();
        this.bindEvents();
        this.bindCustomControls();
        this.changeView();
        await this.loadAppointments();
        this.updateCurrentDateDisplay();
    }

    // Nuevo método para cargar profesionales del DOM
    loadProfessionalsFromDOM() {
        const professionalCheckboxes = document.querySelectorAll('input[name="professionals[]"][value]:not([value="all"])');
        this.allProfessionals = Array.from(professionalCheckboxes).map(checkbox => {
            const label = document.querySelector(`label[for="${checkbox.id}"]`);
            return {
                id: parseInt(checkbox.value),
                name: label.textContent.trim()
            };
        });
    }
    
    // MANTENER solo este método síncrono (líneas 46-58)
    getFilteredProfessionals() {
        const selectedProfessionals = this.getSelectedProfessionals();
        
        if (selectedProfessionals.length > 0) {
            // Retornar solo los profesionales seleccionados usando datos locales
            return this.allProfessionals.filter(prof => 
                selectedProfessionals.includes(prof.id.toString())
            );
        } else {
            // Retornar todos los profesionales (cuando está seleccionado "Todos")
            return this.allProfessionals;
        }
    }
    async initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        // Cargar configuración de horarios de negocio
        const businessHoursConfig = await this.loadBusinessHours();
        
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            timeZone: 'UTC',
            headerToolbar: false, // Ocultar header por defecto
            height: 'auto',
            selectable: true,
            selectMirror: true,
            
            // Configuración específica por vista
            views: {
                dayGridMonth: {
                    dayMaxEvents: false,
                    moreLinkClick: false,
                    selectable: false
                },
                timeGridWeek: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true
                },
                timeGridDay: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true
                }
            },
            
            weekends: true,
            editable: true,
            allDaySlot: false,
            eventConstraint: 'businessHours',
            
            // Eventos
            select: (info) => this.handleDateSelect(info),
            eventClick: (info) => this.handleEventClick(info),
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            
            // Configurar horarios de negocio
            slotMinTime: businessHoursConfig.slotMinTime,
            slotMaxTime: businessHoursConfig.slotMaxTime,
            slotDuration: businessHoursConfig.slotDuration || '00:15:00',
            businessHours: {
                daysOfWeek: businessHoursConfig.daysOfWeek,
                startTime: businessHoursConfig.startTime,
                endTime: businessHoursConfig.endTime
            }
        });
        
        this.calendar.render();
    }

    initializeMiniCalendar() {
        const miniCalendarEl = document.getElementById('miniCalendar');
        this.renderMiniCalendar(miniCalendarEl, this.currentDate);
    }

    renderMiniCalendar(container, date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        const today = new Date();
        
        // Crear header del mini calendario
        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-center mb-2';
        header.innerHTML = `
            <button class="btn btn-sm btn-outline-secondary" id="miniPrev">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span class="fw-bold">${date.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })}</span>
            <button class="btn btn-sm btn-outline-secondary" id="miniNext">
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        // Crear tabla del calendario
        const table = document.createElement('table');
        table.innerHTML = `
            <thead>
                <tr>
                    <th>D</th><th>L</th><th>M</th><th>M</th><th>J</th><th>V</th><th>S</th>
                </tr>
            </thead>
            <tbody id="miniCalendarBody"></tbody>
        `;
        
        const tbody = table.querySelector('#miniCalendarBody');
        
        // Calcular primer día del mes y días en el mes
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        // Generar semanas
        for (let week = 0; week < 6; week++) {
            const row = document.createElement('tr');
            
            for (let day = 0; day < 7; day++) {
                const cellDate = new Date(startDate);
                cellDate.setDate(startDate.getDate() + (week * 7) + day);
                
                const cell = document.createElement('td');
                cell.textContent = cellDate.getDate();
                cell.dataset.date = cellDate.toISOString().split('T')[0];
                
                // Clases CSS
                if (cellDate.getMonth() !== month) {
                    cell.classList.add('text-muted');
                }
                if (cellDate.toDateString() === today.toDateString()) {
                    cell.classList.add('today');
                }
                if (cellDate.toDateString() === this.currentDate.toDateString()) {
                    cell.classList.add('selected');
                }
                
                // Verificar si tiene citas
                if (this.hasAppointmentsOnDate(cellDate)) {
                    cell.classList.add('has-appointments');
                }
                
                // Event listener
                cell.addEventListener('click', () => {
                    this.selectMiniCalendarDate(cellDate);
                });
                
                row.appendChild(cell);
            }
            
            tbody.appendChild(row);
        }
        
        // Limpiar y agregar contenido
        container.innerHTML = '';
        container.appendChild(header);
        container.appendChild(table);
        
        // Bind navigation events
        document.getElementById('miniPrev').addEventListener('click', () => {
            const newDate = new Date(date);
            newDate.setMonth(newDate.getMonth() - 1);
            this.renderMiniCalendar(container, newDate);
        });
        
        document.getElementById('miniNext').addEventListener('click', () => {
            const newDate = new Date(date);
            newDate.setMonth(newDate.getMonth() + 1);
            this.renderMiniCalendar(container, newDate);
        });
    }

    selectMiniCalendarDate(date) {
        this.currentDate = new Date(date);
        this.calendar.gotoDate(date);
        this.updateCurrentDateDisplay();
        this.updateMiniCalendarSelection();
        
        // Si estamos en vista día con múltiples profesionales, actualizar vista de columnas
        if (this.currentView === 'day' && this.shouldShowProfessionalColumns()) {
            this.renderProfessionalColumns(date);
        }
    }

    updateMiniCalendarSelection() {
        const miniCalendarEl = document.getElementById('miniCalendar');
        const cells = miniCalendarEl.querySelectorAll('td');
        const currentDateStr = this.currentDate.toISOString().split('T')[0];
        
        cells.forEach(cell => {
            cell.classList.remove('selected');
            if (cell.dataset.date === currentDateStr) {
                cell.classList.add('selected');
            }
        });
    }

    bindCustomControls() {
        // Navegación
        document.getElementById('prevBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.prev();
                this.updateCurrentDateDisplay();
            }
        });
        
        document.getElementById('nextBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.next();
                this.updateCurrentDateDisplay();
            }
        });
        
        document.getElementById('todayBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.today();
                this.updateCurrentDateDisplay();
            }
        });
        
        // Intervalos de tiempo
        document.querySelectorAll('[data-interval]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const interval = parseInt(e.target.getAttribute('data-interval'));
                this.changeTimeInterval(interval);
            });
        });
                
        document.getElementById('serviceFilter').addEventListener('change', () => {
            this.loadAppointments();
        });
    }

    changeTimeInterval(interval) {
        this.timeInterval = interval;
        
        // Actualizar el texto del botón
        const dropdownButton = document.getElementById('timeIntervalDropdown');
        dropdownButton.innerHTML = `<i class="fas fa-clock me-1"></i>${interval} min`;
        
        // Actualizar items activos
        document.querySelectorAll('[data-interval]').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-interval="${interval}"]`).classList.add('active');
        
        // Aplicar el nuevo intervalo al calendario
        this.applyTimeInterval();
    }

    applyTimeInterval() {
        if (this.calendar) {
            // Para FullCalendar, configurar el slotDuration
            this.calendar.setOption('slotDuration', `00:${this.timeInterval.toString().padStart(2, '0')}:00`);
            this.calendar.setOption('slotLabelInterval', `00:${this.timeInterval.toString().padStart(2, '0')}:00`);
        }
        
        // Si estamos en vista de columnas profesionales, re-renderizar
        if (document.getElementById('professionalsColumns').style.display !== 'none') {
            this.renderProfessionalColumns(this.currentDate);
        }
    }

    async initializeCalendar() {
        const calendarEl = document.getElementById('calendar');
        
        // Cargar configuración de horarios de negocio
        const businessHoursConfig = await this.loadBusinessHours();
        
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            timeZone: 'UTC',
            headerToolbar: false, // Ocultar header por defecto
            height: 'auto',
            selectable: true,
            selectMirror: true,
            
            // Configuración específica por vista
            views: {
                dayGridMonth: {
                    dayMaxEvents: false,
                    moreLinkClick: false,
                    selectable: false
                },
                timeGridWeek: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true
                },
                timeGridDay: {
                    dayMaxEvents: false,
                    slotEventOverlap: true,
                    eventOverlap: true,
                    selectable: true
                }
            },
            
            weekends: true,
            editable: true,
            allDaySlot: false,
            eventConstraint: 'businessHours',
            
            // Eventos
            select: (info) => this.handleDateSelect(info),
            eventClick: (info) => this.handleEventClick(info),
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            
            // Configurar horarios de negocio
            slotMinTime: businessHoursConfig.slotMinTime,
            slotMaxTime: businessHoursConfig.slotMaxTime,
            slotDuration: businessHoursConfig.slotDuration || '00:15:00',
            businessHours: {
                daysOfWeek: businessHoursConfig.daysOfWeek,
                startTime: businessHoursConfig.startTime,
                endTime: businessHoursConfig.endTime
            }
        });
        
        this.calendar.render();
    }

    async renderProfessionalColumns(date) {
        const professionals = this.getFilteredProfessionals();
        const header = document.getElementById('professionalsHeader');
        const timeSlots = document.getElementById('timeSlots');
        
        // Limpiar contenido existente
        header.innerHTML = '<div class="time-column">Hora</div>';
        timeSlots.innerHTML = '';
        
        // Agregar columnas de profesionales
        professionals.forEach(prof => {
            const col = document.createElement('div');
            col.className = 'professional-column';
            col.textContent = prof.name;
            col.dataset.professionalId = prof.id;
            header.appendChild(col);
        });
        
        // Generar slots de tiempo
        const businessHours = await this.loadBusinessHours();
        const startTime = this.parseTime(businessHours.startTime);
        const endTime = this.parseTime(businessHours.endTime);
        const slotDuration = this.timeInterval || 30; // Usar timeInterval dinámico
        
        for (let time = startTime; time < endTime; time += slotDuration) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            
            // Etiqueta de tiempo
            const timeLabel = document.createElement('div');
            timeLabel.className = 'time-label';
            timeLabel.textContent = this.formatTime(time);
            timeSlot.appendChild(timeLabel);
            
            // Slots por profesional
            professionals.forEach(prof => {
                const profSlot = document.createElement('div');
                profSlot.className = 'professional-slot';
                profSlot.dataset.professionalId = prof.id;
                profSlot.dataset.time = this.formatTime(time);
                profSlot.dataset.date = date.toISOString().split('T')[0];
                
                // Buscar citas en este slot
                const appointments = this.getAppointmentsForSlot(date, time, prof.id);
                // Dentro del forEach de appointments en renderProfessionalColumns
                appointments.forEach(apt => {
                    const aptBlock = document.createElement('div');
                    aptBlock.className = 'appointment-block';
                    aptBlock.dataset.id = apt.id;
                    
                    // Mostrar información completa del turno
                    aptBlock.innerHTML = `
                        <div class="appointment-title">${apt.title}</div>
                        <div class="appointment-time">${new Date(apt.start).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</div>
                    `;
                    aptBlock.addEventListener('click', async () => {
                        // Cargar información completa de la cita
                        try {
                            const response = await fetch(`/agenda/appointment/${apt.id}`);
                            const fullAppointmentData = await response.json();
                            this.showAppointmentDetails(fullAppointmentData);
                        } catch (error) {
                            console.error('Error loading appointment details:', error);
                            // Fallback: usar los datos disponibles
                            this.showAppointmentDetails(apt);
                        }
                    });
                    
                    // Calcular posición y altura total de la cita
                    const aptStart = new Date(apt.start);
                    const aptEnd = new Date(apt.end);
                    const aptStartMinutes = aptStart.getHours() * 60 + aptStart.getMinutes();
                    const aptEndMinutes = aptEnd.getHours() * 60 + aptEnd.getMinutes();
                    const aptDurationMinutes = aptEndMinutes - aptStartMinutes;
                    
                    const slotStartMinutes = time;
                    
                    // Posición dentro del slot donde comienza
                    const minutesFromSlotStart = aptStartMinutes - slotStartMinutes;
                    const positionPercentage = (minutesFromSlotStart / slotDuration) * 100;
                    
                    // Altura total de la cita (puede extenderse más allá del slot actual)
                    const heightInPixels = (aptDurationMinutes / slotDuration) * 60; // Asumiendo 60px por slot
                    
                    // Aplicar posicionamiento CSS
                    aptBlock.style.position = 'absolute';
                    aptBlock.style.top = `${positionPercentage}%`;
                    aptBlock.style.left = '0';
                    aptBlock.style.right = '0';
                    aptBlock.style.height = `${heightInPixels}px`;
                    aptBlock.style.minHeight = '15px';
                    aptBlock.style.zIndex = '10';
                    
                    aptBlock.addEventListener('click', () => {
                        this.showAppointmentDetails(apt);
                    });
                    profSlot.appendChild(aptBlock);
                });
                
                // Altura del slot proporcional al intervalo de tiempo
                const slotHeight = Math.max(60, slotDuration * 2); // Mínimo 60px, 2px por minuto
                profSlot.style.position = 'relative';
                profSlot.style.height = `${slotHeight}px`;
                
                // Event listener para crear nueva cita
                profSlot.addEventListener('click', (e) => {
                    if (e.target === profSlot) {
                        this.openAppointmentModal({
                            date: date.toISOString().split('T')[0],
                            time: this.formatTime(time),
                            professionalId: prof.id,
                            isNew: true
                        });
                    }
                });
                
                timeSlot.appendChild(profSlot);
            });
            
            timeSlots.appendChild(timeSlot);
        }
    }

    updateCurrentDateDisplay() {
        const display = document.getElementById('currentDateDisplay');
        let text = '';
        
        if (this.currentView === 'day') {
            text = this.currentDate.toLocaleDateString('es-ES', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        } else if (this.currentView === 'week') {
            const startOfWeek = new Date(this.currentDate);
            startOfWeek.setDate(this.currentDate.getDate() - this.currentDate.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            
            text = `${startOfWeek.getDate()} - ${endOfWeek.getDate()} de ${startOfWeek.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' })}`;
        } else if (this.currentView === 'month') {
            text = this.currentDate.toLocaleDateString('es-ES', {
                month: 'long',
                year: 'numeric'
            });
        }
        
        display.textContent = text;
    }

    // Funciones auxiliares
    hasAppointmentsOnDate(date) {
        const dateStr = date.toISOString().split('T')[0];
        return this.allAppointments.some(apt => {
            const aptDate = new Date(apt.start).toISOString().split('T')[0];
            return aptDate === dateStr;
        });
    }

    getAppointmentsForSlot(date, time, professionalId) {
        const dateStr = date.toISOString().split('T')[0];
        const slotStartMinutes = time;
        const slotDuration = this.timeInterval || 30;
        
        return this.allAppointments.filter(apt => {
            const aptDate = new Date(apt.start).toISOString().split('T')[0];
            const aptStart = new Date(apt.start);
            const aptStartMinutes = aptStart.getHours() * 60 + aptStart.getMinutes();
            
            // Solo mostrar la cita en el slot donde COMIENZA
            // La cita debe comenzar dentro de este slot específico
            const appointmentStartsInThisSlot = (
                aptStartMinutes >= slotStartMinutes && 
                aptStartMinutes < slotStartMinutes + slotDuration
            );
            
            return aptDate === dateStr && 
                   appointmentStartsInThisSlot && 
                   apt.extendedProps.professionalId == professionalId;
        });
    }

    parseTime(timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        return hours * 60 + minutes;
    }

    formatTime(minutes) {
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
    }

    async loadBusinessHours() {
        // Configuración por defecto de horarios de negocio
        return {
            slotMinTime: '08:00:00',
            slotMaxTime: '20:00:00',
            slotDuration: '00:30:00',
            daysOfWeek: [1, 2, 3, 4, 5, 6], // Lunes a Sábado
            startTime: '08:00',
            endTime: '20:00'
        };
    }

    bindEvents() {
        // Bind filtros de profesional y servicio
        const professionalFilter = document.getElementById('professionalFilter');
        const serviceFilter = document.getElementById('serviceFilter');
        
        if (professionalFilter) {
            professionalFilter.addEventListener('change', () => {
                this.currentFilters.professional = professionalFilter.value;
                this.loadAppointments();
            });
        }
        
        if (serviceFilter) {
            serviceFilter.addEventListener('change', () => {
                this.currentFilters.service = serviceFilter.value;
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
            const params = new URLSearchParams();
            
            // Obtener profesionales seleccionados
            const selectedProfessionals = this.getSelectedProfessionals();
            if (selectedProfessionals.length > 0) {
                // Si hay profesionales específicos seleccionados
                selectedProfessionals.forEach(profId => {
                    params.append('professionals[]', profId);
                });
            } else {
                // Si está seleccionado "Todos", enviar todos los IDs
                this.allProfessionals.forEach(prof => {
                    params.append('professionals[]', prof.id);
                });
            }
            
            if (this.currentFilters.service) {
                params.append('service', this.currentFilters.service);
            }
            
            const response = await fetch(`/agenda/appointments?${params.toString()}`);
            const appointments = await response.json();
            
            this.allAppointments = appointments;
            
            // Solo actualizar FullCalendar si está visible
            if (this.calendar && document.getElementById('calendar').style.display !== 'none') {
                this.calendar.removeAllEvents();
                this.calendar.addEventSource(appointments);
            }
            
            // Si estamos en vista de columnas, re-renderizar
            if (document.getElementById('professionalsColumns').style.display !== 'none') {
                this.renderProfessionalColumns(this.currentDate);
            }
        } catch (error) {
            console.error('Error loading appointments:', error);
            this.allAppointments = [];
        }
    }

    handleDateSelect(info) {
        // Manejar selección de fecha para crear nueva cita
        const startTime = info.start;
        const endTime = info.end;
        
        // Aquí puedes abrir un modal para crear nueva cita
        console.log('Selected time slot:', startTime, 'to', endTime);
        
        // Ejemplo de creación de evento temporal
        const newEvent = {
            title: 'Nueva Cita',
            start: startTime,
            end: endTime,
            backgroundColor: '#007bff',
            borderColor: '#007bff'
        };
        
        this.calendar.addEvent(newEvent);
    }

    handleEventClick(info) {
        // Manejar click en evento existente
        const event = info.event;
        console.log('Event clicked:', event.title, event.start);
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
        
        // Manejar data.start como string o Date
        let startDate;
        if (typeof data.start === 'string') {
            startDate = new Date(data.start);
        } else {
            startDate = data.start;
        }
        
        document.getElementById('appointment-date').value = startDate.toISOString().split('T')[0];
        
        // Extraer y establecer la hora
        const timeStr = startDate.toTimeString().slice(0, 5); // HH:MM
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
        
        // Ahora cargar horarios disponibles
        if (data.professionalId && data.serviceId && startDate) {
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

    handleEventDrop(info) {
        // Manejar cuando se arrastra un evento a nueva posición
        const event = info.event;
        console.log('Event moved:', event.title, 'to', event.start);
        
        // Aquí puedes hacer una llamada AJAX para actualizar la cita en el servidor
        // Si falla, puedes revertir con: info.revert();
    }

    handleEventResize(info) {
        // Manejar cuando se redimensiona un evento
        const event = info.event;
        console.log('Event resized:', event.title, 'new end:', event.end);
        
        // Aquí puedes hacer una llamada AJAX para actualizar la duración en el servidor
        // Si falla, puedes revertir con: info.revert();
    }

    // Método para obtener profesionales seleccionados (actualizado para múltiples)
    getSelectedProfessionals() {
        const checkboxes = document.querySelectorAll('input[name="professionals[]"]:checked');
        const selectedIds = Array.from(checkboxes).map(cb => cb.value).filter(val => val !== 'all');
        
        // Si está seleccionado "todos" o no hay ninguno seleccionado, retornar array vacío
        const allSelected = document.querySelector('input[name="professionals[]"][value="all"]:checked');
        if (allSelected || selectedIds.length === 0) {
            return [];
        }
        
        return selectedIds;
    }

    // Método para determinar si mostrar columnas de profesionales
    shouldShowProfessionalColumns() {
        const selectedProfessionals = this.getSelectedProfessionals();
        // Mostrar columnas cuando hay múltiples profesionales seleccionados O cuando no hay ninguno seleccionado (todos)
        return selectedProfessionals.length === 0 || selectedProfessionals.length > 1;
    }
    
    // Actualizar botones de vista (solo visual, no funcional)
    updateViewButtons(activeView) {
        // Remover clase active de todos los botones
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Agregar clase active al botón correspondiente
        const activeButton = document.getElementById(activeView);
        if (activeButton) {
            activeButton.classList.add('active');
        }
    }
    
    // Cambia la vista según la lógica automática
    changeView() {
        const viewType = this.determineAutoView();
        
        if (viewType === 'week') {
            // Vista semana con FullCalendar
            document.getElementById('calendar').style.display = 'block';
            document.getElementById('professionalsColumns').style.display = 'none';
            
            if (this.calendar) {
                this.calendar.changeView('timeGridWeek');
            }
        } else {
            // Vista día con columnas profesionales
            document.getElementById('calendar').style.display = 'none';
            document.getElementById('professionalsColumns').style.display = 'block';
            this.renderProfessionalColumns(this.currentDate);
        }
        
        this.updateCurrentDateDisplay();
    }
    
    // Modificar bindCustomControls para remover los event listeners de los botones de vista
    bindCustomControls() {
        // Navegación
        document.getElementById('prevBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.prev();
                this.updateCurrentDateDisplay();
            }
        });
        
        document.getElementById('nextBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.next();
                this.updateCurrentDateDisplay();
            }
        });
        
        document.getElementById('todayBtn').addEventListener('click', () => {
            if (this.calendar) {
                this.calendar.today();
                this.updateCurrentDateDisplay();
            }
        });
        
        // Intervalos de tiempo
        document.querySelectorAll('[data-interval]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const interval = parseInt(e.target.getAttribute('data-interval'));
                this.changeTimeInterval(interval);
            });
        });
        
        document.getElementById('serviceFilter').addEventListener('change', () => {
            this.loadAppointments();
        });
    }
    
    // Determina automáticamente qué vista usar
    determineAutoView() {
        const selectedProfessionals = this.getSelectedProfessionals();
        
        if (selectedProfessionals.length === 1) {
            // Un profesional seleccionado → Vista semana
            this.currentView = 'timeGridWeek';
            this.updateViewIndicator('week');
            return 'week';
        } else {
            // Múltiples profesionales o todos → Vista de columnas (día)
            this.currentView = 'columns';
            this.updateViewIndicator('day');
            return 'day';
        }
    }

    // Actualizar indicador de vista (reemplaza updateViewButtons)
    updateViewIndicator(viewType) {
        const indicator = document.getElementById('currentViewIndicator');
        if (indicator) {
            const selectedProfessionals = this.getSelectedProfessionals();
            if (viewType === 'week') {
                indicator.textContent = 'Vista Semana (1 Profesional)';
                indicator.className = 'badge bg-success';
            } else {
                if (selectedProfessionals.length === 0) {
                    indicator.textContent = 'Vista Día (Todos los Profesionales)';
                } else {
                    indicator.textContent = `Vista Día (${selectedProfessionals.length} Profesionales)`;
                }
                indicator.className = 'badge bg-info';
            }
        }
    }

    // Método para inicializar el filtro de profesionales con checkboxes
    initializeProfessionalFilter() {
        const allCheckbox = document.querySelector('input[name="professionals[]"][value="all"]');
        const professionalCheckboxes = document.querySelectorAll('input[name="professionals[]"]:not([value="all"])');
        const filterText = document.getElementById('professionalFilterText');
        
        // Manejar checkbox "Todos"
        allCheckbox.addEventListener('change', () => {
            if (allCheckbox.checked) {
                // Desmarcar todos los demás
                professionalCheckboxes.forEach(cb => cb.checked = false);
                filterText.textContent = 'Todos los profesionales';
            }
            this.updateProfessionalFilter();
        });
        
        // Manejar checkboxes individuales
        professionalCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    // Si se selecciona uno individual, desmarcar "Todos"
                    allCheckbox.checked = false;
                }
                
                // Si no hay ninguno seleccionado, marcar "Todos"
                const anyChecked = Array.from(professionalCheckboxes).some(cb => cb.checked);
                if (!anyChecked) {
                    allCheckbox.checked = true;
                }
                
                this.updateProfessionalFilterText();
                this.updateProfessionalFilter();
            });
        });
    }
    
    // Actualizar texto del filtro
    updateProfessionalFilterText() {
        const filterText = document.getElementById('professionalFilterText');
        const allCheckbox = document.querySelector('input[name="professionals[]"][value="all"]');
        const selectedCheckboxes = document.querySelectorAll('input[name="professionals[]"]:checked:not([value="all"])');
        
        if (allCheckbox.checked) {
            filterText.textContent = 'Todos los profesionales';
        } else if (selectedCheckboxes.length === 0) {
            filterText.textContent = 'Ningún profesional seleccionado';
        } else if (selectedCheckboxes.length === 1) {
            const label = document.querySelector(`label[for="${selectedCheckboxes[0].id}"]`);
            filterText.textContent = label.textContent;
        } else {
            filterText.textContent = `${selectedCheckboxes.length} profesionales seleccionados`;
        }
    }
    
    // Actualizar filtro y vista
    updateProfessionalFilter() {
        this.changeView(); // Recalcular vista automáticamente
        this.loadAppointments();
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', async function() {
    agendaManager = new AgendaManager();
    await agendaManager.init();
});