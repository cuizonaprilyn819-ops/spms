<?php

// Static cache headers for the local PHP server. Application requests retain
// Laravel's router, authentication and response headers.
$publicPath = dirname(__DIR__).'/public';
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
$hashed = preg_match('#^/build/assets/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{8,}\.(css|js)$#D', $uri);
$reference = in_array($uri, ['/fonts/figtree.ttf', '/fonts/figtree-italic.ttf', '/images/logoslsu.png', '/favicon.ico'], true);
$path = realpath($publicPath.$uri);
$root = realpath($publicPath).DIRECTORY_SEPARATOR;
if (($hashed || $reference) && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)
    && $path && str_starts_with($path, $root) && is_file($path)) {
    $etag = '"'.hash_file('sha256', $path).'"';
    $modified = filemtime($path);
    header('Cache-Control: public, max-age='.($hashed ? '31536000, immutable' : '86400'));
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
    header('X-Content-Type-Options: nosniff');
    $matches = array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    $unchanged = in_array($etag, $matches, true) || in_array('W/'.$etag, $matches, true) || in_array('*', $matches, true);
    if (! isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        $unchanged = $since !== false && $since >= $modified;
    }
    if ($unchanged) {
        http_response_code(304);
        return true;
    }
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8',
        'ttf' => 'font/ttf', 'png' => 'image/png', 'ico' => 'image/x-icon',
    };
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($path));
    if ($_SERVER['REQUEST_METHOD'] === 'GET') readfile($path);
    return true;
}

return require dirname(__DIR__).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
