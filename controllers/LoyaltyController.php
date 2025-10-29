<?php
require_once __DIR__ . '/../config/database.php';

class LoyaltyController
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getDbConnection();
    }

    // 🟢 Ottieni i punti dell'utente
    public function getUserPoints($userId)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT points FROM loyalty_points WHERE user_id = ?");
            $stmt->execute([$userId]);
            $points = $stmt->fetchColumn();

            if ($points === false) {
                // se non esiste ancora, crealo
                $this->pdo->prepare("INSERT INTO loyalty_points (user_id, points) VALUES (?, 0)")
                    ->execute([$userId]);
                $points = 0;
            }

            echo json_encode(['points' => (int)$points]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore nel recupero punti: ' . $e->getMessage()]);
        }
    }

    // 🟡 Aggiungi punti dopo un ordine
    public function addPoints($userId, $pointsToAdd)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO loyalty_points (user_id, points)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE points = points + VALUES(points)
            ");
            $stmt->execute([$userId, $pointsToAdd]);

            echo json_encode(['message' => 'Punti aggiunti con successo']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore aggiunta punti: ' . $e->getMessage()]);
        }
    }

    // 🔴 Riscatta punti in buoni
    public function redeemPoints($userId, $pointsToRedeem)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT points FROM loyalty_points WHERE user_id = ?");
            $stmt->execute([$userId]);
            $currentPoints = $stmt->fetchColumn();

            if ($currentPoints < $pointsToRedeem) {
                http_response_code(400);
                echo json_encode(['error' => 'Punti insufficienti']);
                return;
            }

            $stmt = $this->pdo->prepare("UPDATE loyalty_points SET points = points - ? WHERE user_id = ?");
            $stmt->execute([$pointsToRedeem, $userId]);

            echo json_encode(['message' => 'Buono riscattato con successo']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore riscatto punti: ' . $e->getMessage()]);
        }
    }
}
