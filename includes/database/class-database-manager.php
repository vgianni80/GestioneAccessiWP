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
    private $logs_table;
    
    /**
     * Versione corrente del database
     */
    const DB_VERSION = '1.3.1';
    
    public function __construct() {
        global $wpdb;
        // Fix: uso consistente del prefisso gabt_
        $this->bookings_table = $wpdb->prefix . 'gabt_bookings';
        $this->guests_table = $wpdb->prefix . 'gabt_guests';
        $this->comuni_table = $wpdb->prefix . 'gabt_comuni';
        $this->logs_table = $wpdb->prefix . 'gabt_logs';
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Verifica se è necessario aggiornare il database
        $this->maybe_upgrade_db();
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
            adults int(3) NOT NULL DEFAULT 1,
            children int(3) NOT NULL DEFAULT 0,
            total_guests int(3) NOT NULL,
            accommodation_name varchar(255),
            accommodation_address text,
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
            KEY checkin_date (checkin_date),
            KEY schedine_sent (schedine_sent)
        ) $charset_collate;";
        
        // Tabella ospiti
        $guests_sql = "CREATE TABLE {$this->guests_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            guest_type varchar(20) NOT NULL DEFAULT 'componente_famiglia',
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            gender enum('M','F') NOT NULL,
            birth_date date NOT NULL,
            birth_place varchar(100) NOT NULL,
            birth_province varchar(2),
            birth_country varchar(100) DEFAULT 'Italia',
            nationality varchar(100) DEFAULT 'Italia',
            document_type varchar(10) NOT NULL,
            document_number varchar(50) NOT NULL,
            document_place varchar(100) NOT NULL,
            document_date date,
            status varchar(20) DEFAULT 'pending',
            sent_to_police tinyint(1) DEFAULT 0,
            sent_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY guest_type (guest_type),
            KEY document_number (document_number),
            KEY status (status),
            CONSTRAINT fk_booking_id FOREIGN KEY (booking_id) REFERENCES {$this->bookings_table}(id) ON DELETE CASCADE
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
        
        // Tabella logs
        $logs_sql = "CREATE TABLE {$this->logs_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            level varchar(20) NOT NULL DEFAULT 'info',
            user_id bigint(20),
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_type (log_type),
            KEY level (level),
            KEY created_at (created_at),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Esegui le query con gestione errori
        $results = array();
        $results['bookings'] = dbDelta($bookings_sql);
        $results['guests'] = dbDelta($guests_sql);
        $results['comuni'] = dbDelta($comuni_sql);
        $results['logs'] = dbDelta($logs_sql);
        
        // Verifica errori
        if ($wpdb->last_error) {
            $this->log_error('Errore creazione tabelle: ' . $wpdb->last_error);
            return false;
        }
        
        // Aggiorna la versione del database
        update_option('gabt_db_version', self::DB_VERSION);
        
        return true;
    }
    
    /**
     * Ottiene i nomi delle tabelle
     */
    public function get_table_names() {
        return array(
            'bookings' => $this->bookings_table,
            'guests' => $this->guests_table,
            'comuni' => $this->comuni_table,
            'logs' => $this->logs_table
        );
    }
    
    /**
     * Verifica se le tabelle esistono
     */
    public function tables_exist() {
        global $wpdb;
        
        $tables = array(
            $this->bookings_table,
            $this->guests_table,
            $this->comuni_table,
            $this->logs_table
        );
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
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
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->upgrade_database($current_version);
        }
    }
    
    /**
     * Esegue l'upgrade del database
     */
    private function upgrade_database($from_version) {
        global $wpdb;
        
        // Backup delle tabelle esistenti se necessario
        if ($this->should_backup_before_upgrade($from_version)) {
            $this->backup_tables();
        }
        
        // Esegui migrations basate sulla versione
        if (version_compare($from_version, '1.3.0', '<')) {
            $this->migrate_to_1_3_0();
        }
        
        if (version_compare($from_version, '1.3.1', '<')) {
            $this->migrate_to_1_3_1();
        }
        
        // Ricrea le tabelle con la struttura aggiornata
        $this->create_tables();
    }
    
    /**
     * Migration to version 1.3.0
     */
    private function migrate_to_1_3_0() {
        global $wpdb;
        
        // Rinomina tabelle vecchie se esistono con prefisso diverso
        $old_tables = array(
            'cva_bookings' => $this->bookings_table,
            'cva_guests' => $this->guests_table,
            'cva_comuni' => $this->comuni_table
        );
        
        foreach ($old_tables as $old_name => $new_name) {
            $old_table = $wpdb->prefix . $old_name;
            if ($this->table_exists($old_table) && !$this->table_exists($new_name)) {
                $wpdb->query("RENAME TABLE `{$old_table}` TO `{$new_name}`");
                $this->log_info("Tabella rinominata da {$old_table} a {$new_name}");
            }
        }
    }
    
    /**
     * Migration to version 1.3.1
     */
    private function migrate_to_1_3_1() {
        global $wpdb;
        
        // Aggiungi colonna status agli ospiti se non esiste
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM `{$this->guests_table}` LIKE 'status'");
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE `{$this->guests_table}` ADD COLUMN `status` varchar(20) DEFAULT 'pending' AFTER `document_date`");
        }
        
        // Aggiungi indici mancanti
        $this->add_missing_indexes();
    }
    
    /**
     * Aggiunge indici mancanti
     */
    private function add_missing_indexes() {
        global $wpdb;
        
        // Indice su schedine_sent se non esiste
        $index_exists = $wpdb->get_var(
            "SHOW INDEX FROM `{$this->bookings_table}` WHERE Key_name = 'schedine_sent'"
        );
        
        if (!$index_exists) {
            $wpdb->query("ALTER TABLE `{$this->bookings_table}` ADD INDEX `schedine_sent` (`schedine_sent`)");
        }
    }
    
    /**
     * Verifica se una tabella esiste
     */
    private function table_exists($table_name) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $result === $table_name;
    }
    
    /**
     * Determina se fare backup prima dell'upgrade
     */
    private function should_backup_before_upgrade($from_version) {
        // Backup per major version changes
        $from_major = explode('.', $from_version)[0];
        $to_major = explode('.', self::DB_VERSION)[0];
        
        return $from_major !== $to_major;
    }
    
    /**
     * Backup delle tabelle
     */
    private function backup_tables() {
        global $wpdb;
        
        $timestamp = date('YmdHis');
        $tables = $this->get_table_names();
        
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $backup_name = $table . '_backup_' . $timestamp;
                $wpdb->query("CREATE TABLE `{$backup_name}` LIKE `{$table}`");
                $wpdb->query("INSERT INTO `{$backup_name}` SELECT * FROM `{$table}`");
                $this->log_info("Backup creato per tabella {$table}");
            }
        }
    }
    
    /**
     * Ottimizza le tabelle
     */
    public function optimize_tables() {
        global $wpdb;
        
        $tables = $this->get_table_names();
        
        foreach ($tables as $table) {
            if ($this->table_exists($table)) {
                $wpdb->query("OPTIMIZE TABLE `{$table}`");
            }
        }
        
        $this->log_info('Tabelle ottimizzate');
    }
    
    /**
     * Pulisce i dati vecchi
     */
    public function cleanup_old_data($days = 365) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Pulisci prenotazioni vecchie completate
        $deleted = $wpdb->delete(
            $this->bookings_table,
            array(
                'status' => 'completed',
                'checkout_date' => array('<=', $cutoff_date)
            ),
            array('%s', '%s')
        );
        
        if ($deleted) {
            $this->log_info("Eliminate {$deleted} prenotazioni vecchie");
        }
        
        // Pulisci log vecchi
        $this->cleanup_logs(30);
    }
    
    /**
     * Pulisce i log vecchi
     */
    public function cleanup_logs($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$this->logs_table}` WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted) {
            $this->log_info("Eliminati {$deleted} log vecchi");
        }
    }
    
    /**
     * Log info
     */
    private function log_info($message) {
        $this->log($message, 'info');
    }
    
    /**
     * Log error
     */
    private function log_error($message) {
        $this->log($message, 'error');
        error_log('[GABT Database Manager] ERROR: ' . $message);
    }
    
    /**
     * Log generico
     */
    private function log($message, $level = 'info') {
        global $wpdb;
        
        $wpdb->insert(
            $this->logs_table,
            array(
                'log_type' => 'database',
                'message' => $message,
                'level' => $level,
                'user_id' => get_current_user_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Ottiene statistiche del database
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Dimensione tabelle
        $tables = $this->get_table_names();
        foreach ($tables as $key => $table) {
            if ($this->table_exists($table)) {
                $size = $wpdb->get_row($wpdb->prepare(
                    "SELECT 
                        COUNT(*) as row_count,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = %s 
                    AND table_name = %s",
                    DB_NAME,
                    $table
                ));
                
                $stats[$key] = array(
                    'rows' => $size->row_count ?? 0,
                    'size_mb' => $size->size_mb ?? 0
                );
            }
        }
        
        return $stats;
    }
}
