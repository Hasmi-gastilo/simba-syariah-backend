<?php

// Immediate CORS preflight handling
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, X-CSRF-Token, X-Api-Version');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit(0);
}

// Suppress deprecations in PHP 8.5
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

// Ensure /tmp storage directories exist for Vercel
$dirs = [
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/storage/app/public',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Restore /api prefix if Vercel stripped it
if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (str_starts_with($uri, '/v1')) {
        $_SERVER['REQUEST_URI'] = '/api' . $uri;
    }
}

// Forward request to Laravel public index.php
require __DIR__ . '/../public/index.php';
