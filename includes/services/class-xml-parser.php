<?php
/**
 * Parser XML per le risposte del servizio Alloggiati Web
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_XML_Parser {
    
    /**
     * Analizza la risposta GenerateToken
     */
    public function parseGenerateTokenResponse($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('ns', 'AlloggiatiService');
            
            $result = $xml->xpath('//ns:GenerateTokenResponse/ns:GenerateTokenResult')[0];
            
            if (!$result) {
                throw new Exception('Risposta XML non valida per GenerateToken');
            }
            
            return array(
                'esito' => (string)$result->Esito === 'true',
                'token' => (string)$result->Token,
                'expires' => (string)$result->DataScadenzaToken,
                'errore_cod' => (string)$result->ErroreCod,
                'errore_des' => (string)$result->ErroreDes,
                'errore_dettaglio' => (string)$result->ErroreDettaglio
            );
            
        } catch (Exception $e) {
            error_log('[GABT_XML_Parser] Errore parsing GenerateToken: ' . $e->getMessage());
            return array(
                'esito' => false,
                'errore_des' => 'Errore nel parsing della risposta XML: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Analizza la risposta Authentication_Test
     */
    public function parseAuthenticationTestResponse($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('ns', 'AlloggiatiService');
            
            $result = $xml->xpath('//ns:Authentication_TestResponse/ns:Authentication_TestResult')[0];
            
            if (!$result) {
                throw new Exception('Risposta XML non valida per Authentication_Test');
            }
            
            return array(
                'esito' => (string)$result->Esito === 'true',
                'errore_cod' => (string)$result->ErroreCod,
                'errore_des' => (string)$result->ErroreDes,
                'errore_dettaglio' => (string)$result->ErroreDettaglio
            );
            
        } catch (Exception $e) {
            error_log('[GABT_XML_Parser] Errore parsing Authentication_Test: ' . $e->getMessage());
            return array(
                'esito' => false,
                'errore_des' => 'Errore nel parsing della risposta XML: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Analizza la risposta Tabella
     */
    public function parseTabellaResponse($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('ns', 'AlloggiatiService');
            
            $result = $xml->xpath('//ns:TabellaResponse/ns:TabellaResult')[0];
            
            if (!$result) {
                throw new Exception('Risposta XML non valida per Tabella');
            }
            
            $tabella_data = array();
            if (isset($result->Tabella) && isset($result->Tabella->string)) {
                foreach ($result->Tabella->string as $row) {
                    $tabella_data[] = (string)$row;
                }
            }
            
            return array(
                'esito' => (string)$result->Esito === 'true',
                'tabella' => $tabella_data,
                'errore_cod' => (string)$result->ErroreCod,
                'errore_des' => (string)$result->ErroreDes,
                'errore_dettaglio' => (string)$result->ErroreDettaglio
            );
            
        } catch (Exception $e) {
            error_log('[GABT_XML_Parser] Errore parsing Tabella: ' . $e->getMessage());
            return array(
                'esito' => false,
                'errore_des' => 'Errore nel parsing della risposta XML: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Analizza la risposta Send
     */
    public function parseSendResponse($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xml->registerXPathNamespace('ns', 'AlloggiatiService');
            
            $result = $xml->xpath('//ns:SendResponse/ns:SendResult')[0];
            
            if (!$result) {
                throw new Exception('Risposta XML non valida per Send');
            }
            
            $errori = array();
            if (isset($result->Errori) && isset($result->Errori->string)) {
                foreach ($result->Errori->string as $errore) {
                    $errori[] = (string)$errore;
                }
            }
            
            return array(
                'esito' => (string)$result->Esito === 'true',
                'errori' => $errori,
                'errore_cod' => (string)$result->ErroreCod,
                'errore_des' => (string)$result->ErroreDes,
                'errore_dettaglio' => (string)$result->ErroreDettaglio
            );
            
        } catch (Exception $e) {
            error_log('[GABT_XML_Parser] Errore parsing Send: ' . $e->getMessage());
            return array(
                'esito' => false,
                'errore_des' => 'Errore nel parsing della risposta XML: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Formatta una risposta XML per debug
     */
    public function formatXmlResponse($xmlString) {
        try {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($xmlString);
            return $dom->saveXML();
        } catch (Exception $e) {
            return $xmlString;
        }
    }
    
    /**
     * Estrae errori da una risposta XML generica
     */
    public function extractErrors($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            
            // Cerca errori SOAP fault
            $faults = $xml->xpath('//soap:Fault');
            if (!empty($faults)) {
                $fault = $faults[0];
                return array(
                    'type' => 'soap_fault',
                    'code' => (string)$fault->faultcode,
                    'message' => (string)$fault->faultstring,
                    'detail' => (string)$fault->detail
                );
            }
            
            // Cerca errori standard del servizio
            $errors = $xml->xpath('//*[contains(name(), "ErroreCod")]');
            if (!empty($errors)) {
                $parent = $errors[0]->xpath('..')[0];
                return array(
                    'type' => 'service_error',
                    'code' => (string)$parent->ErroreCod,
                    'message' => (string)$parent->ErroreDes,
                    'detail' => (string)$parent->ErroreDettaglio
                );
            }
            
            return null;
            
        } catch (Exception $e) {
            return array(
                'type' => 'parse_error',
                'message' => 'Errore nel parsing XML: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Valida la struttura di una risposta XML
     */
    public function validateXmlResponse($xmlResponse, $expectedOperation) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            
            // Verifica che sia una risposta SOAP valida
            $envelope = $xml->xpath('//soap:Envelope');
            if (empty($envelope)) {
                return array(
                    'valid' => false,
                    'error' => 'Risposta non è un envelope SOAP valido'
                );
            }
            
            // Verifica che contenga l'operazione attesa
            $operation = $xml->xpath("//*[contains(name(), '{$expectedOperation}Response')]");
            if (empty($operation)) {
                return array(
                    'valid' => false,
                    'error' => "Risposta non contiene l'operazione attesa: {$expectedOperation}"
                );
            }
            
            return array('valid' => true);
            
        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => 'XML malformato: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Converte i dati di una tabella in array associativo
     */
    public function parseTableData($tableRows, $columns) {
        $data = array();
        
        foreach ($tableRows as $row) {
            $fields = explode('|', $row);
            $record = array();
            
            for ($i = 0; $i < count($columns) && $i < count($fields); $i++) {
                $record[$columns[$i]] = trim($fields[$i]);
            }
            
            if (!empty($record)) {
                $data[] = $record;
            }
        }
        
        return $data;
    }
    
    /**
     * Estrae informazioni di debug da una risposta XML
     */
    public function extractDebugInfo($xmlResponse) {
        try {
            $xml = new SimpleXMLElement($xmlResponse);
            
            $debug = array(
                'response_size' => strlen($xmlResponse),
                'has_soap_envelope' => !empty($xml->xpath('//soap:Envelope')),
                'operations' => array(),
                'errors' => array()
            );
            
            // Trova tutte le operazioni nella risposta
            foreach ($xml->xpath('//*[contains(name(), "Response")]') as $op) {
                $debug['operations'][] = $op->getName();
            }
            
            // Trova tutti gli errori
            $errors = $this->extractErrors($xmlResponse);
            if ($errors) {
                $debug['errors'][] = $errors;
            }
            
            return $debug;
            
        } catch (Exception $e) {
            return array(
                'error' => 'Impossibile estrarre info debug: ' . $e->getMessage()
            );
        }
    }
}
