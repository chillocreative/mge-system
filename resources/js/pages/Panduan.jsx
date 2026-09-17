import { useState, useMemo, useEffect, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';

/**
 * Panduan Pengguna (User Manual) — Ciri sokongan.
 *
 * Halaman dokumentasi awam (tanpa perlu log masuk) yang menerangkan cara
 * menggunakan setiap modul MGE-PMS dalam bahasa yang mudah. Dipautkan dari
 * halaman log masuk. Kandungan disusun sebagai data supaya senang dikemas kini.
 */

const SISTEM_SECTIONS = [
    {
        id: 'bermula',
        title: 'Bermula',
        icon: '🚀',
        intro: 'Cara log masuk dan mengenali skrin utama sistem.',
        groups: [
            {
                heading: 'Log masuk',
                steps: [
                    'Buka pelayar dan pergi ke app.mge-eng.com.',
                    'Masukkan emel dan kata laluan yang diberikan oleh admin.',
                    'Klik butang "Sign In to Portal".',
                    'Jika akaun baru didaftarkan, ia perlu diluluskan oleh admin dahulu sebelum boleh log masuk.',
                ],
            },
            {
                heading: 'Skrin utama (Dashboard)',
                steps: [
                    'Selepas log masuk, anda akan lihat Dashboard — ringkasan tugasan, projek, staf dan kewangan.',
                    'Menu di sebelah kiri mengandungi semua modul. Klik untuk buka.',
                    'Ikon loceng di atas kanan menunjukkan notifikasi. Nama anda di penjuru kanan untuk tetapan dan log keluar.',
                ],
            },
        ],
    },
    {
        id: 'projek',
        title: 'Projek',
        icon: '🏗️',
        intro: 'Uruskan projek pembinaan — milestone, log tapak, tapak (sites), dokumen dan surat-menyurat.',
        groups: [
            {
                heading: 'Cipta projek baru',
                steps: [
                    'Klik "Projects" > "All Projects" di menu kiri.',
                    'Klik butang "New Project".',
                    'Isi nama projek, kod, klien, tarikh mula/tamat dan bajet.',
                    'Klik simpan. Projek akan muncul dalam senarai.',
                ],
            },
            {
                heading: 'Tab dalam projek',
                steps: [
                    'Overview — ringkasan projek dan pasukan.',
                    'Tasks — senarai tugasan projek, boleh tambah/edit/padam terus.',
                    'Milestones — pencapaian penting projek.',
                    'Timeline — garis masa milestone dan acara kalendar (tugasan tidak dipaparkan di sini).',
                    'Site Logs — log kerja harian di tapak.',
                    'Sites — senarai tapak/zon dalam projek (untuk projek berbilang tapak).',
                    'Documents — fail dan dokumen projek.',
                    'Calendar — acara projek.',
                    'Discussions — perbincangan pasukan.',
                ],
            },
            {
                heading: 'Tugasan dalam projek (Tasks tab)',
                steps: [
                    'Buka projek, klik tab "Tasks", klik "Add Task".',
                    'Isi tajuk, keterangan, keutamaan (priority), tarikh akhir dan boleh tetapkan lebih dari satu staf (multiple assignees).',
                    'Klik ikon pensel untuk edit, atau ikon tong sampah untuk padam (sistem akan minta pengesahan dahulu).',
                    'Tukar status terus dari dropdown pada setiap baris: Pending, In progress, In review, Completed atau Cancelled.',
                    'Tag keutamaan (priority) dipaparkan di sebelah status untuk senang dikenal pasti.',
                ],
            },
            {
                heading: 'Tapak (Sites) — projek berbilang lokasi',
                steps: [
                    'Buka projek, klik tab "Sites".',
                    'Klik "Add site", isi nama tapak (cth: "Tapak A"), kod dan alamat.',
                    'Selepas tapak dicipta, ia akan muncul sebagai pilihan bila anda buat Log Tapak, HIRARC, Permit Kerja atau surat-menyurat.',
                    'Tapak adalah pilihan — jika tiada tapak, sistem tetap guna medan lokasi teks biasa.',
                ],
            },
            {
                heading: 'Log tapak harian (Site Log)',
                steps: [
                    'Buka projek, klik tab "Site Logs" > isi log baru.',
                    'Rekod bilangan pekerja (Workers), Weather Times — catat setiap kali cuaca berubah (rekod ini penting sebagai bukti kelewatan akibat cuaca).',
                    'Isi Machinery yang digunakan; setiap baris jentera boleh dipautkan (optional) kepada aset berdaftar — pautan ini akan terus menetapkan (auto-assign) aset tersebut kepada projek dalam Assets > Machineries.',
                    'Isi Work performed, Issues dan Safety notes.',
                    'Di bahagian Attachments, klik "Choose files" untuk lampirkan gambar/dokumen — ia dimuat naik apabila log disimpan.',
                    'Butang "Monthly Report" jana PDF laporan bulanan; butang "Machinery" jana laporan penggunaan jentera bulanan.',
                    'Log boleh diedit dalam tempoh yang ditetapkan; selepas itu ia dikunci.',
                ],
            },
            {
                heading: 'Acara projek (Calendar tab)',
                steps: [
                    'Buka projek, klik tab "Calendar", klik "New Event".',
                    'Isi tajuk, jenis acara, tarikh/masa mula & tamat, lokasi dan keterangan.',
                    'Tambah Attendees (ahli projek) — mereka akan terima notifikasi dalam sistem DAN jemputan emel.',
                    'Jika acara diedit dan attendee baru ditambah, hanya attendee baru sahaja yang dimaklumkan.',
                ],
            },
            {
                heading: 'Dokumen projek (Documents tab)',
                steps: [
                    'Dokumen boleh ditapis mengikut kategori: Monthly Report, Minute Meeting dan Progress Tracking (selain kategori am seperti Drawing, Contract, Permit dan lain-lain).',
                ],
            },
        ],
    },
    {
        id: 'kontrak',
        title: 'Kontrak (Contracts)',
        icon: '📑',
        intro: 'Urus kontrak projek, dokumen, lukisan dan Bill of Quantity (BQ).',
        groups: [
            {
                heading: 'Senarai & cipta kontrak',
                steps: [
                    'Klik "Projects" > "Contracts".',
                    'Klik tajuk kontrak (pautan) atau ikon mata untuk buka halaman butiran.',
                    'Klik "New Contract" untuk tambah kontrak baru.',
                    'Isi tajuk, no. kontrak, nilai, tarikh, status dan PIC (boleh tambah lebih dari satu PIC).',
                ],
            },
            {
                heading: 'Butiran kontrak — Documents',
                steps: [
                    'Buka kontrak, klik tab "Documents".',
                    'Muat naik dokumen; klik ikon mata untuk lihat terus (inline) atau muat turun.',
                    'Dokumen boleh dipadam jika tidak diperlukan lagi.',
                ],
            },
            {
                heading: 'Butiran kontrak — Drawings',
                steps: [
                    'Buka kontrak, klik tab "Drawings".',
                    'Muat naik lukisan secara pukal atau ikut folder (bulk/folder upload).',
                    'Lihat atau muat turun lukisan yang telah dimuat naik.',
                ],
            },
            {
                heading: 'Butiran kontrak — Bill of Quantity (BQ)',
                steps: [
                    'Buka kontrak, klik tab "Bill of Quantity (BQ)".',
                    'Setiap kontrak hanya ada SATU dokumen BQ (format pdf/xls/xlsx/doc/docx). Klik "Upload BQ" untuk muat naik.',
                    'Klik "View BQ" untuk buka dokumen dalam tab baru.',
                    'Klik "Replace" untuk ganti dengan fail baru, atau "Remove" untuk buang BQ sedia ada.',
                ],
            },
        ],
    },
    {
        id: 'surat',
        title: 'Surat-menyurat (Correspondence)',
        icon: '✉️',
        intro: 'Rekod NCR, RFA, RFI dan surat rasmi projek, lengkap dengan sejarah dan PDF.',
        groups: [
            {
                heading: 'Cipta rekod surat',
                steps: [
                    'Klik "Projects" > "Correspondence".',
                    'Klik "New" / tambah, pilih projek, jenis (NCR/RFA/RFI dll), tajuk dan tarikh.',
                    'Pilih tapak (jika ada) dan lampirkan fail sokongan.',
                ],
            },
            {
                heading: 'Aliran kerja & sejarah',
                steps: [
                    'Klik ikon jam (Workflow & history) pada baris surat untuk buka panel aliran kerja.',
                    'Hand over — serahkan surat kepada pihak lain (klien, perunding, kontraktor).',
                    'Tambah nota, tukar status, dan lihat sejarah penuh siapa memegang surat dan bila.',
                    'Status yang boleh dipilih: Open, Pending, Closed, Decline, Forwarded atau Others (jika Others, isi keterangan status sendiri).',
                    'Tutup surat — perlu rujukan penutup DAN sekurang-kurangnya satu dokumen dilampirkan; sistem turut rekod tarikh tutup untuk klien dan perunding secara berasingan.',
                    'Rekod yang sudah ditutup (closed) dikunci dan tidak boleh diedit lagi.',
                    'Klik ikon muat turun untuk jana PDF surat (butiran + sejarah penuh).',
                ],
            },
        ],
    },
    {
        id: 'tugasan',
        title: 'Tugasan (Tasks)',
        icon: '✅',
        intro: 'Agih dan jejak tugasan kepada staf.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Klik "Tasks" di menu kiri (atau tab Tasks dalam projek).',
                    'Cipta tugasan: tajuk, keterangan, keutamaan, tarikh akhir dan tetapkan kepada staf.',
                    'Tambah komen dan lampiran pada tugasan.',
                    'Kemas kini status apabila tugasan siap.',
                ],
            },
        ],
    },
    {
        id: 'klien',
        title: 'Klien (Clients)',
        icon: '🤝',
        intro: 'Simpan maklumat klien dan syarikat.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Klik "Clients" di menu kiri.',
                    'Tambah klien baru: nama syarikat, orang untuk dihubungi, emel dan telefon.',
                    'Klien boleh dipilih semasa mencipta projek.',
                ],
            },
        ],
    },
    {
        id: 'kewangan',
        title: 'Kewangan (Finance)',
        icon: '💰',
        intro: 'Invois, perbelanjaan dan pemantauan bajet.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Klik "Finance" di menu kiri.',
                    'Overview — ringkasan kewangan keseluruhan.',
                    'Invoices — cipta dan jejak invois; muat turun PDF.',
                    'Expenses — rekod perbelanjaan.',
                    'Monthly Summary & Budget vs Actual — bandingkan bajet dengan perbelanjaan sebenar.',
                ],
            },
        ],
    },
    {
        id: 'hr',
        title: 'HR (Sumber Manusia)',
        icon: '👥',
        intro: 'Rekod staf, cuti, kehadiran, gaji, latihan dan memo.',
        groups: [
            {
                heading: 'Staf',
                steps: [
                    'Klik "HR" > "Staff".',
                    'Tambah/edit rekod pekerja: no. pekerja, jabatan, jawatan, tarikh mula kerja.',
                    'Buka rekod staf untuk lihat projek yang mereka terlibat dan baki cuti.',
                ],
            },
            {
                heading: 'Cuti (Leave)',
                steps: [
                    'Klik "HR" > "Leave" untuk mohon cuti (jenis cuti, tarikh mula & tamat).',
                    'Lihat "Leave Balances" untuk baki cuti tahunan, sakit dan lain-lain.',
                    'Admin/HR meluluskan atau menolak permohonan di skrin kelulusan.',
                    'Cuti Umum (Public Holidays) diuruskan oleh HR dalam modul cuti.',
                ],
            },
            {
                heading: 'Kehadiran & Gaji (Attendance & Payroll)',
                steps: [
                    'Muat naik data kehadiran bulanan.',
                    'Jana gaji, semak dan luluskan.',
                    'Slip gaji dan borang EA boleh dijana sebagai PDF.',
                ],
            },
            {
                heading: 'Latihan, Memo & Kalendar',
                steps: [
                    'Training — rekod dan permohonan latihan staf.',
                    'Memo — memo dalaman dengan lampiran dan notifikasi.',
                    'Calendar — acara syarikat dan cuti dipaparkan bersama.',
                ],
            },
        ],
    },
    {
        id: 'keselamatan',
        title: 'Keselamatan (Safety & OSHA)',
        icon: '🦺',
        intro: 'Insiden, hazard, HIRARC, permit kerja, statistik keselamatan dan mesyuarat.',
        groups: [
            {
                heading: 'Insiden & Hazard',
                steps: [
                    'Klik "Safety" di menu kiri.',
                    'Tab Incidents — laporkan insiden (tarikh, lokasi, keterangan, keterukan, hari hilang kerja).',
                    'Tab Hazards — laporkan bahaya yang dikenal pasti dan tindakan pembetulan.',
                ],
            },
            {
                heading: 'HIRARC (Penilaian Risiko)',
                steps: [
                    'Dari halaman Safety, klik butang "HIRARC".',
                    'Klik "New HIRARC", isi tajuk, proses dan projek/tapak.',
                    'Tambah setiap bahaya: kebarangkalian (1–5) dan keterukan (1–5).',
                    'Sistem kira tahap risiko automatik (Rendah/Sederhana/Tinggi/Kritikal) — nilai dikira oleh sistem, bukan ditaip.',
                ],
            },
            {
                heading: 'Permit Kerja (Permit To Work)',
                steps: [
                    'Dari halaman Safety, klik butang "Permits".',
                    'Cipta permit: jenis kerja (hot work, ruang terkurung, kerja di ketinggian dll), tempoh sah dan langkah keselamatan.',
                    '"Save draft" untuk simpan, atau "Save & submit" untuk hantar untuk kelulusan.',
                    'Pihak berkuasa (safety.manage) meluluskan/menolak. Permit yang lewat tempoh dipaparkan sebagai "expired" secara automatik.',
                ],
            },
            {
                heading: 'Statistik Keselamatan',
                steps: [
                    'Dari halaman Safety, klik butang "Statistics".',
                    'Pilih tahun (dan projek jika perlu).',
                    'Masukkan jam bekerja (man-hours) setiap bulan.',
                    'Sistem kira LTIFR dan kadar keterukan (severity rate) automatik.',
                ],
            },
            {
                heading: 'Mesyuarat Toolbox & Checklist',
                steps: [
                    'Tab Meetings — rekod mesyuarat toolbox dan kehadiran.',
                    'Tab Checklists — senarai semak pematuhan (OSHA, PPE, perancah dll).',
                ],
            },
        ],
    },
    {
        id: 'alam',
        title: 'Alam Sekitar (Environmental)',
        icon: '🌱',
        intro: 'Rekod sisa, pemeriksaan tapak dan audit alam sekitar.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Klik "Environmental" di menu kiri.',
                    'Rekod sisa, pemeriksaan tapak dan audit alam sekitar.',
                    'Jana laporan sebagai PDF.',
                ],
            },
        ],
    },
    {
        id: 'aset',
        title: 'Aset (Assets)',
        icon: '🚜',
        intro: 'Kenderaan, jentera, inventori dan penyelenggaraan.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Klik "Assets" > "Machineries" di menu kiri.',
                    'Senarai memaparkan Registration, Serial No, Make/Model, Type, Assigned To Project dan Status.',
                    'Klik "New Machinery" untuk daftar kenderaan/jentera baru; medan "Assigned To Project" untuk pilih projek dalam sistem.',
                    'Buka rekod jentera untuk lihat sejarah Project Assignment dan dokumen (road tax, insurance, permit).',
                    'Inventory — stok dan barangan.',
                    'Maintenance — jadual dan rekod penyelenggaraan.',
                ],
            },
        ],
    },
    {
        id: 'lain',
        title: 'Mesyuarat, Chat & Notifikasi',
        icon: '💬',
        intro: 'Komunikasi dan peringatan dalam sistem.',
        groups: [
            {
                heading: 'Cara guna',
                steps: [
                    'Meetings — jadual mesyuarat, agenda dan minit.',
                    'Chat — mesej peribadi dan kumpulan secara langsung.',
                    'Notifications — peringatan automatik (loceng di atas kanan). Notifikasi juga dihantar secara emel secara automatik (default) untuk kelulusan cuti, penetapan tugasan, jemputan kalendar, slip gaji dan reset kata laluan.',
                    'Admin boleh konfigurasi penghantaran emel di "Settings" > tab "Email (SMTP)": isi host, port, username dan password SMTP, klik "Send Test" untuk uji, kemudian tandakan "Enable email sending (system-wide)" dan klik "Save SMTP Settings".',
                ],
            },
        ],
    },
    {
        id: 'akses',
        title: 'Pengguna & Akses',
        icon: '🔐',
        intro: 'Kelulusan pendaftaran, peranan dan kebenaran.',
        groups: [
            {
                heading: 'Cara guna (Admin sahaja)',
                steps: [
                    'Users — luluskan atau tolak pendaftaran akaun baru.',
                    'User Access — tetapkan peranan (Admin & HR, Finances & HR, Projects, Employee) dan kebenaran.',
                    'Settings > tab "Email (SMTP)" — konfigurasi penghantaran emel sistem (lihat bahagian Notifikasi & Emel).',
                    'Pekerja yang berhenti kerja — tukar status kepada tidak aktif; log masuk mereka akan disekat automatik.',
                ],
            },
            {
                heading: 'Tetapan akaun sendiri (semua pengguna)',
                steps: [
                    'Klik nama anda di penjuru kanan atas untuk ke halaman profil dan tukar kata laluan.',
                    'Terlupa kata laluan? Di halaman log masuk, klik pautan "Forgot Password" untuk reset melalui emel.',
                ],
            },
        ],
    },
];

