<?php
declare(strict_types=1);
// Prueba con las 13 tablas existentes de dbsaru. Todo dato de prueba se revierte.
// No ejecuta CREATE/ALTER/DROP y no carga contenidos permanentes.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/backend/config/Database.php';
$real = Database::connect();
$real->beginTransaction();

final class IsolatedPDO extends PDO
{
    private PDO $inner; private int $depth = 0;
    public function __construct(PDO $inner) { $this->inner = $inner; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return $this->inner->prepare($query, $options); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    { return $fetchMode === null ? $this->inner->query($query) : $this->inner->query($query, $fetchMode, ...$fetchModeArgs); }
    public function exec(string $statement): int|false { return $this->inner->exec($statement); }
    public function lastInsertId(?string $name = null): string|false { return $this->inner->lastInsertId($name); }
    public function beginTransaction(): bool { $this->depth++; $this->inner->exec('SAVEPOINT saru_test_' . $this->depth); return true; }
    public function commit(): bool { $this->inner->exec('RELEASE SAVEPOINT saru_test_' . $this->depth); $this->depth--; return true; }
    public function rollBack(): bool { $this->inner->exec('ROLLBACK TO SAVEPOINT saru_test_' . $this->depth); $this->inner->exec('RELEASE SAVEPOINT saru_test_' . $this->depth); $this->depth--; return true; }
    public function inTransaction(): bool { return $this->depth > 0; }
}
$db = new IsolatedPDO($real);
$temporary = sys_get_temp_dir() . '/saru-mysql-' . bin2hex(random_bytes(8));
mkdir($temporary . '/storage', 0700, true); mkdir($temporary . '/sessions', 0700, true);
foreach (['services/Http.php', 'services/Data.php', 'services/RateLimit.php', 'models/Usuario.php', 'middleware/Auth.php',
    'controllers/AuthController.php', 'controllers/StudentController.php', 'controllers/ContentController.php', 'controllers/QuizController.php',
    'controllers/AttemptController.php', 'controllers/MaterialController.php', 'controllers/InstallController.php', 'services/LearningRoutes.php'] as $file) {
    $dest = $temporary . '/backend/' . $file;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0700, true);
    copy(dirname(__DIR__) . '/backend/' . $file, $dest); require $dest;
}
ini_set('session.save_path', $temporary . '/sessions'); $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; Auth::start();
$checks = [];
function verify(bool $condition, string $label): void { global $checks; if (!$condition) throw new RuntimeException('FALLÓ: ' . $label); $checks[] = $label; }
function deny(callable $call, int $status, string $label): void { try { $call(); } catch (HttpError $e) { verify($e->status === $status, $label); return; } throw new RuntimeException('No rechazó: ' . $label); }
function removeTestFiles(string $directory): void { foreach (new FilesystemIterator($directory) as $f) { if ($f->isDir()) removeTestFiles($f->getPathname()); else unlink($f->getPathname()); } rmdir($directory); }
try {
    $suffix = bin2hex(random_bytes(5));
    $course = (int) Data::one($db, 'SELECT id FROM cursos WHERE activo = 1 ORDER BY id LIMIT 1')['id'];
    $newStudent = ['nombres' => 'Prueba', 'apellidos' => 'Transaccional', 'usuario' => 'test.' . $suffix, 'curso_id' => $course, 'password' => bin2hex(random_bytes(18))];
    $studentId = StudentController::create($db, $newStudent)['id'];
    $users = new Usuario($db); $user = $users->find($studentId);
    $other = array_replace($newStudent, ['usuario' => 'otro.' . $suffix]);
    $otherUser = $users->find(StudentController::create($db, $other)['id']);
    AuthController::login($db, $users, $newStudent);
    verify((bool) Auth::user($users, true)['cambiar_password'], 'Login real exige cambio temporal');
    AuthController::password($db, $users, ['actual' => $newStudent['password'], 'nueva' => bin2hex(random_bytes(18))]);
    $user = Auth::user($users);
    verify(!(bool) $user['cambiar_password'], 'Cambio de contraseña real con MySQL');
    $subjectId = ContentController::subject($db, ['curso_id' => $course, 'nombre' => 'Prueba transaccional ' . $suffix, 'descripcion' => '', 'activo' => true], null)['id'];
    $topicData = ['materia_id' => $subjectId, 'titulo' => 'Tema transaccional ' . $suffix, 'descripcion' => 'Texto de búsqueda exclusivo ' . $suffix, 'orden' => 1, 'estado' => 'borrador'];
    $topicId = ContentController::topic($db, $topicData, null)['id'];
    deny(static fn() => ContentController::visibleTopic($db, $topicId, $user), 404, 'Estudiante no ve borradores');
    verify(count(ContentController::catalog($db, $user, ['q' => $suffix])['temas']) === 0, 'Buscador excluye borradores');
    $topicData['estado'] = 'publicado'; ContentController::topic($db, $topicData, $topicId);
    verify(count(ContentController::catalog($db, $user, ['q' => $suffix])['temas']) === 1, 'Busca temas publicados por descripción');
    verify(count(ContentController::catalog($db, $user, ['materia_id' => (string) $subjectId])['temas']) === 1, 'Filtra por materia y curso del estudiante');
    deny(static fn() => ContentController::resource($db, ['tema_id' => $topicId, 'titulo' => 'Inválido', 'tipo' => 'enlace', 'url' => 'javascript:alert(1)', 'activo' => true], null), 422, 'Rechaza enlaces ejecutables');
    $resourceId = ContentController::resource($db, ['tema_id' => $topicId, 'titulo' => 'Recurso de prueba', 'tipo' => 'enlace', 'url' => 'https://example.com/', 'activo' => true], null)['id'];
    verify(count(ContentController::detail($db, $topicId, $user)['recursos']) === 1, 'Devuelve recursos activos del tema');
    $quizId = QuizController::save($db, ['tema_id' => $topicId, 'titulo' => 'Prueba transaccional', 'activo' => false], null)['id'];
    deny(static fn() => QuizController::save($db, ['titulo' => 'Prueba', 'activo' => true], $quizId), 422, 'No activa cuestionarios sin 10 preguntas');
    $question = ['enunciado' => 'Pregunta sintética de prueba', 'explicacion' => 'Explicación privada de prueba', 'activo' => true,
        'opciones' => [['texto' => 'Opción uno', 'es_correcta' => true], ['texto' => 'Opción dos', 'es_correcta' => false], ['texto' => 'Opción tres', 'es_correcta' => false], ['texto' => 'Opción cuatro', 'es_correcta' => false]]];
    deny(static fn() => QuizController::question($db, $quizId, array_replace($question, ['opciones' => array_slice($question['opciones'], 0, 3)]), null), 422, 'Requiere cuatro alternativas');
    $invalid = $question; $invalid['opciones'][1]['es_correcta'] = true;
    deny(static fn() => QuizController::question($db, $quizId, $invalid, null), 422, 'Rechaza dos respuestas correctas');
    for ($i = 1; $i <= 15; $i++) { $question['enunciado'] = 'Pregunta sintética ' . $i; QuizController::question($db, $quizId, $question, null); }
    QuizController::save($db, ['titulo' => 'Prueba transaccional', 'activo' => true], $quizId);
    verify(count(QuizController::validQuestions($db, $quizId)) === 15, 'Banco válido con 15 preguntas');
    $attemptId = AttemptController::start($db, $user, ['cuestionario_id' => $quizId])['id'];
    $attempt = AttemptController::detail($db, $attemptId, $user);
    verify(count($attempt['preguntas']) === 10, 'Intento selecciona exactamente 10 preguntas');
    $origins = Data::query($db, 'SELECT pregunta_origen_id FROM intento_preguntas WHERE intento_id = ?', [$attemptId])->fetchAll(PDO::FETCH_COLUMN);
    verify(count(array_unique($origins)) === 10, 'No repite preguntas dentro del intento');
    foreach ($attempt['preguntas'] as $p) {
        verify(count($p['opciones']) === 4 && !isset($p['explicacion']) && !isset($p['opciones'][0]['es_correcta']), 'Cuatro opciones sin clave ni explicación antes de finalizar');
    }
    verify(AttemptController::start($db, $user, ['cuestionario_id' => $quizId])['id'] === $attemptId, 'Doble inicio retoma el mismo intento pendiente');
    deny(static fn() => AttemptController::detail($db, $attemptId, $otherUser), 404, 'Otro estudiante no accede al intento');
    deny(static fn() => AttemptController::finish($db, $attemptId, $user), 422, 'Impide finalizar sin diez respuestas');
    $first = $attempt['preguntas'][0]; $second = $attempt['preguntas'][1];
    deny(static fn() => AttemptController::answer($db, $attemptId, $user, ['pregunta_id' => (int) $first['id'], 'opcion_id' => (int) $second['opciones'][0]['id']]), 404, 'Impide alternativa de otra pregunta');
    foreach ($attempt['preguntas'] as $p) {
        $correctId = (int) Data::one($db, 'SELECT id FROM intento_opciones WHERE intento_pregunta_id = ? AND es_correcta = 1', [$p['id']])['id'];
        AttemptController::answer($db, $attemptId, $user, ['pregunta_id' => (int) $p['id'], 'opcion_id' => $correctId]);
    }
    $wrong = (int) Data::one($db, 'SELECT id FROM intento_opciones WHERE intento_pregunta_id = ? AND es_correcta = 0 LIMIT 1', [$first['id']])['id'];
    AttemptController::answer($db, $attemptId, $user, ['pregunta_id' => (int) $first['id'], 'opcion_id' => $wrong]);
    verify(AttemptController::detail($db, $attemptId, $user)['preguntas'][0]['seleccionada'] === $wrong, 'Permite cambiar respuesta y retomarla');
    $score = AttemptController::finish($db, $attemptId, $user);
    verify($score['aciertos'] === 9 && $score['calificacion'] === 90, 'Calcula nueve aciertos como 90/100');
    verify(AttemptController::finish($db, $attemptId, $user)['calificacion'] === 90.0, 'Finalización idempotente');
    deny(static fn() => AttemptController::answer($db, $attemptId, $user, ['pregunta_id' => (int) $first['id'], 'opcion_id' => $wrong]), 409, 'No modifica respuestas finalizadas');
    $final = AttemptController::detail($db, $attemptId, $user);
    verify(isset($final['preguntas'][0]['explicacion'], $final['preguntas'][0]['opciones'][0]['es_correcta']), 'Muestra corrección y explicación al finalizar');
    $originId = (int) $origins[0]; $changed = $question; $changed['enunciado'] = 'Modificado en el banco';
    QuizController::question($db, $quizId, $changed, $originId);
    verify(AttemptController::detail($db, $attemptId, $user)['preguntas'] === $final['preguntas'], 'Editar origen conserva copias históricas inmutables');
    $nextId = AttemptController::start($db, $user, ['cuestionario_id' => $quizId])['id'];
    verify($nextId !== $attemptId && count(AttemptController::history($db, $user)['intentos']) === 2, 'Permite repetir y conserva ambos intentos');
    verify(count(AttemptController::history($db, $otherUser)['intentos']) === 0, 'Historial separado por estudiante');
    $all = QuizController::questions($db, $quizId)['preguntas'];
    foreach (array_slice($all, 0, 6) as $p) { $disabled = $question; $disabled['activo'] = false; QuizController::question($db, $quizId, $disabled, (int) $p['id']); }
    verify(!(bool) Data::one($db, 'SELECT activo FROM cuestionarios WHERE id = ?', [$quizId])['activo'], 'Desactiva cuestionario al quedar menos de diez válidas');
    $pdf = $temporary . '/fixture.pdf'; file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    MaterialController::validateUpload(['error' => 0, 'tmp_name' => $pdf, 'name' => 'prueba.pdf']);
    verify(true, 'Valida extensión, MIME y firma PDF');
    deny(static fn() => MaterialController::validateUpload(['error' => 0, 'tmp_name' => $pdf, 'name' => 'prueba.php']), 422, 'Rechaza extensión ejecutable');
    file_put_contents($pdf, 'Esto no es un PDF');
    deny(static fn() => MaterialController::validateUpload(['error' => 0, 'tmp_name' => $pdf, 'name' => 'prueba.pdf']), 422, 'Rechaza archivos disfrazados de PDF');
    verify(LearningRoutes::methods('admin/preguntas/1') === ['POST'] && LearningRoutes::methods('intentos/1') === ['GET'], 'Rutas y métodos de aprendizaje consistentes');
    $real->rollBack();
    verify((int) Data::query($real, 'SELECT COUNT(*) FROM usuarios WHERE usuario = ?', [$newStudent['usuario']])->fetchColumn() === 0, 'Rollback elimina todos los datos de prueba');
    echo implode(PHP_EOL, array_map(static fn($v) => 'OK ' . $v, $checks)) . PHP_EOL;
    echo 'TOTAL: ' . count($checks) . " comprobaciones MySQL correctas. Sin datos de prueba persistentes.\n";
} finally {
    if ($real->inTransaction()) $real->rollBack();
    if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION = []; session_destroy(); }
    removeTestFiles($temporary);
}
