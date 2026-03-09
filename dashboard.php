<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
requireLogin();
$isLoggedIn = true; // confirmed by requireLogin()

$cryptoPrices = getCryptoPrices();
$allStats     = getAllStats($cryptoPrices);
$cashStats    = getCashStats();
$unrealizedStats = getTotalUnrealizedPnl();
$cashLedger   = getCashLedger(40);
$maintenance  = getMaintenance();

// Pre-select category / action from URL
$preAddCat  = $_GET['add']  ?? null;
$preSellId  = $_GET['sell'] ?? null;
$preSellCat = $_GET['cat']  ?? null;

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$catData = [];
foreach (CATEGORIES as $cat => $cfg) {
    $invs = getInvestments($cat);
    $stats = getCatStats($cat, $cryptoPrices);
    $catData[$cat] = compact('invs','stats');
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="page-header">
  <div class="page-title-group">
    <div class="page-title">📋 Dash<span>board</span></div>
    <div class="page-subtitle">Kelola semua investasi Anda — tambah, edit, hapus & jual</div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" onclick="doExportPDF()">⬇ PDF</button>
    <button class="btn btn-outline btn-sm" onclick="openModal('cash-modal')">💵 Kelola Kas</button>
    <?php if($maintenance['is_active']): ?>
    <button class="btn btn-sm" style="background:var(--red);color:#fff" onclick="deactivateMaintenance()">🔴 Matikan Maintenance</button>
    <?php else: ?>
    <button class="btn btn-outline btn-sm" onclick="openModal('maintenance-modal')">🔧 Maintenance</button>
    <?php endif; ?>
    <button class="btn btn-gold" onclick="openAddModal(null)">+ Tambah Investasi</button>
  </div>
</div>

<!-- SUMMARY BAR -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <?php $dbCryptoStats = $catData['crypto']['stats']; ?>
  <div class="stat-card">
    <div class="stat-label">Total Portfolio</div>
    <div class="stat-value gold" id="db-total-porto"
         data-base-non-crypto="<?= $allStats['totalValue'] - $dbCryptoStats['totalValue'] ?>"
    ><?= fmtIDR($allStats['totalValue']) ?></div>
    <div class="stat-sub">Nilai aktif saat ini</div>
  </div>
  <div class="stat-card"
       id="db-totalpnl-card"
       data-base-realized="<?= $soldStats['total_realized_pnl'] ?? 0 ?>"
       data-base-non-crypto-pnl="<?= $allStats['pnl'] - $dbCryptoStats['pnl'] ?>"
       data-base-crypto-pnl="<?= $dbCryptoStats['pnl'] ?>"
       data-base-cost="<?= $allStats['totalCost'] ?>">
    <div class="stat-label">Total PnL</div>
    <div class="stat-value" id="db-totalpnl-val"
         style="color:var(--<?= pnlClass($allStats['pnl']) ?>)"
    ><?= pnlSign($allStats['pnl']) . fmtIDR($allStats['pnl']) ?></div>
    <div class="stat-sub" id="db-totalpnl-sub"><?= pnlSign($allStats['pnlPct']) . number_format($allStats['pnlPct'],2) ?>% keseluruhan</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Modal</div>
    <div class="stat-value"><?= fmtIDR($allStats['totalCost']) ?></div>
    <div class="stat-sub">Modal aktif diinvestasikan</div>
  </div>
  <div class="stat-card" style="border-color:rgba(34,197,94,0.3);cursor:pointer" onclick="openModal('cash-modal')">
    <div class="stat-label">💵 Saldo Kas</div>
    <div class="stat-value <?= $cashStats['balance']>=0?'green':'red' ?>"
         id="cash-balance-val" data-raw="<?= $cashStats['balance'] ?>"><?= fmtIDR($cashStats['balance']) ?></div>
    <div class="stat-sub">
      <?php if($cashStats['balance'] <= 0): ?>
        <span style="color:var(--red)">⚠️ Kas kosong — isi dulu!</span>
      <?php else: ?>Klik untuk kelola kas<?php endif; ?>
    </div>
  </div>
</div>

<!-- FILTER -->
<div class="filter-bar" id="filter-bar">
  <button class="filter-btn active" data-cat="all" onclick="filterCat('all',this)">🔍 Semua</button>
  <?php foreach (CATEGORIES as $cat => $cfg): ?>
  <button class="filter-btn" data-cat="<?= $cat ?>" onclick="filterCat('<?= $cat ?>',this)">
    <?= $cfg['icon'] ?> <?= $cfg['label'] ?>
    <span style="color:var(--text3);margin-left:4px">(<?= count($catData[$cat]['invs']) ?>)</span>
  </button>
  <?php endforeach; ?>
</div>

<!-- PER CATEGORY SECTIONS -->
<?php foreach (CATEGORIES as $cat => $cfg):
  $d     = $catData[$cat];
  $stats = $d['stats'];
  $invs  = $d['invs'];
?>
<div class="card cat-section" id="section-<?= $cat ?>" style="margin-bottom:18px">
  <div class="card-header">
    <div class="card-title">
      <span style="font-size:20px"><?= $cfg['icon'] ?></span>
      <?= $cfg['label'] ?>
      <span class="badge badge-neutral"><?= $stats['count'] ?></span>
      <?php if ($cfg['has_pnl']): ?>
      <span class="badge badge-<?= pnlClass($stats['pnl']) ?>"
            style="color:var(--<?= pnlClass($stats['pnl']) ?>)"
            id="dash-catbadge-<?= $cat ?>">
        PnL: <?= pnlSign($stats['pnl']) . fmtIDR($stats['pnl']) ?>
        (<?= pnlSign($stats['pnlPct']) . number_format($stats['pnlPct'],2) ?>%)
      </span>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span style="font-size:12px;color:var(--gold)" id="dash-cattotal-<?= $cat ?>">Total: <?= fmtIDR($stats['totalValue']) ?></span>
      <button class="btn btn-gold btn-sm" onclick="openAddModal('<?= $cat ?>')">+ Tambah</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nama / Keterangan</th>
          <th>Ticker</th>
          <th>Qty</th>
          <th class="td-right">Harga Beli</th>
          <th class="td-right">Harga Saat Ini</th>
          <th class="td-right">Modal</th>
          <th class="td-right">Nilai Saat Ini</th>
          <?php if ($cfg['has_pnl']): ?>
            <th class="td-right">PnL</th><th class="td-right">PnL%</th>
            <th class="td-right"><?= $cat === 'property' ? 'Yield / Bulan' : 'Unrealized' ?></th>
          <?php else: ?><th colspan="2">—</th><?php endif; ?>
          <th class="td-center">Tanggal</th>
          <th class="td-center">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($invs)): ?>
        <tr><td colspan="<?= $cfg['has_pnl'] ? 12 : 10 ?>">
          <div class="empty">
            <div class="e-icon"><?= $cfg['icon'] ?></div>
            <div class="e-title">Belum Ada Investasi</div>
            <div class="e-sub">Tambahkan investasi pertama Anda di kategori ini</div>
            <button class="btn btn-outline btn-sm" onclick="openAddModal('<?= $cat ?>')">+ Tambah Sekarang</button>
          </div>
        </td></tr>
        <?php else: ?>
        <?php foreach ($invs as $i => $inv):
          $cost    = getInvCost($inv);
          $curVal  = getInvCurrentValue($inv, $cryptoPrices);
          $upnl    = (float)($inv['unrealized_pnl'] ?? 0);
          $rpnl    = (float)($inv['realized_pnl']   ?? 0);
          // PnL = (harga sekarang - modal) + unrealized manual + realized (dividen/bunga/staking/bagi hasil)
          // Untuk property: curVal = buyPrice (modal), jadi price diff = 0, PnL = upnl + rpnl
          // Untuk crypto: curVal = live price * qty (sudah include price diff), upnl = 0
          // Untuk saham/RD: curVal = price*qty atau fallback ke modal, upnl/rpnl = manual input
          $priceDiff = $curVal - $cost;
          $pnl       = $priceDiff + $upnl + $rpnl;
          $pnlPct    = $cost > 0 ? ($pnl / $cost * 100) : 0;
          $livePrice = null;
          if ($cat === 'crypto' && !empty($inv['ticker'])) {
            $coinId = $inv['coin_id'] ?? getCoinId($inv['ticker']);
            $livePrice = $cryptoPrices[$coinId] ?? null;
          }
          $displayPrice = $livePrice ?? ($inv['current_price'] ?? null);
        ?>
        <tr<?php if ($cat === 'crypto'): ?>
          data-crypto-row="1"
          data-coin-id="<?= htmlspecialchars($coinId ?? '') ?>"
          data-qty="<?= (float)($inv['qty'] ?? 0) ?>"
          data-cost="<?= $cost ?>"
          data-upnl="<?= $upnl ?>"
          data-rpnl="<?= $rpnl ?>"
          data-inv-id="<?= $inv['id'] ?>"
        <?php endif; ?>>
          <td style="color:var(--text3)"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($inv['name'] ?: '—') ?></div>
            <?php if ($inv['note']): ?><div style="font-size:10px;color:var(--text3)"><?= htmlspecialchars($inv['note']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($inv['ticker']): ?>
            <span class="badge <?= $cat==='crypto'?'badge-gold':'badge-neutral' ?>"><?= htmlspecialchars($inv['ticker']) ?></span>
            <?php else: ?><span style="color:var(--text3)">—</span><?php endif; ?>
          </td>
          <td class="td-mono">
            <?php if ($cat === 'stocks' && $inv['qty']): ?>
              <?= fmtNum((float)$inv['qty'], 0) ?> lot<br>
              <span style="font-size:10px;color:var(--text3)"><?= fmtNum((float)$inv['qty']*SHARES_PER_LOT,0) ?> lbr</span>
            <?php elseif ($inv['qty']): ?>
              <?= fmtNum((float)$inv['qty']) ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="td-right td-mono"><?= $inv['buy_price'] ? fmtIDR((float)$inv['buy_price']) : '—' ?></td>
          <td class="td-right td-mono"<?php if($cat==='crypto'): ?> id="crypto-price-<?= $inv['id'] ?>"<?php endif; ?>>
            <?php if ($displayPrice): ?>
              <?= fmtIDR((float)$displayPrice) ?>
              <?php if ($cat==='crypto' && $livePrice): ?><br><span class="badge badge-cyan" style="font-size:9px">LIVE</span><?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td class="td-right td-mono"><?= fmtIDR($cost) ?></td>
          <td class="td-right td-mono"<?php if($cat==='crypto'): ?> id="crypto-val-<?= $inv['id'] ?>"<?php endif; ?>><strong><?= fmtIDR($curVal) ?></strong></td>
          <?php if ($cfg['has_pnl']): ?>
          <td class="td-right td-mono" <?php if($cat==='crypto'): ?>id="crypto-pnl-<?= $inv['id'] ?>"<?php endif; ?> style="color:var(--<?= pnlClass($pnl) ?>)"><?= pnlSign($pnl) . fmtIDR($pnl) ?></td>
          <td class="td-right td-mono" <?php if($cat==='crypto'): ?>id="crypto-pct-<?= $inv['id'] ?>"<?php endif; ?> style="color:var(--<?= pnlClass($pnl) ?>)"><?= pnlSign($pnlPct) . number_format($pnlPct,2) ?>%</td>
          <td class="td-right td-mono" style="font-size:11px">
            <?php if ($cat === 'property'): ?>
              <?php $mi = getPropertyMonthlyIncome($inv); $yi = getPropertyYield($inv); ?>
              <?php if ($mi > 0): ?>
                <div style="color:var(--green);font-weight:600"><?= fmtIDR($mi) ?>/bln</div>
                <div style="color:var(--gold);font-size:10px"><?= number_format($yi,2) ?>%/thn</div>
              <?php else: ?><span style="color:var(--text3)">—</span><?php endif; ?>
            <?php else: ?>
              <?php if ($upnl != 0): ?>
                <div style="color:var(--<?= pnlClass($upnl) ?>)"><?= pnlSign($upnl) . fmtIDR(abs($upnl)) ?></div>
                <div style="font-size:9px;color:var(--text3)">Unreal.</div>
              <?php endif; ?>
              <?php if ($rpnl != 0): ?>
                <div style="color:var(--<?= pnlClass($rpnl) ?>)"><?= pnlSign($rpnl) . fmtIDR(abs($rpnl)) ?></div>
                <div style="font-size:9px;color:var(--gold)">Realized</div>
              <?php endif; ?>
              <?php if ($upnl == 0 && $rpnl == 0): ?>
                <span style="color:var(--text3)">—</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <?php elseif ($cat === 'property'): ?>
          <?php $mi = getPropertyMonthlyIncome($inv); $yi = getPropertyYield($inv); ?>
          <td class="td-right td-mono" style="color:var(--green)"><?= $mi > 0 ? fmtIDR($mi).'/bln' : '—' ?></td>
          <td class="td-right td-mono" style="color:var(--gold)"><?= $yi > 0 ? number_format($yi,2).'%/thn' : '—' ?></td>
          <?php else: ?><td colspan="2" style="color:var(--text3)">—</td><?php endif; ?>
          <td class="td-center" style="white-space:nowrap;color:var(--text3)"><?= $inv['inv_date'] ?: '—' ?></td>
          <td class="td-center">
            <div style="display:flex;gap:5px;justify-content:center">
              <button class="btn btn-outline btn-xs" onclick="openEditModal(<?= htmlspecialchars(json_encode($inv)) ?>)">✏</button>
              <?php if ($cfg['has_pnl']): ?>
              <button class="btn btn-green btn-xs" onclick="openSellModal(<?= htmlspecialchars(json_encode([
                'id'    => $inv['id'],
                'cat'   => $cat,
                'name'  => $inv['name'] ?? $inv['ticker'] ?? '—',
                'cost'  => $cost,
                'curVal'=> $curVal,
                'qty'   => $inv['qty'],
                'upnl'  => $upnl,
                'rpnl'  => $rpnl,
              ])) ?>)">💸 Jual</button>
              <?php endif; ?>

              <?php if ($cfg['has_pnl'] && $cat !== 'crypto'): ?>
              <?php
                $upnl2 = (float)($inv['unrealized_pnl'] ?? 0);
                $rpnl2 = (float)($inv['realized_pnl']   ?? 0);
              ?>
              <button class="btn btn-outline btn-xs"
                style="color:var(--gold)"
                title="Catat PnL"
                onclick="openPnlModal(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['name'] ?: ($inv['ticker'] ?: 'Investasi'))) ?>', <?= $upnl2 ?>, <?= $rpnl2 ?>, '<?= $cat ?>')">±</button>
              <?php endif; ?>
              <?php if ($cat === 'crypto'): ?>
              <?php
                $rpnl2   = (float)($inv['realized_pnl'] ?? 0);
                $coinId2 = $inv['coin_id'] ?? getCoinId($inv['ticker'] ?? '');
                $curQty2 = (float)($inv['qty'] ?? 0);
              ?>
              <button class="btn btn-outline btn-xs"
                style="color:var(--gold)"
                title="Catat Staking/Reward"
                onclick="openPnlModal(<?= $inv['id'] ?>, '<?= htmlspecialchars(addslashes($inv['name'] ?: ($inv['ticker'] ?: 'Investasi'))) ?>', 0, <?= $rpnl2 ?>, 'crypto', '<?= $coinId2 ?>', <?= $curQty2 ?>)">💰</button>
              <?php endif; ?>
              <button class="btn btn-danger btn-xs" onclick="deleteInv(<?= $inv['id'] ?>, '<?= $cat ?>')">✕</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>



