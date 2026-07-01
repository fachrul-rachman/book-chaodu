# Chao Du Booking System

## Tujuan

Membangun aplikasi booking Chao Du yang sederhana, cepat digunakan, dan tetap aman untuk operasional nyata.

Aplikasi memiliki tiga jenis pengguna:

- **Customer**: mengisi booking melalui form publik.
- **Admin**: mengatur paket, memeriksa pembayaran, memperbaiki data tertentu, menyetujui atau menolak booking, dan melakukan retry integrasi.
- **Checker**: memindai QR atau memasukkan kode secara manual saat hari acara, lalu melakukan check-in.

## Stack wajib

- Laravel 13
- React
- PostgreSQL
- Cloudflare R2 melalui antarmuka S3
- Google Drive melalui service account file path
- Notion API
- 2OCR
- Email provider yang dikonfigurasi melalui environment
- PWA, mobile-first

Gunakan modular monolith. Jangan menambah microservice, Redis, message broker, atau infrastruktur lain tanpa kebutuhan nyata dan instruksi eksplisit.

## Prinsip implementasi

- KISS: sederhana, efisien, tetapi tidak ringkih.
- Semua aturan penting divalidasi di backend.
- Frontend memberikan validasi dan pengalaman pengguna yang jelas, tetapi bukan sumber kebenaran.
- Operasi alokasi nomor, approval, check-in, dan retry harus aman terhadap request ganda.
- Jangan mengarang aturan bisnis yang belum tertulis.
- Jangan menduplikasi penjelasan antar dokumen. Gunakan referensi ke dokumen sumber.

## Urutan baca

1. `AGENTS.md`
2. `BUSINESS_RULES.md`
3. `USER_FLOWS.md`
4. `DATA_MODEL.md`
5. `SYSTEM_DESIGN.md`
6. `UI_REQUIREMENTS.md`
7. `PLAN.md`

## Scope MVP

Termasuk:

- Form booking publik lima bagian.
- Tiga paket: sembahyang, hio jumbo, dan combo.
- OCR nama Mandarin.
- Preview dan pembuatan kertas doa.
- Reservasi nomor meja dan nomor hio.
- Validasi pembayaran manual.
- QR check-in.
- Google Drive dan Notion per booking approved.
- Email approval dan rejection.
- Retry integrasi yang gagal.
- PWA mobile-first.

Tidak termasuk:

- Multi-event.
- Akun customer.
- Pembayaran otomatis.
- Check-in parsial per peserta.
- Pengelolaan galeri foto di dalam aplikasi.
- Audit log lengkap untuk seluruh perubahan.

## Requirement yang belum tersedia

Codex tidak boleh mengarang nilai berikut:

- Harga awal setiap paket.
- Data rekening tujuan.
- Foto paket.
- Template gambar kertas doa A dan B.
- Koordinat teks pada template.
- Font Mandarin final.
- Kredensial dan nama provider email.
- URL aplikasi produksi.

Sediakan konfigurasi, placeholder, atau seed yang aman tanpa membuat data bisnis palsu.
