# ProcessFlow — Phase 1

Fondasi platform otomasi proses bisnis berbasis Laravel 12, PHP 8.3+, React 19, TypeScript, Inertia 2, Tailwind CSS, shadcn/ui, dan Spatie Laravel Permission. Root aplikasi berada di `D:\Code\processflow`, bersama `artisan`, `composer.json`, `package.json`, serta dokumen asli `agent.md` dan `task.md`. Folder `app/` adalah source Laravel, bukan root aplikasi.

## Arsitektur dan ruang lingkup

Modular monolith dengan controller tipis, Form Request untuk validasi, policy untuk otorisasi, dan service transaksional untuk perubahan workflow dan pengguna. Session authentication dari starter kit resmi Laravel tetap digunakan. Izin frontend hanya mengatur tampilan; backend tetap memeriksa setiap tindakan.

Phase 1 mencakup autentikasi, pengaturan akun dan tema, dashboard berbasis data, workflow draft CRUD/arsip/duplikasi, pengelolaan pengguna dan assignment role, serta departemen. Designer hanya mengelola workflow miliknya; Super Admin mengelola seluruh workflow; Auditor mendapat akses baca. Pendaftaran selalu memberi role Requester, bukan administrator.

Workflow belum dapat dipublikasikan atau dijalankan. Builder, formulir dinamis, approval, instance runtime, notifikasi, SLA, analytics runtime, Docker/Nginx, dan deployment produksi merupakan milestone berikutnya. Navigasi tidak menampilkan fitur yang belum tersedia.

## Skema database

- `users`: identitas, email unik, password hash, email verification, `department_id` nullable.
- `departments`: nama unik dan timestamps.
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`: RBAC Spatie.
- `workflows`: nama, kode unik, deskripsi, kategori, pemilik dengan FK ke users, status draft/archived, timestamps.
- Tabel session, cache, dan queue dari Laravel.

Workflow versions immutable, nodes/edges, form definitions, instances, tasks, dan execution/audit logs ditambahkan pada milestone terkait; belum ada klaim bahwa state runtime tersedia. Workflow yang diarsipkan tidak dapat diedit/dihapus. Pengguna pemilik workflow tidak dapat dihapus, dan administrator tidak dapat menghapus/menurunkan role dirinya sendiri atau administrator terakhir.

## Persiapan

Jalankan seluruh perintah dari root `D:\Code\processflow` (folder `processflow`, bukan `app/`):

```powershell
composer install
npm ci
php artisan key:generate
```

Untuk instalasi baru, salin `.env.example` menjadi `.env` sebelum membuat application key. Jangan simpan `.env` atau application key ke version control.

Database aplikasi menggunakan MySQL. Sediakan database `processflow`, aktifkan ekstensi PHP `pdo_mysql`, lalu isi `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT` (umumnya `3306`), `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env` dengan kredensial milik Anda. Jangan menimpa `.env` yang sudah dikonfigurasi. Untuk cache/queue Redis sesuai `.env.example`, aktifkan ekstensi `redis` dan isi `REDIS_*`; pengembangan lokal juga dapat memakai `CACHE_STORE=file` dan `QUEUE_CONNECTION=database`. Jalankan:

```powershell
php artisan migrate --seed
npm run build
php artisan serve
```

Untuk pengembangan frontend, jalankan `npm run dev` pada terminal terpisah. Redis digunakan untuk cache dan queue sesuai `.env.example`. Jangan mengaktifkan debug pada deployment produksi.

### Runtime lokal

PHP CLI membutuhkan `pdo_mysql` untuk aplikasi. Perintah `migrate --seed` di atas hanya untuk instalasi awal; untuk memeriksa database yang sudah terisi, gunakan `php artisan migrate:status --database=mysql`. Jangan menjalankan ulang seeder tanpa meninjau efeknya: `DemoDataSeeder` dapat mereset password akun demo yang cocok. Jangan gunakan `migrate:fresh` pada database berisi data.

SQLite hanya dipakai untuk tes terisolasi melalui `phpunit.xml`, bukan sebagai database aplikasi. Jika ekstensi SQLite belum aktif di php.ini, gunakan opsi `-d` pada perintah tes di bawah. Server `php artisan serve` hanya untuk pengembangan.

## Pengguna awal

Seeder mengisi lima role (Super Admin, Workflow Designer, Requester, Approver, Auditor), izin, dan tiga departemen (IT, Finance, Operations), tanpa password demo atau administrator bawaan.

Daftarkan akun melalui aplikasi, kemudian promosikan akun yang benar melalui terminal tepercaya:

```powershell
php artisan tinker
```

Di Tinker, ganti email berikut dengan akun yang sudah didaftarkan:

```php
$user = App\Models\User::where('email', 'admin@example.com')->firstOrFail();
$user->syncRoles(['Super Admin']);
```

Login ulang, lalu gunakan halaman Pengguna & Role untuk memberi role dan departemen kepada pengguna lainnya.

### Data demo eksplisit (lokal/testing saja)

Password demo adalah literal `password` di `DemoDataSeeder`; tidak membutuhkan `DEMO_PASSWORD` atau konfigurasi password. Gunakan akun demo hanya di lingkungan lokal/testing.

```powershell
php artisan db:seed --class=DemoDataSeeder
```

Seeder membuat departemen dan lima akun: `admin@demo.processflow.test` (Super Admin), `designer@demo.processflow.test` (Workflow Designer), `requester@demo.processflow.test` (Requester), `approver@demo.processflow.test` (Approver), serta `auditor@demo.processflow.test` (Auditor). Login memakai password `password`. Designer baru mendapat dua workflow draft dan satu archived; semuanya hanya metadata Phase 1, tanpa builder, approval, atau runtime engine.

Seeder menolak lingkungan selain `local`/`testing` sebelum menulis data. Saat dijalankan ulang, password akun demo yang email, nama `Demo ROLE`, dan role persis cocok dikembalikan ke `password` melalui hashed cast. Akun lain dengan email yang sama tidak diubah; departemen dan role tidak diubah atau dipromosikan. Workflow berkode sama tidak diubah. Workflow demo hanya dibuat saat akun designer baru dibuat; data workflow yang dihapus/diubah setelah seed tidak dipulihkan otomatis.

## Pemeriksaan

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
npm run lint
npx tsc --noEmit
npm run build
npm audit
composer audit
```

