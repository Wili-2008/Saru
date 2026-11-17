<?php
declare(strict_types=1);

final class Data
{
    public static function query(PDO $db, string $sql, array $params = []): PDOStatement
    {
        $q = $db->prepare($sql); $q->execute($params); return $q;
    }
    public static function one(PDO $db, string $sql, array $params = []): array
    {
        $row = self::query($db, $sql, $params)->fetch();
        if (!$row) { throw new HttpError(404, 'Registro no encontrado o no disponible.'); }
        return $row;
    }
    public static function id(array $data, string $key): int
    {
        $id = $data[$key] ?? null;
        if (!is_int($id) || $id < 1 || $id > PHP_INT_MAX) { throw new HttpError(422, 'Revisa el campo ' . $key . '.'); }
        return $id;
    }
    public static function flag(array $data, string $key = 'activo'): int
    {
        if (!isset($data[$key]) || !is_bool($data[$key])) { throw new HttpError(422, 'Revisa el estado ' . $key . '.'); }
        return (int) $data[$key];
    }
    public static function optional(array $data, string $key, int $max = 10000): string
    {
        if (!isset($data[$key]) || $data[$key] === '') { return ''; }
        return Http::text($data, $key, $max);
    }
    public static function transaction(PDO $db, callable $action)
    {
        $db->beginTransaction();
        try { $result = $action(); $db->commit(); return $result; }
        finally { if ($db->inTransaction()) { $db->rollBack(); } }
    }
}
