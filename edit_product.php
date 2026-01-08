<?php
require_once 'config.php';

// --- PHP: Manejar la lógica de guardado (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $productId = $_POST['product_id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    // ATENCIÓN: Ahora recibimos 'price_raw' del campo oculto JS
    $priceRaw = $_POST['price_raw'] ?? '0'; // Obtener el valor numérico limpio
    $currency = $_POST['currency'] ?? 'CLP'; // Asegúrate de que CLP sea el default si es tu moneda principal
    $url = $_POST['url'] ?? '';
    $necessityLevel = $_POST['necessity_level'] ?? 3;
    // --- LIMPIEZA Y VALIDACIÓN DEL PRECIO EN PHP ---
    // Elimina cualquier caracter no numérico (para seguridad, aunque JS ya lo hace)
    $cleanPrice = preg_replace('/[^0-9]/', '', $priceRaw);
    // Convierte a entero. Si tu DB permite decimales, usa floatval().
    // Aquí asumimos enteros para CLP.
    $purchaseReasonOption = $_POST['purchase_reason_option'] ?? '';

    $purchaseReason = '';
    if ($purchaseReasonOption === 'custom') {
        // Si el usuario eligió "Otro", el motivo es lo que escribió en el textarea
        $purchaseReason = $_POST['purchase_reason_custom'] ?? '';
    } else {
        // De lo contrario, el motivo es el valor de la opción predefinida que eligió
        $purchaseReason = $purchaseReasonOption;
    }

    // Nuevos campos
    // Valor por defecto si no se envía
    // Asegurarse de que necessityLevel sea un entero y esté dentro del rango esperado (1-5)
    $necessityLevel = max(1, min(5, intval($necessityLevel)));
    $price = intval($cleanPrice);

    // Validaciones
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de producto inválido.']);
        exit;
    }
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido.']);
        exit;
    }
    // La validación del precio ahora usa la variable $price limpia
    if (!is_numeric($price) || $price < 0) {
        echo json_encode(['success' => false, 'message' => 'El precio debe ser un número positivo.']);
        exit;
    }
    // Validar URL solo si no está vacía
    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'La URL del producto no es válida.']);
        exit;
    }

    // Validar nivel de necesidad (opcional, ya lo limitamos con max/min pero es buena práctica)
    if (!is_int($necessityLevel) || $necessityLevel < 1 || $necessityLevel > 5) {
        echo json_encode(['success' => false, 'message' => 'Nivel de necesidad inválido.']);
        exit;
    }
    try {
        $pdo = getDBConnection();
        // --- 1. INICIAR LA TRANSACCIÓN ---
        // A partir de aquí, todas las operaciones son un "todo o nada".
        $pdo->beginTransaction();

        // --- 2. Obtener el Precio Actual (ANTES de actualizar) ---
        $sql_get_price = "SELECT price FROM list_products WHERE id = ?";
        $stmt_get_price = $pdo->prepare($sql_get_price);
        $stmt_get_price->execute([$productId]);
        $currentProduct = $stmt_get_price->fetch();

        if (!$currentProduct) {
            // Lanza una excepción que será capturada por el bloque catch.
            throw new Exception("El producto con ID $productId no fue encontrado.");
        }
        $oldPrice = $currentProduct['price'];
        $newPrice = $price; // El nuevo precio que viene del formulario.

        // --- 3. Actualizar la Información Principal del Producto ---
        $sql_update = "UPDATE list_products 
                   SET name = ?, description = ?, price = ?, currency = ?, product_url = ?, 
                       updated_at = ?, necessity_level = ?, purchase_reason = ? 
                   WHERE id = ?";
        $stmt_update = $pdo->prepare($sql_update);
        // Ejecuta la actualización. Si falla, saltará al bloque catch.
        $stmt_update->execute([$name, $description, $newPrice, $currency, $url, $fechaHoraActual, $necessityLevel, $purchaseReason, $productId]);

        // --- 4. Condicional: Insertar en el Historial SOLO si el precio cambió ---
        if ($newPrice !== $oldPrice) {
            $sql_history = "INSERT INTO product_price (product_id, price, currency, purchased_at) VALUES (?, ?, ?, ?)";
            $stmt_history = $pdo->prepare($sql_history);
            // Ejecuta la inserción. Si falla, saltará al bloque catch.
            $stmt_history->execute([$productId, $newPrice, $currency, $fechaHoraActual]);
        }

        // --- 5. Si TODO lo anterior tuvo éxito, Confirmar los Cambios ---
        // Solo en este punto los cambios se guardan permanentemente en la BD.
        $pdo->commit();

        // --- 6. Enviar la Respuesta de Éxito AL FINAL ---
        http_response_code(200); // OK
        echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente.']);
        exit();
    } catch (Exception $e) {
        // --- 7. Si ALGO falló en el bloque 'try', Revertir TODOS los cambios ---
        // Se asegura de que haya una transacción activa antes de intentar revertirla.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Enviar una respuesta de error al usuario.
        http_response_code(500); // Internal Server Error
        // Es mejor no mostrar el error específico al usuario por seguridad.
        echo json_encode(['success' => false, 'message' => 'Ocurrió un error al actualizar el producto.']);

        // Opcional: Guarda el error real en un log para que tú lo veas.
        // error_log("Error de actualización: " . $e->getMessage());
    }
}

