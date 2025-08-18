<?php
// --- rebuy_product.php ---

header('Content-Type: application/json');
require_once 'config.php'; // Incluye tu archivo de conexión y funciones

// 1. Recibir los datos enviados por POST
$name = $_POST['name'] ?? '';
$price = $_POST['price'] ?? 0;
$currency = $_POST['currency'] ?? 'CLP';
$description = $_POST['description'] ?? '';
$url = $_POST['url'] ?? '';
$historyId = $_POST['history_id'] ?? null; // Recibimos el ID

// 2. Validar que al menos el nombre exista
if (empty(trim($name))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido.']);
    exit;
}

// 3. Insertar el producto en la lista principal
try {
    $pdo = getDBConnection();

    // Asignamos valores por defecto para la nueva entrada en la lista
    $fechaHoraActual = date('Y-m-d H:i:s');
    $defaultNecessity = 3; // 'Necesario' por defecto, puedes cambiarlo
    $defaultReason = 'Recompra desde historial';

    $sql = "INSERT INTO list_products 
                (name, description, price, currency, product_url, created_at, 
                 necessity_level, purchase_reason, rebuy_from_history_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $description, $price, $currency, $url, $fechaHoraActual, $defaultNecessity, $defaultReason, $historyId]);

    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => "'{$name}' ha sido añadido a tu lista de compras."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos al añadir el producto.']);
    // Opcional: Guardar el error real en un archivo de logs
    // error_log("Error en rebuy_product: " . $e->getMessage());
}
