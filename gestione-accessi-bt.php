<?php
/**
 * Plugin Name: Gestione Accessi BluTrasimeno
 * Description: Plugin per gestione prenotazioni e comunicazioni automatiche al servizio Alloggiati Web della Polizia di Stato
 * Version: 1.3.0
 * Author: Gianni Valeri
 * Text Domain: gestione-accessi-bt
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('GABT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GABT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GABT_VERSION', '1.3.0');

// Funzione di attivazione semplice e sicura
function gabt_activate_plugin() {
    try {
        // Crea solo le opzioni di base
        add_option('gabt_plugin_version', GABT_VERSION);
        add_option('gabt_activation_time', current_time('timestamp'));
        
        // Imposta opzioni di default essenziali
        add_option('gabt_alloggiati_username', '');
        add_option('gabt_alloggiati_password', '');
        add_option('gabt_alloggiati_ws_key', '');
        add_option('gabt_accommodation_name', get_bloginfo('name'));
        add_option('gabt_accommodation_email', get_option('admin_email'));
        
        // Crea le tabelle database
        gabt_create_database_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        return true;
    } catch (Exception $e) {
        error_log('GABT Activation Error: ' . $e->getMessage());
        return false;
    }
}

// Funzione per creare le tabelle database
function gabt_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabella prenotazioni
    $table_bookings = $wpdb->prefix . 'gabt_bookings';
    $sql_bookings = "CREATE TABLE $table_bookings (
        id int(11) NOT NULL AUTO_INCREMENT,
        booking_code varchar(50) NOT NULL,
        guest_name varchar(255) NOT NULL,
        guest_email varchar(255),
        guest_phone varchar(50),
        check_in_date date NOT NULL,
        check_out_date date NOT NULL,
        total_guests int(11) NOT NULL DEFAULT 1,
        total_nights int(11) NOT NULL DEFAULT 1,
        room_number varchar(50),
        notes text,
        status varchar(20) NOT NULL DEFAULT 'active',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY booking_code (booking_code),
        KEY check_in_date (check_in_date),
        KEY status (status)
    ) $charset_collate;";
    
    // Tabella ospiti
    $table_guests = $wpdb->prefix . 'gabt_guests';
    $sql_guests = "CREATE TABLE $table_guests (
        id int(11) NOT NULL AUTO_INCREMENT,
        booking_id int(11) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        gender enum('M','F') NOT NULL,
        birth_date date NOT NULL,
        birth_place varchar(255) NOT NULL,
        birth_province varchar(10),
        nationality varchar(10) NOT NULL DEFAULT 'IT',
        document_type varchar(20) NOT NULL,
        document_number varchar(50) NOT NULL,
        document_place varchar(255) NOT NULL,
        document_date date,
        guest_type varchar(20) NOT NULL DEFAULT 'ospite',
        arrival_date date,
        departure_date date,
        schedina_sent tinyint(1) NOT NULL DEFAULT 0,
        schedina_sent_date datetime NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY schedina_sent (schedina_sent),
        KEY arrival_date (arrival_date),
        FOREIGN KEY (booking_id) REFERENCES $table_bookings(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Tabella log
    $table_logs = $wpdb->prefix . 'gabt_logs';
    $sql_logs = "CREATE TABLE $table_logs (
        id int(11) NOT NULL AUTO_INCREMENT,
        log_type varchar(50) NOT NULL,
        message text NOT NULL,
        context longtext,
        level varchar(20) NOT NULL DEFAULT 'info',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY log_type (log_type),
        KEY level (level),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Esegui le query di creazione tabelle
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql_bookings);
    dbDelta($sql_guests);
    dbDelta($sql_logs);
    
    // Log dell'attivazione
    error_log('GABT: Tabelle database create durante attivazione');
}

// Funzione di disattivazione semplice
function gabt_deactivate_plugin() {
    try {
        // Pulisci solo i cron job
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

// Autoloader semplice per le classi del plugin
// Non caricare classi durante l'attivazione per evitare output inatteso
spl_autoload_register(function ($class_name) {
    // Non caricare nulla durante l'attivazione
    if (defined('WP_ADMIN') && isset($_GET['action']) && $_GET['action'] === 'activate') {
        return;
    }
    
    // Controlla se la classe appartiene al nostro plugin
    if (!is_string($class_name) || strpos($class_name, 'GABT_') !== 0) {
        return;
    }
    
    // Converti il nome della classe in percorso file
    $class_file = str_replace('GABT_', '', $class_name);
    $class_file = str_replace('_', '-', strtolower($class_file));
    $class_file = 'class-' . $class_file . '.php';
    
    // Definisci le directory dove cercare le classi
    $directories = [
        GABT_PLUGIN_PATH . 'includes/',
        GABT_PLUGIN_PATH . 'includes/admin/',
        GABT_PLUGIN_PATH . 'includes/frontend/',
        GABT_PLUGIN_PATH . 'includes/database/',
        GABT_PLUGIN_PATH . 'includes/services/',
        GABT_PLUGIN_PATH . 'includes/ajax/',
        GABT_PLUGIN_PATH . 'includes/cron/',
    ];
    
    // Cerca il file nelle directory
    foreach ($directories as $directory) {
        $file_path = $directory . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
});

// Inizializza il plugin solo dopo che WordPress è completamente caricato
// e SOLO se non siamo in fase di attivazione
add_action('plugins_loaded', function() {
    // Non caricare nulla durante l'attivazione per evitare output inatteso
    if (defined('WP_ADMIN') && isset($_GET['action']) && $_GET['action'] === 'activate') {
        return;
    }
    
    // Carica la classe principale solo se necessario
    if (file_exists(GABT_PLUGIN_PATH . 'includes/class-plugin-core.php')) {
        require_once GABT_PLUGIN_PATH . 'includes/class-plugin-core.php';
        
        // Inizializza il plugin
        if (class_exists('GABT_Plugin_Core')) {
            GABT_Plugin_Core::get_instance();
        }
    }
});

// Aggiungi menu admin di base
// Non caricare durante l'attivazione per evitare output inatteso
add_action('admin_menu', function() {
    // Non caricare nulla durante l'attivazione
    if (defined('WP_ADMIN') && isset($_GET['action']) && $_GET['action'] === 'activate') {
        return;
    }
    
    if (current_user_can('manage_options')) {
        add_menu_page(
            'Gestione Accessi BT',
            'Gestione Accessi BT',
            'manage_options',
            'gestione-accessi-bt',
            function() {
                echo '<div class="wrap">';
                echo '<h1>Gestione Accessi BluTrasimeno</h1>';
                echo '<p>Plugin attivato con successo! Versione: ' . GABT_VERSION . '</p>';
                
                if (class_exists('GABT_Plugin_Core')) {
                    echo '<p>✅ Sistema completamente caricato</p>';
                } else {
                    echo '<p>⚠️ Sistema in modalità base - alcune funzionalità potrebbero non essere disponibili</p>';
                }
                
                echo '<h2>Configurazione</h2>';
                echo '<p>Vai alle <a href="admin.php?page=gestione-accessi-bt-settings">Impostazioni</a> per configurare il plugin.</p>';
                echo '</div>';
            },
            'dashicons-admin-users',
            30
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'gestione-accessi-bt-settings',
            function() {
                echo '<div class="wrap">';
                echo '<h1>Impostazioni Gestione Accessi BT</h1>';
                echo '<p>Configurazione del plugin in sviluppo...</p>';
                echo '</div>';
            }
        );
    }
});

// Messaggio di attivazione
add_action('admin_notices', function() {
    if (get_transient('gabt_activation_notice')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Gestione Accessi BluTrasimeno</strong> attivato con successo!</p>';
        echo '</div>';
        delete_transient('gabt_activation_notice');
    }
});

// Imposta il messaggio di attivazione se il plugin è stato appena attivato
if (get_option('gabt_plugin_version') === GABT_VERSION && !get_transient('gabt_activation_notice')) {
    set_transient('gabt_activation_notice', true, 30);
}
