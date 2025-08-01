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

// Funzione di attivazione minimalista
function gabt_activate_minimal() {
    // Crea solo le opzioni di base
    add_option('gabt_plugin_version', GABT_VERSION);
    add_option('gabt_activation_time', current_time('timestamp'));
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Funzione di disattivazione minimalista
function gabt_deactivate_minimal() {
    // Pulisci solo i cron job
    wp_clear_scheduled_hook('gabt_daily_schedine_send');
    flush_rewrite_rules();
}

// Hook di attivazione e disattivazione
register_activation_hook(__FILE__, 'gabt_activate_minimal');
register_deactivation_hook(__FILE__, 'gabt_deactivate_minimal');

// Inizializzazione del plugin
add_action('plugins_loaded', function() {
    // Carica solo se necessario
    if (is_admin()) {
        // Aggiungi menu admin
        add_action('admin_menu', function() {
            add_menu_page(
                'Gestione Accessi BT',
                'Gestione Accessi BT',
                'manage_options',
                'gestione-accessi-bt',
                function() {
                    echo '<div class="wrap">';
                    echo '<h1>Gestione Accessi BluTrasimeno</h1>';
                    echo '<p>Plugin attivato con successo! Versione: ' . GABT_VERSION . '</p>';
                    echo '<p>Il plugin è in modalità minimalista per debug.</p>';
                    echo '</div>';
                },
                'dashicons-admin-users',
                30
            );
        });
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

// Imposta il messaggio di attivazione
if (get_option('gabt_plugin_version') === GABT_VERSION) {
    set_transient('gabt_activation_notice', true, 30);
}
?>
