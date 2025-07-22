# 🛒 Lista de Compras - Gestor de Precios

Una aplicación web desarrollada en PHP y MySQL para gestionar tu lista de compras con precios actualizados y enlaces de productos.

## ✨ Características

- **Gestión de productos**: Agregar, editar y eliminar productos de tu lista
- **Actualización de precios**: Botón para actualizar precios automáticamente
- **Enlaces de productos**: Guarda y accede a los enlaces de compra
- **Múltiples monedas**: Soporte para USD, EUR, MXN, ARS, COP
- **Interfaz moderna**: Diseño responsive y atractivo
- **Base de datos MySQL**: Almacenamiento persistente de datos

## 🚀 Instalación y Configuración

### Requisitos
- PHP 8.1 o superior
- MySQL 8.0 o superior
- Extensiones PHP: mysqli, curl, json

### Pasos de instalación

1. **Instalar dependencias**:
   ```bash
   sudo apt update
   sudo apt install -y php php-mysql php-mysqli php-curl php-json mysql-server
   ```

2. **Iniciar MySQL**:
   ```bash
   sudo systemctl start mysql
   sudo systemctl enable mysql
   ```

3. **Configurar base de datos**:
   ```bash
   sudo mysql < setup_database.sql
   ```

4. **Iniciar servidor PHP**:
   ```bash
   php -S 0.0.0.0:8080
   ```

5. **Acceder a la aplicación**:
   Abre tu navegador y ve a `http://localhost:8080`

## 📁 Estructura de archivos

```
shopping-app/
├── index.php              # Página principal de la aplicación
├── config.php             # Configuración de base de datos
├── price_updater.php      # Módulo de actualización de precios
├── setup_database.sql     # Script de configuración de BD
└── README.md              # Este archivo
```

## 🎯 Funcionalidades

### Agregar productos
1. Completa el formulario con:
   - **Nombre del producto** (requerido)
   - **Precio** (opcional)
   - **Moneda** (USD por defecto)
   - **URL del producto** (opcional)
   - **Descripción** (opcional)
2. Haz clic en "Agregar Producto"

### Actualizar precios
- Haz clic en el botón "🔄 Actualizar Precio" en cualquier producto
- El sistema intentará obtener el precio real desde la URL del producto
- Si no es posible, simulará una actualización de precio

### Eliminar productos
- Haz clic en el botón "🗑️ Eliminar" en cualquier producto
- Confirma la eliminación en el diálogo

### Ver productos
- Haz clic en "🔗 Ver producto" para abrir el enlace en una nueva pestaña

## 🔧 Configuración de base de datos

La aplicación utiliza las siguientes credenciales por defecto:
- **Host**: localhost
- **Usuario**: shopping_user
- **Contraseña**: shopping_pass
- **Base de datos**: shopping_list

Para cambiar estas credenciales, edita el archivo `config.php`.

## 🌐 Actualización de precios

El sistema incluye dos métodos de actualización de precios:

1. **Web Scraping**: Intenta extraer precios reales de las URLs de productos
2. **Simulación**: Si no puede obtener precios reales, simula fluctuaciones de precio

### Sitios soportados para scraping
- Amazon
- Mercado Libre
- Patrones generales de precios

## 📱 Diseño Responsive

La aplicación está optimizada para:
- Computadoras de escritorio
- Tablets
- Teléfonos móviles

## 🎨 Personalización

### Colores y estilos
Los estilos están definidos en el archivo `index.php`. Puedes modificar:
- Colores del tema
- Fuentes
- Espaciado
- Animaciones

### Monedas
Para agregar nuevas monedas, edita el select en `index.php`:
```html
<option value="NUEVA_MONEDA">NUEVA_MONEDA - Descripción</option>
```

## 🔒 Seguridad

- Uso de declaraciones preparadas (prepared statements) para prevenir inyección SQL
- Validación de datos de entrada
- Escape de caracteres HTML para prevenir XSS

## 🐛 Solución de problemas

### Error de conexión a MySQL
```bash
sudo systemctl status mysql
sudo systemctl restart mysql
```

### Permisos de archivos
```bash
chmod 755 /path/to/shopping-app
chmod 644 /path/to/shopping-app/*.php
```

### Puerto ocupado
Si el puerto 8080 está ocupado, usa otro puerto:
```bash
php -S 0.0.0.0:8081
```

## 📈 Mejoras futuras

- Integración con APIs de tiendas reales
- Notificaciones de cambios de precio
- Categorías de productos
- Lista de deseos
- Comparación de precios entre tiendas
- Historial de precios
- Exportación de datos

## 📄 Licencia

Este proyecto es de código abierto y está disponible bajo la licencia MIT.

---

¡Disfruta gestionando tu lista de compras! 🛍️

