class SpecialSchedulesManager {
    constructor() {
        this.modal = null;
        this.formModal = null;
        this.currentProfessionalId = null;
        this.initializeEventListeners();
        this.createFormModal();
    }

    initializeEventListeners() {
        // Listener para botones de jornadas especiales (ver lista)
        document.addEventListener('click', (e) => {
            if (e.target.closest('.special-hours-btn')) {
                const btn = e.target.closest('.special-hours-btn');
                const professionalId = btn.dataset.professionalId;
                this.openSpecialSchedulesModal(professionalId);
            }
            
            // Listener para botón "Habilitar jornada especial" (agregar directamente)
            if (e.target.closest('.enable-special-hours-btn')) {
                const btn = e.target.closest('.enable-special-hours-btn');
                const professionalId = btn.dataset.professionalId;
                this.currentProfessionalId = professionalId;
                this.showSpecialScheduleForm();
            }
        });
    }

    createFormModal() {
        // Crear el modal del formulario si no existe
        if (!document.getElementById('specialScheduleFormModal')) {
            const modalHtml = `
                <div class="modal fade" id="specialScheduleFormModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-calendar-plus me-2"></i>
                                    <span id="formModalTitle">Nueva Jornada Especial</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="specialScheduleFormElement">
                                    <input type="hidden" id="professionalId" value="">
                                    <input type="hidden" id="scheduleId" value="">
                                    
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="fecha" class="form-label">Fecha</label>
                                            <input type="date" class="form-control" id="fecha" name="fecha" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="horaDesde" class="form-label">Hora Desde</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <select class="form-select" id="horaDesdeHour" name="horaDesdeHour" required>
                                                        ${this.generateHourOptions()}
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" id="horaDesdeMinute" name="horaDesdeMinute" required>
                                                        ${this.generateMinuteOptions()}
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="horaHasta" class="form-label">Hora Hasta</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <select class="form-select" id="horaHastaHour" name="horaHastaHour" required>
                                                        ${this.generateHourOptions()}
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" id="horaHastaMinute" name="horaHastaMinute" required>
                                                        ${this.generateMinuteOptions()}
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" form="specialScheduleFormElement" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Guardar
                                </button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            this.formModal = new bootstrap.Modal(document.getElementById('specialScheduleFormModal'));
            
            // Inicializar eventos del formulario
            this.initializeFormEvents();
        }
    }

    generateHourOptions() {
        let options = '';
        for (let i = 0; i <= 23; i++) {
            const hour = i.toString().padStart(2, '0');
            options += `<option value="${hour}">${hour}</option>`;
        }
        return options;
    }

    generateMinuteOptions() {
        const minutes = [0, 15, 30, 45];
        let options = '';
        minutes.forEach(minute => {
            const min = minute.toString().padStart(2, '0');
            options += `<option value="${min}">${min}</option>`;
        });
        return options;
    }

    initializeFormEvents() {
        const form = document.getElementById('specialScheduleFormElement');
        if (form) {
            form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        }

        // Evento cuando se cierra el modal del formulario
        const formModalElement = document.getElementById('specialScheduleFormModal');
        formModalElement.addEventListener('hidden.bs.modal', () => {
            // Si hay un modal de lista abierto, refrescarlo
            if (this.modal && this.currentProfessionalId) {
                this.openSpecialSchedulesModal(this.currentProfessionalId);
            }
        });
    }

    async openSpecialSchedulesModal(professionalId) {
        this.currentProfessionalId = professionalId;
        
        try {
            const response = await fetch(`/profesionales/${professionalId}/special-schedules`);
            if (!response.ok) {
                throw new Error('Error al cargar las jornadas especiales');
            }
            
            const html = await response.text();
            
            // Crear o actualizar modal
            this.createModal(html);
            
            // Mostrar modal
            this.modal.show();
            
            // Inicializar eventos del modal
            this.initializeModalEvents();
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cargar las jornadas especiales');
        }
    }

    createModal(content) {
        // Remover modal existente si existe
        const existingModal = document.getElementById('specialSchedulesModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Crear nuevo modal
        const modalHtml = `
            <div class="modal fade" id="specialSchedulesModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        ${content}
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.modal = new bootstrap.Modal(document.getElementById('specialSchedulesModal'));
    }

    initializeModalEvents() {
        const modalElement = document.getElementById('specialSchedulesModal');
        
        // Botón agregar jornada especial
        const addBtn = modalElement.querySelector('#addSpecialScheduleBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                // Cerrar modal de lista y abrir formulario
                this.modal.hide();
                this.showSpecialScheduleForm();
            });
        }

