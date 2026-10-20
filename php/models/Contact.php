<?php
declare(strict_types=1);
require_once __DIR__ . '/../database/crud.php';
final class Contact
{
    private const COLUMNS = ['id','name','email','phone','interest','message','is_read','created_at'];
    public function __construct(private PDO $db) {}
    private function normalize(array $row): array
    {
        $row['is_read'] = in_array($row['is_read'], [true, 1, '1', 't'], true);
        return $row;
    }
    public function create(array $data): void
    {
        // Never accept a read status from a public submission; the database default is false.
        createRecord($this->db, 'contacts', [
            'name'=>$data['name'], 'email'=>$data['email'], 'phone'=>$data['phone'],
            'interest'=>$data['interest'], 'message'=>$data['message'], 'created_at'=>gmdate('Y-m-d H:i:s'),
        ]);
    }
    public function paginate(int $page): array
    {
        $total = countRecords($this->db, 'contacts');
        $pages = max(1, (int) ceil($total / 20));
        $page = min($page, $pages);
        $rows = readRecords($this->db, 'contacts', self::COLUMNS, [], 20, ($page-1)*20);
        return ['data'=>array_map($this->normalize(...), $rows), 'pagination'=>['page'=>$page,'pages'=>$pages,'total'=>$total,'perPage'=>20]];
    }
    public function setRead(int $id, bool $read): ?array
    {
        if (!readRecord($this->db,'contacts',['id'],['id'=>$id])) return null;
        $value = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? ($read ? 'true' : 'false') : (int)$read;
        updateRecords($this->db,'contacts',['is_read'=>$value],['id'=>$id]);
        $row = readRecord($this->db,'contacts',self::COLUMNS,['id'=>$id]);
        return $row ? $this->normalize($row) : null;
    }
}
