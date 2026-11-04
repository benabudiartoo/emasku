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

## Toko pembelian dan potongan jual

- **Toko / tempat pembelian** adalah teks bebas pada barang (maks 120 karakter), terpisah dari "Lokasi simpan". Muncul di detail, kartu daftar, filter "Toko" di Koleksi, kolom Toko di ekspor, dan bagian "Rekap per toko" di ekspor (dikelompokkan menurut tulisan yang sama persis).
- **Harga per gram** = harga perolehan dibagi berat bersih (dibulatkan sekali, 2 desimal, tidak disimpan). Kosong, bukan nol, bila harga atau berat belum ada.
- **Potongan jual (%)** diisi per barang. Perkiraan harga jual:
  - harga setelah potongan = harga beli per gram × (1 − potongan) × berat bersih (sama dengan harga perolehan × (1 − potongan));
  - harga pasar barang = gram emas murni × harga spot terbaru per gram;
  - **perkiraan harga jual = yang lebih besar dari keduanya**, dan layar menyebut yang mana yang dipakai.
- Bila harga pasar tidak bisa diambil (atau kadar barang belum diisi), yang tampil hanya harga setelah potongan dengan keterangan "tanpa pembanding harga pasar"; tidak pernah dianggap nol atau sama dengan harga setelah potongan. Barang tanpa harga beli, berat bersih, atau potongan tidak diperkirakan dan jumlahnya ditulis.
- Kartu **Perkiraan hasil jual** di Ringkasan menjumlah hanya barang yang bisa diperkirakan dan membandingkannya dengan harga perolehan barang yang sama. Ini perkiraan, bukan janji harga toko emas.

## Impor dari Excel

Koleksi, lalu **Impor Excel** (atau kartu di halaman Mulai): unggah `.xlsx`, lihat pratinjau, centang baris, **Simpan**. Belum ada yang tersimpan sebelum Anda menyimpan.

- **Format:** sheet dikenali dari judul kolom **Nama Perhiasan** dan **Berat (Gram)** atau **Harga Beli (Rp)**. Kolom lain yang dipakai: Toko / Lokasi Pembelian, Kategori, Tanggal Beli, Kadar, dan POTONGAN bila ada. Kolom Harga / Gram diabaikan (dihitung otomatis). Sheet "Daftar Perhiasan" dipilih lebih dulu; sheet lain bisa dipilih di pratinjau, satu sheet per impor.
- **Pemetaan:** setiap baris menjadi perhiasan milik sendiri, cara perolehan "beli". Berat diimpor sebagai **berat bersih logam** (batu dianggap tidak diketahui). Kategori ke jenis perhiasan (Kalung / Koye menjadi kalung, yang tidak dikenal menjadi "Lainnya" dan ditandai). Tanggal Beli menjadi "Dimiliki sejak" (pasti).
- **Kadar** boleh `17K`, `750`, atau `75.5%`. Bila ada dua angka yang bertentangan (mis. `750/17K`: 750‰ sama dengan 18K), **karat dipakai** dan baris ditandai "perlu dicek"; teks kadar asli disimpan di catatan barang. Kadar yang tak terbaca membuat barang diimpor tanpa kadar ("Data diperlukan").
- **Duplikat** (nama, tanggal, berat bersih, dan harga sama dengan barang yang sudah ada atau baris sebelumnya) ditandai dan tidak dicentang.
- **Batas dan keamanan:** `.xlsx` saja, maksimal 2 MB dan 500 baris per sheet. Berkas hanya dibaca sebagai teks; rumus Excel tidak dijalankan. Pratinjau disimpan di server selama 30 menit dan terikat pada pengguna yang mengunggah; yang disimpan adalah hasil baca di server, bukan data dari peramban. Penyimpanan semua baris dilakukan dalam satu transaksi.
- Barang hasil impor bisa diubah lewat form biasa.
- **Contoh berkas:** halaman impor punya tombol "Unduh contoh berkas Excel" (`/koleksi/impor/contoh.xlsx`): sheet "Daftar Perhiasan" berisi judul kolom yang dikenali dan tiga baris contoh fiktif, ditambah sheet "Petunjuk". Berkas contoh dibuat otomatis oleh aplikasi sehingga selalu cocok dengan format yang dibaca pembaca impor, dan lolos pratinjau tanpa peringatan.

## Halaman admin ringan

