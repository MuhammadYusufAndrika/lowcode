# ProcessFlow

Platform otomasi proses bisnis: Laravel 12, PHP 8.3+ (image PHP 8.4), React 19, TypeScript, Inertia 2, Tailwind, React Flow, Spatie Permission, MySQL 8.4, Redis. Jalankan perintah dari root repository (`D:\Code\processflow`), bukan folder `app/`.

## Implementasi saat ini

Modular monolith dengan session authentication, RBAC, policy backend, validasi server dan service transaksional. Pendaftaran selalu memberi role Requester. Super Admin mengelola pengguna/role, departemen dan workflow; Workflow Designer mengelola workflow miliknya; Requester mengirim dan membatalkan permintaan sendiri; Approver mengambil keputusan sesuai assignment; Auditor mendapat akses baca monitoring/audit. Menyembunyikan tombol bukan pengganti otorisasi backend.

Tersedia CRUD/arsip/duplikasi workflow, visual builder, konfigurasi field/assignment/cabang, draft revision, publikasi snapshot versi, katalog formulir dinamis, submission/file privat, approval, timeline, monitoring, audit dan notifikasi dalam aplikasi. Draft save/publish memakai revision check (409 jika stale). Instance tetap menunjuk snapshot versi yang diterbitkan, bukan draft terbaru. Arsip menolak submission baru. Pengguna pemilik workflow tidak dapat dihapus; administrator terakhir dan role administrator sendiri dilindungi.

### Skema graph dan semantik runtime

Definition JSON memiliki `nodes` dan `edges`. Node: `{id,type,position:{x,y},data:{label,...}}`; edge: `{id,source,target,sourceHandle}`. ID node berupa huruf/angka/underscore/hyphen. Batas: 100 node, 300 edge, definition 1 MiB, maksimal 100 field per node, key field unik di seluruh graph. Validasi publish lebih ketat daripada penyimpanan draft: tepat satu start, setidaknya satu end, semua node terjangkau, tanpa cycle/dangling edge atau incoming edge ke start.

| Tipe | Konfigurasi dan perilaku |
| --- | --- |
| `start` | Satu cabang `next`, mulai eksekusi. |
| `form` | `fields`, satu cabang `next`. Seluruh field dari semua form dikumpulkan dan diisi pada submission awal; ini **bukan** formulir interaktif bertahap di tengah workflow. Runtime melewati node form tanpa menunggu input baru. |
| `approval` | `assignee_type` (`user`/`role`), `assignee_id`, opsional `deadline_hours` dan `escalation_user_id`. User penerima harus memiliki role Approver dan izin `approvals.decide`; role assignment harus memiliki penerima yang eligible. Dua cabang wajib: `approved` dan `rejected`. Runtime berhenti menunggu satu keputusan; role assignment bukan voting/quorum. |
| `condition` | `condition:{field,operator,value}`, cabang `true` dan `false`. Referensi field yang valid, bukan file. |
| `notification` | `message` atau label, mengirim notifikasi database kepada requester, lalu `next`. |
| `end` | Tanpa outgoing edge. `outcome` adalah `completed` (default) atau `rejected`. |

Penolakan approval mengikuti edge `rejected`; **tidak otomatis mengakhiri** instance. Hubungkan cabang itu ke end ber-outcome `rejected` bila itu tujuan bisnisnya. End `completed` yang dicapai melalui cabang reject tetap menghasilkan status completed.

Field: `text`, `textarea`, `number`, `date`, `select`, `radio`, `checkbox`, `file`; atribut `key`, `label`, `type`, `required`, opsional placeholder/default/options/min/max. Key dimulai huruf, maksimum 64 karakter. Text maksimum 10.000 karakter; number finite dalam rentang ±10^12; date `YYYY-MM-DD`; select/radio hanya opsi yang tersedia; checkbox boolean. Default value divalidasi sesuai tipe dan batas; file tidak mempunyai default isi.

