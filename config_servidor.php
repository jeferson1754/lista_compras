<?php
// Configuración de la base de datos
define('DB_HOST', 'sql208.epizy.com');
define('DB_USER', 'epiz_32740026');
define('DB_PASS', 'eJWcVk2au5gqD');
define('DB_NAME', 'epiz_32740026_r_user');

// Función para conectar a la base de datos
function getDBConnection()
{
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}


