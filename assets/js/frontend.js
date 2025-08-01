/**
 * Frontend JavaScript per Gestione Accessi BluTrasimeno
 * Gestisce form ospiti, validazione, Fetch API e UI interattiva
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

// Configurazione globale
const GABT = {
    ajaxUrl: window.gabt_frontend_vars?.ajax_url || '/wp-admin/admin-ajax.php',
    nonce: window.gabt_frontend_vars?.nonce || '',
    strings: window.gabt_frontend_vars?.strings || {},
    bookingStatusUrl: window.gabt_frontend_vars?.booking_status_url || '/stato-prenotazione/'
};

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Inizializzazione componenti
    initGuestForms();
    initBookingSearch();
    initProgressTracking();
    initFormValidation();
});

/**
 * Inizializza form ospiti
 */
function initGuestForms() {
    const guestForms = document.querySelectorAll('.gabt-single-guest-form');
    
    guestForms.forEach((form, index) => {
        const guestNumber = form.dataset.guestNumber || (index + 1);
        
        // Setup form
        setupGuestForm(form, guestNumber);
        
        // Submit handler
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            handleGuestFormSubmit(form);
        });
    });
    
    // Completamento registrazione
    const completeBtn = document.querySelector('.gabt-complete-registration');
    if (completeBtn) {
        completeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            completeRegistration();
        });
    }
}

/**
 * Setup singolo form ospite
 */
function setupGuestForm(form, guestNumber) {
    // Auto-uppercase per alcuni campi
    const uppercaseFields = form.querySelectorAll('input[name="first_name"], input[name="last_name"], input[name="birth_place"], input[name="document_place"]');
    uppercaseFields.forEach(field => {
        field.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });
    
    // Formattazione numero documento
    const documentField = form.querySelector('input[name="document_number"]');
    if (documentField) {
        documentField.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }
    
    // Validazione real-time
    const validationFields = form.querySelectorAll('input, select');
    validationFields.forEach(field => {
        field.addEventListener('blur', () => validateField(field));
    });
    
    // Calcolo età automatico
    const birthDateField = form.querySelector('input[name="birth_date"]');
    if (birthDateField) {
        birthDateField.addEventListener('change', () => updateAgeInfo(birthDateField));
    }
}

/**
 * Gestisce submit form ospite
 */
async function handleGuestFormSubmit(form) {
    // Validazione completa
    if (!validateForm(form)) {
        showMessage('Correggi gli errori nel form prima di continuare', 'error');
        return;
    }
    
    // Mostra loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Salvando...';
    
    try {
        // Prepara dati
        const formData = new FormData(form);
        formData.append('action', 'gabt_save_guest');
        formData.append('nonce', GABT.nonce);
        
        // Invio con Fetch API
        const response = await fetch(GABT.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            handleGuestSaveSuccess(form, data.data);
        } else {
            handleGuestSaveError(form, data.data);
        }
        
    } catch (error) {
        console.error('Errore durante il salvataggio:', error);
        handleGuestSaveError(form, { message: 'Errore di connessione. Riprova.' });
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Gestisce successo salvataggio ospite
 */
function handleGuestSaveSuccess(form, data) {
    // Marca form come completato
    form.classList.add('completed');
    
    // Mostra messaggio successo
    showMessage(data.message || 'Ospite salvato con successo', 'success');
    
    // Aggiorna progresso
    updateProgress();
    
    // Trigger evento personalizzato
    document.dispatchEvent(new CustomEvent('guest-saved', { detail: data }));
    
    // Auto-scroll al prossimo form se presente
    const currentGuestForm = form.closest('.gabt-guest-form');
    const nextGuestForm = currentGuestForm?.nextElementSibling;
    
    if (nextGuestForm && nextGuestForm.classList.contains('gabt-guest-form')) {
        nextGuestForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const firstInput = nextGuestForm.querySelector('input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 500);
        }
    }
}

/**
 * Gestisce errore salvataggio ospite
 */
function handleGuestSaveError(form, data) {
    showMessage(data.message || 'Errore durante il salvataggio', 'error');
    
    // Evidenzia campi con errori se specificati
    if (data.field_errors) {
        Object.keys(data.field_errors).forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.classList.add('error');
                
                // Rimuovi errore precedente se presente
                const existingError = field.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
                
                // Aggiungi nuovo errore
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.textContent = data.field_errors[fieldName];
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            }
        });
    }
}

/**
 * Completa registrazione
 */
async function completeRegistration() {
    try {
        const formData = new FormData();
        formData.append('action', 'gabt_complete_registration');
        formData.append('nonce', GABT.nonce);
        formData.append('booking_code', getBookingCode());
        
        const response = await fetch(GABT.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Registrazione completata con successo!', 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showMessage(data.data.message || 'Errore completamento', 'error');
        }
        
    } catch (error) {
        console.error('Errore completamento registrazione:', error);
        showMessage('Errore di connessione', 'error');
    }
}

/**
 * Inizializza ricerca prenotazioni
 */
function initBookingSearch() {
    const searchForm = document.getElementById('gabt-booking-search');
    if (!searchForm) return;
    
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleBookingSearch(searchForm);
    });
}

/**
 * Gestisce ricerca prenotazione
 */
function handleBookingSearch(form) {
    const bookingCodeInput = form.querySelector('input[name="booking_code"]');
    const bookingCode = bookingCodeInput.value.trim();
    
    if (!bookingCode) {
        showMessage('Inserisci un codice prenotazione', 'error');
        bookingCodeInput.focus();
        return;
    }
    
    // Redirect alla pagina stato con codice
    const url = new URL(GABT.bookingStatusUrl, window.location.origin);
    url.searchParams.set('booking_code', bookingCode);
    window.location.href = url.toString();
}