Operator condition: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `in`. Perbandingan urutan hanya untuk field number; nilai JSON kondisi harus benar-benar number, bukan string angka. Boolean bukan string `"true"`; select/radio harus cocok opsi; tanggal harus valid. `in` memakai list 1–100 nilai bertipe sesuai field. Equality bersifat strict, kecuali integer/float dibandingkan secara numerik. Actual null selalu menghasilkan false, termasuk untuk `neq`.

File maksimal **10 MiB per file**, hanya PDF/JPEG/PNG/plain text dengan pemeriksaan MIME dan ekstensi. Disimpan melalui disk local di `storage/app/private/workflow-files`, diunduh lewat route aplikasi berotorisasi, bukan URL publik. Nginx menolak `/storage` dan `/uploads`; jangan jalankan `storage:link` untuk upload workflow. Deployment membatasi total request 32 MiB dan 20 file; banyak file 10 MiB sekaligus dapat melampaui batas request. Tidak ada antivirus/content disarm bawaan.

Submission menggunakan UUID idempotency key per requester; pengulangan payload sama mengembalikan instance yang sama, perubahan workflow/version/payload dengan key sama ditolak (409). Keputusan memakai transaction/row lock dan pemeriksaan state; pengulangan keputusan yang sama oleh aktor yang sama tidak menjalankan transisi ulang. Ini bukan bukti pengujian race di MySQL nyata.

### Data dan background processing

Database mencakup users/departments/RBAC, workflows dengan draft definition/revision, workflow versions berisi snapshot JSON, instances dan values, tasks, files, execution logs, audit logs, serta workflow notifications. Posisi node bersifat visual, edge menentukan transisi. Runtime bukan tabel node mutable yang mengubah instance lama ketika draft diedit.

Scheduler mendispatch `ProcessWorkflowDeadlines` setiap menit dengan `withoutOverlapping`; worker Redis harus aktif. Service deadline memeriksa ulang task pending/instance running di dalam transaction, menulis reminder sekali, lalu mengalihkan assignment ke escalation user eligible sekali bila disetel. Reminder dan escalation terjadi saat overdue, bukan jadwal email terpisah. Notifikasi assignment, keputusan, outcome, notification node dan SLA adalah **database-only**; tidak ada email workflow atau push notification. `MAIL_MAILER=log` pada Compose bukan delivery email. `php artisan workflows:deadlines` tersedia untuk pemeriksaan manual.

## Docker Compose

### Prasyarat dan rahasia

Docker Engine/Desktop Linux containers dan Compose v2 dengan dukungan required-variable interpolation. Dockerfile multistage: Composer dependencies tanpa dev, Node 22/Vite assets, PHP 8.4 FPM runtime, dan Nginx static image. App/worker/scheduler memakai target/image runtime yang sama; tidak ada source bind mount. Tidak ada migrasi, seed, key generation atau config cache otomatis saat container mulai.

Ekstensi yang dipasang: `pdo_mysql`, `redis`, `zip`, `intl`, `mbstring`, `pcntl`, `bcmath`, `opcache`, `curl`, `dom`, selain ekstensi bawaan image (termasuk fileinfo/PDO). `pcntl` mendukung timeout/signal worker. Paket yang diperiksa tidak mewajibkan GD untuk runtime; GD tidak dipasang. Helper pengujian gambar dapat memerlukan GD jika tes dijalankan dalam image lain. Runtime produksi tidak menyertakan dev dependencies atau Node/Composer.

Supply nilai Anda sendiri melalui process environment, secret manager yang mengekspor environment, atau file Compose env di luar version control. Jangan menimpa `.env` yang sudah ada. `.dockerignore` mengecualikan `.env*` (kecuali template), Git, vendor/node_modules, cache/log/storage lokal, build lama dan SQLite; runtime directories dibuat kembali tanpa menghapus isi volume.

Variabel wajib (Compose gagal sebelum startup jika kosong):

