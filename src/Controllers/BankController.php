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

    public function confirmPayment(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        if ($this->payment->confirm($data['application_id'], $data['pin'], $user->id)) {
            $this->auditLog->create($user->id, 'confirm_payment', ['application_id' => $data['application_id']]);
            $response->getBody()->write(json_encode(['message' => 'Payment confirmed']));
            return $response->withHeader('Content-Type', 'application/json');
        }
        return $this->errorResponse($response, 'Invalid PIN or application ID', 400);
    }

    public function getAnalytics(Request $request, Response $response): Response
    {
        $data = [
            'total_payments' => $this->payment->sum(),
            'pending_payments' => $this->payment->findByStatus('pending'),
            'confirmed_payments' => $this->payment->findByStatus('confirmed')
        ];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}