<!-- ═══════════════════════════════════════════════
     PnL SUMMARY — Unrealized & Realized
════════════════════════════════════════════════ -->
<div class="section-title" id="pnl-summary" style="margin-top:36px">📊 Ringkasan <span>PnL</span></div>
<div class="stats-grid" style="margin-bottom:28px">
  <div class="stat-card" style="border-color:rgba(34,197,94,0.3)"
       id="db-upnl-profit-card"
       data-base-non-crypto="<?= max(0, $unrealizedStats['profit'] - max(0, $dbCryptoStats['pnl'])) ?>"
       data-base-crypto-pnl="<?= $dbCryptoStats['pnl'] ?>">
    <div class="stat-label">📈 Unrealized Profit</div>
    <div class="stat-value green" id="db-upnl-profit"><?= fmtIDR($unrealizedStats['profit']) ?></div>
    <div class="stat-sub">Keuntungan belum terealisasi</div>
  </div>
  <div class="stat-card" style="border-color:rgba(239,68,68,0.3)"
       id="db-upnl-loss-card"
       data-base-non-crypto="<?= $unrealizedStats['loss'] - max(0, -$dbCryptoStats['pnl']) ?>">
    <div class="stat-label">📉 Unrealized Loss</div>
    <div class="stat-value red" id="db-upnl-loss"><?= fmtIDR($unrealizedStats['loss']) ?></div>
    <div class="stat-sub">Kerugian belum terealisasi</div>
  </div>
  <div class="stat-card" id="db-net-upnl-card"
       data-base-non-crypto-pnl="<?= $unrealizedStats['net'] - $dbCryptoStats['pnl'] ?>">
    <div class="stat-label">💹 Net Unrealized PnL</div>
    <div class="stat-value" id="db-net-upnl-val"
         style="color:var(--<?= pnlClass($unrealizedStats['net']) ?>)"
    ><?= pnlSign($unrealizedStats['net']) . fmtIDR($unrealizedStats['net']) ?></div>
    <div class="stat-sub">Posisi aktif (belum dijual)</div>
  </div>
  <div class="stat-card" style="border-color:rgba(240,180,41,0.3)">
    <div class="stat-label">💸 Realized PnL</div>
    <?php $sold = getSoldStats(); ?>
    <div class="stat-value gold"><?= pnlSign($sold['total_realized_pnl']) . fmtIDR($sold['total_realized_pnl']) ?></div>
    <div class="stat-sub">
      <?php if ($sold['realized_from_sell'] != 0): ?>
        💰 Jual: <?= pnlSign($sold['realized_from_sell']) . fmtIDR($sold['realized_from_sell']) ?><br>
      <?php endif; ?>
      <?php if ($sold['realized_from_savings'] != 0): ?>
        🏦 Bunga: <?= pnlSign($sold['realized_from_savings']) . fmtIDR(abs($sold['realized_from_savings'])) ?>
      <?php endif; ?>
      <?php if ($sold['total_sold'] == 0 && $sold['realized_from_savings'] == 0): ?>
        Belum ada
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════
     TARGET MANAGEMENT SECTION
════════════════════════════════════════════════ -->
<div class="section-title" id="targets" style="margin-top:36px">🎯 Manajemen <span>Target</span></div>

<!-- Total target display -->
<?php
$targets     = getTargets();
$totalTarget = calcTotalTarget($targets);
$totalProg   = $totalTarget > 0 ? min($allStats['totalValue']/$totalTarget*100,100) : 0;
?>
<div class="card" style="margin-bottom:16px;padding:20px 24px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px">
    <div>
      <div style="font-family:var(--font-head);font-weight:700;font-size:15px">🏆 Target Total Portofolio</div>
      <div style="font-size:11px;color:var(--text3);margin-top:3px">Dihitung otomatis dari sum semua target kategori</div>
    </div>
    <div style="display:flex;align-items:center;gap:16px">
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3)">Nilai Saat Ini</div>
        <div style="font-size:20px;font-weight:500;color:var(--gold)"
             id="db-tgt-porto-val"
             data-base-non-crypto="<?= $allStats['totalValue'] - $dbCryptoStats['totalValue'] ?>"
        ><?= fmtIDR($allStats['totalValue']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3)">Target Total</div>
        <div style="font-size:20px;font-weight:500;color:var(--text)"><?= $totalTarget > 0 ? fmtIDR($totalTarget) : 'Belum diset' ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:11px;color:var(--text3)">Tercapai</div>
        <div style="font-size:24px;font-weight:700;color:var(--gold)"
             id="db-tgt-pct"
             data-total-target="<?= $totalTarget ?>"
             data-base-non-crypto="<?= $allStats['totalValue'] - $dbCryptoStats['totalValue'] ?>"
        ><?= number_format($totalProg,1) ?>%</div>
      </div>
    </div>
  </div>
  <div class="progress-bg" style="height:8px">
    <div class="progress-fill" id="db-tgt-bar"
         data-total-target="<?= $totalTarget ?>"
         data-base-non-crypto="<?= $allStats['totalValue'] - $dbCryptoStats['totalValue'] ?>"
         style="width:<?= $totalProg ?>%;background:linear-gradient(90deg,#e5a000,#f0b429)"></div>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-top:6px">
    <span id="db-tgt-sisa"><?= $totalTarget>0 ? 'Sisa: '.fmtIDR(max(0,$totalTarget-$allStats['totalValue'])) : '' ?></span>
    <span id="db-tgt-status"><?= $totalProg>=100 ? '✅ Target tercapai!' : ($totalTarget>0 ? 'Terus semangat!' : '') ?></span>
  </div>
</div>

<!-- Per-category target inputs -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-bottom:20px">
<?php foreach(CATEGORIES as $cat => $cfg):
  $s   = $catData[$cat]['stats'];
  $tgt = $targets[$cat] ?? 0;
  $pct = $tgt > 0 ? min($s['totalValue']/$tgt*100,100) : 0;
?>
<div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
    <span style="font-size:20px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:<?= $cfg['color'] ?>20;border-radius:8px"><?= $cfg['icon'] ?></span>
    <div style="flex:1">
      <div style="font-family:var(--font-head);font-weight:700;font-size:13px"><?= $cfg['label'] ?></div>
      <div style="font-size:10px;color:var(--text3)"
           <?php if($cat==='crypto'): ?>id="db-cat-sub-crypto"<?php endif; ?>
      ><?= $s['count'] ?> investasi · <?= fmtIDR($s['totalValue']) ?></div>
    </div>
    <?php if($cfg['has_pnl'] && $s['count']>0): ?>
    <span class="badge badge-<?= pnlClass($s['pnl']) ?>" style="font-size:10px"
          <?php if($cat==='crypto'): ?>id="db-cat-badge-crypto"<?php endif; ?>>
      <?= pnlSign($s['pnl']) . fmtIDR($s['pnl']) ?>
    </span>
    <?php endif; ?>
  </div>
  <div class="progress-bg" style="height:4px;margin-bottom:10px">
    <div class="progress-fill"
         <?php if($cat==='crypto'): ?>
           id="db-cat-bar-crypto"
           data-tgt="<?= $tgt ?>"
         <?php endif; ?>
         style="width:<?= $pct ?>%;background:<?= $cfg['color'] ?>"></div>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <div style="flex:1">
      <label style="font-size:10px;color:var(--text3);display:block;margin-bottom:4px">Target (Rp)</label>
      <input type="number" class="form-control" id="target-<?= $cat ?>"
        value="<?= $tgt ?: '' ?>" placeholder="Set target..."
        step="any" style="padding:7px 10px;font-size:12px"
        oninput="updateTargetPreview('<?= $cat ?>')">
    </div>
    <div style="text-align:right;min-width:55px">
      <div style="font-size:18px;font-weight:600;color:<?= $cfg['color'] ?>"
           <?php if($cat==='crypto'): ?>id="db-cat-pct-crypto"<?php endif; ?>
      ><?= number_format($pct,1) ?>%</div>
      <div style="font-size:9px;color:var(--text3)"><?= $tgt>0?fmtIDR($tgt):'Belum diset' ?></div>
    </div>
    <button class="btn btn-outline btn-xs" onclick="saveTarget('<?= $cat ?>')" title="Simpan target kategori ini">✓</button>
  </div>
  <div style="font-size:10px;color:var(--text3);margin-top:6px" id="tgt-preview-<?= $cat ?>">
    <?php if($tgt>0): ?>
      <?php $rem = max(0,$tgt-$s['totalValue']); ?>
      <?= $pct>=100 ? '✅ Target tercapai!' : 'Sisa: '.fmtIDR($rem) ?>
    <?php else: ?>
      Belum ada target
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<div style="display:flex;gap:10px;margin-bottom:32px">
  <button class="btn btn-gold" onclick="saveAllTargets()">💾 Simpan Semua Target</button>
  <div style="font-size:11px;color:var(--text3);align-self:center">Total target akan diperbarui otomatis</div>
</div>

<!-- ═══════════════════════════════════════════════
     KAS SECTION
════════════════════════════════════════════════ -->
<div class="section-title" id="cash" style="margin-top:36px">💵 Manajemen <span>Kas</span></div>

