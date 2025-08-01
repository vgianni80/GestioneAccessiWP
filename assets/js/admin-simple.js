/**
 * JavaScript semplificato per admin - Gestione Accessi BluTrasimeno
 * Versione stabile e minimalista
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('GABT Admin JS caricato');
    
    // Form validation per nuova prenotazione
    const bookingForm = document.querySelector('form.gabt-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            const checkinDate = document.querySelector('input[name="check_in_date"]');
            const checkoutDate = document.querySelector('input[name="check_out_date"]');
            
            if (checkinDate && checkoutDate) {
                const checkin = new Date(checkinDate.value);
                const checkout = new Date(checkoutDate.value);
                
                if (checkout <= checkin) {
                    e.preventDefault();
                    alert('La data di check-out deve essere successiva al check-in');
                    return false;
                }
                
                // Calcola automaticamente le notti
                const timeDiff = checkout.getTime() - checkin.getTime();
                const nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                
                console.log('Notti calcolate:', nights);
            }
        });
    }
    
    // Conferma prima di salvare impostazioni
    const settingsForm = document.querySelector('form');
    if (settingsForm && window.location.href.includes('settings')) {
        settingsForm.addEventListener('submit', function(e) {
            if (!confirm('Sei sicuro di voler salvare le impostazioni?')) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// Funzione di utilità per chiamate AJAX semplici
function gabAjaxCall(action, data, callback) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('nonce', gabAdmin.nonce);
    
    for (const key in data) {
        formData.append(key, data[key]);
    }
    
    fetch(gabAdmin.ajaxUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (callback) callback(result);
    })
    .catch(error => {
        console.error('Errore AJAX:', error);
        if (callback) callback({success: false, data: 'Errore di rete'});
    });
}
