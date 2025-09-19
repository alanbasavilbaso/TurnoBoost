/**
 * Wizard Navigation and Management
 */
class WizardManager {
    constructor() {
        this.currentStep = 1;
        this.totalSteps = 4;
        this.stepData = {};
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateStepDisplay();
        this.loadStepData();
    }

    setupEventListeners() {
        // Navigation buttons
        document.getElementById('next-step')?.addEventListener('click', () => this.nextStep());
        document.getElementById('prev-step')?.addEventListener('click', () => this.prevStep());
        
        // Submit button - CORREGIDO
        document.getElementById('submit-form')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.submitForm();
        });
        
        // Step clicks
        document.querySelectorAll('.step').forEach(step => {
            step.addEventListener('click', (e) => {
                const stepNumber = parseInt(e.currentTarget.dataset.step);
                if (this.canNavigateToStep(stepNumber)) {
                    this.goToStep(stepNumber);
                }
            });
        });
        
        // Edit buttons in review
        document.querySelectorAll('.edit-step-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const stepNumber = parseInt(e.currentTarget.dataset.step);
                this.goToStep(stepNumber);
            });
        });
        
        // Form field changes
        this.setupFieldListeners();
        
        // Listen for service changes - MEJORADO
        document.addEventListener('servicesUpdated', () => {
            if (this.currentStep === this.totalSteps) {
                this.updateServicesReview();
            }
        });
        
        // También escuchar cuando se carga la página por primera vez
        Promise.resolve().then(() => {
            if (this.currentStep === this.totalSteps) {
                this.updateServicesReview();
            }
        });
    }

    setupFieldListeners() {
        // Listen for changes in form fields to update step data
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('input', () => this.updateStepData());
            form.addEventListener('change', () => this.updateStepData());
        }
    }

    nextStep() {
        if (this.validateCurrentStep()) {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.updateStepDisplay();
                this.updateReviewIfNeeded();
            }
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    }

    goToStep(stepNumber) {
        if (stepNumber >= 1 && stepNumber <= this.totalSteps) {
            this.currentStep = stepNumber;
            this.updateStepDisplay();
            this.updateReviewIfNeeded();
        }
    }

    canNavigateToStep(stepNumber) {
        // Allow navigation to previous steps or next step if current is valid
        return stepNumber <= this.currentStep || 
               (stepNumber === this.currentStep + 1 && this.validateCurrentStep());
    }

    updateStepDisplay() {
        // Update step indicators
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNumber === this.currentStep) {
                step.classList.add('active');
            } else if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            }
        });

        // Update step content
        document.querySelectorAll('.wizard-step-content').forEach((content, index) => {
            const stepNumber = index + 1;
            content.classList.toggle('active', stepNumber === this.currentStep);
        });

        // Update navigation buttons
        this.updateNavigationButtons();
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    updateNavigationButtons() {
        const prevBtn = document.getElementById('prev-step');
        const nextBtn = document.getElementById('next-step');
        const submitBtn = document.getElementById('submit-form');

        // Previous button
        if (prevBtn) {
            prevBtn.style.display = this.currentStep > 1 ? 'block' : 'none';
        }

        // Next/Submit buttons
        if (this.currentStep === this.totalSteps) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (submitBtn) submitBtn.style.display = 'block';
        } else {
            if (nextBtn) nextBtn.style.display = 'block';
            if (submitBtn) submitBtn.style.display = 'none';
        }
    }

    validateCurrentStep() {
        const currentStepElement = document.getElementById(`step-${this.currentStep}`);
        if (!currentStepElement) return true;

        // Remove previous error states
        currentStepElement.classList.remove('has-errors');
        document.querySelector(`.step[data-step="${this.currentStep}"]`)?.classList.remove('has-errors');

        let isValid = true;
        const requiredFields = currentStepElement.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Step-specific validations
        switch (this.currentStep) {
            case 1:
                isValid = this.validateBasicInfo() && isValid;
                break;
            case 2:
                isValid = this.validateSchedule() && isValid;
                break;
            case 3:
                isValid = this.validateServices() && isValid;
                break;
        }

        if (!isValid) {
            currentStepElement.classList.add('has-errors');
            document.querySelector(`.step[data-step="${this.currentStep}"]`)?.classList.add('has-errors');
            
            // Show error message
            this.showValidationError('Por favor, complete todos los campos requeridos.');
        }

        return isValid;
    }

    validateBasicInfo() {
        const name = document.querySelector('[name*="[name]"]')?.value;
        const email = document.querySelector('[name*="[email]"]')?.value;
        
        if (!name || !email) {
            return false;
        }
        
        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showValidationError('Por favor, ingrese un email válido.');
            return false;
        }
        
        return true;
    }

    validateSchedule() {
        // Check if at least one day is enabled with valid times
        const enabledDays = document.querySelectorAll('[name*="_enabled"]:checked');
        
        if (enabledDays.length === 0) {
            this.showValidationError('Debe habilitar al menos un día de disponibilidad.');
            return false;
        }
        
        // Validate time ranges for enabled days
        for (let checkbox of enabledDays) {
            const dayMatch = checkbox.name.match(/availability_(\d+)_enabled/);
            if (dayMatch) {
                const dayNumber = dayMatch[1];
                const startTime = document.querySelector(`[name*="availability_${dayNumber}_range1_start"]`)?.value;
                const endTime = document.querySelector(`[name*="availability_${dayNumber}_range1_end"]`)?.value;
                
                if (!startTime || !endTime) {
                    this.showValidationError('Debe completar los horarios para todos los días habilitados.');
                    return false;
                }
                
                if (startTime >= endTime) {
                    this.showValidationError('La hora de inicio debe ser menor que la hora de fin.');
                    return false;
                }
            }
        }
        
        return true;
    }

    validateServices() {
        // Services are optional, but if added, they should be properly configured
        return true;
    }

    showValidationError(message) {
        // Remove existing alerts
        document.querySelectorAll('.wizard-validation-alert').forEach(alert => alert.remove());
        
        // Create new alert
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show wizard-validation-alert';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert at the top of current step
        const currentStepElement = document.getElementById(`step-${this.currentStep}`);
        if (currentStepElement) {
            currentStepElement.insertBefore(alert, currentStepElement.firstChild);
        }
    }

    updateStepData() {
        // Store current step data
        this.stepData[this.currentStep] = this.getStepFormData(this.currentStep);
    }

    getStepFormData(stepNumber) {
        const stepElement = document.getElementById(`step-${stepNumber}`);
        if (!stepElement) return {};
        
        const data = {};
        const inputs = stepElement.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            if (input.name) {
                if (input.type === 'checkbox') {
                    data[input.name] = input.checked;
                } else {
                    data[input.name] = input.value;
                }
            }
        });
        
        return data;
    }

    loadStepData() {
        // Load existing data for all steps
        for (let i = 1; i <= this.totalSteps; i++) {
            this.stepData[i] = this.getStepFormData(i);
        }
    }

    updateReviewIfNeeded() {
        if (this.currentStep === this.totalSteps) {
            this.updateReviewContent();
        }
    }

    updateReviewContent() {
        this.updateBasicInfoReview();
        this.updateScheduleReview();
        this.updateServicesReview(); // Asegurar que se llame
    }

    updateBasicInfoReview() {
        const container = document.getElementById('review-basic-info');
        if (!container) return;
        
        const name = document.querySelector('[name*="[name]"]')?.value || 'No especificado';
        const specialty = document.querySelector('[name*="[specialty]"]')?.value || 'No especificado';
        const email = document.querySelector('[name*="[email]"]')?.value || 'No especificado';
        const phone = document.querySelector('[name*="[phone]"]')?.value || 'No especificado';
        const active = document.querySelector('[name*="[active]"]')?.checked ? 'Activo' : 'Inactivo';
        
        container.innerHTML = `
            <div class="review-item">
                <span class="review-label">Nombre:</span>
                <span class="review-value">${name}</span>
            </div>
            <div class="review-item">
                <span class="review-label">Especialidad:</span>
                <span class="review-value">${specialty}</span>
            </div>
            <div class="review-item">
                <span class="review-label">Email:</span>
                <span class="review-value">${email}</span>
            </div>
            <div class="review-item">
                <span class="review-label">Teléfono:</span>
                <span class="review-value">${phone}</span>
            </div>
            <div class="review-item">
                <span class="review-label">Estado:</span>
                <span class="review-value">${active}</span>
            </div>
        `;
    }

    updateScheduleReview() {
        const container = document.getElementById('review-schedule');
        if (!container) return;
        
        const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        let scheduleHtml = '';
        
        days.forEach((dayName, index) => {
            const checkbox = document.querySelector(`[name*="availability_${index}_enabled"]`);
            if (checkbox && checkbox.checked) {
                const startTime = document.querySelector(`[name*="availability_${index}_range1_start"]`)?.value || '';
                const endTime = document.querySelector(`[name*="availability_${index}_range1_end"]`)?.value || '';
                const startTime2 = document.querySelector(`[name*="availability_${index}_range2_start"]`)?.value || '';
                const endTime2 = document.querySelector(`[name*="availability_${index}_range2_end"]`)?.value || '';
                
                let timeRange = `${startTime} - ${endTime}`;
                if (startTime2 && endTime2) {
                    timeRange += `, ${startTime2} - ${endTime2}`;
                }
                
                scheduleHtml += `
                    <div class="review-item">
                        <span class="review-label">${dayName}:</span>
                        <span class="review-value">${timeRange}</span>
                    </div>
                `;
            }
        });
        
        if (!scheduleHtml) {
            scheduleHtml = '<p class="text-muted">No hay días configurados</p>';
        }
        
        container.innerHTML = scheduleHtml;
    }

    // NUEVO MÉTODO
    submitForm() {
        if (this.validateCurrentStep()) {
            const form = document.querySelector('form');
            if (form) {
                // Trigger any final validations or data processing
                const submitEvent = new Event('beforeSubmit', { bubbles: true });
                form.dispatchEvent(submitEvent);
                
                // Submit the form
                form.submit();
            }
        }
    }

    updateServicesReview() {
        const container = document.getElementById('review-services');
        if (!container) return;
        
        // CORREGIDO: Leer servicios desde servicesConfiguration global
        if (typeof servicesConfiguration === 'undefined' || Object.keys(servicesConfiguration).length === 0) {
            container.innerHTML = '<p class="text-muted">No hay servicios configurados</p>';
            return;
        }
        
        let servicesHtml = '';
        Object.entries(servicesConfiguration).forEach(([serviceId, config]) => {
            const dayKeys = ['availableSunday', 'availableMonday', 'availableTuesday', 'availableWednesday', 'availableThursday', 'availableFriday', 'availableSaturday'];
            const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            
            const availableDays = dayKeys
                .map((key, index) => config[key] ? dayNames[index] : null)
                .filter(day => day !== null)
                .join(', ') || 'Ninguno';
            
            const duration = config.customDurationMinutes ? `${config.customDurationMinutes} min` : 'Por defecto';
            const price = config.customPrice ? `$${config.customPrice}` : 'Por defecto';
            
            servicesHtml += `
                <div class="review-item mb-3">
                    <div class="review-service-header">
                        <span class="review-label fw-bold">${config.name}</span>
                    </div>
                    <div class="review-service-details">
                        <small class="text-muted d-block">Duración: ${duration}</small>
                        <small class="text-muted d-block">Precio: ${price}</small>
                        <small class="text-muted d-block">Días disponibles: ${availableDays}</small>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = servicesHtml;
    }
}

// Crear instancia global
let wizardManagerInstance = null;

// Initialize wizard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('-------');
    if (document.querySelector('.wizard-container')) {
        wizardManagerInstance = new WizardManager();
    }
});

// Exponer globalmente para uso en modales
window.WizardManager = {
    init: function() {
        if (document.querySelector('.wizard-container')) {
            if (wizardManagerInstance) {
                // Reinicializar si ya existe
                wizardManagerInstance.init();
            } else {
                // Crear nueva instancia
                wizardManagerInstance = new WizardManager();
            }
        }
    },
    getInstance: function() {
        return wizardManagerInstance;
    }
};