<?php
/**
 * P2P Application Configuration
 */

return [
    // Application Settings
    'app' => [
        'name' => 'P2P Network',
        'env' => 'development',
        'debug' => true,
        'url' => 'http://localhost:8080',
        'timezone' => 'UTC',
    ],

    // Database Configuration
    'database' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'p2p_network',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],

    // Authentication
    'auth' => [
        'jwt_secret' => 'your-super-secret-jwt-key-change-in-production',
        'jwt_expiry' => 86400, // 24 hours in seconds
        'jwt_algorithm' => 'HS256',
        'bcrypt_cost' => 10,
    ],

    // WebSocket Server
    'websocket' => [
        'host' => '127.0.0.1',
        'port' => 8081,
        'protocol' => 'ws',
    ],

    // P2P Settings
    'p2p' => [
        'stun_servers' => [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302',
        ],
        'turn_servers' => [],
        'ice_candidates_timeout' => 30000, // milliseconds
        'max_file_size' => 104857600, // 100MB
    ],

    // Security
    'security' => [
        'cors_enabled' => true,
        'cors_origins' => ['http://localhost:8080', 'http://127.0.0.1:8080'],
        'rate_limit' => 60, // requests per minute
        'csrf_protection' => true,
    ],

    // File Upload
    'upload' => [
        'max_size' => 104857600, // 100MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'],
        'upload_path' => 'uploads/',
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'level' => 'debug',
        'path' => 'logs/',
    ],
];
