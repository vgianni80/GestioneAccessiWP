<?php
/**
 * Template nuova prenotazione admin
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p class="description">Crea una nuova prenotazione per la tua struttura ricettiva</p>
    
    <div class="gabt-admin-container">
        <div class="gabt-page-header">
            <h1>Nuova Prenotazione</h1>
            <p class="description">Crea una nuova prenotazione nel sistema</p>

    <form id="gabt-new-booking-form" class="gabt-form" method="post">
        <?php wp_nonce_field('gabt_new_booking', 'gabt_nonce'); ?>
        
        <div class="gabt-form-row">
            <div class="gabt-form-field">
                <label for="checkin_date">Data Check-in <span class="required">*</span></label>
                <input type="date" id="checkin_date" name="checkin_date" required>
            </div>
            
            <div class="gabt-form-field">
                <label for="checkout_date">Data Check-out <span class="required">*</span></label>
                <input type="date" id="checkout_date" name="checkout_date" required>
            </div>
        </div>

        <div class="gabt-form-row">
            <div class="gabt-form-field">
                <label for="nights">Numero Notti <span class="required">*</span></label>
                <input type="number" id="nights" name="nights" min="1" required readonly>
                <div class="description">Calcolato automaticamente dalle date</div>
            </div>
            
            <div class="gabt-form-field">
                <label for="adults">Adulti <span class="required">*</span></label>
                <input type="number" id="adults" name="adults" min="1" required>
            </div>
        </div>

        <div class="gabt-form-row">
            <div class="gabt-form-field">
                <label for="children">Bambini</label>
                <input type="number" id="children" name="children" min="0" value="0">
            </div>
            
            <div class="gabt-form-field">
                <label for="total_guests">Ospiti Totali</label>
                <input type="number" id="total_guests" name="total_guests" readonly>
                <div class="description">Calcolato automaticamente</div>
            </div>
        </div>

        <h3>Informazioni Struttura</h3>
        
        <div class="gabt-form-field">
            <label for="accommodation_name">Nome Struttura</label>
            <input type="text" id="accommodation_name" name="accommodation_name" 
                   value="<?php echo esc_attr(get_option('gabt_accommodation_name', '')); ?>">
        </div>

        <div class="gabt-form-field">
            <label for="accommodation_address">Indirizzo Struttura</label>
            <textarea id="accommodation_address" name="accommodation_address" rows="3"><?php echo esc_textarea(get_option('gabt_accommodation_address', '')); ?></textarea>
        </div>

        <div class="gabt-form-row">
            <div class="gabt-form-field">
                <label for="accommodation_phone">Telefono</label>
                <input type="tel" id="accommodation_phone" name="accommodation_phone" 
                       value="<?php echo esc_attr(get_option('gabt_accommodation_phone', '')); ?>">
            </div>
            
            <div class="gabt-form-field">
                <label for="accommodation_email">Email</label>
                <input type="email" id="accommodation_email" name="accommodation_email" 
                       value="<?php echo esc_attr(get_option('gabt_accommodation_email', '')); ?>">
            </div>
        </div>

        <div class="gabt-form-field">
            <label for="notes">Note</label>
            <textarea id="notes" name="notes" rows="4" placeholder="Note aggiuntive sulla prenotazione..."></textarea>
        </div>

        <div class="gabt-form-actions">
            <button type="submit" name="save_booking" class="gabt-btn gabt-btn-primary">
                Crea Prenotazione
            </button>
            
            <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt'); ?>" 
               class="gabt-btn gabt-btn-secondary">
                Annulla
            </a>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Calcolo automatico notti e ospiti totali
    function updateCalculatedFields() {
        var checkin = $('#checkin_date').val();
        var checkout = $('#checkout_date').val();
        var adults = parseInt($('#adults').val()) || 0;
        var children = parseInt($('#children').val()) || 0;
        
        // Calcola notti
        if (checkin && checkout) {
            var checkinDate = new Date(checkin);
            var checkoutDate = new Date(checkout);
            
            if (checkoutDate > checkinDate) {
                var timeDiff = checkoutDate.getTime() - checkinDate.getTime();
                var nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
                $('#nights').val(nights);
            }
        }
        
        // Calcola ospiti totali
        $('#total_guests').val(adults + children);
    }
    
    $('#checkin_date, #checkout_date, #adults, #children').on('change', updateCalculatedFields);
    
    // Validazione date
    $('#checkin_date').on('change', function() {
        var today = new Date().toISOString().split('T')[0];
        if ($(this).val() < today) {
            alert('La data di check-in non può essere nel passato');
            $(this).val('');
        }
    });
    
    $('#checkout_date').on('change', function() {
        var checkin = $('#checkin_date').val();
        if (checkin && $(this).val() <= checkin) {
            alert('La data di check-out deve essere successiva al check-in');
            $(this).val('');
        }
    });
});
</script>
    </div> <!-- .gabt-admin-container -->
</div> <!-- .wrap -->