<!-- Kas stats row -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="stat-card" style="border-color:rgba(34,197,94,0.3)">
    <div class="stat-label">💵 Saldo Kas</div>
    <div class="stat-value <?= $cashStats['balance']>=0?'green':'red' ?>"><?= fmtIDR($cashStats['balance']) ?></div>
    <div class="stat-sub">Saldo tersedia</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">📥 Total Masuk Manual</div>
    <div class="stat-value green"><?= fmtIDR($cashStats['topup']) ?></div>
    <div class="stat-sub"><?= $cashStats['topup_cnt'] ?> penambahan</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">💸 Masuk dari Jual</div>
    <div class="stat-value gold"><?= fmtIDR($cashStats['from_sale']) ?></div>
    <div class="stat-sub"><?= $cashStats['from_sale_cnt'] ?> penjualan</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">📤 Total Keluar</div>
    <div class="stat-value red"><?= fmtIDR(abs($cashStats['invest_out']) + abs($cashStats['withdrawal'])) ?></div>
    <div class="stat-sub"><?= $cashStats['invest_out_cnt'] + $cashStats['withdrawal_cnt'] ?> transaksi</div>
  </div>
</div>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <button class="btn btn-green" onclick="openModal('cash-modal')">📥 Tambah Kas</button>
  <button class="btn btn-outline" onclick="openWithdrawModal()">📤 Tarik Kas</button>
</div>

<!-- Cash ledger table -->
<div class="card" style="margin-bottom:32px">
  <?php if(empty($cashLedger)): ?>
  <div class="empty">
    <div class="e-icon">💵</div>
    <div class="e-title">Belum Ada Riwayat Kas</div>
    <div class="e-sub">Tambahkan kas atau lakukan transaksi investasi</div>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Tipe</th>
        <th>Keterangan</th>
        <th class="td-right">Nominal</th>
        <th class="td-right">Saldo Setelah</th>
        <th class="td-center">Tanggal</th>
        <th class="td-center">Aksi</th>
      </tr></thead>
      <tbody>
      <?php
      $runningBal = 0;
      $entries = array_reverse($cashLedger); // oldest first for running balance
      $balMap = [];
      foreach($entries as $e) { $runningBal += (float)$e['amount']; $balMap[$e['id']] = $runningBal; }
      foreach(array_reverse($entries) as $entry):
        $amt   = (float)$entry['amount'];
        $isIn  = $amt >= 0;
        $isLocked = in_array($entry['type'], ['topup','from_sale','invest_out']);
      ?>
      <tr>
        <td>
          <?php if($entry['type']==='from_sale'): ?>
            <span class="badge badge-gold">💸 Dari Jual</span>
          <?php elseif($entry['type']==='topup'): ?>
            <span class="badge badge-green">📥 Topup</span>
          <?php elseif($entry['type']==='invest_out'): ?>
            <span class="badge badge-neutral">📊 Investasi</span>
          <?php else: ?>
            <span class="badge badge-red">📤 Tarik</span>
          <?php endif; ?>
        </td>
        <td>
          <div style="font-size:12px"><?= htmlspecialchars($entry['note'] ?: '—') ?></div>
          <?php if($entry['inv_name']): ?>
          <div style="font-size:10px;color:var(--text3)"><?= htmlspecialchars($entry['inv_name']) ?><?= $entry['inv_ticker']?' ('.$entry['inv_ticker'].')':'' ?></div>
          <?php endif; ?>
        </td>
        <td class="td-right td-mono" style="color:var(--<?= $isIn?'green':'red' ?>);font-weight:500">
          <?= $isIn?'+':'' ?><?= fmtIDRFull($amt) ?>
        </td>
        <td class="td-right td-mono" style="color:var(--<?= ($balMap[$entry['id']]??0)>=0?'text':'red' ?>)">
          <?= fmtIDR($balMap[$entry['id']] ?? 0) ?>
        </td>
        <td class="td-center" style="color:var(--text3);white-space:nowrap"><?= $entry['tx_date'] ?></td>
        <td class="td-center">
          <span title="Riwayat kas tidak dapat dihapus" style="font-size:10px;color:var(--text3)">🔒</span>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- CASH MODAL - Tambah Kas -->
<div class="modal-overlay" id="cash-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-title">📥 Tambah Kas</div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Nominal (Rp)</label>
      <input type="text" class="form-control" id="cash-amount" placeholder="Contoh: 1.000.000 atau 1000000,50" inputmode="decimal">
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Keterangan (opsional)</label>
      <input type="text" class="form-control" id="cash-note" placeholder="Misal: Gaji, Transfer masuk...">
    </div>
    <div class="form-group" style="margin-bottom:20px">
      <label class="form-label">Tanggal</label>
      <input type="date" class="form-control" id="cash-date">
    </div>
    <div style="background:var(--surface2);border:1px solid rgba(34,197,94,0.3);border-radius:9px;padding:12px 15px;font-size:12px;margin-bottom:18px">
      <span style="color:var(--text3)">Saldo saat ini: </span>
      <strong style="color:var(--<?= $cashStats['balance']>=0?'green':'red' ?>)"><?= fmtIDR($cashStats['balance']) ?></strong>
    </div>
    <div class="modal-footer">
      <button class="btn btn-green" style="flex:1" onclick="saveCashTopup()">📥 Tambah Kas</button>
      <button class="btn btn-outline" onclick="closeModal('cash-modal')">Batal</button>
    </div>
  </div>
</div>

<!-- WITHDRAW MODAL - Tarik Kas -->
<div class="modal-overlay" id="withdraw-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-title">📤 Tarik / Gunakan Kas</div>
    <div style="background:var(--surface2);border:1px solid rgba(239,68,68,0.3);border-radius:9px;padding:12px 15px;font-size:12px;margin-bottom:16px">
      ⚠️ Penarikan kas akan <strong style="color:var(--red)">mengurangi saldo</strong>.
      Saldo saat ini: <strong style="color:var(--<?= $cashStats['balance']>=0?'green':'red' ?>)"><?= fmtIDR($cashStats['balance']) ?></strong>
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Nominal yang Ditarik (Rp)</label>
      <input type="text" class="form-control" id="withdraw-amount" placeholder="Contoh: 500.000 atau 500000,50" inputmode="decimal">
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Keterangan (opsional)</label>
      <input type="text" class="form-control" id="withdraw-note" placeholder="Misal: Kebutuhan pribadi...">
    </div>
    <div class="form-group" style="margin-bottom:20px">
      <label class="form-label">Tanggal</label>
      <input type="date" class="form-control" id="withdraw-date">
    </div>
    <div class="modal-footer">
      <button class="btn btn-danger" style="flex:1" onclick="saveCashWithdraw()">📤 Tarik Kas</button>
      <button class="btn btn-outline" onclick="closeModal('withdraw-modal')">Batal</button>
    </div>
  </div>
</div>
<!-- MAINTENANCE STATUS BANNER -->
<?php if($maintenance['is_active']): ?>
<div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.4);border-radius:12px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div style="display:flex;align-items:center;gap:12px">
    <span style="font-size:22px">🔴</span>
    <div>
      <div style="font-weight:700;font-size:13px;color:var(--red)">Maintenance Mode AKTIF</div>
      <div style="font-size:11px;color:var(--text2)">
        Overview digantikan halaman maintenance untuk pengunjung.
        <?php if(!empty($maintenance['end_time'])): ?>
        Selesai: <strong><?= date('d M Y H:i', strtotime($maintenance['end_time'])) ?></strong>
        <?php else: ?>Tanpa batas waktu.<?php endif; ?>
      </div>
    </div>
  </div>
  <button class="btn btn-sm" style="background:var(--red);color:#fff" onclick="deactivateMaintenance()">
    ✓ Selesai — Matikan Maintenance
  </button>
</div>
<?php endif; ?>

<!-- MAINTENANCE MODAL -->
<div class="modal-overlay" id="maintenance-modal">
  <div class="modal" style="max-width:560px">
    <div class="modal-title">🔧 Aktifkan Maintenance Mode</div>
    <div style="background:rgba(239,68,68,0.07);border:1px solid rgba(239,68,68,0.25);border-radius:9px;padding:11px 15px;font-size:11px;color:var(--text2);margin-bottom:18px;line-height:1.7">
      Saat maintenance aktif, halaman <strong>overview publik</strong> digantikan tampilan maintenance. Admin tetap bisa lihat normal.
    </div>
    <div style="display:flex;gap:10px;margin-bottom:18px">
      <button id="tab-default" class="btn btn-gold btn-sm" style="flex:1" onclick="setMaintTab('default')">🎨 Tampilan Default</button>
      <button id="tab-custom"  class="btn btn-outline btn-sm" style="flex:1" onclick="setMaintTab('custom')">💻 Custom HTML</button>
    </div>
    <!-- DEFAULT -->
    <div id="maint-default-form">
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Judul</label>
        <input type="text" class="form-control" id="maint-title" value="Sedang dalam Pemeliharaan">
      </div>
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Pesan (opsional)</label>
        <textarea class="form-control" id="maint-message" rows="3" placeholder="Keterangan untuk pengunjung..." style="resize:vertical"></textarea>
      </div>
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Waktu Selesai (opsional)</label>
        <input type="datetime-local" class="form-control" id="maint-end-time">
        <div class="form-hint">Kosongkan jika tanpa batas waktu. Countdown otomatis muncul.</div>
      </div>
      <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:6px">
        <div style="background:var(--surface2);padding:7px 12px;font-size:10px;color:var(--text3)">👁 Preview</div>
        <div style="background:#0a0c0f;padding:18px;text-align:center">
          <div style="background:rgba(240,180,41,0.06);border:1px solid rgba(240,180,41,0.2);border-radius:10px;padding:16px;max-width:300px;margin:0 auto">
            <div style="font-size:26px;margin-bottom:6px">🔧</div>
            <div id="prev-title" style="font-weight:800;font-size:14px;color:#f0b429;margin-bottom:5px">Sedang dalam Pemeliharaan</div>
            <div id="prev-msg"   style="font-size:10px;color:#9aa0b0;margin-bottom:6px">Kami sedang melakukan peningkatan sistem.</div>
            <div id="prev-time"  style="font-size:9px;color:#5a6070;font-family:monospace"></div>
          </div>
        </div>
      </div>
    </div>
    <!-- CUSTOM HTML -->
    <div id="maint-custom-form" style="display:none">
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:9px 13px;font-size:11px;color:var(--text2);margin-bottom:12px">
        💡 Masukkan HTML penuh. Gunakan tag <code style="color:var(--gold)">&lt;!DOCTYPE html&gt;</code>.
      </div>
      <div class="form-group">
        <label class="form-label">Custom HTML</label>
        <textarea class="form-control" id="maint-custom-html" rows="12"
          placeholder="<!DOCTYPE html>..." style="resize:vertical;font-family:var(--mono);font-size:11px"></textarea>
      </div>
    </div>
    <div class="modal-footer" style="margin-top:18px">
      <button class="btn btn-danger" style="flex:1" onclick="activateMaintenance()">🔧 Aktifkan Maintenance</button>
      <button class="btn btn-outline" onclick="closeModal('maintenance-modal')">Batal</button>
    </div>
  </div>
</div>

