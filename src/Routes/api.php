<?php
use Slim\App;
use App\Controllers\AuthController;
use App\Controllers\ApplicantController;
use App\Controllers\AdminController;
use App\Controllers\BankController;
use App\Controllers\NotificationController;
use App\Middleware\AuthMiddleware;

// Health check
$app->get('/', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'OK', 'message' => 'API is running']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Authentication routes (public)
$app->post('/register', [AuthController::class, 'register']);

$app->post('/login', [AuthController::class, 'login']);
$app->post('/regu', [AuthController::class, 'register']);
$app->get('/verify', [AuthController::class, 'verify']);
$app->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$app->post('/reset-password', [AuthController::class, 'resetPassword']);

// PIN verification (public - no auth required initially)
//$app->post('/applicant/verify-application-pin', [ApplicantController::class, 'verifyApplicationPin']);

// PIN format validation (public utility)
$app->post('/validate-pin-format', function ($request, $response) {
    $data = json_decode((string)$request->getBody(), true) ?? [];

    if (empty($data['pin'])) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'valid' => false,
            'error' => 'PIN is required'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
    }

    $pin = $data['pin'];
    $isValid = preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $pin);

    $response->getBody()->write(json_encode([
        'success' => true,
        'valid' => (bool)$isValid,
        'message' => $isValid ? 'PIN format is valid' : 'Invalid PIN format. Expected: XXXX-XXXX-XXXX-XXXX'
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

// For immediate file upload
$app->post('/applicant/application/upload-file',
    [ApplicantController::class, 'uploadFile']
)->add(new AuthMiddleware(['applicant']));

// Applicant routes (protected)
$app->group('/applicant', function ($group) {
    // Profile management
    $group->get('/profile', [ApplicantController::class, 'getProfile']);
    $group->put('/profile', [ApplicantController::class, 'updateProfile']);
    $group->post('/verify-application-pin', [ApplicantController::class, 'verifyApplicationPin']);

    // Application questions
    $group->get('/questions', [ApplicantController::class, 'getQuestions']);

    // PIN-based application start
    $group->post('/start-application-with-pin', [ApplicantController::class, 'startApplicationWithPin']);

    // Application progress
    $group->post('/application/save-progress', [ApplicantController::class, 'saveProgress']);
    $group->get('/application/load-progress', [ApplicantController::class, 'loadProgress']);

    // Application submission
    $group->post('/application/submit', [ApplicantController::class, 'submitApplication']);
    $group->get('/application/submitted', [ApplicantController::class, 'getSubmittedApplication']);

    // Legacy endpoints (for backward compatibility)
    $group->post('/application', [ApplicantController::class, 'createApplication']);
    $group->get('/status', [ApplicantController::class, 'getStatus']);

    // Payment
    $group->post('/payment', [ApplicantController::class, 'initiatePayment']);

    // Notifications
    $group->get('/notifications', [NotificationController::class, 'getNotifications']);
})->add(new AuthMiddleware(['applicant']));

// Admin routes (protected)
$app->group('/admin', function ($group) {
    // Application management
    $group->get('/applications', [AdminController::class, 'getApplications']);
    $group->get('/applications/{id}', [AdminController::class, 'getApplicationDetails']);
    $group->put('/applications/{id}/status', [AdminController::class, 'updateApplicationStatus']);

    // Analytics
    $group->get('/analytics', [AdminController::class, 'getAnalytics']);

    // User management
    $group->post('/users', [AdminController::class, 'createUser']);
    $group->get('/users', [AdminController::class, 'getUsers']);
    $group->put('/users/{id}', [AdminController::class, 'updateUser']);
    $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);

    // Interview and offer management
    $group->post('/approve-interview', [AdminController::class, 'approveInterview']);
    $group->post('/issue-offer', [AdminController::class, 'issueOffer']);

    // Permissions
    $group->get('/permissions', [AdminController::class, 'getPermissions']);

    // Question management
    $group->get('/questions', [AdminController::class, 'getQuestions']);
    $group->post('/questions', [AdminController::class, 'createQuestion']);
    $group->put('/questions/{id}', [AdminController::class, 'updateQuestion']);
    $group->delete('/questions/{id}', [AdminController::class, 'deleteQuestion']);

    // PIN Management (NEW)
    $group->get('/pin-statistics', [AdminController::class, 'getPinStatistics']);
    $group->get('/pin-audit', [AdminController::class, 'getPinAudit']);
    $group->post('/expire-pin', [AdminController::class, 'expirePin']);
    $group->post('/cleanup-expired-pins', [AdminController::class, 'cleanupExpiredPins']);
})->add(new AuthMiddleware(['principal', 'finance', 'it', 'registrar']));

// Bank routes (protected)
$app->group('/bank', function ($group) {
    // Enhanced payment creation with PIN generation
    $group->post('/create-payment', [BankController::class, 'createPayment']);

    // Get all payments (enhanced with PIN data)
    $group->get('/payments', [BankController::class, 'getPayments']);

    // Add this line in the applicant routes group
    $group->get('/payments-id', [BankController::class, 'getPaymentsByUserId']);

    // Legacy payment confirmation (kept for backward compatibility)
    $group->post('/confirm-payment', [BankController::class, 'confirmPayment']);

    // Analytics (enhanced with PIN statistics)
    $group->get('/analytics', [BankController::class, 'getAnalytics']);

    // PIN generation utility
    $group->post('/generate-pin', [BankController::class, 'generatePin']);
})->add(new AuthMiddleware(['bank']));

// Error handling
$app->addErrorMiddleware(true, true, true);