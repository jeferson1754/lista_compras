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
            // Asumiendo que has recibido los datos por POST
            $priceInput = $_POST['price'] ?? ''; // Obtener el valor del campo 'price' (que es tu hidden input)

            // Nuevo campo: Nivel de Necesidad
            $necessityLevel = $_POST['necessity_level'] ?? 3; // Valor predeterminado si no se selecciona
            // Asegurarse de que necessityLevel sea un entero y esté dentro del rango esperado (1-5)
            $necessityLevel = max(1, min(5, intval($necessityLevel)));
            // Limpiar el valor recibido para asegurar que sea numérico
            // Aunque JavaScript lo limpia, es CRÍTICO hacerlo también en el servidor
            // como medida de seguridad y robustez, ya que JS puede ser deshabilitado o manipulado.
            $cleanPrice = preg_replace('/[^0-9]/', '', $priceInput); // Elimina todo lo que no sea dígito

            // Convertir a un tipo numérico (integer o float, dependiendo de tu necesidad)
            // Si tu precio es siempre entero (como en CLP), puedes usar intval()
            $price = intval($cleanPrice);
            $currency = $_POST['currency'] ?? 'USD';
            $url = $_POST['url'] ?? '';

            if (!empty($name)) {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("INSERT INTO list_products (name, description, price, currency, product_url, created_at, necessity_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $price, $currency, $url, $fechaHoraActual, $necessityLevel])) {
                    echo json_encode(['success' => true, 'message' => 'Producto agregado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al agregar producto']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'El nombre del producto es requerido']);
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
        case 'mark_purchased':
            $productId = $_POST['product_id'] ?? 0;
            $purchasedPrice = $_POST['purchased_price'] ?? 0; // Podría ser el precio actual o el que el usuario ingresa
            $purchasedCurrency = $_POST['purchased_currency'] ?? 'CLP';

            if ($productId <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido.']);
                exit;
            }

            $pdo = getDBConnection();

            // 1. Obtener los datos del producto antes de moverlo
            $stmt = $pdo->prepare("SELECT * FROM list_products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado.']);
                exit;
            }

            // Limpieza del precio (repetir la misma lógica que ya tienes)
            $cleanPurchasedPrice = preg_replace('/[^0-9.]/', '', $purchasedPrice); // Permite decimales si los usas
            $finalPurchasedPrice = floatval($cleanPurchasedPrice);

            // 2. Insertar en purchase_history
            $stmt = $pdo->prepare("INSERT INTO purchase_history (
            product_id, name, description, purchased_price, purchased_currency, product_url,
            necessity_level, purchase_reason, purchased_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"); // NOW() para la fecha de compra

            try {
                $pdo->beginTransaction(); // Iniciar transacción para asegurar atomicidad

                $stmt->execute([
                    $product['id'],
                    $product['name'],
                    $product['description'],
                    $finalPurchasedPrice, // Usar el precio de compra final
                    $purchasedCurrency,
                    $product['product_url'],
                    $product['necessity_level'],
                    $product['purchase_reason'] ?? NULL // Puede que purchase_reason aún no exista en todos los productos
                ]);

                // 3. Marcar el producto original como comprado en list_products
                $stmtUpdate = $pdo->prepare("UPDATE list_products SET is_purchased = TRUE WHERE id = ?");
                $stmtUpdate->execute([$productId]);

                $pdo->commit(); // Confirmar la transacción

                echo json_encode(['success' => true, 'message' => 'Producto marcado como comprado y movido al historial.']);
            } catch (PDOException $e) {
                $pdo->rollBack(); // Revertir si algo falla
                error_log("Error al marcar como comprado: " . $e->getMessage()); // Para depuración
                echo json_encode(['success' => false, 'message' => 'Error al registrar la compra: ' . $e->getMessage()]);
            }
            exit;
    }
}

$resultados = obtener_datos($conexion, "c.Nombre = 'Ocio' OR c.Categoria_Padre = '24'", $current_month, $current_year, $previous_month, $previous_year);

$datos_financieros = obtener_datos_ultimos_meses($conexion, 6);

$ultimo_mes = end($datos_financieros);

$balance_mes_actual = $ultimo_mes['ingresos'] - $ultimo_mes['egresos'];
$total_ingresos = $ultimo_mes['ingresos'];

