<?php
declare(strict_types=1);
// Herramienta local solicitada para preparar las dos cuentas iniciales, sin credenciales fijas.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
foreach (['config/Database.php', 'services/Http.php', 'services/Data.php', 'services/RateLimit.php', 'models/Usuario.php', 'controllers/InstallController.php', 'controllers/StudentController.php'] as $file) { require dirname(__DIR__) . '/backend/' . $file; }
$storage = dirname(__DIR__) . '/storage/';
try {
    if (count($argv) !== 3) { throw new RuntimeException('Uso: php tools/crear-accesos.php USUARIO_ADMIN USUARIO_ESTUDIANTE'); }
    $adminName = Http::username(['usuario' => $argv[1]]); $studentName = Http::username(['usuario' => $argv[2]]);
    if (strcasecmp($adminName, $studentName) === 0) { throw new RuntimeException('Usa nombres de usuario distintos.'); }
    $db = Database::connect(); $users = new Usuario($db);
    $mutex = fopen($storage . 'access-setup.lock', 'c+');
    if (!$mutex || !flock($mutex, LOCK_EX)) { throw new RuntimeException('No se pudo bloquear la preparación de cuentas.'); }
    $path = $storage . 'accesos-iniciales.json';
    if (is_file($path)) {
        $access = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if ($access['administrador']['usuario'] !== $adminName || $access['estudiante']['usuario'] !== $studentName) { throw new RuntimeException('Ya hay credenciales iniciales preparadas para otros usuarios. No se reemplazaron.'); }
    } else {
        if ((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'")->fetchColumn() > 0 || is_file($storage . 'installed.lock')) { throw new RuntimeException('La instalación ya está completada. Crea estudiantes desde el administrador existente.'); }
        $access = ['administrador' => ['usuario' => $adminName, 'password' => bin2hex(random_bytes(12))], 'estudiante' => ['usuario' => $studentName, 'password' => bin2hex(random_bytes(12))]];
        if (file_put_contents($path, json_encode($access, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) { throw new RuntimeException('No se pudieron guardar las credenciales privadas.'); }
    }
    $admin = $users->byUsername($adminName);
    if (!$admin) {
        if ((int) $db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'administrador'")->fetchColumn() > 0 || is_file($storage . 'installed.lock')) { throw new RuntimeException('Ya existe un administrador distinto. No se crearon cuentas administrativas adicionales.'); }
        $token = bin2hex(random_bytes(24));
        file_put_contents($storage . 'install-token.json', json_encode(['hash' => hash('sha256', $token), 'expires' => time() + 600], JSON_THROW_ON_ERROR), LOCK_EX);
        InstallController::create($db, ['nombres' => 'Administrador', 'apellidos' => 'SARU', 'usuario' => $adminName, 'password' => $access['administrador']['password'], 'token' => $token]);
        $admin = $users->byUsername($adminName);
    }
    if ($admin['rol'] !== 'administrador' || !password_verify($access['administrador']['password'], $admin['password_hash'])) { throw new RuntimeException('Las credenciales guardadas no corresponden al administrador. No se restableció su contraseña.'); }
    $student = $users->byUsername($studentName);
    if (!$student) {
        $course = Data::one($db, 'SELECT id FROM cursos WHERE activo = 1 ORDER BY id LIMIT 1');
        StudentController::create($db, ['nombres' => 'Estudiante', 'apellidos' => 'Inicial', 'usuario' => $studentName, 'password' => $access['estudiante']['password'], 'curso_id' => (int) $course['id']]);
        $student = $users->byUsername($studentName);
    }
    if ($student['rol'] !== 'estudiante' || !password_verify($access['estudiante']['password'], $student['password_hash'])) { throw new RuntimeException('El estudiante ya tiene otra contraseña. No se modificó.'); }
    echo "Administrador y estudiante preparados y verificados.\nCredenciales privadas: storage/accesos-iniciales.json\nNo publiques ese archivo; está fuera del DocumentRoot y excluido de Git.\n";
} catch (Throwable $e) {
    fwrite(STDERR, ($e instanceof PDOException ? 'No se pudieron preparar las cuentas. Revisa MySQL y el registro local.' : $e->getMessage()) . PHP_EOL);
    exit(1);
} finally {
    if (isset($mutex) && is_resource($mutex)) { flock($mutex, LOCK_UN); fclose($mutex); }
}
