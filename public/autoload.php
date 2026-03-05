<?php
/**
 * Simple Autoloader
 * Loads classes without Composer
 */

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'P2P\\';
    $baseDir = __DIR__ . '/../src/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    // Get the relative class name
    $relativeClass = substr($class, $len);
    
    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Load Config class early for configuration
require_once __DIR__ . '/../src/Core/Config.php';
require_once __DIR__ . '/../src/Core/Database.php';
