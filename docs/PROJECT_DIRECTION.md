# Arah Proyek cemilOnTheGo

Sumber kebenaran bersama untuk semua agent yang mengerjakan cemilOnTheGo. Baca file ini dan `AGENTS.md` sebelum mulai. Jangan menulis kredensial atau isi `.env` di file ini.

## 1. Tujuan produk

Aplikasi internal manajemen jastip: toko dan produk jastip, mitra yang diminta membeli barang di toko, lalu invoice ke customer, jadwal pengiriman, dan pembayaran ke mitra.

Ini proyek internal, bukan atas nama perusahaan lain.

## 2. Stack dan dasar

- Laravel 13, Livewire 4 (single-file component berawalan `⚡` di `resources/views`), spatie/laravel-permission, MySQL, Pest 4, Tailwind 4 + Vite.
- Dibuat dari template ALHCore. Spesifikasi template ada di `SPEC_ALHCore_v0.1.md`.
- Kredensial database ada di `.env` lokal (di-ignore git). Jangan menuliskannya di file mana pun atau di commit. Test memakai SQLite in-memory, jadi tidak menyentuh database itu.

Pola ALHCore yang **wajib dipertahankan**:

1. **Otorisasi di level aksi Livewire.** Setiap aksi yang mengubah data (simpan, nonaktifkan) memeriksa permission sendiri dengan `abort_unless(..., 403)`, tidak cukup di halaman induk. Membuka form edit juga diperiksa, karena datanya dimuat ke state komponen.
2. **Middleware permission di level route.** Setiap route yang dilindungi menu memakai `->middleware('permission:modul:fitur-view')`. Menyembunyikan tombol atau menu bukan pengamanan.
3. **Nonaktifkan lewat status, bukan hard delete.** Data yang akan direferensikan modul lain tidak dihapus. Nonaktif memakai kolom `status` (`active` / `inactive`).

Permission diturunkan dari `app/Helpers/MenuHelper.php` dengan pola `modul:fitur-aksi` (`view`, `create`, `edit`, `delete`). Detail konvensi ada di `AGENTS.md`.

## 3. Keputusan yang sudah diambil

### Dasar

- Identifier kode berbahasa Inggris (`Store`), label UI berbahasa Indonesia ("Toko").
- Toko dinonaktifkan lewat status, bukan dihapus, karena akan direferensikan produk, invoice, dan pembayaran mitra.
- Role: `admin` (akses penuh) dan `user` (role netral bawaan, tanpa permission sama sekali; dipakai pendaftar publik, dan pendaftaran publik nonaktif secara default). Role `mitra` tetap di-seed, tetapi kosong dan tidak dipakai.
- **Mitra adalah tabel `partners` sendiri, bukan `User` ber-role `mitra`.** Kolom: `name`, `phone`, city/area, `payment_method` (bank atau e-wallet), `account_name`, `account_number` (cast `encrypted`), `notes`, `status` (aktif/nonaktif), dan `user_id` nullable + unique. `user_id` hanya cadangan untuk login mitra nanti dan **tidak dipakai sekarang**. Permission `partner:partner-view|create|edit|delete`, dengan pola nonaktifkan yang sama seperti Toko. Transaksi pembelian dan pembayaran ke mitra nanti memakai FK `partner_id`.
- Harga beli dari toko dipisah dari harga jual ke customer. Selisihnya adalah margin dan dipakai di invoice.
- Modul Vendor bawaan ALHCore sudah diubah menjadi Toko.

### Disetujui Kang

Toko:

- Menonaktifkan (modal, atau mengubah status aktif menjadi nonaktif di form edit) butuh permission `store:store-delete`. Mengaktifkan kembali cukup `store:store-edit`.
- Jam buka berupa satu atau lebih baris (hari mulai, hari selesai, jam buka, jam tutup), disimpan sebagai JSON.

Produk dan harga:

- Setiap produk milik satu toko. **Tidak ada tabel varian**: setiap varian adalah produk terpisah. Kolom `variant` hanya teks opsional. Produk punya kolom satuan (`unit`) dan status aktif/nonaktif sendiri.
- Harga beli (`buy_price`) dan harga jual (`sell_price`) dipisah, dalam Rupiah utuh. Label UI untuk selisihnya adalah **"Estimasi margin"**; nilainya dihitung, tidak disimpan.
- Harga di produk hanya **harga acuan**. Invoice menyalin nama dan harga ke `invoice_items` (snapshot), supaya invoice lama tidak berubah saat harga produk berubah.
- **Tidak ada harga per customer.** Harga di `invoice_items` boleh diubah per baris saat invoice dibuat.
- Harga beli aktual dicatat pada transaksi pembelian mitra, bukan di produk, karena harga di toko bisa berbeda dari daftar (promo, harga naik). Itu dasar pembayaran ke mitra.
- **Pajak dan biaya layanan tidak masuk Produk.** Keduanya urusan tingkat invoice.
- Menonaktifkan toko tidak menonaktifkan produknya secara berantai. Saat memilih produk untuk pesanan baru, produk milik toko nonaktif difilter.
- Harga beli dan margin sebaiknya disembunyikan dari pengguna non-admin dengan permission tersendiri nanti (relevan bila mitra kelak punya login).
- Tidak memakai tabel `store_products` (pivot) dan tidak menyimpan riwayat harga untuk sekarang. `buy_price_checked_at` cukup untuk melihat seberapa basi harga beli.
- Foto produk: satu foto opsional, dikerjakan sebagai langkah terpisah setelah CRUD Produk selesai dan test hijau. Tipe (JPG, PNG, WebP) dinilai dari isi berkas, ukuran maksimal 2 MB, dimensi maksimal 6000 x 6000 piksel, dan disimpan di disk `public` dengan nama acak.

