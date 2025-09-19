/**
 * ===== BOOKING WIZARD SYSTEM - BOOTSTRAP COMPATIBLE =====
 * Sistema de reservas online con wizard de 3 pasos
 */

class BookingWizard {
    /**
     * Precarga los datos del paciente en el formulario de contacto
     * @param {Object} patientData - Datos del paciente
     */
    preloadPatientData(patientData) {
        if (!this.elements.contactForm || !patientData) return;
        
        const fields = {
            'firstname': patientData.firstName || '',
            'lastname': patientData.lastName || '',
            'email': patientData.email || '',
            'phone': patientData.phone || ''
        };
        
        Object.entries(fields).forEach(([fieldName, value]) => {
            const field = this.elements.contactForm.querySelector(`#patient-${fieldName}`);
            if (field) {
                field.value = value;
            }
        });
    }
    
    /**
     * Muestra información sobre la modificación del turno
     */
    showModificationInfo() {
        if (!this.elements.modificationInfo || !this.state.preloadData) return;
        
        // Mostrar el contenedor de información
        this.elements.modificationInfo.classList.remove('d-none');
        
        // Llenar con los datos del turno original
        const originalDate = new Date(this.state.preloadData.originalDate);
        const dateFormatted = originalDate.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeFormatted = this.state.preloadData.originalTime;
        
        // Actualizar el texto
        const infoText = this.elements.modificationInfo.querySelector('.modification-info-text');
        if (infoText) {
            infoText.innerHTML = `Estás modificando tu turno original del <strong>${dateFormatted}</strong> a las <strong>${timeFormatted}</strong>`;
        }
        
        // Agregar un banner informativo adicional
        const container = document.querySelector('.container-fluid');
        if (container) {
            const banner = document.createElement('div');
            banner.className = 'alert alert-info d-flex align-items-center mb-4';
            banner.innerHTML = `
                <i class="fas fa-edit me-2"></i>
                <div>
                    <h5 class="alert-heading mb-1">Modificando turno existente</h5>
                    <p class="mb-0">
                        Turno original: ${dateFormatted} a las ${timeFormatted}
                        <br>
                        <small class="text-muted">Puedes cambiar la fecha, hora o datos de contacto.</small>
                    </p>
                </div>
            `;
            container.insertBefore(banner, container.firstChild);
        }
    }
    constructor() {
        // Estado del wizard
        this.state = {
            currentStep: 1,
            selectedService: null,
            selectedProfessional: null,
            selectedDate: null,
            selectedTime: null,
            selectedLocationId: null,
            domain: null,
            isLoading: false,
            wizardStep1Complete: false,
            isModification: false,
            preloadData: null
        };

        // Estado del selector de fechas
        this.dateSelector = {
            currentStartDate: new Date(),
            daysLoaded: [],
            isLoadingMore: false,
            daysPerBatch: 10
        };

        // Referencias DOM
        this.elements = {
            // Wizard navigation
            prevStepBtn: null,
            nextStepBtn: null,
            confirmBookingBtn: null,
            
            // Progress indicators
            progressSteps: null,
            
            // Wizard steps
            step1: null,
            step2: null,
            step3: null,
            
            // Step 1: Professional selection
            professionalCards: null,
            
            // Step 2: Date and time selection
            daysContainer: null,
            prevDateBtn: null,
            nextDateBtn: null,
            timeslotsContainer: null,
            timeslotsLoading: null,
            timeslotsContent: null,
            
            // Step 3: Contact form
            contactForm: null,
            
            // Selection display
            selectedProfessionalDisplay: null,
            selectedDateDisplay: null,
            selectedTimeDisplay: null
        };

        // Calendar state
        this.calendar = {
            currentDate: new Date(),
            selectedDate: null
        };

        this.init();
    }

    /**
     * Inicializa el wizard
     */
    init() {
        this.initializeElements();
        this.bindEvents();
        this.loadInitialData();
        this.initializeWizard();
        this.initializeServiceDescriptions();
    }

