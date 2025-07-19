<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\AuditLog;

class ApplicantController
{
    private $application;
    private $payment;
    private $notification;
    private $auditLog;

    public function __construct(Application $application, Payment $payment, Notification $notification, AuditLog $auditLog)
    {
        $this->application = $application;
        $this->payment = $payment;
        $this->notification = $notification;
        $this->auditLog = $auditLog;
    }

    public function createApplication(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        $files = $request->getUploadedFiles();

        $appId = $this->application->create($user->id, $data['program_type'], $data['form_data']);
        foreach ($files['documents'] ?? [] as $file) {
            if ($file->getSize() > 5 * 1024 * 1024) {
                return $this->errorResponse($response, 'File too large', 400);
            }
            $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
            if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
                return $this->errorResponse($response, 'Invalid file type', 400);
            }
            $path = $_ENV['UPLOAD_DIR'] . '/' . uniqid() . '.' . $ext;
            $file->moveTo($path);
            $this->application->addDocument($appId, $path, $file->getClientMediaType());
        }

        $this->auditLog->create($user->id, 'create_application', ['application_id' => $appId]);
        $response->getBody()->write(json_encode(['application_id' => $appId]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function submitApplication(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        $this->application->submit($data['application_id']);
        $this->notification->create($user->id, 'application_submitted', 'Application submitted successfully');
        $this->auditLog->create($user->id, 'submit_application', ['application_id' => $data['application_id']]);
        $response->getBody()->write(json_encode(['message' => 'Application submitted']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getStatus(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $apps = $this->application->findByUser($user->id);
        $response->getBody()->write(json_encode($apps));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function initiatePayment(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        $pin = 'PIN' . rand(1000, 9999);
        $paymentId = $this->payment->create($data['application_id'], $data['amount'], $pin);
        $this->notification->create($user->id, 'payment_initiated', "Payment initiated, PIN: $pin");
        $this->auditLog->create($user->id, 'initiate_payment', ['payment_id' => $paymentId]);
        $response->getBody()->write(json_encode(['pin' => $pin]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}