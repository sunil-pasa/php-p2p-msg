<?php
/**
 * P2P Network Application - Main Entry Point
 * Handles all HTTP requests and routes them to appropriate controllers
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Define base paths
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Load autoloader
require BASE_PATH . '/public/autoload.php';

use P2P\Core\Config;
use P2P\Core\Database;
use P2P\Controllers\ApiController;

// Initialize configuration
Config::init();

// Initialize database
Database::getInstance();

// Handle CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Route to API
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

// Remove script name from URI
$path = str_replace($scriptName, '', $requestUri);

// Route requests
if (strpos($path, '/api/') === 0) {
    // API request
    $controller = new ApiController();
    $controller->run();
} elseif ($path === '/' || $path === '/index.php') {
    // Serve frontend
    readfile(PUBLIC_PATH . '/index.html');
} elseif (file_exists(PUBLIC_PATH . $path)) {
    // Serve static files
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];
    
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'text/plain'));
    readfile(PUBLIC_PATH . $path);
} else {
    // 404
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}
