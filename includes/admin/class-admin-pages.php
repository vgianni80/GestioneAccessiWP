<?php
/**
 * Pagine admin per il plugin Gestione Accessi BluTrasimeno
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GABT_Admin_Pages {
    
    private $booking_repository;
    private $settings_manager;
    
    public function __construct() {
        // Carica dipendenze solo se le classi esistono
        if (class_exists('GABT_Booking_Repository')) {
            $this->booking_repository = new GABT_Booking_Repository();
        }
        
        if (class_exists('GABT_Settings_Manager')) {
            $this->settings_manager = new GABT_Settings_Manager();
        }
    }
    
    /**
     * Inizializzazione
     */
    public function init() {
        // Hook necessari per le pagine admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Carica assets admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'gestione-accessi-bt') === false) {
            return;
        }
        // Assets già caricati dalla classe principale
    }
    
    /**
     * Pagina principale - Dashboard
     */
    public function main_page() {
        $stats = array('total' => 0, 'this_month' => 0, 'schedine_sent' => 0, 'by_status' => array());
        $recent_bookings = array();
        
        if ($this->booking_repository) {
            try {
                $stats = $this->booking_repository->get_booking_stats();
                $recent_bookings = $this->booking_repository->get_bookings(array('limit' => 5));
            } catch (Exception $e) {
                error_log('GABT Admin Pages - Errore caricamento dati: ' . $e->getMessage());
            }
        }
        
        $this->render_template('dashboard', array(
            'stats' => $stats,
            'recent_bookings' => $recent_bookings
        ));
    }
    
    /**
     * Pagina nuova prenotazione
     */
    public function new_booking_page() {
        $message = '';
        $message_type = '';
        
        // Gestisce il salvataggio della prenotazione
        if (isset($_POST['save_booking']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_new_booking')) {
            if ($this->booking_repository) {
                $result = $this->process_new_booking();
                
                if (is_wp_error($result)) {
                    $message = $result->get_error_message();
                    $message_type = 'error';
                } else {
                    $message = 'Prenotazione creata con successo! Codice: ' . $result['booking_code'];
                    $message_type = 'success';
                    
                    // Redirect alla pagina dettagli se disponibile
                    $redirect_url = admin_url('admin.php?page=gestione-accessi-bt&booking_id=' . $result['booking_id']);
                    echo '<script>setTimeout(function(){ window.location.href = "' . $redirect_url . '"; }, 2000);</script>';
                }
            } else {
                $message = 'Errore: componenti non disponibili';
                $message_type = 'error';
            }
        }
        
        $this->render_template('new-booking', array(
            'message' => $message,
            'message_type' => $message_type
        ));
    }
    
    /**
     * Pagina impostazioni
     */
    public function settings_page() {
        $message = '';
        $message_type = '';
        
        // Gestisce il salvataggio delle impostazioni
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_settings')) {
            if ($this->settings_manager) {
                $result = $this->settings_manager->save_settings($_POST);
                
                if ($result) {
                    $message = 'Impostazioni salvate con successo!';
                    $message_type = 'success';
                } else {
                    $message = 'Errore nel salvataggio delle impostazioni';
                    $message_type = 'error';
                }
            } else {
                $message = 'Errore: gestore impostazioni non disponibile';
                $message_type = 'error';
            }
        }
        
        $settings = array();
        if ($this->settings_manager) {
            try {
                $settings = $this->settings_manager->get_all_settings();
            } catch (Exception $e) {
                $settings = $this->get_default_settings();
                $message = 'Utilizzando impostazioni di default';
                $message_type = 'warning';
            }
        } else {
            $settings = $this->get_default_settings();
        }
        
        // Prova prima il template moderno
        $modern_template = GABT_PLUGIN_PATH . "templates/admin/settings-modern.php";
        
        if (file_exists($modern_template)) {
            // Carica template moderno
            extract(array(
                'settings' => $settings,
                'message' => $message,
                'message_type' => $message_type
            ));
            include $modern_template;
        } else {
            // Fallback al template integrato moderno
            $this->render_modern_settings(array(
                'settings' => $settings,
                'message' => $message,
                'message_type' => $message_type
            ));
        }
    }
    
    /**
     * Pagina test connessione
     */
    public function test_page() {
        $test_result = null;
        
        // Gestisce il test della connessione
        if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['gabt_nonce'], 'gabt_test')) {
            if ($this->settings_manager && class_exists('GABT_Alloggiati_Client')) {
                $settings = $this->settings_manager->get_alloggiati_settings();
                
                if (empty($settings['username']) || empty($settings['password']) || empty($settings['ws_key'])) {
                    $test_result = array(
                        'success' => false,
                        'message' => 'Configurazione incompleta. Verifica le impostazioni.'
                    );
                } else {
                    try {
                        $client = new GABT_Alloggiati_Client(
                            $settings['username'],
                            $settings['password'],
                            $settings['ws_key']
                        );
                        
                        $test_result = $client->testConnection();
                    } catch (Exception $e) {
                        $test_result = array(
                            'success' => false,
                            'message' => 'Errore durante il test: ' . $e->getMessage()
                        );
                    }
                }
            } else {
                $test_result = array(
                    'success' => false,
                    'message' => 'Componenti necessari non disponibili'
                );
            }
        }
        
        $settings = array();
        if ($this->settings_manager) {
            $settings = $this->settings_manager->get_all_settings();
        }
        
        $this->render_template('test-connection', array(
            'test_result' => $test_result,
            'settings' => $settings
        ));
    }
    
    /**
     * Renderizza un template admin
     */
    private function render_template($template_name, $vars = array()) {
        // Estrae le variabili nel scope
        extract($vars);
        
        // Mostra eventuali messaggi
        if (!empty($message)) {
            $class = $message_type === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
        
        // Carica il template se esiste
        $template_file = GABT_PLUGIN_PATH . "templates/admin/{$template_name}.php";
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            $this->render_fallback_template($template_name, $vars);
        }
    }
    
    public function render_modern_settings($vars) {
        $settings = $vars['settings'] ?? array();
        ?>
        <div class="gabt-admin-container">
            <div class="gabt-flex gabt-items-center gabt-justify-between gabt-mb-8">
                <div>
                    <h1 class="gabt-text-lg gabt-font-bold" style="margin: 0;">
                        ⚙️ Impostazioni Gestione Accessi
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: var(--gabt-gray-600);">
                        Configura la connessione al servizio Alloggiati Web e i dati della struttura
                    </p>
                </div>
                <div class="gabt-flex gabt-gap-4">
                    <button type="button" id="gabt-test-modern" class="gabt-btn gabt-btn--secondary gabt-btn--sm">
                        🧪 Test API Moderne
                    </button>
                    <button type="button" id="gabt-test-connection" class="gabt-btn gabt-btn--primary gabt-btn--sm">
                        🔗 Test Connessione
                    </button>
                </div>
            </div>

            <?php if (!empty($vars['message'])): ?>
                <div class="gabt-notification gabt-notification--<?= $vars['message_type'] === 'error' ? 'error' : 'success' ?> gabt-mb-6" style="position: relative; top: auto; right: auto;">
                    <div class="gabt-notification__content">
                        <div class="gabt-notification__icon">
                            <?= $vars['message_type'] === 'error' ? '❌' : '✅' ?>
                        </div>
                        <div class="gabt-notification__message">
                            <?= esc_html($vars['message']) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form id="gabt-settings-form" method="post" class="gabt-grid gabt-grid-2">
                <?php wp_nonce_field('gabt_settings', 'gabt_nonce'); ?>
                
                <!-- Sezione Alloggiati Web -->
                <div class="gabt-card">
                    <div class="gabt-card__header">
                        <h2>🏛️ Servizio Alloggiati Web</h2>
                    </div>
                    <div class="gabt-card__content">
                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="alloggiati_username">
                                Username *
                            </label>
                            <input 
                                type="text" 
                                id="alloggiati_username" 
                                name="alloggiati_username" 
                                value="<?= esc_attr($settings['alloggiati']['username'] ?? '') ?>"
                                class="gabt-form__input"
                                placeholder="Il tuo username Alloggiati Web"
                                required
                            />
                            <div class="gabt-form__help">
                                Username fornito dalla Questura per l'accesso al servizio
                            </div>
                        </div>

                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="alloggiati_password">
                                Password *
                            </label>
                            <input 
                                type="password" 
                                id="alloggiati_password" 
                                name="alloggiati_password" 
                                value="<?= esc_attr($settings['alloggiati']['password'] ?? '') ?>"
                                class="gabt-form__input"
                                placeholder="La tua password"
                                required
                            />
                        </div>

                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="alloggiati_ws_key">
                                Web Service Key *
                            </label>
                            <input 
                                type="text" 
                                id="alloggiati_ws_key" 
                                name="alloggiati_ws_key" 
                                value="<?= esc_attr($settings['alloggiati']['ws_key'] ?? '') ?>"
                                class="gabt-form__input"
                                placeholder="Chiave WS fornita dalla Polizia"
                                required
                            />
                            <div class="gabt-form__help">
                                Chiave del web service fornita dalla Polizia di Stato
                            </div>
                        </div>

                        <div class="gabt-form__group">
                            <div class="gabt-checkbox">
                                <input 
                                    type="checkbox" 
                                    id="alloggiati_auto_send" 
                                    name="alloggiati_auto_send" 
                                    value="1"
                                    <?= checked($settings['alloggiati']['auto_send'] ?? 0, 1, false) ?>
                                />
                                <label for="alloggiati_auto_send">
                                    Abilita invio automatico schedine
                                </label>
                            </div>
                        </div>

                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="alloggiati_send_time">
                                Orario Invio Automatico
                            </label>
                            <input 
                                type="time" 
                                id="alloggiati_send_time" 
                                name="alloggiati_send_time" 
                                value="<?= esc_attr($settings['alloggiati']['send_time'] ?? '02:00') ?>"
                                class="gabt-form__input"
                            />
                            <div class="gabt-form__help">
                                Orario giornaliero per l'invio automatico delle schedine
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sezione Struttura -->
                <div class="gabt-card">
                    <div class="gabt-card__header">
                        <h2>🏨 Dati Struttura Ricettiva</h2>
                    </div>
                    <div class="gabt-card__content">
                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="accommodation_name">
                                Nome Struttura *
                            </label>
                            <input 
                                type="text" 
                                id="accommodation_name" 
                                name="accommodation_name" 
                                value="<?= esc_attr($settings['accommodation']['name'] ?? get_bloginfo('name')) ?>"
                                class="gabt-form__input"
                                placeholder="Nome della tua struttura"
                                required
                            />
                        </div>

                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="accommodation_address">
                                Indirizzo Completo
                            </label>
                            <textarea 
                                id="accommodation_address" 
                                name="accommodation_address" 
                                rows="3"
                                class="gabt-form__input"
                                placeholder="Via, numero civico, CAP, città, provincia"
                            ><?= esc_textarea($settings['accommodation']['address'] ?? '') ?></textarea>
                        </div>

                        <div class="gabt-grid gabt-grid-2">
                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="accommodation_phone">
                                    Telefono
                                </label>
                                <input 
                                    type="tel" 
                                    id="accommodation_phone" 
                                    name="accommodation_phone" 
                                    value="<?= esc_attr($settings['accommodation']['phone'] ?? '') ?>"
                                    class="gabt-form__input"
                                    placeholder="+39 075 123456"
                                />
                            </div>

                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="accommodation_email">
                                    Email Struttura
                                </label>
                                <input 
                                    type="email" 
                                    id="accommodation_email" 
                                    name="accommodation_email" 
                                    value="<?= esc_attr($settings['accommodation']['email'] ?? get_option('admin_email')) ?>"
                                    class="gabt-form__input"
                                    placeholder="info@struttura.it"
                                />
                            </div>
                        </div>

                        <div class="gabt-grid gabt-grid-2">
                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="accommodation_comune">
                                    Comune
                                </label>
                                <input 
                                    type="text" 
                                    id="accommodation_comune" 
                                    name="accommodation_comune" 
                                    value="<?= esc_attr($settings['accommodation']['comune'] ?? '') ?>"
                                    class="gabt-form__input"
                                    placeholder="Es. Castiglione del Lago"
                                />
                            </div>

                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="accommodation_provincia">
                                    Provincia
                                </label>
                                <input 
                                    type="text" 
                                    id="accommodation_provincia" 
                                    name="accommodation_provincia" 
                                    value="<?= esc_attr($settings['accommodation']['provincia'] ?? '') ?>"
                                    class="gabt-form__input"
                                    placeholder="PG"
                                    maxlength="2"
                                    style="text-transform: uppercase;"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sezione Email -->
                <div class="gabt-card">
                    <div class="gabt-card__header">
                        <h2>📧 Configurazione Email</h2>
                    </div>
                    <div class="gabt-card__content">
                        <div class="gabt-grid gabt-grid-2">
                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="email_from_name">
                                    Nome Mittente
                                </label>
                                <input 
                                    type="text" 
                                    id="email_from_name" 
                                    name="email_from_name" 
                                    value="<?= esc_attr($settings['email']['from_name'] ?? get_bloginfo('name')) ?>"
                                    class="gabt-form__input"
                                />
                            </div>

                            <div class="gabt-form__group">
                                <label class="gabt-form__label" for="email_from_email">
                                    Email Mittente
                                </label>
                                <input 
                                    type="email" 
                                    id="email_from_email" 
                                    name="email_from_email" 
                                    value="<?= esc_attr($settings['email']['from_email'] ?? get_option('admin_email')) ?>"
                                    class="gabt-form__input"
                                />
                            </div>
                        </div>

                        <div class="gabt-form__group">
                            <div class="gabt-checkbox">
                                <input 
                                    type="checkbox" 
                                    id="email_admin_notifications" 
                                    name="email_admin_notifications" 
                                    value="1"
                                    <?= checked($settings['email']['admin_notifications'] ?? 1, 1, false) ?>
                                />
                                <label for="email_admin_notifications">
                                    Ricevi notifiche email per nuove prenotazioni e invii schedine
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sezione Avanzate -->
                <div class="gabt-card">
                    <div class="gabt-card__header">
                        <h2>🛠️ Impostazioni Avanzate</h2>
                    </div>
                    <div class="gabt-card__content">
                        <div class="gabt-form__group">
                            <div class="gabt-checkbox">
                                <input 
                                    type="checkbox" 
                                    id="debug_mode" 
                                    name="debug_mode" 
                                    value="1"
                                    <?= checked($settings['general']['debug_mode'] ?? 0, 1, false) ?>
                                />
                                <label for="debug_mode">
                                    Modalità debug (genera log dettagliati)
                                </label>
                            </div>
                            <div class="gabt-form__help">
                                Attiva solo se richiesto dal supporto tecnico
                            </div>
                        </div>

                        <div class="gabt-form__group">
                            <label class="gabt-form__label" for="log_retention_days">
                                Giorni Conservazione Log
                            </label>
                            <input 
                                type="number" 
                                id="log_retention_days" 
                                name="log_retention_days" 
                                value="<?= esc_attr($settings['general']['log_retention_days'] ?? 30) ?>"
                                class="gabt-form__input"
                                min="1" 
                                max="365"
                                style="max-width: 100px;"
                            />
                            <div class="gabt-form__help">
                                I log più vecchi verranno eliminati automaticamente
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Pulsanti Azione -->
            <div class="gabt-flex gabt-justify-between gabt-items-center gabt-mt-8">
                <div class="gabt-flex gabt-gap-4">
                    <button 
                        type="submit" 
                        form="gabt-settings-form"
                        name="save_settings"
                        class="gabt-btn gabt-btn--primary gabt-btn--lg"
                    >
                        💾 Salva Impostazioni
                    </button>
                    <button 
                        type="button" 
                        id="gabt-reset-settings"
                        class="gabt-btn gabt-btn--secondary"
                    >
                        🔄 Reset
                    </button>
                </div>
                
                <div class="gabt-text-sm" style="color: var(--gabt-gray-500);">
                    Ultima modifica: <?= date('d/m/Y H:i') ?>
                </div>
            </div>

            <!-- Statistiche Sistema -->
            <div class="gabt-card gabt-mt-8">
                <div class="gabt-card__header">
                    <h2>📊 Stato Sistema</h2>
                </div>
                <div class="gabt-card__content">
                    <div class="gabt-stats-grid">
                        <div class="gabt-stat-card gabt-stat-card--primary">
                            <div class="gabt-stat-card__title">Plugin</div>
                            <div class="gabt-stat-card__value"><?= GABT_VERSION ?></div>
                        </div>
                        
                        <div class="gabt-stat-card gabt-stat-card--success">
                            <div class="gabt-stat-card__title">WordPress</div>
                            <div class="gabt-stat-card__value"><?= get_bloginfo('version') ?></div>
                        </div>
                        
                        <div class="gabt-stat-card gabt-stat-card--info">
                            <div class="gabt-stat-card__title">PHP</div>
                            <div class="gabt-stat-card__value"><?= PHP_VERSION ?></div>
                        </div>
                        
                        <div class="gabt-stat-card gabt-stat-card--warning">
                            <div class="gabt-stat-card__title">API Status</div>
                            <div class="gabt-stat-card__value">
                                <span id="gabt-api-status">🔄</span>
                            </div>
                        </div>
                    </div>

                    <details style="margin-top: var(--gabt-space-6);">
                        <summary style="cursor: pointer; font-weight: 500;">
                            🔍 Informazioni Debug
                        </summary>
                        <div style="margin-top: var(--gabt-space-4); padding: var(--gabt-space-4); background: var(--gabt-gray-50); border-radius: var(--gabt-radius); font-family: var(--gabt-font-mono); font-size: 0.75rem;">
                            <strong>Plugin Path:</strong> <?= esc_html(GABT_PLUGIN_PATH) ?><br>
                            <strong>Plugin URL:</strong> <?= esc_html(GABT_PLUGIN_URL) ?><br>
                            <strong>REST API Base:</strong> <?= rest_url('gabt/v1') ?><br>
                            <strong>Current User:</strong> <?= get_current_user_id() ?> (<?= current_user_can('manage_options') ? 'Admin' : 'User' ?>)<br>
                            <strong>Environment:</strong> <?= wp_get_environment_type() ?><br>
                            <strong>Debug Mode:</strong> <?= defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled' ?><br>
                            <strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?><br>
                            <strong>Max Execution Time:</strong> <?= ini_get('max_execution_time') ?>s
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <script>
        // Test stato API al caricamento della pagina
        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('gabt-api-status');
            
            if (typeof window.gabtApp !== 'undefined') {
                // Test rapido delle API
                window.gabtApp.apiCall('GET', '/settings')
                    .then(() => {
                        statusEl.textContent = '✅';
                        statusEl.title = 'API funzionanti';
                    })
                    .catch(() => {
                        statusEl.textContent = '❌';
                        statusEl.title = 'Problemi API';
                    });
            }
        });
        </script>
        <?php
    }

    /**
     * Template di fallback quando il file non esiste
     */
    private function render_fallback_template($template_name, $vars = array()) {
        echo '<div class="wrap">';
        echo '<h1>' . ucfirst(str_replace('-', ' ', $template_name)) . '</h1>';
        echo '<div class="notice notice-warning">';
        echo '<p>Template "' . esc_html($template_name) . '.php" non trovato. Utilizzando fallback.</p>';
        echo '</div>';
        
        switch ($template_name) {
            case 'dashboard':
                $this->render_dashboard_fallback($vars);
                break;
                
            case 'new-booking':
                $this->render_new_booking_fallback();
                break;
                
            case 'settings':
                $this->render_settings_fallback($vars);
                break;
                
            case 'test-connection':
                $this->render_test_fallback($vars);
                break;
                
            default:
                echo '<p>Pagina ' . esc_html($template_name) . ' non implementata.</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Dashboard fallback
     */
    private function render_dashboard_fallback($vars) {
        echo '<h2>Dashboard</h2>';
        echo '<p>Plugin attivo e funzionante!</p>';
        
        $stats = $vars['stats'] ?? array();
        if (!empty($stats)) {
            echo '<h3>Statistiche</h3>';
            echo '<ul>';
            echo '<li>Prenotazioni totali: ' . ($stats['total'] ?? 0) . '</li>';
            echo '<li>Questo mese: ' . ($stats['this_month'] ?? 0) . '</li>';
            echo '<li>Schedine inviate: ' . ($stats['schedine_sent'] ?? 0) . '</li>';
            echo '</ul>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=gestione-accessi-bt-settings') . '" class="button button-primary">Vai alle Impostazioni</a></p>';
    }
    
    /**
     * Nuova prenotazione fallback
     */
    private function render_new_booking_fallback() {
        echo '<h2>Nuova Prenotazione</h2>';
        echo '<form method="post">';
        wp_nonce_field('gabt_new_booking', 'gabt_nonce');
        
        echo '<table class="form-table">';
        echo '<tr><th><label for="checkin_date">Data Check-in</label></th>';
        echo '<td><input type="date" id="checkin_date" name="checkin_date" required></td></tr>';
        
        echo '<tr><th><label for="checkout_date">Data Check-out</label></th>';
        echo '<td><input type="date" id="checkout_date" name="checkout_date" required></td></tr>';
        
        echo '<tr><th><label for="adults">Adulti</label></th>';
        echo '<td><input type="number" id="adults" name="adults" min="1" value="1" required></td></tr>';
        
        echo '<tr><th><label for="children">Bambini</label></th>';
        echo '<td><input type="number" id="children" name="children" min="0" value="0"></td></tr>';
        echo '</table>';
        
        submit_button('Crea Prenotazione', 'primary', 'save_booking');
        echo '</form>';
    }
    
    /**
     * Impostazioni fallback
     */
    private function render_settings_fallback($vars) {
        echo '<h2>Impostazioni</h2>';
        echo '<form method="post">';
        wp_nonce_field('gabt_settings', 'gabt_nonce');
        
        $settings = $vars['settings'] ?? array();
        
        echo '<table class="form-table">';
        echo '<tr><th><label for="alloggiati_username">Username Alloggiati</label></th>';
        echo '<td><input type="text" id="alloggiati_username" name="alloggiati_username" value="' . esc_attr($settings['alloggiati']['username'] ?? '') . '"></td></tr>';
        
        echo '<tr><th><label for="alloggiati_password">Password Alloggiati</label></th>';
        echo '<td><input type="password" id="alloggiati_password" name="alloggiati_password" value="' . esc_attr($settings['alloggiati']['password'] ?? '') . '"></td></tr>';
        
        echo '<tr><th><label for="alloggiati_ws_key">WS Key</label></th>';
        echo '<td><input type="text" id="alloggiati_ws_key" name="alloggiati_ws_key" value="' . esc_attr($settings['alloggiati']['ws_key'] ?? '') . '"></td></tr>';
        echo '</table>';
        
        submit_button('Salva Impostazioni', 'primary', 'save_settings');
        echo '</form>';
    }
    
    /**
     * Test connessione fallback
     */
    private function render_test_fallback($vars) {
        echo '<h2>Test Connessione</h2>';
        
        $test_result = $vars['test_result'] ?? null;
        if ($test_result) {
            $class = $test_result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $class . '"><p>' . esc_html($test_result['message']) . '</p></div>';
        }
        
        echo '<form method="post">';
        wp_nonce_field('gabt_test', 'gabt_nonce');
        submit_button('Testa Connessione', 'primary', 'test_connection');
        echo '</form>';
    }
    
    /**
     * Processa una nuova prenotazione
     */
    private function process_new_booking() {
        if (!$this->booking_repository) {
            return new WP_Error('no_repository', 'Repository prenotazioni non disponibile');
        }
        
        // Sanitizza e valida i dati
        $booking_data = array(
            'checkin_date' => sanitize_text_field($_POST['checkin_date'] ?? ''),
            'checkout_date' => sanitize_text_field($_POST['checkout_date'] ?? ''),
            'adults' => intval($_POST['adults'] ?? 1),
            'children' => intval($_POST['children'] ?? 0)
        );
        
        // Calcola notti e ospiti totali
        if (!empty($booking_data['checkin_date']) && !empty($booking_data['checkout_date'])) {
            $checkin = new DateTime($booking_data['checkin_date']);
            $checkout = new DateTime($booking_data['checkout_date']);
            $booking_data['nights'] = $checkout->diff($checkin)->days;
        } else {
            $booking_data['nights'] = 1;
        }
        
        $booking_data['total_guests'] = $booking_data['adults'] + $booking_data['children'];
        
        // Validazioni
        if (empty($booking_data['checkin_date']) || empty($booking_data['checkout_date'])) {
            return new WP_Error('missing_dates', 'Date di check-in e check-out sono obbligatorie');
        }
        
        if ($booking_data['nights'] <= 0) {
            return new WP_Error('invalid_nights', 'Numero di notti non valido');
        }
        
        if ($booking_data['total_guests'] <= 0) {
            return new WP_Error('invalid_guests', 'Numero di ospiti non valido');
        }
        
        // Crea la prenotazione
        try {
            $booking_id = $this->booking_repository->create_booking($booking_data);
            
            if (is_wp_error($booking_id)) {
                return $booking_id;
            }
            
            // Ottiene il codice prenotazione generato
            $booking = $this->booking_repository->get_booking($booking_id);
            
            return array(
                'booking_id' => $booking_id,
                'booking_code' => $booking ? $booking->booking_code : 'N/A'
            );
            
        } catch (Exception $e) {
            return new WP_Error('create_error', 'Errore durante la creazione: ' . $e->getMessage());
        }
    }
    
    /**
     * Ottiene impostazioni di default
     */
    private function get_default_settings() {
        return array(
            'alloggiati' => array(
                'username' => '',
                'password' => '',
                'ws_key' => '',
                'auto_send' => 0,
                'send_time' => '02:00'
            ),
            'accommodation' => array(
                'name' => get_bloginfo('name'),
                'address' => '',
                'phone' => '',
                'email' => get_option('admin_email'),
                'comune' => '',
                'provincia' => ''
            ),
            'email' => array(
                'from_name' => get_bloginfo('name'),
                'from_email' => get_option('admin_email'),
                'guest_subject' => 'Registrazione completata',
                'guest_template' => 'Gentile ospite, la registrazione è stata completata.',
                'admin_notifications' => 1,
                'admin_email' => get_option('admin_email')
            ),
            'general' => array(
                'debug_mode' => 0,
                'log_retention_days' => 30,
                'require_document_date' => 0
            )
        );
    }
}