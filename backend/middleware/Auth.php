<?php
declare(strict_types=1);

final class Auth
{
    public static function start(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('saru_session');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Strict',
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    }

    public static function csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
            throw new HttpError(403, 'La sesión del formulario expiró. Recarga la página.');
        }
    }

    public static function user(Usuario $users, bool $allowPasswordChange = false): array
    {
        $id = $_SESSION['user_id'] ?? 0;
        $user = $id ? $users->find((int) $id) : null;
        $configPath = dirname(__DIR__) . '/config/local.php';
        $config = is_file($configPath) ? require $configPath : [];
        $minutes = max(5, min(240, (int) ($config['session_minutes'] ?? 30)));
        if (!$user || !(bool) $user['activo'] || !hash_equals($user['password_hash'], $_SESSION['credential'] ?? '') ||
            time() - ($_SESSION['last_seen'] ?? 0) > $minutes * 60 ||
            !in_array($user['rol'], ['administrador', 'estudiante'], true)) {
            unset($_SESSION['user_id'], $_SESSION['credential'], $_SESSION['last_seen']);
            throw new HttpError(401, 'Inicia sesión para continuar.');
        }
        $_SESSION['last_seen'] = time();
        if (!$allowPasswordChange && (bool) $user['cambiar_password']) {
            throw new HttpError(403, 'Debes cambiar tu contraseña temporal antes de continuar.');
        }
        return $user;
    }

    public static function admin(Usuario $users): array
    {
        $user = self::user($users);
        if ($user['rol'] !== 'administrador') { throw new HttpError(403, 'Solo el administrador puede realizar esta operación.'); }
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int) $user['id'], 'credential' => $user['password_hash'],
            'last_seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'],
            'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        session_destroy();
    }
}