$total_ocio = $resultados['total'];
$result_detalles_ocio = $resultados['detalles'];
$anterior_total_ocio = $resultados['anterior_total'];

// --- 1. Obtener y sanitizar parámetros de filtro y ordenamiento ---
$searchName = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0; // 0 for 'all categories' or no filter
// --- 3. Construir la cláusula ORDER BY dinámicamente ---
$ocio = $total_ingresos * 0.3;

$ocio_restante = $ocio - $total_ocio;

$leisureBudget = floatval($ocio);

// --- 2. Construir la cláusula WHERE dinámicamente ---
$whereClauses = [];
$queryParams = []; // For prepared statement parameter binding

// Filter by name
if (!empty($searchName)) {
    $whereClauses[] = "name LIKE :search_name";
    $queryParams[':search_name'] = '%' . $searchName . '%';
}

/*
// Filter by category (assuming 0 means 'all categories' or no filter)
if ($categoryId > 0) {
    $whereClauses[] = "category_id = :category_id";
    $queryParams[':category_id'] = $categoryId;
}
    */

// Join the WHERE clauses
$where = '';
if (count($whereClauses) > 0) {
    $where = ' WHERE ' . implode(' AND ', $whereClauses);
}
$sortBy = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'necessity_level-desc'; // CAMBIO IMPORTANTE: Nuevo valor por defecto

$orderBy = "";
switch ($sortBy) {
    case 'name-asc':
        $orderBy = "ORDER BY name ASC";
        break;
    case 'name-desc':
        $orderBy = "ORDER BY name DESC";
        break;
    case 'price-asc':
        $orderBy = "ORDER BY price ASC";
        break;
    case 'price-desc':
        $orderBy = "ORDER BY price DESC";
        break;
    case 'created_at-desc':
        $orderBy = "ORDER BY created_at DESC";
        break;
    case 'necessity_level-desc': // Nuevo: Ordenar por nivel de necesidad (de mayor a menor)
        // Agregamos created_at como segundo criterio para desempate si los niveles son iguales
        $orderBy = "ORDER BY necessity_level DESC, created_at DESC";
        break;
    default: // Fallback for invalid sort_by values
        $orderBy = "ORDER BY created_at DESC";
        break;
}

// --- 4. Get products from the database with filters and sorting ---
$pdo = getDBConnection(); // Make sure this function returns a valid PDO object.
$sql = "SELECT * FROM list_products" . $where . " AND is_purchased = FALSE " . $orderBy;

// Si $where puede estar vacío:
$finalWhere = " WHERE is_purchased = FALSE";
if (!empty($whereClauses)) {
    $finalWhere = " WHERE " . implode(' AND ', $whereClauses) . " AND is_purchased = FALSE";
}

$sql = "SELECT * FROM list_products" . $finalWhere . " " . $orderBy;

$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams); // Execute the query with bound parameters for safety
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lastUpdateTimestamp = 0; // Para almacenar el timestamp más reciente de actualización
// Calcular valor total y precio promedio en CLP
$totalValue = 0;
foreach ($products as $p) {
    $price = floatval($p['price']);
    if ($p['currency'] === 'USD') $price *= 800;
    if ($p['currency'] === 'EUR') $price *= 900;
    $totalValue += $price;
    // Actualizar el timestamp de la última modificación
    // Asumiendo que 'updated_at' es una columna TIMESTAMP/DATETIME en tu DB
    if (isset($p['updated_at'])) {
        $currentProductTimestamp = strtotime($p['updated_at']);
        if ($currentProductTimestamp > $lastUpdateTimestamp) {
            $lastUpdateTimestamp = $currentProductTimestamp;
        }
    }
}
$totalProducts = count($products);
$avgPrice = $totalProducts > 0 ? $totalValue / $totalProducts : 0;
// Formatear la fecha de última actualización
$lastUpdate = $lastUpdateTimestamp > 0 ? date('d/m/Y H:i', $lastUpdateTimestamp) : 'Nunca';
$estimatedTotalCost = $totalValue;

