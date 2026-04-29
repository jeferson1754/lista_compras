<?php
// Incluir tu conexión a la base de datos
require_once 'config.php';

$pdo = getDBConnection(); // Make sure this function returns a valid PDO object.

// 1. Manejar la inserción de nueva categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = $_POST['name'];
    $icon = $_POST['icon'];
    $color = $_POST['color'];

    $stmt = $pdo->prepare("INSERT INTO categories (name, icon, color) VALUES (?, ?, ?)");
    $stmt->execute([$name, $icon, $color]);
    header("Location: categories.php"); // Recargar para limpiar el POST
    exit;
}

// Manejar la actualización de categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id = (int)$_POST['edit_id'];
    $name = $_POST['name'];
    $icon = $_POST['icon'];
    $color = $_POST['color'];

    $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ?, color = ? WHERE id = ?");
    $stmt->execute([$name, $icon, $color, $id]);

    header("Location: categories.php?success=updated");
    exit;
}

// 2. Manejar la eliminación
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: categories.php");
    exit;
}

// 3. Obtener todas las categorías
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Categorías</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen p-4 md:p-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold text-gray-800">Gestionar Categorías</h1>
            <a onclick="history.length > 1 ? history.back() : window.location.href='index.php'" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

            <div class="md:col-span-1">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 sticky top-8">
                    <h2 class="text-lg font-bold mb-4">Nueva Categoría</h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" name="name" required class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icono (FontAwesome)</label>
                            <input type="text" name="icon" placeholder="fas fa-tag" required class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                            <p class="text-[10px] text-gray-400 mt-1">Ej: fas fa-shopping-basket, fas fa-laptop</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color (Tailwind Classes)</label>
                            <select name="color" class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50">
                                <option value="bg-emerald-50 text-emerald-700 border-emerald-100">Esmeralda (Alimentos)</option>
                                <option value="bg-blue-50 text-blue-700 border-blue-100">Azul (Tecnología)</option>
                                <option value="bg-amber-50 text-amber-700 border-amber-100">Ámbar (Limpieza)</option>
                                <option value="bg-pink-50 text-pink-700 border-pink-100">Rosado (Cuidado Personal)</option>
                                <option value="bg-purple-50 text-purple-700 border-purple-100">Púrpura (Hogar)</option>
                                <option value="bg-rose-50 text-rose-700 border-rose-100">Rosa (Cuidado)</option>
                                <option value="bg-gray-100 text-gray-600 border-gray-200">Gris (Otros)</option>
                                <option value="bg-red-50 text-red-700 border-red-100">Rojo (Urgente / Deudas)</option>
                                <option value="bg-orange-50 text-orange-700 border-orange-100">Naranja (Transporte)</option>
                                <option value="bg-yellow-50 text-yellow-700 border-yellow-100">Amarillo (Entretenimiento)</option>
                                <option value="bg-lime-50 text-lime-700 border-lime-100">Lima (Salud)</option>
                                <option value="bg-green-50 text-green-700 border-green-100">Verde (Ahorro)</option>
                                <option value="bg-teal-50 text-teal-700 border-teal-100">Teal (Servicios)</option>
                                <option value="bg-cyan-50 text-cyan-700 border-cyan-100">Cian (Internet / Apps)</option>
                                <option value="bg-indigo-50 text-indigo-700 border-indigo-100">Índigo (Educación)</option>
                                <option value="bg-violet-50 text-violet-700 border-violet-100">Violeta (Suscripciones)</option>
                                <option value="bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100">Fucsia (Ocio)</option>
                                <option value="bg-slate-100 text-slate-700 border-slate-200">Slate (Administración)</option>
                                <option value="bg-stone-100 text-stone-700 border-stone-200">Stone (Misc)</option>
                            </select>
                        </div>

                        <button type="submit" name="add_category" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                            Crear Categoría
                        </button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-4 text-sm font-bold text-gray-600">Vista Previa</th>
                                <th class="px-6 py-4 text-sm font-bold text-gray-600">Nombre</th>
                                <th class="px-6 py-4 text-right text-sm font-bold text-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($categories as $cat): ?>

                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg border text-xs font-bold uppercase <?php echo $cat['color']; ?>">
                                            <i class="<?php echo $cat['icon']; ?>"></i>
                                            Demo
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-800">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($cat)); ?>)"
                                            class="text-blue-400 hover:text-blue-600 p-2 transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <a href="?delete=<?php echo $cat['id']; ?>"
                                            onclick="return confirm('¿Seguro?')"
                                            class="text-red-400 hover:text-red-600 p-2">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($categories)): ?>
                        <div class="p-8 text-center text-gray-400">No hay categorías creadas aún.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden animate-fade-in">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                        <h3 class="text-xl font-bold text-gray-800">Editar Categoría</h3>
                        <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form method="POST" class="p-6 space-y-4" style="margin-top: -40px;">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" name="name" id="edit_name" required
                                class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icono (FontAwesome)</label>
                            <input type="text" name="icon" id="edit_icon" required
                                class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50 focus:ring-2 focus:ring-blue-400 outline-none">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Color</label>
                            <select name="color" id="edit_color" class="w-full mt-1 px-4 py-2 border rounded-xl bg-gray-50">
                                <option value="bg-emerald-50 text-emerald-700 border-emerald-100">Esmeralda (Alimentos)</option>
                                <option value="bg-blue-50 text-blue-700 border-blue-100">Azul (Tecnología)</option>
                                <option value="bg-amber-50 text-amber-700 border-amber-100">Ámbar (Limpieza)</option>
                                <option value="bg-pink-50 text-pink-700 border-pink-100">Rosado (Cuidado Personal)</option>
                                <option value="bg-purple-50 text-purple-700 border-purple-100">Púrpura (Hogar)</option>
                                <option value="bg-rose-50 text-rose-700 border-rose-100">Rosa (Cuidado)</option>
                                <option value="bg-gray-100 text-gray-600 border-gray-200">Gris (Otros)</option>
                                <option value="bg-red-50 text-red-700 border-red-100">Rojo (Urgente / Deudas)</option>
                                <option value="bg-orange-50 text-orange-700 border-orange-100">Naranja (Transporte)</option>
                                <option value="bg-yellow-50 text-yellow-700 border-yellow-100">Amarillo (Entretenimiento)</option>
                                <option value="bg-lime-50 text-lime-700 border-lime-100">Lima (Salud)</option>
                                <option value="bg-green-50 text-green-700 border-green-100">Verde (Ahorro)</option>
                                <option value="bg-teal-50 text-teal-700 border-teal-100">Teal (Servicios)</option>
                                <option value="bg-cyan-50 text-cyan-700 border-cyan-100">Cian (Internet / Apps)</option>
                                <option value="bg-indigo-50 text-indigo-700 border-indigo-100">Índigo (Educación)</option>
                                <option value="bg-violet-50 text-violet-700 border-violet-100">Violeta (Suscripciones)</option>
                                <option value="bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100">Fucsia (Ocio)</option>
                                <option value="bg-slate-100 text-slate-700 border-slate-200">Slate (Administración)</option>
                                <option value="bg-stone-100 text-stone-700 border-stone-200">Stone (Misc)</option>
                            </select>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 border border-gray-300 text-gray-600 rounded-xl hover:bg-gray-50 transition">
                                Cancelar
                            </button>
                            <button type="submit" name="update_category" class="flex-1 bg-blue-600 text-white py-2 rounded-xl font-bold hover:bg-blue-700 transition shadow-lg shadow-blue-200">
                                Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <script>
        function openEditModal(category) {
            document.getElementById('edit_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_icon').value = category.icon;
            document.getElementById('edit_color').value = category.color;

            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.add('hidden');
        }

        // Cerrar modal si se hace clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) closeEditModal();
        }
    </script>

</body>

</html>