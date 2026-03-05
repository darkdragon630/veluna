<?php
/**
 * PortoFolio /create — Setup akun administrator
 * HANYA bisa diakses jika belum ada user di database.
 * Setelah akun dibuat, halaman ini OTOMATIS DITUTUP (403).
 */
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php'
require_once __DIR__ . '/config/config.php';

// Jika sudah ada user => 403
if (userExists()) {
    http_response_code(403);
    die(<<<HTML
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<title>Akses Ditolak - PortoFolio</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0a0c0f;color:#dde1eb;font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#14181e;border:1px solid #28303e;border-radius:16px;padding:40px;max-width:420px;text-align:center}
h2{color:#ef4444;margin-bottom:12px;font-size:20px}
p{color:#8a8fa0;font-size:13px;line-height:1.6;margin-bottom:24px}
a{display:inline-block;background:#f0b429;color:#0a0c0f;padding:10px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px}
</style></head><body>
<div class="box">
  <div style="font-size:48px;margin-bottom:16px">&#128274;</div>
  <h2>Halaman Setup Ditutup</h2>
  <p>Akun sudah pernah dibuat. Halaman pembuatan akun tidak bisa diakses lagi untuk alasan keamanan.</p>
  <a href="login.php">Ke Halaman Login</a>
</div></body></html>
HTML);
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['_csrf'] ?? '')) {
        $error = 'Token keamanan tidak valid. Refresh halaman.';
    } else {
        $username  = trim($_POST['username']  ?? '');
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 40) {
            $error = 'Username harus 3 hingga 40 karakter.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username hanya boleh huruf, angka, dan underscore.';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'Password harus mengandung minimal 1 huruf kapital.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Password harus mengandung minimal 1 angka.';
        } elseif ($password !== $password2) {
            $error = 'Konfirmasi password tidak cocok.';
        } elseif (!createUser($username, $password)) {
            $error = 'Gagal menyimpan akun. Pastikan database sudah siap.';
        } else {
            $success = true;
        }
    }
}
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Akun - PortoFolio</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
<style>
.setup-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.setup-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:40px;width:100%;max-width:460px}
.notice{background:rgba(240,180,41,.08);border:1px solid rgba(240,180,41,.25);border-radius:10px;padding:12px 16px;font-size:11px;color:var(--text2);margin-bottom:22px;line-height:1.7}
.pwd-rules{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:11px;color:var(--text3);margin-top:6px;line-height:1.9}
.rule{display:flex;align-items:center;gap:6px}.rule.ok{color:var(--green)}.rule.fail{color:var(--red)}
.success-box{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:12px;padding:24px;text-align:center}
.input-wrap{position:relative}.input-wrap input{padding-right:44px}
.eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;padding:4px}
</style>
</head>
<body>
<div class="setup-wrap">
  <div class="setup-card">
    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:42px;margin-bottom:8px">&#128737;</div>
      <div style="font-family:var(--font-head);font-size:22px;font-weight:800">Setup <span style="color:var(--gold)">Akun</span></div>
      <div style="color:var(--text3);font-size:12px;margin-top:4px">Buat akun administrator PortoFolio</div>
    </div>

    <div class="notice">
      &#9888;&#65039; <strong>Halaman ini hanya muncul sekali.</strong><br>
      Setelah akun berhasil dibuat, URL <code style="color:var(--gold)">/create</code> akan otomatis ditutup permanen.
    </div>

    <?php if ($success): ?>
    <div class="success-box">
      <div style="font-size:40px;margin-bottom:12px">&#9989;</div>
      <div style="font-size:16px;font-weight:700;color:var(--green);margin-bottom:8px">Akun Berhasil Dibuat!</div>
      <div style="font-size:12px;color:var(--text2);margin-bottom:20px">Halaman setup telah ditutup permanen. Silakan login.</div>
      <a href="<?= BASE_URL ?>login.php" class="btn btn-gold btn-full" style="display:block;padding:12px;text-align:center;text-decoration:none">
        &#128272; Login Sekarang
      </a>
    </div>

    <?php else: ?>
    <?php if ($error): ?>
    <div class="login-error" style="margin-bottom:18px">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="setup-form">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" id="inp-user"
          placeholder="Contoh: admin, porto_user" autofocus autocomplete="username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
          oninput="checkForm()">
        <div class="form-hint">3-40 karakter. Hanya huruf, angka, underscore.</div>
      </div>
      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Password</label>
        <div class="input-wrap">
          <input type="password" name="password" class="form-control" id="inp-pass"
            placeholder="Minimal 8 karakter" autocomplete="new-password" oninput="checkPwd()">
          <button type="button" class="eye-btn" onclick="toggleEye('inp-pass',this)">&#128065;</button>
        </div>
        <div class="pwd-rules" id="pwd-rules">
          <div class="rule" id="r-len">&#9675; Minimal 8 karakter</div>
          <div class="rule" id="r-cap">&#9675; Mengandung huruf kapital (A-Z)</div>
          <div class="rule" id="r-num">&#9675; Mengandung angka (0-9)</div>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label class="form-label">Konfirmasi Password</label>
        <div class="input-wrap">
          <input type="password" name="password2" class="form-control" id="inp-pass2"
            placeholder="Ulangi password" autocomplete="new-password" oninput="checkForm()">
          <button type="button" class="eye-btn" onclick="toggleEye('inp-pass2',this)">&#128065;</button>
        </div>
        <div id="match-hint" style="font-size:11px;margin-top:5px;color:var(--text3)"></div>
      </div>
      <button type="submit" class="btn btn-gold btn-full" id="submit-btn" style="padding:13px" disabled>
        &#128737; Buat Akun &amp; Tutup Halaman Setup
      </button>
    </form>
    <?php endif; ?>

    <div style="margin-top:20px;text-align:center;font-size:10px;color:var(--text3)">
      PortoFolio <?= APP_VERSION ?> &bull; Password dienkripsi bcrypt (cost 12)
    </div>
  </div>
</div>
<script>
function toggleEye(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '\u{1F441}' : '\u{1F648}';
}
function setRule(id, ok) {
  const el = document.getElementById(id);
  const txt = el.textContent.slice(2);
  el.className = 'rule ' + (ok ? 'ok' : 'fail');
  el.textContent = (ok ? '\u2713 ' : '\u2717 ') + txt;
}
function checkPwd() {
  const p = document.getElementById('inp-pass').value;
  setRule('r-len', p.length >= 8);
  setRule('r-cap', /[A-Z]/.test(p));
  setRule('r-num', /[0-9]/.test(p));
  checkForm();
}
function checkForm() {
  const u = document.getElementById('inp-user').value.trim();
  const p = document.getElementById('inp-pass').value;
  const p2 = document.getElementById('inp-pass2').value;
  const hint = document.getElementById('match-hint');
  const pwdOk = p.length >= 8 && /[A-Z]/.test(p) && /[0-9]/.test(p);
  const userOk = u.length >= 3 && /^[a-zA-Z0-9_]+$/.test(u);
  const matchOk = p === p2 && p2.length > 0;
  if (p2.length > 0) {
    hint.textContent = matchOk ? '\u2713 Password cocok' : '\u2717 Password tidak cocok';
    hint.style.color = matchOk ? 'var(--green)' : 'var(--red)';
  } else { hint.textContent = ''; }
  document.getElementById('submit-btn').disabled = !(userOk && pwdOk && matchOk);
}
</script>
</body>
</html>
