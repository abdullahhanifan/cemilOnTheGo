# EXTRACTION LOG — ALH Core Tahap 1 (prune, generikkan, boot)

Dikerjakan 19 September 2026 dari `PROMPT_ALHCore_Tahap1.md` dan `SPEC_ALHCore_v0.1.md` (Rev 1).
File ini dan `PROMPT_ALHCore_Tahap1.md` dihapus manusia sebelum tag `v0.1.0`.

Status git: tidak ada perintah git yang mengubah state dijalankan (hanya `git log`, `git status`, `git check-ignore`). `git log` masih "no commits".

## 1. Dipertahankan / ditulis ulang / dihapus (ringkas per folder)

Total file di repo sekarang: 177 (di luar `vendor`, `node_modules`, `public/build`, `.env`, dan file log ini) = **13 baru + 54 ditulis ulang/diubah substansial + 110 dipertahankan** (tak berubah, atau hanya penggantian token warna/font).
**Dihapus: 477 file** yang terhitung pasti, ditambah isi `.github/` yang sudah terhapus sebelum sempat dihitung (perkiraan ±26 file: `workflows/deploy.yml` dan salinan skill Boost).

| Folder | Dihapus | Ditulis ulang / diubah | Baru | Catatan |
|---|---|---|---|---|
| root | 19 + `.github/` | 10 | 0 | Dihapus: `bun.lock`, `_ide_helper.php`, `tailadmin-laravel.png`, 4 skrip shell, `nixpacks.toml`, `.env.testing`, `docs/` (9), `storage/framework/lsp-*.php`. Diubah: `.env.example`, `.gitignore`, `README.md`, `AGENTS.md`, `composer.json`, `phpunit.xml`, `boost.json`, dua lockfile. |
| `app/` | 137 | 8 | 1 | Dihapus: Console (5), Enums, Api + SsoController, 3 middleware `Check*Api*`, Jobs (4), 20 model, Services (12), `LoginRequest`, 86 class View Component. Baru: `EnsureRegistrationEnabled`. |
| `bootstrap/` | 0 | 1 | 0 | `app.php`. |
| `config/` | 7 | 3 | 1 | Baru: `alh.php`. Diubah: `app` (nama), `database` (default), `services` (ditulis ulang). |
| `database/` | 40 | 3 | 1 | 27 migration, 8 factory, 5 seeder dihapus. Baru: `AdminUserSeeder`. |
| `routes/` | 1 | 2 | 0 | `api.php` dihapus. |
| `public/` | 61 | 1 | 2 | Favicon diganti placeholder. Baru: `images/brand/logo.svg`, `logo-icon.svg`. |
| `resources/` | 191 | 21 | 2 | 52 file JS (chart, peta, kalender, popover, tooltip) dan 139 view dihapus. Baru: `components/common/auth-panel`, `components/form/captcha`. |
| `tests/` | 21 | 5 | 6 | Baru: `AdminUserSeederTest`, `ErrorHandlingTest`, `CharacterizationTest`, `RegistrationTest`, `SmokeTest`, `TrustProxiesTest`. `RbacAndSsoTest` menjadi `RbacTest`. |
| `.agents/`, `storage/` (kerangka) | 1 | 0 | 0 | `.agents/` generik (skill Boost), dipertahankan utuh. |

Dependensi: Composer dihapus `dedoc/scramble`, `laravel/sanctum`, `laravel/socialite`, `spatie/laravel-analytics`, dev `barryvdh/laravel-ide-helper`, `laravel/sail`. NPM dihapus `apexcharts`, `@fullcalendar/*` (5), `jsvectormap`, `leaflet`, `maplibre-gl`, `prismjs`, `quill`, `swiper`, ditambah `alpinejs` dan `@floating-ui/dom` (lihat keputusan 15–16).

## 2. Keputusan yang saya ambil

