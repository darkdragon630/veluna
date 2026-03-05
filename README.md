# 📊 PortoFolio — Personal Investment Tracker
**Version:** 1.0.0 | **Build:** 2025.03

---

## 🚀 Cara Setup Pertama Kali

### Requirements
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.4+
- Web Server: Apache 2.4+ dengan `mod_rewrite` aktif (XAMPP / Laragon sudah termasuk)

---

### Langkah 1 — Import Database
```sql
-- Via MySQL CLI:
mysql -u root -p < database.sql

-- Atau via phpMyAdmin:
-- Database → Import → pilih database.sql → Execute
```

---

### Langkah 2 — Konfigurasi Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Username MySQL Anda
define('DB_PASS', '');              // Password MySQL Anda
define('DB_NAME', 'portofolio_db');
define('DB_PORT', 3306);
```

---

### Langkah 3 — Upload ke Server
1. Upload seluruh folder `portofolio/` ke `public_html/` atau `htdocs/`
2. Pastikan `.htaccess` ter-upload (file tersembunyi, aktifkan "Show hidden files")
3. Akses via browser: `https://yourdomain.com/portofolio/`

---

### Langkah 4 — Buat Akun Administrator
Buka URL setup (hanya muncul **sekali** sebelum ada akun):
```
https://yourdomain.com/portofolio/create
```
- Masukkan username & password
- Syarat password: min 8 karakter + 1 huruf kapital + 1 angka
- Setelah submit, halaman `/create` **otomatis ditutup permanen (403)**

> ⚠️ **Penting:** Jangan sampai lupa password. Jika lupa, hapus row di tabel `auth_users`
> di database, lalu buka `/create` lagi untuk buat ulang.

---

### Langkah 5 — Login
Buka: `https://yourdomain.com/portofolio/login.php`  
Masukkan username & password yang sudah dibuat.

---

## 📂 Struktur File

```
portofolio/
├── .htaccess                  → 🛡️ Keamanan, routing, proteksi folder
├── index.php                  → 📊 Overview publik (semua bisa lihat)
├── dashboard.php              → 📋 Dashboard admin (CRUD + kas + target + maintenance)
├── login.php                  → 🔐 Halaman login dengan rate limiting
├── logout.php                 → ⏏ Logout + hapus session
├── create.php                 → 🛡️ Setup akun (otomatis ditutup setelah dipakai)
├── suggestions.php            → 💡 Form saran fitur (publik)
├── targets.php                → ➡️ Redirect ke dashboard#targets
├── maintenance_page.php       → 🔧 Halaman maintenance (auto-include dari index.php)
├── config/
│   ├── config.php             → ⚙️ Session, auth, CSRF, rate limiting, fungsi user
│   └── database.php           → 🗃️ Koneksi MySQL (PDO)
├── includes/
│   ├── helpers.php            → 🔧 Semua fungsi PHP (investasi, kas, maintenance, dll)
│   ├── header.php             → 🧩 HTML header + navbar bersama
│   └── footer.php             → 🧩 HTML footer bersama
├── api/
│   ├── investments.php        → REST API CRUD investasi
│   ├── cash.php               → REST API kas (topup / withdrawal)
│   ├── targets.php            → REST API target per kategori
│   ├── crypto.php             → Proxy CoinGecko + cache DB
│   ├── maintenance.php        → REST API aktifkan / matikan maintenance
│   └── suggestions.php        → API simpan saran fitur
├── assets/
│   ├── style.css              → 🎨 Stylesheet utama (dark theme)
│   └── app.js                 → ⚡ JS shared utilities
├── database.sql               → 🗃️ MySQL schema lengkap
└── README.md                  → 📖 Panduan ini
```

---

## ✨ Fitur Lengkap

### 📊 Investasi & Portfolio
| Fitur | Keterangan |
|-------|-----------|
| 7 Kategori | Darurat, Tabungan, Saham, Reksa Dana, Crypto, Properti |
| CRUD Investasi | Tambah, edit, hapus per item |
| Jual Investasi | Catat harga jual + realized PnL otomatis |
| Reksa Dana | Formula NAB: Unit = Uang Bersih ÷ NAB Beli, support fee % |
| Saham | Sistem lot (1 lot = 100 lembar), harga beli & saat ini |
| Crypto | Harga realtime via CoinGecko, refresh otomatis |
| Properti Tokenisasi | Input yield % / pendapatan bulanan, hitung tahunan |
| Target per Kategori | Progress bar tiap kategori + total target portofolio |

### 💵 Kas & Keuangan
| Fitur | Keterangan |
|-------|-----------|
| Saldo Kas | Top-up manual, auto-potong saat investasi, auto-tambah saat jual |
| Format IDR | Input angka format Indonesia: `1.000.000` atau `1.000.000,50` |
| Riwayat Kas | Semua entri permanen & terkunci (tidak bisa dihapus) |
| Blokir Investasi | Tidak bisa tambah investasi jika saldo kas kosong |

