import { useState, useMemo, useEffect, useRef } from 'react';
import { Link, useParams, Navigate } from 'react-router-dom';
import { MANUALS } from './content';

/**
 * Panduan Manual Reader — Scoped to specific manual (:manual).
 * Serves at route /panduan/:manual (where manual is 'sistem' or 'web').
 * Removes manual switcher and query params, focused search & scroll-spy.
 */

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

export default function PanduanManual() {
    const { manual } = useParams();
    const safeCurrent = MANUALS[manual] || MANUALS.sistem;

    // Hooks must be called unconditionally
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(safeCurrent.sections[0].id);
    const [menuOpen, setMenuOpen] = useState(false);
    const searchRef = useRef(null);

    const q = query.trim().toLowerCase();

    const filtered = useMemo(() => {
        if (!q) return safeCurrent.sections;
        return safeCurrent.sections.map((s) => {
            const sectionMatch = s.title.toLowerCase().includes(q) || s.intro.toLowerCase().includes(q);
            const groups = s.groups.map((g) => {
                const headingMatch = g.heading.toLowerCase().includes(q);
                const steps = sectionMatch || headingMatch ? g.steps : g.steps.filter((st) => st.toLowerCase().includes(q));
                return { ...g, steps, _keep: headingMatch || steps.length > 0 };
            }).filter((g) => sectionMatch || g._keep);
            return { ...s, groups };
        }).filter((s) => s.groups.length > 0);
    }, [q, safeCurrent.sections]);

    const totalHits = useMemo(() => {
        if (!q) return 0;
        return filtered.reduce((n, s) => n + s.groups.reduce((m, g) => m + g.steps.length, 0), 0);
    }, [filtered, q]);

    // Scroll-spy IntersectionObserver
    useEffect(() => {
        if (q) return; 
        const obs = new IntersectionObserver(
            (entries) => {
                const vis = entries.filter((e) => e.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio);
                if (vis[0]) setActive(vis[0].target.id);
            },
            { rootMargin: '-96px 0px -60% 0px', threshold: [0, 0.25, 0.5, 1] }
        );
        safeCurrent.sections.forEach((s) => {
            const el = document.getElementById(s.id);
            if (el) obs.observe(el);
        });
        return () => obs.disconnect();
    }, [q, safeCurrent.sections]);

    // Keyboard shortcut "/" to focus search
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

    // Guard check AFTER all hooks
    if (!MANUALS[manual]) {
        return <Navigate to="/panduan" replace />;
    }

    const current = MANUALS[manual];

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
                        <button 
                            onClick={() => setMenuOpen((v) => !v)} 
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 lg:hidden"
                        >
                            Menu
                        </button>
                        <Link 
                            to="/login" 
                            className="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            Log Masuk
                        </Link>
                    </div>
                </div>
            </header>

            <div className="mx-auto max-w-6xl px-4 py-8">
                {/* Back Link */}
                <Link 
                    to="/panduan" 
                    className="mb-4 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-emerald-600"
                >
                    ← Semua panduan
                </Link>

                {/* Hero */}
                <div className="mb-8 bg-white pb-4">
                    <h1 className="text-3xl font-semibold tracking-tight text-slate-900">{current.pageTitle}</h1>
                    <p className="mt-2 max-w-2xl text-sm text-slate-600">{current.tagline}</p>
                    
                    <div className="relative mt-5 max-w-xl">
                        <svg className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
                        </svg>
                        <input
                            ref={searchRef}
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={current.placeholder}
                            className="w-full rounded-xl border border-slate-200 bg-white py-3.5 pl-11 pr-10 text-sm font-medium text-slate-900 shadow-sm ring-1 ring-slate-200 placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        />
                        {query && (
                            <button 
                                onClick={() => { setQuery(''); searchRef.current?.focus(); }} 
                                className="absolute right-3 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" 
                                aria-label="Kosongkan"
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        )}
                    </div>
                    {q && (
                        <p className="mt-2 text-xs text-slate-500">{totalHits} hasil dalam {filtered.length} modul untuk "{query.trim()}"</p>
                    )}
                </div>

                <div className="flex gap-8">
                    {/* Sidebar */}
                    <aside className={`${menuOpen ? 'block' : 'hidden'} fixed inset-x-0 top-[57px] z-20 max-h-[70vh] overflow-y-auto border-b border-slate-200 bg-white p-4 shadow-lg lg:static lg:block lg:max-h-none lg:w-64 lg:shrink-0 lg:overflow-visible lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none`}>
                        <nav className="lg:sticky lg:top-24">
                            <p className="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-slate-400">Kandungan</p>
                            <ul className="space-y-0.5">
                                {current.sections.map((s, idx) => {
                                    const visible = !q || filtered.some((f) => f.id === s.id);
                                    return (
                                        <li key={s.id}>
                                            <button
                                                onClick={() => go(s.id)}
                                                disabled={!visible}
                                                className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors ${active === s.id && !q ? 'text-emerald-700 font-semibold border-l-2 border-emerald-500 pl-2' : visible ? 'text-slate-600 hover:bg-slate-100' : 'cursor-default text-slate-300'}`}
                                            >
                                                <span className="tabular-nums text-xs text-slate-400">{String(idx + 1).padStart(2, '0')}</span>
                                                {s.title}
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
                                <p className="mt-3 text-sm font-medium text-slate-600">Tiada hasil untuk "{query.trim()}".</p>
                                <p className="mt-1 text-xs text-slate-400">Cuba kata kunci lain, cth: "cuti", "projek", "permit".</p>
                            </div>
                        ) : (
                            <div className="space-y-8">
                                {filtered.map((s, filterIdx) => {
                                    // Find index in ORIGINAL sections array for numbering consistency
                                    const originalIndex = current.sections.findIndex(sec => sec.id === s.id);
                                    
                                    return (
                                        <section key={s.id} id={s.id} className="scroll-mt-28">
                                            <div className="mb-4 space-y-1">
                                                <p className="text-xs font-semibold tracking-wider text-emerald-600">{String(originalIndex + 1).padStart(2, '0')}</p>
                                                <h2 className="text-lg font-bold text-slate-900"><Highlight text={s.title} query={q} /></h2>
                                                <p className="text-sm text-slate-500"><Highlight text={s.intro} query={q} /></p>
                                            </div>

                                            <div className="space-y-4">
                                                {s.groups.map((g, gi) => (
                                                    <div key={gi} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md">
                                                        <h3 className="mb-3 text-sm font-semibold text-slate-900"><Highlight text={g.heading} query={q} /></h3>
                                                        <ol className="space-y-2.5">
                                                            {g.steps.map((step, si) => (
                                                                <li key={si} className="flex gap-3 text-sm leading-relaxed text-slate-700">
                                                                    <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-bold text-slate-600">{si + 1}</span>
                                                                    <span><Highlight text={step} query={q} /></span>
                                                                </li>
                                                            ))}
                                                        </ol>
                                                    </div>
                                                ))}
                                            </div>
                                        </section>
                                    );
                                })}
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
