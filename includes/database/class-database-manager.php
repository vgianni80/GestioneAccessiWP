<?php
/**
 * Gestore del database per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Database_Manager {
    
    /**
     * Nomi delle tabelle
     */
    private $bookings_table;
    private $guests_table;
    private $comuni_table;
    
    public function __construct() {
        global $wpdb;
        $this->bookings_table = $wpdb->prefix . 'cva_bookings';
        $this->guests_table = $wpdb->prefix . 'cva_guests';
        $this->comuni_table = $wpdb->prefix . 'cva_comuni';
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook per eventuali operazioni di inizializzazione
    }
    
    /**
     * Crea le tabelle del database
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella prenotazioni
        $bookings_sql = "CREATE TABLE {$this->bookings_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_code varchar(20) NOT NULL,
            checkin_date date NOT NULL,
            checkout_date date NOT NULL,
            nights int(3) NOT NULL,
            adults int(3) NOT NULL,
            children int(3) NOT NULL,
            total_guests int(3) NOT NULL,
            accommodation_name varchar(255),
            accommodation_address varchar(255),
            accommodation_phone varchar(50),
            accommodation_email varchar(100),
            notes text,
            status varchar(20) DEFAULT 'pending',
            schedine_sent tinyint(1) DEFAULT 0,
            schedine_sent_date datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_code (booking_code),
            KEY status (status),
            KEY checkin_date (checkin_date)
        ) $charset_collate;";
        
        // Tabella ospiti
        $guests_sql = "CREATE TABLE {$this->guests_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            guest_type varchar(20) NOT NULL,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            gender char(1) NOT NULL,
            birth_date date NOT NULL,
            birth_place varchar(100) NOT NULL,
            birth_province varchar(2),
            birth_country varchar(100) DEFAULT 'Italia',
            nationality varchar(100) DEFAULT 'Italia',
            document_type varchar(10) NOT NULL,
            document_number varchar(50) NOT NULL,
            document_place varchar(100) NOT NULL,
            document_date date,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY guest_type (guest_type),
            FOREIGN KEY (booking_id) REFERENCES {$this->bookings_table}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabella comuni
        $comuni_sql = "CREATE TABLE {$this->comuni_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            codice varchar(10) NOT NULL,
            nome varchar(100) NOT NULL,
            provincia varchar(2) NOT NULL,
            regione varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY codice (codice),
            KEY nome (nome),
            KEY provincia (provincia)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bookings_sql);
        dbDelta($guests_sql);
        dbDelta($comuni_sql);
        
        // Aggiorna la versione del database
        update_option('gabt_db_version', GABT_VERSION);
    }
    
    /**
     * Ottiene i nomi delle tabelle
     */
    public function get_table_names() {
        return array(
            'bookings' => $this->bookings_table,
            'guests' => $this->guests_table,
            'comuni' => $this->comuni_table
        );
    }
    
    /**
     * Verifica se le tabelle esistono
     */
    public function tables_exist() {
        global $wpdb;
        
        $tables = array($this->bookings_table, $this->guests_table, $this->comuni_table);
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($result !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Ottiene la versione del database
     */
    public function get_db_version() {
        return get_option('gabt_db_version', '0.0.0');
    }
    
    /**
     * Aggiorna il database se necessario
     */
    public function maybe_upgrade_db() {
        $current_version = $this->get_db_version();
        
        if (version_compare($current_version, GABT_VERSION, '<')) {
            $this->create_tables();
        }
    }
}
