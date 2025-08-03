<?php
/**
 * Plugin Core moderno con REST API
 * Elimina completamente AJAX legacy e usa tecnologie 2024
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Plugin_Core {
    
    private static $instance = null;
    private $admin_menu;
    private $admin_pages;
    private $frontend_handler;
    private $database_manager;
    private $rest_api;
    private $cron_manager;
    
    private function __construct() {
        $this->init_hooks();
        $this->load_components();
    }
    
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
    }
    
    /**
     * Carica i componenti del plugin
     */
    private function load_components() {
        try {
            // Database Manager (sempre per primo)
            if (class_exists('GABT_Database_Manager')) {
                $this->database_manager = new GABT_Database_Manager();
            }
            
            // REST API (sostituisce AJAX)
            if (class_exists('GABT_REST_API')) {
                $this->rest_api = new GABT_REST_API();
            }
            
            // Componenti admin
            if (is_admin()) {
                if (class_exists('GABT_Admin_Menu')) {
                    $this->admin_menu = new GABT_Admin_Menu();
                }
                
                if (class_exists('GABT_Admin_Pages')) {
                    $this->admin_pages = new GABT_Admin_Pages();
                }
            }
            
            // Componenti frontend
            if (class_exists('GABT_Frontend_Handler')) {
                $this->frontend_handler = new GABT_Frontend_Handler();
            }
            
            // Cron manager
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
        // Text domain
        load_plugin_textdomain('gestione-accessi-bt', false, dirname(plugin_basename(GABT_PLUGIN_PATH)) . '/languages');
        
        // Inizializza componenti in ordine
        if ($this->database_manager) {
            $this->database_manager->init();
            error_log('GABT: ✅ Database Manager inizializzato');
        }
        
        // IMPORTANTE: REST API sostituisce AJAX
        if ($this->rest_api) {
            $this->rest_api->init();
            error_log('GABT: ✅ REST API inizializzata');
        } else {
            error_log('GABT: ⚠️ REST API non disponibile');
        }
        
        if ($this->admin_menu) {
            $this->admin_menu->init();
            error_log('GABT: ✅ Admin Menu inizializzato');
        }
        
        if ($this->admin_pages) {
            $this->admin_pages->init();
            error_log('GABT: ✅ Admin Pages inizializzato');
        }
        
        if ($this->frontend_handler) {
            $this->frontend_handler->init();
            error_log('GABT: ✅ Frontend Handler inizializzato');
        }
        
        if ($this->cron_manager) {
            $this->cron_manager->init();
            error_log('GABT: ✅ Cron Manager inizializzato');
        }
        
        error_log('GABT: 🚀 Inizializzazione completa - Tecnologie moderne attive');
    }
    
    /**
     * Script frontend moderni
     */
    public function enqueue_frontend_scripts() {
        if (!$this->is_plugin_page()) {
            return;
        }
        
        // CSS moderno con custom properties
        wp_enqueue_style(
            'gabt-frontend-style', 
            GABT_PLUGIN_URL . 'assets/css/frontend-modern.css', 
            array(), 
            GABT_VERSION
        );
        
        // JavaScript moderno (ES6+)
        wp_enqueue_script(
            'gabt-frontend-script', 
            GABT_PLUGIN_URL . 'assets/js/frontend-modern.js', 
            array(), 
            GABT_VERSION, 
            array('strategy' => 'defer') // WordPress 6.3+
        );
        
        // Configurazione moderna con REST API
        wp_localize_script('gabt-frontend-script', 'gabFrontend', array(
            'rest_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'api_namespace' => 'gabt/v1',
            'current_user' => get_current_user_id(),
            'strings' => array(
                'loading' => __('Caricamento...', 'gestione-accessi-bt'),
                'error' => __('Errore', 'gestione-accessi-bt'),
                'success' => __('Successo', 'gestione-accessi-bt')
            )
        ));
    }
    
    /**
     * Script admin moderni
     */
    public function enqueue_admin_scripts($hook) {
        // Debug logging migliorato
        error_log("GABT: Admin scripts per hook: {$hook}");
        
        if (strpos($hook, 'gestione-accessi-bt') === false) {
            return;
        }
        
        // Verifica esistenza file (per debug)
        $js_path = GABT_PLUGIN_PATH . 'assets/js/admin-modern.js';
        $css_path = GABT_PLUGIN_PATH . 'assets/css/admin-modern.css';
        
        if (!file_exists($js_path)) {
            error_log("GABT: File JS non trovato: {$js_path}");
            // Fallback al file normale
            $js_path = GABT_PLUGIN_PATH . 'assets/js/admin.js';
            $js_url = GABT_PLUGIN_URL . 'assets/js/admin.js';
        } else {
            $js_url = GABT_PLUGIN_URL . 'assets/js/admin-modern.js';
        }
        
        if (!file_exists($css_path)) {
            $css_url = GABT_PLUGIN_URL . 'assets/css/admin.css';
        } else {
            $css_url = GABT_PLUGIN_URL . 'assets/css/admin-modern.css';
        }
        
        // CSS moderno
        wp_enqueue_style(
            'gabt-admin-style', 
            $css_url,
            array(), 
            GABT_VERSION
        );
        
        // JavaScript moderno con ES6 modules
        wp_enqueue_script(
            'gabt-admin-script', 
            $js_url,
            array(), 
            GABT_VERSION, 
            array(
                'strategy' => 'defer',
                'in_footer' => true
            )
        );
        
        // Configurazione moderna completa
        wp_localize_script('gabt-admin-script', 'gabAdmin', array(
            // REST API (sostituisce ajax_url)
            'rest_url' => rest_url(),
            'nonce' => wp_create_nonce('wp_rest'),
            'api_namespace' => 'gabt/v1',
            
            // Info plugin
            'plugin_url' => GABT_PLUGIN_URL,
            'plugin_version' => GABT_VERSION,
            'current_page' => $hook,
            
            // User info
            'current_user' => array(
                'id' => get_current_user_id(),
                'can_manage' => current_user_can('manage_options')
            ),
            
            // Configurazione avanzata
            'config' => array(
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
                'environment' => wp_get_environment_type(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            ),
            
            // Stringhe localizzate
            'strings' => array(
                'loading' => __('Caricamento...', 'gestione-accessi-bt'),
                'error' => __('Errore', 'gestione-accessi-bt'),
                'success' => __('Successo', 'gestione-accessi-bt'),
                'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?', 'gestione-accessi-bt'),
                'test_connection' => __('Test connessione in corso...', 'gestione-accessi-bt'),
                'connection_success' => __('Connessione riuscita!', 'gestione-accessi-bt'),
                'connection_failed' => __('Connessione fallita', 'gestione-accessi-bt'),
                'save_success' => __('Salvato con successo', 'gestione-accessi-bt'),
                'save_error' => __('Errore durante il salvataggio', 'gestione-accessi-bt'),
                'network_error' => __('Errore di rete', 'gestione-accessi-bt'),
                'invalid_response' => __('Risposta del server non valida', 'gestione-accessi-bt'),
                'unauthorized' => __('Accesso non autorizzato', 'gestione-accessi-bt'),
                'server_error' => __('Errore interno del server', 'gestione-accessi-bt')
            )
        ));
        
        error_log("GABT: Script moderni caricati per {$hook}");
    }
    
    /**
     * Verifica se siamo in una pagina del plugin
     */
    private function is_plugin_page() {
        if (!is_admin()) {
            return !empty(get_query_var('gabt_action'));
        }
        
        $screen = get_current_screen();
        return $screen && strpos($screen->id, 'gestione-accessi-bt') !== false;
    }
    
    /**
     * Ottiene informazioni API per JavaScript
     */
    public function get_api_info() {
        $endpoints = array();
        
        if ($this->rest_api) {
            $endpoints = array(
                'test_connection' => rest_url('gabt/v1/test-connection'),
                'settings' => rest_url('gabt/v1/settings'),
                'bookings' => rest_url('gabt/v1/bookings'),
                'stats' => rest_url('gabt/v1/stats')
            );
        }
        
        return array(
            'namespace' => 'gabt/v1',
            'base_url' => rest_url('gabt/v1'),
            'endpoints' => $endpoints,
            'nonce' => wp_create_nonce('wp_rest')
        );
    }
    
    /**
     * Getters per componenti
     */
    public function get_database_manager() {
        return $this->database_manager;
    }
    
    public function get_admin_menu() {
        return $this->admin_menu;
    }
    
    public function get_frontend_handler() {
        return $this->frontend_handler;
    }
    
    public function get_rest_api() {
        return $this->rest_api;
    }
    
    /**
     * Stato del plugin per debugging
     */
    public function get_plugin_status() {
        return array(
            'version' => GABT_VERSION,
            'components' => array(
                'database_manager' => $this->database_manager ? 'loaded' : 'not_loaded',
                'admin_menu' => $this->admin_menu ? 'loaded' : 'not_loaded',
                'admin_pages' => $this->admin_pages ? 'loaded' : 'not_loaded',
                'frontend_handler' => $this->frontend_handler ? 'loaded' : 'not_loaded',
                'rest_api' => $this->rest_api ? 'loaded' : 'not_loaded',
                'cron_manager' => $this->cron_manager ? 'loaded' : 'not_loaded'
            ),
            'api_info' => $this->get_api_info(),
            'environment' => array(
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'rest_enabled' => function_exists('rest_url')
            )
        );
    }
    
    /**
     * Metodi di utilità per sviluppo moderno
     */
    public function is_development_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    public function get_cache_buster() {
        return $this->is_development_mode() ? time() : GABT_VERSION;
    }
    
    /**
     * Registra custom post types se necessario (approccio moderno)
     */
    public function register_custom_post_types() {
        // Se in futuro volessimo usare CPT invece di tabelle custom
        // Approccio più WordPress-native
    }
    
    /**
     * Hook per sviluppatori esterni
     */
    public function get_hooks() {
        return array(
            'gabt_after_booking_save' => 'Dopo il salvataggio di una prenotazione',
            'gabt_before_schedine_send' => 'Prima dell\'invio delle schedine',
            'gabt_after_schedine_send' => 'Dopo l\'invio delle schedine',
            'gabt_settings_updated' => 'Dopo l\'aggiornamento delle impostazioni'
        );
    }
}