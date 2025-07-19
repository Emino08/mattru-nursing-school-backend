<?php
namespace App\Models;

use PDO;

class Permission
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($userId, $feature): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO permissions (user_id, feature_name, can_create, can_read, can_update, can_delete) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $feature, 1, 1, 1, 1]);
    }

    public function getByUser($userId): array
    {
        $stmt = $this->db->prepare('SELECT feature_name FROM permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'feature_name');
    }
}