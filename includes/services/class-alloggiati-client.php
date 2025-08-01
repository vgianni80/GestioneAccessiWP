<?php
/**
 * Client SOAP per il servizio Alloggiati Web della Polizia di Stato
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Alloggiati_Client {
    
    private $utente;
    private $password; 
    private $wsKey;
    private $token;
    private $tokenExpires;
    private $serviceUrl = 'https://alloggiatiweb.poliziadistato.it/service/service.asmx';
    private $xml_parser;
    
    // Tipi di tabella disponibili
    const TABELLE = [
        0 => 'Luoghi',
        1 => 'Tipi_Documento', 
        2 => 'Tipi_Alloggiato',
        3 => 'TipoErrore',
        4 => 'ListaAppartamenti'
    ];

    public function __construct($utente, $password, $wsKey) {
        $this->utente = $utente;
        $this->password = $password;
        $this->wsKey = $wsKey;
        $this->xml_parser = new GABT_XML_Parser();
    }
    
    /**
     * Ritorna informazioni sul client per debug
     */
    public function getClientInfo() {
        return [
            'utente' => $this->utente,
            'service_url' => $this->serviceUrl,
            'has_token' => !empty($this->token),
            'token_expires' => $this->tokenExpires ? $this->tokenExpires->format('Y-m-d H:i:s') : null
        ];
    }
    
    /**
     * Genera il token di autenticazione
     */
    public function generateToken() {
        $this->log_debug("Generazione token di autenticazione per utente: " . $this->utente);

        $soapEnvelope = $this->buildGenerateTokenSoap($this->utente, $this->password, $this->wsKey);
        
        $response = $this->sendSoapRequest($soapEnvelope, 'AlloggiatiService/GenerateToken');
        
        if ($response === false) {
            throw new Exception('Errore nella chiamata SOAP per generazione token');
        }

        $result = $this->xml_parser->parseGenerateTokenResponse($response);
        
        if ($result['esito']) {
            $this->token = $result['token'];
            $this->tokenExpires = new DateTime($result['expires']);
            
            $this->log_debug("Token generato con successo, scadenza: " . $this->tokenExpires->format('Y-m-d H:i:s'));
            
            return [
                'success' => true,
                'token' => $this->token,
                'expires' => $this->tokenExpires->format('Y-m-d H:i:s')
            ];
        } else {
            $error = "Errore nella generazione del token: " . $result['errore_cod'] 
                        . ' - ' . $result['errore_des'] 
                        . ' - ' . $result['errore_dettaglio'];
            $this->log_error($error);
            return [
                'success' => false,
                'message' => $result['errore_des'],
                'debug_info' => [
                    'errore_cod' => $result['errore_cod'],
                    'errore_dettaglio' => $result['errore_dettaglio']
                ]
            ];
        }
    }
    
    /**
     * Test di autenticazione
     */
    public function testAuthentication() {
        $this->checkTokenValidity();
        
        $soapEnvelope = $this->buildAuthenticationTestSoap($this->utente, $this->token);
        $response = $this->sendSoapRequest($soapEnvelope, 'AlloggiatiService/Authentication_Test');
        
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Errore nella chiamata SOAP per test autenticazione'
            ];
        }
        
        $result = $this->xml_parser->parseAuthenticationTestResponse($response);
        
        return [
            'success' => $result['esito'],
            'message' => $result['esito'] ? 'Autenticazione riuscita' : $result['errore_des'],
            'debug_info' => $result
        ];
    }
    
    /**
     * Testa la connessione scaricando la tabella luoghi
     */
    public function testConnection() {
        try {
            $token_result = $this->generateToken();
            if (!$token_result['success']) {
                return $token_result;
            }
            
            $auth_result = $this->testAuthentication();
            if (!$auth_result['success']) {
                return $auth_result;
            }
            
            $table_result = $this->downloadTableForTest(0); // Tabella Luoghi
            
            return [
                'success' => $table_result['success'],
                'message' => $table_result['success'] ? 
                    'Connessione e download tabella riusciti' : 
                    'Errore nel download tabella: ' . $table_result['message'],
                'details' => $table_result['debug_info'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore durante il test: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Scarica tabella per test
     */
    public function downloadTableForTest($tipoTabella) {
        $this->checkTokenValidity();
        
        $soapEnvelope = $this->buildTabellaSoap($this->utente, $this->token, $tipoTabella);
        $response = $this->sendSoapRequest($soapEnvelope, 'AlloggiatiService/Tabella');
        
        if ($response === false) {
            return [
                'success' => false,
                'message' => 'Errore nella chiamata SOAP per download tabella'
            ];
        }
        
        $result = $this->xml_parser->parseTabellaResponse($response);
        
        return [
            'success' => $result['esito'],
            'message' => $result['esito'] ? 
                'Tabella scaricata con successo' : 
                $result['errore_des'],
            'debug_info' => $result
        ];
    }
    
    /**
     * Invia schedine al servizio Alloggiati
     */
    public function sendSchedule($schedineData) {
        try {
            $this->checkTokenValidity();
            
            $soapEnvelope = $this->buildSendScheduleSoap($this->utente, $this->token, $schedineData);
            $response = $this->sendSoapRequest($soapEnvelope, 'AlloggiatiService/Send');
            
            if ($response === false) {
                return [
                    'success' => false,
                    'message' => 'Errore nella chiamata SOAP per invio schedine'
                ];
            }
            
            $result = $this->xml_parser->parseSendResponse($response);
            
            return [
                'success' => $result['esito'],
                'message' => $result['esito'] ? 
                    'Schedine inviate con successo' : 
                    $result['errore_des'],
                'debug_info' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore durante l\'invio: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verifica validità token e lo rinnova se necessario
     */
    public function checkTokenValidity() {
        if (empty($this->token) || 
            empty($this->tokenExpires) || 
            $this->tokenExpires <= new DateTime()) {
            
            $result = $this->generateToken();
            if (!$result['success']) {
                throw new Exception('Impossibile generare token valido: ' . $result['message']);
            }
        }
    }
    
    /**
     * Costruisce il SOAP envelope per GenerateToken
     */
    private function buildGenerateTokenSoap($utente, $password, $wsKey) {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GenerateToken xmlns="AlloggiatiService">
      <utente>' . htmlspecialchars($utente) . '</utente>
      <password>' . htmlspecialchars($password) . '</password>
      <wsKey>' . htmlspecialchars($wsKey) . '</wsKey>
    </GenerateToken>
  </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Costruisce il SOAP envelope per Authentication_Test
     */
    private function buildAuthenticationTestSoap($utente, $token) {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Authentication_Test xmlns="AlloggiatiService">
      <utente>' . htmlspecialchars($utente) . '</utente>
      <token>' . htmlspecialchars($token) . '</token>
    </Authentication_Test>
  </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Costruisce il SOAP envelope per Tabella
     */
    private function buildTabellaSoap($utente, $token, $tipo) {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Tabella xmlns="AlloggiatiService">
      <utente>' . htmlspecialchars($utente) . '</utente>
      <token>' . htmlspecialchars($token) . '</token>
      <tipo>' . intval($tipo) . '</tipo>
    </Tabella>
  </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Costruisce il SOAP envelope per Send
     */
    private function buildSendScheduleSoap($utente, $token, $schedineData) {
        $schedineXml = '';
        foreach ($schedineData as $schedina) {
            $schedineXml .= '<string>' . htmlspecialchars($schedina) . '</string>';
        }
        
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" 
               xmlns:xsd="http://www.w3.org/2001/XMLSchema" 
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <Send xmlns="AlloggiatiService">
      <utente>' . htmlspecialchars($utente) . '</utente>
      <token>' . htmlspecialchars($token) . '</token>
      <schedine>' . $schedineXml . '</schedine>
    </Send>
  </soap:Body>
</soap:Envelope>';
    }
    
    /**
     * Invia richiesta SOAP
     */
    private function sendSoapRequest($soapEnvelope, $soapAction) {
        $headers = array(
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ' . $soapAction,
            'Content-Length: ' . strlen($soapEnvelope)
        );
        
        $args = array(
            'body' => $soapEnvelope,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        );
        
        $response = wp_remote_post($this->serviceUrl, $args);
        
        if (is_wp_error($response)) {
            $this->log_error('Errore HTTP: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_error('Codice risposta HTTP non valido: ' . $response_code);
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Logging per debug
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GABT_Alloggiati_Client] ' . $message);
        }
    }
    
    /**
     * Logging per errori
     */
    private function log_error($message) {
        error_log('[GABT_Alloggiati_Client ERROR] ' . $message);
    }
}