<!-- ================== ADD / EDIT MODAL ================== -->
<!-- ═══ MODAL PnL ═══════════════════════════════ -->
<div class="modal-overlay" id="pnl-modal">
  <div class="modal">
    <div class="modal-title">📊 PnL — <span id="pnl-inv-name-title" style="color:var(--gold)"></span></div>
    <input type="hidden" id="pnl-inv-id">
    <input type="hidden" id="pnl-inv-cat">

    <!-- Nilai saat ini -->
    <div id="pnl-current-box" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;
                padding:12px 16px;margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px">
      <div id="pnl-unrealized-box">
        <div style="font-size:10px;color:var(--text3);margin-bottom:3px">📈 Unrealized (Harga)</div>
        <div style="font-size:17px;font-weight:700" id="pnl-current-unrealized">Rp 0</div>
        <div style="font-size:10px;color:var(--text3)">Kenaikan nilai, belum dijual</div>
      </div>
      <div>
        <div style="font-size:10px;color:var(--gold);margin-bottom:3px">💰 Realized (Terima)</div>
        <div style="font-size:17px;font-weight:700" id="pnl-current-realized">Rp 0</div>
        <div style="font-size:10px;color:var(--text3)" id="pnl-realized-label">Dividen / bunga / dll</div>
      </div>
    </div>

    <!-- Pilih jenis PnL (disembunyikan jika hanya realized) -->
    <div id="pnl-kind-wrap" style="margin-bottom:14px">
      <label class="form-label">Jenis</label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <button class="btn" id="pnl-kind-unrealized"
          style="border:2px solid var(--border);color:var(--text3);padding:9px;font-size:11px;text-align:left"
          onclick="setPnlKind('unrealized')">
          📈 <strong>Unrealized</strong><br>
          <span style="font-size:10px">Kenaikan/turun harga</span>
        </button>
        <button class="btn" id="pnl-kind-realized"
          style="border:2px solid var(--border);color:var(--text3);padding:9px;font-size:11px;text-align:left"
          onclick="setPnlKind('realized')">
          💰 <strong>Realized</strong><br>
          <span style="font-size:10px" id="pnl-kind-realized-hint">Dividen / bunga</span>
        </button>
      </div>
    </div>

    <!-- Untung / Rugi -->
    <div id="pnl-type-wrap" style="margin-bottom:12px">
      <label class="form-label">Perubahan</label>
      <div id="pnl-type-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <button class="btn" id="pnl-type-profit"
          style="border:2px solid var(--green);color:var(--green);padding:9px;font-weight:600"
          onclick="setPnlType('profit')">✅ Untung</button>
        <button class="btn" id="pnl-type-loss"
          style="border:2px solid var(--border);color:var(--text3);padding:9px"
          onclick="setPnlType('loss')">❌ Rugi</button>
      </div>
    </div>

    <div id="pnl-qty-hint" style="display:none;font-size:11px;color:var(--gold);
         background:var(--surface2);border:1px solid var(--border);border-radius:6px;
         padding:7px 12px;margin-bottom:10px"></div>

    <div style="margin-bottom:8px">
      <label class="form-label">Nominal (Rp)</label>
      <input type="text" class="form-control" id="pnl-delta-amount"
             placeholder="Contoh: 50.000" inputmode="decimal" oninput="updatePnlPreview()">
    </div>

    <!-- Preview -->
    <div id="pnl-preview-box" style="background:var(--surface2);border:1px solid var(--border);
         border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;display:none">
      <span style="color:var(--text3)">Setelah disimpan: </span>
      <span style="font-weight:700" id="pnl-preview-val"></span>
    </div>

    <div class="modal-footer">
      <button class="btn btn-gold" style="flex:1" onclick="savePnlEntry()">💾 Simpan</button>
      <button class="btn btn-outline" onclick="closeModal('pnl-modal')">Batal</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="add-modal">
  <div class="modal">
    <div class="modal-title" id="modal-title">➕ Tambah Investasi</div>

    <input type="hidden" id="inv-id" value="">

    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Kategori</label>
      <select class="form-control" id="inv-cat" onchange="onCatChange()">
        <?php foreach (CATEGORIES as $cat => $cfg): ?>
        <option value="<?= $cat ?>"><?= $cfg['icon'] ?> <?= $cfg['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Nama / Keterangan</label>
      <input type="text" class="form-control" id="inv-name" placeholder="Misal: BCA Tabungan Berjangka">
    </div>

    <!-- CRYPTO -->
    <div id="grp-crypto" style="display:none">
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Ticker Crypto</label>
          <input type="text" class="form-control" id="inv-ticker" placeholder="DOGE, BTC, ETH..."
            oninput="this.value=this.value.toUpperCase()">
          <div class="form-hint">Otomatis fetch harga real-time</div>
        </div>
        <div class="form-group">
          <label class="form-label">Jumlah Koin</label>
          <input type="number" class="form-control" id="inv-qty" placeholder="0.00000000" step="any">
          <div class="form-hint">Dukung 8 desimal</div>
        </div>
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Harga Beli / Koin (Rp)</label>
          <input type="number" class="form-control" id="inv-buy-price" placeholder="0.00000000" step="any">
        </div>
        <div class="form-group">
          <label class="form-label">Modal Manual (Rp)</label>
          <input type="number" class="form-control" id="inv-amount-crypto" placeholder="Opsional" step="any">
        </div>
      </div>
    </div>

    <!-- STOCKS -->
    <div id="grp-stock" style="display:none">
      <div class="alert alert-info" style="margin-bottom:12px">
        📈 Indonesia: 1 lot = 100 lembar saham. Harga beli & jual per <strong>lembar</strong>.
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Kode Saham (Ticker)</label>
          <input type="text" class="form-control" id="inv-stock-ticker" placeholder="BBCA, TLKM, GOTO..."
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group">
          <label class="form-label">Harga Beli per Lembar (Rp)</label>
          <input type="number" class="form-control" id="inv-stock-buy" placeholder="0" step="any"
            oninput="calcStockTotal()">
        </div>
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Jumlah Lot (1 lot = 100 lembar)</label>
          <input type="number" class="form-control" id="inv-stock-qty" placeholder="0" step="1" min="1"
            oninput="calcStockTotal()">
          <div class="form-hint" id="lembar-info" style="color:var(--cyan)"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Harga Saat Ini per Lembar (Rp)</label>
          <input type="number" class="form-control" id="inv-stock-cur" placeholder="0" step="any">
        </div>
      </div>
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;margin-bottom:14px" id="stock-calc-preview">
        <span style="color:var(--text3)">Total modal = lot × 100 × harga beli per lembar</span>
      </div>
    </div>

    <!-- REKSA DANA -->
    <div id="grp-mf" style="display:none">
      <div class="alert alert-info" style="margin-bottom:12px;font-size:11px;line-height:1.7">
        📦 <strong>Rumus Reksa Dana:</strong><br>
        Uang Bersih = Dana − (Dana × Fee%) &nbsp;|&nbsp;
        Unit = Uang Bersih ÷ NAB Beli &nbsp;|&nbsp;
        Nilai = Unit × NAB Saat Ini
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Kode / Nama Reksa Dana</label>
          <input type="text" class="form-control" id="inv-mf-ticker"
            placeholder="NISP Optima, MAMI, Schroder..."
            oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group">
          <label class="form-label">Jumlah Uang Investasi (Rp)</label>
          <input type="number" class="form-control" id="inv-mf-invest"
            placeholder="Contoh: 1000000" step="any" oninput="calcMFUnits()">
          <div class="form-hint">Uang kotor sebelum fee dipotong</div>
        </div>
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">NAB per Unit saat Beli (Rp)</label>
          <input type="number" class="form-control" id="inv-mf-nab-buy"
            placeholder="Contoh: 2000" step="any" oninput="calcMFUnits()">
          <div class="form-hint">Nilai Aktiva Bersih/unit saat transaksi beli</div>
        </div>
        <div class="form-group">
          <label class="form-label">Fee Pembelian / Subscription (%)</label>
          <input type="number" class="form-control" id="inv-mf-fee"
            placeholder="0" step="any" min="0" max="100" oninput="calcMFUnits()">
          <div class="form-hint">Kosongkan atau isi 0 jika tidak ada biaya</div>
        </div>
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">NAB per Unit Saat Ini (Rp)</label>
          <input type="number" class="form-control" id="inv-mf-nab-cur"
            placeholder="Contoh: 2500" step="any" oninput="calcMFUnits()">
          <div class="form-hint">Untuk menghitung nilai & PnL terkini</div>
        </div>
        <div class="form-group">
          <label class="form-label">Unit Didapat <span style="color:var(--gold)">(auto)</span></label>
          <input type="number" class="form-control" id="inv-mf-unit"
            placeholder="= Uang Bersih ÷ NAB Beli" readonly
            style="color:var(--gold);background:var(--surface3)">
          <div class="form-hint" style="color:var(--cyan)">Dihitung otomatis</div>
        </div>
      </div>

      <!-- Live calculation preview box -->
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;font-size:12px;margin-bottom:14px" id="mf-calc-preview">
        <span style="color:var(--text3)">Isi Jumlah Uang &amp; NAB Beli → Unit dihitung otomatis</span>
      </div>
    </div>

    <!-- PROPERTY (Tokenisasi) -->
    <div id="grp-prop" style="display:none">
      <div class="alert alert-info" style="margin-bottom:12px">
        🏠 Properti Tokenisasi — input modal, pendapatan bulanan &amp; yield per tahun
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Modal / Harga Beli Token (Rp)</label>
          <input type="number" class="form-control" id="inv-prop-buy" placeholder="0" step="any"
            oninput="calcPropYield()">
          <div class="form-hint">Total modal yang diinvestasikan</div>
        </div>
        <div class="form-group">
          <label class="form-label">Pendapatan per Bulan (Rp)</label>
          <input type="number" class="form-control" id="inv-prop-cur" placeholder="0" step="any"
            oninput="syncYieldFromMonthly()">
          <div class="form-hint">Distribusi / sewa bulanan yang diterima</div>
        </div>
      </div>
      <div class="form-grid" style="margin-bottom:14px">
        <div class="form-group">
          <label class="form-label">Yield per Tahun (%)</label>
          <input type="number" class="form-control" id="inv-prop-yield" placeholder="0.00" step="any" min="0"
            oninput="syncMonthlyFromYield()">
          <div class="form-hint">Input langsung % yield/tahun — auto hitung pendapatan bulanan</div>
        </div>
        <div class="form-group">
          <label class="form-label">Pendapatan per Tahun (Rp)</label>
          <input type="number" class="form-control" id="inv-prop-annual" placeholder="Auto-hitung" step="any" readonly
            style="color:var(--gold);background:var(--surface3)">
          <div class="form-hint">Otomatis = pendapatan bulanan × 12</div>
        </div>
      </div>
      <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px;font-size:12px;margin-bottom:14px" id="prop-yield-preview">
        <span style="color:var(--text3)">Isi modal dan pendapatan / yield untuk melihat estimasi</span>
      </div>
    </div>

    <!-- BASIC AMOUNT (emergency / savings) -->
    <div id="grp-basic" style="margin-bottom:14px">
      <div class="form-group">
        <label class="form-label">Nominal (Rp)</label>
        <input type="number" class="form-control" id="inv-amount" placeholder="0.00" step="any">
      </div>
    </div>
    <div id="grp-savings-cur" style="display:none;margin-bottom:14px">
      <div class="form-group">
        <label class="form-label">Nilai Saat Ini (Rp) — opsional</label>
        <input type="number" class="form-control" id="inv-savings-cur" placeholder="Isi jika berbeda dari modal" step="any">
      </div>
    </div>

    <div class="form-grid" style="margin-bottom:14px">
      <div class="form-group">
        <label class="form-label">Tanggal</label>
        <input type="date" class="form-control" id="inv-date">
      </div>
      <div class="form-group">
        <label class="form-label">Catatan (opsional)</label>
        <input type="text" class="form-control" id="inv-note" placeholder="Catatan tambahan...">
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-gold" style="flex:1" onclick="saveInv()">💾 Simpan</button>
      <button class="btn btn-outline" onclick="closeModal('add-modal')">Batal</button>
    </div>
  </div>
</div>

<!-- ================== SELL MODAL ================== -->
<div class="modal-overlay" id="sell-modal">
  <div class="modal">
    <div class="modal-title">💸 Jual Investasi</div>
    <input type="hidden" id="sell-inv-id">
    <input type="hidden" id="sell-inv-cat">
    <div id="sell-info-wrap" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:18px">
      <table class="sell-info-table" style="width:100%;font-size:12px">
        <tr><td style="color:var(--text3)">Aset</td><td id="s-name" style="font-weight:600">—</td></tr>
        <tr><td style="color:var(--text3)">Qty</td><td id="s-qty">—</td></tr>
        <tr><td style="color:var(--text3)">Modal</td><td id="s-cost">—</td></tr>
        <tr id="s-pnl-row" style="display:none">
          <td style="color:var(--text3)">Total PnL</td>
          <td id="s-pnl" style="font-weight:700">—</td>
        </tr>
        <tr style="border-top:1px solid var(--border)">
          <td style="color:var(--gold);font-weight:600;padding-top:6px">Nilai Realisasi</td>
          <td id="s-val" style="color:var(--gold);font-weight:700;font-size:14px;padding-top:6px">—</td>
        </tr>
      </table>
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label class="form-label">Harga Jual / Nilai Realisasi (Rp)
        <span style="font-size:10px;color:var(--text3);font-weight:400;margin-left:6px">modal + PnL — bisa diubah manual</span>
      </label>
      <input type="number" class="form-control" id="sell-price" placeholder="0" step="any">
    </div>
    <div class="form-group" style="margin-bottom:18px">
      <label class="form-label">Tanggal Jual</label>
      <input type="date" class="form-control" id="sell-date">
    </div>
    <div class="modal-footer">
      <button class="btn btn-gold" style="flex:1" onclick="confirmSell()">✅ Konfirmasi Jual</button>
      <button class="btn btn-outline" onclick="closeModal('sell-modal')">Batal</button>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const CATS = <?= json_encode(CATEGORIES) ?>;
let cryptoPrices = <?= json_encode($cryptoPrices) ?>;

// ── Auto-refresh harga crypto tiap 30 detik ──────────────────
function updateCryptoDashboard(newPrices) {
  cryptoPrices = newPrices;

  let _cryptoCatVal = 0, _cryptoCatCost = 0, _cryptoCatPnl = 0;
  document.querySelectorAll('tr[data-crypto-row]').forEach(row => {
    const coinId = row.dataset.coinId;
    const price  = newPrices[coinId];
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

    const priceEl = document.getElementById('crypto-price-' + id);
    const valEl   = document.getElementById('crypto-val-'   + id);
    const pnlEl   = document.getElementById('crypto-pnl-'   + id);
    const pctEl   = document.getElementById('crypto-pct-'   + id);

    if (priceEl) priceEl.innerHTML = fmtIDR(price) + '<br><span class="badge badge-cyan" style="font-size:9px">LIVE</span>';
    if (valEl)   valEl.innerHTML   = '<strong>' + fmtIDR(curVal) + '</strong>';
    if (pnlEl)   { pnlEl.textContent = sign(pnl) + fmtIDR(pnl); pnlEl.style.color = pnlColor; }
    if (pctEl)   { pctEl.textContent = sign(pnlPct) + pnlPct.toFixed(2) + '%'; pctEl.style.color = pnlColor; }

    // Akumulasi untuk update header kategori
    _cryptoCatVal  += curVal;
    _cryptoCatCost += cost;
    _cryptoCatPnl  += pnl;
  });

  // ── Update header kategori crypto (table badge + total) ──
  if (_cryptoCatCost > 0) {
    const catPnlPct  = _cryptoCatPnl / _cryptoCatCost * 100;
    const sign2      = v => v >= 0 ? '+' : '';
    const catColor   = v => v > 0 ? 'var(--green)' : v < 0 ? 'var(--red)' : 'var(--text)';
    const catCls     = v => v > 0 ? 'badge-green' : v < 0 ? 'badge-red' : 'badge-neutral';

    const badgeEl = document.getElementById('dash-catbadge-crypto');
    if (badgeEl) {
      badgeEl.textContent = `PnL: ${sign2(_cryptoCatPnl)}${fmtIDR(_cryptoCatPnl)} (${sign2(catPnlPct)}${catPnlPct.toFixed(2)}%)`;
      badgeEl.className   = 'badge ' + catCls(_cryptoCatPnl);
      badgeEl.style.color = catColor(_cryptoCatPnl);
    }
    const totalEl = document.getElementById('dash-cattotal-crypto');
    if (totalEl) totalEl.textContent = 'Total: ' + fmtIDR(_cryptoCatVal);
  }

  const sign   = v => v >= 0 ? '+' : '';
  const cc     = v => v > 0 ? 'var(--green)' : v < 0 ? 'var(--red)' : 'var(--text)';
  const ccCls  = v => v > 0 ? 'badge-green' : v < 0 ? 'badge-red' : 'badge-neutral';

  // ── Top summary: Total Portfolio ──
  const dbPortoEl = document.getElementById('db-total-porto');
  if (dbPortoEl) {
    const bnc = parseFloat(dbPortoEl.dataset.baseNonCrypto) || 0;
    dbPortoEl.textContent = fmtIDR(bnc + _cryptoCatVal);
  }

  // ── Top summary: Total PnL ──
  const dbPnlCard = document.getElementById('db-totalpnl-card');
  const dbPnlVal  = document.getElementById('db-totalpnl-val');
  const dbPnlSub  = document.getElementById('db-totalpnl-sub');
  if (dbPnlCard && dbPnlVal) {
    const bReal   = parseFloat(dbPnlCard.dataset.baseRealized)     || 0;
    const bNcPnl  = parseFloat(dbPnlCard.dataset.baseNonCryptoPnl) || 0;
    const bCost   = parseFloat(dbPnlCard.dataset.baseCost)         || 0;
    const newUnreal = bNcPnl + _cryptoCatPnl;
    const newTotal  = newUnreal + bReal;
    const newPct    = bCost > 0 ? newTotal / bCost * 100 : 0;
    dbPnlVal.textContent = sign(newTotal) + fmtIDR(newTotal);
    dbPnlVal.style.color = cc(newTotal);
    if (dbPnlSub) dbPnlSub.textContent = sign(newPct) + newPct.toFixed(2) + '% keseluruhan';
  }

  // ── Ringkasan PnL: Net Unrealized ──
  const dbNetCard = document.getElementById('db-net-upnl-card');
  const dbNetVal  = document.getElementById('db-net-upnl-val');
  if (dbNetCard && dbNetVal) {
    const bNcPnl2  = parseFloat(dbNetCard.dataset.baseNonCryptoPnl) || 0;
    const newNet   = bNcPnl2 + _cryptoCatPnl;
    dbNetVal.textContent = sign(newNet) + fmtIDR(newNet);
    dbNetVal.style.color = cc(newNet);
  }

  // ── Ringkasan PnL: Unrealized Profit ──
  const dbProfitCard = document.getElementById('db-upnl-profit-card');
  const dbProfitVal  = document.getElementById('db-upnl-profit');
  if (dbProfitCard && dbProfitVal) {
    const bNc3  = parseFloat(dbProfitCard.dataset.baseNonCrypto) || 0;
    const gain  = Math.max(0, _cryptoCatPnl);
    dbProfitVal.textContent = fmtIDR(bNc3 + gain);
  }

  // ── Ringkasan PnL: Unrealized Loss ──
  const dbLossCard = document.getElementById('db-upnl-loss-card');
  const dbLossVal  = document.getElementById('db-upnl-loss');
  if (dbLossCard && dbLossVal) {
    const bNc4  = parseFloat(dbLossCard.dataset.baseNonCrypto) || 0;
    const loss  = Math.abs(Math.min(0, _cryptoCatPnl));
    dbLossVal.textContent = fmtIDR(bNc4 + loss);
  }

  // ── Target Total Portofolio ──
  const dbTgtPorto = document.getElementById('db-tgt-porto-val');
  const dbTgtPct   = document.getElementById('db-tgt-pct');
  const dbTgtBar   = document.getElementById('db-tgt-bar');
  const dbTgtSisa  = document.getElementById('db-tgt-sisa');
  const dbTgtStat  = document.getElementById('db-tgt-status');
  if (dbTgtPorto) {
    const bnc5 = parseFloat(dbTgtPorto.dataset.baseNonCrypto) || 0;
    dbTgtPorto.textContent = fmtIDR(bnc5 + _cryptoCatVal);
  }
  if (dbTgtPct) {
    const ttgt = parseFloat(dbTgtPct.dataset.totalTarget) || 0;
    const bnc6 = parseFloat(dbTgtPct.dataset.baseNonCrypto) || 0;
    const nv   = bnc6 + _cryptoCatVal;
    const np   = ttgt > 0 ? Math.min(nv / ttgt * 100, 100) : 0;
    dbTgtPct.textContent = np.toFixed(1) + '%';
    if (dbTgtBar) dbTgtBar.style.width = np.toFixed(2) + '%';
    if (dbTgtSisa) dbTgtSisa.textContent = ttgt > 0 ? 'Sisa: ' + fmtIDR(Math.max(0, ttgt - nv)) : '';
    if (dbTgtStat) dbTgtStat.textContent = np >= 100 ? '✅ Target tercapai!' : (ttgt > 0 ? 'Terus semangat!' : '');
  }

  // ── Per-category crypto card di grid target ──
  const dbCatSub   = document.getElementById('db-cat-sub-crypto');
  const dbCatBadge = document.getElementById('db-cat-badge-crypto');
  const dbCatBar   = document.getElementById('db-cat-bar-crypto');
  const dbCatPct   = document.getElementById('db-cat-pct-crypto');
  if (dbCatSub)   dbCatSub.textContent = `1 investasi · ${fmtIDR(_cryptoCatVal)}`;
  if (dbCatBadge && _cryptoCatCost > 0) {
    dbCatBadge.textContent = sign(_cryptoCatPnl) + fmtIDR(_cryptoCatPnl);
    dbCatBadge.className   = 'badge ' + ccCls(_cryptoCatPnl);
    dbCatBadge.style.color = cc(_cryptoCatPnl);
  }
  if (dbCatBar) {
    const catTgt = parseFloat(dbCatBar.dataset.tgt) || 0;
    const catNp  = catTgt > 0 ? Math.min(_cryptoCatVal / catTgt * 100, 100) : 0;
    dbCatBar.style.width = catNp.toFixed(2) + '%';
    if (dbCatPct) dbCatPct.textContent = catNp.toFixed(1) + '%';
  }
}

async function fetchAndUpdateCryptoDash() {
  try {
    const r = await fetch(BASE_URL + 'api/crypto.php?action=auto');
    const d = await r.json();
    if (d.prices && Object.keys(d.prices).length > 0) {
      updateCryptoDashboard(d.prices);
    }
  } catch(e) { /* network error */ }
}

// Langsung update saat dashboard dibuka
fetchAndUpdateCryptoDash();

// Ulangi tiap 65 detik
setInterval(fetchAndUpdateCryptoDash, 65000);

// ---- FILTER ----
function filterCat(cat, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.cat-section').forEach(s => {
    s.style.display = (cat === 'all' || s.id === 'section-'+cat) ? '' : 'none';
  });
}

