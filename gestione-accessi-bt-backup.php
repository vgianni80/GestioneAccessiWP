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

// Autoloader per le classi del plugin
spl_autoload_register(function ($class_name) {
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

// Funzioni di attivazione e disattivazione sicure
function gabt_activate_plugin() {
    // Include il gestore di attivazione sicuro
    require_once GABT_PLUGIN_PATH . 'includes/class-activation-handler.php';
    
    // Chiama il metodo di attivazione sicuro
    return GABT_Activation_Handler::activate();
}

function gabt_deactivate_plugin() {
    // Include il gestore di attivazione sicuro
    require_once GABT_PLUGIN_PATH . 'includes/class-activation-handler.php';
    
    // Chiama il metodo di disattivazione sicuro
    return GABT_Activation_Handler::deactivate();
}

// Inizializza il plugin
function gabt_init_plugin() {
    // Include la classe principale
    if (!class_exists('GABT_Plugin_Core')) {
        require_once GABT_PLUGIN_PATH . 'includes/class-plugin-core.php';
    }
    
    return GABT_Plugin_Core::get_instance();
}

// Hook di attivazione e disattivazione
register_activation_hook(__FILE__, 'gabt_activate_plugin');
register_deactivation_hook(__FILE__, 'gabt_deactivate_plugin');

// Avvia il plugin
add_action('plugins_loaded', 'gabt_init_plugin');
