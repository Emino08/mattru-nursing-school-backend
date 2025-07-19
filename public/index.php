<?php
//use Psr\Http\Message\ResponseInterface as Response;
//use Psr\Http\Message\ServerRequestInterface as Request;
//use Slim\Factory\AppFactory;
//use DI\Container;
//
//require __DIR__ . '/../vendor/autoload.php';
//
//$container = new Container();
//AppFactory::setContainer($container);
//$app = AppFactory::create();
//
//$container->set('settings', require __DIR__ . '/../config/database.php');
//require __DIR__ . '/../src/Routes/api.php';
//
//$app->addErrorMiddleware(true, true, true);
//$app->run();


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Create Container and App
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Set base path if needed
 $app->setBasePath('/api');

// Load database settings
$container->set('settings', require __DIR__ . '/../config/database.php');

// Set up container definitions for models and controllers
$container->set(PDO::class, function () use ($container) {
    $settings = $container->get('settings')['db'];
    $dsn = "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['name']};charset=utf8mb4";
    return new PDO($dsn, $settings['user'], $settings['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
});

$container->set(App\Models\User::class, function ($container) {
    return new App\Models\User($container->get(PDO::class));
});

$container->set(App\Models\UserProfile::class, function ($container) {
    return new App\Models\UserProfile($container->get(PDO::class));
});

$container->set(App\Controllers\AuthController::class, function ($container) {
    return new App\Controllers\AuthController(
        $container->get(App\Models\User::class),
        $container->get(App\Models\UserProfile::class)
    );
});
// Include routes - now $app is defined before including the routes file
require __DIR__ . '/../src/Routes/api.php';

// Error middleware should be added last
$app->addErrorMiddleware(true, true, true);

// Run the app
$app->run();