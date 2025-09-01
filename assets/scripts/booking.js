// Variables globales
let selectedService = null;
let selectedProfessional = null;
let selectedDate = null;
let selectedTimeSlot = null;
let clinicDomain = null;
let isUserAuthenticated = false;

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    // Obtener el dominio de la clínica desde el elemento HTML
    const clinicElement = document.querySelector('[data-clinic-domain]');
    if (clinicElement) {
        clinicDomain = clinicElement.dataset.clinicDomain;
    }
    
    // Verificar estado de autenticación
    const authElement = document.querySelector('[data-user-authenticated]');
    if (authElement) {
        isUserAuthenticated = authElement.dataset.userAuthenticated === 'true';
    }
    
    // Establecer fecha de hoy por defecto
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('selected-date');
    if (dateInput) {
        dateInput.value = today;
        selectedDate = today;
        
        // Event listener para cambio de fecha
        dateInput.addEventListener('change', function() {
            selectedDate = this.value;
            loadTimeSlots();
        });
    }
    
    // Cargar servicios y abrir modal automáticamente
    loadServices().then(() => {
        // Abrir modal de servicios automáticamente después de cargar
        openServiceModal();
    });
});

// Cargar servicios
async function loadServices() {
    try {
        const response = await fetch(`/reservas/${clinicDomain}/api/services`);
        const services = await response.json();
        displayServices(services);
        return services;
    } catch (error) {
        console.error('Error loading services:', error);
        throw error;
    }
}

// Mostrar servicios en el modal
function displayServices(services) {
    const container = document.getElementById('services-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    services.forEach(service => {
        const serviceElement = document.createElement('div');
        serviceElement.className = 'modal-service-item';
        serviceElement.onclick = () => selectService(service);
        
        serviceElement.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${service.name}</h6>
                    <p class="text-muted mb-1">${service.description || 'Sin descripción'}</p>
                    <small class="text-info">
                        <i class="fas fa-clock me-1"></i>${service.durationFormatted}
                    </small>
                </div>
                <div class="text-end">
                    <span class="price-badge">$${service.price || '0'}</span>
                </div>
            </div>
        `;
        
        container.appendChild(serviceElement);
    });
}

// Seleccionar servicio
function selectService(service) {
    selectedService = service;
    
    // Actualizar UI
    const serviceName = document.getElementById('selected-service-name');
    const serviceDuration = document.getElementById('selected-service-duration');
    const serviceCard = document.getElementById('selected-service-card');
    
    if (serviceName) serviceName.textContent = service.name;
    if (serviceDuration) serviceDuration.textContent = service.durationFormatted;
    if (serviceCard) serviceCard.classList.add('selected');
    
    // Cerrar modal actual
    const serviceModal = bootstrap.Modal.getInstance(document.getElementById('serviceModal'));
    if (serviceModal) {
        serviceModal.hide();
        
        // Escuchar el evento de cierre del modal para abrir el siguiente
        document.getElementById('serviceModal').addEventListener('hidden.bs.modal', function onServiceModalHidden() {
            // Remover el listener para evitar múltiples llamadas
            this.removeEventListener('hidden.bs.modal', onServiceModalHidden);
            
            // Cargar profesionales y abrir modal
            loadProfessionals(service.id).then(() => {
                openProfessionalModal();
            });
        });
    }
    
    updateFloatingButton();
}

// Cargar profesionales
async function loadProfessionals(serviceId) {
    try {
        const response = await fetch(`/reservas/${clinicDomain}/api/professionals/${serviceId}`);
        const professionals = await response.json();
        displayProfessionals(professionals);
        return professionals;
    } catch (error) {
        console.error('Error loading professionals:', error);
        throw error;
    }
}

// Mostrar profesionales en el modal
function displayProfessionals(professionals) {
    const container = document.getElementById('professionals-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    professionals.forEach(professional => {
        const professionalElement = document.createElement('div');
        professionalElement.className = 'modal-professional-item';
        professionalElement.onclick = () => selectProfessional(professional);
        
        professionalElement.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">${professional.name}</h6>
                    <p class="text-muted mb-1">${professional.specialization}</p>
                    <small class="text-info">
                        <i class="fas fa-phone me-1"></i>${professional.phone}
                    </small>
                </div>
                <div class="text-end">
                    <span class="price-badge">$${professional.priceFormatted}</span>
                </div>
            </div>
        `;
        
        container.appendChild(professionalElement);
    });
}

// Seleccionar profesional
function selectProfessional(professional) {
    selectedProfessional = professional;
    
    // Actualizar UI
    const professionalName = document.getElementById('selected-professional-name');
    const professionalPrice = document.getElementById('selected-professional-price');
    const professionalCard = document.getElementById('selected-professional-card');
    
    if (professionalName) professionalName.textContent = professional.name;
    if (professionalPrice) professionalPrice.textContent = `$${professional.priceFormatted}`;
    if (professionalCard) professionalCard.classList.add('selected');
    
    // Cerrar modal
    const professionalModal = bootstrap.Modal.getInstance(document.getElementById('professionalModal'));
    if (professionalModal) {
        professionalModal.hide();
    }
    
    // Cargar horarios disponibles
    loadTimeSlots();
    updateFloatingButton();
}

