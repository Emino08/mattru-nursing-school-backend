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

    public function create($userId, $type, $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, message, sent_at) 
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $type, $message]);
        return (int)$this->db->lastInsertId();
    }

    public function getByUserId($userId, $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications 
             WHERE user_id = ? 
             ORDER BY sent_at DESC 
             LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadByUserId($userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications 
             WHERE user_id = ? AND is_read = 0 
             ORDER BY sent_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markAsRead($id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = ?'
        );
        return $stmt->execute([$id]);
    }

    public function markAllAsReadForUser($userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?'
        );
        return $stmt->execute([$userId]);
    }

    public function delete($id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM notifications WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function deleteOldNotifications($daysToKeep = 30): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM notifications WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }

    public function sendBulkNotification($userIds, $type, $message): int
    {
        $count = 0;
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, message, sent_at) 
             VALUES (?, ?, ?, NOW())'
        );

        foreach ($userIds as $userId) {
            if ($stmt->execute([$userId, $type, $message])) {
                $count++;
            }
        }

        return $count;
    }
}