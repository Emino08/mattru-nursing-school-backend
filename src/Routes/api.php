<?php
//use Psr\Http\Message\ResponseInterface as Response;
//use Psr\Http\Message\ServerRequestInterface as Request;
//use App\Controllers\AuthController;
//use App\Controllers\ApplicantController;
//use App\Controllers\AdminController;
//use App\Controllers\BankController;
//use App\Controllers\NotificationController;
//use App\Middleware\AuthMiddleware;
//
//// Add this at the top of your routes in api.php
//$app->get('/', function (Request $request, Response $response) {
//    $response->getBody()->write('API is working!');
//    return $response;
//});
//
//$app->post('/register', [AuthController::class, 'register']);
//$app->post('/login', [AuthController::class, 'login']);
//$app->get('/verify', [AuthController::class, 'verify']);
//
//$app->group('/applicant', function ($group) {
//    $group->post('/application', [ApplicantController::class, 'createApplication']);
//    $group->post('/application/submit', [ApplicantController::class, 'submitApplication']);
//    $group->get('/status', [ApplicantController::class, 'getStatus']);
//    $group->post('/payment', [ApplicantController::class, 'initiatePayment']);
//    $group->get('/notifications', [NotificationController::class, 'getNotifications']);
//})->add(new AuthMiddleware(['applicant']));
//
//$app->group('/admin', function ($group) {
//    $group->get('/applications', [AdminController::class, 'getApplications']);
//    $group->get('/analytics', [AdminController::class, 'getAnalytics']);
//    $group->post('/create-user', [AdminController::class, 'createUser']);
//    $group->post('/approve-interview', [AdminController::class, 'approveInterview']);
//    $group->post('/issue-offer', [AdminController::class, 'issueOffer']);
//})->add(new AuthMiddleware(['principal', 'finance', 'it', 'registrar']));
//
//$app->group('/bank', function ($group) {
//    $group->post('/confirm-payment', [BankController::class, 'confirmPayment']);
//    $group->get('/analytics', [BankController::class, 'getAnalytics']);
//})->add(new AuthMiddleware(['bank']));


use App\Controllers\AuthController;
use App\Controllers\ApplicantController;
use App\Controllers\AdminController;
use App\Controllers\BankController;
use App\Controllers\NotificationController;
use App\Middleware\AuthMiddleware;

$app->post('/register', [AuthController::class, 'register']);
$app->post('/login', [AuthController::class, 'login']);
$app->get('/verify', [AuthController::class, 'verify']);
$app->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$app->post('/reset-password', [AuthController::class, 'resetPassword']);

$app->group('/applicant', function ($group) {
    $group->post('/application', [ApplicantController::class, 'createApplication']);
    $group->post('/application/submit', [ApplicantController::class, 'submitApplication']);
    $group->get('/status', [ApplicantController::class, 'getStatus']);
    $group->post('/payment', [ApplicantController::class, 'initiatePayment']);
    $group->get('/notifications', [NotificationController::class, 'getNotifications']);
})->add(new AuthMiddleware(['applicant']));

$app->group('/admin', function ($group) {
    $group->get('/applications', [AdminController::class, 'getApplications']);
    $group->get('/analytics', [AdminController::class, 'getAnalytics']);
    $group->post('/create-user', [AdminController::class, 'createUser']);
    $group->post('/approve-interview', [AdminController::class, 'approveInterview']);
    $group->post('/issue-offer', [AdminController::class, 'issueOffer']);
})->add(new AuthMiddleware(['principal', 'finance', 'it', 'registrar']));

$app->group('/bank', function ($group) {
    $group->post('/confirm-payment', [BankController::class, 'confirmPayment']);
    $group->get('/analytics', [BankController::class, 'getAnalytics']);
})->add(new AuthMiddleware(['bank']));