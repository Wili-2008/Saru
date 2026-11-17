<?php
declare(strict_types=1);
// Limpia exclusivamente la materia temporal identificada por un marcador aleatorio de pruebas.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/backend/config/Database.php';
$marker = $argv[1] ?? '';
if (!preg_match('/^SARU-E2E-[a-f0-9]{16}$/D', $marker)) { fwrite(STDERR, 'Marcador inválido.'); exit(1); }
$db = Database::connect();
$q = $db->prepare('SELECT id FROM materias WHERE nombre = ?'); $q->execute([$marker]); $ids = $q->fetchAll(PDO::FETCH_COLUMN);
if (count($ids) === 0) { echo "No hay datos de prueba pendientes.\n"; exit; }
if (count($ids) !== 1) { fwrite(STDERR, 'La materia de prueba no es única. No se borró nada.'); exit(1); }
$id = (int) $ids[0];
$q = $db->prepare('SELECT m.nombre_guardado FROM materiales m JOIN temas t ON t.id = m.tema_id WHERE t.materia_id = ?'); $q->execute([$id]); $files = $q->fetchAll(PDO::FETCH_COLUMN);
$db->beginTransaction();
try {
    $queries = [
        'DELETE r FROM respuestas_estudiante r JOIN intento_preguntas p ON p.id = r.intento_pregunta_id JOIN intentos i ON i.id = p.intento_id JOIN cuestionarios q ON q.id = i.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE o FROM intento_opciones o JOIN intento_preguntas p ON p.id = o.intento_pregunta_id JOIN intentos i ON i.id = p.intento_id JOIN cuestionarios q ON q.id = i.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE p FROM intento_preguntas p JOIN intentos i ON i.id = p.intento_id JOIN cuestionarios q ON q.id = i.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE i FROM intentos i JOIN cuestionarios q ON q.id = i.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE o FROM opciones o JOIN preguntas p ON p.id = o.pregunta_id JOIN cuestionarios q ON q.id = p.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE p FROM preguntas p JOIN cuestionarios q ON q.id = p.cuestionario_id JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE q FROM cuestionarios q JOIN temas t ON t.id = q.tema_id WHERE t.materia_id = ?',
        'DELETE m FROM materiales m JOIN temas t ON t.id = m.tema_id WHERE t.materia_id = ?',
        'DELETE r FROM recursos r JOIN temas t ON t.id = r.tema_id WHERE t.materia_id = ?',
        'DELETE FROM temas WHERE materia_id = ?',
        'DELETE FROM materias WHERE id = ?',
    ];
    foreach ($queries as $sql) { $q = $db->prepare($sql); $q->execute([$id]); }
    $db->commit();
} finally { if ($db->inTransaction()) $db->rollBack(); }
foreach ($files as $file) {
    if (preg_match('/^[a-f0-9]{48}\.pdf$/D', $file)) {
        $path = dirname(__DIR__) . '/storage/materiales/' . $file;
        if (is_file($path)) unlink($path);
    }
}
echo "Contenido temporal E2E eliminado. Datos iniciales y cuentas conservados.\n";
