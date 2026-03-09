<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

// Cek maintenance mode (admin tetap bisa lihat overview)
$maintenance = getMaintenance();
if ($maintenance['is_active'] && !isLoggedIn()) {
    // Tampilkan halaman maintenance, hentikan eksekusi lebih lanjut
    $mEndTs   = !empty($maintenance['end_time']) ? strtotime($maintenance['end_time']) : null;
    $mTitle   = htmlspecialchars($maintenance['title'] ?? 'Sedang dalam Pemeliharaan');
    $mMessage = $maintenance['message'] ?? '';
    if ($maintenance['mode'] === 'custom' && !empty($maintenance['custom_html'])) {
        // Custom HTML — output langsung
        echo $maintenance['custom_html'];
        exit;
    }
    // Default maintenance page
    include __DIR__ . '/maintenance_page.php';
    exit;
}

// Overview = halaman publik — tidak perlu login
$cryptoPrices = getCryptoPrices();
$targets      = getTargets();
$allStats     = getAllStats($cryptoPrices);
$soldStats    = getSoldStats();
$cashStats    = getCashStats();
$recentTx     = getRecentTransactions(25);
$isLoggedIn   = isLoggedIn();

// Total target = sum semua target kategori
$totalTarget  = calcTotalTarget($targets);
$totalProgress = $totalTarget > 0 ? min(($allStats['totalValue'] / $totalTarget) * 100, 100) : 0;

$pageTitle  = 'Overview';
$activePage = 'overview';

$catData = [];
foreach (CATEGORIES as $cat => $cfg) {
    $catData[$cat] = [
        'invs'  => getInvestments($cat),
        'stats' => getCatStats($cat, $cryptoPrices),
    ];
}

// Best performer
$performers = [];
foreach (CATEGORIES as $cat => $cfg) {
    if (!$cfg['has_pnl']) continue;
    $s = $catData[$cat]['stats'];
    if ($s['count'] > 0) $performers[$cat] = $s['pnlPct'];
}
arsort($performers);
$bestCat = array_key_first($performers) ?? null;
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div class="page-title-group">
    <div class="page-title">📊 Over<span>view</span></div>
    <div class="page-subtitle">Ringkasan keseluruhan portofolio investasi Anda</div>
  </div>
  <div class="page-actions">
    <span class="crypto-live"><span class="live-dot"></span>Crypto Live</span>
    <button class="btn btn-outline btn-sm" onclick="refreshCrypto()">⟳ Refresh</button>
    <button class="btn btn-outline btn-sm" onclick="doExportPDF()">⬇ PDF</button>
    <?php if ($isLoggedIn): ?>
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-gold btn-sm">📋 Kelola</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>login.php?redirect=dashboard.php" class="btn btn-gold btn-sm">🔐 Kelola</a>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     SUMMARY STATS — 6 kartu utama
════════════════════════════════════════════════ -->
<?php
  $upnl     = getTotalUnrealizedPnl();
  $totalPnl = $upnl['net'] + $soldStats['total_realized_pnl'];
