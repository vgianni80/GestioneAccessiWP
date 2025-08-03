<?php
/**
 * Gestore di attivazione sicuro per il plugin
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Activation_Handler {
    
    /**
     * Attivazione sicura del plugin
     */
    public static function activate() {
        try {
            // Verifica che WordPress sia completamente caricato
            if (!function_exists('flush_rewrite_rules') || !function_exists('update_option')) {
                return false;
            }
            
            // Carica esplicitamente le classi necessarie
            self::load_required_classes();
            
            // Crea le tabelle del database
            self::create_database_tables();
            
            // Imposta le opzioni di default
            self::set_default_options();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Imposta versione del plugin
            update_option('gabt_plugin_version', GABT_VERSION);
            update_option('gabt_activation_time', current_time('timestamp'));
            
            return true;
            
        } catch (Exception $e) {
            // Log dell'errore
            if (function_exists('error_log')) {
                error_log('GABT Plugin Activation Error: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Disattivazione sicura del plugin
     */
    public static function deactivate() {
        try {
            // Verifica che WordPress sia completamente caricato
            if (!function_exists('wp_clear_scheduled_hook')) {
                return false;
            }
            
            // Rimuovi i cron job
            wp_clear_scheduled_hook('gabt_daily_schedine_send');
            wp_clear_scheduled_hook('gabt_weekly_log_cleanup');
            
            // Flush rewrite rules
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
            
            return true;
            
        } catch (Exception $e) {
            // Log dell'errore
            if (function_exists('error_log')) {
                error_log('GABT Plugin Deactivation Error: ' . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Carica le classi necessarie per l'attivazione
     */
    private static function load_required_classes() {
        $required_files = [
            GABT_PLUGIN_PATH . 'includes/database/class-database-manager.php',
            GABT_PLUGIN_PATH . 'includes/admin/class-settings-manager.php'
        ];
        
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
    
    /**
     * Crea le tabelle del database
     */
    private static function create_database_tables() {
        global $wpdb;
        
        // Tabella prenotazioni
        $bookings_table = $wpdb->prefix . 'gabt_bookings';
        $bookings_sql = "CREATE TABLE IF NOT EXISTS $bookings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_code varchar(20) NOT NULL UNIQUE,
            checkin_date date NOT NULL,
            checkout_date date NOT NULL,
            nights int(11) NOT NULL,
            adults int(11) NOT NULL DEFAULT 1,
            children int(11) NOT NULL DEFAULT 0,
            total_guests int(11) NOT NULL,
            accommodation_name varchar(255),
            accommodation_address text,
            accommodation_phone varchar(50),
            accommodation_email varchar(100),
            status varchar(50) NOT NULL DEFAULT 'pending',
            notes text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_code (booking_code),
            KEY checkin_date (checkin_date),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Tabella ospiti
        $guests_table = $wpdb->prefix . 'gabt_guests';
        $guests_sql = "CREATE TABLE IF NOT EXISTS $guests_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            gender enum('M','F') NOT NULL,
            birth_date date NOT NULL,
            birth_place varchar(100) NOT NULL,
            birth_province varchar(10),
            nationality varchar(50) DEFAULT 'ITALIANA',
            document_type varchar(50) NOT NULL,
            document_number varchar(50) NOT NULL,
            document_place varchar(100) NOT NULL,
            document_date date,
            guest_type varchar(50) NOT NULL DEFAULT 'componente_famiglia',
            status varchar(50) NOT NULL DEFAULT 'pending',
            sent_to_police tinyint(1) NOT NULL DEFAULT 0,
            sent_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY document_number (document_number),
            KEY status (status),
            FOREIGN KEY (booking_id) REFERENCES $bookings_table(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        // Esegui le query
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($bookings_sql);
        dbDelta($guests_sql);
    }
    
    /**
     * Imposta le opzioni di default
     */
    private static function set_default_options() {
        $default_options = [
            'gabt_alloggiati_username' => '',
            'gabt_alloggiati_password' => '',
            'gabt_alloggiati_ws_key' => '',
            'gabt_alloggiati_auto_send' => 0,
            'gabt_alloggiati_send_time' => '02:00',
            'gabt_accommodation_name' => get_bloginfo('name'),
            'gabt_accommodation_address' => '',
            'gabt_accommodation_phone' => '',
            'gabt_accommodation_email' => get_option('admin_email'),
            'gabt_accommodation_comune' => '',
            'gabt_accommodation_provincia' => '',
            'gabt_email_from_name' => get_bloginfo('name'),
            'gabt_email_from_email' => get_option('admin_email'),
            'gabt_email_guest_subject' => 'Registrazione completata',
            'gabt_email_guest_template' => self::get_default_email_template(),
            'gabt_email_admin_notifications' => 1,
            'gabt_email_admin_email' => get_option('admin_email'),
            'gabt_debug_mode' => 0,
            'gabt_log_retention_days' => 30,
            'gabt_require_document_date' => 0
        ];
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Template email di default
     */
    private static function get_default_email_template() {
        return "Gentile {guest_name},\n\n" .
               "La registrazione per la sua prenotazione (codice: {booking_code}) è stata completata con successo.\n\n" .
               "Dettagli soggiorno:\n" .
               "- Check-in: {checkin_date}\n" .
               "- Check-out: {checkout_date}\n" .
               "- Notti: {nights}\n" .
               "- Struttura: {accommodation_name}\n\n" .
               "I suoi dati sono stati trasmessi alle autorità competenti come previsto dalla normativa vigente.\n\n" .
               "Cordiali saluti,\n" .
               "{accommodation_name}";
    }
}