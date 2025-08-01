<?php
/**
 * Script per identificare l'output inatteso durante l'attivazione
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Debug Output Inatteso\n";
echo str_repeat("=", 50) . "\n\n";

// Lista di tutti i file PHP del plugin
$files_to_check = [
    'gestione-accessi-bt.php',
    'includes/class-plugin-core.php',
    'includes/class-activation-handler.php',
    'includes/admin/class-settings-manager.php',
    'includes/admin/class-admin-pages.php',
    'includes/frontend/class-frontend-handler.php',
    'includes/database/class-database-manager.php',
    'includes/services/class-alloggiati-web-client.php',
    'includes/services/class-xml-parser.php',
    'includes/services/class-schedule-formatter.php',
    'includes/ajax/class-ajax-handler.php',
    'includes/cron/class-cron-manager.php'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "⚠️  File non trovato: $file\n";
        continue;
    }
    
    echo "🔍 Controllo: $file\n";
    
    // Controlla BOM
    $content = file_get_contents($full_path);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        echo "   ❌ BOM trovato all'inizio del file!\n";
    }
    
    // Controlla spazi prima di <?php
    if (!preg_match('/^\s*<\?php/', $content)) {
        echo "   ❌ Spazi o caratteri prima di <?php!\n";
        $first_chars = substr($content, 0, 20);
        echo "   Primi caratteri: " . bin2hex($first_chars) . "\n";
    }
    
    // Controlla spazi dopo ?>
    if (preg_match('/\?>\s+$/', $content)) {
        echo "   ❌ Spazi dopo ?> alla fine del file!\n";
    }
    
    // Controlla se il file termina con ?>
    if (preg_match('/\?>\s*$/', $content)) {
        echo "   ⚠️  File termina con ?> (non raccomandato per file solo PHP)\n";
    }
    
    // Test caricamento del file
    ob_start();
    $error = null;
    
    try {
        // Simula costanti WordPress se non definite
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__ . '/');
        }
        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', __DIR__);
        }
        
        // Mock funzioni WordPress essenziali
        if (!function_exists('plugin_dir_url')) {
            function plugin_dir_url($file) { return 'http://localhost/wp-content/plugins/gestione-accessi-bt/'; }
        }
        if (!function_exists('plugin_dir_path')) {
            function plugin_dir_path($file) { return __DIR__ . '/'; }
        }
        if (!function_exists('get_option')) {
            function get_option($option, $default = false) { return $default; }
        }
        if (!function_exists('add_option')) {
            function add_option($option, $value) { return true; }
        }
        if (!function_exists('current_time')) {
            function current_time($type) { return time(); }
        }
        if (!function_exists('get_bloginfo')) {
            function get_bloginfo($show = '') { return 'Test Site'; }
        }
        if (!function_exists('flush_rewrite_rules')) {
            function flush_rewrite_rules() { return true; }
        }
        if (!function_exists('wp_clear_scheduled_hook')) {
            function wp_clear_scheduled_hook($hook) { return true; }
        }
        
        include $full_path;
        
    } catch (ParseError $e) {
        $error = "ERRORE SINTASSI: " . $e->getMessage();
    } catch (Error $e) {
        $error = "ERRORE FATALE: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "ECCEZIONE: " . $e->getMessage();
    }
    
    $output = ob_get_clean();
    
    if ($error) {
        echo "   ❌ $error\n";
    }
    
    if (!empty($output)) {
        echo "   ❌ OUTPUT TROVATO (" . strlen($output) . " caratteri):\n";
        echo "   " . str_replace("\n", "\n   ", substr($output, 0, 200)) . "\n";
        if (strlen($output) > 200) {
            echo "   ... (troncato)\n";
        }
    } else {
        echo "   ✅ Nessun output\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "Debug completato.\n";
echo "Cerca i file con ❌ per identificare la fonte dell'output inatteso.\n";
?>
