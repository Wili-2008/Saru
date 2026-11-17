<?php
declare(strict_types=1);

final class AuthController
{
    public static function login(PDO $db, Usuario $users, array $data): array
    {
        RateLimit::check('login', 30);
        $username = Http::text($data, 'usuario', 60);
        $password = $data['password'] ?? '';
        if (!is_string($password) || strlen($password) > 72 || strpos($password, "\0") !== false) {
            throw new HttpError(401, 'Usuario o contraseña incorrectos.');
        }
        $user = $users->byUsername($username);
        $hash = $user['password_hash'] ?? password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        if (!password_verify($password, $hash) || !$user || !(bool) $user['activo']) {
            throw new HttpError(401, 'Usuario o contraseña incorrectos.');
        }
        Auth::login($user);
        return ['usuario' => Usuario::publicData($user), 'csrf' => $_SESSION['csrf']];
    }

    public static function password(PDO $db, Usuario $users, array $data): array
    {
        $user = Auth::user($users, true);
        RateLimit::check('password-' . $user['id'], 15);
        $current = $data['actual'] ?? '';
        $new = Http::password($data, 'nueva');
        if (!is_string($current) || strlen($current) > 72 || strpos($current, "\0") !== false || !password_verify($current, $user['password_hash'])) {
            throw new HttpError(422, 'La contraseña actual no es correcta.');
        }
        if (password_verify($new, $user['password_hash'])) { throw new HttpError(422, 'Elige una contraseña diferente a la actual.'); }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        // Comparación del hash anterior: un restablecimiento concurrente no se sobrescribe.
        $q = $db->prepare('UPDATE usuarios SET password_hash = ?, cambiar_password = 0 WHERE id = ? AND password_hash = ? AND activo = 1');
        $q->execute([$hash, $user['id'], $user['password_hash']]);
        if ($q->rowCount() !== 1) { throw new HttpError(409, 'La cuenta cambió. Inicia sesión nuevamente.'); }
        $user['password_hash'] = $hash;
        $user['cambiar_password'] = 0;
        Auth::login($user);
        return ['usuario' => Usuario::publicData($user), 'csrf' => $_SESSION['csrf']];
    }
}