1. **Backup tidak terdeteksi.** Folder `ALHCore-undip-backup` tidak ada di sebelah `ALHCore` maupun di `~/Documents`, `~/Desktop`, `~/Downloads` (kedalaman 5). Saya lanjut berdasarkan konfirmasi pengguna ("sudah saya backup").
2. **`TrustProxies`.** Di UNDIP kelas `App\Http\Middleware\TrustProxies` tidak terdaftar; yang aktif adalah `trustProxies(at: '*')` di `bootstrap/app.php`, jadi keduanya mempercayai `*`. Sekarang kelas didaftarkan lewat `$middleware->replace(...)` dan membaca `config('alh.trusted_proxies')` (env `TRUSTED_PROXIES`, string koma-terpisah, kosong = tidak ada yang dipercaya; `*` tetap bisa dipilih eksplisit). Kunci `trusted_proxies` ditambahkan di `config/alh.php` (di luar bentuk §7) karena `env()` di `bootstrap/app.php` tidak aman terhadap `config:cache`. Diuji di `TrustProxiesTest`.
3. **`config/database.php`:** `'default' => env('DB_CONNECTION')` tanpa fallback diubah ke `env('DB_CONNECTION', 'sqlite')` (default standar Laravel). Tanpa itu, `composer install` di clone tanpa `.env` crash saat `package:discover` ("Undefined array key driver"), sehingga verifikasi §9.1 mustahil.
4. **Override Captcha rusak, diperbaiki.** `App\Overrides\Captcha::text()` memanggil `$font->valign()`, yang tidak ada di Intervention Image 4.1.5 (terkunci di `composer.lock`), sehingga **setiap request gambar captcha berakhir 500**. mews/captcha 3.5.0 sudah menangani cast `int` (PHP 8.1+) dan Intervention 3/4 di kelas induknya, jadi `text()` dihapus. Kelas dan binding tetap ada (whitelist). Dijaga oleh tes "the captcha image renders" (terbukti gagal bila kode lama dikembalikan).
5. **Captcha di tiga tempat** lewat komponen baru `<x-form.captcha>` (signin, signup, forgot-password). Tersembunyi dan aturan validasinya kosong bila `CAPTCHA_DISABLE=true`. Kode captcha sekali pakai (`Cache::pull`), jadi `⚡signin` mengosongkan field dan mengirim event `captcha-refresh` setelah **setiap** percobaan (blok `finally`). **Koreksi (20 September 2026, laporan pengguna "captcha salah terus"):** versi pertama memakai `wire:ignore` plus `src` bertimestamp di HTML server. Itu keliru: Livewire mem-parse HTML tiap render ulang ke elemen lepas dan browser langsung memuat `<img src>` di dalamnya, jadi setiap percobaan menghasilkan **dua** request `/captcha/flat` (satu dari HTML, satu dari refresh JS). Keduanya menerbitkan kode baru dan menimpa kode di sesi, sehingga gambar yang tampil bisa tidak cocok dengan kode tersimpan. Sekarang HTML server **tidak memuat URL captcha sama sekali**: `src` awal berupa data-URI 1×1 dan gambar hanya diisi dari JS (sekali di `x-init`, sekali per event), dengan penjaga `busy` agar tidak ada dua request tumpang tindih. Diverifikasi di Chrome headless: satu request per kejadian (dulu dua), termasuk saat muat halaman pertama.
6. **`⚡signin` menampilkan `session('status')`/`session('success')`** (pesan setelah reset password dan registrasi); sebelumnya tidak tampil di layout fullscreen.
7. **Halaman error.** Isi `pages/errors/error-{404,500,503}` dipindah ke `errors/{404,500,503}`; `pages/errors/*` dan `⚡not-found` dihapus; callback exception di `bootstrap/app.php` memakai `errors.500`. Fallback route hanya `abort(404)`. Tiap halaman error mengisi `$title` sendiri (Laravel merender tanpa `$title`).
8. **Token warna.** `app.css` ditulis ulang (1357 → 676 baris): token `accent-*` dan `neutral-*` terpisah dan tidak saling merujuk; skala `brand-*` diturunkan hanya dari token accent (`color-mix`); skala `gray` dikembalikan ke skala TailAdmin default (sebelumnya nilai Figma UNDIP) dan override `slate` dihapus (memuat `#edf1f6`, `#949cab`); font satu token `--font-sans` (system stack, Google Fonts/Poppins dihapus). Skala semantik `success/error/warning/orange/blue-light` **tidak diubah**. CSS pihak ketiga (ApexCharts, FullCalendar, Swiper, Quill, Prism, jsvectormap, simplebar) dibuang; CSS flatpickr dan Select2 dipertahankan dengan hex UNDIP diganti token.
9. **Pemetaan hex → token di Blade:** `#000d90`→`accent-primary`, `#000352`→`accent-primary-hover` (gradient panel: `accent-secondary`), `#edf1f6/#c9ced5/#949cab/#ced4da`→`neutral-border`, `#616e84/#4c5a73/#5b6880`→`neutral-label`, `#2f3847`→`neutral-heading`, `#f5f9fd`→`neutral-bg`, sisanya ke utilitas `gray-*`/`success-*`. Tiga fill SVG (`#D2272A`, `#6A737B`) menjadi `currentColor`; dua hex generik lain (`#1E2634`, `#f8fafc`) juga diganti utilitas.
10. **Guard role super admin hardcode** (`'Super Admin'` di `⚡roles` dan `⚡role-form-modal`, untuk melindungi role dari edit/hapus) diganti `config('alh.super_admin_role')`; tanpa ini role `admin` baru bisa dihapus dari UI.
11. **Form user:** email wajib (kolom `NOT NULL`), ekspor CSV tanpa kolom "SSO Provider", modal detail tanpa "Auth Method", label "Username" (bukan "NIM / NIP").
12. **Config tes.** `.env.testing` dihapus; `phpunit.xml` berisi `APP_KEY` baru, `CAPTCHA_DISABLE=true`, sqlite in-memory, dan `APP_DEBUG=true` (paritas dengan `.env.testing` lama; setelah keputusan 22 tes 403 tidak lagi bergantung padanya). Tanpa `.env`, phpdotenv mencoba membaca `.env` yang tidak ada dan PHPUnit 12 melaporkan 1 warning per tes (52 warning), maka `tests/TestCase::createApplication()` memuat `.env.example` sebagai sumber default. Efek samping yang disengaja: tes tidak bergantung pada `.env` lokal.
13. **Tes di luar daftar prompt:** `AdminUserSeederTest` (guard produksi: kosong/placeholder/pendek ditolak), `TrustProxiesTest`, empat tes captcha di `SignInTest`, dan tes karakterisasi ke-3 (otorisasi aksi modal, pertanyaan SPEC §8.4).
14. **View Component.** Dari 91 class, hanya 5 yang terjangkau (Alert, Notification, SpinnerFour, UserDropdown, ChevronDown) dan dipertahankan apa adanya (prompt hanya "boleh" menghapus). `common/common-grid-shape` dihapus karena bergantung pada `images/shape/grid-01.svg` (dihapus); `common/preloader` tak terjangkau (loading modal di layout kini memakai `spinner-four`).
15. **`alpinejs` dihapus:** tidak diimpor dari `resources/**`; Alpine datang dari Livewire, dan `Alpine.store('sidebar')` didefinisikan inline di layout lewat `alpine:init`.
16. **`popover.js`, `tooltip.js`, `@floating-ui/dom` dihapus:** tidak ada `data-popover`/`data-tooltip` di view yang tersisa. `window.createPopper`, flatpickr, komponen Alpine `dropdown`, dan auto-open date picker tetap (diminta prompt), walau kode tersisa belum memakainya.
17. **Sidebar:** cabang `pro`/`layout` dan badge `pro` dibuang; cabang `subItems` dipertahankan karena masih didukung `MenuHelper`.
18. **`AGENTS.md`:** blok `<project-conventions>` ditulis ulang generik (identitas UNDIP, aturan Cloudflare Rocket Loader, `ref-tailadmin`, contoh helpdesk, `deploy.sh` dibuang; bagian "Current State" yang usang diperbarui); paket `sanctum`, `sail`, `alpinejs` dihapus dari daftar Boost. Versi final tetap Tahap 3.
19. **`boost.json`, `opencode.json`, `.mcp.json`, `.agents/`:** generik, dipertahankan. Agent `copilot` dihapus dari `boost.json` (permintaan pengguna) supaya `boost:update` tidak membuat ulang `.github/skills`.
20. **`.env` dibuat atas permintaan pengguna** (MySQL milik pengguna, kredensial diisi pengguna). File ini git-ignored dan sengaja ada, sehingga butir §9.9 "tidak ada `.env*` selain `.env.example`" hanya terpenuhi setelah `.env` dihapus manusia. Di dalamnya `ADMIN_PASSWORD` acak, bukan placeholder.
21. `LoginRequest` dan `StorageService` (bersyarat) tidak dirujuk kode yang tersisa, jadi dihapus. Baris `ref-tailadmin` dihapus dari `.gitignore`. Favicon placeholder dibuat dengan GD (kotak biru "ALH").
22. **Callback exception di `bootstrap/app.php` dihapus** (permintaan pengguna, temuan 2 sebelumnya). Callback `render(Throwable)` menelan semua exception saat `APP_DEBUG=false`, bukan hanya `HttpException`: `abort(403/419/429)`, tamu yang membuka `/dashboard` (`AuthenticationException`), dan form signup tidak valid (`ValidationException`) semuanya menjadi HTTP 500. Laravel sudah merender `errors/{404,500,503}.blade.php` sendiri, jadi callback dibuang tanpa pengganti. Dijaga oleh `ErrorHandlingTest` (produksi: redirect ke signin, redirect balik dengan error validasi, 403/419/429 tetap statusnya, 404 dan 500 memakai halaman generik).
23. **Dependensi dinaikkan** (permintaan pengguna, temuan 4 sebelumnya): `composer update livewire/livewire guzzlehttp/guzzle league/commonmark --with-dependencies`. Livewire 4.3.3 → 4.4.5, guzzle 7.15.1 → 7.15.5, league/commonmark 2.8.2 → 2.10.1, plus 17 paket pendukung (patch symfony dan lain-lain); 20 paket berubah, tidak ada yang ditambah/dihapus. `laravel/framework`, Spatie, mews/captcha, Pest, Intervention Image tidak berubah dan constraint di `composer.json` tidak diubah. `composer audit` kini: no advisories.
24. **TailAdmin: versi free** (jawaban pengguna atas pertanyaan 5 sebelumnya). Belum ditulis di `SPEC` (dokumen milik manusia) maupun `THIRD_PARTY_NOTICES.md` (Tahap 3).
25. **Kedaluwarsa kode captcha 60 → 300 detik.** mews/captcha hanya menerapkan blok konfigurasi bernama (`flat`) dan tidak menggabungkannya dengan `default`, sehingga `expire` untuk gambar `flat` jatuh ke bawaan kelas (60 detik) meski `default` diubah. Kode yang benar ditolak bila diketik lebih dari semenit setelah gambar muncul (gambar tidak auto-refresh). `expire => 300` dipasang di blok `flat` (dan `default`). Diuji: diterima di 290 detik, ditolak di 310 detik; tes `a captcha code stays valid for a few minutes`.

