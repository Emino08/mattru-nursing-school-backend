<?php
namespace App\Models;

use PDO;

class Payment
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($applicationId, $amount, $pin): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (applicant_id, amount, payment_method, transaction_reference, bank_confirmation_pin, payment_status, payment_date) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $ref = 'TXN' . rand(1000000000, 9999999999);
        $stmt->execute([$applicationId, $amount, 'bank', $ref, $pin, 'pending']);
        return (int)$this->db->lastInsertId();
    }

    public function createForUser($userId, $amount, $method, $pin): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (bank_user_id, amount, payment_method, transaction_reference, bank_confirmation_pin, payment_status, payment_date) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $ref = 'TXN' . rand(1000000000, 9999999999);
        $stmt->execute([$userId, $amount, $method, $ref, $pin, 'pending']);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Get payments by user ID
     */

    public function getPaymentsByUserId($userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments p WHERE p.bank_user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Create payment with application fee PIN
     */
    public function createWithApplicationPin($userId, $data): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payments (
                bank_user_id, 
                amount, 
                payment_method, 
                transaction_reference, 
                bank_confirmation_pin, 
                depositor_name, 
                depositor_phone, 
                application_fee_pin, 
                payment_status, 
                payment_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->execute([
            $userId,
            $data['amount'],
            $data['payment_method'],
            $data['transaction_reference'],
            $data['bank_confirmation_pin'],
            $data['depositor_name'],
            $data['depositor_phone'],
            $data['application_fee_pin'],
            'confirmed', // Auto-confirm bank payments
        ]);

        $paymentId = (int)$this->db->lastInsertId();

        // Return the created payment
        return $this->getById($paymentId);
    }

    /**
     * Verify application fee PIN
     */
//    public function verifyApplicationPin($pin): ?array
//    {
//        $stmt = $this->db->prepare(
//            'SELECT p.*, u.email, u.role
//             FROM payments p
//             LEFT JOIN users u ON p.bank_user_id = u.id
//             WHERE p.application_fee_pin = ? AND p.payment_status = ?'
//        );
//        $stmt->execute([$pin, 'confirmed']);
//        $result = $stmt->fetch(PDO::FETCH_ASSOC);
//
//        if (!$result) {
//            return null;
//        }
//
//        // Check if PIN is still valid (30 days from payment date)
//        $paymentDate = new \DateTime($result['payment_date']);
//        $expiryDate = $paymentDate->add(new \DateInterval('P30D'));
//        $now = new \DateTime();
//
//        if ($now > $expiryDate) {
//            // PIN has expired
//            return ['expired' => true, 'payment' => $result];
//        }
//
//        return ['expired' => false, 'payment' => $result];
//    }

    /**
     * Verify application fee PIN with additional checks
     */
    public function verifyApplicationPin($pin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, u.email, u.role 
         FROM payments p 
         LEFT JOIN users u ON p.bank_user_id = u.id 
         WHERE p.application_fee_pin = ? AND p.payment_status = ?'
        );
        $stmt->execute([$pin, 'confirmed']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // Check if PIN is still valid (30 days from payment date)
        $paymentDate = new \DateTime($result['payment_date']);
        $expiryDate = $paymentDate->add(new \DateInterval('P30D'));
        $now = new \DateTime();

        if ($now > $expiryDate) {
            return ['expired' => true, 'payment' => $result];
        }

        return ['expired' => false, 'payment' => $result];
    }

    /**
     * Assign PIN to first applicant who uses it
     */
    /**
     * Assign PIN to first applicant who uses it (using user_id, not application_id)
     */
    public function assignPinToApplicant($pin, $userId): bool
    {
        // First check if PIN is already assigned to a user
        $checkStmt = $this->db->prepare(
            'SELECT pin_used_by_user_id FROM payments 
         WHERE application_fee_pin = ? 
         AND pin_used_by_user_id IS NOT NULL'
        );
        $checkStmt->execute([$pin]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // PIN already assigned to a user
            return false;
        }

        // Assign the PIN to this user (not application)
        $stmt = $this->db->prepare(
            'UPDATE payments 
         SET pin_used_by_user_id = ?, 
             pin_used = 1, 
             pin_used_at = NOW() 
         WHERE application_fee_pin = ? 
         AND pin_used_by_user_id IS NULL'
        );

        return $stmt->execute([$userId, $pin]);
    }

    /**
     * Check if a PIN is already used by a specific applicant
     */
    public function isPinUsedByApplicant($pin, $applicantId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM payments 
         WHERE application_fee_pin = ? 
         AND applicant_id = ?'
        );
        $stmt->execute([$pin, $applicantId]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Mark PIN as used
     */
    public function markPinAsUsed($pin): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE payments SET pin_used = 1, pin_used_at = NOW() WHERE application_fee_pin = ?'
        );
        return $stmt->execute([$pin]);
    }

    /**
     * Get all payments for bank portal
     */
    public function getAllPayments(): array
    {
        $stmt = $this->db->query(
            'SELECT p.*, u.email as bank_user_email 
             FROM payments p 
             LEFT JOIN users u ON p.bank_user_id = u.id 
             ORDER BY p.payment_date DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt = $this->db->prepare('SELECT SUM(amount) FROM payments WHERE payment_status = ?');
        $stmt->execute(['confirmed']);
        $result = $stmt->fetchColumn();
        return $result ? (float)$result : 0.0;
    }

    public function findByStatus($status): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE payment_status = ?');
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUserId($userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE bank_user_id = ? ORDER BY payment_date DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function getById($id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new \Exception("Payment with ID {$id} not found");
        }

        return $result;
    }

    public function updateStatus($id, $status): bool
    {
        $stmt = $this->db->prepare('UPDATE payments SET payment_status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /**
     * Get payment statistics for dashboard
     */
    public function getStatistics(): array
    {
        $stats = [];

        // Total confirmed payments
        $stmt = $this->db->prepare('SELECT COUNT(*), SUM(amount) FROM payments WHERE payment_status = ?');
        $stmt->execute(['confirmed']);
        $confirmed = $stmt->fetch(PDO::FETCH_NUM);
        $stats['confirmed'] = ['count' => (int)$confirmed[0], 'total' => (float)($confirmed[1] ?? 0)];

        // Pending payments
        $stmt = $this->db->prepare('SELECT COUNT(*), SUM(amount) FROM payments WHERE payment_status = ?');
        $stmt->execute(['pending']);
        $pending = $stmt->fetch(PDO::FETCH_NUM);
        $stats['pending'] = ['count' => (int)$pending[0], 'total' => (float)($pending[1] ?? 0)];

        // Today's payments
        $stmt = $this->db->prepare('SELECT COUNT(*), SUM(amount) FROM payments WHERE DATE(payment_date) = CURDATE()');
        $stmt->execute();
        $today = $stmt->fetch(PDO::FETCH_NUM);
        $stats['today'] = ['count' => (int)$today[0], 'total' => (float)($today[1] ?? 0)];

        // This week's payments
        $stmt = $this->db->prepare('SELECT COUNT(*), SUM(amount) FROM payments WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute();
        $week = $stmt->fetch(PDO::FETCH_NUM);
        $stats['week'] = ['count' => (int)$week[0], 'total' => (float)($week[1] ?? 0)];

        return $stats;
    }

}