$generalMonthlyBudget = $ocio_restante;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - Gestor de Precios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'inter': ['Inter', 'sans-serif'],
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'scale-in': 'scaleIn 0.2s ease-out',
                        'fade-out': 'fadeOut 0.3s ease-in'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': {
                                opacity: '0',
                                transform: 'translateY(20px)'
                            },
                            '100%': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            }
                        },
                        slideUp: {
                            '0%': {
                                opacity: '0',
                                transform: 'translateY(30px)'
                            },
                            '100%': {
                                opacity: '1',
                                transform: 'translateY(0)'
                            }
                        },
                        bounceGentle: {
                            '0%, 100%': {
                                transform: 'translateY(0)'
                            },
                            '50%': {
                                transform: 'translateY(-5px)'
                            }
                        },
                        scaleIn: {
                            '0%': {
                                transform: 'scale(0.95)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'scale(1)',
                                opacity: '1'
                            }
                        },
                        fadeOut: {
                            '0%': {
                                opacity: '1',
                                transform: 'scale(1)'
                            },
                            '100%': {
                                opacity: '0',
                                transform: 'scale(0.95)'
                            }
                        }
                    },
                    backgroundImage: {
                        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                        'mesh-gradient': 'linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%)',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .skeleton {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .loading-state {
            position: relative;
            overflow: hidden;
        }

        .loading-state::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            100% {
                left: 100%;
            }
        }
    </style>
</head>

<body class="font-inter bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 min-h-screen">

    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-1/2 -left-1/2 w-96 h-96 bg-gradient-to-r from-blue-400/20 to-purple-400/20 rounded-full blur-3xl animate-pulse-slow"></div>
        <div class="absolute -bottom-1/2 -right-1/2 w-96 h-96 bg-gradient-to-r from-pink-400/20 to-orange-400/20 rounded-full blur-3xl animate-bounce-gentle"></div>
    </div>

    <nav class="sticky top-0 z-40 glass-effect border-b border-white/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas fa-shopping-cart text-white text-lg"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-800">Lista de Compras</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 bg-white/50 px-3 py-1 rounded-full">
                        <i class="fas fa-box mr-1"></i>
                        <span><?= $totalProducts ?></span> productos
                    </span>
                </div>
            </div>
        </div>
    </nav>



    <!-- OPCIÓN 3: Botón Flotante Lateral -->
    <div class="fixed right-6 bottom-6 z-50">
        <a href="history.php"
            class="flex items-center bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white px-4 py-3 rounded-full shadow-2xl hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">
            <i class="fas fa-history text-lg"></i>
            <span class="font-medium hidden group-hover:inline-block transition-all">Ver Historial</span>
        </a>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alert-container" class="mb-0"></div>
        <div class="mb-6">
            <h2 class="text-3xl font-bold text-gray-900 mb-2 flex items-center group">
                <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl mr-4 shadow-lg group-hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-boxes text-white text-lg"></i>
                </div>
                <span class="bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">
                    Mis Productos
                </span>
                <span class="ml-4 inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-600 text-lg font-medium rounded-full border">
                    <?php echo count($products); ?>
                </span>
            </h2>
            <p class="text-gray-600 ml-16">Gestiona y monitorea tus productos deseados</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white/70 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-lg animate-fade-in">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-shopping-bag text-white text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Productos</p>
                        <p class="text-2xl font-bold text-gray-800"><?= $totalProducts ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white/70 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-lg animate-fade-in">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-400 to-blue-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-white text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Valor Total</p>
                        <p class="text-2xl font-bold text-gray-800">$<?= number_format($totalValue, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white/70 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-lg animate-fade-in">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-400 to-purple-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-line text-white text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Precio Promedio</p>
                        <p class="text-2xl font-bold text-gray-800">$<?= number_format($avgPrice, 0, ',', '.'); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white/70 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-lg animate-fade-in">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-orange-400 to-orange-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock text-white text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Última Actualización</p>
                        <p class="text-lg font-semibold text-gray-800"><?= $lastUpdate ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-8">
            <!-- Título principal con animación -->


            <!-- Panel de resumen financiero -->
            <div class="grid md:grid-cols-2 gap-6">

                <!-- Tarjeta de Gasto Estimado -->
                <div class="relative overflow-hidden bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 group
            <?php echo ($estimatedTotalCost > $generalMonthlyBudget && $generalMonthlyBudget > 0) ? 'ring-2 ring-red-200' : 'ring-1 ring-gray-100'; ?>">

                    <!-- Indicador lateral animado -->
                    <div class="absolute left-0 top-0 w-1.5 h-full bg-gradient-to-b 
                <?php echo ($estimatedTotalCost > $generalMonthlyBudget && $generalMonthlyBudget > 0) ? 'from-red-400 to-red-600' : 'from-emerald-400 to-emerald-600'; ?>
                group-hover:w-2 transition-all duration-300">
                    </div>

                    <!-- Contenido principal -->
                    <div class="p-6 pl-8">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-xl mr-3
                                <?php echo ($estimatedTotalCost > $generalMonthlyBudget && $generalMonthlyBudget > 0) ? 'bg-red-100' : 'bg-emerald-100'; ?>">
                                        <i class="fas fa-calculator 
                                    <?php echo ($estimatedTotalCost > $generalMonthlyBudget && $generalMonthlyBudget > 0) ? 'text-red-600' : 'text-emerald-600'; ?>">
                                        </i>
                                    </div>
                                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide">
                                        Gasto Estimado Total
                                    </h3>
                                </div>

                                <div class="mb-4">
                                    <p class="text-3xl font-bold text-gray-900 mb-1">
                                        $<?php echo number_format($estimatedTotalCost, 0, ',', '.'); ?>
                                        <span class="text-lg font-medium text-gray-500">CLP</span>
                                    </p>

                                    <!-- Barra de progreso visual -->
                                    <?php if ($generalMonthlyBudget > 0): ?>
                                        <?php
                                        $percentage = min(($estimatedTotalCost / $generalMonthlyBudget) * 100, 100);
                                        $isOverBudget = $estimatedTotalCost > $generalMonthlyBudget;
                                        ?>
                                        <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                                            <div class="h-2 rounded-full transition-all duration-500 
                                        <?php echo $isOverBudget ? 'bg-gradient-to-r from-red-400 to-red-600' : 'bg-gradient-to-r from-emerald-400 to-emerald-600'; ?>"
                                                style="width: <?php echo min($percentage, 100); ?>%">
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <?php echo number_format($percentage, 1); ?>% del presupuesto general
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <!-- Estado del presupuesto -->
                                <?php if ($generalMonthlyBudget > 0 && $estimatedTotalCost > $generalMonthlyBudget): ?>
                                    <div class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 text-xs font-semibold rounded-full border border-red-200">
                                        <i class="fas fa-exclamation-triangle mr-1.5"></i>
                                        Excede presupuesto por $<?php echo number_format($estimatedTotalCost - $generalMonthlyBudget, 0, ',', '.'); ?>
                                    </div>
                                <?php elseif ($generalMonthlyBudget > 0): ?>
                                    <div class="inline-flex items-center px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-full border border-emerald-200">
                                        <i class="fas fa-check-circle mr-1.5"></i>
                                        Dentro del presupuesto
                                    </div>
                                <?php else: ?>
                                    <div class="inline-flex items-center px-3 py-1.5 bg-amber-50 text-amber-700 text-xs font-semibold rounded-full border border-amber-200">
                                        <i class="fas fa-info-circle mr-1.5"></i>
                                        Define un presupuesto general
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Presupuesto General y Ocio -->
                <div class="space-y-4">

                    <!-- Presupuesto General -->
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-blue-100 rounded-xl mr-3 group-hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-wallet text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        Presupuesto Restante de Ocio
                                    </h3>
                                    <p class="text-xl font-bold <?php echo ($estimatedTotalCost > $generalMonthlyBudget && $generalMonthlyBudget > 0) ? 'text-red-600' : 'text-blue-700'; ?>">
                                        $<?php echo number_format($generalMonthlyBudget, 0, ',', '.'); ?>
                                        <span class="text-sm font-medium text-gray-500">CLP</span>
                                    </p>
                                </div>
                            </div>

                            <?php if ($generalMonthlyBudget > 0): ?>
                                <?php $remaining = $generalMonthlyBudget - $estimatedTotalCost; ?>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500 mb-1">Restante</p>
                                    <p class="text-lg font-bold <?php echo $remaining >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>">
                                        $<?php echo number_format(abs($remaining), 0, ',', '.'); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Presupuesto de Ocio -->
                    <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 p-5 border border-gray-100 group">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-10 h-10 bg-purple-100 rounded-xl mr-3 group-hover:bg-purple-200 transition-colors">
                                    <i class="fas fa-gamepad text-purple-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-600 uppercase tracking-wide mb-1">
                                        Presupuesto General de Ocio
                                    </h3>
                                    <p class="text-xl font-bold text-purple-700">
                                        $<?php echo number_format($leisureBudget, 0, ',', '.'); ?>
                                        <span class="text-sm font-medium text-gray-500">CLP</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="bg-white/80 backdrop-blur-lg rounded-3xl shadow-2xl border border-white/20 mb-8 overflow-hidden animate-slide-up">
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-8 py-6">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-plus-circle mr-3"></i>
                    Agregar Nuevo Producto
                </h2>
                <p class="text-blue-100 mt-1">Completa los datos del producto que deseas agregar</p>
            </div>

            <form id="add-product-form" class="p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-tag mr-2 text-blue-500"></i>
                            Nombre del Producto *
                        </label>
                        <input type="text" id="name" name="name" required
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="Ej: iPhone 13, MacBook Pro...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-money-bill-wave mr-2 text-green-500"></i>
                            Precio
                        </label>
                        <!-- Cambia el input a type="text" y agrega un id -->
                        <input type="text" name="price_formatted" id="price_formatted"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-green-100 focus:border-green-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="$0" inputmode="numeric" autocomplete="off">

                        <input type="hidden" name="price" id="price_raw">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-2">
                        <label for="necessity_level_add" class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-clipboard-check mr-2 text-indigo-500"></i>
                            Nivel de Necesidad
                        </label>
                        <select id="necessity_level_add" name="necessity_level"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                            <option value="" disabled selected>Selecciona un nivel</option>
                            <option value="5">5 - ¡Esencial!</option>
                            <option value="4">4 - Muy Necesario</option>
                            <option value="3" selected>3 - Necesario</option>
                            <option value="2">2 - Opcional</option>
                            <option value="1">1 - Capricho</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-coins mr-2 text-yellow-500"></i>
                            Moneda
                        </label>
                        <select id="currency" name="currency"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-yellow-100 focus:border-yellow-500 transition-all duration-300">
                            <option value="CLP">🇨🇱 CLP - Peso Chileno</option>
                            <option value="USD">🇺🇸 USD - Dólar</option>
                            <option value="EUR">🇪🇺 EUR - Euro</option>
                        </select>
                    </div>

                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-2 mb-8">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-align-left mr-2 text-gray-500"></i>
                            Descripción
                        </label>
                        <textarea name="description" id="description" rows="3"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-gray-100 focus:border-gray-500 transition-all duration-300 placeholder-gray-400 resize-none"
                            placeholder="Descripción opcional del producto..."></textarea>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-link mr-2 text-purple-500"></i>
                            URL del Producto
                        </label>
                        <input type="url" id="url" name="url"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="https://ejemplo.com/producto">
                    </div>
                </div>

                <button type="submit"
                    class="w-full md:w-auto bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex items-center justify-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Agregar Producto</span>
                </button>
            </form>
        </div>

        <form method="GET" action="">
            <div class="flex flex-col md:flex-row gap-4 mb-8">
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" id="search-input" name="search_name"
                            class="w-full pl-12 pr-4 py-3 bg-white/80 backdrop-blur-lg border border-white/20 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="Buscar productos..."
                            value="<?php echo htmlspecialchars($searchName); ?>">
                    </div>
                </div>
                <div class="flex gap-2">
                    <!--
                    <select id="category-select" name="category_id"
                        class="px-4 py-3 bg-white/80 backdrop-blur-lg border border-white/20 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                        <option value="0">Todas las categorías</option>
                        <?php
                        // Suponiendo que tienes una variable $categories con tus categorías
                        // Ejemplo: $categories = [['id' => 1, 'name' => 'Electrónica'], ['id' => 2, 'name' => 'Ropa']];
                        // Debes obtener estas categorías de tu base de datos si es necesario
                        $allCategories = []; // ¡Asegúrate de poblar esto desde tu DB si tienes categorías!
                        // Ejemplo de cómo podrías obtenerlas:
                        /*
                $stmtCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
                $allCategories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
                */

                        foreach ($allCategories as $cat) {
                            $selected = ($categoryId == $cat['id']) ? 'selected' : '';
                            echo "<option value=\"{$cat['id']}\" {$selected}>" . htmlspecialchars($cat['name']) . "</option>";
                        }
                        ?>
                    </select>
                    -->

                    <select id="sort-select" name="sort_by"
                        class="px-4 py-3 bg-white/80 backdrop-blur-lg border border-white/20 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                        <option value="necessity_level-desc" <?php echo ($sortBy === 'necessity_level-desc') ? 'selected' : ''; ?>>Prioridad (Necesidad)</option>

                        <option value="created_at-desc" <?php echo ($sortBy === 'created_at-desc') ? 'selected' : ''; ?>>Más Reciente</option>
                        <option value="name-asc" <?php echo ($sortBy === 'name-asc') ? 'selected' : ''; ?>>Nombre A-Z</option>
                        <option value="name-desc" <?php echo ($sortBy === 'name-desc') ? 'selected' : ''; ?>>Nombre Z-A</option>
                        <option value="price-asc" <?php echo ($sortBy === 'price-asc') ? 'selected' : ''; ?>>Precio Menor</option>
                        <option value="price-desc" <?php echo ($sortBy === 'price-desc') ? 'selected' : ''; ?>>Precio Mayor</option>
                    </select>

                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-filter mr-2"></i>Aplicar
                    </button>
                </div>
            </div>
        </form>



        <?php if (empty($products)): ?>
            <?php if (!empty($searchName) || $categoryId > 0): ?>
                <div class="text-center py-16">
                    <div class="w-32 h-32 mx-auto mb-6 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-4xl text-yellow-500"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-3">No se encontraron productos con los filtros aplicados.</h3>
                    <p class="text-gray-500 mb-6 max-w-md mx-auto">Intenta ajustar tu búsqueda o selecciona "Todas las categorías".</p>
                    <a href="?" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-undo mr-2"></i>
                        Borrar Filtros
                    </a>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="w-32 h-32 mx-auto mb-6 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                        <i class="fas fa-shopping-cart text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-gray-700 mb-3">No hay productos en tu lista</h3>
                    <p class="text-gray-500 mb-6 max-w-md mx-auto">Agrega tu primer producto usando el formulario de arriba para comenzar a gestionar tu lista de compras.</p>
                    <button onclick="document.querySelector('input[name=name]').focus()"
                        class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-plus mr-2"></i>
                        Agregar Primer Producto
                    </button>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                <?php foreach ($products as $product): ?>
                    <div class="product-card bg-white/80 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 overflow-hidden card-hover animate-fade-in" data-product-id="<?php echo $product['id']; ?>">
                        <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 overflow-hidden group">
                            <!-- Header con gradiente sutil -->
                            <div class="bg-gradient-to-br from-gray-50 to-white p-6 border-b border-gray-100">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1 mr-4">
                                        <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-gray-700 transition-colors">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </h3>

                                        <!-- Indicador de necesidad mejorado -->
                                        <div class="flex items-center gap-2">
                                            <?php
                                            $necessityLevel = $product['necessity_level'] ?? 0;
                                            $necessityText = [
                                                1 => 'Capricho',
                                                2 => 'Opcional',
                                                3 => 'Necesario',
                                                4 => 'Muy Necesario',
                                                5 => '¡Esencial!'
                                            ];

                                            // Configuración de colores y estilos por nivel
                                            $levelConfig = [
                                                1 => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'border' => 'border-red-200', 'icon' => 'fas fa-heart'],
                                                2 => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'border' => 'border-orange-200', 'icon' => 'fas fa-star-half-alt'],
                                                3 => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'icon' => 'fas fa-star'],
                                                4 => ['bg' => 'bg-green-50', 'text' => 'text-green-600', 'border' => 'border-green-200', 'icon' => 'fas fa-star'],
                                                5 => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'icon' => 'fas fa-bolt']
                                            ];

                                            $config = $levelConfig[$necessityLevel] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'border' => 'border-gray-200', 'icon' => 'fas fa-question'];
                                            ?>

                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold <?php echo $config['bg'] . ' ' . $config['text'] . ' ' . $config['border']; ?> border">
                                                <i class="<?php echo $config['icon']; ?> text-xs"></i>
                                                <?php echo $necessityText[$necessityLevel] ?? 'N/A'; ?>
                                            </span>

                                            <!-- Indicador visual de estrellas -->
                                            <div class="flex items-center gap-0.5">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star text-xs <?php echo $i <= $necessityLevel ? $config['text'] : 'text-gray-300'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Moneda destacada -->
                                    <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl text-white text-lg font-bold shadow-lg">
                                        <?php echo $product['currency']; ?>
                                    </div>
                                </div>

                                <!-- Descripción -->
                                <?php if (!empty($product['description'])): ?>
                                    <p class="text-gray-600 text-sm leading-relaxed line-clamp-2 mb-0">
                                        <?php echo htmlspecialchars($product['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Contenido principal -->
                            <div class="p-6">
                                <!-- Precio destacado -->
                                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-5 mb-6 border border-emerald-100">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-sm font-medium text-emerald-700 mb-1 block">Precio actual</span>
                                            <div class="text-3xl font-bold text-emerald-800" id="price-<?php echo $product['id']; ?>">
                                                $<?php echo number_format($product['price'], 0, ',', '.'); ?>
                                                <span class="text-lg font-medium text-emerald-600"><?php echo $product['currency']; ?></span>
                                            </div>
                                        </div>
                                        <div class="text-emerald-600">
                                            <i class="fas fa-chart-line text-2xl"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información adicional -->
                                <div class="space-y-3 mb-6">
                                    <!-- Fecha de actualización -->
                                    <div class="flex items-center text-sm text-gray-500">
                                        <div class="flex items-center justify-center w-8 h-8 bg-gray-100 rounded-lg mr-3">
                                            <i class="fas fa-clock text-gray-400"></i>
                                        </div>
                                        <span>Actualizado: <?php echo date('d/m/Y H:i', strtotime($product['updated_at'])); ?></span>
                                    </div>

                                    <!-- Enlace al producto -->
                                    <?php if (!empty($product['product_url'])): ?>
                                        <div class="flex items-center">
                                            <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-lg mr-3">
                                                <i class="fas fa-external-link-alt text-blue-500 text-sm"></i>
                                            </div>
                                            <a href="<?php echo htmlspecialchars($product['product_url']); ?>" target="_blank"
                                                class="text-blue-600 hover:text-blue-800 font-medium text-sm transition-colors hover:underline">
                                                Ver producto en tienda
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Botones de acción mejorados -->
                                <div class="flex gap-1">
                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>"
                                        class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-3 px-4 rounded-xl transition-all duration-300 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                                        <i class="fas fa-edit"></i>
                                        <span class="hidden sm:inline">Editar</span>
                                    </a>
                                    <button class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 flex items-center justify-center space-x-2 mark-purchased-btn"
                                        data-product-id="<?php echo $product['id']; ?>"
                                        data-product-price="<?php echo htmlspecialchars($product['price']); ?>"
                                        data-product-currency="<?php echo htmlspecialchars($product['currency']); ?>">
                                        <i class="fas fa-check"></i> Comprado
                                    </button>
                                    <button class="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-medium py-3 px-4 rounded-xl transition-all duration-300 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 delete-product-btn"
                                        data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                        <span class="hidden sm:inline">Eliminar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>



    <div id="loading-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 max-w-sm mx-4 text-center animate-scale-in">
            <div class="w-16 h-16 mx-auto mb-4 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Procesando...</h3>
            <p class="text-gray-600">Por favor espera un momento</p>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const priceFormattedInput = document.getElementById('price_formatted'); // Tu input visible
            const priceRawInput = document.getElementById('price_raw'); // Tu input oculto para la BD
            const form = priceFormattedInput.closest('form'); // Obtén el formulario padre
            const addProductForm = document.getElementById('add-product-form');
            const addNecessityLevelSelect = document.getElementById('necessity_level_add'); // Nuevo

            document.querySelectorAll('.mark-purchased-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const productPrice = this.dataset.productPrice; // Precio original
                    const productCurrency = this.dataset.productCurrency; // Moneda original

                    // Opcional: Pedir al usuario que confirme o ingrese el precio real pagado
                    // let actualPrice = prompt('¿Cuál fue el precio final pagado por este producto?', productPrice);
                    // if (actualPrice === null || actualPrice.trim() === '') {
                    //     return; // Usuario canceló o no ingresó nada
                    // }
                    // const finalPriceToSend = cleanNumber(actualPrice); // Limpiar para enviar al backend

                    // Usaremos el precio actual en la DB por simplicidad, o el que el usuario haya guardado.
                    const finalPriceToSend = cleanNumber(productPrice);


                    if (confirm('¿Estás seguro de que quieres marcar este producto como comprado? Se moverá a tu historial.')) {
                        const formData = new FormData();
                        formData.append('action', 'mark_purchased');
                        formData.append('product_id', productId);
                        formData.append('purchased_price', finalPriceToSend); // Envía el precio limpio
                        formData.append('purchased_currency', productCurrency);

                        fetch('index.php', { // O 'api.php' si lo separaste
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    showAlert(data.message, 'success');
                                    // Eliminar la tarjeta del DOM o recargar la lista
                                    // this.closest('.product-card').remove(); // Eliminar visualmente
                                    location.reload(); // Recarga simple para actualizar la lista
                                } else {
                                    showAlert(data.message, 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error al marcar como comprado:', error);
                                showAlert('Error de conexión al marcar como comprado.', 'error');
                            });
                    }
                });
            });
            // Función para limpiar el valor de formato y obtener solo los dígitos
            function cleanNumber(formattedValue) {
                return formattedValue.replace(/[^0-9]/g, ''); // Elimina todo lo que no sea dígito
            }

            // Función para formatear el número como CLP (la que ya tenías)
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

            // Escuchar el evento 'input' en el campo visible
            priceFormattedInput.addEventListener('input', function(e) {
                const originalLength = e.target.value.length;
                const cursorPosition = e.target.selectionStart;

                // Formatear el valor para mostrarlo
                let formattedValue = formatCurrencyCLP(e.target.value);
                e.target.value = formattedValue;

                // Intentar restablecer la posición del cursor
                const newLength = e.target.value.length;
                const diff = newLength - originalLength;
                if (cursorPosition + diff >= 0) {
                    e.target.setSelectionRange(cursorPosition + diff, cursorPosition + diff);
                }

                // ¡IMPORTANTE! Actualizar el campo oculto con el valor limpio en tiempo real
                // Esto es bueno si vas a usar AJAX, si no, el evento 'submit' es suficiente.
                priceRawInput.value = cleanNumber(priceFormattedInput.value);
            });

            // Opcional: Formatear el valor inicial si el campo ya tiene un valor al cargar la página
            if (priceFormattedInput.value) {
                priceFormattedInput.value = formatCurrencyCLP(priceFormattedInput.value);
                priceRawInput.value = cleanNumber(priceFormattedInput.value); // También el campo oculto
            }


            // --- Parte nueva: Manejar el envío del formulario ---
            if (form) { // Asegúrate de que el input esté dentro de un formulario
                form.addEventListener('submit', function(e) {
                    // Antes de que el formulario se envíe, asegúrate de que el campo 'price_raw'
                    // tenga el valor numérico limpio final.
                    priceRawInput.value = cleanNumber(priceFormattedInput.value);

                    // Si por alguna razón el campo formateado está vacío pero no quieres enviar un 0,
                    // puedes añadir una validación aquí.
                    if (priceRawInput.value === '') {
                        // Si el campo está vacío, podrías evitar el envío o establecer un valor por defecto.
                        // e.preventDefault(); // Evita el envío si es un campo requerido
                        // priceRawInput.value = '0'; // O establecerlo a 0
                    }
                });
            }
            if (addProductForm) {
                addProductForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    // Si usas un campo oculto 'action' en el formulario:
                    // formData.append('action', 'add_product');

                    // === Validación adicional para el nivel de necesidad ===
                    const selectedNecessityLevel = addNecessityLevelSelect.value;
                    if (selectedNecessityLevel === '' || parseInt(selectedNecessityLevel) < 1 || parseInt(selectedNecessityLevel) > 5) {
                        showAlert('Por favor, selecciona un nivel de necesidad para el producto.', 'error');
                        return; // Detiene el envío del formulario
                    }
                    // ======================================================

                });
            }
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