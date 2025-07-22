<?php
// Configuración de la base de datos
define('DB_HOST', 'sql208.epizy.com');
define('DB_USER', 'epiz_32740026');
define('DB_PASS', 'eJWcVk2au5gqD');
define('DB_NAME', 'epiz_32740026_r_user');

// Función para conectar a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para obtener precios actualizados (simulación)
function updateProductPrice($productId) {
    // En una implementación real, aquí se haría scraping o llamada a API
    // Por ahora simularemos una actualización de precio
    $newPrice = rand(50, 2000) + (rand(0, 99) / 100);
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE products SET price = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$newPrice, $productId]);
}
?>

