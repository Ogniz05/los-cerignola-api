<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ✅ LOG DI DEBUG (crea /api_debug.log)
file_put_contents(__DIR__ . '/api_debug.log', date('H:i:s') . " | URI: {$_SERVER['REQUEST_URI']} | METHOD: {$_SERVER['REQUEST_METHOD']}\n", FILE_APPEND);

// ✅ CONTROLLERS
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/OrderController.php';

// ✅ PERCORSO RICHIESTO
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// rimuove prefisso (indipendentemente da come Apache lo serve)
$path = str_replace(['/los-cerignola-api', '/api'], '', $path);
$path = trim($path, '/');
$route_parts = explode('/', $path);

// ✅ ROUTING
switch ($route_parts[0]) {
    case 'products':
        $controller = new ProductController();
        if (isset($route_parts[1]) && is_numeric($route_parts[1])) {
            $controller->getProductById($route_parts[1]);
        } else {
            $controller->getAllProducts();
        }
        break;

    case 'login':
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $controller->login($data['email'] ?? '', $data['password'] ?? '');
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non consentito.']);
        }
        break;

    case 'orders':
        $controller = new OrderController();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getOrders();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // gestisce anche POST?action=delete o POST?action=pay
            $action = $_GET['action'] ?? null;
            if ($action === 'delete') {
                $controller->deleteOrder();
            } elseif ($action === 'pay') {
                $data = json_decode(file_get_contents('php://input'), true);
                $controller->payOrder($data['order_id'] ?? null);
            } else {
                $controller->createOrder();
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH' && isset($route_parts[1]) && isset($_GET['status'])) {
            $controller->updateOrderStatus($route_parts[1], $_GET['status']);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $controller->deleteOrder();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non consentito per orders.']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint non trovato']);
        break;
}
