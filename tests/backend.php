<?php
declare(strict_types=1);
// Pruebas aisladas: SQLite en memoria; jamás se conecta ni modifica dbsaru.
// GET_LOCK se simula y FOR UPDATE se omite: la concurrencia MySQL requiere prueba real.
if (PHP_SAPI !== 'cli') { exit; }
$temporary = sys_get_temp_dir() . '/saru-test-' . bin2hex(random_bytes(8));
mkdir($temporary . '/storage', 0700, true);
mkdir($temporary . '/sessions', 0700, true);
$files = ['services/Http.php', 'services/RateLimit.php', 'models/Usuario.php', 'middleware/Auth.php',
    'controllers/AuthController.php', 'controllers/StudentController.php', 'controllers/InstallController.php'];
foreach ($files as $file) {
    $dest = $temporary . '/backend/' . $file;
    if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0700, true); }
    copy(dirname(__DIR__) . '/backend/' . $file, $dest);
    require $dest;
}

class TestDatabase extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new TestDatabase('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('GET_LOCK', static fn() => 1);
$db->sqliteCreateFunction('RELEASE_LOCK', static fn() => 1);
$db->exec("CREATE TABLE cursos (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, activo INTEGER NOT NULL DEFAULT 1);
CREATE TABLE usuarios (id INTEGER PRIMARY KEY AUTOINCREMENT, curso_id INTEGER NULL, nombres TEXT NOT NULL, apellidos TEXT NOT NULL,
usuario TEXT UNIQUE COLLATE NOCASE NOT NULL, password_hash TEXT NOT NULL, rol TEXT NOT NULL DEFAULT 'estudiante', activo INTEGER NOT NULL DEFAULT 1,
cambiar_password INTEGER NOT NULL DEFAULT 1, creado_en TEXT DEFAULT CURRENT_TIMESTAMP);
INSERT INTO cursos (id, nombre) VALUES (1, 'Sexto de secundaria');");
$users = new Usuario($db);
ini_set('session.save_path', $temporary . '/sessions');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
Auth::start();
$passed = 0;
$checks = [];
function check(bool $condition, string $label): void {
    global $passed, $checks;
    if (!$condition) { throw new RuntimeException('FALLÓ: ' . $label); }
    $passed++; $checks[] = $label;
}
function rejected(callable $action, int $status, string $label): void {
    try { $action(); } catch (HttpError $e) { check($e->status === $status, $label); return; }
    throw new RuntimeException('No rechazó: ' . $label);
}
function cleanup(string $directory): void {
    foreach (new FilesystemIterator($directory) as $item) {
        if ($item->isDir() && !$item->isLink()) { cleanup($item->getPathname()); }
        else { unlink($item->getPathname()); }
    }
    rmdir($directory);
}
try {
    check(strlen($_SESSION['csrf']) === 64, 'Token CSRF aleatorio');
    rejected(static fn() => Auth::csrf(), 403, 'Rechaza solicitudes sin CSRF');
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf']; Auth::csrf();
    rejected(static fn() => Auth::user($users), 401, 'Rechaza usuario sin sesión');
    rejected(static fn() => Http::username(['usuario' => '<script>']), 422, 'Valida nombre de usuario');
    rejected(static fn() => Http::password(['password' => 'corta']), 422, 'Rechaza contraseña corta');
    rejected(static fn() => Http::password(['password' => str_repeat('á', 6)]), 422, 'Mínimo medido en caracteres, también con acentos');
    rejected(static fn() => Http::password(['password' => str_repeat('á', 37)]), 422, 'Límite de contraseña medido en bytes');
    check(!InstallController::available($db), 'Instalador deshabilitado por defecto');
    $token = bin2hex(random_bytes(24));
    file_put_contents($temporary . '/storage/install-token.json', json_encode(['hash' => hash('sha256', $token), 'expires' => time() + 3600]));
    check(InstallController::available($db), 'Instalador habilitado mediante clave local');
    $adminData = ['nombres' => 'Prueba', 'apellidos' => 'Administración', 'usuario' => 'admin-prueba', 'password' => 'Frase privada de prueba 123!', 'token' => 'incorrecto'];
    rejected(static fn() => InstallController::create($db, $adminData), 403, 'Rechaza clave de instalación incorrecta');
    $adminData['token'] = $token;
    InstallController::create($db, $adminData);
    $admin = $users->byUsername('admin-prueba');
    check($admin['rol'] === 'administrador' && !$admin['cambiar_password'], 'Crea administrador con rol correcto');
    check(password_verify($adminData['password'], $admin['password_hash']), 'Administrador almacenado con password_hash');
    check(!InstallController::available($db), 'Bloquea instalador tras crear administrador');
    check(is_file($temporary . '/storage/installed.lock') && !is_file($temporary . '/storage/install-token.json'), 'Consume clave y deja bloqueo permanente');
    rejected(static fn() => InstallController::create($db, $adminData), 403, 'No permite segundo administrador');
    rejected(static fn() => AuthController::login($db, $users, ['usuario' => 'admin-prueba', 'password' => 'incorrecta']), 401, 'Rechaza contraseña incorrecta');
    $oldSession = session_id();
    $login = AuthController::login($db, $users, $adminData);
    check(session_id() !== $oldSession, 'Regenera ID al iniciar sesión');
    check(!isset($login['usuario']['password_hash']), 'No devuelve hashes al navegador');
    check(Auth::admin($users)['id'] === $admin['id'], 'Administrador autorizado en servidor');
    $studentData = ['nombres' => 'Estudiante', 'apellidos' => 'Prueba', 'usuario' => 'estudiante.prueba', 'curso_id' => 1,
        'password' => 'Temporal de prueba 123!', 'rol' => 'administrador', 'activo' => false, 'cambiar_password' => false];
    $created = StudentController::create($db, $studentData);
    $id = $created['id'];
    $student = $users->find($id);
    check($student['rol'] === 'estudiante' && (bool) $student['activo'] && (bool) $student['cambiar_password'], 'No permite inyectar rol ni omitir cambio temporal');
    check(count(StudentController::list($db)['estudiantes']) === 1, 'Lista solo estudiantes');
    rejected(static fn() => StudentController::create($db, array_replace($studentData, ['usuario' => 'sin-curso', 'curso_id' => 99])), 422, 'Rechaza curso inexistente');
    AuthController::login($db, $users, $studentData);
    rejected(static fn() => Auth::user($users), 403, 'Impide acceso antes de cambiar contraseña temporal');
    check(Auth::user($users, true)['id'] === $id, 'Permite pantalla de cambio obligatorio');
    rejected(static fn() => AuthController::password($db, $users, ['actual' => 'mal', 'nueva' => 'Nueva frase de prueba 456!']), 422, 'Verifica contraseña actual');
    rejected(static fn() => AuthController::password($db, $users, ['actual' => $studentData['password'], 'nueva' => $studentData['password']]), 422, 'Rechaza reutilizar contraseña temporal');
    AuthController::password($db, $users, ['actual' => $studentData['password'], 'nueva' => 'Nueva frase de prueba 456!']);
    check(!Auth::user($users)['cambiar_password'], 'Quita marca temporal al cambiar contraseña');
    rejected(static fn() => Auth::admin($users), 403, 'Estudiante no puede administrar');
    $studentSession = $_SESSION;
    AuthController::login($db, $users, $adminData);
    StudentController::update($db, $id, 'datos', array_replace($studentData, ['nombres' => 'Nombre actualizado']));
    check($users->find($id)['nombres'] === 'Nombre actualizado', 'Edita datos del estudiante');
    StudentController::update($db, $id, 'estado', ['activo' => false]);
    $_SESSION = $studentSession;
    rejected(static fn() => Auth::user($users), 401, 'Desactivar revoca acceso a sesión existente');
    rejected(static fn() => AuthController::login($db, $users, ['usuario' => 'estudiante.prueba', 'password' => 'Nueva frase de prueba 456!']), 401, 'Cuenta inactiva no inicia sesión');
    StudentController::update($db, $id, 'estado', ['activo' => true]);
    AuthController::login($db, $users, ['usuario' => 'estudiante.prueba', 'password' => 'Nueva frase de prueba 456!']);
    $studentSession = $_SESSION;
    StudentController::update($db, $id, 'password', ['password' => 'Otra temporal de prueba 789!']);
    $_SESSION = $studentSession;
    rejected(static fn() => Auth::user($users, true), 401, 'Restablecer contraseña revoca sesiones previas');
    AuthController::login($db, $users, ['usuario' => 'estudiante.prueba', 'password' => 'Otra temporal de prueba 789!']);
    check((bool) Auth::user($users, true)['cambiar_password'], 'Restablecimiento exige nuevo cambio');
    rejected(static fn() => StudentController::update($db, (int) $admin['id'], 'estado', ['activo' => false]), 404, 'Endpoint de estudiantes no modifica administradores');
    rejected(static fn() => StudentController::update($db, $id, 'estado', ['activo' => 'false']), 422, 'Valida estado booleano');
    check(!$db->inTransaction(), 'Revierte transacciones al rechazar cambios');
    $_SESSION['last_seen'] = time() - 1900;
    rejected(static fn() => Auth::user($users, true), 401, 'Expira la sesión por inactividad');
    RateLimit::check('test-limit', 1);
    rejected(static fn() => RateLimit::check('test-limit', 1), 429, 'Limita intentos repetidos');
    Auth::logout();
    check($_SESSION === [], 'Cierre de sesión vacía la identidad');
    echo implode(PHP_EOL, array_map(static fn($label) => 'OK ' . $label, $checks)) . PHP_EOL;
    echo "TOTAL: {$passed} comprobaciones correctas con SQLite aislado. MySQL se verifica en tests/mysql.php.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    cleanup($temporary);
}
