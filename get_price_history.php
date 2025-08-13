<?php
// --- Conexión a la base de datos (reutiliza tu código de conexión) ---
header('Content-Type: application/json'); // Indica que la respuesta será JSON
require_once 'config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
// --- Lógica para obtener el historial de precios ---

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // <-- ESTA LÍNEA ES LA SOLUCIÓN
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
// Obtener el nombre del producto de la solicitud GET
$productId = $_GET['id'] ?? '';

if (empty($productId)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se proporcionó el ID del producto']);
    exit;
}

try {
    // Consulta para obtener el precio y la fecha de un producto específico, ordenado por fecha
    $sql = "SELECT price, purchased_at FROM product_price WHERE product_id = ? ORDER BY purchased_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$productId]);

    $history = $stmt->fetchAll();

    // Devolver los resultados en formato JSON
    echo json_encode($history);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al ejecutar la consulta']);
}
