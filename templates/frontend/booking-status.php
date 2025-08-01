<?php
/**
 * Template stato prenotazione frontend
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
    <div class="gabt-booking-status-page">
        <div class="gabt-page-header">
            <h1>Stato Prenotazione</h1>
            <p class="subtitle">Controlla lo stato della tua prenotazione e degli ospiti registrati</p>
        <h3>Cerca la tua prenotazione</h3>
        <form id="gabt-booking-search" method="get">
            <div class="gabt-form-field">
                <label for="booking_code">Codice Prenotazione</label>
                <input type="text" id="booking_code" name="booking_code" 
                       placeholder="Inserisci il codice prenotazione" required>
                <div class="description">Il codice ti è stato fornito al momento della prenotazione</div>
            </div>
            
            <button type="submit" class="gabt-btn gabt-btn-primary">
                Cerca Prenotazione
            </button>
        </form>
    </div>

    <?php elseif ($booking): ?>
    <!-- Dettagli prenotazione trovata -->
    <div class="gabt-booking-details">
        <div class="gabt-booking-header">
            <h2>Prenotazione: <?php echo esc_html($booking->booking_code); ?></h2>
            <div class="gabt-booking-status <?php echo esc_attr($booking->status); ?>">
                <?php
                $status_labels = [
                    'pending' => 'In Attesa',
                    'confirmed' => 'Confermata',
                    'guests_registered' => 'Ospiti Registrati',
                    'sent_to_police' => 'Inviata alla Polizia',
                    'completed' => 'Completata',
                    'cancelled' => 'Annullata'
                ];
                echo esc_html($status_labels[$booking->status] ?? 'Sconosciuto');
                ?>
            </div>
        </div>

        <div class="gabt-booking-info-grid">
            <div class="gabt-info-card">
                <h4>📅 Date del Soggiorno</h4>
                <p><strong>Check-in:</strong> <?php echo date('d/m/Y', strtotime($booking->checkin_date)); ?></p>
                <p><strong>Check-out:</strong> <?php echo date('d/m/Y', strtotime($booking->checkout_date)); ?></p>
                <p><strong>Notti:</strong> <?php echo $booking->nights; ?></p>
            </div>

            <div class="gabt-info-card">
                <h4>👥 Ospiti</h4>
                <p><strong>Adulti:</strong> <?php echo $booking->adults; ?></p>
                <p><strong>Bambini:</strong> <?php echo $booking->children; ?></p>
                <p><strong>Totale:</strong> <?php echo $booking->total_guests; ?></p>
            </div>

            <?php if (!empty($booking->accommodation_name)): ?>
            <div class="gabt-info-card">
                <h4>🏨 Struttura</h4>
                <p><strong><?php echo esc_html($booking->accommodation_name); ?></strong></p>
                <?php if (!empty($booking->accommodation_address)): ?>
                <p><?php echo esc_html($booking->accommodation_address); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($guests)): ?>
        <div class="gabt-guests-list">
            <h3>👤 Ospiti Registrati (<?php echo count($guests); ?>/<?php echo $booking->total_guests; ?>)</h3>
            
            <div class="gabt-guests-grid">
                <?php foreach ($guests as $guest): ?>
                <div class="gabt-guest-card">
                    <h4><?php echo esc_html($guest->first_name . ' ' . $guest->last_name); ?></h4>
                    <p><strong>Nato il:</strong> <?php echo date('d/m/Y', strtotime($guest->birth_date)); ?></p>
                    <p><strong>a:</strong> <?php echo esc_html($guest->birth_place); ?></p>
                    <p><strong>Documento:</strong> <?php echo esc_html($guest->document_type . ' ' . $guest->document_number); ?></p>
                    
                    <div class="gabt-guest-status <?php echo esc_attr($guest->status); ?>">
                        <?php
                        $guest_status_labels = [
                            'pending' => 'In Attesa',
                            'registered' => 'Registrato',
                            'sent' => 'Inviato',
                            'confirmed' => 'Confermato'
                        ];
                        echo esc_html($guest_status_labels[$guest->status] ?? 'Sconosciuto');
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Azioni disponibili -->
        <div class="gabt-booking-actions">
            <?php if ($booking->status === 'confirmed' && count($guests) < $booking->total_guests): ?>
            <a href="<?php echo home_url('/registrazione-ospiti/' . $booking->booking_code); ?>" 
               class="gabt-btn gabt-btn-primary">
                Completa Registrazione Ospiti
            </a>
            <?php endif; ?>

            <?php if (in_array($booking->status, ['guests_registered', 'sent_to_police'])): ?>
            <div class="gabt-status-message gabt-success">
                <p>✅ La registrazione è stata completata con successo!</p>
                <?php if ($booking->status === 'sent_to_police'): ?>
                <p>I dati sono stati inviati alla Polizia di Stato come richiesto dalla legge.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($booking->notes)): ?>
            <div class="gabt-booking-notes">
                <h4>📝 Note</h4>
                <p><?php echo esc_html($booking->notes); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- Prenotazione non trovata -->
    <div class="gabt-error-message">
        <h3>❌ Prenotazione non trovata</h3>
        <p>Il codice prenotazione inserito non è stato trovato nel sistema.</p>
        <p>Verifica di aver inserito il codice corretto o contatta la struttura per assistenza.</p>
        
        <a href="<?php echo remove_query_arg('booking_code'); ?>" class="gabt-btn gabt-btn-secondary">
            Cerca di nuovo
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.gabt-booking-status-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.gabt-search-form {
    background: #f9f9f9;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.gabt-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.gabt-booking-status {
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: bold;
    text-transform: uppercase;
    font-size: 0.9em;
}

.gabt-booking-status.pending { background: #fff3cd; color: #856404; }
.gabt-booking-status.confirmed { background: #d4edda; color: #155724; }
.gabt-booking-status.guests_registered { background: #cce5ff; color: #004085; }
.gabt-booking-status.sent_to_police { background: #e2e3e5; color: #383d41; }
.gabt-booking-status.completed { background: #d1ecf1; color: #0c5460; }
.gabt-booking-status.cancelled { background: #f8d7da; color: #721c24; }

.gabt-booking-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.gabt-info-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.gabt-info-card h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 1.1em;
}

.gabt-guests-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.gabt-guest-card {
    background: white;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #007cba;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.gabt-guest-status {
    margin-top: 10px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
    display: inline-block;
}

.gabt-guest-status.registered { background: #d4edda; color: #155724; }
.gabt-guest-status.sent { background: #cce5ff; color: #004085; }
.gabt-guest-status.confirmed { background: #d1ecf1; color: #0c5460; }

.gabt-status-message {
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.gabt-status-message.gabt-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.gabt-error-message {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .gabt-booking-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .gabt-booking-info-grid {
        grid-template-columns: 1fr;
    }
}
</style>
    </div> <!-- .gabt-booking-status-page -->
</div> <!-- .gabt-frontend-container -->

<?php
// Se il template viene caricato standalone, aggiungi footer
if (!wp_doing_ajax() && !headers_sent()) {
    get_footer();
}
?>
