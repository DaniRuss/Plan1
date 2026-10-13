<?php

$publicPath = __DIR__;
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = realpath($publicPath.$uri);

if (
    $uri !== '/'
    && $file !== false
    && is_file($file)
    && str_starts_with($file, realpath($publicPath).DIRECTORY_SEPARATOR)
) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $cacheable = in_array($extension, [
        'css', 'js', 'map', 'woff', 'woff2', 'ttf', 'eot',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
    ], true);

    if ($cacheable) {
        $modified = filemtime($file);
        $etag = '"'.dechex($modified).'-'.dechex(filesize($file)).'"';
        $mimeTypes = [
            'css' => 'text/css; charset=UTF-8', 'js' => 'application/javascript; charset=UTF-8',
            'map' => 'application/json', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
            'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'webp' => 'image/webp',
            'ico' => 'image/x-icon',
        ];

        header('Content-Type: '.($mimeTypes[$extension] ?? 'application/octet-stream'));
        header('Cache-Control: public, max-age=604800');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
        header('ETag: '.$etag);

        if (
            trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag
            || strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '') >= $modified
        ) {
            http_response_code(304);
            return true;
        }

        header('Content-Length: '.filesize($file));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($file);
        }

        return true;
    }

    return false;
}

require_once $publicPath.'/index.php';
