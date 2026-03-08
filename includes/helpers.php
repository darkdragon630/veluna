<?php
// =====================================================
// PortoFolio — Shared Helpers & Categories v1.1.0
// =====================================================

define('CATEGORIES', [
    'emergency'   => ['label' => 'Simpanan Darurat', 'icon' => '🛡',  'color' => '#06b6d4', 'has_pnl' => false],
    'savings'     => ['label' => 'Tabungan',          'icon' => '💰',  'color' => '#22c55e', 'has_pnl' => true],
    'stocks'      => ['label' => 'Saham',             'icon' => '📈',  'color' => '#f97316', 'has_pnl' => true],
    'mutualFunds' => ['label' => 'Reksa Dana',        'icon' => '📦',  'color' => '#a855f7', 'has_pnl' => true],
    'crypto'      => ['label' => 'Crypto',            'icon' => '₿',   'color' => '#f0b429', 'has_pnl' => true],
    'property'    => ['label' => 'Properti',          'icon' => '🏠',  'color' => '#ef4444', 'has_pnl' => false], // pakai ROI yield bulanan
]);

define('COIN_MAP', [
    'BTC' => 'bitcoin',       'ETH' => 'ethereum',        'DOGE' => 'dogecoin',
    'BNB' => 'binancecoin',   'SOL' => 'solana',          'ADA'  => 'cardano',
    'XRP' => 'ripple',        'USDT'=> 'tether',          'USDC' => 'usd-coin',
    'SHIB'=> 'shiba-inu',     'DOT' => 'polkadot',        'AVAX' => 'avalanche-2',
    'MATIC'=>'matic-network', 'LINK'=> 'chainlink',       'UNI'  => 'uniswap',
    'ATOM'=> 'cosmos',        'LTC' => 'litecoin',        'TRX'  => 'tron',
    'NEAR'=> 'near',          'APT' => 'aptos',           'ARB'  => 'arbitrum',
    'OP'  => 'optimism',      'ICP' => 'internet-computer','INJ' => 'injective-protocol',
    'SUI' => 'sui',           'PEPE'=> 'pepe',            'FLOKI'=> 'floki',
    'WIF' => 'dogwifhat',     'BONK'=> 'bonk',            'TON'  => 'the-open-network',
    'AAVE'=> 'aave',          'MKR' => 'maker',           'SAND' => 'the-sandbox',
    'AXS' => 'axie-infinity', 'FIL' => 'filecoin',        'ALGO' => 'algorand',
    'VET' => 'vechain',       'HBAR'=> 'hedera-hashgraph','ETC'  => 'ethereum-classic',
]);

// ── CONSTANTS ──────────────────────────────────────
define('SHARES_PER_LOT', 100); // Indonesia: 1 lot = 100 lembar saham

// ── UTILITIES ──────────────────────────────────────
function getCoinId(string $ticker): string {
    $map = COIN_MAP;
    return $map[strtoupper($ticker)] ?? strtolower($ticker);
}

function fmtIDR(float $val, int $dec = 2): string {
    $sign = $val < 0 ? '-' : '';
    $abs  = abs($val);
    if ($abs >= 1_000_000_000_000) return $sign . 'Rp ' . number_format($abs / 1_000_000_000_000, 2) . 'T';
    if ($abs >= 1_000_000_000)     return $sign . 'Rp ' . number_format($abs / 1_000_000_000, 2) . 'M';
    if ($abs >= 1_000_000)         return $sign . 'Rp ' . number_format($abs / 1_000_000, 2) . 'jt';
    return $sign . 'Rp ' . number_format($abs, $dec, ',', '.');
}

function fmtIDRFull(float $val): string {
    $sign = $val < 0 ? '-' : '';
    return $sign . 'Rp ' . number_format(abs($val), 2, ',', '.');
}

function fmtNum(float $val, int $dec = 8): string {
    $str = number_format($val, $dec, '.', ',');
    if (str_contains($str, '.')) $str = rtrim(rtrim($str, '0'), '.');
    return $str;
}

function pnlClass(float $v): string { return $v > 0 ? 'green' : ($v < 0 ? 'red' : 'neutral'); }
function pnlSign(float $v): string  { return $v >= 0 ? '+' : ''; }

