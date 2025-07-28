<?php
// Suponiendo que tienes un archivo de conexión a la base de datos
// require_once 'config/database.php';
require_once 'config.php';
// --- LÓGICA PHP PARA CARGAR LOS PRODUCTOS ---
// Esta es una simulación. Debes reemplazarla con tu propia consulta a la base de datos.
try {
    $stmt = getDBConnection()->query("SELECT id, name, price, necessity_level, purchase_reason FROM list_products ORDER BY necessity_level DESC;");
    $products = $stmt->fetchAll();

    // -- Fin de los datos de ejemplo --

} catch (Exception $e) {
    // Manejo de errores de base de datos
    $products = [];
    $error_message = "Error al cargar los productos: " . $e->getMessage();
}

// Función para obtener el texto de necesidad (la reutilizamos)
function getNecessityText(int $level): string
{
    $map = [1 => 'Capricho', 2 => 'Opcional', 3 => 'Necesario', 4 => 'Muy Necesario', 5 => '¡Esencial!'];
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
                                <input type="checkbox" id="product-<?php echo $product['id']; ?>" class="h-6 w-6 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 product-checkbox" checked
                                    data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                    data-price="<?php echo $product['price']; ?>"
                                    data-necessity="<?php echo getNecessityText($product['necessity_level']); ?>"
                                    data-reason="<?php echo htmlspecialchars($product['purchase_reason']); ?>">
                                <div class="ml-4 flex-grow">
                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($product['name']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-semibold text-green-600">$<?php echo number_format($product['price'], 0, ',', '.'); ?></span> |
                                        <span class="font-semibold"><?php echo getNecessityText($product['necessity_level']); ?></span>
                                    </p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <button id="generate-prompt-btn" class="w-full bg-indigo-600 text-white font-bold text-lg py-3 px-6 rounded-xl hover:bg-indigo-700 transition-all duration-300 flex items-center justify-center gap-2">
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

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const budgetInput = document.getElementById('budget');
            const generateBtn = document.getElementById('generate-prompt-btn');
            const resultWrapper = document.getElementById('result-wrapper');
            const promptOutput = document.getElementById('prompt-output');
            const copyBtn = document.getElementById('copy-btn');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');

            generateBtn.addEventListener('click', function() {
                // 1. Obtener el presupuesto y formatearlo
                const budget = parseFloat(budgetInput.value);
                if (isNaN(budget) || budget <= 0) {
                    alert('Por favor, ingresa un presupuesto válido.');
                    return;
                }
                const formattedBudget = new Intl.NumberFormat('es-CL', {
                    style: 'currency',
                    currency: 'CLP'
                }).format(budget);

                // 2. Recopilar los productos seleccionados
                let productListText = '';
                let productsSelectedCount = 0;
                productCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        productsSelectedCount++;
                        const price = parseFloat(checkbox.dataset.price);
                        const formattedPrice = new Intl.NumberFormat('es-CL', {
                            style: 'currency',
                            currency: 'CLP'
                        }).format(price);

                        productListText += `- **Producto ${productsSelectedCount}:** ${checkbox.dataset.name}\n`;
                        productListText += `  - **Precio:** ${formattedPrice}\n`;
                        productListText += `  - **Nivel de Necesidad:** ${checkbox.dataset.necessity}\n`;
                        productListText += `  - **Motivo:** "${checkbox.dataset.reason}"\n\n`;
                    }
                });

                if (productsSelectedCount === 0) {
                    alert('Por favor, selecciona al menos un producto.');
                    return;
                }

                // 3. Construir el prompt final usando una plantilla de texto
                const promptTemplate = `
**Contexto:**
Actúa como un asesor financiero personal y experto en decisiones de compra. Mi objetivo es usar mi presupuesto de la manera más inteligente posible, evaluando objetivamente mis opciones.

**Mi Presupuesto Actual:**
${formattedBudget}

**Mi Lista de Productos a Considerar:**
${productListText.trim()}
**Tarea Principal:**
Analiza mi presupuesto y la lista de productos. Luego, recomiéndame los **3 productos prioritarios** que debería considerar comprar. Para cada uno de esos 3 productos, crea un análisis conciso de "Pros" y "Contras" de realizar la compra ahora.

El análisis debe ser objetivo, considerando tanto la necesidad y el motivo que he indicado, como el impacto en mi presupuesto. La respuesta debe ser clara y ayudarme a tomar la mejor decisión final.
        `;

                // 4. Mostrar el resultado
                promptOutput.value = promptTemplate.trim();
                resultWrapper.classList.remove('hidden');
            });

            // Funcionalidad del botón de copiar
            copyBtn.addEventListener('click', function() {
                promptOutput.select();
                document.execCommand('copy');
                copyBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Copiado!';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fas fa-copy mr-1"></i> Copiar';
                }, 2000);
            });
        });
    </script>

</body>

</html>