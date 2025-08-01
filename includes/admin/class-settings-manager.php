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
            'username' => get_option(self::OPTION_PREFIX . 'alloggiati_username', ''),
            'password' => get_option(self::OPTION_PREFIX . 'alloggiati_password', ''),
            'ws_key' => get_option(self::OPTION_PREFIX . 'alloggiati_ws_key', ''),
            'auto_send' => get_option(self::OPTION_PREFIX . 'alloggiati_auto_send', 1),
            'send_time' => get_option(self::OPTION_PREFIX . 'alloggiati_send_time', '02:00')
        );
    }
    
    /**
     * Ottiene le impostazioni della struttura ricettiva
     */
    public function get_accommodation_settings() {
        return array(
            'name' => get_option(self::OPTION_PREFIX . 'accommodation_name', ''),
            'address' => get_option(self::OPTION_PREFIX . 'accommodation_address', ''),
            'phone' => get_option(self::OPTION_PREFIX . 'accommodation_phone', ''),
            'email' => get_option(self::OPTION_PREFIX . 'accommodation_email', ''),
            'comune' => get_option(self::OPTION_PREFIX . 'accommodation_comune', ''),
            'provincia' => get_option(self::OPTION_PREFIX . 'accommodation_provincia', '')
        );
    }
    
    /**
     * Ottiene le impostazioni email
     */
    public function get_email_settings() {
        return array(
            'from_name' => get_option(self::OPTION_PREFIX . 'email_from_name', get_bloginfo('name')),
            'from_email' => get_option(self::OPTION_PREFIX . 'email_from_email', get_option('admin_email')),
            'guest_subject' => get_option(self::OPTION_PREFIX . 'email_guest_subject', 'Conferma registrazione ospiti'),
            'guest_template' => get_option(self::OPTION_PREFIX . 'email_guest_template', $this->get_default_guest_email_template()),
            'admin_notifications' => get_option(self::OPTION_PREFIX . 'email_admin_notifications', 1),
            'admin_email' => get_option(self::OPTION_PREFIX . 'email_admin_email', get_option('admin_email'))
        );
    }
    
    /**
     * Ottiene le impostazioni generali
     */
    public function get_general_settings() {
        return array(
            'debug_mode' => get_option(self::OPTION_PREFIX . 'debug_mode', 0),
            'log_retention_days' => get_option(self::OPTION_PREFIX . 'log_retention_days', 30),
            'guest_form_fields' => get_option(self::OPTION_PREFIX . 'guest_form_fields', $this->get_default_form_fields()),
            'require_document_date' => get_option(self::OPTION_PREFIX . 'require_document_date', 0)
        );
    }
    
    /**
     * Ottiene tutte le impostazioni con valori di default sicuri
     */
    public function get_all_settings() {
        return array(
            'alloggiati' => array(
                'username' => $this->get_setting('alloggiati_username', ''),
                'password' => $this->get_setting('alloggiati_password', ''),
                'ws_key' => $this->get_setting('alloggiati_ws_key', ''),
                'auto_send' => $this->get_setting('alloggiati_auto_send', 0),
                'send_time' => $this->get_setting('alloggiati_send_time', '02:00')
            ),
            'accommodation' => array(
                'name' => $this->get_setting('accommodation_name', ''),
                'address' => $this->get_setting('accommodation_address', ''),
                'phone' => $this->get_setting('accommodation_phone', ''),
                'email' => $this->get_setting('accommodation_email', ''),
                'comune' => $this->get_setting('accommodation_comune', ''),
                'provincia' => $this->get_setting('accommodation_provincia', '')
            ),
            'email' => array(
                'from_name' => $this->get_setting('email_from_name', get_bloginfo('name')),
                'from_email' => $this->get_setting('email_from_email', get_option('admin_email')),
                'guest_subject' => $this->get_setting('email_guest_subject', 'Registrazione completata'),
                'guest_template' => $this->get_setting('email_guest_template', $this->get_default_guest_email_template()),
                'admin_notifications' => $this->get_setting('email_admin_notifications', 1),
                'admin_email' => $this->get_setting('email_admin_email', get_option('admin_email'))
            ),
            'general' => array(
                'debug_mode' => $this->get_setting('debug_mode', 0),
                'log_retention_days' => $this->get_setting('log_retention_days', 30),
                'require_document_date' => $this->get_setting('require_document_date', 0)
            )
        );
    }
    
    /**
     * Salva le impostazioni
     */
    public function save_settings($post_data) {
        $saved = true;
        
        // Impostazioni Alloggiati Web
        if (isset($post_data['alloggiati_username'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'alloggiati_username', sanitize_text_field($post_data['alloggiati_username']));
        }
        
        if (isset($post_data['alloggiati_password'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'alloggiati_password', sanitize_text_field($post_data['alloggiati_password']));
        }
        
        if (isset($post_data['alloggiati_ws_key'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'alloggiati_ws_key', sanitize_text_field($post_data['alloggiati_ws_key']));
        }
        
        if (isset($post_data['alloggiati_auto_send'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'alloggiati_auto_send', intval($post_data['alloggiati_auto_send']));
        }
        
        if (isset($post_data['alloggiati_send_time'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'alloggiati_send_time', sanitize_text_field($post_data['alloggiati_send_time']));
        }
        
        // Impostazioni struttura ricettiva
        if (isset($post_data['accommodation_name'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_name', sanitize_text_field($post_data['accommodation_name']));
        }
        
        if (isset($post_data['accommodation_address'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_address', sanitize_textarea_field($post_data['accommodation_address']));
        }
        
        if (isset($post_data['accommodation_phone'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_phone', sanitize_text_field($post_data['accommodation_phone']));
        }
        
        if (isset($post_data['accommodation_email'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_email', sanitize_email($post_data['accommodation_email']));
        }
        
        if (isset($post_data['accommodation_comune'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_comune', sanitize_text_field($post_data['accommodation_comune']));
        }
        
        if (isset($post_data['accommodation_provincia'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'accommodation_provincia', sanitize_text_field($post_data['accommodation_provincia']));
        }
        
        // Impostazioni email
        if (isset($post_data['email_from_name'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_from_name', sanitize_text_field($post_data['email_from_name']));
        }
        
        if (isset($post_data['email_from_email'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_from_email', sanitize_email($post_data['email_from_email']));
        }
        
        if (isset($post_data['email_guest_subject'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_guest_subject', sanitize_text_field($post_data['email_guest_subject']));
        }
        
        if (isset($post_data['email_guest_template'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_guest_template', wp_kses_post($post_data['email_guest_template']));
        }
        
        if (isset($post_data['email_admin_notifications'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_admin_notifications', intval($post_data['email_admin_notifications']));
        }
        
        if (isset($post_data['email_admin_email'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'email_admin_email', sanitize_email($post_data['email_admin_email']));
        }
        
        // Impostazioni generali
        if (isset($post_data['debug_mode'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'debug_mode', intval($post_data['debug_mode']));
        }
        
        if (isset($post_data['log_retention_days'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'log_retention_days', intval($post_data['log_retention_days']));
        }
        
        if (isset($post_data['require_document_date'])) {
            $saved &= update_option(self::OPTION_PREFIX . 'require_document_date', intval($post_data['require_document_date']));
        }
        
        return $saved;
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
     * Ottiene i campi form di default
     */
    private function get_default_form_fields() {
        return array(
            'first_name' => array('required' => true, 'label' => 'Nome'),
            'last_name' => array('required' => true, 'label' => 'Cognome'),
            'gender' => array('required' => true, 'label' => 'Sesso'),
            'birth_date' => array('required' => true, 'label' => 'Data di nascita'),
            'birth_place' => array('required' => true, 'label' => 'Luogo di nascita'),
            'birth_province' => array('required' => false, 'label' => 'Provincia di nascita'),
            'nationality' => array('required' => false, 'label' => 'Nazionalità'),
            'document_type' => array('required' => true, 'label' => 'Tipo documento'),
            'document_number' => array('required' => true, 'label' => 'Numero documento'),
            'document_place' => array('required' => true, 'label' => 'Luogo rilascio documento'),
            'document_date' => array('required' => false, 'label' => 'Data rilascio documento')
        );
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
     * Ottiene tutte le opzioni del plugin
     */
    public function get_all_plugin_options() {
        global $wpdb;
        
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
            self::OPTION_PREFIX . '%'
        ));
        
        $result = array();
        foreach ($options as $option) {
            $key = str_replace(self::OPTION_PREFIX, '', $option->option_name);
            $result[$key] = maybe_unserialize($option->option_value);
        }
        
        return $result;
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
    }
}