| Variable | Nilai yang harus disediakan operator |
| --- | --- |
| `APP_KEY` | Key Laravel valid dan stabil, misalnya `base64:` berisi 32 byte acak yang di-encode base64. Simpan aman, jangan rotasi sembarangan. |
| `APP_URL` | URL eksternal aplikasi. |
| `DB_DATABASE` | Nama database deployment. |
| `DB_USERNAME` | User aplikasi non-root, bukan root. |
| `DB_PASSWORD` | Password kuat untuk user aplikasi. |
| `MYSQL_ROOT_PASSWORD` | Password root database terpisah. Hanya service MySQL menerima ini. |
| `REDIS_PASSWORD` | Password Redis kuat. |

Tidak ada default credential. `.env.example` adalah template pengembangan, bukan secret produksi. Jika memakai file, gunakan `docker compose --env-file PATH ...` pada **setiap** command Compose; contoh berikut mengasumsikan environment sudah diekspor. Hindari `docker compose config` tanpa `--quiet` di CI/log karena nilai environment dapat tercetak. Environment container dapat dilihat operator Docker; env references ini bukan encrypted Docker secrets. Lindungi akses daemon, backup dan file rahasia.

Untuk instalasi baru saja, operator dapat membuat key secara eksplisit di terminal tepercaya: `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`. Simpan hasil sebagai secret `APP_KEY`, bukan source/log/README. Startup tidak membuat atau mencetak key.

Compose memaksa `APP_DEBUG=false`, MySQL/Redis internal host, Redis cache/queue/session, encrypted session, local filesystem dan stderr logging. Default `APP_ENV=production`, secure cookies true. `SESSION_SECURE_COOKIE=false` hanya untuk demo HTTP loopback; production gunakan HTTPS dan true. `HTTP_BIND=127.0.0.1`, `HTTP_PORT=8080` default; hanya Nginx dipublikasikan. MySQL, Redis dan FPM **tidak memiliki published port**. `APP_TIMEZONE`, `LOG_LEVEL`, `IMAGE_TAG` opsional.

### Instalasi pertama

```powershell
docker compose config --quiet
docker compose build --pull
docker compose up -d mysql redis app
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose up -d nginx worker scheduler
docker compose ps
```

`DatabaseSeeder` menyiapkan roles/permissions/departments, bukan akun administrator/password bawaan. Jangan mengganti seed di atas dengan DemoDataSeeder pada produksi. Daftarkan akun yang benar lewat aplikasi, lalu promosikan melalui terminal tepercaya:

```powershell
docker compose exec app php artisan tinker
```

```php
$user = App\Models\User::where('email', 'YOUR_REGISTERED_EMAIL')->firstOrFail();
$user->syncRoles(['Super Admin']);
```

Ganti placeholder email dengan akun yang Anda verifikasi, lalu login ulang. Jangan menyediakan credentials administrator di Dockerfile atau startup script.

### Operasi dan update

Volume `storage` dibagi app/worker/scheduler, dimiliki www-data saat volume baru diinisialisasi. Nginx hanya menerima public assets dari image, tidak menerima private storage. Volume MySQL dan Redis (AOF) persisten. Jangan gunakan `down -v` untuk update: itu menghancurkan data. Volume eksternal yang sudah ada harus mempunyai permission storage yang sesuai; startup non-root tidak melakukan recursive chown atau menghapus data.

MySQL healthcheck menjalankan authenticated SELECT pada database aplikasi; Redis melakukan authenticated PING. App healthcheck memeriksa FPM ping; Nginx `/healthz` memeriksa server HTTP; worker/scheduler memeriksa proses, bukan freshness pekerjaan. Dependency checks membantu initial ordering, bukan recovery otomatis atau pembuktian aplikasi siap/schema termigrasi. Docker tidak otomatis me-restart container yang sekadar unhealthy; tambahkan monitoring/alerting eksternal dan queue-age/SLA checks.

Worker timeout 60 detik di bawah Redis retry_after default 90 detik, shutdown grace 90 detik; max-time 3600 menyebabkan worker refresh via restart policy. Jalankan satu scheduler; jangan menganggap `withoutOverlapping` pada dispatch sebagai jaminan exactly-once lintas replica. Review locking, job duration dan retry policy sebelum scaling.