Mitra:

- **Login mitra ditunda, jadi transaksi pembelian mitra diinput oleh admin**, bukan oleh mitra sendiri. Kolom `partners.user_id` disiapkan untuk login mitra nanti dan belum dipakai.

### Keputusan implementasi yang diambil agent (belum ditinjau eksplisit oleh Kang)

Ini sudah berjalan di kode. Tandai di sini bila ingin diubah.

- Produk unik per (toko, nama, varian). `variant` disimpan sebagai string kosong (bukan NULL) supaya duplikat tanpa varian juga tertangkap.
- Toko sebuah produk tidak bisa diubah setelah dibuat. Produk baru hanya boleh untuk toko aktif.
- `buy_price_checked_at` diperbarui saat produk dibuat, saat harga beli berubah, atau saat admin mencentang "Harga beli sudah dicek ulang".
- Harga diinput sebagai angka bulat 0 sampai 999.999.999. Estimasi margin dalam persen dihitung terhadap harga jual.
- "Daftar Produk" berada di grup menu "Toko".

## 4. Roadmap berurutan

Status: belum mulai / sedang dikerjakan / selesai. Perbarui baris ini saat status berubah, dan isi kolom "Dikerjakan oleh" dengan agent dan branch-nya.

| # | Modul | Status | Dikerjakan oleh (agent/branch) | Catatan |
|---|---|---|---|---|
| 1 | Toko | selesai | Claude Code / `main` | |
| 2 | Produk (milik satu toko) | selesai | Claude Code / `main` | CRUD dan satu foto opsional. |
| 3 | Mitra | belum mulai | - | Tabel `partners` sendiri (lihat bagian 3). Cakupan modul ini hanya data mitra (CRUD `partners`); transaksi pembelian dan pembayaran nanti memakai FK `partner_id`. |
| 4 | Invoice ke customer | belum mulai | - | `invoice_items` menyalin nama dan harga produk; harga per baris boleh diubah. Pajak dan biaya layanan di tingkat invoice. |
| 5 | Jadwal pengiriman | belum mulai | - | |
| 6 | Pembayaran ke mitra | belum mulai | - | Berdasarkan harga beli aktual pada transaksi pembelian mitra (diinput admin), memakai FK `partner_id`. |

## 5. Aturan kerja bersama

- Baca file ini dan `AGENTS.md` sebelum mulai.
- Kerjakan satu modul per agent, di branch terpisah. Jangan mengubah file modul yang sedang dikerjakan agent lain.
- Jangan menghapus atau melonggarkan test tanpa persetujuan eksplisit dari Kang.
- Perubahan skema database (migrasi) harus dicatat di bagian 6 sebelum di-merge.
- Ikuti konvensi kode yang ada. Tanyakan bila ada keputusan desain yang ambigu, jangan menebak.
- Jangan menjalankan `migrate`, `migrate:fresh`, `db:seed`, atau perintah lain yang menulis ke database pada `.env` lokal tanpa izin Kang. Database itu dipakai sungguhan; berikan perintahnya kepada Kang, dan verifikasi dengan `php artisan test`.

## 6. Log keputusan dan perubahan

Setiap agent menambah satu baris di bawah header setiap kali membuat keputusan desain atau mengubah skema. Urutkan dari yang terlama ke terbaru dan jangan menyunting baris lama.

| Tanggal | Agent/branch | Keputusan atau perubahan | Alasan |
|---|---|---|---|
| 2026-09-20 | Kang, dicatat oleh Claude Code / `main` | Mitra memakai tabel `partners` sendiri, bukan `User` ber-role `mitra`. Menggantikan keputusan sebelumnya "mitra memakai role Spatie". | Data rekening dan lokasi mitra tidak cocok disimpan di `users`. Login mitra ditunda, jadi `partners.user_id` (nullable, unique) hanya dicadangkan. |
| 2026-09-20 | Kang, dicatat oleh Claude Code / `main` | `default_role` kembali ke role netral `user` tanpa permission. Role `mitra` tetap di-seed, kosong, dan tidak dipakai. Pendaftaran publik tetap nonaktif. | Role bawaan tidak boleh membawa akses. Role `mitra` dicadangkan untuk login mitra nanti. |