// ---- ADD / EDIT MODAL ----
function onCatChange() {
  const cat = document.getElementById('inv-cat').value;
  document.getElementById('grp-crypto').style.display     = cat==='crypto'      ? '' : 'none';
  document.getElementById('grp-stock').style.display      = cat==='stocks'      ? '' : 'none';
  document.getElementById('grp-mf').style.display         = cat==='mutualFunds' ? '' : 'none';
  document.getElementById('grp-prop').style.display       = cat==='property'    ? '' : 'none';
  document.getElementById('grp-basic').style.display      = (cat==='emergency'||cat==='savings') ? '' : 'none';
  document.getElementById('grp-savings-cur').style.display= cat==='savings'     ? '' : 'none';
  if (cat==='stocks') calcStockTotal();
  if (cat==='mutualFunds') calcMFUnits();
}

function openAddModal(cat) {
  document.getElementById('inv-id').value   = '';
  document.getElementById('modal-title').textContent = '➕ Tambah Investasi';
  if (cat) document.getElementById('inv-cat').value = cat;
  document.getElementById('inv-date').value = new Date().toISOString().slice(0,10);
  // Reset all fields
  ['inv-name','inv-ticker','inv-qty','inv-buy-price','inv-amount-crypto','inv-amount',
   'inv-stock-ticker','inv-stock-buy','inv-stock-qty','inv-stock-cur',
   'inv-mf-ticker','inv-mf-invest','inv-mf-nab-buy','inv-mf-fee','inv-mf-nab-cur','inv-mf-unit',
   'inv-prop-buy','inv-prop-cur','inv-prop-yield','inv-savings-cur','inv-note'].forEach(f=>{
    const el=document.getElementById(f); if(el) el.value='';
  });
  onCatChange();
  openModal('add-modal');
}