Pemeriksaan terbaru: 44 tes / 295 assertions lulus; Pint, ESLint, TypeScript dan build lulus. Tes mencakup autentikasi starter kit, otorisasi dan ownership workflow, validasi kode unik, arsip/duplikasi, pengelolaan pengguna/departemen, proteksi administrator, pendaftaran dengan least privilege, serta idempotensi seed, tabrakan akun/kode workflow, guard lingkungan, password demo literal, dan reset password hanya untuk akun demo yang cocok.

Verifikasi relokasi 15 Oktober 2026 dijalankan dari `D:\Code\processflow` menggunakan PHP CLI 8.4.24. `php artisan optimize:clear --no-ansi` berhasil untuk config, cache, compiled, events, routes, dan views tanpa error Redis; compiled views yang masih menyimpan path lama telah dibersihkan. Tidak ditemukan path root lama pada source/config kustom; referensi historis di `storage/logs/laravel.log` dibiarkan. `php artisan migrate:status --no-ansi` menggunakan konfigurasi MySQL yang ada dan kelima migrasi berstatus Ran. PHPUnit dengan ekstensi SQLite per proses lulus (44 tes / 295 assertions); Pint `--test`, `npm run lint`, `npx tsc --noEmit`, dan `npm run build` juga lulus. Audit dependency tidak dijalankan pada verifikasi ini.

HTTP smoke dari root dengan `php -S 127.0.0.1:18763 -t public`: `/`, `/login`, dan `/register` mengembalikan 200; `/dashboard` untuk guest mengembalikan 302 ke `/login`. Hanya proses server pemeriksaan yang dihentikan setelah selesai. Hash `.env` sebelum/sesudah identik; tidak ada migrasi/seed yang dijalankan ulang atau reset password akun MySQL. Browser interaktif/E2E, operasi Redis di luar cache clear, konkurensi MySQL, email delivery, dan deployment produksi belum diverifikasi.

## Milestone berikutnya

1. Phase 2: React Flow builder, persistence draft graph dan validasi server.
2. Phase 3: workflow versions immutable, form submission, engine dan approval dengan locking/idempotency.
3. Phase 4: monitoring, notifikasi, SLA dan audit bisnis.
4. Phase 5: deployment Docker/Nginx, worker/scheduler, pengujian tambahan dan panduan produksi.
