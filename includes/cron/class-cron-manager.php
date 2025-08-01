<?php
/**
 * Gestore dei cron job per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Cron_Manager {
    
    private $booking_repository;
    private $settings_manager;
    
    public function __construct() {
        $this->booking_repository = new GABT_Booking_Repository();
        $this->settings_manager = new GABT_Settings_Manager();
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook per i cron job
        add_action('gabt_daily_schedine_send', array($this, 'auto_send_schedine'));
        add_action('gabt_cleanup_logs', array($this, 'cleanup_old_logs'));
        
        // Pianifica i cron job se non esistono
        $this->schedule_events();
    }
    
    /**
     * Pianifica gli eventi cron
     */
    public function schedule_events() {
        // Cron per invio automatico schedine
        if (!wp_next_scheduled('gabt_daily_schedine_send')) {
            $settings = $this->settings_manager->get_alloggiati_settings();
            $send_time = $settings['send_time'] ?? '02:00';
            
            // Calcola il timestamp per l'orario desiderato
            $next_run = strtotime('today ' . $send_time);
            if ($next_run < time()) {
                $next_run = strtotime('tomorrow ' . $send_time);
            }
            
            wp_schedule_event($next_run, 'daily', 'gabt_daily_schedine_send');
        }
        
        // Cron per pulizia log
        if (!wp_next_scheduled('gabt_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'gabt_cleanup_logs');
        }
    }
    
    /**
     * Rimuove gli eventi cron
     */
    public function unschedule_events() {
        wp_clear_scheduled_hook('gabt_daily_schedine_send');
        wp_clear_scheduled_hook('gabt_cleanup_logs');
    }
    
    /**
     * Invio automatico delle schedine
     */
    public function auto_send_schedine() {
        $this->log_debug('Avvio invio automatico schedine');
        
        // Verifica se l'invio automatico è abilitato
        $settings = $this->settings_manager->get_alloggiati_settings();
        if (!$settings['auto_send']) {
            $this->log_debug('Invio automatico disabilitato');
            return;
        }
        
        // Verifica configurazione
        if (!$this->settings_manager->are_alloggiati_settings_complete()) {
            $this->log_error('Configurazione Alloggiati Web incompleta');
            return;
        }
        
        // Ottiene le prenotazioni pronte per l'invio
        $bookings = $this->booking_repository->get_bookings_ready_for_schedine();
        
        if (empty($bookings)) {
            $this->log_debug('Nessuna prenotazione pronta per l\'invio');
            return;
        }
        
        $this->log_debug('Trovate ' . count($bookings) . ' prenotazioni da processare');
        
        // Inizializza il client Alloggiati
        $client = new GABT_Alloggiati_Client(
            $settings['username'],
            $settings['password'],
            $settings['ws_key']
        );
        
        $sent_count = 0;
        $error_count = 0;
        
        foreach ($bookings as $booking) {
            try {
                $result = $this->send_booking_schedine($booking, $client);
                
                if ($result['success']) {
                    $sent_count++;
                    $this->log_debug("Schedine inviate per prenotazione {$booking->booking_code}");
                } else {
                    $error_count++;
                    $this->log_error("Errore invio schedine per prenotazione {$booking->booking_code}: {$result['message']}");
                }
                
            } catch (Exception $e) {
                $error_count++;
                $this->log_error("Eccezione durante invio per prenotazione {$booking->booking_code}: " . $e->getMessage());
            }
            
            // Pausa tra gli invii per non sovraccaricare il servizio
            sleep(2);
        }
        
        $this->log_debug("Invio completato: {$sent_count} successi, {$error_count} errori");
        
        // Invia notifica admin se configurata
        $this->send_admin_notification($sent_count, $error_count);
    }
    
    /**
     * Invia le schedine per una singola prenotazione
     */
    private function send_booking_schedine($booking, $client) {
        // Carica gli ospiti se non già presenti
        if (!isset($booking->guests)) {
            $booking->guests = $this->booking_repository->get_booking_guests($booking->id);
        }
        
        if (empty($booking->guests)) {
            return array(
                'success' => false,
                'message' => 'Nessun ospite registrato'
            );
        }
        
        // Formatta le schedine
        $schedine = GABT_Schedule_Formatter::formatBookingSchedules($booking->guests, $booking);
        
        if (empty($schedine)) {
            return array(
                'success' => false,
                'message' => 'Impossibile generare le schedine'
            );
        }
        
        // Invia le schedine
        $result = $client->sendSchedule($schedine);
        
        if ($result['success']) {
            // Marca come inviate
            $this->booking_repository->mark_schedine_sent($booking->id);
            
            // Invia email di conferma al cliente se configurata
            $this->send_schedine_result_email($booking, $result);
        }
        
        return $result;
    }
    
    /**
     * Pulizia dei log vecchi
     */
    public function cleanup_old_logs() {
        $settings = $this->settings_manager->get_general_settings();
        $retention_days = $settings['log_retention_days'] ?? 30;
        
        $this->log_debug("Avvio pulizia log più vecchi di {$retention_days} giorni");
        
        // Percorso del file di log
        $log_file = WP_CONTENT_DIR . '/gabt-logs.txt';
        
        if (!file_exists($log_file)) {
            return;
        }
        
        $cutoff_date = date('Y-m-d', strtotime("-{$retention_days} days"));
        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        $filtered_lines = array();
        
        foreach ($lines as $line) {
            // Estrae la data dalla riga di log (formato: [YYYY-MM-DD HH:MM:SS])
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoff_date) {
                    $filtered_lines[] = $line;
                }
            } else {
                // Mantiene righe senza data (potrebbero essere continuazioni)
                $filtered_lines[] = $line;
            }
        }
        
        // Riscrive il file con le righe filtrate
        file_put_contents($log_file, implode("\n", $filtered_lines));
        
        $removed_lines = count($lines) - count($filtered_lines);
        $this->log_debug("Pulizia completata: rimosse {$removed_lines} righe di log");
    }
    
    /**
     * Invia notifica admin sui risultati dell'invio automatico
     */
    private function send_admin_notification($sent_count, $error_count) {
        $email_settings = $this->settings_manager->get_email_settings();
        
        if (!$email_settings['admin_notifications'] || empty($email_settings['admin_email'])) {
            return;
        }
        
        $subject = 'Gestione Accessi BT - Resoconto invio automatico schedine';
        
        $message = "Resoconto dell'invio automatico delle schedine:\n\n";
        $message .= "- Schedine inviate con successo: {$sent_count}\n";
        $message .= "- Errori durante l'invio: {$error_count}\n";
        $message .= "- Data e ora: " . current_time('d/m/Y H:i:s') . "\n\n";
        
        if ($error_count > 0) {
            $message .= "Si consiglia di verificare i log per maggiori dettagli sugli errori.\n";
        }
        
        $message .= "\n--\nGestione Accessi BluTrasimeno";
        
        $headers = array(
            'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>'
        );
        
        wp_mail($email_settings['admin_email'], $subject, $message, $headers);
    }
    
    /**
     * Invia email con risultato invio schedine al cliente
     */
    private function send_schedine_result_email($booking, $result) {
        $email_settings = $this->settings_manager->get_email_settings();
        
        if (empty($booking->accommodation_email)) {
            return;
        }
        
        $subject = 'Conferma invio schedine - ' . $booking->booking_code;
        
        $message = "Gentile Cliente,\n\n";
        $message .= "Le comunichiamo che le schedine per la prenotazione {$booking->booking_code} ";
        
        if ($result['success']) {
            $message .= "sono state inviate con successo alle autorità competenti.\n\n";
            $message .= "Data invio: " . current_time('d/m/Y H:i:s') . "\n";
        } else {
            $message .= "non sono state inviate a causa di un errore tecnico.\n\n";
            $message .= "La preghiamo di contattarci per risolvere il problema.\n";
        }
        
        $message .= "\nCordiali saluti,\n";
        
        $accommodation_settings = $this->settings_manager->get_accommodation_settings();
        $message .= $accommodation_settings['name'] ?: get_bloginfo('name');
        
        $headers = array(
            'From: ' . $email_settings['from_name'] . ' <' . $email_settings['from_email'] . '>'
        );
        
        wp_mail($booking->accommodation_email, $subject, $message, $headers);
    }
    
    /**
     * Ottiene lo stato dei cron job
     */
    public function get_cron_status() {
        return array(
            'schedine_send' => array(
                'scheduled' => wp_next_scheduled('gabt_daily_schedine_send'),
                'next_run' => wp_next_scheduled('gabt_daily_schedine_send') ? 
                    date('d/m/Y H:i:s', wp_next_scheduled('gabt_daily_schedine_send')) : 'Non pianificato'
            ),
            'log_cleanup' => array(
                'scheduled' => wp_next_scheduled('gabt_cleanup_logs'),
                'next_run' => wp_next_scheduled('gabt_cleanup_logs') ? 
                    date('d/m/Y H:i:s', wp_next_scheduled('gabt_cleanup_logs')) : 'Non pianificato'
            )
        );
    }
    
    /**
     * Forza l'esecuzione dell'invio schedine
     */
    public function force_schedine_send() {
        $this->log_debug('Avvio invio manuale schedine');
        $this->auto_send_schedine();
    }
    
    /**
     * Ripianifica i cron job
     */
    public function reschedule_events() {
        $this->unschedule_events();
        $this->schedule_events();
    }
    
    /**
     * Logging per debug
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GABT_Cron_Manager] ' . $message);
        }
        
        // Log su file personalizzato
        $this->write_log($message, 'DEBUG');
    }
    
    /**
     * Logging per errori
     */
    private function log_error($message) {
        error_log('[GABT_Cron_Manager ERROR] ' . $message);
        $this->write_log($message, 'ERROR');
    }
    
    /**
     * Scrive nel file di log personalizzato
     */
    private function write_log($message, $level = 'INFO') {
        $log_file = WP_CONTENT_DIR . '/gabt-logs.txt';
        
        $formatted_message = sprintf(
            "[%s] [%s] %s\n",
            current_time('Y-m-d H:i:s'),
            $level,
            $message
        );
        
        file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    }
}
