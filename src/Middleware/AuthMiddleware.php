<?php
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!$authHeader) {
            $response = $handler->handle($request);
            return $this->errorResponse($response, 'Authorization header missing', 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);
        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));

            // Check if user has required role
            if (!empty($this->allowedRoles) && !in_array($decoded->role, $this->allowedRoles)) {
                $response = $handler->handle($request);
                return $this->errorResponse($response, 'Unauthorized role', 403);
            }

            // Add user data to request attributes
            $request = $request->withAttribute('user', $decoded);

            // Continue with the request
            return $handler->handle($request);
        } catch (\Exception $e) {
            $response = $handler->handle($request);
            return $this->errorResponse($response, 'Invalid token: ' . $e->getMessage(), 401);
        }
    }

    private function errorResponse(Response $response, string $message, int $status): Response
    {
        $payload = json_encode(['error' => $message]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}