// ── DB QUERIES ─────────────────────────────────────
function getInvestments(?string $cat = null): array {
    $db  = getDB();
    $sql = "SELECT * FROM investments WHERE is_sold = 0";
    $p   = [];
    if ($cat) { $sql .= " AND category = ?"; $p[] = $cat; }
    $sql .= " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql); $stmt->execute($p);
    return $stmt->fetchAll();
}

function getSellHistory(int $limit = 50): array {
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM sell_history ORDER BY sell_date DESC, created_at DESC LIMIT ?");
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll();
}

function getRecentTransactions(int $limit = 30): array {
    $db = getDB();
    // buys = investasi aktif, sells = dari sell_history
    $buys = $db->query(
        "SELECT id, category, name, ticker, qty, amount AS amount, inv_date AS tx_date,
                'buy' AS tx_type, NULL AS realized_pnl, NULL AS sell_price
         FROM investments WHERE is_sold=0 ORDER BY created_at DESC LIMIT $limit"
    )->fetchAll();

    $sells = $db->query(
        "SELECT investment_id AS id, category, name, ticker, qty, cost AS amount,
                sell_date AS tx_date, 'sell' AS tx_type, realized_pnl, sell_price
         FROM sell_history ORDER BY sell_date DESC, created_at DESC LIMIT $limit"
    )->fetchAll();

    $all = array_merge($buys, $sells);
    usort($all, fn($a,$b) => strcmp($b['tx_date']??'', $a['tx_date']??''));
    return array_slice($all, 0, $limit);
}

function getTargets(): array {
    $db   = getDB();
    $rows = $db->query("SELECT category, target_amount FROM targets")->fetchAll();
    $res  = [];
    foreach ($rows as $r) $res[$r['category']] = (float)$r['target_amount'];
    return $res;
}

/** Hitung total target dari jumlah semua target kategori (bukan field 'total' terpisah) */
function calcTotalTarget(array $targets): float {
    $sum = 0;
    foreach (array_keys(CATEGORIES) as $cat) {
        $sum += $targets[$cat] ?? 0;
    }
    return $sum;
}

function getCryptoPrices(): array {
    $db   = getDB();
    $rows = $db->query("SELECT coin_id, price_idr FROM crypto_cache")->fetchAll();
    $res  = [];
    foreach ($rows as $r) $res[$r['coin_id']] = (float)$r['price_idr'];
    return $res;
}

/** Statistik investasi yang sudah terjual */
function getSoldStats(): array {
    $db = getDB();

    // PnL dari transaksi jual
    $row = $db->query(
        "SELECT
            COUNT(*) AS total_sold,
            COALESCE(SUM(cost),0)         AS total_modal_dijual,
            COALESCE(SUM(sell_price),0)   AS total_cash_masuk,
            COALESCE(SUM(realized_pnl),0) AS total_realized_pnl
         FROM sell_history"
    )->fetch();

    // Realized PnL dari semua investasi:
    // dividen saham, bagi hasil properti, staking crypto, bunga tabungan, dividen reksa dana
    $invRealRow = $db->query(
        "SELECT COALESCE(SUM(realized_pnl), 0) AS inv_realized
         FROM investments WHERE is_sold = 0"
    )->fetch();

    $invRealized = (float)($invRealRow['inv_realized'] ?? 0);

    return [
        'total_sold'            => (int)$row['total_sold'],
        'total_modal'           => (float)$row['total_modal_dijual'],
        'total_cash'            => (float)$row['total_cash_masuk'],
        'total_realized_pnl'    => (float)$row['total_realized_pnl'] + $invRealized,
        'realized_from_sell'    => (float)$row['total_realized_pnl'],
        'realized_from_savings' => $invRealized,  // dividen/bunga/staking/bagi hasil semua kategori
    ];
}

// ── CALCULATION ────────────────────────────────────

/**
 * Nilai saat ini sebuah investasi.
 * Saham : harga_per_lembar × lot × 100
 * Crypto: live price × qty
 * Properti: modal (nilai tokenisasi — PnL tidak dihitung dari sini)
 */
