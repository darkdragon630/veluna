/* =====================================================
   PortoFolio — Shared JavaScript v1.1.0
   ===================================================== */

// ===================== TOAST =====================
function toast(msg, type = 'info', duration = 3500) {
  const c = document.getElementById('toast-container');
  if (!c) return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' };
  el.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${msg}</span>`;
  c.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0'; el.style.transform = 'translateX(100%)';
    el.style.transition = '0.3s'; setTimeout(() => el.remove(), 300);
  }, duration);
}

// ===================== CONFIRM =====================
let _confirmCallback = null;
function confirm2(title, text, icon, okLabel, okClass, callback) {
  document.getElementById('confirm-title').textContent = title;
  document.getElementById('confirm-text').textContent  = text;
  document.getElementById('confirm-icon').textContent  = icon || '⚠️';
  const btn = document.getElementById('confirm-ok-btn');
  btn.textContent = okLabel || 'Ya, Lanjutkan';
  btn.className   = `btn ${okClass || 'btn-danger'}`;
  _confirmCallback = callback;
  document.getElementById('confirm-overlay').classList.add('open');
}
function confirmOk()    { if (_confirmCallback) _confirmCallback(); confirmCancel(); }
function confirmCancel(){ document.getElementById('confirm-overlay').classList.remove('open'); _confirmCallback = null; }

// ===================== MODAL =====================
function openModal(id)  { const el = document.getElementById(id); if(el) el.classList.add('open'); }
function closeModal(id) { const el = document.getElementById(id); if(el) el.classList.remove('open'); }
function closeAllModals() {
  document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeAllModals();
});

// ===================== MOBILE NAV =====================
function toggleMobileNav() {
  const nav = document.getElementById('mobile-nav');
  if(nav) nav.classList.toggle('open');
}

// ===================== FORMAT =====================
function fmtIDR(val, short = true) {
  if (val === null || val === undefined || isNaN(val)) return 'Rp 0';
  const sign = val < 0 ? '-' : '';
  const abs  = Math.abs(val);
  if (short) {
    if (abs >= 1e12) return sign + 'Rp ' + (abs/1e12).toFixed(2) + 'T';
    if (abs >= 1e9)  return sign + 'Rp ' + (abs/1e9).toFixed(2) + 'M';
    if (abs >= 1e6)  return sign + 'Rp ' + (abs/1e6).toFixed(2) + 'jt';
  }
  return sign + 'Rp ' + abs.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtNum(val, dec = 8) {
  const n = parseFloat(val);
  if (isNaN(n)) return '0';
  return n.toFixed(dec).replace(/\.?0+$/, '');
}
function pnlClass(v) { return v > 0 ? 'green' : v < 0 ? 'red' : 'neutral'; }
function pnlSign(v)  { return v >= 0 ? '+' : ''; }

// ===================== API HELPER =====================
async function api(endpoint, method = 'GET', data = null) {
  const opts = { method, headers: { 'Content-Type': 'application/json' } };
  if (data) opts.body = JSON.stringify(data);
  const res = await fetch((typeof BASE_URL !== 'undefined' ? BASE_URL : '') + 'api/' + endpoint, opts);
  return res.json();
}

// ===================== PNL TOGGLE =====================
function togglePnl(cat) {
  const dd   = document.getElementById(`pnl-dd-${cat}`);
  const chev = document.getElementById(`chev-${cat}`);
  if (!dd) return;
  dd.classList.toggle('open');
  if (chev) chev.classList.toggle('open');
}

// ===================== PDF EXPORT — DETAILED =====================
function exportPDF(tableData, stats, targets, filename, extras) {
  try {
    const { jsPDF } = window.jspdf;
    const doc  = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
    const W    = 210;
    const M    = 13;  // margin
    const cW   = W - M * 2; // content width
    let y      = 0;

    // ── Color palette ───────────────────────────────────────────
    const C = {
      bg:       [10, 12, 15],
      surface:  [20, 24, 32],
      surface2: [28, 33, 43],
      border:   [40, 48, 62],
      gold:     [240, 180, 41],
      goldDim:  [180, 130, 25],
      green:    [34, 197, 94],
      red:      [239, 68, 68],
      text:     [220, 225, 235],
      text2:    [130, 140, 160],
      text3:    [70,  80, 100],
      cyan:     [6,  182, 212],
    };

    // ── helpers ─────────────────────────────────────────────────
    const setFont = (style='normal', size=9, color=C.text) => {
      doc.setFont('helvetica', style);
      doc.setFontSize(size);
      doc.setTextColor(...color);
    };
    const fillRect = (x,yy,w,h,color) => {
      doc.setFillColor(...color);
      doc.rect(x,yy,w,h,'F');
    };
    const line = (x1,yy,x2,color=C.border) => {
      doc.setDrawColor(...color);
      doc.setLineWidth(0.2);
      doc.line(x1,yy,x2,yy);
    };
    const checkPage = (needed=20) => {
      if (y + needed > 282) {
        addFooter();
        doc.addPage();
        fillRect(0,0,W,297,C.bg);
        y = 16;
      }
    };
    const addFooter = () => {
      const pg = doc.getNumberOfPages();
      fillRect(0, 287, W, 10, [8,10,13]);
      setFont('normal', 6.5, C.text3);
      doc.text('PortoFolio — Dokumen Rahasia & Pribadi', M, 293);
      doc.text(`Halaman ${pg}`, W - M - 14, 293);
      const ts = new Date().toLocaleString('id-ID',{dateStyle:'medium',timeStyle:'short'});
      doc.text(`Dicetak: ${ts}`, W/2 - 20, 293);
    };

    // ── COVER PAGE ───────────────────────────────────────────────
    fillRect(0, 0, W, 297, C.bg);

    // decorative top bar
    fillRect(0, 0, W, 3, C.gold);

    // Logo area
    fillRect(M, 22, 14, 14, C.surface2);
    setFont('bold', 11, C.gold);
    doc.text('P', M + 4, 31.5);

    // Title
    setFont('bold', 22, C.gold);
    doc.text('PORTOFOLIO', M + 18, 30);
    setFont('normal', 9, C.text2);
    doc.text('Investment Tracker Report', M + 18, 37);

    // Divider
    line(M, 46, W - M, C.goldDim);

    // Date & summary block
    setFont('normal', 8.5, C.text2);
    const now = new Date();
    doc.text(`Tanggal Laporan: ${now.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}`, M, 54);
    doc.text(`Waktu: ${now.toLocaleTimeString('id-ID')}`, M, 61);

    // Summary stats box
    y = 76;
    fillRect(M, y, cW, 48, C.surface);
    doc.setDrawColor(...C.border); doc.setLineWidth(0.3);
    doc.rect(M, y, cW, 48, 'S');

    setFont('bold', 8, C.text3);
    doc.text('RINGKASAN PORTOFOLIO', M + 6, y + 8);

    const sumItems = [
      { label: 'Total Nilai Portofolio', val: fmtIDR(stats.totalValue, false), color: C.gold },
      { label: 'Total Modal Aktif',      val: fmtIDR(stats.totalCost, false),  color: C.text },
      { label: 'Total PnL',              val: `${pnlSign(stats.pnl)}${fmtIDR(stats.pnl, false)} (${pnlSign(stats.pnlPct)}${parseFloat(stats.pnlPct).toFixed(2)}%)`,
        color: stats.pnl >= 0 ? C.green : C.red },
    ];
    if (extras?.cashBalance !== undefined) {
      sumItems.push({ label: 'Saldo Kas', val: fmtIDR(extras.cashBalance, false), color: C.green });
    }

    sumItems.forEach((item, i) => {
      const col  = i < 2 ? M + 4 : M + cW/2 + 4;
      const rowY = y + 18 + (i % 2) * 14;
      setFont('normal', 7.5, C.text3); doc.text(item.label, col, rowY);
      setFont('bold', 9, item.color);  doc.text(item.val,   col, rowY + 6.5);
    });

    // Target total progress bar
    if (targets) {
      const totalTgt = Object.keys(targets).filter(k=>k!=='total').reduce((s,k)=>s+(targets[k]||0),0);
      if (totalTgt > 0) {
        const pct = Math.min(stats.totalValue / totalTgt * 100, 100);
        y = 134;
        setFont('normal', 7.5, C.text2);
        doc.text(`Target Total: ${fmtIDR(totalTgt, false)} — Tercapai: ${pct.toFixed(1)}%`, M, y);
        y += 4;
        fillRect(M, y, cW, 4, C.surface2);
        fillRect(M, y, cW * pct / 100, 4, C.gold);
        y += 10;
      }
    }

    // Categories summary table on cover
    y = 158;
    setFont('bold', 7.5, C.text3);
    doc.text('RINGKASAN PER KATEGORI', M, y); y += 5;

    const catCols = [M, M+52, M+90, M+128, M+160];
    fillRect(M, y, cW, 7, C.surface2);
    setFont('bold', 6.5, C.text3);
    ['Kategori','Nilai','Modal','PnL','% Target'].forEach((h,i) => doc.text(h, catCols[i]+2, y+4.8));
    y += 7;

    Object.entries(tableData).forEach(([cat, rows], idx) => {
      if (y > 260) return;
      if (idx % 2 === 0) fillRect(M, y, cW, 7, [14,17,23]);
      setFont('normal', 7, C.text);
      doc.text((rows.catLabel||cat).replace(/[\u{1F000}-\u{1FFFF}]/gu,'').replace(/[^\x00-\x7F]/g,'').trim().slice(0,18)||cat.toUpperCase(), catCols[0]+2, y+4.8);
      setFont('normal', 7, C.text);       doc.text(fmtIDR(rows.totalValue,false).slice(0,16), catCols[1]+2, y+4.8);
      setFont('normal', 7, C.text2);      doc.text(fmtIDR(rows.totalCost,false).slice(0,16),  catCols[2]+2, y+4.8);
      const pnl = rows.pnl || 0;
      setFont('bold', 7, pnl>=0?C.green:C.red);
      doc.text(`${pnlSign(pnl)}${fmtIDR(pnl,false)}`.slice(0,16), catCols[3]+2, y+4.8);
      const tgt = targets?.[cat] || 0;
      const tpct = tgt > 0 ? (rows.totalValue/tgt*100).toFixed(1)+'%' : '—';
      setFont('normal', 7, C.text2); doc.text(tpct, catCols[4]+2, y+4.8);
      y += 7;
    });

    // decorative bottom
    fillRect(0, 287, W, 10, [8,10,13]);
    setFont('normal', 6.5, C.text3);
    doc.text('PortoFolio Investment Tracker — Dokumen Rahasia', M, 293);
    doc.text('Halaman 1', W - M - 14, 293);

    // ── DETAIL PAGES — one per category ──────────────────────────
    Object.entries(tableData).forEach(([cat, rows]) => {
      if (!rows.items || rows.items.length === 0) return;

      doc.addPage();
      fillRect(0, 0, W, 297, C.bg);
      y = 0;

      // Category header bar
      fillRect(0, 0, W, 28, C.surface);
      fillRect(0, 0, 3, 28, C.gold);

      const catLabelClean = (rows.catLabel||cat)
        .replace(/[\u{1F000}-\u{1FFFF}]/gu,'')
        .replace(/[^\x00-\x7F]/g,'')
        .trim() || cat.toUpperCase();

      setFont('bold', 14, C.gold);
      doc.text(catLabelClean || cat.toUpperCase(), M + 4, 12);
      setFont('normal', 8, C.text2);
      doc.text(`${rows.items?.length || 0} investasi aktif`, M + 4, 20);

      // Stats row right side
      const statRX = W - M;
      setFont('bold', 9, C.text);
      doc.text(fmtIDR(rows.totalValue, false), statRX - 50, 11, {align:'right'});
      setFont('normal', 7, C.text3); doc.text('Nilai Saat Ini', statRX - 50, 17, {align:'right'});
      if (rows.hasPnl) {
        const pnl = rows.pnl || 0;
        setFont('bold', 9, pnl>=0?C.green:C.red);
        doc.text(`${pnlSign(pnl)}${fmtIDR(pnl,false)}`, statRX, 11, {align:'right'});
        setFont('normal', 7, C.text3); doc.text('PnL Total', statRX, 17, {align:'right'});
      }
      if (cat === 'property') {
        const monthlyTotal = rows.items?.reduce((s, inv) => s + (inv.monthly || 0), 0) || 0;
        if (monthlyTotal > 0) {
          setFont('normal', 7, C.green);
          doc.text(`+${fmtIDR(monthlyTotal,false)}/bln`, statRX, 24, {align:'right'});
        }
      }

      // Target progress
      const tgt = targets?.[cat] || 0;
      if (tgt > 0) {
        const pct = Math.min(rows.totalValue / tgt * 100, 100);
        y = 32;
        setFont('normal', 7, C.text2);
        doc.text(`Target: ${fmtIDR(tgt,false)} — Progress: ${pct.toFixed(1)}%`, M, y); y += 3.5;
        fillRect(M, y, cW, 3, C.surface2);
        fillRect(M, y, cW * pct / 100, 3, C.gold);
        y += 7;
      } else { y = 34; }

      line(M, y, W-M); y += 4;

      // Table header
      const isStock = cat === 'stocks';
      const isCrypto = cat === 'crypto';
      const isProp  = cat === 'property';
      const isSavings = cat === 'savings' || cat === 'emergency';

      let cols, headers;
      if (isSavings) {
        cols    = [M, M+70, M+115, M+155];
        headers = ['Nama / Keterangan','Modal (Rp)','Nilai Saat Ini (Rp)','Tanggal'];
      } else if (isProp) {
        cols    = [M, M+60, M+100, M+138, M+165];
        headers = ['Nama','Modal (Rp)','Pendapatan/Bln','Yield/Thn','Tgl Beli'];
      } else {
        cols    = [M, M+48, M+82, M+112, M+142, M+163];
        headers = ['Nama','Ticker','Qty / Lot','Modal (Rp)','Nilai Saat Ini','PnL'];
      }

      fillRect(M, y, cW, 7, C.surface2);
      setFont('bold', 6.5, C.text3);
      headers.forEach((h, i) => doc.text(h, cols[i]+2, y+4.8));
      y += 7;

      rows.items.forEach((inv, idx) => {
        checkPage(8);
        if (idx % 2 === 0) fillRect(M, y, cW, 7, [14,17,23]);

        const pnl = (inv.value || 0) - (inv.cost || 0);

        if (isSavings) {
          setFont('normal', 7.5, C.text);     doc.text((inv.name||'—').slice(0,30), cols[0]+2, y+4.8);
          setFont('normal', 7.5, C.text);     doc.text(fmtIDR(inv.cost,false).slice(0,20), cols[1]+2, y+4.8);
          setFont('normal', 7.5, C.text);     doc.text(fmtIDR(inv.value,false).slice(0,20), cols[2]+2, y+4.8);
          setFont('normal', 7, C.text3);       doc.text(inv.date||'—', cols[3]+2, y+4.8);
        } else if (isProp) {
          setFont('normal', 7.5, C.text);     doc.text((inv.name||'—').slice(0,22), cols[0]+2, y+4.8);
          setFont('normal', 7.5, C.text);     doc.text(fmtIDR(inv.cost,false).slice(0,18), cols[1]+2, y+4.8);
          setFont('bold', 7.5, C.green);      doc.text(inv.monthly ? fmtIDR(inv.monthly,false).slice(0,18) : '—', cols[2]+2, y+4.8);
          setFont('bold', 7.5, C.gold);       doc.text(inv.yieldAnn ? inv.yieldAnn.toFixed(2)+'%' : '—', cols[3]+2, y+4.8);
          setFont('normal', 7, C.text3);       doc.text(inv.date||'—', cols[4]+2, y+4.8);
        } else {
          setFont('normal', 7.5, C.text);     doc.text((inv.name||'—').slice(0,22), cols[0]+2, y+4.8);
          setFont('normal', 7.5, C.cyan);     doc.text((inv.ticker||'—').slice(0,10), cols[1]+2, y+4.8);
          setFont('normal', 7, C.text2);       doc.text(inv.qty ? fmtNum(inv.qty)+(isStock?' lot':'') : '—', cols[2]+2, y+4.8);
          setFont('normal', 7.5, C.text);     doc.text(fmtIDR(inv.cost,false).slice(0,16), cols[3]+2, y+4.8);
          setFont('normal', 7.5, C.text);     doc.text(fmtIDR(inv.value,false).slice(0,16), cols[4]+2, y+4.8);
          if (rows.hasPnl) {
            setFont('bold', 7.5, pnl>=0?C.green:C.red);
            doc.text(`${pnlSign(pnl)}${fmtIDR(pnl,false)}`.slice(0,16), cols[5]+2, y+4.8);
          }
        }

        // Note row if exists
        if (inv.note) {
          y += 7;
          if (idx%2===0) fillRect(M, y, cW, 5, [14,17,23]);
          setFont('normal', 6, C.text3);
          doc.text(`  Catatan: ${inv.note.slice(0,80)}`, cols[0], y+3.5);
        }
        y += 7;
      });

      // Category PnL summary row
      if (rows.hasPnl) {
        checkPage(14);
        y += 2;
        line(M, y, W-M, C.goldDim); y += 4;
        setFont('bold', 8, C.text2);
        doc.text('Total Kategori:', M + 2, y);
        setFont('bold', 8.5, C.gold);
        doc.text(fmtIDR(rows.totalValue, false), M + 38, y);
        const pnl = rows.pnl || 0;
        setFont('bold', 8.5, pnl>=0?C.green:C.red);
        doc.text(`PnL: ${pnlSign(pnl)}${fmtIDR(pnl,false)} (${pnlSign(rows.pnlPct||0)}${parseFloat(rows.pnlPct||0).toFixed(2)}%)`, M + 80, y);
        y += 8;
      }

      addFooter();
    });

    // ── SUMMARY LAST PAGE ───────────────────────────────────────
    doc.addPage();
    fillRect(0, 0, W, 297, C.bg);
    fillRect(0, 0, W, 3, C.gold);
    y = 18;

    setFont('bold', 14, C.gold); doc.text('REKAP & KESIMPULAN', M, y); y += 10;
    line(M, y, W-M, C.goldDim); y += 8;

    // PnL breakdown table
    setFont('bold', 8, C.text3); doc.text('PnL PER KATEGORI', M, y); y += 5;
    fillRect(M, y, cW, 7, C.surface2);
    setFont('bold', 6.5, C.text3);
    ['Kategori','Modal','Nilai Saat Ini','PnL','PnL %','% Target'].forEach((h,i) => {
      doc.text(h, [M,M+36,M+78,M+118,M+152,M+175][i]+2, y+4.8);
    });
    y += 7;

    let totalCostSum=0, totalValSum=0, totalPnlSum=0;
    Object.entries(tableData).forEach(([cat, rows], idx) => {
      if (idx%2===0) fillRect(M, y, cW, 7, [14,17,23]);
      const pnl = rows.pnl || 0;
      totalCostSum += rows.totalCost || 0;
      totalValSum  += rows.totalValue || 0;
      totalPnlSum  += pnl;
      const tgt    = targets?.[cat] || 0;
      const tpct   = tgt > 0 ? (rows.totalValue/tgt*100).toFixed(1)+'%' : '—';
      const catClean = (rows.catLabel||cat).replace(/[\u{1F000}-\u{1FFFF}]/gu,'').replace(/[^\x00-\x7F]/g,'').trim();
      setFont('normal',7,C.text);     doc.text(catClean.slice(0,16)||cat, M+2, y+4.8);
      setFont('normal',7,C.text2);    doc.text(fmtIDR(rows.totalCost,false).slice(0,16), M+38, y+4.8);
      setFont('normal',7,C.text);     doc.text(fmtIDR(rows.totalValue,false).slice(0,16), M+80, y+4.8);
      setFont('bold',7,pnl>=0?C.green:C.red); doc.text(`${pnlSign(pnl)}${fmtIDR(pnl,false)}`.slice(0,16), M+120, y+4.8);
      const pnlPct = rows.totalCost>0 ? (pnl/rows.totalCost*100) : 0;
      setFont('bold',7,pnl>=0?C.green:C.red); doc.text(`${pnlSign(pnlPct)}${pnlPct.toFixed(2)}%`, M+154, y+4.8);
      setFont('normal',7,C.text2);    doc.text(tpct, M+177, y+4.8);
      y += 7;
    });

    // Total row
    fillRect(M, y, cW, 8, C.surface2);
    setFont('bold', 8, C.gold);      doc.text('TOTAL', M+2, y+5.5);
    setFont('bold', 8, C.text);      doc.text(fmtIDR(totalCostSum,false), M+38, y+5.5);
    setFont('bold', 8, C.gold);      doc.text(fmtIDR(totalValSum,false), M+80, y+5.5);
    setFont('bold', 8, totalPnlSum>=0?C.green:C.red);
    doc.text(`${pnlSign(totalPnlSum)}${fmtIDR(totalPnlSum,false)}`, M+120, y+5.5);
    const totalPnlPct = totalCostSum>0 ? (totalPnlSum/totalCostSum*100) : 0;
    setFont('bold', 8, totalPnlSum>=0?C.green:C.red);
    doc.text(`${pnlSign(totalPnlPct)}${totalPnlPct.toFixed(2)}%`, M+154, y+5.5);
    y += 14;

    // Cash summary if available
    if (extras?.cashStats) {
      const cs = extras.cashStats;
      line(M, y, W-M, C.goldDim); y += 8;
      setFont('bold', 8, C.text3); doc.text('RINGKASAN KAS', M, y); y += 5;

      const cashItems = [
        ['Saldo Kas Tersedia', fmtIDR(cs.balance,false), cs.balance>=0?C.green:C.red],
        ['Total Masuk Manual', fmtIDR(cs.topup,false), C.green],
        ['Masuk dari Penjualan', fmtIDR(cs.from_sale,false), C.gold],
        ['Total Investasi Keluar', fmtIDR(cs.invest_out,false), C.red],
        ['Total Penarikan', fmtIDR(cs.withdrawal,false), C.red],
      ];
      cashItems.forEach((item, i) => {
        if (i%2===0) fillRect(M, y, cW/2, 7, [14,17,23]);
        setFont('normal',7,C.text3); doc.text(item[0], M+2, y+4.8);
        setFont('bold',8,item[2]);   doc.text(item[1], M+50, y+4.8);
        y += 7;
      });
      y += 4;
    }

    // Disclaimer
    line(M, y, W-M); y += 6;
    setFont('normal', 7, C.text3);
    doc.text('Laporan ini dibuat otomatis oleh PortoFolio Investment Tracker.', M, y); y += 5;
    doc.text('Data bersifat pribadi dan rahasia. Jangan disebarkan kepada pihak yang tidak berkepentingan.', M, y); y += 5;
    doc.text(`Dicetak: ${new Date().toLocaleString('id-ID')} — v1.1.0`, M, y);

    addFooter();

    doc.save(filename || `PortoFolio_${new Date().toISOString().slice(0,10)}.pdf`);
    toast('PDF berhasil diunduh ✅', 'success');
  } catch(e) {
    console.error('PDF error:', e);
    toast('Gagal generate PDF: ' + e.message, 'error');
  }
}
