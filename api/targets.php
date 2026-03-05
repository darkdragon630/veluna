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

if ($method === 'GET') {
    $targets = getTargets();
    echo json_encode(['success'=>true,'data'=>$targets]);
    exit;
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $cat  = $body['category'] ?? null;
    $amount = (float)($body['target_amount'] ?? 0);

    if (!$cat) { echo json_encode(['success'=>false,'error'=>'Invalid category']); exit; }

    $db->prepare("INSERT INTO targets (category, target_amount) VALUES (?,?) ON DUPLICATE KEY UPDATE target_amount=?")->execute([$cat, $amount, $amount]);
    echo json_encode(['success'=>true]);
    exit;
}
