<?php
// PortoFolio config v2 - SECURE (no plaintext password)
define('APP_NAME',    'PortoFolio');
define('APP_VERSION', '1.0.0');
define('APP_BUILD',   '2025.03');
define('APP_DESC',    'Personal Investment Tracker');
define('WA_NUMBER',          '6289635637904');
define('SESSION_NAME',       'pf_sess');
define('SESSION_REGEN_INTERVAL', 300);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
date_default_timezone_set('Asia/Jakarta');
define('CURRENCY', 'IDR');
define('COINGECKO_API', 'https://api.coingecko.com/api/v3');
define('CRYPTO_CACHE_TTL', 60);

define('SESSION_LIFETIME', 86400); // 24 jam

ini_set('session.use_strict_mode',  '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  'Strict');
ini_set('session.gc_maxlifetime',   (string)SESSION_LIFETIME);
ini_set('session.cookie_lifetime',  (string)SESSION_LIFETIME); // cookie tetap hidup 24 jam walau browser ditutup
session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

// Session ID regeneration — HANYA untuk page biasa, bukan API request.
// Jika dilakukan saat API call (fetch/XHR), cookie lama terhapus → logout.
$_isApiReq = defined('API_REQUEST') && API_REQUEST === true;
if (!$_isApiReq) {
    if (isset($_SESSION['_last_regen'])) {
        if (time() - $_SESSION['_last_regen'] > SESSION_REGEN_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    } else { $_SESSION['_last_regen'] = time(); }
}

// UA hash — hanya set saat login, jangan destroy saat beda (fetch bisa beda UA minor)
$__ua = md5(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80));
if (!isset($_SESSION['_ua_hash'])) {
    $_SESSION['_ua_hash'] = $__ua;
}
// Catatan: pengecekan UA dihapus karena fetch() dari browser bisa kirim UA berbeda
// yang menyebabkan logout tak terduga. Security tetap dijaga via session token.

function isLoggedIn(): bool {
    if (!isset($_SESSION['pf_auth'], $_SESSION['pf_user_id'])) return false;
    // Cek expiry server-side (24 jam sejak login)
    if (isset($_SESSION['pf_expire_at']) && time() > $_SESSION['pf_expire_at']) {
        session_destroy();
        return false;
    }
    try {
        $s = getDB()->prepare("SELECT id FROM auth_users WHERE id=?");
        $s->execute([$_SESSION['pf_user_id']]);
        return (bool)$s->fetch();
    } catch (Throwable) { return false; }
}
function requireLogin(): void {
    if (!isLoggedIn()) {
        $pg = basename($_SERVER['PHP_SELF'] ?? '');
        $qs = ($pg && $pg !== 'login.php') ? '?redirect='.urlencode($pg) : '';
        header('Location: '.BASE_URL.'login.php'.$qs); exit;
    }
}
function getLoggedInUsername(): string { return $_SESSION['pf_username'] ?? 'User'; }
function csrfToken(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function verifyCsrf(string $t): bool {
    return !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}
function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        $ip = trim(explode(',', $_SERVER[$k] ?? '')[0]);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}
function isLockedOut(): bool {
    try {
        // Gunakan NOW() dan INTERVAL langsung di SQL — bebas timezone mismatch
        $s = getDB()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address=? AND success=0
             AND attempted_at > NOW() - INTERVAL ? MINUTE"
        );
        $s->execute([getClientIP(), LOGIN_LOCKOUT_MINUTES]);
        return (int)$s->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
    } catch (Throwable) { return false; }
}
function getLockoutRemaining(): int {
    try {
        // Ambil sisa detik langsung dari DB menggunakan TIMESTAMPDIFF
        $s = getDB()->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(),
               DATE_ADD(MAX(attempted_at), INTERVAL ? MINUTE))
             FROM login_attempts
             WHERE ip_address=? AND success=0
             AND attempted_at > NOW() - INTERVAL ? MINUTE"
        );
        $s->execute([LOGIN_LOCKOUT_MINUTES, getClientIP(), LOGIN_LOCKOUT_MINUTES]);
        $sec = (int)$s->fetchColumn();
        return max(0, $sec);
    } catch (Throwable) { return 0; }
}
function getFailedAttempts(): int {
    try {
        $s = getDB()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE ip_address=? AND success=0
             AND attempted_at > NOW() - INTERVAL ? MINUTE"
        );
        $s->execute([getClientIP(), LOGIN_LOCKOUT_MINUTES]);
        return (int)$s->fetchColumn();
    } catch (Throwable) { return 0; }
}
function recordAttempt(string $u, bool $ok): void {
    try {
        getDB()->prepare(
            "INSERT INTO login_attempts (ip_address, username, success) VALUES (?,?,?)"
        )->execute([getClientIP(), $u, $ok ? 1 : 0]);
        // Bersihkan data lama secara berkala
        if (rand(1, 15) === 1) {
            getDB()->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 24 HOUR");
        }
    } catch (Throwable) {}
}
function userExists(): bool {
    try { return (int)getDB()->query("SELECT COUNT(*) FROM auth_users")->fetchColumn() > 0; }
    catch (Throwable) { return false; }
}
function createUser(string $u, string $p): bool {
    try {
        $h = password_hash($p, PASSWORD_BCRYPT, ['cost'=>12]);
        getDB()->prepare("INSERT INTO auth_users (username,password_hash) VALUES (?,?)")->execute([$u,$h]);
        return true;
    } catch (Throwable) { return false; }
}
function verifyLogin(string $u, string $p): ?array {
    try {
        $s = getDB()->prepare("SELECT id,username,password_hash FROM auth_users WHERE username=?");
        $s->execute([$u]); $user = $s->fetch();
        if ($user && password_verify($p, $user['password_hash'])) return $user;
    } catch (Throwable) {}
    return null;
}
function loginUser(array $user): void {
    // Set cookie lifetime sebelum regenerate agar cookie baru ikut 24 jam
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_regenerate_id(true);
    $_SESSION['pf_auth']      = true;
    $_SESSION['pf_user_id']   = $user['id'];
    $_SESSION['pf_username']  = $user['username'];
    $_SESSION['pf_token']     = bin2hex(random_bytes(16));
    $_SESSION['pf_login_at']  = time();
    $_SESSION['pf_expire_at'] = time() + SESSION_LIFETIME; // hard expiry di server side
    $_SESSION['_last_regen']  = time();
    $_SESSION['_ua_hash']     = md5(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80));
}
if (!defined('ADMIN_USERNAME')) {
    // Jangan panggil getDB() di sini — bisa jalan sebelum database.php load
    // ADMIN_USERNAME di-resolve lazy saat pertama kali dibutuhkan
    define('ADMIN_USERNAME', '__pending__');
}
function resolveAdminUsername(): string {
    if (defined('ADMIN_USERNAME') && ADMIN_USERNAME !== '__pending__') {
        return ADMIN_USERNAME;
    }
    try {
        $u = getDB()->query("SELECT username FROM auth_users LIMIT 1")->fetchColumn();
        return $u ?: 'admin';
    } catch (Throwable) { return 'admin'; }
}
if (!defined('BASE_URL')) {
    $__proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    $__host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $__dir   = realpath(__DIR__.'/..');
    $__root  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '/');
    $__rel   = rtrim(str_replace('\\','/', str_replace($__root,'',$__dir)),'/').'/';
    define('BASE_URL',$__proto.'://'.$__host.$__rel);
}
