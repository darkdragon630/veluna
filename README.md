# 📊 PortoFolio — Personal Investment Tracker

```
██████╗  ██████╗ ██████╗ ████████╗ ██████╗ ███████╗ ██████╗ ██╗     ██╗ ██████╗
██╔══██╗██╔═══██╗██╔══██╗╚══██╔══╝██╔═══██╗██╔════╝██╔═══██╗██║     ██║██╔═══██╗
██████╔╝██║   ██║██████╔╝   ██║   ██║   ██║█████╗  ██║   ██║██║     ██║██║   ██║
██╔═══╝ ██║   ██║██╔══██╗   ██║   ██║   ██║██╔══╝  ██║   ██║██║     ██║██║   ██║
██║     ╚██████╔╝██║  ██║   ██║   ╚██████╔╝██║     ╚██████╔╝███████╗██║╚██████╔╝
╚═╝      ╚═════╝ ╚═╝  ╚═╝   ╚═╝    ╚═════╝ ╚═╝      ╚═════╝ ╚══════╝╚═╝ ╚═════╝
```

**🏷️ v1.1.0** &nbsp;|&nbsp; **🛠️ Build 2025.03** &nbsp;|&nbsp; **🌙 Dark Gold Theme** &nbsp;|&nbsp; **⚡ Realtime Crypto**

---

## ✨ Apa itu PortoFolio?

**PortoFolio** adalah tracker investasi pribadi berbasis web, full-stack PHP + MySQL, dengan tampilan dark gold yang elegan. Dirancang untuk satu pengguna (admin) dengan tampilan overview publik yang bersih.

```
🌐 Overview Publik  →  Lihat ringkasan, target, & transaksi tanpa login
🔐 Dashboard Admin  →  Kelola semua investasi, kas, PnL, target, & maintenance
⚡ Crypto Live       →  Harga realtime CoinGecko, update tiap 65 detik
```

---

## 🚀 Cara Setup

### Langkah 1 — Import Database

```bash
# Via MySQL CLI:
mysql -u root -p nama_database < database.sql
```

> Atau via **phpMyAdmin**: Database → Import → pilih `database.sql` → Execute

---

### Langkah 2 — Konfigurasi Database

Edit `config/database.php`:

```php
$host = 'localhost';      // Host MySQL
$user = 'root';           // Username
$pass = '';               // Password
$db   = 'portofolio_db';  // Nama database
$port = 3306;             // Port (default 3306)
```

> **Hosting remote / shared?** Tambahkan `PDO::ATTR_EMULATE_PREPARES => true` di options array.

---

### Langkah 3 — Upload ke Server

```
public_html/
└── portofolio/       ← Upload seluruh folder ke sini
    ├── .htaccess     ← WAJIB ter-upload (aktifkan "Show hidden files" di FTP)
    └── ...
```

Akses: `https://yourdomain.com/portofolio/`

---

### Langkah 4 — Buat Akun Admin

```
https://yourdomain.com/portofolio/create
```

- Masukkan username & password *(min. 8 karakter + 1 huruf kapital + 1 angka)*
- Setelah submit, halaman `/create` **otomatis ditutup permanen (403)**

> 💡 **Lupa password?** Hapus row di tabel `auth_users` via phpMyAdmin, buka `/create` lagi.

---

### Langkah 5 — Login & Mulai

```
https://yourdomain.com/portofolio/login.php
```

---

## 📂 Struktur Proyek

