<?php
// maintenance_page.php — Ditampilkan saat maintenance aktif, variabel disiapkan di index.php
// $mTitle, $mMessage, $mEndTs, BASE_URL tersedia dari scope pemanggil
$mTitle   = $mTitle   ?? 'Sedang dalam Pemeliharaan';
$mMessage = $mMessage ?? '';
$mEndTs   = $mEndTs   ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= $mTitle ?> — PortoFolio</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0c0f;--surface:#14181e;--surface2:#1c2130;--border:#283042;
  --gold:#f0b429;--gold2:#e5a000;--text:#dde1eb;--text2:#9aa0b0;--text3:#5a6070;
  --red:#ef4444;--green:#22c55e;
  --font:'Syne',system-ui,sans-serif;--mono:'DM Mono',monospace;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow:hidden}

/* animated bg particles */
.bg-grid{position:fixed;inset:0;background-image:linear-gradient(rgba(240,180,41,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(240,180,41,.03) 1px,transparent 1px);background-size:48px 48px;pointer-events:none}
.glow{position:fixed;width:600px;height:600px;border-radius:50%;background:radial-gradient(circle,rgba(240,180,41,.07) 0%,transparent 70%);top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;animation:pulse 4s ease-in-out infinite}
@keyframes pulse{0%,100%{transform:translate(-50%,-50%) scale(1);opacity:.5}50%{transform:translate(-50%,-50%) scale(1.2);opacity:1}}

.card{background:var(--surface);border:1px solid var(--border);border-radius:24px;padding:48px 40px;max-width:520px;width:100%;text-align:center;position:relative;z-index:1;box-shadow:0 32px 80px rgba(0,0,0,.5)}

/* gear spinner */
.gear-wrap{margin-bottom:28px;position:relative;display:inline-block;width:72px;height:72px}
.gear{width:72px;height:72px;animation:spin 8s linear infinite}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.gear-inner{position:absolute;inset:18px;background:var(--surface);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px}

.badge-maint{display:inline-flex;align-items:center;gap:6px;background:rgba(240,180,41,.12);border:1px solid rgba(240,180,41,.3);border-radius:20px;padding:5px 14px;font-size:11px;color:var(--gold);font-family:var(--mono);letter-spacing:.5px;margin-bottom:18px}
.dot-live{width:7px;height:7px;border-radius:50%;background:var(--gold);animation:blink 1.2s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}

h1{font-size:26px;font-weight:800;margin-bottom:10px;line-height:1.2}
h1 span{color:var(--gold)}
.subtitle{font-size:13px;color:var(--text2);line-height:1.7;margin-bottom:28px}

/* Countdown */
.countdown-wrap{background:var(--surface2);border:1px solid var(--border);border-radius:14px;padding:20px 24px;margin-bottom:28px}
.countdown-label{font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:14px;font-family:var(--mono)}
.countdown-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.count-box{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:12px 6px}
.count-num{font-size:28px;font-weight:700;font-family:var(--mono);color:var(--gold);line-height:1;display:block}
.count-lbl{font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-top:4px;display:block}
.no-time{font-size:12px;color:var(--text3);font-family:var(--mono)}

/* progress */
.progress-wrap{margin-bottom:22px}
.progress-track{height:3px;background:var(--surface2);border-radius:2px;overflow:hidden}
.progress-move{height:100%;width:30%;background:linear-gradient(90deg,transparent,var(--gold),transparent);animation:slide 2s linear infinite}
@keyframes slide{from{transform:translateX(-100%)}to{transform:translateX(400%)}}

footer{font-size:10px;color:var(--text3);font-family:var(--mono)}
footer a{color:var(--gold);text-decoration:none}
</style>
</head>
<body>
<div class="bg-grid"></div>
<div class="glow"></div>

<div class="card">
  <div class="gear-wrap">
    <svg class="gear" viewBox="0 0 72 72" fill="none">
      <path d="M36 22a14 14 0 100 28 14 14 0 000-28z" fill="none" stroke="#f0b429" stroke-width="2"/>
      <path fill="#f0b42960" d="M33 4h6l1.5 6.5a22 22 0 014.8 2l5.7-3.3 4.2 4.2-3.3 5.7a22 22 0 012 4.8L60 25.5v5l-6 1.5a22 22 0 01-2 4.8l3.3 5.7-4.2 4.2-5.7-3.3a22 22 0 01-4.8 2L39 52h-6l-1.5-6.5a22 22 0 01-4.8-2l-5.7 3.3-4.2-4.2 3.3-5.7a22 22 0 01-2-4.8L12 30.5v-5l6-1.5a22 22 0 012-4.8l-3.3-5.7 4.2-4.2 5.7 3.3a22 22 0 014.8-2L33 4z"/>
    </svg>
    <div class="gear-inner">🔧</div>
  </div>

  <div class="badge-maint"><span class="dot-live"></span>MAINTENANCE</div>

  <h1><?= $mTitle ?></h1>
  <p class="subtitle">
    <?= $mMessage ? nl2br(htmlspecialchars($mMessage)) : 'Kami sedang melakukan peningkatan sistem.<br>Harap bersabar, website akan segera kembali normal.' ?>
  </p>

  <?php if ($mEndTs && $mEndTs > time()): ?>
  <div class="countdown-wrap">
    <div class="countdown-label">⏱ Selesai dalam</div>
    <div class="countdown-grid">
      <div class="count-box"><span class="count-num" id="cd-d">00</span><span class="count-lbl">Hari</span></div>
      <div class="count-box"><span class="count-num" id="cd-h">00</span><span class="count-lbl">Jam</span></div>
      <div class="count-box"><span class="count-num" id="cd-m">00</span><span class="count-lbl">Menit</span></div>
      <div class="count-box"><span class="count-num" id="cd-s">00</span><span class="count-lbl">Detik</span></div>
    </div>
  </div>
  <?php else: ?>
  <div class="countdown-wrap">
    <div class="no-time">⏳ Estimasi waktu selesai belum ditentukan</div>
  </div>
  <?php endif; ?>

  <div class="progress-wrap">
    <div class="progress-track"><div class="progress-move"></div></div>
  </div>

  <footer>
    PortoFolio &bull; Maintenance Mode &bull;
    <a href="<?= BASE_URL ?>login.php">Admin Login</a>
  </footer>
</div>

<script>
<?php if ($mEndTs && $mEndTs > time()): ?>
const endTs = <?= (int)$mEndTs ?> * 1000;
function tick() {
  const diff = Math.max(0, endTs - Date.now());
  const d = Math.floor(diff / 86400000);
  const h = Math.floor((diff % 86400000) / 3600000);
  const m = Math.floor((diff % 3600000) / 60000);
  const s = Math.floor((diff % 60000) / 1000);
  document.getElementById('cd-d').textContent = String(d).padStart(2,'0');
  document.getElementById('cd-h').textContent = String(h).padStart(2,'0');
  document.getElementById('cd-m').textContent = String(m).padStart(2,'0');
  document.getElementById('cd-s').textContent = String(s).padStart(2,'0');
  if (diff <= 0) {
    // Maintenance selesai — refresh ke overview
    setTimeout(() => location.reload(), 2000);
    return;
  }
  setTimeout(tick, 1000);
}
tick();
// Poll API tiap 30 detik, jika sudah tidak aktif langsung reload
setInterval(async () => {
  try {
    const r = await fetch('<?= BASE_URL ?>api/maintenance.php');
    const d = await r.json();
    if (!d.is_active) location.reload();
  } catch(e){}
}, 30000);
<?php else: ?>
// Tanpa waktu — poll API tiap 30 detik
setInterval(async () => {
  try {
    const r = await fetch('<?= BASE_URL ?>api/maintenance.php');
    const d = await r.json();
    if (!d.is_active) location.reload();
  } catch(e){}
}, 30000);
<?php endif; ?>
</script>
</body>
</html>
