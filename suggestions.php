<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
$isLoggedIn = isLoggedIn(); // Saran bisa diakses tanpa login

$pageTitle  = 'Saran Fitur';
$activePage = 'suggestions';

// Get recent suggestions
$db = getDB();
$suggestions = $db->query("SELECT * FROM feature_suggestions ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div class="page-title-group">
    <div class="page-title">💡 Saran <span>Fitur</span></div>
    <div class="page-subtitle">Kirimkan ide fitur baru yang ingin Anda lihat di PortoFolio</div>
  </div>
</div>

<div class="suggestion-wrap">
  <!-- FORM CARD -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-header">
      <div class="card-title">✍️ Kirim Saran Fitur Baru</div>
      <div style="display:flex;align-items:center;gap:8px">
        <span class="badge badge-green">📱 Via WhatsApp</span>
        <span style="font-size:11px;color:var(--text3)">Dikirim ke developer</span>
      </div>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:20px">
        ℹ️ Setelah submit, Anda akan diarahkan ke WhatsApp untuk mengirim saran secara langsung ke developer.
      </div>

      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Nama Anda (opsional)</label>
        <input type="text" class="form-control" id="sug-name" placeholder="Nama pengirim...">
      </div>

      <div class="form-group" style="margin-bottom:16px">
        <label class="form-label">Judul Saran Fitur</label>
        <input type="text" class="form-control" id="sug-title" placeholder="Misal: Tambahkan fitur grafik candlestick untuk saham">
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label class="form-label">Deskripsi Detail</label>
        <textarea class="form-control" id="sug-detail" rows="5"
          placeholder="Jelaskan fitur yang Anda inginkan secara detail. Semakin detail, semakin mudah untuk diimplementasikan..."
          style="resize:vertical;min-height:120px"></textarea>
      </div>

      <div class="form-group" style="margin-bottom:20px">
        <label class="form-label">Prioritas / Urgensi</label>
        <select class="form-control" id="sug-priority">
          <option value="Nice to have">🟡 Nice to have — Bagus kalau ada</option>
          <option value="Penting">🟠 Penting — Sangat dibutuhkan</option>
          <option value="Kritis">🔴 Kritis — Harus segera ada</option>
        </select>
      </div>

      <div style="display:flex;gap:10px">
        <button class="btn btn-gold" style="flex:1;padding:13px" onclick="submitSuggestion()">
          📱 Kirim via WhatsApp
        </button>
        <button class="btn btn-outline" onclick="previewMessage()">👁 Preview Pesan</button>
      </div>
    </div>
  </div>

  <!-- PREVIEW MODAL -->
  <div class="modal-overlay" id="preview-modal">
    <div class="modal">
      <div class="modal-title">👁 Preview Pesan WhatsApp</div>
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;font-size:12px;line-height:1.8;white-space:pre-wrap;margin-bottom:20px;max-height:300px;overflow-y:auto" id="msg-preview"></div>
      <div class="modal-footer">
        <button class="btn btn-gold" style="flex:1" onclick="sendToWA()">📱 Kirim ke WhatsApp</button>
        <button class="btn btn-outline" onclick="closeModal('preview-modal')">Tutup</button>
      </div>
    </div>
  </div>

  <!-- RECENT SUGGESTIONS -->
  <div class="card suggestion-list-card">
    <div class="card-header">
      <div class="card-title">📋 Riwayat Saran (<?= count($suggestions) ?>)</div>
      <span style="font-size:11px;color:var(--text3)">20 saran terbaru</span>
    </div>
    <?php if (empty($suggestions)): ?>
    <div class="empty">
      <div class="e-icon">💡</div>
      <div class="e-title">Belum Ada Saran</div>
      <div class="e-sub">Jadilah yang pertama mengirimkan saran fitur!</div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th style="width:36px">#</th>
          <th style="width:110px">Nama</th>
          <th>Judul & Deskripsi</th>
          <th style="width:110px;text-align:center">Waktu</th>
        </tr></thead>
        <tbody>
        <?php foreach ($suggestions as $i => $s):
          // Pisahkan judul (baris pertama) dan deskripsi (sisa teks)
          $parts  = explode("\n\n", $s['suggestion'], 2);
          $judul  = trim($parts[0]);
          $detail = isset($parts[1]) ? trim($parts[1]) : '';
        ?>
        <tr>
          <td style="color:var(--text3);text-align:center"><?= count($suggestions) - $i ?></td>
          <td>
            <span style="font-size:12px"><?= htmlspecialchars($s['sender_name'] ?: 'Anonim') ?></span>
          </td>
          <td>
            <div style="font-weight:600;font-size:12px;color:var(--text);margin-bottom:3px">
              <?= htmlspecialchars($judul) ?>
            </div>
            <?php if ($detail): ?>
            <div style="font-size:11px;color:var(--text3);line-height:1.5;max-width:420px">
              <?= nl2br(htmlspecialchars(mb_substr($detail, 0, 160) . (mb_strlen($detail) > 160 ? '…' : ''))) ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="text-align:center;white-space:nowrap;color:var(--text3);font-size:11px">
            <?= date('d M Y', strtotime($s['created_at'])) ?><br>
            <span style="color:var(--border2)"><?= date('H:i', strtotime($s['created_at'])) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const WA_NUMBER = '<?= WA_NUMBER ?>';

function buildMessage() {
  const name     = document.getElementById('sug-name').value.trim();
  const title    = document.getElementById('sug-title').value.trim();
  const detail   = document.getElementById('sug-detail').value.trim();
  const priority = document.getElementById('sug-priority').value;

  if (!title) { toast('Masukkan judul saran fitur', 'error'); return null; }
  if (!detail) { toast('Masukkan deskripsi detail saran', 'error'); return null; }

  const msg = `*💡 SARAN FITUR — PortoFolio v<?= APP_VERSION ?>*\n\n` +
    `*👤 Dari:* ${name || 'Anonim'}\n` +
    `*📌 Judul:* ${title}\n` +
    `*⚡ Prioritas:* ${priority}\n\n` +
    `*📝 Deskripsi:*\n${detail}\n\n` +
    `_Dikirim dari PortoFolio Investment Tracker — ${new Date().toLocaleString('id-ID')}_`;

  return { msg, name, full: `${title}\n\n${detail}` };
}

function previewMessage() {
  const data = buildMessage();
  if (!data) return;
  document.getElementById('msg-preview').textContent = data.msg;
  openModal('preview-modal');
}

async function saveToDB(name, fullText) {
  try {
    await fetch(BASE_URL + 'api/suggestions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sender_name: name, suggestion: fullText })
    });
  } catch(e) {}
}

async function submitSuggestion() {
  const data = buildMessage();
  if (!data) return;
  await saveToDB(data.name, data.full);
  sendToWA(data.msg);
}

function sendToWA(msg) {
  if (!msg) {
    const data = buildMessage();
    if (!data) return;
    msg = data.msg;
  }
  closeModal('preview-modal');
  const url = `https://wa.me/${WA_NUMBER}?text=${encodeURIComponent(msg)}`;
  window.open(url, '_blank');
  toast('Membuka WhatsApp... ✅', 'success');
  // Clear form
  setTimeout(() => {
    document.getElementById('sug-name').value = '';
    document.getElementById('sug-title').value = '';
    document.getElementById('sug-detail').value = '';
    setTimeout(() => location.reload(), 1500);
  }, 1000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
