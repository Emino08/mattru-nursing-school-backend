<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BackupController
{
    public function createBackup(Request $request, Response $response): Response
    {
        $backupFile = "/backups/backup-" . date('Y-m-d') . ".sql";
        exec("mysqldump -u root -p{$_ENV['DB_PASS']} mattru_nursing > $backupFile");
        $response->getBody()->write(json_encode(['message' => 'Backup created', 'file' => $backupFile]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}