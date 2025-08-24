<?php
//namespace App\Models;
//
//use PDO;
//
//class ApplicationResponse
//{
//    private $db;
//
//    public function __construct(PDO $db)
//    {
//        $this->db = $db;
//    }
//
//    public function create($applicationId, $questionId, $answer, $filePath = null): int
//    {
//        $stmt = $this->db->prepare(
//            'INSERT INTO application_responses (application_id, question_id, answer, file_path, created_at)
//             VALUES (?, ?, ?, ?, NOW())'
//        );
//        $stmt->execute([$applicationId, $questionId, $answer, $filePath]);
//        return (int)$this->db->lastInsertId();
//    }
//
//    public function getByApplicationId($applicationId): array
//    {
//        $stmt = $this->db->prepare(
//            'SELECT ar.*, q.question_text, q.question_type, q.category
//             FROM application_responses ar
//             JOIN questions q ON ar.question_id = q.id
//             WHERE ar.application_id = ?
//             ORDER BY q.category_order, q.sort_order'
//        );
//        $stmt->execute([$applicationId]);
//        return $stmt->fetchAll(PDO::FETCH_ASSOC);
//    }
//
//    public function updateResponse($applicationId, $questionId, $answer, $filePath = null): bool
//    {
//        $stmt = $this->db->prepare(
//            'UPDATE application_responses
//             SET answer = ?, file_path = ?
//             WHERE application_id = ? AND question_id = ?'
//        );
//        return $stmt->execute([$answer, $filePath, $applicationId, $questionId]);
//    }
//
//    public function deleteByApplicationId($applicationId): bool
//    {
//        $stmt = $this->db->prepare('DELETE FROM application_responses WHERE application_id = ?');
//        return $stmt->execute([$applicationId]);
//    }
//
//    public function getResponsesByCategory($applicationId): array
//    {
//        $stmt = $this->db->prepare(
//            'SELECT ar.*, q.question_text, q.question_type, c.name as category_name
//             FROM application_responses ar
//             JOIN questions q ON ar.question_id = q.id
//             JOIN categories c ON q.category_id = c.id
//             WHERE ar.application_id = ?
//             ORDER BY c.display_order, q.sort_order'
//        );
//        $stmt->execute([$applicationId]);
//
//        $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
//        $categorized = [];
//
//        foreach ($responses as $response) {
//            $category = $response['category_name'];
//            if (!isset($categorized[$category])) {
//                $categorized[$category] = [];
//            }
//            $categorized[$category][] = $response;
//        }
//
//        return $categorized;
//    }
//}

namespace App\Models;

use PDO;

class ApplicationResponse
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($applicationId, $questionId, $value, $filePath = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO application_responses (application_id, question_id, response_value, answer, file_path, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$applicationId, $questionId, $value, $value, $filePath]);
        return (int)$this->db->lastInsertId();
    }

    public function getByApplicationId($applicationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ar.*, q.question_text, q.question_type, q.category 
             FROM application_responses ar
             LEFT JOIN questions q ON ar.question_id = q.id 
             WHERE ar.application_id = ?
             ORDER BY q.category_order, q.sort_order'
        );
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByApplicationId($applicationId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM application_responses WHERE application_id = ?');
        return $stmt->execute([$applicationId]);
    }

    public function updateResponse($applicationId, $questionId, $value, $filePath = null): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE application_responses 
             SET response_value = ?, answer = ?, file_path = ?
             WHERE application_id = ? AND question_id = ?'
        );
        return $stmt->execute([$value, $value, $filePath, $applicationId, $questionId]);
    }

    public function findByApplicationAndQuestion($applicationId, $questionId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM application_responses 
             WHERE application_id = ? AND question_id = ?'
        );
        $stmt->execute([$applicationId, $questionId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deleteResponse($applicationId, $questionId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM application_responses 
             WHERE application_id = ? AND question_id = ?'
        );
        return $stmt->execute([$applicationId, $questionId]);
    }
}