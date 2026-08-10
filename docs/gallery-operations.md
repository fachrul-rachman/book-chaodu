# Panduan Operasional dan Peluncuran Gallery

Dokumen ini adalah runbook untuk Admin, Team Content, dan operator server. Gallery internal menggantikan Google Drive dan Notion untuk approval baru. Data URL/ID Drive dan Notion lama tetap disimpan sebagai data legacy dan tidak perlu dimigrasikan.

## Batas dan format media

- Foto: maksimal 30 MB per file; JPG/JPEG, PNG, atau WebP.
- Video: maksimal 1 GB per file; MP4 dengan video H.264.
- Audio video boleh tidak ada. Jika ada, codec wajib AAC.
- Caption opsional, maksimal 200 karakter.
- Penghapusan oleh Team Content bersifat permanen. Sistem hanya mempertahankan jejak siapa dan kapan penghapusan dilakukan.
- Urutan awal mengikuti waktu upload dan dapat disusun manual.

Browser mengunggah langsung ke bucket R2 private. Setelah upload, server memeriksa ukuran, extension, MIME, signature, dan integritas foto. Video tetap berstatus `PROCESSING` sampai worker queue menjalankan `ffprobe`; video baru tampil di album setelah lolos pemeriksaan H.264/AAC.

## Persiapan server

Pastikan server memiliki PHP extension `gd` dan `zip`, serta program `ffprobe` dari paket FFmpeg:

```bash
php -m | grep -E 'gd|zip'
ffprobe -version
```

Jika binary berada di lokasi lain, isi `GALLERY_FFPROBE_BINARY`. Batas waktu inspeksi dapat diatur lewat `GALLERY_VIDEO_INSPECTION_TIMEOUT_SECONDS`; default 1.800 detik.

Folder temporary server harus memiliki ruang kosong yang cukup. Inspeksi video membutuhkan ruang hingga ukuran satu video. Pembuatan ZIP dapat membutuhkan kira-kira dua kali total ukuran album selama proses berlangsung.

## Bucket dan CORS Cloudflare R2

Gunakan bucket gallery terpisah pada akun Cloudflare yang sama. Di dashboard Cloudflare, pastikan:

- public access dan custom public domain untuk bucket gallery tidak aktif;
- bucket file private lama juga tetap tidak public;
- API token gallery hanya memiliki akses ke bucket gallery;
- credential `R2_GALLERY_*` berbeda dari credential bucket private lama;
- lifecycle abort multipart upload yang tidak selesai aktif, disarankan setelah tujuh hari.

Atur CORS dengan origin yang benar, bukan wildcard production:

```json
[
  {
    "AllowedOrigins": [
      "https://chaodu.lestarimemorialpark.com",
      "http://localhost:8000",
      "http://localhost:5173"
    ],
    "AllowedMethods": ["PUT"],
    "AllowedHeaders": ["Content-Type"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3600
  }
]
```

Hapus origin development dari konfigurasi production. `ETag` wajib diekspos untuk penyelesaian multipart video.

## Membuat akun Team Content

Pembuatan akun dilakukan sekali oleh operator yang memiliki akses server/database. Gunakan Tinker pada terminal privat:

```bash
php artisan tinker
```

Lalu buat atau perbarui user dengan email resmi. Ganti nilai contoh dan gunakan password sementara yang kuat:

```php
App\Models\User::query()->updateOrCreate(
    ['email' => 'content@example.com'],
    [
        'name' => 'Team Content',
        'password' => Illuminate\Support\Facades\Hash::make('GANTI_PASSWORD_SEMENTARA'),
        'role' => App\Enums\UserRole::ContentTeam,
        'is_active' => true,
        'email_verified_at' => now(),
    ],
);
```

Jangan menaruh password asli di dokumentasi, tiket, atau terminal bersama. Setelah selesai, uji login dan pastikan akun hanya dapat membuka `/content`, bukan area Admin, Checker, atau Printer.

## Cara kerja Team Content

### Upload global

1. Login sebagai Team Content.
2. Buka **Media Global**.
3. Pilih beberapa foto/video atau tarik file ke area upload.
4. Periksa daftar file dan caption opsional.
5. Mulai upload dan tunggu tiap progress selesai.
6. Tunggu status pemrosesan selesai. Foto menunggu thumbnail; video menunggu pemeriksaan codec.
7. Gunakan sembunyikan/tampilkan, ubah caption, susun urutan, atau hapus permanen sesuai kebutuhan.

Media global berstatus siap akan muncul pada semua album booking approved, termasuk booking lama.

### Upload customer

1. Buka **Media Customer**.
2. Cari nomor booking atau nama customer.
3. Cocokkan nomor booking, paket, meja, dan/atau nomor hio.
4. Pilih booking approved yang benar.
5. Upload dan kelola media seperti pada media global.

Media customer hanya muncul pada album booking yang dipilih. Jangan melanjutkan upload bila identitas meja/hio tidak cocok.

### Retry upload

