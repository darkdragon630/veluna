<?php
// =====================================================
// PortoFolio — Database Configuration
// =====================================================

define('DB_HOST',    'db.fr-pari1.bengt.wasmernet.com');
define('DB_USER',    '72ab2ceb7f318000c745eacdb8f2');       // Ganti sesuai server Anda
define('DB_PASS',    '069a72ab-2cec-7031-8000-bac678134431');           // Ganti sesuai server Anda
define('DB_NAME',    'dbmJeex8QZtQYss4JJSP8uic');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT',    10272);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // SSL — wajib untuk managed/remote DB (Wasmer, PlanetScale, dll)
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_SSL_CA               => null,
            ]);
        } catch (PDOException $e) {
            if (php_sapi_name() === 'cli' || (defined('API_REQUEST') && API_REQUEST)) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'DB Connection Failed']);
                exit;
            }
            die('<div style="background:#1a0000;color:#ef4444;padding:24px;font-family:monospace;border:1px solid #ef4444;border-radius:8px;margin:20px">
                <strong>⚠ Database Connection Failed</strong><br><br>
                Pastikan MySQL berjalan dan konfigurasi di <code>config/database.php</code> sudah benar.<br><br>
                <em>Jalankan <code>database.sql</code> untuk membuat database.</em>
            </div>');
        }
    }
    return $pdo;
}
