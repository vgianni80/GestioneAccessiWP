<?php
/**
 * Template form ospiti frontend
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Se il template viene caricato standalone, aggiungi struttura HTML completa
if (!wp_doing_ajax() && !headers_sent()) {
    get_header();
}
?>

<div class="gabt-frontend-container">
    <div class="gabt-page-header">
        <h1>Registrazione Ospiti</h1>
        <p class="subtitle">Prenotazione: <?php echo esc_html($booking->booking_code); ?></p>
    </div>

<?php
// Mostra informazioni prenotazione
echo '<div class="gabt-booking-info">';
echo '<h3>Dettagli del Soggiorno</h3>';
echo '<div class="gabt-booking-details">';
echo '<p><strong>Check-in:</strong> ' . date('d/m/Y', strtotime($booking->checkin_date)) . '</p>';
echo '<p><strong>Check-out:</strong> ' . date('d/m/Y', strtotime($booking->checkout_date)) . '</p>';
echo '<p><strong>Notti:</strong> ' . $booking->nights . '</p>';
echo '<p><strong>Ospiti totali:</strong> ' . $booking->total_guests . '</p>';

if (!empty($booking->accommodation_name)) {
    echo '<p><strong>Struttura:</strong> ' . esc_html($booking->accommodation_name) . '</p>';
}

if (!empty($booking->accommodation_address)) {
    echo '<p><strong>Indirizzo:</strong> ' . esc_html($booking->accommodation_address) . '</p>';
}
echo '</div>';
echo '</div>';

// Renderizza il form usando la classe GuestForms
$guest_forms->render_form($booking);
?>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Inizializzazione specifica per questa pagina
    $('.gabt-single-guest-form').each(function(index) {
        var $form = $(this);
        var guestNumber = index + 1;
        
        // Se è il primo form, focus automatico
        if (index === 0) {
            $form.find('input:first').focus();
        }
        
        // Validazione in tempo reale
        $form.find('input, select').on('blur', function() {
            validateField($(this));
        });
        
        // Auto-uppercase per alcuni campi
        $form.find('input[name="first_name"], input[name="last_name"], input[name="birth_place"], input[name="document_place"]').on('input', function() {
            $(this).val($(this).val().toUpperCase());
        });
        
        // Formattazione numero documento
        $form.find('input[name="document_number"]').on('input', function() {
            $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9]/g, ''));
        });
    });
    
    // Funzione di validazione campo
    function validateField($field) {
        var value = $field.val().trim();
        var fieldName = $field.attr('name');
        var isRequired = $field.prop('required');
        var isValid = true;
        var errorMessage = '';
        
        // Rimuovi errori precedenti
        $field.removeClass('error');
        $field.siblings('.field-error').remove();
        
        // Validazione campo obbligatorio
        if (isRequired && !value) {
            isValid = false;
            errorMessage = 'Questo campo è obbligatorio';
        }
        
        // Validazioni specifiche
        if (value) {
            switch (fieldName) {
                case 'first_name':
                case 'last_name':
                    if (value.length < 2) {
                        isValid = false;
                        errorMessage = 'Deve contenere almeno 2 caratteri';
                    }
                    break;
                    
                case 'birth_date':
                    var birthDate = new Date(value);
                    var today = new Date();
                    var age = today.getFullYear() - birthDate.getFullYear();
                    
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
            }
        }
        
        if (!isValid) {
            $field.addClass('error');
            $field.after('<div class="field-error">' + errorMessage + '</div>');
        }
        
        return isValid;
    }
    
    // Progress tracking
    function updateProgress() {
        var totalForms = $('.gabt-single-guest-form').length;
        var completedForms = $('.gabt-guest-form.completed').length;
        var percentage = totalForms > 0 ? (completedForms / totalForms) * 100 : 0;
        
        $('.gabt-progress-fill').css('width', percentage + '%');
        $('.gabt-progress p').text('Ospiti registrati: ' + completedForms + ' di ' + totalForms);
        
        // Mostra pulsante completamento se tutti registrati
        if (completedForms === totalForms && totalForms > 0) {
            $('.gabt-complete-registration').show().removeClass('gabt-btn-secondary').addClass('gabt-btn-success');
        }
    }
    
    // Aggiorna progresso iniziale
    updateProgress();
    
    // Aggiorna progresso quando un ospite viene salvato
    $(document).on('guest-saved', function() {
        updateProgress();
    });
});
</script>
</div> <!-- .gabt-frontend-container -->

<?php
// Se il template viene caricato standalone, aggiungi footer
if (!wp_doing_ajax() && !headers_sent()) {
    get_footer();
}
?>
