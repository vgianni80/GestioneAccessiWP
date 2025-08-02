/**
 * Admin JavaScript per Gestione Accessi BluTrasimeno
 * Gestisce pannello amministrativo, AJAX, validazione e UI
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

// Configurazione globale - Fix per le variabili globali
const GABT_Admin = {
    ajaxUrl: window.gabt_admin_vars?.ajax_url || '/wp-admin/admin-ajax.php',
    nonce: window.gabt_admin_vars?.nonce || '',
    strings: window.gabt_admin_vars?.strings || {}
};

// Inizializzazione quando il DOM è pronto
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Inizializzazione componenti admin
    initBookingManagement();
    initSettingsForm();
    initTestConnections();
    initBulkActions();
    initDataExport();
    initFormValidation();
    initUIComponents();
});

/**
 * Inizializza gestione prenotazioni
 */
function initBookingManagement() {
    // Form nuova prenotazione
    const newBookingForm = document.getElementById('gabt-new-booking-form');
    if (newBookingForm) {
        setupNewBookingForm(newBookingForm);
    }
    
    // Tabella prenotazioni
    const bookingsTable = document.querySelector('.gabt-bookings-table');
    if (bookingsTable) {
        setupBookingsTable(bookingsTable);
    }
    
    // Azioni rapide dashboard
    const quickActions = document.querySelectorAll('.gabt-quick-action');
    quickActions.forEach(action => {
        action.addEventListener('click', handleQuickAction);
    });
}

/**
 * Setup form nuova prenotazione
 */
function setupNewBookingForm(form) {
    // Calcolo automatico notti e ospiti totali
    const checkinField = form.querySelector('#checkin_date');
    const checkoutField = form.querySelector('#checkout_date');
    const adultsField = form.querySelector('#adults');
    const childrenField = form.querySelector('#children');
    
    function updateCalculatedFields() {
        // Calcola notti
        if (checkinField.value && checkoutField.value) {
            const checkinDate = new Date(checkinField.value);
            const checkoutDate = new Date(checkoutField.value);
            
            if (checkoutDate > checkinDate) {
                const timeDiff = checkoutDate.getTime() - checkinDate.getTime();
                const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                const nightsField = form.querySelector('#nights');
                if (nightsField) {
                    nightsField.value = nights;
                }
            }
        }
        
        // Calcola ospiti totali
        const adults = parseInt(adultsField.value) || 0;
        const children = parseInt(childrenField.value) || 0;
        const totalGuestsField = form.querySelector('#total_guests');
        if (totalGuestsField) {
            totalGuestsField.value = adults + children;
        }
    }
    
    [checkinField, checkoutField, adultsField, childrenField].forEach(field => {
        if (field) {
            field.addEventListener('change', updateCalculatedFields);
        }
    });
    
    // Validazione date
    if (checkinField) {
        checkinField.addEventListener('change', function() {
            const today = new Date().toISOString().split('T')[0];
            if (this.value < today) {
                showMessage('La data di check-in non può essere nel passato', 'error');
                this.value = '';
            }
        });
    }
    
    if (checkoutField) {
        checkoutField.addEventListener('change', function() {
            const checkin = checkinField.value;
            if (checkin && this.value <= checkin) {
                showMessage('La data di check-out deve essere successiva al check-in', 'error');
                this.value = '';
            }
        });
    }
    
    // Submit handler
    form.addEventListener('submit', handleNewBookingSubmit);
}

/**
 * Gestisce submit nuova prenotazione
 */
async function handleNewBookingSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    if (!validateForm(form)) {
        showMessage('Correggi gli errori nel form', 'error');
        return;
    }
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creando...';
    
    try {
        const formData = new FormData(form);
        formData.append('action', 'gabt_create_booking');
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Prenotazione creata con successo!', 'success');
            form.reset();
            
            // Redirect alla dashboard dopo 2 secondi
            setTimeout(() => {
                window.location.href = data.data.redirect_url || 'admin.php?page=gestione-accessi-bt';
            }, 2000);
        } else {
            showMessage(data.data.message || 'Errore durante la creazione', 'error');
        }
        
    } catch (error) {
        console.error('Errore creazione prenotazione:', error);
        showMessage('Errore di connessione', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Setup tabella prenotazioni
 */
function setupBookingsTable(table) {
    // Azioni inline
    const actionButtons = table.querySelectorAll('.gabt-action-btn');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', handleTableAction);
    });
    
    // Ordinamento colonne
    const sortableHeaders = table.querySelectorAll('.sortable');
    sortableHeaders.forEach(header => {
        header.addEventListener('click', handleColumnSort);
    });
    
    // Filtri
    const filterInputs = document.querySelectorAll('.gabt-table-filter');
    filterInputs.forEach(input => {
        input.addEventListener('input', debounce(handleTableFilter, 300));
    });
}

