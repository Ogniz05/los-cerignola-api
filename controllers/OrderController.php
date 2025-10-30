<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ProductController.php'; // ✅ percorso corretto

class OrderController
{
    private $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /* ------------------------------------------------------- */
    /* 📦 CREA UN NUOVO ORDINE (guest o utente loggato)         */
    /* ------------------------------------------------------- */
    public function createOrder($userId = null)
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $items = $data['items'] ?? [];
        $total = $data['total'] ?? 0;

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nessun prodotto nel carrello']);
            return;
        }

        try {
            $this->pdo->beginTransaction();

            // 🔹 Determina la "source"
            $source = $userId ? 'utente' : 'guest';

            // 🔹 Crea l'ordine
            if ($userId) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (user_id, total_amount, status, source, created_at)
                    VALUES (?, ?, 'Nuovo', ?, NOW())
                ");
                $stmt->execute([$userId, $total, $source]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (total_amount, status, source, created_at)
                    VALUES (?, 'Nuovo', ?, NOW())
                ");
                $stmt->execute([$total, $source]);
            }

            $orderId = $this->pdo->lastInsertId();

            // 🔹 Inserisci i prodotti dell’ordine
            $itemStmt = $this->pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['id'],
                    $item['quantity'],
                    $item['price']
                ]);
            }

            $this->pdo->commit();

            http_response_code(201);
            echo json_encode([
                'message' => 'Ordine creato con successo',
                'order_id' => $orderId
            ]);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Errore creazione ordine: ' . $e->getMessage()]);
        }
    }

    /* ------------------------------------------------------- */
    /* 🍕 RECUPERA TUTTI GLI ORDINI (solo staff o admin)       */
    /* ------------------------------------------------------- */
    public function getOrders()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    o.id AS order_id,
                    o.user_id,
                    u.name AS user_name,
                    o.total_amount,
                    o.status,
                    o.source,
                    o.created_at,
                    o.updated_at
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                ORDER BY o.created_at DESC
            ");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $itemStmt = $this->pdo->prepare("
                    SELECT 
                        oi.product_id, 
                        p.name, 
                        oi.quantity, 
                        oi.price
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $itemStmt->execute([$order['order_id']]);
                $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            http_response_code(200);
            echo json_encode($orders);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore durante il recupero degli ordini: ' . $e->getMessage()]);
        }
    }

    /* ------------------------------------------------------- */
    /* 🔄 AGGIORNA STATO ORDINE (solo staff)                   */
    /* ------------------------------------------------------- */
    public function updateOrderStatus($orderId, $status)
    {
        if (!$orderId || !$status) {
            http_response_code(400);
            echo json_encode(['error' => 'ID ordine o stato mancante']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $orderId]);

            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(['message' => 'Stato ordine aggiornato', 'new_status' => $status]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Ordine non trovato']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore aggiornamento stato: ' . $e->getMessage()]);
        }
    }

    /* ------------------------------------------------------- */
    /* 💳 ELIMINA ORDINE (simula pagamento completato)         */
    /* ------------------------------------------------------- */
    public function deleteOrder()
    {
        $data = json_decode(file_get_contents("php://input"), true);
        $orderId = $data['order_id'] ?? null;

        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID ordine mancante']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);

            if ($stmt->rowCount() > 0) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Ordine eliminato con successo']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Ordine non trovato']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore durante l\'eliminazione: ' . $e->getMessage()]);
        }
    }

/* ------------------------------------------------------- */
/* 💰 SIMULA PAGAMENTO ORDINE + ASSEGNA PUNTI              */
/* ------------------------------------------------------- */
public function payOrder($orderId)
{
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID ordine mancante']);
        return;
    }

    try {
        $this->pdo->beginTransaction();

        // 1️⃣ Recupera dati ordine
        $stmt = $this->pdo->prepare("SELECT user_id, total_amount FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Ordine non trovato']);
            return;
        }

        $userId = $order['user_id'];
        $total = (float)$order['total_amount'];

        // 2️⃣ Se l'ordine è associato a un utente, assegna punti fedeltà
        if (!empty($userId)) {
            $points = (int)floor($total); // 1 punto per ogni euro speso

            $updatePoints = $this->pdo->prepare("
                UPDATE users 
                SET loyalty_points = loyalty_points + ? 
                WHERE id = ?
            ");
            $updatePoints->execute([$points, $userId]);
        }

        // 3️⃣ Elimina l’ordine (pagato)
        $delete = $this->pdo->prepare("DELETE FROM orders WHERE id = ?");
        $delete->execute([$orderId]);

        $this->pdo->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Ordine pagato e rimosso',
            'earned_points' => isset($points) ? $points : 0
        ]);

    } catch (PDOException $e) {
        $this->pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante il pagamento: ' . $e->getMessage()]);
    }
}

}
