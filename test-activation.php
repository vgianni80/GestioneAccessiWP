<?php
/**
 * Script di test per verificare l'attivazione del plugin
 * Eseguire questo script per verificare che non ci siano errori di sintassi
 * 
 * @package GestioneAccessiBT
 * @since 1.3.0
 */

// Simula l'ambiente WordPress minimale
if (!defined('ABSPATH')) {
    define('ABSPATH', '/');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', __DIR__);
}

// Funzioni WordPress mock per il test
if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost/wp-content/plugins/gestione-accessi-bt/';
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return __DIR__ . '/';
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'Test Site';
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

echo "🧪 Test di attivazione plugin Gestione Accessi BluTrasimeno\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Test 1: Verifica file principale
echo "1. Test file principale... ";
try {
    ob_start();
    include_once __DIR__ . '/gestione-accessi-bt.php';
    $output = ob_get_clean();
    
    if (empty($output)) {
        echo "✅ OK - Nessun output inatteso\n";
    } else {
        echo "❌ ERRORE - Output inatteso: " . strlen($output) . " caratteri\n";
        echo "   Output: " . substr($output, 0, 100) . "...\n";
    }
} catch (Exception $e) {
    echo "❌ ERRORE - Eccezione: " . $e->getMessage() . "\n";
}

// Test 2: Verifica autoloader
echo "2. Test autoloader... ";
try {
    // Testa il caricamento di una classe
    if (class_exists('GABT_Plugin_Core')) {
        echo "✅ OK - Classe principale caricata\n";
    } else {
        echo "❌ ERRORE - Classe principale non trovata\n";
    }
} catch (Exception $e) {
    echo "❌ ERRORE - Eccezione autoloader: " . $e->getMessage() . "\n";
}

// Test 3: Verifica struttura file
echo "3. Test struttura file... ";
$required_files = [
    'includes/class-plugin-core.php',
    'includes/admin/class-admin-menu.php',
    'includes/admin/class-admin-pages.php',
    'includes/admin/class-settings-manager.php',
    'includes/database/class-database-manager.php',
    'includes/database/class-booking-repository.php',
    'includes/services/class-alloggiati-client.php',
    'includes/services/class-xml-parser.php',
    'includes/services/class-schedule-formatter.php',
    'includes/ajax/class-ajax-handlers.php',
    'includes/cron/class-cron-manager.php',
    'includes/frontend/class-frontend-handler.php',
    'includes/frontend/class-guest-forms.php',
    'assets/css/admin.css',
    'assets/css/frontend.css',
    'assets/js/admin.js',
    'assets/js/frontend.js'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $missing_files[] = $file;
    }
}

if (empty($missing_files)) {
    echo "✅ OK - Tutti i file richiesti presenti\n";
} else {
    echo "❌ ERRORE - File mancanti:\n";
    foreach ($missing_files as $file) {
        echo "   - $file\n";
    }
}

// Test 4: Verifica sintassi PHP
echo "4. Test sintassi PHP... ";
$php_files = glob(__DIR__ . '/{*.php,includes/*.php,includes/*/*.php}', GLOB_BRACE);
$syntax_errors = [];

foreach ($php_files as $file) {
    $output = shell_exec("php -l \"$file\" 2>&1");
    if (strpos($output, 'No syntax errors') === false) {
        $syntax_errors[] = basename($file) . ': ' . trim($output);
    }
}

if (empty($syntax_errors)) {
    echo "✅ OK - Nessun errore di sintassi\n";
} else {
    echo "❌ ERRORE - Errori di sintassi trovati:\n";
    foreach ($syntax_errors as $error) {
        echo "   - $error\n";
    }
}

// Test 5: Verifica JavaScript
echo "5. Test JavaScript... ";
$js_files = [
    'assets/js/admin.js',
    'assets/js/frontend.js'
];

$js_errors = [];
foreach ($js_files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $content = file_get_contents(__DIR__ . '/' . $file);
        
        // Verifica che non contenga jQuery
        if (strpos($content, 'jQuery') !== false || strpos($content, '$') !== false) {
            $js_errors[] = "$file contiene ancora riferimenti jQuery";
        }
        
        // Verifica che contenga Fetch API
        if (strpos($content, 'fetch(') === false) {
            $js_errors[] = "$file non utilizza Fetch API";
        }
    }
}

if (empty($js_errors)) {
    echo "✅ OK - JavaScript modernizzato correttamente\n";
} else {
    echo "❌ AVVISO - Problemi JavaScript:\n";
    foreach ($js_errors as $error) {
        echo "   - $error\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";

if (empty($missing_files) && empty($syntax_errors)) {
    echo "🎉 RISULTATO: Plugin pronto per l'installazione!\n";
    echo "\nProssimi passi:\n";
    echo "1. Disattiva il plugin se già attivo\n";
    echo "2. Sostituisci i file del plugin con questa versione\n";
    echo "3. Riattiva il plugin\n";
    echo "4. Vai alle impostazioni per configurare le credenziali\n";
} else {
    echo "⚠️  RISULTATO: Plugin necessita correzioni prima dell'installazione\n";
}

echo "\n";
?>
