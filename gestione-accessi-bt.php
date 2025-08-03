<?php
/**
 * Plugin Name: Gestione Accessi BluTrasimeno
 * Description: Plugin per gestione prenotazioni e comunicazioni automatiche al servizio Alloggiati Web della Polizia di Stato
 * Version: 1.3.0
 * Author: Gianni Valeri
 * Text Domain: gestione-accessi-bt
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('GABT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GABT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GABT_VERSION', '1.3.0');

/**
 * Autoloader semplice per le classi del plugin
 */
spl_autoload_register(function ($class_name) {
    // Controlla se la classe appartiene al nostro plugin
    if (strpos($class_name, 'GABT_') !== 0) {
        return;
    }
    
    // Converti il nome della classe in percorso file
    $class_file = str_replace('GABT_', '', $class_name);
    $class_file = str_replace('_', '-', strtolower($class_file));
    $class_file = 'class-' . $class_file . '.php';
    
    // Directory di ricerca
    $directories = [
        GABT_PLUGIN_PATH . 'includes/',
        GABT_PLUGIN_PATH . 'includes/admin/',
        GABT_PLUGIN_PATH . 'includes/frontend/',
        GABT_PLUGIN_PATH . 'includes/database/',
        GABT_PLUGIN_PATH . 'includes/services/',
        GABT_PLUGIN_PATH . 'includes/ajax/',
        GABT_PLUGIN_PATH . 'includes/cron/',
    ];
    
    foreach ($directories as $directory) {
        $file_path = $directory . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
});

/**
 * Funzione di attivazione sicura
 */
function gabt_activate_plugin() {
    try {
        // Verifica requisiti minimi
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die('Questo plugin richiede PHP 7.4 o superiore. Versione attuale: ' . PHP_VERSION);
        }
        
        // Verifica estensioni PHP necessarie
        $required_extensions = ['mysqli', 'curl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                wp_die("Estensione PHP mancante: {$ext}");
            }
        }
        
        // Crea le tabelle database
        gabt_create_database_tables();
        
        // Imposta opzioni di default
        gabt_set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Imposta versione del plugin
        update_option('gabt_plugin_version', GABT_VERSION);
        update_option('gabt_activation_time', current_time('timestamp'));
        
        return true;
        
    } catch (Exception $e) {
        error_log('GABT Activation Error: ' . $e->getMessage());
        wp_die('Errore durante l\'attivazione del plugin: ' . $e->getMessage());
    }
}

/**
 * Crea le tabelle del database
 */
function gabt_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabella prenotazioni
    $table_bookings = $wpdb->prefix . 'gabt_bookings';
    $sql_bookings = "CREATE TABLE $table_bookings (
        id int(11) NOT NULL AUTO_INCREMENT,
        booking_code varchar(50) NOT NULL,
        checkin_date date NOT NULL,
        checkout_date date NOT NULL,
        nights int(11) NOT NULL DEFAULT 1,
        adults int(11) NOT NULL DEFAULT 1,
        children int(11) NOT NULL DEFAULT 0,
        total_guests int(11) NOT NULL DEFAULT 1,
        accommodation_name varchar(255),
        accommodation_address text,
        accommodation_phone varchar(50),
        accommodation_email varchar(255),
        notes text,
        status varchar(20) NOT NULL DEFAULT 'pending',
        schedine_sent tinyint(1) NOT NULL DEFAULT 0,
        schedine_sent_date datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY booking_code (booking_code),
        KEY checkin_date (checkin_date),
        KEY status (status),
        KEY schedine_sent (schedine_sent)
    ) $charset_collate;";
    
    // Tabella ospiti
    $table_guests = $wpdb->prefix . 'gabt_guests';
    $sql_guests = "CREATE TABLE $table_guests (
        id int(11) NOT NULL AUTO_INCREMENT,
        booking_id int(11) NOT NULL,
        guest_type varchar(20) NOT NULL DEFAULT 'componente_famiglia',
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        gender enum('M','F') NOT NULL,
        birth_date date NOT NULL,
        birth_place varchar(255) NOT NULL,
        birth_province varchar(10),
        birth_country varchar(100) DEFAULT 'Italia',
        nationality varchar(100) DEFAULT 'Italia',
        document_type varchar(20) NOT NULL,
        document_number varchar(50) NOT NULL,
        document_place varchar(255) NOT NULL,
        document_date date,
        status varchar(20) NOT NULL DEFAULT 'pending',
        sent_to_police tinyint(1) NOT NULL DEFAULT 0,
        sent_at datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY guest_type (guest_type),
        KEY document_number (document_number),
        KEY status (status),
        CONSTRAINT fk_gabt_booking_id FOREIGN KEY (booking_id) REFERENCES $table_bookings(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Tabella log
    $table_logs = $wpdb->prefix . 'gabt_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id int(11) NOT NULL AUTO_INCREMENT,
        log_type varchar(50) NOT NULL,
        message text NOT NULL,
        context longtext,
        level varchar(20) NOT NULL DEFAULT 'info',
        user_id bigint(20),
        ip_address varchar(45),
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY log_type (log_type),
        KEY level (level),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // Esegui le query di creazione tabelle
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $results = [];
    $results['bookings'] = dbDelta($sql_bookings);
    $results['guests'] = dbDelta($sql_guests);
    $results['logs'] = dbDelta($sql_logs);
    
    // Verifica errori
    if ($wpdb->last_error) {
        throw new Exception('Errore creazione tabelle database: ' . $wpdb->last_error);
    }
    
    // Log dell'attivazione
    error_log('GABT: Tabelle database create durante attivazione');
    
    return $results;
}

