<?php
declare(strict_types=1);

final class QuizController
{
    public static function validQuestions(PDO $db, int $id): array
    {
        return Data::query($db, "SELECT p.id, p.enunciado, p.explicacion FROM preguntas p JOIN opciones o ON o.pregunta_id = p.id WHERE p.cuestionario_id = ? AND p.activo = 1 AND TRIM(p.enunciado) <> '' GROUP BY p.id, p.enunciado, p.explicacion HAVING COUNT(o.id) = 4 AND SUM(o.es_correcta = 1) = 1 AND SUM(o.es_correcta IN (0, 1)) = 4 AND SUM(TRIM(o.texto) <> '') = 4 ORDER BY p.id", [$id])->fetchAll();
    }
    public static function list(PDO $db): array
    {
        $items = Data::query($db, 'SELECT q.*, t.titulo AS tema, m.nombre AS materia FROM cuestionarios q JOIN temas t ON t.id = q.tema_id JOIN materias m ON m.id = t.materia_id ORDER BY q.id DESC')->fetchAll();
        foreach ($items as &$item) { $item['preguntas_validas'] = count(self::validQuestions($db, (int) $item['id'])); }
        return ['cuestionarios' => $items];
    }
    public static function save(PDO $db, array $data, ?int $id): array
    {
        $title = Http::text($data, 'titulo', 200);
        $active = Data::flag($data);
        return Data::transaction($db, static function () use ($db, $data, $id, $title, $active) {
            if ($id === null) {
                $topic = Data::id($data, 'tema_id');
                Data::one($db, 'SELECT id FROM temas WHERE id = ? FOR UPDATE', [$topic]);
                if ($active) { throw new HttpError(422, 'Crea el cuestionario inactivo y agrega al menos 10 preguntas válidas.'); }
                Data::query($db, 'INSERT INTO cuestionarios (tema_id, titulo, activo) VALUES (?, ?, 0)', [$topic, $title]);
                return ['id' => (int) $db->lastInsertId()];
            }
            Data::one($db, 'SELECT id FROM cuestionarios WHERE id = ? FOR UPDATE', [$id]);
            if ($active && count(self::validQuestions($db, $id)) < 10) { throw new HttpError(422, 'Se necesitan al menos 10 preguntas activas con cuatro opciones y una correcta.'); }
            Data::query($db, 'UPDATE cuestionarios SET titulo = ?, activo = ? WHERE id = ?', [$title, $active, $id]);
            return ['id' => $id];
        });
    }
    public static function questions(PDO $db, int $id): array
    {
        $quiz = Data::one($db, 'SELECT * FROM cuestionarios WHERE id = ?', [$id]);
        $items = Data::query($db, 'SELECT * FROM preguntas WHERE cuestionario_id = ? ORDER BY id', [$id])->fetchAll();
        foreach ($items as &$item) {
            $item['opciones'] = Data::query($db, 'SELECT id, texto, es_correcta, orden FROM opciones WHERE pregunta_id = ? ORDER BY orden', [$item['id']])->fetchAll();
        }
        return ['cuestionario' => $quiz, 'preguntas' => $items];
    }
    public static function question(PDO $db, int $quizId, array $data, ?int $id): array
    {
        $statement = Http::text($data, 'enunciado', 10000);
        $explanation = Data::optional($data, 'explicacion');
        $active = Data::flag($data);
        $options = $data['opciones'] ?? null;
        if (!is_array($options) || array_keys($options) !== [0, 1, 2, 3]) { throw new HttpError(422, 'Cada pregunta requiere exactamente cuatro opciones.'); }
        $valid = []; $correct = 0;
        foreach ($options as $option) {
            if (!is_array($option)) { throw new HttpError(422, 'Opción inválida.'); }
            $flag = Data::flag($option, 'es_correcta'); $correct += $flag;
            $valid[] = [Http::text($option, 'texto', 4000), $flag];
        }
        if ($correct !== 1) { throw new HttpError(422, 'Marca exactamente una opción correcta.'); }
        return Data::transaction($db, static function () use ($db, $quizId, $id, $statement, $explanation, $active, $valid) {
            Data::one($db, 'SELECT id FROM cuestionarios WHERE id = ? FOR UPDATE', [$quizId]);
            if ($id === null) {
                Data::query($db, 'INSERT INTO preguntas (cuestionario_id, enunciado, explicacion, activo) VALUES (?, ?, ?, ?)', [$quizId, $statement, $explanation, $active]);
                $id = (int) $db->lastInsertId();
            } else {
                Data::one($db, 'SELECT id FROM preguntas WHERE id = ? AND cuestionario_id = ?', [$id, $quizId]);
                Data::query($db, 'UPDATE preguntas SET enunciado = ?, explicacion = ?, activo = ? WHERE id = ?', [$statement, $explanation, $active, $id]);
                // Opciones de origen, nunca las copias inmutables de los intentos.
                Data::query($db, 'DELETE FROM opciones WHERE pregunta_id = ?', [$id]);
            }
            foreach ($valid as $index => [$text, $flag]) {
                Data::query($db, 'INSERT INTO opciones (pregunta_id, texto, es_correcta, orden) VALUES (?, ?, ?, ?)', [$id, $text, $flag, $index + 1]);
            }
            if (count(self::validQuestions($db, $quizId)) < 10) { Data::query($db, 'UPDATE cuestionarios SET activo = 0 WHERE id = ?', [$quizId]); }
            return ['id' => $id];
        });
    }
}
