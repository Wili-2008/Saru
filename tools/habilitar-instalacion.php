<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/backend/config/Database.php';
try {
    $storage = dirname(__DIR__) . '/storage/';
    $db = Database::connect();
    $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute(['dbsaru.initial-administrator']);
    if ((int) $lock->fetchColumn() !== 1) { throw new RuntimeException('Hay otra instalación en curso.'); }
    if (is_file($storage . 'installed.lock') || (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'")->fetchColumn() > 0) {
        throw new RuntimeException('Ya existe un administrador o una instalación completada. No se habilitó el instalador.');
    }
    $token = bin2hex(random_bytes(24));
    $written = file_put_contents($storage . 'install-token.json', json_encode(['hash' => hash('sha256', $token), 'expires' => time() + 3600], JSON_THROW_ON_ERROR), LOCK_EX);
    if ($written === false) { throw new RuntimeException('No se pudo escribir en storage.'); }
    echo "Instalación habilitada durante una hora.\nAbre /instalacion.html en SARU.\nClave privada de instalación: " . $token . "\nNo compartas esta clave.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'No se habilitó la instalación. ' . ($e instanceof PDOException ? 'Comprueba local.php y MySQL.' : $e->getMessage()) . PHP_EOL);
    exit(1);
} finally {
    if (isset($db)) { $q = $db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute(['dbsaru.initial-administrator']); }
}
