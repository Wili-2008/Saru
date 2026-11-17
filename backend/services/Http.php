<?php
declare(strict_types=1);

final class HttpError extends RuntimeException
{
    public int $status;
    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

final class Http
{
    public static function respond(int $status, string $message, array $data = []): void
    {
        http_response_code($status);
        echo json_encode(['estado' => $status < 400, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function body(): array
    {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
            throw new HttpError(415, 'Envía los datos como JSON.');
        }
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if ($raw === false || strlen($raw) > 65536) {
            throw new HttpError(413, 'La solicitud es demasiado grande.');
        }
        try {
            $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpError(400, 'El JSON no es válido.');
        }
        if (!is_array($body) || substr(ltrim($raw), 0, 1) !== '{') {
            throw new HttpError(400, 'Se esperaba un objeto JSON.');
        }
        return $body;
    }

    public static function text(array $data, string $key, int $max): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            throw new HttpError(422, 'Completa el campo ' . $key . '.');
        }
        $v = trim($data[$key]);
        $length = preg_match_all('/./us', $v);
        if ($v === '' || $length === false || $length > $max || preg_match('/[\x00-\x1F\x7F]/', $v)) {
            throw new HttpError(422, 'Revisa el campo ' . $key . '.');
        }
        return $v;
    }

    public static function username(array $data): string
    {
        $v = self::text($data, 'usuario', 60);
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/D', $v)) {
            throw new HttpError(422, 'El usuario debe tener entre 3 y 60 letras, números, puntos, guiones o guiones bajos.');
        }
        return $v;
    }

    public static function password(array $data, string $key = 'password'): string
    {
        $v = $data[$key] ?? null;
        if (!is_string($v) || strlen($v) > 72 || strpos($v, "\0") !== false || trim($v) === '') {
            throw new HttpError(422, 'La contraseña debe tener al menos 12 caracteres y como máximo 72 bytes.');
        }
        $characters = preg_match_all('/./us', $v);
        if ($characters === false || $characters < 12) {
            throw new HttpError(422, 'La contraseña debe tener al menos 12 caracteres y como máximo 72 bytes.');
        }
        return $v;
    }
}
