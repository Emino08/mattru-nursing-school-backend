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

    public function create($userId, $action, $details = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, details, form_data, created_at) 
             VALUES (?, ?, ?, ?, NOW())'
        );

        // Handle details - can be array or string
        $detailsJson = null;
        $formData = null;

        if (is_array($details)) {
            $detailsJson = json_encode($details);
            $formData = json_encode($details); // Store in form_data column too for backward compatibility
        } elseif (is_string($details)) {
            $formData = $details;
        }

        $stmt->execute([$userId, $action, $detailsJson, $formData]);
        return (int)$this->db->lastInsertId();
    }

    public function getByUserId($userId, $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByAction($action, $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT al.*, u.email 
             FROM audit_logs al
             JOIN users u ON al.user_id = u.id
             WHERE al.action = ? 
             ORDER BY al.created_at DESC 
             LIMIT ?'
        );
        $stmt->execute([$action, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll($limit = 1000): array
    {
        $stmt = $this->db->prepare(
            'SELECT al.*, u.email 
             FROM audit_logs al
             JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC 
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDateRange($startDate, $endDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT al.*, u.email 
             FROM audit_logs al
             JOIN users u ON al.user_id = u.id
             WHERE al.created_at BETWEEN ? AND ?
             ORDER BY al.created_at DESC'
        );
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteOldLogs($daysToKeep = 90): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$daysToKeep]);
        return $stmt->rowCount();
    }
}