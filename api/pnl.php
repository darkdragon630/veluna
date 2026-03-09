<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

/**
 * POST {
 *   investment_id : int,
 *   kind          : 'unrealized' | 'realized',
 *   delta         : float  (+ untung, - rugi),
 *   -- crypto staking only --
 *   add_qty       : bool   (optional),
 *   coin_id       : string (optional),
 *   price_idr     : float  (optional, harga 1 koin dalam IDR)
 * }
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$invId = (int)($body['investment_id'] ?? 0);
$kind  = $body['kind'] ?? 'unrealized';
$delta = (float)($body['delta'] ?? 0);

if (!$invId) {
    echo json_encode(['success' => false, 'error' => 'investment_id required']);
    exit;
}
if (!in_array($kind, ['unrealized', 'realized'])) {
    echo json_encode(['success' => false, 'error' => 'kind harus unrealized atau realized']);
    exit;
}
if ($delta == 0) {
    echo json_encode(['success' => false, 'error' => 'Delta tidak boleh 0']);
    exit;
}

try {
    $db = getDB();

    // Pastikan kolom ada — graceful fallback jika migration belum jalan
    $cols = $db->query("SHOW COLUMNS FROM investments LIKE 'realized_pnl'")->fetchAll();
    if (empty($cols)) {
        // Kolom belum ada, buat otomatis
        $db->exec("ALTER TABLE investments ADD COLUMN unrealized_pnl DECIMAL(28,8) DEFAULT 0 AFTER note");
        $db->exec("ALTER TABLE investments ADD COLUMN realized_pnl DECIMAL(28,8) DEFAULT 0 AFTER unrealized_pnl");
    }

    $col = $kind === 'realized' ? 'realized_pnl' : 'unrealized_pnl';

    // Jika kolom unrealized_pnl juga belum ada, buat
    $uCols = $db->query("SHOW COLUMNS FROM investments LIKE 'unrealized_pnl'")->fetchAll();
    if (empty($uCols)) {
        $db->exec("ALTER TABLE investments ADD COLUMN unrealized_pnl DECIMAL(28,8) DEFAULT 0 AFTER note");
    }

    // Update PnL
    $db->prepare(
        "UPDATE investments SET $col = COALESCE($col, 0) + ? WHERE id = ?"
    )->execute([$delta, $invId]);

    // Crypto staking: tambah qty koin berdasarkan IDR / harga
    $newQty = null;
    if (!empty($body['add_qty']) && !empty($body['price_idr']) && $body['price_idr'] > 0) {
        $priceIDR = (float)$body['price_idr'];
        // delta positif = untung = beli koin dari reward
        // qty tambahan = delta_positif / harga_per_koin
        $qtyToAdd = abs($delta) / $priceIDR;

        $db->prepare(
            "UPDATE investments SET qty = COALESCE(qty, 0) + ? WHERE id = ?"
        )->execute([$qtyToAdd, $invId]);

        $row = $db->prepare("SELECT qty FROM investments WHERE id = ?");
        $row->execute([$invId]);
        $newQty = (float)($row->fetch()['qty'] ?? 0);
    }

    // Ambil nilai terbaru
    $row = $db->prepare("SELECT unrealized_pnl, realized_pnl FROM investments WHERE id = ?");
    $row->execute([$invId]);
    $r = $row->fetch();

    $result = [
        'success'        => true,
        'unrealized_pnl' => (float)($r['unrealized_pnl'] ?? 0),
        'realized_pnl'   => (float)($r['realized_pnl']   ?? 0),
    ];
    if ($newQty !== null) $result['new_qty'] = $newQty;

    echo json_encode($result);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ]);
}
