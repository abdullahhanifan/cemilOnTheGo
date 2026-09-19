# Spesifikasi ALH Core v0.1

> Status: **Draft — Rev 1 (19 September 2026).** Hasil audit isi UNDIP Dashboard menggantikan sebagian asumsi draft awal. Bagian yang berubah ditandai **[Rev 1]**. Yang masih menunggu konfirmasi ditandai **[Usulan]**.
> Dasar utama: codebase **UNDIP Dashboard** (jalur auth reguler, non-SSO; Manajemen User & Role yang sudah dinyatakan matang; sistem visual/layout).
> Lapisan tambahan: prinsip keamanan hasil pembelajaran dari **EduCore** (bukan kode EduCore, hanya pola/kaidahnya).

---

## 1. Latar Belakang & Tujuan

Setiap project baru (client, abdimas, internal PT. Cipta Digital Global Teknologi) selalu mengulang pekerjaan yang sama di awal: autentikasi, role & permission, layout admin (sidebar/header), komponen UI dasar, dan Manajemen User. Ini memakan waktu dan berisiko inkonsistensi kualitas antar-project.

**ALH Core** dibuat sebagai **starter kit / boilerplate** berbentuk **git template repository**, sehingga project baru bisa langsung "Use this template" dan mulai dari fitur bisnis, bukan dari nol. Setiap project turunan mendapat salinan kode sendiri; perbaikan di ALH Core tidak otomatis sampai ke project yang sudah berjalan.

Tujuan v0.1:
1. Auth reguler (non-SSO) siap pakai — login, register (**opsional, default mati lewat feature flag [Rev 1]**), forgot/reset password, logout, captcha (dapat dimatikan).
2. Skeleton role & permission generik (bukan role spesifik EduCore) menggunakan Spatie Laravel-Permission.
3. Layout admin (sidebar + header) mengikuti sistem visual UNDIP Dashboard, dengan token warna yang mudah diganti per-project.
4. Manajemen User (CRUD user + assign role) diambil dari modul UNDIP yang sudah matang.
5. Komponen Blade reusable: stat-card, data-table-with-status, action-panel (hasil polish yang sudah dikerjakan di EduCore, diterapkan ulang di atas base UNDIP).
6. Dokumen pendamping: `DESIGN_SYSTEM.md` (template, token warna placeholder) dan `SESSION-SUMMARY.md` (konvensi handoff antar sesi agent).
7. Prinsip keamanan EduCore didokumentasikan sebagai **guideline wajib diterapkan**, bukan kode yang di-copy paste.

**Bukan tujuan v0.1** (di luar cakupan, boleh menyusul di v0.2+):
- SSO / integrasi identity provider eksternal.
- Modul bisnis spesifik (tidak ada modul tes psikometri, tidak ada modul EduCore apapun di dalam ALH Core; tidak ada modul Helpdesk, Content, Report, atau Sync dari UNDIP).
- Multi-tenancy.
- API/REST layer publik. **[Rev 1]** Sanctum, `api.php`, `ApiKey`, dan `personal_access_tokens` tidak dibawa.
- **[Rev 1]** Grafik, peta, kalender, dan editor rich-text (ApexCharts, FullCalendar, jsvectormap, Leaflet, Quill, Swiper, Prism). Ditambahkan per project saat ada kebutuhan nyata.

---

## 2. Format & Distribusi

