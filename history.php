<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Compras - Gestor de Precios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

<body>


    <div class="container mt-8 max-w-7xl mx-auto px-4">
        <!-- Header mejorado -->
        <div class="mb-6">
            <a href="index.php" class="text-indigo-600 hover:text-indigo-800 font-semibold transition-colors flex items-center gap-2 w-fit">
                <i class="fas fa-arrow-left"></i>
                <span>Volver a mi Lista</span>
            </a>
        </div>
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2 flex items-center">
                        <div class="flex items-center justify-center w-14 h-14 bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl mr-4 shadow-lg">
                            <i class="fas fa-history text-white text-xl"></i>
                        </div>
                        <span class="bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">
                            Historial de Compras
                        </span>
                    </h1>
                    <p class="text-gray-600 text-lg ml-18">Analiza tus patrones de compra y toma mejores decisiones financieras</p>
                </div>

                <?php
                $total_items_comprados = 0;
                $total_gastado = 0.00;

                try {

                    $pdo = getDBConnection();
                    // La consulta SQL que nos proporcionaste
                    $sql = "SELECT COUNT(*) AS total_items_comprados, SUM(purchased_price) AS total_gastado FROM purchase_history;";

                    // Preparamos y ejecutamos la consulta
                    $stmt = $pdo->query($sql);

                    // Obtenemos la única fila de resultados como un array asociativo
                    $result = $stmt->fetch();

                    // Verificamos si obtuvimos un resultado para evitar errores
                    if ($result) {
                        // Asignamos cada valor a su variable correspondiente
                        $total_items_comprados = (int) $result['total_items_comprados'];
                        $total_gastado = (float) $result['total_gastado'];
                    }
                } catch (\PDOException $e) {
                    // Manejo de errores en caso de que la consulta falle
                    die("Error al ejecutar la consulta: " . $e->getMessage());
                }

                ?>
                <!-- Estadísticas rápidas -->
                <div class="hidden lg:flex space-x-4">
                    <div class="text-center bg-white rounded-xl p-4 shadow-lg border border-gray-100">
                        <div class="text-2xl font-bold text-orange-600"><?php echo $total_items_comprados; ?></div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Compras Total</div>
                    </div>
                    <div class="text-center bg-white rounded-xl p-4 shadow-lg border border-gray-100">
                        <div class="text-2xl font-bold text-green-600">
                            $<?php
                                echo number_format($total_gastado, 0, ',', '.');
                                ?>
                        </div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Gasto Total</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de análisis mejorado -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border border-gray-100">
            <div class="flex items-center mb-6">
                <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl mr-4 shadow-lg">
                    <i class="fas fa-chart-pie text-white"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">Análisis Inteligente de Compras</h3>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Motivos de compra -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-100">
                    <div class="flex items-center mb-4">
                        <div class="flex items-center justify-center w-10 h-10 bg-blue-500 rounded-lg mr-3">
                            <i class="fas fa-brain text-white text-sm"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800">Motivos de Compra Recurrentes</h4>
                    </div>

                    <div class="space-y-3 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                        <?php
                        $reasonsAnalysis = getPurchaseReasonsAnalysis(getDBConnection());
                        if (empty($reasonsAnalysis)): ?>
                            <div class="flex items-center justify-center py-8 text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-inbox text-3xl mb-3 text-gray-300"></i>
                                    <p>No hay datos de motivos de compra disponibles</p>
                                    <p class="text-sm">Realiza más compras para ver análisis</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $totalReasons = array_sum(array_column($reasonsAnalysis, 'count'));
                            foreach ($reasonsAnalysis as $analysis):
                                $percentage = ($analysis['count'] / $totalReasons) * 100;
                            ?>
                                <div class="bg-white rounded-xl p-4 shadow-sm border border-blue-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-medium text-gray-800">
                                            <?php echo htmlspecialchars($analysis['purchase_reason']); ?>
                                        </span>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-blue-600"><?php echo $analysis['count']; ?></span>
                                            <span class="text-xs text-gray-500">compras</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-blue-100 rounded-full h-2">
                                        <div class="bg-gradient-to-r from-blue-400 to-blue-600 h-2 rounded-full transition-all duration-500"
                                            style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1"><?php echo number_format($percentage, 1); ?>% del total</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Estilos para personalizar el scrollbar -->
                    <style>
                        .custom-scrollbar::-webkit-scrollbar {
                            width: 8px;
                        }

                        .custom-scrollbar::-webkit-scrollbar-thumb {
                            background: linear-gradient(to bottom, #60a5fa, #3b82f6);
                            border-radius: 9999px;
                        }

                        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                            background: linear-gradient(to bottom, #2563eb, #1d4ed8);
                        }

                        .custom-scrollbar::-webkit-scrollbar-track {
                            background: #f1f5f9;
                            border-radius: 9999px;
                        }
                    </style>

                </div>

                <!-- Nivel de necesidad -->
                <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-2xl p-6 border border-emerald-100">
                    <div class="flex items-center mb-4">
                        <div class="flex items-center justify-center w-10 h-10 bg-emerald-500 rounded-lg mr-3">
                            <i class="fas fa-layer-group text-white text-sm"></i>
                        </div>
                        <h4 class="text-lg font-bold text-gray-800">Nivel de Necesidad al Comprar</h4>
                    </div>

                    <div class="space-y-3">
                        <?php
                        $necessityAnalysis = getNecessityLevelAnalysis(getDBConnection());
                        $necessityTextMap = [
                            1 => 'Capricho',
                            2 => 'Opcional',
                            3 => 'Necesario',
                            4 => 'Muy Necesario',
                            5 => '¡Esencial!'
                        ];
                        $necessityColors = [
                            1 => ['bg' => 'from-red-400 to-red-600', 'text' => 'text-red-700', 'icon' => 'fas fa-heart'],
                            2 => ['bg' => 'from-orange-400 to-orange-600', 'text' => 'text-orange-700', 'icon' => 'fas fa-star-half-alt'],
                            3 => ['bg' => 'from-yellow-400 to-yellow-600', 'text' => 'text-yellow-700', 'icon' => 'fas fa-star'],
                            4 => ['bg' => 'from-green-400 to-green-600', 'text' => 'text-green-700', 'icon' => 'fas fa-star'],
                            5 => ['bg' => 'from-purple-400 to-purple-600', 'text' => 'text-purple-700', 'icon' => 'fas fa-bolt']
                        ];

                        if (empty($necessityAnalysis)): ?>
                            <div class="flex items-center justify-center py-8 text-gray-500">
                                <div class="text-center">
                                    <i class="fas fa-inbox text-3xl mb-3 text-gray-300"></i>
                                    <p>No hay datos de nivel de necesidad disponibles</p>
                                    <p class="text-sm">Realiza más compras para ver análisis</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $totalNecessity = array_sum(array_column($necessityAnalysis, 'count'));
                            foreach ($necessityAnalysis as $analysis):
                                $percentage = ($analysis['count'] / $totalNecessity) * 100;
                                $level = $analysis['necessity_level'];
                                $config = $necessityColors[$level] ?? ['bg' => 'from-gray-400 to-gray-600', 'text' => 'text-gray-700', 'icon' => 'fas fa-question'];
                            ?>
                                <div class="bg-white rounded-xl p-4 shadow-sm border border-emerald-200">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <i class="<?php echo $config['icon']; ?> <?php echo $config['text']; ?> mr-2"></i>
                                            <span class="font-medium text-gray-800"><?php echo $necessityTextMap[$level] ?? 'N/A'; ?></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold <?php echo $config['text']; ?>"><?php echo $analysis['count']; ?></span>
                                            <span class="text-xs text-gray-500">compras</span>
                                        </div>
                                    </div>
                                    <div class="w-full bg-emerald-100 rounded-full h-2">
                                        <div class="bg-gradient-to-r <?php echo $config['bg']; ?> h-2 rounded-full transition-all duration-500"
                                            style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-1"><?php echo number_format($percentage, 1); ?>% del total</div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de compras mejorado -->
        <div class="bg-white rounded-3xl shadow-xl p-8 border border-gray-100">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl mr-4 shadow-lg">
                        <i class="fas fa-shopping-bag text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">Tus Compras Realizadas</h3>
                </div>


            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php
                $purchaseRecords = getPurchaseHistory(getDBConnection());
                if (empty($purchaseRecords)): ?>
                    <div class="col-span-full">
                        <div class="flex flex-col items-center justify-center py-16 text-gray-500">
                            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <i class="fas fa-shopping-cart text-3xl text-gray-300"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-700 mb-2">No hay compras registradas</h4>
                            <p class="text-center max-w-md">Cuando realices tu primera compra, aparecerá aquí junto con análisis detallados de tus patrones de gasto.</p>
                            <button class="mt-4 inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium rounded-xl transition-all shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Registrar primera compra
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($purchaseRecords as $item):
                        $necessityLevel = $item['necessity_level'];
                        $necessityConfig = $necessityColors[$necessityLevel] ?? ['bg' => 'from-gray-400 to-gray-600', 'text' => 'text-gray-700', 'icon' => 'fas fa-question'];
                    ?>
                        <div class="bg-gradient-to-br from-white to-gray-50 rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all duration-300 border border-gray-100 group">
                            <!-- Header de la tarjeta -->
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h4 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-gray-700 transition-colors">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </h4>
                                    <p class="text-sm text-gray-600 line-clamp-2 leading-relaxed">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </p>
                                </div>

                                <!-- Indicador de necesidad -->
                                <div class="ml-3">
                                    <div class="inline-flex items-center px-2.5 py-1 bg-white rounded-full text-xs font-semibold <?php echo $necessityConfig['text']; ?> border shadow-sm">
                                        <i class="<?php echo $necessityConfig['icon']; ?> mr-1"></i>
                                        <?php echo $necessityTextMap[$necessityLevel] ?? 'N/A'; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Precio destacado -->
                            <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-4 mb-4 border border-green-100">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs text-green-700 font-medium mb-1">Precio de compra</p>
                                        <p class="text-xl font-bold text-green-800">
                                            $<?php echo number_format($item['purchased_price'], 0, ',', '.'); ?>
                                            <span class="text-sm font-medium text-green-600"><?php echo $item['purchased_currency']; ?></span>
                                        </p>
                                    </div>
                                    <div class="text-green-600">
                                        <i class="fas fa-receipt text-lg"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalles adicionales -->
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center text-gray-500">
                                    <i class="fas fa-calendar-alt w-4 mr-2"></i>
                                    <span><?php echo date('d/m/Y H:i', strtotime($item['purchased_at'])); ?></span>
                                </div>

                                <?php if (!empty($item['purchase_reason'])): ?>
                                    <div class="flex items-start text-gray-500">
                                        <i class="fas fa-comment-alt w-4 mr-2 mt-0.5"></i>
                                        <span class="flex-1"><?php echo htmlspecialchars($item['purchase_reason']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Acciones -->
                            <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-3">

                                <button
                                    class="open-details-modal-btn w-full inline-flex items-center justify-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors"
                                    data-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                    data-price="$<?php echo number_format($item['purchased_price'], 0, ',', '.'); ?> <?php echo htmlspecialchars($item['purchased_currency']); ?>"
                                    data-necessity-text="<?php echo htmlspecialchars($necessityTextMap[$necessityLevel] ?? 'N/A'); ?>"
                                    data-necessity-icon="<?php echo htmlspecialchars($necessityConfig['icon']); ?>"
                                    data-necessity-class="<?php echo htmlspecialchars($necessityConfig['text']); ?>"
                                    data-date="<?php echo date('d/m/Y \a \l\a\s H:i', strtotime($item['purchased_at'])); ?>"
                                    data-reason="<?php echo htmlspecialchars($item['purchase_reason']); ?>">
                                    <i class="fas fa-eye mr-2"></i>
                                    Detalles
                                </button>

                                <button
                                    class="rebuy-btn w-full inline-flex items-center justify-center px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold rounded-lg transition-all duration-300 transform hover:scale-105"
                                    data-history-id="<?php echo htmlspecialchars($item['id']); ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                    data-price="<?php echo htmlspecialchars($item['purchased_price']); ?>"
                                    data-currency="<?php echo htmlspecialchars($item['purchased_currency']); ?>"
                                    data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                    data-url="<?php echo htmlspecialchars($item['product_url']); ?>">
                                    <i class="fas fa-cart-plus mr-2"></i>
                                    <span>Comprar</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="details-modal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50 hidden transition-opacity duration-300">

        <div id="modal-content" class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-auto transform transition-transform duration-300 scale-95 max-h-[90vh] overflow-y-auto">
            <!-- max-h-[90vh] limita la altura del modal al 90% de la ventana -->
            <!-- overflow-y-auto permite el scroll vertical si el contenido excede esa altura -->

            <div class="flex justify-between items-center p-5 border-b border-gray-200">
                <h3 id="modal-title" class="text-xl font-bold text-gray-800">Detalles de la Compra</h3>
                <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <p id="modal-description" class="text-gray-600"></p>

                <div class="bg-green-50 rounded-xl p-4 border border-green-100 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-green-700 font-medium">Precio de compra</p>
                        <p id="modal-price" class="text-2xl font-bold text-green-800"></p>
                    </div>
                    <div id="modal-necessity" class="text-sm font-semibold px-3 py-1 rounded-full flex items-center gap-2">
                        <i id="modal-necessity-icon"></i>
                        <span id="modal-necessity-text"></span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="text-md font-semibold text-gray-700 mb-2">📈 Historial de Precios</h4>

                    <div id="price-history-list" class="space-y-2 text-sm max-h-40 overflow-y-auto pr-2">
                        <p id="price-list-info" class="text-gray-500">Cargando historial...</p>
                    </div>

                    <div id="priceHistoryChart" class="mt-4" style="display: none;">
                        <canvas id="priceTrendCanvas"></canvas>
                    </div>

                </div>

                <div class="bg-gray-50/70 border border-gray-200 rounded-xl p-4">
                    <dl class="space-y-3">

                        <div class="flex items-center">
                            <dt class="flex items-center w-28">
                                <i class="fas fa-calendar-alt w-5 mr-2 text-gray-400"></i>
                                <span class="font-semibold text-gray-600">Fecha:</span>
                            </dt>
                            <dd id="modal-date" class="text-gray-800"></dd>
                        </div>

                        <div class="flex items-start">
                            <dt class="flex items-start w-28 pt-0.5">
                                <i class="fas fa-comment-alt w-5 mr-2 text-gray-400"></i>
                                <span class="font-semibold text-gray-600">Motivo:</span>
                            </dt>
                            <dd id="modal-reason" class="flex-1 text-gray-800"></dd>
                        </div>

                    </dl>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos del Modal
            const modal = document.getElementById('details-modal');
            const modalContent = document.getElementById('modal-content');
            const closeModalBtn = document.getElementById('close-modal-btn');

            // Todos los botones que abren el modal
            const openModalBtns = document.querySelectorAll('.open-details-modal-btn');
            const priceHistoryList = document.getElementById('price-history-list');


            // Función para abrir el modal
            const openModal = async (data) => {
                // Llenar el modal con los datos del botón presionado
                document.getElementById('modal-title').textContent = data.name;
                let description = data.description || "";
                let maxLength = 250;

                if (description.length > maxLength) {
                    description = description.substring(0, maxLength) + "...";
                }

                document.getElementById('modal-description').textContent = description;
                document.getElementById('modal-price').textContent = data.price;
                document.getElementById('modal-date').textContent = data.date;
                document.getElementById('modal-reason').textContent = data.reason;

                // Rellenar la "píldora" de necesidad
                const necessityPill = document.getElementById('modal-necessity');
                const necessityIcon = document.getElementById('modal-necessity-icon');
                const necessityText = document.getElementById('modal-necessity-text');

                necessityText.textContent = data.necessityText;
                necessityIcon.className = data.necessityIcon; // Reemplaza todas las clases del icono

                // Limpiamos clases de color previas y añadimos la nueva
                necessityPill.className = 'text-sm font-semibold px-3 py-1 rounded-full flex items-center gap-2 ' + data.necessityClass;

                // Mostrar el modal con una transición suave
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.style.opacity = '1';
                    modalContent.style.transform = 'scale(1)';
                }, 10); // Un pequeño retardo para que la transición CSS se active

                // --- Lógica para la lista de precios ---
                // 1. Mostrar estado de carga
                priceHistoryList.innerHTML = '<p class="text-gray-500">Cargando historial...</p>';

                try {
                    // 1️⃣ Petición al servidor para obtener los datos
                    const response = await fetch(`get_price_history.php?id=${(data.id)}`);
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor.');
                    }

                    const historyData = await response.json();

                    if (!historyData || historyData.length === 0) {
                        priceHistoryList.innerHTML = '<p class="text-gray-500">No hay historial de precios para este producto.</p>';
                        document.getElementById('priceHistoryChart').style.display = 'none';
                        return;
                    }

                    // 2️⃣ Construir HTML de la lista
                    let listHtml = '';
                    const labels = [];
                    const prices = [];

                    for (let i = 0; i < historyData.length; i++) {
                        const item = historyData[i];
                        const currentDate = new Date(item.purchased_at).toLocaleDateString('es-CL');
                        const currentPrice = parseFloat(item.price);
                        const formattedPrice = '$' + new Intl.NumberFormat('es-CL').format(currentPrice);

                        labels.unshift(currentDate); // invertimos para que sea cronológico
                        prices.unshift(currentPrice);

                        let comparisonHtml = '';

                        if (i < historyData.length - 1) {
                            const previousPurchasePrice = parseFloat(historyData[i + 1].price);
                            const difference = currentPrice - previousPurchasePrice;

                            if (difference > 0) {
                                comparisonHtml = `<span class="flex items-center text-red-500 font-semibold"><i class="fas fa-arrow-up mr-1"></i> Sube</span>`;
                            } else if (difference < 0) {
                                comparisonHtml = `<span class="flex items-center text-green-600 font-semibold"><i class="fas fa-arrow-down mr-1"></i> Baja</span>`;
                            } else {
                                comparisonHtml = `<span class="flex items-center text-gray-500 font-semibold"><i class="fas fa-equals mr-1"></i> Mantiene</span>`;
                            }
                        } else {
                            comparisonHtml = `<span class="text-xs text-gray-500 font-medium">(Primera compra)</span>`;
                        }

                        listHtml += `
            <div class="flex justify-between items-center p-2 rounded-lg ${i % 2 === 0 ? 'bg-gray-50' : 'bg-white'}">
                <div class="font-semibold text-gray-800">${formattedPrice}</div>
                <div class="text-gray-500">${currentDate}</div>
                <div>${comparisonHtml}</div>
            </div>
        `;
                    }

                    priceHistoryList.innerHTML = listHtml;
                    // Preparar arrays de colores dinámicos
                    const borderColors = [];
                    const backgroundColors = [];

                    for (let i = 0; i < prices.length; i++) {
                        if (i === 0) {
                            // Primera compra
                            backgroundColors.push('rgba(107, 114, 128, 0.2)');
                        } else {
                            const diff = prices[i] - prices[i - 1];
                            if (diff > 0) {
                                borderColors.push('#ef4444'); // rojo
                                backgroundColors.push('rgba(239, 68, 68, 0.2)');
                            } else if (diff < 0) {
                                borderColors.push('#16a34a'); // verde
                                backgroundColors.push('rgba(22, 163, 74, 0.2)');
                            } else {
                                borderColors.push('#6b7280'); // gris
                                backgroundColors.push('rgba(107, 114, 128, 0.2)');
                            }
                        }
                    }

                    // Mostrar gráfico de evolución
                    const chartContainer = document.getElementById('priceHistoryChart');
                    chartContainer.style.display = 'block';
                    chartContainer.innerHTML = '<canvas id="priceTrendCanvas"></canvas>';

                    const ctx = document.getElementById('priceTrendCanvas').getContext('2d');

                    // Destruir gráfico previo si existe
                    if (window.priceTrendChart) {
                        window.priceTrendChart.destroy();
                    }

                    window.priceTrendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Precio del producto',
                                data: prices,
                                borderColor: borderColors, // Colores dinámicos por punto
                                backgroundColor: backgroundColors, // Colores dinámicos por punto
                                tension: 0.3,
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Fecha'
                                    }
                                },
                                y: {
                                    title: {
                                        display: true,
                                        text: 'Precio (CLP)'
                                    },
                                    beginAtZero: false
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return '$' + new Intl.NumberFormat('es-CL').format(context.parsed.y);
                                        }
                                    }
                                }
                            }
                        }
                    });


                } catch (error) {
                    priceHistoryList.innerHTML = '<p class="text-red-500">No se pudo cargar el historial.</p>';
                    console.error('Error fetching price history:', error);
                }

            };

            // Función para cerrar el modal
            const closeModal = () => {
                modal.style.opacity = '0';
                modalContent.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300); // Espera a que termine la transición de CSS
            };

            // Asignar evento a cada botón de "Ver detalles"
            openModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Recolectar todos los datos desde los atributos data-*
                    const data = {
                        id: this.dataset.id,
                        name: this.dataset.name,
                        description: this.dataset.description,
                        price: this.dataset.price,
                        necessityText: this.dataset.necessityText,
                        necessityIcon: this.dataset.necessityIcon,
                        necessityClass: this.dataset.necessityClass,
                        date: this.dataset.date,
                        reason: this.dataset.reason,
                    };
                    openModal(data);
                });
            });

            // Eventos para cerrar el modal
            closeModalBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(event) {
                // Cierra el modal solo si se hace clic en el fondo oscuro
                if (event.target === modal) {
                    closeModal();
                }
            });

            // Opcional: Cerrar el modal con la tecla 'Escape'
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });


            // --- LÓGICA PARA EL BOTÓN "VOLVER A COMPRAR" ---
            const rebuyButtons = document.querySelectorAll('.rebuy-btn');

            rebuyButtons.forEach(btn => {
                btn.addEventListener('click', function(event) {
                    event.preventDefault();
                    const button = this;
                    const originalText = button.querySelector('span').innerHTML;
                    const icon = button.querySelector('i');

                    // Estado de "Cargando"
                    button.disabled = true;
                    button.querySelector('span').textContent = 'Añadiendo...';

                    // 1. Recolectar los datos del botón
                    const formData = new FormData();
                    formData.append('name', button.dataset.name);
                    formData.append('history_id', button.dataset.historyId);
                    formData.append('price', button.dataset.price);
                    formData.append('currency', button.dataset.currency);
                    formData.append('description', button.dataset.description);
                    formData.append('url', button.dataset.url);

                    // 2. Enviar los datos al servidor con fetch
                    fetch('rebuy_product.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // 3. Éxito: Cambiar el estado del botón permanentemente
                                button.querySelector('span').textContent = '¡Añadido!';
                                icon.className = 'fas fa-check mr-2';
                                button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                                button.classList.add('bg-green-500', 'cursor-not-allowed');
                            } else {
                                // 4. Error: Revertir el botón y mostrar alerta
                                button.disabled = false;
                                button.querySelector('span').textContent = originalText;
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            // Error de red
                            console.error('Error:', error);
                            button.disabled = false;
                            button.querySelector('span').textContent = originalText;
                            alert('Ocurrió un error de conexión.');
                        });
                });
            });

        });
    </script>
</body>

</html>