<?php
namespace App\Models;

use PDO;

class ApplicationProgress
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

//    public function saveProgress($userId, $data): bool
//    {
//        $existing = $this->getProgress($userId);
//
//        if ($existing) {
//            // Update existing progress
//            $stmt = $this->db->prepare(
//                'UPDATE application_progress
//                 SET form_data = ?, files_metadata = ?, current_step = ?, completed_steps = ?, last_saved_at = NOW()
//                 WHERE user_id = ?'
//            );
//            return $stmt->execute([
//                json_encode($data['form_data']),
//                json_encode($data['files_metadata']),
//                $data['current_step'],
//                json_encode($data['completed_steps']),
//                $userId
//            ]);
//        } else {
//            // Create new progress
//            $stmt = $this->db->prepare(
//                'INSERT INTO application_progress (user_id, form_data, files_metadata, current_step, completed_steps, last_saved_at)
//                 VALUES (?, ?, ?, ?, ?, NOW())'
//            );
//            return $stmt->execute([
//                $userId,
//                json_encode($data['form_data']),
//                json_encode($data['files_metadata']),
//                $data['current_step'],
//                json_encode($data['completed_steps'])
//            ]);
//        }
//    }

    public function updateFilesMetadata($userId, $filesMetadata) {
        // First, check if the user has an existing progress record
        $stmt = $this->db->prepare("
        SELECT id FROM application_progress 
        WHERE user_id = :user_id
    ");
        $stmt->execute([':user_id' => $userId]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Update existing record
            $stmt = $this->db->prepare("
            UPDATE application_progress 
            SET files_metadata = :files_metadata,
                last_saved_at = NOW()
            WHERE user_id = :user_id
        ");

            return $stmt->execute([
                ':files_metadata' => json_encode($filesMetadata),
                ':user_id' => $userId
            ]);
        } else {
            // Create new progress record with just files metadata
            $stmt = $this->db->prepare("
            INSERT INTO application_progress 
            (user_id, form_data, files_metadata, current_step, completed_steps, last_saved_at, created_at) 
            VALUES 
            (:user_id, :form_data, :files_metadata, :current_step, :completed_steps, NOW(), NOW())
        ");

            return $stmt->execute([
                ':user_id' => $userId,
                ':form_data' => json_encode([]),
            ':files_metadata' => json_encode($filesMetadata),
            ':current_step' => 0,
            ':completed_steps' => json_encode([])
        ]);
    }
    }
    public function saveProgress($userId, $data): bool
    {
        // Validate user_id is not null/0 and user exists
        if (!$userId || $userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID provided');
        }

        // Check if user exists
        $userStmt = $this->db->prepare('SELECT id FROM users WHERE id = ?');
        $userStmt->execute([$userId]);
        if (!$userStmt->fetch()) {
            throw new InvalidArgumentException('User does not exist');
        }

        $existing = $this->getProgress($userId);

        if ($existing) {
            // Update existing progress
            $stmt = $this->db->prepare(
                'UPDATE application_progress
             SET form_data = ?, files_metadata = ?, current_step = ?, completed_steps = ?, last_saved_at = NOW()
             WHERE user_id = ?'
            );
            return $stmt->execute([
                json_encode($data['form_data']),
                json_encode($data['files_metadata']),
                $data['current_step'],
                json_encode($data['completed_steps']),
                $userId
            ]);
        } else {
            // Create new progress
            $stmt = $this->db->prepare(
                'INSERT INTO application_progress (user_id, form_data, files_metadata, current_step, completed_steps, last_saved_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
            );
            return $stmt->execute([
                $userId,
                json_encode($data['form_data']),
                json_encode($data['files_metadata']),
                $data['current_step'],
                json_encode($data['completed_steps'])
            ]);
        }
    }
    public function getProgress($userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM application_progress WHERE user_id = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function clearProgress($userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM application_progress WHERE user_id = ?');
        return $stmt->execute([$userId]);
    }

    public function updateStep($userId, $step): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE application_progress SET current_step = ?, last_saved_at = NOW() WHERE user_id = ?'
        );
        return $stmt->execute([$step, $userId]);
    }
}