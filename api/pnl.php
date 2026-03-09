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
 *   -- crypto staking saja --
 *   add_qty       : bool   (optional),
 *   coin_id       : string (optional),
 *   price_idr     : float  (optional)
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

if (!$invId) { echo json_encode(['success' => false, 'error' => 'investment_id required']); exit; }
if (!in_array($kind, ['unrealized', 'realized'])) { echo json_encode(['success' => false, 'error' => 'kind harus unrealized atau realized']); exit; }
if ($delta == 0) { echo json_encode(['success' => false, 'error' => 'Delta tidak boleh 0']); exit; }

try {
    $db  = getDB();
    $col = $kind === 'realized' ? 'realized_pnl' : 'unrealized_pnl';

    // NULL-safe update — kedua kolom sudah ada di DB
    $db->prepare(
        "UPDATE investments SET {$col} = COALESCE({$col}, 0) + ? WHERE id = ? AND is_sold = 0"
    )->execute([$delta, $invId]);

    // Crypto staking: tambah qty koin = delta_IDR / harga_per_koin
    $newQty = null;
    if (!empty($body['add_qty']) && !empty($body['price_idr']) && (float)$body['price_idr'] > 0) {
        $qtyToAdd = abs($delta) / (float)$body['price_idr'];
        $db->prepare(
            "UPDATE investments SET qty = COALESCE(qty, 0) + ? WHERE id = ? AND is_sold = 0"
        )->execute([$qtyToAdd, $invId]);

        $r = $db->prepare("SELECT qty FROM investments WHERE id = ?");
        $r->execute([$invId]);
        $newQty = (float)($r->fetch()['qty'] ?? 0);
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
