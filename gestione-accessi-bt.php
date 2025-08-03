<?php
/**
 * Plugin Name: Gestione Accessi BluTrasimeno
 * Description: Plugin moderno per gestione prenotazioni e comunicazioni automatiche al servizio Alloggiati Web della Polizia di Stato
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
 * Autoloader moderno e sicuro per PHP 8+
 */
spl_autoload_register(function ($class_name) {
    if (!is_string($class_name) || empty($class_name) || strpos($class_name, 'GABT_') !== 0) {
        return false;
    }
    
    $class_file = str_replace('GABT_', '', $class_name);
    if (!is_string($class_file) || empty($class_file)) {
        return false;
    }
    
    $class_file = str_replace('_', '-', strtolower(trim($class_file)));
    if (empty($class_file)) {
        return false;
    }
    
    $class_file = 'class-' . $class_file . '.php';
    
    if (!defined('GABT_PLUGIN_PATH') || !is_string(GABT_PLUGIN_PATH)) {
        return false;
    }
    
    $base_path = rtrim(GABT_PLUGIN_PATH, '/') . '/';
    $directories = [
        $base_path . 'includes/',
        $base_path . 'includes/admin/',
        $base_path . 'includes/frontend/',
        $base_path . 'includes/database/',
        $base_path . 'includes/services/',
        $base_path . 'includes/rest-api/',
        $base_path . 'includes/cron/',
    ];
    
    foreach ($directories as $directory) {
        if (!is_string($directory) || empty($directory)) {
            continue;
        }
        
        $file_path = $directory . $class_file;
        
        if (file_exists($file_path) && is_readable($file_path)) {
            try {
                require_once $file_path;
                if (class_exists($class_name, false)) {
                    return true;
                }
            } catch (Exception $e) {
                error_log("GABT Autoloader: Errore caricamento {$file_path}: " . $e->getMessage());
            }
        }
    }
    
    return false;
});

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
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    try {
        $results = [];
        $results['bookings'] = dbDelta($sql_bookings);
        $results['guests'] = dbDelta($sql_guests);
        $results['logs'] = dbDelta($sql_logs);
        
        if ($wpdb->last_error) {
            throw new Exception('Errore creazione tabelle database: ' . $wpdb->last_error);
        }
        
        error_log('GABT: Tabelle database create con successo');
        return $results;
        
    } catch (Exception $e) {
        error_log('GABT: Errore creazione tabelle: ' . $e->getMessage());
        return false;
    }
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
 * Attivazione plugin
 */
function gabt_activate_plugin() {
    try {
        error_log('GABT: 🚀 Avvio attivazione plugin moderno');
        
        // Verifica PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die('Plugin richiede PHP 7.4+. Versione corrente: ' . PHP_VERSION);
        }
        
        // Verifica estensioni
        $required_extensions = ['mysqli', 'curl', 'json'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                wp_die("Estensione PHP mancante: {$ext}");
            }
        }
        
        // Crea tabelle
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $db_result = gabt_create_database_tables();
        if ($db_result === false) {
            throw new Exception('Errore creazione tabelle database');
        }
        
        // Opzioni default
        gabt_set_default_options();
        
        // Flush rewrite rules per REST API
        flush_rewrite_rules();
        
        // Versione e timestamp
        update_option('gabt_plugin_version', GABT_VERSION);
        update_option('gabt_activation_time', current_time('timestamp'));
        
        // Messaggio di successo
        set_transient('gabt_activation_notice', true, 30);
        
        error_log('GABT: ✅ Plugin attivato con successo - versione ' . GABT_VERSION);
        
        return true;
        
    } catch (Exception $e) {
        error_log('GABT: ❌ Errore attivazione: ' . $e->getMessage());
        wp_die('Errore attivazione plugin: ' . esc_html($e->getMessage()));
    }
}

/**
 * Disattivazione plugin
 */
