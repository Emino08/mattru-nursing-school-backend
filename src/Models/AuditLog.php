<?php
namespace App\Models;

use PDO;

class AuditLog
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($userId, $action, $details): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, details, created_at) 
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $action, json_encode($details), date('Y-m-d H:i:s')]);
    }
}
