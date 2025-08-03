<?php
/**
 * Classe principale del plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Plugin_Core {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Componenti del plugin
     */
    private $admin_menu;
    private $admin_pages;
    private $frontend_handler;
    private $database_manager;
    private $ajax_handlers;
    private $cron_manager;
    
    /**
     * Costruttore privato per singleton
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_components();
    }
    
    /**
     * Ottiene l'istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inizializza gli hook WordPress
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    /**
     * Carica i componenti del plugin
     */
    private function load_components() {
        try {
            // Carica sempre il database manager per primo
            if (class_exists('GABT_Database_Manager')) {
                $this->database_manager = new GABT_Database_Manager();
            }
            
            // Carica componenti admin solo nell'area admin
            if (is_admin()) {
                if (class_exists('GABT_Admin_Menu')) {
                    $this->admin_menu = new GABT_Admin_Menu();
                }
                
                if (class_exists('GABT_Admin_Pages')) {
                    $this->admin_pages = new GABT_Admin_Pages();
                }
                
                if (class_exists('GABT_Ajax_Handlers')) {
                    $this->ajax_handlers = new GABT_Ajax_Handlers();
                }
            }
            
            // Carica componenti frontend
            if (class_exists('GABT_Frontend_Handler')) {
                $this->frontend_handler = new GABT_Frontend_Handler();
            }
            
            // Carica cron manager
            if (class_exists('GABT_Cron_Manager')) {
                $this->cron_manager = new GABT_Cron_Manager();
            }
            
        } catch (Exception $e) {
            error_log('GABT Plugin Core - Errore caricamento componenti: ' . $e->getMessage());
        }
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        // Carica il text domain per le traduzioni
        load_plugin_textdomain('gestione-accessi-bt', false, dirname(plugin_basename(GABT_PLUGIN_PATH)) . '/languages');
        
        // Inizializza i componenti
        if ($this->database_manager) {
            $this->database_manager->init();
        }
        
        if ($this->admin_menu) {
            $this->admin_menu->init();
        }
        
        if ($this->admin_pages) {
            $this->admin_pages->init();
        }
        
        if ($this->frontend_handler) {
            $this->frontend_handler->init();
        }
        
        if ($this->ajax_handlers) {
            $this->ajax_handlers->init();
        }
        
        if ($this->cron_manager) {
            $this->cron_manager->init();
        }
    }
    
    /**
     * Aggiunge il menu admin
     */
    public function add_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // CORRETTO: Ordine giusto dei parametri per add_menu_page()
        // ($page_title, $menu_title, $capability, $menu_slug, $callback, $icon_url, $position)
        add_menu_page(
            'Gestione Accessi BluTrasimeno',  // $page_title
            'Gestione Accessi BluTrasimeno',            // $menu_title  
            'manage_options',                 // $capability
            'gestione-accessi-bt',            // $menu_slug
            array($this, 'admin_page_dashboard'), // $callback
            'dashicons-groups',               // $icon_url
            30                                // $position
        );
        
        // CORRETTO: Ordine giusto dei parametri per add_submenu_page()
        // ($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback)
        add_submenu_page(
            'gestione-accessi-bt',            // $parent_slug
            'Dashboard',                      // $page_title
            'Dashboard',                      // $menu_title
            'manage_options',                 // $capability
            'gestione-accessi-bt',            // $menu_slug
            array($this, 'admin_page_dashboard') // $callback
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Nuova Prenotazione',
            'Nuova Prenotazione',
            'manage_options',
            'gestione-accessi-bt-new-booking',
            array($this, 'admin_page_new_booking')
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'gestione-accessi-bt-settings',
            array($this, 'admin_page_settings')
        );
        
        add_submenu_page(
            'gestione-accessi-bt',
            'Test Connessione',
            'Test Connessione',
            'manage_options',
            'gestione-accessi-bt-test',
            array($this, 'admin_page_test')
        );
    }
    
    /**
     * Pagina dashboard admin
     */
    public function admin_page_dashboard() {
        if ($this->admin_pages) {
            $this->admin_pages->main_page();
        } else {
            $this->admin_page_fallback('Dashboard');
        }
    }
    
    /**
     * Pagina nuova prenotazione
     */
    public function admin_page_new_booking() {
        if ($this->admin_pages) {
            $this->admin_pages->new_booking_page();
        } else {
            $this->admin_page_fallback('Nuova Prenotazione');
        }
    }
    
    /**
     * Pagina impostazioni
     */
    public function admin_page_settings() {
        if ($this->admin_pages) {
            $this->admin_pages->settings_page();
        } else {
            $this->admin_page_fallback('Impostazioni');
        }
    }
    
    /**
     * Pagina test connessione
     */
    public function admin_page_test() {
        if ($this->admin_pages) {
            $this->admin_pages->test_page();
        } else {
            $this->admin_page_fallback('Test Connessione');
        }
    }
    
    /**
     * Pagina di fallback quando i componenti non sono caricati
     */
    private function admin_page_fallback($page_title) {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($page_title) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Attenzione:</strong> Alcuni componenti del plugin non sono stati caricati correttamente.</p>';
        echo '<p>La pagina ' . esc_html($page_title) . ' non è disponibile al momento.</p>';
        echo '</div>';
        echo '<p><a href="' . admin_url('admin.php?page=gestione-accessi-bt') . '" class="button">Torna alla Dashboard</a></p>';
        echo '</div>';
    }
    
    /**
     * Carica gli script frontend
     */
    public function enqueue_frontend_scripts() {
        // Carica solo se necessario
        if (!$this->is_plugin_page()) {
            return;
        }
        
        wp_enqueue_style(
            'gabt-frontend-style', 
            GABT_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            GABT_VERSION
        );
        
        wp_enqueue_script(
            'gabt-frontend-script', 
            GABT_PLUGIN_URL . 'assets/js/frontend.js', 
            array(), 
            GABT_VERSION, 
            true
        );
        
        wp_localize_script('gabt-frontend-script', 'gabt_frontend_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gabt_frontend_nonce'),
            'booking_status_url' => home_url('/stato-prenotazione/'),
            'strings' => array(
                'loading' => __('Caricamento...', 'gestione-accessi-bt'),
                'error' => __('Errore', 'gestione-accessi-bt'),
                'success' => __('Successo', 'gestione-accessi-bt')
            )
        ));
    }
    
    /**
     * Carica gli script admin
     */
    public function enqueue_admin_scripts($hook) {
        // Carica solo nelle pagine del plugin
        if (strpos($hook, 'gestione-accessi-bt') === false) {
            return;
        }
        
        wp_enqueue_style(
            'gabt-admin-style', 
            GABT_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            GABT_VERSION
        );
        
        wp_enqueue_script(
            'gabt-admin-script', 
            GABT_PLUGIN_URL . 'assets/js/admin.js', 
            array(), 
            GABT_VERSION, 
            true
        );
        
        wp_localize_script('gabt-admin-script', 'gabt_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gabt_admin_nonce'),
            'strings' => array(
                'loading' => __('Caricamento...', 'gestione-accessi-bt'),
                'error' => __('Errore', 'gestione-accessi-bt'),
                'success' => __('Successo', 'gestione-accessi-bt'),
                'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?', 'gestione-accessi-bt')
            )
        ));
    }
    
    /**
     * Verifica se siamo in una pagina del plugin
     */
    private function is_plugin_page() {
        // Nel frontend, verifica query vars personalizzate
        if (!is_admin()) {
            return !empty(get_query_var('gabt_action'));
        }
        
        // Nell'admin, verifica la pagina corrente
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'gestione-accessi-bt') !== false;
    }
    
    /**
     * Ottiene il database manager
     */
    public function get_database_manager() {
        return $this->database_manager;
    }
    
    /**
     * Ottiene l'admin menu
     */
    public function get_admin_menu() {
        return $this->admin_menu;
    }
    
    /**
     * Ottiene il frontend handler
     */
    public function get_frontend_handler() {
        return $this->frontend_handler;
    }
    
    /**
     * Ottiene lo stato del plugin
     */
    public function get_plugin_status() {
        return array(
            'version' => GABT_VERSION,
            'database_manager' => $this->database_manager ? 'loaded' : 'not_loaded',
            'admin_menu' => $this->admin_menu ? 'loaded' : 'not_loaded',
            'admin_pages' => $this->admin_pages ? 'loaded' : 'not_loaded',
            'frontend_handler' => $this->frontend_handler ? 'loaded' : 'not_loaded',
            'ajax_handlers' => $this->ajax_handlers ? 'loaded' : 'not_loaded',
            'cron_manager' => $this->cron_manager ? 'loaded' : 'not_loaded'
        );
    }
}