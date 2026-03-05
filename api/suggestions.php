<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');
// Saran bisa dikirim siapapun tanpa login — data publik

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['sender_name'] ?? '');
    $text = trim($body['suggestion']  ?? '');
    if (!$text) { echo json_encode(['success'=>false,'error'=>'Suggestion is required']); exit; }
    $db = getDB();
    $db->prepare("INSERT INTO feature_suggestions (sender_name, suggestion) VALUES (?,?)")->execute([$name ?: null, $text]);
    echo json_encode(['success'=>true,'id'=>(int)$db->lastInsertId()]);
    exit;
}
echo json_encode(['success'=>false,'error'=>'Method not allowed']);
