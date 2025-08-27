// Manejo de horarios de disponibilidad
document.addEventListener('DOMContentLoaded', function() {
    // Generar opciones de tiempo cada 30 minutos desde 00:00 hasta 23:30
    function generateTimeOptions() {
        const options = [];
        for (let hour = 0; hour <= 23; hour++) {
            for (let minute = 0; minute < 60; minute += 30) {
                const timeString = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                options.push(timeString);
            }
        }
        return options;
    }
    
    // Crear dropdown de tiempo personalizado
    function createTimeDropdown(input) {
        const container = input.parentNode;
        const dropdown = document.createElement('div');
        dropdown.className = 'time-dropdown';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        `;
        
        container.appendChild(dropdown);
        return dropdown;
    }
    
    // Poblar dropdown con opciones en orden secuencial, posicionando en el valor actual
    function populateDropdown(dropdown, input) {
        dropdown.innerHTML = ''; // Limpiar opciones anteriores
        
        const timeOptions = generateTimeOptions();
        const currentValue = input.value;
        let currentOptionElement = null;
        
        // Crear todas las opciones en orden secuencial (00:00 a 23:30)
        timeOptions.forEach((time, index) => {
            const option = document.createElement('div');
            option.className = 'time-option';
            option.textContent = time;
            option.style.cssText = `
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f8f9fa;
                transition: background-color 0.2s;
                ${time === currentValue ? 'background-color: #e3f2fd; font-weight: 600;' : ''}
            `;
            
            option.addEventListener('mouseenter', function() {
                if (time !== currentValue) {
                    this.style.backgroundColor = '#f8f9fa';
                }
            });
            
            option.addEventListener('mouseleave', function() {
                if (time !== currentValue) {
                    this.style.backgroundColor = 'white';
                } else {
                    this.style.backgroundColor = '#e3f2fd';
                }
            });
            
            option.addEventListener('click', function() {
                input.value = time;
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
                dropdown.style.display = 'none';
                
                // Disparar evento change para validaciones
                const changeEvent = new Event('change', { bubbles: true });
                input.dispatchEvent(changeEvent);
            });
            
            dropdown.appendChild(option);
            
            // Guardar referencia al elemento del valor actual
            if (time === currentValue) {
                currentOptionElement = option;
            }
        });
        
        // Posicionar el scroll en el valor actual después de que se renderice
        if (currentOptionElement) {
            setTimeout(() => {
                currentOptionElement.scrollIntoView({ 
                    block: 'center',
                    behavior: 'instant'
                });
            }, 10);
        }
    }
    
    // Función para validar formato de tiempo
    function validateTimeInput(input) {
        const value = input.value;
        const isValid = /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(value);
        
        input.classList.remove('is-valid', 'is-invalid');
        
        if (value) {
            if (isValid) {
                input.classList.add('is-valid');
            } else {
                input.classList.add('is-invalid');
            }
        }
        
        return isValid;
    }
    
    // Configurar inputs de tiempo
    function setupTimeInput(input) {
        const container = input.parentNode;
        container.style.position = 'relative';
        
        const dropdown = createTimeDropdown(input);
        
        // Mostrar dropdown al hacer clic en el input
        input.addEventListener('click', function(e) {
            e.stopPropagation();
            // Ocultar otros dropdowns
            document.querySelectorAll('.time-dropdown').forEach(dd => {
                if (dd !== dropdown) dd.style.display = 'none';
            });
            
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            } else {
                populateDropdown(dropdown, input);
                dropdown.style.display = 'block';
            }
        });
        
        // Permitir escritura manual
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9:]/g, '');
            
            // Aplicar máscara HH:MM
            if (value.length === 2 && !value.includes(':')) {
                value = value + ':';
            }
            if (value.length > 5) {
                value = value.substring(0, 5);
            }
            
            e.target.value = value;
            validateTimeInput(e.target);
        });
        
        // Validar al perder el foco
        input.addEventListener('blur', function(e) {
            setTimeout(() => {
                if (!dropdown.contains(document.activeElement)) {
                    dropdown.style.display = 'none';
                }
            }, 150);
            
            const value = e.target.value;
            if (value) {
                const match = value.match(/^(\d{1,2}):?(\d{0,2})$/);
                if (match) {
                    const hours = parseInt(match[1]) || 0;
                    const minutes = parseInt(match[2]) || 0;
                    
                    if (hours <= 23 && minutes <= 59) {
                        e.target.value = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
                        validateTimeInput(e.target);
                    }
                }
            }
        });
        
        // Navegación con teclado
        input.addEventListener('keydown', function(e) {
            if (dropdown.style.display === 'block') {
                const options = dropdown.querySelectorAll('.time-option');
                let selectedIndex = -1;
                
                options.forEach((option, index) => {
                    if (option.style.backgroundColor === 'rgb(13, 110, 253)') {
                        selectedIndex = index;
                    }
                });
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, options.length - 1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0) {
                        options[selectedIndex].click();
                    }
                } else if (e.key === 'Escape') {
                    dropdown.style.display = 'none';
                }
                
                // Actualizar selección visual
                options.forEach((option, index) => {
                    if (index === selectedIndex) {
                        option.style.backgroundColor = 'rgb(13, 110, 253)';
                        option.style.color = 'white';
                    } else {
                        option.style.backgroundColor = 'white';
                        option.style.color = 'black';
                    }
                });
            }
        });
    }
    
    // Inicializar todos los inputs de tiempo
    const timeInputs = document.querySelectorAll('input[type="time"], input[id*="time"], input[id*="start"], input[id*="end"]');
    timeInputs.forEach(setupTimeInput);
    
    // Ocultar dropdowns al hacer clic fuera
    document.addEventListener('click', function() {
        document.querySelectorAll('.time-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    });
    
    // Manejo de botones de agregar/quitar rangos
    document.querySelectorAll('.add-range-btn').forEach(button => {
        button.addEventListener('click', function() {
            const day = this.dataset.day;
            const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
            if (range2) {
                range2.style.display = 'block';
                this.style.display = 'none';
                
                // Configurar inputs de tiempo del nuevo rango
                const newTimeInputs = range2.querySelectorAll('input[type="time"], input[id*="time"], input[id*="start"], input[id*="end"]');
                newTimeInputs.forEach(setupTimeInput);
            }
        });
    });
    
    document.querySelectorAll('.remove-range-btn').forEach(button => {
        button.addEventListener('click', function() {
            const day = this.dataset.day;
            const range2 = document.querySelector(`[data-day="${day}"] [data-range="2"]`);
            const addBtn = document.querySelector(`[data-day="${day}"] .add-range-btn`);
            
            if (range2) {
                range2.style.display = 'none';
                // Limpiar valores
                const inputs = range2.querySelectorAll('input');
                inputs.forEach(input => {
                    input.value = '';
                    input.classList.remove('is-valid', 'is-invalid');
                });
            }
            
            if (addBtn) {
                addBtn.style.display = 'inline-block';
            }
        });
    });
});