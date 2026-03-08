<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'];

/**
 * POST { investment_id, delta }
 * delta: positif = tambah untung, negatif = tambah rugi
 * Nilai disimpan kumulatif di investments.unrealized_pnl
 */
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $invId = (int)($body['investment_id'] ?? 0);
    $delta = (float)($body['delta'] ?? 0);

    if (!$invId) { echo json_encode(['success'=>false,'error'=>'investment_id required']); exit; }
    if ($delta == 0) { echo json_encode(['success'=>false,'error'=>'Delta tidak boleh 0']); exit; }

    $newVal = adjustUnrealizedPnl($invId, $delta);
    echo json_encode(['success'=>true,'unrealized_pnl'=>$newVal]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Method not allowed']);