Untuk update: backup database dan private files, build image baru dengan tag terkontrol, rencanakan maintenance/stop worker dan scheduler selama perubahan schema, jalankan **satu** `docker compose exec app php artisan migrate --force` setelah image app diperbarui, kemudian recreate seluruh runtime/web. Tidak ada parallel migration otomatis. Jangan seed demo atau menjalankan `migrate:fresh`. Pastikan web assets dan runtime dari revision yang sama. Jangan cache config ke dalam image dengan secret; entrypoint tidak menjalankan `optimize:clear` yang dapat menghapus shared cache/lock.

```powershell
docker compose logs --tail=100 app worker scheduler nginx
docker compose exec app php artisan migrate:status
docker compose exec app php artisan queue:failed
```

Untuk production nyata: tempatkan TLS reverse proxy di depan binding loopback (atau konfigurasi jaringan proxy secara eksplisit), pastikan external scheme/host cocok dan uji secure cookies/CSRF/redirect. Konfigurasi trusted proxy harus ditinjau bila diperlukan; jangan menerima forwarded headers dari sembarang client. Tambahkan TLS/HSTS pada edge setelah HTTPS benar, resource limits sesuai kapasitas, rotasi log, backup/restore teruji, patching/pinning image digest dan vulnerability scanning. Base image memakai version tags, bukan digest immutable. Redis bukan tempat satu-satunya menyimpan backup bisnis; eviction/persistence/HA perlu kebijakan deployment. Mengubah env password tidak mengganti user/password pada volume MySQL yang sudah terinisialisasi; rotasi harus dilakukan secara terkoordinasi.

## Demo lokal eksplisit, bukan produksi

Gunakan project Compose/volume dan secrets **terpisah** dari produksi. Sebelum command instalasi, set `APP_ENV=local`, `APP_URL=http://localhost:8080`, `SESSION_SECURE_COOKIE=false`, `HTTP_BIND=127.0.0.1` dalam environment khusus demo; supply semua secret wajib sendiri. Debug tetap false. Setelah migrasi:

```powershell
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

Seeder menolak selain local/testing. Jangan bypass guard dengan menjalankan container ber-environment local yang menunjuk database produksi. Menjalankan ulang seeder tidak mereset password atau role akun yang sudah ada. Akun demo lama `@demo.processflow.test` hanya diubah ke `@gmail.com` bila nama `Demo ROLE` dan role persis cocok, dengan ID dan histori tetap; benturan email atau identitas lama yang tidak cocok membatalkan transaksi akun. Akun Gmail yang sudah ada tanpa akun demo lama dibiarkan tanpa perubahan dan ketidakcocokan identitas dilaporkan; tidak ada promosi role. Workflow demo lain yang sudah ada tidak di-reset; procurement draft milik demo designer yang belum memiliki versi dapat diisi dan dipublish oleh seeder.

Sample runtime opsional setelah DemoDataSeeder (hanya environment local; tidak mereset data):

```powershell
php artisan db:seed --class=RuntimeDemoInstancesSeeder
```

Seeder menambahkan lima instance procurement: pending department, pending Finance, completed low/high, dan rejected. Idempotency key tetap membuat rerun melewati sample yang sudah ada tanpa menghapus histori; workflow procurement harus sudah diterbitkan dengan alur department lalu Finance untuk amount >= 10.000.000.

Semua login di tabel ini memakai password literal **`password`**, **khusus demo lokal/testing**:

| Login | Role |
| --- | --- |
| `admin@gmail.com` | Super Admin |
| `designer@gmail.com` | Workflow Designer |
| `requester@gmail.com` | Requester |
| `approver@gmail.com` | Approver |
| `auditor@gmail.com` | Auditor |

1. Designer: buka procurement builder; inspect semua enam tipe node, field item/amount/attachment, threshold numeric 10.000.000, approval dan dua end outcome. Save/publish perubahan akan membuat versi baru; instance lama tidak berubah. Dua workflow demo lain tetap draft/archived metadata.
2. Requester: buka katalog procurement, kirim item dan amount di bawah threshold: department approval terlebih dahulu, lalu condition false menuju notification/completed. Opsional upload file yang diizinkan.
3. Kirim permintaan lain dengan amount >= 10.000.000: instance menunggu department approval, kemudian Finance approval sebelum notification/completed. Approver membuka tugas dan file; penolakan menuju end rejected. Gunakan instance terpisah untuk mencoba kedua cabang.
4. Requester: lihat status/timeline/notifikasi atau batalkan request yang masih running. Auditor: baca monitoring/audit; akun lain tidak boleh melihat private request/file tanpa izin.
5. Untuk demo SLA, designer publish deadline pendek (minimum 0,01 jam) lalu submit instance baru ke approval dan biarkan overdue. Scheduler + worker memproses reminder/escalation; periksa database notifications dan execution/audit logs, bukan inbox email. Escalation demo ke approver yang sama tidak mendemonstrasikan pengalihan ke orang berbeda.

## Pengembangan tanpa Docker

Salin `.env.example` ke `.env` hanya jika belum ada; konfigurasi MySQL/Redis milik Anda, jangan overwrite environment yang ada. Aktifkan PHP pdo_mysql/redis dan dependency extensions.

```powershell
composer install
npm ci
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Untuk frontend development jalankan `npm run dev`; untuk job/SLA jalankan `php artisan queue:work redis` dan `php artisan schedule:work` di terminal terpisah. Artisan serve/Vite bukan server produksi. DemoDataSeeder tetap opt-in dengan aturan lingkungan yang sama.

