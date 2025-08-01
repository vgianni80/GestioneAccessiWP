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
    
    private $db_manager;
    private $table_names;
    
    public function __construct() {
        $this->db_manager = new GABT_Database_Manager();
        $this->table_names = $this->db_manager->get_table_names();
    }
    
    /**
     * Crea una nuova prenotazione
     */
    public function create_booking($booking_data) {
        global $wpdb;
        
        $defaults = array(
            'booking_code' => $this->generate_booking_code(),
            'status' => 'pending',
            'schedine_sent' => 0,
            'created_at' => current_time('mysql')
        );
        
        $booking_data = wp_parse_args($booking_data, $defaults);
        
        $result = $wpdb->insert(
            $this->table_names['bookings'],
            $booking_data,
            array(
                '%s', '%s', '%s', '%d', '%d', '%d', '%d', 
                '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Errore nella creazione della prenotazione: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Ottiene una prenotazione per ID
     */
    public function get_booking($booking_id) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['bookings']} WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            return null;
        }
        
        // Carica anche gli ospiti
        $booking->guests = $this->get_booking_guests($booking_id);
        
        return $booking;
    }
    
    /**
     * Ottiene una prenotazione per codice
     */
    public function get_booking_by_code($booking_code) {
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_names['bookings']} WHERE booking_code = %s",
            $booking_code
        ));
        
        if (!$booking) {
            return null;
        }
        
        $booking->guests = $this->get_booking_guests($booking->id);
        
        return $booking;
    }
    
    /**
     * Ottiene tutte le prenotazioni con filtri
     */
    public function get_bookings($filters = array()) {
        global $wpdb;
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "checkin_date >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "checkin_date <= %s";
            $where_values[] = $filters['date_to'];
        }
        
        if (!empty($filters['schedine_sent'])) {
            $where_clauses[] = "schedine_sent = %d";
            $where_values[] = $filters['schedine_sent'];
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $order_by = isset($filters['order_by']) ? $filters['order_by'] : 'created_at';
        $order = isset($filters['order']) ? $filters['order'] : 'DESC';
        
        $limit_sql = '';
        if (isset($filters['limit'])) {
            $limit_sql = $wpdb->prepare("LIMIT %d", $filters['limit']);
            if (isset($filters['offset'])) {
                $limit_sql = $wpdb->prepare("LIMIT %d, %d", $filters['offset'], $filters['limit']);
            }
        }
        
        $sql = "SELECT * FROM {$this->table_names['bookings']} {$where_sql} ORDER BY {$order_by} {$order} {$limit_sql}";
        
        if (!empty($where_values)) {
            $bookings = $wpdb->get_results($wpdb->prepare($sql, $where_values));
        } else {
            $bookings = $wpdb->get_results($sql);
        }
        
        return $bookings;
    }
    
    /**
     * Aggiorna una prenotazione
     */
    public function update_booking($booking_id, $booking_data) {
        global $wpdb;
        
        $booking_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_names['bookings'],
            $booking_data,
            array('id' => $booking_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Elimina una prenotazione
     */
    public function delete_booking($booking_id) {
        global $wpdb;
        
        // Prima elimina gli ospiti (CASCADE dovrebbe farlo automaticamente)
        $wpdb->delete($this->table_names['guests'], array('booking_id' => $booking_id), array('%d'));
        
        // Poi elimina la prenotazione
        $result = $wpdb->delete($this->table_names['bookings'], array('id' => $booking_id), array('%d'));
        
        return $result !== false;
    }
    
    /**
     * Aggiunge un ospite a una prenotazione
     */
    public function add_guest($booking_id, $guest_data) {
        global $wpdb;
        
        $guest_data['booking_id'] = $booking_id;
        $guest_data['created_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $this->table_names['guests'],
            $guest_data,
            array(
                '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 
                '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
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
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_names['guests']} WHERE booking_id = %d ORDER BY guest_type, id",
            $booking_id
        ));
    }
    
    /**
     * Aggiorna un ospite
     */
    public function update_guest($guest_id, $guest_data) {
        global $wpdb;
        
        $guest_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_names['guests'],
            $guest_data,
            array('id' => $guest_id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Elimina un ospite
     */
    public function delete_guest($guest_id) {
        global $wpdb;
        
        $result = $wpdb->delete($this->table_names['guests'], array('id' => $guest_id), array('%d'));
        
        return $result !== false;
    }
    
    /**
     * Marca le schedine come inviate
     */
    public function mark_schedine_sent($booking_id) {
        return $this->update_booking($booking_id, array(
            'schedine_sent' => 1,
            'schedine_sent_date' => current_time('mysql'),
            'status' => 'completed'
        ));
    }
    
    /**
     * Ottiene le prenotazioni pronte per l'invio delle schedine
     */
    public function get_bookings_ready_for_schedine() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT b.*, COUNT(g.id) as guest_count 
             FROM {$this->table_names['bookings']} b 
             LEFT JOIN {$this->table_names['guests']} g ON b.id = g.booking_id 
             WHERE b.schedine_sent = 0 
             AND b.status = 'confirmed' 
             AND b.checkin_date <= CURDATE() 
             GROUP BY b.id 
             HAVING guest_count > 0"
        );
    }
    
    /**
     * Genera un codice prenotazione univoco
     */
    private function generate_booking_code() {
        do {
            $code = 'BT' . date('Y') . strtoupper(wp_generate_password(6, false));
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
        
        // Prenotazioni totali
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_names['bookings']}");
        
        // Prenotazioni per stato
        $status_counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$this->table_names['bookings']} GROUP BY status"
        );
        
        foreach ($status_counts as $status) {
            $stats['by_status'][$status->status] = $status->count;
        }
        
        // Schedine inviate
        $stats['schedine_sent'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['bookings']} WHERE schedine_sent = 1"
        );
        
        // Prenotazioni questo mese
        $stats['this_month'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_names['bookings']} 
             WHERE MONTH(created_at) = MONTH(CURDATE()) 
             AND YEAR(created_at) = YEAR(CURDATE())"
        );
        
        return $stats;
    }
}
