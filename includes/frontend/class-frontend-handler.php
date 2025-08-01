<?php
/**
 * Gestore frontend per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Frontend_Handler {
    
    private $booking_repository;
    private $guest_forms;
    
    public function __construct() {
        $this->booking_repository = new GABT_Booking_Repository();
        $this->guest_forms = new GABT_Guest_Forms();
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook per URL personalizzati
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_custom_pages'));
        
        // Shortcode per form ospiti
        add_shortcode('gabt_guest_form', array($this, 'guest_form_shortcode'));
        add_shortcode('gabt_booking_status', array($this, 'booking_status_shortcode'));
    }
    
    /**
     * Aggiunge regole di rewrite per URL personalizzati
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^gestione-accessi/([^/]+)/?$',
            'index.php?gabt_action=guest_form&booking_code=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^gestione-accessi/([^/]+)/conferma/?$',
            'index.php?gabt_action=confirmation&booking_code=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^gestione-accessi/([^/]+)/stato/?$',
            'index.php?gabt_action=booking_status&booking_code=$matches[1]',
            'top'
        );
    }
    
    /**
     * Aggiunge variabili query personalizzate
     */
    public function add_query_vars($vars) {
        $vars[] = 'gabt_action';
        $vars[] = 'booking_code';
        return $vars;
    }
    
    /**
     * Gestisce le pagine personalizzate
     */
    public function handle_custom_pages() {
        $action = get_query_var('gabt_action');
        $booking_code = get_query_var('booking_code');
        
        if (!$action || !$booking_code) {
            return;
        }
        
        switch ($action) {
            case 'guest_form':
                $this->display_guest_form($booking_code);
                break;
                
            case 'confirmation':
                $this->display_confirmation_page($booking_code);
                break;
                
            case 'booking_status':
                $this->display_booking_status($booking_code);
                break;
        }
    }
    
    /**
     * Mostra il form per la registrazione degli ospiti
     */
    public function display_guest_form($booking_code) {
        $booking = $this->booking_repository->get_booking_by_code($booking_code);
        
        if (!$booking) {
            $this->display_error_page('Prenotazione non trovata');
            return;
        }
        
        if ($booking->status === 'completed') {
            $this->display_message_page(
                'Registrazione già completata',
                'La registrazione per questa prenotazione è già stata completata.'
            );
            return;
        }
        
        // Carica il template
        $this->load_template('guest-form', array(
            'booking' => $booking,
            'guest_forms' => $this->guest_forms
        ));
    }
    
    /**
     * Mostra la pagina di conferma
     */
    public function display_confirmation_page($booking_code) {
        $booking = $this->booking_repository->get_booking_by_code($booking_code);
        
        if (!$booking) {
            $this->display_error_page('Prenotazione non trovata');
            return;
        }
        
        $this->load_template('confirmation', array(
            'booking' => $booking
        ));
    }
    
    /**
     * Mostra lo stato della prenotazione
     */
    public function display_booking_status($booking_code) {
        $booking = $this->booking_repository->get_booking_by_code($booking_code);
        
        if (!$booking) {
            $this->display_error_page('Prenotazione non trovata');
            return;
        }
        
        $this->load_template('booking-status', array(
            'booking' => $booking
        ));
    }
    
    /**
     * Shortcode per il form ospiti
     */
    public function guest_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'booking_code' => ''
        ), $atts);
        
        if (empty($atts['booking_code'])) {
            return '<p>Codice prenotazione mancante.</p>';
        }
        
        $booking = $this->booking_repository->get_booking_by_code($atts['booking_code']);
        
        if (!$booking) {
            return '<p>Prenotazione non trovata.</p>';
        }
        
        ob_start();
        $this->guest_forms->render_form($booking);
        return ob_get_clean();
    }
    
    /**
     * Shortcode per lo stato della prenotazione
     */
    public function booking_status_shortcode($atts) {
        $atts = shortcode_atts(array(
            'booking_code' => ''
        ), $atts);
        
        if (empty($atts['booking_code'])) {
            return '<p>Codice prenotazione mancante.</p>';
        }
        
        $booking = $this->booking_repository->get_booking_by_code($atts['booking_code']);
        
        if (!$booking) {
            return '<p>Prenotazione non trovata.</p>';
        }
        
        ob_start();
        include GABT_PLUGIN_PATH . 'templates/frontend/booking-status-widget.php';
        return ob_get_clean();
    }
    
    /**
     * Carica un template frontend
     */
    private function load_template($template_name, $vars = array()) {
        // Estrae le variabili nel scope locale
        extract($vars);
        
        // Cerca prima nel tema, poi nel plugin
        $template_file = locate_template("gabt/{$template_name}.php");
        
        if (!$template_file) {
            $template_file = GABT_PLUGIN_PATH . "templates/frontend/{$template_name}.php";
        }
        
        if (file_exists($template_file)) {
            // Carica header del tema
            get_header();
            
            echo '<div class="gabt-frontend-container">';
            include $template_file;
            echo '</div>';
            
            // Carica footer del tema
            get_footer();
            exit;
        } else {
            $this->display_error_page('Template non trovato');
        }
    }
    
    /**
     * Mostra una pagina di errore
     */
    private function display_error_page($message) {
        get_header();
        echo '<div class="gabt-error-page">';
        echo '<h1>Errore</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<a href="' . home_url() . '">Torna alla home</a>';
        echo '</div>';
        get_footer();
        exit;
    }
    
    /**
     * Mostra una pagina con messaggio
     */
    private function display_message_page($title, $message) {
        get_header();
        echo '<div class="gabt-message-page">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($message) . '</p>';
        echo '<a href="' . home_url() . '">Torna alla home</a>';
        echo '</div>';
        get_footer();
        exit;
    }
    
    /**
     * Ottiene l'URL per una pagina frontend
     */
    public static function get_frontend_url($action, $booking_code) {
        $base_url = home_url('gestione-accessi/' . $booking_code);
        
        switch ($action) {
            case 'guest_form':
                return $base_url . '/';
                
            case 'confirmation':
                return $base_url . '/conferma/';
                
            case 'booking_status':
                return $base_url . '/stato/';
                
            default:
                return $base_url . '/';
        }
    }
    
    /**
     * Verifica se siamo in una pagina frontend del plugin
     */
    public static function is_plugin_frontend_page() {
        return !empty(get_query_var('gabt_action'));
    }
    
    /**
     * Ottiene informazioni sulla pagina corrente
     */
    public static function get_current_page_info() {
        if (!self::is_plugin_frontend_page()) {
            return null;
        }
        
        return array(
            'action' => get_query_var('gabt_action'),
            'booking_code' => get_query_var('booking_code')
        );
    }
    
    /**
     * Aggiunge meta tag per SEO
     */
    public function add_frontend_meta_tags() {
        if (!self::is_plugin_frontend_page()) {
            return;
        }
        
        $action = get_query_var('gabt_action');
        $booking_code = get_query_var('booking_code');
        
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        
        switch ($action) {
            case 'guest_form':
                echo '<title>Registrazione Ospiti - ' . $booking_code . '</title>' . "\n";
                break;
                
            case 'confirmation':
                echo '<title>Conferma Registrazione - ' . $booking_code . '</title>' . "\n";
                break;
                
            case 'booking_status':
                echo '<title>Stato Prenotazione - ' . $booking_code . '</title>' . "\n";
                break;
        }
    }
}
