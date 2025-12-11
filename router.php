<?php
/**
 * Router script for PHP built-in server
 * This ensures all HTTP methods are properly handled
 */

// Get the requested URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Allow the built-in server to serve static files
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Otherwise, pass to index.php
require __DIR__ . '/index.php';
