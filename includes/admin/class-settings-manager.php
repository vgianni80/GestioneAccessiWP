<?php
/**
 * Gestore delle impostazioni per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Settings_Manager {
    
    const OPTION_PREFIX = 'gabt_';
    
    /**
     * Ottiene le impostazioni Alloggiati Web
     */
    public function get_alloggiati_settings() {
        return array(
            'username' => $this->get_setting('alloggiati_username', ''),
            'password' => $this->get_setting('alloggiati_password', ''),
            'ws_key' => $this->get_setting('alloggiati_ws_key', ''),
            'auto_send' => $this->get_setting('alloggiati_auto_send', 0),
            'send_time' => $this->get_setting('alloggiati_send_time', '02:00')
        );
    }
    
    /**
     * Ottiene le impostazioni della struttura ricettiva
     */
    public function get_accommodation_settings() {
        return array(
            'name' => $this->get_setting('accommodation_name', get_bloginfo('name')),
            'address' => $this->get_setting('accommodation_address', ''),
            'phone' => $this->get_setting('accommodation_phone', ''),
            'email' => $this->get_setting('accommodation_email', get_option('admin_email')),
            'comune' => $this->get_setting('accommodation_comune', ''),
            'provincia' => $this->get_setting('accommodation_provincia', '')
        );
    }
    
    /**
     * Ottiene le impostazioni email
     */
    public function get_email_settings() {
        return array(
            'from_name' => $this->get_setting('email_from_name', get_bloginfo('name')),
            'from_email' => $this->get_setting('email_from_email', get_option('admin_email')),
            'guest_subject' => $this->get_setting('email_guest_subject', 'Conferma registrazione ospiti'),
            'guest_template' => $this->get_setting('email_guest_template', $this->get_default_guest_email_template()),
            'admin_notifications' => $this->get_setting('email_admin_notifications', 1),
            'admin_email' => $this->get_setting('email_admin_email', get_option('admin_email'))
        );
    }
    
    /**
     * Ottiene le impostazioni generali
     */
    public function get_general_settings() {
        return array(
            'debug_mode' => $this->get_setting('debug_mode', 0),
            'log_retention_days' => $this->get_setting('log_retention_days', 30),
            'require_document_date' => $this->get_setting('require_document_date', 0)
        );
    }
    
    /**
     * Ottiene tutte le impostazioni
     */
    public function get_all_settings() {
        return array(
            'alloggiati' => $this->get_alloggiati_settings(),
            'accommodation' => $this->get_accommodation_settings(),
            'email' => $this->get_email_settings(),
            'general' => $this->get_general_settings()
        );
    }
    
    /**
     * Salva le impostazioni
     */
    public function save_settings($post_data) {
        $saved = true;
        
        try {
            // Impostazioni Alloggiati Web
            if (isset($post_data['alloggiati_username'])) {
                $saved &= $this->save_setting('alloggiati_username', sanitize_text_field($post_data['alloggiati_username']));
            }
            
            if (isset($post_data['alloggiati_password'])) {
                $saved &= $this->save_setting('alloggiati_password', sanitize_text_field($post_data['alloggiati_password']));
            }
            
            if (isset($post_data['alloggiati_ws_key'])) {
                $saved &= $this->save_setting('alloggiati_ws_key', sanitize_text_field($post_data['alloggiati_ws_key']));
            }
            
            if (isset($post_data['alloggiati_auto_send'])) {
                $saved &= $this->save_setting('alloggiati_auto_send', intval($post_data['alloggiati_auto_send']));
            }
            
            if (isset($post_data['alloggiati_send_time'])) {
                $saved &= $this->save_setting('alloggiati_send_time', sanitize_text_field($post_data['alloggiati_send_time']));
            }
            
            // Impostazioni struttura ricettiva
            if (isset($post_data['accommodation_name'])) {
                $saved &= $this->save_setting('accommodation_name', sanitize_text_field($post_data['accommodation_name']));
            }
            
            if (isset($post_data['accommodation_address'])) {
                $saved &= $this->save_setting('accommodation_address', sanitize_textarea_field($post_data['accommodation_address']));
            }
            
            if (isset($post_data['accommodation_phone'])) {
                $saved &= $this->save_setting('accommodation_phone', sanitize_text_field($post_data['accommodation_phone']));
            }
            
            if (isset($post_data['accommodation_email'])) {
                $saved &= $this->save_setting('accommodation_email', sanitize_email($post_data['accommodation_email']));
            }
            
            if (isset($post_data['accommodation_comune'])) {
                $saved &= $this->save_setting('accommodation_comune', sanitize_text_field($post_data['accommodation_comune']));
            }
            
            if (isset($post_data['accommodation_provincia'])) {
                $saved &= $this->save_setting('accommodation_provincia', sanitize_text_field($post_data['accommodation_provincia']));
            }
            
            // Impostazioni email
            if (isset($post_data['email_from_name'])) {
                $saved &= $this->save_setting('email_from_name', sanitize_text_field($post_data['email_from_name']));
            }
            
            if (isset($post_data['email_from_email'])) {
                $saved &= $this->save_setting('email_from_email', sanitize_email($post_data['email_from_email']));
            }
            
            if (isset($post_data['email_guest_subject'])) {
                $saved &= $this->save_setting('email_guest_subject', sanitize_text_field($post_data['email_guest_subject']));
            }
            
            if (isset($post_data['email_guest_template'])) {
                $saved &= $this->save_setting('email_guest_template', wp_kses_post($post_data['email_guest_template']));
            }
            
            if (isset($post_data['email_admin_notifications'])) {
                $saved &= $this->save_setting('email_admin_notifications', intval($post_data['email_admin_notifications']));
            }
            
            if (isset($post_data['email_admin_email'])) {
                $saved &= $this->save_setting('email_admin_email', sanitize_email($post_data['email_admin_email']));
            }
            
            // Impostazioni generali
            if (isset($post_data['debug_mode'])) {
                $saved &= $this->save_setting('debug_mode', intval($post_data['debug_mode']));
            }
            
            if (isset($post_data['log_retention_days'])) {
                $saved &= $this->save_setting('log_retention_days', intval($post_data['log_retention_days']));
            }
            
            if (isset($post_data['require_document_date'])) {
                $saved &= $this->save_setting('require_document_date', intval($post_data['require_document_date']));
            }
            
        } catch (Exception $e) {
            error_log('GABT Settings Manager - Errore salvataggio: ' . $e->getMessage());
            return false;
        }
        
        return $saved;
    }
    
    /**
     * Verifica se le impostazioni Alloggiati Web sono complete
     */
    public function are_alloggiati_settings_complete() {
        $settings = $this->get_alloggiati_settings();
        
        return !empty($settings['username']) && 
               !empty($settings['password']) && 
               !empty($settings['ws_key']);
    }
    
    /**
     * Ottiene una singola impostazione
     */
    public function get_setting($key, $default = '') {
        return get_option(self::OPTION_PREFIX . $key, $default);
    }
    
    /**
     * Salva una singola impostazione
     */
    public function save_setting($key, $value) {
        return update_option(self::OPTION_PREFIX . $key, $value);
    }
    
    /**
     * Elimina una impostazione
     */
    public function delete_setting($key) {
        return delete_option(self::OPTION_PREFIX . $key);
    }
    
    /**
     * Ottiene il template email di default per gli ospiti
     */
    private function get_default_guest_email_template() {
        return "Gentile {guest_name},\n\n" .
               "La registrazione per la sua prenotazione (codice: {booking_code}) è stata completata con successo.\n\n" .
               "Dettagli soggiorno:\n" .
               "- Check-in: {checkin_date}\n" .
               "- Check-out: {checkout_date}\n" .
               "- Notti: {nights}\n" .
               "- Struttura: {accommodation_name}\n\n" .
               "I suoi dati sono stati trasmessi alle autorità competenti come previsto dalla normativa vigente.\n\n" .
               "Cordiali saluti,\n" .
               "{accommodation_name}";
    }
    
    /**
     * Ottiene tutte le opzioni del plugin
     */
    public function get_all_plugin_options() {
        global $wpdb;
        
        try {
            $options = $wpdb->get_results($wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                self::OPTION_PREFIX . '%'
            ));
            
            $result = array();
            if ($options) {
                foreach ($options as $option) {
                    $key = str_replace(self::OPTION_PREFIX, '', $option->option_name);
                    $result[$key] = maybe_unserialize($option->option_value);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('GABT Settings Manager - Errore get_all_plugin_options: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Esporta le impostazioni
     */
    public function export_settings() {
        $settings = $this->get_all_plugin_options();
        
        // Rimuovi dati sensibili
        unset($settings['alloggiati_password']);
        unset($settings['alloggiati_ws_key']);
        
        return json_encode($settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Importa le impostazioni
     */
    public function import_settings($json_data) {
        $settings = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Dati JSON non validi');
        }
        
        $imported = 0;
        foreach ($settings as $key => $value) {
            // Salta dati sensibili per sicurezza
            if (in_array($key, array('alloggiati_password', 'alloggiati_ws_key'))) {
                continue;
            }
            
            if ($this->save_setting($key, $value)) {
                $imported++;
            }
        }
        
        return $imported;
    }
    
    /**
     * Reset delle impostazioni
     */
    public function reset_settings($section = null) {
        try {
            if ($section) {
                // Reset di una sezione specifica
                $prefix = self::OPTION_PREFIX . $section . '_';
                global $wpdb;
                
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $prefix . '%'
                ));
            } else {
                // Reset completo
                $options = $this->get_all_plugin_options();
                foreach (array_keys($options) as $key) {
                    $this->delete_setting($key);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('GABT Settings Manager - Errore reset: ' . $e->getMessage());
            return false;
        }
    }
}