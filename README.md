# Catatan Emas Saya

Aplikasi web pribadi (PWA, berbahasa Indonesia) untuk mencatat emas batangan dan perhiasan beserta berat emas murni yang dapat diperiksa. Setiap pemilik punya akun sendiri dan hanya melihat barang dan fotonya sendiri.

**Stack:** Laravel 12, Inertia + React (TypeScript), Tailwind, PostgreSQL, Docker + Supervisor.

## Fitur Phase 0

- Daftar dan masuk dengan akun sendiri; data terisolasi per pemilik (policy pada halaman, API, dan lampiran).
- Batangan (per keping bernomor seri atau lot identik) dan perhiasan (berat kotor, batu: tanpa/diketahui/belum diketahui, berat bersih, kadar permil/persen/karat).
- Emas murni = berat bersih x kadar, dihitung dengan bcmath (tanpa float). Nilai yang kosong tidak pernah dianggap nol: tampil **Data diperlukan** dan tidak ikut total.
- Lokasi simpan bernama, kepemilikan milik sendiri atau titipan (titipan tidak dihitung), tanggal mulai dimiliki dengan tingkat kepastian.
- Ringkasan dengan rincian per angka, mode **Sembunyikan nominal**, dan onboarding.
- Dapat dipasang di ponsel (PWA); service worker hanya menyimpan aset statis, bukan data pribadi.

Di luar Phase 0: riwayat kejadian, harga dan nilai, zakat, pembayaran, ekspor.

## Menjalankan untuk pengembangan

Prasyarat: PHP 8.2+ (ekstensi `pdo_pgsql`, `bcmath`), Composer, Node 22, PostgreSQL 16.

```bash
cp .env.example .env          # isi DB_PASSWORD, lalu:
composer install
php artisan key:generate
php artisan migrate
npm ci && npm run build        # atau `npm run dev`
php artisan serve
```

Data demo fiktif (hanya non-produksi): `php artisan db:seed --class=DemoSeeder` lalu masuk dengan `demo@contoh.test` / `demo-12345`.

## Harga emas otomatis (opsional)

Di `/zakat/hitung`, tombol **Ambil harga terbaru** mengisi harga emas per gram dalam rupiah. Tanpa kunci API. Isi `GOLDPRICE_ENABLED=false` di `.env` untuk menyembunyikan tombol; harga tetap bisa diisi manual.

- **Sumber:** emas dalam USD per troy ounce dari [Gold-API.com](https://gold-api.com) (gratis, tanpa kunci; dibagi 31,1034768 untuk per gram) dan kurs USD ke IDR dari [Frankfurter](https://frankfurter.dev) (tanpa kunci). Rupiah per gram = USD per gram x kurs, dihitung dengan bcmath.
- **Bukan harga toko emas.** Ini harga spot internasional yang dikonversi; harga Antam atau toko emas bisa berbeda.
- **Syarat Gold-API.com:** pemakaian komersial diizinkan dan atribusi tidak diwajibkan, tetapi IP yang mengirim beberapa permintaan per detik bisa diblokir. Satu cache bersama (60 detik) menjaga pemanggilan paling banyak sekali per menit, dan hasil ditampilkan sebagai "diambil pukul ..." bukan data langsung.
- Bila sumber gagal (dimatikan, batas terlampaui, galat), tombol menampilkan pesan dan harga tetap diisi manual; tidak pernah ada angka nol atau data usang.
- goldprice.org tidak dipakai (syarat penggunaannya melarang pengambilan otomatis) dan GoldPriceZ tidak dipakai (perlu kunci terdaftar).

## Pengujian

Tes memakai PostgreSQL (database terpisah, mis. `catatan_emas_test`, diatur di `phpunit.xml`).

```bash
php artisan test
npx tsc --noEmit
npm run build
```

## Deploy ke VPS (Docker + Caddy)

Aplikasi dan PostgreSQL berjalan di Docker Compose; Caddy di host menangani HTTPS dan meneruskan ke `localhost:8081`.

Prasyarat: VPS Linux dengan akses root, domain yang A record-nya mengarah ke IP VPS, port 80 dan 443 terbuka.

1. Pasang Docker, Caddy, dan git:
   ```bash
   curl -fsSL https://get.docker.com | sh
   apt install -y caddy git
   ```
2. Ambil kode: `git clone https://github.com/benabudiartoo/emasku.git /opt/emasku && cd /opt/emasku`
3. Siapkan `.env`: `cp .env.example .env`, lalu ubah:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://emas.domainanda.id
   DB_PASSWORD=<password kuat>
   SESSION_SECURE_COOKIE=true
   ```
   Buat kunci: `docker compose run --rm app php artisan key:generate --show`, tempel hasilnya ke `APP_KEY=`.
4. Jalankan dan migrasi (migrasi **tidak** otomatis; ulangi setiap update):
   ```bash
   docker compose up -d --build
   docker compose exec app php artisan migrate --force
   ```
5. Caddy, di `/etc/caddy/Caddyfile`, lalu `systemctl reload caddy`:
   ```
   emas.domainanda.id {
       reverse_proxy localhost:8081
   }
   ```
6. Buka `https://emas.domainanda.id` dan daftar akun. Jangan jalankan `DemoSeeder` di server.

Aplikasi memercayai header proxy (`trustProxies`) agar URL dan cookie memakai HTTPS di belakang Caddy.

### Update

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

### Backup

Data ada di dua volume Docker: `pgdata` (database) dan `appstorage` (foto). Contoh cadangan database:

```bash
docker compose exec -T postgres pg_dump -U emas catatan_emas > backup-$(date +%F).sql
```

Salin juga volume `appstorage` secara berkala.

## Struktur singkat

- `app/Services/GoldCalculator.php`: perhitungan berat bersih dan emas murni.
- `app/Policies/`: isolasi pemilik (milik orang lain dijawab 404).
- `resources/js/Pages/`: halaman Inertia; `resources/js/Components/ItemForm.tsx` form barang bersama.
- `docker/`: konfigurasi nginx dan Supervisor di dalam container.
