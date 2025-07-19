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

    public function create($userId, $program, $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO applications (applicant_id, program_type, application_status, form_data, submission_date) 
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $program, 'draft', json_encode($data), date('Y-m-d H:i:s')]);
        return (int)$this->db->lastInsertId();
    }

    public function submit($id): void
    {
        $stmt = $this->db->prepare('UPDATE applications SET application_status = ? WHERE id = ?');
        $stmt->execute(['submitted', $id]);
    }

    public function findByUser($userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM applications WHERE applicant_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT a.*, u.email FROM applications a JOIN users u ON a.applicant_id = u.id');
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status): void
    {
        $stmt = $this->db->prepare('UPDATE applications SET application_status = ? WHERE id = ?');
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
}