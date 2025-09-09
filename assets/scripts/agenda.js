let agendaManager;

// Agenda Management JavaScript
class AgendaManager {
    constructor() {
        this.calendar = null;
        this.calendarEl = null;
        this.miniCalendar = null;
        this.allAppointments = [];
        this.allBlocks = [];
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
        this.businessHoursCache = null;
        this.lastBusinessHoursUpdate = null;
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
        // this.applyTimeInterval();
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
    
    getCurrentSelectedService() {
        // Obtener el servicio seleccionado actualmente (único)
        const serviceSelect = document.querySelector('[name="service_id"]');
        return serviceSelect ? serviceSelect.value : null;
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
            this.renderProfessionalColumns(this.currentDate, this.allAppointments, this.allBlocks);
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
            // eventConstraint: 'businessHours',
            
            // Eventos
            select: (info) => this.handleDateSelect(info),
            eventClick: (info) => {
                // Manejar click en bloques no laborables
                if (info.event.extendedProps && info.event.extendedProps.type === 'non-working') {
                    console.log('Bloque no laborable clickeado:', {
                        profesional: info.event.extendedProps.professionalName,
                        profesionalId: info.event.extendedProps.professionalId,
                        fecha: info.event.start.toISOString().split('T')[0],
                        horaInicio: info.event.start.toTimeString().split(' ')[0],
                        horaFin: info.event.end.toTimeString().split(' ')[0],
                        razon: info.event.extendedProps.reason
                    });
                    return; // No abrir modal para bloques no laborables
                }
                
                this.handleEventClick(info);
            },
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

    async renderProfessionalColumns(date, appointments = null, blocks = null) {
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
        
        // Usar los appointments proporcionados o los almacenados en la instancia
        const appointmentsToRender = appointments || this.allAppointments;
        // Usar los blocks proporcionados o un array vacío si no hay
        const blocksToRender = blocks || this.allBlocks;
        
        // NUEVA LÓGICA: Renderizar bloques una sola vez por profesional
        professionals.forEach(prof => {
            // Obtener todos los bloques del profesional para esta fecha
            const professionalBlocks = this.getBlocksForProfessional(date, prof.id, blocksToRender);
            
            professionalBlocks.forEach(block => {
                this.renderSingleBlock(block, prof.id, startTime, slotDuration, timeSlots, endTime);
            });
        });
        
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
                const appointments = this.getAppointmentsForSlot(date, time, prof.id, appointmentsToRender);
                appointments.forEach(apt => {
                    const aptBlock = document.createElement('div');
                    aptBlock.className = 'appointment-block';
                    aptBlock.dataset.id = apt.id;

                    // Aplicar colores dinámicos desde el backend
                    if (apt.backgroundColor) {
                        aptBlock.style.backgroundColor = apt.backgroundColor;
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
                
                // Ya no buscamos bloques en cada slot, se renderizan por profesional
                
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
        // const dateStr = date.toISOString().split('T')[0];
        const dateStr = date.getFullYear() + '-' + 
        String(date.getMonth() + 1).padStart(2, '0') + '-' + 
        String(date.getDate()).padStart(2, '0');
    
        return this.allAppointments.some(apt => {
            const aptDate = new Date(apt.start).toISOString().split('T')[0];
            return aptDate === dateStr;
        });
    }

    getAppointmentsForSlot(date, time, professionalId) {
        // const dateStr = date.toISOString().split('T')[0];
        const dateStr = date.getFullYear() + '-' + 
        String(date.getMonth() + 1).padStart(2, '0') + '-' + 
        String(date.getDate()).padStart(2, '0');
    
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
    
    getBlocksForSlot(date, time, professionalId, blocksToRender) {
        // const dateStr = date.toISOString().split('T')[0];
        const dateStr = date.getFullYear() + '-' + 
        String(date.getMonth() + 1).padStart(2, '0') + '-' + 
        String(date.getDate()).padStart(2, '0');
    
        const slotStartMinutes = time;
        const slotDuration = this.timeInterval || 30;
        
        return blocksToRender.filter(block => {
            const blockDate = new Date(block.start).toISOString().split('T')[0];
            const blockStart = new Date(block.start);
            const blockStartMinutes = blockStart.getHours() * 60 + blockStart.getMinutes();
            
            // Solo mostrar el bloque en el slot donde COMIENZA
            // El bloque debe comenzar dentro de este slot específico
            const blockStartsInThisSlot = (
                blockStartMinutes <= slotStartMinutes && 
                blockStartMinutes < slotStartMinutes + slotDuration
            );
            
            return blockDate === dateStr && 
                   blockStartsInThisSlot && 
                   block.extendedProps.professionalId == professionalId;
        });
    }
    
    // Nueva función para obtener bloques por profesional
    getBlocksForProfessional(date, professionalId, blocksToRender) {
        const dateStr = date.getFullYear() + '-' + 
            String(date.getMonth() + 1).padStart(2, '0') + '-' + 
            String(date.getDate()).padStart(2, '0');
        
        return blocksToRender.filter(block => {
            const blockDate = new Date(block.start).toISOString().split('T')[0];
            return blockDate === dateStr && 
                   block.extendedProps.professionalId == professionalId;
        });
    }
    
    // Nueva función para renderizar un bloque completo
    renderSingleBlock(block, professionalId, businessStartTime, slotDuration, timeSlots, businessEndTime) {
        const blockStart = new Date(block.start);
        const blockEnd = new Date(block.end);
        const blockStartMinutes = blockStart.getHours() * 60 + blockStart.getMinutes();
        const blockEndMinutes = blockEnd.getHours() * 60 + blockEnd.getMinutes();
        
        
        // NUEVA LÓGICA: Recortar el bloque al rango visible
        const visibleStartMinutes = Math.max(blockStartMinutes, businessStartTime);
        const visibleEndMinutes = Math.min(blockEndMinutes, businessEndTime);
        
        // Si el bloque está completamente fuera del rango visible, no renderizar
        if (visibleStartMinutes >= visibleEndMinutes || visibleEndMinutes <= businessStartTime || visibleStartMinutes >= businessEndTime) {
            return;
        }
        
        // Calcular posición y altura basándose en la parte visible
        const offsetFromBusinessStart = visibleStartMinutes - businessStartTime;
        const visibleDurationMinutes = visibleEndMinutes - visibleStartMinutes;
        
        const topPosition = (offsetFromBusinessStart / slotDuration) * 35; // 35px por slot
        const height = Math.max((visibleDurationMinutes / slotDuration) * 35, 15); // Mínimo 15px de altura
        
        // Crear elemento del bloque
        const blockElement = document.createElement('div');
        blockElement.className = block.className ?? 'block-element professional-block-event';
        blockElement.dataset.id = block.id;
        blockElement.style.backgroundColor = block.backgroundColor || '#f8d7da';
        
        // Contenido del bloque - mostrar horario original, no el recortado
        blockElement.innerHTML = `
            <div class="block-time">${new Date(block.start).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})} - ${new Date(block.end).toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}</div>
            <div class="block-title">${block.title}</div>
        `;
        
        if (block.extendedProps.type !== 'non-working') {
            blockElement.addEventListener('click', (event) => {
                event.stopPropagation();
                const blockData = {
                    id: block.id,
                    title: block.title,
                    start: blockStart,
                    end: blockEnd,
                    reason: block.extendedProps?.reason || 'Sin motivo especificado',
                    professionalName: block.extendedProps?.professionalName,
                    blockType: block.extendedProps?.blockType,
                    blockId: block.extendedProps?.blockId,
                    professionalId: block.extendedProps?.professionalId
                };
                this.showBlockDetails(blockData, event);
            });
        }
        
        
        // Aplicar estilos - posicionamiento relativo a timeSlots
        blockElement.style.position = 'absolute';
        blockElement.style.top = `${topPosition}px`;
        blockElement.style.height = `${height}px`;
        blockElement.style.minHeight = '15px';
        blockElement.style.zIndex = '5';
        blockElement.style.borderRadius = '4px';
        blockElement.style.padding = '2px 4px';
        blockElement.style.fontSize = '0.75rem';
        blockElement.style.overflow = 'hidden';
        blockElement.style.pointerEvents = 'auto';
        
        // Calcular la posición horizontal dentro de timeSlots
        const professionals = this.getFilteredProfessionals();
        const professionalIndex = professionals.findIndex(p => p.id == professionalId);
        
        // Calcular el ancho y posición de la columna del profesional
        const timeLabelWidth = 80; // Ancho de la columna de tiempo
        const availableWidth = timeSlots.offsetWidth - timeLabelWidth;
        const columnWidth = availableWidth / professionals.length;
        const leftOffset = timeLabelWidth + (columnWidth * professionalIndex);
        
        blockElement.style.left = `${leftOffset + 2}px`; // +2px margen
        blockElement.style.width = `${columnWidth - 4}px`; // -4px para márgenes
        
        // Asegurar que timeSlots tenga position relative
        if (getComputedStyle(timeSlots).position === 'static') {
            timeSlots.style.position = 'relative';
        }
        
        // Agregar directamente al contenedor timeSlots
        timeSlots.appendChild(blockElement);
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

    async loadBusinessHours(forceReload = false) {
        // Si tenemos cache y no se fuerza la recarga, usar cache
        if (this.businessHoursCache && !forceReload) {
            return this.businessHoursCache;
        }

        const selectedLocationId = document.getElementById('locationFilter').value;
        const selectedProfessionals = this.getSelectedProfessionals();
        
        try {
            // Construir URL con parámetros de consulta
            const params = new URLSearchParams();
            if (selectedLocationId) {
                params.append('locationId', selectedLocationId);
            }
            if (selectedProfessionals.length > 0) {
                selectedProfessionals.forEach(id => {
                    params.append('professionalIds[]', id);
                });
            }
            
            const url = `/agenda/business-hours?${params.toString()}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al cargar horarios de negocio');
            }
            
            const data = await response.json();
            
            // Guardar en cache
            this.businessHoursCache = data;
            this.lastBusinessHoursUpdate = Date.now();
            
            return data;
        } catch (error) {
            console.error('Error loading business hours:', error);
            
            // Retornar valores por defecto
            const defaultHours = {
                startTime: '08:00',
                endTime: '18:00',
                slotMinTime: '08:00:00',
                slotMaxTime: '18:00:00',
                slotDuration: '00:15:00',
                daysOfWeek: [1, 2, 3, 4, 5]
            };
            this.businessHoursCache = defaultHours;
            return defaultHours;
        }
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
                this.validateTimeSlotAvailability();
            });
        }
        
        // Cambio de servicio en modal
        const modalService = document.getElementById('modal-service');
        if (modalService) {
            modalService.addEventListener('change', (e) => {
                this.validateTimeSlotAvailability();
                // Mostrar selects de hora de fin si hay un servicio seleccionado
                this.toggleTimeUntilContainer(e.target.value);
            });
        }
        
        // Cambio de fecha en modal
        const appointmentDate = document.getElementById('appointment-date');
        if (appointmentDate) {
            appointmentDate.addEventListener('change', () => {
                this.validateTimeSlotAvailability();
            });
        }

        // Validación de horas y disponibilidad
        const hourFromSelect = document.getElementById('appointment-hour-from');
        const minuteFromSelect = document.getElementById('appointment-minute-from');
        const hourTo = document.getElementById('appointment-hour-to');
        const minuteTo = document.getElementById('appointment-minute-to');
        
        if (hourFromSelect) {
            hourFromSelect.addEventListener('change', () => {
                this.calculateEndTimeFromService(); // Recalcular hora de fin
                this.validateTimeRange();
                this.validateTimeSlotAvailability();
            });
        }
        if (minuteFromSelect) {
            minuteFromSelect.addEventListener('change', () => {
                this.calculateEndTimeFromService(); // Recalcular hora de fin
                this.validateTimeRange();
                this.validateTimeSlotAvailability();
            });
        }
        if (hourTo) {
            hourTo.addEventListener('change', () => {
                this.validateTimeRange();
            });
        }
        if (minuteTo) {
            minuteTo.addEventListener('change', () => {
                this.validateTimeRange();
            });
        }
    }

    // Nueva función para calcular hora de fin basada en la duración del servicio
    calculateEndTimeFromService() {
        const serviceSelect = document.getElementById('modal-service');
        const hourFrom = document.getElementById('appointment-hour-from');
        const minuteFrom = document.getElementById('appointment-minute-from');
        const hourTo = document.getElementById('appointment-hour-to');
        const minuteTo = document.getElementById('appointment-minute-to');
        
        if (!serviceSelect || !serviceSelect.value || !hourFrom || !minuteFrom || !hourTo || !minuteTo) {
            return;
        }
        
        // Extraer la duración del texto del servicio seleccionado
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        const serviceText = selectedOption.textContent;
        const durationMatch = serviceText.match(/\((\d+)min/);
        
        if (!durationMatch) {
            console.warn('No se pudo extraer la duración del servicio');
            return;
        }
        
        const serviceDurationMinutes = parseInt(durationMatch[1]);
        const fromHour = parseInt(hourFrom.value) || 0;
        const fromMinute = parseInt(minuteFrom.value) || 0;
        
        // Calcular hora de fin
        const totalFromMinutes = fromHour * 60 + fromMinute;
        const totalToMinutes = totalFromMinutes + serviceDurationMinutes;
        
        const toHour = Math.floor(totalToMinutes / 60);
        const toMinute = totalToMinutes % 60;
        
        // Validar que no exceda las 24 horas
        if (toHour >= 24) {
            hourTo.value = '23';
            minuteTo.value = '59';
        } else {
            hourTo.value = toHour.toString().padStart(2, '0');
            minuteTo.value = toMinute.toString().padStart(2, '0');
        }
    }

    // Nueva función para mostrar/ocultar contenedor de hora de fin
    toggleTimeUntilContainer(serviceId) {
        const timeUntilContainer = document.getElementById('time-until-container');
        if (timeUntilContainer) {
            if (serviceId && serviceId !== '') {
                timeUntilContainer.classList.remove('hide');
                // Calcular hora de fin basada en la duración del servicio
                this.calculateEndTimeFromService();
            } else {
                timeUntilContainer.classList.add('hide');
            }
        }
    }

    // Nueva función para establecer hora de fin por defecto
    setDefaultEndTime() {
        const hourTo = document.getElementById('appointment-hour-to');
        const minuteTo = document.getElementById('appointment-minute-to');
        const hourFrom = document.getElementById('appointment-hour-from');
        const minuteFrom = document.getElementById('appointment-minute-from');
        
        if (hourTo && minuteTo && hourFrom && minuteFrom) {
            // Si no hay valores seleccionados, usar hora de inicio + 1 hora
            if (!hourTo.value || !minuteTo.value) {
                const fromHour = parseInt(hourFrom.value) || 9;
                const fromMinute = parseInt(minuteFrom.value) || 0;
                
                let toHour = fromHour + 1;
                let toMinute = fromMinute;
                
                // Ajustar si pasa de 23 horas
                if (toHour > 23) {
                    toHour = 23;
                    toMinute = 59;
                }
                
                hourTo.value = toHour.toString().padStart(2, '0');
                minuteTo.value = toMinute.toString().padStart(2, '0');
            }
        }
    }

    // Nueva función para validar rango de tiempo
    validateTimeRange() {
        const hourFrom = document.getElementById('appointment-hour-from');
        const minuteFrom = document.getElementById('appointment-minute-from');
        const hourTo = document.getElementById('appointment-hour-to');
        const minuteTo = document.getElementById('appointment-minute-to');
        
        if (hourFrom && minuteFrom && hourTo && minuteTo) {
            const fromTime = parseInt(hourFrom.value) * 60 + parseInt(minuteFrom.value);
            const toTime = parseInt(hourTo.value) * 60 + parseInt(minuteTo.value);
            
            const timeUntilContainer = document.getElementById('time-until-container');
            let errorElement = document.getElementById('time-validation-error');
            
            if (toTime <= fromTime) {
                // Mostrar error
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.id = 'time-validation-error';
                    errorElement.className = 'text-danger small mt-1';
                    errorElement.textContent = 'La hora de fin debe ser posterior a la hora de inicio';
                    timeUntilContainer.appendChild(errorElement);
                }
                return false;
            } else {
                // Remover error si existe
                if (errorElement) {
                    errorElement.remove();
                }
                return true;
            }
        }
        return true;
    }
    
    async loadAppointmentsData() {
        const params = new URLSearchParams();
        
        // Determinar el tipo de vista actual
        const viewType = this.determineAutoView();
        
        // Agregar filtro de ubicación
        const selectedLocationId = document.getElementById('locationFilter').value;
        if (selectedLocationId) {
            params.append('location', selectedLocationId);
        }
        
        // Agregar filtro de fecha según la vista
        if (this.currentDate) {
            if (viewType === 'week') {
                const currentDate = new Date(this.currentDate);
                const dayOfWeek = currentDate.getDay();
                const startOfWeek = new Date(currentDate);
                startOfWeek.setDate(currentDate.getDate() - dayOfWeek - 1);
                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(startOfWeek.getDate() + 6);
                
                const startStr = startOfWeek.toISOString().split('T')[0];
                const endStr = endOfWeek.toISOString().split('T')[0];
                
                params.append('start', startStr);
                params.append('end', endStr);
            } else {
                // Formatear fecha local sin conversión UTC
                const dateStr = this.currentDate.getFullYear() + '-' + 
                    String(this.currentDate.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(this.currentDate.getDate()).padStart(2, '0');
                params.append('date', dateStr);
            }
        }
        
        // Obtener profesionales seleccionados
        const selectedProfessionals = this.getSelectedProfessionals();
        if (selectedProfessionals.length > 0) {
            selectedProfessionals.forEach(profId => {
                params.append('professionals[]', profId);
            });
        } else {
            this.allProfessionals.forEach(prof => {
                params.append('professionals[]', prof.id);
            });
        }
        
        if (this.currentFilters.service) {
            params.append('service', this.currentFilters.service);
        }
        
        const response = await fetch(`/agenda/appointments?${params.toString()}`);
        return await response.json();
    }

    async loadAppointments() {
        try {
            // Usar el método coordinador que carga ambos
            await this.loadAppointmentsAndBlocks();
        } catch (error) {
            console.error('Error loading appointments:', error);
            this.allAppointments = [];
        }
    }

    async loadBlocks() {
        try {
            const params = new URLSearchParams();
            
            // Determinar el tipo de vista actual
            const viewType = this.determineAutoView();
            
            // Agregar filtro de ubicación
            const selectedLocationId = document.getElementById('locationFilter').value;
            if (selectedLocationId) {
                params.append('location', selectedLocationId);
            }
            
            // Agregar filtro de fecha según la vista
            if (this.currentDate) {
                if (viewType === 'week') {
                    // Vista semanal: enviar start y end (domingo a sábado)
                    const currentDate = new Date(this.currentDate);
                    
                    // Calcular el domingo de la semana actual
                    const dayOfWeek = currentDate.getDay() // 0 = domingo, 1 = lunes, etc.
                    const startOfWeek = new Date(currentDate);
                    startOfWeek.setDate(currentDate.getDate() - dayOfWeek - 1);
                    
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
                    // Formatear fecha local sin conversión UTC
                    const dateStr = this.currentDate.getFullYear() + '-' + 
                        String(this.currentDate.getMonth() + 1).padStart(2, '0') + '-' + 
                        String(this.currentDate.getDate()).padStart(2, '0');
                    params.append('start', dateStr);
                    params.append('end', dateStr);
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
            
            const response = await fetch(`/agenda/blocks?${params.toString()}`);
            const blocks = await response.json();
            
            return blocks;
        } catch (error) {
            console.error('Error loading blocks:', error);
            return [];
        }
    }

    async loadNonWorkingBlocks() {
        const selectedProfessionals = this.getSelectedProfessionals();
        
        // Solo cargar si hay múltiples profesionales seleccionados o ninguno (todos)
        if (selectedProfessionals.length === 1) {
            return [];
        }
        
        try {
            const locationId = document.getElementById('location-filter')?.value || '';
            const params = new URLSearchParams({
                include_non_working: 'true',
                start: this.calendar ? this.calendar.view.activeStart.toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
                end: this.calendar ? this.calendar.view.activeEnd.toISOString().split('T')[0] : new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0]
            });
            
            // Agregar profesionales seleccionados
            const professionalsToLoad = selectedProfessionals.length > 0 ? selectedProfessionals : this.allProfessionals.map(p => p.id.toString());
            professionalsToLoad.forEach(id => {
                params.append('professionals[]', id);
            });
            
            if (locationId) {
                params.append('location_id', locationId);
            }
            
            const response = await fetch(`/agenda/blocks?${params.toString()}`);
            const events = await response.json();
            
            // Filtrar solo los bloques no laborables
            return events.filter(event => 
                event.extendedProps && event.extendedProps.type === 'non-working'
            );
        } catch (error) {
            console.error('Error cargando bloques no laborables:', error);
            return [];
        }
    }

    async loadAppointmentsAndBlocks() {
        try {
            // Cargar appointments y blocks (que ya incluye non-working) en paralelo
            const [appointments, blocks] = await Promise.all([
                this.loadAppointmentsData(), // Método auxiliar que solo obtiene datos
                this.loadBlocks() // Ya incluye todos los tipos de bloques
            ]);
            
            // Combinar para FullCalendar
            const allEvents = [...appointments, ...blocks];
            
            // Guardar appointments por separado
            this.allAppointments = appointments;
            // Guardar blocks por separado
            this.allBlocks = blocks;
            
            // Actualizar vista según el modo actual
            if (this.calendar && document.getElementById('calendar').style.display !== 'none') {
                // Vista semanal (FullCalendar) - un profesional
                this.calendar.removeAllEvents();
                this.calendar.addEventSource(allEvents);
            }
            
            if (document.getElementById('professionalsColumns').style.display !== 'none') {
                // Vista diaria (Columnas) - múltiples profesionales
                this.renderProfessionalColumns(this.currentDate, appointments, blocks);
            }
            
            return { appointments, blocks, allEvents };
        } catch (error) {
            console.error('Error loading appointments and blocks:', error);
            this.allAppointments = [];
            return { appointments: [], blocks: [], allEvents: [] };
        }
    }
    
    handleDateSelect(info) {
        // Manejar selección de fecha para crear nueva cita
        const startTime = info.start;
        const endTime = info.end;
        const startTimeUtc = this.toUTC(startTime);
        
        // alan
        const professionals = this.getSelectedProfessionals();
        // Formatear la fecha para el modal
        const appointmentDate = startTime.toISOString().split('T')[0];
        const appointmentTime = startTimeUtc.toTimeString().slice(0, 5);
        
        // Abrir el modal de turnos con los datos de fecha y hora
        this.openAppointmentModal({
            isNew: true,
            date: appointmentDate,
            time: appointmentTime,
            start: startTimeUtc,
            end: endTime,
            professionalId: professionals.length === 1? professionals[0] : null 
        });
        
        // Limpiar la selección del calendario
        this.calendar.unselect();
    }

    toUTC(date) {
        return new Date(date.getTime() + (date.getTimezoneOffset() * 60000));
    }

    handleEventClick(info) {
        console.log('Event clicked:', info.event);
        if (info.event.extendedProps?.type === 'block') {
            // Si es un bloque, mostrar detalles
            const blockData = {
                id: info.event.id,
                title: info.event.title,
                start: this.toUTC(info.event.start),
                end: this.toUTC(info.event.end),
                reason: info.event.extendedProps?.reason || 'Sin motivo especificado',
                professionalName: info.event.extendedProps?.professionalName,
                blockType: info.event.extendedProps?.blockType,
                blockId: info.event.extendedProps?.blockId,
                professionalId: info.event.extendedProps?.professionalId
            };
            
            this.showBlockDetails(blockData, info.jsEvent);
        } else {
            const eventData = {
                id: info.event.id,
                title: info.event.title,
                start: this.toUTC(info.event.start),
                end: this.toUTC(info.event.end),
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
    }

    showAppointmentDetails(eventData, clickEvent) {
        console.log('showAppointmentDetails', eventData);
        // Remover tooltip existente si hay uno
        this.removeExistingTooltip();
        this.clearTimeValidationMessage();
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
        this.setupTooltipCloseEventsOfAppointment(tooltip);
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
                
                <!-- Selectores de Estado con Colores -->
                <div class="status-selectors">
                    <div class="status-label">Cambiar estado:</div>
                    <div class="status-options">
                        <button class="status-option bg-info text-white ${data.status === 'scheduled' ? 'active' : ''}" 
                                data-status="scheduled" 
                                title="Reservado">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Reservado</span>
                        </button>
                        <button class="status-option bg-primary text-white ${data.status === 'confirmed' ? 'active' : ''}" 
                                data-status="confirmed" 
                                title="Confirmado">
                            <i class="fas fa-check-circle"></i>
                            <span>Confirmado</span>
                        </button>
                        <button class="status-option bg-success text-white ${data.status === 'completed' ? 'active' : ''}" 
                                data-status="completed" 
                                title="Completado"">
                            <i class="fas fa-check-double"></i>
                            <span>Completado</span>
                        </button>
                        <button class="status-option bg-danger text-white ${data.status === 'cancelled' ? 'active' : ''}" 
                                data-status="cancelled" 
                                title="Cancelado">
                            <i class="fas fa-times-circle"></i>
                            <span>Cancelado</span>
                        </button>
                        <button class="status-option bg-warning text-white ${data.status === 'no_show' ? 'active' : ''}" 
                                data-status="no_show" 
                                title="No se presentó">
                            <i class="fas fa-user-times"></i>
                            <span>No se presentó</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Guardar datos del turno en el tooltip para las acciones
        tooltip._appointmentData = data.appointmentData;
        return tooltip;
    }

    showBlockDetails(blockData, clickEvent) {
        console.log('showBlockDetails', blockData);
        
        // Remover tooltip existente
        this.removeExistingTooltip();
        
        // Formatear datos para el tooltip
        const formattedData = {
            reason: blockData.reason,
            professionalName: blockData.professionalName,
            date: blockData.start.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }),
            time: `${blockData.start.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})} - ${blockData.end.toLocaleTimeString('es-ES', {hour: '2-digit', minute: '2-digit'})}`,
            blockType: blockData.blockType,
            blockData: blockData
        };
        
        // Crear y mostrar tooltip
        const tooltip = this.createBlockTooltip(formattedData);
        document.body.appendChild(tooltip);
        
        // Posicionar tooltip
        this.positionTooltip(tooltip, clickEvent);
        
        // Configurar eventos
        this.setupTooltipCloseEventsOfBlock(tooltip);
        // this.setupBlockActions(tooltip, blockData);
    }
    
    createBlockTooltip(data) {
        // Obtener el template del tooltip de bloques
        const template = document.getElementById('block-tooltip-template');
        
        if (!template) {
            console.error('Template del tooltip de bloques no encontrado');
            return null;
        }
        
        // Clonar el contenido del template
        const tooltip = template.content.cloneNode(true).firstElementChild;
        
        // Obtener información extendida del bloque
        const blockData = data.blockData;
        const extendedProps = blockData.extendedProps || {};
        
        // Determinar si mostrar opciones de serie
        const isMultiDay = data.blockType === 'monthly_recurring' || data.blockType === 'date_range' || data.blockType === 'weekdays_pattern';
        
        // Mostrar/ocultar botones según el tipo de bloqueo
        const singleButtons = tooltip.querySelector('[data-multi-day="false"]');
        const multiButtons = tooltip.querySelector('[data-multi-day="true"]');
        
        if (isMultiDay) {
            singleButtons.style.display = 'none';
            multiButtons.style.display = 'flex';
        } else {
            singleButtons.style.display = 'flex';
            multiButtons.style.display = 'none';
        }
        
        // Llenar datos básicos
        tooltip.querySelector('[data-field="reason"]').textContent = data.reason;
        tooltip.querySelector('[data-field="professionalName"]').textContent = data.professionalName;
        tooltip.querySelectorAll('[data-field="professionalName"]').forEach(el => {
            el.textContent = data.professionalName;
        });
        tooltip.querySelector('[data-field="date"]').textContent = data.date;
        tooltip.querySelector('[data-field="time"]').textContent = data.time;
        
        // Generar descripción detallada del tipo de bloque
        const blockTypeInfo = this.getBlockTypeDescription(data.blockType, extendedProps);
        tooltip.querySelector('[data-field="blockType"]').textContent = blockTypeInfo.description;
        
        // Mostrar información específica según el tipo
        this.populateBlockSpecificInfo(tooltip, data.blockType, extendedProps, data);
        
        // Guardar datos del bloque en el tooltip para las acciones
        tooltip._blockData = data.blockData;
        
        // Agregar la clase show para hacerlo visible
        setTimeout(() => {
            tooltip.classList.add('show');
        }, 10);
        
        return tooltip;
    }

    getBlockTypeDescription(blockType, extendedProps) {
        switch(blockType) {
            case 'single_day':
                return {
                    description: 'Un solo día',
                    icon: '📅'
                };
            case 'date_range':
                return {
                    description: 'Rango de fechas (todos los días)',
                    icon: '📆'
                };
            case 'weekdays_pattern':
                return {
                    description: 'Patrón de días específicos',
                    icon: '🗓️'
                };
            case 'monthly_recurring':
                return {
                    description: 'Repetición mensual',
                    icon: '🔄'
                };
            default:
                return {
                    description: 'Bloqueo único',
                    icon: '🚫'
                };
        }
    }

    populateBlockSpecificInfo(tooltip, blockType, extendedProps, data) {
        // Ocultar todas las filas opcionales primero
        const optionalRows = ['dateRange', 'weekdays', 'recurrence'];
        optionalRows.forEach(field => {
            const container = tooltip.querySelector(`[data-field-container="${field}"]`);
            if (container) container.style.display = 'none';
        });
        
        // Mostrar horario
        const scheduleElement = tooltip.querySelector('[data-field="schedule"]');
        if (scheduleElement) {
            if (extendedProps.allDay) {
                scheduleElement.textContent = 'Todo el día';
            } else {
                scheduleElement.textContent = data.time;
            }
        }
        
        // Información específica según el tipo
        switch(blockType) {
            case 'single_day':
                // Para un solo día, la fecha ya se muestra en el campo date
                break;
                
            case 'date_range':
                const dateRangeContainer = tooltip.querySelector('[data-field-container="dateRange"]');
                const dateRangeElement = tooltip.querySelector('[data-field="dateRange"]');
                if (dateRangeContainer && dateRangeElement) {
                    dateRangeContainer.style.display = 'flex';
                    const startDate = new Date(extendedProps.startDate).toLocaleDateString('es-ES');
                    const endDate = new Date(extendedProps.endDate).toLocaleDateString('es-ES');
                    dateRangeElement.textContent = `${startDate} - ${endDate}`;
                }
                break;
                
            case 'weekdays_pattern':
                const weekdaysContainer = tooltip.querySelector('[data-field-container="weekdays"]');
                const weekdaysElement = tooltip.querySelector('[data-field="weekdays"]');
                const dateRangeContainer2 = tooltip.querySelector('[data-field-container="dateRange"]');
                const dateRangeElement2 = tooltip.querySelector('[data-field="dateRange"]');
                
                if (weekdaysContainer && weekdaysElement) {
                    weekdaysContainer.style.display = 'flex';
                    const weekdayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                    const selectedDays = extendedProps.weekdaysPattern ? 
                        extendedProps.weekdaysPattern.split(',').map(d => weekdayNames[parseInt(d)]) : [];
                    weekdaysElement.textContent = selectedDays.join(', ');
                }
                
                if (dateRangeContainer2 && dateRangeElement2) {
                    dateRangeContainer2.style.display = 'flex';
                    const startDate = new Date(extendedProps.startDate).toLocaleDateString('es-ES');
                    const endDate = new Date(extendedProps.endDate).toLocaleDateString('es-ES');
                    dateRangeElement2.textContent = `${startDate} - ${endDate}`;
                }
                break;
                
            case 'monthly_recurring':
                const recurrenceContainer = tooltip.querySelector('[data-field-container="recurrence"]');
                const recurrenceElement = tooltip.querySelector('[data-field="recurrence"]');
                
                if (recurrenceContainer && recurrenceElement) {
                    recurrenceContainer.style.display = 'flex';
                    const dayOfMonth = extendedProps.monthlyDayOfMonth || new Date(extendedProps.startDate).getDate();
                    let recurrenceText = `Día ${dayOfMonth} de cada mes`;
                    
                    if (extendedProps.monthlyEndDate) {
                        const endDate = new Date(extendedProps.monthlyEndDate).toLocaleDateString('es-ES');
                        recurrenceText += ` hasta ${endDate}`;
                    } else {
                        recurrenceText += ' (indefinidamente)';
                    }
                    
                    recurrenceElement.textContent = recurrenceText;
                }
                break;
        }
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

    setupTooltipCloseEventsOfAppointment(tooltip) {
        // Cerrar al hacer clic en el botón de cerrar
        const closeBtn = tooltip.querySelector('[data-action="close"]');
        closeBtn.addEventListener('click', () => {
            this.removeTooltip(tooltip);
        });
        // Configurar botones de acción
        const editBtn = tooltip.querySelector('[data-action="edit"]');
        const deleteBtn = tooltip.querySelector('[data-action="delete"]');
        if(editBtn) {
            editBtn.addEventListener('click', () => {
                this.removeTooltip(tooltip);
                this.openAppointmentModal(tooltip._appointmentData);
            });
        }
            
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                this.removeTooltip(tooltip);
                this.showConfirmationModal(
                    '¿Eliminar este turno?',
                    'Esta acción no se puede deshacer.',
                    'fas fa-trash',
                    'btn-danger',
                    () => {
                        this.updateAppointmentStatus(tooltip._appointmentData.id, 'cancelled');
                        this.loadAppointments();
                    }
                );
            });
        }
            
        // Configurar selectores de estado
        const statusOptions = tooltip.querySelectorAll('.status-option');
        statusOptions.forEach(option => {
            option.addEventListener('click', async (e) => {
                e.stopPropagation();
                const newStatus = option.getAttribute('data-status');
                const appointmentId = tooltip._appointmentData.id;
                // Actualizar visualmente el estado activo
                statusOptions.forEach(opt => opt.classList.remove('active'));
                option.classList.add('active');
                // Actualizar el estado en el servidor
                try {
                    await this.updateAppointmentStatus(appointmentId, newStatus);
                    this.showAlert('Estado actualizado correctamente', 'success');
                    this.removeTooltip(tooltip);
                    this.loadAppointments();
                } catch (error) {
                    console.error('Error updating status:', error);
                    this.showAlert('Error al actualizar el estado', 'error');
                }
            });
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

    setupTooltipCloseEventsOfBlock(tooltip) {
        // Cerrar al hacer clic en el botón de cerrar
        const closeBtn = tooltip.querySelector('[data-action="close"]');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.removeTooltip(tooltip);
            });
        }
        
        // Configurar botones de eliminación de bloque
        const deleteButtons = tooltip.querySelectorAll('.tooltip-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const action = button.dataset.action;
                if (action === 'close') return;
                this.removeTooltip(tooltip);
                this.handleBlockDelete(tooltip._blockData, action);
            });
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
    
    // Método para ocultar tooltip
    hideBlockTooltip() {
        const tooltip = document.querySelector('.block-tooltip');
        if (tooltip) {
            tooltip.classList.remove('show');
            setTimeout(() => {
                tooltip.remove();
            }, 200); // Esperar a que termine la animación
        }
    }

    handleBlockDelete(blockData, action) {
        debugger;
        let message, subtext;
        
        switch (action) {
            case 'delete':
                message = '¿Está seguro de eliminar este bloqueo?';
                subtext = 'Esta acción no se puede deshacer.';
                break;
            case 'delete-single':
                message = '¿Eliminar solo este bloqueo?';
                subtext = 'Se eliminará únicamente este día del bloqueo.';
                break;
            case 'delete-series':
                message = '¿Eliminar toda la serie de bloqueos?';
                subtext = 'Se eliminarán todos los bloqueos relacionados.';
                break;
        }

        this.showConfirmationModal(
            message,
            subtext,
            'fas fa-trash text-danger',
            'btn-danger',
            async () => {
                await this.deleteBlock(blockData.blockId);
                // Cerrar tooltip
                const tooltip = document.querySelector('.block-tooltip');
                if (tooltip) {
                    this.removeTooltip(tooltip);
                }
                // Recargar eventos
                await this.loadAppointmentsAndBlocks();
            }
        );
    }

    async deleteBlock(blockId) {
        try {
            const response = await fetch(`/agenda/blocks/${blockId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                this.showAlert('Bloqueo eliminado correctamente', 'success');
            } else {
                this.showAlert(result.message || 'Error al eliminar el bloqueo', 'error');
            }
        } catch (error) {
            console.error('Error al eliminar bloqueo:', error);
            this.showAlert('Error al eliminar el bloqueo', 'error');
        }
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
        const existingAppointmentTooltip = document.querySelector('.appointment-tooltip');
        if (existingAppointmentTooltip) {
            this.removeTooltip(existingAppointmentTooltip);
        }
        const existingBlockTooltips = document.querySelectorAll('.block-tooltip:not([data-testid="block-tooltip-container"])');
        existingBlockTooltips.forEach(tooltip => {
            this.removeTooltip(tooltip);
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
            'scheduled': 'Reservado',
            'confirmed': 'Confirmado',
            'completed': 'Completado',
            'cancelled': 'Cancelado',
            'no_show': 'No se presentó'
        };
        return statusMap[status] || 'No se presentó';
    }

    getStatusBadgeClass(status) {
        const classMap = {
            'scheduled': 'bg-info',
            'confirmed': 'bg-primary',
            'completed': 'bg-success',
            'cancelled': 'bg-danger',
            'no_show': 'bg-warning'
        };
        return classMap[status] || 'bg-primary';
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
                bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
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
        this.clearTimeValidationMessage();
        
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
                // Preseleccionar hora y minuto si se proporcionan
                if (data.time) {
                    const [hours, minutes] = data.time.split(':');
                    const hourFrom = document.getElementById('appointment-hour-from');
                    const minuteFrom = document.getElementById('appointment-minute-from');
                    if (hourFrom && hours) {
                        hourFrom.value = hours.padStart(2, '0');
                    }
                    if (minuteFrom && minutes) {
                        minuteFrom.value = minutes.padStart(2, '0');
                    }
                }
                if (data.professionalId) {
                    const profesional = document.getElementById('modal-professional');
                    if (profesional) {
                        profesional.value = data.professionalId;
                        this.loadProfessionalServices(data.professionalId);
                    }
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
        
        // Mostrar el botón de crear nuevo paciente
        const createNewPatientBtn = document.getElementById('create-new-patient');
        if (createNewPatientBtn) {
            createNewPatientBtn.style.display = 'block';
        }
        
        // Ocultar formulario de nuevo paciente
        const newPatientForm = document.getElementById('new-patient-form');
        if (newPatientForm) {
            newPatientForm.style.display = 'none';
        }
        
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
        
        // Ocultar contenedor de hora de fin
        const timeUntilContainer = document.getElementById('time-until-container');
        if (timeUntilContainer) {
            timeUntilContainer.classList.add('hide');
        }
        
        // Remover mensaje de error si existe
        const errorElement = document.getElementById('time-validation-error');
        if (errorElement) {
            errorElement.remove();
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
        
        // Extraer y establecer la hora de inicio
        const timeStr = startDate.toTimeString().slice(0, 5); // HH:MM
        const [hour, minute] = timeStr.split(':');
        
        const hourFromSelect = document.getElementById('appointment-hour-from');
        const minuteFromSelect = document.getElementById('appointment-minute-from');
        
        if (hourFromSelect && minuteFromSelect) {
            // En lugar de reemplazar las opciones, solo seleccionar la correcta
            hourFromSelect.value = hour.padStart(2, '0');
            minuteFromSelect.value = minute.padStart(2, '0');
        }
        
        // Establecer hora de fin si existe
        if (data.end) {
            const endDate = new Date(data.end);
            const endTimeStr = endDate.toTimeString().slice(0, 5);
            const [endHour, endMinute] = endTimeStr.split(':');
            
            const hourToSelect = document.getElementById('appointment-hour-to');
            const minuteToSelect = document.getElementById('appointment-minute-to');
            
            if (hourToSelect && minuteToSelect) {
                // Solo seleccionar la opción correcta, no reemplazar todas las opciones
                hourToSelect.value = endHour.padStart(2, '0');
                minuteToSelect.value = endMinute.padStart(2, '0');
                
                // Mostrar contenedor de hora de fin
                const timeUntilContainer = document.getElementById('time-until-container');
                if (timeUntilContainer) {
                    timeUntilContainer.classList.remove('hide');
                }
            }
        }
        
        document.getElementById('appointment-notes').value = data.notes || '';
        
        // Cargar servicios del profesional y esperar a que termine
        if (data.professionalId) {
            await this.loadProfessionalServices(data.professionalId, data.serviceId);
            // Mostrar contenedor de hora de fin si hay servicio
            if (data.serviceId) {
                this.toggleTimeUntilContainer(data.serviceId);
            }
        }
        
        // Buscar y seleccionar paciente
        if (data.patientId && data.patientName) {
            this.selectPatient(data.patientId, data.patientName, data.patientEmail, data.patientPhone);
        }
        
        // // Ahora cargar horarios disponibles
        // if (data.professionalId && data.serviceId && startDate) {
        //     this.loadAvailableSlots();
        // }
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
            if (selectedServiceId && 
                document.getElementById('appointment-date').value && 
                document.getElementById('appointment-hour-from').value && 
                document.getElementById('appointment-minute-from').value) {
                this.validateTimeSlotAvailability();
            }
            
        } catch (error) {
            console.error('Error loading professional services:', error);
        }
    }

    // Reemplazar la función loadAvailableSlots() actual con esta nueva implementación
    async validateTimeSlotAvailability() {
        const professionalId = document.getElementById('modal-professional').value;
        const serviceId = document.getElementById('modal-service').value;
        const date = document.getElementById('appointment-date').value;
        const hourFrom = document.getElementById('appointment-hour-from').value;
        const minuteFrom = document.getElementById('appointment-minute-from').value;
        const appointmentId = document.getElementById('appointment-id').value; // Obtener ID del turno si existe
        
        // Limpiar mensajes previos
        this.clearTimeValidationMessage();
        
        // Solo validar si tenemos todos los datos necesarios
        if (!professionalId || !serviceId || !date || !hourFrom || !minuteFrom) {
            return;
        }
        
        const selectedTime = `${hourFrom}:${minuteFrom}`;
        
        try {
            let url = `/agenda/validate-slot?professional=${professionalId}&service=${serviceId}&date=${date}&time=${selectedTime}`;
            // Agregar appointmentId si existe (modo edición)
            if (appointmentId) {
                url += `&appointmentId=${appointmentId}`;
            }
            const response = await fetch(url);
            const result = await response.json();
            
            if (!result.available) {
                this.showTimeValidationMessage(
                    result.message,
                    'warning'
                );
            }
            
        } catch (error) {
            console.error('Error validating time slot:', error);
        }
    }

    clearTimeValidationMessage() {
        const messageContainer = document.getElementById('time-validation-message');
        if (!messageContainer) return;

        messageContainer.classList.add('hide');
    }
    
    showTimeValidationMessage(message, type = 'warning') {
        // Buscar o crear contenedor para el mensaje
        let messageContainer = document.getElementById('time-validation-message');
        
        messageContainer.className = `alert alert-${type} mt-2`;
        messageContainer.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        `;
        messageContainer.classList.remove('hide');
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
            <div class="alert alert-info d-flex justify-content-between align-items-start">
                <div>
                    <strong>Paciente seleccionado:</strong> ${patientName}<br>
                    <small>${patientEmail} - ${patientPhone}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" id="remove-selected-patient" title="Remover paciente seleccionado">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        // Ocultar el botón de crear nuevo paciente
        const createNewPatientBtn = document.getElementById('create-new-patient');
        if (createNewPatientBtn) {
            createNewPatientBtn.style.display = 'none';
        }
        // Agregar event listener al botón de remover
        const removeBtn = document.getElementById('remove-selected-patient');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => this.removeSelectedPatient());
        }
    }

    showNewPatientForm() {
        document.getElementById('new-patient-form').style.display = 'block';
        document.getElementById('patient-results').innerHTML = '';
    }

    /**
     * Valida el formulario de citas
     */
    validateAppointmentForm() {
        const requiredFields = [
            { id: 'appointment-date', name: 'Fecha' },
            { id: 'appointment-hour-from', name: 'Hora desde' },
            { id: 'appointment-minute-from', name: 'Minuto desde' },
            { id: 'modal-professional', name: 'Profesional' },
            { id: 'modal-service', name: 'Servicio' }
        ];

        // Validar campos requeridos
        for (const field of requiredFields) {
            const element = document.getElementById(field.id);
            if (!element || !element.value.trim()) {
                this.showAlert(`El campo ${field.name} es requerido`, 'error');
                element?.focus();
                return false;
            }
        }

        // Validar que se haya seleccionado un paciente o se esté creando uno nuevo
        const selectedPatientId = document.getElementById('selected-patient-id').value;
        const newPatientForm = document.getElementById('new-patient-form');
        const isNewPatientVisible = newPatientForm.style.display !== 'none';
        
        if (!selectedPatientId && !isNewPatientVisible) {
            this.showAlert('Debe seleccionar un paciente o crear uno nuevo', 'error');
            return false;
        }

        // Si se está creando un paciente nuevo, validar campos requeridos
        if (isNewPatientVisible) {
            const patientName = document.getElementById('patient-name').value.trim();
            const patientPhone = document.getElementById('patient-phone').value.trim();
            
            if (!patientName) {
                this.showAlert('El nombre del paciente es requerido', 'error');
                document.getElementById('patient-name').focus();
                return false;
            }
            
            if (!patientPhone) {
                this.showAlert('El teléfono del paciente es requerido', 'error');
                document.getElementById('patient-phone').focus();
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene los datos del formulario de citas
     */
    getAppointmentFormData() {
        const data = {
            date: document.getElementById('appointment-date').value,
            appointment_time_from: document.getElementById('appointment-hour-from').value + ':' + document.getElementById('appointment-minute-from').value,
            professional_id: document.getElementById('modal-professional').value,
            service_id: document.getElementById('modal-service').value,
            location_id: document.getElementById('appointment-location-id').value, // Agregar esta línea
            notes: document.getElementById('appointment-notes').value || ''
        };

        // Agregar ID del turno si existe (para edición)
        const appointmentId = document.getElementById('appointment-id').value;
        if (appointmentId) {
            data.id = appointmentId;
        }

        // Agregar hora hasta si está visible
        const timeUntilContainer = document.getElementById('time-until-container');
        if (timeUntilContainer && !timeUntilContainer.classList.contains('hide')) {
            const hourTo = document.getElementById('appointment-hour-to').value;
            const minuteTo = document.getElementById('appointment-minute-to').value;
            if (hourTo && minuteTo) {
                data.appointment_time_to = hourTo + ':' + minuteTo;
            }
        }

        // Agregar datos del paciente
        const selectedPatientId = document.getElementById('selected-patient-id').value;
        const newPatientForm = document.getElementById('new-patient-form');
        const isNewPatientVisible = newPatientForm.style.display !== 'none';

        if (selectedPatientId) {
            data.patient_id = selectedPatientId;
        } else if (isNewPatientVisible) {
            // Datos para crear nuevo paciente
            data.patient_name = document.getElementById('patient-name').value.trim();
            data.patient_email = document.getElementById('patient-email').value.trim();
            data.patient_phone = document.getElementById('patient-phone').value.trim();
            data.patient_birth_date = document.getElementById('patient-birth-date').value;
        }

        return data;
    }
    // Agregar este método en la clase AgendaManager
    closeModal() {
        const modal = document.getElementById('appointmentModal');
        if (modal) {
            const bootstrapModal = bootstrap.Modal.getInstance(modal);
            if (bootstrapModal) {
                bootstrapModal.hide();
            }
        }
    }

    async saveAppointment() {
        if (!this.validateAppointmentForm()) {
            return;
        }
        
        const appointmentData = this.getAppointmentFormData();
        const isEditing = appointmentData.id && appointmentData.id.trim() !== '';
        
        try {
            let url, method;
            
            if (isEditing) {
                url = `/agenda/appointment/${appointmentData.id}`;
                method = 'PUT';
            } else {

                url = '/agenda/appointment';
                method = 'POST';
            }
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(appointmentData)
            });
            
            const result = await response.json();

            if (result.success) {
                const message = isEditing ? 'Cita actualizada exitosamente' : 'Cita creada exitosamente';
                this.showAlert(message, 'success');
                this.closeModal();
                this.loadAppointments();
            } else {
                // Verificar si es un error de disponibilidad
                if (result.error_type === 'availability') {
                    this.showAvailabilityAlert(result.error, appointmentData);
                } else if (result.error_type === 'block') {
                    this.showBlockAlert(result.error, appointmentData);
                } else {
                    debugger;
                    const errorMessage = isEditing ? 'Error al actualizar la cita' : 'Error al crear la cita';
                    this.showAlert(result.error || errorMessage, 'error');
                }
            }
            
        } catch (error) {
            console.error('Error saving appointment:', error);
            const errorMessage = isEditing ? 'Error de conexión al actualizar la cita' : 'Error de conexión al guardar la cita';
            this.showAlert(errorMessage, 'error');
        }
    }

    showBlockAlert(message, appointmentData, appointmentId = null) {
        const modal = document.getElementById('blockAlertModal');
        const messageContainer = document.getElementById('blockAlertMessage');
        const forceButton = document.getElementById('forceCreateBlockedAppointment');
        
        // Configurar el mensaje
        messageContainer.innerHTML = `
            <p class="text-muted mb-3">${message}</p>
        `;
        
        // Limpiar event listeners previos
        const newForceButton = forceButton.cloneNode(true);
        forceButton.parentNode.replaceChild(newForceButton, forceButton);
        
        // Agregar nuevo event listener
        newForceButton.addEventListener('click', () => {
            // Verificar si es edición o creación
            const isEditing = appointmentId || (appointmentData.id && appointmentData.id.trim() !== '');
            
            if (isEditing) {
                const id = appointmentId || appointmentData.id;
                this.forceUpdateAppointment(id, appointmentData);
            } else {
                this.forceCreateAppointment(appointmentData);
            }
            
            bootstrap.Modal.getInstance(modal).hide();
        });
        
        // Mostrar el modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }


    /**
     * Muestra el modal de alerta de disponibilidad
     */
    showAvailabilityAlert(message, appointmentData, appointmentId = null) {
        const modal = document.getElementById('availabilityAlertModal');
        const messageContainer = document.getElementById('availabilityAlertMessage');
        const forceButton = document.getElementById('forceCreateAppointment');
        
        // Configurar el mensaje
        messageContainer.innerHTML = `
            <p class="text-muted mb-3">${message}</p>
        `;
        
        // Limpiar event listeners previos
        const newForceButton = forceButton.cloneNode(true);
        forceButton.parentNode.replaceChild(newForceButton, forceButton);
        
        // Agregar nuevo event listener
        newForceButton.addEventListener('click', () => {
            // Verificar si es edición o creación
            const isEditing = appointmentId || (appointmentData.id && appointmentData.id.trim() !== '');
            
            if (isEditing) {
                const id = appointmentId || appointmentData.id;
                this.forceUpdateAppointment(id, appointmentData);
            } else {
                this.forceCreateAppointment(appointmentData);
            }
            
            bootstrap.Modal.getInstance(modal).hide();
        });
        
        // Mostrar el modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }
    
    /**
     * Fuerza la creación de una cita saltándose las validaciones
     */
    async forceCreateAppointment(appointmentData) {
        try {
            // Agregar el parámetro force
            appointmentData.force = true;
            
            const response = await fetch('/agenda/appointment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(appointmentData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('Cita creada exitosamente (forzada)', 'success');
                bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
                this.loadAppointments();
            } else {
                debugger;
                this.showAlert(result.error || 'Error al crear la cita', 'error');
            }
            
        } catch (error) {
            console.error('Error forcing appointment creation:', error);
            this.showAlert('Error de conexión al crear la cita', 'error');
        }
    }

    async updateAppointment(appointmentId, formData) {
        try {
            const response = await fetch(`/agenda/appointment/${appointmentId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('Turno actualizado correctamente', 'success');
                bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
                this.loadAppointments();
            } else {
                // Verificar si es un error de disponibilidad
                if (result.error_type === 'availability') {
                    // Crear appointmentData a partir de formData y appointmentId
                    const appointmentData = {
                        ...formData,
                        id: appointmentId
                    };
                    this.showAvailabilityAlert(result.error, appointmentData, appointmentId);
                } else {
                    this.showAlert(result.error || 'Error al actualizar el turno', 'error');
                }
            }
            
        } catch (error) {
            console.error('Error updating appointment:', error);
            this.showAlert('Error de conexión', 'error');
        }
    }

    /**
     * Fuerza la actualización de una cita saltándose las validaciones
     */
    async forceUpdateAppointment(appointmentId, appointmentData) {
        try {
            // Agregar el parámetro force
            appointmentData.force = true;
            
            const response = await fetch(`/agenda/appointment/${appointmentId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(appointmentData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('Turno actualizado exitosamente (forzado)', 'success');
                bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
                this.loadAppointments();
            } else {
                this.showAlert(result.error || 'Error al actualizar el turno', 'error');
            }
            
        } catch (error) {
            console.error('Error forcing appointment update:', error);
            this.showAlert('Error de conexión al actualizar el turno', 'error');
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
    async changeView() {
        console.log('*******');
        const viewType = this.determineAutoView();
        
        if (viewType === 'week') {
            // Vista semana con FullCalendar
            document.getElementById('calendar').style.display = 'block';
            document.getElementById('professionalsColumns').style.display = 'none';
            
            if (this.calendar) {
                // Actualizar horarios de negocio en FullCalendar
                const businessHours = await this.loadBusinessHours();
                this.calendar.setOption('slotMinTime', businessHours.slotMinTime);
                this.calendar.setOption('slotMaxTime', businessHours.slotMaxTime);
                this.calendar.setOption('slotDuration', businessHours.slotDuration);
                this.calendar.setOption('businessHours', {
                    daysOfWeek: businessHours.daysOfWeek,
                    startTime: businessHours.startTime,
                    endTime: businessHours.endTime
                });
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

    // Método para remover paciente seleccionado
    removeSelectedPatient() {
        // Limpiar el campo hidden del paciente seleccionado
        const selectedPatientId = document.getElementById('selected-patient-id');
        if (selectedPatientId) {
            selectedPatientId.value = '';
        }
        
        // Limpiar la información del paciente seleccionado
        const selectedPatientInfo = document.getElementById('selected-patient-info');
        if (selectedPatientInfo) {
            selectedPatientInfo.innerHTML = '';
        }
        
        // Mostrar nuevamente el botón de crear nuevo paciente
        const createNewPatientBtn = document.getElementById('create-new-patient');
        if (createNewPatientBtn) {
            createNewPatientBtn.style.display = 'block';
        }
        
        // Limpiar resultados de búsqueda si existen
        const patientResults = document.getElementById('patient-results');
        if (patientResults) {
            patientResults.innerHTML = '';
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
        // Limpiar cache antes de cambiar vista
        this.clearBusinessHoursCache();
        this.changeView(); // Recalcular vista automáticamente
        this.loadAppointments();
    }
    
    // Limpiar cache cuando cambian los filtros
    clearBusinessHoursCache() {
        this.businessHoursCache = null;
        this.lastBusinessHoursUpdate = null;
    }
}

// Sincronizar filtro de location con campo hidden del modal Y actualizar grilla
document.getElementById('locationFilter').addEventListener('change', function() {
    const selectedLocationId = this.value;
    
    // Sincronizar con el campo hidden del modal
    document.getElementById('appointment-location-id').value = selectedLocationId;
    
    // Limpiar cache y actualizar la grilla de la agenda
    if (window.agendaManager) {
        window.agendaManager.clearBusinessHoursCache();
        window.agendaManager.changeView();
        window.agendaManager.loadAppointments();
    }
});

// Al abrir el modal, asegurar que el location_id esté sincronizado
document.getElementById('appointmentModal').addEventListener('show.bs.modal', function() {
    const selectedLocationId = document.getElementById('locationFilter').value;
    document.getElementById('appointment-location-id').value = selectedLocationId;
});

// Funcionalidad del botón flotante
function initFloatingActionButton() {
    const floatingBtn = document.getElementById('floatingActionBtn');
    const floatingOptions = document.getElementById('floatingOptions');
    const floatingIcon = document.getElementById('floatingIcon');
    const reserveOption = document.getElementById('reserveOption');
    const blockOption = document.getElementById('blockOption');
    
    let isOpen = false;
    
    // Toggle del menú flotante
    floatingBtn.addEventListener('click', function() {
        isOpen = !isOpen;
        
        if (isOpen) {
            floatingOptions.classList.add('show');
            floatingBtn.classList.add('active');
            floatingIcon.className = 'fas fa-times';
        } else {
            floatingOptions.classList.remove('show');
            floatingBtn.classList.remove('active');
            floatingIcon.className = 'fas fa-plus';
        }
    });
    
    // Cerrar menú al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.floating-action-container') && isOpen) {
            closeFloatingMenu();
        }
    });
    
    // Opción de reservar
    reserveOption.addEventListener('click', function() {
        closeFloatingMenu();
        // El modal se abre automáticamente por data-bs-toggle
        // Aquí puedes agregar lógica adicional si es necesaria
        console.log('Abriendo modal de reserva');
    });
    
    // Opción de bloqueo horario
    blockOption.addEventListener('click', function() {
        closeFloatingMenu();
        // Abrir modal de bloqueo
        const blockModal = new bootstrap.Modal(document.getElementById('blockModal'));
        blockModal.show();
        console.log('Abriendo modal de bloqueo horario');
    });
    
    function closeFloatingMenu() {
        isOpen = false;
        floatingOptions.classList.remove('show');
        floatingBtn.classList.remove('active');
        floatingIcon.className = 'fas fa-plus';
    }
}

function toggleBlockTypeFields(blockType) {
    // Ocultar todos los grupos
    document.getElementById('singleDayGroup').style.display = 'none';
    document.getElementById('dateRangeGroup').style.display = 'none';
    document.getElementById('patternGroup').style.display = 'none';
    document.getElementById('monthlyRecurringGroup').style.display = 'none';
    
    // Mostrar el grupo correspondiente
    switch(blockType) {
        case 'single_day':
            document.getElementById('singleDayGroup').style.display = 'block';
            break;
        case 'date_range':
            document.getElementById('dateRangeGroup').style.display = 'block';
            break;
        case 'weekdays_pattern':
            document.getElementById('patternGroup').style.display = 'block';
            break;
        case 'monthly_recurring':
            document.getElementById('monthlyRecurringGroup').style.display = 'block';
            break;
    }
}

function updateExampleText(blockType) {
    const exampleDiv = document.getElementById('exampleText');
    const exampleContent = document.getElementById('exampleContent');
    
    let example = '';
    switch(blockType) {
        case 'single_day':
            example = 'Bloquea solo el día seleccionado';
            break;
        case 'date_range':
            example = 'Bloquea todos los días en el rango seleccionado';
            break;
        case 'weekdays_pattern':
            example = 'Bloquea solo los días de la semana seleccionados en el período';
            break;
        case 'monthly_recurring':
            example = 'Bloquea el mismo día de cada mes automáticamente';
            break;
    }
    
    if (example) {
        exampleContent.textContent = example;
        exampleDiv.style.display = 'block';
    } else {
        exampleDiv.style.display = 'none';
    }
}

function updateMonthlyPreview() {
    const showPreview = document.getElementById('showMonthlyPreview').checked;
    const startDate = document.getElementById('monthlyStartDate').value;
    const previewDiv = document.getElementById('monthlyPreview');
    const previewList = document.getElementById('monthlyPreviewList');
    
    if (!showPreview || !startDate) {
        previewDiv.style.display = 'none';
        return;
    }
    
    const date = new Date(startDate);
    const dayOfMonth = date.getDate();
    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    let previewHtml = '';
    for (let i = 0; i < 6; i++) {
        const previewDate = new Date(date.getFullYear(), date.getMonth() + i, dayOfMonth);
        const monthName = monthNames[previewDate.getMonth()];
        const year = previewDate.getFullYear();
        previewHtml += `<li>${dayOfMonth} de ${monthName} ${year}</li>`;
    }
    
    previewList.innerHTML = previewHtml;
    previewDiv.style.display = 'block';
}

function saveBlock() {
    const form = document.getElementById('blockForm');
    const formData = new FormData(form);
    
    // Recopilar datos del formulario
    const blockData = {
        professional_id: document.getElementById('blockProfessional').value,
        block_type: document.getElementById('blockType').value,
        reason: document.getElementById('blockReason').value,
        all_day: document.getElementById('allDay').checked
    };
    
    // Agregar campos específicos según el tipo
    switch(blockData.block_type) {
        case 'single_day':
            blockData.block_date = document.getElementById('blockDate').value;
            break;
        case 'date_range':
            blockData.start_date = document.getElementById('startDate').value;
            blockData.end_date = document.getElementById('endDate').value;
            break;
        case 'weekdays_pattern':
            blockData.pattern_start_date = document.getElementById('patternStartDate').value;
            blockData.pattern_end_date = document.getElementById('patternEndDate').value;
            
            // Recopilar días seleccionados
            const weekdays = [];
            ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'].forEach((day, index) => {
                const checkbox = document.getElementById(day);
                if (checkbox && checkbox.checked) {
                    weekdays.push(index); // 0=Domingo, 1=Lunes, etc.
                }
            });
            blockData.weekdays = weekdays;
            break;
        case 'monthly_recurring':
            blockData.monthly_start_date = document.getElementById('monthlyStartDate').value;
            const monthlyEndDate = document.getElementById('monthlyEndDate').value;
            if (monthlyEndDate) {
                blockData.monthly_end_date = monthlyEndDate;
            }
            break;
    }
    
    // Agregar horarios si no es todo el día
    if (!blockData.all_day) {
        const startHour = document.getElementById('startTime-hour').value;
        const startMinute = document.getElementById('startTime-minute').value;
        const endHour = document.getElementById('endTime-hour').value;
        const endMinute = document.getElementById('endTime-minute').value;
        
        // Validar que se hayan seleccionado hora y minuto
        if (startHour && startMinute) {
            blockData.start_time = startHour + ':' + startMinute;
        }
        
        if (endHour && endMinute) {
            blockData.end_time = endHour + ':' + endMinute;
        }
        
        // Validar que se hayan proporcionado ambos tiempos si no es todo el día
        if (!blockData.start_time || !blockData.end_time) {
            showNotification('Por favor seleccione hora de inicio y fin', 'error');
            return;
        }
    }
    
    // Validar datos requeridos
    if (!blockData.professional_id || !blockData.reason) {
        showNotification('Por favor complete todos los campos requeridos', 'error');
        return;
    }
    
    // Enviar datos al servidor
    fetch('/agenda/blocks', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(blockData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('blockModal'));
            modal.hide();
            
            // Limpiar formulario
            document.getElementById('blockForm').reset();
            
            // Recargar calendario
            if (agendaManager && agendaManager.calendar) {
                agendaManager.calendar.refetchEvents();
            }
            
            // Mostrar notificación de éxito en lugar de alert
            showNotification('Bloqueo creado exitosamente', 'success');
        } else {
            // Mostrar notificación de error en lugar de alert
            showNotification(data.message || data.error || 'Error al crear el bloqueo', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Mostrar notificación de error en lugar de alert
        showNotification('Error de conexión. Por favor, inténtalo de nuevo.', 'error');
    });
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', async function() {
    agendaManager = new AgendaManager();
    await agendaManager.init();
    
    // Inicializar botón flotante
    initFloatingActionButton();
    
    // Inicializar modal de bloqueo
    initBlockModal();

    // Ejemplos dinámicos para repetición
    function updateRepeatExample() {
        const repeatType = document.getElementById('repeatType').value;
        const exampleText = document.getElementById('repeatExampleText');
        
        const examples = {
            'daily': 'Ej: Si eliges 15 de enero, se bloqueará todos los días: 15/01, 16/01, 17/01...',
            'weekly': 'Ej: Si eliges lunes 15 de enero, se bloqueará todos los lunes: 15/01, 22/01, 29/01...',
            'monthly': 'Ej: Si eliges 15 de enero, se bloqueará el día 15 de cada mes: 15/01, 15/02, 15/03...'
        };
        
        exampleText.textContent = examples[repeatType] || examples['daily'];
    }

    function loadProfessionalsForBlock() {
        const blockProfessionalSelect = document.getElementById('blockProfessional');
        if (!blockProfessionalSelect) return;
        
        // Limpiar opciones existentes
        blockProfessionalSelect.innerHTML = '<option value="">Seleccionar profesional...</option>';
        
        // Obtener profesionales desde el array global o desde los checkboxes
        let professionals = [];
        
        // Si existe la instancia de agenda, usar sus profesionales
        if (window.agenda && window.agenda.allProfessionals) {
            professionals = window.agenda.allProfessionals;
        } else {
            // Fallback: obtener desde los checkboxes del filtro
            const professionalCheckboxes = document.querySelectorAll('input[name="professionals[]"][value]:not([value="all"])');
            professionals = Array.from(professionalCheckboxes).map(checkbox => ({
                id: checkbox.value,
                name: checkbox.nextElementSibling.textContent.trim()
            }));
        }
        
        // Agregar opciones al select
        professionals.forEach(professional => {
            const option = document.createElement('option');
            option.value = professional.id;
            option.textContent = professional.name;
            blockProfessionalSelect.appendChild(option);
        });
    }
    
    function initBlockModal() {
        const blockModal = document.getElementById('blockModal');
        const blockForm = document.getElementById('blockForm');
        const blockTypeSelect = document.getElementById('blockType');
        const saveBlockBtn = document.getElementById('saveBlock');
        const allDayCheckbox = document.getElementById('allDay');
        const timeRangeDiv = document.getElementById('timeRange');
        
        if (!blockModal || !blockForm) {
            console.log('Block modal elements not found');
            return;
        }
        
        // Manejar cambios en el tipo de bloqueo
        if (blockTypeSelect) {
            blockTypeSelect.addEventListener('change', function() {
                toggleBlockTypeFields(this.value);
                updateExampleText(this.value);
            });
        }
        
        // Manejar checkbox de todo el día
        if (allDayCheckbox && timeRangeDiv) {
            allDayCheckbox.addEventListener('change', function() {
                timeRangeDiv.style.display = this.checked ? 'none' : 'block';
            });
        }
        
        // Manejar vista previa mensual
        const showMonthlyPreview = document.getElementById('showMonthlyPreview');
        const monthlyStartDate = document.getElementById('monthlyStartDate');
        
        if (showMonthlyPreview && monthlyStartDate) {
            showMonthlyPreview.addEventListener('change', updateMonthlyPreview);
            monthlyStartDate.addEventListener('change', updateMonthlyPreview);
        }
        
        // Guardar bloqueo
        if (saveBlockBtn) {
            saveBlockBtn.addEventListener('click', function() {
                saveBlock();
            });
        }
        
        // Cargar profesionales en el select
        loadProfessionalsForBlock();
    }

    // Event listener para cambio de tipo de repetición
    document.getElementById('repeatType')?.addEventListener('change', updateRepeatExample);
});