<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../vendor/autoload.php';
// Enable PHP error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Create Container and App
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();


// Add other middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

// Set base path
//$app->setBasePath('/api');

// Load database settings
$container->set('settings', require __DIR__ . '/../config/database.php');

// Container definitions (PDO, models, controllers remain the same)
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

// Add CORS middleware FIRST (before other middleware)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Handle preflight OPTIONS requests for all routes
$app->options('/{routes:.*}', function (Request $request, Response $response) {
    return $response;
});

// Include routes
require __DIR__ . '/../src/Routes/api.php';

// Error middleware should be added last
$app->addErrorMiddleware(true, true, true);

// Run the app
$app->run();