- **Bentuk:** GitHub git template repository (bukan package Composer, bukan sub-tree). Alasan: paling cepat dipakai ulang ("Use this template" → repo baru bersih tanpa histori git lama), dan tetap mudah di-maintain sebagai satu sumber kebenaran.
- **Nama repository:** `alh-core`.
- **Nama produk/branding di dalam kode:** `ALH Core` (dipakai di README, judul default aplikasi sebelum diganti per-project). Nama package Composer default: `ciptadigital/alh-core`, lisensi `proprietary` **[Usulan]**.
- **Versioning:** tag semver (`v0.1.0`, dst) di repo template itu sendiri, supaya project turunan tahu dari versi ALH Core mana mereka mulai. Untuk v0.1, tidak perlu mekanisme sinkronisasi otomatis — cukup tag.
- **[Rev 1] Status git lokal:** folder `ALHCore` sudah `git init` baru tanpa remote dan tanpa histori UNDIP (terverifikasi: `remote -v` kosong, `count-objects` nol, tidak ada `.git` bersarang maupun `.gitmodules`). Commit pertama baru dibuat setelah Tahap 1 selesai dan direview, supaya kode UNDIP tidak masuk histori. Sebelum agent berjalan, backup folder utuh dibuat di luar repo.
- **[Rev 1]** Repo GitHub dibuat **private** dulu, dan baru dijadikan template repository setelah verifikasi end-to-end (§9 langkah 6) lolos.

---

## 3. Sumber Dasar per Komponen [Rev 1]

Tabel ini eksplisit dan mengikat: kejelasan sumber di sini penting supaya proses ekstraksi tidak mencampur dua codebase yang berbeda gaya.

### 3.1 Stack terverifikasi dari UNDIP Dashboard

PHP ^8.3, Laravel ^13.0, Livewire ^4.3 (single-file component berawalan ⚡ yang berada di `resources/views`, bukan di `app/Livewire`), Spatie Laravel-Permission ^8.3, mews/captcha ^3.4, Tailwind 4 + Vite 7 (komponen berbasis TailAdmin), Pest 4. Auth **custom** (bukan Breeze/Fortify/Jetstream): login adalah komponen Livewire `⚡signin` berbasis `username`; signup dan forgot/reset password adalah controller + Blade.

### 3.2 Pemetaan

| Komponen | Sumber | Catatan |
|---|---|---|
| Autentikasi (login/logout/reset password) | **UNDIP Dashboard**, jalur reguler saja (bagian SSO **tidak** diikutkan) | Diekstrak apa adanya lalu dirapikan. Login memakai `username`; `email` dijadikan wajib (dibutuhkan reset password); `password` tidak lagi nullable |
| Signup | **UNDIP Dashboard**, diperbaiki | Dari kode yang terlihat, signup UNDIP tidak mengisi `username` (kolom wajib dan unik) sehingga gagal. Diperbaiki, dan dikontrol `ALH_REGISTRATION_ENABLED` (default mati) |
| Captcha | **UNDIP Dashboard** (`mews/captcha` + override `App\Overrides\Captcha` untuk PHP 8.1+) | Dibawa. Dapat dimatikan lewat `CAPTCHA_DISABLE`. Di `⚡signin` UNDIP captcha tidak terpasang; di ALH Core dipasang di signin, signup, dan forgot-password **[Usulan]** |
| Struktur Role & Permission | **UNDIP Dashboard** (Spatie Laravel-Permission) | Lihat §4. Mekanisme permission yang diturunkan dari menu dipertahankan |
| Manajemen User (CRUD + assign role) | **UNDIP Dashboard** — dinyatakan sudah matang oleh user | Livewire SFC `pages/rbac/⚡users` dan `⚡roles` + modal di `components/rbac/`. Diekstrak dengan perubahan minimal: buang field SSO, role generik. Pengamanan ditambah di Tahap 2 |
| Layout Admin (sidebar, header, struktur halaman) | **UNDIP Dashboard** | Logo, warna, dan font di-hardcode di class Blade, sehingga harus diganti token pada Tahap 1 (lihat §5) |
| Komponen Blade reusable (stat-card, data-table-with-status, action-panel) | **Pola/hasil kerja EduCore** (dibangun ulang di atas visual UNDIP) | Murni komponen UI generik yang kebetulan pertama kali dirapikan selama pengembangan EduCore |
| Komponen dasar UI (`ui/*`, `common/*`, `form/*`) | **TailAdmin** (via UNDIP Dashboard) | Hanya yang terjangkau dari halaman yang dipertahankan. Kredit dan lisensi dicatat di `THIRD_PARTY_NOTICES.md` (Tahap 3). Versi TailAdmin yang dipakai (free atau pro) perlu dipastikan sebelum template dibagikan |
| `DESIGN_SYSTEM.md` (format dokumen) | Pola dari EduCore | Isi/token warna dikosongkan/placeholder, bukan warna Teal Trust EduCore |
| `SESSION-SUMMARY.md` (konvensi handoff agent) | Pola dari EduCore | Format saja, isi kosong di starter kit |
| Prinsip keamanan (route-level permission middleware, defense-in-depth, no-hard-delete) | **Pelajaran dari EduCore**, ditulis sebagai guideline | Diterapkan sebagai *pattern* di skeleton (satu grup route contoh yang sudah pasang middleware permission), bukan sekadar teks dokumentasi |