    /**
     * Inicializa las referencias a elementos DOM
     */
    initializeElements() {
        // Navigation buttons
        this.elements.prevStepBtn = document.getElementById('prev-step');
        this.elements.nextStepBtn = document.getElementById('next-step');
        this.elements.confirmBookingBtn = document.getElementById('confirm-booking');
        
        // Progress steps
        this.elements.progressSteps = document.querySelectorAll('.progress-step');
        
        // Wizard steps
        this.elements.step1 = document.getElementById('step-1');
        this.elements.step2 = document.getElementById('step-2');
        this.elements.step3 = document.getElementById('step-3');
        
        // Professional cards
        this.elements.professionalCards = document.querySelectorAll('.professional-card');
        
        // Date selector elements - ACTUALIZADO
        this.elements.daysContainer = document.getElementById('days-container');
        this.elements.prevDateBtn = document.getElementById('prev-date');
        this.elements.nextDateBtn = document.getElementById('next-date');
        this.elements.prevDaysBtn = document.getElementById('prev-days');
        this.elements.nextDaysBtn = document.getElementById('next-days');
        
        // Timeslots
        this.elements.timeslotsContainer = document.getElementById('timeslots-container');
        this.elements.timeslotsLoading = document.getElementById('timeslots-loading');
        this.elements.timeslotsContent = document.getElementById('timeslots-content');
        
        // Contact form
        this.elements.contactForm = document.getElementById('contact-form');
        
        // Selection displays
        this.elements.selectedProfessionalDisplay = document.getElementById('selected-professional');
        this.elements.selectedDateDisplay = document.getElementById('selected-date');
        this.elements.selectedTimeDisplay = document.getElementById('selected-time');
        this.elements.selectedPriceDisplay = document.getElementById('selected-price');
        
        // Service details container
        this.elements.serviceDetails = document.getElementById('service-details');
        
        // Modification info container
        this.elements.modificationInfo = document.getElementById('modification-info');
    }

    /**
     * Carga datos iniciales del template
     */
    loadInitialData() {
        if (window.bookingData) {
            this.state.selectedService = window.bookingData.selectedService;
            this.state.selectedServiceDuration = window.bookingData.selectedServiceDuration;
            this.state.selectedProfessional = window.bookingData.selectedProfessional;
            this.state.wizardStep1Complete = window.bookingData.wizardStep1Complete;
            this.state.domain = window.bookingData.domain;
            this.state.selectedLocationId = window.bookingData.locationId;
            
            // Manejar datos de modificación
            if (window.bookingData.isModification && window.bookingData.preloadData) {
                this.state.isModification = true;
                this.state.preloadData = window.bookingData.preloadData;
                
                // Preseleccionar datos del turno existente
                this.state.selectedService = window.bookingData.preloadData.serviceId;
                this.state.selectedProfessional = window.bookingData.preloadData.professionalId;
                this.state.selectedLocationId = window.bookingData.preloadData.locationId;
                this.state.wizardStep1Complete = false;
                
                // Mostrar información de modificación
                this.showModificationInfo();
            }
        }
    }

    /**
     * Selecciona automáticamente el primer día disponible
     */
    selectFirstAvailableDate() {
        const firstAvailableDay = document.querySelector('.day-item:not(.disabled)');
        if (firstAvailableDay) {
            const dateString = firstAvailableDay.getAttribute('data-date');
            if (dateString) {
                this.selectDate(dateString);
            }
        }
    }

    /**
     * Actualiza el precio mostrado
     */
    updatePriceDisplay() {
        if (this.elements.selectedPriceDisplay && this.state.selectedService) {
            const price = this.state.selectedService.price || '$0';
            this.elements.selectedPriceDisplay.textContent = price;
        }
    }

    /**
     * Resetea la hora seleccionada
     */
    resetSelectedTime() {
        // Limpiar selección visual
        document.querySelectorAll('.timeslot-btn.selected').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        this.state.selectedTime = null;
        this.state.selectedDateTime = null;
        
        // Resetear display usando updateTimeDisplay
        this.updateTimeDisplay(null);
        
        // Deshabilitar botón de continuar
        this.updateNavigationButtons();
    }
    
