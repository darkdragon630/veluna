<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(['success'=>true,'stats'=>getCashStats(),'ledger'=>getCashLedger(50)]);
    exit;
}

if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $type   = $body['type']   ?? 'topup';
    $amount = (float)($body['amount'] ?? 0);
    $note   = trim($body['note'] ?? '');
    $date   = $body['date']   ?? date('Y-m-d');

    if (!in_array($type, ['topup','withdrawal'])) {
        echo json_encode(['success'=>false,'error'=>'Invalid type']); exit;
    }
    if ($amount <= 0) {
        echo json_encode(['success'=>false,'error'=>'Amount harus > 0']); exit;
    }
    $finalAmount = ($type === 'withdrawal') ? -abs($amount) : abs($amount);
    $id = addCashEntry($type, $finalAmount, $note, $date);
    echo json_encode(['success'=>true,'id'=>$id,'balance'=>getCashBalance()]);
    exit;
}

// DELETE — hanya withdrawal yang bisa dihapus
// topup dan from_sale TIDAK bisa dihapus (permanent record)
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }

    $stmt = getDB()->prepare("SELECT type FROM cash_ledger WHERE id=?");
    $stmt->execute([$id]); $entry = $stmt->fetch();
    if (!$entry) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

    // Hanya withdrawal yang bisa dihapus
    if (in_array($entry['type'], ['topup','from_sale','invest_out'])) {
        echo json_encode(['success'=>false,'error'=>'Entri kas masuk dan investasi tidak dapat dihapus']); exit;
    }
    getDB()->prepare("DELETE FROM cash_ledger WHERE id=?")->execute([$id]);
    echo json_encode(['success'=>true,'balance'=>getCashBalance()]);
    exit;
}
