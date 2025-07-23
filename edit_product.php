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

    // --- LIMPIEZA Y VALIDACIÓN DEL PRECIO EN PHP ---
    // Elimina cualquier caracter no numérico (para seguridad, aunque JS ya lo hace)
    $cleanPrice = preg_replace('/[^0-9]/', '', $priceRaw);
    // Convierte a entero. Si tu DB permite decimales, usa floatval().
    // Aquí asumimos enteros para CLP.
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

    $pdo = getDBConnection(); // Asegúrate de que esta función está definida en config.php

    $stmt = $pdo->prepare("UPDATE list_products SET name = ?, description = ?, price = ?, currency = ?, product_url = ?, updated_at = NOW() WHERE id = ?");

    if ($stmt->execute([$name, $description, $price, $currency, $url, $productId])) {
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
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Gestor de Precios</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
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
                            <label for="currency">Moneda</label>
                            <select id="currency" name="currency">
                                <option value="CLP" <?php echo ($product['currency'] == 'CLP') ? 'selected' : ''; ?>>CLP - Peso Chileno</option>
                                <option value="USD" <?php echo ($product['currency'] == 'USD') ? 'selected' : ''; ?>>USD - Dólar</option>
                                <option value="EUR" <?php echo ($product['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="url">URL del Producto</label>
                            <input type="url" id="url" name="url" placeholder="https://..." value="<?php echo htmlspecialchars($product['product_url']); ?>">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" rows="3" placeholder="Descripción opcional del producto"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js"></script>

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