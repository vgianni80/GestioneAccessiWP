<?php
/**
 * Gestore delle form per gli ospiti
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Guest_Forms {
    
    private $settings_manager;
    
    public function __construct() {
        $this->settings_manager = new GABT_Settings_Manager();
    }
    
    /**
     * Renderizza il form per gli ospiti
     */
    public function render_form($booking) {
        $form_fields = $this->settings_manager->get_setting('guest_form_fields', $this->get_default_form_fields());
        $existing_guests = $booking->guests ?? array();
        
        echo '<div class="gabt-guest-form-container">';
        echo '<h2>Registrazione Ospiti - Prenotazione ' . esc_html($booking->booking_code) . '</h2>';
        
        // Informazioni prenotazione
        $this->render_booking_info($booking);
        
        // Form per ogni ospite
        for ($i = 0; $i < $booking->total_guests; $i++) {
            $guest = isset($existing_guests[$i]) ? $existing_guests[$i] : null;
            $this->render_guest_form($i + 1, $booking, $guest, $form_fields);
        }
        
        // Pulsanti azione
        $this->render_form_buttons($booking);
        
        echo '</div>';
    }
    
    /**
     * Renderizza le informazioni della prenotazione
     */
    private function render_booking_info($booking) {
        echo '<div class="gabt-booking-info">';
        echo '<h3>Dettagli Soggiorno</h3>';
        echo '<div class="gabt-booking-details">';
        echo '<p><strong>Check-in:</strong> ' . date('d/m/Y', strtotime($booking->checkin_date)) . '</p>';
        echo '<p><strong>Check-out:</strong> ' . date('d/m/Y', strtotime($booking->checkout_date)) . '</p>';
        echo '<p><strong>Notti:</strong> ' . $booking->nights . '</p>';
        echo '<p><strong>Ospiti totali:</strong> ' . $booking->total_guests . '</p>';
        
        if (!empty($booking->accommodation_name)) {
            echo '<p><strong>Struttura:</strong> ' . esc_html($booking->accommodation_name) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Renderizza il form per un singolo ospite
     */
    private function render_guest_form($guest_number, $booking, $guest = null, $form_fields = array()) {
        $is_first_guest = ($guest_number === 1);
        $guest_type = $is_first_guest ? 'capofamiglia' : 'componente_famiglia';
        
        if ($guest) {
            $guest_type = $guest->guest_type;
        }
        
        echo '<div class="gabt-guest-form" data-guest-number="' . $guest_number . '">';
        echo '<h4>Ospite ' . $guest_number . ($is_first_guest ? ' (Capofamiglia)' : '') . '</h4>';
        
        echo '<form class="gabt-single-guest-form">';
        echo '<input type="hidden" name="booking_code" value="' . esc_attr($booking->booking_code) . '">';
        echo '<input type="hidden" name="guest_type" value="' . esc_attr($guest_type) . '">';
        
        // Campi del form
        foreach ($form_fields as $field_name => $field_config) {
            $this->render_form_field($field_name, $field_config, $guest);
        }
        
        echo '<div class="gabt-form-actions">';
        echo '<button type="submit" class="gabt-save-guest">Salva Ospite ' . $guest_number . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Renderizza un singolo campo del form
     */
    private function render_form_field($field_name, $field_config, $guest = null) {
        $value = $guest ? ($guest->$field_name ?? '') : '';
        $required = $field_config['required'] ? 'required' : '';
        $label = $field_config['label'];
        
        echo '<div class="gabt-form-field">';
        echo '<label for="' . $field_name . '">' . esc_html($label);
        if ($field_config['required']) {
            echo ' <span class="required">*</span>';
        }
        echo '</label>';
        
        switch ($field_name) {
            case 'gender':
                echo '<select name="' . $field_name . '" id="' . $field_name . '" ' . $required . '>';
                echo '<option value="">Seleziona...</option>';
                echo '<option value="M"' . selected($value, 'M', false) . '>Maschio</option>';
                echo '<option value="F"' . selected($value, 'F', false) . '>Femmina</option>';
                echo '</select>';
                break;
                
            case 'document_type':
                echo '<select name="' . $field_name . '" id="' . $field_name . '" ' . $required . '>';
                echo '<option value="">Seleziona...</option>';
                echo '<option value="CI"' . selected($value, 'CI', false) . '>Carta d\'Identità</option>';
                echo '<option value="PP"' . selected($value, 'PP', false) . '>Passaporto</option>';
                echo '<option value="PG"' . selected($value, 'PG', false) . '>Patente di Guida</option>';
                echo '</select>';
                break;
                
            case 'birth_date':
            case 'document_date':
                echo '<input type="date" name="' . $field_name . '" id="' . $field_name . '" value="' . esc_attr($value) . '" ' . $required . '>';
                break;
                
            case 'nationality':
            case 'birth_country':
                echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" value="' . esc_attr($value ?: 'Italia') . '" ' . $required . '>';
                break;
                
            default:
                echo '<input type="text" name="' . $field_name . '" id="' . $field_name . '" value="' . esc_attr($value) . '" ' . $required . '>';
                break;
        }
        
        echo '</div>';
    }
    
    /**
     * Renderizza i pulsanti del form
     */
    private function render_form_buttons($booking) {
        echo '<div class="gabt-form-footer">';
        
        // Verifica se tutti gli ospiti sono stati registrati
        $guests_count = count($booking->guests ?? array());
        $all_guests_registered = ($guests_count >= $booking->total_guests);
        
        if ($all_guests_registered) {
            echo '<button type="button" class="gabt-complete-registration" data-booking-code="' . esc_attr($booking->booking_code) . '">';
            echo 'Completa Registrazione';
            echo '</button>';
        }
        
        echo '<div class="gabt-progress">';
        echo '<p>Ospiti registrati: ' . $guests_count . ' di ' . $booking->total_guests . '</p>';
        echo '<div class="gabt-progress-bar">';
        echo '<div class="gabt-progress-fill" style="width: ' . (($guests_count / $booking->total_guests) * 100) . '%"></div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
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
     * Valida i dati del form
     */
    public function validate_form_data($data) {
        $errors = array();
        
        // Validazioni di base
        if (empty($data['first_name'])) {
            $errors[] = 'Nome è obbligatorio';
        }
        
        if (empty($data['last_name'])) {
            $errors[] = 'Cognome è obbligatorio';
        }
        
        if (empty($data['gender']) || !in_array($data['gender'], array('M', 'F'))) {
            $errors[] = 'Sesso non valido';
        }
        
        if (empty($data['birth_date']) || !$this->is_valid_date($data['birth_date'])) {
            $errors[] = 'Data di nascita non valida';
        }
        
        if (empty($data['birth_place'])) {
            $errors[] = 'Luogo di nascita è obbligatorio';
        }
        
        if (empty($data['document_type']) || !in_array($data['document_type'], array('CI', 'PP', 'PG'))) {
            $errors[] = 'Tipo documento non valido';
        }
        
        if (empty($data['document_number'])) {
            $errors[] = 'Numero documento è obbligatorio';
        }
        
        if (empty($data['document_place'])) {
            $errors[] = 'Luogo rilascio documento è obbligatorio';
        }
        
        // Validazione età minima
        if (!empty($data['birth_date'])) {
            $birth_date = new DateTime($data['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
            
            if ($age > 120) {
                $errors[] = 'Data di nascita non realistica';
            }
        }
        
        // Validazione lunghezza campi
        if (strlen($data['first_name']) > 30) {
            $errors[] = 'Nome troppo lungo (massimo 30 caratteri)';
        }
        
        if (strlen($data['last_name']) > 50) {
            $errors[] = 'Cognome troppo lungo (massimo 50 caratteri)';
        }
        
        if (strlen($data['document_number']) > 20) {
            $errors[] = 'Numero documento troppo lungo (massimo 20 caratteri)';
        }
        
        return $errors;
    }
    
    /**
     * Verifica se una data è valida
     */
    private function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Renderizza un messaggio di errore
     */
    public function render_error_message($message) {
        echo '<div class="gabt-error-message">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }
    
    /**
     * Renderizza un messaggio di successo
     */
    public function render_success_message($message) {
        echo '<div class="gabt-success-message">';
        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }
    
    /**
     * Genera il form di ricerca prenotazione
     */
    public function render_booking_search_form() {
        echo '<div class="gabt-booking-search">';
        echo '<h3>Cerca la tua prenotazione</h3>';
        echo '<form id="gabt-booking-search-form">';
        echo '<div class="gabt-form-field">';
        echo '<label for="booking_code">Codice Prenotazione</label>';
        echo '<input type="text" id="booking_code" name="booking_code" required>';
        echo '</div>';
        echo '<button type="submit">Cerca</button>';
        echo '</form>';
        echo '</div>';
    }
}
