<?php
namespace App\Models;

use PDO;

class Notification
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($userId, $type, $message): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, message, sent_at) 
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $message, date('Y-m-d H:i:s')]);
    }

    public function findByUser($userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}