<?php
define('API_REQUEST', true);
define('BASE_URL', '');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/helpers.php';
header('Content-Type: application/json');

// GET - publik, untuk auto-refresh overview
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $m = getMaintenance();
    echo json_encode([
        'success'   => true,
        'is_active' => (bool)$m['is_active'],
        'end_time'  => $m['end_time'] ?? null,
        'end_ts'    => !empty($m['end_time']) ? strtotime($m['end_time']) : null,
    ]);
    exit;
}

if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? 'activate';

    if ($action === 'deactivate') {
        setMaintenance(false);
        echo json_encode(['success'=>true,'is_active'=>false]);
        exit;
    }

    $mode    = $body['mode']    ?? 'default';
    $title   = trim($body['title']   ?? 'Sedang dalam Pemeliharaan');
    $message = trim($body['message'] ?? '');
    $endTime = null;

    if (!empty($body['end_time'])) {
        $ts = strtotime($body['end_time']);
        if ($ts && $ts > time()) $endTime = date('Y-m-d H:i:s', $ts);
    }

    $customHtml = ($mode === 'custom') ? ($body['custom_html'] ?? null) : null;

    setMaintenance(true, [
        'mode'         => $mode,
        'title'        => $title ?: 'Sedang dalam Pemeliharaan',
        'message'      => $message ?: null,
        'end_time'     => $endTime,
        'custom_html'  => $customHtml,
        'activated_by' => getLoggedInUsername(),
    ]);

    echo json_encode(['success'=>true,'is_active'=>true,'end_time'=>$endTime]);
    exit;
}
