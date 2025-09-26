document.addEventListener('DOMContentLoaded', function() {
    // Actualizar preview del dominio en tiempo real
    const domainInput = document.getElementById('company_domain');
    const domainPreview = document.getElementById('domain-preview');
    const baseUrl = window.location.protocol + '//' + window.location.host + '/';
    
    // WhatsApp functionality - Definir todas las variables al principio
    const phoneInput = document.getElementById('company_phone');
    const validateWhatsAppBtn = document.getElementById('validate-whatsapp-btn');
    const verifyWhatsAppBtn = document.getElementById('verify-whatsapp-btn');
    const whatsappStatusSection = document.getElementById('whatsapp-status-section');
    const whatsappQrSection = document.getElementById('whatsapp-qr-section');
    const whatsappSuccessSection = document.getElementById('whatsapp-success-section');
    const whatsappErrorSection = document.getElementById('whatsapp-error-section');
    const lastCheckedElement = document.getElementById('whatsapp-last-checked');
    const qrCodeContainer = document.getElementById('qr-code-container');
    
    // Variables para el temporizador del QR
    let qrTimer = null;
    let qrTimeRemaining = 0;
    const QR_DURATION = 20; // 20 segundos
    
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
    // WhatsApp phone input formatting and validation
    if (phoneInput) {
        // Validar si tiene +54 y sacarlo del valor al cargar la página
        const initialValue = phoneInput.value;
        if (initialValue && initialValue.startsWith('+54')) {
            phoneInput.value = initialValue.substring(3); // Quitar +54 y mostrar solo dígitos
        }
        
        // Enable/disable connect button based on input
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value;
            
            // Remove all non-digits
            let digits = value.replace(/\D/g, '');
            
            // Limit to 12 digits maximum
            if (digits.length > 12) {
                digits = digits.substring(0, 12);
            }
            
            e.target.value = digits;
            
            // Enable/disable connect button
            if (validateWhatsAppBtn) {
                validateWhatsAppBtn.disabled = digits.length < 8; // Minimum 8 digits
            }
        });

        // Validate phone format on blur
        phoneInput.addEventListener('blur', function(e) {
            const value = e.target.value;
            const digitsOnly = value.replace(/\D/g, '');
            
            if (value && (digitsOnly.length < 8 || digitsOnly.length > 12)) {
                e.target.setCustomValidity('El número debe tener entre 8 y 12 dígitos');
            } else {
                e.target.setCustomValidity('');
            }
        });

        // Check initial state after processing
        const processedValue = phoneInput.value;
        const processedDigits = processedValue.replace(/\D/g, '');
        if (validateWhatsAppBtn) {
            validateWhatsAppBtn.disabled = processedDigits.length < 8;
        }
        
        // Show status section if phone exists
        if (processedDigits.length >= 8 && whatsappStatusSection) {
            whatsappStatusSection.style.display = 'block';
            if (lastCheckedElement) {
                lastCheckedElement.style.display = 'block';
            }
        }
    }

    // Connect WhatsApp button
    if (validateWhatsAppBtn) {
        validateWhatsAppBtn.addEventListener('click', function() {
            connectWhatsApp();
        });
    }

    // Verify WhatsApp button
    // Event listener para el botón verificar (CORREGIR ESTE)
    if (verifyWhatsAppBtn) {
        verifyWhatsAppBtn.addEventListener('click', function() {
            // Reutilizar el método connectWhatsApp en lugar de duplicar código
            connectWhatsApp();
        });
    }

    function connectWhatsApp() {
        if (!phoneInput || !phoneInput.value) {
            showWhatsAppError('Por favor ingresa un número de teléfono');
            return;
        }

        let phoneValue = phoneInput.value;
        phoneValue = (!phoneValue.startsWith('+54')) ? '+54' + phoneValue : phoneValue;

        const digitsOnly = phoneValue.replace(/\D/g, '');
        
        if (!phoneValue.startsWith('+54') || digitsOnly.length < 10) {
            showWhatsAppError('El número debe ser +54 seguido de al menos 8 dígitos');
            return;
        }

        const fullPhone = phoneValue; // Ya tiene el formato correcto

        // Actualizar botón a estado de carga
        const verifyButton = document.getElementById('verify-whatsapp-btn');
        if (verifyButton) {
            updateVerifyButton(verifyButton, 'loading');
        }

        // Show status section
        if (whatsappStatusSection) {
            whatsappStatusSection.style.display = 'block';
        }

        // Usar el endpoint correcto
        fetch('/configuracion/whatsapp/qr-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                phone: fullPhone
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showWhatsAppError(data.message || data.error || 'Error al verificar estado de WhatsApp');
                // Restaurar botón a estado normal
                if (verifyButton) {
                    updateVerifyButton(verifyButton, 'verify');
                }
            }
            updateWhatsAppStatus(data);
        })
        .catch(error => {
            console.error('Error:', error);
            showWhatsAppError('Error de conexión al verificar WhatsApp');
            // Restaurar botón
            if (verifyButton) {
                updateVerifyButton(verifyButton, 'verify');
            }
        });
    }

    function updateWhatsAppStatus(data) {
        const { connectionStatus, qrData, lastChecked } = data;
        const verifyButton = document.getElementById('verify-whatsapp-btn');
        const statusElement = document.getElementById('whatsapp-status');
        
        // Update last checked time
        if (lastChecked) {
            const lastCheckedElement = document.getElementById('whatsapp-last-checked');
            const lastCheckedTime = document.getElementById('last-checked-time');
            if (lastCheckedTime) {
                lastCheckedTime.textContent = lastChecked;
                lastCheckedElement.style.display = 'block';
            }
        }

        // Update status badge
        if (statusElement) {
            switch (connectionStatus) {
                case 'connected':
                    statusElement.innerHTML = '<span class="badge bg-success">Conectado</span>';
                    break;
                case 'disconnected':
                    statusElement.innerHTML = '<span class="badge bg-warning">Desconectado</span>';
                    break;
                case 'initializing':
                    statusElement.innerHTML = '<span class="badge bg-info">Inicializando...</span>';
                    break;
                case 'error':
                    statusElement.innerHTML = '<span class="badge bg-danger">Error</span>';
                    break;
                default:
                    statusElement.innerHTML = '<span class="badge bg-secondary">Sin verificar</span>';
                    break;
            }
        }

        // Hide all sections first
        hideAllWhatsAppSections();
        
        // Clear any existing timer
        if (qrTimer) {
            clearInterval(qrTimer);
            qrTimer = null;
        }

        // Show appropriate section based on status
        switch (connectionStatus) {
            case 'connected':
                if (whatsappSuccessSection) {
                    whatsappSuccessSection.style.display = 'block';
                }
                // Cambiar botón a "Verificar Estado"
                updateVerifyButton(verifyButton, 'verify');
                break;
            case 'disconnected':
            case 'qr_ready':
                if (qrData && qrData.qr && whatsappQrSection) {
                    displayQRCode(qrData.qr);
                    whatsappQrSection.style.display = 'block';
                    // Iniciar temporizador del QR
                    startQRTimer();
                    // Cambiar botón a "Actualizar QR"
                    updateVerifyButton(verifyButton, 'refresh');
                }
                break;
            default:
                // Para cualquier otro estado, restaurar botón normal
                updateVerifyButton(verifyButton, 'verify');
                break;
        }
    }

    function displayQRCode(qrCodeData) {
        if (qrCodeContainer && qrCodeData) {
            qrCodeContainer.innerHTML = `
                <img src="${qrCodeData}" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                <div class="mt-2">
                    <small class="text-info">
                        <i class="fas fa-clock me-1"></i>
                        <span id="qr-timer">El QR expira en <strong>20</strong> segundos</span>
                    </small>
                </div>
            `;
        }
    }
    
    function startQRTimer() {
        qrTimeRemaining = QR_DURATION;
        const timerElement = document.getElementById('qr-timer');
        
        qrTimer = setInterval(() => {
            qrTimeRemaining--;
            
            if (timerElement) {
                if (qrTimeRemaining > 0) {
                    timerElement.innerHTML = `El QR expira en <strong>${qrTimeRemaining}</strong> segundos`;
                    
                    // Cambiar color según el tiempo restante
                    if (qrTimeRemaining <= 5) {
                        timerElement.parentElement.className = 'text-danger';
                    } else if (qrTimeRemaining <= 10) {
                        timerElement.parentElement.className = 'text-warning';
                    } else {
                        timerElement.parentElement.className = 'text-info';
                    }
                } else {
                    timerElement.innerHTML = '<strong>QR expirado - Haz clic en "Actualizar QR"</strong>';
                    timerElement.parentElement.className = 'text-danger';
                    clearInterval(qrTimer);
                    qrTimer = null;
                }
            }
        }, 1000);
    }
    
    function updateVerifyButton(button, mode) {
        if (!button) return;
        
        switch (mode) {
            case 'verify':
                button.innerHTML = '<i class="fas fa-sync-alt me-1"></i> Verificar Estado';
                button.className = 'btn btn-outline-primary btn-sm';
                button.disabled = false;
                break;
            case 'refresh':
                button.innerHTML = '<i class="fas fa-redo me-1"></i> Actualizar QR';
                button.className = 'btn btn-outline-warning btn-sm';
                button.disabled = false;
                break;
            case 'loading':
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Verificando...';
                button.disabled = true;
                break;
        }
    }

    function showWhatsAppError(message) {
        hideAllWhatsAppSections();
        
        if (whatsappErrorSection) {
            const errorMessage = document.getElementById('whatsapp-error-message');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
            whatsappErrorSection.style.display = 'block';
        }
    }

    function hideAllWhatsAppSections() {
        [whatsappQrSection, whatsappSuccessSection, whatsappErrorSection].forEach(section => {
            if (section) {
                section.style.display = 'none';
            }
        });
    }

    // ===== FUNCIONALIDAD DE TOGGLES DE CONTACTO =====
    const requireContactData = document.getElementById('company_requireContactData');
    const contactOptionsContainer = document.getElementById('contactOptionsContainer');
    const requireEmail = document.getElementById('company_requireEmail');
    const requirePhone = document.getElementById('company_requirePhone');

    // Función para mostrar/ocultar las opciones de contacto
    function toggleContactOptions() {
        if (requireContactData && requireContactData.checked) {
            if (contactOptionsContainer) {
                contactOptionsContainer.style.display = 'block';
            }
            // Si se activa requireContactData y ninguna opción está seleccionada, activar email por defecto
            if (requireEmail && requirePhone && !requireEmail.checked && !requirePhone.checked) {
                requireEmail.checked = true;
            }
        } else {
            if (contactOptionsContainer) {
                contactOptionsContainer.style.display = 'none';
            }
            // Si se desactiva requireContactData, desactivar ambas opciones
            if (requireEmail) requireEmail.checked = false;
            if (requirePhone) requirePhone.checked = false;
        }
    }

    // Función para asegurar que al menos una opción esté seleccionada
    function ensureAtLeastOneSelected() {
        if (requireContactData && requireContactData.checked) {
            // Si ninguna está seleccionada, activar la otra
            if (requireEmail && requirePhone) {
                // Si se desactivó email, activar phone
                if (this === requireEmail) {
                    requirePhone.checked = true;
                } else {
                    // Si se desactivó phone, activar email
                    requireEmail.checked = true;
                }
            }
        }
    }

    // Inicializar el estado al cargar la página
    if (requireContactData) {
        toggleContactOptions();

        // Event listeners
        requireContactData.addEventListener('change', toggleContactOptions);
    }

    if (requireEmail) {
        requireEmail.addEventListener('change', ensureAtLeastOneSelected);
    }

    if (requirePhone) {
        requirePhone.addEventListener('change', ensureAtLeastOneSelected);
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