<?php
/**
 * Gestori AJAX per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Ajax_Handlers {
    
    private $booking_repository;
    private $settings_manager;
    
    public function __construct() {
        $this->booking_repository = new GABT_Booking_Repository();
        $this->settings_manager = new GABT_Settings_Manager();
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook AJAX per admin
        add_action('wp_ajax_gabt_save_booking', array($this, 'save_booking'));
        add_action('wp_ajax_gabt_save_guest_data', array($this, 'save_guest_data'));
        add_action('wp_ajax_gabt_send_schedine', array($this, 'send_schedine'));
        add_action('wp_ajax_gabt_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_gabt_refresh_comuni', array($this, 'refresh_comuni'));
        add_action('wp_ajax_gabt_complete_registration', array($this, 'complete_registration'));
        add_action('wp_ajax_gabt_export_data', array($this, 'export_data'));
        
        // Hook AJAX per frontend (nopriv)
        add_action('wp_ajax_nopriv_gabt_save_guest_data', array($this, 'save_guest_data'));
        add_action('wp_ajax_nopriv_gabt_complete_registration', array($this, 'complete_registration'));
    }
    
    /**
     * Salva una prenotazione via AJAX
     */
    public function save_booking() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gabt_admin_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
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
            
            $booking_id = $this->booking_repository->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                wp_send_json_error(array(
                    'message' => $booking_id->get_error_message()
                ));
            }
            
            $booking = $this->booking_repository->get_booking($booking_id);
            
            wp_send_json_success(array(
                'booking_id' => $booking_id,
                'booking_code' => $booking->booking_code,
                'message' => 'Prenotazione salvata con successo'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore nel salvataggio: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Salva i dati di un ospite via AJAX
     */
    public function save_guest_data() {
        // Verifica nonce
        $nonce_action = is_admin() ? 'gabt_admin_nonce' : 'gabt_nonce';
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_die('Nonce verification failed');
        }
        
        try {
            $booking_code = sanitize_text_field($_POST['booking_code']);
            $booking = $this->booking_repository->get_booking_by_code($booking_code);
            
            if (!$booking) {
                wp_send_json_error(array(
                    'message' => 'Prenotazione non trovata'
                ));
            }
            
            $guest_data = array(
                'guest_type' => sanitize_text_field($_POST['guest_type']),
                'first_name' => sanitize_text_field($_POST['first_name']),
                'last_name' => sanitize_text_field($_POST['last_name']),
                'gender' => sanitize_text_field($_POST['gender']),
                'birth_date' => sanitize_text_field($_POST['birth_date']),
                'birth_place' => sanitize_text_field($_POST['birth_place']),
                'birth_province' => sanitize_text_field($_POST['birth_province']),
                'birth_country' => sanitize_text_field($_POST['birth_country']) ?: 'Italia',
                'nationality' => sanitize_text_field($_POST['nationality']) ?: 'Italia',
                'document_type' => sanitize_text_field($_POST['document_type']),
                'document_number' => sanitize_text_field($_POST['document_number']),
                'document_place' => sanitize_text_field($_POST['document_place']),
                'document_date' => sanitize_text_field($_POST['document_date'])
            );
            
            // Validazione
            $errors = GABT_Schedule_Formatter::validateGuestData((object)$guest_data);
            if (!empty($errors)) {
                wp_send_json_error(array(
                    'message' => 'Dati non validi',
                    'errors' => $errors
                ));
            }
            
            $guest_id = $this->booking_repository->add_guest($booking->id, $guest_data);
            
            if (is_wp_error($guest_id)) {
                wp_send_json_error(array(
                    'message' => $guest_id->get_error_message()
                ));
            }
            
            wp_send_json_success(array(
                'guest_id' => $guest_id,
                'message' => 'Ospite salvato con successo'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore nel salvataggio: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Invia le schedine per una prenotazione via AJAX
     */
    public function send_schedine() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gabt_admin_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $booking_id = intval($_POST['booking_id']);
            $booking = $this->booking_repository->get_booking($booking_id);
            
            if (!$booking) {
                wp_send_json_error(array(
                    'message' => 'Prenotazione non trovata'
                ));
            }
            
            if (empty($booking->guests)) {
                wp_send_json_error(array(
                    'message' => 'Nessun ospite registrato per questa prenotazione'
                ));
            }
            
            // Verifica configurazione
            if (!$this->settings_manager->are_alloggiati_settings_complete()) {
                wp_send_json_error(array(
                    'message' => 'Configurazione Alloggiati Web incompleta'
                ));
            }
            
            // Formatta le schedine
            $schedine = GABT_Schedule_Formatter::formatBookingSchedules($booking->guests, $booking);
            
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
                
                wp_send_json_success(array(
                    'message' => 'Schedine inviate con successo'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message'],
                    'debug_info' => $result['debug_info'] ?? null
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore nell\'invio: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Testa la connessione Alloggiati Web via AJAX
     */
    public function test_connection() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gabt_admin_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (empty($settings['username']) || empty($settings['password']) || empty($settings['ws_key'])) {
                wp_send_json_error(array(
                    'message' => 'Configurazione incompleta. Verifica username, password e WS Key.'
                ));
            }
            
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            $result = $client->testConnection();
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'details' => $result['details'] ?? null
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message']
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore durante il test: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Aggiorna la tabella comuni via AJAX
     */
    public function refresh_comuni() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gabt_admin_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (!$this->settings_manager->are_alloggiati_settings_complete()) {
                wp_send_json_error(array(
                    'message' => 'Configurazione Alloggiati Web incompleta'
                ));
            }
            
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            // Scarica la tabella luoghi (codice 0)
            $result = $client->downloadTableForTest(0);
            
            if ($result['success'] && !empty($result['debug_info']['tabella'])) {
                // Processa e salva i comuni
                $comuni_saved = $this->process_comuni_data($result['debug_info']['tabella']);
                
                wp_send_json_success(array(
                    'message' => "Tabella comuni aggiornata con successo. {$comuni_saved} comuni salvati."
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Errore nel download della tabella comuni: ' . $result['message']
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Completa la registrazione degli ospiti via AJAX
     */
    public function complete_registration() {
        // Verifica nonce
        $nonce_action = is_admin() ? 'gabt_admin_nonce' : 'gabt_nonce';
        if (!wp_verify_nonce($_POST['nonce'], $nonce_action)) {
            wp_die('Nonce verification failed');
        }
        
        try {
            $booking_code = sanitize_text_field($_POST['booking_code']);
            $booking = $this->booking_repository->get_booking_by_code($booking_code);
            
            if (!$booking) {
                wp_send_json_error(array(
                    'message' => 'Prenotazione non trovata'
                ));
            }
            
            if (empty($booking->guests)) {
                wp_send_json_error(array(
                    'message' => 'Nessun ospite registrato'
                ));
            }
            
            // Aggiorna lo status della prenotazione
            $this->booking_repository->update_booking($booking->id, array(
                'status' => 'confirmed'
            ));
            
            // Invia email di conferma se configurata
            $this->send_registration_complete_email($booking);
            
            wp_send_json_success(array(
                'message' => 'Registrazione completata con successo'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore nel completamento: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Esporta i dati via AJAX
     */
    public function export_data() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'gabt_admin_nonce')) {
            wp_die('Nonce verification failed');
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        try {
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            $date_from = sanitize_text_field($_POST['date_from'] ?? '');
            $date_to = sanitize_text_field($_POST['date_to'] ?? '');
            
            $filters = array();
            if ($date_from) $filters['date_from'] = $date_from;
            if ($date_to) $filters['date_to'] = $date_to;
            
            $bookings = $this->booking_repository->get_bookings($filters);
            
            if ($format === 'csv') {
                $csv_data = $this->generate_csv_export($bookings);
                
                wp_send_json_success(array(
                    'data' => $csv_data,
                    'filename' => 'prenotazioni_' . date('Y-m-d') . '.csv'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Formato non supportato'
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Errore nell\'esportazione: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Processa i dati dei comuni dalla tabella Alloggiati
     */
    private function process_comuni_data($tabella_data) {
        global $wpdb;
        
        $db_manager = new GABT_Database_Manager();
        $table_names = $db_manager->get_table_names();
        
        $saved = 0;
        
        foreach ($tabella_data as $row) {
            // Assumendo formato: codice|nome|provincia|regione
            $fields = explode('|', $row);
            
            if (count($fields) >= 3) {
                $comune_data = array(
                    'codice' => trim($fields[0]),
                    'nome' => trim($fields[1]),
                    'provincia' => trim($fields[2]),
                    'regione' => isset($fields[3]) ? trim($fields[3]) : ''
                );
                
                $result = $wpdb->replace($table_names['comuni'], $comune_data);
                if ($result) $saved++;
            }
        }
        
        return $saved;
    }
    
    /**
     * Invia email di completamento registrazione
     */
    private function send_registration_complete_email($booking) {
        $email_settings = $this->settings_manager->get_email_settings();
        
        if (empty($email_settings['guest_template']) || empty($booking->accommodation_email)) {
            return false;
        }
        
        $accommodation_settings = $this->settings_manager->get_accommodation_settings();
        
        // Sostituzioni template
        $replacements = array(
            '{guest_name}' => $booking->guests[0]->first_name . ' ' . $booking->guests[0]->last_name,
            '{booking_code}' => $booking->booking_code,
            '{checkin_date}' => date('d/m/Y', strtotime($booking->checkin_date)),
            '{checkout_date}' => date('d/m/Y', strtotime($booking->checkout_date)),
            '{nights}' => $booking->nights,
            '{accommodation_name}' => $accommodation_settings['name'] ?: get_bloginfo('name')
        );
        
        $message = str_replace(array_keys($replacements), array_values($replacements), $email_settings['guest_template']);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>'
        );
        
        return wp_mail(
            $booking->accommodation_email,
            $email_settings['guest_subject'],
            nl2br($message),
            $headers
        );
    }
    
    /**
     * Genera esportazione CSV
     */
    private function generate_csv_export($bookings) {
        $csv = "Codice Prenotazione,Check-in,Check-out,Notti,Adulti,Bambini,Status,Schedine Inviate\n";
        
        foreach ($bookings as $booking) {
            $csv .= sprintf(
                "%s,%s,%s,%d,%d,%d,%s,%s\n",
                $booking->booking_code,
                $booking->checkin_date,
                $booking->checkout_date,
                $booking->nights,
                $booking->adults,
                $booking->children,
                $booking->status,
                $booking->schedine_sent ? 'Sì' : 'No'
            );
        }
        
        return $csv;
    }
}
?>