<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../vendor/autoload.php';

const JWT_SECRET_KEY = "supersecretkey"; // cambia con una chiave più sicura

/* ------------------------------------------------------ */
/* 🔐 GENERA TOKEN JWT                                    */
/* ------------------------------------------------------ */
function generateJWT($id, $email, $role)
{
    $payload = [
        'user_id' => $id,
        'email'   => $email,
        'role'    => $role,
        'iat'     => time(),
        'exp'     => time() + (60 * 60) // valido 1 ora
    ];

    return JWT::encode($payload, JWT_SECRET_KEY, 'HS256');
}

/* ------------------------------------------------------ */
/* 👤 OTTIENI UTENTE AUTENTICATO DAL TOKEN (se presente)  */
/* ------------------------------------------------------ */
function getAuthenticatedUser()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    }

    if (!$headers) return null;

    if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        $jwt = $matches[1];
        try {
            $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            error_log("❌ JWT decode failed: " . $e->getMessage());
            return null;
        }
    }

    return null;
}

/* ------------------------------------------------------ */
/* ✅ VALIDA TOKEN E BLOCCA ACCESSO NON AUTORIZZATO       */
/* ------------------------------------------------------ */
function validateJWT($authHeader)
{
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['error' => 'Token mancante o invalido']);
        exit;
    }

    $jwt = str_replace('Bearer ', '', $authHeader);

    try {
        $decoded = JWT::decode($jwt, new Key(JWT_SECRET_KEY, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token non valido: ' . $e->getMessage()]);
        exit;
    }
}
