<?php
/**
 * Script per identificare l'output inatteso durante l'attivazione
 * Salva questo file come debug-output.php nella root del plugin e eseguilo via browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🔍 Debug Output Inatteso - Gestione Accessi BT\n";
echo str_repeat("=", 60) . "\n\n";

// Lista di tutti i file PHP del plugin da controllare
$files_to_check = [
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

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "⚠️  File non trovato: $file\n";
        continue;
    }
    
    echo "🔍 Controllo: $file\n";
    
    // Leggi il contenuto del file
    $content = file_get_contents($full_path);
    $issues = [];
    
    // 1. Controlla BOM all'inizio
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $issues[] = "❌ BOM trovato all'inizio del file";
    }
    
    // 2. Controlla spazi/caratteri prima di <?php
    if (!preg_match('/^\s*<\?php/', $content)) {
        $issues[] = "❌ Spazi o caratteri prima di <?php";
        $first_chars = substr($content, 0, 10);
        $hex = bin2hex($first_chars);
        $issues[] = "   Primi caratteri (hex): $hex";
    }
    
    // 3. Controlla spazi dopo ?>
    if (preg_match('/\?>\s+$/', $content)) {
        $issues[] = "❌ Spazi dopo ?> alla fine del file";
    }
    
    // 4. Controlla se il file termina con ?> (non raccomandato)
    if (preg_match('/\?>\s*$/', $content)) {
        $issues[] = "⚠️  File termina con ?> (raccomandato rimuoverlo)";
    }
    
    // 5. Controlla output statements (echo, print, var_dump, ecc.)
    $output_patterns = [
        '/\becho\s+/' => 'echo statement',
        '/\bprint\s+/' => 'print statement', 
        '/\bvar_dump\s*\(/' => 'var_dump call',
        '/\bprint_r\s*\(/' => 'print_r call',
        '/\bprintf\s*\(/' => 'printf call'
    ];
    
    foreach ($output_patterns as $pattern => $description) {
        if (preg_match($pattern, $content)) {
            // Escludi commenti e stringhe
            $lines = explode("\n", $content);
            foreach ($lines as $line_num => $line) {
                $line = trim($line);
                if (preg_match($pattern, $line) && 
                    !preg_match('/^\s*\/\//', $line) && 
                    !preg_match('/^\s*\*/', $line) &&
                    !preg_match('/^\s*\/\*/', $line)) {
                    $issues[] = "⚠️  $description trovato alla linea " . ($line_num + 1) . ": " . substr($line, 0, 50);
                }
            }
        }
    }
    
    // 6. Test caricamento del file
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
        if (!defined('GABT_PLUGIN_URL')) {
            define('GABT_PLUGIN_URL', 'http://localhost/wp-content/plugins/gestione-accessi-bt/');
        }
        if (!defined('GABT_PLUGIN_PATH')) {
            define('GABT_PLUGIN_PATH', __DIR__ . '/');
        }
        if (!defined('GABT_VERSION')) {
            define('GABT_VERSION', '1.3.0');
        }
        
        // Mock funzioni WordPress essenziali se non esistono
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
        if (!function_exists('update_option')) {
            function update_option($option, $value) { return true; }
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
        if (!function_exists('dbDelta')) {
            function dbDelta($queries) { return array(); }
        }
        
        // Mock oggetti globali
        global $wpdb;
        if (!isset($wpdb)) {
            $wpdb = new stdClass();
            $wpdb->prefix = 'wp_';
            $wpdb->get_charset_collate = function() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; };
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
        $issues[] = "❌ $error";
    }
    
    if (!empty($output)) {
        $issues[] = "❌ OUTPUT TROVATO (" . strlen($output) . " caratteri)";
        $preview = substr($output, 0, 100);
        $issues[] = "   Preview: " . addcslashes($preview, "\r\n\t");
        if (strlen($output) > 100) {
            $issues[] = "   ... (troncato)";
        }
    }
    
    if (empty($issues)) {
        echo "   ✅ Nessun problema trovato\n";
    } else {
        foreach ($issues as $issue) {
            echo "   $issue\n";
        }
    }
    
    echo "\n";
}

echo str_repeat("=", 60) . "\n";
echo "🎯 RACCOMANDAZIONI:\n\n";
echo "1. Rimuovi tutti i ?> alla fine dei file PHP\n";
echo "2. Verifica che non ci siano spazi prima di <?php\n";
echo "3. Salva i file con encoding UTF-8 senza BOM\n";
echo "4. Rimuovi eventuali echo/print/var_dump non necessari\n";
echo "5. Controlla che tutti i file terminino con una nuova linea\n\n";

echo "💡 Dopo aver corretto i problemi, prova a riattivare il plugin.\n";
?>