// Cargar horarios disponibles
async function loadTimeSlots() {
    if (!selectedService || !selectedProfessional || !selectedDate) {
        return;
    }
    
    try {
        const response = await fetch(`/reservas/${clinicDomain}/api/timeslots?service=${selectedService.id}&professional=${selectedProfessional.id}&date=${selectedDate}`);
        const jsonResponse = await response.json();
        displayTimeSlots(jsonResponse);
    } catch (error) {
        console.error('Error loading time slots:', error);
    }
}

// Mostrar horarios disponibles
function displayTimeSlots(jsonResponse) {
    const container = document.getElementById('timeslots-container');
    if (!container) return;
    
    container.innerHTML = '';
    const timeSlots = jsonResponse.timeSlots;
    
    // Verificar si hay horarios disponibles
    if (timeSlots.afternoon.length === 0 && timeSlots.morning.length === 0) {
        container.innerHTML = '<p class="text-muted text-center py-4"><i class="fas fa-exclamation-circle me-2"></i>No hay horarios disponibles para esta fecha.</p>';
        return;
    }
    
    const slotsGrid = document.createElement('div');
    slotsGrid.className = 'row';
    
    // Mostrar horarios de la mañana
    if (timeSlots.morning.length > 0) {
        const morningTitle = document.createElement('div');
        morningTitle.className = 'col-12 mb-2';
        morningTitle.innerHTML = '<h6 class="text-muted"><i class="fas fa-sun me-2"></i>Mañana</h6>';
        slotsGrid.appendChild(morningTitle);
        
        timeSlots.morning.forEach(slot => {
            const slotElement = document.createElement('div');
            slotElement.className = 'col-md-3 col-sm-4 col-6';
            
            const slotButton = document.createElement('div');
            slotButton.className = 'time-slot';
            slotButton.textContent = slot.time;
            slotButton.onclick = () => selectTimeSlot(slot, slotButton);
            
            slotElement.appendChild(slotButton);
            slotsGrid.appendChild(slotElement);
        });
    }
    
    // Mostrar horarios de la tarde
    if (timeSlots.afternoon.length > 0) {
        const afternoonTitle = document.createElement('div');
        afternoonTitle.className = 'col-12 mb-2 mt-3';
        afternoonTitle.innerHTML = '<h6 class="text-muted"><i class="fas fa-moon me-2"></i>Tarde</h6>';
        slotsGrid.appendChild(afternoonTitle);
        
        timeSlots.afternoon.forEach(slot => {
            const slotElement = document.createElement('div');
            slotElement.className = 'col-md-3 col-sm-4 col-6';
            
            const slotButton = document.createElement('div');
            slotButton.className = 'time-slot';
            slotButton.textContent = slot.time;
            slotButton.onclick = () => selectTimeSlot(slot, slotButton);
            
            slotElement.appendChild(slotButton);
            slotsGrid.appendChild(slotElement);
        });
    }
    
    container.appendChild(slotsGrid);
}

