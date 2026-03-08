<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

// GET — ambil entri PnL untuk 1 investasi
if ($method === 'GET') {
    $invId = (int)($_GET['inv_id'] ?? 0);
    if (!$invId) { echo json_encode(['success'=>false,'error'=>'inv_id required']); exit; }
    $entries = getInvPnl($invId);
    $summary = getInvPnlSummary($invId);
    echo json_encode(['success'=>true,'entries'=>$entries,'summary'=>$summary]);
    exit;
}

// POST — tambah entri baru
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $invId  = (int)($body['investment_id'] ?? 0);
    $type   = $body['type']   ?? '';
    $amount = (float)($body['amount'] ?? 0);
    $source = trim($body['source'] ?? '');
    $note   = trim($body['note']   ?? '');
    $date   = $body['date']   ?? date('Y-m-d');

    if (!$invId)                          { echo json_encode(['success'=>false,'error'=>'investment_id required']); exit; }
    if (!in_array($type, ['profit','loss'])) { echo json_encode(['success'=>false,'error'=>'type harus profit atau loss']); exit; }
    if ($amount <= 0)                     { echo json_encode(['success'=>false,'error'=>'Amount harus > 0']); exit; }

    $id      = addInvPnl($invId, $type, $amount, $source, $note, $date);
    $summary = getInvPnlSummary($invId);
    echo json_encode(['success'=>true,'id'=>$id,'summary'=>$summary]);
    exit;
}

// DELETE — hapus entri
if ($method === 'DELETE') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'id required']); exit; }
    $ok = deleteInvPnl($id);
    echo json_encode(['success'=>$ok]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Method not allowed']);