function openEditModal(inv) {
  document.getElementById('inv-id').value   = inv.id;
  document.getElementById('inv-cat').value  = inv.category;
  document.getElementById('inv-name').value = inv.name || '';
  document.getElementById('inv-date').value = inv.inv_date || new Date().toISOString().slice(0,10);
  document.getElementById('inv-note').value = inv.note || '';
  document.getElementById('modal-title').textContent = '✏ Edit Investasi';
  onCatChange();

  const cat = inv.category;
  if (cat === 'crypto') {
    document.getElementById('inv-ticker').value       = inv.ticker || '';
    document.getElementById('inv-qty').value          = inv.qty || '';
    document.getElementById('inv-buy-price').value    = inv.buy_price || '';
    document.getElementById('inv-amount-crypto').value= inv.amount || '';
  } else if (cat === 'stocks') {
    document.getElementById('inv-stock-ticker').value = inv.ticker || '';
    document.getElementById('inv-stock-buy').value    = inv.buy_price || '';
    document.getElementById('inv-stock-qty').value    = inv.qty || '';
    document.getElementById('inv-stock-cur').value    = inv.current_price || '';
    calcStockTotal();
  } else if (cat === 'mutualFunds') {
    // MF: qty = unit, buy_price = NAB beli, amount = modal bersih, current_price = NAB saat ini
    document.getElementById('inv-mf-ticker').value   = inv.ticker || '';
    document.getElementById('inv-mf-invest').value   = inv.amount || '';         // modal bersih
    document.getElementById('inv-mf-nab-buy').value  = inv.buy_price || '';     // NAB beli
    document.getElementById('inv-mf-unit').value     = inv.qty || '';           // unit
    document.getElementById('inv-mf-nab-cur').value  = inv.current_price || '';
    calcMFUnits();
  } else if (cat === 'property') {
    document.getElementById('inv-prop-buy').value     = inv.buy_price || '';
    document.getElementById('inv-prop-cur').value     = inv.current_value || '';
    // Hitung yield dari data yang ada
    const modal = parseFloat(inv.buy_price) || 0;
    const monthly = parseFloat(inv.current_value) || 0;
    if (modal > 0 && monthly > 0) {
      document.getElementById('inv-prop-yield').value  = (monthly * 12 / modal * 100).toFixed(2);
      document.getElementById('inv-prop-annual').value = (monthly * 12).toFixed(0);
    }
    calcPropYield();
  } else {
    document.getElementById('inv-amount').value       = inv.amount || '';
    document.getElementById('inv-savings-cur').value  = inv.current_value || '';
  }
  openModal('add-modal');
}

async function saveInv() {
  const id  = document.getElementById('inv-id').value;
  const cat = document.getElementById('inv-cat').value;
  const name= document.getElementById('inv-name').value.trim();
  const date= document.getElementById('inv-date').value;
  const note= document.getElementById('inv-note').value.trim();

  // Blok penambahan baru jika saldo kas = 0
  if (!id) { // hanya cek saat tambah baru, bukan edit
    const balEl = document.getElementById('cash-balance-val');
    const bal   = balEl ? parseFloat(balEl.dataset.raw) : null;
    if (bal !== null && bal <= 0) {
      toast('❌ Saldo kas kosong! Tambahkan kas terlebih dahulu sebelum investasi.', 'error', 5000);
      return;
    }
  }

  const payload = { id: id||null, category: cat, name, inv_date: date, note };

  if (cat === 'emergency' || cat === 'savings') {
    const amount = parseFloat(document.getElementById('inv-amount').value);
    if (!amount || amount <= 0) { toast('Masukkan nominal yang valid', 'error'); return; }
    payload.amount = amount;
    if (cat === 'savings') {
      const cur = parseFloat(document.getElementById('inv-savings-cur').value);
      if (cur) payload.current_value = cur;
    }
  } else if (cat === 'crypto') {
    const ticker   = document.getElementById('inv-ticker').value.trim().toUpperCase();
    const qty      = parseFloat(document.getElementById('inv-qty').value);
    const buyPrice = parseFloat(document.getElementById('inv-buy-price').value);
    if (!ticker) { toast('Masukkan ticker crypto', 'error'); return; }
    if (!qty || qty <= 0) { toast('Masukkan jumlah koin', 'error'); return; }
    payload.ticker    = ticker;
    payload.qty       = qty;
    payload.buy_price = buyPrice || null;
    payload.amount    = buyPrice && qty ? buyPrice*qty : (parseFloat(document.getElementById('inv-amount-crypto').value)||0);
  } else if (cat === 'stocks') {
    const ticker   = document.getElementById('inv-stock-ticker').value.trim().toUpperCase();
    const buyPrice = parseFloat(document.getElementById('inv-stock-buy').value);
    const qty      = parseFloat(document.getElementById('inv-stock-qty').value);
    const curPrice = parseFloat(document.getElementById('inv-stock-cur').value);
    if (!qty||qty<=0)          { toast('Masukkan jumlah lot', 'error'); return; }
    if (!buyPrice||buyPrice<=0){ toast('Masukkan harga beli per lembar', 'error'); return; }
    payload.ticker        = ticker;
    payload.qty           = qty;                         // lot
    payload.buy_price     = buyPrice;                    // per lembar
    payload.current_price = curPrice || null;
    payload.amount        = buyPrice * qty * 100;        // modal = lot × 100 × harga lembar
  } else if (cat === 'mutualFunds') {
    // Rumus Reksa Dana:
    // Unit = Dana Bersih / NAB Beli
    // Nilai = Unit × NAB Saat Ini
    const ticker    = document.getElementById('inv-mf-ticker').value.trim().toUpperCase();
    const dana      = parseFloat(document.getElementById('inv-mf-invest').value);
    const nabBuy    = parseFloat(document.getElementById('inv-mf-nab-buy').value);
    const fee       = parseFloat(document.getElementById('inv-mf-fee').value) || 0;
    const nabCur    = parseFloat(document.getElementById('inv-mf-nab-cur').value) || 0;
    if (!dana||dana<=0)    { toast('Masukkan dana investasi', 'error'); return; }
    if (!nabBuy||nabBuy<=0){ toast('Masukkan NAB per unit saat beli', 'error'); return; }
    const danaBersih = dana * (1 - fee/100);     // dana setelah fee
    const unit       = danaBersih / nabBuy;       // unit yang didapat
    payload.ticker        = ticker || null;
    payload.qty           = unit;                 // unit reksa dana
    payload.buy_price     = nabBuy;               // NAB saat beli
    payload.current_price = nabCur || null;       // NAB saat ini
    payload.amount        = danaBersih;           // modal bersih (setelah fee)
  } else if (cat === 'property') {
    const buyPrice    = parseFloat(document.getElementById('inv-prop-buy').value);
    let   monthlyInc  = parseFloat(document.getElementById('inv-prop-cur').value) || 0;
    const yieldPct    = parseFloat(document.getElementById('inv-prop-yield').value) || 0;
    if (!buyPrice||buyPrice<=0){ toast('Masukkan modal / harga beli token properti', 'error'); return; }
    // Jika monthly kosong tapi yield diisi → hitung monthly dari yield
    if (!monthlyInc && yieldPct > 0) monthlyInc = buyPrice * yieldPct / 100 / 12;
    payload.buy_price     = buyPrice;
    payload.current_value = monthlyInc || null;
    payload.amount        = buyPrice;
  }

  try {
    const res = await api('investments.php', 'POST', payload);
    if (res.success) {
      toast(id ? 'Investasi diperbarui ✅' : 'Investasi berhasil ditambahkan ✅', 'success');
      closeModal('add-modal');
      setTimeout(() => location.reload(), 600);
    } else { toast('Error: ' + (res.error||''), 'error'); }
  } catch(e) { toast('Network error', 'error'); }
}

// ---- LOT CALCULATOR — Saham (1 lot = 100 lembar) ----
function calcStockTotal() {
  const buyPrice = parseFloat(document.getElementById('inv-stock-buy').value) || 0;
  const qty      = parseFloat(document.getElementById('inv-stock-qty').value) || 0;
  const lotInfo  = document.getElementById('lembar-info');
  const preview  = document.getElementById('stock-calc-preview');
  const lembar   = qty * 100;
  if (lotInfo) lotInfo.textContent = qty > 0 ? `= ${lembar.toLocaleString('id-ID')} lembar saham` : '';
  if (preview) {
    if (buyPrice > 0 && qty > 0) {
      const total = buyPrice * lembar;
      preview.innerHTML = `<span style="color:var(--text)">
        ${qty} lot × 100 lembar × <span style="color:var(--cyan)">Rp ${buyPrice.toLocaleString('id-ID')}</span>/lembar =
        <strong style="color:var(--gold)">Rp ${total.toLocaleString('id-ID', {minimumFractionDigits:0})}</strong> modal
      </span>`;
    } else { preview.innerHTML = '<span style="color:var(--text3)">Total modal = lot × 100 × harga per lembar</span>'; }
  }
}

// ---- REKSA DANA CALCULATOR ----
// Rumus:
//   Uang Bersih  = Dana - (Dana × Fee%)
//   Unit Didapat = Uang Bersih / NAB Beli
//   Nilai Saat Ini = Unit × NAB Saat Ini
//   Keuntungan = Nilai Jual - Modal Awal (Uang Bersih)
function calcMFUnits() {
  const dana   = parseFloat(document.getElementById('inv-mf-invest').value)  || 0;
  const nabBuy = parseFloat(document.getElementById('inv-mf-nab-buy').value) || 0;
  const fee    = parseFloat(document.getElementById('inv-mf-fee').value)     || 0;
  const nabCur = parseFloat(document.getElementById('inv-mf-nab-cur').value) || 0;
  const unitEl = document.getElementById('inv-mf-unit');
  const prev   = document.getElementById('mf-calc-preview');
  if (!prev) return;

  if (dana > 0 && nabBuy > 0) {
    // Step 1: Hitung uang bersih setelah fee
    const feeAmt     = dana * (fee / 100);          // potongan fee
    const uangBersih = dana - feeAmt;                // Uang Bersih = Dana - (Dana × Fee%)
    // Step 2: Hitung unit
    const unit       = uangBersih / nabBuy;          // Unit = Uang Bersih / NAB Beli
    // Step 3: Hitung nilai & keuntungan jika NAB sekarang diisi
    const nilaiCur   = nabCur > 0 ? unit * nabCur : null; // Nilai = Unit × NAB Saat Ini
    const keuntungan = nilaiCur !== null ? nilaiCur - uangBersih : null; // Untung = Nilai Jual - Modal

    if (unitEl) unitEl.value = unit.toFixed(6);

    const fmt = (n) => Math.abs(n).toLocaleString('id-ID', {maximumFractionDigits:0});
    const card = (label, val, color='var(--text)') =>
      `<div style="background:var(--surface3);border-radius:6px;padding:8px 10px">
        <div style="font-size:9px;color:var(--text3);margin-bottom:3px">${label}</div>
        <div style="font-size:12px;font-weight:500;color:${color}">Rp ${val}</div>
      </div>`;

    let html = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">`;
    html += card('💰 Uang Investasi', fmt(dana));
    if (fee > 0) {
      html += card(`🏷 Fee ${fee}% (Biaya)`, fmt(feeAmt), 'var(--red)');
      html += card('✅ Uang Bersih', fmt(uangBersih), 'var(--gold)');
    }
    html += card('📊 NAB Beli/Unit', fmt(nabBuy));
    html += `<div style="background:var(--surface3);border:1px solid var(--cyan);border-radius:6px;padding:8px 10px;grid-column:${fee>0?'2':'1 / span 2'}">
      <div style="font-size:9px;color:var(--text3);margin-bottom:3px">📦 Unit Didapat</div>
      <div style="font-size:13px;font-weight:700;color:var(--cyan)">${unit.toFixed(4)} unit</div>
      <div style="font-size:9px;color:var(--text3);margin-top:2px">= Rp ${fmt(uangBersih)} ÷ Rp ${fmt(nabBuy)}</div>
    </div>`;
    html += '</div>';

    if (nilaiCur !== null) {
      html += `<div style="border-top:1px solid var(--border);padding-top:8px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">`;
      html += card('📈 NAB Saat Ini', fmt(nabCur));
      html += card('💎 Nilai Investasi', fmt(nilaiCur), 'var(--gold)');
      const kColor = keuntungan >= 0 ? 'var(--green)' : 'var(--red)';
      html += `<div style="background:var(--surface3);border:1px solid ${keuntungan>=0?'rgba(34,197,94,0.3)':'rgba(239,68,68,0.3)'};border-radius:6px;padding:8px 10px">
        <div style="font-size:9px;color:var(--text3);margin-bottom:3px">🎯 Keuntungan</div>
        <div style="font-size:12px;font-weight:700;color:${kColor}">${keuntungan>=0?'+':'-'}Rp ${fmt(keuntungan)}</div>
        <div style="font-size:9px;color:var(--text3);margin-top:2px">= Nilai - Uang Bersih</div>
      </div>`;
      html += '</div>';
    }
    prev.innerHTML = html;
  } else {
    if (unitEl) unitEl.value = '';
    prev.innerHTML = `<div style="color:var(--text3);font-size:11px">
      <strong>Cara isi:</strong><br>
      1. Masukkan jumlah uang investasi<br>
      2. Masukkan NAB per unit saat beli<br>
      3. Isi fee jika ada biaya (opsional)<br>
      4. Isi NAB saat ini untuk lihat PnL
    </div>`;
  }
}