function getInvCurrentValue(array $inv, array $cryptoPrices): float {
    $cat    = $inv['category'];
    $qty    = (float)($inv['qty']    ?? 0);
    $amount = (float)($inv['amount'] ?? 0);

    if ($cat === 'emergency') return $amount;

    if ($cat === 'savings') return (float)($inv['current_value'] ?? $amount);

    if ($cat === 'stocks') {
        $cur = (float)($inv['current_price'] ?? 0);
        if ($cur && $qty) return $cur * $qty * SHARES_PER_LOT; // lot × 100 × harga lembar
        return (float)($inv['current_value'] ?? $amount);
    }
    if ($cat === 'mutualFunds') {
        // Nilai = Unit × NAB saat ini
        // buy_price = NAB beli, current_price = NAB saat ini, qty = unit, amount = modal bersih
        $nabCur = (float)($inv['current_price'] ?? 0);
        if ($nabCur && $qty) return $nabCur * $qty; // unit × NAB saat ini
        return (float)($inv['current_value'] ?? $amount);
    }

    if ($cat === 'crypto') {
        $coinId    = $inv['coin_id'] ?? getCoinId($inv['ticker'] ?? '');
        $livePrice = $cryptoPrices[$coinId] ?? 0;
        if ($livePrice && $qty) return $livePrice * $qty;
        $cur = (float)($inv['current_price'] ?? 0);
        if ($cur && $qty) return $cur * $qty;
        return $amount;
    }

    // Properti tokenisasi: nilai portofolio = modal awal (harga beli token)
    // current_value dipakai untuk menyimpan pendapatan per bulan
    if ($cat === 'property') return (float)($inv['buy_price'] ?? $amount);

    return $amount;
}

/**
 * Modal / biaya masuk.
 * Saham: buy_price per lembar × lot × 100
 */
function getInvCost(array $inv): float {
    $cat    = $inv['category'];
    $qty    = (float)($inv['qty']    ?? 0);
    $amount = (float)($inv['amount'] ?? 0);

    if ($cat === 'stocks') {
        $bp = (float)($inv['buy_price'] ?? 0);
        if ($bp && $qty) return $bp * $qty * SHARES_PER_LOT; // modal = lot × 100 × harga lembar
    }
    if ($cat === 'mutualFunds') {
        // Modal = dana bersih setelah fee (disimpan di amount)
        return (float)($inv['amount'] ?? 0);
    }
    if ($cat === 'crypto') {
        $bp = (float)($inv['buy_price'] ?? 0);
        if ($bp && $qty) return $bp * $qty;
    }
    if ($cat === 'property') return (float)($inv['buy_price'] ?? $amount);

    return $amount;
}

/** Pendapatan per bulan properti (di-store di current_value) */
function getPropertyMonthlyIncome(array $inv): float {
    return (float)($inv['current_value'] ?? 0);
}

/** Annualized yield properti */
function getPropertyYield(array $inv): float {
    $modal  = getInvCost($inv);
    $income = getPropertyMonthlyIncome($inv);
    if ($modal <= 0 || $income <= 0) return 0;
    return ($income * 12 / $modal) * 100;
}

/** Stats per kategori — PnL = sum(currentValue) - sum(cost) */
function getCatStats(string $cat, array $cryptoPrices): array {
    $invs = getInvestments($cat);
    $totalCost = 0; $totalValue = 0;
    foreach ($invs as $inv) {
        $totalCost  += getInvCost($inv);
        $totalValue += getInvCurrentValue($inv, $cryptoPrices);
    }
    $pnl    = $totalValue - $totalCost;
    $pnlPct = $totalCost > 0 ? ($pnl / $totalCost * 100) : 0;
    return compact('totalCost','totalValue','pnl','pnlPct') + ['count' => count($invs)];
}

/**
 * Total stats — pnl = SUM pnl semua kategori
 * Kategori profit berkontribusi positif, kategori loss berkontribusi negatif.
 */