```
portofolio/
│
├── 📄 index.php                 Overview publik (semua bisa lihat)
├── 📋 dashboard.php             Dashboard admin — CRUD, kas, PnL, target, maintenance
├── 🔐 login.php                 Login dengan rate limiting & lockout UI
├── 🚪 logout.php                Logout + hapus session
├── ⚙️  create.php                Setup akun (otomatis ditutup setelah dipakai)
├── 💡 suggestions.php           Form saran fitur publik
├── 🔧 maintenance_page.php      Halaman maintenance (default template)
│
├── config/
│   ├── config.php              Session, auth, CSRF, rate limiting, konstanta
│   └── database.php            Koneksi MySQL (PDO + retry cold start 4x)
│
├── includes/
│   ├── helpers.php             Semua fungsi PHP bisnis (investasi, kas, PnL, crypto)
│   ├── header.php              HTML header + navbar + CSS
│   └── footer.php              HTML footer + JS global (toast, modal, API helper)
│
├── api/
│   ├── investments.php         REST API CRUD investasi (GET/POST/PUT/DELETE)
│   ├── cash.php                REST API kas (topup, tarik)
│   ├── targets.php             REST API target per kategori
│   ├── crypto.php              Proxy CoinGecko + cache DB (TTL 60 detik)
│   ├── maintenance.php         REST API aktifkan / matikan maintenance
│   ├── suggestions.php         API saran fitur → WA
│   └── pnl.php                 REST API update unrealized/realized PnL
│
├── assets/
│   ├── style.css               Stylesheet (dark gold, responsive, CSS vars)
│   └── app.js                  JS shared: toast, modal, confirm2, fmtIDR, exportPDF
│
├── database.sql                MySQL schema lengkap
└── README.md                   Panduan ini
```

---

## ✅ Fitur Lengkap

### 📊 Portofolio & Investasi

| Fitur | Detail |
|-------|--------|
| **6 Kategori Aset** | Darurat 🛡️ · Tabungan 💰 · Saham 📈 · Reksa Dana 📦 · Crypto ₿ · Properti 🏠 |
| **CRUD Lengkap** | Tambah, edit, hapus, jual per investasi |
| **Saham (Lot)** | 1 lot = 100 lembar, harga beli & jual per lembar |
| **Reksa Dana** | Formula: Uang Bersih = Dana − (Dana × Fee%), Unit = Bersih ÷ NAB Beli |
| **Crypto Live** | Harga realtime CoinGecko · Cache DB 60 detik · Update DOM tiap 65 detik |
| **Properti Tokenisasi** | Input modal + yield % / pendapatan bulanan · auto-hitung satu sama lain |
| **Jual Investasi** | Catat harga jual → Realized PnL otomatis → Kas otomatis bertambah |
| **Target per Kategori** | Progress bar warna per kategori + total target portofolio |

---

### 📈 Sistem PnL (Profit & Loss)

```
Total PnL  =  Unrealized PnL  +  Realized PnL
                    │                   │
          Posisi aktif belum      Sudah terealisasi
          dijual (floating)       (jual / bunga / dividen)
```

| Kategori | Jenis PnL | Keterangan |
|----------|-----------|------------|
| Simpanan Darurat | — | Tidak ada PnL |
| Tabungan | **Realized** | Bunga sudah diterima = keuntungan nyata |
| Saham | **Unrealized** | Floating sampai dijual |
| Reksa Dana | **Unrealized** | Floating sampai dijual |
| Crypto | **Unrealized** | Dihitung otomatis dari harga live × qty |
| Properti | **Unrealized + Realized** | Unrealized = kenaikan token, Realized = bagi hasil |

**Cara kerja update PnL (sistem kumulatif delta):**

```
Hari 1: input +Rp 100.000  →  tersimpan: Rp 100.000
Hari 2: input −Rp  30.000  →  tersimpan: Rp  70.000
Hari 3: input +Rp  40.000  →  tersimpan: Rp 110.000
```

> ℹ️ **Crypto khusus:** Unrealized PnL crypto tidak disimpan di DB — selalu dihitung live dari `harga × qty − modal` setiap render halaman.

---

### ⚡ Realtime Crypto

Update otomatis **tanpa reload halaman** pada semua elemen berikut:

**Overview (`index.php`):**
```
Per baris     →  Nilai, PnL, live price badge
Kategori      →  Badge PnL, total nilai, chip breakdown
Stat cards    →  Total Portofolio, Unrealized PnL, Total PnL, Target %
Target bar    →  Progress bar, %, teks sisa, status tercapai
Mini card     →  Nilai, PnL, progress bar, % (grid target per-kategori)
```

**Dashboard (`dashboard.php`):**
```
Per baris     →  Harga live, Nilai Saat Ini, PnL, PnL%
Kategori      →  Badge PnL, total di header tabel
Stat cards    →  Total Portfolio, Total PnL
Ringkasan PnL →  Unrealized Profit, Unrealized Loss, Net Unrealized
Target        →  Nilai saat ini, %, progress bar, sisa, per-kategori grid
```

