<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
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

$conexion = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME) or die("No se ha podido conectar al Servidor");

include('funciones.php');

date_default_timezone_set('America/Santiago');

$fechaHoraActual = date('Y-m-d H:i:s');

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

$previous_month = $current_month - 1;
$previous_year = $current_year;

// Si es enero, ir al mes 12 del año anterior
if ($previous_month == 0) {
    $previous_month = 12;
    $previous_year = $current_year - 1;
}

$resultados = obtener_datos($conexion, "c.Nombre = 'Ocio' OR c.Categoria_Padre = '24'", $current_month, $current_year, $previous_month, $previous_year);

$datos_financieros = obtener_datos_ultimos_meses($conexion, 6);

$ultimo_mes = end($datos_financieros);

$balance_mes_actual = $ultimo_mes['ingresos'] - $ultimo_mes['egresos'];
$total_ingresos = $ultimo_mes['ingresos'];

$total_ocio = $resultados['total'];
$result_detalles_ocio = $resultados['detalles'];
$anterior_total_ocio = $resultados['anterior_total'];
$ocio = $total_ingresos * 0.3;

$ocio_restante = $balance_mes_actual * 0.3;
