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

// ✅ LOG DI DEBUG
file_put_contents(__DIR__ . '/api_debug.log', date('H:i:s') . " | URI: {$_SERVER['REQUEST_URI']} | METHOD: {$_SERVER['REQUEST_METHOD']}\n", FILE_APPEND);

// ✅ CONTROLLERS
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/config/database.php';

// ✅ PERCORSO RICHIESTO
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace(['/los-cerignola-api', '/api'], '', $path);
$path = trim($path, '/');
$route_parts = explode('/', $path);

// ✅ ROUTING
switch ($route_parts[0]) {
    /* -----------------------------------------------------------
     * 🛍️ PRODOTTI
     * ----------------------------------------------------------- */
    case 'products':
        $controller = new ProductController();
        if (isset($route_parts[1]) && is_numeric($route_parts[1])) {
            $controller->getProductById($route_parts[1]);
        } else {
            $controller->getAllProducts();
        }
        break;

    /* -----------------------------------------------------------
     * 🔑 LOGIN
     * ----------------------------------------------------------- */
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

    /* -----------------------------------------------------------
     * 🧾 ORDINI
     * ----------------------------------------------------------- */
    case 'orders':
        $controller = new OrderController();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getOrders();

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    /* -----------------------------------------------------------
     * ⭐ LOYALTY POINTS
     * ----------------------------------------------------------- */
    case 'loyalty':
        $db = new Database();
        $pdo = $db->connect();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // GET /api/loyalty?user_id=1
            $userId = $_GET['user_id'] ?? null;

            if (!$userId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID utente mancante']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT loyalty_points FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $points = $stmt->fetchColumn();

            if ($points !== false) {
                echo json_encode(['user_id' => $userId, 'loyalty_points' => (int)$points]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Utente non trovato']);
            }

        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // POST /api/loyalty?action=update
            $data = json_decode(file_get_contents('php://input'), true);
            $userId = $data['user_id'] ?? null;
            $action = $data['action'] ?? null; // "add" | "redeem"
            $points = (int)($data['points'] ?? 0);

            if (!$userId || !$action || $points <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Dati insufficienti']);
                exit;
            }

            if ($action === 'add') {
                $stmt = $pdo->prepare("UPDATE users SET loyalty_points = loyalty_points + ? WHERE id = ?");
                $stmt->execute([$points, $userId]);
                echo json_encode(['message' => "✅ Aggiunti $points punti all’utente #$userId"]);

            } elseif ($action === 'redeem') {
                $stmt = $pdo->prepare("UPDATE users SET loyalty_points = GREATEST(loyalty_points - ?, 0) WHERE id = ?");
                $stmt->execute([$points, $userId]);
                echo json_encode(['message' => "🎁 Riscattati $points punti per l’utente #$userId"]);

            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Azione non valida']);
            }

        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Metodo non consentito per loyalty.']);
        }
        break;

    /* -----------------------------------------------------------
     * ❌ DEFAULT
     * ----------------------------------------------------------- */
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint non trovato']);
        break;
}
