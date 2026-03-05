<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

function jsonOk($data = [])  { echo json_encode(['success'=>true] + $data); exit; }
function jsonErr($msg, $code=400) { http_response_code($code); echo json_encode(['success'=>false,'error'=>$msg]); exit; }

// READ
if ($method === 'GET') {
    $cat = $_GET['category'] ?? null;
    $invs = getInvestments($cat);
    jsonOk(['data' => $invs]);
}

// CREATE / UPDATE
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) jsonErr('Invalid JSON');

    $id  = $body['id'] ?? null;
    $cat = $body['category'] ?? null;
    if (!$cat || !array_key_exists($cat, CATEGORIES)) jsonErr('Invalid category');

    $coin_id = null;
    if ($cat === 'crypto' && !empty($body['ticker'])) {
        $coin_id = getCoinId($body['ticker']);
    }

    $fields = [
        'category'      => $cat,
        'name'          => $body['name']          ?? null,
        'ticker'        => $body['ticker']         ?? null,
        'coin_id'       => $coin_id,
        'qty'           => isset($body['qty'])      ? (float)$body['qty']       : null,
        'buy_price'     => isset($body['buy_price']) ? (float)$body['buy_price']: null,
        'current_price' => isset($body['current_price']) ? (float)$body['current_price'] : null,
        'amount'        => isset($body['amount'])   ? (float)$body['amount']    : 0,
        'current_value' => isset($body['current_value']) ? (float)$body['current_value'] : null,
        'inv_date'      => $body['inv_date']       ?? null,
        'note'          => $body['note']           ?? null,
    ];

    if ($id) {
        // UPDATE
        $setClauses = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE investments SET $setClauses WHERE id = ? AND is_sold = 0");
        $stmt->execute([...array_values($fields), (int)$id]);
        jsonOk(['id' => (int)$id]);
    } else {
        // INSERT
        $cols = implode(', ', array_map(fn($k)=>"`$k`", array_keys($fields)));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $db->prepare("INSERT INTO investments ($cols) VALUES ($placeholders)");
        $stmt->execute(array_values($fields));
        $newId = (int)$db->lastInsertId();

        // Auto-kurangi kas saat menambah investasi (jika ada kas)
        $modal = (float)($fields['amount'] ?? 0);
        if ($modal > 0) {
            $assetName = ($fields['name'] ?: $fields['ticker'] ?: 'Investasi');
            $catLabel  = ucfirst($fields['category']);
            addCashEntry('invest_out', -$modal,
                "Investasi: {$assetName} [{$catLabel}]",
                $fields['inv_date'] ?? date('Y-m-d'),
                $newId
            );
        }
        jsonOk(['id' => $newId]);
    }
}

// SELL / DELETE
if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) jsonErr('Invalid JSON');
    $action = $body['action'] ?? '';

    if ($action === 'sell') {
        $id        = (int)($body['id'] ?? 0);
        $sellPrice = (float)($body['sell_price'] ?? 0);
        $sellDate  = $body['sell_date'] ?? date('Y-m-d');
        if (!$id || !$sellPrice) jsonErr('Invalid params');

        $inv = $db->prepare("SELECT * FROM investments WHERE id = ? AND is_sold = 0");
        $inv->execute([$id]);
        $row = $inv->fetch();
        if (!$row) jsonErr('Investment not found', 404);

        $cost        = getInvCost($row);
        $realizedPnl = $sellPrice - $cost;

        // 1. Tandai investasi sebagai terjual
        $db->prepare("UPDATE investments SET is_sold=1, sell_price=?, sell_date=?, realized_pnl=? WHERE id=?")
           ->execute([$sellPrice, $sellDate, $realizedPnl, $id]);

        // 2. Insert manual ke sell_history (tidak bergantung pada trigger MySQL)
        //    Trigger tetap ada di database.sql sebagai fallback, tapi insert PHP ini
        //    memastikan data masuk bahkan di shared hosting yg menonaktifkan trigger.
        $already = $db->prepare("SELECT COUNT(*) FROM sell_history WHERE investment_id = ?");
        $already->execute([$id]);
        if ((int)$already->fetchColumn() === 0) {
            $db->prepare(
                "INSERT INTO sell_history
                    (investment_id, category, name, ticker, qty, buy_price,
                     cost, sell_price, realized_pnl, sell_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $id,
                $row['category'],
                $row['name'],
                $row['ticker'],
                $row['qty'],
                $row['buy_price'],
                $cost,
                $sellPrice,
                $realizedPnl,
                $sellDate,
            ]);
        }

        // 3. Auto-tambah kas dari hasil penjualan (selalu +sell_price)
        //    Cash = actual money received, PnL = profit/loss vs modal
        addCashEntry('from_sale', $sellPrice,
            'Hasil jual: ' . ($row['name'] ?: $row['ticker'] ?: 'Investasi #'.$id),
            $sellDate, $id
        );

        jsonOk(['realized_pnl' => $realizedPnl, 'cash_added' => $sellPrice]);
    }
    jsonErr('Unknown action');
}

// DELETE
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonErr('Invalid id');
    $stmt = $db->prepare("DELETE FROM investments WHERE id = ?");
    $stmt->execute([$id]);
    jsonOk(['deleted' => $id]);
}
