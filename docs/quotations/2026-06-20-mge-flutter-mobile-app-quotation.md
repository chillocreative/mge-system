# SEBUT HARGA / QUOTATION
## Pembangunan Aplikasi Mobile (Flutter) — MGE-PMS Companion App

---

**Daripada (Vendor):**
Chillo Creative
[Alamat / No. Pendaftaran / Telefon / Emel]

**Kepada (Client):**
MGE Engineering
[Alamat / Pegawai Bertanggungjawab]

| | |
|---|---|
| **No. Sebut Harga** | CC-MGE-2026-001 |
| **Tarikh** | 20 Jun 2026 |
| **Sah Sehingga** | 20 Julai 2026 (30 hari) |
| **Mata Wang** | Ringgit Malaysia (MYR) |

---

## 1. Pengenalan & Skop Projek

Chillo Creative dengan sukacitanya mengemukakan sebut harga untuk pembangunan **aplikasi mobile
rentas-platform (iOS & Android)** menggunakan rangka kerja **Flutter**, sebagai pelengkap (companion)
kepada sistem pengurusan projek sedia ada **MGE-PMS**.

Aplikasi mobile ini akan disambungkan terus kepada backend Laravel (API) sedia ada, membolehkan
kakitangan lapangan dan pengurusan mengakses fungsi teras secara mudah alih, termasuk **check-in
kehadiran di tapak dengan GPS & foto**, serta notifikasi push masa nyata.

**Modul Versi 1 (v1):**
1. **Log Masuk & Keselamatan** — autentikasi selamat (Sanctum token), simpanan kelayakan terenkripsi,
   sesi automatik.
2. **Dashboard** — papar statistik mengikut peranan pengguna (Admin, Finance, Projects, Employee).
3. **Notifikasi** — push notification masa nyata (Firebase Cloud Messaging) + notifikasi dalam-app.
4. **Projek** — senarai & butiran projek, milestone, log tapak, paparan dokumen.
5. **Tugasan (Tasks)** — senarai & butiran tugasan, komen, muat naik lampiran.
6. **Kehadiran (Attendance)** — check-in/out di tapak dengan penanda lokasi GPS, foto bukti, dan
   baris-gilir luar-talian (offline queue) bila tiada talian.

> *Modul Safety, Environmental, Chat, Email, Finance dan Payroll TIDAK termasuk dalam v1 dan boleh
> ditambah sebagai fasa akan datang.*

---

## 2. Skop Kerja Terperinci

| # | Komponen | Penerangan |
|---|----------|-----------|
| 1 | Reka bentuk UI/UX | Reka bentuk antara muka mobile, design system, ikon, aliran skrin |
| 2 | Asas aplikasi | Seni bina (state management), lapisan integrasi API, persekitaran dev/staging/prod |
| 3 | Autentikasi | Log masuk, simpanan token selamat, auto-refresh sesi, log keluar |
| 4 | Dashboard | Skrin statistik dinamik ikut peranan + carta ringkas |
| 5 | Notifikasi push | Integrasi FCM, pendaftaran token peranti, notifikasi masa nyata (Echo/Pusher) |
| 6 | Modul Projek | Senarai, carian, butiran, milestone, log tapak, pelihat dokumen |
| 7 | Modul Tugasan | Senarai, butiran, komen, muat naik/papar lampiran |
| 8 | Modul Kehadiran | Check-in/out GPS, geofence, kamera foto, offline sync |
| 9 | Penyesuaian API | Endpoint mobile, simpanan token FCM, pengendalian fail untuk mobile |
| 10 | QA & Ujian | Ujian fungsi, peranti merentas saiz, pembetulan pepijat |
| 11 | Penyebaran | Penerbitan ke Google Play Store & Apple App Store, dokumentasi & serahan |

---

## 3. Pelaburan (Harga)

Harga dipecahkan mengikut fasa penghantaran:

| Fasa | Penghantaran | Harga (RM) |
|------|--------------|-----------:|
| Fasa 1 | Discovery & Reka bentuk UI/UX | 6,000 |
| Fasa 2 | Asas Aplikasi (seni bina, autentikasi, integrasi API) | 9,000 |
| Fasa 3 | Dashboard + Notifikasi Push (FCM + masa nyata) | 9,500 |
| Fasa 4 | Modul Projek + Tugasan | 16,000 |
| Fasa 5 | Modul Kehadiran (GPS + kamera + offline) | 10,500 |
| Fasa 6 | QA, Ujian & Penyebaran (Play Store + App Store) | 7,000 |
| | **JUMLAH KECIL** | **58,000** |

> *Harga di atas TIDAK termasuk SST 8% (jika berkenaan). Sekiranya Chillo Creative berdaftar SST,
> cukai perkhidmatan 8% (RM4,640) akan dikenakan, menjadikan jumlah keseluruhan **RM62,640**.*

### Add-On Pilihan (Tidak Wajib)
| Item | Harga (RM) |
|------|-----------:|
| Sokongan & penyelenggaraan 3 bulan selepas pelancaran | 4,500 |
| Retainer penyelenggaraan tahunan (12 bulan) | 9,600 / tahun |
| Akaun Apple Developer (USD99/tahun) & Google Play (USD25 sekali) | Tuntut balik mengikut kos |

---

## 4. Tempoh Pembinaan

Anggaran tempoh keseluruhan: **lebih kurang 14 minggu (~3.5 bulan)** bermula selepas deposit
diterima dan akses backend diberikan.

| Fasa | Tempoh |
|------|--------|
| Fasa 1 — Discovery & Reka bentuk | 2 minggu |
| Fasa 2 — Asas Aplikasi | 2 minggu |
| Fasa 3 — Dashboard + Notifikasi | 2 minggu |
| Fasa 4 — Projek + Tugasan | 3 minggu |
| Fasa 5 — Kehadiran | 2.5 minggu |
| Fasa 6 — QA & Penyebaran | 2 minggu |
| **Jumlah (dengan sebahagian kerja serentak)** | **~14 minggu** |

---

## 5. Terma Pembayaran

| Peringkat | Peratus | Bila |
|-----------|--------:|------|
| Deposit pendahuluan | 30% | Semasa sah-terima sebut harga |
| Bayaran pertengahan | 30% | Selepas siap Fasa 3 |
| Bayaran UAT | 30% | Selepas ujian penerimaan pengguna (UAT) lulus |
| Bayaran akhir | 10% | Semasa go-live / serahan akhir |

---

## 6. Andaian & Pengecualian

- Backend Laravel (MGE-PMS) sedia ada dan API berfungsi; akses pembangunan akan diberikan.
- MGE menyediakan akaun Apple Developer & Google Play (atau membenarkan tuntutan balik kos).
- Kandungan/data ujian disediakan oleh MGE.
- Skop di luar 6 modul v1 (Safety, Chat, Finance, Payroll, dll) adalah fasa berasingan.
- Perubahan skop selepas pengesahan reka bentuk tertakluk kepada change request.

---

## 7. Penerimaan

Sila tandatangan di bawah untuk mengesahkan penerimaan sebut harga ini.

| Pihak | Nama | Tandatangan | Tarikh |
|-------|------|-------------|--------|
| Chillo Creative | | | |
| MGE Engineering | | | |

---

*Sebut harga ini sah selama 30 hari dari tarikh dikeluarkan. Terima kasih atas peluang ini.*
