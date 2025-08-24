<?php
namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Application;
use App\Models\ApplicationProgress;
use App\Models\ApplicationResponse;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\UserProfile;
use App\Models\Question;
use PHPMailer\PHPMailer\PHPMailer;

class ApplicantController
{
    private Application $application;
    private ApplicationProgress $applicationProgress;
    private ApplicationResponse $applicationResponse;
    private Payment $payment;
    private Notification $notification;
    private AuditLog $auditLog;
    private UserProfile $userProfile;
    private Question $question;

    public function __construct(
        Application $application,
        ApplicationProgress $applicationProgress,
        ApplicationResponse $applicationResponse,
        Payment $payment,
        Notification $notification,
        AuditLog $auditLog,
        UserProfile $userProfile,
        Question $question
    ) {
        $this->application = $application;
        $this->applicationProgress = $applicationProgress;
        $this->applicationResponse = $applicationResponse;
        $this->payment = $payment;
        $this->notification = $notification;
        $this->auditLog = $auditLog;
        $this->userProfile = $userProfile;
        $this->question = $question;
    }

    private function getUserId(Request $request): int
    {
        $userAttr = $request->getAttribute('user');
        if (is_array($userAttr) && isset($userAttr['id'])) {
            return (int)$userAttr['id'];
        }
        if (is_object($userAttr) && isset($userAttr->id)) {
            return (int)$userAttr->id;
        }
        return (int)$request->getAttribute('user_id');
    }


    private function getUserIdNew(Request $request): int
    {
        // First try to get from request attributes (middleware/session)
        $userId = (int)$request->getAttribute('user_id');

        // If not found in attributes, try to get from request body
        if ($userId === 0) {
            $data = json_decode((string)$request->getBody(), true) ?? [];
            $userId = (int)($data['user_id'] ?? 0);
        }

        // Validate that user exists using UserProfile model
        if ($userId > 0) {
            try {
                $userExists = $this->userProfile->findWithUserByUserId($userId);
                if (!$userExists) {
                    error_log("User ID {$userId} not found");
                    return 0;
                }
            } catch (\Exception $e) {
                error_log("Error validating user ID: " . $e->getMessage());
                return 0;
            }
        }

        return $userId;
    }

    public function getProfile(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = $this->userProfile->findWithUserByUserId($userId);

        if (!$data || empty($data['first_name'])) {
            $data = $data ?? ['user_id' => $userId, 'email' => null, 'role' => null];
            $data += [
                'first_name' => '',
                'last_name' => '',
                'phone' => '',
                'date_of_birth' => '',
                'nationality' => '',
                'address' => [
                    'country' => '',
                    'province' => '',
                    'district' => '',
                    'town' => '',
                ],
                'emergency_contact' => [
                    'name' => '',
                    'phone' => '',
                ],
                'profile_picture' => null,
            ];
        } else {
            $data['address'] = json_decode($data['address'] ?? '{}', true) ?: ['country'=>'','province'=>'','district'=>'','town'=>''];
            $data['emergency_contact'] = json_decode($data['emergency_contact'] ?? '{}', true) ?: ['name'=>'','phone'=>''];
        }

        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'],
            'nationality' => $data['nationality'],
            'address' => $data['address'],
            'emergency_contact' => $data['emergency_contact'],
            'user' => [
                'id' => $data['user_id'],
                'email' => $data['email'],
                'role' => $data['role'],
            ]
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updateProfile(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = json_decode((string)$request->getBody(), true) ?? [];

        $required = ['first_name','last_name','phone','date_of_birth','nationality','address','emergency_contact'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                return $this->errorResponse($response, "Missing field: $field", 422);
            }
        }

        $existing = $this->userProfile->findByUserId($userId);
        if ($existing) {
            $this->userProfile->update($userId, $data);
        } else {
            $this->userProfile->create($userId, $data);
        }

        return $this->getProfile($request, $response);
    }

