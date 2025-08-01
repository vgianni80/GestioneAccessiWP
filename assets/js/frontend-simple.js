/**
 * JavaScript semplificato per frontend - Gestione Accessi BluTrasimeno
 * Versione stabile e minimalista
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('GABT Frontend JS caricato');
    
    // Form registrazione ospiti semplificato
    const guestForms = document.querySelectorAll('.gabt-guest-form');
    guestForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'gabt_save_guest');
            formData.append('nonce', gabFrontend.nonce);
            
            // Mostra loading
            const submitBtn = form.querySelector('input[type="submit"]');
            const originalText = submitBtn.value;
            submitBtn.value = 'Salvando...';
            submitBtn.disabled = true;
            
            fetch(gabFrontend.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showMessage('Ospite salvato con successo!', 'success');
                    form.reset();
                } else {
                    showMessage('Errore nel salvataggio: ' + result.data, 'error');
                }
            })
            .catch(error => {
                console.error('Errore:', error);
                showMessage('Errore di rete', 'error');
            })
            .finally(() => {
                submitBtn.value = originalText;
                submitBtn.disabled = false;
            });
        });
    });
    
    // Validazione date
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const today = new Date().toISOString().split('T')[0];
            if (this.value < today && this.name.includes('birth') === false) {
                alert('La data non può essere nel passato');
                this.value = '';
            }
        });
    });
});

// Funzione per mostrare messaggi
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'gabt-message gabt-message-' + type;
    messageDiv.innerHTML = '<p>' + message + '</p>';
    
    // Inserisci il messaggio all'inizio del body o in un container specifico
    const container = document.querySelector('.gabt-frontend-container') || document.body;
    container.insertBefore(messageDiv, container.firstChild);
    
    // Rimuovi il messaggio dopo 5 secondi
    setTimeout(function() {
        messageDiv.remove();
    }, 5000);
}

// CSS inline per i messaggi
const style = document.createElement('style');
style.textContent = `
.gabt-message {
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
    border-left: 4px solid;
}
.gabt-message-success {
    background: #d4edda;
    color: #155724;
    border-left-color: #28a745;
}
.gabt-message-error {
    background: #f8d7da;
    color: #721c24;
    border-left-color: #dc3545;
}
.gabt-message-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left-color: #17a2b8;
}
`;
document.head.appendChild(style);
