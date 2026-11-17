<?php
declare(strict_types=1);

final class Usuario
{
    private PDO $db;
    public function __construct(PDO $db) { $this->db = $db; }

    public function find(int $id): ?array
    {
        $q = $this->db->prepare('SELECT * FROM usuarios WHERE id = ?');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public function byUsername(string $username): ?array
    {
        $q = $this->db->prepare('SELECT * FROM usuarios WHERE usuario = ?');
        $q->execute([$username]);
        return $q->fetch() ?: null;
    }

    public static function publicData(array $user): array
    {
        return [
            'id' => (int) $user['id'], 'nombres' => $user['nombres'], 'apellidos' => $user['apellidos'],
            'usuario' => $user['usuario'], 'rol' => $user['rol'], 'activo' => (bool) $user['activo'],
            'cambiar_password' => (bool) $user['cambiar_password'],
            'curso_id' => $user['curso_id'] === null ? null : (int) $user['curso_id'],
        ];
    }
}
