<?php
/**
 * Script per correggere automaticamente i problemi di output inatteso
 * Salva questo file come fix-output.php nella root del plugin e eseguilo via browser
 * ATTENZIONE: Questo script modifica i tuoi file! Fai un backup prima di eseguirlo.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔧 Fix Output Inatteso - Gestione Accessi BT\n";
echo str_repeat("=", 60) . "\n\n";

// Lista di tutti i file PHP del plugin da correggere
$files_to_fix = [
    'gestione-accessi-bt.php',
    'includes/class-plugin-core.php',
    'includes/class-activation-handler.php',
    'includes/admin/class-admin-menu.php',
    'includes/admin/class-admin-pages.php',
    'includes/admin/class-settings-manager.php',
    'includes/ajax/class-ajax-handlers.php',
    'includes/cron/class-cron-manager.php',
    'includes/database/class-database-manager.php',
    'includes/database/class-booking-repository.php',
    'includes/frontend/class-frontend-handler.php',
    'includes/frontend/class-guest-forms.php',
    'includes/services/class-alloggiati-client.php',
    'includes/services/class-xml-parser.php',
    'includes/services/class-schedule-formatter.php'
];

$total_fixes = 0;

foreach ($files_to_fix as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "⚠️  File non trovato: $file\n";
        continue;
    }
    
    echo "🔧 Correggendo: $file\n";
    
    // Leggi il contenuto del file
    $original_content = file_get_contents($full_path);
    $content = $original_content;
    $fixes_applied = [];
    
    // 1. Rimuovi BOM se presente
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
        $fixes_applied[] = "Rimosso BOM";
    }
    
    // 2. Rimuovi spazi prima di <?php
    if (preg_match('/^(\s+)<\?php/', $content, $matches)) {
        $content = preg_replace('/^(\s+)<\?php/', '<?php', $content);
        $fixes_applied[] = "Rimossi spazi prima di <?php";
    }
    
    // 3. Rimuovi  alla fine del file se presente
    if (preg_match('/\?>\s*$/', $content)) {
        $content = preg_replace('/\?>\s*$/', '', $content);
        $fixes_applied[] = "Rimosso ?> alla fine del file";
    }
    
    // 4. Rimuovi spazi finali e assicurati che il file termini con una nuova linea
    $content = rtrim($content);
    if (!empty($content) && substr($content, -1) !== "\n") {
        $content .= "\n";
        $fixes_applied[] = "Aggiunta nuova linea finale";
    }
    
    // 5. Rimuovi spazi finali da ogni linea
    $lines = explode("\n", $content);
    $lines = array_map('rtrim', $lines);
    $new_content = implode("\n", $lines);
    
    if ($new_content !== $content) {
        $content = $new_content;
        $fixes_applied[] = "Rimossi spazi finali dalle linee";
    }
    
    // 6. Controlla encoding e forza UTF-8 senza BOM
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'auto');
        $fixes_applied[] = "Convertito encoding a UTF-8";
    }
    
    // Se ci sono state modifiche, salva il file
    if ($content !== $original_content) {
        // Backup del file originale
        $backup_path = $full_path . '.backup.' . date('Y-m-d-H-i-s');
        file_put_contents($backup_path, $original_content);
        
        // Salva il file corretto
        file_put_contents($full_path, $content);
        
        echo "   ✅ Applicati " . count($fixes_applied) . " fix:\n";
        foreach ($fixes_applied as $fix) {
            echo "      - $fix\n";
        }
        echo "   📋 Backup salvato in: " . basename($backup_path) . "\n";
        
        $total_fixes += count($fixes_applied);
    } else {
        echo "   ✅ Nessuna correzione necessaria\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "🎉 COMPLETATO!\n\n";
echo "Totale correzioni applicate: $total_fixes\n\n";

if ($total_fixes > 0) {
    echo "✅ I file sono stati corretti. Ora prova a:\n";
    echo "1. Disattivare il plugin se già attivo\n";
    echo "2. Riattivare il plugin\n";
    echo "3. Se hai ancora problemi, controlla i log di WordPress\n\n";
    
    echo "📁 I file originali sono stati salvati come backup (.backup.YYYY-MM-DD-HH-MM-SS)\n";
    echo "   Puoi eliminarli dopo aver verificato che tutto funzioni correttamente.\n\n";
} else {
    echo "ℹ️  Nessun problema di output trovato nei file PHP.\n";
    echo "   Il problema potrebbe essere in:\n";
    echo "   - Template file (.php files in templates/)\n";
    echo "   - File di configurazione\n";
    echo "   - Interazioni con altri plugin\n\n";
}

echo "🔍 Se il problema persiste, esegui debug-output.php per un'analisi più dettagliata.\n";