## 3. Temuan (tidak dikerjakan)

1. **Login tidak mengecek `status` dan tidak ada throttling** (sesuai prompt: ditunda ke Tahap 2). Tes karakterisasi `todo`: user `inactive` berhasil login.
2. ~~403/419/429 dirender sebagai HTTP 500 saat `APP_DEBUG=false`~~ **Diperbaiki** (keputusan 22). Cakupan sebenarnya lebih luas dari temuan awal: tamu di `/dashboard` dan validasi signup yang gagal juga menjadi 500.
3. **Aksi komponen Livewire tidak mengotorisasi diri sendiri.** Hanya `mount()` halaman yang mengecek permission. Tes karakterisasi `todo`: user role `user` yang memanggil `saveUser` di `rbac.user-form-modal` berhasil membuat user (status 200). `exportCsv` di `⚡users` juga tanpa cek. Tahap 2.
4. ~~`composer audit`: 13 advisori~~ **Diperbaiki** (keputusan 23): sekarang no advisories.
5. **Class View Component memakai namespace huruf kecil** (`App\View\Components\ui\...`) sementara Laravel mencari `Ui\...`. Di filesystem case-insensitive (macOS) class ditemukan; di Linux jatuh ke komponen anonim, yang tetap jalan karena view punya `@props`. Belum diuji di Linux.
6. Dropdown user (`Edit profile`, `Account settings`, `Support`) menautkan ke `#` (sisa demo TailAdmin).
7. Layout `app` dan `fullscreen-layout` menduplikasi ±80 baris skrip store Alpine; loading modal di `app.blade.php` tidak pernah dipicu (skrip pemicunya dikomentari).
8. Skala warna semantik (`success`, `error`, `warning`, `orange`, `blue-light`) masih nilai Figma UNDIP, bukan hex brand. Ganti bersama token bila perlu.
9. `.editorconfig` mengatur indent 2 untuk PHP/Blade, sedangkan Pint (preset Laravel) memakai 4.
10. Kredit dan lisensi TailAdmin tidak lagi disebut di README; `THIRD_PARTY_NOTICES.md` ada di Tahap 3 (versi free/pro perlu dipastikan, SPEC §8.3).
11. `npm ci` di npm 11.17 memberi peringatan `allow-scripts` untuk `esbuild`, `@tailwindcss/oxide`, `fsevents`; build tetap hijau.
12. Halaman auth kini diuji di Chrome headless (Playwright, di luar repo): hitung request captcha, tiga percobaan gagal lalu login berhasil, dan kode diterima 70 detik setelah gambar. Signup dan forgot-password belum dijalankan di browser (markup sama, dijaga tes Pest).

