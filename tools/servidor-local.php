<?php
// Alternativa local para pruebas; WampServer usa el VirtualHost documentado.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/api' || $path === '/api/') { require dirname(__DIR__) . '/backend/api/index.php'; return true; }
$root = realpath(dirname(__DIR__) . '/frontend');
$file = realpath($root . rawurldecode($path));
if ($file !== false && is_dir($file)) { $file = realpath($file . '/index.html'); }
if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file) ||
    !in_array(pathinfo($file, PATHINFO_EXTENSION), ['html', 'css', 'js', 'svg'], true)) {
    http_response_code(404); echo 'Página no encontrada.'; return true;
}
$mime = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8', 'svg' => 'image/svg+xml'];
header('Content-Type: ' . $mime[pathinfo($file, PATHINFO_EXTENSION)]);
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\'; img-src \'self\'; connect-src \'self\'; object-src \'none\'; base-uri \'self\'; frame-ancestors \'none\'; form-action \'self\'');
readfile($file);
return true;
