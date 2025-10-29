<?php
require_once __DIR__ . '/../controllers/LoyaltyController.php';
require_once __DIR__ . '/../utils/jwt.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Token non valido']);
    exit;
}

$controller = new LoyaltyController();
$userId = $user['user_id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $controller->getUserPoints($userId);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        $action = $data['action'] ?? null;
        $points = $data['points'] ?? 0;

        if ($action === 'add') $controller->addPoints($userId, $points);
        elseif ($action === 'redeem') $controller->redeemPoints($userId, $points);
        else {
            http_response_code(400);
            echo json_encode(['error' => 'Azione non valida']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metodo non consentito']);
        break;
}
