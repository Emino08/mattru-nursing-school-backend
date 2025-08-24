<?php
namespace App\Models;

use PDO;
use Exception;

class User
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get the database connection
     */
    public function getDb(): PDO
    {
        return $this->db;
    }

    public function create($email, $password, $role, $permissions = []): int
    {
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $verificationToken = bin2hex(random_bytes(16));

            // Create user with pending status (requires email verification)
            $stmt = $this->db->prepare(
                'INSERT INTO users (email, password, role, status, verification_token, created_at, updated_at) 
                 VALUES (?, ?, ?, ?,?, NOW(), NOW())'
            );

            $success = $stmt->execute([$email, $hashedPassword, $role, 'pending', $verificationToken]);

            if (!$success) {
                throw new Exception('Failed to create user account');
            }

            $userId = (int)$this->db->lastInsertId();

            // Add permissions if provided
            if (!empty($permissions)) {
                $this->addPermissions($userId, $permissions);
            }

            return $userId;

        } catch (Exception $e) {
            error_log('User creation error: ' . $e->getMessage());
            throw new Exception('Failed to create user account: ' . $e->getMessage());
        }
    }

    public function findByEmail($email): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log('Find by email error: ' . $e->getMessage());
            return null;
        }
    }

    public function verify($token): bool
    {
        try {
            $user = $this->findByVerificationToken($token);
            if (!$user) {
                return false;
            }

            // Update user status to active and clear verification token
            $stmt = $this->db->prepare(
                'UPDATE users SET status = ?, verification_token = NULL, updated_at = NOW() WHERE id = ?'
            );
            return $stmt->execute(['active', $user['id']]);

        } catch (Exception $e) {
            error_log('Email verification error: ' . $e->getMessage());
            return false;
        }
    }

    public function findById($id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log('Find by ID error: ' . $e->getMessage());
            return null;
        }
    }

    public function verifyPassword($email, $password): ?array
    {
        try {
            $user = $this->findByEmail($email);
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
            return null;
        } catch (Exception $e) {
            error_log('Password verification error: ' . $e->getMessage());
            return null;
        }
    }

    public function updatePassword($userId, $newPassword): bool
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            return $stmt->execute([$hashedPassword, $userId]);
        } catch (Exception $e) {
            error_log('Password update error: ' . $e->getMessage());
            return false;
        }
    }

    public function updateStatus($userId, $status): bool
    {
        try {
            $validStatuses = ['active', 'inactive', 'pending'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }

            $stmt = $this->db->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
            return $stmt->execute([$status, $userId]);
        } catch (Exception $e) {
            error_log('Status update error: ' . $e->getMessage());
            return false;
        }
    }

    public function setVerificationToken($userId, $token): bool
    {
        try {
            $stmt = $this->db->prepare('UPDATE users SET verification_token = ?, updated_at = NOW() WHERE id = ?');
            return $stmt->execute([$token, $userId]);
        } catch (Exception $e) {
            error_log('Verification token update error: ' . $e->getMessage());
            return false;
        }
    }

    public function setPasswordResetToken($userId, $token): bool
    {
        try {
            $stmt = $this->db->prepare('UPDATE users SET password_reset_token = ?, updated_at = NOW() WHERE id = ?');
            return $stmt->execute([$token, $userId]);
        } catch (Exception $e) {
            error_log('Password reset token update error: ' . $e->getMessage());
            return false;
        }
    }

    public function findByVerificationToken($token): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE verification_token = ? AND verification_token IS NOT NULL');
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log('Find by verification token error: ' . $e->getMessage());
            return null;
        }
    }

    public function findByPasswordResetToken($token): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE password_reset_token = ? AND password_reset_token IS NOT NULL');
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log('Find by password reset token error: ' . $e->getMessage());
            return null;
        }
    }

    public function getAllUsers(): array
    {
        try {
            $stmt = $this->db->query('SELECT id, email, role, status, created_at, updated_at FROM users ORDER BY created_at DESC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Get all users error: ' . $e->getMessage());
            return [];
        }
    }

    public function getUsersByRole($role): array
    {
        try {
            $stmt = $this->db->prepare('SELECT id, email, role, status, created_at, updated_at FROM users WHERE role = ? ORDER BY created_at DESC');
            $stmt->execute([$role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Get users by role error: ' . $e->getMessage());
            return [];
        }
    }

    public function deleteUser($userId): bool
    {
        try {
            // Begin transaction for safe deletion
            $this->db->beginTransaction();

            // Delete related records first (to avoid foreign key constraints)
            $this->db->prepare('DELETE FROM permissions WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare('DELETE FROM user_profiles WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare('DELETE FROM application_progress WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare('DELETE FROM audit_logs WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);

            // Delete user
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $success = $stmt->execute([$userId]);

            if ($success) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollback();
                return false;
            }

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Delete user error: ' . $e->getMessage());
            return false;
        }
    }

    private function addPermissions($userId, $permissions): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO permissions (user_id, feature_name, can_create, can_read, can_update, can_delete) 
                 VALUES (?, ?, 1, 1, 1, 1)'
            );

            foreach ($permissions as $permission) {
                $stmt->execute([$userId, $permission]);
            }
        } catch (Exception $e) {
            error_log('Add permissions error: ' . $e->getMessage());
            throw new Exception('Failed to add user permissions');
        }
    }

    public function getPermissions($userId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT feature_name FROM permissions WHERE user_id = ?');
            $stmt->execute([$userId]);
            return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'feature_name');
        } catch (Exception $e) {
            error_log('Get permissions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if email is already registered
     */
    public function emailExists($email): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log('Email exists check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user information
     */
    public function updateUser($userId, $data): bool
    {
        try {
            $allowedFields = ['email', 'role', 'status'];
            $setClause = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $setClause[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($setClause)) {
                return false;
            }

            $setClause[] = "updated_at = NOW()";
            $values[] = $userId;

            $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);

        } catch (Exception $e) {
            error_log('Update user error: ' . $e->getMessage());
            return false;
        }
    }
}