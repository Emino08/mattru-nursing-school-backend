<?php
//namespace App\Models;
//
//use PDO;
//
//class User
//{
//    private $db;
//
//    public function __construct(PDO $db)
//    {
//        $this->db = $db;
//    }
//
//    public function create($email, $password, $role, $permissions = []): int
//    {
//        $stmt = $this->db->prepare(
//            'INSERT INTO users (email, password, role, status, verification_token)
//             VALUES (?, ?, ?, ?, ?)'
//        );
//        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
//        $token = bin2hex(random_bytes(32));
//        $stmt->execute([$email, $hashedPassword, $role, 'pending', $token]);
//        $userId = (int)$this->db->lastInsertId();
//
//        $this->setPermissions($userId, $permissions);
//        return $userId;
//    }
//
//    public function findByEmail($email): ?array
//    {
//        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
//        $stmt->execute([$email]);
//        return $stmt->fetch() ?: null;
//    }
//
//    public function verify($token): bool
//    {
//        $stmt = $this->db->prepare(
//            'UPDATE users SET status = ?, verification_token = NULL WHERE verification_token = ?'
//        );
//        return $stmt->execute(['active', $token]);
//    }
//
//    public function setPermissions($userId, array $permissions): void
//    {
//        $stmt = $this->db->prepare('DELETE FROM permissions WHERE user_id = ?');
//        $stmt->execute([$userId]);
//        foreach ($permissions as $feature) {
//            $stmt = $this->db->prepare(
//                'INSERT INTO permissions (user_id, feature_name, can_create, can_read, can_update, can_delete)
//                 VALUES (?, ?, ?, ?, ?, ?)'
//            );
//            $stmt->execute([$userId, $feature, 1, 1, 1, 1]);
//        }
//    }
//
//    public function getPermissions($userId): array
//    {
//        $stmt = $this->db->prepare('SELECT feature_name FROM permissions WHERE user_id = ?');
//        $stmt->execute([$userId]);
//        return array_column($stmt->fetchAll(), 'feature_name');
//    }
//}


namespace App\Models;

use PDO;

class User
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($email, $password, $role, $permissions = []): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password, role, status, verification_token) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(32));
        $stmt->execute([$email, $hashedPassword, $role, 'pending', $token]);
        $userId = (int)$this->db->lastInsertId();

        $this->setPermissions($userId, $permissions);
        return $userId;
    }

    public function findByEmail($email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function verify($token): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET status = ?, verification_token = NULL WHERE verification_token = ?'
        );
        return $stmt->execute(['active', $token]);
    }

    public function setPermissions($userId, array $permissions): void
    {
        $stmt = $this->db->prepare('DELETE FROM permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($permissions as $feature) {
            $stmt = $this->db->prepare(
                'INSERT INTO permissions (user_id, feature_name, can_create, can_read, can_update, can_delete) 
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $feature, 1, 1, 1, 1]);
        }
    }

    public function getPermissions($userId): array
    {
        $stmt = $this->db->prepare('SELECT feature_name FROM permissions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'feature_name');
    }

    public function setPasswordResetToken($userId, $token): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET password_reset_token = ? WHERE id = ?'
        );
        $stmt->execute([$token, $userId]);
    }

    public function findByResetToken($token): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE password_reset_token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function updatePassword($userId, $newPassword): void
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hashedPassword, $userId]);
    }

    public function clearPasswordResetToken($userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_reset_token = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    }
}