## 4. Hasil verifikasi §9

1. `composer validate` (juga `--strict`) valid; `composer install` bersih dari nol (hapus `vendor`) exit 0 termasuk `package:discover`; `npm ci && npm run build` hijau (126 modul).
2. Database kosong → `migrate:fresh --seed` berhasil pada sqlite sementara (5 migration, 12 permission, admin + role `admin` 12 permission, role `user` 1 permission). Ulang dengan `migrate --seed` pada MySQL milik pengguna (kosong, 0 tabel sebelumnya): 13 tabel, hasil sama. Guard produksi diuji nyata: password placeholder dan `short1` ditolak dengan `RuntimeException`.
3. `php artisan test`: **73 lulus, 2 todo, 0 gagal** (225 assertion; termasuk tes modul contoh (kini `StorePageTest`) dan tes captcha baru). `vendor/bin/pint --test`: passed.
4. Cek rujukan view (skrip di luar repo): 46 view dijangkau, **0 rujukan hilang**, 0 komponen/class tak terjangkau. Ikon `MenuHelper` (`dashboard`, `user-management`, `role-management`) ada. Route `route('...')` yang dipakai semuanya terdefinisi; `route:cache` berhasil.
5. Smoke test (`SmokeTest`): tamu membuka `/signin` dan `/forgot-password`; `/dashboard` tamu → `/signin`; admin membuka `/dashboard`, `/rbac/users`, `/rbac/roles` (200); URL tak dikenal → halaman 404 generik; `/health` 200. Ditambah HTTP nyata (`artisan serve`): `/signup` 404, `/captcha/flat` 200 `image/jpeg` 180×50 dan berbeda tiap request, dan login E2E lewat protokol Livewire ke MySQL: salah password → error, benar → redirect `/dashboard`, tiga halaman terproteksi 200, sidebar dua grup, logout → `/signin` (12/12 lulus, captcha dimatikan hanya untuk proses itu). Login E2E dan `/captcha/flat` diulang setelah upgrade Livewire 4.4.5: hasil sama.
6. Tes karakterisasi: lihat bagian 5.
7. Grep bersih: nol hasil, kecuali sisa yang dijelaskan di bagian 6.
8. Hex UNDIP di `resources/`: nol (`#000d90`, `#000352`, `#0b32d5`, `#f0f2ff`, `#edf1f6`, `#616e84`, `#2f3847`, `#949cab`, `#c9ced5`, `#4c5a73`, `#f5f9fd`, `#d3af35`, dan `rgba(0,13,144,…)`). Hex tersisa hanya di `resources/css/app.css` (definisi token dan skala semantik/gray) dan nol di Blade/JS. Placeholder: accent `#2563eb`/`#1d4ed8`/`#1e40af`; neutral `#f9fafb`/`#ffffff`/`#e5e7eb`/`#6b7280`/`#111827`.
9. Tidak ada secret di file repo (pencarian pola kunci/token/password kosong) dan tidak ada `/Users/`. `.env*` selain `.env.example`: hanya `.env` yang dibuat atas permintaan pengguna (keputusan 20). `APP_KEY` di `phpunit.xml` adalah kunci khusus tes.
10. `git log`: "does not have any commits yet". Tidak ada perintah git pengubah state.

