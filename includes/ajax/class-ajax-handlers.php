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
    
    /**
     * Azioni AJAX ammesse
     */
    const ALLOWED_ACTIONS = array(
        'gabt_save_booking',
        'gabt_save_guest_data',
        'gabt_send_schedine',
        'gabt_test_connection',
        'gabt_refresh_comuni',
        'gabt_complete_registration',
        'gabt_export_data'
    );
    
    public function __construct() {
        $this->booking_repository = new GABT_Booking_Repository();
        $this->settings_manager = new GABT_Settings_Manager();
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook AJAX per admin
        foreach (self::ALLOWED_ACTIONS as $action) {
            add_action('wp_ajax_' . $action, array($this, str_replace('gabt_', '', $action)));
        }
        
        // Hook AJAX per frontend (nopriv)
        $nopriv_actions = array('gabt_save_guest_data', 'gabt_complete_registration');
        foreach ($nopriv_actions as $action) {
            add_action('wp_ajax_nopriv_' . $action, array($this, str_replace('gabt_', '', $action)));
        }
    }
    
    /**
     * Verifica nonce per richieste admin
     */
    private function verify_admin_request() {
        if (!check_ajax_referer('gabt_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Richiesta non valida. Ricarica la pagina e riprova.'
            ), 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Non hai i permessi necessari per questa azione.'
            ), 403);
        }
    }
    
    /**
     * Verifica nonce per richieste frontend
     */
    private function verify_frontend_request() {
        $nonce_action = is_admin() ? 'gabt_admin_nonce' : 'gabt_frontend_nonce';
        
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Richiesta non valida. Ricarica la pagina e riprova.'
            ), 403);
        }
    }
    
    /**
     * Salva una prenotazione via AJAX
     */
    public function save_booking() {
        $this->verify_admin_request();
        
        try {
            // Validazione base
            $required_fields = array('checkin_date', 'checkout_date', 'adults');
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Campo obbligatorio mancante: {$field}");
                }
            }
            
            $booking_data = array(
                'checkin_date' => sanitize_text_field($_POST['checkin_date']),
                'checkout_date' => sanitize_text_field($_POST['checkout_date']),
                'nights' => absint($_POST['nights'] ?? 0),
                'adults' => absint($_POST['adults']),
                'children' => absint($_POST['children'] ?? 0),
                'total_guests' => absint($_POST['adults']) + absint($_POST['children'] ?? 0),
                'accommodation_name' => sanitize_text_field($_POST['accommodation_name'] ?? ''),
                'accommodation_address' => sanitize_textarea_field($_POST['accommodation_address'] ?? ''),
                'accommodation_phone' => sanitize_text_field($_POST['accommodation_phone'] ?? ''),
                'accommodation_email' => sanitize_email($_POST['accommodation_email'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
            );
            
            $booking_id = $this->booking_repository->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                throw new Exception($booking_id->get_error_message());
            }
            
            $booking = $this->booking_repository->get_booking($booking_id);
            
            wp_send_json_success(array(
                'booking_id' => $booking_id,
                'booking_code' => $booking->booking_code,
                'message' => 'Prenotazione salvata con successo',
                'redirect_url' => admin_url('admin.php?page=gestione-accessi-bt-booking-details&id=' . $booking_id)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Salva i dati di un ospite via AJAX
     */
    public function save_guest_data() {
        $this->verify_frontend_request();
        
        try {
            // Validazione booking code
            if (empty($_POST['booking_code'])) {
                throw new Exception('Codice prenotazione mancante');
            }
            
            $booking_code = sanitize_text_field($_POST['booking_code']);
            $booking = $this->booking_repository->get_booking_by_code($booking_code);
            
            if (!$booking) {
                throw new Exception('Prenotazione non trovata');
            }
            
            // Prepara dati ospite
            $guest_data = array(
                'guest_type' => sanitize_text_field($_POST['guest_type'] ?? 'componente_famiglia'),
                'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
                'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
                'gender' => sanitize_text_field($_POST['gender'] ?? ''),
                'birth_date' => sanitize_text_field($_POST['birth_date'] ?? ''),
                'birth_place' => sanitize_text_field($_POST['birth_place'] ?? ''),
                'birth_province' => sanitize_text_field($_POST['birth_province'] ?? ''),
                'birth_country' => sanitize_text_field($_POST['birth_country'] ?? 'Italia'),
                'nationality' => sanitize_text_field($_POST['nationality'] ?? 'Italia'),
                'document_type' => sanitize_text_field($_POST['document_type'] ?? ''),
                'document_number' => sanitize_text_field($_POST['document_number'] ?? ''),
                'document_place' => sanitize_text_field($_POST['document_place'] ?? ''),
                'document_date' => sanitize_text_field($_POST['document_date'] ?? '')
            );
            
            // Validazione con Schedule Formatter
            $errors = GABT_Schedule_Formatter::validateGuestData((object)$guest_data);
            if (!empty($errors)) {
                throw new Exception('Dati non validi: ' . implode(', ', $errors));
            }
            
            $guest_id = $this->booking_repository->add_guest($booking->id, $guest_data);
            
            if (is_wp_error($guest_id)) {
                throw new Exception($guest_id->get_error_message());
            }
            
            wp_send_json_success(array(
                'guest_id' => $guest_id,
                'message' => 'Ospite salvato con successo',
                'remaining_guests' => $booking->total_guests - count($booking->guests) - 1
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'field_errors' => $this->parse_field_errors($e->getMessage())
            ));
        }
    }
    
    /**
     * Invia le schedine per una prenotazione via AJAX
     */
    public function send_schedine() {
        $this->verify_admin_request();
        
        try {
            $booking_id = absint($_POST['booking_id'] ?? 0);
            
            if (!$booking_id) {
                throw new Exception('ID prenotazione non valido');
            }
            
            $booking = $this->booking_repository->get_booking($booking_id);
            
            if (!$booking) {
                throw new Exception('Prenotazione non trovata');
            }
            
            if (empty($booking->guests)) {
                throw new Exception('Nessun ospite registrato per questa prenotazione');
            }
            
            // Verifica configurazione
            if (!$this->settings_manager->are_alloggiati_settings_complete()) {
                throw new Exception('Configurazione Alloggiati Web incompleta. Verifica le impostazioni.');
            }
            
            // Formatta le schedine
            $schedine = GABT_Schedule_Formatter::formatBookingSchedules($booking->guests, $booking);
            
            if (empty($schedine)) {
                throw new Exception('Impossibile generare le schedine');
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
                
                wp_send_json_success(array(
                    'message' => 'Schedine inviate con successo',
                    'schedine_count' => count($schedine)
                ));
            } else {
                throw new Exception($result['message'] ?? 'Errore sconosciuto durante l\'invio');
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'debug_info' => WP_DEBUG ? $e->getTrace() : null
            ));
        }
    }
    
    /**
     * Testa la connessione Alloggiati Web via AJAX
     */
    public function test_connection() {
        $this->verify_admin_request();
        
        try {
            $test_type = sanitize_text_field($_POST['test_type'] ?? 'connection');
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (empty($settings['username']) || empty($settings['password']) || empty($settings['ws_key'])) {
                throw new Exception('Configurazione incompleta. Verifica username, password e WS Key.');
            }
            
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            switch ($test_type) {
                case 'authentication':
                    $result = $client->testAuthentication();
                    break;
                    
                case 'download_tables':
                    $result = $client->downloadTableForTest(0); // Tabella Luoghi
                    break;
                    
                default:
                    $result = $client->testConnection();
            }
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'details' => $result['details'] ?? null
                ));
            } else {
                throw new Exception($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Aggiorna la tabella comuni via AJAX
     */
    public function refresh_comuni() {
        $this->verify_admin_request();
        
        try {
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (!$this->settings_manager->are_alloggiati_settings_complete()) {
                throw new Exception('Configurazione Alloggiati Web incompleta');
            }
            
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            // Scarica la tabella luoghi (codice 0)
            $result = $client->downloadTableForTest(0);
            
            if (!$result['success']) {
                throw new Exception('Errore nel download della tabella: ' . $result['message']);
            }
            
            if (empty($result['debug_info']['tabella'])) {
                throw new Exception('Nessun dato ricevuto dalla tabella comuni');
            }
            
            // Processa e salva i comuni
            $comuni_saved = $this->process_comuni_data($result['debug_info']['tabella']);
            
            wp_send_json_success(array(
                'message' => "Tabella comuni aggiornata con successo. {$comuni_saved} comuni salvati.",
                'comuni_saved' => $comuni_saved
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Completa la registrazione degli ospiti via AJAX
     */
    public function complete_registration() {
        $this->verify_frontend_request();
        
        try {
            if (empty($_POST['booking_code'])) {
                throw new Exception('Codice prenotazione mancante');
            }
            
            $booking_code = sanitize_text_field($_POST['booking_code']);
            $booking = $this->booking_repository->get_booking_by_code($booking_code);
            
            if (!$booking) {
                throw new Exception('Prenotazione non trovata');
            }
            
            if (empty($booking->guests)) {
                throw new Exception('Nessun ospite registrato');
            }
            
            if (count($booking->guests) < $booking->total_guests) {
                throw new Exception('Non tutti gli ospiti sono stati registrati');
            }
            
            // Aggiorna lo status della prenotazione
            $updated = $this->booking_repository->update_booking($booking->id, array(
                'status' => 'confirmed'
            ));
            
            if (!$updated) {
                throw new Exception('Errore durante l\'aggiornamento della prenotazione');
            }
            
            // Invia email di conferma se configurata
            $this->send_registration_complete_email($booking);
            
            wp_send_json_success(array(
                'message' => 'Registrazione completata con successo',
                'redirect_url' => home_url('/gestione-accessi/' . $booking_code . '/conferma/')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Esporta i dati via AJAX
     */
    public function export_data() {
        $this->verify_admin_request();
        
        try {
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            $date_from = sanitize_text_field($_POST['date_from'] ?? '');
            $date_to = sanitize_text_field($_POST['date_to'] ?? '');
            
            if (!in_array($format, array('csv', 'json'))) {
                throw new Exception('Formato non supportato');
            }
            
            $filters = array();
            if ($date_from) $filters['date_from'] = $date_from;
            if ($date_to) $filters['date_to'] = $date_to;
            
            $bookings = $this->booking_repository->get_bookings($filters);
            
            if ($format === 'csv') {
                $csv_data = $this->generate_csv_export($bookings);
                
                wp_send_json_success(array(
                    'data' => base64_encode($csv_data),
                    'filename' => 'prenotazioni_' . date('Y-m-d') . '.csv',
                    'mime_type' => 'text/csv'
                ));
            } else {
                $json_data = $this->generate_json_export($bookings);
                
                wp_send_json_success(array(
                    'data' => base64_encode($json_data),
                    'filename' => 'prenotazioni_' . date('Y-m-d') . '.json',
                    'mime_type' => 'application/json'
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
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
        $errors = 0;
        
        // Inizia transazione
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($tabella_data as $row) {
                // Assumendo formato: codice|nome|provincia|regione
                $fields = explode('|', $row);
                
                if (count($fields) >= 3) {
                    $comune_data = array(
                        'codice' => sanitize_text_field(trim($fields[0])),
                        'nome' => sanitize_text_field(trim($fields[1])),
                        'provincia' => sanitize_text_field(trim($fields[2])),
                        'regione' => isset($fields[3]) ? sanitize_text_field(trim($fields[3])) : ''
                    );
                    
                    // Validazione base
                    if (empty($comune_data['codice']) || empty($comune_data['nome'])) {
                        $errors++;
                        continue;
                    }
                    
                    $result = $wpdb->replace(
                        $table_names['comuni'],
                        $comune_data,
                        array('%s', '%s', '%s', '%s')
                    );
                    
                    if ($result !== false) {
                        $saved++;
                    } else {
                        $errors++;
                    }
                }
            }
            
            $wpdb->query('COMMIT');
            
            // Log operazione
            $this->log_operation('comuni_import', array(
                'saved' => $saved,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
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
            '{accommodation_name}' => $accommodation_settings['name'] ?: get_bloginfo('name'),
            '{total_guests}' => $booking->total_guests,
            '{booking_url}' => home_url('/gestione-accessi/' . $booking->booking_code . '/stato/')
        );
        
        $message = str_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $email_settings['guest_template']
        );
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>'
        );
        
        $sent = wp_mail(
            $booking->accommodation_email,
            $email_settings['guest_subject'],
            nl2br($message),
            $headers
        );
        
        if ($sent) {
            $this->log_operation('email_sent', array(
                'booking_id' => $booking->id,
                'recipient' => $booking->accommodation_email
            ));
        }
        
        return $sent;
    }
    
    /**
     * Genera esportazione CSV
     */
    private function generate_csv_export($bookings) {
        $csv = "Codice Prenotazione,Check-in,Check-out,Notti,Adulti,Bambini,Totale Ospiti,Status,Schedine Inviate,Struttura,Email,Telefono,Data Creazione\n";
        
        foreach ($bookings as $booking) {
            $csv .= sprintf(
                '"%s","%s","%s",%d,%d,%d,%d,"%s","%s","%s","%s","%s","%s"' . "\n",
                $booking->booking_code,
                $booking->checkin_date,
                $booking->checkout_date,
                $booking->nights,
                $booking->adults,
                $booking->children,
                $booking->total_guests,
                $booking->status,
                $booking->schedine_sent ? 'Sì' : 'No',
                $booking->accommodation_name ?? '',
                $booking->accommodation_email ?? '',
                $booking->accommodation_phone ?? '',
                $booking->created_at
            );
        }
        
        return $csv;
    }
    
    /**
     * Genera esportazione JSON
     */
    private function generate_json_export($bookings) {
        $export_data = array();
        
        foreach ($bookings as $booking) {
            // Carica anche gli ospiti per ogni prenotazione
            $booking->guests = $this->booking_repository->get_booking_guests($booking->id);
            
            $booking_data = array(
                'booking_code' => $booking->booking_code,
                'checkin_date' => $booking->checkin_date,
                'checkout_date' => $booking->checkout_date,
                'nights' => $booking->nights,
                'adults' => $booking->adults,
                'children' => $booking->children,
                'total_guests' => $booking->total_guests,
                'status' => $booking->status,
                'schedine_sent' => (bool)$booking->schedine_sent,
                'schedine_sent_date' => $booking->schedine_sent_date,
                'accommodation' => array(
                    'name' => $booking->accommodation_name,
                    'address' => $booking->accommodation_address,
                    'phone' => $booking->accommodation_phone,
                    'email' => $booking->accommodation_email
                ),
                'notes' => $booking->notes,
                'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
                'guests' => array()
            );
            
            // Aggiungi dati ospiti
            foreach ($booking->guests as $guest) {
                $booking_data['guests'][] = array(
                    'type' => $guest->guest_type,
                    'full_name' => $guest->first_name . ' ' . $guest->last_name,
                    'gender' => $guest->gender,
                    'birth_date' => $guest->birth_date,
                    'birth_place' => $guest->birth_place,
                    'nationality' => $guest->nationality,
                    'document' => array(
                        'type' => $guest->document_type,
                        'number' => $guest->document_number,
                        'place' => $guest->document_place,
                        'date' => $guest->document_date
                    ),
                    'status' => $guest->status,
                    'sent_to_police' => (bool)$guest->sent_to_police
                );
            }
            
            $export_data[] = $booking_data;
        }
        
        return json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Analizza errori di campo dal messaggio
     */
    private function parse_field_errors($message) {
        $field_errors = array();
        
        // Pattern comuni di errore per campo
        $patterns = array(
            'first_name' => '/nome/i',
            'last_name' => '/cognome/i',
            'birth_date' => '/data.*nascita/i',
            'document_number' => '/numero.*documento/i',
            'gender' => '/sesso/i'
        );
        
        foreach ($patterns as $field => $pattern) {
            if (preg_match($pattern, $message)) {
                $field_errors[$field] = $message;
            }
        }
        
        return $field_errors;
    }
    
    /**
     * Log operazioni
     */
    private function log_operation($operation, $data = array()) {
        global $wpdb;
        
        $db_manager = new GABT_Database_Manager();
        $table_names = $db_manager->get_table_names();
        
        $wpdb->insert(
            $table_names['logs'],
            array(
                'log_type' => 'ajax_' . $operation,
                'message' => "AJAX operation: {$operation}",
                'context' => json_encode($data),
                'level' => 'info',
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Ottiene i limiti di rate per le operazioni
     */
    private function check_rate_limit($operation, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $transient_key = 'gabt_rate_limit_' . md5($operation . $user_id . $ip);
        $attempts = get_transient($transient_key) ?: 0;
        
        if ($attempts >= $limit) {
            wp_send_json_error(array(
                'message' => 'Troppe richieste. Riprova tra qualche minuto.'
            ), 429);
        }
        
        set_transient($transient_key, $attempts + 1, $window);
    }
}