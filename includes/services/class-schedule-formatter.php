<?php
/**
 * Formatter per le schedine secondo il tracciato record Alloggiati Web
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Schedule_Formatter {
    
    // Mappa i tipi di ospite secondo la tabella del manuale
    const TIPO_ALLOGGIATO_MAP = [
        'capofamiglia' => '16',
        'componente_famiglia' => '17',
        'capogruppo' => '18',
        'componente_gruppo' => '19'
    ];
    
    // Mappa i tipi documento
    const DOCUMENTO_MAP = [
        'CI' => '1',  // Carta d'Identità
        'PP' => '2',  // Passaporto
        'PG' => '3'   // Patente di Guida
    ];
    
    /**
     * Formatta una singola schedina ospite
     */
    public static function formatSchedule($guest, $booking) {
        $schedina = '';
        
        // Tipo Alloggiato (posizioni 0-1)
        $schedina .= str_pad(self::TIPO_ALLOGGIATO_MAP[$guest->guest_type] ?? '17', 2, '0', STR_PAD_LEFT);
        
        // Data Arrivo (posizioni 2-11) - formato gg/mm/aaaa
        $checkin_date = DateTime::createFromFormat('Y-m-d', $booking->checkin_date);
        $schedina .= $checkin_date->format('d/m/Y');
        
        // Numero Giorni di Permanenza (posizioni 12-13)
        $schedina .= str_pad($booking->nights, 2, '0', STR_PAD_LEFT);
        
        // Cognome (posizioni 14-63) - 50 caratteri
        $schedina .= str_pad(strtoupper($guest->last_name), 50, ' ');
        
        // Nome (posizioni 64-93) - 30 caratteri
        $schedina .= str_pad(strtoupper($guest->first_name), 30, ' ');
        
        // Sesso (posizione 94) - M=1, F=2
        $schedina .= ($guest->gender === 'F') ? '2' : '1';
        
        // Data Nascita (posizioni 95-104) - formato gg/mm/aaaa
        $birth_date = DateTime::createFromFormat('Y-m-d', $guest->birth_date);
        $schedina .= $birth_date->format('d/m/Y');
        
        // Comune Nascita (posizioni 105-113) - 9 caratteri - Codice tabella comuni
        $schedina .= str_pad(self::getComuneCode($guest->birth_place), 9, ' ');
        
        // Provincia Nascita (posizioni 114-115) - 2 caratteri
        $schedina .= str_pad(self::getProvinciaFromComune($guest->birth_place), 2, ' ');
        
        // Stato Nascita (posizioni 116-124) - 9 caratteri - Codice tabella stati
        $schedina .= str_pad('100000001', 9, ' '); // Italia
        
        // Cittadinanza (posizioni 125-133) - 9 caratteri
        $schedina .= str_pad('100000001', 9, ' '); // Italia
        
        // Tipo Documento (posizioni 134-138) - 5 caratteri
        $schedina .= str_pad(self::DOCUMENTO_MAP[$guest->document_type] ?? '1', 5, ' ');
        
        // Numero Documento (posizioni 139-158) - 20 caratteri
        $schedina .= str_pad($guest->document_number, 20, ' ');
        
        // Luogo Rilascio Documento (posizioni 159-167) - 9 caratteri
        $schedina .= str_pad(self::getComuneCode($guest->document_place), 9, ' ');
        
        return $schedina;
    }
    
    /**
     * Formatta multiple schedine per una prenotazione
     */
    public static function formatBookingSchedules($guests, $booking) {
        $schedine = [];
        foreach ($guests as $guest) {
            $schedine[] = self::formatSchedule($guest, $booking);
        }
        return $schedine;
    }
    
    /**
     * Valida i dati di un ospite prima della formattazione
     */
    public static function validateGuestData($guest) {
        $errors = [];
        
        // Campi obbligatori
        $required_fields = [
            'first_name' => 'Nome',
            'last_name' => 'Cognome', 
            'gender' => 'Sesso',
            'birth_date' => 'Data di nascita',
            'birth_place' => 'Luogo di nascita',
            'document_type' => 'Tipo documento',
            'document_number' => 'Numero documento',
            'document_place' => 'Luogo rilascio documento'
        ];
        
        foreach ($required_fields as $field => $label) {
            if (empty($guest->$field)) {
                $errors[] = "Campo obbligatorio mancante: {$label}";
            }
        }
        
        // Validazioni specifiche
        if (!empty($guest->gender) && !in_array($guest->gender, ['M', 'F'])) {
            $errors[] = "Sesso deve essere M o F";
        }
        
        if (!empty($guest->birth_date) && !self::isValidDate($guest->birth_date)) {
            $errors[] = "Data di nascita non valida";
        }
        
        if (!empty($guest->document_type) && !array_key_exists($guest->document_type, self::DOCUMENTO_MAP)) {
            $errors[] = "Tipo documento non valido";
        }
        
        if (!empty($guest->guest_type) && !array_key_exists($guest->guest_type, self::TIPO_ALLOGGIATO_MAP)) {
            $errors[] = "Tipo ospite non valido";
        }
        
        // Lunghezza campi
        if (strlen($guest->first_name ?? '') > 30) {
            $errors[] = "Nome troppo lungo (max 30 caratteri)";
        }
        
        if (strlen($guest->last_name ?? '') > 50) {
            $errors[] = "Cognome troppo lungo (max 50 caratteri)";
        }
        
        if (strlen($guest->document_number ?? '') > 20) {
            $errors[] = "Numero documento troppo lungo (max 20 caratteri)";
        }
        
        return $errors;
    }
    
    /**
     * Valida i dati di una prenotazione
     */
    public static function validateBookingData($booking) {
        $errors = [];
        
        if (empty($booking->checkin_date)) {
            $errors[] = "Data check-in mancante";
        } elseif (!self::isValidDate($booking->checkin_date)) {
            $errors[] = "Data check-in non valida";
        }
        
        if (empty($booking->nights) || $booking->nights <= 0) {
            $errors[] = "Numero notti non valido";
        }
        
        if ($booking->nights > 99) {
            $errors[] = "Numero notti troppo alto (max 99)";
        }
        
        return $errors;
    }
    
    /**
     * Ottiene il codice del comune dalla tabella comuni
     */
    private static function getComuneCode($comune_name) {
        global $wpdb;
        
        $db_manager = new GABT_Database_Manager();
        $table_names = $db_manager->get_table_names();
        
        $code = $wpdb->get_var($wpdb->prepare(
            "SELECT codice FROM {$table_names['comuni']} WHERE nome LIKE %s LIMIT 1",
            '%' . $comune_name . '%'
        ));
        
        // Se non trovato, restituisce un codice generico
        return $code ?: '123456789';
    }
    
    /**
     * Ottiene la provincia dal comune
     */
    private static function getProvinciaFromComune($comune_name) {
        global $wpdb;
        
        $db_manager = new GABT_Database_Manager();
        $table_names = $db_manager->get_table_names();
        
        $provincia = $wpdb->get_var($wpdb->prepare(
            "SELECT provincia FROM {$table_names['comuni']} WHERE nome LIKE %s LIMIT 1",
            '%' . $comune_name . '%'
        ));
        
        return $provincia ?: 'XX';
    }
    
    /**
     * Verifica se una data è valida
     */
    private static function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Pulisce e normalizza una stringa per il tracciato record
     */
    private static function cleanString($string, $max_length = null) {
        // Rimuove caratteri speciali e accenti
        $string = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        
        // Rimuove caratteri non alfanumerici eccetto spazi
        $string = preg_replace('/[^A-Za-z0-9\s]/', '', $string);
        
        // Normalizza gli spazi
        $string = preg_replace('/\s+/', ' ', trim($string));
        
        // Tronca se necessario
        if ($max_length && strlen($string) > $max_length) {
            $string = substr($string, 0, $max_length);
        }
        
        return strtoupper($string);
    }
    
    /**
     * Formatta una schedina con validazione completa
     */
    public static function formatScheduleWithValidation($guest, $booking) {
        // Valida i dati
        $guest_errors = self::validateGuestData($guest);
        $booking_errors = self::validateBookingData($booking);
        
        $all_errors = array_merge($guest_errors, $booking_errors);
        
        if (!empty($all_errors)) {
            return [
                'success' => false,
                'errors' => $all_errors
            ];
        }
        
        // Formatta la schedina
        try {
            $schedina = self::formatSchedule($guest, $booking);
            
            return [
                'success' => true,
                'schedina' => $schedina,
                'length' => strlen($schedina)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['Errore nella formattazione: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Ottiene informazioni sui campi del tracciato record
     */
    public static function getTracciatoInfo() {
        return [
            'total_length' => 168,
            'fields' => [
                'tipo_alloggiato' => ['start' => 0, 'length' => 2, 'description' => 'Tipo Alloggiato'],
                'data_arrivo' => ['start' => 2, 'length' => 10, 'description' => 'Data Arrivo (gg/mm/aaaa)'],
                'giorni_permanenza' => ['start' => 12, 'length' => 2, 'description' => 'Giorni di Permanenza'],
                'cognome' => ['start' => 14, 'length' => 50, 'description' => 'Cognome'],
                'nome' => ['start' => 64, 'length' => 30, 'description' => 'Nome'],
                'sesso' => ['start' => 94, 'length' => 1, 'description' => 'Sesso (1=M, 2=F)'],
                'data_nascita' => ['start' => 95, 'length' => 10, 'description' => 'Data Nascita (gg/mm/aaaa)'],
                'comune_nascita' => ['start' => 105, 'length' => 9, 'description' => 'Codice Comune Nascita'],
                'provincia_nascita' => ['start' => 114, 'length' => 2, 'description' => 'Provincia Nascita'],
                'stato_nascita' => ['start' => 116, 'length' => 9, 'description' => 'Codice Stato Nascita'],
                'cittadinanza' => ['start' => 125, 'length' => 9, 'description' => 'Codice Cittadinanza'],
                'tipo_documento' => ['start' => 134, 'length' => 5, 'description' => 'Tipo Documento'],
                'numero_documento' => ['start' => 139, 'length' => 20, 'description' => 'Numero Documento'],
                'luogo_rilascio' => ['start' => 159, 'length' => 9, 'description' => 'Codice Luogo Rilascio']
            ]
        ];
    }
}
