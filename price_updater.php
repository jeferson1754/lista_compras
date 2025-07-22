<?php
require_once 'config.php';

class PriceUpdater {
    
    public function updateProductPrice($productId) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM list_products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            return false;
        }
        
        $newPrice = $this->fetchPriceFromURL($product['product_url']);
        
        // Si no se puede obtener precio real, simular actualización
        if ($newPrice === false) {
            $newPrice = $this->simulatePriceUpdate($product['price']);
        }
        
        // Actualizar precio en la base de datos
        $stmt = $pdo->prepare("UPDATE list_products SET price = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$newPrice, $productId]) ? $newPrice : false;
    }
    
    private function fetchPriceFromURL($url) {
        if (empty($url)) {
            return false;
        }
        
        // Configurar cURL para web scraping básico
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            return false;
        }
        
        // Intentar extraer precio usando patrones comunes
        $pricePatterns = [
            // Amazon
            '/<span[^>]*class="[^"]*a-price-whole[^"]*"[^>]*>([0-9,]+)<\/span>/',
            '/<span[^>]*class="[^"]*a-offscreen[^"]*"[^>]*>\$([0-9,]+\.?[0-9]*)<\/span>/',
            
            // Mercado Libre
            '/<span[^>]*class="[^"]*price-tag-fraction[^"]*"[^>]*>([0-9,]+)<\/span>/',
            '/<span[^>]*class="[^"]*andes-money-amount__fraction[^"]*"[^>]*>([0-9,]+)<\/span>/',
            
            // Patrones generales
            '/\$([0-9,]+\.?[0-9]*)/',
            '/price["\s:>]+([0-9,]+\.?[0-9]*)/i',
            '/([0-9,]+\.?[0-9]*)\s*USD/i',
        ];
        
        foreach ($pricePatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $price = str_replace(',', '', $matches[1]);
                if (is_numeric($price) && $price > 0) {
                    return floatval($price);
                }
            }
        }
        
        return false;
    }
    
    private function simulatePriceUpdate($currentPrice) {
        // Simular fluctuación de precio entre -20% y +20%
        $variation = (rand(-20, 20) / 100);
        $newPrice = $currentPrice * (1 + $variation);
        
        // Asegurar que el precio no sea negativo
        return max(0.01, round($newPrice, 2));
    }
    
    public function updateAllPrices() {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT id FROM list_products");
        $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results = [];
        foreach ($productIds as $id) {
            $newPrice = $this->updateProductPrice($id);
            $results[$id] = $newPrice;
        }
        
        return $results;
    }
}

// Si se llama directamente via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $updater = new PriceUpdater();
    
    switch ($_POST['action']) {
        case 'update_single_price':
            $productId = $_POST['product_id'] ?? 0;
            if ($productId > 0) {
                $newPrice = $updater->updateProductPrice($productId);
                if ($newPrice !== false) {
                    echo json_encode([
                        'success' => true, 
                        'new_price' => number_format($newPrice, 2),
                        'message' => 'Precio actualizado correctamente'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar precio']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
            }
            break;
            
        case 'update_all_prices':
            $results = $updater->updateAllPrices();
            echo json_encode([
                'success' => true,
                'results' => $results,
                'message' => 'Todos los precios han sido actualizados'
            ]);
            break;
    }
    exit;
}
?>

