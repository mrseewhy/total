<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/crud.php';

final class Subscriber
{
    public function __construct(private PDO $db) {}

    public function create(array $data): void
    {
        createRecord($this->db, 'subscribers', ['email' => $data['email'], 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function paginate(int $page, int $perPage): array
    {
        $total = countRecords($this->db, 'subscribers');
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = readRecords($this->db, 'subscribers', ['id', 'email', 'created_at'], [], $perPage, ($page - 1) * $perPage);
        return ['data' => $rows, 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'pages' => $pages]];
    }

    public function exportRows(): Generator
    {
        for ($offset = 0; ; $offset += 1000) {
            $rows = readRecords($this->db, 'subscribers', ['id', 'email', 'created_at'], [], 1000, $offset);
            foreach ($rows as $row) yield $row;
            if (count($rows) < 1000) break;
        }
    }
}
