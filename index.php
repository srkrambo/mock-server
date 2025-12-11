<?php
/**
 * PHP Mock Server
 * Main entry point
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
$config = require __DIR__ . '/config.php';

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'MockServer\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Create and handle request
use MockServer\Router;

$router = new Router($config);
$router->handleRequest();
