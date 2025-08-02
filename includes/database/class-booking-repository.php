<?php
/**
 * Repository per la gestione delle prenotazioni
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Booking_Repository {
    
    private $table_names;
    
    /**
     * Stati prenotazione validi
     */
    const VALID_STATUSES = array(
        'pending', 'confirmed', 'completed', 'cancelled'
    );
    
    public function __construct() {
        global $wpdb;
        $this->table_names = array(
            'bookings' => $wpdb->prefix . 'gabt_bookings',
            'guests' => $wpdb->prefix . 'gabt_guests',
            'logs' => $wpdb->prefix . 'gabt_logs'
        );
    }
    
    /**
     * Crea una nuova prenotazione
     */
    public function create_booking($booking_data) {
        global $wpdb;
        
        // Validazione dati
        $validation_errors = $this->validate_booking_data($booking_data);
        if (!empty($validation_errors)) {
            return new WP_Error('validation_error', implode(', ', $validation_errors));
        }
        
        // Sanitizzazione dati
        $sanitized_data = $this->sanitize_booking_data($booking_data);
        
        // Aggiungi defaults
        $defaults = array(
            'booking_code' => $this->generate_booking_code(),
            'status' => 'pending',
            'schedine_sent' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $booking_data = wp_parse_args($sanitized_data, $defaults);
        
        // Inizia transazione
        $wpdb->query('START TRANSACTION');
        
        try {
            $result = $wpdb->insert(
                $this->table_names['bookings'],
                $booking_data,
                $this->get_booking_format($booking_data)
            );
            
            if ($result === false) {
                throw new Exception($wpdb->last_error ?: 'Errore inserimento database');
            }
            
            $booking_id = $wpdb->insert_id;
            
            // Commit transazione
            $wpdb->query('COMMIT');
            
            // Log creazione
            $this->log_booking_action($booking_id, 'created');
            
            return $booking_id;
            
        } catch (Exception $e) {
            // Rollback in caso di errore
            $wpdb->query('ROLLBACK');
            
            $this->log_error('Errore creazione prenotazione: ' . $e->getMessage());
            
            return new WP_Error('db_error', 'Errore nella creazione della prenotazione: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottiene una prenotazione per ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        
        $booking_id = absint($booking_id);
        
        if (!$booking_id) {
            return null;
        }
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['bookings']} WHERE id = %d",
            $booking_id
        ));
        
        if ($booking) {
            // Carica anche gli ospiti
            $booking->guests = $this->get_booking_guests($booking_id);
        }
        
        return $booking;
    }
    
    /**
     * Ottiene una prenotazione per codice
     */
    public function get_booking_by_code($booking_code) {
        global $wpdb;
        
        $booking_code = sanitize_text_field($booking_code);
        
        if (empty($booking_code)) {
            return null;
        }
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['bookings']} WHERE booking_code = %s",
            $booking_code
        ));
        
        if ($booking) {
            $booking->guests = $this->get_booking_guests($booking->id);
        }
        
        return $booking;
    }
    
    /**
     * Ottiene tutte le prenotazioni con filtri
     */
    public function get_bookings($filters = array()) {
        global $wpdb;
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        // Filtro status
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES)) {
            $where_clauses[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        // Filtro date
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "checkin_date >= %s";
            $where_values[] = sanitize_text_field($filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "checkin_date <= %s";
            $where_values[] = sanitize_text_field($filters['date_to']);
        }
        
        // Costruisci WHERE
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        // Ordinamento
        $order_by = 'created_at DESC';
        
        // Paginazione
        $limit_sql = '';
        if (isset($filters['limit'])) {
            $limit = absint($filters['limit']);
            $offset = isset($filters['offset']) ? absint($filters['offset']) : 0;
            $limit_sql = $wpdb->prepare("LIMIT %d, %d", $offset, $limit);
        }
        
        // Costruisci query completa
        $sql = "SELECT * FROM {$this->table_names['bookings']} {$where_sql} ORDER BY {$order_by} {$limit_sql}";
        
        // Esegui query
        if (!empty($where_values)) {
            $query = $wpdb->prepare($sql, $where_values);
            $bookings = $wpdb->get_results($query);
        } else {
            $bookings = $wpdb->get_results($sql);
        }
        
        return $bookings ?: array();
    }
    
    /**
     * Aggiorna una prenotazione
     */
    public function update_booking($booking_id, $booking_data) {
        global $wpdb;
        
        $booking_id = absint($booking_id);
        
        if (!$booking_id) {
            return false;
        }
        
        // Sanitizza dati
        $booking_data = $this->sanitize_booking_data($booking_data);
        $booking_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_names['bookings'],
            $booking_data,
            array('id' => $booking_id),
            $this->get_booking_format($booking_data),
            array('%d')
        );
        
        if ($result !== false) {
            // Log aggiornamento
            $this->log_booking_action($booking_id, 'updated', $booking_data);
        }
        
        return $result !== false;
    }
    
    /**
     * Aggiunge un ospite a una prenotazione
     */
    public function add_guest($booking_id, $guest_data) {
        global $wpdb;
        
        $booking_id = absint($booking_id);
        
        if (!$booking_id) {
            return new WP_Error('invalid_booking', 'ID prenotazione non valido');
        }
        
        // Verifica che la prenotazione esista
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return new WP_Error('booking_not_found', 'Prenotazione non trovata');
        }
        
        // Sanitizza dati ospite
        $guest_data = $this->sanitize_guest_data($guest_data);
        $guest_data['booking_id'] = $booking_id;
        $guest_data['created_at'] = current_time('mysql');
        $guest_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $this->table_names['guests'],
            $guest_data,
            $this->get_guest_format($guest_data)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Errore nell\'aggiunta dell\'ospite: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Ottiene gli ospiti di una prenotazione
     */
    public function get_booking_guests($booking_id) {
        global $wpdb;
        
        $booking_id = absint($booking_id);
        
        if (!$booking_id) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['guests']} 
             WHERE booking_id = %d 
             ORDER BY guest_type DESC, id ASC",
            $booking_id
        )) ?: array();
    }
    
    /**
     * Marca le schedine come inviate
     */
    public function mark_schedine_sent($booking_id) {
        global $wpdb;
        
        $result = $this->update_booking($booking_id, array(
            'schedine_sent' => 1,
            'schedine_sent_date' => current_time('mysql'),
            'status' => 'completed'
        ));
        
        if ($result) {
            // Aggiorna anche status ospiti
            $wpdb->update(
                $this->table_names['guests'],
                array(
                    'sent_to_police' => 1,
                    'sent_at' => current_time('mysql'),
                    'status' => 'sent'
                ),
                array('booking_id' => $booking_id),
                array('%d', '%s', '%s'),
                array('%d')
            );
        }
        
        return $result;
    }
    
    /**
     * Ottiene le prenotazioni pronte per l'invio delle schedine
     */
    public function get_bookings_ready_for_schedine() {
        global $wpdb;
        
        $sql = "SELECT b.*, COUNT(g.id) as guest_count 
                FROM {$this->table_names['bookings']} b 
                LEFT JOIN {$this->table_names['guests']} g ON b.id = g.booking_id 
                WHERE b.schedine_sent = 0 
                AND b.status = 'confirmed' 
                AND b.checkin_date <= CURDATE() 
                GROUP BY b.id 
                HAVING guest_count > 0
                ORDER BY b.checkin_date ASC";
        
        return $wpdb->get_results($sql) ?: array();
    }
    
    /**
     * Genera un codice prenotazione univoco
     */
    private function generate_booking_code() {
        do {
            $code = 'BT' . date('Y') . wp_generate_password(6, false, false);
            $code = strtoupper($code);
        } while ($this->booking_code_exists($code));
        
        return $code;
    }
    
    /**
     * Verifica se un codice prenotazione esiste già
     */
    private function booking_code_exists($code) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_names['bookings']} WHERE booking_code = %s",
            $code
        ));
        
        return $count > 0;
    }
    
    /**
     * Ottiene statistiche delle prenotazioni
     */
    public function get_booking_stats() {
        global $wpdb;
        
        $stats = array();
        
        try {
            // Prenotazioni totali
            $stats['total'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_names['bookings']}"
            ) ?: 0;
            
            // Schedine inviate
            $stats['schedine_sent'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_names['bookings']} 
                 WHERE schedine_sent = 1"
            ) ?: 0;
            
            // Prenotazioni questo mese
            $stats['this_month'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_names['bookings']} 
                 WHERE MONTH(created_at) = MONTH(CURDATE()) 
                 AND YEAR(created_at) = YEAR(CURDATE())"
            ) ?: 0;
            
            // Prenotazioni per stato
            $status_counts = $wpdb->get_results(
                "SELECT status, COUNT(*) as count 
                 FROM {$this->table_names['bookings']} 
                 GROUP BY status"
            );
            
            $stats['by_status'] = array();
            if ($status_counts) {
                foreach ($status_counts as $status) {
                    $stats['by_status'][$status->status] = $status->count;
                }
            }
            
        } catch (Exception $e) {
            $this->log_error('Errore calcolo statistiche: ' . $e->getMessage());
            
            // Ritorna statistiche di default
            $stats = array(
                'total' => 0,
                'schedine_sent' => 0,
                'this_month' => 0,
                'by_status' => array()
            );
        }
        
        return $stats;
    }
    
    /**
     * Valida i dati della prenotazione
     */
    private function validate_booking_data($data) {
        $errors = array();
        
        // Date obbligatorie
        if (empty($data['checkin_date'])) {
            $errors[] = 'Data check-in mancante';
        }
        
        if (empty($data['checkout_date'])) {
            $errors[] = 'Data check-out mancante';
        }
        
        // Verifica date valide
        if (!empty($data['checkin_date']) && !empty($data['checkout_date'])) {
            $checkin = strtotime($data['checkin_date']);
            $checkout = strtotime($data['checkout_date']);
            
            if ($checkin === false || $checkout === false) {
                $errors[] = 'Date non valide';
            } elseif ($checkout <= $checkin) {
                $errors[] = 'La data di check-out deve essere successiva al check-in';
            }
        }
        
        // Ospiti
        if (empty($data['total_guests']) || $data['total_guests'] < 1) {
            $errors[] = 'Numero ospiti non valido';
        }
        
        // Email valida se presente
        if (!empty($data['accommodation_email']) && !is_email($data['accommodation_email'])) {
            $errors[] = 'Email non valida';
        }
        
        return $errors;
    }
    
    /**
     * Sanitizza i dati della prenotazione
     */
    private function sanitize_booking_data($data) {
        $sanitized = array();
        
        // Campi testo
        $text_fields = array('booking_code', 'accommodation_name', 'accommodation_phone');
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Campi textarea
        if (isset($data['accommodation_address'])) {
            $sanitized['accommodation_address'] = sanitize_textarea_field($data['accommodation_address']);
        }
        
        if (isset($data['notes'])) {
            $sanitized['notes'] = sanitize_textarea_field($data['notes']);
        }
        
        // Email
        if (isset($data['accommodation_email'])) {
            $sanitized['accommodation_email'] = sanitize_email($data['accommodation_email']);
        }
        
        // Numeri
        $int_fields = array('nights', 'adults', 'children', 'total_guests', 'schedine_sent');
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = absint($data[$field]);
            }
        }
        
        // Date
        $date_fields = array('checkin_date', 'checkout_date', 'schedine_sent_date');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Status
        if (isset($data['status']) && in_array($data['status'], self::VALID_STATUSES)) {
            $sanitized['status'] = $data['status'];
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitizza i dati dell'ospite
     */
    private function sanitize_guest_data($data) {
        $sanitized = array();
        
        // Campi testo
        $text_fields = array(
            'guest_type', 'first_name', 'last_name', 'birth_place',
            'birth_province', 'birth_country', 'nationality',
            'document_type', 'document_number', 'document_place'
        );
        
        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Gender
        if (isset($data['gender']) && in_array($data['gender'], array('M', 'F'))) {
            $sanitized['gender'] = $data['gender'];
        }
        
        // Date
        $date_fields = array('birth_date', 'document_date');
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Status
        if (isset($data['status'])) {
            $sanitized['status'] = sanitize_text_field($data['status']);
        }
        
        // Boolean
        if (isset($data['sent_to_police'])) {
            $sanitized['sent_to_police'] = absint($data['sent_to_police']);
        }
        
        return $sanitized;
    }
    
    /**
     * Ottiene il formato per wpdb
     */
    private function get_booking_format($data) {
        $format = array();
        
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'id':
                case 'nights':
                case 'adults':
                case 'children':
                case 'total_guests':
                case 'schedine_sent':
                    $format[] = '%d';
                    break;
                default:
                    $format[] = '%s';
            }
        }
        
        return $format;
    }
    
    /**
     * Ottiene il formato per ospiti
     */
    private function get_guest_format($data) {
        $format = array();
        
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'id':
                case 'booking_id':
                case 'sent_to_police':
                    $format[] = '%d';
                    break;
                default:
                    $format[] = '%s';
            }
        }
        
        return $format;
    }
    
    /**
     * Log azioni prenotazione
     */
    private function log_booking_action($booking_id, $action, $data = array()) {
        global $wpdb;
        
        try {
            $wpdb->insert(
                $this->table_names['logs'],
                array(
                    'log_type' => 'booking_' . $action,
                    'message' => "Booking {$booking_id} {$action}",
                    'context' => json_encode($data),
                    'level' => 'info',
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ),
                array('%s', '%s', '%s', '%s', '%d', '%s')
            );
        } catch (Exception $e) {
            error_log('GABT: Errore logging: ' . $e->getMessage());
        }
    }
    
    /**
     * Log errori
     */
    private function log_error($message) {
        global $wpdb;
        
        try {
            $wpdb->insert(
                $this->table_names['logs'],
                array(
                    'log_type' => 'booking_error',
                    'message' => $message,
                    'level' => 'error',
                    'user_id' => get_current_user_id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );
        } catch (Exception $e) {
            // Fallback al log di WordPress
            error_log('[GABT Booking Repository] ' . $message);
        }
        
        if (WP_DEBUG) {
            error_log('[GABT Booking Repository] ' . $message);
        }
    }
}