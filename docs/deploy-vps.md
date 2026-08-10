# Panduan Deploy ke VPS

Dokumen ini khusus untuk menyiapkan aplikasi ini di VPS.
Fokusnya adalah hal yang perlu disiapkan, urutan kerja, dan hal yang wajib dicek sebelum dipakai.

## Tujuan

Supaya aplikasi bisa dipakai dengan aman untuk:

- form booking customer;
- halaman admin;
- proses approval;
- pembuatan file kertas;
- kirim email;
- simpan file;
- album foto/video internal dan sambungan OCR.

## Yang perlu disiapkan

### Server

Minimal siapkan:

- VPS Linux;
- domain utama;
- HTTPS aktif;
- PostgreSQL;
- PHP 8.3;
- Composer;
- Node.js dan npm;
- Nginx atau Apache;
- process manager untuk proses yang harus terus hidup.

### Akses luar yang wajib

Aplikasi ini perlu bisa terhubung ke:

- Cloudflare R2;
- bucket Cloudflare R2 khusus gallery;
- layanan OCR;
- SMTP Brevo.

Kalau salah satu akses ini diblokir firewall, beberapa bagian aplikasi tidak akan jalan normal.

## Data penting yang harus ada

Sebelum deploy, siapkan:

- data PostgreSQL;
- data SMTP Brevo;
- data Cloudflare R2;
- credential R2 gallery yang hanya memiliki akses ke bucket gallery;
- data OCR;
- data CAPTCHA jika memang mau dinyalakan;
- akun admin awal;
- akun petugas awal.

## Hal yang perlu diperhatikan

### 1. Database harus PostgreSQL

Jangan pakai database lain untuk server utama.

### 2. File upload jangan disimpan sembarangan

File penting seperti:

- bukti transfer;
- foto nama Mandarin;
- file kertas;
- QR;

harus masuk ke penyimpanan yang sudah disiapkan dengan benar.

### 3. Email harus benar-benar dites

Aplikasi ini mengirim email approval.
Jadi SMTP Brevo harus dites, bukan hanya diisi.

### 4. Proses latar belakang harus jalan terus

Ada proses yang tidak boleh macet, seperti:

- antrean kerja;
- pelepasan nomor pembayaran yang sudah lewat waktu.

Kalau proses ini mati, alur booking bisa terganggu.

### 5. Bucket gallery harus private

Public access dan custom public domain bucket gallery harus nonaktif. Media hanya dikirim melalui route aplikasi yang memeriksa hubungan booking.

### 6. HTTPS wajib

Karena ada:

- login;
- upload bukti transfer;
- email customer;
- nomor pembayaran;

jadi domain produksi wajib pakai HTTPS.

## Urutan kerja deploy

### 1. Salin project ke server

Bisa lewat git clone atau cara lain yang rapi.

### 2. Buat file pengaturan server

Mulai dari `.env.example`, lalu isi nilai produksi.

Yang paling penting untuk diisi:

- `APP_NAME`
- `APP_ENV`
- `APP_DEBUG=false`
- `APP_URL`
- `DB_*`
- `QUEUE_CONNECTION=database`
- `MAIL_*`
- `R2_*`
- `R2_GALLERY_*`
- `GALLERY_FFPROBE_BINARY`
- `GALLERY_VIDEO_INSPECTION_TIMEOUT_SECONDS`
- `TWO_OCR_API_KEY`
- `TWO_OCR_BASE_URL`
- `CAPTCHA_*` jika dipakai
- `DEFAULT_ADMIN_*`
- `DEFAULT_CHECKER_*`

### 3. Install kebutuhan server

Jalankan:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 4. Buat kunci aplikasi

Kalau belum ada:

```bash
php artisan key:generate
```

### 5. Jalankan database

```bash
php artisan migrate --force
```

### 6. Pastikan folder yang dibutuhkan bisa ditulis

Biasanya yang perlu aman:

- `storage/`
- `bootstrap/cache/`

### 7. Jalankan antrean kerja

Harus ada proses yang terus hidup untuk antrean.

Contoh:

```bash
php artisan queue:work --tries=3 --timeout=3600
```

Jangan dijalankan manual satu kali lalu ditinggal.
Harus dipasang sebagai proses tetap.

### 8. Pasang jadwal rutin

Ada perintah yang perlu dijalankan rutin untuk melepas nomor pembayaran yang lewat waktu.

Pasang cron:

```bash
* * * * * cd /path-ke-project && php artisan schedule:run >> /dev/null 2>&1
```

Lalu pastikan ada jadwal untuk:

- melepas nomor pembayaran yang sudah habis waktunya.
- membersihkan ZIP gallery yang sudah kedaluwarsa.

Kalau belum dibuat lewat scheduler, minimal jalankan manual dengan cron:

```bash
php artisan virtual-accounts:release-expired
```

### 9. Arahkan web server ke folder publik

Root web harus ke folder:

- `public/`

Bukan ke root project.

## Daftar cek sebelum live

### Cek dasar

- domain bisa dibuka;
- HTTPS aktif;
- halaman utama tampil;
- login admin berhasil.

### Cek booking

- customer bisa isi form;
- validasi muncul dengan benar;
- nomor pembayaran muncul;
- nomor pembayaran bisa disalin;
- nominal bayar bisa disalin;
- bukti transfer bisa diunggah.

### Cek admin

- daftar booking tampil;
- detail booking tampil;
- nominal transfer tampil benar;
- file kertas bisa dibuka;
- approve dan reject berjalan.

### Cek file

- bukti transfer tersimpan;
- file kertas tersimpan;
- QR tersimpan.

### Cek sambungan luar

- OCR bisa baca foto;
- koneksi baca/tulis/hapus bucket gallery berhasil;
- email approval terkirim.

### Cek proses setelah approve

Setelah booking di-approve, pastikan:

- QR jadi;
- link album internal tampil;
- email approval masuk.

## Perintah bantu yang penting

### Cek koneksi R2

```bash
php artisan storage:r2-check
php artisan storage:gallery-check --write
```

### Ulang buat file kertas yang gagal

```bash
php artisan prayer-papers:retry
```

Atau untuk satu booking:

```bash
php artisan prayer-papers:retry NOMOR_BOOKING
```

### Ulang proses approval yang gagal

```bash
php artisan approval-integrations:retry NOMOR_BOOKING
```

Atau satu bagian saja:

```bash
php artisan approval-integrations:retry NOMOR_BOOKING qr
php artisan approval-integrations:retry NOMOR_BOOKING approval_email
```

### Lepas nomor pembayaran yang habis waktu

```bash
php artisan virtual-accounts:release-expired
```

## Hal yang jangan dilakukan

- jangan biarkan `APP_DEBUG=true` di produksi;
- jangan simpan file Google di folder publik;
- jangan lupa jalankan antrean kerja;
- jangan lupa HTTPS;
- jangan deploy tanpa tes email;
- jangan anggap file upload aman kalau R2 belum benar-benar dites.

## Saran urutan go live

Urutan paling aman:

1. siapkan VPS;
2. siapkan database;
3. isi semua pengaturan server;
4. tes R2;
5. tes login admin;
6. tes 1 booking penuh dari awal sampai approve;
7. tes email approval;
8. tes file kertas;
9. baru buka ke user.

## Ringkasan singkat

Kalau disingkat, yang paling penting saat deploy aplikasi ini adalah:

- PostgreSQL harus benar;
- antrean kerja harus hidup;
- R2 harus benar;
- gallery R2, OCR, dan Brevo harus dites;
- HTTPS wajib;
- booking penuh harus dites dari customer sampai approval.