`/admin` menampilkan siapa saja yang memakai aplikasi, **hanya sebagai jumlah**: total pengguna, aktif dan baru dalam 30 hari, total barang dan perhitungan zakat, lalu daftar pengguna (nama, email, tanggal daftar, terakhir masuk, jumlah barang, jumlah zakat). Isi emas, berat, nilai, lokasi, catatan, foto, dan hasil zakat siapa pun tidak pernah ditampilkan.

- Jadikan seseorang admin dari server: `docker compose exec app php artisan user:admin email@contoh.com`. Cabut dengan `--revoke`. Tidak ada jalur lewat web, dan `is_admin` tidak bisa diisi lewat form daftar atau pengaturan.
- Pengguna biasa dan tamu yang membuka `/admin` mendapat halaman "tidak ditemukan". Admin melihat baris "Admin" di Menu lainnya.
- "Terakhir masuk" tercatat sejak versi ini; pengguna lama tampil "Belum pernah masuk" sampai masuk lagi.

## Grafik di Ringkasan

Halaman Ringkasan juga menampilkan **Total berat emas** (berat bersih semua barang milik sendiri, tanpa batu dan tanpa melihat kadar) di samping total emas murni; angkanya bisa lebih besar karena barang di bawah 24 karat. Barang yang beratnya belum bisa dihitung tidak dijumlah dan jumlahnya ditulis.

Empat grafik di bawahnya memakai barang milik sendiri (barang titipan tidak ikut). Grafik emas murni butuh data lengkap termasuk kadar; barang yang belum lengkap tidak pernah dihitung sebagai nol:

- **Emas murni per jenis** dan **per lokasi** (gram). Lokasi: lima terbesar, sisanya digabung sebagai "Lainnya"; barang tanpa lokasi tampil sebagai "Tanpa lokasi".
- **Pertumbuhan total berat emas**: total gram kumulatif (berat bersih semua barang, bukan hanya emas murni) menurut "Dimiliki sejak". Barang tanpa tanggal tidak digambar dan jumlahnya ditulis; jumlah tanggal perkiraan juga ditulis.
- **Nilai sekarang vs harga beli** (Rp): nilai = gram emas murni x harga terbaru (Gold-API.com x kurs Frankfurter, lihat bagian harga otomatis), dihitung di server dengan bcmath. Pembandingnya hanya barang yang "Harga perolehan"-nya diisi, dibanding nilai barang yang sama. Harga perolehan dianggap total untuk satu catatan barang, bukan per keping. Bila tidak ada barang berharga perolehan, hanya nilai total yang tampil.
- Nilai rupiah dimuat setelah halaman terbuka lewat `GET /ringkasan/nilai`. Bila harga gagal diambil atau `GOLDPRICE_ENABLED=false`, grafik rupiah diganti pesan dan grafik gram tetap tampil. Ini harga spot internasional, bukan harga toko emas.
- Saat "Sembunyikan nominal" aktif, isi semua grafik diganti "Nominal disembunyikan" (bentuk batang pun bisa membocorkan angka). Setiap grafik punya tampilan tabel.

## Ekspor Excel dan PDF

Tombol **Unduh Excel** dan **Unduh PDF** ada di Koleksi (mengikuti filter yang aktif), Ringkasan, daftar Zakat, dan detail satu perhitungan zakat.

- Berkas selalu berisi angka lengkap walau "Sembunyikan nominal" aktif di layar, dan hanya memuat data pemilik yang sedang masuk.
- Nilai yang tidak bisa dihitung ditulis "Data diperlukan" (atau "Perlu peninjauan" untuk zakat), tidak pernah nol. Barang titipan ditandai dan tidak ikut total.
- Perhitungan zakat diambil dari angka yang tersimpan, bukan dihitung ulang. PDF dan Excel memuat keterangan bahwa ini alat bantu hitung, bukan fatwa.
- Sel Excel yang berawalan `=`, `+`, `-`, atau `@` diberi tanda kutip di depan agar tidak dibaca sebagai rumus.
- Rute: `/ekspor/koleksi.xlsx|pdf`, `/ekspor/ringkasan.xlsx|pdf`, `/ekspor/zakat.xlsx|pdf`, `/ekspor/zakat/{id}.xlsx|pdf`; dibatasi 20 unduhan per menit per pengguna (`config/export.php`).
- Memakai paket `openspout/openspout` (Excel) dan `dompdf/dompdf` (PDF); keduanya tidak butuh ekstensi PHP tambahan di image Docker.

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
