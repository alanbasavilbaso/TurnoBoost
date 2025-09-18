document.addEventListener('DOMContentLoaded', function() {
    // Actualizar preview del dominio en tiempo real
    const domainInput = document.getElementById('company_domain');
    const domainPreview = document.getElementById('domain-preview');
    const baseUrl = window.location.protocol + '//' + window.location.host + '/';
    
    if (domainInput && domainPreview) {
        domainInput.addEventListener('input', function() {
            const domain = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
            this.value = domain;
            domainPreview.textContent = baseUrl + (domain || 'tu-dominio');
        });
    }

    // Funcionalidad del selector de color
    const colorInput = document.getElementById('primary-color-input');
    const colorHexInput = document.getElementById('color-hex-input');
    const colorSample = document.getElementById('color-sample');
    
    if (colorInput && colorHexInput && colorSample) {
        // Sincronizar el valor inicial
        colorHexInput.value = colorInput.value;
        
        // Actualizar cuando cambia el selector de color
        colorInput.addEventListener('input', function() {
            const color = this.value;
            colorHexInput.value = color;
            colorSample.style.backgroundColor = color;
        });
        
        // Actualizar cuando se escribe en el campo hexadecimal
        colorHexInput.addEventListener('input', function() {
            const color = this.value;
            if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                colorInput.value = color;
                colorSample.style.backgroundColor = color;
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });
        
        // Hacer el campo hexadecimal editable
        colorHexInput.removeAttribute('readonly');
    }
    
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Validación en tiempo real para campos de tiempo
    const minimumTimeInput = document.getElementById('company_minimumBookingTime');
    const maximumTimeInput = document.getElementById('company_maximumFutureTime');

    if (minimumTimeInput) {
        minimumTimeInput.addEventListener('input', function() {
            validateTimeInput(this, 0, 10080, 'minutos');
        });
    }

    if (maximumTimeInput) {
        maximumTimeInput.addEventListener('input', function() {
            validateTimeInput(this, 1, 365, 'días');
        });
    }

    // Función para validar inputs de tiempo
    function validateTimeInput(input, min, max, unit) {
        const value = parseInt(input.value);
        const feedback = input.parentNode.querySelector('.invalid-feedback') || createFeedbackElement(input);
        
        if (isNaN(value) || value < min || value > max) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            feedback.textContent = `Debe estar entre ${min} y ${max} ${unit}`;
            feedback.style.display = 'block';
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            feedback.style.display = 'none';
        }
    }

    // Crear elemento de feedback si no existe
    function createFeedbackElement(input) {
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        input.parentNode.appendChild(feedback);
        return feedback;
    }

    // Formatear números en inputs
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseInt(this.value);
            }
        });
    });

    // Contador de caracteres para descripción
    const descriptionField = document.getElementById('company_description');
    const charCount = document.getElementById('char-count');
    
    if (descriptionField && charCount) {
        // Función para actualizar el contador
        function updateCharCount() {
            const currentLength = descriptionField.value.length;
            charCount.textContent = currentLength;
            
            // Cambiar color según proximidad al límite
            if (currentLength > 240) {
                charCount.style.color = '#dc3545'; // Rojo
            } else if (currentLength > 200) {
                charCount.style.color = '#fd7e14'; // Naranja
            } else {
                charCount.style.color = '#6c757d'; // Gris normal
            }
        }
        
        // Actualizar contador al cargar la página
        updateCharCount();
        
        // Actualizar contador mientras se escribe
        descriptionField.addEventListener('input', updateCharCount);
        descriptionField.addEventListener('keyup', updateCharCount);
    }
});

// Función para copiar URL al portapapeles
function copyToClipboard() {
    const url = document.getElementById('booking-url').textContent;
    navigator.clipboard.writeText(url).then(function() {
        // Mostrar feedback visual
        const btn = document.querySelector('.copy-btn');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        
        setTimeout(function() {
            btn.innerHTML = originalContent;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(function() {
        // Fallback para navegadores que no soportan clipboard API
        const textArea = document.createElement('textarea');
        textArea.value = url;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Mostrar feedback
        const btn = document.querySelector('.copy-btn');
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-success');
        
        setTimeout(function() {
            btn.innerHTML = originalContent;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-primary');
        }, 2000);
    });
}

// Función para mostrar/ocultar secciones
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const icon = document.querySelector(`[data-target="${sectionId}"] i`);
    
    if (section.style.display === 'none') {
        section.style.display = 'block';
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
    } else {
        section.style.display = 'none';
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
    }
}