**Timing fetch:**
```
Load halaman  →  Fetch langsung (tidak tunggu 65 detik)
              ↓
           Interval 65 detik (sinkron dengan TTL cache = 60 detik)
```

---

### 💵 Manajemen Kas

| Fitur | Detail |
|-------|--------|
| **Topup Manual** | Tambah kas + catatan + tanggal |
| **Tarik Kas** | Catat pengeluaran dari kas |
| **Auto-potong** | Beli investasi baru → saldo otomatis berkurang |
| **Auto-tambah** | Jual investasi → hasil penjualan masuk kas |
| **Blokir Investasi** | Tidak bisa tambah investasi jika saldo kas ≤ 0 |
| **Riwayat Permanen** | Semua transaksi kas tidak bisa dihapus |
| **Format IDR** | Input `1.000.000` atau `1.000.000,50` (titik = ribuan, koma = desimal) |

---

### 🔧 Maintenance Mode

| Fitur | Detail |
|-------|--------|
| **Mode Default** | Judul + pesan + waktu selesai + countdown otomatis |
| **Mode Custom HTML** | Upload HTML penuh termasuk `<!DOCTYPE html>` |
| **Admin Bypass** | Admin tetap bisa lihat overview normal saat maintenance aktif |
| **Riwayat Log** | Semua sesi maintenance tercatat + durasi aktif |

---

### 🛡️ Keamanan

| Lapisan | Detail |
|---------|--------|
| **Setup Sekali** | `/create` tutup permanen (403) setelah akun dibuat |
| **bcrypt cost-12** | Hash password industry-standard |
| **CSRF Token** | Token unik per session, validasi setiap POST |
| **Rate Limiting** | Maks 5 gagal login → kunci IP 15 menit |
| **Lockout UI** | Form disembunyikan saat dikunci, tampil countdown real |
| **Session Security** | ID di-regenerate saat login + tiap 5 menit aktif |
| **HttpOnly Cookie** | Session tidak bisa diakses JavaScript |
| **DB Session Handler** | Session tersimpan di MySQL, bukan file system |
| **.htaccess** | Blokir akses langsung ke `config/`, `includes/`, `api/` |

---

## 🗃️ Skema Database

| Tabel | Fungsi |
|-------|--------|
| `investments` | Semua investasi aktif + kolom unrealized/realized PnL |
| `sell_history` | Riwayat jual + realized PnL snapshot |
| `targets` | Target nominal per kategori |
| `cash_ledger` | Riwayat transaksi kas (topup, jual, investasi, tarik) |
| `crypto_cache` | Cache harga CoinGecko (coin_id → price_idr, price_usd) |
| `maintenance` | Status maintenance saat ini (1 baris) |
| `maintenance_log` | Riwayat semua sesi maintenance + durasi |
| `auth_users` | Akun admin (username + bcrypt hash) |
| `login_attempts` | Log rate limiting per IP |
| `php_sessions` | Session handler berbasis DB |
| `feature_suggestions` | Saran fitur dari pengguna |

---

## ⚙️ Konfigurasi Lanjutan

### Nomor WhatsApp Saran Fitur

`config/config.php`:
```php
define('WA_NUMBER', '62812xxxxxxxx');
```

### Tambah Ticker Crypto

`includes/helpers.php` → array `COIN_MAP`:
```php
const COIN_MAP = [
    'BTC'  => 'bitcoin',
    'ETH'  => 'ethereum',
    'DOGE' => 'dogecoin',
    'SOL'  => 'solana',       // ← tambahkan di sini
    'AVAX' => 'avalanche-2',
];
```

> Cari coin ID yang tepat di: `https://api.coingecko.com/api/v3/coins/list`

### Auto-Refresh via Cron (Opsional)

```bash
# Refresh harga crypto tiap 5 menit
*/5 * * * * curl -s "https://yourdomain.com/portofolio/api/crypto.php?action=auto" > /dev/null
```

### Session Lifetime

```php
define('SESSION_LIFETIME', 86400); // 86400 = 24 jam (default)
```

---

## 🔌 API Reference

Semua endpoint di `api/` memerlukan CSRF token di header `X-CSRF-Token` (kecuali `crypto.php`).

