<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\AuditLog;

class AdminController
{
    private $user;
    private $application;
    private $payment;
    private $notification;
    private $auditLog;

    public function __construct(User $user, Application $application, Payment $payment, Notification $notification, AuditLog $auditLog)
    {
        $this->user = $user;
        $this->application = $application;
        $this->payment = $payment;
        $this->notification = $notification;
        $this->auditLog = $auditLog;
    }

    public function getApplications(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!in_array('application_management', $user->permissions)) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }
        $apps = $this->application->findAll();
        $response->getBody()->write(json_encode($apps));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getAnalytics(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!in_array('analytics_dashboard', $user->permissions)) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }
        $data = [
            'total_applications' => $this->application->count(),
            'total_payments' => $this->payment->sum(),
            'pending_interviews' => $this->application->countByStatus('interview_scheduled')
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createUser(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if ($user->role !== 'principal') {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }
        $data = $request->getParsedBody();
        $permissions = $data['permissions'] ?? [
            'application_management',
            'analytics_dashboard'
        ];
        $userId = $this->user->create($data['email'], $data['password'], $data['role'], $permissions);
        $this->auditLog->create($user->id, 'create_user', ['user_id' => $userId]);
        $response->getBody()->write(json_encode(['message' => 'User created']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function approveInterview(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!in_array('interview_scheduling', $user->permissions)) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }
        $data = $request->getParsedBody();
        $this->application->updateStatus($data['id'], 'interview_scheduled');
        $this->notification->create($data['user_id'], 'interview', 'Interview scheduled for your application');
        $this->auditLog->create($user->id, 'approve_interview', ['application_id' => $data['id']]);
        $response->getBody()->write(json_encode(['message' => 'Interview approved']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function issueOffer(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        if (!in_array('offer_letter_management', $user->permissions)) {
            return $this->errorResponse($response, 'Unauthorized', 403);
        }
        $data = $request->getParsedBody();
        $this->application->updateStatus($data['id'], 'offer_issued');
        $this->notification->create($data['user_id'], 'offer_letter', 'Offer letter issued for your application');
        $this->auditLog->create($user->id, 'issue_offer', ['application_id' => $data['id']]);
        $response->getBody()->write(json_encode(['message' => 'Offer letter issued']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}