<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

if (!userExists()) { header('Location: '.BASE_URL.'create.php'); exit; }
if (isLoggedIn())  { header('Location: '.BASE_URL.'dashboard.php'); exit; }

$error   = '';
$locked  = false;
$lockSec = 0;
$failed  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ① Cek lockout PERTAMA — sebelum apapun, bahkan sebelum CSRF
    // Ini mencegah bruteforce CSRF token sekaligus
    if (isLockedOut()) {
        $locked  = true;
        $lockSec = getLockoutRemaining();
        // Rekam attempt ini juga (anti-bypass via direct POST)
        recordAttempt(trim($_POST['username'] ?? ''), false);
        $error = 'Terlalu banyak percobaan gagal. Akses dikunci.';
    }
    // ② Baru cek CSRF
    elseif (!verifyCsrf($_POST['_csrf'] ?? '')) {
        $error = 'Token keamanan tidak valid. Refresh halaman.';
    }
    // ③ Proses login
    else {
        $u    = trim($_POST['username'] ?? '');
        $p    = $_POST['password'] ?? '';
        $user = verifyLogin($u, $p);

        if ($user) {
            recordAttempt($u, true);
            loginUser($user);
            unset($_SESSION['_csrf']);
            $rd = basename($_GET['redirect'] ?? 'dashboard.php');
            if (!preg_match('/^[a-z0-9_\-]+\.php$/i', $rd)) $rd = 'dashboard.php';
            header('Location: '.BASE_URL.$rd);
            exit;
        } else {
            recordAttempt($u, false);
            $failed = getFailedAttempts();
            $rem    = LOGIN_MAX_ATTEMPTS - $failed;

            if ($rem <= 0) {
                // Baru saja mencapai limit — langsung kunci
                $locked  = true;
                $lockSec = getLockoutRemaining();
                $error   = 'Akun dikunci '.LOGIN_LOCKOUT_MINUTES.' menit karena terlalu banyak percobaan gagal.';
            } else {
                $error = 'Username atau password salah. '.$rem.' percobaan tersisa sebelum dikunci.';
            }
        }
    }

} else {
    // GET request — cek apakah sudah terkena lockout
    if (isLockedOut()) {
        $locked  = true;
        $lockSec = getLockoutRemaining();
        $failed  = LOGIN_MAX_ATTEMPTS;
    } else {
        $failed = getFailedAttempts();
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — PortoFolio</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:ital,wght@0,300;0,400;0,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
<style>
.login-wrap      { min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px }
.version-badge   { position:fixed;bottom:20px;right:20px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:6px 14px;font-size:10px;color:var(--text3);z-index:10 }
/* Progress bar percobaan */
.attempt-bar     { height:4px;border-radius:2px;background:var(--surface2);margin-bottom:20px;overflow:hidden }
.attempt-fill    { height:100%;border-radius:2px;transition:width .4s,background .4s }
/* Lockout box */
.lockout-box     { background:rgba(239,68,68,0.09);border:1px solid rgba(239,68,68,0.35);border-radius:14px;padding:28px 24px;text-align:center;margin-bottom:4px }
.lockout-icon    { font-size:40px;margin-bottom:12px }
.lockout-title   { font-size:14px;font-weight:700;color:var(--red);margin-bottom:6px }
.lockout-cd      { font-size:12px;color:var(--text2);margin-bottom:18px;line-height:1.6 }
.cd-grid         { display:grid;grid-template-columns:repeat(3,1fr);gap:10px;max-width:240px;margin:16px auto 0 }
.cd-box          { background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 6px }
.cd-num          { display:block;font-size:24px;font-weight:700;font-family:var(--mono);color:var(--red);line-height:1 }
.cd-lbl          { display:block;font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-top:3px }
/* Eye toggle */
.pw-wrap         { position:relative }
.pw-wrap input   { padding-right:46px }
.eye-btn         { position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;padding:4px }
</style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="login-brand">Porto<span>Folio</span></div>
      <div class="login-tagline">Personal Investment Tracker</div>
      <div style="margin-top:8px">
        <span class="badge badge-gold">v<?= APP_VERSION ?></span>
        <span style="margin-left:6px;font-size:10px;color:var(--text3)"><?= APP_BUILD ?></span>
      </div>
    </div>

    <?php if ($locked): ?>
    <!-- ══ LOCKOUT STATE: form disembunyikan sepenuhnya ══ -->
    <div class="lockout-box">
      <div class="lockout-icon">&#128274;</div>
      <div class="lockout-title">Akses Dikunci Sementara</div>
      <div class="lockout-cd">
        <?= LOGIN_MAX_ATTEMPTS ?> kali gagal login.<br>
        Coba lagi setelah countdown selesai.
      </div>
      <div class="cd-grid">
        <div class="cd-box"><span class="cd-num" id="cd-m">--</span><span class="cd-lbl">Menit</span></div>
        <div class="cd-box"><span class="cd-num" id="cd-s">--</span><span class="cd-lbl">Detik</span></div>
        <div class="cd-box"><span class="cd-num" id="cd-pct">--%</span><span class="cd-lbl">Sisa</span></div>
      </div>
    </div>
    <div style="margin-top:14px;text-align:center;font-size:11px;color:var(--text3)">
      Dikunci selama <?= LOGIN_LOCKOUT_MINUTES ?> menit &bull; IP: <?= htmlspecialchars(getClientIP()) ?>
    </div>

    <?php else: ?>
    <!-- ══ NORMAL STATE ══ -->

    <?php if ($error): ?>
    <div class="login-error">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($failed > 0): ?>
    <?php
      $pct     = min(100, ($failed / LOGIN_MAX_ATTEMPTS) * 100);
      $danger  = $failed >= LOGIN_MAX_ATTEMPTS - 1;
      $barColor= $danger ? 'var(--red)' : ($failed >= 2 ? '#f59e0b' : 'var(--gold)');
    ?>
    <div class="attempt-bar">
      <div class="attempt-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>" autocomplete="off">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               placeholder="Masukkan username" autofocus autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label class="form-label">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pw-field" class="form-control"
                 placeholder="Masukkan password" autocomplete="current-password">
          <button type="button" class="eye-btn" onclick="togglePw()">&#128065;</button>
        </div>
      </div>
      <button type="submit" class="btn btn-gold btn-full" style="padding:13px">
        &#128272; Masuk ke Dashboard
      </button>
    </form>

    <?php if ($failed > 0): ?>
    <div style="margin-top:12px;text-align:center;font-size:11px;color:<?= $danger?'var(--red)':'var(--text3)' ?>">
      <?= $failed ?>/<?= LOGIN_MAX_ATTEMPTS ?> percobaan gagal
      &mdash; <?= LOGIN_MAX_ATTEMPTS - $failed ?> tersisa sebelum dikunci <?= LOGIN_LOCKOUT_MINUTES ?> menit
    </div>
    <?php endif; ?>

    <?php endif; // end locked check ?>

    <div style="margin-top:24px;padding-top:18px;border-top:1px solid var(--border);text-align:center;font-size:10px;color:var(--text3)">
      <?= APP_DESC ?> &bull; Build <?= APP_BUILD ?>
    </div>
  </div>
</div>

<div class="version-badge">&#9889; v<?= APP_VERSION ?></div>

<script>
function togglePw() {
  const f = document.getElementById('pw-field');
  if (!f) return;
  f.type = f.type === 'password' ? 'text' : 'password';
  f.nextElementSibling.textContent = f.type === 'password' ? '\uD83D\uDC41' : '\uD83D\uDE48';
}

<?php if ($locked && $lockSec > 0): ?>
// Countdown lockout — form tidak muncul sampai secs == 0
const TOTAL = <?= (int)($lockSec + 1) ?>;
let   secs  = <?= (int)$lockSec ?>;

function tickLock() {
  if (secs <= 0) { location.reload(); return; }
  const m   = Math.floor(secs / 60);
  const s   = secs % 60;
  const pct = Math.round((secs / TOTAL) * 100);
  document.getElementById('cd-m').textContent   = String(m).padStart(2, '0');
  document.getElementById('cd-s').textContent   = String(s).padStart(2, '0');
  document.getElementById('cd-pct').textContent = pct + '%';
  secs--;
  setTimeout(tickLock, 1000);
}
tickLock();
<?php endif; ?>
</script>
</body>
</html>
