<?php
declare(strict_types=1);

final class RateLimit
{
    public static function check(string $scope, int $limit): void
    {
        $directory = dirname(__DIR__, 2) . '/storage';
        $path = $directory . '/rate-' . hash('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local')) . '.json';
        $file = fopen($path, 'c+');
        if (!$file || !flock($file, LOCK_EX)) {
            throw new HttpError(503, 'No se pudo comprobar el límite de intentos. Revisa los permisos de storage.');
        }
        try {
            $data = json_decode(stream_get_contents($file), true);
            $now = time();
            if (!is_array($data) || ($data['until'] ?? 0) <= $now) {
                $data = ['count' => 0, 'until' => $now + 900];
            }
            if ($data['count'] >= $limit) {
                header('Retry-After: ' . max(1, $data['until'] - $now));
                throw new HttpError(429, 'Demasiados intentos. Intenta nuevamente en 15 minutos.');
            }
            $data['count']++;
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($data, JSON_THROW_ON_ERROR));
            fflush($file);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }
}
