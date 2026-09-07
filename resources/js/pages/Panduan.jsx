import { useState, useMemo, useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';

/**
 * Panduan Pengguna (User Manual) — Ciri sokongan.
 *
 * Halaman dokumentasi awam (tanpa perlu log masuk) yang menerangkan cara
 * menggunakan setiap modul MGE-PMS dalam bahasa yang mudah. Dipautkan dari
 * halaman log masuk. Kandungan disusun sebagai data supaya senang dikemas kini.
 */

const SECTIONS = [
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
                    'Tasks — senarai tugasan projek.',
                    'Milestones — pencapaian penting projek.',
                    'Timeline — garis masa projek.',
                    'Site Logs — log kerja harian di tapak.',
                    'Sites — senarai tapak/zon dalam projek (untuk projek berbilang tapak).',
                    'Documents — fail dan dokumen projek.',
                    'Calendar — acara projek.',
                    'Discussions — perbincangan pasukan.',
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
                    'Rekod tarikh, cuaca, bilangan pekerja, kerja yang dijalankan dan jentera yang digunakan.',
                    'Jika projek ada tapak, pilih tapak yang berkenaan.',
                    'Log boleh diedit dalam tempoh yang ditetapkan; selepas itu ia dikunci.',
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
                    'Tutup surat — perlu rujukan penutup DAN sekurang-kurangnya satu dokumen dilampirkan.',
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
                    'Klik "Assets" di menu kiri.',
                    'Vehicles — rekod kenderaan/jentera, no. siri/casis dan penetapan kepada projek.',
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
                    'Notifications — peringatan automatik (loceng di atas kanan).',
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
                    'User Access / Roles — tetapkan peranan (Admin & HR, Finances & HR, Projects, Employee) dan kebenaran.',
                    'Pekerja yang berhenti kerja — tukar status kepada tidak aktif; log masuk mereka akan disekat automatik.',
                ],
            },
        ],
    },
];

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
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(SECTIONS[0].id);
    const [menuOpen, setMenuOpen] = useState(false);
    const searchRef = useRef(null);

    const q = query.trim().toLowerCase();

    // Tapis modul, kumpulan dan langkah mengikut carian.
    const filtered = useMemo(() => {
        if (!q) return SECTIONS;
        return SECTIONS
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
    }, [q]);

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
        SECTIONS.forEach((s) => { const el = document.getElementById(s.id); if (el) obs.observe(el); });
        return () => obs.disconnect();
    }, [q]);

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
                    <p className="mt-2 max-w-2xl text-sm text-emerald-50/90">
                        Panduan ringkas cara menggunakan setiap modul. Cari topik di bawah, atau pilih modul di menu sisi.
                    </p>
                    <div className="relative mt-5 max-w-xl">
                        <svg className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
                        <input
                            ref={searchRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari topik… (cth: cuti, HIRARC, invois)"
                            className="w-full rounded-xl border-0 py-3 pl-11 pr-10 text-sm text-slate-800 shadow-lg ring-1 ring-black/5 focus:outline-none focus:ring-2 focus:ring-white"
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
                                {SECTIONS.map((s) => {
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