**Yang tidak dibawa dari UNDIP:** SSO (SsoController, Services/Sso, Socialite), Helpdesk, Content (post/FAQ/resource), Report, Sync (SIAP/Moodle), GA4 Analytics, API + Sanctum + Scramble, skrip deploy khusus UNDIP, seluruh aset dan nama UNDIP.

---

## 4. Role & Permission — Skeleton Generik [Rev 1]

**Temuan audit.** UNDIP tidak memakai daftar permission statis. Permission diturunkan dari struktur menu di `MenuHelper`: tiap item menu punya `permission_key` (`modul:fitur`) yang otomatis menghasilkan empat permission `modul:fitur-view|create|edit|delete`. Matriks di UI Manajemen Role dirender dari struktur yang sama, dan seeder membaca daftar permission dari sana. Bagian ini sudah matang dan memenuhi prinsip "granular per-aksi", sehingga **dipertahankan**. Penamaan `lihat-user` / `kelola-user` pada draft awal diganti supaya seragam dengan mekanisme ini **[Usulan]**.

**Role default (seed awal):**
- `admin` — akses penuh. Diberikan semua permission di seeder, dan `Gate::before` melewatkan semua pengecekan untuk role yang namanya diatur di `config/alh.php` (`super_admin_role`, default `admin`). Menggantikan `'Super Admin'` yang di-hardcode di UNDIP.
- `user` — role dasar tanpa akses admin; hanya `dashboard:dashboard-view`.

**Permission default (diturunkan dari menu):**
- `dashboard:dashboard-{view,create,edit,delete}`
- `access-control:users-{view,create,edit,delete}`
- `access-control:roles-{view,create,edit,delete}`

**Prinsip yang wajib dipertahankan saat project turunan menambah role/permission baru** (didokumentasikan di README + `SECURITY_GUIDELINES.md`, lihat §6):
1. Permission granular per-aksi (bukan satu permission besar `akses-admin`), supaya matriks akses tetap presisi saat role baru ditambahkan.
2. Setiap role baru **wajib** didaftarkan di seeder, bukan dibuat manual via tinker/DB langsung, supaya reproducible.
3. Penamaan permission konsisten: `modul:fitur-aksi` (mis. `access-control:users-edit`). Menambah menu berarti menambah `permission_key` di `MenuHelper`, bukan menulis permission manual.
4. **[Rev 1]** Setiap route yang dilindungi menu wajib punya permission middleware yang sesuai (lihat §6 dan Tahap 2).

**Akun admin default [Rev 1]:** dibuat `AdminUserSeeder` dari `.env` lewat `config/alh.php` (`ADMIN_NAME`, `ADMIN_USERNAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`). `.env.example` berisi placeholder yang jelas salah pakai. Di production, seeder menolak berjalan bila password kosong, sama dengan placeholder, atau kurang dari 12 karakter. Password wajib diganti setelah first-run (tertulis di README). Command interaktif `alh-core:install` tetap di v0.2.

---

## 5. Layout & Sistem Visual [Rev 1]

