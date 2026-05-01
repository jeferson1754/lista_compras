<?php
// Elimina cualquier mensaje de error visible que pueda romper el JSON
error_reporting(0);
header('Content-Type: application/json');
require_once 'config.php';
// --- 4. Get products from the database with filters and sorting ---
$pdo = getDBConnection(); // Make sure this function returns a valid PDO object.

try {
    $stmt = $pdo->prepare("
    SELECT id, name, product_url, price 
    FROM list_products 
    WHERE product_url IS NOT NULL 
    AND product_url != '' 
    AND product_url NOT LIKE :url_link
    AND is_purchased = FALSE
    ");

    $url_link = "aliexpress";

    $stmt->execute([
        ':url_link' => "%$url_link%"
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (Exception $e) {
    // Si hay error, enviamos un JSON con el error, no texto plano
    echo json_encode(['error' => $e->getMessage()]);
}
exit; // Asegura que no se imprima nada más