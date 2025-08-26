<?php
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
        $this->mailer->SMTPSecure = $_ENV['SMTP_SECURE'] ?? 'tls'; // Default to 'tls' if not set
        $this->mailer->Username = $_ENV['SMTP_USER'];
        $this->mailer->Password = $_ENV['SMTP_PASS'];
    }

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
//        // Check if user already exists
//        $existingUser = $this->user->findByEmail($data['email']);
//        if ($existingUser) {
//            return $this->errorResponse($response, 'User with this email already exists', 409);
//        }
//
//        $userId = $this->user->create($data['email'], $data['password'], 'applicant');
//        $this->userProfile->create($userId, $data['profile']);
//        $user = $this->user->findByEmail($data['email']);
//
//        $this->mailer->setFrom('admission@msn.edu.sl', 'Mattru School of Nursing');
//        $this->mailer->addAddress($data['email']);
//        $this->mailer->Subject = 'Verify Your Email';
//        $this->mailer->Body = "Click to verify: https://backend.msn.edu.sl/verify?token={$user['verification_token']}";
//        $this->mailer->send();
//
//        $response->getBody()->write(json_encode(['message' => 'User registered, please verify email']));
//        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
//    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        if (empty($data['email']) || empty($data['password']) || empty($data['captcha']) || empty($data['profile'])) {
            return $this->errorResponse($response, 'Missing required fields', 400);
        }

        if ($data['captcha'] !== 'valid_captcha') {
            return $this->errorResponse($response, 'Invalid CAPTCHA', 400);
        }

        // Check if user already exists
        $existingUser = $this->user->findByEmail($data['email']);
        if ($existingUser) {
            return $this->errorResponse($response, 'User with this email already exists', 409);
        }

        $userId = $this->user->create($data['email'], $data['password'], 'applicant');
        $this->userProfile->create($userId, $data['profile']);
        $user = $this->user->findByEmail($data['email']);

        $verificationUrl = "https://backend.msn.edu.sl/verify?token={$user['verification_token']}";
        $currentYear = date('Y');
        $recipientName = $data['profile']['first_name'] ?? 'Dear Applicant';

        $emailBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Email Verification - Mattru School of Nursing</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f4f7fa;
                color: #333333;
                line-height: 1.6;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
            }
            
            .logo {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                font-weight: bold;
                border: 3px solid rgba(255, 255, 255, 0.3);
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            
            .content {
                padding: 40px 30px;
                text-align: center;
            }
            
            .welcome-message {
                font-size: 18px;
                color: #333;
                margin-bottom: 20px;
            }
            
            .description {
                font-size: 16px;
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            
            .verification-button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                padding: 15px 40px;
                border-radius: 50px;
                font-size: 16px;
                font-weight: 600;
                margin: 20px 0;
                transition: transform 0.2s ease;
            }
            
            .verification-button:hover {
                transform: translateY(-2px);
            }
            
            .security-notice {
                background: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin: 30px 0;
                text-align: left;
                border-radius: 0 8px 8px 0;
            }
            
            .security-notice h3 {
                margin: 0 0 10px 0;
                color: #333;
                font-size: 16px;
            }
            
            .security-notice p {
                margin: 0;
                font-size: 14px;
                color: #666;
            }
            
            .footer {
                background: #f8f9fa;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #e9ecef;
            }
            
            .footer p {
                margin: 5px 0;
                font-size: 14px;
                color: #666;
            }
            
            .contact-info {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e9ecef;
            }
            
            .contact-info a {
                color: #667eea;
                text-decoration: none;
            }
            
            @media (max-width: 600px) {
                .email-container {
                    margin: 0;
                    border-radius: 0;
                }
                
                .header, .content, .footer {
                    padding: 20px;
                }
                
                .header h1 {
                    font-size: 24px;
                }
                
                .verification-button {
                    padding: 12px 30px;
                    font-size: 14px;
                }
            }
        </style>
    </head>
    <body>
        <div class='email-container'>
            <div class='header'>
                <div class='logo'>MSN</div>
                <h1>Welcome to Mattru School of Nursing!</h1>
                <p>Verify your email to complete registration</p>
            </div>
            
            <div class='content'>
                <p class='welcome-message'>Hello $recipientName,</p>
                
                <p class='description'>
                    Thank you for registering with Mattru School of Nursing! We're excited to have you join our community of future healthcare professionals.
                </p>
                
                <p class='description'>
                    To complete your registration and secure your account, please verify your email address by clicking the button below:
                </p>
                
                <a href='$verificationUrl' class='verification-button'>Verify My Email</a>
                
                <div class='security-notice'>
                    <h3>🔒 Security Notice</h3>
                    <p>This verification link is valid for 24 hours and can only be used once. If you didn't create an account with us, please ignore this email.</p>
                </div>
                
                <p style='font-size: 14px; color: #666; margin-top: 30px;'>
                    If the button doesn't work, copy and paste this link into your browser:<br>
                    <a href='$verificationUrl' style='color: #667eea; word-break: break-all;'>$verificationUrl</a>
                </p>
            </div>
            
            <div class='footer'>
                <p><strong>Mattru School of Nursing</strong></p>
                <p>Building the future of healthcare in Sierra Leone</p>
                
                <div class='contact-info'>
                    <p>Need help? Contact us:</p>
                    <p>Email: <a href='mailto:admission@msn.edu.sl'>admission@msn.edu.sl</a></p>
                    <p>Website: <a href='https://admission.msn.edu.sl'>admission.msn.edu.sl</a></p>
                </div>
                
                <p style='margin-top: 20px; font-size: 12px; color: #999;'>
                    &copy; $currentYear Mattru School of Nursing. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>";

        $this->mailer->setFrom('admission@msn.edu.sl', 'Mattru School of Nursing');
        $this->mailer->addAddress($data['email']);
        $this->mailer->Subject = '🎓 Welcome to Mattru School of Nursing - Verify Your Email';
        $this->mailer->isHTML(true);
        $this->mailer->Body = $emailBody;
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

//    public function verify(Request $request, Response $response): Response
//    {
//        $token = $request->getQueryParams()['token'] ?? '';
//        if ($this->user->verify($token)) {
//            $response->getBody()->write(json_encode(['message' => 'Email verified']));
//            return $response->withHeader('Content-Type', 'application/json');
//        }
//        return $this->errorResponse($response, 'Invalid verification token', 400);
//    }

    public function verify(Request $request, Response $response): Response
    {
        $token = $request->getQueryParams()['token'] ?? '';
        $verificationResult = $this->user->verify($token);

        // Determine the verification status
        $status = 'error';
        $title = 'Verification Failed';
        $message = 'Invalid or expired verification token.';
        $icon = '❌';

        if ($verificationResult === true) {
            $status = 'success';
            $title = 'Email Verified Successfully!';
            $message = 'Your email has been verified. You can now log in to your account.';
            $icon = '✅';
        }

        $currentYear = date('Y');

        $html = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Email Verification - Mattru School of Nursing</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                width: 100%;
                text-align: center;
                overflow: hidden;
                animation: slideIn 0.6s ease-out;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .header {
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white;
                padding: 30px 20px;
                position: relative;
            }

            .header.error {
                background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            }

            .header.info {
                background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            }

            .icon {
                font-size: 4rem;
                margin-bottom: 10px;
                display: block;
            }

            .title {
                font-size: 1.8rem;
                font-weight: 600;
                margin-bottom: 5px;
            }

            .content {
                padding: 40px 30px;
            }

            .message {
                font-size: 1.1rem;
                color: #666;
                line-height: 1.6;
                margin-bottom: 30px;
            }

            .logo {
                width: 80px;
                height: 80px;
                background: #f8f9fa;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: #333;
                border: 3px solid #e9ecef;
            }

            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 25px;
                font-weight: 500;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                margin: 0 10px;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }

            .btn.secondary {
                background: #6c757d;
            }

            .footer {
                background: #f8f9fa;
                padding: 20px;
                font-size: 0.9rem;
                color: #666;
                border-top: 1px solid #e9ecef;
            }

            @media (max-width: 480px) {
                .container {
                    margin: 10px;
                    border-radius: 15px;
                }

                .title {
                    font-size: 1.5rem;
                }

                .content {
                    padding: 30px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header $status'>
                <span class='icon'>$icon</span>
                <h1 class='title'>$title</h1>
            </div>

            <div class='content'>
                <div class='logo'>MSN</div>
                <p class='message'>$message</p>

                <div class='actions'>
                    <a href='https://admission.msn.edu.sl/login' class='btn'>Go to Login</a>
                    <a href='https://admission.msn.edu.sl' class='btn secondary'>Back to Home</a>
                </div>
            </div>

            <div class='footer'>
                <p>&copy; $currentYear Mattru School of Nursing. All rights reserved.</p>
                <p>If you need assistance, please contact our support team.</p>
            </div>
        </div>
    </body>
    </html>";

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
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

        $this->mailer->setFrom('no-reply@msn.edu.sl', 'Mattru School of Nursing');
        $this->mailer->addAddress($email);
        $this->mailer->Subject = 'Password Reset Request';
        $this->mailer->Body = "Click to reset your password: https://backend.msn.edu.sl/reset-password?token=$resetToken";
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