function calcPropYield() {
  const modal   = parseFloat(document.getElementById('inv-prop-buy').value) || 0;
  const monthly = parseFloat(document.getElementById('inv-prop-cur').value) || 0;
  const preview = document.getElementById('prop-yield-preview');
  const annualEl= document.getElementById('inv-prop-annual');
  const yieldEl = document.getElementById('inv-prop-yield');
  if (!preview) return;
  if (modal > 0 && monthly > 0) {
    const annualYield = (monthly * 12 / modal * 100);
    const annual      = monthly * 12;
    // Sync field yield & tahunan (jika user tidak sedang edit field yield)
    if (document.activeElement?.id !== 'inv-prop-yield' && yieldEl)
      yieldEl.value = annualYield.toFixed(2);
    if (annualEl) annualEl.value = annual.toFixed(0);
    preview.innerHTML = `<span style="color:var(--text)">
      Rp ${monthly.toLocaleString('id-ID')}/bulan =
      <strong style="color:var(--green)">Rp ${annual.toLocaleString('id-ID')}/tahun</strong>
      &nbsp;|&nbsp; Yield: <strong style="color:var(--gold)">${annualYield.toFixed(2)}%/tahun</strong>
    </span>`;
  } else if (modal > 0) {
    preview.innerHTML = '<span style="color:var(--text3)">Isi pendapatan bulanan atau yield per tahun</span>';
    if (annualEl) annualEl.value = '';
  } else {
    preview.innerHTML = '<span style="color:var(--text3)">Isi modal dan pendapatan / yield</span>';
  }
}

// Sync: user input yield % → hitung monthly otomatis
function syncMonthlyFromYield() {
  const modal    = parseFloat(document.getElementById('inv-prop-buy').value) || 0;
  const yieldPct = parseFloat(document.getElementById('inv-prop-yield').value) || 0;
  const annualEl = document.getElementById('inv-prop-annual');
  const monthlyEl= document.getElementById('inv-prop-cur');
  if (modal > 0 && yieldPct > 0) {
    const monthly = modal * yieldPct / 100 / 12;
    const annual  = monthly * 12;
    if (monthlyEl) monthlyEl.value = monthly.toFixed(2);
    if (annualEl)  annualEl.value  = annual.toFixed(0);
    const preview = document.getElementById('prop-yield-preview');
    if (preview) preview.innerHTML = `<span style="color:var(--text)">
      Yield ${yieldPct}%/thn = Rp ${monthly.toLocaleString('id-ID', {minimumFractionDigits:0, maximumFractionDigits:0})}/bulan =
      <strong style="color:var(--green)">Rp ${annual.toLocaleString('id-ID', {minimumFractionDigits:0, maximumFractionDigits:0})}/tahun</strong>
    </span>`;
  }
}

// Sync: user input monthly → update yield %
function syncYieldFromMonthly() {
  calcPropYield();
}

// ---- DELETE ----
function deleteInv(id, cat) {
  confirm2('Hapus Investasi?', 'Data investasi akan dihapus permanen. Indeks akan diperbarui otomatis.', '🗑️', 'Ya, Hapus', 'btn-danger', async () => {
    const res = await api('investments.php', 'DELETE', { id });
    if (res.success) { toast('Investasi dihapus', 'info'); setTimeout(() => location.reload(), 600); }
    else toast('Gagal hapus: ' + (res.error||''), 'error');
  });
}

// ---- SELL ----
function openSellModal(info) {
  const cost  = parseFloat(info.cost)  || 0;
  const upnl  = parseFloat(info.upnl)  || 0;
  const rpnl  = parseFloat(info.rpnl)  || 0;
  const totalPnl    = upnl + rpnl;
  // Nilai realisasi = modal + total PnL (unrealized + realized)
  // Jika PnL = 0, fallback ke curVal (harga pasar, mis. crypto live)
  const realisasi   = totalPnl !== 0 ? (cost + totalPnl) : (parseFloat(info.curVal) || cost);

  document.getElementById('sell-inv-id').value  = info.id;
  document.getElementById('sell-inv-cat').value = info.cat;
  document.getElementById('s-name').textContent = info.name;
  document.getElementById('s-qty').textContent  = info.qty ? fmtNum(parseFloat(info.qty)) : '—';
  document.getElementById('s-cost').textContent = fmtIDR(cost);

  // Tampilkan baris PnL jika ada
  const pnlRow = document.getElementById('s-pnl-row');
  const pnlEl  = document.getElementById('s-pnl');
  if (totalPnl !== 0) {
    pnlRow.style.display = '';
    pnlEl.textContent    = `${pnlSign(totalPnl)}${fmtIDR(totalPnl)}`;
    pnlEl.style.color    = totalPnl > 0 ? 'var(--green)' : 'var(--red)';
  } else {
    pnlRow.style.display = 'none';
  }

  document.getElementById('s-val').textContent  = fmtIDR(realisasi);
  document.getElementById('sell-price').value   = realisasi.toFixed(2);
  document.getElementById('sell-date').value    = new Date().toISOString().slice(0,10);
  openModal('sell-modal');
}

async function confirmSell() {
  const id        = document.getElementById('sell-inv-id').value;
  const cat       = document.getElementById('sell-inv-cat').value;
  const sellPrice = parseFloat(document.getElementById('sell-price').value);
  const sellDate  = document.getElementById('sell-date').value;
  if (!sellPrice||sellPrice<=0) { toast('Masukkan harga jual yang valid', 'error'); return; }
  const res = await api('investments.php', 'PUT', { action:'sell', id, sell_price: sellPrice, sell_date: sellDate });
  if (res.success) {
    const pnl = res.realized_pnl;
    toast(`Dijual! Realized PnL: ${pnlSign(pnl)}${fmtIDR(pnl)} 💸`, pnl>=0?'success':'error');
    closeModal('sell-modal');
    setTimeout(() => location.reload(), 700);
  } else { toast('Gagal jual: ' + (res.error||''), 'error'); }
}

// ---- PDF EXPORT ----
function doExportPDF() {
  const cats   = <?= json_encode(CATEGORIES) ?>;
  const catStats = <?= json_encode(array_combine(array_keys(CATEGORIES), array_map(fn($d)=>$d['stats'],$catData))) ?>;
  const targets  = <?= json_encode(getTargets()) ?>;
  const allStats = <?= json_encode($allStats) ?>;
  const catInvsRaw = <?= json_encode(array_combine(array_keys(CATEGORIES), array_map(fn($d,$cat) =>
    array_map(fn($inv)=>[
      'name' => ($inv['name']??'') . ($inv['ticker'] ? ' ('.$inv['ticker'].')' : ''),
      'cost' => getInvCost($inv),
      'value'=> getInvCurrentValue($inv, $cryptoPrices),
      'date' => $inv['inv_date'] ?? '—',
    ], $d['invs'])
  , $catData, array_keys(CATEGORIES)))) ?>;
  const tableData = {};
  for (const cat of Object.keys(cats)) {
    tableData[cat] = {
      catLabel: cats[cat].icon+' '+cats[cat].label,
      hasPnl: cats[cat].has_pnl,
      ...catStats[cat],
      items: catInvsRaw[cat]
    };
  }
  const now = new Date();
  const fn = `PortoFolio_Dashboard_${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}_${String(now.getHours()).padStart(2,'0')}${String(now.getMinutes()).padStart(2,'0')}.pdf`;
  exportPDF(tableData, allStats, targets, fn);
}

// ─── TARGET FUNCTIONS ────────────────────────────────
function updateTargetPreview(cat) {
  // Live preview update only — actual save on button click
  const val    = parseFloat(document.getElementById('target-'+cat).value) || 0;
  const prev   = document.getElementById('tgt-preview-'+cat);
  if (!prev) return;
  // We don't know current value in JS easily, just show the target
  if (val > 0) {
    prev.textContent = 'Target: Rp ' + val.toLocaleString('id-ID');
    prev.style.color = 'var(--text2)';
  } else {
    prev.textContent = 'Belum ada target';
    prev.style.color = 'var(--text3)';
  }
}

async function saveTarget(cat) {
  const val = parseFloat(document.getElementById('target-'+cat)?.value) || 0;
  const res = await api('targets.php', 'POST', { category: cat, target_amount: val });
  if (res.success) {
    // Recalculate total
    const cats = ['emergency','savings','stocks','mutualFunds','crypto','property'];
    let total = 0;
    cats.forEach(c => { total += parseFloat(document.getElementById('target-'+c)?.value||0)||0; });
    await api('targets.php', 'POST', { category: 'total', target_amount: total });
    toast(`Target ${cat} disimpan ✅`, 'success');
    setTimeout(() => location.reload(), 500);
  } else toast('Gagal: '+(res.error||''), 'error');
}

async function saveAllTargets() {
  const cats = ['emergency','savings','stocks','mutualFunds','crypto','property'];
  let total  = 0;
  for (const cat of cats) {
    const val = parseFloat(document.getElementById('target-'+cat)?.value) || 0;
    total += val;
    await api('targets.php', 'POST', { category: cat, target_amount: val });
  }
  await api('targets.php', 'POST', { category: 'total', target_amount: total });
  toast('Semua target berhasil disimpan ✅', 'success');
  setTimeout(() => location.reload(), 700);
}

// ─── CASH FUNCTIONS ─────────────────────────────────
function openWithdrawModal() {
  document.getElementById('withdraw-date').value = new Date().toISOString().slice(0,10);
  document.getElementById('withdraw-amount').value = '';
  document.getElementById('withdraw-note').value = '';
  openModal('withdraw-modal');
}

// Parsing angka format Indonesia (titik=ribuan, koma=desimal): "1.000.000,50" → 1000000.50
function parseIDR(str) {
  if (!str) return NaN;
  // Hapus spasi
  let s = String(str).trim();
  // Cek apakah pakai koma sebagai desimal: "1.000,50" atau "1000,50"
  // Jika ada koma → koma = desimal, titik = ribuan
  if (s.includes(',')) {
    s = s.replace(/\./g, '').replace(',', '.');
  } else {
    // Tidak ada koma: titik bisa ribuan (1.000.000) atau desimal (0.5)
    // Jika lebih dari 1 titik → titik = ribuan
    const dots = (s.match(/\./g) || []).length;
    if (dots > 1) s = s.replace(/\./g, '');
    // 1 titik → biarkan (normal float)
  }
  return parseFloat(s);
}

async function saveCashTopup() {
  const amount = parseIDR(document.getElementById('cash-amount').value);
  const note   = document.getElementById('cash-note').value.trim();
  const date   = document.getElementById('cash-date').value;
  if (!amount || amount <= 0 || isNaN(amount)) { toast('Masukkan nominal yang valid', 'error'); return; }
  const res = await api('cash.php', 'POST', { type:'topup', amount, note, date });
  if (res.success) { toast(`✅ Kas +${fmtIDR(amount)} ditambahkan`, 'success'); closeModal('cash-modal'); setTimeout(()=>location.reload(),500); }
  else toast('Error: '+(res.error||''), 'error');
}

async function saveCashWithdraw() {
  const amount = parseIDR(document.getElementById('withdraw-amount').value);
  const note   = document.getElementById('withdraw-note').value.trim();
  const date   = document.getElementById('withdraw-date').value;
  if (!amount || amount <= 0 || isNaN(amount)) { toast('Masukkan nominal yang valid', 'error'); return; }
  const res = await api('cash.php', 'POST', { type:'withdrawal', amount, note, date });
  if (res.success) { toast(`Penarikan ${fmtIDR(amount)} dicatat`, 'info'); closeModal('withdraw-modal'); setTimeout(()=>location.reload(),500); }
  else toast('Error: '+(res.error||''), 'error');
}

// Semua riwayat kas bersifat permanen dan tidak dapat dihapus

// Set default dates for cash modals
document.getElementById('cash-date').value = new Date().toISOString().slice(0,10);
document.getElementById('withdraw-date').value = new Date().toISOString().slice(0,10);
// ─────────────────────────────────────────────────────

