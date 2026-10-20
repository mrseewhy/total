<?php
declare(strict_types=1);
final class Activity
{
    public function __construct(private PDO $db) {}
    public function paginate(int $page, string $module, bool $admin): array
    {
        $where = $admin ? " WHERE 1=1" : " WHERE module<>'users'";
        $params = [];
        if ($module !== "") {
            $where .= " AND module=?";
            $params[] = $module;
        }
        $q = $this->db->prepare("SELECT COUNT(*) FROM activity_log" . $where);
        $q->execute($params);
        $total = (int) $q->fetchColumn();
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $offset = ($page - 1) * 20;
        $q = $this->db->prepare(
            "SELECT id,actor_name,module,action,target_id,summary,created_at FROM activity_log" .
                $where .
                " ORDER BY id DESC LIMIT 20 OFFSET " .
                $offset,
        );
        $q->execute($params);
        return [
            "data" => $q->fetchAll(PDO::FETCH_ASSOC),
            "pagination" => [
                "page" => $page,
                "pages" => $pages,
                "total" => $total,
                "perPage" => 20,
            ],
        ];
    }
}
