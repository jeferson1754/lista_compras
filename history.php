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

                <!-- Estadísticas rápidas -->
                <div class="hidden lg:flex space-x-4">
                    <div class="text-center bg-white rounded-xl p-4 shadow-lg border border-gray-100">
                        <div class="text-2xl font-bold text-orange-600"><?php echo count($purchaseRecords ?? []); ?></div>
                        <div class="text-xs text-gray-500 uppercase tracking-wide">Compras Total</div>
                    </div>
                    <div class="text-center bg-white rounded-xl p-4 shadow-lg border border-gray-100">
                        <div class="text-2xl font-bold text-green-600">
                            $<?php
                                $totalSpent = 0;
                                if (!empty($purchaseRecords)) {
                                    foreach ($purchaseRecords as $item) {
                                        $totalSpent += $item['purchased_price'];
                                    }
                                }
                                echo number_format($totalSpent, 0, ',', '.');
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

                    <div class="space-y-3">
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
                                        <span class="font-medium text-gray-800"><?php echo htmlspecialchars($analysis['purchase_reason']); ?></span>
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

                <!-- Filtros y controles -->
                <div class="flex items-center space-x-3">
                    <button class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-xl transition-colors">
                        <i class="fas fa-filter mr-2"></i>
                        Filtrar
                    </button>
                    <button class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white text-sm font-medium rounded-xl transition-all shadow-lg">
                        <i class="fas fa-download mr-2"></i>
                        Exportar
                    </button>
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
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <button class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                                    <i class="fas fa-eye mr-2"></i>
                                    Ver detalles
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>