/**
 * Gestisce azioni tabella
 */
async function handleTableAction(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const action = btn.dataset.action;
    const bookingId = btn.dataset.bookingId;
    
    if (!action || !bookingId) return;
    
    // Conferma per azioni distruttive
    if (['delete', 'cancel'].includes(action)) {
        if (!confirm('Sei sicuro di voler procedere?')) {
            return;
        }
    }
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Elaborando...';
    
    try {
        const formData = new FormData();
        formData.append('action', `gabt_${action}_booking`);
        formData.append('booking_id', bookingId);
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.data.message || 'Operazione completata', 'success');
            
            // Aggiorna riga tabella o ricarica pagina
            if (action === 'delete') {
                btn.closest('tr').remove();
            } else {
                location.reload();
            }
        } else {
            showMessage(data.data.message || 'Errore durante l\'operazione', 'error');
        }
        
    } catch (error) {
        console.error('Errore azione tabella:', error);
        showMessage('Errore di connessione', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

/**
 * Inizializza form impostazioni
 */
function initSettingsForm() {
    const settingsForm = document.getElementById('gabt-settings-form');
    if (!settingsForm) return;
    
    settingsForm.addEventListener('submit', handleSettingsSubmit);
    
    // Test credenziali in tempo reale
    const credentialFields = settingsForm.querySelectorAll('input[name*="alloggiati"]');
    credentialFields.forEach(field => {
        field.addEventListener('blur', debounce(validateCredentials, 1000));
    });
}

/**
 * Gestisce submit impostazioni
 */
async function handleSettingsSubmit(e) {
    e.preventDefault();
    const form = e.target;
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Salvando...';
    
    try {
        const formData = new FormData(form);
        formData.append('action', 'gabt_save_settings');
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage('Impostazioni salvate con successo!', 'success');
        } else {
            showMessage(data.data.message || 'Errore durante il salvataggio', 'error');
        }
        
    } catch (error) {
        console.error('Errore salvataggio impostazioni:', error);
        showMessage('Errore di connessione', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Inizializza test connessioni
 */
function initTestConnections() {
    const testButtons = document.querySelectorAll('.gabt-test-btn');
    testButtons.forEach(btn => {
        btn.addEventListener('click', handleConnectionTest);
    });
}

/**
 * Gestisce test connessione
 */
async function handleConnectionTest(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const testType = btn.dataset.test;
    
    if (!testType) return;
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Testando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'gabt_test_connection');
        formData.append('test_type', testType);
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.data.message || 'Test completato con successo', 'success');
            
            // Mostra dettagli se presenti
            if (data.data.details) {
                showTestResults(data.data.details);
            }
        } else {
            showMessage(data.data.message || 'Test fallito', 'error');
        }
        
    } catch (error) {
        console.error('Errore test connessione:', error);
        showMessage('Errore durante il test', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
    }
}

/**
 * Inizializza azioni bulk
 */
function initBulkActions() {
    const bulkActionSelect = document.getElementById('bulk-action-selector-top');
    const bulkActionBtn = document.getElementById('doaction');
    
    if (bulkActionSelect && bulkActionBtn) {
        bulkActionBtn.addEventListener('click', handleBulkAction);
    }
}

/**
 * Gestisce azioni bulk
 */
async function handleBulkAction(e) {
    e.preventDefault();
    
    const action = document.getElementById('bulk-action-selector-top').value;
    const checkedItems = document.querySelectorAll('input[name="booking_ids[]"]:checked');
    
    if (action === '-1') {
        showMessage('Seleziona un\'azione', 'error');
        return;
    }
    
    if (checkedItems.length === 0) {
        showMessage('Seleziona almeno un elemento', 'error');
        return;
    }
    
    if (!confirm(`Applicare l'azione "${action}" a ${checkedItems.length} elementi?`)) {
        return;
    }
    
    const bookingIds = Array.from(checkedItems).map(item => item.value);
    
    try {
        const formData = new FormData();
        formData.append('action', 'gabt_bulk_action');
        formData.append('bulk_action', action);
        formData.append('booking_ids', JSON.stringify(bookingIds));
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(`Azione applicata a ${bookingIds.length} elementi`, 'success');
            location.reload();
        } else {
            showMessage(data.data.message || 'Errore durante l\'operazione bulk', 'error');
        }
        
    } catch (error) {
        console.error('Errore azione bulk:', error);
        showMessage('Errore di connessione', 'error');
    }
}

