<?php
// Railway PHP Built-in Server Router
// Handles static files + PHP routing

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Serve static files directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    // Set correct content type
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $types = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'svg'  => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    readfile($file);
    return true;
}

// Route PHP files
if ($uri !== '/' && file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
    require $file;
    return true;
}

// Everything else → index.php
require __DIR__ . '/index.php';