### `api/investments.php`
| Method | Params / Body | Fungsi |
|--------|--------------|--------|
| `GET` | `?category=crypto` | Ambil investasi per kategori |
| `POST` | `{category, name, qty, ...}` | Tambah / edit investasi |
| `PUT` | `{action:'sell', id, sell_price}` | Jual investasi |
| `DELETE` | `{id}` | Hapus investasi |

### `api/crypto.php`
| Action | Fungsi |
|--------|--------|
| `?action=auto` | Baca cache, refresh jika TTL > 60 detik |
| `?action=prices` | Baca cache saja (tidak fetch CoinGecko) |
| `?action=refresh` | Force fetch CoinGecko sekarang |

### `api/pnl.php`
| Method | Body | Fungsi |
|--------|------|--------|
| `POST` | `{investment_id, kind, delta}` | Update PnL kumulatif (unrealized / realized) |
| `POST` | `{..., coin_id, price_idr, add_qty:true}` | Crypto staking: tambah qty otomatis |

---

## 🐛 Troubleshooting

| Masalah | Solusi |
|---------|--------|
| **404 semua halaman** | Aktifkan `mod_rewrite` Apache · Pastikan `.htaccess` ter-upload |
| **Error 500 di API** | Cek urutan require: `database.php` → `config.php` → `helpers.php` |
| **"MySQL server has gone away"** | Normal di shared hosting cold start — retry otomatis sudah ada (4× ~11 detik) |
| **Tidak bisa login** | Cek tabel `auth_users` ada isinya · Coba buka `/create` jika masih kosong |
| **Harga crypto kosong / 0** | Rate limit CoinGecko — tunggu 1–2 menit lalu klik `⟳ Refresh` |
| **Unrealized PnL selalu 0** | Pastikan memanggil `getTotalUnrealizedPnl($cryptoPrices)` bukan tanpa argumen |
| **Session logout sendiri** | Cek `SESSION_LIFETIME` di `config.php` · Pastikan tabel `php_sessions` ada |
| **Syntax error di index.php** | Cari deklarasi `const miniVal` yang duplikat — hapus blok yang lama |

---

## 📝 Changelog

### v1.1.0 — *2025.03 (terkini)*

**🔧 Bug Fixes**
- Fix `Uncaught SyntaxError: Identifier 'miniVal' has already been declared`
- Fix unrealized PnL crypto selalu 0 saat pertama load halaman
- Fix `getTotalUnrealizedPnl()` tidak menghitung price diff crypto dari live prices
- Fix session DB handler tidak menyimpan session dengan benar
- Fix logout error 500

**⚡ Realtime Improvements**
- Fetch harga crypto langsung saat halaman dibuka (sebelumnya tunggu 65 detik)
- Semua stat cards Overview & Dashboard sekarang update realtime
- Ringkasan PnL (Unrealized Profit / Loss / Net) di Dashboard realtime
- Target Total Portofolio + progress bar realtime di kedua halaman
- Grid target per-kategori crypto realtime (nilai, badge PnL, bar, %)
- Mini card crypto di Target Investasi (Overview) realtime
- Badge warna PnL kategori ikut berubah warna sesuai nilai terbaru

**🏗️ Perbaikan Arsitektur**
- `getTotalUnrealizedPnl(array $cryptoPrices = [])` — crypto PnL dihitung dari live cache
- Sistem `data-base-non-crypto` attributes sebagai anchor kalkulasi JS
- Dashboard: skema ID `db-*` untuk semua elemen realtime

---

### v1.0.0 — *2025.03*
- 🎉 Rilis pertama
- 6 kategori investasi dengan sistem PnL dual (unrealized + realized)
- Crypto realtime per baris via CoinGecko
- Manajemen kas lengkap (topup, tarik, auto-potong, blokir)
- Maintenance mode (default + custom HTML + countdown)
- CSRF, rate limiting, bcrypt cost-12, DB session handler
- Export PDF, target per kategori, riwayat transaksi
- Overview publik + Dashboard admin terpisah

---

## 📞 Dukungan & Feedback

Gunakan fitur **💡 Saran Fitur** di dalam aplikasi untuk kirim feedback langsung via WhatsApp.

---

*PortoFolio v1.1.0 — Dibuat dengan ❤️ untuk tracking investasi pribadi*
