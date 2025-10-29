<?php
require_once __DIR__ . '/../controllers/OrderController.php';
file_put_contents(__DIR__ . '/debug.log', date('H:i:s') . " | Metodo: {$_SERVER['REQUEST_METHOD']} | Query: " . json_encode($_GET) . "\n", FILE_APPEND);


$controller = new OrderController();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ✅ ROUTING */
switch (true) {
    case $method === 'GET':
        $controller->getOrders();
        break;

    case $method === 'POST' && $action === 'create':
        $controller->createOrder();
        break;

    case $method === 'POST' && $action === 'delete': // 👈 fallback per simulare DELETE
        $controller->deleteOrder();
        break;

    case $method === 'PATCH':
        $controller->updateOrderStatus($_GET['id'] ?? null, $_GET['status'] ?? null);
        break;

    case $method === 'DELETE':
        $controller->deleteOrder();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint non trovato']);
        break;
}
