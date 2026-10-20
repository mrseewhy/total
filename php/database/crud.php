<?php
declare(strict_types=1);

// Identifiers must be developer-defined. Values always use bound parameters.
function crudIdentifier(PDO $db, string $name): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $name)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    $quote = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? '`' : '"';
    return $quote . $name . $quote;
}

function crudWhere(PDO $db, array $conditions, array &$values): string
{
    $clauses = [];
    foreach ($conditions as $column => $value) {
        $column = crudIdentifier($db, $column);
        $clauses[] = $column . ($value === null ? ' IS NULL' : ' = ?');
        if ($value !== null) $values[] = $value;
    }
    return $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
}

function createRecord(PDO $db, string $table, array $data): int
{
    if (!$data) throw new InvalidArgumentException('Create data is required.');
    $columns = array_map(fn ($column) => crudIdentifier($db, $column), array_keys($data));
    $sql = 'INSERT INTO ' . crudIdentifier($db, $table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($data), '?')) . ')';
    $stmt = $db->prepare($sql);
    $stmt->execute(array_values($data));
    return $stmt->rowCount();
}

function readRecords(PDO $db, string $table, array $columns, array $conditions = [], int $limit = 20, int $offset = 0, string $orderBy = 'id', string $direction = 'DESC'): array
{
    if (!$columns || $limit < 1 || $limit > 1000 || $offset < 0 || !in_array($direction, ['ASC', 'DESC'], true)) {
        throw new InvalidArgumentException('Invalid read options.');
    }
    $values = [];
    $sql = 'SELECT ' . implode(', ', array_map(fn ($column) => crudIdentifier($db, $column), $columns)) . ' FROM ' . crudIdentifier($db, $table);
    $sql .= crudWhere($db, $conditions, $values) . ' ORDER BY ' . crudIdentifier($db, $orderBy) . ' ' . $direction . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function readRecord(PDO $db, string $table, array $columns, array $conditions): ?array
{
    if (!$conditions) throw new InvalidArgumentException('Read conditions are required.');
    return readRecords($db, $table, $columns, $conditions, 1)[0] ?? null;
}

function countRecords(PDO $db, string $table, array $conditions = []): int
{
    $values = [];
    $sql = 'SELECT COUNT(*) FROM ' . crudIdentifier($db, $table) . crudWhere($db, $conditions, $values);
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    return (int) $stmt->fetchColumn();
}

function updateRecords(PDO $db, string $table, array $data, array $conditions): int
{
    if (!$data || !$conditions) throw new InvalidArgumentException('Update data and conditions are required.');
    $sets = array_map(fn ($column) => crudIdentifier($db, $column) . ' = ?', array_keys($data));
    $values = array_values($data);
    $sql = 'UPDATE ' . crudIdentifier($db, $table) . ' SET ' . implode(', ', $sets) . crudWhere($db, $conditions, $values);
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    return $stmt->rowCount();
}

function deleteRecords(PDO $db, string $table, array $conditions): int
{
    if (!$conditions) throw new InvalidArgumentException('Delete conditions are required.');
    $values = [];
    $sql = 'DELETE FROM ' . crudIdentifier($db, $table) . crudWhere($db, $conditions, $values);
    $stmt = $db->prepare($sql);
    $stmt->execute($values);
    return $stmt->rowCount();
}

function isUniqueViolation(PDO $db, PDOException $error): bool
{
    $code = (int) ($error->errorInfo[1] ?? 0);
    return match ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
        'mysql' => $code === 1062,
        'pgsql' => (string) $error->getCode() === '23505',
        'sqlite' => in_array($code, [19, 2067, 1555], true) && (str_contains($error->getMessage(), 'UNIQUE constraint failed') || str_contains($error->getMessage(), 'PRIMARY KEY')),
        default => false,
    };
}