/**
 * Inizializza tracking progresso
 */
function initProgressTracking() {
    updateProgress();
    
    // Aggiorna progresso quando un ospite viene salvato
    document.addEventListener('guest-saved', updateProgress);
}

/**
 * Aggiorna barra progresso
 */
function updateProgress() {
    const totalForms = document.querySelectorAll('.gabt-guest-form').length;
    const completedForms = document.querySelectorAll('.gabt-guest-form.completed').length;
    const percentage = totalForms > 0 ? (completedForms / totalForms) * 100 : 0;
    
    // Aggiorna barra visuale
    const progressFill = document.querySelector('.gabt-progress-fill');
    if (progressFill) {
        progressFill.style.width = percentage + '%';
    }
    
    const progressText = document.querySelector('.gabt-progress-text');
    if (progressText) {
        progressText.textContent = `Ospiti registrati: ${completedForms} di ${totalForms}`;
    }
    
    // Mostra/nascondi pulsante completamento
    const completeBtn = document.querySelector('.gabt-complete-registration');
    if (completeBtn) {
        if (completedForms === totalForms && totalForms > 0) {
            completeBtn.style.display = 'block';
            completeBtn.classList.remove('gabt-btn-secondary');
            completeBtn.classList.add('gabt-btn-success');
        } else {
            completeBtn.style.display = 'none';
        }
    }
}

/**
 * Inizializza validazione form
 */
function initFormValidation() {
    // Rimuovi errori quando l'utente inizia a digitare
    document.addEventListener('input', (e) => {
        if (e.target.classList.contains('error')) {
            e.target.classList.remove('error');
            const errorElement = e.target.parentNode.querySelector('.field-error');
            if (errorElement) {
                errorElement.remove();
            }
        }
    });
}

/**
 * Valida singolo campo
 */
function validateField(field) {
    const value = field.value.trim();
    const fieldName = field.name;
    const isRequired = field.required;
    let isValid = true;
    let errorMessage = '';
    
    // Rimuovi errori precedenti
    field.classList.remove('error');
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Validazione campo obbligatorio
    if (isRequired && !value) {
        isValid = false;
        errorMessage = 'Questo campo è obbligatorio';
    }
    
    // Validazioni specifiche
    if (value && isValid) {
        switch (fieldName) {
            case 'first_name':
            case 'last_name':
                if (value.length < 2) {
                    isValid = false;
                    errorMessage = 'Deve contenere almeno 2 caratteri';
                }
                break;
                
            case 'birth_date':
                const birthDate = new Date(value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                
                if (birthDate > today) {
                    isValid = false;
                    errorMessage = 'La data di nascita non può essere futura';
                } else if (age > 120) {
                    isValid = false;
                    errorMessage = 'Data di nascita non realistica';
                }
                break;
                
            case 'document_number':
                if (value.length < 3) {
                    isValid = false;
                    errorMessage = 'Numero documento troppo corto';
                }
                break;
                
            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Inserisci un indirizzo email valido';
                }
                break;
        }
    }
    
    if (!isValid) {
        field.classList.add('error');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = errorMessage;
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }
    
    return isValid;
}

/**
 * Valida form completo
 */
function validateForm(form) {
    let isValid = true;
    
    const requiredFields = form.querySelectorAll('input[required], select[required]');
    requiredFields.forEach(field => {
        if (!validateField(field)) {
            isValid = false;
        }
    });
    
    return isValid;
}

/**
 * Aggiorna info età
 */
function updateAgeInfo(field) {
    const birthDate = new Date(field.value);
    const today = new Date();
    
    if (birthDate && birthDate < today) {
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        let ageInfo = field.parentNode.querySelector('.age-info');
        if (ageInfo) {
            ageInfo.textContent = `Età: ${age} anni`;
        } else {
            ageInfo = document.createElement('div');
            ageInfo.className = 'age-info';
            ageInfo.textContent = `Età: ${age} anni`;
            field.parentNode.insertBefore(ageInfo, field.nextSibling);
        }
    }
}

/**
 * Mostra messaggio all'utente
 */
function showMessage(message, type = 'info') {
    // Rimuovi messaggi precedenti
    const existingMessages = document.querySelectorAll('.gabt-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Crea nuovo messaggio
    const messageDiv = document.createElement('div');
    messageDiv.className = `gabt-message gabt-message-${type}`;
    messageDiv.innerHTML = `
        <p>${message}</p>
        <button type="button" class="gabt-message-close">&times;</button>
    `;
    
    // Aggiungi al DOM
    document.body.insertBefore(messageDiv, document.body.firstChild);
    
    // Auto-rimozione dopo 5 secondi
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 300);
        }
    }, 5000);
    
    // Click per chiudere
    const closeBtn = messageDiv.querySelector('.gabt-message-close');
    closeBtn.addEventListener('click', () => {
        messageDiv.style.opacity = '0';
        setTimeout(() => messageDiv.remove(), 300);
    });
}

/**
 * Ottiene codice prenotazione dalla pagina
 */
function getBookingCode() {
    const bookingCodeElement = document.querySelector('.gabt-booking-code');
    if (bookingCodeElement) {
        return bookingCodeElement.textContent;
    }
    
    const bookingCodeInput = document.querySelector('input[name="booking_code"]');
    if (bookingCodeInput) {
        return bookingCodeInput.value;
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('booking_code') || '';
}

/**
 * Utility: Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
