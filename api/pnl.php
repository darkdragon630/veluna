<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

/**
 * POST {
 *   investment_id: int,
 *   kind: 'unrealized' | 'realized',
 *   delta: float  (+ untung, - rugi)
 * }
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $invId = (int)($body['investment_id'] ?? 0);
    $kind  = $body['kind'] ?? 'unrealized';
    $delta = (float)($body['delta'] ?? 0);

    if (!$invId)                                { echo json_encode(['success'=>false,'error'=>'investment_id required']); exit; }
    if (!in_array($kind, ['unrealized','realized'])) { echo json_encode(['success'=>false,'error'=>'kind harus unrealized atau realized']); exit; }
    if ($delta == 0)                            { echo json_encode(['success'=>false,'error'=>'Delta tidak boleh 0']); exit; }

    $vals = adjustInvPnl($invId, $kind, $delta);
    echo json_encode(['success'=>true] + $vals);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Method not allowed']);
