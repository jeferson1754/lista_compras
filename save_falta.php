<?php
header('Content-Type: application/json');

// 1️⃣ Conexión DB (PDO)
require_once 'config.php';
$pdo = getDBConnection();

// 2️⃣ Leer JSON
$data = json_decode(file_get_contents("php://input"), true);

// 3️⃣ Validaciones
if (
    empty($data['product_id']) ||
    empty($data['importance'])
) {
    echo json_encode([
        'success' => false,
        'message' => 'Datos incompletos'
    ]);
    exit;
}

$data['fecha'] = $data['fecha'] ?? date('d/m/Y H:i', strtotime($fechaHoraActual));

// 4️⃣ Sanitizar
$productId = (int) $data['product_id'];
$context = trim($data['contexto'] ?? '');
$importance = (int) $data['importance'];


$data['fecha'] = empty($data['fecha'])
    ? date('Y-m-d H:i:s', strtotime($fechaHoraActual))
    : date('Y-m-d H:i:s', strtotime($data['fecha']));

$fecha = str_replace('T', ' ', $data['fecha']); // MySQL format

// 5️⃣ Preparar SQL
$sql = "INSERT INTO product_usages
(product_id, type, context, importance, used_at)
VALUES (:product_id, 'faltó', :context, :importance, :used_at)";

$stmt = $pdo->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al preparar la consulta'
    ]);
    exit;
}

// 6️⃣ Ejecutar (UNA sola vez)
$success = $stmt->execute([
    ':product_id' => $productId,
    ':context' => $context,
    ':importance' => $importance,
    ':used_at' => $fecha
]);

if ($success) {
    echo json_encode([
        'success' => true,
        'message' => 'Uso registrado correctamente'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar'
    ]);
}
