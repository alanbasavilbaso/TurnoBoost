// Selector de servicios personalizado sin bootstrap-select
function initCustomServicesPicker() {
    console.log('Iniciando selector personalizado de servicios...');
    
    const originalSelect = document.getElementById('services-select');
    if (!originalSelect) {
        console.log('No se encontró #services-select');
        return;
    }
    
    // Remover picker previo si existe
    const existingPicker = document.querySelector('.custom-services-picker');
    if (existingPicker) {
        existingPicker.remove();
    }
    
    // Ocultar el select original
    originalSelect.style.display = 'none';
    
    // Crear el contenedor personalizado
    const customPicker = createCustomPicker(originalSelect);
    originalSelect.parentNode.insertBefore(customPicker, originalSelect.nextSibling);
    
    // Inicializar Bootstrap dropdown manualmente
    initializeBootstrapDropdown(customPicker);
    
    // Cargar servicios preseleccionados
    setTimeout(() => loadPreselectedServices(), 100);
}

function createCustomPicker(originalSelect) {
    const container = document.createElement('div');
    container.className = 'custom-services-picker';
    
    const dropdownId = 'services-dropdown-' + Date.now();
    
    container.innerHTML = `
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle w-100" type="button" 
                    id="${dropdownId}" data-bs-toggle="dropdown" aria-expanded="false">
                Seleccionar servicios...
            </button>
            <div class="dropdown-menu w-100" aria-labelledby="${dropdownId}" 
                 style="max-height: 300px; overflow-y: auto;">
                <div class="px-3 py-2">
                    <input type="text" class="form-control form-control-sm" 
                           placeholder="Buscar servicios..." id="service-search-${Date.now()}">
                </div>
                <div class="dropdown-divider"></div>
                <div class="services-list">
                    ${generateServiceOptions(originalSelect)}
                </div>
            </div>
        </div>
    `;
    
    // Agregar eventos
    setupCustomPickerEvents(container, originalSelect);
    
    return container;
}

function initializeBootstrapDropdown(container) {
    // Esperar a que Bootstrap esté disponible
    function waitForBootstrap() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
            console.log('Bootstrap disponible, inicializando dropdown...');
            
            const dropdownButton = container.querySelector('[data-bs-toggle="dropdown"]');
            if (dropdownButton) {
                // Crear instancia de dropdown manualmente
                const dropdown = new bootstrap.Dropdown(dropdownButton);
                console.log('Dropdown inicializado:', dropdown);
                
                // Agregar evento de click manual como fallback
                dropdownButton.addEventListener('click', function(e) {
                    console.log('Click en dropdown button');
                    e.preventDefault();
                    
                    const dropdownMenu = container.querySelector('.dropdown-menu');
                    const isOpen = dropdownMenu.classList.contains('show');
                    
                    if (isOpen) {
                        dropdown.hide();
                    } else {
                        dropdown.show();
                    }
                });
            }
        } else {
            console.log('Bootstrap no disponible, reintentando...');
            setTimeout(waitForBootstrap, 100);
        }
    }
    
    waitForBootstrap();
}

function generateServiceOptions(originalSelect) {
    let html = '';
    const options = originalSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value) {
            const serviceName = option.textContent.trim();
            const servicePrice = option.getAttribute('data-subtext') || '';
            
            html += `
                <div class="dropdown-item service-option" data-value="${option.value}">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" 
                               value="${option.value}" id="service-${option.value}">
                        <label class="form-check-label" for="service-${option.value}">
                            <div class="d-flex justify-content-between">
                                <span>${serviceName}</span>
                                <small class="text-muted">${servicePrice}</small>
                            </div>
                        </label>
                    </div>
                </div>
            `;
        }
    });
    
    return html;
}

function setupCustomPickerEvents(container, originalSelect) {
    const searchInput = container.querySelector('input[placeholder="Buscar servicios..."]');
    const servicesList = container.querySelector('.services-list');
    const checkboxes = container.querySelectorAll('.form-check-input');
    
    // Búsqueda en tiempo real
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const serviceOptions = servicesList.querySelectorAll('.service-option');
            
            serviceOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });
    }
    
    // Manejar selección de servicios
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Checkbox cambiado:', this.value, this.checked);
            updateOriginalSelect(originalSelect);
            updateSelectedDisplay();
            updateDropdownButton(container);
        });
    });
    
    // Prevenir que el dropdown se cierre al hacer clic en los checkboxes
    container.addEventListener('click', function(e) {
        if (e.target.closest('.service-option') || e.target.closest('.form-check')) {
            e.stopPropagation();
        }
    });
}