- Sidebar + header mengikuti struktur UNDIP Dashboard apa adanya (posisi menu, header info user, pola breadcrumb bila ada).
- Warna: token neutral (bg halaman, bg card, border, label) dan token accent (brand color) **dipisah eksplisit** sejak awal — penerapan langsung pelajaran perbaikan UI EduCore ("flat/kurang tegas" karena warna struktural ikut ditinting warna brand).
- **Temuan audit:** warna UNDIP (`#000d90`, `#000352`) dan warna netral (`#edf1f6`, `#616e84`, `#2f3847`, `#949cab`) di-hardcode sebagai hex di class Blade (sidebar, signin), bukan token. Karena itu pemisahan neutral/accent **dikerjakan di Tahap 1**, bukan hanya didokumentasikan.
- Token didefinisikan di `@theme` pada `resources/css/app.css` (Tailwind 4 mensyaratkan awalan `--color-`): `--color-accent-primary`, `--color-accent-primary-hover`, `--color-accent-secondary`, `--color-neutral-bg`, `--color-neutral-card`, `--color-neutral-border`, `--color-neutral-label`, `--color-neutral-heading`. Utility yang dipakai: `bg-accent-primary`, `text-neutral-label`, dst. Font lewat satu token `--font-sans`.
- `DESIGN_SYSTEM.md` starter mendokumentasikan token ini dengan nilai default netral (placeholder, wajib diganti per-project), tanpa hex final EduCore.
- Komponen reusable dari EduCore (stat-card, data-table-with-status-badge, action-panel, tombol CTA gradient) ditata ulang memakai token generik ini, bukan warna Teal Trust.

---

## 6. Dokumen Pendamping dalam Template

Repo `alh-core` menyertakan, sejak commit pertama (README minimal) dan dilengkapi di Tahap 3:

1. **`README.md`** — cara pakai template ("Use this template" → clone → `composer install` → `.env` → `php artisan migrate --seed` → role & permission default sudah ter-seed → login dengan akun admin dari `.env`). **[Rev 1]** Memuat prasyarat (PHP 8.3, ekstensi `gd` untuk captcha, Composer, Node) dan versi stack.
2. **`DESIGN_SYSTEM.md`** — template dengan struktur section yang sama seperti dokumen EduCore (palette, tipografi, spesifikasi komponen), isi token placeholder.
3. **`SESSION-SUMMARY.md`** — file kosong dengan header format standar, siap dipakai coding agent project turunan untuk handoff antar sesi.
4. **`SECURITY_GUIDELINES.md`** — konsolidasi pelajaran EduCore menjadi checklist eksplisit:
   - Setiap route admin **wajib** dilindungi middleware permission di level route group, bukan hanya disembunyikan di menu (`@can` di Blade saja tidak cukup). **[Rev 1]** Audit UNDIP menunjukkan route admin hanya dilindungi `auth`, sehingga ini bukan teori.
   - **[Rev 1]** Komponen Livewire wajib mengotorisasi juga di `mount()` dan di setiap aksi yang mengubah data. Request `/livewire/update` hanya menjalankan ulang middleware yang terdaftar sebagai persistent middleware, sehingga middleware permission di route saja tidak cukup untuk melindungi aksi komponen. Perilaku ini diverifikasi pada Livewire 4 di Tahap 2.
   - **[Rev 1]** Login hanya untuk user `status = active`, dengan rate limiting dan captcha.
   - Aksi kritis (hapus, ubah status penting) **wajib** soft-delete / berbasis status, bukan hard-delete. **[Rev 1]** Untuk user: nonaktifkan lewat `status`, bukan hapus baris.
   - Setiap tabel milik user (data sensitif/dapat diaudit) disarankan punya kolom `created_by`/`updated_by` untuk audit trail dasar.
   - **[Rev 1]** Signup publik default mati. Seeder admin menolak password placeholder di production. `TrustProxies` tidak mempercayai `*` secara default; daftar proxy datang dari env.
   - Checklist ini disertai **contoh nyata** di skeleton: satu route group Manajemen User yang memakai middleware permission sebagai referensi pola, bukan cuma penjelasan teks.