function getAllStats(array $cryptoPrices): array {
    $totalCost  = 0;
    $totalValue = 0;
    $totalPnl   = 0;

    foreach (array_keys(CATEGORIES) as $cat) {
        $s = getCatStats($cat, $cryptoPrices);
        $totalCost  += $s['totalCost'];
        $totalValue += $s['totalValue'];
        $totalPnl   += $s['pnl'];  // profit kategori + (loss kategori -)
    }
    $pnlPct = $totalCost > 0 ? ($totalPnl / $totalCost * 100) : 0;
    return [
        'totalCost'  => $totalCost,
        'totalValue' => $totalValue,
        'pnl'        => $totalPnl,
        'pnlPct'     => $pnlPct,
    ];
}

// ── CASH LEDGER ────────────────────────────────────

/** Saldo kas tersedia saat ini */
function getCashBalance(): float {
    $db  = getDB();
    $row = $db->query("SELECT COALESCE(SUM(amount),0) AS bal FROM cash_ledger")->fetch();
    return (float)$row['bal'];
}

/** Statistik kas: total topup, total from_sale, total withdrawal */
function getCashStats(): array {
    $db   = getDB();
    $rows = $db->query(
        "SELECT type, COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
         FROM cash_ledger GROUP BY type"
    )->fetchAll();
    $res = ['topup'=>0,'from_sale'=>0,'withdrawal'=>0,'invest_out'=>0,'topup_cnt'=>0,'from_sale_cnt'=>0,'withdrawal_cnt'=>0,'invest_out_cnt'=>0];
    foreach ($rows as $r) {
        $res[$r['type']]        = (float)$r['total'];  // invest_out & withdrawal are negative
        $res[$r['type'].'_cnt'] = (int)$r['cnt'];
    }
    // balance = sum of all amounts (invest_out and withdrawal are stored negative)
    $res['balance'] = $res['topup'] + $res['from_sale'] + $res['invest_out'] + $res['withdrawal'];
    return $res;
}

/** Riwayat kas terbaru */
function getCashLedger(int $limit = 30): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT cl.*, inv.name AS inv_name, inv.ticker AS inv_ticker, inv.category AS inv_category
         FROM cash_ledger cl
         LEFT JOIN investments inv ON inv.id = cl.ref_invest_id
         ORDER BY cl.tx_date DESC, cl.created_at DESC
         LIMIT ?"
    );
    $stmt->execute([(int)$limit]);
    return $stmt->fetchAll();
}

/** Tambah kas manual (topup atau withdrawal) */
function addCashEntry(string $type, float $amount, string $note = '', string $date = '', ?int $refId = null): int {
    $db   = getDB();
    $date = $date ?: date('Y-m-d');
    $db->prepare(
        "INSERT INTO cash_ledger (type, amount, note, ref_invest_id, tx_date) VALUES (?,?,?,?,?)"
    )->execute([$type, $amount, $note ?: null, $refId, $date]);
    return (int)$db->lastInsertId();
}

// ── MAINTENANCE ────────────────────────────────────────

function getMaintenance(): array {
    try {
        $row = getDB()->query("SELECT * FROM maintenance WHERE id=1")->fetch();
        if (!$row) return ['is_active'=>0,'mode'=>'default','title'=>'','message'=>'','end_time'=>null,'custom_html'=>null];
        // Auto-deactivate jika end_time sudah lewat
        if ($row['is_active'] && $row['end_time'] && strtotime($row['end_time']) <= time()) {
            getDB()->exec("UPDATE maintenance SET is_active=0 WHERE id=1");
            $row['is_active'] = 0;
        }
        return $row;
    } catch (Throwable $e) { return ['is_active'=>0]; }
}