const WEB_SECTIONS = [
    {
        id: 'web-mula',
        title: 'Bermula',
        icon: '🌐',
        intro: 'Log masuk ke CMS laman web dan mengenali menu.',
        groups: [
            {
                heading: 'Log masuk ke CMS',
                steps: [
                    'Buka pelayar dan pergi ke https://mge-eng.com/cms/wp-admin (log masuk WordPress biasa).',
                    'Masukkan username/emel dan kata laluan WordPress anda, klik butang log masuk.',
                    'Pautan "Staff Login" di laman awam (mge-eng.com) adalah untuk log masuk ke MGE-PMS (app.mge-eng.com), BUKAN untuk CMS laman web — jangan keliru dengan dua sistem ini.',
                ],
            },
            {
                heading: 'Mengenali menu kiri',
                steps: [
                    'Menu kiri CMS mengandungi: Services, Projects, Certificates, Activities, Gallery dan Media.',
                    'Services, Projects, Certificates dan Activities adalah kandungan utama yang dipaparkan di laman web awam.',
                    'Gallery sudah didaftarkan dalam CMS tetapi belum digunakan di laman web lagi.',
                    'Pages dan Posts turut wujud dalam WordPress tetapi TIDAK digunakan oleh laman web — abaikan kedua-dua menu ini, sebarang perubahan padanya tidak akan muncul di laman web.',
                ],
            },
        ],
    },
    {
        id: 'web-perkhidmatan',
        title: 'Perkhidmatan (Services)',
        icon: '🧩',
        intro: 'Urus senarai perkhidmatan syarikat yang dipaparkan di laman web.',
        groups: [
            {
                heading: 'Tambah/edit perkhidmatan',
                steps: [
                    'Klik "Services" > "Add New" (atau buka rekod sedia ada untuk edit).',
                    'Isi tajuk dan kandungan utama (main content) dalam editor.',
                    'Muat naik gambar utama (featured image/thumbnail).',
                    'Isi excerpt (ringkasan pendek) dan pilih Service Categories yang berkaitan.',
                ],
            },
            {
                heading: 'Medan ACF (wajib diisi)',
                steps: [
                    'Icon Name — pilih dari senarai (wajib).',
                    'Short Description — teks pendek, maksimum 200 aksara (wajib).',
                    'Key Features — repeater; klik "Add row" untuk tambah setiap ciri.',
                    'Display Order — nombor; nombor lebih kecil dipaparkan dahulu.',
                    'Medan wajib yang dibiarkan kosong akan kelihatan kosong di laman web — pastikan semua diisi sebelum terbitkan.',
                ],
            },
            {
                heading: 'Di mana ia dipaparkan',
                steps: [
                    'Perkhidmatan dipaparkan di halaman Home, halaman Services dan halaman butiran (detail page) perkhidmatan berkenaan.',
                ],
            },
        ],
    },
    {
        id: 'web-projek',
        title: 'Projek (Projects)',
        icon: '🏢',
        intro: 'Urus senarai projek yang dipaparkan di laman web.',
        groups: [
            {
                heading: 'Tambah/edit projek',
                steps: [
                    'Klik "Projects" > "Add New" (atau buka rekod sedia ada untuk edit).',
                    'Isi tajuk, kandungan (content) dan muat naik gambar utama (featured image).',
                    'Pilih Project Categories yang berkaitan.',
                ],
            },
            {
                heading: 'Medan ACF (wajib diisi)',
                steps: [
                    'Location — wajib.',
                    'Start Date dan Completion Date — Completion Date wajib.',
                    'Project Owner dan Project Value (RM).',
                    'Scope of Work — keterangan skop kerja.',
                    'Photo 1 hingga Photo 5 — muat naik gambar projek.',
                    'Status — pilih salah satu: "Current-In Progress", "Current-Upcoming" atau "Previous-Completed".',
                    'Featured Project — tandakan "true" jika mahu projek ini dipaparkan di halaman Home.',
                ],
            },
            {
                heading: 'Di mana ia dipaparkan',
                steps: [
                    'Halaman Projects memaparkan projek secara berasingan: Current (In Progress/Upcoming) dan Previous (Completed), mengikut medan Status.',
                    'Projek yang ditanda Featured Project akan muncul di halaman Home.',
                ],
            },
        ],
    },
    {
        id: 'web-sijil',
        title: 'Sijil (Certificates)',
        icon: '🎖️',
        intro: 'Urus senarai sijil dan pengiktirafan syarikat.',
        groups: [
            {
                heading: 'Tambah/edit sijil',
                steps: [
                    'Klik "Certificates" > "Add New" (atau buka rekod sedia ada untuk edit).',
                    'Isi tajuk sijil.',
                ],
            },
            {
                heading: 'Medan ACF (wajib diisi)',
                steps: [
                    'Icon — pilih dari senarai (wajib).',
                    'Category — wajib.',
                    'Issuer — pihak yang mengeluarkan sijil (wajib).',
                    'Summary — ringkasan sijil (wajib).',
                    'Details — repeater label/value, maksimum 6 baris.',
                    'Status/Validity — status sah laku sijil.',
                    'Display Order — nombor; nombor lebih kecil dipaparkan dahulu.',
                ],
            },
            {
                heading: 'Di mana ia dipaparkan',
                steps: [
                    'Sijil dipaparkan di halaman Certificates.',
                ],
            },
        ],
    },
    {
        id: 'web-aktiviti',
        title: 'Aktiviti (Activities)',
        icon: '📸',
        intro: 'Urus rekod aktiviti dan acara syarikat.',
        groups: [
            {
                heading: 'Tambah/edit aktiviti',
                steps: [
                    'Klik "Activities" > "Add New" (atau buka rekod sedia ada untuk edit).',
                    'Isi tajuk dan kandungan (content), muat naik gambar utama (featured image).',
                ],
            },
            {
                heading: 'Medan ACF',
                steps: [
                    'Photo 1 hingga Photo 8 — muat naik gambar aktiviti.',
                    'Display Order — nombor; nombor lebih kecil dipaparkan dahulu.',
                ],
            },
            {
                heading: 'Di mana ia dipaparkan',
                steps: [
                    'Aktiviti dipaparkan di halaman Activity dan halaman butiran (detail page) aktiviti berkenaan.',
                ],
            },
        ],
    },
    {
        id: 'web-media',
        title: 'Gambar & Media',
        icon: '🖼️',
        intro: 'Panduan memuat naik dan menguruskan gambar.',
        groups: [
            {
                heading: 'Muat naik gambar',
                steps: [
                    'Gambar boleh dimuat naik terus melalui menu "Media", atau terus dari medan gambar (photo field) semasa mengedit Service/Project/Activity/Certificate.',
                    'Guna format JPG, PNG atau WebP.',
                    'Mampatkan (compress) gambar besar supaya bersaiz beberapa ratus KB sahaja — elakkan gambar bersaiz beberapa MB.',
                    'Namakan fail dengan nama yang jelas dan mudah difahami (cth: "tapak-projek-a-1.jpg"), bukan nama automatik kamera.',
                ],
            },
        ],
    },
    {
        id: 'web-terbit',
        title: 'Menerbitkan & Melihat Perubahan',
        icon: '🚀',
        intro: 'Bagaimana dan bila perubahan CMS kelihatan di laman web.',
        groups: [
            {
                heading: 'Bagaimana perubahan sampai ke laman web',
                steps: [
                    'Laman web awam (mge-eng.com) adalah laman statik yang DIBINA SEMULA (rebuild) — ia TIDAK dipaparkan terus daripada WordPress secara langsung.',
                    'Menerbitkan atau mengemas kini rekod Project, Service atau Gallery akan mencetuskan rebuild automatik.',
                    'Satu notis akan muncul dalam WP admin apabila rebuild dicetuskan.',
                    'Perubahan akan kelihatan di laman web dalam masa lebih kurang 2–3 minit; terdapat "debounce" 2 minit supaya beberapa kali edit pantas digabungkan menjadi satu rebuild.',
                ],
            },
            {
                heading: 'Aktiviti dan Sijil — perlu tindakan tambahan',
                steps: [
                    'Menerbitkan atau mengemas kini Activity atau Certificate TIDAK mencetuskan rebuild automatik.',
                    'Selepas mengedit Activity atau Certificate, maklumkan kepada developer/admin untuk jalankan rebuild.',
                    'Alternatif: buat dan simpan sedikit perubahan kecil pada mana-mana Project atau Service untuk mencetuskan rebuild.',
                    'Jangan jangka perubahan terus kelihatan selepas "refresh" — beri masa 2–3 minit selepas rebuild dicetuskan.',
                ],
            },
        ],
    },
    {
        id: 'web-borang',
        title: 'Borang Hubungi & Perkara yang Perlu Dielakkan',
        icon: '📬',
        intro: 'Cara borang hubungi berfungsi dan sebab-sebab elak buat perubahan tertentu.',
        groups: [
            {
                heading: 'Borang Hubungi (Contact form)',
                steps: [
                    'Penghantaran dari borang di halaman Contact akan dihantar terus ke emel admin laman web.',
                    'Penghantaran borang TIDAK disimpan dalam WordPress — semak peti emel admin secara berkala supaya tiada pertanyaan pelanggan terlepas.',
                ],
            },
            {
                heading: 'Apa yang TIDAK boleh diedit dalam CMS',
                steps: [
                    'Teks "About Us" dan gambar pasukan (team photos).',
                    'Privacy Policy.',
                    'Alamat, telefon, emel dan peta di halaman Contact.',
                    'Menu navigasi dan footer laman web.',
                    'Semua di atas berada dalam kod laman web — hubungi developer untuk perubahan.',
                ],
            },
            {
                heading: 'Perkara yang perlu dielakkan',
                steps: [
                    'Jangan tukar "slug" (pautan URL) rekod sedia ada tanpa keperluan — ini akan mematahkan pautan sedia ada.',
                    'Jangan padam rekod yang sedang dipaparkan di laman web tanpa gantinya.',
                    'Pastikan semua medan wajib diisi — medan wajib yang kosong akan kelihatan kosong di laman web.',
                    'Jangan tukar tema (theme) WordPress atau nyahaktifkan plugin "MGE Headless Core" atau "Advanced Custom Fields" — ini akan merosakkan laman web.',
                    'Jangan edit Pages atau Posts dalam WordPress dengan jangkaan ia akan muncul di laman web — ia tidak digunakan.',
                ],
            },
        ],
    },
];