// --- PHP: Obtener datos del producto para mostrar en el formulario (GET) ---
$productId = $_GET['id'] ?? 0;

if ($productId > 0) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM list_products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: index.php?message=Producto+no+encontrado');
        exit;
    }
} else {
    header('Location: index.php?message=ID+de+producto+no+proporcionado');
    exit;
}

// --- NUEVO: Cargar usos ANTES de la compra ---
$product_usages = [];

try {
    $stmt_usages = $pdo->prepare(
        "SELECT context, importance, used_at
         FROM product_usages
         WHERE product_id = ?
           AND type = 'faltó'
         ORDER BY used_at DESC"
    );
    $stmt_usages->execute([$productId]);
    $product_usages = $stmt_usages->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // error_log("Error cargando usos: " . $e->getMessage());
}



// La lista de tus motivos predefinidos. Tenerla en un array es más fácil de gestionar.
$predefinedReasons = [
    'Necesidad Real',
    'Reemplazo',
    'Por Impulso',
    'Evento Específico',
    'Deseo / Capricho',
    'Por Promoción / Oferta',
    'Curiosidad / Probar'
];

// Obtenemos el motivo guardado en la base de datos.
$savedReason = $product['purchase_reason'] ?? '';

// Verificamos si el motivo guardado es uno personalizado.
// Es personalizado si NO está en nuestra lista de opciones predefinidas.
$isCustomReason = !empty($savedReason) && !in_array($savedReason, $predefinedReasons);


// --- NUEVO: Cargar el Historial de Precios del Producto ---
// 1. Cargar el historial de cambios de precio del PRODUCTO ACTUAL
// Esta consulta no cambia. Siempre se refiere al producto que estás editando.
$price_history = [];
try {
    $stmt_history = $pdo->prepare(
        query: "SELECT price, purchased_at FROM product_price WHERE product_id = ? ORDER BY purchased_at DESC"
    );
    $stmt_history->execute([$productId]);
    $price_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // error_log("No se pudo cargar el historial de precios: " . $e->getMessage());
}

// 2. Si es una recompra, obtener el PRECIO ORIGINAL de esa compra
$original_purchase = null;
if (!empty($product['rebuy_from_history_id'])) {
    try {
        // Buscamos en la tabla de historial de COMPRAS, no en la de precios
        $stmt_original = $pdo->prepare(
            "SELECT purchased_price, purchased_at FROM purchase_history WHERE id = ?"
        );
        $stmt_original->execute([$product['rebuy_from_history_id']]);
        $original_purchase = $stmt_original->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // error_log("No se pudo cargar la compra original: " . $e->getMessage());
    }
}