        // Botones de editar
        modalElement.querySelectorAll('.edit-special-schedule').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const scheduleId = e.target.closest('.edit-special-schedule').dataset.scheduleId;
                this.modal.hide();
                this.editSpecialSchedule(scheduleId);
            });
        });

        // Botones de eliminar
        modalElement.querySelectorAll('.delete-special-schedule').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const scheduleId = e.target.closest('.delete-special-schedule').dataset.scheduleId;
                this.deleteSpecialSchedule(scheduleId);
            });
        });
    }

    showSpecialScheduleForm(scheduleData = null) {
        // Configurar el formulario
        document.getElementById('professionalId').value = this.currentProfessionalId;
        document.getElementById('scheduleId').value = scheduleData ? scheduleData.id : '';
        
        // Configurar título
        document.getElementById('formModalTitle').textContent = 
            scheduleData ? 'Editar Jornada Especial' : 'Nueva Jornada Especial';
        
        // Llenar datos si es edición
        if (scheduleData) {
            document.getElementById('fecha').value = scheduleData.fecha;
            document.getElementById('horaDesdeHour').value = scheduleData.horaDesde.split(':')[0];
            document.getElementById('horaDesdeMinute').value = scheduleData.horaDesde.split(':')[1];
            document.getElementById('horaHastaHour').value = scheduleData.horaHasta.split(':')[0];
            document.getElementById('horaHastaMinute').value = scheduleData.horaHasta.split(':')[1];
        } else {
            // Limpiar formulario
            document.getElementById('specialScheduleFormElement').reset();
            document.getElementById('professionalId').value = this.currentProfessionalId;
        }
        
        // Mostrar modal
        this.formModal.show();
    }

    async editSpecialSchedule(scheduleId) {
        try {
            const response = await fetch(`/profesionales/special-schedules/${scheduleId}`);
            if (!response.ok) {
                throw new Error('Error al cargar la jornada especial');
            }
            
            const scheduleData = await response.json();
            this.showSpecialScheduleForm(scheduleData);
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cargar la jornada especial');
        }
    }

    async deleteSpecialSchedule(scheduleId) {
        if (!confirm('¿Está seguro de que desea eliminar esta jornada especial?')) {
            return;
        }

        try {
            const response = await fetch(`/profesionales/special-schedules/${scheduleId}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Error al eliminar la jornada especial');
            }

            // Recargar modal
            this.openSpecialSchedulesModal(this.currentProfessionalId);
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al eliminar la jornada especial');
        }
    }

    async handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const scheduleId = document.getElementById('scheduleId').value;
        
        // Construir datos
        const data = {
            professionalId: this.currentProfessionalId,
            fecha: formData.get('fecha'),
            horaDesde: `${formData.get('horaDesdeHour')}:${formData.get('horaDesdeMinute')}`,
            horaHasta: `${formData.get('horaHastaHour')}:${formData.get('horaHastaMinute')}`
        };
    
        try {
            // CORREGIR: Usar rutas correctas con ID del profesional
            const url = scheduleId ? 
                `/profesionales/special-schedules/${scheduleId}` : 
                `/profesionales/${this.currentProfessionalId}/special-schedules`;
                        
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });
    
            if (!response.ok) {
                throw new Error('Error al guardar la jornada especial');
            }
    
            // Cerrar modal del formulario
            this.formModal.hide();
            
            // El evento 'hidden.bs.modal' se encargará de refrescar la lista
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al guardar la jornada especial');
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new SpecialSchedulesManager();
});