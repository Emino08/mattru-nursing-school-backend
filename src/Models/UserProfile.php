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

    public function create($userId, $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_profiles (user_id, first_name, last_name, phone, address, date_of_birth, nationality, emergency_contact, profile_picture) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['first_name'],
            $data['last_name'],
            $data['phone'],
            json_encode($data['address']),
            $data['date_of_birth'],
            $data['nationality'],
            json_encode($data['emergency_contact']),
            $data['profile_picture'] ?? null
        ]);
    }

    public function update($userId, $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE user_profiles SET first_name = ?, last_name = ?, phone = ?, address = ?, date_of_birth = ?, nationality = ?, emergency_contact = ?, profile_picture = ? 
             WHERE user_id = ?'
        );
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['phone'],
            json_encode($data['address']),
            $data['date_of_birth'],
            $data['nationality'],
            json_encode($data['emergency_contact']),
            $data['profile_picture'] ?? null,
            $userId
        ]);
    }

    public function findByUserId($userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
}