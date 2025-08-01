<?php
/**
 * Template dashboard admin
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
    <p class="description">Panoramica generale delle prenotazioni e stato del sistema</p>
    
    <div class="gabt-admin-container">

    <!-- Cards statistiche -->
    <div class="gabt-dashboard-cards">
        <div class="gabt-card">
            <h3>Prenotazioni Totali</h3>
            <span class="stat-number"><?php echo $stats['total'] ?? 0; ?></span>
            <span class="stat-label">Tutte le prenotazioni</span>
        </div>

        <div class="gabt-card">
            <h3>Questo Mese</h3>
            <span class="stat-number"><?php echo $stats['this_month'] ?? 0; ?></span>
            <span class="stat-label">Prenotazioni correnti</span>
        </div>

        <div class="gabt-card">
            <h3>Schedine Inviate</h3>
            <span class="stat-number"><?php echo $stats['schedine_sent'] ?? 0; ?></span>
            <span class="stat-label">Invii completati</span>
        </div>

        <div class="gabt-card">
            <h3>In Attesa</h3>
            <span class="stat-number"><?php echo $stats['by_status']['pending'] ?? 0; ?></span>
            <span class="stat-label">Da processare</span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
        <!-- Prenotazioni recenti -->
        <div class="gabt-table">
            <h3 style="padding: 15px 20px; margin: 0; background: #f6f7f7; border-bottom: 1px solid #ccd0d4;">
                Prenotazioni Recenti
            </h3>
            
            <?php if (!empty($recent_bookings)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Codice</th>
                        <th>Check-in</th>
                        <th>Ospiti</th>
                        <th>Status</th>
                        <th>Schedine</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                    <tr>
                        <td><strong><?php echo esc_html($booking->booking_code); ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($booking->checkin_date)); ?></td>
                        <td><?php echo $booking->total_guests; ?></td>
                        <td>
                            <span class="gabt-status-badge <?php echo $booking->status; ?>">
                                <?php 
                                $statuses = ['pending' => 'In Attesa', 'confirmed' => 'Confermata', 'completed' => 'Completata', 'cancelled' => 'Annullata'];
                                echo $statuses[$booking->status] ?? $booking->status; 
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($booking->schedine_sent): ?>
                                <span style="color: #28a745;">✓ Inviate</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">✗ Non inviate</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-booking-details', ['id' => $booking->id]); ?>" 
                               class="gabt-btn gabt-btn-small gabt-btn-primary">Dettagli</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="padding: 20px; text-align: center; color: #666;">
                <p>Nessuna prenotazione trovata</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Azioni rapide -->
        <div class="gabt-card">
            <h3>Azioni Rapide</h3>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-new-booking'); ?>" 
                   class="gabt-btn gabt-btn-primary">
                    ➕ Nuova Prenotazione
                </a>
                
                <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-manage-bookings'); ?>" 
                   class="gabt-btn gabt-btn-secondary">
                    📋 Gestisci Prenotazioni
                </a>
                
                <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-test'); ?>" 
                   class="gabt-btn gabt-btn-secondary">
                    🔧 Test Connessione
                </a>
                
                <a href="<?php echo GABT_Admin_Menu::get_admin_url('gestione-accessi-bt-settings'); ?>" 
                   class="gabt-btn gabt-btn-secondary">
                    ⚙️ Impostazioni
                </a>
            </div>
        </div>
    </div>

    <!-- Status sistema -->
    <div class="gabt-card" style="margin-top: 20px;">
        <h3>Stato Sistema</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <?php
            $settings_manager = new GABT_Settings_Manager();
            $alloggiati_configured = $settings_manager->are_alloggiati_settings_complete();
            ?>
            
            <div>
                <strong>Configurazione Alloggiati Web:</strong><br>
                <?php if ($alloggiati_configured): ?>
                    <span style="color: #28a745;">✓ Configurato</span>
                <?php else: ?>
                    <span style="color: #dc3545;">✗ Non configurato</span>
                <?php endif; ?>
            </div>
            
            <div>
                <strong>Versione Plugin:</strong><br>
                <span><?php echo GABT_VERSION; ?></span>
            </div>
            
            <div>
                <strong>Database:</strong><br>
                <?php
                $db_manager = new GABT_Database_Manager();
                if ($db_manager->tables_exist()): ?>
                    <span style="color: #28a745;">✓ Tabelle OK</span>
                <?php else: ?>
                    <span style="color: #dc3545;">✗ Errore tabelle</span>
                <?php endif; ?>
            </div>
            
            <div>
                <strong>Cron Jobs:</strong><br>
                <?php if (wp_next_scheduled('gabt_daily_schedine_send')): ?>
                    <span style="color: #28a745;">✓ Attivi</span>
                <?php else: ?>
                    <span style="color: #dc3545;">✗ Non attivi</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div> <!-- .gabt-admin-container -->
</div> <!-- .wrap -->
