<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
// Crypto prices = data publik, tidak perlu login

$db     = getDB();
$action = $_GET['action'] ?? 'prices';

// Get active crypto investments
function getActiveCryptoIds($db): array {
    $rows = $db->query("SELECT DISTINCT coin_id, ticker FROM investments WHERE category='crypto' AND is_sold=0 AND coin_id IS NOT NULL")->fetchAll();
    $ids = [];
    foreach ($rows as $r) {
        $id = $r['coin_id'] ?: getCoinId($r['ticker'] ?? '');
        if ($id) $ids[] = $id;
    }
    return array_unique($ids);
}

// Fetch from CoinGecko
function fetchFromCoinGecko(array $ids): array {
    if (empty($ids)) return [];
    $idsStr = implode(',', $ids);
    $url = COINGECKO_API . "/simple/price?ids={$idsStr}&vs_currencies=idr,usd";

    $ctx = stream_context_create(['http' => [
        'timeout' => 8,
        'header'  => "User-Agent: PortoFolioTracker/1.0\r\n",
    ]]);
    $response = @file_get_contents($url, false, $ctx);
    if (!$response) return [];
    return json_decode($response, true) ?: [];
}

if ($action === 'prices') {
    // Return cached prices
    $prices = [];
    $rows = $db->query("SELECT coin_id, price_idr FROM crypto_cache")->fetchAll();
    foreach ($rows as $r) $prices[$r['coin_id']] = (float)$r['price_idr'];
    echo json_encode(['success'=>true, 'prices'=>$prices]);
    exit;
}

if ($action === 'refresh') {
    $ids = getActiveCryptoIds($db);
    if (empty($ids)) { echo json_encode(['success'=>true,'prices'=>[]]); exit; }

    $data = fetchFromCoinGecko($ids);
    if (empty($data)) { echo json_encode(['success'=>false,'error'=>'CoinGecko unavailable']); exit; }

    $prices = [];
    $stmt = $db->prepare("INSERT INTO crypto_cache (coin_id, price_idr, price_usd) VALUES (?,?,?) ON DUPLICATE KEY UPDATE price_idr=?, price_usd=?, updated_at=NOW()");
    foreach ($data as $coinId => $vals) {
        $idr = (float)($vals['idr'] ?? 0);
        $usd = (float)($vals['usd'] ?? 0);
        $stmt->execute([$coinId, $idr, $usd, $idr, $usd]);
        $prices[$coinId] = $idr;
    }
    echo json_encode(['success'=>true, 'prices'=>$prices, 'count'=>count($prices)]);
    exit;
}

// Auto-refresh if cache is stale (for background cron or first load)
if ($action === 'auto') {
    $ids = getActiveCryptoIds($db);
    $fetched = false;
    if (!empty($ids)) {
        // Cek apakah ada cache yang stale (> TTL detik) atau cache kosong
        $staleRow   = $db->query("SELECT COUNT(*) FROM crypto_cache WHERE updated_at < NOW() - INTERVAL ".CRYPTO_CACHE_TTL." SECOND")->fetchColumn();
        $totalCache = $db->query("SELECT COUNT(*) FROM crypto_cache")->fetchColumn();
        if ($staleRow > 0 || $totalCache == 0) {
            $data = fetchFromCoinGecko($ids);
            if (!empty($data)) {
                $stmt = $db->prepare("INSERT INTO crypto_cache (coin_id, price_idr, price_usd) VALUES (?,?,?) ON DUPLICATE KEY UPDATE price_idr=?, price_usd=?, updated_at=NOW()");
                foreach ($data as $coinId => $vals) {
                    $idr = (float)($vals['idr'] ?? 0);
                    $usd = (float)($vals['usd'] ?? 0);
                    $stmt->execute([$coinId, $idr, $usd, $idr, $usd]);
                }
                $fetched = true;
            }
            // Jika CoinGecko gagal, tetap return cache lama (jangan error)
        }
    }
    // Selalu return semua cached prices
    $prices = [];
    $rows = $db->query("SELECT coin_id, price_idr FROM crypto_cache")->fetchAll();
    foreach ($rows as $r) $prices[$r['coin_id']] = (float)$r['price_idr'];
    echo json_encode(['success' => true, 'prices' => $prices, 'fetched' => $fetched]);
    exit;
}
