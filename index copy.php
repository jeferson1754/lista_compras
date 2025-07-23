<?php
require_once 'config.php';

// Manejar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_product':
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $price = $_POST['price'] ?? 0;
            $currency = $_POST['currency'] ?? 'USD';
            $url = $_POST['url'] ?? '';

            if (!empty($name)) {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("INSERT INTO list_products (name, description, price, currency, product_url) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $price, $currency, $url])) {
                    echo json_encode(['success' => true, 'message' => 'Producto agregado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al agregar producto']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido']);
            }
            exit;

        case 'update_price':
            $productId = $_POST['product_id'] ?? 0;
            if ($productId > 0) {
                if (updateProductPrice($productId)) {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT price FROM list_products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $newPrice = $stmt->fetchColumn();
                    echo json_encode(['success' => true, 'new_price' => number_format($newPrice, 2)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar precio']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
            }
            exit;

        case 'delete_product':
            $productId = $_POST['product_id'] ?? 0;
            if ($productId > 0) {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("DELETE FROM list_products WHERE id = ?");
                if ($stmt->execute([$productId])) {
                    echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al eliminar producto']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
            }
            exit;
    }
}

// Obtener productos de la base de datos
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM list_products ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - Gestor de Precios</title>
    <style>
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

        .add-form {
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

        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 65, 108, 0.4);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .product-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .product-description {
            color: #6c757d;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 15px;
        }

        .product-url {
            margin-bottom: 20px;
        }

        .product-url a {
            color: #4facfe;
            text-decoration: none;
            font-weight: 500;
        }

        .product-url a:hover {
            text-decoration: underline;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .product-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
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

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-actions {
                flex-direction: column;
            }
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            color: white;
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.4);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Lista de Compras</h1>
            <p>Gestiona tus productos y mantén los precios actualizados</p>
        </div>

        <div class="content">
            <div id="alert-container"></div>

            <div class="add-form">
                <h3 style="margin-bottom: 20px; color: #495057;">➕ Agregar Nuevo Producto</h3>
                <form id="add-product-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Nombre del Producto *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="price">Precio</label>
                            <input type="number" id="price" name="price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="currency">Moneda</label>
                            <select id="currency" name="currency">
                                <option value="CLP">CLP - Peso Chileno</option>
                                <option value="USD">USD - Dólar</option>
                                <option value="EUR">EUR - Euro</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="url">URL del Producto</label>
                            <input type="url" id="url" name="url" placeholder="https://...">
                        </div>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description" rows="3" placeholder="Descripción opcional del producto"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Agregar Producto</button>
                </form>
            </div>

            <h3 style="margin-bottom: 25px; color: #495057;">📦 Mis Productos (<?php echo count($products); ?>)</h3>

            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                        <?php if (!empty($product['description'])): ?>
                            <div class="product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                        <?php endif; ?>
                        <div class="product-price" id="price-<?php echo $product['id']; ?>">
                            $<?php echo number_format($product['price'], 0, ',', '.'); ?> <?php echo $product['currency']; ?>
                        </div>
                        <div class="product-meta">
                            Actualizado: <?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?>
                        </div>
                        <?php if (!empty($product['product_url'])): ?>
                            <div class="product-url">
                                <a href="<?php echo htmlspecialchars($product['product_url']); ?>" target="_blank">🔗 Ver producto</a>
                            </div>
                        <?php endif; ?>
                        <div class="product-actions">
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-info">
                                ✏️ Editar
                            </a>
                            <button class="btn btn-danger delete-product-btn" data-product-id="<?php echo $product['id']; ?>">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div style="text-align: center; padding: 50px; color: #6c757d;">
                    <h4>No hay productos en tu lista</h4>
                    <p>Agrega tu primer producto usando el formulario de arriba</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Manejar formulario de agregar producto
        document.getElementById('add-product-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_product');

            fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        this.reset();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Error al procesar la solicitud', 'error');
                });
        });

        /*
        // Manejar actualización de precios
        document.querySelectorAll('.update-price-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const card = this.closest('.product-card');
                const priceElement = document.getElementById(`price-${productId}`);

                card.classList.add('loading');
                this.textContent = '⏳ Actualizando...';

                const formData = new FormData();
                formData.append('action', 'update_price');
                formData.append('product_id', productId);

                fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const currentCurrency = priceElement.textContent.split(' ')[1];
                            priceElement.textContent = `${data.new_price} ${currentCurrency}`;
                            showAlert('Precio actualizado correctamente', 'success');
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showAlert('Error al actualizar el precio', 'error');
                    })
                    .finally(() => {
                        card.classList.remove('loading');
                        this.textContent = '🔄 Actualizar Precio';
                    });
            });
        });
        */

        // Manejar eliminación de productos
        document.querySelectorAll('.delete-product-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (confirm('¿Estás seguro de que quieres eliminar este producto?')) {
                    const productId = this.dataset.productId;
                    const card = this.closest('.product-card');

                    const formData = new FormData();
                    formData.append('action', 'delete_product');
                    formData.append('product_id', productId);

                    fetch('', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                card.style.animation = 'fadeOut 0.5s ease-out';
                                setTimeout(() => {
                                    card.remove();
                                    showAlert(data.message, 'success');
                                }, 500);
                            } else {
                                showAlert(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showAlert('Error al eliminar el producto', 'error');
                        });
                }
            });
        });
    </script>

    <style>
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: scale(1);
            }

            to {
                opacity: 0;
                transform: scale(0.8);
            }
        }
    </style>
</body>

</html>