<?php
/**
 * Template per test connessione Alloggiati Web
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
    
    <div class="gabt-test-connection">
        <div class="card">
            <h2>Test Connessione Alloggiati Web</h2>
            <p>Verifica la connessione al servizio Alloggiati Web della Polizia di Stato.</p>
            
            <div class="gabt-test-results" id="gabt-test-results" style="display: none;">
                <div class="notice notice-info">
                    <p><strong>Test in corso...</strong></p>
                </div>
            </div>
            
            <div class="gabt-test-actions">
                <button type="button" class="button button-primary" id="gabt-test-connection-btn">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Testa Connessione
                </button>
                
                <button type="button" class="button button-secondary" id="gabt-test-auth-btn">
                    <span class="dashicons dashicons-lock"></span>
                    Testa Autenticazione
                </button>
                
                <button type="button" class="button button-secondary" id="gabt-download-tables-btn">
                    <span class="dashicons dashicons-download"></span>
                    Scarica Tabelle
                </button>
            </div>
        </div>
        
        <div class="card">
            <h2>Configurazione Attuale</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Username</th>
                    <td>
                        <?php 
                        $username = isset($settings['alloggiati']['username']) ? $settings['alloggiati']['username'] : '';
                        echo $username ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Configurato' : '<span class="dashicons dashicons-warning" style="color: orange;"></span> Non configurato';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Password</th>
                    <td>
                        <?php 
                        $password = isset($settings['alloggiati']['password']) ? $settings['alloggiati']['password'] : '';
                        echo $password ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Configurata' : '<span class="dashicons dashicons-warning" style="color: orange;"></span> Non configurata';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">WS Key</th>
                    <td>
                        <?php 
                        $ws_key = isset($settings['alloggiati']['ws_key']) ? $settings['alloggiati']['ws_key'] : '';
                        echo $ws_key ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Configurata' : '<span class="dashicons dashicons-warning" style="color: orange;"></span> Non configurata';
                        ?>
                    </td>
                </tr>
            </table>
            
            <?php if (empty($username) || empty($password) || empty($ws_key)): ?>
            <div class="notice notice-warning">
                <p><strong>Attenzione:</strong> Alcune credenziali non sono configurate. 
                   <a href="<?php echo admin_url('admin.php?page=gestione-accessi-bt-settings'); ?>">Vai alle Impostazioni</a> per configurarle.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Log Test</h2>
            <div id="gabt-test-log" class="gabt-test-log">
                <p><em>Nessun test eseguito ancora.</em></p>
            </div>
        </div>
    </div>
</div>

<style>
.gabt-test-connection .card {
    margin-bottom: 20px;
    padding: 20px;
}

.gabt-test-actions {
    margin-top: 15px;
}

.gabt-test-actions .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.gabt-test-actions .dashicons {
    margin-right: 5px;
}

.gabt-test-results {
    margin: 15px 0;
}

.gabt-test-log {
    background: #f9f9f9;
    border: 1px solid #ddd;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

.gabt-test-log .log-entry {
    margin-bottom: 5px;
    padding: 5px;
    border-left: 3px solid #ccc;
    padding-left: 10px;
}

.gabt-test-log .log-success {
    border-left-color: #46b450;
    background: #f0f8f0;
}

.gabt-test-log .log-error {
    border-left-color: #dc3232;
    background: #fdf0f0;
}

.gabt-test-log .log-warning {
    border-left-color: #ffb900;
    background: #fffbf0;
}

.gabt-test-log .log-info {
    border-left-color: #00a0d2;
    background: #f0f8ff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const testConnectionBtn = document.getElementById('gabt-test-connection-btn');
    const testAuthBtn = document.getElementById('gabt-test-auth-btn');
    const downloadTablesBtn = document.getElementById('gabt-download-tables-btn');
    const testResults = document.getElementById('gabt-test-results');
    const testLog = document.getElementById('gabt-test-log');
    
    function addLogEntry(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.className = `log-entry log-${type}`;
        entry.innerHTML = `<strong>[${timestamp}]</strong> ${message}`;
        
        if (testLog.querySelector('em')) {
            testLog.innerHTML = '';
        }
        
        testLog.appendChild(entry);
        testLog.scrollTop = testLog.scrollHeight;
    }
    
    function showResults(message, type = 'info') {
        testResults.style.display = 'block';
        testResults.innerHTML = `<div class="notice notice-${type}"><p>${message}</p></div>`;
    }
    
    async function performTest(action, button) {
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="dashicons dashicons-update spin"></span> Test in corso...';
        
        addLogEntry(`Avvio test: ${action}`, 'info');
        showResults('Test in corso...', 'info');
        
        try {
            const formData = new FormData();
            formData.append('action', 'gabt_test_connection');
            formData.append('test_type', action);
            formData.append('nonce', gabAdmin.nonce);
            
            const response = await fetch(gabAdmin.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                addLogEntry(data.data.message || 'Test completato con successo', 'success');
                showResults(data.data.message || 'Test completato con successo', 'success');
                
                if (data.data.details) {
                    addLogEntry(`Dettagli: ${data.data.details}`, 'info');
                }
            } else {
                addLogEntry(data.data || 'Errore durante il test', 'error');
                showResults(data.data || 'Errore durante il test', 'error');
            }
        } catch (error) {
            addLogEntry(`Errore di rete: ${error.message}`, 'error');
            showResults(`Errore di rete: ${error.message}`, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }
    
    if (testConnectionBtn) {
        testConnectionBtn.addEventListener('click', function() {
            performTest('connection', this);
        });
    }
    
    if (testAuthBtn) {
        testAuthBtn.addEventListener('click', function() {
            performTest('authentication', this);
        });
    }
    
    if (downloadTablesBtn) {
        downloadTablesBtn.addEventListener('click', function() {
            performTest('download_tables', this);
        });
    }
});
</script>

<style>
.dashicons.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
