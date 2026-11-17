<?php
declare(strict_types=1);
// Ejecuta la entrada real de la API en procesos PHP separados, sin red ni MySQL.
if (PHP_SAPI !== 'cli') { exit; }
$temporary = sys_get_temp_dir() . '/saru-api-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700, true);
$entry = dirname(__DIR__) . '/backend/api/index.php';
$cases = [['GET', 'sesion', 200], ['POST', 'auth/logout', 403], ['POST', 'auth/login', 403],
    ['GET', 'ruta-inexistente', 404], ['PUT', 'auth/login', 405]];
try {
    foreach ($cases as [$method, $route, $expected]) {
        $runner = $temporary . '/run.php';
        $code = '<?php ini_set("session.save_path", ' . var_export($temporary, true) . ');' .
            '$_SERVER["REQUEST_METHOD"]=' . var_export($method, true) . ';' .
            '$_GET["route"]=' . var_export($route, true) . ';' .
            'register_shutdown_function(static function(){fwrite(STDERR,"STATUS:".http_response_code());});' .
            'require ' . var_export($entry, true) . ';';
        file_put_contents($runner, $code);
        $process = proc_open([PHP_BINARY, '-n', $runner], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('No se pudo ejecutar PHP.'); }
        $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
        proc_close($process);
        $json = json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        if (strpos($error, 'STATUS:' . $expected) === false || !isset($json['estado'], $json['message'], $json['data']) ||
            $json['estado'] !== ($expected < 400)) { throw new RuntimeException('Falló ' . $method . ' ' . $route . ': ' . $output . $error); }
        if ($route === 'sesion' && ($json['data']['usuario'] !== null || strlen($json['data']['csrf']) !== 64)) { throw new RuntimeException('Sesión pública inválida.'); }
        echo "OK {$method} {$route}: {$expected}, JSON uniforme\n";
    }
    echo "TOTAL: 5 comprobaciones de la entrada API en PHP CLI. No sustituyen pruebas HTTP.\n";
} finally {
    foreach (new FilesystemIterator($temporary) as $file) { unlink($file->getPathname()); }
    rmdir($temporary);
}
