<?php
declare(strict_types=1);

final class InstallController
{
    public static function available(PDO $db): bool
    {
        $directory = dirname(__DIR__, 2) . '/storage/';
        if (is_file($directory . 'installed.lock') || !is_file($directory . 'install-token.json')) { return false; }
        $token = json_decode(file_get_contents($directory . 'install-token.json'), true);
        return is_array($token) && ($token['expires'] ?? 0) > time() &&
            (int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'")->fetchColumn() === 0;
    }

    public static function create(PDO $db, array $data): array
    {
        RateLimit::check('install', 10);
        $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute(['dbsaru.initial-administrator']);
        if ((int) $lock->fetchColumn() !== 1) { throw new HttpError(409, 'Hay otra instalación en curso.'); }
        try {
            if (!self::available($db)) { throw new HttpError(403, 'La instalación está deshabilitada.'); }
            $directory = dirname(__DIR__, 2) . '/storage/';
            $token = json_decode(file_get_contents($directory . 'install-token.json'), true);
            $provided = $data['token'] ?? '';
            if (!is_string($provided) || !hash_equals($token['hash'], hash('sha256', $provided))) {
                throw new HttpError(403, 'La clave de instalación no es válida.');
            }
            $names = Http::text($data, 'nombres', 100);
            $surnames = Http::text($data, 'apellidos', 100);
            $username = Http::username($data);
            $hash = password_hash(Http::password($data), PASSWORD_DEFAULT);
            $db->beginTransaction();
            $q = $db->prepare("INSERT INTO usuarios (nombres, apellidos, usuario, password_hash, rol, activo, cambiar_password) VALUES (?, ?, ?, ?, 'administrador', 1, 0)");
            $q->execute([$names, $surnames, $username, $hash]);
            $db->commit();
            // La presencia del administrador también bloquea futuras instalaciones si el disco falla.
            file_put_contents($directory . 'installed.lock', gmdate(DATE_ATOM), LOCK_EX);
            unlink($directory . 'install-token.json');
            return [];
        } finally {
            if ($db->inTransaction()) { $db->rollBack(); }
            $release = $db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute(['dbsaru.initial-administrator']);
        }
    }
}
