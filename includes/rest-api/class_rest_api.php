<?php
/**
 * REST API Endpoints moderni per Gestione Accessi BT
 * Sostituisce completamente il vecchio sistema AJAX
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_REST_API {
    
    private $namespace = 'gabt/v1';
    private $settings_manager;
    private $booking_repository;
    
    public function __construct() {
        $this->settings_manager = new GABT_Settings_Manager();
        $this->booking_repository = new GABT_Booking_Repository();
    }
    
    /**
     * Registra tutti gli endpoint REST API
     */
    public function init() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Registra le rotte REST API
     */
    public function register_routes() {
        // Endpoint per test connessione
        register_rest_route($this->namespace, '/test-connection', array(
            'methods' => 'POST',
            'callback' => array($this, 'test_connection'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'test_type' => array(
                    'required' => false,
                    'default' => 'connection',
                    'enum' => array('connection', 'authentication', 'download_tables')
                )
            )
        ));
        
        // Endpoint per salvare impostazioni
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_settings'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => $this->get_settings_schema()
        ));
        
        // Endpoint per ottenere impostazioni
        register_rest_route($this->namespace, '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // Endpoint per salvare prenotazione
        register_rest_route($this->namespace, '/bookings', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_booking'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => $this->get_booking_schema()
        ));
        
        // Endpoint per inviare schedine
        register_rest_route($this->namespace, '/bookings/(?P<id>\d+)/send-schedine', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_schedine'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));
        
        // Endpoint per statistiche
        register_rest_route($this->namespace, '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
    }
    
    /**
     * Verifica permessi admin
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Test connessione Alloggiati
     */
    public function test_connection($request) {
        try {
            $test_type = $request->get_param('test_type');
            
            $settings = $this->settings_manager->get_alloggiati_settings();
            
            if (empty($settings['username']) || empty($settings['password']) || empty($settings['ws_key'])) {
                return new WP_Error(
                    'incomplete_config',
                    'Configurazione incompleta. Verifica username, password e WS Key.',
                    array('status' => 400)
                );
            }
            
            if (!class_exists('GABT_Alloggiati_Client')) {
                return new WP_Error(
                    'missing_client',
                    'Classe client Alloggiati non trovata',
                    array('status' => 500)
                );
            }
            
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            switch ($test_type) {
                case 'authentication':
                    $result = $client->testAuthentication();
                    break;
                case 'download_tables':
                    $result = $client->downloadTableForTest(0);
                    break;
                default:
                    $result = $client->testConnection();
            }
            
            if ($result['success']) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => $result['message'],
                    'details' => $result['details'] ?? null,
                    'timestamp' => current_time('c')
                ));
            } else {
                return new WP_Error(
                    'connection_failed',
                    $result['message'],
                    array('status' => 503)
                );
            }
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Salva impostazioni
     */
    public function save_settings($request) {
        try {
            $params = $request->get_params();
            
            // Rimuovi parametri di controllo REST API
            unset($params['_wpnonce'], $params['_wp_http_referer']);
            
            $result = $this->settings_manager->save_settings($params);
            
            if ($result) {
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Impostazioni salvate con successo',
                    'timestamp' => current_time('c')
                ));
            } else {
                return new WP_Error(
                    'save_failed',
                    'Errore nel salvataggio delle impostazioni',
                    array('status' => 500)
                );
            }
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Ottiene impostazioni
     */
    public function get_settings($request) {
        try {
            $settings = $this->settings_manager->get_all_settings();
            
            // Rimuovi dati sensibili
            if (isset($settings['alloggiati']['password'])) {
                $settings['alloggiati']['password'] = '***';
            }
            if (isset($settings['alloggiati']['ws_key'])) {
                $settings['alloggiati']['ws_key'] = substr($settings['alloggiati']['ws_key'], 0, 4) . '***';
            }
            
            return rest_ensure_response($settings);
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Salva prenotazione
     */
    public function save_booking($request) {
        try {
            $params = $request->get_params();
            
            $booking_data = array(
                'checkin_date' => $params['checkin_date'],
                'checkout_date' => $params['checkout_date'],
                'nights' => absint($params['nights']),
                'adults' => absint($params['adults']),
                'children' => absint($params['children'] ?? 0),
                'total_guests' => absint($params['adults']) + absint($params['children'] ?? 0),
                'accommodation_name' => sanitize_text_field($params['accommodation_name'] ?? ''),
                'accommodation_address' => sanitize_textarea_field($params['accommodation_address'] ?? ''),
                'accommodation_phone' => sanitize_text_field($params['accommodation_phone'] ?? ''),
                'accommodation_email' => sanitize_email($params['accommodation_email'] ?? ''),
                'notes' => sanitize_textarea_field($params['notes'] ?? '')
            );
            
            $booking_id = $this->booking_repository->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                return $booking_id;
            }
            
            $booking = $this->booking_repository->get_booking($booking_id);
            
            return rest_ensure_response(array(
                'success' => true,
                'booking_id' => $booking_id,
                'booking_code' => $booking->booking_code,
                'message' => 'Prenotazione salvata con successo',
                'redirect_url' => admin_url('admin.php?page=gestione-accessi-bt-booking-details&id=' . $booking_id)
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Invia schedine
     */
    public function send_schedine($request) {
        try {
            $booking_id = absint($request->get_param('id'));
            
            $booking = $this->booking_repository->get_booking($booking_id);
            
            if (!$booking) {
                return new WP_Error(
                    'booking_not_found',
                    'Prenotazione non trovata',
                    array('status' => 404)
                );
            }
            
            if (empty($booking->guests)) {
                return new WP_Error(
                    'no_guests',
                    'Nessun ospite registrato per questa prenotazione',
                    array('status' => 400)
                );
            }
            
            if (!$this->settings_manager->are_alloggiati_settings_complete()) {
                return new WP_Error(
                    'incomplete_config',
                    'Configurazione Alloggiati Web incompleta',
                    array('status' => 400)
                );
            }
            
            // Formatta le schedine
            $schedine = GABT_Schedule_Formatter::formatBookingSchedules($booking->guests, $booking);
            
            if (empty($schedine)) {
                return new WP_Error(
                    'format_error',
                    'Impossibile generare le schedine',
                    array('status' => 500)
                );
            }
            
            // Invia le schedine
            $settings = $this->settings_manager->get_alloggiati_settings();
            $client = new GABT_Alloggiati_Client(
                $settings['username'],
                $settings['password'],
                $settings['ws_key']
            );
            
            $result = $client->sendSchedule($schedine);
            
            if ($result['success']) {
                $this->booking_repository->mark_schedine_sent($booking_id);
                
                return rest_ensure_response(array(
                    'success' => true,
                    'message' => 'Schedine inviate con successo',
                    'schedine_count' => count($schedine),
                    'timestamp' => current_time('c')
                ));
            } else {
                return new WP_Error(
                    'send_failed',
                    $result['message'] ?? 'Errore sconosciuto durante l\'invio',
                    array('status' => 503)
                );
            }
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Ottiene statistiche
     */
    public function get_stats($request) {
        try {
            $stats = $this->booking_repository->get_booking_stats();
            
            return rest_ensure_response(array(
                'stats' => $stats,
                'timestamp' => current_time('c'),
                'cache_duration' => 300 // 5 minuti
            ));
            
        } catch (Exception $e) {
            return new WP_Error(
                'server_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
    
    /**
     * Schema per validazione impostazioni
     */
    private function get_settings_schema() {
        return array(
            'alloggiati_username' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'alloggiati_password' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'alloggiati_ws_key' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'alloggiati_auto_send' => array(
                'required' => false,
                'type' => 'boolean'
            ),
            'alloggiati_send_time' => array(
                'required' => false,
                'type' => 'string',
                'pattern' => '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/'
            ),
            'accommodation_name' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'accommodation_address' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            ),
            'accommodation_phone' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'accommodation_email' => array(
                'required' => false,
                'type' => 'string',
                'format' => 'email'
            )
        );
    }
    
    /**
     * Schema per validazione prenotazioni
     */
    private function get_booking_schema() {
        return array(
            'checkin_date' => array(
                'required' => true,
                'type' => 'string',
                'format' => 'date'
            ),
            'checkout_date' => array(
                'required' => true,
                'type' => 'string',
                'format' => 'date'
            ),
            'adults' => array(
                'required' => true,
                'type' => 'integer',
                'minimum' => 1
            ),
            'children' => array(
                'required' => false,
                'type' => 'integer',
                'minimum' => 0,
                'default' => 0
            )
        );
    }
    
    /**
     * Ottiene l'URL base per le API
     */
    public function get_api_url() {
        return rest_url($this->namespace);
    }
}
