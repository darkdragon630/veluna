# 📊 PortoFolio — Personal Investment Tracker
**Version:** 1.0.0 | **Build:** 2025.03

---

## 🚀 Cara Setup Pertama Kali

### Requirements
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.4+
- Web Server: Apache 2.4+ dengan `mod_rewrite` aktif

---

### Langkah 1 — Import Database
```sql
-- Via MySQL CLI:
mysql -u root -p < database.sql

-- Atau via phpMyAdmin: Database → Import → pilih database.sql → Execute
```

Jika upgrade dari versi sebelumnya, jalankan migrasi tambahan:
```sql
ALTER TABLE `investments`
  ADD COLUMN `unrealized_pnl` DECIMAL(28,8) DEFAULT 0
  COMMENT 'Unrealized PnL kumulatif (+ untung, - rugi)'
  AFTER `current_price`;
```

---

### Langkah 2 — Konfigurasi Database
Edit `config/database.php`:
```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'portofolio_db';
$port = 3306;
```

---

### Langkah 3 — Upload ke Server
1. Upload seluruh folder `portofolio/` ke `public_html/` atau `htdocs/`
2. Pastikan `.htaccess` ter-upload (aktifkan "Show hidden files" di FTP)
3. Akses: `https://yourdomain.com/portofolio/`

---

### Langkah 4 — Buat Akun Administrator
```
https://yourdomain.com/portofolio/create
```
- Masukkan username & password (min 8 karakter + 1 kapital + 1 angka)
- Setelah submit, halaman `/create` otomatis ditutup permanen (403)

> Jika lupa password: hapus row di tabel `auth_users` via phpMyAdmin, buka `/create` lagi.

---

### Langkah 5 — Login
`https://yourdomain.com/portofolio/login.php`

---

## 📂 Struktur File

```
portofolio/
├── .htaccess                  → Keamanan, routing, proteksi folder
├── index.php                  → Overview publik (semua bisa lihat)
├── dashboard.php              → Dashboard admin (CRUD + kas + PnL + target + maintenance)
├── login.php                  → Halaman login dengan rate limiting
├── logout.php                 → Logout + hapus session
├── create.php                 → Setup akun (otomatis ditutup setelah dipakai)
├── suggestions.php            → Form saran fitur (publik)
├── maintenance_page.php       → Halaman maintenance
├── config/
│   ├── config.php             → Session, auth, CSRF, rate limiting
│   └── database.php           → Koneksi MySQL (PDO + retry cold start)
├── includes/
│   ├── helpers.php            → Semua fungsi PHP (investasi, kas, PnL, maintenance)
│   ├── header.php             → HTML header + navbar
│   └── footer.php             → HTML footer
├── api/
│   ├── investments.php        → REST API CRUD investasi
│   ├── cash.php               → REST API kas
│   ├── targets.php            → REST API target
│   ├── crypto.php             → Proxy CoinGecko + cache
│   ├── maintenance.php        → REST API maintenance
│   ├── suggestions.php        → API saran fitur
│   └── pnl.php                → REST API update unrealized PnL
├── assets/
│   ├── style.css              → Stylesheet (dark gold theme)
│   └── app.js                 → JS shared utilities
├── database.sql               → MySQL schema lengkap
└── README.md                  → Panduan ini
```

---

## ✨ Fitur Lengkap

### 📊 Investasi & Portfolio
| Fitur | Keterangan |
|-------|-----------|
| 6 Kategori | Darurat, Tabungan, Saham, Reksa Dana, Crypto, Properti |
| CRUD Investasi | Tambah, edit, hapus per item |
| Jual Investasi | Catat harga jual + realized PnL otomatis masuk kas |
| Reksa Dana | Formula NAB: Unit = Uang Bersih ÷ NAB Beli, support fee % |
| Saham | Sistem lot (1 lot = 100 lembar) |
| Crypto | Harga realtime via CoinGecko |
| Properti | Input yield % / pendapatan bulanan |
| Target per Kategori | Progress bar + total target portofolio |

### 📈 PnL — Keuntungan & Kerugian
| Fitur | Keterangan |
|-------|-----------|
| Unrealized PnL | Satu nilai net per investasi, input delta harian (+/-) |
| Realized PnL | Otomatis dari transaksi jual + bunga/keuntungan tabungan |
| Total PnL | Unrealized + Realized, tampil di overview & dashboard |
| Logika Tabungan | Keuntungan nabung = **Realized** (sudah diterima) |
| Logika Lainnya | Posisi aktif = **Unrealized** sampai benar-benar dijual |
| Update Kumulatif | Input delta hari ini, sistem akumulasi otomatis |