?>
<div class="stats-grid">
  <!-- Baris 1: Portofolio -->
  <div class="stat-card">
    <div class="stat-label">Total Portofolio</div>
    <div class="stat-value gold"><?= fmtIDR($allStats['totalValue']) ?></div>
    <div class="stat-sub">Nilai aktif semua aset</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Modal Aktif</div>
    <div class="stat-value"><?= fmtIDR($allStats['totalCost']) ?></div>
    <div class="stat-sub">Modal yang masih berjalan</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Target Total</div>
    <div class="stat-value gold"><?= number_format($totalProgress, 1) ?>%</div>
    <div class="stat-sub">dari <?= fmtIDR($totalTarget ?: 0) ?></div>
  </div>
  <div class="stat-card" style="border-color:rgba(34,197,94,0.3)">
    <div class="stat-label">💵 Saldo Kas</div>
    <div class="stat-value <?= $cashStats['balance'] >= 0 ? 'green' : 'red' ?>">
      <?= fmtIDR($cashStats['balance']) ?>
    </div>
    <div class="stat-sub">
      <?= $cashStats['topup_cnt'] + $cashStats['from_sale_cnt'] ?> entri
      <?php if ($cashStats['from_sale'] > 0): ?>
        &bull; +<?= fmtIDR($cashStats['from_sale']) ?> dari jual
      <?php endif; ?>
    </div>
  </div>

  <!-- Baris 2: PnL -->
  <div class="stat-card" style="border-color:rgba(<?= $upnl['net']>=0?'34,197,94':'239,68,68' ?>,0.3)">
    <div class="stat-label">📈 Unrealized PnL</div>
    <div class="stat-value <?= pnlClass($upnl['net']) ?>"><?= pnlSign($upnl['net']) . fmtIDR($upnl['net']) ?></div>
    <div class="stat-sub">
      <span style="color:var(--green)">+<?= fmtIDR($upnl['profit']) ?></span>
      / <span style="color:var(--red)">-<?= fmtIDR($upnl['loss']) ?></span>
      &bull; Posisi aktif
    </div>
  </div>
  <div class="stat-card" style="border-color:rgba(240,180,41,0.3)">
    <div class="stat-label">💸 Realized PnL</div>
    <div class="stat-value gold"><?= pnlSign($soldStats['total_realized_pnl']) . fmtIDR($soldStats['total_realized_pnl']) ?></div>
    <div class="stat-sub">
      <?php if ($soldStats['total_sold'] > 0): ?>
        💰 Jual: <?= pnlSign($soldStats['realized_from_sell']) . fmtIDR($soldStats['realized_from_sell']) ?><br>
      <?php endif; ?>
      <?php if ($soldStats['realized_from_savings'] != 0): ?>
        🏦 Bunga tabungan: <?= pnlSign($soldStats['realized_from_savings']) . fmtIDR(abs($soldStats['realized_from_savings'])) ?><br>
      <?php endif; ?>
      <?php if ($soldStats['total_sold'] == 0 && $soldStats['realized_from_savings'] == 0): ?>
        Belum ada realized PnL
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card" style="border-color:rgba(<?= $totalPnl>=0?'34,197,94':'239,68,68' ?>,0.3)">
    <div class="stat-label">📊 Total PnL</div>
    <div class="stat-value <?= pnlClass($totalPnl) ?>"><?= pnlSign($totalPnl) . fmtIDR($totalPnl) ?></div>
    <div class="stat-sub">
      Unrealized <?= pnlSign($upnl['net']) . fmtIDR($upnl['net']) ?>
      + Realized <?= pnlSign($soldStats['total_realized_pnl']) . fmtIDR($soldStats['total_realized_pnl']) ?>
    </div>
  </div>
  <?php if ($bestCat): ?>
  <div class="stat-card">
    <div class="stat-label">⭐ Best Performer</div>
    <div class="stat-value green"><?= CATEGORIES[$bestCat]['icon'] ?> <?= CATEGORIES[$bestCat]['label'] ?></div>
    <div class="stat-sub"><?= pnlSign($performers[$bestCat]) . number_format($performers[$bestCat], 2) ?>% return</div>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════
     CASH OVERVIEW — KARTU TERPISAH
════════════════════════════════════════════════ -->
<div class="section-title">💵 Overview <span>Kas</span>
</div>
<div class="cash-overview-card card" style="margin-bottom:28px">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0;border-radius:var(--radius);overflow:hidden">
    <!-- SALDO -->
    <div style="padding:22px 24px;border-right:1px solid var(--border)">
      <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px">💵 Saldo Kas Tersedia</div>
      <div style="font-size:28px;font-weight:500;color:var(--<?= $cashStats['balance']>=0?'green':'red' ?>)">
        <?= fmtIDR($cashStats['balance']) ?>
      </div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px">
        Saldo aktif yang dapat digunakan
      </div>
    </div>
    <!-- TOPUP -->
    <div style="padding:22px 24px;border-right:1px solid var(--border)">
      <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px">📥 Total Kas Masuk Manual</div>
      <div style="font-size:22px;font-weight:500;color:var(--green)"><?= fmtIDR($cashStats['topup']) ?></div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px"><?= $cashStats['topup_cnt'] ?> penambahan manual</div>
    </div>
    <!-- FROM SALE -->
    <div style="padding:22px 24px;border-right:1px solid var(--border)">
      <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px">💸 Masuk dari Penjualan</div>
      <div style="font-size:22px;font-weight:500;color:var(--gold)"><?= fmtIDR($cashStats['from_sale']) ?></div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px"><?= $cashStats['from_sale_cnt'] ?> transaksi jual</div>
    </div>
    <!-- WITHDRAWAL -->
    <div style="padding:22px 24px">
      <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px">📤 Total Penarikan</div>
      <div style="font-size:22px;font-weight:500;color:var(--red)"><?= $cashStats['withdrawal'] < 0 ? fmtIDR($cashStats['withdrawal']) : 'Rp 0' ?></div>
      <div style="font-size:11px;color:var(--text2);margin-top:6px"><?= $cashStats['withdrawal_cnt'] ?> penarikan</div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     OVERVIEW INVESTASI DIJUAL (Realized)
