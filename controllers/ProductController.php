<?php
require_once __DIR__ . '/../config/database.php';

class ProductController
{
    private $pdo;

    public function __construct()
    {
        $database = new Database();
        $this->pdo = $database->connect();
    }

    /* ------------------------------------------------------------ */
    /* 📦 RESTITUISCE TUTTI I PRODOTTI                              */
    /* ------------------------------------------------------------ */
    public function getAllProducts()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name, description, price, image_url
                FROM products
                ORDER BY id ASC
            ");

            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode($products);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Errore durante il recupero dei prodotti',
                'message' => $e->getMessage()
            ]);
        }
    }

    /* ------------------------------------------------------------ */
    /* 🔍 RESTITUISCE UN PRODOTTO SPECIFICO PER ID                   */
    /* ------------------------------------------------------------ */
    public function getProductById($id)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, description, price, image_url
                FROM products
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                http_response_code(200);
                echo json_encode($product);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Prodotto non trovato']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Errore durante il recupero del prodotto',
                'message' => $e->getMessage()
            ]);
        }
    }

    /* ------------------------------------------------------------ */
    /* ➕ CREA UN NUOVO PRODOTTO (solo per testing o backend admin)  */
    /* ------------------------------------------------------------ */
    public function createProduct()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!isset($data['name'], $data['price'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome e prezzo sono obbligatori']);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO products (name, description, price, image_url)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? '',
                $data['price'],
                $data['image_url'] ?? null
            ]);

            http_response_code(201);
            echo json_encode([
                'message' => 'Prodotto creato con successo',
                'product_id' => $this->pdo->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Errore durante la creazione del prodotto',
                'message' => $e->getMessage()
            ]);
        }
    }
}
