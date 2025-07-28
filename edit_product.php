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

    $pdo = getDBConnection(); // Asegúrate de que esta función está definida en config.php

    $stmt = $pdo->prepare("UPDATE list_products SET name = ?, description = ?, price = ?, currency = ?, product_url = ?, updated_at = ?, necessity_level = ?, purchase_reason = ? WHERE id = ?");

    if ($stmt->execute([$name, $description, $price, $currency, $url, $fechaHoraActual, $necessityLevel, $purchaseReason, $productId])) {
        echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto.']);
    }
    exit;
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
    <div class="container">
        <div class="header">
            <h1>✏️ Editar Producto</h1>
            <p>Modifica los detalles de tu producto</p>
        </div>

        <div class="content">
            <div id="alert-container"></div>

            <div class="edit-form">
                <h3 style="margin-bottom: 20px; color: #495057;">Detalles del Producto</h3>
                <form id="edit-product-form">
                    <input type="hidden" id="product_id" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nombre del Producto *</label>
                            <input type="text" id="name" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="price_formatted">Precio</label>
                            <input type="text" id="price_formatted"
                                class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-green-100 focus:border-green-500 transition-all duration-300 placeholder-gray-400"
                                placeholder="$0" inputmode="numeric" autocomplete="off"
                                value="<?php
                                        // Formatear el precio al cargar la página si es CLP
                                        if ($product['currency'] === 'CLP') {
                                            echo number_format($product['price'], 0, ',', '.');
                                        } else {
                                            echo htmlspecialchars($product['price']);
                                        }
                                        ?>">
                            <input type="hidden" name="price_raw" id="price_raw" value="<?php echo htmlspecialchars($product['price']); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="necessity_level">Nivel de Necesidad</label>
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

                        <div class="form-group mb-4">
                            <label for="reason-select" class="block text-sm font-medium text-gray-700 mb-1">Motivo de la Compra</label>

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
                                <label for="custom-reason-textarea" class="sr-only">Motivo personalizado</label>
                                <textarea id="custom-reason-textarea" name="purchase_reason_custom" rows="3" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Describe el motivo específico aquí..."><?php echo $isCustomReason ? htmlspecialchars($savedReason) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency">Moneda</label>
                            <select id="currency" name="currency" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                                <option value="CLP" <?php echo ($product['currency'] == 'CLP') ? 'selected' : ''; ?>>CLP - Peso Chileno</option>
                                <option value="USD" <?php echo ($product['currency'] == 'USD') ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="EUR" <?php echo ($product['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="url">URL del Producto</label>
                            <input type="url" id="url" name="url" placeholder="https://..." value="<?php echo htmlspecialchars($product['product_url']); ?>" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-gray-100 focus:border-gray-500 transition-all duration-300 placeholder-gray-400 resize-none" rows="3" placeholder="Descripción opcional del producto"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </form>
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
                            // Opcional: recargar la página o redirigir después de un éxito
                            setTimeout(() => window.location.href = 'index.php', 1500);
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