════════════════════════════════════════════════ -->
<?php if ($soldStats['total_sold'] > 0): ?>
<div class="section-title" style="margin-top:8px">💸 Overview Investasi <span>Terjual</span></div>
<div class="stats-grid" style="margin-bottom:28px">
  <div class="stat-card" style="border-color:rgba(240,180,41,0.3)">
    <div class="stat-label">💰 Total Cash Diterima</div>
    <div class="stat-value gold"><?= fmtIDR($soldStats['total_cash']) ?></div>
    <div class="stat-sub">Dari <?= $soldStats['total_sold'] ?> transaksi jual</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">🏷 Modal Aset Terjual</div>
    <div class="stat-value"><?= fmtIDR($soldStats['total_modal']) ?></div>
    <div class="stat-sub">Modal awal dari aset yang sudah dilepas</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">📊 Total Dijual</div>
    <div class="stat-value"><?= $soldStats['total_sold'] ?> transaksi</div>
    <div class="stat-sub">Aset yang sudah dilepas</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">✅ Keuntungan Realized</div>
    <div class="stat-value <?= pnlClass($soldStats['total_realized_pnl']) ?>">
      <?= pnlSign($soldStats['total_realized_pnl']) . fmtIDR($soldStats['total_realized_pnl']) ?>
    </div>
    <div class="stat-sub">
      <?php $realPct = $soldStats['total_modal'] > 0 ? ($soldStats['total_realized_pnl']/$soldStats['total_modal']*100) : 0; ?>
      <?= pnlSign($realPct) . number_format($realPct,2) ?>% dari modal terjual
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════
     PnL BREAKDOWN per kategori
════════════════════════════════════════════════ -->
<div class="section-title">📈 Komposisi PnL <span>per Kategori</span></div>
<div class="pnl-breakdown" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px">
<?php
$totalPnlSum = 0;
foreach (CATEGORIES as $cat => $cfg):
    $s = $catData[$cat]['stats'];
    if ($s['count'] === 0) continue;
    $totalPnlSum += $s['pnl'];
    if (!$cfg['has_pnl']) continue;
?>
  <div class="pnl-chip" style="background:<?= $cfg['color'] ?>18;border:1px solid <?= $cfg['color'] ?>40;border-radius:10px;padding:10px 16px;flex:1;min-width:140px">
    <div style="font-size:11px;color:var(--text3);margin-bottom:5px"><?= $cfg['icon'] ?> <?= $cfg['label'] ?></div>
    <div style="font-size:16px;font-weight:500;color:var(--<?= pnlClass($s['pnl']) ?>)"><?= pnlSign($s['pnl']) . fmtIDR($s['pnl']) ?></div>
    <div style="font-size:10px;color:var(--text3);margin-top:3px"><?= pnlSign($s['pnlPct']) . number_format($s['pnlPct'],2) ?>%</div>
  </div>
<?php endforeach; ?>
  <!-- TOTAL -->
  <div class="pnl-chip" style="background:var(--gold-dim);border:1px solid rgba(240,180,41,0.4);border-radius:10px;padding:10px 16px;flex:1;min-width:140px">
    <div style="font-size:11px;color:var(--text3);margin-bottom:5px">📊 Total PnL</div>
    <div style="font-size:16px;font-weight:600;color:var(--<?= pnlClass($allStats['pnl']) ?>)"><?= pnlSign($allStats['pnl']) . fmtIDR($allStats['pnl']) ?></div>
    <div style="font-size:10px;color:var(--text3);margin-top:3px"><?= pnlSign($allStats['pnlPct']) . number_format($allStats['pnlPct'],2) ?>% dari modal</div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════
     TARGETS OVERVIEW + PROGRESS BARS
════════════════════════════════════════════════ -->
<div class="section-title">🎯 Target <span>Investasi</span>
  <?php if($isLoggedIn): ?>
  <a href="<?= BASE_URL ?>dashboard.php#targets" class="btn btn-outline btn-sm" style="margin-left:auto">⚙ Edit di Dashboard</a>
  <?php endif; ?>
</div>

