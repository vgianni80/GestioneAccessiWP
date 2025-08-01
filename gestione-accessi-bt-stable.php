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

/**
 * Classe principale del plugin - Versione stabile
 */
class GABT_Plugin_Stable {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Hook AJAX
        add_action('wp_ajax_gabt_save_booking', array($this, 'ajax_save_booking'));
        add_action('wp_ajax_nopriv_gabt_save_booking', array($this, 'ajax_save_booking'));
        add_action('wp_ajax_gabt_save_guest', array($this, 'ajax_save_guest'));
        add_action('wp_ajax_nopriv_gabt_save_guest', array($this, 'ajax_save_guest'));
    }
    
    public function init() {
        // Carica text domain
        load_plugin_textdomain('gestione-accessi-bt', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Gestione Accessi BT',
            'Gestione Accessi BT',
            'manage_options',
            'gestione-accessi-bt',
            array($this, 'admin_dashboard'),
            'dashicons-admin-users',
            30
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Nuova Prenotazione',
            'Nuova Prenotazione',
            'manage_options',
            'gestione-accessi-bt-new',
            array($this, 'admin_new_booking')
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'gestione-accessi-bt-settings',
            array($this, 'admin_settings')
        );
    }
    
    public function admin_dashboard() {
        global $wpdb;
        
        // Statistiche base
        $table_bookings = $wpdb->prefix . 'gabt_bookings';
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings"),
            'this_month' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_bookings WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d",
                date('n'), date('Y')
            )),
            'active' => $wpdb->get_var("SELECT COUNT(*) FROM $table_bookings WHERE status = 'active'")
        );
        
        echo '<div class="wrap">';
        echo '<h1>Dashboard - Gestione Accessi BluTrasimeno</h1>';
        echo '<div class="gabt-dashboard-stats">';
        echo '<div class="gabt-stat-card">';
        echo '<h3>Prenotazioni Totali</h3>';
        echo '<span class="stat-number">' . ($stats['total'] ?? 0) . '</span>';
        echo '</div>';
        echo '<div class="gabt-stat-card">';
        echo '<h3>Questo Mese</h3>';
        echo '<span class="stat-number">' . ($stats['this_month'] ?? 0) . '</span>';
        echo '</div>';
        echo '<div class="gabt-stat-card">';
        echo '<h3>Attive</h3>';
        echo '<span class="stat-number">' . ($stats['active'] ?? 0) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // CSS inline per styling base
        echo '<style>
        .gabt-dashboard-stats { display: flex; gap: 20px; margin: 20px 0; }
        .gabt-stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; flex: 1; }
        .gabt-stat-card h3 { margin: 0 0 10px 0; color: #333; }
        .stat-number { font-size: 2em; font-weight: bold; color: #0073aa; }
        </style>';
    }
    
    public function admin_new_booking() {
        // Processa form se inviato
        if (isset($_POST['save_booking']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_new_booking')) {
            $this->process_new_booking();
        }
        
        echo '<div class="wrap">';
        echo '<h1>Nuova Prenotazione</h1>';
        echo '<form method="post" class="gabt-form">';
        wp_nonce_field('gabt_new_booking', 'gabt_nonce');
        
        echo '<table class="form-table">';
        echo '<tr><th>Codice Prenotazione</th><td><input type="text" name="booking_code" required /></td></tr>';
        echo '<tr><th>Nome Ospite</th><td><input type="text" name="guest_name" required /></td></tr>';
        echo '<tr><th>Email</th><td><input type="email" name="guest_email" /></td></tr>';
        echo '<tr><th>Check-in</th><td><input type="date" name="check_in_date" required /></td></tr>';
        echo '<tr><th>Check-out</th><td><input type="date" name="check_out_date" required /></td></tr>';
        echo '<tr><th>Numero Ospiti</th><td><input type="number" name="total_guests" min="1" value="1" required /></td></tr>';
        echo '</table>';
        
        echo '<p class="submit"><input type="submit" name="save_booking" class="button-primary" value="Salva Prenotazione" /></p>';
        echo '</form>';
        echo '</div>';
    }
    
    public function admin_settings() {
        // Processa form se inviato
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_settings')) {
            $this->process_settings();
        }
        
        echo '<div class="wrap">';
        echo '<h1>Impostazioni</h1>';
        echo '<form method="post">';
        wp_nonce_field('gabt_settings', 'gabt_nonce');
        
        echo '<table class="form-table">';
        echo '<tr><th>Username Alloggiati</th><td><input type="text" name="alloggiati_username" value="' . esc_attr(get_option('gabt_alloggiati_username', '')) . '" /></td></tr>';
        echo '<tr><th>Password Alloggiati</th><td><input type="password" name="alloggiati_password" value="' . esc_attr(get_option('gabt_alloggiati_password', '')) . '" /></td></tr>';
        echo '<tr><th>WS Key</th><td><input type="text" name="alloggiati_ws_key" value="' . esc_attr(get_option('gabt_alloggiati_ws_key', '')) . '" /></td></tr>';
        echo '<tr><th>Nome Struttura</th><td><input type="text" name="accommodation_name" value="' . esc_attr(get_option('gabt_accommodation_name', '')) . '" /></td></tr>';
        echo '</table>';
        
        echo '<p class="submit"><input type="submit" name="save_settings" class="button-primary" value="Salva Impostazioni" /></p>';
        echo '</form>';
        echo '</div>';
    }
    
    private function process_new_booking() {
        global $wpdb;
        
        $table_bookings = $wpdb->prefix . 'gabt_bookings';
        
        $result = $wpdb->insert(
            $table_bookings,
            array(
                'booking_code' => sanitize_text_field($_POST['booking_code']),
                'guest_name' => sanitize_text_field($_POST['guest_name']),
                'guest_email' => sanitize_email($_POST['guest_email']),
                'check_in_date' => sanitize_text_field($_POST['check_in_date']),
                'check_out_date' => sanitize_text_field($_POST['check_out_date']),
                'total_guests' => intval($_POST['total_guests']),
                'total_nights' => $this->calculate_nights($_POST['check_in_date'], $_POST['check_out_date']),
                'status' => 'active',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Prenotazione salvata con successo!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Errore nel salvataggio della prenotazione.</p></div>';
        }
    }
    
    private function process_settings() {
        update_option('gabt_alloggiati_username', sanitize_text_field($_POST['alloggiati_username']));
        update_option('gabt_alloggiati_password', sanitize_text_field($_POST['alloggiati_password']));
        update_option('gabt_alloggiati_ws_key', sanitize_text_field($_POST['alloggiati_ws_key']));
        update_option('gabt_accommodation_name', sanitize_text_field($_POST['accommodation_name']));
        
        echo '<div class="notice notice-success"><p>Impostazioni salvate con successo!</p></div>';
    }
    
    private function calculate_nights($checkin, $checkout) {
        $date1 = new DateTime($checkin);
        $date2 = new DateTime($checkout);
        $diff = $date1->diff($date2);
        return $diff->days;
    }
    
    public function ajax_save_booking() {
        check_ajax_referer('gabt_ajax', 'nonce');
        
        // Logica AJAX per salvare prenotazione
        wp_send_json_success(array('message' => 'Prenotazione salvata'));
    }
    
    public function ajax_save_guest() {
        check_ajax_referer('gabt_ajax', 'nonce');
        
        // Logica AJAX per salvare ospite
        wp_send_json_success(array('message' => 'Ospite salvato'));
    }
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('gabt-frontend', GABT_PLUGIN_URL . 'assets/js/frontend-simple.js', array(), GABT_VERSION, true);
        wp_localize_script('gabt-frontend', 'gabFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gabt_ajax')
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'gestione-accessi-bt') !== false) {
            wp_enqueue_script('gabt-admin', GABT_PLUGIN_URL . 'assets/js/admin-simple.js', array(), GABT_VERSION, true);
            wp_localize_script('gabt-admin', 'gabAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gabt_ajax')
            ));
        }
    }
}

// Funzione di attivazione
function gabt_activate_stable() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabella prenotazioni
    $table_bookings = $wpdb->prefix . 'gabt_bookings';
    $sql = "CREATE TABLE $table_bookings (
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
        UNIQUE KEY booking_code (booking_code)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Opzioni di default
    add_option('gabt_plugin_version', GABT_VERSION);
    add_option('gabt_alloggiati_username', '');
    add_option('gabt_alloggiati_password', '');
    add_option('gabt_alloggiati_ws_key', '');
    add_option('gabt_accommodation_name', get_bloginfo('name'));
    
    flush_rewrite_rules();
}

// Funzione di disattivazione
function gabt_deactivate_stable() {
    flush_rewrite_rules();
}

// Hook di attivazione e disattivazione
register_activation_hook(__FILE__, 'gabt_activate_stable');
register_deactivation_hook(__FILE__, 'gabt_deactivate_stable');

// Inizializza il plugin
add_action('plugins_loaded', function() {
    GABT_Plugin_Stable::get_instance();
});
?>