- Bila satu file gagal saat transfer, tekan retry hanya pada file tersebut.
- Bila status `FAILED` menyatakan format video tidak kompatibel, konversi video ke MP4 H.264/AAC lalu upload ulang.
- Bila status terus `PROCESSING`, cek worker queue dan `failed_jobs` sebelum meminta user mengulang upload.
- File multipart yang ditinggalkan browser akan dibersihkan lifecycle R2; jangan menghapus object secara manual saat upload masih aktif.

### Retry download semua

- Customer dapat meminta ZIP lagi ketika status gagal; fingerprint yang sama tidak membuat job ganda.
- Jika isi album berubah, sistem membuat ZIP baru.
- ZIP berlaku sesuai `GALLERY_ARCHIVE_TTL_HOURS`, default 24 jam.
- Cleanup terjadwal tiap jam. Operator juga dapat menjalankan `php artisan gallery:cleanup-archives` secara manual.

## Queue dan scheduler

Gunakan queue database. Worker harus dikelola Supervisor/systemd dan diberi timeout yang lebih panjang dari job ZIP:

```bash
php artisan queue:work --tries=3 --timeout=3600
```

Setelah deploy kode baru, jalankan:

```bash
php artisan queue:restart
```

Cron Laravel wajib aktif setiap menit:

```cron
* * * * * cd /path/ke/project && php artisan schedule:run >> /dev/null 2>&1
```

Verifikasi jadwal dan antrean:

```bash
php artisan schedule:list
php artisan queue:monitor default --max=100
php artisan queue:failed
```

`schedule:list` harus memuat `gallery:cleanup-archives` setiap jam. Jangan menjalankan worker dengan timeout 120 detik karena ZIP besar dapat terputus.

## Monitoring storage

```bash
php artisan storage:gallery-check
php artisan storage:gallery-check --write
```

Perintah `--write` membuat object kecil lalu menghapusnya. Selain itu, pantau melalui dashboard Cloudflare:

- kapasitas bucket dan pertumbuhannya;
- request/error R2;
- multipart upload yang tidak selesai;
- ukuran folder `gallery/archives`;
- public access tetap nonaktif.

Log aplikasi tidak boleh memuat credential R2, presigned upload URL, path rahasia, atau payload customer lengkap.

## Verifikasi booking lama

Ambil sampel booking approved yang sudah ada tanpa mengubah nomor bookingnya, lalu periksa:

1. `/chaodu/{nomor-booking}` dapat dibuka;
2. media global muncul;
3. media milik booking lain tidak dapat dibuka dengan mengganti ID;
4. upload customer khusus ke booking lama berhasil;
5. halaman Admin menampilkan link album;
6. retry email approval mengirim link album, tanpa link Drive/Notion baru.

Booking lama tidak memerlukan row album atau migrasi file. Album dihitung dari booking approved + media global + media booking saat halaman dibuka.

## Backup, deploy, dan rollback

Sebelum deployment:

1. catat commit release;
2. buat backup PostgreSQL dengan `pg_dump` memakai credential production dari secret manager;
3. verifikasi hasil backup dapat dibaca dan simpan di lokasi terpisah;
4. cek status migration dengan `php artisan migrate:status`;
5. pastikan queue tidak sedang memproses ZIP besar sebelum restart worker.

Urutan deploy:

```bash
php artisan down --retry=60
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Rollback aplikasi:

1. aktifkan maintenance mode;
2. kembalikan kode ke commit release sebelumnya;
3. jalankan `composer install --no-dev --optimize-autoloader` dan build asset dari lock file versi tersebut;
4. hanya jalankan `php artisan migrate:rollback --step=N --force` setelah `--pretend` diperiksa dan dipastikan tidak menghapus data yang masih dibutuhkan;
5. bila rollback migration berisiko, pulihkan backup PostgreSQL ke database recovery terpisah terlebih dahulu;
6. bersihkan cache, restart queue, lakukan smoke test, lalu nonaktifkan maintenance mode.

Modul 10 tidak menambah migration. Data Drive/Notion legacy dan nomor booking lama tidak dihapus, sehingga rollback kode tidak memerlukan migrasi media lama.

## Checklist UAT sebelum live

- Admin: approve booking baru, lihat link album, retry email, dan pastikan Drive/Notion tidak dibuat.
- Team Content: upload batch dari telepon, upload video besar, retry file gagal, susun urutan, sembunyikan, dan hapus.
- Customer: buka album melalui ponsel dan desktop, lihat slideshow, geser video, download satu media, dan download ZIP.
- Keamanan: booking A tidak dapat membuka media booking B; role lain tidak dapat membuka endpoint Team Content.
- Performa: album dengan banyak media tetap memakai thumbnail/lazy loading; request ZIP segera kembali dan pekerjaan berjalan di queue.
- Operasional: worker hidup, cron aktif, cleanup berjalan, bucket private, dan backup terbaru tersedia.

Catat browser/perangkat, nomor booking uji, waktu, dan hasil setiap langkah. Peluncuran ditunda jika isolasi booking gagal, bucket public, worker/cron mati, atau backup belum diverifikasi.