## Validasi dan batas pembuktian

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
php vendor/laravel/pint/builds/pint --test
npx eslint .
npx tsc --noEmit
npm run test
npm run build
docker compose config --quiet
```

ESLint dijalankan tanpa `--fix` agar pemeriksaan tidak mengubah source (`npm run lint` saat ini memakai --fix). SQLite in-memory di phpunit.xml hanya untuk tes terisolasi; bukan database deployment. Jangan menjalankan migrasi test terhadap database produksi.

Verifikasi 16 Oktober 2026: PHPUnit **68 tes / 449 assertions**, Vitest **7 tes**, Pint --test, ESLint read-only, TypeScript noEmit, Vite production build dan git diff --check lulus. Compose `config --quiet` lulus memakai dummy process environment, tanpa mencetak secret atau mengubah `.env`. Docker daemon Linux tidak tersedia, sehingga image build, startup, nginx -t dalam image, healthchecks live, volume permissions, MySQL/Redis container dan HTTP container smoke **belum diverifikasi**. Build frontend host lulus, tetapi tidak membuktikan build Linux image; config validation bukan runtime certification.

Tes terisolasi mencakup policy/ownership, workflow definition/publish validation, immutable versions, runtime submission/approval/conditions/files, idempotency, SLA dan notifikasi sesuai suite repository; MySQL lokal dan Playwright Chromium juga diverifikasi: designer membuat workflow UI TEST #12, menambah start/end, drag, select/edit label, koneksi, save revision 1, reload posisi/properti/graph dan validasi server; requester mengirim procurement v2 amount 15.000.000 (#13), approver mengonfirmasi department lalu Finance, requester melihat completed, dua tugas disetujui beserta komentar dan timeline completion. Run sukses tanpa console/page error. Manifest error pada halaman create diperbaiki dengan shared Vite app entry; regression test ditambahkan. Record UI TEST dari percobaan sebelumnya tetap disimpan, tidak ada reset/cleanup histori. Hasil ini bukan bukti MySQL row-lock concurrency. Pengujian deployment aktual, load/races/retry/crash recovery, backup restore, TLS/proxy, email delivery, serta cakupan browser/E2E lain masih perlu dilakukan. Email workflow belum diimplementasikan; jangan menyebut log mail sebagai delivery. Audit dependency/container dan production security review tetap pekerjaan deployment tersendiri.
