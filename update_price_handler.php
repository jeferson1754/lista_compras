<?php

require_once 'config.php';
$pdo = getDBConnection();

$id = isset($_REQUEST['id']) ? $_REQUEST['id'] : null;
$price = isset($_REQUEST['price']) ? $_REQUEST['price'] : null;

if (isset($id) && isset($price)) {

    $id = (int)$id;
    $price = (float)$price;

    // A partir de aquí, todas las operaciones son un "todo o nada".
    $pdo->beginTransaction();

    // --- 2. Obtener el Precio Actual (ANTES de actualizar) ---
    $sql_get_price = "SELECT price FROM list_products WHERE id = ?";
    $stmt_get_price = $pdo->prepare($sql_get_price);
    $stmt_get_price->execute([$id]);
    $currentProduct = $stmt_get_price->fetch();

    if (!$currentProduct) {
        // Lanza una excepción que será capturada por el bloque catch.
        throw new Exception("El producto con ID $id no fue encontrado.");
    }
    $oldPrice = $currentProduct['price'];
    $newPrice = $price; // El nuevo precio que viene del formulario.

    // --- 3. Actualizar la Información Principal del Producto ---

    $stmt = $pdo->prepare("UPDATE list_products SET price = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$price, $fechaHoraActual, $id]);

    // --- 4. Condicional: Insertar en el Historial SOLO si el precio cambió ---
    if ($newPrice !== $oldPrice) {
        $sql_history = "INSERT INTO product_price (product_id, price, purchased_at) VALUES (?, ?, ?)";
        $stmt_history = $pdo->prepare($sql_history);
        // Ejecuta la inserción. Si falla, saltará al bloque catch.
        $stmt_history->execute([$id, $newPrice, $fechaHoraActual]);
    }

    $pdo->commit();

    echo "Sincronizado";
} else {

    echo $id . "<br>";
    echo $price;
    echo "Faltan datos";
}
