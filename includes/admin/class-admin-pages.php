<?php
/**
 * Pagine admin per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Admin_Pages {
    
    private $booking_repository;
    private $settings_manager;
    
    public function __construct() {
        $this->booking_repository = new GABT_Booking_Repository();
        $this->settings_manager = new GABT_Settings_Manager();
    }

    // Aggiungi dopo il costruttore:
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook necessari per le pagine admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Carica assets admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gestione-accessi-bt') === false) {
            return;
        }
        // Carica CSS/JS specifici
    }
    
    /**
     * Pagina principale - Dashboard
     */
    public function main_page() {
        $stats = $this->booking_repository->get_booking_stats();
        $recent_bookings = $this->booking_repository->get_bookings(array('limit' => 5));
        
        include GABT_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Pagina nuova prenotazione
     */
    public function new_booking_page() {
        $message = '';
        $message_type = '';
        
        // Gestisce il salvataggio della prenotazione
        if (isset($_POST['save_booking']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_new_booking')) {
            $result = $this->process_new_booking();
            
            if (is_wp_error($result)) {
                $message = $result->get_error_message();
                $message_type = 'error';
            } else {
                $message = 'Prenotazione creata con successo! Codice: ' . $result['booking_code'];
                $message_type = 'success';
                
                // Redirect alla pagina dettagli
                wp_redirect(GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-booking-details', array('id' => $result['booking_id'])));
                exit;
            }
        }
        
        include GABT_PLUGIN_PATH . 'templates/admin/new-booking.php';
    }
    
    /**
     * Pagina gestione prenotazioni
     */
    public function manage_bookings_page() {
        $filters = array();
        
        // Gestisce i filtri
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $filters['status'] = sanitize_text_field($_GET['status']);
        }
        
        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $filters['date_from'] = sanitize_text_field($_GET['date_from']);
        }
        
        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            $filters['date_to'] = sanitize_text_field($_GET['date_to']);
        }
        
        // Paginazione
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $filters['limit'] = $per_page;
        $filters['offset'] = ($page - 1) * $per_page;
        
        $bookings = $this->booking_repository->get_bookings($filters);
        
        include GABT_PLUGIN_PATH . 'templates/admin/manage-bookings.php';
    }
    
    /**
     * Pagina dettagli prenotazione
     */
    public function booking_details_page() {
        $booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$booking_id) {
            wp_die('ID prenotazione non valido');
        }
        
        $booking = $this->booking_repository->get_booking($booking_id);
        
        if (!$booking) {
            wp_die('Prenotazione non trovata');
        }
        
        $message = '';
        $message_type = '';
        
        // Gestisce le azioni sulla prenotazione
        if (isset($_POST['action']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_booking_action')) {
            $action = sanitize_text_field($_POST['action']);
            
            switch ($action) {
                case 'send_schedine':
                    $result = $this->send_schedine_for_booking($booking_id);
                    if ($result['success']) {
                        $message = 'Schedine inviate con successo!';
                        $message_type = 'success';
                        $booking = $this->booking_repository->get_booking($booking_id); // Ricarica
                    } else {
                        $message = 'Errore nell\'invio delle schedine: ' . $result['message'];
                        $message_type = 'error';
                    }
                    break;
                    
                case 'update_status':
                    $new_status = sanitize_text_field($_POST['new_status']);
                    $this->booking_repository->update_booking($booking_id, array('status' => $new_status));
                    $message = 'Status aggiornato con successo!';
                    $message_type = 'success';
                    $booking = $this->booking_repository->get_booking($booking_id); // Ricarica
                    break;
            }
        }
        
        include GABT_PLUGIN_PATH . 'templates/admin/booking-details.php';
    }
    
    /**
     * Pagina impostazioni
     */
    public function settings_page() {
        $message = '';
        $message_type = '';
        
        // Gestisce il salvataggio delle impostazioni
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_settings')) {
            $result = $this->settings_manager->save_settings($_POST);
            
            if ($result) {
                $message = 'Impostazioni salvate con successo!';
                $message_type = 'success';
            } else {
                $message = 'Errore nel salvataggio delle impostazioni';
                $message_type = 'error';
            }
        }
        
        $settings = $this->settings_manager->get_all_settings();
        
        include GABT_PLUGIN_PATH . 'templates/admin/settings.php';
    }
    
    /**
     * Pagina test connessione
     */
    public function test_page() {
        $test_result = null;
        
        // Gestisce il test della connessione
        if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_test')) {
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (empty($settings['username']) || empty($settings['password']) || empty($settings['ws_key'])) {
                $test_result = array(
                    'success' => false,
                    'message' => 'Configurazione incompleta. Verifica le impostazioni.'
                );
            } else {
                $client = new GABT_Alloggiati_Client(
                    $settings['username'],
                    $settings['password'],
                    $settings['ws_key']
                );
                
                $test_result = $client->testConnection();
            }
        }
        
        include GABT_PLUGIN_PATH . 'templates/admin/test-connection.php';
    }
    
    /**
     * Processa una nuova prenotazione
     */
    private function process_new_booking() {
        // Sanitizza e valida i dati
        $booking_data = array(
            'checkin_date' => sanitize_text_field($_POST['checkin_date']),
            'checkout_date' => sanitize_text_field($_POST['checkout_date']),
            'nights' => intval($_POST['nights']),
            'adults' => intval($_POST['adults']),
            'children' => intval($_POST['children']),
            'total_guests' => intval($_POST['adults']) + intval($_POST['children']),
            'accommodation_name' => sanitize_text_field($_POST['accommodation_name']),
            'accommodation_address' => sanitize_textarea_field($_POST['accommodation_address']),
            'accommodation_phone' => sanitize_text_field($_POST['accommodation_phone']),
            'accommodation_email' => sanitize_email($_POST['accommodation_email']),
            'notes' => sanitize_textarea_field($_POST['notes'])
        );
        
        // Validazioni
        if (empty($booking_data['checkin_date']) || empty($booking_data['checkout_date'])) {
            return new WP_Error('missing_dates', 'Date di check-in e check-out sono obbligatorie');
        }
        
        if ($booking_data['nights'] <= 0) {
            return new WP_Error('invalid_nights', 'Numero di notti non valido');
        }
        
        if ($booking_data['total_guests'] <= 0) {
            return new WP_Error('invalid_guests', 'Numero di ospiti non valido');
        }
        
        // Crea la prenotazione
        $booking_id = $this->booking_repository->create_booking($booking_data);
        
        if (is_wp_error($booking_id)) {
            return $booking_id;
        }
        
        // Ottiene il codice prenotazione generato
        $booking = $this->booking_repository->get_booking($booking_id);
        
        return array(
            'booking_id' => $booking_id,
            'booking_code' => $booking->booking_code
        );
    }
    
    /**
     * Invia le schedine per una prenotazione
     */
    private function send_schedine_for_booking($booking_id) {
        $booking = $this->booking_repository->get_booking($booking_id);
        
        if (!$booking || empty($booking->guests)) {
            return array(
                'success' => false,
                'message' => 'Prenotazione non trovata o senza ospiti'
            );
        }
        
        // Formatta le schedine
        $schedine = GABT_Schedule_Formatter::formatBookingSchedules($booking->guests, $booking);
        
        if (empty($schedine)) {
            return array(
                'success' => false,
                'message' => 'Impossibile generare le schedine'
            );
        }
        
        // Invia le schedine
        $settings = $this->settings_manager->get_alloggiati_settings();
        $client = new GABT_Alloggiati_Client(
            $settings['username'],
            $settings['password'],
            $settings['ws_key']
        );
        
        $result = $client->sendSchedule($schedine);
        
        if ($result['success']) {
            // Marca come inviate
            $this->booking_repository->mark_schedine_sent($booking_id);
        }
        
        return $result;
    }
    
    /**
     * Ottiene le opzioni per i select
     */
    public function get_status_options() {
        return array(
            'pending' => 'In Attesa',
            'confirmed' => 'Confermata',
            'completed' => 'Completata',
            'cancelled' => 'Annullata'
        );
    }
    
    /**
     * Ottiene le opzioni per i tipi di ospite
     */
    public function get_guest_type_options() {
        return array(
            'capofamiglia' => 'Capofamiglia',
            'componente_famiglia' => 'Componente Famiglia',
            'capogruppo' => 'Capogruppo',
            'componente_gruppo' => 'Componente Gruppo'
        );
    }
    
    /**
     * Ottiene le opzioni per i tipi di documento
     */
    public function get_document_type_options() {
        return array(
            'CI' => 'Carta d\'Identità',
            'PP' => 'Passaporto',
            'PG' => 'Patente di Guida'
        );
    }
}
