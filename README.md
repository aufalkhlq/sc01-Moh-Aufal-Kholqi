# Sistem Reservasi Ruang Meeting

Aplikasi backend RESTful API dan web antarmuka untuk pengelolaan dan reservasi ruang meeting dengan pemodelan data konsistensi tinggi, pencegahan *double-booking* berlapis, penanganan konkurensi (*race-condition safe*), serta arsitektur kode berlapis (*Clean Architecture*).

---

## Daftar Isi
1. [Fitur Berdasarkan Tingkatan](#fitur-berdasarkan-tingkatan)
2. [Prasyarat & Cara Menjalankan](#prasyarat--cara-menjalankan)
3. [Antarmuka Web (UI & Interaksi Pengguna)](#antarmuka-web-ui--interaksi-pengguna)
4. [Keputusan Desain & Alasannya (ADR)](#keputusan-desain--alasannya-adr)
5. [Logika & Matriks Pengujian Overlap](#logika--matriks-pengujian-overlap)
6. [Pencegahan Race Condition & Concurrency](#pencegahan-race-condition--concurrency)
7. [Dokumentasi Endpoint API & Contoh Request](#dokumentasi-endpoint-api--contoh-request)
8. [Batasan yang Disadari (Known Limitations)](#batasan-yang-disadari-known-limitations)
9. [Bagian yang Belum Selesai (Unfinished Parts & Roadmap)](#bagian-yang-belum-selesai-unfinished-parts--roadmap)

---

## Fitur Berdasarkan Tingkatan

### 1. Mandatory (Fondasi Inti)
- **CRUD Ruang Meeting**: Nama, kapasitas, lokasi, status aktif, jeda waktu (*buffer*).
- **Pembuatan Booking**: Reservasi satu ruang untuk rentang waktu (`start_time` & `end_time`).
- **Pencegahan Double-Booking (Aturan Bisnis Utama)**: Ruang tidak boleh dipesan pada rentang waktu yang beririsan.
- **Daftar Booking per Ruang**: Dilengkapi filter berdasarkan tanggal spesifik atau rentang tanggal.
- **Membatalkan Booking (Otorisasi)**: Hanya pemilik booking yang berhak membatalkan reservasi.
- **Identitas User Sederhana**: Mekanisme identifikasi berbasis HTTP Header `X-User-Id` (API) dan Session Switcher (Web) yang ringan dan mudah diuji.
- **Validasi Input Ketat**: Memastikan format waktu ISO 8601 valid, waktu selesai harus setelah waktu mulai (`end_time > start_time`), dan ruang meeting valid.

### 2. Nice to Have (Kematangan Teknis)
- **Pencegahan Overlap di Lapisan Penyimpanan (Database Layer)**: Proteksi ganda menggunakan *Database Triggers* (`BEFORE INSERT` & `BEFORE UPDATE`) dengan `SIGNAL SQLSTATE '45000'`, sehingga *double-booking* tidak mungkin terjadi meskipun kode aplikasi dibypass via raw SQL.
- **Pengujian Matriks Overlap Lengkap**: Unit test untuk 5 variasi bentuk overlap:
  1. Rentang identik (*identical*) $\rightarrow$ Konflik
  2. Bersarang (*enclosed / nested*) $\rightarrow$ Konflik
  3. Memotong di awal (*overlaps start*) $\rightarrow$ Konflik
  4. Memotong di akhir (*overlaps end*) $\rightarrow$ Konflik
  5. Bersentuhan di ujung (*adjacent*: `end_time == start_time`) $\rightarrow$ **Diizinkan**
- **Penanganan Zona Waktu UTC Eksplisit**: Seluruh waktu dinormalisasi dan disimpan dalam UTC (*UTC at rest*). Klien dapat menyertakan parameter `?timezone=Asia/Jakarta` untuk mendapatkan representasi waktu lokal di respons (*Local at edge*).
- **Format Error Terstandarisasi**:
  - `409 Conflict` dengan kode `SCHEDULE_CONFLICT` dan detail booking yang beririsan.
  - `422 Unprocessable Content` untuk validasi parameter, jam operasional, atau format waktu.
  - `403 Forbidden` untuk pelanggaran hak otorisasi pemilik booking.
  - `401 Unauthorized` jika header identitas tidak disertakan.
  - `404 Not Found` untuk entitas yang tidak ditemukan.
- **Pemisahan Lapisan Domain (Clean Layered Architecture)**: `OverlapDetectionService` murni tanpa ketergantungan HTTP maupun Database, dapat diuji unit test dalam hitungan milidetik.

### 3. Enhancement (Tingkat Lanjut)
- **Aman terhadap Race Condition**: Transaksi atomik database dengan *Pessimistic Locking* (`SELECT ... FOR UPDATE`), dibuktikan dengan *automated concurrency test* yang mengeksekusi dua proses paralel secara simultan.
- **Booking Berulang (Recurring Bookings)**: Mendukung reservasi berkala harian (*daily*) dan mingguan (*weekly*) beserta pengecualian tanggal tertentu (*exception/skip dates*).
- **Jam Operasional & Buffer Time**: Konfigurasi batas jam operasional ruangan serta jeda waktu pembersihan/transisi antar-meeting (*buffer minutes*).
- **Pencarian Ruang Kosong (Availability Search)**: Mencari ruang meeting yang tersedia pada rentang waktu tertentu dengan kapasitas minimum. Evaluasi cerdas memisahkan jendela jam harian tanpa menganggap tanggal yang sama sebagai bentrok jika jamnya berbeda.
- **Audit Trail / Riwayat Perubahan**: Setiap tindakan reservasi, perubahan jadwal (*reschedule*), dan pembatalan (*cancel*) tercatat dalam tabel riwayat tanpa menghapus data lama (*soft cancellation*).
- **Halaman Reservasi Saya (`/my-bookings`)**: Tampilan personal bagi pengguna aktif untuk memantau seluruh jadwal pemesanan, melihat riwayat perubahan, melakukan reschedule, dan membatalkan booking.

---

## Prasyarat & Cara Menjalankan

### Prasyarat
- PHP 8.3 atau 8.4
- MySQL 8.0+ / MariaDB
- Composer
- Git

### Langkah Instalasi
1. **Clone repository & masuk ke direktori**:
   ```bash
   git clone <repo-url>
   cd reservasi_meeting
   ```

2. **Konfigurasi Environment (`.env`)**:
   Salin berkas `.env.example` menjadi `.env`:
   ```bash
   cp .env.example .env
   ```
   Pastikan pengaturan koneksi database MySQL telah sesuai:
   ```env
   APP_NAME="Meeting Room Reservation"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=reservasi_meeting
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Install Dependensi PHP**:
   ```bash
   composer install
   ```

4. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

5. **Jalankan Migrasi & Database Seeder**:
   ```bash
   php artisan migrate --seed
   ```
   *Catatan: Seeder akan otomatis membuat 2 user contoh (Alice: ID 1, Bob: ID 2) dan 3 ruang meeting (Rinjani, Semeru, Bromo) beserta konfigurasi jam operasional.*

6. **Jalankan Server Lokal**:
   ```bash
   php artisan serve
   ```
   Aplikasi siap diakses melalui browser di: **`http://localhost:8000`**.

---

## Menjalankan Pengujian Otomatis (Automated Tests)

Test suite mencakup pengujian unit (Domain Logic & Use Cases), integrasi feature API, database storage triggers, antarmuka web, dan pengujian konkurensi (Race Condition).

Jalankan seluruh test suite (**114 tests, 360 assertions**):
```bash
php artisan test
```

Atau jalankan per modul / spesifikasi:
```bash
# 1. Clean Architecture Use Case Unit Tests (Domain Business Rules)
php artisan test tests/Unit/UseCases/

# 2. Matriks Overlap Domain Logic Tests (5 skenario bentuk overlap murni tanpa HTTP/DB)
php artisan test tests/Unit/Domain/OverlapDetectionServiceTest.php

# 3. CRUD Ruang Meeting & Manajemen Fasilitas
php artisan test tests/Feature/RoomApiTest.php

# 4. Booking Lifecycle, Otorisasi Pemilik, & Validasi Input
php artisan test tests/Feature/BookingApiTest.php

# 5. Pencegahan Overlap di Lapisan Penyimpanan (Database Trigger MySQL/SQLite)
php artisan test tests/Feature/StorageLevelConstraintTest.php

# 6. Fitur Lanjutan: Recurring Booking, Buffer Time, Jam Operasional, & Search
php artisan test tests/Feature/EnhancementFeaturesTest.php

# 7. Antarmuka Web: Rendering, User Switcher, My Bookings, & Modal
php artisan test tests/Feature/MeetingRoomWebTest.php

# 8. Uji Konkurensi & Race Condition (2 proses paralel simultan via proc_open)
php artisan test tests/Feature/ConcurrencyTest.php
```

---

## Antarmuka Web (UI & Interaksi Pengguna)

Selain antarmuka REST API, aplikasi ini telah dilengkapi dengan tampilan antarmuka web yang lengkap, responsif, elegan, dan profesional (menggunakan font *Inter* dan palet warna netral *Zinc* tanpa elemen visual berlebih):

### 1. Halaman Ruang Meeting (`/rooms`)
- **Katalog Ruangan**: Menampilkan kartu ruang meeting dengan indikator kapasitas, jeda waktu (*buffer*), lokasi, status aktif/non-aktif, dan ringkasan jam operasional.
- **Filter**: Memfilter daftar ruangan berdasarkan kapasitas minimum dan status (Semua, Hanya Aktif, Non-Aktif).
- **Manajemen Ruang (CRUD)**:
  - **Tambah Ruang Baru**: Modal formulir pembuatan ruang dengan kapasitas, jeda buffer, dan status aktif.
  - **Edit Ruang**: Modal pembaruan informasi ruang, termasuk *toggle* status aktif/non-aktif melalui dropdown eksplisit.
  - **Atur Jam Operasional Dinamis**: Konfigurasi jam kerja fleksibel yang memungkinkan menambah dan menghapus hari (Senin s/d Minggu) secara dinamis, atau mengosongkannya agar beroperasi **24 Jam Penuh**.
  - **Hapus Ruang**: Modal dialog konfirmasi modern dan aman (menggantikan `confirm()` bawaan browser).

### 2. Halaman Jadwal & Reservasi Ruang (`/rooms/{id}`)
- **Form Pembuatan Booking**:
  - Pilihan mode **Sekali (Single)** vs **Berulang (Recurring)**.
  - Mode berulang mendukung frekuensi Harian (*Daily*) / Mingguan (*Weekly*) serta daftar pengecualian tanggal (*exception dates*).
  - Normalisasi waktu otomatis ke UTC di penyimpanan.
  - Prefill cerdas saat diarahkan dari hasil pencarian ruang kosong.
- **Visualizer Slot Terisi Langsung pada Form**: Menampilkan daftar slot jam yang telah dipesan pada ruangan tersebut secara *real-time* lengkap dengan nama penyelenggara, jam mulai-selesai, dan jeda buffer pembersihan.
- **Modal Peringatan Konflik Interaktif (HTTP 409 Conflict)**: Jika pengguna mengajukan jam yang berbenturan dengan reservasi lain atau masa buffer, sistem otomatis memunculkan modal pop-up interaktif yang membandingkan jadwal yang diajukan vs rincian booking yang bertabrakan.
- **Filter Tanggal**: Memfilter jadwal booking per hari spesifik (`?date=`) atau rentang tanggal (`?start_date=&end_date=`).
- **Otorisasi Pembatalan & Reschedule**:
  - Tombol aksi otomatis mendeteksi apakah pengguna yang aktif adalah pemilik booking.
  - Jika pemilik: tombol "Batalkan" dan "Reschedule" dapat digunakan dengan modal konfirmasi dan alasan.
  - Jika bukan pemilik: tombol terkunci dan menampilkan badge `Bukan Milik Anda`.

### 3. Pencarian Ruang Kosong (`/rooms/search`)
- Form pencarian mode Single maupun Recurring dengan kapasitas minimum yang dibutuhkan.
- **Algoritma Isolasi Harian Cerdas**: Mengevaluasi ketersediaan per jendela jam pada hari yang dipilih. Jika suatu hari memiliki booking pada jam lain, ruangan tetap dianggap tersedia dan muncul di hasil pencarian.
- Tombol satu klik "Pesan Ruang Ini Sekarang" yang otomatis mengisi rentang waktu ke form booking.

### 4. Halaman Reservasi Saya (`/my-bookings`)
- Menu khusus bagi user aktif untuk memantau semua reservasi yang pernah dibuatnya.
- **Tampilan Clean & Minimalis**: Kartu horizontal memanjang ke kanan (*full-width row*) per booking dengan tipografi rapi, bebas emoji, dan palet warna netral zinc.
- **Filter Status Cepat**: Tab filter instan untuk status Semua, Mendatang, Terkonfirmasi, dan Dibatalkan.
- **Aksi Cepat**: Tombol *Lihat Ruang*, *Riwayat Perubahan (Audit Trail Modal)*, *Reschedule (Modal)*, dan *Batalkan Booking (Modal Konfirmasi)*.

### 5. Switcher Identitas Pengguna (*Actor Switcher*)
- Dropdown di pojok kanan atas navbar untuk berganti identitas user secara instan (contoh: *Alice Margatroid* $\leftrightarrow$ *Bob Smith*).
- Memudahkan pengujian aturan otorisasi pembatalan dan hak kepemilikan tanpa perlu proses logout/login yang berbelit.

---

## Keputusan Desain & Alasannya (ADR)

### 1. Dual-Layer Overlap Guard (Aplikasi + Penyimpanan)
- **Keputusan**: Pencegahan jadwal ganda ditegakkan di dua tingkat:
  1. **Application / Service Layer**: Menggunakan *Pessimistic Locking* (`Room::lockForUpdate()`) di dalam database transaction, memastikan proses lain mengantre (*serialize*) saat reservasi sedang diproses.
  2. **Database Storage Layer**: Menggunakan trigger MySQL `BEFORE INSERT` dan `BEFORE UPDATE` yang melemparkan `SIGNAL SQLSTATE '45000'` (`DB_STORAGE_CONSTRAINT`).
- **Alasan**: Di sistem produksi, kode aplikasi bisa mengalami *bypass* (misal skrip migrasi, raw SQL dari developer/admin, atau microservice lain). Storage trigger menjamin secara fisik di level mesin database bahwa baris beririsan tidak dapat tersimpan.

### 2. Penyimpanan Waktu UTC Konsisten (*UTC-at-Rest*)
- **Keputusan**: Semua kolom `start_time` dan `end_time` di database disimpan dalam zona waktu acuan UTC. Konversi dilakukan di tepi sistem (*edge*):
  - Saat request masuk, waktu diparse bersama timezone asal lalu dikonversi ke UTC.
  - Saat response dikembalikan, sistem mengembalikan ISO 8601 UTC string (`start_time_utc`), serta atribut `start_time_local` jika klien menyertakan header/parameter timezone.
- **Alasan**: Mencegah ambigu Daylight Saving Time (DST) atau perbedaan offset antar cabang kantor/klien.

### 3. Identifikasi Pengguna via Header Sederhana (`X-User-Id`)
- **Keputusan**: Menggunakan header HTTP `X-User-Id: <user_id>` yang divalidasi oleh middleware `IdentifyUser` pada API, dan Session Switcher pada Web.
- **Alasan**: Sesuai instruksi spesifikasi teknis agar mekanisme identitas sederhana dan bebas bentuk asalkan mampu membedakan pemilik booking. Tidak membebani evaluasi dengan keharusan login/JWT token yang rumit, namun tetap menjamin validasi otorisasi pembatalan dan pencatatan riwayat (*audit trail*).

### 4. Format Error Semantik HTTP
- **Keputusan**: Memisahkan secara tegas HTTP `409 Conflict` dari `422 Unprocessable Content`.
  - `409 Conflict`: Khusus untuk tabrakan jadwal (`SCHEDULE_CONFLICT`).
  - `422 Unprocessable`: Untuk input yang secara semantik salah (contoh: `end_time <= start_time`, di luar jam operasional).
  - `403 Forbidden`: Saat user mencoba membatalkan atau mengubah jadwal booking milik user lain.

### 5. Arsitektur Berlapis Bersih (Clean Architecture & Dedicated Use Cases)
- **Keputusan**: Memisahkan logika bisnis aplikasi ke dalam kelas Use Case mandiri di `App\Domain\Booking\UseCases\` (`CreateBookingUseCase`, `CancelBookingUseCase`, `RescheduleBookingUseCase`, `SetOperatingHoursUseCase`, `UpdateRoomUseCase`).
- **Alasan**: Menghindari *fat controllers*, menjamin prinsip *Single Responsibility* (SRP), mempermudah pengujian unit terisolasi yang cepat (*pure domain testing*), dan memungkinkan logika bisnis yang sama digunakan kembali baik oleh HTTP API Controller, Web Controller, maupun CLI commands tanpa duplikasi kode.

### 6. Isolasi Ketersediaan Harian Cerdas pada Pencarian Berulang
- **Keputusan**: Pada pencarian ketersediaan berkala (`searchAvailable` mode recurring), sistem mengevaluasi ketersediaan ruang per irisan slot harian (`$date $start_time_of_day` s/d `$date $end_time_of_day`), bukan memperlakukan rentang awal hingga akhir sebagai satu blok waktu raksasa.
- **Alasan**: Menghindari *false positive* di mana pemesanan ruangan pada tanggal 4 jam 14:00 - 15:00 secara keliru menggagalkan pencarian ketersediaan jam 11:00 - 12:00 untuk tanggal 1 s/d 5.

---

## Logika & Matriks Pengujian Overlap

Dua interval waktu $[S_1, E_1]$ dan $[S_2, E_2]$ **beririsan (overlap)** jika dan hanya jika:
$$S_1 < E_2 \quad \text{AND} \quad E_1 > S_2$$

Jika memperhitungkan **Buffer Time** ($B$ menit), interval efektif booking eksisting dihitung sebagai $[S_{\text{existing}}, E_{\text{existing}} + B]$.

### Tabel Matriks Skenario Overlap:

| No | Skenario | Interval Eksisting | Interval Target | Hasil Evaluasi | Status HTTP |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | **Identik** | `10:00 - 11:00` | `10:00 - 11:00` | Overlap | `409 Conflict` |
| 2a | **Bersarang (Di dalam)** | `10:00 - 11:00` | `10:15 - 10:45` | Overlap | `409 Conflict` |
| 2b | **Bersarang (Melingkupi)**| `10:00 - 11:00` | `09:00 - 12:00` | Overlap | `409 Conflict` |
| 3 | **Memotong Awal** | `10:00 - 11:00` | `09:30 - 10:30` | Overlap | `409 Conflict` |
| 4 | **Memotong Akhir** | `10:00 - 11:00` | `10:30 - 11:30` | Overlap | `409 Conflict` |
| 5 | **Bersentuhan Ujung** | `10:00 - 11:00` | `11:00 - 12:00` | **Bebas (Tidak Overlap)** | `201 Created` |
| 6 | **Buffer 15 Menit** | `10:00 - 11:00` | `11:00 - 12:00` | Overlap (kena buffer) | `409 Conflict` |
| 7 | **Pasca Buffer 15M** | `10:00 - 11:00` | `11:15 - 12:15` | **Bebas (Tidak Overlap)** | `201 Created` |

---

## Pencegahan Race Condition & Concurrency

Ketika dua pengguna menekan tombol "Booking" secara bersamaan di milidetik yang sama pada slot yang identik:
1. Permintaan pertama mengeksekusi `Room::lockForUpdate()`, mengunci baris ruang terkait secara eksklusif (*row-level lock*).
2. Permintaan kedua yang tiba pada saat bersamaan akan menunggu (*wait for lock*) sampai transaksi pertama selesai.
3. Transaksi pertama berhasil memasukkan booking dan melakukan `COMMIT`.
4. Transaksi kedua memperoleh giliran kunci, menjalankan verifikasi tumpang tindih, menemukan booking yang baru saja di-commit oleh permintaan pertama, dan langsung melempar `ScheduleConflictException` (HTTP 409).
5. Hal ini dibuktikan secara otomatis pada unit/feature test `tests/Feature/ConcurrencyTest.php` yang menjalankan dua proses CLI latar belakang secara paralel.

---

## Dokumentasi Endpoint API & Contoh Request

### 1. Ruang Meeting (Rooms)

#### `GET /api/rooms`
Mendapatkan daftar semua ruang meeting.
- **Query Params**: `min_capacity` (integer), `is_active` (boolean).

#### `POST /api/rooms`
Membuat ruang meeting baru.
```json
{
  "name": "Ruang Semeru",
  "capacity": 20,
  "location": "Lantai 3, Sayap Timur",
  "buffer_minutes": 15,
  "is_active": true
}
```

#### `GET /api/rooms/available` *(Enhancement)*
Mencari ruang yang kosong pada rentang waktu dan kapasitas tertentu.
- **Query Params**:
  - `start_time`: `2026-09-15T09:00:00Z`
  - `end_time`: `2026-09-15T11:00:00Z`
  - `min_capacity`: `10`
  - `timezone`: `Asia/Jakarta`

#### `GET /api/rooms/{id}/occupied-slots`
Mendapatkan jadwal slot terisi pada ruangan untuk tanggal tertentu beserta masa buffernya.
- **Query Params**: `date` (`YYYY-MM-DD`)

#### `POST /api/rooms/{id}/operating-hours` *(Enhancement)*
Mengonfigurasi jam operasional ruang.
```json
{
  "hours": [
    {"day_of_week": 1, "open_time": "08:00:00", "close_time": "18:00:00"},
    {"day_of_week": 2, "open_time": "08:00:00", "close_time": "18:00:00"},
    {"day_of_week": 3, "open_time": "08:00:00", "close_time": "18:00:00"},
    {"day_of_week": 4, "open_time": "08:00:00", "close_time": "18:00:00"},
    {"day_of_week": 5, "open_time": "08:00:00", "close_time": "18:00:00"}
  ]
}
```

---

### 2. Reservasi (Bookings)

#### `GET /api/bookings/my`
Mendapatkan daftar reservasi milik pengguna aktif.
- **Headers Wajib**: `X-User-Id: 1`
- **Query Params**: `status` (`all` / `upcoming` / `confirmed` / `cancelled`)

#### `POST /api/bookings`
Membuat reservasi baru.
- **Headers Wajib**: `X-User-Id: 1`
- **Body Single Booking**:
  ```json
  {
    "room_id": 1,
    "title": "Weekly Sprint Planning",
    "start_time": "2026-09-15T09:00:00Z",
    "end_time": "2026-09-15T10:30:00Z",
    "timezone": "UTC"
  }
  ```
- **Body Recurring Booking (Enhancement)**:
  ```json
  {
    "room_id": 1,
    "title": "Daily Standup Series",
    "is_recurring": true,
    "frequency": "daily",
    "start_date": "2026-09-15",
    "end_date": "2026-09-19",
    "start_time_of_day": "09:00:00",
    "end_time_of_day": "09:30:00",
    "exception_dates": ["2026-09-17"]
  }
  ```

#### `GET /api/rooms/{id}/bookings`
Mendapatkan jadwal booking untuk suatu ruangan.
- **Query Params**:
  - `date`: `2026-09-15` (filter 1 hari penuh)
  - `start_date` & `end_date`: `2026-09-15` s/d `2026-09-20`
  - `timezone`: `Asia/Jakarta`

#### `DELETE /api/bookings/{id}`
Membatalkan booking (Otorisasi: hanya pemilik booking).
- **Headers Wajib**: `X-User-Id: 1`
- **Body Opsional**:
  ```json
  {
    "reason": "Klien membatalkan jadwal meeting"
  }
  ```

#### `POST /api/bookings/{id}/reschedule` *(Enhancement)*
Mengubah waktu jadwal booking.
- **Headers Wajib**: `X-User-Id: 1`
- **Body**:
  ```json
  {
    "start_time": "2026-09-15T14:00:00Z",
    "end_time": "2026-09-15T15:00:00Z",
    "reason": "Diundur ke sesi siang"
  }
  ```

#### `GET /api/bookings/{id}/history` *(Enhancement)*
Melihat audit trail lengkap dari suatu booking (pembuatan, perubahan jadwal, dan pembatalan).

---

## Batasan yang Disadari (Known Limitations)

1. **Storage Constraint di MySQL vs PostgreSQL**:
   - Di PostgreSQL, terdapat fitur *Exclusion Constraint* bawaan (`EXCLUDE USING gist`) yang merupakan solusi index spasial native untuk rentang waktu.
   - Karena project ini berjalan di MySQL, MySQL tidak memiliki *exclusion constraints* native untuk interval rentang waktu. Oleh karena itu, batasan penyimpanan diimplementasikan menggunakan **Database Triggers** (`BEFORE INSERT` & `BEFORE UPDATE`) dengan `SIGNAL SQLSTATE '45000'`, dikombinasikan dengan *Pessimistic Locking* di lapisan aplikasi.
2. **Skalabilitas Multi-Node Database**:
   - Jika sistem di-deploy pada arsitektur cluster multi-master atau distributed SQL (seperti CockroachDB / Galera Cluster), *pessimistic lock* lokal (`lockForUpdate`) membutuhkan *Distributed Lock Manager* (misalnya Redis Redlock) untuk menjamin latensi rendah antar node.
3. **Penyimpanan Recurring Bookings**:
   - Pola booking berulang saat ini diekspansi menjadi baris-baris fisik individual di tabel `bookings` yang direferensikan ke `recurrence_rules`. Pendekatan ini memudahkan query tumpang tindih dan pengubahan jadwal satu kejadian (*single instance override*), tetapi jika rentang pengulangan sangat panjang (misal 5 tahun ke depan), tabel akan terisi banyak baris di awal.
4. **Daylight Saving Time (DST) Jangka Panjang**:
   - Meskipun sistem menggunakan *UTC-at-rest*, jika suatu recurring booking dibuat untuk zona waktu yang memiliki aturan Daylight Saving Time musiman (misalnya New York / London), waktu jam lokal harian berpotensi bergeser 1 jam di UTC jika tidak menggunakan library rule DST khusus seperti `Intl/ICU` timezone rule expansion.
5. **Autentikasi Sederhana**:
   - Sesuai dengan instruksi soal yang membebaskan bentuk identitas pengguna (cukup untuk membedakan kepemilikan booking), sistem belum menggunakan password hash atau token JWT bearer, melainkan header `X-User-Id` dan dropdown switcher sesi web.

---

## Bagian yang Belum Selesai (Unfinished Parts & Roadmap)

Bagian-bagian berikut secara sadar belum diimplementasikan karena berada di luar batasan waktu dan lingkup inti penilaian teknis, namun telah disiapkan arsitekturnya untuk pengembangan tahap berikutnya:

1. **Autentikasi Lengkap & Manajemen Pengguna (OAuth2 / JWT / SSO)**:
   - *Status*: Belum diimplementasikan.
   - *Penjelasan*: Saat ini identitas pengguna disimulasikan menggunakan header `X-User-Id` (API) dan session user switcher (Web) agar penguji/evaluator dapat berpindah akun (*Alice* $\leftrightarrow$ *Bob*) secara instan tanpa hambatan form login/password. Fitur login, register, reset password, dan JWT token auth masuk dalam rencana tahap berikutnya.
2. **Sinkronisasi Kalender Eksternal (Google Calendar & Microsoft Outlook)**:
   - *Status*: Belum diimplementasikan.
   - *Penjelasan*: Integrasi dua arah dengan Google Calendar API atau Microsoft Graph API, serta generator berkas `.ics` (iCalendar file download) untuk ditambahkan langsung ke kalender email pengguna.
3. **Sistem Notifikasi & Reminder Otomatis (Email / Slack / Webhook / WhatsApp)**:
   - *Status*: Belum diimplementasikan.
   - *Penjelasan*: Pekerja latar belakang (*background queue worker*) untuk mengirim email konfirmasi pemesanan, tautan reschedule, serta pesan pengingat meeting (*15 minutes before meeting starts*).
4. **Inventaris Fasilitas & Resource Tambahan Ruangan**:
   - *Status*: Belum diimplementasikan.
   - *Penjelasan*: Pemesanan fasilitas tambahan di dalam ruang meeting (contoh: proyektor 4K, mikrofon nirkabel, webcam konferensi, snack/catering) yang kuotanya ikut terikat pada jadwal meeting.
5. **Dukungan PostgreSQL Native Exclusion Constraint**:
   - *Status*: Belum diimplementasikan (saat ini menggunakan MySQL Storage Trigger).
   - *Penjelasan*: Opsi migrasi skema database alternatif untuk PostgreSQL dengan ekstensi `btree_gist` dan constraint `EXCLUDE USING gist (room_id WITH =, time_range WITH &&)`.
