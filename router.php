<?php
// Router for PHP's built-in web server (used on Railway). Replaces .htaccess.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = '/' . ltrim(str_replace('\\', '/', $path), '/');

// Never serve private files (.git, .env, SQL dump, includes/, docs, config) or run uploaded PHP.
if (
    strpos($path, '..') !== false ||
    preg_match('#/\.#', $path) ||
    preg_match('#^/(includes|docker)(/|$)#i', $path) ||
    preg_match('#\.(sql|md|ini|toml|json|lock|log|yml|yaml)$#i', $path) ||
    preg_match('#^/(Dockerfile|router\.php)$#i', $path) ||
    preg_match('#^/uploads/.*\.(php|phtml|phar)$#i', $path)
) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

$file = __DIR__ . $path;

if (is_file($file)) {
    return false;                       // static file, or a .php file: let the server handle it normally
}
if (is_file($file . '.php')) {          // /login  ->  /login.php
    require $file . '.php';
    return true;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    require rtrim($file, '/') . '/index.php';
    return true;
}

require __DIR__ . '/index.php';         // default page