// Pre-open sell modal if redirected from overview
// ─── MAINTENANCE FUNCTIONS ───────────────────────────────
let _maintTab = 'default';
function setMaintTab(tab) {
  _maintTab = tab;
  const d = tab === 'default';
  document.getElementById('maint-default-form').style.display = d ? '' : 'none';
  document.getElementById('maint-custom-form').style.display  = d ? 'none' : '';
  document.getElementById('tab-default').className = d ? 'btn btn-gold btn-sm' : 'btn btn-outline btn-sm';
  document.getElementById('tab-custom').className  = d ? 'btn btn-outline btn-sm' : 'btn btn-gold btn-sm';
  if (d) updateMaintPreview();
}
function updateMaintPreview() {
  const title = (document.getElementById('maint-title')?.value  || 'Sedang dalam Pemeliharaan');
  const msg   = (document.getElementById('maint-message')?.value || 'Kami sedang melakukan peningkatan sistem.');
  const et    = document.getElementById('maint-end-time')?.value;
  const pt    = document.getElementById('prev-title');
  const pm    = document.getElementById('prev-msg');
  const pe    = document.getElementById('prev-time');
  if (pt) pt.textContent = title;
  if (pm) pm.textContent = msg;
  if (pe) pe.textContent = et
    ? 'Selesai: ' + new Date(et).toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'})
    : 'Tanpa batas waktu';
}
async function activateMaintenance() {
  const mode    = _maintTab;
  const payload = { action:'activate', mode };
  if (mode === 'default') {
    payload.title   = document.getElementById('maint-title').value.trim() || 'Sedang dalam Pemeliharaan';
    payload.message = document.getElementById('maint-message').value.trim();
    const et = document.getElementById('maint-end-time').value;
    if (et) payload.end_time = et;
  } else {
    const html = document.getElementById('maint-custom-html').value.trim();
    if (!html) { toast('Custom HTML tidak boleh kosong', 'error'); return; }
    payload.custom_html = html;
  }
  const res = await api('maintenance.php', 'POST', payload);
  if (res.success) {
    toast('Maintenance mode diaktifkan', 'info', 4000);
    closeModal('maintenance-modal');
    setTimeout(() => location.reload(), 700);
  } else toast('Gagal: ' + (res.error||''), 'error');
}
async function deactivateMaintenance() {
  confirm2('Matikan Maintenance?', 'Overview akan kembali tampil normal untuk semua pengunjung.', '✅', 'Ya, Matikan', 'btn-gold', async () => {
    const res = await api('maintenance.php', 'POST', { action:'deactivate' });
    if (res.success) { toast('Maintenance dinonaktifkan', 'success'); setTimeout(() => location.reload(), 600); }
    else toast('Gagal: '+(res.error||''), 'error');
  });
}
document.addEventListener('DOMContentLoaded', () => {
  ['maint-title','maint-message','maint-end-time'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', updateMaintPreview);
    document.getElementById(id)?.addEventListener('change', updateMaintPreview);
  });
  updateMaintPreview();
});
// ─────────────────────────────────────────────────────────



// ──────────────────────────────────────────────────────────────
// PnL — Unrealized & Realized per investasi
// ──────────────────────────────────────────────────────────────
const REALIZED_LABELS = {
  savings:    'Bunga tabungan',
  stocks:     'Dividen saham',
  mutualFunds:'Dividen reksa dana',
  crypto:     'Staking / reward',
  property:   'Bagi hasil / sewa',
  emergency:  '—',
};

// Kategori yang defaultnya realized (penghasilan rutin)
const REALIZED_DEFAULT_CATS = ['savings', 'property'];

let _pnlCurrentUnrealized = 0;
let _pnlCurrentRealized   = 0;
let _pnlType   = 'profit';
let _pnlKind   = 'unrealized';
let _pnlCat    = '';
let _pnlCoinId = '';
let _pnlCurQty = 0;

// Kategori only-realized: tidak perlu pilih jenis
const ONLY_REALIZED_CATS = ['savings', 'crypto'];
// Kategori no-loss: tidak mungkin rugi dari bunga/staking
const NO_LOSS_CATS = ['savings', 'crypto'];

function openPnlModal(invId, invName, currentUnrealized, currentRealized, cat, coinId, currentQty) {
  _pnlCurrentUnrealized = parseFloat(currentUnrealized) || 0;
  _pnlCurrentRealized   = parseFloat(currentRealized)   || 0;
  _pnlCat  = cat || '';
  _pnlType = 'profit';

  document.getElementById('pnl-inv-id').value  = invId;
  document.getElementById('pnl-inv-cat').value  = cat || '';
  document.getElementById('pnl-inv-name-title').textContent = invName;
  document.getElementById('pnl-delta-amount').value = '';
  document.getElementById('pnl-preview-box').style.display = 'none';

  // Nilai saat ini
  const uEl = document.getElementById('pnl-current-unrealized');
  uEl.textContent = (_pnlCurrentUnrealized >= 0 ? '+' : '') + fmtIDR(_pnlCurrentUnrealized);
  uEl.style.color = _pnlCurrentUnrealized > 0 ? 'var(--green)' : _pnlCurrentUnrealized < 0 ? 'var(--red)' : 'var(--text)';

  const rEl = document.getElementById('pnl-current-realized');
  rEl.textContent = (_pnlCurrentRealized >= 0 ? '+' : '') + fmtIDR(_pnlCurrentRealized);
  rEl.style.color = _pnlCurrentRealized > 0 ? 'var(--green)' : _pnlCurrentRealized < 0 ? 'var(--red)' : 'var(--text)';

  // Label realized sesuai kategori
  const rlabel = REALIZED_LABELS[cat] || 'Dividen / bunga / staking';
  document.getElementById('pnl-realized-label').textContent    = rlabel;
  document.getElementById('pnl-kind-realized-hint').textContent = rlabel;

  const onlyRealized = ONLY_REALIZED_CATS.includes(cat);

  // Sembunyikan pilihan Unrealized & kotak unrealized untuk only-realized
  document.getElementById('pnl-kind-wrap').style.display     = onlyRealized ? 'none' : 'block';
  document.getElementById('pnl-unrealized-box').style.display = onlyRealized ? 'none' : 'block';

  // Sembunyikan tombol Rugi untuk kategori no-loss (bunga/staking selalu positif)
  const lossBtn = document.getElementById('pnl-type-loss');
  lossBtn.style.display = NO_LOSS_CATS.includes(cat) ? 'none' : '';
  document.getElementById('pnl-type-grid').style.gridTemplateColumns =
    NO_LOSS_CATS.includes(cat) ? '1fr' : '1fr 1fr';

  // Simpan coin info untuk crypto staking
  _pnlCoinId  = coinId   || '';
  _pnlCurQty  = parseFloat(currentQty) || 0;

  // Hint qty untuk crypto
  const qtyHintEl = document.getElementById('pnl-qty-hint');
  if (cat === 'crypto' && qtyHintEl) {
    const price = cryptoPrices[coinId] || 0;
    if (price > 0) {
      qtyHintEl.style.display = 'block';
      qtyHintEl.textContent   = `Harga saat ini: ${fmtIDR(price)} — Qty koin akan ditambah otomatis`;
    } else {
      qtyHintEl.style.display = 'block';
      qtyHintEl.textContent   = 'Qty koin akan dihitung dari harga live CoinGecko';
    }
  } else if (qtyHintEl) {
    qtyHintEl.style.display = 'none';
  }

  // Set kind SEBELUM openModal agar _pnlKind sudah benar
  _pnlKind = onlyRealized ? 'realized' : 'unrealized';
  setPnlKind(_pnlKind);
  setPnlType('profit');
  openModal('pnl-modal');
}

function setPnlKind(kind) {
  _pnlKind = kind;
  const btnU = document.getElementById('pnl-kind-unrealized');
  const btnR = document.getElementById('pnl-kind-realized');
  if (kind === 'unrealized') {
    btnU.style.border = '2px solid var(--green)'; btnU.style.color = 'var(--green)';
    btnR.style.border = '2px solid var(--border)'; btnR.style.color = 'var(--text3)';
  } else {
    btnR.style.border = '2px solid var(--gold)'; btnR.style.color = 'var(--gold)';
    btnU.style.border = '2px solid var(--border)'; btnU.style.color = 'var(--text3)';
  }
  updatePnlPreview();
}

function setPnlType(type) {
  _pnlType = type;
  const btnP = document.getElementById('pnl-type-profit');
  const btnL = document.getElementById('pnl-type-loss');
  if (type === 'profit') {
    btnP.style.border = '2px solid var(--green)'; btnP.style.color = 'var(--green)';
    btnL.style.border = '2px solid var(--border)'; btnL.style.color = 'var(--text3)';
  } else {
    btnL.style.border = '2px solid var(--red)'; btnL.style.color = 'var(--red)';
    btnP.style.border = '2px solid var(--border)'; btnP.style.color = 'var(--text3)';
  }
  updatePnlPreview();
}

function updatePnlPreview() {
  const raw    = document.getElementById('pnl-delta-amount').value;
  const amount = parseIDR(raw);
  const preBox = document.getElementById('pnl-preview-box');
  const preVal = document.getElementById('pnl-preview-val');
  if (!amount || isNaN(amount) || amount <= 0) { preBox.style.display = 'none'; return; }

  const delta   = _pnlType === 'profit' ? amount : -amount;
  const current = _pnlKind === 'unrealized' ? _pnlCurrentUnrealized : _pnlCurrentRealized;
  const result  = current + delta;
  const label   = _pnlKind === 'unrealized' ? 'Unrealized' : 'Realized';

  preBox.style.display = 'block';

  if (_pnlCat === 'crypto' && _pnlKind === 'realized' && amount > 0) {
    const price    = cryptoPrices[_pnlCoinId] || 0;
    const qtyAdded = price > 0 ? (amount / price) : 0;
    const newQty   = _pnlCurQty + qtyAdded;
    const qtyStr   = qtyAdded > 0
      ? ` | +${fmtNum(qtyAdded, 8)} koin → total ${fmtNum(newQty, 8)}`
      : ' | harga live tidak tersedia';
    preVal.textContent = `Realized: +${fmtIDR(result)}${qtyStr}`;
  } else {
    preVal.textContent = `${label}: ${result >= 0 ? '+' : ''}${fmtIDR(result)}`;
  }
  preVal.style.color = result > 0 ? 'var(--green)' : result < 0 ? 'var(--red)' : 'var(--text)';
}

async function savePnlEntry() {
  const invId  = parseInt(document.getElementById('pnl-inv-id').value);
  const raw    = document.getElementById('pnl-delta-amount').value;
  const amount = parseIDR(raw);

  if (!amount || isNaN(amount) || amount <= 0) {
    toast('Masukkan nominal yang valid', 'error'); return;
  }

  const delta   = _pnlType === 'profit' ? amount : -amount;
  const payload = { investment_id: invId, kind: _pnlKind, delta };

  // Crypto staking: kirim harga live agar server bisa hitung qty
  if (_pnlCat === 'crypto' && _pnlKind === 'realized') {
    const price = cryptoPrices[_pnlCoinId] || 0;
    if (price > 0) {
      payload.coin_id    = _pnlCoinId;
      payload.price_idr  = price;
      payload.add_qty    = true;
    }
  }

  const res = await api('pnl.php', 'POST', payload);

  if (res.success) {
    const val   = _pnlKind === 'unrealized' ? res.unrealized_pnl : res.realized_pnl;
    const label = _pnlKind === 'unrealized' ? 'Unrealized' : 'Realized';
    let msg = `${label} PnL: ${val >= 0 ? '+' : ''}${fmtIDR(val)} ✅`;
    if (res.new_qty) msg += ` | Qty: ${fmtNum(res.new_qty, 8)}`;
    toast(msg, 'success');
    closeModal('pnl-modal');
    setTimeout(() => location.reload(), 600);
  } else {
    toast('Gagal: ' + (res.error || 'Unknown error'), 'error');
  }
}


<?php if ($preSellId && $preSellCat): ?>
window.addEventListener('load', () => {
  <?php
  $inv = null;
  foreach (($catData[$preSellCat]['invs']??[]) as $i) { if ($i['id']==$preSellId) { $inv=$i; break; } }
  if ($inv):
    $cost = getInvCost($inv);
    $curVal = getInvCurrentValue($inv, $cryptoPrices);
  ?>
  openSellModal(<?= json_encode([
    'id'    => $inv['id'],
    'cat'   => $preSellCat,
    'name'  => $inv['name'] ?? $inv['ticker'] ?? '—',
    'cost'  => $cost,
    'curVal'=> $curVal,
    'qty'   => $inv['qty'],
    'upnl'  => (float)($inv['unrealized_pnl'] ?? 0),
    'rpnl'  => (float)($inv['realized_pnl']   ?? 0),
  ]) ?>);
  <?php endif; ?>
});
<?php endif; ?>

<?php if ($preAddCat): ?>
window.addEventListener('load', () => openAddModal('<?= $preAddCat ?>'));
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
