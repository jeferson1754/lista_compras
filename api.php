<?php
// api/products.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Allow all origins for development. Restrict this in production.
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php'; // Adjust path as needed

$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Handle fetching products
        $stmt = $pdo->query("SELECT * FROM list_products ORDER BY created_at DESC");
        $products = $stmt->fetchAll();
        echo json_encode($products);
        break;

    case 'POST':
        // Handle adding a new product
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name']) || !isset($data['price']) || !isset($data['currency'])) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
            http_response_code(400);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO list_products (name, description, price, currency, product_url) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['currency'],
            $data['product_url'] ?? null
        ]);

        if ($success) {
            $lastInsertId = $pdo->lastInsertId();
            // Fetch the newly inserted product to return its data (including timestamps)
            $stmt = $pdo->prepare("SELECT * FROM list_products WHERE id = ?");
            $stmt->execute([$lastInsertId]);
            $newProduct = $stmt->fetch();
            echo json_encode(['success' => true, 'message' => 'Producto agregado.', 'id' => $lastInsertId, 'created_at' => $newProduct['created_at'], 'updated_at' => $newProduct['updated_at']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al agregar producto.']);
            http_response_code(500);
        }
        break;

    case 'PUT':
        // Handle updating an existing product
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$id || !isset($data['name']) || !isset($data['price']) || !isset($data['currency'])) {
            echo json_encode(['success' => false, 'message' => 'ID de producto o datos incompletos.']);
            http_response_code(400);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE list_products SET name = ?, description = ?, price = ?, currency = ?, product_url = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['currency'],
            $data['product_url'] ?? null,
            $id
        ]);

        if ($success) {
            // Fetch the updated product to return its new 'updated_at'
            $stmt = $pdo->prepare("SELECT updated_at FROM list_products WHERE id = ?");
            $stmt->execute([$id]);
            $updatedProduct = $stmt->fetch();
            echo json_encode(['success' => true, 'message' => 'Producto actualizado.', 'updated_at' => $updatedProduct['updated_at']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar producto.']);
            http_response_code(500);
        }
        break;

    case 'DELETE':
        // Handle deleting a product
        $id = $_GET['id'] ?? null;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID de producto no proporcionado.']);
            http_response_code(400);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM list_products WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Producto eliminado.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar producto.']);
            http_response_code(500);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        http_response_code(405); // Method Not Allowed
        break;
}
