<?php
namespace App\Models;

use PDO;

class UserProfile
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByUserId($userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function findWithUserByUserId($userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT up.*, u.email, u.role 
             FROM user_profiles up
             RIGHT JOIN users u ON up.user_id = u.id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create($userId, $data): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_profiles (user_id, first_name, last_name, phone, address, date_of_birth, nationality, emergency_contact, sponsor, profile_picture)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([
            $userId,
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            json_encode($data['address'] ?? []),
            $data['date_of_birth'] ?? null,
            $data['nationality'] ?? null,
            json_encode($data['emergency_contact'] ?? []),
            json_encode($data['sponsor'] ?? null),
            $data['profile_picture'] ?? null
        ]);
    }

    public function update($userId, $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE user_profiles 
             SET first_name = ?, last_name = ?, phone = ?, address = ?, 
                 date_of_birth = ?, nationality = ?, emergency_contact = ?, 
                 sponsor = ?, profile_picture = ?
             WHERE user_id = ?'
        );
        return $stmt->execute([
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone'] ?? null,
            json_encode($data['address'] ?? []),
            $data['date_of_birth'] ?? null,
            $data['nationality'] ?? null,
            json_encode($data['emergency_contact'] ?? []),
            json_encode($data['sponsor'] ?? null),
            $data['profile_picture'] ?? null,
            $userId
        ]);
    }

    public function updateProfilePicture($userId, $filePath): bool
    {
        $stmt = $this->db->prepare('UPDATE user_profiles SET profile_picture = ? WHERE user_id = ?');
        return $stmt->execute([$filePath, $userId]);
    }

    public function delete($userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM user_profiles WHERE user_id = ?');
        return $stmt->execute([$userId]);
    }
}