const MANUALS = {
    sistem: {
        label: 'Sistem MGE-PMS',
        tagline: 'Panduan ringkas cara menggunakan setiap modul. Cari topik di bawah, atau pilih modul di menu sisi.',
        placeholder: 'Cari topik… (cth: cuti, BQ, jentera)',
        sections: SISTEM_SECTIONS,
    },
    web: {
        label: 'Laman Web mge-eng.com',
        tagline: 'Panduan ringkas menguruskan kandungan laman web syarikat melalui CMS. Cari topik di bawah, atau pilih bahagian di menu sisi.',
        placeholder: 'Cari topik… (cth: projek, perkhidmatan, gambar)',
        sections: WEB_SECTIONS,
    },
};

// Serlahkan (highlight) padanan carian dalam teks.
function Highlight({ text, query }) {
    if (!query) return text;
    const idx = text.toLowerCase().indexOf(query.toLowerCase());
    if (idx === -1) return text;
    return (
        <>
            {text.slice(0, idx)}
            <mark className="rounded bg-amber-200/70 px-0.5 text-slate-900">{text.slice(idx, idx + query.length)}</mark>
            {text.slice(idx + query.length)}
        </>
    );
}

export default function Panduan() {
    const [searchParams, setSearchParams] = useSearchParams();
    const initialManual = searchParams.get('manual') === 'web' ? 'web' : 'sistem';
    const [manual, setManual] = useState(initialManual);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(MANUALS[initialManual].sections[0].id);
    const [menuOpen, setMenuOpen] = useState(false);
    const searchRef = useRef(null);

    const current = MANUALS[manual];
    const q = query.trim().toLowerCase();

    const switchManual = (key) => {
        if (key === manual) return;
        setManual(key);
        setQuery('');
        setActive(MANUALS[key].sections[0].id);
        setMenuOpen(false);
        const next = new URLSearchParams(searchParams);
        if (key === 'sistem') next.delete('manual'); else next.set('manual', key);
        setSearchParams(next, { replace: true });
    };

    // Tapis modul, kumpulan dan langkah mengikut carian.
    const filtered = useMemo(() => {
        if (!q) return current.sections;
        return current.sections
            .map((s) => {
                const sectionMatch = s.title.toLowerCase().includes(q) || s.intro.toLowerCase().includes(q);
                const groups = s.groups
                    .map((g) => {
                        const headingMatch = g.heading.toLowerCase().includes(q);
                        const steps = sectionMatch || headingMatch ? g.steps : g.steps.filter((st) => st.toLowerCase().includes(q));
                        return { ...g, steps, _keep: headingMatch || steps.length > 0 };
                    })
                    .filter((g) => sectionMatch || g._keep);
                return { ...s, groups };
            })
            .filter((s) => s.groups.length > 0);
    }, [q, current.sections]);

    const totalHits = useMemo(() => {
        if (!q) return 0;
        return filtered.reduce((n, s) => n + s.groups.reduce((m, g) => m + g.steps.length, 0), 0);
    }, [filtered, q]);

    // Scroll-spy: serlahkan modul aktif dalam sidebar semasa menatal.
    useEffect(() => {
        if (q) return; // tiada spy semasa mencari
        const obs = new IntersectionObserver(
            (entries) => {
                const vis = entries.filter((e) => e.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                if (vis[0]) setActive(vis[0].target.id);
            },
            { rootMargin: '-96px 0px -60% 0px', threshold: [0, 0.25, 0.5, 1] },
        );
        current.sections.forEach((s) => { const el = document.getElementById(s.id); if (el) obs.observe(el); });
        return () => obs.disconnect();
    }, [q, current.sections]);

    // Pintasan papan kekunci "/" untuk fokus ke carian.
    useEffect(() => {
        const onKey = (e) => {
            if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                e.preventDefault();
                searchRef.current?.focus();
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    const go = (id) => {
        setMenuOpen(false);
        setActive(id);
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800">
            {/* Top bar */}
            <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
                    <div className="flex items-center gap-3">
                        <img src="/logo.png" alt="MGE-PMS" className="h-9 w-9 rounded-lg object-contain" />
                        <div>
                            <p className="text-sm font-bold leading-tight text-slate-900">MGE-PMS</p>
                            <p className="text-xs text-slate-500">Panduan Pengguna</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setMenuOpen((v) => !v)} className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 lg:hidden">Menu</button>
                        <Link to="/login" className="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700">Log Masuk</Link>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-6xl px-4 py-8">
                {/* Hero + search */}
                <div className="mb-8 overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-green-700 p-6 text-white shadow-sm sm:p-8">
                    <h1 className="text-2xl font-bold sm:text-3xl">Panduan Pengguna MGE-PMS</h1>
                    <div className="mt-4 inline-flex rounded-full bg-white/10 p-1">
                        {Object.entries(MANUALS).map(([key, m]) => (
                            <button
                                key={key}
                                onClick={() => switchManual(key)}
                                className={`rounded-full px-4 py-1.5 text-sm font-semibold transition-colors ${manual === key ? 'bg-white text-emerald-700' : 'text-white hover:bg-white/20'}`}
                            >
                                {m.label}
                            </button>
                        ))}
                    </div>
                    <p className="mt-4 max-w-2xl text-sm text-emerald-50/90">
                        {current.tagline}
                    </p>
                    <div className="relative mt-5 max-w-xl">
                        <svg className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
                        <input
                            ref={searchRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={current.placeholder}
                            className="w-full rounded-xl border border-white bg-white py-3.5 pl-11 pr-10 text-sm font-medium text-slate-900 shadow-xl ring-1 ring-black/10 placeholder:font-normal placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        {query && (
                            <button onClick={() => { setQuery(''); searchRef.current?.focus(); }} className="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Kosongkan">
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        )}
                    </div>
                    {q && (
                        <p className="mt-2 text-xs text-emerald-50/80">{totalHits} hasil dalam {filtered.length} modul untuk “{query.trim()}”.</p>
                    )}
                </div>

                <div className="flex gap-8">
                    {/* Sidebar */}
                    <aside className={`${menuOpen ? 'block' : 'hidden'} fixed inset-x-0 top-[57px] z-20 max-h-[70vh] overflow-y-auto border-b border-slate-200 bg-white p-4 shadow-lg lg:static lg:block lg:max-h-none lg:w-64 lg:shrink-0 lg:overflow-visible lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none`}>
                        <nav className="lg:sticky lg:top-24">
                            <p className="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-slate-400">Kandungan</p>
                            <ul className="space-y-0.5">
                                {current.sections.map((s) => {
                                    const visible = !q || filtered.some((f) => f.id === s.id);
                                    return (
                                        <li key={s.id}>
                                            <button
                                                onClick={() => go(s.id)}
                                                disabled={!visible}
                                                className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors ${active === s.id && !q ? 'bg-emerald-50 font-semibold text-emerald-700' : visible ? 'text-slate-600 hover:bg-slate-100' : 'cursor-default text-slate-300'}`}
                                            >
                                                <span aria-hidden>{s.icon}</span> {s.title}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </nav>
                    </aside>

                    {/* Content */}
                    <main className="min-w-0 flex-1">
                        {filtered.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white py-16 text-center">
                                <p className="text-4xl">🔍</p>
                                <p className="mt-3 text-sm font-medium text-slate-600">Tiada hasil untuk “{query.trim()}”.</p>
                                <p className="mt-1 text-xs text-slate-400">Cuba kata kunci lain, cth: “cuti”, “projek”, “permit”.</p>
                            </div>
                        ) : (
                            <div className="space-y-8">
                                {filtered.map((s) => (
                                    <section key={s.id} id={s.id} className="scroll-mt-28">
                                        <div className="mb-3 flex items-center gap-3">
                                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-xl" aria-hidden>{s.icon}</span>
                                            <div>
                                                <h2 className="text-lg font-bold text-slate-900"><Highlight text={s.title} query={q} /></h2>
                                                <p className="text-sm text-slate-500"><Highlight text={s.intro} query={q} /></p>
                                            </div>
                                        </div>

                                        <div className="space-y-4">
                                            {s.groups.map((g, gi) => (
                                                <div key={gi} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                                                    <h3 className="mb-3 text-sm font-semibold text-slate-900"><Highlight text={g.heading} query={q} /></h3>
                                                    <ol className="space-y-2.5">
                                                        {g.steps.map((step, si) => (
                                                            <li key={si} className="flex gap-3 text-sm leading-relaxed text-slate-700">
                                                                <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-700">{si + 1}</span>
                                                                <span><Highlight text={step} query={q} /></span>
                                                            </li>
                                                        ))}
                                                    </ol>
                                                </div>
                                            ))}
                                        </div>
                                    </section>
                                ))}
                            </div>
                        )}

                        <footer className="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400">
                            © {new Date().getFullYear()} Multi Green Engineering Sdn. Bhd. ·{' '}
                            <a href="https://mge-eng.com" target="_blank" rel="noopener noreferrer" className="font-semibold text-emerald-600 hover:text-emerald-700">mge-eng.com</a>
                            {' '}·{' '}
                            <Link to="/login" className="font-semibold text-emerald-600 hover:text-emerald-700">Log Masuk</Link>
                        </footer>
                    </main>
                </div>
            </div>
        </div>
    );
}
