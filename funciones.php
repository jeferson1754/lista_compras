<?php
function obtener_datos($conexion, $where, $current_month, $current_year, $previous_month, $previous_year)
{
    // SQL queries
    $sql_total = "SELECT SUM(g.Valor) AS total
    FROM gastos g
    INNER JOIN categorias_gastos c ON g.ID_Categoria_Gastos = c.ID 
    WHERE (MONTH(g.Fecha) = ? AND YEAR(g.Fecha) = ?)
    AND(" . $where . ")";

    $sql_detalles = "SELECT d.Detalle AS Descripcion, g.Valor, c.Nombre as categoria, g.Fecha
    FROM gastos g
    INNER JOIN categorias_gastos c ON g.ID_Categoria_Gastos = c.ID
    INNER JOIN detalle d ON g.ID_Detalle = d.ID
    WHERE (MONTH(g.Fecha) = ? AND YEAR(g.Fecha) = ?)
    AND(" . $where . ")
    ORDER BY g.Fecha DESC";

    $sql_anterior = "SELECT SUM(g.Valor) AS total
    FROM gastos g
    INNER JOIN categorias_gastos c ON g.ID_Categoria_Gastos = c.ID 
    WHERE (MONTH(g.Fecha) = ? AND YEAR(g.Fecha) = ?)
    AND(" . $where . ")";

    // Consulta del total de gastos del mes actual
    $stmt_total = mysqli_prepare($conexion, $sql_total);
    mysqli_stmt_bind_param($stmt_total, "ss", $current_month, $current_year);
    mysqli_stmt_execute($stmt_total);
    $result_total = mysqli_stmt_get_result($stmt_total);
    $total = mysqli_fetch_assoc($result_total)['total'] ?? 0;

    // Consulta de los detalles de los gastos del mes actual
    $stmt_detalles = mysqli_prepare($conexion, $sql_detalles);
    mysqli_stmt_bind_param($stmt_detalles, "ss", $current_month, $current_year);
    mysqli_stmt_execute($stmt_detalles);
    $result_detalles = mysqli_stmt_get_result($stmt_detalles);

    // Consulta del total de gastos del mes anterior
    $stmt_anterior = mysqli_prepare($conexion, $sql_anterior);
    mysqli_stmt_bind_param($stmt_anterior, "ss", $previous_month, $previous_year);
    mysqli_stmt_execute($stmt_anterior);
    $result_anterior = mysqli_stmt_get_result($stmt_anterior);
    $anterior_total = mysqli_fetch_assoc($result_anterior)['total'] ?? 0;

    // Retornar los resultados en un array
    return [
        'total' => $total,
        'detalles' => $result_detalles,
        'anterior_total' => $anterior_total
    ];
}
function obtener_nombre_mes_espanol($numero_mes)
{
    $meses_array = array(
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre'
    );
    return $meses_array[$numero_mes];
}

function obtener_datos_ultimos_meses($conexion, $meses)
{
    $datos = [];
    $stmt = $conexion->prepare("
                                SELECT 
                                    SUM(CASE WHEN categorias_gastos.Nombre = 'Ingresos' THEN gastos.Valor ELSE 0 END) AS total_ingresos,
                                    SUM(CASE WHEN categorias_gastos.Nombre != 'Ingresos' THEN gastos.Valor ELSE 0 END) AS total_egresos
                                FROM gastos 
                                INNER JOIN categorias_gastos ON categorias_gastos.ID = gastos.ID_Categoria_Gastos 
                                WHERE MONTH(gastos.Fecha) = ? AND YEAR(gastos.Fecha) = ?
                            ");

    for ($i = $meses - 1; $i >= 0; $i--) {
        $fecha = date('Y-m-01', strtotime("-$i month"));
        $mes = date('n', strtotime($fecha));
        $anio = date('Y', strtotime($fecha));

        $stmt->bind_param('ii', $mes, $anio);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $total_ingresos = $row['total_ingresos'] ?? 0;
        $total_egresos = $row['total_egresos'] ?? 0;

        $datos[] = [
            'mes' => obtener_nombre_mes_espanol($mes),
            'ingresos' => $total_ingresos,
            'egresos' => $total_egresos
        ];
    }
    $stmt->close();
    return $datos;
}

function getPurchaseHistory($pdo, $limit = 20, $offset = 0)
{
    // Puedes añadir filtros aquí (por mes, año, categoría, nivel de necesidad, etc.)
    $stmt = $pdo->prepare("SELECT ph.*, lp.name as original_name, lp.description as original_description
                            FROM purchase_history ph
                            JOIN list_products lp ON ph.product_id = lp.id
                            ORDER BY purchased_at DESC
                            LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// En history.php
function getPurchaseReasonsAnalysis($pdo)
{
    $stmt = $pdo->query("SELECT purchase_reason, COUNT(*) as count
                            FROM purchase_history
                            WHERE purchase_reason IS NOT NULL AND purchase_reason != ''
                            GROUP BY purchase_reason
                            ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getNecessityLevelAnalysis($pdo)
{
    $stmt = $pdo->query("SELECT necessity_level, COUNT(*) as count
                            FROM purchase_history
                            WHERE necessity_level IS NOT NULL
                            GROUP BY necessity_level
                            ORDER BY necessity_level DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Para usar:
// $reasonsAnalysis = getPurchaseReasonsAnalysis(getDBConnection());
// $necessityAnalysis = getNecessityLevelAnalysis(getDBConnection());