function gabt_deactivate_plugin() {
    try {
        error_log('GABT: 🔄 Disattivazione plugin');
        
        // Pulisci cron jobs
        wp_clear_scheduled_hook('gabt_daily_schedine_send');
        wp_clear_scheduled_hook('gabt_weekly_log_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('GABT: ✅ Plugin disattivato correttamente');
        
        return true;
        
    } catch (Exception $e) {
        error_log('GABT: ❌ Errore disattivazione: ' . $e->getMessage());
        return false;
    }
}

/**
 * Inizializzazione plugin moderno
 */
function gabt_init_plugin() {
    try {
        error_log('GABT: 🔧 Inizializzazione plugin moderno');
        
        // Verifica costanti
        if (!defined('GABT_PLUGIN_PATH') || !defined('GABT_VERSION')) {
            throw new Exception('Costanti plugin non definite');
        }
        
        // Carica classe principale
        $core_file = GABT_PLUGIN_PATH . 'includes/class-plugin-core.php';
        
        if (!file_exists($core_file)) {
            throw new Exception("File core non trovato: {$core_file}");
        }
        
        if (!class_exists('GABT_Plugin_Core')) {
            require_once $core_file;
        }
        
        if (!class_exists('GABT_Plugin_Core')) {
            throw new Exception('Classe GABT_Plugin_Core non caricata');
        }
        
        // Inizializza istanza
        $plugin_instance = GABT_Plugin_Core::get_instance();
        
        if (!$plugin_instance) {
            throw new Exception('Impossibile creare istanza plugin');
        }
        
        error_log('GABT: ✅ Plugin inizializzato con successo');
        
        return $plugin_instance;
        
    } catch (Exception $e) {
        $error_message = 'GABT Init Error: ' . $e->getMessage();
        error_log('GABT: ❌ ' . $error_message);
        
        // Mostra errore admin
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>Gestione Accessi BT:</strong> ' . esc_html($error_message) . '</p>';
                echo '</div>';
            });
        }
        
        return false;
    }
}

/**
 * Verifica compatibilità
 */
function gabt_check_compatibility() {
    $issues = [];
    
    // PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $issues[] = 'PHP 7.4+ richiesto (attuale: ' . PHP_VERSION . ')';
    }
    
    // WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        $issues[] = 'WordPress 5.0+ richiesto';
    }
    
    // REST API
    if (!function_exists('rest_url')) {
        $issues[] = 'REST API WordPress non disponibile';
    }
    
    // Estensioni PHP
    $required_extensions = ['mysqli', 'curl', 'json'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $issues[] = "Estensione PHP mancante: {$ext}";
        }
    }
    
    return $issues;
}

// Hook di attivazione/disattivazione
register_activation_hook(__FILE__, 'gabt_activate_plugin');
register_deactivation_hook(__FILE__, 'gabt_deactivate_plugin');

// Inizializzazione
add_action('plugins_loaded', function() {
    // Verifica compatibilità
    $compatibility_issues = gabt_check_compatibility();
    
    if (!empty($compatibility_issues)) {
        add_action('admin_notices', function() use ($compatibility_issues) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Gestione Accessi BT:</strong> Problemi di compatibilità:</p>';
            echo '<ul>';
            foreach ($compatibility_issues as $issue) {
                echo '<li>' . esc_html($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });
        return;
    }
    
    // Inizializza plugin
    gabt_init_plugin();
    
}, 10);

// Messaggio di attivazione
add_action('admin_notices', function() {
    if (get_transient('gabt_activation_notice')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>🚀 Gestione Accessi BluTrasimeno</strong> attivato con tecnologie moderne! Versione: ' . GABT_VERSION . '</p>';
        echo '</div>';
        delete_transient('gabt_activation_notice');
    }
});

// Debug info per sviluppatori
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options')) {
            echo "<!-- GABT Debug: Plugin caricato, versione " . GABT_VERSION . " -->";
        }
    });
}