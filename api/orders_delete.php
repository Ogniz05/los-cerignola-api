<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);
$order_id = $data['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID ordine mancante']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Ordine eliminato']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Ordine non trovato']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore server', 'message' => $e->getMessage()]);
}
