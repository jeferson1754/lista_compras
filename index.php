<?php
require_once 'config.php'; // Asegúrate de que config.php esté en el mismo directorio o ajusta la ruta según sea necesario
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT * FROM list_products ORDER BY created_at DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular valor total y precio promedio en CLP
$totalValue = 0;
foreach ($products as $p) {
    $price = floatval($p['price']);
    if ($p['currency'] === 'USD') $price *= 800;
    if ($p['currency'] === 'EUR') $price *= 900;
    $totalValue += $price;
}
$totalProducts = count($products);
$avgPrice = $totalProducts > 0 ? $totalValue / $totalProducts : 0;
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
                        <span id="product-count">0</span> productos
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div id="alert-container" class="mb-6"></div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white/70 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-lg animate-fade-in">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-400 to-green-600 rounded-xl flex items-center justify-center">
                        <i class="fas fa-shopping-bag text-white text-lg"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-600">Total Productos</p>
                        <p class="text-2xl font-bold text-gray-800" id="total-products">0</p>
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
                        <p class="text-2xl font-bold text-gray-800" id="total-value">$0</p>
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
                        <p class="text-2xl font-bold text-gray-800" id="avg-price">$0</p>
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
                        <p class="text-lg font-semibold text-gray-800" id="last-update">Ahora</p>
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
                        <input type="text" name="name" required
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="Ej: iPhone 13, MacBook Pro...">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-money-bill-wave mr-2 text-green-500"></i>
                            Precio
                        </label>
                        <!-- Cambia el input a type="text" y agrega un id -->
                        <input type="text" name="price" id="price-input"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-green-100 focus:border-green-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="$0" inputmode="numeric" autocomplete="off">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-coins mr-2 text-yellow-500"></i>
                            Moneda
                        </label>
                        <select name="currency"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-yellow-100 focus:border-yellow-500 transition-all duration-300">
                            <option value="CLP">🇨🇱 CLP - Peso Chileno</option>
                            <option value="USD">🇺🇸 USD - Dólar</option>
                            <option value="EUR">🇪🇺 EUR - Euro</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">
                            <i class="fas fa-link mr-2 text-purple-500"></i>
                            URL del Producto
                        </label>
                        <input type="url" name="url"
                            class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-purple-100 focus:border-purple-500 transition-all duration-300 placeholder-gray-400"
                            placeholder="https://ejemplo.com/producto">
                    </div>
                </div>

                <div class="space-y-2 mb-8">
                    <label class="block text-sm font-semibold text-gray-700">
                        <i class="fas fa-align-left mr-2 text-gray-500"></i>
                        Descripción
                    </label>
                    <textarea name="description" rows="3"
                        class="w-full px-4 py-3 bg-white/80 border border-gray-200 rounded-xl focus:ring-4 focus:ring-gray-100 focus:border-gray-500 transition-all duration-300 placeholder-gray-400 resize-none"
                        placeholder="Descripción opcional del producto..."></textarea>
                </div>

                <button type="submit"
                    class="w-full md:w-auto bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-semibold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-lg flex items-center justify-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Agregar Producto</span>
                </button>
            </form>
        </div>

        <div class="flex flex-col md:flex-row gap-4 mb-8">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="search-input"
                        class="w-full pl-12 pr-4 py-3 bg-white/80 backdrop-blur-lg border border-white/20 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300 placeholder-gray-400"
                        placeholder="Buscar productos...">
                </div>
            </div>
            <div class="flex gap-2">
                <select id="sort-select"
                    class="px-4 py-3 bg-white/80 backdrop-blur-lg border border-white/20 rounded-xl focus:ring-4 focus:ring-blue-100 focus:border-blue-500 transition-all duration-300">
                    <option value="name-asc">Nombre A-Z</option>
                    <option value="name-desc">Nombre Z-A</option>
                    <option value="price-asc">Precio Menor</option>
                    <option value="price-desc">Precio Mayor</option>
                    <option value="date-desc">Más Reciente</option>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-boxes mr-3 text-blue-500"></i>
                Mis Productos
                <span class="ml-3 text-lg font-normal text-gray-500" id="products-count">(0)</span>
            </h2>
        </div>

        <div id="products-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
        </div>

        <div id="empty-state" class="text-center py-16 hidden">
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
    </div>

    <div id="loading-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl p-8 max-w-sm mx-4 text-center animate-scale-in">
            <div class="w-16 h-16 mx-auto mb-4 border-4 border-blue-200 border-t-blue-500 rounded-full animate-spin"></div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Procesando...</h3>
            <p class="text-gray-600">Por favor espera un momento</p>
        </div>
    </div>

    <script>
        let products = <?php echo json_encode($products); ?>;
        let totalValue = <?php echo json_encode($totalValue); ?>;
        let avgPrice = <?php echo json_encode($avgPrice); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            const priceInput = document.getElementById('price-input');
            if (priceInput) {
                priceInput.addEventListener('input', function(e) {
                    // Elimina todo lo que no sea número
                    let value = this.value.replace(/\D/g, '');
                    // Convierte a número y formatea como CLP
                    if (value) {
                        value = parseInt(value, 10);
                        this.value = value.toLocaleString('es-CL', {
                            style: 'currency',
                            currency: 'CLP',
                            minimumFractionDigits: 0
                        });
                    } else {
                        this.value = '';
                    }
                });

                // Al enviar el formulario, convierte el valor a número sin formato
                const form = priceInput.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        priceInput.value = priceInput.value.replace(/\D/g, '');
                    });
                }
            }
        });

        // Utility Functions
        function formatPrice(price, currency) {
            const formatter = new Intl.NumberFormat('es-CL', {
                style: 'currency',
                currency: currency
            });
            return formatter.format(price);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-CL', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function getCurrencyFlag(currency) {
            const flags = {
                'CLP': '🇨🇱',
                'USD': '🇺🇸',
                'EUR': '🇪🇺'
            };
            return flags[currency] || '💰';
        }

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'from-green-500 to-green-600' : 'from-red-500 to-red-600';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';

            const alert = document.createElement('div');
            alert.className = `bg-gradient-to-r ${alertClass} text-white px-6 py-4 rounded-xl shadow-lg animate-slide-up flex items-center space-x-3`;
            alert.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span class="font-medium">${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-auto hover:bg-white/20 rounded-lg p-1">
                    <i class="fas fa-times"></i>
                </button>
            `;

            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function showLoading(show = true) {
            const modal = document.getElementById('loading-modal');
            if (show) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            } else {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function updateStats() {
            const totalProducts = products.length;

            const lastUpdate = products.length > 0 ? new Date(Math.max(...products.map(p => new Date(p.updated_at)))) : new Date();

            document.getElementById('total-products').textContent = totalProducts;
            document.getElementById('product-count').textContent = totalProducts;
            document.getElementById('total-value').textContent = formatPrice(totalValue, 'CLP');
            document.getElementById('avg-price').textContent = formatPrice(avgPrice, 'CLP');
            document.getElementById('last-update').textContent = formatDate(lastUpdate);
            document.getElementById('products-count').textContent = `(${totalProducts})`;
        }

        function createProductCard(product) {
            return `
                <div class="bg-white/80 backdrop-blur-lg rounded-2xl shadow-lg border border-white/20 overflow-hidden card-hover animate-fade-in" data-product-id="${product.id}">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-xl font-bold text-gray-800 flex-1 mr-4">${product.name}</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-2xl">${getCurrencyFlag(product.currency)}</span>
                            </div>
                        </div>
                        
                        ${product.description ? `<p class="text-gray-600 mb-4 line-clamp-2">${product.description}</p>` : ''}
                        
                        <div class="bg-gradient-to-r from-green-100 to-emerald-100 rounded-xl p-4 mb-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-green-700">Precio actual</span>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-green-800" id="price-${product.id}">
                                        ${formatPrice(product.price, product.currency)}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-500 mb-4 flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            Actualizado: ${formatDate(product.updated_at)}
                        </div>
                        
                        ${product.product_url ? `
                            <div class="mb-4">
                                <a href="${product.product_url}" target="_blank" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                                    <i class="fas fa-external-link-alt mr-2"></i>
                                    Ver producto
                                </a>
                            </div>
                        ` : ''}
                        
                        <div class="flex gap-3">
                            <button onclick="editProduct(${product.id})" 
                                    class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 flex items-center justify-center space-x-2">
                                <i class="fas fa-edit"></i>
                                <span>Editar</span>
                            </button>
                            <button onclick="deleteProduct(${product.id})" 
                                    class="flex-1 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 flex items-center justify-center space-x-2">
                                <i class="fas fa-trash"></i>
                                <span>Eliminar</span>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderProducts(productsToRender = products) {
            const grid = document.getElementById('products-grid');
            const emptyState = document.getElementById('empty-state');

            if (productsToRender.length === 0) {
                grid.innerHTML = '';
                emptyState.classList.remove('hidden');
            } else {
                emptyState.classList.add('hidden');
                grid.innerHTML = productsToRender.map(product => createProductCard(product)).join('');
            }

            updateStats();
        }

        function filterAndSortProducts() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const sortBy = document.getElementById('sort-select').value;

            let filtered = products.filter(product =>
                product.name.toLowerCase().includes(searchTerm) ||
                (product.description && product.description.toLowerCase().includes(searchTerm))
            );

            filtered.sort((a, b) => {
                switch (sortBy) {
                    case 'name-asc':
                        return a.name.localeCompare(b.name);
                    case 'name-desc':
                        return b.name.localeCompare(a.name);
                    case 'price-asc':
                        return a.price - b.price;
                    case 'price-desc':
                        return b.price - a.price;
                    case 'date-desc':
                        return new Date(b.updated_at) - new Date(a.updated_at);
                    default:
                        return 0;
                }
            });

            renderProducts(filtered);
        }

        async function addProduct(formData) {
            showLoading(true);
            try {
                const response = await fetch('api.php', { // Your PHP endpoint for adding
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });

                const result = await response.json();

                if (result.success) {
                    // Assuming your PHP returns the newly added product with an ID
                    // For now, using a dummy ID and timestamp
                    const newProduct = {
                        id: result.id || Date.now(), // Use ID from PHP if available
                        name: formData.get('name'),
                        description: formData.get('description') || '',
                        price: parseFloat(formData.get('price')) || 0,
                        currency: formData.get('currency'),
                        product_url: formData.get('url') || '',
                        created_at: result.created_at || new Date().toISOString(),
                        updated_at: result.updated_at || new Date().toISOString()
                    };
                    products.unshift(newProduct);
                    renderProducts();
                    showAlert('Producto agregado correctamente', 'success');
                    document.getElementById('add-product-form').reset();
                } else {
                    showAlert(`Error al agregar producto: ${result.message || 'Error desconocido'}`, 'error');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                showAlert('Error de conexión al agregar el producto.', 'error');
            } finally {
                showLoading(false);
            }
        }

        function editProduct(id) {
            const product = products.find(p => p.id === id);
            if (!product) return;

            // Fill form with product data
            const form = document.getElementById('add-product-form');
            form.name.value = product.name;
            form.description.value = product.description;
            form.price.value = product.price;
            form.currency.value = product.currency;
            form.url.value = product.product_url;
            form.dataset.editingId = product.id; // Store ID for update

            // Change button text to indicate editing
            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<i class="fas fa-save"></i><span>Actualizar Producto</span>';
            submitButton.classList.remove('from-blue-500', 'to-purple-600', 'hover:from-blue-600', 'hover:to-purple-700');
            submitButton.classList.add('from-orange-500', 'to-orange-600', 'hover:from-orange-600', 'hover:to-orange-700');

            // Scroll to form
            form.scrollIntoView({
                behavior: 'smooth'
            });
            form.name.focus();

            showAlert('Datos cargados para edición. Presiona "Actualizar Producto" para guardar cambios.', 'success');
        }

        async function updateProduct(id, formData) {
            showLoading(true);
            try {
                const response = await fetch(`api.php?id=${id}`, { // Your PHP endpoint for updating
                    method: 'PUT', // Often PUT for updates
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(Object.fromEntries(formData))
                });

                const result = await response.json();

                if (result.success) {
                    // Update the product in the local array
                    const index = products.findIndex(p => p.id === id);
                    if (index !== -1) {
                        products[index] = {
                            ...products[index],
                            name: formData.get('name'),
                            description: formData.get('description') || '',
                            price: parseFloat(formData.get('price')) || 0,
                            currency: formData.get('currency'),
                            product_url: formData.get('url') || '',
                            updated_at: result.updated_at || new Date().toISOString() // Use updated_at from PHP
                        };
                    }
                    renderProducts();
                    showAlert('Producto actualizado correctamente', 'success');
                    document.getElementById('add-product-form').reset();
                } else {
                    showAlert(`Error al actualizar producto: ${result.message || 'Error desconocido'}`, 'error');
                }
            } catch (error) {
                console.error('Error updating product:', error);
                showAlert('Error de conexión al actualizar el producto.', 'error');
            } finally {
                showLoading(false);
                // Reset form and button
                const form = document.getElementById('add-product-form');
                form.removeAttribute('data-editingId');
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.innerHTML = '<i class="fas fa-plus"></i><span>Agregar Producto</span>';
                submitButton.classList.remove('from-orange-500', 'to-orange-600', 'hover:from-orange-600', 'hover:to-orange-700');
                submitButton.classList.add('from-blue-500', 'to-purple-600', 'hover:from-blue-600', 'hover:to-purple-700');
            }
        }

        async function deleteProduct(id) {
            if (!confirm('¿Estás seguro de que quieres eliminar este producto?')) {
                return;
            }

            showLoading(true);
            try {
                const response = await fetch(`api.php?id=${id}`, { // Your PHP endpoint for deleting
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    products = products.filter(product => product.id !== id);
                    renderProducts();
                    showAlert('Producto eliminado correctamente', 'success');
                } else {
                    showAlert(`Error al eliminar producto: ${result.message || 'Error desconocido'}`, 'error');
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                showAlert('Error de conexión al eliminar el producto.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', () => {
            renderProducts(); // Initial render

            const addProductForm = document.getElementById('add-product-form');
            addProductForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                const editingId = this.dataset.editingId;

                if (editingId) {
                    updateProduct(parseInt(editingId), formData);
                } else {
                    addProduct(formData);
                }
            });

            document.getElementById('search-input').addEventListener('input', filterAndSortProducts);
            document.getElementById('sort-select').addEventListener('change', filterAndSortProducts);
        });

        // Initial load of products from PHP (if available)
        async function fetchProducts() {
            showLoading(true);
            try {
                const response = await fetch('api.php'); // Your PHP endpoint for fetching
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();
                products = data; // Assign fetched products
                renderProducts();
            } catch (error) {
                console.error('Error fetching products:', error);
                showAlert('Error al cargar productos. Inténtalo de nuevo más tarde.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Call fetchProducts on initial load
        document.addEventListener('DOMContentLoaded', fetchProducts);
    </script>
</body>

</html>