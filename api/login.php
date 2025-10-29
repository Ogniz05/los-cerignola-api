<?php
// Mostra errori solo nel log, non in output
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore interno',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    error_log("🚨 Eccezione login: " . $e->getMessage());
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Errore PHP',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    error_log("⚠️ Errore PHP [$errno]: $errstr in $errfile:$errline");
    exit;
});

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email e password richieste']);
    exit;
}

// ✅ Esegui il login
$auth = new AuthController();
$auth->login($email, $password);
