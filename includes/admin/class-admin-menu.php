<?php
/**
 * Gestore del menu admin per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Admin_Menu {
    
    private $admin_pages;
    
    public function __construct() {
        $this->admin_pages = new GABT_Admin_Pages();
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Aggiunge il menu admin
     */
    public function add_admin_menu() {
        // Menu principale
        add_menu_page(
            'Gestione Accessi Blu Trasimeno',
            'Gestione Accessi Blu Trasimeno',
            'manage_options',
            'gestione-accessi-bt',
            array($this->admin_pages, 'main_page'),
            'dashicons-groups',
            30
        );
        
        // Sottomenu - Dashboard (stesso della pagina principale)
        add_submenu_page(
            'gestione-accessi-bt',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'gestione-accessi-bt',
            array($this->admin_pages, 'main_page')
        );
        
        // Sottomenu - Nuova Prenotazione
        add_submenu_page(
            'gestione-accessi-bt',
            'Nuova Prenotazione',
            'Nuova Prenotazione',
            'manage_options',
            'gestione-accessi-bt-new-booking',
            array($this->admin_pages, 'new_booking_page')
        );
        
        // Sottomenu - Gestione Prenotazioni
        add_submenu_page(
            'gestione-accessi-bt',
            'Gestione Prenotazioni',
            'Gestione Prenotazioni',
            'manage_options',
            'gestione-accessi-bt-manage-bookings',
            array($this->admin_pages, 'manage_bookings_page')
        );
        
        // Sottomenu - Dettagli Prenotazione (nascosto dal menu)
        add_submenu_page(
            null, // parent_slug null = nascosto dal menu
            'Dettagli Prenotazione',
            'Dettagli Prenotazione',
            'manage_options',
            'gestione-accessi-bt-booking-details',
            array($this->admin_pages, 'booking_details_page')
        );
        
        // Sottomenu - Impostazioni
        add_submenu_page(
            'gestione-accessi-bt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'gestione-accessi-bt-settings',
            array($this->admin_pages, 'settings_page')
        );
        
        // Sottomenu - Test Connessione
        add_submenu_page(
            'gestione-accessi-bt',
            'Test Connessione',
            'Test Connessione',
            'manage_options',
            'gestione-accessi-bt-test',
            array($this->admin_pages, 'test_page')
        );
    }
    
    /**
     * Ottiene l'URL di una pagina admin
     */
    public static function get_admin_url($page, $args = array()) {
        $url = admin_url('admin.php?page=' . $page);
        
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        
        return $url;
    }
    
    /**
     * Verifica se siamo in una pagina del plugin
     */
    public static function is_plugin_page() {
        $screen = get_current_screen();
        return strpos($screen->id, 'gestione-accessi-bt') !== false;
    }
    
    /**
     * Ottiene la pagina corrente del plugin
     */
    public static function get_current_page() {
        if (!self::is_plugin_page()) {
            return null;
        }
        
        return isset($_GET['page']) ? sanitize_text_field($_GET['page']) : null;
    }
    
    /**
     * Aggiunge notice admin
     */
    public static function add_admin_notice($message, $type = 'success') {
        add_action('admin_notices', function() use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
    
    /**
     * Ottiene il breadcrumb per la pagina corrente
     */
    public static function get_breadcrumb() {
        $page = self::get_current_page();
        $breadcrumb = array();
        
        $breadcrumb[] = array(
            'title' => 'Gestione Accessi BT',
            'url' => self::get_admin_url('gestione-accessi-bt')
        );
        
        switch ($page) {
            case 'gestione-accessi-bt-new-booking':
                $breadcrumb[] = array('title' => 'Nuova Prenotazione');
                break;
                
            case 'gestione-accessi-bt-manage-bookings':
                $breadcrumb[] = array('title' => 'Gestione Prenotazioni');
                break;
                
            case 'gestione-accessi-bt-booking-details':
                $breadcrumb[] = array(
                    'title' => 'Gestione Prenotazioni',
                    'url' => self::get_admin_url('gestione-accessi-bt-manage-bookings')
                );
                $breadcrumb[] = array('title' => 'Dettagli Prenotazione');
                break;
                
            case 'gestione-accessi-bt-settings':
                $breadcrumb[] = array('title' => 'Impostazioni');
                break;
                
            case 'gestione-accessi-bt-test':
                $breadcrumb[] = array('title' => 'Test Connessione');
                break;
        }
        
        return $breadcrumb;
    }
}
