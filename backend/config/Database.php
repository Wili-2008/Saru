<?php
declare(strict_types=1);

final class Database
{
    public static function connect(): PDO
    {
        $path = __DIR__ . '/local.php';
        $config = is_file($path) ? require $path : [];
        $value = static function (string $env, string $key, $default = null) use ($config) {
            $v = getenv($env);
            return $v !== false ? $v : ($config[$key] ?? $default);
        };
        $user = $value('SARU_DB_USER', 'db_user');
        $password = $value('SARU_DB_PASSWORD', 'db_password');
        $name = $value('SARU_DB_NAME', 'db_name', 'dbsaru');
        if (!is_string($user) || $user === '' || !is_string($password) || $name !== 'dbsaru') {
            throw new RuntimeException('Configure las credenciales locales de dbsaru.');
        }
        $host = $value('SARU_DB_HOST', 'db_host', '127.0.0.1');
        $port = (int) $value('SARU_DB_PORT', 'db_port', 3306);
        if (!is_string($host) || !preg_match('/^[a-zA-Z0-9.:-]+$/D', $host) || $port < 1 || $port > 65535) {
            throw new RuntimeException('Configuración MySQL inválida.');
        }
        return new PDO("mysql:host={$host};port={$port};dbname=dbsaru;charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