function updateOriginalSelect(originalSelect) {
    const selectedValues = [];
    const checkboxes = document.querySelectorAll('.custom-services-picker .form-check-input:checked');
    
    checkboxes.forEach(checkbox => {
        selectedValues.push(checkbox.value);
    });
    
    // Actualizar el select original
    const options = originalSelect.querySelectorAll('option');
    options.forEach(option => {
        option.selected = selectedValues.includes(option.value);
    });
    
    console.log('Servicios seleccionados:', selectedValues);
}

function updateDropdownButton(container) {
    const button = container.querySelector('.dropdown-toggle');
    const checkedCount = container.querySelectorAll('.form-check-input:checked').length;
    
    if (checkedCount === 0) {
        button.textContent = 'Seleccionar servicios...';
        button.className = 'btn btn-outline-secondary dropdown-toggle w-100';
    } else {
        button.textContent = `${checkedCount} servicio${checkedCount > 1 ? 's' : ''} seleccionado${checkedCount > 1 ? 's' : ''}`;
        button.className = 'btn btn-primary dropdown-toggle w-100';
    }
}

function updateSelectedDisplay() {
    const container = document.querySelector('.selected-services-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    const checkedBoxes = document.querySelectorAll('.custom-services-picker .form-check-input:checked');
    
    if (checkedBoxes.length === 0) {
        container.innerHTML = '<p class="text-muted">No hay servicios seleccionados</p>';
        return;
    }
    
    checkedBoxes.forEach(checkbox => {
        const serviceOption = checkbox.closest('.service-option');
        const label = serviceOption.querySelector('.form-check-label');
        const serviceName = label.querySelector('span').textContent;
        const servicePrice = label.querySelector('small').textContent;
        
        const badge = document.createElement('span');
        badge.className = 'badge bg-primary me-2 mb-2 p-2';
        badge.innerHTML = `
            ${serviceName} ${servicePrice}
            <button type="button" class="btn-close btn-close-white ms-2" 
                    onclick="removeCustomService('${checkbox.value}')"></button>
        `;
        
        container.appendChild(badge);
    });
}

function loadPreselectedServices() {
    const originalSelect = document.getElementById('services-select');
    if (!originalSelect) return;
    
    const selectedOptions = originalSelect.querySelectorAll('option:checked');
    console.log('Cargando servicios preseleccionados:', selectedOptions.length);
    
    selectedOptions.forEach(option => {
        const checkbox = document.querySelector(`#service-${option.value}`);
        if (checkbox) {
            checkbox.checked = true;
            console.log('Preseleccionado:', option.value);
        }
    });
    
    // Actualizar displays
    const container = document.querySelector('.custom-services-picker');
    if (container) {
        updateDropdownButton(container);
        updateSelectedDisplay();
    }
}

// Función global para remover servicios
window.removeCustomService = function(serviceId) {
    const checkbox = document.querySelector(`#service-${serviceId}`);
    if (checkbox) {
        checkbox.checked = false;
        
        const originalSelect = document.getElementById('services-select');
        updateOriginalSelect(originalSelect);
        updateSelectedDisplay();
        
        const container = document.querySelector('.custom-services-picker');
        updateDropdownButton(container);
    }
};

// Inicialización con múltiples intentos
let initAttempts = 0;
const maxAttempts = 5;

function attemptInit() {
    initAttempts++;
    console.log(`Intento de inicialización #${initAttempts}`);
    
    const selectElement = document.getElementById('services-select');
    if (selectElement) {
        initCustomServicesPicker();
    } else if (initAttempts < maxAttempts) {
        setTimeout(attemptInit, 200 * initAttempts);
    } else {
        console.log('No se pudo encontrar el elemento después de', maxAttempts, 'intentos');
    }
}

// Eventos de inicialización
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(attemptInit, 100);
    });
} else {
    setTimeout(attemptInit, 100);
}

// Para navegación SPA
document.addEventListener('turbo:load', function() {
    console.log('Turbo load detectado');
    initAttempts = 0;
    setTimeout(attemptInit, 200);
});

document.addEventListener('turbo:render', function() {
    console.log('Turbo render detectado');
    initAttempts = 0;
    setTimeout(attemptInit, 300);
});

console.log('Script de selector personalizado cargado');