5. **`AGENTS.md`** **[Rev 1]** — instruksi generik untuk coding agent: stack dan versi, perintah test/build, dan aturan "baca `SPEC`, `SESSION-SUMMARY.md`, `DESIGN_SYSTEM.md`, dan `SECURITY_GUIDELINES.md` sebelum mengubah kode".
6. **`THIRD_PARTY_NOTICES.md`** **[Rev 1]** — kredit dan lisensi TailAdmin dan dependensi utama.

---

## 7. Struktur Direktori [Rev 1]

Livewire 4 di UNDIP memakai single-file component, sehingga halaman dan modal berada di `resources/views`, bukan `app/Livewire`.

```
app/
  Helpers/MenuHelper.php            (sumber permission + menu)
  Http/
    Controllers/                    AuthController, PasswordResetController
    Middleware/                     CheckMaintenanceMode, SetUserTimezone,
                                    TrustProxies, EnsureRegistrationEnabled
    Requests/Auth/                  RegisterRequest
  Models/User.php
  Overrides/Captcha.php
  Providers/AppServiceProvider.php
  View/Components/                  (hanya yang terjangkau)
bootstrap/app.php                   (alias middleware: role, permission,
                                     role_or_permission, registration.enabled)
config/
  alh.php                           (registration_enabled, super_admin_role,
                                     default_role, admin)
  captcha.php, permission.php, ... (standar)
database/
  migrations/                       users_and_auth, cache, permission_tables,
                                    jobs, job_batches
  seeders/                          DatabaseSeeder, RbacSeeder, AdminUserSeeder
  factories/UserFactory.php
resources/
  css/app.css                       (token @theme)
  js/app.js                         (ringan: popper, flatpickr, select2, Alpine dropdown)
  views/
    layouts/                        app, app-header, backdrop,
                                    fullscreen-layout, sidebar
    pages/
      auth/                         ⚡signin, signup, reset-password, new-password
      dashboard/⚡index
      rbac/                         ⚡users, ⚡roles
      maintenance.blade.php
    errors/                         404, 500, 503
    components/
      rbac/                         modal user & role
      ui/, common/, form/, svg/     (hanya yang terjangkau)
      tables/th-sortable
      stat-card.blade.php           (Tahap 3)
      data-table-with-status.blade.php   (Tahap 3)
      action-panel.blade.php        (Tahap 3)
routes/web.php, routes/console.php
tests/                              (Pest: Rbac, SignIn, Registration,
                                     Maintenance, SidebarMenuRbac, Smoke)
DESIGN_SYSTEM.md
SESSION-SUMMARY.md
SECURITY_GUIDELINES.md
AGENTS.md
THIRD_PARTY_NOTICES.md
README.md
```

---

## 8. Keputusan & Open Items [Rev 1]

### 8.1 Terjawab oleh audit

| # | Pertanyaan draft awal | Jawaban |
|---|---|---|
| 1 | Basis auth UNDIP | Custom. Login = Livewire SFC `⚡signin`; signup/reset = controller + Blade |
| 2 | Versi | Laravel ^13.0, Livewire ^4.3, Spatie Permission ^8.3 |
| 3 | Self-signup di UNDIP | Ada (`POST /signup`), tetapi tidak mengisi `username` sehingga gagal. Dibawa dengan perbaikan dan feature flag default mati |
| 4 | Akun admin default | Seeder membaca `.env` via `config/alh.php`; menolak placeholder di production |

### 8.2 Terkunci

- Captcha dibawa (dapat dimatikan lewat env).
- Grafik/peta/kalender/editor rich-text tidak dibawa.
- Sanctum, `api.php`, `ApiKey`, `personal_access_tokens` tidak dibawa.
- SSO dibuang sepenuhnya, termasuk kolom `sso_provider` / `sso_id`.
- Skrip deploy UNDIP tidak dibawa; salinannya disimpan di luar repo sebagai referensi v0.2.
- Migration dasar diedit langsung (squash), bukan ditambah migration baru.
- Satu lockfile JS: `package-lock.json` (`bun.lock` dihapus).
- Pengembangan lewat whitelist, bukan daftar hapus.

