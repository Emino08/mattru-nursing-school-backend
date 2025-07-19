<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\Notification;

class NotificationController
{
    private $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function getNotifications(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $notifications = $this->notification->findByUser($user->id);
        $response->getBody()->write(json_encode($notifications));
        return $response->withHeader('Content-Type', 'application/json');
    }
}