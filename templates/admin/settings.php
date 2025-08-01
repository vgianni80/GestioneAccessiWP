<?php
/**
 * Template impostazioni admin
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p class="description">Configura le impostazioni del plugin per il corretto funzionamento</p>
    
    <div class="gabt-admin-container">
        <div class="gabt-page-header">
            <h1>Impostazioni</h1>
            <p class="description">Configura il plugin per il corretto funzionamento</p>

    <form id="gabt-settings-form" method="post">
        <?php wp_nonce_field('gabt_settings', 'gabt_nonce'); ?>

        <!-- Impostazioni Alloggiati Web -->
        <div class="gabt-settings-section">
            <h3>🔐 Configurazione Alloggiati Web</h3>
            <div class="section-content">
                <div class="gabt-form-field">
                    <label for="alloggiati_username">Username <span class="required">*</span></label>
                    <input type="text" id="alloggiati_username" name="alloggiati_username" 
                           value="<?php echo esc_attr($settings['alloggiati']['username']); ?>" required>
                    <div class="description">Username fornito dalla Polizia di Stato per l'accesso al servizio</div>
                </div>

                <div class="gabt-form-field">
                    <label for="alloggiati_password">Password <span class="required">*</span></label>
                    <input type="password" id="alloggiati_password" name="alloggiati_password" 
                           value="<?php echo esc_attr($settings['alloggiati']['password']); ?>" required>
                    <div class="description">Password associata all'username</div>
                </div>

                <div class="gabt-form-field">
                    <label for="alloggiati_ws_key">WS Key <span class="required">*</span></label>
                    <input type="text" id="alloggiati_ws_key" name="alloggiati_ws_key" 
                           value="<?php echo esc_attr($settings['alloggiati']['ws_key']); ?>" required>
                    <div class="description">Chiave del web service fornita dalla Polizia di Stato</div>
                </div>

                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label>
                            <input type="checkbox" name="alloggiati_auto_send" value="1" 
                                   <?php checked($settings['alloggiati']['auto_send'], 1); ?>>
                            Invio automatico schedine
                        </label>
                        <div class="description">Abilita l'invio automatico giornaliero delle schedine</div>
                    </div>

                    <div class="gabt-form-field">
                        <label for="alloggiati_send_time">Orario invio automatico</label>
                        <input type="time" id="alloggiati_send_time" name="alloggiati_send_time" 
                               value="<?php echo esc_attr($settings['alloggiati']['send_time']); ?>">
                        <div class="description">Orario giornaliero per l'invio automatico</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Impostazioni Struttura -->
        <div class="gabt-settings-section">
            <h3>🏨 Informazioni Struttura Ricettiva</h3>
            <div class="section-content">
                <div class="gabt-form-field">
                    <label for="accommodation_name">Nome Struttura</label>
                    <input type="text" id="accommodation_name" name="accommodation_name" 
                           value="<?php echo esc_attr($settings['accommodation']['name']); ?>">
                </div>

                <div class="gabt-form-field">
                    <label for="accommodation_address">Indirizzo Completo</label>
                    <textarea id="accommodation_address" name="accommodation_address" rows="3"><?php echo esc_textarea($settings['accommodation']['address']); ?></textarea>
                </div>

                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label for="accommodation_phone">Telefono</label>
                        <input type="tel" id="accommodation_phone" name="accommodation_phone" 
                               value="<?php echo esc_attr($settings['accommodation']['phone']); ?>">
                    </div>

                    <div class="gabt-form-field">
                        <label for="accommodation_email">Email</label>
                        <input type="email" id="accommodation_email" name="accommodation_email" 
                               value="<?php echo esc_attr($settings['accommodation']['email']); ?>">
                    </div>
                </div>

                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label for="accommodation_comune">Comune</label>
                        <input type="text" id="accommodation_comune" name="accommodation_comune" 
                               value="<?php echo esc_attr($settings['accommodation']['comune']); ?>">
                    </div>

                    <div class="gabt-form-field">
                        <label for="accommodation_provincia">Provincia</label>
                        <input type="text" id="accommodation_provincia" name="accommodation_provincia" 
                               value="<?php echo esc_attr($settings['accommodation']['provincia']); ?>" maxlength="2">
                    </div>
                </div>
            </div>
        </div>

        <!-- Impostazioni Email -->
        <div class="gabt-settings-section">
            <h3>📧 Configurazione Email</h3>
            <div class="section-content">
                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label for="email_from_name">Nome Mittente</label>
                        <input type="text" id="email_from_name" name="email_from_name" 
                               value="<?php echo esc_attr($settings['email']['from_name']); ?>">
                    </div>

                    <div class="gabt-form-field">
                        <label for="email_from_email">Email Mittente</label>
                        <input type="email" id="email_from_email" name="email_from_email" 
                               value="<?php echo esc_attr($settings['email']['from_email']); ?>">
                    </div>
                </div>

                <div class="gabt-form-field">
                    <label for="email_guest_subject">Oggetto Email Ospiti</label>
                    <input type="text" id="email_guest_subject" name="email_guest_subject" 
                           value="<?php echo esc_attr($settings['email']['guest_subject']); ?>">
                </div>

                <div class="gabt-form-field">
                    <label for="email_guest_template">Template Email Ospiti</label>
                    <textarea id="email_guest_template" name="email_guest_template" rows="8"><?php echo esc_textarea($settings['email']['guest_template']); ?></textarea>
                    <div class="description">
                        Variabili disponibili: {guest_name}, {booking_code}, {checkin_date}, {checkout_date}, {nights}, {accommodation_name}
                    </div>
                </div>

                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label>
                            <input type="checkbox" name="email_admin_notifications" value="1" 
                                   <?php checked($settings['email']['admin_notifications'], 1); ?>>
                            Notifiche admin via email
                        </label>
                    </div>

                    <div class="gabt-form-field">
                        <label for="email_admin_email">Email Admin</label>
                        <input type="email" id="email_admin_email" name="email_admin_email" 
                               value="<?php echo esc_attr($settings['email']['admin_email']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Impostazioni Generali -->
        <div class="gabt-settings-section">
            <h3>⚙️ Impostazioni Generali</h3>
            <div class="section-content">
                <div class="gabt-form-row">
                    <div class="gabt-form-field">
                        <label>
                            <input type="checkbox" name="debug_mode" value="1" 
                                   <?php checked($settings['general']['debug_mode'], 1); ?>>
                            Modalità Debug
                        </label>
                        <div class="description">Abilita logging dettagliato per troubleshooting</div>
                    </div>

                    <div class="gabt-form-field">
                        <label for="log_retention_days">Giorni Conservazione Log</label>
                        <input type="number" id="log_retention_days" name="log_retention_days" 
                               value="<?php echo esc_attr($settings['general']['log_retention_days']); ?>" min="1" max="365">
                        <div class="description">Numero di giorni di conservazione dei file di log</div>
                    </div>
                </div>

                <div class="gabt-form-field">
                    <label>
                        <input type="checkbox" name="require_document_date" value="1" 
                               <?php checked($settings['general']['require_document_date'], 1); ?>>
                        Richiedi data rilascio documento
                    </label>
                    <div class="description">Rende obbligatorio il campo data rilascio documento nei form ospiti</div>
                </div>
            </div>
        </div>

        <div class="gabt-form-actions">
            <button type="submit" name="save_settings" class="gabt-btn gabt-btn-primary">
                Salva Impostazioni
            </button>
            
            <button type="button" class="gabt-btn gabt-btn-secondary" onclick="location.reload()">
                Annulla
            </button>
        </div>
    </form>
    </div> <!-- .gabt-admin-container -->
</div> <!-- .wrap -->
