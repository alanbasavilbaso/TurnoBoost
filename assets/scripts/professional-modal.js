class ProfessionalModal {
    constructor(options) {
        this.modalId = options.modalId;
        this.formContainerId = options.formContainerId;
        this.baseUrl = options.baseUrl;
        this.createUrl = options.createUrl;
        this.editUrl = options.editUrl;
        
        this.modal = document.getElementById(this.modalId);
        this.formContainer = document.getElementById(this.formContainerId);
        this.modalTitle = document.getElementById('modalTitle');
        
        this.initializeEventListeners();
    }
    
    initializeEventListeners() {
        // Escuchar clicks en botones que abren el modal
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-bs-target="#' + this.modalId + '"]');
            if (target) {
                const action = target.dataset.action;
                const professionalId = target.dataset.professionalId;
                
                if (action === 'new') {
                    this.openCreateModal();
                } else if (action === 'edit' && professionalId) {
                    this.openEditModal(professionalId);
                }
            }
        });
        
        // Limpiar modal al cerrarse
        this.modal.addEventListener('hidden.bs.modal', () => {
            this.clearModal();
        });
    }
    
    openCreateModal() {
        this.modalTitle.textContent = 'Nuevo Profesional';
        this.loadForm(this.createUrl);
    }
    
    openEditModal(professionalId) {
        this.modalTitle.textContent = 'Editar Profesional';
        const editUrl = this.editUrl.replace('__ID__', professionalId);
        this.loadForm(editUrl);
    }
    
    showLoading(show) {
        const loadingEl = document.getElementById('loading-spinner');
        if (loadingEl) {
            loadingEl.style.display = show ? 'block' : 'none';
        }
    }
    
    loadForm(url) {
        this.showLoading();
        
        fetch(url)
            .then(response => response.text())
            .then(html => {
                this.formContainer.innerHTML = html;
                this.initializeFormComponents();
            })
            .catch(error => {
                console.error('Error loading form:', error);
                this.showError('Error al cargar el formulario');
            });
    }
    
    initializeFormComponents() {
        setTimeout(() => {
            // Inicializar WizardManager solo si existe el contenedor
            if (document.querySelector('.wizard-container') && typeof WizardManager !== 'undefined' && WizardManager.init) {
                WizardManager.init();
            }
            
            // Inicializar formulario de profesional una sola vez
            if (typeof initializeProfessionalForm === 'function') {
                initializeProfessionalForm();
            }
            
            // Verificar servicios disponibles (solo log, no inicializar)
            const $servicesSelect = $('#services-select');
            if ($servicesSelect.length) {
                const optionsCount = $servicesSelect.find('option').length;
                console.log(`Select de servicios encontrado con ${optionsCount} opciones`);
            }
        }, 200);
    }
    
    clearModal() {
        // this.modal.find('.modal-body').empty();
        
        // Resetear flags de inicialización cuando se cierra el modal
        if (typeof resetFormInitialization === 'function') {
            resetFormInitialization();
        }
    }
}