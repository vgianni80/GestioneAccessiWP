/**
 * JavaScript moderno ES6+ per Gestione Accessi BT
 * Usa Fetch API, async/await, modules pattern e reactive UI
 */

class GABTAdmin {
    constructor(config) {
        this.config = config;
        this.apiBase = `${config.rest_url}gabt/v1`;
        this.nonce = config.nonce;
        
        this.init();
    }
    
    async init() {
        console.log('🚀 GABT Admin: Inizializzazione moderna', this.config);
        
        // Binding eventi con pattern moderno
        this.bindEvents();
        
        // Inizializza componenti reattivi
        this.initReactiveComponents();
        
        // Test iniziale configurazione
        await this.testConfiguration();
    }
    
    /**
     * Binding eventi con delegation pattern moderno
     */
    bindEvents() {
        // Gestione form impostazioni
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#gabt-settings-form')) {
                e.preventDefault();
                this.handleSettingsSubmit(e.target);
            }
        });
        
        // Test connessione
        document.addEventListener('click', (e) => {
            if (e.target.matches('#gabt-test-connection')) {
                e.preventDefault();
                this.handleTestConnection(e.target);
            }
        });
        
        // Test moderno
        document.addEventListener('click', (e) => {
            if (e.target.matches('#gabt-test-modern')) {
                e.preventDefault();
                this.handleModernTest(e.target);
            }
        });
        
        // Salva prenotazione
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#gabt-booking-form')) {
                e.preventDefault();
                this.handleBookingSubmit(e.target);
            }
        });
        
        // Invia schedine
        document.addEventListener('click', (e) => {
            if (e.target.matches('.gabt-send-schedine')) {
                e.preventDefault();
                this.handleSendScheduline(e.target);
            }
        });
    }
    
    /**
     * Inizializza componenti reattivi
     */
    initReactiveComponents() {
        // Auto-calcolo notti
        this.initDateCalculator();
        
        // Auto-calcolo ospiti totali
        this.initGuestCalculator();
        
        // Stato dei form reattivo
        this.initFormState();
    }
    
    /**
     * Gestione moderna form impostazioni
     */
    async handleSettingsSubmit(form) {
        const submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
        const originalText = submitBtn.value || submitBtn.textContent;
        
        try {
            // UI feedback immediato
            this.setButtonLoading(submitBtn, 'Salvataggio...');
            
            // Raccogli dati form con FormData moderna
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Gestione checkbox (FormData non include checkbox non selezionati)
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                if (!checkbox.checked) {
                    data[checkbox.name] = '0';
                }
            });
            
            console.log('💾 Salvataggio impostazioni:', data);
            
            // Chiamata API moderna
            const response = await this.apiCall('POST', '/settings', data);
            
            // Success feedback
            this.showNotification('✅ Impostazioni salvate con successo!', 'success');
            
            // Aggiorna UI se necessario
            this.updateSettingsUI(data);
            
        } catch (error) {
            console.error('❌ Errore salvataggio impostazioni:', error);
            this.showNotification(
                `❌ Errore: ${error.message || 'Impossibile salvare le impostazioni'}`,
                'error'
            );
        } finally {
            this.setButtonLoading(submitBtn, originalText, false);
        }
    }
    
    /**
     * Test connessione moderno
     */
    async handleTestConnection(button, testType = 'connection') {
        const originalText = button.textContent;
        
        try {
            this.setButtonLoading(button, '🔄 Test in corso...');
            this.clearTestResults();
            
            console.log(`🔍 Test connessione tipo: ${testType}`);
            
            const response = await this.apiCall('POST', '/test-connection', {
                test_type: testType
            });
            
            this.showTestResult('success', `✅ ${response.message}`, response.details);
            
        } catch (error) {
            console.error('❌ Errore test connessione:', error);
            
            let errorMessage = error.message || 'Errore sconosciuto';
            
            // Messaggi di errore user-friendly
            if (error.status === 400) {
                errorMessage = '⚙️ Configurazione incompleta';
            } else if (error.status === 503) {
                errorMessage = '🌐 Servizio temporaneamente non disponibile';
            } else if (error.status === 500) {
                errorMessage = '🔧 Errore interno del server';
            }
            
            this.showTestResult('error', `❌ ${errorMessage}`, error.details);
            
        } finally {
            this.setButtonLoading(button, originalText, false);
        }
    }
    
    /**
     * Test moderno delle API
     */
    async handleModernTest(button) {
        const originalText = button.textContent;
        
        try {
            this.setButtonLoading(button, '🚀 Test moderno...');
            
            // Test multiple API endpoints
            const tests = [
                { name: 'Settings API', endpoint: '/settings', method: 'GET' },
                { name: 'Stats API', endpoint: '/stats', method: 'GET' }
            ];
            
            const results = [];
            
            for (const test of tests) {
                try {
                    const startTime = performance.now();
                    await this.apiCall(test.method, test.endpoint);
                    const endTime = performance.now();
                    
                    results.push({
                        name: test.name,
                        status: 'success',
                        time: Math.round(endTime - startTime)
                    });
                } catch (error) {
                    results.push({
                        name: test.name,
                        status: 'error',
                        error: error.message
                    });
                }
            }
            
            this.showTestResults(results);
            
        } catch (error) {
            this.showTestResult('error', `❌ Test fallito: ${error.message}`);
        } finally {
            this.setButtonLoading(button, originalText, false);
        }
    }
    
    /**
     * Gestione prenotazioni
     */
    async handleBookingSubmit(form) {
        const submitBtn = form.querySelector('input[type="submit"], button[type="submit"]');
        const originalText = submitBtn.value || submitBtn.textContent;
        
        try {
            this.setButtonLoading(submitBtn, 'Salvataggio...');
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Validazione client-side
            const validation = this.validateBookingData(data);
            if (!validation.valid) {
                throw new Error(validation.errors.join(', '));
            }
            
            const response = await this.apiCall('POST', '/bookings', data);
            
            this.showNotification('✅ Prenotazione salvata con successo!', 'success');
            
            // Redirect se presente
            if (response.redirect_url) {
                setTimeout(() => {
                    window.location.href = response.redirect_url;
                }, 1500);
            }
            
        } catch (error) {
            this.showNotification(`❌ Errore: ${error.message}`, 'error');
        } finally {
            this.setButtonLoading(submitBtn, originalText, false);
        }
    }
    
    /**
     * Invia schedine
     */
    async handleSendScheduline(button) {
        const bookingId = button.dataset.bookingId;
        const originalText = button.textContent;
        
        if (!confirm('Sei sicuro di voler inviare le schedine?')) {
            return;
        }
        
        try {
            this.setButtonLoading(button, 'Invio...');
            
            const response = await this.apiCall('POST', `/bookings/${bookingId}/send-schedine`);
            
            this.showNotification(`✅ ${response.message}`, 'success');
            
            // Aggiorna UI
            this.updateSchedulineStatus(button, 'sent');
            
        } catch (error) {
            this.showNotification(`❌ Errore invio: ${error.message}`, 'error');
        } finally {
            this.setButtonLoading(button, originalText, false);
        }
    }
    
    /**
     * Chiamata API moderna con fetch
     */
    async apiCall(method, endpoint, data = null) {
        const url = `${this.apiBase}${endpoint}`;
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            },
            credentials: 'same-origin'
        };
        
        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }
        
        console.log(`🌐 API Call: ${method} ${url}`, data);
        
        try {
            const response = await fetch(url, options);
            
            // Controlla se la risposta è JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('🚫 Risposta non JSON:', text.substring(0, 200));
                throw new Error('Risposta del server non valida (non JSON)');
            }
            
            const result = await response.json();
            
            if (!response.ok) {
                // WordPress REST API error format
                if (result.code && result.message) {
                    const error = new Error(result.message);
                    error.code = result.code;
                    error.status = response.status;
                    error.details = result.data;
                    throw error;
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            console.log(`✅ API Success: ${method} ${url}`, result);
            return result;
            
        } catch (error) {
            console.error(`❌ API Error: ${method} ${url}`, error);
            
            // Network errors
            if (error.name === 'TypeError' && error.message.includes('fetch')) {
                error.message = 'Errore di connessione di rete';
            }
            
            throw error;
        }
    }
    
    /**
     * UI Helper: Stato pulsante loading
     */
    setButtonLoading(button, text, loading = true) {
        button.disabled = loading;
        
        if (button.tagName === 'INPUT') {
            button.value = text;
        } else {
            button.textContent = text;
        }
        
        if (loading) {
            button.classList.add('gabt-loading');
        } else {
            button.classList.remove('gabt-loading');
        }
    }
    
    /**
     * UI Helper: Notificazioni moderne
     */
    showNotification(message, type = 'info', duration = 5000) {
        // Rimuovi notifiche esistenti
        document.querySelectorAll('.gabt-notification').forEach(el => el.remove());
        
        const notification = document.createElement('div');
        notification.className = `gabt-notification gabt-notification--${type}`;
        notification.innerHTML = `
            <div class="gabt-notification__content">
                ${message}
                <button class="gabt-notification__close" aria-label="Chiudi">×</button>
            </div>
        `;
        
        // Stili inline per compatibilità
        Object.assign(notification.style, {
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: '9999',
            padding: '15px 20px',
            borderRadius: '8px',
            boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
            fontSize: '14px',
            fontWeight: '500',
            maxWidth: '400px',
            backgroundColor: type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6',
            color: 'white',
            transform: 'translateX(100%)',
            transition: 'transform 0.3s ease'
        });
        
        document.body.appendChild(notification);
        
        // Animazione entrata
        requestAnimationFrame(() => {
            notification.style.transform = 'translateX(0)';
        });
        
        // Chiusura manuale
        notification.querySelector('.gabt-notification__close').addEventListener('click', () => {
            this.hideNotification(notification);
        });
        
        // Auto-chiusura
        if (duration > 0) {
            setTimeout(() => {
                this.hideNotification(notification);
            }, duration);
        }
    }
    
    /**
     * Nascondi notifica con animazione
     */
    hideNotification(notification) {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
    
    /**
     * Mostra risultato test
     */
    showTestResult(type, message, details = null) {
        this.clearTestResults();
        
        const resultContainer = document.createElement('div');
        resultContainer.className = `gabt-test-result gabt-test-result--${type}`;
        resultContainer.innerHTML = `
            <div class="gabt-test-result__content">
                <p class="gabt-test-result__message">${message}</p>
                ${details ? `
                    <button class="gabt-test-result__toggle" type="button">
                        Mostra dettagli
                    </button>
                    <div class="gabt-test-result__details" style="display: none;">
                        <pre>${JSON.stringify(details, null, 2)}</pre>
                    </div>
                ` : ''}
            </div>
        `;
        
        // Stili inline moderni
        Object.assign(resultContainer.style, {
            margin: '15px 0',
            padding: '15px',
            borderRadius: '8px',
            border: `2px solid ${type === 'success' ? '#10b981' : '#ef4444'}`,
            backgroundColor: type === 'success' ? '#f0fdf4' : '#fef2f2',
            color: type === 'success' ? '#065f46' : '#991b1b',
            animation: 'gabtFadeIn 0.3s ease'
        });
        
        // Trova dove inserire il risultato
        const testButton = document.querySelector('#gabt-test-connection, #gabt-test-modern');
        if (testButton) {
            const targetContainer = testButton.closest('.form-table') || testButton.closest('form') || testButton.parentElement;
            targetContainer.appendChild(resultContainer);
        }
        
        // Toggle dettagli
        const toggleBtn = resultContainer.querySelector('.gabt-test-result__toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const detailsEl = resultContainer.querySelector('.gabt-test-result__details');
                const isVisible = detailsEl.style.display !== 'none';
                detailsEl.style.display = isVisible ? 'none' : 'block';
                toggleBtn.textContent = isVisible ? 'Mostra dettagli' : 'Nascondi dettagli';
            });
        }
        
        // Auto-hide dopo 10 secondi
        setTimeout(() => {
            if (resultContainer.parentNode) {
                resultContainer.style.opacity = '0.5';
            }
        }, 10000);
    }
    
    /**
     * Mostra risultati multipli test
     */
    showTestResults(results) {
        this.clearTestResults();
        
        const container = document.createElement('div');
        container.className = 'gabt-test-results';
        container.innerHTML = `
            <h4>🧪 Risultati Test API Moderne</h4>
            <div class="gabt-test-results__grid">
                ${results.map(result => `
                    <div class="gabt-test-result-card gabt-test-result-card--${result.status}">
                        <div class="gabt-test-result-card__header">
                            <span class="gabt-test-result-card__icon">
                                ${result.status === 'success' ? '✅' : '❌'}
                            </span>
                            <span class="gabt-test-result-card__name">${result.name}</span>
                        </div>
                        <div class="gabt-test-result-card__content">
                            ${result.status === 'success' 
                                ? `<span class="gabt-test-result-card__time">${result.time}ms</span>`
                                : `<span class="gabt-test-result-card__error">${result.error}</span>`
                            }
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
        
        // Stili inline per il grid
        Object.assign(container.style, {
            margin: '20px 0',
            padding: '20px',
            borderRadius: '12px',
            backgroundColor: '#f8fafc',
            border: '1px solid #e2e8f0'
        });
        
        const grid = container.querySelector('.gabt-test-results__grid');
        Object.assign(grid.style, {
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
            gap: '15px',
            marginTop: '15px'
        });
        
        // Stili per le card
        container.querySelectorAll('.gabt-test-result-card').forEach(card => {
            Object.assign(card.style, {
                padding: '15px',
                borderRadius: '8px',
                border: '1px solid #e2e8f0',
                backgroundColor: 'white'
            });
        });
        
        const testButton = document.querySelector('#gabt-test-modern');
        if (testButton) {
            const targetContainer = testButton.closest('.form-table') || testButton.parentElement;
            targetContainer.appendChild(container);
        }
    }
    
    /**
     * Pulisci risultati test precedenti
     */
    clearTestResults() {
        document.querySelectorAll('.gabt-test-result, .gabt-test-results').forEach(el => el.remove());
    }
    
    /**
     * Inizializza calcolatore date
     */
    initDateCalculator() {
        const checkinInput = document.querySelector('#checkin_date');
        const checkoutInput = document.querySelector('#checkout_date');
        const nightsInput = document.querySelector('#nights');
        
        if (!checkinInput || !checkoutInput) return;
        
        const calculateNights = () => {
            const checkin = checkinInput.value;
            const checkout = checkoutInput.value;
            
            if (checkin && checkout) {
                const date1 = new Date(checkin);
                const date2 = new Date(checkout);
                const timeDiff = date2.getTime() - date1.getTime();
                const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                
                if (nights > 0 && nightsInput) {
                    nightsInput.value = nights;
                    // Trigger evento per altri listener
                    nightsInput.dispatchEvent(new Event('change'));
                }
            }
        };
        
        checkinInput.addEventListener('change', calculateNights);
        checkoutInput.addEventListener('change', calculateNights);
    }
    
    /**
     * Inizializza calcolatore ospiti
     */
    initGuestCalculator() {
        const adultsInput = document.querySelector('#adults');
        const childrenInput = document.querySelector('#children');
        const totalInput = document.querySelector('#total_guests');
        
        if (!adultsInput || !totalInput) return;
        
        const calculateTotal = () => {
            const adults = parseInt(adultsInput.value) || 0;
            const children = parseInt(childrenInput?.value || 0);
            const total = adults + children;
            
            totalInput.value = total;
            totalInput.dispatchEvent(new Event('change'));
        };
        
        adultsInput.addEventListener('change', calculateTotal);
        if (childrenInput) {
            childrenInput.addEventListener('change', calculateTotal);
        }
    }
    
    /**
     * Inizializza stato form reattivo
     */
    initFormState() {
        // Salvataggio automatico draft delle impostazioni
        const settingsForm = document.querySelector('#gabt-settings-form');
        if (settingsForm) {
            this.initFormAutosave(settingsForm, 'gabt_settings_draft');
        }
        
        // Validazione in tempo reale
        this.initRealtimeValidation();
    }
    
    /**
     * Salvataggio automatico form
     */
    initFormAutosave(form, storageKey) {
        const inputs = form.querySelectorAll('input, select, textarea');
        
        // Carica draft salvato
        const savedDraft = localStorage.getItem(storageKey);
        if (savedDraft) {
            try {
                const data = JSON.parse(savedDraft);
                inputs.forEach(input => {
                    if (data[input.name] !== undefined) {
                        if (input.type === 'checkbox') {
                            input.checked = data[input.name] === '1';
                        } else {
                            input.value = data[input.name];
                        }
                    }
                });
            } catch (e) {
                console.warn('Errore caricamento draft:', e);
            }
        }
        
        // Salva cambiamenti
        const saveDebounced = this.debounce(() => {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Gestione checkbox
            inputs.forEach(input => {
                if (input.type === 'checkbox' && !input.checked) {
                    data[input.name] = '0';
                }
            });
            
            localStorage.setItem(storageKey, JSON.stringify(data));
        }, 1000);
        
        inputs.forEach(input => {
            input.addEventListener('input', saveDebounced);
            input.addEventListener('change', saveDebounced);
        });
        
        // Pulisci draft al submit
        form.addEventListener('submit', () => {
            localStorage.removeItem(storageKey);
        });
    }
    
    /**
     * Validazione in tempo reale
     */
    initRealtimeValidation() {
        // Email validation
        document.querySelectorAll('input[type="email"]').forEach(input => {
            input.addEventListener('blur', () => {
                this.validateEmail(input);
            });
        });
        
        // Date validation
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', () => {
                this.validateDate(input);
            });
        });
    }
    
    /**
     * Validazione email
     */
    validateEmail(input) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = !input.value || emailRegex.test(input.value);
        
        this.setFieldValidation(input, isValid, 'Formato email non valido');
    }
    
    /**
     * Validazione date
     */
    validateDate(input) {
        const value = input.value;
        const isValid = !value || !isNaN(Date.parse(value));
        
        this.setFieldValidation(input, isValid, 'Data non valida');
    }
    
    /**
     * Imposta stato validazione campo
     */
    setFieldValidation(input, isValid, errorMessage) {
        const existingError = input.parentNode.querySelector('.gabt-field-error');
        
        if (existingError) {
            existingError.remove();
        }
        
        if (isValid) {
            input.classList.remove('gabt-invalid');
        } else {
            input.classList.add('gabt-invalid');
            
            const errorEl = document.createElement('div');
            errorEl.className = 'gabt-field-error';
            errorEl.textContent = errorMessage;
            errorEl.style.color = '#ef4444';
            errorEl.style.fontSize = '12px';
            errorEl.style.marginTop = '5px';
            
            input.parentNode.appendChild(errorEl);
        }
    }
    
    /**
     * Validazione dati prenotazione
     */
    validateBookingData(data) {
        const errors = [];
        
        if (!data.checkin_date) {
            errors.push('Data check-in obbligatoria');
        }
        
        if (!data.checkout_date) {
            errors.push('Data check-out obbligatoria');
        }
        
        if (data.checkin_date && data.checkout_date) {
            const checkin = new Date(data.checkin_date);
            const checkout = new Date(data.checkout_date);
            
            if (checkout <= checkin) {
                errors.push('La data di check-out deve essere successiva al check-in');
            }
        }
        
        if (!data.adults || parseInt(data.adults) < 1) {
            errors.push('Almeno un adulto è obbligatorio');
        }
        
        return {
            valid: errors.length === 0,
            errors
        };
    }
    
    /**
     * Aggiorna stato schedine nell'UI
     */
    updateSchedulineStatus(button, status) {
        const row = button.closest('tr');
        if (row) {
            const statusCell = row.querySelector('.schedine-status');
            if (statusCell) {
                statusCell.textContent = status === 'sent' ? 'Inviate' : 'Da inviare';
                statusCell.className = `schedine-status schedine-status--${status}`;
            }
            
            if (status === 'sent') {
                button.remove();
            }
        }
    }
    
    /**
     * Aggiorna UI impostazioni
     */
    updateSettingsUI(data) {
        // Aggiorna indicatori di stato
        const indicators = document.querySelectorAll('.gabt-status-indicator');
        indicators.forEach(indicator => {
            // Logic per aggiornare indicatori basati sui dati salvati
        });
    }
    
    /**
     * Test configurazione iniziale
     */
    async testConfiguration() {
        try {
            // Test connettività API di base
            const response = await fetch(`${this.apiBase}/settings`, {
                headers: {
                    'X-WP-Nonce': this.nonce
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                console.log('✅ API connectivity test passed');
            } else {
                console.warn('⚠️ API connectivity issues detected');
            }
        } catch (error) {
            console.error('❌ API connectivity test failed:', error);
        }
    }
    
    /**
     * Utility: Debounce function
     */
    debounce(func, wait) {
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
    
    /**
     * Utility: Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Inizializzazione quando il DOM è pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGABT);
} else {
    initGABT();
}

function initGABT() {
    // Verifica che le variabili siano disponibili
    if (typeof gabAdmin === 'undefined') {
        console.error('❌ GABT: Configurazione non trovata');
        return;
    }
    
    // Inizializza l'applicazione moderna
    window.gabtApp = new GABTAdmin(gabAdmin);
    
    // Aggiungi CSS dinamico per animazioni
    const style = document.createElement('style');
    style.textContent = `
        @keyframes gabtFadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .gabt-loading {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        
        .gabt-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #2271b1;
            border-radius: 50%;
            animation: gabtSpin 1s linear infinite;
        }
        
        @keyframes gabtSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .gabt-invalid {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 1px #ef4444 !important;
        }
        
        .gabt-notification {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .gabt-notification__content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .gabt-notification__close {
            background: none;
            border: none;
            color: inherit;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            margin-left: 15px;
        }
    `;
    document.head.appendChild(style);
}

// Export per possibile uso come modulo
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GABTAdmin;
}