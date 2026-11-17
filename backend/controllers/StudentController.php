<?php
declare(strict_types=1);

final class StudentController
{
    public static function list(PDO $db): array
    {
        $q = $db->query("SELECT u.id, u.curso_id, u.nombres, u.apellidos, u.usuario, u.activo, u.cambiar_password, u.creado_en, c.nombre AS curso FROM usuarios u LEFT JOIN cursos c ON c.id = u.curso_id WHERE u.rol = 'estudiante' ORDER BY u.apellidos, u.nombres, u.id");
        return ['estudiantes' => $q->fetchAll()];
    }

    private static function fields(PDO $db, array $data): array
    {
        $names = Http::text($data, 'nombres', 100);
        $surnames = Http::text($data, 'apellidos', 100);
        $username = Http::username($data);
        $course = $data['curso_id'] ?? null;
        if (!is_int($course) || $course < 1) { throw new HttpError(422, 'Selecciona un curso válido.'); }
        $q = $db->prepare('SELECT id FROM cursos WHERE id = ? AND activo = 1');
        $q->execute([$course]);
        if (!$q->fetch()) { throw new HttpError(422, 'El curso no está disponible.'); }
        return [$names, $surnames, $username, $course];
    }

    public static function create(PDO $db, array $data): array
    {
        $fields = self::fields($db, $data);
        $hash = password_hash(Http::password($data), PASSWORD_DEFAULT);
        $q = $db->prepare("INSERT INTO usuarios (nombres, apellidos, usuario, curso_id, password_hash, rol, activo, cambiar_password) VALUES (?, ?, ?, ?, ?, 'estudiante', 1, 1)");
        $q->execute([...$fields, $hash]);
        return ['id' => (int) $db->lastInsertId()];
    }

    public static function update(PDO $db, int $id, string $action, array $data): array
    {
        $db->beginTransaction();
        try {
            $q = $db->prepare("SELECT id FROM usuarios WHERE id = ? AND rol = 'estudiante' FOR UPDATE");
            $q->execute([$id]);
            if (!$q->fetch()) { throw new HttpError(404, 'Estudiante no encontrado.'); }
            if ($action === 'datos') {
                $fields = self::fields($db, $data);
                $q = $db->prepare("UPDATE usuarios SET nombres = ?, apellidos = ?, usuario = ?, curso_id = ? WHERE id = ? AND rol = 'estudiante'");
                $q->execute([...$fields, $id]);
            } elseif ($action === 'estado') {
                if (!isset($data['activo']) || !is_bool($data['activo'])) { throw new HttpError(422, 'El estado debe ser verdadero o falso.'); }
                $q = $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ? AND rol = 'estudiante'");
                $q->execute([(int) $data['activo'], $id]);
            } else {
                $q = $db->prepare("UPDATE usuarios SET password_hash = ?, cambiar_password = 1 WHERE id = ? AND rol = 'estudiante'");
                $q->execute([password_hash(Http::password($data), PASSWORD_DEFAULT), $id]);
            }
            $db->commit();
        } finally {
            if ($db->inTransaction()) { $db->rollBack(); }
        }
        return [];
    }
}
