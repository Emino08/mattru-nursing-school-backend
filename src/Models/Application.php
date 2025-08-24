<?php
namespace App\Models;

use PDO;

class Application
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create($userId, $programType, $formData): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO applications (user_id, applicant_id, program_type, application_status, form_data, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $userId, // Using user_id as applicant_id for backward compatibility
            $programType,
            'draft',
            json_encode($formData)
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function submitNew($userId, $formData): int
    {
        // Create new application
        $stmt = $this->db->prepare(
            'INSERT INTO applications (user_id, applicant_id, application_status, form_data, submission_date, created_at) 
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $userId,
            'submitted',
            json_encode($formData)
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function submit($id): void
    {
        $stmt = $this->db->prepare('UPDATE applications SET application_status = ?, submission_date = NOW() WHERE id = ?');
        $stmt->execute(['submitted', $id]);
    }

    public function findByUser($userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM applications WHERE user_id = ? OR applicant_id = ? ORDER BY created_at DESC');
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT a.*, u.email FROM applications a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status): void
    {
        $stmt = $this->db->prepare('UPDATE applications SET application_status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public function count(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM applications');
        return (int)$stmt->fetchColumn();
    }

    public function countByStatus($status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM applications WHERE application_status = ?');
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    public function getLatestSubmittedByUser($userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM applications 
             WHERE (user_id = ? OR applicant_id = ?) AND application_status = ? 
             ORDER BY submission_date DESC, created_at DESC 
             LIMIT 1'
        );
        $stmt->execute([$userId, $userId, 'submitted']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getApplicationWithResponses($applicationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, 
            ar.question_id, ar.answer, ar.file_path,
            q.question_text, q.question_type, q.category,
            c.display_order AS category_order
     FROM applications a
     LEFT JOIN application_responses ar ON a.id = ar.application_id
     LEFT JOIN questions q ON ar.question_id = q.id
     LEFT JOIN categories c ON q.category_id = c.id
     WHERE a.id = ?
     ORDER BY c.display_order, q.sort_order'
        );

        $stmt->execute([$applicationId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [];
        }

        // Process the results
        $application = [
            'id' => (int)$results[0]['id'],
            'user_id' => (int)$results[0]['user_id'],
            'status' => $results[0]['application_status'],
            'submitted_at' => $results[0]['submission_date'] ?? $results[0]['created_at'],
            'application_number' => $results[0]['application_number'] ?? null,
            'categories' => []
        ];

        foreach ($results as $row) {
            if ($row['question_id']) {
                $category = $row['category'] ?? 'Other';
                if (!isset($application['categories'][$category])) {
                    $application['categories'][$category] = [];
                }
                $application['categories'][$category][] = [
                    'question_id' => (int)$row['question_id'],
                    'question_text' => $row['question_text'],
                    'answer' => $row['answer'],
                    'file_path' => $row['file_path'],
                    'question_type' => $row['question_type']
                ];
            }
        }

        return $application;
    }

    public function getById($id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM applications WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function deleteApplication($id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM applications WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function updateFormData($id, $formData): bool
    {
        $stmt = $this->db->prepare('UPDATE applications SET form_data = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([json_encode($formData), $id]);
    }

    public function generateApplicationNumber($applicationId): string
    {
        $currentYear = date('Y');

        // Get the count of applications for this year
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM applications 
             WHERE YEAR(created_at) = ? AND id <= ?'
        );
        $stmt->execute([$currentYear, $applicationId]);
        $count = (int)$stmt->fetchColumn();

        // Generate application number in format APP-YYYY-XXX
        $applicationNumber = sprintf('APP-%s-%03d', $currentYear, $count);

        // Store the application number
        $stmt = $this->db->prepare(
            'UPDATE applications SET application_number = ? WHERE id = ?'
        );
        $stmt->execute([$applicationNumber, $applicationId]);

        return $applicationNumber;
    }

    public function getApplicationNumber($applicationId): ?string
    {
        $stmt = $this->db->prepare('SELECT application_number FROM applications WHERE id = ?');
        $stmt->execute([$applicationId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }
}