## 5. Tes karakterisasi (tidak diperbaiki, itu Tahap 2)

| Skenario | Hasil | Status |
|---|---|---|
| User role `user` membuka `/rbac/users` lewat URL | 403 (`mount()` mengotorisasi) | **lulus**, aktif |
| User `status = inactive` login di `⚡signin` | login berhasil (tidak ada cek status) | gagal → `->todo()` |
| User role `user` memanggil `saveUser` di `rbac.user-form-modal` | 200, user dibuat (aksi komponen tanpa otorisasi) | gagal → `->todo()` |

## 6. Sisa grep §9.7 yang dijelaskan

- `config/auth.php`, `config/session.php`, `config/permission.php`, `tests/Pest.php`: idiom "Of course" di komentar bawaan Laravel/Spatie/Pest, bukan istilah UNDIP.
- `.agents/skills/laravel-best-practices/rules/testing.md`: contoh kode `Ticket::factory()` di dokumen skill Boost, generik dan akan ditimpa `boost:update`.
- `PROMPT_ALHCore_Tahap1.md` sendiri memuat semua istilah (dokumen instruksi); dihapus manusia bersama log ini.

## 7. Captcha di UNDIP (untuk laporan)

Hanya di **form signup** (`signup.blade.php` menampilkan `/captcha/flat` dan field `captcha`; `RegisterRequest` memakai rule `captcha`), plus binding di `AppServiceProvider`. `⚡signin` dan forgot-password tidak memakainya. Karena override `text()` memanggil `valign()` yang tidak ada di Intervention 4, gambar captcha signup pun tidak pernah bisa dirender: signup UNDIP rusak dua kali (tanpa `username`, dan captcha 500).

## 8. Placeholder yang wajib diganti per project

- Token warna `--color-accent-*` dan `--color-neutral-*` di `resources/css/app.css` (nilai default: biru generik dan abu-abu Tailwind).
- Font `--font-sans` (system stack).
- Logo `public/images/brand/logo.svg`, `logo-icon.svg`, dan `public/favicon.ico` (monogram "ALH").
- `APP_NAME`, dan seluruh nilai `ADMIN_*` di `.env` (password admin wajib diganti setelah first-run).
- `composer.json`: `name` (`<namespace>/alh-core`), `description`, `license` (`proprietary`), sesuai default yang boleh diubah manusia.

## 9. Pertanyaan untuk manusia

Terjawab pengguna: bug 403→500 (dikerjakan, keputusan 22), upgrade dependensi (dikerjakan, keputusan 23), `copilot` di `boost.json` (dikerjakan), versi TailAdmin (free, keputusan 24).

Masih terbuka:
1. Hapus `.env` lokal (butir §9.9) dan `PROMPT_ALHCore_Tahap1.md` sebelum commit pertama? **Ditunda pengguna**; keduanya tetap ada sekarang.
2. Nilai final token accent dan neutral (masih placeholder, bagian 8).
