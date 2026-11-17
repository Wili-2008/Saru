<?php
declare(strict_types=1);

final class ContentController
{
    public static function subjects(PDO $db): array
    {
        return ['materias' => Data::query($db, 'SELECT m.*, c.nombre AS curso FROM materias m JOIN cursos c ON c.id = m.curso_id ORDER BY m.nombre')->fetchAll()];
    }
    public static function subject(PDO $db, array $data, ?int $id): array
    {
        $course = Data::id($data, 'curso_id');
        Data::one($db, 'SELECT id FROM cursos WHERE id = ? AND activo = 1', [$course]);
        $values = [$course, Http::text($data, 'nombre', 120), Data::optional($data, 'descripcion'), Data::flag($data)];
        if ($id !== null) {
            Data::one($db, 'SELECT id FROM materias WHERE id = ?', [$id]);
            Data::query($db, 'UPDATE materias SET curso_id = ?, nombre = ?, descripcion = ?, activo = ? WHERE id = ?', [...$values, $id]);
        } else {
            Data::query($db, 'INSERT INTO materias (curso_id, nombre, descripcion, activo) VALUES (?, ?, ?, ?)', $values);
            $id = (int) $db->lastInsertId();
        }
        return ['id' => $id];
    }
    public static function topics(PDO $db): array
    {
        return ['temas' => Data::query($db, 'SELECT t.*, m.nombre AS materia FROM temas t JOIN materias m ON m.id = t.materia_id ORDER BY m.nombre, t.orden, t.titulo')->fetchAll()];
    }
    public static function topic(PDO $db, array $data, ?int $id): array
    {
        $subject = Data::id($data, 'materia_id');
        Data::one($db, 'SELECT id FROM materias WHERE id = ?', [$subject]);
        $state = $data['estado'] ?? '';
        if (!in_array($state, ['borrador', 'publicado'], true)) { throw new HttpError(422, 'Estado del tema inválido.'); }
        $order = Data::id($data, 'orden');
        if ($order > 4294967295) { throw new HttpError(422, 'Orden fuera de rango.'); }
        $values = [$subject, Http::text($data, 'titulo', 200), Data::optional($data, 'descripcion'), $order, $state];
        if ($id !== null) {
            Data::one($db, 'SELECT id FROM temas WHERE id = ?', [$id]);
            Data::query($db, 'UPDATE temas SET materia_id = ?, titulo = ?, descripcion = ?, orden = ?, estado = ? WHERE id = ?', [...$values, $id]);
        } else {
            Data::query($db, 'INSERT INTO temas (materia_id, titulo, descripcion, orden, estado) VALUES (?, ?, ?, ?, ?)', $values);
            $id = (int) $db->lastInsertId();
        }
        return ['id' => $id];
    }
    public static function resources(PDO $db): array
    {
        return ['recursos' => Data::query($db, 'SELECT r.*, t.titulo AS tema FROM recursos r JOIN temas t ON t.id = r.tema_id ORDER BY r.id DESC')->fetchAll()];
    }
    public static function resource(PDO $db, array $data, ?int $id): array
    {
        $topic = Data::id($data, 'tema_id');
        Data::one($db, 'SELECT id FROM temas WHERE id = ?', [$topic]);
        $type = $data['tipo'] ?? '';
        $url = Http::text($data, 'url', 2048);
        $parts = parse_url($url);
        if (!in_array($type, ['video', 'libro', 'enlace'], true) || !filter_var($url, FILTER_VALIDATE_URL) ||
            !is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new HttpError(422, 'Usa un tipo válido y una dirección HTTP o HTTPS sin credenciales.');
        }
        $values = [$topic, Http::text($data, 'titulo', 200), $type, $url, Data::optional($data, 'descripcion'), Data::flag($data)];
        if ($id !== null) {
            Data::one($db, 'SELECT id FROM recursos WHERE id = ?', [$id]);
            Data::query($db, 'UPDATE recursos SET tema_id = ?, titulo = ?, tipo = ?, url = ?, descripcion = ?, activo = ? WHERE id = ?', [...$values, $id]);
        } else {
            Data::query($db, 'INSERT INTO recursos (tema_id, titulo, tipo, url, descripcion, activo) VALUES (?, ?, ?, ?, ?, ?)', $values);
            $id = (int) $db->lastInsertId();
        }
        return ['id' => $id];
    }
    public static function visibleTopic(PDO $db, int $id, array $user): array
    {
        if ($user['rol'] === 'administrador') {
            return Data::one($db, 'SELECT t.*, m.nombre AS materia FROM temas t JOIN materias m ON m.id = t.materia_id WHERE t.id = ?', [$id]);
        }
        return Data::one($db, "SELECT t.*, m.nombre AS materia FROM temas t JOIN materias m ON m.id = t.materia_id JOIN cursos c ON c.id = m.curso_id WHERE t.id = ? AND t.estado = 'publicado' AND m.activo = 1 AND c.activo = 1 AND c.id = ?", [$id, $user['curso_id']]);
    }
    public static function catalog(PDO $db, array $user, array $filters): array
    {
        $course = (int) ($user['curso_id'] ?? 0);
        $subjects = Data::query($db, 'SELECT m.id, m.nombre, m.descripcion FROM materias m JOIN cursos c ON c.id = m.curso_id WHERE m.activo = 1 AND c.activo = 1 AND c.id = ? ORDER BY m.nombre', [$course])->fetchAll();
        $sql = "SELECT t.id, t.materia_id, t.titulo, t.descripcion, t.orden, m.nombre AS materia FROM temas t JOIN materias m ON m.id = t.materia_id JOIN cursos c ON c.id = m.curso_id WHERE t.estado = 'publicado' AND m.activo = 1 AND c.activo = 1 AND c.id = ?";
        $params = [$course];
        if (isset($filters['q']) && $filters['q'] !== '') {
            $search = Http::text($filters, 'q', 200);
            $sql .= ' AND (t.titulo LIKE ? OR t.descripcion LIKE ?)';
            $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%';
        }
        if (!empty($filters['materia_id'])) {
            $subject = filter_var($filters['materia_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($subject === false) { throw new HttpError(422, 'Materia inválida.'); }
            $sql .= ' AND m.id = ?'; $params[] = $subject;
        }
        $topics = Data::query($db, $sql . ' ORDER BY m.nombre, t.orden, t.titulo', $params)->fetchAll();
        return ['materias' => $subjects, 'temas' => $topics];
    }
    public static function detail(PDO $db, int $id, array $user): array
    {
        $topic = self::visibleTopic($db, $id, $user);
        $active = $user['rol'] === 'administrador' ? '' : ' AND activo = 1';
        $materials = Data::query($db, 'SELECT id, titulo, descripcion, tipo, nombre_original, tamano_bytes, activo FROM materiales WHERE tema_id = ?' . $active . ' ORDER BY id DESC', [$id])->fetchAll();
        $resources = Data::query($db, 'SELECT id, titulo, tipo, url, descripcion, activo FROM recursos WHERE tema_id = ?' . $active . ' ORDER BY id DESC', [$id])->fetchAll();
        $quiz = Data::query($db, 'SELECT id, titulo, activo FROM cuestionarios WHERE tema_id = ?' . $active, [$id])->fetch() ?: null;
        return ['tema' => $topic, 'materiales' => $materials, 'recursos' => $resources, 'cuestionario' => $quiz];
    }
}
