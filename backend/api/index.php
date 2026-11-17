<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return false; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once dirname(__DIR__) . '/services/Http.php';
require_once dirname(__DIR__) . '/config/Database.php';
require_once dirname(__DIR__) . '/services/RateLimit.php';
require_once dirname(__DIR__) . '/models/Usuario.php';
require_once dirname(__DIR__) . '/middleware/Auth.php';
require_once dirname(__DIR__) . '/controllers/AuthController.php';
require_once dirname(__DIR__) . '/controllers/InstallController.php';
require_once dirname(__DIR__) . '/controllers/StudentController.php';
require_once dirname(__DIR__) . '/services/Data.php';
require_once dirname(__DIR__) . '/controllers/ContentController.php';
require_once dirname(__DIR__) . '/controllers/QuizController.php';
require_once dirname(__DIR__) . '/controllers/AttemptController.php';
require_once dirname(__DIR__) . '/controllers/MaterialController.php';
require_once dirname(__DIR__) . '/services/LearningRoutes.php';

try {
    Auth::start();
    $method = $_SERVER['REQUEST_METHOD'];
    $route = $_GET['route'] ?? '';
    if (!is_string($route)) { throw new HttpError(400, 'Ruta inválida.'); }
    $route = trim($route, '/');
    if ($method === 'GET' && $route === 'sesion') {
        $user = null;
        if (isset($_SESSION['user_id'])) {
            try { $user = Usuario::publicData(Auth::user(new Usuario(Database::connect()), true)); }
            catch (HttpError $e) { if ($e->status !== 401) { throw $e; } }
        }
        Http::respond(200, 'Sesión consultada.', ['usuario' => $user, 'csrf' => $_SESSION['csrf']]);
    }
    if ($method === 'POST') { Auth::csrf(); }
    if ($method === 'POST' && $route === 'auth/logout') {
        Auth::logout();
        Http::respond(200, 'Sesión cerrada.');
    }
    $known = ['salud' => ['GET'], 'instalacion' => ['GET', 'POST'], 'auth/login' => ['POST'],
        'auth/password' => ['POST'], 'cursos' => ['GET'], 'resumen' => ['GET'], 'estudiantes' => ['GET', 'POST']];
    $studentRoute = preg_match('#^estudiantes/([1-9][0-9]*)/(datos|estado|password)$#D', $route, $match);
    $learning = LearningRoutes::methods($route);
    if (!isset($known[$route]) && !$studentRoute && $learning === null) { throw new HttpError(404, 'Ruta no encontrada.'); }
    $allowed = $learning ?? ($studentRoute ? ['POST'] : $known[$route]);
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        throw new HttpError(405, 'Método no permitido.');
    }
    $db = Database::connect();
    $users = new Usuario($db);
    if ($learning !== null) { LearningRoutes::dispatch($db, $users, $route, $method); }
    if ($route === 'salud') {
        // La prueba no modifica datos; confirma las columnas usadas durante esta fase.
        $db->query('SELECT id, curso_id, nombres, apellidos, usuario, password_hash, rol, activo, cambiar_password, creado_en FROM usuarios LIMIT 0');
        $db->query('SELECT id, nombre, activo FROM cursos LIMIT 0');
        Http::respond(200, 'PHP y MySQL disponibles.', ['base_de_datos' => 'dbsaru']);
    }
    if ($route === 'instalacion') {
        if ($method === 'GET') { Http::respond(200, 'Estado de instalación.', ['disponible' => InstallController::available($db)]); }
        Http::respond(201, 'Administrador creado. Ya puedes iniciar sesión.', InstallController::create($db, Http::body()));
    }
    if ($route === 'auth/login') { Http::respond(200, 'Sesión iniciada.', AuthController::login($db, $users, Http::body())); }
    if ($route === 'auth/password') { Http::respond(200, 'Contraseña actualizada.', AuthController::password($db, $users, Http::body())); }
    Auth::admin($users);
    if ($route === 'cursos') { Http::respond(200, 'Cursos disponibles.', ['cursos' => $db->query('SELECT id, nombre FROM cursos WHERE activo = 1 ORDER BY nombre')->fetchAll()]); }
    if ($route === 'resumen') {
        $stats = $db->query("SELECT COUNT(*) AS total, COALESCE(SUM(activo = 1), 0) AS activos, COALESCE(SUM(activo = 0), 0) AS inactivos, COALESCE(SUM(cambiar_password = 1), 0) AS pendientes FROM usuarios WHERE rol = 'estudiante'")->fetch();
        foreach (['materias', 'temas', 'cuestionarios'] as $table) { $stats[$table] = $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
        Http::respond(200, 'Resumen de estudiantes.', array_map('intval', $stats));
    }
    if ($route === 'estudiantes') {
        if ($method === 'GET') { Http::respond(200, 'Estudiantes consultados.', StudentController::list($db)); }
        Http::respond(201, 'Estudiante registrado con contraseña temporal.', StudentController::create($db, Http::body()));
    }
    if ($studentRoute) { Http::respond(200, 'Estudiante actualizado.', StudentController::update($db, (int) $match[1], $match[2], Http::body())); }
} catch (HttpError $e) {
    Http::respond($e->status, $e->getMessage());
} catch (PDOException $e) {
    error_log('SARU PDO: ' . $e->getMessage());
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) { Http::respond(409, 'Ya existe un registro con ese usuario, nombre o tema. Cada tema admite un solo cuestionario.'); }
    if (in_array((int) ($e->errorInfo[1] ?? 0), [1451, 1452], true)) { Http::respond(422, 'La operación contiene una referencia no disponible. Recarga los datos.'); }
    Http::respond(503, 'No se pudo acceder a dbsaru. Revisa la configuración local y el servicio MySQL.');
} catch (Throwable $e) {
    error_log('SARU: ' . $e->getMessage());
    Http::respond(503, 'El servicio no está disponible. Revisa la configuración y el registro de PHP.');
}
