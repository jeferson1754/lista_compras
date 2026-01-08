<?php
// Suponiendo que tienes un archivo de conexión a la base de datos
// require_once 'config/database.php';
require_once 'config.php';
// --- LÓGICA PHP PARA CARGAR LOS PRODUCTOS ---
// Esta es una simulación. Debes reemplazarla con tu propia consulta a la base de datos.
$products = [];

try {
    $pdo = getDBConnection();

    // 1️⃣ Obtener productos no comprados
    $stmt = $pdo->query("
        SELECT id, name, price, necessity_level, purchase_reason
        FROM list_products
        WHERE is_purchased = FALSE
        ORDER BY necessity_level DESC
    ");

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2️⃣ Enriquecer cada producto
    foreach ($products as &$product) {
        $productId = $product['id'];

        /* =========================
           📈 HISTORIAL DE PRECIOS
        ========================== */
        $price_history = [];
        try {
            $stmt_history = $pdo->prepare("
                SELECT price, purchased_at
                FROM product_price
                WHERE product_id = ?
                ORDER BY purchased_at DESC
            ");
            $stmt_history->execute([$productId]);
            $price_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $price_history = [];
        }

        $product['price_history'] = $price_history;

        // Tendencia de precio
        $price_trend = 'estable';
        if (count($price_history) >= 2) {
            $current = (float)$price_history[0]['price'];
            $previous = (float)$price_history[1]['price'];

            if ($current > $previous) {
                $price_trend = 'sube';
            } elseif ($current < $previous) {
                $price_trend = 'baja';
            }
        }

        $product['price_trend'] = $price_trend;

        /* =========================
           🔁 USOS DEL PRODUCTO
        ========================== */
        $product_usages = [];
        try {
            $stmt_usages = $pdo->prepare("
                SELECT context, importance, used_at
                FROM product_usages
                WHERE product_id = ?
                  AND type = 'faltó'
                ORDER BY used_at DESC
            ");
            $stmt_usages->execute([$productId]);
            $product_usages = $stmt_usages->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $product_usages = [];
        }

        $product['usages'] = $product_usages;

        // Métricas derivadas
        $usage_count = count($product_usages);
        $importance_avg = 0;

        if ($usage_count > 0) {
            $total_importance = array_sum(array_column($product_usages, 'importance'));
            $importance_avg = round($total_importance / $usage_count, 2);
        }

        $product['usage_count'] = $usage_count;
        $product['importance_avg'] = $importance_avg;
    }
} catch (Exception $e) {
    $products = [];
    $error_message = "Error al cargar los productos: " . $e->getMessage();
}

//echo json_encode($products);

/* =========================
   Función reutilizable
========================= */
function getNecessityText(int $level): string
{
    $map = [
        1 => 'Capricho',
        2 => 'Opcional',
        3 => 'Necesario',
        4 => 'Muy Necesario',
        5 => '¡Esencial!'
    ];
    return $map[$level] ?? 'N/A';
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asesor de Compras por IA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>

</head>

<body class="bg-gray-50">

    <div class="container mx-auto p-4 md:p-8 max-w-4xl">
        <div class="mb-6">
            <a href="index.php" class="text-indigo-600 hover:text-indigo-800 font-semibold transition-colors flex items-center gap-2 w-fit">
                <i class="fas fa-arrow-left"></i>
                <span>Volver a mi Lista</span>
            </a>
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-200">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-800">🤖 Asesor de Compras por IA</h1>
                <p class="text-gray-600 mt-2">Genera un prompt detallado para que una IA te ayude a decidir qué comprar.</p>
            </div>

            <div class="mb-8">
                <label for="budget" class="block text-lg font-semibold text-gray-700 mb-2">1. Ingresa tu presupuesto</label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <span class="text-gray-500 text-lg">$</span>
                    </div>
                    <input type="number" id="budget" value="<?php echo intval($ocio_restante); ?>" class="block w-full text-lg rounded-xl border-gray-300 pl-10 pr-4 py-3 focus:border-indigo-500 focus:ring-indigo-500" placeholder="100000" min="0">
                </div>
            </div>

            <div class="mb-8">
                <h2 class="block text-lg font-semibold text-gray-700 mb-2">2. Selecciona los productos a considerar</h2>
                <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
                    <?php if (empty($products)): ?>
                        <p class="text-gray-500">No hay productos en tu lista. ¡Añade algunos para empezar!</p>
                    <?php else: ?>
                        <?php foreach ($products as $product): ?>
                            <label for="product-<?php echo $product['id']; ?>" class="flex items-center p-4 bg-gray-50 border border-gray-200 rounded-xl hover:bg-indigo-50 hover:border-indigo-300 transition-all cursor-pointer">
                                <input type="checkbox"
                                    class="h-6 w-6 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 product-checkbox"

                                    data-name="<?= htmlspecialchars($product['name']) ?>"
                                    data-price="<?= $product['price'] ?>"
                                    data-necessity="<?= getNecessityText($product['necessity_level']) ?>"
                                    data-reason="<?= htmlspecialchars($product['purchase_reason']) ?>"

                                    data-usage-count="<?= $product['usage_count'] ?>"
                                    data-importance="<?= $product['importance_avg'] ?>"
                                    data-price-trend="<?= $product['price_trend'] ?>" />

                                <div class="ml-4 flex-grow">
                                    <p class="font-bold text-gray-800"><?= htmlspecialchars($product['name']) ?></p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-semibold text-green-600">
                                            $<?= number_format($product['price'], 0, ',', '.') ?>
                                        </span> |
                                        <span class="font-semibold">
                                            <?= getNecessityText($product['necessity_level']) ?>
                                        </span>
                                    </p>

                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span>Prioridad</span>
                                            <span class="font-semibold score-text"></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="score-bar h-2 rounded-full transition-all"></div>
                                        </div>
                                    </div>
                                </div>

                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-3">

                <!-- 🤖 Elegir por mí -->
                <button
                    id="autoSelectBtn"
                    class="w-full bg-gradient-to-r from-slate-600 to-slate-700
               text-white font-semibold text-lg
               py-3 px-6 rounded-xl
               hover:from-slate-700 hover:to-slate-800
               transition-all duration-300
               flex items-center justify-center gap-2
               shadow-md hover:shadow-lg">
                    <i class="fas fa-robot"></i>
                    Elegir por mí
                </button>

                <!-- ✨ Generar Prompt -->
                <button
                    id="generate-prompt-btn"
                    class="w-full bg-indigo-600 text-white font-bold text-lg
               py-3 px-6 rounded-xl
               hover:bg-indigo-700
               transition-all duration-300
               flex items-center justify-center gap-2
               shadow-md hover:shadow-lg">
                    <i class="fas fa-magic"></i>
                    Generar Prompt
                </button>

            </div>


            <div id="result-wrapper" class="mt-8 hidden">
                <h2 class="block text-lg font-semibold text-gray-700 mb-2">3. ¡Tu prompt está listo!</h2>
                <div class="relative">
                    <textarea id="prompt-output" rows="15" class="w-full p-4 bg-gray-100 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-300" readonly></textarea>
                    <button id="copy-btn" class="absolute top-3 right-3 bg-gray-600 text-white px-3 py-1 rounded-lg hover:bg-gray-700 text-sm">
                        <i class="fas fa-copy mr-1"></i>
                        Copiar
                    </button>




                </div>
            </div>

            <div class="bg-white rounded-xl p-4 shadow-md relative">
                <div id="radarLabel"
                    class="absolute top-2 right-3 px-3 py-1 rounded-full text-xs font-bold hidden">
                </div>

                <h3 class="text-lg font-bold text-gray-700 mb-2">
                    📊 Radar de Prioridad
                </h3>

                <div id="priorityRadar" style="width:100%; height:300px;"></div>
            </div>



            <script>
                const radarChart = echarts.init(document.getElementById('priorityRadar'));

                /* ==========================
   HELPERS
========================== */



                function renderScoreBar(checkbox) {
                    const necessityMap = {
                        'Capricho': 1,
                        'Opcional': 2,
                        'Necesario': 3,
                        'Muy Necesario': 4,
                        '¡Esencial!': 5
                    };

                    const necessity = necessityMap[checkbox.dataset.necessity] ?? 1;
                    const usageCount = Number(checkbox.dataset.usageCount ?? 0);
                    const importance = Number(checkbox.dataset.importance ?? 1);
                    const priceTrend = checkbox.dataset.priceTrend ?? 'estable';

                    const score = calculatePriorityScore({
                        necessity,
                        usageCount,
                        importance,
                        priceTrend
                    });

                    const container = checkbox.closest('label');
                    const bar = container.querySelector('.score-bar');
                    const text = container.querySelector('.score-text');

                    bar.style.width = `${score}%`;
                    bar.className = `score-bar h-2 rounded-full transition-all ${getScoreColor(score)}`;
                    text.textContent = `${score}/100`;

                    return score;
                }


                const necessityMap = {
                    'Capricho': 1,
                    'Opcional': 2,
                    'Necesario': 3,
                    'Muy Necesario': 4,
                    '¡Esencial!': 5
                };

                const formatCLP = value =>
                    new Intl.NumberFormat('es-CL', {
                        style: 'currency',
                        currency: 'CLP'
                    }).format(value);

                const getScoreColor = score =>
                    score >= 75 ? 'bg-green-500' :
                    score >= 50 ? 'bg-yellow-400' :
                    'bg-red-500';


                function calculatePriorityScore({
                    necessity,
                    usageCount,
                    importance,
                    priceTrend
                }) {
                    return Math.round(
                        (necessity / 5) * 25 +
                        (Math.min(usageCount, 10) / 10) * 30 +
                        (importance / 5) * 25 +
                        (priceTrend === 'baja' ? 20 : priceTrend === 'estable' ? 12 : 5)
                    );
                }

                /* ==========================
                   MAIN
                ========================== */

                document.addEventListener('DOMContentLoaded', () => {
                    const budgetInput = document.getElementById('budget');
                    const generateBtn = document.getElementById('generate-prompt-btn');
                    const resultWrapper = document.getElementById('result-wrapper');
                    const promptOutput = document.getElementById('prompt-output');
                    const copyBtn = document.getElementById('copy-btn');
                    const checkboxes = document.querySelectorAll('.product-checkbox');



                    generateBtn.addEventListener('click', () => {
                        const budget = Number(budgetInput.value);
                        if (!budget) return alert('Por favor, ingresa un presupuesto válido.');

                        const formattedBudget = new Intl.NumberFormat('es-CL', {
                            style: 'currency',
                            currency: 'CLP'
                        }).format(budget);

                        let productListText = '';
                        let evaluatedProducts = [];
                        let index = 1;
                        let productsSelectedCount = 0;

                        checkboxes.forEach(cb => {
                            const necessity = necessityMap[cb.dataset.necessity] ?? 1;
                            const usageCount = Number(cb.dataset.usageCount ?? 0);
                            const importance = Number(cb.dataset.importance ?? 1);
                            const priceTrend = cb.dataset.priceTrend ?? 'estable';
                            const price = Number(cb.dataset.price);

                            // UI score bar
                            const score = renderScoreBar(cb);

                            evaluatedProducts.push({
                                checkbox: cb,
                                score,
                                price
                            });

                            if (!cb.checked) return;

                            productsSelectedCount++;
                            const formattedPrice = new Intl.NumberFormat('es-CL', {
                                style: 'currency',
                                currency: 'CLP'
                            }).format(price);

                            productListText += `- **Producto ${productsSelectedCount}:** ${cb.dataset.name}\n`;
                            productListText += `  - **Precio Actual:** ${formattedPrice}\n`;
                            productListText += `  - **Nivel de Necesidad:** ${cb.dataset.necessity}\n`;
                            productListText += `  - **Motivo:** "${cb.dataset.reason}"\n`;

                            productListText += `  - **Cantidad de veces que ha faltado:** ${usageCount}\n`;
                            productListText += `  - **Importancia promedio cuando faltó:** ${importance}/5\n`;

                            if (priceTrend === 'sube') {
                                productListText += `  - **Historial de precio:** El precio ha subido recientemente 📈\n`;
                            } else if (priceTrend === 'baja') {
                                productListText += `  - **Historial de precio:** El precio ha bajado recientemente 📉\n`;
                            } else {
                                productListText += `  - **Historial de precio:** El precio se ha mantenido estable ➖\n`;
                            }

                            productListText += `\n`;
                        });

                        if (!productListText) return alert('Selecciona al menos un producto.');

                        // Auto selección TOP 3
                        evaluatedProducts
                            .sort((a, b) => b.score - a.score)
                            .reduce((acc, p) => {
                                if (acc.count < 3 && acc.total + p.price <= budget) {
                                    p.checkbox.checked = true;
                                    acc.total += p.price;
                                    acc.count++;
                                }
                                return acc;
                            }, {
                                total: 0,
                                count: 0
                            });

                        promptOutput.value = `
**Rol:** Actúa como un Asesor Financiero experto en Economía Conductual y Análisis de Coste-Beneficio. Tu enfoque es ultra-racional, objetivo y escéptico ante gastos innecesarios.

**Contexto del Usuario:**
- **Presupuesto Disponible:** ${formattedBudget}
- **Productos en Consideración:** ${productListText.trim()}

**Instrucciones de Análisis Académico y Financiero:**
Para cada producto, aplica un análisis riguroso basado en estos pilares:
1. **Ponderación de Utilidad (60%):** Cruza la frecuencia de uso con la necesidad crítica. Si el uso es ocasional pero el precio es alto, penaliza el score.
2. **Análisis de Oportunidad de Mercado (20%):** Basado en el historial de precios provisto, detecta si es un mínimo histórico o una inflación artificial.
3. **Costo de Oportunidad (20%):** Analiza qué porcentaje del presupuesto total consume y qué otras necesidades se sacrifican.

**Reglas de Clasificación:**
Asigna un score de 1 a 100 y clasifica según:
- **🟢 Compra Racional (Score ≥ 75):** Alta utilidad, precio justo, impacto presupuestario manejable.
- **🟡 Compra Debatible (Score 50-74):** Deseo vs. Necesidad no claro, o precio poco atractivo.
- **🔴 Compra Impulsiva (Score < 50):** Baja frecuencia de uso, gratificación instantánea o precio inflado.

**Tarea:**
Presenta un informe ejecutivo comparativo. Selecciona estrictamente los **3 mejores productos** (o menos, si el resto no alcanza un score racional).

**Estructura de Respuesta por Producto:**
1. **Análisis de Valor:** (Breve párrafo sobre por qué este producto es una inversión o un gasto).
2. **Pros y Contras:** (2 de cada uno, enfocados en lo financiero).
3. **Cálculo de 'Costo por Uso':** (Estima el precio dividido por los usos mensuales esperados).
4. **Veredicto:** [Comprar Ahora / Esperar / Descartar] + Clasificación (🟢/🟡/🔴).

**Restricción:** Si detectas que un producto es un capricho innecesario, sé directo y recomienda no comprarlo, incluso si el presupuesto alcanza.
`.trim();

                        resultWrapper.classList.remove('hidden');
                    });

                    copyBtn.addEventListener('click', () => {
                        promptOutput.select();
                        document.execCommand('copy');
                        copyBtn.innerHTML = '✔ Copiado';
                        setTimeout(() => copyBtn.innerHTML = 'Copiar', 2000);
                    });
                });

                // 🔄 Render inicial de todas las barras de prioridad
                document.querySelectorAll('.product-checkbox').forEach(cb => {
                    renderScoreBar(cb);
                });
                document.getElementById('autoSelectBtn').addEventListener('click', () => {
                    const budget = Number(document.getElementById('budget').value);

                    if (isNaN(budget) || budget <= 0) {
                        alert('Ingresa un presupuesto válido');
                        return;
                    }


                    // Limpiar selección previa
                    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = false);

                    // Construir lista evaluable
                    const products = Array.from(document.querySelectorAll('.product-checkbox'))
                        .map(cb => {
                            const score = renderScoreBar(cb); // score REAL
                            const price = Number(cb.dataset.price ?? 0);

                            return {
                                checkbox: cb,
                                score,
                                price,
                                efficiency: price > 0 ? score / price : 0
                            };
                        })
                        // Filtrar productos absurdos
                        .filter(p => p.score >= 40 && p.price > 0);

                    // Orden inteligente:
                    // 1️⃣ Mayor eficiencia
                    // 2️⃣ Mayor score
                    products.sort((a, b) => {
                        if (b.efficiency !== a.efficiency) {
                            return b.efficiency - a.efficiency;
                        }
                        return b.score - a.score;
                    });

                    // Selección respetando presupuesto
                    let total = 0;
                    let count = 0;

                    for (const product of products) {
                        if (count >= 3) break;
                        if (total + product.price <= budget) {
                            product.checkbox.checked = true;
                            total += product.price;
                            count++;
                        }
                    }

                    // Feedback visual mínimo
                    if (count === 0) {
                        alert('No hay productos recomendables dentro del presupuesto.');
                    }
                });

                function getRadarValues(cb) {
                    const necessityMap = {
                        'Capricho': 20,
                        'Opcional': 40,
                        'Necesario': 60,
                        'Muy Necesario': 80,
                        '¡Esencial!': 100
                    };

                    const necessity = necessityMap[cb.dataset.necessity] ?? 20;
                    const usage = Math.min(Number(cb.dataset.usageCount || 0), 10) * 10;
                    const price = Number(cb.dataset.price || 0);

                    const priceScore =
                        price <= 10000 ? 100 :
                        price <= 30000 ? 75 :
                        price <= 60000 ? 50 :
                        30;

                    return [necessity, usage, priceScore];
                }

                function renderBehaviorLabel(score) {
                    const label = document.getElementById('radarLabel');
                    label.classList.remove('hidden');

                    if (score >= 75) {
                        label.textContent = '🟢 Compra Racional';
                        label.className = 'absolute top-2 right-3 bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold';
                    } else if (score >= 50) {
                        label.textContent = '🟡 Compra Debatible';
                        label.className = 'absolute top-2 right-3 bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-bold';
                    } else {
                        label.textContent = '🔴 Compra Impulsiva';
                        label.className = 'absolute top-2 right-3 bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-bold';
                    }
                }




                function renderAverageRadar(products) {
                    if (!products.length) return;

                    const avg = [0, 0, 0];
                    products.forEach(cb => {
                        const v = getRadarValues(cb);
                        avg[0] += v[0];
                        avg[1] += v[1];
                        avg[2] += v[2];
                    });

                    avg.forEach((_, i) => avg[i] = Math.round(avg[i] / products.length));

                    renderBehaviorLabel(
                        Math.round((avg[0] + avg[1] + avg[2]) / 3)
                    );

                    radarChart.setOption({
                        radar: {
                            indicator: [{
                                    name: 'Necesidad',
                                    max: 100
                                },
                                {
                                    name: 'Uso',
                                    max: 100
                                },
                                {
                                    name: 'Precio',
                                    max: 100
                                }
                            ]
                        },
                        series: [{
                            type: 'radar',
                            data: [{
                                value: avg,
                                name: 'Promedio TOP 3',
                                areaStyle: {
                                    color: 'rgba(99,102,241,0.35)'
                                },
                                lineStyle: {
                                    color: '#4f46e5'
                                }
                            }]
                        }]
                    }, true);
                    radarChart.setOption(option, true);
                }




                function renderComparisonRadar(cbs) {

                    const colors = ['#4f46e5', '#16a34a', '#dc2626', '#f59e0b'];

                    const seriesData = cbs.map((cb, index) => ({
                        value: getRadarValues(cb),
                        name: cb.dataset.name,
                        lineStyle: {
                            color: colors[index],
                            width: 3
                        },
                        itemStyle: {
                            color: colors[index]
                        },
                        areaStyle: {
                            color: colors[index],
                            opacity: 0.2
                        }
                    }));

                    const avgScore =
                        seriesData
                        .flatMap(s => s.value)
                        .reduce((a, b) => a + b, 0) / (seriesData.length * 3);

                    renderBehaviorLabel(Math.round(avgScore));

                    radarChart.setOption({
                        tooltip: {
                            trigger: 'item'
                        },

                        legend: {
                            top: -5,
                            data: seriesData.map(s => s.name),
                            textStyle: {
                                fontSize: 12
                            }
                        },

                        radar: {
                            indicator: [{
                                    name: 'Necesidad',
                                    max: 100
                                },
                                {
                                    name: 'Uso',
                                    max: 100
                                },
                                {
                                    name: 'Precio',
                                    max: 100
                                }
                            ],
                            splitArea: {
                                areaStyle: {
                                    color: ['#f8fafc']
                                }
                            }
                        },

                        series: [{
                            type: 'radar',
                            data: seriesData
                        }]
                    }, true);
                }




                document.querySelectorAll('.product-checkbox').forEach(cb => {
                    cb.addEventListener('change', () => {
                        const selected = Array.from(document.querySelectorAll('.product-checkbox:checked'));

                        if (selected.length === 2) {
                            renderComparisonRadar(selected);
                        } else if (selected.length >= 3) {
                            renderAverageRadar(selected.slice(0, 3));
                        }
                    });
                });


                document.addEventListener('DOMContentLoaded', () => {
                    const top3 = Array.from(document.querySelectorAll('.product-checkbox'))
                        .map(cb => ({
                            cb,
                            score: renderScoreBar(cb)
                        }))
                        .sort((a, b) => b.score - a.score)
                        .slice(0, 3)
                        .map(p => p.cb);

                    renderAverageRadar(top3);
                });
            </script>


</body>

</html>