// Resource file containing exported guide sections and manuals configuration

export const SISTEM_SECTIONS = [
    {
        id: 'bermula',
        title: 'Bermula',
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
            {
                heading: 'Data laporan bulanan (tab Report Data)',
                steps: [
                    'Buka projek, klik tab "Report Data".',
                    'Contract Particulars — nilai kontrak, WJP, tempoh, DLP, tarikh milikan/siap, CIDB dan insurans (kontrak utama perlu ditanda "Main contract" di Projects > Contracts).',
                    'Parties & Contacts — pihak dalam kontrak (Owner, SO, Consultant, Contractor, dll.) beserta alamat, logo dan pegawai untuk dihubungi.',
                    'Organisation Chart — jawatan dan garis pelaporan ahli projek.',
                    'Progress & Baseline — jadual CPM bulanan (scheduled %) dan rekod kemajuan setiap tempoh laporan; variance dan hari ahead/delay dikira automatik.',
                    'Delay Notices & Tests — notis kelewatan dan rekod ujian/pentauliahan.',
                    'Site-Log Categories — senarai kategori pekerja dan jentera khusus projek yang digunakan dalam borang Site Log.',
                    'Report Images — peta lokasi, akses tapak dan gambar kemajuan (label seperti "Aerial 1" supaya boleh dibandingkan bulan ke bulan).',
                ],
            },
            {
                heading: 'Laporan Bulanan (Monthly Reports)',
                steps: [
                    'Klik "Projects" > "Monthly Reports".',
                    'Klik "New Report": pilih projek, tempoh (tarikh akhir tempoh laporan), no. laporan dan label bulan; boleh salin seksyen statik (cover, parties, org chart, dll.) dari laporan lepas untuk projek yang sama.',
                    'Buka laporan untuk edit setiap seksyen — kemas kini data, tambah nota seksyen, dan togol "include" untuk pilih seksyen yang dipaparkan dalam PDF.',
                    'Klik "Save" untuk simpan satu seksyen atau "Save all" untuk simpan semua seksyen sekali gus.',
                    '"Reset to system data" buang suntingan (overrides) seksyen dan kembali kepada data sistem asal.',
                    '"Regenerate" jana semula data sistem bagi seksyen (atau semua seksyen) — suntingan (overrides) dan nota yang telah disimpan dikekalkan.',
                    'Klik "Finalise" apabila laporan sedia; laporan yang sudah finalise dikunci daripada suntingan. Klik "Reopen" untuk buka semula jika perlu labur.',
                    'Klik "Export PDF" untuk jana PDF laporan bulanan penuh.',
                    'Nota: carta S-curve, laman landscape, eksport Word (.docx) dan import program kerja (Gantt) akan disediakan dalam fasa akan datang.',
                    'Kebenaran: role dengan "reports.view" boleh lihat laporan dan eksport PDF; role dengan "reports.manage" boleh cipta, edit, regenerate dan finalise laporan.',
                ],
            },
        ],
    },
    {
        id: 'kontrak',
        title: 'Kontrak (Contracts)',
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

export const WEB_SECTIONS = [
    {
        id: 'web-mula',
        title: 'Bermula',
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

export const MANUALS = {
    sistem: {
        label: 'Sistem MGE-PMS',
        pageTitle: 'Panduan Sistem MGE-PMS',
        tagline: 'Panduan ringkas cara menggunakan setiap modul. Cari topik di bawah, atau pilih modul di menu sisi.',
        placeholder: 'Cari topik… (cth: cuti, BQ, jentera)',
        eyebrow: 'SISTEM',
        cardTitle: 'Panduan Sistem MGE-PMS',
        cardDescription: 'Modul projek, HR, kewangan, aset, keselamatan dan lain-lain dalam sistem pengurusan projek.',
        cardTopics: ['Projek', 'Kewangan', 'HR & Kehadiran', 'Keselamatan', 'Aset'],
        sections: SISTEM_SECTIONS,
    },
    web: {
        label: 'Laman Web mge-eng.com',
        pageTitle: 'Panduan Laman Web',
        tagline: 'Panduan ringkas menguruskan kandungan laman web syarikat melalui CMS. Cari topik di bawah, atau pilih bahagian di menu sisi.',
        placeholder: 'Cari topik… (cth: projek, perkhidmatan, gambar)',
        eyebrow: 'LAMAN WEB',
        cardTitle: 'Panduan Laman Web mge-eng.com',
        cardDescription: 'Menguruskan kandungan laman web korporat melalui WordPress CMS: perkhidmatan, projek, sijil, aktiviti dan gambar.',
        cardTopics: ['Perkhidmatan', 'Projek', 'Sijil', 'Aktiviti', 'Media'],
        sections: WEB_SECTIONS,
    },
};