    public function getQuestions(Request $request, Response $response): Response
    {
        $questions = $this->question->getAllActiveQuestions();
        $response->getBody()->write(json_encode($questions));
        return $response->withHeader('Content-Type', 'application/json');
    }

//    public function saveProgress(Request $request, Response $response): Response
//    {
//        $userId = $this->getUserId($request);
//        $contentType = $request->getHeaderLine('Content-Type');
//
//        // Handle multipart/form-data
//        if (strpos($contentType, 'multipart/form-data') !== false) {
//            $data = $request->getParsedBody();
//            $formData = json_decode($data['formData'] ?? '{}', true);
//            $currentStep = (int)($data['currentStep'] ?? 0);
//            $completedSteps = json_decode($data['completedSteps'] ?? '[]', true);
//            $files = $request->getUploadedFiles();
//
//            // Get existing metadata to preserve already uploaded files
//            $existingProgress = $this->applicationProgress->getProgress($userId);
//            $existingFilesMetadata = [];
//            if ($existingProgress && !empty($existingProgress['files_metadata'])) {
//                $existingFilesMetadata = json_decode($existingProgress['files_metadata'], true) ?: [];
//            }
//
//            // Handle file uploads
//            $filesMetadata = $existingFilesMetadata; // Start with existing metadata
//            $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
//            $baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost:8000';
//
//            // Ensure upload directory exists
//            if (!is_dir($uploadDir)) {
//                mkdir($uploadDir, 0777, true);
//            }
//
//            foreach ($files as $key => $file) {
//                if ($file && $file->getError() === UPLOAD_ERR_OK) {
//                    // Validate file size (10MB max)
//                    if ($file->getSize() > 10 * 1024 * 1024) {
//                        continue; // Skip oversized files
//                    }
//
//                    $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
//                    $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
//                    $filepath = $uploadDir . $filename;
//
//                    try {
//                        $file->moveTo($filepath);
//
//                        // Store metadata with full URL
//                        $filesMetadata[$key] = [
//                            'filename' => $filename,
//                            'original_name' => $file->getClientFilename(),
//                            'file_path' => $baseUrl . '/' . trim($uploadDir, '/') . '/' . $filename,
//                            'size' => $file->getSize(),
//                            'type' => $file->getClientMediaType(),
//                            'uploaded_at' => date('Y-m-d H:i:s')
//                        ];
//                    } catch (Exception $e) {
//                        error_log("File upload failed for $key: " . $e->getMessage());
//                    }
//                }
//            }
//        } else {
//            // Handle JSON data
//            $data = json_decode((string)$request->getBody(), true) ?? [];
//            $formData = $data['formData'] ?? [];
//            $currentStep = $data['currentStep'] ?? 0;
//            $completedSteps = $data['completedSteps'] ?? [];
//
//            // Preserve existing file metadata when saving via JSON
//            $existingProgress = $this->applicationProgress->getProgress($userId);
//            $filesMetadata = [];
//            if ($existingProgress && !empty($existingProgress['files_metadata'])) {
//                $filesMetadata = json_decode($existingProgress['files_metadata'], true) ?: [];
//            }
//        }
//
//        // Save or update progress
//        $result = $this->applicationProgress->saveProgress($userId, [
//            'form_data' => $formData,
//            'files_metadata' => $filesMetadata,
//            'current_step' => $currentStep,
//            'completed_steps' => $completedSteps
//        ]);
//
//        $this->auditLog->create($userId, 'save_application_progress', [
//            'step' => $currentStep,
//            'files_count' => count($filesMetadata)
//        ]);
//
//        $response->getBody()->write(json_encode([
//            'success' => true,
//            'message' => 'Progress saved',
//            'files_metadata' => $filesMetadata // Return updated metadata to frontend
//        ]));
//        return $response->withHeader('Content-Type', 'application/json');
//    }