<!-- Total target bar -->
<div class="card" style="margin-bottom:16px;padding:18px 22px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
    <div>
      <div style="font-family:var(--font-head);font-size:14px;font-weight:700">🏆 Total Portofolio</div>
      <div style="font-size:11px;color:var(--text3);margin-top:3px">
        <?= fmtIDR($allStats['totalValue']) ?> dari <?= $totalTarget>0 ? fmtIDR($totalTarget) : 'Belum diset' ?>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:24px;font-weight:600;color:var(--gold)"><?= number_format($totalProgress,1) ?>%</div>
      <?php if($allStats['pnl'] != 0): ?>
      <div style="font-size:11px;color:var(--<?= pnlClass($allStats['pnl']) ?>)">
        PnL: <?= pnlSign($allStats['pnl']) . fmtIDR($allStats['pnl']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="progress-bg" style="height:10px">
    <div class="progress-fill" style="width:<?= $totalProgress ?>%;background:linear-gradient(90deg,#e5a000,#f0b429)"></div>
  </div>
  <?php if($totalTarget > 0): ?>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-top:7px">
    <span>Sisa: <?= fmtIDR(max(0,$totalTarget-$allStats['totalValue'])) ?></span>
    <span><?= $totalProgress >= 100 ? '✅ Target tercapai!' : number_format($totalProgress,1).'% tercapai' ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- Per-category targets grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin-bottom:28px">
<?php foreach(CATEGORIES as $cat => $cfg):
  $s   = $catData[$cat]['stats'];
  $tgt = $targets[$cat] ?? 0;
  $pct = $tgt > 0 ? min($s['totalValue']/$tgt*100, 100) : 0;
?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;transition:border-color .2s"
     onmouseover="this.style.borderColor='<?= $cfg['color'] ?>50'" onmouseout="this.style.borderColor='var(--border)'">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <div style="display:flex;align-items:center;gap:9px">
      <span style="font-size:18px"><?= $cfg['icon'] ?></span>
      <div>
        <div style="font-family:var(--font-head);font-size:12px;font-weight:700"><?= $cfg['label'] ?></div>
        <div style="font-size:10px;color:var(--text3)"><?= $s['count'] ?> investasi</div>
      </div>
    </div>
    <div style="text-align:right">
      <div style="font-size:14px;font-weight:500;color:<?= $cfg['color'] ?>"><?= number_format($pct,1) ?>%</div>
      <?php if($cfg['has_pnl'] && $s['count']>0): ?>
      <div style="font-size:10px;color:var(--<?= pnlClass($s['pnl']) ?>)">PnL <?= pnlSign($s['pnl']).fmtIDR($s['pnl']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="progress-bg" style="height:5px;margin-bottom:7px">
    <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $cfg['color'] ?>"></div>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3)">
    <span><?= fmtIDR($s['totalValue']) ?></span>
    <span><?= $tgt>0 ? fmtIDR($tgt) : 'Belum diset' ?></span>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════
     CATEGORY CARDS
════════════════════════════════════════════════ -->
<div class="section-title">Portofolio per <span>Kategori</span></div>
<div class="categories-grid">
<?php foreach (CATEGORIES as $cat => $cfg):
  $d        = $catData[$cat];
  $stats    = $d['stats'];
  $invs     = $d['invs'];
  $target   = $targets[$cat] ?? 0;
  $progress = $target > 0 ? min(($stats['totalValue']/$target)*100, 100) : 0;
?>
<div class="cat-card" id="cat-card-<?= $cat ?>">
  <div class="cat-card-header">
    <div class="cat-icon-wrap">
      <div class="cat-icon" style="background:<?= $cfg['color'] ?>20;color:<?= $cfg['color'] ?>"><?= $cfg['icon'] ?></div>
      <div>
        <div class="cat-name"><?= $cfg['label'] ?></div>
        <div class="cat-count"><?= $stats['count'] ?> investasi aktif</div>
      </div>
    </div>
    <div class="cat-total-wrap">
      <div class="cat-total"><?= fmtIDR($stats['totalValue']) ?></div>
      <div class="cat-pct" style="color:<?= $cfg['color'] ?>"><?= number_format($progress,1) ?>% dari target</div>
    </div>
  </div>

  <div class="progress-wrap">
    <div class="progress-labels">
      <span><?= fmtIDR($stats['totalValue']) ?></span>
      <span>Target: <?= $target > 0 ? fmtIDR($target) : 'Belum diset' ?></span>
    </div>
    <div class="progress-bg">
      <div class="progress-fill" style="width:<?= $progress ?>%;background:<?= $cfg['color'] ?>"></div>
    </div>
  </div>

  <?php if ($cfg['has_pnl']): ?>
  <div class="pnl-toggle" onclick="togglePnl('<?= $cat ?>')">
    <span>
      PnL Detail
      <span class="badge badge-<?= pnlClass($stats['pnl']) ?>">
        <?= pnlSign($stats['pnl']) . fmtIDR($stats['pnl']) ?>
        (<?= pnlSign($stats['pnlPct']) . number_format($stats['pnlPct'],2) ?>%)
      </span>
    </span>
    <span class="pnl-chevron" id="chev-<?= $cat ?>">▼</span>
  </div>
  <?php else: ?>
  <div class="pnl-toggle" onclick="togglePnl('<?= $cat ?>')">
    <?php if ($cat === 'property'): ?>
      <?php
        $monthlyTotal = array_sum(array_map('getPropertyMonthlyIncome', $invs));
        $modal = $stats['totalCost'];
        $yieldAnn = $modal > 0 ? ($monthlyTotal*12/$modal*100) : 0;
      ?>
      <span>
        Pendapatan/bulan: <strong style="color:var(--green)"><?= fmtIDR($monthlyTotal) ?></strong>
        &nbsp;&bull;&nbsp; Yield: <strong style="color:var(--gold)"><?= number_format($yieldAnn,2) ?>%/thn</strong>
      </span>
    <?php else: ?>
      <span>Lihat Detail (<?= $stats['count'] ?> item)</span>
    <?php endif; ?>
    <span class="pnl-chevron" id="chev-<?= $cat ?>">▼</span>
  </div>
  <?php endif; ?>

  <div class="pnl-dropdown" id="pnl-dd-<?= $cat ?>">
    <div class="inv-list">
      <?php if (empty($invs)): ?>
      <div style="text-align:center;padding:20px 0;font-size:11px;color:var(--text3)">
        Belum ada investasi di kategori ini.
        <br><a href="<?= BASE_URL ?><?= $isLoggedIn ? 'dashboard.php?add='.$cat : 'login.php?redirect=dashboard.php%3Fadd='.$cat ?>"
               class="btn btn-outline btn-xs" style="margin-top:8px;display:inline-flex">
          <?= $isLoggedIn ? '+ Tambah' : '🔐 Login' ?>
        </a>
      </div>
      <?php else: ?>
        <?php foreach ($invs as $inv):
          $cost   = getInvCost($inv);
          $curVal = getInvCurrentValue($inv, $cryptoPrices);
          $pnl    = $curVal - $cost;
          $pnlPct = $cost > 0 ? ($pnl/$cost*100) : 0;
          $livePrice = null;
          if ($cat === 'crypto' && !empty($inv['ticker'])) {
              $coinId = $inv['coin_id'] ?? getCoinId($inv['ticker']);
              $livePrice = $cryptoPrices[$coinId] ?? null;
          }
          $monthlyIncome = ($cat === 'property') ? getPropertyMonthlyIncome($inv) : null;
          $yieldProp     = ($cat === 'property') ? getPropertyYield($inv) : null;
        ?>
        <div class="inv-item"<?php if ($cat === 'crypto'): ?>
          data-crypto-row="1"
          data-coin-id="<?= htmlspecialchars($coinId ?? '') ?>"
          data-qty="<?= (float)($inv['qty'] ?? 0) ?>"
          data-cost="<?= $cost ?>"
          data-upnl="<?= (float)($inv['unrealized_pnl'] ?? 0) ?>"
          data-rpnl="<?= (float)($inv['realized_pnl'] ?? 0) ?>"
          data-inv-id="<?= $inv['id'] ?>"
        <?php endif; ?>>
          <div class="inv-info">
            <div class="inv-name">
              <?= htmlspecialchars($inv['name'] ?: ($inv['ticker'] ?: '—')) ?>
              <?php if ($cat === 'crypto' && $inv['ticker']): ?>
                <?php if ($livePrice): ?>
                  <span class="crypto-live" id="live-<?= $inv['id'] ?>">
                    <span class="live-dot"></span><?= htmlspecialchars($inv['ticker']) ?> = <?= fmtIDR($livePrice) ?>
                  </span>
                <?php else: ?>
                  <span class="badge badge-neutral" id="live-<?= $inv['id'] ?>"><?= htmlspecialchars($inv['ticker']) ?> loading...</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <div class="inv-meta">
              <?php if ($cat === 'stocks'): ?>
                <?= fmtNum((float)$inv['qty'],0) ?> lot
                (<?= fmtNum((float)$inv['qty'] * SHARES_PER_LOT, 0) ?> lembar)
                <?php if ($inv['ticker']): ?>&bull; <?= htmlspecialchars($inv['ticker']) ?><?php endif; ?>
              <?php elseif ($cat === 'mutualFunds'): ?>
                <?= fmtNum((float)$inv['qty']) ?> unit<?php if ($inv['ticker']): ?> &bull; <?= htmlspecialchars($inv['ticker']) ?><?php endif; ?>
              <?php elseif ($cat === 'crypto' && $inv['qty']): ?>
                <?= fmtNum((float)$inv['qty']) ?> <?= htmlspecialchars($inv['ticker'] ?? 'koin') ?>
              <?php elseif ($cat === 'property' && $monthlyIncome > 0): ?>
                <span style="color:var(--green)">📅 Rp <?= number_format($monthlyIncome,0,',','.') ?>/bulan</span>
                &bull; Yield <?= number_format($yieldProp,2) ?>%/thn
              <?php endif; ?>
              <?php if ($inv['inv_date']): ?>&bull; <?= $inv['inv_date'] ?><?php endif; ?>
              <?php if ($inv['note']): ?>&bull; <?= htmlspecialchars($inv['note']) ?><?php endif; ?>
            </div>
          </div>
          <div class="inv-value-wrap">
            <div class="inv-value" <?php if($cat==='crypto'): ?>id="idx-val-<?= $inv['id'] ?>"<?php endif; ?>><?= fmtIDR($curVal) ?></div>
            <?php if ($cfg['has_pnl']): ?>
            <div class="inv-pnl" <?php if($cat==='crypto'): ?>id="idx-pnl-<?= $inv['id'] ?>"<?php endif; ?> style="color:var(--<?= pnlClass($pnl) ?>)">
              <?= pnlSign($pnl) . fmtIDR($pnl) ?> (<?= pnlSign($pnlPct) . number_format($pnlPct,2) ?>%)
            </div>
            <?php endif; ?>
            <?php
              $upnlInv = (float)($inv['unrealized_pnl'] ?? 0);
              $rpnlInv = (float)($inv['realized_pnl']   ?? 0);
            ?>
            <?php if ($upnlInv != 0): ?>
            <div style="font-size:10px;margin-top:2px;color:var(--<?= pnlClass($upnlInv) ?>)">
              📈 <?= pnlSign($upnlInv) . fmtIDR(abs($upnlInv)) ?> <span style="color:var(--text3)">unreal.</span>
            </div>
            <?php endif; ?>
            <?php if ($rpnlInv != 0): ?>
            <div style="font-size:10px;margin-top:2px;color:var(--<?= pnlClass($rpnlInv) ?>)">
              💰 <?= pnlSign($rpnlInv) . fmtIDR(abs($rpnlInv)) ?> <span style="color:var(--gold)">realized</span>
            </div>
            <?php endif; ?>
            <?php if (!$cfg['has_pnl'] && $cat === 'property' && $monthlyIncome): ?>
            <div class="inv-pnl" style="color:var(--green)">+<?= fmtIDR($monthlyIncome) ?>/bln</div>
            <?php endif; ?>
          </div>
          <?php if ($isLoggedIn && $cfg['has_pnl']): ?>
          <div class="inv-btns">
            <a href="<?= BASE_URL ?>dashboard.php?sell=<?= $inv['id'] ?>&cat=<?= $cat ?>" class="btn btn-outline btn-xs">Jual</a>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ═══════════════════════════════════════════════
     RIWAYAT TRANSAKSI
════════════════════════════════════════════════ -->
<div class="section-title" style="margin-top:36px">🕒 Riwayat <span>Transaksi</span></div>
<div class="card" style="margin-bottom:20px">
  <?php if (empty($recentTx)): ?>
  <div class="empty">
    <div class="e-icon">🧾</div>
    <div class="e-title">Belum Ada Transaksi</div>
    <div class="e-sub">Mulai tambahkan investasi untuk melihat riwayat di sini</div>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Tipe</th>
        <th>Kategori</th>
        <th>Nama / Aset</th>
        <th>Ticker</th>
        <th class="td-right">Modal / Cost</th>
        <th class="td-right">Harga Jual</th>
        <th class="td-right">PnL Realized</th>
        <th class="td-center">Tanggal</th>
      </tr></thead>
      <tbody>
      <?php foreach ($recentTx as $tx):
        $isSell = $tx['tx_type'] === 'sell';
        $cat    = $tx['category'];
        $cfg    = CATEGORIES[$cat] ?? ['icon'=>'?','color'=>'#666','label'=>$cat];
      ?>
      <tr>
        <td>
          <?php if ($isSell): ?>
            <span class="badge badge-red">💸 JUAL</span>
          <?php else: ?>
            <span class="badge badge-green">📥 BELI</span>
          <?php endif; ?>
        </td>
        <td>
          <span style="color:<?= $cfg['color'] ?>"><?= $cfg['icon'] ?></span>
          <span style="font-size:11px;color:var(--text2)"><?= $cfg['label'] ?></span>
        </td>
        <td>
          <strong><?= htmlspecialchars($tx['name'] ?: '—') ?></strong>
          <?php if ($tx['qty']): ?>
          <div style="font-size:10px;color:var(--text3)">
            <?php if ($cat==='stocks'): ?>
              <?= fmtNum((float)$tx['qty'],0) ?> lot (<?= fmtNum((float)$tx['qty']*SHARES_PER_LOT,0) ?> lbr)
            <?php else: ?>
              <?= fmtNum((float)$tx['qty']) ?> unit/koin
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($tx['ticker']): ?>
          <span class="badge <?= $cat==='crypto'?'badge-gold':'badge-neutral' ?>"><?= htmlspecialchars($tx['ticker']) ?></span>
          <?php else: ?><span style="color:var(--text3)">—</span><?php endif; ?>
        </td>
        <td class="td-right td-mono"><?= fmtIDRFull((float)$tx['amount']) ?></td>
        <td class="td-right td-mono">
          <?= $isSell && $tx['sell_price'] ? fmtIDRFull((float)$tx['sell_price']) : '<span style="color:var(--text3)">—</span>' ?>
        </td>
        <td class="td-right td-mono">
          <?php if ($isSell && $tx['realized_pnl'] !== null): ?>
            <span style="color:var(--<?= pnlClass((float)$tx['realized_pnl']) ?>);font-weight:500">
              <?= pnlSign((float)$tx['realized_pnl']) . fmtIDRFull((float)$tx['realized_pnl']) ?>
            </span>
          <?php else: ?>
            <span style="color:var(--text3)">—</span>
          <?php endif; ?>
        </td>
        <td class="td-center" style="color:var(--text3);white-space:nowrap">
          <?= htmlspecialchars($tx['tx_date'] ?? '—') ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let cryptoPrices = <?= json_encode($cryptoPrices) ?>;
let invData = <?= json_encode(array_map(fn($d) => $d['invs'], $catData)) ?>;

async function refreshCrypto() {
  const btn = event.target;
  btn.textContent = '⟳ Loading...'; btn.disabled = true;
  try {
    const res = await fetch(BASE_URL + 'api/crypto.php?action=refresh');
    const data = await res.json();
    if (data.success) { toast('Harga crypto diperbarui ✅', 'success'); setTimeout(() => location.reload(), 800); }
    else toast('Gagal refresh: ' + (data.error||''), 'error');
  } catch(e) { toast('Network error', 'error'); }
  btn.textContent = '⟳ Refresh'; btn.disabled = false;
}

function doExportPDF() {
  const cats     = <?= json_encode(CATEGORIES) ?>;
  const catStats = <?= json_encode(array_combine(array_keys(CATEGORIES), array_map(fn($d)=>$d['stats'],$catData))) ?>;
  const targets  = <?= json_encode($targets) ?>;
  const allStats = <?= json_encode($allStats) ?>;
  const catInvsRaw = <?= json_encode(array_combine(array_keys(CATEGORIES), array_map(fn($d,$cat)=>
    array_map(fn($inv)=>[
      'name'    => ($inv['name']??'').($inv['ticker']?' ('.$inv['ticker'].')':''),
      'ticker'  => $inv['ticker'] ?? null,
      'qty'     => $inv['qty'] ?? null,
      'cost'          => getInvCost($inv),
      'value'         => getInvCurrentValue($inv, $cryptoPrices),
      'unrealized_pnl'=> (float)($inv['unrealized_pnl'] ?? 0),
      'realized_pnl'  => (float)($inv['realized_pnl']   ?? 0),
      'date'          => $inv['inv_date'] ?? '—',
      'note'          => $inv['note'] ?? null,
      'monthly'       => ($cat === 'property') ? getPropertyMonthlyIncome($inv) : null,
      'yieldAnn'      => ($cat === 'property') ? getPropertyYield($inv) : null,
    ], $d['invs'])
  , $catData, array_keys(CATEGORIES)))) ?>;

  const tableData = {};
  for (const cat of Object.keys(cats)) {
    tableData[cat] = {
      catLabel : cats[cat].label,
      hasPnl   : cats[cat].has_pnl,
      ...catStats[cat],
      items    : catInvsRaw[cat],
    };
  }
  const now = new Date();
  const extras = {
    cashBalance:    <?= $cashStats['balance'] ?>,
    cashStats:      <?= json_encode($cashStats) ?>,
    unrealizedStats:<?= json_encode(getTotalUnrealizedPnl()) ?>,
    soldStats:      <?= json_encode($soldStats) ?>,
    totalPnl:       <?= $upnl['net'] + $soldStats['total_realized_pnl'] ?>,
  };
  exportPDF(tableData, allStats, targets,
    `PortoFolio_Overview_${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}.pdf`,
    extras
  );
}

// Auto-refresh crypto every 30s
function updateCryptoDisplay() {
  // Update badge harga live
  (invData['crypto']||[]).forEach(inv => {
    const coinId = inv.coin_id || inv.ticker?.toLowerCase();
    const price  = cryptoPrices[coinId];
    const liveEl = document.getElementById('live-' + inv.id);
    if (liveEl && price) {
      liveEl.className = 'crypto-live';
      liveEl.innerHTML = `<span class="live-dot"></span>${inv.ticker} = ${fmtIDR(price)}`;
    }
  });

  // Update nilai, PnL, PnL% per baris
  document.querySelectorAll('[data-crypto-row]').forEach(row => {
    const coinId = row.dataset.coinId;
    const price  = cryptoPrices[coinId];
    if (!price) return;

    const qty    = parseFloat(row.dataset.qty)  || 0;
    const cost   = parseFloat(row.dataset.cost)  || 0;
    const upnl   = parseFloat(row.dataset.upnl)  || 0;
    const rpnl   = parseFloat(row.dataset.rpnl)  || 0;
    const id     = row.dataset.invId;

    const curVal    = price * qty;
    const priceDiff = curVal - cost;
    const pnl       = priceDiff + upnl + rpnl;
    const pnlPct    = cost > 0 ? (pnl / cost * 100) : 0;
    const pnlColor  = pnl > 0 ? 'var(--green)' : pnl < 0 ? 'var(--red)' : 'var(--text)';
    const sign      = v => v >= 0 ? '+' : '';

    const valEl = document.getElementById('idx-val-' + id);
    const pnlEl = document.getElementById('idx-pnl-' + id);

    if (valEl) valEl.textContent = fmtIDR(curVal);
    if (pnlEl) {
      pnlEl.textContent = `${sign(pnl)}${fmtIDR(pnl)} (${sign(pnlPct)}${pnlPct.toFixed(2)}%)`;
      pnlEl.style.color = pnlColor;
    }
  });
}

setInterval(async () => {
  try {
    const r = await fetch(BASE_URL + 'api/crypto.php?action=auto');
    const d = await r.json();
    if (d.prices) { cryptoPrices = d.prices; updateCryptoDisplay(); }
  } catch(e) {}
}, 65000);
</script>

<!-- ═══════════════════════════════════════════════
     CASH LEDGER TABLE (di bawah riwayat transaksi)
════════════════════════════════════════════════ -->
<?php
$cashLedger = getCashLedger(30);
if (!empty($cashLedger)):
?>
<div class="section-title" style="margin-top:36px">📒 Riwayat <span>Kas</span>
  <?php if($isLoggedIn): ?>
  <a href="<?= BASE_URL ?>dashboard.php#cash" class="btn btn-green btn-sm" style="margin-left:auto">+ Kelola Kas di Dashboard</a>
  <?php endif; ?>
</div>
<div class="card" style="margin-bottom:32px">
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Tipe</th>
        <th>Keterangan</th>
        <th class="td-right">Nominal</th>
        <th class="td-center">Tanggal</th>

      </tr></thead>
      <tbody>
      <?php foreach($cashLedger as $entry):
        $amt = (float)$entry['amount'];
        $isIn = $amt >= 0;
      ?>
      <tr>
        <td>
          <?php if($entry['type']==='from_sale'): ?>
            <span class="badge badge-gold">💸 Dari Jual</span>
          <?php elseif($entry['type']==='topup'): ?>
            <span class="badge badge-green">📥 Topup</span>
          <?php else: ?>
            <span class="badge badge-red">📤 Tarik</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($entry['note']): ?>
            <?= htmlspecialchars($entry['note']) ?>
          <?php elseif($entry['inv_name']): ?>
            <span style="color:var(--text2)"><?= htmlspecialchars($entry['inv_name']) ?>
            <?php if($entry['inv_ticker']): ?>(<?= htmlspecialchars($entry['inv_ticker']) ?>)<?php endif; ?></span>
          <?php else: ?>
            <span style="color:var(--text3)">—</span>
          <?php endif; ?>
        </td>
        <td class="td-right td-mono" style="color:var(--<?= $isIn?'green':'red' ?>);font-weight:500">
          <?= $isIn ? '+' : '' ?><?= fmtIDRFull($amt) ?>
        </td>
        <td class="td-center" style="color:var(--text3);white-space:nowrap"><?= $entry['tx_date'] ?></td>

      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
