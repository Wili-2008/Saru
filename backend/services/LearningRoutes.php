<?php
declare(strict_types=1);

final class LearningRoutes
{
    public static function methods(string $route): ?array
    {
        if (in_array($route, ['admin/materias', 'admin/temas', 'admin/recursos', 'admin/materiales', 'admin/cuestionarios'], true)) { return ['GET', 'POST']; }
        if (preg_match('#^admin/(materias|temas|recursos|materiales|cuestionarios)/[1-9][0-9]*$#D', $route)) { return ['POST']; }
        if (preg_match('#^admin/cuestionarios/[1-9][0-9]*/preguntas$#D', $route)) { return ['GET', 'POST']; }
        if (preg_match('#^admin/preguntas/[1-9][0-9]*$#D', $route)) { return ['POST']; }
        if (in_array($route, ['catalogo', 'historial', 'admin/resultados'], true) || preg_match('#^(catalogo/temas|intentos)/[1-9][0-9]*$#D', $route) || preg_match('#^materiales/[1-9][0-9]*/archivo$#D', $route)) { return ['GET']; }
        if ($route === 'intentos' || preg_match('#^intentos/[1-9][0-9]*/(respuesta|finalizar)$#D', $route)) { return ['POST']; }
        return null;
    }
    public static function dispatch(PDO $db, Usuario $users, string $route, string $method): void
    {
        $user = str_starts_with($route, 'admin/') ? Auth::admin($users) : Auth::user($users);
        $data = [];
        if ($method === 'POST') {
            if (preg_match('#^admin/materiales(?:/[1-9][0-9]*)?$#D', $route)) {
                try { $data = json_decode($_POST['datos'] ?? '', true, 32, JSON_THROW_ON_ERROR); }
                catch (JsonException $e) { throw new HttpError(422, 'No se recibieron los datos del material. Revisa el límite post_max_size de PHP.'); }
                if (!is_array($data)) { throw new HttpError(422, 'Datos de material inválidos.'); }
            } else { $data = Http::body(); }
        }
        if ($route === 'admin/materias') {
            Http::respond($method === 'GET' ? 200 : 201, 'Materias disponibles.', $method === 'GET' ? ContentController::subjects($db) : ContentController::subject($db, $data, null));
        }
        if ($route === 'admin/temas') { Http::respond($method === 'GET' ? 200 : 201, 'Temas disponibles.', $method === 'GET' ? ContentController::topics($db) : ContentController::topic($db, $data, null)); }
        if ($route === 'admin/recursos') { Http::respond($method === 'GET' ? 200 : 201, 'Recursos disponibles.', $method === 'GET' ? ContentController::resources($db) : ContentController::resource($db, $data, null)); }
        if ($route === 'admin/materiales') { Http::respond($method === 'GET' ? 200 : 201, 'Materiales disponibles.', $method === 'GET' ? MaterialController::list($db) : MaterialController::save($db, $data, null, $_FILES['archivo'] ?? null)); }
        if ($route === 'admin/cuestionarios') { Http::respond($method === 'GET' ? 200 : 201, 'Cuestionarios disponibles.', $method === 'GET' ? QuizController::list($db) : QuizController::save($db, $data, null)); }
        if (preg_match('#^admin/(materias|temas|recursos|materiales|cuestionarios)/([1-9][0-9]*)$#D', $route, $m)) {
            $id = (int) $m[2];
            switch ($m[1]) {
                case 'materias': $result = ContentController::subject($db, $data, $id); break;
                case 'temas': $result = ContentController::topic($db, $data, $id); break;
                case 'recursos': $result = ContentController::resource($db, $data, $id); break;
                case 'materiales': $result = MaterialController::save($db, $data, $id, $_FILES['archivo'] ?? null); break;
                default: $result = QuizController::save($db, $data, $id);
            }
            Http::respond(200, 'Cambios guardados.', $result);
        }
        if (preg_match('#^admin/cuestionarios/([1-9][0-9]*)/preguntas$#D', $route, $m)) {
            Http::respond($method === 'GET' ? 200 : 201, 'Banco de preguntas actualizado.', $method === 'GET' ? QuizController::questions($db, (int) $m[1]) : QuizController::question($db, (int) $m[1], $data, null));
        }
        if (preg_match('#^admin/preguntas/([1-9][0-9]*)$#D', $route, $m)) {
            $question = Data::one($db, 'SELECT cuestionario_id FROM preguntas WHERE id = ?', [(int) $m[1]]);
            Http::respond(200, 'Pregunta actualizada.', QuizController::question($db, (int) $question['cuestionario_id'], $data, (int) $m[1]));
        }
        if ($route === 'catalogo') { Http::respond(200, 'Contenidos disponibles.', ContentController::catalog($db, $user, $_GET)); }
        if (preg_match('#^catalogo/temas/([1-9][0-9]*)$#D', $route, $m)) { Http::respond(200, 'Tema disponible.', ContentController::detail($db, (int) $m[1], $user)); }
        if (preg_match('#^materiales/([1-9][0-9]*)/archivo$#D', $route, $m)) { MaterialController::file($db, (int) $m[1], $user); }
        if ($route === 'intentos') { Http::respond(201, 'Evaluación preparada.', AttemptController::start($db, $user, $data)); }
        if ($route === 'historial' || $route === 'admin/resultados') { Http::respond(200, 'Historial disponible.', AttemptController::history($db, $user)); }
        if (preg_match('#^intentos/([1-9][0-9]*)$#D', $route, $m)) { Http::respond(200, 'Evaluación disponible.', AttemptController::detail($db, (int) $m[1], $user)); }
        if (preg_match('#^intentos/([1-9][0-9]*)/(respuesta|finalizar)$#D', $route, $m)) {
            Http::respond(200, $m[2] === 'respuesta' ? 'Respuesta guardada.' : 'Evaluación finalizada.', $m[2] === 'respuesta' ? AttemptController::answer($db, (int) $m[1], $user, $data) : AttemptController::finish($db, (int) $m[1], $user));
        }
        throw new HttpError(404, 'Ruta no encontrada.');
    }
}
