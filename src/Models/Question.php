<?php
namespace App\Models;

use PDO;

class Question
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

//    public function getAllActiveQuestions(): array
//    {
//        $stmt = $this->db->prepare(
//            'SELECT q.*, c.name as category_name, c.display_order as category_order,
//                    qtc.id as column_id, qtc.column_name as name, qtc.column_type as type,
//                    qtc.is_required, qtc.sort_order as column_sort_order
//             FROM questions q
//             LEFT JOIN categories c ON q.category_id = c.id
//             LEFT JOIN question_table_columns qtc ON q.id = qtc.question_id
//             WHERE q.is_active = 1 AND c.is_active = 1
//             ORDER BY c.display_order, q.sort_order, qtc.sort_order'
//        );
//        $stmt->execute();
//        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
//
//        // Process and group questions with their table columns
//        $questions = [];
//        $processedQuestions = [];
//
//        foreach ($results as $row) {
//            $questionId = $row['id'];
//
//            if (!isset($processedQuestions[$questionId])) {
//                $question = [
//                    'id' => $row['id'],
//                    'category' => $row['category_name'],
//                    'category_id' => $row['category_id'],
//                    'category_order' => $row['category_order'],
//                    'section' => $row['section'],
//                    'question_text' => $row['question_text'],
//                    'question_type' => $row['question_type'],
//                    'options' => $row['options'],
//                    'validation_rules' => $row['validation_rules'],
//                    'conditional_logic' => $row['conditional_logic'],
//                    'is_required' => (bool)$row['is_required'],
//                    'sort_order' => $row['sort_order'],
//                    'table_columns' => []
//                ];
//
//                $processedQuestions[$questionId] = $question;
//            }
//
//            // Add table column if exists
//            if ($row['column_id']) {
//                $processedQuestions[$questionId]['table_columns'][] = [
//                    'id' => $row['column_id'],
//                    'name' => $row['name'],
//                    'type' => $row['type'],
//                    'is_required' => (bool)$row['is_required'],
//                    'options' => [] // You might need to add options for select type columns
//                ];
//            }
//        }
//
//        return array_values($processedQuestions);
//    }

    public function getAllActiveQuestions(): array
    {
        $stmt = $this->db->prepare(
            'SELECT q.*, c.name as category_name, c.display_order as category_order,
                qtc.id as column_id, qtc.column_name as name, qtc.column_type as type,
                qtc.is_required, qtc.sort_order as column_sort_order, qtc.options as column_options
         FROM questions q
         LEFT JOIN categories c ON q.category_id = c.id
         LEFT JOIN question_table_columns qtc ON q.id = qtc.question_id
         WHERE q.is_active = 1 AND c.is_active = 1
         ORDER BY c.display_order, q.sort_order, qtc.sort_order'
        );
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process and group questions with their table columns
        $questions = [];
        $processedQuestions = [];

        foreach ($results as $row) {
            $questionId = $row['id'];

            if (!isset($processedQuestions[$questionId])) {
                $question = [
                    'id' => $row['id'],
                    'category' => $row['category_name'],
                    'category_id' => $row['category_id'],
                    'category_order' => $row['category_order'],
                    'section' => $row['section'],
                    'question_text' => $row['question_text'],
                    'question_type' => $row['question_type'],
                    'options' => $row['options'] ? json_decode($row['options'], true) : null,
                    'validation_rules' => $row['validation_rules'] ? json_decode($row['validation_rules'], true) : null,
                    'conditional_logic' => $row['conditional_logic'] ? json_decode($row['conditional_logic'], true) : null,
                    'is_required' => (bool)$row['is_required'],
                    'sort_order' => $row['sort_order'],
                    'table_columns' => []
                ];

                $processedQuestions[$questionId] = $question;
            }

            // Add table column if exists
            if ($row['column_id']) {
                $columnOptions = null;
                if (!empty($row['column_options'])) {
                    $decodedOptions = json_decode($row['column_options'], true);
                    $columnOptions = is_array($decodedOptions) ? $decodedOptions : [];
                }

                $processedQuestions[$questionId]['table_columns'][] = [
                    'id' => $row['column_id'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'is_required' => (bool)$row['is_required'],
                    'sort_order' => $row['column_sort_order'],
                    'options' => $columnOptions
                ];
            }
        }

        return array_values($processedQuestions);
    }
    public function getQuestionById($id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM questions WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getQuestionsByCategory($categoryId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM questions WHERE category_id = ? AND is_active = 1 ORDER BY sort_order'
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createQuestion($data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO questions (category_id, category, section, question_text, question_type, options, 
                                   validation_rules, conditional_logic, is_required, sort_order, is_active, category_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['category_id'],
            $data['category'],
            $data['section'],
            $data['question_text'],
            $data['question_type'],
            json_encode($data['options'] ?? null),
            json_encode($data['validation_rules'] ?? null),
            json_encode($data['conditional_logic'] ?? null),
            $data['is_required'] ?? 0,
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $data['category_order'] ?? 0
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateQuestion($id, $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE questions 
             SET category_id = ?, category = ?, section = ?, question_text = ?, question_type = ?, 
                 options = ?, validation_rules = ?, conditional_logic = ?, is_required = ?, 
                 sort_order = ?, is_active = ?, category_order = ?, updated_at = NOW()
             WHERE id = ?'
        );
        return $stmt->execute([
            $data['category_id'],
            $data['category'],
            $data['section'],
            $data['question_text'],
            $data['question_type'],
            json_encode($data['options'] ?? null),
            json_encode($data['validation_rules'] ?? null),
            json_encode($data['conditional_logic'] ?? null),
            $data['is_required'] ?? 0,
            $data['sort_order'] ?? 0,
            $data['is_active'] ?? 1,
            $data['category_order'] ?? 0,
            $id
        ]);
    }

    public function deleteQuestion($id): bool
    {
        // Soft delete by setting is_active to 0
        $stmt = $this->db->prepare('UPDATE questions SET is_active = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }
}