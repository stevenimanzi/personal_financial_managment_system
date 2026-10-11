<?php

function db(): mysqli
{
    static $link = null;

    if ($link === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $link = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $link->set_charset('utf8mb4');
    }

    return $link;
}

function db_run(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);

    if ($params) {
        $types = '';
        foreach ($params as $value) {
            $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db_run($sql, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

function db_row(string $sql, array $params = []): ?array
{
    $stmt = db_run($sql, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $row = db_row($sql, $params);

    return $row ? reset($row) : null;
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}

function db_insert(string $sql, array $params = []): int
{
    $stmt = db_run($sql, $params);
    $id = $stmt->insert_id;
    $stmt->close();

    return $id;
}

function db_transaction(callable $callback)
{
    $link = db();
    $link->begin_transaction();

    try {
        $result = $callback();
        $link->commit();

        return $result;
    } catch (Throwable $e) {
        $link->rollback();
        throw $e;
    }
}
