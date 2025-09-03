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
        // Cargar la preferencia de timeInterval desde localStorage, por defecto 30 minutos
        this.timeInterval = parseInt(localStorage.getItem('agenda_time_interval')) || 30;
        this.currentFilters = {
            professional: '',
            service: '',
            view: 'timeGridWeek'
        };
    }

    async init() {
        this.calendarEl = document.getElementById('calendar');
        this.loadProfessionalsFromDOM();
        await this.initializeCalendar();
        this.initializeMiniCalendar();
        this.initializeProfessionalFilter();
        this.bindEvents();
        this.bindCustomControls();
        this.changeView();
        await this.loadAppointments();
        this.updateCurrentDateDisplay();
        // Aplicar la preferencia de timeInterval guardada
        this.applyTimeInterval();
        this.updateTimeIntervalUI();
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
        
        // Recargar turnos para la nueva fecha
        this.loadAppointments();
        
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
            this.navigatePrevious();
        });
        
        document.getElementById('nextBtn').addEventListener('click', () => {
            this.navigateNext();
        });
        
        document.getElementById('todayBtn').addEventListener('click', () => {
            this.navigateToday();
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

    // Nuevo método para navegación anterior
    navigatePrevious() {
        const viewType = this.determineAutoView();
        
        if (viewType === 'week') {
            // Vista semanal: retroceder una semana
            this.currentDate.setDate(this.currentDate.getDate() - 7);
            if (this.calendar) {
                this.calendar.gotoDate(this.currentDate);
            }
        } else {
            // Vista diaria: retroceder un día
            this.currentDate.setDate(this.currentDate.getDate() - 1);
            if (this.shouldShowProfessionalColumns()) {
                this.renderProfessionalColumns(this.currentDate);
            }
        }
        
        this.updateCurrentDateDisplay();
        this.updateMiniCalendarSelection();
        this.loadAppointments();
    }

    // Nuevo método para navegación siguiente
    navigateNext() {
        const viewType = this.determineAutoView();
        
        if (viewType === 'week') {
            // Vista semanal: avanzar una semana
            this.currentDate.setDate(this.currentDate.getDate() + 7);
            if (this.calendar) {
                this.calendar.gotoDate(this.currentDate);
            }
        } else {
            // Vista diaria: avanzar un día
            this.currentDate.setDate(this.currentDate.getDate() + 1);
            if (this.shouldShowProfessionalColumns()) {
                this.renderProfessionalColumns(this.currentDate);
            }
        }
        
        this.updateCurrentDateDisplay();
        this.updateMiniCalendarSelection();
        this.loadAppointments();
    }

    // Nuevo método para ir a hoy
    navigateToday() {
        this.currentDate = new Date();
        
        const viewType = this.determineAutoView();
        
        if (viewType === 'week') {
            if (this.calendar) {
                this.calendar.gotoDate(this.currentDate);
            }
        } else {
            if (this.shouldShowProfessionalColumns()) {
                this.renderProfessionalColumns(this.currentDate);
            }
        }
        
        this.updateCurrentDateDisplay();
        this.updateMiniCalendarSelection();
        this.loadAppointments();
    }

    changeTimeInterval(interval) {
        this.timeInterval = interval;
        
        // Guardar la preferencia en localStorage
        localStorage.setItem('agenda_time_interval', interval.toString());
        
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

    updateTimeIntervalUI() {
        const dropdownButton = document.getElementById('timeIntervalDropdown');
        if (dropdownButton) {
            dropdownButton.innerHTML = `<i class="fas fa-clock me-1"></i>${this.timeInterval} min`;
        }
        
        // Actualizar items activos
        document.querySelectorAll('[data-interval]').forEach(item => {
            item.classList.remove('active');
        });
        const activeItem = document.querySelector(`[data-interval="${this.timeInterval}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }

    applyTimeInterval() {
        // Cerrar cualquier tooltip existente antes de re-renderizar
        this.removeExistingTooltip();
        
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
        // Cerrar cualquier tooltip existente antes de re-renderizar
        this.removeExistingTooltip();
        
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
                appointments.forEach(apt => {
                    const aptBlock = document.createElement('div');
                    aptBlock.className = 'appointment-block';
                    aptBlock.dataset.id = apt.id;

                    // Aplicar colores dinámicos desde el backend
                    if (apt.backgroundColor) {
                        aptBlock.style.backgroundColor = apt.backgroundColor;
                    }
                    if (apt.borderColor) {
                        aptBlock.style.borderColor = apt.borderColor;
                        aptBlock.style.borderWidth = '2px';
                        aptBlock.style.borderStyle = 'solid';
                    }

                    // Mostrar información completa del turno
                    aptBlock.innerHTML = `
                        <div class="appointment-time">${new Date(apt.start).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})} - ${new Date(apt.end).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</div>
                        <div class="appointment-title">${apt.title}</div>
                    `;
                    // UN SOLO EVENT LISTENER CON EL EVENTO PASADO CORRECTAMENTE
                    aptBlock.addEventListener('click', async (event) => {
                        event.stopPropagation(); // Evitar que se propague al slot
                        // Cargar información completa de la cita
                        try {
                            const response = await fetch(`/agenda/appointment/${apt.id}`);
                            const fullAppointmentData = await response.json();
                            this.showAppointmentDetails(fullAppointmentData, event);
                        } catch (error) {
                            console.error('Error loading appointment details:', error);
                            // Fallback: usar los datos disponibles
                            this.showAppointmentDetails(apt, event);
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
                    const heightInPixels = (aptDurationMinutes / slotDuration) * 35; // Asumiendo 35px por slot

                    // Aplicar posicionamiento CSS
                    aptBlock.style.position = 'absolute';
                    aptBlock.style.top = `${positionPercentage}%`;
                    aptBlock.style.left = '0';
                    aptBlock.style.right = '0';
                    aptBlock.style.height = `${heightInPixels}px`;
                    aptBlock.style.minHeight = '15px';
                    aptBlock.style.zIndex = '10';

                    profSlot.appendChild(aptBlock);
                });
                
                // Altura del slot proporcional al intervalo de tiempo
                const slotHeight = 35;
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
        const headerDisplay = document.getElementById('currentDateHeader');
        let headerText = '';
        
        if (this.currentView === 'columns') {
            headerText = this.currentDate.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        } else if (this.currentView === 'timeGridWeek') {
            const startOfWeek = new Date(this.currentDate);
            startOfWeek.setDate(this.currentDate.getDate() - this.currentDate.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            
            const startFormatted = startOfWeek.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const endFormatted = endOfWeek.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            headerText = `${startFormatted} a ${endFormatted}`;
        }
        
        if (headerDisplay) {
            headerDisplay.textContent = headerText;
        }
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
            
            // Determinar el tipo de vista actual
            const viewType = this.determineAutoView();
            
            // Agregar filtro de fecha según la vista
            if (this.currentDate) {
                if (viewType === 'week') {
                    // Vista semanal: enviar start y end (domingo a sábado)
                    const currentDate = new Date(this.currentDate);
                    
                    // Calcular el domingo de la semana actual
                    const dayOfWeek = currentDate.getDay(); // 0 = domingo, 1 = lunes, etc.
                    const startOfWeek = new Date(currentDate);
                    startOfWeek.setDate(currentDate.getDate() - dayOfWeek);
                    
                    // Calcular el sábado de la semana actual
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    
                    // Formatear fechas
                    const startStr = startOfWeek.toISOString().split('T')[0];
                    const endStr = endOfWeek.toISOString().split('T')[0];
                    
                    params.append('start', startStr);
                    params.append('end', endStr);
                } else {
                    // Vista diaria: enviar solo date (o start/end con la misma fecha)
                    const dateStr = this.currentDate.toISOString().split('T')[0];
                    params.append('date', dateStr);
                    // Alternativamente, puedes usar start/end con la misma fecha:
                    // params.append('start', dateStr);
                    // params.append('end', dateStr);
                }
            }
            
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
        
        // Formatear la fecha para el modal
        const appointmentDate = startTime.toISOString().split('T')[0];
        const appointmentTime = startTime.toTimeString().slice(0, 5);
        
        // Abrir el modal de turnos con los datos de fecha y hora
        this.openAppointmentModal({
            isNew: true,
            date: appointmentDate,
            time: appointmentTime,
            start: startTime,
            end: endTime
        });
        
        // Limpiar la selección del calendario
        this.calendar.unselect();
    }

    handleEventClick(info) {
        console.log('Event clicked:', info.event);
        
        const eventData = {
            id: info.event.id,
            title: info.event.title,
            start: info.event.start,
            end: info.event.end,
            professionalId: info.event.extendedProps?.professionalId,
            serviceId: info.event.extendedProps?.serviceId,
            status: info.event.extendedProps?.status,
            notes: info.event.extendedProps?.notes,
            patientId: info.event.extendedProps?.patientId,
            patientName: info.event.extendedProps?.patientName,
            patientEmail: info.event.extendedProps?.patientEmail,
            patientPhone: info.event.extendedProps?.patientPhone,
            professionalName: info.event.extendedProps?.professionalName,
            serviceName: info.event.extendedProps?.serviceName
        };
        
        this.showAppointmentDetails(eventData, info.jsEvent);
    }

    showAppointmentDetails(eventData, clickEvent) {
        console.log('showAppointmentDetails', eventData);
        // Remover tooltip existente si hay uno
        this.removeExistingTooltip();
        // Formatear fecha en formato DD/MM/YYYY
        const startDate = new Date(eventData.start);
        const formattedDate = startDate.toLocaleDateString('es-ES', {
            day: '2-digit',
            month: '2-digit', 
            year: 'numeric'
        });
        const startTimeUTC = startDate.toTimeString().substring(0,5) // HH:MM en UTC
        const endDate = new Date(eventData.end);
        const endTimeUTC = endDate.toTimeString().substring(0, 5); // HH:MM en UTC
        // Calcular duración
        const durationMs = endDate - startDate;
        const durationMinutes = Math.round(durationMs / (1000 * 60));
        const hours = Math.floor(durationMinutes / 60);
        const minutes = durationMinutes % 60;
        const durationText = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
        // Crear el tooltip
        const tooltip = this.createAppointmentTooltip({
            patientName: eventData.patientName || 'No especificado',
            serviceName: eventData.serviceName || 'No especificado',
            professionalName: eventData.professionalName || 'No especificado',
            date: formattedDate,
            time: `${startTimeUTC} - ${endTimeUTC}`,
            duration: durationText,
            status: eventData.status,
            notes: eventData.notes,
            appointmentData: eventData
        });
        // Posicionar el tooltip
        this.positionTooltip(tooltip, clickEvent);
        // Agregar al DOM
        document.body.appendChild(tooltip);
        // Mostrar con animación
        setTimeout(() => {
            tooltip.classList.add('show');
        }, 10);
        // Configurar eventos de cierre
        this.setupTooltipCloseEvents(tooltip);
    }

    createAppointmentTooltip(data) {
        const tooltip = document.createElement('div');
        tooltip.className = 'appointment-tooltip';
        tooltip.setAttribute('data-testid', 'detail-tooltip-container');
        const statusBadge = this.getStatusText(data.status);
        const statusClass = this.getStatusBadgeClass(data.status);
        tooltip.innerHTML = `
            <div class="tooltip-header">
                <div class="status-container">
                    <span class="status-badge ${statusClass}">${statusBadge}</span>
                </div>
                <div class="tooltip-actions">
                    <button type="button" class="tooltip-btn delete-btn" data-action="delete" title="Eliminar turno">
                        <i class="fas fa-trash" style="color: #E42043;"></i>
                    </button>
                    <button type="button" class="tooltip-btn edit-btn" data-action="edit" title="Editar turno">
                        <i class="fas fa-edit" style="color: #7440BB;"></i>
                        Editar
                    </button>
                    <button type="button" class="tooltip-btn close-btn" data-action="close" title="Cerrar">
                        <i class="fas fa-times" style="color: #1F2A30;"></i>
                    </button>
                </div>
            </div>
            <div class="tooltip-content">
                <div class="patient-info">
                    <h3 class="patient-name">${data.patientName}</h3>
                    <div class="service-info">
                        <span class="service-name">${data.serviceName}</span>
                    </div>
                </div>
                <div class="appointment-details">
                    <div class="detail-row">
                        <span class="detail-label">Profesional:</span>
                        <span class="detail-value">${data.professionalName}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Fecha:</span>
                        <span class="detail-value">${data.date}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Hora:</span>
                        <span class="detail-value">${data.time}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Duración:</span>
                        <span class="detail-value">${data.duration}</span>
                    </div>
                    ${data.notes ? `
                    <div class="detail-row notes-row">
                        <span class="detail-label">Notas:</span>
                        <span class="detail-value">${data.notes}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
        // Guardar datos del turno en el tooltip para las acciones
        tooltip._appointmentData = data.appointmentData;
        return tooltip;
    }

    positionTooltip(tooltip, clickEvent) {
        if (!clickEvent) {
            tooltip.style.left = '50%';
            tooltip.style.top = '50%';
            tooltip.style.transform = 'translate(-50%, -50%)';
            return;
        }
        
        const tooltipRect = { width: 320, height: 400 };
        
        // Usar directamente las coordenadas del click
        let left = clickEvent.clientX;
        let top = clickEvent.clientY;
        
        // Ajustar horizontalmente si se sale de la pantalla
        if (left + tooltipRect.width > window.innerWidth) {
            left = clickEvent.clientX - tooltipRect.width;
        }
        if (left < 0) {
            left = 10;
        }
        
        // Ajustar verticalmente si se sale de la pantalla
        if (top + tooltipRect.height > window.innerHeight) {
            top = window.innerHeight - tooltipRect.height - 10; // 10px de margen
        }
        if (top < 0) {
            top = 10;
        }
        
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
        tooltip.style.transform = 'none';
    }


    setupTooltipCloseEvents(tooltip) {
        // Cerrar al hacer clic en el botón de cerrar
        const closeBtn = tooltip.querySelector('[data-action="close"]');
        closeBtn.addEventListener('click', () => {
            this.removeTooltip(tooltip);
        });
        // Configurar botones de acción
        const editBtn = tooltip.querySelector('[data-action="edit"]');
        const deleteBtn = tooltip.querySelector('[data-action="delete"]');
        editBtn.addEventListener('click', () => {
            this.removeTooltip(tooltip);
            this.openAppointmentModal(tooltip._appointmentData);
        });
        deleteBtn.addEventListener('click', () => {
            this.removeTooltip(tooltip);
            this.showConfirmationModal(
                '¿Eliminar este turno?',
                'Esta acción no se puede deshacer.',
                'fas fa-trash',
                'btn-danger',
                () => this.updateAppointmentStatus(tooltip._appointmentData.id, 'cancelled')
            );
        });
        // Cerrar al hacer clic fuera del tooltip
        setTimeout(() => {
            document.addEventListener('click', (e) => {
                if (!tooltip.contains(e.target)) {
                    this.removeTooltip(tooltip);
                }
            }, { once: true });
        }, 100);
        // Cerrar con ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.removeTooltip(tooltip);
            }
        }, { once: true });
    }

    removeTooltip(tooltip) {
        if (tooltip && tooltip.parentNode) {
            tooltip.classList.remove('show');
            setTimeout(() => {
                if (tooltip.parentNode) {
                    tooltip.parentNode.removeChild(tooltip);
                }
            }, 200);
        }
    }

    removeExistingTooltip() {
        const existingTooltip = document.querySelector('.appointment-tooltip');
        if (existingTooltip) {
            this.removeTooltip(existingTooltip);
        }
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
        const statusContainer = document.getElementById('appointment-status-container');
        const statusSelect = document.getElementById('appointment-status');
        
        if (modalLabel) {
            if (data.isNew) {
                modalLabel.textContent = 'Nuevo Turno';
                // Ocultar selector de estado para turnos nuevos
                if (statusContainer) {
                    statusContainer.classList.add('d-none');
                }
                const appointmentDate = document.getElementById('appointment-date');
                if (appointmentDate) {
                    appointmentDate.value = data.date;
                }
            } else {
                modalLabel.textContent = 'Editar Turno';
                // Mostrar selector de estado para turnos existentes
                if (statusContainer) {
                    statusContainer.classList.remove('d-none');
                }
                // Establecer el estado actual
                if (statusSelect && data.status) {
                    statusSelect.value = data.status;
                }
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
        console.log('----');
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
        // Agregar el estado si está presente
        const statusSelect = document.getElementById('appointment-status');
        if (statusSelect && !statusSelect.closest('.d-none')) {
            data.status = statusSelect.value;
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
        const allCheckbox = document.querySelector('input[name="professionals[]"][value="all"]');
        const professionalCheckboxes = document.querySelectorAll('input[name="professionals[]"]:not([value="all"])');
        const selectedProfessionalCheckboxes = document.querySelectorAll('input[name="professionals[]"]:not([value="all"]):checked');
        
        // Si no hay selecciones pero solo existe un profesional, retornar ese único profesional
        if (professionalCheckboxes.length === 1) {
            return [professionalCheckboxes[0].value];
        }

        // Si "Todos" está seleccionado, retornar array vacío
        if (allCheckbox?.checked) {
            return [];
        }
        
        // Si hay selecciones específicas, retornar esos IDs
        if (selectedProfessionalCheckboxes.length > 0) {
            return Array.from(selectedProfessionalCheckboxes).map(checkbox => checkbox.value);
        }
        
        // En cualquier otro caso, retornar array vacío
        return [];
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
        console.log('*******');
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
    
    // Determina automáticamente qué vista usar
    determineAutoView() {
        const selectedProfessionals = this.getSelectedProfessionals();
        
        if (selectedProfessionals.length === 1) {
            // Un profesional seleccionado → Vista semana
            this.currentView = 'timeGridWeek';
            return 'week';
        } else {
            // Múltiples profesionales o todos → Vista de columnas (día)
            this.currentView = 'columns';
            return 'day';
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
            filterText.textContent = `${selectedCheckboxes.length} seleccionados`;
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