function setMaintenance(bool $active, array $data = []): bool {
    try {
        $db = getDB();
        if ($active) {
            $mode  = $data['mode']         ?? 'default';
            $title = $data['title']        ?? 'Sedang dalam Pemeliharaan';
            $msg   = $data['message']      ?? null;
            $end   = $data['end_time']     ?? null;
            $html  = $data['custom_html']  ?? null;
            $by    = $data['activated_by'] ?? null;

            // 1. Update state tabel maintenance
            $db->prepare(
                "INSERT INTO maintenance (id,is_active,mode,title,message,end_time,custom_html,activated_at,activated_by)
                 VALUES (1,1,?,?,?,?,?,NOW(),?)
                 ON DUPLICATE KEY UPDATE
                   is_active=1, mode=VALUES(mode), title=VALUES(title),
                   message=VALUES(message), end_time=VALUES(end_time),
                   custom_html=VALUES(custom_html),
                   activated_at=NOW(), activated_by=VALUES(activated_by)"
            )->execute([$mode, $title, $msg, $end, $html, $by]);

            // 2. Tutup log lama yang masih terbuka
            $db->prepare(
                "UPDATE maintenance_log
                 SET deactivated_at = NOW(),
                     deactivated_by = ?,
                     duration_sec   = TIMESTAMPDIFF(SECOND, activated_at, NOW())
                 WHERE deactivated_at IS NULL"
            )->execute([$by]);

            // 3. Catat sesi baru ke maintenance_log
            $db->prepare(
                "INSERT INTO maintenance_log (mode, title, message, end_time, activated_at, activated_by)
                 VALUES (?, ?, ?, ?, NOW(), ?)"
            )->execute([$mode, $title, $msg, $end, $by]);

        } else {
            $by = $data['deactivated_by'] ?? null;

            // 1. Matikan flag maintenance
            $db->exec("UPDATE maintenance SET is_active=0 WHERE id=1");

            // 2. Tutup log yang masih terbuka
            $db->prepare(
                "UPDATE maintenance_log
                 SET deactivated_at = NOW(),
                     deactivated_by = ?,
                     duration_sec   = TIMESTAMPDIFF(SECOND, activated_at, NOW())
                 WHERE deactivated_at IS NULL"
            )->execute([$by]);
        }
        return true;
    } catch (Throwable $e) { return false; }
}

// ─────────────────────────────────────────────────────────────
// MANUAL PnL — Keuntungan & Kerugian per investasi
// ─────────────────────────────────────────────────────────────

/** Ambil semua entri PnL manual untuk 1 investasi */

// ─────────────────────────────────────────────────────────────
// UNREALIZED PnL — satu nilai net per investasi
// Disimpan di kolom investments.unrealized_pnl
// Input: delta (hari ini +/-), disimpan: nilai kumulatif
// ─────────────────────────────────────────────────────────────

/**
 * Adjust PnL per investasi.
 * $kind: 'unrealized' (kenaikan harga) atau 'realized' (dividen/bunga/staking/bagi hasil)
 * $delta: positif = untung, negatif = rugi
 * Returns: ['unrealized_pnl' => x, 'realized_pnl' => y]
 */
function adjustInvPnl(int $invId, string $kind, float $delta): array {
    $db  = getDB();
    $col = $kind === 'realized' ? 'realized_pnl' : 'unrealized_pnl';
    $db->prepare(
        "UPDATE investments SET $col = COALESCE($col, 0) + ? WHERE id = ?"
    )->execute([$delta, $invId]);

    $row = $db->prepare("SELECT unrealized_pnl, realized_pnl FROM investments WHERE id = ?");
    $row->execute([$invId]);
    $r = $row->fetch();
    return [
        'unrealized_pnl' => (float)($r['unrealized_pnl'] ?? 0),
        'realized_pnl'   => (float)($r['realized_pnl']   ?? 0),
    ];
}

/**
 * Total unrealized PnL — kenaikan harga posisi aktif (belum dijual).
 * Semua kategori kecuali emergency (tidak ada PnL).
 */
function getTotalUnrealizedPnl(): array {
    try {
        $row = getDB()->query(
            "SELECT
               COALESCE(SUM(CASE WHEN unrealized_pnl > 0 THEN unrealized_pnl ELSE 0 END), 0) AS total_profit,
               COALESCE(SUM(CASE WHEN unrealized_pnl < 0 THEN unrealized_pnl ELSE 0 END), 0) AS total_loss,
               COALESCE(SUM(unrealized_pnl), 0) AS net
             FROM investments
             WHERE is_sold = 0 AND category != 'emergency'"
        )->fetch();
        return [
            'profit' => (float)$row['total_profit'],
            'loss'   => abs((float)$row['total_loss']),
            'net'    => (float)$row['net'],
        ];
    } catch (Throwable) { return ['profit'=>0,'loss'=>0,'net'=>0]; }
}
