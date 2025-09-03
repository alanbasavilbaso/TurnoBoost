// Gestor genérico de modales para todas las entidades
class EntityModalManager {
    constructor(config) {
        this.config = {
            entityName: config.entityName, // 'servicio', 'profesional', 'ubicacion'
            entityNamePlural: config.entityNamePlural, // 'servicios', 'profesionales', 'ubicaciones'
            baseUrl: config.baseUrl, // '/servicios', '/profesionales', '/ubicaciones'
            deleteWarning: config.deleteWarning || 'Esta acción no se puede deshacer.',
            ...config
        };
        
        this.currentEntityId = null;
        this.initEventListeners();
    }

    initEventListeners() {
        // Botones de ver detalles
        document.addEventListener('click', (e) => {
            if (e.target.closest('.view-btn')) {
                this.handleViewClick(e.target.closest('.view-btn'));
            }
        });

        // Botones de editar
        document.addEventListener('click', (e) => {
            if (e.target.closest('.edit-btn')) {
                this.handleEditClick(e.target.closest('.edit-btn'));
            }
        });

        // Botones de eliminar
        document.addEventListener('click', (e) => {
            if (e.target.closest('.delete-btn')) {
                this.handleDeleteClick(e.target.closest('.delete-btn'));
            }
        });

        // Botón de nuevo elemento
        const newEntityBtn = document.querySelector(`[data-entity="${this.config.entityName}"][data-action="new"]`);
        if (newEntityBtn) {
            newEntityBtn.addEventListener('click', () => this.loadNewForm());
        }

        // Botón de editar desde modal de vista
        const editFromViewBtn = document.getElementById('entityEditFromViewBtn');
        if (editFromViewBtn) {
            editFromViewBtn.addEventListener('click', () => {
                if (this.currentEntityId) {
                    bootstrap.Modal.getInstance(document.getElementById('entityViewModal')).hide();
                    this.loadEditForm(this.currentEntityId);
                }
            });
        }
    }

    handleViewClick(button) {
        const entityId = button.dataset.entityId;
        if (entityId) {
            this.loadEntityDetails(entityId);
        }
    }

    handleEditClick(button) {
        const entityId = button.dataset.entityId;
        if (entityId) {
            this.loadEditForm(entityId);
        }
    }

    handleDeleteClick(button) {
        const entityId = button.dataset.entityId;
        const entityName = button.dataset.entityName;
        if (entityId && entityName) {
            this.showDeleteModal(entityId, entityName);
        }
    }

    loadEntityDetails(entityId) {
        this.currentEntityId = entityId;
        
        // Configurar modal
        document.getElementById('entityViewTitle').textContent = `Detalles del ${this.config.entityName}`;
        document.getElementById('entityEditFromViewBtn').style.display = 'inline-block';
        
        const modal = new bootstrap.Modal(document.getElementById('entityViewModal'));
        modal.show();
        
        // Cargar contenido
        fetch(`${this.config.baseUrl}/${entityId}/details`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('entityViewBody').innerHTML = this.renderEntityDetails(data);
            })
            .catch(error => {
                document.getElementById('entityViewBody').innerHTML = 
                    '<div class="alert alert-danger">Error al cargar los detalles</div>';
            });
    }

    loadEditForm(entityId) {
        this.currentEntityId = entityId;
        this.showFormModal('Editar', 'edit', 'bg-success text-white');
        
        fetch(`${this.config.baseUrl}/${entityId}/form`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('entityFormBody').innerHTML = html;
                this.initializeForm();
                this.setupFormSubmission(`${this.config.baseUrl}/${entityId}/edit`);
            })
            .catch(error => {
                document.getElementById('entityFormBody').innerHTML = 
                    '<div class="alert alert-danger">Error al cargar el formulario</div>';
            });
    }

    loadNewForm() {
        this.currentEntityId = null;
        this.showFormModal('Nuevo', 'plus', 'bg-success text-white');
        
        fetch(`${this.config.baseUrl}/new/form`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('entityFormBody').innerHTML = html;
                this.initializeForm();
                this.setupFormSubmission(`${this.config.baseUrl}/new`);
            })
            .catch(error => {
                document.getElementById('entityFormBody').innerHTML = 
                    '<div class="alert alert-danger">Error al cargar el formulario</div>';
            });
    }

    showFormModal(action, icon, headerClass) {
        document.getElementById('entityFormTitle').textContent = `${action} ${this.config.entityName}`;
        document.getElementById('entityFormIcon').className = `fas fa-${icon} me-2`;
        document.getElementById('entityFormHeader').className = `modal-header ${headerClass}`;
        
        const modal = new bootstrap.Modal(document.getElementById('entityFormModal'));
        modal.show();
    }

    showDeleteModal(entityId, entityName) {
        document.getElementById('entityTypeLabel').textContent = `el ${this.config.entityName}`;
        document.getElementById('entityName').textContent = entityName;
        document.getElementById('entityDeleteWarning').textContent = this.config.deleteWarning;
        document.getElementById('entityDeleteForm').action = `${this.config.baseUrl}/${entityId}`;
        document.getElementById('entityDeleteToken').value = this.getCSRFToken(entityId);
        
        const modal = new bootstrap.Modal(document.getElementById('entityDeleteModal'));
        modal.show();
    }

    setupFormSubmission(actionUrl) {
        const form = document.getElementById('entityFormBody').querySelector('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitForm(form, actionUrl);
            });
        }
    }

    submitForm(form, actionUrl) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
        
        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.redirected) {
                window.location.reload();
            } else {
                return response.text();
            }
        })
        .then(html => {
            if (html) {
                document.getElementById('entityFormBody').innerHTML = html;
                this.initializeForm();
                this.setupFormSubmission(actionUrl);
            }
        })
        .catch(error => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Métodos que pueden ser sobrescritos por entidades específicas
    renderEntityDetails(data) {
        // Implementación por defecto - puede ser sobrescrita
        return '<div class="alert alert-info">Detalles no implementados para esta entidad</div>';
    }

    initializeForm() {
        // Implementación por defecto - puede ser sobrescrita
        // Aquí se pueden inicializar componentes específicos del formulario
    }

    getCSRFToken(entityId) {
        // Implementar según tu sistema de CSRF tokens
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
}