**Contoh alur unrealized PnL:**
```
Hari 1: +Rp 100.000  →  tersimpan: +Rp 100.000
Hari 2: rugi 30.000  →  tersimpan: +Rp  70.000
Hari 3: +Rp  40.000  →  tersimpan: +Rp 110.000
```

### 💵 Kas & Keuangan
| Fitur | Keterangan |
|-------|-----------|
| Saldo Kas | Top-up manual, auto-potong investasi, auto-tambah saat jual |
| Format IDR | Input `1.000.000` atau `1.000.000,50` |
| Riwayat Kas | Permanen, tidak bisa dihapus |
| Blokir Investasi | Tidak bisa tambah jika saldo kosong |

### 🛡️ Keamanan
| Fitur | Keterangan |
|-------|-----------|
| Setup Sekali | `/create` otomatis tutup setelah akun dibuat |
| bcrypt Password | Hash cost-12 |
| CSRF Protection | Token unik per session |
| Rate Limiting | Maks 5 gagal → kunci 15 menit |
| Lockout UI | Form disembunyikan saat dikunci, tampil countdown |
| Session Regenerate | ID baru saat login + tiap 5 menit |
| HttpOnly Cookie | Tidak bisa diakses JS |
| .htaccess | Blokir akses `config/`, `includes/`, `api/` |

### 🔧 Maintenance Mode
| Fitur | Keterangan |
|-------|-----------|
| Mode Default | Judul + pesan + waktu selesai |
| Mode Custom HTML | Full HTML custom |
| Countdown Timer | Hitung mundur otomatis |
| Admin Bypass | Admin tetap bisa lihat normal |
| Riwayat Log | Semua sesi + durasi tercatat |

---

## 🏷️ Tabel Kategori & PnL

| Kategori | Icon | PnL | Jenis PnL |
|----------|------|-----|-----------|
| Simpanan Darurat | 🛡️ | ❌ | — |
| Tabungan | 💰 | ✅ | Keuntungan bunga = **Realized** |
| Saham | 📈 | ✅ | **Unrealized** sampai dijual |
| Reksa Dana | 📦 | ✅ | **Unrealized** sampai dijual |
| Crypto | ₿ | ✅ | **Unrealized** sampai dijual |
| Properti | 🏠 | ✅ | **Unrealized** (estimasi yield) |

---

## 🗃️ Tabel Database

| Tabel | Fungsi |
|-------|--------|
| `investments` | Investasi aktif + kolom `unrealized_pnl` |
| `sell_history` | Riwayat jual + realized PnL |
| `targets` | Target per kategori |
| `cash_ledger` | Riwayat transaksi kas |
| `crypto_cache` | Cache harga CoinGecko |
| `feature_suggestions` | Saran fitur pengguna |
| `maintenance` | Status maintenance (1 baris) |
| `maintenance_log` | Riwayat sesi maintenance |
| `auth_users` | Akun admin (bcrypt) |
| `login_attempts` | Log rate limiting |

---

## ⚙️ Konfigurasi Lanjutan

### Nomor WhatsApp Saran Fitur
`config/config.php`:
```php
define('WA_NUMBER', '62812xxxxxxxx');
```

### Tambah Ticker Crypto
`includes/helpers.php` → `COIN_MAP`:
```php
'SOL' => 'solana',
'AVAX' => 'avalanche-2',
```

### Auto-Refresh Crypto (Cron)
```bash
*/5 * * * * curl -s "https://yourdomain.com/portofolio/api/crypto.php?action=auto"
```

### Session Lifetime
```php
define('SESSION_LIFETIME', 86400); // 24 jam (default)
```

---

## ⚠️ Troubleshooting

| Masalah | Solusi |
|---------|--------|
| 404 / halaman tidak muncul | Pastikan `mod_rewrite` aktif dan `.htaccess` ter-upload |
| Error 500 semua API | Cek urutan `require_once`: `database.php` harus sebelum `config.php` |
| `MySQL server has gone away` | Cold start hosting — retry otomatis sudah ada (4x, ~11 detik) |
| Tidak bisa login | Cek tabel `auth_users`, atau buka `/create` jika belum ada akun |
| Harga crypto kosong | Rate limit CoinGecko — tunggu beberapa menit |
| Lupa password | Hapus row `auth_users` via phpMyAdmin, buka `/create` |

---

## 📞 Dukungan
Gunakan fitur **💡 Saran Fitur** di aplikasi untuk kirim feedback via WhatsApp.

---

*PortoFolio v1.0.0 — Build 2025.03 | Dibuat dengan ❤️*
