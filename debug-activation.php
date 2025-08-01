<?php
/**
 * Script di debug per identificare l'errore fatale
 * Eseguire questo script per trovare la causa dell'errore
 */

// Abilita la visualizzazione degli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "🔍 Debug Attivazione Plugin\n";
echo str_repeat("=", 50) . "\n\n";

// Simula costanti WordPress essenziali
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', __DIR__);
}

// Funzioni WordPress mock essenziali
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

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Test Site'; }
}

if (!function_exists('current_time')) {
    function current_time($type) { return time(); }
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

// Mock wpdb
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new stdClass();
    $wpdb->prefix = 'wp_';
}

echo "1. Test caricamento file principale...\n";
try {
    ob_start();
    include __DIR__ . '/gestione-accessi-bt.php';
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "❌ Output inatteso: " . strlen($output) . " caratteri\n";
        echo "Output: " . substr($output, 0, 200) . "\n\n";
    } else {
        echo "✅ File principale caricato senza output\n\n";
    }
} catch (ParseError $e) {
    echo "❌ ERRORE DI SINTASSI: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n\n";
    exit(1);
} catch (Error $e) {
    echo "❌ ERRORE FATALE: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ ECCEZIONE: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n\n";
    exit(1);
}

echo "2. Test caricamento Activation Handler...\n";
try {
    if (file_exists(__DIR__ . '/includes/class-activation-handler.php')) {
        include __DIR__ . '/includes/class-activation-handler.php';
        echo "✅ Activation Handler caricato\n\n";
    } else {
        echo "❌ File Activation Handler non trovato\n\n";
    }
} catch (Error $e) {
    echo "❌ ERRORE in Activation Handler: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n\n";
}

echo "3. Test funzione di attivazione...\n";
try {
    if (function_exists('gabt_activate_plugin')) {
        $result = gabt_activate_plugin();
        echo "✅ Funzione di attivazione eseguita: " . ($result ? 'SUCCESS' : 'FAILED') . "\n\n";
    } else {
        echo "❌ Funzione gabt_activate_plugin non trovata\n\n";
    }
} catch (Error $e) {
    echo "❌ ERRORE FATALE nella funzione di attivazione: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

echo "4. Test caricamento classe principale...\n";
try {
    if (function_exists('gabt_init_plugin')) {
        $plugin = gabt_init_plugin();
        echo "✅ Plugin inizializzato: " . get_class($plugin) . "\n\n";
    } else {
        echo "❌ Funzione gabt_init_plugin non trovata\n\n";
    }
} catch (Error $e) {
    echo "❌ ERRORE nella inizializzazione: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Linea: " . $e->getLine() . "\n\n";
}

echo "5. Verifica file essenziali...\n";
$essential_files = [
    'gestione-accessi-bt.php',
    'includes/class-activation-handler.php',
    'includes/class-plugin-core.php',
    'includes/database/class-database-manager.php',
    'includes/admin/class-settings-manager.php'
];

foreach ($essential_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file\n";
        
        // Test sintassi
        $output = shell_exec("php -l \"" . __DIR__ . "/$file\" 2>&1");
        if ($output && strpos($output, 'No syntax errors') === false) {
            echo "   ❌ ERRORE SINTASSI: $output\n";
        }
    } else {
        echo "❌ $file MANCANTE\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Debug completato. Controlla gli errori sopra per identificare il problema.\n";
?>