    /**
     * Actualiza el display de tiempo con rango de duración
     */
    updateTimeDisplay(timeString) {
        if (!timeString || !window.bookingData?.selectedServiceDuration) {
            if (this.elements.selectedTimeDisplay) {
                this.elements.selectedTimeDisplay.textContent = timeString || 'Seleccionar hora';
            }
            return;
        }
        
        // Convertir la hora de inicio a objeto Date
        const [hours, minutes] = timeString.split(':').map(Number);
        const startTime = new Date();
        startTime.setHours(hours, minutes, 0, 0);
        
        // Calcular la hora de fin sumando la duración
        const endTime = new Date(startTime);
        endTime.setMinutes(endTime.getMinutes() + window.bookingData.selectedServiceDuration);
        
        // Formatear las horas
        const startFormatted = startTime.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
        const endFormatted = endTime.toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: false 
        });
        
        if (this.elements.selectedTimeDisplay) {
            this.elements.selectedTimeDisplay.textContent = `${startFormatted} - ${endFormatted}`;
        }
    }
    
    /**
     * Inicializa el estado del wizard
     */
    initializeWizard() {
        // Determinar el paso inicial basado en el estado
        if (this.state.isModification && this.state.preloadData) {
            this.state.currentStep = 1;
            this.showStep(1);
            this.updateProgressIndicator();
            
            // Preseleccionar el profesional después de que el DOM esté listo
            setTimeout(() => {
                const professionalId = this.state.preloadData.professionalId;
                const professionalName = this.state.preloadData.professionalName || 'Profesional';
                this.selectProfessional(professionalId, professionalName, false);
            }, 100);
        } else if (this.state.wizardStep1Complete) {
            this.state.currentStep = 2;
            this.showStep(2);
            this.updateProgressIndicator();
        } else {
            this.showStep(1);
        }
        
        this.updateNavigationButtons();
        // CAMBIO: Usar generateDateSelector en lugar de generateCalendar
        this.generateDateSelector();
    }

    /**
     * Siguiente paso
     */
    nextStep() {
        if (this.canProceedToNextStep() && this.state.currentStep < 3) {
            this.showStep(this.state.currentStep + 1);
            
            // Si vamos al paso 2, generar el selector de fechas
            if (this.state.currentStep === 2) {
                this.generateDateSelector();
            }
        }
    }

    /**
     * Genera el selector de fechas horizontal
     */
    async generateDateSelector() {
        if (!this.elements.daysContainer) {
            console.error('Days container element not found');
            return;
        }

        // Cargar fechas disponibles si no están cargadas
        if (this.dateSelector.daysLoaded.length === 0) {
            await this.loadInitialDates();
        }

        this.renderDates();
    }

    /**
     * Carga las fechas iniciales
     */
    async loadInitialDates() {
        try {
            const startDate = new Date();
            const endDate = new Date();
            endDate.setDate(startDate.getDate() + this.dateSelector.daysPerBatch);

            const dates = await this.loadAvailableDatesRange(startDate, endDate);
            this.dateSelector.daysLoaded = dates;
            this.dateSelector.currentStartDate = startDate;
        } catch (error) {
            console.error('Error loading initial dates:', error);
        }
    }

    /**
     * Carga más fechas cuando se llega al final del scroll
     */
    async loadMoreDates() {
        if (this.dateSelector.isLoadingMore || !this.state.selectedProfessional) return;
        
        this.dateSelector.isLoadingMore = true;
        
        try {
            // Calcular la fecha de inicio para los próximos 10 días
            const lastLoadedDate = new Date(this.dateSelector.daysLoaded[this.dateSelector.daysLoaded.length - 1].date);
            const startDate = new Date(lastLoadedDate);
            startDate.setDate(startDate.getDate() + 1);
            
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + 9); // Cargar exactamente 10 días más
            
            // Cargar las nuevas fechas
            const newDates = await this.loadAvailableDatesRange(startDate, endDate);
            
            if (newDates && newDates.length > 0) {
                // Agregar las nuevas fechas al array existente
                this.dateSelector.daysLoaded = [...this.dateSelector.daysLoaded, ...newDates];
                
                // Actualizar la fecha final
                this.dateSelector.endDate = endDate.toISOString().split('T')[0];
                
                // Re-renderizar las fechas
                this.renderDates();
            }
        } catch (error) {
            console.error('Error loading more dates:', error);
        } finally {
            this.dateSelector.isLoadingMore = false;
        }
    }

    /**
     * Carga las fechas disponibles en un rango específico
     */
    async loadAvailableDatesRange(startDate, endDate) {
        // Primero generar todos los días del rango
        const allDates = this.generateDateRange(startDate, endDate);
        
        if (!this.state.selectedProfessional || !this.state.selectedService) {
            return allDates;
        }

        try {
            const url = this.buildApiUrl('available-dates', {
                professional_id: this.state.selectedProfessional,
                service_id: this.state.selectedService,
                start_date: startDate.toISOString().split('T')[0],
                end_date: endDate.toISOString().split('T')[0],
                location_id: this.state.selectedLocationId
            });
            
            const response = await this.apiRequest(url);
            
            // Crear un mapa de fechas disponibles para búsqueda rápida
            const availableDatesMap = {};
            response.forEach(dateInfo => {
                availableDatesMap[dateInfo.date] = dateInfo;
            });

            // Combinar todos los días con la información de disponibilidad
            return allDates.map(dateInfo => {
                const availableInfo = availableDatesMap[dateInfo.date];
                // Crear fecha local sin conversión de zona horaria
                const [year, month, day] = dateInfo.date.split('-');
                const dateObj = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
                
                if (availableInfo) {
                    return {
                        ...dateInfo,
                        dayNumber: dateObj.getDate(), // Recalcular dayNumber
                        slotsCount: availableInfo.slotsCount || 0,
                        hasSlots: (availableInfo.slotsCount || 0) > 0,
                        monthName: this.getMonthName(dateObj)
                    };
                } else {
                    return {
                        ...dateInfo,
                        dayNumber: dateObj.getDate(), // Recalcular dayNumber
                        slotsCount: 0,
                        hasSlots: false,
                        monthName: this.getMonthName(dateObj)
                    };
                }
            });
        } catch (error) {
            console.error('Error loading available dates:', error);
            return allDates;
        }
    }

    /**
     * Genera un rango de fechas básico
     */
    generateDateRange(startDate, endDate) {
        const dates = [];
        
        // Crear fecha local sin problemas de zona horaria
        let currentDateStr = startDate.toISOString().split('T')[0];
        const endDateStr = endDate.toISOString().split('T')[0];
        const today = new Date().toISOString().split('T')[0]; // Fecha de hoy en formato YYYY-MM-DD
        
        while (currentDateStr <= endDateStr) {
            // Crear fecha local para cada iteración
            const [year, month, day] = currentDateStr.split('-');
            const current = new Date(parseInt(year), parseInt(month) - 1, parseInt(day));
            
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            const monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            
            dates.push({
                date: currentDateStr,
                dayName: dayNames[current.getDay()],
                dayNumber: current.getDate(),
                monthName: monthNames[current.getMonth()],
                slotsCount: 0,
                hasSlots: false,
                isWeekend: current.getDay() === 0 || current.getDay() === 6,
                isToday: currentDateStr === today // Agregar detección del día actual
            });
            
            // Incrementar la fecha como string
            const nextDate = new Date(parseInt(year), parseInt(month) - 1, parseInt(day) + 1);
            currentDateStr = nextDate.toISOString().split('T')[0];
        }
        
        return dates;
    }

    /**
     * Scroll hacia la izquierda de a un día
     */
    scrollDayLeft() {
        if (!this.elements.daysContainer) return;
        
        const dayItems = this.elements.daysContainer.querySelectorAll('.day-item');
        if (dayItems.length === 0) return;
        
        const dayWidth = dayItems[0].offsetWidth + 16; // Ancho del día + margen
        
        this.elements.daysContainer.scrollBy({
            left: -dayWidth,
            behavior: 'smooth'
        });
    }

    /**
     * Scroll hacia la derecha de a un día
     */
    scrollDayRight() {
        if (!this.elements.daysContainer) return;
        
        const dayItems = this.elements.daysContainer.querySelectorAll('.day-item');
        if (dayItems.length === 0) return;
        
        const dayWidth = dayItems[0].offsetWidth + 16; // Ancho del día + margen
        const container = this.elements.daysContainer;
        
        // Verificar si necesitamos cargar más fechas
        const isNearEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - dayWidth * 2;
        
        if (isNearEnd && !this.dateSelector.isLoadingMore) {
            this.loadMoreDates();
        }
        
        container.scrollBy({
            left: dayWidth,
            behavior: 'smooth'
        });
    }

    /**
     * Obtiene el nombre abreviado del mes
     */
    getMonthName(date) {
        const monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 
                          'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return monthNames[date.getMonth()];
    }

    /**
     * Renderiza las fechas en el selector horizontal
     */
    async renderDates() {
        if (!this.elements.daysContainer) return;

        let datesHTML = '';
        
        this.dateSelector.daysLoaded.forEach(dateInfo => {
            const isSelected = this.calendar.selectedDate === dateInfo.date;
            const isDisabled = !dateInfo.hasSlots || dateInfo.slotsCount === 0;
            
            let dayClass = 'day-item';
            if (isSelected) dayClass += ' selected';
            if (isDisabled) dayClass += ' disabled';
            if (dateInfo.isWeekend) dayClass += ' weekend';
            if (dateInfo.isToday) dayClass += ' today'; // Agregar clase para el día actual
            
            // Permitir hacer clic incluso en días sin horarios para mostrar mensaje
            datesHTML += `
                <div class="${dayClass}" data-date="${dateInfo.date}" onclick="bookingWizard.selectDate('${dateInfo.date}')">
                    <div class="day-month">${dateInfo.monthName}</div>
                    <div class="day-name">${dateInfo.dayName}</div>
                    <div class="day-number">${dateInfo.dayNumber}</div>
                </div>
            `;
        });

        this.elements.daysContainer.innerHTML = datesHTML;
        
        // Después de renderizar las fechas, seleccionar automáticamente la primera disponible
        if (!this.state.selectedDate) {
            setTimeout(() => {
                this.selectFirstAvailableDate();
            }, 100);
        }
    }

    /**
     * Vincula eventos
     */
    bindEvents() {
        // Navigation buttons
        if (this.elements.prevStepBtn) {
            this.elements.prevStepBtn.addEventListener('click', () => this.previousStep());
        }
        
        if (this.elements.nextStepBtn) {
            this.elements.nextStepBtn.addEventListener('click', () => this.nextStep());
        }
        
        if (this.elements.confirmBookingBtn) {
            this.elements.confirmBookingBtn.addEventListener('click', () => this.confirmBooking());
        }

        // Professional selection
        this.elements.professionalCards.forEach(card => {
            card.addEventListener('click', (e) => {
                const professionalId = card.dataset.professionalId;
                const professionalName = card.querySelector('.professional-name')?.textContent || 'Profesional';
                this.selectProfessional(professionalId, professionalName);
            });
        });

        // Nuevos botones de navegación de días
        if (this.elements.prevDaysBtn) {
            this.elements.prevDaysBtn.addEventListener('click', () => this.scrollDayLeft());
        }

        if (this.elements.nextDaysBtn) {
            this.elements.nextDaysBtn.addEventListener('click', () => this.scrollDayRight());
        }

        // Contact form submission
        if (this.elements.contactForm) {
            this.elements.contactForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }
    }

    /**
     * Muestra un paso específico del wizard
     */
    showStep(stepNumber) {
        // Ocultar todos los pasos
        [this.elements.step1, this.elements.step2, this.elements.step3].forEach(step => {
            if (step) step.style.display = 'none';
        });

        // Mostrar el paso actual
        const currentStepElement = document.getElementById(`step-${stepNumber}`);
        if (currentStepElement) {
            currentStepElement.style.display = 'block';
        }

        this.state.currentStep = stepNumber;
        this.updateProgressIndicator();
        this.updateNavigationButtons();
        
        // Actualizar visibilidad de detalles del servicio
        this.updateServiceDetailsVisibility();
        
        // Si es el paso 3 y hay datos de modificación, precargar los datos del paciente
        if (stepNumber === 3 && this.state.isModification && this.state.preloadData) {
            // Usar setTimeout para asegurar que el DOM esté completamente renderizado
            setTimeout(() => {
                this.preloadPatientData(this.state.preloadData.patient);
            }, 100);
        }
    }

    /**
     * Actualiza el indicador de progreso
     */
    updateProgressIndicator() {
        this.elements.progressSteps.forEach((step, index) => {
            const stepNumber = index + 1;
            const stepCircle = step.querySelector('.step-circle');
            
            if (stepNumber < this.state.currentStep) {
                // Paso completado
                step.classList.add('completed');
                step.classList.remove('active');
            } else if (stepNumber === this.state.currentStep) {
                // Paso actual
                step.classList.add('active');
                step.classList.remove('completed');
            } else {
                // Paso pendiente
                step.classList.remove('active', 'completed');
            }
        });
    }

    /**
     * Actualiza los botones de navegación
     */
    updateNavigationButtons() {
        // Botón anterior
        if (this.elements.prevStepBtn) {
            this.elements.prevStepBtn.style.display = this.state.currentStep > 1 ? 'block' : 'none';
        }

        // Botón siguiente
        if (this.elements.nextStepBtn) {
            this.elements.nextStepBtn.style.display = this.state.currentStep < 3 ? 'block' : 'none';
            this.elements.nextStepBtn.disabled = !this.canProceedToNextStep();
        }

        // Botón confirmar
        if (this.elements.confirmBookingBtn) {
            this.elements.confirmBookingBtn.style.display = this.state.currentStep === 3 ? 'block' : 'none';
        }
    }

    /**
     * Verifica si se puede proceder al siguiente paso
     */
    canProceedToNextStep() {
        switch (this.state.currentStep) {
            case 1:
                return this.state.selectedProfessional !== null;
            case 2:
                return this.state.selectedDate !== null && this.state.selectedTime !== null;
            case 3:
                return this.validateContactForm();
            default:
                return false;
        }
    }

    /**
     * Paso anterior
     */
    previousStep() {
        if (this.state.currentStep > 1) {
            this.showStep(this.state.currentStep - 1);
        }
    }

    /**
     * Selecciona una fecha
     */
    selectDate(dateString) {
        // Remover selección anterior
        document.querySelectorAll('.day-item.selected').forEach(day => {
            day.classList.remove('selected');
        });

        // Verificar si la fecha está disponible
        const selectedDay = document.querySelector(`[data-date="${dateString}"]`);
        if (selectedDay && selectedDay.classList.contains('disabled')) {
            return; // No permitir seleccionar fechas deshabilitadas
        }

        // Seleccionar nueva fecha
        if (selectedDay) {
            selectedDay.classList.add('selected');
        }

        this.calendar.selectedDate = dateString;
        this.state.selectedDate = dateString;
        
        // Actualizar display de fecha seleccionada
        if (this.elements.selectedDateDisplay) {
            const date = new Date(dateString+'T00:00:00');
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            this.elements.selectedDateDisplay.textContent = date.toLocaleDateString('es-ES', options);
        }

        // Resetear hora seleccionada cuando cambia la fecha
        this.resetSelectedTime();
        
        // Cargar horarios para la fecha seleccionada
        this.loadTimeSlots(dateString);
        this.updateNavigationButtons();
    }

    /**
     * Actualiza el selector de fechas cuando cambia el profesional
     */
    async updateDateSelector() {
        this.dateSelector.daysLoaded = [];
        await this.generateDateSelector();
    }

    /**
     * Carga los horarios disponibles
     */
    async loadTimeSlots(date) {
        if (!this.elements.timeslotsContainer || !this.state.selectedProfessional) return;

        this.setLoading(true, this.elements.timeslotsContainer);

        try {
            const url = this.buildApiUrl('timeslots', {
                professional_id: this.state.selectedProfessional,
                service_id: this.state.selectedService,
                date: date,
                location_id: this.state.selectedLocationId
            });
            
            const timeslots = await this.apiRequest(url);
            this.renderTimeSlots(timeslots);
        } catch (error) {
            console.error('Error loading timeslots:', error);
            this.handleError(error, this.elements.timeslotsContainer, 'Error al cargar los horarios');
        }finally {
            this.setLoading(false, this.elements.timeslotsContainer);
        }
    }

    /**
     * Renderiza los horarios organizados por secciones
     */
    renderTimeSlots(timeslots) {
        if (!this.elements.timeslotsContainer) return;

        this.setLoading(false, this.elements.timeslotsContainer);

        if (!timeslots || timeslots.length === 0) {
            this.elements.timeslotsContainer.innerHTML = `
                <div class="timeslots-empty">
                    <i class="fas fa-clock"></i>
                    <p>No hay horarios disponibles para esta fecha</p>
                </div>
            `;
            return;
        }

        // Organizar horarios por secciones
        const sections = {
            morning: { title: 'Mañana', times: [] },
            afternoon: { title: 'Tarde', times: [] },
            night: { title: 'Noche', times: [] }
        };

        timeslots.forEach(slot => {
            const hour = parseInt(slot.time.split(':')[0]);
            
            if (hour >= 6 && hour < 12) {
                sections.morning.times.push(slot);
            } else if (hour >= 12 && hour < 18) {
                sections.afternoon.times.push(slot);
            } else {
                sections.night.times.push(slot);
            }
        });

        let html = '<div class="timeslots-sections">';

        Object.entries(sections).forEach(([key, section]) => {
            if (section.times.length > 0) {
                html += `
                    <div class="timeslot-section">
                        <div class="timeslot-section-header">
                            <i class="fas fa-${key === 'morning' ? 'sun' : key === 'afternoon' ? 'cloud-sun' : 'moon'}"></i>
                            ${section.title}
                        </div>
                        <div class="timeslot-section-content">
                            <div class="timeslots-grid">
                `;

                section.times.forEach(slot => {
                    const isSelected = this.state.selectedTime === slot.time;
                    const buttonClass = `timeslot-btn ${isSelected ? 'selected' : ''} ${!slot.available ? 'disabled' : ''}`;
                    
                    html += `
                        <button 
                            class="${buttonClass}" 
                            data-time="${slot.time}" 
                            data-datetime="${slot.datetime}"
                            ${!slot.available ? 'disabled' : ''}
                            onclick="bookingWizard.selectTime('${slot.time}', '${slot.datetime}')"
                        >
                            ${slot.time}
                        </button>
                    `;
                });

                html += `
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        html += '</div>';
        this.elements.timeslotsContainer.innerHTML = html;
    }

    /**
     * Selecciona un horario específico
     */
    selectTime(time, datetime) {
        // Remover selección anterior
        document.querySelectorAll('.timeslot-btn.selected').forEach(btn => {
            btn.classList.remove('selected');
        });

        // Seleccionar nuevo horario
        const selectedBtn = document.querySelector(`[data-time="${time}"]`);
        if (selectedBtn) {
            selectedBtn.classList.add('selected');
        }

        this.state.selectedTime = time;
        this.state.selectedDateTime = datetime;

        // Actualizar display con rango de tiempo usando la nueva función
        this.updateTimeDisplay(time);

        this.updateNavigationButtons();
    }

    /**
     * Valida el formulario de contacto
     */
    validateContactForm() {
        if (!this.elements.contactForm) return false;

        const firstname = this.elements.contactForm.querySelector('#patient-firstname');
        const lastname = this.elements.contactForm.querySelector('#patient-lastname');
        const phone = this.elements.contactForm.querySelector('#patient-phone');

        return firstname && firstname.value.trim() !== '' && 
               lastname && lastname.value.trim() !== '' &&
               phone && phone.value.trim() !== '';
    }


    /**
     * Selecciona un profesional y carga las fechas disponibles
     * @param {string} professionalId - ID del profesional
     * @param {string} professionalName - Nombre del profesional
     * @param {boolean} resetDate - Si debe resetear la fecha seleccionada
     */
    selectProfessional(professionalId, professionalName, resetDate = true) {
        // Limpiar selección anterior
        document.querySelectorAll('.professional-card.selected').forEach(card => {
            card.classList.remove('selected');
        });

        // Seleccionar nuevo profesional
        const selectedCard = document.querySelector(`[data-professional-id="${professionalId}"]`);
        if (selectedCard) {
            selectedCard.classList.add('selected');
        }

        this.state.selectedProfessional = professionalId;
        
        // Actualizar display
        if (this.elements.selectedProfessionalDisplay) {
            this.elements.selectedProfessionalDisplay.textContent = professionalName;
        }

        // Limpiar fechas y horarios anteriores si es necesario
        if (resetDate) {
            this.state.availableDates = [];
            this.calendar.selectedDate = null;
            this.state.selectedTime = null;
        }
        
        // Cargar nuevas fechas disponibles
        this.loadInitialDates()
        
        this.updateNavigationButtons();
    }
    /**
     * Confirma la reserva
     */
    async confirmBooking() {
        if (!this.validateContactForm()) {
            this.showBookingError('Por favor, completa todos los campos obligatorios.');
            return;
        }

        const formData = new FormData(this.elements.contactForm);
        const bookingData = {
            service_id: this.state.selectedService,
            professional_id: this.state.selectedProfessional,
            location_id: this.state.selectedLocationId,
            date: this.state.selectedDate,
            time: this.state.selectedTime,
            name: formData.get('firstname'),
            lastname: formData.get('lastname') || '',
            phone: formData.get('phone'),
            email: formData.get('email') || '',
            notes: formData.get('notes') || ''
        };
        
        // Si es una modificación, agregar el ID del turno original
        if (this.state.isModification && this.state.preloadData) {
            bookingData.original_appointment_id = this.state.preloadData.appointmentId;
        }

        try {
            this.setLoading(true);
            await this.submitBooking(bookingData);
        } catch (error) {
            this.showBookingError(error.message);
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Envía la reserva al servidor
     */
    async submitBooking(bookingData) {
        try {
            this.setLoading(true);
            
            // Agregar datos de modificación si es una modificación
            if (this.state.isModification && this.state.preloadData) {
                bookingData.appointment_id = this.state.preloadData.appointmentId;
                bookingData.modify_token = this.state.preloadData.modifyToken;
                bookingData.is_modification = true;
            }
            
            const url = this.buildApiUrl('create');
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(bookingData)
            });

            const result = await response.json();
            
            if (response.ok && result.success) {
                this.showBookingSuccess(result);
            } else {
                this.showBookingError(result.message || 'Error al procesar la reserva');
            }
        } catch (error) {
            console.error('Error submitting booking:', error);
            this.showBookingError('Error de conexión. Por favor, inténtalo de nuevo.');
        } finally {
            this.setLoading(false);
        }
    }

    /**
     * Muestra mensaje de éxito
     */
    showBookingSuccess(result) {
        // Ocultar el formulario de reserva
        const bookingContainer = document.querySelector('.container-fluid');
        if (bookingContainer) {
            bookingContainer.style.display = 'none';
        }

        // Llenar los datos de la pantalla de éxito
        this.populateSuccessScreen(result);

        // Mostrar la pantalla de éxito
        const successScreen = document.getElementById('booking-success-screen');
        if (successScreen) {
            successScreen.classList.remove('d-none');
            
            // Actualizar título si es una modificación
            if (this.state.isModification) {
                const successTitle = successScreen.querySelector('h2');
                if (successTitle) {
                    successTitle.textContent = '¡Turno modificado con éxito!';
                }
            }
        }
    }

    /**
     * Llena los datos en la pantalla de éxito
     */
    populateSuccessScreen(result) {
        // Nombre del paciente
        const patientName = document.getElementById('success-patient-name');
        if (patientName && result.appointment) {
            patientName.textContent = result.appointment?.patientFirstName || 'Cliente';
        }

        // Servicio
        const serviceElement = document.getElementById('success-service');
        if (serviceElement && result.appointment) {
            serviceElement.textContent = result.appointment?.serviceName;
        }

        // Fecha y hora - usando los campos date y time del backend
        const datetimeElement = document.getElementById('success-datetime');
        if (datetimeElement && result.date && result.time) {
            // Crear fecha sin conversiones de zona horaria
            const [year, month, day] = result.date.split('-');
            const [hour, minute] = result.time.split(':');
            
            // Crear fecha local sin problemas de zona horaria
            const date = new Date(year, month - 1, day, hour, minute);
            
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            datetimeElement.textContent = date.toLocaleDateString('es-ES', options);
        }

        // Duración
        const durationElement = document.getElementById('success-duration');
        if (durationElement && result.appointment) {
            durationElement.textContent = `${result.appointment?.duration} Minutos`;
        }

        // Ubicación
        const locationElement = document.getElementById('success-location');
        if (locationElement && result.appointment) {
            locationElement.textContent = result.appointment?.locationName;
        }

        // Precio
        const priceElement = document.getElementById('success-price');
        if (priceElement && result.appointment) {
            priceElement.textContent = `$${result.appointment?.price?.toString() || '0'}`;
        }

        // Email
        const emailElement = document.getElementById('success-email');
        if (emailElement && result.appointment) {
            emailElement.textContent = result.appointment?.patientEmail || 'N/A';
        }
    }

    /**
     * Muestra mensaje de error
     */
    showBookingError(message) {
        // Llenar el mensaje de error
        const errorMessageElement = document.getElementById('error-message');
        if (errorMessageElement) {
            errorMessageElement.textContent = message;
        }

        // Mostrar el modal
        const errorModal = new bootstrap.Modal(document.getElementById('booking-error-modal'));
        errorModal.show();
    }

    /**
     * Maneja el envío del formulario
     */
    handleFormSubmit(event) {
        event.preventDefault();
        this.confirmBooking();
    }

    /**
     * Establece estado de carga
     */
    setLoading(isLoading, container = null) {
        this.state.isLoading = isLoading;
        
        if (container) {
            const loadingElement = container.querySelector('.spinner-border') || 
                                 container.querySelector('#timeslots-loading');
            const contentElement = container.querySelector('#timeslots-content');
            
            if (loadingElement && contentElement) {
                loadingElement.style.display = isLoading ? 'block' : 'none';
                contentElement.style.display = isLoading ? 'none' : 'block';
            }
        }
    }

    /**
     * Maneja errores
     */
    handleError(error, container = null, message = 'Error al cargar los datos') {
        console.error('Booking error:', error);
        
        if (container) {
            container.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>${message}</p>
                </div>
            `;
        }
    }

    /**
     * Realiza petición a la API
     */
    async apiRequest(url) {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Construye URL de la API
     */
    buildApiUrl(endpoint, params = {}) {
        let url = `/booking/${this.state.domain}/api/${endpoint}`;
        
        if (Object.keys(params).length > 0) {
            const searchParams = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    searchParams.append(key, value);
                }
            });
            url += '?' + searchParams.toString();
        }
        
        return url;
    }

    /**
     * Inicializa la funcionalidad de expandir/contraer descripciones
     */
    initializeServiceDescriptions() {
        const descriptions = document.querySelectorAll('.service-description');
        
        descriptions.forEach(description => {
            const container = description.closest('.service-description-container');
            const expandBtn = container.querySelector('.expand-btn');
            const collapseBtn = container.querySelector('.collapse-btn');
            
            // Aplicar truncado inicial
            description.classList.add('truncated');
            
            // Verificar si el texto está realmente truncado
            setTimeout(() => {
                if (this.isTextTruncated(description)) {
                    expandBtn.style.display = 'block';
                }
            }, 100);
            
            // Event listeners para los botones
            expandBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar que se active el click del card
                this.expandDescription(description, expandBtn, collapseBtn);
            });
            
            collapseBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar que se active el click del card
                this.collapseDescription(description, expandBtn, collapseBtn);
            });
        });
    }

    /**
     * Verifica si el texto está truncado
     */
    isTextTruncated(element) {
        return element.scrollHeight > element.clientHeight;
    }

    /**
     * Expande la descripción
     */
    expandDescription(description, expandBtn, collapseBtn) {
        description.classList.remove('truncated');
        description.classList.add('expanded');
        expandBtn.style.display = 'none';
        collapseBtn.style.display = 'block';
    }

    /**
     * Contrae la descripción
     */
    collapseDescription(description, expandBtn, collapseBtn) {
        description.classList.remove('expanded');
        description.classList.add('truncated');
        expandBtn.style.display = 'block';
        collapseBtn.style.display = 'none';
    }
    
    /**
     * Muestra u oculta los detalles del servicio según el step activo
     */
    updateServiceDetailsVisibility() {
        if (this.elements.serviceDetails) {
            // Mostrar detalles solo en step 2 (fecha/hora) y step 3 (confirmación)
            const shouldShow = this.state.currentStep >= 2;
            this.elements.serviceDetails.style.display = shouldShow ? 'block' : 'none';
        }
    }
}

// Función global para seleccionar ubicación (compatibilidad)
function selectLocation(locationId) {
    const currentUrl = new URL(window.location);
    currentUrl.searchParams.set('location', locationId);
    window.location.href = currentUrl.toString();
}

// Inicializar el wizard cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('--')
    window.bookingWizard = new BookingWizard();
});