/**
 * Imposta le opzioni di default
 */
function gabt_set_default_options() {
    $default_options = [
        'gabt_alloggiati_username' => '',
        'gabt_alloggiati_password' => '',
        'gabt_alloggiati_ws_key' => '',
        'gabt_alloggiati_auto_send' => 0,
        'gabt_alloggiati_send_time' => '02:00',
        'gabt_accommodation_name' => get_bloginfo('name'),
        'gabt_accommodation_email' => get_option('admin_email'),
        'gabt_email_from_name' => get_bloginfo('name'),
        'gabt_email_from_email' => get_option('admin_email'),
        'gabt_debug_mode' => 0,
        'gabt_log_retention_days' => 30
    ];
    
    foreach ($default_options as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }
}

/**
 * Funzione di disattivazione
 */
function gabt_deactivate_plugin() {
    try {
        // Pulisci i cron job
        wp_clear_scheduled_hook('gabt_daily_schedine_send');
        wp_clear_scheduled_hook('gabt_weekly_log_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        return true;
    } catch (Exception $e) {
        error_log('GABT Deactivation Error: ' . $e->getMessage());
        return false;
    }
}

// Hook di attivazione e disattivazione
register_activation_hook(__FILE__, 'gabt_activate_plugin');
register_deactivation_hook(__FILE__, 'gabt_deactivate_plugin');

/**
 * Inizializza il plugin quando WordPress è pronto
 */
function gabt_init_plugin() {
    try {
        // Carica la classe principale
        if (!class_exists('GABT_Plugin_Core')) {
            require_once GABT_PLUGIN_PATH . 'includes/class-plugin-core.php';
        }
        
        // Inizializza il plugin
        if (class_exists('GABT_Plugin_Core')) {
            return GABT_Plugin_Core::get_instance();
        }
        
        throw new Exception('Classe GABT_Plugin_Core non trovata');
        
    } catch (Exception $e) {
        error_log('GABT Init Error: ' . $e->getMessage());
        
        // Mostra errore solo agli amministratori
        if (current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p><strong>Gestione Accessi BT:</strong> Errore di inizializzazione - ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
        
        return false;
    }
}

// Inizializza dopo che WordPress è completamente caricato
add_action('plugins_loaded', 'gabt_init_plugin');

/**
 * Aggiungi menu admin di base (fallback)
 */
add_action('admin_menu', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    add_menu_page(
        'Gestione Accessi BluTrasimeno',
        'manage_options',
        'gestione-accessi-bt',
        'gabt_admin_page_fallback',
        'dashicons-admin-users',
        30
    );
});

/**
 * Pagina admin di fallback
 */
function gabt_admin_page_fallback() {
    echo '<div class="wrap">';
    echo '<h1>Gestione Accessi BluTrasimeno</h1>';
    
    if (class_exists('GABT_Plugin_Core')) {
        echo '<div class="notice notice-success"><p>✅ Plugin caricato correttamente!</p></div>';
        echo '<p>Se vedi questo messaggio, il plugin è attivo ma sta utilizzando la modalità di fallback.</p>';
    } else {
        echo '<div class="notice notice-error"><p>❌ Errore nel caricamento del plugin.</p></div>';
        echo '<p>Controlla i log di WordPress per maggiori dettagli.</p>';
    }
    
    echo '<h2>Informazioni di Debug</h2>';
    echo '<ul>';
    echo '<li><strong>Versione:</strong> ' . GABT_VERSION . '</li>';
    echo '<li><strong>Path Plugin:</strong> ' . GABT_PLUGIN_PATH . '</li>';
    echo '<li><strong>URL Plugin:</strong> ' . GABT_PLUGIN_URL . '</li>';
    echo '<li><strong>WordPress Debug:</strong> ' . (WP_DEBUG ? 'Abilitato' : 'Disabilitato') . '</li>';
    echo '<li><strong>PHP Version:</strong> ' . PHP_VERSION . '</li>';
    echo '</ul>';
    
    // Test connessione database
    global $wpdb;
    $test_table = $wpdb->prefix . 'gabt_bookings';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$test_table'") === $test_table;
    
    echo '<h3>Test Database</h3>';
    echo '<p>Tabella prenotazioni: ' . ($table_exists ? '✅ Esistente' : '❌ Non trovata') . '</p>';
    
    echo '</div>';
}

/**
 * Messaggio di attivazione
 */
add_action('admin_notices', function() {
    if (get_transient('gabt_activation_notice')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Gestione Accessi BluTrasimeno</strong> attivato con successo! Versione: ' . GABT_VERSION . '</p>';
        echo '</div>';
        delete_transient('gabt_activation_notice');
    }
});

// Imposta il messaggio di attivazione
if (get_option('gabt_plugin_version') === GABT_VERSION && !get_transient('gabt_activation_notice')) {
    set_transient('gabt_activation_notice', true, 30);
}