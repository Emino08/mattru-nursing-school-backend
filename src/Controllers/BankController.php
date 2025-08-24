<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Payment;
use App\Models\AuditLog;

class BankController
{
    private $payment;
    private $auditLog;

    public function __construct(Payment $payment, AuditLog $auditLog)
    {
        $this->payment = $payment;
        $this->auditLog = $auditLog;
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

    /**
     * Generate 16-digit application PIN
     */
    private function generateApplicationPin(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        return implode('-', $segments);
    }

    /**
     * Create payment with application PIN
     */
    public function createPayment(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = json_decode((string)$request->getBody(), true) ?? [];

        // Validate required fields
        $required = ['amount', 'depositor_name', 'bank_confirmation_pin', 'transaction_reference'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse($response, "Missing required field: $field", 422);
            }
        }

        try {
            // Generate application PIN if not provided
            if (empty($data['application_fee_pin'])) {
                $data['application_fee_pin'] = $this->generateApplicationPin();
            }

            // Set payment method if not provided
            if (empty($data['payment_method'])) {
                $data['payment_method'] = 'bank';
            }

            // Create payment record
            $payment = $this->payment->createWithApplicationPin($userId, $data);

            // Log the action
            $this->auditLog->create($userId, 'create_payment_with_pin', [
                'payment_id' => $payment['id'],
                'amount' => $data['amount'],
                'depositor_name' => $data['depositor_name']
            ]);

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Payment record created successfully',
                'payment' => $payment
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (\Exception $e) {
            error_log("Payment creation error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to create payment record', 500);
        }
    }

    /**
     * Get payments by user ID
     */
    public function getPaymentsByUserId(Request $request, Response $response): Response
    {
        try {
            // Use the existing getUserId method to extract user ID
            $userId = $this->getUserId($request);

            if ($userId === 0) {
                return $this->errorResponse($response, 'Invalid or missing user ID', 422);
            }

            // Get payments for this user
            $payments = $this->payment->getPaymentsByUserId($userId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'user_id' => $userId,
                'payments' => $payments,
                'count' => count($payments)
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Get payments by user ID error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to retrieve user payments', 500);
        }
    }

    /**
     * Get all payments for bank portal
     */
    public function getPayments(Request $request, Response $response): Response
    {
        try {
            $payments = $this->payment->getAllPayments();

            $response->getBody()->write(json_encode([
                'success' => true,
                'payments' => $payments
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Get payments error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to retrieve payments', 500);
        }
    }

    /**
     * Confirm payment (existing functionality)
     */
    public function confirmPayment(Request $request, Response $response): Response
    {
        $userId = $this->getUserId($request);
        $data = json_decode((string)$request->getBody(), true) ?? [];

        if (empty($data['application_id']) || empty($data['pin'])) {
            return $this->errorResponse($response, 'Missing application_id or pin', 422);
        }

        try {
            if ($this->payment->confirm($data['application_id'], $data['pin'], $userId)) {
                $this->auditLog->create($userId, 'confirm_payment', [
                    'application_id' => $data['application_id']
                ]);

                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Payment confirmed successfully'
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                return $this->errorResponse($response, 'Invalid PIN or application ID', 400);
            }
        } catch (\Exception $e) {
            error_log("Payment confirmation error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to confirm payment', 500);
        }
    }

    /**
     * Get payment analytics
     */
    public function getAnalytics(Request $request, Response $response): Response
    {
        try {
            $stats = $this->payment->getStatistics();

            $data = [
                'total_payments' => $stats['confirmed']['total'],
                'total_count' => $stats['confirmed']['count'],
                'pending_payments' => $this->payment->findByStatus('pending'),
                'confirmed_payments' => $this->payment->findByStatus('confirmed'),
                'statistics' => $stats
            ];

            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            return $this->errorResponse($response, 'Failed to retrieve analytics', 500);
        }
    }

    /**
     * Generate new application PIN (utility endpoint)
     */
    public function generatePin(Request $request, Response $response): Response
    {
        $pin = $this->generateApplicationPin();

        $response->getBody()->write(json_encode([
            'success' => true,
            'pin' => $pin
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}