// Seleccionar horario
function selectTimeSlot(slot, element) {
    // Remover selección anterior
    document.querySelectorAll('.time-slot.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    // Seleccionar nuevo horario
    element.classList.add('selected');
    selectedTimeSlot = slot;
    
    updateConfirmButton();
    updateFloatingButton();
}

// Actualizar estado del botón de confirmación
function updateConfirmButton() {
    const button = document.getElementById('confirm-booking-btn');
    if (!button) return;
    
    const isComplete = selectedService && selectedProfessional && selectedDate && selectedTimeSlot;
    
    button.disabled = !isComplete;
    
    if (isComplete) {
        button.innerHTML = '<i class="fas fa-check me-2"></i>Confirmar Reserva';
    } else {
        button.innerHTML = '<i class="fas fa-check me-2"></i>Completa tu selección';
    }
}

// Actualizar botón flotante
function updateFloatingButton() {
    const floatingBtn = document.getElementById('floating-reserve-btn');
    if (!floatingBtn) return;
    
    const isComplete = selectedService && selectedProfessional && selectedDate && selectedTimeSlot;
    
    if (isComplete) {
        floatingBtn.style.display = 'block';
    } else {
        floatingBtn.style.display = 'none';
    }
}

// Abrir modal de reserva
function openBookingModal() {
    if (!selectedService || !selectedProfessional || !selectedDate || !selectedTimeSlot) {
        alert('Por favor completa toda la selección');
        return;
    }
    
    // Llenar datos del modal
    document.getElementById('booking-service').textContent = selectedService.name;
    document.getElementById('booking-professional').textContent = selectedProfessional.name;
    document.getElementById('booking-date').textContent = formatDate(selectedDate);
    document.getElementById('booking-time').textContent = selectedTimeSlot.time;
    document.getElementById('booking-duration').textContent = selectedService.durationFormatted;
    document.getElementById('booking-price').textContent = `$${selectedProfessional.priceFormatted}`;
    
    // Actualizar botón según estado de autenticación
    const authBtn = document.getElementById('auth-and-book-btn');
    if (isUserAuthenticated) {
        authBtn.innerHTML = '<i class="fas fa-calendar-check me-2"></i>Reservar';
    } else {
        authBtn.innerHTML = '<i class="fas fa-user-check me-2"></i>Autenticarme y Reservar';
    }
    
    // Mostrar modal
    const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
    bookingModal.show();
}

// Manejar proceso de reserva
function handleBooking() {
    if (isUserAuthenticated) {
        // Usuario ya autenticado, proceder con la reserva
        processBooking();
    } else {
        // Usuario no autenticado, abrir modal de autenticación
        const bookingModal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
        bookingModal.hide();
        
        setTimeout(() => {
            const authModal = new bootstrap.Modal(document.getElementById('authModal'));
            authModal.show();
        }, 300);
    }
}

// Enviar código de autenticación
function sendAuthCode() {
    const email = document.getElementById('auth-email').value;
    
    if (!email || !email.includes('@')) {
        alert('Por favor ingresa un email válido');
        return;
    }
    
    // Simular envío de código (aquí harías la llamada al servidor)
    console.log('Enviando código a:', email);
    
    // Mostrar paso 2
    document.getElementById('auth-step-1').style.display = 'none';
    document.getElementById('auth-step-2').style.display = 'block';
    
    // En un caso real, aquí harías:
    // fetch('/api/send-auth-code', { method: 'POST', body: JSON.stringify({email}) })
}

// Verificar código y reservar
function verifyCodeAndBook() {
    const code = document.getElementById('auth-code').value;
    
    if (!code || code.length < 4) {
        alert('Por favor ingresa el código de verificación');
        return;
    }
    
    // Simular verificación (aquí harías la llamada al servidor)
    console.log('Verificando código:', code);
    
    // Simular autenticación exitosa
    isUserAuthenticated = true;
    
    // Cerrar modal de auth
    const authModal = bootstrap.Modal.getInstance(document.getElementById('authModal'));
    authModal.hide();
    
    // Procesar reserva
    setTimeout(() => {
        processBooking();
    }, 300);
}

// Procesar reserva
async function processBooking() {
    try {
        // Aquí harías la llamada al servidor para crear la reserva
        const bookingData = {
            service: selectedService.id,
            professional: selectedProfessional.id,
            date: selectedDate,
            time: selectedTimeSlot.time,
            // otros datos necesarios
        };
        
        console.log('Procesando reserva:', bookingData);
        
        // Simular llamada al servidor
        // const response = await fetch('/api/create-booking', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(bookingData)
        // });
        
        // Simular éxito
        alert(`¡Reserva confirmada!\n\nServicio: ${selectedService.name}\nProfesional: ${selectedProfessional.name}\nFecha: ${formatDate(selectedDate)}\nHora: ${selectedTimeSlot.time}\nPrecio: $${selectedProfessional.priceFormatted}`);
        
        // Cerrar modal
        const bookingModal = bootstrap.Modal.getInstance(document.getElementById('bookingModal'));
        if (bookingModal) {
            bookingModal.hide();
        }
        
        // Resetear selecciones
        resetSelections();
        
    } catch (error) {
        console.error('Error al procesar reserva:', error);
        alert('Error al procesar la reserva. Por favor intenta nuevamente.');
    }
}

// Volver al paso de email
function backToEmailStep() {
    document.getElementById('auth-step-2').style.display = 'none';
    document.getElementById('auth-step-1').style.display = 'block';
    document.getElementById('auth-code').value = '';
}

// Resetear selecciones
function resetSelections() {
    selectedService = null;
    selectedProfessional = null;
    selectedTimeSlot = null;
    
    // Resetear UI
    document.getElementById('selected-service-name').textContent = 'Seleccionar servicio';
    document.getElementById('selected-service-duration').textContent = '';
    document.getElementById('selected-professional-name').textContent = 'Seleccionar profesional';
    document.getElementById('selected-professional-price').textContent = '';
    
    document.getElementById('selected-service-card').classList.remove('selected');
    document.getElementById('selected-professional-card').classList.remove('selected');
    
    document.querySelectorAll('.time-slot.selected').forEach(el => {
        el.classList.remove('selected');
    });
    
    updateFloatingButton();
    updateConfirmButton();
}

// Formatear fecha
function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('es-ES', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Abrir modales
function openServiceModal() {
    const serviceModal = document.getElementById('serviceModal');
    if (serviceModal) {
        new bootstrap.Modal(serviceModal).show();
    }
}

function openProfessionalModal() {
    if (!selectedService) {
        alert('Primero selecciona un servicio');
        return;
    }
    const professionalModal = document.getElementById('professionalModal');
    if (professionalModal) {
        new bootstrap.Modal(professionalModal).show();
    }
}

// Confirmar reserva (botón del sidebar)
document.addEventListener('DOMContentLoaded', function() {
    const confirmButton = document.getElementById('confirm-booking-btn');
    if (confirmButton) {
        confirmButton.addEventListener('click', function() {
            openBookingModal();
        });
    }
});