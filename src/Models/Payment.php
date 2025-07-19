<?php
namespace App\Models;

use PDO;

class Payment
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($applicationId, $amount, $pin): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (applicant_id, amount, payment_method, transaction_reference, bank_confirmation_pin, payment_status) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ref = 'TXN' . uniqid();
        $stmt->execute([$applicationId, $amount, 'bank', $ref, $pin, 'pending']);
        return (int)$this->db->lastInsertId();
    }

    public function confirm($applicationId, $pin, $bankUserId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE payments SET payment_status = ?, verified_by = ? 
             WHERE applicant_id = ? AND bank_confirmation_pin = ?'
        );
        return $stmt->execute(['confirmed', $bankUserId, $applicationId, $pin]);
    }

    public function sum(): float
    {
        $stmt = $this->db->query('SELECT SUM(amount) FROM payments WHERE payment_status = ?');
        $stmt->execute(['confirmed']);
        return (float)$stmt->fetchColumn();
    }

    public function findByStatus($status): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE payment_status = ?');
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }
}