<?php
//namespace App\Controllers;
//
//use Psr\Http\Message\ResponseInterface as Response;
//use Psr\Http\Message\ServerRequestInterface as Request;
//use App\Models\User;
//use App\Models\UserProfile;
//use Firebase\JWT\JWT;
//use PHPMailer\PHPMailer\PHPMailer;
//
//class AuthController
//{
//    private $user;
//    private $userProfile;
//    private $mailer;
//
//    public function __construct(User $user, UserProfile $userProfile)
//    {
//        $this->user = $user;
//        $this->userProfile = $userProfile;
//        $this->mailer = new PHPMailer(true);
//        $this->mailer->isSMTP();
//        $this->mailer->Host = $_ENV['SMTP_HOST'];
//        $this->mailer->Port = $_ENV['SMTP_PORT'];
//        $this->mailer->SMTPAuth = true;
//        $this->mailer->Username = $_ENV['SMTP_USER'];
//        $this->mailer->Password = $_ENV['SMTP_PASS'];
//    }
//
//    public function register(Request $request, Response $response): Response
//    {
//        $data = $request->getParsedBody();
//        if (empty($data['email']) || empty($data['password']) || empty($data['captcha']) || empty($data['profile'])) {
//            return $this->errorResponse($response, 'Missing required fields', 400);
//        }
//
//        if ($data['captcha'] !== 'valid_captcha') {
//            return $this->errorResponse($response, 'Invalid CAPTCHA', 400);
//        }
//
//        $userId = $this->user->create($data['email'], $data['password'], 'applicant');
//        $this->userProfile->create($userId, $data['profile']);
//        $user = $this->user->findByEmail($data['email']);
//
//        $this->mailer->setFrom('no-reply@mattru.edu', 'Mattru Nursing');
//        $this->mailer->addAddress($data['email']);
//        $this->mailer->Subject = 'Verify Your Email';
//        $this->mailer->Body = "Click to verify: http://localhost:5173/verify?token={$user['verification_token']}";
//        $this->mailer->send();
//
//        $response->getBody()->write(json_encode(['message' => 'User registered, please verify email']));
//        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
//    }
//
//    public function login(Request $request, Response $response): Response
//    {
//        $data = $request->getParsedBody();
//        $user = $this->user->findByEmail($data['email'] ?? '');
//        if (!$user || !password_verify($data['password'] ?? '', $user['password']) || $user['status'] !== 'active') {
//            return $this->errorResponse($response, 'Invalid credentials or unverified email', 401);
//        }
//
//        $permissions = $this->user->getPermissions($user['id']);
//        $token = JWT::encode([
//            'id' => $user['id'],
//            'email' => $user['email'],
//            'role' => $user['role'],
//            'permissions' => $permissions,
//            'exp' => time() + 3600
//        ], $_ENV['JWT_SECRET'], 'HS256');
//
//        $response->getBody()->write(json_encode(['token' => $token]));
//        return $response->withHeader('Content-Type', 'application/json');
//    }
//
//    public function verify(Request $request, Response $response): Response
//    {
//        $token = $request->getQueryParams()['token'] ?? '';
//        if ($this->user->verify($token)) {
//            $response->getBody()->write(json_encode(['message' => 'Email verified']));
//            return $response->withHeader('Content-Type', 'application/json');
//        }
//        return $this->errorResponse($response, 'Invalid verification token', 400);
//    }
//
//    private function errorResponse(Response $response, string $message, int $status): Response
//    {
//        $response->getBody()->write(json_encode(['error' => $message]));
//        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
//    }
//}


namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\User;
use App\Models\UserProfile;
use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;

class AuthController
{
    private $user;
    private $userProfile;
    private $mailer;

    public function __construct(User $user, UserProfile $userProfile)
    {
        $this->user = $user;
        $this->userProfile = $userProfile;
        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['SMTP_HOST'];
        $this->mailer->Port = $_ENV['SMTP_PORT'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['SMTP_USER'];
        $this->mailer->Password = $_ENV['SMTP_PASS'];
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (empty($data['email']) || empty($data['password']) || empty($data['captcha']) || empty($data['profile'])) {
            return $this->errorResponse($response, 'Missing required fields', 400);
        }

        if ($data['captcha'] !== 'valid_captcha') {
            return $this->errorResponse($response, 'Invalid CAPTCHA', 400);
        }

        $userId = $this->user->create($data['email'], $data['password'], 'applicant');
        $this->userProfile->create($userId, $data['profile']);
        $user = $this->user->findByEmail($data['email']);

        $this->mailer->setFrom('no-reply@mattru.edu', 'Mattru Nursing');
        $this->mailer->addAddress($data['email']);
        $this->mailer->Subject = 'Verify Your Email';
        $this->mailer->Body = "Click to verify: http://localhost:5173/verify?token={$user['verification_token']}";
        $this->mailer->send();

        $response->getBody()->write(json_encode(['message' => 'User registered, please verify email']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $this->user->findByEmail($data['email'] ?? '');
        if (!$user || !password_verify($data['password'] ?? '', $user['password']) || $user['status'] !== 'active') {
            return $this->errorResponse($response, 'Invalid credentials or unverified email', 401);
        }

        $permissions = $this->user->getPermissions($user['id']);
        $token = JWT::encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'permissions' => $permissions,
            'exp' => time() + 3600
        ], $_ENV['JWT_SECRET'], 'HS256');

        $response->getBody()->write(json_encode(['token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function verify(Request $request, Response $response): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';
        if ($this->user->verify($token)) {
            $response->getBody()->write(json_encode(['message' => 'Email verified']));
            return $response->withHeader('Content-Type', 'application/json');
        }
        return $this->errorResponse($response, 'Invalid verification token', 400);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';
        $user = $this->user->findByEmail($email);
        if (!$user) {
            return $this->errorResponse($response, 'User not found', 404);
        }

        $resetToken = bin2hex(random_bytes(32));
        $this->user->setPasswordResetToken($user['id'], $resetToken);

        $this->mailer->setFrom('no-reply@mattru.edu', 'Mattru Nursing');
        $this->mailer->addAddress($email);
        $this->mailer->Subject = 'Password Reset Request';
        $this->mailer->Body = "Click to reset your password: http://localhost:5173/reset-password?token=$resetToken";
        $this->mailer->send();

        $response->getBody()->write(json_encode(['message' => 'Password reset email sent']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $token = $data['token'] ?? '';
        $newPassword = $data['password'] ?? '';

        if (empty($token) || empty($newPassword)) {
            return $this->errorResponse($response, 'Missing required fields', 400);
        }

        $user = $this->user->findByResetToken($token);
        if (!$user) {
            return $this->errorResponse($response, 'Invalid or expired reset token', 400);
        }

        $this->user->updatePassword($user['id'], $newPassword);
        $this->user->clearPasswordResetToken($user['id']);

        $response->getBody()->write(json_encode(['message' => 'Password reset successfully']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}