/**
 * Inizializza esportazione dati
 */
function initDataExport() {
    const exportButtons = document.querySelectorAll('.gabt-export-btn');
    exportButtons.forEach(btn => {
        btn.addEventListener('click', handleDataExport);
    });
}

/**
 * Gestisce esportazione dati
 */
async function handleDataExport(e) {
    e.preventDefault();
    const btn = e.currentTarget;
    const exportType = btn.dataset.export;
    
    if (!exportType) return;
    
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Esportando...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'gabt_export_data');
        formData.append('export_type', exportType);
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Download file
            const blob = new Blob([atob(data.data.data)], { type: data.data.mime_type });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = data.data.filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showMessage('Esportazione completata', 'success');
        } else {
            showMessage('Errore durante l\'esportazione', 'error');
        }
        
    } catch (error) {
        console.error('Errore esportazione:', error);
        showMessage('Errore di connessione', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = originalText;
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
 * Valida form completo
 */
function validateForm(form) {
    let isValid = true;
    
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
            
            // Aggiungi messaggio errore se non presente
            if (!field.parentNode.querySelector('.field-error')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.textContent = 'Questo campo è obbligatorio';
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            }
        }
    });
    
    return isValid;
}

/**
 * Inizializza componenti UI
 */
function initUIComponents() {
    // Tooltip
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
    
    // Accordion
    const accordions = document.querySelectorAll('.gabt-accordion-header');
    accordions.forEach(header => {
        header.addEventListener('click', toggleAccordion);
    });
    
    // Tab navigation
    const tabButtons = document.querySelectorAll('.gabt-tab-button');
    tabButtons.forEach(button => {
        button.addEventListener('click', switchTab);
    });
}

/**
 * Gestisce azioni rapide
 */
async function handleQuickAction(e) {
    e.preventDefault();
    const action = e.currentTarget.dataset.action;
    
    if (!action) return;
    
    try {
        const formData = new FormData();
        formData.append('action', `gabt_quick_${action}`);
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showMessage(data.data.message || 'Azione completata', 'success');
            
            // Aggiorna statistiche se necessario
            if (data.data.stats) {
                updateDashboardStats(data.data.stats);
            }
        } else {
            showMessage(data.data.message || 'Errore durante l\'azione', 'error');
        }
        
    } catch (error) {
        console.error('Errore azione rapida:', error);
        showMessage('Errore di connessione', 'error');
    }
}

/**
 * Mostra messaggio all'utente
 */
