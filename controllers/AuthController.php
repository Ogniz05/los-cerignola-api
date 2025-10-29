<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/jwt.php';

class AuthController
{
    private $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /* -------------------------------------------------------- */
    /* 🔐 LOGIN UTENTE O STAFF                                  */
    /* -------------------------------------------------------- */
    public function login($email, $password)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Utente non trovato']);
                return;
            }

            if (!password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Password errata']);
                return;
            }

            // ✅ Genera token JWT (ora corretto)
            $token = generateJWT(
                $user['id'],
                $user['email'],
                $user['role']
            );

            http_response_code(200);
            echo json_encode([
                'message' => 'Login riuscito',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'loyalty_points' => (int)$user['loyalty_points']
                ],
                'token' => $token
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Errore DB: ' . $e->getMessage()]);
        }
    }
}
