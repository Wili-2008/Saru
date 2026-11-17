<?php
declare(strict_types=1);

final class MaterialController
{
    public static function list(PDO $db): array
    {
        return ['materiales' => Data::query($db, 'SELECT m.id, m.tema_id, m.titulo, m.descripcion, m.tipo, m.nombre_original, m.tamano_bytes, m.activo, t.titulo AS tema FROM materiales m JOIN temas t ON t.id = m.tema_id ORDER BY m.id DESC')->fetchAll()];
    }
    public static function directory(): string { return dirname(__DIR__, 2) . '/storage/materiales/'; }
    public static function maxBytes(): int
    {
        $file = dirname(__DIR__) . '/config/local.php';
        $config = is_file($file) ? require $file : [];
        return max(1048576, min(104857600, (int) ($config['pdf_max_bytes'] ?? 20971520)));
    }
    public static function validateUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { throw new HttpError(422, 'No se recibió el PDF completo. Revisa el tamaño máximo permitido en PHP.'); }
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 8 || $size > self::maxBytes()) { throw new HttpError(422, 'El PDF está vacío o supera el tamaño permitido.'); }
        $name = $file['name'] ?? '';
        if (!is_string($name) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') { throw new HttpError(422, 'Selecciona un archivo con extensión .pdf.'); }
        if ((new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) !== 'application/pdf') { throw new HttpError(422, 'El archivo no tiene un tipo PDF válido.'); }
        $handle = fopen($file['tmp_name'], 'rb');
        try { $signature = fread($handle, 5); } finally { fclose($handle); }
        if ($signature !== '%PDF-') { throw new HttpError(422, 'La firma del PDF no es válida.'); }
    }
    public static function save(PDO $db, array $data, ?int $id, ?array $file): array
    {
        $topic = Data::id($data, 'tema_id');
        Data::one($db, 'SELECT id FROM temas WHERE id = ?', [$topic]);
        $type = $data['tipo'] ?? '';
        if (!in_array($type, ['resumen', 'guia', 'libro', 'otro'], true)) { throw new HttpError(422, 'Tipo de material inválido.'); }
        $title = Http::text($data, 'titulo', 200); $description = Data::optional($data, 'descripcion'); $active = Data::flag($data);
        $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($id === null && !$hasFile) { throw new HttpError(422, 'Selecciona un archivo PDF.'); }
        $saved = null; $old = null;
        if ($hasFile) {
            if (!is_uploaded_file($file['tmp_name'] ?? '')) { throw new HttpError(422, 'Archivo de carga inválido.'); }
            self::validateUpload($file);
            $directory = self::directory();
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('No se pudo crear storage/materiales.'); }
            $saved = bin2hex(random_bytes(24)) . '.pdf';
            if (!move_uploaded_file($file['tmp_name'], $directory . $saved)) { throw new RuntimeException('No se pudo guardar el PDF.'); }
        }
        try {
            $result = Data::transaction($db, static function () use ($db, $id, $topic, $type, $title, $description, $active, $hasFile, $file, $saved, &$old) {
                if ($id !== null) { $old = Data::one($db, 'SELECT nombre_guardado FROM materiales WHERE id = ? FOR UPDATE', [$id]); }
                $values = [$topic, $title, $description, $type, $active];
                if ($id === null) {
                    Data::query($db, 'INSERT INTO materiales (tema_id, titulo, descripcion, tipo, activo, nombre_original, nombre_guardado, tamano_bytes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [...$values, self::original($file['name']), $saved, filesize(self::directory() . $saved)]);
                    $id = (int) $db->lastInsertId();
                } elseif ($hasFile) {
                    Data::query($db, 'UPDATE materiales SET tema_id = ?, titulo = ?, descripcion = ?, tipo = ?, activo = ?, nombre_original = ?, nombre_guardado = ?, tamano_bytes = ? WHERE id = ?', [...$values, self::original($file['name']), $saved, filesize(self::directory() . $saved), $id]);
                } else {
                    Data::query($db, 'UPDATE materiales SET tema_id = ?, titulo = ?, descripcion = ?, tipo = ?, activo = ? WHERE id = ?', [...$values, $id]);
                }
                return ['id' => $id];
            });
        } catch (Throwable $e) {
            if ($saved !== null && is_file(self::directory() . $saved)) { unlink(self::directory() . $saved); }
            throw $e;
        }
        // Los PDF reemplazados se conservan fuera del acceso web; no hay borrado de historial.
        return $result;
    }
    private static function original(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        if ($name === '' || strlen($name) > 255) { return 'documento.pdf'; }
        return $name;
    }
    public static function file(PDO $db, int $id, array $user): void
    {
        $material = Data::one($db, 'SELECT * FROM materiales WHERE id = ?', [$id]);
        ContentController::visibleTopic($db, (int) $material['tema_id'], $user);
        if ($user['rol'] !== 'administrador' && !(bool) $material['activo']) { throw new HttpError(404, 'Material no disponible.'); }
        if (!preg_match('/^[a-f0-9]{48}\.pdf$/D', $material['nombre_guardado'])) { throw new HttpError(404, 'Archivo no disponible.'); }
        $path = self::directory() . $material['nombre_guardado'];
        if (!is_file($path) || !is_readable($path)) { throw new HttpError(404, 'Archivo no disponible.'); }
        $file = fopen($path, 'rb');
        if (!$file) { throw new HttpError(404, 'Archivo no disponible.'); }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="material.pdf"; filename*=UTF-8\'\'' . rawurlencode($material['nombre_original']));
        header('Content-Length: ' . filesize($path));
        header('Content-Security-Policy: frame-ancestors \'self\'');
        header('X-Frame-Options: SAMEORIGIN');
        session_write_close();
        fpassthru($file); fclose($file); exit;
    }
}