function showMessage(message, type = 'info') {
    // Rimuovi messaggi precedenti
    const existingMessages = document.querySelectorAll('.gabt-admin-message');
    existingMessages.forEach(msg => msg.remove());
    
    // Crea nuovo messaggio
    const messageDiv = document.createElement('div');
    messageDiv.className = `gabt-admin-message gabt-admin-message-${type}`;
    messageDiv.innerHTML = `
        <p>${escapeHtml(message)}</p>
        <button type="button" class="gabt-message-close">&times;</button>
    `;
    
    // Aggiungi al DOM
    const container = document.querySelector('.gabt-admin-container') || document.querySelector('.wrap') || document.body;
    container.insertBefore(messageDiv, container.firstChild);
    
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
 * Mostra risultati test
 */
function showTestResults(results) {
    const resultsContainer = document.querySelector('.gabt-test-results');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = `
        <h3>Risultati Test</h3>
        <pre>${JSON.stringify(results, null, 2)}</pre>
    `;
    resultsContainer.style.display = 'block';
}

/**
 * Aggiorna statistiche dashboard
 */
function updateDashboardStats(stats) {
    Object.keys(stats).forEach(key => {
        const statElement = document.querySelector(`[data-stat="${key}"]`);
        if (statElement) {
            statElement.textContent = stats[key];
        }
    });
}

/**
 * Gestisce ordinamento colonne
 */
function handleColumnSort(e) {
    const header = e.currentTarget;
    const column = header.dataset.column;
    const currentOrder = header.dataset.order || 'asc';
    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
    
    // Aggiorna URL con parametri di ordinamento
    const url = new URL(window.location);
    url.searchParams.set('orderby', column);
    url.searchParams.set('order', newOrder);
    window.location.href = url.toString();
}

/**
 * Gestisce filtri tabella
 */
function handleTableFilter(e) {
    const input = e.target;
    const filterType = input.dataset.filter;
    const value = input.value;
    
    // Aggiorna URL con parametri filtro
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    // Reset paginazione quando si filtra
    url.searchParams.delete('paged');
    
    window.location.href = url.toString();
}

/**
 * Valida credenziali in tempo reale
 */
async function validateCredentials() {
    const form = document.getElementById('gabt-settings-form');
    if (!form) return;
    
    const username = form.querySelector('input[name="alloggiati_username"]').value;
    const password = form.querySelector('input[name="alloggiati_password"]').value;
    const wsKey = form.querySelector('input[name="alloggiati_ws_key"]').value;
    
    if (!username || !password || !wsKey) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'gabt_test_connection');
        formData.append('test_type', 'authentication');
        formData.append('nonce', GABT_Admin.nonce);
        
        const response = await fetch(GABT_Admin.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        const statusElement = document.querySelector('.gabt-credentials-status');
        if (statusElement) {
            if (data.success) {
                statusElement.innerHTML = '<span style="color: green;">✓ Credenziali valide</span>';
            } else {
                statusElement.innerHTML = '<span style="color: red;">✗ Credenziali non valide</span>';
            }
        }
        
    } catch (error) {
        console.error('Errore validazione credenziali:', error);
    }
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

/**
 * Utility: Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Funzioni helper aggiuntive
function showTooltip(e) {
    const element = e.currentTarget;
    const text = element.dataset.tooltip;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'gabt-tooltip';
    tooltip.textContent = text;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
    tooltip.style.left = (rect.left + (rect.width - tooltip.offsetWidth) / 2) + 'px';
    
    element._tooltip = tooltip;
}

function hideTooltip(e) {
    const element = e.currentTarget;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

function toggleAccordion(e) {
    const header = e.currentTarget;
    const content = header.nextElementSibling;
    const isOpen = content.style.display === 'block';
    
    // Chiudi tutti gli accordion dello stesso gruppo
    const group = header.closest('.gabt-accordion-group');
    if (group) {
        group.querySelectorAll('.gabt-accordion-content').forEach(item => {
            item.style.display = 'none';
        });
        group.querySelectorAll('.gabt-accordion-header').forEach(item => {
            item.classList.remove('active');
        });
    }
    
    // Toggle questo accordion
    if (!isOpen) {
        content.style.display = 'block';
        header.classList.add('active');
    }
}

function switchTab(e) {
    const button = e.currentTarget;
    const tabId = button.dataset.tab;
    
    if (!tabId) return;
    
    // Rimuovi active da tutti i tab
    document.querySelectorAll('.gabt-tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.gabt-tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // Attiva tab selezionato
    button.classList.add('active');
    const targetTab = document.getElementById(tabId);
    if (targetTab) {
        targetTab.style.display = 'block';
    }
    
    // Salva tab attivo in localStorage
    localStorage.setItem('gabt_active_tab', tabId);
}

// Ripristina tab attivo al caricamento
window.addEventListener('load', () => {
    const activeTab = localStorage.getItem('gabt_active_tab');
    if (activeTab) {
        const button = document.querySelector(`[data-tab="${activeTab}"]`);
        if (button) {
            button.click();
        }
    }
});

// Aggiungi stili per i messaggi
const style = document.createElement('style');
style.textContent = `
.gabt-admin-message {
    position: fixed;
    top: 32px;
    right: 20px;
    max-width: 400px;
    padding: 15px 40px 15px 15px;
    border-radius: 4px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 9999;
    transition: opacity 0.3s ease;
}

.gabt-admin-message-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.gabt-admin-message-error {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.gabt-admin-message-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

.gabt-message-close {
    position: absolute;
    top: 5px;
    right: 10px;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
}

.gabt-message-close:hover {
    opacity: 1;
}

.gabt-tooltip {
    position: absolute;
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 10000;
    pointer-events: none;
}

.gabt-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}
`;
document.head.appendChild(style);