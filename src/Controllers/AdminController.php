<?php
namespace App\Controllers;

use PDO;
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

    public function getPermissions($request, $response): \Psr\Http\Message\ResponseInterface
    {
        $data = [
            'roles' => [
                'principal' => ['approve_interview', 'issue_offer', 'view_analytics'],
                'finance'   => ['view_analytics'],
                'it'        => ['create_user', 'view_analytics'],
                'registrar' => ['view_applications']
            ]
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get PIN statistics
     * GET /admin/pin-statistics
     */
    public function getPinStatistics($request, $response)
    {
        try {
            $container = $this->getContainer();
            $db = $container->get('db');

            $stmt = $db->query('SELECT * FROM pin_statistics');
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'statistics' => $stats
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to retrieve PIN statistics'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Get PIN usage audit
     * GET /admin/pin-audit
     */
    public function getPinAudit($request, $response)
    {
        try {
            $container = $this->getContainer();
            $db = $container->get('db');

            $params = $request->getQueryParams();
            $limit = isset($params['limit']) ? (int)$params['limit'] : 100;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;

            $stmt = $db->prepare(
                'SELECT pua.*, p.transaction_reference, p.depositor_name, u.email 
             FROM pin_usage_audit pua 
             LEFT JOIN payments p ON pua.payment_id = p.id 
             LEFT JOIN users u ON pua.user_id = u.id 
             ORDER BY pua.created_at DESC 
             LIMIT ? OFFSET ?'
            );
            $stmt->execute([$limit, $offset]);
            $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'audit' => $audit,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to retrieve PIN audit'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Manually expire PIN
     * POST /admin/expire-pin
     */
    public function expirePin($request, $response)
    {
        $container = $this->getContainer();
        $db = $container->get('db');
        $data = $request->getParsedBody();

        if (empty($data['pin'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'PIN is required'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(422);
        }

        try {
            $stmt = $db->prepare('UPDATE payments SET payment_status = ? WHERE application_fee_pin = ?');
            $result = $stmt->execute(['expired', $data['pin']]);

            if ($stmt->rowCount() > 0) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'PIN expired successfully'
                ]));
            } else {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => 'PIN not found'
                ]));
            }
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to expire PIN'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Run cleanup expired PINs
     * POST /admin/cleanup-expired-pins
     */
    public function cleanupExpiredPins($request, $response)
    {
        try {
            $container = $this->getContainer();
            $db = $container->get('db');

            $stmt = $db->prepare('CALL CleanupExpiredPins()');
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Cleanup completed',
                'expired_count' => $result['expired_pins_count'] ?? 0
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Failed to run cleanup'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
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