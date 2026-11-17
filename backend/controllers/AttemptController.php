<?php
declare(strict_types=1);

final class AttemptController
{
    public static function start(PDO $db, array $user, array $data): array
    {
        if ($user['rol'] !== 'estudiante') { throw new HttpError(403, 'Las evaluaciones se resuelven con una cuenta de estudiante.'); }
        $quizId = Data::id($data, 'cuestionario_id');
        return Data::transaction($db, static function () use ($db, $user, $quizId) {
            Data::one($db, 'SELECT id FROM usuarios WHERE id = ? FOR UPDATE', [$user['id']]);
            $quiz = Data::one($db, 'SELECT id, tema_id, activo FROM cuestionarios WHERE id = ? FOR UPDATE', [$quizId]);
            ContentController::visibleTopic($db, (int) $quiz['tema_id'], $user);
            if (!(bool) $quiz['activo']) { throw new HttpError(422, 'El cuestionario todavía no está disponible.'); }
            $existing = Data::query($db, "SELECT id FROM intentos WHERE usuario_id = ? AND cuestionario_id = ? AND estado = 'en_progreso' ORDER BY id DESC LIMIT 1", [$user['id'], $quizId])->fetchColumn();
            if ($existing !== false) { return ['id' => (int) $existing, 'retomado' => true]; }
            $questions = QuizController::validQuestions($db, $quizId);
            if (count($questions) < 10) { throw new HttpError(422, 'Este cuestionario necesita al menos 10 preguntas activas válidas.'); }
            shuffle($questions); $questions = array_slice($questions, 0, 10);
            Data::query($db, "INSERT INTO intentos (usuario_id, cuestionario_id, estado) VALUES (?, ?, 'en_progreso')", [$user['id'], $quizId]);
            $attemptId = (int) $db->lastInsertId();
            foreach ($questions as $index => $question) {
                Data::query($db, 'INSERT INTO intento_preguntas (intento_id, pregunta_origen_id, enunciado, explicacion, orden) VALUES (?, ?, ?, ?, ?)', [$attemptId, $question['id'], $question['enunciado'], $question['explicacion'], $index + 1]);
                $questionId = (int) $db->lastInsertId();
                $options = Data::query($db, 'SELECT texto, es_correcta FROM opciones WHERE pregunta_id = ? ORDER BY orden', [$question['id']])->fetchAll();
                if (count($options) !== 4) { throw new HttpError(409, 'El cuestionario cambió. Intenta nuevamente.'); }
                shuffle($options);
                foreach ($options as $order => $option) {
                    Data::query($db, 'INSERT INTO intento_opciones (intento_pregunta_id, texto, es_correcta, orden) VALUES (?, ?, ?, ?)', [$questionId, $option['texto'], $option['es_correcta'], $order + 1]);
                }
            }
            return ['id' => $attemptId, 'retomado' => false];
        });
    }
    public static function owned(PDO $db, int $id, array $user, bool $lock = false): array
    {
        $sql = 'SELECT * FROM intentos WHERE id = ?'; $params = [$id];
        if ($user['rol'] !== 'administrador') { $sql .= ' AND usuario_id = ?'; $params[] = $user['id']; }
        if ($lock) { $sql .= ' FOR UPDATE'; }
        return Data::one($db, $sql, $params);
    }
    public static function detail(PDO $db, int $id, array $user): array
    {
        $attempt = self::owned($db, $id, $user);
        $done = $attempt['estado'] === 'finalizado';
        $info = Data::one($db, 'SELECT q.titulo, t.titulo AS tema, m.nombre AS materia FROM cuestionarios q JOIN temas t ON t.id = q.tema_id JOIN materias m ON m.id = t.materia_id WHERE q.id = ?', [$attempt['cuestionario_id']]);
        $questions = Data::query($db, 'SELECT id, enunciado, orden' . ($done ? ', explicacion' : '') . ' FROM intento_preguntas WHERE intento_id = ? ORDER BY orden', [$id])->fetchAll();
        foreach ($questions as &$question) {
            $question['opciones'] = Data::query($db, 'SELECT id, texto, orden' . ($done ? ', es_correcta' : '') . ' FROM intento_opciones WHERE intento_pregunta_id = ? ORDER BY orden', [$question['id']])->fetchAll();
            $answer = Data::query($db, 'SELECT intento_opcion_id FROM respuestas_estudiante WHERE intento_pregunta_id = ?', [$question['id']])->fetchColumn();
            $question['seleccionada'] = $answer === false ? null : (int) $answer;
        }
        return ['intento' => array_merge($attempt, $info), 'preguntas' => $questions];
    }
    public static function answer(PDO $db, int $id, array $user, array $data): array
    {
        if ($user['rol'] !== 'estudiante') { throw new HttpError(403, 'Solo el estudiante puede responder su evaluación.'); }
        $questionId = Data::id($data, 'pregunta_id'); $optionId = Data::id($data, 'opcion_id');
        return Data::transaction($db, static function () use ($db, $id, $user, $questionId, $optionId) {
            $attempt = self::owned($db, $id, $user, true);
            if ($attempt['estado'] !== 'en_progreso') { throw new HttpError(409, 'Esta evaluación ya fue finalizada.'); }
            Data::one($db, 'SELECT o.id FROM intento_opciones o JOIN intento_preguntas p ON p.id = o.intento_pregunta_id WHERE o.id = ? AND p.id = ? AND p.intento_id = ?', [$optionId, $questionId, $id]);
            $existing = Data::query($db, 'SELECT id FROM respuestas_estudiante WHERE intento_pregunta_id = ?', [$questionId])->fetchColumn();
            if ($existing === false) {
                Data::query($db, 'INSERT INTO respuestas_estudiante (intento_pregunta_id, intento_opcion_id) VALUES (?, ?)', [$questionId, $optionId]);
            } else {
                Data::query($db, 'UPDATE respuestas_estudiante SET intento_opcion_id = ?, respondido_en = CURRENT_TIMESTAMP WHERE id = ?', [$optionId, $existing]);
            }
            return ['pregunta_id' => $questionId, 'opcion_id' => $optionId];
        });
    }
    public static function finish(PDO $db, int $id, array $user): array
    {
        if ($user['rol'] !== 'estudiante') { throw new HttpError(403, 'Solo el estudiante puede finalizar su evaluación.'); }
        return Data::transaction($db, static function () use ($db, $id, $user) {
            $attempt = self::owned($db, $id, $user, true);
            if ($attempt['estado'] === 'finalizado') { return ['id' => $id, 'aciertos' => (int) $attempt['aciertos'], 'calificacion' => (float) $attempt['calificacion']]; }
            $count = (int) Data::query($db, 'SELECT COUNT(*) FROM intento_preguntas WHERE intento_id = ?', [$id])->fetchColumn();
            $score = Data::one($db, 'SELECT COUNT(*) AS respondidas, COALESCE(SUM(o.es_correcta = 1), 0) AS aciertos FROM intento_preguntas p JOIN respuestas_estudiante r ON r.intento_pregunta_id = p.id JOIN intento_opciones o ON o.id = r.intento_opcion_id AND o.intento_pregunta_id = p.id WHERE p.intento_id = ?', [$id]);
            if ($count !== 10 || (int) $score['respondidas'] !== 10) { throw new HttpError(422, 'Responde las 10 preguntas antes de finalizar.'); }
            $hits = (int) $score['aciertos'];
            Data::query($db, "UPDATE intentos SET aciertos = ?, calificacion = ?, finalizado_en = CURRENT_TIMESTAMP, estado = 'finalizado' WHERE id = ? AND estado = 'en_progreso'", [$hits, $hits * 10, $id]);
            return ['id' => $id, 'aciertos' => $hits, 'calificacion' => $hits * 10];
        });
    }
    public static function history(PDO $db, array $user): array
    {
        $sql = 'SELECT i.*, q.titulo, t.titulo AS tema, m.nombre AS materia, u.nombres, u.apellidos, u.usuario FROM intentos i JOIN cuestionarios q ON q.id = i.cuestionario_id JOIN temas t ON t.id = q.tema_id JOIN materias m ON m.id = t.materia_id JOIN usuarios u ON u.id = i.usuario_id';
        $params = [];
        if ($user['rol'] !== 'administrador') { $sql .= ' WHERE i.usuario_id = ?'; $params[] = $user['id']; }
        return ['intentos' => Data::query($db, $sql . ' ORDER BY i.iniciado_en DESC, i.id DESC', $params)->fetchAll()];
    }
}