?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Gestor de Precios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* Estilos CSS idénticos a los de tu página principal para mantener la consistencia */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 300;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 30px;
        }

        .edit-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
            margin-bottom: 5px;
            color: #495057;
        }

        input,
        textarea,
        select {
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #4facfe;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(108, 117, 125, 0.4);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-800">✏️ Editar Producto</h1>
            <p class="text-lg text-gray-600 mt-1">Modifica los detalles y consulta su historial de precios.</p>
        </div>

        <div id="alert-container" class="mb-6"></div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-200">
                <form id="edit-product-form" class="p-8 space-y-6">
                    <input type="hidden" id="product_id" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-1">Nombre del Producto *</label>
                        <input type="text" id="name" name="name" class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-500 transition" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="price_formatted" class="block text-sm font-semibold text-gray-700 mb-1">Precio</label>
                            <input type="text" id="price_formatted" class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-400 focus:border-green-500 transition" value="<?php echo number_format($product['price'], 0, ',', '.'); ?>" placeholder="$0">
                            <input type="hidden" name="price_raw" id="price_raw" value="<?php echo htmlspecialchars($product['price']); ?>">
                        </div>
                        <div>
                            <label for="currency" class="block text-sm font-semibold text-gray-700 mb-1">Moneda</label>
                            <select id="currency" name="currency" class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-500 transition">
                                <option value="CLP" <?php echo ($product['currency'] == 'CLP') ? 'selected' : ''; ?>>CLP - Peso Chileno</option>
                                <option value="USD" <?php echo ($product['currency'] == 'USD') ? 'selected' : ''; ?>>USD - Dólar</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="necessity_level" class="block text-sm font-semibold text-gray-700 mb-1">Nivel de Necesidad</label>
                            <select id="necessity_level" name="necessity_level"
                                class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                                <option value="5" <?php echo (isset($product['necessity_level']) && $product['necessity_level'] == 5) ? 'selected' : ''; ?>>5 - ¡Esencial!</option>
                                <option value="4" <?php echo (isset($product['necessity_level']) && $product['necessity_level'] == 4) ? 'selected' : ''; ?>>4 - Muy Necesario</option>
                                <option value="3" <?php echo (isset($product['necessity_level']) && $product['necessity_level'] == 3) ? 'selected' : ''; ?>>3 - Necesario</option>
                                <option value="2" <?php echo (isset($product['necessity_level']) && $product['necessity_level'] == 2) ? 'selected' : ''; ?>>2 - Opcional</option>
                                <option value="1" <?php echo (isset($product['necessity_level']) && $product['necessity_level'] == 1) ? 'selected' : ''; ?>>1 - Capricho</option>
                                <?php if (!isset($product['necessity_level'])): ?>
                                    <option value="" disabled selected>Selecciona un nivel</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label for="url" class="block text-sm font-semibold text-gray-700 mb-1">URL del Producto (Opcional)</label>
                            <input type="url" id="url" name="url" class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-400 focus:border-blue-500 transition" value="<?php echo htmlspecialchars($product['product_url']); ?>" placeholder="https://...">
                        </div>
                    </div>

                    <div>
                        <label for="reason-select" class="block text-sm font-semibold text-gray-700 mb-1">Motivo de la Compra</label>
                        <select id="reason-select" name="purchase_reason_option" class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl focus:ring-4 focus:ring-pink-100 focus:border-pink-500 transition-all duration-300">
                            <option value="">Selecciona un motivo</option>

                            <?php foreach ($predefinedReasons as $reason): ?>
                                <option value="<?php echo htmlspecialchars($reason); ?>" <?php echo ($savedReason === $reason) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($reason); ?>
                                </option>
                            <?php endforeach; ?>

                            <option value="custom" <?php echo $isCustomReason ? 'selected' : ''; ?>>
                                Otro (especificar)...
                            </option>
                        </select>
                        <div id="custom-reason-wrapper" class="mt-2 <?php echo $isCustomReason ? '' : 'hidden'; ?>">
                            <textarea id="custom-reason-textarea" name="purchase_reason_custom" rows="2" class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-xl" placeholder="Describe el motivo..."><?php echo $isCustomReason ? htmlspecialchars($savedReason) : ''; ?></textarea>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Descripción (Opcional)</label>
                        <textarea id="description" name="description" class="w-full px-4 py-3 bg-gray-50 border border-gray-300 rounded-xl resize-y" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="pt-6 border-t border-gray-200 flex items-center gap-4">
                        <button type="submit" class="w-full sm:w-auto bg-blue-600 text-white font-bold py-3 px-8 rounded-xl hover:bg-blue-700 transition-all duration-300 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i>
                            Guardar Cambios
                        </button>
                        <a href="index.php" class="text-gray-600 font-semibold hover:text-gray-800 transition">Cancelar</a>
                    </div>
                </form>
            </div>


            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg ... p-6">

                    <?php if ($original_purchase): ?>
                        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <h4 class="text-sm font-semibold text-blue-800">Recomprado de:</h4>
                            <p class="text-xl font-bold text-blue-900 mt-1">
                                $<?php echo number_format($original_purchase['purchased_price'], 0, ',', '.'); ?>
                            </p>
                            <p class="text-xs text-blue-600 mt-1">
                                (Comprado el <?php echo date('d/m/Y', strtotime($original_purchase['purchased_at'])); ?>)
                            </p>
                        </div>
                    <?php endif; ?>

                    <h3 class="text-xl font-bold text-gray-800 mb-4 ...">
                        <i class="fas fa-chart-line ..."></i>
                        Historial de Cambios
                    </h3>
                    <div id="price-history-list" class="space-y-2 ...">
                        <?php if (empty($price_history)): ?>
                            <p class="text-gray-500 ...">No hay cambios de precio registrados para este ítem.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php
                                $prev_price = null; // Guardará el último precio mostrado (más reciente)
                                foreach ($price_history as $history_item):
                                    $price = $history_item['price'];
                                    $color = "text-gray-600";
                                    $indicator = "";

                                    if (!is_null($prev_price)) {
                                        // Ojo: como estamos recorriendo del más reciente al más antiguo
                                        // se debe invertir la lógica de comparación
                                        if ($price > $prev_price) {
                                            $color = "text-green-600"; // ahora es BAJÓ
                                            $indicator = "↓ Bajó";
                                        } elseif ($price < $prev_price) {
                                            $color = "text-red-600"; // ahora es SUBIÓ
                                            $indicator = "↑ Subió";
                                        } else {
                                            $color = "text-gray-600";
                                            $indicator = "→ Igual";
                                        }
                                    }
                                ?>
                                    <li class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                                        <p class="text-sm <?php echo $color; ?>">
                                            <strong>Precio:</strong> $<?php echo number_format($price, 0, ',', '.'); ?>
                                            <?php if ($indicator): ?>
                                                <span class="ml-2 font-semibold"><?php echo $indicator; ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            (Cambió el <?php echo date('d/m/Y', strtotime($history_item['purchased_at'])); ?>)
                                        </p>
                                    </li>
                                <?php
                                    $prev_price = $price; // actualizamos referencia
                                endforeach;
                                ?>
                            </ul>
                        <?php endif; ?>



                    </div>
                    <div class="mt-6 bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                            Usos antes de la compra
                        </h3>

                        <?php if (empty($product_usages)): ?>
                            <p class="text-gray-500 text-sm">
                                No se registraron faltas antes de la compra.
                            </p>
                        <?php else: ?>
                            <ul class="space-y-3 max-h-64 overflow-y-auto pr-2">
                                <?php foreach ($product_usages as $usage): ?>
                                    <li class="p-4 bg-red-50 border border-red-200 rounded-xl">
                                        <p class="text-sm text-gray-800">
                                            <strong>Contexto:</strong>
                                            <?php echo htmlspecialchars($usage['context'] ?: 'No especificado'); ?>
                                        </p>
                                        <div class="mt-2">
                                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                                <span>Importancia</span>
                                                <span><?php echo $usage['importance']; ?>/5</span>
                                            </div>

                                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    style="
                width: <?php echo ($usage['importance'] * 20); ?>%;
                background-color:<?php
                                    echo match (true) {
                                        $usage['importance'] >= 4 => '#ef4444', // rojo
                                        $usage['importance'] == 3 => '#f59e0b', // amarillo
                                        default => '#22c55e', // verde
                                    };
                ?>
            ">
                                                </div>
                                            </div>
                                        </div>


                                        <p class="text-xs text-gray-500 mt-2">
                                            <?php echo date('d/m/Y H:i', strtotime($usage['used_at'])); ?>
                                        </p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <div class="mt-4 text-sm text-gray-600 font-semibold">
                                Total de faltas registradas:
                                <span class="text-red-600">
                                    <?php echo count($product_usages); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>
    <script>
        const reasonSelect = document.getElementById('reason-select');
        const customReasonWrapper = document.getElementById('custom-reason-wrapper');
        const customReasonTextarea = document.getElementById('custom-reason-textarea');

        reasonSelect.addEventListener('change', function() {
            if (reasonSelect.value === 'custom') {
                customReasonWrapper.classList.remove('hidden');
                customReasonTextarea.setAttribute('required', '');
            } else {
                customReasonWrapper.classList.add('hidden');
                customReasonTextarea.removeAttribute('required');
                customReasonTextarea.value = ''; // Limpia el textarea si se cambia de opción
            }
        });
    </script>
    <script>
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // --- FUNCIONES DE FORMATO DE PRECIO EN JAVASCRIPT ---
        // Función para limpiar el valor de formato y obtener solo los dígitos
        function cleanNumber(formattedValue) {
            return formattedValue.replace(/[^0-9]/g, ''); // Elimina todo lo que no sea dígito
        }

        // Función para formatear el número como CLP
        function formatCurrencyCLP(value) {
            let cleanValue = value.replace(/[^0-9]/g, '');
            let numberValue = parseInt(cleanValue, 10);
            if (isNaN(numberValue) || cleanValue === '') {
                return '';
            }
            return new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: 'CLP',
                useGrouping: true,
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(numberValue);
        }
        // --- FIN FUNCIONES DE FORMATO DE PRECIO ---


        document.addEventListener('DOMContentLoaded', function() {
            const priceFormattedInput = document.getElementById('price_formatted'); // Tu input visible
            const priceRawInput = document.getElementById('price_raw'); // Tu input oculto para la BD
            const currencySelect = document.getElementById('currency'); // Tu select de moneda
            const form = document.getElementById('edit-product-form');
            const necessityLevelSelect = document.getElementById('necessity_level'); // Nuevo


            // --- Lógica de formato en tiempo real para el campo de precio ---
            // Solo aplica el formato si la moneda seleccionada es CLP
            const applyFormat = () => {
                if (currencySelect.value === 'CLP') {
                    // Si el campo visible ya tiene un valor (ej. cargado de la BD)
                    if (priceFormattedInput.value) {
                        priceFormattedInput.value = formatCurrencyCLP(priceFormattedInput.value);
                    }
                } else {
                    // Si la moneda no es CLP, quitar cualquier formato de CLP
                    // y mostrar el valor numérico limpio.
                    priceFormattedInput.value = cleanNumber(priceFormattedInput.value);
                }
            };

            // Listener para el input de precio
            priceFormattedInput.addEventListener('input', function(e) {
                if (currencySelect.value === 'CLP') {
                    const originalLength = e.target.value.length;
                    const cursorPosition = e.target.selectionStart;

                    let formattedValue = formatCurrencyCLP(e.target.value);
                    e.target.value = formattedValue;

                    const newLength = e.target.value.length;
                    const diff = newLength - originalLength;
                    if (cursorPosition + diff >= 0) {
                        e.target.setSelectionRange(cursorPosition + diff, cursorPosition + diff);
                    }
                }
                // ¡IMPORTANTE! Siempre actualizar el campo oculto con el valor limpio
                priceRawInput.value = cleanNumber(priceFormattedInput.value);
            });

            // Listener para el cambio de moneda
            currencySelect.addEventListener('change', function() {
                applyFormat(); // Re-aplicar o quitar formato cuando cambia la moneda
                // También actualiza el campo oculto inmediatamente después de cambiar moneda
                priceRawInput.value = cleanNumber(priceFormattedInput.value);
            });

            // Aplicar formato inicial al cargar la página si la moneda es CLP
            applyFormat();
            // Asegurarse de que el campo oculto tenga el valor correcto al cargar
            priceRawInput.value = cleanNumber(priceFormattedInput.value);


            // --- Lógica de envío de formulario (AJAX) ---
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Antes de enviar, asegúrate de que el campo oculto tenga el valor limpio final
                priceRawInput.value = cleanNumber(priceFormattedInput.value);

                const formData = new FormData(this);

                // Validaciones en el cliente (usando el valor limpio del input oculto o el visible)
                const productName = document.getElementById('name').value.trim();
                const productPriceForValidation = parseInt(priceRawInput.value, 10); // Usa el valor limpio del hidden input
                const productUrl = document.getElementById('url').value.trim();

                // Validación del nivel de necesidad
                const selectedNecessityLevel = necessityLevelSelect.value;
                if (selectedNecessityLevel === '' || parseInt(selectedNecessityLevel) < 1 || parseInt(selectedNecessityLevel) > 5) {
                    showAlert('Por favor, selecciona un nivel de necesidad válido.', 'error');
                    return;
                }

                if (productName === '') {
                    showAlert('El nombre del producto es requerido.', 'error');
                    return;
                }
                // Ajusta la validación para usar el entero limpio
                if (isNaN(productPriceForValidation) || productPriceForValidation < 0) {
                    showAlert('El precio debe ser un número positivo.', 'error');
                    return;
                }
                if (productUrl !== '' && !isValidUrl(productUrl)) {
                    showAlert('La URL del producto no es válida. Asegúrate de incluir http:// o https://', 'error');
                    return;
                }

                fetch('', { // La misma página maneja el POST
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert(data.message, 'success');
                            // Opcional: recargar la página después de un éxito para ver los cambios
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Error al procesar la solicitud.', 'error');
                    });
            });

            // Función de validación de URL simple
            function isValidUrl(string) {
                try {
                    new URL(string);
                    return true;
                } catch (e) {
                    return false;
                }
            }
        });
    </script>
</body>

</html>