### 🛡️ Keamanan
| Fitur | Keterangan |
|-------|-----------|
| Setup Sekali | Halaman `/create` otomatis tutup setelah akun dibuat |
| bcrypt Password | Hash cost-12, tidak bisa di-reverse |
| CSRF Protection | Token unik per session di setiap form login |
| Rate Limiting | Maks 5 percobaan gagal → dikunci 15 menit |
| Session Regenerate | ID session baru saat login + tiap 5 menit |
| Session Fixation | Dicegah dengan `session_regenerate_id(true)` |
| HttpOnly Cookie | Tidak bisa diakses JavaScript |
| Redirect Whitelist | Parameter `?redirect=` divalidasi ketat |
| .htaccess | Blokir akses langsung ke `config/`, `includes/`, `api/` |

### 🔧 Maintenance Mode
| Fitur | Keterangan |
|-------|-----------|
| Aktifkan dari Dashboard | Tombol 🔧 di header dashboard |
| Mode Default | Judul + pesan + waktu selesai |
| Mode Custom HTML | Input full HTML page sendiri |
| Countdown Timer | Hitung mundur otomatis jika waktu diset |
| Auto-Refresh | Halaman reload sendiri saat maintenance selesai |
| Polling API | Cek status tiap 30 detik, redirect jika sudah normal |
| Admin Bypass | Admin tetap bisa lihat overview saat maintenance aktif |
| Riwayat Log | Tabel log semua sesi maintenance + durasi |

### 🌐 Halaman Publik
| Halaman | Keterangan |
|---------|-----------|
| Overview (`/`) | Semua bisa lihat tanpa login |
| Saran (`/suggestions`) | Form feedback publik → WhatsApp |
| Refresh Crypto | Bisa diakses tanpa login (data publik) |

---

## 🏷️ Kategori Investasi

| Kategori | Icon | PnL | Catatan |
|----------|------|-----|---------|
| Simpanan Darurat | 🛡️ | ❌ | Tidak ada PnL |
| Tabungan | 💰 | ✅ | Bisa beda nilai saat ini |
| Saham | 📈 | ✅ | Sistem lot 100 lembar |
| Reksa Dana | 📦 | ✅ | Formula NAB + fee |
| Crypto | ₿ | ✅ | Harga realtime |
| Properti | 🏠 | ✅ | Yield tahunan |

---

## ⚙️ Konfigurasi Lanjutan

### Ganti Nomor WhatsApp (untuk Saran Fitur)
Edit `config/config.php`:
```php
define('WA_NUMBER', '62812xxxxxxxx'); // Format internasional tanpa +
```

### Tambah Ticker Crypto
Edit `includes/helpers.php` di bagian `COIN_MAP`:
```php
'MYTICKER' => 'coingecko-coin-id',
// Contoh: 'SOL' => 'solana'
```

### Auto-Refresh Crypto (Cron Job)
```bash
*/5 * * * * curl -s "https://yourdomain.com/portofolio/api/crypto.php?action=auto" > /dev/null
```

### Login Session
Default: session hilang saat browser ditutup (cookie `lifetime=0`).  
Untuk ubah ke persistent session, edit `config/config.php`:
```php
define('SESSION_LIFETIME', 86400); // 24 jam
```

---

## 🗃️ Tabel Database

| Tabel | Fungsi |
|-------|--------|
| `investments` | Data semua investasi aktif |
| `sell_history` | Riwayat penjualan + realized PnL |
| `targets` | Target per kategori + total |
| `cash_ledger` | Riwayat semua transaksi kas |
| `crypto_cache` | Cache harga crypto dari CoinGecko |
| `feature_suggestions` | Saran fitur dari pengguna |
| `maintenance` | Status maintenance aktif (1 baris) |
| `maintenance_log` | Riwayat semua sesi maintenance |
| `auth_users` | Akun admin (password bcrypt) |
| `login_attempts` | Log percobaan login untuk rate limiting |

---

## ⚠️ Troubleshooting

**Halaman tidak bisa diakses / 404**
- Pastikan `mod_rewrite` aktif di Apache
- Pastikan `.htaccess` ter-upload (cek "show hidden files" di FTP)

**Tidak bisa login**
- Cek apakah tabel `auth_users` sudah ada di database
- Jika belum ada akun, buka `/create` untuk setup

**Harga crypto tidak muncul**
- CoinGecko API gratis punya rate limit — tunggu beberapa menit
- Cek koneksi server ke internet (firewall?)

**Session cepat expired**
- Edit `SESSION_LIFETIME` di `config/config.php`

**Lupa password**
- Hapus row di tabel `auth_users` via phpMyAdmin
- Buka `/create` untuk buat akun baru

---

## 📞 Dukungan
Gunakan fitur **💡 Saran Fitur** di dalam aplikasi untuk mengirim feedback via WhatsApp.

---

*PortoFolio v1.0.0 — Build 2025.03 | Dibuat dengan ❤️*
