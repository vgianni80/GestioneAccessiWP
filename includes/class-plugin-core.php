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
    }
    
    /**
     * Carica i componenti del plugin
     */
    private function load_components() {
        try {
            // Carica sempre il database manager
            if (class_exists('GABT_Database_Manager')) {
                $this->database_manager = new GABT_Database_Manager();
            }
            
            // Carica componenti admin solo nell'area admin
            if (is_admin()) {
                if (class_exists('GABT_Admin_Pages')) {
                    $this->admin_pages = new GABT_Admin_Pages();
                }
                if (class_exists('GABT_Ajax_Handlers')) {
                    $this->ajax_handlers = new GABT_Ajax_Handlers();
                }
            }
            
            // Carica componenti frontend sempre (per AJAX)
            if (class_exists('GABT_Frontend_Handler')) {
                $this->frontend_handler = new GABT_Frontend_Handler();
            }
            
            // Carica sempre il cron manager
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
        load_plugin_textdomain('gestione-accessi-bt', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Inizializza i componenti
        if ($this->database_manager) {
            $this->database_manager->init();
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
     * Carica gli script frontend
     */
    public function enqueue_frontend_scripts() {
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
    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        
        // Carica solo nelle pagine del plugin
        if (strpos($screen->id, 'gestione-accessi-bt') === false) {
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
     * Attivazione del plugin
     */
    public static function activate() {
        try {
            // Assicurati che WordPress sia completamente caricato
            if (!function_exists('flush_rewrite_rules')) {
                return;
            }
            
            // Crea le tabelle del database
            if (class_exists('GABT_Database_Manager')) {
                $db_manager = new GABT_Database_Manager();
                $db_manager->create_tables();
            }
            
            // Aggiungi regole di rewrite
            if (class_exists('GABT_Frontend_Handler')) {
                $frontend_handler = new GABT_Frontend_Handler();
                $frontend_handler->add_rewrite_rules();
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Pianifica i cron job
            if (class_exists('GABT_Cron_Manager')) {
                $cron_manager = new GABT_Cron_Manager();
                $cron_manager->schedule_events();
            }
            
            // Imposta versione del plugin
            update_option('gabt_plugin_version', GABT_VERSION);
            
        } catch (Exception $e) {
            // Log dell'errore se possibile
            if (function_exists('error_log')) {
                error_log('GABT Plugin Activation Error: ' . $e->getMessage());
            }
            
            // Non bloccare l'attivazione per errori non critici
        }
    }
    
    /**
     * Disattivazione del plugin
     */
    public static function deactivate() {
        try {
            // Assicurati che WordPress sia completamente caricato
            if (!function_exists('wp_clear_scheduled_hook')) {
                return;
            }
            
            // Rimuovi i cron job
            wp_clear_scheduled_hook('gabt_daily_schedine_send');
            wp_clear_scheduled_hook('gabt_weekly_log_cleanup');
            
            // Flush rewrite rules
            if (function_exists('flush_rewrite_rules')) {
                flush_rewrite_rules();
            }
            
        } catch (Exception $e) {
            // Log dell'errore se possibile
            if (function_exists('error_log')) {
                error_log('GABT Plugin Deactivation Error: ' . $e->getMessage());
            }
        }
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
}
?>