### 8.3 Menunggu konfirmasi

1. **Identitas login.** Default: `username` (seperti UNDIP), `email` wajib untuk reset password. Alternatif: login dengan email.
2. **Penamaan permission.** Usulan: skema UNDIP `modul:fitur-aksi` (§4). Alternatif: kembali ke `lihat-user` / `kelola-user`, dengan konsekuensi menulis ulang mekanisme permission-dari-menu dan modal role.
3. **Penempatan captcha.** Usulan: signin, signup, forgot-password.
4. **Nama package dan lisensi** (`ciptadigital/alh-core`, `proprietary`), dan **versi TailAdmin** yang dipakai (free atau pro) sebelum template dibagikan.

### 8.4 Diverifikasi di tahap berikutnya

- Apakah komponen `⚡users` dan `⚡roles` UNDIP mengotorisasi aksi di dalam komponen (Tahap 2; tes karakterisasi di Tahap 1 memberi jawaban awal).
- Perilaku persistent middleware pada Livewire 4.
- Apakah login UNDIP mengecek `status` di tempat lain (dari kode `⚡signin` yang terlihat, tidak).

Catatan teknis non-blocking:
- Ekstraksi tidak boleh membawa data atau nama spesifik UNDIP (nama instansi, logo, data dummy).
- `.env.example` generik (nama aplikasi `ALH Core`).
- `config/logging.php` dan beberapa model cocok dengan pencarian "sso" hanya karena kata "processors" dan "associate"; bukan kode SSO.

---

## 9. Rencana Tahapan Kerja [Rev 1]

1. ✅ Kunci keputusan dasar (dokumen ini).
2. ✅ Review bersama user — Open Items §8 terjawab lewat audit kode; sisanya di §8.3.
3. **Tahap 1 — Prune, generikkan, boot** (`PROMPT_ALHCore_Tahap1.md`): whitelist prune, buang SSO/API/modul bisnis, token warna, signup diperbaiki, captcha, seeder admin dari `.env`, RBAC apa adanya dengan menu minimal. Kriteria selesai: install bersih, `migrate:fresh --seed`, `php artisan test` hijau, cek rujukan view nol yang hilang, grep istilah UNDIP bersih, tanpa secret. Commit pertama dilakukan manual setelah review.
4. **Tahap 2 — Hardening RBAC dan Manajemen User:**
   - permission middleware di route group (dengan contoh referensi);
   - otorisasi di `mount()` dan aksi komponen Livewire, plus verifikasi persistent middleware;
   - login: cek `status`, rate limiting, captcha, middleware `guest`;
   - hapus user → nonaktifkan lewat `status`;
   - `TrustProxies` berbasis env;
   - tes akses langsung via URL per role.
5. **Tahap 3 — Komponen reusable dan dokumen:** stat-card, data-table-with-status, action-panel, `DESIGN_SYSTEM.md`, `SECURITY_GUIDELINES.md`, `SESSION-SUMMARY.md`, `AGENTS.md`, `THIRD_PARTY_NOTICES.md`, README final. Hapus `EXTRACTION_LOG.md`.
6. **Verifikasi end-to-end:** clone ke folder baru, jalankan dari nol (`migrate --seed`, login, cek middleware permission benar-benar memblokir akses langsung via URL — pola audit yang sama dengan EduCore). Jadikan template repository dan tag `v0.1.0`.

---

*Dokumen ini adalah working draft. Struktur dan penomoran keputusan mengikuti pola FSD EduCore (locked decisions, revisi ditandai, open items dipisah per kategori). Rev 1 menandai hasil audit atas kode UNDIP Dashboard; keputusan di §8.3 dikunci pada revisi berikutnya.*