    public function saveProgress(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $contentType = $request->getHeaderLine('Content-Type');

        // Get existing metadata first
        $existingProgress = $this->applicationProgress->getProgress($userId);
        $existingFilesMetadata = [];
        if ($existingProgress && !empty($existingProgress['files_metadata'])) {
            $existingFilesMetadata = is_string($existingProgress['files_metadata'])
                ? json_decode($existingProgress['files_metadata'], true)
                : $existingProgress['files_metadata'];
            $existingFilesMetadata = $existingFilesMetadata ?: [];
        }

        if (strpos($contentType, 'multipart/form-data') !== false) {
            $data = $request->getParsedBody();
            $formData = json_decode($data['formData'] ?? '{}', true);
            $currentStep = (int)($data['currentStep'] ?? 0);
            $completedSteps = json_decode($data['completedSteps'] ?? '[]', true);

            // Start with existing metadata
            $filesMetadata = $existingFilesMetadata;

            // Handle new file uploads...
            // (rest of file upload logic)
        } else {
            $data = json_decode((string)$request->getBody(), true) ?? [];
            $formData = $data['formData'] ?? [];
            $currentStep = $data['currentStep'] ?? 0;
            $completedSteps = $data['completedSteps'] ?? [];

            // IMPORTANT: Preserve existing file metadata
            $filesMetadata = $existingFilesMetadata;
        }

        // Save progress with preserved metadata
        $result = $this->applicationProgress->saveProgress($userId, [
            'form_data' => $formData,
            'files_metadata' => $filesMetadata,
            'current_step' => $currentStep,
            'completed_steps' => $completedSteps
        ]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Progress saved',
            'files_metadata' => $filesMetadata
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

//    public function loadProgress(Request $request, Response $response): Response
//    {
//        $userId = $this->getUserId($request);
//        $progress = $this->applicationProgress->getProgress($userId);
//
//        if (!$progress) {
//            $progress = [
//                'draft' => [
//                    'formData' => '{}',
//                    'filesMetadata' => '{}',
//                    'currentStep' => 0,
//                    'completedSteps' => '[]',
//                    'updated_at' => null
//                ]
//            ];
//        } else {
//            $progress = [
//                'draft' => [
//                    'formData' => $progress['form_data'] ?? '{}',
//                    'filesMetadata' => $progress['files_metadata'] ?? '{}',
//                    'currentStep' => $progress['current_step'] ?? 0,
//                    'completedSteps' => $progress['completed_steps'] ?? '[]',
//                    'updated_at' => $progress['last_saved_at']
//                ]
//            ];
//        }
//
//        $response->getBody()->write(json_encode($progress));
//        return $response->withHeader('Content-Type', 'application/json');
//    }

    public function loadProgress(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $progress = $this->applicationProgress->getProgress($userId);

        if (!$progress) {
            $progress = [
                'draft' => [
                    'formData' => '{}',
                    'filesMetadata' => '{}',
                    'currentStep' => 0,
                    'completedSteps' => '[]',
                    'updated_at' => null
                ]
            ];
        } else {
            // Ensure files_metadata is properly formatted
            $filesMetadata = $progress['files_metadata'] ?? '{}';

            // If it's already a JSON string, keep it; otherwise encode it
            if (!is_string($filesMetadata)) {
                $filesMetadata = json_encode($filesMetadata);
            }

            // Validate it's valid JSON
            $decoded = json_decode($filesMetadata, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $filesMetadata = '{}';
            }

            $progress = [
                'draft' => [
                    'formData' => $progress['form_data'] ?? '{}',
                    'filesMetadata' => $filesMetadata,
                    'currentStep' => $progress['current_step'] ?? 0,
                    'completedSteps' => $progress['completed_steps'] ?? '[]',
                    'updated_at' => $progress['last_saved_at']
                ]
            ];
        }

        $response->getBody()->write(json_encode($progress));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Verify application PIN
     */

//    public function verifyApplicationPin(Request $request, Response $response): Response
//    {
//        $data = json_decode((string)$request->getBody(), true) ?? [];
//
//        if (empty($data['application_pin'])) {
//            return $this->errorResponse($response, 'Application PIN is required', 422);
//        }
//
//        // Clean the PIN (remove hyphens)
//        $cleanPin = str_replace('-', '', $data['application_pin']);
//
//        // Validate PIN format (should be 16 digits)
//        if (!preg_match('/^\d{16}$/', $cleanPin)) {
//            return $this->errorResponse($response, 'Invalid PIN format. PIN must be 16 digits.', 422);
//        }
//
//        // Re-format with hyphens for database lookup
//        $formattedPin = substr($cleanPin, 0, 4) . '-' .
//            substr($cleanPin, 4, 4) . '-' .
//            substr($cleanPin, 8, 4) . '-' .
//            substr($cleanPin, 12, 4);
//
//        try {
//            $result = $this->payment->verifyApplicationPin($formattedPin);
//
//            if (!$result) {
//                return $this->errorResponse($response, 'Invalid PIN. Please check your payment receipt and try again.', 404);
//            }
//
//            if ($result['expired']) {
//                return $this->errorResponse($response, 'This PIN has expired. Please make a new payment to get a fresh PIN.', 410);
//            }
//
//            // Log the PIN verification
//            $userId = $this->getUserIdNew($request);
//            $this->auditLog->create($userId, 'verify_application_pin', [
//                'pin' => $formattedPin,
//                'payment_id' => $result['payment']['id']
//            ]);
//
//
//            $response->getBody()->write(json_encode([
//                'success' => true,
//                'message' => 'PIN verified successfully',
//                'payment' => [
//                    'id' => $result['payment']['id'],
//                    'amount' => $result['payment']['amount'],
//                    'transaction_reference' => $result['payment']['transaction_reference'],
//                    'depositor_name' => $result['payment']['depositor_name'],
//                    'payment_date' => $result['payment']['payment_date']
//                ]
//            ]));
//            return $response->withHeader('Content-Type', 'application/json');
//
//        } catch (\Exception $e) {
//            error_log("PIN verification error: " . $e->getMessage());
//            return $this->errorResponse($response, 'Unable to verify PIN. Please try again or contact support.' . $userId, 500);
//        }
//    }

    public function verifyApplicationPin(Request $request, Response $response): Response
    {
        $data = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($data['application_pin'])) {
            return $this->errorResponse($response, 'Application PIN is required', 422);
        }

        // Clean the PIN (remove hyphens)
        $cleanPin = str_replace('-', '', $data['application_pin']);

        // Validate PIN format (should be 16 digits)
        if (!preg_match('/^\d{16}$/', $cleanPin)) {
            return $this->errorResponse($response, 'Invalid PIN format. PIN must be 16 digits.', 422);
        }

        // Re-format with hyphens for database lookup
        $formattedPin = substr($cleanPin, 0, 4) . '-' .
            substr($cleanPin, 4, 4) . '-' .
            substr($cleanPin, 8, 4) . '-' .
            substr($cleanPin, 12, 4);

        try {
            $result = $this->payment->verifyApplicationPin($formattedPin);

            if (!$result) {
                return $this->errorResponse($response, 'Invalid PIN. Please check your payment receipt and try again.', 404);
            }

            if ($result['expired']) {
                return $this->errorResponse($response, 'This PIN has expired. Please make a new payment to get a fresh PIN.', 410);
            }

            // Get current user ID
            $userId = $this->getUserIdNew($request);

            // Check if PIN has already been used by another user
            if ($result['payment']['pin_used_by_user_id']) {
                // If the current user is not the one who used the PIN
                if ($result['payment']['pin_used_by_user_id'] != $userId) {
                    return $this->errorResponse($response, 'This PIN has already been used by another applicant.', 403);
                }
            } else {
                // Mark PIN as used by this user (first time use)
                $this->payment->assignPinToApplicant($formattedPin, $userId);
            }

            // Log the PIN verification
            $this->auditLog->create($userId, 'verify_application_pin', [
                'pin' => $formattedPin,
                'payment_id' => $result['payment']['id']
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'PIN verified successfully',
                'payment' => [
                    'id' => $result['payment']['id'],
                    'amount' => $result['payment']['amount'],
                    'transaction_reference' => $result['payment']['transaction_reference'],
                    'depositor_name' => $result['payment']['depositor_name'],
                    'payment_date' => $result['payment']['payment_date']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("PIN verification error: " . $e->getMessage());
            return $this->errorResponse($response, 'Unable to verify PIN. Please try again or contact support.', 500);
        }
    }

    /**
     * Start application with verified PIN
     */
    public function startApplicationWithPin(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($data['application_pin'])) {
            return $this->errorResponse($response, 'Application PIN is required', 422);
        }

        // Re-verify the PIN
        $result = $this->payment->verifyApplicationPin($data['application_pin']);

        if (!$result || $result['expired']) {
            return $this->errorResponse($response, 'Invalid or expired PIN', 400);
        }

        try {
            // Mark PIN as used
            $this->payment->markPinAsUsed($data['application_pin']);

            // Check if user already has an application in progress
            $existingApplication = $this->application->getLatestByUser($userId);

            if ($existingApplication && $existingApplication['application_status'] === 'draft') {
                // Continue with existing application
                $applicationId = $existingApplication['id'];
            } else {
                // Create new application
                $applicationId = $this->application->create($userId, null, [
                    'payment_reference' => $result['payment']['transaction_reference'],
                    'payment_pin' => $data['application_pin']
                ]);
            }

            // Log the application start
            $this->auditLog->create($userId, 'start_application_with_pin', [
                'application_id' => $applicationId,
                'payment_id' => $result['payment']['id'],
                'pin' => $data['application_pin']
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Application started successfully',
                'application_id' => $applicationId,
                'payment_info' => $result['payment']
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Start application error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to start application', 500);
        }
    }

    public function submitApplication(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $contentType = $request->getHeaderLine('Content-Type');

        // Handle multipart/form-data
        if (strpos($contentType, 'multipart/form-data') !== false) {
            $data = $request->getParsedBody();
            $formData = json_decode($data['formData'] ?? '{}', true);
            $files = $request->getUploadedFiles();

            // Handle file uploads
            $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
            $baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost:8000';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filesPaths = [];
            foreach ($files as $key => $file) {
                if ($file && $file->getError() === UPLOAD_ERR_OK) {
                    $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
                    $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
                    $filepath = $uploadDir . $filename;

                    try {
                        $file->moveTo($filepath);
                        $filesPaths[$key] = $filename;
                    } catch (Exception $e) {
                        error_log("File upload failed for $key: " . $e->getMessage());
                    }
                }
            }

            // Also get files from existing progress
            $existingProgress = $this->applicationProgress->getProgress($userId);
            if ($existingProgress && !empty($existingProgress['files_metadata'])) {
                $existingFilesMetadata = json_decode($existingProgress['files_metadata'], true) ?: [];
                foreach ($existingFilesMetadata as $key => $metadata) {
                    if (!isset($filesPaths[$key]) && !empty($metadata['file_path'])) {
                        $filesPaths[$key] = $metadata['file_path'];
                    }
                }
            }
        } else {
            $data = json_decode((string)$request->getBody(), true) ?? [];
            $formData = $data['formData'] ?? [];

            // Get files from saved progress
            $filesPaths = [];
            $existingProgress = $this->applicationProgress->getProgress($userId);
            if ($existingProgress && !empty($existingProgress['files_metadata'])) {
                $existingFilesMetadata = json_decode($existingProgress['files_metadata'], true) ?: [];
                foreach ($existingFilesMetadata as $key => $metadata) {
                    if (!empty($metadata['file_path'])) {
                        $filesPaths[$key] = $metadata['file_path'];
                    }
                }
            }
        }

        // Check if user already has a submitted application and get/reuse the application ID
        $existingApp = $this->application->getLatestSubmittedByUser($userId);
        $applicationId = null;

        if ($existingApp) {
            // Reuse existing application ID for resubmissions
            $applicationId = $existingApp['id'];

            // Delete existing responses to replace them
            $this->applicationResponse->deleteByApplicationId($applicationId);

            // Update the application
            $this->application->updateFormData($applicationId, $formData);
            $this->application->updateStatus($applicationId, 'submitted');
        } else {
            // Create new application
            $applicationId = $this->application->submitNew($userId, $formData);
        }

        // Generate Application Number if not exists
        $appNumber = $this->application->getApplicationNumber($applicationId);
        if (!$appNumber) {
            $appNumber = $this->application->generateApplicationNumber($applicationId);
        }

        // Save responses - improved logic to handle proper question IDs
        foreach ($formData as $key => $value) {
            // Extract question ID more carefully
            if (strpos($key, 'question_') === 0) {
                $questionIdStr = str_replace('question_', '', $key);

                // Validate that we have a numeric question ID
                if (is_numeric($questionIdStr)) {
                    $questionId = (int)$questionIdStr;
                    $filePath = isset($filesPaths[$key]) ? $filesPaths[$key] : null;

                    if (is_array($value)) {
                        $value = json_encode($value);
                    }

                    $this->applicationResponse->create($applicationId, $questionId, $value, $filePath);
                }
            }
            // Handle table-based responses (like WASSCE results)
            elseif (preg_match('/^(\w+)_(\d+)_(.+)$/', $key, $matches)) {
                // This handles keys like 'wassce_sitting_1_result', 'employment_1_company', etc.
                $tableType = $matches[1]; // 'wassce', 'employment', etc.
                $rowIndex = (int)$matches[2]; // 1, 2, 3, etc.
                $fieldName = $matches[3]; // 'result', 'company', etc.

                // You might want to store these in a separate table or as JSON
                // For now, skip these or handle them differently
                continue;
            }
        }

        // Clear progress after successful submission
        $this->applicationProgress->clearProgress($userId);

        // Send confirmation email
        $this->sendApplicationConfirmationEmail($userId, $applicationId, $appNumber);

        // Create notification
        $this->notification->create($userId, 'application_submitted', 'Your application has been submitted successfully');

        // Log the submission
        $this->auditLog->create($userId, 'submit_application', ['application_id' => $applicationId]);

        // Get the submitted application details
        $application = $this->application->getApplicationWithResponses($applicationId);

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Application submitted successfully',
            'application' => $application,
            'application_id' => $applicationId,
            'application_number' => $appNumber
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getSubmittedApplication(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $application = $this->application->getLatestSubmittedByUser($userId);

        if (!$application) {
            $response->getBody()->write(json_encode(['application' => null]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $responses = $this->applicationResponse->getByApplicationId($application['id']);
        $categorizedResponses = [];

        foreach ($responses as $response_item) {
            $category = $response_item['category'] ?? 'Other';
            if (!isset($categorizedResponses[$category])) {
                $categorizedResponses[$category] = [];
            }
            $categorizedResponses[$category][] = [
                'question_id' => $response_item['question_id'],
                'question_text' => $response_item['question_text'],
                'answer' => $response_item['answer'] ?? $response_item['response_value'],
                'file_path' => $response_item['file_path'],
                'question_type' => $response_item['question_type']
            ];
        }

        // Get application number
        $appNumber = $this->application->getApplicationNumber($application['id']);

        $result = [
            'application' => [
                'id' => $application['id'],
                'application_number' => $appNumber,
                'submitted_at' => $application['submission_date'] ?? $application['created_at'],
                'categories' => $categorizedResponses
            ]
        ];

        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Add new endpoint for immediate file upload
//    public function uploadFile(Request $request, Response $response): Response
//    {
//        $userId = $this->getUserId($request);
//        $files = $request->getUploadedFiles();
//
//        if (empty($files['file'])) {
//            return $this->errorResponse($response, 'No file provided', 400);
//        }
//
//        $file = $files['file'];
//        if ($file->getError() !== UPLOAD_ERR_OK) {
//            return $this->errorResponse($response, 'File upload error', 400);
//        }
//
//        // Validate file size (10MB max)
//        if ($file->getSize() > 10 * 1024 * 1024) {
//            return $this->errorResponse($response, 'File too large. Maximum size is 10MB', 400);
//        }
//
//        $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
//        $baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost:8000';
//        if (!is_dir($uploadDir)) {
//            mkdir($uploadDir, 0777, true);
//        }
//
//        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
//        $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
//        $filepath = $uploadDir . $filename;
//
//        try {
//            $file->moveTo($filepath);
//
//            $fileMetadata = [
//                'filename' => $filename,
//                'original_name' => $file->getClientFilename(),
//                'file_path' => $baseUrl . '/' . trim($uploadDir, '/') . '/' . $filename,
//                'size' => $file->getSize(),
//                'type' => $file->getClientMediaType(),
//                'uploaded_at' => date('Y-m-d H:i:s')
//            ];
//
//            $response->getBody()->write(json_encode([
//                'success' => true,
//                'file_metadata' => $fileMetadata
//            ]));
//            return $response->withHeader('Content-Type', 'application/json');
//
//        } catch (Exception $e) {
//            error_log("File upload failed: " . $e->getMessage());
//            return $this->errorResponse($response, 'File upload failed', 500);
//        }
//    }

    public function uploadFile(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $files = $request->getUploadedFiles();

        // Get question_id from the request
        $data = $request->getParsedBody();
        $questionKey = $data['question_key'] ?? null; // e.g., "question_23" or "wassce_sitting_1_statement"

        if (empty($files['file'])) {
            return $this->errorResponse($response, 'No file provided', 400);
        }

        $file = $files['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->errorResponse($response, 'File upload error', 400);
        }

        // Validate file size (10MB max)
        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->errorResponse($response, 'File too large. Maximum size is 10MB', 400);
        }

        $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
        $baseUrl = $_ENV['BASE_URL'] ?? 'http://localhost:8000';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        try {
            $file->moveTo($filepath);

            $fileMetadata = [
                'filename' => $filename,
                'original_name' => $file->getClientFilename(),
                'file_path' => $baseUrl . '/' . trim($uploadDir, '/') . '/' . $filename,
                'size' => $file->getSize(),
                'type' => $file->getClientMediaType(),
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            // If question_key is provided, save to progress immediately
            if ($questionKey && $userId) {
                // Get existing progress
                $existingProgress = $this->applicationProgress->getProgress($userId);
                $existingFilesMetadata = [];
                if ($existingProgress && !empty($existingProgress['files_metadata'])) {
                    $existingFilesMetadata = json_decode($existingProgress['files_metadata'], true) ?: [];
                }

                // Update with new file metadata
                $existingFilesMetadata[$questionKey] = $fileMetadata;

                // Save back to progress
                $this->applicationProgress->updateFilesMetadata($userId, $existingFilesMetadata);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'file_metadata' => $fileMetadata,
                'question_key' => $questionKey
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            error_log("File upload failed: " . $e->getMessage());
            return $this->errorResponse($response, 'File upload failed', 500);
        }
    }

    public function createApplication(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $appId = $this->application->create($userId, $data['program_type'] ?? null, $data['form_data'] ?? []);

        $uploadDir = $_ENV['UPLOAD_DIR'] ?? 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($files as $file) {
            if ($file && $file->getError() === UPLOAD_ERR_OK) {
                if ($file->getSize() > 10 * 1024 * 1024) {
                    return $this->errorResponse($response, 'File too large. Maximum size is 10MB', 400);
                }
                $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), ['pdf', 'jpg', 'jpeg', 'png'])) {
                    return $this->errorResponse($response, 'Invalid file type. Allowed: pdf, jpg, jpeg, png', 400);
                }
                $filename = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                $file->moveTo($filepath);
            }
        }

        $this->auditLog->create($userId, 'create_application', ['application_id' => $appId]);
        $response->getBody()->write(json_encode(['application_id' => $appId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $apps = $this->application->findByUser($userId);
        $response->getBody()->write(json_encode($apps));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function sendApplicationConfirmationEmail(int $userId, int $applicationId, string $applicationNumber): void
    {
        try {
            // Get user details
            $userProfile = $this->userProfile->findWithUserByUserId($userId);
            if (!$userProfile || empty($userProfile['email'])) {
                return;
            }

            // Get application details with responses
            $application = $this->application->getApplicationWithResponses($applicationId);
            $responses = $this->applicationResponse->getByApplicationId($applicationId);

            // Initialize PHPMailer
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $_ENV['SMTP_HOST'];
            $mailer->Port = $_ENV['SMTP_PORT'];
            $mailer->SMTPAuth = true;
            $mailer->SMTPSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
            $mailer->Username = $_ENV['SMTP_USER'];
            $mailer->Password = $_ENV['SMTP_PASS'];

            $mailer->setFrom($_ENV['SMTP_USER'], 'Mattru School of Nursing');
            $mailer->addAddress($userProfile['email'], $userProfile['first_name'] . ' ' . $userProfile['last_name']);
            $mailer->isHTML(true);
            $mailer->Subject = "✅ Application Submitted Successfully - " . $applicationNumber;

            // Create modern HTML email template
            $emailContent = $this->generateApplicationEmailTemplate($userProfile, $application, $responses, $applicationNumber);
            $mailer->Body = $emailContent;

            $mailer->send();
        } catch (Exception $e) {
            error_log("Failed to send application confirmation email: " . $e->getMessage());
        }
    }

    private function generateApplicationEmailTemplate(array $userProfile, array $application, array $responses, string $applicationNumber): string
    {
//        $submissionDate = date('F j, Y \a\t g:i A', strtotime($application['submission_date'] ?? $application['created_at']));
//        $applicantName = $userProfile['first_name'] . ' ' . $userProfile['last_name'];

        // Fix the null date handling to prevent strtotime deprecation warning
        $submissionDate = 'Not available';

        // Check submission_date first, then created_at, with proper null checks
        if (!empty($application['submission_date'])) {
            $submissionDate = date('F j, Y \a\t g:i A', strtotime($application['submission_date']));
        } elseif (!empty($application['created_at'])) {
            $submissionDate = date('F j, Y \a\t g:i A', strtotime($application['created_at']));
        }

        $applicantName = $userProfile['first_name'] . ' ' . $userProfile['last_name'];

        // Categorize responses
        $categorizedResponses = [];
        foreach ($responses as $response) {
            $category = $response['category'] ?? 'Other Information';
            if (!isset($categorizedResponses[$category])) {
                $categorizedResponses[$category] = [];
            }
            $categorizedResponses[$category][] = $response;
        }

        return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Application Confirmation</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; line-height: 1.6; color: #333; background-color: #f8fafc; }
            .container { max-width: 600px; margin: 0 auto; background: white; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
            .header h1 { font-size: 28px; margin-bottom: 10px; }
            .header p { font-size: 16px; opacity: 0.9; }
            .success-badge { background: #10b981; color: white; padding: 8px 16px; border-radius: 20px; display: inline-block; margin: 20px 0; font-weight: 600; }
            .content { padding: 30px; }
            .app-details { background: #f1f5f9; border-radius: 12px; padding: 25px; margin: 25px 0; border-left: 4px solid #667eea; }
            .app-details h3 { color: #667eea; margin-bottom: 15px; font-size: 18px; }
            .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
            .detail-label { font-weight: 600; color: #64748b; }
            .detail-value { color: #1e293b; }
            .section { margin: 30px 0; }
            .section-title { background: #667eea; color: white; padding: 12px 20px; border-radius: 8px 8px 0 0; font-weight: 600; font-size: 16px; }
            .section-content { background: #f8fafc; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; padding: 20px; }
            .response-item { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
            .response-item:last-child { border-bottom: none; margin-bottom: 0; }
            .question { font-weight: 600; color: #374151; margin-bottom: 5px; }
            .answer { color: #6b7280; background: white; padding: 10px; border-radius: 6px; border: 1px solid #e5e7eb; }
            .file-link { color: #667eea; text-decoration: none; }
            .file-link:hover { text-decoration: underline; }
            .next-steps { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 12px; margin: 30px 0; }
            .next-steps h3 { margin-bottom: 15px; }
            .next-steps ul { list-style: none; }
            .next-steps li { margin: 8px 0; padding-left: 20px; position: relative; }
            .next-steps li:before { content: '✓'; position: absolute; left: 0; font-weight: bold; }
            .footer { background: #1e293b; color: white; padding: 30px; text-align: center; }
            .footer p { margin: 5px 0; }
            .contact-info { margin: 20px 0; }
            .social-links { margin: 20px 0; }
            .social-links a { color: #60a5fa; text-decoration: none; margin: 0 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <!-- Header -->
            <div class='header'>
                <h1>🎓 Mattru Nursing School</h1>
                <p>Excellence in Nursing Education</p>
                <div class='success-badge'>✅ Application Submitted Successfully</div>
            </div>

            <!-- Main Content -->
            <div class='content'>
                <h2>Dear {$applicantName},</h2>
                <p style='margin: 20px 0; font-size: 16px; color: #4b5563;'>
                    Congratulations! Your application has been successfully submitted to Mattru Nursing School. 
                    We have received all your information and will begin processing your application immediately.
                </p>

                <!-- Application Details -->
                <div class='app-details'>
                    <h3>📋 Application Summary</h3>
                    <div class='detail-row'>
                        <span class='detail-label'>Application Number:</span>
                        <span class='detail-value'><strong>{$applicationNumber}</strong></span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Submitted Date:</span>
                        <span class='detail-value'>{$submissionDate}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Applicant Name:</span>
                        <span class='detail-value'>{$applicantName}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Email:</span>
                        <span class='detail-value'>{$userProfile['email']}</span>
                    </div>
                    <div class='detail-row'>
                        <span class='detail-label'>Status:</span>
                        <span class='detail-value' style='color: #10b981; font-weight: 600;'>Under Review</span>
                    </div>
                </div>

                <!-- Application Responses -->
                " . $this->formatApplicationResponses($categorizedResponses) . "

                <!-- Next Steps -->
                <div class='next-steps'>
                    <h3>🚀 What Happens Next?</h3>
                    <ul>
                        <li>Our admissions team will review your application after 30 business days</li>
                        <li>You will receive an email notification about your application status</li>
                        <li>If selected, you'll be invited for an interview or assessment</li>
                        <li>Final admission decisions will be communicated via email and phone</li>
                        <li>Keep your application number ({$applicationNumber}) for future reference</li>
                    </ul>
                </div>

                <div style='background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                    <p style='color: #92400e; margin: 0;'>
                        <strong>📞 Need Help?</strong> Contact our admissions office at 
                        <a href='mailto:admissions@msn.edu.sl' style='color: #92400e;'>admissions@msn.edu.sl</a> 
                        or call +232-78863342 / +232-618435 for any questions about your application.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class='footer'>
                <h3 style='margin-bottom: 15px;'>Mattru School of Nursing</h3>
                <div class='contact-info'>
                    <p>📍 Mattru Jong, Bonthe District, Sierra Leone</p>
                    <p>📧 info@msn.edu.sl | 📞 +232-78-863342</p>
                    <p>🌐 www.msn.edu.sl</p>
                </div>
                <div class='social-links'>
                    <a href='#'>Facebook</a> | 
                    <a href='#'>Twitter</a> | 
                    <a href='#'>LinkedIn</a> | 
                    <a href='#'>Instagram</a>
                </div>
                <p style='margin-top: 20px; font-size: 14px; opacity: 0.8;'>
                    © {
                    <?php echo (new DateTimeImmutable('now', new DateTimeZone('Africa/Freetown')))->format('Y'); ?> Mattru Nursing School. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>";
    }

    private function formatApplicationResponses(array $categorizedResponses): string
    {
        $html = '';

        foreach ($categorizedResponses as $category => $responses) {
            $html .= "
        <div class='section'>
            <div class='section-title'>{$category}</div>
            <div class='section-content'>";

            foreach ($responses as $response) {
                $answer = $response['answer'] ?? $response['response_value'] ?? 'Not provided';
                $filePath = $response['file_path'] ?? null;

                $html .= "
            <div class='response-item'>
                <div class='question'>{$response['question_text']}</div>
                <div class='answer'>";

                if ($filePath) {
                    $html .= "<a href='{$filePath}' class='file-link' target='_blank'>📎 View Uploaded Document</a>";
                } else {
                    $html .= htmlspecialchars($answer);
                }

                $html .= "</div></div>";
            }

            $html .= "</div></div>";
        }

        return $html;
    }

    public function initiatePayment(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = $request->getParsedBody();
        $pin = rand(100000, 999999);
        $paymentId = $this->payment->createForUser($userId, $data['amount'] ?? 500, 'bank', $pin);
        $this->notification->create($userId, 'payment_initiated', "Payment initiated. Reference: TXN$paymentId");
        $this->auditLog->create($userId, 'create_payment', ['payment_id' => $paymentId, 'amount' => $data['amount'] ?? 500]);
        $response->getBody()->write(json_encode(['reference' => "TXN$paymentId", 'pin' => $pin]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}