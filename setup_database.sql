-- Crear base de datos para la aplicación de compras
CREATE DATABASE IF NOT EXISTS shopping_list;
USE shopping_list;

-- Crear tabla para productos
CREATE TABLE IF NOT EXISTS list_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2),
    currency VARCHAR(3) DEFAULT 'USD',
    product_url TEXT,
    image_url TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insertar algunos productos de ejemplo
INSERT INTO list_products (name, description, price, currency, product_url) VALUES
('iPhone 15 Pro', 'Smartphone Apple iPhone 15 Pro 128GB', 999.00, 'USD', 'https://www.apple.com/iphone-15-pro/'),
('MacBook Air M2', 'Laptop Apple MacBook Air con chip M2', 1199.00, 'USD', 'https://www.apple.com/macbook-air/'),
('AirPods Pro', 'Auriculares inalámbricos Apple AirPods Pro', 249.00, 'USD', 'https://www.apple.com/airpods-pro/');

-- Crear usuario para la aplicación
CREATE USER IF NOT EXISTS 'shopping_user'@'localhost' IDENTIFIED BY 'shopping_pass';
GRANT ALL PRIVILEGES ON shopping_list.* TO 'shopping_user'@'localhost';
FLUSH PRIVILEGES;

