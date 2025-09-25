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
        // Enable/disable connect button based on input
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            
            // Format the number (area code + number)
            if (value.length >= 2) {
                if (value.length <= 4) {
                    // Area code only
                    value = value.substring(0, 4);
                } else {
                    // Area code + number
                    const areaCode = value.substring(0, value.length <= 4 ? value.length : (value.length <= 6 ? 2 : (value.length <= 10 ? 3 : 4)));
                    const number = value.substring(areaCode.length);
                    
                    if (number.length > 0) {
                        // Format number in groups
                        if (number.length <= 4) {
                            value = areaCode + ' ' + number;
                        } else {
                            value = areaCode + ' ' + number.substring(0, 4) + ' ' + number.substring(4, 8);
                        }
                    } else {
                        value = areaCode;
                    }
                }
            }
            
            e.target.value = value;
            
            // Enable/disable connect button
            const cleanValue = e.target.value.replace(/\D/g, '');
            if (validateWhatsAppBtn) {
                validateWhatsAppBtn.disabled = cleanValue.length < 10;
            }
        });

        // Validate phone format on blur
        phoneInput.addEventListener('blur', function(e) {
            const value = e.target.value.replace(/\D/g, '');
            if (value && (value.length < 10 || value.length > 12)) {
                e.target.setCustomValidity('El número debe tener entre 10 y 12 dígitos');
            } else {
                e.target.setCustomValidity('');
            }
        });

        // Check initial state
        const initialValue = phoneInput.value.replace(/\D/g, '');
        if (validateWhatsAppBtn) {
            validateWhatsAppBtn.disabled = initialValue.length < 10;
        }
        
        // Show status section if phone exists
        if (initialValue.length >= 10 && whatsappStatusSection) {
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

        const phoneValue = phoneInput.value.replace(/\D/g, '');
        if (phoneValue.length < 10) {
            showWhatsAppError('El número debe tener al menos 10 dígitos');
            return;
        }

        const fullPhone = '+54' + phoneValue;

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
            if (data.success) {
                updateWhatsAppStatus(data);
            } else {
                showWhatsAppError(data.message || data.error || 'Error al verificar estado de WhatsApp');
                // Restaurar botón a estado normal
                if (verifyButton) {
                    updateVerifyButton(verifyButton, 'verify');
                }
            }
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